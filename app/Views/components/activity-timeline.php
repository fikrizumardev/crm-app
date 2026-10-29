<?php

use App\Models\ActivityModel;

/**
 * Timeline aktivitas.
 *
 * @var list<array<string, mixed>>                                      $activities
 * @var array<string, array{label: string, name: string, url: string}>|null $related     label record tertaut (opsional)
 * @var string|null                                                     $emptyTitle
 * @var string|null                                                     $emptyMessage
 */
$authUser = current_user();
?>
<?php if ($activities === []): ?>
    <?= component('empty-state', [
        'icon'    => 'clock',
        'title'   => $emptyTitle ?? 'Belum ada aktivitas',
        'message' => $emptyMessage ?? 'Catat telepon, meeting, WhatsApp, atau kunjungan agar riwayat follow-up tidak hilang.',
    ]) ?>
<?php else: ?>
    <ul role="list" class="px-5 py-4">
        <?php foreach ($activities as $index => $activity): ?>
            <?php
            $type      = ActivityModel::TYPES[$activity['type']] ?? ActivityModel::TYPES['note'];
            $link      = $related[$activity['related_type'] . ':' . $activity['related_id']] ?? null;
            $canDelete = $authUser !== null && ($authUser->isAdmin() || (int) $activity['user_id'] === $authUser->id);
            ?>
            <li class="relative pb-6 last:pb-0">
                <?php if ($index < count($activities) - 1): ?>
                    <span class="absolute left-4 top-9 -ml-px h-[calc(100%-2.25rem)] w-0.5 bg-slate-100" aria-hidden="true"></span>
                <?php endif ?>
                <div class="relative flex gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full ring-4 ring-white <?= $type['class'] ?>" title="<?= esc($type['label'], 'attr') ?>">
                        <?= icon($type['icon'], 'h-4 w-4') ?>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-3">
                            <p class="text-sm font-medium text-slate-900"><?= esc($activity['subject']) ?></p>
                            <time class="whitespace-nowrap text-xs text-slate-400" datetime="<?= esc($activity['activity_at'], 'attr') ?>" title="<?= esc(tanggal_id($activity['activity_at'], true), 'attr') ?>">
                                <?= esc(waktu_relatif($activity['activity_at'])) ?>
                            </time>
                        </div>
                        <?php if (! empty($activity['description'])): ?>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-600"><?= esc($activity['description']) ?></p>
                        <?php endif ?>
                        <p class="mt-1 flex flex-wrap items-center gap-x-2 text-xs text-slate-400">
                            <span><?= esc($type['label']) ?> · <?= esc($activity['user_name'] ?? '-') ?></span>
                            <?php if ($link !== null): ?>
                                <span>· <?= esc($link['label']) ?> <a href="<?= esc($link['url'], 'attr') ?>" class="text-brand-700 hover:underline"><?= esc($link['name']) ?></a></span>
                            <?php endif ?>
                            <?php if ($canDelete): ?>
                                <span>·</span>
                                <?= component('delete-button', [
                                    'action'  => site_url('activities/' . $activity['id'] . '/delete'),
                                    'title'   => 'Hapus aktivitas?',
                                    'message' => 'Aktivitas "' . $activity['subject'] . '" akan dihapus.',
                                    'label'   => 'Hapus aktivitas',
                                    'compact' => true,
                                ]) ?>
                            <?php endif ?>
                        </p>
                    </div>
                </div>
            </li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
