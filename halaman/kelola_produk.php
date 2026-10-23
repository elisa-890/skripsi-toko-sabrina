<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

$daftar_produk = $koneksi->query(
    "SELECT id_produk, kode_produk, nama_produk, kategori, satuan, aktif FROM produk ORDER BY aktif DESC, nama_produk"
)->fetchAll();

$pesan_sukses = $_SESSION['pesan_produk_sukses'] ?? '';
unset($_SESSION['pesan_produk_sukses']);
$pesan_error = $_SESSION['pesan_produk_error'] ?? '';
unset($_SESSION['pesan_produk_error']);
?>

<?php if ($pesan_sukses): ?>
    <div class="pesan pesan-sukses"><?= aman($pesan_sukses) ?></div>
<?php endif; ?>
<?php if ($pesan_error): ?>
    <div class="pesan pesan-error"><?= aman($pesan_error) ?></div>
<?php endif; ?>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Tambah Produk Baru</h2>
    <form action="php/simpan_produk.php" method="POST" class="form-transaksi" id="formTambahProduk">
        <input type="hidden" name="aksi" value="tambah">

        <div class="grup-input">
            <label for="nama_produk">Nama Produk</label>
            <input type="text" name="nama_produk" id="nama_produk" required maxlength="100" placeholder="Contoh: BR1, Jagung Giling">
            <p class="teks-bantuan" style="color:var(--abu-lembut);">Nama dagang produk, dipakai untuk ditampilkan di semua halaman.</p>
        </div>

        <div class="grup-input">
            <label for="kode_produk">Kode Produk</label>
            <input type="text" name="kode_produk" id="kode_produk" required maxlength="20" placeholder="Contoh: PAK-001" autocomplete="off">
            <p class="teks-bantuan" id="bantuanKodeProduk" style="color:var(--abu-lembut);">
                Kode unik untuk membedakan produk secara internal (mis. kode gudang/SKU). <strong>Harus berbeda dari Nama Produk</strong> — bukan sekadar menyalin nama.
            </p>
        </div>

        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label for="kategori">Kategori / Jenis</label>
                <input type="text" name="kategori" id="kategori" maxlength="50" placeholder="Contoh: Pakan Ayam">
            </div>
            <div class="grup-input">
                <label for="satuan">Satuan</label>
                <select name="satuan" id="satuan" required>
                    <?php foreach (SATUAN_DIIZINKAN as $s): ?>
                        <option value="<?= aman($s) ?>"><?= aman(ucfirst($s)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <button type="submit" class="tombol tombol-utama tombol-lebar">Tambah Produk</button>
    </form>
    <script>
        (function () {
            const form = document.getElementById('formTambahProduk');
            const inputNama = document.getElementById('nama_produk');
            const inputKode = document.getElementById('kode_produk');
            const bantuan = document.getElementById('bantuanKodeProduk');

            function samaPersis() {
                const nama = inputNama.value.trim().toLowerCase();
                const kode = inputKode.value.trim().toLowerCase();
                return nama !== '' && kode !== '' && nama === kode;
            }

            function periksa() {
                if (samaPersis()) {
                    inputKode.setCustomValidity('Kode Produk tidak boleh sama persis dengan Nama Produk.');
                    bantuan.style.color = 'var(--merah-galat)';
                } else {
                    inputKode.setCustomValidity('');
                    bantuan.style.color = 'var(--abu-lembut)';
                }
            }

            inputNama.addEventListener('input', periksa);
            inputKode.addEventListener('input', periksa);
            form.addEventListener('submit', (e) => {
                periksa();
                if (samaPersis()) {
                    e.preventDefault();
                    inputKode.reportValidity();
                }
            });
        })();
    </script>
</section>

<section class="kartu kartu-panel">
    <h2 class="judul-panel">Daftar Produk (<?= count($daftar_produk) ?>)</h2>
    <?php if (empty($daftar_produk)): ?>
        <p class="teks-kosong">Belum ada produk. Tambahkan lewat form di atas, atau impor dari Excel.</p>
    <?php else: ?>
        <div class="wadah-tabel">
        <table class="tabel-data">
            <thead><tr><th>Kode</th><th>Nama Produk</th><th>Kategori</th><th>Satuan</th><th>Status</th><th>Aksi</th></tr></thead>
            <tbody>
                <?php foreach ($daftar_produk as $p): ?>
                    <tr style="<?= $p['aktif'] ? '' : 'opacity:0.5;' ?>">
                        <td><code><?= aman($p['kode_produk']) ?></code></td>
                        <td><?= aman($p['nama_produk']) ?></td>
                        <td><?= aman($p['kategori']) ?></td>
                        <td><?= aman($p['satuan']) ?></td>
                        <td><?= $p['aktif'] ? '✅ Aktif' : '⛔ Nonaktif' ?></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap;">
                                <form action="php/simpan_produk.php" method="POST" style="margin:0;">
                                    <input type="hidden" name="aksi" value="toggle_aktif">
                                    <input type="hidden" name="id_produk" value="<?= (int)$p['id_produk'] ?>">
                                    <button type="submit" class="tombol" style="padding:8px 12px;font-size:0.78rem;background:var(--abu-latar);color:var(--abu-teks);">
                                        <?= $p['aktif'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                                    </button>
                                </form>
                                <button type="button" class="tombol tombol-edit-produk" style="padding:8px 12px;font-size:0.78rem;background:var(--emas-pucat);color:var(--navy-tua);"
                                    data-id="<?= (int)$p['id_produk'] ?>"
                                    data-kode="<?= aman($p['kode_produk']) ?>"
                                    data-kategori="<?= aman($p['kategori']) ?>"
                                    data-satuan="<?= aman($p['satuan']) ?>">
                                    Edit
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    <?php endif; ?>
</section>

<!-- Panel edit produk (disembunyikan, dimunculkan lewat JS saat tombol Edit diklik) -->
<section class="kartu kartu-panel" id="panelEditProduk" style="display:none;">
    <h2 class="judul-panel">Edit Produk: <span id="namaProdukEdit"></span></h2>
    <form action="php/simpan_produk.php" method="POST" class="form-transaksi" id="formEditProduk">
        <input type="hidden" name="aksi" value="edit">
        <input type="hidden" name="id_produk" id="edit_id_produk">
        <div class="baris-dua-kolom">
            <div class="grup-input">
                <label for="edit_kode_produk">Kode Produk</label>
                <input type="text" name="kode_produk" id="edit_kode_produk" maxlength="20" required>
                <p class="teks-bantuan" style="color:var(--abu-lembut);">Harus berbeda dari Nama Produk (nama produk sendiri tidak diedit di sini).</p>
            </div>
            <div class="grup-input">
                <label for="edit_kategori">Kategori</label>
                <input type="text" name="kategori" id="edit_kategori" maxlength="50">
            </div>
        </div>
        <div class="grup-input">
            <label for="edit_satuan">Satuan</label>
            <select name="satuan" id="edit_satuan" required>
                <?php foreach (SATUAN_DIIZINKAN as $s): ?>
                    <option value="<?= aman($s) ?>"><?= aman(ucfirst($s)) ?></option>
                <?php endforeach; ?>
                <option value="" id="opsiSatuanLama">Satuan lama (data lawas)</option>
            </select>
            <p class="teks-bantuan" style="color:var(--abu-lembut);">Kalau produk ini berasal dari data impor lama dengan satuan di luar kg/Bungkus/Sak, satuan aslinya tetap muncul sebagai pilihan agar tidak tertimpa tidak sengaja.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <button type="submit" class="tombol tombol-utama" style="flex:1;">Simpan Perubahan</button>
            <button type="button" id="tombolBatalEdit" class="tombol" style="background:var(--abu-latar);color:var(--abu-teks);">Batal</button>
        </div>
    </form>
</section>

<script>
document.querySelectorAll('.tombol-edit-produk').forEach(tombol => {
    tombol.addEventListener('click', () => {
        const panel = document.getElementById('panelEditProduk');
        document.getElementById('edit_id_produk').value = tombol.dataset.id;
        document.getElementById('edit_kategori').value = tombol.dataset.kategori;
        document.getElementById('edit_kode_produk').value = tombol.dataset.kode;
        const namaProdukIni = tombol.closest('tr').querySelector('td:nth-child(2)').textContent.trim();
        document.getElementById('namaProdukEdit').textContent = namaProdukIni;

        const formEdit = document.getElementById('formEditProduk');
        const inputKodeEdit = document.getElementById('edit_kode_produk');
        function periksaEdit() {
            if (inputKodeEdit.value.trim().toLowerCase() === namaProdukIni.toLowerCase() && namaProdukIni !== '') {
                inputKodeEdit.setCustomValidity('Kode Produk tidak boleh sama persis dengan Nama Produk.');
            } else {
                inputKodeEdit.setCustomValidity('');
            }
        }
        inputKodeEdit.oninput = periksaEdit;
        formEdit.onsubmit = (e) => { periksaEdit(); if (inputKodeEdit.validationMessage) { e.preventDefault(); inputKodeEdit.reportValidity(); } };

        const satuanSekarang = tombol.dataset.satuan.toLowerCase();
        const opsiLama = document.getElementById('opsiSatuanLama');
        const satuanBaku = <?= json_encode(SATUAN_DIIZINKAN) ?>;
        if (!satuanBaku.includes(satuanSekarang)) {
            opsiLama.value = satuanSekarang;
            opsiLama.textContent = tombol.dataset.satuan + ' (satuan lama)';
            opsiLama.style.display = '';
        } else {
            opsiLama.style.display = 'none';
        }
        document.getElementById('edit_satuan').value = satuanSekarang;

        panel.style.display = 'block';
        panel.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
});

document.getElementById('tombolBatalEdit').addEventListener('click', () => {
    document.getElementById('panelEditProduk').style.display = 'none';
});
</script>
