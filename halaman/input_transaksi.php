<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

$daftar_produk = $koneksi->query(
    "SELECT id_produk, kode_produk, nama_produk, satuan, kategori FROM produk WHERE aktif = 1 ORDER BY kategori, nama_produk"
)->fetchAll();

// Dropdown "Jenis Pakan" disederhanakan jadi 3 pilihan tetap: Ayam, Bebek, Babi
// (mengelompokkan semua kategori produk seperti "Obat Ayam", "Suplemen Bebek", dst.)
$daftar_kategori = ['Ayam', 'Bebek', 'Babi'];

// Kelompokkan produk per jenis pakan supaya dropdown Kode/Nama Produk bisa
// ditampilkan per grup (pakai <optgroup>) — memudahkan membedakan mana
// produk Ayam, Bebek, dan Babi. Produk yang kategorinya tak cocok dengan
// ketiganya (mis. "Peralatan", "Umum") masuk ke grup "Lainnya".
$produk_per_kelompok = ['Ayam' => [], 'Bebek' => [], 'Babi' => [], 'Lainnya' => []];
foreach ($daftar_produk as $p) {
    $kelompok = kelompok_pakan($p['kategori']) ?: 'Lainnya';
    $produk_per_kelompok[$kelompok][] = $p;
}

// Peta satuan asli & harga terakhir per produk, dipakai JS untuk auto-isi form
$peta_produk = [];
foreach ($daftar_produk as $p) {
    $harga_terakhir = $koneksi->prepare(
        "SELECT harga_satuan FROM transaksi WHERE id_produk = :id ORDER BY tanggal_transaksi DESC, id_transaksi DESC LIMIT 1"
    );
    $harga_terakhir->execute(['id' => $p['id_produk']]);
    $harga = $harga_terakhir->fetchColumn();

    $peta_produk[$p['id_produk']] = [
        'kategori'       => $p['kategori'],
        'kelompok'       => kelompok_pakan($p['kategori']),
        'satuan'         => $p['satuan'],
        'harga_terakhir' => $harga !== false ? (float)$harga : 0,
    ];
}

// 10 transaksi terakhir, ditampilkan langsung di halaman ini supaya bisa
// diedit/dihapus tanpa perlu pindah ke menu Riwayat.
$transaksi_terakhir = $koneksi->query(
    "SELECT t.id_transaksi, t.tanggal_transaksi, p.kode_produk, p.nama_produk, p.kategori,
            t.jumlah, COALESCE(NULLIF(t.satuan_transaksi, ''), p.satuan) AS satuan,
            t.harga_satuan, t.total_penjualan
     FROM transaksi t
     JOIN produk p ON p.id_produk = t.id_produk
     ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC
     LIMIT 10"
)->fetchAll();

$pesan_sukses = $_SESSION['pesan_transaksi_sukses'] ?? '';
unset($_SESSION['pesan_transaksi_sukses']);
$pesan_error = $_SESSION['pesan_transaksi_error'] ?? '';
unset($_SESSION['pesan_transaksi_error']);

// Id transaksi yang baru saja disimpan (jika ada) -> dipakai untuk otomatis
// membuka nota di tab baru begitu form berhasil disimpan, tanpa perlu
// mengetik ulang data yang sama untuk membuat nota.
$transaksi_baru_id = $_SESSION['transaksi_baru_id'] ?? null;
unset($_SESSION['transaksi_baru_id']);
?>

<?php if ($pesan_sukses): ?>
    <div class="pesan pesan-sukses"><?= aman($pesan_sukses) ?></div>
<?php endif; ?>
<?php if ($pesan_error): ?>
    <div class="pesan pesan-error"><?= aman($pesan_error) ?></div>
