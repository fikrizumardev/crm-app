<?php

use App\Models\ContactModel;

/**
 * @var array<string, mixed> $contact
 * @var array<int, string>   $owners
 * @var string               $title
 */
$isEdit = isset($contact['id']);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $title,
    'breadcrumbs' => array_values(array_filter([
        ['label' => 'Kontak', 'url' => site_url('contacts')],
        $isEdit ? ['label' => ContactModel::fullName($contact), 'url' => site_url('contacts/' . $contact['id'])] : null,
        ['label' => $isEdit ? 'Ubah' : 'Tambah'],
    ])),
]) ?>

<form action="<?= site_url($isEdit ? 'contacts/' . $contact['id'] : 'contacts') ?>" method="post" class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
        <?= component('form-field', ['name' => 'first_name', 'label' => 'Nama depan', 'value' => $contact['first_name'] ?? '', 'required' => true, 'attrs' => ['maxlength' => '100']]) ?>
        <?= component('form-field', ['name' => 'last_name', 'label' => 'Nama belakang', 'value' => $contact['last_name'] ?? '', 'attrs' => ['maxlength' => '100']]) ?>
        <div class="sm:col-span-2 sm:max-w-[calc(50%-0.625rem)]">
            <?= component('company-select', [
                'name'          => 'company_id',
                'label'         => 'Perusahaan',
                'selectedId'    => $contact['company_id'] ?? '',
                'selectedLabel' => $contact['company_name'] ?? '',
            ]) ?>
        </div>
        <?= component('form-field', ['name' => 'job_title', 'label' => 'Jabatan', 'value' => $contact['job_title'] ?? '', 'placeholder' => 'mis. Purchasing Manager', 'attrs' => ['maxlength' => '100']]) ?>
        <?= component('form-field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => $contact['email'] ?? '']) ?>
        <?= component('form-field', ['name' => 'phone', 'label' => 'No. HP', 'type' => 'tel', 'value' => $contact['phone'] ?? '', 'placeholder' => '0812xxxxxxxx']) ?>
        <?= component('form-field', ['name' => 'whatsapp', 'label' => 'WhatsApp', 'type' => 'tel', 'value' => $contact['whatsapp'] ?? '', 'help' => 'Kosongkan bila sama dengan No. HP.']) ?>
        <?= component('form-field', ['name' => 'birth_date', 'label' => 'Tanggal lahir', 'type' => 'date', 'value' => $contact['birth_date'] ?? '', 'help' => 'Opsional.']) ?>
        <?php if ($owners !== []): ?>
            <?= component('form-field', ['name' => 'owner_id', 'label' => 'Owner', 'type' => 'select', 'value' => $contact['owner_id'] ?? '', 'options' => $owners, 'required' => true]) ?>
        <?php endif ?>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
        <?= component('button', ['label' => 'Batal', 'url' => site_url($isEdit ? 'contacts/' . $contact['id'] : 'contacts'), 'variant' => 'secondary']) ?>
        <?= component('button', ['label' => $isEdit ? 'Simpan perubahan' : 'Simpan kontak']) ?>
    </div>
</form>
<?= $this->endSection() ?>
