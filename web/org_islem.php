<?php
// ============================================================
// org_islem.php — Personel kartı "Organizasyon" sekmesi: yönetici · astlar · yönettiği birimler
//   GET  org (id)
//   POST org_yonetici (id, yonetici_id) | org_ast_ekle (id, ids JSON) | org_ast_cikar (id, ast_id) | org_birimler (id, birimler JSON)   (CSRF korumalı)
// Görme: kendi kartı ya da personel görme/düzenleme yetkisi · Değiştirme: personel düzenleme yetkisi (ik_duzenle)
// ============================================================
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/ik_ortak.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function orc($v, $k = 200) { http_response_code($k); header('Content-Type: application/json; charset=utf-8'); echo json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR); exit; }
function orh($m, $k = 400) { orc(['ok' => false, 'hata' => $m], $k); }

$uid = intval($_SESSION['kullanici_id'] ?? 0);
if ($uid <= 0) orh('Oturum süresi doldu, lütfen yeniden giriş yapın.', 401);
ik_sema($db);
$Y = vy_coz($db, 'ik', $uid);
if (empty($Y['ik_erisim'])) orh('İnsan Kaynakları sayfasına erişim yetkiniz yok.', 403);
$girdi = $_POST + $_GET;
$islem = (string)($girdi['islem'] ?? '');
$DUZ = !empty($Y['ik_duzenle']);

