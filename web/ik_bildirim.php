<?php
// ============================================================
// ik_bildirim.php — Bildirim API'si (site içi uyarı + ses + tarayıcı/telefon bildirimi)
//   GET  son        ?after=ID   → ID'den sonraki yeni bildirimler + okunmamış sayısı (sayfa her ~20 sn yoklar)
//   GET  liste                  → son 30 bildirim (zil menüsü)
//   GET  vapid                  → Web Push açık anahtarı
//   GET  push_liste             → servis çalışanı (ik-sw.js) için: henüz gösterilmemiş bildirimler (gösterilmiş olarak işaretlenir)
//   POST okundu     id | hepsi=1
//   POST abone      endpoint, p256dh, auth      · POST abone_sil endpoint
//   POST mobil_kayit token, platform(android|ios) · POST mobil_sil token   (mobil uygulama / Firebase)
// Giriş yapmış herkes kendi bildirimlerini görür. Oturum kilidi erken bırakılır (yoklama diğer istekleri yavaşlatmasın).
// ============================================================
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/ik_ortak.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function ikb_c($v, $k = 200) { $o = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); http_response_code($k); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('Content-Length: ' . strlen($o)); echo $o; exit; }
function ikb_h($m, $k = 400) { ikb_c(['ok' => false, 'hata' => $m], $k); }

$uid = intval($_SESSION['kullanici_id'] ?? 0);
$csrf = (string)($_SESSION['tb_csrf'] ?? '');
session_write_close(); // kilidi bırak
if ($uid <= 0) ikb_h('Oturum süresi doldu.', 401);
ik_kanal_sema($db);
$girdi = $_POST + $_GET;
$islem = (string)($girdi['islem'] ?? '');

function ikb_satir($r)
{
    return ['id' => intval($r['id']), 'baslik' => (string)$r['baslik'], 'mesaj' => (string)$r['mesaj'], 'link' => (string)$r['link'], 'tur' => (string)$r['tur'], 'okundu' => (bool)$r['okundu'], 'tarih' => (string)$r['olusturma']];
}
function ikb_okunmamis($db, $uid) { $q = $db->query("SELECT COUNT(*) FROM ik_bildirimler WHERE kullanici_id = $uid AND okundu = 0"); return $q ? intval($q->fetch_row()[0]) : 0; }

if ($islem === 'son') {
    if (!function_exists('pf_vdb_oto_arkada') && is_file(__DIR__ . '/platform_vekil.php')) { try { require_once __DIR__ . '/platform_vekil.php'; require_once __DIR__ . '/ik_kanal.php'; pf_vdb_oto_arkada($db); } catch (Throwable $e) {} }
    try { require_once __DIR__ . '/giris_rapor_mail.php'; giris_rapor_tara($db); } catch (Throwable $e) {}   // günlük giriş raporu (mail) zamanı geldiyse gönder   // otomatik veri tabanı yedeği (vekil sunucu) zamanı geldiyse arka planda çalıştır
    $after = max(0, intval($girdi['after'] ?? 0));
    $liste = [];
    if ($after === 0) {
        // İlk yükleme: yalnızca son numarayı döndür (eski bildirimler için ses / uyarı çalmasın)
        $son = intval($db->query("SELECT COALESCE(MAX(id), 0) FROM ik_bildirimler WHERE kullanici_id = $uid")->fetch_row()[0] ?? 0);
        ikb_c(['ok' => true, 'items' => [], 'son_id' => $son, 'okunmamis' => ikb_okunmamis($db, $uid)]);
    }
    $q = $db->query("SELECT * FROM ik_bildirimler WHERE kullanici_id = $uid AND id > $after ORDER BY id ASC LIMIT 20");
    $son = $after;
    if ($q) while ($r = $q->fetch_assoc()) { $liste[] = ikb_satir($r); $son = max($son, intval($r['id'])); }
    ikb_c(['ok' => true, 'items' => $liste, 'son_id' => $son, 'okunmamis' => ikb_okunmamis($db, $uid)]);
}
if ($islem === 'liste') {
    $liste = [];
    $q = $db->query("SELECT * FROM ik_bildirimler WHERE kullanici_id = $uid ORDER BY id DESC LIMIT 30");
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ikb_satir($r);
    ikb_c(['ok' => true, 'items' => $liste, 'okunmamis' => ikb_okunmamis($db, $uid)]);
}
if ($islem === 'vapid') {
    $v = ik_vapid($db);
    if (!$v) ikb_h('Bu sunucuda Web Push desteklenmiyor (PHP openssl eksik).', 501);
    ikb_c(['ok' => true, 'anahtar' => $v['public']]);
}
if ($islem === 'mobil_tani') {
    // Mobil bildirim tanısı: ayar var mı, kayıtlı cihaz kaç, test bildirimi gidiyor mu (yalnızca kendi cihazlarınıza)
    $ayar = ik_fcm_ayar();
    $cihazlar = []; $q = $db->query("SELECT id, platform, LEFT(token, 12) AS t, olusturma, son_basari FROM ik_mobil_cihazlar WHERE kullanici_id = $uid ORDER BY id DESC LIMIT 10");
    if ($q) while ($r = $q->fetch_assoc()) $cihazlar[] = $r;
    $sonuc = [];
    if ($ayar && $cihazlar) {
        $q = $db->query("SELECT id, token FROM ik_mobil_cihazlar WHERE kullanici_id = $uid");
        while ($c = $q->fetch_assoc()) {
            $GLOBALS['ik_fcm_son'] = null;
            $kod = ik_fcm_gonder($c['token'], 'Bilge360 İK', 'Bu bir test bildirimidir.', ['link' => 'ik.php', 'tur' => 'test', 'bildirim_id' => '0']);
            $sonuc[] = ['cihaz' => intval($c['id']), 'http' => $kod, 'ayrinti' => $GLOBALS['ik_fcm_son']['govde'] ?? ''];
        }
    }
    ikb_c(['ok' => true, 'fcm_servis_dosyasi' => $ayar !== null, 'proje' => $ayar['project_id'] ?? null, 'openssl' => function_exists('openssl_sign'), 'curl' => function_exists('curl_init'), 'kayitli_cihaz' => count($cihazlar), 'cihazlar' => $cihazlar, 'test_sonucu' => $sonuc,
        'yorum' => !$ayar ? 'config/fcm_servis.json yok ya da geçersiz.' : (!$cihazlar ? 'Bu kullanıcı için kayıtlı telefon yok: uygulama cihaz kodunu sunucuya kaydedememiş (bildirim izni verilmemiş, google-services.json eksik ya da web dosyaları sunucuda yüklü değil).' : 'Test bildirimi gönderildi; http 200 ise telefon almalı.')]);
}
if ($islem === 'push_liste') {
    $liste = [];
    $q = $db->query("SELECT * FROM ik_bildirimler WHERE kullanici_id = $uid AND push_durum = 0 AND olusturma > DATE_SUB(NOW(), INTERVAL 1 DAY) ORDER BY id ASC LIMIT 5");
    $ids = [];
    if ($q) while ($r = $q->fetch_assoc()) { $liste[] = ikb_satir($r); $ids[] = intval($r['id']); }
    if ($ids) $db->query("UPDATE ik_bildirimler SET push_durum = 1 WHERE id IN (" . implode(',', $ids) . ")");
    ikb_c(['ok' => true, 'items' => $liste]);
}

