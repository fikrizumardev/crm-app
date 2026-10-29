<?php
/**
 * @var array<string, mixed>       $deal
 * @var array<string, mixed>       $quotation
 * @var list<array<string, mixed>> $items
 * @var list<array<string, mixed>> $products
 * @var string                     $title
 */
$isEdit = isset($quotation['id']);
$errors = form_errors();

// Item awal: input lama (setelah validasi gagal) → item tersimpan → satu baris kosong.
$oldItems = old('items', null, false);
$rows     = is_array($oldItems) ? array_values($oldItems) : array_map(static fn (array $item): array => [
    'product_id'   => (string) ($item['product_id'] ?? ''),
    'description'  => $item['description'],
    'qty'          => (string) (float) $item['qty'],
    'unit_price'   => (string) (float) $item['unit_price'],
    'discount_pct' => (string) (float) $item['discount_pct'],
], $items);

if ($rows === []) {
    $rows = [['product_id' => '', 'description' => '', 'qty' => '1', 'unit_price' => '', 'discount_pct' => '0']];
}

$config = [
    'products' => $products,
    'items'    => $rows,
    'taxRate'  => (float) str_replace(',', '.', (string) ($quotation['tax_rate'] ?? app_setting('tax_rate', '11'))),
];
$input = 'block w-full rounded-md border-0 px-2.5 py-1.5 text-sm text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-brand-600';
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => $title,
    'subtitle'    => 'Deal: ' . $deal['title'] . ($deal['company_name'] ? ' · ' . $deal['company_name'] : ''),
    'breadcrumbs' => array_values(array_filter([
        ['label' => 'Deal', 'url' => site_url('deals')],
        ['label' => $deal['title'], 'url' => site_url('deals/' . $deal['id'])],
        $isEdit ? ['label' => $quotation['number'], 'url' => site_url('quotations/' . $quotation['id'])] : null,
        ['label' => $isEdit ? 'Ubah' : 'Buat penawaran'],
    ])),
]) ?>

<?php if ($errors !== []): ?>
    <div class="mb-4 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        <p class="font-semibold">Periksa kembali isian penawaran:</p>
        <ul class="mt-1 list-disc pl-5">
            <?php foreach ($errors as $message): ?>
                <li><?= esc($message) ?></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php endif ?>

