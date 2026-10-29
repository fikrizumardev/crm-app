<?php
/**
 * Bar aksi "alihkan owner" untuk baris terpilih. Dipakai di dalam elemen x-data="{ selected: [] }"
 * dengan checkbox baris `data-row-check` (lihat components/table).
 *
 * @var string             $path   leads|deals|companies|contacts
 * @var array<int, string> $owners pilihan owner baru
 */
$returnTo = current_url() . (service('request')->getUri()->getQuery() !== '' ? '?' . service('request')->getUri()->getQuery() : '');
?>
<div x-show="selected.length > 0" x-cloak x-transition
     class="mb-3 flex flex-col gap-3 rounded-lg border border-brand-200 bg-brand-50 px-4 py-3 text-sm text-brand-900 sm:flex-row sm:items-center">
    <p><span class="font-semibold" x-text="selected.length"></span> data dipilih</p>
    <form method="post" action="<?= site_url($path . '/reassign') ?>" class="flex flex-wrap items-center gap-2 sm:ml-auto">
        <?= csrf_field() ?>
        <input type="hidden" name="return_to" value="<?= esc($returnTo, 'attr') ?>">
        <template x-for="id in selected" :key="id">
            <input type="hidden" name="ids[]" :value="id">
        </template>
        <label for="bulk-owner" class="text-brand-800">Alihkan ke</label>
        <select id="bulk-owner" name="owner_id" required class="rounded-md border-0 py-1.5 text-sm ring-1 ring-inset ring-brand-200 focus:ring-2 focus:ring-brand-600">
            <option value="">— Pilih owner —</option>
            <?php foreach ($owners as $id => $name): ?>
                <option value="<?= $id ?>"><?= esc($name) ?></option>
            <?php endforeach ?>
        </select>
        <?= component('button', ['label' => 'Alihkan', 'icon' => 'arrows-right-left']) ?>
        <?= component('button', ['label' => 'Batal', 'type' => 'button', 'variant' => 'ghost', 'attrs' => ['@click' => 'selected = []']]) ?>
    </form>
</div>
