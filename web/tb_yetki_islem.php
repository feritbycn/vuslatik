<?php
// ============================================================
// tb_yetki_islem.php — "Yetkiler" penceresi (assets/js/tb-yetki-panel.js) uç noktası
//   Yalnızca kurum yetkilisi (rol: kurucu) kullanabilir. Kurallar kuruma özeldir (k{no}_modul_yetkileri).
//   GET liste | kontrol   ·   POST kaydet | hedef_sil
// ============================================================
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/tb_yetki_motor.php';
$uid = intval($_SESSION['kullanici_id'] ?? 0);
if ($uid <= 0 || TB_KURUM_ID <= 0) tb_json(['ok' => false, 'hata' => 'Oturum süresi doldu, lütfen yeniden giriş yapın.'], 401);
function yh($m, $k = 400) { tb_json(['ok' => false, 'hata' => $m], $k); }
$ben = vy_kullanici($db, $uid);
if (!$ben['kurucu']) yh('Yetkileri yalnızca kurum yetkilisi yönetebilir.', 403);
$girdi = $_POST + $_GET;
$islem = (string)($girdi['islem'] ?? '');
$modul = (string)($girdi['modul'] ?? '');
$tan = vy_tanimlar()[$modul] ?? null;
if (!$tan) yh('Bilinmeyen modül.');
vy_tablo($db);
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_dogru()) yh('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.', 419);

/** Hedefin görünen adı */
function yp_ad($db, $tip, $deger)
{
    if ($tip === 'herkes') return 'Tüm kullanıcılar';
    if ($tip === 'kullanici') { $q = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = " . intval($deger)); $r = $q ? $q->fetch_row() : null; return $r ? (string)$r[0] : '(silinmiş kullanıcı)'; }
    return (string)$deger;
}

if ($islem === 'liste') {
    $roller = ['personel', 'yetkili'];
    $gorevler = []; $q = $db->query("SELECT DISTINCT gorev FROM kullanicilar WHERE gorev <> '' AND aktif = 1 ORDER BY gorev");
    if ($q) while ($r = $q->fetch_row()) $gorevler[] = (string)$r[0];
    $birimler = []; $unvanlar = [];
    $q = @$db->query("SELECT DISTINCT departman FROM ik_personel WHERE departman <> '' ORDER BY departman"); if ($q) while ($r = $q->fetch_row()) $birimler[] = (string)$r[0];
    $q = @$db->query("SELECT DISTINCT unvan FROM ik_personel WHERE unvan <> '' ORDER BY unvan"); if ($q) while ($r = $q->fetch_row()) $unvanlar[] = (string)$r[0];
    $kisiler = []; $q = $db->query("SELECT id, ad_soyad, gorev, rol FROM kullanicilar WHERE aktif = 1 ORDER BY ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) $kisiler[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'gorev' => $r['gorev'], 'rol' => $r['rol']];
    $yet = [];
    foreach ($tan['yetkiler'] as $a => $t) {
        $v = !empty($t['herkes']) ? 'Herkes' : (!empty($t['roller']['yetkili']) ? 'Yalnızca “yetkili” rolü' : 'Kapalı');
        $yet[] = ['anahtar' => $a, 'ad' => $t['ad'], 'varsayilan' => $v];
    }
    $grup = [];
    foreach (vy_kurallar($db, $modul, true) as $r) {
        $k = $r['hedef_tipi'] . '|' . $r['hedef_deger'];
        if (!isset($grup[$k])) $grup[$k] = ['tip' => $r['hedef_tipi'], 'deger' => (string)$r['hedef_deger'], 'ad' => yp_ad($db, $r['hedef_tipi'], $r['hedef_deger']), 'yetkiler' => new stdClass()];
        $grup[$k]['yetkiler']->{$r['yetki']} = intval($r['izin']);
    }
    $sira = ['herkes' => 0, 'rol' => 1, 'gorev' => 2, 'birim' => 3, 'unvan' => 4, 'kullanici' => 5];
    $kurallar = array_values($grup);
    usort($kurallar, function ($a, $b) use ($sira) { return [$sira[$a['tip']] ?? 9, $a['ad']] <=> [$sira[$b['tip']] ?? 9, $b['ad']]; });
    tb_json(['ok' => true, 'baslik' => $tan['baslik'], 'roller' => $roller, 'gorevler' => $gorevler, 'birimler' => $birimler, 'unvanlar' => $unvanlar, 'kisiler' => $kisiler, 'yetkiler' => $yet, 'kurallar' => $kurallar]);
}
if ($islem === 'kontrol') {
    $kid = intval($girdi['kullanici_id'] ?? 0);
    $y = vy_coz($db, $modul, $kid); unset($y['kurucu']);
    tb_json(['ok' => true, 'yetki' => $y]);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') yh('Geçersiz istek.', 405);

$tip = (string)($girdi['hedef_tipi'] ?? ''); $deger = (string)($girdi['hedef_deger'] ?? '');
if (!in_array($tip, ['herkes', 'rol', 'gorev', 'birim', 'unvan', 'kullanici'], true)) yh('Geçersiz hedef.');
if ($tip === 'herkes') $deger = '';
elseif ($deger === '') yh('Hedef seçilmedi.');
if ($tip === 'rol' && !in_array($deger, ['personel', 'yetkili'], true)) yh('Yalnızca “personel” ve “yetkili” rolleri için kural tanımlanabilir (kurum yetkilisi her zaman tam yetkilidir).');
if ($tip === 'kullanici') {
    $q = $db->query("SELECT rol FROM kullanicilar WHERE id = " . intval($deger)); $r = $q ? $q->fetch_row() : null;
    if (!$r) yh('Kullanıcı bulunamadı.');
    if ($r[0] === 'kurucu') yh('Kurum yetkilisi zaten tüm yetkilere sahiptir.');
    $deger = (string)intval($deger);
}
$deger = mb_substr($deger, 0, 150, 'UTF-8');

if ($islem === 'kaydet') {
    $yetki = (string)($girdi['yetki'] ?? '');
    if (!isset($tan['yetkiler'][$yetki])) yh('Bilinmeyen yetki.');
    $izin = (string)($girdi['izin'] ?? '');
    if ($izin === '') {
        $st = $db->prepare("DELETE FROM modul_yetkileri WHERE modul = ? AND yetki = ? AND hedef_tipi = ? AND hedef_deger = ?");
        $st->bind_param('ssss', $modul, $yetki, $tip, $deger); $st->execute();
    } else {
        $i = $izin === '1' ? 1 : 0;
        $st = $db->prepare("INSERT INTO modul_yetkileri (modul, yetki, hedef_tipi, hedef_deger, izin, guncelleyen_id, guncelleme_tarihi) VALUES (?, ?, ?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE izin = VALUES(izin), guncelleyen_id = VALUES(guncelleyen_id), guncelleme_tarihi = NOW()");
        $st->bind_param('ssssii', $modul, $yetki, $tip, $deger, $i, $uid); $st->execute();
    }
    vy_kurallar($db, $modul, true);
    tb_json(['ok' => true]);
}
if ($islem === 'hedef_sil') {
    $st = $db->prepare("DELETE FROM modul_yetkileri WHERE modul = ? AND hedef_tipi = ? AND hedef_deger = ?");
    $st->bind_param('sss', $modul, $tip, $deger); $st->execute();
    tb_json(['ok' => true]);
}
yh('Bilinmeyen işlem.');
