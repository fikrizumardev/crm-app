<?php
/**
 * Pilih perusahaan dengan pencarian (combobox Alpine, data dari GET /companies/search).
 *
 * @var string|null     $name          nama field (default company_id)
 * @var string|null     $label
 * @var int|string|null $selectedId
 * @var string|null     $selectedLabel
 * @var bool|null       $required
 * @var string|null     $help
 */
$name ??= 'company_id';
$error  = form_errors()[$name] ?? null;
$oldId  = old($name, null, false);

// Setelah validasi gagal, pakai pilihan terakhir user (ID + label dikirim bersama form).
$config = [
    'url'           => site_url('companies/search'),
    'selectedId'    => (string) ($oldId ?? $selectedId ?? ''),
    'selectedLabel' => (string) ($oldId !== null ? old($name . '_label', '', false) : ($selectedLabel ?? '')),
];
?>
<div x-data="companySelect(<?= esc(json_encode($config), 'attr') ?>)" class="relative" @click.outside="close()">
    <label for="field-<?= esc($name, 'attr') ?>-search" class="block text-sm font-medium text-slate-700">
        <?= esc($label ?? 'Perusahaan') ?><?php if (! empty($required)): ?> <span class="text-red-500">*</span><?php endif ?>
    </label>
    <input type="hidden" name="<?= esc($name, 'attr') ?>" :value="selectedId">
    <input type="hidden" name="<?= esc($name, 'attr') ?>_label" :value="selectedLabel">
    <div class="relative mt-1">
        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400"><?= icon('magnifying-glass', 'h-4 w-4') ?></span>
        <input id="field-<?= esc($name, 'attr') ?>-search" type="text" autocomplete="off" x-model="query"
               @input.debounce.300ms="search()" @focus="open = true; search()"
               @keydown.down.prevent="move(1)" @keydown.up.prevent="move(-1)" @keydown.enter.prevent="pick(results[active])" @keydown.escape="close()"
               placeholder="Ketik nama perusahaan…"
               class="block w-full rounded-md border-0 py-2 pl-9 pr-9 text-sm text-slate-900 shadow-sm ring-1 ring-inset <?= $error ? 'ring-red-300' : 'ring-slate-300' ?> placeholder:text-slate-400 focus:ring-2 focus:ring-inset focus:ring-brand-600">
        <button type="button" x-show="selectedId" x-cloak @click="clear()" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600" title="Hapus pilihan">
            <?= icon('x-mark', 'h-4 w-4') ?>
        </button>
    </div>
    <ul x-show="open" x-cloak class="absolute z-20 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-sm shadow-lg ring-1 ring-slate-900/5">
        <template x-for="(item, index) in results" :key="item.id">
            <li @click="pick(item)" @mouseenter="active = index" :class="index === active ? 'bg-brand-50 text-brand-800' : 'text-slate-700'" class="cursor-pointer px-3 py-2">
                <span x-text="item.name" class="font-medium"></span>
                <span x-show="item.city" x-text="'· ' + item.city" class="text-xs text-slate-400"></span>
            </li>
        </template>
        <li x-show="loading" class="px-3 py-2 text-slate-400">Mencari…</li>
        <li x-show="!loading && results.length === 0" class="px-3 py-2 text-slate-500">Perusahaan tidak ditemukan.</li>
    </ul>
    <?php if ($error): ?>
        <p class="mt-1 text-xs text-red-600"><?= esc($error) ?></p>
    <?php elseif (! empty($help)): ?>
        <p class="mt-1 text-xs text-slate-500"><?= esc($help) ?></p>
    <?php endif ?>
</div>
