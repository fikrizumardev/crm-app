<?php

use CodeIgniter\Pager\Pager;

/**
 * @var list<array<string, mixed>> $logs
 * @var Pager                      $pager
 * @var array<string, mixed>       $filters
 * @var array<int, string>         $users
 * @var list<string>               $entities
 * @var list<string>               $actions
 */
$inputClass  = 'block w-full rounded-md border-0 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
$actionColor = [
    'create' => 'green', 'update' => 'blue', 'delete' => 'red', 'login' => 'teal', 'login_failed' => 'orange',
    'login_locked' => 'red', 'stage_change' => 'indigo', 'convert' => 'purple', 'reassign' => 'yellow',
];
$format = static function (mixed $value): string {
    if ($value === null || $value === '') {
        return '—';
    }

    return is_array($value) ? (string) json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
};
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Log Audit',
    'subtitle' => 'Catatan siapa mengubah apa dan kapan, termasuk login sukses dan gagal.',
]) ?>

<form method="get" action="<?= site_url('admin/audit-logs') ?>" class="mb-4 grid grid-cols-1 gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
    <div>
        <label for="filter-user" class="mb-1 block text-xs font-medium text-slate-500">Pengguna</label>
        <select id="filter-user" name="user" class="<?= $inputClass ?>">
            <option value="">Semua</option>
            <?php foreach ($users as $id => $name): ?>
                <option value="<?= $id ?>" <?= (string) ($filters['user'] ?? '') === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div>
        <label for="filter-entity" class="mb-1 block text-xs font-medium text-slate-500">Entitas</label>
        <select id="filter-entity" name="entity" class="<?= $inputClass ?>">
            <option value="">Semua</option>
            <?php foreach ($entities as $entity): ?>
                <option value="<?= esc($entity, 'attr') ?>" <?= ($filters['entity'] ?? '') === $entity ? 'selected' : '' ?>><?= esc($entity) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div>
        <label for="filter-action" class="mb-1 block text-xs font-medium text-slate-500">Aksi</label>
        <select id="filter-action" name="action" class="<?= $inputClass ?>">
            <option value="">Semua</option>
            <?php foreach ($actions as $action): ?>
                <option value="<?= esc($action, 'attr') ?>" <?= ($filters['action'] ?? '') === $action ? 'selected' : '' ?>><?= esc($action) ?></option>
            <?php endforeach ?>
        </select>
    </div>
    <div>
        <label for="filter-from" class="mb-1 block text-xs font-medium text-slate-500">Dari</label>
        <input id="filter-from" type="date" name="from" value="<?= esc($filters['from'] ?? '', 'attr') ?>" class="<?= $inputClass ?>">
    </div>
    <div>
        <label for="filter-to" class="mb-1 block text-xs font-medium text-slate-500">Sampai</label>
        <input id="filter-to" type="date" name="to" value="<?= esc($filters['to'] ?? '', 'attr') ?>" class="<?= $inputClass ?>">
    </div>
    <div class="flex items-end gap-2">
        <?= component('button', ['label' => 'Filter', 'icon' => 'funnel', 'variant' => 'secondary']) ?>
        <?= component('button', ['label' => 'Reset', 'url' => site_url('admin/audit-logs'), 'variant' => 'ghost']) ?>
    </div>
</form>

<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <?php if ($logs === []): ?>
        <?= component('empty-state', ['icon' => 'shield-check', 'title' => 'Tidak ada log yang cocok']) ?>
    <?php else: ?>
        <ul class="divide-y divide-slate-100">
            <?php foreach ($logs as $log): ?>
                <?php
                $old  = $log['old_values'] === null ? [] : (array) json_decode($log['old_values'], true);
                $new  = $log['new_values'] === null ? [] : (array) json_decode($log['new_values'], true);
                $keys = array_values(array_unique([...array_keys($old), ...array_keys($new)]));
                ?>
                <li x-data="{ open: false }">
                    <button type="button" @click="open = !open" class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 px-5 py-3 text-left text-sm hover:bg-slate-50" <?= $keys === [] ? 'disabled' : '' ?>>
                        <span class="w-36 shrink-0 text-xs text-slate-500"><?= tanggal_id($log['created_at'], true) ?></span>
                        <?= component('badge', ['label' => $log['action'], 'color' => $actionColor[$log['action']] ?? 'gray']) ?>
                        <span class="font-medium text-slate-800"><?= esc($log['entity']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></span>
                        <span class="text-slate-500">oleh <?= esc($log['user_name'] ?? 'Sistem / tamu') ?></span>
                        <span class="ml-auto text-xs text-slate-400"><?= esc($log['ip_address'] ?? '') ?></span>
                        <?php if ($keys !== []): ?>
                            <span class="text-slate-400 transition" :class="open ? 'rotate-180' : ''"><?= icon('chevron-down', 'h-4 w-4') ?></span>
                        <?php endif ?>
                    </button>
                    <?php if ($keys !== []): ?>
                        <div x-show="open" x-cloak class="overflow-x-auto bg-slate-50 px-5 pb-4">
                            <table class="min-w-full text-xs">
                                <thead>
                                    <tr class="text-left text-slate-500">
                                        <th class="py-2 pr-4 font-semibold">Kolom</th>
                                        <th class="py-2 pr-4 font-semibold">Sebelum</th>
                                        <th class="py-2 font-semibold">Sesudah</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 font-mono">
                                    <?php foreach ($keys as $key): ?>
                                        <tr>
                                            <td class="py-1.5 pr-4 text-slate-600"><?= esc($key) ?></td>
                                            <td class="py-1.5 pr-4 text-red-700"><?= esc($format($old[$key] ?? null)) ?></td>
                                            <td class="py-1.5 text-green-700"><?= esc($format($new[$key] ?? null)) ?></td>
                                        </tr>
                                    <?php endforeach ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif ?>
                </li>
            <?php endforeach ?>
        </ul>
        <div class="border-t border-slate-200 px-4 py-3"><?= $pager->links('default', 'tailwind') ?></div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
