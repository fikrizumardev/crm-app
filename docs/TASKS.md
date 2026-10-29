# TASKS — Sistem CRM CodeIgniter 4

Daftar pekerjaan implementasi berdasarkan [PRD.md](PRD.md). Urutan mengikuti rencana rilis 5 fase (8 minggu, 1 developer full-stack).

## Cara pakai

- Centang `[x]` bila task selesai dan memenuhi kriteria terima (**AC**).
- ID task: `T<fase>.<nomor>`, mis. `T2.03`. Pakai ID ini di pesan commit, mis. `feat(lead): konversi lead (T2.04)`.
- Kolom **PRD** menunjuk bagian PRD yang menjadi acuan.
- Task bertanda **(P1)** boleh ditunda ke rilis minor bila waktu tidak cukup.

### Definition of Done (berlaku untuk semua task)

- Kode mengikuti PSR-12 dan pola di PRD §6 (controller tipis, logika di Service, validasi di Model).
- Semua output view di-escape dengan `esc()`; semua form memakai `csrf_field()`.
- Data dibatasi per peran/owner di server, bukan hanya di UI (PRD §3, §5).
- UI memakai Tailwind CDN dan komponen partial yang sudah ada, tanpa build step.
- Teks antarmuka berbahasa Indonesia, angka uang format `Rp 1.250.000`, tanggal `28 Okt 2026`.
- Feature test untuk alur utama task tersebut lulus (`vendor/bin/phpunit`).

---

## Ringkasan progres

| Fase | Minggu | Task | Gate |
| --- | --- | --- | --- |
| 1. Fondasi | 1 | T1.01 – T1.14 | Gate 1 — demo: login + seeder jalan |
| 2. Data inti | 2–3 | T2.01 – T2.12 | — |
| 3. Pipeline | 4–5 | T3.01 – T3.12 | Gate 2 — alur lead sampai deal Won end-to-end |
| 4. Insight & admin | 6 | T4.01 – T4.10 | — |
| 5. Rilis v1.0 | 7–8 | T5.01 – T5.14 | Go-live v1.0 setelah lolos UAT |
| Backlog | — | B.01 – B.07 | — |

---

## Fase 1 — Fondasi (Minggu 1)

### Setup proyek

- [x] **T1.01 Inisialisasi proyek CodeIgniter 4.7** — PRD §6
  - `composer create-project codeigniter4/appstarter .` lalu kunci `"codeigniter4/framework": "^4.7"`.
  - Pindahkan `fakerphp/faker` dari `require-dev` ke `require` (PRD §8).
  - Buat `.env` dari `env`: `CI_ENVIRONMENT=development`, `app.baseURL`, kredensial database, `app.appTimezone=Asia/Jakarta`, `app.defaultLocale=id`.
  - Tambahkan `.gitignore` (pastikan `.env`, `vendor/`, `writable/*` tidak ikut), `git init`.
  - **AC:** `php spark serve` menampilkan halaman welcome; `php -v` ≥ 8.2.

- [x] **T1.02 Konfigurasi dasar aplikasi** — PRD §6, §9
  - `Config/App.php`: locale `id`, `supportedLocales = ['id']`, timezone `Asia/Jakarta`, `indexPage = ''`.
  - `Config/Filters.php`: aktifkan `csrf` dan `secureheaders` secara global.
  - `Config/Security.php`: CSRF via cookie, `regenerate = true`, header `X-CSRF-TOKEN`.
  - `Config/Session.php`: `expiration = 7200`, cookie `HttpOnly`, `SameSite=Lax`.
  - **AC:** form tanpa token CSRF ditolak; header keamanan muncul di response.

- [x] **T1.03 Helper format Indonesia** — PRD §9
  - `app/Helpers/format_helper.php`: `rupiah($angka)`, `tanggal_id($date, $withTime = false)`, `waktu_relatif($date)`.
  - Autoload helper di `Config/Autoload.php`.
  - **AC:** `rupiah(485000000)` → `Rp 485.000.000`; `tanggal_id('2026-10-28')` → `28 Okt 2026`.

