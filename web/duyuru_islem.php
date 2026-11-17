<?php
// ============================================================
// duyuru_islem.php — Duyurular · Dosya paylaşımı · Anketler JSON uç noktası
//   GET  ozet | hedef_secenek | duyurular | duyuru | dosyalar | dosya_indir | dosya_indirenler | anketler | anket | anket_disa
//   POST duyuru_kaydet | duyuru_durum | duyuru_oku | duyuru_sil
//        dosya_yukle | dosya_guncelle | dosya_sil
//        anket_kaydet | anket_durum | anket_cevapla | anket_sil          (hepsi CSRF korumalı)
// Yayınlama yetkisi: "ik_duyuru_yonet" (Yetkiler panelinden verilir). Görme: hedef kitlede olmak.
// ============================================================
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/duyuru_ortak.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function dyc($v, $k = 200) { if ($k === 200 && !empty($GLOBALS['IK_HLOG'])) { $L = $GLOBALS['IK_HLOG']; $GLOBALS['IK_HLOG'] = null; ik_ek_harici_yaz($L[0], $L[1], $L[2], $v); } $o = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); http_response_code($k); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('Content-Length: ' . strlen($o)); echo $o; exit; }
function dyh($m, $k = 400) { dyc(['ok' => false, 'hata' => $m], $k); }

$uid = intval($_SESSION['kullanici_id'] ?? 0);
if ($uid <= 0) dyh('Oturum süresi doldu, lütfen yeniden giriş yapın.', 401);
ik_sema($db); dy_sema($db);
$Y = vy_coz($db, 'ik', $uid);
if (empty($Y['ik_erisim'])) dyh('İnsan Kaynakları sayfasına erişim yetkiniz yok.', 403);
if (array_key_exists('ik_menu_duyuru', $Y) && empty($Y['ik_menu_duyuru'])) dyh('Bu bölümü görme yetkiniz yok.', 403);
$YON = !empty($Y['ik_duyuru_yonet']);
$girdi = $_POST + $_GET;
$islem = (string)($_GET['islem'] ?? $girdi['islem'] ?? '');
try { require_once __DIR__ . '/platform_paket.php'; pf_paket_sema($db); $PKL = pf_paket_kilitli($db); } catch (Throwable $e) { $PKL = []; }
if ((strpos($islem, 'dosya') === 0 && in_array('dosyalar', $PKL, true)) || (strpos($islem, 'anket') === 0 && in_array('anketler', $PKL, true))) dyh('Bu özellik kurumunuzun paketinde yok veya süresi bitti.', 402);
$DOSYA_KLASOR = tb_yukleme_dizin('ik_dosyalar');
if ($_SERVER['REQUEST_METHOD'] === 'POST') { try { require_once __DIR__ . '/ik_ek.php'; $GLOBALS['IK_HLOG'] = [$db, $uid, ik_ek_harici_hazirla($db, $islem, $girdi)]; } catch (Throwable $e) {} }
$BIRIM = dy_kullanici_birim($db, $uid);
$ben = dy_kullanicilar($db)[$uid] ?? ['ad' => (string)($_SESSION['ad_soyad'] ?? '')];

function dy_csrf($g)
{
    $gelen = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $g['_csrf'] ?? '');
    if (empty($_SESSION['tb_csrf']) || !hash_equals((string)$_SESSION['tb_csrf'], $gelen)) dyh('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.', 419);
}
function dy_yonet_sart() { global $YON; if (!$YON) dyh('Bu işlem için duyuru / paylaşım yetkiniz yok.', 403); }
function dy_tarih($v) { $v = trim((string)$v); return $v !== '' && strtotime($v) ? date('Y-m-d', strtotime($v)) : null; }
function dy_tarihsaat($v) { $v = trim((string)$v); return $v !== '' && strtotime($v) ? date('Y-m-d H:i:s', strtotime($v)) : null; }
function dy_e($db, $s) { return $db->real_escape_string((string)$s); }
/** Yüklenen tek dosyayı doğrular ve klasöre taşır → [saklanan_ad, orijinal, mime, boyut] */
function dy_dosya_kaydet($f, $on)
{
    global $DOSYA_KLASOR;
    if (!isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) dyh('Dosya yüklenemedi (en fazla 25 MB).');
    if ($f['size'] > DY_DOSYA_LIMIT) dyh('Dosya en fazla 25 MB olabilir.');
    $uz = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!isset(DY_UZANTI[$uz])) dyh('Bu dosya türü desteklenmiyor. PDF, görsel, Word, Excel, PowerPoint, metin ya da ZIP yükleyebilirsiniz.');
    if (!tb_dosya_icerik_uygun($f['tmp_name'], $uz)) dyh('Dosya içeriği uzantısıyla uyuşmuyor; yükleme reddedildi.');
    $ad = $on . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $uz;
    if (!move_uploaded_file($f['tmp_name'], $DOSYA_KLASOR . '/' . $ad)) dyh('Dosya kaydedilemedi (klasör yazılabilir değil).', 500);
    return [$ad, mb_substr(basename($f['name']), 0, 255, 'UTF-8'), DY_UZANTI[$uz], intval($f['size'])];
}
/** $_FILES['x'] (tekli ya da çoklu) → dosya dizisi listesi */
function dy_dosya_listesi($anahtar)
{
    $o = [];
    if (empty($_FILES[$anahtar])) return $o;
    $f = $_FILES[$anahtar];
    if (is_array($f['name'])) { foreach ($f['name'] as $i => $n) { if ($f['error'][$i] === UPLOAD_ERR_NO_FILE) continue; $o[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]]; } }
    elseif ($f['error'] !== UPLOAD_ERR_NO_FILE) $o[] = $f;
    return $o;
}
function dy_dosya_sil_fiziksel($db, $where)
{
    global $DOSYA_KLASOR;
    $q = $db->query("SELECT id, dosya FROM ik_dosyalar WHERE $where");
    $ids = [];
    if ($q) while ($r = $q->fetch_row()) { @unlink($DOSYA_KLASOR . '/' . basename($r[1])); $ids[] = intval($r[0]); }
    if ($ids) { $db->query("DELETE FROM ik_dosya_indirme WHERE dosya_id IN (" . implode(',', $ids) . ")"); $db->query("DELETE FROM ik_dosyalar WHERE id IN (" . implode(',', $ids) . ")"); }
}
function dy_zaman_yaz($z) { return $z ? date('c', strtotime($z)) : null; }

