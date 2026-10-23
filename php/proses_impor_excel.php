<?php
/**
 * php/proses_impor_excel.php
 * -----------------------------------------------------------------
 * Menerima file .xlsx yang diunggah, membaca isinya lewat PembacaXlsx,
 * memvalidasi & memasukkan data transaksi secara massal.
 *
 * DUA FORMAT KOLOM YANG DIDUKUNG (terdeteksi otomatis dari header):
 *
 *  Format A — Template bawaan aplikasi:
 *      nama_produk | tanggal | jumlah | satuan | harga_satuan | catatan
 *
 *  Format B — Format laporan toko (mis. "Data Lengkap Penjualan"):
 *      Tanggal | Jenis Pakan | Nama Produk | Harga Satuan (Rp) |
 *      Jumlah Terjual (mis. "15 sak") | Total Penjualan (Rp)
 *
 * Produk yang belum terdaftar akan DIBUAT OTOMATIS (memakai kategori
 * dari kolom "Jenis Pakan"/"kategori" jika ada, dan satuan yang
 * terdeteksi dari data), sehingga tidak perlu mendaftarkan puluhan
 * produk satu-satu sebelum impor.
 * -----------------------------------------------------------------
 */

require_once __DIR__ . '/../config/pengaturan.php';
require_once __DIR__ . '/../config/koneksi_db.php';
require_once __DIR__ . '/../config/hitung_tren.php';
require_once __DIR__ . '/PembacaXlsx.php';
wajib_peran(['peneliti']);

function kembali_dengan_error_impor(string $pesan): void
{
    $_SESSION['pesan_impor_error'] = $pesan;
    header('Location: ../index.php?hal=impor_data');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['berkas_excel'])) {
    header('Location: ../index.php?hal=impor_data');
    exit;
}

$berkas = $_FILES['berkas_excel'];

if ($berkas['error'] !== UPLOAD_ERR_OK) {
    $pesan_kode = match ($berkas['error']) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Ukuran file melebihi batas maksimal server.',
        default => 'Gagal mengunggah file. Coba lagi.',
    };
    kembali_dengan_error_impor($pesan_kode);
}

$ekstensi = strtolower(pathinfo($berkas['name'], PATHINFO_EXTENSION));
if ($ekstensi !== 'xlsx') {
    kembali_dengan_error_impor('Format file harus .xlsx (Excel). Simpan ulang file Anda sebagai .xlsx jika masih .xls.');
}

if ($berkas['size'] > 20 * 1024 * 1024) {
    kembali_dengan_error_impor('Ukuran file terlalu besar (maksimal 20 MB).');
}

try {
    $pembaca = new PembacaXlsx();
    $baris_semua = $pembaca->baca($berkas['tmp_name']);
} catch (Throwable $e) {
    kembali_dengan_error_impor('Gagal membaca file Excel: ' . $e->getMessage());
    exit;
}

if (count($baris_semua) < 2) {
    kembali_dengan_error_impor('File tidak berisi data yang terbaca.');
}

// --- Cari baris header: telusuri 6 baris pertama, cari yang mengandung nama kolom dikenali ---
$alias_kolom = [
    'nama_produk'    => ['nama_produk', 'nama produk', 'produk'],
    'kode_produk'    => ['kode_produk', 'kode produk', 'kode'],
    'kategori'       => ['kategori', 'jenis_pakan', 'jenis pakan'],
    'tanggal'        => ['tanggal'],
    'jumlah'         => ['jumlah'],
    'satuan'         => ['satuan'],
    'jumlah_terjual' => ['jumlah_terjual', 'jumlah terjual'],
    'harga_satuan'   => ['harga_satuan', 'harga satuan (rp)', 'harga satuan'],
    'total_penjualan'=> ['total_penjualan', 'total penjualan (rp)', 'total penjualan', 'total_harga'],
    'catatan'        => ['catatan', 'keterangan'],
];

$indeks_baris_header = null;
$peta_kolom = [];

