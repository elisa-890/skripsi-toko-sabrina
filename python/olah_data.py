"""
python/olah_data.py
---------------------------------------------------------------------
Modul bersama: mengambil data transaksi dari database SQLite
(database.db) dan mengubahnya menjadi tabel yang siap dipakai untuk
melatih model (GLM & XGBoost) maupun untuk membuat prediksi.

Modul ini menyediakan DUA pipeline yang terpisah dan tidak saling
mempengaruhi:

1. PIPELINE RESMI — AGREGAT MINGGUAN (ambil_data_agregat_mingguan +
   bangun_fitur_agregat_mingguan). Ini adalah pipeline yang PERSIS
   mengikuti metodologi penelitian pada BAB III/IV skripsi: seluruh
   produk digabung menjadi satu deret waktu mingguan, dengan fitur
   lag_1, lag_2, lag_4 (nilai minggu-minggu sebelumnya), roll_4,
   roll_8 (rata-rata bergulir 4 & 8 minggu). Metrik dari pipeline inilah
   yang disimpan sebagai metrik RESMI model pada tabel evaluasi_model,
   sehingga selalu konsisten dengan angka yang dilaporkan di skripsi
   (Bab IV: MAE/RMSE/MAPE hasil analisis Google Colab).

2. PIPELINE TAMBAHAN — PER PRODUK HARIAN (ambil_data_transaksi +
   bangun_fitur). Ini adalah pipeline TAMBAHAN di luar cakupan
   metodologi resmi skripsi, dipakai HANYA agar aplikasi dapat
   menyajikan perkiraan per produk pada halaman Prediksi dan grafik
   "Perkiraan Penjualan 7 Hari" di Beranda. Metrik dari pipeline ini
   TIDAK disimpan sebagai metrik resmi model (lihat latih_model.py).

Memakai modul sqlite3 bawaan Python — tidak perlu memasang pustaka
database tambahan apapun.
---------------------------------------------------------------------
"""

import os
import sys
import sqlite3
import pandas as pd

PATH_DATABASE = os.path.join(os.path.dirname(__file__), '..', 'database.db')


def buka_koneksi() -> sqlite3.Connection:
    if not os.path.exists(PATH_DATABASE):
        print('{"error": "File database.db belum ada. Buka aplikasi lewat browser terlebih dahulu agar database dibuat otomatis."}')
        sys.exit(1)
    koneksi = sqlite3.connect(PATH_DATABASE)
    koneksi.row_factory = sqlite3.Row
    return koneksi


# =====================================================================
# 1. PIPELINE RESMI — AGREGAT MINGGUAN (sesuai metodologi BAB III/IV)
# =====================================================================

KOLOM_FITUR_AGREGAT = ['lag_1', 'lag_2', 'lag_4', 'roll_4', 'roll_8', 'week_num', 'month', 'quarter']
KOLOM_TARGET_AGREGAT = 'total_kg'


# Kategori produk yang dihitung sebagai "pakan" untuk target model resmi,
# PERSIS cakupan "Total Penjualan" pada notebook Colab (jumlah kolom Pakan
# Ayam + Pakan Babi + Pakan Bebek hasil pivot Jenis Pakan) — TIDAK termasuk
# Obat, Peralatan, atau Suplemen. Kategori produk pakan murni (BR1, BR2,
# Jagung Giling, Konsentrat Babi, Pellet Finisher, dst.) tercatat sebagai
# "Pakan Ayam", "Pakan Babi", atau "Pakan Bebek" di database aplikasi.
KATEGORI_PAKAN = ['Pakan Ayam', 'Pakan Babi', 'Pakan Bebek']


def ambil_data_agregat_mingguan() -> pd.DataFrame:
    """
    Mengambil transaksi dari produk BERKATEGORI PAKAN SAJA (lihat
    KATEGORI_PAKAN di atas) digabung menjadi satu deret waktu harian,
    sesuai cakupan "Total Penjualan" pada notebook Colab yang secara
    sengaja mengecualikan kategori Obat/Peralatan/Suplemen dari target
    model resmi.
    """
    koneksi = buka_koneksi()
    placeholder = ','.join('?' for _ in KATEGORI_PAKAN)
    kueri = f"""
        SELECT t.tanggal_transaksi AS tanggal,
               SUM(CASE WHEN t.jumlah_kg > 0 THEN t.jumlah_kg ELSE t.jumlah END) AS jumlah_terjual
        FROM transaksi t
        JOIN produk p ON p.id_produk = t.id_produk
        WHERE p.kategori IN ({placeholder})
        GROUP BY t.tanggal_transaksi
        ORDER BY t.tanggal_transaksi
    """
    df = pd.read_sql_query(kueri, koneksi, params=KATEGORI_PAKAN)
    koneksi.close()
    return df


