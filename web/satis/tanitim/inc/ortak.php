<?php
// ============================================================
// inc/ortak.php — ayarlar, güvenlik, yardımcılar (tanıtım sitesi)
// Veriler data/ klasöründe JSON olarak tutulur; veritabanı gerekmez.
// ============================================================
if (defined('TS_ORTAK')) return;
define('TS_ORTAK', true);
define('TS_KOK', dirname(__DIR__));
define('TS_VERI', TS_KOK . '/data');
date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ───────── ayarlar ───────── */
function ts_varsayilan()
{
    return [
        'marka' => 'Turkuaz Bileşim',
        'baslik' => 'İK Programı: İzin, Mesai, Puantaj ve Giriş-Çıkış | Turkuaz Bileşim',
        'aciklama' => 'İzin, onay akışı, mesai, puantaj, giriş-çıkış ve özlük evraklarını tek İK programında toplayın. SMS ve e-posta bildirimi. 15 gün ücretsiz deneyin.',
        'anahtar' => 'ik yazılımı, insan kaynakları programı, personel takip programı, izin takip programı, puantaj programı, mesai takip programı, personel giriş çıkış takip, pdks programı, özlük dosyası programı, online izin talep formu, bordro takip, ücretsiz ik programı denemesi',
        'alan_adi' => '',
        'og_gorsel' => 'assets/img/og.png',
        'ga4' => '', 'gtm' => '', 'gsc' => '', 'bing' => '', 'meta_pixel' => '',
        'cerez_bandi' => '1',
        'robots_index' => '1',
        'whatsapp' => '', 'whatsapp_mesaj' => 'Merhaba, Turkuaz Bileşim İK programının 15 günlük ücretsiz denemesi hakkında bilgi almak istiyorum.',
        'telefon' => '', 'eposta' => '', 'talep_eposta' => '', 'adres' => '', 'sehir' => 'İstanbul',
        'instagram' => '', 'linkedin' => '', 'x' => '', 'youtube' => '', 'facebook' => '',
        'deneme_gun' => '15',
        'ek_head' => '', 'ek_body' => '',
    ];
}
function ts_ayar_tumu()
{
    static $c = null;
    if ($c !== null) return $c;
    $c = ts_varsayilan();
    $d = @file_get_contents(TS_VERI . '/ayarlar.json');
    $j = $d ? json_decode($d, true) : null;
    if (is_array($j)) foreach ($j as $k => $v) if (array_key_exists($k, $c) && is_string($v)) $c[$k] = $v;
    return $c;
}
function ts_ayar($k, $v = '') { $a = ts_ayar_tumu(); return isset($a[$k]) && $a[$k] !== '' ? $a[$k] : $v; }
function ts_json_oku($ad, $vars = [])
{
    $d = @file_get_contents(TS_VERI . '/' . $ad);
    $j = $d ? json_decode($d, true) : null;
    return is_array($j) ? $j : $vars;
}
/** Kilitli, atomik yazım */
function ts_json_yaz($ad, $veri)
{
    if (!is_dir(TS_VERI)) @mkdir(TS_VERI, 0750, true);
    ts_veri_koru();
    $yol = TS_VERI . '/' . $ad;
    $gecici = $yol . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($gecici, json_encode($veri, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT), LOCK_EX) === false) return false;
    return @rename($gecici, $yol);
}
function ts_veri_koru()
{
    if (!is_file(TS_VERI . '/.htaccess')) @file_put_contents(TS_VERI . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    if (!is_file(TS_VERI . '/index.html')) @file_put_contents(TS_VERI . '/index.html', '');
}
function ts_ayar_kaydet(array $yeni)
{
    $a = ts_varsayilan(); $mevcut = ts_json_oku('ayarlar.json', []);
    foreach ($a as $k => $v) if (isset($mevcut[$k])) $a[$k] = $mevcut[$k];
    foreach ($yeni as $k => $v) if (array_key_exists($k, $a) && is_string($v)) $a[$k] = trim($v);
    return ts_json_yaz('ayarlar.json', $a);
}

/* ───────── adres ───────── */
function ts_https() { return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'); }
function ts_taban()
{
    $ad = trim(ts_ayar('alan_adi', ''));
    if ($ad !== '') { $ad = preg_replace('~/+$~', '', $ad); if (!preg_match('~^https?://~i', $ad)) $ad = 'https://' . $ad; return $ad; }
    $host = preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $_SERVER['HTTP_HOST'] ?? '') ? $_SERVER['HTTP_HOST'] : 'localhost';
    $yol = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
    if (substr($yol, -9) === '/yonetim') $yol = substr($yol, 0, -9);
    return (ts_https() ? 'https' : 'http') . '://' . $host . $yol;
}
function ts_url($yol = '') { return ts_taban() . '/' . ltrim($yol, '/'); }

/* ───────── oturum / CSRF ───────── */
function ts_oturum()
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('TSOTURUM');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => ts_https(), 'httponly' => true, 'samesite' => 'Lax']);
    @session_start();
}
function ts_csrf()
{
    ts_oturum();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
    return $_SESSION['csrf'];
}
function ts_csrf_ok($t) { ts_oturum(); return !empty($_SESSION['csrf']) && is_string($t) && hash_equals($_SESSION['csrf'], $t); }

