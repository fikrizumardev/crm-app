<?php

use App\Entities\User;

/**
 * @var User $user
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Ganti Password',
    'subtitle' => $user->must_change_password
        ? 'Akun Anda memakai password sementara. Buat password baru untuk melanjutkan.'
        : 'Gunakan password yang kuat dan tidak dipakai di aplikasi lain.',
]) ?>

<form action="<?= site_url('profile/password') ?>" method="post" class="max-w-xl rounded-lg border border-slate-200 bg-white shadow-sm">
    <?= csrf_field() ?>
    <div class="space-y-5 p-5">
        <?= component('form-field', [
            'name'     => 'current_password',
            'label'    => 'Password saat ini',
            'type'     => 'password',
            'required' => true,
            'attrs'    => ['autocomplete' => 'current-password'],
        ]) ?>
        <?= component('form-field', [
            'name'     => 'new_password',
            'label'    => 'Password baru',
            'type'     => 'password',
            'required' => true,
            'help'     => 'Minimal 8 karakter, kombinasi huruf dan angka.',
            'attrs'    => ['autocomplete' => 'new-password', 'minlength' => '8'],
        ]) ?>
        <?= component('form-field', [
            'name'     => 'confirm_password',
            'label'    => 'Ulangi password baru',
            'type'     => 'password',
            'required' => true,
            'attrs'    => ['autocomplete' => 'new-password'],
        ]) ?>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
        <?php if (! $user->must_change_password): ?>
            <a href="<?= site_url('profile') ?>" class="rounded-md bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50">Batal</a>
        <?php endif ?>
        <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">Simpan password</button>
    </div>
</form>
<?= $this->endSection() ?>
