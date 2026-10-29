<?php

use CodeIgniter\Pager\PagerRenderer;

/**
 * Template pager CI4 bergaya Tailwind (Config\Pager::$templates['tailwind']).
 *
 * @var PagerRenderer $pager
 */
$pager->setSurroundCount(2);
$total = $pager->getTotal() ?? 0;
?>
<nav class="flex flex-col items-center justify-between gap-3 sm:flex-row" aria-label="Navigasi halaman">
    <p class="text-sm text-slate-500">
        <?php if ($total > 0): ?>
            Menampilkan <span class="font-medium text-slate-700"><?= angka($pager->getPerPageStart()) ?></span>–<span class="font-medium text-slate-700"><?= angka($pager->getPerPageEnd()) ?></span>
            dari <span class="font-medium text-slate-700"><?= angka($total) ?></span> data
        <?php else: ?>
            Tidak ada data
        <?php endif ?>
    </p>

    <?php if ($pager->getPageCount() > 1): ?>
        <ul class="isolate inline-flex -space-x-px rounded-md shadow-sm">
            <li>
                <?php if ($pager->hasPreviousPage()): ?>
                    <a href="<?= $pager->getPreviousPage() ?>" class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-500 ring-1 ring-inset ring-slate-300 hover:bg-slate-50" aria-label="Sebelumnya">
                        <?= icon('chevron-left', 'h-4 w-4') ?>
                    </a>
                <?php else: ?>
                    <span class="relative inline-flex items-center rounded-l-md px-2 py-2 text-slate-300 ring-1 ring-inset ring-slate-200"><?= icon('chevron-left', 'h-4 w-4') ?></span>
                <?php endif ?>
            </li>

            <?php if ($pager->hasPrevious()): ?>
                <li><a href="<?= $pager->getFirst() ?>" class="relative hidden items-center px-3 py-2 text-sm text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:inline-flex">1</a></li>
                <li><span class="relative hidden items-center px-3 py-2 text-sm text-slate-400 ring-1 ring-inset ring-slate-300 sm:inline-flex">…</span></li>
            <?php endif ?>

            <?php foreach ($pager->links() as $link): ?>
                <li>
                    <?php if ($link['active']): ?>
                        <span aria-current="page" class="relative z-10 inline-flex items-center bg-brand-600 px-3 py-2 text-sm font-semibold text-white ring-1 ring-inset ring-brand-600"><?= $link['title'] ?></span>
                    <?php else: ?>
                        <a href="<?= $link['uri'] ?>" class="relative inline-flex items-center px-3 py-2 text-sm text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50"><?= $link['title'] ?></a>
                    <?php endif ?>
                </li>
            <?php endforeach ?>

            <?php if ($pager->hasNext()): ?>
                <li><span class="relative hidden items-center px-3 py-2 text-sm text-slate-400 ring-1 ring-inset ring-slate-300 sm:inline-flex">…</span></li>
                <li><a href="<?= $pager->getLast() ?>" class="relative hidden items-center px-3 py-2 text-sm text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 sm:inline-flex"><?= $pager->getPageCount() ?></a></li>
            <?php endif ?>

            <li>
                <?php if ($pager->hasNextPage()): ?>
                    <a href="<?= $pager->getNextPage() ?>" class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-500 ring-1 ring-inset ring-slate-300 hover:bg-slate-50" aria-label="Berikutnya">
                        <?= icon('chevron-right', 'h-4 w-4') ?>
                    </a>
                <?php else: ?>
                    <span class="relative inline-flex items-center rounded-r-md px-2 py-2 text-slate-300 ring-1 ring-inset ring-slate-200"><?= icon('chevron-right', 'h-4 w-4') ?></span>
                <?php endif ?>
            </li>
        </ul>
    <?php endif ?>
</nav>
