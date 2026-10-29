<?php

namespace App\Models;

use App\Entities\User;

class LeadModel extends OwnedModel
{
    public const STATUS_NEW         = 'new';
    public const STATUS_CONTACTED   = 'contacted';
    public const STATUS_QUALIFIED   = 'qualified';
    public const STATUS_UNQUALIFIED = 'unqualified';
    public const STATUS_CONVERTED   = 'converted';

    /**
     * Label status (urut alur: Baru → Dihubungi → Kualifikasi → Tidak Qualified / Dikonversi).
     */
    public const STATUSES = [
        self::STATUS_NEW         => 'Baru',
        self::STATUS_CONTACTED   => 'Dihubungi',
        self::STATUS_QUALIFIED   => 'Kualifikasi',
        self::STATUS_UNQUALIFIED => 'Tidak Qualified',
        self::STATUS_CONVERTED   => 'Dikonversi',
    ];

    /**
     * Warna badge per status (lihat components/badge).
     */
    public const STATUS_COLORS = [
        self::STATUS_NEW         => 'blue',
        self::STATUS_CONTACTED   => 'purple',
        self::STATUS_QUALIFIED   => 'teal',
        self::STATUS_UNQUALIFIED => 'gray',
        self::STATUS_CONVERTED   => 'green',
    ];

    protected $table         = 'leads';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'name',
        'company_name',
        'job_title',
        'phone',
        'email',
        'source_id',
        'status',
        'owner_id',
        'notes',
        'converted_contact_id',
        'converted_company_id',
        'converted_deal_id',
        'converted_at',
    ];
    protected $validationRules = [
        'name'         => ['label' => 'Nama', 'rules' => 'required|min_length[2]|max_length[150]'],
        'company_name' => ['label' => 'Perusahaan', 'rules' => 'permit_empty|max_length[150]'],
        'job_title'    => ['label' => 'Jabatan', 'rules' => 'permit_empty|max_length[100]'],
        'phone'        => ['label' => 'No. HP', 'rules' => 'permit_empty|valid_phone|max_length[20]'],
        'email'        => ['label' => 'Email', 'rules' => 'permit_empty|valid_email|max_length[150]'],
        'source_id'    => ['label' => 'Sumber', 'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[lead_sources.id]'],
        'status'       => ['label' => 'Status', 'rules' => 'required|in_list[new,contacted,qualified,unqualified,converted]'],
        'owner_id'     => ['label' => 'Owner', 'rules' => 'required|is_natural_no_zero|is_not_unique[users.id]'],
        'notes'        => ['label' => 'Catatan', 'rules' => 'permit_empty|max_length[5000]'],
    ];

    /**
     * Kolom yang boleh dipakai untuk sort di daftar.
     */
    private const SORTABLE = [
        'name'       => 'leads.name',
        'company'    => 'leads.company_name',
        'status'     => 'leads.status',
        'source'     => 'lead_sources.name',
        'owner'      => 'owners.name',
        'created_at' => 'leads.created_at',
    ];

    public function isLocked(array $record): bool
    {
        return ($record['status'] ?? null) === self::STATUS_CONVERTED;
    }

    /**
     * Query daftar lead sesuai cakupan user dan filter dari query string.
     *
     * Filter: q, status, source, owner, from, to, sort, dir.
     *
     * @param array<string, mixed> $filters
     *
     * @return $this
     */
    public function filtered(array $filters, User $user): static
    {
        $this->select('leads.*, lead_sources.name AS source_name, owners.name AS owner_name')
            ->join('lead_sources', 'lead_sources.id = leads.source_id', 'left')
            ->join('users AS owners', 'owners.id = leads.owner_id', 'left')
            ->visibleTo($user);

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q);

            $this->groupStart()
                ->like('leads.name', $q)
                ->orLike('leads.company_name', $q)
                ->orLike('leads.email', $q);

            if (strlen($digits) >= 4) {
                $this->orLike('leads.phone', normalize_phone($digits) ?? $digits);
            }

            $this->groupEnd();
        }

        if (isset(self::STATUSES[$filters['status'] ?? ''])) {
            $this->where('leads.status', $filters['status']);
        }

        if (ctype_digit((string) ($filters['source'] ?? ''))) {
            $this->where('leads.source_id', (int) $filters['source']);
        }

        if (ctype_digit((string) ($filters['owner'] ?? ''))) {
            $this->where('leads.owner_id', (int) $filters['owner']);
        }

        $this->applyDateRange('leads.created_at', $filters['from'] ?? null, $filters['to'] ?? null);

        return $this->applySort(self::SORTABLE, $filters['sort'] ?? null, $filters['dir'] ?? null, 'created_at');
    }

    /**
     * Lead/kontak lain dengan no. HP atau email yang sama (untuk peringatan duplikat).
     *
     * @return list<array{type: string, id: int, name: string, owner: string|null, match: string, url: string}>
     */
    public function findDuplicates(?string $phone, ?string $email, ?int $excludeLeadId = null): array
    {
        $matches = [];

        if ($phone !== null && $phone !== '') {
            $this->collectLeadMatches($matches, 'leads.phone', $phone, 'No. HP', $excludeLeadId);
            $this->collectContactMatches($matches, $phone, 'No. HP');
        }

        if ($email !== null && $email !== '') {
            $this->collectLeadMatches($matches, 'leads.email', $email, 'Email', $excludeLeadId);
            $this->collectContactMatches($matches, $email, 'Email');
        }

        return array_values($matches);
    }

    /**
     * @param array<string, array<string, mixed>> $matches
     */
    private function collectLeadMatches(array &$matches, string $column, string $value, string $label, ?int $excludeId): void
    {
        $builder = $this->db->table('leads')
            ->select('leads.id, leads.name, users.name AS owner_name')
            ->join('users', 'users.id = leads.owner_id', 'left')
            ->where($column, $value)
            ->where('leads.deleted_at', null)
            ->limit(5);

        if ($excludeId !== null) {
            $builder->where('leads.id !=', $excludeId);
        }

        foreach ($builder->get()->getResultArray() as $row) {
            $key = 'lead-' . $row['id'];
            $matches[$key] ??= [
                'type'  => 'Lead',
                'id'    => (int) $row['id'],
                'name'  => $row['name'],
                'owner' => $row['owner_name'],
                'match' => $label,
                'url'   => site_url('leads/' . $row['id']),
            ];
        }
    }

    /**
     * @param array<string, array<string, mixed>> $matches
     */
    private function collectContactMatches(array &$matches, string $value, string $label): void
    {
        $builder = $this->db->table('contacts')
            ->select('contacts.id, contacts.first_name, contacts.last_name, users.name AS owner_name')
            ->join('users', 'users.id = contacts.owner_id', 'left')
            ->where('contacts.deleted_at', null)
            ->limit(5);

        if ($label === 'Email') {
            $builder->where('contacts.email', $value);
        } else {
            $builder->groupStart()->where('contacts.phone', $value)->orWhere('contacts.whatsapp', $value)->groupEnd();
        }

        foreach ($builder->get()->getResultArray() as $row) {
            $key = 'contact-' . $row['id'];
            $matches[$key] ??= [
                'type'  => 'Kontak',
                'id'    => (int) $row['id'],
                'name'  => trim($row['first_name'] . ' ' . $row['last_name']),
                'owner' => $row['owner_name'],
                'match' => $label,
                'url'   => site_url('contacts/' . $row['id']),
            ];
        }
    }
}
