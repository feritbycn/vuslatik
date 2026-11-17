<?php
// ============================================================
// config/baglan.php — Çekirdek: oturum, veritabanı ($db), kurum çözümleme, CSRF, yardımcılar
//   Her sayfa / uç nokta bunu ekler. Oturumdaki kurum numarasına göre $db tabloları otomatik "k{no}_" ön ekiyle çalışır.
//   Oturum anahtarları: kurum_id · kullanici_id · ad_soyad · tb_csrf · pf_yonetici_id (platform yöneticisi)
// ============================================================
if (defined('TB_BAGLAN')) return;
define('TB_BAGLAN', true);
define('TB_KOK', dirname(__DIR__));
define('TB_SURUM', '1.0');
$tb_surum = TB_SURUM;

$TB_DB = is_file(__DIR__ . '/db.php') ? (require __DIR__ . '/db.php') : [];
date_default_timezone_set($TB_DB['saat_dilimi'] ?? 'Europe/Istanbul');
mb_internal_encoding('UTF-8');

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) header('Strict-Transport-Security: max-age=15552000');
    header('Permissions-Policy: geolocation=(self), camera=(), microphone=()');
}
if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name($TB_DB['oturum_adi'] ?? 'TBSID');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    ini_set('session.gc_maxlifetime', '28800');
    @session_start();
}
if (empty($_SESSION['tb_csrf'])) $_SESSION['tb_csrf'] = bin2hex(random_bytes(24));

require_once TB_KOK . '/vdb.php';

// JSON uç noktalarında ölümcül PHP hatası boş sayfa yerine okunabilir JSON olarak döner (arayüz nedenini gösterebilsin)
if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '_islem') !== false || strpos($_SERVER['SCRIPT_NAME'] ?? '', 'ik_bildirim') !== false) {
    register_shutdown_function(function () {
        $e = error_get_last();
        if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_RECOVERABLE_ERROR], true)) {
            if (!headers_sent()) { http_response_code(500); header('Content-Type: application/json; charset=utf-8'); }
            @error_log('[TB] ' . $e['message'] . ' @ ' . $e['file'] . ':' . $e['line']);
            $ayr = !empty($_SESSION['pf_yonetici_id']) ? ': ' . $e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')' : '. Sorun devam ederse yöneticinize bildirin.';   // iç ayrıntılar yalnızca platform yöneticisine gösterilir
            echo json_encode(['ok' => false, 'hata' => 'Sunucu hatası' . $ayr], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
    });
}

// Host başlığı enjeksiyonu: geçersiz alan adı içeren istekler reddedilir (bağlantı/e-posta adresleri bu başlıktan türetilebilir)
if (PHP_SAPI !== 'cli' && isset($_SERVER['HTTP_HOST']) && !preg_match('/^[a-z0-9]([a-z0-9.\-]*[a-z0-9])?(:\d{1,5})?$|^\[[0-9a-f:]+\](:\d{1,5})?$/i', (string)$_SERVER['HTTP_HOST'])) { http_response_code(400); exit('Geçersiz istek.'); }
mysqli_report(MYSQLI_REPORT_OFF);
$db = @new VDB($TB_DB['host'] ?? 'localhost', $TB_DB['kullanici'] ?? '', $TB_DB['sifre'] ?? '', $TB_DB['veritabani'] ?? '', intval($TB_DB['port'] ?? 3306));
$tb_kilit = is_file(__DIR__ . '/kurulum.kilit');
$tb_kurulum_sayfasi = basename($_SERVER['SCRIPT_NAME'] ?? '') === 'kurulum.php';
$tb_json_ucu = strpos($_SERVER['SCRIPT_NAME'] ?? '', '_islem') !== false || strpos($_SERVER['SCRIPT_NAME'] ?? '', 'ik_bildirim') !== false;
if ($db->connect_errno && !$tb_kilit && !$tb_kurulum_sayfasi && !$tb_json_ucu && !headers_sent() && is_file(TB_KOK . '/kurulum.php')) { header('Location: kurulum.php'); exit; }
if ($db->connect_errno) {
    http_response_code(500);
    if (stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false || strpos($_SERVER['SCRIPT_NAME'] ?? '', '_islem') !== false) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'hata' => 'Veritabanına bağlanılamadı (config/db.php eksik ya da bilgiler hatalı). Kurulum sayfasını açıp bilgileri yeniden girin.']);
    } else {
        echo '<!doctype html><meta charset="utf-8"><title>Veritabanı</title><body style="font-family:system-ui;padding:40px"><h2>Veritabanına bağlanılamadı</h2><p><code>config/db.php</code> dosyasındaki bilgileri kontrol edin.</p></body>';
    }
    exit;
}
$db->set_charset('utf8mb4');
$db->query("SET time_zone = '" . date('P') . "'");

