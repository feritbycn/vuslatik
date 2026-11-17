<?php
// Cron (her dakika): * * * * * php /yol/giris_rapor_cron.php
// Tüm kurumların günlük giriş raporlarını zamanı geldiyse gönderir.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/giris_rapor_mail.php';
$db->kurum = 0; $kurumlar = [];
$q = $db->query("SELECT * FROM pf_kurumlar ORDER BY id"); if ($q) while ($r = $q->fetch_assoc()) $kurumlar[] = $r;
foreach ($kurumlar as $k) {
    if (function_exists('pf_kurum_kullanilabilir') && !pf_kurum_kullanilabilir($k)) continue;
    $db->kurum = intval($k['id']); $GLOBALS['__ik_ayar'] = null; $GLOBALS['KURUM'] = $k;
    try { giris_rapor_tara($db); } catch (Throwable $e) { fwrite(STDERR, $k['kod'] . ': ' . $e->getMessage() . "\n"); }
}
$db->kurum = 0;