<?php endif; ?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Catat Transaksi Penjualan</h2>

    <?php if (empty($daftar_produk)): ?>
        <p class="teks-kosong">
            Belum ada produk terdaftar. Tambahkan produk lewat menu
            <a href="?hal=kelola_produk" style="color:var(--navy-utama);font-weight:700;">Kelola Produk</a>,
            atau impor langsung dari Excel lewat menu Impor (produk baru akan dibuat otomatis).
        </p>
    <?php else: ?>
    <form action="php/simpan_transaksi.php" method="POST" class="form-transaksi">
        <div class="grup-input">
            <label for="kategori_filter">Jenis Pakan</label>
            <select id="kategori_filter">
                <option value="">Semua Jenis</option>
                <?php foreach ($daftar_kategori as $k): ?>
                    <option value="<?= aman($k) ?>"><?= aman($k) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label for="kode_produk_pilih">Kode Produk</label>
                <select id="kode_produk_pilih" required>
                    <option value="" disabled selected>Pilih kode…</option>
                    <?php foreach ($produk_per_kelompok as $nama_kelompok => $produk_kelompok): ?>
                        <?php if (empty($produk_kelompok)) continue; ?>
                        <optgroup label="<?= aman($nama_kelompok) ?>">
                            <?php foreach ($produk_kelompok as $p): ?>
                                <option value="<?= (int)$p['id_produk'] ?>" data-kategori="<?= aman(kelompok_pakan($p['kategori'])) ?>">
                                    <?= aman($p['kode_produk']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grup-input">
                <label for="nama_produk_pilih">Nama Produk</label>
                <select id="nama_produk_pilih" required>
                    <option value="" disabled selected>Pilih nama…</option>
                    <?php foreach ($produk_per_kelompok as $nama_kelompok => $produk_kelompok): ?>
                        <?php if (empty($produk_kelompok)) continue; ?>
                        <optgroup label="<?= aman($nama_kelompok) ?>">
                            <?php foreach ($produk_kelompok as $p): ?>
                                <option value="<?= (int)$p['id_produk'] ?>" data-kategori="<?= aman(kelompok_pakan($p['kategori'])) ?>">
                                    <?= aman($p['nama_produk']) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <input type="hidden" name="id_produk" id="id_produk" required>
        <p class="teks-bantuan" style="color:var(--abu-lembut);">
            Kode dan Nama saling terhubung — pilih salah satu, yang lain otomatis ikut menyesuaikan produk yang sama.
        </p>

        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label for="jumlah">Jumlah</label>
                <input type="number" step="0.01" min="0.01" name="jumlah" id="jumlah" required inputmode="decimal">
            </div>
            <div class="grup-input">
                <label for="satuan_transaksi">Satuan</label>
                <select name="satuan_transaksi" id="satuan_transaksi" required>
                    <option value="kg">Kg</option>
                    <option value="bungkus">Bungkus</option>
                    <option value="sak">Sak</option>
                </select>
            </div>
        </div>

        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label for="harga_satuan">Harga per Satuan (Rp)</label>
                <input type="number" step="0.01" min="0" name="harga_satuan" id="harga_satuan" value="0">
            </div>
            <div class="grup-input">
                <label for="tanggal_transaksi">Tanggal</label>
                <input type="date" name="tanggal_transaksi" id="tanggal_transaksi" value="<?= date('Y-m-d') ?>" required>
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
        <p class="teks-bantuan" style="color:var(--abu-lembut);">
            Konversi otomatis ke kg: 1 sak = 50 kg, 1 bungkus = 1 kg, 1 kg = 1 kg. Nilai ini dipakai supaya data
            latih model Prediksi tetap konsisten walau satuan penjualan berbeda-beda.
        </p>

        <div class="grup-input">
            <label for="catatan">Catatan (opsional)</label>
            <input type="text" name="catatan" id="catatan" maxlength="255" placeholder="Contoh: promo akhir pekan">
        </div>

        <button type="submit" class="tombol tombol-utama tombol-lebar">Simpan Transaksi</button>
    </form>
    <?php endif; ?>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Transaksi Terbaru</h2>
    <?php if (empty($transaksi_terakhir)): ?>
        <p class="teks-kosong">Belum ada transaksi tercatat.</p>
    <?php else: ?>
        <div class="wadah-tabel">
        <table class="tabel-data">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jenis Pakan</th>
                    <th>Kode</th>
                    <th>Produk</th>
                    <th>Jumlah</th>
                    <th>Harga Satuan</th>
                    <th>Total (Rp)</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transaksi_terakhir as $t): ?>
                    <tr>
                        <td><?= date('d M Y', strtotime($t['tanggal_transaksi'])) ?></td>
                        <td><?= aman($t['kategori']) ?></td>
                        <td><code><?= aman($t['kode_produk']) ?></code></td>
                        <td><?= aman($t['nama_produk']) ?></td>
                        <td><?= angka($t['jumlah']) ?> <?= aman($t['satuan']) ?></td>
                        <td><?= number_format($t['harga_satuan'], 0, ',', '.') ?></td>
                        <td><?= number_format($t['total_penjualan'], 0, ',', '.') ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <a href="cetak_nota.php?id=<?= (int)$t['id_transaksi'] ?>" target="_blank" class="tombol" style="padding:6px 10px;font-size:0.75rem;background:var(--navy-pucat);color:var(--navy-utama);text-decoration:none;display:inline-block;">🖨️ Nota</a>
                                <a href="index.php?hal=edit_transaksi&id=<?= (int)$t['id_transaksi'] ?>" class="tombol" style="padding:6px 10px;font-size:0.75rem;background:var(--emas-pucat);color:var(--navy-tua);text-decoration:none;display:inline-block;">Edit</a>
                                <form action="php/hapus_transaksi.php" method="POST" style="margin:0;" onsubmit="return confirm('Hapus transaksi ini? Tindakan tidak bisa dibatalkan.');">
                                    <input type="hidden" name="id_transaksi" value="<?= (int)$t['id_transaksi'] ?>">
                                    <button type="submit" class="tombol" style="padding:6px 10px;font-size:0.75rem;background:var(--merah-latar);color:var(--merah-galat);">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <p class="teks-bantuan" style="color:var(--abu-lembut);">
            Menampilkan 10 transaksi terbaru. Lihat semua & filter lengkap di menu
            <a href="?hal=riwayat" style="color:var(--navy-utama);font-weight:700;">Riwayat</a>.
        </p>
    <?php endif; ?>
