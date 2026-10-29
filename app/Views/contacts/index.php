<?php

use App\Entities\User;
use App\Models\ContactModel;
use CodeIgniter\Pager\Pager;

/**
 * @var list<array<string, mixed>> $contacts
 * @var Pager                      $pager
 * @var array<string, mixed>       $filters
 * @var array<string, mixed>|null  $company
 * @var array<int, string>         $owners
 * @var bool                       $canExport
 * @var User                       $user
 */
$contactModel = model(ContactModel::class);
$canReassign = ! $user->isSales() && $owners !== [];
$hasFilter    = array_filter(array_diff_key($filters, ['sort' => 1, 'dir' => 1]), static fn ($v): bool => $v !== null && $v !== '') !== [];
$inputClass   = 'block w-full rounded-md border-0 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';

$actions = $canExport
    ? component('button', ['label' => 'Ekspor CSV', 'url' => query_url('contacts/export', ['page' => null]), 'icon' => 'arrow-down-tray', 'variant' => 'secondary'])
    : '';
$actions .= component('button', ['label' => 'Tambah Kontak', 'url' => site_url('contacts/new'), 'icon' => 'plus']);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Kontak',
    'subtitle' => $company ? 'Kontak di ' . $company['name'] . '.' : 'Orang-orang yang Anda hubungi di perusahaan pelanggan.',
    'actions'  => $actions,
]) ?>

<form method="get" action="<?= site_url('contacts') ?>" class="mb-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="lg:col-span-2">
            <label for="filter-q" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
            <div class="relative">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><?= icon('magnifying-glass', 'h-4 w-4') ?></span>
                <input id="filter-q" type="search" name="q" value="<?= esc($filters['q'] ?? '', 'attr') ?>" placeholder="Nama, perusahaan, jabatan, no. HP, email" class="<?= $inputClass ?> pl-9">
            </div>
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
        <?php if ($company): ?>
            <div class="flex items-end">
                <span class="inline-flex items-center gap-2 rounded-md bg-brand-50 px-3 py-2 text-sm text-brand-800">
                    <?= icon('building-office-2', 'h-4 w-4') ?> <?= esc($company['name']) ?>
                    <a href="<?= esc(query_url('contacts', ['company' => null, 'page' => null]), 'attr') ?>" class="text-brand-500 hover:text-brand-800" title="Hapus filter perusahaan"><?= icon('x-mark', 'h-4 w-4') ?></a>
                </span>
                <input type="hidden" name="company" value="<?= (int) $company['id'] ?>">
            </div>
        <?php endif ?>
    </div>
    <input type="hidden" name="sort" value="<?= esc($filters['sort'] ?? '', 'attr') ?>">
    <input type="hidden" name="dir" value="<?= esc($filters['dir'] ?? '', 'attr') ?>">
    <div class="mt-3 flex items-center justify-end gap-2">
        <?php if ($hasFilter): ?>
            <?= component('button', ['label' => 'Reset', 'url' => site_url('contacts'), 'variant' => 'ghost']) ?>
        <?php endif ?>
        <?= component('button', ['label' => 'Terapkan filter', 'icon' => 'funnel', 'variant' => 'secondary']) ?>
    </div>
</form>

<?php ob_start() ?>
<?php foreach ($contacts as $contact): ?>
    <?php $waLink = wa_link($contact['whatsapp'] ?: $contact['phone']) ?>
    <tr class="hover:bg-slate-50">
        <?php if ($canReassign): ?>
            <td class="w-10 px-4 py-3"><input type="checkbox" data-row-check value="<?= (int) $contact['id'] ?>" x-model="selected" aria-label="Pilih baris" class="rounded border-slate-300 text-brand-600 focus:ring-brand-600"></td>
        <?php endif ?>
        <td class="px-4 py-3">
            <a href="<?= site_url('contacts/' . $contact['id']) ?>" class="font-medium text-slate-900 hover:text-brand-700"><?= esc(ContactModel::fullName($contact)) ?></a>
            <div class="text-xs text-slate-500"><?= esc($contact['email'] ?? '') ?></div>
        </td>
        <td class="px-4 py-3">
            <?php if ($contact['company_name']): ?>
                <a href="<?= site_url('companies/' . $contact['company_id']) ?>" class="text-slate-700 hover:text-brand-700"><?= esc($contact['company_name']) ?></a>
            <?php else: ?>
                <span class="text-slate-400">-</span>
            <?php endif ?>
            <div class="text-xs text-slate-500"><?= esc($contact['job_title'] ?? '') ?></div>
        </td>
        <td class="whitespace-nowrap px-4 py-3">
            <div class="flex items-center gap-2 text-slate-600">
                <?= esc(format_hp($contact['phone'])) ?>
                <?php if ($waLink): ?>
                    <a href="<?= esc($waLink, 'attr') ?>" target="_blank" rel="noopener" class="rounded p-1 text-green-600 hover:bg-green-50" title="Chat WhatsApp"><?= icon('chat-bubble-left-right', 'h-4 w-4') ?></a>
                <?php endif ?>
                <?php if ($contact['phone']): ?>
                    <a href="tel:<?= esc($contact['phone'], 'attr') ?>" class="rounded p-1 text-slate-500 hover:bg-slate-100" title="Telepon"><?= icon('phone', 'h-4 w-4') ?></a>
                <?php endif ?>
            </div>
        </td>
        <?php if (! $user->isSales()): ?>
            <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= esc($contact['owner_name']) ?></td>
        <?php endif ?>
        <td class="whitespace-nowrap px-4 py-3 text-right">
            <a href="<?= site_url('contacts/' . $contact['id'] . '/edit') ?>" class="inline-flex rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Ubah"><?= icon('pencil-square', 'h-4 w-4') ?></a>
            <?php if ($contactModel->canDelete($user, $contact)): ?>
                <?= component('delete-button', [
                    'action'  => site_url('contacts/' . $contact['id'] . '/delete'),
                    'title'   => 'Hapus kontak?',
                    'message' => 'Kontak "' . ContactModel::fullName($contact) . '" akan dihapus dari daftar.',
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
    ['label' => 'Perusahaan & jabatan', 'sort' => 'company'],
    ['label' => 'No. HP'],
];

if (! $user->isSales()) {
    $columns[] = ['label' => 'Owner', 'sort' => 'owner'];
}

$columns[] = ['label' => '', 'class' => 'text-right'];
?>
<div x-data="{ selected: [] }">
<?php if ($canReassign): ?>
    <?= component('bulk-reassign', ['path' => 'contacts', 'owners' => $owners]) ?>
<?php endif ?>
<?= component('table', [
    'columns' => $columns,
    'rows'    => $rows,
    'empty'   => $hasFilter
        ? component('empty-state', ['icon' => 'magnifying-glass', 'title' => 'Tidak ada kontak yang cocok', 'message' => 'Coba ubah kata kunci atau filter pencarian.'])
        : component('empty-state', ['icon' => 'users', 'title' => 'Belum ada kontak', 'message' => 'Tambahkan kontak atau konversi lead yang sudah qualified.', 'actionUrl' => site_url('contacts/new'), 'actionLabel' => 'Tambah Kontak']),
    'footer'  => $pager->links('default', 'tailwind'),
]) ?>
</div>
<?= $this->endSection() ?>
