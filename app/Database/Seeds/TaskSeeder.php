<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * ±150 tugas (25 per sales): 3 jatuh tempo hari ini, 3 terlambat, sisanya mendatang atau selesai.
 * Sebagian dibuat oleh manager (dengan notifikasi penugasan). Dilewati bila tabel tugas sudah berisi data.
 */
class TaskSeeder extends Seeder
{
    public const PER_SALES = 25;

    private const TITLES = [
        'Kirim proposal ke {name}',
        'Follow-up penawaran {name}',
        'Telepon {name} soal jadwal demo',
        'Siapkan demo untuk {name}',
        'Konfirmasi PO dari {name}',
        'Kirim revisi harga ke {name}',
        'Jadwalkan kunjungan ke {name}',
        'Minta data kebutuhan dari {name}',
    ];

    public function run(): void
    {
        if ($this->db->table('tasks')->countAllResults() > 0) {
            if (! $this->silent) {
                CLI::write('TaskSeeder: data tugas sudah ada, dilewati.', 'yellow');
            }

            return;
        }

        $faker    = DemoData::faker(60);
        $users    = DemoData::userIds($this->db);
        $managers = [];

        foreach ($this->db->table('users')->select('id, manager_id')->where('role', 'sales')->get()->getResultArray() as $row) {
            $managers[(int) $row['id']] = (int) $row['manager_id'];
        }

        $rows          = [];
        $notifications = [];

        foreach (DemoData::SALES as $username) {
            $userId  = $users[$username];
            $targets = $this->targets($userId);

            for ($i = 0; $i < self::PER_SALES; $i++) {
                [$type, $id, $name] = $targets === [] ? [null, null, 'klien'] : $targets[$i % count($targets)];

                // 0–2: hari ini, 3–5: terlambat, 6–15: mendatang, 16–24: selesai.
                $status = $i >= 16 ? 'done' : 'open';
                $due    = match (true) {
                    $i < 3  => Time::today()->setHour(9 + $i * 3),
                    $i < 6  => Time::today()->subDays($faker->numberBetween(1, 7))->setHour($faker->numberBetween(9, 16)),
                    $i < 16 => Time::today()->addDays($faker->numberBetween(1, 21))->setHour($faker->numberBetween(9, 16)),
                    default => Time::today()->subDays($faker->numberBetween(1, 60))->setHour($faker->numberBetween(9, 16)),
                };
                $createdBy = $i % 5 === 0 ? $managers[$userId] : $userId;
                $createdAt = Time::today()->subDays($faker->numberBetween(1, 10))->setHour(8);

                if ($createdAt->isAfter($due)) {
                    $createdAt = $due->subDays(2);
                }

                $title = str_replace('{name}', $name, $faker->randomElement(self::TITLES));

                $rows[] = [
                    'title'        => $title,
                    'description'  => $faker->boolean(50) ? 'Pastikan semua dokumen pendukung sudah lengkap sebelum dikirim.' : null,
                    'due_at'       => $due->toDateTimeString(),
                    'priority'     => $faker->randomElement(['low', 'medium', 'medium', 'high']),
                    'status'       => $status,
                    'completed_at' => $status === 'done' ? $due->subHours($faker->numberBetween(1, 30))->toDateTimeString() : null,
                    'related_type' => $type,
                    'related_id'   => $id,
                    'assigned_to'  => $userId,
                    'created_by'   => $createdBy,
                    'created_at'   => $createdAt->toDateTimeString(),
                    'updated_at'   => $createdAt->toDateTimeString(),
                ];

                if ($createdBy !== $userId && $status === 'open' && $i >= 6) {
                    $notifications[] = [
                        'user_id'    => $userId,
                        'type'       => 'task_assigned',
                        'message'    => 'Manager Anda memberi tugas: ' . $title,
                        'url'        => site_url('tasks?tab=week'),
                        'read_at'    => null,
                        'created_at' => $createdAt->toDateTimeString(),
                        'updated_at' => $createdAt->toDateTimeString(),
                    ];
                }
            }
        }

        $rows[] = $this->showcaseTask($users['andi.saputra']);

        $this->db->table('tasks')->insertBatch($rows);

        if ($notifications !== []) {
            $this->db->table('notifications')->insertBatch($notifications);
        }
    }

    /**
     * Record yang bisa ditautkan untuk seorang sales: deal terbuka, lead aktif, kontak.
     *
     * @return list<array{0: string, 1: int, 2: string}>
     */
    private function targets(int $userId): array
    {
        $targets = [];

        $deals = $this->db->table('deals d')
            ->select('d.id, c.name')
            ->join('companies c', 'c.id = d.company_id')
            ->join('pipeline_stages s', 's.id = d.stage_id')
            ->where('d.owner_id', $userId)->where('s.is_won', 0)->where('s.is_lost', 0)
            ->get()->getResultArray();

        foreach ($deals as $deal) {
            $targets[] = ['deal', (int) $deal['id'], $deal['name']];
        }

        $leads = $this->db->table('leads')->select('id, company_name')->where('owner_id', $userId)
            ->whereIn('status', ['new', 'contacted', 'qualified'])->limit(8)->get()->getResultArray();

        foreach ($leads as $lead) {
            $targets[] = ['lead', (int) $lead['id'], (string) $lead['company_name']];
        }

        return $targets;
    }

    /**
     * @return array<string, mixed>
     */
    private function showcaseTask(int $andiId): array
    {
        $dealId = $this->db->table('deals')->select('id')->where('title', DealSeeder::SHOWCASE)->get()->getRow('id');
        $now    = Time::now()->toDateTimeString();

        return [
            'title'        => 'Kirim revisi penawaran ke Pak Bambang',
            'description'  => 'Revisi harga lisensi untuk 40 user sesuai permintaan saat meeting demo.',
            'due_at'       => Time::tomorrow()->setHour(10)->toDateTimeString(),
            'priority'     => 'high',
            'status'       => 'open',
            'completed_at' => null,
            'related_type' => $dealId === null ? null : 'deal',
            'related_id'   => $dealId === null ? null : (int) $dealId,
            'assigned_to'  => $andiId,
            'created_by'   => $andiId,
            'created_at'   => $now,
            'updated_at'   => $now,
        ];
    }
}
