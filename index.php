<?php
require_once __DIR__ . '/config/pengaturan.php';
wajib_login();

$peran_saat_ini = $_SESSION['peran'] ?? 'pemilik';

// Router halaman sederhana lewat parameter ?hal=, sekaligus peta peran yang
// berhak membuka tiap halaman. Sistem ini punya 2 peran dengan tugas
// berbeda:
//   - peneliti : unggah dataset, preprocessing, feature engineering, latih
//                & evaluasi model GLM/XGBoost, analisis hasil penelitian.
//   - pemilik  : membaca tabel hasil prediksi utk perencanaan stok, serta
//                mencatat transaksi penjualan sehari-hari.
$halaman_diizinkan = [
    'beranda'         => ['berkas' => 'halaman/beranda.php',         'peran' => ['peneliti', 'pemilik']],
    'input_transaksi' => ['berkas' => 'halaman/input_transaksi.php', 'peran' => ['pemilik']],
    'kelola_produk'   => ['berkas' => 'halaman/kelola_produk.php',   'peran' => ['pemilik']],
    'impor_data'      => ['berkas' => 'halaman/impor_data.php',      'peran' => ['peneliti']],
    'kelola_data'     => ['berkas' => 'halaman/kelola_data.php',     'peran' => ['peneliti']],
    'struktur_database' => ['berkas' => 'halaman/struktur_database.php', 'peran' => ['peneliti']],
    'riwayat'         => ['berkas' => 'halaman/riwayat.php',         'peran' => ['pemilik']],
    'laporan'         => ['berkas' => 'halaman/laporan.php',         'peran' => ['pemilik']],
    'edit_transaksi'  => ['berkas' => 'halaman/edit_transaksi.php',  'peran' => ['pemilik']],
    'hasil_prediksi'          => ['berkas' => 'halaman/hasil_prediksi.php',          'peran' => ['peneliti', 'pemilik']],
    'prediksi_minggu_depan'   => ['berkas' => 'halaman/prediksi_minggu_depan.php',   'peran' => ['peneliti', 'pemilik']],
    'tren_penjualan'          => ['berkas' => 'halaman/tren_penjualan.php',           'peran' => ['peneliti', 'pemilik']],
    'latih_ulang'     => ['berkas' => 'halaman/latih_ulang.php',     'peran' => ['peneliti']],
    'ganti_sandi'     => ['berkas' => 'halaman/ganti_sandi.php',     'peran' => ['peneliti', 'pemilik']],
];

$hal_diminta = $_GET['hal'] ?? beranda_untuk_peran($peran_saat_ini);
if (
    !array_key_exists($hal_diminta, $halaman_diizinkan)
    || !in_array($peran_saat_ini, $halaman_diizinkan[$hal_diminta]['peran'], true)
) {
    // Halaman tidak ada, atau bukan hak peran ini -> alihkan ke beranda
    // sesuai peran, bukan ke halaman 'beranda' yang sama untuk semua orang.
    $hal_diminta = beranda_untuk_peran($peran_saat_ini);
}
$berkas_halaman = $halaman_diizinkan[$hal_diminta]['berkas'];

// Muat konten halaman dengan aman: kalau ada error di satu halaman,
// tampilkan pesan yang rapi tanpa merusak seluruh tampilan (header,
// menu navigasi, dsb tetap tampil normal).
$pesan_akses_ditolak = $_SESSION['pesan_akses_ditolak'] ?? '';
unset($_SESSION['pesan_akses_ditolak']);

ob_start();
try {
    require __DIR__ . '/' . $berkas_halaman;
    $konten_halaman = ob_get_clean();
} catch (Throwable $e) {
    ob_end_clean();
    error_log('[Dashboard Toko Sabrina] ' . $e->getMessage());
    $konten_halaman = '<div class="pesan pesan-error">⚠️ Terjadi kesalahan saat memuat halaman ini. '
        . '<a href="cek_sistem.php" style="text-decoration:underline;color:inherit;">Cek Sistem</a> untuk melihat penyebabnya.</div>';
}
if ($pesan_akses_ditolak) {
    $konten_halaman = '<div class="pesan pesan-error">🔒 ' . aman($pesan_akses_ditolak) . '</div>' . $konten_halaman;
}

