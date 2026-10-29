<?php
/**
 * Flash message dari session: success, error, warning, info.
 */
$styles = [
    'success' => ['box' => 'border-green-200 bg-green-50 text-green-800', 'icon' => 'check-circle', 'iconClass' => 'text-green-500'],
    'error'   => ['box' => 'border-red-200 bg-red-50 text-red-800', 'icon' => 'x-circle', 'iconClass' => 'text-red-500'],
    'warning' => ['box' => 'border-amber-200 bg-amber-50 text-amber-800', 'icon' => 'exclamation-triangle', 'iconClass' => 'text-amber-500'],
    'info'    => ['box' => 'border-sky-200 bg-sky-50 text-sky-800', 'icon' => 'information-circle', 'iconClass' => 'text-sky-500'],
];
?>
<?php foreach ($styles as $key => $style): ?>
    <?php $message = session()->getFlashdata($key) ?>
    <?php if (is_string($message) && $message !== ''): ?>
        <div x-data="{ show: true }" x-show="show" x-transition.opacity role="alert"
             class="mb-4 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm <?= $style['box'] ?>">
            <?= icon($style['icon'], 'mt-0.5 h-5 w-5 shrink-0 ' . $style['iconClass']) ?>
            <p class="flex-1"><?= esc($message) ?></p>
            <button type="button" class="-m-1 rounded p-1 opacity-60 hover:opacity-100" @click="show = false">
                <span class="sr-only">Tutup</span>
                <?= icon('x-mark', 'h-4 w-4') ?>
            </button>
        </div>
    <?php endif ?>
<?php endforeach ?>
