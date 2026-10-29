<?php
/**
 * Halaman akses ditolak (403).
 *
 * @var string|null $message
 */
?>
<?= view('errors/html/_simple', [
    'code'    => '403',
    'heading' => 'Akses ditolak',
    'message' => $message ?? 'Anda tidak memiliki akses ke halaman ini.',
], ['saveData' => false]) ?>
