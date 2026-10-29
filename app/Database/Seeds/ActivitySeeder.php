<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * ±400 aktivitas dengan ringkasan percakapan realistis untuk deal, lead, kontak, dan perusahaan.
 * Dilewati bila tabel aktivitas sudah berisi data.
 */
class ActivitySeeder extends Seeder
{
    /**
     * Jenis → daftar [ringkasan, detail].
     */
    private const TEMPLATES = [
        'call' => [
            ['Telepon follow-up proposal', 'Klien sudah menerima proposal, minta waktu seminggu untuk diskusi internal.'],
            ['Konfirmasi jadwal demo', 'Demo disepakati hari Kamis jam 10.00 di kantor klien.'],
            ['Tanya progres persetujuan budget', 'Budget masih menunggu tanda tangan direktur keuangan.'],
            ['Telepon perkenalan', 'PIC tertarik, minta dikirimi company profile dan referensi pelanggan.'],
        ],
        'meeting' => [
            ['Meeting presentasi solusi', 'Presentasi ke tim IT dan operasional, ada pertanyaan soal integrasi dengan sistem akuntansi.'],
            ['Meeting negosiasi harga', 'Klien minta diskon 10% untuk pembelian lisensi tahunan.'],
            ['Kickoff kebutuhan sistem', 'Mengumpulkan kebutuhan gudang: 3 lokasi, 25 pengguna, laporan stok harian.'],
            ['Review draft kontrak', 'Legal klien minta revisi pasal SLA dan masa garansi.'],
        ],
        'email' => [
            ['Kirim proposal teknis', 'Proposal teknis dan estimasi biaya dikirim ke email PIC.'],
            ['Kirim revisi penawaran', 'Revisi penawaran dengan diskon volume dikirim, menunggu konfirmasi.'],
            ['Kirim materi demo', 'Rekaman demo dan user manual singkat dikirim untuk dipelajari tim klien.'],
        ],
        'whatsapp' => [
            ['Follow-up via WhatsApp', 'Mengingatkan jadwal meeting besok, PIC konfirmasi hadir.'],
            ['Kirim brosur via WhatsApp', 'Brosur produk dan daftar harga dikirim, klien akan pelajari dulu.'],
            ['Tanya kabar keputusan', 'PIC bilang keputusan diambil setelah rapat direksi akhir bulan.'],
        ],
        'visit' => [
            ['Kunjungan ke kantor klien', 'Survei lokasi gudang dan ruang server untuk rencana instalasi.'],
            ['Kunjungan silaturahmi', 'Bertemu direktur operasional, membahas rencana ekspansi cabang tahun depan.'],
            ['Demo langsung di lokasi', 'Demo aplikasi di gudang klien, tim operasional antusias dengan fitur scan barcode.'],
        ],
        'note' => [
            ['Catatan kebutuhan klien', 'Klien butuh laporan stok per gudang dan integrasi dengan aplikasi akuntansi yang sudah ada.'],
            ['Catatan pengambil keputusan', 'Keputusan akhir ada di direktur utama, PIC kami hanya memberi rekomendasi.'],
            ['Catatan kompetitor', 'Klien juga menerima penawaran dari dua vendor lain dengan harga lebih rendah.'],
        ],
    ];

