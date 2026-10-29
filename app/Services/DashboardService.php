<?php

namespace App\Services;

use App\Entities\User;
use App\Models\UserModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Angka KPI dan data grafik dashboard (PRD §4 M2). Hasil di-cache 5 menit per user + filter.
 */
class DashboardService
{
    public const CACHE_TTL = 300;

    public function __construct(
        private readonly BaseConnection $db,
        private readonly UserModel $users,
    ) {
    }

    /**
     * Owner yang dihitung: sales → dirinya; manager/admin → cakupannya, atau satu sales bila difilter.
     *
     * @return list<int>|null null berarti semua data
     */
    public function ownerIds(User $viewer, ?int $ownerFilter): ?array
    {
        $scope = $this->users->scopeIds($viewer);

        if ($ownerFilter !== null && ! $viewer->isSales() && ($scope === null || in_array($ownerFilter, $scope, true))) {
            return [$ownerFilter];
        }

        return $scope;
    }

    /**
     * KPI + data grafik (di-cache).
     *
     * @return array{kpi: array<string, float|int|null>, pipeline: array<string, list<mixed>>, trend: array<string, list<mixed>>, generated_at: string}
     */
    public function data(User $viewer, ?int $ownerFilter = null): array
    {
        $ownerIds = $this->ownerIds($viewer, $ownerFilter);
        $key      = sprintf('dashboard_%d_%s', $viewer->id, $ownerIds === null ? 'all' : md5(implode(',', $ownerIds)));

        return cache()->remember($key, self::CACHE_TTL, fn (): array => $this->compute($ownerIds));
    }

    /**
     * @param list<int>|null $ownerIds
     *
     * @return array{kpi: array<string, float|int|null>, pipeline: array<string, list<mixed>>, trend: array<string, list<mixed>>, generated_at: string}
     */
    public function compute(?array $ownerIds): array
    {
        $monthStart = Time::today()->setDay(1)->toDateTimeString();

        $newLeads = $this->scoped($this->db->table('leads'), 'leads.owner_id', $ownerIds)
            ->where('leads.deleted_at', null)
            ->where('leads.created_at >=', $monthStart)
            ->countAllResults();

        $open = $this->openDeals($ownerIds)
            ->select('COUNT(*) AS total, COALESCE(SUM(deals.amount), 0) AS amount, COALESCE(SUM(deals.amount * deals.probability / 100), 0) AS weighted')
            ->get()->getRowArray();

        $wonMonth = $this->closedDeals($ownerIds, 'is_won')
            ->select('COALESCE(SUM(deals.amount), 0) AS amount, COUNT(*) AS total')
            ->where('deals.closed_at >=', $monthStart)
            ->get()->getRowArray();

        $since = Time::today()->subDays(90)->toDateTimeString();
        $won   = $this->closedDeals($ownerIds, 'is_won')->where('deals.closed_at >=', $since)->countAllResults();
        $lost  = $this->closedDeals($ownerIds, 'is_lost')->where('deals.closed_at >=', $since)->countAllResults();

        return [
            'kpi' => [
                'newLeads'      => $newLeads,
                'openDeals'     => (int) $open['total'],
                'pipelineValue' => (float) $open['amount'],
                'weightedValue' => (float) $open['weighted'],
                'wonValue'      => (float) $wonMonth['amount'],
                'wonCount'      => (int) $wonMonth['total'],
                'winRate'       => $won + $lost === 0 ? null : round($won / ($won + $lost) * 100, 1),
            ],
            'pipeline'     => $this->pipelineByStage($ownerIds),
            'trend'        => $this->wonTrend($ownerIds),
            'generated_at' => Time::now()->toDateTimeString(),
        ];
    }

    /**
     * Nilai deal terbuka per tahap.
     *
     * @param list<int>|null $ownerIds
     *
     * @return array{labels: list<string>, values: list<float>, counts: list<int>}
     */
    private function pipelineByStage(?array $ownerIds): array
    {
        $totals = [];

        foreach ($this->openDeals($ownerIds)->select('deals.stage_id, COUNT(*) AS total, SUM(deals.amount) AS amount')->groupBy('deals.stage_id')->get()->getResultArray() as $row) {
            $totals[(int) $row['stage_id']] = $row;
        }

        $result = ['labels' => [], 'values' => [], 'counts' => []];
        $stages = $this->db->table('pipeline_stages')->where('is_won', 0)->where('is_lost', 0)->orderBy('sort_order')->get()->getResultArray();

        foreach ($stages as $stage) {
            $result['labels'][] = $stage['name'];
            $result['values'][] = (float) ($totals[(int) $stage['id']]['amount'] ?? 0);
            $result['counts'][] = (int) ($totals[(int) $stage['id']]['total'] ?? 0);
        }

        return $result;
    }

    /**
     * Nilai Won per bulan, 6 bulan terakhir (termasuk bulan berjalan).
     *
     * @param list<int>|null $ownerIds
     *
     * @return array{labels: list<string>, values: list<float>}
     */
    private function wonTrend(?array $ownerIds): array
    {
        $start = Time::today()->setDay(1)->subMonths(5);
        $rows  = $this->closedDeals($ownerIds, 'is_won')
            ->select("DATE_FORMAT(deals.closed_at, '%Y-%m') AS period, SUM(deals.amount) AS amount", false)
            ->where('deals.closed_at >=', $start->toDateTimeString())
            ->groupBy('period')
            ->get()->getResultArray();
        $values = array_column($rows, 'amount', 'period');
        $result = ['labels' => [], 'values' => []];

        for ($i = 0; $i < 6; $i++) {
            $month              = $start->addMonths($i);
            $result['labels'][] = nama_bulan($month->getMonth()) . ' ' . $month->getYear();
            $result['values'][] = (float) ($values[$month->format('Y-m')] ?? 0);
        }

        return $result;
    }

    /**
     * @param list<int>|null $ownerIds
     */
    private function openDeals(?array $ownerIds): BaseBuilder
    {
        return $this->scoped($this->db->table('deals'), 'deals.owner_id', $ownerIds)
            ->join('pipeline_stages', 'pipeline_stages.id = deals.stage_id')
            ->where('deals.deleted_at', null)
            ->where('pipeline_stages.is_won', 0)
            ->where('pipeline_stages.is_lost', 0);
    }

    /**
     * @param list<int>|null $ownerIds
     */
    private function closedDeals(?array $ownerIds, string $flag): BaseBuilder
    {
        return $this->scoped($this->db->table('deals'), 'deals.owner_id', $ownerIds)
            ->join('pipeline_stages', 'pipeline_stages.id = deals.stage_id')
            ->where('deals.deleted_at', null)
            ->where('pipeline_stages.' . $flag, 1);
    }

    /**
     * @param list<int>|null $ownerIds
     */
    private function scoped(BaseBuilder $builder, string $column, ?array $ownerIds): BaseBuilder
    {
        return $ownerIds === null ? $builder : $builder->whereIn($column, $ownerIds === [] ? [0] : $ownerIds);
    }
}
