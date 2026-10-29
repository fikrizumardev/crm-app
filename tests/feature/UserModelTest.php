<?php

use App\Entities\User;
use App\Models\UserModel;
use Tests\Support\AppTestCase;

/**
 * @internal
 */
final class UserModelTest extends AppTestCase
{
    private function newUser(array $overrides = []): User
    {
        $user = new User(array_merge([
            'username' => 'rina.marlina',
            'name'     => 'Rina Marlina',
            'role'     => 'sales',
        ], $overrides));
        $user->password = 'Rahasia123';

        return $user;
    }

    public function testUsernameDisimpanLowercaseDanPasswordDiHash(): void
    {
        $model = model(UserModel::class);
        $id    = $model->insert($this->newUser(['username' => '  Rina.Marlina ']));

        $this->assertIsInt($id);
        $saved = $model->find($id);
        $this->assertSame('rina.marlina', $saved->username);
        $this->assertNotSame('Rahasia123', $saved->password_hash);
        $this->assertTrue($saved->verifyPassword('Rahasia123'));
    }

    public function testFormatUsernameDivalidasi(): void
    {
        $model = model(UserModel::class);

        foreach (['abc', 'nama dengan spasi', 'nama-strip', str_repeat('a', 31), 'tanda!seru'] as $invalid) {
            $this->assertFalse($model->insert($this->newUser(['username' => $invalid])), "Username '{$invalid}' seharusnya ditolak");
            $this->assertArrayHasKey('username', $model->errors());
        }
    }

    public function testUsernameCadanganDanDuplikatDitolak(): void
    {
        $model = model(UserModel::class);

        foreach (['root', 'system', 'api', 'admin'] as $reserved) {
            $this->assertFalse($model->insert($this->newUser(['username' => $reserved])));
            $this->assertArrayHasKey('username', $model->errors());
        }

        $this->assertFalse($model->insert($this->newUser(['username' => 'andi.saputra'])));
        $this->assertSame('Username sudah dipakai.', $model->errors()['username']);
    }

    public function testCakupanDataPerPeran(): void
    {
        $model = model(UserModel::class);

        $this->assertNull($model->scopeIds($this->user('admin')));

        $dewiScope = $model->scopeIds($this->user('dewi.lestari'));
        sort($dewiScope);
        $expected = [
            $this->user('dewi.lestari')->id,
            $this->user('andi.saputra')->id,
            $this->user('siti.rahma')->id,
            $this->user('budi.santoso')->id,
        ];
        sort($expected);
        $this->assertSame($expected, $dewiScope);

        $this->assertSame([$this->user('andi.saputra')->id], $model->scopeIds($this->user('andi.saputra')));
    }

    public function testHelperPeranEntity(): void
    {
        $this->assertTrue($this->user('admin')->isAdmin());
        $this->assertTrue($this->user('hendra.wijaya')->isManager());
        $this->assertTrue($this->user('maya.kusuma')->isSales());
        $this->assertSame('DL', $this->user('dewi.lestari')->initials());
    }
}
