<?php

use App\Models\DealModel;
use App\Models\QuotationModel;

/**
 * @var array<string, mixed>       $deal
 * @var list<array<string, mixed>> $history
 * @var list<array<string, mixed>> $activities
 * @var list<array<string, mixed>> $tasks
 * @var list<array<string, mixed>> $quotations
 * @var list<array<string, mixed>> $stages
 * @var array<int, string>         $lostReasons
 * @var array<int, string>         $assignees
 * @var bool                       $canEdit
 * @var bool                       $canDelete
 */
$isOpen  = ! $deal['is_won'] && ! $deal['is_lost'];
$overdue = $isOpen && $deal['expected_close_date'] && $deal['expected_close_date'] < date('Y-m-d');
$contact = trim(($deal['contact_first_name'] ?? '') . ' ' . ($deal['contact_last_name'] ?? ''));

$actions = '';

if ($canEdit) {
    $actions .= component('button', ['label' => 'Ubah', 'url' => site_url('deals/' . $deal['id'] . '/edit'), 'icon' => 'pencil-square', 'variant' => 'secondary']);
}

if ($canDelete) {
    $actions .= component('delete-button', [
        'action'  => site_url('deals/' . $deal['id'] . '/delete'),
        'title'   => 'Hapus deal?',
        'message' => 'Deal "' . $deal['title'] . '" akan dihapus dari daftar dan pipeline.',
    ]);
}

$lostStageIds = array_column(array_filter($stages, static fn ($s): bool => (int) $s['is_lost'] === 1), 'id');
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $deal['title'],
    'subtitle'    => implode(' · ', array_filter([$deal['company_name'], $contact ?: null])),
    'actions'     => $actions,
    'breadcrumbs' => [['label' => 'Deal', 'url' => site_url('deals')], ['label' => $deal['title']]],
]) ?>