<form action="<?= site_url($isEdit ? 'quotations/' . $quotation['id'] : 'quotations') ?>" method="post" x-data="quotationForm(<?= esc(json_encode($config), 'attr') ?>)" class="space-y-6">
    <?= csrf_field() ?>
    <?php if (! $isEdit): ?>
        <input type="hidden" name="deal_id" value="<?= (int) $deal['id'] ?>">
    <?php endif ?>

    <section class="grid grid-cols-1 gap-5 rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-3">
        <?= component('form-field', ['name' => 'issue_date', 'label' => 'Tanggal penawaran', 'type' => 'date', 'value' => $quotation['issue_date'] ?? '', 'required' => true]) ?>
        <?= component('form-field', ['name' => 'valid_until', 'label' => 'Berlaku sampai', 'type' => 'date', 'value' => $quotation['valid_until'] ?? '', 'required' => true]) ?>
        <div class="text-sm">
            <p class="font-medium text-slate-700">Nomor</p>
            <p class="mt-2 font-mono text-slate-600"><?= esc($quotation['number'] ?? 'Dibuat otomatis saat disimpan') ?></p>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-3">
            <h2 class="text-sm font-semibold text-slate-800">Item penawaran</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[46rem] text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-3 py-2 text-left">Produk / deskripsi</th>
                        <th class="w-24 px-3 py-2 text-right">Qty</th>
                        <th class="w-40 px-3 py-2 text-right">Harga satuan</th>
                        <th class="w-24 px-3 py-2 text-right">Diskon %</th>
                        <th class="w-40 px-3 py-2 text-right">Jumlah</th>
                        <th class="w-10 px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <template x-for="(item, index) in items" :key="item.key">
                        <tr class="align-top">
                            <td class="min-w-[18rem] px-3 py-2">
                                <select :name="'items[' + index + '][product_id]'" x-model="item.product_id" @change="pickProduct(item)" aria-label="Produk" class="<?= $input ?> mb-1.5">
                                    <option value="">— Deskripsi bebas —</option>
                                    <template x-for="product in products" :key="product.id">
                                        <option :value="String(product.id)" x-text="product.sku + ' · ' + product.name" :selected="String(product.id) === item.product_id"></option>
                                    </template>
                                </select>
                                <input type="text" :name="'items[' + index + '][description]'" x-model="item.description" maxlength="255" placeholder="Deskripsi item" aria-label="Deskripsi" class="<?= $input ?>">
                            </td>
                            <td class="px-3 py-2"><input type="number" :name="'items[' + index + '][qty]'" x-model="item.qty" min="0.01" step="any" aria-label="Qty" class="<?= $input ?> text-right"></td>
                            <td class="px-3 py-2"><input type="number" :name="'items[' + index + '][unit_price]'" x-model="item.unit_price" min="0" step="any" aria-label="Harga satuan" class="<?= $input ?> text-right"></td>
                            <td class="px-3 py-2"><input type="number" :name="'items[' + index + '][discount_pct]'" x-model="item.discount_pct" min="0" max="100" step="any" aria-label="Diskon" class="<?= $input ?> text-right"></td>
                            <td class="whitespace-nowrap px-3 py-3 text-right font-medium text-slate-800" x-text="crm.rupiah(line(item))"></td>
                            <td class="px-3 py-2 text-right">
                                <button type="button" @click="remove(index)" :disabled="items.length === 1" class="rounded p-1.5 text-slate-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-30" title="Hapus baris"><?= icon('trash', 'h-4 w-4') ?></button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="flex flex-col gap-4 border-t border-slate-200 p-5 sm:flex-row sm:justify-between">
            <div>
                <?= component('button', ['label' => 'Tambah baris', 'icon' => 'plus', 'variant' => 'secondary', 'type' => 'button', 'attrs' => ['@click' => 'add()']]) ?>
            </div>
            <dl class="w-full space-y-1.5 text-sm sm:w-80">
                <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd class="text-slate-800" x-text="crm.rupiah(totals().subtotal)"></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Diskon</dt><dd class="text-slate-800" x-text="'−' + crm.rupiah(totals().discount)"></dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">PPN <span x-text="taxRate.toLocaleString('id-ID') + '%'"></span></dt><dd class="text-slate-800" x-text="crm.rupiah(totals().tax)"></dd></div>
                <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total</dt><dd x-text="crm.rupiah(totals().grand)"></dd></div>
                <p class="pt-1 text-xs text-slate-400">Total final dihitung ulang oleh sistem saat disimpan.</p>
            </dl>
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <?= component('form-field', ['name' => 'notes', 'label' => 'Catatan & syarat', 'type' => 'textarea', 'rows' => 3, 'value' => $quotation['notes'] ?? '', 'help' => 'Tampil di halaman cetak, mis. syarat pembayaran atau garansi.']) ?>
    </section>

    <div class="flex justify-end gap-2">
        <?= component('button', ['label' => 'Batal', 'url' => site_url($isEdit ? 'quotations/' . $quotation['id'] : 'deals/' . $deal['id']), 'variant' => 'secondary']) ?>
        <?= component('button', ['label' => $isEdit ? 'Simpan perubahan' : 'Simpan penawaran']) ?>
    </div>
</form>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script <?= csp_script_nonce() ?>>
    document.addEventListener('alpine:init', () => {
        let seq = 0;
        const blank = () => ({ key: ++seq, product_id: '', description: '', qty: '1', unit_price: '', discount_pct: '0' });
        const round2 = (n) => Math.round((n + Number.EPSILON) * 100) / 100;

        Alpine.data('quotationForm', (config) => ({
            products: config.products,
            taxRate: config.taxRate,
            items: config.items.map((item) => ({ ...blank(), ...item, product_id: String(item.product_id ?? '') })),
            add() {
                this.items.push(blank());
            },
            remove(index) {
                if (this.items.length > 1) this.items.splice(index, 1);
            },
            pickProduct(item) {
                const product = this.products.find((p) => String(p.id) === item.product_id);
                if (!product) return;
                item.description = product.name;
                item.unit_price = String(product.price);
            },
            // Rumus sama dengan QuotationService::calculate().
            line(item) {
                const gross = round2((Number(item.qty) || 0) * (Number(item.unit_price) || 0));
                return round2(gross * (1 - (Number(item.discount_pct) || 0) / 100));
            },
            totals() {
                let subtotal = 0;
                let net = 0;
                this.items.forEach((item) => {
                    subtotal += round2((Number(item.qty) || 0) * (Number(item.unit_price) || 0));
                    net += this.line(item);
                });
                subtotal = round2(subtotal);
                net = round2(net);
                const tax = round2(net * this.taxRate / 100);
                return { subtotal, discount: round2(subtotal - net), tax, grand: round2(net + tax) };
            },
        }));
    });
</script>
<?= $this->endSection() ?>
