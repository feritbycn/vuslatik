<?php
// vekil_bildir.php — Vekil sunucu, anahtar dosyası silindiğinde / vekil dosyası değiştirildiğinde ana sunucuya imzasız bildirim gönderir.
// Yalnızca uyarı kaydeder (hiçbir yetki vermez); aynı kurum için art arda gelen bildirimler birleştirilir.
require_once __DIR__ . '/config/baglan.php';
header('Cache-Control: no-store');
$kod = strtolower(trim((string)($_GET['kod'] ?? ''))); $olay = (string)($_GET['olay'] ?? ''); $host = pf_vekil_host_norm($_GET['host'] ?? '');
if (in_array($olay, ['anahtar_yok', 'kod_degisti'], true) && preg_match('/^[a-z0-9_-]{1,40}$/', $kod)) {
    $k = pf_kurum_kod($db, $kod);
    if ($k && pf_vekil_al($db, $k['id'])) {
        $ayr = $olay === 'anahtar_yok' ? 'Vekil sunucuda anahtar dosyası (vekil.anahtar.php) silinmiş ya da değiştirilmiş' : 'Vekil sunucuda index.php dosyası değiştirilmiş';
        pf_vekil_uyari($db, intval($k['id']), $olay, $ayr . ($host !== '' ? ' — adres: ' . mb_substr($host, 0, 120, 'UTF-8') : ''), pf_vekil_ip($_SERVER['REMOTE_ADDR'] ?? ''));
    }
}
http_response_code(204);
