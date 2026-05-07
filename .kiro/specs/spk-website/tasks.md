# Implementation Plan: SPK Website (Sistem Pendukung Keputusan)

## Overview

Implementasi website SPK berbasis PHP + MySQL di backend dan HTML5/CSS/JavaScript/Tailwind CSS di frontend. Pendekatan incremental: mulai dari fondasi database dan autentikasi, lalu manajemen data, engine perhitungan SAW, tampilan hasil, hingga fitur keamanan dan pengaturan sistem.

## Tasks

- [x] 1. Setup struktur proyek dan database
  - Buat struktur direktori proyek: `public/`, `src/`, `api/`, `assets/`, `config/`
  - Buat file konfigurasi koneksi database `config/database.php` dengan PDO
  - Buat skema database MySQL: tabel `users`, `kriteria`, `alternatif`, `nilai_alternatif`, `hasil_perhitungan`, `riwayat_perhitungan`, `pengaturan`
  - Buat file migrasi SQL `database/schema.sql` yang dapat dijalankan ulang
  - Setup Tailwind CSS via CDN atau build tool di layout utama
  - _Requirements: 1.6, 4.4, 6.1_

- [x] 2. Implementasi autentikasi pengguna
  - [x] 2.1 Buat halaman login (`public/login.php`)
    - Form login dengan field username dan password
    - Styling responsif menggunakan Tailwind CSS
    - _Requirements: 1.1, 1.2_

  - [x] 2.2 Buat backend autentikasi (`api/auth.php`)
    - Verifikasi kredensial menggunakan `password_verify()` dengan bcrypt
    - Buat sesi PHP dengan timeout 60 menit tidak aktif
    - Kembalikan respons JSON untuk sukses/gagal
    - _Requirements: 1.1, 1.2, 1.3, 1.6_

  - [x] 2.3 Buat middleware session guard (`src/middleware/auth_guard.php`)
    - Cek validitas sesi di setiap halaman yang dilindungi
    - Redirect ke halaman login jika sesi tidak valid
    - Kembalikan HTTP 401 untuk request API tanpa sesi valid
    - _Requirements: 1.3, 1.5, 7.4_

  - [x] 2.4 Implementasi logout (`api/logout.php`)
    - Hapus sesi dan redirect ke halaman login
    - _Requirements: 1.4_

  - [x] 2.5 Tulis unit test untuk fungsi autentikasi
    - Test verifikasi password hash bcrypt
    - Test pembuatan dan penghapusan sesi
    - Test redirect untuk pengguna tidak terautentikasi
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5_

- [x] 3. Buat layout utama dan navigasi
  - [x] 3.1 Buat template layout utama (`src/layout/main.php`)
    - Header dengan nama sistem, nama organisasi, dan logo (dari pengaturan)
    - Sidebar navigasi yang dapat diakses dari seluruh halaman
    - Area konten utama
    - Responsif untuk layar minimal 320px menggunakan Tailwind CSS
    - _Requirements: 6.2, 6.4, 6.5_

  - [x] 3.2 Buat halaman Dashboard (`public/dashboard.php`)
    - Tampilkan kartu ringkasan: jumlah kriteria aktif, jumlah alternatif, tanggal perhitungan terakhir
    - Fetch data dari API menggunakan JavaScript (Fetch API)
    - _Requirements: 6.1, 6.3_

  - [x] 3.3 Buat API endpoint dashboard (`api/dashboard.php`)
    - Query ringkasan data dari database
    - Kembalikan JSON dalam waktu < 2 detik
    - _Requirements: 6.3_

- [x] 4. Checkpoint — Pastikan semua test lulus
  - Pastikan login, logout, session guard, dan dashboard berjalan dengan benar. Tanyakan kepada user jika ada pertanyaan.

- [x] 5. Implementasi manajemen kriteria
  - [x] 5.1 Buat API CRUD kriteria (`api/kriteria.php`)
    - GET: ambil semua kriteria
    - POST: tambah kriteria baru (nama, bobot 0.01–1.00, tipe Benefit/Cost)
    - PUT: update kriteria
    - DELETE: hapus kriteria beserta nilai alternatif terkait (cascade)
    - Validasi nama unik, tipe data, dan rentang bobot
    - Gunakan prepared statements PDO
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.6, 2.7, 7.1, 7.3_

  - [x] 5.2 Buat halaman manajemen kriteria (`public/kriteria.php`)
    - Tabel daftar kriteria yang dapat diurutkan (nama, bobot, tipe)
    - Form tambah/edit kriteria dengan validasi client-side
    - Tombol hapus dengan konfirmasi
    - Tampilkan pesan error untuk nama duplikat
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.6, 2.7_

  - [x] 5.3 Implementasi validasi total bobot
    - Hitung total bobot saat proses perhitungan dijalankan
    - Tampilkan peringatan "Total bobot kriteria harus sama dengan 1.00" jika tidak valid
    - _Requirements: 2.5_

  - [x] 5.4 Tulis unit test untuk API kriteria
    - Test tambah, update, hapus kriteria
    - Test validasi nama duplikat
    - Test validasi rentang bobot
    - _Requirements: 2.1, 2.2, 2.3, 2.4, 2.7_

