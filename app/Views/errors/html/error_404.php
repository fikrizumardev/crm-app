<?php
/**
 * Halaman tidak ditemukan (404). Pesan teknis dari exception sengaja tidak ditampilkan.
 */
?>
<?= view('errors/html/_simple', [
    'code'    => '404',
    'heading' => 'Halaman tidak ditemukan',
    'message' => 'Halaman atau data yang Anda cari tidak ada, sudah dihapus, atau bukan milik Anda.',
], ['saveData' => false]) ?>
