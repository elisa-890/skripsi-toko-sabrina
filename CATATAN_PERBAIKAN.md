# Catatan Perbaikan — Dashboard Toko Sabrina

Dokumen ini merangkum semua yang diperiksa dan diperbaiki pada sistem, plus panduan instalasi & troubleshooting supaya tidak error lagi.

---

## 1. Ringkasan Sistem

- **Jenis aplikasi:** Web PHP (Progressive Web App) untuk toko pakan ternak.
- **Database:** SQLite, satu file portable `database.db` di root folder. **Bukan MySQL** — tidak butuh phpMyAdmin sama sekali.
- **Fitur utama:** input transaksi penjualan, kelola produk, impor data Excel, latih model prediksi (GLM & XGBoost via Python), lihat hasil prediksi, laporan.
- **Dua peran akun:**
  | Akun | Kata sandi | Akses |
  |---|---|---|
  | `pemilik` | `sabrina2026` | Input transaksi, Kelola Produk, Riwayat, Laporan, Hasil Prediksi (baca saja) |
  | `peneliti` | `peneliti2026` | Impor Data, Kelola Data, Latih Ulang Model, Hasil Prediksi (bisa jalankan prediksi baru), Struktur Database |

  Catatan: tidak ada akun "admin" — keduanya sengaja dipisah menunya, tidak ada satu akun yang mengakses semuanya.

---

## 2. Bug yang Ditemukan & Diperbaiki

Semua perbaikan berikut sudah diuji langsung (server PHP dijalankan sungguhan, login, isi transaksi, jalankan skrip Python) — bukan cuma dibaca kodenya.

### 2.1 MAPE bisa meledak tidak wajar akibat tanggal transaksi salah ketik
**Gejala:** Nilai MAPE di menu Latih Ulang bisa menunjukkan angka seperti `249717406289235443712.0000%`, padahal wajarnya di bawah 100%.

**Penyebab:** Transaksi dengan tanggal jauh ke depan (misal salah ketik tahun) membuat data agregat mingguan punya banyak "minggu kosong" (penjualan = 0). Rumus MAPE resmi (mengikuti metodologi skripsi) membagi dengan angka mendekati nol pada minggu kosong ini, menghasilkan angka raksasa.

**Perbaikan:**
- `php/simpan_transaksi.php` — menolak tanggal transaksi lebih dari 7 hari ke depan atau lebih dari 15 tahun ke belakang dari hari ini, dengan pesan error yang jelas ke pengguna.
- `php/edit_transaksi_proses.php` — validasi yang sama diterapkan saat mengedit transaksi.
- `php/proses_impor_excel.php` — baris Excel dengan tanggal jauh ke masa depan otomatis dilewati (dicatat sebagai baris gagal, bukan ikut mencemari data).

**Catatan penting:** Rumus MAPE resmi di `python/latih_model.py` sengaja **tidak diubah**, karena dibuat identik dengan notebook Colab agar angkanya tetap cocok dengan yang dilaporkan di skripsi Bab III/IV.

### 2.2 `cek_sistem.php` salah mendeteksi Python sebagai "terpasang" padahal belum
**Gejala:** Baris "Python bisa dijalankan" tertanda ✅ hijau, tapi keterangannya berbunyi *"Python was not found; run without arguments to install from the Microsoft Store..."* — jelas kontradiksi.

**Penyebab:** Di Windows, kalau Python belum terpasang tapi ada alias bawaan Microsoft Store, mengetik `python` di terminal memunculkan pesan penawaran instal yang **kebetulan mengandung kata "Python"**. Kode lama hanya mengecek apakah kata "Python" ada di output — jadi salah kira itu tanda sukses.

**Perbaikan:** `cek_sistem.php` sekarang memeriksa pola versi asli (`Python 3.x.x`) DAN memastikan outputnya bukan pesan galat/alias Microsoft Store, sebelum menganggap Python berhasil dijalankan. Solusi yang ditampilkan juga disesuaikan: kalau terdeteksi kasus alias Microsoft Store, pengguna diarahkan untuk instal Python asli dari python.org.

