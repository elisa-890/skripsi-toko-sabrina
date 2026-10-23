# Dasbor Prediksi Penjualan Toko Sabrina

Aplikasi web (Progressive Web App) untuk mencatat transaksi penjualan toko pakan ternak
dan memprediksi kebutuhan pakan mingguan menggunakan model **GLM** dan **XGBoost**.

- **Nama:** (Elisa D Lumbantoruan)
- **NIM:** (22040128)
- **Program Studi:** Teknik Informatika
- **Judul Skripsi:** (ANALISIS PERBANDINGAN GENERALIZED LINEAR MODEL DAN XGBOOST UNTUK PREDIKSI TREN PENJUALAN PAKAN TERNAK PADA TOKO  SABRINA DI HUMBANG HASUNDUTAN )

## Fitur Utama
- Login dengan 2 peran: **Peneliti** dan **Pemilik**
- Input, edit, dan riwayat transaksi penjualan
- Kelola produk dan laporan penjualan (unduh laporan)
- Impor data transaksi dari Excel (.xlsx)
- Latih ulang model GLM & XGBoost, lengkap dengan evaluasi MAE / RMSE / MAPE
- Prediksi penjualan minggu depan dan tren penjualan
- Cetak nota

## Teknologi
- PHP 8.0+ (ekstensi `pdo_sqlite`, `zip`, `SimpleXML`)
- SQLite (file `database.db`, tanpa perlu MySQL)
- Python 3.9+ (`pandas`, `statsmodels`, `xgboost`, `scikit-learn`)
- HTML, CSS, JavaScript

## Cara Instalasi (XAMPP / Windows)
1. Install **XAMPP** dan **Python 3.9+**.
2. Salin folder project ini ke `C:\xampp\htdocs\toko_sabrina\`.
3. Install pustaka Python:
   ```
   pip install -r python/requirements.txt
   ```
4. Jalankan **Apache** di XAMPP (MySQL tidak perlu dinyalakan).
5. Buka `http://localhost/toko_sabrina/login.php` di browser.
6. Jika Python tidak terdeteksi otomatis, buat file `python/python_bin.txt` berisi satu baris
   path lengkap ke `python.exe`, misalnya:
   ```
   C:\Users\NamaAnda\AppData\Local\Programs\Python\Python312\python.exe
   ```

## Database
Database memakai **SQLite**: file `database.db` sudah disertakan di repository ini.
Struktur tabel (untuk dokumentasi) ada di `skema_database.sql`. Jika `database.db` dihapus,
database kosong akan dibuat otomatis saat aplikasi dibuka kembali.

## Akun Login Bawaan
| Peran    | Nama pengguna | Kata sandi     |
|----------|---------------|----------------|
| Peneliti | `peneliti`    | `peneliti2026` |
| Pemilik  | `pemilik`     | `sabrina2026`  |

Kata sandi bisa diganti lewat menu **Ganti Sandi**.

## Struktur Folder
| Folder / File | Keterangan |
|---------------|------------|
| `halaman/`    | Tampilan setiap menu |
| `php/`        | Proses backend (login, simpan transaksi, impor, laporan, dll.) |
| `python/`     | Skrip pelatihan model dan prediksi |
| `config/`     | Koneksi database dan pengaturan aplikasi |
| `models/`     | Model terlatih (`.pkl`) |
| `assets/`     | CSS, JavaScript, ikon, dan template Excel |
| `database.db` | Database SQLite |

Dokumentasi lengkap ada di `BACA_SAYA.txt`, dan panduan deploy ke server (VPS) ada di `DEPLOY_VPS.md`.
