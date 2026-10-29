<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seeder lengkap untuk demo/development (PRD §8).
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
    }
}
