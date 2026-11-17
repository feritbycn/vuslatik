<?php
// Cron (her dakika): * * * * * php /yol/fcm_cron.php
// Tüm kurumların bekleyen / yeniden denenecek telefon (FCM) bildirimlerini gönderir. Yalnızca komut satırından çalışır.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/ik_ortak.php';
$db->kurum = 0; $kurumlar = [];
$q = $db->query("SELECT * FROM pf_kurumlar ORDER BY id"); if ($q) while ($r = $q->fetch_assoc()) $kurumlar[] = $r;
foreach ($kurumlar as $k) {
    if (($k['durum'] ?? '') !== 'aktif') continue;   // NOT: pf_kurum_kullanilabilir() çağrılmaz — komut satırında lisans doğrulaması (aktivasyon) tetiklenip kurulumu kilitleyebilir
    $db->kurum = intval($k['id']); $GLOBALS['__ik_ayar'] = null; $GLOBALS['KURUM'] = $k;
    try { ik_kanal_sema($db); ik_fcm_kuyruk_calistir($db, 20); $db->query("DELETE FROM ik_fcm_log WHERE zaman < DATE_SUB(NOW(), INTERVAL 30 DAY)"); } catch (Throwable $e) { fwrite(STDERR, ($k['kod'] ?? $k['id']) . ': ' . $e->getMessage() . "\n"); }
}
$db->kurum = 0;