- [x] 6. Implementasi manajemen alternatif
  - [x] 6.1 Buat API CRUD alternatif (`api/alternatif.php`)
    - GET: ambil semua alternatif beserta nilai kriterianya
    - POST: tambah alternatif baru dengan nilai numerik per kriteria
    - PUT: update alternatif dan nilai kriterianya
    - DELETE: hapus alternatif beserta seluruh nilai kriterianya (cascade)
    - Validasi nama unik dan nilai numerik
    - Gunakan prepared statements PDO
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 7.1, 7.3_

  - [x] 6.2 Buat halaman manajemen alternatif (`public/alternatif.php`)
    - Tabel daftar alternatif beserta nilai setiap kriteria
    - Form tambah/edit alternatif dengan field dinamis per kriteria
    - Nonaktifkan form jika belum ada kriteria, tampilkan pesan "Tambahkan kriteria terlebih dahulu"
    - Validasi client-side untuk nilai numerik
    - _Requirements: 3.1, 3.2, 3.3, 3.4, 3.5, 3.6, 3.7, 3.8_

  - [x] 6.3 Tulis unit test untuk API alternatif
    - Test tambah, update, hapus alternatif
    - Test validasi nama duplikat
    - Test validasi nilai non-numerik
    - Test kondisi tanpa kriteria
    - _Requirements: 3.1, 3.5, 3.7, 3.8_

- [x] 7. Checkpoint — Pastikan semua test lulus
  - Pastikan CRUD kriteria dan alternatif berjalan dengan benar, data tersimpan ke database. Tanyakan kepada user jika ada pertanyaan.

- [x] 8. Implementasi SPK Engine (Algoritma Moora)
  - [x] 8.1 Buat kelas SPK_Engine (`src/engine/SPKEngine.php`)
    - Method `normalize(array $values, string $type): array` — normalisasi nilai per kriteria
      - Benefit: `nilai / max(nilai)`
      - Cost: `min(nilai) / nilai`
    - Method `calculatePreference(array $normalizedMatrix, array $weights): array` — hitung nilai preferensi
    - Method `rank(array $preferences): array` — urutkan dari tertinggi ke terendah
    - _Requirements: 4.1, 4.2, 4.3_

  - [x] 8.2 Tulis unit test untuk normalisasi Moora
    - Test normalisasi tipe Benefit: nilai/max
    - Test normalisasi tipe Cost: min/nilai
    - Test dengan nilai edge case (semua nilai sama, satu nilai nol)
    - _Requirements: 4.1_

  - [x] 8.3 Tulis unit test untuk perhitungan preferensi dan ranking
    - Test perhitungan nilai preferensi dengan bobot diketahui
    - Test pengurutan ranking dari tertinggi ke terendah
    - Test dengan 100 alternatif dan 20 kriteria (performa < 3 detik)
    - _Requirements: 4.2, 4.3, 4.6_

  - [x] 8.4 Buat API endpoint perhitungan (`api/hitung.php`)
    - Validasi: cek ketersediaan kriteria dan alternatif
    - Validasi total bobot = 1.00
    - Panggil SPK_Engine untuk proses perhitungan
    - Simpan hasil (normalisasi, preferensi, ranking) ke database dengan timestamp
    - Kembalikan hasil sebagai JSON
    - _Requirements: 4.4, 4.5, 4.6, 4.7, 2.5_

- [x] 9. Implementasi tampilan hasil dan laporan
  - [x] 9.1 Buat halaman hasil perhitungan (`public/hasil.php`)
    - Tabel matriks normalisasi (alternatif × kriteria)
    - Tabel ranking: nomor urut, nama alternatif, nilai preferensi, posisi ranking
    - Sorot baris alternatif dengan nilai preferensi tertinggi sebagai rekomendasi utama
    - _Requirements: 5.1, 5.2, 5.5_

  - [x] 9.2 Implementasi grafik batang dengan Chart.js
    - Integrasikan Chart.js via CDN
    - Render bar chart nilai preferensi setiap alternatif
    - _Requirements: 5.4_

  - [x] 9.3 Implementasi unduh laporan PDF
    - Integrasikan library PDF (misal: TCPDF atau mPDF) di PHP
    - Buat endpoint `api/laporan.php` yang menghasilkan PDF berisi: ringkasan kriteria, data alternatif, tabel normalisasi, tabel ranking
    - Tombol "Unduh Laporan" di halaman hasil
    - _Requirements: 5.3_

  - [x] 9.4 Buat halaman riwayat perhitungan (`public/riwayat.php`)
    - Tampilkan daftar riwayat perhitungan diurutkan berdasarkan timestamp terbaru
    - Link untuk melihat detail hasil perhitungan lama
    - _Requirements: 5.6_

  - [x] 9.5 Tulis unit test untuk SPK Engine end-to-end
    - Test alur lengkap: input data → normalisasi → preferensi → ranking
    - Verifikasi hasil ranking konsisten dengan perhitungan manual
    - _Requirements: 4.1, 4.2, 4.3_