// ── Şema ──
(function ($db) {
    try { if (ik_ayar($db, 'org_sema_v1') === '1') return; } catch (Throwable $e) {}
    $db->query("CREATE TABLE IF NOT EXISTS ik_birim_yoneticileri (
        birim VARCHAR(100) NOT NULL, kullanici_id INT NOT NULL, ekleyen_id INT NULL, tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (birim, kullanici_id), KEY k_kul (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    ik_ayar_yaz($db, 'org_sema_v1', '1');
})($db);

function or_kisi($r) {
    return ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'gorev' => (string)($r['gorev'] ?? ''), 'birim' => trim((string)($r['departman'] ?? '')), 'renk' => $r['avatar_renk'] ?: '#0ea5e9',
        'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? '')];
}
/** Aktif herkes: id => satır (yonetici_id dâhil) */
function or_herkes($db) {
    $m = [];
    $q = $db->query("SELECT k.id, k.ad_soyad, k.gorev, k.avatar_renk, k.profil_resmi, p.departman, p.yonetici_id FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 ORDER BY k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) $m[intval($r['id'])] = $r;
    return $m;
}
function or_ustler($m, $id) { $z = []; $s = $id; for ($i = 0; $i < 30; $i++) { $y = intval($m[$s]['yonetici_id'] ?? 0); if (!$y || !isset($m[$y]) || in_array($y, $z, true)) break; $z[] = $y; $s = $y; } return $z; }
function or_altlar($m, $id) { $out = []; $kuyruk = [$id]; while ($kuyruk) { $x = array_shift($kuyruk); foreach ($m as $k => $r) if (intval($r['yonetici_id'] ?? 0) === $x && !isset($out[$k]) && $k !== $id) { $out[$k] = true; $kuyruk[] = $k; } } return array_keys($out); }
function or_birim_listesi($db, $m) {
    $l = array_filter(array_map('trim', preg_split('/\R/u', (string)ik_ayar($db, 'departman_listesi', ''))), 'strlen');
    foreach ($m as $r) { $d = trim((string)($r['departman'] ?? '')); if ($d !== '') $l[] = $d; }
    $u = []; foreach ($l as $x) $u[mb_strtolower($x, 'UTF-8')] = $x;
    $out = []; foreach ($u as $x) { $n = 0; foreach ($m as $r) if (mb_strtolower(trim((string)($r['departman'] ?? '')), 'UTF-8') === mb_strtolower($x, 'UTF-8')) $n++; $out[] = ['ad' => $x, 'kisi' => $n]; }
    usort($out, function ($a, $b) { return strcasecmp($a['ad'], $b['ad']); });
    return $out;
}
function or_veri($db, $id, $DUZ) {
    $m = or_herkes($db);
    if (!isset($m[$id])) { $q = $db->query("SELECT k.id, k.ad_soyad, k.gorev, k.avatar_renk, k.profil_resmi, p.departman, p.yonetici_id FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.id = " . intval($id)); $r = $q ? $q->fetch_assoc() : null; if (!$r) orh('Personel bulunamadı.', 404); $m[$id] = $r; }
    $yon = intval($m[$id]['yonetici_id'] ?? 0);
    $ustler = or_ustler($m, $id);
    $zincir = []; foreach (array_reverse($ustler) as $x) $zincir[] = or_kisi($m[$x]);
    $astlar = []; foreach ($m as $k => $r) if (intval($r['yonetici_id'] ?? 0) === $id && $k !== $id) { $a = or_kisi($r); $a['alt_say'] = count(or_altlar($m, $k)); $astlar[] = $a; }
    $tum = or_altlar($m, $id);
    $kardes = []; if ($yon) foreach ($m as $k => $r) if (intval($r['yonetici_id'] ?? 0) === $yon && $k !== $id) $kardes[] = or_kisi($r);
    $bq = $db->query("SELECT birim FROM ik_birim_yoneticileri WHERE kullanici_id = " . intval($id) . " ORDER BY birim");
    $secili = []; if ($bq) while ($b = $bq->fetch_row()) $secili[] = $b[0];
    $birimler = or_birim_listesi($db, $m);
    // birim başına diğer yöneticiler
    $by = []; $yq = $db->query("SELECT y.birim, k.ad_soyad FROM ik_birim_yoneticileri y JOIN kullanicilar k ON k.id = y.kullanici_id WHERE k.aktif = 1 AND y.kullanici_id <> " . intval($id));
    if ($yq) while ($r = $yq->fetch_row()) $by[mb_strtolower($r[0], 'UTF-8')][] = $r[1];
    foreach ($birimler as &$b) $b['diger'] = $by[mb_strtolower($b['ad'], 'UTF-8')] ?? [];
    unset($b);
    $kisiler = []; if ($DUZ) foreach ($m as $k => $r) { $x = or_kisi($r); $x['yonetici_id'] = intval($r['yonetici_id'] ?? 0); $kisiler[] = $x; }
    $ben = $m[$id];
    $ekip_birim = []; foreach ($tum as $t) { $d = trim((string)($m[$t]['departman'] ?? '')) ?: 'Belirtilmemiş'; $ekip_birim[$d] = ($ekip_birim[$d] ?? 0) + 1; }
    arsort($ekip_birim);
    // Üst yöneticiden başlayan tüm alt ağaç (açılır ağaç, ekip duvarı, merdiven görünümleri için)
    $kok = $zincir ? $ustler[count($ustler) - 1] : $id;
    $agac = []; foreach (array_merge([$kok], array_slice(or_altlar($m, $kok), 0, 300)) as $x) if (isset($m[$x])) { $n = or_kisi($m[$x]); $n['yonetici_id'] = intval($m[$x]['yonetici_id'] ?? 0); $agac[] = $n; }
    // Onay yolculuğu: bu kişi izin isterse kimlerden geçer
    $onay = [];
    try { $ak = ik_akis_cozumle($db, $id); foreach ($ak['adimlar'] as $a) if (isset($m[$a['id']])) { $o = or_kisi($m[$a['id']]); $o['vekil'] = $a['vekil_ad'] ?? ''; $onay[] = $o; } } catch (Throwable $e) {}
    // Yetki tablosu (yalnızca düzenleme yetkisi olanlara): yönetici, kişi ve astlar için
    $yetki = null;
    if ($DUZ) {
        $sut = [['ik_personel_gor', 'Kartları görme'], ['ik_duzenle', 'Personel düzenleme'], ['ik_izin_onay', 'İzin onayı'], ['ik_mesai_yonet', 'Mesai yönetimi'], ['ik_maas', 'Maaş bilgisi'], ['ik_rapor_gor', 'Raporlar'], ['kurucu', 'Yönetici']];
        $kisilerY = []; if ($yon && isset($m[$yon])) $kisilerY[] = [$yon, 'Yöneticisi']; $kisilerY[] = [$id, 'Kendisi']; foreach (array_slice($astlar, 0, 8) as $a) $kisilerY[] = [$a['id'], 'Astı'];
        $sat = []; foreach ($kisilerY as $ky) { $yy = vy_coz($db, 'ik', $ky[0]); $v = []; foreach ($sut as $su) $v[] = !empty($yy[$su[0]]) ? 1 : 0; $sat[] = ['kisi' => or_kisi($m[$ky[0]]), 'rol' => $ky[1], 'v' => $v]; }
        $yetki = ['sutunlar' => array_column($sut, 1), 'satirlar' => $sat];
    }
    return ['ok' => true, 'duzenle' => $DUZ, 'agac' => $agac, 'kok_id' => $kok, 'onay' => $onay, 'yetki' => $yetki, 'gorunum' => (string)ik_ayar($db, 'org_gorunum_u' . intval($GLOBALS['uid'] ?? 0), ''), 'kisi' => or_kisi($ben), 'yonetici' => $yon && isset($m[$yon]) ? or_kisi($m[$yon]) : null, 'zincir' => $zincir, 'astlar' => $astlar, 'kardesler' => $kardes,
        'toplam_ekip' => count($tum), 'ekip_birim' => $ekip_birim, 'birimler' => $birimler, 'yonettigi_birimler' => $secili, 'kisiler' => $kisiler];
}
function or_csrf($g) { $gelen = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $g['_csrf'] ?? ''); if (empty($_SESSION['tb_csrf']) || !hash_equals((string)$_SESSION['tb_csrf'], $gelen)) orh('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.', 419); }

$id = intval($girdi['id'] ?? $uid);
if ($islem === 'org') {
    if ($id !== $uid && empty($Y['ik_personel_gor']) && !$DUZ) orh('Bu personelin kartını görme yetkiniz yok.', 403);
    orc(or_veri($db, $id, $DUZ));
}
if ($islem === 'org_gorunum') {
    or_csrf($girdi);
    $g = (string)($girdi['gorunum'] ?? '');
    if (!in_array($g, ['sandvic', 'kartlar', 'akis', 'yatay', 'yorunge', 'yolculuk', 'merdiven', 'birimler', 'acilir', 'matris', 'duvar'], true)) orh('Geçersiz görünüm.');
    ik_ayar_yaz($db, 'org_gorunum_u' . $uid, $g);
    orc(['ok' => true, 'gorunum' => $g]);
}
if (!in_array($islem, ['org_yonetici', 'org_ast_ekle', 'org_ast_cikar', 'org_birimler'], true)) orh('Geçersiz işlem.', 404);
or_csrf($girdi);
if (!$DUZ) orh('Organizasyon bilgisini yalnızca personel düzenleme yetkisi olanlar değiştirebilir.', 403);
$m = or_herkes($db);
if (!isset($m[$id])) orh('Personel aktif değil ya da bulunamadı.', 404);
$yaz = function ($kid, $yon) use ($db, $uid) {
    $kid = intval($kid); $db->query("INSERT IGNORE INTO ik_personel (kullanici_id) VALUES ($kid)");
    $db->query("UPDATE ik_personel SET yonetici_id = " . ($yon ? intval($yon) : 'NULL') . ", guncelleyen_id = " . intval($uid) . ", guncelleme_tarihi = NOW() WHERE kullanici_id = $kid");
};

if ($islem === 'org_yonetici') {
    $y = intval($girdi['yonetici_id'] ?? 0);
    if ($y) {
        if ($y === $id) orh('Kişi kendi yöneticisi olamaz.');
        if (!isset($m[$y])) orh('Seçilen yönetici aktif değil.');
        if (in_array($y, or_altlar($m, $id), true)) orh('Bu kişi zaten ' . $m[$id]['ad_soyad'] . ' kişisinin ekibinde; döngü oluşur.');
    }
    $yaz($id, $y);
}
if ($islem === 'org_ast_ekle') {
    $ids = json_decode((string)($girdi['ids'] ?? '[]'), true); if (!is_array($ids) || !$ids) orh('Kimse seçilmedi.');
    $ust = or_ustler($m, $id); $n = 0;
    foreach (array_unique(array_map('intval', $ids)) as $a) {
        if ($a === $id || !isset($m[$a])) continue;
        if (in_array($a, $ust, true)) orh($m[$a]['ad_soyad'] . ' zaten bu kişinin üst yöneticisi; ast olarak eklenemez.');
        $yaz($a, $id); $n++;
    }
    if (!$n) orh('Eklenecek geçerli kişi yok.');
}
if ($islem === 'org_ast_cikar') {
    $a = intval($girdi['ast_id'] ?? 0);
    if (intval($m[$a]['yonetici_id'] ?? 0) === $id) $yaz($a, 0);
}
if ($islem === 'org_birimler') {
    $l = json_decode((string)($girdi['birimler'] ?? '[]'), true); if (!is_array($l)) $l = [];
    $db->query("DELETE FROM ik_birim_yoneticileri WHERE kullanici_id = " . intval($id));
    $st = $db->prepare("INSERT IGNORE INTO ik_birim_yoneticileri (birim, kullanici_id, ekleyen_id) VALUES (?, ?, ?)");
    foreach (array_slice(array_unique(array_map(function ($x) { return mb_substr(trim((string)$x), 0, 100, 'UTF-8'); }, $l)), 0, 50) as $b) { if ($b === '') continue; $st->bind_param('sii', $b, $id, $uid); $st->execute(); }
}
orc(or_veri($db, $id, true));
