<?php
require_once __DIR__ . '/../config/pengaturan.php';
wajib_peran(['peneliti', 'pemilik']);
require_once __DIR__ . '/../config/koneksi_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=ganti_sandi');
    exit;
}

function tolak(string $pesan): void
{
    $_SESSION['pesan_sandi_error'] = $pesan;
    header('Location: ../index.php?hal=ganti_sandi');
    exit;
}

$sandi_saat_ini      = (string) ($_POST['sandi_saat_ini'] ?? '');
$sandi_baru          = (string) ($_POST['sandi_baru'] ?? '');
$konfirmasi_sandi    = (string) ($_POST['konfirmasi_sandi_baru'] ?? '');

if ($sandi_saat_ini === '' || $sandi_baru === '' || $konfirmasi_sandi === '') {
    tolak('Semua kolom wajib diisi.');
}

if ($sandi_baru !== $konfirmasi_sandi) {
    tolak('Konfirmasi kata sandi baru tidak sama dengan kata sandi baru.');
}

if (strlen($sandi_baru) < 6) {
    tolak('Kata sandi baru minimal 6 karakter.');
}

$id_pengguna = (int) ($_SESSION['id_pengguna'] ?? 0);

$stmt = $koneksi->prepare('SELECT kata_sandi_hash FROM pengguna WHERE id_pengguna = :id');
$stmt->execute(['id' => $id_pengguna]);
$baris = $stmt->fetch();

if (!$baris || !password_verify($sandi_saat_ini, $baris['kata_sandi_hash'])) {
    tolak('Kata sandi saat ini salah.');
}

if ($sandi_saat_ini === $sandi_baru) {
    tolak('Kata sandi baru tidak boleh sama dengan kata sandi lama.');
}

$hash_baru = password_hash($sandi_baru, PASSWORD_BCRYPT);
$koneksi->prepare('UPDATE pengguna SET kata_sandi_hash = :hash WHERE id_pengguna = :id')
        ->execute(['hash' => $hash_baru, 'id' => $id_pengguna]);

$_SESSION['pesan_sandi_sukses'] = 'Kata sandi berhasil diubah. Gunakan kata sandi baru ini untuk login selanjutnya.';
header('Location: ../index.php?hal=ganti_sandi');
exit;
