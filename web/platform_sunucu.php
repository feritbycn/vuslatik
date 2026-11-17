<?php
// ============================================================
// platform_sunucu.php — Sunucu kopyası / taşıma algılama.
//   Uygulama dosyaları başka bir sunucuya komple kopyalanırsa (aynı veritabanıyla ya da veritabanı yedeğiyle) kopya kendini
//   "yeni sunucu" olarak kaydeder. Onaylanana kadar İKİ SUNUCU DA pasif olur (kurum girişleri kapanır); Platform yöneticisi
//   Kurumlar sayfasından hangi sunucunun devam edeceğini seçer, diğeri engellenir.
//   Sunucu kimliği = sunucu adı (gethostname) + uygulama klasörü yolu; alan adı/IP takma adları yanlış alarm üretmez.
// ============================================================
if (!function_exists('pf_sunucu_sema')) {

function pf_sunucu_sema($db)
{
    static $y = false; if ($y) return; $y = true; $o = $db->kurum; $db->kurum = 0;
    $db->query("CREATE TABLE IF NOT EXISTS pf_sunucu (
        id INT NOT NULL AUTO_INCREMENT, sid CHAR(40) NOT NULL, ad VARCHAR(190) NOT NULL DEFAULT '', yol VARCHAR(255) NOT NULL DEFAULT '', url VARCHAR(255) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '', durum VARCHAR(10) NOT NULL DEFAULT 'aktif', ilk DATETIME NULL, son DATETIME NULL,
        PRIMARY KEY (id), UNIQUE KEY u_sid (sid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->kurum = $o;
}
function pf_sunucu_kimlik()
{
    $ad = (string)@gethostname(); $yol = rtrim(str_replace('\\', '/', (string)(defined('TB_KOK') ? TB_KOK : dirname(__DIR__))), '/');
    $host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $url = $host !== '' ? ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . (string)($_SERVER['HTTP_HOST'] ?? '') . rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/') : '';
    return ['sid' => sha1($ad . '|' . $yol), 'ad' => mb_substr($ad, 0, 190, 'UTF-8'), 'yol' => mb_substr($yol, 0, 255, 'UTF-8'), 'url' => mb_substr($url, 0, 255, 'UTF-8'), 'ip' => mb_substr((string)($_SERVER['SERVER_ADDR'] ?? ''), 0, 45, 'UTF-8')];
}
function pf_sunucu_ayar($db, $k, $v = null)
{
    $o = $db->kurum; $db->kurum = 0; $e = $db->real_escape_string($k);
    if ($v !== null) { $v2 = $db->real_escape_string($v); $db->query("INSERT INTO pf_ayarlar (anahtar, deger) VALUES ('$e', '$v2') ON DUPLICATE KEY UPDATE deger = VALUES(deger)"); $db->kurum = $o; return $v; }
    $q = $db->query("SELECT deger FROM pf_ayarlar WHERE anahtar = '$e'"); $r = $q ? $q->fetch_row() : null; $db->kurum = $o; return $r ? (string)$r[0] : '';
}
function pf_sunucu_gizli($db) { $g = pf_sunucu_ayar($db, 'sunucu_gizli'); if (strlen($g) < 32) { $g = bin2hex(random_bytes(32)); pf_sunucu_ayar($db, 'sunucu_gizli', $g); } return $g; }
function pf_sunucu_imza($gizli, $olay, $zaman, $veri) { return hash_hmac('sha256', $olay . '|' . $zaman . '|' . $veri, $gizli); }

/** Diğer bilinen sunuculara imzalı bildirim gönderir (en iyi çaba; ulaşılamazsa sessizce geçer). */
function pf_sunucu_bildir($db, $olay, array $veri, $benSid)
{
    if (!function_exists('curl_init')) return;
    $gizli = pf_sunucu_gizli($db); $zaman = time(); $js = json_encode($veri, JSON_UNESCAPED_UNICODE);
    $o = $db->kurum; $db->kurum = 0; $q = $db->query("SELECT url FROM pf_sunucu WHERE sid <> '" . $db->real_escape_string($benSid) . "' AND url <> ''"); $urller = []; if ($q) while ($r = $q->fetch_row()) $urller[$r[0]] = 1; $db->kurum = $o;
    foreach (array_keys($urller) as $u) {
        $c = curl_init($u . 'sunucu_bildir.php');
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['olay' => $olay, 'zaman' => $zaman, 'veri' => $js, 'imza' => pf_sunucu_imza($gizli, $olay, $zaman, $js)]), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 3, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_FOLLOWLOCATION => false]);
        @curl_exec($c); @curl_close($c);
    }
}


/** Platform yöneticisine e-posta/SMS uyarısı (gönderen kurum kanalı kullanılır). Aynı konu 10 dakikada bir. */
function pf_uyari_bildir($db, $konu, $metin)
{
    try {
        $o = $db->kurum; $db->kurum = 0;
        $mail = trim(pf_sunucu_ayar($db, 'uyari_mail')); $tel = trim(pf_sunucu_ayar($db, 'uyari_tel')); $gk = intval(pf_sunucu_ayar($db, 'lm_kurum'));
        if (($mail === '' && $tel === '') || $gk <= 0) { $db->kurum = $o; return; }
        $anah = 'uyari_son_' . substr(sha1($konu), 0, 12);
        if (time() - intval(pf_sunucu_ayar($db, $anah)) < 600) { $db->kurum = $o; return; }
        pf_sunucu_ayar($db, $anah, (string)time());
        require_once __DIR__ . '/ik_kanal.php';
        $db->kurum = $gk; unset($GLOBALS['__ik_ayar']);
        if ($mail !== '' && ik_mail_hazir($db)) { $html = ik_mail_sablon($konu, nl2br(htmlspecialchars($metin, ENT_QUOTES, 'UTF-8')), '', '', $db, 'Yönetici'); ik_mail_gonder($db, $mail, $konu, $html, 'Yönetici'); }
        if ($tel !== '' && ik_sms_hazir($db)) ik_sms_gonder($db, $tel, mb_substr($konu . ' — ' . strtok($metin, "\n") . ' Panelden onaylayın.', 0, 300, 'UTF-8'));
        $db->kurum = 0; unset($GLOBALS['__ik_ayar']); $db->kurum = $o;
    } catch (Throwable $e) { $db->kurum = 0; }
}

