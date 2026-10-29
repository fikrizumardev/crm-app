# Panduan Deploy Produksi

Panduan memasang Sistem CRM di server produksi (T5.13) dan checklist go-live v1.0 (T5.14).

## 1. Persiapan server

- PHP 8.2+ dengan ekstensi `intl`, `mbstring`, `mysqlnd`, `json`, `curl`, `fileinfo`
- MySQL 8.0 / MariaDB 10.6+
- Composer 2 (di server, atau jalankan `composer install` di lokal lalu unggah folder `vendor/`)
- Sertifikat HTTPS aktif untuk domain (mis. Let's Encrypt / AutoSSL cPanel)

Buat database dan user khusus aplikasi:

```sql
CREATE DATABASE crm_app CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm_app'@'localhost' IDENTIFIED BY 'ganti-dengan-password-kuat';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON crm_app.* TO 'crm_app'@'localhost';
```

## 2. Unggah kode

```bash
git clone https://github.com/fikrizumardev/crm-app.git
cd crm-app
composer install --no-dev --optimize-autoloader
```

`fakerphp/faker` ada di `require` (bukan `require-dev`) sehingga tetap terpasang; Faker hanya dipakai seeder, tidak pernah saat melayani request.

## 3. Document root ke `public/`

Hanya folder `public/` yang boleh bisa diakses dari web.

- **VPS (Nginx/Apache):** set `root /path/crm-app/public;` (Nginx) atau `DocumentRoot /path/crm-app/public` (Apache).
- **Shared hosting (cPanel):** letakkan proyek di luar `public_html` (mis. `/home/user/crm-app`), lalu di *Domains → Document Root* arahkan domain/subdomain ke `/home/user/crm-app/public`. Bila document root tidak bisa diubah, salin isi `public/` ke `public_html/` dan ubah path di `public_html/index.php` (`require FCPATH . '../app/Config/Paths.php'`) agar menunjuk ke folder proyek.

File `public/.htaccess` sudah berisi aturan rewrite untuk Apache.

## 4. Konfigurasi `.env`

```bash
cp .env.example .env
```

Isi minimal untuk produksi:

```ini
CI_ENVIRONMENT = production
app.baseURL = 'https://crm.namaperusahaan.co.id/'
app.forceGlobalSecureRequests = true
cookie.secure = true

database.default.hostname = localhost
database.default.database = crm_app
database.default.username = crm_app
database.default.password = 'ganti-dengan-password-kuat'
database.default.DBDriver = MySQLi
```

Dengan `production`: Debug Toolbar & pesan error detail mati, Content Security Policy ditegakkan penuh, dan token keamanan yang kedaluwarsa dialihkan kembali ke form (bukan halaman error).

## 5. Izin folder

```bash
chmod -R 775 writable public/uploads
chown -R www-data:www-data writable public/uploads   # sesuaikan user web server
```

`writable/` menyimpan sesi, cache dashboard, dan log. `public/uploads/` menyimpan logo perusahaan; file `.htaccess` di dalamnya menolak eksekusi skrip.

## 6. Database

```bash
php spark migrate
php spark db:seed ProductionSeeder
```

`ProductionSeeder` mengisi pengaturan, master data (sumber lead, industri, tahap pipeline, alasan kalah), dan **satu akun `admin` dengan password acak** yang dicetak di terminal. Catat password tersebut — hanya ditampilkan sekali dan wajib diganti saat login pertama. **Jangan** menjalankan `DatabaseSeeder` di produksi (berisi data & akun demo).

## 7. Opsional: optimasi

```bash
php spark optimize      # cache konfigurasi & lokasi file
```

Jalankan ulang setelah setiap update kode.

## 8. Update versi

```bash
git pull
composer install --no-dev --optimize-autoloader
php spark migrate
php spark cache:clear
```

Selalu backup database sebelum `migrate` (mis. `mysqldump crm_app > backup-$(date +%F).sql`).

## 9. Backup rutin

- Database: `mysqldump` harian (cron) dan simpan minimal 30 hari.
- File: `public/uploads/` dan `.env`.
- Log audit di tabel `audit_logs` dipertahankan minimal 1 tahun (PRD §9).

---

## Checklist go-live v1.0

**Server (T5.13)**

- [ ] HTTPS aktif dan `http://` dialihkan ke `https://`
- [ ] Document root mengarah ke `public/`; `https://domain/.env` dan `https://domain/app/` tidak bisa dibuka
- [ ] `.env` berisi `CI_ENVIRONMENT = production`, `cookie.secure = true`, `app.forceGlobalSecureRequests = true`
- [ ] `writable/` dan `public/uploads/` bisa ditulis web server
- [ ] `php spark migrate` dan `php spark db:seed ProductionSeeder` berhasil, password admin tercatat
- [ ] Backup database terjadwal

**Aplikasi (T5.14)**

- [ ] Admin login, mengganti password awal
- [ ] Admin mengisi *Pengaturan* (nama, alamat, telepon, logo, PPN)
- [ ] Admin meninjau *Master Data* (tahap pipeline, sumber lead, industri, alasan kalah)
- [ ] Admin membuat akun manager & sales (password awal dibagikan langsung ke masing-masing)
- [ ] Smoke test: login sebagai sales → buat lead → konversi → pindah deal di Kanban → buat & cetak penawaran
- [ ] Minimal 2 akun admin aktif (cadangan bila satu admin berhalangan)