### Database

- [x] **T1.04 Migration tabel user & keamanan** — PRD §7
  - `users`, `login_attempts`, `settings`, `audit_logs`, `notifications`.
  - `users.username` UNIQUE, `role` ENUM(admin, manager, sales), `manager_id` FK ke `users.id`.
  - **AC:** `php spark migrate` sukses; `php spark migrate:rollback` bersih.

- [x] **T1.05 Migration master data** — PRD §7
  - `lead_sources`, `industries`, `pipeline_stages` (`probability`, `sort_order`, `is_won`, `is_lost`), `lost_reasons`.

- [x] **T1.06 Migration entitas CRM** — PRD §7
  - Urutan sesuai FK: `companies` → `contacts` → `leads` → `deals` → `deal_stage_history` → `activities` → `tasks` → `products` → `quotations` → `quotation_items`.
  - Semua entitas utama punya `created_at`, `updated_at`, `deleted_at`, dan `owner_id` bila relevan.
  - Buat semua indeks di PRD §7 (mis. `deals(owner_id, stage_id)`, `tasks(assigned_to, status, due_at)`).
  - **AC:** 19 tabel terbentuk dari T1.04–T1.06 (`remember_tokens` menyusul di B.01); engine InnoDB, collation `utf8mb4_unicode_ci`.

### Layout & komponen UI

- [x] **T1.07 Layout utama Tailwind CDN** — PRD §6
  - `app/Views/layouts/main.php`: `<script src="https://cdn.tailwindcss.com">` + `tailwind.config` inline (warna brand), Alpine.js 3 via CDN.
  - Sidebar (menu sesuai peran), topbar (nama user, lonceng notifikasi, menu profil/logout), area konten, flash message.
  - Sidebar berubah jadi drawer di layar < 768 px (Alpine `x-data`).
  - `app/Views/layouts/auth.php` untuk halaman login.
  - Meta tag `csrf-token` untuk dipakai `fetch`.
  - **AC:** layout rapi di desktop dan mobile; tidak ada file CSS/JS hasil build di `public/`.

- [x] **T1.08 Komponen partial reusable** — PRD §6
  - `app/Views/components/`: `table`, `badge` (status/tahap), `modal` (konfirmasi hapus), `flash`, `pagination` (template pager CI4 bergaya Tailwind), `empty-state`, `form-field` (label + input + pesan error).
  - Ikon Heroicons sebagai SVG inline di `components/icons/`.
  - **AC:** komponen dipakai minimal di satu halaman contoh tanpa duplikasi markup.

### Autentikasi (username)

- [x] **T1.09 UserModel & Entity User** — PRD §5, §7
  - Validasi username `^[a-z0-9._]{4,30}$`, unik, simpan lowercase; tolak kata cadangan `admin`, `root`, `system`, `api` (kecuali dari seeder).
  - Entity `User`: `setPassword()` memakai `password_hash(PASSWORD_DEFAULT)`, `isAdmin()`, `isManager()`, `isSales()`.

- [x] **T1.10 AuthService & AuthController** — PRD §5
  - `GET/POST /login`, `POST /logout`.
  - Login dengan username + password; pesan gagal generik "Username atau password salah".
  - Throttle 5 percobaan gagal per username+IP dalam 15 menit (tabel `login_attempts`), lalu kunci 15 menit.
  - Sukses: `session()->regenerate()`, simpan `user_id`, `username`, `role`, `name`; update `last_login_at`; rehash password bila perlu (`password_needs_rehash`).
  - Akun `is_active = 0` tidak bisa login.
  - Catat login sukses/gagal ke log audit.
  - **AC:** tidak ada field email di form login; feature test login sukses, gagal, throttle, dan user nonaktif lulus.

