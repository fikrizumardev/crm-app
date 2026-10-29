# PRD — Sistem CRM CodeIgniter 4

| | |
| --- | --- |
| Versi dokumen | 1.0 |
| Tanggal | 28 Oktober 2026 |
| Penyusun | Fikri |
| Status | Draft |

---

## 1. Ringkasan produk

Kita membangun CRM web internal untuk tim sales B2B skala UKM (5–50 pengguna) di atas CodeIgniter 4 versi terbaru, dengan UI Tailwind via CDN tanpa proses build, login menggunakan username, dan seeder berisi data contoh realistis konteks Indonesia.

Masalah yang diselesaikan: data pelanggan, prospek, dan riwayat follow-up masih tersebar di Excel, WhatsApp, dan catatan pribadi sales. Akibatnya pipeline tidak terlihat, follow-up terlewat, dan manajer tidak punya angka forecast yang bisa dipercaya.

Solusi: satu aplikasi terpusat untuk mengelola **Lead → Kontak & Perusahaan → Deal (pipeline) → Aktivitas/Tugas → Penawaran**, dengan dashboard ringkas untuk manajer. Aplikasi berjalan di shared hosting atau VPS standar (PHP + MySQL) tanpa Node.js.

---

## 2. Tujuan, non-tujuan, dan metrik

### Tujuan (v1.0)

- Semua lead, kontak, perusahaan, dan deal tersimpan di satu tempat dengan pemilik (owner) yang jelas.
- Pipeline deal terlihat per tahap dengan nilai Rupiah dan probabilitas, termasuk forecast bulanan.
- Setiap sales punya daftar tugas/follow-up harian dan pengingat jatuh tempo.
- Manajer bisa melihat performa tim dari dashboard tanpa membuka Excel.
- Aplikasi bisa di-deploy hanya dengan `composer install`, migrasi, dan seeder.

### Non-tujuan (di luar v1.0)

- Integrasi email inbox (IMAP/SMTP sync), WhatsApp API, atau telepon VoIP.
- Marketing automation, kampanye email massal, dan landing page builder.
- Aplikasi mobile native; v1.0 cukup responsif di browser mobile.
- Multi-tenant (banyak perusahaan dalam satu instalasi).
- Login dengan email, SSO, atau OAuth.

### Metrik keberhasilan (3 bulan setelah go-live)

| Metrik | Target |
| --- | --- |
| Pengguna aktif mingguan / total akun sales | ≥ 90% |
| Deal aktif yang punya aktivitas ≤ 14 hari terakhir | ≥ 80% |
| Tugas yang selesai sebelum jatuh tempo | ≥ 70% |
| Waktu muat halaman daftar (1.000 baris, paginasi 25) | < 1 detik |
| Waktu setup dari nol sampai login dengan data contoh | < 10 menit |

---

## 3. Persona dan peran akses

Ada tiga peran tetap (role-based, bukan permission granular) agar v1.0 sederhana: **admin**, **manager**, dan **sales**.

| Peran | Persona | Kebutuhan utama | Cakupan data |
| --- | --- | --- | --- |
| admin | Staf IT / owner bisnis | Kelola user, peran, master data (tahap pipeline, sumber lead, industri), pengaturan aplikasi | Semua data |
| manager | Sales manager / supervisor | Lihat pipeline tim, forecast, reassign lead/deal, laporan | Semua data tim yang dipimpinnya |
| sales | Account executive / sales lapangan | Input lead, follow-up, update deal, catat aktivitas, buat penawaran | Hanya data miliknya (owner) |

### Matriks hak akses

| Aksi | admin | manager | sales |
| --- | --- | --- | --- |
| Kelola user & reset password | Ya | Tidak | Tidak |
| Kelola master data | Ya | Tidak | Tidak |
| Lihat semua lead/deal | Ya | Tim sendiri | Milik sendiri |
| Reassign owner | Ya | Tim sendiri | Tidak |
| Hapus data (soft delete) | Ya | Ya | Milik sendiri, status belum Won |
| Ekspor CSV | Ya | Ya | Tidak |
| Lihat log audit | Ya | Tidak | Tidak |

