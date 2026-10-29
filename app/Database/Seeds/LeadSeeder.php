<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * 120 lead dengan distribusi status 30/25/20/10/15% (Baru/Dihubungi/Kualifikasi/Tidak Qualified/Dikonversi).
 * Lead Dikonversi tertaut ke perusahaan & kontak yang sudah ada. Dilewati bila tabel lead sudah berisi data.
 */
class LeadSeeder extends Seeder
{
    /**
     * Jumlah per status (total 120).
     */
    public const DISTRIBUTION = [
        'new'         => 36,
        'contacted'   => 30,
        'qualified'   => 24,
        'unqualified' => 12,
        'converted'   => 18,
    ];

    /**
     * Rentang umur lead (hari) per status agar alurnya masuk akal.
     */
    private const AGE = [
        'new'         => [0, 30],
        'contacted'   => [7, 75],
        'qualified'   => [14, 120],
        'unqualified' => [30, 180],
    ];

    private const NOTES = [
        'new' => [
            'Mengisi form permintaan demo di website, belum dihubungi.',
            'Dapat kartu nama saat pameran, minta brosur produk.',
            'Masuk dari iklan Google, tertarik modul gudang.',
            'Kirim pesan WhatsApp menanyakan harga lisensi per user.',
            'Direferensikan oleh pelanggan lama, minta dihubungi minggu ini.',
        ],
        'contacted' => [
            'Sudah ditelepon, minta dikirimi company profile dan daftar harga.',
            'Follow-up via WhatsApp, menunggu jadwal meeting dengan direksi.',
            'Sudah kirim email perkenalan, PIC sedang cuti sampai minggu depan.',
            'Tertarik demo online, minta jadwal hari Kamis.',
            'Masih membandingkan dengan dua vendor lain.',
        ],
        'qualified' => [
            'Budget sudah disetujui, target implementasi kuartal depan.',
            'Butuh sistem gudang untuk 3 cabang, minta proposal teknis.',
            'Decision maker sudah ikut demo, minta penawaran resmi.',
            'Sistem lama sudah tidak didukung vendor, perlu migrasi tahun ini.',
            'Siap uji coba 30 hari untuk 10 user.',
        ],
        'unqualified' => [
            'Belum ada budget tahun ini, dihubungi lagi tahun depan.',
            'Skala bisnis masih terlalu kecil untuk solusi kami.',
            'Sudah memakai sistem kompetitor, kontrak masih 2 tahun.',
            'Hanya mencari referensi harga untuk tugas kuliah.',
        ],
    ];

    private const COMPANY_PREFIXES = ['PT', 'PT', 'CV', 'UD'];
    private const COMPANY_WORDS    = ['Anugerah', 'Cipta', 'Tunas', 'Dharma', 'Wijaya', 'Mulia', 'Kencana', 'Permata', 'Samudra', 'Pelita', 'Bumi', 'Kurnia'];
    private const COMPANY_TYPES    = ['Distribusi', 'Logistik', 'Farma', 'Pangan', 'Retail', 'Teknik', 'Konstruksi', 'Edukasi', 'Plastik', 'Elektronik'];