<!-- Progres tahap -->
<div class="mb-6 overflow-x-auto rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
    <ol class="flex min-w-max items-center gap-2 text-xs font-medium">
        <?php foreach ($stages as $stage): ?>
            <?php
            $current = (int) $stage['id'] === (int) $deal['stage_id'];
            $passed  = $isOpen && (int) $stage['sort_order'] < (int) $deal['stage_order'] && ! $stage['is_won'] && ! $stage['is_lost'];
            $classes = match (true) {
                $current && (int) $stage['is_won'] === 1  => 'bg-green-600 text-white',
                $current && (int) $stage['is_lost'] === 1 => 'bg-red-600 text-white',
                $current                                 => 'bg-brand-600 text-white',
                $passed                                  => 'bg-brand-100 text-brand-800',
                default                                  => 'bg-slate-100 text-slate-500',
            };
            ?>
            <li class="rounded-full px-3 py-1.5 <?= $classes ?>"><?= esc($stage['name']) ?> · <?= (int) $stage['probability'] ?>%</li>
        <?php endforeach ?>
    </ol>
    <?php if ($canEdit): ?>
        <form action="<?= site_url('deals/' . $deal['id'] . '/stage') ?>" method="post" class="mt-4 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-4"
              x-data="{ stage: '<?= (int) $deal['stage_id'] ?>', lostIds: <?= esc(json_encode(array_map('strval', $lostStageIds)), 'attr') ?> }">
            <?= csrf_field() ?>
            <div>
                <label for="move-stage" class="mb-1 block text-xs font-medium text-slate-500">Pindah ke tahap</label>
                <select id="move-stage" name="stage_id" x-model="stage" class="block rounded-md border-0 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                    <?php foreach ($stages as $stage): ?>
                        <option value="<?= $stage['id'] ?>" <?= (int) $stage['id'] === (int) $deal['stage_id'] ? 'selected' : '' ?>><?= esc($stage['name']) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <div x-show="lostIds.includes(stage)" x-cloak>
                <label for="move-reason" class="mb-1 block text-xs font-medium text-slate-500">Alasan kalah</label>
                <select id="move-reason" name="lost_reason_id" class="block rounded-md border-0 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                    <option value="">— Pilih alasan —</option>
                    <?php foreach ($lostReasons as $id => $name): ?>
                        <option value="<?= $id ?>" <?= (int) $id === (int) $deal['lost_reason_id'] ? 'selected' : '' ?>><?= esc($name) ?></option>
                    <?php endforeach ?>
                </select>
            </div>
            <?= component('button', ['label' => 'Pindahkan', 'variant' => 'secondary', 'icon' => 'arrow-path']) ?>
        </form>
    <?php endif ?>
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Penawaran</h2>
                <?php if ($canEdit): ?>
                    <?= component('button', ['label' => 'Buat penawaran', 'url' => site_url('quotations/new?deal=' . $deal['id']), 'icon' => 'plus', 'variant' => 'secondary']) ?>
                <?php endif ?>
            </div>
            <?php if ($quotations === []): ?>
                <?= component('empty-state', ['icon' => 'document-text', 'title' => 'Belum ada penawaran', 'message' => 'Buat penawaran harga dari katalog produk untuk deal ini.']) ?>
            <?php else: ?>
                <ul class="divide-y divide-slate-100">
                    <?php foreach ($quotations as $quotation): ?>
                        <?php $qStatus = QuotationModel::STATUSES[$quotation['status']] ?>
                        <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <a href="<?= site_url('quotations/' . $quotation['id']) ?>" class="font-mono text-sm font-medium text-slate-900 hover:text-brand-700"><?= esc($quotation['number']) ?></a>
                                <p class="text-xs text-slate-500"><?= tanggal_id($quotation['issue_date']) ?> · berlaku s.d. <?= tanggal_id($quotation['valid_until']) ?></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="whitespace-nowrap text-sm font-semibold text-slate-800"><?= rupiah($quotation['grand_total']) ?></span>
                                <?= component('badge', ['label' => $qStatus['label'], 'color' => $qStatus['color']]) ?>
                            </div>
                        </li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Aktivitas</h2>
                <?= component('activity-form', ['relatedType' => 'deal', 'relatedId' => $deal['id'], 'defaultType' => 'meeting']) ?>
            </div>
            <?= component('activity-timeline', ['activities' => $activities]) ?>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Tugas</h2>
                <?= component('task-form', ['relatedType' => 'deal', 'relatedId' => $deal['id'], 'assignees' => $assignees]) ?>
            </div>
            <?= component('task-list', ['tasks' => $tasks, 'showAssignee' => $assignees !== []]) ?>
        </section>
    </div>

    <div class="space-y-6 self-start">
        <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Nilai deal</p>
            <p class="mt-1 text-2xl font-semibold text-slate-900"><?= rupiah($deal['amount']) ?></p>
            <p class="mt-1 text-sm text-slate-500">Tertimbang <?= rupiah((float) $deal['amount'] * (int) $deal['probability'] / 100) ?> (<?= (int) $deal['probability'] ?>%)</p>
            <dl class="mt-5 space-y-3 text-sm">
                <div class="flex items-center justify-between gap-4">
                    <dt class="text-slate-500">Tahap</dt>
                    <dd><?= component('badge', ['label' => $deal['stage_name'], 'color' => DealModel::stageColor($deal)]) ?></dd>
                </div>
                <?php if ($deal['is_lost'] && $deal['lost_reason_name']): ?>
                    <div class="flex justify-between gap-4">
                        <dt class="text-slate-500">Alasan kalah</dt>
                        <dd class="text-right font-medium text-red-700"><?= esc($deal['lost_reason_name']) ?></dd>
                    </div>
                <?php endif ?>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500"><?= $isOpen ? 'Perkiraan closing' : 'Ditutup' ?></dt>
                    <dd class="text-right <?= $overdue ? 'font-semibold text-red-600' : 'text-slate-800' ?>">
                        <?= $isOpen ? tanggal_id($deal['expected_close_date']) . ($overdue ? ' (lewat)' : '') : tanggal_id($deal['closed_at']) ?>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Perusahaan</dt>
                    <dd class="text-right">
                        <?php if ($deal['company_name']): ?><a href="<?= site_url('companies/' . $deal['company_id']) ?>" class="text-brand-700 hover:underline"><?= esc($deal['company_name']) ?></a><?php else: ?>-<?php endif ?>
                    </dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Kontak</dt>
                    <dd class="text-right">
                        <?php if ($contact !== ''): ?><a href="<?= site_url('contacts/' . $deal['contact_id']) ?>" class="text-brand-700 hover:underline"><?= esc($contact) ?></a><?php else: ?>-<?php endif ?>
                    </dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-slate-100 pt-3">
                    <dt class="text-slate-500">Owner</dt>
                    <dd class="text-right text-slate-800"><?= esc($deal['owner_name'] ?? '-') ?></dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-slate-500">Dibuat</dt>
                    <dd class="text-right text-slate-800"><?= tanggal_id($deal['created_at']) ?></dd>
                </div>
            </dl>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Riwayat tahap</h2>
            </div>
            <ol class="space-y-4 px-5 py-4">
                <?php foreach ($history as $item): ?>
                    <li class="flex gap-3 text-sm">
                        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full <?= $item['is_won'] ? 'bg-green-500' : ($item['is_lost'] ? 'bg-red-500' : 'bg-brand-500') ?>"></span>
                        <div>
                            <p class="text-slate-800">
                                <?php if ($item['from_stage']): ?>
                                    <?= esc($item['from_stage']) ?> → <span class="font-medium"><?= esc($item['to_stage']) ?></span>
                                <?php else: ?>
                                    Dibuat di tahap <span class="font-medium"><?= esc($item['to_stage']) ?></span>
                                <?php endif ?>
                            </p>
                            <p class="text-xs text-slate-500"><?= tanggal_id($item['changed_at'], true) ?> · <?= esc($item['changed_by_name'] ?? 'Sistem') ?></p>
                        </div>
                    </li>
                <?php endforeach ?>
                <?php if ($history === []): ?>
                    <li class="text-sm text-slate-500">Belum ada riwayat.</li>
                <?php endif ?>
            </ol>
        </section>
    </div>
</div>
<?= $this->endSection() ?>