// ── Yazma işlemleri: CSRF ──
$gelen = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $girdi['_csrf'] ?? '');
if ($csrf === '' || !hash_equals($csrf, $gelen)) ikb_h('Oturum doğrulaması başarısız. Sayfayı yenileyin.', 419);

if ($islem === 'okundu') {
    if (!empty($girdi['hepsi'])) $db->query("UPDATE ik_bildirimler SET okundu = 1 WHERE kullanici_id = $uid AND okundu = 0");
    else $db->query("UPDATE ik_bildirimler SET okundu = 1 WHERE kullanici_id = $uid AND id = " . intval($girdi['id'] ?? 0));
    ikb_c(['ok' => true, 'okunmamis' => ikb_okunmamis($db, $uid)]);
}
if ($islem === 'abone') {
    $ep = trim((string)($girdi['endpoint'] ?? ''));
    if (!preg_match('~^https://~i', $ep) || strlen($ep) > 2000) ikb_h('Geçersiz abonelik.');
    $h = hash('sha256', $ep); $p = mb_substr((string)($girdi['p256dh'] ?? ''), 0, 200); $a = mb_substr((string)($girdi['auth'] ?? ''), 0, 100); $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200);
    $st = $db->prepare("INSERT INTO ik_push_aboneler (kullanici_id, endpoint_hash, endpoint, p256dh, auth, ua) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE kullanici_id = VALUES(kullanici_id), p256dh = VALUES(p256dh), auth = VALUES(auth), ua = VALUES(ua)");
    $st->bind_param('isssss', $uid, $h, $ep, $p, $a, $ua);
    if (!$st->execute()) ikb_h('Abonelik kaydedilemedi.', 500);
    ikb_c(['ok' => true]);
}
if ($islem === 'mobil_kayit') {
    // Mobil uygulama (Android/iOS) FCM cihaz kodunu kaydeder
    $tk = trim((string)($girdi['token'] ?? ''));
    if ($tk === '' || strlen($tk) > 512) ikb_h('Geçersiz cihaz kodu.');
    $pl = in_array(($girdi['platform'] ?? ''), ['android', 'ios'], true) ? $girdi['platform'] : ''; $h = hash('sha256', $tk); $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 200);
    $st = $db->prepare("INSERT INTO ik_mobil_cihazlar (kullanici_id, token_hash, token, platform, ua) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE kullanici_id = VALUES(kullanici_id), platform = VALUES(platform), ua = VALUES(ua)");
    $st->bind_param('issss', $uid, $h, $tk, $pl, $ua);
    if (!$st->execute()) ikb_h('Cihaz kaydedilemedi.', 500);
    ikb_c(['ok' => true, 'fcm' => ik_fcm_ayar() !== null]);
}
if ($islem === 'mobil_hata') {
    // Uygulamanın kendi durum / hata bildirimi (tanı panelinde görünür)
    $m = mb_substr(trim((string)($girdi['mesaj'] ?? '')), 0, 380, 'UTF-8');
    $st = $db->prepare("INSERT INTO ik_fcm_log (kullanici_id, http, sonuc, ayrinti) VALUES (?, 0, 'istemci', ?)");
    $st->bind_param('is', $uid, $m); $st->execute();
    ikb_c(['ok' => true]);
}
if ($islem === 'mobil_sil') {
    $h = hash('sha256', trim((string)($girdi['token'] ?? '')));
    $db->query("DELETE FROM ik_mobil_cihazlar WHERE kullanici_id = $uid AND token_hash = '$h'");
    ikb_c(['ok' => true]);
}
if ($islem === 'abone_sil') {
    $h = hash('sha256', trim((string)($girdi['endpoint'] ?? '')));
    $db->query("DELETE FROM ik_push_aboneler WHERE kullanici_id = $uid AND endpoint_hash = '$h'");
    ikb_c(['ok' => true]);
}
ikb_h('Bilinmeyen işlem.');
