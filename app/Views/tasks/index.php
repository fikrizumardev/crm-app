<?php

use App\Entities\User;
use App\Models\TaskModel;
use CodeIgniter\Pager\Pager;

/**
 * @var string                     $tab
 * @var list<array<string, mixed>> $tasks
 * @var Pager                      $pager
 * @var array<string, int>         $counts
 * @var array<string, array{label: string, name: string, url: string}> $related
 * @var array<int, string>         $assignees
 * @var int|null                   $assigneeId
 * @var User                       $user
 */
$emptyMessages = [
    'today'   => ['Tidak ada tugas hari ini', 'Semua beres untuk hari ini. Tambahkan follow-up baru bila perlu.'],
    'overdue' => ['Tidak ada tugas terlambat', 'Bagus! Semua tugas diselesaikan tepat waktu.'],
    'week'    => ['Tidak ada tugas minggu ini', 'Tugas yang jatuh tempo sampai hari Minggu akan tampil di sini.'],
    'done'    => ['Belum ada tugas selesai', 'Tugas yang sudah ditandai selesai akan tampil di sini.'],
];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Tugas',
    'subtitle' => 'Follow-up dan pekerjaan yang perlu diselesaikan.',
    'actions'  => component('task-form', ['assignees' => $assignees, 'variant' => 'primary']),
]) ?>

<div class="rounded-lg border border-slate-200 bg-white shadow-sm">
    <div class="flex flex-col gap-3 border-b border-slate-200 px-3 sm:flex-row sm:items-center sm:justify-between">
        <nav class="flex gap-1 overflow-x-auto" aria-label="Tab tugas">
            <?php foreach (TaskModel::TABS as $key => $label): ?>
                <?php $active = $key === $tab ?>
                <a href="<?= esc(query_url('tasks', ['tab' => $key, 'page' => null]), 'attr') ?>"
                   class="flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium <?= $active ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
                    <?= esc($label) ?>
                    <span class="rounded-full px-2 py-0.5 text-xs <?= $key === 'overdue' && $counts[$key] > 0 ? 'bg-red-100 text-red-700' : 'bg-slate-100 text-slate-600' ?>"><?= $counts[$key] ?></span>
                </a>
            <?php endforeach ?>
        </nav>
        <?php if ($assignees !== []): ?>
            <form method="get" action="<?= site_url('tasks') ?>" class="pb-3 sm:pb-0" x-data>
                <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
                <select name="assignee" @change="$el.form.submit()" aria-label="Filter penerima tugas"
                        class="rounded-md border-0 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                    <option value="">Semua anggota tim</option>
                    <?php foreach ($assignees as $id => $name): ?>
                        <option value="<?= $id ?>" <?= $assigneeId === $id ? 'selected' : '' ?>><?= esc($name) ?></option>
                    <?php endforeach ?>
                </select>
            </form>
        <?php endif ?>
    </div>

    <?= component('task-list', [
        'tasks'        => $tasks,
        'related'      => $related,
        'showAssignee' => ! $user->isSales(),
        'emptyTitle'   => $emptyMessages[$tab][0],
        'emptyMessage' => $emptyMessages[$tab][1],
    ]) ?>

    <?php if ($tasks !== []): ?>
        <div class="border-t border-slate-200 px-4 py-3"><?= $pager->links('default', 'tailwind') ?></div>
    <?php endif ?>
</div>
<?= $this->endSection() ?>
