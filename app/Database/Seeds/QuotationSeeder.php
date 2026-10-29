<?php

namespace App\Database\Seeds;

use App\Services\QuotationService;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * 30 penawaran untuk deal tahap Proposal ke atas, 2–6 item per penawaran.
 * Nomor QUO/YYYY/MM/NNNN berurutan per bulan. Dilewati bila tabel penawaran sudah berisi data.
 */
class QuotationSeeder extends Seeder
{
    public const TOTAL = 30;

    public function run(): void
    {
        if ($this->db->table('quotations')->countAllResults() > 0) {
            if (! $this->silent) {
                CLI::write('QuotationSeeder: data penawaran sudah ada, dilewati.', 'yellow');
            }

            return;
        }

        $faker    = DemoData::faker(70);
        $taxRate  = (float) ($this->db->table('settings')->where('key', 'tax_rate')->get()->getRow('value') ?? 11);
        $products = $this->db->table('products')->where('is_active', 1)->where('deleted_at', null)->orderBy('id')->get()->getResultArray();
        $bySku    = array_column($products, null, 'sku');
        $proposal = (int) $this->db->table('pipeline_stages')->where('name', 'Proposal')->get()->getRow('sort_order');

        // Deal yang pernah mencapai tahap Proposal (termasuk Won/Lost setelah Proposal).
        $deals = $this->db->table('deals d')
            ->select('d.id, d.title, d.owner_id, d.created_at, d.closed_at, s.is_won, s.is_lost, s.sort_order')
            ->join('pipeline_stages s', 's.id = d.stage_id')
            ->where('d.deleted_at', null)
            ->where("EXISTS (SELECT 1 FROM deal_stage_history h JOIN pipeline_stages hs ON hs.id = h.to_stage_id WHERE h.deal_id = d.id AND hs.sort_order >= {$proposal} AND hs.is_lost = 0)", null, false)
            ->orderBy('d.id')
            ->get()->getResultArray();

        if ($deals === [] || $products === []) {
            return;
        }

        $plans = [];

        foreach (array_slice($deals, 0, self::TOTAL) as $deal) {
            $isShowcase = $deal['title'] === DealSeeder::SHOWCASE;
            $start      = Time::parse($deal['created_at'])->addDays(3);
            $end        = $deal['closed_at'] !== null ? Time::parse($deal['closed_at'])->subDays(1) : Time::today();
            $issue      = $isShowcase ? Time::today()->subDays(10) : $this->between($faker, $start, $end);

            $items = $isShowcase ? $this->showcaseItems($bySku) : $this->randomItems($faker, $products);
            $plans[] = [
                'deal'   => $deal,
                'issue'  => $issue,
                'items'  => $items,
                'status' => match (true) {
                    (int) $deal['is_won'] === 1  => 'accepted',
                    (int) $deal['is_lost'] === 1 => 'rejected',
                    $isShowcase                  => 'sent',
                    default                      => $faker->randomElement(['draft', 'sent', 'sent']),
                },
                'notes' => "Harga belum termasuk biaya pengiriman di luar kota.\nPembayaran 50% di muka, 50% setelah serah terima.",
            ];
        }

        // Nomor berurutan per bulan sesuai tanggal penawaran.
        usort($plans, static fn (array $a, array $b): int => [$a['issue']->getTimestamp(), $a['deal']['id']] <=> [$b['issue']->getTimestamp(), $b['deal']['id']]);
        $sequence = [];

        foreach ($plans as $plan) {
            $month            = $plan['issue']->format('Y/m');
            $sequence[$month] = ($sequence[$month] ?? 0) + 1;
            $totals           = QuotationService::calculate($plan['items'], $taxRate);
            $issueDate        = $plan['issue']->format('Y-m-d');
            $created          = $plan['issue']->setHour(10)->toDateTimeString();

            $this->db->table('quotations')->insert([
                'number'         => sprintf('QUO/%s/%04d', $month, $sequence[$month]),
                'deal_id'        => $plan['deal']['id'],
                'issue_date'     => $issueDate,
                'valid_until'    => $plan['issue']->addDays(14)->format('Y-m-d'),
                'subtotal'       => $totals['subtotal'],
                'discount_total' => $totals['discount_total'],
                'tax_rate'       => $taxRate,
                'tax_amount'     => $totals['tax_amount'],
                'grand_total'    => $totals['grand_total'],
                'status'         => $plan['status'],
                'notes'          => $plan['notes'],
                'created_by'     => $plan['deal']['owner_id'],
                'created_at'     => $created,
                'updated_at'     => $created,
            ]);

            $quotationId = (int) $this->db->insertID();
            $rows        = [];

            foreach ($plan['items'] as $i => $item) {
                $rows[] = $item + ['quotation_id' => $quotationId, 'line_total' => $totals['lines'][$i], 'created_at' => $created, 'updated_at' => $created];
            }

            $this->db->table('quotation_items')->insertBatch($rows);
        }
    }

    /**
     * @param list<array<string, mixed>> $products
     *
     * @return list<array<string, mixed>>
     */
    private function randomItems(\Faker\Generator $faker, array $products): array
    {
        $items = [];

        foreach ($faker->randomElements($products, $faker->numberBetween(2, 6)) as $product) {
            $qty = match ($product['unit']) {
                'user', 'outlet', 'unit' => $faker->randomElement([1, 2, 5, 10, 15, 20, 25, 40]),
                'hari', 'man-day'        => $faker->numberBetween(2, 10),
                'tiket'                  => $faker->numberBetween(5, 20),
                default                  => 1,
            };

            $items[] = [
                'product_id'   => (int) $product['id'],
                'description'  => $product['name'],
                'qty'          => $qty,
                'unit_price'   => (float) $product['price'],
                'discount_pct' => $faker->randomElement([0, 0, 0, 5, 10]),
            ];
        }

        return $items;
    }

    /**
     * Penawaran deal showcase: lisensi ERP 40 user + implementasi + maintenance.
     *
     * @param array<string, array<string, mixed>> $bySku
     *
     * @return list<array<string, mixed>>
     */
    private function showcaseItems(array $bySku): array
    {
        $item = static fn (string $sku, float $qty, float $discount): array => [
            'product_id'   => (int) $bySku[$sku]['id'],
            'description'  => $bySku[$sku]['name'],
            'qty'          => $qty,
            'unit_price'   => (float) $bySku[$sku]['price'],
            'discount_pct' => $discount,
        ];

        return [
            $item('LIC-ERP-USR', 40, 10),
            $item('LIC-WMS-SITE', 3, 0),
            $item('SVC-TRN-DAY', 6, 0),
            $item('MNT-ERP-YR', 1, 0),
        ];
    }

    private function between(\Faker\Generator $faker, Time $start, Time $end): Time
    {
        if ($end->isBefore($start)) {
            return $start->isAfter(Time::today()) ? Time::today() : $start;
        }

        $date = Time::createFromTimestamp($faker->numberBetween($start->getTimestamp(), $end->getTimestamp()), app_timezone());

        return $date->isAfter(Time::today()) ? Time::today() : $date->setTime(0, 0);
    }
}