/** Duyuru görünür mü? (yönetici hep; diğerleri: yayında + süresi dolmamış + hedefte) */
function dy_duyuru_gorunur($d)
{
    global $YON, $uid, $BIRIM;
    if ($YON || intval($d['olusturan_id']) === $uid) return true;
    if ($d['durum'] !== 'yayinda') return false;
    if (!empty($d['bitis']) && $d['bitis'] < date('Y-m-d')) return false;
    return dy_hedefte($d, $uid, $BIRIM);
}
function dy_anket_etkin_durum($a) { return ($a['durum'] === 'acik' && !empty($a['bitis']) && strtotime($a['bitis']) < time()) ? 'kapali' : $a['durum']; }
function dy_anket_gorunur($a)
{
    global $YON, $uid, $BIRIM;
    if ($YON || intval($a['olusturan_id']) === $uid) return true;
    if ($a['durum'] === 'taslak') return false;
    return dy_hedefte($a, $uid, $BIRIM);
}
/** Dosya görünür mü / indirilebilir mi → [gorunur, indirebilir] */
function dy_dosya_erisim($db, $f)
{
    global $YON, $uid, $BIRIM;
    if ($YON || intval($f['yukleyen_id']) === $uid) return [true, true];
    if (!empty($f['duyuru_id'])) {
        $d = $db->query("SELECT * FROM ik_duyurular WHERE id = " . intval($f['duyuru_id']))->fetch_assoc();
        $g = $d && dy_duyuru_gorunur($d);
        return [$g, $g];
    }
    if (!dy_hedefte($f, $uid, $BIRIM)) return [false, false];
    if (($f['indirme_tip'] ?? 'gorenler') !== 'secili') return [true, true];
    return [true, in_array($uid, array_map('intval', dy_json_dizi($f['indirme_kisi'] ?? '')), true)];
}

/* ====================================================== OKUMA ====================================================== */
if ($islem === 'ozet') {
    $dy = 0; $an = 0;
    $q = $db->query("SELECT d.* FROM ik_duyurular d LEFT JOIN ik_okumalar o ON o.tip = 'duyuru' AND o.oge_id = d.id AND o.kullanici_id = $uid WHERE d.durum = 'yayinda' AND o.oge_id IS NULL AND (d.bitis IS NULL OR d.bitis >= CURDATE()) AND d.olusturan_id <> $uid");
    if ($q) while ($r = $q->fetch_assoc()) if (dy_hedefte($r, $uid, $BIRIM)) $dy++;
    $q = $db->query("SELECT a.* FROM ik_anketler a LEFT JOIN ik_anket_katilim k ON k.anket_id = a.id AND k.kullanici_id = $uid WHERE a.durum = 'acik' AND k.anket_id IS NULL AND (a.bitis IS NULL OR a.bitis > NOW()) AND a.olusturan_id <> $uid");
    if ($q) while ($r = $q->fetch_assoc()) if (dy_hedefte($r, $uid, $BIRIM)) $an++;
    dyc(['ok' => true, 'duyuru' => $dy, 'anket' => $an, 'yonetici' => $YON]);
}

