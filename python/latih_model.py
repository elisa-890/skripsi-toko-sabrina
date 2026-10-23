"""
python/latih_model.py
---------------------------------------------------------------------
Melatih & membandingkan dua model prediksi penjualan.

PENTING — dua pipeline terpisah dijalankan di sini:

1. MODEL RESMI (agregat mingguan, seluruh produk digabung), PERSIS
   mengikuti metodologi penelitian pada BAB III/IV skripsi (REVISI):
   GLM (Gaussian, link identitas) & XGBoost (n_estimators=100,
   learning_rate=0.1, max_depth=4) dilatih pada data mingguan dengan
   fitur lag/rolling, dibagi 80:20 secara KRONOLOGIS (80% data
   pertama = latih, 20% terakhir = uji — lihat bagi_data_kronologis()),
   BUKAN random split — sesuai keputusan pembimbing/penguji bahwa
   split kronologis lebih tepat secara teori untuk data deret waktu.
   Model terbaik ditentukan dengan aturan yang SAMA seperti di skripsi
   (unggul pada minimal 2 dari 3 metrik: MAE, RMSE, MAPE). HANYA
   metrik dari pipeline inilah yang disimpan ke tabel evaluasi_model,
   sehingga angka yang tampil di halaman Latih Model dan Prediksi
   selalu konsisten dengan angka yang dilaporkan di skripsi (VERSI
   REVISI — notebook Colab & BAB IV perlu dilatih ulang & diperbarui
   mengikuti perubahan ini, lihat catatan di bagi_data_kronologis()).

2. MODEL TAMBAHAN (per produk, harian), fitur khusus aplikasi di luar
   cakupan skripsi, dipakai supaya halaman Prediksi & grafik Beranda
   bisa menyajikan perkiraan per produk. Model ini TETAP dilatih dan
   disimpan (dipakai oleh prediksi.py), tetapi metriknya TIDAK ditulis
   ke tabel evaluasi_model, agar tidak tertukar dengan metrik resmi.

Dipanggil dari PHP (halaman/latih_ulang.php) lewat shell_exec, atau
manual: python3 latih_model.py
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
from xgboost import XGBRegressor
from sklearn.metrics import mean_absolute_error, mean_squared_error, mean_absolute_percentage_error

from olah_data import (
    ambil_data_transaksi, bangun_fitur, KOLOM_FITUR, KOLOM_TARGET, buka_koneksi,
    ambil_data_agregat_mingguan, bangun_fitur_agregat_mingguan,
    KOLOM_FITUR_AGREGAT, KOLOM_TARGET_AGREGAT,
)

warnings.filterwarnings('ignore')

PATH_MODELS = os.path.join(os.path.dirname(__file__), '..', 'models')


def hitung_mape(y_asli, y_prediksi) -> float:
    """MAPE dengan penanganan pembagian nol."""
    y_asli = np.array(y_asli, dtype=float)
    y_prediksi = np.array(y_prediksi, dtype=float)
    mask = y_asli != 0
    if mask.sum() == 0:
        return 0.0
    return float(np.mean(np.abs((y_asli[mask] - y_prediksi[mask]) / y_asli[mask])) * 100)


def bagi_data_kronologis(df: pd.DataFrame, rasio_uji: float = 0.2):
    """
    Membagi data 80:20 secara BERURUTAN menurut waktu (80% data paling
    awal = data latih, 20% data paling akhir = data uji) — dipakai oleh
    KEDUA pipeline (resmi/agregat mingguan & tambahan/per-produk).
    Dipilih karena lebih tepat secara teori untuk data deret waktu
    dibanding random split (model tidak "mengintip" pola dari periode
    yang lebih baru saat dilatih pada data yang lebih lama).
    """
    df = df.sort_values('tanggal')
    titik_bagi = int(len(df) * (1 - rasio_uji))
    return df.iloc[:titik_bagi], df.iloc[titik_bagi:]


def bagi_data_acak(df: pd.DataFrame, rasio_uji: float = 0.2, seed: int = 42):
    """
    Replikasi persis sklearn.model_selection.train_test_split(X, y,
    test_size=0.2, random_state=42) dari notebook Colab: index diacak
    dengan seed tetap (42), lalu 20% terakhir dari urutan acak itu jadi
    data uji. Dipakai KHUSUS untuk pipeline RESMI (agregat mingguan) agar
    metrik MAE/RMSE/MAPE yang dihasilkan cocok dengan yang dilaporkan di
    skripsi. Catatan: pembagian acak pada data deret waktu (bukan
    kronologis) berisiko test set "bocor" berdekatan waktu dengan train
    set; ini dipertahankan sesuai notebook aslinya, bukan praktik yang
    disarankan untuk pekerjaan baru.
    """
    from sklearn.model_selection import train_test_split
    data_latih, data_uji = train_test_split(df, test_size=rasio_uji, random_state=seed)
    return data_latih, data_uji


def latih_glm_generik(data_latih, data_uji, kolom_fitur, kolom_target):
    # has_constant='add' dipaksa agar jumlah kolom X_latih & X_uji selalu
    # konsisten walau salah satu fitur bernilai konstan di sebagian data.
    X_latih = sm.add_constant(data_latih[kolom_fitur], has_constant='add')
    y_latih = data_latih[kolom_target]
    X_uji = sm.add_constant(data_uji[kolom_fitur], has_constant='add')

    # family=Gaussian(), link=Identity -> KONSISTEN dengan model yang benar-benar
    # dilatih & dilaporkan di Google Colab (lihat Gambar 4.23: "Model Family:
    # Gaussian", "Link Function: Identity"). Target variabel (total kg terjual
    # per minggu) adalah data kontinu, bukan data hitungan (count) diskrit,
    # sehingga distribusi Gaussian dengan link identitas memang lebih sesuai
    # dibanding Poisson/log yang dirancang untuk data count.
    model = sm.GLM(y_latih, X_latih, family=sm.families.Gaussian()).fit()
    prediksi_uji = model.predict(X_uji)
    return model, prediksi_uji


def latih_xgboost_generik(data_latih, data_uji, kolom_fitur, kolom_target,
                           n_estimators=200, max_depth=4, learning_rate=0.08,
                           subsample=0.9, colsample_bytree=0.9):
    X_latih = data_latih[kolom_fitur]
    y_latih = data_latih[kolom_target]
    X_uji = data_uji[kolom_fitur]

    model = XGBRegressor(
        n_estimators=n_estimators, max_depth=max_depth, learning_rate=learning_rate,
        subsample=subsample, colsample_bytree=colsample_bytree, random_state=42,
        objective='reg:squarederror',
    )
    model.fit(X_latih, y_latih)
    prediksi_uji = model.predict(X_uji)
    return model, prediksi_uji


def latih_xgboost_resmi(data_latih, data_uji, kolom_fitur, kolom_target):
    """
    Parameter PERSIS notebook Colab (PREDIKSITREN.ipynb cell 64):
    n_estimators=100, learning_rate=0.1, max_depth=4, random_state=42,
    TANPA subsample/colsample_bytree (dibiarkan default XGBoost = 1.0).
    Dipakai khusus untuk pipeline RESMI (agregat mingguan).
    """
    X_latih = data_latih[kolom_fitur]
    y_latih = data_latih[kolom_target]
    X_uji = data_uji[kolom_fitur]

    model = XGBRegressor(
        objective='reg:squarederror',
        n_estimators=100,
        learning_rate=0.1,
        max_depth=4,
        random_state=42,
    )
    model.fit(X_latih, y_latih)
    prediksi_uji = model.predict(X_uji)
    return model, prediksi_uji


def hitung_metrik(y_asli, y_prediksi) -> dict:
    return {
        'mae': float(mean_absolute_error(y_asli, y_prediksi)),
        'rmse': float(np.sqrt(mean_squared_error(y_asli, y_prediksi))),
        'mape': hitung_mape(y_asli, y_prediksi),
        'n': len(y_asli),
    }


def hitung_metrik_resmi(y_asli, y_prediksi) -> dict:
    """
    Sama seperti hitung_metrik(), TAPI MAPE dihitung memakai
    sklearn.metrics.mean_absolute_percentage_error (persis notebook
    Colab cell 71-72), bukan hitung_mape() versi sistem. Bedanya:
    sklearn TIDAK membuang baris dengan y_asli = 0 (memakai epsilon
    kecil sebagai pembagi), sedangkan hitung_mape() sistem membuang
    baris tsb sepenuhnya. Dipakai khusus untuk pipeline RESMI.
    """
    return {
        'mae': float(mean_absolute_error(y_asli, y_prediksi)),
        'rmse': float(np.sqrt(mean_squared_error(y_asli, y_prediksi))),
        'mape': float(mean_absolute_percentage_error(y_asli, y_prediksi) * 100),
        'n': len(y_asli),
    }


def simpan_evaluasi(koneksi, nama_model: str, mae: float, rmse: float, mape: float, n: int, terbaik: bool):
    koneksi.execute(
        """INSERT INTO evaluasi_model (nama_model, mae, rmse, mape, jumlah_data, model_terbaik)
           VALUES (?, ?, ?, ?, ?, ?)""",
        (nama_model, mae, rmse, mape, n, int(terbaik))
    )
    koneksi.commit()


# =====================================================================
# 1. MODEL RESMI — agregat mingguan (sesuai metodologi BAB III/IV)
# =====================================================================

def latih_model_resmi(koneksi):
    """
    Melatih GLM (Gaussian) & XGBoost pada data agregat mingguan (seluruh
    produk digabung), PERSIS mengikuti metodologi Google Colab yang
    dilaporkan pada skripsi. Mengembalikan (metrik_glm, metrik_xgb,
    glm_terbaik) dan MENYIMPAN metrik ini ke tabel evaluasi_model
    sebagai metrik resmi.
    """
    df_mentah = ambil_data_agregat_mingguan()
    df_mingguan = bangun_fitur_agregat_mingguan(df_mentah)

    if len(df_mingguan) < 10:
        return None, None, None, 'Data belum cukup untuk melatih model resmi (butuh riwayat transaksi minimal ±10 minggu).'

    # bagi_data_kronologis() = pembagian 80:20 berurutan menurut waktu
    # (BUKAN diacak). Dipilih sesuai keputusan pembimbing/penguji: split
    # kronologis lebih tepat secara teori untuk data deret waktu (time
    # series) dibanding random split, meski berarti notebook Colab &
    # BAB IV skripsi perlu dilatih ulang & direvisi mengikuti ini.
    data_latih, data_uji = bagi_data_kronologis(df_mingguan)
    if len(data_uji) == 0 or len(data_latih) == 0:
        return None, None, None, 'Pembagian data latih/uji gagal (data mingguan terlalu sedikit).'

    model_glm, pred_glm = latih_glm_generik(data_latih, data_uji, KOLOM_FITUR_AGREGAT, KOLOM_TARGET_AGREGAT)
    model_xgb, pred_xgb = latih_xgboost_resmi(data_latih, data_uji, KOLOM_FITUR_AGREGAT, KOLOM_TARGET_AGREGAT)

    y_asli = data_uji[KOLOM_TARGET_AGREGAT].tolist()
    metrik_glm = hitung_metrik_resmi(y_asli, pred_glm)
    metrik_xgb = hitung_metrik_resmi(y_asli, pred_xgb)

    print("=== HASIL EVALUASI GLM ===")
    print(f"MAE  : {metrik_glm['mae']:.4f}")
    print(f"RMSE : {metrik_glm['rmse']:.4f}")
    print(f"MAPE : {metrik_glm['mape']:.4f}%")
    print()
    print("=== HASIL EVALUASI XGBOOST ===")
    print(f"MAE  : {metrik_xgb['mae']:.4f}")
    print(f"RMSE : {metrik_xgb['rmse']:.4f}")
    print(f"MAPE : {metrik_xgb['mape']:.4f}%")
    print()

    # Model terbaik = unggul pada minimal 2 dari 3 metrik, SAMA seperti
    # aturan penentuan model terbaik pada BAB IV skripsi.
    glm_menang = 0
    if metrik_glm['mae'] <= metrik_xgb['mae']:
        glm_menang += 1
    if metrik_glm['rmse'] <= metrik_xgb['rmse']:
        glm_menang += 1
    if metrik_glm['mape'] <= metrik_xgb['mape']:
        glm_menang += 1
    glm_terbaik = glm_menang >= 2

    os.makedirs(PATH_MODELS, exist_ok=True)
    with open(os.path.join(PATH_MODELS, 'model_glm_agregat.pkl'), 'wb') as f:
        pickle.dump({'model': model_glm, 'kolom_fitur': KOLOM_FITUR_AGREGAT}, f)
    with open(os.path.join(PATH_MODELS, 'model_xgboost_agregat.pkl'), 'wb') as f:
        pickle.dump({'model': model_xgb, 'kolom_fitur': KOLOM_FITUR_AGREGAT}, f)

    simpan_evaluasi(koneksi, 'GLM', metrik_glm['mae'], metrik_glm['rmse'], metrik_glm['mape'], metrik_glm['n'], glm_terbaik)
    simpan_evaluasi(koneksi, 'XGBoost', metrik_xgb['mae'], metrik_xgb['rmse'], metrik_xgb['mape'], metrik_xgb['n'], not glm_terbaik)

    return metrik_glm, metrik_xgb, glm_terbaik, None


# =====================================================================
# 2. MODEL TAMBAHAN — per produk harian (fitur khusus aplikasi,
#    TIDAK memengaruhi metrik resmi pada evaluasi_model)
# =====================================================================

def latih_model_per_produk():
    """
    Melatih model per produk (harian) untuk keperluan perkiraan per
    produk pada halaman Prediksi & grafik Beranda. Model disimpan ke
    models/model_glm.pkl dan models/model_xgboost.pkl (dipakai oleh
    prediksi.py). Metrik dari fungsi ini SENGAJA tidak ditulis ke
    tabel evaluasi_model — lihat catatan pada bagian atas berkas ini.
    """
    df_mentah = ambil_data_transaksi()
    if df_mentah.empty:
        return 0

    df_fitur = bangun_fitur(df_mentah)

    model_glm_per_produk = {}
    model_xgb_per_produk = {}

    for id_produk, kelompok in df_fitur.groupby('id_produk'):
        if len(kelompok) < 14:
            continue  # data terlalu sedikit untuk split latih/uji yang bermakna

        data_latih, data_uji = bagi_data_kronologis(kelompok)
        if len(data_uji) == 0 or len(data_latih) == 0:
            continue

        try:
            model_glm, _ = latih_glm_generik(data_latih, data_uji, KOLOM_FITUR, KOLOM_TARGET)
            model_glm_per_produk[int(id_produk)] = model_glm
        except Exception as e:
            print(f"Peringatan: GLM per-produk gagal untuk produk {id_produk}: {e}", file=sys.stderr)

        try:
            model_xgb, _ = latih_xgboost_generik(data_latih, data_uji, KOLOM_FITUR, KOLOM_TARGET)
            model_xgb_per_produk[int(id_produk)] = model_xgb
        except Exception as e:
            print(f"Peringatan: XGBoost per-produk gagal untuk produk {id_produk}: {e}", file=sys.stderr)

    os.makedirs(PATH_MODELS, exist_ok=True)
    with open(os.path.join(PATH_MODELS, 'model_glm.pkl'), 'wb') as f:
        pickle.dump({'model_per_produk': model_glm_per_produk, 'kolom_fitur': KOLOM_FITUR}, f)
    with open(os.path.join(PATH_MODELS, 'model_xgboost.pkl'), 'wb') as f:
        pickle.dump({'model_per_produk': model_xgb_per_produk, 'kolom_fitur': KOLOM_FITUR}, f)

    return len(model_glm_per_produk) or len(model_xgb_per_produk)


# =====================================================================
# Entry point
# =====================================================================

def latih_semua_produk():
    koneksi = buka_koneksi()
    try:
        metrik_glm, metrik_xgb, glm_terbaik, pesan_error = latih_model_resmi(koneksi)
    finally:
        koneksi.close()

    if pesan_error:
        print(json.dumps({'status': 'gagal', 'pesan': pesan_error}))
        return

    # Model tambahan per produk dilatih SETELAH model resmi berhasil,
    # dan kegagalannya tidak menggagalkan proses (fitur ini bersifat
    # tambahan/opsional bagi aplikasi).
    jumlah_produk_dilatih = 0
    try:
        jumlah_produk_dilatih = latih_model_per_produk()
    except Exception as e:
        print(f"Peringatan: pelatihan model per-produk gagal: {e}", file=sys.stderr)

    print(json.dumps({
        'status': 'berhasil',
        'model_terbaik': 'GLM' if glm_terbaik else 'XGBoost',
        'evaluasi_glm': metrik_glm,
        'evaluasi_xgboost': metrik_xgb,
        'jumlah_produk_dilatih': jumlah_produk_dilatih,
        'catatan': 'Metrik di atas dihitung dari model resmi (agregat mingguan), konsisten dengan metodologi BAB III/IV skripsi.',
    }, default=str))


if __name__ == '__main__':
    latih_semua_produk()
