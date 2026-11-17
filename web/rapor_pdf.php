<?php
// ============================================================
// rapor_pdf.php — İK raporlarının PDF çıktısı (kütüphanesiz, A4 yatay, çok sayfalı)
//   rp_pdf($rapor, $gorunum, $ayar): $gorunum = tablo | grafik | ikisi
//   ik_pdf.php'deki yazı tipi kodlaması ve genişlik ölçümü kullanılır (Türkçe karakterler dahil).
// ============================================================
require_once __DIR__ . '/ik_pdf.php';

function rp_pdf_kes($s, $boy, $azami, $kalin = false)
{
    $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
    if (ik_pdf_genislik($s, $boy, $kalin) <= $azami) return $s;
    $h = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY);
    while ($h && ik_pdf_genislik(implode('', $h) . '…', $boy, $kalin) > $azami) array_pop($h);
    return implode('', $h) . '..';
}

function rp_pdf(array $r, $gorunum = 'ikisi', array $ayar = [])
{
    $K = 842; $Y = 595; $ml = 30; $gen = $K - 2 * $ml;
    $renk = ik_pdf_renk($ayar['renk'] ?? '#6366f1'); $renk2 = ik_pdf_renk(preg_match('/^#[0-9a-f]{6}$/i', (string)($ayar['renk2'] ?? '')) ? $ayar['renk2'] : ($ayar['renk'] ?? '#6366f1'));
    $sayfalar = []; $c = ''; $y = 0; $no = 0;
    $ZEM = ['uyari' => '1 0.965 0.86', 'hata' => '1 0.9 0.9', 'tatil' => '1 0.88 0.92', 'haftasonu' => '0.95 0.91 1'];
    $VUR = ['kirmizi' => '0.86 0.15 0.15', 'turuncu' => '0.85 0.47 0.02', 'yesil' => '0.09 0.58 0.33', 'mor' => '0.5 0.2 0.85', 'mavi' => '0.15 0.38 0.92'];
    $yaz = function ($x, $yy, $s, $boy, $f = 'F1', $rg = '0.059 0.09 0.165') use (&$c) { $c .= "BT /$f $boy Tf $rg rg $x $yy Td (" . ik_pdf_kodla($s) . ") Tj ET\n"; };
    $dik = function ($x, $yy, $w, $h, $rg) use (&$c) { $c .= "$rg rg " . sprintf('%.2f %.2f %.2f %.2f', $x, $yy, $w, $h) . " re f\n"; };
    $logo = ik_pdf_logo($ayar['logo_dosya'] ?? '', $ayar['renk'] ?? '#6366f1', $ayar['renk2'] ?? '', $ml, $K);
    $yeni = function () use (&$c, &$y, &$sayfalar, &$no, $K, $Y, $ml, $renk, $renk2, $r, &$yaz, &$dik, $logo) {
        if ($c !== '') $sayfalar[] = $c;
        $c = ''; $no++;
        if ($no === 1) {
            $a1 = array_map('floatval', explode(' ', $renk)); $a2 = array_map('floatval', explode(' ', $renk2)); $n = 50;
            for ($i = 0; $i < $n; $i++) { $t = $i / ($n - 1); $c .= sprintf("%.3f %.3f %.3f rg %.2f %d %.2f 74 re f\n", $a1[0] + ($a2[0] - $a1[0]) * $t, $a1[1] + ($a2[1] - $a1[1]) * $t, $a1[2] + ($a2[2] - $a1[2]) * $t, $K * $i / $n, $Y - 74, $K / $n + 1); }
            $x0 = $ml;
            if ($logo) { $lh = 40; $lw = min(150, $logo[1] * $lh / max(1, $logo[2])); $lh = $lw * $logo[2] / max(1, $logo[1]); $c .= "q $lw 0 0 $lh $ml " . ($Y - 74 + (74 - $lh) / 2) . " cm /Im1 Do Q\n"; $x0 = $ml + $lw + 14; }
            $yaz($x0, $Y - 34, $r['baslik'], 20, 'F2', '1 1 1');
            $yaz($x0, $Y - 54, ik_pdf_kes_basit($r['alt'], 10.5, $K - $x0 - $ml - 150), 10.5, 'F1', '0.93 0.95 1');
            $yaz($K - $ml - 130, $Y - 34, date('d.m.Y H:i'), 9.5, 'F1', '0.93 0.95 1');
            $y = $Y - 74 - 22;
        } else {
            $dik(0, $Y - 24, $K, 24, '0.973 0.980 0.988');
            $yaz($ml, $Y - 16, $r['baslik'] . ' — ' . rp_pdf_kes($r['alt'], 8.5, $K - 2 * $ml - 80), 8.5, 'F2', '0.39 0.45 0.55');
            $y = $Y - 24 - 18;
        }
    };
    $yeni();
    $bosluk = function ($h) use (&$y, &$yeni) { if ($y - $h < 40) $yeni(); };

    // Özet kutuları
    if (!empty($r['ozet'])) {
        $n = count($r['ozet']); $bw = ($gen - ($n - 1) * 8) / $n; $bh = 46;
        $bosluk($bh + 8);
        foreach ($r['ozet'] as $i => $o) {
            $x = $ml + $i * ($bw + 8); $rg = $VUR[$o[2] ?? 'mavi'] ?? $VUR['mavi'];
            $dik($x, $y - $bh, $bw, $bh, '0.965 0.973 0.988'); $dik($x, $y - $bh, 3.5, $bh, $rg);
            $yaz($x + 12, $y - 16, rp_pdf_kes($o[0], 8.5, $bw - 18), 8.5, 'F1', '0.39 0.45 0.55');
            $vb = ik_pdf_genislik((string)$o[1], 13, true) > $bw - 18 ? 10 : 13; $yaz($x + 12, $y - 35, rp_pdf_kes((string)$o[1], $vb, $bw - 18, true), $vb, 'F2', '0.059 0.09 0.165');
        }
        $y -= $bh + 16;
    }
    // Grafikler
    if ($gorunum !== 'tablo') {
        foreach ((array)$r['grafikler'] as $g) {
            $mak = 0;
            if ($g['tip'] === 'halka') {
                $top = array_sum(array_column($g['dilimler'], 'deger')) ?: 1; $bosluk(70 + count($g['dilimler']) * 13);
                $yaz($ml, $y - 10, $g['baslik'], 11, 'F2'); $y -= 22;
                $x = $ml;
                foreach ($g['dilimler'] as $d) { $w = $gen * $d['deger'] / $top; $dik($x, $y - 16, max(0.5, $w), 16, ik_pdf_renk($d['renk'])); $x += $w; }
                $y -= 32;
                foreach ($g['dilimler'] as $d) { $dik($ml, $y - 8, 8, 8, ik_pdf_renk($d['renk'])); $yaz($ml + 14, $y - 7, $d['ad'] . ': ' . rp_gun_say($d['deger']) . ' ' . ($g['birim'] ?? '') . ' (%' . round($d['deger'] * 100 / $top) . ')', 9, 'F1', '0.2 0.25 0.33'); $y -= 13; }
                $y -= 14; continue;
            }
            $et = $g['etiketler']; $sr = $g['seriler']; $yigin = !empty($g['yigin']);
            foreach ($et as $i => $_) { $t = 0; foreach ($sr as $s) { $t = $yigin ? $t + $s['veri'][$i] : max($t, $s['veri'][$i]); } $mak = max($mak, $t); }
            $mak = $mak ?: 1; $satH = 15; $lw = 130; $bw = $gen - $lw - 70;
            $bosluk(26 + count($et) * $satH + 20);
            $yaz($ml, $y - 10, $g['baslik'], 11, 'F2'); $y -= 24;
            foreach ($et as $i => $ad) {
                $yaz($ml, $y - 10, rp_pdf_kes($ad, 9, $lw - 8), 9, 'F1', '0.2 0.25 0.33'); $x = $ml + $lw; $top = 0;
                foreach ($sr as $s) { $v = $s['veri'][$i]; $w = $bw * $v / $mak; if ($w > 0) { $dik($x, $y - 12, $w, 11, ik_pdf_renk($s['renk'])); $x += $yigin ? $w : 0; } $top = $yigin ? $top + $v : max($top, $v); if (!$yigin) { /* tek seri */ } }
                $xe = $ml + $lw + ($yigin ? $bw * $top / $mak : $bw * $top / $mak);
                $yaz($xe + 5, $y - 10, rp_gun_say(round($top, 2)) . ' ' . ($g['birim'] ?? ''), 8.5, 'F2', '0.28 0.33 0.41');
                $y -= $satH;
            }
            if (count($sr) > 1) { $x = $ml + $lw; foreach ($sr as $s) { $dik($x, $y - 9, 8, 8, ik_pdf_renk($s['renk'])); $yaz($x + 12, $y - 8, $s['ad'], 8.5, 'F1', '0.28 0.33 0.41'); $x += 14 + ik_pdf_genislik($s['ad'], 8.5) + 14; } $y -= 14; }
            $y -= 14;
        }
    }
    // Tablo çizici
    $tablo = function ($baslik, array $sut, array $sat, $toplam = null) use (&$y, &$c, &$yeni, $ml, $gen, $ZEM, $VUR, &$yaz, &$dik, $bosluk) {
        if (!$sat) return;
        if ($baslik) { $bosluk(60); $yaz($ml, $y - 10, $baslik, 12, 'F2'); $y -= 24; }
        // sütun genişlikleri: içerik genişliği; sığmazsa önce sola dayalı (metin) sütunlar daralır, gerekirse hepsi ölçeklenir
        $w = []; $esnek = [];
        foreach ($sut as $i => $s) {
            $kelime = 0; foreach (preg_split('/\s+/u', $s['ad']) as $kw) $kelime = max($kelime, ik_pdf_genislik($kw, 8.5, true));
            $m = $kelime + 12; $icerik = 0;
            foreach (array_slice($sat, 0, 150) as $row) $icerik = max($icerik, ik_pdf_genislik((string)($row[$s['k']] ?? ''), 8.5) + 12);
            $m = max($m, $icerik); $w[$i] = min($m, in_array($s['k'], ['aciklama', 'tur', 'turler'], true) ? 200 : 170); $esnek[$i] = $s['h'] === 'l' ? max(60, $kelime + 12) : $w[$i];
        }
        $top = array_sum($w);
        if ($top > $gen) {
            $kayma = $top - $gen; $esn = 0; foreach ($w as $i => $x) if ($sut[$i]['h'] === 'l') $esn += $x - $esnek[$i];
            if ($esn > 0) { $f = min(1, $kayma / $esn); foreach ($w as $i => $x) if ($sut[$i]['h'] === 'l') $w[$i] = $x - ($x - $esnek[$i]) * $f; }
            $top = array_sum($w);
        }
        $olcek = $top > 0 ? $gen / $top : 1; foreach ($w as $i => $_) $w[$i] = $w[$i] * $olcek;
        $iki = false; foreach ($sut as $i => $s) if (ik_pdf_genislik($s['ad'], 8.5, true) > $w[$i] - 8) $iki = true;
        $baslikH = $iki ? 27 : 18;
        $baslikCiz = function () use (&$y, &$c, $sut, $w, $ml, $gen, &$yaz, &$dik, $baslikH, $iki) {
            $dik($ml, $y - $baslikH, $gen, $baslikH, '0.145 0.176 0.30'); $x = $ml;
            foreach ($sut as $i => $s) {
                $satirlar = $iki ? array_slice(ik_pdf_sar($s['ad'], 8.5, $w[$i] - 8, true), 0, 2) : [$s['ad']];
                foreach ($satirlar as $li => $t) { $t = rp_pdf_kes($t, 8.5, $w[$i] - 6, true); $tx = $s['h'] === 'r' ? $x + $w[$i] - 5 - ik_pdf_genislik($t, 8.5, true) : $x + 5; $yaz($tx, $y - ($iki ? 11 + $li * 10 : 12.5), $t, 8.5, 'F2', '1 1 1'); }
                $x += $w[$i];
            }
            $y -= $baslikH;
        };
        $bosluk(60); $baslikCiz();
        foreach ($sat as $n => $row) {
            if ($y - 16 < 36) { $yeni(); $baslikCiz(); }
            $zem = $ZEM[$row['_c'] ?? ''] ?? ($n % 2 ? '0.972 0.98 0.99' : '1 1 1'); $dik($ml, $y - 16, $gen, 16, $zem); $x = $ml;
            foreach ($sut as $i => $s) {
                $t = rp_pdf_kes((string)($row[$s['k']] ?? ''), 8.5, $w[$i] - 8); $vur = $VUR[$row['_h'][$s['k']] ?? ''] ?? null;
                $tx = $s['h'] === 'r' ? $x + $w[$i] - 5 - ik_pdf_genislik($t, 8.5, (bool)$vur) : $x + 5;
                $yaz($tx, $y - 11.5, $t, 8.5, $vur ? 'F2' : ($s['k'] === 'ad' ? 'F2' : 'F1'), $vur ?: '0.2 0.25 0.33'); $x += $w[$i];
            }
            $y -= 16;
        }
        $c .= "0.886 0.910 0.941 RG 0.6 w $ml $y m " . ($ml + $gen) . " $y l S\n";
        $y -= 22;
    };
    if ($gorunum !== 'grafik') {
        $tablo('', $r['sutunlar'], $r['satirlar']);
        foreach ((array)$r['detaylar'] as $d) $tablo($d['baslik'], $d['sutunlar'], $d['satirlar']);
    }
    if (!$r['satirlar']) { $bosluk(40); $yaz($ml, $y - 10, 'Seçilen kriterlere uyan kayıt bulunamadı.', 11, 'F1', '0.39 0.45 0.55'); $y -= 24; }
    foreach ((array)$r['notlar'] as $nt) { foreach (ik_pdf_sar($nt, 8.5, $gen) as $l) { $bosluk(14); $yaz($ml, $y - 8, $l, 8.5, 'F1', '0.45 0.5 0.6'); $y -= 12; } }
    $sayfalar[] = $c;

    // Sayfa altlığı + PDF nesneleri
    $say = count($sayfalar); $ob = []; $kids = []; $ek = $logo ? ' /XObject << /Im1 6 0 R >>' : '';
    $ob[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $ob[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding 5 0 R >>'; $ob[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding 5 0 R >>';
    $ob[5] = '<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [128 /Gbreve /gbreve /Scedilla /scedilla /Idotaccent /dotlessi] >>';
    if ($logo) $ob[6] = '<< /Type /XObject /Subtype /Image /Width ' . $logo[1] . ' /Height ' . $logo[2] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo[0]) . " >>\nstream\n" . $logo[0] . "\nendstream";
    $n = 7;
    foreach ($sayfalar as $i => $icerik) {
        $icerik .= sprintf("BT /F1 8 Tf 0.58 0.64 0.72 rg %d 16 Td (%s) Tj ET\n", $ml, ik_pdf_kodla(($ayar['alt'] ?? 'Turkuaz Bileşim · İnsan Kaynakları') . '  ·  ' . date('d.m.Y H:i')));
        $sy = ik_pdf_kodla('Sayfa ' . ($i + 1) . ' / ' . $say);
        $icerik .= sprintf("BT /F1 8 Tf 0.58 0.64 0.72 rg %d 16 Td (%s) Tj ET\n", $K - $ml - 46, $sy);
        $pg = $n++; $ic = $n++; $kids[] = "$pg 0 R";
        $ob[$pg] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $K $Y] /Resources << /Font << /F1 3 0 R /F2 4 0 R >>$ek >> /Contents $ic 0 R >>";
        $ob[$ic] = '<< /Length ' . strlen($icerik) . " >>\nstream\n$icerik\nendstream";
    }
    $ob[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $say . ' >>';
    ksort($ob);
    $pdf = "%PDF-1.4\n"; $ofs = [];
    foreach ($ob as $no2 => $g) { $ofs[$no2] = strlen($pdf); $pdf .= "$no2 0 obj\n$g\nendobj\n"; }
    $maks = max(array_keys($ob)); $xr = strlen($pdf);
    $pdf .= "xref\n0 " . ($maks + 1) . "\n0000000000 65535 f \n";
    for ($i = 1; $i <= $maks; $i++) $pdf .= isset($ofs[$i]) ? sprintf("%010d 00000 n \n", $ofs[$i]) : "0000000000 65535 f \n";
    return $pdf . "trailer\n<< /Size " . ($maks + 1) . " /Root 1 0 R >>\nstartxref\n$xr\n%%EOF";
}
function ik_pdf_kes_basit($s, $boy, $azami) { return rp_pdf_kes($s, $boy, $azami); }
