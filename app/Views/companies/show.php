<?php

use App\Models\ActivityModel;
use App\Models\ContactModel;

/**
 * @var array<string, mixed>       $company
 * @var list<array<string, mixed>> $contacts
 * @var list<array<string, mixed>> $deals
 * @var list<array<string, mixed>> $activities
 * @var list<array<string, mixed>> $notes
 * @var list<array<string, mixed>> $tasks
 * @var array<int, string>         $assignees
 * @var bool                       $canEdit
 * @var bool                       $canDelete
 */
$actions = '';

if ($canEdit) {
    $actions .= component('button', ['label' => 'Ubah', 'url' => site_url('companies/' . $company['id'] . '/edit'), 'icon' => 'pencil-square', 'variant' => 'secondary']);
}

if ($canDelete) {
    $actions .= component('delete-button', [
        'action'  => site_url('companies/' . $company['id'] . '/delete'),
        'title'   => 'Hapus perusahaan?',
        'message' => 'Perusahaan "' . $company['name'] . '" akan dihapus dari daftar. Kontak di dalamnya tetap tersimpan.',
    ]);
}

$tabs = [
    'contacts'   => ['label' => 'Kontak', 'count' => count($contacts)],
    'deals'      => ['label' => 'Deal', 'count' => count($deals)],
    'activities' => ['label' => 'Aktivitas', 'count' => count($activities)],
    'notes'      => ['label' => 'Catatan', 'count' => count($notes)],
];

