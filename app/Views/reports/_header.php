<?php

use App\Services\ReportService;

/**
 * Judul, tab laporan, dan filter periode.
 *
 * @var string                    $report
 * @var array<string, mixed>|null $period
 * @var string|null               $subtitle
 */
$tabs = [
    'performance' => 'Kinerja Sales',
    'win-loss'    => 'Win/Loss',
    'sources'     => 'Sumber Lead',
    'forecast'    => 'Forecast',
];
$exportUrl = query_url('reports/' . $report, ['export' => 'csv']);
?>
<?= component('page-header', [
    'title'    => 'Laporan',
    'subtitle' => $subtitle ?? null,
    'actions'  => component('button', ['label' => 'Ekspor CSV', 'url' => $exportUrl, 'icon' => 'arrow-down-tray', 'variant' => 'secondary']),
]) ?>

<nav class="mb-4 flex gap-1 overflow-x-auto border-b border-slate-200" aria-label="Jenis laporan">
    <?php foreach ($tabs as $key => $label): ?>
        <a href="<?= site_url('reports/' . $key) . ($period !== null && $key !== 'forecast' ? '?' . http_build_query(array_filter(['preset' => $period['preset'], 'from' => $period['preset'] ? null : $period['from'], 'to' => $period['preset'] ? null : $period['to']])) : '') ?>"
           class="whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium <?= $key === $report ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
            <?= esc($label) ?>
        </a>
    <?php endforeach ?>
</nav>

<?php if ($period !== null): ?>
    <form method="get" action="<?= site_url('reports/' . $report) ?>" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm"
          x-data="{ custom: <?= $period['preset'] === null ? 'true' : 'false' ?> }">
        <div>
            <label for="report-preset" class="mb-1 block text-xs font-medium text-slate-500">Periode</label>
            <select id="report-preset" name="preset" @change="custom = $el.value === ''; if (!custom) $el.form.submit()"
                    class="rounded-md border-0 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                <?php foreach (ReportService::PRESETS as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $period['preset'] === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
                <option value="" <?= $period['preset'] === null ? 'selected' : '' ?>>Pilih tanggal…</option>
            </select>
        </div>
        <template x-if="custom">
            <div class="flex flex-wrap items-end gap-3">
                <div>
                    <label for="report-from" class="mb-1 block text-xs font-medium text-slate-500">Dari</label>
                    <input id="report-from" type="date" name="from" value="<?= esc($period['from'], 'attr') ?>" class="rounded-md border-0 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                </div>
                <div>
                    <label for="report-to" class="mb-1 block text-xs font-medium text-slate-500">Sampai</label>
                    <input id="report-to" type="date" name="to" value="<?= esc($period['to'], 'attr') ?>" class="rounded-md border-0 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                </div>
                <?= component('button', ['label' => 'Tampilkan', 'variant' => 'secondary']) ?>
            </div>
        </template>
        <p class="ml-auto text-sm text-slate-500"><?= tanggal_id($period['from']) ?> – <?= tanggal_id($period['to']) ?></p>
    </form>
<?php endif ?>
