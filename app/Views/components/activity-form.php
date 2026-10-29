<?php

use App\Models\ActivityModel;

/**
 * Tombol + modal "Catat aktivitas" untuk record lead/kontak/perusahaan/deal.
 *
 * @var string      $relatedType
 * @var int|string  $relatedId
 * @var string|null $defaultType jenis aktivitas awal (default call)
 * @var string|null $label       teks tombol
 * @var string|null $modalKey    pembeda bila ada lebih dari satu form di halaman (activity|note)
 */
$modalKey ??= 'activity';
$useOld  = session()->getFlashdata('open_modal') === $modalKey;
$errors  = $useOld ? form_errors() : [];
$old     = static fn (string $key, string $default = ''): string => $useOld ? (string) old($key, $default, false) : $default;
$type    = $old('type', $defaultType ?? 'call');
$input   = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
?>
<div x-data="{ open: <?= $useOld ? 'true' : 'false' ?>, type: <?= esc(json_encode($type), 'attr') ?> }" @keydown.escape.window="open = false">
    <?= component('button', ['label' => $label ?? 'Catat aktivitas', 'icon' => 'plus', 'variant' => 'secondary', 'type' => 'button', 'attrs' => ['@click' => 'open = true']]) ?>

    <div x-cloak x-show="open" class="relative z-50" role="dialog" aria-modal="true" aria-label="Catat aktivitas">
        <div x-show="open" x-transition.opacity class="fixed inset-0 bg-slate-900/50"></div>
        <div class="fixed inset-0 z-10 overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
                <form x-show="open" x-transition @click.outside="open = false" action="<?= site_url('activities') ?>" method="post"
                      class="w-full max-w-lg overflow-hidden rounded-lg bg-white text-left shadow-xl">
                    <?= csrf_field() ?>
                    <input type="hidden" name="related_type" value="<?= esc($relatedType, 'attr') ?>">
                    <input type="hidden" name="related_id" value="<?= (int) $relatedId ?>">
                    <input type="hidden" name="type" :value="type">
                    <input type="hidden" name="_modal" value="<?= esc($modalKey, 'attr') ?>">

                    <div class="flex items-center justify-between border-b border-slate-200 px-5 py-3">
                        <h3 class="text-base font-semibold text-slate-900">Catat aktivitas</h3>
                        <button type="button" class="rounded p-1 text-slate-400 hover:text-slate-600" @click="open = false"><?= icon('x-mark', 'h-5 w-5') ?></button>
                    </div>

                    <div class="space-y-4 p-5">
                        <div>
                            <p class="mb-2 text-sm font-medium text-slate-700">Jenis</p>
                            <div class="grid grid-cols-3 gap-2 sm:grid-cols-6">
                                <?php foreach (ActivityModel::TYPES as $value => $meta): ?>
                                    <button type="button" @click="type = '<?= $value ?>'"
                                            :class="type === '<?= $value ?>' ? 'ring-2 ring-brand-600 bg-brand-50 text-brand-800' : 'ring-1 ring-slate-200 text-slate-600 hover:bg-slate-50'"
                                            class="flex flex-col items-center gap-1 rounded-md px-2 py-2 text-xs font-medium">
                                        <?= icon($meta['icon'], 'h-5 w-5') ?>
                                        <?= esc($meta['label']) ?>
                                    </button>
                                <?php endforeach ?>
                            </div>
                            <?php if (isset($errors['type'])): ?><p class="mt-1 text-xs text-red-600"><?= esc($errors['type']) ?></p><?php endif ?>
                        </div>
                        <div>
                            <label for="activity-subject" class="block text-sm font-medium text-slate-700">Ringkasan <span class="text-red-500">*</span></label>
                            <input id="activity-subject" type="text" name="subject" required maxlength="200" value="<?= esc($old('subject'), 'attr') ?>"
                                   placeholder="mis. Meeting demo, klien minta revisi harga" class="mt-1 <?= $input ?>">
                            <?php if (isset($errors['subject'])): ?><p class="mt-1 text-xs text-red-600"><?= esc($errors['subject']) ?></p><?php endif ?>
                        </div>
                        <div>
                            <label for="activity-description" class="block text-sm font-medium text-slate-700">Detail</label>
                            <textarea id="activity-description" name="description" rows="3" maxlength="5000" class="mt-1 <?= $input ?>"><?= esc($old('description')) ?></textarea>
                        </div>
                        <div>
                            <label for="activity-at" class="block text-sm font-medium text-slate-700">Waktu</label>
                            <input id="activity-at" type="datetime-local" name="activity_at" value="<?= esc($old('activity_at', date('Y-m-d\TH:i')), 'attr') ?>" class="mt-1 <?= $input ?> sm:w-64">
                            <?php if (isset($errors['activity_at'])): ?><p class="mt-1 text-xs text-red-600"><?= esc($errors['activity_at']) ?></p><?php endif ?>
                        </div>
                        <?php if (isset($errors['related_id'])): ?><p class="text-xs text-red-600"><?= esc($errors['related_id']) ?></p><?php endif ?>
                    </div>

                    <div class="flex justify-end gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                        <?= component('button', ['label' => 'Batal', 'variant' => 'secondary', 'type' => 'button', 'attrs' => ['@click' => 'open = false']]) ?>
                        <?= component('button', ['label' => 'Simpan aktivitas']) ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
