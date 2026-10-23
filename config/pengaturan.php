<?php
/**
 * config/pengaturan.php
 * -----------------------------------------------------------------
 * Pengaturan umum aplikasi:
 * - Session
 * - Zona waktu
 * - Konfigurasi Python
 * - Session timeout
 * - Error handling
 * - Hak akses pengguna
 * - Konversi satuan
 * - Kode produk otomatis
 * -----------------------------------------------------------------
 *
 * PERBAIKAN UNTUK SHARED HOSTING / CPANEL
 * ----------------------------------------
 * Python dikunci ke virtualenv yang sudah terbukti berhasil:
 *
 * /home/aplb6542/virtualenv/toko_sabrina_python/3.12/bin/python
 *
 * Selain itu HOME dan beberapa variabel environment numerik
 * disiapkan sebelum Python dijalankan.
 */

// =====================================================================
// ENVIRONMENT SERVER
// =====================================================================

// Pastikan HOME tersedia ketika PHP memanggil Python.
// Ini penting karena set_env_vars.py pada virtualenv membutuhkan HOME.
if (function_exists('putenv')) {
    @putenv('HOME=/home/aplb6542');
    @putenv('USER=aplb6542');

    // Batasi thread library numerik agar shared hosting tidak
    // membuat terlalu banyak thread.
    @putenv('OPENBLAS_NUM_THREADS=1');
    @putenv('OMP_NUM_THREADS=1');
    @putenv('MKL_NUM_THREADS=1');
    @putenv('NUMEXPR_NUM_THREADS=1');

    // Tambahkan virtualenv Python ke PATH.
    $python_bin_dir = '/home/aplb6542/virtualenv/toko_sabrina_python/3.12/bin';

    $path_lama = getenv('PATH');
    if (!is_string($path_lama)) {
        $path_lama = '';
    }

    if ($path_lama === '') {
        @putenv('PATH=' . $python_bin_dir . ':/usr/local/bin:/usr/bin:/bin');
    } elseif (strpos($path_lama, $python_bin_dir) === false) {
        @putenv('PATH=' . $python_bin_dir . ':' . $path_lama);
    }
}


// =====================================================================
// SESSION
// =====================================================================

if (session_status() === PHP_SESSION_NONE) {

    // Session maksimal 30 menit.
    ini_set('session.gc_maxlifetime', '1800');

    // Cookie session dihapus ketika browser ditutup.
    ini_set('session.cookie_lifetime', '0');

    // Cookie tidak dapat dibaca JavaScript.
    ini_set('session.cookie_httponly', '1');

    // Cookie secure hanya jika HTTPS.
    ini_set(
        'session.cookie_secure',
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? '1' : '0'
    );

    // Perlindungan tambahan terhadap CSRF.
    ini_set('session.cookie_samesite', 'Lax');

    // Jangan menerima session ID sembarangan.
    ini_set('session.use_strict_mode', '1');

    // Session hanya melalui cookie.
    ini_set('session.use_only_cookies', '1');

    session_start();
}


// =====================================================================
// TIMEZONE
// =====================================================================

date_default_timezone_set('Asia/Jakarta');


// =====================================================================
// MIDDLEWARE VALIDASI SESSION TIMEOUT
// =====================================================================

function validasi_session_timeout(): void
{
    $SESSION_TIMEOUT = 1800; // 30 menit
    $GRACE_PERIOD     = 60;   // tambahan toleransi 1 menit

    // Hanya berlaku untuk pengguna yang sudah login.
    if (!empty($_SESSION['id_pengguna'])) {

        $waktu_sekarang = time();

        $waktu_aktivitas_terakhir =
            $_SESSION['waktu_aktivitas_terakhir'] ?? null;

        // Pertama kali login.
        if ($waktu_aktivitas_terakhir === null) {

            $_SESSION['waktu_aktivitas_terakhir'] = $waktu_sekarang;

        } else {

            $selisih_waktu =
                $waktu_sekarang - (int)$waktu_aktivitas_terakhir;

            // Session sudah kedaluwarsa.
            if ($selisih_waktu > ($SESSION_TIMEOUT + $GRACE_PERIOD)) {

                $_SESSION = [];

                if (ini_get('session.use_cookies')) {

                    $param = session_get_cookie_params();

                    setcookie(
                        session_name(),
                        '',
                        time() - 42000,
                        $param['path'],
                        $param['domain'],
                        $param['secure'],
                        $param['httponly']
                    );
                }

                session_destroy();

                header(
                    'Location: ../login.php?pesan=sesi_kedaluwarsa'
                );
                exit;
            }

            // User masih aktif.
            $_SESSION['waktu_aktivitas_terakhir'] = $waktu_sekarang;
        }
    }
}


