<?php

use CodeIgniter\Security\Exceptions\SecurityException;
use Tests\Support\AppTestCase;

/**
 * @internal
 */
final class SecurityTest extends AppTestCase
{
    protected bool $withCsrf = true;

    public function testFormTanpaTokenCsrfDitolak(): void
    {
        $this->expectException(SecurityException::class);

        $this->post('login', ['username' => 'andi.saputra', 'password' => 'Sales#2026']);
    }

    public function testHeaderKeamananDikirim(): void
    {
        $result = $this->get('login');

        $this->assertSame('SAMEORIGIN', $result->response()->getHeaderLine('X-Frame-Options'));
        $this->assertSame('nosniff', $result->response()->getHeaderLine('X-Content-Type-Options'));
    }

    public function testHalamanMemuatMetaCsrfDanTokenForm(): void
    {
        $result = $this->get('login');

        $result->assertSee('<meta name="csrf-token"');
        $result->assertSee('name="csrf_token"');
    }
}
