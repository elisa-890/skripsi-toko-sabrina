#!/usr/bin/env bash
# =====================================================================
# setup_vps.sh
# -----------------------------------------------------------------
# Jalankan skrip ini SEKALI di VPS setelah folder aplikasi diupload,
# supaya izin folder & pustaka Python langsung siap tanpa kerja manual.
#
# Cara pakai (di VPS, dari dalam folder aplikasi ini):
#   chmod +x setup_vps.sh
#   sudo ./setup_vps.sh www-data       # ganti "www-data" kalau user web server Anda beda
#                                       # (Nginx+PHP-FPM Ubuntu/Debian biasanya www-data)
# =====================================================================
set -e

WEB_USER="${1:-www-data}"
APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo "== Dashboard Toko Sabrina — Setup VPS =="
echo "Folder aplikasi : $APP_DIR"
echo "User web server : $WEB_USER"
echo

# ---------------------------------------------------------------------
# 1) Cek PHP & ekstensi wajib
# ---------------------------------------------------------------------
echo "-> Mengecek PHP..."
if ! command -v php >/dev/null 2>&1; then
    echo "   [X] PHP belum terpasang. Install dulu, contoh (Ubuntu):"
    echo "       sudo apt install php php-fpm php-sqlite3 php-zip php-xml"
    exit 1
fi
php -v | head -n1

for ext in pdo_sqlite zip simplexml; do
    if php -m | grep -qi "^$ext$"; then
        echo "   [OK] Ekstensi $ext aktif"
    else
        echo "   [X] Ekstensi $ext BELUM aktif -> jalankan: sudo apt install php-$ext (lalu restart php-fpm)"
    fi
done
echo

# ---------------------------------------------------------------------
# 2) Cek Python & pasang pustaka via virtual environment
# ---------------------------------------------------------------------
echo "-> Menyiapkan Python..."
PYBIN="$(command -v python3 || true)"
if [ -z "$PYBIN" ]; then
    echo "   [X] python3 belum terpasang. Install dulu: sudo apt install python3 python3-venv python3-pip"
    exit 1
fi
echo "   Ditemukan: $($PYBIN --version)"

if [ ! -d "$APP_DIR/python/venv" ]; then
    echo "   Membuat virtual environment di python/venv ..."
    "$PYBIN" -m venv "$APP_DIR/python/venv"
fi

echo "   Memasang pustaka dari python/requirements.txt ..."
"$APP_DIR/python/venv/bin/pip" install --upgrade pip >/dev/null
"$APP_DIR/python/venv/bin/pip" install -r "$APP_DIR/python/requirements.txt"

# Arahkan aplikasi untuk memakai python venv ini (paling stabil di VPS,
# tidak tercampur dengan python sistem)
echo "$APP_DIR/python/venv/bin/python" > "$APP_DIR/python/python_bin.txt"
echo "   [OK] python/python_bin.txt diarahkan ke venv: $APP_DIR/python/venv/bin/python"
echo

# ---------------------------------------------------------------------
# 3) Set kepemilikan & izin folder
# ---------------------------------------------------------------------
echo "-> Mengatur kepemilikan & izin folder untuk user '$WEB_USER'..."
chown -R "$WEB_USER":"$WEB_USER" "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$APP_DIR/models"
chmod 664 "$APP_DIR/database.db" 2>/dev/null || true
chmod +x "$APP_DIR/python/venv/bin/python" 2>/dev/null || true
echo "   [OK] Selesai."
echo

# ---------------------------------------------------------------------
# 4) Cek shell_exec
# ---------------------------------------------------------------------
echo "-> Mengecek shell_exec (dipakai untuk menjalankan Python dari PHP)..."
DISABLED=$(php -r 'echo ini_get("disable_functions");')
if echo "$DISABLED" | grep -qi "shell_exec"; then
    echo "   [X] shell_exec ADA di disable_functions php.ini -> fitur Latih Ulang & Prediksi tidak akan jalan."
    echo "       Hapus 'shell_exec' dari disable_functions di php.ini, lalu restart php-fpm."
else
    echo "   [OK] shell_exec tidak diblokir di php.ini"
fi
echo

echo "== Setup selesai =="
echo "Langkah berikutnya:"
echo "  1. Pastikan web server (Nginx/Apache) sudah dikonfigurasi (lihat nginx_contoh.conf jika pakai Nginx)."
echo "  2. Buka https://domain-anda/cek_sistem.php di browser untuk verifikasi akhir."
echo "  3. Login lalu SEGERA ganti password default (peneliti2026 / sabrina2026) lewat menu Ganti Sandi."