/* ---------- sabitlenmiş duyurular (her sayfanın üstünde görünür) ---------- */
if ($islem === 'sabitler') {
    $q = $db->query("SELECT d.id, d.baslik, d.icerik, d.tur, d.olusturan_id, d.durum, d.bitis, d.hedef_tip, d.hedef_birim, d.hedef_kisi, d.yayin, d.olusturma, k.ad_soyad yazar, o.zaman okundu FROM ik_duyurular d LEFT JOIN kullanicilar k ON k.id = d.olusturan_id
        LEFT JOIN ik_okumalar o ON o.tip = 'duyuru' AND o.oge_id = d.id AND o.kullanici_id = $uid WHERE d.sabit = 1 AND d.durum = 'yayinda' AND (d.bitis IS NULL OR d.bitis >= CURDATE()) ORDER BY COALESCE(d.yayin, d.olusturma) DESC, d.id DESC LIMIT 20");
    $liste = []; $okuyan = [];
    if ($YON) { $oq = $db->query("SELECT oge_id, COUNT(*) FROM ik_okumalar WHERE tip = 'duyuru' GROUP BY oge_id"); if ($oq) while ($r = $oq->fetch_row()) $okuyan[intval($r[0])] = intval($r[1]); }
    if ($q) while ($d = $q->fetch_assoc()) {
        if (!dy_hedefte($d, $uid, $BIRIM) && !$YON && intval($d['olusturan_id']) !== $uid) continue;
        $o = ['id' => intval($d['id']), 'baslik' => $d['baslik'], 'tam' => mb_substr(trim((string)$d['icerik']), 0, 1500, 'UTF-8'), 'tur' => $d['tur'], 'yazar' => (string)$d['yazar'], 'okundu' => !empty($d['okundu']) || intval($d['olusturan_id']) === $uid, 'yonet' => $YON];
        if ($YON) { $o['alici'] = max(0, count(dy_alicilar($db, $d)) - 1); $o['okuyan'] = $okuyan[$o['id']] ?? 0; }
        $liste[] = $o; if (count($liste) >= 5) break;
    }
    dyc(['ok' => true, 'liste' => $liste]);
}

if ($islem === 'hedef_secenek') {
    dy_yonet_sart();
    $birim = []; $kisi = [];
    foreach (dy_kullanicilar($db) as $k) { $birim[$k['birim']] = ($birim[$k['birim']] ?? 0) + 1; $kisi[] = ['id' => $k['id'], 'ad' => $k['ad'], 'birim' => $k['birim'], 'gorev' => $k['gorev'], 'mail' => filter_var($k['email'], FILTER_VALIDATE_EMAIL) !== false, 'tel' => ik_telefon_norm($k['telefon']) !== '', 'renk' => $k['renk'], 'resim' => $k['resim']]; }
    ksort($birim, SORT_NATURAL | SORT_FLAG_CASE);
    $b = []; foreach ($birim as $ad => $n) $b[] = ['ad' => $ad, 'n' => $n];
    $klasor = []; $q = $db->query("SELECT DISTINCT klasor FROM ik_dosyalar WHERE klasor <> '' AND duyuru_id IS NULL ORDER BY klasor"); if ($q) while ($r = $q->fetch_row()) $klasor[] = $r[0];
    dyc(['ok' => true, 'birimler' => $b, 'kisiler' => $kisi, 'klasorler' => $klasor, 'mail_hazir' => ik_mail_hazir($db), 'sms_hazir' => ik_sms_hazir($db)]);
}

/* ---------- duyurular ---------- */
if ($islem === 'duyurular') {
    $durum = in_array($girdi['durum'] ?? '', ['yayinda', 'taslak', 'arsiv'], true) ? $girdi['durum'] : 'yayinda';
    if ($durum !== 'yayinda' && !$YON) $durum = 'yayinda';
    $q = $db->query("SELECT d.*, k.ad_soyad yazar, (SELECT COUNT(*) FROM ik_dosyalar f WHERE f.duyuru_id = d.id) ek_say, o.zaman okundu, o.onay onayladi FROM ik_duyurular d
        LEFT JOIN kullanicilar k ON k.id = d.olusturan_id LEFT JOIN ik_okumalar o ON o.tip = 'duyuru' AND o.oge_id = d.id AND o.kullanici_id = $uid
        WHERE d.durum = '$durum' ORDER BY d.sabit DESC, COALESCE(d.yayin, d.olusturma) DESC, d.id DESC LIMIT 400");
    $liste = [];
    $okuyan = [];
    if ($YON) { $oq = $db->query("SELECT oge_id, COUNT(*) n, SUM(onay) o FROM ik_okumalar WHERE tip = 'duyuru' GROUP BY oge_id"); if ($oq) while ($r = $oq->fetch_row()) $okuyan[intval($r[0])] = [intval($r[1]), intval($r[2])]; }
    if ($q) while ($d = $q->fetch_assoc()) {
        if (!dy_duyuru_gorunur($d)) continue;
        $o = ['id' => intval($d['id']), 'baslik' => $d['baslik'], 'ozet' => dy_ozet_metin($d['icerik']), 'tam' => mb_substr(trim((string)$d['icerik']), 0, 3000, 'UTF-8'), 'tur' => $d['tur'], 'sabit' => (bool)$d['sabit'], 'durum' => $d['durum'], 'yazar' => (string)$d['yazar'], 'yazar_id' => intval($d['olusturan_id']),
            'zaman' => dy_zaman_yaz($d['yayin'] ?: $d['olusturma']), 'bitis' => $d['bitis'], 'hedef' => dy_hedef_ozet($d), 'ek_say' => intval($d['ek_say']), 'okundu' => !empty($d['okundu']) || intval($d['olusturan_id']) === $uid,
            'onay_iste' => (bool)$d['onay_iste'], 'onayladi' => (bool)$d['onayladi']];
        if ($YON) { $o['alici'] = count(dy_alicilar($db, $d)); $o['okuyan'] = $okuyan[$o['id']][0] ?? 0; $o['onaylayan'] = $okuyan[$o['id']][1] ?? 0; }
        $liste[] = $o;
    }
    dyc(['ok' => true, 'liste' => $liste, 'yonetici' => $YON, 'durum' => $durum]);
}

if ($islem === 'duyuru') {
    $id = intval($girdi['id'] ?? 0);
    $d = $db->query("SELECT d.*, k.ad_soyad yazar FROM ik_duyurular d LEFT JOIN kullanicilar k ON k.id = d.olusturan_id WHERE d.id = $id")->fetch_assoc();
    if (!$d || !dy_duyuru_gorunur($d)) dyh('Duyuru bulunamadı ya da görme yetkiniz yok.', 404);
    $ekler = [];
    $q = $db->query("SELECT id, orijinal_ad, mime, boyut FROM ik_dosyalar WHERE duyuru_id = $id ORDER BY id");
    if ($q) while ($r = $q->fetch_assoc()) $ekler[] = ['id' => intval($r['id']), 'ad' => $r['orijinal_ad'], 'mime' => $r['mime'], 'boyut' => intval($r['boyut'])];
    $ben_o = $db->query("SELECT zaman, onay FROM ik_okumalar WHERE tip = 'duyuru' AND oge_id = $id AND kullanici_id = $uid")->fetch_assoc();
    $o = ['id' => $id, 'baslik' => $d['baslik'], 'icerik' => (string)$d['icerik'], 'tur' => $d['tur'], 'sabit' => (bool)$d['sabit'], 'durum' => $d['durum'], 'yazar' => (string)$d['yazar'], 'yazar_id' => intval($d['olusturan_id']),
        'zaman' => dy_zaman_yaz($d['yayin'] ?: $d['olusturma']), 'bitis' => $d['bitis'], 'hedef' => dy_hedef_ozet($d), 'onay_iste' => (bool)$d['onay_iste'], 'onayladi' => !empty($ben_o['onay']), 'ekler' => $ekler];
    if ($YON || intval($d['olusturan_id']) === $uid) {
        $o['hedef_tip'] = $d['hedef_tip']; $o['hedef_birim'] = dy_json_dizi($d['hedef_birim']); $o['hedef_kisi'] = array_map('intval', dy_json_dizi($d['hedef_kisi']));
        $ok = []; $q = $db->query("SELECT kullanici_id, zaman, onay FROM ik_okumalar WHERE tip = 'duyuru' AND oge_id = $id"); if ($q) while ($r = $q->fetch_assoc()) $ok[intval($r['kullanici_id'])] = $r;
        $kul = dy_kullanicilar($db); $okuyanlar = []; $okumayanlar = [];
        foreach (dy_alicilar($db, $d) as $a) {
            if ($a === intval($d['olusturan_id'])) continue;
            if (isset($ok[$a])) $okuyanlar[] = ['id' => $a, 'ad' => $kul[$a]['ad'], 'birim' => $kul[$a]['birim'], 'zaman' => dy_zaman_yaz($ok[$a]['zaman']), 'onay' => (bool)$ok[$a]['onay']];
            else $okumayanlar[] = ['id' => $a, 'ad' => $kul[$a]['ad'], 'birim' => $kul[$a]['birim']];
        }
        usort($okuyanlar, function ($x, $y) { return strcmp($y['zaman'], $x['zaman']); });
        $o['okuyanlar'] = $okuyanlar; $o['okumayanlar'] = $okumayanlar;
    }
    dyc(['ok' => true, 'duyuru' => $o]);
}

/* ---------- dosyalar ---------- */
if ($islem === 'dosyalar') {
    $q = $db->query("SELECT f.*, k.ad_soyad yukleyen FROM ik_dosyalar f LEFT JOIN kullanicilar k ON k.id = f.yukleyen_id WHERE f.duyuru_id IS NULL ORDER BY f.klasor, f.tarih DESC, f.id DESC LIMIT 600");
    $liste = []; $kul = dy_kullanicilar($db);
    if ($q) while ($f = $q->fetch_assoc()) {
        list($gor, $ind) = dy_dosya_erisim($db, $f);
        if (!$gor) continue;
        $o = ['id' => intval($f['id']), 'ad' => $f['ad'], 'aciklama' => $f['aciklama'], 'klasor' => $f['klasor'], 'orijinal' => $f['orijinal_ad'], 'mime' => $f['mime'], 'boyut' => intval($f['boyut']), 'tarih' => dy_zaman_yaz($f['tarih']),
            'yukleyen' => (string)$f['yukleyen'], 'indirebilir' => $ind, 'kisitli' => ($f['indirme_tip'] ?? '') === 'secili', 'hedef' => dy_hedef_ozet($f), 'indirme_say' => intval($f['indirme_say'])];
        if ($YON) { $o['hedef_tip'] = $f['hedef_tip']; $o['hedef_birim'] = dy_json_dizi($f['hedef_birim']); $o['hedef_kisi'] = array_map('intval', dy_json_dizi($f['hedef_kisi'])); $o['indirme_tip'] = $f['indirme_tip']; $o['indirme_kisi'] = array_map('intval', dy_json_dizi($f['indirme_kisi'])); }
        $liste[] = $o;
    }
    dyc(['ok' => true, 'liste' => $liste, 'yonetici' => $YON]);
}

if ($islem === 'dosya_indir') {
    $id = intval($girdi['id'] ?? 0);
    $f = $db->query("SELECT * FROM ik_dosyalar WHERE id = $id")->fetch_assoc();
    if (!$f) { http_response_code(404); exit('Dosya bulunamadı'); }
    list($gor, $ind) = dy_dosya_erisim($db, $f);
    if (!$gor || !$ind) { http_response_code(403); exit('Bu dosyayı indirme yetkiniz yok'); }
    $yol = $DOSYA_KLASOR . '/' . basename($f['dosya']);
    if (!is_file($yol)) { http_response_code(404); exit('Dosya sunucuda yok'); }
    if (intval($f['yukleyen_id']) !== $uid) {
        $db->query("UPDATE ik_dosyalar SET indirme_say = indirme_say + 1 WHERE id = $id");
        $db->query("INSERT INTO ik_dosya_indirme (dosya_id, kullanici_id, zaman) VALUES ($id, $uid, NOW())");
    }
    $inline = !empty($girdi['goster']) && preg_match('#^(image/(jpeg|png|webp|gif)|application/pdf)$#', $f['mime']);
    header('Content-Type: ' . ($f['mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($yol));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename*=UTF-8\'\'' . rawurlencode($f['orijinal_ad'] ?: 'dosya'));
    readfile($yol); exit;
}

if ($islem === 'dosya_indirenler') {
    dy_yonet_sart();
    $id = intval($girdi['id'] ?? 0); $kul = dy_kullanicilar($db); $l = [];
    $q = $db->query("SELECT kullanici_id, COUNT(*) n, MAX(zaman) son FROM ik_dosya_indirme WHERE dosya_id = $id GROUP BY kullanici_id ORDER BY son DESC");
    if ($q) while ($r = $q->fetch_assoc()) $l[] = ['ad' => $kul[intval($r['kullanici_id'])]['ad'] ?? ('#' . $r['kullanici_id']), 'birim' => $kul[intval($r['kullanici_id'])]['birim'] ?? '', 'n' => intval($r['n']), 'son' => dy_zaman_yaz($r['son'])];
    dyc(['ok' => true, 'liste' => $l]);
}

/* ---------- anketler ---------- */
function dy_anket_satir($db, $a, $ozet = true)
{
    global $uid, $YON;
    $id = intval($a['id']);
    $katildi = $db->query("SELECT 1 FROM ik_anket_katilim WHERE anket_id = $id AND kullanici_id = $uid")->num_rows > 0;
    $kat = intval($db->query("SELECT COUNT(*) FROM ik_anket_katilim WHERE anket_id = $id")->fetch_row()[0]);
    $o = ['id' => $id, 'baslik' => $a['baslik'], 'aciklama' => (string)$a['aciklama'], 'durum' => dy_anket_etkin_durum($a), 'bitis' => $a['bitis'] ? date('c', strtotime($a['bitis'])) : null, 'anonim' => (bool)$a['anonim'], 'sonuc_goster' => $a['sonuc_goster'],
        'katildi' => $katildi, 'katilan' => $kat, 'hedef' => dy_hedef_ozet($a), 'yazar_id' => intval($a['olusturan_id']), 'yazar' => (string)($a['yazar'] ?? ''),
        'soru_say' => intval($db->query("SELECT COUNT(*) FROM ik_anket_sorular WHERE anket_id = $id")->fetch_row()[0]), 'zaman' => dy_zaman_yaz($a['olusturma'])];
    if ($YON) $o['alici'] = count(dy_alicilar($db, $a));
    return $o;
}
if ($islem === 'anketler') {
    $q = $db->query("SELECT a.*, k.ad_soyad yazar FROM ik_anketler a LEFT JOIN kullanicilar k ON k.id = a.olusturan_id ORDER BY FIELD(a.durum,'acik','taslak','kapali'), a.id DESC LIMIT 300");
    $liste = [];
    if ($q) while ($a = $q->fetch_assoc()) { if (!dy_anket_gorunur($a)) continue; $liste[] = dy_anket_satir($db, $a); }
    dyc(['ok' => true, 'liste' => $liste, 'yonetici' => $YON]);
}

/** Anket sonuçları (yetkiye göre) */
function dy_anket_sonuc($db, $a, $tam)
{
    $id = intval($a['id']); $sonuc = [];
    $q = $db->query("SELECT * FROM ik_anket_sorular WHERE anket_id = $id ORDER BY sira, id");
    $kul = dy_kullanicilar($db);
    if ($q) while ($s = $q->fetch_assoc()) {
        $sid = intval($s['id']); $sec = dy_json_dizi($s['secenekler']);
        $r = ['id' => $sid, 'say' => intval($db->query("SELECT COUNT(DISTINCT yanit_no) FROM ik_anket_cevaplar WHERE soru_id = $sid")->fetch_row()[0])];
        if ($s['tip'] === 'tek' || $s['tip'] === 'coklu') {
            $n = array_fill(0, count($sec), 0);
            $c = $db->query("SELECT deger, COUNT(*) FROM ik_anket_cevaplar WHERE soru_id = $sid GROUP BY deger"); if ($c) while ($x = $c->fetch_row()) { $i = intval($x[0]); if (isset($n[$i])) $n[$i] += intval($x[1]); }
            $r['sayilar'] = $n;
        } elseif ($s['tip'] === 'puan') {
            $n = [0, 0, 0, 0, 0];
            $c = $db->query("SELECT deger, COUNT(*) FROM ik_anket_cevaplar WHERE soru_id = $sid GROUP BY deger"); if ($c) while ($x = $c->fetch_row()) { $i = intval($x[0]); if ($i >= 1 && $i <= 5) $n[$i - 1] += intval($x[1]); }
            $top = array_sum($n); $r['sayilar'] = $n; $r['ortalama'] = $top ? round(($n[0] + 2 * $n[1] + 3 * $n[2] + 4 * $n[3] + 5 * $n[4]) / $top, 2) : null;
        } elseif ($tam) {
            $m = []; $c = $db->query("SELECT kullanici_id, deger FROM ik_anket_cevaplar WHERE soru_id = $sid AND deger <> '' ORDER BY id DESC LIMIT 200");
            if ($c) while ($x = $c->fetch_row()) $m[] = ['ad' => $x[0] ? ($kul[intval($x[0])]['ad'] ?? '') : '', 'deger' => $x[1]];
            $r['metinler'] = $m;
        }
        $sonuc[] = $r;
    }
    return $sonuc;
}
if ($islem === 'anket') {
    $id = intval($girdi['id'] ?? 0);
    $a = $db->query("SELECT a.*, k.ad_soyad yazar FROM ik_anketler a LEFT JOIN kullanicilar k ON k.id = a.olusturan_id WHERE a.id = $id")->fetch_assoc();
    if (!$a || !dy_anket_gorunur($a)) dyh('Anket bulunamadı ya da görme yetkiniz yok.', 404);
    $o = dy_anket_satir($db, $a);
    $sorular = []; $q = $db->query("SELECT * FROM ik_anket_sorular WHERE anket_id = $id ORDER BY sira, id");
    if ($q) while ($s = $q->fetch_assoc()) $sorular[] = ['id' => intval($s['id']), 'tip' => $s['tip'], 'soru' => $s['soru'], 'secenekler' => dy_json_dizi($s['secenekler']), 'zorunlu' => (bool)$s['zorunlu']];
    $o['sorular'] = $sorular;
    $hedefte = dy_hedefte($a, $uid, $BIRIM);
    $o['yanitlayabilir'] = $hedefte && $o['durum'] === 'acik' && !$o['katildi'];
    $yapan = $YON || intval($a['olusturan_id']) === $uid;
    $goster = $yapan || ($hedefte && ($a['sonuc_goster'] === 'herkes' || ($a['sonuc_goster'] === 'katilan' && $o['katildi'])));
    $o['sonuc_gorunur'] = $goster;
    if ($goster) $o['sonuclar'] = dy_anket_sonuc($db, $a, $yapan);
    if ($yapan) {
        $o['hedef_tip'] = $a['hedef_tip']; $o['hedef_birim'] = dy_json_dizi($a['hedef_birim']); $o['hedef_kisi'] = array_map('intval', dy_json_dizi($a['hedef_kisi']));
        $kat = []; $q = $db->query("SELECT kullanici_id, zaman FROM ik_anket_katilim WHERE anket_id = $id"); if ($q) while ($r = $q->fetch_assoc()) $kat[intval($r['kullanici_id'])] = $r['zaman'];
        $kul = dy_kullanicilar($db); $katilanlar = []; $katilmayanlar = [];
        foreach (dy_alicilar($db, $a) as $x) { if (isset($kat[$x])) $katilanlar[] = ['ad' => $kul[$x]['ad'], 'birim' => $kul[$x]['birim'], 'zaman' => dy_zaman_yaz($kat[$x])]; else $katilmayanlar[] = ['ad' => $kul[$x]['ad'], 'birim' => $kul[$x]['birim']]; }
        $o['katilanlar'] = $katilanlar; $o['katilmayanlar'] = $katilmayanlar; $o['alici'] = count($katilanlar) + count($katilmayanlar);
        $o['cevap_var'] = $o['katilan'] > 0;
    }
    dyc(['ok' => true, 'anket' => $o]);
}
if ($islem === 'anket_disa') {                       // CSV için satırlar (yönetici)
    $id = intval($girdi['id'] ?? 0);
    $a = $db->query("SELECT * FROM ik_anketler WHERE id = $id")->fetch_assoc();
    if (!$a || !($YON || intval($a['olusturan_id']) === $uid)) dyh('Yetkiniz yok.', 403);
    $sor = []; $q = $db->query("SELECT * FROM ik_anket_sorular WHERE anket_id = $id ORDER BY sira, id"); if ($q) while ($s = $q->fetch_assoc()) $sor[] = $s;
    $kul = dy_kullanicilar($db); $yan = [];
    $q = $db->query("SELECT soru_id, yanit_no, kullanici_id, deger, zaman FROM ik_anket_cevaplar WHERE anket_id = $id ORDER BY yanit_no, id");
    if ($q) while ($r = $q->fetch_assoc()) { $y = &$yan[$r['yanit_no']]; $y['ad'] = $r['kullanici_id'] ? ($kul[intval($r['kullanici_id'])]['ad'] ?? '') : ''; $y['zaman'] = $r['zaman']; $y['c'][intval($r['soru_id'])][] = $r['deger']; unset($y); }
    $baslik = array_merge([$a['anonim'] ? 'Katılımcı' : 'Ad soyad', 'Tarih'], array_map(function ($s) { return $s['soru']; }, $sor)); $satir = [];
    foreach ($yan as $y) {
        $r = [$a['anonim'] ? '(anonim)' : $y['ad'], date('d.m.Y H:i', strtotime($y['zaman']))];
        foreach ($sor as $s) {
            $v = $y['c'][intval($s['id'])] ?? []; $sec = dy_json_dizi($s['secenekler']);
            if ($s['tip'] === 'tek' || $s['tip'] === 'coklu') $v = array_map(function ($i) use ($sec) { return $sec[intval($i)] ?? $i; }, $v);
            $r[] = implode(', ', $v);
        }
        $satir[] = $r;
    }
    dyc(['ok' => true, 'baslik' => $baslik, 'satirlar' => $satir, 'ad' => $a['baslik']]);
}

/* ====================================================== YAZMA ====================================================== */
dy_csrf($girdi);

/* ---------- duyuru ---------- */
if ($islem === 'duyuru_kaydet') {
    dy_yonet_sart();
    $id = intval($girdi['id'] ?? 0);
    $baslik = mb_substr(trim((string)($girdi['baslik'] ?? '')), 0, 200, 'UTF-8'); if ($baslik === '') dyh('Başlık yazın.');
    $icerik = mb_substr(trim((string)($girdi['icerik'] ?? '')), 0, 20000, 'UTF-8');
    $tur = in_array($girdi['tur'] ?? '', DY_TURLER, true) ? $girdi['tur'] : 'genel';
    $sabit = !empty($girdi['sabit']) ? 1 : 0; $onay = !empty($girdi['onay_iste']) ? 1 : 0;
    $bitis = dy_tarih($girdi['bitis'] ?? '');
    $durum = ($girdi['durum'] ?? 'yayinda') === 'taslak' ? 'taslak' : 'yayinda';
    $h = dy_hedef_oku($girdi);
    $eski = $id ? $db->query("SELECT * FROM ik_duyurular WHERE id = $id")->fetch_assoc() : null;
    if ($id && !$eski) dyh('Duyuru bulunamadı.', 404);
    $yeniYayin = $durum === 'yayinda' && (!$eski || $eski['durum'] !== 'yayinda');
    if ($eski && $eski['durum'] === 'arsiv' && $durum === 'yayinda') $yeniYayin = false;
    $adet = count(dy_dosya_listesi('dosyalar'));
    $mevcutEk = $id ? intval($db->query("SELECT COUNT(*) FROM ik_dosyalar WHERE duyuru_id = $id")->fetch_row()[0]) : 0;
    if ($mevcutEk + $adet > 15) dyh('Bir duyuruya en fazla 15 dosya eklenebilir.');
    if (!$id) {
        $st = $db->prepare("INSERT INTO ik_duyurular (baslik, icerik, tur, sabit, hedef_tip, hedef_birim, hedef_kisi, durum, onay_iste, bitis, olusturan_id, olusturma, yayin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), " . ($durum === 'yayinda' ? 'NOW()' : 'NULL') . ")");
        $st->bind_param('sssissssisi', $baslik, $icerik, $tur, $sabit, $h['tip'], $h['birim'], $h['kisi'], $durum, $onay, $bitis, $uid);
        if (!$st->execute()) dyh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
        $id = intval($db->son_id());
    } else {
        $dz = $durum === 'taslak' ? ($eski['durum'] === 'arsiv' ? 'arsiv' : 'taslak') : ($eski['durum'] === 'arsiv' ? 'arsiv' : 'yayinda');
        $st = $db->prepare("UPDATE ik_duyurular SET baslik = ?, icerik = ?, tur = ?, sabit = ?, hedef_tip = ?, hedef_birim = ?, hedef_kisi = ?, durum = ?, onay_iste = ?, bitis = ?, guncelleme = NOW()" . ($yeniYayin ? ', yayin = NOW()' : '') . " WHERE id = ?");
        $st->bind_param('sssissssisi', $baslik, $icerik, $tur, $sabit, $h['tip'], $h['birim'], $h['kisi'], $dz, $onay, $bitis, $id);
        if (!$st->execute()) dyh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
    }
    // silinen ekler
    foreach (dy_json_dizi($girdi['ek_sil'] ?? '') as $e) dy_dosya_sil_fiziksel($db, "id = " . intval($e) . " AND duyuru_id = $id");
    // yeni ekler
    foreach (dy_dosya_listesi('dosyalar') as $f) {
        list($ad, $orj, $mime, $boyut) = dy_dosya_kaydet($f, 'd' . $id);
        $st = $db->prepare("INSERT INTO ik_dosyalar (ad, dosya, orijinal_ad, mime, boyut, duyuru_id, yukleyen_id, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $st->bind_param('ssssiii', $orj, $ad, $orj, $mime, $boyut, $id, $uid); $st->execute();
    }
    $bildirilen = 0;
    if ($durum === 'yayinda' && ($yeniYayin || !empty($girdi['yeniden_bildir']))) {
        $kanal = dy_kanal($girdi);
        $d = $db->query("SELECT * FROM ik_duyurular WHERE id = $id")->fetch_assoc();
        $ekSay = intval($db->query("SELECT COUNT(*) FROM ik_dosyalar WHERE duyuru_id = $id")->fetch_row()[0]);
        $onek = ['acil' => '🚨', 'onemli' => '⚠️', 'etkinlik' => '🎉', 'bilgi' => 'ℹ️'][$tur] ?? '📢';
        $govde = nl2br(ik_h(dy_ozet_metin($icerik, 600)));
        $bildirilen = dy_bildir($db, dy_alicilar($db, $d), $uid, $kanal, [
            'baslik' => $onek . ' ' . $baslik, 'html' => '<b>' . ik_h($ben['ad'] ?? '') . '</b> yeni bir duyuru yayınladı:<br><br>' . $govde . ($ekSay ? '<br><br>📎 ' . $ekSay . ' ek dosya' : '') . ($onay ? '<br><br><b>Lütfen okuyup “Okudum, anladım” ile onaylayın.</b>' : ''),
            'sms' => 'Duyuru: ' . $baslik . ' —', 'buton' => 'Duyuruyu oku', 'link' => 'ik.php?sekme=duyurular&id=' . $id, 'tablo' => 'ik_duyuru', 'bid' => $id]);
    }
    dyc(['ok' => true, 'id' => $id, 'bildirilen' => $bildirilen]);
}
if ($islem === 'duyuru_durum') {
    dy_yonet_sart();
    $id = intval($girdi['id'] ?? 0);
    if (!$db->query("SELECT 1 FROM ik_duyurular WHERE id = $id")->num_rows) dyh('Duyuru bulunamadı.', 404);
    if (isset($girdi['durum']) && in_array($girdi['durum'], ['yayinda', 'arsiv', 'taslak'], true)) $db->query("UPDATE ik_duyurular SET durum = '" . dy_e($db, $girdi['durum']) . "'" . ($girdi['durum'] === 'yayinda' ? ', yayin = COALESCE(yayin, NOW())' : '') . " WHERE id = $id");
    if (isset($girdi['sabit'])) $db->query("UPDATE ik_duyurular SET sabit = " . (!empty($girdi['sabit']) ? 1 : 0) . " WHERE id = $id");
    dyc(['ok' => true]);
}
if ($islem === 'duyuru_oku') {
    $id = intval($girdi['id'] ?? 0);
    $d = $db->query("SELECT * FROM ik_duyurular WHERE id = $id")->fetch_assoc();
    if (!$d || !dy_duyuru_gorunur($d)) dyh('Duyuru bulunamadı.', 404);
    $onay = !empty($girdi['onay']) ? 1 : 0;
    $db->query("INSERT INTO ik_okumalar (tip, oge_id, kullanici_id, zaman, onay) VALUES ('duyuru', $id, $uid, NOW(), $onay) ON DUPLICATE KEY UPDATE onay = GREATEST(onay, $onay)");
    dyc(['ok' => true]);
}
if ($islem === 'duyuru_sil') {
    dy_yonet_sart();
    $id = intval($girdi['id'] ?? 0);
    dy_dosya_sil_fiziksel($db, "duyuru_id = $id");
    $db->query("DELETE FROM ik_okumalar WHERE tip = 'duyuru' AND oge_id = $id");
    $db->query("DELETE FROM ik_duyurular WHERE id = $id");
    dyc(['ok' => true]);
}

/* ---------- dosya paylaşımı ---------- */
function dy_dosya_ortak_alanlar($db, $g)
{
    $h = dy_hedef_oku($g);
    $it = ($g['indirme_tip'] ?? 'gorenler') === 'secili' ? 'secili' : 'gorenler';
    $ik = []; if ($it === 'secili') foreach (dy_json_dizi($g['indirme_kisi'] ?? '') as $k) { $k = intval($k); if ($k > 0) $ik[$k] = $k; }
    if ($it === 'secili' && !$ik) $it = 'gorenler';
    return [$h, $it, json_encode(array_values($ik))];
}
if ($islem === 'dosya_yukle') {
    dy_yonet_sart();
    $liste = dy_dosya_listesi('dosya');
    if (!$liste) dyh('Dosya seçin.');
    if (count($liste) > 20) dyh('Tek seferde en fazla 20 dosya yüklenebilir.');
    list($h, $it, $ik) = dy_dosya_ortak_alanlar($db, $girdi);
    $aciklama = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 500, 'UTF-8'); $klasor = mb_substr(trim((string)($girdi['klasor'] ?? '')), 0, 80, 'UTF-8');
    $ids = [];
    foreach ($liste as $i => $f) {
        list($ad, $orj, $mime, $boyut) = dy_dosya_kaydet($f, 'f');
        $baslik = count($liste) === 1 && trim((string)($girdi['ad'] ?? '')) !== '' ? mb_substr(trim($girdi['ad']), 0, 200, 'UTF-8') : mb_substr(pathinfo($orj, PATHINFO_FILENAME), 0, 200, 'UTF-8');
        $st = $db->prepare("INSERT INTO ik_dosyalar (ad, aciklama, klasor, dosya, orijinal_ad, mime, boyut, hedef_tip, hedef_birim, hedef_kisi, indirme_tip, indirme_kisi, yukleyen_id, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $st->bind_param('ssssssisssssi', $baslik, $aciklama, $klasor, $ad, $orj, $mime, $boyut, $h['tip'], $h['birim'], $h['kisi'], $it, $ik, $uid);
        $st->execute(); $ids[] = intval($db->son_id());
    }
    $bildirilen = 0; $kanal = dy_kanal($girdi);
    if (!empty($girdi['bildir']) && $ids) {
        $r = ['hedef_tip' => $h['tip'], 'hedef_birim' => $h['birim'], 'hedef_kisi' => $h['kisi']];
        $adlar = []; $q = $db->query("SELECT ad FROM ik_dosyalar WHERE id IN (" . implode(',', $ids) . ")"); if ($q) while ($x = $q->fetch_row()) $adlar[] = $x[0];
        $alici = dy_alicilar($db, $r);
        $bildirilen = dy_bildir($db, $alici, $uid, $kanal, ['baslik' => '📁 Yeni paylaşılan dosya', 'html' => '<b>' . ik_h($ben['ad'] ?? '') . '</b> sizinle ' . (count($adlar) > 1 ? count($adlar) . ' dosya' : 'bir dosya') . ' paylaştı:<br><b>' . ik_h(implode(', ', array_slice($adlar, 0, 5))) . '</b>' . ($aciklama !== '' ? '<br>' . ik_h($aciklama) : ''),
            'sms' => 'Yeni paylaşılan dosya: ' . implode(', ', array_slice($adlar, 0, 2)), 'buton' => 'Dosyalara git', 'link' => 'ik.php?sekme=dosyalar', 'tablo' => 'ik_dosya', 'bid' => 0]);
    }
    dyc(['ok' => true, 'adet' => count($ids), 'bildirilen' => $bildirilen]);
}
if ($islem === 'dosya_guncelle') {
    dy_yonet_sart();
    $id = intval($girdi['id'] ?? 0);
    if (!$db->query("SELECT 1 FROM ik_dosyalar WHERE id = $id AND duyuru_id IS NULL")->num_rows) dyh('Dosya bulunamadı.', 404);
    $ad = mb_substr(trim((string)($girdi['ad'] ?? '')), 0, 200, 'UTF-8'); if ($ad === '') dyh('Dosya adı boş olamaz.');
    list($h, $it, $ik) = dy_dosya_ortak_alanlar($db, $girdi);
    $aciklama = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 500, 'UTF-8'); $klasor = mb_substr(trim((string)($girdi['klasor'] ?? '')), 0, 80, 'UTF-8');
    $st = $db->prepare("UPDATE ik_dosyalar SET ad = ?, aciklama = ?, klasor = ?, hedef_tip = ?, hedef_birim = ?, hedef_kisi = ?, indirme_tip = ?, indirme_kisi = ? WHERE id = ?");
    $st->bind_param('ssssssssi', $ad, $aciklama, $klasor, $h['tip'], $h['birim'], $h['kisi'], $it, $ik, $id); $st->execute();
    dyc(['ok' => true]);
}
if ($islem === 'dosya_sil') {
    dy_yonet_sart();
    $id = intval($girdi['id'] ?? 0);
    dy_dosya_sil_fiziksel($db, "id = $id AND duyuru_id IS NULL");
    dyc(['ok' => true]);
}

/* ---------- anket ---------- */
if ($islem === 'anket_kaydet') {
    dy_yonet_sart();
    $id = intval($girdi['id'] ?? 0);
    $baslik = mb_substr(trim((string)($girdi['baslik'] ?? '')), 0, 200, 'UTF-8'); if ($baslik === '') dyh('Anket başlığı yazın.');
    $acik = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 3000, 'UTF-8');
    $anonim = !empty($girdi['anonim']) ? 1 : 0;
    $sg = in_array($girdi['sonuc_goster'] ?? '', ['yonetici', 'katilan', 'herkes'], true) ? $girdi['sonuc_goster'] : 'katilan';
    $bitis = dy_tarihsaat($girdi['bitis'] ?? '');
    $durum = ($girdi['durum'] ?? 'acik') === 'taslak' ? 'taslak' : 'acik';
    $h = dy_hedef_oku($girdi);
    $sorular = dy_json_dizi($girdi['sorular'] ?? '');
    $temiz = [];
    foreach ($sorular as $s) {
        $soru = mb_substr(trim((string)($s['soru'] ?? '')), 0, 500, 'UTF-8'); if ($soru === '') continue;
        $tip = in_array($s['tip'] ?? '', ['tek', 'coklu', 'puan', 'metin'], true) ? $s['tip'] : 'tek';
        $sec = [];
        if ($tip === 'tek' || $tip === 'coklu') { foreach ((array)($s['secenekler'] ?? []) as $x) { $x = mb_substr(trim((string)$x), 0, 200, 'UTF-8'); if ($x !== '') $sec[] = $x; } if (count($sec) < 2) dyh('“' . $soru . '” sorusu için en az 2 seçenek girin.'); if (count($sec) > 20) dyh('Bir soruya en fazla 20 seçenek eklenebilir.'); }
        $temiz[] = ['soru' => $soru, 'tip' => $tip, 'sec' => $sec, 'zorunlu' => !isset($s['zorunlu']) || !empty($s['zorunlu']) ? 1 : 0];
    }
    if (count($temiz) > 50) dyh('Ankette en fazla 50 soru olabilir.');
    $eski = $id ? $db->query("SELECT * FROM ik_anketler WHERE id = $id")->fetch_assoc() : null;
    $kilitli = $id && intval($db->query("SELECT COUNT(*) FROM ik_anket_katilim WHERE anket_id = $id")->fetch_row()[0]) > 0;
    if ($durum === 'acik' && !$temiz && !$kilitli) dyh('Yayınlamak için en az bir soru ekleyin.');
    if ($id && !$eski) dyh('Anket bulunamadı.', 404);
    $yeniYayin = $durum === 'acik' && (!$eski || $eski['durum'] === 'taslak');
    if (!$id) {
        $st = $db->prepare("INSERT INTO ik_anketler (baslik, aciklama, hedef_tip, hedef_birim, hedef_kisi, anonim, sonuc_goster, bitis, durum, olusturan_id, olusturma) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $st->bind_param('sssssisssi', $baslik, $acik, $h['tip'], $h['birim'], $h['kisi'], $anonim, $sg, $bitis, $durum, $uid);
        if (!$st->execute()) dyh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
        $id = intval($db->son_id()); $cevapVar = false;
    } else {
        $cevapVar = intval($db->query("SELECT COUNT(*) FROM ik_anket_katilim WHERE anket_id = $id")->fetch_row()[0]) > 0;
        $dz = $eski['durum'] === 'kapali' ? 'kapali' : $durum;
        if ($cevapVar) $anonim = intval($eski['anonim']);     // yanıt geldikten sonra anonimlik değişmez
        $st = $db->prepare("UPDATE ik_anketler SET baslik = ?, aciklama = ?, hedef_tip = ?, hedef_birim = ?, hedef_kisi = ?, anonim = ?, sonuc_goster = ?, bitis = ?, durum = ? WHERE id = ?");
        $st->bind_param('sssssisssi', $baslik, $acik, $h['tip'], $h['birim'], $h['kisi'], $anonim, $sg, $bitis, $dz, $id); $st->execute();
    }
    if (!$cevapVar) {                                         // yanıt gelmemişse sorular baştan yazılır
        $db->query("DELETE FROM ik_anket_sorular WHERE anket_id = $id");
        foreach ($temiz as $i => $s) {
            $sj = json_encode($s['sec'], JSON_UNESCAPED_UNICODE); $sira = $i + 1;
            $st = $db->prepare("INSERT INTO ik_anket_sorular (anket_id, sira, tip, soru, secenekler, zorunlu) VALUES (?, ?, ?, ?, ?, ?)");
            $st->bind_param('iisssi', $id, $sira, $s['tip'], $s['soru'], $sj, $s['zorunlu']); $st->execute();
        }
    }
    $bildirilen = 0;
    if ($yeniYayin || (!empty($girdi['yeniden_bildir']) && $durum === 'acik')) {
        $a = $db->query("SELECT * FROM ik_anketler WHERE id = $id")->fetch_assoc();
        $bildirilen = dy_bildir($db, dy_alicilar($db, $a), $uid, dy_kanal($girdi), ['baslik' => '📊 Yeni anket: ' . $baslik, 'html' => '<b>' . ik_h($ben['ad'] ?? '') . '</b> sizi ankete katılmaya davet ediyor:<br><br><b>' . ik_h($baslik) . '</b>' . ($acik !== '' ? '<br>' . nl2br(ik_h(dy_ozet_metin($acik, 400))) : '') .
            ($bitis ? '<br><br>Son katılım: <b>' . date('d.m.Y H:i', strtotime($bitis)) . '</b>' : '') . ($anonim ? '<br>🔒 Anket anonimdir; cevaplarınız kimlikle eşleştirilmez.' : ''),
            'sms' => 'Yeni anket: ' . $baslik . ' —', 'buton' => 'Ankete katıl', 'link' => 'ik.php?sekme=anketler&id=' . $id, 'tablo' => 'ik_anket', 'bid' => $id]);
    }
    dyc(['ok' => true, 'id' => $id, 'bildirilen' => $bildirilen]);
}
if ($islem === 'anket_durum') {
    dy_yonet_sart();
    $id = intval($girdi['id'] ?? 0); $d = ($girdi['durum'] ?? '') === 'kapali' ? 'kapali' : 'acik';
    if (!$db->query("SELECT 1 FROM ik_anketler WHERE id = $id")->num_rows) dyh('Anket bulunamadı.', 404);
    $db->query("UPDATE ik_anketler SET durum = '$d'" . ($d === 'acik' ? ', bitis = IF(bitis IS NOT NULL AND bitis < NOW(), NULL, bitis)' : '') . " WHERE id = $id");
    dyc(['ok' => true]);
}
if ($islem === 'anket_sil') {
    dy_yonet_sart();
    $id = intval($girdi['id'] ?? 0);
    foreach (['ik_anket_cevaplar', 'ik_anket_katilim', 'ik_anket_sorular'] as $t) $db->query("DELETE FROM $t WHERE anket_id = $id");
    $db->query("DELETE FROM ik_anketler WHERE id = $id");
    dyc(['ok' => true]);
}
if ($islem === 'anket_cevapla') {
    $id = intval($girdi['id'] ?? 0);
    $a = $db->query("SELECT * FROM ik_anketler WHERE id = $id")->fetch_assoc();
    if (!$a || !dy_hedefte($a, $uid, $BIRIM) || $a['durum'] === 'taslak') dyh('Bu ankete katılma yetkiniz yok.', 403);
    if (dy_anket_etkin_durum($a) !== 'acik') dyh('Bu anket kapanmış.');
    $cv = json_decode((string)($girdi['cevaplar'] ?? ''), true); if (!is_array($cv)) dyh('Cevaplar okunamadı.');
    $satirlar = [];
    $q = $db->query("SELECT * FROM ik_anket_sorular WHERE anket_id = $id ORDER BY sira, id");
    if ($q) while ($s = $q->fetch_assoc()) {
        $sid = intval($s['id']); $sec = dy_json_dizi($s['secenekler']); $v = $cv[$sid] ?? $cv[(string)$sid] ?? null;
        $bos = $v === null || $v === '' || $v === [];
        if ($bos) { if ($s['zorunlu']) dyh('“' . $s['soru'] . '” sorusunu cevaplayın.'); continue; }
        if ($s['tip'] === 'tek') { $i = intval($v); if (!isset($sec[$i])) dyh('Geçersiz seçenek.'); $satirlar[] = [$sid, (string)$i]; }
        elseif ($s['tip'] === 'coklu') { foreach (array_unique(array_map('intval', (array)$v)) as $i) { if (!isset($sec[$i])) dyh('Geçersiz seçenek.'); $satirlar[] = [$sid, (string)$i]; } }
        elseif ($s['tip'] === 'puan') { $i = intval($v); if ($i < 1 || $i > 5) dyh('Puan 1 ile 5 arasında olmalı.'); $satirlar[] = [$sid, (string)$i]; }
        else $satirlar[] = [$sid, mb_substr(trim((string)$v), 0, 2000, 'UTF-8')];
    }
    if (!$satirlar) dyh('En az bir soruyu cevaplayın.');
    $db->query("INSERT IGNORE INTO ik_anket_katilim (anket_id, kullanici_id, zaman) VALUES ($id, $uid, NOW())");
    if ($db->etkilenen() < 1) dyh('Bu ankete zaten katıldınız.');
    $yn = random_int(1, 2147483000); $kid = $a['anonim'] ? null : $uid;
    foreach ($satirlar as $s) {
        $st = $db->prepare("INSERT INTO ik_anket_cevaplar (anket_id, soru_id, yanit_no, kullanici_id, deger, zaman) VALUES (?, ?, ?, ?, ?, NOW())");
        $st->bind_param('iiiis', $id, $s[0], $yn, $kid, $s[1]); $st->execute();
    }
    dyc(['ok' => true]);
}

dyh('Bilinmeyen işlem.', 404);