// =====================================================================
// ERROR HANDLING GLOBAL
// =====================================================================

error_reporting(E_ALL);

// Jangan tampilkan error teknis kepada pengunjung.
ini_set('display_errors', '0');

// Tetap catat error ke log server.
ini_set('log_errors', '1');


// =====================================================================
// CEK PERMINTAAN AJAX
// =====================================================================

if (!function_exists('permintaan_ajax')) {

    function permintaan_ajax(): bool
    {
        return str_contains(
            $_SERVER['SCRIPT_NAME'] ?? '',
            '/php/'
        );
    }
}


// =====================================================================
// HALAMAN ERROR
// =====================================================================

if (!function_exists('tampilkan_halaman_error')) {

    function tampilkan_halaman_error(string $pesan): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }

        $ke_root =
            str_contains(
                $_SERVER['SCRIPT_NAME'] ?? '',
                '/halaman/'
            )
            ? '../'
            : '';

        echo '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Terjadi Kesalahan — Dashboard Toko Sabrina</title>

<style>
body {
    font-family:
        -apple-system,
        BlinkMacSystemFont,
        "Segoe UI",
        sans-serif;

    background: #f7f5f2;
    color: #3f3a35;

    display: flex;
    min-height: 100vh;

    align-items: center;
    justify-content: center;

    margin: 0;
    padding: 24px;
}

.kotak {
    max-width: 460px;

    background: #fff;

    border-radius: 16px;

    padding: 32px 28px;

    box-shadow:
        0 8px 24px rgba(27,67,50,0.12);

    text-align: center;
}

.kotak h1 {
    font-size: 1.2rem;
    color: #b3261e;
    margin: 0 0 10px;
}

.kotak p {
    font-size: 0.92rem;
    line-height: 1.6;
    margin: 0 0 18px;
}

.kotak a {
    display: inline-block;

    background: #2d6a4f;
    color: #fff;

    padding: 11px 20px;

    border-radius: 10px;

    text-decoration: none;
    font-weight: 600;
    font-size: 0.9rem;

    margin: 4px;
}

.kotak a.kedua {
    background: #eaf3ee;
    color: #2d6a4f;
}
</style>

</head>

<body>

<div class="kotak">

<h1>⚠️ Terjadi Kesalahan</h1>

<p>'
            . htmlspecialchars(
                $pesan,
                ENT_QUOTES,
                'UTF-8'
            )
            . '</p>

<a href="'
            . $ke_root
            . 'index.php">
Kembali ke Beranda
</a>

<a
    class="kedua"
    href="'
            . $ke_root
            . 'cek_sistem.php">
Cek Sistem
</a>

</div>

</body>
</html>';
    }
}


// =====================================================================
// GLOBAL FATAL ERROR HANDLER
// =====================================================================

