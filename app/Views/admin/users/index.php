<?php

use App\Entities\User;
use CodeIgniter\Pager\Pager;

/**
 * @var list<User>                                                      $users
 * @var Pager                                                           $pager
 * @var array<string, mixed>                                            $filters
 * @var array{username: string, password: string, reason: string}|null $generated
 * @var User                                                            $me
 */
$inputClass = 'block w-full rounded-md border-0 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
$roleColor  = ['admin' => 'purple', 'manager' => 'indigo', 'sales' => 'blue'];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Pengguna',
    'subtitle' => 'Kelola akun, peran, struktur tim, dan reset password.',
    'actions'  => component('button', ['label' => 'Tambah Pengguna', 'url' => site_url('admin/users/new'), 'icon' => 'plus']),
]) ?>

<?php if (is_array($generated)): ?>
    <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4" x-data="{ copied: false }">
        <div class="flex gap-3">
            <?= icon('key', 'h-5 w-5 shrink-0 text-amber-600') ?>
            <div class="flex-1 text-sm text-amber-900">
                <p class="font-semibold"><?= esc($generated['reason']) ?> untuk <span class="font-mono">@<?= esc($generated['username']) ?></span></p>
                <p class="mt-1">Sampaikan password ini ke pengguna secara langsung. Password hanya ditampilkan <strong>sekali</strong> dan wajib diganti saat login pertama.</p>
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <code class="rounded bg-white px-3 py-1.5 font-mono text-base tracking-wider text-slate-900 ring-1 ring-amber-200" x-ref="password"><?= esc($generated['password']) ?></code>
                    <button type="button" class="rounded-md bg-white px-3 py-1.5 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-300 hover:bg-amber-100"
                            @click="navigator.clipboard.writeText($refs.password.textContent.trim()); copied = true; setTimeout(() => copied = false, 2000)">
                        <span x-show="!copied">Salin</span><span x-show="copied" x-cloak>Tersalin ✓</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>

<form method="get" action="<?= site_url('admin/users') ?>" class="mb-4 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-4">
    <div class="sm:col-span-2">
        <label for="filter-q" class="mb-1 block text-xs font-medium text-slate-500">Cari</label>
        <input id="filter-q" type="search" name="q" value="<?= esc($filters['q'] ?? '', 'attr') ?>" placeholder="Nama, username, email" class="<?= $inputClass ?>">
    </div>
    <div>
        <label for="filter-role" class="mb-1 block text-xs font-medium text-slate-500">Peran</label>
        <select id="filter-role" name="role" class="<?= $inputClass ?>">
            <option value="">Semua peran</option>
            <?php foreach (User::ROLES as $value => $label): ?>
                <option value="<?= $value ?>" <?= ($filters['role'] ?? '') === $value ? 'selected' : '' ?>><?= esc($label) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div class="flex items-end gap-2">
        <div class="flex-1">
            <label for="filter-status" class="mb-1 block text-xs font-medium text-slate-500">Status</label>
            <select id="filter-status" name="status" class="<?= $inputClass ?>">
                <option value="">Semua</option>
                <option value="active" <?= ($filters['status'] ?? '') === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= ($filters['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <?= component('button', ['label' => 'Cari', 'variant' => 'secondary', 'icon' => 'magnifying-glass']) ?>
    </div>
</form>

<?php ob_start() ?>
<?php foreach ($users as $user): ?>
    <tr class="<?= $user->is_active ? 'hover:bg-slate-50' : 'bg-slate-50 text-slate-400' ?>">
        <td class="px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600"><?= esc($user->initials()) ?></span>
                <div>
                    <p class="font-medium <?= $user->is_active ? 'text-slate-900' : 'text-slate-500' ?>"><?= esc($user->name) ?><?= $user->id === $me->id ? ' <span class="text-xs font-normal text-slate-400">(Anda)</span>' : '' ?></p>
                    <p class="text-xs text-slate-500">@<?= esc($user->username) ?><?= $user->email ? ' · ' . esc($user->email) : '' ?></p>
                </div>
            </div>
        </td>
        <td class="px-4 py-3"><?= component('badge', ['label' => $user->roleLabel(), 'color' => $roleColor[$user->role] ?? 'gray']) ?></td>
        <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= esc($user->manager_name ?? '-') ?></td>
        <td class="px-4 py-3">
            <?= $user->is_active ? component('badge', ['label' => 'Aktif', 'color' => 'green']) : component('badge', ['label' => 'Nonaktif', 'color' => 'gray']) ?>
            <?php if ($user->must_change_password): ?>
                <span class="ml-1 text-xs text-amber-600" title="Wajib ganti password saat login">wajib ganti password</span>
            <?php endif ?>
        </td>
        <td class="whitespace-nowrap px-4 py-3 text-slate-600"><?= $user->last_login_at ? esc(waktu_relatif($user->last_login_at)) : 'Belum pernah' ?></td>
        <td class="whitespace-nowrap px-4 py-3 text-right" x-data="{ open: false }">
            <div class="relative inline-block text-left" @click.outside="open = false">
                <button type="button" @click="open = !open" class="rounded p-1.5 text-slate-500 hover:bg-slate-100" title="Aksi"><?= icon('ellipsis-vertical', 'h-5 w-5') ?></button>
                <div x-show="open" x-cloak x-transition class="absolute right-0 z-20 mt-1 w-52 rounded-md bg-white py-1 text-left shadow-lg ring-1 ring-slate-900/5">
                    <a href="<?= site_url('admin/users/' . $user->id . '/edit') ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"><?= icon('pencil-square', 'h-4 w-4') ?> Ubah data</a>
                    <form method="post" action="<?= site_url('admin/users/' . $user->id . '/reset-password') ?>"
                          @submit.prevent="if (confirm('Reset password <?= esc($user->name, 'js') ?>? Password lama tidak bisa dipakai lagi.')) $el.submit()">
                        <?= csrf_field() ?>
                        <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"><?= icon('key', 'h-4 w-4') ?> Reset password</button>
                    </form>
                    <?php if ($user->id !== $me->id): ?>
                        <form method="post" action="<?= site_url('admin/users/' . $user->id . ($user->is_active ? '/deactivate' : '/activate')) ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-sm <?= $user->is_active ? 'text-red-600 hover:bg-red-50' : 'text-green-700 hover:bg-green-50' ?>">
                                <?= icon($user->is_active ? 'lock-closed' : 'check-circle', 'h-4 w-4') ?> <?= $user->is_active ? 'Nonaktifkan' : 'Aktifkan kembali' ?>
                            </button>
                        </form>
                    <?php endif ?>
                </div>
            </div>
        </td>
    </tr>
<?php endforeach ?>
<?php $rows = ob_get_clean() ?>

<?= component('table', [
    'columns' => [
        ['label' => 'Pengguna'],
        ['label' => 'Peran'],
        ['label' => 'Manager'],
        ['label' => 'Status'],
        ['label' => 'Login terakhir'],
        ['label' => '', 'class' => 'text-right'],
    ],
    'rows'   => $rows,
    'empty'  => component('empty-state', ['icon' => 'user-group', 'title' => 'Pengguna tidak ditemukan']),
    'footer' => $pager->links('default', 'tailwind'),
]) ?>
<?= $this->endSection() ?>
