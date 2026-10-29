<?php

/**
 * Helper format tampilan khas Indonesia: Rupiah, tanggal, nomor HP, NPWP.
 */

use CodeIgniter\I18n\Time;

if (! function_exists('rupiah')) {
    /**
     * Format angka menjadi Rupiah tanpa desimal, mis. `Rp 485.000.000`.
     */
    function rupiah(float|int|string|null $angka, bool $withSymbol = true): string
    {
        $angka     = round((float) ($angka ?? 0));
        $formatted = number_format(abs($angka), 0, ',', '.');

        return ($angka < 0 ? '-' : '') . ($withSymbol ? 'Rp ' : '') . $formatted;
    }
}

if (! function_exists('rupiah_ringkas')) {
    /**
     * Rupiah ringkas untuk kartu/grafik, mis. `7316000000` → `Rp 7,3 M`, `485000000` → `Rp 485 jt`.
     */
    function rupiah_ringkas(float|int|string|null $angka): string
    {
        $angka = (float) ($angka ?? 0);
        $abs   = abs($angka);
        $sign  = $angka < 0 ? '-' : '';

        return match (true) {
            $abs >= 1e12 => $sign . 'Rp ' . str_replace('.', ',', (string) round($abs / 1e12, 1)) . ' T',
            $abs >= 1e9  => $sign . 'Rp ' . str_replace('.', ',', (string) round($abs / 1e9, 1)) . ' M',
            $abs >= 1e6  => $sign . 'Rp ' . number_format($abs / 1e6, 0, ',', '.') . ' jt',
            default      => rupiah($angka),
        };
    }
}

if (! function_exists('to_time')) {
    /**
     * Ubah string/DateTime menjadi Time di zona waktu aplikasi; null bila kosong/tidak valid.
     */
    function to_time(DateTimeInterface|string|null $date): ?Time
    {
        if ($date === null || $date === '' || (is_string($date) && str_starts_with($date, '0000-00-00'))) {
            return null;
        }

        try {
            if ($date instanceof DateTimeInterface) {
                return Time::createFromInstance($date)->setTimezone(app_timezone());
            }

            return Time::parse($date, app_timezone());
        } catch (Throwable) {
            return null;
        }
    }
}

if (! function_exists('nama_bulan')) {
    /**
     * Nama bulan Indonesia (1–12), versi singkat atau panjang.
     */
    function nama_bulan(int $bulan, bool $short = true): string
    {
        $short3 = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        $long   = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        return ($short ? $short3 : $long)[$bulan - 1] ?? '';
    }
}

if (! function_exists('tanggal_id')) {
    /**
     * Format tanggal Indonesia, mis. `28 Okt 2026` atau `28 Okt 2026 14:05`.
     */
    function tanggal_id(DateTimeInterface|string|null $date, bool $withTime = false, string $empty = '-'): string
    {
        $time = to_time($date);

        if ($time === null) {
            return $empty;
        }

        $result = $time->getDay() . ' ' . nama_bulan($time->getMonth()) . ' ' . $time->getYear();

        return $withTime ? $result . ' ' . $time->format('H:i') : $result;
    }
}

if (! function_exists('waktu_relatif')) {
    /**
     * Waktu relatif dalam Bahasa Indonesia, mis. `5 menit lalu`, `kemarin`, `dalam 2 hari`.
     * Selisih lebih dari 30 hari ditampilkan sebagai tanggal biasa.
     */
    function waktu_relatif(DateTimeInterface|string|null $date, ?Time $now = null): string
    {
        $time = to_time($date);

        if ($time === null) {
            return '-';
        }

        $now ??= Time::now(app_timezone());
        $diff   = $now->getTimestamp() - $time->getTimestamp();
        $future = $diff < 0;
        $abs    = abs($diff);

        if ($abs < 60) {
            return $future ? 'sebentar lagi' : 'baru saja';
        }

        $dayDiff = (int) $now->setTime(0, 0)->difference($time->setTime(0, 0))->getDays();

        if ($abs < 3600) {
            $text = intdiv($abs, 60) . ' menit';
        } elseif ($abs < 86400 && $dayDiff === 0) {
            $text = intdiv($abs, 3600) . ' jam';
        } elseif ($dayDiff === -1) {
            return 'kemarin';
        } elseif ($dayDiff === 1) {
            return 'besok';
        } elseif (abs($dayDiff) <= 30) {
            $text = abs($dayDiff) . ' hari';
        } else {
            return tanggal_id($time);
        }

        return $future ? 'dalam ' . $text : $text . ' lalu';
    }
}

if (! function_exists('normalize_phone')) {
    /**
     * Normalisasi nomor HP ke format lokal angka saja, mis. `+62 812-3456` → `08123456`.
     */
    function normalize_phone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone);

        if ($digits === '' || $digits === null) {
            return null;
        }

        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '8')) {
            $digits = '0' . $digits;
        }

        return $digits;
    }
}

