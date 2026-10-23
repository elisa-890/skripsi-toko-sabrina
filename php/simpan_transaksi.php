<?php
require_once __DIR__ . '/../config/pengaturan.php';
require_once __DIR__ . '/../config/koneksi_db.php';
require_once __DIR__ . '/../config/hitung_tren.php';
wajib_peran(['pemilik']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=input_transaksi');
    exit;
}

function kembali_dengan_error(string $pesan): void
{
    $_SESSION['pesan_transaksi_error'] = $pesan;
    header('Location: ../index.php?hal=input_transaksi');
    exit;
}

$id_produk         = filter_input(INPUT_POST, 'id_produk', FILTER_VALIDATE_INT);
$jumlah            = filter_input(INPUT_POST, 'jumlah', FILTER_VALIDATE_FLOAT);
$satuan_transaksi  = strtolower(trim($_POST['satuan_transaksi'] ?? ''));
$tanggal_transaksi = $_POST['tanggal_transaksi'] ?? '';
$harga_satuan      = filter_input(INPUT_POST, 'harga_satuan', FILTER_VALIDATE_FLOAT) ?: 0;
$catatan           = trim($_POST['catatan'] ?? '');

if (!$id_produk || !$jumlah || $jumlah <= 0 || $tanggal_transaksi === '') {
    kembali_dengan_error('Mohon lengkapi semua data wajib dengan benar.');
}

if (!in_array($satuan_transaksi, SATUAN_DIIZINKAN, true)) {
    kembali_dengan_error('Satuan tidak valid. Pilih salah satu: ' . implode(', ', SATUAN_DIIZINKAN) . '.');
}

$tanggal_valid = DateTime::createFromFormat('Y-m-d', $tanggal_transaksi);
if (!$tanggal_valid) {
    kembali_dengan_error('Format tanggal tidak valid.');
}

// Cegah tanggal transaksi salah ketik jauh ke masa depan/masa lalu ekstrem
// (mis. "2026" tertukar jadi "2062", atau tahun kurang satu digit). Selain
// data jadi tidak masuk akal, ini juga bisa membuat perhitungan MAPE pada
// menu "Latih Ulang" meledak tidak wajar karena banyak minggu kosong
// tercipta di antara data lama dan tanggal jauh tsb.
$hari_ini = new DateTime('today');
$batas_depan = (clone $hari_ini)->modify('+7 days');
$batas_belakang = (clone $hari_ini)->modify('-15 years');
if ($tanggal_valid > $batas_depan) {
    kembali_dengan_error('Tanggal transaksi tidak boleh jauh di masa depan. Cek kembali tahun/bulan/tanggal yang diketik.');
}
if ($tanggal_valid < $batas_belakang) {
    kembali_dengan_error('Tanggal transaksi terlalu lampau. Cek kembali tahun/bulan/tanggal yang diketik.');
}

$cek_produk = $koneksi->prepare('SELECT id_produk FROM produk WHERE id_produk = :id');
$cek_produk->execute(['id' => $id_produk]);
if (!$cek_produk->fetch()) {
    kembali_dengan_error('Produk tidak ditemukan.');
}

$total_penjualan = $jumlah * $harga_satuan;
$jumlah_kg       = $jumlah * (faktor_konversi_kg($satuan_transaksi) ?? 1.0);

$pernyataan = $koneksi->prepare(
    'INSERT INTO transaksi
        (id_produk, tanggal_transaksi, jumlah, satuan_transaksi, jumlah_kg, harga_satuan, total_penjualan, catatan, id_pengguna)
     VALUES
        (:id_produk, :tanggal, :jumlah, :satuan_transaksi, :jumlah_kg, :harga_satuan, :total_penjualan, :catatan, :id_pengguna)'
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
        'id_pengguna'      => $_SESSION['id_pengguna'],
    ]);
} catch (PDOException $e) {
    kembali_dengan_error('Gagal menyimpan transaksi. Coba lagi.');
}

// Simpan id transaksi yang baru saja dibuat -> dipakai halaman
// input_transaksi.php untuk otomatis membuka nota (cetak_nota.php) di tab
// baru, memakai data yang SAMA dengan yang baru diinput (tanpa ketik ulang).
$_SESSION['transaksi_baru_id'] = (int)$koneksi->lastInsertId();

// Perbarui tren_penjualan supaya data mingguan langsung mencerminkan
// transaksi baru ini (lihat config/hitung_tren.php).
hitung_ulang_tren_penjualan($koneksi);

$_SESSION['pesan_transaksi_sukses'] = 'Transaksi berhasil disimpan (' . angka($jumlah) . ' ' . $satuan_transaksi . ').';
header('Location: ../index.php?hal=input_transaksi');
exit;
