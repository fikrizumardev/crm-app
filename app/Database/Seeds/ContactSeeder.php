<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * 1–4 kontak per perusahaan (±150), termasuk showcase Bambang Hartono di PT Sinar Logistik Nusantara.
 * Dilewati bila tabel kontak sudah berisi data.
 */
class ContactSeeder extends Seeder
{
    public function run(): void
    {
        if ($this->db->table('contacts')->countAllResults() > 0) {
            if (! $this->silent) {
                CLI::write('ContactSeeder: data kontak sudah ada, dilewati.', 'yellow');
            }

            return;
        }

        $faker     = DemoData::faker(20);
        $companies = $this->db->table('companies')->orderBy('id')->get()->getResultArray();
        $emails    = [];
        $rows      = [];

        DemoData::reservePhone('081234567810');

        foreach ($companies as $company) {
            $domain    = preg_replace('#^https?://(www\.)?#', '', (string) $company['website']) ?: DemoData::slug($company['name'], '') . '.co.id';
            $count     = $faker->numberBetween(1, 4);
            $createdAt = Time::parse($company['created_at']);

            if ($company['name'] === CompanySeeder::SHOWCASE) {
                $emails['bambang.hartono@sinarlogistik.co.id'] = true;
                $rows[]                                        = [
                    'company_id' => $company['id'],
                    'first_name' => 'Bambang',
                    'last_name'  => 'Hartono',
                    'job_title'  => 'Kepala IT',
                    'phone'      => '081234567810',
                    'whatsapp'   => '081234567810',
                    'email'      => 'bambang.hartono@sinarlogistik.co.id',
                    'birth_date' => '1979-03-14',
                    'owner_id'   => $company['owner_id'],
                    'created_at' => $createdAt->toDateTimeString(),
                    'updated_at' => $createdAt->toDateTimeString(),
                ];
                $count--;
            }

            for ($i = 0; $i < $count; $i++) {
                $firstName = $faker->firstName();
                $lastName  = $faker->lastName();
                $email     = DemoData::slug($firstName . ' ' . $lastName) . '@' . $domain;

                if (isset($emails[$email])) {
                    $email = DemoData::slug($firstName . ' ' . $lastName) . $faker->numberBetween(2, 99) . '@' . $domain;
                }

                $emails[$email] = true;
                $phone          = DemoData::mobile($faker);
                $created        = $createdAt->addDays($faker->numberBetween(0, 20));

                if ($created->isAfter(Time::now())) {
                    $created = Time::now()->subHours($faker->numberBetween(1, 48));
                }

                $rows[] = [
                    'company_id' => $company['id'],
                    'first_name' => $firstName,
                    'last_name'  => $lastName,
                    'job_title'  => $faker->randomElement(DemoData::JOB_TITLES),
                    'phone'      => $phone,
                    'whatsapp'   => $faker->boolean(85) ? $phone : DemoData::mobile($faker),
                    'email'      => $faker->boolean(90) ? $email : null,
                    'birth_date' => $faker->boolean(60) ? $faker->dateTimeBetween('-58 years', '-24 years')->format('Y-m-d') : null,
                    'owner_id'   => $company['owner_id'],
                    'created_at' => $created->toDateTimeString(),
                    'updated_at' => $created->toDateTimeString(),
                ];
            }
        }

        $this->db->table('contacts')->insertBatch($rows);
    }
}