/* ───────── hız sınırı (IP başına) ───────── */
function ts_hiz($ad, $limit, $saniye)
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0';
    $d = ts_json_oku('hiz.json', []); $simdi = time(); $k = $ad . '|' . $ip;
    foreach ($d as $a => $liste) { $d[$a] = array_values(array_filter($liste, function ($t) use ($simdi) { return $t > $simdi - 86400; })); if (!$d[$a]) unset($d[$a]); }
    $liste = array_values(array_filter($d[$k] ?? [], function ($t) use ($simdi, $saniye) { return $t > $simdi - $saniye; }));
    if (count($liste) >= $limit) return false;
    $liste[] = $simdi; $d[$k] = $liste; ts_json_yaz('hiz.json', $d);
    return true;
}

/* ───────── ikon (lucide, satır içi) ───────── */
function ik($ad, $sinif = '')
{
    static $onbellek = [];
    if (!preg_match('/^[a-z0-9-]+$/', $ad)) return '';
    if (!isset($onbellek[$ad])) {
        $s = @file_get_contents(TS_KOK . '/assets/icons/' . $ad . '.svg');
        $onbellek[$ad] = $s ? preg_replace(['/<!--.*?-->/s', '/<title>.*?<\/title>/s', '/\s+(width|height|class|role)="[^"]*"/', '/\s+/'], ['', '', '', ' '], $s) : '';
        if ($onbellek[$ad] !== '' && strpos($onbellek[$ad], 'fill=') === false) $onbellek[$ad] = str_replace('<svg ', '<svg fill="currentColor" ', $onbellek[$ad]);
    }
    return str_replace('<svg ', '<svg class="ik ' . h($sinif) . '" aria-hidden="true" focusable="false" ', $onbellek[$ad]);
}

/* ───────── WhatsApp ───────── */
function ts_wa_numara()
{
    $n = preg_replace('/\D+/', '', ts_ayar('whatsapp', ''));
    if ($n === '') return '';
    if (strlen($n) === 11 && $n[0] === '0') $n = '90' . substr($n, 1);
    elseif (strlen($n) === 10) $n = '90' . $n;
    return $n;
}
function ts_wa_link($mesaj = null)
{
    $n = ts_wa_numara(); if ($n === '') return '';
    return 'https://wa.me/' . $n . '?text=' . rawurlencode($mesaj ?? ts_ayar('whatsapp_mesaj', ''));
}

/* ───────── yönetici ───────── */
function ts_yonetici() { return ts_json_oku('yonetici.json', []); }
function ts_giris_yapmis() { ts_oturum(); return !empty($_SESSION['yonetici']) && $_SESSION['yonetici'] === 1; }
