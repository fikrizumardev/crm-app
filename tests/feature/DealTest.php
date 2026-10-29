<?php

use Tests\Support\AppTestCase;

/**
 * Deal, pindah tahap (form & JSON), dan Kanban (T3.01–T3.03).
 *
 * @internal
 */
final class DealTest extends AppTestCase
{
    /**
     * @return array<string, string>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'title'               => 'Implementasi ERP Gudang — 3 cabang',
            'amount'              => '485.000.000',
            'stage_id'            => (string) $this->masterId('pipeline_stages', 'Proposal'),
            'probability'         => '',
            'expected_close_date' => date('Y-m-d', strtotime('+30 days')),
        ], $overrides);
    }

    private function deal(int $id): array
    {
        return $this->db->table('deals')->where('id', $id)->get()->getRowArray();
    }

    public function testBuatDealDenganProbabilitasDefaultTahap(): void
    {
        $companyId = $this->makeCompany('andi.saputra');
        $contactId = $this->makeContact('andi.saputra', ['company_id' => $companyId]);

        $this->actingAs('andi.saputra')->post('deals', $this->input([
            'company_id' => (string) $companyId,
            'contact_id' => (string) $contactId,
            'owner_id'   => (string) $this->user('siti.rahma')->id,
        ]));

        $deal = $this->db->table('deals')->where('title', 'Implementasi ERP Gudang — 3 cabang')->get()->getRowArray();
        $this->assertNotNull($deal);
        $this->assertSame('485000000.00', $deal['amount']);
        $this->assertSame('50', (string) $deal['probability']);
        $this->assertSame($this->user('andi.saputra')->id, (int) $deal['owner_id']);
        $this->assertNull($deal['closed_at']);
        $this->seeInDatabase('deal_stage_history', ['deal_id' => $deal['id'], 'from_stage_id' => null, 'to_stage_id' => $deal['stage_id']]);
    }

    public function testKontakHarusBagianPerusahaan(): void
    {
        $companyId = $this->makeCompany('andi.saputra');
        $otherCo   = $this->makeCompany('andi.saputra', ['name' => 'PT Lain']);
        $contactId = $this->makeContact('andi.saputra', ['company_id' => $otherCo]);

        $this->actingAs('andi.saputra')->post('deals', $this->input(['company_id' => (string) $companyId, 'contact_id' => (string) $contactId]));

        $this->assertArrayHasKey('contact_id', $this->flashErrors());
        $this->assertSame(0, $this->db->table('deals')->countAllResults());
    }

    public function testDealLostWajibAlasan(): void
    {
        $lost = (string) $this->masterId('pipeline_stages', 'Lost');

        $this->actingAs('andi.saputra')->post('deals', $this->input(['stage_id' => $lost]));
        $this->assertArrayHasKey('lost_reason_id', $this->flashErrors());
        $this->assertSame(0, $this->db->table('deals')->countAllResults());

        $this->actingAs('andi.saputra')->post('deals', $this->input([
            'stage_id'       => $lost,
            'lost_reason_id' => (string) $this->masterId('lost_reasons', 'Kompetitor'),
            'probability'    => '60',
        ]));

        $deal = $this->db->table('deals')->get()->getRowArray();
        $this->assertSame('0', (string) $deal['probability']);
        $this->assertNotNull($deal['closed_at']);
    }

    public function testUbahTahapLewatFormMencatatRiwayat(): void
    {
        $id = $this->makeDeal('andi.saputra', 'Negosiasi');

        $this->actingAs('andi.saputra')->post('deals/' . $id, $this->input([
            'title'       => 'Deal Menang',
            'stage_id'    => (string) $this->masterId('pipeline_stages', 'Won'),
            'probability' => '75',
        ]))->assertRedirectTo(site_url('deals/' . $id));

        $deal = $this->deal($id);
        $this->assertSame('Deal Menang', $deal['title']);
        $this->assertSame('100', (string) $deal['probability']);
        $this->assertNotNull($deal['closed_at']);
        $this->seeInDatabase('deal_stage_history', [
            'deal_id'       => $id,
            'from_stage_id' => $this->masterId('pipeline_stages', 'Negosiasi'),
            'to_stage_id'   => $this->masterId('pipeline_stages', 'Won'),
        ]);
        $this->seeInDatabase('audit_logs', ['action' => 'stage_change', 'entity' => 'deal', 'entity_id' => $id]);
    }

    public function testProbabilitasManualDipertahankanBilaTahapSama(): void
    {
        $id = $this->makeDeal('andi.saputra', 'Proposal');

        $this->actingAs('andi.saputra')->post('deals/' . $id, $this->input(['probability' => '65']));

        $this->assertSame('65', (string) $this->deal($id)['probability']);
        $this->assertSame(1, $this->db->table('deal_stage_history')->where('deal_id', $id)->countAllResults());
    }

    public function testKembaliDariWonMenghapusTanggalTutup(): void
    {
        $id = $this->makeDeal('andi.saputra', 'Won');

        $this->actingAs('andi.saputra')->post("deals/{$id}/stage", ['stage_id' => (string) $this->masterId('pipeline_stages', 'Negosiasi')]);

        $deal = $this->deal($id);
        $this->assertNull($deal['closed_at']);
        $this->assertSame('75', (string) $deal['probability']);
    }

    public function testPindahTahapJson(): void
    {
        $id = $this->makeDeal('andi.saputra', 'Prospek');

        $result = $this->actingAs('andi.saputra')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json'])
            ->post("deals/{$id}/stage", ['stage_id' => (string) $this->masterId('pipeline_stages', 'Proposal')]);

        $result->assertOK();
        $json = json_decode($result->getJSON(), true);
        $this->assertTrue($json['ok']);
        $this->assertSame('Proposal', $json['deal']['stage_name']);
        $this->assertSame(50, $json['deal']['probability']);
        $this->assertArrayHasKey('csrf', $json);
        $this->assertSame((string) $this->masterId('pipeline_stages', 'Proposal'), (string) $this->deal($id)['stage_id']);
    }

    public function testPindahKeLostTanpaAlasanDitolak(): void
    {
        $id = $this->makeDeal('andi.saputra', 'Negosiasi');

        $result = $this->actingAs('andi.saputra')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("deals/{$id}/stage", ['stage_id' => (string) $this->masterId('pipeline_stages', 'Lost')]);

        $result->assertStatus(422);
        $this->assertStringContainsString('alasan kalah', json_decode($result->getJSON(), true)['message']);
        $this->assertSame((string) $this->masterId('pipeline_stages', 'Negosiasi'), (string) $this->deal($id)['stage_id']);

        // Lewat form biasa: kembali ke detail dengan pesan error.
        $this->actingAs('andi.saputra')->withHeaders([])->post("deals/{$id}/stage", ['stage_id' => (string) $this->masterId('pipeline_stages', 'Lost')])
            ->assertRedirectTo(site_url('deals/' . $id));
        $this->assertStringContainsString('alasan kalah', (string) ($_SESSION['error'] ?? ''));
    }

    public function testSalesHanyaBisaMemindahkanDealMiliknya(): void
    {
        $id = $this->makeDeal('siti.rahma', 'Prospek');

        $result = $this->actingAs('andi.saputra')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("deals/{$id}/stage", ['stage_id' => (string) $this->masterId('pipeline_stages', 'Proposal')]);

        $result->assertStatus(404);
        $this->assertSame((string) $this->masterId('pipeline_stages', 'Prospek'), (string) $this->deal($id)['stage_id']);

        // Manager tim boleh.
        $this->actingAs('dewi.lestari')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->post("deals/{$id}/stage", ['stage_id' => (string) $this->masterId('pipeline_stages', 'Proposal')])
            ->assertOK();
    }

    public function testKanbanSesuaiCakupan(): void
    {
        $this->makeDeal('andi.saputra', 'Prospek', ['title' => 'Kanban Andi']);
        $this->makeDeal('siti.rahma', 'Proposal', ['title' => 'Kanban Siti']);
        $this->makeDeal('putri.ayu', 'Negosiasi', ['title' => 'Kanban Putri']);
        $this->makeDeal('andi.saputra', 'Won', ['title' => 'Won Lama', 'closed_at' => date('Y-m-d H:i:s', strtotime('-60 days'))]);

        $sales = $this->actingAs('andi.saputra')->get('deals/board');
        $sales->assertOK();
        $sales->assertSee('Kanban Andi');
        $sales->assertDontSee('Kanban Siti');
        $sales->assertDontSee('Won Lama');

        $manager = $this->actingAs('dewi.lestari')->get('deals/board');
        $manager->assertSee('Kanban Siti');
        $manager->assertDontSee('Kanban Putri');

        $filtered = $this->actingAs('dewi.lestari')->get('deals/board?owner=' . $this->user('siti.rahma')->id);
        $filtered->assertSee('Kanban Siti');
        $filtered->assertDontSee('Kanban Andi');

        $this->actingAs('admin')->get('deals/board')->assertSee('Kanban Putri');
    }

    public function testFilterDaftarDeal(): void
    {
        $this->makeDeal('andi.saputra', 'Prospek', ['title' => 'Deal Kecil', 'amount' => 20000000]);
        $this->makeDeal('andi.saputra', 'Won', ['title' => 'Deal Besar', 'amount' => 800000000]);

        $result = $this->actingAs('andi.saputra')->get('deals?min_amount=100.000.000');
        $result->assertSee('Deal Besar');
        $result->assertDontSee('Deal Kecil');

        $open = $this->actingAs('andi.saputra')->get('deals?status=open');
        $open->assertSee('Deal Kecil');
        $open->assertDontSee('Deal Besar');
    }

    public function testDetailDealMenampilkanRiwayat(): void
    {
        $id = $this->makeDeal('andi.saputra', 'Prospek', ['title' => 'Deal Detail']);

        $result = $this->actingAs('andi.saputra')->get('deals/' . $id);
        $result->assertOK();
        $result->assertSee('Dibuat di tahap');
        $result->assertSee('Riwayat tahap');

        $this->actingAs('maya.kusuma')->get('deals/' . $id)->assertStatus(404);
    }

    public function testSalesTidakBisaMenghapusDealWon(): void
    {
        $id = $this->makeDeal('andi.saputra', 'Won');

        $this->actingAs('andi.saputra')->post("deals/{$id}/delete")->assertStatus(403);
        $this->actingAs('dewi.lestari')->post("deals/{$id}/delete")->assertRedirectTo(site_url('deals'));
        $this->assertNotNull($this->deal($id)['deleted_at']);
    }
}
