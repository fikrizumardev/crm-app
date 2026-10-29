<?php

use App\Entities\User;
use App\Models\QuotationModel;
use CodeIgniter\Pager\Pager;

/**
 * @var list<array<string, mixed>> $quotations
 * @var Pager                      $pager
 * @var array<string, mixed>       $filters
 * @var User                       $user
 */
$hasFilter  = array_filter(array_diff_key($filters, ['sort' => 1, 'dir' => 1]), static fn ($v): bool => $v !== null && $v !== '') !== [];
$inputClass = 'block w-full rounded-md border-0 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
$today      = date('Y-m-d');
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Penawaran',
    'subtitle' => 'Penawaran harga dibuat dari halaman detail deal.',
    'actions'  => component('button', ['label' => 'Pilih deal', 'url' => site_url('deals?status=open'), 'icon' => 'banknotes', 'variant' => 'secondary']),
]) ?>

<form method="get" action="<?= site_url('quotations') ?>" class="mb-4 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
    <div class="sm:col-span-2">
        <label for="filter-q" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><?= icon('magnifying-glass', 'h-4 w-4') ?></span>
            <input id="filter-q" type="search" name="q" value="<?= esc($filters['q'] ?? '', 'attr') ?>" placeholder="Nomor, deal, atau perusahaan" class="<?= $inputClass ?> pl-9">
        </div>
    </div>
    <div>
        <label for="filter-status" class="mb-1 block text-xs font-medium text-slate-500">Status</label>
        <select id="filter-status" name="status" class="<?= $inputClass ?>">
            <option value="">Semua status</option>
            <?php foreach (QuotationModel::STATUSES as $value => $meta): ?>
                <option value="<?= $value ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= esc($meta['label']) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="flex items-end gap-2">
        <?= component('button', ['label' => 'Cari', 'icon' => 'magnifying-glass', 'variant' => 'secondary']) ?>
        <?php if ($hasFilter): ?>
            <?= component('button', ['label' => 'Reset', 'url' => site_url('quotations'), 'variant' => 'ghost']) ?>
        <?php endif ?>
    </div>
</form>

<?php ob_start() ?>
<?php foreach ($quotations as $quotation): ?>
    <?php
    $status  = QuotationModel::STATUSES[$quotation['status']];
    $expired = in_array($quotation['status'], [QuotationModel::STATUS_DRAFT, QuotationModel::STATUS_SENT], true) && $quotation['valid_until'] < $today;
    ?>
    <tr class="hover:bg-slate-50">
        <td class="whitespace-nowrap px-4 py-3">
            <a href="<?= site_url('quotations/' . $quotation['id']) ?>" class="font-mono text-sm font-medium text-slate-900 hover:text-brand-700"><?= esc($quotation['number']) ?></a>
        </td>
        <td class="px-4 py-3">
            <a href="<?= site_url('deals/' . $quotation['deal_id']) ?>" class="text-slate-800 hover:text-brand-700"><?= esc($quotation['deal_title']) ?></a>
            <div class="text-xs text-slate-500"><?= esc($quotation['company_name'] ?? '-') ?></div>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= tanggal_id($quotation['issue_date']) ?></td>
        <td class="whitespace-nowrap px-4 py-3 <?= $expired ? 'font-medium text-red-600' : 'text-slate-600' ?>"><?= tanggal_id($quotation['valid_until']) ?><?= $expired ? ' (kedaluwarsa)' : '' ?></td>
        <td class="whitespace-nowrap px-4 py-3 text-right font-semibold text-slate-900"><?= rupiah($quotation['grand_total']) ?></td>
        <td class="px-4 py-3"><?= component('badge', ['label' => $status['label'], 'color' => $status['color']]) ?></td>
        <?php if (! $user->isSales()): ?>
            <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= esc($quotation['owner_name']) ?></td>
        <?php endif ?>
        <td class="whitespace-nowrap px-4 py-3 text-right">
            <a href="<?= site_url('quotations/' . $quotation['id'] . '/print') ?>" target="_blank" rel="noopener" class="inline-flex rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Cetak"><?= icon('document-arrow-down', 'h-4 w-4') ?></a>
        </td>
    </tr>
<?php endforeach ?>
<?php $rows = ob_get_clean() ?>

<?php
$columns = [
    ['label' => 'Nomor', 'sort' => 'number'],
    ['label' => 'Deal'],
    ['label' => 'Tanggal', 'sort' => 'issue_date'],
    ['label' => 'Berlaku sampai', 'sort' => 'valid_until'],
    ['label' => 'Total', 'sort' => 'grand_total', 'class' => 'text-right'],
    ['label' => 'Status', 'sort' => 'status'],
];

if (! $user->isSales()) {
    $columns[] = ['label' => 'Owner'];
}

$columns[] = ['label' => '', 'class' => 'text-right'];
?>
<?= component('table', [
    'columns' => $columns,
    'rows'    => $rows,
    'empty'   => $hasFilter
        ? component('empty-state', ['icon' => 'magnifying-glass', 'title' => 'Penawaran tidak ditemukan', 'message' => 'Coba kata kunci atau status lain.'])
        : component('empty-state', ['icon' => 'document-text', 'title' => 'Belum ada penawaran', 'message' => 'Buka detail deal lalu klik "Buat penawaran".', 'actionUrl' => site_url('deals?status=open'), 'actionLabel' => 'Pilih deal']),
    'footer'  => $pager->links('default', 'tailwind'),
]) ?>
<?= $this->endSection() ?>
