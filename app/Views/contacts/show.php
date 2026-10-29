<?php

use App\Models\ContactModel;

/**
 * @var array<string, mixed> $contact
 * @var bool                 $canEdit
 * @var bool                 $canDelete
 * @var list<array<string, mixed>> $activities
 * @var list<array<string, mixed>> $tasks
 * @var list<array<string, mixed>> $deals
 * @var array<int, string>   $assignees
 */
$fullName = ContactModel::fullName($contact);
$waNumber = $contact['whatsapp'] ?: $contact['phone'];
$actions  = '';

if ($waNumber) {
    $actions .= component('button', ['label' => 'Chat WhatsApp', 'url' => wa_link($waNumber), 'icon' => 'chat-bubble-left-right', 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]);
}

if ($contact['phone']) {
    $actions .= component('button', ['label' => 'Telepon', 'url' => 'tel:' . $contact['phone'], 'icon' => 'phone', 'variant' => 'secondary']);
}

if ($canEdit) {
    $actions .= component('button', ['label' => 'Ubah', 'url' => site_url('contacts/' . $contact['id'] . '/edit'), 'icon' => 'pencil-square', 'variant' => 'secondary']);
}

if ($canDelete) {
    $actions .= component('delete-button', [
        'action'  => site_url('contacts/' . $contact['id'] . '/delete'),
        'title'   => 'Hapus kontak?',
        'message' => 'Kontak "' . $fullName . '" akan dihapus dari daftar.',
    ]);
}
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $fullName,
    'subtitle'    => implode(' · ', array_filter([$contact['job_title'], $contact['company_name']])),
    'actions'     => $actions,
    'breadcrumbs' => [['label' => 'Kontak', 'url' => site_url('contacts')], ['label' => $fullName]],
]) ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-6 lg:col-span-2">
        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Timeline aktivitas</h2>
                <?php if ($canEdit): ?>
                    <?= component('activity-form', ['relatedType' => 'contact', 'relatedId' => $contact['id']]) ?>
                <?php endif ?>
            </div>
            <?= component('activity-timeline', [
                'activities'   => $activities,
                'emptyMessage' => 'Riwayat telepon, meeting, WhatsApp, dan kunjungan dengan ' . $contact['first_name'] . ' akan tampil di sini.',
            ]) ?>
        </section>

        <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Tugas</h2>
                <?php if ($canEdit): ?>
                    <?= component('task-form', ['relatedType' => 'contact', 'relatedId' => $contact['id'], 'assignees' => $assignees]) ?>
                <?php endif ?>
            </div>
            <?= component('task-list', ['tasks' => $tasks, 'showAssignee' => $assignees !== []]) ?>
        </section>

        <?php if ($deals !== []): ?>
            <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-200 px-5 py-3">
                    <h2 class="text-sm font-semibold text-slate-800">Deal</h2>
                </div>
                <?= view('deals/_list', ['deals' => $deals], ['saveData' => false]) ?>
            </section>
        <?php endif ?>
    </div>

    <div class="self-start rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex items-center gap-3">
            <span class="flex h-12 w-12 items-center justify-center rounded-full bg-brand-100 text-base font-semibold text-brand-700">
                <?= esc(strtoupper(mb_substr($contact['first_name'], 0, 1) . mb_substr((string) $contact['last_name'], 0, 1))) ?>
            </span>
            <div class="min-w-0">
                <p class="truncate font-semibold text-slate-900"><?= esc($fullName) ?></p>
                <p class="truncate text-sm text-slate-500"><?= esc($contact['job_title'] ?? '-') ?></p>
            </div>
        </div>
        <dl class="mt-5 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Perusahaan</dt>
                <dd class="text-right text-slate-800">
                    <?php if ($contact['company_name']): ?>
                        <a href="<?= site_url('companies/' . $contact['company_id']) ?>" class="text-brand-700 hover:underline"><?= esc($contact['company_name']) ?></a>
                    <?php else: ?>-<?php endif ?>
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">No. HP</dt>
                <dd class="text-right text-slate-800"><?= esc(format_hp($contact['phone'])) ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">WhatsApp</dt>
                <dd class="text-right text-slate-800"><?= esc(format_hp($waNumber)) ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Email</dt>
                <dd class="truncate text-right text-slate-800">
                    <?php if ($contact['email']): ?><a href="mailto:<?= esc($contact['email'], 'attr') ?>" class="hover:text-brand-700"><?= esc($contact['email']) ?></a><?php else: ?>-<?php endif ?>
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Tanggal lahir</dt>
                <dd class="text-right text-slate-800"><?= tanggal_id($contact['birth_date']) ?></dd>
            </div>
            <div class="flex justify-between gap-4 border-t border-slate-100 pt-3">
                <dt class="text-slate-500">Owner</dt>
                <dd class="text-right text-slate-800"><?= esc($contact['owner_name'] ?? '-') ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Dibuat</dt>
                <dd class="text-right text-slate-800"><?= tanggal_id($contact['created_at']) ?></dd>
            </div>
        </dl>
    </div>
</div>
<?= $this->endSection() ?>
