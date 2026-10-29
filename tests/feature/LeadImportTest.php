<?php

use App\Exceptions\BusinessRuleException;
use Tests\Support\AppTestCase;

/**
 * Impor CSV lead (T2.09).
 *
 * @internal
 */
final class LeadImportTest extends AppTestCase
{
    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->file = tempnam(sys_get_temp_dir(), 'lead') . '.csv';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    public function testUnduhTemplate(): void
    {
        $result = $this->actingAs('andi.saputra')->get('leads/import/template');

        $result->assertOK();
        $body = $result->response()->getBody();
        $this->assertStringStartsWith("\xEF\xBB\xBF" . 'nama,perusahaan,jabatan,no_hp,email,sumber,status,catatan,owner_username', $body);
    }

    public function testImporValidasiPerBaris(): void
    {
        $this->makeContact('siti.rahma', ['phone' => '081300000000']);

        file_put_contents($this->file, implode("\n", [
            "\xEF\xBB\xBFnama;perusahaan;jabatan;no_hp;email;sumber;status;catatan",
            'Rahmat Hidayat;PT Cahaya;Manajer;0812-1111-2222;rahmat@cahaya.co.id;Pameran;Dihubungi;Minta demo',
            'Sari Dewi;CV Sari;;0812-1111-3333;;;;',
            ';PT Tanpa Nama;;;;;;',
            'Joko Susilo;PT Joko;;0812-4444;;Radio;Baru;',
            'Duplikat Kontak;PT X;;0813-0000-0000;;;;',
            'Duplikat Baris;PT Y;;0812-1111-2222;;;;',
            'Status Salah;PT Z;;;;;Dikonversi;',
        ]));

        $result = service('leadImport')->import($this->file, $this->user('andi.saputra'));

        $this->assertSame(2, $result['imported']);
        $this->assertSame([4, 5, 6, 7, 8], array_column($result['failed'], 'line'));

        $errors = array_column($result['failed'], 'errors', 'line');
        $this->assertStringContainsString('Nama wajib diisi', implode(' ', $errors[4]));
        $this->assertStringContainsString('Sumber "Radio" tidak dikenal', implode(' ', $errors[5]));
        $this->assertStringContainsString('Duplikat', implode(' ', $errors[6]));
        $this->assertStringContainsString('Duplikat dengan baris lain', implode(' ', $errors[7]));
        $this->assertStringContainsString('Dikonversi tidak bisa diimpor', implode(' ', $errors[8]));

        $this->seeInDatabase('leads', [
            'name'     => 'Rahmat Hidayat',
            'status'   => 'contacted',
            'phone'    => '081211112222',
            'owner_id' => $this->user('andi.saputra')->id,
        ]);
        $this->seeInDatabase('leads', ['name' => 'Sari Dewi', 'status' => 'new']);
        $this->seeInDatabase('audit_logs', ['action' => 'import', 'entity' => 'lead']);
    }

    public function testManagerBisaMenentukanOwnerTimnya(): void
    {
        file_put_contents($this->file, "nama,owner_username\nLead Budi,budi.santoso\nLead Putri,putri.ayu\n");

        $result = service('leadImport')->import($this->file, $this->user('dewi.lestari'));

        $this->assertSame(1, $result['imported']);
        $this->seeInDatabase('leads', ['name' => 'Lead Budi', 'owner_id' => $this->user('budi.santoso')->id]);
        $this->assertStringContainsString('di luar tim Anda', implode(' ', $result['failed'][0]['errors']));
    }

    public function testFileTanpaKolomNamaDitolak(): void
    {
        file_put_contents($this->file, "perusahaan,email\nPT A,a@a.co.id\n");

        $this->expectException(BusinessRuleException::class);

        service('leadImport')->import($this->file, $this->user('andi.saputra'));
    }

    public function testUnggahTanpaFileDitolak(): void
    {
        $this->actingAs('andi.saputra')->post('leads/import')->assertRedirectTo(site_url('leads/import'));

        $this->assertArrayHasKey('file', $this->flashErrors());
    }
}
