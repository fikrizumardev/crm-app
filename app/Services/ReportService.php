<?php

namespace App\Services;

use App\Entities\User;
use App\Models\UserModel;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;

/**
 * Laporan untuk admin/manager (PRD §4 M9). Manager hanya melihat timnya.
 */
class ReportService
{
    public const PRESETS = [
        'this_month'   => 'Bulan ini',
        'last_month'   => 'Bulan lalu',
        'this_quarter' => 'Kuartal ini',
        'this_year'    => 'Tahun ini',
        'last_90'      => '90 hari terakhir',
    ];

    public function __construct(
        private readonly BaseConnection $db,
        private readonly UserModel $users,
    ) {
    }

    /**
     * Rentang periode dari preset atau tanggal manual (default bulan ini).
     *
     * @return array{from: string, to: string, preset: string|null, label: string}
     */
    public function period(?string $preset, ?string $from, ?string $to): array
    {
        $valid = static fn (?string $d): bool => $d !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) === 1 && strtotime($d) !== false;

        if (! isset(self::PRESETS[$preset ?? '']) && $valid($from) && $valid($to)) {
            [$from, $to] = $from <= $to ? [$from, $to] : [$to, $from];

            return ['from' => $from, 'to' => $to, 'preset' => null, 'label' => tanggal_id($from) . ' – ' . tanggal_id($to)];
        }

        $preset = isset(self::PRESETS[$preset ?? '']) ? $preset : 'this_month';
        $today  = Time::today();

        [$start, $end] = match ($preset) {
            'last_month'   => [$today->setDay(1)->subMonths(1), $today->setDay(1)->subDays(1)],
            'this_quarter' => [Time::create($today->getYear(), (int) (floor(($today->getMonth() - 1) / 3) * 3 + 1), 1), $today],
            'this_year'    => [Time::create($today->getYear(), 1, 1), $today],
            'last_90'      => [$today->subDays(89), $today],
            default        => [$today->setDay(1), $today],
        };

