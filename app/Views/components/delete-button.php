<?php
/**
 * Tombol yang membuka modal konfirmasi hapus.
 *
 * @var string      $action  URL POST hapus
 * @var string|null $title
 * @var string|null $message
 * @var string|null $label   teks tombol (default "Hapus")
 * @var bool|null   $compact tampilkan ikon saja
 */
$payload = [
    'action'  => $action,
    'title'   => $title ?? 'Hapus data?',
    'message' => $message ?? 'Data yang dihapus tidak akan tampil lagi di daftar.',
];
?>
<?php if (! empty($compact)): ?>
    <button type="button" x-data title="<?= esc($label ?? 'Hapus', 'attr') ?>"
            class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600"
            @click="$dispatch('confirm-delete', <?= esc(json_encode($payload), 'attr') ?>)">
        <span class="sr-only"><?= esc($label ?? 'Hapus') ?></span>
        <?= icon('trash', 'h-4 w-4') ?>
    </button>
<?php else: ?>
    <button type="button" x-data
            class="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-red-600 shadow-sm ring-1 ring-inset ring-red-200 hover:bg-red-50"
            @click="$dispatch('confirm-delete', <?= esc(json_encode($payload), 'attr') ?>)">
        <?= icon('trash', 'h-4 w-4') ?>
        <?= esc($label ?? 'Hapus') ?>
    </button>
<?php endif ?>