require_once TB_KOK . '/platform_ortak.php';
pf_sema($db);
pf_lisans_sema($db);
if (PHP_SAPI !== 'cli') tb_hiz_kapi($db);                          // DoS / kaba kuvvet: IP ve oturum bazlı hız sınırı
require_once TB_KOK . '/platform_vekil.php';
$TB_VEKIL_KURUM = intval(pf_vekil_dogrula($db) ?: 0);       // uzak (vekil) sunucudan gelen imzalı istek mi? (imza yoksa 0; geçersizse 403 ile durur)
require_once TB_KOK . '/platform_sunucu.php';
pf_sunucu_kapi($db);                                         // uygulama başka sunucuya kopyalandıysa onaylanana kadar durdurur
if (!$tb_kilit && !$tb_kurulum_sayfasi) {                      // kurulum tamamlanmamış: yönetici varsa kilidi yaz, yoksa sihirbaza yönlendir
    $q = $db->query("SELECT COUNT(*) FROM pf_yoneticiler"); $r = $q ? $q->fetch_row() : [0];
    if (intval($r[0]) > 0) @file_put_contents(__DIR__ . '/kurulum.kilit', date('c'));
    elseif ($tb_json_ucu) { tb_json(['ok' => false, 'hata' => 'Kurulum tamamlanmamış. Tarayıcıda kurulum sayfasını açın.'], 503); }
    elseif (is_file(TB_KOK . '/kurulum.php')) { header('Location: kurulum.php'); exit; }
}