        return ['from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d'), 'preset' => $preset, 'label' => self::PRESETS[$preset]];
    }

    /**
     * Kinerja per sales dalam periode.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, mixed>}
     */
    public function salesPerformance(User $viewer, string $from, string $to): array
    {
        $scope = $this->users->scopeIds($viewer);
        $users = $this->db->table('users')->select('id, name, username')->where('role', 'sales')->where('deleted_at', null)->orderBy('name');

        if ($scope !== null) {
            $users->whereIn('id', $scope);
        }

        $people = $users->get()->getResultArray();
        $ids    = array_map('intval', array_column($people, 'id'));
        [$start, $end] = [$from . ' 00:00:00', $to . ' 23:59:59'];

        $leads = $this->countBy('leads', 'owner_id', 'created_at', $ids, $start, $end);
        $deals = $this->countBy('deals', 'owner_id', 'created_at', $ids, $start, $end);
        $acts  = $this->countBy('activities', 'user_id', 'activity_at', $ids, $start, $end);
        $won   = $this->closedBy('is_won', $ids, $start, $end);
        $lost  = $this->closedBy('is_lost', $ids, $start, $end);

        $rows   = [];
        $totals = ['leads' => 0, 'deals' => 0, 'won_count' => 0, 'won_value' => 0.0, 'lost_count' => 0, 'activities' => 0];

        foreach ($people as $person) {
            $id  = (int) $person['id'];
            $row = [
                'id'         => $id,
                'name'       => $person['name'],
                'leads'      => $leads[$id] ?? 0,
                'deals'      => $deals[$id] ?? 0,
                'won_count'  => $won[$id]['count'] ?? 0,
                'won_value'  => $won[$id]['value'] ?? 0.0,
                'lost_count' => $lost[$id]['count'] ?? 0,
                'activities' => $acts[$id] ?? 0,
            ];
            $row['win_rate'] = $this->rate($row['won_count'], $row['won_count'] + $row['lost_count']);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += $row[$key];
            }

            $rows[] = $row;
        }

        $totals['win_rate'] = $this->rate($totals['won_count'], $totals['won_count'] + $totals['lost_count']);

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * Ringkasan Won/Lost dan rincian alasan kalah dalam periode (berdasarkan tanggal tutup).
     *
     * @return array{won_count: int, won_value: float, lost_count: int, lost_value: float, win_rate: float|null, reasons: list<array<string, mixed>>}
     */
    public function winLoss(User $viewer, string $from, string $to): array
    {
        $scope = $this->users->scopeIds($viewer);
        [$start, $end] = [$from . ' 00:00:00', $to . ' 23:59:59'];

        $summary = $this->scoped($this->db->table('deals'), 'deals.owner_id', $scope)
            ->select('SUM(pipeline_stages.is_won) AS won_count, SUM(CASE WHEN pipeline_stages.is_won = 1 THEN deals.amount ELSE 0 END) AS won_value')
            ->select('SUM(pipeline_stages.is_lost) AS lost_count, SUM(CASE WHEN pipeline_stages.is_lost = 1 THEN deals.amount ELSE 0 END) AS lost_value')
            ->join('pipeline_stages', 'pipeline_stages.id = deals.stage_id')
            ->where('deals.deleted_at', null)
            ->where('deals.closed_at >=', $start)
            ->where('deals.closed_at <=', $end)
            ->get()->getRowArray();

        $reasonRows = $this->scoped($this->db->table('deals'), 'deals.owner_id', $scope)
            ->select('deals.lost_reason_id, COUNT(*) AS total, SUM(deals.amount) AS amount')
            ->join('pipeline_stages', 'pipeline_stages.id = deals.stage_id')
            ->where('deals.deleted_at', null)
            ->where('pipeline_stages.is_lost', 1)
            ->where('deals.closed_at >=', $start)
            ->where('deals.closed_at <=', $end)
            ->groupBy('deals.lost_reason_id')
            ->get()->getResultArray();
        $byReason = array_column($reasonRows, null, 'lost_reason_id');

        $lostCount = (int) ($summary['lost_count'] ?? 0);
        $reasons   = [];

        foreach ($this->db->table('lost_reasons')->orderBy('name')->get()->getResultArray() as $reason) {
            $count     = (int) ($byReason[$reason['id']]['total'] ?? 0);
            $reasons[] = [
                'name'    => $reason['name'],
                'count'   => $count,
                'value'   => (float) ($byReason[$reason['id']]['amount'] ?? 0),
                'percent' => $this->rate($count, $lostCount),
            ];
        }

        usort($reasons, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $wonCount = (int) ($summary['won_count'] ?? 0);

        return [
            'won_count'  => $wonCount,
            'won_value'  => (float) ($summary['won_value'] ?? 0),
            'lost_count' => $lostCount,
            'lost_value' => (float) ($summary['lost_value'] ?? 0),
            'win_rate'   => $this->rate($wonCount, $wonCount + $lostCount),
            'reasons'    => $reasons,
        ];
    }

    /**
     * Kinerja sumber lead: jumlah lead masuk dalam periode dan tingkat konversinya.
     *
     * @return list<array<string, mixed>>
     */
    public function leadSources(User $viewer, string $from, string $to): array
    {
        $scope = $this->users->scopeIds($viewer);

        $stats = $this->scoped($this->db->table('leads'), 'leads.owner_id', $scope)
            ->select('leads.source_id, COUNT(*) AS total')
            ->select("SUM(CASE WHEN leads.status IN ('qualified', 'converted') THEN 1 ELSE 0 END) AS qualified")
            ->select("SUM(CASE WHEN leads.status = 'converted' THEN 1 ELSE 0 END) AS converted")
            ->select("SUM(CASE WHEN leads.status = 'unqualified' THEN 1 ELSE 0 END) AS unqualified")
            ->where('leads.deleted_at', null)
            ->where('leads.created_at >=', $from . ' 00:00:00')
            ->where('leads.created_at <=', $to . ' 23:59:59')
            ->groupBy('leads.source_id')
            ->get()->getResultArray();
        $bySource = [];

        foreach ($stats as $row) {
            $bySource[(string) ($row['source_id'] ?? '')] = $row;
        }

        $sources   = $this->db->table('lead_sources')->orderBy('name')->get()->getResultArray();
        $sources[] = ['id' => '', 'name' => 'Tanpa sumber'];
        $rows      = [];

        foreach ($sources as $source) {
            $row = $bySource[(string) $source['id']] ?? null;

            if ($row === null && $source['id'] === '') {
                continue;
            }

            $total  = (int) ($row['total'] ?? 0);
            $rows[] = [
                'name'        => $source['name'],
                'leads'       => $total,
                'qualified'   => (int) ($row['qualified'] ?? 0),
                'converted'   => (int) ($row['converted'] ?? 0),
                'unqualified' => (int) ($row['unqualified'] ?? 0),
                'rate'        => $this->rate((int) ($row['converted'] ?? 0), $total),
            ];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['converted'], $b['leads']] <=> [$a['converted'], $a['leads']]);

        return $rows;
    }

    /**
     * Forecast per bulan perkiraan closing: Σ(nilai × probabilitas) deal terbuka.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float|int>}
     */
    public function forecast(User $viewer, int $months = 6): array
    {
        $scope = $this->users->scopeIds($viewer);
        $start = Time::today()->setDay(1);

        $stats = $this->scoped($this->db->table('deals'), 'deals.owner_id', $scope)
            ->select("CASE WHEN deals.expected_close_date IS NULL THEN 'none' WHEN deals.expected_close_date < '" . $start->format('Y-m-d') . "' THEN 'overdue' ELSE DATE_FORMAT(deals.expected_close_date, '%Y-%m') END AS bucket", false)
            ->select('COUNT(*) AS total, SUM(deals.amount) AS amount, SUM(deals.amount * deals.probability / 100) AS weighted')
            ->join('pipeline_stages', 'pipeline_stages.id = deals.stage_id')
            ->where('deals.deleted_at', null)
            ->where('pipeline_stages.is_won', 0)
            ->where('pipeline_stages.is_lost', 0)
            ->groupBy('bucket')
            ->get()->getResultArray();
        $byBucket = array_column($stats, null, 'bucket');

        $buckets = ['overdue' => 'Lewat perkiraan (belum ditutup)'];

        for ($i = 0; $i < $months; $i++) {
            $month                            = $start->addMonths($i);
            $buckets[$month->format('Y-m')] = nama_bulan($month->getMonth(), false) . ' ' . $month->getYear();
        }

        $later = array_filter(array_keys($byBucket), static fn ($key): bool => ! isset($buckets[$key]) && $key !== 'none');
        $rows  = [];

        foreach ($buckets as $key => $label) {
            $rows[] = $this->forecastRow($label, [$byBucket[$key] ?? null]);
        }

        $rows[] = $this->forecastRow('Setelah ' . end($buckets), array_map(static fn ($key) => $byBucket[$key], $later));
        $rows[] = $this->forecastRow('Tanpa perkiraan tanggal', [$byBucket['none'] ?? null]);

        $totals = ['count' => 0, 'amount' => 0.0, 'weighted' => 0.0];

        foreach ($rows as $row) {
            $totals['count'] += $row['count'];
            $totals['amount'] += $row['amount'];
            $totals['weighted'] += $row['weighted'];
        }

        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * @param list<array<string, mixed>|null> $parts
     *
     * @return array{label: string, count: int, amount: float, weighted: float}
     */
    private function forecastRow(string $label, array $parts): array
    {
        $row = ['label' => $label, 'count' => 0, 'amount' => 0.0, 'weighted' => 0.0];

        foreach (array_filter($parts) as $part) {
            $row['count'] += (int) $part['total'];
            $row['amount'] += (float) $part['amount'];
            $row['weighted'] += (float) $part['weighted'];
        }

        return $row;
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, int>
     */
    private function countBy(string $table, string $userColumn, string $dateColumn, array $ids, string $start, string $end): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->db->table($table)
            ->select("{$userColumn} AS uid, COUNT(*) AS total")
            ->whereIn($userColumn, $ids)
            ->where('deleted_at', null)
            ->where("{$dateColumn} >=", $start)
            ->where("{$dateColumn} <=", $end)
            ->groupBy($userColumn)
            ->get()->getResultArray();

        return array_map('intval', array_column($rows, 'total', 'uid'));
    }

    /**
     * @param list<int> $ids
     *
     * @return array<int, array{count: int, value: float}>
     */
    private function closedBy(string $flag, array $ids, string $start, string $end): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->db->table('deals')
            ->select('deals.owner_id AS uid, COUNT(*) AS total, SUM(deals.amount) AS amount')
            ->join('pipeline_stages', 'pipeline_stages.id = deals.stage_id')
            ->whereIn('deals.owner_id', $ids)
            ->where('deals.deleted_at', null)
            ->where('pipeline_stages.' . $flag, 1)
            ->where('deals.closed_at >=', $start)
            ->where('deals.closed_at <=', $end)
            ->groupBy('deals.owner_id')
            ->get()->getResultArray();

        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['uid']] = ['count' => (int) $row['total'], 'value' => (float) $row['amount']];
        }

        return $result;
    }

    /**
     * @param list<int>|null $scope
     */
    private function scoped(BaseBuilder $builder, string $column, ?array $scope): BaseBuilder
    {
        return $scope === null ? $builder : $builder->whereIn($column, $scope === [] ? [0] : $scope);
    }

    private function rate(int $part, int $whole): ?float
    {
        return $whole === 0 ? null : round($part / $whole * 100, 1);
    }
}
