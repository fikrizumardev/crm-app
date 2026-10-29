<?php

use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Models\SettingModel;
use App\Models\UserModel;
use Tests\Support\AppTestCase;

/**
 * Manajemen user, reassign, pengaturan, master data, dan log audit (T4.05–T4.09).
 *
 * @internal
 */
final class AdminTest extends AppTestCase
{
    public function testHalamanAdminHanyaUntukAdmin(): void
    {
        foreach (['admin/users', 'admin/settings', 'admin/master', 'admin/audit-logs'] as $path) {
            $this->actingAs('dewi.lestari')->get($path)->assertStatus(403);
            $this->actingAs('andi.saputra')->get($path)->assertStatus(403);
            $this->actingAs('admin')->get($path)->assertOK();
        }
    }

    public function testBuatPenggunaDenganPasswordOtomatis(): void
    {
        $result = $this->actingAs('admin')->post('admin/users', [
            'name'       => 'Rina Marlina',
            'username'   => 'Rina.Marlina',
            'role'       => 'sales',
            'manager_id' => (string) $this->user('dewi.lestari')->id,
            'password'   => '',
        ]);

        $result->assertRedirectTo(site_url('admin/users'));
        $generated = $_SESSION['generated_password'] ?? null;
        $this->assertSame('rina.marlina', $generated['username']);

        $user = $this->user('rina.marlina');
        $this->assertTrue($user->must_change_password);
        $this->assertTrue($user->verifyPassword($generated['password']));
        $this->assertMatchesRegularExpression('/^(?=.*[A-Za-z])(?=.*\d).{10}$/', $generated['password']);
        $this->assertSame($this->user('dewi.lestari')->id, $user->manager_id);

        // Login pertama diarahkan untuk ganti password.
        $this->post('login', ['username' => 'rina.marlina', 'password' => $generated['password']]);
        $this->actingAs('rina.marlina')->get('/')->assertRedirectTo(site_url('profile/password'));
    }

    public function testValidasiPenggunaBaru(): void
    {
        $this->actingAs('admin')->post('admin/users', ['name' => 'X', 'username' => 'root', 'role' => 'sales', 'password' => 'pendek']);
        $this->assertArrayHasKey('manager_id', $this->flashErrors());
        $this->assertArrayHasKey('password', $this->flashErrors());

        $this->actingAs('admin')->post('admin/users', [
            'name' => 'Pengguna Root', 'username' => 'root', 'role' => 'manager', 'password' => 'Rahasia2026',
        ]);
        $this->assertArrayHasKey('username', $this->flashErrors());

        $this->actingAs('admin')->post('admin/users', [
            'name' => 'Pengguna Baru', 'username' => 'pengguna.baru', 'role' => 'manager', 'password' => 'pendek',
        ]);
        $this->assertArrayHasKey('password', $this->flashErrors());
        $this->assertNull(model(UserModel::class)->findByUsername('pengguna.baru'));
    }

    public function testUbahPenggunaTermasukUsernameAdminAwal(): void
    {
        $admin = $this->user('admin');

        $this->actingAs('admin')->post('admin/users/' . $admin->id, [
            'name' => 'Rizky Pratama S.Kom', 'username' => 'admin', 'role' => 'admin', 'email' => '', 'phone' => '',
        ])->assertRedirectTo(site_url('admin/users'));

        $this->assertSame('Rizky Pratama S.Kom', $this->user('admin')->name);

        $andi = $this->user('andi.saputra');
        $this->actingAs('admin')->post('admin/users/' . $andi->id, [
            'name' => 'Andi Saputra', 'username' => 'andi.s', 'role' => 'sales', 'manager_id' => (string) $this->user('hendra.wijaya')->id,
        ]);
        $updated = model(UserModel::class)->find($andi->id);
        $this->assertSame('andi.s', $updated->username);
        $this->assertSame($this->user('hendra.wijaya')->id, $updated->manager_id);
    }

    public function testManagerYangPunyaTimTidakBisaDiubahPerannya(): void
    {
        $dewi = $this->user('dewi.lestari');

        $this->actingAs('admin')->post('admin/users/' . $dewi->id, [
            'name' => 'Dewi Lestari', 'username' => 'dewi.lestari', 'role' => 'sales', 'manager_id' => (string) $this->user('hendra.wijaya')->id,
        ]);

        $this->assertStringContainsString('memimpin tim', (string) $_SESSION['error']);
        $this->assertTrue($this->user('dewi.lestari')->isManager());
    }

