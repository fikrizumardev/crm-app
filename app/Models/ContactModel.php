<?php

namespace App\Models;

use App\Entities\User;

class ContactModel extends OwnedModel
{
    protected $table         = 'contacts';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'company_id',
        'first_name',
        'last_name',
        'job_title',
        'phone',
        'whatsapp',
        'email',
        'birth_date',
        'owner_id',
    ];
    protected $validationRules = [
        'company_id' => ['label' => 'Perusahaan', 'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[companies.id]'],
        'first_name' => ['label' => 'Nama depan', 'rules' => 'required|max_length[100]'],
        'last_name'  => ['label' => 'Nama belakang', 'rules' => 'permit_empty|max_length[100]'],
        'job_title'  => ['label' => 'Jabatan', 'rules' => 'permit_empty|max_length[100]'],
        'phone'      => ['label' => 'No. HP', 'rules' => 'permit_empty|valid_phone|max_length[20]'],
        'whatsapp'   => ['label' => 'WhatsApp', 'rules' => 'permit_empty|valid_phone|max_length[20]'],
        'email'      => ['label' => 'Email', 'rules' => 'permit_empty|valid_email|max_length[150]'],
        'birth_date' => ['label' => 'Tanggal lahir', 'rules' => 'permit_empty|valid_date[Y-m-d]'],
        'owner_id'   => ['label' => 'Owner', 'rules' => 'required|is_natural_no_zero|is_not_unique[users.id]'],
    ];

    private const SORTABLE = [
        'name'       => 'contacts.first_name',
        'company'    => 'companies.name',
        'job_title'  => 'contacts.job_title',
        'owner'      => 'owners.name',
        'created_at' => 'contacts.created_at',
    ];

    /**
     * Nama lengkap kontak.
     *
     * @param array<string, mixed> $contact
     */
    public static function fullName(array $contact): string
    {
        return trim(($contact['first_name'] ?? '') . ' ' . ($contact['last_name'] ?? ''));
    }

    /**
     * Join perusahaan yang masih aktif (tidak terhapus).
     *
     * @return $this
     */
    private function withCompanyAndOwner(): static
    {
        return $this->select('contacts.*, companies.name AS company_name, owners.name AS owner_name')
            ->join('companies', 'companies.id = contacts.company_id AND companies.deleted_at IS NULL', 'left')
            ->join('users AS owners', 'owners.id = contacts.owner_id', 'left');
    }

    /**
     * Query daftar kontak. Filter: q, company, owner, sort, dir.
     *
     * @param array<string, mixed> $filters
     *
     * @return $this
     */
    public function filtered(array $filters, User $user): static
    {
        $this->withCompanyAndOwner()->visibleTo($user);

        $q = trim((string) ($filters['q'] ?? ''));

        if ($q !== '') {
            $digits = preg_replace('/\D+/', '', $q);

            $this->groupStart()
                ->like("CONCAT(contacts.first_name, ' ', COALESCE(contacts.last_name, ''))", $q, 'both', null, true)
                ->orLike('contacts.email', $q)
                ->orLike('companies.name', $q)
                ->orLike('contacts.job_title', $q);

            if (strlen($digits) >= 4) {
                $this->orLike('contacts.phone', normalize_phone($digits) ?? $digits);
            }

            $this->groupEnd();
        }

        if (ctype_digit((string) ($filters['company'] ?? ''))) {
            $this->where('contacts.company_id', (int) $filters['company']);
        }

        if (ctype_digit((string) ($filters['owner'] ?? ''))) {
            $this->where('contacts.owner_id', (int) $filters['owner']);
        }

        return $this->applySort(self::SORTABLE, $filters['sort'] ?? null, $filters['dir'] ?? null, 'name', 'asc');
    }

    /**
     * Detail kontak + nama perusahaan & owner.
     *
     * @return array<string, mixed>
     */
    public function findDetailOrFail(int|string $id, User $user): array
    {
        return $this->withCompanyAndOwner()->findVisibleOrFail($id, $user);
    }

    /**
     * Kontak milik perusahaan yang boleh dilihat user.
     *
     * @return list<array<string, mixed>>
     */
    public function forCompany(int $companyId, User $user): array
    {
        return $this->select('contacts.*, owners.name AS owner_name')
            ->join('users AS owners', 'owners.id = contacts.owner_id', 'left')
            ->visibleTo($user)
            ->where('contacts.company_id', $companyId)
            ->orderBy('contacts.first_name')
            ->findAll();
    }
}
