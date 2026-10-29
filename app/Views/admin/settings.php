<?php
/**
 * @var array<string, string|null> $settings
 */
$logo = app_logo_url();
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Pengaturan',
    'subtitle' => 'Identitas perusahaan yang tampil di aplikasi dan dokumen penawaran.',
]) ?>

<form action="<?= site_url('admin/settings') ?>" method="post" enctype="multipart/form-data" class="max-w-3xl rounded-lg border border-slate-200 bg-white shadow-sm">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
        <?= component('form-field', ['name' => 'company_name', 'label' => 'Nama perusahaan', 'value' => $settings['company_name'] ?? '', 'required' => true, 'class' => 'sm:col-span-2', 'attrs' => ['maxlength' => '150']]) ?>
        <?= component('form-field', ['name' => 'company_address', 'label' => 'Alamat', 'type' => 'textarea', 'rows' => 2, 'value' => $settings['company_address'] ?? '', 'class' => 'sm:col-span-2']) ?>
        <?= component('form-field', ['name' => 'company_phone', 'label' => 'Telepon', 'type' => 'tel', 'value' => $settings['company_phone'] ?? '']) ?>
        <div class="grid grid-cols-2 gap-4">
            <?= component('form-field', ['name' => 'currency', 'label' => 'Mata uang', 'value' => $settings['currency'] ?? 'IDR', 'required' => true, 'attrs' => ['maxlength' => '3']]) ?>
            <?= component('form-field', ['name' => 'tax_rate', 'label' => 'Tarif PPN (%)', 'value' => $settings['tax_rate'] ?? '11', 'required' => true, 'attrs' => ['inputmode' => 'decimal']]) ?>
        </div>

        <div class="sm:col-span-2" x-data="{ preview: <?= esc(json_encode($logo), 'attr') ?>, remove: false }">
            <p class="block text-sm font-medium text-slate-700">Logo</p>
            <div class="mt-2 flex flex-wrap items-center gap-4">
                <div class="flex h-16 w-32 items-center justify-center overflow-hidden rounded-md border border-dashed border-slate-300 bg-slate-50">
                    <template x-if="preview && !remove"><img :src="preview" alt="Logo perusahaan" class="max-h-14 max-w-[7.5rem] object-contain"></template>
                    <span x-show="!preview || remove" class="text-xs text-slate-400">Belum ada logo</span>
                </div>
                <div class="space-y-2">
                    <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"
                           @change="const f = $event.target.files[0]; if (f) { preview = URL.createObjectURL(f); remove = false; }"
                           class="block text-sm text-slate-700 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-brand-700 hover:file:bg-brand-100">
                    <p class="text-xs text-slate-500">PNG, JPG, atau WEBP, maks. 1 MB dan 1200×1200 px.</p>
                    <?php if ($logo !== null): ?>
                        <label class="flex items-center gap-2 text-xs text-slate-600">
                            <input type="checkbox" name="remove_logo" value="1" x-model="remove" class="rounded border-slate-300 text-red-600 focus:ring-red-600"> Hapus logo
                        </label>
                    <?php endif ?>
                    <?php if (isset(form_errors()['logo'])): ?><p class="text-xs text-red-600"><?= esc(form_errors()['logo']) ?></p><?php endif ?>
                </div>
            </div>
        </div>
    </div>
    <div class="flex justify-end border-t border-slate-200 bg-slate-50 px-5 py-3">
        <?= component('button', ['label' => 'Simpan pengaturan']) ?>
    </div>
</form>
<?= $this->endSection() ?>
