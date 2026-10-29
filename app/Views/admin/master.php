<?php
/**
 * @var array<string, array{label: string, items: list<array<string, mixed>>}> $lists
 * @var list<array<string, mixed>>                                             $stages
 */
$input = 'block w-full rounded-md border-0 py-1.5 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'    => 'Master Data',
    'subtitle' => 'Pilihan yang dipakai di seluruh aplikasi. Data yang masih dipakai tidak bisa dihapus.',
]) ?>

<!-- Tahap pipeline -->
<section id="stages" class="mb-6 rounded-lg border border-slate-200 bg-white shadow-sm" x-data="stageOrder(<?= esc(json_encode(['url' => site_url('admin/master/stages/reorder')]), 'attr') ?>)">
    <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-800">Tahap pipeline</h2>
            <p class="text-xs text-slate-500">Geser <span class="font-medium">⠿</span> untuk mengubah urutan tahap terbuka. Won dan Lost selalu di akhir.</p>
        </div>
    </div>
    <ul class="divide-y divide-slate-100" x-ref="list">
        <?php foreach ($stages as $stage): ?>
            <?php $system = (int) $stage['is_won'] === 1 || (int) $stage['is_lost'] === 1 ?>
            <li class="flex flex-wrap items-center gap-3 px-5 py-2.5" <?= $system ? '' : 'data-stage="' . (int) $stage['id'] . '"' ?>>
                <span class="w-5 text-center text-slate-400 <?= $system ? 'opacity-30' : 'cursor-grab' ?>" <?= $system ? '' : 'data-handle' ?> title="<?= $system ? 'Tahap sistem' : 'Geser untuk mengurutkan' ?>">⠿</span>
                <form method="post" action="<?= site_url('admin/master/stages/' . $stage['id']) ?>" class="flex flex-1 flex-wrap items-center gap-2">
                    <?= csrf_field() ?>
                    <input type="text" name="name" value="<?= esc($stage['name'], 'attr') ?>" required maxlength="50" aria-label="Nama tahap" class="<?= $input ?> w-48">
                    <div class="flex items-center gap-1">
                        <input type="number" name="probability" value="<?= (int) $stage['probability'] ?>" min="0" max="100" aria-label="Probabilitas" class="<?= $input ?> w-20" <?= $system ? 'readonly' : '' ?>>
                        <span class="text-sm text-slate-500">%</span>
                    </div>
                    <?php if ((int) $stage['is_won'] === 1): ?>
                        <?= component('badge', ['label' => 'Won', 'color' => 'green']) ?>
                    <?php elseif ((int) $stage['is_lost'] === 1): ?>
                        <?= component('badge', ['label' => 'Lost', 'color' => 'red']) ?>
                    <?php endif ?>
                    <span class="text-xs text-slate-400"><?= angka($stage['usage']) ?> deal</span>
                    <button type="submit" class="ml-auto rounded-md px-2.5 py-1.5 text-xs font-semibold text-brand-700 hover:bg-brand-50">Simpan</button>
                </form>
                <?php if (! $system): ?>
                    <?= component('delete-button', [
                        'action'  => site_url('admin/master/stages/' . $stage['id'] . '/delete'),
                        'title'   => 'Hapus tahap?',
                        'message' => 'Tahap "' . $stage['name'] . '" akan dihapus. Tahap yang masih dipakai deal tidak bisa dihapus.',
                        'compact' => true,
                    ]) ?>
                <?php else: ?>
                    <span class="w-7"></span>
                <?php endif ?>
            </li>
        <?php endforeach ?>
    </ul>
    <form method="post" action="<?= site_url('admin/master/stages') ?>" class="flex flex-wrap items-center gap-2 border-t border-slate-100 bg-slate-50 px-5 py-3">
        <?= csrf_field() ?>
        <span class="w-5"></span>
        <input type="text" name="name" required maxlength="50" placeholder="Tahap baru, mis. Demo" aria-label="Nama tahap baru" class="<?= $input ?> w-48">
        <div class="flex items-center gap-1">
            <input type="number" name="probability" value="50" min="0" max="100" aria-label="Probabilitas tahap baru" class="<?= $input ?> w-20">
            <span class="text-sm text-slate-500">%</span>
        </div>
        <?= component('button', ['label' => 'Tambah tahap', 'icon' => 'plus', 'variant' => 'secondary']) ?>
    </form>
