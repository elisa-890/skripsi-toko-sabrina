<?php
require_once __DIR__ . '/config/pengaturan.php';
require_once __DIR__ . '/config/mobile_helper.php';

// Jika sudah login, langsung arahkan ke beranda
if (!empty($_SESSION['id_pengguna'])) {
    header('Location: index.php');
    exit;
}

$pesan_error = $_SESSION['pesan_login_error'] ?? '';
unset($_SESSION['pesan_login_error']);

// Pesan khusus jika session timeout
$pesan_timeout = '';
if (isset($_GET['pesan']) && $_GET['pesan'] === 'sesi_kedaluwarsa') {
    $pesan_timeout = 'Sesi Anda telah berakhir. Silakan login ulang.';
}

$device_type = deteksi_device_type();
$is_mobile = adalah_mobile();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<?php echo get_viewport_meta(); ?>
<meta name="viewport-fit" content="cover">
<title>Masuk — <?= aman(NAMA_APLIKASI) ?></title>
<link rel="manifest" href="assets/manifest.json">
<link rel="icon" type="image/png" href="assets/icons/icon-192.png">
<link rel="apple-touch-icon" href="assets/icons/icon-192.png">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Toko Sabrina">
<meta name="theme-color" content="#10203f">
<link rel="stylesheet" href="assets/css/style.css">
<style>
    <?php echo get_mobile_optimizations_css(); ?>
    
    /* Optimasi untuk layar kecil (HP portrait) */
    @media (max-height: 600px) {
        .login-panel-kiri {
            display: none !important;  /* Hide gambar di HP layar kecil */
        }
        .login-panel-kanan {
            padding: 16px;
        }
        .kartu-login {
            padding: 20px 16px;
        }
    }
    
    /* Safe area untuk iPhone notch */
    @media (orientation: landscape) and (max-height: 500px) {
        .kartu-login {
            max-height: 90vh;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }
    }
</style>
</head>
<body class="halaman-login device-<?php echo $device_type; ?> <?php echo $is_mobile ? 'is-mobile' : 'is-desktop'; ?>">
    <div class="login-panel-kiri">
        <img src="assets/images/toko_sabrina.jpg" alt="Toko Pakan Ternak Sabrina" class="login-gambar">
        <div class="login-gambar-overlay">
            <img src="assets/icons/icon-192.png" alt="Logo Toko Sabrina" class="lingkaran-logo">
            <h2>Toko Pakan Ternak Sabrina</h2>
            <p>Desa Nagasaribu V, Kec. Lintongnihuta, Kab. Humbang Hasundutan</p>
        </div>
    </div>

    <div class="login-panel-kanan">
        <div class="kartu-login">
            <div class="logo-login">
                <h1>Selamat Datang</h1>
                <p class="subjudul">Masuk ke Dashboard Prediksi Penjualan</p>
                <p class="subjudul" style="margin-top:6px;font-size:0.85rem;opacity:0.85;">
                    Tersedia 2 jenis akun: <strong>peneliti</strong> (pengolahan data &amp; model)
                    dan <strong>pemilik</strong> (transaksi &amp; hasil prediksi).
                </p>
            </div>

            <?php if ($pesan_timeout): ?>
                <div class="pesan pesan-info">
                    <strong>⏱️ Sesi Berakhir</strong><br>
                    <?php echo aman($pesan_timeout); ?>
                </div>
            <?php endif; ?>

            <?php if ($pesan_error): ?>
                <div class="pesan pesan-error">
                    <strong>❌ Login Gagal</strong><br>
                    <?php echo aman($pesan_error); ?>
                </div>
            <?php endif; ?>

            <form action="php/proses_login.php" method="POST" class="form-login">
                <div class="grup-input">
                    <label for="nama_pengguna">Nama Pengguna</label>
                    <input 
                        type="text" 
                        id="nama_pengguna" 
                        name="nama_pengguna" 
                        required 
                        autofocus 
                        autocomplete="username"
                        placeholder="Contoh: pemilik"
                        inputmode="text">
                </div>
                <div class="grup-input">
                    <label for="kata_sandi">Kata Sandi</label>
                    <input 
                        type="password" 
                        id="kata_sandi" 
                        name="kata_sandi" 
                        required 
                        autocomplete="current-password"
                        placeholder="Masukkan kata sandi"
                        inputmode="password">
                </div>
                <button type="submit" class="tombol tombol-utama tombol-lebar">Masuk</button>
            </form>

            <p class="catatan-kaki">
                &copy; <?= date('Y') ?> Toko Sabrina 
                <br>
                <a href="cek_sistem.php" style="color:var(--navy-utama);font-weight:600;">Cek Kesiapan Server</a>
            </p>
        </div>
    </div>

<script src="assets/js/script.js"></script>
<?php echo get_mobile_interactions_script(); ?>
<script>
// Load PWA pada mobile
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('assets/sw.js').catch(err => {
        console.log('Service Worker registration failed (non-critical):', err);
    });
}

// Focus input pertama di mobile untuk UX lebih baik
document.addEventListener('DOMContentLoaded', function() {
    const inputPertama = document.getElementById('nama_pengguna');
    if (inputPertama && window.innerHeight < 768) {
        // Di mobile portrait, jangan auto-focus (user mungkin sedang lihat keyboard)
        // inputPertama.focus();
    }
});
</script>
</body>
</html>
