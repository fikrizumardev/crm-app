<?php

use App\Entities\User;

/**
 * @var User               $user
 * @var array<int, string> $managers
 * @var User               $me
 * @var string             $title
 */
$isEdit = $user->id !== null;
$isSelf = $isEdit && $user->id === $me->id;
$role   = (string) old('role', $user->role, false);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $title,
    'breadcrumbs' => [['label' => 'Pengguna', 'url' => site_url('admin/users')], ['label' => $isEdit ? $user->name : 'Tambah']],
]) ?>

<form action="<?= site_url($isEdit ? 'admin/users/' . $user->id : 'admin/users') ?>" method="post" class="max-w-3xl rounded-lg border border-slate-200 bg-white shadow-sm"
      x-data="{ role: <?= esc(json_encode($role), 'attr') ?> }">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
        <?= component('form-field', [
            'name'     => 'name',
            'label'    => 'Nama lengkap',
            'value'    => $user->name,
            'required' => true,
            'class'    => 'sm:col-span-2',
            'attrs'    => ['maxlength' => '100'],
        ]) ?>
        <?= component('form-field', [
            'name'        => 'username',
            'label'       => 'Username',
            'value'       => $user->username,
            'required'    => true,
            'placeholder' => 'mis. rina.marlina',
            'help'        => '4–30 karakter: huruf kecil, angka, titik, atau garis bawah. Dipakai untuk login.',
            'attrs'       => ['maxlength' => '30', 'autocapitalize' => 'none', 'pattern' => '[a-z0-9._]{4,30}'],
        ]) ?>
        <?php if (! $isEdit): ?>
            <?= component('form-field', [
                'name'  => 'password',
                'label' => 'Password awal',
                'type'  => 'text',
                'help'  => 'Kosongkan untuk dibuatkan otomatis. Pengguna wajib menggantinya saat login pertama.',
                'attrs' => ['autocomplete' => 'off'],
            ]) ?>
        <?php else: ?>
            <div class="text-sm">
                <p class="font-medium text-slate-700">Password</p>
                <p class="mt-2 text-slate-500">Gunakan menu <span class="font-medium">Reset password</span> di daftar pengguna untuk membuat password baru.</p>
            </div>
        <?php endif ?>

        <div>
            <label for="field-role" class="block text-sm font-medium text-slate-700">Peran <span class="text-red-500">*</span></label>
            <select id="field-role" name="role" x-model="role" <?= $isSelf ? 'disabled' : '' ?>
                    class="mt-1 block w-full rounded-md border-0 px-3 py-2 text-sm shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600 disabled:bg-slate-50">
                <?php foreach (User::ROLES as $value => $label): ?>
                    <option value="<?= $value ?>" <?= $role === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach ?>
            </select>
            <?php if ($isSelf): ?>
                <input type="hidden" name="role" value="<?= esc($user->role, 'attr') ?>">
                <p class="mt-1 text-xs text-slate-500">Anda tidak bisa mengubah peran akun sendiri.</p>
            <?php elseif (isset(form_errors()['role'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= esc(form_errors()['role']) ?></p>
            <?php endif ?>
        </div>
        <div x-show="role === 'sales'" x-cloak>
            <?= component('form-field', [
                'name'    => 'manager_id',
                'label'   => 'Manager',
                'type'    => 'select',
                'value'   => $user->manager_id,
                'options' => $managers,
                'empty'   => '— Pilih manager —',
                'help'    => 'Manager dapat melihat dan mengelola data sales di timnya.',
            ]) ?>
        </div>

        <?= component('form-field', ['name' => 'email', 'label' => 'Email kontak', 'type' => 'email', 'value' => $user->email, 'help' => 'Opsional, tidak dipakai untuk login.']) ?>
        <?= component('form-field', ['name' => 'phone', 'label' => 'No. HP', 'type' => 'tel', 'value' => $user->phone]) ?>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
        <?= component('button', ['label' => 'Batal', 'url' => site_url('admin/users'), 'variant' => 'secondary']) ?>
        <?= component('button', ['label' => $isEdit ? 'Simpan perubahan' : 'Buat pengguna']) ?>
    </div>
</form>
<?= $this->endSection() ?>
