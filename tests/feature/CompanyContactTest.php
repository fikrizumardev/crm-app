<?php

use Tests\Support\AppTestCase;

/**
 * CRUD perusahaan & kontak per peran.
 *
 * @internal
 */
final class CompanyContactTest extends AppTestCase
{
    public function testBuatPerusahaan(): void
    {
        $result = $this->actingAs('andi.saputra')->post('companies', [
            'name'           => 'PT Medika Sehat Sentosa',
            'industry_id'    => (string) $this->masterId('industries', 'Kesehatan'),
            'npwp'           => '01.234.567.8-901.234',
            'phone'          => '(021) 555-1234',
            'website'        => 'www.medikasehat.co.id',
            'city'           => 'Jakarta',
            'province'       => 'DKI Jakarta',
            'employee_count' => '120',
        ]);

        $company = $this->db->table('companies')->where('name', 'PT Medika Sehat Sentosa')->get()->getRowArray();
        $result->assertRedirectTo(site_url('companies/' . $company['id']));
        $this->assertSame('012345678901234', $company['npwp']);
        $this->assertSame('0215551234', $company['phone']);
        $this->assertSame('https://www.medikasehat.co.id', $company['website']);
        $this->assertSame($this->user('andi.saputra')->id, (int) $company['owner_id']);
    }

    public function testValidasiNpwpDanProvinsi(): void
    {
        $this->actingAs('andi.saputra')->post('companies', [
            'name'     => 'PT Salah Data',
            'npwp'     => '12345',
            'province' => 'Provinsi Fiktif',
        ]);

        $errors = $this->flashErrors();
        $this->assertSame('NPWP harus 15 atau 16 digit.', $errors['npwp'] ?? null);
        $this->assertArrayHasKey('province', $errors);
        $this->dontSeeInDatabase('companies', ['name' => 'PT Salah Data']);
    }

    public function testNpwpEnamBelasDigitDiterima(): void
    {
        $this->actingAs('andi.saputra')->post('companies', ['name' => 'PT NIK Baru', 'npwp' => '3171234567890123']);

        $this->seeInDatabase('companies', ['name' => 'PT NIK Baru', 'npwp' => '3171234567890123']);
    }

    public function testDetailPerusahaanMenampilkanKontaknya(): void
    {
        $companyId = $this->makeCompany('andi.saputra', ['name' => 'PT Sinar Logistik Nusantara']);
        $this->makeContact('andi.saputra', ['company_id' => $companyId, 'first_name' => 'Bambang', 'last_name' => 'Hartono']);

        $result = $this->actingAs('andi.saputra')->get('companies/' . $companyId);

        $result->assertOK();
        $result->assertSee('PT Sinar Logistik Nusantara');
        $result->assertSee('Bambang Hartono');
    }

    public function testPerusahaanSalesLainTidakBisaDiakses(): void
    {
        $companyId = $this->makeCompany('putri.ayu');

        $this->actingAs('andi.saputra')->get('companies/' . $companyId)->assertStatus(404);
        $this->actingAs('dewi.lestari')->get('companies/' . $companyId)->assertStatus(404);
        $this->actingAs('hendra.wijaya')->get('companies/' . $companyId)->assertOK();
    }

    public function testPencarianPerusahaanHanyaDalamCakupan(): void
    {
        $this->makeCompany('andi.saputra', ['name' => 'PT Cari Andi']);
        $this->makeCompany('putri.ayu', ['name' => 'PT Cari Putri']);

        $result = $this->actingAs('andi.saputra')->get('companies/search?q=Cari');

        $result->assertOK();
        $names = array_column(json_decode($result->getJSON(), true), 'name');
        $this->assertSame(['PT Cari Andi'], $names);
    }

    public function testHapusPerusahaanOlehManager(): void
    {
        $companyId = $this->makeCompany('siti.rahma');

        $this->actingAs('andi.saputra')->post('companies/' . $companyId . '/delete')->assertStatus(404);
        $this->actingAs('dewi.lestari')->post('companies/' . $companyId . '/delete')->assertRedirectTo(site_url('companies'));
        $this->assertNotNull($this->db->table('companies')->where('id', $companyId)->get()->getRow('deleted_at'));
    }

    public function testBuatKontakDenganPerusahaan(): void
    {
        $companyId = $this->makeCompany('andi.saputra');

        $this->actingAs('andi.saputra')->post('contacts', [
            'company_id' => (string) $companyId,
            'first_name' => 'Bambang',
            'last_name'  => 'Hartono',
            'job_title'  => 'Kepala IT',
            'phone'      => '0812-3456-7810',
            'email'      => 'Bambang.Hartono@sinarlogistik.co.id',
            'birth_date' => '1979-03-14',
        ]);

        $this->seeInDatabase('contacts', [
            'company_id' => $companyId,
            'first_name' => 'Bambang',
            'phone'      => '081234567810',
            'email'      => 'bambang.hartono@sinarlogistik.co.id',
            'owner_id'   => $this->user('andi.saputra')->id,
        ]);
    }

    public function testKontakTidakBisaDitautkanKePerusahaanSalesLain(): void
    {
        $companyId = $this->makeCompany('putri.ayu');

        $this->actingAs('andi.saputra')->post('contacts', ['company_id' => (string) $companyId, 'first_name' => 'Penyusup']);

        $this->assertSame('Perusahaan tidak ditemukan.', $this->flashErrors()['company_id'] ?? null);
        $this->dontSeeInDatabase('contacts', ['first_name' => 'Penyusup']);
    }

    public function testDetailKontakMenampilkanTombolWhatsAppDanTelepon(): void
    {
        $contactId = $this->makeContact('andi.saputra', ['first_name' => 'Bambang', 'phone' => '081234567810']);

        $result = $this->actingAs('andi.saputra')->get('contacts/' . $contactId);

        $result->assertOK();
        $result->assertSee('https://wa.me/6281234567810');
        $result->assertSee('tel:081234567810');
    }

    public function testUbahKontak(): void
    {
        $contactId = $this->makeContact('andi.saputra', ['first_name' => 'Lama']);

        $this->actingAs('andi.saputra')->post('contacts/' . $contactId, ['first_name' => 'Baru', 'whatsapp' => '+62 811 2233 4455'])
            ->assertRedirectTo(site_url('contacts/' . $contactId));

        $this->seeInDatabase('contacts', ['id' => $contactId, 'first_name' => 'Baru', 'whatsapp' => '081122334455']);
        $this->seeInDatabase('audit_logs', ['action' => 'update', 'entity' => 'contact', 'entity_id' => $contactId]);
    }

    public function testDaftarKontakSesuaiCakupanDanEkspor(): void
    {
        $this->makeContact('andi.saputra', ['first_name' => 'Kontak', 'last_name' => 'Andi Saja']);
        $this->makeContact('maya.kusuma', ['first_name' => 'Kontak', 'last_name' => 'Maya Saja']);

        $list = $this->actingAs('andi.saputra')->get('contacts');
        $list->assertSee('Kontak Andi Saja');
        $list->assertDontSee('Kontak Maya Saja');

        $this->actingAs('andi.saputra')->get('contacts/export')->assertStatus(403);
        $this->actingAs('andi.saputra')->get('companies/export')->assertStatus(403);

        $csv = $this->actingAs('hendra.wijaya')->get('contacts/export')->response()->getBody();
        $this->assertStringContainsString('Maya Saja', $csv);
        $this->assertStringNotContainsString('Andi Saja', $csv);
    }
}