- [x] **T1.11 Filter auth, role, dan paksa ganti password** — PRD §5
  - `AuthFilter` (redirect ke `/login` bila belum login), `RoleFilter` (argumen `role:admin,manager`), `ForcePasswordChangeFilter` (arahkan ke `/profile/password` bila `must_change_password = 1`).
  - Daftarkan alias di `Config/Filters.php`; kelompokkan rute di `Config/Routes.php`.
  - **AC:** akses rute admin oleh sales menghasilkan 403; user dengan `must_change_password` tidak bisa membuka halaman lain.

- [x] **T1.12 Halaman profil & ganti password** — PRD §5
  - `GET/POST /profile` (nama, no. HP, email kontak), `GET/POST /profile/password` (password lama, baru, konfirmasi; min. 8 karakter huruf + angka).
  - Username tampil read-only.

### Seeder awal

- [x] **T1.13 SettingSeeder, MasterDataSeeder, UserSeeder** — PRD §8
  - Setting: "PT Nusantara Solusi Digital", IDR, PPN 11%.
  - Master: 7 sumber lead, 8 industri, 6 tahap pipeline (Prospek 10% → Won 100% / Lost 0%), 5 alasan kalah.
  - 9 akun demo sesuai tabel PRD §8 (1 admin, 2 manager, 6 sales; 3 sales per manager).
  - Seeder idempoten: aman dijalankan ulang setelah `migrate:refresh`.

- [x] **T1.14 Dashboard placeholder** — PRD §4 M2
  - `GET /` menampilkan sapaan + kartu KPI kosong agar alur login → dashboard bisa didemokan.

> **Gate 1 — demo:** `php spark migrate && php spark db:seed DatabaseSeeder`, lalu login sebagai `admin`, `dewi.lestari`, dan `andi.saputra` berhasil, menu sesuai peran, logout berfungsi.

---

## Fase 2 — Data inti (Minggu 2–3)

### Fondasi akses data

- [x] **T2.01 Base model dengan scope owner** — PRD §3, §5
  - `app/Models/OwnedModel.php`: method `visibleTo(User $user)` — admin: semua; manager: owner = dirinya atau sales yang `manager_id`-nya dia; sales: owner = dirinya.
  - Method `canEdit(User $user, $record)` dan `canDelete(...)` sesuai matriks PRD §3.
  - **AC:** unit test memastikan sales A tidak bisa membaca/mengubah record milik sales B (akses langsung via URL menghasilkan 404).

- [x] **T2.02 AuditLogger service** — PRD §4 M9
  - `AuditLogger::log($action, $entity, $id, $old, $new)`; dipanggil dari Service saat create/update/delete.

### Lead (M3)

- [x] **T2.03 CRUD Lead** — PRD §4 M3
  - Rute resource `/leads`; daftar dengan pencarian (nama, perusahaan, no. HP), filter status/sumber/owner/rentang tanggal, sort, paginasi 25.
  - Form: nama, perusahaan, jabatan, no. HP, email (opsional), sumber, status, owner (hanya admin/manager yang bisa memilih owner), catatan.
  - Peringatan duplikat bila no. HP atau email sudah ada di `leads` atau `contacts`.
  - Soft delete dengan modal konfirmasi.

- [x] **T2.04 Konversi Lead** — PRD §4 M3
  - `POST /leads/{id}/convert` lewat `LeadConversionService` dalam satu transaksi DB.
  - Membuat Perusahaan (atau memilih yang sudah ada), Kontak, dan Deal opsional; isi `converted_*_id` dan `converted_at`; status → Dikonversi.
  - Lead yang sudah dikonversi tidak bisa dikonversi ulang.
  - **AC:** feature test konversi menghasilkan 3 record terhubung; rollback bila salah satu gagal.

### Perusahaan (M4) & Kontak (M5)

- [x] **T2.05 CRUD Perusahaan** — PRD §4 M4
  - Field sesuai PRD; validasi NPWP opsional (15/16 digit); dropdown provinsi Indonesia (array statis di `Config/Indonesia.php`).
  - Halaman detail dengan tab (Alpine): Kontak, Deal, Aktivitas, Catatan.