if (! function_exists('format_hp')) {
    /**
     * Tampilkan nomor HP berkelompok, mis. `081234567810` → `0812-3456-7810`.
     */
    function format_hp(?string $phone): string
    {
        $digits = normalize_phone($phone);

        if ($digits === null) {
            return '-';
        }

        // Telepon kantor: kode area 3 digit untuk kota besar (021, 031, ...), selainnya 4 digit.
        if (str_starts_with($digits, '0') && ! str_starts_with($digits, '08') && strlen($digits) >= 9) {
            $areaLength = in_array(substr($digits, 0, 3), ['021', '022', '024', '031', '061'], true) ? 3 : 4;

            return '(' . substr($digits, 0, $areaLength) . ') ' . substr($digits, $areaLength);
        }

        if (! str_starts_with($digits, '08') || strlen($digits) < 10) {
            return $digits;
        }

        return substr($digits, 0, 4) . '-' . substr($digits, 4, 4) . '-' . substr($digits, 8);
    }
}

if (! function_exists('wa_link')) {
    /**
     * Tautan "Chat WhatsApp" (`https://wa.me/628xx`) dari nomor lokal `08xx`.
     */
    function wa_link(?string $phone): ?string
    {
        $digits = normalize_phone($phone);

        if ($digits === null) {
            return null;
        }

        return 'https://wa.me/62' . ltrim($digits, '0');
    }
}

if (! function_exists('format_npwp')) {
    /**
     * Format NPWP 15 digit `01.234.567.8-901.234`; 16 digit (format NIK) ditampilkan apa adanya.
     */
    function format_npwp(?string $npwp): string
    {
        $digits = preg_replace('/\D+/', '', (string) $npwp);

        if ($digits === '' || $digits === null) {
            return '-';
        }

        if (strlen($digits) !== 15) {
            return $digits;
        }

        return sprintf(
            '%s.%s.%s.%s-%s.%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 1),
            substr($digits, 9, 3),
            substr($digits, 12, 3),
        );
    }
}

if (! function_exists('parse_rupiah')) {
    /**
     * Ubah input nominal format Indonesia ke angka desimal untuk database,
     * mis. `Rp 485.000.000` → `485000000`, `1.250.000,50` → `1250000.50`.
     * Input yang bukan angka dikembalikan apa adanya agar gagal di validasi.
     */
    function parse_rupiah(mixed $value): ?string
    {
        $text = trim(str_ireplace(['rp', ' '], '', (string) $value));

        if ($text === '') {
            return null;
        }

        if (preg_match('/^-?[0-9.,]+$/', $text) !== 1) {
            return $text;
        }

        if (str_contains($text, ',')) {
            return str_replace(['.', ','], ['', '.'], $text);
        }

        if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $text) === 1) {
            return str_replace('.', '', $text);
        }

        return $text;
    }
}

if (! function_exists('parse_datetime_input')) {
    /**
     * Ubah nilai input `datetime-local` (`2026-10-28T14:30`) ke format database `Y-m-d H:i:s`.
     * Nilai yang tidak dikenali dikembalikan apa adanya agar gagal di validasi.
     */
    function parse_datetime_input(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ](\d{2}:\d{2})(:\d{2})?$/', $value, $m) === 1) {
            return $m[1] . ' ' . $m[2] . ($m[3] ?? ':00');
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1) {
            return $value . ' 09:00:00';
        }

        return $value;
    }
}

if (! function_exists('persen')) {
    /**
     * Format persentase Indonesia, mis. `42.5` → `42,5%`; null → `—`.
     */
    function persen(float|int|null $value): string
    {
        return $value === null ? '—' : str_replace('.', ',', (string) round((float) $value, 1)) . '%';
    }
}

if (! function_exists('terbilang')) {
    /**
     * Angka dalam kata Bahasa Indonesia, mis. `1250000` → `satu juta dua ratus lima puluh ribu`.
     * Pecahan desimal diabaikan (dibulatkan ke rupiah terdekat).
     */
    function terbilang(float|int|string $angka): string
    {
        $angka = (int) round(abs((float) $angka));
        $words = ['', 'satu', 'dua', 'tiga', 'empat', 'lima', 'enam', 'tujuh', 'delapan', 'sembilan', 'sepuluh', 'sebelas'];

        $convert = static function (int $n) use (&$convert, $words): string {
            return match (true) {
                $n < 12             => $words[$n],
                $n < 20             => $convert($n - 10) . ' belas',
                $n < 100            => $convert(intdiv($n, 10)) . ' puluh ' . $convert($n % 10),
                $n < 200            => 'seratus ' . $convert($n - 100),
                $n < 1000           => $convert(intdiv($n, 100)) . ' ratus ' . $convert($n % 100),
                $n < 2000           => 'seribu ' . $convert($n - 1000),
                $n < 1000000        => $convert(intdiv($n, 1000)) . ' ribu ' . $convert($n % 1000),
                $n < 1000000000     => $convert(intdiv($n, 1000000)) . ' juta ' . $convert($n % 1000000),
                $n < 1000000000000  => $convert(intdiv($n, 1000000000)) . ' miliar ' . $convert($n % 1000000000),
                default             => $convert(intdiv($n, 1000000000000)) . ' triliun ' . $convert($n % 1000000000000),
            };
        };

        return $angka === 0 ? 'nol' : trim((string) preg_replace('/\s+/', ' ', $convert($angka)));
    }
}

if (! function_exists('angka')) {
    /**
     * Format bilangan bulat dengan pemisah ribuan titik, mis. `1.250`.
     */
    function angka(float|int|string|null $value): string
    {
        return number_format((float) ($value ?? 0), 0, ',', '.');
    }
}
