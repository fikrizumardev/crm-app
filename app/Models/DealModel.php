<?php

namespace App\Models;

use App\Entities\User;
use CodeIgniter\I18n\Time;

class DealModel extends OwnedModel
{
    protected $table         = 'deals';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'title',
        'company_id',
        'contact_id',
        'stage_id',
        'amount',
        'probability',
        'expected_close_date',
        'closed_at',
        'lost_reason_id',
        'owner_id',
    ];
    protected $validationRules = [
        'title'               => ['label' => 'Judul deal', 'rules' => 'required|min_length[3]|max_length[200]'],
        'company_id'          => ['label' => 'Perusahaan', 'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[companies.id]'],
        'contact_id'          => ['label' => 'Kontak', 'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[contacts.id]'],
        'stage_id'            => ['label' => 'Tahap', 'rules' => 'required|is_natural_no_zero|is_not_unique[pipeline_stages.id]'],
        'amount'              => ['label' => 'Nilai deal', 'rules' => 'required|decimal|greater_than_equal_to[0]|less_than[10000000000000]'],
        'probability'         => ['label' => 'Probabilitas', 'rules' => 'required|is_natural|less_than_equal_to[100]'],
        'expected_close_date' => ['label' => 'Perkiraan closing', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
        'lost_reason_id'      => ['label' => 'Alasan kalah', 'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[lost_reasons.id]'],
        'owner_id'            => ['label' => 'Owner', 'rules' => 'required|is_natural_no_zero|is_not_unique[users.id]'],
    ];

    /**
     * Status turunan dari tahap: open | won | lost.
     */
    public const STATUSES = ['open' => 'Terbuka', 'won' => 'Won', 'lost' => 'Lost'];

    private const SORTABLE = [
        'title'      => 'deals.title',
        'company'    => 'companies.name',
        'amount'     => 'deals.amount',
        'stage'      => 'pipeline_stages.sort_order',
        'close_date' => 'deals.expected_close_date',
        'owner'      => 'owners.name',
        'created_at' => 'deals.created_at',
    ];

    /**
     * Deal yang sudah Won tidak boleh dihapus sales.
     */
    public function isLocked(array $record): bool
    {
        if (array_key_exists('is_won', $record)) {
            return (int) $record['is_won'] === 1;
        }

        $stage = model(PipelineStageModel::class)->find($record['stage_id'] ?? 0);

        return $stage !== null && (int) $stage['is_won'] === 1;
    }

    /**
     * Select standar dengan perusahaan, kontak, tahap, owner, dan alasan kalah.
     *
     * @return $this
     */
    public function withRelations(): static
    {
        return $this->select('deals.*, companies.name AS company_name, contacts.first_name AS contact_first_name, contacts.last_name AS contact_last_name')
            ->select('pipeline_stages.name AS stage_name, pipeline_stages.sort_order AS stage_order, pipeline_stages.is_won, pipeline_stages.is_lost')
            ->select('owners.name AS owner_name, lost_reasons.name AS lost_reason_name')
            ->join('pipeline_stages', 'pipeline_stages.id = deals.stage_id')
            ->join('companies', 'companies.id = deals.company_id AND companies.deleted_at IS NULL', 'left')
            ->join('contacts', 'contacts.id = deals.contact_id AND contacts.deleted_at IS NULL', 'left')
            ->join('users AS owners', 'owners.id = deals.owner_id', 'left')
            ->join('lost_reasons', 'lost_reasons.id = deals.lost_reason_id', 'left');
    }

    /**
     * Query daftar deal. Filter: q, stage, status, owner, close_from, close_to, min_amount, sort, dir.
     *
     * @param array<string, mixed> $filters
     *
     * @return $this
     */
    public function filtered(array $filters, User $user): static
    {
        $this->withRelations()->visibleTo($user);

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->groupStart()->like('deals.title', $q)->orLike('companies.name', $q)->groupEnd();
        }

        if (ctype_digit((string) ($filters['stage'] ?? ''))) {
            $this->where('deals.stage_id', (int) $filters['stage']);
        }

        match ($filters['status'] ?? null) {
            'open'  => $this->where('pipeline_stages.is_won', 0)->where('pipeline_stages.is_lost', 0),
            'won'   => $this->where('pipeline_stages.is_won', 1),
            'lost'  => $this->where('pipeline_stages.is_lost', 1),
            default => null,
        };

        if (ctype_digit((string) ($filters['owner'] ?? ''))) {
            $this->where('deals.owner_id', (int) $filters['owner']);
        }

        foreach (['close_from' => '>=', 'close_to' => '<='] as $key => $operator) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($filters[$key] ?? '')) === 1) {
                $this->where('deals.expected_close_date ' . $operator, $filters[$key]);
            }
        }

        $minAmount = parse_rupiah($filters['min_amount'] ?? null);

        if ($minAmount !== null && is_numeric($minAmount)) {
            $this->where('deals.amount >=', $minAmount);
        }

        return $this->applySort(self::SORTABLE, $filters['sort'] ?? null, $filters['dir'] ?? null, 'created_at');
    }

    /**
     * @return array<string, mixed>
     */
    public function findDetailOrFail(int|string $id, User $user): array
    {
        return $this->withRelations()->findVisibleOrFail($id, $user);
    }

    /**
     * Deal untuk papan Kanban: semua deal terbuka + deal Won/Lost yang ditutup 30 hari terakhir.
     *
     * @return list<array<string, mixed>>
     */
    public function forBoard(User $user, ?int $ownerId = null): array
    {
        $this->withRelations()->visibleTo($user)
            ->groupStart()
            ->groupStart()->where('pipeline_stages.is_won', 0)->where('pipeline_stages.is_lost', 0)->groupEnd()
            ->orWhere('deals.closed_at >=', Time::today()->subDays(30)->toDateTimeString())
            ->groupEnd();

        if ($ownerId !== null) {
            $this->where('deals.owner_id', $ownerId);
        }

        return $this->orderBy('deals.expected_close_date IS NULL', '', false)
            ->orderBy('deals.expected_close_date', 'ASC')
            ->orderBy('deals.id', 'ASC')
            ->findAll();
    }

    /**
     * Deal milik perusahaan yang boleh dilihat user.
     *
     * @return list<array<string, mixed>>
     */
    public function forCompany(int $companyId, User $user): array
    {
        return $this->withRelations()->visibleTo($user)
            ->where('deals.company_id', $companyId)
            ->orderBy('deals.created_at', 'DESC')
            ->findAll();
    }

    /**
     * Deal yang melibatkan kontak (sebagai kontak utama).
     *
     * @return list<array<string, mixed>>
     */
    public function forContact(int $contactId, User $user): array
    {
        return $this->withRelations()->visibleTo($user)
            ->where('deals.contact_id', $contactId)
            ->orderBy('deals.created_at', 'DESC')
            ->findAll();
    }

    /**
     * Riwayat perpindahan tahap, terbaru di atas.
     *
     * @return list<array<string, mixed>>
     */
    public function stageHistory(int $dealId): array
    {
        return $this->db->table('deal_stage_history h')
            ->select('h.*, f.name AS from_stage, t.name AS to_stage, t.is_won, t.is_lost, u.name AS changed_by_name')
            ->join('pipeline_stages f', 'f.id = h.from_stage_id', 'left')
            ->join('pipeline_stages t', 't.id = h.to_stage_id')
            ->join('users u', 'u.id = h.changed_by', 'left')
            ->where('h.deal_id', $dealId)
            ->orderBy('h.changed_at', 'DESC')
            ->orderBy('h.id', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * Warna badge untuk tahap.
     *
     * @param array<string, mixed> $deal
     */
    public static function stageColor(array $deal): string
    {
        if ((int) ($deal['is_won'] ?? 0) === 1) {
            return 'green';
        }

        if ((int) ($deal['is_lost'] ?? 0) === 1) {
            return 'red';
        }

        return ['blue', 'indigo', 'purple', 'orange', 'teal'][((int) ($deal['stage_order'] ?? 1) - 1) % 5];
    }
}
