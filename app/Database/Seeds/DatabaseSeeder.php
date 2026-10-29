<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder lengkap untuk demo/development: 11 seeder sesuai urutan PRD §8.
 * Untuk server produksi gunakan ProductionSeeder.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SettingSeeder::class);
        $this->call(MasterDataSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(ProductSeeder::class);
        $this->call(CompanySeeder::class);
        $this->call(ContactSeeder::class);
        $this->call(LeadSeeder::class);
        $this->call(DealSeeder::class);
        $this->call(ActivitySeeder::class);
        $this->call(TaskSeeder::class);
        $this->call(QuotationSeeder::class);
    }
}
