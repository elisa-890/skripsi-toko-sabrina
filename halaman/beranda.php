<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti', 'pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

$bulan_ini = date('Y-m');

$ringkasan_stmt = $koneksi->prepare(
    "SELECT COUNT(*) AS jml, COALESCE(SUM(total_penjualan),0) AS total
     FROM transaksi WHERE strftime('%Y-%m', tanggal_transaksi) = :bulan"
);
$ringkasan_stmt->execute(['bulan' => $bulan_ini]);
$ringkasan_bulan = $ringkasan_stmt->fetch();

$jumlah_produk = $koneksi->query("SELECT COUNT(*) FROM produk WHERE aktif = 1")->fetchColumn();

$model_terbaik = $koneksi->query(
    "SELECT * FROM evaluasi_model WHERE model_terbaik = 1 ORDER BY dilatih_pada DESC LIMIT 1"
)->fetch();

// Prediksi ke depan (semua produk digabung per tanggal) untuk grafik ringkas.
// Catatan: "ke depan" didefinisikan relatif terhadap TANGGAL TRANSAKSI TERAKHIR
// yang tercatat di sistem (bukan tanggal kalender hari ini). Ini penting kalau
// data historis Anda berhenti beberapa waktu lalu (mis. data terakhir Februari
// 2026 tapi hari ini sudah lewat itu) — prediksi tetap relevan ditampilkan.
$tanggal_acuan = $koneksi->query("SELECT MAX(tanggal_transaksi) FROM transaksi")->fetchColumn() ?: date('Y-m-d');

$prediksi_stmt = $koneksi->prepare(
    "SELECT tanggal_prediksi, SUM(nilai_prediksi) AS total_prediksi
     FROM hasil_prediksi
     WHERE tanggal_prediksi > :acuan
     GROUP BY tanggal_prediksi
     ORDER BY tanggal_prediksi ASC
     LIMIT 7"
);
$prediksi_stmt->execute(['acuan' => $tanggal_acuan]);
$prediksi_mingguan = $prediksi_stmt->fetchAll();

$label_tanggal = array_map(fn($r) => date('d M', strtotime($r['tanggal_prediksi'])), $prediksi_mingguan);
$nilai_prediksi = array_map(fn($r) => (float)$r['total_prediksi'], $prediksi_mingguan);

// Produk dengan transaksi terbanyak bulan ini
$produk_terlaris_stmt = $koneksi->prepare(
    "SELECT p.nama_produk, SUM(t.jumlah) AS total_terjual, p.satuan
     FROM transaksi t JOIN produk p ON p.id_produk = t.id_produk
     WHERE strftime('%Y-%m', t.tanggal_transaksi) = :bulan
     GROUP BY t.id_produk ORDER BY total_terjual DESC LIMIT 5"
);
$produk_terlaris_stmt->execute(['bulan' => $bulan_ini]);
$daftar_terlaris = $produk_terlaris_stmt->fetchAll();
?>

<section class="kartu hero-toko">
    <img src="assets/images/toko_sabrina.jpg" alt="Toko Pakan Ternak Sabrina" class="hero-toko-gambar">
    <div class="hero-toko-overlay">
        <p class="hero-toko-label">Selamat datang di</p>
        <h2 class="hero-toko-judul">Toko Pakan Ternak Sabrina</h2>
        <p class="hero-toko-alamat">Desa Nagasaribu V, Kec. Lintongnihuta, Kab. Humbang Hasundutan</p>
    </div>
</section>

<section class="grid-kartu-ringkasan">
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">💰</span>
        <div>
            <p class="label-kartu">Omzet Bulan Ini</p>
            <p class="nilai-kartu">Rp <?= number_format($ringkasan_bulan['total'], 0, ',', '.') ?></p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">🧾</span>
        <div>
            <p class="label-kartu">Transaksi Bulan Ini</p>
            <p class="nilai-kartu"><?= (int)$ringkasan_bulan['jml'] ?></p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">🌾</span>
        <div>
            <p class="label-kartu">Produk Aktif</p>
            <p class="nilai-kartu"><?= (int)$jumlah_produk ?></p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">🎯</span>
        <div>
            <p class="label-kartu">Model Terbaik</p>
            <p class="nilai-kartu"><?= $model_terbaik ? aman(str_replace('_',' ',$model_terbaik['nama_model'])) : '—' ?></p>
        </div>
    </div>
</section>

<div class="grid-dua-panel">
<section class="kartu kartu-panel">
    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:4px;">
        <h2 class="judul-panel" style="margin:0;">Perkiraan Penjualan 7 Hari ke Depan</h2>
        <a href="?hal=prediksi_minggu_depan"
           class="tombol tombol-sekunder"
           style="font-size:0.82rem;padding:5px 12px;text-decoration:none;">
            📆 Lihat Prediksi Minggu Depan →
        </a>
    </div>
    <?php if (empty($prediksi_mingguan)): ?>
        <p class="teks-kosong">Belum ada hasil prediksi. Jalankan prediksi dari menu <strong>Prediksi</strong>.</p>
    <?php else: ?>
        <div class="wadah-grafik">
            <canvas id="grafikBeranda"></canvas>
        </div>
    <?php endif; ?>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Produk Terlaris Bulan Ini</h2>
    <?php if (empty($daftar_terlaris)): ?>
        <p class="teks-kosong">Belum ada data transaksi bulan ini.</p>
    <?php else: ?>
        <ul class="daftar-terlaris">
            <?php foreach ($daftar_terlaris as $i => $p): ?>
                <li>
                    <span class="peringkat">#<?= $i + 1 ?></span>
                    <span class="nama-produk-item"><?= aman($p['nama_produk']) ?></span>
                    <span class="jumlah-item"><?= number_format($p['total_terjual'], 1) ?> <?= aman($p['satuan']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
</div>

<?php if (!empty($prediksi_mingguan)): ?>
<script>
    const ctxBeranda = document.getElementById('grafikBeranda');
    new Chart(ctxBeranda, {
        type: 'line',
        data: {
            labels: <?= json_encode($label_tanggal) ?>,
            datasets: [{
                label: 'Perkiraan Total Terjual',
                data: <?= json_encode($nilai_prediksi) ?>,
                borderColor: '#2c5aa8',
                backgroundColor: 'rgba(44,90,168,0.12)',
                borderWidth: 2.5,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#2c5aa8',
                pointRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, grid: { color: '#eef1f7' } },
                x: { grid: { display: false } }
            }
        }
    });
</script>
<?php endif; ?>