// ── Kurum çözümleme: oturumda kurum varsa ve hâlâ aktifse $db o kuruma bağlanır ──
$KURUM = null;
if (empty($_SESSION['kullanici_id']) && !empty($_COOKIE['tb_hatirla']) && ($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {   // "Beni hatırla": oturum düşmüşse çerezden geri aç
    $hk = pf_hatirla_coz($db);
    if ($hk) {
        $kk = pf_kurum($db, $hk[0]);
        if ($kk && pf_kurum_kullanilabilir($kk) && (!$TB_VEKIL_KURUM || $TB_VEKIL_KURUM === intval($hk[0])) && ($TB_VEKIL_KURUM || pf_vekil_uzak_mi($db, $hk[0]) === false)) {
            $db->kurum = $hk[0];
            $kq = $db->query("SELECT ad_soyad, aktif, sifre_degistir FROM kullanicilar WHERE id = " . $hk[1]); $kr = $kq ? $kq->fetch_assoc() : null;
            $db->kurum = 0;
            if ($kr && intval($kr['aktif']) === 1 && intval($kr['sifre_degistir']) !== 1) {
                session_regenerate_id(true);
                $_SESSION['kurum_id'] = $hk[0]; $_SESSION['kullanici_id'] = $hk[1]; $_SESSION['ad_soyad'] = (string)$kr['ad_soyad']; $_SESSION['tb_csrf'] = bin2hex(random_bytes(24));
            } else pf_hatirla_sil($db);
        } else pf_hatirla_sil($db);
    } else pf_hatirla_cerez('', time() - 3600);
}
if (!empty($_SESSION['kurum_id']) && !empty($_SESSION['kullanici_id'])) {
    $KURUM = pf_kurum($db, intval($_SESSION['kurum_id']));
    // Vekil sunucudan gelen istek yalnızca kendi kurumunun oturumunu kullanabilir; uzak sunucuda çalışan kurum ana adresten (destek girişi hariç) açılmaz
    $vekil_ret = $KURUM && (($TB_VEKIL_KURUM && $TB_VEKIL_KURUM !== intval($KURUM['id'])) || (!$TB_VEKIL_KURUM && empty($_SESSION['pf_yonetici_id']) && pf_vekil_uzak_mi($db, $KURUM['id']) !== false));
    if (!$KURUM || $vekil_ret || !pf_kurum_kullanilabilir($KURUM)) {          // kurum silindi / askıya alındı / kullanım süresi bitti
        unset($_SESSION['kurum_id'], $_SESSION['kullanici_id'], $_SESSION['ad_soyad']);
        $KURUM = null;
    } else {
        $db->kurum = intval($KURUM['id']);
        $q = $db->query("SELECT aktif FROM kullanicilar WHERE id = " . intval($_SESSION['kullanici_id']));
        $r = $q ? $q->fetch_row() : null;
        if ($q && (!$r || intval($r[0]) !== 1)) {          // kullanıcı silindi / pasif
            unset($_SESSION['kurum_id'], $_SESSION['kullanici_id'], $_SESSION['ad_soyad']);
            $KURUM = null; $db->kurum = 0;
        }
    }
}
define('TB_KURUM_ID', $KURUM ? intval($KURUM['id']) : 0);
// Tek kurum paketi olarak kurulduysa (config/kurum.id): bu sunucu yalnızca o kurumu barındırır; platform paneli ve kurum seçimi kapalıdır
define('TB_TEK_KURUM', $TB_VEKIL_KURUM > 0 ? $TB_VEKIL_KURUM : (is_file(__DIR__ . '/kurum.id') ? max(0, intval(@file_get_contents(__DIR__ . '/kurum.id'))) : 0));
define('TB_VEKIL_ISTEK', $TB_VEKIL_KURUM > 0);   // vekil sunucudan gelen istek: veri ve platform bu (ana) sunucuda, paket sistemi çalışır
define('TB_PROFIL_KLASOR', 'uploads/k' . TB_KURUM_ID . '/profil');

/** Giriş yapılmamışsa giriş sayfasına yönlendirir */
function girisKontrol()
{
    if (TB_KURUM_ID > 0 && !empty($_SESSION['kullanici_id'])) return;
    $git = 'giris.php?don=' . rawurlencode(basename($_SERVER['SCRIPT_NAME'] ?? 'ik.php'));
    if (!headers_sent()) header('Location: ' . $git);
    else echo '<script>location.href=' . json_encode($git) . '</script>';
    exit;
}
function csrf_token() { return (string)($_SESSION['tb_csrf'] ?? ''); }
function csrf_dogru($g = null)
{
    $gelen = (string)($g ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? $_GET['_csrf'] ?? ''));
    return !empty($_SESSION['tb_csrf']) && hash_equals((string)$_SESSION['tb_csrf'], $gelen);
}
function tb_json($v, $kod = 200)
{
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store');
    echo json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}
function tb_istemci_ip()
{
    $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** Yüklenen dosyanın içeriği uzantıyla uyumlu mu? (uzantı değiştirilmiş zararlı dosyalara karşı) true = uygun / bilinmeyen tür */
function tb_dosya_icerik_uygun($tmp, $uz)
{
    $uz = strtolower((string)$uz); $f = @fopen($tmp, 'rb'); if (!$f) return false; $b = (string)fread($f, 4096); fclose($f);
    if ($b === '') return in_array($uz, ['txt', 'csv'], true);
    $imza = [
        'jpg' => ["\xFF\xD8\xFF"], 'jpeg' => ["\xFF\xD8\xFF"], 'png' => ["\x89PNG\r\n\x1a\n"], 'gif' => ['GIF87a', 'GIF89a'], 'pdf' => ['%PDF-'],
        'docx' => ["PK\x03\x04"], 'xlsx' => ["PK\x03\x04"], 'pptx' => ["PK\x03\x04"], 'zip' => ["PK\x03\x04", "PK\x05\x06"],
        'doc' => ["\xD0\xCF\x11\xE0"], 'xls' => ["\xD0\xCF\x11\xE0"], 'ppt' => ["\xD0\xCF\x11\xE0"],
    ];
    if ($uz === 'webp') return substr($b, 0, 4) === 'RIFF' && substr($b, 8, 4) === 'WEBP';
    if (isset($imza[$uz])) { foreach ($imza[$uz] as $m) if (strncmp($b, $m, strlen($m)) === 0) return true; return false; }
    if (in_array($uz, ['txt', 'csv'], true)) return strpos($b, "\0") === false && stripos($b, '<?php') === false && stripos($b, '<script') === false;
    return true;
}

// ── Kuruma özel yükleme klasörleri (uploads/k{no}/...) ──
function tb_yukleme_web($ad) { return 'uploads/k' . TB_KURUM_ID . '/' . $ad; }
/** Kurumun yükleme klasörü (yoksa oluşturur). $acik=false → tarayıcıdan doğrudan erişim kapalı (.htaccess) */
function tb_yukleme_dizin($ad, $acik = false)
{
    $d = TB_KOK . '/' . tb_yukleme_web($ad);
    if (!is_dir($d)) @mkdir($d, 0755, true);
    if (!$acik && is_dir($d)) {
        if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
        if (!is_file($d . '/index.html')) @file_put_contents($d . '/index.html', '');
    }
    return $d;
}
function tb_profil_klasoru() { return tb_yukleme_dizin('profil', true); }
function tb_profil_resmi_yolu($deger)
{
    $deger = trim((string)$deger);
    return $deger !== '' && preg_match('~^uploads/k\d+/profil/[\w.\-]+$~', $deger) ? $deger : '';
}
function tb_profil_resmi_src($deger)
{
    $y = tb_profil_resmi_yolu($deger);
    if ($y === '') return '';
    $t = @filemtime(TB_KOK . '/' . $y);
    return $y . ($t ? '?v=' . $t : '');
}