---

## 4. Kebutuhan fungsional per modul

v1.0 terdiri dari 9 modul; prioritas P0 wajib untuk rilis, P1 boleh menyusul di rilis minor.

| Kode | Modul | Prioritas |
| --- | --- | --- |
| M1 | Autentikasi & profil | P0 |
| M2 | Dashboard | P0 |
| M3 | Lead | P0 |
| M4 | Perusahaan (Account) | P0 |
| M5 | Kontak | P0 |
| M6 | Deal & Pipeline (Kanban) | P0 |
| M7 | Aktivitas & Tugas | P0 |
| M8 | Produk & Penawaran (Quotation) | P1 |
| M9 | Laporan, Pengaturan, Manajemen User, Log Audit | P0 (user) / P1 (laporan) |

M1 dijelaskan di [bagian 5](#5-autentikasi-dan-otorisasi).

### M2 — Dashboard

- Kartu KPI: jumlah lead baru bulan ini, deal terbuka, total nilai pipeline (Rp), nilai Won bulan ini, win rate.
- Grafik pipeline per tahap dan tren Won 6 bulan terakhir (Chart.js via CDN).
- Widget "Tugas saya hari ini" dan "Tugas terlambat".
- Sales melihat angka miliknya; manager/admin bisa filter per sales.

### M3 — Lead

- CRUD lead: nama, perusahaan, jabatan, no. HP, email (opsional), sumber, status, owner, catatan.
- Status: Baru → Dihubungi → Kualifikasi → Tidak Qualified / Dikonversi.
- **Konversi lead** dalam satu aksi: membuat Perusahaan + Kontak + (opsional) Deal, lalu lead ditandai Dikonversi dan tertaut ke record baru.
- Pencarian, filter (status, sumber, owner, rentang tanggal), sort, paginasi 25 baris.
- Impor CSV dengan template dan validasi per baris (P1); ekspor CSV.
- Cegah duplikat: peringatan bila no. HP atau email sudah ada.

### M4 — Perusahaan

- CRUD: nama, industri, NPWP (opsional), telepon, website, alamat, kota, provinsi, jumlah karyawan, owner.
- Halaman detail berisi tab: Kontak, Deal, Aktivitas, Catatan.

### M5 — Kontak

- CRUD: nama, jabatan, perusahaan, no. HP, WhatsApp, email, tanggal lahir (opsional), owner.
- Tombol cepat "Chat WhatsApp" (link `wa.me`) dan "Telepon" (`tel:`) — tanpa integrasi API.
- Timeline aktivitas per kontak.

### M6 — Deal & Pipeline

- CRUD deal: judul, perusahaan, kontak utama, nilai (Rp), tahap, probabilitas, perkiraan tanggal closing, owner, alasan kalah.
- Tahap default: Prospek (10%) → Kualifikasi (25%) → Proposal (50%) → Negosiasi (75%) → Won (100%) / Lost (0%). Tahap dapat diatur admin.
- Tampilan **Kanban** dengan drag-and-drop (SortableJS via CDN) dan tampilan tabel.
- Perpindahan tahap tercatat otomatis sebagai riwayat (stage history).
- Lost wajib mengisi alasan kalah (harga, kompetitor, tidak ada budget, tidak ada respons, lainnya).

### M7 — Aktivitas & Tugas

- Jenis aktivitas: Telepon, Meeting, Email, WhatsApp, Kunjungan, Catatan.
- Tugas memiliki jatuh tempo, prioritas, status (Belum/Selesai), dan bisa ditautkan ke lead, kontak, perusahaan, atau deal (relasi polimorfik).
- Daftar tugas: Hari ini, Terlambat, Minggu ini, Selesai.
- Notifikasi dalam aplikasi (ikon lonceng) untuk tugas jatuh tempo dan penugasan baru.

### M8 — Produk & Penawaran (P1)

- Katalog produk/layanan: SKU, nama, harga satuan, satuan, aktif/nonaktif.
- Penawaran dari deal: nomor otomatis `QUO/2026/10/0001`, item, diskon per item, PPN 11% (dapat diatur), total, berlaku sampai.
- Cetak/PDF lewat halaman print-friendly (print CSS), tanpa library PDF di v1.0.

### M9 — Laporan, Pengaturan, User, Audit

- Laporan: kinerja per sales, win/loss per alasan, sumber lead terbaik, forecast per bulan; filter periode; ekspor CSV.
- Pengaturan: nama perusahaan, logo, mata uang, tarif PPN, master tahap pipeline, sumber lead, industri.
- Manajemen user (admin): buat, nonaktifkan, reset password, set peran dan manager.
- Log audit: siapa mengubah apa dan kapan (create/update/delete pada entitas utama, login sukses/gagal).

---

## 5. Autentikasi dan otorisasi

Login hanya memakai **username + password**; email bukan identitas login dan tidak dipakai untuk reset password. Auth dibangun sendiri (custom, ringan) memakai Session, Filter, dan Throttler bawaan CI4, karena CodeIgniter Shield secara default menjadikan email sebagai identitas utama.

### Aturan username

- 4–30 karakter, huruf kecil, angka, titik, dan underscore: regex `^[a-z0-9._]{4,30}$`.
- Unik (case-insensitive; disimpan lowercase), tidak bisa diubah sendiri oleh user — hanya admin.
- Kata yang dicadangkan ditolak: `admin`, `root`, `system`, `api` (kecuali akun admin awal dari seeder).

### Aturan password

- Minimal 8 karakter, kombinasi huruf dan angka; disimpan dengan `password_hash()` (`PASSWORD_DEFAULT`) dan diverifikasi `password_verify()`, rehash otomatis bila algoritma berubah.
- User wajib ganti password saat login pertama bila dibuat/di-reset admin (`must_change_password = 1`).

### Alur

1. User membuka `/login`, mengisi username dan password.
2. Sistem memeriksa throttle: maksimal 5 percobaan gagal per username+IP dalam 15 menit; setelah itu terkunci 15 menit.
3. Jika valid dan akun aktif: `session()->regenerate()`, simpan `user_id`, `username`, `role`, `name` di session, perbarui `last_login_at`, catat ke log audit.
4. Jika `must_change_password = 1` → arahkan ke `/profile/password` sebelum boleh mengakses halaman lain.
5. Logout (`POST /logout`) menghancurkan session dan kembali ke `/login`.
6. Lupa password: tidak ada reset via email; user menghubungi admin, admin melakukan reset dari menu Manajemen User.

### Otorisasi

- Filter `auth` melindungi semua rute kecuali `/login`; filter `role:admin` / `role:admin,manager` untuk rute terbatas.
- Pembatasan data (sales hanya melihat miliknya) dilakukan di layer Model/Service lewat scope `owner_id`, bukan hanya disembunyikan di UI.
- Session timeout idle 2 jam; opsi "Ingat saya" 30 hari dengan token acak yang di-hash di tabel `remember_tokens` (P1).
- Pesan gagal login generik: "Username atau password salah" (tidak membocorkan username mana yang ada).

---

## 6. Arsitektur teknis

Aplikasi adalah monolit server-rendered **CodeIgniter 4.7.5** (rilis stabil terbaru per 9 Oktober 2026, [Packagist](https://packagist.org/packages/codeigniter4/framework)) dengan view PHP biasa; seluruh aset front-end dimuat dari CDN sehingga **tidak ada `npm`, Vite, atau langkah build**.

| Lapisan | Pilihan | Catatan |
| --- | --- | --- |
| Bahasa | PHP ≥ 8.2 | Syarat minimum CI 4.7; ekstensi `intl`, `mbstring`, `mysqlnd`, `json` |
| Framework | CodeIgniter 4.7.x via `composer create-project codeigniter4/appstarter` | Kunci `^4.7` di `composer.json` |
| Database | MySQL 8.0 / MariaDB 10.6+ | InnoDB, `utf8mb4_unicode_ci` |
| CSS | Tailwind CSS Play CDN `<script src="https://cdn.tailwindcss.com"></script>` | Konfigurasi tema lewat `tailwind.config` inline di layout |
| Interaksi | Alpine.js 3 (CDN) | Dropdown, modal, toggle sidebar, konfirmasi hapus |
| Kanban | SortableJS (CDN) | Drag-and-drop tahap deal, kirim via `fetch` + CSRF token |
| Grafik | Chart.js 4 (CDN) | Dashboard dan laporan |
| Ikon | Heroicons (SVG inline di partial view) | Tanpa icon font |
| Testing | PHPUnit (bawaan CI4) + `CIUnitTestCase` / `FeatureTestTrait` | Database test SQLite in-memory atau MySQL terpisah |

### Pola kode

- MVC CI4 standar ditambah lapisan **Service** (`app/Services`) untuk logika bisnis (konversi lead, pindah tahap deal, hitung penawaran) agar controller tetap tipis.
- Model memakai `useSoftDeletes`, `useTimestamps`, `$allowedFields`, dan aturan `$validationRules`; Entity untuk User, Deal, Quotation.
- Layout tunggal `app/Views/layouts/main.php` (sidebar + topbar) dengan `$this->extend()` / `$this->section()`; komponen UI berulang (tabel, badge status, modal, flash message, paginasi) sebagai partial di `app/Views/components/`.
- Rute dikelompokkan dengan `$routes->group()` dan filter `auth` / `role`; endpoint AJAX (Kanban, notifikasi) mengembalikan JSON dari controller yang sama.

### Catatan Tailwind CDN

- Play CDN cocok untuk internal tool tanpa build; tradeoff-nya file JS ~100 KB dan kelas di-generate di browser. Dapat diterima untuk target < 50 pengguna.
- Warna brand didefinisikan sekali di `tailwind.config` inline pada layout, bukan file konfigurasi terpisah.
- Kelas Tailwind ditulis literal di view (bukan disusun dinamis dari string) agar mudah dibaca dan dirawat.

---

## 7. Model data

Skema terdiri dari 20 tabel, semuanya dibuat lewat Migration CI4 (`php spark migrate`). Setiap tabel entitas utama memiliki `id` BIGINT UNSIGNED, `created_at`, `updated_at`, `deleted_at` (soft delete), dan `owner_id` bila relevan.

| Tabel | Kolom kunci | Relasi |
| --- | --- | --- |
| `users` | `username` (UNIQUE), `password_hash`, `name`, `email` (opsional, info kontak saja), `phone`, `role` ENUM(admin, manager, sales), `manager_id`, `is_active`, `must_change_password`, `last_login_at` | `manager_id` → `users.id` |
| `login_attempts` | `username`, `ip_address`, `success`, `user_agent`, `created_at` | — |
| `remember_tokens` (P1) | `user_id`, `selector`, `hashed_validator`, `expires_at` | → `users` |
| `lead_sources` | `name` (Website, Referral, Pameran, Instagram, Google Ads, Cold Call, WhatsApp) | — |
| `industries` | `name` | — |
| `pipeline_stages` | `name`, `probability`, `sort_order`, `is_won`, `is_lost` | — |
| `lost_reasons` | `name` | — |
| `companies` | `name`, `industry_id`, `npwp`, `phone`, `website`, `address`, `city`, `province`, `employee_count`, `owner_id` | → `industries`, `users` |
| `contacts` | `company_id`, `first_name`, `last_name`, `job_title`, `phone`, `whatsapp`, `email`, `birth_date`, `owner_id` | → `companies`, `users` |
| `leads` | `name`, `company_name`, `job_title`, `phone`, `email`, `source_id`, `status`, `owner_id`, `notes`, `converted_contact_id`, `converted_company_id`, `converted_deal_id`, `converted_at` | → `lead_sources`, `users`, hasil konversi |
| `deals` | `title`, `company_id`, `contact_id`, `stage_id`, `amount` DECIMAL(15,2), `probability`, `expected_close_date`, `closed_at`, `lost_reason_id`, `owner_id` | → `companies`, `contacts`, `pipeline_stages`, `lost_reasons`, `users` |
| `deal_stage_history` | `deal_id`, `from_stage_id`, `to_stage_id`, `changed_by`, `changed_at` | → `deals`, `pipeline_stages`, `users` |
| `activities` | `type` ENUM(call, meeting, email, whatsapp, visit, note), `subject`, `description`, `related_type`, `related_id`, `activity_at`, `user_id` | Polimorfik ke lead/contact/company/deal |
| `tasks` | `title`, `description`, `due_at`, `priority` ENUM(low, medium, high), `status` ENUM(open, done), `completed_at`, `related_type`, `related_id`, `assigned_to`, `created_by` | Polimorfik + → `users` |
| `products` | `sku` (UNIQUE), `name`, `unit`, `price` DECIMAL(15,2), `is_active` | — |
| `quotations` | `number` (UNIQUE), `deal_id`, `issue_date`, `valid_until`, `subtotal`, `discount_total`, `tax_rate`, `tax_amount`, `grand_total`, `status` | → `deals` |
| `quotation_items` | `quotation_id`, `product_id`, `description`, `qty`, `unit_price`, `discount_pct`, `line_total` | → `quotations`, `products` |
| `notifications` | `user_id`, `type`, `message`, `url`, `read_at` | → `users` |
| `audit_logs` | `user_id`, `action`, `entity`, `entity_id`, `old_values` JSON, `new_values` JSON, `ip_address` | → `users` |
| `settings` | `key` (UNIQUE), `value` | — |

**Indeks penting:** `users.username` UNIQUE; `leads(owner_id, status)`; `deals(owner_id, stage_id)`; `deals.expected_close_date`; `tasks(assigned_to, status, due_at)`; `activities(related_type, related_id)`; `contacts.phone` dan `leads.phone` untuk cek duplikat.

---

## 8. Seeder data contoh

Satu perintah `php spark db:seed DatabaseSeeder` mengisi aplikasi dengan data bisnis yang terasa nyata: konteks perusahaan distributor solusi IT di Indonesia, nama orang dan perusahaan lokal, nomor HP format `08xx`, kota-kota Indonesia, dan nilai deal dalam Rupiah yang wajar.

### Prinsip

- **Realistis, bukan lorem ipsum:** nama perusahaan fiktif tapi wajar (mis. PT Sinar Logistik Nusantara, CV Berkah Pangan Mandiri, PT Medika Sehat Sentosa), jabatan lazim (Purchasing Manager, Direktur Operasional, Kepala IT), alamat dengan kota dan provinsi yang benar.
- **Fiktif:** tidak memakai nama perusahaan, NPWP, atau nomor HP milik pihak nyata; NPWP dan nomor HP dibuat dengan pola valid tapi acak.
- **Deterministik:** Faker locale `id_ID` dengan `$faker->seed(2026)` sehingga hasil seeder selalu sama di setiap mesin. `fakerphp/faker` dipindah dari `require-dev` ke `require` agar seeder demo tetap jalan di server.
- **Tanggal relatif:** semua tanggal dihitung dari hari seeder dijalankan (6 bulan ke belakang sampai 3 bulan ke depan) sehingga dashboard, tugas "hari ini", dan tugas terlambat selalu terisi.
- **Konsisten:** setiap deal tertaut ke perusahaan dan kontak yang ada; deal Won punya `closed_at` dan riwayat tahap lengkap; deal Lost punya alasan kalah.

### Urutan dan volume seeder

| # | Seeder | Isi | Jumlah |
| --- | --- | --- | --- |
| 1 | `SettingSeeder` | Nama perusahaan "PT Nusantara Solusi Digital", mata uang IDR, PPN 11% | 1 set |
| 2 | `MasterDataSeeder` | Sumber lead, industri (Manufaktur, Retail, Logistik, Kesehatan, Pendidikan, F&B, Konstruksi, Perbankan), tahap pipeline, alasan kalah | ~30 baris |
| 3 | `UserSeeder` | 1 admin, 2 manager, 6 sales (3 per manager) | 9 user |
| 4 | `ProductSeeder` | Lisensi software, hardware, jasa implementasi, maintenance tahunan (Rp 1,5 jt – Rp 250 jt) | 25 produk |
| 5 | `CompanySeeder` | Perusahaan di Jakarta, Surabaya, Bandung, Semarang, Medan, Makassar, Denpasar, Balikpapan | 60 |
| 6 | `ContactSeeder` | 1–4 kontak per perusahaan | ~150 |
| 7 | `LeadSeeder` | Status tersebar: 30% Baru, 25% Dihubungi, 20% Kualifikasi, 10% Tidak Qualified, 15% Dikonversi | 120 |
| 8 | `DealSeeder` + riwayat tahap | Nilai Rp 15 jt – Rp 850 jt; ~55% masih terbuka, ~25% Won, ~20% Lost | 80 |
| 9 | `ActivitySeeder` | Telepon, meeting, WhatsApp, kunjungan dengan ringkasan percakapan realistis | ~400 |
| 10 | `TaskSeeder` | Termasuk 2–3 jatuh tempo hari ini dan 2–3 terlambat per sales | ~150 |
| 11 | `QuotationSeeder` | Penawaran untuk deal tahap Proposal ke atas, 2–6 item | 30 |

### Akun demo

Semua akun demo memakai `must_change_password = 0` (khusus demo).

| Username | Nama | Peran | Password demo |
| --- | --- | --- | --- |
| `admin` | Rizky Pratama | admin | `Admin#2026` |
| `dewi.lestari` | Dewi Lestari | manager | `Manager#2026` |
| `hendra.wijaya` | Hendra Wijaya | manager | `Manager#2026` |
| `andi.saputra` | Andi Saputra | sales | `Sales#2026` |
| `siti.rahma` | Siti Rahmawati | sales | `Sales#2026` |
| `budi.santoso` | Budi Santoso | sales | `Sales#2026` |
| `putri.ayu` | Putri Ayu Ningsih | sales | `Sales#2026` |
| `fajar.nugroho` | Fajar Nugroho | sales | `Sales#2026` |
| `maya.kusuma` | Maya Kusumawati | sales | `Sales#2026` |

### Contoh record yang dihasilkan

- **Perusahaan:** PT Sinar Logistik Nusantara — Logistik, Surabaya, Jawa Timur, 250 karyawan, owner `andi.saputra`.
- **Kontak:** Bambang Hartono, Kepala IT, 0812-3456-7810, bambang.hartono@sinarlogistik.co.id.
- **Deal:** "Implementasi ERP Gudang — 3 cabang", Rp 485.000.000, tahap Negosiasi (75%), closing estimasi akhir bulan depan.
- **Aktivitas:** "Meeting demo di kantor klien, Pak Bambang minta revisi harga lisensi untuk 40 user."
- **Tugas:** "Kirim revisi penawaran ke Pak Bambang", prioritas tinggi, jatuh tempo besok.

### Pemisahan seeder

`DatabaseSeeder` menjalankan semuanya untuk demo/development. Untuk produksi tersedia `ProductionSeeder` yang hanya mengisi pengaturan, master data, dan satu akun admin dengan password acak yang dicetak di terminal (wajib diganti saat login pertama).

---

## 9. Kebutuhan non-fungsional

### Keamanan

- CSRF aktif global (filter `csrf`), token disertakan di semua form dan header `X-CSRF-TOKEN` untuk request `fetch`.
- Escape output dengan `esc()` di semua view; validasi input lewat Validation CI4 di setiap create/update.
- Query hanya lewat Query Builder / Model (parameter binding), tanpa SQL string mentah dari input user.
- Header keamanan via filter `secureheaders`; cookie session `HttpOnly`, `SameSite=Lax`, `Secure` saat HTTPS.
- Content Security Policy mengizinkan hanya domain CDN yang dipakai (`cdn.tailwindcss.com`, `cdn.jsdelivr.net`).
- `.env` tidak masuk repository; `CI_ENVIRONMENT=production` mematikan toolbar debug dan pesan error detail.
- Otorisasi data diperiksa di server untuk setiap aksi (bukan hanya menyembunyikan tombol).

### Performa

- Halaman daftar < 1 detik untuk 10.000 baris per tabel (paginasi server-side, indeks pada kolom filter).
- Dashboard memakai query agregat; hasil di-cache 5 menit per user (Cache CI4 driver file).
- Hindari N+1: join atau eager-load manual untuk daftar deal/kontak.

### Kegunaan & tampilan

- Bahasa antarmuka Indonesia (file `app/Language/id`), format Rupiah `Rp 485.000.000`, tanggal `28 Okt 2026`, zona waktu `Asia/Jakarta`.
- Responsif: sidebar menjadi drawer di layar < 768 px; tabel dapat di-scroll horizontal.
- Konsisten: palet warna, badge status, dan tombol aksi didefinisikan sekali di komponen partial.

### Keandalan & operasional

- Semua perubahan skema hanya lewat Migration; tidak ada edit manual tabel.
- Log aplikasi di `writable/logs`; log audit di database dipertahankan minimal 1 tahun.
- Kompatibel shared hosting: document root mengarah ke folder `public/`.

### Kualitas kode

- Feature test untuk login (sukses, gagal, throttle, user nonaktif), filter peran, konversi lead, dan pindah tahap deal.
- Coding standard PSR-12 (CodeIgniter Coding Standard via php-cs-fixer).

---

## 10. Struktur folder, rute, dan rencana rilis

### Struktur folder utama

```
app/
  Config/Routes.php, Filters.php, Auth.php (aturan throttle & session)
  Controllers/  AuthController, DashboardController, LeadController, CompanyController,
                ContactController, DealController, ActivityController, TaskController,
                ProductController, QuotationController, ReportController,
                Admin/UserController, Admin/SettingController, Admin/AuditLogController
  Filters/      AuthFilter, RoleFilter, ForcePasswordChangeFilter
  Models/       UserModel, LeadModel, CompanyModel, ContactModel, DealModel, ...
  Entities/     User, Deal, Quotation
  Services/     AuthService, LeadConversionService, DealPipelineService,
                QuotationService, AuditLogger, NotificationService
  Database/Migrations/   satu file per tabel
  Database/Seeds/        DatabaseSeeder, ProductionSeeder, *Seeder per entitas
  Language/id/  App.php, Validation.php
  Views/
    layouts/main.php, layouts/auth.php
    components/ table, badge, modal, flash, pagination, empty-state
    auth/ dashboard/ leads/ companies/ contacts/ deals/ activities/
    tasks/ products/ quotations/ reports/ admin/
public/  index.php, assets/img/logo.png (tanpa CSS/JS hasil build)
```

### Rute utama

| Method | URI | Filter | Fungsi |
| --- | --- | --- | --- |
| GET / POST | `/login` | — | Form dan proses login username |
| POST | `/logout` | auth | Keluar |
| GET / POST | `/profile`, `/profile/password` | auth | Profil dan ganti password |
| GET | `/` | auth | Dashboard |
| resource | `/leads`, `/companies`, `/contacts`, `/deals`, `/products`, `/quotations` | auth | CRUD (index, new, create, show, edit, update, delete) |
| POST | `/leads/{id}/convert` | auth | Konversi lead |
| GET | `/deals/board` | auth | Kanban pipeline |
| POST | `/deals/{id}/stage` | auth | Pindah tahap (JSON, dari drag-and-drop) |
| GET / POST | `/tasks`, `/tasks/{id}/done` | auth | Daftar dan selesaikan tugas |
| POST | `/activities` | auth | Catat aktivitas dari halaman detail mana pun |
| GET | `/reports/*` | role:admin,manager | Laporan dan ekspor CSV |
| any | `/admin/*` | role:admin | User, pengaturan, master data, log audit |

### Rencana rilis

Estimasi total 8 minggu untuk 1 developer full-stack; tanggal mulai belum ditetapkan, sehingga durasi ditulis dalam minggu.

| Fase | Minggu | Cakupan | Gate di akhir fase |
| --- | --- | --- | --- |
| 1. Fondasi | 1 | Setup CI 4.7, migrasi 20 tabel, layout Tailwind CDN, login username, seeder master & user | **Gate 1 — demo:** login + seeder jalan |
| 2. Data inti | 2–3 | Lead + konversi, Perusahaan, Kontak, impor/ekspor CSV, seeder data demo | — |
| 3. Pipeline | 4–5 | Deal + Kanban, riwayat tahap, aktivitas & tugas, notifikasi | **Gate 2 — pipeline:** alur lead sampai deal Won end-to-end |
| 4. Insight & admin | 6 | Dashboard KPI, laporan + CSV, manajemen user, log audit | — |
| 5. Rilis v1.0 | 7–8 | Produk & penawaran, feature test, UAT dengan tim, deploy produksi | **Go-live v1.0** setelah lolos UAT |

---

## 11. Risiko, asumsi, dan pertanyaan terbuka

### Risiko

| Risiko | Dampak | Mitigasi |
| --- | --- | --- |
| Tailwind Play CDN tidak dianjurkan untuk produksi publik dan bergantung koneksi internet | Tampilan rusak bila CDN tidak terjangkau; JS ~100 KB per halaman | Aplikasi internal dengan pengguna sedikit; versi CDN dikunci; opsi fallback: unduh skrip CDN ke `public/vendor/` (tetap tanpa build) |
| Tanpa reset password mandiri, admin menjadi bottleneck | User terkunci menunggu admin | Minimal 2 akun admin; reset cepat satu klik dari daftar user |
| Username bisa ditebak (mis. `nama.belakang`) | Serangan brute force | Throttle 5x/15 menit, log percobaan gagal, password minimal 8 karakter |
| Faker di `require` menambah dependensi produksi | Ukuran vendor naik sedikit | Hanya dipanggil oleh seeder, bukan saat request |
| Scope data per owner terlewat di salah satu query | Sales melihat data milik orang lain | Scope terpusat di Model dasar + feature test per peran |

### Asumsi

- Satu perusahaan pengguna per instalasi (single-tenant), mata uang tunggal IDR.
- Server mendukung PHP 8.2+, Composer, dan MySQL 8 / MariaDB 10.6+.
- Pengguna selalu online saat memakai aplikasi (CDN bisa diakses).
- Email user hanya disimpan sebagai informasi kontak, tidak untuk login atau notifikasi.

### Pertanyaan terbuka

- [ ] Apakah manager boleh melihat data sales di luar timnya, atau hanya timnya sendiri?
- [ ] Apakah modul Penawaran (M8) dan cetak PDF wajib ada di v1.0, atau cukup di v1.1?
- [ ] Perlukah fitur "Ingat saya" (remember me) sejak awal?
- [ ] Kebijakan masa simpan data lead yang tidak qualified: dihapus otomatis setelah berapa lama?
- [ ] Apakah tahap pipeline default di atas sesuai proses penjualan Anda, atau perlu disesuaikan?
- [ ] Konteks bisnis data seeder (distributor solusi IT) sudah tepat, atau ingin industri lain?
