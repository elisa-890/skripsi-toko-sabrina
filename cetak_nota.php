<?php
/**
 * cetak_nota.php
 * -----------------------------------------------------------------
 * Menampilkan nota/struk transaksi siap cetak, dibuat LANGSUNG dari
 * data yang sudah tersimpan di tabel transaksi (id_transaksi lewat
 * ?id=). Tidak ada input ulang sama sekali — kasir hanya mengisi
 * form Input Transaksi SATU KALI, lalu nota ini otomatis terbentuk
 * dari data yang sama untuk diberikan/dicetak untuk pelanggan.
 *
 * Dipanggil dari:
 *   - halaman/input_transaksi.php (tombol "Cetak Nota" per baris, dan
 *     otomatis terbuka di tab baru begitu transaksi baru disimpan)
 *   - halaman/riwayat.php (tombol "Cetak Nota" per baris)
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/config/pengaturan.php';
wajib_peran(['pemilik']);
require_once __DIR__ . '/config/koneksi_db.php';

$id_transaksi = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_transaksi) {
    http_response_code(400);
    die('Nomor transaksi tidak valid.');
}

$pernyataan = $koneksi->prepare(
    "SELECT t.id_transaksi, t.tanggal_transaksi, t.jumlah, t.id_batch_impor,
            COALESCE(NULLIF(t.satuan_transaksi, ''), p.satuan) AS satuan,
            t.harga_satuan, t.total_penjualan, t.catatan,
            p.kode_produk, p.nama_produk, p.kategori,
            u.nama_lengkap AS nama_kasir, u.peran AS peran_kasir
     FROM transaksi t
     JOIN produk p ON p.id_produk = t.id_produk
     LEFT JOIN pengguna u ON u.id_pengguna = t.id_pengguna
     WHERE t.id_transaksi = :id"
);
$pernyataan->execute(['id' => $id_transaksi]);
$t = $pernyataan->fetch();

if (!$t) {
    http_response_code(404);
    die('Transaksi tidak ditemukan.');
}

// Label "Kasir" pada nota TIDAK BOLEH menampilkan nama akun peneliti —
// peran peneliti hanya bertugas mendigitalisasi data historis lewat menu
// Impor Data (lihat index.php: peran 'impor_data' => ['peneliti']), bukan
// benar-benar melayani transaksi pelanggan di toko. Jadi untuk transaksi
// hasil impor (id_batch_impor terisi) ATAU jika kebetulan tercatat atas
// akun berperan peneliti, nota tetap menampilkan "Pemilik Toko Sabrina"
// sebagai representasi toko, bukan nama akun peneliti.
if (!empty($t['id_batch_impor']) || ($t['peran_kasir'] ?? '') === 'peneliti') {
    $label_kasir = !empty($t['id_batch_impor'])
        ? 'Pemilik Toko Sabrina (Data Riwayat)'
        : 'Pemilik Toko Sabrina';
} else {
    $label_kasir = $t['nama_kasir'] ?? 'Pemilik Toko Sabrina';
}

$nomor_nota = 'TS-' . date('Ymd', strtotime($t['tanggal_transaksi'])) . '-' . str_pad((string)$t['id_transaksi'], 5, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nota <?= aman($nomor_nota) ?> — Toko Sabrina</title>
<style>
    :root {
        --navy-tua: #10203f;
        --navy-utama: #1b3766;
        --abu-teks: #1c2333;
        --abu-lembut: #667085;
        --abu-garis: #d9dee8;
        --emas-aksen: #c69a4c;
    }
    * { box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, sans-serif;
        background: #eef1f6;
        color: var(--abu-teks);
        margin: 0;
        padding: 24px;
        display: flex;
        flex-direction: column;
        align-items: center;
    }
    .balok-aksi {
        width: 100%;
        max-width: 380px;
        display: flex;
        gap: 10px;
        margin-bottom: 16px;
    }
    .balok-aksi button, .balok-aksi a {
        flex: 1;
        text-align: center;
        padding: 12px;
        border-radius: 10px;
        border: none;
        font-weight: 700;
        font-size: 0.9rem;
        cursor: pointer;
        text-decoration: none;
        display: block;
    }
    .btn-cetak { background: var(--navy-utama); color: #fff; }
    .btn-kembali { background: #fff; color: var(--navy-utama); border: 1px solid var(--abu-garis) !important; }

    .nota {
        width: 100%;
        max-width: 380px;
        background: #fff;
        border-radius: 12px;
        padding: 22px 20px;
        box-shadow: 0 4px 18px rgba(16, 32, 63, 0.1);
    }
    .nota-header { text-align: center; margin-bottom: 14px; }
    .nota-header .nama-toko { font-size: 1.1rem; font-weight: 800; color: var(--navy-tua); margin: 0; }
    .nota-header .alamat-toko { font-size: 0.72rem; color: var(--abu-lembut); margin: 4px 0 0; line-height: 1.4; }
    .garis-putus { border: none; border-top: 1px dashed var(--abu-garis); margin: 12px 0; }

    .info-nota { font-size: 0.78rem; color: var(--abu-lembut); display: flex; justify-content: space-between; margin-bottom: 2px; }
    .info-nota b { color: var(--abu-teks); }

    table.item-nota { width: 100%; border-collapse: collapse; font-size: 0.82rem; margin-top: 10px; }
    table.item-nota th { text-align: left; font-size: 0.68rem; text-transform: uppercase; letter-spacing: .03em; color: var(--abu-lembut); padding-bottom: 6px; border-bottom: 1px solid var(--abu-garis); }
    table.item-nota td { padding: 8px 0; vertical-align: top; }
    table.item-nota td.kanan { text-align: right; }
    .nama-item { font-weight: 700; }
    .sub-item { font-size: 0.72rem; color: var(--abu-lembut); }

    .baris-total { display: flex; justify-content: space-between; padding: 4px 0; font-size: 0.85rem; }
    .baris-total.grand { font-size: 1.05rem; font-weight: 800; color: var(--navy-tua); padding-top: 8px; }

    .catatan-nota { font-size: 0.75rem; color: var(--abu-lembut); margin-top: 10px; font-style: italic; }
    .footer-nota { text-align: center; font-size: 0.72rem; color: var(--abu-lembut); margin-top: 16px; line-height: 1.5; }

    @media print {
        body { background: #fff; padding: 0; }
        .balok-aksi { display: none !important; }
        .nota { box-shadow: none; max-width: 100%; padding: 0; }
    }
</style>
</head>
<body>

<div class="balok-aksi">
    <button type="button" class="btn-cetak" onclick="window.print()">🖨️ Cetak Nota</button>
    <a href="index.php?hal=input_transaksi" class="btn-kembali">Kembali</a>
</div>

<div class="nota">
    <div class="nota-header">
        <p class="nama-toko">Toko Pakan Ternak Sabrina</p>
        <p class="alamat-toko">Desa Nagasaribu V, Kec. Lintongnihuta,<br>Kab. Humbang Hasundutan</p>
    </div>

    <hr class="garis-putus">

    <div class="info-nota"><span>No. Nota</span><b><?= aman($nomor_nota) ?></b></div>
    <div class="info-nota"><span>Tanggal</span><b><?= date('d M Y', strtotime($t['tanggal_transaksi'])) ?></b></div>
    <div class="info-nota"><span>Kasir</span><b><?= aman($label_kasir) ?></b></div>

    <hr class="garis-putus">

    <table class="item-nota">
        <thead>
            <tr>
                <th>Produk</th>
                <th class="kanan">Jumlah</th>
                <th class="kanan">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="nama-item"><?= aman($t['nama_produk']) ?></div>
                    <div class="sub-item"><?= aman($t['kode_produk']) ?> · Rp<?= number_format($t['harga_satuan'], 0, ',', '.') ?>/<?= aman($t['satuan']) ?></div>
                </td>
                <td class="kanan"><?= angka($t['jumlah']) ?> <?= aman($t['satuan']) ?></td>
                <td class="kanan">Rp<?= number_format($t['total_penjualan'], 0, ',', '.') ?></td>
            </tr>
        </tbody>
    </table>

    <hr class="garis-putus">

    <div class="baris-total grand"><span>Total Bayar</span><span>Rp<?= number_format($t['total_penjualan'], 0, ',', '.') ?></span></div>

    <?php if (!empty($t['catatan'])): ?>
        <p class="catatan-nota">Catatan: <?= aman($t['catatan']) ?></p>
    <?php endif; ?>

    <div class="footer-nota">
        Terima kasih telah berbelanja di Toko Sabrina 🙏<br>
        Nota ini dicetak otomatis dari sistem, sah tanpa tanda tangan.
    </div>
</div>

</body>
</html>
