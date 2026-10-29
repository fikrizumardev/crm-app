<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * Master data: sumber lead, industri, tahap pipeline, alasan kalah.
 * Idempoten: baris dicocokkan berdasarkan nama.
 */
class MasterDataSeeder extends Seeder
{
    public const LEAD_SOURCES = ['Website', 'Referral', 'Pameran', 'Instagram', 'Google Ads', 'Cold Call', 'WhatsApp'];
    public const INDUSTRIES   = ['Manufaktur', 'Retail', 'Logistik', 'Kesehatan', 'Pendidikan', 'F&B', 'Konstruksi', 'Perbankan'];
    public const LOST_REASONS = ['Harga', 'Kompetitor', 'Tidak ada budget', 'Tidak ada respons', 'Lainnya'];

    /**
     * @var list<array{name: string, probability: int, sort_order: int, is_won: int, is_lost: int}>
     */
    public const PIPELINE_STAGES = [
        ['name' => 'Prospek', 'probability' => 10, 'sort_order' => 1, 'is_won' => 0, 'is_lost' => 0],
        ['name' => 'Kualifikasi', 'probability' => 25, 'sort_order' => 2, 'is_won' => 0, 'is_lost' => 0],
        ['name' => 'Proposal', 'probability' => 50, 'sort_order' => 3, 'is_won' => 0, 'is_lost' => 0],
        ['name' => 'Negosiasi', 'probability' => 75, 'sort_order' => 4, 'is_won' => 0, 'is_lost' => 0],
        ['name' => 'Won', 'probability' => 100, 'sort_order' => 5, 'is_won' => 1, 'is_lost' => 0],
        ['name' => 'Lost', 'probability' => 0, 'sort_order' => 6, 'is_won' => 0, 'is_lost' => 1],
    ];

    public function run(): void
    {
        $this->upsertNames('lead_sources', self::LEAD_SOURCES);
        $this->upsertNames('industries', self::INDUSTRIES);
        $this->upsertNames('lost_reasons', self::LOST_REASONS);

        foreach (self::PIPELINE_STAGES as $stage) {
            $this->upsert('pipeline_stages', $stage);
        }
    }

    /**
     * @param list<string> $names
     */
    private function upsertNames(string $table, array $names): void
    {
        foreach ($names as $name) {
            $this->upsert($table, ['name' => $name]);
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function upsert(string $table, array $row): void
    {
        $now     = Time::now()->toDateTimeString();
        $builder = $this->db->table($table);

        if ($builder->where('name', $row['name'])->countAllResults() > 0) {
            return;
        }

        $builder->insert($row + ['created_at' => $now, 'updated_at' => $now]);
    }
}