for ($i = 0; $i < min(6, count($baris_semua)); $i++) {
    $baris_ini = array_map(fn($v) => strtolower(trim((string)$v)), $baris_semua[$i]);
    $peta_percobaan = [];
    foreach ($alias_kolom as $kunci => $daftar_alias) {
        foreach ($baris_ini as $idx => $teks_kolom) {
            if (in_array($teks_kolom, $daftar_alias, true)) {
                $peta_percobaan[$kunci] = $idx;
                break;
            }
        }
    }
    // Baris dianggap header jika minimal ketemu nama_produk + (tanggal) + (jumlah ATAU jumlah_terjual)
    if (isset($peta_percobaan['nama_produk']) && isset($peta_percobaan['tanggal'])
        && (isset($peta_percobaan['jumlah']) || isset($peta_percobaan['jumlah_terjual']))) {
        $indeks_baris_header = $i;
        $peta_kolom = $peta_percobaan;
        break;
    }
}

if ($indeks_baris_header === null) {
    kembali_dengan_error_impor('Header kolom tidak dikenali. Pastikan file memiliki kolom nama_produk/Nama Produk, tanggal/Tanggal, dan jumlah/Jumlah Terjual.');
}

$format_gabungan = isset($peta_kolom['jumlah_terjual']); // Format B: "15 sak" dalam satu kolom

// --- Data referensi produk (dimuat ulang di tengah proses jika ada produk baru dibuat) ---
function muat_peta_produk(PDO $koneksi): array
{
    $peta = [];
    foreach ($koneksi->query("SELECT id_produk, nama_produk, satuan FROM produk") as $p) {
        $peta[strtolower(trim($p['nama_produk']))] = ['id' => (int)$p['id_produk'], 'satuan' => $p['satuan']];
    }
    return $peta;
}
$peta_produk = muat_peta_produk($koneksi);

$pernyataan_tambah_produk = $koneksi->prepare(
    'INSERT INTO produk (kode_produk, nama_produk, kategori, satuan, aktif) VALUES (:kode, :nama, :kategori, :satuan, 1)'
);

$pernyataan_cek_duplikat = $koneksi->prepare(
    'SELECT COUNT(*) FROM transaksi
     WHERE id_produk = :id_produk AND tanggal_transaksi = :tanggal AND jumlah = :jumlah
       AND satuan_transaksi = :satuan_transaksi
       AND harga_satuan = :harga_satuan AND total_penjualan = :total_penjualan'
);

$id_batch_impor = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(3)), 0, 6);

$pernyataan_insert = $koneksi->prepare(
    'INSERT INTO transaksi
        (id_produk, tanggal_transaksi, jumlah, satuan_transaksi, jumlah_kg, harga_satuan, total_penjualan, catatan, id_pengguna, id_batch_impor)
     VALUES
        (:id_produk, :tanggal, :jumlah, :satuan_transaksi, :jumlah_kg, :harga_satuan, :total_penjualan, :catatan, :id_pengguna, :id_batch_impor)'
);

$jumlah_berhasil = 0;
$jumlah_gagal = 0;
$jumlah_dilewati_duplikat = 0;
$jumlah_produk_baru = 0;
$daftar_error = [];

$koneksi->beginTransaction();

