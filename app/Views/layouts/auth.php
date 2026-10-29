<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-100">
<head>
    <?= $this->include('layouts/partials/head') ?>
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<div class="flex min-h-full">
    <div class="relative hidden w-0 flex-1 flex-col justify-between bg-gradient-to-br from-brand-700 via-brand-800 to-slate-900 p-12 text-white lg:flex">
        <div class="flex items-center gap-3">
            <?php if ($logo = app_logo_url()): ?>
                <span class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-lg bg-white p-1"><img src="<?= esc($logo, 'attr') ?>" alt="" class="max-h-8 max-w-8 object-contain"></span>
            <?php else: ?>
                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-white/15 text-sm font-bold">CRM</span>
            <?php endif ?>
            <span class="text-lg font-semibold"><?= esc(app_setting('company_name', 'Sistem CRM')) ?></span>
        </div>
        <div class="max-w-md">
            <h2 class="text-3xl font-semibold leading-tight">Semua lead, pelanggan, dan pipeline penjualan di satu tempat.</h2>
            <p class="mt-4 text-brand-100">Pantau follow-up harian, nilai pipeline, dan kinerja tim tanpa membuka Excel.</p>
        </div>
        <p class="text-sm text-brand-200">&copy; <?= date('Y') ?> <?= esc(app_setting('company_name', '')) ?></p>
    </div>

    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm">
            <?= $this->renderSection('content') ?>
        </div>
    </div>
</div>
</body>
</html>
