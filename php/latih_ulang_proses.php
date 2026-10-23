<?php
/**
 * php/latih_ulang_proses.php
 * -----------------------------------------------------------------
 * Dipanggil lewat AJAX dari halaman/latih_ulang.php.
 * Menjalankan python/latih_model.py dan mengembalikan hasil evaluasi.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti']);

header('Content-Type: application/json; charset=utf-8');

// Beri waktu lebih panjang untuk proses training
set_time_limit(300);

if (!function_exists('shell_exec')) {
    http_response_code(500);
    echo json_encode(['status' => 'gagal', 'pesan' => 'Fitur Python tidak tersedia karena shell_exec dinonaktifkan di server.']);
    exit;
}

$perintah =
    'OPENBLAS_NUM_THREADS=1 '
    . 'OMP_NUM_THREADS=1 '
    . 'MKL_NUM_THREADS=1 '
    . 'NUMEXPR_NUM_THREADS=1 '
    . escapeshellarg_bin(PYTHON_BIN) . ' ' .
    shell_arg_aman(PATH_PYTHON . '/latih_model.py') . ' 2>&1';

$keluaran = shell_exec($perintah);

if ($keluaran === null) {
    echo json_encode(['status' => 'gagal', 'pesan' => 'Gagal menjalankan proses Python. Periksa apakah shell_exec diizinkan di server.']);
    exit;
}

$baris_baris = array_filter(array_map('trim', explode("\n", trim($keluaran))));
$baris_terakhir = end($baris_baris);

$hasil_decode = json_decode($baris_terakhir, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    $pesan_ramah = 'Proses Python gagal dijalankan. Buka halaman "Cek Sistem" (cek_sistem.php) untuk melihat penyebabnya.';
    if (stripos((string)$keluaran, 'ModuleNotFoundError') !== false) {
        $pesan_ramah = 'Beberapa library Python belum terpasang di server. Jalankan: pip install pandas statsmodels xgboost scikit-learn — atau buka halaman "Cek Sistem" untuk rincian.';
    } elseif (stripos((string)$keluaran, 'not found') !== false || stripos((string)$keluaran, 'tidak dikenal') !== false || trim((string)$keluaran) === '') {
        $pesan_ramah = 'Perintah Python (' . PYTHON_BIN . ') tidak ditemukan di server. Periksa PYTHON_BIN di config/pengaturan.php, atau buka halaman "Cek Sistem".';
    }
    echo json_encode(['status' => 'gagal', 'pesan' => $pesan_ramah, 'detail' => $keluaran]);
    exit;
}

echo json_encode($hasil_decode);
