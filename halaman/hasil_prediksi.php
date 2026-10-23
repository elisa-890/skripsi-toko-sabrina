<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti', 'pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

$peran_saat_ini = $_SESSION['peran'] ?? 'pemilik';

$daftar_produk = $koneksi->query("SELECT id_produk, nama_produk, satuan FROM produk WHERE aktif = 1 ORDER BY nama_produk")->fetchAll();

// Peta id_produk -> {nama, satuan}, dipakai JS untuk menampilkan nama produk
// asli (bukan cuma ID) dan menghitung konversi otomatis ke kg di tabel hasil.
$peta_info_produk = [];
foreach ($daftar_produk as $p) {
    $peta_info_produk[$p['id_produk']] = ['nama' => $p['nama_produk'], 'satuan' => $p['satuan']];
}

$model_terbaik = $koneksi->query(
    "SELECT * FROM evaluasi_model WHERE model_terbaik = 1 ORDER BY dilatih_pada DESC LIMIT 1"
)->fetch();
?>

<?php if (!$model_terbaik): ?>
    <div class="pesan pesan-error">
        <?= $peran_saat_ini === 'peneliti'
            ? 'Model belum pernah dilatih. Buka menu <strong>Latih Model</strong> terlebih dahulu sebelum membuat prediksi.'
            : 'Belum ada model yang dilatih oleh peneliti, sehingga tabel prediksi belum tersedia. Silakan hubungi peneliti untuk melatih model terlebih dahulu.' ?>
    </div>
<?php endif; ?>

<?php if ($peran_saat_ini === 'pemilik'): ?>

<?php
// ---------------------------------------------------------------------
// MODE PEMILIK: hanya MEMBACA tabel hasil prediksi yang sudah dibuat dan
// disimpan oleh peneliti (tabel hasil_prediksi), tanpa tombol untuk
// menjalankan/melatih ulang model apa pun. Ini sesuai peran pemilik:
// menerima & membaca hasil prediksi sebagai acuan perencanaan stok.
// ---------------------------------------------------------------------
$tanggal_acuan = $koneksi->query("SELECT MAX(tanggal_transaksi) FROM transaksi")->fetchColumn() ?: date('Y-m-d');

$hasil_stmt = $koneksi->prepare(
    "SELECT hp.tanggal_prediksi, hp.nilai_prediksi, hp.model_dipakai, p.nama_produk, p.satuan
     FROM hasil_prediksi hp
     JOIN produk p ON p.id_produk = hp.id_produk
     WHERE hp.tanggal_prediksi > :acuan
     ORDER BY hp.tanggal_prediksi ASC, p.nama_produk ASC"
);
$hasil_stmt->execute(['acuan' => $tanggal_acuan]);
$daftar_hasil = $hasil_stmt->fetchAll();
?>

<?php if ($model_terbaik): ?>
<section class="kartu kartu-panel">
    <h2 class="judul-panel">Akurasi Model Saat Ini: <?= aman(str_replace('_', ' ', $model_terbaik['nama_model'])) ?></h2>
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
    </div>
