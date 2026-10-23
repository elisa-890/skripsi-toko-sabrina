-- =====================================================================
-- SKEMA DATABASE SQLite: Dashboard Toko Sabrina
-- Catatan: file ini hanya untuk REFERENSI/DOKUMENTASI struktur tabel.
-- Anda TIDAK PERLU menjalankan file ini secara manual — aplikasi akan
-- otomatis membuat database.db beserta semua tabelnya sendiri saat
-- pertama kali diakses (lihat config/koneksi_db.php).
-- =====================================================================

PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS pengguna (
    id_pengguna     INTEGER PRIMARY KEY AUTOINCREMENT,
    nama_pengguna   TEXT NOT NULL UNIQUE,
    kata_sandi_hash TEXT NOT NULL,
    nama_lengkap    TEXT NOT NULL DEFAULT 'Pemilik Toko',
    peran           TEXT NOT NULL DEFAULT 'pemilik',
    dibuat_pada     TEXT DEFAULT (datetime('now','localtime'))
);

-- Produk (mis. BR1, BR2, Jagung Giling, Konsentrat Babi, dst).
-- Setiap produk punya SATU satuan tetap (kg, sak, bungkus, dst) sesuai
-- kebiasaan pencatatan di data asli toko.
CREATE TABLE IF NOT EXISTS produk (
    id_produk    INTEGER PRIMARY KEY AUTOINCREMENT,
    nama_produk  TEXT NOT NULL UNIQUE,
    kategori     TEXT NOT NULL DEFAULT 'Umum',   -- mis. 'Pakan Ayam', 'Obat Babi'
    satuan       TEXT NOT NULL DEFAULT 'pcs',    -- mis. 'sak', 'kg', 'bungkus'
    aktif        INTEGER NOT NULL DEFAULT 1,
    dibuat_pada  TEXT DEFAULT (datetime('now','localtime'))
);

CREATE TABLE IF NOT EXISTS transaksi (
    id_transaksi      INTEGER PRIMARY KEY AUTOINCREMENT,
    id_produk         INTEGER NOT NULL,
    tanggal_transaksi TEXT NOT NULL,     -- format YYYY-MM-DD
    jumlah            REAL NOT NULL,     -- sesuai satuan produk
    harga_satuan      REAL DEFAULT 0,
    total_penjualan   REAL DEFAULT 0,
    catatan           TEXT,
    id_pengguna       INTEGER,
    id_batch_impor    TEXT,              -- penanda sesi impor Excel (NULL = input manual)
    dibuat_pada       TEXT DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE,
    FOREIGN KEY (id_pengguna) REFERENCES pengguna(id_pengguna) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_transaksi_tanggal ON transaksi(tanggal_transaksi);
CREATE INDEX IF NOT EXISTS idx_transaksi_produk_tanggal ON transaksi(id_produk, tanggal_transaksi);
CREATE INDEX IF NOT EXISTS idx_transaksi_batch ON transaksi(id_batch_impor);

CREATE TABLE IF NOT EXISTS hasil_prediksi (
    id_prediksi      INTEGER PRIMARY KEY AUTOINCREMENT,
    id_produk        INTEGER NOT NULL,
    tanggal_prediksi TEXT NOT NULL,
    nilai_prediksi   REAL NOT NULL,
    model_dipakai    TEXT NOT NULL DEFAULT 'GLM',
    dibuat_pada      TEXT DEFAULT (datetime('now','localtime')),
    FOREIGN KEY (id_produk) REFERENCES produk(id_produk) ON DELETE CASCADE
);
CREATE INDEX IF NOT EXISTS idx_prediksi_produk_tgl ON hasil_prediksi(id_produk, tanggal_prediksi);

CREATE TABLE IF NOT EXISTS evaluasi_model (
    id_evaluasi   INTEGER PRIMARY KEY AUTOINCREMENT,
    nama_model    TEXT NOT NULL,
    mae           REAL NOT NULL,
    rmse          REAL NOT NULL,
    mape          REAL NOT NULL,
    jumlah_data   INTEGER NOT NULL DEFAULT 0,
    model_terbaik INTEGER NOT NULL DEFAULT 0,
    dilatih_pada  TEXT DEFAULT (datetime('now','localtime'))
);

-- Akun awal: 2 peran berbeda (lihat BACA_SAYA.txt untuk detail hak akses)
--   pengguna 'pemilik'  , sandi 'sabrina2026'  -> transaksi & baca hasil prediksi
--   pengguna 'peneliti' , sandi 'peneliti2026' -> unggah data, latih & evaluasi model
INSERT OR IGNORE INTO pengguna (id_pengguna, nama_pengguna, kata_sandi_hash, nama_lengkap, peran)
VALUES (1, 'pemilik', '__HASH_AKAN_DIISI_OTOMATIS__', 'Pemilik Toko Sabrina', 'pemilik');
INSERT OR IGNORE INTO pengguna (id_pengguna, nama_pengguna, kata_sandi_hash, nama_lengkap, peran)
VALUES (2, 'peneliti', '__HASH_AKAN_DIISI_OTOMATIS__', 'Peneliti', 'peneliti');
