<?php

use App\Models\TaskModel;

/**
 * Tombol + modal "Tambah tugas", mandiri atau tertaut ke record.
 *
 * @var string|null          $relatedType
 * @var int|string|null      $relatedId
 * @var array<int, string>   $assignees pilihan penerima tugas (kosong untuk sales)
 * @var string|null          $label
 * @var string|null          $variant   gaya tombol
 */
$useOld = session()->getFlashdata('open_modal') === 'task';
$errors = $useOld ? form_errors() : [];
$old    = static fn (string $key, string $default = ''): string => $useOld ? (string) old($key, $default, false) : $default;
$input  = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
$me     = current_user();
?>
<div x-data="{ open: <?= $useOld ? 'true' : 'false' ?> }" @keydown.escape.window="open = false">
    <?= component('button', ['label' => $label ?? 'Tambah tugas', 'icon' => 'plus', 'variant' => $variant ?? 'secondary', 'type' => 'button', 'attrs' => ['@click' => 'open = true']]) ?>

    <div x-cloak x-show="open" class="relative z-50" role="dialog" aria-modal="true" aria-label="Tambah tugas">
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <form x-show="open" x-transition @click.outside="open = false" action="<?= site_url('tasks') ?>" method="post"
                      class="w-full max-w-lg overflow-hidden rounded-lg bg-white text-left shadow-xl">
                    <?= csrf_field() ?>
                    <?php if (! empty($relatedType)): ?>
                        <input type="hidden" name="related_type" value="<?= esc($relatedType, 'attr') ?>">
                        <input type="hidden" name="related_id" value="<?= (int) $relatedId ?>">
                    <?php endif ?>

                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold text-slate-900">Tambah tugas</h3>
                        <button type="button" class="rounded p-1 text-slate-400 hover:text-slate-600" @click="open = false"><?= icon('x-mark', 'h-5 w-5') ?></button>
                    </div>

                    <div class="grid grid-cols-1 gap-4 p-5 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <label for="task-title" class="block text-sm font-medium text-slate-700">Judul tugas <span class="text-red-500">*</span></label>
                            <input id="task-title" type="text" name="title" required maxlength="200" value="<?= esc($old('title'), 'attr') ?>"
                                   placeholder="mis. Kirim revisi penawaran" class="mt-1 <?= $input ?>">
                            <?php if (isset($errors['title'])): ?><p class="mt-1 text-xs text-red-600"><?= esc($errors['title']) ?></p><?php endif ?>
                        </div>
                        <div>
                            <label for="task-due" class="block text-sm font-medium text-slate-700">Jatuh tempo <span class="text-red-500">*</span></label>
                            <input id="task-due" type="datetime-local" name="due_at" required value="<?= esc($old('due_at', date('Y-m-d', strtotime('+1 day')) . 'T09:00'), 'attr') ?>" class="mt-1 <?= $input ?>">
                            <?php if (isset($errors['due_at'])): ?><p class="mt-1 text-xs text-red-600"><?= esc($errors['due_at']) ?></p><?php endif ?>
                        </div>
                        <div>
                            <label for="task-priority" class="block text-sm font-medium text-slate-700">Prioritas</label>
                            <select id="task-priority" name="priority" class="mt-1 <?= $input ?>">
                                <?php foreach (TaskModel::PRIORITIES as $value => $meta): ?>
                                    <option value="<?= $value ?>" <?= $old('priority', 'medium') === $value ? 'selected' : '' ?>><?= esc($meta['label']) ?></option>
                                <?php endforeach ?>
                            </select>
                        </div>
                        <?php if (! empty($assignees)): ?>
                            <div class="sm:col-span-2">
                                <label for="task-assignee" class="block text-sm font-medium text-slate-700">Ditugaskan ke</label>
                                <select id="task-assignee" name="assigned_to" class="mt-1 <?= $input ?>">
                                    <?php foreach ($assignees as $id => $name): ?>
                                        <option value="<?= $id ?>" <?= $old('assigned_to', (string) $me?->id) === (string) $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                                    <?php endforeach ?>
                                </select>
                                <?php if (isset($errors['assigned_to'])): ?><p class="mt-1 text-xs text-red-600"><?= esc($errors['assigned_to']) ?></p><?php endif ?>
                            </div>
                        <?php endif ?>
                        <div class="sm:col-span-2">
                            <label for="task-description" class="block text-sm font-medium text-slate-700">Keterangan</label>
                            <textarea id="task-description" name="description" rows="2" maxlength="5000" class="mt-1 <?= $input ?>"><?= esc($old('description')) ?></textarea>
                        </div>
                        <?php if (isset($errors['related_id'])): ?><p class="text-xs text-red-600 sm:col-span-2"><?= esc($errors['related_id']) ?></p><?php endif ?>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                        <?= component('button', ['label' => 'Batal', 'variant' => 'secondary', 'type' => 'button', 'attrs' => ['@click' => 'open = false']]) ?>
                        <?= component('button', ['label' => 'Simpan tugas']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
