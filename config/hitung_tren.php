<?php
/**
 * config/hitung_tren.php
 * -----------------------------------------------------------------
 * Menghitung ULANG tabel tren_penjualan dari data transaksi terbaru.
 *
 * Alasan tabel ini ada: sebelumnya, agregasi mingguan (dasar dari
 * tren penjualan) hanya dihitung sementara (on-the-fly) oleh skrip
 * Python saat proses Latih Ulang / Prediksi berjalan, dan TIDAK
 * pernah disimpan sebagai data tersendiri. Artinya alur "data
 * penjualan -> tren penjualan" tidak terlihat langsung di database,
 * hanya tersembunyi di dalam logika kode.
 *
 * hitung_ulang_tren_penjualan() menutup celah itu: setiap kali ada
 * transaksi baru (input manual maupun impor Excel), fungsi ini
 * membaca ULANG seluruh tabel transaksi, mengagregasinya per minggu
 * (Senin sebagai awal minggu, sesuai metodologi Bab III/IV skripsi),
 * lalu menyimpan hasilnya ke tabel tren_penjualan beserta arah tren
 * (naik/turun/stabil) dibanding minggu sebelumnya.
 *
 * Cakupan produk yang dihitung SAMA PERSIS dengan pipeline resmi di
 * python/olah_data.py (KATEGORI_PAKAN): hanya kategori "Pakan Ayam",
 * "Pakan Babi", dan "Pakan Bebek" -- supaya angka total kg/minggu di
 * tabel ini selalu konsisten dengan angka yang dipakai untuk melatih
 * model GLM/XGBoost resmi.
 * -----------------------------------------------------------------
 */

const KATEGORI_PAKAN_TREN = ['Pakan Ayam', 'Pakan Babi', 'Pakan Bebek'];

/** Ambang batas persentase perubahan supaya dianggap "Naik"/"Turun" (bukan "Stabil"). */
const AMBANG_STABIL_PERSEN = 3.0;

/**
 * Mengembalikan tanggal Senin (awal minggu, format Y-m-d) dari sebuah
 * tanggal transaksi, konsisten dengan resample('W-MON', label='left')
 * yang dipakai olah_data.py di sisi Python.
 */
function awal_minggu_senin(string $tanggal): string
{
    $dt = new DateTime($tanggal);
    $hari_iso = (int)$dt->format('N'); // 1 (Senin) .. 7 (Minggu)
    if ($hari_iso > 1) {
        $dt->modify('-' . ($hari_iso - 1) . ' days');
    }
    return $dt->format('Y-m-d');
}

/**
 * Hitung ulang seluruh isi tren_penjualan dari tabel transaksi saat ini.
 * Aman dipanggil berkali-kali (idempotent): tabel dikosongkan lalu
 * diisi ulang dari awal setiap kali dipanggil, supaya data selalu
 * konsisten dengan tabel transaksi yang terbaru.
 */
function hitung_ulang_tren_penjualan(PDO $koneksi): void
{
    $placeholder = implode(',', array_fill(0, count(KATEGORI_PAKAN_TREN), '?'));

    $stmt = $koneksi->prepare(
        "SELECT t.tanggal_transaksi AS tanggal,
                SUM(CASE WHEN t.jumlah_kg > 0 THEN t.jumlah_kg ELSE t.jumlah END) AS total_kg
         FROM transaksi t
         JOIN produk p ON p.id_produk = t.id_produk
         WHERE p.kategori IN ($placeholder)
         GROUP BY t.tanggal_transaksi
         ORDER BY t.tanggal_transaksi"
    );
    $stmt->execute(KATEGORI_PAKAN_TREN);
    $baris_harian = $stmt->fetchAll();

    // Agregasi harian -> mingguan (kunci = tanggal Senin awal minggu)
    $per_minggu = [];
    foreach ($baris_harian as $b) {
        $minggu = awal_minggu_senin($b['tanggal']);
        $per_minggu[$minggu] = ($per_minggu[$minggu] ?? 0) + (float)$b['total_kg'];
    }
    ksort($per_minggu);

    $koneksi->beginTransaction();
    try {
        $koneksi->exec('DELETE FROM tren_penjualan');

        $insert = $koneksi->prepare(
            'INSERT INTO tren_penjualan
                (minggu_mulai, total_kg_terjual, arah_tren, persentase_perubahan, dihitung_pada)
             VALUES (:minggu, :total, :arah, :persen, :dihitung_pada)'
        );

        $minggu_sebelumnya = null;
        foreach ($per_minggu as $minggu => $total) {
            $arah = 'Stabil';
            $persen = null;
            if ($minggu_sebelumnya !== null && $minggu_sebelumnya > 0) {
                $persen = (($total - $minggu_sebelumnya) / $minggu_sebelumnya) * 100;
                if ($persen > AMBANG_STABIL_PERSEN) {
                    $arah = 'Naik';
                } elseif ($persen < -AMBANG_STABIL_PERSEN) {
                    $arah = 'Turun';
                }
            }

            $insert->execute([
                'minggu'        => $minggu,
                'total'         => $total,
                'arah'          => $arah,
                'persen'        => $persen,
                'dihitung_pada' => date('Y-m-d H:i:s'),
            ]);

            $minggu_sebelumnya = $total;
        }

        $koneksi->commit();
    } catch (Throwable $e) {
        $koneksi->rollBack();
        error_log('[Dashboard Toko Sabrina] Gagal menghitung ulang tren_penjualan: ' . $e->getMessage());
    }
}
