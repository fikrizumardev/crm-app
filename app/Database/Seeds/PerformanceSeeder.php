<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Data volume besar untuk uji performa (PRD §2, §9): 10.000 lead dan 10.000 deal.
 * TIDAK dipanggil DatabaseSeeder. Jalankan di database uji/lokal setelah DatabaseSeeder:
 *
 *     php spark db:seed PerformanceSeeder
 */
class PerformanceSeeder extends Seeder
{
    public const ROWS  = 10000;
    private const BATCH = 1000;

    public function run(): void
    {
        $faker     = DemoData::faker(90);
        $owners    = array_values(array_intersect_key(DemoData::userIds($this->db), array_flip(DemoData::SALES)));
        $sources   = array_values(DemoData::masterIds($this->db, 'lead_sources'));
        $stages    = $this->db->table('pipeline_stages')->get()->getResultArray();
        $reasons   = array_values(DemoData::masterIds($this->db, 'lost_reasons'));
        $companies = array_map('intval', array_column($this->db->table('companies')->select('id')->get()->getResultArray(), 'id'));
        $statuses  = ['new', 'contacted', 'qualified', 'unqualified'];

        if ($owners === [] || $stages === []) {
            CLI::error('Jalankan DatabaseSeeder terlebih dahulu.');

            return;
        }

        $start = microtime(true);

        for ($offset = 0; $offset < self::ROWS; $offset += self::BATCH) {
            $leads = [];
            $deals = [];

            for ($i = 0; $i < self::BATCH; $i++) {
                $created = Time::now()->subMinutes($faker->numberBetween(1, 60 * 24 * 365))->toDateTimeString();
                $leads[] = [
                    'name'         => $faker->firstName() . ' ' . $faker->lastName(),
                    'company_name' => 'PT ' . $faker->lastName() . ' ' . $faker->randomElement(['Jaya', 'Abadi', 'Sentosa', 'Makmur']),
                    'job_title'    => $faker->randomElement(DemoData::JOB_TITLES),
                    'phone'        => '08' . $faker->numerify('##########'),
                    'email'        => null,
                    'source_id'    => $faker->randomElement($sources),
                    'status'       => $faker->randomElement($statuses),
                    'owner_id'     => $faker->randomElement($owners),
                    'created_at'   => $created,
                    'updated_at'   => $created,
                ];

                $stage   = $faker->randomElement($stages);
                $closed  = (int) $stage['is_won'] === 1 || (int) $stage['is_lost'] === 1;
                $deals[] = [
                    'title'               => 'Pengadaan sistem ' . $faker->numberBetween(1, 99999),
                    'company_id'          => $companies === [] ? null : $faker->randomElement($companies),
                    'stage_id'            => $stage['id'],
                    'amount'              => $faker->numberBetween(30, 1700) * 500000,
                    'probability'         => $stage['probability'],
                    'expected_close_date' => Time::today()->addDays($faker->numberBetween(-60, 120))->format('Y-m-d'),
                    'closed_at'           => $closed ? $created : null,
                    'lost_reason_id'      => (int) $stage['is_lost'] === 1 ? $faker->randomElement($reasons) : null,
                    'owner_id'            => $faker->randomElement($owners),
                    'created_at'          => $created,
                    'updated_at'          => $created,
                ];
            }

            $this->db->table('leads')->insertBatch($leads);
            $this->db->table('deals')->insertBatch($deals);
        }

        CLI::write(sprintf('PerformanceSeeder: %d lead + %d deal dibuat dalam %.1f detik.', self::ROWS, self::ROWS, microtime(true) - $start), 'green');
    }
}
