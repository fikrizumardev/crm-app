<?php

use Tests\Support\AppTestCase;

/**
 * CRUD lead per peran, cek duplikat, dan ekspor.
 *
 * @internal
 */
final class LeadTest extends AppTestCase
{
    /**
     * @return array<string, string>
     */
    private function leadInput(array $overrides = []): array
    {
        return array_merge([
            'name'         => 'Rahmat Hidayat',
            'company_name' => 'PT Cahaya Abadi',
            'job_title'    => 'Manajer Operasional',
            'phone'        => '0812-1111-2222',
            'email'        => 'Rahmat@CahayaAbadi.co.id',
            'source_id'    => (string) $this->masterId('lead_sources', 'Pameran'),
            'status'       => 'new',
            'notes'        => 'Minta demo.',
        ], $overrides);
    }

    public function testDaftarLeadSesuaiCakupan(): void
    {
        $this->makeLead('andi.saputra', ['name' => 'Lead Milik Andi']);
        $this->makeLead('siti.rahma', ['name' => 'Lead Milik Siti']);
        $this->makeLead('putri.ayu', ['name' => 'Lead Milik Putri']);

        $sales = $this->actingAs('andi.saputra')->get('leads');
        $sales->assertOK();
        $sales->assertSee('Lead Milik Andi');
        $sales->assertDontSee('Lead Milik Siti');
        $sales->assertDontSee('Lead Milik Putri');

        $manager = $this->actingAs('dewi.lestari')->get('leads');
        $manager->assertSee('Lead Milik Andi');
        $manager->assertSee('Lead Milik Siti');
        $manager->assertDontSee('Lead Milik Putri');

        $admin = $this->actingAs('admin')->get('leads');
        $admin->assertSee('Lead Milik Putri');
    }

    public function testFilterPencarianDanStatus(): void
    {
        $this->makeLead('andi.saputra', ['name' => 'Agus Salim', 'phone' => '081299887766', 'status' => 'qualified']);
        $this->makeLead('andi.saputra', ['name' => 'Bunga Citra', 'status' => 'new']);

        $byPhone = $this->actingAs('andi.saputra')->get('leads?q=0812-9988');
        $byPhone->assertSee('Agus Salim');
        $byPhone->assertDontSee('Bunga Citra');

        $byStatus = $this->actingAs('andi.saputra')->get('leads?status=new');
        $byStatus->assertSee('Bunga Citra');
        $byStatus->assertDontSee('Agus Salim');
    }

    public function testSalesMembuatLeadSelaluMiliknyaSendiri(): void
    {
        $result = $this->actingAs('andi.saputra')->post('leads', $this->leadInput([
            'owner_id' => (string) $this->user('siti.rahma')->id,
        ]));

        $lead = $this->db->table('leads')->where('name', 'Rahmat Hidayat')->get()->getRowArray();
        $this->assertNotNull($lead);
        $result->assertRedirectTo(site_url('leads/' . $lead['id']));
        $this->assertSame($this->user('andi.saputra')->id, (int) $lead['owner_id']);
        $this->assertSame('081211112222', $lead['phone']);
        $this->assertSame('rahmat@cahayaabadi.co.id', $lead['email']);
        $this->seeInDatabase('audit_logs', ['action' => 'create', 'entity' => 'lead', 'entity_id' => $lead['id']]);
    }

    public function testManagerMemilihOwnerDalamTim(): void
    {
        $this->actingAs('dewi.lestari')->post('leads', $this->leadInput(['owner_id' => (string) $this->user('budi.santoso')->id]));
        $this->seeInDatabase('leads', ['name' => 'Rahmat Hidayat', 'owner_id' => $this->user('budi.santoso')->id]);
    }

    public function testManagerTidakBisaMemilihOwnerTimLain(): void
    {
        $this->actingAs('dewi.lestari')->post('leads', $this->leadInput(['owner_id' => (string) $this->user('putri.ayu')->id]));

        $this->assertArrayHasKey('owner_id', $this->flashErrors());
        $this->dontSeeInDatabase('leads', ['name' => 'Rahmat Hidayat']);
    }

    public function testValidasiLead(): void
    {
        $this->actingAs('andi.saputra')->post('leads', $this->leadInput(['name' => '', 'email' => 'bukan-email', 'phone' => '12']));

        $errors = $this->flashErrors();
        $this->assertSame('Nama wajib diisi.', $errors['name'] ?? null);
        $this->assertArrayHasKey('email', $errors);
        $this->assertArrayHasKey('phone', $errors);
    }

    public function testStatusDikonversiTidakBisaDipilihManual(): void
    {
        $this->actingAs('andi.saputra')->post('leads', $this->leadInput(['status' => 'converted']));

        $this->assertArrayHasKey('status', $this->flashErrors());
        $this->dontSeeInDatabase('leads', ['name' => 'Rahmat Hidayat']);
    }

