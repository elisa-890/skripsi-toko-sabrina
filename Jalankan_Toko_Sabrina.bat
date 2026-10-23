@echo off
REM =====================================================================
REM  Jalankan_Toko_Sabrina.bat
REM  ---------------------------------------------------------------
REM  Peluncur aplikasi Dashboard Toko Sabrina, TANPA perlu XAMPP Control
REM  Panel / Apache / hosting / VPS. Cara kerja:
REM    1. Menjalankan server bawaan PHP (php -S) di latar belakang,
REM       khusus melayani folder aplikasi ini saja.
REM    2. Membuka browser dalam "mode aplikasi" (tanpa address bar/tab)
REM       supaya terasa seperti aplikasi asli, bukan tab browser biasa.
REM
REM  Kalau PHP tidak otomatis ditemukan, buat file "php_bin.txt" di
REM  folder ini berisi SATU BARIS path lengkap ke php.exe Anda, contoh:
REM      C:\xampp\php\php.exe
REM =====================================================================

setlocal enabledelayedexpansion
cd /d "%~dp0"

set "PORT=8000"
set "PHP_EXE="

REM --- 1) Cek file override manual php_bin.txt ---
if exist "php_bin.txt" (
    set /p PHP_EXE=<php_bin.txt
)

REM --- 2) Coba lokasi umum instalasi XAMPP ---
if "!PHP_EXE!"=="" (
    if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
)

REM --- 3) Coba PHP yang terdaftar di PATH sistem ---
if "!PHP_EXE!"=="" (
    where php >nul 2>nul
    if !errorlevel! equ 0 set "PHP_EXE=php"
)

if "!PHP_EXE!"=="" (
    echo ============================================================
    echo   PHP tidak ditemukan di komputer ini.
    echo   Buat file "php_bin.txt" di folder ini, isi 1 baris path
    echo   lengkap ke php.exe Anda, contoh:
    echo       C:\xampp\php\php.exe
    echo ============================================================
    pause
    exit /b 1
)

REM --- Pastikan server lama (kalau masih nyala) dimatikan dulu ---
taskkill /F /FI "WINDOWTITLE eq ServerTokoSabrina*" >nul 2>nul

REM --- Jalankan server PHP bawaan di latar belakang (jendela tersembunyi) ---
start "ServerTokoSabrina" /min "%PHP_EXE%" -S localhost:%PORT% -t "%~dp0"

REM Beri waktu sebentar untuk server siap sebelum browser dibuka
timeout /t 2 /nobreak >nul

set "URL=http://localhost:%PORT%/login.php"

REM --- Coba buka di jendela mode-aplikasi (tanpa address bar) ---
set "EDGE=C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"
set "CHROME1=C:\Program Files\Google\Chrome\Application\chrome.exe"
set "CHROME2=C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"

if exist "%EDGE%" (
    start "" "%EDGE%" --app=%URL%
) else if exist "%CHROME1%" (
    start "" "%CHROME1%" --app=%URL%
) else if exist "%CHROME2%" (
    start "" "%CHROME2%" --app=%URL%
) else (
    REM Fallback: buka pakai browser default (tab biasa)
    start "" "%URL%"
)

exit /b 0
