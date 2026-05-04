# Requirements Document

## Introduction

Website SPK (Sistem Pendukung Keputusan) adalah aplikasi berbasis web yang membantu pengguna dalam proses pengambilan keputusan secara terstruktur menggunakan algoritma multi-kriteria. Sistem ini mengimplementasikan metode SAW (Simple Additive Weighting) sebagai algoritma utama, dengan kemungkinan perluasan ke metode TOPSIS atau AHP di masa mendatang.

Sistem dibangun menggunakan HTML5, CSS, JavaScript, dan Tailwind CSS pada sisi frontend, serta PHP pada sisi backend. Pengguna dapat mendefinisikan kriteria, memasukkan data alternatif, menjalankan perhitungan, dan melihat hasil perankingan secara interaktif.

## Glossary

- **SPK**: Sistem Pendukung Keputusan — sistem berbasis web untuk membantu pengambilan keputusan menggunakan algoritma multi-kriteria.
- **Alternatif**: Pilihan atau kandidat yang akan dievaluasi dan dibandingkan dalam proses pengambilan keputusan.
- **Kriteria**: Parameter atau atribut yang digunakan untuk mengevaluasi setiap alternatif.
- **Bobot**: Nilai numerik (0–1) yang merepresentasikan tingkat kepentingan relatif suatu kriteria terhadap kriteria lainnya.
- **SAW**: Simple Additive Weighting — metode pengambilan keputusan multi-kriteria yang menghitung nilai preferensi dengan menjumlahkan perkalian bobot dan nilai ternormalisasi setiap kriteria.
- **Normalisasi**: Proses mengubah nilai kriteria ke dalam skala yang seragam (0–1) agar dapat dibandingkan secara adil.
- **Benefit**: Tipe kriteria di mana nilai yang lebih tinggi lebih diinginkan (contoh: nilai ujian, pendapatan).
- **Cost**: Tipe kriteria di mana nilai yang lebih rendah lebih diinginkan (contoh: biaya, jarak).
- **Ranking**: Urutan alternatif berdasarkan nilai preferensi akhir dari tertinggi ke terendah.
- **Admin**: Pengguna dengan hak akses penuh untuk mengelola data kriteria, alternatif, dan konfigurasi sistem.
- **User**: Pengguna umum yang dapat melihat hasil perhitungan dan menjalankan proses SPK.
- **Dashboard**: Halaman utama yang menampilkan ringkasan data dan navigasi sistem.
- **SPK_Engine**: Komponen backend PHP yang bertanggung jawab atas seluruh perhitungan algoritma SPK.
- **Database**: Penyimpanan data berbasis MySQL yang menyimpan kriteria, alternatif, bobot, dan hasil perhitungan.
- **API**: Antarmuka berbasis HTTP (PHP) yang melayani permintaan data dari frontend.

---

## Requirements

### Requirement 1: Autentikasi Pengguna

**User Story:** Sebagai Admin, saya ingin dapat login ke sistem, sehingga hanya pengguna yang berwenang yang dapat mengelola data SPK.

#### Acceptance Criteria

1. WHEN Admin mengakses halaman login dan memasukkan kredensial yang valid, THE SPK SHALL mengautentikasi Admin dan mengarahkan ke Dashboard.
2. IF Admin memasukkan kredensial yang tidak valid, THEN THE SPK SHALL menampilkan pesan kesalahan "Username atau password salah" tanpa mengungkap informasi sensitif.
3. WHEN Admin berhasil login, THE SPK SHALL membuat sesi yang kedaluwarsa setelah 60 menit tidak aktif.
4. WHEN Admin mengklik tombol logout, THE SPK SHALL menghapus sesi dan mengarahkan ke halaman login.
5. IF pengguna yang tidak terautentikasi mencoba mengakses halaman yang dilindungi, THEN THE SPK SHALL mengarahkan pengguna ke halaman login.
6. THE SPK SHALL menyimpan password Admin dalam bentuk hash menggunakan algoritma bcrypt sebelum disimpan ke Database.

---

### Requirement 2: Manajemen Kriteria

**User Story:** Sebagai Admin, saya ingin mendefinisikan kriteria beserta bobot dan tipenya, sehingga sistem dapat mengevaluasi alternatif secara akurat.

#### Acceptance Criteria

