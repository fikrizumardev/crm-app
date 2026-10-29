<?php
/**
 * @var array<string, mixed>      $lead
 * @var array<string, mixed>|null $existingCompany
 * @var string                    $firstName
 * @var string                    $lastName
 * @var array<int, string>        $industries
 * @var array<string, string>     $provinces
 * @var array<int, string>        $stages
 */
$mode       = old('company_mode', $existingCompany ? 'existing' : 'new', false);
$createDeal = old('company_mode', null, false) === null ? true : old('create_deal', '', false) === '1';
$company    = $lead['company_name'] ?: $lead['name'];
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => 'Konversi Lead',
    'subtitle'    => 'Buat perusahaan, kontak, dan (opsional) deal dari lead "' . $lead['name'] . '" sekaligus.',
    'breadcrumbs' => [
        ['label' => 'Lead', 'url' => site_url('leads')],
        ['label' => $lead['name'], 'url' => site_url('leads/' . $lead['id'])],
        ['label' => 'Konversi'],
    ],
]) ?>

<form action="<?= site_url('leads/' . $lead['id'] . '/convert') ?>" method="post"
      x-data="{ mode: <?= esc(json_encode($mode), 'attr') ?>, createDeal: <?= $createDeal ? 'true' : 'false' ?> }"
      class="space-y-6">
    <?= csrf_field() ?>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">1</span>
            <h2 class="text-sm font-semibold text-slate-800">Perusahaan</h2>
        </div>
        <div class="space-y-5 p-5">
            <fieldset class="flex flex-col gap-3 sm:flex-row sm:gap-6">
                <legend class="sr-only">Pilihan perusahaan</legend>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name="company_mode" value="new" x-model="mode" class="text-brand-600 focus:ring-brand-600">
                    Buat perusahaan baru
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="radio" name="company_mode" value="existing" x-model="mode" class="text-brand-600 focus:ring-brand-600">
                    Pilih perusahaan yang sudah ada
                </label>
            </fieldset>

            <div x-show="mode === 'new'" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <?= component('form-field', ['name' => 'company_name', 'label' => 'Nama perusahaan', 'value' => $lead['company_name'] ?? '', 'required' => false, 'class' => 'sm:col-span-2']) ?>
                <?= component('form-field', ['name' => 'company_industry_id', 'label' => 'Industri', 'type' => 'select', 'options' => $industries, 'empty' => '— Pilih industri —']) ?>
                <?= component('form-field', ['name' => 'company_city', 'label' => 'Kota']) ?>
                <?= component('form-field', ['name' => 'company_province', 'label' => 'Provinsi', 'type' => 'select', 'options' => $provinces, 'empty' => '— Pilih provinsi —']) ?>
            </div>

            <div x-show="mode === 'existing'" x-cloak class="max-w-xl">
                <?= component('company-select', [
                    'name'          => 'company_id',
                    'label'         => 'Perusahaan',
                    'selectedId'    => $existingCompany['id'] ?? '',
                    'selectedLabel' => $existingCompany['name'] ?? '',
                    'help'          => $existingCompany ? 'Perusahaan dengan nama yang sama sudah terdaftar dan dipilih otomatis.' : null,
                ]) ?>
            </div>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center gap-3 border-b border-slate-200 px-5 py-3">
            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">2</span>
            <h2 class="text-sm font-semibold text-slate-800">Kontak</h2>
        </div>
        <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
            <?= component('form-field', ['name' => 'contact_first_name', 'label' => 'Nama depan', 'value' => $firstName, 'required' => true]) ?>
            <?= component('form-field', ['name' => 'contact_last_name', 'label' => 'Nama belakang', 'value' => $lastName]) ?>
            <?= component('form-field', ['name' => 'contact_job_title', 'label' => 'Jabatan', 'value' => $lead['job_title'] ?? '']) ?>
            <?= component('form-field', ['name' => 'contact_phone', 'label' => 'No. HP / WhatsApp', 'type' => 'tel', 'value' => $lead['phone'] ?? '']) ?>
            <?= component('form-field', ['name' => 'contact_email', 'label' => 'Email', 'type' => 'email', 'value' => $lead['email'] ?? '']) ?>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
            <div class="flex items-center gap-3">
                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700">3</span>
                <h2 class="text-sm font-semibold text-slate-800">Deal <span class="font-normal text-slate-500">(opsional)</span></h2>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" name="create_deal" value="1" x-model="createDeal" class="rounded text-brand-600 focus:ring-brand-600">
                Buat deal untuk peluang ini
            </label>
        </div>
        <div x-show="createDeal" class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
            <?= component('form-field', ['name' => 'deal_title', 'label' => 'Judul deal', 'value' => 'Peluang ' . $company, 'class' => 'sm:col-span-2']) ?>
            <?= component('form-field', ['name' => 'deal_amount', 'label' => 'Nilai deal (Rp)', 'placeholder' => 'mis. 150.000.000', 'attrs' => ['inputmode' => 'numeric']]) ?>
            <?= component('form-field', ['name' => 'deal_stage_id', 'label' => 'Tahap', 'type' => 'select', 'options' => $stages, 'value' => array_key_first($stages)]) ?>
            <?= component('form-field', ['name' => 'deal_expected_close_date', 'label' => 'Perkiraan closing', 'type' => 'date', 'value' => date('Y-m-d', strtotime('last day of next month'))]) ?>
        </div>
        <p x-show="!createDeal" x-cloak class="px-5 py-4 text-sm text-slate-500">Deal bisa dibuat nanti dari halaman perusahaan.</p>
    </section>

    <div class="flex justify-end gap-2">
        <?= component('button', ['label' => 'Batal', 'url' => site_url('leads/' . $lead['id']), 'variant' => 'secondary']) ?>
        <?= component('button', ['label' => 'Konversi lead', 'icon' => 'arrows-right-left']) ?>
    </div>
</form>
<?= $this->endSection() ?>
