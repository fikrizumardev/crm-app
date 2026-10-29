<?php
/**
 * Pembungkus tabel data: header (bisa diurutkan), baris, dan footer paginasi.
 *
 * @var list<array{label: string, sort?: string, class?: string, html?: string}> $columns  `html` = isi header mentah (mis. checkbox)
 * @var string      $rows   HTML <tr> hasil render baris
 * @var string|null $empty  HTML empty-state bila $rows kosong
 * @var string|null $footer HTML di bawah tabel (mis. paginasi)
 */
$request    = service('request');
$activeSort = $request->getGet('sort');
$activeDir  = $request->getGet('dir') === 'desc' ? 'desc' : 'asc';
$hasRows    = trim($rows) !== '';
?>
<div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
    <?php if ($hasRows): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50">
                    <tr>
                        <?php foreach ($columns as $column): ?>
                            <th scope="col" class="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-slate-500 <?= $column['class'] ?? '' ?>">
                                <?php if (isset($column['html'])): ?>
                                    <?= $column['html'] ?>
                                <?php elseif (! empty($column['sort'])): ?>
                                    <a href="<?= esc(sort_url($column['sort']), 'attr') ?>" class="group inline-flex items-center gap-1 hover:text-slate-800">
                                        <?= esc($column['label']) ?>
                                        <?php if ($activeSort === $column['sort']): ?>
                                            <?= icon($activeDir === 'asc' ? 'chevron-up' : 'chevron-down', 'h-3.5 w-3.5 text-slate-700') ?>
                                        <?php else: ?>
                                            <?= icon('chevron-up-down', 'h-3.5 w-3.5 text-slate-300 group-hover:text-slate-500') ?>
                                        <?php endif ?>
                                    </a>
                                <?php else: ?>
                                    <?= esc($column['label']) ?>
                                <?php endif ?>
                            </th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 bg-white">
                    <?= $rows ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <?= $empty ?? '' ?>
    <?php endif ?>

    <?php if (! empty($footer)): ?>
        <div class="border-t border-slate-200 bg-white px-4 py-3"><?= $footer ?></div>
    <?php endif ?>
</div>
