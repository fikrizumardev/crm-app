# Audit Keamanan Mandiri — v1.0

Hasil pemeriksaan T5.07 terhadap PRD §9. Sebagian besar pemeriksaan dikunci dengan test otomatis agar tidak mundur di versi berikutnya (`tests/feature/ReleaseReadinessTest.php` dan test per modul).

| Area | Pemeriksaan | Hasil | Bukti / test |
| --- | --- | --- | --- |
| Autentikasi | Semua rute selain `/login` wajib login dan melewati filter ganti-password | ✅ | `testSemuaRuteKecualiLoginWajibAuthDanRuteTerbatasPakaiRole` (memeriksa >80 rute) |
| Otorisasi peran | `/admin/*` khusus admin; laporan, ekspor, dan alih owner khusus admin/manager; kelola produk khusus admin | ✅ | Test yang sama + `AdminTest`, `DashboardReportTest` |
| Akses data (IDOR) | Sales membuka/mengubah/menghapus lead, perusahaan, kontak, deal, tugas, penawaran milik orang lain → 404 | ✅ | `testIdorSemuaEntitasSebagaiSales`, `QuotationTest::testAksesPenawaranMengikutiOwnerDeal` |
| Cakupan manager | Manager hanya melihat & mengubah data timnya | ✅ | `OwnedModelTest`, `DealTest::testKanbanSesuaiCakupan` |
| Login | Pesan gagal generik, kunci 5×/15 menit per username+IP, waktu respons seragam untuk username tidak dikenal, regenerasi session ID, rehash password otomatis | ✅ | `AuthTest` |
| CSRF | Filter `csrf` global untuk semua POST; permintaan AJAX memakai header `X-CSRF-TOKEN`, token diperbarui setiap respons | ✅ | `SecurityTest::testFormTanpaTokenCsrfDitolak` |
| XSS | Seluruh data dinamis di view lewat `esc()`; output tanpa `esc()` hanya string statis, kelas CSS dari konstanta, atau angka | ✅ | Review manual semua `<?= $…` di `app/Views` |
| Content Security Policy | Skrip hanya dari domain sendiri + `cdn.tailwindcss.com` + `cdn.jsdelivr.net`, skrip inline wajib nonce, `object-src 'none'`, `frame-ancestors 'self'`; nol pelanggaran di 22 halaman (mode production, dicek di Chrome) | ✅ | `testHeaderKeamananDanCsp` |
| SQL injection | Semua query lewat Query Builder/Model (binding). Satu query mentah (penomoran penawaran `FOR UPDATE`) memakai parameter binding; ekspresi `escape=false` hanya berisi konstanta | ✅ | Review `app/` |
| Mass assignment | Model memakai `$allowedFields`; controller hanya meneruskan field yang diizinkan | ✅ | Review controller |
| Open redirect | `redirect_url` setelah login, `return_to`, dan URL notifikasi hanya boleh URL internal | ✅ | `AuthTest::testRedirectKeLuarAplikasiDiabaikan` |
| Unggah file | Logo: hanya PNG/JPG/WEBP ≤ 1 MB, nama file dibuat server, folder `public/uploads/` menolak eksekusi skrip. Impor CSV: ekstensi csv/txt ≤ 2 MB, maks. 1.000 baris | ✅ | `SettingController`, `public/uploads/.htaccess` |
| Ekspor CSV | Nilai yang diawali `= + - @` diberi awalan `'` agar tidak dieksekusi sebagai formula di Excel | ✅ | `LeadTest::testEksporMencegahFormulaInjection` |
| Header keamanan | `X-Frame-Options`, `X-Content-Type-Options`, dsb. lewat filter `secureheaders` | ✅ | `SecurityTest::testHeaderKeamananDikirim` |
| Session & cookie | `HttpOnly`, `SameSite=Lax`; `Secure` + paksa HTTPS diaktifkan lewat `.env` produksi | ⚠️ konfigurasi saat deploy | `docs/DEPLOY.md` §4 |
| Rahasia | `.env` tidak masuk repository; production mematikan Debug Toolbar & detail error | ✅ | `.gitignore`, `Config\Boot\production.php` |
| Log audit | Login sukses/gagal/terkunci, create/update/delete, pindah tahap, alih owner, reset password tercatat; password tidak pernah disimpan di log | ✅ | `AuditLogger` |

## Catatan dan risiko yang diterima

1. **`'unsafe-eval'` di CSP** — wajib untuk Alpine.js (ekspresi `x-data`/`@click` dievaluasi dengan `Function`). Risiko dikurangi karena seluruh skrip inline tetap wajib nonce dan tidak ada data pengguna yang disisipkan ke ekspresi Alpine tanpa `esc()`. Alternatif ke depan: build CSP Alpine (`@alpinejs/csp`) dengan konsekuensi menulis ulang ekspresi menjadi komponen terdaftar.
2. **Style `'unsafe-inline'`** — Tailwind Play CDN menyuntik `<style>` saat runtime. Di development CSP hanya *report-only* karena Kint/Debug Toolbar menambahkan nonce style yang bentrok; di production kebijakan ditegakkan penuh.
3. **Ketergantungan CDN** — versi semua skrip dikunci (`tailwindcss 3.4.17`, `alpinejs 3.17.5`, `sortablejs 1.15.7`, `chart.js 4.6.1`). Fallback ke file lokal tercatat sebagai backlog B.04.
4. **Lupa password** — sesuai PRD tidak ada reset mandiri; admin melakukan reset satu klik. Disarankan minimal 2 admin aktif.