$related = ActivityModel::relatedLabels($activities);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $company['name'],
    'subtitle'    => implode(' · ', array_filter([$company['industry_name'], $company['city']])),
    'actions'     => $actions,
    'breadcrumbs' => [['label' => 'Perusahaan', 'url' => site_url('companies')], ['label' => $company['name']]],
]) ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2" x-data="{ tab: 'contacts' }">
        <div class="rounded-lg border border-slate-200 bg-white shadow-sm">
            <nav class="flex gap-1 overflow-x-auto border-b border-slate-200 px-3" aria-label="Tab">
                <?php foreach ($tabs as $key => $tab): ?>
                    <button type="button" @click="tab = '<?= $key ?>'"
                            :class="tab === '<?= $key ?>' ? 'border-brand-600 text-brand-700' : 'border-transparent text-slate-500 hover:text-slate-700'"
                            class="flex items-center gap-2 whitespace-nowrap border-b-2 px-3 py-3 text-sm font-medium">
                        <?= esc($tab['label']) ?>
                        <?php if ($tab['count'] !== null): ?>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600"><?= $tab['count'] ?></span>
                        <?php endif ?>
                    </button>
                <?php endforeach ?>
            </nav>

            <div x-show="tab === 'contacts'">
                <?php if ($contacts === []): ?>
                    <?= component('empty-state', [
                        'icon'        => 'users',
                        'title'       => 'Belum ada kontak',
                        'message'     => 'Tambahkan orang yang Anda hubungi di perusahaan ini.',
                        'actionUrl'   => $canEdit ? site_url('contacts/new?company=' . $company['id']) : null,
                        'actionLabel' => 'Tambah Kontak',
                    ]) ?>
                <?php else: ?>
                    <ul class="divide-y divide-slate-100">
                        <?php foreach ($contacts as $contact): ?>
                            <li class="flex flex-col gap-2 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-semibold text-slate-600">
                                        <?= esc(strtoupper(mb_substr($contact['first_name'], 0, 1) . mb_substr((string) $contact['last_name'], 0, 1))) ?>
                                    </span>
                                    <div>
                                        <a href="<?= site_url('contacts/' . $contact['id']) ?>" class="text-sm font-medium text-slate-900 hover:text-brand-700"><?= esc(ContactModel::fullName($contact)) ?></a>
                                        <p class="text-xs text-slate-500"><?= esc($contact['job_title'] ?? '-') ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3 text-xs text-slate-500 sm:text-right">
                                    <span><?= esc(format_hp($contact['phone'])) ?></span>
                                    <?php if ($link = wa_link($contact['whatsapp'] ?: $contact['phone'])): ?>
                                        <a href="<?= esc($link, 'attr') ?>" target="_blank" rel="noopener" class="rounded bg-green-50 px-1.5 py-0.5 font-medium text-green-700 hover:bg-green-100">WhatsApp</a>
                                    <?php endif ?>
                                </div>
                            </li>
                        <?php endforeach ?>
                    </ul>
                    <?php if ($canEdit): ?>
                        <div class="border-t border-slate-100 px-5 py-3">
                            <a href="<?= site_url('contacts/new?company=' . $company['id']) ?>" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800"><?= icon('plus', 'h-4 w-4') ?> Tambah kontak</a>
                        </div>
                    <?php endif ?>
                <?php endif ?>
            </div>

            <div x-show="tab === 'deals'" x-cloak>
                <?php if ($deals === []): ?>
                    <?= component('empty-state', [
                        'icon'        => 'banknotes',
                        'title'       => 'Belum ada deal',
                        'message'     => 'Peluang penjualan untuk perusahaan ini akan tampil di sini.',
                        'actionUrl'   => $canEdit ? site_url('deals/new?company=' . $company['id']) : null,
                        'actionLabel' => 'Tambah Deal',
                    ]) ?>
                <?php else: ?>
                    <?= view('deals/_list', ['deals' => $deals], ['saveData' => false]) ?>
                    <?php if ($canEdit): ?>
                        <div class="border-t border-slate-100 px-5 py-3">
                            <a href="<?= site_url('deals/new?company=' . $company['id']) ?>" class="inline-flex items-center gap-1 text-sm font-medium text-brand-700 hover:text-brand-800"><?= icon('plus', 'h-4 w-4') ?> Tambah deal</a>
                        </div>
                    <?php endif ?>
                <?php endif ?>
            </div>

            <div x-show="tab === 'activities'" x-cloak>
                <?php if ($canEdit): ?>
                    <div class="flex justify-end border-b border-slate-100 px-5 py-3">
                        <?= component('activity-form', ['relatedType' => 'company', 'relatedId' => $company['id'], 'defaultType' => 'visit']) ?>
                    </div>
                <?php endif ?>
                <?= component('activity-timeline', [
                    'activities'   => $activities,
                    'related'      => $related,
                    'emptyMessage' => 'Aktivitas perusahaan ini, termasuk dari kontak dan deal-nya, akan tampil di sini.',
                ]) ?>
            </div>

            <div x-show="tab === 'notes'" x-cloak>
                <?php if ($canEdit): ?>
                    <div class="flex justify-end border-b border-slate-100 px-5 py-3">
                        <?= component('activity-form', ['relatedType' => 'company', 'relatedId' => $company['id'], 'defaultType' => 'note', 'label' => 'Tambah catatan', 'modalKey' => 'note']) ?>
                    </div>
                <?php endif ?>
                <?= component('activity-timeline', [
                    'activities'   => $notes,
                    'emptyTitle'   => 'Belum ada catatan',
                    'emptyMessage' => 'Simpan informasi penting tentang perusahaan ini, mis. kebiasaan pembayaran atau struktur organisasi.',
                ]) ?>
            </div>
        </div>

        <section class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="flex items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800">Tugas</h2>
                <?php if ($canEdit): ?>
                    <?= component('task-form', ['relatedType' => 'company', 'relatedId' => $company['id'], 'assignees' => $assignees]) ?>
                <?php endif ?>
            </div>
            <?= component('task-list', ['tasks' => $tasks, 'showAssignee' => $assignees !== []]) ?>
        </section>
    </div>

    <div class="self-start rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <h2 class="text-sm font-semibold text-slate-800">Informasi perusahaan</h2>
        <dl class="mt-4 space-y-3 text-sm">
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Industri</dt>
                <dd class="text-right text-slate-800"><?= esc($company['industry_name'] ?? '-') ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Telepon</dt>
                <dd class="text-right text-slate-800">
                    <?php if ($company['phone']): ?><a href="tel:<?= esc($company['phone'], 'attr') ?>" class="hover:text-brand-700"><?= esc(format_hp($company['phone'])) ?></a><?php else: ?>-<?php endif ?>
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Website</dt>
                <dd class="truncate text-right text-slate-800">
                    <?php if ($company['website']): ?><a href="<?= esc($company['website'], 'attr') ?>" target="_blank" rel="noopener noreferrer" class="text-brand-700 hover:underline"><?= esc(preg_replace('#^https?://#', '', $company['website'])) ?></a><?php else: ?>-<?php endif ?>
                </dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">NPWP</dt>
                <dd class="text-right text-slate-800"><?= esc(format_npwp($company['npwp'])) ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Karyawan</dt>
                <dd class="text-right text-slate-800"><?= $company['employee_count'] !== null ? angka($company['employee_count']) . ' orang' : '-' ?></dd>
            </div>
            <div>
                <dt class="text-slate-500">Alamat</dt>
                <dd class="mt-1 whitespace-pre-line text-slate-800"><?= esc(implode("\n", array_filter([$company['address'], implode(', ', array_filter([$company['city'], $company['province']]))]))) ?: '-' ?></dd>
            </div>
            <div class="flex justify-between gap-4 border-t border-slate-100 pt-3">
                <dt class="text-slate-500">Owner</dt>
                <dd class="text-right text-slate-800"><?= esc($company['owner_name'] ?? '-') ?></dd>
            </div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Dibuat</dt>
                <dd class="text-right text-slate-800"><?= tanggal_id($company['created_at']) ?></dd>
            </div>
        </dl>
    </div>
</div>
<?= $this->endSection() ?>
