<?php

namespace App\Models;

use App\Entities\User;
use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table          = 'users';
    protected $primaryKey     = 'id';
    protected $returnType     = User::class;
    protected $useSoftDeletes = true;
    protected $useTimestamps  = true;
    protected $allowedFields  = [
        'username',
        'password_hash',
        'name',
        'email',
        'phone',
        'role',
        'manager_id',
        'is_active',
        'must_change_password',
        'last_login_at',
    ];
    protected $validationRules = [
        'id'            => 'permit_empty|is_natural_no_zero',
        'username'      => ['label' => 'Username', 'rules' => 'required|regex_match[/^[a-z0-9._]{4,30}$/]|is_unique[users.username,id,{id}]|username_not_reserved'],
        'password_hash' => ['label' => 'Password', 'rules' => 'required'],
        'name'          => ['label' => 'Nama', 'rules' => 'required|min_length[3]|max_length[100]'],
        'email'         => ['label' => 'Email', 'rules' => 'permit_empty|valid_email|max_length[150]'],
        'phone'         => ['label' => 'No. HP', 'rules' => 'permit_empty|valid_phone|max_length[20]'],
        'role'          => ['label' => 'Peran', 'rules' => 'required|in_list[admin,manager,sales]'],
        'manager_id'    => ['label' => 'Manager', 'rules' => 'permit_empty|is_natural_no_zero|is_not_unique[users.id]'],
    ];
    protected $afterInsert = ['clearScopeCache'];
    protected $afterUpdate = ['clearScopeCache'];
    protected $afterDelete = ['clearScopeCache'];

    /**
     * @var array<int, list<int>>
     */
    private array $scopeCache = [];

    protected $validationMessages = [
        'username' => [
            'regex_match' => 'Username 4–30 karakter: huruf kecil, angka, titik, atau garis bawah.',
        ],
    ];

    public function findByUsername(string $username): ?User
    {
        return $this->where('username', strtolower(trim($username)))->first();
    }

    /**
     * ID user dalam cakupan data seorang user:
     * admin → null (semua), manager → dirinya + sales bawahannya, sales → dirinya.
     *
     * @return list<int>|null
     */
    public function scopeIds(User $user): ?array
    {
        if ($user->isAdmin()) {
            return null;
        }

        if (! $user->isManager()) {
            return [(int) $user->id];
        }

        // Dipanggil per baris di halaman daftar (canEdit/canDelete): simpan hasil per request.
        if (! array_key_exists($user->id, $this->scopeCache)) {
            $team = $this->db->table($this->table)
                ->select('id')
                ->where('manager_id', $user->id)
                ->where('deleted_at', null)
                ->get()
                ->getResultArray();

            $this->scopeCache[$user->id] = array_values(array_unique([(int) $user->id, ...array_map('intval', array_column($team, 'id'))]));
        }

        return $this->scopeCache[$user->id];
    }

    /**
     * Hapus cache cakupan tim setelah data user berubah (mis. manager sales diganti).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function clearScopeCache(array $data): array
    {
        $this->scopeCache = [];

        return $data;
    }

    /**
     * User aktif yang boleh dipilih sebagai owner oleh user tertentu.
     *
     * @return list<User>
     */
    public function assignableOwners(User $user): array
    {
        $ids = $this->scopeIds($user);

        $this->where('is_active', 1)->orderBy('name');

        if ($ids !== null) {
            $this->whereIn('id', $ids);
        }

        return $this->findAll();
    }

    /**
     * Pilihan owner dalam format [id => nama] untuk dropdown.
     *
     * @return array<int, string>
     */
    public function ownerOptions(User $user): array
    {
        $options = [];

        foreach ($this->assignableOwners($user) as $owner) {
            $options[$owner->id] = $owner->name;
        }

        return $options;
    }
}
