<?php
/**
 * Template halaman error sederhana (tanpa layout aplikasi agar aman dirender kapan pun).
 *
 * @var string $code
 * @var string $heading
 * @var string $message
 */
?>
<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($heading) ?></title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
</head>
<body class="flex h-full items-center justify-center px-6 font-sans antialiased">
    <main class="text-center">
        <p class="text-base font-semibold text-blue-600"><?= esc($code) ?></p>
        <h1 class="mt-2 text-3xl font-semibold tracking-tight text-slate-900"><?= esc($heading) ?></h1>
        <p class="mx-auto mt-4 max-w-md text-sm text-slate-600"><?= esc($message) ?></p>
        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="<?= site_url('/') ?>" class="rounded-md bg-blue-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-blue-500">Kembali ke dashboard</a>
            <a href="javascript:history.back()" class="rounded-md px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">Halaman sebelumnya</a>
        </div>
    </main>
</body>
</html>
