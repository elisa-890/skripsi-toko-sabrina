<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti']);
require_once __DIR__ . '/../config/koneksi_db.php';

$jumlah_produk = $koneksi->query("SELECT COUNT(*) FROM produk WHERE aktif = 1")->fetchColumn();

$ringkasan_impor = $_SESSION['ringkasan_impor'] ?? null;
unset($_SESSION['ringkasan_impor']);
$pesan_error = $_SESSION['pesan_impor_error'] ?? '';
unset($_SESSION['pesan_impor_error']);
?>

<?php if ($pesan_error): ?>
    <div class="pesan pesan-error"><?= aman($pesan_error) ?></div>
<?php endif; ?>

<?php if ($ringkasan_impor): ?>
    <section class="kartu kartu-panel">
        <h2 class="judul-panel">Hasil Impor Terakhir</h2>
        <div class="grid-metrik" style="grid-template-columns:repeat(4,1fr);">
            <div class="metrik">
                <span class="label-metrik">Total Baris</span>
                <span class="nilai-metrik"><?= (int)$ringkasan_impor['total'] ?></span>
            </div>
            <div class="metrik">
                <span class="label-metrik">Berhasil</span>
                <span class="nilai-metrik" style="color:#1b3766;"><?= (int)$ringkasan_impor['berhasil'] ?></span>
            </div>
            <div class="metrik">
                <span class="label-metrik">Duplikat</span>
                <span class="nilai-metrik" style="color:#6c757d;"><?= (int)($ringkasan_impor['dilewati_duplikat'] ?? 0) ?></span>
            </div>
            <div class="metrik">
                <span class="label-metrik">Gagal</span>
                <span class="nilai-metrik" style="color:#b3261e;"><?= (int)$ringkasan_impor['gagal'] ?></span>
            </div>
        </div>

        <?php if (!empty($ringkasan_impor['produk_baru'])): ?>
            <p class="teks-bantuan" style="margin-top:10px;">
                ✨ <?= (int)$ringkasan_impor['produk_baru'] ?> produk baru otomatis ditambahkan ke sistem karena belum pernah terdaftar sebelumnya.
            </p>
        <?php endif; ?>

        <?php if (!empty($ringkasan_impor['dilewati_duplikat'])): ?>
            <p class="teks-bantuan" style="color:var(--abu-lembut);font-weight:500;margin-top:10px;">
                ℹ️ <?= (int)$ringkasan_impor['dilewati_duplikat'] ?> baris dilewati karena persis sama (produk, tanggal, jumlah, satuan, harga & total) dengan data yang sudah tersimpan -- biasanya karena file yang sama tidak sengaja diimpor dua kali.
            </p>
        <?php endif; ?>

        <?php if (!empty($ringkasan_impor['id_batch_impor'])): ?>
            <p class="teks-bantuan" style="margin-top:10px;">
                ✅ Kode sesi impor: <code><?= aman($ringkasan_impor['id_batch_impor']) ?></code> — kalau ini cuma percobaan, buka <a href="?hal=kelola_data" style="color:var(--navy-utama);font-weight:700;">menu Kelola Data</a> untuk menghapusnya.
            </p>
        <?php endif; ?>

        <?php if (!empty($ringkasan_impor['daftar_error'])): ?>
            <h3 style="font-size:0.95rem;margin-top:18px;">Rincian Baris Gagal</h3>
            <div class="wadah-tabel">
            <table class="tabel-data">
                <thead><tr><th>Baris ke-</th><th>Alasan Gagal</th></tr></thead>
                <tbody>
                    <?php foreach ($ringkasan_impor['daftar_error'] as $err): ?>
                        <tr><td><?= (int)$err['baris'] ?></td><td><?= aman($err['alasan']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Unggah Data Transaksi dari Excel</h2>
    <p class="teks-bantuan" style="color:var(--abu-lembut);font-weight:500;">
        Cocok untuk memasukkan data historis (misalnya penjualan 2024–2026) sekaligus tanpa input satu per satu.
        Produk baru akan <strong>otomatis dibuat</strong> jika belum terdaftar.
    </p>

    <a href="assets/template/template_impor_transaksi.xlsx" download class="tombol" style="background:var(--navy-pucat);color:var(--navy-tua);display:block;text-align:center;text-decoration:none;margin:14px 0;">
        ⬇️ Unduh Template Excel
    </a>
    <a href="?hal=kelola_data" style="display:block;text-align:center;font-size:0.82rem;color:var(--abu-lembut);font-weight:600;margin-bottom:14px;">
        🗑️ Kelola / hapus data hasil uji coba →
    </a>

    <form action="php/proses_impor_excel.php" method="POST" enctype="multipart/form-data" class="form-transaksi">
        <div class="grup-input">
            <label for="berkas_excel">Pilih File (.xlsx)</label>
            <input type="file" id="berkas_excel" name="berkas_excel" accept=".xlsx" required>
        </div>
        <button type="submit" class="tombol tombol-utama tombol-lebar">Unggah &amp; Impor</button>
    </form>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Format File yang Didukung</h2>
    <p class="teks-bantuan" style="color:var(--abu-lembut);font-weight:500;">
        Sistem otomatis mendeteksi salah satu dari dua format kolom berikut (urutan kolom bebas):
    </p>

    <h3 style="font-size:0.88rem;margin-top:14px;color:var(--navy-tua);">Format 1 — Template Bawaan</h3>
    <div class="wadah-tabel">
    <table class="tabel-data">
        <thead><tr><th>Kolom</th><th>Wajib?</th></tr></thead>
        <tbody>
            <tr><td>nama_produk</td><td>Ya</td></tr>
            <tr><td>tanggal</td><td>Ya (format YYYY-MM-DD atau DD/MM/YYYY)</td></tr>
            <tr><td>jumlah</td><td>Ya</td></tr>
            <tr><td>satuan</td><td>Tidak (default "pcs")</td></tr>
            <tr><td>harga_satuan, catatan</td><td>Tidak</td></tr>
        </tbody>
    </table>
    </div>

    <h3 style="font-size:0.88rem;margin-top:16px;color:var(--navy-tua);">Format 2 — Laporan Toko (mis. rekap penjualan)</h3>
    <div class="wadah-tabel">
    <table class="tabel-data">
        <thead><tr><th>Kolom</th><th>Wajib?</th></tr></thead>
        <tbody>
            <tr><td>Tanggal</td><td>Ya (format DD/MM/YYYY)</td></tr>
            <tr><td>Nama Produk</td><td>Ya</td></tr>
            <tr><td>Jenis Pakan</td><td>Tidak (dipakai sebagai kategori produk baru)</td></tr>
            <tr><td>Jumlah Terjual</td><td>Ya — format gabungan, contoh: <code>"15 sak"</code></td></tr>
            <tr><td>Harga Satuan (Rp)</td><td>Tidak</td></tr>
            <tr><td>Total Penjualan (Rp)</td><td>Tidak (dihitung otomatis jika kosong)</td></tr>
        </tbody>
    </table>
    </div>

    <p class="teks-bantuan" style="margin-top:14px;">
        Saat ini ada <?= (int)$jumlah_produk ?> produk aktif terdaftar di sistem.
        Lihat & kelola lewat menu <a href="?hal=kelola_produk" style="color:var(--navy-utama);font-weight:700;">Kelola Produk</a>.
    </p>
</section>
