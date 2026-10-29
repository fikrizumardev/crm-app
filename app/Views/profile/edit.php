<?php

use App\Entities\User;

/**
 * @var User $user
 */
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Profil Saya',
    'subtitle' => 'Perbarui nama dan informasi kontak Anda.',
]) ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <form action="<?= site_url('profile') ?>" method="post" class="rounded-lg border border-slate-200 bg-white shadow-sm lg:col-span-2">
        <?= csrf_field() ?>
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
            <?= component('form-field', [
                'name'     => 'username',
                'label'    => 'Username',
                'value'    => $user->username,
                'readonly' => true,
                'help'     => 'Username hanya bisa diubah oleh admin.',
                'class'    => 'sm:col-span-2',
                'attrs'    => ['disabled' => 'disabled'],
            ]) ?>
            <?= component('form-field', [
                'name'     => 'name',
                'label'    => 'Nama lengkap',
                'value'    => $user->name,
                'required' => true,
                'class'    => 'sm:col-span-2',
                'attrs'    => ['maxlength' => '100'],
            ]) ?>
            <?= component('form-field', [
                'name'        => 'phone',
                'label'       => 'No. HP',
                'type'        => 'tel',
                'value'       => $user->phone,
                'placeholder' => '0812xxxxxxxx',
            ]) ?>
            <?= component('form-field', [
                'name'  => 'email',
                'label' => 'Email kontak',
                'type'  => 'email',
                'value' => $user->email,
                'help'  => 'Hanya sebagai informasi kontak, tidak dipakai untuk login.',
            ]) ?>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <button type="submit" class="rounded-md bg-brand-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">Simpan perubahan</button>
        </div>
    </form>

    <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-100 text-base font-semibold text-brand-700"><?= esc($user->initials()) ?></span>
            <div>
                <p class="font-semibold text-slate-900"><?= esc($user->name) ?></p>
                <p class="text-sm text-slate-500">@<?= esc($user->username) ?></p>
            </div>
        </div>
        <dl class="mt-5 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Peran</dt>
                <dd><?= component('badge', ['label' => $user->roleLabel(), 'color' => $user->isAdmin() ? 'purple' : ($user->isManager() ? 'indigo' : 'blue')]) ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Login terakhir</dt>
                <dd class="text-slate-700"><?= tanggal_id($user->last_login_at, true) ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Terdaftar sejak</dt>
                <dd class="text-slate-700"><?= tanggal_id($user->created_at) ?></dd>
            </div>
        </dl>
        <a href="<?= site_url('profile/password') ?>" class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-white px-3 py-2 text-sm font-semibold text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
            <?= icon('key', 'h-4 w-4') ?> Ganti password
        </a>
    </div>
</div>
<?= $this->endSection() ?>
