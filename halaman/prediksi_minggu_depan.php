<?php
/**
 * halaman/prediksi_minggu_depan.php
 * -----------------------------------------------------------------
 * Menampilkan prediksi penjualan untuk MINGGU DEPAN secara agregat
 * per produk maupun total keseluruhan.
 *
 * Logika "minggu depan":
 *   - Ambil tanggal Senin pekan depan dari tanggal transaksi terakhir
 *     (bukan hari ini di kalender) agar relevan walau data historis
 *     berhenti beberapa waktu lalu.
 *   - Rentang = Senin s/d Minggu pekan depan (7 hari).
 *   - Sumber data = tabel hasil_prediksi yang sudah diisi oleh Python
 *     (prediksi.py dipanggil dari hasil_prediksi.php).
 * -----------------------------------------------------------------
 */
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti', 'pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

$peran_saat_ini = $_SESSION['peran'] ?? 'pemilik';

// ── Tentukan rentang "minggu depan" ─────────────────────────────
$tanggal_acuan = $koneksi->query(
    "SELECT MAX(tanggal_transaksi) FROM transaksi"
)->fetchColumn() ?: date('Y-m-d');

$dt_acuan = new DateTime($tanggal_acuan);
$hari_iso  = (int)$dt_acuan->format('N'); // 1=Senin … 7=Minggu

// Geser ke Senin pekan DEPAN (bukan pekan ini)
$dt_senin_depan = clone $dt_acuan;
$dt_senin_depan->modify('+' . (8 - $hari_iso) . ' days');
$dt_minggu_depan = clone $dt_senin_depan;
$dt_minggu_depan->modify('+6 days');

$tgl_mulai = $dt_senin_depan->format('Y-m-d');
$tgl_akhir  = $dt_minggu_depan->format('Y-m-d');

// ── Cek model terbaik ────────────────────────────────────────────
$model_terbaik = $koneksi->query(
    "SELECT * FROM evaluasi_model WHERE model_terbaik = 1 ORDER BY dilatih_pada DESC LIMIT 1"
)->fetch();

// ── Ambil prediksi per produk untuk rentang minggu depan ─────────
$stmt = $koneksi->prepare(
    "SELECT hp.tanggal_prediksi,
            hp.nilai_prediksi,
            hp.model_dipakai,
            p.id_produk,
            p.nama_produk,
            p.satuan,
            p.kategori
     FROM hasil_prediksi hp
     JOIN produk p ON p.id_produk = hp.id_produk
     WHERE hp.tanggal_prediksi BETWEEN :mulai AND :akhir
     ORDER BY hp.tanggal_prediksi ASC, p.nama_produk ASC"
);
$stmt->execute(['mulai' => $tgl_mulai, 'akhir' => $tgl_akhir]);
$semua_baris = $stmt->fetchAll();

// ── Agregasi: total per produk selama seminggu ───────────────────
$per_produk   = []; // [id_produk => ['nama'=>, 'satuan'=>, 'total'=>, 'harian'=>[]]]
$total_semua  = 0.0;
$per_tanggal  = []; // untuk grafik: [tanggal => total_semua_produk]

foreach ($semua_baris as $b) {
    $id  = $b['id_produk'];
    $tgl = $b['tanggal_prediksi'];
    $val = (float)$b['nilai_prediksi'];

    if (!isset($per_produk[$id])) {
        $per_produk[$id] = [
            'nama'   => $b['nama_produk'],
            'satuan' => $b['satuan'],
            'total'  => 0.0,
            'harian' => [],
        ];
    }
    $per_produk[$id]['total']          += $val;
    $per_produk[$id]['harian'][$tgl]    = $val;
    $per_tanggal[$tgl]                  = ($per_tanggal[$tgl] ?? 0.0) + $val;
    $total_semua                        += $val;
}
ksort($per_tanggal);

// ── Bandingkan dg minggu berjalan (sudah lewat) ──────────────────
// Rentang: Senin pekan INI s/d (tanggal_acuan)
$dt_senin_ini = clone $dt_acuan;
$dt_senin_ini->modify('-' . ($hari_iso - 1) . ' days');
$tgl_senin_ini = $dt_senin_ini->format('Y-m-d');

