<?php

namespace App\Database\Seeds;

use App\Services\UserService;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Seeder untuk produksi: pengaturan, master data, dan satu akun admin dengan password acak
 * yang dicetak sekali di terminal (wajib diganti saat login pertama). Tanpa data demo.
 *
 *     php spark db:seed ProductionSeeder
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingSeeder::class);
        $this->call(MasterDataSeeder::class);

        if ($this->db->table('users')->where('role', 'admin')->countAllResults() > 0) {
            if (! $this->silent) {
                CLI::write('Akun admin sudah ada, tidak dibuat ulang.', 'yellow');
            }

            return;
        }

        $password = UserService::generatePassword(12);
        $now      = Time::now()->toDateTimeString();

        // Langsung lewat Query Builder agar username cadangan `admin` bisa dipakai akun awal.
        $this->db->table('users')->insert([
            'username'             => 'admin',
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'name'                 => 'Administrator',
            'role'                 => 'admin',
            'is_active'            => 1,
            'must_change_password' => 1,
            'created_at'           => $now,
            'updated_at'           => $now,
        ]);

        if ($this->silent) {
            return;
        }

        CLI::newLine();
        CLI::write('Akun admin berhasil dibuat.', 'green');
        CLI::write('  Username : admin');
        CLI::write('  Password : ' . CLI::color($password, 'yellow'));
        CLI::write('Simpan password ini sekarang — hanya ditampilkan sekali dan wajib diganti saat login pertama.', 'light_red');
        CLI::newLine();
    }
}
