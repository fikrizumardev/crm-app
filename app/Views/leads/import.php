<?php

use App\Models\LeadModel;
use App\Services\LeadImportService;

/**
 * @var array{imported: int, failed: list<array{line: int, name: string, errors: list<string>}>}|null $result
 * @var array<int, string> $sources
 */
$fileError = form_errors()['file'] ?? null;
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => 'Impor Lead',
    'subtitle'    => 'Tambahkan banyak lead sekaligus dari file CSV.',
    'breadcrumbs' => [['label' => 'Lead', 'url' => site_url('leads')], ['label' => 'Impor']],
]) ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <form action="<?= site_url('leads/import') ?>" method="post" enctype="multipart/form-data" class="rounded-lg border border-slate-200 bg-white shadow-sm lg:col-span-2">
        <?= csrf_field() ?>
        <div class="space-y-4 p-5">
            <ol class="list-decimal space-y-1 pl-5 text-sm text-slate-600">
                <li>Unduh <a href="<?= site_url('leads/import/template') ?>" class="font-medium text-brand-700 underline">template CSV</a>, lalu isi satu lead per baris.</li>
                <li>Simpan sebagai CSV (pemisah koma atau titik koma), maksimal <?= LeadImportService::MAX_ROWS ?> baris dan 2 MB.</li>
                <li>Unggah file di bawah. Baris yang tidak valid atau duplikat akan dilewati dan dilaporkan.</li>
            </ol>
            <div>
                <label for="field-file" class="block text-sm font-medium text-slate-700">File CSV <span class="text-red-500">*</span></label>
                <input id="field-file" type="file" name="file" accept=".csv,text/csv" required
                       class="mt-1 block w-full text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                <?php if ($fileError): ?><p class="mt-1 text-xs text-red-600"><?= esc($fileError) ?></p><?php endif ?>
            </div>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
            <?= component('button', ['label' => 'Unduh template', 'url' => site_url('leads/import/template'), 'icon' => 'document-arrow-down', 'variant' => 'secondary']) ?>
            <?= component('button', ['label' => 'Impor sekarang', 'icon' => 'arrow-up-tray']) ?>
        </div>
    </form>

    <div class="rounded-lg border border-slate-200 bg-white p-5 text-sm shadow-sm">
        <h2 class="font-semibold text-slate-800">Format kolom</h2>
        <dl class="mt-3 space-y-2 text-slate-600">
            <div><dt class="inline font-mono text-xs text-slate-800">nama</dt> <dd class="inline">— wajib diisi</dd></div>
            <div><dt class="inline font-mono text-xs text-slate-800">perusahaan, jabatan, no_hp, email, catatan</dt> <dd class="inline">— opsional</dd></div>
            <div><dt class="inline font-mono text-xs text-slate-800">sumber</dt> <dd class="inline">— salah satu: <?= esc(implode(', ', $sources)) ?></dd></div>
            <div><dt class="inline font-mono text-xs text-slate-800">status</dt> <dd class="inline">— <?= esc(implode(', ', array_slice(LeadModel::STATUSES, 0, 4))) ?> (kosong = Baru)</dd></div>
            <?php if (! has_role('sales')): ?>
                <div><dt class="inline font-mono text-xs text-slate-800">owner_username</dt> <dd class="inline">— username sales tim Anda (kosong = Anda)</dd></div>
            <?php endif ?>
        </dl>
    </div>
</div>

<?php if (is_array($result)): ?>
    <div class="mt-6 rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Hasil impor</h2>
            <?= component('badge', ['label' => $result['imported'] . ' berhasil', 'color' => 'green']) ?>
            <?= component('badge', ['label' => count($result['failed']) . ' gagal', 'color' => $result['failed'] === [] ? 'gray' : 'red']) ?>
        </div>
        <?php if ($result['failed'] !== []): ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm">
                    <thead class="bg-slate-50">
                        <tr>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Baris</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Nama</th>
                            <th class="px-4 py-2 text-left text-xs font-semibold uppercase text-slate-500">Kesalahan</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php foreach ($result['failed'] as $failure): ?>
                            <tr>
                                <td class="whitespace-nowrap px-4 py-2 text-slate-600"><?= (int) $failure['line'] ?></td>
                                <td class="px-4 py-2 text-slate-800"><?= esc($failure['name'] !== '' ? $failure['name'] : '-') ?></td>
                                <td class="px-4 py-2 text-red-700"><?= esc(implode(' ', $failure['errors'])) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="px-5 py-4 text-sm text-slate-600">Semua baris berhasil diimpor. <a href="<?= site_url('leads') ?>" class="font-medium text-brand-700 underline">Lihat daftar lead</a>.</p>
        <?php endif ?>
    </div>
<?php endif ?>
<?= $this->endSection() ?>
