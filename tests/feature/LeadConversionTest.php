<?php

use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\LeadModel;
use Tests\Support\AppTestCase;

/**
 * Konversi lead → perusahaan + kontak + deal (T2.04).
 *
 * @internal
 */
final class LeadConversionTest extends AppTestCase
{
    /**
     * @return array<string, string>
     */
    private function input(array $overrides = []): array
    {
        return array_merge([
            'company_mode'             => 'new',
            'company_name'             => 'PT Cahaya Abadi Logistik',
            'company_industry_id'      => (string) $this->masterId('industries', 'Logistik'),
            'company_city'             => 'Surabaya',
            'company_province'         => 'Jawa Timur',
            'contact_first_name'       => 'Rahmat',
            'contact_last_name'        => 'Hidayat',
            'contact_job_title'        => 'Manajer Operasional',
            'contact_phone'            => '0812-1111-2222',
            'contact_email'            => 'rahmat@cahayaabadi.co.id',
            'create_deal'              => '1',
            'deal_title'               => 'Implementasi WMS 2 gudang',
            'deal_amount'              => '150.000.000',
            'deal_stage_id'            => (string) $this->stageId('Kualifikasi'),
            'deal_expected_close_date' => date('Y-m-d', strtotime('+30 days')),
        ], $overrides);
    }

    private function stageId(string $name): int
    {
        return $this->masterId('pipeline_stages', $name);
    }

    public function testKonversiMembuatTigaRecordTerhubung(): void
    {
        $leadId = $this->makeLead('andi.saputra', ['name' => 'Rahmat Hidayat', 'company_name' => 'PT Cahaya Abadi Logistik']);

        $result = $this->actingAs('andi.saputra')->post("leads/{$leadId}/convert", $this->input());

        $company = $this->db->table('companies')->where('name', 'PT Cahaya Abadi Logistik')->get()->getRowArray();
        $contact = $this->db->table('contacts')->where('first_name', 'Rahmat')->get()->getRowArray();
        $deal    = $this->db->table('deals')->where('title', 'Implementasi WMS 2 gudang')->get()->getRowArray();
        $lead    = $this->db->table('leads')->where('id', $leadId)->get()->getRowArray();

        $result->assertRedirectTo(site_url('companies/' . $company['id']));
        $this->assertSame($company['id'], $contact['company_id']);
        $this->assertSame('081211112222', $contact['whatsapp']);
        $this->assertSame($company['id'], $deal['company_id']);
        $this->assertSame($contact['id'], $deal['contact_id']);
        $this->assertSame('150000000.00', $deal['amount']);
        $this->assertSame('25', (string) $deal['probability']);

        foreach ([$company, $contact, $deal] as $record) {
            $this->assertSame($this->user('andi.saputra')->id, (int) $record['owner_id']);
        }

        $this->assertSame('converted', $lead['status']);
        $this->assertSame($company['id'], $lead['converted_company_id']);
        $this->assertSame($contact['id'], $lead['converted_contact_id']);
        $this->assertSame($deal['id'], $lead['converted_deal_id']);
        $this->assertNotNull($lead['converted_at']);

        $this->seeInDatabase('deal_stage_history', ['deal_id' => $deal['id'], 'from_stage_id' => null, 'to_stage_id' => $this->stageId('Kualifikasi')]);
        $this->seeInDatabase('audit_logs', ['action' => 'convert', 'entity' => 'lead', 'entity_id' => $leadId]);
    }

    public function testKonversiKePerusahaanYangSudahAdaTanpaDeal(): void
    {
        $companyId = $this->makeCompany('andi.saputra', ['name' => 'PT Sudah Ada']);
        $leadId    = $this->makeLead('andi.saputra');

        $this->actingAs('andi.saputra')->post("leads/{$leadId}/convert", $this->input([
            'company_mode' => 'existing',
            'company_id'   => (string) $companyId,
            'create_deal'  => '',
        ]))->assertRedirectTo(site_url('companies/' . $companyId));

        $this->assertSame(1, $this->db->table('companies')->countAllResults());
        $this->assertSame(0, $this->db->table('deals')->countAllResults());
        $this->seeInDatabase('contacts', ['first_name' => 'Rahmat', 'company_id' => $companyId]);
        $this->seeInDatabase('leads', ['id' => $leadId, 'status' => 'converted', 'converted_deal_id' => null]);
    }

