<?php
/**
 * Tombol/tautan aksi dengan gaya konsisten.
 *
 * @var string                     $label
 * @var string|null                $url     bila diisi, dirender sebagai <a>
 * @var string|null                $icon
 * @var string|null                $variant primary|secondary|danger|ghost
 * @var string|null                $type    type tombol (default "submit")
 * @var array<string, string>|null $attrs
 */
$variants = [
    'primary'   => 'bg-brand-600 text-white shadow-sm hover:bg-brand-500',
    'secondary' => 'bg-white text-slate-700 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50',
    'danger'    => 'bg-red-600 text-white shadow-sm hover:bg-red-500',
    'ghost'     => 'text-slate-600 hover:bg-slate-100',
];
$classes = 'inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md px-3 py-2 text-sm font-semibold '
    . ($variants[$variant ?? 'primary'] ?? $variants['primary']);
$extra = '';

foreach ($attrs ?? [] as $attr => $attrValue) {
    $extra .= ' ' . preg_replace('/[^@:a-zA-Z0-9_.\-]/', '', (string) $attr) . '="' . esc($attrValue, 'attr') . '"';
}
?>
<?php if (! empty($url)): ?>
    <a href="<?= esc($url, 'attr') ?>" class="<?= $classes ?>"<?= $extra ?>>
        <?php if (! empty($icon)): ?><?= icon($icon, 'h-4 w-4') ?><?php endif ?>
        <?= esc($label) ?>
    </a>
<?php else: ?>
    <button type="<?= esc($type ?? 'submit', 'attr') ?>" class="<?= $classes ?>"<?= $extra ?>>
        <?php if (! empty($icon)): ?><?= icon($icon, 'h-4 w-4') ?><?php endif ?>
        <?= esc($label) ?>
    </button>
<?php endif ?>
