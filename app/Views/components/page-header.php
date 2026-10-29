<?php
/**
 * Judul halaman + subjudul + tombol aksi + breadcrumb opsional.
 *
 * @var string                                  $title
 * @var string|null                             $subtitle
 * @var string|null                             $actions HTML tombol aksi
 * @var list<array{label: string, url?: string}>|null $breadcrumbs
 */
?>
<div class="mb-6">
    <?php if (! empty($breadcrumbs)): ?>
        <nav class="mb-2 flex items-center gap-1 text-sm text-slate-500" aria-label="Breadcrumb">
            <?php foreach ($breadcrumbs as $i => $crumb): ?>
                <?php if ($i > 0): ?><?= icon('chevron-right', 'h-3.5 w-3.5 text-slate-400') ?><?php endif ?>
                <?php if (! empty($crumb['url'])): ?>
                    <a href="<?= esc($crumb['url'], 'attr') ?>" class="hover:text-slate-700"><?= esc($crumb['label']) ?></a>
                <?php else: ?>
                    <span class="text-slate-700"><?= esc($crumb['label']) ?></span>
                <?php endif ?>
            <?php endforeach ?>
        </nav>
    <?php endif ?>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="truncate text-2xl font-semibold text-slate-900"><?= esc($title) ?></h1>
            <?php if (! empty($subtitle)): ?>
                <p class="mt-1 text-sm text-slate-500"><?= esc($subtitle) ?></p>
            <?php endif ?>
        </div>
        <?php if (! empty($actions)): ?>
            <div class="flex flex-wrap items-center gap-2"><?= $actions ?></div>
        <?php endif ?>
    </div>
</div>
