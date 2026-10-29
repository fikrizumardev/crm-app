<?php
/**
 * Badge status/tahap.
 *
 * @var string      $label
 * @var string|null $color gray|blue|indigo|green|yellow|orange|red|purple|teal
 */
$colors = [
    'gray'   => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    'blue'   => 'bg-blue-50 text-blue-700 ring-blue-600/20',
    'indigo' => 'bg-indigo-50 text-indigo-700 ring-indigo-600/20',
    'green'  => 'bg-green-50 text-green-700 ring-green-600/20',
    'yellow' => 'bg-yellow-50 text-yellow-800 ring-yellow-600/20',
    'orange' => 'bg-orange-50 text-orange-700 ring-orange-600/20',
    'red'    => 'bg-red-50 text-red-700 ring-red-600/20',
    'purple' => 'bg-purple-50 text-purple-700 ring-purple-600/20',
    'teal'   => 'bg-teal-50 text-teal-700 ring-teal-600/20',
];
$classes = $colors[$color ?? 'gray'] ?? $colors['gray'];
?>
<span class="inline-flex items-center whitespace-nowrap rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset <?= $classes ?>"><?= esc($label) ?></span>