    public function run(): void
    {
        if ($this->db->table('leads')->countAllResults() > 0) {
            if (! $this->silent) {
                CLI::write('LeadSeeder: data lead sudah ada, dilewati.', 'yellow');
            }

            return;
        }

        $faker   = DemoData::faker(30);
        $users   = DemoData::userIds($this->db);
        $sources = array_values(DemoData::masterIds($this->db, 'lead_sources'));
        $rows    = [];
        $index   = 0;

        foreach (self::DISTRIBUTION as $status => $total) {
            if ($status === 'converted') {
                continue;
            }

            for ($i = 0; $i < $total; $i++, $index++) {
                $firstName = $faker->firstName();
                $lastName  = $faker->lastName();
                $company   = $faker->randomElement(self::COMPANY_PREFIXES) . ' ' . $faker->randomElement(self::COMPANY_WORDS) . ' '
                    . $faker->randomElement(self::COMPANY_TYPES) . ' ' . $faker->randomElement(['Indonesia', 'Nusantara', 'Jaya', 'Sentosa', 'Abadi']);
                $createdAt = DemoData::pastDate($faker, self::AGE[$status][1], self::AGE[$status][0]);
                $domain    = DemoData::slug(explode(' ', $company)[1] . explode(' ', $company)[2], '') . '.co.id';

                $rows[] = [
                    'name'                 => $firstName . ' ' . $lastName,
                    'company_name'         => $company,
                    'job_title'            => $faker->randomElement(DemoData::JOB_TITLES),
                    'phone'                => DemoData::mobile($faker),
                    'email'                => $faker->boolean(70) ? DemoData::slug($firstName . ' ' . $lastName) . '@' . $domain : null,
                    'source_id'            => $faker->randomElement($sources),
                    'status'               => $status,
                    'owner_id'             => $users[DemoData::SALES[$index % count(DemoData::SALES)]],
                    'notes'                =>$faker->randomElement(self::NOTES[$status]),
                    'converted_contact_id' => null,
                    'converted_company_id' => null,
                    'converted_at'         => null,
                    'created_at'           => $createdAt->toDateTimeString(),
                    'updated_at'           => min($createdAt->addDays($faker->numberBetween(0, 5)), Time::now())->toDateTimeString(),
                ];
            }
        }

        foreach ($this->convertedLeads($faker, $sources) as $row) {
            $rows[] = $row;
        }

        // Urutkan berdasarkan tanggal agar ID mengikuti urutan waktu masuk.
        usort($rows, static fn (array $a, array $b): int => strcmp($a['created_at'], $b['created_at']));

        foreach (array_chunk($rows, 50) as $chunk) {
            $this->db->table('leads')->insertBatch($chunk);
        }
    }

    /**
     * Lead yang sudah dikonversi: diambil dari kontak yang ada sehingga relasinya konsisten.
     *
     * @param list<int> $sources
     *
     * @return list<array<string, mixed>>
     */
    private function convertedLeads(\Faker\Generator $faker, array $sources): array
    {
        $contacts = $this->db->table('contacts')
            ->select('contacts.*, companies.name AS company_name, companies.created_at AS company_created_at')
            ->join('companies', 'companies.id = contacts.company_id')
            ->orderBy('contacts.id')
            ->get()
            ->getResultArray();

        if ($contacts === []) {
            return [];
        }

        // Ambil satu kontak per perusahaan, tersebar merata.
        $byCompany = [];

        foreach ($contacts as $contact) {
            $byCompany[$contact['company_id']] ??= $contact;
        }

        $byCompany = array_values($byCompany);
        $step      = max(1, intdiv(count($byCompany), self::DISTRIBUTION['converted']));
        $rows      = [];

        for ($i = 0; $i < self::DISTRIBUTION['converted'] && isset($byCompany[$i * $step]); $i++) {
            $contact     = $byCompany[$i * $step];
            $convertedAt = Time::parse($contact['company_created_at']);
            $createdAt   = $convertedAt->subDays($faker->numberBetween(5, 14));

            $rows[] = [
                'name'                 => trim($contact['first_name'] . ' ' . $contact['last_name']),
                'company_name'         => $contact['company_name'],
                'job_title'            => $contact['job_title'],
                'phone'                => $contact['phone'],
                'email'                => $contact['email'],
                'source_id'            => $faker->randomElement($sources),
                'status'               => 'converted',
                'owner_id'             => $contact['owner_id'],
                'notes'                => 'Dikonversi setelah meeting presentasi solusi.',
                'converted_contact_id' => $contact['id'],
                'converted_company_id' => $contact['company_id'],
                'converted_at'         => $convertedAt->toDateTimeString(),
                'created_at'           => $createdAt->toDateTimeString(),
                'updated_at'           => $convertedAt->toDateTimeString(),
            ];
        }

        return $rows;
    }
}
