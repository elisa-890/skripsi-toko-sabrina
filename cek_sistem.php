<?php
/**
 * cek_sistem.php
 * -----------------------------------------------------------------
 * Halaman diagnostik mandiri (tidak perlu login, tidak bergantung ke
 * database) untuk memeriksa apakah server sudah siap menjalankan
 * Dashboard Toko Sabrina. Tampilkan hasil cek satu per satu supaya
 * pemilik/teknisi bisa langsung tahu apa yang perlu diperbaiki kalau
 * ada pesan "terjadi kesalahan pada server".
 * -----------------------------------------------------------------
 */

date_default_timezone_set('Asia/Jakarta');

function baris_cek(string $label, bool $ok, string $keterangan = '', string $solusi = ''): array
{
    return compact('label', 'ok', 'keterangan', 'solusi');
}

$hasil = [];

// 1) Versi PHP
$versi_php_ok = version_compare(PHP_VERSION, '8.0.0', '>=');
$hasil[] = baris_cek('Versi PHP (' . PHP_VERSION . ')', $versi_php_ok, '', 'Gunakan PHP 8.0 atau lebih baru.');

// 2) Ekstensi pdo_sqlite
$hasil[] = baris_cek(
    'Ekstensi pdo_sqlite',
    extension_loaded('pdo_sqlite'),
    'Dipakai untuk membuka database database.db',
    'Aktifkan "extension=pdo_sqlite" di php.ini lalu restart Apache/PHP.'
);

// 3) Ekstensi zip
$hasil[] = baris_cek(
    'Ekstensi zip',
    extension_loaded('zip'),
    'Dipakai saat membaca file .xlsx pada menu Impor',
    'Aktifkan "extension=zip" di php.ini lalu restart Apache/PHP.'
);

// 3b) Ekstensi SimpleXML (dipakai PembacaXlsx.php untuk mengurai isi file .xlsx)
$hasil[] = baris_cek(
    'Ekstensi SimpleXML',
    class_exists('SimpleXMLElement'),
    'Dipakai untuk membaca isi XML di dalam file .xlsx saat impor data',
    'Aktifkan "extension=simplexml" (atau "extension=xml") di php.ini lalu restart Apache/PHP.'
);

// 4) Folder aplikasi bisa ditulis (untuk membuat database.db & menyimpan model)
$folder_bisa_ditulis = is_writable(__DIR__);
$hasil[] = baris_cek(
    'Folder aplikasi bisa ditulis',
    $folder_bisa_ditulis,
    'Diperlukan agar file database.db & models/*.pkl bisa dibuat/diperbarui',
    'Ubah izin folder ini (dan folder models/) menjadi bisa ditulis oleh web server (mis. chmod 755/775).'
);

$folder_models_ok = is_dir(__DIR__ . '/models') && is_writable(__DIR__ . '/models');
$hasil[] = baris_cek(
    'Folder models/ bisa ditulis',
    $folder_models_ok,
    'Tempat menyimpan model hasil "Latih Ulang"',
    'Pastikan folder models/ ada dan bisa ditulis web server.'
);

