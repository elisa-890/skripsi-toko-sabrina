<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

$produk_filter = filter_input(INPUT_GET, 'produk', FILTER_VALIDATE_INT) ?: null;
$dari_filter   = $_GET['dari'] ?? '';
$sampai_filter = $_GET['sampai'] ?? '';

$daftar_produk = $koneksi->query("SELECT id_produk, kode_produk, nama_produk FROM produk WHERE aktif = 1 ORDER BY nama_produk")->fetchAll();

$klausa = [];
$params = [];

if ($produk_filter) {
    $klausa[] = 't.id_produk = :id_produk';
    $params['id_produk'] = $produk_filter;
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

$ringkasan_stmt = $koneksi->prepare(
    "SELECT COUNT(*) AS jumlah_transaksi,
            COALESCE(SUM(t.total_penjualan), 0) AS omzet,
            COALESCE(SUM(t.jumlah_kg), 0) AS total_qty
     FROM transaksi t
     JOIN produk p ON p.id_produk = t.id_produk
     $where_sql"
);

$produk_klausa = ['p.aktif = 1'];
if ($produk_filter) {
    $produk_klausa[] = 'p.id_produk = :id_produk';
}
if ($dari_filter) {
    $produk_klausa[] = 't.tanggal_transaksi >= :dari';
}
if ($sampai_filter) {
    $produk_klausa[] = 't.tanggal_transaksi <= :sampai';
}
$produk_where_sql = 'WHERE ' . implode(' AND ', $produk_klausa);

$produk_stmt = $koneksi->prepare(
    "SELECT p.id_produk, p.kode_produk, p.nama_produk, p.satuan,
            COUNT(t.id_transaksi) AS jumlah_transaksi,
            COALESCE(SUM(t.jumlah_kg), 0) AS total_jumlah,
            COALESCE(SUM(t.total_penjualan), 0) AS omzet
     FROM produk p
     LEFT JOIN transaksi t ON t.id_produk = p.id_produk
     $produk_where_sql
     GROUP BY p.id_produk
     ORDER BY omzet DESC, total_jumlah DESC, p.nama_produk ASC"
);

try {
    $ringkasan_stmt->execute($params);
    $ringkasan = $ringkasan_stmt->fetch();
    $produk_stmt->execute($params);
    $daftar_laporan_produk = $produk_stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[Laporan] ' . $e->getMessage());
    $ringkasan = ['jumlah_transaksi' => 0, 'omzet' => 0, 'total_qty' => 0];
    $daftar_laporan_produk = [];
}

?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Filter Laporan</h2>
    <form method="GET" action="index.php" class="form-filter">
        <input type="hidden" name="hal" value="laporan">
        <div class="grup-input">
            <label for="produk">Produk</label>
            <select name="produk" id="produk">
                <option value="">Semua Produk</option>
                <?php foreach ($daftar_produk as $p): ?>
                    <option value="<?= (int)$p['id_produk'] ?>" <?= $produk_filter == $p['id_produk'] ? 'selected' : '' ?>>
                        [<?= aman($p['kode_produk']) ?>] <?= aman($p['nama_produk']) ?>
                    </option>
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
        <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <button type="submit" class="tombol tombol-utama">Tampilkan Laporan</button>
            <a href="php/unduh_laporan.php?format=doc<?= $produk_filter ? '&produk=' . (int)$produk_filter : '' ?><?= $dari_filter ? '&dari=' . urlencode($dari_filter) : '' ?><?= $sampai_filter ? '&sampai=' . urlencode($sampai_filter) : '' ?>" class="tombol tombol-sekunder" style="display:inline-flex;align-items:center;justify-content:center;">Unduh Word</a>
            <a href="php/unduh_laporan.php?format=pdf<?= $produk_filter ? '&produk=' . (int)$produk_filter : '' ?><?= $dari_filter ? '&dari=' . urlencode($dari_filter) : '' ?><?= $sampai_filter ? '&sampai=' . urlencode($sampai_filter) : '' ?>" class="tombol tombol-utama" style="display:inline-flex;align-items:center;justify-content:center;">Unduh PDF</a>
        </div>
    </form>
</section>

<section class="grid-kartu-ringkasan">
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">📈</span>
        <div>
            <p class="label-kartu">Total Omzet</p>
            <p class="nilai-kartu">Rp <?= number_format((float)$ringkasan['omzet'], 0, ',', '.') ?></p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">🧾</span>
        <div>
            <p class="label-kartu">Jumlah Transaksi</p>
            <p class="nilai-kartu"><?= number_format((int)$ringkasan['jumlah_transaksi'], 0, ',', '.') ?></p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">⚖️</span>
        <div>
            <p class="label-kartu">Total Terjual (kg)</p>
            <p class="nilai-kartu"><?= angka((float)$ringkasan['total_qty']) ?> kg</p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">🏆</span>
        <div>
            <p class="label-kartu">Produk Terlaris</p>
            <p class="nilai-kartu">
                <?php if (!empty($daftar_laporan_produk[0])): ?>
                    <?= aman($daftar_laporan_produk[0]['nama_produk']) ?>
                <?php else: ?>
                    —
                <?php endif; ?>
            </p>
        </div>
    </div>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Rekap Per Produk</h2>
    <?php if (empty($daftar_laporan_produk)): ?>
        <p class="teks-kosong">Belum ada data untuk periode ini.</p>
    <?php else: ?>
        <div class="wadah-tabel">
            <table class="tabel-data">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th>Kode</th>
                        <th>Jumlah Transaksi</th>
                        <th>Total Terjual (kg)</th>
                        <th>Omzet</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($daftar_laporan_produk as $item): ?>
                        <tr>
                            <td><?= aman($item['nama_produk']) ?></td>
                            <td><code><?= aman($item['kode_produk']) ?></code></td>
                            <td><?= number_format((int)$item['jumlah_transaksi'], 0, ',', '.') ?></td>
                            <td><?= angka((float)$item['total_jumlah']) ?> kg</td>
                            <td>Rp <?= number_format((float)$item['omzet'], 0, ',', '.') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
