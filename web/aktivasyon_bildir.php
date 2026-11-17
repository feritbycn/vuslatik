<?php
// aktivasyon_bildir.php — ANA SUNUCU: müşteri kurulumlarının aktivasyon / doğrulama isteklerini yanıtlar.
// İstek, pakete özel gizli anahtarla imzalıdır; yanıt da imzalanır (ve varsa ed25519 ile mühürlenir).
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/aktivasyon_ortak.php';
header('Cache-Control: no-store'); header('Content-Type: application/json; charset=utf-8');
if (TB_TEK_KURUM > 0) { http_response_code(404); echo '{"ok":false}'; exit; }          // müşteri kurulumunda bu uç nokta çalışmaz
$aid = (string)($_POST['aid'] ?? ''); $olay = (string)($_POST['olay'] ?? ''); $zaman = intval($_POST['zaman'] ?? 0); $js = (string)($_POST['veri'] ?? ''); $imza = (string)($_POST['imza'] ?? '');
if (!preg_match('/^A[A-F0-9]{16}$/', $aid) || !in_array($olay, ['etkinlestir', 'dogrula'], true) || abs(time() - $zaman) > 600) { http_response_code(400); echo '{"ok":false}'; exit; }
akt_sema($db); $db->kurum = 0;
$st = $db->prepare("SELECT anahtar FROM pf_aktivasyon WHERE aid = ?"); $st->bind_param('s', $aid); $st->execute(); $q = $st->get_result(); $row = $q ? $q->fetch_row() : null;
if (!$row || !hash_equals(akt_imza($row[0], $olay, $zaman, $js), $imza)) { http_response_code(403); echo '{"ok":false}'; exit; }
$v = json_decode($js, true); if (!is_array($v) || !preg_match('/^[a-f0-9]{16}$/', (string)($v['nonce'] ?? ''))) { http_response_code(400); echo '{"ok":false}'; exit; }
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
$r = akt_istek_isle($db, $aid, $olay, $v, $ip);
$cevap = ['yanit' => '', 'imza' => '', 'ed' => ''];
$yj = json_encode($r, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$cevap['yanit'] = $yj; $cevap['imza'] = hash_hmac('sha256', $yj . '|' . $v['nonce'], $row[0]);
if (!empty($r['ok']) && !empty($r['belirtec'])) {
    $c = akt_anahtar_cifti($db);
    if ($c['sk'] !== '') $cevap['ed'] = bin2hex(sodium_crypto_sign_detached(json_encode($r['belirtec'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), hex2bin($c['sk'])));
}
echo json_encode($cevap);