$judul_halaman = [
    'beranda'         => 'Beranda',
    'input_transaksi' => 'Input Transaksi',
    'kelola_produk'   => 'Kelola Produk',
    'impor_data'      => 'Impor Data Excel',
    'kelola_data'     => 'Kelola Data',
    'struktur_database' => 'Struktur Database',
    'riwayat'         => 'Riwayat Transaksi',
    'laporan'         => 'Laporan',
    'edit_transaksi'  => 'Edit Transaksi',
    'hasil_prediksi'         => 'Hasil Prediksi',
    'prediksi_minggu_depan'  => 'Prediksi Minggu Depan',
    'tren_penjualan'         => 'Tren Penjualan',
    'latih_ulang'     => 'Latih Ulang Model',
    'ganti_sandi'     => 'Ganti Kata Sandi',
][$hal_diminta];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<title><?= aman($judul_halaman) ?> — <?= aman(NAMA_APLIKASI) ?></title>
<link rel="manifest" href="assets/manifest.json">
<meta name="theme-color" content="#10203f">
<link rel="icon" type="image/png" href="assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="assets/icons/icon-192.png">
<link rel="stylesheet" href="assets/css/style.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body>

<?php
$semua_item_menu = [
    ['kunci' => 'beranda',         'url' => 'beranda',         'ikon' => '🏠', 'label' => 'Beranda'],
    ['kunci' => 'impor_data',      'url' => 'impor_data',      'ikon' => '📥', 'label' => 'Impor Data'],
    ['kunci' => 'kelola_data',     'url' => 'kelola_data',     'ikon' => '🗂️', 'label' => 'Kelola Data'],
    ['kunci' => 'struktur_database', 'url' => 'struktur_database', 'ikon' => '🗄️', 'label' => 'Struktur Database'],
    ['kunci' => 'latih_ulang',     'url' => 'latih_ulang',     'ikon' => '🧠', 'label' => 'Latih Model'],
    ['kunci' => 'hasil_prediksi',         'url' => 'hasil_prediksi',         'ikon' => '🔮', 'label' => 'Prediksi'],
    ['kunci' => 'prediksi_minggu_depan',  'url' => 'prediksi_minggu_depan',  'ikon' => '📆', 'label' => 'Prediksi Minggu Depan'],
    ['kunci' => 'tren_penjualan',         'url' => 'tren_penjualan',         'ikon' => '🔀', 'label' => 'Tren Penjualan'],
    ['kunci' => 'input_transaksi', 'url' => 'input_transaksi', 'ikon' => '➕', 'label' => 'Input Transaksi'],
    ['kunci' => 'kelola_produk',   'url' => 'kelola_produk',   'ikon' => '📦', 'label' => 'Kelola Produk'],
    ['kunci' => 'riwayat',         'url' => 'riwayat',         'ikon' => '📋', 'label' => 'Riwayat'],
    ['kunci' => 'laporan',         'url' => 'laporan',         'ikon' => '📊', 'label' => 'Laporan'],
];
// Hanya tampilkan menu yang memang boleh diakses oleh peran pengguna yang
// sedang login (peneliti hanya lihat menu risetnya, pemilik hanya lihat
// menu operasional tokonya).
$item_menu = array_values(array_filter($semua_item_menu, function ($m) use ($halaman_diizinkan, $peran_saat_ini) {
    return in_array($peran_saat_ini, $halaman_diizinkan[$m['kunci']]['peran'], true);
}));
$item_menu_bawah = ['beranda', 'impor_data', 'latih_ulang', 'hasil_prediksi', 'prediksi_minggu_depan', 'input_transaksi', 'riwayat', 'laporan'];
?>

