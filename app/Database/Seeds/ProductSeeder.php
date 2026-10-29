<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use CodeIgniter\I18n\Time;

/**
 * 25 produk/layanan distributor solusi IT (Rp 1,5 jt – Rp 250 jt). Idempoten berdasarkan SKU.
 */
class ProductSeeder extends Seeder
{
    /**
     * @var list<array{0: string, 1: string, 2: string, 3: int}> [sku, nama, satuan, harga]
     */
    public const PRODUCTS = [
        // Lisensi software
        ['LIC-ERP-USR', 'Lisensi ERP Nusantara (per user)', 'user', 4500000],
        ['LIC-ERP-CORE', 'Lisensi ERP Nusantara Core Server', 'paket', 85000000],
        ['LIC-WMS-SITE', 'Lisensi WMS Gudang (per site)', 'site', 65000000],
        ['LIC-POS-OUT', 'Lisensi POS Retail (per outlet)', 'outlet', 3500000],
        ['LIC-HRIS-50', 'Lisensi HRIS hingga 50 karyawan', 'paket', 18000000],
        ['LIC-CRM-USR', 'Lisensi CRM Sales (per user/tahun)', 'user', 2400000],
        ['LIC-BI-DASH', 'Lisensi Dashboard Business Intelligence', 'paket', 35000000],
        ['LIC-EDU-SIS', 'Lisensi Sistem Informasi Sekolah', 'paket', 24000000],
        ['LIC-HIS-CLN', 'Lisensi SIMRS Klinik & Rumah Sakit', 'paket', 120000000],
        // Hardware
        ['HW-SRV-2U', 'Server Rack 2U Xeon 32GB RAM', 'unit', 98000000],
        ['HW-NAS-4B', 'NAS Storage 4-Bay 16TB', 'unit', 28500000],
        ['HW-FW-UTM', 'Firewall UTM Small Business', 'unit', 22000000],
        ['HW-SW-24P', 'Switch Managed 24 Port PoE', 'unit', 9750000],
        ['HW-AP-WIFI6', 'Access Point WiFi 6', 'unit', 3250000],
        ['HW-POS-TERM', 'Terminal POS All-in-One', 'unit', 11500000],
        ['HW-BCS-2D', 'Barcode Scanner 2D Wireless', 'unit', 1850000],
        ['HW-PRN-THR', 'Printer Thermal Label', 'unit', 4200000],
        // Jasa implementasi
        ['SVC-IMPL-ERP', 'Jasa Implementasi ERP (paket 3 bulan)', 'paket', 250000000],
        ['SVC-IMPL-WMS', 'Jasa Implementasi WMS', 'paket', 145000000],
        ['SVC-TRN-DAY', 'Pelatihan Pengguna (per hari)', 'hari', 7500000],
        ['SVC-MIG-DATA', 'Jasa Migrasi Data', 'paket', 25000000],
        ['SVC-CUST-MD', 'Kustomisasi Modul (per man-day)', 'man-day', 2750000],
        // Maintenance tahunan
        ['MNT-ERP-YR', 'Maintenance & Support ERP Tahunan', 'tahun', 48000000],
        ['MNT-HW-YR', 'Kontrak Pemeliharaan Hardware Tahunan', 'tahun', 18500000],
        ['MNT-REMOTE', 'Remote Support (per tiket)', 'tiket', 1500000],
    ];

    public function run(): void
    {
        $now   = Time::now()->toDateTimeString();
        $table = $this->db->table('products');

        foreach (self::PRODUCTS as [$sku, $name, $unit, $price]) {
            if ($table->where('sku', $sku)->countAllResults() > 0) {
                continue;
            }

            $table->insert([
                'sku'        => $sku,
                'name'       => $name,
                'unit'       => $unit,
                'price'      => $price,
                'is_active'  => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