// 5) shell_exec aktif (dipakai untuk memanggil Python)
$shell_exec_ok = function_exists('shell_exec') && !in_array('shell_exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true);
$tes_shell = $shell_exec_ok ? @shell_exec('echo tes_ok') : null;
$shell_exec_ok = $shell_exec_ok && is_string($tes_shell) && str_contains($tes_shell, 'tes_ok');
$hasil[] = baris_cek(
    'shell_exec() aktif',
    $shell_exec_ok,
    'Dipakai PHP untuk menjalankan skrip Python (Latih & Prediksi)',
    'Hubungi penyedia hosting untuk mengaktifkan shell_exec(), atau hapus "shell_exec" dari disable_functions di php.ini. Pada banyak hosting bersama (shared hosting), fitur ini memang sengaja dimatikan demi keamanan — jika tidak bisa diaktifkan, menu Latih Ulang & Prediksi tidak akan berjalan di server tersebut.'
);

// 6) Python terpasang & bisa dipanggil
require_once __DIR__ . '/config/pengaturan.php';
$python_bin = defined('PYTHON_BIN') ? PYTHON_BIN : 'python3';
$versi_python = $shell_exec_ok ? @shell_exec(escapeshellarg_bin($python_bin) . ' --version 2>&1') : null;
// PENTING: jangan cuma cek kata "Python" ada di output. Di Windows, kalau
// Python BELUM terpasang tapi ada alias bawaan Microsoft Store, mengetik
// "python" di terminal memunculkan pesan seperti:
//   "Python was not found; run without arguments to install from the
//    Microsoft Store, or disable this shortcut from Settings > Apps..."
// Pesan itu MENGANDUNG kata "Python" juga, jadi cek lama (stripos) salah
// kira Python sudah terpasang padahal belum. Di sini dicek pola versi asli
// (mis. "Python 3.12.3") DAN pesan tsb dipastikan bukan pesan galat/alias.
$python_ok = is_string($versi_python)
    && preg_match('/Python\s+\d+\.\d+/i', $versi_python) === 1
    && stripos($versi_python, 'was not found') === false
    && stripos($versi_python, 'Microsoft Store') === false;
$solusi_python = $python_ok ? '' : (
    (is_string($versi_python) && stripos($versi_python, 'Microsoft Store') !== false)
        ? 'Python belum benar-benar terpasang. Unduh & instal Python asli dari python.org (JANGAN dari Microsoft Store), lalu centang "Add python.exe to PATH" saat instalasi, lalu restart Apache/komputer.'
        : 'Set PYTHON_BIN di config/pengaturan.php ke path lengkap python.exe / python3 yang benar.'
);
$cek_python = baris_cek(
    'Python bisa dijalankan (' . $python_bin . ')',
    $python_ok,
    $python_ok ? trim((string)$versi_python) : 'Tidak dapat menjalankan perintah python',
    $solusi_python
);
$hasil[] = $cek_python;

// 7) Paket Python yang dibutuhkan
$paket_dibutuhkan = ['pandas', 'statsmodels', 'xgboost', 'sklearn'];
$paket_hasil = [];
if ($python_ok) {
    foreach ($paket_dibutuhkan as $paket) {
  $perintah_cek_paket =
    'HOME=/home/aplb6542 '
    . 'OPENBLAS_NUM_THREADS=1 '
    . 'OMP_NUM_THREADS=1 '
    . 'MKL_NUM_THREADS=1 '
    . 'NUMEXPR_NUM_THREADS=1 '
    . escapeshellarg_bin($python_bin)
    . ' -c "import ' . $paket . '" 2>&1';

$cek = @shell_exec($perintah_cek_paket);
$paket_ok = trim((string)$cek) === '';
        $paket_hasil[] = baris_cek(
            'Paket Python: ' . ($paket === 'sklearn' ? 'scikit-learn' : $paket),
            $paket_ok,
            '',
            'pip install ' . ($paket === 'sklearn' ? 'scikit-learn' : $paket)
        );
    }
} else {
    foreach ($paket_dibutuhkan as $paket) {
        $paket_hasil[] = baris_cek('Paket Python: ' . ($paket === 'sklearn' ? 'scikit-learn' : $paket), false, 'Tidak dapat dicek karena Python tidak berjalan', '');
    }
}

// 8) Cek akun login (pemilik & peneliti) di database.
// Meng-include koneksi_db.php di sini SEKALIGUS memicu migrasi otomatis
// (pastikan_database_siap + jalankan_migrasi_tambahan), jadi kalau akun
// "peneliti" ternyata belum ada di database lama, akun itu akan otomatis
// dibuat begitu halaman ini dibuka -- tidak perlu hapus database.db manual.
$daftar_akun = [];
$cek_akun_error = '';
try {
    require_once __DIR__ . '/config/koneksi_db.php';
    $daftar_akun = $koneksi->query("SELECT nama_pengguna, peran, dibuat_pada FROM pengguna ORDER BY id_pengguna")->fetchAll();
} catch (Throwable $e) {
    $cek_akun_error = $e->getMessage();
}
$akun_pemilik_ada  = (bool) array_filter($daftar_akun, fn($a) => $a['nama_pengguna'] === 'pemilik');
$akun_peneliti_ada = (bool) array_filter($daftar_akun, fn($a) => $a['nama_pengguna'] === 'peneliti');

$hasil_akun = [
    baris_cek('Akun "pemilik" tersedia', $akun_pemilik_ada, '', 'Buka halaman ini sekali lagi (migrasi otomatis akan mencoba membuat ulang), atau hapus database.db agar dibuat fresh.'),
    baris_cek('Akun "peneliti" tersedia', $akun_peneliti_ada, '', 'Buka halaman ini sekali lagi (migrasi otomatis akan mencoba membuat ulang), atau hapus database.db agar dibuat fresh.'),
];

$semua_wajib = array_merge($hasil, [
    baris_cek('Python & seluruh paketnya', $python_ok && !in_array(false, array_column($paket_hasil, 'ok'), true)),
]);
$semua_ok = !in_array(false, array_column($hasil, 'ok'), true) && $python_ok && !in_array(false, array_column($paket_hasil, 'ok'), true)
    && !in_array(false, array_column($hasil_akun, 'ok'), true);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Cek Sistem — Dashboard Toko Sabrina</title>
<link rel="stylesheet" href="assets/css/style.css">
<style>
    .cek-wadah{max-width:760px;margin:0 auto;padding:32px 18px 60px;}
    .cek-daftar{display:flex;flex-direction:column;gap:10px;margin-top:18px;}
    .cek-item{display:flex;gap:12px;padding:14px 16px;border-radius:12px;border:1px solid var(--abu-garis);background:#fff;}
    .cek-item.gagal{background:var(--merah-latar);border-color:#f3cfcc;}
    .cek-item.sukses{background:var(--hijau-sukses-bg);border-color:#cfe6d9;}
    .cek-status{font-size:1.1rem;line-height:1.3;}
    .cek-label{font-weight:700;font-size:0.92rem;}
    .cek-ket{font-size:0.82rem;color:var(--abu-lembut);margin-top:2px;}
    .cek-solusi{font-size:0.8rem;margin-top:6px;color:var(--emas-aksen);font-weight:600;}
    .ringkasan-cek{padding:18px 20px;border-radius:14px;font-weight:700;text-align:center;margin-top:8px;}
    .ringkasan-cek.ok{background:var(--hijau-sukses-bg);color:var(--hijau-sukses-tx);}
    .ringkasan-cek.bermasalah{background:var(--merah-latar);color:var(--merah-galat);}
    .subgrup{margin-top:22px;font-weight:700;color:var(--navy-tua);font-size:0.95rem;}
</style>
</head>
<body>
<div class="cek-wadah">
    <h1 style="font-size:1.4rem;">🔍 Cek Kesiapan Server</h1>
    <p style="color:var(--abu-lembut);font-size:0.9rem;">Halaman ini memeriksa apakah server sudah siap menjalankan semua fitur Dashboard Toko Sabrina, termasuk fitur Latih Ulang &amp; Prediksi yang memakai Python.</p>

    <div class="ringkasan-cek <?= $semua_ok ? 'ok' : 'bermasalah' ?>">
        <?= $semua_ok ? '✅ Semua pemeriksaan berhasil — server siap digunakan.' : '⚠️ Ada beberapa hal yang perlu diperbaiki di server (lihat rincian di bawah).' ?>
    </div>

    <div class="subgrup">Dasar PHP &amp; Database</div>
    <div class="cek-daftar">
        <?php foreach (array_slice($hasil, 0, 5) as $item): ?>
            <div class="cek-item <?= $item['ok'] ? 'sukses' : 'gagal' ?>">
                <div class="cek-status"><?= $item['ok'] ? '✅' : '❌' ?></div>
                <div>
                    <div class="cek-label"><?= htmlspecialchars($item['label']) ?></div>
                    <?php if ($item['keterangan']): ?><div class="cek-ket"><?= htmlspecialchars($item['keterangan']) ?></div><?php endif; ?>
                    <?php if (!$item['ok'] && $item['solusi']): ?><div class="cek-solusi">💡 <?= htmlspecialchars($item['solusi']) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="subgrup">Fitur Latih Ulang &amp; Prediksi (Python)</div>
    <div class="cek-daftar">
        <?php foreach ([$hasil[5], $cek_python] as $item): ?>
            <div class="cek-item <?= $item['ok'] ? 'sukses' : 'gagal' ?>">
                <div class="cek-status"><?= $item['ok'] ? '✅' : '❌' ?></div>
                <div>
                    <div class="cek-label"><?= htmlspecialchars($item['label']) ?></div>
                    <?php if ($item['keterangan']): ?><div class="cek-ket"><?= htmlspecialchars($item['keterangan']) ?></div><?php endif; ?>
                    <?php if (!$item['ok'] && $item['solusi']): ?><div class="cek-solusi">💡 <?= htmlspecialchars($item['solusi']) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
        <?php foreach ($paket_hasil as $item): ?>
            <div class="cek-item <?= $item['ok'] ? 'sukses' : 'gagal' ?>">
                <div class="cek-status"><?= $item['ok'] ? '✅' : '❌' ?></div>
                <div>
                    <div class="cek-label"><?= htmlspecialchars($item['label']) ?></div>
                    <?php if (!$item['ok'] && $item['solusi']): ?><div class="cek-solusi">💡 <?= htmlspecialchars($item['solusi']) ?></div><?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="subgrup">Akun Login (Peneliti &amp; Pemilik)</div>
    <div class="cek-daftar">
        <?php if ($cek_akun_error): ?>
            <div class="cek-item gagal">
                <div class="cek-status">❌</div>
                <div>
                    <div class="cek-label">Tidak bisa membaca tabel akun</div>
                    <div class="cek-ket"><?= htmlspecialchars($cek_akun_error) ?></div>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($hasil_akun as $item): ?>
                <div class="cek-item <?= $item['ok'] ? 'sukses' : 'gagal' ?>">
                    <div class="cek-status"><?= $item['ok'] ? '✅' : '❌' ?></div>
                    <div>
                        <div class="cek-label"><?= htmlspecialchars($item['label']) ?></div>
                        <?php if (!$item['ok'] && $item['solusi']): ?><div class="cek-solusi">💡 <?= htmlspecialchars($item['solusi']) ?></div><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="cek-item sukses" style="background:#f4f6fb;border-color:var(--abu-garis);">
                <div class="cek-status">ℹ️</div>
                <div>
                    <div class="cek-label">Daftar akun di database saat ini (<?= count($daftar_akun) ?> akun)</div>
                    <div class="cek-ket">
                        <?php foreach ($daftar_akun as $a): ?>
                            <?= htmlspecialchars($a['nama_pengguna']) ?> — peran: <?= htmlspecialchars($a['peran']) ?><br>
                        <?php endforeach; ?>
                        <?php if (empty($daftar_akun)): ?>(belum ada akun sama sekali)<?php endif; ?>
                    </div>
                    <div class="cek-solusi" style="color:var(--abu-lembut);">Kata sandi tidak ditampilkan di sini demi keamanan. Jika belum pernah diganti sejak instalasi awal, lihat kata sandi bawaan di BACA_SAYA.txt (file ini TIDAK diupload ke server publik) — lalu segera ganti lewat menu "Ganti Sandi" setelah login.</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!$python_ok || in_array(false, array_column($paket_hasil, 'ok'), true)): ?>
    <div class="cek-item gagal" style="margin-top:18px;">
        <div class="cek-status">💡</div>
        <div>
            <div class="cek-label">Cara pasang semua paket Python sekaligus</div>
            <div class="cek-ket">Jalankan perintah ini di Command Prompt/Terminal server:</div>
            <code style="display:block;background:#fff;padding:10px 12px;border-radius:8px;margin-top:6px;font-size:0.82rem;overflow-x:auto;">pip install pandas statsmodels xgboost scikit-learn</code>
        </div>
    </div>
    <?php endif; ?>

    <p style="text-align:center;margin-top:28px;">
        <a href="login.php" style="color:var(--navy-utama);font-weight:700;">← Kembali ke halaman Login</a>
    </p>
</div>
</body>
</html>
