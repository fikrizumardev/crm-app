<?php

namespace App\Services;

use App\Entities\User;
use App\Models\LoginAttemptModel;
use App\Models\UserModel;
use CodeIgniter\I18n\Time;
use Config\Auth;

/**
 * Autentikasi username + password berbasis session (PRD §5).
 */
class AuthService
{
    public const MSG_INVALID  = 'Username atau password salah.';
    public const MSG_INACTIVE = 'Akun Anda dinonaktifkan. Hubungi admin.';

    /**
     * Hash acak untuk menyamakan waktu respons saat username tidak ditemukan.
     */
    private const DUMMY_HASH = '$2y$10$57vHxlkkafMGudJaIiHhXuDmBTnmstw4vT3MqTTaKPKQQWD1xdumG';

    private ?User $user = null;

    public function __construct(
        private readonly UserModel $users,
        private readonly LoginAttemptModel $attempts,
        private readonly AuditLogger $audit,
        private readonly Auth $config,
    ) {
    }

    /**
     * Proses login: cek throttle, verifikasi password, lalu buat session.
     */
    public function attempt(string $username, string $password, string $ip, ?string $userAgent = null): LoginResult
    {
        $username = strtolower(trim($username));

        $lockedSeconds = $this->lockoutRemaining($username, $ip);

        if ($lockedSeconds > 0) {
            $this->audit->log('login_locked', 'auth', null, null, ['username' => $username], null);

            return LoginResult::failure(sprintf(
                'Terlalu banyak percobaan login gagal. Coba lagi dalam %d menit.',
                (int) ceil($lockedSeconds / 60),
            ));
        }

        $user = $this->users->findByUsername($username);

        // Tetap jalankan password_verify walau user tidak ada agar waktu respons seragam.
        $valid = $user !== null
            ? $user->verifyPassword($password)
            : password_verify($password, self::DUMMY_HASH);

        if (! $valid) {
            $this->recordFailure($username, $ip, $userAgent, $user);

            return LoginResult::failure(self::MSG_INVALID);
        }

        if (! $user->is_active) {
            $this->recordFailure($username, $ip, $userAgent, $user);

            return LoginResult::failure(self::MSG_INACTIVE);
        }

        $this->attempts->record($username, $ip, true, $userAgent);
        $this->completeLogin($user, $password);

        return LoginResult::success($user);
    }

    /**
     * Sisa waktu kunci (detik) untuk username + IP; 0 bila tidak terkunci.
     *
     * Terkunci bila ada `maxAttempts` kegagalan beruntun dalam `attemptWindow` detik,
     * dan kunci berlaku `lockoutTime` detik sejak kegagalan terakhir.
     */
    public function lockoutRemaining(string $username, string $ip): int
    {
        $failures = $this->attempts->recentFailures(
            strtolower(trim($username)),
            $ip,
            $this->config->attemptWindow + $this->config->lockoutTime,
            $this->config->maxAttempts,
        );

        if (count($failures) < $this->config->maxAttempts) {
            return 0;
        }

        $newest = $failures[0];
        $oldest = $failures[count($failures) - 1];

        if ($newest->getTimestamp() - $oldest->getTimestamp() > $this->config->attemptWindow) {
            return 0;
        }

        return max(0, $newest->getTimestamp() + $this->config->lockoutTime - Time::now()->getTimestamp());
    }

    /**
     * User yang sedang login; null bila belum login atau akunnya sudah tidak aktif.
     */
    public function user(): ?User
    {
        $id = session('user_id');

        if ($id === null) {
            $this->user = null;

            return null;
        }

        if ($this->user !== null && $this->user->id === (int) $id) {
            return $this->user;
        }

        $user = $this->users->find((int) $id);

        if ($user === null || ! $user->is_active) {
            $this->logout();

            return null;
        }

        // Peran/nama bisa diubah admin saat user masih login.
        session()->set(['role' => $user->role, 'name' => $user->name]);

        return $this->user = $user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Hapus seluruh data session dan buat ID session baru.
     */
    public function logout(): void
    {
        $session = session();
        $session->remove(array_keys($_SESSION ?? []));
        $session->regenerate(true);
        $this->user = null;
    }

    /**
     * Ganti password user dan cabut kewajiban ganti password.
     */
    public function changePassword(User $user, string $newPassword): void
    {
        $user->password             = $newPassword;
        $user->must_change_password = false;
        $this->users->save($user);

        $this->audit->log('password_changed', 'user', $user->id, null, null);

        if ($this->user !== null && $this->user->id === $user->id) {
            $this->user = $this->users->find($user->id);
        }
    }

    /**
     * Forget cached user (dipakai test dan saat data user diubah).
     */
    public function refresh(): void
    {
        $this->user = null;
    }

    private function completeLogin(User $user, string $password): void
    {
        $session = session();
        $session->regenerate(true);
        $session->set([
            'user_id'  => $user->id,
            'username' => $user->username,
            'role'     => $user->role,
            'name'     => $user->name,
        ]);

        $updates = ['last_login_at' => Time::now()->toDateTimeString()];

        if ($user->passwordNeedsRehash()) {
            $updates['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }

        $this->users->skipValidation()->update($user->id, $updates);
        $this->users->skipValidation(false);

        $this->user = $this->users->find($user->id);

        $this->audit->log('login', 'auth', $user->id, null, ['username' => $user->username], $user->id);

        // Pengingat tugas jatuh tempo hari ini, sekali per hari saat login pertama (tanpa cron).
        service('notifications')->dueTodayDigest($this->user);
    }

    private function recordFailure(string $username, string $ip, ?string $userAgent, ?User $user): void
    {
        $this->attempts->record($username, $ip, false, $userAgent);
        $this->audit->log('login_failed', 'auth', $user?->id, null, ['username' => $username], $user?->id);
    }
}
