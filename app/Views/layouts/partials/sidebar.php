<?php

use Config\Menu;

$authUser = current_user();
$sections = config(Menu::class)->forRole($authUser->role);
?>
<div class="flex grow flex-col gap-y-5 overflow-y-auto bg-slate-900 px-4 pb-4">
    <a href="<?= site_url('/') ?>" class="flex h-16 shrink-0 items-center gap-3 pl-2 pr-10 md:pr-2">
        <?php if ($logo = app_logo_url()): ?>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-white p-1"><img src="<?= esc($logo, 'attr') ?>" alt="" class="max-h-7 max-w-7 object-contain"></span>
        <?php else: ?>
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-sm font-bold text-white">CRM</span>
        <?php endif ?>
        <span class="min-w-0">
            <span class="block text-sm font-semibold text-white">Sistem CRM</span>
            <span class="block truncate text-xs text-slate-400"><?= esc(app_setting('company_name', '')) ?></span>
        </span>
    </a>

    <nav class="flex flex-1 flex-col" aria-label="Menu utama">
        <ul role="list" class="flex flex-1 flex-col gap-y-6">
            <?php foreach ($sections as $section): ?>
                <li>
                    <?php if ($section['title'] !== ''): ?>
                        <div class="px-2 text-xs font-semibold uppercase tracking-wider text-slate-500"><?= esc($section['title']) ?></div>
                    <?php endif ?>
                    <ul role="list" class="<?= $section['title'] !== '' ? 'mt-2 ' : '' ?>space-y-1">
                        <?php foreach ($section['items'] as $item): ?>
                            <li>
                                <?php if (! empty($item['soon'])): ?>
                                    <span class="flex cursor-not-allowed items-center gap-x-3 rounded-md px-2 py-2 text-sm text-slate-500" title="Segera hadir">
                                        <?= icon($item['icon'], 'h-5 w-5 shrink-0') ?>
                                        <?= esc($item['label']) ?>
                                        <span class="ml-auto rounded bg-slate-800 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-400">Segera</span>
                                    </span>
                                <?php elseif (array_filter((array) ($item['match'] ?? $item['url']), static fn (string $pattern): bool => url_is($pattern)) !== []): ?>
                                    <a href="<?= site_url($item['url']) ?>" class="flex items-center gap-x-3 rounded-md bg-slate-800 px-2 py-2 text-sm font-semibold text-white" aria-current="page">
                                        <?= icon($item['icon'], 'h-5 w-5 shrink-0 text-brand-400') ?>
                                        <?= esc($item['label']) ?>
                                    </a>
                                <?php else: ?>
                                    <a href="<?= site_url($item['url']) ?>" class="flex items-center gap-x-3 rounded-md px-2 py-2 text-sm font-medium text-slate-300 hover:bg-slate-800 hover:text-white">
                                        <?= icon($item['icon'], 'h-5 w-5 shrink-0 text-slate-400') ?>
                                        <?= esc($item['label']) ?>
                                    </a>
                                <?php endif ?>
                            </li>
                        <?php endforeach ?>
                    </ul>
                </li>
            <?php endforeach ?>

            <li class="mt-auto rounded-lg bg-slate-800/60 px-3 py-3">
                <p class="text-xs text-slate-400">Masuk sebagai</p>
                <p class="truncate text-sm font-medium text-white"><?= esc($authUser->name) ?></p>
                <p class="text-xs text-slate-400"><?= esc($authUser->roleLabel()) ?> · @<?= esc($authUser->username) ?></p>
            </li>
        </ul>
    </nav>
</div>