</section>
<?php endif; ?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Tabel Hasil Prediksi Penjualan Pakan Ternak (kg/minggu)</h2>
    <p class="teks-bantuan" style="color:var(--abu-lembut,#6b7280);font-weight:500;">
        Gunakan angka ini sebagai acuan perencanaan dan pengadaan stok pakan ternak pada periode mendatang.
        Nilai prediksi sudah dikonversi otomatis ke satuan kilogram (kg).
    </p>
    <?php if (empty($daftar_hasil)): ?>
        <p class="teks-kosong">Belum ada hasil prediksi yang tersimpan.</p>
    <?php else: ?>
        <div class="wadah-tabel"><table class="tabel-data">
            <thead><tr><th>Produk</th><th>Tanggal</th><th>Perkiraan Terjual (kg)</th><th>Model</th></tr></thead>
            <tbody>
                <?php foreach ($daftar_hasil as $h): ?>
                <tr>
                    <td><?= aman($h['nama_produk']) ?></td>
                    <td><?= date('d M Y', strtotime($h['tanggal_prediksi'])) ?></td>
                    <td><?= number_format($h['nilai_prediksi'], 1, ',', '.') ?> kg</td>
                    <td><?= aman(str_replace('_', ' ', $h['model_dipakai'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table></div>
    <?php endif; ?>
</section>

<?php return; endif; ?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Buat Prediksi</h2>
    <div class="baris-dua-kolom">
        <div class="grup-input">
            <label for="pilih_produk_prediksi">Produk</label>
            <select id="pilih_produk_prediksi">
                <option value="semua">Semua Produk</option>
                <?php foreach ($daftar_produk as $p): ?>
                    <option value="<?= (int)$p['id_produk'] ?>"><?= aman($p['nama_produk']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grup-input">
            <label for="jumlah_hari_prediksi">Jumlah Hari ke Depan</label>
            <input type="number" id="jumlah_hari_prediksi" min="1" max="60" value="7">
        </div>
    </div>
    <button id="tombolBuatPrediksi" class="tombol tombol-utama tombol-lebar" <?= $model_terbaik ? '' : 'disabled' ?>>
        Jalankan Prediksi
    </button>
    <p id="statusPrediksi" class="teks-bantuan"></p>
</section>

<?php if ($model_terbaik): ?>
<section class="kartu kartu-panel">
    <h2 class="judul-panel">Akurasi Model Saat Ini: <?= aman(str_replace('_', ' ', $model_terbaik['nama_model'])) ?></h2>
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
    </div>
</section>
<?php endif; ?>

<section class="kartu kartu-panel" id="panelGrafikPrediksi" style="display:none;">
    <h2 class="judul-panel">Grafik Perkiraan Penjualan</h2>
    <div class="wadah-grafik">
        <canvas id="grafikPrediksi"></canvas>
    </div>
</section>

<section id="panelTabelPrediksi"></section>

<script>
const tombolBuat   = document.getElementById('tombolBuatPrediksi');
const statusEl     = document.getElementById('statusPrediksi');
const panelGrafik  = document.getElementById('panelGrafikPrediksi');
const panelTabel   = document.getElementById('panelTabelPrediksi');
let grafikAktif = null;

const paletWarna = ['#2c5aa8', '#c69a4c', '#3b82f6', '#6b8fc9', '#dfb96a'];

// Info produk (nama), dipakai JS untuk menampilkan nama produk asli (bukan
// cuma ID). Model prediksi sekarang dilatih memakai jumlah_kg (jumlah yang
// sudah dikonversi ke kilogram: 1 sak = 50 kg, 1 bungkus = 1 kg, 1 kg = 1 kg),
// jadi angka yang dikembalikan sudah langsung dalam satuan kg — tidak perlu
// dikonversi ulang di sini seperti sebelumnya.
const petaInfoProduk = <?= json_encode($peta_info_produk, JSON_UNESCAPED_UNICODE) ?>;

function namaProduk(idProduk) {
    return petaInfoProduk[idProduk]?.nama || `Produk #${idProduk}`;
}

tombolBuat?.addEventListener('click', async () => {
    const idProduk = document.getElementById('pilih_produk_prediksi').value;
    const jumlahHari = document.getElementById('jumlah_hari_prediksi').value;

    tombolBuat.disabled = true;
    statusEl.textContent = 'Memproses prediksi, mohon tunggu…';

    try {
        const respons = await fetch(`php/ambil_hasil.php?id_produk=${encodeURIComponent(idProduk)}&jumlah_hari=${encodeURIComponent(jumlahHari)}`);
        const data = await respons.json();

        if (data.status !== 'berhasil') {
            statusEl.textContent = data.pesan || 'Prediksi gagal dibuat.';
            tombolBuat.disabled = false;
            return;
        }

        statusEl.textContent = `Prediksi selesai memakai model ${data.model_dipakai.replace('_',' ')}.`;

        try {
            tampilkanGrafik(data.hasil);
            tampilkanTabel(data.hasil);
        } catch (errTampil) {
            statusEl.textContent = 'Data prediksi berhasil didapat, tapi gagal menampilkan grafik/tabel (kemungkinan pustaka Chart.js gagal dimuat). Coba muat ulang halaman (F5).';
        }
    } catch (err) {
        statusEl.textContent = 'Tidak bisa terhubung ke server, atau respons server tidak valid. Periksa koneksi internet, atau buka halaman Cek Sistem (cek_sistem.php) untuk mendiagnosis masalahnya.';
    } finally {
        tombolBuat.disabled = false;
    }
});

function tampilkanGrafik(hasilPerProduk) {
    panelGrafik.style.display = 'block';
    const semuaTanggal = new Set();
    Object.values(hasilPerProduk).forEach(daftar => daftar.forEach(d => semuaTanggal.add(d.tanggal)));
    const labelTanggal = Array.from(semuaTanggal).sort();

    const dataset = Object.entries(hasilPerProduk).map(([idProduk, daftar], i) => {
        const petaNilai = Object.fromEntries(daftar.map(d => [d.tanggal, d.nilai]));
        return {
            label: namaProduk(idProduk),
            data: labelTanggal.map(tgl => petaNilai[tgl] ?? null),
            borderColor: paletWarna[i % paletWarna.length],
            backgroundColor: paletWarna[i % paletWarna.length] + '22',
            borderWidth: 2.5,
            tension: 0.35,
            fill: false,
            pointRadius: 3,
        };
    });

    if (grafikAktif) grafikAktif.destroy();
    grafikAktif = new Chart(document.getElementById('grafikPrediksi'), {
        type: 'line',
        data: { labels: labelTanggal.map(t => new Date(t).toLocaleDateString('id-ID', {day:'2-digit', month:'short'})), datasets: dataset },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: dataset.length > 1, position: 'bottom' } },
            scales: { y: { beginAtZero: true, grid: { color: '#eef1ef' } }, x: { grid: { display: false } } }
        }
    });
}

function tampilkanTabel(hasilPerProduk) {
    let html = '<div class="kartu kartu-panel"><h2 class="judul-panel">Rincian Angka Prediksi</h2>';
    html += '<p class="teks-bantuan" style="color:var(--abu-lembut);font-weight:500;">Nilai prediksi otomatis dikonversi ke satuan kilogram (kg), sehingga hasil yang tampil selalu konsisten walau penjualan harian dicatat dalam satuan kg, bungkus, atau sak.</p>';
    html += '<div class="wadah-tabel"><table class="tabel-data"><thead><tr><th>Produk</th><th>Tanggal</th><th>Perkiraan Terjual (kg)</th></tr></thead><tbody>';
    Object.entries(hasilPerProduk).forEach(([idProduk, daftar]) => {
        daftar.forEach(d => {
            html += `<tr><td>${namaProduk(idProduk)}</td><td>${new Date(d.tanggal).toLocaleDateString('id-ID',{day:'2-digit',month:'short',year:'numeric'})}</td><td>${d.nilai.toLocaleString('id-ID')} kg</td></tr>`;
        });
    });
    html += '</tbody></table></div></div>';
    panelTabel.innerHTML = html;
}
</script>
