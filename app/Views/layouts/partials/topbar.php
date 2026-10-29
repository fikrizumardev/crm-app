<?php

use App\Models\NotificationModel;

$authUser    = current_user();
$unreadCount = model(NotificationModel::class)->unreadCount($authUser->id);
?>
<div class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-x-4 border-b border-slate-200 bg-white/95 px-4 shadow-sm backdrop-blur sm:px-6 lg:px-8">
    <button type="button" class="-m-2.5 p-2.5 text-slate-600 md:hidden" @click="sidebarOpen = true">
        <span class="sr-only">Buka menu</span>
        <?= icon('bars-3', 'h-6 w-6') ?>
    </button>

    <div class="hidden text-sm text-slate-500 sm:block"><?= tanggal_id(date('Y-m-d')) ?></div>

    <div class="ml-auto flex items-center gap-x-2 sm:gap-x-4">
        <div class="relative" x-data="notificationBell(<?= esc(json_encode(['unread' => $unreadCount, 'listUrl' => site_url('notifications'), 'readAllUrl' => site_url('notifications/read-all')]), 'attr') ?>)" @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" class="relative rounded-full p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-700" @click="toggle()" :aria-expanded="open" title="Notifikasi">
                <span class="sr-only">Notifikasi</span>
                <?= icon('bell', 'h-6 w-6') ?>
                <span x-show="unread > 0" x-cloak x-text="unread > 99 ? '99+' : unread"
                      class="absolute right-1 top-1 flex h-4 min-w-[1rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"></span>
            </button>
            <div x-cloak x-show="open" x-transition.origin.top.right
                 class="absolute right-0 z-30 mt-2 w-80 origin-top-right overflow-hidden rounded-md bg-white shadow-lg ring-1 ring-slate-900/5 sm:w-96">
                <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2">
                    <p class="text-sm font-semibold text-slate-800">Notifikasi</p>
                    <button type="button" x-show="unread > 0" @click="readAll()" class="text-xs font-medium text-brand-700 hover:text-brand-800">Tandai semua dibaca</button>
                </div>
                <div class="max-h-96 overflow-y-auto">
                    <p x-show="loading" class="px-4 py-6 text-center text-sm text-slate-400">Memuat…</p>
                    <p x-show="!loading && items.length === 0" class="px-4 py-6 text-center text-sm text-slate-500">Belum ada notifikasi.</p>
                    <template x-for="item in items" :key="item.id">
                        <a :href="item.url" class="flex gap-3 border-b border-slate-50 px-4 py-3 hover:bg-slate-50" :class="item.read ? '' : 'bg-brand-50/50'">
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="item.read ? 'bg-transparent' : 'bg-brand-500'"></span>
                            <span class="min-w-0">
                                <span class="block text-sm text-slate-700" x-text="item.message"></span>
                                <span class="mt-0.5 block text-xs text-slate-400" x-text="item.time"></span>
                            </span>
                        </a>
                    </template>
                </div>
            </div>
        </div>

        <div class="h-6 w-px bg-slate-200" aria-hidden="true"></div>

        <div class="relative" x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false">
            <button type="button" class="flex items-center gap-x-3 rounded-md p-1 hover:bg-slate-50" @click="open = !open" :aria-expanded="open" aria-haspopup="true">
                <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-700"><?= esc($authUser->initials()) ?></span>
                <span class="hidden text-left lg:block">
                    <span class="block text-sm font-semibold text-slate-800"><?= esc($authUser->name) ?></span>
                    <span class="block text-xs text-slate-500"><?= esc($authUser->roleLabel()) ?></span>
                </span>
                <?= icon('chevron-down', 'hidden h-4 w-4 text-slate-400 lg:block') ?>
            </button>

            <div x-cloak x-show="open" x-transition.origin.top.right
                 class="absolute right-0 z-30 mt-2 w-56 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-slate-900/5">
                <div class="border-b border-slate-100 px-4 py-2">
                    <p class="truncate text-sm font-medium text-slate-800"><?= esc($authUser->name) ?></p>
                    <p class="truncate text-xs text-slate-500">@<?= esc($authUser->username) ?></p>
                </div>
                <a href="<?= site_url('profile') ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    <?= icon('user-circle', 'h-4 w-4 text-slate-400') ?> Profil saya
                </a>
                <a href="<?= site_url('profile/password') ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                    <?= icon('key', 'h-4 w-4 text-slate-400') ?> Ganti password
                </a>
                <form action="<?= site_url('logout') ?>" method="post" class="border-t border-slate-100">
                    <?= csrf_field() ?>
                    <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                        <?= icon('arrow-right-start-on-rectangle', 'h-4 w-4') ?> Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
