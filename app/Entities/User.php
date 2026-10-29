<?php

namespace App\Entities;

use CodeIgniter\Entity\Entity;

/**
 * @property int         $id
 * @property string      $username
 * @property string      $password_hash
 * @property string      $name
 * @property string|null $email
 * @property string|null $phone
 * @property string      $role
 * @property int|null    $manager_id
 * @property bool        $is_active
 * @property bool        $must_change_password
 */
class User extends Entity
{
    public const ROLE_ADMIN   = 'admin';
    public const ROLE_MANAGER = 'manager';
    public const ROLE_SALES   = 'sales';

    /**
     * Label peran untuk tampilan.
     */
    public const ROLES = [
        self::ROLE_ADMIN   => 'Admin',
        self::ROLE_MANAGER => 'Manager',
        self::ROLE_SALES   => 'Sales',
    ];

    protected $dates = ['last_login_at', 'created_at', 'updated_at', 'deleted_at'];
    protected $casts = [
        'id'                   => 'integer',
        'manager_id'           => '?integer',
        'is_active'            => 'boolean',
        'must_change_password' => 'boolean',
    ];

    /**
     * Username selalu disimpan lowercase tanpa spasi di tepi.
     */
    public function setUsername(string $username): static
    {
        $this->attributes['username'] = strtolower(trim($username));

        return $this;
    }

    /**
     * Simpan password dalam bentuk hash; dipanggil lewat `$user->password = '...'`.
     */
    public function setPassword(string $password): static
    {
        $this->attributes['password_hash'] = password_hash($password, PASSWORD_DEFAULT);

        return $this;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, (string) ($this->attributes['password_hash'] ?? ''));
    }

    public function passwordNeedsRehash(): bool
    {
        return password_needs_rehash((string) ($this->attributes['password_hash'] ?? ''), PASSWORD_DEFAULT);
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isSales(): bool
    {
        return $this->role === self::ROLE_SALES;
    }

    /**
     * @param string ...$roles
     */
    public function hasRole(string ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? ucfirst((string) $this->role);
    }

    /**
     * Inisial nama untuk avatar, mis. "Dewi Lestari" → "DL".
     */
    public function initials(): string
    {
        $words = preg_split('/\s+/', trim((string) $this->name)) ?: [];
        $first = $words[0] ?? '';
        $last  = count($words) > 1 ? $words[count($words) - 1] : '';

        return strtoupper(mb_substr($first, 0, 1) . mb_substr($last, 0, 1));
    }

    /**
     * Nama depan untuk sapaan.
     */
    public function firstName(): string
    {
        return explode(' ', trim((string) $this->name))[0] ?? '';
    }
}
