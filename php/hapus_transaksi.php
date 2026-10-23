<?php
require_once __DIR__ . '/../config/pengaturan.php';
require_once __DIR__ . '/../config/koneksi_db.php';
require_once __DIR__ . '/../config/hitung_tren.php';
wajib_peran(['pemilik']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=riwayat');
    exit;
}

$id_transaksi = filter_input(INPUT_POST, 'id_transaksi', FILTER_VALIDATE_INT);

if ($id_transaksi) {
    $koneksi->prepare('DELETE FROM transaksi WHERE id_transaksi = :id')->execute(['id' => $id_transaksi]);
    hitung_ulang_tren_penjualan($koneksi);
    $_SESSION['pesan_transaksi_sukses'] = 'Transaksi berhasil dihapus.';
} else {
    $_SESSION['pesan_transaksi_error'] = 'ID transaksi tidak valid.';
}

header('Location: ../index.php?hal=riwayat');
exit;
