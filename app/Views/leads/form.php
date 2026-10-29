<?php

use App\Models\LeadModel;

/**
 * @var array<string, mixed> $lead
 * @var array<int, string>   $sources
 * @var array<int, string>   $owners
 * @var list<array<string, mixed>> $duplicates
 * @var string               $title
 */
$isEdit      = isset($lead['id']);
$isConverted = ($lead['status'] ?? null) === LeadModel::STATUS_CONVERTED;
$statuses    = LeadModel::STATUSES;

if (! $isConverted) {
    unset($statuses[LeadModel::STATUS_CONVERTED]);
}
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $title,
    'breadcrumbs' => array_values(array_filter([
        ['label' => 'Lead', 'url' => site_url('leads')],
        $isEdit ? ['label' => $lead['name'], 'url' => site_url('leads/' . $lead['id'])] : null,
        ['label' => $isEdit ? 'Ubah' : 'Tambah'],
    ])),
]) ?>

<form action="<?= site_url($isEdit ? 'leads/' . $lead['id'] : 'leads') ?>" method="post" class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <?= csrf_field() ?>
    <div class="p-5">
        <?= component('duplicate-warning', ['duplicates' => $duplicates]) ?>

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <?= component('form-field', ['name' => 'name', 'label' => 'Nama', 'value' => $lead['name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => '150']]) ?>
            <?= component('form-field', ['name' => 'company_name', 'label' => 'Perusahaan', 'value' => $lead['company_name'] ?? '', 'placeholder' => 'mis. PT Maju Bersama', 'attrs' => ['maxlength' => '150']]) ?>
            <?= component('form-field', ['name' => 'job_title', 'label' => 'Jabatan', 'value' => $lead['job_title'] ?? '', 'attrs' => ['maxlength' => '100']]) ?>
            <?= component('form-field', ['name' => 'phone', 'label' => 'No. HP', 'type' => 'tel', 'value' => $lead['phone'] ?? '', 'placeholder' => '0812xxxxxxxx']) ?>
            <?= component('form-field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $lead['email'] ?? '', 'help' => 'Opsional.']) ?>
            <?= component('form-field', ['name' => 'source_id', 'label' => 'Sumber', 'type' => 'select', 'value' => $lead['source_id'] ?? '', 'options' => $sources, 'empty' => '— Pilih sumber —']) ?>
            <?= component('form-field', [
                'name'    => 'status',
                'label'   => 'Status',
                'type'    => 'select',
                'value'   => $lead['status'] ?? LeadModel::STATUS_NEW,
                'options' => $statuses,
                'attrs'   => $isConverted ? ['disabled' => 'disabled'] : [],
                'help'    => $isConverted ? 'Lead yang sudah dikonversi tidak bisa diubah statusnya.' : 'Status Dikonversi diset otomatis lewat tombol Konversi.',
            ]) ?>
            <?php if ($owners !== []): ?>
                <?= component('form-field', ['name' => 'owner_id', 'label' => 'Owner', 'type' => 'select', 'value' => $lead['owner_id'] ?? '', 'options' => $owners, 'required' => true]) ?>
            <?php endif ?>
            <?= component('form-field', ['name' => 'notes', 'label' => 'Catatan', 'type' => 'textarea', 'value' => $lead['notes'] ?? '', 'class' => 'sm:col-span-2', 'attrs' => ['maxlength' => '5000']]) ?>
        </div>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
        <?= component('button', ['label' => 'Batal', 'url' => site_url($isEdit ? 'leads/' . $lead['id'] : 'leads'), 'variant' => 'secondary']) ?>
        <?= component('button', ['label' => $isEdit ? 'Simpan perubahan' : 'Simpan lead']) ?>
    </div>
</form>
<?= $this->endSection() ?>
