<?php

namespace App\Database\Seeds;

use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * 60 perusahaan fiktif di 8 kota, termasuk record showcase "PT Sinar Logistik Nusantara".
 * Dilewati bila tabel perusahaan sudah berisi data.
 */
class CompanySeeder extends Seeder
{
    public const TOTAL    = 60;
    public const SHOWCASE = 'PT Sinar Logistik Nusantara';

    /**
     * Industri → kata khas nama perusahaan.
     */
    private const INDUSTRY_WORDS = [
        'Manufaktur' => ['Industri', 'Plastindo', 'Metalindo', 'Tekstil', 'Manufaktur'],
        'Retail'     => ['Retailindo', 'Niaga', 'Swalayan', 'Mart'],
        'Logistik'   => ['Logistik', 'Ekspedisi', 'Cargo', 'Transportasi'],
        'Kesehatan'  => ['Medika', 'Farma', 'Husada', 'Sehat'],
        'Pendidikan' => ['Edukasi', 'Cendekia', 'Pendidikan', 'Ilmu'],
        'F&B'        => ['Pangan', 'Boga', 'Kuliner', 'Rasa'],
        'Konstruksi' => ['Konstruksi', 'Bangun', 'Beton', 'Karya'],
        'Perbankan'  => ['Artha', 'Dana', 'Finansial', 'Kredit'],
    ];

    private const WORDS = [
        'Sinar', 'Cahaya', 'Berkah', 'Maju', 'Mitra', 'Sumber', 'Prima', 'Bintang', 'Abadi', 'Jaya', 'Sentosa',
        'Mandiri', 'Nusantara', 'Sejahtera', 'Makmur', 'Gemilang', 'Harapan', 'Surya', 'Mega', 'Lestari', 'Utama', 'Agung',
    ];

    private const STREETS = [
        'Jl. Ahmad Yani', 'Jl. Jenderal Sudirman', 'Jl. Gatot Subroto', 'Jl. Diponegoro', 'Jl. Gajah Mada', 'Jl. Pemuda',
        'Jl. Pahlawan', 'Jl. Merdeka', 'Jl. Imam Bonjol', 'Jl. Hayam Wuruk', 'Jl. Veteran', 'Jl. Basuki Rahmat',
        'Jl. Raya Industri', 'Jl. Kawasan Pergudangan',
    ];

    public function run(): void
    {
        if ($this->db->table('companies')->countAllResults() > 0) {
            if (! $this->silent) {
                CLI::write('CompanySeeder: data perusahaan sudah ada, dilewati.', 'yellow');
            }

            return;
        }

        $faker      = DemoData::faker(10);
        $users      = DemoData::userIds($this->db);
        $industries = DemoData::masterIds($this->db, 'industries');
        $cities     = array_keys(DemoData::CITIES);
        $names      = [self::SHOWCASE => true];
        $rows       = [];

        $rows[] = $this->row(
            self::SHOWCASE,
            $industries['Logistik'],
            'Surabaya',
            $users['andi.saputra'],
            250,
            'https://www.sinarlogistik.co.id',
            '0313541288',
            'Jl. Kawasan Pergudangan Margomulyo Blok C-12',
            '017452368615000',
            Time::today()->subDays(150)->setHour(9),
        );

        for ($i = 1; $i < self::TOTAL; $i++) {
            $industry = array_keys(self::INDUSTRY_WORDS)[$i % count(self::INDUSTRY_WORDS)];
            $city     = $cities[$i % count($cities)];

            do {
                $first  = $faker->randomElement(self::WORDS);
                $last   = $faker->randomElement(array_values(array_diff(self::WORDS, [$first])));
                $word   = $faker->randomElement(self::INDUSTRY_WORDS[$industry]);
                $prefix = match ($industry) {
                    'Perbankan'  => 'PT BPR',
                    'Pendidikan' => $faker->boolean(50) ? 'Yayasan' : 'PT',
                    default      => $faker->boolean(75) ? 'PT' : 'CV',
                };
                $name = "{$prefix} {$first} {$word} {$last}";
            } while (isset($names[$name]));

            $names[$name] = true;
            $domain       = DemoData::slug($first . $word, '') . '.co.id';

            $rows[] = $this->row(
                $name,
                $industries[$industry],
                $city,
                $users[DemoData::SALES[$i % count(DemoData::SALES)]],
                $faker->randomElement([15, 25, 40, 60, 80, 120, 150, 250, 400, 750, 1200]),
                $faker->boolean(80) ? 'https://www.' . $domain : null,
                DemoData::CITIES[$city][1] . $faker->numerify(strlen(DemoData::CITIES[$city][1]) === 3 ? '#######' : '######'),
                $faker->randomElement(self::STREETS) . ' No. ' . $faker->numberBetween(1, 250),
                $faker->boolean(70) ? $faker->numerify('0#.###.###.#-###.000') : null,
                DemoData::pastDate($faker, 172, 14),
            );
        }

        $this->db->table('companies')->insertBatch($rows);
    }

    /**
     * @return array<string, mixed>
     */
    private function row(
        string $name,
        int $industryId,
        string $city,
        int $ownerId,
        int $employees,
        ?string $website,
        string $phone,
        string $address,
        ?string $npwp,
        Time $createdAt,
    ): array {
        return [
            'name'           => $name,
            'industry_id'    => $industryId,
            'npwp'           => $npwp === null ? null : preg_replace('/\D+/', '', $npwp),
            'phone'          => $phone,
            'website'        => $website,
            'address'        => $address,
            'city'           => $city,
            'province'       => DemoData::CITIES[$city][0],
            'employee_count' => $employees,
            'owner_id'       => $ownerId,
            'created_at'     => $createdAt->toDateTimeString(),
            'updated_at'     => $createdAt->toDateTimeString(),
        ];
    }
}
