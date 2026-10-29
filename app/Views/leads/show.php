<?php

use App\Models\ContactModel;
use App\Models\LeadModel;

/**
 * @var array<string, mixed>      $lead
 * @var array<string, mixed>|null $company
 * @var array<string, mixed>|null $contact
 * @var bool                      $canEdit
 * @var bool                      $canDelete
 * @var list<array<string, mixed>> $activities
 * @var list<array<string, mixed>> $tasks
 * @var array<int, string>         $assignees
 */
$isConverted = $lead['status'] === LeadModel::STATUS_CONVERTED;

$actions = '';

if ($canEdit && ! $isConverted) {
    $actions .= component('button', ['label' => 'Konversi', 'url' => site_url('leads/' . $lead['id'] . '/convert'), 'icon' => 'arrows-right-left']);
}

if ($canEdit) {
    $actions .= component('button', ['label' => 'Ubah', 'url' => site_url('leads/' . $lead['id'] . '/edit'), 'icon' => 'pencil-square', 'variant' => 'secondary']);
}

if ($canDelete) {
    $actions .= component('delete-button', [
        'action'  => site_url('leads/' . $lead['id'] . '/delete'),
        'title'   => 'Hapus lead?',
        'message' => 'Lead "' . $lead['name'] . '" akan dihapus dari daftar.',
    ]);
}
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $lead['name'],
    'subtitle'    => trim(($lead['job_title'] ?? '') . ($lead['job_title'] && $lead['company_name'] ? ' · ' : '') . ($lead['company_name'] ?? '')),
    'actions'     => $actions,
    'breadcrumbs' => [['label' => 'Lead', 'url' => site_url('leads')], ['label' => $lead['name']]],
]) ?>

<?php if ($isConverted): ?>
    <div class="mb-6 flex flex-col gap-2 rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 sm:flex-row sm:items-center">
        <?= icon('check-circle', 'h-5 w-5 shrink-0 text-green-500') ?>
        <p class="flex-1">
            Dikonversi pada <?= tanggal_id($lead['converted_at'], true) ?>
            <?php if ($company): ?> menjadi perusahaan <a href="<?= site_url('companies/' . $company['id']) ?>" class="font-semibold underline"><?= esc($company['name']) ?></a><?php endif ?>
            <?php if ($contact): ?> dan kontak <a href="<?= site_url('contacts/' . $contact['id']) ?>" class="font-semibold underline"><?= esc(ContactModel::fullName($contact)) ?></a><?php endif ?>.
        </p>
    </div>
<?php endif ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Informasi lead</h2>
            </div>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 p-5 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-slate-500">No. HP</dt>
                    <dd class="mt-1 flex items-center gap-2 text-slate-800">
                        <?= esc(format_hp($lead['phone'])) ?>
                        <?php if ($lead['phone']): ?>
                            <a href="<?= esc(wa_link($lead['phone']), 'attr') ?>" target="_blank" rel="noopener" class="rounded bg-green-50 px-1.5 py-0.5 text-xs font-medium text-green-700 hover:bg-green-100">WhatsApp</a>
                            <a href="tel:<?= esc($lead['phone'], 'attr') ?>" class="rounded bg-slate-100 px-1.5 py-0.5 text-xs font-medium text-slate-700 hover:bg-slate-200">Telepon</a>
                        <?php endif ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Email</dt>
                    <dd class="mt-1 text-slate-800">
                        <?php if ($lead['email']): ?><a href="mailto:<?= esc($lead['email'], 'attr') ?>" class="hover:text-brand-700"><?= esc($lead['email']) ?></a><?php else: ?>-<?php endif ?>
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500">Perusahaan</dt>
                    <dd class="mt-1 text-slate-800"><?= esc($lead['company_name'] ?? '-') ?></dd>
                </div>
                <div>
                    <dt class="text-slate-500">Jabatan</dt>
                    <dd class="mt-1 text-slate-800"><?= esc($lead['job_title'] ?? '-') ?></dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-slate-500">Catatan</dt>
                    <dd class="mt-1 whitespace-pre-line text-slate-800"><?= esc($lead['notes'] ?? '-') ?></dd>
                </div>
            </dl>
        </div>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Aktivitas</h2>
                <?php if ($canEdit): ?>
                    <?= component('activity-form', ['relatedType' => 'lead', 'relatedId' => $lead['id']]) ?>
                <?php endif ?>
            </div>
            <?= component('activity-timeline', ['activities' => $activities]) ?>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Tugas</h2>
                <?php if ($canEdit): ?>
                    <?= component('task-form', ['relatedType' => 'lead', 'relatedId' => $lead['id'], 'assignees' => $assignees]) ?>
                <?php endif ?>
            </div>
            <?= component('task-list', ['tasks' => $tasks, 'showAssignee' => $assignees !== []]) ?>
        </section>
    </div>

    <div class="self-start rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="space-y-4 text-sm">
            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-500">Status</dt>
                <dd><?= component('badge', ['label' => LeadModel::STATUSES[$lead['status']], 'color' => LeadModel::STATUS_COLORS[$lead['status']]]) ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Sumber</dt>
                <dd class="text-slate-800"><?= esc($lead['source_name'] ?? '-') ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Owner</dt>
                <dd class="text-slate-800"><?= esc($lead['owner_name'] ?? '-') ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Dibuat</dt>
                <dd class="text-slate-800"><?= tanggal_id($lead['created_at'], true) ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Diperbarui</dt>
                <dd class="text-slate-800"><?= waktu_relatif($lead['updated_at']) ?></dd>
            </div>
        </dl>
    </div>
</div>
<?= $this->endSection() ?>
