# Perbaikan Fitur Unduh Laporan

Perbaikan ini mengubah hasil unduhan laporan menjadi dokumen yang lebih resmi dan berbentuk tabel.

## Perubahan

- PDF menggunakan kop: Pemerintah Kabupaten Humbang Hasundutan, Kecamatan Lintong Nihuta, Desa Nagasaribu V.
- Logo yang digunakan adalah logo bawaan Sistem Toko Sabrina.
- Alamat dan HP ditampilkan pada kop dokumen.
- Nomor surat dibuat otomatis dengan pola `001/DSN-V/LN/[BULAN-ROMAWI]/[TAHUN]`.
- Tanggal dokumen dibuat otomatis mengikuti tanggal saat laporan diunduh.
- Nomor surat dan tanggal yang sama ditampilkan pada PDF dan Word.
- Rekap ringkasan dan data produk ditampilkan dalam tabel bergaris.
- Tabel PDF mendukung beberapa halaman, mengulang kop/tabel, dan melanjutkan nomor data.
- Hasil Word memakai HTML kompatibel Microsoft Word dengan tabel dan border eksplisit, disusun mengikuti struktur PDF.
- Tanda tangan mencantumkan `Juntar Lumbantoruan`.
- Query laporan disesuaikan dengan kolom database `transaksi.jumlah`.

## Berkas yang terkait

- `php/unduh_laporan.php`
- `assets/icons/icon-192-pdf.jpg`

Tautan lama pada `halaman/laporan.php` tetap kompatibel: `format=doc` untuk Word dan `format=pdf` untuk PDF.