def bangun_fitur_agregat_mingguan(df: pd.DataFrame) -> pd.DataFrame:
    """
    Mengubah data transaksi harian gabungan menjadi data mingguan
    dengan fitur lag & rolling mean, sesuai metodologi yang digunakan
    untuk evaluasi model resmi pada skripsi:
        - agregasi data harian menjadi mingguan (total kg/minggu)
        - lag_1, lag_2, lag_4  (nilai penjualan 1, 2, 4 minggu lalu)
        - roll_4, roll_8       (rata-rata bergulir 4 dan 8 minggu)
    """
    if df.empty or len(df) == 0:
        return pd.DataFrame(columns=['tanggal', KOLOM_TARGET_AGREGAT] + KOLOM_FITUR_AGREGAT)

    df = df.copy()
    df['tanggal'] = pd.to_datetime(df['tanggal'])
    df = df.set_index('tanggal').sort_index()

    # Isi hari tanpa transaksi dengan 0 agar total mingguan akurat.
    # Tidak ada filter tanggal tambahan; seluruh data transaksi digunakan.
    rentang_lengkap = pd.date_range(df.index.min(), df.index.max(), freq='D')
    df = df.reindex(rentang_lengkap, fill_value=0)
    df.index.name = 'tanggal'

    # Agregasi ke mingguan (awal minggu = Senin), total kg per minggu.
    # method_end="Minggu" dibentuk dgn to_period('W').start_time pada notebook
    # Colab, hasilnya SAMA dengan resample('W-MON', label='left', closed='left')
    # di sini (keduanya = tanggal Senin awal minggu tsb).
    mingguan = df['jumlah_terjual'].resample('W-MON', label='left', closed='left').sum()
    mingguan = mingguan.rename(KOLOM_TARGET_AGREGAT).reset_index()

    # -------------------------------------------------------------
    # URUTAN & RUMUS DI BAWAH INI SENGAJA DIBUAT PERSIS MENIRU
    # notebook Colab (PREDIKSITREN.ipynb, cell 31-43), TERMASUK satu
    # keputusan metodologis notebook yang sebetulnya kurang ideal
    # (dijelaskan di bawah) — supaya angka MAE/RMSE/MAPE di sistem
    # konsisten 1:1 dengan yang dilaporkan di skripsi (Bab IV).
    # -------------------------------------------------------------

    # 1) Fitur lag (cell 31), lalu buang baris yang belum punya lag_4
    #    (cell 32: df_feature.dropna()) -> 4 baris pertama hilang.
    mingguan['lag_1'] = mingguan[KOLOM_TARGET_AGREGAT].shift(1)
    mingguan['lag_2'] = mingguan[KOLOM_TARGET_AGREGAT].shift(2)
    mingguan['lag_4'] = mingguan[KOLOM_TARGET_AGREGAT].shift(4)
    mingguan = mingguan.dropna(subset=['lag_1', 'lag_2', 'lag_4']).reset_index(drop=True)

    # 2) Rolling mean (cell 37) dihitung SETELAH baris di atas dibuang, dan
    #    TANPA di-shift(1) lebih dulu -> window 4/8 minggu ini MEMASUKKAN
    #    nilai minggu yang sedang diprediksi itu sendiri (bukan hanya masa
    #    lalu). Ini konsisten dengan kode Colab, tapi secara metodologi
    #    murni ini bentuk "data leakage" ringan (model "mengintip" sebagian
    #    nilai targetnya sendiri lewat roll_4/roll_8) — angka error jadi
    #    terlihat lebih rendah daripada rolling mean yang benar (di-shift).
    #    Dipertahankan apa adanya di sini semata supaya cocok dengan yang
    #    sudah dilaporkan di skripsi; BUKAN praktik yang disarankan untuk
    #    dipakai ulang di penelitian lain.
    mingguan['roll_4'] = mingguan[KOLOM_TARGET_AGREGAT].rolling(window=4).mean()
    mingguan['roll_8'] = mingguan[KOLOM_TARGET_AGREGAT].rolling(window=8).mean()
    mingguan = mingguan.dropna(subset=['roll_4', 'roll_8']).reset_index(drop=True)

    # 3) Fitur kalender (cell 42): week_num pakai ISO week (isocalendar),
    #    BUKAN %U (yang dipakai versi sebelumnya) -> dua standar ini bisa
    #    beda 1 terutama di sekitar pergantian tahun.
    mingguan['week_num'] = mingguan['tanggal'].dt.isocalendar().week.astype(int)
    mingguan['month'] = mingguan['tanggal'].dt.month
    mingguan['quarter'] = mingguan['tanggal'].dt.quarter

    return mingguan


