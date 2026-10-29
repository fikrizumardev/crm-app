<?php

namespace App\Models;

use App\Entities\User;
use Config\Indonesia;

class CompanyModel extends OwnedModel
{
    protected $table         = 'companies';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'name',
        'industry_id',
        'npwp',
        'phone',
        'website',
        'address',
        'city',
        'province',
        'employee_count',
        'owner_id',
    ];
    protected $validationRules = [
        'name'           => ['label' => 'Nama perusahaan', 'rules' => 'required|min_length[2]|max_length[150]'],
        'industry_id'    => ['label' => 'Industri', 'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[industries.id]'],
        'npwp'           => ['label' => 'NPWP', 'rules' => 'permit_empty|valid_npwp'],
        'phone'          => ['label' => 'Telepon', 'rules' => 'permit_empty|valid_phone|max_length[20]'],
        'website'        => ['label' => 'Website', 'rules' => 'permit_empty|valid_url_strict[https,http]|max_length[150]'],
        'address'        => ['label' => 'Alamat', 'rules' => 'permit_empty|max_length[1000]'],
        'city'           => ['label' => 'Kota', 'rules' => 'permit_empty|max_length[100]'],
        'province'       => ['label' => 'Provinsi', 'rules' => 'permit_empty'],
        'employee_count' => ['label' => 'Jumlah karyawan', 'rules' => 'permit_empty|is_natural|less_than_equal_to[10000000]'],
        'owner_id'       => ['label' => 'Owner', 'rules' => 'required|is_natural_no_zero|is_not_unique[users.id]'],
    ];

    private const SORTABLE = [
        'name'       => 'companies.name',
        'industry'   => 'industries.name',
        'city'       => 'companies.city',
        'owner'      => 'owners.name',
        'contacts'   => 'contact_count',
        'created_at' => 'companies.created_at',
    ];

    protected function initialize(): void
    {
        $this->validationRules['province']['rules'] = 'permit_empty|in_list[' . implode(',', config(Indonesia::class)->provinces) . ']';
    }

    /**
     * Query daftar perusahaan. Filter: q, industry, province, owner, sort, dir.
     *
     * @param array<string, mixed> $filters
     *
     * @return $this
     */
    public function filtered(array $filters, User $user): static
    {
        $this->select('companies.*, industries.name AS industry_name, owners.name AS owner_name')
            ->select('(SELECT COUNT(*) FROM contacts c WHERE c.company_id = companies.id AND c.deleted_at IS NULL) AS contact_count', false)
            ->join('industries', 'industries.id = companies.industry_id', 'left')
            ->join('users AS owners', 'owners.id = companies.owner_id', 'left')
            ->visibleTo($user);

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $this->groupStart()
                ->like('companies.name', $q)
                ->orLike('companies.city', $q)
                ->orLike('companies.phone', preg_replace('/\D+/', '', $q) ?: $q)
                ->groupEnd();
        }

        if (ctype_digit((string) ($filters['industry'] ?? ''))) {
            $this->where('companies.industry_id', (int) $filters['industry']);
        }

        if (in_array($filters['province'] ?? null, config(Indonesia::class)->provinces, true)) {
            $this->where('companies.province', $filters['province']);
        }

        if (ctype_digit((string) ($filters['owner'] ?? ''))) {
            $this->where('companies.owner_id', (int) $filters['owner']);
        }

        return $this->applySort(self::SORTABLE, $filters['sort'] ?? null, $filters['dir'] ?? null, 'name', 'asc');
    }

    /**
     * Pencarian ringan untuk pilihan perusahaan (combobox).
     *
     * @return list<array{id: int, name: string, city: string|null}>
     */
    public function search(string $q, User $user, int $limit = 20): array
    {
        $this->select('companies.id, companies.name, companies.city')->visibleTo($user);

        if (trim($q) !== '') {
            $this->like('companies.name', trim($q));
        }

        $rows = $this->orderBy('companies.name')->findAll($limit);

        return array_map(static fn (array $row): array => [
            'id'   => (int) $row['id'],
            'name' => $row['name'],
            'city' => $row['city'],
        ], $rows);
    }

    /**
     * Detail perusahaan + nama industri & owner.
     *
     * @return array<string, mixed>
     */
    public function findDetailOrFail(int|string $id, User $user): array
    {
        $this->select('companies.*, industries.name AS industry_name, owners.name AS owner_name')
            ->join('industries', 'industries.id = companies.industry_id', 'left')
            ->join('users AS owners', 'owners.id = companies.owner_id', 'left');

        return $this->findVisibleOrFail($id, $user);
    }
}
