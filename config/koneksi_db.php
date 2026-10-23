<?php
/**
 * config/koneksi_db.php
 * -----------------------------------------------------------------
 * Koneksi database SQLite untuk Dashboard Toko Sabrina.
 * Tidak perlu MySQL/phpMyAdmin sama sekali — database tersimpan
 * sebagai satu file portable: database.db di root folder aplikasi.
 *
 * Saat pertama kali diakses, file database.db akan dibuat otomatis
 * beserta 2 akun awal (2 peran):
 *   nama pengguna : pemilik   | kata sandi : sabrina2026
 *   nama pengguna : peneliti  | kata sandi : peneliti2026
 * -----------------------------------------------------------------
 */

define('PATH_DATABASE', dirname(__DIR__) . '/database.db');

try {
    $koneksi = new PDO('sqlite:' . PATH_DATABASE);
    $koneksi->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $koneksi->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $koneksi->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    http_response_code(500);
    die('Gagal membuka database SQLite. Pastikan ekstensi PHP "pdo_sqlite" aktif. Detail teknis: ' . $e->getMessage());
}

/**
 * Inisialisasi tabel otomatis jika belum ada (dicek lewat tabel "pengguna").
 * Ini membuat aplikasi "zero-config": tinggal upload folder, langsung jalan.
 */
