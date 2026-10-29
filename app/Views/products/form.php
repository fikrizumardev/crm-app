<?php
/**
 * @var array<string, mixed> $product
 * @var string               $title
 */
$isEdit = isset($product['id']);
$active = (string) old('is_active', (string) ($product['is_active'] ?? 1), false) === '1';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $title,
    'breadcrumbs' => [['label' => 'Produk', 'url' => site_url('products')], ['label' => $isEdit ? $product['name'] : 'Tambah']],
]) ?>

<form action="<?= site_url($isEdit ? 'products/' . $product['id'] : 'products') ?>" method="post" class="max-w-3xl rounded-lg border border-slate-200 bg-white shadow-sm">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
        <?= component('form-field', ['name' => 'sku', 'label' => 'SKU', 'value' => $product['sku'] ?? '', 'required' => true, 'placeholder' => 'mis. LIC-ERP-USR', 'help' => 'Kode unik produk; otomatis huruf besar.', 'attrs' => ['maxlength' => '50', 'autocapitalize' => 'characters']]) ?>
        <?= component('form-field', ['name' => 'unit', 'label' => 'Satuan', 'value' => $product['unit'] ?? '', 'required' => true, 'placeholder' => 'unit, user, paket, tahun…', 'attrs' => ['maxlength' => '30']]) ?>
        <?= component('form-field', ['name' => 'name', 'label' => 'Nama produk', 'value' => $product['name'] ?? '', 'required' => true, 'class' => 'sm:col-span-2', 'attrs' => ['maxlength' => '200']]) ?>
        <?= component('form-field', [
            'name'     => 'price',
            'label'    => 'Harga satuan (Rp)',
            'value'    => isset($product['price']) ? number_format((float) $product['price'], 0, ',', '.') : '',
            'required' => true,
            'help'     => 'Harga sebelum PPN.',
            'attrs'    => ['inputmode' => 'numeric'],
        ]) ?>
        <div class="flex items-center pt-6">
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="is_active" value="1" <?= $active ? 'checked' : '' ?> class="rounded border-slate-300 text-brand-600 focus:ring-brand-600">
                Aktif (bisa dipilih di penawaran)
            </label>
        </div>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
        <?= component('button', ['label' => 'Batal', 'url' => site_url('products'), 'variant' => 'secondary']) ?>
        <?= component('button', ['label' => $isEdit ? 'Simpan perubahan' : 'Simpan produk']) ?>
    </div>
</form>
<?= $this->endSection() ?>