- [x] **T2.06 CRUD Kontak** — PRD §4 M5
  - Field sesuai PRD; pilih perusahaan via select dengan pencarian sederhana.
  - Tombol "Chat WhatsApp" (`https://wa.me/62...`, konversi `08xx` → `628xx`) dan "Telepon" (`tel:`).
  - Halaman detail dengan timeline aktivitas (diisi di Fase 3).

- [x] **T2.07 Tab detail Perusahaan terhubung** — PRD §4 M4
  - Tab Kontak menampilkan kontak milik perusahaan; tab Deal & Aktivitas menyusul setelah T3.01 dan T3.05.

### Impor/ekspor

- [x] **T2.08 Ekspor CSV** — PRD §3, §4 M3
  - Ekspor lead, perusahaan, kontak sesuai filter aktif; hanya admin/manager.
  - Header kolom berbahasa Indonesia; encoding UTF-8 dengan BOM agar terbaca Excel.

- [x] **T2.09 Impor CSV Lead (P1)** — PRD §4 M3
  - Unduh template CSV; unggah; validasi per baris; ringkasan "X berhasil, Y gagal" + daftar error per baris.

### Seeder data demo

- [x] **T2.10 ProductSeeder & CompanySeeder** — PRD §8
  - Faker `id_ID` dengan `$faker->seed(2026)`.
  - 25 produk (lisensi, hardware, jasa implementasi, maintenance; Rp 1,5 jt – Rp 250 jt).
  - 60 perusahaan di 8 kota (Jakarta, Surabaya, Bandung, Semarang, Medan, Makassar, Denpasar, Balikpapan) dengan provinsi yang benar; termasuk record showcase "PT Sinar Logistik Nusantara".

- [x] **T2.11 ContactSeeder & LeadSeeder** — PRD §8
  - ~150 kontak (1–4 per perusahaan) dengan jabatan lazim, email `nama@domainperusahaan.co.id`, no. HP `08xx`.
  - 120 lead dengan distribusi status 30/25/20/10/15%; lead Dikonversi tertaut ke perusahaan/kontak hasil konversi.
  - Semua tanggal relatif terhadap hari seeder dijalankan.

- [x] **T2.12 Feature test Fase 2** — PRD §9
  - CRUD lead/perusahaan/kontak per peran, konversi lead, cek duplikat, pembatasan akses owner.

---

## Fase 3 — Pipeline (Minggu 4–5)

### Deal & pipeline (M6)

- [x] **T3.01 CRUD Deal (tampilan tabel)** — PRD §4 M6
  - Field sesuai PRD; probabilitas default mengikuti tahap tapi bisa diubah.
  - Pindah ke tahap Lost wajib memilih alasan kalah; Won/Lost mengisi `closed_at`.
  - Filter: tahap, owner, rentang tanggal closing, nilai minimum.

- [x] **T3.02 DealPipelineService & riwayat tahap** — PRD §4 M6
  - `moveToStage($deal, $stageId, $user)`: validasi, update tahap/probabilitas, tulis `deal_stage_history`, audit log, transaksi DB.
  - Dipakai oleh form edit dan Kanban.

- [x] **T3.03 Kanban board** — PRD §4 M6
  - `GET /deals/board`: kolom per tahap (urut `sort_order`) dengan total nilai Rp dan jumlah deal per kolom.
  - Drag-and-drop dengan SortableJS (CDN) → `POST /deals/{id}/stage` via `fetch` + header `X-CSRF-TOKEN`; respons JSON.
  - Drop ke kolom Lost membuka modal alasan kalah; gagal → kartu kembali ke posisi semula + toast error.
  - **AC:** sales hanya bisa memindahkan deal miliknya; total kolom ter-update tanpa reload.

- [x] **T3.04 Detail Deal** — PRD §4 M6
  - Info deal, perusahaan & kontak terkait, riwayat tahap (timeline), aktivitas, tugas, dan (Fase 5) penawaran.