</section>

<?php if ($transaksi_baru_id): ?>
<script>
    // Transaksi baru saja tersimpan -> langsung buka nota di tab baru,
    // memakai data yang SAMA dengan yang baru diinput (tidak ada input ulang).
    window.open('cetak_nota.php?id=<?= (int)$transaksi_baru_id ?>', '_blank');
</script>
<?php endif; ?>

<script>
    const petaProduk   = <?= json_encode($peta_produk, JSON_UNESCAPED_UNICODE) ?>;
    const FAKTOR_KG    = { kg: 1, bungkus: 1, sak: 50 };

    const pilihKategori = document.getElementById('kategori_filter');
    const pilihKode      = document.getElementById('kode_produk_pilih');
    const pilihNama      = document.getElementById('nama_produk_pilih');
    const inputIdProduk  = document.getElementById('id_produk');
    const pilihSatuan   = document.getElementById('satuan_transaksi');
    const inputHarga    = document.getElementById('harga_satuan');
    const inputJumlah   = document.getElementById('jumlah');
    const teksTotal     = document.getElementById('teks_total');
    const teksJumlahKg  = document.getElementById('teks_jumlah_kg');

    // Filter daftar produk berdasarkan Jenis Pakan yang dipilih (berlaku ke KEDUA dropdown)
    pilihKategori.addEventListener('change', () => {
        const kategoriTerpilih = pilihKategori.value;
        [pilihKode, pilihNama].forEach(pilih => {
            Array.from(pilih.querySelectorAll('optgroup')).forEach(grup => {
                const opsiGrup = Array.from(grup.querySelectorAll('option'));
                let adaYangCocok = false;
                opsiGrup.forEach(opsi => {
                    const cocok = !kategoriTerpilih || opsi.dataset.kategori === kategoriTerpilih;
                    opsi.hidden = !cocok;
                    if (cocok) adaYangCocok = true;
                });
                grup.hidden = !adaYangCocok; // sembunyikan optgroup kalau semua isinya tersembunyi
            });
            const opsiAktif = pilih.selectedOptions[0];
            if (opsiAktif && opsiAktif.hidden) {
                pilih.value = '';
            }
        });
        if (pilihKode.value === '' || pilihNama.value === '') {
            inputIdProduk.value = '';
            pilihSatuan.value = 'kg';
            perbaruiTotal();
        }
    });

    function pilihProdukTerjadi(idProduk) {
        inputIdProduk.value = idProduk;
        pilihKode.value = idProduk;
        pilihNama.value = idProduk;

        const info = petaProduk[idProduk];
        if (!info) return;
        const satuanAsli = (info.satuan || '').toLowerCase();
        pilihSatuan.value = FAKTOR_KG.hasOwnProperty(satuanAsli) ? satuanAsli : 'kg';
        if (parseFloat(inputHarga.value || '0') === 0) {
            inputHarga.value = info.harga_terakhir || 0;
        }
        perbaruiTotal();
    }

    // Pilih lewat Kode Produk -> Nama Produk otomatis ikut, begitu juga sebaliknya
    pilihKode.addEventListener('change', () => pilihProdukTerjadi(pilihKode.value));
    pilihNama.addEventListener('change', () => pilihProdukTerjadi(pilihNama.value));

    function perbaruiTotal() {
        const jumlah = parseFloat(inputJumlah.value || '0');
        const harga  = parseFloat(inputHarga.value || '0');
        const faktor = FAKTOR_KG[pilihSatuan.value] || 1;

        teksJumlahKg.value = jumlah > 0 ? `${(jumlah * faktor).toLocaleString('id-ID', {maximumFractionDigits:2})} kg` : '';
        teksTotal.value = (jumlah > 0 && harga > 0)
            ? `Rp ${(jumlah * harga).toLocaleString('id-ID')}`
            : '';
    }

    inputJumlah.addEventListener('input', perbaruiTotal);
    inputHarga.addEventListener('input', perbaruiTotal);
    pilihSatuan.addEventListener('change', perbaruiTotal);
</script>
