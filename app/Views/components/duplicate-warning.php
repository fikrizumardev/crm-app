<?php
/**
 * Peringatan data duplikat (no. HP/email sama) dengan opsi tetap menyimpan.
 * Diletakkan di dalam <form>; tombol "Tetap simpan" mengirim confirm_duplicate=1.
 *
 * @var list<array{type: string, id: int, name: string, owner: string|null, match: string, url: string}> $duplicates
 */
?>
<?php if (! empty($duplicates)): ?>
    <div class="mb-5 rounded-lg border border-amber-200 bg-amber-50 p-4">
        <div class="flex gap-3">
            <?= icon('exclamation-triangle', 'h-5 w-5 shrink-0 text-amber-500') ?>
            <div class="flex-1 text-sm text-amber-800">
                <p class="font-semibold">Kemungkinan data duplikat</p>
                <p class="mt-1">No. HP atau email ini sudah tercatat:</p>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    <?php foreach ($duplicates as $duplicate): ?>
                        <li>
                            <?= esc($duplicate['type']) ?>
                            <a href="<?= esc($duplicate['url'], 'attr') ?>" class="font-medium underline" target="_blank" rel="noopener"><?= esc($duplicate['name']) ?></a>
                            — <?= esc($duplicate['match']) ?> sama<?php if (! empty($duplicate['owner'])): ?>, owner <?= esc($duplicate['owner']) ?><?php endif ?>
                        </li>
                    <?php endforeach ?>
                </ul>
                <div class="mt-3">
                    <button type="submit" name="confirm_duplicate" value="1" class="rounded-md bg-amber-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-500">Tetap simpan</button>
                    <span class="ml-2 text-xs">atau perbaiki data lalu simpan ulang.</span>
                </div>
            </div>
        </div>
    </div>
<?php endif ?>
