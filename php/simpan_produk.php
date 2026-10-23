<?php
require_once __DIR__ . '/../config/pengaturan.php';
require_once __DIR__ . '/../config/koneksi_db.php';
wajib_peran(['pemilik']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php?hal=kelola_produk');
    exit;
}

$aksi = $_POST['aksi'] ?? '';

function kode_sudah_dipakai(PDO $koneksi, string $kode, ?int $kecuali_id = null): bool
{
    if ($kecuali_id) {
        $cek = $koneksi->prepare('SELECT 1 FROM produk WHERE kode_produk = :kode AND id_produk != :id');
        $cek->execute(['kode' => $kode, 'id' => $kecuali_id]);
    } else {
        $cek = $koneksi->prepare('SELECT 1 FROM produk WHERE kode_produk = :kode');
        $cek->execute(['kode' => $kode]);
    }
    return (bool)$cek->fetch();
}

if ($aksi === 'tambah') {
    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $kategori    = trim($_POST['kategori'] ?? '') ?: 'Umum';
    $satuan      = trim($_POST['satuan'] ?? '');
    $kode_produk = trim($_POST['kode_produk'] ?? '');

    if ($nama_produk === '' || $satuan === '' || $kode_produk === '') {
        $_SESSION['pesan_produk_error'] = 'Nama produk, kode produk, dan satuan wajib diisi.';
        header('Location: ../index.php?hal=kelola_produk');
        exit;
    }

    if (strtolower($kode_produk) === strtolower($nama_produk)) {
        $_SESSION['pesan_produk_error'] = 'Kode Produk tidak boleh sama persis dengan Nama Produk. Isi kode yang berbeda, mis. singkatan atau nomor urut (contoh: PAK-001).';
        header('Location: ../index.php?hal=kelola_produk');
        exit;
    }

    if (!in_array(strtolower($satuan), SATUAN_DIIZINKAN, true)) {
        $_SESSION['pesan_produk_error'] = 'Satuan harus salah satu dari: ' . implode(', ', SATUAN_DIIZINKAN) . '.';
        header('Location: ../index.php?hal=kelola_produk');
        exit;
    }

    if (kode_sudah_dipakai($koneksi, $kode_produk)) {
        $_SESSION['pesan_produk_error'] = "Kode produk '$kode_produk' sudah dipakai produk lain. Gunakan kode lain atau kosongkan agar dibuat otomatis.";
        header('Location: ../index.php?hal=kelola_produk');
        exit;
    }

    try {
        $pernyataan = $koneksi->prepare(
            'INSERT INTO produk (kode_produk, nama_produk, kategori, satuan, aktif) VALUES (:kode, :nama, :kategori, :satuan, 1)'
        );
        $pernyataan->execute(['kode' => $kode_produk, 'nama' => $nama_produk, 'kategori' => $kategori, 'satuan' => $satuan]);
        $_SESSION['pesan_produk_sukses'] = "Produk '$nama_produk' (kode: $kode_produk) berhasil ditambahkan.";
    } catch (PDOException $e) {
        $_SESSION['pesan_produk_error'] = "Gagal menambah produk. Kemungkinan nama '$nama_produk' sudah terdaftar sebelumnya.";
    }

    header('Location: ../index.php?hal=kelola_produk');
    exit;
}

if ($aksi === 'edit') {
    $id_produk   = filter_input(INPUT_POST, 'id_produk', FILTER_VALIDATE_INT);
    $kode_produk = trim($_POST['kode_produk'] ?? '');
    $kategori    = trim($_POST['kategori'] ?? '') ?: 'Umum';
    $satuan      = trim($_POST['satuan'] ?? '');

    if (!$id_produk || $kode_produk === '' || $satuan === '') {
        $_SESSION['pesan_produk_error'] = 'Data tidak lengkap untuk menyimpan perubahan.';
        header('Location: ../index.php?hal=kelola_produk');
        exit;
    }

    $cek_nama = $koneksi->prepare('SELECT nama_produk FROM produk WHERE id_produk = :id');
    $cek_nama->execute(['id' => $id_produk]);
    $nama_produk_sekarang = $cek_nama->fetchColumn();

    if ($nama_produk_sekarang !== false && strtolower($kode_produk) === strtolower((string)$nama_produk_sekarang)) {
        $_SESSION['pesan_produk_error'] = 'Kode Produk tidak boleh sama persis dengan Nama Produk.';
        header('Location: ../index.php?hal=kelola_produk');
        exit;
    }

    if (kode_sudah_dipakai($koneksi, $kode_produk, $id_produk)) {
        $_SESSION['pesan_produk_error'] = "Kode produk '$kode_produk' sudah dipakai produk lain.";
        header('Location: ../index.php?hal=kelola_produk');
        exit;
    }

    $koneksi->prepare('UPDATE produk SET kode_produk = :kode, kategori = :kategori, satuan = :satuan WHERE id_produk = :id')
            ->execute(['kode' => $kode_produk, 'kategori' => $kategori, 'satuan' => $satuan, 'id' => $id_produk]);

    $_SESSION['pesan_produk_sukses'] = 'Perubahan produk berhasil disimpan.';
    header('Location: ../index.php?hal=kelola_produk');
    exit;
}

if ($aksi === 'toggle_aktif') {
    $id_produk = filter_input(INPUT_POST, 'id_produk', FILTER_VALIDATE_INT);
    if ($id_produk) {
        $koneksi->prepare('UPDATE produk SET aktif = 1 - aktif WHERE id_produk = :id')
                ->execute(['id' => $id_produk]);
        $_SESSION['pesan_produk_sukses'] = 'Status produk berhasil diperbarui.';
    }
    header('Location: ../index.php?hal=kelola_produk');
    exit;
}

header('Location: ../index.php?hal=kelola_produk');
exit;
