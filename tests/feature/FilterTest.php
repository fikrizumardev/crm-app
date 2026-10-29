<?php

use App\Models\UserModel;
use Tests\Support\AppTestCase;

/**
 * @internal
 */
final class FilterTest extends AppTestCase
{
    private function withAdminRoute(): static
    {
        return $this->withRoutes([
            ['GET', 'admin/ping', static fn () => 'pong-admin', ['filter' => ['auth', 'forcepassword', 'role:admin']]],
            ['GET', 'reports/ping', static fn () => 'pong-report', ['filter' => ['auth', 'forcepassword', 'role:admin,manager']]],
        ]);
    }

    public function testRuteAdminDitolakUntukSales(): void
    {
        $result = $this->withAdminRoute()->actingAs('andi.saputra')->get('admin/ping');

        $result->assertStatus(403);
        $result->assertSee('Akses ditolak');
    }

    public function testRuteAdminDitolakUntukManager(): void
    {
        $this->withAdminRoute()->actingAs('dewi.lestari')->get('admin/ping')->assertStatus(403);
    }

    public function testRuteAdminBisaDibukaAdmin(): void
    {
        $result = $this->withAdminRoute()->actingAs('admin')->get('admin/ping');

        $result->assertOK();
        $result->assertSee('pong-admin');
    }

    public function testRoleBanyakArgumen(): void
    {
        $this->withAdminRoute()->actingAs('dewi.lestari')->get('reports/ping')->assertSee('pong-report');
        $this->withAdminRoute()->actingAs('andi.saputra')->get('reports/ping')->assertStatus(403);
    }

    public function testRoleFilterMengembalikanJsonUntukAjax(): void
    {
        $result = $this->withAdminRoute()
            ->actingAs('andi.saputra')
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get('admin/ping');

        $result->assertStatus(403);
        $result->assertJSONFragment(['message' => 'Anda tidak memiliki akses ke halaman ini.']);
    }

    public function testWajibGantiPasswordMemblokirHalamanLain(): void
    {
        $user = $this->user('andi.saputra');
        model(UserModel::class)->update($user->id, ['must_change_password' => 1]);

        $this->actingAs('andi.saputra')->get('/')->assertRedirectTo(site_url('profile/password'));
        $this->actingAs('andi.saputra')->get('profile')->assertRedirectTo(site_url('profile/password'));
        $this->actingAs('andi.saputra')->get('profile/password')->assertOK();
    }

    public function testSetelahGantiPasswordBisaAksesLagi(): void
    {
        $user = $this->user('andi.saputra');
        model(UserModel::class)->update($user->id, ['must_change_password' => 1]);

        $this->actingAs('andi.saputra')->post('profile/password', [
            'current_password' => 'Sales#2026',
            'new_password'     => 'PasswordBaru2026',
            'confirm_password' => 'PasswordBaru2026',
        ])->assertRedirectTo(site_url('/'));

        $this->assertFalse($this->user('andi.saputra')->must_change_password);
        $this->actingAs('andi.saputra')->get('/')->assertOK();
    }

    public function testMenuSidebarSesuaiPeran(): void
    {
        $admin = $this->actingAs('admin')->get('/');
        $admin->assertSee('Administrasi');
        $admin->assertSee('Laporan');

        $manager = $this->actingAs('dewi.lestari')->get('/');
        $manager->assertDontSee('Administrasi');
        $manager->assertSee('Laporan');

        $sales = $this->actingAs('andi.saputra')->get('/');
        $sales->assertDontSee('Administrasi');
        $sales->assertDontSee('Laporan');
    }

    public function testHalamanTidakDikenalMengembalikan404(): void
    {
        $result = $this->actingAs('admin')->get('halaman-tidak-ada');

        $result->assertStatus(404);
        $result->assertSee('Halaman tidak ditemukan');
    }
}
