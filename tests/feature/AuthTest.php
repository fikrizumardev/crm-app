<?php

use App\Models\UserModel;
use App\Services\AuthService;
use CodeIgniter\I18n\Time;
use Tests\Support\AppTestCase;

/**
 * @internal
 */
final class AuthTest extends AppTestCase
{
    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    public function testHalamanLoginTanpaFieldEmail(): void
    {
        $result = $this->get('login');

        $result->assertOK();
        $result->assertSee('name="username"');
        $result->assertSee('name="password"');
        $result->assertDontSee('name="email"');
        $result->assertDontSee('type="email"');
    }

    public function testTamuDiarahkanKeLogin(): void
    {
        $result = $this->get('/');

        $result->assertRedirectTo(site_url('login'));
        $this->assertSame(site_url('/'), $_SESSION['redirect_url'] ?? null);
    }

    public function testLoginSukses(): void
    {
        $result = $this->post('login', ['username' => 'Andi.Saputra', 'password' => 'Sales#2026']);

        $result->assertRedirectTo(site_url('/'));
        $result->assertSessionHas('user_id', $this->user('andi.saputra')->id);
        $result->assertSessionHas('role', 'sales');
        $result->assertSessionHas('username', 'andi.saputra');
        $result->assertSessionHas('name', 'Andi Saputra');

        $this->assertNotNull($this->user('andi.saputra')->last_login_at);
        $this->seeInDatabase('login_attempts', ['username' => 'andi.saputra', 'success' => 1]);
        $this->seeInDatabase('audit_logs', ['action' => 'login', 'entity' => 'auth', 'user_id' => $this->user('andi.saputra')->id]);
    }

    public function testLoginKembaliKeHalamanTujuan(): void
    {
        $result = $this->withSession(['redirect_url' => site_url('profile')])
            ->post('login', ['username' => 'andi.saputra', 'password' => 'Sales#2026']);

        $result->assertRedirectTo(site_url('profile'));
    }

    public function testRedirectKeLuarAplikasiDiabaikan(): void
    {
        $result = $this->withSession(['redirect_url' => 'https://evil.example.net/phish'])
            ->post('login', ['username' => 'andi.saputra', 'password' => 'Sales#2026']);

        $result->assertRedirectTo(site_url('/'));
    }

    public function testPasswordSalahPesanGenerik(): void
    {
        $result = $this->post('login', ['username' => 'andi.saputra', 'password' => 'salah']);

        $result->assertRedirectTo(site_url('login'));
        $result->assertSessionHas('error', AuthService::MSG_INVALID);
        $result->assertSessionMissing('user_id');
        $this->seeInDatabase('login_attempts', ['username' => 'andi.saputra', 'success' => 0]);
        $this->seeInDatabase('audit_logs', ['action' => 'login_failed', 'entity' => 'auth']);
    }

    public function testUsernameTidakDikenalPesanSama(): void
    {
        $result = $this->post('login', ['username' => 'tidak.ada', 'password' => 'Sales#2026']);

        $result->assertSessionHas('error', AuthService::MSG_INVALID);
        $result->assertSessionMissing('user_id');
    }

    public function testFieldWajibDiisi(): void
    {
        $result = $this->post('login', ['username' => '', 'password' => '']);

        $result->assertRedirectTo(site_url('login'));
        $errors = $_SESSION['errors'] ?? [];
        $this->assertSame('Username wajib diisi.', $errors['username'] ?? null);
        $this->assertSame('Password wajib diisi.', $errors['password'] ?? null);
    }

    public function testThrottleSetelahLimaKaliGagal(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('login', ['username' => 'andi.saputra', 'password' => 'salah' . $i]);
        }

        // Password benar pun ditolak saat terkunci.
        $result = $this->post('login', ['username' => 'andi.saputra', 'password' => 'Sales#2026']);

        $result->assertSessionMissing('user_id');
        $this->assertStringContainsString('Terlalu banyak percobaan login', (string) $_SESSION['error']);
        $this->seeInDatabase('audit_logs', ['action' => 'login_locked']);

        // Username lain dari IP yang sama tidak ikut terkunci.
        $this->post('login', ['username' => 'siti.rahma', 'password' => 'Sales#2026'])
            ->assertSessionHas('user_id', $this->user('siti.rahma')->id);
    }

    public function testKunciBerakhirSetelahLimaBelasMenit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('login', ['username' => 'andi.saputra', 'password' => 'salah']);
        }

        $this->assertGreaterThan(0, service('auth')->lockoutRemaining('andi.saputra', '0.0.0.0'));

        Time::setTestNow(Time::now()->addMinutes(16));

        $this->assertSame(0, service('auth')->lockoutRemaining('andi.saputra', '0.0.0.0'));
    }

    public function testUserNonaktifTidakBisaLogin(): void
    {
        $user = $this->user('budi.santoso');
        model(UserModel::class)->update($user->id, ['is_active' => 0]);

        $result = $this->post('login', ['username' => 'budi.santoso', 'password' => 'Sales#2026']);

        $result->assertSessionMissing('user_id');
        $result->assertSessionHas('error', AuthService::MSG_INACTIVE);
    }

    public function testSessionUserYangDinonaktifkanDiakhiri(): void
    {
        $user = $this->user('budi.santoso');
        model(UserModel::class)->update($user->id, ['is_active' => 0]);

        $result = $this->actingAs('budi.santoso')->get('/');

        $result->assertRedirectTo(site_url('login'));
    }

    public function testPasswordDiRehashBilaPerlu(): void
    {
        $user    = $this->user('putri.ayu');
        $oldHash = password_hash('Sales#2026', PASSWORD_BCRYPT, ['cost' => 4]);
        $this->db->table('users')->where('id', $user->id)->update(['password_hash' => $oldHash]);

        $this->post('login', ['username' => 'putri.ayu', 'password' => 'Sales#2026'])
            ->assertSessionHas('user_id', $user->id);

        $newHash = $this->user('putri.ayu')->password_hash;
        $this->assertNotSame($oldHash, $newHash);
        $this->assertFalse(password_needs_rehash($newHash, PASSWORD_DEFAULT));
        $this->assertTrue(password_verify('Sales#2026', $newHash));
    }

    public function testUserLoginDiarahkanDariHalamanLogin(): void
    {
        $this->actingAs('admin')->get('login')->assertRedirectTo(site_url('/'));
    }

    public function testLogoutMenghapusSession(): void
    {
        $result = $this->actingAs('andi.saputra')->post('logout');

        $result->assertRedirectTo(site_url('login'));
        $result->assertSessionMissing('user_id');
        $result->assertSessionHas('success', 'Anda telah keluar.');
    }

    public function testLogoutHanyaLewatPost(): void
    {
        $this->actingAs('andi.saputra')->get('logout')->assertStatus(404);
    }
}
