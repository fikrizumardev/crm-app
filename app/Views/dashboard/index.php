<?php

use App\Entities\User;

/**
 * @var User                       $user
 * @var array<string, mixed>       $data
 * @var array<int, string>         $owners
 * @var int|null                   $ownerId
 * @var list<array<string, mixed>> $todayTasks
 * @var list<array<string, mixed>> $overdueTasks
 * @var array<string, int>         $taskCounts
 */
$hour     = (int) date('G');
$greeting = match (true) {
    $hour < 11 => 'Selamat pagi',
    $hour < 15 => 'Selamat siang',
    $hour < 18 => 'Selamat sore',
    default    => 'Selamat malam',
};
$kpi   = $data['kpi'];
$scope = $ownerId !== null ? $owners[$ownerId] : ($user->isSales() ? 'Anda' : ($user->isManager() ? 'tim Anda' : 'seluruh tim'));

$cards = [
    ['label' => 'Lead baru bulan ini', 'value' => angka($kpi['newLeads']), 'note' => 'sejak 1 ' . nama_bulan((int) date('n')), 'icon' => 'funnel', 'url' => site_url('leads?from=' . date('Y-m-01'))],
    ['label' => 'Deal terbuka', 'value' => angka($kpi['openDeals']), 'note' => 'semua tahap aktif', 'icon' => 'banknotes', 'url' => site_url('deals?status=open')],
    ['label' => 'Nilai pipeline', 'value' => rupiah_ringkas($kpi['pipelineValue']), 'title' => rupiah($kpi['pipelineValue']), 'note' => 'tertimbang ' . rupiah_ringkas($kpi['weightedValue']), 'icon' => 'chart-bar', 'url' => site_url('deals/board')],
    ['label' => 'Won bulan ini', 'value' => rupiah_ringkas($kpi['wonValue']), 'title' => rupiah($kpi['wonValue']), 'note' => angka($kpi['wonCount']) . ' deal', 'icon' => 'check-circle', 'url' => site_url('deals?status=won')],
    ['label' => 'Win rate', 'value' => persen($kpi['winRate']), 'note' => '90 hari terakhir', 'icon' => 'sparkles', 'url' => null],
];

$chartData = ['pipeline' => $data['pipeline'], 'trend' => $data['trend']];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$filter = '';

if ($owners !== []) {
    ob_start(); ?>
    <form method="get" action="<?= site_url('/') ?>" x-data>
        <label for="dashboard-owner" class="sr-only">Tampilkan data</label>
        <select id="dashboard-owner" name="owner" @change="$el.form.submit()" class="rounded-md border-0 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
            <option value=""><?= $user->isAdmin() ? 'Seluruh tim' : 'Seluruh tim saya' ?></option>
            <?php foreach ($owners as $id => $name): ?>
                <option value="<?= $id ?>" <?= $ownerId === $id ? 'selected' : '' ?>><?= esc($name) ?></option>
            <?php endforeach ?>
        </select>
    </form>
    <?php $filter = ob_get_clean();
}
?>
<?= component('page-header', [
    'title'    => $greeting . ', ' . $user->firstName() . '!',
    'subtitle' => 'Ringkasan penjualan ' . $scope . ' per ' . tanggal_id(date('Y-m-d')) . '. Diperbarui ' . waktu_relatif($data['generated_at']) . '.',
    'actions'  => $filter,
]) ?>

