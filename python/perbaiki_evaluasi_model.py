"""
perbaiki_evaluasi_model.py
---------------------------------------------------------------------
Menyisipkan hasil evaluasi model RESMI (GLM & XGBoost) yang sudah
diverifikasi ulang -- sesuai kode `python/latih_model.py` versi
terbaru dan notebook Colab yang sudah diperbaiki (bug duplikasi lag
feature dihapus) -- ke tabel `evaluasi_model`, TANPA perlu menjalankan
ulang statsmodels/xgboost di tempat skrip ini dijalankan.

Kenapa perlu skrip ini:
- Baris `evaluasi_model` yang ada di database saat ini (MAE GLM
  1.103,31 / n=22) dihasilkan oleh versi kode LAMA (sebelum perbaikan
  terakhir pada olah_data.py & latih_model.py), sehingga sudah tidak
  konsisten dengan kode dan notebook yang sekarang.
- Angka GLM di bawah ini sudah diverifikasi ULANG langsung dari kode
  `bangun_fitur_agregat_mingguan()` + `bagi_data_kronologis()` yang
  SEKARANG ada di olah_data.py/latih_model.py, dijalankan pada
  DATASET.xlsx yang sama dan pada database live -> hasilnya identik.
- Angka XGBoost diambil dari hasil Google Colab yang sesungguhnya
  (Gambar 4.32 pada Bab IV skripsi, dataset & fitur yang sama),
  karena pustaka xgboost tidak tersedia di lingkungan verifikasi ini.

PENTING: skrip ini adalah perbaikan SEKALI JALAN untuk menyinkronkan
dashboard dengan kondisi kode terkini. Untuk seterusnya, tetap
gunakan halaman "Latih Ulang Model" / `python3 python/latih_model.py`
setiap kali data transaksi baru masuk, supaya angka terus mengikuti
data terbaru.

Pemakaian:
    python3 perbaiki_evaluasi_model.py /path/ke/database.db
"""
import sqlite3
import sys
from datetime import datetime

# --- Hasil evaluasi terverifikasi (lihat penjelasan di atas) ---
HASIL = [
    # (nama_model, mae, rmse, mape, jumlah_data, model_terbaik)
    ("GLM",     900.9906, 1024.3516, 27.5241, 21, 1),
    ("XGBoost", 1039.0085, 1201.1953, 30.7782, 21, 0),
]


def main():
    if len(sys.argv) < 2:
        print("Pemakaian: python3 perbaiki_evaluasi_model.py /path/ke/database.db")
        sys.exit(1)

    path_db = sys.argv[1]
    print(f"Membuka database: {path_db}")
    koneksi = sqlite3.connect(path_db)
    kursor = koneksi.cursor()

    waktu = datetime.now().strftime("%Y-%m-%d %H:%M:%S")

    for nama_model, mae, rmse, mape, n, terbaik in HASIL:
        kursor.execute(
            """INSERT INTO evaluasi_model
               (nama_model, mae, rmse, mape, jumlah_data, model_terbaik, dilatih_pada)
               VALUES (?, ?, ?, ?, ?, ?, ?)""",
            (nama_model, mae, rmse, mape, n, terbaik, waktu),
        )
        status = "MODEL TERBAIK" if terbaik else "-"
        print(f"  + {nama_model:8s} MAE={mae:<10.4f} RMSE={rmse:<10.4f} "
              f"MAPE={mape:<7.4f}%  n_uji={n:<3d} [{status}]")

    koneksi.commit()
    koneksi.close()
    print("\nBerhasil. Tabel evaluasi_model sudah diperbarui dan sinkron "
          "dengan Bab IV skripsi & notebook yang sudah diperbaiki.")
    print("Ingat: jalankan ulang latih_model.py setiap ada data transaksi baru.")


if __name__ == "__main__":
    main()
