<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * 80 deal (Rp 15 jt – Rp 850 jt): ~55% terbuka, ~25% Won, ~20% Lost, lengkap dengan riwayat tahap.
 * Termasuk showcase "Implementasi ERP Gudang — 3 cabang". Dilewati bila tabel deal sudah berisi data.
 */
class DealSeeder extends Seeder
{
    public const SHOWCASE = 'Implementasi ERP Gudang — 3 cabang';

    /**
     * Jumlah deal per tahap akhir (total 80; showcase dihitung di Negosiasi).
     */
    public const DISTRIBUTION = [
        'Prospek'     => 12,
        'Kualifikasi' => 12,
        'Proposal'    => 11,
        'Negosiasi'   => 9,
        'Won'         => 20,
        'Lost'        => 16,
    ];

    /**
     * Template judul per industri; {n} diganti angka.
     */
    private const TITLES = [
        'Manufaktur' => ['Implementasi ERP Produksi', 'Upgrade server & storage pabrik', 'Lisensi ERP {n} user', 'Sistem barcode gudang bahan baku'],
        'Retail'     => ['POS {n} outlet', 'Upgrade jaringan WiFi {n} toko', 'Dashboard penjualan BI', 'Terminal POS & printer {n} unit'],
        'Logistik'   => ['WMS gudang {n} lokasi', 'Implementasi ERP Logistik', 'Handheld scanner {n} unit', 'Maintenance sistem tahunan'],
        'Kesehatan'  => ['SIMRS klinik {n} cabang', 'Upgrade server rekam medis', 'Firewall & keamanan jaringan', 'Lisensi HRIS tenaga medis'],
        'Pendidikan' => ['Sistem Informasi Sekolah', 'Jaringan WiFi kampus {n} gedung', 'Lab komputer {n} unit', 'Pelatihan guru & staf TU'],
        'F&B'        => ['POS restoran {n} outlet', 'Dashboard penjualan cabang', 'Upgrade jaringan outlet', 'HRIS {n} karyawan'],
        'Konstruksi' => ['ERP proyek & anggaran', 'Server & NAS kantor proyek', 'HRIS {n} karyawan lapangan', 'Lisensi ERP {n} user'],
        'Perbankan'  => ['Firewall & UTM kantor cabang', 'Upgrade core server', 'Dashboard BI kredit', 'Maintenance hardware tahunan'],
    ];

    public function run(): void
    {
        if ($this->db->table('deals')->countAllResults() > 0) {
            if (! $this->silent) {
                CLI::write('DealSeeder: data deal sudah ada, dilewati.', 'yellow');
            }

            return;
        }

        $faker       = DemoData::faker(40);
        $stages      = $this->db->table('pipeline_stages')->orderBy('sort_order')->get()->getResultArray();
        $stageByName = array_column($stages, null, 'name');
        $openPath    = array_values(array_filter($stages, static fn ($s): bool => ! $s['is_won'] && ! $s['is_lost']));
        $lostReasons = array_values(DemoData::masterIds($this->db, 'lost_reasons'));
        $companies   = $this->db->table('companies c')
            ->select('c.id, c.name, c.owner_id, c.created_at, i.name AS industry')
            ->join('industries i', 'i.id = c.industry_id', 'left')
            ->orderBy('c.id')
            ->get()->getResultArray();
        $contacts = [];

        foreach ($this->db->table('contacts')->select('id, company_id')->orderBy('id')->get()->getResultArray() as $row) {
            $contacts[$row['company_id']][] = (int) $row['id'];
        }

        $plans = [];

        foreach (self::DISTRIBUTION as $stageName => $total) {
            for ($i = 0; $i < $total; $i++) {
                $plans[] = $stageName;
            }
        }

        // Deal showcase menempati satu slot Negosiasi.
        $showcaseCompany = array_values(array_filter($companies, static fn ($c): bool => $c['name'] === CompanySeeder::SHOWCASE))[0] ?? null;

        if ($showcaseCompany !== null) {
            unset($plans[array_search('Negosiasi', $plans, true)]);
            $this->createDeal($faker, $showcaseCompany, $contacts[$showcaseCompany['id']] ?? [], $stageByName, $openPath, 'Negosiasi', $lostReasons, true);
        }

        $closedCount = ['Won' => 0, 'Lost' => 0];

        foreach ($faker->shuffle(array_values($plans)) as $i => $stageName) {
            $closedDaysAgo = null;
            $minAge        = 0;

            // Deal Won/Lost: tanggal tutup disebar merata ke 6 bulan terakhir.
            if (isset($closedCount[$stageName])) {
                $k             = $closedCount[$stageName]++;
                $closedDaysAgo = 2 + (int) floor($k * 145 / self::DISTRIBUTION[$stageName]) + $faker->numberBetween(0, 5);
                $minAge        = $closedDaysAgo + 12;
            }

            $company = $this->pickCompany($companies, $i * 7 + 3, $minAge);
            $this->createDeal($faker, $company, $contacts[$company['id']] ?? [], $stageByName, $openPath, $stageName, $lostReasons, false, $closedDaysAgo);
        }

        $this->linkConvertedLeads();
    }