foreach ($baris_semua as $nomor_baris => $baris) {
    if ($nomor_baris <= $indeks_baris_header) continue; // lewati baris judul & header

    $nomor_baris_tampil = $nomor_baris + 1;

    $nama_produk_mentah = trim((string)($baris[$peta_kolom['nama_produk']] ?? ''));
    $tanggal_mentah     = $baris[$peta_kolom['tanggal']] ?? null;
    $kategori_mentah    = isset($peta_kolom['kategori']) ? trim((string)($baris[$peta_kolom['kategori']] ?? '')) : '';
    $kode_produk_mentah = isset($peta_kolom['kode_produk']) ? trim((string)($baris[$peta_kolom['kode_produk']] ?? '')) : '';
    $harga_mentah       = isset($peta_kolom['harga_satuan']) ? ($baris[$peta_kolom['harga_satuan']] ?? 0) : 0;
    $total_mentah       = isset($peta_kolom['total_penjualan']) ? ($baris[$peta_kolom['total_penjualan']] ?? null) : null;
    $catatan_mentah     = isset($peta_kolom['catatan']) ? trim((string)($baris[$peta_kolom['catatan']] ?? '')) : '';

    // Baris kosong / baris ringkasan (mis. "GRAND TOTAL ...") -> lewati diam-diam
    if ($nama_produk_mentah === '' || $tanggal_mentah === null || $tanggal_mentah === '') {
        continue;
    }

    // --- Ambil jumlah & satuan, tergantung format file ---
    $satuan_terdeteksi = null;
    if ($format_gabungan) {
        $teks_jumlah = trim((string)($baris[$peta_kolom['jumlah_terjual']] ?? ''));
        if (!preg_match('/^([\d.,]+)\s*(\S+)$/', $teks_jumlah, $cocok)) {
            $daftar_error[] = ['baris' => $nomor_baris_tampil, 'alasan' => "Kolom jumlah '$teks_jumlah' tidak bisa dibaca (format diharapkan: '15 sak')."];
            $jumlah_gagal++;
            continue;
        }
        $jumlah = (float)str_replace(',', '', $cocok[1]);
        $satuan_terdeteksi = $cocok[2];
    } else {
        $jumlah_mentah = $baris[$peta_kolom['jumlah']] ?? null;
        if (!is_numeric($jumlah_mentah) || (float)$jumlah_mentah <= 0) {
            $daftar_error[] = ['baris' => $nomor_baris_tampil, 'alasan' => "Jumlah '$jumlah_mentah' tidak valid."];
            $jumlah_gagal++;
            continue;
        }
        $jumlah = (float)$jumlah_mentah;
        $satuan_terdeteksi = isset($peta_kolom['satuan']) ? trim((string)($baris[$peta_kolom['satuan']] ?? '')) : 'pcs';
    }

    if ($jumlah <= 0) {
        $daftar_error[] = ['baris' => $nomor_baris_tampil, 'alasan' => 'Jumlah harus lebih besar dari 0.'];
        $jumlah_gagal++;
        continue;
    }

    // Normalisasi ejaan satuan yang maknanya sama dengan salah satu satuan
    // baku (kg/bungkus/sak), supaya konversi otomatis ke kg di halaman
    // Prediksi bisa berjalan. Satuan lain (mis. botol, ikat, buah) DIBIARKAN
    // apa adanya karena bukan varian ejaan dari kg/bungkus/sak.
    $peta_normalisasi_satuan = ['zak' => 'sak', 'karung' => 'sak', 'pak' => 'bungkus', 'bks' => 'bungkus'];
    $satuan_terdeteksi = $peta_normalisasi_satuan[strtolower($satuan_terdeteksi)] ?? $satuan_terdeteksi;

    // --- Validasi & normalisasi tanggal ---
    $tanggal_final = PembacaXlsx::normalisasiTanggal($tanggal_mentah);
    if (!$tanggal_final) {
        $daftar_error[] = ['baris' => $nomor_baris_tampil, 'alasan' => "Tanggal '$tanggal_mentah' tidak valid."];
        $jumlah_gagal++;
        continue;
    }

    // Tolak baris dengan tanggal jauh ke masa depan (biasanya salah format
    // kolom Excel, mis. tahun tertukar/tergeser). Satu baris seperti ini
    // saja bisa membuat data agregat mingguan punya banyak minggu kosong,
    // yang berakibat perhitungan MAPE di menu "Latih Ulang" meledak tidak
    // wajar (lihat DateTime batas di simpan_transaksi.php).
    if (strtotime($tanggal_final) > strtotime('+7 days')) {
        $daftar_error[] = ['baris' => $nomor_baris_tampil, 'alasan' => "Tanggal '$tanggal_mentah' jauh di masa depan, kemungkinan salah format. Baris dilewati."];
        $jumlah_gagal++;
        continue;
    }

    // --- Cari atau buat produk otomatis ---
    $kunci_produk = strtolower($nama_produk_mentah);
    if (!isset($peta_produk[$kunci_produk])) {
        try {
            $kode_final = $kode_produk_mentah;
            // Kode dari file dipakai hanya jika terisi, tidak sama persis dengan
            // nama produk, dan belum dipakai produk lain; jika tidak, buat otomatis.
            $kode_sudah_ada = false;
            if ($kode_final !== '') {
                $cek_kode_impor = $koneksi->prepare('SELECT 1 FROM produk WHERE kode_produk = :kode');
                $cek_kode_impor->execute(['kode' => $kode_final]);
                $kode_sudah_ada = (bool)$cek_kode_impor->fetch();
            }
            if ($kode_final === '' || strtolower($kode_final) === strtolower($nama_produk_mentah) || $kode_sudah_ada) {
                $kode_final = hasilkan_kode_produk($koneksi, $kategori_mentah !== '' ? $kategori_mentah : 'Umum');
            }
            $pernyataan_tambah_produk->execute([
                'kode'     => $kode_final,
                'nama'     => $nama_produk_mentah,
                'kategori' => $kategori_mentah !== '' ? $kategori_mentah : 'Umum',
                'satuan'   => $satuan_terdeteksi !== '' ? $satuan_terdeteksi : 'pcs',
            ]);
            $peta_produk[$kunci_produk] = ['id' => (int)$koneksi->lastInsertId(), 'satuan' => $satuan_terdeteksi];
            $jumlah_produk_baru++;
        } catch (PDOException $e) {
            $daftar_error[] = ['baris' => $nomor_baris_tampil, 'alasan' => "Gagal membuat produk baru '$nama_produk_mentah'."];
            $jumlah_gagal++;
            continue;
        }
    }
    $id_produk = $peta_produk[$kunci_produk]['id'];

    $harga_satuan = is_numeric($harga_mentah) ? (float)$harga_mentah : 0;
    $total_penjualan = is_numeric($total_mentah) ? (float)$total_mentah : ($jumlah * $harga_satuan);
    $satuan_transaksi_final = $satuan_terdeteksi !== '' ? strtolower($satuan_terdeteksi) : 'kg';
    $jumlah_kg = $jumlah * (faktor_konversi_kg($satuan_transaksi_final) ?? 1.0);

    // --- Cek duplikat: hanya lewati jika SELURUH kolom relevan sama persis
    // (produk, tanggal, jumlah, satuan, harga, total) -- mis. file yang sama
    // tidak sengaja diimpor dua kali. Transaksi berbeda yang kebetulan cuma
    // produk/tanggal/jumlahnya sama TETAP disimpan (lihat catatan di atas). ---
    $pernyataan_cek_duplikat->execute([
        'id_produk'         => $id_produk,
        'tanggal'           => $tanggal_final,
        'jumlah'            => $jumlah,
        'satuan_transaksi'  => $satuan_transaksi_final,
        'harga_satuan'      => $harga_satuan,
        'total_penjualan'   => $total_penjualan,
    ]);
    if ((int)$pernyataan_cek_duplikat->fetchColumn() > 0) {
        $jumlah_dilewati_duplikat++;
        continue;
    }

    try {
        $pernyataan_insert->execute([
            'id_produk'        => $id_produk,
            'tanggal'          => $tanggal_final,
            'jumlah'           => $jumlah,
            'satuan_transaksi' => $satuan_transaksi_final,
            'jumlah_kg'        => $jumlah_kg,
            'harga_satuan'     => $harga_satuan,
            'total_penjualan'  => $total_penjualan,
            'catatan'          => $catatan_mentah !== '' ? $catatan_mentah : null,
            'id_pengguna'      => $_SESSION['id_pengguna'],
            'id_batch_impor'   => $id_batch_impor,
        ]);
        $jumlah_berhasil++;
    } catch (PDOException $e) {
        $daftar_error[] = ['baris' => $nomor_baris_tampil, 'alasan' => 'Gagal menyimpan ke database.'];
        $jumlah_gagal++;
    }
}

$koneksi->commit();

// Perbarui tren_penjualan supaya data mingguan mencerminkan seluruh
// transaksi hasil impor batch ini (lihat config/hitung_tren.php).
if ($jumlah_berhasil > 0) {
    hitung_ulang_tren_penjualan($koneksi);
}

$_SESSION['ringkasan_impor'] = [
    'total'             => $jumlah_berhasil + $jumlah_gagal + $jumlah_dilewati_duplikat,
    'berhasil'          => $jumlah_berhasil,
    'gagal'             => $jumlah_gagal,
    'dilewati_duplikat' => $jumlah_dilewati_duplikat,
    'produk_baru'       => $jumlah_produk_baru,
    'id_batch_impor'    => $jumlah_berhasil > 0 ? $id_batch_impor : null,
    'daftar_error'      => array_slice($daftar_error, 0, 50),
];

header('Location: ../index.php?hal=impor_data');
exit;
