<?php

use App\Models\ContactModel;
use App\Models\QuotationModel;

/**
 * Halaman cetak penawaran. Mandiri (tanpa layout/CDN) agar hasil cetak konsisten;
 * simpan sebagai PDF lewat dialog print browser.
 *
 * @var array<string, mixed>       $quotation
 * @var list<array<string, mixed>> $items
 * @var array<string, mixed>       $deal
 * @var array<string, mixed>|null  $company
 * @var array<string, mixed>|null  $contact
 * @var array<string, string|null> $settings
 */
$logo        = app_logo_url();
$companyName = $settings['company_name'] ?? 'Perusahaan';
$isDraft     = $quotation['status'] === QuotationModel::STATUS_DRAFT;
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Penawaran <?= esc($quotation['number']) ?> · <?= esc($companyName) ?></title>
    <style>
        @page { size: A4; margin: 16mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #f1f5f9; color: #0f172a; font: 13px/1.5 "Segoe UI", Roboto, Arial, sans-serif; }
        .toolbar { display: flex; gap: 8px; justify-content: center; padding: 16px; }
        .toolbar button, .toolbar a { border: 1px solid #cbd5e1; background: #fff; color: #334155; padding: 8px 14px; border-radius: 6px; font: 600 13px/1 inherit; text-decoration: none; cursor: pointer; }
        .toolbar button.primary { background: #1d5fed; border-color: #1d5fed; color: #fff; }
        .sheet { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto 24px; padding: 16mm 14mm; background: #fff; box-shadow: 0 1px 3px rgba(15, 23, 42, .12); }
        .watermark { position: absolute; top: 40%; left: 0; right: 0; text-align: center; font-size: 96px; font-weight: 800; color: rgba(148, 163, 184, .18); transform: rotate(-24deg); pointer-events: none; }
        header { display: flex; justify-content: space-between; gap: 24px; padding-bottom: 14px; border-bottom: 2px solid #1d5fed; }
        header img { max-height: 56px; max-width: 160px; object-fit: contain; }
        .company h1 { margin: 6px 0 2px; font-size: 18px; }
        .muted { color: #64748b; }
        .doc-title { text-align: right; }
        .doc-title h2 { margin: 0; font-size: 22px; letter-spacing: .08em; color: #1d5fed; }
        .meta { margin-top: 6px; border-collapse: collapse; margin-left: auto; }
        .meta td { padding: 1px 0 1px 12px; }
        .parties { display: flex; justify-content: space-between; gap: 24px; margin: 18px 0; }
        .parties h3 { margin: 0 0 4px; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
        table.items { width: 100%; border-collapse: collapse; }
        table.items th { background: #f1f5f9; font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #475569; text-align: left; padding: 8px; }
        table.items td { padding: 8px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        .num, table.items th.num { text-align: right; white-space: nowrap; }
        .totals { width: 280px; margin: 12px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 3px 8px; }
        .totals tr.grand td { border-top: 2px solid #0f172a; font-size: 15px; font-weight: 700; padding-top: 6px; }
        .terbilang { margin-top: 8px; padding: 8px 10px; background: #f8fafc; border-left: 3px solid #1d5fed; font-style: italic; }
        .notes { margin-top: 18px; }
        .notes p { margin: 0; white-space: pre-line; }
        .notes h3, .sign h3 { margin: 0 0 4px; font-size: 11px; text-transform: uppercase; letter-spacing: .06em; color: #64748b; }
        .sign { display: flex; justify-content: flex-end; margin-top: 36px; text-align: center; }
        .sign .line { margin-top: 64px; border-top: 1px solid #0f172a; padding-top: 4px; min-width: 200px; font-weight: 600; }
        footer { margin-top: 28px; font-size: 11px; color: #94a3b8; text-align: center; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none; }
            .sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; }
            tr { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
<div class="toolbar">
    <button type="button" class="primary" id="print-button">Cetak / Simpan PDF</button>
    <a href="<?= site_url('quotations/' . $quotation['id']) ?>">Kembali</a>
</div>

<main class="sheet">
    <?php if ($isDraft): ?><div class="watermark">DRAFT</div><?php endif ?>

    <header>
        <div class="company">
            <?php if ($logo !== null): ?><img src="<?= esc($logo, 'attr') ?>" alt="<?= esc($companyName, 'attr') ?>"><?php endif ?>
            <h1><?= esc($companyName) ?></h1>
            <?php if (! empty($settings['company_address'])): ?><div class="muted"><?= esc($settings['company_address']) ?></div><?php endif ?>
            <?php if (! empty($settings['company_phone'])): ?><div class="muted">Telp. <?= esc(format_hp($settings['company_phone'])) ?></div><?php endif ?>
        </div>
        <div class="doc-title">
            <h2>PENAWARAN HARGA</h2>
            <table class="meta">
                <tr><td class="muted">Nomor</td><td><strong><?= esc($quotation['number']) ?></strong></td></tr>
                <tr><td class="muted">Tanggal</td><td><?= tanggal_id($quotation['issue_date']) ?></td></tr>
                <tr><td class="muted">Berlaku s.d.</td><td><?= tanggal_id($quotation['valid_until']) ?></td></tr>
            </table>
        </div>
    </header>

    <section class="parties">
        <div>
            <h3>Kepada</h3>
            <strong><?= esc($company['name'] ?? '-') ?></strong>
            <?php if ($contact !== null): ?>
                <div>u.p. <?= esc(ContactModel::fullName($contact)) ?><?= $contact['job_title'] ? ' — ' . esc($contact['job_title']) : '' ?></div>
            <?php endif ?>
            <?php if ($company !== null && ($company['address'] || $company['city'])): ?>
                <div class="muted"><?= esc(implode(', ', array_filter([$company['address'], $company['city'], $company['province']]))) ?></div>
            <?php endif ?>
        </div>
        <div style="text-align: right">
            <h3>Perihal</h3>
            <strong><?= esc($deal['title']) ?></strong>
        </div>
    </section>

    <p>Dengan hormat, bersama ini kami sampaikan penawaran harga sebagai berikut:</p>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 32px">No</th>
                <th>Deskripsi</th>
                <th class="num">Qty</th>
                <th class="num">Harga satuan</th>
                <th class="num">Diskon</th>
                <th class="num">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $i => $item): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= esc($item['description']) ?><?php if ($item['sku']): ?><div class="muted" style="font-size: 11px"><?= esc($item['sku']) ?></div><?php endif ?></td>
                    <td class="num"><?= esc(str_replace('.', ',', (string) (float) $item['qty'])) ?> <?= esc($item['unit'] ?? '') ?></td>
                    <td class="num"><?= rupiah($item['unit_price']) ?></td>
                    <td class="num"><?= (float) $item['discount_pct'] > 0 ? persen((float) $item['discount_pct']) : '—' ?></td>
                    <td class="num"><?= rupiah($item['line_total']) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">Subtotal</td><td class="num"><?= rupiah($quotation['subtotal']) ?></td></tr>
        <?php if ((float) $quotation['discount_total'] > 0): ?>
            <tr><td class="muted">Diskon</td><td class="num">−<?= rupiah($quotation['discount_total']) ?></td></tr>
        <?php endif ?>
        <tr><td class="muted">PPN <?= persen((float) $quotation['tax_rate']) ?></td><td class="num"><?= rupiah($quotation['tax_amount']) ?></td></tr>
        <tr class="grand"><td>Total</td><td class="num"><?= rupiah($quotation['grand_total']) ?></td></tr>
    </table>
    <div class="terbilang">Terbilang: <?= esc(ucfirst(terbilang($quotation['grand_total']))) ?> rupiah</div>

    <?php if ($quotation['notes']): ?>
        <section class="notes">
            <h3>Catatan &amp; syarat</h3>
            <p><?= esc($quotation['notes']) ?></p>
        </section>
    <?php endif ?>

    <p style="margin-top: 18px">Penawaran ini berlaku sampai <strong><?= tanggal_id($quotation['valid_until']) ?></strong>. Atas perhatian dan kerja samanya kami ucapkan terima kasih.</p>

    <section class="sign">
        <div>
            <h3>Hormat kami,</h3>
            <div class="line"><?= esc($quotation['owner_name'] ?? '') ?></div>
            <div class="muted"><?= esc($companyName) ?></div>
        </div>
    </section>

    <footer><?= esc($quotation['number']) ?> · Dicetak <?= tanggal_id(date('Y-m-d H:i:s'), true) ?></footer>
</main>

<script <?= csp_script_nonce() ?>>
    document.getElementById('print-button').addEventListener('click', () => window.print());
</script>
</body>
</html>
