<?php
ob_start();
require_once __DIR__ . '/../config/pengaturan.php';
require_once __DIR__ . '/../config/koneksi_db.php';
wajib_peran(['pemilik']);

$format = strtolower($_GET['format'] ?? 'pdf');
$produk_filter = filter_input(INPUT_GET, 'produk', FILTER_VALIDATE_INT) ?: null;
$dari_filter = trim((string)($_GET['dari'] ?? ''));
$sampai_filter = trim((string)($_GET['sampai'] ?? ''));

$klausa = [];
$params = [];
if ($produk_filter) { $klausa[] = 't.id_produk = :id_produk'; $params['id_produk'] = $produk_filter; }
if ($dari_filter) { $klausa[] = 't.tanggal_transaksi >= :dari'; $params['dari'] = $dari_filter; }
if ($sampai_filter) { $klausa[] = 't.tanggal_transaksi <= :sampai'; $params['sampai'] = $sampai_filter; }
$where_sql = $klausa ? 'WHERE ' . implode(' AND ', $klausa) : '';

$ringkasan_stmt = $koneksi->prepare("SELECT COUNT(*) AS jumlah_transaksi, COALESCE(SUM(t.total_penjualan),0) AS omzet, COALESCE(SUM(t.jumlah),0) AS total_qty FROM transaksi t JOIN produk p ON p.id_produk=t.id_produk $where_sql");
$ringkasan_stmt->execute($params);
$ringkasan = $ringkasan_stmt->fetch() ?: ['jumlah_transaksi'=>0, 'omzet'=>0, 'total_qty'=>0];

$produk_klausa = ['p.aktif = 1'];
if ($produk_filter) { $produk_klausa[] = 'p.id_produk = :id_produk'; }
if ($dari_filter) { $produk_klausa[] = 't.tanggal_transaksi >= :dari'; }
if ($sampai_filter) { $produk_klausa[] = 't.tanggal_transaksi <= :sampai'; }
$produk_stmt = $koneksi->prepare("SELECT p.kode_produk,p.nama_produk,p.satuan,COUNT(t.id_transaksi) AS jumlah_transaksi,COALESCE(SUM(t.jumlah),0) AS total_jumlah,COALESCE(SUM(t.total_penjualan),0) AS omzet FROM produk p LEFT JOIN transaksi t ON t.id_produk=p.id_produk WHERE " . implode(' AND ', $produk_klausa) . " GROUP BY p.id_produk ORDER BY omzet DESC,total_jumlah DESC,p.nama_produk ASC");
$produk_stmt->execute($params);
$daftar = $produk_stmt->fetchAll();

