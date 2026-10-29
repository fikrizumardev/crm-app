<?php
/**
 * @var string                                   $report
 * @var array<string, mixed>                     $period
 * @var array{rows: list<array<string, mixed>>}  $data
 */
$th = 'whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500';
$td = 'whitespace-nowrap px-4 py-3';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= view('reports/_header', ['report' => $report, 'period' => $period, 'subtitle' => 'Sumber lead terbaik berdasarkan jumlah lead masuk dan tingkat konversi.'], ['saveData' => false]) ?>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50">
                <tr>
                    <th class="<?= $th ?> text-left">Sumber</th>
                    <th class="<?= $th ?> text-right">Lead masuk</th>
                    <th class="<?= $th ?> text-right">Kualifikasi+</th>
                    <th class="<?= $th ?> text-right">Dikonversi</th>
                    <th class="<?= $th ?> text-right">Tidak qualified</th>
                    <th class="<?= $th ?> w-1/4 text-left">Konversi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($data['rows'] as $row): ?>
                    <tr>
                        <td class="<?= $td ?> font-medium text-slate-900"><?= esc($row['name']) ?></td>
                        <td class="<?= $td ?> text-right text-slate-700"><?= angka($row['leads']) ?></td>
                        <td class="<?= $td ?> text-right text-slate-700"><?= angka($row['qualified']) ?></td>
                        <td class="<?= $td ?> text-right font-medium text-green-700"><?= angka($row['converted']) ?></td>
                        <td class="<?= $td ?> text-right text-slate-700"><?= angka($row['unqualified']) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-2">
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-brand-500" style="width: <?= (float) ($row['rate'] ?? 0) ?>%"></div>
                                </div>
                                <span class="w-12 text-right text-xs text-slate-600"><?= persen($row['rate']) ?></span>
                            </div>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
</div>
<p class="mt-3 text-xs text-slate-500">Dihitung dari lead yang masuk pada periode terpilih dan status terkininya. Konversi = Dikonversi ÷ Lead masuk.</p>
<?= $this->endSection() ?>
