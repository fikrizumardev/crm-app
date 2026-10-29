<?php

use App\Models\DealModel;

/**
 * Daftar deal ringkas untuk halaman detail perusahaan/kontak.
 *
 * @var list<array<string, mixed>> $deals
 */
?>
<ul role="list" class="divide-y divide-slate-100">
    <?php foreach ($deals as $deal): ?>
        <?php $open = ! $deal['is_won'] && ! $deal['is_lost'] ?>
        <li class="flex flex-col gap-1 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <a href="<?= site_url('deals/' . $deal['id']) ?>" class="text-sm font-medium text-slate-900 hover:text-brand-700"><?= esc($deal['title']) ?></a>
                <p class="text-xs text-slate-500">
                    <?= $open ? 'Perkiraan closing ' . tanggal_id($deal['expected_close_date']) : 'Ditutup ' . tanggal_id($deal['closed_at']) ?>
                    · <?= esc($deal['owner_name'] ?? '-') ?>
                </p>
            </div>
            <div class="flex items-center gap-3">
                <span class="whitespace-nowrap text-sm font-semibold text-slate-800"><?= rupiah($deal['amount']) ?></span>
                <?= component('badge', ['label' => $deal['stage_name'], 'color' => DealModel::stageColor($deal)]) ?>
            </div>
        </li>
    <?php endforeach ?>
</ul>