    public function testTidakBisaMemakaiPerusahaanDiLuarCakupan(): void
    {
        $companyId = $this->makeCompany('putri.ayu', ['name' => 'PT Milik Putri']);
        $leadId    = $this->makeLead('andi.saputra');

        $this->actingAs('andi.saputra')->post("leads/{$leadId}/convert", $this->input([
            'company_mode' => 'existing',
            'company_id'   => (string) $companyId,
        ]));

        $this->assertArrayHasKey('company_id', $this->flashErrors());
        $this->assertSame(0, $this->db->table('contacts')->countAllResults());
        $this->seeInDatabase('leads', ['id' => $leadId, 'status' => 'new']);
    }

    public function testRollbackBilaDealGagal(): void
    {
        $leadId = $this->makeLead('andi.saputra');

        $this->actingAs('andi.saputra')->post("leads/{$leadId}/convert", $this->input([
            'deal_stage_id' => (string) $this->stageId('Won'),
        ]));

        $this->assertArrayHasKey('deal_stage_id', $this->flashErrors());
        $this->assertSame(0, $this->db->table('companies')->countAllResults(), 'Perusahaan harus ikut di-rollback');
        $this->assertSame(0, $this->db->table('contacts')->countAllResults(), 'Kontak harus ikut di-rollback');
        $this->assertSame(0, $this->db->table('deals')->countAllResults());
        $this->dontSeeInDatabase('audit_logs', ['entity' => 'company']);
        $this->seeInDatabase('leads', ['id' => $leadId, 'status' => 'new', 'converted_company_id' => null]);
    }

    public function testRollbackBilaKontakTidakValid(): void
    {
        $leadId = $this->makeLead('andi.saputra');

        $this->actingAs('andi.saputra')->post("leads/{$leadId}/convert", $this->input([
            'contact_first_name' => '',
            'deal_amount'        => 'abc',
        ]));

        $this->assertArrayHasKey('contact_first_name', $this->flashErrors());
        $this->assertSame(0, $this->db->table('companies')->countAllResults());
        $this->seeInDatabase('leads', ['id' => $leadId, 'status' => 'new']);
    }

    public function testLeadDikonversiTidakBisaDikonversiUlang(): void
    {
        $leadId = $this->makeLead('andi.saputra', ['status' => 'converted']);

        $this->actingAs('andi.saputra')->get("leads/{$leadId}/convert")->assertRedirectTo(site_url('leads/' . $leadId));

        $this->actingAs('andi.saputra')->post("leads/{$leadId}/convert", $this->input())
            ->assertSessionHas('error', 'Lead ini sudah dikonversi sebelumnya.');
        $this->assertSame(0, $this->db->table('companies')->countAllResults());
    }

    public function testServiceMelemparExceptionYangSesuai(): void
    {
        $service = service('leadConversion');
        $lead    = model(LeadModel::class)->find($this->makeLead('andi.saputra', ['status' => 'converted']));

        try {
            $service->convert($lead, $this->input(), $this->user('andi.saputra'));
            $this->fail('Seharusnya BusinessRuleException');
        } catch (BusinessRuleException) {
            $this->addToAssertionCount(1);
        }

        $lead = model(LeadModel::class)->find($this->makeLead('andi.saputra'));
        $this->expectException(ValidationFailedException::class);
        $service->convert($lead, $this->input(['company_name' => '']), $this->user('andi.saputra'));
    }

    public function testSalesTidakBisaMengonversiLeadSalesLain(): void
    {
        $leadId = $this->makeLead('siti.rahma');

        $this->actingAs('andi.saputra')->post("leads/{$leadId}/convert", $this->input())->assertStatus(404);
        $this->assertSame(0, $this->db->table('companies')->countAllResults());
    }
}
