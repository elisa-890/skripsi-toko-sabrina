@echo off
REM =====================================================================
REM  Tutup_Aplikasi.bat
REM  Mematikan server lokal (php -S) yang dijalankan oleh
REM  Jalankan_Toko_Sabrina.bat / Buka_Toko_Sabrina.vbs.
REM  Aman dijalankan kapan saja -- data tersimpan di database.db,
REM  bukan di memori server, jadi tidak akan hilang.
REM =====================================================================
echo Menutup server Dashboard Toko Sabrina...
taskkill /F /FI "WINDOWTITLE eq ServerTokoSabrina*" >nul 2>nul
taskkill /F /IM php.exe >nul 2>nul
echo Selesai. Aman ditutup.
timeout /t 2 /nobreak >nul
