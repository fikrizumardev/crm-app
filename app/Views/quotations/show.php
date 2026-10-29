<?php

use App\Models\QuotationModel;

/**
 * @var array<string, mixed>       $quotation
 * @var list<array<string, mixed>> $items
 * @var bool                       $canEdit
 * @var bool                       $canDelete
 */
$status  = QuotationModel::STATUSES[$quotation['status']];
$isDraft = $quotation['status'] === QuotationModel::STATUS_DRAFT;
$expired = in_array($quotation['status'], [QuotationModel::STATUS_DRAFT, QuotationModel::STATUS_SENT], true) && $quotation['valid_until'] < date('Y-m-d');

$actions = component('button', ['label' => 'Cetak / PDF', 'url' => site_url('quotations/' . $quotation['id'] . '/print'), 'icon' => 'document-arrow-down', 'variant' => 'secondary', 'attrs' => ['target' => '_blank', 'rel' => 'noopener']]);

if ($canEdit && $isDraft) {
    $actions .= component('button', ['label' => 'Ubah', 'url' => site_url('quotations/' . $quotation['id'] . '/edit'), 'icon' => 'pencil-square', 'variant' => 'secondary']);
}

if ($canDelete) {
    $actions .= component('delete-button', [
        'action'  => site_url('quotations/' . $quotation['id'] . '/delete'),
        'title'   => 'Hapus penawaran?',
        'message' => 'Penawaran ' . $quotation['number'] . ' akan dihapus. Nomornya tidak akan dipakai ulang.',
    ]);
}

// Aksi status yang relevan untuk status saat ini.
$transitions = match ($quotation['status']) {
    QuotationModel::STATUS_DRAFT => [QuotationModel::STATUS_SENT => 'Tandai terkirim'],
    QuotationModel::STATUS_SENT  => [QuotationModel::STATUS_ACCEPTED => 'Diterima klien', QuotationModel::STATUS_REJECTED => 'Ditolak klien', QuotationModel::STATUS_DRAFT => 'Kembali ke draft'],
    default                      => [QuotationModel::STATUS_SENT => 'Buka kembali'],
};
?>
<?= $this->extend('layouts/main') ?>

<?= $this->section('content') ?>
<?= component('page-header', [
    'title'       => 'Penawaran ' . $quotation['number'],
    'subtitle'    => $quotation['deal_title'] . ($quotation['company_name'] ? ' · ' . $quotation['company_name'] : ''),
    'actions'     => $actions,
    'breadcrumbs' => [
        ['label' => 'Deal', 'url' => site_url('deals')],
        ['label' => $quotation['deal_title'], 'url' => site_url('deals/' . $quotation['deal_id'])],
        ['label' => $quotation['number']],
    ],
]) ?>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm lg:col-span-2">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-xs font-semibold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-4 py-3 text-left">Item</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Harga</th>
                        <th class="px-4 py-3 text-right">Diskon</th>
                        <th class="px-4 py-3 text-right">Jumlah</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-slate-900"><?= esc($item['description']) ?></p>
                                <?php if ($item['sku']): ?><p class="font-mono text-xs text-slate-400"><?= esc($item['sku']) ?></p><?php endif ?>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-slate-700"><?= esc(str_replace('.', ',', (string) (float) $item['qty'])) ?> <?= esc($item['unit'] ?? '') ?></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-slate-700"><?= rupiah($item['unit_price']) ?></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right text-slate-700"><?= (float) $item['discount_pct'] > 0 ? persen((float) $item['discount_pct']) : '—' ?></td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium text-slate-900"><?= rupiah($item['line_total']) ?></td>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <dl class="ml-auto w-full space-y-1.5 border-t border-slate-200 p-5 text-sm sm:w-80">
            <div class="flex justify-between"><dt class="text-slate-500">Subtotal</dt><dd><?= rupiah($quotation['subtotal']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">Diskon</dt><dd>−<?= rupiah($quotation['discount_total']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-slate-500">PPN <?= persen((float) $quotation['tax_rate']) ?></dt><dd><?= rupiah($quotation['tax_amount']) ?></dd></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base font-semibold"><dt>Total</dt><dd><?= rupiah($quotation['grand_total']) ?></dd></div>
        </dl>
        <?php if ($quotation['notes']): ?>
            <div class="border-t border-slate-200 px-5 py-4 text-sm">
                <p class="font-medium text-slate-700">Catatan & syarat</p>
                <p class="mt-1 whitespace-pre-line text-slate-600"><?= esc($quotation['notes']) ?></p>
            </div>
        <?php endif ?>
    </section>

    <section class="self-start rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
        <dl class="space-y-3 text-sm">
            <div class="flex items-center justify-between gap-4">
                <dt class="text-slate-500">Status</dt>
                <dd><?= component('badge', ['label' => $status['label'], 'color' => $status['color']]) ?></dd>
            </div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Tanggal</dt><dd><?= tanggal_id($quotation['issue_date']) ?></dd></div>
            <div class="flex justify-between gap-4">
                <dt class="text-slate-500">Berlaku sampai</dt>
                <dd class="<?= $expired ? 'font-semibold text-red-600' : '' ?>"><?= tanggal_id($quotation['valid_until']) ?><?= $expired ? ' (kedaluwarsa)' : '' ?></dd>
            </div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Owner</dt><dd><?= esc($quotation['owner_name'] ?? '-') ?></dd></div>
            <div class="flex justify-between gap-4"><dt class="text-slate-500">Dibuat</dt><dd><?= tanggal_id($quotation['created_at'], true) ?></dd></div>
        </dl>

        <?php if ($canEdit): ?>
            <div class="mt-5 space-y-2 border-t border-slate-100 pt-4">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Ubah status</p>
                <?php foreach ($transitions as $value => $label): ?>
                    <form method="post" action="<?= site_url('quotations/' . $quotation['id'] . '/status') ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status" value="<?= $value ?>">
                        <button type="submit" class="w-full rounded-md px-3 py-2 text-sm font-semibold shadow-sm <?= $value === QuotationModel::STATUS_ACCEPTED ? 'bg-green-600 text-white hover:bg-green-500' : ($value === QuotationModel::STATUS_REJECTED ? 'bg-white text-red-600 ring-1 ring-inset ring-red-200 hover:bg-red-50' : 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50') ?>">
                            <?= esc($label) ?>
                        </button>
                    </form>
                <?php endforeach ?>
                <?php if ($quotation['status'] === QuotationModel::STATUS_ACCEPTED): ?>
                    <p class="text-xs text-slate-500">Penawaran diterima? Jangan lupa pindahkan deal ke tahap Won.</p>
                <?php endif ?>
            </div>
        <?php endif ?>
    </section>
</div>
<?= $this->endSection() ?>
