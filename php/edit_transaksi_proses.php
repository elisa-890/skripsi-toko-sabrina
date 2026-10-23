<?php
require_once __DIR__ . '/../config/pengaturan.php';
require_once __DIR__ . '/../config/koneksi_db.php';
require_once __DIR__ . '/../config/hitung_tren.php';
wajib_peran(['pemilik']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=riwayat');
    exit;
}

$id_transaksi      = filter_input(INPUT_POST, 'id_transaksi', FILTER_VALIDATE_INT);
$id_produk         = filter_input(INPUT_POST, 'id_produk', FILTER_VALIDATE_INT);
$jumlah            = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_FLOAT);
$satuan_transaksi  = strtolower(trim($_POST['satuan_transaksi'] ?? ''));
$tanggal_transaksi = $_POST['tanggal_transaksi'] ?? '';
$harga_satuan      = filter_input(INPUT_POST, 'harga_satuan', FILTER_VALIDATE_FLOAT) ?: 0;
$catatan           = trim($_POST['catatan'] ?? '');

function kembali_dengan_error_edit(int $id, string $pesan): void
{
    $_SESSION['pesan_edit_error'] = $pesan;
    header("Location: ../index.php?hal=edit_transaksi&id=$id");
    exit;
}

if (!$id_transaksi || !$id_produk || !$jumlah || $jumlah <= 0 || $tanggal_transaksi === '') {
    kembali_dengan_error_edit((int)$id_transaksi, 'Mohon lengkapi semua data wajib dengan benar.');
}

if (!in_array($satuan_transaksi, SATUAN_DIIZINKAN, true)) {
    kembali_dengan_error_edit((int)$id_transaksi, 'Satuan tidak valid. Pilih salah satu: ' . implode(', ', SATUAN_DIIZINKAN) . '.');
}

$tanggal_valid = DateTime::createFromFormat('Y-m-d', $tanggal_transaksi);
if (!$tanggal_valid) {
    kembali_dengan_error_edit($id_transaksi, 'Format tanggal tidak valid.');
}

// Sama seperti simpan_transaksi.php: cegah tanggal salah ketik jauh ke
// masa depan/lampau ekstrem, yang bisa membuat perhitungan MAPE di menu
// "Latih Ulang" meledak tidak wajar akibat minggu kosong di data agregat.
$hari_ini = new DateTime('today');
$batas_depan = (clone $hari_ini)->modify('+7 days');
$batas_belakang = (clone $hari_ini)->modify('-15 years');
if ($tanggal_valid > $batas_depan) {
    kembali_dengan_error_edit($id_transaksi, 'Tanggal transaksi tidak boleh jauh di masa depan. Cek kembali tahun/bulan/tanggal yang diketik.');
}
if ($tanggal_valid < $batas_belakang) {
    kembali_dengan_error_edit($id_transaksi, 'Tanggal transaksi terlalu lampau. Cek kembali tahun/bulan/tanggal yang diketik.');
}

$cek_transaksi = $koneksi->prepare('SELECT id_transaksi FROM transaksi WHERE id_transaksi = :id');
$cek_transaksi->execute(['id' => $id_transaksi]);
if (!$cek_transaksi->fetch()) {
    $_SESSION['pesan_transaksi_error'] = 'Transaksi tidak ditemukan.';
    header('Location: ../index.php?hal=riwayat');
    exit;
}

$total_penjualan = $jumlah * $harga_satuan;
$jumlah_kg       = $jumlah * (faktor_konversi_kg($satuan_transaksi) ?? 1.0);

$pernyataan = $koneksi->prepare(
    'UPDATE transaksi
     SET id_produk = :id_produk, tanggal_transaksi = :tanggal, jumlah = :jumlah,
         satuan_transaksi = :satuan_transaksi, jumlah_kg = :jumlah_kg,
         harga_satuan = :harga_satuan, total_penjualan = :total_penjualan, catatan = :catatan
     WHERE id_transaksi = :id_transaksi'
);

try {
    $pernyataan->execute([
        'id_produk'        => $id_produk,
        'tanggal'          => $tanggal_transaksi,
        'jumlah'           => $jumlah,
        'satuan_transaksi' => $satuan_transaksi,
        'jumlah_kg'        => $jumlah_kg,
        'harga_satuan'     => $harga_satuan,
        'total_penjualan'  => $total_penjualan,
        'catatan'          => $catatan !== '' ? $catatan : null,
        'id_transaksi'     => $id_transaksi,
    ]);
} catch (PDOException $e) {
    kembali_dengan_error_edit($id_transaksi, 'Gagal menyimpan perubahan. Coba lagi.');
}

hitung_ulang_tren_penjualan($koneksi);

$_SESSION['pesan_transaksi_sukses'] = 'Transaksi berhasil diperbarui.';
header('Location: ../index.php?hal=riwayat');
exit;
