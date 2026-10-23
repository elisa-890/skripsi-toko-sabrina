<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

$id_produk_filter = filter_input(INPUT_GET, 'produk', FILTER_VALIDATE_INT) ?: null;
$dari_filter      = $_GET['dari'] ?? '';
$sampai_filter    = $_GET['sampai'] ?? '';

$daftar_produk = $koneksi->query("SELECT id_produk, kode_produk, nama_produk, kategori FROM produk ORDER BY nama_produk")->fetchAll();

// Kelompokkan produk per jenis pakan (Ayam/Bebek/Babi/Lainnya) supaya dropdown
// filter Produk juga jelas membedakan jenisnya, sama seperti di Catat Transaksi.
$produk_per_kelompok = ['Ayam' => [], 'Bebek' => [], 'Babi' => [], 'Lainnya' => []];
foreach ($daftar_produk as $p) {
    $kelompok = kelompok_pakan($p['kategori']) ?: 'Lainnya';
    $produk_per_kelompok[$kelompok][] = $p;
}

$pesan_sukses = $_SESSION['pesan_transaksi_sukses'] ?? '';
unset($_SESSION['pesan_transaksi_sukses']);
$pesan_error = $_SESSION['pesan_transaksi_error'] ?? '';
unset($_SESSION['pesan_transaksi_error']);

$klausa = [];
$params = [];

if ($id_produk_filter) {
    $klausa[] = 't.id_produk = :id_produk';
    $params['id_produk'] = $id_produk_filter;
}
if ($dari_filter) {
    $klausa[] = 't.tanggal_transaksi >= :dari';
    $params['dari'] = $dari_filter;
}
if ($sampai_filter) {
    $klausa[] = 't.tanggal_transaksi <= :sampai';
    $params['sampai'] = $sampai_filter;
}

$where_sql = $klausa ? ('WHERE ' . implode(' AND ', $klausa)) : '';

$pernyataan = $koneksi->prepare(
    "SELECT t.id_transaksi, t.tanggal_transaksi, p.nama_produk, p.kode_produk, p.kategori, t.jumlah,
            COALESCE(NULLIF(t.satuan_transaksi, ''), p.satuan) AS satuan, t.jumlah_kg,
            t.harga_satuan, t.total_penjualan, t.catatan
     FROM transaksi t
     JOIN produk p ON p.id_produk = t.id_produk
     $where_sql
     ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC
     LIMIT 300"
);
$pernyataan->execute($params);
$daftar_transaksi = $pernyataan->fetchAll();
?>

<?php if ($pesan_sukses): ?>
    <div class="pesan pesan-sukses"><?= aman($pesan_sukses) ?></div>
<?php endif; ?>
<?php if ($pesan_error): ?>
    <div class="pesan pesan-error"><?= aman($pesan_error) ?></div>
<?php endif; ?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Filter Riwayat</h2>
    <form method="GET" action="index.php" class="form-filter">
        <input type="hidden" name="hal" value="riwayat">
        <div class="grup-input">
            <label for="produk">Produk</label>
            <select name="produk" id="produk">
                <option value="">Semua Produk</option>
                <?php foreach ($produk_per_kelompok as $nama_kelompok => $produk_kelompok): ?>
                    <?php if (empty($produk_kelompok)) continue; ?>
                    <optgroup label="<?= aman($nama_kelompok) ?>">
                        <?php foreach ($produk_kelompok as $p): ?>
                            <option value="<?= (int)$p['id_produk'] ?>" <?= $id_produk_filter == $p['id_produk'] ? 'selected' : '' ?>>
                                [<?= aman($p['kode_produk']) ?>] <?= aman($p['nama_produk']) ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label for="dari">Dari Tanggal</label>
                <input type="date" name="dari" id="dari" value="<?= aman($dari_filter) ?>">
            </div>
            <div class="grup-input">
                <label for="sampai">Sampai Tanggal</label>
                <input type="date" name="sampai" id="sampai" value="<?= aman($sampai_filter) ?>">
            </div>
        </div>
        <button type="submit" class="tombol tombol-utama tombol-lebar">Terapkan Filter</button>
    </form>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Daftar Transaksi (<?= count($daftar_transaksi) ?>)</h2>
    <?php if (empty($daftar_transaksi)): ?>
        <p class="teks-kosong">Tidak ada transaksi yang cocok dengan filter.</p>
    <?php else: ?>
        <div class="wadah-tabel">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Pakan</th>
                    <th>Kode</th>
                    <th>Produk</th>
                    <th>Jumlah</th>
                    <th>Setara (kg)</th>
                    <th>Harga Satuan</th>
                    <th>Total (Rp)</th>
                    <th>Catatan</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_transaksi as $t): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($t['tanggal_transaksi'])) ?></td>
                        <td><?= aman($t['kategori']) ?></td>
                        <td><code><?= aman($t['kode_produk']) ?></code></td>
                        <td><?= aman($t['nama_produk']) ?></td>
                        <td><?= angka($t['jumlah']) ?> <?= aman($t['satuan']) ?></td>
                        <td><?= angka($t['jumlah_kg']) ?> kg</td>
                        <td><?= number_format($t['harga_satuan'], 0, ',', '.') ?></td>
                        <td><?= number_format($t['total_penjualan'], 0, ',', '.') ?></td>
                        <td><?= aman($t['catatan'] ?? '—') ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <a href="cetak_nota.php?id=<?= (int)$t['id_transaksi'] ?>" target="_blank" class="tombol" style="padding:6px 10px;font-size:0.75rem;background:var(--navy-pucat);color:var(--navy-utama);text-decoration:none;display:inline-block;">🖨️ Nota</a>
                                <a href="index.php?hal=edit_transaksi&id=<?= (int)$t['id_transaksi'] ?>" class="tombol" style="padding:6px 10px;font-size:0.75rem;background:var(--emas-pucat);color:var(--navy-tua);text-decoration:none;display:inline-block;">Edit</a>
                                <form action="php/hapus_transaksi.php" method="POST" style="margin:0;" onsubmit="return confirm('Hapus transaksi ini? Tindakan tidak bisa dibatalkan.');">
                                    <input type="hidden" name="id_transaksi" value="<?= (int)$t['id_transaksi'] ?>">
                                    <button type="submit" class="tombol" style="padding:6px 10px;font-size:0.75rem;background:var(--merah-latar);color:var(--merah-galat);">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>
