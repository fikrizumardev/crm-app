# Skenario UAT — Sistem CRM v1.0

Skenario uji penerimaan pengguna (T5.12) per peran, berdasarkan PRD §4. Jalankan di server staging dengan data demo (`php spark db:seed DatabaseSeeder`).

**Cara mencatat:** isi kolom *Hasil* dengan ✅ (sesuai) atau ❌ (tidak sesuai). Setiap ❌ dicatat sebagai issue dengan langkah, hasil yang diharapkan, hasil aktual, dan screenshot. Temuan diberi label **blocking** bila menghalangi pekerjaan harian; go-live hanya bila tidak ada temuan blocking yang terbuka.

| Tanggal UAT | Penguji | Peran | Perangkat/browser |
| --- | --- | --- | --- |
| | | | |

## A. Sales (`andi.saputra` / `Sales#2026`)

| # | Skenario | Hasil yang diharapkan | Hasil |
| --- | --- | --- | --- |
| A1 | Login dengan username, lalu logout | Masuk ke dashboard berisi angka milik sendiri; logout kembali ke halaman login | |
| A2 | Login dengan password salah 5 kali | Pesan "Username atau password salah", lalu dikunci 15 menit | |
| A3 | Tambah lead baru dengan no. HP yang sudah dipakai kontak lain | Muncul peringatan duplikat; bisa "Tetap simpan" | |
| A4 | Cari lead berdasarkan nama/no. HP dan filter status | Hanya lead milik sendiri yang muncul | |
| A5 | Konversi lead "Kualifikasi" menjadi perusahaan + kontak + deal | Ketiganya terbentuk dan saling terhubung; status lead "Dikonversi" | |
| A6 | Buka detail kontak, klik "Chat WhatsApp" dan "Telepon" | WhatsApp terbuka ke nomor 62…; aplikasi telepon terbuka | |
| A7 | Catat aktivitas meeting di detail deal | Muncul di timeline deal dan di tab Aktivitas perusahaan | |
| A8 | Buat tugas follow-up jatuh tempo besok, lalu tandai selesai | Tugas muncul di tab "Minggu ini", lalu pindah ke "Selesai" | |
| A9 | Geser deal di papan Pipeline dari Proposal ke Negosiasi | Kartu pindah, total kolom berubah tanpa reload, riwayat tahap tercatat | |
| A10 | Geser deal ke Lost | Diminta memilih alasan kalah; tanpa alasan, deal tidak pindah | |
| A11 | Buat penawaran dari deal dengan 3 item dan diskon | Total di layar sama dengan total setelah disimpan; nomor `QUO/tahun/bulan/urut` | |
| A12 | Cetak penawaran dan simpan sebagai PDF | Halaman cetak rapi berisi logo, item, total, terbilang, masa berlaku | |
| A13 | Coba buka alamat deal milik sales lain (ganti ID di URL) | Muncul "Halaman tidak ditemukan" | |
| A14 | Ganti password dari menu profil | Password baru berlaku pada login berikutnya | |
| A15 | Buka aplikasi di HP (lebar ±375 px) | Menu berubah jadi tombol ☰; daftar bisa digeser ke samping | |

## B. Manager (`dewi.lestari` / `Manager#2026`)

| # | Skenario | Hasil yang diharapkan | Hasil |
| --- | --- | --- | --- |
| B1 | Buka dashboard, pilih filter per sales | Angka berubah sesuai sales terpilih; hanya anggota tim sendiri yang bisa dipilih | |
| B2 | Buka daftar lead/deal | Berisi data seluruh tim (Andi, Siti, Budi), tidak ada data tim Hendra | |
| B3 | Alihkan 3 lead sekaligus dari Andi ke Siti | Owner berubah; Siti menerima notifikasi lonceng | |
| B4 | Beri tugas ke Budi dari halaman Tugas | Budi menerima notifikasi tugas baru | |
| B5 | Buka Laporan → Kinerja Sales, Win/Loss, Sumber Lead, Forecast | Angka sesuai data tim; ekspor CSV terbuka rapi di Excel | |
| B6 | Ekspor lead ke CSV dengan filter status | File hanya berisi lead sesuai filter | |
| B7 | Hapus deal Won milik anggota tim | Berhasil (sales tidak bisa menghapus deal Won) | |
| B8 | Coba buka menu Administrasi via URL `/admin/users` | Akses ditolak (403) | |

## C. Admin (`admin` / `Admin#2026`)

| # | Skenario | Hasil yang diharapkan | Hasil |
| --- | --- | --- | --- |
| C1 | Buat pengguna sales baru tanpa mengisi password | Password otomatis ditampilkan sekali; login pertama wajib ganti password | |
| C2 | Reset password pengguna | Password baru tampil sekali; password lama tidak berlaku | |
| C3 | Nonaktifkan pengguna yang sedang login | Pengguna langsung keluar pada klik berikutnya dan tidak bisa login | |
| C4 | Coba nonaktifkan akun sendiri | Ditolak dengan pesan yang jelas | |
| C5 | Ubah pengaturan: nama perusahaan, logo, PPN 12% | Logo tampil di sidebar & cetak penawaran; penawaran baru memakai PPN 12% | |
| C6 | Tambah tahap pipeline "Demo", urutkan setelah Kualifikasi | Tahap baru muncul di Kanban sesuai urutan | |
| C7 | Coba hapus sumber lead yang masih dipakai | Ditolak dengan pesan "masih dipakai" | |
| C8 | Tambah produk baru, lalu nonaktifkan | Produk nonaktif tidak muncul di form penawaran | |
| C9 | Buka Log Audit, filter entitas "deal" | Terlihat siapa mengubah apa, dengan nilai sebelum & sesudah | |

## Ringkasan temuan

| # | Skenario | Deskripsi | Blocking? | Status |
| --- | --- | --- | --- | --- |
| | | | | |