    public function run(): void
    {
        if ($this->db->table('activities')->countAllResults() > 0) {
            if (! $this->silent) {
                CLI::write('ActivitySeeder: data aktivitas sudah ada, dilewati.', 'yellow');
            }

            return;
        }

        $faker = DemoData::faker(50);
        $rows  = [];

        // Deal: 2–5 aktivitas antara tanggal dibuat dan tanggal tutup/sekarang.
        $deals = $this->db->table('deals')->select('id, title, owner_id, created_at, closed_at')->orderBy('id')->get()->getResultArray();

        foreach ($deals as $deal) {
            if ($deal['title'] === DealSeeder::SHOWCASE) {
                $rows[] = $this->row('deal', $deal['id'], $deal['owner_id'], 'meeting', 'Meeting demo di kantor klien', 'Meeting demo di kantor klien, Pak Bambang minta revisi harga lisensi untuk 40 user.', Time::now()->subDays(2)->setHour(10)->setMinute(30));
                $rows[] = $this->row('deal', $deal['id'], $deal['owner_id'], 'call', 'Telepon follow-up proposal', 'Pak Bambang sudah menerima proposal, akan dibahas dengan tim keuangan.', Time::now()->subDays(12)->setHour(14));
                $rows[] = $this->row('deal', $deal['id'], $deal['owner_id'], 'visit', 'Survei 3 gudang cabang', 'Survei gudang Surabaya, Sidoarjo, dan Gresik untuk kebutuhan perangkat.', Time::now()->subDays(30)->setHour(9));

                continue;
            }

            $start = Time::parse($deal['created_at'])->getTimestamp();
            $end   = $deal['closed_at'] !== null ? Time::parse($deal['closed_at'])->getTimestamp() : time();

            for ($i = 0, $n = $faker->numberBetween(2, 5); $i < $n; $i++) {
                $type = $faker->randomElement(['call', 'call', 'meeting', 'email', 'whatsapp', 'whatsapp', 'visit', 'note']);
                [$subject, $description] = $faker->randomElement(self::TEMPLATES[$type]);

                $rows[] = $this->row('deal', $deal['id'], $deal['owner_id'], $type, $subject, $description, $this->between($faker, $start, $end));
            }
        }

        // Lead yang sudah dihubungi/kualifikasi: 1–2 aktivitas.
        $leads = $this->db->table('leads')->select('id, owner_id, created_at')->whereIn('status', ['contacted', 'qualified', 'unqualified'])->orderBy('id')->get()->getResultArray();

        foreach ($leads as $lead) {
            for ($i = 0, $n = $faker->numberBetween(1, 2); $i < $n; $i++) {
                $type = $faker->randomElement(['call', 'whatsapp', 'email']);
                [$subject, $description] = $faker->randomElement(self::TEMPLATES[$type]);

                $rows[] = $this->row('lead', $lead['id'], $lead['owner_id'], $type, $subject, $description, $this->between($faker, Time::parse($lead['created_at'])->getTimestamp(), time()));
            }
        }

        // Kontak & perusahaan: kunjungan dan catatan.
        $contacts = $this->db->table('contacts')->select('id, owner_id, created_at')->orderBy('id')->get()->getResultArray();

        foreach ($contacts as $i => $contact) {
            if ($i % 4 !== 0) {
                continue;
            }

            $type = $faker->randomElement(['visit', 'whatsapp', 'call']);
            [$subject, $description] = $faker->randomElement(self::TEMPLATES[$type]);
            $rows[] = $this->row('contact', $contact['id'], $contact['owner_id'], $type, $subject, $description, $this->between($faker, Time::parse($contact['created_at'])->getTimestamp(), time()));
        }

        $companies = $this->db->table('companies')->select('id, owner_id, created_at')->orderBy('id')->get()->getResultArray();

        foreach ($companies as $i => $company) {
            if ($i % 3 !== 0) {
                continue;
            }

            [$subject, $description] = $faker->randomElement(self::TEMPLATES['note']);
            $rows[] = $this->row('company', $company['id'], $company['owner_id'], 'note', $subject, $description, $this->between($faker, Time::parse($company['created_at'])->getTimestamp(), time()));
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            $this->db->table('activities')->insertBatch($chunk);
        }
    }

    private function between(\Faker\Generator $faker, int $start, int $end): Time
    {
        $timestamp = $faker->numberBetween($start, max($start, $end));
        $time      = Time::createFromTimestamp($timestamp, app_timezone());

        // Geser ke jam kerja.
        $time = $time->setHour($faker->numberBetween(8, 17))->setMinute($faker->numberBetween(0, 59));

        return $time->isAfter(Time::now()) ? Time::now()->subMinutes($faker->numberBetween(10, 240)) : $time;
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $type, int|string $id, int|string $userId, string $activityType, string $subject, string $description, Time $at): array
    {
        return [
            'type'         => $activityType,
            'subject'      => $subject,
            'description'  => $description,
            'related_type' => $type,
            'related_id'   => (int) $id,
            'activity_at'  => $at->toDateTimeString(),
            'user_id'      => (int) $userId,
            'created_at'   => $at->toDateTimeString(),
            'updated_at'   => $at->toDateTimeString(),
        ];
    }
}