$stmt_aktual = $koneksi->prepare(
    "SELECT COALESCE(SUM(CASE WHEN t.jumlah_kg > 0 THEN t.jumlah_kg ELSE t.jumlah END), 0) AS total_kg
     FROM transaksi t
     JOIN produk p ON p.id_produk = t.id_produk
     WHERE t.tanggal_transaksi BETWEEN :mulai AND :akhir"
);
$stmt_aktual->execute(['mulai' => $tgl_senin_ini, 'akhir' => $tanggal_acuan]);
$total_minggu_berjalan = (float)($stmt_aktual->fetchColumn() ?: 0);

$selisih_persen = ($total_minggu_berjalan > 0)
    ? (($total_semua - $total_minggu_berjalan) / $total_minggu_berjalan) * 100
    : null;

// Label hari (Senin-Minggu) untuk grafik
$label_hari_grafik = [];
$nilai_hari_grafik = [];
$hari_nama_id      = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
for ($i = 0; $i < 7; $i++) {
    $dt_tmp = clone $dt_senin_depan;
    $dt_tmp->modify("+$i days");
    $tgl_tmp = $dt_tmp->format('Y-m-d');
    $label_hari_grafik[] = $hari_nama_id[$i] . ', ' . $dt_tmp->format('d M');
    $nilai_hari_grafik[] = round($per_tanggal[$tgl_tmp] ?? 0, 1);
}

// Warna per produk untuk grafik radar/bar
$palet = ['#2c5aa8','#c69a4c','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'];
?>

<?php if (!$model_terbaik): ?>
<div class="pesan pesan-error">
    Model belum dilatih. Jalankan <strong>Latih Model</strong> &amp; <strong>Prediksi</strong>
    terlebih dahulu agar data prediksi tersedia.
</div>
<?php endif; ?>

<!-- ── Kartu ringkasan cepat ─────────────────────────── -->
<section class="grid-kartu-ringkasan">
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">📅</span>
        <div>
            <p class="label-kartu">Periode Prediksi</p>
            <p class="nilai-kartu" style="font-size:1rem;">
                <?= date('d M', strtotime($tgl_mulai)) ?> – <?= date('d M Y', strtotime($tgl_akhir)) ?>
            </p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">⚖️</span>
        <div>
            <p class="label-kartu">Total Prediksi Minggu Depan</p>
            <p class="nilai-kartu"><?= number_format($total_semua, 1, ',', '.') ?> kg</p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">
            <?php if ($selisih_persen === null): ?>➖
            <?php elseif ($selisih_persen > 3): ?>📈
            <?php elseif ($selisih_persen < -3): ?>📉
            <?php else: ?>➖<?php endif; ?>
        </span>
        <div>
            <p class="label-kartu">vs Minggu Berjalan</p>
            <p class="nilai-kartu">
                <?php if ($selisih_persen === null): ?>
                    —
                <?php else: ?>
                    <?= ($selisih_persen >= 0 ? '+' : '') . number_format($selisih_persen, 1, ',', '.') ?>%
                <?php endif; ?>
            </p>
        </div>
    </div>
    <div class="kartu kartu-ringkasan">
        <span class="ikon-kartu">🎯</span>
        <div>
            <p class="label-kartu">Model</p>
            <p class="nilai-kartu"><?= $model_terbaik ? aman(str_replace('_', ' ', $model_terbaik['nama_model'])) : '—' ?></p>
        </div>
    </div>
</section>

