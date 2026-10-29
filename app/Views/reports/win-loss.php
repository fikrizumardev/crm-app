<?php
/**
 * @var string               $report
 * @var array<string, mixed> $period
 * @var array<string, mixed> $data
 */
$th = 'whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500';
$td = 'whitespace-nowrap px-4 py-3';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('reports/_header', ['report' => $report, 'period' => $period, 'subtitle' => 'Perbandingan deal menang dan kalah serta alasan kalah terbanyak.'], ['saveData' => false]) ?>

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Won</p>
        <p class="mt-2 text-2xl font-semibold text-green-700"><?= angka($data['won_count']) ?> deal</p>
        <p class="text-sm text-slate-500"><?= rupiah($data['won_value']) ?></p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Lost</p>
        <p class="mt-2 text-2xl font-semibold text-red-700"><?= angka($data['lost_count']) ?> deal</p>
        <p class="text-sm text-slate-500"><?= rupiah($data['lost_value']) ?></p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Win rate</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900"><?= persen($data['win_rate']) ?></p>
        <p class="text-sm text-slate-500">Won ÷ (Won + Lost)</p>
    </div>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="border-b border-slate-200 px-5 py-3">
        <h2 class="text-sm font-semibold text-slate-800">Alasan kalah</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="<?= $th ?> text-left">Alasan</th>
                    <th class="<?= $th ?> text-right">Jumlah deal</th>
                    <th class="<?= $th ?> text-right">Nilai</th>
                    <th class="<?= $th ?> w-1/3 text-left">Porsi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($data['reasons'] as $reason): ?>
                    <tr>
                        <td class="<?= $td ?> font-medium text-slate-900"><?= esc($reason['name']) ?></td>
                        <td class="<?= $td ?> text-right text-slate-700"><?= angka($reason['count']) ?></td>
                        <td class="<?= $td ?> text-right text-slate-700"><?= rupiah($reason['value']) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-red-400" style="width: <?= (float) ($reason['percent'] ?? 0) ?>%"></div>
                                </div>
                                <span class="w-12 text-right text-xs text-slate-600"><?= persen($reason['percent']) ?></span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<?= $this->endSection() ?>