    public function testTidakBisaMenonaktifkanDiriSendiriAtauAdminTerakhir(): void
    {
        $admin = $this->user('admin');

        $this->actingAs('admin')->post('admin/users/' . $admin->id . '/deactivate');
        $this->assertSame('Anda tidak bisa menonaktifkan akun sendiri.', $_SESSION['error'] ?? null);
        $this->assertTrue($this->user('admin')->is_active);

        // Admin kedua mencoba menonaktifkan/menurunkan admin terakhir yang aktif.
        $other = new User(['id' => 999, 'role' => 'admin', 'name' => 'Admin Lain']);

        try {
            service('userService')->setActive($admin, false, $other);
            $this->fail('Seharusnya ditolak');
        } catch (BusinessRuleException $e) {
            $this->assertSame('Admin terakhir tidak bisa dinonaktifkan.', $e->getMessage());
        }

        $this->expectException(BusinessRuleException::class);
        service('userService')->update($admin, ['name' => 'Rizky', 'username' => 'admin', 'role' => 'manager'], $other);
    }

    public function testNonaktifkanDanAktifkanPengguna(): void
    {
        $budi = $this->user('budi.santoso');

        $this->actingAs('admin')->post('admin/users/' . $budi->id . '/deactivate')->assertRedirectTo(site_url('admin/users'));
        $this->assertFalse($this->user('budi.santoso')->is_active);
        $this->actingAs('budi.santoso')->get('/')->assertRedirectTo(site_url('login'));

        $this->actingAs('admin')->post('admin/users/' . $budi->id . '/activate');
        $this->assertTrue($this->user('budi.santoso')->is_active);
        $this->seeInDatabase('audit_logs', ['action' => 'deactivate', 'entity' => 'user', 'entity_id' => $budi->id]);
    }

    public function testResetPasswordSatuKlik(): void
    {
        $siti = $this->user('siti.rahma');

        for ($i = 0; $i < 5; $i++) {
            $this->post('login', ['username' => 'siti.rahma', 'password' => 'salah']);
        }

        $this->actingAs('admin')->post('admin/users/' . $siti->id . '/reset-password')->assertRedirectTo(site_url('admin/users'));

        $password = $_SESSION['generated_password']['password'];
        $updated  = $this->user('siti.rahma');
        $this->assertTrue($updated->must_change_password);
        $this->assertFalse($updated->verifyPassword('Sales#2026'));

        // Kunci login dibuka sehingga password baru langsung bisa dipakai.
        $this->withSession([])->post('login', ['username' => 'siti.rahma', 'password' => $password])
            ->assertSessionHas('user_id', $siti->id);
    }

    public function testReassignMassalOlehManager(): void
    {
        $a = $this->makeLead('andi.saputra', ['name' => 'Lead A']);
        $b = $this->makeLead('andi.saputra', ['name' => 'Lead B']);
        $c = $this->makeLead('putri.ayu', ['name' => 'Lead Tim Lain']);
        $siti = $this->user('siti.rahma')->id;

        $this->actingAs('dewi.lestari')->post('leads/reassign', ['ids' => [(string) $a, (string) $b, (string) $c], 'owner_id' => (string) $siti])
            ->assertRedirectTo(site_url('leads'));

        $this->seeInDatabase('leads', ['id' => $a, 'owner_id' => $siti]);
        $this->seeInDatabase('leads', ['id' => $b, 'owner_id' => $siti]);
        $this->seeInDatabase('leads', ['id' => $c, 'owner_id' => $this->user('putri.ayu')->id]);
        $this->assertSame('2 data berhasil dialihkan ke owner baru.', $_SESSION['success'] ?? null);
        $this->seeInDatabase('notifications', ['user_id' => $siti, 'type' => 'reassigned']);
        $this->assertSame(2, $this->db->table('audit_logs')->where('action', 'reassign')->countAllResults());
    }

    public function testReassignDitolakUntukSalesDanOwnerLuarTim(): void
    {
        $deal = $this->makeDeal('andi.saputra');

        $this->actingAs('andi.saputra')->post('deals/reassign', ['ids' => [(string) $deal], 'owner_id' => (string) $this->user('siti.rahma')->id])->assertStatus(403);

        $this->actingAs('dewi.lestari')->post('deals/reassign', ['ids' => [(string) $deal], 'owner_id' => (string) $this->user('putri.ayu')->id]);
        $this->assertStringContainsString('owner baru', (string) $_SESSION['error']);
        $this->seeInDatabase('deals', ['id' => $deal, 'owner_id' => $this->user('andi.saputra')->id]);

        $this->actingAs('admin')->post('deals/reassign', ['ids' => [(string) $deal], 'owner_id' => (string) $this->user('putri.ayu')->id]);
        $this->seeInDatabase('deals', ['id' => $deal, 'owner_id' => $this->user('putri.ayu')->id]);
    }