### 2.3 Cek menyeluruh lainnya (tidak ditemukan masalah)
- Semua file `.php` lolos cek sintaks (`php -l`).
- Semua halaman utama (Beranda, Input Transaksi, Kelola Produk, Riwayat, Laporan, Impor Data, Kelola Data, Latih Ulang, Hasil Prediksi, Ganti Sandi, Struktur Database) dibuka & diuji di kedua peran — tidak ada error PHP tersembunyi.
- Alur login, simpan transaksi, dan skrip `latih_model.py` diuji jalan dari awal sampai akhir dengan hasil MAPE wajar (27–32%) setelah data uji coba dibersihkan.
- File `database.db` bawaan valid dan sehat — berisi 2256 transaksi asli, tidak perlu dibuat ulang.

---

## 3. Cara Instalasi di XAMPP (Windows)

**Poin paling penting: JANGAN impor apapun ke phpMyAdmin.** Aplikasi ini tidak memakai MySQL sama sekali.

1. Ekstrak folder `toko_sabrina` ke dalam `C:\xampp\htdocs\toko_sabrina\`. Pastikan file `database.db` ikut ter-copy di dalamnya.
2. Buka **XAMPP Control Panel**, nyalakan **Apache** saja (modul MySQL tidak perlu dinyalakan).
3. Buka `php.ini` (biasanya `C:\xampp\php\php.ini`), pastikan baris berikut **tidak** diberi tanda `;` di depannya (aktif):
   ```
   extension=pdo_sqlite
   extension=zip
   extension=simplexml
   ```
   Simpan, lalu restart Apache dari Control Panel.
4. Install Python asli dari **python.org/downloads** (JANGAN dari Microsoft Store). Saat instalasi, **wajib centang "Add python.exe to PATH"**.
5. Buka Command Prompt baru, jalankan:
   ```
   pip install pandas statsmodels xgboost scikit-learn
   ```
6. Buka browser ke `http://localhost/toko_sabrina/login.php` — database otomatis terbaca, tidak perlu proses impor manual apapun.
7. Login pakai salah satu akun di atas.

---

## 4. Diagnosa Mandiri

Kalau ada bagian yang masih terasa error, buka dulu halaman diagnostik bawaan:

```
http://localhost/toko_sabrina/cek_sistem.php
```

Halaman ini memeriksa: versi PHP, ekstensi `pdo_sqlite`/`zip`/`SimpleXML`, izin tulis folder, `shell_exec()`, Python & paketnya, serta akun login — lengkap dengan solusi di tiap baris yang gagal (❌).

## 5. Troubleshooting Cepat

| Gejala | Penyebab | Solusi |
|---|---|---|
| Error saat impor `skema_database.sql` ke phpMyAdmin (`#1064 ... near 'PRAGMA'`) | File itu sintaks SQLite, bukan MySQL, dan memang tidak untuk diimpor ke phpMyAdmin | Jangan impor sama sekali — cukup buka aplikasi lewat browser, database dibuat otomatis |
| Paket Python (pandas, xgboost, dst.) semua ❌ padahal sudah `pip install` | Python yang terdeteksi sebenarnya alias kosong Microsoft Store, bukan Python asli | Uninstall alias, install Python asli dari python.org dengan opsi "Add to PATH" dicentang |
| MAPE di Latih Ulang menampilkan angka aneh (triliunan persen) | Ada transaksi dengan tanggal salah ketik jauh ke depan/lampau | Sudah dicegah otomatis lewat validasi tanggal (lihat bagian 2.1) — cek juga Riwayat Transaksi untuk tanggal yang janggal |
| Menu Latih Ulang/Prediksi tidak jalan sama sekali | `shell_exec()` dimatikan di hosting/php.ini | Aktifkan `shell_exec` di php.ini, atau hubungi penyedia hosting (banyak shared hosting mematikan ini demi keamanan) |
