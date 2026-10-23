<?php
/**
 * php/PembacaXlsx.php
 * -----------------------------------------------------------------
 * Pembaca file .xlsx sederhana TANPA perlu Composer/PhpSpreadsheet.
 * File .xlsx sebenarnya adalah arsip ZIP berisi XML, jadi kita baca
 * langsung memakai ZipArchive (bawaan PHP) + parsing XML dasar.
 *
 * Keterbatasan (cukup untuk kebutuhan impor data transaksi sederhana):
 *  - Hanya membaca sheet PERTAMA
 *  - Tidak mendukung rumus (formula), hanya nilai
 *  - Tidak mendukung merged cell
 *  - Mendukung sel teks, angka, dan tanggal (serial number Excel)
 * -----------------------------------------------------------------
 */

class PembacaXlsx
{
    private array $stringBersama = [];

    /**
     * Membaca file .xlsx dan mengembalikan array baris (tiap baris = array kolom A,B,C,...).
     * Baris pertama biasanya adalah header.
     */
    public function baca(string $pathFile): array
    {
        $zip = new ZipArchive();
        if ($zip->open($pathFile) !== true) {
            throw new RuntimeException('File .xlsx tidak bisa dibuka (rusak atau bukan file Excel yang valid).');
        }

        // 1. Baca shared strings (teks yang dipakai berulang disimpan terpisah oleh Excel)
        $isiSharedStrings = $zip->getFromName('xl/sharedStrings.xml');
        if ($isiSharedStrings !== false) {
            $this->uraiSharedStrings($isiSharedStrings);
        }

        // 2. Cari sheet pertama (biasanya xl/worksheets/sheet1.xml)
        $namaSheet = 'xl/worksheets/sheet1.xml';
        $isiSheet = $zip->getFromName($namaSheet);
        if ($isiSheet === false) {
            $zip->close();
            throw new RuntimeException('Tidak dapat menemukan data sheet pertama di dalam file Excel.');
        }
        $zip->close();

        return $this->uraiSheet($isiSheet);
    }

    private function uraiSharedStrings(string $xml): void
    {
        $dom = new SimpleXMLElement($xml);
        $ns = $dom->getNamespaces(true);
        foreach ($dom->si as $si) {
            // Sebuah <si> bisa berisi <t> langsung atau beberapa <r><t> (rich text)
            if (isset($si->t)) {
                $this->stringBersama[] = (string)$si->t;
            } else {
                $teks = '';
                foreach ($si->r as $r) {
                    $teks .= (string)$r->t;
                }
                $this->stringBersama[] = $teks;
            }
        }
    }

    private function uraiSheet(string $xml): array
    {
        $dom = new SimpleXMLElement($xml);
        $barisSemua = [];

        foreach ($dom->sheetData->row as $row) {
            $baris = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];          // contoh: "B3"
                $kolomHuruf = preg_replace('/[0-9]/', '', $ref);
                $tipe = (string)$c['t'];          // 's' = shared string, 'str' = formula string, 'inlineStr' = teks langsung, kosong = angka

                if ($tipe === 's') {
                    $nilaiMentah = isset($c->v) ? (string)$c->v : '';
                    $nilai = $this->stringBersama[(int)$nilaiMentah] ?? '';
                } elseif ($tipe === 'inlineStr') {
                    // PENTING: sel inlineStr TIDAK punya elemen <v>, teksnya ada di
                    // <is><t>...</t></is> (atau beberapa <is><r><t>...</t></r></is>
                    // untuk rich text). Membaca dari <v> di sini selalu menghasilkan
                    // string kosong -> inilah sebabnya kolom teks (nama produk,
                    // tanggal, satuan, catatan) selalu kosong saat diimpor walau
                    // kolom angka (jumlah, harga) tetap terbaca normal.
                    if (isset($c->is->t)) {
                        $nilai = (string)$c->is->t;
                    } elseif (isset($c->is->r)) {
                        $nilai = '';
                        foreach ($c->is->r as $r) {
                            $nilai .= (string)$r->t;
                        }
                    } else {
                        $nilai = '';
                    }
                } elseif ($tipe === 'str') {
                    $nilai = isset($c->v) ? (string)$c->v : '';
                } else {
                    $nilaiMentah = isset($c->v) ? (string)$c->v : '';
                    $nilai = $nilaiMentah === '' ? null : $nilaiMentah;
                }

                $baris[$this->indeksDariHurufKolom($kolomHuruf)] = $nilai;
            }

            if (!empty($baris)) {
                ksort($baris);
                $barisSemua[] = $baris;
            }
        }

        return $barisSemua;
    }

    private function indeksDariHurufKolom(string $huruf): int
    {
        $huruf = strtoupper($huruf);
        $indeks = 0;
        for ($i = 0; $i < strlen($huruf); $i++) {
            $indeks = $indeks * 26 + (ord($huruf[$i]) - ord('A') + 1);
        }
        return $indeks - 1; // 0-based
    }

    /**
     * Mengubah nilai tanggal dari sel Excel menjadi format Y-m-d.
     * Menangani tiga kemungkinan:
     *   1. Serial number tanggal Excel (misalnya 45306)
     *   2. Teks format Indonesia "DD/MM/YYYY" (mis. "01/01/2024") — PENTING:
     *      PHP strtotime() secara default membaca format bergaris miring
     *      sebagai bulan/hari/tahun (gaya AS), jadi ini WAJIB ditangani
     *      eksplisit agar "02/01/2024" tidak salah dibaca sebagai 2 Januari.
     *   3. Teks format ISO "YYYY-MM-DD" (tidak ambigu, aman dipakai strtotime)
     */
    public static function normalisasiTanggal($nilai): ?string
    {
        if ($nilai === null || $nilai === '') {
            return null;
        }

        // Kasus 1: serial number tanggal Excel (basis 1900, dengan koreksi bug tahun kabisat 1900)
        if (is_numeric($nilai)) {
            $serial = (float)$nilai;
            $timestampUnix = ($serial - 25569) * 86400;
            return gmdate('Y-m-d', (int)$timestampUnix);
        }

        $teks = trim((string)$nilai);

        // Kasus 2: format Indonesia DD/MM/YYYY atau DD-MM-YYYY
        if (preg_match('#^(\d{1,2})[/\-](\d{1,2})[/\-](\d{4})$#', $teks, $cocok)) {
            [, $hari, $bulan, $tahun] = $cocok;
            if ((int)$bulan <= 12 && (int)$hari <= 31) {
                $tanggalObj = DateTime::createFromFormat('d-m-Y', "$hari-$bulan-$tahun");
                if ($tanggalObj) {
                    return $tanggalObj->format('Y-m-d');
                }
            }
        }

        // Kasus 3: format ISO YYYY-MM-DD (tidak ambigu)
        if (preg_match('#^(\d{4})-(\d{1,2})-(\d{1,2})#', $teks)) {
            $waktu = strtotime($teks);
            return $waktu !== false ? date('Y-m-d', $waktu) : null;
        }

        // Fallback terakhir: biarkan PHP mencoba menebak
        $waktu = strtotime($teks);
        return $waktu !== false ? date('Y-m-d', $waktu) : null;
    }
}
