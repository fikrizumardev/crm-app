<?php
/**
 * Tampilan saat data kosong.
 *
 * @var string      $title
 * @var string|null $message
 * @var string|null $icon
 * @var string|null $actionUrl
 * @var string|null $actionLabel
 */
?>
<div class="px-6 py-12 text-center">
    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-slate-100">
        <?= icon($icon ?? 'inbox', 'h-6 w-6 text-slate-400') ?>
    </div>
    <h3 class="mt-3 text-sm font-semibold text-slate-900"><?= esc($title) ?></h3>
    <?php if (! empty($message)): ?>
        <p class="mx-auto mt-1 max-w-sm text-sm text-slate-500"><?= esc($message) ?></p>
    <?php endif ?>
    <?php if (! empty($actionUrl)): ?>
        <a href="<?= esc($actionUrl, 'attr') ?>" class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-brand-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-brand-500">
            <?= icon('plus', 'h-4 w-4') ?>
            <?= esc($actionLabel ?? 'Tambah') ?>
        </a>
    <?php endif ?>
</div>