    /**
     * @param array<string, mixed>                $company
     * @param list<int>                           $contactIds
     * @param array<string, array<string, mixed>> $stageByName
     * @param list<array<string, mixed>>          $openPath
     * @param list<int>                           $lostReasons
     */
    private function createDeal(\Faker\Generator $faker, array $company, array $contactIds, array $stageByName, array $openPath, string $finalStage, array $lostReasons, bool $isShowcase, ?int $closedDaysAgo = null): void
    {
        $final  = $stageByName[$finalStage];
        $isWon  = (int) $final['is_won'] === 1;
        $isLost = (int) $final['is_lost'] === 1;

        // Jalur tahap: Prospek → ... → tahap akhir (Lost bisa berhenti di tahap terbuka mana pun).
        if ($isWon) {
            $path = [...$openPath, $final];
        } elseif ($isLost) {
            $path = [...array_slice($openPath, 0, $faker->numberBetween(1, count($openPath))), $final];
        } else {
            $path = array_values(array_filter($openPath, static fn ($s): bool => (int) $s['sort_order'] <= (int) $final['sort_order']));
        }

        $companyCreated = Time::parse($company['created_at']);
        $daysAvailable  = max(10, (int) floor((time() - $companyCreated->getTimestamp()) / 86400));

        if ($isShowcase) {
            $createdAt = Time::today()->subDays(55)->setHour(10);
            $closedAt  = null;
        } elseif ($isWon || $isLost) {
            // Deal dibuat 10–60 hari sebelum ditutup, tidak lebih awal dari perusahaannya.
            $closedDaysAgo ??= $faker->numberBetween(1, max(1, min(175, $daysAvailable - 10)));
            $createdDaysAgo = $closedDaysAgo + $faker->numberBetween(10, max(10, min(60, $daysAvailable - $closedDaysAgo - 1)));
            $createdAt      = Time::today()->subDays($createdDaysAgo)->setHour($faker->numberBetween(8, 16));
            $closedAt       = Time::today()->subDays($closedDaysAgo)->setHour($faker->numberBetween(9, 16))->setMinute($faker->numberBetween(0, 59));
        } else {
            $createdAt = Time::today()->subDays($faker->numberBetween(3, min(120, $daysAvailable)))->setHour($faker->numberBetween(8, 16));
            $closedAt  = null;
        }

        $endAt  = $closedAt ?? Time::now();
        $amount = $isShowcase ? 485000000 : $faker->numberBetween(30, 1700) * 500000;
        $title  = $isShowcase ? self::SHOWCASE : $this->title($faker, (string) $company['industry']);

        $this->db->table('deals')->insert([
            'title'               => $title,
            'company_id'          => $company['id'],
            'contact_id'          => $isShowcase ? $this->showcaseContact() : ($contactIds === [] ? null : $faker->randomElement($contactIds)),
            'stage_id'            => $final['id'],
            'amount'              => $amount,
            'probability'         => $final['probability'],
            'expected_close_date' => $isShowcase
                ? date('Y-m-t', strtotime('first day of next month'))
                : ($closedAt?->format('Y-m-d') ?? Time::today()->addDays($faker->numberBetween(-10, 90))->format('Y-m-d')),
            'closed_at'      => $closedAt?->toDateTimeString(),
            'lost_reason_id' => $isLost ? $faker->randomElement($lostReasons) : null,
            'owner_id'       => $company['owner_id'],
            'created_at'     => $createdAt->toDateTimeString(),
            'updated_at'     => $endAt->toDateTimeString(),
        ]);

        $dealId = (int) $this->db->insertID();

        // Riwayat tahap dengan waktu bertahap antara tanggal dibuat dan tanggal tutup/sekarang.
        $span    = max(3600, $endAt->getTimestamp() - $createdAt->getTimestamp());
        $steps   = count($path);
        $history = [];
        $from    = null;

        foreach ($path as $i => $stage) {
            $at = $i === 0 ? $createdAt : ($i === $steps - 1 && $closedAt !== null ? $closedAt : Time::createFromTimestamp($createdAt->getTimestamp() + (int) ($span * $i / $steps)));

            $history[] = [
                'deal_id'       => $dealId,
                'from_stage_id' => $from,
                'to_stage_id'   => $stage['id'],
                'changed_by'    => $company['owner_id'],
                'changed_at'    => $at->toDateTimeString(),
            ];
            $from = $stage['id'];
        }

        $this->db->table('deal_stage_history')->insertBatch($history);
    }

    /**
     * Ambil perusahaan bergiliran yang umurnya minimal $minAge hari (agar deal tidak lebih tua dari perusahaannya).
     *
     * @param list<array<string, mixed>> $companies
     *
     * @return array<string, mixed>
     */
    private function pickCompany(array $companies, int $start, int $minAge): array
    {
        $count = count($companies);

        for ($offset = 0; $offset < $count; $offset++) {
            $company = $companies[($start + $offset) % $count];

            if ((time() - Time::parse($company['created_at'])->getTimestamp()) / 86400 >= $minAge) {
                return $company;
            }
        }

        return $companies[$start % $count];
    }

    private function title(\Faker\Generator $faker, string $industry): string
    {
        $template = $faker->randomElement(self::TITLES[$industry] ?? self::TITLES['Manufaktur']);

        return str_replace('{n}', (string) $faker->randomElement([3, 5, 8, 10, 12, 15, 20, 25, 40, 50]), $template);
    }

    private function showcaseContact(): ?int
    {
        $id = $this->db->table('contacts')->select('id')->where('email', 'bambang.hartono@sinarlogistik.co.id')->get()->getRow('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Lead yang sudah dikonversi ditautkan ke deal pertama perusahaan hasil konversinya.
     */
    private function linkConvertedLeads(): void
    {
        $leads = $this->db->table('leads')->select('id, converted_company_id')->where('status', 'converted')->get()->getResultArray();

        foreach ($leads as $lead) {
            $dealId = $this->db->table('deals')->select('id')->where('company_id', $lead['converted_company_id'])->orderBy('id')->limit(1)->get()->getRow('id');

            if ($dealId !== null) {
                $this->db->table('leads')->where('id', $lead['id'])->update(['converted_deal_id' => $dealId]);
            }
        }
    }
}
