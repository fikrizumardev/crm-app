<?php
/**
 * @var array<string, mixed>  $company
 * @var array<int, string>    $industries
 * @var array<string, string> $provinces
 * @var array<int, string>    $owners
 * @var string                $title
 */
$isEdit = isset($company['id']);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $title,
    'breadcrumbs' => array_values(array_filter([
        ['label' => 'Perusahaan', 'url' => site_url('companies')],
        $isEdit ? ['label' => $company['name'], 'url' => site_url('companies/' . $company['id'])] : null,
        ['label' => $isEdit ? 'Ubah' : 'Tambah'],
    ])),
]) ?>

<form action="<?= site_url($isEdit ? 'companies/' . $company['id'] : 'companies') ?>" method="post" class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
        <?= component('form-field', ['name' => 'name', 'label' => 'Nama perusahaan', 'value' => $company['name'] ?? '', 'required' => true, 'class' => 'sm:col-span-2', 'placeholder' => 'mis. PT Sinar Logistik Nusantara', 'attrs' => ['maxlength' => '150']]) ?>
        <?= component('form-field', ['name' => 'industry_id', 'label' => 'Industri', 'type' => 'select', 'value' => $company['industry_id'] ?? '', 'options' => $industries, 'empty' => '— Pilih industri —']) ?>
        <?= component('form-field', ['name' => 'npwp', 'label' => 'NPWP', 'value' => isset($company['npwp']) && $company['npwp'] ? format_npwp($company['npwp']) : '', 'placeholder' => '01.234.567.8-901.234', 'help' => 'Opsional, 15 atau 16 digit.']) ?>
        <?= component('form-field', ['name' => 'phone', 'label' => 'Telepon', 'type' => 'tel', 'value' => $company['phone'] ?? '', 'placeholder' => '021xxxxxxx']) ?>
        <?= component('form-field', ['name' => 'website', 'label' => 'Website', 'value' => $company['website'] ?? '', 'placeholder' => 'www.contoh.co.id']) ?>
        <?= component('form-field', ['name' => 'address', 'label' => 'Alamat', 'type' => 'textarea', 'rows' => 2, 'value' => $company['address'] ?? '', 'class' => 'sm:col-span-2']) ?>
        <?= component('form-field', ['name' => 'city', 'label' => 'Kota', 'value' => $company['city'] ?? '', 'attrs' => ['maxlength' => '100']]) ?>
        <?= component('form-field', ['name' => 'province', 'label' => 'Provinsi', 'type' => 'select', 'value' => $company['province'] ?? '', 'options' => $provinces, 'empty' => '— Pilih provinsi —']) ?>
        <?= component('form-field', ['name' => 'employee_count', 'label' => 'Jumlah karyawan', 'type' => 'number', 'value' => $company['employee_count'] ?? '', 'attrs' => ['min' => '0']]) ?>
        <?php if ($owners !== []): ?>
            <?= component('form-field', ['name' => 'owner_id', 'label' => 'Owner', 'type' => 'select', 'value' => $company['owner_id'] ?? '', 'options' => $owners, 'required' => true]) ?>
        <?php endif ?>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
        <?= component('button', ['label' => 'Batal', 'url' => site_url($isEdit ? 'companies/' . $company['id'] : 'companies'), 'variant' => 'secondary']) ?>
        <?= component('button', ['label' => $isEdit ? 'Simpan perubahan' : 'Simpan perusahaan']) ?>
    </div>
</form>
<?= $this->endSection() ?>