function laporan_h(string $text): string { return htmlspecialchars($text, ENT_QUOTES, 'UTF-8'); }
function laporan_potong(string $text, int $limit): string {
    return function_exists('mb_strimwidth') ? mb_strimwidth($text, 0, $limit, '...', 'UTF-8') : substr($text, 0, $limit);
}
function laporan_tanggal(string $date): string {
    if (!$date) return '-';
    $stamp = strtotime($date);
    return $stamp ? date('d-m-Y', $stamp) : $date;
}
function tanggal_indonesia(): string {
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    return date('d') . ' ' . $bulan[(int)date('n') - 1] . ' ' . date('Y');
}
function data_uri(string $path): string {
    if (!is_readable($path)) return '';
    $data = file_get_contents($path);
    return $data === false ? '' : 'data:' . (mime_content_type($path) ?: 'image/png') . ';base64,' . base64_encode($data);
}
function pdf_escape(string $text): string {
    $text = function_exists('iconv') ? (@iconv('UTF-8','Windows-1252//TRANSLIT',$text) ?: $text) : $text;
    return str_replace(['\\','(',')',"\r","\n"], ['\\\\','\\(','\\)',' ',' '], $text);
}
function pdf_text(string &$s, float $x, float $y, string $text, int $size = 9, string $font = 'F1'): void {
    $s .= "BT /$font $size Tf 1 0 0 1 " . round($x,2) . ' ' . round($y,2) . " Tm (" . pdf_escape($text) . ") Tj ET\n";
}
function pdf_center_text(string &$s, float $center_x, float $y, string $text, int $size = 9, string $font = 'F1'): void {
    $converted = function_exists('iconv') ? (@iconv('UTF-8', 'Windows-1252//TRANSLIT', $text) ?: $text) : $text;
    $width = strlen($converted) * $size * 0.48;
    pdf_text($s, $center_x - ($width / 2), $y, $text, $size, $font);
}
function pdf_line(string &$s, float $x1, float $y1, float $x2, float $y2, float $width = 0.6): void {
    $s .= "$width w $x1 $y1 m $x2 $y2 l S\n";
}
function pdf_rect(string &$s, float $x, float $y, float $w, float $h, bool $fill = false): void {
    $s .= "$x $y $w $h re " . ($fill ? 'f' : 'S') . "\n";
}
function jpg_info(string $path): ?array {
    $info = @getimagesize($path);
    return $info ? ['w'=>$info[0], 'h'=>$info[1], 'data'=>file_get_contents($path)] : null;
}
function buat_pdf(array $rows, array $summary, string $logo_path, string $periode, string $nomor_surat, string $tanggal_dokumen, array $identitas): string {
    $objects = [];
    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    $objects[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>';
    $image_id = null;
    $img = jpg_info($logo_path);
    if ($img && $img['data'] !== false) {
        $image_id = 5;
        $objects[$image_id] = '<< /Type /XObject /Subtype /Image /Width '.$img['w'].' /Height '.$img['h'].' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length '.strlen($img['data'])." >>\nstream\n".$img['data']."\nendstream";
    }
    $rows_per_page = 15;
    $chunks = array_chunk($rows, $rows_per_page);
    if (!$chunks) $chunks = [[]];
    $page_ids = [];
    $next = $image_id ? 5 : 4;
    foreach ($chunks as $page_no => $page_rows) {
        $page_id = ++$next; $content_id = ++$next; $page_ids[] = $page_id;
        $s = "q 0 0 0 RG 0 0 0 rg\n";
        if ($page_no === 0) {
            if ($image_id) { $s .= "q 70 0 0 70 40 695 cm /Im1 Do Q\n"; }
            pdf_center_text($s, 343, 748, $identitas['kabupaten'], 14, 'F2');
        pdf_center_text($s, 343, 729, $identitas['kecamatan'], 13, 'F2');
        pdf_center_text($s, 343, 711, $identitas['desa'], 16, 'F2');
        pdf_center_text($s, 343, 694, $identitas['alamat'].' | '.$identitas['hp'], 8, 'F1');
        pdf_line($s, 36, 682, 576, 682, 2.2); pdf_line($s, 36, 678, 576, 678, .5);
            pdf_center_text($s, 297.5, 651, $identitas['judul'], 13, 'F2');
            pdf_center_text($s, 297.5, 635, $identitas['subjudul'], 9, 'F1');
            pdf_text($s, 36, 608, 'Nomor Surat: '.$nomor_surat, 9, 'F1');
            pdf_text($s, 36, 594, 'Periode: '.$periode, 9, 'F1');
            pdf_text($s, 36, 580, 'Tanggal: '.$tanggal_dokumen, 9, 'F1');
            pdf_text($s, 36, 550, 'RINGKASAN', 9, 'F2');
            $sum_y = 528; $sum_w = 127; $sum_labels = ['Total Omzet','Jumlah Transaksi','Total Terjual','Produk Terlaris'];
        $sum_values = ['Rp '.number_format((float)$summary['omzet'],0,',','.'), number_format((int)$summary['jumlah_transaksi'],0,',','.'), angka((float)$summary['total_qty']).' kg', !empty($rows[0]) ? laporan_potong((string)$rows[0]['nama_produk'],18) : '-'];
            for ($i=0; $i<4; $i++) { $x=36+$i*($sum_w+8); $s .= "0.94 0.96 0.99 rg\n"; pdf_rect($s,$x,$sum_y,$sum_w,34,true); $s .= "0 0 0 rg\n"; pdf_text($s,$x+6,$sum_y+22,$sum_labels[$i],7,'F2'); pdf_text($s,$x+6,$sum_y+9,$sum_values[$i],8,'F1'); }
        }
        $top = $page_no === 0 ? 474 : 770; $rh=22; $widths=[28,215,78,92,127]; $heads=['No.','Nama Produk','Transaksi','Terjual','Omzet']; $x=36;
        $s .= "0.12 0.20 0.35 rg\n"; pdf_rect($s,$x,$top,$widths[0],$rh,true); pdf_rect($s,$x+$widths[0],$top,$widths[1],$rh,true); pdf_rect($s,$x+$widths[0]+$widths[1],$top,$widths[2],$rh,true); pdf_rect($s,$x+$widths[0]+$widths[1]+$widths[2],$top,$widths[3],$rh,true); pdf_rect($s,$x+$widths[0]+$widths[1]+$widths[2]+$widths[3],$top,$widths[4],$rh,true); $s .= "1 1 1 rg\n";
        $cx=$x; foreach($heads as $i=>$head){ pdf_text($s,$cx+5,$top+7,$head,7,'F2'); $cx += $widths[$i]; }
        $s .= "0 0 0 rg\n"; $y=$top-$rh;
        foreach($page_rows as $index=>$item){ $vals=[(string)($page_no * $rows_per_page + $index + 1), laporan_potong((string)$item['nama_produk'], 30), number_format((int)$item['jumlah_transaksi'],0,',','.'), angka((float)$item['total_jumlah']).' '.($item['satuan'] ?: 'kg'), 'Rp '.number_format((float)$item['omzet'],0,',','.')]; $cx=36; foreach($vals as $i=>$val){ pdf_rect($s,$cx,$y,$widths[$i],$rh,false); pdf_text($s,$cx+5,$y+7,$val,8,'F1'); $cx += $widths[$i]; } $y-=$rh; }
        if ($page_no === count($chunks) - 1) { pdf_text($s, 380, 118, 'Lintong Nihuta, '.$tanggal_dokumen, 9, 'F1'); pdf_text($s, 428, 103, 'Disahkan oleh,', 9, 'F1'); pdf_text($s, 419, 53, $identitas['pengesah'], 10, 'F2'); }
        pdf_text($s, 36, 28, $identitas['hp'], 8, 'F1'); pdf_text($s, 490, 28, 'Halaman ' . ($page_no + 1) . '/' . count($chunks), 8, 'F1');
        $resources='<< /Font << /F1 3 0 R /F2 4 0 R >>'; if($image_id) $resources.=' /XObject << /Im1 '.$image_id.' 0 R >>'; $resources.=' >>';
        $objects[$page_id]='<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources '.$resources.' /Contents '.$content_id.' 0 R >>'; $objects[$content_id]='<< /Length '.strlen($s).' >>' . "\nstream\n" . $s . "endstream";
    }
    $objects[2]='<< /Type /Pages /Count '.count($page_ids).' /Kids ['.implode(' ',array_map(fn($id)=>$id.' 0 R',$page_ids)).'] >>';
    $pdf="%PDF-1.4\n"; $offsets=[]; $offset=strlen($pdf); $ids=array_keys($objects); sort($ids,SORT_NUMERIC); foreach($ids as $id){$offsets[$id]=$offset; $pdf.=$id." 0 obj\n".$objects[$id]."\nendobj\n"; $offset=strlen($pdf);} $xref=strlen($pdf); $pdf .= "xref\n0 " . (max($ids)+1) . "\n0000000000 65535 f\n"; for($i=1;$i<=max($ids);$i++) $pdf .= isset($offsets[$i]) ? sprintf("%010d 00000 n\n", $offsets[$i]) : "0000000000 00000 f\n"; return $pdf . "trailer\n<< /Size " . (max($ids)+1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF";
}

$periode = ($dari_filter || $sampai_filter) ? laporan_tanggal($dari_filter ?: '') . ' s/d ' . laporan_tanggal($sampai_filter ?: '') : 'Seluruh data';
$bulan_romawi = ['I','II','III','IV','V','VI','VII','VIII','IX','X','XI','XII'];
$nomor_surat = '001/DSN-V/LN/' . $bulan_romawi[(int)date('n') - 1] . '/' . date('Y');
$tanggal_dokumen = tanggal_indonesia();
$identitas = [
    'kabupaten' => 'PEMERINTAH KABUPATEN HUMBANG HASUNDUTAN',
    'kecamatan' => 'KECAMATAN LINTONG NIHUTA',
    'desa' => 'DESA NAGASARIBU V',
    'alamat' => 'Alamat: Onan Sibaganding, Desa Nagasaribu V, Kec. Lintong Nihuta',
    'hp' => 'HP: 0821 6777 3420',
    'judul' => 'LAPORAN PENJUALAN',
    'subjudul' => 'Rekapitulasi Data Penjualan',
    'pengesah' => 'Juntar Lumbantoruan',
];
$logo_png = __DIR__ . '/../assets/icons/icon-192.png';
$logo_jpg = __DIR__ . '/../assets/icons/icon-192-pdf.jpg';
$logo_src = data_uri($logo_png);
$terlaris = !empty($daftar[0]) ? laporan_h((string)$daftar[0]['nama_produk']) : '-';
$html = '<!doctype html><html lang="id"><head><meta charset="utf-8"><title>Laporan Penjualan - Desa Nagasaribu V</title><style>@page{size:A4;margin:15mm 13mm}body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;font-size:10pt}.kop{display:flex;align-items:center;border-bottom:3px solid #111;padding-bottom:4px;position:relative}.kop:after{content:"";position:absolute;bottom:-7px;left:0;right:0;border-bottom:1px solid #111}.logo{width:76px;height:76px;object-fit:contain;margin-right:18px}.kop-text{flex:1;text-align:center;line-height:1.2}.kop-text div:nth-child(1){font-size:15pt;font-weight:bold}.kop-text div:nth-child(2){font-size:14pt;font-weight:bold}.kop-text div:nth-child(3){font-size:17pt;font-weight:bold}.alamat{font-size:8pt;margin-top:4px}.judul{text-align:center;margin:27px 0 15px}.judul h1{font-size:14pt;margin:0}.judul p{margin:4px 0;font-size:9pt}.identitas{border-collapse:collapse;width:100%;margin-bottom:14px}.identitas td{padding:3px 0}.ringkasan{width:100%;border-collapse:collapse;table-layout:fixed;margin:8px 0 16px}.ringkasan td{border:1px solid #b9c1cc;background:#f0f3f8;padding:7px;vertical-align:top}.label{font-weight:bold;font-size:8pt;text-transform:uppercase;color:#27364d}.nilai{font-weight:bold;font-size:10pt;margin-top:4px}.tabel{border-collapse:collapse;width:100%;page-break-inside:auto}.tabel th,.tabel td{border:1px solid #777;padding:6px 7px;font-size:8.5pt}.tabel th{background:#1f2937;color:white;text-align:center}.tabel td:nth-child(1),.tabel td:nth-child(3){text-align:center}.tabel td:nth-child(4),.tabel td:nth-child(5){text-align:right}.tabel tr{page-break-inside:avoid}.tabel thead{display:table-header-group}.ttd{margin-top:32px;margin-left:auto;width:220px;text-align:center;line-height:1.35}.ttd .nama{margin-top:52px;font-weight:bold;text-decoration:underline}.footer{margin-top:16px;font-size:8pt}.no-data{text-align:center;padding:14px}.word-meta{margin-top:10px}</style></head><body>';
$html .= '<div class="kop">'.($logo_src ? '<img class="logo" src="'.$logo_src.'" alt="Logo Sistem">' : '').'<div class="kop-text"><div>'.laporan_h($identitas['kabupaten']).'</div><div>'.laporan_h($identitas['kecamatan']).'</div><div>'.laporan_h($identitas['desa']).'</div><div class="alamat">'.laporan_h($identitas['alamat']).' &nbsp; | &nbsp; '.laporan_h($identitas['hp']).'</div></div></div>';
$html .= '<div class="judul"><h1>'.laporan_h($identitas['judul']).'</h1><p>'.laporan_h($identitas['subjudul']).'</p></div><table class="identitas"><tr><td><b>Nomor Surat:</b> '.laporan_h($nomor_surat).'</td><td style="text-align:right"><b>Tanggal:</b> '.$tanggal_dokumen.'</td></tr><tr><td><b>Periode:</b> '.laporan_h($periode).'</td><td style="text-align:right"><b>Disahkan oleh:</b> '.laporan_h($identitas['pengesah']).'</td></tr></table>';
$html .= '<table class="ringkasan"><tr><td><div class="label">Total Omzet</div><div class="nilai">Rp '.number_format((float)$ringkasan['omzet'],0,',','.').'</div></td><td><div class="label">Jumlah Transaksi</div><div class="nilai">'.number_format((int)$ringkasan['jumlah_transaksi'],0,',','.').'</div></td><td><div class="label">Total Terjual</div><div class="nilai">'.angka((float)$ringkasan['total_qty']).' kg</div></td><td><div class="label">Produk Terlaris</div><div class="nilai">'.$terlaris.'</div></td></tr></table>';
$html .= '<table class="tabel"><thead><tr><th style="width:6%">No.</th><th>Nama Produk</th><th style="width:17%">Transaksi</th><th style="width:18%">Total Terjual</th><th style="width:22%">Omzet</th></tr></thead><tbody>';
if (!$daftar) $html .= '<tr><td colspan="5" class="no-data">Belum ada data untuk periode ini.</td></tr>';
foreach ($daftar as $i=>$item) $html .= '<tr><td>'.($i+1).'</td><td>'.laporan_h((string)$item['nama_produk']).'<br><small>Kode: '.laporan_h((string)$item['kode_produk']).'</small></td><td>'.number_format((int)$item['jumlah_transaksi'],0,',','.').'</td><td>'.angka((float)$item['total_jumlah']).' '.laporan_h((string)($item['satuan'] ?: 'kg')).'</td><td>Rp '.number_format((float)$item['omzet'],0,',','.').'</td></tr>';
$html .= '</tbody></table><div class="ttd">Lintong Nihuta, '.$tanggal_dokumen.'<br>Disahkan oleh,<div class="nama">'.laporan_h($identitas['pengesah']).'</div></div><div class="footer">Dokumen ini dicetak dari Sistem Toko Sabrina.</div></body></html>';

// Template Word memakai tabel dan style inline agar konsisten pada Microsoft Word.
$html_word = '<!doctype html><html xmlns:o="urn:schemas-microsoft-com:office:office" lang="id"><head><meta charset="utf-8"><title>Laporan Penjualan - Desa Nagasaribu V</title><style>@page{size:A4;margin:15mm 13mm}body{font-family:Arial,Helvetica,sans-serif;color:#111;margin:0;font-size:10pt}table{border-collapse:collapse}.kop{width:100%;border:0}.kop td{border:0}.kop .kop-text{text-align:center;line-height:1.2}.data th{background:#1f2937;color:#fff;border:1px solid #555;padding:6px;font-size:8.5pt}.data td{border:1px solid #777;padding:6px 7px;font-size:8.5pt}.data thead{display:table-header-group}.data tr{page-break-inside:avoid}.summary td{border:1px solid #aeb8c6;background:#f0f3f8;padding:7px}.small{font-size:8pt}.signature{margin-top:32px}</style></head><body>';
$html_word .= '<table class="kop" width="100%" cellpadding="0" cellspacing="0" style="border-bottom:3px solid #111;margin-bottom:4px"><tr><td width="92" align="center" valign="middle"><img src="'.$logo_src.'" width="76" height="76" alt="Logo Sistem"></td><td align="center" valign="middle"><div style="font-size:15pt;font-weight:bold">'.laporan_h($identitas['kabupaten']).'</div><div style="font-size:14pt;font-weight:bold">'.laporan_h($identitas['kecamatan']).'</div><div style="font-size:17pt;font-weight:bold">'.laporan_h($identitas['desa']).'</div><div class="small">'.laporan_h($identitas['alamat']).' | '.laporan_h($identitas['hp']).'</div></td></tr></table>';
$html_word .= '<table width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:4px"><tr><td style="border-top:3px solid #111;font-size:1px;line-height:1px">&nbsp;</td></tr><tr><td style="border-top:1px solid #111;font-size:1px;line-height:1px">&nbsp;</td></tr></table>';
$html_word .= '<div style="text-align:center;margin:26px 0 14px"><div style="font-size:14pt;font-weight:bold">'.laporan_h($identitas['judul']).'</div><div style="font-size:9pt;margin-top:4px">'.laporan_h($identitas['subjudul']).'</div></div>';
$html_word .= '<table width="100%" cellpadding="3" cellspacing="0" style="margin-bottom:12px"><tr><td><b>Nomor Surat:</b> '.laporan_h($nomor_surat).'</td><td align="right"><b>Tanggal:</b> '.$tanggal_dokumen.'</td></tr><tr><td><b>Periode:</b> '.laporan_h($periode).'</td><td align="right"><b>Disahkan oleh:</b> '.laporan_h($identitas['pengesah']).'</td></tr></table>';
$html_word .= '<table class="summary" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:16px"><tr><td><b class="small">TOTAL OMZET</b><br><b>Rp '.number_format((float)$ringkasan['omzet'],0,',','.').'</b></td><td><b class="small">JUMLAH TRANSAKSI</b><br><b>'.number_format((int)$ringkasan['jumlah_transaksi'],0,',','.').'</b></td><td><b class="small">TOTAL TERJUAL</b><br><b>'.angka((float)$ringkasan['total_qty']).' kg</b></td><td><b class="small">PRODUK TERLARIS</b><br><b>'.$terlaris.'</b></td></tr></table>';
$html_word .= '<table class="data" border="1" width="100%" cellpadding="0" cellspacing="0"><thead><tr><th width="6%">No.</th><th>Nama Produk</th><th width="17%">Transaksi</th><th width="18%">Total Terjual</th><th width="22%">Omzet</th></tr></thead><tbody>';
if (!$daftar) $html_word .= '<tr><td colspan="5" align="center">Belum ada data untuk periode ini.</td></tr>';
foreach ($daftar as $i=>$item) $html_word .= '<tr><td align="center">'.($i+1).'</td><td>'.laporan_h((string)$item['nama_produk']).'<br><span class="small">Kode: '.laporan_h((string)$item['kode_produk']).'</span></td><td align="center">'.number_format((int)$item['jumlah_transaksi'],0,',','.').'</td><td align="right">'.angka((float)$item['total_jumlah']).' '.laporan_h((string)($item['satuan'] ?: 'kg')).'</td><td align="right">Rp '.number_format((float)$item['omzet'],0,',','.').'</td></tr>';
$html_word .= '</tbody></table><table class="signature" width="32%" align="right" cellpadding="2" cellspacing="0"><tr><td align="center">Lintong Nihuta, '.$tanggal_dokumen.'<br>Disahkan oleh,<br><br><br><b><u>'.laporan_h($identitas['pengesah']).'</u></b></td></tr></table><p class="small">Dokumen ini dicetak dari Sistem Toko Sabrina.</p></body></html>';

if ($format === 'doc' || $format === 'word' || $format === 'docx') { ob_end_clean(); header('Content-Type: application/msword; charset=UTF-8'); header('Content-Disposition: attachment; filename="laporan_penjualan_nagasaribu_v.doc"'); echo $html_word; exit; }
ob_end_clean(); header('Content-Type: application/pdf'); header('Content-Disposition: attachment; filename="laporan_penjualan_nagasaribu_v.pdf"'); echo buat_pdf($daftar, $ringkasan, is_readable($logo_jpg) ? $logo_jpg : $logo_png, $periode, $nomor_surat, $tanggal_dokumen, $identitas); exit;