<!-- ── Grafik total harian minggu depan ─────────────── -->
<section class="kartu kartu-panel">
    <h2 class="judul-panel">📊 Grafik Prediksi Per Hari — Minggu Depan</h2>
    <p class="teks-bantuan" style="color:var(--abu-lembut,#6b7280);margin-bottom:12px;">
        Total prediksi penjualan seluruh produk per hari, dalam satuan kg.
    </p>
    <?php if (empty($semua_baris)): ?>
        <p class="teks-kosong">
            Belum ada data prediksi untuk minggu depan.
            <?= $peran_saat_ini === 'peneliti'
                ? 'Jalankan prediksi dari menu <strong>Prediksi</strong> terlebih dahulu.'
                : 'Hubungi peneliti untuk menjalankan prediksi.' ?>
        </p>
    <?php else: ?>
        <div class="wadah-grafik">
            <canvas id="grafikMingguDepan"></canvas>
        </div>
    <?php endif; ?>
</section>

<?php if (!empty($per_produk)): ?>
<!-- ── Tabel per produk ──────────────────────────────── -->
<section class="kartu kartu-panel">
    <h2 class="judul-panel">📦 Rincian Per Produk</h2>
    <p class="teks-bantuan" style="color:var(--abu-lembut,#6b7280);margin-bottom:12px;">
        Prediksi total penjualan tiap produk selama seminggu penuh (<?= date('d M', strtotime($tgl_mulai)) ?> – <?= date('d M Y', strtotime($tgl_akhir)) ?>).
        Nilai sudah dikonversi ke kilogram (kg).
    </p>
    <div class="wadah-tabel">
    <table class="tabel-data">
        <thead>
            <tr>
                <th>#</th>
                <th>Produk</th>
                <th>Total Prediksi (kg)</th>
                <th>Rata-rata/Hari (kg)</th>
                <th>Rekomendasi Stok</th>
            </tr>
        </thead>
        <tbody>
            <?php $no = 1; foreach ($per_produk as $id => $data):
                $rata = $data['total'] / 7;
                // Stok rekomendasi = total + 15% buffer pengaman
                $stok_rekomendasi = $data['total'] * 1.15;
            ?>
            <tr>
                <td><?= $no++ ?></td>
                <td><strong><?= aman($data['nama']) ?></strong></td>
                <td><?= number_format($data['total'], 1, ',', '.') ?> kg</td>
                <td><?= number_format($rata, 1, ',', '.') ?> kg</td>
                <td>
                    <span class="lencana lencana-emas">
                        <?= number_format($stok_rekomendasi, 1, ',', '.') ?> kg
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:700;border-top:2px solid var(--biru-tua,#10203f);">
                <td colspan="2">TOTAL</td>
                <td><?= number_format($total_semua, 1, ',', '.') ?> kg</td>
                <td><?= number_format($total_semua / 7, 1, ',', '.') ?> kg</td>
                <td><span class="lencana lencana-emas"><?= number_format($total_semua * 1.15, 1, ',', '.') ?> kg</span></td>
            </tr>
        </tfoot>
    </table>
    </div>
    <p class="teks-bantuan" style="margin-top:8px;font-style:italic;">
        * Rekomendasi stok = total prediksi + 15% buffer pengaman untuk mengantisipasi lonjakan permintaan.
    </p>
</section>

<!-- ── Tabel harian lengkap per produk ──────────────── -->
<section class="kartu kartu-panel">
    <h2 class="judul-panel">📅 Rincian Harian Per Produk</h2>
    <p class="teks-bantuan" style="color:var(--abu-lembut,#6b7280);margin-bottom:12px;">
        Breakdown prediksi per hari untuk setiap produk. Berguna untuk merencanakan
        pengiriman atau pengadaan stok pada hari-hari tertentu.
    </p>
    <div class="wadah-tabel">
    <table class="tabel-data">
        <thead>
            <tr>
                <th>Produk</th>
                <?php for ($i = 0; $i < 7; $i++):
                    $dt_tmp = clone $dt_senin_depan;
                    $dt_tmp->modify("+$i days");
                ?>
                <th><?= $hari_nama_id[$i] ?><br><small><?= $dt_tmp->format('d/m') ?></small></th>
                <?php endfor; ?>
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($per_produk as $id => $data): ?>
            <tr>
                <td><strong><?= aman($data['nama']) ?></strong></td>
                <?php for ($i = 0; $i < 7; $i++):
                    $dt_tmp = clone $dt_senin_depan;
                    $dt_tmp->modify("+$i days");
                    $tgl_tmp = $dt_tmp->format('Y-m-d');
                    $val_hari = $data['harian'][$tgl_tmp] ?? 0;
                ?>
                <td><?= number_format($val_hari, 1, ',', '.') ?></td>
                <?php endfor; ?>
                <td><strong><?= number_format($data['total'], 1, ',', '.') ?> kg</strong></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="font-weight:700;">
                <td>TOTAL</td>
                <?php for ($i = 0; $i < 7; $i++):
                    $dt_tmp = clone $dt_senin_depan;
                    $dt_tmp->modify("+$i days");
                    $tgl_tmp = $dt_tmp->format('Y-m-d');
                ?>
                <td><?= number_format($per_tanggal[$tgl_tmp] ?? 0, 1, ',', '.') ?></td>
                <?php endfor; ?>
                <td><?= number_format($total_semua, 1, ',', '.') ?> kg</td>
            </tr>
        </tfoot>
    </table>
    </div>
</section>

<!-- ── Catatan & akurasi model ───────────────────────── -->
<?php if ($model_terbaik): ?>
<section class="kartu kartu-panel">
    <h2 class="judul-panel">📐 Akurasi Model: <?= aman(str_replace('_', ' ', $model_terbaik['nama_model'])) ?></h2>
    <p class="teks-bantuan" style="color:var(--abu-lembut,#6b7280);margin-bottom:12px;">
        Angka di bawah adalah metrik evaluasi terakhir. Semakin kecil nilai MAE, RMSE, dan MAPE,
        semakin akurat prediksi yang dihasilkan.
    </p>
    <div class="grid-metrik">
        <div class="metrik">
            <span class="label-metrik">MAE</span>
            <span class="nilai-metrik"><?= number_format($model_terbaik['mae'], 3) ?></span>
        </div>
        <div class="metrik">
            <span class="label-metrik">RMSE</span>
            <span class="nilai-metrik"><?= number_format($model_terbaik['rmse'], 3) ?></span>
        </div>
        <div class="metrik">
            <span class="label-metrik">MAPE</span>
            <span class="nilai-metrik"><?= number_format($model_terbaik['mape'], 2) ?>%</span>
        </div>
        <div class="metrik">
            <span class="label-metrik">Data Latih</span>
            <span class="nilai-metrik"><?= number_format($model_terbaik['jumlah_data']) ?> baris</span>
        </div>
    </div>
    <p class="teks-bantuan" style="margin-top:12px;font-style:italic;">
        ⚠️ Prediksi bersifat estimasi berdasarkan pola historis. Faktor eksternal
        (hari raya, cuaca, pasokan pakan) dapat memengaruhi penjualan aktual.
        Gunakan angka ini sebagai <strong>acuan</strong>, bukan kepastian.
    </p>
</section>
<?php endif; ?>

<script>
const ctxMinggu = document.getElementById('grafikMingguDepan');
if (ctxMinggu) {
    // Warna gradient per batang
    new Chart(ctxMinggu, {
        type: 'bar',
        data: {
            labels: <?= json_encode($label_hari_grafik) ?>,
            datasets: [{
                label: 'Prediksi Total (kg)',
                data: <?= json_encode($nilai_hari_grafik) ?>,
                backgroundColor: [
                    'rgba(44,90,168,0.75)',
                    'rgba(198,154,76,0.75)',
                    'rgba(59,130,246,0.75)',
                    'rgba(16,185,129,0.75)',
                    'rgba(245,158,11,0.75)',
                    'rgba(239,68,68,0.75)',
                    'rgba(139,92,246,0.75)',
                ],
                borderColor: [
                    '#2c5aa8','#c69a4c','#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6'
                ],
                borderWidth: 2,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => ` ${ctx.parsed.y.toLocaleString('id-ID', {minimumFractionDigits:1})} kg`
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#eef1f7' },
                    ticks: {
                        callback: v => v.toLocaleString('id-ID') + ' kg'
                    }
                },
                x: { grid: { display: false } }
            }
        }
    });
}
</script>
<?php endif; ?>
