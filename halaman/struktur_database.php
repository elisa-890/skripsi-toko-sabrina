<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti']);
require_once __DIR__ . '/../config/koneksi_db.php';

/**
 * halaman/struktur_database.php
 * -----------------------------------------------------------------
 * Menu khusus peran Peneliti untuk memeriksa kondisi basis data
 * SQLite (database.db) langsung dari dalam aplikasi, tanpa perlu
 * membuka DB Browser for SQLite atau tools eksternal lain.
 *
 * Menampilkan 6 tabel sistem beserta jumlah barisnya, lalu jika salah
 * satu kartu diklik: struktur kolom (nama, tipe, wajib diisi, primary
 * key) dan pratinjau 20 baris data terbaru dari tabel tersebut.
 *
 * Nama tabel divalidasi lewat whitelist $daftar_tabel di bawah (bukan
 * langsung dari input pengguna) sehingga aman dari SQL injection lewat
 * parameter ?tabel=.
 * -----------------------------------------------------------------
 */

$daftar_tabel = [
    'evaluasi_model' => '📋',
    'hasil_prediksi' => '📋',
    'pengguna'       => '📋',
    'produk'         => '📋',
    'transaksi'      => '📋',
    'tren_penjualan' => '📋',
];

// Hitung jumlah baris tiap tabel untuk ditampilkan pada kartu.
$jumlah_baris = [];
foreach ($daftar_tabel as $nama_tabel => $ikon) {
    $jumlah_baris[$nama_tabel] = (int)$koneksi->query("SELECT COUNT(*) FROM {$nama_tabel}")->fetchColumn();
}

// Tabel yang sedang dipilih lewat klik kartu (divalidasi terhadap whitelist).
$tabel_dipilih = $_GET['tabel'] ?? '';
if (!array_key_exists($tabel_dipilih, $daftar_tabel)) {
    $tabel_dipilih = '';
}

$struktur_kolom = [];
$data_pratinjau = [];
if ($tabel_dipilih !== '') {
    $struktur_kolom = $koneksi->query("PRAGMA table_info({$tabel_dipilih})")->fetchAll();
    $data_pratinjau = $koneksi->query("SELECT * FROM {$tabel_dipilih} ORDER BY ROWID DESC LIMIT 20")->fetchAll();
}
?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">🗄️ Struktur Database Sistem</h2>
    <p class="teks-bantuan" style="margin-top:-10px;margin-bottom:18px;color:var(--abu-lembut);font-size:0.86rem;">
        Menampilkan seluruh tabel pada basis data SQLite (<code>database.db</code>) beserta jumlah baris data terkini.
    </p>

    <div class="grid-kartu-ringkasan">
        <?php foreach ($daftar_tabel as $nama_tabel => $ikon): ?>
            <a href="?hal=struktur_database&tabel=<?= urlencode($nama_tabel) ?>"
               class="kartu kartu-ringkasan"
               style="text-decoration:none;cursor:pointer;<?= $tabel_dipilih === $nama_tabel ? 'border-color:var(--emas-aksen);box-shadow:var(--bayangan-menonjol);' : '' ?>">
                <span class="ikon-kartu"><?= $ikon ?></span>
                <div>
                    <p class="label-kartu"><?= aman($nama_tabel) ?></p>
                    <p class="nilai-kartu"><?= number_format($jumlah_baris[$nama_tabel], 0, ',', '.') ?></p>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="kartu kartu-panel">
    <?php if ($tabel_dipilih === ''): ?>
        <p class="teks-kosong">Klik salah satu kartu tabel di atas untuk melihat struktur kolom dan isinya.</p>
    <?php else: ?>
        <h2 class="judul-panel">Struktur Kolom — <?= aman($tabel_dipilih) ?></h2>
        <div class="wadah-tabel" style="margin-bottom:26px;">
            <table class="tabel-data">
                <thead>
                    <tr><th>Kolom</th><th>Tipe</th><th>Wajib Diisi</th><th>Primary Key</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($struktur_kolom as $kolom): ?>
                    <tr>
                        <td><?= aman($kolom['name']) ?></td>
                        <td><?= aman($kolom['type']) ?></td>
                        <td><?= $kolom['notnull'] ? 'Ya' : 'Tidak' ?></td>
                        <td><?= $kolom['pk'] ? '🔑 Ya' : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <h2 class="judul-panel">Pratinjau Data Terbaru (maks. 20 baris)</h2>
        <?php if (empty($data_pratinjau)): ?>
            <p class="teks-kosong">Tabel ini belum memiliki data.</p>
        <?php else: ?>
            <div class="wadah-tabel">
                <table class="tabel-data">
                    <thead>
                        <tr>
                            <?php foreach (array_keys($data_pratinjau[0]) as $nama_kolom): ?>
                                <th><?= aman($nama_kolom) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data_pratinjau as $baris): ?>
                            <tr>
                                <?php foreach ($baris as $nilai): ?>
                                    <td><?= aman((string)($nilai ?? '—')) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
