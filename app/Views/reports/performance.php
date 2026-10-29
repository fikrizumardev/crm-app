<?php
/**
 * @var string               $report
 * @var array<string, mixed> $period
 * @var array{rows: list<array<string, mixed>>, totals: array<string, mixed>} $data
 */
$th = 'whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500';
$td = 'whitespace-nowrap px-4 py-3';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('reports/_header', ['report' => $report, 'period' => $period, 'subtitle' => 'Aktivitas dan hasil penjualan setiap sales pada periode terpilih.'], ['saveData' => false]) ?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <?php if ($data['rows'] === []): ?>
        <?= component('empty-state', ['icon' => 'chart-bar', 'title' => 'Belum ada sales di tim ini']) ?>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="<?= $th ?> text-left">Sales</th>
                        <th class="<?= $th ?> text-right">Lead baru</th>
                        <th class="<?= $th ?> text-right">Deal dibuat</th>
                        <th class="<?= $th ?> text-right">Won</th>
                        <th class="<?= $th ?> text-right">Nilai Won</th>
                        <th class="<?= $th ?> text-right">Lost</th>
                        <th class="<?= $th ?> text-right">Win rate</th>
                        <th class="<?= $th ?> text-right">Aktivitas</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($data['rows'] as $row): ?>
                        <tr class="hover:bg-slate-50">
                            <td class="<?= $td ?> font-medium text-slate-900">
                                <a href="<?= site_url('deals?owner=' . $row['id']) ?>" class="hover:text-brand-700"><?= esc($row['name']) ?></a>
                            </td>
                            <td class="<?= $td ?> text-right text-slate-700"><?= angka($row['leads']) ?></td>
                            <td class="<?= $td ?> text-right text-slate-700"><?= angka($row['deals']) ?></td>
                            <td class="<?= $td ?> text-right text-slate-700"><?= angka($row['won_count']) ?></td>
                            <td class="<?= $td ?> text-right font-medium text-green-700"><?= rupiah($row['won_value']) ?></td>
                            <td class="<?= $td ?> text-right text-slate-700"><?= angka($row['lost_count']) ?></td>
                            <td class="<?= $td ?> text-right text-slate-700"><?= persen($row['win_rate']) ?></td>
                            <td class="<?= $td ?> text-right text-slate-700"><?= angka($row['activities']) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
                <tfoot class="bg-slate-50 font-semibold text-slate-900">
                    <tr>
                        <td class="<?= $td ?>">Total</td>
                        <td class="<?= $td ?> text-right"><?= angka($data['totals']['leads']) ?></td>
                        <td class="<?= $td ?> text-right"><?= angka($data['totals']['deals']) ?></td>
                        <td class="<?= $td ?> text-right"><?= angka($data['totals']['won_count']) ?></td>
                        <td class="<?= $td ?> text-right text-green-700"><?= rupiah($data['totals']['won_value']) ?></td>
                        <td class="<?= $td ?> text-right"><?= angka($data['totals']['lost_count']) ?></td>
                        <td class="<?= $td ?> text-right"><?= persen($data['totals']['win_rate']) ?></td>
                        <td class="<?= $td ?> text-right"><?= angka($data['totals']['activities']) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    <?php endif ?>
</div>
<p class="mt-3 text-xs text-slate-500">Lead & deal dihitung dari tanggal dibuat, Won/Lost dari tanggal ditutup, aktivitas dari tanggal aktivitas. Win rate = Won ÷ (Won + Lost).</p>
<?= $this->endSection() ?>
