<?php

use App\Exceptions\ValidationFailedException;
use App\Models\DealModel;
use App\Services\QuotationService;
use Tests\Support\AppTestCase;

/**
 * Produk & penawaran: penomoran, perhitungan, status, akses (T5.01–T5.03, T5.09).
 *
 * @internal
 */
final class QuotationTest extends AppTestCase
{
    private function product(string $sku, float $price, bool $active = true): int
    {
        $this->db->table('products')->insert([
            'sku' => $sku, 'name' => 'Produk ' . $sku, 'unit' => 'unit', 'price' => $price, 'is_active' => $active ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @return array<string, mixed>
     */
    private function input(array $items, array $overrides = []): array
    {
        return array_merge(['issue_date' => '2026-10-28', 'valid_until' => '2026-11-11', 'notes' => 'Pembayaran 50% di muka.', 'items' => $items], $overrides);
    }

    public function testPerhitunganTotal(): void
    {
        $totals = QuotationService::calculate([
            ['qty' => 40, 'unit_price' => 4500000, 'discount_pct' => 10],
            ['qty' => 1, 'unit_price' => 48000000, 'discount_pct' => 0],
            ['qty' => 2.5, 'unit_price' => 1999.99, 'discount_pct' => 12.5],
        ], 11);

        $this->assertSame([162000000.0, 48000000.0, 4374.98], $totals['lines']);
        $this->assertSame(228004999.98, $totals['subtotal']);
        $this->assertSame(18000625.0, $totals['discount_total']);
        $this->assertSame(23100481.25, $totals['tax_amount']);
        $this->assertSame(233104856.23, $totals['grand_total']);

        $none = QuotationService::calculate([['qty' => 1, 'unit_price' => 1000000, 'discount_pct' => 0]], 0);
        $this->assertSame(1000000.0, $none['grand_total']);
    }

    public function testBuatPenawaranDariDealDanHitungUlangDiServer(): void
    {
        $dealId  = $this->makeDeal('andi.saputra', 'Proposal');
        $product = $this->product('LIC-ERP-USR', 4500000);

        // Harga & deskripsi kosong diisi dari produk; total dari klien diabaikan.
        $result = $this->actingAs('andi.saputra')->post('quotations', ['deal_id' => (string) $dealId] + $this->input([
            ['product_id' => (string) $product, 'description' => '', 'qty' => '40', 'unit_price' => '', 'discount_pct' => '10'],
            ['product_id' => '', 'description' => 'Pelatihan pengguna', 'qty' => '2', 'unit_price' => '7.500.000', 'discount_pct' => '0'],
            ['product_id' => '', 'description' => '', 'qty' => '', 'unit_price' => '', 'discount_pct' => ''],
        ]) + ['grand_total' => '1']);

        $quotation = $this->db->table('quotations')->get()->getRowArray();
        $result->assertRedirectTo(site_url('quotations/' . $quotation['id']));
        $this->assertSame('QUO/2026/10/0001', $quotation['number']);
        $this->assertSame('draft', $quotation['status']);
        $this->assertSame('11.00', $quotation['tax_rate']);
        $this->assertSame('195000000.00', $quotation['subtotal']);
        $this->assertSame('18000000.00', $quotation['discount_total']);
        $this->assertSame('19470000.00', $quotation['tax_amount']);
        $this->assertSame('196470000.00', $quotation['grand_total']);
        $this->assertSame($this->user('andi.saputra')->id, (int) $quotation['created_by']);

        $items = $this->db->table('quotation_items')->where('quotation_id', $quotation['id'])->orderBy('id')->get()->getResultArray();
        $this->assertCount(2, $items);
        $this->assertSame('Produk LIC-ERP-USR', $items[0]['description']);
        $this->assertSame('162000000.00', $items[0]['line_total']);
        $this->seeInDatabase('audit_logs', ['action' => 'create', 'entity' => 'quotation']);
    }

    public function testNomorBerurutanDanResetPerBulan(): void
    {
        $dealId  = $this->makeDeal('andi.saputra', 'Proposal');
        $deal    = model(DealModel::class)->find($dealId);
        $service = service('quotationService');
        $andi    = $this->user('andi.saputra');
        $item    = [['description' => 'Jasa', 'qty' => '1', 'unit_price' => '1000000']];

        $numbers = [];

        foreach (['2026-10-01', '2026-10-15', '2026-11-02', '2026-10-31', '2027-01-05'] as $date) {
            $id        = $service->create($deal, $this->input($item, ['issue_date' => $date, 'valid_until' => $date]), $andi);
            $numbers[] = $this->db->table('quotations')->where('id', $id)->get()->getRow('number');
        }

        $this->assertSame(['QUO/2026/10/0001', 'QUO/2026/10/0002', 'QUO/2026/11/0001', 'QUO/2026/10/0003', 'QUO/2027/01/0001'], $numbers);

        // Nomor penawaran yang dihapus tidak dipakai ulang.
        $this->db->table('quotations')->where('number', 'QUO/2026/10/0003')->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $id = $service->create($deal, $this->input($item, ['issue_date' => '2026-10-20', 'valid_until' => '2026-10-20']), $andi);
        $this->assertSame('QUO/2026/10/0004', $this->db->table('quotations')->where('id', $id)->get()->getRow('number'));
    }

    public function testValidasiItemDanTanggal(): void
    {
        $dealId   = $this->makeDeal('andi.saputra', 'Proposal');
        $inactive = $this->product('HW-LAMA', 1000, false);

        $this->actingAs('andi.saputra')->post('quotations', ['deal_id' => (string) $dealId] + $this->input([
            ['product_id' => (string) $inactive, 'description' => 'X', 'qty' => '1', 'unit_price' => '1000'],
            ['description' => 'Tanpa qty', 'qty' => '0', 'unit_price' => '1000'],
            ['description' => 'Diskon salah', 'qty' => '1', 'unit_price' => '1000', 'discount_pct' => '150'],
        ], ['valid_until' => '2026-10-01']));

        $errors = $this->flashErrors();
        $this->assertStringContainsString('nonaktif', $errors['items.0'] ?? '');
        $this->assertStringContainsString('qty', $errors['items.1'] ?? '');
        $this->assertStringContainsString('diskon', $errors['items.2'] ?? '');
        $this->assertArrayHasKey('valid_until', $errors);
        $this->assertSame(0, $this->db->table('quotations')->countAllResults());

        $this->expectException(ValidationFailedException::class);
        service('quotationService')->create(model(DealModel::class)->find($dealId), $this->input([]), $this->user('andi.saputra'));
    }

    public function testUbahHanyaSaatDraftDanAlurStatus(): void
    {
        $dealId = $this->makeDeal('andi.saputra', 'Negosiasi');
        $id     = service('quotationService')->create(model(DealModel::class)->find($dealId), $this->input([['description' => 'Lisensi', 'qty' => '1', 'unit_price' => '1000000']]), $this->user('andi.saputra'));

        $this->actingAs('andi.saputra')->post("quotations/{$id}", $this->input([['description' => 'Lisensi revisi', 'qty' => '2', 'unit_price' => '1000000']]))
            ->assertRedirectTo(site_url('quotations/' . $id));
        $this->seeInDatabase('quotations', ['id' => $id, 'grand_total' => '2220000.00']);
        $this->seeInDatabase('quotation_items', ['quotation_id' => $id, 'description' => 'Lisensi revisi']);
        $this->assertSame(1, $this->db->table('quotation_items')->where('quotation_id', $id)->countAllResults());

        $this->actingAs('andi.saputra')->post("quotations/{$id}/status", ['status' => 'sent']);
        $this->seeInDatabase('quotations', ['id' => $id, 'status' => 'sent']);

        // Setelah terkirim tidak bisa diubah.
        $this->actingAs('andi.saputra')->post("quotations/{$id}", $this->input([['description' => 'Coba', 'qty' => '1', 'unit_price' => '1']]));
        $this->assertStringContainsString('Draft', (string) $_SESSION['error']);
        $this->seeInDatabase('quotations', ['id' => $id, 'grand_total' => '2220000.00']);

        $this->actingAs('andi.saputra')->post("quotations/{$id}/status", ['status' => 'bogus']);
        $this->seeInDatabase('quotations', ['id' => $id, 'status' => 'sent']);

        $this->actingAs('andi.saputra')->post("quotations/{$id}/status", ['status' => 'accepted']);
        $this->seeInDatabase('audit_logs', ['action' => 'status_change', 'entity' => 'quotation', 'entity_id' => $id]);

        // Penawaran diterima tidak bisa dihapus sales, bisa oleh manager tim.
        $this->actingAs('andi.saputra')->post("quotations/{$id}/delete")->assertStatus(403);
        $this->actingAs('dewi.lestari')->post("quotations/{$id}/delete")->assertRedirectTo(site_url('deals/' . $dealId));
    }

    public function testAksesPenawaranMengikutiOwnerDeal(): void
    {
        $dealId = $this->makeDeal('siti.rahma', 'Proposal');
        $id     = service('quotationService')->create(model(DealModel::class)->find($dealId), $this->input([['description' => 'Jasa', 'qty' => '1', 'unit_price' => '1000']]), $this->user('siti.rahma'));

        foreach (["quotations/{$id}", "quotations/{$id}/edit", "quotations/{$id}/print", 'quotations/new?deal=' . $dealId] as $path) {
            $this->actingAs('andi.saputra')->get($path)->assertStatus(404);
        }

        $this->actingAs('andi.saputra')->post("quotations/{$id}/status", ['status' => 'sent'])->assertStatus(404);
        $this->actingAs('andi.saputra')->post('quotations', ['deal_id' => (string) $dealId] + $this->input([['description' => 'X', 'qty' => '1', 'unit_price' => '1']]))->assertStatus(404);
        $this->actingAs('andi.saputra')->get('quotations')->assertDontSee('QUO/2026/10/0001');

        $this->actingAs('dewi.lestari')->get("quotations/{$id}")->assertOK();
        $this->actingAs('hendra.wijaya')->get("quotations/{$id}")->assertStatus(404);
    }

    public function testHalamanCetak(): void
    {
        $companyId = $this->makeCompany('andi.saputra', ['name' => 'PT Sinar Logistik Nusantara', 'city' => 'Surabaya']);
        $dealId    = $this->makeDeal('andi.saputra', 'Negosiasi', ['company_id' => $companyId, 'title' => 'Implementasi ERP Gudang']);
        $id        = service('quotationService')->create(model(DealModel::class)->find($dealId), $this->input([['description' => 'Lisensi ERP', 'qty' => '1', 'unit_price' => '1250000']]), $this->user('andi.saputra'));

        $result = $this->actingAs('andi.saputra')->get("quotations/{$id}/print");

        $result->assertOK();
        $result->assertSee('PENAWARAN HARGA');
        $result->assertSee('QUO/2026/10/0001');
        $result->assertSee('PT Sinar Logistik Nusantara');
        $result->assertSee('PT Nusantara Solusi Digital');
        $result->assertSee('Rp 1.387.500');
        $result->assertSee('Satu juta tiga ratus delapan puluh tujuh ribu lima ratus rupiah');
        $result->assertSee('11 Nov 2026');
        $result->assertSee('@media print');
        $result->assertSee('DRAFT');
    }

    public function testProdukHanyaDikelolaAdmin(): void
    {
        $this->actingAs('andi.saputra')->get('products')->assertOK();
        $this->actingAs('andi.saputra')->get('products/new')->assertStatus(403);
        $this->actingAs('dewi.lestari')->post('products', ['sku' => 'X-1', 'name' => 'X', 'unit' => 'unit', 'price' => '1', 'is_active' => '1'])->assertStatus(403);

        $this->actingAs('admin')->post('products', ['sku' => 'lic-crm-usr', 'name' => 'Lisensi CRM', 'unit' => 'user', 'price' => '2.400.000', 'is_active' => '1'])
            ->assertRedirectTo(site_url('products'));
        $this->seeInDatabase('products', ['sku' => 'LIC-CRM-USR', 'price' => '2400000.00', 'is_active' => 1]);

        $this->actingAs('admin')->post('products', ['sku' => 'LIC-CRM-USR', 'name' => 'Duplikat', 'unit' => 'user', 'price' => '1', 'is_active' => '1']);
        $this->assertSame('SKU sudah dipakai produk lain.', $this->flashErrors()['sku'] ?? null);

        $id = $this->masterIdBySku('LIC-CRM-USR');
        $this->actingAs('admin')->post("products/{$id}", ['sku' => 'LIC-CRM-USR', 'name' => 'Lisensi CRM', 'unit' => 'user', 'price' => '2400000', 'is_active' => '0'])
            ->assertRedirectTo(site_url('products'));
        $this->seeInDatabase('products', ['id' => $id, 'is_active' => 0]);

        // Produk nonaktif tidak muncul di form penawaran.
        $dealId = $this->makeDeal('andi.saputra', 'Proposal');
        $this->actingAs('andi.saputra')->get('quotations/new?deal=' . $dealId)->assertDontSee('LIC-CRM-USR');
    }

    private function masterIdBySku(string $sku): int
    {
        return (int) $this->db->table('products')->where('sku', $sku)->get()->getRow('id');
    }
}
