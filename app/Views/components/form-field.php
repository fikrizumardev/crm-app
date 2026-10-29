<?php
/**
 * Field form: label + input/select/textarea + pesan error.
 *
 * @var string                     $name
 * @var string                     $label
 * @var string|null                $type        text|email|tel|number|date|password|url|select|textarea
 * @var mixed                      $value       nilai awal (ditimpa old input setelah validasi gagal)
 * @var array<array-key, string>   $options     pilihan untuk select [value => label]
 * @var string|null                $placeholder
 * @var string|null                $empty       opsi kosong pertama untuk select, mis. "— Pilih —"
 * @var bool|null                  $required
 * @var bool|null                  $readonly
 * @var string|null                $help
 * @var string|null                $error       pesan error (default dari flash `errors`)
 * @var array<string, string>|null $attrs       atribut tambahan
 * @var string|null                $class       class pembungkus
 */
$type     ??= 'text';
$id         = 'field-' . str_replace(['[', ']'], ['-', ''], $name);
$current    = old($name, $value ?? '', false);
$error    ??= form_errors()[$name] ?? null;
$required ??= false;
$readonly ??= false;

$inputClass = 'block w-full rounded-md border-0 px-3 py-2 text-sm text-slate-900 shadow-sm ring-1 ring-inset placeholder:text-slate-400 focus:ring-2 focus:ring-inset disabled:cursor-not-allowed disabled:bg-slate-50 read-only:bg-slate-50 read-only:text-slate-500 '
    . ($error ? 'ring-red-300 focus:ring-red-500' : 'ring-slate-300 focus:ring-brand-600');

$extra = '';

foreach ($attrs ?? [] as $attr => $attrValue) {
    $extra .= ' ' . preg_replace('/[^@:a-zA-Z0-9_.\-]/', '', (string) $attr) . '="' . esc($attrValue, 'attr') . '"';
}

if ($required) {
    $extra .= ' required';
}

if ($readonly) {
    $extra .= ' readonly';
}

if ($error) {
    $extra .= ' aria-invalid="true" aria-describedby="' . $id . '-error"';
}
?>
<div class="<?= esc($class ?? '', 'attr') ?>">
    <label for="<?= $id ?>" class="block text-sm font-medium text-slate-700">
        <?= esc($label) ?><?php if ($required): ?> <span class="text-red-500">*</span><?php endif ?>
    </label>
    <div class="mt-1">
        <?php if ($type === 'select'): ?>
            <select id="<?= $id ?>" name="<?= esc($name, 'attr') ?>" class="<?= $inputClass ?> pr-8"<?= $extra ?>>
                <?php if (isset($empty)): ?>
                    <option value=""><?= esc($empty) ?></option>
                <?php endif ?>
                <?php foreach ($options ?? [] as $optionValue => $optionLabel): ?>
                    <option value="<?= esc((string) $optionValue, 'attr') ?>"<?= (string) $optionValue === (string) $current ? ' selected' : '' ?>><?= esc($optionLabel) ?></option>
                <?php endforeach ?>
            </select>
        <?php elseif ($type === 'textarea'): ?>
            <textarea id="<?= $id ?>" name="<?= esc($name, 'attr') ?>" rows="<?= (int) ($rows ?? 4) ?>" class="<?= $inputClass ?>" placeholder="<?= esc($placeholder ?? '', 'attr') ?>"<?= $extra ?>><?= esc((string) $current) ?></textarea>
        <?php else: ?>
            <input id="<?= $id ?>" type="<?= esc($type, 'attr') ?>" name="<?= esc($name, 'attr') ?>" value="<?= $type === 'password' ? '' : esc((string) $current, 'attr') ?>"
                   class="<?= $inputClass ?>" placeholder="<?= esc($placeholder ?? '', 'attr') ?>"<?= $extra ?>>
        <?php endif ?>
    </div>
    <?php if ($error): ?>
        <p id="<?= $id ?>-error" class="mt-1 text-xs text-red-600"><?= esc($error) ?></p>
    <?php elseif (! empty($help)): ?>
        <p class="mt-1 text-xs text-slate-500"><?= esc($help) ?></p>
    <?php endif ?>
</div>
