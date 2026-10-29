<?php

use App\Models\TaskModel;

/**
 * Daftar tugas ringkas (halaman detail, widget dashboard, halaman tugas).
 *
 * @var list<array<string, mixed>>                                          $tasks
 * @var array<string, array{label: string, name: string, url: string}>|null $related     label record tertaut
 * @var bool|null                                                           $showAssignee
 * @var string|null                                                         $emptyTitle
 * @var string|null                                                         $emptyMessage
 */
$returnTo = current_url() . (service('request')->getUri()->getQuery() !== '' ? '?' . service('request')->getUri()->getQuery() : '');
$taskSvc  = service('taskService');
$me       = current_user();
?>
<?php if ($tasks === []): ?>
    <?= component('empty-state', [
        'icon'    => 'clipboard-document-check',
        'title'   => $emptyTitle ?? 'Belum ada tugas',
        'message' => $emptyMessage ?? 'Buat tugas follow-up agar tidak ada yang terlewat.',
    ]) ?>
<?php else: ?>
    <ul role="list" class="divide-y divide-slate-100">
        <?php foreach ($tasks as $task): ?>
            <?php
            $done     = $task['status'] === TaskModel::STATUS_DONE;
            $overdue  = TaskModel::isOverdue($task);
            $priority = TaskModel::PRIORITIES[$task['priority']] ?? TaskModel::PRIORITIES['medium'];
            $link     = $related[($task['related_type'] ?? '') . ':' . ($task['related_id'] ?? '')] ?? null;
            ?>
            <li class="flex items-start gap-3 px-5 py-3 <?= $overdue ? 'bg-red-50/40' : '' ?>">
                <form action="<?= site_url('tasks/' . $task['id'] . ($done ? '/reopen' : '/done')) ?>" method="post" class="pt-0.5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
                    <button type="submit" title="<?= $done ? 'Buka kembali' : 'Tandai selesai' ?>"
                            class="flex h-5 w-5 items-center justify-center rounded-full border <?= $done ? 'border-green-500 bg-green-500 text-white' : 'border-slate-300 text-transparent hover:border-green-500 hover:text-green-500' ?>">
                        <svg class="h-3 w-3" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.6l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
                        <span class="sr-only"><?= $done ? 'Buka kembali' : 'Tandai selesai' ?></span>
                    </button>
                </form>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-medium <?= $done ? 'text-slate-400 line-through' : 'text-slate-900' ?>"><?= esc($task['title']) ?></p>
                    <?php if (! empty($task['description'])): ?>
                        <p class="mt-0.5 line-clamp-2 text-xs text-slate-500"><?= esc($task['description']) ?></p>
                    <?php endif ?>
                    <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
                        <?php if ($done): ?>
                            <span>Selesai <?= esc(waktu_relatif($task['completed_at'])) ?></span>
                        <?php else: ?>
                            <span class="inline-flex items-center gap-1 <?= $overdue ? 'font-semibold text-red-600' : '' ?>">
                                <?= icon('clock', 'h-3.5 w-3.5') ?>
                                <?= $overdue ? 'Terlambat · ' : '' ?><?= tanggal_id($task['due_at'], true) ?>
                            </span>
                        <?php endif ?>
                        <?= component('badge', ['label' => $priority['label'], 'color' => $priority['color']]) ?>
                        <?php if (! empty($showAssignee)): ?>
                            <span>· <?= esc($task['assignee_name'] ?? '-') ?></span>
                        <?php endif ?>
                        <?php if ($link !== null): ?>
                            <span>· <?= esc($link['label']) ?> <a href="<?= esc($link['url'], 'attr') ?>" class="text-brand-700 hover:underline"><?= esc($link['name']) ?></a></span>
                        <?php endif ?>
                    </p>
                </div>
                <?php if ($me !== null && $taskSvc->canDelete($task, $me)): ?>
                    <form action="<?= site_url('tasks/' . $task['id'] . '/delete') ?>" method="post" x-data
                          @submit.prevent="if (confirm('Hapus tugas ini?')) $el.submit()">
                        <?= csrf_field() ?>
                        <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
                        <button type="submit" class="rounded p-1.5 text-slate-300 hover:bg-red-50 hover:text-red-600" title="Hapus tugas"><?= icon('trash', 'h-4 w-4') ?></button>
                    </form>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