</section>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <?php foreach ($lists as $type => $list): ?>
        <section id="<?= $type ?>" class="self-start rounded-lg border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-200 px-5 py-3">
                <h2 class="text-sm font-semibold text-slate-800"><?= esc($list['label']) ?></h2>
            </div>
            <ul class="divide-y divide-slate-100">
                <?php foreach ($list['items'] as $item): ?>
                    <li class="flex items-center gap-2 px-4 py-2" x-data="{ editing: false }">
                        <template x-if="!editing">
                            <div class="flex flex-1 items-center gap-2">
                                <span class="flex-1 text-sm text-slate-800"><?= esc($item['name']) ?></span>
                                <span class="text-xs text-slate-400"><?= angka($item['usage']) ?>×</span>
                                <button type="button" @click="editing = true" class="rounded p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" title="Ubah"><?= icon('pencil-square', 'h-4 w-4') ?></button>
                            </div>
                        </template>
                        <template x-if="editing">
                            <form method="post" action="<?= site_url('admin/master/' . $type . '/' . $item['id']) ?>" class="flex flex-1 items-center gap-2">
                                <?= csrf_field() ?>
                                <input type="text" name="name" value="<?= esc($item['name'], 'attr') ?>" required maxlength="100" aria-label="Nama" class="<?= $input ?> flex-1">
                                <button type="submit" class="rounded-md px-2 py-1 text-xs font-semibold text-brand-700 hover:bg-brand-50">Simpan</button>
                                <button type="button" @click="editing = false" class="rounded-md px-2 py-1 text-xs text-slate-500 hover:bg-slate-100">Batal</button>
                            </form>
                        </template>
                        <?php if ((int) $item['usage'] === 0): ?>
                            <?= component('delete-button', [
                                'action'  => site_url('admin/master/' . $type . '/' . $item['id'] . '/delete'),
                                'title'   => 'Hapus ' . strtolower($list['label']) . '?',
                                'message' => '"' . $item['name'] . '" akan dihapus permanen.',
                                'compact' => true,
                            ]) ?>
                        <?php else: ?>
                            <span class="w-7" title="Masih dipakai"></span>
                        <?php endif ?>
                    </li>
                <?php endforeach ?>
            </ul>
            <form method="post" action="<?= site_url('admin/master/' . $type) ?>" class="flex items-center gap-2 border-t border-slate-100 bg-slate-50 px-4 py-3">
                <?= csrf_field() ?>
                <input type="text" name="name" required maxlength="100" placeholder="Tambah <?= esc(strtolower($list['label']), 'attr') ?>…" aria-label="Nama baru" class="<?= $input ?> flex-1">
                <?= component('button', ['label' => 'Tambah', 'variant' => 'secondary']) ?>
            </form>
        </section>
    <?php endforeach ?>
</div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.7/Sortable.min.js"></script>
<script <?= csp_script_nonce() ?>>
    document.addEventListener('alpine:init', () => {
        Alpine.data('stageOrder', (config) => ({
            init() {
                Sortable.create(this.$refs.list, {
                    handle: '[data-handle]',
                    draggable: '[data-stage]',
                    animation: 150,
                    onEnd: (evt) => {
                        if (evt.oldIndex === evt.newIndex) return;
                        this.save([...this.$refs.list.querySelectorAll('[data-stage]')].map((el) => el.dataset.stage));
                    },
                });
            },
            async save(order) {
                const body = {};
                order.forEach((id, i) => { body['order[' + i + ']'] = id; });
                const result = await window.crm.post(config.url, body);
                window.crm.toast(result.data.message || 'Gagal menyimpan urutan.', result.ok ? 'success' : 'error');
                if (!result.ok) setTimeout(() => window.location.reload(), 1200);
            },
        }));
    });
</script>
<?= $this->endSection() ?>
