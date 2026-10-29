# Sistem CRM

CRM web internal untuk tim sales B2B skala UKM: **Lead → Perusahaan & Kontak → Deal (pipeline) → Aktivitas/Tugas → Penawaran**, dengan dashboard dan laporan untuk manajer.

Dibangun dengan CodeIgniter 4.7, MySQL, Tailwind CSS (Play CDN) dan Alpine.js — **tanpa Node.js dan tanpa langkah build front-end**. Bisa dijalankan di shared hosting maupun VPS standar.

Dokumen: [PRD](docs/PRD.md) · [Rencana kerja](docs/TASKS.md) · [Panduan deploy](docs/DEPLOY.md) · [Skenario UAT](docs/UAT.md) · [Audit keamanan](docs/AUDIT-KEAMANAN.md)

## Fitur

| Modul | Isi |
| --- | --- |
| Login & profil | Login username + password, kunci 15 menit setelah 5 kali gagal, wajib ganti password untuk akun baru/di-reset |
| Lead | Pencarian & filter, cek duplikat no. HP/email, konversi ke perusahaan + kontak + deal dalam satu langkah, impor & ekspor CSV |
| Perusahaan & kontak | Detail dengan tab kontak/deal/aktivitas/catatan, tombol Chat WhatsApp & Telepon |
| Deal & pipeline | Tampilan tabel dan papan Kanban (geser antar tahap), riwayat tahap, alasan kalah |
| Aktivitas & tugas | Catat telepon/meeting/WhatsApp/kunjungan dari halaman mana pun, tugas harian (hari ini, terlambat, minggu ini), notifikasi lonceng |
| Produk & penawaran | Katalog produk, penawaran bernomor `QUO/YYYY/MM/NNNN`, PPN dari pengaturan, halaman cetak/simpan PDF |
| Dashboard & laporan | KPI, grafik pipeline & tren Won, laporan kinerja sales, win/loss, sumber lead, forecast — ekspor CSV |
| Administrasi | Pengguna & reset password, alih owner massal, pengaturan & logo, master data, log audit |

Akses data per peran: **sales** hanya miliknya, **manager** timnya, **admin** semua data — dibatasi di server, bukan hanya di tampilan.

## Kebutuhan server

- PHP **8.2+** dengan ekstensi `intl`, `mbstring`, `mysqlnd`, `json`, `curl`, `fileinfo`
- MySQL **8.0** / MariaDB **10.6+** (InnoDB, `utf8mb4_unicode_ci`)
- Composer 2
- Browser pengguna perlu akses internet ke `cdn.tailwindcss.com` dan `cdn.jsdelivr.net`

## Instalasi lokal (± 10 menit)

```bash
git clone https://github.com/fikrizumardev/crm-app.git
cd crm-app
composer install
cp .env.example .env          # sesuaikan kredensial database
```

Buat database kosong (contoh MySQL):

```sql
CREATE DATABASE crm_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Lalu migrasi, isi data contoh, dan jalankan:

```bash
php spark migrate
php spark db:seed DatabaseSeeder
php spark serve
```

Buka <http://localhost:8080> dan masuk dengan salah satu akun demo di bawah. Proses migrasi + seeder dari database kosong selesai dalam hitungan detik.

## Akun demo

Dibuat oleh `DatabaseSeeder` (khusus demo/development).

| Username | Nama | Peran | Password |
| --- | --- | --- | --- |
| `admin` | Rizky Pratama | admin | `Admin#2026` |
| `dewi.lestari` | Dewi Lestari | manager | `Manager#2026` |
| `hendra.wijaya` | Hendra Wijaya | manager | `Manager#2026` |
| `andi.saputra` | Andi Saputra | sales (tim Dewi) | `Sales#2026` |
| `siti.rahma` | Siti Rahmawati | sales (tim Dewi) | `Sales#2026` |
| `budi.santoso` | Budi Santoso | sales (tim Dewi) | `Sales#2026` |
| `putri.ayu` | Putri Ayu Ningsih | sales (tim Hendra) | `Sales#2026` |
| `fajar.nugroho` | Fajar Nugroho | sales (tim Hendra) | `Sales#2026` |
| `maya.kusuma` | Maya Kusumawati | sales (tim Hendra) | `Sales#2026` |

Data contoh: 25 produk, 60 perusahaan, ±160 kontak, 120 lead, 80 deal, ±430 aktivitas, ±150 tugas, 30 penawaran — konteks distributor solusi IT di Indonesia. Tanggal dihitung relatif dari hari seeder dijalankan.

## Perintah penting

| Perintah | Fungsi |
| --- | --- |
| `php spark migrate` | Membuat/memperbarui struktur database |
| `php spark db:seed DatabaseSeeder` | Data demo lengkap + 9 akun demo |
| `php spark db:seed ProductionSeeder` | **Produksi**: pengaturan, master data, dan 1 akun `admin` dengan password acak yang dicetak di terminal |
| `php spark db:seed PerformanceSeeder` | Tambah 10.000 lead + 10.000 deal untuk uji performa (jangan di produksi) |
| `php spark migrate:refresh -f && php spark db:seed DatabaseSeeder` | Reset database demo ke kondisi awal |
| `vendor/bin/phpunit --no-coverage` | Menjalankan seluruh test |

## Menjalankan test

Test memakai database MySQL terpisah `crm_app_test` (lihat `Config\Database::$tests` atau `database.tests.*` di `.env`):

```sql
CREATE DATABASE crm_app_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
vendor/bin/phpunit --no-coverage
```

## Deploy ke produksi

Ringkasnya: arahkan **document root ke folder `public/`**, set `CI_ENVIRONMENT = production`, jalankan `composer install --no-dev --optimize-autoloader`, `php spark migrate`, lalu `php spark db:seed ProductionSeeder`. Langkah lengkap (shared hosting & VPS, HTTPS, izin folder, checklist go-live) ada di **[docs/DEPLOY.md](docs/DEPLOY.md)**.

## Struktur singkat

```
app/
  Config/        Routes, Filters, Auth, Menu, ContentSecurityPolicy, Indonesia (provinsi)
  Controllers/   modul aplikasi + Admin/ (pengguna, pengaturan, master data, log audit)
  Filters/       AuthFilter, RoleFilter, ForcePasswordChangeFilter
  Models/        OwnedModel (pembatasan data per owner) + model per tabel
  Services/      logika bisnis: konversi lead, pipeline deal, penawaran, laporan, audit, notifikasi
  Database/      Migrations/ dan Seeds/
  Views/         layouts/, components/ (komponen UI bersama), halaman per modul
tests/           feature & unit test (PHPUnit)
docs/            PRD, rencana kerja, deploy, UAT, audit keamanan
```

## Pemecahan masalah

- **"Session: Configured save path … is not writable"** — folder `writable/` harus bisa ditulis PHP (`chmod -R 775 writable` di Linux). Di Windows, hapus atribut read-only: `attrib -R writable\* /S /D`.
- **Tampilan tanpa gaya (polos)** — browser tidak bisa menjangkau `cdn.tailwindcss.com`; periksa koneksi internet/proxy kantor.
- **"The action you requested is not allowed"** — token keamanan form kedaluwarsa (mis. form dibuka di dua tab). Muat ulang halaman lalu kirim lagi.
