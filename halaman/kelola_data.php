<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti']);
require_once __DIR__ . '/../config/koneksi_db.php';

$total_transaksi = $koneksi->query("SELECT COUNT(*) FROM transaksi")->fetchColumn();

// Daftar sesi impor (batch), dikelompokkan, hanya yang dari impor Excel (id_batch_impor tidak null)
$daftar_batch = $koneksi->query(
    "SELECT id_batch_impor, COUNT(*) AS jumlah_baris,
            MIN(tanggal_transaksi) AS tanggal_awal, MAX(tanggal_transaksi) AS tanggal_akhir,
            MIN(dibuat_pada) AS diimpor_pada
     FROM transaksi
     WHERE id_batch_impor IS NOT NULL
     GROUP BY id_batch_impor
     ORDER BY diimpor_pada DESC"
)->fetchAll();

$jumlah_input_manual = $koneksi->query(
    "SELECT COUNT(*) FROM transaksi WHERE id_batch_impor IS NULL"
)->fetchColumn();

$pesan_sukses = $_SESSION['pesan_kelola_sukses'] ?? '';
unset($_SESSION['pesan_kelola_sukses']);
$pesan_error = $_SESSION['pesan_kelola_error'] ?? '';
unset($_SESSION['pesan_kelola_error']);
?>

<?php if ($pesan_sukses): ?>
    <div class="pesan pesan-sukses"><?= aman($pesan_sukses) ?></div>
<?php endif; ?>
<?php if ($pesan_error): ?>
    <div class="pesan pesan-error"><?= aman($pesan_error) ?></div>
<?php endif; ?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Ringkasan Data Transaksi</h2>
    <div class="grid-metrik" style="grid-template-columns:repeat(2,1fr);">
        <div class="metrik">
            <span class="label-metrik">Total Transaksi</span>
            <span class="nilai-metrik"><?= (int)$total_transaksi ?></span>
        </div>
        <div class="metrik">
            <span class="label-metrik">Input Manual</span>
            <span class="nilai-metrik"><?= (int)$jumlah_input_manual ?></span>
        </div>
    </div>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Riwayat Sesi Impor Excel</h2>
    <?php if (empty($daftar_batch)): ?>
        <p class="teks-kosong">Belum ada data yang diimpor lewat Excel.</p>
    <?php else: ?>
        <div class="wadah-tabel">
        <table class="tabel-data">
            <thead>
                <tr><th>Diimpor Pada</th><th>Rentang Tanggal Data</th><th>Jumlah Baris</th><th>Aksi</th></tr>
            </thead>
            <tbody>
                <?php foreach ($daftar_batch as $b): ?>
                    <tr>
                        <td><?= date('d M Y H:i', strtotime($b['diimpor_pada'])) ?></td>
                        <td><?= date('d M Y', strtotime($b['tanggal_awal'])) ?> – <?= date('d M Y', strtotime($b['tanggal_akhir'])) ?></td>
                        <td><?= (int)$b['jumlah_baris'] ?></td>
                        <td>
                            <form action="php/hapus_data.php" method="POST" onsubmit="return confirm('Hapus ' + <?= (int)$b['jumlah_baris'] ?> + ' baris transaksi dari sesi impor ini? Tindakan ini tidak bisa dibatalkan.');" style="margin:0;">
                                <input type="hidden" name="aksi" value="hapus_batch">
                                <input type="hidden" name="id_batch_impor" value="<?= aman($b['id_batch_impor']) ?>">
                                <button type="submit" class="tombol" style="background:var(--merah-latar);color:var(--merah-galat);padding:8px 14px;font-size:0.8rem;">Hapus</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<section class="kartu kartu-panel" style="border-color:#f3cfcc;">
    <h2 class="judul-panel" style="color:var(--merah-galat);">⚠️ Zona Berbahaya</h2>
    <p class="teks-bantuan" style="color:var(--abu-lembut);font-weight:500;">
        Menghapus SEMUA data transaksi (baik input manual maupun hasil impor Excel), termasuk
        riwayat hasil prediksi dan evaluasi model. Cocok dipakai untuk membersihkan data uji coba
        sebelum mulai memakai data sungguhan. Tindakan ini <strong>tidak bisa dibatalkan</strong>.
    </p>
    <form action="php/hapus_data.php" method="POST" onsubmit="return submitHapusSemua(event)">
        <input type="hidden" name="aksi" value="hapus_semua">
        <div class="grup-input">
            <label for="konfirmasi_hapus">Ketik <strong>HAPUS SEMUA DATA</strong> untuk mengonfirmasi</label>
            <input type="text" id="konfirmasi_hapus" name="konfirmasi_hapus" autocomplete="off" placeholder="HAPUS SEMUA DATA">
        </div>
        <button type="submit" class="tombol tombol-lebar" style="background:var(--merah-galat);color:#fff;margin-top:12px;">
            Hapus Semua Data Transaksi
        </button>
    </form>
</section>

<script>
function submitHapusSemua(event) {
    const nilai = document.getElementById('konfirmasi_hapus').value.trim();
    if (nilai !== 'HAPUS SEMUA DATA') {
        alert('Ketik persis "HAPUS SEMUA DATA" (huruf besar) untuk mengonfirmasi.');
        event.preventDefault();
        return false;
    }
    return confirm('Anda yakin? Semua data transaksi, hasil prediksi, dan riwayat evaluasi model akan dihapus permanen.');
}
</script>