1. THE SPK SHALL menyediakan formulir untuk menambahkan kriteria dengan field: nama kriteria, bobot (0.01–1.00), dan tipe (Benefit atau Cost).
2. WHEN Admin menambahkan kriteria baru, THE SPK SHALL menyimpan data kriteria ke Database dan menampilkan kriteria dalam daftar.
3. WHEN Admin memperbarui data kriteria yang sudah ada, THE SPK SHALL memperbarui data di Database dan menampilkan perubahan secara langsung.
4. WHEN Admin menghapus kriteria, THE SPK SHALL menghapus kriteria beserta seluruh nilai alternatif yang terkait dari Database.
5. IF total bobot seluruh kriteria tidak sama dengan 1.00 saat proses perhitungan dijalankan, THEN THE SPK SHALL menampilkan peringatan "Total bobot kriteria harus sama dengan 1.00" dan menghentikan proses perhitungan.
6. THE SPK SHALL menampilkan daftar seluruh kriteria beserta bobot dan tipenya dalam bentuk tabel yang dapat diurutkan.
7. IF Admin mencoba menyimpan kriteria dengan nama yang sudah ada, THEN THE SPK SHALL menampilkan pesan kesalahan "Nama kriteria sudah digunakan".

---

### Requirement 3: Manajemen Alternatif

**User Story:** Sebagai Admin, saya ingin memasukkan data alternatif beserta nilai setiap kriterianya, sehingga sistem memiliki data yang cukup untuk proses perhitungan.

#### Acceptance Criteria

1. THE SPK SHALL menyediakan formulir untuk menambahkan alternatif dengan field: nama alternatif dan nilai numerik untuk setiap kriteria yang telah didefinisikan.
2. WHEN Admin menambahkan alternatif baru, THE SPK SHALL menyimpan data alternatif dan seluruh nilai kriterianya ke Database.
3. WHEN Admin memperbarui data alternatif, THE SPK SHALL memperbarui data di Database dan menampilkan perubahan secara langsung.
4. WHEN Admin menghapus alternatif, THE SPK SHALL menghapus alternatif beserta seluruh nilai kriterianya dari Database.
5. IF Admin memasukkan nilai non-numerik pada field nilai kriteria, THEN THE SPK SHALL menampilkan pesan kesalahan "Nilai kriteria harus berupa angka".
6. THE SPK SHALL menampilkan daftar seluruh alternatif beserta nilai setiap kriterianya dalam bentuk tabel.
7. IF Admin mencoba menyimpan alternatif dengan nama yang sudah ada, THEN THE SPK SHALL menampilkan pesan kesalahan "Nama alternatif sudah digunakan".
8. WHILE tidak ada kriteria yang terdefinisi, THE SPK SHALL menonaktifkan formulir penambahan alternatif dan menampilkan pesan "Tambahkan kriteria terlebih dahulu".

---

### Requirement 4: Perhitungan SAW (Simple Additive Weighting)

**User Story:** Sebagai Admin, saya ingin menjalankan perhitungan SAW, sehingga sistem dapat menghasilkan ranking alternatif berdasarkan kriteria dan bobot yang telah ditentukan.

#### Acceptance Criteria

1. WHEN Admin menjalankan proses perhitungan, THE SPK_Engine SHALL menormalisasi nilai setiap alternatif untuk setiap kriteria menggunakan rumus: nilai/max(nilai) untuk tipe Benefit, dan min(nilai)/nilai untuk tipe Cost.
2. WHEN normalisasi selesai, THE SPK_Engine SHALL menghitung nilai preferensi setiap alternatif dengan menjumlahkan perkalian nilai ternormalisasi dan bobot masing-masing kriteria.
3. WHEN perhitungan nilai preferensi selesai, THE SPK_Engine SHALL mengurutkan alternatif dari nilai preferensi tertinggi ke terendah untuk menghasilkan Ranking.
4. THE SPK_Engine SHALL menyimpan hasil perhitungan (nilai normalisasi, nilai preferensi, dan Ranking) ke Database dengan timestamp.
5. IF tidak ada alternatif atau kriteria yang terdefinisi saat perhitungan dijalankan, THEN THE SPK_Engine SHALL mengembalikan pesan kesalahan "Data tidak cukup untuk melakukan perhitungan".
6. THE SPK_Engine SHALL menyelesaikan proses perhitungan untuk hingga 100 alternatif dan 20 kriteria dalam waktu kurang dari 3 detik.
7. WHEN perhitungan selesai, THE SPK SHALL menampilkan tabel normalisasi dan tabel hasil Ranking kepada Admin.

---

### Requirement 5: Tampilan Hasil dan Laporan

**User Story:** Sebagai User, saya ingin melihat hasil perhitungan SPK secara visual dan dapat mengunduh laporan, sehingga saya dapat memahami dan mendokumentasikan hasil keputusan.