### Aktivitas & tugas (M7)

- [x] **T3.05 Aktivitas (polimorfik)** — PRD §4 M7
  - `POST /activities` dari halaman detail lead/kontak/perusahaan/deal (form modal); `related_type` + `related_id` divalidasi terhadap whitelist.
  - Jenis: Telepon, Meeting, Email, WhatsApp, Kunjungan, Catatan; ikon berbeda per jenis.
  - Partial `components/activity-timeline` dipakai di semua halaman detail.

- [x] **T3.06 Tugas** — PRD §4 M7
  - `GET /tasks` dengan tab Hari ini, Terlambat, Minggu ini, Selesai; `POST /tasks/{id}/done`.
  - Buat tugas dari halaman detail mana pun (tertaut) atau mandiri; manager/admin bisa assign ke sales timnya.
  - Badge prioritas (rendah/sedang/tinggi) dan penanda terlambat berwarna merah.

- [x] **T3.07 Notifikasi dalam aplikasi** — PRD §4 M7
  - `NotificationService`: buat notifikasi saat tugas di-assign dan saat lead/deal di-reassign.
  - Tugas jatuh tempo hari ini dibuat sebagai notifikasi saat user login pertama kali di hari itu (tanpa cron).
  - Dropdown lonceng di topbar (jumlah belum dibaca, tandai sudah dibaca) via endpoint JSON.

- [x] **T3.08 Lengkapi tab detail** — PRD §4 M4, M5
  - Tab Deal & Aktivitas di detail Perusahaan, timeline di detail Kontak dan Lead.

### Seeder

- [x] **T3.09 DealSeeder + riwayat tahap** — PRD §8
  - 80 deal, nilai Rp 15 jt – Rp 850 jt; ~55% terbuka, ~25% Won, ~20% Lost (dengan alasan kalah).
  - Riwayat tahap konsisten (deal Negosiasi punya jejak Prospek → Kualifikasi → Proposal → Negosiasi); Won punya `closed_at` tersebar 6 bulan terakhir.
  - Record showcase "Implementasi ERP Gudang — 3 cabang", Rp 485.000.000, Negosiasi.

- [x] **T3.10 ActivitySeeder & TaskSeeder** — PRD §8
  - ~400 aktivitas dengan ringkasan percakapan realistis (kalimat template Bahasa Indonesia, bukan lorem ipsum).
  - ~150 tugas, termasuk 2–3 jatuh tempo hari ini dan 2–3 terlambat per sales.

- [x] **T3.11 Feature test Fase 3** — PRD §9
  - Pindah tahap (form & JSON), Lost tanpa alasan ditolak, akses Kanban per peran, tugas selesai, notifikasi.

- [x] **T3.12 Uji alur end-to-end manual** — Gate 2
  - Lead baru → konversi → deal → aktivitas & tugas → pindah tahap via Kanban → Won.

> **Gate 2 — pipeline:** alur T3.12 berjalan tanpa error untuk akun sales dan manager.

---

## Fase 4 — Insight & admin (Minggu 6)

### Dashboard (M2)

- [x] **T4.01 KPI dashboard** — PRD §4 M2
  - Kartu: lead baru bulan ini, deal terbuka, total nilai pipeline, nilai Won bulan ini, win rate (Won / (Won + Lost) 90 hari terakhir).
  - Sales melihat angka miliknya; manager/admin dapat filter per sales.
  - Cache 5 menit per user (PRD §9).

- [x] **T4.02 Grafik dashboard** — PRD §4 M2
  - Chart.js via CDN: nilai pipeline per tahap (bar) dan tren nilai Won 6 bulan terakhir (line).
  - Widget "Tugas saya hari ini" dan "Tugas terlambat" (maks. 5 item + tautan ke `/tasks`).

### Laporan (M9)

- [x] **T4.03 Laporan kinerja sales** — PRD §4 M9
  - Per sales: lead baru, deal dibuat, Won (jumlah & Rp), Lost, win rate, jumlah aktivitas; filter periode.

