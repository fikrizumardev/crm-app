<?php

use CodeIgniter\Pager\Pager;

/**
 * @var list<array<string, mixed>> $products
 * @var Pager                      $pager
 * @var array<string, mixed>       $filters
 * @var bool                       $canManage
 */
$hasFilter  = array_filter(array_diff_key($filters, ['sort' => 1, 'dir' => 1]), static fn ($v): bool => $v !== null && $v !== '') !== [];
$inputClass = 'block w-full rounded-md border-0 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Produk',
    'subtitle' => 'Katalog produk dan layanan untuk penawaran harga.',
    'actions'  => $canManage ? component('button', ['label' => 'Tambah Produk', 'url' => site_url('products/new'), 'icon' => 'plus']) : '',
]) ?>

<form method="get" action="<?= site_url('products') ?>" class="mb-4 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
    <div class="sm:col-span-2">
        <label for="filter-q" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
        <div class="relative">
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><?= icon('magnifying-glass', 'h-4 w-4') ?></span>
            <input id="filter-q" type="search" name="q" value="<?= esc($filters['q'] ?? '', 'attr') ?>" placeholder="SKU atau nama produk" class="<?= $inputClass ?> pl-9">
        </div>
    </div>
    <div>
        <label for="filter-status" class="mb-1 block text-xs font-medium text-slate-500">Status</label>
        <select id="filter-status" name="status" class="<?= $inputClass ?>">
            <option value="">Semua</option>
            <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
            <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
        </select>
    </div>
    <div class="flex items-end gap-2">
        <?= component('button', ['label' => 'Cari', 'icon' => 'magnifying-glass', 'variant' => 'secondary']) ?>
        <?php if ($hasFilter): ?>
            <?= component('button', ['label' => 'Reset', 'url' => site_url('products'), 'variant' => 'ghost']) ?>
        <?php endif ?>
    </div>
</form>

<?php ob_start() ?>
<?php foreach ($products as $product): ?>
    <tr class="<?= $product['is_active'] ? 'hover:bg-slate-50' : 'bg-slate-50 text-slate-400' ?>">
        <td class="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600"><?= esc($product['sku']) ?></td>
        <td class="px-4 py-3 font-medium <?= $product['is_active'] ? 'text-slate-900' : 'text-slate-500' ?>"><?= esc($product['name']) ?></td>
        <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= esc($product['unit']) ?></td>
        <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-800"><?= rupiah($product['price']) ?></td>
        <td class="px-4 py-3"><?= component('badge', $product['is_active'] ? ['label' => 'Aktif', 'color' => 'green'] : ['label' => 'Nonaktif', 'color' => 'gray']) ?></td>
        <?php if ($canManage): ?>
            <td class="whitespace-nowrap px-4 py-3 text-right">
                <a href="<?= site_url('products/' . $product['id'] . '/edit') ?>" class="inline-flex rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Ubah"><?= icon('pencil-square', 'h-4 w-4') ?></a>
                <?= component('delete-button', [
                    'action'  => site_url('products/' . $product['id'] . '/delete'),
                    'title'   => 'Hapus produk?',
                    'message' => 'Produk "' . $product['name'] . '" tidak akan muncul lagi di katalog. Penawaran lama tidak berubah.',
                    'compact' => true,
                ]) ?>
            </td>
        <?php endif ?>
    </tr>
<?php endforeach ?>
<?php $rows = ob_get_clean() ?>

<?php
$columns = [
    ['label' => 'SKU', 'sort' => 'sku'],
    ['label' => 'Nama produk', 'sort' => 'name'],
    ['label' => 'Satuan', 'sort' => 'unit'],
    ['label' => 'Harga', 'sort' => 'price', 'class' => 'text-right'],
    ['label' => 'Status'],
];

if ($canManage) {
    $columns[] = ['label' => '', 'class' => 'text-right'];
}
?>
<?= component('table', [
    'columns' => $columns,
    'rows'    => $rows,
    'empty'   => $hasFilter
        ? component('empty-state', ['icon' => 'magnifying-glass', 'title' => 'Produk tidak ditemukan', 'message' => 'Coba kata kunci lain.'])
        : component('empty-state', ['icon' => 'cube', 'title' => 'Belum ada produk', 'message' => 'Tambahkan produk dan layanan untuk dipakai di penawaran.', 'actionUrl' => $canManage ? site_url('products/new') : null, 'actionLabel' => 'Tambah Produk']),
    'footer'  => $pager->links('default', 'tailwind'),
]) ?>
<?= $this->endSection() ?>
