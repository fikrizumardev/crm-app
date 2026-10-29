<?php

use App\Entities\User;
use App\Models\LeadModel;
use CodeIgniter\Pager\Pager;

/**
 * @var list<array<string, mixed>> $leads
 * @var Pager                      $pager
 * @var array<string, mixed>       $filters
 * @var array<int, string>         $sources
 * @var array<int, string>         $owners
 * @var bool                       $canExport
 * @var User                       $user
 */
$leadModel   = model(LeadModel::class);
$canReassign = ! $user->isSales() && $owners !== [];
$hasFilter   = array_filter(array_diff_key($filters, ['sort' => 1, 'dir' => 1]), static fn ($v): bool => $v !== null && $v !== '') !== [];
$inputClass  = 'block w-full rounded-md border-0 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?php
$actions = component('button', ['label' => 'Impor', 'url' => site_url('leads/import'), 'icon' => 'arrow-up-tray', 'variant' => 'secondary']);

if ($canExport) {
    $actions .= component('button', ['label' => 'Ekspor CSV', 'url' => query_url('leads/export', ['page' => null]), 'icon' => 'arrow-down-tray', 'variant' => 'secondary']);
}

$actions .= component('button', ['label' => 'Tambah Lead', 'url' => site_url('leads/new'), 'icon' => 'plus']);
?>
<?= component('page-header', [
    'title'    => 'Lead',
    'subtitle' => 'Prospek yang belum menjadi pelanggan.',
    'actions'  => $actions,
]) ?>

<form method="get" action="<?= site_url('leads') ?>" class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <label for="filter-q" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><?= icon('magnifying-glass', 'h-4 w-4') ?></span>
                <input id="filter-q" type="search" name="q" value="<?= esc($filters['q'] ?? '', 'attr') ?>" placeholder="Nama, perusahaan, no. HP, email" class="<?= $inputClass ?> pl-9">
            </div>
        </div>
        <div>
            <label for="filter-status" class="mb-1 block text-xs font-medium text-slate-500">Status</label>
            <select id="filter-status" name="status" class="<?= $inputClass ?>">
                <option value="">Semua status</option>
                <?php foreach (LeadModel::STATUSES as $value => $label): ?>
                    <option value="<?= $value ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label for="filter-source" class="mb-1 block text-xs font-medium text-slate-500">Sumber</label>
            <select id="filter-source" name="source" class="<?= $inputClass ?>">
                <option value="">Semua sumber</option>
                <?php foreach ($sources as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) ($filters['source'] ?? '') === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <?php if ($owners !== []): ?>
            <div>
                <label for="filter-owner" class="mb-1 block text-xs font-medium text-slate-500">Owner</label>
                <select id="filter-owner" name="owner" class="<?= $inputClass ?>">
                    <option value="">Semua owner</option>
                    <?php foreach ($owners as $id => $name): ?>
                        <option value="<?= $id ?>" <?= (string) ($filters['owner'] ?? '') === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
        <?php endif ?>
        <div class="grid grid-cols-2 gap-2 sm:col-span-2">
            <div>
                <label for="filter-from" class="mb-1 block text-xs font-medium text-slate-500">Dari tanggal</label>
                <input id="filter-from" type="date" name="from" value="<?= esc($filters['from'] ?? '', 'attr') ?>" class="<?= $inputClass ?>">
            </div>
            <div>
                <label for="filter-to" class="mb-1 block text-xs font-medium text-slate-500">Sampai</label>
                <input id="filter-to" type="date" name="to" value="<?= esc($filters['to'] ?? '', 'attr') ?>" class="<?= $inputClass ?>">
            </div>
        </div>
    </div>
    <input type="hidden" name="sort" value="<?= esc($filters['sort'] ?? '', 'attr') ?>">
    <input type="hidden" name="dir" value="<?= esc($filters['dir'] ?? '', 'attr') ?>">
    <div class="mt-3 flex items-center justify-end gap-2">
        <?php if ($hasFilter): ?>
            <?= component('button', ['label' => 'Reset', 'url' => site_url('leads'), 'variant' => 'ghost']) ?>
        <?php endif ?>
        <?= component('button', ['label' => 'Terapkan filter', 'icon' => 'funnel', 'variant' => 'secondary']) ?>
    </div>