- [x] **T4.04 Laporan win/loss, sumber lead, forecast (P1)** — PRD §4 M9
  - Win/loss per alasan kalah; konversi per sumber lead; forecast per bulan = Σ(nilai × probabilitas) per `expected_close_date`.
  - Semua laporan bisa diekspor CSV; rute `role:admin,manager`.

### Admin

- [x] **T4.05 Manajemen user** — PRD §4 M9, §5
  - `/admin/users`: daftar, tambah (password awal + `must_change_password = 1`), edit (nama, peran, manager, status aktif), nonaktifkan.
  - Reset password satu klik: generate password acak, tampilkan sekali ke admin, set `must_change_password = 1`.
  - Admin tidak bisa menonaktifkan dirinya sendiri atau admin terakhir.

- [x] **T4.06 Reassign owner** — PRD §3
  - Ubah owner lead/deal/perusahaan/kontak (admin: semua; manager: dalam timnya); opsi bulk dari halaman daftar.
  - Kirim notifikasi ke owner baru.

- [x] **T4.07 Pengaturan aplikasi** — PRD §4 M9
  - `/admin/settings`: nama perusahaan, unggah logo (`public/uploads/`), mata uang, tarif PPN.

- [x] **T4.08 Master data** — PRD §4 M9
  - CRUD sumber lead, industri, alasan kalah; tahap pipeline (nama, probabilitas, urutan via drag-and-drop).
  - Tahap yang masih dipakai deal tidak bisa dihapus.

- [x] **T4.09 Log audit** — PRD §4 M9
  - `/admin/audit-logs`: filter user, entitas, aksi, tanggal; tampilkan diff `old_values` vs `new_values`.

- [x] **T4.10 Feature test Fase 4** — PRD §9
  - Angka KPI sesuai data seeder, pembatasan laporan per peran, reset password, proteksi admin terakhir.

---

## Fase 5 — Rilis v1.0 (Minggu 7–8)

### Produk & penawaran (M8, P1)

- [ ] **T5.01 CRUD Produk** — PRD §4 M8
  - SKU unik, nama, satuan, harga, aktif/nonaktif; produk nonaktif tidak muncul di form penawaran.

- [ ] **T5.02 Penawaran (Quotation)** — PRD §4 M8
  - Buat dari halaman detail deal; nomor otomatis `QUO/YYYY/MM/NNNN` (reset per bulan, aman dari race condition).
  - Item dinamis (Alpine): pilih produk atau deskripsi bebas, qty, harga, diskon %; subtotal, PPN (dari pengaturan), grand total dihitung di klien untuk tampilan dan dihitung ulang di server (`QuotationService`) sebagai sumber kebenaran.
  - Status: Draft, Terkirim, Diterima, Ditolak.

- [ ] **T5.03 Cetak penawaran** — PRD §4 M8
  - Halaman print-friendly (`@media print`) dengan logo, data perusahaan, item, total, dan masa berlaku; simpan PDF lewat dialog print browser.

- [ ] **T5.04 QuotationSeeder** — PRD §8
  - 30 penawaran untuk deal tahap Proposal ke atas, 2–6 item tiap penawaran.

### Penyelesaian seeder

- [ ] **T5.05 DatabaseSeeder & ProductionSeeder** — PRD §8
  - `DatabaseSeeder` memanggil 11 seeder sesuai urutan PRD §8.
  - `ProductionSeeder`: setting + master data + 1 admin dengan password acak dicetak di terminal (`CLI::write`), `must_change_password = 1`.
  - **AC:** dari database kosong, `php spark migrate && php spark db:seed DatabaseSeeder` selesai < 1 menit tanpa error.

### Kualitas & keamanan

- [ ] **T5.06 Content Security Policy** — PRD §9
  - Aktifkan CSP di `Config/ContentSecurityPolicy.php`; izinkan `cdn.tailwindcss.com` dan `cdn.jsdelivr.net` (Alpine, SortableJS, Chart.js).
  - Kunci versi semua skrip CDN (mis. `alpinejs@3.x.x`, `chart.js@4.x.x`).

