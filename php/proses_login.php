<?php
require_once __DIR__ . '/../config/pengaturan.php';
require_once __DIR__ . '/../config/koneksi_db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

$nama_pengguna = trim($_POST['nama_pengguna'] ?? '');
$kata_sandi    = $_POST['kata_sandi'] ?? '';

if ($nama_pengguna === '' || $kata_sandi === '') {
    $_SESSION['pesan_login_error'] = 'Nama pengguna dan kata sandi wajib diisi.';
    header('Location: ../login.php');
    exit;
}

// Batasi percobaan login sederhana (anti brute-force ringan)
$_SESSION['percobaan_login'] = ($_SESSION['percobaan_login'] ?? 0) + 1;
if ($_SESSION['percobaan_login'] > 20) {
    $_SESSION['pesan_login_error'] = 'Terlalu banyak percobaan. Coba lagi beberapa saat lagi.';
    header('Location: ../login.php');
    exit;
}

$pernyataan = $koneksi->prepare(
    'SELECT id_pengguna, nama_pengguna, kata_sandi_hash, nama_lengkap, peran
     FROM pengguna WHERE nama_pengguna = :nama_pengguna LIMIT 1'
);
$pernyataan->execute(['nama_pengguna' => $nama_pengguna]);
$pengguna = $pernyataan->fetch();

if ($pengguna && password_verify($kata_sandi, $pengguna['kata_sandi_hash'])) {
    session_regenerate_id(true);
    $_SESSION['id_pengguna']    = $pengguna['id_pengguna'];
    $_SESSION['nama_pengguna']  = $pengguna['nama_pengguna'];
    $_SESSION['nama_lengkap']   = $pengguna['nama_lengkap'];
    $_SESSION['peran']          = $pengguna['peran'];
    unset($_SESSION['percobaan_login']);

    header('Location: ../index.php');
    exit;
}

$_SESSION['pesan_login_error'] = 'Nama pengguna atau kata sandi salah.';
header('Location: ../login.php');
exit;