<div class="app-shell">

    <aside class="sidebar" id="sidebarAplikasi">
        <div class="sidebar-brand">
            <img src="assets/icons/icon-192.png" alt="Logo Toko Sabrina" class="lingkaran-logo lingkaran-logo-kecil">
            <div>
                <p class="sidebar-brand-nama">Toko Sabrina</p>
                <p class="sidebar-brand-sub">Dashboard Penjualan</p>
            </div>
        </div>

        <nav class="sidebar-menu">
            <?php foreach ($item_menu as $m): ?>
                <a href="?hal=<?= $m['url'] ?>" class="sidebar-link <?= $hal_diminta === $m['kunci'] ? 'aktif' : '' ?>">
                    <span class="sidebar-link-ikon"><?= $m['ikon'] ?></span>
                    <span><?= $m['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="sidebar-footer">
            <a href="?hal=ganti_sandi" class="sidebar-link <?= $hal_diminta === 'ganti_sandi' ? 'aktif' : '' ?>">
                <span class="sidebar-link-ikon">🔑</span><span>Ganti Sandi</span>
            </a>
            <a href="php/logout.php" class="sidebar-link sidebar-link-keluar">
                <span class="sidebar-link-ikon">⏻</span><span>Keluar</span>
            </a>
        </div>
    </aside>

    <div class="app-utama">
        <header class="header-atas">
            <button type="button" class="tombol-hamburger" id="tombolHamburger" aria-label="Buka menu">☰</button>
            <div class="header-judul">
                <h1 class="judul-app"><?= aman($judul_halaman) ?></h1>
                <p class="subjudul-app">Toko Pakan Ternak Sabrina</p>
            </div>
            <div class="header-kanan">
                <a href="?hal=ganti_sandi" class="nama-pengguna-aktif" style="text-decoration:none;color:inherit;" title="Ganti kata sandi">
                    <?= aman($_SESSION['nama_lengkap']) ?> <small style="opacity:.7;font-weight:500;">(<?= $peran_saat_ini === 'peneliti' ? 'Peneliti' : 'Pemilik' ?>)</small>
                </a>
                <a href="php/logout.php" class="tombol-keluar" title="Keluar">⏻</a>
            </div>
        </header>


        <main class="konten-utama">
            <?= $konten_halaman ?>
        </main>

        <footer class="footer-app">
            <p>&copy; <?= date('Y') ?> Toko Pakan Ternak Sabrina — Desa Nagasaribu V, Kec. Lintongnihuta, Kab. Humbang Hasundutan</p>
        </footer>
    </div>
</div>

<nav class="navigasi-bawah">
    <?php foreach ($item_menu as $m): if (!in_array($m['kunci'], $item_menu_bawah, true)) continue; ?>
        <a href="?hal=<?= $m['url'] ?>" class="item-nav <?= $hal_diminta === $m['kunci'] ? 'aktif' : '' ?>">
            <span class="ikon-nav"><?= $m['ikon'] ?></span><span><?= $m['label'] === 'Input Transaksi' ? 'Input' : ($m['label'] === 'Impor Data' ? 'Impor' : ($m['label'] === 'Latih Model' ? 'Latih' : $m['label'])) ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<div class="overlay-sidebar" id="overlaySidebar"></div>

<script src="assets/js/script.js"></script>
<script>
(function () {
    const sidebar = document.getElementById('sidebarAplikasi');
    const overlay = document.getElementById('overlaySidebar');
    const tombol = document.getElementById('tombolHamburger');
    if (!sidebar || !overlay || !tombol) return;
    const buka = () => { sidebar.classList.add('terbuka'); overlay.classList.add('terlihat'); };
    const tutup = () => { sidebar.classList.remove('terbuka'); overlay.classList.remove('terlihat'); };
    tombol.addEventListener('click', () => sidebar.classList.contains('terbuka') ? tutup() : buka());
    overlay.addEventListener('click', tutup);
})();
</script>
</body>
</html>
