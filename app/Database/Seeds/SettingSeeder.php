<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Pengaturan aplikasi default. Idempoten: hanya menambah key yang belum ada.
 */
class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'company_name'    => 'PT Nusantara Solusi Digital',
            'company_logo'    => '',
            'company_address' => 'Jl. Jenderal Sudirman Kav. 52-53, Senayan, Jakarta Selatan 12190',
            'company_phone'   => '0215150888',
            'currency'        => 'IDR',
            'tax_rate'        => '11',
        ];

        $now   = Time::now()->toDateTimeString();
        $table = $this->db->table('settings');

        foreach ($defaults as $key => $value) {
            if ($table->where('key', $key)->countAllResults() > 0) {
                continue;
            }

            $table->insert(['key' => $key, 'value' => $value, 'created_at' => $now, 'updated_at' => $now]);
        }
    }
}
