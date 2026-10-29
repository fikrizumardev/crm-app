<?php

use App\Entities\User;
use App\Models\CompanyModel;
use CodeIgniter\Pager\Pager;

/**
 * @var list<array<string, mixed>> $companies
 * @var Pager                      $pager
 * @var array<string, mixed>       $filters
 * @var array<int, string>         $industries
 * @var array<string, string>      $provinces
 * @var array<int, string>         $owners
 * @var bool                       $canExport
 * @var User                       $user
 */
$companyModel = model(CompanyModel::class);
$canReassign = ! $user->isSales() && $owners !== [];
$hasFilter    = array_filter(array_diff_key($filters, ['sort' => 1, 'dir' => 1]), static fn ($v): bool => $v !== null && $v !== '') !== [];
$inputClass   = 'block w-full rounded-md border-0 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';

$actions = $canExport
    ? component('button', ['label' => 'Ekspor CSV', 'url' => query_url('companies/export', ['page' => null]), 'icon' => 'arrow-down-tray', 'variant' => 'secondary'])
    : '';
$actions .= component('button', ['label' => 'Tambah Perusahaan', 'url' => site_url('companies/new'), 'icon' => 'plus']);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Perusahaan',
    'subtitle' => 'Akun pelanggan dan calon pelanggan beserta kontak di dalamnya.',
    'actions'  => $actions,
]) ?>

<form method="get" action="<?= site_url('companies') ?>" class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="lg:col-span-2">
            <label for="filter-q" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><?= icon('magnifying-glass', 'h-4 w-4') ?></span>
                <input id="filter-q" type="search" name="q" value="<?= esc($filters['q'] ?? '', 'attr') ?>" placeholder="Nama, kota, telepon" class="<?= $inputClass ?> pl-9">
            </div>
        </div>
        <div>
            <label for="filter-industry" class="mb-1 block text-xs font-medium text-slate-500">Industri</label>
            <select id="filter-industry" name="industry" class="<?= $inputClass ?>">
                <option value="">Semua industri</option>
                <?php foreach ($industries as $id => $name): ?>
                    <option value="<?= $id ?>" <?= (string) ($filters['industry'] ?? '') === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <div>
            <label for="filter-province" class="mb-1 block text-xs font-medium text-slate-500">Provinsi</label>
            <select id="filter-province" name="province" class="<?= $inputClass ?>">
                <option value="">Semua provinsi</option>
                <?php foreach ($provinces as $province): ?>
                    <option value="<?= esc($province, 'attr') ?>" <?= ($filters['province'] ?? '') === $province ? 'selected' : '' ?>><?= esc($province) ?></option>
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
    </div>
    <input type="hidden" name="sort" value="<?= esc($filters['sort'] ?? '', 'attr') ?>">
    <input type="hidden" name="dir" value="<?= esc($filters['dir'] ?? '', 'attr') ?>">
    <div class="mt-3 flex items-center justify-end gap-2">
        <?php if ($hasFilter): ?>
            <?= component('button', ['label' => 'Reset', 'url' => site_url('companies'), 'variant' => 'ghost']) ?>
        <?php endif ?>
        <?= component('button', ['label' => 'Terapkan filter', 'icon' => 'funnel', 'variant' => 'secondary']) ?>
    </div>
</form>

<?php ob_start() ?>
<?php foreach ($companies as $company): ?>
    <tr class="hover:bg-slate-50">
        <?php if ($canReassign): ?>
            <td class="w-10 px-4 py-3"><input type="checkbox" data-row-check value="<?= (int) $company['id'] ?>" x-model="selected" aria-label="Pilih baris" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600"></td>
        <?php endif ?>
        <td class="px-4 py-3">
            <a href="<?= site_url('companies/' . $company['id']) ?>" class="font-medium text-slate-900 hover:text-brand-700"><?= esc($company['name']) ?></a>
            <div class="text-xs text-slate-500"><?= esc($company['phone'] ? format_hp($company['phone']) : '') ?></div>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= esc($company['industry_name'] ?? '-') ?></td>
        <td class="px-4 py-3">
            <div class="text-slate-700"><?= esc($company['city'] ?? '-') ?></div>
            <div class="text-xs text-slate-500"><?= esc($company['province'] ?? '') ?></div>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= angka($company['contact_count']) ?></td>
        <?php if (! $user->isSales()): ?>
            <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= esc($company['owner_name']) ?></td>
        <?php endif ?>
        <td class="whitespace-nowrap px-4 py-3 text-right">
            <a href="<?= site_url('companies/' . $company['id'] . '/edit') ?>" class="inline-flex rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Ubah"><?= icon('pencil-square', 'h-4 w-4') ?></a>
            <?php if ($companyModel->canDelete($user, $company)): ?>
                <?= component('delete-button', [
                    'action'  => site_url('companies/' . $company['id'] . '/delete'),
                    'title'   => 'Hapus perusahaan?',
                    'message' => 'Perusahaan "' . $company['name'] . '" akan dihapus dari daftar. Kontak di dalamnya tetap tersimpan.',
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
    ['label' => 'Nama perusahaan', 'sort' => 'name'],
    ['label' => 'Industri', 'sort' => 'industry'],
    ['label' => 'Lokasi', 'sort' => 'city'],
    ['label' => 'Kontak', 'sort' => 'contacts'],
];

if (! $user->isSales()) {
    $columns[] = ['label' => 'Owner', 'sort' => 'owner'];
}

$columns[] = ['label' => '', 'class' => 'text-right'];
?>
<div x-data="{ selected: [] }">
<?php if ($canReassign): ?>
    <?= component('bulk-reassign', ['path' => 'companies', 'owners' => $owners]) ?>
<?php endif ?>
<?= component('table', [
    'columns' => $columns,
    'rows'    => $rows,
    'empty'   => $hasFilter
        ? component('empty-state', ['icon' => 'magnifying-glass', 'title' => 'Tidak ada perusahaan yang cocok', 'message' => 'Coba ubah kata kunci atau filter pencarian.'])
        : component('empty-state', ['icon' => 'building-office-2', 'title' => 'Belum ada perusahaan', 'message' => 'Tambahkan perusahaan atau konversi lead yang sudah qualified.', 'actionUrl' => site_url('companies/new'), 'actionLabel' => 'Tambah Perusahaan']),
    'footer'  => $pager->links('default', 'tailwind'),
]) ?>
</div>
<?= $this->endSection() ?>
