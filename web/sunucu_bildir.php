<?php
// sunucu_bildir.php — Sunucular arası imzalı bildirim: 'yeni' (yeni sunucu algılandı) ve 'karar' (hangi sunucu devam ediyor).
require_once __DIR__ . '/config/baglan.php';
header('Cache-Control: no-store'); header('Content-Type: application/json; charset=utf-8');
$olay = (string)($_POST['olay'] ?? ''); $zaman = intval($_POST['zaman'] ?? 0); $js = (string)($_POST['veri'] ?? ''); $imza = (string)($_POST['imza'] ?? '');
$gizli = pf_sunucu_gizli($db);
if (!in_array($olay, ['yeni', 'karar'], true) || abs(time() - $zaman) > 300 || !hash_equals(pf_sunucu_imza($gizli, $olay, $zaman, $js), $imza)) { http_response_code(403); echo '{"ok":false}'; exit; }
$v = json_decode($js, true); if (!is_array($v) || !preg_match('/^[a-f0-9]{40}$/', (string)($v['sid'] ?? $v['aktif'] ?? ''))) { http_response_code(400); echo '{"ok":false}'; exit; }
pf_sunucu_sema($db); $db->kurum = 0; $e = function ($x, $n) use ($db) { return $db->real_escape_string(mb_substr((string)$x, 0, $n, 'UTF-8')); };
if ($olay === 'yeni') {
    $db->query("INSERT IGNORE INTO pf_sunucu (sid, ad, yol, url, ip, durum, ilk, son) VALUES ('" . $e($v['sid'], 40) . "', '" . $e($v['ad'] ?? '', 190) . "', '" . $e($v['yol'] ?? '', 255) . "', '" . $e($v['url'] ?? '', 255) . "', '" . $e($v['ip'] ?? '', 45) . "', 'bekliyor', NOW(), NOW())");
} else {
    $a = $e($v['aktif'], 40);
    $db->query("INSERT IGNORE INTO pf_sunucu (sid, durum, ilk, son) VALUES ('$a', 'aktif', NOW(), NOW())");
    $db->query("UPDATE pf_sunucu SET durum = IF(sid = '$a', 'aktif', 'engelli')");
    pf_log($db, 'sunucu_karar', 'Sunucu kararı başka sunucudan alındı', null);
}
echo '{"ok":true}';
