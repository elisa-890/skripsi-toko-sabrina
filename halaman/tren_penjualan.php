<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti', 'pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';
require_once __DIR__ . '/../config/hitung_tren.php';

// Hitung ulang di sini juga (bukan hanya saat transaksi disimpan) supaya
// halaman ini selalu menampilkan data terbaru walau dibuka langsung,
// mis. setelah impor data lewat jalur lain atau database dipulihkan dari
// cadangan (backup).
hitung_ulang_tren_penjualan($koneksi);

$daftar_tren = $koneksi->query(
    "SELECT minggu_mulai, total_kg_terjual, arah_tren, persentase_perubahan
     FROM tren_penjualan
     ORDER BY minggu_mulai DESC
     LIMIT 26"
)->fetchAll();

// Untuk grafik: urut naik (lama -> baru), maksimal 12 minggu terakhir
$untuk_grafik = array_slice(array_reverse($daftar_tren), -12);
$label_minggu = array_map(fn($r) => date('d M', strtotime($r['minggu_mulai'])), $untuk_grafik);
$nilai_mingguan = array_map(fn($r) => (float)$r['total_kg_terjual'], $untuk_grafik);

$tren_terakhir = $daftar_tren[0] ?? null;
$total_minggu = count($daftar_tren);

function lencana_arah(string $arah): string
{
    $kelas = $arah === 'Naik' ? 'lencana-sukses' : ($arah === 'Turun' ? 'lencana-bahaya' : 'lencana-emas');
    $simbol = $arah === 'Naik' ? '▲' : ($arah === 'Turun' ? '▼' : '▬');
    return '<span class="lencana ' . $kelas . '">' . $simbol . ' ' . aman($arah) . '</span>';
}
?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">🔀 Alur Data: Penjualan → Tren</h2>
    <p class="teks-kosong" style="text-align:left;padding:0 0 4px;">
        Setiap transaksi yang dicatat atau diimpor otomatis diagregasi menjadi data mingguan di bawah ini,
        mengikuti metodologi pada Bab III/IV skripsi (agregasi Senin&ndash;Minggu, kategori Pakan Ayam/Babi/Bebek).
        Data inilah yang menjadi dasar tabel <code>tren_penjualan</code> di database.
    </p>
</section>

<section class="grid-kartu-ringkasan">
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">🗓️</span>
        <div>
            <p class="label-kartu">Minggu Tercatat</p>
            <p class="nilai-kartu"><?= (int)$total_minggu ?></p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">⚖️</span>
        <div>
            <p class="label-kartu">Total kg Minggu Terakhir</p>
            <p class="nilai-kartu"><?= $tren_terakhir ? number_format($tren_terakhir['total_kg_terjual'], 1, ',', '.') . ' kg' : '—' ?></p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu"><?= $tren_terakhir && $tren_terakhir['arah_tren'] === 'Naik' ? '📈' : ($tren_terakhir && $tren_terakhir['arah_tren'] === 'Turun' ? '📉' : '➖') ?></span>
        <div>
            <p class="label-kartu">Arah Tren Terakhir</p>
            <p class="nilai-kartu"><?= $tren_terakhir ? aman($tren_terakhir['arah_tren']) : '—' ?></p>
        </div>
    </div>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Grafik Tren Penjualan Mingguan (kg)</h2>
    <?php if (empty($untuk_grafik)): ?>
        <p class="teks-kosong">Belum cukup data transaksi untuk membentuk tren mingguan. Catat atau impor data penjualan terlebih dahulu.</p>
    <?php else: ?>
        <div class="wadah-grafik">
            <canvas id="grafikTren"></canvas>
        </div>
    <?php endif; ?>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Tabel tren_penjualan (26 Minggu Terakhir)</h2>
    <?php if (empty($daftar_tren)): ?>
        <p class="teks-kosong">Belum ada data tren.</p>
    <?php else: ?>
        <div class="wadah-tabel">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>Minggu Mulai</th>
                    <th>Total Terjual (kg)</th>
                    <th>Perubahan</th>
                    <th>Arah Tren</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_tren as $t): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($t['minggu_mulai'])) ?></td>
                        <td><?= number_format($t['total_kg_terjual'], 1, ',', '.') ?> kg</td>
                        <td><?= $t['persentase_perubahan'] !== null ? number_format($t['persentase_perubahan'], 1, ',', '.') . '%' : '—' ?></td>
                        <td><?= lencana_arah($t['arah_tren']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($untuk_grafik)): ?>
<script>
    const ctxTren = document.getElementById('grafikTren');
    new Chart(ctxTren, {
        type: 'line',
        data: {
            labels: <?= json_encode($label_minggu) ?>,
            datasets: [{
                label: 'Total Terjual per Minggu (kg)',
                data: <?= json_encode($nilai_mingguan) ?>,
                borderColor: '#c69a4c',
                backgroundColor: 'rgba(198,154,76,0.15)',
                borderWidth: 2.5,
                tension: 0.3,
                fill: true,
                pointBackgroundColor: '#10203f',
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