# =====================================================================
# 2. PIPELINE TAMBAHAN — PER PRODUK HARIAN (fitur khusus aplikasi,
#    DI LUAR metodologi resmi yang dilaporkan pada skripsi)
# =====================================================================

KOLOM_FITUR = [
    'hari_dalam_minggu', 'hari_dalam_bulan', 'bulan',
    'hari_sejak_awal', 'rata2_bergerak_7hari'
]
KOLOM_TARGET = 'jumlah_terjual'


def ambil_data_transaksi(id_produk: int = None) -> pd.DataFrame:
    """
    Mengambil data transaksi harian teragregasi per produk. Dipakai
    KHUSUS untuk fitur tambahan aplikasi (perkiraan per produk),
    bukan untuk metrik resmi model.
    """
    koneksi = buka_koneksi()
    kondisi = f"WHERE t.id_produk = {int(id_produk)}" if id_produk else ""
    kueri = f"""
        SELECT t.id_produk, p.nama_produk, t.tanggal_transaksi AS tanggal,
               SUM(CASE WHEN t.jumlah_kg > 0 THEN t.jumlah_kg ELSE t.jumlah END) AS jumlah_terjual
        FROM transaksi t
        JOIN produk p ON p.id_produk = t.id_produk
        {kondisi}
        GROUP BY t.id_produk, t.tanggal_transaksi
        ORDER BY t.id_produk, t.tanggal_transaksi
    """
    df = pd.read_sql_query(kueri, koneksi)
    koneksi.close()
    return df


def bangun_fitur(df: pd.DataFrame) -> pd.DataFrame:
    """
    Mengubah data transaksi mentah menjadi fitur harian per produk
    (fitur tambahan aplikasi, di luar metodologi resmi skripsi).
    """
    if df.empty:
        return df

    hasil_semua = []
    for id_produk, kelompok in df.groupby('id_produk'):
        kelompok = kelompok.copy()
        kelompok['tanggal'] = pd.to_datetime(kelompok['tanggal'])
        kelompok = kelompok.set_index('tanggal').sort_index()

        rentang_lengkap = pd.date_range(kelompok.index.min(), kelompok.index.max(), freq='D')
        kelompok = kelompok.reindex(rentang_lengkap)
        kelompok['id_produk'] = id_produk
        kelompok['jumlah_terjual'] = kelompok['jumlah_terjual'].fillna(0)
        kelompok.index.name = 'tanggal'
        kelompok = kelompok.reset_index()

        kelompok['hari_dalam_minggu'] = kelompok['tanggal'].dt.weekday
        kelompok['hari_dalam_bulan'] = kelompok['tanggal'].dt.day
        kelompok['bulan'] = kelompok['tanggal'].dt.month
        kelompok['hari_sejak_awal'] = (kelompok['tanggal'] - kelompok['tanggal'].min()).dt.days
        kelompok['rata2_bergerak_7hari'] = (
            kelompok['jumlah_terjual'].shift(1).rolling(window=7, min_periods=1).mean().fillna(0)
        )

        hasil_semua.append(kelompok)

    return pd.concat(hasil_semua, ignore_index=True)


if __name__ == '__main__':
    # Mode uji cepat dari command line:
    #   python olah_data.py agregat        -> cek pipeline resmi mingguan
    #   python olah_data.py [id_produk]    -> cek pipeline per produk harian
    if len(sys.argv) > 1 and sys.argv[1] == 'agregat':
        df_mentah = ambil_data_agregat_mingguan()
        df_mingguan = bangun_fitur_agregat_mingguan(df_mentah)
        print(df_mingguan.tail(10).to_string())
    else:
        id_produk_arg = int(sys.argv[1]) if len(sys.argv) > 1 else None
        df_mentah = ambil_data_transaksi(id_produk_arg)
        df_fitur = bangun_fitur(df_mentah)
        print(df_fitur.tail(10).to_string())
