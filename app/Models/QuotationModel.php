<?php

namespace App\Models;

use App\Entities\User;

/**
 * Penawaran harga. Cakupan data mengikuti owner deal-nya.
 */
class QuotationModel extends OwnedModel
{
    public const STATUS_DRAFT    = 'draft';
    public const STATUS_SENT     = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_DRAFT    => ['label' => 'Draft', 'color' => 'gray'],
        self::STATUS_SENT     => ['label' => 'Terkirim', 'color' => 'blue'],
        self::STATUS_ACCEPTED => ['label' => 'Diterima', 'color' => 'green'],
        self::STATUS_REJECTED => ['label' => 'Ditolak', 'color' => 'red'],
    ];

    protected $table         = 'quotations';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'number',
        'deal_id',
        'issue_date',
        'valid_until',
        'subtotal',
        'discount_total',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'status',
        'notes',
        'created_by',
    ];
    protected $validationRules = [
        'number'      => ['label' => 'Nomor', 'rules' => 'required|max_length[30]'],
        'deal_id'     => ['label' => 'Deal', 'rules' => 'required|is_natural_no_zero|is_not_unique[deals.id]'],
        'issue_date'  => ['label' => 'Tanggal penawaran', 'rules' => 'required|valid_date[Y-m-d]'],
        'valid_until' => ['label' => 'Berlaku sampai', 'rules' => 'required|valid_date[Y-m-d]'],
        'status'      => ['label' => 'Status', 'rules' => 'required|in_list[draft,sent,accepted,rejected]'],
        'notes'       => ['label' => 'Catatan', 'rules' => 'permit_empty|max_length[5000]'],
    ];

    private const SORTABLE = [
        'number'      => 'quotations.number',
        'issue_date'  => 'quotations.issue_date',
        'valid_until' => 'quotations.valid_until',
        'grand_total' => 'quotations.grand_total',
        'status'      => 'quotations.status',
        'created_at'  => 'quotations.created_at',
    ];

    /**
     * Penawaran terlihat bila deal-nya terlihat. `owner_id` diambil dari deal agar
     * aturan canView/canEdit/canDelete OwnedModel tetap berlaku.
     *
     * @return $this
     */
    public function visibleTo(User $user): static
    {
        $this->select('quotations.*, deals.owner_id, deals.title AS deal_title, deals.company_id, deals.contact_id')
            ->select('companies.name AS company_name, owners.name AS owner_name')
            ->join('deals', 'deals.id = quotations.deal_id')
            ->join('companies', 'companies.id = deals.company_id', 'left')
            ->join('users AS owners', 'owners.id = deals.owner_id', 'left')
            ->where('deals.deleted_at', null);

        $ids = model(UserModel::class)->scopeIds($user);

        if ($ids !== null) {
            $this->whereIn('deals.owner_id', $ids === [] ? [0] : $ids);
        }

        return $this;
    }

    /**
     * Penawaran yang sudah diterima klien tidak boleh dihapus sales.
     */
    public function isLocked(array $record): bool
    {
        return ($record['status'] ?? null) === self::STATUS_ACCEPTED;
    }

    /**
     * Daftar penawaran. Filter: q (nomor/deal/perusahaan), status, sort, dir.
     *
     * @param array<string, mixed> $filters
     *
     * @return $this
     */
    public function filtered(array $filters, User $user): static
    {
        $this->visibleTo($user);

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->groupStart()->like('quotations.number', $q)->orLike('deals.title', $q)->orLike('companies.name', $q)->groupEnd();
        }

        if (isset(self::STATUSES[$filters['status'] ?? ''])) {
            $this->where('quotations.status', $filters['status']);
        }

        return $this->applySort(self::SORTABLE, $filters['sort'] ?? null, $filters['dir'] ?? null, 'created_at');
    }

    /**
     * Penawaran milik satu deal.
     *
     * @return list<array<string, mixed>>
     */
    public function forDeal(int $dealId, User $user): array
    {
        return $this->visibleTo($user)
            ->where('quotations.deal_id', $dealId)
            ->orderBy('quotations.created_at', 'DESC')
            ->findAll();
    }
}