register_shutdown_function(function () {

    $error = error_get_last();

    if (
        $error === null ||
        !in_array(
            $error['type'],
            [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR
            ],
            true
        )
    ) {
        return;
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    // AJAX / proses PHP.
    if (permintaan_ajax()) {

        if (!headers_sent()) {
            header(
                'Content-Type: application/json; charset=utf-8'
            );

            http_response_code(500);
        }

        echo json_encode(
            [
                'status' => 'gagal',

                'pesan' =>
                    'Terjadi kesalahan pada server. '
                    . 'Silakan coba lagi, atau buka halaman '
                    . '"Cek Sistem" untuk melihat penyebabnya.'
            ],
            JSON_UNESCAPED_UNICODE
        );

        return;
    }

    tampilkan_halaman_error(
        'Terjadi kesalahan tak terduga pada server. '
        . 'Tim toko dapat membuka halaman "Cek Sistem" '
        . 'untuk melihat detail teknisnya.'
    );
});


// =====================================================================
// INFORMASI APLIKASI
// =====================================================================

define(
    'NAMA_APLIKASI',
    'Dashboard Toko Sabrina'
);

define(
    'BASE_PATH',
    dirname(__DIR__)
);


// =====================================================================
// PYTHON SERVER
// =====================================================================
//
// PYTHON DIKUNCI KE VIRTUALENV YANG SUDAH TERBUKTI BERHASIL.
//
// Hasil test Anda:
// Python 3.12.14
// pandas OK
//
// Jadi tidak perlu mencari "python3" dari PATH.
//

define(
    'PYTHON_BIN',
    '/home/aplb6542/virtualenv/toko_sabrina_python/3.12/bin/python'
);

/**
 * Kompatibilitas dengan kode lama.
 *
 * Beberapa file aplikasi seperti cek_sistem.php mungkin masih
 * memanggil deteksi_python_bin().
 *
 * Karena Python virtualenv sudah terbukti bekerja, kita tidak
 * perlu melakukan deteksi python3 dari PATH lagi.
 */
function deteksi_python_bin(): string
{
    return PYTHON_BIN;
}

// =====================================================================
// PATH PYTHON DAN MODEL
// =====================================================================

define(
    'PATH_PYTHON',
    BASE_PATH . '/python'
);

define(
    'PATH_MODELS',
    BASE_PATH . '/models'
);


// =====================================================================
// FUNGSI ESCAPE PERINTAH PYTHON
// =====================================================================
//
// Fungsi ini berguna jika kode lain menggunakan PYTHON_BIN
// dan membutuhkan escaping shell.
//
// Contoh:
// escapeshellarg_bin(PYTHON_BIN)
//

if (!function_exists('escapeshellarg_bin')) {

   function escapeshellarg_bin(string $perintah): string
{
    $perintah = trim($perintah);

    if ($perintah === '') {
        return '';
    }

    /*
     * Shared hosting tertentu menonaktifkan escapeshellarg().
     *
     * Untuk Python_BIN kita hanya perlu menangani:
     * - path absolut Python
     * - python3
     * - python
     * - perintah sederhana seperti "py -3"
     *
     * Jika path mengandung spasi, bungkus seluruh path dengan
     * tanda kutip.
     */

    if (str_contains($perintah, ' ')) {
        $bagian = preg_split('/\s+/', $perintah);

        $hasil = [];

        foreach ($bagian as $i => $kata) {
            if ($kata === '') {
                continue;
            }

            /*
             * Kata pertama adalah executable Python.
             * Jika berupa path, lindungi dari shell.
             */
            if ($i === 0 && str_contains($kata, '/')) {
                $hasil[] = '"' . str_replace('"', '\\"', $kata) . '"';
            } else {
                $hasil[] = $kata;
            }
        }

        return implode(' ', $hasil);
    }

    /*
     * Path Python absolut seperti:
     * /home/aplb6542/virtualenv/toko_sabrina_python/3.12/bin/python
     */
    if (str_contains($perintah, '/')) {
        return '"' . str_replace('"', '\\"', $perintah) . '"';
    }

    return $perintah;
}
}

// =====================================================================
// PENGGANTI escapeshellarg() UNTUK SERVER
// =====================================================================

if (!function_exists('shell_arg_aman')) {
    function shell_arg_aman(string $nilai): string
    {
        return "'" . str_replace("'", "'\\''", $nilai) . "'";
    }
}

// =====================================================================
// FUNGSI UNTUK MENJALANKAN PYTHON DENGAN ENVIRONMENT YANG BENAR
// =====================================================================
//
// Gunakan fungsi ini jika ada file PHP yang perlu menjalankan Python.
//
// Contoh:
//
// $hasil = jalankan_python(
//     ['python_script.py', 'argumen1']
// );
//
// Fungsi ini memastikan HOME dan thread environment tersedia.
//

if (!function_exists('jalankan_python')) {

    function jalankan_python(
        array $argumen = [],
        ?int &$kode_keluar = null
    ): string {

        $python = PYTHON_BIN;

        // Pastikan executable Python memang ada.
        if (!is_file($python)) {

            $kode_keluar = 1;

            return
                "Python tidak ditemukan: "
                . $python;
        }

        // Perintah environment.
        $prefix =
            'HOME='
            . escapeshellarg('/home/aplb6542')
            . ' '
            . 'USER='
            . escapeshellarg('aplb6542')
            . ' '
            . 'OPENBLAS_NUM_THREADS=1 '
            . 'OMP_NUM_THREADS=1 '
            . 'MKL_NUM_THREADS=1 '
            . 'NUMEXPR_NUM_THREADS=1 ';

        // Python executable.
        $perintah =
            $prefix
            . escapeshellarg($python);

        // Tambahkan argumen.
        foreach ($argumen as $argumen_satu) {

            $perintah .= ' '
                . escapeshellarg(
                    (string)$argumen_satu
                );
        }

        $perintah .= ' 2>&1';

        $hasil = shell_exec($perintah);

        $kode_keluar = 0;

        if (!is_string($hasil)) {
            return '';
        }

        return $hasil;
    }
}


// =====================================================================
// LOGIN
// =====================================================================

function wajib_login(): void
{
    // Periksa timeout.
    validasi_session_timeout();

    // Periksa login.
    if (empty($_SESSION['id_pengguna'])) {

        header('Location: ../login.php');

        exit;
    }
}


// =====================================================================
// BERANDA SESUAI PERAN
// =====================================================================

function beranda_untuk_peran(string $peran): string
{
    return $peran === 'peneliti'
        ? 'impor_data'
        : 'beranda';
}


// =====================================================================
// VALIDASI PERAN
// =====================================================================

function wajib_peran(array $peran_diizinkan): void
{
    wajib_login();

    $peran_saat_ini =
        $_SESSION['peran'] ?? '';

    // Jika memiliki hak akses.
    if (
        in_array(
            $peran_saat_ini,
            $peran_diizinkan,
            true
        )
    ) {
        return;
    }


    // ================================================================
    // PROSES DI FOLDER PHP
    // ================================================================

    if (
        str_contains(
            $_SERVER['SCRIPT_NAME'] ?? '',
            '/php/'
        )
    ) {

        http_response_code(403);

        $tipe_konten =
            $_SERVER['CONTENT_TYPE'] ?? '';

        $menerima_json =
            str_contains(
                $_SERVER['HTTP_ACCEPT'] ?? '',
                'application/json'
            )
            ||
            str_contains(
                $tipe_konten,
                'application/json'
            );


        if (
            $menerima_json ||
            permintaan_ajax()
        ) {

            header(
                'Content-Type: application/json; charset=utf-8'
            );

            echo json_encode(
                [
                    'status' => 'gagal',

                    'pesan' =>
                        'Akun Anda (peran: '
                        . $peran_saat_ini
                        . ') tidak memiliki akses '
                        . 'ke fitur ini.'
                ],
                JSON_UNESCAPED_UNICODE
            );

        } else {

            tampilkan_halaman_error(
                'Akun Anda tidak memiliki akses '
                . 'ke fitur ini.'
            );
        }

        exit;
    }


    // ================================================================
    // HALAMAN BIASA
    // ================================================================

    $_SESSION['pesan_akses_ditolak'] =
        'Halaman tersebut khusus untuk peran lain '
        . 'dan tidak bisa diakses oleh akun Anda.';

    header(
        'Location: ../index.php?hal='
        . beranda_untuk_peran($peran_saat_ini)
    );

    exit;
}


// =====================================================================
// OUTPUT HTML AMAN
// =====================================================================

function aman(string $teks): string
{
    return htmlspecialchars(
        $teks,
        ENT_QUOTES,
        'UTF-8'
    );
}


// =====================================================================
// FORMAT ANGKA
// =====================================================================

function angka(
    float $nilai,
    int $desimal_maks = 2
): string {

    $dibulatkan =
        round(
            $nilai,
            $desimal_maks
        );

    if (
        $dibulatkan ==
        (int)$dibulatkan
    ) {

        return number_format(
            $dibulatkan,
            0,
            ',',
            '.'
        );
    }

    $teks =
        number_format(
            $dibulatkan,
            $desimal_maks,
            ',',
            '.'
        );

    // Hilangkan nol di belakang.
    $teks =
        rtrim(
            $teks,
            '0'
        );

    $teks =
        rtrim(
            $teks,
            ','
        );

    return $teks;
}


// =====================================================================
// KELOMPOK PAKAN
// =====================================================================

function kelompok_pakan(
    string $kategori
): string {

    $k =
        strtolower(
            $kategori
        );

    if (
        str_contains(
            $k,
            'ayam'
        )
    ) {
        return 'Ayam';
    }

    if (
        str_contains(
            $k,
            'bebek'
        )
    ) {
        return 'Bebek';
    }

    if (
        str_contains(
            $k,
            'babi'
        )
    ) {
        return 'Babi';
    }

    return '';
}


// =====================================================================
// SATUAN PRODUK
// =====================================================================

const SATUAN_DIIZINKAN = [
    'kg',
    'bungkus',
    'sak'
];


// =====================================================================
// FAKTOR KONVERSI KG
// =====================================================================

function faktor_konversi_kg(
    string $satuan
): ?float {

    $peta = [
        'kg'      => 1.0,
        'bungkus' => 1.0,
        'sak'     => 50.0
    ];

    return
        $peta[
            strtolower(
                trim($satuan)
            )
        ]
        ?? null;
}


// =====================================================================
// KODE PRODUK OTOMATIS
// =====================================================================

function hasilkan_kode_produk(
    PDO $koneksi,
    string $kategori
): string {

    $prefiks =
        strtoupper(
            substr(
                preg_replace(
                    '/[^A-Za-z]/',
                    '',
                    $kategori
                ) ?: 'PRD',
                0,
                3
            )
        );

    if ($prefiks === '') {
        $prefiks = 'PRD';
    }

    $nomor = 1;

    do {

        $kode_coba =
            $prefiks
            . '-'
            . str_pad(
                (string)$nomor,
                3,
                '0',
                STR_PAD_LEFT
            );

        $cek =
            $koneksi->prepare(
                'SELECT 1
                 FROM produk
                 WHERE kode_produk = :k'
            );

        $cek->execute(
            [
                'k' => $kode_coba
            ]
        );

        $nomor++;

    } while ($cek->fetch());

    return $kode_coba;
}