    public function testPengaturanAplikasi(): void
    {
        $this->actingAs('admin')->post('admin/settings', [
            'company_name'    => 'PT Maju Bersama Digital',
            'company_address' => 'Jl. Asia Afrika No. 8, Bandung',
            'company_phone'   => '022-4201234',
            'currency'        => 'idr',
            'tax_rate'        => '12,5',
        ])->assertRedirectTo(site_url('admin/settings'));

        SettingModel::clearCache();
        $settings = model(SettingModel::class);
        $this->assertSame('PT Maju Bersama Digital', $settings->getValue('company_name'));
        $this->assertSame('IDR', $settings->getValue('currency'));
        $this->assertSame('12.5', $settings->getValue('tax_rate'));
        $this->assertSame('0224201234', $settings->getValue('company_phone'));
        $this->seeInDatabase('audit_logs', ['action' => 'update', 'entity' => 'settings']);

        $this->actingAs('admin')->post('admin/settings', ['company_name' => '', 'currency' => 'RUPIAH', 'tax_rate' => '150']);
        $errors = $this->flashErrors();
        $this->assertArrayHasKey('company_name', $errors);
        $this->assertArrayHasKey('currency', $errors);
        $this->assertArrayHasKey('tax_rate', $errors);
    }

    public function testMasterDataSederhana(): void
    {
        $this->actingAs('admin')->post('admin/master/sources', ['name' => 'TikTok']);
        $id = $this->masterId('lead_sources', 'TikTok');
        $this->assertGreaterThan(0, $id);

        $this->actingAs('admin')->post('admin/master/sources', ['name' => 'tiktok']);
        $this->assertStringContainsString('sudah ada', (string) $_SESSION['error']);

        $this->actingAs('admin')->post("admin/master/sources/{$id}", ['name' => 'TikTok Ads']);
        $this->seeInDatabase('lead_sources', ['id' => $id, 'name' => 'TikTok Ads']);

        $this->makeLead('andi.saputra', ['source_id' => $id]);
        $this->actingAs('admin')->post("admin/master/sources/{$id}/delete");
        $this->assertStringContainsString('masih dipakai', (string) $_SESSION['error']);
        $this->seeInDatabase('lead_sources', ['id' => $id]);

        $this->actingAs('admin')->post('admin/master/industries', ['name' => 'Pertambangan']);
        $industry = $this->masterId('industries', 'Pertambangan');
        $this->actingAs('admin')->post("admin/master/industries/{$industry}/delete");
        $this->dontSeeInDatabase('industries', ['id' => $industry]);
    }

    public function testTahapPipeline(): void
    {
        $this->actingAs('admin')->post('admin/master/stages', ['name' => 'Demo', 'probability' => '40']);
        $demo = $this->masterId('pipeline_stages', 'Demo');
        $this->seeInDatabase('pipeline_stages', ['id' => $demo, 'probability' => 40, 'is_won' => 0]);

        // Urutan: Demo setelah Kualifikasi; Won/Lost tetap di akhir.
        $order = [];

        foreach (['Prospek', 'Kualifikasi', 'Demo', 'Proposal', 'Negosiasi'] as $name) {
            $order[] = (string) $this->masterId('pipeline_stages', $name);
        }

        $this->actingAs('admin')->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post('admin/master/stages/reorder', ['order' => $order])->assertOK();
        $names = array_column($this->db->table('pipeline_stages')->orderBy('sort_order')->get()->getResultArray(), 'name');
        $this->assertSame(['Prospek', 'Kualifikasi', 'Demo', 'Proposal', 'Negosiasi', 'Won', 'Lost'], $names);

        $this->actingAs('admin')->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])->post('admin/master/stages/reorder', ['order' => [$order[0]]])->assertStatus(422);

        // Tahap sistem tidak bisa dihapus; probabilitasnya tetap.
        $won = $this->masterId('pipeline_stages', 'Won');
        $this->actingAs('admin')->withHeaders([])->post("admin/master/stages/{$won}/delete");
        $this->seeInDatabase('pipeline_stages', ['id' => $won]);
        $this->actingAs('admin')->post("admin/master/stages/{$won}", ['name' => 'Menang', 'probability' => '50']);
        $this->seeInDatabase('pipeline_stages', ['id' => $won, 'name' => 'Menang', 'probability' => 100]);

        // Tahap yang dipakai deal tidak bisa dihapus.
        $this->makeDeal('andi.saputra', 'Demo');
        $this->actingAs('admin')->post("admin/master/stages/{$demo}/delete");
        $this->assertStringContainsString('masih dipakai', (string) $_SESSION['error']);
        $this->seeInDatabase('pipeline_stages', ['id' => $demo]);
    }

    public function testLogAuditDenganFilterDanDiff(): void
    {
        $leadId = $this->makeLead('andi.saputra', ['name' => 'Lama']);
        $this->actingAs('andi.saputra')->post('leads/' . $leadId, ['name' => 'Baru Diubah', 'status' => 'contacted']);

        $result = $this->actingAs('admin')->get('admin/audit-logs?entity=lead&action=update');
        $result->assertOK();
        $result->assertSee('Baru Diubah');
        $result->assertSee('Lama');
        $result->assertSee('Andi Saputra');
        $result->assertDontSee('login_failed');
    }
}
