<?php
/**
 * php/ambil_hasil.php
 * -----------------------------------------------------------------
 * Dipanggil lewat AJAX (fetch) dari halaman/hasil_prediksi.php.
 *
 * Menjalankan python/prediksi.py dan mengembalikan hasilnya sebagai JSON.
 *
 * CATATAN SERVER:
 * Shared hosting Toko Sabrina menonaktifkan escapeshellarg().
 * Karena itu file ini TIDAK menggunakan escapeshellarg().
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/pengaturan.php';

wajib_peran(['peneliti']);

// Endpoint ini harus selalu mengembalikan JSON murni.
header('Content-Type: application/json; charset=utf-8');


// =====================================================================
// AMBIL PARAMETER
// =====================================================================

$id_produk = $_REQUEST['id_produk'] ?? 'semua';

$jumlah_hari = filter_var(
    $_REQUEST['jumlah_hari'] ?? 7,
    FILTER_VALIDATE_INT
);


// =====================================================================
// VALIDASI JUMLAH HARI
// =====================================================================

if (
    $jumlah_hari === false ||
    $jumlah_hari < 1 ||
    $jumlah_hari > 60
) {
    echo json_encode([
        'status' => 'gagal',
        'pesan'  => 'Jumlah hari harus antara 1 dan 60.'
    ]);

    exit;
}


// =====================================================================
// VALIDASI ID PRODUK
// =====================================================================

if (
    $id_produk !== 'semua' &&
    !ctype_digit((string)$id_produk)
) {
    echo json_encode([
        'status' => 'gagal',
        'pesan'  => 'ID produk tidak valid.'
    ]);

    exit;
}


// =====================================================================
// CEK SHELL_EXEC
// =====================================================================

if (!function_exists('shell_exec')) {

    http_response_code(500);

    echo json_encode([
        'status' => 'gagal',
        'pesan'  => 'Fitur Python tidak tersedia karena shell_exec dinonaktifkan di server.'
    ]);

    exit;
}


// =====================================================================
// SUSUN PERINTAH PYTHON
// =====================================================================
//
// Server menonaktifkan escapeshellarg().
//
// Kita tidak perlu menggunakan escapeshellarg() untuk:
// - id_produk karena sudah divalidasi hanya "semua" atau angka
// - jumlah_hari karena sudah divalidasi sebagai angka 1-60
//
// Untuk path Python dan script digunakan escapeshellarg_bin(),
// yaitu fungsi khusus yang sudah kita buat di pengaturan.php.
//

$python_bin = escapeshellarg_bin(PYTHON_BIN);

$script_py = escapeshellarg_bin(
    PATH_PYTHON . '/prediksi.py'
);

$id_arg = (string)$id_produk;

$hari_arg = (string)$jumlah_hari;


// =====================================================================
// PERINTAH LENGKAP
// =====================================================================

$perintah =
    'OPENBLAS_NUM_THREADS=1 ' .
    'OMP_NUM_THREADS=1 ' .
    'MKL_NUM_THREADS=1 ' .
    'NUMEXPR_NUM_THREADS=1 ' .
    $python_bin .
    ' ' .
    $script_py .
    ' ' .
    $id_arg .
    ' ' .
    $hari_arg .
    ' 2>&1';


// =====================================================================
// JALANKAN PYTHON
// =====================================================================

$keluaran = shell_exec($perintah);


// =====================================================================
// JIKA SHELL_EXEC GAGAL
// =====================================================================

if ($keluaran === null) {

    http_response_code(500);

    echo json_encode([
        'status'   => 'gagal',
        'pesan'    => 'Gagal menjalankan proses Python. Periksa apakah shell_exec diizinkan di server.',
    ]);

    exit;
}


// =====================================================================
// BERSIHKAN OUTPUT PYTHON
// =====================================================================

$keluaran_trim = trim((string)$keluaran);

$baris_baris = array_filter(
    array_map(
        'trim',
        explode("\n", $keluaran_trim)
    )
);


// =====================================================================
// JIKA PYTHON TIDAK MENGHASILKAN OUTPUT
// =====================================================================

if (empty($baris_baris)) {

    echo json_encode([
        'status' => 'gagal',
        'pesan'  => 'Python tidak menghasilkan keluaran.'
    ]);

    exit;
}


// =====================================================================
// AMBIL BARIS TERAKHIR OUTPUT PYTHON
// =====================================================================

$baris_terakhir = end($baris_baris);


// =====================================================================
// DECODE JSON
// =====================================================================

$hasil_decode = json_decode(
    $baris_terakhir,
    true
);


// =====================================================================
// JIKA JSON TIDAK VALID
// =====================================================================

if (json_last_error() !== JSON_ERROR_NONE) {

    echo json_encode([
        'status'   => 'gagal',
        'pesan'    => 'Output Python bukan JSON valid.',
        'detail'   => $keluaran,
        'perintah' => $perintah
    ]);

    exit;
}


// =====================================================================
// KIRIM HASIL
// =====================================================================

echo json_encode(
    $hasil_decode,
    JSON_UNESCAPED_UNICODE
);