<div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-5">
    <?php foreach ($cards as $card): ?>
        <?php $tag = $card['url'] ? 'a' : 'div' ?>
        <<?= $tag ?> <?= $card['url'] ? 'href="' . esc($card['url'], 'attr') . '"' : '' ?> class="block rounded-lg border border-slate-200 bg-white p-4 shadow-sm <?= $card['url'] ? 'hover:border-brand-300' : '' ?>">
            <div class="flex items-center justify-between gap-2">
                <p class="text-sm font-medium text-slate-500"><?= esc($card['label']) ?></p>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-brand-50"><?= icon($card['icon'], 'h-4 w-4 text-brand-600') ?></span>
            </div>
            <p class="mt-3 truncate text-2xl font-semibold text-slate-900" title="<?= esc($card['title'] ?? $card['value'], 'attr') ?>"><?= esc($card['value']) ?></p>
            <p class="mt-1 truncate text-xs text-slate-500"><?= esc($card['note']) ?></p>
        </<?= $tag ?>>
    <?php endforeach ?>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Nilai pipeline per tahap</h2>
        </div>
        <div class="h-72 p-4"><canvas id="chart-pipeline" aria-label="Grafik nilai pipeline per tahap" role="img"></canvas></div>
    </section>
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Tren nilai Won 6 bulan terakhir</h2>
        </div>
        <div class="h-72 p-4"><canvas id="chart-trend" aria-label="Grafik tren nilai Won" role="img"></canvas></div>
    </section>
</div>

<div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Tugas saya hari ini <span class="font-normal text-slate-500">(<?= $taskCounts['today'] ?>)</span></h2>
            <a href="<?= site_url('tasks?tab=today') ?>" class="text-xs font-medium text-brand-700 hover:text-brand-800">Lihat semua</a>
        </div>
        <?= component('task-list', ['tasks' => $todayTasks, 'emptyTitle' => 'Tidak ada tugas hari ini', 'emptyMessage' => 'Semua follow-up hari ini sudah beres.']) ?>
    </section>
    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Tugas terlambat <span class="font-normal <?= $taskCounts['overdue'] > 0 ? 'text-red-600' : 'text-slate-500' ?>">(<?= $taskCounts['overdue'] ?>)</span></h2>
            <a href="<?= site_url('tasks?tab=overdue') ?>" class="text-xs font-medium text-brand-700 hover:text-brand-800">Lihat semua</a>
        </div>
        <?= component('task-list', ['tasks' => $overdueTasks, 'emptyTitle' => 'Tidak ada tugas terlambat', 'emptyMessage' => 'Bagus! Semua tugas tepat waktu.']) ?>
    </section>
</div>

<script type="application/json" id="dashboard-data"><?= json_encode($chartData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.6.1/dist/chart.umd.min.js"></script>
<script <?= csp_script_nonce() ?>>
    (() => {
        const data = JSON.parse(document.getElementById('dashboard-data').textContent);
        const short = (value) => {
            if (value >= 1e9) return 'Rp ' + (value / 1e9).toLocaleString('id-ID', { maximumFractionDigits: 1 }) + ' M';
            if (value >= 1e6) return 'Rp ' + (value / 1e6).toLocaleString('id-ID', { maximumFractionDigits: 0 }) + ' jt';
            return window.crm.rupiah(value);
        };
        Chart.defaults.font.family = getComputedStyle(document.body).fontFamily;
        Chart.defaults.color = '#64748b';

        new Chart(document.getElementById('chart-pipeline'), {
            type: 'bar',
            data: {
                labels: data.pipeline.labels,
                datasets: [{ label: 'Nilai pipeline', data: data.pipeline.values, backgroundColor: '#337ef8', borderRadius: 6, maxBarThickness: 48 }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (ctx) => window.crm.rupiah(ctx.parsed.y) + ' · ' + data.pipeline.counts[ctx.dataIndex] + ' deal' } },
                },
                scales: { y: { beginAtZero: true, ticks: { callback: short } }, x: { grid: { display: false } } },
            },
        });

        new Chart(document.getElementById('chart-trend'), {
            type: 'line',
            data: {
                labels: data.trend.labels,
                datasets: [{ label: 'Nilai Won', data: data.trend.values, borderColor: '#16a34a', backgroundColor: 'rgba(22, 163, 74, 0.12)', fill: true, tension: 0.3, pointRadius: 4 }],
            },
            options: {
                maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: (ctx) => window.crm.rupiah(ctx.parsed.y) } } },
                scales: { y: { beginAtZero: true, ticks: { callback: short } }, x: { grid: { display: false } } },
            },
        });
    })();
</script>
<?= $this->endSection() ?>