    public function testPeringatanDuplikatNoHpKontak(): void
    {
        $this->makeContact('siti.rahma', ['first_name' => 'Wati', 'phone' => '081211112222']);

        $result = $this->actingAs('andi.saputra')->post('leads', $this->leadInput());

        $result->assertRedirectTo(site_url('leads/new'));
        $duplicates = $_SESSION['duplicates'] ?? [];
        $this->assertCount(1, $duplicates);
        $this->assertSame('Kontak', $duplicates[0]['type']);
        $this->assertSame('No. HP', $duplicates[0]['match']);
        $this->dontSeeInDatabase('leads', ['name' => 'Rahmat Hidayat']);

        // Tetap simpan setelah konfirmasi.
        $this->actingAs('andi.saputra')->post('leads', $this->leadInput(['confirm_duplicate' => '1']));
        $this->seeInDatabase('leads', ['name' => 'Rahmat Hidayat']);
    }

    public function testPeringatanDuplikatEmailLead(): void
    {
        $this->makeLead('putri.ayu', ['name' => 'Lead Lama', 'email' => 'rahmat@cahayaabadi.co.id']);

        $this->actingAs('andi.saputra')->post('leads', $this->leadInput(['phone' => '']));

        $this->assertSame('Email', $_SESSION['duplicates'][0]['match'] ?? null);
        $this->assertSame('Lead Lama', $_SESSION['duplicates'][0]['name'] ?? null);
    }

    public function testUpdateLeadTidakMenganggapDirinyaDuplikat(): void
    {
        $id = $this->makeLead('andi.saputra', ['name' => 'Lama', 'phone' => '081211112222']);

        $result = $this->actingAs('andi.saputra')->post('leads/' . $id, $this->leadInput(['status' => 'contacted']));

        $result->assertRedirectTo(site_url('leads/' . $id));
        $this->seeInDatabase('leads', ['id' => $id, 'name' => 'Rahmat Hidayat', 'status' => 'contacted']);
        $this->seeInDatabase('audit_logs', ['action' => 'update', 'entity' => 'lead', 'entity_id' => $id]);
    }

    public function testSalesTidakBisaMengaksesLeadSalesLain(): void
    {
        $id = $this->makeLead('siti.rahma', ['name' => 'Rahasia Siti']);

        $this->actingAs('andi.saputra')->get('leads/' . $id)->assertStatus(404);
        $this->actingAs('andi.saputra')->get('leads/' . $id . '/edit')->assertStatus(404);
        $this->actingAs('andi.saputra')->post('leads/' . $id, $this->leadInput())->assertStatus(404);
        $this->actingAs('andi.saputra')->post('leads/' . $id . '/delete')->assertStatus(404);

        $this->seeInDatabase('leads', ['id' => $id, 'name' => 'Rahasia Siti', 'deleted_at' => null]);
    }

    public function testHapusLead(): void
    {
        $id = $this->makeLead('andi.saputra');

        $this->actingAs('andi.saputra')->post('leads/' . $id . '/delete')->assertRedirectTo(site_url('leads'));

        $this->assertNotNull($this->db->table('leads')->where('id', $id)->get()->getRow('deleted_at'));
        $this->seeInDatabase('audit_logs', ['action' => 'delete', 'entity' => 'lead', 'entity_id' => $id]);
        $this->actingAs('andi.saputra')->get('leads/' . $id)->assertStatus(404);
    }

    public function testSalesTidakBisaMenghapusLeadDikonversi(): void
    {
        $id = $this->makeLead('andi.saputra', ['status' => 'converted']);

        $this->actingAs('andi.saputra')->post('leads/' . $id . '/delete')->assertStatus(403);
        $this->seeInDatabase('leads', ['id' => $id, 'deleted_at' => null]);

        $this->actingAs('dewi.lestari')->post('leads/' . $id . '/delete')->assertRedirectTo(site_url('leads'));
    }

    public function testEksporHanyaUntukAdminDanManager(): void
    {
        $this->makeLead('andi.saputra', ['name' => 'Ekspor Andi', 'phone' => '081234567890']);
        $this->makeLead('putri.ayu', ['name' => 'Ekspor Putri']);

        $this->actingAs('andi.saputra')->get('leads/export')->assertStatus(403);

        $result = $this->actingAs('dewi.lestari')->get('leads/export');
        $result->assertOK();
        $this->assertStringContainsString('text/csv', $result->response()->getHeaderLine('Content-Type'));

        $csv = $result->response()->getBody();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Nama,Perusahaan,Jabatan', $csv);
        $this->assertStringContainsString('Ekspor Andi', $csv);
        $this->assertStringContainsString('0812-3456-7890', $csv);
        $this->assertStringNotContainsString('Ekspor Putri', $csv);
    }

    public function testEksporMengikutiFilter(): void
    {
        $this->makeLead('andi.saputra', ['name' => 'Status Baru', 'status' => 'new']);
        $this->makeLead('andi.saputra', ['name' => 'Status Gagal', 'status' => 'unqualified']);

        $csv = $this->actingAs('admin')->get('leads/export?status=unqualified')->response()->getBody();

        $this->assertStringContainsString('Status Gagal', $csv);
        $this->assertStringNotContainsString('Status Baru', $csv);
    }

    public function testEksporMencegahFormulaInjection(): void
    {
        $this->makeLead('andi.saputra', ['name' => '=HYPERLINK("http://evil")']);

        $csv = $this->actingAs('admin')->get('leads/export')->response()->getBody();

        $this->assertStringContainsString("'=HYPERLINK", $csv);
    }
}