</form>

<?php ob_start() ?>
<?php foreach ($leads as $lead): ?>
    <tr class="hover:bg-slate-50">
        <?php if ($canReassign): ?>
            <td class="w-10 px-4 py-3"><input type="checkbox" data-row-check value="<?= (int) $lead['id'] ?>" x-model="selected" aria-label="Pilih baris" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600"></td>
        <?php endif ?>
        <td class="px-4 py-3">
            <a href="<?= site_url('leads/' . $lead['id']) ?>" class="font-medium text-slate-900 hover:text-brand-700"><?= esc($lead['name']) ?></a>
            <div class="whitespace-nowrap text-xs text-slate-500"><?= esc($lead['phone'] ? format_hp($lead['phone']) : ($lead['email'] ?? '-')) ?></div>
        </td>
        <td class="px-4 py-3">
            <div class="text-slate-700"><?= esc($lead['company_name'] ?? '-') ?></div>
            <div class="text-xs text-slate-500"><?= esc($lead['job_title'] ?? '') ?></div>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= esc($lead['source_name'] ?? '-') ?></td>
        <td class="px-4 py-3"><?= component('badge', ['label' => LeadModel::STATUSES[$lead['status']], 'color' => LeadModel::STATUS_COLORS[$lead['status']]]) ?></td>
        <?php if (! $user->isSales()): ?>
            <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= esc($lead['owner_name']) ?></td>
        <?php endif ?>
        <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= tanggal_id($lead['created_at']) ?></td>
        <td class="whitespace-nowrap px-4 py-3 text-right">
            <a href="<?= site_url('leads/' . $lead['id'] . '/edit') ?>" class="inline-flex rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Ubah"><?= icon('pencil-square', 'h-4 w-4') ?></a>
            <?php if ($leadModel->canDelete($user, $lead)): ?>
                <?= component('delete-button', [
                    'action'  => site_url('leads/' . $lead['id'] . '/delete'),
                    'title'   => 'Hapus lead?',
                    'message' => 'Lead "' . $lead['name'] . '" akan dihapus dari daftar.',
                    'compact' => true,
                ]) ?>
            <?php endif ?>
        </td>
    </tr>
<?php endforeach ?>
<?php $rows = ob_get_clean() ?>

<?php
$selectAll = '<input type="checkbox" aria-label="Pilih semua" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600" @change="selected = $event.target.checked ? [...document.querySelectorAll(\'[data-row-check]\')].map((c) => c.value) : []">';
$columns   = [
    ...($canReassign ? [['label' => '', 'class' => 'w-10', 'html' => $selectAll]] : []),
    ['label' => 'Nama', 'sort' => 'name'],
    ['label' => 'Perusahaan', 'sort' => 'company'],
    ['label' => 'Sumber', 'sort' => 'source'],
    ['label' => 'Status', 'sort' => 'status'],
];

if (! $user->isSales()) {
    $columns[] = ['label' => 'Owner', 'sort' => 'owner'];
}

$columns[] = ['label' => 'Dibuat', 'sort' => 'created_at'];
$columns[] = ['label' => '', 'class' => 'text-right'];
?>
<div x-data="{ selected: [] }">
<?php if ($canReassign): ?>
    <?= component('bulk-reassign', ['path' => 'leads', 'owners' => $owners]) ?>
<?php endif ?>
<?= component('table', [
    'columns' => $columns,
    'rows'    => $rows,
    'empty'   => $hasFilter
        ? component('empty-state', ['icon' => 'magnifying-glass', 'title' => 'Tidak ada lead yang cocok', 'message' => 'Coba ubah kata kunci atau filter pencarian.'])
        : component('empty-state', ['icon' => 'funnel', 'title' => 'Belum ada lead', 'message' => 'Tambahkan lead pertama atau impor dari file CSV.', 'actionUrl' => site_url('leads/new'), 'actionLabel' => 'Tambah Lead']),
    'footer'  => $pager->links('default', 'tailwind'),
]) ?>
</div>
<?= $this->endSection() ?>
