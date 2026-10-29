<?php
/**
 * Modal konfirmasi global (sekali di layout). Dibuka lewat event Alpine:
 * $dispatch('confirm-delete', { action: '/leads/1/delete', title: '...', message: '...', confirm: 'Hapus' })
 * Lihat components/delete-button.
 */
?>
<div x-data="{ show: false, action: '', title: '', message: '', confirm: 'Hapus' }"
     @confirm-delete.window="action = $event.detail.action; title = $event.detail.title || 'Hapus data?'; message = $event.detail.message || 'Data yang dihapus tidak akan tampil lagi di daftar.'; confirm = $event.detail.confirm || 'Hapus'; show = true"
     @keydown.escape.window="show = false"
     x-cloak x-show="show" class="relative z-50" role="dialog" aria-modal="true" :aria-label="title">
    <div x-show="show" x-transition.opacity class="fixed inset-0 bg-slate-900/50"></div>

    <div class="fixed inset-0 z-10 overflow-y-auto">
        <div class="flex min-h-full items-end justify-center p-4 sm:items-center">
            <div x-show="show" x-transition @click.outside="show = false"
                 class="w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl">
                <div class="flex gap-4 px-5 pb-4 pt-5">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-red-100">
                        <?= icon('exclamation-triangle', 'h-6 w-6 text-red-600') ?>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-slate-900" x-text="title"></h3>
                        <p class="mt-1 text-sm text-slate-600" x-text="message"></p>
                    </div>
                </div>
                <form method="post" :action="action" class="flex flex-col-reverse gap-2 bg-slate-50 px-5 py-3 sm:flex-row sm:justify-end">
                    <?= csrf_field() ?>
                    <button type="button" class="rounded-md bg-white px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50" @click="show = false">Batal</button>
                    <button type="submit" class="rounded-md bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-500" x-text="confirm"></button>
                </form>
            </div>
        </div>
    </div>
</div>