function pastikan_database_siap(PDO $koneksi): void
{
    $cek = $koneksi->query("SELECT name FROM sqlite_master WHERE type='table' AND name='pengguna'")->fetch();
    if ($cek) {
        return; // sudah pernah diinisialisasi
    }

    $koneksi->exec("
        CREATE TABLE pengguna (
            id_pengguna     INTEGER PRIMARY KEY AUTOINCREMENT,
            nama_pengguna   TEXT NOT NULL UNIQUE,
            kata_sandi_hash TEXT NOT NULL,
            nama_lengkap    TEXT NOT NULL DEFAULT 'Pemilik Toko',
            peran           TEXT NOT NULL DEFAULT 'pemilik',
            dibuat_pada     TEXT DEFAULT (datetime('now','localtime'))
        );

        CREATE TABLE produk (
            id_produk    INTEGER PRIMARY KEY AUTOINCREMENT,
            kode_produk  TEXT NOT NULL DEFAULT '',
            nama_produk  TEXT NOT NULL UNIQUE,
            kategori     TEXT NOT NULL DEFAULT 'Umum',
            satuan       TEXT NOT NULL DEFAULT 'pcs',
            aktif        INTEGER NOT NULL DEFAULT 1,
            dibuat_pada  TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE UNIQUE INDEX idx_produk_kode ON produk(kode_produk);

        CREATE TABLE transaksi (
            id_transaksi      INTEGER PRIMARY KEY AUTOINCREMENT,
            id_produk         INTEGER NOT NULL,
            tanggal_transaksi TEXT NOT NULL,
            jumlah            REAL NOT NULL,
            harga_satuan      REAL DEFAULT 0,
            total_penjualan   REAL DEFAULT 0,
            catatan           TEXT,
            id_pengguna       INTEGER,
            id_batch_impor    TEXT,
            dibuat_pada       TEXT DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
            FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL
        );
        CREATE INDEX idx_transaksi_tanggal ON transaksi(tanggal_transaksi);
        CREATE INDEX idx_transaksi_produk_tanggal ON transaksi(id_produk, tanggal_transaksi);
        CREATE INDEX idx_transaksi_batch ON transaksi(id_batch_impor);

        CREATE TABLE hasil_prediksi (
            id_prediksi      INTEGER PRIMARY KEY AUTOINCREMENT,
            id_produk        INTEGER NOT NULL,
            tanggal_prediksi TEXT NOT NULL,
            nilai_prediksi   REAL NOT NULL,
            model_dipakai    TEXT NOT NULL DEFAULT 'GLM',
            dibuat_pada      TEXT DEFAULT (datetime('now','localtime')),
            FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE
        );
        CREATE INDEX idx_prediksi_produk_tgl ON hasil_prediksi(id_produk, tanggal_prediksi);

        CREATE TABLE evaluasi_model (
            id_evaluasi   INTEGER PRIMARY KEY AUTOINCREMENT,
            nama_model    TEXT NOT NULL,
            mae           REAL NOT NULL,
            rmse          REAL NOT NULL,
            mape          REAL NOT NULL,
            jumlah_data   INTEGER NOT NULL DEFAULT 0,
            model_terbaik INTEGER NOT NULL DEFAULT 0,
            dilatih_pada  TEXT DEFAULT (datetime('now','localtime'))
        );
    ");

    // Buat akun awal (hash dibuat langsung oleh PHP saat ini, jadi selalu
    // valid tanpa perlu generate manual seperti versi MySQL sebelumnya):
    //   1) pemilik  / sabrina2026   -> input transaksi & lihat hasil prediksi
    //   2) peneliti / peneliti2026  -> unggah dataset, latih & evaluasi model
    $hash_pemilik = password_hash('sabrina2026', PASSWORD_BCRYPT);
    $koneksi->prepare("INSERT INTO pengguna (nama_pengguna, kata_sandi_hash, nama_lengkap, peran) VALUES ('pemilik', :hash, 'Pemilik Toko Sabrina', 'pemilik')")
            ->execute(['hash' => $hash_pemilik]);

    $hash_peneliti = password_hash('peneliti2026', PASSWORD_BCRYPT);
    $koneksi->prepare("INSERT INTO pengguna (nama_pengguna, kata_sandi_hash, nama_lengkap, peran) VALUES ('peneliti', :hash, 'Peneliti', 'peneliti')")
            ->execute(['hash' => $hash_peneliti]);
}

/**
 * Tabel tren_penjualan: hasil agregasi mingguan dari tabel transaksi
 * (lihat config/hitung_tren.php). Tabel ini secara eksplisit membuktikan
 * alur "data penjualan -> tren penjualan" di level struktur database,
 * bukan hanya dihitung sementara di dalam skrip Python.
 */
function pastikan_tabel_tren_siap(PDO $koneksi): void
{
    $cek = $koneksi->query("SELECT name FROM sqlite_master WHERE type='table' AND name='tren_penjualan'")->fetch();
    if ($cek) {
        return;
    }

    $koneksi->exec("
        CREATE TABLE tren_penjualan (
            id_tren               INTEGER PRIMARY KEY AUTOINCREMENT,
            minggu_mulai          TEXT NOT NULL UNIQUE,
            total_kg_terjual      REAL NOT NULL DEFAULT 0,
            arah_tren             TEXT NOT NULL DEFAULT 'Stabil',
            persentase_perubahan  REAL,
            dihitung_pada         TEXT DEFAULT (datetime('now','localtime'))
        );
        CREATE INDEX idx_tren_minggu ON tren_penjualan(minggu_mulai);
    ");
}

pastikan_database_siap($koneksi);
pastikan_tabel_tren_siap($koneksi);
jalankan_migrasi_tambahan($koneksi);

/**
 * Migrasi ringan untuk database yang sudah pernah dibuat SEBELUM fitur
 * "kode produk" ditambahkan. Aman dijalankan berulang kali (idempotent):
 * hanya melakukan perubahan jika kolom/index memang belum ada.
 */
function jalankan_migrasi_tambahan(PDO $koneksi): void
{
    $kolom_produk = $koneksi->query("PRAGMA table_info(produk)")->fetchAll();
    $nama_kolom = array_column($kolom_produk, 'name');

    if (!in_array('kode_produk', $nama_kolom, true)) {
        $koneksi->exec("ALTER TABLE produk ADD COLUMN kode_produk TEXT NOT NULL DEFAULT ''");

        // Buatkan kode otomatis untuk semua produk lama yang belum punya kode
        $produk_tanpa_kode = $koneksi->query("SELECT id_produk, kategori FROM produk WHERE kode_produk = ''")->fetchAll();
        foreach ($produk_tanpa_kode as $p) {
            $prefiks = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $p['kategori']) ?: 'PRD', 0, 3));
            $nomor = 1;
            do {
                $kode_coba = $prefiks . '-' . str_pad((string)$nomor, 3, '0', STR_PAD_LEFT);
                $cek = $koneksi->prepare("SELECT 1 FROM produk WHERE kode_produk = :k");
                $cek->execute(['k' => $kode_coba]);
                $nomor++;
            } while ($cek->fetch());

            $koneksi->prepare("UPDATE produk SET kode_produk = :k WHERE id_produk = :id")
                    ->execute(['k' => $kode_coba, 'id' => $p['id_produk']]);
        }

        $koneksi->exec("CREATE UNIQUE INDEX IF NOT EXISTS idx_produk_kode ON produk(kode_produk)");
    }

    // ------------------------------------------------------------------
    // Migrasi: satuan per-transaksi (mis. seorang pelanggan bisa beli
    // produk yang sama dalam "sak" hari ini dan "kg" besok) + kolom
    // jumlah_kg (jumlah yang sudah dikonversi ke kilogram), supaya
    // data latih model prediksi tetap konsisten walau satuan penjualan
    // berbeda-beda per transaksi.
    // ------------------------------------------------------------------
    $kolom_transaksi = $koneksi->query("PRAGMA table_info(transaksi)")->fetchAll();
    $nama_kolom_transaksi = array_column($kolom_transaksi, 'name');

    if (!in_array('satuan_transaksi', $nama_kolom_transaksi, true)) {
        $koneksi->exec("ALTER TABLE transaksi ADD COLUMN satuan_transaksi TEXT NOT NULL DEFAULT ''");
    }
    if (!in_array('jumlah_kg', $nama_kolom_transaksi, true)) {
        $koneksi->exec("ALTER TABLE transaksi ADD COLUMN jumlah_kg REAL NOT NULL DEFAULT 0");
    }

    // Isi data lama yang satuan_transaksi/jumlah_kg-nya masih kosong,
    // memakai satuan tetap produk (perilaku lama) sebagai perkiraan awal.
    $baris_belum_lengkap = $koneksi->query(
        "SELECT t.id_transaksi, t.jumlah, p.satuan
         FROM transaksi t JOIN produk p ON p.id_produk = t.id_produk
         WHERE t.satuan_transaksi = '' "
    )->fetchAll();

    // ------------------------------------------------------------------
    // Migrasi: pastikan akun "peneliti" tersedia juga di database yang
    // sudah pernah dibuat SEBELUM sistem 2-peran (peneliti & pemilik) ini
    // ditambahkan. Aman dijalankan berulang kali: hanya dibuat kalau
    // memang belum ada pengguna dengan nama "peneliti".
    // ------------------------------------------------------------------
    $sudah_ada_peneliti = $koneksi->query("SELECT 1 FROM pengguna WHERE nama_pengguna = 'peneliti'")->fetch();
    if (!$sudah_ada_peneliti) {
        $hash_peneliti = password_hash('peneliti2026', PASSWORD_BCRYPT);
        $koneksi->prepare("INSERT INTO pengguna (nama_pengguna, kata_sandi_hash, nama_lengkap, peran) VALUES ('peneliti', :hash, 'Peneliti', 'peneliti')")
                ->execute(['hash' => $hash_peneliti]);
    }

    if ($baris_belum_lengkap) {
        $peta_faktor_kg = ['kg' => 1.0, 'bungkus' => 1.0, 'sak' => 50.0];
        $perbarui = $koneksi->prepare(
            'UPDATE transaksi SET satuan_transaksi = :satuan, jumlah_kg = :jumlah_kg WHERE id_transaksi = :id'
        );
        // PENTING: dibungkus satu transaksi supaya SQLite tidak melakukan
        // fsync per baris (yang membuat ribuan UPDATE satu-satu menjadi
        // sangat lambat dan bisa melebihi batas waktu eksekusi PHP).
        $koneksi->beginTransaction();
        try {
            foreach ($baris_belum_lengkap as $baris) {
                $satuan = $baris['satuan'] ?: 'pcs';
                $faktor = $peta_faktor_kg[strtolower(trim($satuan))] ?? 1.0;
                $perbarui->execute([
                    'satuan'    => $satuan,
                    'jumlah_kg' => (float)$baris['jumlah'] * $faktor,
                    'id'        => $baris['id_transaksi'],
                ]);
            }
            $koneksi->commit();
        } catch (Throwable $e) {
            $koneksi->rollBack();
            throw $e;
        }
    }
}

