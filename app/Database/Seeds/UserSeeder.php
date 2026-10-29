<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Akun demo (PRD §8): 1 admin, 2 manager, 6 sales (3 per manager).
 * Idempoten: akun dicocokkan berdasarkan username dan diperbarui bila sudah ada.
 */
class UserSeeder extends Seeder
{
    /**
     * @var list<array{username: string, name: string, role: string, password: string, manager?: string, phone: string, email: string}>
     */
    public const USERS = [
        ['username' => 'admin', 'name' => 'Rizky Pratama', 'role' => 'admin', 'password' => 'Admin#2026', 'phone' => '081210000001', 'email' => 'rizky.pratama@nusantarasolusi.co.id'],
        ['username' => 'dewi.lestari', 'name' => 'Dewi Lestari', 'role' => 'manager', 'password' => 'Manager#2026', 'phone' => '081210000002', 'email' => 'dewi.lestari@nusantarasolusi.co.id'],
        ['username' => 'hendra.wijaya', 'name' => 'Hendra Wijaya', 'role' => 'manager', 'password' => 'Manager#2026', 'phone' => '081210000003', 'email' => 'hendra.wijaya@nusantarasolusi.co.id'],
        ['username' => 'andi.saputra', 'name' => 'Andi Saputra', 'role' => 'sales', 'password' => 'Sales#2026', 'manager' => 'dewi.lestari', 'phone' => '081210000004', 'email' => 'andi.saputra@nusantarasolusi.co.id'],
        ['username' => 'siti.rahma', 'name' => 'Siti Rahmawati', 'role' => 'sales', 'password' => 'Sales#2026', 'manager' => 'dewi.lestari', 'phone' => '081210000005', 'email' => 'siti.rahma@nusantarasolusi.co.id'],
        ['username' => 'budi.santoso', 'name' => 'Budi Santoso', 'role' => 'sales', 'password' => 'Sales#2026', 'manager' => 'dewi.lestari', 'phone' => '081210000006', 'email' => 'budi.santoso@nusantarasolusi.co.id'],
        ['username' => 'putri.ayu', 'name' => 'Putri Ayu Ningsih', 'role' => 'sales', 'password' => 'Sales#2026', 'manager' => 'hendra.wijaya', 'phone' => '081210000007', 'email' => 'putri.ayu@nusantarasolusi.co.id'],
        ['username' => 'fajar.nugroho', 'name' => 'Fajar Nugroho', 'role' => 'sales', 'password' => 'Sales#2026', 'manager' => 'hendra.wijaya', 'phone' => '081210000008', 'email' => 'fajar.nugroho@nusantarasolusi.co.id'],
        ['username' => 'maya.kusuma', 'name' => 'Maya Kusumawati', 'role' => 'sales', 'password' => 'Sales#2026', 'manager' => 'hendra.wijaya', 'phone' => '081210000009', 'email' => 'maya.kusuma@nusantarasolusi.co.id'],
    ];

    public function run(): void
    {
        $now   = Time::now()->toDateTimeString();
        $table = $this->db->table('users');

        // Pengguna dibuat langsung lewat Query Builder agar akun `admin` (kata cadangan) tetap bisa dibuat.
        foreach (self::USERS as $user) {
            $managerId = null;

            if (isset($user['manager'])) {
                $managerId = $table->select('id')->where('username', $user['manager'])->get()->getRow('id');
            }

            $row = [
                'name'                 => $user['name'],
                'email'                => $user['email'],
                'phone'                => $user['phone'],
                'role'                 => $user['role'],
                'manager_id'           => $managerId,
                'is_active'            => 1,
                'must_change_password' => 0,
                'updated_at'           => $now,
            ];

            $existingId = $table->select('id')->where('username', $user['username'])->get()->getRow('id');

            if ($existingId !== null) {
                $table->where('id', $existingId)->update($row);

                continue;
            }

            $table->insert($row + [
                'username'      => $user['username'],
                'password_hash' => $this->hash($user['password']),
                'created_at'    => $now,
            ]);
        }
    }

    /**
     * Hash password; disimpan sementara per proses karena akun demo memakai password yang sama.
     */
    private function hash(string $password): string
    {
        static $cache = [];

        return $cache[$password] ??= password_hash($password, PASSWORD_DEFAULT);
    }
}
