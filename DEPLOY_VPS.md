# Panduan Deploy ke VPS — Dashboard Toko Sabrina

Paket ini sudah dibersihkan & dilengkapi supaya orang yang deploy tidak perlu
mengedit kode apa pun. Ikuti langkah berikut di VPS (asumsi Ubuntu/Debian).

## 1. Pasang paket dasar di VPS

```bash
sudo apt update
sudo apt install -y nginx php php-fpm php-sqlite3 php-zip php-xml php-mbstring \
                     python3 python3-venv python3-pip unzip
```

Kalau pakai Apache, ganti `nginx` dengan `apache2` dan pastikan modul
`mod_rewrite` + `AllowOverride All` aktif (supaya `.htaccess` di paket ini
terbaca).

## 2. Upload & ekstrak

```bash
sudo mkdir -p /var/www/toko_sabrina
cd /var/www/toko_sabrina
sudo unzip toko_sabrina.zip -d .
```

## 3. Jalankan skrip setup otomatis (SEKALI SAJA)

```bash
cd /var/www/toko_sabrina
chmod +x setup_vps.sh
sudo ./setup_vps.sh www-data
```

Skrip ini otomatis:
- Mengecek PHP & ekstensi wajib (`pdo_sqlite`, `zip`, `simplexml`)
- Membuat **virtual environment Python** di `python/venv` & memasang
  `pandas`, `statsmodels`, `xgboost`, `scikit-learn` (versi terkini, terpisah
  dari python sistem supaya tidak bentrok dengan aplikasi lain di VPS)
- Menulis `python/python_bin.txt` agar mengarah ke venv tsb (path yang **benar
  benar valid di server ini**, bukan sisa path Windows dari komputer lama)
- Mengatur kepemilikan (`chown`) & izin folder ke user web server
- Mengecek apakah `shell_exec()` diblokir di `php.ini`

## 4. Konfigurasi web server

**Kalau pakai Nginx** (wajib, karena Nginx tidak baca `.htaccess`):
Salin isi `nginx_contoh.conf` ke `/etc/nginx/sites-available/toko-sabrina`,
sesuaikan `server_name`, `root`, dan versi socket PHP-FPM, lalu:

```bash
sudo ln -s /etc/nginx/sites-available/toko-sabrina /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

**Kalau pakai Apache**: `.htaccess` yang sudah disertakan di root, `config/`,
`python/`, dan `models/` akan otomatis memblokir akses langsung ke
`database.db`, file `.pkl`, skrip Python, dan folder config — asal
`AllowOverride All` sudah aktif di virtual host Apache Anda.

## 5. Verifikasi

Buka `https://domain-anda/cek_sistem.php` — halaman diagnostik bawaan
aplikasi akan menunjukkan ✅/❌ untuk setiap komponen (PHP, ekstensi, Python,
paket Python, akun login). Perbaiki apa pun yang masih ❌ sebelum lanjut.

Lalu tes juga langsung dari browser bahwa file berikut **TIDAK BISA** diakses
(harus muncul 403/404, bukan isi filenya):
- `https://domain-anda/database.db`
- `https://domain-anda/config/koneksi_db.php`
- `https://domain-anda/models/model_xgboost.pkl`

## 6. Login & ganti password default

Buka `https://domain-anda/login.php`:
- `peneliti` / `peneliti2026`
- `pemilik` / `sabrina2026`

**Segera ganti kedua password ini** lewat menu "🔑 Ganti Sandi" karena
password default ini tertulis terbuka di dokumentasi (BACA_SAYA.txt) dan
tidak boleh dibiarkan aktif di server publik.

## 7. (Opsional tapi disarankan) Pasang HTTPS

```bash
sudo apt install -y certbot python3-certbot-nginx   # atau python3-certbot-apache
sudo certbot --nginx -d domain-anda.com             # atau --apache
```

Aplikasi sudah otomatis mengaktifkan cookie session `Secure` ketika HTTPS
terdeteksi, jadi tidak perlu ubah kode apa pun.

## 8. Backup rutin

Karena database-nya cuma satu file, backup sangat sederhana — jadwalkan cron
harian, contoh:

```bash
0 2 * * * cp /var/www/toko_sabrina/database.db /var/backups/toko_sabrina_$(date +\%F).db
```

---

### Ringkasan perubahan yang sudah dilakukan pada paket ini (dibanding zip asli)
- Menghapus `python/python_bin.txt` lama (isinya path Windows milik komputer
  developer — akan diganti otomatis oleh `setup_vps.sh` dengan path venv yang
  valid di VPS Anda). Tidak memengaruhi pemakaian lokal karena deteksi
  otomatis tetap jalan seperti biasa.
- Menghapus file debug/eksperimen (`temp_*.py`) dan `__pycache__` yang tidak
  dipakai aplikasi
- Menambahkan `.htaccess` (root, `config/`, `python/`, `models/`) untuk
  memblokir akses langsung ke file sensitif di Apache
- Menambahkan `nginx_contoh.conf` untuk proteksi setara di Nginx
- Menambahkan `python/requirements.txt` dan `setup_vps.sh` untuk instalasi
  sekali-jalan tanpa langkah manual di VPS

**Paket ini TETAP bisa dijalankan di komputer lokal seperti sebelumnya** —
`Jalankan_Toko_Sabrina.bat`, `Buka_Toko_Sabrina.vbs`, dan `Tutup_Aplikasi.bat`
tidak dihapus/diubah, dan tidak bergantung pada `python_bin.txt` (launcher
lokal ini hanya butuh PHP, bukan Python, untuk menyalakan server). Jadi satu
paket yang sama bisa dipakai untuk demo di laptop Windows (dobel klik
`Jalankan_Toko_Sabrina.bat`) maupun untuk deploy sungguhan di VPS
(`setup_vps.sh`) — tidak perlu dua versi zip terpisah.

## Menjalankan secara lokal (Windows, tanpa VPS)
1. Ekstrak folder ini di mana saja (tidak wajib di dalam `htdocs` XAMPP lagi,
   karena launcher sudah pakai server bawaan PHP sendiri).
2. Dobel klik `Jalankan_Toko_Sabrina.bat` — otomatis mencari `php.exe`
   (XAMPP atau PATH sistem), menyalakan server di `localhost:8000`, dan
   membuka browser dalam mode aplikasi.
3. Kalau PHP tidak ditemukan otomatis, buat file `php_bin.txt` di folder ini
   berisi 1 baris path lengkap ke `php.exe`, sesuai instruksi yang muncul di
   layar.
4. Tutup aplikasi lewat `Tutup_Aplikasi.bat` (mematikan server latar
   belakangnya).
5. Untuk fitur Latih Ulang & Prediksi tetap butuh Python + pustaka
   `pandas`, `statsmodels`, `xgboost`, `scikit-learn` terpasang di komputer
   tsb (lihat `BACA_SAYA.txt` untuk detail).