- [ ] **T5.07 Audit keamanan mandiri** — PRD §9
  - Cek setiap rute: filter auth/role, scope owner, `esc()` di view, CSRF di semua POST, tidak ada SQL mentah dari input.
  - Uji IDOR: akses `/leads/{id}`, `/deals/{id}` milik user lain sebagai sales.

- [ ] **T5.08 Performa** — PRD §2, §9
  - Generate 10.000 baris lead/deal (seeder khusus `PerformanceSeeder`, tidak dipanggil `DatabaseSeeder`) dan pastikan halaman daftar < 1 detik.
  - Cek N+1 dengan Debug Toolbar.

- [ ] **T5.09 Lengkapi test suite** — PRD §9
  - Pastikan semua feature test Fase 1–5 lulus; tambahkan test penomoran dan perhitungan penawaran.

- [ ] **T5.10 Responsif & polishing UI**
  - Uji di lebar 375 px, 768 px, 1280 px; empty state di semua daftar; konsistensi badge dan tombol.

### Dokumentasi & rilis

- [ ] **T5.11 README.md**
  - Kebutuhan server, langkah instalasi (< 10 menit), daftar akun demo, perintah migrasi/seeder, cara deploy ke shared hosting (document root `public/`).

- [ ] **T5.12 UAT dengan tim sales**
  - Skenario uji per peran (admin, manager, sales) berdasarkan PRD §4; catat temuan sebagai issue dan perbaiki yang blocking.

- [ ] **T5.13 Deploy produksi**
  - `composer install --no-dev --optimize-autoloader` (Faker tetap terpasang karena ada di `require`), `CI_ENVIRONMENT=production`, `php spark migrate`, `php spark db:seed ProductionSeeder`, HTTPS aktif, permission folder `writable/`.

- [ ] **T5.14 Go-live v1.0**
  - Admin mengganti password awal, membuat akun tim, dan menjalankan smoke test (login, buat lead, pindah deal, cetak penawaran).

> **Go-live v1.0:** semua task P0 selesai, UAT tanpa temuan blocking, deploy produksi berjalan.

---

## Backlog (setelah v1.0)

- [ ] **B.01** Fitur "Ingat saya" dengan tabel `remember_tokens` (selector + validator ter-hash, 30 hari) — PRD §5.
- [ ] **B.02** Impor CSV untuk perusahaan dan kontak.
- [ ] **B.03** Kebijakan masa simpan lead Tidak Qualified (hapus/arsip otomatis via `php spark` command + cron).
- [ ] **B.04** Fallback aset CDN: salin skrip ke `public/vendor/` bila server tanpa akses internet.
- [ ] **B.05** Pengingat ulang tahun kontak di dashboard.
- [ ] **B.06** Ekspor penawaran ke PDF di server (mis. Dompdf).
- [ ] **B.07** Integrasi email/WhatsApp (di luar cakupan v1.0, PRD §2).

---

## Keputusan yang masih menunggu (PRD §11)

Task di bawah dikerjakan dengan asumsi default; ubah bila jawabannya berbeda.

| Pertanyaan | Asumsi default | Task terdampak |
| --- | --- | --- |
| Manager boleh melihat data di luar timnya? | Tidak, hanya timnya | T2.01, T4.01, T4.03, T4.06 |
| Penawaran (M8) wajib di v1.0? | Ya, dikerjakan di Fase 5 | T5.01 – T5.04 |
| "Ingat saya" sejak awal? | Tidak, masuk backlog | B.01 |
| Masa simpan lead Tidak Qualified? | Tidak dihapus otomatis | B.03 |
| Tahap pipeline default sudah sesuai? | Ya, bisa diubah admin | T1.13, T4.08 |
| Konteks seeder distributor solusi IT? | Ya | T1.13, T2.10 – T3.10, T5.04 |
