<?php
/**
 * @var array<string, mixed>       $deal
 * @var list<array<string, mixed>> $stages
 * @var array<int, string>         $lostReasons
 * @var array<int, string>         $contacts
 * @var array<int, string>         $owners
 * @var string                     $title
 */
$isEdit   = isset($deal['id']);
$stageMap = [];

foreach ($stages as $stage) {
    $stageMap[$stage['id']] = ['probability' => (int) $stage['probability'], 'won' => (int) $stage['is_won'] === 1, 'lost' => (int) $stage['is_lost'] === 1];
}

$errors   = form_errors();
$stageId  = (string) old('stage_id', (string) ($deal['stage_id'] ?? ''), false);
$contacts = array_map(static fn ($id, $name) => ['id' => (string) $id, 'name' => $name], array_keys($contacts), $contacts);
$alpine   = [
    'stages'      => $stageMap,
    'stageId'     => $stageId,
    'probability' => (string) old('probability', (string) ($deal['probability'] ?? ''), false),
    'contacts'    => $contacts,
    'contactId'   => (string) old('contact_id', (string) ($deal['contact_id'] ?? ''), false),
    'contactsUrl' => site_url('contacts/options'),
];
$input = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset focus:ring-2 focus:ring-inset focus:ring-brand-600';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $title,
    'breadcrumbs' => array_values(array_filter([
        ['label' => 'Deal', 'url' => site_url('deals')],
        $isEdit ? ['label' => $deal['title'], 'url' => site_url('deals/' . $deal['id'])] : null,
        ['label' => $isEdit ? 'Ubah' : 'Tambah'],
    ])),
]) ?>

<form action="<?= site_url($isEdit ? 'deals/' . $deal['id'] : 'deals') ?>" method="post" class="rounded-lg border border-slate-200 bg-white shadow-sm"
      x-data="dealForm(<?= esc(json_encode($alpine), 'attr') ?>)" @company-selected.window="loadContacts($event.detail.id)">
    <?= csrf_field() ?>
    <div class="grid grid-cols-1 gap-5 p-5 sm:grid-cols-2">
        <?= component('form-field', ['name' => 'title', 'label' => 'Judul deal', 'value' => $deal['title'] ?? '', 'required' => true, 'class' => 'sm:col-span-2', 'placeholder' => 'mis. Implementasi ERP Gudang — 3 cabang', 'attrs' => ['maxlength' => '200']]) ?>

        <?= component('company-select', ['name' => 'company_id', 'label' => 'Perusahaan', 'selectedId' => $deal['company_id'] ?? '', 'selectedLabel' => $deal['company_name'] ?? '']) ?>

        <div>
            <label for="field-contact_id" class="block text-sm font-medium text-slate-700">Kontak utama</label>
            <select id="field-contact_id" name="contact_id" x-model="contactId" class="mt-1 <?= $input ?> <?= isset($errors['contact_id']) ? 'ring-red-300' : 'ring-slate-300' ?>">
                <option value="">— Pilih kontak —</option>
                <template x-for="contact in contacts" :key="contact.id">
                    <option :value="contact.id" x-text="contact.name" :selected="contact.id === contactId"></option>
                </template>
            </select>
            <?php if (isset($errors['contact_id'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= esc($errors['contact_id']) ?></p>
            <?php else: ?>
                <p class="mt-1 text-xs text-slate-500" x-text="contacts.length === 0 ? 'Pilih perusahaan terlebih dahulu.' : ''"></p>
            <?php endif ?>
        </div>

        <?= component('form-field', ['name' => 'amount', 'label' => 'Nilai deal (Rp)', 'value' => isset($deal['amount']) ? number_format((float) $deal['amount'], 0, ',', '.') : '', 'required' => true, 'placeholder' => 'mis. 150.000.000', 'attrs' => ['inputmode' => 'numeric']]) ?>
        <?= component('form-field', ['name' => 'expected_close_date', 'label' => 'Perkiraan closing', 'type' => 'date', 'value' => $deal['expected_close_date'] ?? '']) ?>

        <div>
            <label for="field-stage_id" class="block text-sm font-medium text-slate-700">Tahap <span class="text-red-500">*</span></label>
            <select id="field-stage_id" name="stage_id" x-model="stageId" @change="onStageChange()" class="mt-1 <?= $input ?> <?= isset($errors['stage_id']) ? 'ring-red-300' : 'ring-slate-300' ?>">
                <?php foreach ($stages as $stage): ?>
                    <option value="<?= $stage['id'] ?>" <?= (string) $stage['id'] === $stageId ? 'selected' : '' ?>><?= esc($stage['name']) ?> (<?= (int) $stage['probability'] ?>%)</option>
                <?php endforeach ?>
            </select>
            <?php if (isset($errors['stage_id'])): ?><p class="mt-1 text-xs text-red-600"><?= esc($errors['stage_id']) ?></p><?php endif ?>
        </div>
        <div>
            <label for="field-probability" class="block text-sm font-medium text-slate-700">Probabilitas (%)</label>
            <input id="field-probability" type="number" name="probability" min="0" max="100" x-model="probability" :readonly="isClosed()"
                   class="mt-1 <?= $input ?> read-only:bg-slate-50 <?= isset($errors['probability']) ? 'ring-red-300' : 'ring-slate-300' ?>">
            <?php if (isset($errors['probability'])): ?>
                <p class="mt-1 text-xs text-red-600"><?= esc($errors['probability']) ?></p>
            <?php else: ?>
                <p class="mt-1 text-xs text-slate-500">Default mengikuti tahap, boleh disesuaikan.</p>
            <?php endif ?>
        </div>

        <div x-show="isLost()" x-cloak class="sm:col-span-2">
            <?= component('form-field', ['name' => 'lost_reason_id', 'label' => 'Alasan kalah', 'type' => 'select', 'value' => $deal['lost_reason_id'] ?? '', 'options' => $lostReasons, 'empty' => '— Pilih alasan —', 'help' => 'Wajib diisi bila deal dipindahkan ke Lost.']) ?>
        </div>

        <?php if ($owners !== []): ?>
            <?= component('form-field', ['name' => 'owner_id', 'label' => 'Owner', 'type' => 'select', 'value' => $deal['owner_id'] ?? '', 'options' => $owners, 'required' => true]) ?>
        <?php endif ?>
    </div>
    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
        <?= component('button', ['label' => 'Batal', 'url' => site_url($isEdit ? 'deals/' . $deal['id'] : 'deals'), 'variant' => 'secondary']) ?>
        <?= component('button', ['label' => $isEdit ? 'Simpan perubahan' : 'Simpan deal']) ?>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce() ?>>
    document.addEventListener('alpine:init', () => {
        Alpine.data('dealForm', (config) => ({
            ...config,
            isLost() {
                return Boolean(this.stages[this.stageId]?.lost);
            },
            isClosed() {
                const stage = this.stages[this.stageId];
                return Boolean(stage && (stage.won || stage.lost));
            },
            onStageChange() {
                const stage = this.stages[this.stageId];
                if (stage) this.probability = String(stage.probability);
            },
            async loadContacts(companyId) {
                this.contactId = '';
                this.contacts = [];
                if (!companyId) return;
                const response = await fetch(this.contactsUrl + '?company=' + encodeURIComponent(companyId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (!response.ok) return;
                this.contacts = (await response.json()).map((c) => ({ id: String(c.id), name: c.name }));
                if (this.contacts.length === 1) this.contactId = this.contacts[0].id;
            },
        }));
    });
</script>
<?= $this->endSection() ?>
