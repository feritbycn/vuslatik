<?php
// ============================================================
// ik_pdf.php — Bağımsız (kütüphanesiz) küçük PDF üretici: İK e-postalarının PDF kopyası / izin belgesi.
//   Standart Helvetica yazı tipi kullanılır (PDF okuyucularda yerleşiktir); Türkçe karakterler (ğ Ğ ş Ş ı İ) özel kodlamayla basılır.
//   Logo: yalnızca sunucuda duran dosya (GD varsa PNG/JPG/WEBP → JPEG'e çevrilip gömülür).
// ============================================================

function ik_pdf_kodla($s)
{
    static $ozel = ['Ğ' => "\200", 'ğ' => "\201", 'Ş' => "\202", 'ş' => "\203", 'İ' => "\204", 'ı' => "\205"];
    $o = '';
    foreach (preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY) as $c) {
        if (isset($ozel[$c])) { $o .= $ozel[$c]; continue; }
        if (strlen($c) === 1) { $o .= ($c >= ' ' && $c !== "\x7f") ? $c : ' '; continue; }
        $x = @iconv('UTF-8', 'CP1252//IGNORE', $c); if ($x !== false && $x !== '') $o .= $x;           // emoji vb. atlanır
    }
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $o);
}
function ik_pdf_genislik($s, $boy, $kalin = false)
{
    $w = 0;
    foreach (preg_split('//u', (string)$s, -1, PREG_SPLIT_NO_EMPTY) as $c) {
        if (strpos("iljI.,:;'!|ftr ", $c) !== false) $w += .28; elseif ($c === 'm' || $c === 'M' || $c === 'W') $w += .85; elseif ($c === 'w') $w += .72;
        elseif (preg_match('/\p{Lu}/u', $c)) $w += .68; elseif (preg_match('/\d/', $c)) $w += .556; else $w += .54;
    }
    return $w * $boy * ($kalin ? 1.06 : 1);
}
function ik_pdf_sar($s, $boy, $azami, $kalin = false)
{
    $satirlar = [];
    foreach (preg_split('/\R/u', (string)$s) as $par) {
        $cur = '';
        foreach (preg_split('/\s+/u', trim($par), -1, PREG_SPLIT_NO_EMPTY) as $k) {
            $dene = $cur === '' ? $k : $cur . ' ' . $k;
            if ($cur !== '' && ik_pdf_genislik($dene, $boy, $kalin) > $azami) { $satirlar[] = $cur; $cur = $k; } else $cur = $dene;
        }
        $satirlar[] = $cur;
    }
    return $satirlar;
}
function ik_pdf_renk($hex)
{
    if (!preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', (string)$hex, $m)) $m = [0, '63', '66', 'f1'];
    return sprintf('%.3f %.3f %.3f', hexdec($m[1]) / 255, hexdec($m[2]) / 255, hexdec($m[3]) / 255);
}
/** Logo dosyasını JPEG baytlarına çevirir: [jpeg, genişlik, yükseklik] ya da null */
function ik_pdf_logo($yol, $c1 = '#ffffff', $c2 = '', $x0 = 44, $sayfa = 595)
{
    if (!$yol || !is_file($yol) || !function_exists('imagecreatefromstring')) return null;
    $im = @imagecreatefromstring((string)file_get_contents($yol)); if (!$im) return null;
    $w = imagesx($im); $h = imagesy($im); $zemin = imagecreatetruecolor($w, $h);
    $rgb = function ($hx) { return preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', (string)$hx, $m) ? [hexdec($m[1]), hexdec($m[2]), hexdec($m[3])] : [255, 255, 255]; };
    $a = $rgb($c1); $b = $c2 !== '' ? $rgb($c2) : $a; $lw = min(220, $w * 50 / max(1, $h));
    // Logonun arkası, sayfadaki üst bant gradyanının tam o bölgesiyle aynı renklerle doldurulur (şeffaf logo bantla bütünleşir)
    for ($x = 0; $x < $w; $x++) {
        $t = min(1, max(0, ($x0 + $x * $lw / $w) / $sayfa));
        imageline($zemin, $x, 0, $x, $h, imagecolorallocate($zemin, (int)round($a[0] + ($b[0] - $a[0]) * $t), (int)round($a[1] + ($b[1] - $a[1]) * $t), (int)round($a[2] + ($b[2] - $a[2]) * $t)));
    }
    imagecopy($zemin, $im, 0, 0, 0, 0, $w, $h); ob_start(); imagejpeg($zemin, null, 90); $j = ob_get_clean();
    return [$j, $w, $h];
}
/**
 * $o: firma, renk, logo_dosya, baslik, selam, paragraflar[], satirlar[[etiket,değer]], link, imza, alt
 * Dönüş: PDF baytları
 */
function ik_pdf_belge(array $o)
{
    $K = 595; $Y = 842; $ml = 44; $gen = $K - 2 * $ml; $renk = ik_pdf_renk($o['renk'] ?? '#6366f1');
    $c = ''; $y = $Y;
    $yaz = function ($x, $yy, $s, $boy, $f = 'F1', $r = '0.059 0.09 0.165') use (&$c) { $c .= "BT /$f $boy Tf $r rg $x $yy Td (" . ik_pdf_kodla($s) . ") Tj ET\n"; };
    // üst şerit
    $r2 = ik_pdf_renk(preg_match('/^#[0-9a-f]{6}$/i', (string)($o['renk2'] ?? '')) ? $o['renk2'] : ($o['renk'] ?? '#6366f1'));
    $a1 = array_map('floatval', explode(' ', $renk)); $a2 = array_map('floatval', explode(' ', $r2)); $n = 40;
    for ($i = 0; $i < $n; $i++) { $t = $i / ($n - 1); $c .= sprintf("%.3f %.3f %.3f rg %.2f %d %.2f 96 re f\n", $a1[0] + ($a2[0] - $a1[0]) * $t, $a1[1] + ($a2[1] - $a1[1]) * $t, $a1[2] + ($a2[2] - $a1[2]) * $t, $K * $i / $n, $Y - 96, $K / $n + 1); }
    $h1 = preg_match('/^#[0-9a-f]{6}$/i', (string)($o['renk'] ?? '')) ? $o['renk'] : '#6366f1'; $h2 = preg_match('/^#[0-9a-f]{6}$/i', (string)($o['renk2'] ?? '')) ? $o['renk2'] : $h1;
    $logo = ik_pdf_logo($o['logo_dosya'] ?? '', $h1, $h2, $ml, $K);
    if ($logo) {
        $lh = 50; $lw = min(220, $logo[1] * $lh / max(1, $logo[2])); $lh = $lw * $logo[2] / max(1, $logo[1]);
        $c .= "q $lw 0 0 $lh $ml " . ($Y - 96 + (96 - $lh) / 2) . " cm /Im1 Do Q\n"; $logoVar = true;
    } else $yaz($ml, $Y - 58, (string)($o['firma'] ?? 'İNSAN KAYNAKLARI'), 20, 'F2', '1 1 1');
    $y = $Y - 96 - 40;
    if (!empty($o['selam'])) { $yaz($ml, $y, 'Merhaba ' . $o['selam'] . ',', 12); $y -= 28; }
    foreach (ik_pdf_sar((string)($o['baslik'] ?? ''), 20, $gen, true) as $l) { $yaz($ml, $y, $l, 20, 'F2'); $y -= 26; }
    $y -= 4;
    foreach ((array)($o['paragraflar'] ?? []) as $p) { foreach (ik_pdf_sar($p, 12, $gen) as $l) { $yaz($ml, $y, $l, 12, 'F1', '0.2 0.25 0.33'); $y -= 18; } $y -= 4; }
    $y -= 8;
    foreach ((array)($o['satirlar'] ?? []) as $r) {
        $dl = ik_pdf_sar((string)$r[1], 12, $gen - 150); $yy = $y;
        $yaz($ml, $yy, (string)$r[0], 12, 'F2');
        foreach ($dl as $i => $l) $yaz($ml + 150, $yy - $i * 16, $l, 12, 'F1', '0.2 0.25 0.33');
        $y -= max(1, count($dl)) * 16 + 12;
        $c .= "0.886 0.910 0.941 RG 0.8 w $ml " . ($y + 6) . ' m ' . ($K - $ml) . ' ' . ($y + 6) . " l S\n"; $y -= 14;
        if ($y < 140) break;
    }
    if (!empty($o['link'])) { $y -= 4; $yaz($ml, $y, 'Ayrıntılar: ' . $o['link'], 10, 'F1', '0.39 0.45 0.55'); $y -= 22; }
    $y -= 10; $yaz($ml, $y, 'Sevgiler', 12, 'F1', '0.28 0.33 0.41'); $y -= 18;
    foreach (ik_pdf_sar((string)($o['imza'] ?? ''), 12, $gen, true) as $l) { $yaz($ml, $y, $l, 12, 'F2', '0.28 0.33 0.41'); $y -= 16; }
    $c .= "0.973 0.980 0.988 rg 0 0 $K 56 re f\n";
    $yaz($ml, 22, (string)($o['alt'] ?? 'Bu belge İK sistemi tarafından otomatik oluşturulmuştur.') . '  ' . date('d.m.Y H:i'), 9, 'F1', '0.58 0.64 0.72');
    // nesneler
    $ob = []; $ek = $logoVar ? ' /XObject << /Im1 8 0 R >>' : '';
    $ob[1] = '<< /Type /Catalog /Pages 2 0 R >>'; $ob[2] = '<< /Type /Pages /Kids [3 0 R] /Count 1 >>';
    $ob[3] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 $K $Y] /Resources << /Font << /F1 4 0 R /F2 5 0 R >>$ek >> /Contents 7 0 R >>";
    $ob[4] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding 6 0 R >>'; $ob[5] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding 6 0 R >>';
    $ob[6] = '<< /Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [128 /Gbreve /gbreve /Scedilla /scedilla /Idotaccent /dotlessi] >>';
    $ob[7] = '<< /Length ' . strlen($c) . " >>\nstream\n$c\nendstream";
    if ($logoVar) $ob[8] = '<< /Type /XObject /Subtype /Image /Width ' . $logo[1] . ' /Height ' . $logo[2] . ' /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length ' . strlen($logo[0]) . " >>\nstream\n" . $logo[0] . "\nendstream";
    $pdf = "%PDF-1.4\n"; $ofs = [];
    foreach ($ob as $n => $g) { $ofs[$n] = strlen($pdf); $pdf .= "$n 0 obj\n$g\nendobj\n"; }
    $xr = strlen($pdf); $say = count($ob) + 1;
    $pdf .= "xref\n0 $say\n0000000000 65535 f \n";
    foreach ($ofs as $x) $pdf .= sprintf("%010d 00000 n \n", $x);
    return $pdf . "trailer\n<< /Size $say /Root 1 0 R >>\nstartxref\n$xr\n%%EOF";
}
/** Giden e-postanın (başlık + HTML gövde + tablo) PDF kopyası */
function ik_mail_pdf_uret($db, $baslik, $html, $ad = '', $link = '')
{
    $satirlar = [];
    if (preg_match_all('~<tr><td[^>]*>(.*?)</td><td[^>]*>(.*?)</td></tr>~s', $html, $m, PREG_SET_ORDER))
        foreach ($m as $r) $satirlar[] = [trim(html_entity_decode(strip_tags($r[1]), ENT_QUOTES, 'UTF-8')), trim(html_entity_decode(strip_tags(preg_replace('~<br\s*/?>~i', ', ', $r[2])), ENT_QUOTES, 'UTF-8'))];
    $govde = preg_replace('~<table.*$~s', '', $html);
    $govde = trim(html_entity_decode(strip_tags(preg_replace('~<(br|/p|/div)\s*/?>~i', "\n", $govde)), ENT_QUOTES, 'UTF-8'));
    $firma = trim((string)ik_ayar($db, 'mail_firma_ad', '')) ?: 'İNSAN KAYNAKLARI'; $logo = trim((string)ik_ayar($db, 'mail_logo', ''));
    $logoDosya = ($logo !== '' && preg_match('~^uploads/k\d+/ik_logo/[A-Za-z0-9._-]+$~', $logo)) ? TB_KOK . '/' . $logo : '';
    return ik_pdf_belge(['firma' => $firma, 'renk' => ik_ayar($db, 'mail_renk', '#6366f1'), 'renk2' => ik_ayar($db, 'mail_renk2', ''), 'logo_dosya' => $logoDosya, 'selam' => $ad, 'baslik' => $baslik,
        'paragraflar' => $govde !== '' ? [$govde] : [], 'satirlar' => $satirlar, 'link' => $link, 'imza' => trim((string)ik_ayar($db, 'mail_imza', '')) ?: $firma]);
}
