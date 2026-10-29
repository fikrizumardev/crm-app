<?php

use Tests\Support\AppTestCase;

/**
 * @internal
 */
final class ProfileTest extends AppTestCase
{
    public function testHalamanProfilMenampilkanUsernameReadOnly(): void
    {
        $result = $this->actingAs('andi.saputra')->get('profile');

        $result->assertOK();
        $result->assertSee('andi.saputra');
        $result->assertSee('readonly');
    }

    public function testUpdateProfil(): void
    {
        $result = $this->actingAs('andi.saputra')->post('profile', [
            'name'     => 'Andi Saputra Wijaya',
            'phone'    => '+62 812-9999-0000',
            'email'    => 'andi@contoh.co.id',
            'username' => 'hacker', // diabaikan: username tidak bisa diubah sendiri
        ]);

        $result->assertRedirectTo(site_url('profile'));
        $user = $this->user('andi.saputra');
        $this->assertSame('Andi Saputra Wijaya', $user->name);
        $this->assertSame('081299990000', $user->phone);
        $this->assertSame('andi@contoh.co.id', $user->email);
        $this->seeInDatabase('audit_logs', ['action' => 'update', 'entity' => 'user', 'entity_id' => $user->id]);
    }

    public function testUpdateProfilValidasi(): void
    {
        $this->actingAs('andi.saputra')->post('profile', ['name' => '', 'email' => 'bukan-email']);

        $errors = $_SESSION['errors'] ?? [];
        $this->assertArrayHasKey('name', $errors);
        $this->assertArrayHasKey('email', $errors);
        $this->assertSame('Andi Saputra', $this->user('andi.saputra')->name);
    }

    public function testGantiPasswordSukses(): void
    {
        $result = $this->actingAs('siti.rahma')->post('profile/password', [
            'current_password' => 'Sales#2026',
            'new_password'     => 'BaruSekali99',
            'confirm_password' => 'BaruSekali99',
        ]);

        $result->assertRedirectTo(site_url('/'));
        $this->assertTrue($this->user('siti.rahma')->verifyPassword('BaruSekali99'));
        $this->seeInDatabase('audit_logs', ['action' => 'password_changed', 'entity' => 'user']);
    }

    public function testGantiPasswordDitolakBilaPasswordLamaSalah(): void
    {
        $this->actingAs('siti.rahma')->post('profile/password', [
            'current_password' => 'Salah#2026',
            'new_password'     => 'BaruSekali99',
            'confirm_password' => 'BaruSekali99',
        ]);

        $this->assertSame('Password saat ini salah.', $_SESSION['errors']['current_password'] ?? null);
        $this->assertTrue($this->user('siti.rahma')->verifyPassword('Sales#2026'));
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string}>
     */
    public static function passwordLemahProvider(): iterable
    {
        yield 'terlalu pendek' => ['abc12', 'abc12', 'new_password'];
        yield 'tanpa angka' => ['HanyaHurufSaja', 'HanyaHurufSaja', 'new_password'];
        yield 'tanpa huruf' => ['1234567890', '1234567890', 'new_password'];
        yield 'konfirmasi beda' => ['BaruSekali99', 'BaruSekali98', 'confirm_password'];
    }

    /**
     * @dataProvider passwordLemahProvider
     */
    public function testGantiPasswordValidasi(string $new, string $confirm, string $field): void
    {
        $this->actingAs('siti.rahma')->post('profile/password', [
            'current_password' => 'Sales#2026',
            'new_password'     => $new,
            'confirm_password' => $confirm,
        ]);

        $this->assertArrayHasKey($field, $_SESSION['errors'] ?? []);
        $this->assertTrue($this->user('siti.rahma')->verifyPassword('Sales#2026'));
    }
}
