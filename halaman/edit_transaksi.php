<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

$id_transaksi = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id_transaksi) {
    echo '<div class="pesan pesan-error">ID transaksi tidak valid.</div>';
    return;
}

$stmt = $koneksi->prepare(
    "SELECT t.*, p.nama_produk, p.satuan AS satuan_produk
     FROM transaksi t JOIN produk p ON p.id_produk = t.id_produk
     WHERE t.id_transaksi = :id"
);
$stmt->execute(['id' => $id_transaksi]);
$transaksi = $stmt->fetch();

if (!$transaksi) {
    echo '<div class="pesan pesan-error">Transaksi tidak ditemukan. Mungkin sudah dihapus.</div>';
    return;
}

// Satuan transaksi asli (jika kosong/data lama, pakai satuan tetap produk sebagai perkiraan)
$satuan_transaksi_sekarang = strtolower($transaksi['satuan_transaksi'] ?: $transaksi['satuan_produk']);
if (!in_array($satuan_transaksi_sekarang, SATUAN_DIIZINKAN, true)) {
    $satuan_transaksi_sekarang = 'kg';
}

$daftar_produk = $koneksi->query("SELECT id_produk, kode_produk, nama_produk, satuan FROM produk ORDER BY nama_produk")->fetchAll();
$peta_satuan_produk = [];
foreach ($daftar_produk as $p) {
    $peta_satuan_produk[$p['id_produk']] = $p['satuan'];
}

$pesan_error = $_SESSION['pesan_edit_error'] ?? '';
unset($_SESSION['pesan_edit_error']);
?>

<?php if ($pesan_error): ?>
    <div class="pesan pesan-error"><?= aman($pesan_error) ?></div>
<?php endif; ?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Edit Transaksi #<?= (int)$transaksi['id_transaksi'] ?></h2>

    <form action="php/edit_transaksi_proses.php" method="POST" class="form-transaksi">
        <input type="hidden" name="id_transaksi" value="<?= (int)$transaksi['id_transaksi'] ?>">

        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label for="kode_produk_pilih">Kode Produk</label>
                <select id="kode_produk_pilih" required>
                    <?php foreach ($daftar_produk as $p): ?>
                        <option value="<?= (int)$p['id_produk'] ?>" <?= $p['id_produk'] == $transaksi['id_produk'] ? 'selected' : '' ?>>
                            <?= aman($p['kode_produk']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grup-input">
                <label for="nama_produk_pilih">Nama Produk</label>
                <select id="nama_produk_pilih" required>
                    <?php foreach ($daftar_produk as $p): ?>
                        <option value="<?= (int)$p['id_produk'] ?>" <?= $p['id_produk'] == $transaksi['id_produk'] ? 'selected' : '' ?>>
                            <?= aman($p['nama_produk']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <input type="hidden" name="id_produk" id="id_produk" value="<?= (int)$transaksi['id_produk'] ?>" required>
        <p class="teks-bantuan" style="color:var(--abu-lembut);">
            Kode dan Nama saling terhubung — pilih salah satu, yang lain otomatis ikut menyesuaikan produk yang sama.
        </p>

        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label for="jumlah">Jumlah</label>
                <input type="number" step="0.01" min="0.01" name="jumlah" id="jumlah" value="<?= aman((string)$transaksi['jumlah']) ?>" required>
            </div>
            <div class="grup-input">
                <label for="satuan_transaksi">Satuan</label>
                <select name="satuan_transaksi" id="satuan_transaksi" required>
                    <option value="kg" <?= $satuan_transaksi_sekarang === 'kg' ? 'selected' : '' ?>>Kg</option>
                    <option value="bungkus" <?= $satuan_transaksi_sekarang === 'bungkus' ? 'selected' : '' ?>>Bungkus</option>
                    <option value="sak" <?= $satuan_transaksi_sekarang === 'sak' ? 'selected' : '' ?>>Sak</option>
                </select>
            </div>
        </div>

        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label for="harga_satuan">Harga per Satuan (Rp)</label>
                <input type="number" step="0.01" min="0" name="harga_satuan" id="harga_satuan" value="<?= aman((string)$transaksi['harga_satuan']) ?>">
            </div>
            <div class="grup-input">
                <label for="tanggal_transaksi">Tanggal</label>
                <input type="date" name="tanggal_transaksi" id="tanggal_transaksi" value="<?= aman($transaksi['tanggal_transaksi']) ?>" required>
            </div>
        </div>

        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label>Jumlah Terjual (setara kg)</label>
                <input type="text" id="teks_jumlah_kg" readonly disabled placeholder="—">
            </div>
            <div class="grup-input">
                <label>Total Penjualan (Rp)</label>
                <input type="text" id="teks_total" readonly disabled placeholder="—">
            </div>
        </div>

        <div class="grup-input">
            <label for="catatan">Catatan (opsional)</label>
            <input type="text" name="catatan" id="catatan" maxlength="255" value="<?= aman($transaksi['catatan'] ?? '') ?>">
        </div>

        <div style="display:flex;gap:10px;">
            <button type="submit" class="tombol tombol-utama" style="flex:1;">Simpan Perubahan</button>
            <a href="index.php?hal=riwayat" class="tombol" style="background:var(--abu-latar);color:var(--abu-teks);text-decoration:none;display:flex;align-items:center;">Batal</a>
        </div>
    </form>
</section>

<script>
    const petaSatuanProduk = <?= json_encode($peta_satuan_produk, JSON_UNESCAPED_UNICODE) ?>;
    const FAKTOR_KG   = { kg: 1, bungkus: 1, sak: 50 };
    const pilihKode      = document.getElementById('kode_produk_pilih');
    const pilihNama      = document.getElementById('nama_produk_pilih');
    const inputIdProduk  = document.getElementById('id_produk');
    const inputJumlah = document.getElementById('jumlah');
    const inputHarga  = document.getElementById('harga_satuan');
    const pilihSatuan = document.getElementById('satuan_transaksi');
    const teksTotal    = document.getElementById('teks_total');
    const teksJumlahKg = document.getElementById('teks_jumlah_kg');

    function pilihProdukTerjadi(idProduk) {
        inputIdProduk.value = idProduk;
        pilihKode.value = idProduk;
        pilihNama.value = idProduk;
    }
    pilihKode.addEventListener('change', () => pilihProdukTerjadi(pilihKode.value));
    pilihNama.addEventListener('change', () => pilihProdukTerjadi(pilihNama.value));

    function perbaruiTotal() {
        const jumlah = parseFloat(inputJumlah.value || '0');
        const harga  = parseFloat(inputHarga.value || '0');
        const faktor = FAKTOR_KG[pilihSatuan.value] || 1;

        teksJumlahKg.value = jumlah > 0 ? `${(jumlah * faktor).toLocaleString('id-ID', {maximumFractionDigits:2})} kg` : '';
        teksTotal.value = (jumlah > 0 && harga > 0) ? `Rp ${(jumlah * harga).toLocaleString('id-ID')}` : '';
    }

    inputJumlah.addEventListener('input', perbaruiTotal);
    inputHarga.addEventListener('input', perbaruiTotal);
    pilihSatuan.addEventListener('change', perbaruiTotal);
    perbaruiTotal();
</script>