/** Bu istek çalışabilir mi? null = evet; aksi halde ['durum' => 'bekliyor'|'engelli', 'ben' => satır]. */
function pf_sunucu_denetle($db)
{
    if (!empty($GLOBALS['tb_kurulum_sayfasi'])) return null;
    pf_sunucu_sema($db);
    if (pf_sunucu_ayar($db, 'sunucu_kilidi') === '0') return null;
    $k = pf_sunucu_kimlik(); $sid = $db->real_escape_string($k['sid']); $o = $db->kurum; $db->kurum = 0;
    $q = $db->query("SELECT sid, durum, son FROM pf_sunucu"); $satir = []; if ($q) while ($r = $q->fetch_assoc()) $satir[$r['sid']] = $r;
    $sonuc = null;
    if (!$satir) {                                           // ilk çalışan sunucu: kendini kaydeder
        $db->query("INSERT IGNORE INTO pf_sunucu (sid, ad, yol, url, ip, durum, ilk, son) VALUES ('$sid', '" . $db->real_escape_string($k['ad']) . "', '" . $db->real_escape_string($k['yol']) . "', '" . $db->real_escape_string($k['url']) . "', '" . $db->real_escape_string($k['ip']) . "', 'aktif', NOW(), NOW())");
    } elseif (!isset($satir[$k['sid']])) {                   // tanınmayan sunucu = kopya / taşıma
        $db->query("INSERT IGNORE INTO pf_sunucu (sid, ad, yol, url, ip, durum, ilk, son) VALUES ('$sid', '" . $db->real_escape_string($k['ad']) . "', '" . $db->real_escape_string($k['yol']) . "', '" . $db->real_escape_string($k['url']) . "', '" . $db->real_escape_string($k['ip']) . "', 'bekliyor', NOW(), NOW())");
        $db->kurum = $o; pf_sunucu_bildir($db, 'yeni', $k, $k['sid']); $db->kurum = 0;
        pf_uyari_bildir($db, 'Uygulama başka sunucuya taşındı', 'Uygulama yeni bir sunucuda çalıştırıldı ve onayınızı bekliyor.' . "\nSunucu: " . $k['ad'] . "\nAdres: " . ($k['url'] ?: '-') . "\nKlasör: " . $k['yol'] . "\nIP: " . ($k['ip'] ?: '-') . "\n\nOnaylayana kadar tüm sunucularda kurum girişleri kapalıdır. Platform → Kurumlar sayfasından seçim yapın.");
        $sonuc = ['durum' => 'bekliyor'];
    } else {
        $m = $satir[$k['sid']]; $bek = false; foreach ($satir as $s) if ($s['durum'] === 'bekliyor') $bek = true;
        if ($m['durum'] === 'engelli') $sonuc = ['durum' => 'engelli'];
        elseif ($m['durum'] === 'bekliyor' || $bek) $sonuc = ['durum' => 'bekliyor'];
        if (!$m['son'] || strtotime($m['son']) < time() - 300) $db->query("UPDATE pf_sunucu SET son = NOW()" . ($k['url'] !== '' ? ", url = '" . $db->real_escape_string($k['url']) . "'" : '') . " WHERE sid = '$sid'");
    }
    $db->kurum = $o; return $sonuc;
}
/** İstek engellenecekse yanıtı yazıp çıkar. Platform paneli (engelli değilse) onay verebilmek için açık kalır. */
function pf_sunucu_kapi($db)
{
    $d = pf_sunucu_denetle($db); if (!$d) return;
    $dosya = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($d['durum'] === 'bekliyor' && in_array($dosya, ['platform.php', 'platform_islem.php', 'sunucu_bildir.php', 'vekil_bildir.php'], true)) return;
    if ($dosya === 'sunucu_bildir.php') return;
    $mesaj = $d['durum'] === 'engelli' ? 'Bu sunucu devre dışı bırakıldı. Uygulama başka bir sunucuda devam ediyor.' : 'Sunucu değişikliği algılandı. Platform yöneticisi hangi sunucuda devam edileceğini onaylayana kadar sistem geçici olarak kapalıdır.';
    http_response_code(503); header('Retry-After: 600'); header('Cache-Control: no-store');
    if (strpos($dosya, '_islem') !== false || strpos($dosya, 'ik_bildirim') !== false || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'hata' => $mesaj, 'sunucu_kilidi' => $d['durum']], JSON_UNESCAPED_UNICODE); }
    elseif (PHP_SAPI === 'cli') { fwrite(STDERR, $mesaj . "\n"); }
    else echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Geçici olarak kapalı</title><body style="font-family:system-ui;display:grid;place-items:center;min-height:100vh;margin:0;background:#f1f5f9;color:#0f172a"><div style="max-width:440px;background:#fff;border-radius:18px;padding:28px;box-shadow:0 10px 40px rgba(0,0,0,.08);margin:16px"><h2 style="margin:0 0 10px">⏸ Geçici olarak kapalı</h2><p style="line-height:1.55;margin:0">' . htmlspecialchars($mesaj) . '</p></div></body>';
    exit;
}

}