#### Acceptance Criteria

1. THE SPK SHALL menampilkan hasil Ranking dalam bentuk tabel yang memuat: nomor urut, nama alternatif, nilai preferensi, dan posisi Ranking.
2. THE SPK SHALL menampilkan tabel matriks normalisasi yang memuat nilai ternormalisasi setiap alternatif untuk setiap kriteria.
3. WHEN User mengklik tombol "Unduh Laporan", THE SPK SHALL menghasilkan dan mengunduh file PDF yang memuat: ringkasan kriteria, data alternatif, tabel normalisasi, dan tabel Ranking.
4. THE SPK SHALL menampilkan grafik batang yang memvisualisasikan nilai preferensi setiap alternatif menggunakan library Chart.js.
5. WHEN hasil perhitungan tersedia, THE SPK SHALL menampilkan alternatif dengan nilai preferensi tertinggi sebagai rekomendasi utama yang disorot secara visual.
6. THE SPK SHALL menampilkan riwayat perhitungan sebelumnya yang dapat diakses oleh Admin, diurutkan berdasarkan timestamp terbaru.

---

### Requirement 6: Dashboard dan Navigasi

**User Story:** Sebagai Admin, saya ingin memiliki Dashboard yang informatif, sehingga saya dapat memantau status data dan mengakses fitur sistem dengan mudah.

#### Acceptance Criteria

1. THE SPK SHALL menampilkan Dashboard yang memuat: jumlah kriteria aktif, jumlah alternatif, dan tanggal perhitungan terakhir.
2. THE SPK SHALL menyediakan navigasi sidebar yang dapat diakses dari seluruh halaman sistem.
3. WHEN Admin mengakses Dashboard, THE SPK SHALL memuat data ringkasan dari Database dan menampilkannya dalam waktu kurang dari 2 detik.
4. THE SPK SHALL menampilkan antarmuka yang responsif dan dapat digunakan pada perangkat dengan lebar layar minimal 320px menggunakan Tailwind CSS.
5. THE SPK SHALL menggunakan skema warna yang konsisten dan komponen UI yang seragam di seluruh halaman menggunakan Tailwind CSS.

---

### Requirement 7: Keamanan dan Validasi Input

**User Story:** Sebagai Admin, saya ingin sistem terlindungi dari serangan umum, sehingga data dan integritas sistem tetap terjaga.

#### Acceptance Criteria

1. THE API SHALL menggunakan prepared statements untuk seluruh query Database guna mencegah SQL Injection.
2. THE SPK SHALL melakukan sanitasi seluruh input pengguna sebelum diproses atau disimpan ke Database untuk mencegah XSS (Cross-Site Scripting).
3. THE API SHALL memvalidasi tipe data dan rentang nilai seluruh input sebelum memproses permintaan.
4. IF permintaan API diterima tanpa sesi yang valid, THEN THE API SHALL mengembalikan respons HTTP 401 Unauthorized.
5. THE SPK SHALL menggunakan token CSRF pada seluruh formulir yang melakukan operasi tulis (tambah, ubah, hapus).
6. THE API SHALL membatasi ukuran payload permintaan maksimal 1MB untuk mencegah serangan denial-of-service berbasis ukuran data.

---

### Requirement 8: Konfigurasi dan Pengaturan Sistem

**User Story:** Sebagai Admin, saya ingin dapat mengonfigurasi pengaturan dasar sistem, sehingga SPK dapat disesuaikan dengan kebutuhan spesifik organisasi.

#### Acceptance Criteria

1. THE SPK SHALL menyediakan halaman pengaturan untuk mengubah: nama sistem, nama organisasi, dan logo yang ditampilkan di header.
2. WHEN Admin menyimpan pengaturan baru, THE SPK SHALL memperbarui konfigurasi di Database dan menerapkan perubahan pada seluruh halaman tanpa perlu restart server.
3. THE SPK SHALL menyediakan fitur reset data untuk menghapus seluruh data alternatif dan hasil perhitungan, dengan konfirmasi dua langkah sebelum eksekusi.
4. IF Admin mengupload logo dengan format selain PNG, JPG, atau SVG, THEN THE SPK SHALL menampilkan pesan kesalahan "Format file tidak didukung. Gunakan PNG, JPG, atau SVG".
5. IF Admin mengupload logo dengan ukuran lebih dari 2MB, THEN THE SPK SHALL menampilkan pesan kesalahan "Ukuran file melebihi batas 2MB".
