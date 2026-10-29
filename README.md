# Sistem CRM

CRM web internal untuk tim sales B2B: Lead → Perusahaan & Kontak → Deal → Aktivitas/Tugas → Penawaran.
Dibangun dengan CodeIgniter 4.7, MySQL, Tailwind CSS (CDN) dan Alpine.js — tanpa langkah build front-end.

Dokumen produk: [docs/PRD.md](docs/PRD.md) · Rencana kerja: [docs/TASKS.md](docs/TASKS.md)

## Fitur

- Login dengan username, pembatasan percobaan gagal, wajib ganti password untuk akun baru/di-reset
- Lead (impor/ekspor CSV, cek duplikat, konversi ke perusahaan + kontak + deal), perusahaan, kontak
- Deal dengan papan Kanban, riwayat tahap, alasan kalah
- Aktivitas & tugas di setiap record, notifikasi lonceng
- Dashboard KPI + grafik, laporan kinerja sales, win/loss, sumber lead, forecast (ekspor CSV)
- Administrasi: pengguna, alih owner massal, pengaturan & logo, master data, log audit
- Akses data per peran: sales → miliknya, manager → timnya, admin → semua

## Kebutuhan

- PHP ≥ 8.2 dengan ekstensi `intl`, `mbstring`, `mysqlnd`, `json`
- Composer 2
- MySQL 8.0 / MariaDB 10.6+

## Instalasi cepat

```bash
composer install
cp env .env            # lalu sesuaikan nilai di bawah
```

Isi minimal `.env`:

```ini
CI_ENVIRONMENT = development
app.baseURL = 'http://localhost:8080/'

database.default.hostname = 127.0.0.1
database.default.database = crm_app
database.default.username = root
database.default.password =
database.default.DBDriver = MySQLi
```

Buat database `crm_app` (collation `utf8mb4_unicode_ci`), lalu:

```bash
php spark migrate
php spark db:seed DatabaseSeeder
php spark serve
```

Buka <http://localhost:8080> dan masuk dengan salah satu akun demo.

## Akun demo

| Username | Peran | Password |
| --- | --- | --- |
| `admin` | admin | `Admin#2026` |
| `dewi.lestari`, `hendra.wijaya` | manager | `Manager#2026` |
| `andi.saputra`, `siti.rahma`, `budi.santoso` (tim Dewi) | sales | `Sales#2026` |
| `putri.ayu`, `fajar.nugroho`, `maya.kusuma` (tim Hendra) | sales | `Sales#2026` |

## Menjalankan test

Test memakai database MySQL terpisah `crm_app_test` (lihat `Config\Database::$tests` atau `database.tests.*` di `.env`).

```bash
vendor/bin/phpunit --no-coverage
```
