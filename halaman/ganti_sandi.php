<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti', 'pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

$pesan_sukses = $_SESSION['pesan_sandi_sukses'] ?? '';
unset($_SESSION['pesan_sandi_sukses']);
$pesan_error = $_SESSION['pesan_sandi_error'] ?? '';
unset($_SESSION['pesan_sandi_error']);
?>

<?php if ($pesan_sukses): ?>
    <div class="pesan pesan-sukses"><?= aman($pesan_sukses) ?></div>
<?php endif; ?>
<?php if ($pesan_error): ?>
    <div class="pesan pesan-error"><?= aman($pesan_error) ?></div>
<?php endif; ?>

<section class="kartu kartu-panel" style="max-width:480px;">
    <h2 class="judul-panel">Ganti Kata Sandi</h2>
    <p class="teks-bantuan" style="color:var(--abu-lembut);margin-bottom:14px;">
        Masuk sebagai <strong><?= aman($_SESSION['nama_pengguna']) ?></strong>
        (<?= aman($_SESSION['peran'] === 'peneliti' ? 'Peneliti' : 'Pemilik') ?>).
        Gunakan kata sandi baru yang hanya Anda ketahui — jangan pakai kata sandi bawaan lagi
        setelah sistem terbukti berjalan dengan baik.
    </p>

    <form action="php/ganti_sandi_proses.php" method="POST" class="form-transaksi" id="formGantiSandi">
        <div class="grup-input">
            <label for="sandi_saat_ini">Kata Sandi Saat Ini</label>
            <input type="password" name="sandi_saat_ini" id="sandi_saat_ini" required autocomplete="current-password">
        </div>

        <div class="grup-input">
            <label for="sandi_baru">Kata Sandi Baru</label>
            <input type="password" name="sandi_baru" id="sandi_baru" required minlength="6" autocomplete="new-password">
            <p class="teks-bantuan" style="color:var(--abu-lembut);">Minimal 6 karakter.</p>
        </div>

        <div class="grup-input">
            <label for="konfirmasi_sandi_baru">Konfirmasi Kata Sandi Baru</label>
            <input type="password" name="konfirmasi_sandi_baru" id="konfirmasi_sandi_baru" required minlength="6" autocomplete="new-password">
        </div>

        <button type="submit" class="tombol tombol-utama tombol-lebar">Simpan Kata Sandi Baru</button>
    </form>
</section>

<script>
(function () {
    const form = document.getElementById('formGantiSandi');
    const baru = document.getElementById('sandi_baru');
    const konfirmasi = document.getElementById('konfirmasi_sandi_baru');

    function periksaCocok() {
        if (baru.value !== konfirmasi.value) {
            konfirmasi.setCustomValidity('Konfirmasi kata sandi tidak sama dengan kata sandi baru.');
        } else {
            konfirmasi.setCustomValidity('');
        }
    }
    baru.addEventListener('input', periksaCocok);
    konfirmasi.addEventListener('input', periksaCocok);
    form.addEventListener('submit', (e) => {
        periksaCocok();
        if (konfirmasi.validationMessage) {
            e.preventDefault();
            konfirmasi.reportValidity();
        }
    });
})();
</script>