- [x] 10. Checkpoint — Pastikan semua test lulus
  - Pastikan perhitungan Moora menghasilkan hasil yang benar, grafik tampil, dan PDF dapat diunduh. Tanyakan kepada user jika ada pertanyaan.

- [x] 11. Implementasi keamanan dan validasi input
  - [x] 11.1 Implementasi CSRF token
    - Buat helper `src/helpers/csrf.php` untuk generate dan validasi token CSRF
    - Sisipkan token CSRF pada seluruh form yang melakukan operasi tulis (tambah, ubah, hapus)
    - Validasi token di setiap API endpoint yang menerima POST/PUT/DELETE
    - _Requirements: 7.5_

  - [x] 11.2 Implementasi sanitasi input dan proteksi XSS
    - Buat helper `src/helpers/sanitize.php` untuk sanitasi input menggunakan `htmlspecialchars()` dan `filter_var()`
    - Terapkan sanitasi di seluruh endpoint API sebelum data diproses atau disimpan
    - _Requirements: 7.2_

  - [x] 11.3 Implementasi validasi payload dan rate limiting
    - Batasi ukuran payload request maksimal 1MB di konfigurasi PHP atau middleware
    - Validasi tipe data dan rentang nilai di seluruh endpoint API
    - _Requirements: 7.3, 7.6_

  - [x] 11.4 Tulis unit test untuk keamanan
    - Test CSRF token: generate, validasi, dan penolakan token tidak valid
    - Test sanitasi XSS: input berbahaya harus di-escape
    - Test SQL injection: prepared statements harus mencegah injeksi
    - _Requirements: 7.1, 7.2, 7.5_

- [x] 12. Implementasi pengaturan sistem
  - [x] 12.1 Buat API pengaturan (`api/pengaturan.php`)
    - GET: ambil pengaturan saat ini (nama sistem, nama organisasi, logo)
    - POST: simpan pengaturan baru ke database
    - Validasi format file logo (PNG, JPG, SVG) dan ukuran maksimal 2MB
    - _Requirements: 8.1, 8.2, 8.4, 8.5_

  - [x] 12.2 Buat halaman pengaturan (`public/pengaturan.php`)
    - Form untuk mengubah nama sistem, nama organisasi, dan upload logo
    - Tampilkan pesan error untuk format/ukuran file tidak valid
    - Perubahan diterapkan ke seluruh halaman tanpa restart server
    - _Requirements: 8.1, 8.2, 8.4, 8.5_

  - [x] 12.3 Implementasi fitur reset data
    - Tombol "Reset Data" di halaman pengaturan
    - Konfirmasi dua langkah sebelum eksekusi (modal konfirmasi + input teks konfirmasi)
    - Hapus seluruh data alternatif dan hasil perhitungan dari database
    - _Requirements: 8.3_

  - [x] 12.4 Tulis unit test untuk pengaturan
    - Test simpan dan ambil pengaturan
    - Test validasi format dan ukuran file logo
    - Test reset data dengan konfirmasi dua langkah
    - _Requirements: 8.1, 8.2, 8.3, 8.4, 8.5_

- [ ] 13. Integrasi dan wiring komponen
  - [ ] 13.1 Hubungkan seluruh halaman dengan layout utama dan navigasi sidebar
    - Pastikan semua halaman menggunakan template layout yang sama
    - Navigasi sidebar aktif sesuai halaman yang sedang dibuka
    - _Requirements: 6.2, 6.5_

  - [ ] 13.2 Integrasikan pengaturan sistem ke seluruh halaman
    - Header menampilkan nama sistem, nama organisasi, dan logo dari database
    - Perubahan pengaturan langsung tercermin tanpa reload manual
    - _Requirements: 8.1, 8.2_

  - [ ] 13.3 Pastikan konsistensi skema warna dan komponen UI Tailwind CSS
    - Terapkan design system Tailwind yang konsisten di seluruh halaman
    - Uji responsivitas pada lebar layar 320px, 768px, dan 1280px
    - _Requirements: 6.4, 6.5_

  - [ ] 13.4 Tulis integration test end-to-end
    - Test alur lengkap: login → tambah kriteria → tambah alternatif → hitung Moora → lihat hasil → unduh laporan → logout
    - _Requirements: 1.1, 2.2, 3.2, 4.7, 5.3_

- [ ] 14. Final Checkpoint — Pastikan semua test lulus
  - Pastikan seluruh fitur terintegrasi dengan benar, semua test lulus, dan tidak ada kode yang terisolasi. Tanyakan kepada user jika ada pertanyaan.

## Notes

- Task bertanda `*` bersifat opsional dan dapat dilewati untuk MVP yang lebih cepat
- Setiap task mereferensikan requirement spesifik untuk keterlacakan
- Gunakan prepared statements PDO di seluruh query database (wajib, bukan opsional)
- Seluruh API endpoint harus memvalidasi sesi sebelum memproses request
- Tailwind CSS digunakan untuk seluruh styling — hindari CSS custom kecuali benar-benar diperlukan
- Chart.js dan library PDF (TCPDF/mPDF) diintegrasikan via Composer atau CDN
