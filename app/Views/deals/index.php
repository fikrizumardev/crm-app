<?php

use App\Entities\User;
use App\Models\DealModel;
use CodeIgniter\Pager\Pager;

/**
 * @var list<array<string, mixed>> $deals
 * @var Pager                      $pager
 * @var array<string, mixed>       $filters
 * @var array<int, string>         $stages
 * @var array<int, string>         $owners
 * @var User                       $user
 */
$dealModel  = model(DealModel::class);
$canReassign = ! $user->isSales() && $owners !== [];
$hasFilter  = array_filter(array_diff_key($filters, ['sort' => 1, 'dir' => 1]), static fn ($v): bool => $v !== null && $v !== '') !== [];
$inputClass = 'block w-full rounded-md border-0 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
$today      = date('Y-m-d');

$actions = component('button', ['label' => 'Papan Kanban', 'url' => site_url('deals/board'), 'icon' => 'squares-2x2', 'variant' => 'secondary'])
    . component('button', ['label' => 'Tambah Deal', 'url' => site_url('deals/new'), 'icon' => 'plus']);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Deal',
    'subtitle' => 'Peluang penjualan beserta nilai, tahap, dan perkiraan closing.',
    'actions'  => $actions,
]) ?>

<form method="get" action="<?= site_url('deals') ?>" class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <label for="filter-q" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><?= icon('magnifying-glass', 'h-4 w-4') ?></span>
                <input id="filter-q" type="search" name="q" value="<?= esc($filters['q'] ?? '', 'attr') ?>" placeholder="Judul deal atau perusahaan" class="<?= $inputClass ?> pl-9">
            </div>
        </div>
        <div>
            <label for="filter-status" class="mb-1 block text-xs font-medium text-slate-500">Status</label>
            <select id="filter-status" name="status" class="<?= $inputClass ?>">
                <option value="">Semua status</option>
                <?php foreach (DealModel::STATUSES as $value => $label): ?>
                    <option value="<?= $value ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label for="filter-stage" class="mb-1 block text-xs font-medium text-slate-500">Tahap</label>
            <select id="filter-stage" name="stage" class="<?= $inputClass ?>">
                <option value="">Semua tahap</option>
                <?php foreach ($stages as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) ($filters['stage'] ?? '') === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
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
                <label for="filter-close-from" class="mb-1 block text-xs font-medium text-slate-500">Closing dari</label>
                <input id="filter-close-from" type="date" name="close_from" value="<?= esc($filters['close_from'] ?? '', 'attr') ?>" class="<?= $inputClass ?>">
            </div>
            <div>
                <label for="filter-close-to" class="mb-1 block text-xs font-medium text-slate-500">Sampai</label>
                <input id="filter-close-to" type="date" name="close_to" value="<?= esc($filters['close_to'] ?? '', 'attr') ?>" class="<?= $inputClass ?>">
            </div>
        </div>
        <div>
            <label for="filter-min" class="mb-1 block text-xs font-medium text-slate-500">Nilai minimum (Rp)</label>
            <input id="filter-min" type="text" inputmode="numeric" name="min_amount" value="<?= esc($filters['min_amount'] ?? '', 'attr') ?>" placeholder="mis. 100.000.000" class="<?= $inputClass ?>">
        </div>
    </div>
    <input type="hidden" name="sort" value="<?= esc($filters['sort'] ?? '', 'attr') ?>">
    <input type="hidden" name="dir" value="<?= esc($filters['dir'] ?? '', 'attr') ?>">
    <div class="mt-3 flex items-center justify-end gap-2">
        <?php if ($hasFilter): ?>
            <?= component('button', ['label' => 'Reset', 'url' => site_url('deals'), 'variant' => 'ghost']) ?>
        <?php endif ?>
        <?= component('button', ['label' => 'Terapkan filter', 'icon' => 'funnel', 'variant' => 'secondary']) ?>
    </div>
</form>

<?php ob_start() ?>
<?php foreach ($deals as $deal): ?>
    <?php $open = ! $deal['is_won'] && ! $deal['is_lost'] ?>
    <tr class="hover:bg-slate-50">
        <?php if ($canReassign): ?>
            <td class="w-10 px-4 py-3"><input type="checkbox" data-row-check value="<?= (int) $deal['id'] ?>" x-model="selected" aria-label="Pilih baris" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600"></td>
        <?php endif ?>
        <td class="px-4 py-3">
            <a href="<?= site_url('deals/' . $deal['id']) ?>" class="font-medium text-slate-900 hover:text-brand-700"><?= esc($deal['title']) ?></a>
            <div class="text-xs text-slate-500"><?= esc($deal['company_name'] ?? '-') ?></div>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-800"><?= rupiah($deal['amount']) ?></td>
        <td class="px-4 py-3">
            <?= component('badge', ['label' => $deal['stage_name'], 'color' => DealModel::stageColor($deal)]) ?>
            <div class="mt-0.5 text-xs text-slate-500"><?= (int) $deal['probability'] ?>%</div>
        </td>
        <td class="whitespace-nowrap px-4 py-3 <?= $open && $deal['expected_close_date'] && $deal['expected_close_date'] < $today ? 'font-medium text-red-600' : 'text-slate-600' ?>">
            <?= $open ? tanggal_id($deal['expected_close_date']) : 'Ditutup ' . tanggal_id($deal['closed_at']) ?>
        </td>
        <?php if (! $user->isSales()): ?>
            <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= esc($deal['owner_name']) ?></td>
        <?php endif ?>
        <td class="whitespace-nowrap px-4 py-3 text-right">
            <a href="<?= site_url('deals/' . $deal['id'] . '/edit') ?>" class="inline-flex rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Ubah"><?= icon('pencil-square', 'h-4 w-4') ?></a>
            <?php if ($dealModel->canDelete($user, $deal)): ?>
                <?= component('delete-button', [
                    'action'  => site_url('deals/' . $deal['id'] . '/delete'),
                    'title'   => 'Hapus deal?',
                    'message' => 'Deal "' . $deal['title'] . '" akan dihapus dari daftar dan pipeline.',
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
    ['label' => 'Deal', 'sort' => 'title'],
    ['label' => 'Nilai', 'sort' => 'amount', 'class' => 'text-right'],
    ['label' => 'Tahap', 'sort' => 'stage'],
    ['label' => 'Perkiraan closing', 'sort' => 'close_date'],
];

if (! $user->isSales()) {
    $columns[] = ['label' => 'Owner', 'sort' => 'owner'];
}

$columns[] = ['label' => '', 'class' => 'text-right'];
?>
<div x-data="{ selected: [] }">
<?php if ($canReassign): ?>
    <?= component('bulk-reassign', ['path' => 'deals', 'owners' => $owners]) ?>
<?php endif ?>
<?= component('table', [
    'columns' => $columns,
    'rows'    => $rows,
    'empty'   => $hasFilter
        ? component('empty-state', ['icon' => 'magnifying-glass', 'title' => 'Tidak ada deal yang cocok', 'message' => 'Coba ubah kata kunci atau filter pencarian.'])
        : component('empty-state', ['icon' => 'banknotes', 'title' => 'Belum ada deal', 'message' => 'Tambahkan deal atau konversi lead yang sudah qualified.', 'actionUrl' => site_url('deals/new'), 'actionLabel' => 'Tambah Deal']),
    'footer'  => $pager->links('default', 'tailwind'),
]) ?>
</div>
<?= $this->endSection() ?>
