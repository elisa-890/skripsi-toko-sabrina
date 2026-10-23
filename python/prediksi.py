"""
python/prediksi.py
---------------------------------------------------------------------
Membuat prediksi penjualan N hari ke depan untuk satu atau semua
produk, menggunakan model terbaik yang tersimpan (ditentukan dari
tabel evaluasi_model). Hasil prediksi disimpan ke tabel hasil_prediksi
dan juga dicetak sebagai JSON ke stdout (dibaca oleh php/ambil_hasil.php).

Cara pakai:
    python3 prediksi.py <id_produk> <jumlah_hari>
    python3 prediksi.py semua <jumlah_hari>
---------------------------------------------------------------------
"""

import os
import sys
import json
import pickle
import warnings
import numpy as np
import pandas as pd
import statsmodels.api as sm

from olah_data import ambil_data_transaksi, bangun_fitur, KOLOM_FITUR, buka_koneksi

warnings.filterwarnings('ignore')

PATH_MODELS = os.path.join(os.path.dirname(__file__), '..', 'models')


def ambil_model_terbaik_nama(koneksi) -> str:
    baris = koneksi.execute(
        "SELECT nama_model FROM evaluasi_model WHERE model_terbaik = 1 ORDER BY dilatih_pada DESC LIMIT 1"
    ).fetchone()
    return baris['nama_model'] if baris else 'GLM'


def muat_model(nama_model: str):
    nama_file = 'model_glm.pkl' if nama_model == 'GLM' else 'model_xgboost.pkl'
    path_file = os.path.join(PATH_MODELS, nama_file)
    if not os.path.exists(path_file):
        return None
    with open(path_file, 'rb') as f:
        return pickle.load(f)


def buat_fitur_masa_depan(df_riwayat_produk: pd.DataFrame, jumlah_hari: int) -> pd.DataFrame:
    """
    Membangun baris fitur untuk N hari ke depan setelah tanggal
    terakhir yang tercatat, mengikuti pola fitur yang sama dengan
    olah_data.bangun_fitur (hari_dalam_minggu, tren waktu, dst).
    """
    tanggal_terakhir = df_riwayat_produk['tanggal'].max()
    hari_sejak_awal_terakhir = df_riwayat_produk['hari_sejak_awal'].max()
    rata2_terakhir = df_riwayat_produk['jumlah_terjual'].tail(7).mean()

    baris = []
    for i in range(1, jumlah_hari + 1):
        tanggal_baru = tanggal_terakhir + pd.Timedelta(days=i)
        baris.append({
            'tanggal': tanggal_baru,
            'hari_dalam_minggu': tanggal_baru.weekday(),
            'hari_dalam_bulan': tanggal_baru.day,
            'bulan': tanggal_baru.month,
            'hari_sejak_awal': hari_sejak_awal_terakhir + i,
            'rata2_bergerak_7hari': rata2_terakhir,
        })
    return pd.DataFrame(baris)


def prediksi_satu_produk(id_produk: int, jumlah_hari: int, nama_model: str, paket_model) -> list:
    df_mentah = ambil_data_transaksi(id_produk)
    if df_mentah.empty:
        return []

    df_fitur = bangun_fitur(df_mentah)
    df_masa_depan = buat_fitur_masa_depan(df_fitur, jumlah_hari)

    model_per_produk = paket_model['model_per_produk']
    if id_produk not in model_per_produk:
        return []

    model = model_per_produk[id_produk]

    if nama_model == 'GLM':
        X = sm.add_constant(df_masa_depan[KOLOM_FITUR], has_constant='add')
        prediksi = model.predict(X)
    else:
        X = df_masa_depan[KOLOM_FITUR]
        prediksi = model.predict(X)

    # --- Batasi (clip) hasil prediksi ke rentang yang masuk akal secara bisnis ---
    # Untuk produk dengan data jarang/tidak stabil, ekstrapolasi tren linear ke
    # masa depan tetap bisa "meledak" jadi angka raksasa yang tidak masuk akal
    # (bahkan Infinity), yang juga bikin hasilnya gagal diproses sebagai JSON.
    # Kita batasi maksimum prediksi ke beberapa kali lipat dari rekor penjualan
    # harian tertinggi produk ini.
    rekor_harian_tertinggi = df_fitur['jumlah_terjual'].max()
    batas_atas_wajar = max(rekor_harian_tertinggi * 5, 10)  # minimal 10 agar produk baru/jarang tidak selalu dipangkas ke 0

    prediksi = np.nan_to_num(prediksi, nan=0.0, posinf=batas_atas_wajar, neginf=0.0)
    prediksi = np.clip(prediksi, 0, batas_atas_wajar)  # penjualan tidak bisa negatif & tidak masuk akal jika meledak

    hasil = []
    for tanggal, nilai in zip(df_masa_depan['tanggal'], prediksi):
        hasil.append({
            'tanggal': tanggal.strftime('%Y-%m-%d'),
            'nilai': round(float(nilai), 2)
        })
    return hasil


def simpan_prediksi_ke_db(koneksi, id_produk: int, hasil: list, nama_model: str):
    koneksi.execute("DELETE FROM hasil_prediksi WHERE id_produk = ? AND tanggal_prediksi >= date('now','localtime')", (id_produk,))
    for baris in hasil:
        koneksi.execute(
            """INSERT INTO hasil_prediksi (id_produk, tanggal_prediksi, nilai_prediksi, model_dipakai)
               VALUES (?, ?, ?, ?)""",
            (id_produk, baris['tanggal'], baris['nilai'], nama_model)
        )
    koneksi.commit()


def jalankan(id_produk_arg: str, jumlah_hari: int):
    koneksi = buka_koneksi()
    nama_model = ambil_model_terbaik_nama(koneksi)
    paket_model = muat_model(nama_model)

    if paket_model is None:
        print(json.dumps({'status': 'gagal', 'pesan': f'Model {nama_model} belum dilatih. Jalankan latih ulang terlebih dahulu.'}))
        koneksi.close()
        return

    if id_produk_arg == 'semua':
        kursor = koneksi.execute("SELECT id_produk FROM produk WHERE aktif = 1")
        daftar_id = [b['id_produk'] for b in kursor.fetchall()]
    else:
        daftar_id = [int(id_produk_arg)]

    hasil_semua_produk = {}
    for id_produk in daftar_id:
        hasil = prediksi_satu_produk(id_produk, jumlah_hari, nama_model, paket_model)
        if hasil:
            simpan_prediksi_ke_db(koneksi, id_produk, hasil, nama_model)
            hasil_semua_produk[id_produk] = hasil

    koneksi.close()
    print(json.dumps({
        'status': 'berhasil',
        'model_dipakai': nama_model,
        'hasil': hasil_semua_produk
    }, default=str))


if __name__ == '__main__':
    if len(sys.argv) < 3:
        print(json.dumps({'status': 'gagal', 'pesan': 'Argumen kurang. Pakai: prediksi.py <id_produk|semua> <jumlah_hari>'}))
        sys.exit(1)

    id_produk_arg = sys.argv[1]
    jumlah_hari = int(sys.argv[2])
    jalankan(id_produk_arg, jumlah_hari)
