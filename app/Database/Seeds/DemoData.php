<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\I18n\Time;
use Faker\Factory;
use Faker\Generator;

/**
 * Data & utilitas bersama untuk seeder data demo (konteks distributor solusi IT di Indonesia).
 */
final class DemoData
{
    public const SEED = 2026;

    /**
     * Kota → [provinsi, kode area telepon].
     */
    public const CITIES = [
        'Jakarta'    => ['DKI Jakarta', '021'],
        'Surabaya'   => ['Jawa Timur', '031'],
        'Bandung'    => ['Jawa Barat', '022'],
        'Semarang'   => ['Jawa Tengah', '024'],
        'Medan'      => ['Sumatera Utara', '061'],
        'Makassar'   => ['Sulawesi Selatan', '0411'],
        'Denpasar'   => ['Bali', '0361'],
        'Balikpapan' => ['Kalimantan Timur', '0542'],
    ];

    public const SALES = ['andi.saputra', 'siti.rahma', 'budi.santoso', 'putri.ayu', 'fajar.nugroho', 'maya.kusuma'];

    public const JOB_TITLES = [
        'Direktur Utama', 'Direktur Operasional', 'General Manager', 'Kepala IT', 'IT Manager', 'Supervisor IT',
        'Purchasing Manager', 'Staf Pengadaan', 'Manajer Keuangan', 'Finance & Accounting Manager',
        'Manajer Operasional', 'Kepala Gudang', 'HRD Manager', 'Owner',
    ];

    private const MOBILE_PREFIXES = ['811', '812', '813', '821', '822', '852', '853', '814', '815', '816', '855', '856', '857', '858', '817', '818', '819', '859', '877', '878', '881', '882', '895', '896', '897', '898'];

    /**
     * @var array<string, true>
     */
    private static array $usedPhones = [];

    public static function faker(int $offset = 0): Generator
    {
        $faker = Factory::create('id_ID');
        $faker->seed(self::SEED + $offset);

        return $faker;
    }

    /**
     * Nomor HP fiktif dengan pola valid `08xx`, unik dalam satu proses seeding.
     */
    public static function mobile(Generator $faker): string
    {
        do {
            $phone = '0' . $faker->randomElement(self::MOBILE_PREFIXES) . $faker->numerify(str_repeat('#', $faker->numberBetween(7, 8)));
        } while (isset(self::$usedPhones[$phone]));

        self::$usedPhones[$phone] = true;

        return $phone;
    }

    public static function reservePhone(string $phone): void
    {
        self::$usedPhones[$phone] = true;
    }

    /**
     * Huruf kecil ASCII untuk email, mis. "Dwi Ayu" → "dwi.ayu".
     */
    public static function slug(string $text, string $separator = '.'): string
    {
        $text = strtolower(trim((string) preg_replace('/[^A-Za-z ]+/', '', $text)));

        return (string) preg_replace('/\s+/', $separator, $text);
    }

    /**
     * Waktu acak antara $daysAgoMax dan $daysAgoMin hari yang lalu, jam kerja.
     */
    public static function pastDate(Generator $faker, int $daysAgoMax, int $daysAgoMin = 0): Time
    {
        $date = Time::today()
            ->subDays($faker->numberBetween($daysAgoMin, $daysAgoMax))
            ->setHour($faker->numberBetween(8, 17))
            ->setMinute($faker->numberBetween(0, 59));

        // Jangan sampai jatuh di jam yang belum lewat hari ini.
        return $date->isAfter(Time::now()) ? Time::now()->subMinutes($faker->numberBetween(5, 90)) : $date;
    }

    /**
     * Peta username → id.
     *
     * @return array<string, int>
     */
    public static function userIds(BaseConnection $db): array
    {
        $rows = $db->table('users')->select('id, username')->get()->getResultArray();

        return array_map('intval', array_column($rows, 'id', 'username'));
    }

    /**
     * Peta nama → id untuk tabel master.
     *
     * @return array<string, int>
     */
    public static function masterIds(BaseConnection $db, string $table): array
    {
        $rows = $db->table($table)->select('id, name')->get()->getResultArray();

        return array_map('intval', array_column($rows, 'id', 'name'));
    }
}
