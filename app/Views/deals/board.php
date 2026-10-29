<?php

use App\Entities\User;

/**
 * @var array<int, array{stage: array<string, mixed>, deals: list<array<string, mixed>>, count: int, sum: float}> $columns
 * @var array<int, string> $lostReasons
 * @var array<int, string> $owners
 * @var int|null           $ownerId
 * @var User               $user
 */
$today  = date('Y-m-d');
$config = [
    'baseUrl'      => site_url('deals'),
    'lostStageIds' => array_values(array_map('strval', array_keys(array_filter($columns, static fn ($c): bool => (int) $c['stage']['is_lost'] === 1)))),
    'totals'       => array_map(static fn ($c): array => ['count' => $c['count'], 'sum' => $c['sum']], $columns),
];

$actions = component('button', ['label' => 'Tampilan tabel', 'url' => site_url('deals'), 'icon' => 'banknotes', 'variant' => 'secondary'])
    . component('button', ['label' => 'Tambah Deal', 'url' => site_url('deals/new'), 'icon' => 'plus']);
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Pipeline',
    'subtitle' => 'Geser kartu untuk memindahkan deal antar tahap. Kolom Won dan Lost berisi deal yang ditutup 30 hari terakhir; tiap kolom menampilkan maksimal 50 kartu.',
    'actions'  => $actions,
]) ?>

<?php if ($owners !== []): ?>
    <form method="get" action="<?= site_url('deals/board') ?>" class="mb-4 flex flex-wrap items-center gap-2" x-data>
        <label for="board-owner" class="text-sm text-slate-600">Owner</label>
        <select id="board-owner" name="owner" @change="$el.form.submit()" class="rounded-md border-0 py-1.5 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
            <option value="">Semua owner</option>
            <?php foreach ($owners as $id => $name): ?>
                <option value="<?= $id ?>" <?= $ownerId === $id ? 'selected' : '' ?>><?= esc($name) ?></option>
            <?php endforeach ?>
        </select>
    </form>
<?php endif ?>

