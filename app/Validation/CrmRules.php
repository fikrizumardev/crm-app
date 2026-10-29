<?php

namespace App\Validation;

use Config\Auth;

/**
 * Aturan validasi khusus aplikasi CRM.
 */
class CrmRules
{
    /**
     * Tolak username yang dicadangkan (admin, root, system, api).
     */
    public function username_not_reserved(?string $value): bool
    {
        return ! in_array(strtolower(trim((string) $value)), config(Auth::class)->reservedUsernames, true);
    }

    /**
     * Password minimal N karakter dan mengandung huruf serta angka.
     */
    public function strong_password(?string $value): bool
    {
        $value = (string) $value;

        return mb_strlen($value) >= config(Auth::class)->passwordMinLength
            && preg_match('/\pL/u', $value) === 1
            && preg_match('/\d/', $value) === 1;
    }

    /**
     * Nomor telepon/HP Indonesia: 8–15 digit setelah tanda baca dibuang.
     */
    public function valid_phone(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }

        if (preg_match('/^[0-9+\-\s().]+$/', $value) !== 1) {
            return false;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return strlen($digits) >= 8 && strlen($digits) <= 15;
    }

    /**
     * NPWP 15 digit (format lama) atau 16 digit (format NIK); tanda baca diabaikan.
     */
    public function valid_npwp(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return true;
        }

        if (preg_match('/^[0-9.\-\s]+$/', $value) !== 1) {
            return false;
        }

        $length = strlen(preg_replace('/\D+/', '', $value));

        return $length === 15 || $length === 16;
    }
}
