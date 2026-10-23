<?php
/**
 * php/hapus_data.php
 * -----------------------------------------------------------------
 * Menangani penghapusan data transaksi dari halaman Kelola Data:
 *   - aksi=hapus_batch  : hapus satu sesi impor Excel tertentu
 *   - aksi=hapus_semua  : hapus SEMUA transaksi + hasil_prediksi +
 *                         evaluasi_model (butuh teks konfirmasi persis)
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/pengaturan.php';
require_once __DIR__ . '/../config/koneksi_db.php';
require_once __DIR__ . '/../config/hitung_tren.php';
wajib_peran(['peneliti']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=kelola_data');
    exit;
}

$aksi = $_POST['aksi'] ?? '';

if ($aksi === 'hapus_batch') {
    $id_batch = trim($_POST['id_batch_impor'] ?? '');

    if ($id_batch === '') {
        $_SESSION['pesan_kelola_error'] = 'Sesi impor tidak valid.';
        header('Location: ../index.php?hal=kelola_data');
        exit;
    }

    $pernyataan = $koneksi->prepare('DELETE FROM transaksi WHERE id_batch_impor = :batch');
    $pernyataan->execute(['batch' => $id_batch]);
    $jumlah_terhapus = $pernyataan->rowCount();
    hitung_ulang_tren_penjualan($koneksi);

    $_SESSION['pesan_kelola_sukses'] = "$jumlah_terhapus baris transaksi dari sesi impor tersebut berhasil dihapus.";
    header('Location: ../index.php?hal=kelola_data');
    exit;
}

if ($aksi === 'hapus_semua') {
    $konfirmasi = trim($_POST['konfirmasi_hapus'] ?? '');

    if ($konfirmasi !== 'HAPUS SEMUA DATA') {
        $_SESSION['pesan_kelola_error'] = 'Teks konfirmasi tidak sesuai. Data tidak dihapus.';
        header('Location: ../index.php?hal=kelola_data');
        exit;
    }

    $koneksi->beginTransaction();
    $koneksi->exec('DELETE FROM hasil_prediksi');
    $koneksi->exec('DELETE FROM evaluasi_model');
    $koneksi->exec('DELETE FROM transaksi');
    $koneksi->exec('DELETE FROM tren_penjualan');
    $koneksi->commit();

    $_SESSION['pesan_kelola_sukses'] = 'Semua data transaksi, hasil prediksi, dan riwayat evaluasi model berhasil dihapus.';
    header('Location: ../index.php?hal=kelola_data');
    exit;
}

$_SESSION['pesan_kelola_error'] = 'Aksi tidak dikenali.';
header('Location: ../index.php?hal=kelola_data');
exit;