<div x-data="kanbanBoard(<?= esc(json_encode($config), 'attr') ?>)" class="-mx-4 sm:-mx-6 lg:-mx-8">
    <div class="flex gap-4 overflow-x-auto px-4 pb-4 sm:px-6 lg:px-8">
        <?php foreach ($columns as $stageId => $column): ?>
            <?php
            $stage   = $column['stage'];
            $isWon   = (int) $stage['is_won'] === 1;
            $isLost  = (int) $stage['is_lost'] === 1;
            $accent  = $isWon ? 'bg-green-500' : ($isLost ? 'bg-red-500' : 'bg-brand-500');
            ?>
            <section class="flex w-72 shrink-0 flex-col rounded-lg bg-slate-100">
                <header class="px-3 pb-2 pt-3">
                    <div class="flex items-center justify-between gap-2">
                        <h2 class="flex items-center gap-2 text-sm font-semibold text-slate-800">
                            <span class="h-2.5 w-2.5 rounded-full <?= $accent ?>"></span>
                            <?= esc($stage['name']) ?>
                            <span class="text-xs font-normal text-slate-500"><?= (int) $stage['probability'] ?>%</span>
                        </h2>
                        <span class="rounded-full bg-white px-2 py-0.5 text-xs font-medium text-slate-600" x-text="(totals['<?= $stageId ?>']?.count ?? 0).toLocaleString('id-ID')"><?= angka($column['count']) ?></span>
                    </div>
                    <p class="mt-1 text-sm font-semibold text-slate-700" x-text="crm.rupiah(totals['<?= $stageId ?>']?.sum ?? 0)">
                        <?= rupiah($column['sum']) ?>
                    </p>
                </header>
                <div class="flex min-h-[8rem] flex-1 flex-col gap-2 px-2 pb-3" data-stage-list data-stage-id="<?= $stageId ?>">
                    <?php foreach ($column['deals'] as $deal): ?>
                        <?php $overdue = ! $isWon && ! $isLost && $deal['expected_close_date'] && $deal['expected_close_date'] < $today ?>
                        <article class="cursor-grab rounded-md bg-white p-3 shadow-sm ring-1 ring-slate-200 hover:ring-brand-300 active:cursor-grabbing"
                                 data-id="<?= (int) $deal['id'] ?>" data-amount="<?= (float) $deal['amount'] ?>">
                            <a href="<?= site_url('deals/' . $deal['id']) ?>" class="block text-sm font-medium text-slate-900 hover:text-brand-700"><?= esc($deal['title']) ?></a>
                            <p class="mt-0.5 truncate text-xs text-slate-500"><?= esc($deal['company_name'] ?? '-') ?></p>
                            <div class="mt-2 flex items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-slate-800"><?= rupiah($deal['amount']) ?></span>
                                <?php if (! $user->isSales()): ?>
                                    <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 text-[10px] font-semibold text-slate-600" title="<?= esc($deal['owner_name'], 'attr') ?>">
                                        <?= esc(strtoupper(implode('', array_map(static fn ($w) => mb_substr($w, 0, 1), array_slice(explode(' ', (string) $deal['owner_name']), 0, 2))))) ?>
                                    </span>
                                <?php endif ?>
                            </div>
                            <p class="mt-1 flex items-center gap-1 text-xs <?= $overdue ? 'font-medium text-red-600' : 'text-slate-400' ?>">
                                <?= icon('calendar', 'h-3.5 w-3.5') ?>
                                <?= $isWon || $isLost ? 'Ditutup ' . tanggal_id($deal['closed_at']) : tanggal_id($deal['expected_close_date']) ?>
                            </p>
                        </article>
                    <?php endforeach ?>
                </div>
                <?php if ($column['count'] > count($column['deals'])): ?>
                    <a href="<?= esc(site_url('deals?stage=' . $stageId . ($ownerId !== null ? '&owner=' . $ownerId : '')), 'attr') ?>" class="mx-2 mb-3 rounded-md px-2 py-1.5 text-center text-xs font-medium text-brand-700 hover:bg-white">
                        +<?= angka($column['count'] - count($column['deals'])) ?> deal lainnya — lihat di tabel
                    </a>
                <?php endif ?>
            </section>
        <?php endforeach ?>
    </div>

    <!-- Modal alasan kalah -->
    <div x-cloak x-show="lostModal" class="relative z-50" role="dialog" aria-modal="true" aria-label="Alasan kalah">
        <div class="fixed inset-0 bg-slate-900/50"></div>
        <div class="fixed inset-0 z-10 flex items-end justify-center p-4 sm:items-center">
            <div class="w-full max-w-md overflow-hidden rounded-lg bg-white shadow-xl" @keydown.escape.window="cancelLost()">
                <div class="px-5 pb-4 pt-5">
                    <h3 class="text-base font-semibold text-slate-900">Kenapa deal ini kalah?</h3>
                    <p class="mt-1 text-sm text-slate-600">Alasan kalah wajib diisi agar laporan win/loss akurat.</p>
                    <select x-model="lostReason" class="mt-4 block w-full rounded-md border-0 py-2 text-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-brand-600">
                        <option value="">— Pilih alasan —</option>
                        <?php foreach ($lostReasons as $id => $name): ?>
                            <option value="<?= $id ?>"><?= esc($name) ?></option>
                        <?php endforeach ?>
                    </select>
                </div>
                <div class="flex justify-end gap-2 bg-slate-50 px-5 py-3">
                    <?= component('button', ['label' => 'Batal', 'variant' => 'secondary', 'type' => 'button', 'attrs' => ['@click' => 'cancelLost()']]) ?>
                    <button type="button" @click="confirmLost()" :disabled="!lostReason"
                            class="inline-flex items-center justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 disabled:opacity-50">
                        Pindahkan ke Lost
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js"></script>
<script <?= csp_script_nonce() ?>>
    document.addEventListener('alpine:init', () => {
        Alpine.data('kanbanBoard', (config) => ({
            totals: config.totals,
            lostModal: false,
            lostReason: '',
            pending: null,
            init() {
                this.$root.querySelectorAll('[data-stage-list]').forEach((list) => {
                    Sortable.create(list, {
                        group: 'deals',
                        animation: 150,
                        ghostClass: 'opacity-40',
                        filter: 'a',
                        preventOnFilter: false,
                        onEnd: (evt) => this.dropped(evt),
                    });
                });
            },
            // Total kolom dihitung server (kolom bisa berisi lebih banyak deal daripada kartu yang tampil).
            shift(item, fromId, toId) {
                const amount = Number(item.dataset.amount || 0);
                const from = this.totals[fromId];
                const to = this.totals[toId] ?? (this.totals[toId] = { count: 0, sum: 0 });
                from.count -= 1;
                from.sum -= amount;
                to.count += 1;
                to.sum += amount;
            },
            dropped(evt) {
                if (evt.from === evt.to) return;
                this.shift(evt.item, evt.from.dataset.stageId, evt.to.dataset.stageId);
                const move = { evt, dealId: evt.item.dataset.id, stageId: evt.to.dataset.stageId };
                if (config.lostStageIds.includes(move.stageId)) {
                    this.pending = move;
                    this.lostReason = '';
                    this.lostModal = true;
                    return;
                }
                this.send(move, null);
            },
            confirmLost() {
                const move = this.pending;
                this.lostModal = false;
                this.pending = null;
                this.send(move, this.lostReason);
            },
            cancelLost() {
                if (!this.pending) return;
                this.revert(this.pending.evt);
                this.lostModal = false;
                this.pending = null;
            },
            async send(move, lostReason) {
                const data = { stage_id: move.stageId };
                if (lostReason) data.lost_reason_id = lostReason;
                try {
                    const result = await window.crm.post(config.baseUrl + '/' + move.dealId + '/stage', data);
                    if (!result.ok) throw new Error(result.data.message || 'Gagal memindahkan deal.');
                    window.crm.toast(result.data.message);
                } catch (e) {
                    this.revert(move.evt);
                    window.crm.toast(e.message || 'Gagal memindahkan deal.', 'error');
                }
            },
            revert(evt) {
                evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
                this.shift(evt.item, evt.to.dataset.stageId, evt.from.dataset.stageId);
            },
        }));
    });
</script>
<?= $this->endSection() ?>
