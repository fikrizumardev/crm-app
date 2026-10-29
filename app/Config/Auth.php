<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Aturan autentikasi username + password (PRD §5).
 */
class Auth extends BaseConfig
{
    /**
     * Jumlah percobaan gagal per username + IP sebelum dikunci.
     */
    public int $maxAttempts = 5;

    /**
     * Rentang waktu (detik) penghitungan percobaan gagal.
     */
    public int $attemptWindow = 900;

    /**
     * Lama penguncian (detik) setelah batas percobaan terlampaui.
     */
    public int $lockoutTime = 900;

    /**
     * Username yang tidak boleh dipakai akun baru (kecuali akun awal dari seeder).
     *
     * @var list<string>
     */
    public array $reservedUsernames = ['admin', 'root', 'system', 'api'];

    /**
     * Panjang minimal password.
     */
    public int $passwordMinLength = 8;

    /**
     * Rute yang tetap boleh dibuka saat user wajib mengganti password.
     *
     * @var list<string>
     */
    public array $passwordChangeAllowedPaths = ['profile/password', 'logout'];
}
