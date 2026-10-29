<!DOCTYPE html>
<html lang="id" class="h-full bg-slate-50">
<head>
    <?= $this->include('layouts/partials/head') ?>
</head>
<body class="h-full font-sans text-slate-800 antialiased" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">

<!-- Sidebar mobile (drawer, < 768 px) -->
<div x-cloak x-show="sidebarOpen" class="relative z-40 md:hidden" role="dialog" aria-modal="true">
    <div x-show="sidebarOpen"
         x-transition:enter="transition-opacity ease-linear duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-linear duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
         class="fixed inset-0 bg-slate-900/60" @click="sidebarOpen = false"></div>

    <div class="fixed inset-y-0 left-0 flex w-full max-w-xs">
        <div x-show="sidebarOpen"
             x-transition:enter="transition ease-in-out duration-200 transform" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition ease-in-out duration-200 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="-translate-x-full"
             class="relative flex w-64 flex-col">
            <button type="button" class="absolute right-2 top-4 z-10 rounded-md p-2 text-slate-400 hover:bg-slate-800 hover:text-white" @click="sidebarOpen = false">
                <span class="sr-only">Tutup menu</span>
                <?= icon('x-mark', 'h-6 w-6') ?>
            </button>
            <?= $this->include('layouts/partials/sidebar') ?>
        </div>
    </div>
</div>

<!-- Sidebar desktop -->
<div class="hidden md:fixed md:inset-y-0 md:z-30 md:flex md:w-64 md:flex-col">
    <?= $this->include('layouts/partials/sidebar') ?>
</div>

<div class="flex min-h-full flex-col md:pl-64">
    <?= $this->include('layouts/partials/topbar') ?>

    <main class="min-w-0 flex-1 py-6">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <?= component('flash') ?>
            <?= $this->renderSection('content') ?>
        </div>
    </main>

    <footer class="border-t border-slate-200 bg-white py-4">
        <p class="mx-auto max-w-7xl px-4 text-xs text-slate-400 sm:px-6 lg:px-8">
            &copy; <?= date('Y') ?> <?= esc(app_setting('company_name', 'CRM')) ?> · Sistem CRM
        </p>
    </footer>
</div>

<?= component('modal') ?>
<?= component('toast') ?>
<?= $this->include('layouts/partials/scripts') ?>
<?= $this->renderSection('scripts') ?>
</body>
</html>
