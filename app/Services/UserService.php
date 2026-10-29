<?php

namespace App\Services;

use App\Entities\User;
use App\Exceptions\BusinessRuleException;
use App\Exceptions\ValidationFailedException;
use App\Models\UserModel;

/**
 * Manajemen user oleh admin (PRD §4 M9, §5).
 */
class UserService
{
    public function __construct(
        private readonly UserModel $users,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Password acak yang mudah dibaca (tanpa karakter mirip seperti 0/O, 1/l), selalu berisi huruf dan angka.
     */
    public static function generatePassword(int $length = 10): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits  = '23456789';
        $all     = $letters . $digits;
        $chars   = [$letters[random_int(0, strlen($letters) - 1)], $digits[random_int(0, strlen($digits) - 1)]];

        while (count($chars) < $length) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        shuffle($chars);

        return implode('', $chars);
    }

    /**
     * Buat user baru dengan kewajiban ganti password saat login pertama.
     *
     * @param array<string, mixed> $input
     *
     * @return array{id: int, password: string, generated: bool}
     *
     * @throws ValidationFailedException
     */
    public function create(array $input, User $actor): array
    {
        $password  = trim((string) ($input['password'] ?? ''));
        $generated = $password === '';
        $password  = $generated ? self::generatePassword() : $password;

        // Kumpulkan semua error agar form menampilkan semuanya sekaligus.
        $errors = [];

        try {
            $data = $this->prepare($input);
        } catch (ValidationFailedException $e) {
            $errors = $e->getErrors();
        }

        if (! $generated && ! service('validation')->check($password, 'strong_password')) {
            $errors['password'] = 'Password minimal 8 karakter dan harus mengandung huruf serta angka.';
        }

        if ($errors !== []) {
            throw new ValidationFailedException($errors);
        }

        $data += [
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'is_active'            => 1,
            'must_change_password' => 1,
        ];

        $id = $this->users->insert($data);

        if ($id === false) {
            throw new ValidationFailedException($this->users->errors());
        }

        $this->audit->log('create', 'user', $id, null, $this->users->find($id));

        return ['id' => (int) $id, 'password' => $password, 'generated' => $generated];
    }

    /**
     * @param array<string, mixed> $input
     *
     * @throws BusinessRuleException|ValidationFailedException
     */
    public function update(User $user, array $input, User $actor): void
    {
        $data = $this->prepare($input);

        if ($user->id === $actor->id && $data['role'] !== $user->role) {
            throw new BusinessRuleException('Anda tidak bisa mengubah peran akun sendiri.');
        }

        if ($user->isAdmin() && $data['role'] !== User::ROLE_ADMIN && $this->activeAdminCount() <= 1) {
            throw new BusinessRuleException('Minimal harus ada satu admin aktif.');
        }

        if ($user->isManager() && $data['role'] !== User::ROLE_MANAGER && $this->teamSize($user) > 0) {
            throw new BusinessRuleException('Manager ini masih memimpin tim. Pindahkan anggota timnya ke manager lain terlebih dahulu.');
        }

        // Username yang tidak berubah tidak divalidasi ulang (akun awal `admin` memakai kata cadangan).
        if ($data['username'] === $user->username) {
            unset($data['username']);
        }

        $old = clone $user;

        if (! $this->users->update($user->id, ['id' => $user->id] + $data)) {
            throw new ValidationFailedException($this->users->errors());
        }

        $this->audit->log('update', 'user', $user->id, $old, $this->users->find($user->id));
    }

    /**
     * Aktifkan/nonaktifkan user. User nonaktif langsung keluar pada request berikutnya.
     *
     * @throws BusinessRuleException
     */
    public function setActive(User $user, bool $active, User $actor): void
    {
        if (! $active && $user->id === $actor->id) {
            throw new BusinessRuleException('Anda tidak bisa menonaktifkan akun sendiri.');
        }

        if (! $active && $user->isAdmin() && $user->is_active && $this->activeAdminCount() <= 1) {
            throw new BusinessRuleException('Admin terakhir tidak bisa dinonaktifkan.');
        }

        $this->users->skipValidation()->update($user->id, ['is_active' => $active ? 1 : 0]);
        $this->users->skipValidation(false);
        $this->audit->log($active ? 'activate' : 'deactivate', 'user', $user->id, ['is_active' => (int) $user->is_active], ['is_active' => $active ? 1 : 0]);
    }

    /**
     * Reset password ke password acak dan wajibkan ganti saat login berikutnya.
     */
    public function resetPassword(User $user, User $actor): string
    {
        $password = self::generatePassword();

        $this->users->skipValidation()->update($user->id, [
            'password_hash'        => password_hash($password, PASSWORD_DEFAULT),
            'must_change_password' => 1,
        ]);
        $this->users->skipValidation(false);

        // Buka kunci login yang mungkin aktif setelah percobaan gagal berulang.
        db_connect()->table('login_attempts')->where('username', $user->username)->where('success', 0)->delete();

        $this->audit->log('reset_password', 'user', $user->id, null, ['must_change_password' => 1]);

        return $password;
    }

    public function activeAdminCount(): int
    {
        return $this->users->where('role', User::ROLE_ADMIN)->where('is_active', 1)->countAllResults();
    }

    public function teamSize(User $manager): int
    {
        return $this->users->where('manager_id', $manager->id)->countAllResults();
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     *
     * @throws ValidationFailedException
     */
    private function prepare(array $input): array
    {
        $role      = (string) ($input['role'] ?? '');
        $managerId = ctype_digit((string) ($input['manager_id'] ?? '')) ? (int) $input['manager_id'] : null;

        if ($role === User::ROLE_SALES) {
            $manager = $managerId === null ? null : $this->users->find($managerId);

            if ($manager === null || ! $manager->isManager()) {
                throw new ValidationFailedException(['manager_id' => 'Pilih manager untuk sales ini.']);
            }
        } else {
            $managerId = null;
        }

        $email = trim((string) ($input['email'] ?? ''));

        return [
            'username'   => strtolower(trim((string) ($input['username'] ?? ''))),
            'name'       => trim((string) ($input['name'] ?? '')),
            'email'      => $email === '' ? null : strtolower($email),
            'phone'      => normalize_phone($input['phone'] ?? null),
            'role'       => $role,
            'manager_id' => $managerId,
        ];
    }
}
