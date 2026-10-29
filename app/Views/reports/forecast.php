<?php
/**
 * @var string               $report
 * @var array<string, mixed> $data
 */
$th  = 'whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500';
$td  = 'whitespace-nowrap px-4 py-3';
$max = max(1, ...array_column($data['rows'], 'weighted'));
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('reports/_header', ['report' => $report, 'period' => null, 'subtitle' => 'Perkiraan pendapatan dari deal terbuka: nilai × probabilitas, per bulan perkiraan closing.'], ['saveData' => false]) ?>

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Deal terbuka</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900"><?= angka($data['totals']['count']) ?></p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Total nilai pipeline</p>
        <p class="mt-2 text-2xl font-semibold text-slate-900"><?= rupiah($data['totals']['amount']) ?></p>
    </div>
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-sm text-slate-500">Forecast tertimbang</p>
        <p class="mt-2 text-2xl font-semibold text-brand-700"><?= rupiah($data['totals']['weighted']) ?></p>
    </div>
</div>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="<?= $th ?> text-left">Perkiraan closing</th>
                    <th class="<?= $th ?> text-right">Deal</th>
                    <th class="<?= $th ?> text-right">Nilai</th>
                    <th class="<?= $th ?> text-right">Forecast tertimbang</th>
                    <th class="<?= $th ?> w-1/4"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($data['rows'] as $i => $row): ?>
                    <tr class="<?= $i === 0 && $row['count'] > 0 ? 'bg-red-50/50' : '' ?>">
                        <td class="<?= $td ?> font-medium <?= $i === 0 ? 'text-red-700' : 'text-slate-900' ?>"><?= esc($row['label']) ?></td>
                        <td class="<?= $td ?> text-right text-slate-700"><?= angka($row['count']) ?></td>
                        <td class="<?= $td ?> text-right text-slate-700"><?= rupiah($row['amount']) ?></td>
                        <td class="<?= $td ?> text-right font-semibold text-slate-900"><?= rupiah($row['weighted']) ?></td>
                        <td class="px-4 py-3">
                            <div class="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full rounded-full bg-brand-500" style="width: <?= round($row['weighted'] / $max * 100, 1) ?>%"></div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
            <tfoot class="bg-slate-50 font-semibold text-slate-900">
                <tr>
                    <td class="<?= $td ?>">Total</td>
                    <td class="<?= $td ?> text-right"><?= angka($data['totals']['count']) ?></td>
                    <td class="<?= $td ?> text-right"><?= rupiah($data['totals']['amount']) ?></td>
                    <td class="<?= $td ?> text-right"><?= rupiah($data['totals']['weighted']) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
<p class="mt-3 text-xs text-slate-500">Baris merah: deal yang perkiraan closing-nya sudah lewat tetapi belum ditutup — perbarui tanggal atau tutup deal tersebut.</p>
<?= $this->endSection() ?>
