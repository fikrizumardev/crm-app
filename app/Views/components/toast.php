<?php
/**
 * Notifikasi singkat (toast). Dipicu lewat window.crm.toast(pesan, 'success'|'error').
 */
?>
<div x-data="{ toasts: [], add(detail) { const id = Date.now() + Math.random(); this.toasts.push({ id, ...detail }); setTimeout(() => this.remove(id), 4500); }, remove(id) { this.toasts = this.toasts.filter((t) => t.id !== id); } }"
     @toast.window="add($event.detail)"
     class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4 sm:inset-x-auto sm:right-4 sm:items-end" aria-live="polite">
    <template x-for="toast in toasts" :key="toast.id">
        <div x-transition class="pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-lg px-4 py-3 text-sm shadow-lg ring-1"
             :class="toast.type === 'error' ? 'bg-red-50 text-red-800 ring-red-200' : 'bg-white text-slate-800 ring-slate-200'">
            <span x-show="toast.type === 'error'"><?= icon('x-circle', 'h-5 w-5 text-red-500') ?></span>
            <span x-show="toast.type !== 'error'"><?= icon('check-circle', 'h-5 w-5 text-green-500') ?></span>
            <p class="flex-1" x-text="toast.message"></p>
            <button type="button" class="opacity-60 hover:opacity-100" @click="remove(toast.id)"><?= icon('x-mark', 'h-4 w-4') ?></button>
        </div>
    </template>
</div>
