<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti']);
require_once __DIR__ . '/../config/koneksi_db.php';

$riwayat_evaluasi = $koneksi->query(
    "SELECT * FROM evaluasi_model ORDER BY dilatih_pada DESC LIMIT 10"
)->fetchAll();

$total_transaksi = $koneksi->query("SELECT COUNT(*) AS jml FROM transaksi")->fetch()['jml'];
?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Latih Ulang Model Prediksi</h2>
    <p class="teks-bantuan">
        Proses ini akan melatih ulang model <strong>GLM</strong> dan <strong>XGBoost</strong>
        menggunakan seluruh data transaksi terbaru (<?= (int)$total_transaksi ?> transaksi tercatat),
        lalu otomatis memilih model dengan performa terbaik (unggul di minimal 2 dari 3 metrik:
        MAE, RMSE, MAPE — mengikuti cara penentuan model terbaik pada penelitian) untuk dipakai
        pada halaman Prediksi.
    </p>
    <?php if ($total_transaksi < 30): ?>
        <div class="pesan pesan-error">
            Data transaksi masih sedikit (disarankan minimal ±30 transaksi per produk, mencakup beberapa minggu)
            agar model bisa belajar pola dengan baik. Anda tetap bisa mencoba melatih model sekarang.
        </div>
    <?php endif; ?>
    <button id="tombolLatihUlang" class="tombol tombol-utama tombol-lebar">Mulai Latih Ulang</button>
    <p id="statusLatih" class="teks-bantuan"></p>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Riwayat Evaluasi Model</h2>
    <?php if (empty($riwayat_evaluasi)): ?>
        <p class="teks-kosong">Belum pernah dilatih.</p>
    <?php else: ?>
        <div class="wadah-tabel">
        <table class="tabel-data">
            <thead>
                <tr><th>Tanggal</th><th>Model</th><th>MAE</th><th>RMSE</th><th>MAPE</th><th>Terbaik</th></tr>
            </thead>
            <tbody>
                <?php foreach ($riwayat_evaluasi as $e): ?>
                    <tr class="<?= $e['model_terbaik'] ? 'baris-terbaik' : '' ?>">
                        <td><?= date('d M Y H:i', strtotime($e['dilatih_pada'])) ?></td>
                        <td><?= aman(str_replace('_', ' ', $e['nama_model'])) ?></td>
                        <td><?= number_format($e['mae'], 3) ?></td>
                        <td><?= number_format($e['rmse'], 3) ?></td>
                        <td><?= number_format($e['mape'], 2) ?>%</td>
                        <td><?= $e['model_terbaik'] ? '✅' : '—' ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<script>
document.getElementById('tombolLatihUlang').addEventListener('click', async function() {
    const tombol = this;
    const statusEl = document.getElementById('statusLatih');
    tombol.disabled = true;
    statusEl.textContent = 'Melatih model, proses ini bisa memakan waktu beberapa saat…';

    try {
        const respons = await fetch('php/latih_ulang_proses.php', { method: 'POST' });
        const data = await respons.json();

        if (data.status === 'berhasil') {
            statusEl.textContent = `Selesai! Model terbaik: ${data.model_terbaik.replace('_',' ')}. Memuat ulang halaman…`;
            setTimeout(() => window.location.reload(), 1500);
        } else {
            statusEl.textContent = data.pesan || 'Pelatihan gagal.';
            tombol.disabled = false;
        }
    } catch (err) {
        statusEl.textContent = 'Tidak bisa terhubung ke server, atau respons server tidak valid. Periksa koneksi internet, atau buka halaman Cek Sistem (cek_sistem.php) untuk mendiagnosis masalahnya.';
        tombol.disabled = false;
    }
});
</script>
