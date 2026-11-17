<?php
// ============================================================
// ik_islem.php — İnsan Kaynakları JSON uç noktası
//   GET  baslangic | personeller | personel | izinler | belge (indir)
//   POST personel_kaydet | izin_talep | izin_karar | izin_iptal | belge_yukle | belge_sil
//        | zimmet_kaydet | zimmet_iade | zimmet_sil                    (hepsi CSRF korumalı)
//   İzin onay akışı: GET akis_getir | akis_onizle | izin_detay | bildirim_ayar_getir | bildirim_log
//                    POST akis_kaydet | akis_sil | bildirim_ayar_kaydet | kanal_test           (yetki: ik_akis_yonet)
// Yetkiler: tb_yetki_motor.php → modül "ik"
// ============================================================
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/ik_ortak.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function ikc($v, $k = 200) { if ($k === 200 && !empty($GLOBALS['IK_LOG']) && is_array($v) && !empty($v['ok'])) { try { require_once __DIR__ . '/ik_ek.php'; $L = $GLOBALS['IK_LOG']; $GLOBALS['IK_LOG'] = null; ik_ek_oto_log($L[0], $L[1], $L[2], $L[3], $v); } catch (Throwable $e) {} } $o = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE); http_response_code($k); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); header('Content-Length: ' . strlen($o)); header('Connection: close'); echo $o; exit; }
function ikh($m, $k = 400) { ikc(['ok' => false, 'hata' => $m], $k); }

$uid = intval($_SESSION['kullanici_id'] ?? 0);
if ($uid <= 0) ikh('Oturum süresi doldu, lütfen yeniden giriş yapın.', 401);
ik_sema($db);
$Y = vy_coz($db, 'ik', $uid);
if (empty($Y['ik_erisim'])) ikh('İnsan Kaynakları sayfasına erişim yetkiniz yok.', 403);
$girdi = $_POST + $_GET; // Bazı hostinglerde zorunlu https/www yönlendirmesi POST'u GET'e çevirebiliyor; sorgu dizesindeki veriler de kabul edilir (bkz. ik_csrf)
$islem = (string)($_GET['islem'] ?? $girdi['islem'] ?? '');
$BELGE_KLASOR = tb_yukleme_dizin('ik_belgeler');
// Menü bazlı görünürlük yetkileri (Yetkiler panelinden verilir / alınır) — sunucu tarafında da uygulanır
$IK_MENU_YETKI = ['izin_takvimi' => 'ik_menu_takvim', 'puantaj_ay' => 'ik_menu_puantaj', 'puantaj_kaydet' => 'ik_menu_puantaj', 'masraflar' => 'ik_menu_masraf', 'masraf_ekle' => 'ik_menu_masraf', 'masraf_sil' => 'ik_menu_masraf', 'avanslar' => 'ik_menu_avans', 'avans_ekle' => 'ik_menu_avans', 'avans_karar' => 'ik_menu_avans', 'avans_sil' => 'ik_menu_avans',
    'egitimler' => 'ik_menu_egitim', 'egitim_katilim_guncelle' => 'ik_menu_egitim', 'performans' => 'ik_menu_performans', 'bordro' => 'ik_menu_bordro'];
// İK ek modülü: menü kapatma, otomatik işlem günlüğü, loglar, ajanda, görünüm
require_once __DIR__ . '/ik_ek.php'; ik_ek_sema($db);
require_once __DIR__ . '/ik_perf.php';
$IK_EK_KAPALI = ik_ek_kapali($db);
require_once __DIR__ . '/platform_paket.php';
try { pf_paket_sema($db); } catch (Throwable $e) {}
$IK_KILITLI = pf_paket_kilitli($db);
if (isset(ik_ek_islem_menu()[$islem]) && in_array(ik_ek_islem_menu()[$islem], $IK_EK_KAPALI, true)) ikh('Bu bölüm kurum yöneticisi tarafından kapatılmış.', 403);
$IK_PAKET_MENU = ik_ek_islem_menu() + ['belge_tum' => 'belge_tum', 'bordro' => 'bordro', 'rapor' => 'raporlar'];
if (isset($IK_PAKET_MENU[$islem]) && in_array($IK_PAKET_MENU[$islem], $IK_KILITLI, true)) ikh('Bu özellik kurumunuzun paketinde yok veya süresi bitti. Paketlerim sayfasından bilgi alabilirsiniz.', 402);
if ($islem === 'paketlerim' || $islem === 'paket_talep') pf_paket_ik_calistir($db, $uid, $Y, $girdi, $islem);
if ($_SERVER['REQUEST_METHOD'] === 'POST') $GLOBALS['IK_LOG'] = [$db, $uid, $islem, $girdi];
if (in_array($islem, ['personel_resim_kaldir', 'loglar', 'personel_log', 'ajanda', 'takvim', 'aday_liste', 'aday_kaydet', 'aday_asama', 'aday_sil', 'not_kaydet', 'not_sil', 'gorunum_getir', 'gorunum_kaydet', 'favicon_yukle', 'favicon_sil', 'panel_ek'], true)) ik_ek_calistir($db, $uid, $Y, $girdi, $islem);
if (strpos($islem, 'perf_') === 0) ik_perf_calistir($db, $uid, $Y, $girdi, $islem);
if (isset($IK_MENU_YETKI[$islem]) && array_key_exists($IK_MENU_YETKI[$islem], $Y) && empty($Y[$IK_MENU_YETKI[$islem]])) ikh('Bu bölümü görme yetkiniz yok.', 403);

$ALANLAR = [
    'iletisim' => ['telefon', 'email', 'telefon2', 'email2', 'adres', 'il', 'ilce', 'acil_kisi', 'acil_yakinlik', 'acil_telefon', 'kan_grubu', 'medeni_durum', 'cocuk_sayisi'],
    'egitim'   => ['egitim_durumu', 'okul', 'bolum', 'mezuniyet_yili'],
    'kisisel'  => ['tc_kimlik', 'dogum_tarihi', 'dogum_yeri', 'cinsiyet', 'askerlik', 'anne_adi', 'baba_adi'],
    'is'       => ['ise_giris', 'isten_cikis', 'cikis_nedeni', 'unvan', 'departman', 'yonetici_id', 'calisma_sekli', 'sgk_no', 'kariyer_baslangic', 'sgk_baslangic', 'sirket', 'yillik_izin_hak', 'notlar'],
    'mali'     => ['maas', 'maas_tipi', 'iban', 'banka'],
];
$KULLANICI_ALAN = ['telefon', 'email', 'dogum_tarihi']; // kullanicilar tablosunda

function ik_csrf($g)
{
    $gelen = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $g['_csrf'] ?? '');
    if (empty($_SESSION['tb_csrf']) || !hash_equals((string)$_SESSION['tb_csrf'], $gelen)) ikh('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.', 419);
}
/** Birim müdürü atanınca birimdeki personelin "bağlı olduğu yönetici" alanını doldurur. Yalnız yöneticisi boş olanlar (ya da eski müdüre bağlı olanlar) değişir; ara yöneticiler bozulmaz. */
function ik_birim_mudur_esitle($db, $birim, $eskiler, $yeniler, $uid)
{
    $e = $db->real_escape_string($birim); $yeni = $yeniler ? intval($yeniler[0]) : 0; $n = 0;
    $m = []; $q = $db->query("SELECT kullanici_id, yonetici_id FROM ik_personel WHERE yonetici_id IS NOT NULL"); if ($q) while ($r = $q->fetch_row()) $m[intval($r[0])] = intval($r[1]);
    $ust = []; for ($s = $yeni, $i = 0; $s && $i < 30; $i++) { $s = $m[$s] ?? 0; if (!$s || in_array($s, $ust, true)) break; $ust[] = $s; }
    $q = $db->query("SELECT p.kullanici_id, p.yonetici_id FROM ik_personel p JOIN kullanicilar k ON k.id = p.kullanici_id WHERE k.aktif = 1 AND p.isten_cikis IS NULL AND LOWER(p.departman) = LOWER('$e')");
    $satir = []; if ($q) while ($r = $q->fetch_row()) $satir[] = [intval($r[0]), intval($r[1])];
    foreach ($satir as $r) {
        list($kid, $yon) = $r;
        if (in_array($kid, $yeniler, true)) continue;                       // müdürün kendisi değişmez
        if ($yon && !in_array($yon, $eskiler, true)) continue;              // başka yöneticiye bağlı: dokunma
        if ($yeni === $yon) continue;
        if ($yeni && in_array($kid, $ust, true)) continue;                  // döngü olmasın
        $db->query("UPDATE ik_personel SET yonetici_id = " . ($yeni ?: 'NULL') . ", guncelleyen_id = " . intval($uid) . ", guncelleme_tarihi = NOW() WHERE kullanici_id = " . $kid); $n++;
    }
    return $n;
}
function ik_birim_yon_sema($db)
{
    static $ok = false; if ($ok) return; $ok = true;
    $db->query("CREATE TABLE IF NOT EXISTS ik_birim_yoneticileri (
        birim VARCHAR(100) NOT NULL, kullanici_id INT NOT NULL, ekleyen_id INT NULL, tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (birim, kullanici_id), KEY k_kul (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function ik_gorebilir($Y, $uid, $hedef) { return intval($hedef) === $uid || !empty($Y['ik_personel_gor']) || !empty($Y['ik_duzenle']); }
function ik_birim_listeye_ekle($db, $birim)
{
    $birim = trim($birim); if ($birim === '') return;
    $l = array_filter(array_map('trim', preg_split('/\R/u', (string)ik_ayar($db, 'departman_listesi', ''))), 'strlen');
    foreach ($l as $x) if (mb_strtolower($x, 'UTF-8') === mb_strtolower($birim, 'UTF-8')) return;
    $l[] = $birim; ik_ayar_yaz($db, 'departman_listesi', implode("\n", $l));
}
function ik_birim_yaz($db, $kid, $birim, $uid)
{
    $kid = intval($kid);
    $db->query("INSERT IGNORE INTO ik_personel (kullanici_id) VALUES ($kid)");
    $db->query("UPDATE ik_personel SET departman = '" . $db->real_escape_string($birim) . "', guncelleyen_id = " . intval($uid) . ", guncelleme_tarihi = NOW() WHERE kullanici_id = $kid");
}
function ik_departman($r) { $d = trim((string)($r['departman'] ?? '')); if ($d === '') $d = trim((string)($r['gorev'] ?? '')); return $d !== '' ? $d : 'Belirtilmemiş'; }
/** "25.000,50" / "25000.5" / "14" → float; boş → null */
function ik_sayi($v)
{
    $v = preg_replace('/[^\d.,-]/', '', (string)$v);
    if ($v === '' || $v === '-') return null;
    if (strpos($v, ',') !== false) $v = str_replace(',', '.', str_replace('.', '', $v));   // Türkçe biçim
    elseif (substr_count($v, '.') > 1 || preg_match('/\.\d{3}$/', $v)) $v = str_replace('.', '', $v); // 25.000
    return round((float)$v, 2);
}
function ik_tarih($v) { $v = trim((string)$v); return $v !== '' && strtotime($v) ? date('Y-m-d', strtotime($v)) : null; }
function ik_kidem($giris, $cikis = null)
{
    if (!$giris) return '';
    $a = new DateTime($giris); $b = new DateTime($cikis ?: 'now');
    $f = $a->diff($b);
    return ($f->y ? $f->y . ' yıl ' : '') . ($f->m ? $f->m . ' ay' : ($f->y ? '' : 'yeni'));
}

/** Sırası bende olan (ya da zincirsiz eski talepte benim onaylayabileceğim) bekleyen talep sayısı */
function ik_bana_bekleyen_kosul($uid, $Y)
{
    $uid = intval($uid);
    $k = "(EXISTS (SELECT 1 FROM ik_izin_adimlari a WHERE a.talep_id = t.id AND a.sira = t.aktif_adim AND a.durum = 'bekliyor' AND (a.onaylayici_id = $uid OR (a.vekil_id = $uid AND EXISTS (SELECT 1 FROM ik_izin_talepleri x WHERE x.kullanici_id = a.onaylayici_id AND x.durum = 'onaylandi' AND CURDATE() BETWEEN x.baslangic AND x.bitis))))";
    if (!empty($Y['ik_izin_onay'])) $k .= " OR NOT EXISTS (SELECT 1 FROM ik_izin_adimlari a2 WHERE a2.talep_id = t.id)";
    return $k . ") AND t.durum = 'beklemede' AND t.kullanici_id <> $uid";
}
function ik_bana_bekleyen($db, $uid, $Y)
{
    $q = $db->query("SELECT COUNT(*) FROM ik_izin_talepleri t WHERE " . ik_bana_bekleyen_kosul($uid, $Y));
    return $q ? intval($q->fetch_row()[0]) : 0;
}
/** İzin talebi satırı + onay zinciri (liste / detay için ortak biçim) */
function ik_izin_satirlari($db, $q, $uid, $Y)
{
    $satir = []; $ids = [];
    if ($q) while ($r = $q->fetch_assoc()) { $satir[] = $r; $ids[] = intval($r['id']); }
    $adimlar = [];
    if ($ids) {
        $a = $db->query("SELECT a.*, u.ad_soyad, v.ad_soyad karar_veren FROM ik_izin_adimlari a LEFT JOIN kullanicilar u ON u.id = a.onaylayici_id LEFT JOIN kullanicilar v ON v.id = a.karar_veren_id WHERE a.talep_id IN (" . implode(',', $ids) . ") ORDER BY a.talep_id, a.sira, a.id");
        if ($a) while ($r = $a->fetch_assoc()) $adimlar[intval($r['talep_id'])][] = ['sira' => intval($r['sira']), 'id' => intval($r['onaylayici_id']), 'ad' => (string)$r['ad_soyad'], 'durum' => $r['durum'],
            'karar_veren' => (string)$r['karar_veren'], 'tarih' => $r['karar_tarihi'], 'not' => (string)$r['not_metni'], 'kanal' => ['site' => (bool)$r['k_site'], 'sms' => (bool)$r['k_sms'], 'mail' => (bool)$r['k_mail']]];
    }
    $liste = [];
    foreach ($satir as $r) {
        $tid = intval($r['id']); $zincir = $adimlar[$tid] ?? [];
        $aktif = null; foreach ($zincir as $z) if ($z['durum'] === 'bekliyor') { $aktif = $z; break; }
        $sahip = intval($r['kullanici_id']) === $uid;
        $karar = false;
        if ($r['durum'] === 'beklemede' && !$sahip) $karar = $zincir ? ($aktif && ($aktif['id'] === $uid || !empty($Y['kurucu']))) : !empty($Y['ik_izin_onay']);
        $liste[] = ['id' => $tid, 'kid' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''),
            'tur' => $r['tur'], 'tur_ad' => IK_IZIN_TURLERI[$r['tur']] ?? $r['tur'], 'baslangic' => $r['baslangic'], 'bitis' => $r['bitis'], 'gun' => (float)$r['gun'], 'yarim_gun' => intval($r['yarim_gun']) === 1,
            'aciklama' => (string)$r['aciklama'], 'vekil' => $r['vekil'], 'durum' => $r['durum'], 'onaylayan' => $r['onaylayan'], 'onay_notu' => $r['onay_notu'], 'onay_tarihi' => $r['onay_tarihi'],
            'olusturma' => $r['olusturma_tarihi'], 'departman' => (string)($r['departman'] ?? ''), 'adimlar' => $zincir, 'aktif_ad' => $aktif ? $aktif['ad'] : null,
            'sira_bende' => $r['durum'] === 'beklemede' && $aktif && $aktif['id'] === $uid, 'karar_verebilir' => $karar,
            'iptal_edebilir' => ($sahip && $r['durum'] === 'beklemede') || (!empty($Y['ik_izin_onay']) && $r['durum'] === 'onaylandi')];
    }
    return $liste;
}
const IK_IZIN_SORGU = "SELECT t.*, k.ad_soyad, k.avatar_renk, k.profil_resmi, o.ad_soyad onaylayan, v.ad_soyad vekil FROM ik_izin_talepleri t
                     JOIN kullanicilar k ON k.id = t.kullanici_id LEFT JOIN kullanicilar o ON o.id = t.onaylayan_id LEFT JOIN kullanicilar v ON v.id = t.vekil_id";

// ──────────────────────────── GET ────────────────────────────
if ($islem === 'baslangic') {
    require_once __DIR__ . '/kurum_yedek.php'; yd_zamanli_arkada($db);
    require_once __DIR__ . '/platform_mesaj.php'; pf_lm_tara_arkada($db); if (function_exists('pf_vdb_oto_arkada')) pf_vdb_oto_arkada($db);
    ik_sonra(function () use ($db) { try { require_once __DIR__ . '/ik_etkinlik.php'; ik_etkinlik_hatirlat($db); } catch (Throwable $e) {} });
    ik_sonra(function () use ($db) { try { ik_ek_not_hatirlat($db); } catch (Throwable $e) {} });
    $ozet = ['izin' => ik_izin_ozet($db, $uid)];
    $bugun = date('Y-m-d');
    $izinliler = [];
    $kosul = (!empty($Y['ik_personel_gor']) || !empty($Y['ik_izin_onay'])) ? '1=1' : "t.kullanici_id = $uid";
    $q = $db->query("SELECT t.kullanici_id, t.tur, t.bitis, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_izin_talepleri t JOIN kullanicilar k ON k.id = t.kullanici_id
                     WHERE t.durum = 'onaylandi' AND '$bugun' BETWEEN t.baslangic AND t.bitis AND $kosul ORDER BY k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) $izinliler[] = ['id' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9',
        'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'tur' => IK_IZIN_TURLERI[$r['tur']] ?? $r['tur'], 'bitis' => $r['bitis']];
    $ozet['izinli'] = $izinliler;
    $ozet['bekleyen'] = (!empty($Y['ik_izin_onay']) || !empty($Y['ik_personel_gor'])) ? intval($db->query("SELECT COUNT(*) FROM ik_izin_talepleri WHERE durum = 'beklemede' AND kullanici_id <> $uid")->fetch_row()[0]) : null;
    $ozet['bana_bekleyen'] = ik_bana_bekleyen($db, $uid, $Y);
    $ozet['benim_bekleyen'] = intval($db->query("SELECT COUNT(*) FROM ik_izin_talepleri WHERE durum = 'beklemede' AND kullanici_id = $uid")->fetch_row()[0]);
    if (!empty($Y['ik_personel_gor'])) {
        $ozet['personel'] = intval($db->query("SELECT COUNT(*) FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND p.isten_cikis IS NULL")->fetch_row()[0]);
        // Yaklaşan doğum günleri ve iş yıldönümleri (30 gün)
        $yak = [];
        $q = $db->query("SELECT k.id, k.ad_soyad, k.avatar_renk, k.profil_resmi, k.dogum_tarihi, p.ise_giris FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND p.isten_cikis IS NULL");
        if ($q) while ($r = $q->fetch_assoc()) {
            foreach (['dogum' => $r['dogum_tarihi'], 'yildonumu' => $r['ise_giris']] as $tip => $t) {
                if (!$t || $t === '0000-00-00') continue;
                $ay = date('m-d', strtotime($t));
                $bu = strtotime(date('Y') . '-' . $ay); if ($bu < strtotime('today')) $bu = strtotime((date('Y') + 1) . '-' . $ay);
                $kalan = (int)round(($bu - strtotime('today')) / 86400);
                $yil = (int)date('Y', $bu) - (int)date('Y', strtotime($t));
                if ($kalan <= 30 && ($tip === 'dogum' || $yil >= 1)) $yak[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9',
                    'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'tip' => $tip, 'tarih' => date('Y-m-d', $bu), 'kalan' => $kalan, 'yil' => $yil];
            }
        }
        usort($yak, function ($a, $b) { return $a['kalan'] <=> $b['kalan']; });
        $ozet['yaklasan'] = array_slice($yak, 0, 12);
        $ozet['departmanlar'] = [];
        // Departman = İK kartındaki departman, yoksa kullanıcının görevi (farklı tablolar → PHP'de birleştirilir; harmanlama çakışması olmaz)
        $say = [];
        $q = $db->query("SELECT p.departman, k.gorev FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND p.isten_cikis IS NULL");
        if ($q) while ($r = $q->fetch_assoc()) { $d = ik_departman($r); $say[$d] = ($say[$d] ?? 0) + 1; }
        arsort($say);
        foreach ($say as $d => $n) $ozet['departmanlar'][] = ['ad' => $d, 'n' => $n];
    }
    if (!empty($Y['ik_belge'])) {
        $ozet['biten_belgeler'] = [];
        $q = $db->query("SELECT b.id, b.ad, b.tur, b.gecerlilik, k.id kid, k.ad_soyad, DATEDIFF(b.gecerlilik, CURDATE()) kalan FROM ik_belgeler b JOIN kullanicilar k ON k.id = b.kullanici_id
                         WHERE b.gecerlilik IS NOT NULL AND b.gecerlilik <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) ORDER BY b.gecerlilik LIMIT 10");
        if ($q) while ($r = $q->fetch_assoc()) $ozet['biten_belgeler'][] = ['id' => intval($r['id']), 'kid' => intval($r['kid']), 'kisi' => $r['ad_soyad'], 'ad' => $r['ad'] ?: (IK_BELGE_TURLERI[$r['tur']] ?? ''), 'gecerlilik' => $r['gecerlilik'], 'kalan' => intval($r['kalan'])];
        $ozet['zimmet'] = intval($db->query("SELECT COUNT(*) FROM ik_zimmetler WHERE durum = 'zimmetli'")->fetch_row()[0]);
    }
    $kisiler = [];
    $dm = []; $dq = $db->query("SELECT kullanici_id, departman FROM ik_personel"); if ($dq) while ($d = $dq->fetch_row()) $dm[intval($d[0])] = (string)$d[1];
    $q = $db->query("SELECT id, ad_soyad, avatar_renk, profil_resmi FROM kullanicilar WHERE aktif = 1 ORDER BY ad_soyad");
    if ($q) while ($r = $q->fetch_row()) $kisiler[] = ['id' => intval($r[0]), 'ad' => $r[1], 'renk' => $r[2] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r[3] ?? ''), 'departman' => $dm[intval($r[0])] ?? ''];
    // Kullanım süresi uyarısı (yalnızca kurum yöneticisine): bitişe ≤5 gün kala ve ek süre boyunca; günde bir bildirim de düşer
    $lisansUyari = null;
    if (!empty($GLOBALS['KURUM']) && ($db->query("SELECT rol FROM kullanicilar WHERE id = $uid")->fetch_row()[0] ?? '') === 'kurucu') {
        $l = pf_lisans($GLOBALS['KURUM']);
        if (in_array($l['durum'], ['yaklasiyor', 'ek_sure'], true)) {
            $lisansUyari = $l;
            if (ik_log_uygun($db, $uid, 'lisans_uyari', 20)) {
                $t = $l['bitis'] ? date('d.m.Y', strtotime($l['bitis'])) : '';
                ik_bildir($db, $uid, $uid, $l['durum'] === 'yaklasiyor' ? '⏳ Kullanım süreniz bitiyor' : '⚠️ Kullanım süreniz doldu',
                    $l['durum'] === 'yaklasiyor' ? "⏳ Kullanım süreniz <b>$t</b> tarihinde bitiyor ({$l['kalan']} gün kaldı). Kesinti yaşamamak için süreyi yenileyin." : "⚠️ Kullanım süreniz <b>$t</b> tarihinde doldu. <b>{$l['ek_kalan']} gün</b> içinde yenilenmezse hesap askıya alınacak.", 'ik_lisans', 0);
            }
        }
    }
    ikc(['ok' => true, 'lisans_uyari' => $lisansUyari, 'ben' => $uid, 'menu_kapali' => $IK_EK_KAPALI, 'kilitli' => $IK_KILITLI, 'paket' => pf_paket_ik_ozet($db), 'yetki' => $Y, 'ozet' => $ozet, 'kisiler' => $kisiler, 'ben_ad' => $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $uid")->fetch_row()[0] ?? '',
        'izin_turleri' => IK_IZIN_TURLERI, 'belge_turleri' => IK_BELGE_TURLERI, 'calisma' => IK_CALISMA, 'birim_listesi' => ik_liste_birlesik($db, 'departman_listesi', 'departman'), 'unvan_listesi' => ik_liste_birlesik($db, 'unvan_listesi', 'unvan')]);
}

if ($islem === 'personeller') {
    if (empty($Y['ik_personel_gor'])) ikh('Personel listesini görme yetkiniz yok.', 403);
    $w = ['1=1'];
    $durum = (string)($_GET['durum'] ?? 'aktif');
    if ($durum === 'aktif') $w[] = 'k.aktif = 1 AND p.isten_cikis IS NULL';
    elseif ($durum === 'ayrilan') $w[] = '(p.isten_cikis IS NOT NULL OR k.aktif = 0)';
    $ara = trim((string)($_GET['ara'] ?? ''));
    if ($ara !== '') {
        // Büyük/küçük harf duyarsız (tablo harmanlaması ne olursa olsun)
        $a = $db->real_escape_string($ara);
        $ak = $db->real_escape_string(mb_strtolower(strtr($ara, ['I' => 'ı', 'İ' => 'i']), 'UTF-8'));
        $p = [];
        foreach (['k.ad_soyad', 'k.gorev', 'p.unvan', 'p.departman', 'k.telefon', 'k.email'] as $c) { $p[] = "$c LIKE '%$a%'"; $p[] = "LOWER($c) LIKE '%$ak%'"; }
        $w[] = '(' . implode(' OR ', $p) . ')';
    }
    $dep = trim((string)($_GET['departman'] ?? ''));
    // (departman süzgeci aşağıda PHP'de uygulanır)
    $q = $db->query("SELECT k.id, k.ad_soyad, k.gorev, k.rol, k.telefon, k.email, k.avatar_renk, k.profil_resmi, k.dogum_tarihi, k.aktif, p.unvan, p.departman, p.ise_giris, p.isten_cikis, p.calisma_sekli,
            (SELECT COUNT(*) FROM ik_izin_talepleri t WHERE t.kullanici_id = k.id AND t.durum = 'onaylandi' AND CURDATE() BETWEEN t.baslangic AND t.bitis) AS izinde
        FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE " . implode(' AND ', $w) . " ORDER BY k.ad_soyad");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) if ($dep === '' || ik_departman($r) === $dep) $liste[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9',
        'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''),
        'unvan' => $r['unvan'] ?: '', 'departman' => $r['departman'] ?: ($r['gorev'] ?: ''), 'rol' => $r['rol'], 'telefon' => $r['telefon'] ?: '', 'email' => $r['email'] ?: '',
        'ise_giris' => $r['ise_giris'], 'kidem' => ik_kidem($r['ise_giris'], $r['isten_cikis']), 'ayrildi' => (bool)$r['isten_cikis'] || !intval($r['aktif']),
        'calisma' => IK_CALISMA[$r['calisma_sekli'] ?? ''] ?? '', 'izinde' => intval($r['izinde']) > 0];
    ikc(['ok' => true, 'liste' => $liste]);
}

if ($islem === 'personel') {
    ik_form_sema($db);
    $id = intval($_GET['id'] ?? $uid);
    if (!ik_gorebilir($Y, $uid, $id)) ikh('Bu personelin kartını görme yetkiniz yok.', 403);
    $k = $db->query("SELECT id, ad_soyad, kullanici_adi, rol, gorev, telefon, email, dogum_tarihi, avatar_renk, profil_resmi, aktif FROM kullanicilar WHERE id = $id")->fetch_assoc();
    if (!$k) ikh('Personel bulunamadı.', 404);
    $p = $db->query("SELECT * FROM ik_personel WHERE kullanici_id = $id")->fetch_assoc() ?: [];
    $kendisi = $id === $uid;
    $maasGor = !empty($Y['ik_maas']) || $kendisi;
    $kart = ['id' => $id, 'ad' => $k['ad_soyad'], 'rol' => $k['rol'], 'gorev' => $k['gorev'] ?: '', 'renk' => $k['avatar_renk'] ?: '#0ea5e9',
        'resim' => tb_profil_resmi_src($k['profil_resmi'] ?? ''), 'aktif' => intval($k['aktif']) === 1,
        'telefon' => $k['telefon'] ?: '', 'email' => $k['email'] ?: '', 'dogum_tarihi' => ($k['dogum_tarihi'] && $k['dogum_tarihi'] !== '0000-00-00') ? $k['dogum_tarihi'] : ''];
    foreach ($ALANLAR as $grup => $alanlar) foreach ($alanlar as $a) if (!in_array($a, $KULLANICI_ALAN, true)) $kart[$a] = $p[$a] ?? null;
    if (!$maasGor) foreach ($ALANLAR['mali'] as $a) unset($kart[$a]);
    if ($kendisi && empty($Y['ik_personel_gor']) && empty($Y['ik_duzenle'])) unset($kart['notlar']); // İK notları kişiye gösterilmez
    $kart['departman'] = $kart['departman'] ?: ($k['gorev'] ?: '');
    $kart['kidem'] = ik_kidem($p['ise_giris'] ?? null, $p['isten_cikis'] ?? null);
    if (!empty($kart['yonetici_id'])) $kart['yonetici_adi'] = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = " . intval($kart['yonetici_id']))->fetch_row()[0] ?? '';
    $duz = [
        // Personel kendi kartını DÜZENLEYEMEZ: tüm bölümler yalnızca "Personel bilgilerini düzenleme" (ik_duzenle) yetkisiyle
        'iletisim' => !empty($Y['ik_duzenle']), 'egitim' => !empty($Y['ik_duzenle']),
        'kisisel' => !empty($Y['ik_duzenle']), 'is' => !empty($Y['ik_duzenle']), 'mali' => !empty($Y['ik_maas']),
    ];
    $izinler = [];
    $q = $db->query("SELECT t.*, o.ad_soyad onaylayan FROM ik_izin_talepleri t LEFT JOIN kullanicilar o ON o.id = t.onaylayan_id WHERE t.kullanici_id = $id ORDER BY t.baslangic DESC LIMIT 50");
    if ($q) while ($r = $q->fetch_assoc()) $izinler[] = ['id' => intval($r['id']), 'tur' => $r['tur'], 'tur_ad' => IK_IZIN_TURLERI[$r['tur']] ?? $r['tur'], 'baslangic' => $r['baslangic'], 'bitis' => $r['bitis'],
        'gun' => (float)$r['gun'], 'durum' => $r['durum'], 'aciklama' => (string)$r['aciklama'], 'onaylayan' => $r['onaylayan'], 'onay_notu' => $r['onay_notu']];
    $belgeler = [];
    $q = $db->query("SELECT b.*, u.ad_soyad yukleyen FROM ik_belgeler b LEFT JOIN kullanicilar u ON u.id = b.yukleyen_id WHERE b.kullanici_id = $id ORDER BY b.tarih DESC");
    if ($q) while ($r = $q->fetch_assoc()) $belgeler[] = ['id' => intval($r['id']), 'tur' => $r['tur'], 'tur_ad' => IK_BELGE_TURLERI[$r['tur']] ?? $r['tur'], 'ad' => $r['ad'], 'orijinal_ad' => $r['orijinal_ad'],
        'boyut' => intval($r['boyut']), 'mime' => $r['mime'], 'gecerlilik' => $r['gecerlilik'], 'tarih' => $r['tarih'], 'yukleyen' => $r['yukleyen'], 'url' => 'ik_islem.php?islem=belge&id=' . intval($r['id'])];
    $zimmetler = [];
    $q = $db->query("SELECT * FROM ik_zimmetler WHERE kullanici_id = $id ORDER BY (durum = 'zimmetli') DESC, teslim_tarihi DESC");
    if ($q) while ($r = $q->fetch_assoc()) $zimmetler[] = ['id' => intval($r['id']), 'esya' => $r['esya'], 'seri_no' => $r['seri_no'], 'aciklama' => $r['aciklama'], 'teslim_tarihi' => $r['teslim_tarihi'], 'iade_tarihi' => $r['iade_tarihi'], 'durum' => $r['durum']];
    ikc(['ok' => true, 'kart' => $kart, 'duzenlenebilir' => $duz, 'maas_gor' => $maasGor, 'kendisi' => $kendisi, 'izin_ozet' => ik_izin_ozet($db, $id), 'bilgi_formu' => (function () use ($db, $id) { $q = $db->query("SELECT dosya, ham, guncelleme FROM ik_personel_form WHERE kullanici_id = " . intval($id)); $r = $q ? $q->fetch_assoc() : null; return $r ? ['dosya' => $r['dosya'], 'guncelleme' => $r['guncelleme'], 'ham' => json_decode((string)$r['ham'], true) ?: []] : null; })(),
        'izin_bakiye' => (function () use ($db, $id) { $q = $db->query("SELECT rapor_tarihi, ham FROM ik_izin_bakiye WHERE kullanici_id = " . intval($id)); $r = $q ? $q->fetch_assoc() : null; return $r ? ['rapor_tarihi' => $r['rapor_tarihi'], 'ham' => json_decode((string)$r['ham'], true) ?: []] : null; })(),
        'evrak_gizli' => ($evrakGizli = ($kendisi && !ik_ek_evrak_gor($db, $uid, $Y))), 'izinler' => $izinler, 'belgeler' => $evrakGizli ? [] : $belgeler, 'zimmetler' => $evrakGizli ? [] : $zimmetler, 'belge_yonet' => !empty($Y['ik_belge']), 'resim_yonet' => !empty($Y['ik_duzenle']), 'log_gor' => (!empty($Y['ik_personel_gor']) || !empty($Y['ik_duzenle']) || !empty($Y['kurucu']))]);
}

/* İzinler → Raporlar: bakiye ve kullanılan izin tabloları (yalnızca personel görme/izin onay yetkisi olanlar) */
if ($islem === 'izin_rapor') {
    if (empty($Y['ik_personel_gor']) && empty($Y['ik_izin_onay'])) ikh('Bu raporu görme yetkiniz yok.', 403);
    $tur = ($_GET['tur'] ?? 'bakiye') === 'kullanilan' ? 'kullanilan' : 'bakiye';
    if ($tur === 'bakiye') {
        $q = $db->query("SELECT k.id, k.ad_soyad, p.tc_kimlik, p.departman FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND p.isten_cikis IS NULL ORDER BY k.ad_soyad");
        $liste = [];
        if ($q) while ($r = $q->fetch_assoc()) {
            $o = ik_izin_ozet($db, intval($r['id']));
            $liste[] = ['id' => intval($r['id']), 'ad_soyad' => $r['ad_soyad'], 'tc' => (string)($r['tc_kimlik'] ?? ''), 'birim' => (string)($r['departman'] ?? ''),
                'devir' => (float)$o['devir'], 'hak' => (float)$o['hak'], 'toplam' => (float)$o['toplam'], 'kullanilan' => (float)$o['kullanilan'], 'bekleyen' => (float)$o['bekleyen'], 'kalan' => (float)$o['kalan'],
                'donem_bas' => $o['donem_bas'] ?? null, 'donem_bit' => $o['donem_bit'] ?? null];
        }
        ikc(['ok' => true, 'liste' => $liste]);
    }
    $yil = intval($_GET['yil'] ?? date('Y')); $ay = max(1, min(12, intval($_GET['ay'] ?? date('n'))));
    $ilk = sprintf('%04d-%02d-01', $yil, $ay); $son = date('Y-m-t', strtotime($ilk));
    $q = $db->query("SELECT t.tur, t.baslangic, t.bitis, t.yarim_gun, t.gun, t.aciklama, t.durum, k.ad_soyad, p.tc_kimlik FROM ik_izin_talepleri t JOIN kullanicilar k ON k.id = t.kullanici_id LEFT JOIN ik_personel p ON p.kullanici_id = k.id
                     WHERE t.durum IN ('onaylandi','beklemede','reddedildi') AND t.baslangic <= '$son' AND t.bitis >= '$ilk' ORDER BY t.baslangic, k.ad_soyad");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['ad_soyad' => $r['ad_soyad'], 'tc' => (string)($r['tc_kimlik'] ?? ''), 'tur' => IK_IZIN_TURLERI[$r['tur']] ?? $r['tur'], 'baslangic' => $r['baslangic'], 'bitis' => $r['bitis'],
        'gun' => (float)$r['gun'], 'aciklama' => (string)$r['aciklama'], 'durum' => $r['durum']];
    ikc(['ok' => true, 'yil' => $yil, 'ay' => $ay, 'liste' => $liste]);
}

if ($islem === 'izin_takvimi') {
    $yil = intval($_GET['yil'] ?? date('Y')); $ay = max(1, min(12, intval($_GET['ay'] ?? date('n'))));
    $ilk = sprintf('%04d-%02d-01', $yil, $ay); $son = date('Y-m-t', strtotime($ilk));
    $tumu = !empty($Y['ik_personel_gor']) || !empty($Y['ik_izin_onay']);
    $w = $tumu ? '1=1' : "t.kullanici_id = $uid";
    $q = $db->query("SELECT t.id, t.kullanici_id, t.tur, t.baslangic, t.bitis, t.durum, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_izin_talepleri t
                     JOIN kullanicilar k ON k.id = t.kullanici_id
                     WHERE t.durum IN ('onaylandi','beklemede') AND $w AND t.baslangic <= '$son' AND t.bitis >= '$ilk' ORDER BY t.baslangic");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'kid' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9',
        'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'tur' => IK_IZIN_TURLERI[$r['tur']] ?? $r['tur'], 'baslangic' => $r['baslangic'], 'bitis' => $r['bitis'], 'durum' => $r['durum']];
    ikc(['ok' => true, 'yil' => $yil, 'ay' => $ay, 'liste' => $liste]);
}

if ($islem === 'puantaj_ay') {
    $yil = intval($_GET['yil'] ?? date('Y')); $ay = max(1, min(12, intval($_GET['ay'] ?? date('n'))));
    $kid = intval($_GET['kullanici_id'] ?? $uid);
    if ($kid !== $uid && empty($Y['ik_puantaj_yonet']) && empty($Y['ik_personel_gor'])) ikh('Bu personelin puantajını görme yetkiniz yok.', 403);
    $gunSayi = intval(date('t', strtotime(sprintf('%04d-%02d-01', $yil, $ay))));
    $veriler = [];
    $q = $db->query("SELECT tarih, durum, not_metni FROM ik_puantaj WHERE kullanici_id = $kid AND YEAR(tarih) = $yil AND MONTH(tarih) = $ay");
    if ($q) while ($r = $q->fetch_assoc()) $veriler[$r['tarih']] = ['durum' => $r['durum'], 'not' => $r['not_metni']];
    $izinliGunler = [];
    $qi = $db->query("SELECT baslangic, bitis FROM ik_izin_talepleri WHERE kullanici_id = $kid AND durum = 'onaylandi' AND baslangic <= LAST_DAY('$yil-$ay-01') AND bitis >= '$yil-$ay-01'");
    if ($qi) while ($r = $qi->fetch_assoc()) for ($d = strtotime($r['baslangic']); $d <= strtotime($r['bitis']); $d += 86400) $izinliGunler[date('Y-m-d', $d)] = true;
    $gunler = [];
    for ($g = 1; $g <= $gunSayi; $g++) {
        $tarih = sprintf('%04d-%02d-%02d', $yil, $ay, $g);
        $haftaGun = intval(date('N', strtotime($tarih)));
        $varsayilan = $haftaGun >= 6 ? 'haftasonu' : (isset($izinliGunler[$tarih]) ? 'izinli' : null);
        $kayit = $veriler[$tarih] ?? null;
        $gunler[] = ['tarih' => $tarih, 'durum' => $kayit['durum'] ?? $varsayilan, 'not' => $kayit['not'] ?? '', 'elle' => $kayit !== null];
    }
    $personeller = [];
    if (!empty($Y['ik_puantaj_yonet']) || !empty($Y['ik_personel_gor'])) {
        $qp = $db->query("SELECT id, ad_soyad FROM kullanicilar WHERE aktif = 1 ORDER BY ad_soyad");
        if ($qp) while ($r = $qp->fetch_row()) $personeller[] = ['id' => intval($r[0]), 'ad' => $r[1]];
    }
    ikc(['ok' => true, 'yil' => $yil, 'ay' => $ay, 'kullanici_id' => $kid, 'gunler' => $gunler, 'personeller' => $personeller, 'durumlar' => IK_PUANTAJ_DURUM, 'yonet' => !empty($Y['ik_puantaj_yonet'])]);
}

if ($islem === 'masraflar') {
    $tumGorebilir = !empty($Y['ik_masraf_onay']);
    $kapsam = (string)($_GET['kapsam'] ?? 'benim');
    $w = ($kapsam === 'tum' && $tumGorebilir) ? '1=1' : "m.kullanici_id = $uid";
    $q = $db->query("SELECT m.*, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_masraflar m JOIN kullanicilar k ON k.id = m.kullanici_id WHERE $w ORDER BY (m.durum='beklemede') DESC, m.tarih DESC LIMIT 300");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'kid' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9',
        'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'kategori' => $r['kategori'], 'kategori_ad' => IK_MASRAF_KATEGORI[$r['kategori']] ?? $r['kategori'],
        'tutar' => (float)$r['tutar'], 'tarih' => $r['tarih'], 'aciklama' => $r['aciklama'], 'durum' => $r['durum'], 'onay_notu' => $r['onay_notu']];
    ikc(['ok' => true, 'liste' => $liste, 'tum_gorebilir' => $tumGorebilir, 'kategoriler' => IK_MASRAF_KATEGORI]);
}

if ($islem === 'avanslar') {
    $tumGorebilir = !empty($Y['ik_avans_onay']);
    $kapsam = (string)($_GET['kapsam'] ?? 'benim');
    $w = ($kapsam === 'tum' && $tumGorebilir) ? '1=1' : "m.kullanici_id = $uid";
    $q = $db->query("SELECT m.*, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_avanslar m JOIN kullanicilar k ON k.id = m.kullanici_id WHERE $w ORDER BY (m.durum='beklemede') DESC, m.id DESC LIMIT 300");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'kid' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9',
        'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'tutar' => (float)$r['tutar'], 'taksit' => intval($r['taksit']), 'tarih' => $r['istenen_tarih'], 'aciklama' => $r['aciklama'], 'durum' => $r['durum'], 'onay_notu' => $r['onay_notu']];
    ikc(['ok' => true, 'liste' => $liste, 'tum_gorebilir' => $tumGorebilir]);
}

if ($islem === 'birimler') {
    $say = []; $ad = [];
    $av = [];
    $q = $db->query("SELECT p.departman, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND (p.isten_cikis IS NULL) ORDER BY k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) { $d = trim((string)$r['departman']); if ($d === '') continue; $k = mb_strtolower($d, 'UTF-8'); $ad[$k] = $ad[$k] ?? $d; $say[$k] = ($say[$k] ?? 0) + 1; $av[$k][] = ['ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? '')]; }
    foreach (preg_split('/\R/u', (string)ik_ayar($db, 'departman_listesi', '')) as $d) { $d = trim($d); if ($d === '') continue; $k = mb_strtolower($d, 'UTF-8'); $ad[$k] = $ad[$k] ?? $d; $say[$k] = $say[$k] ?? 0; }
    ik_birim_yon_sema($db);
    $md = [];
    $q = $db->query("SELECT y.birim, k.id, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_birim_yoneticileri y JOIN kullanicilar k ON k.id = y.kullanici_id WHERE k.aktif = 1 ORDER BY y.tarih, k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) $md[mb_strtolower(trim((string)$r['birim']), 'UTF-8')][] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? '')];
    $l = []; foreach ($ad as $k => $d) $l[] = ['ad' => $d, 'n' => $say[$k], 'uyeler' => array_slice($av[$k] ?? [], 0, 4), 'mudurler' => $md[$k] ?? []];
    usort($l, function ($a, $b) { return strcasecmp($a['ad'], $b['ad']); });
    ikc(['ok' => true, 'liste' => $l]);
}

if ($islem === 'unvanlar') {
    $say = []; $ad = []; $av = [];
    $q = $db->query("SELECT p.unvan, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND (p.isten_cikis IS NULL) ORDER BY k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) { $d = trim((string)$r['unvan']); if ($d === '') continue; $k = mb_strtolower($d, 'UTF-8'); $ad[$k] = $ad[$k] ?? $d; $say[$k] = ($say[$k] ?? 0) + 1; $av[$k][] = ['ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? '')]; }
    foreach (ik_liste_oku($db, 'unvan_listesi') as $d) { $k = mb_strtolower($d, 'UTF-8'); $ad[$k] = $ad[$k] ?? $d; $say[$k] = $say[$k] ?? 0; }
    $l = []; foreach ($ad as $k => $d) $l[] = ['ad' => $d, 'n' => $say[$k], 'uyeler' => array_slice($av[$k] ?? [], 0, 4)];
    usort($l, function ($a, $b) { return strcasecmp($a['ad'], $b['ad']); });
    ikc(['ok' => true, 'liste' => $l]);
}
if ($islem === 'birim_uyeler') {
    if (empty($Y['ik_personel_gor'])) ikh('Bu bilgiyi görme yetkiniz yok.', 403);
    $b = trim((string)($_GET['birim'] ?? ''));
    $l = [];
    $q = $db->query("SELECT k.id, k.ad_soyad, k.gorev, k.avatar_renk, k.profil_resmi, p.departman, p.unvan FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND (p.isten_cikis IS NULL) ORDER BY k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) {
        $d = trim((string)$r['departman']);
        if (mb_strtolower($d, 'UTF-8') !== mb_strtolower($b, 'UTF-8')) continue;
        $l[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'unvan' => (string)($r['unvan'] ?: $r['gorev']), 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? '')];
    }
    ikc(['ok' => true, 'liste' => $l]);
}

if ($islem === 'bekleyenler') {
    $o = ['ok' => true, 'masraf' => [], 'avans' => [], 'masraf_yetki' => !empty($Y['ik_masraf_onay']), 'avans_yetki' => !empty($Y['ik_avans_onay'])];
    if ($o['masraf_yetki']) { $q = $db->query("SELECT m.id, m.tutar, m.kategori, m.tarih, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_masraflar m JOIN kullanicilar k ON k.id = m.kullanici_id WHERE m.durum = 'beklemede' ORDER BY m.id DESC LIMIT 6");
        if ($q) while ($r = $q->fetch_assoc()) $o['masraf'][] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'tutar' => (float)$r['tutar'], 'bilgi' => IK_MASRAF_KATEGORI[$r['kategori']] ?? $r['kategori'], 'tarih' => $r['tarih']];
        $o['masraf_say'] = intval($db->query("SELECT COUNT(*) FROM ik_masraflar WHERE durum = 'beklemede'")->fetch_row()[0]); }
    if ($o['avans_yetki']) { $q = $db->query("SELECT m.id, m.tutar, m.taksit, m.istenen_tarih, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_avanslar m JOIN kullanicilar k ON k.id = m.kullanici_id WHERE m.durum = 'beklemede' ORDER BY m.id DESC LIMIT 6");
        if ($q) while ($r = $q->fetch_assoc()) $o['avans'][] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'tutar' => (float)$r['tutar'], 'bilgi' => intval($r['taksit']) > 1 ? $r['taksit'] . ' taksit' : 'Tek seferde', 'tarih' => $r['istenen_tarih']];
        $o['avans_say'] = intval($db->query("SELECT COUNT(*) FROM ik_avanslar WHERE durum = 'beklemede'")->fetch_row()[0]); }
    ikc($o);
}

if (strncmp($islem, 'yedek_', 6) === 0) {                      // yedek / geri yükleme talebi
    require_once __DIR__ . '/kurum_yedek.php'; yd_sema($db);
    $destek = !empty($_SESSION['destek']) && !empty($_SESSION['pf_yonetici_id']);
    $kidY = intval($db->kurum);
    if (empty($Y['kurucu']) && !$destek) ikh('Bu bölüm yalnızca kurum yetkilisine açıktır.', 403);
    if ($islem === 'yedek_bilgi') {
        $liste = yd_liste($db, $kidY); $db->kurum = $kidY;
        $ozet = array_map(function ($r) { return ['id' => $r['id'], 'olusturma' => $r['olusturma'], 'tur' => $r['tur']]; }, $liste);
        ikc(['ok' => true, 'destek' => $destek, 'son' => yd_son_yedek($db, $kidY), 'yedekler' => $destek ? $liste : $ozet, 'islemler' => $destek ? yd_islemler($db, $kidY) : [], 'talepler' => yd_talepler($db, $kidY), 'aralik' => pf_ayar($db, 'yedek_aralik', '7'),
            'iletisim' => pf_ayar($db, 'iletisim_eposta') ?: pf_ayar($db, 'destek_mail')]);
    }
    if ($islem === 'yedek_dosya') { if (!$destek) ikh('Yetkisiz.', 403); yd_indir($db, intval($girdi['id'] ?? 0), $kidY); }
    ik_csrf($girdi);
    if ($islem === 'yedek_talep') {
        $yid = intval($girdi['yedek_id'] ?? 0);
        if ($yid) { $r = yd_kayit($db, $yid, $kidY); if (!$r) ikh('Seçilen yedek bulunamadı.'); $db->kurum = $kidY; }
        try { $id = yd_talep_ac($db, $kidY, $uid, (string)($_SESSION['ad_soyad'] ?? ''), $yid ?: null, trim((string)($girdi['mesaj'] ?? ''))); } catch (Throwable $e) { $db->kurum = $kidY; ikh($e->getMessage()); }
        $db->kurum = $kidY; ikc(['ok' => true, 'id' => $id]);
    }
    if ($islem === 'yedek_talep_iptal') {
        $tid = intval($girdi['id'] ?? 0); yd_k0($db, function () use ($db, $tid, $kidY) { $db->query("UPDATE pf_yedek_talepleri SET durum = 'iptal', islem_zamani = NOW() WHERE id = $tid AND kurum_id = $kidY AND durum = 'bekliyor'"); });
        ikc(['ok' => true]);
    }
    if (!$destek) ikh('Yedek alma ve geri yükleme işlemleri için yedek geri yükleme talebi oluşturun.', 403);
    $r = yd_api($db, $islem, $kidY); if ($r === null) ikh('Bilinmeyen işlem.');
    if (!empty($r['ok']) && in_array($islem, ['yedek_al', 'yedek_uygula'], true)) pf_log($db, 'destek_' . $islem, '', $kidY);
    ikc($r, !empty($r['ok']) ? 200 : 400);
}
if ($islem === 'egitimler') {
    require_once __DIR__ . '/ik_etkinlik.php'; ik_etkinlik_sema($db);
    $q = $db->query("SELECT * FROM ik_egitimler ORDER BY (tarih IS NULL), tarih DESC, id DESC");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) {
        $kat = [];
        $qk = $db->query("SELECT ek.kullanici_id, ek.tamamlandi, k.ad_soyad FROM ik_egitim_katilim ek JOIN kullanicilar k ON k.id = ek.kullanici_id WHERE ek.egitim_id = " . intval($r['id']));
        $benimDurum = null;
        if ($qk) while ($kr = $qk->fetch_assoc()) { $kat[] = ['id' => intval($kr['kullanici_id']), 'ad' => $kr['ad_soyad'], 'tamamlandi' => (bool)$kr['tamamlandi']]; if (intval($kr['kullanici_id']) === $uid) $benimDurum = (bool)$kr['tamamlandi']; }
        $liste[] = ['id' => intval($r['id']), 'ad' => $r['ad'], 'egitmen' => $r['egitmen'], 'tarih' => $r['tarih'], 'sure_saat' => (float)$r['sure_saat'], 'aciklama' => $r['aciklama'], 'katilimcilar' => $kat, 'benim_durum' => $benimDurum,
            'tur' => $r['tur'], 'saat' => $r['saat'], 'yer' => $r['yer'], 'bildirim_ac' => intval($r['bildirim_ac']) === 1, 'bildirim_sms' => intval($r['bildirim_sms']) === 1,
            'bildirim_mail' => intval($r['bildirim_mail']) === 1, 'bildirim_gunler' => ik_etkinlik_gunler($r['bildirim_gunler'])];
    }
    ikc(['ok' => true, 'liste' => $liste, 'yonet' => !empty($Y['ik_egitim_yonet']), 'sms_hazir' => ik_sms_hazir($db), 'mail_hazir' => ik_mail_hazir($db)]);
}

if ($islem === 'performans') {
    $tumGorebilir = !empty($Y['ik_personel_gor']) || !empty($Y['ik_performans_yonet']);
    $kid = ($tumGorebilir && !empty($_GET['kullanici_id'])) ? intval($_GET['kullanici_id']) : $uid;
    $q = $db->query("SELECT p.*, d.ad_soyad degerlendiren FROM ik_performans p LEFT JOIN kullanicilar d ON d.id = p.degerlendiren_id WHERE p.kullanici_id = $kid ORDER BY p.tarih DESC");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'donem' => $r['donem'], 'puan' => (float)$r['puan'], 'degerlendiren' => $r['degerlendiren'], 'notlar' => $r['notlar'], 'tarih' => $r['tarih']];
    ikc(['ok' => true, 'liste' => $liste, 'yonet' => !empty($Y['ik_performans_yonet']), 'kullanici_id' => $kid]);
}

if ($islem === 'bordro') {
    $tumGorebilir = !empty($Y['ik_maas']);
    $kid = ($tumGorebilir && !empty($_GET['kullanici_id'])) ? intval($_GET['kullanici_id']) : $uid;
    $q = $db->query("SELECT * FROM ik_bordrolar WHERE kullanici_id = $kid ORDER BY yil DESC, ay DESC");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'yil' => intval($r['yil']), 'ay' => intval($r['ay']), 'brut' => (float)$r['brut'], 'net' => (float)$r['net'],
        'kesinti' => (float)$r['kesinti'], 'dosya' => $r['dosya'] ? ('ik_islem.php?islem=bordro_indir&id=' . intval($r['id'])) : null];
    ikc(['ok' => true, 'liste' => $liste, 'yonet' => !empty($Y['ik_bordro_yonet']), 'kullanici_id' => $kid]);
}

if ($islem === 'bordro_indir') {
    $id = intval($_GET['id'] ?? 0);
    $r = $db->query("SELECT * FROM ik_bordrolar WHERE id = $id")->fetch_assoc();
    if (!$r) { http_response_code(404); exit('Bulunamadı'); }
    if (intval($r['kullanici_id']) !== $uid && empty($Y['ik_maas'])) { http_response_code(403); exit('Yetkisiz'); }
    $yol = tb_yukleme_dizin('ik_bordro') . '/' . basename($r['dosya']);
    if (!$r['dosya'] || !is_file($yol)) { http_response_code(404); exit('Dosya yok'); }
    header('Content-Type: application/pdf'); header('Content-Disposition: inline; filename="bordro_' . $r['yil'] . '_' . $r['ay'] . '.pdf"');
    readfile($yol); exit;
}

if ($islem === 'ayarlar_getir') {
    if (empty($Y['ik_ayarlar']) && empty($Y['ik_bildirim_yonet'])) ikh('İK ayarlarını görme yetkiniz yok.', 403);
    $ayar = [];
    $q = $db->query("SELECT anahtar, deger FROM ik_ayarlar");
    if ($q) while ($r = $q->fetch_assoc()) if (!in_array($r['anahtar'], IK_GIZLI_AYARLAR, true) && !in_array($r['anahtar'], ['vapid_public', 'sema_v3'], true) && !in_array($r['anahtar'], IK_KANAL_AYARLARI, true)) $ayar[$r['anahtar']] = $r['deger'];
    ikc(['ok' => true, 'ayar' => $ayar, 'ayarlar_yonet' => !empty($Y['ik_ayarlar']), 'bildirim_yonet' => !empty($Y['ik_bildirim_yonet'])]);
}

if ($islem === 'zimmet_tum') {
    if (empty($Y['ik_belge'])) ikh('Tüm zimmetleri görme yetkiniz yok.', 403);
    $q = $db->query("SELECT z.*, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_zimmetler z JOIN kullanicilar k ON k.id = z.kullanici_id ORDER BY (z.durum='zimmetli') DESC, z.teslim_tarihi DESC LIMIT 500");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'kid' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9',
        'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'esya' => $r['esya'], 'seri_no' => $r['seri_no'], 'durum' => $r['durum'], 'teslim_tarihi' => $r['teslim_tarihi'], 'iade_tarihi' => $r['iade_tarihi']];
    ikc(['ok' => true, 'liste' => $liste]);
}

if ($islem === 'belge_tum') {
    if (empty($Y['ik_belge'])) ikh('Tüm özlük evraklarını görme yetkiniz yok.', 403);
    $q = $db->query("SELECT b.*, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_belgeler b JOIN kullanicilar k ON k.id = b.kullanici_id ORDER BY b.tarih DESC LIMIT 500");
    $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'kid' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9',
        'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'tur_ad' => IK_BELGE_TURLERI[$r['tur']] ?? $r['tur'], 'ad_belge' => $r['ad'] ?: (IK_BELGE_TURLERI[$r['tur']] ?? ''),
        'gecerlilik' => $r['gecerlilik'], 'tarih' => $r['tarih'], 'url' => 'ik_islem.php?islem=belge&id=' . intval($r['id'])];
    ikc(['ok' => true, 'liste' => $liste]);
}

if ($islem === 'izin_pdf') {                                   // izin belgesi (PDF) — talep sahibi ya da yetkili indirir
    require_once __DIR__ . '/ik_pdf.php';
    $tid = intval($girdi['id'] ?? 0);
    $q = $db->query("SELECT t.*, k.ad_soyad, o.ad_soyad AS onaylayan, v.ad_soyad AS vekil FROM ik_izin_talepleri t JOIN kullanicilar k ON k.id = t.kullanici_id LEFT JOIN kullanicilar o ON o.id = t.onaylayan_id LEFT JOIN kullanicilar v ON v.id = t.vekil_id WHERE t.id = $tid");
    $t = $q ? $q->fetch_assoc() : null;
    if (!$t) { http_response_code(404); exit('Talep bulunamadı'); }
    if (intval($t['kullanici_id']) !== $uid && empty($Y['ik_izin_onay']) && empty($Y['ik_personel_gor']) && empty($Y['kurucu'])) { http_response_code(403); exit('Yetkisiz'); }
    $DUR = ['beklemede' => 'Onay bekliyor', 'onaylandi' => 'Onaylandı', 'reddedildi' => 'Reddedildi', 'iptal' => 'İptal edildi'];
    $satir = [['Personel', $t['ad_soyad']], ['İzin türü', IK_IZIN_TURLERI[$t['tur']] ?? $t['tur']], ['Başlangıç tarihi', ik_tarih_uzun($t['baslangic'])], ['Bitiş tarihi', ik_tarih_uzun($t['bitis'])],
        ['İşe dönüş tarihi', ik_tarih_uzun(ik_ise_donus($t['bitis']))], ['Süre', !empty($t['yarim_gun']) ? 'Yarım gün' : ik_gun_yaz($t['gun']) . ' iş günü'], ['Durum', $DUR[$t['durum']] ?? $t['durum']]];
    if ($t['vekil']) $satir[] = ['Vekil', $t['vekil']];
    if ($t['onaylayan'] && $t['durum'] !== 'beklemede') $satir[] = [$t['durum'] === 'onaylandi' ? 'Onaylayan' : 'Karar veren', $t['onaylayan'] . ($t['onay_tarihi'] ? ' · ' . date('d.m.Y H:i', strtotime($t['onay_tarihi'])) : '')];
    if (trim((string)$t['onay_notu']) !== '') $satir[] = ['Onay notu', $t['onay_notu']];
    if (trim((string)$t['aciklama']) !== '') $satir[] = ['Açıklama', $t['aciklama']];
    $logo = trim((string)ik_ayar($db, 'mail_logo', '')); $firma = trim((string)ik_ayar($db, 'mail_firma_ad', '')) ?: 'İNSAN KAYNAKLARI';
    $pdf = ik_pdf_belge(['firma' => $firma, 'renk' => ik_ayar($db, 'mail_renk', '#6366f1'), 'renk2' => ik_ayar($db, 'mail_renk2', ''), 'logo_dosya' => preg_match('~^uploads/k\d+/ik_logo/[A-Za-z0-9._-]+$~', $logo) ? TB_KOK . '/' . $logo : '',
        'baslik' => 'İzin belgesi', 'paragraflar' => ['Talep no: #' . $tid . ' · Oluşturma: ' . date('d.m.Y', strtotime($t['olusturma_tarihi']))], 'satirlar' => $satir, 'imza' => trim((string)ik_ayar($db, 'mail_imza', '')) ?: $firma]);
    header('Content-Type: application/pdf'); header('Content-Length: ' . strlen($pdf)); header('Cache-Control: private, no-store'); header('Content-Disposition: attachment; filename="izin_belgesi_' . $tid . '.pdf"');
    echo $pdf; exit;
}
if ($islem === 'izinler') {
    $kapsam = (string)($_GET['kapsam'] ?? 'benim');
    $tumu = !empty($Y['ik_izin_onay']) || !empty($Y['ik_personel_gor']);
    $w = [];
    if ($kapsam === 'bana') $w[] = ik_bana_bekleyen_kosul($uid, $Y);
    elseif ($kapsam !== 'tum' || !$tumu) $w[] = "t.kullanici_id = $uid";
    $d = (string)($_GET['durum'] ?? '');
    if (in_array($d, ['beklemede', 'onaylandi', 'reddedildi', 'iptal'], true)) $w[] = "t.durum = '$d'";
    $yil = intval($_GET['yil'] ?? 0); if ($yil) $w[] = "YEAR(t.baslangic) = $yil";
    $w = $w ? implode(' AND ', $w) : '1=1';
    $q = $db->query(IK_IZIN_SORGU . " WHERE $w ORDER BY (t.durum = 'beklemede') DESC, t.baslangic DESC LIMIT 300");
    ikc(['ok' => true, 'liste' => ik_izin_satirlari($db, $q, $uid, $Y), 'tumu_gorebilir' => $tumu, 'bana_bekleyen' => ik_bana_bekleyen($db, $uid, $Y)]);
}

if ($islem === 'izin_zincir') {
    $kid = (!empty($Y['ik_izin_onay']) && !empty($_GET['kullanici_id'])) ? intval($_GET['kullanici_id']) : $uid;
    $a = ik_akis_cozumle($db, $kid);
    ikc(['ok' => true, 'adimlar' => array_map(function ($x) { return ['ad' => $x['ad'], 'sms' => $x['sms'], 'mail' => $x['mail']]; }, $a['adimlar']), 'departman' => $a['departman'], 'akis_var' => $a['akis_var']]);
}

if ($islem === 'izin_detay') {
    $id = intval($_GET['id'] ?? 0);
    $t = $db->query("SELECT * FROM ik_izin_talepleri WHERE id = $id")->fetch_assoc();
    if (!$t) ikh('Talep bulunamadı.', 404);
    $adimdaMi = intval($db->query("SELECT COUNT(*) FROM ik_izin_adimlari WHERE talep_id = $id AND onaylayici_id = $uid")->fetch_row()[0]) > 0;
    if (intval($t['kullanici_id']) !== $uid && !$adimdaMi && empty($Y['ik_izin_onay']) && empty($Y['ik_personel_gor'])) ikh('Bu talebi görme yetkiniz yok.', 403);
    $q = $db->query(IK_IZIN_SORGU . " WHERE t.id = $id");
    $l = ik_izin_satirlari($db, $q, $uid, $Y);
    ikc(['ok' => true, 'talep' => $l[0]]);
}

if ($islem === 'belge') {
    $id = intval($_GET['id'] ?? 0);
    $r = $db->query("SELECT * FROM ik_belgeler WHERE id = $id")->fetch_assoc();
    if (!$r) { http_response_code(404); exit('Belge bulunamadı'); }
    if ((!ik_gorebilir($Y, $uid, $r['kullanici_id']) && empty($Y['ik_belge'])) || (intval($r['kullanici_id']) === $uid && !ik_ek_evrak_gor($db, $uid, $Y))) { http_response_code(403); exit('Yetkisiz'); }
    $yol = $BELGE_KLASOR . '/' . basename($r['dosya']);
    if (!is_file($yol)) { http_response_code(404); exit('Dosya yok'); }
    $inline = preg_match('#^(image/|application/pdf)#', $r['mime']);
    header('Content-Type: ' . ($r['mime'] ?: 'application/octet-stream'));
    header('Content-Length: ' . filesize($yol));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename*=UTF-8\'\'' . rawurlencode($r['orijinal_ad'] ?: 'belge'));
    readfile($yol); exit;
}

// ───────────── İzin onay akışı + bildirim kanalları (yetki: ik_akis_yonet) ─────────────
if (in_array($islem, ['akis_getir', 'akis_onizle', 'bildirim_ayar_getir', 'bildirim_log', 'bildirim_onizle'], true)) {
    if (empty($Y['ik_akis_yonet'])) ikh('Onay akışı ve bildirim kanallarını yönetme yetkiniz yok.', 403);
}
if ($islem === 'akis_getir') {
    $say = []; $ad = [];
    $q = $db->query("SELECT p.departman, k.gorev FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND p.isten_cikis IS NULL");
    $kucuk = function ($x) { return mb_strtolower(trim((string)$x), 'UTF-8'); };
    if ($q) while ($r = $q->fetch_assoc()) { $d = ik_departman($r); $k = $kucuk($d); $ad[$k] = $ad[$k] ?? $d; $say[$k] = ($say[$k] ?? 0) + 1; }
    foreach (preg_split('/\R/u', (string)ik_ayar($db, 'departman_listesi', '')) as $d) { $d = trim($d); if ($d === '') continue; $k = $kucuk($d); $ad[$k] = $ad[$k] ?? $d; $say[$k] = $say[$k] ?? 0; }
    $akislar = [];
    $q = $db->query("SELECT * FROM ik_onay_akis ORDER BY departman, sira, id");
    if ($q) while ($r = $q->fetch_assoc()) { $k = $kucuk($r['departman']); if ($k !== IK_AKIS_VARSAYILAN) $ad[$k] = $ad[$k] ?? $r['departman']; $say[$k] = $say[$k] ?? 0;
        $akislar[$k]['adimlar'][] = ['id' => intval($r['onaylayici_id']), 'vekil_id' => intval($r['vekil_id'] ?? 0), 'site' => (bool)$r['k_site'], 'sms' => (bool)$r['k_sms'], 'mail' => (bool)$r['k_mail']]; }
    $q = $db->query("SELECT * FROM ik_onay_bildirim ORDER BY id");
    if ($q) while ($r = $q->fetch_assoc()) { $k = $kucuk($r['departman']); if ($k !== IK_AKIS_VARSAYILAN) $ad[$k] = $ad[$k] ?? $r['departman']; $say[$k] = $say[$k] ?? 0;
        $kn = ['site' => (bool)$r['k_site'], 'sms' => (bool)$r['k_sms'], 'mail' => (bool)$r['k_mail']];
        if ($r['rol'] === 'talep_sahibi') $akislar[$k]['talep_sahibi'] = $kn; else $akislar[$k]['bilgi'][] = ['id' => intval($r['kullanici_id'])] + $kn; }
    $dizi = [];
    foreach ($ad as $k => $d) $dizi[] = ['ad' => $d, 'n' => $say[$k] ?? 0, 'akis' => !empty($akislar[$k]['adimlar'])];
    usort($dizi, function ($a, $b) { return strcmp(mb_strtolower($a['ad'], 'UTF-8'), mb_strtolower($b['ad'], 'UTF-8')); });
    $son = [];
    foreach ($akislar as $k => $v) { $son[$k === IK_AKIS_VARSAYILAN ? IK_AKIS_VARSAYILAN : $ad[$k]] = $v + ['adimlar' => [], 'talep_sahibi' => ['site' => true, 'sms' => false, 'mail' => false], 'bilgi' => []]; }
    $kisiler = [];
    $q = $db->query("SELECT k.id, k.ad_soyad, k.gorev, k.telefon, k.email, p.departman FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 ORDER BY k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) $kisiler[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'gorev' => (string)($r['gorev'] ?? ''), 'departman' => ik_departman($r), 'tel' => ik_telefon_norm($r['telefon']) !== '', 'mail' => filter_var($r['email'], FILTER_VALIDATE_EMAIL) !== false];
    ikc(['ok' => true, 'gorunum' => (string)ik_ayar($db, 'akis_gorunum_u' . $uid, ''), 'departmanlar' => $dizi, 'akislar' => (object)$son, 'kisiler' => $kisiler, 'sms_hazir' => ik_sms_hazir($db), 'mail_hazir' => ik_mail_hazir($db)]);
}
if ($islem === 'akis_onizle') {
    $kid = intval($_GET['kullanici_id'] ?? 0);
    if (!$db->query("SELECT 1 FROM kullanicilar WHERE id = $kid")->num_rows) ikh('Personel bulunamadı.', 404);
    ikc(['ok' => true, 'sonuc' => ik_akis_cozumle($db, $kid)]);
}
if ($islem === 'bildirim_ayar_getir') {
    $a = [];
    foreach (IK_KANAL_AYARLARI as $k) if (!in_array($k, IK_GIZLI_AYARLAR, true)) $a[$k] = ik_ayar($db, $k, '');
    $a['sms_saglayici'] = $a['sms_saglayici'] ?: ''; $a['smtp_port'] = $a['smtp_port'] ?: '587'; $a['smtp_guvenlik'] = ik_ayar($db, 'smtp_guvenlik', 'tls');
    $abone = intval($db->query("SELECT COUNT(DISTINCT kullanici_id) FROM ik_push_aboneler")->fetch_row()[0] ?? 0);
    ikc(['ok' => true, 'ayar' => $a, 'sms_sifre_var' => ik_ayar($db, 'sms_sifre', '') !== '', 'smtp_sifre_var' => ik_ayar($db, 'smtp_sifre', '') !== '', 'sms_hazir' => ik_sms_hazir($db), 'mail_hazir' => ik_mail_hazir($db),
        'site_url_otomatik' => ik_site_url(null), 'push_abone' => $abone, 'push_hazir' => ik_vapid($db) !== null, 'gonderen' => ik_mail_gonderen($db)[0]]);
}
if ($islem === 'bildirim_onizle') {
    $url = ik_site_url($db);
    $t = ['baslangic' => date('Y-m-d', strtotime('+10 days')), 'bitis' => date('Y-m-d', strtotime('+14 days')), 'tur' => 'yillik', 'gun' => 5, 'aciklama' => 'Aile ziyareti'];
    $tarih = ik_izin_tarih_yaz($t['baslangic'], $t['bitis']); $detay = ik_izin_detay_html($t, 'Ayşe Demir', 'Burak Şen');
    $ornek = [
        ['ad' => 'Yeni izin talebi (ilk onaycıya)', 'konu' => 'Yeni izin talebi — onayınız bekleniyor', 'html' => '<b>Ayşe Demir</b> ' . $tarih . ' için <b>Yıllık izin</b> talep etti (5 gün). <b>Onayınız bekleniyor.</b>', 'sms' => 'Ayşe Demir ' . $tarih . ' için Yıllık izin talep etti (5 gün). Onayınız bekleniyor:', 'buton' => 'Talebi incele ve karar ver', 'link' => 'ik.php?sekme=izinler&talep=7', 'detay' => $detay],
        ['ad' => 'Sıradaki onaycıya (öncekinin onayından sonra)', 'konu' => 'İzin talebi sizin onayınızı bekliyor', 'html' => '<b>Ali Yılmaz</b>, <b>Ayşe Demir</b> adlı personelin ' . $tarih . ' tarihli <b>Yıllık izin</b> talebini onayladı (5 gün). <b>Sizin onayınızı bekliyor.</b>', 'sms' => 'Ali Yılmaz, Ayşe Demir adlı personelin ' . $tarih . ' tarihli Yıllık izin talebini onayladı. Sizin onayınızı bekliyor:', 'buton' => 'Talebi incele ve karar ver', 'link' => 'ik.php?sekme=izinler&talep=7', 'detay' => $detay],
        ['ad' => 'Talep sahibine: izin onaylandı', 'konu' => 'İzniniz onaylandı', 'html' => 'Merhaba <b>Ayşe</b>, ' . $tarih . ' tarihli <b>Yıllık izin</b> talebiniz tüm onaylardan geçti. İyi dinlenmeler!', 'sms' => 'İzniniz onaylandı: ' . $tarih . ' tarihli Yıllık izin talebiniz tüm onaylardan geçti. İyi dinlenmeler!', 'buton' => 'Talebi görüntüle', 'link' => 'ik.php?sekme=izinler&talep=7', 'detay' => $detay],
        ['ad' => 'Talep sahibine: izin reddedildi', 'konu' => 'İzin talebiniz reddedildi', 'html' => '<b>Ali Yılmaz</b> ' . $tarih . ' tarihli talebinizi reddetti.<br><i>Not: O hafta ekip yoğun, başka tarih seçin.</i>', 'sms' => 'İzin talebiniz reddedildi: ' . $tarih . ' Yıllık izin — Ali Yılmaz. Not: O hafta ekip yoğun', 'buton' => 'Talebi görüntüle', 'link' => 'ik.php?sekme=izinler&talep=7', 'detay' => $detay],
    ];
    foreach ($ornek as &$o) { $o['mail'] = ik_mail_sablon($o['konu'], $o['html'] . $o['detay'], $url . $o['link'], $o['buton'], $db, 'Ayşe'); if (!empty($GLOBALS['ik_mail_cid']['url'])) $o['mail'] = str_replace('cid:logo@turkuaz.ik', $GLOBALS['ik_mail_cid']['url'], $o['mail']); $o['sms_tam'] = $o['sms'] . ' ' . $url . $o['link']; unset($o['detay'], $o['html']); }
    ikc(['ok' => true, 'ornekler' => $ornek, 'sms_baslik' => ik_ayar($db, 'sms_baslik', '')]);
}
if ($islem === 'bildirim_log') {
    $liste = [];
    $q = $db->query("SELECT * FROM ik_bildirim_log ORDER BY id DESC LIMIT 60");
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'zaman' => $r['zaman'], 'kanal' => $r['kanal'], 'alici' => $r['alici'], 'konu' => $r['konu'], 'ok' => (bool)$r['basarili'], 'sonuc' => $r['sonuc']];
    ikc(['ok' => true, 'liste' => $liste]);
}

// ───────────────────────── Kullanıcı yönetimi (kurum yetkilisi ya da ik_kullanici_yonet yetkisi) ─────────────────────────
if (in_array($islem, ['kullanicilar', 'kullanici_kaydet', 'kullanici_durum', 'kullanici_sifre', 'kullanici_sifre_gonder', 'kullanici_sms', 'kullanici_ice_aktar', 'kullanici_yonetici_esle', 'personel_disa_aktar', 'izin_ice_aktar', 'izin_bakiye_aktar', 'personel_form_aktar'], true)) {
    if (empty($Y['ik_kullanici_yonet'])) ikh('Kullanıcıları yönetme yetkiniz yok.', 403);
    $ben_kurucu = !empty($Y['kurucu']);
    $kurum_ad = ($GLOBALS['KURUM']['ad'] ?? '');
    $sinir = intval($GLOBALS['KURUM']['kullanici_siniri'] ?? 0);
    $kurucu_say = function () use ($db) { $q = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'kurucu' AND aktif = 1"); return $q ? intval($q->fetch_row()[0]) : 0; };
    $satir = function ($id) use ($db) { $id = intval($id); $q = $db->query("SELECT id, kullanici_adi, ad_soyad, rol, gorev, aktif FROM kullanicilar WHERE id = $id"); return $q ? $q->fetch_assoc() : null; };

    /** Giriş bilgisini SMS ve/veya e-posta ile gönderir → [sms=>[ok,sonuc]|null, mail=>[ok,sonuc]|null] */
    $giris_gonder = function ($m, $yeni, $kanal) use ($db, $kurum_ad) {
        $kod = (string)($GLOBALS['KURUM']['kod'] ?? '');
        $adres = ik_site_url($db) . 'giris.php' . ($kod !== '' ? '?kurum=' . rawurlencode($kod) : '');
        $ilk = trim(explode(' ', (string)$m['ad_soyad'])[0]); $id = intval($m['id']); $o = ['sms' => null, 'mail' => null];
        if ($kanal === 'sms' || $kanal === 'ikisi') {
            $msj = 'Merhaba ' . $ilk . ', ' . ($kurum_ad !== '' ? $kurum_ad . ' ' : '') . 'şifreniz yenilendi' . "\n" . 'Adres: ' . $adres . "\n" . 'Kullanıcı adı: ' . $m['kullanici_adi'] . "\n" . 'Geçici şifre: ' . $yeni . "\n" . 'İlk girişte yeni şifre belirlemeniz istenecek.';
            if (!ik_sms_hazir($db)) $o['sms'] = [false, 'SMS ayarları yapılmamış'];
            elseif (ik_telefon_norm($m['telefon'] ?? '') === '') $o['sms'] = [false, 'Geçerli telefon numarası yok'];
            else { $g = ik_sms_gonder($db, $m['telefon'], $msj); ik_kanal_log($db, $id, 'sms', $m['telefon'], 'Giriş bilgisi', $g['ok'], $g['sonuc']); $o['sms'] = [(bool)$g['ok'], (string)$g['sonuc']]; }
        }
        if ($kanal === 'mail' || $kanal === 'ikisi') {
            if (!ik_mail_hazir($db)) $o['mail'] = [false, 'E-posta ayarları yapılmamış'];
            elseif (!filter_var($m['email'] ?? '', FILTER_VALIDATE_EMAIL)) $o['mail'] = [false, 'Geçerli e-posta adresi yok'];
            else {
                $govde = 'Merhaba <b>' . ik_h($ilk) . '</b>,<br><br>' . ik_h($kurum_ad) . ' hesabınızın şifresi yenilendi. Giriş bilgileriniz:<br><br><b>Kullanıcı adı:</b> ' . ik_h($m['kullanici_adi']) . '<br><b>Geçici şifre:</b> <code style="font-size:16px">' . ik_h($yeni) . '</code><br><br>İlk girişte kendi şifrenizi belirlemeniz istenecek.';
                $g = ik_mail_gonder($db, $m['email'], 'Giriş bilgileriniz', ik_mail_sablon('Şifreniz yenilendi', $govde, $adres, 'Giriş yap', $db, $ilk), $m['ad_soyad']);
                ik_kanal_log($db, $id, 'mail', $m['email'], 'Giriş bilgisi', $g['ok'], $g['sonuc']); $o['mail'] = [(bool)$g['ok'], (string)$g['sonuc']];
            }
        }
        return $o;
    };

    if ($islem === 'kullanicilar') {
        $l = []; $q = $db->query("SELECT id, kullanici_adi, ad_soyad, rol, gorev, aktif, telefon, email, avatar_renk, profil_resmi, son_giris, sifre_degistir FROM kullanicilar ORDER BY aktif DESC, ad_soyad");
        $bm = []; $bq = $db->query("SELECT kullanici_id, departman FROM ik_personel"); if ($bq) while ($b = $bq->fetch_row()) $bm[intval($b[0])] = (string)$b[1];
        if ($q) while ($r = $q->fetch_assoc()) { $r['resim'] = tb_profil_resmi_src($r['profil_resmi']); unset($r['profil_resmi']); $r['birim'] = $bm[intval($r['id'])] ?? ''; $l[] = $r; }
        $aktif = count(array_filter($l, function ($x) { return intval($x['aktif']) === 1; }));
        ikc(['ok' => true, 'kullanicilar' => $l, 'kurum' => $kurum_ad, 'sinir' => $sinir, 'aktif' => $aktif, 'ben_kurucu' => $ben_kurucu, 'ben' => $uid, 'sms_hazir' => ik_sms_hazir($db), 'mail_hazir' => ik_mail_hazir($db)]);
    }
    if ($islem === 'personel_disa_aktar') {
        $kurAd = (string)($GLOBALS['KURUM']['ad'] ?? ''); $gt = function ($d) { return $d && $d !== '0000-00-00' ? date('d/m/Y', strtotime($d)) : '-'; };
        $liste = []; $q = $db->query("SELECT k.ad_soyad, k.aktif, k.gorev, p.tc_kimlik, p.ise_giris, p.pozisyon_baslama, p.isten_cikis, p.calisma_sekli, p.departman, p.unvan FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id ORDER BY k.aktif DESC, k.ad_soyad");
        if ($q) while ($r = $q->fetch_assoc()) {
            $ad = trim((string)$r['ad_soyad']); $pos = mb_strrpos($ad, ' ', 0, 'UTF-8');
            $ay = $pos === false ? [$ad, ''] : [mb_substr($ad, 0, $pos, 'UTF-8'), mb_substr($ad, $pos + 1, null, 'UTF-8')];
            $liste[] = [$ay[0], $ay[1], (string)($r['tc_kimlik'] ?? ''), $gt($r['ise_giris'] ?? null), $gt($r['pozisyon_baslama'] ?: ($r['ise_giris'] ?? null)), $gt($r['isten_cikis'] ?? null), IK_CALISMA[$r['calisma_sekli'] ?? ''] ?? 'Tam zamanlı', $kurAd, (string)($r['departman'] ?? ''), (string)(($r['unvan'] ?? '') !== '' ? $r['unvan'] : $r['gorev']), intval($r['aktif']) === 1 && empty($r['isten_cikis']) ? 'Aktif' : 'Pasif'];
        }
        ikc(['ok' => true, 'baslik' => ['Ad', 'Soyad', 'Kimlik Numarası', 'İşe Başlama Tarihi', 'Pozisyon Başlama Tarihi', 'İşten Ayrılma Tarihi', 'Çalışma Şekli', 'Şirket', 'Departman (Birim)', 'Unvan', 'Çalışma Durumu'], 'satirlar' => $liste, 'kurum' => $kurAd]);
    }
    ik_csrf($girdi);
    if ($islem === 'kullanici_ice_aktar') {                // CSV / Excel toplu içe aktarma (istemci 25'erli gruplar halinde gönderir)
        $liste = json_decode((string)($girdi['satirlar'] ?? '[]'), true);
        if (!is_array($liste) || !$liste) ikh('Aktarılacak satır yok.');
        if (count($liste) > 60) ikh('Tek istekte en fazla 60 satır işlenir.');
        @set_time_limit(0);
        $kuru = !empty($girdi['kuru']); $kartYaz = !empty($Y['ik_duzenle']);
        $q = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE aktif = 1"); $aktifSay = $q ? intval($q->fetch_row()[0]) : 0;
        $slug = function ($ad) { $s = strtolower(strtr($ad, ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u'])); return preg_replace('/[^a-z0-9]+/', '', $s); };
        $varMi = function ($u) use ($db) { return pf_kullanici_cakisma($db, $u, '') !== ''; };
        $kullanilan = []; $sonuc = []; $tcGor = [];
        foreach ($liste as $i => $s) {
            $ad = trim(preg_replace('/\s+/', ' ', (string)($s['ad_soyad'] ?? '')));
            $r = ['satir' => intval($s['satir'] ?? $i + 1), 'ad_soyad' => $ad, 'ok' => false];
            $hata = '';
            $u = strtolower(trim((string)($s['kullanici_adi'] ?? '')));
            $mail = trim((string)($s['email'] ?? '')); if ($mail === '' || !filter_var($mail, FILTER_VALIDATE_EMAIL)) { $m2 = trim((string)($s['email2'] ?? '')); if ($m2 !== '' && filter_var($m2, FILTER_VALIDATE_EMAIL)) $mail = $m2; } if (trim((string)($s['telefon'] ?? '')) === '' && trim((string)($s['telefon2'] ?? '')) !== '') $s['telefon'] = $s['telefon2']; $tc = preg_replace('/\D/', '', (string)($s['tc_kimlik'] ?? ''));
            $rolG = strtolower(trim((string)($s['rol'] ?? ''))); $rol = in_array($rolG, ['yetkili', 'yonetici', 'yönetici'], true) ? 'yetkili' : 'personel';
            $durumG = strtolower(strtr(trim((string)($s['durum'] ?? '')), ['İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ğ' => 'g', 'ğ' => 'g', 'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o', 'Ç' => 'c', 'ç' => 'c']));
            $cikisT = ik_tarih($s['isten_cikis'] ?? '');
            $pasif = (bool)preg_match('/pasif|ayril|cikti|cikis|isten|ex/', $durumG) || ($cikisT && $cikisT <= date('Y-m-d'));
            // Aynı kişi zaten kayıtlıysa (T.C. kimlik ya da ad soyad) atlamak yerine eksik bilgilerini (birim, unvan vb.) günceller
            $mevcut = 0;
            if ($ad !== '' && !isset($tcGor[$tc])) {
                if (strlen($tc) === 11) { $mq = $db->query("SELECT kullanici_id FROM ik_personel WHERE tc_kimlik = '" . $db->real_escape_string($tc) . "' LIMIT 1"); if ($mq && ($mr = $mq->fetch_row())) $mevcut = intval($mr[0]); }
                if (!$mevcut) { $mq = $db->query("SELECT k.id FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.ad_soyad = '" . $db->real_escape_string($ad) . "' AND (p.tc_kimlik IS NULL OR p.tc_kimlik = '' OR p.tc_kimlik = '" . $db->real_escape_string($tc) . "') LIMIT 1"); if ($mq && ($mr = $mq->fetch_row())) $mevcut = intval($mr[0]); }
                if ($mevcut) { $r['guncellendi'] = true; $mu = $db->query("SELECT kullanici_adi FROM kullanicilar WHERE id = $mevcut")->fetch_row(); $u = (string)($mu[0] ?? $u); }
            }
            if ($ad === '') $hata = 'Ad soyad boş.';
            elseif ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) $hata = 'E-posta geçersiz.';
            elseif ($tc !== '' && strlen($tc) !== 11) $hata = 'T.C. kimlik 11 haneli olmalı.';
            elseif ($tc !== '' && isset($tcGor[$tc])) $hata = 'Dosyada aynı T.C. kimlik numarası iki kez geçiyor — atlandı.';
                        elseif (!$mevcut && $sinir > 0 && !$pasif && $aktifSay >= $sinir) $hata = "Kullanıcı sınırına ($sinir) ulaşıldı.";
            elseif (!$mevcut) {
                if ($u === '') { $b = $slug($ad); if (strlen($b) < 3) $b .= 'kul'; $u = $b; $n = 1; while (isset($kullanilan[$u]) || $varMi($u)) { $n++; $u = $b . $n; } }
                elseif (!kullanici_adi_gecerli($u)) $hata = 'Kullanıcı adı geçersiz (küçük harf, rakam, . _ -).';
                elseif (isset($kullanilan[$u]) || $varMi($u)) $hata = 'Bu kullanıcı adı zaten var.';
            }
            if ($hata !== '') { $r['hata'] = $hata; $sonuc[] = $r; continue; }
            $r['kullanici_adi'] = $u; $kullanilan[$u] = 1; if ($tc !== '') $tcGor[$tc] = 1;
            if ($kuru) { $r['ok'] = true; if (!$pasif) $aktifSay++; $sonuc[] = $r; continue; }
            $e = $mevcut ? ['ok' => true, 'id' => $mevcut, 'sifre' => ''] : kurum_kullanici_ekle($db, ['kullanici_adi' => $u, 'ad_soyad' => $ad, 'rol' => $rol, 'gorev' => $s['gorev'] ?? ($s['unvan'] ?? ''), 'telefon' => $s['telefon'] ?? '', 'email' => $mail]);
            if (!$e['ok']) { $r['hata'] = $e['hata']; $sonuc[] = $r; continue; }
            $kid = intval($e['id']); if (!$mevcut) { if (!$pasif) $aktifSay++; else $db->query("UPDATE kullanicilar SET aktif = 0 WHERE id = $kid"); }
            if ($mevcut) {                                   // mevcut kişide yalnızca boş alanlar doldurulur; dolu bilgi ezilmez, rol yalnızca yükseltilir
                $ku = $db->query("SELECT telefon, email, gorev, rol FROM kullanicilar WHERE id = $kid")->fetch_assoc() ?: [];
                $up = [];
                $yt = mb_substr(trim((string)($s['telefon'] ?? '')), 0, 30, 'UTF-8'); if (($ku['telefon'] ?? '') === '' && $yt !== '') $up[] = "telefon = '" . $db->real_escape_string($yt) . "'";
                if (($ku['email'] ?? '') === '' && $mail !== '' && pf_kullanici_cakisma($db, '', $mail, intval($db->kurum), $kid) === '') $up[] = "email = '" . $db->real_escape_string($mail) . "'";
                $yg = mb_substr(trim((string)($s['gorev'] ?? ($s['unvan'] ?? ''))), 0, 100, 'UTF-8'); if (($ku['gorev'] ?? '') === '' && $yg !== '') $up[] = "gorev = '" . $db->real_escape_string($yg) . "'";
                if (($ku['rol'] ?? '') === 'personel' && $rol === 'yetkili') $up[] = "rol = 'yetkili'";
                if ($up) $db->query("UPDATE kullanicilar SET " . implode(', ', $up) . " WHERE id = $kid");
            }
            $dg = ik_tarih($s['dogum_tarihi'] ?? ''); if ($dg) $db->query("UPDATE kullanicilar SET dogum_tarihi = '" . $db->real_escape_string($dg) . "' WHERE id = $kid");
            if ($kartYaz) {
                $db->query("INSERT IGNORE INTO ik_personel (kullanici_id) VALUES ($kid)");
                $set = [];
                ik_birim_listeye_ekle($db, (string)($s['departman'] ?? '')); ik_unvan_listeye_ekle($db, (string)($s['unvan'] ?? ''));
                foreach (['departman' => 100, 'unvan' => 100, 'sgk_no' => 30] as $a => $mx) { $v = mb_substr(trim((string)($s[$a] ?? '')), 0, $mx, 'UTF-8'); if ($v !== '') $set[] = "$a = '" . $db->real_escape_string($v) . "'"; }
                $ig = ik_tarih($s['ise_giris'] ?? ''); if ($ig) $set[] = "ise_giris = '" . $db->real_escape_string($ig) . "'";
                $pb = ik_tarih($s['pozisyon_baslama'] ?? ''); if ($pb) $set[] = "pozisyon_baslama = '" . $db->real_escape_string($pb) . "'";
                if ($cikisT) $set[] = "isten_cikis = '" . $db->real_escape_string($cikisT) . "'";
                $cs = strtolower(strtr(trim((string)($s['calisma_sekli'] ?? '')), ['İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's', 'Ö' => 'o', 'ö' => 'o', 'Ü' => 'u', 'ü' => 'u']));
                $csK = $cs === '' ? '' : (strpos($cs, 'yari') !== false || strpos($cs, 'part') !== false ? 'yari_zamanli' : (strpos($cs, 'sozles') !== false ? 'sozlesmeli' : (strpos($cs, 'staj') !== false ? 'stajyer' : (strpos($cs, 'gonul') !== false ? 'gonullu' : (strpos($cs, 'uzak') !== false ? 'uzaktan' : (strpos($cs, 'tam') !== false ? 'tam_zamanli' : ''))))));
                if ($csK !== '') $set[] = "calisma_sekli = '$csK'";
                if ($tc !== '') $set[] = "tc_kimlik = '" . $db->real_escape_string($tc) . "'";
                $yh = ik_sayi($s['yillik_izin_hak'] ?? ''); if ($yh !== null && $yh !== '') $set[] = 'yillik_izin_hak = ' . floatval($yh);
                if ($set) $db->query("UPDATE ik_personel SET " . implode(', ', $set) . ", guncelleyen_id = $uid, guncelleme_tarihi = NOW() WHERE kullanici_id = $kid");
            }
            $r['ok'] = true; $r['id'] = $kid; $r['sifre'] = $e['sifre']; $sonuc[] = $r;
        }
        ikc(['ok' => true, 'sonuc' => $sonuc, 'kuru' => $kuru]);
    }
    if ($islem === 'izin_ice_aktar') {                     // "İzin Raporları" Excel'inden geçmiş izinleri onaylı olarak ekler (istemci gruplar halinde gönderir)
        if (empty($Y['ik_duzenle'])) ikh('Bu işlem için yetkiniz yok.', 403);
        $liste = json_decode((string)($girdi['satirlar'] ?? '[]'), true);
        if (!is_array($liste) || !$liste) ikh('Aktarılacak satır yok.');
        if (count($liste) > 100) ikh('Tek istekte en fazla 100 satır işlenir.');
        $kuru = !empty($girdi['kuru']);
        $nz = function ($t) { $t = strtr(trim((string)$t), ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'I' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u']); return preg_replace('/\s+/', ' ', strtolower($t)); };
        $kisi = []; $q = $db->query("SELECT id, ad_soyad FROM kullanicilar"); if ($q) while ($r = $q->fetch_row()) $kisi[$nz($r[1])] = intval($r[0]);
        $turHarita = function ($t) use ($nz) {
            $t = $nz($t);
            if (strpos($t, 'yillik') !== false) return 'yillik'; if (strpos($t, 'mazeret') !== false) return 'mazeret'; if (strpos($t, 'ucretsiz') !== false) return 'ucretsiz';
            if (strpos($t, 'babalik') !== false) return 'babalik'; if (strpos($t, 'evlilik') !== false) return 'evlilik'; if (strpos($t, 'vefat') !== false) return 'vefat';
            if (strpos($t, 'hastalik') !== false || strpos($t, 'rapor') !== false) return 'rapor'; if (strpos($t, 'dogum') !== false) return 'dogum'; return 'idari';
        };
        $ayril = function ($v) { if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})(?:\s+(\d{1,2}):(\d{2}))?/', trim((string)$v), $m)) return [sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]), isset($m[4]) ? sprintf('%02d:%02d', $m[4], $m[5]) : '']; if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/', trim((string)$v), $m)) return [$m[1] . '-' . $m[2] . '-' . $m[3], isset($m[4]) ? $m[4] . ':' . $m[5] : '']; return null; };
        $sonuc = []; $eklenen = 0; $tekrar = 0; $bulunamayan = [];
        foreach ($liste as $i => $s) {
            $ad = trim(preg_replace('/\s+/', ' ', trim((string)($s['ad'] ?? '')) . ' ' . trim((string)($s['soyad'] ?? ''))));
            $r = ['satir' => intval($s['satir'] ?? $i + 1), 'ad_soyad' => $ad, 'ok' => false];
            $kid = $kisi[$nz($ad)] ?? 0; $b = $ayril($s['baslangic'] ?? ''); $e = $ayril($s['bitis'] ?? '');
            if ($ad === '') { $r['hata'] = 'Ad soyad boş.'; }
            elseif (!$b || !$e) { $r['hata'] = 'Tarih okunamadı.'; }
            elseif ($e[0] < $b[0]) { $r['hata'] = 'Bitiş tarihi başlangıçtan önce.'; }
            elseif (!$kid) { $r['hata'] = 'Kullanıcılar arasında bulunamadı.'; }
            if (!empty($r['hata'])) { $sonuc[] = $r; continue; }
            $tur = $turHarita($s['tur'] ?? ''); $gun = round(max(0, floatval(str_replace(',', '.', (string)($s['gun'] ?? 0)))), 1);
            if ($gun <= 0) { $gun = $b[0] === $e[0] ? 1 : round((strtotime($e[0]) - strtotime($b[0])) / 86400 + 1, 1); }
            $yarim = $gun < 1 ? 1 : 0;
            $acik = 'Excel\'den içe aktarıldı' . ($b[1] !== '' ? ' (' . $b[1] . ($e[1] !== '' ? '–' . $e[1] : '') . ')' : '');
            $bs = $db->real_escape_string($b[0]); $bt = $db->real_escape_string($e[0]);
            $var = $db->query("SELECT id FROM ik_izin_talepleri WHERE kullanici_id = $kid AND tur = '$tur' AND baslangic = '$bs' AND bitis = '$bt' AND ABS(gun - $gun) < 0.15 AND aciklama LIKE '" . $db->real_escape_string(mb_substr($acik, 0, 40, 'UTF-8')) . "%' LIMIT 1");
            if ($var && $var->num_rows) { $r['ok'] = true; $r['tekrar'] = true; $tekrar++; $sonuc[] = $r; continue; }
            if (!$kuru) {
                $db->query("INSERT INTO ik_izin_talepleri (kullanici_id, tur, baslangic, bitis, yarim_gun, gun, aciklama, durum, onaylayan_id, onay_notu, onay_tarihi) VALUES ($kid, '$tur', '$bs', '$bt', $yarim, $gun, '" . $db->real_escape_string($acik) . "', 'onaylandi', $uid, '', NOW())");
                if ($db->hatano()) { $r['hata'] = 'Kaydedilemedi: ' . $db->hata(); $sonuc[] = $r; continue; }
            }
            $r['ok'] = true; $eklenen++; $sonuc[] = $r;
        }
        ikc(['ok' => true, 'sonuc' => $sonuc, 'eklenen' => $eklenen, 'tekrar' => $tekrar, 'kuru' => $kuru]);
    }
    if ($islem === 'personel_form_aktar') {                // "Personel Bilgi Formu" Word dosyaları (.docx ya da .zip içinde): kart alanlarına işlenir, ham form saklanır
        if (empty($Y['ik_duzenle'])) ikh('Personel bilgilerini yalnızca yetkili kişiler güncelleyebilir.', 403);
        if (!class_exists('ZipArchive')) ikh('Sunucuda ZIP desteği (php-zip) kapalı; Word dosyaları okunamıyor.');
        ik_form_sema($db); @set_time_limit(0);
        $kuru = !empty($girdi['kuru']); $ezme = !empty($girdi['uzerine_yaz']);
        $trk = function ($t) { return strtr($t, ['İ' => 'i', 'I' => 'ı', 'Ş' => 'ş', 'Ğ' => 'ğ', 'Ü' => 'ü', 'Ö' => 'ö', 'Ç' => 'ç']); };
        $nz = function ($t) use ($trk) { return preg_replace('/[^a-z0-9]/', '', strtr(mb_strtolower($trk((string)$t), 'UTF-8'), ['ı' => 'i', 'ş' => 's', 'ğ' => 'g', 'ü' => 'u', 'ö' => 'o', 'ç' => 'c'])); };
        $baslik = function ($t) use ($trk) { $t = trim(preg_replace('/\s+/u', ' ', (string)$t)); if ($t === '') return ''; if ($t !== mb_strtoupper(strtr($t, ['i' => 'İ']), 'UTF-8') && $t !== mb_strtolower($trk($t), 'UTF-8')) return $t;
            return preg_replace_callback('/(^|[\s\/\-\.\(])(\S)/u', function ($m) use ($trk) { return $m[1] . mb_strtoupper(strtr($m[2], ['i' => 'İ', 'ı' => 'I']), 'UTF-8'); }, mb_strtolower($trk($t), 'UTF-8')); };
        $tel = function ($t) { $d = preg_replace('/\D/', '', (string)$t); if (strlen($d) === 12 && substr($d, 0, 2) === '90') $d = '0' . substr($d, 2); if (strlen($d) === 10) $d = '0' . $d; return strlen($d) === 11 ? substr($d, 0, 4) . ' ' . substr($d, 4, 3) . ' ' . substr($d, 7, 2) . ' ' . substr($d, 9, 2) : trim((string)$t); };
        $tar = function ($t) { if (preg_match('/(\d{1,2})\s*[.\/\-]\s*(\d{1,2})\s*[.\/\-]\s*(\d{4})/', (string)$t, $m) && checkdate((int)$m[2], (int)$m[1], (int)$m[3])) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); return null; };
        $mailOku = function ($t) { foreach (preg_split('/[\s,;]+/', (string)$t) as $x) { $x = strtolower(trim($x)); if ($x !== '' && filter_var($x, FILTER_VALIDATE_EMAIL)) return $x; } return ''; };
        $docOku = function ($yol) {            // Word tablosundan "etiket → değer" çıkarır
            $z = new ZipArchive(); if ($z->open($yol) !== true) return null; $x = $z->getFromName('word/document.xml'); $z->close(); if ($x === false) return null;
            $d = new DOMDocument(); if (!@$d->loadXML($x, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) return null;
            $xp = new DOMXPath($d); $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main'); $o = [];
            foreach ($xp->query('//w:tbl/w:tr') as $tr) {
                $h = [];
                foreach ($xp->query('w:tc', $tr) as $tc) { $par = []; foreach ($xp->query('w:p', $tc) as $p) { $par[] = trim(preg_replace('/\s+/u', ' ', $xp->evaluate('string(.)', $p))); } $h[] = trim(implode(' ', array_filter($par, function ($q) { return $q !== ''; }))); }
                if (count($h) >= 2 && $h[0] !== '' && $h[0] !== $h[1]) { $e = array_shift($h); $o[$e] = trim(implode(' ', array_unique(array_filter($h, function ($q) { return $q !== ''; })))); }
            }
            return $o;
        };
        $dosyalar = [];
        $gel = $_FILES['dosyalar'] ?? null;
        if ($gel && is_array($gel['name'])) foreach ($gel['name'] as $i => $ad) if ($gel['error'][$i] === UPLOAD_ERR_OK) $dosyalar[] = [$ad, $gel['tmp_name'][$i]];
        if (!$dosyalar) ikh('Dosya yüklenemedi (dosya boyutu sunucu sınırını aşmış olabilir; daha az dosya seçin).');
        $tmpler = []; $kaynak = [];
        foreach ($dosyalar as $d) {
            $uz = strtolower(pathinfo($d[0], PATHINFO_EXTENSION));
            if ($uz === 'zip') {
                $z = new ZipArchive(); if ($z->open($d[1]) !== true) { $kaynak[] = [basename($d[0]), null, 'ZIP açılamadı']; continue; }
                for ($i = 0; $i < $z->numFiles; $i++) { $n = $z->getNameIndex($i); if (strtolower(pathinfo($n, PATHINFO_EXTENSION)) !== 'docx' || strpos(basename($n), '~$') === 0 || $z->statIndex($i)['size'] > 25 * 1048576) continue; $t = tempnam(sys_get_temp_dir(), 'bf'); file_put_contents($t, $z->getFromIndex($i)); $tmpler[] = $t; $kaynak[] = [basename($n), $t, '']; }
                $z->close();
            } elseif ($uz === 'docx') { $kaynak[] = [basename($d[0]), $d[1], '']; }
            else $kaynak[] = [basename($d[0]), null, in_array($uz, ['png', 'jpg', 'jpeg', 'pdf'], true) ? 'Taranmış belge/resim — okunamadı (Word .docx gerekir)' : 'Desteklenmeyen dosya türü'];
        }
        $sonuc = [];
        foreach ($kaynak as [$dad, $yol, $onHata]) {
            $r = ['dosya' => $dad, 'ok' => false, 'uyari' => []];
            if ($onHata !== '') { $r['hata'] = $onHata; $sonuc[] = $r; continue; }
            $f = $docOku($yol); if (!$f) { $r['hata'] = 'Word dosyası okunamadı.'; $sonuc[] = $r; continue; }
            $F = []; foreach ($f as $e => $v) $F[$nz($e)] = $v;
            $al = function (array $anahtar) use ($F, $nz) { foreach ($anahtar as $a) { $a = $nz($a); if (isset($F[$a]) && trim($F[$a]) !== '' && trim($F[$a]) !== '-') return trim($F[$a]); foreach ($F as $e => $v) if (strpos($e, $a) === 0 && $e !== $a && trim($v) !== '' && trim($v) !== '-' && !preg_match('/^acildurumdaaranacakkisi(yakin|telefon)/', $e)) return trim($v); } return ''; };
            $ad = $baslik($al(['adsoyad']));
            if ($ad === '') $ad = $baslik(trim(preg_replace('/(\s*-?\s*bilgi formu)?$/iu', '', pathinfo($dad, PATHINFO_FILENAME))));
            $tc = preg_replace('/\D/', '', $al(['tckimlik']));
            $r['ad_soyad'] = $ad;
            // kişiyi bul: önce T.C., sonra ad soyad (büyük/küçük harf, boşluk ve Türkçe karakter farkı gözetilmez)
            $kid = 0;
            if (strlen($tc) === 11) { $q = $db->query("SELECT kullanici_id FROM ik_personel WHERE tc_kimlik = '" . $db->real_escape_string($tc) . "' LIMIT 1"); if ($q && ($x = $q->fetch_row())) $kid = intval($x[0]); }
            if (!$kid) { $q = $db->query("SELECT id, ad_soyad FROM kullanicilar"); $anah = $nz($ad); if ($q && $anah !== '') while ($x = $q->fetch_row()) if ($nz($x[1]) === $anah) { $kid = intval($x[0]); break; } }
            if (!$kid) { $r['bulunamadi'] = true; $r['hata'] = 'Kullanıcılar arasında bulunamadı.'; $r['onizleme'] = ['tc' => $tc, 'email' => $al(['dernekeposta']) ?: $al(['sahsieposta']), 'telefon' => $al(['dernekceptelefonu']) ?: $al(['sahsiceptelefonu']), 'ise_giris' => $tar($al(['isebaslamatarihi'])), 'departman' => $baslik($al(['departman'])), 'unvan' => $baslik($al(['unvan'])), 'dogum' => $tar($al(['dogumtarihi']))]; $sonuc[] = $r; continue; }
            $r['id'] = $kid;
            $dogumH = $al(['dogumtarihi']); $dTar = $tar($dogumH); $dYer = trim(preg_replace('/^[\s\\\\\/\-–—,]+|[\s\\\\\/\-–—,]+$/u', '', preg_replace('/\d{1,2}\s*[.\/\-]\s*\d{1,2}\s*[.\/\-]\s*\d{4}/', '', $dogumH))); $dYer = $baslik($dYer);
            $ib = $al(['bankaadi']); $iban = ''; $banka = '';
            if ($ib !== '') { if (preg_match('/TR\s*\d{2}(?:\s*\d){22}/i', $ib, $m)) { $iban = strtoupper(preg_replace('/\s+/', '', $m[0])); $banka = str_replace($m[0], '', $ib); } else $banka = $ib; $banka = $baslik(trim($banka, " \t/-–—:")); }
            $edu = $al(['tamamlananenyuksek']) ?: $al(['egitimdurumu']); $en = $nz($edu); $egitim = '';
            foreach ([['doktora', 'Doktora'], ['yukseklisans', 'Yüksek lisans'], ['onlisans', 'Ön lisans'], ['lisans', 'Lisans'], ['lise', 'Lise'], ['imamhatip', 'Lise'], ['ortaokul', 'Ortaokul'], ['ilkogretim', 'Ortaokul'], ['ilkokul', 'İlkokul']] as [$a, $b]) if (strpos($en, $a) !== false) { $egitim = $b; break; }
            if ($egitim === '') { $en2 = $nz($al(['egitimdurumu'])); foreach ([['onlisans', 'Ön lisans'], ['lisans', 'Lisans'], ['lise', 'Lise']] as [$a, $b]) if (strpos($en2, $a) !== false) { $egitim = $b; break; } }
            $as = $al(['askerlik']); $an = $nz($as); $ask = $as; if (strpos($an, 'muaf') !== false && strpos($an, 'tecil') === false && strpos($an, 'yapil') === false) $ask = 'Muaf'; elseif (strpos($an, 'tecil') !== false && strpos($an, 'yapil') === false) $ask = 'Tecil'; elseif ((strpos($an, 'yapil') !== false || strpos($an, 'yaptim') !== false || strpos($an, 'yerinegetir') !== false) && strpos($an, 'tecil') === false) $ask = 'Yapıldı';
            $md = $nz($al(['medenidurumu'])); $medeni = strpos($md, 'evli') !== false ? 'Evli' : (strpos($md, 'bekar') !== false ? 'Bekar' : (strpos($md, 'bosan') !== false ? 'Boşanmış' : (strpos($md, 'dul') !== false ? 'Dul' : '')));
            $cs = $nz($al(['cocuksayisi'])); $cocuk = null; if ($cs !== '') { $sy = ['bir' => 1, 'iki' => 2, 'uc' => 3, 'dort' => 4, 'bes' => 5, 'alti' => 6, 'yedi' => 7]; $cocuk = ctype_digit($cs) ? min(20, intval($cs)) : ($sy[$cs] ?? null); } elseif (trim($F[$nz('ÇOCUK SAYISI')] ?? '') === '-') $cocuk = 0;
            $kg = ''; if (preg_match('/^\s*(AB|A|B|O|0)\s*(?:rh)?\s*\(?\s*([+\-−])/iu', $al(['kangrubu']), $m)) $kg = strtoupper($m[1]) === 'O' ? '0' : strtoupper($m[1]); if ($kg !== '' && isset($m[2])) $kg .= ' Rh' . ($m[2] === '+' ? '+' : '-');
            $dernekMail = $mailOku($al(['dernekeposta'])); $sahsiMail = $mailOku($al(['sahsieposta']));
            $dernekTel = $al(['dernekceptelefonu']) !== '' ? $tel($al(['dernekceptelefonu'])) : ''; $sahsiTel = $al(['sahsiceptelefonu']) !== '' ? $tel($al(['sahsiceptelefonu'])) : '';
            $ig = $tar($al(['isebaslamatarihi'])); if (!$ig && $al(['isebaslamatarihi']) !== '') $r['uyari'][] = 'İşe başlama tarihi anlaşılamadı: “' . $al(['isebaslamatarihi']) . '”';
            $sg = $tar($al(['sgkbaslang', 'sgkbaslam'])); if (!$sg && $al(['sgkbaslang', 'sgkbaslam']) !== '') $r['uyari'][] = 'SGK başlangıcı anlaşılamadı';
            if ($tc !== '' && strlen($tc) !== 11) { $r['uyari'][] = 'T.C. kimlik 11 haneli değil'; $tc = ''; }
            $ikaz = $al(['ikametgah']);
            $yeni = ['tc_kimlik' => $tc, 'dogum_yeri' => $dYer, 'askerlik' => mb_substr($ask, 0, 80, 'UTF-8'), 'anne_adi' => $baslik($al(['anneadi'])), 'baba_adi' => $baslik($al(['babaadi'])), 'medeni_durum' => $medeni, 'kan_grubu' => $kg,
                'adres' => $ikaz, 'acil_kisi' => $baslik($al(['acildurumdaaranacakkisi'])), 'acil_yakinlik' => $baslik($al(['acildurumdaaranacakkisiyakin'])), 'acil_telefon' => ($t = $al(['acildurumdaaranacakkisitelefon'])) !== '' ? $tel($t) : '',
                'egitim_durumu' => $egitim, 'okul' => $al(['sontamamlananegitimkurumu']), 'iban' => $iban, 'banka' => $banka, 'departman' => $baslik($al(['departman'])), 'unvan' => $baslik($al(['unvan'])), 'email2' => $sahsiMail, 'telefon2' => $sahsiTel];
            if ($cocuk !== null) $yeni['cocuk_sayisi'] = $cocuk;
            $yeniT = ['dogum_tarihi' => $dTar, 'ise_giris' => $ig, 'sgk_baslangic' => $sg];
            $p = $db->query("SELECT * FROM ik_personel WHERE kullanici_id = $kid")->fetch_assoc() ?: [];
            $ku = $db->query("SELECT telefon, email, dogum_tarihi FROM kullanicilar WHERE id = $kid")->fetch_assoc() ?: [];
            $bos = function ($v) { return $v === null || $v === '' || $v === '0000-00-00' || $v === '0' || $v === 0 || $v === '0.0'; };
            $pSet = []; $dolan = [];
            foreach (array_merge($yeni, $yeniT) as $a => $v) {
                if ($v === null || $v === '') continue;
                if ($a === 'dogum_tarihi') continue;
                if (!$ezme && !$bos($p[$a] ?? null) && !($a === 'cocuk_sayisi')) continue;
                if ($a === 'cocuk_sayisi' && !$ezme && intval($p[$a] ?? 0) > 0) continue;
                if ((string)($p[$a] ?? '') === (string)$v) continue;
                $pSet[] = "$a = '" . $db->real_escape_string((string)$v) . "'"; $dolan[] = $a;
            }
            $kSet = [];
            $kMail = $dernekMail ?: $sahsiMail; $kTel = $dernekTel ?: $sahsiTel;
            if ($kMail !== '' && ($ezme || ($ku['email'] ?? '') === '') && strtolower((string)($ku['email'] ?? '')) !== $kMail) { $cak = pf_kullanici_cakisma($db, '', $kMail, intval($db->kurum), $kid); if ($cak === '') { $kSet[] = "email = '" . $db->real_escape_string($kMail) . "'"; $dolan[] = 'email'; } else $r['uyari'][] = 'E-posta başka kullanıcıda kayıtlı: ' . $kMail; }
            if ($kTel !== '' && ($ezme || ($ku['telefon'] ?? '') === '') && ($ku['telefon'] ?? '') !== $kTel) { $kSet[] = "telefon = '" . $db->real_escape_string($kTel) . "'"; $dolan[] = 'telefon'; }
            if ($dTar && ($ezme || $bos($ku['dogum_tarihi'] ?? null)) && ($ku['dogum_tarihi'] ?? '') !== $dTar) { $kSet[] = "dogum_tarihi = '$dTar'"; $dolan[] = 'dogum_tarihi'; }
            if ($yeni['departman'] !== '' && !$kuru) ik_birim_listeye_ekle($db, $yeni['departman']); if ($yeni['unvan'] !== '' && !$kuru) ik_unvan_listeye_ekle($db, $yeni['unvan']);
            $r['dolan'] = $dolan;
            if (!$kuru) {
                $db->query("INSERT IGNORE INTO ik_personel (kullanici_id) VALUES ($kid)");
                if ($pSet) $db->query("UPDATE ik_personel SET " . implode(', ', $pSet) . ", guncelleyen_id = $uid, guncelleme_tarihi = NOW() WHERE kullanici_id = $kid");
                if ($kSet) $db->query("UPDATE kullanicilar SET " . implode(', ', $kSet) . " WHERE id = $kid");
                $ham = json_encode($f, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $db->query("REPLACE INTO ik_personel_form (kullanici_id, dosya, ham, guncelleme) VALUES ($kid, '" . $db->real_escape_string(mb_substr($dad, 0, 190, 'UTF-8')) . "', '" . $db->real_escape_string($ham) . "', NOW())");
                if ($db->hatano()) { $r['hata'] = 'Kaydedilemedi.'; $sonuc[] = $r; continue; }
            }
            $r['ok'] = true; $sonuc[] = $r;
        }
        foreach ($tmpler as $t) @unlink($t);
        if (!$kuru) { try { ik_ek_log($db, $uid, 'Personel', 'personel_form_aktar', 'Personel bilgi formları içe aktarıldı (' . count(array_filter($sonuc, function ($x) { return !empty($x['ok']); })) . ' kişi)', 0); } catch (Throwable $e2) {} }
        ikc(['ok' => true, 'sonuc' => $sonuc]);
    }
    if ($islem === 'izin_bakiye_aktar') {                  // "Çalışan İzin Bakiye Raporu" Excel'i: tüm sütunlar kişiye işlenir (devir dahil)
        if (empty($Y['ik_duzenle'])) ikh('Bu işlem için yetkiniz yok.', 403);
        ik_bakiye_sema($db);
        $liste = json_decode((string)($girdi['satirlar'] ?? '[]'), true);
        if (!is_array($liste) || !$liste) ikh('Aktarılacak satır yok.');
        if (count($liste) > 60) ikh('Tek istekte en fazla 60 satır işlenir.');
        $rapor = ik_tarih((string)($girdi['rapor_tarihi'] ?? '')) ?: date('Y-m-d');
        $nz = function ($t) { $t = strtr(trim((string)$t), ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'I' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u']); return preg_replace('/\s+/', ' ', strtolower($t)); };
        $tarihOku = function ($v) { $v = trim((string)$v); if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', $v, $m)) return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]); if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $v, $m)) return "$m[1]-$m[2]-$m[3]"; return null; };
        $sayiOku = function ($v) { $v = trim((string)$v); if ($v === '' || $v === '-') return null; $v = str_replace(',', '.', $v); return is_numeric($v) ? round((float)$v, 1) : null; };
        $bul = function ($h, $anahtarlar) use ($nz) { foreach ($h as $k => $v) { $kk = $nz($k); foreach ($anahtarlar as $a) if ($kk === $a) return $v; } return null; };
        $tcMap = []; $adMap = [];
        $q = $db->query("SELECT k.id, k.ad_soyad, p.tc_kimlik FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id");
        if ($q) while ($r = $q->fetch_row()) { $adMap[$nz($r[1])] = intval($r[0]); if (strlen((string)$r[2]) === 11) $tcMap[$r[2]] = intval($r[0]); }
        $sonuc = []; $guncellenen = 0;
        foreach ($liste as $i => $h) {
            if (!is_array($h)) continue;
            $ad = trim(preg_replace('/\s+/', ' ', trim((string)$bul($h, ['ad'])) . ' ' . trim((string)$bul($h, ['soyad'])))); $tc = preg_replace('/\D/', '', (string)$bul($h, ['kimlik numarasi', 'tc kimlik no', 'tc kimlik']));
            $r = ['ad_soyad' => $ad, 'ok' => false];
            $kid = (strlen($tc) === 11 && isset($tcMap[$tc])) ? $tcMap[$tc] : ($adMap[$nz($ad)] ?? 0);
            if ($ad === '') { $r['hata'] = 'Ad soyad boş.'; $sonuc[] = $r; continue; }
            if (!$kid) { $r['hata'] = 'Kullanıcılar arasında bulunamadı.'; $sonuc[] = $r; continue; }
            $dd = trim((string)$bul($h, ['guncel hak edis donemi'])); $dBas = null; $dBit = null;
            if (preg_match('~(\d{1,2}[./-]\d{1,2}[./-]\d{4})\s*-\s*(\d{1,2}[./-]\d{1,2}[./-]\d{4})~', $dd, $m)) { $dBas = $tarihOku($m[1]); $dBit = $tarihOku($m[2]); }
            $hakT = $tarihOku($bul($h, ['izne esas hak edis tarihi'])) ?: $tarihOku($bul($h, ['ise giris tarihi']));
            $devir = $sayiOku($bul($h, ['guncel doneme devreden izin hakki'])) ?? 0; $sonHak = $sayiOku($bul($h, ['son donemde hak edilen'])); $kul = $sayiOku($bul($h, ['guncel donemde kullanilan'])) ?? 0;
            $ham = json_encode($h, JSON_UNESCAPED_UNICODE);
            $db->query("INSERT IGNORE INTO ik_personel (kullanici_id) VALUES ($kid)");
            // kişi kartı: boş olmayan bilgiler güncellenir (işe giriş, birim, unvan, kariyer başlangıcı, şirket, T.C.)
            $set = []; $e = function ($x) use ($db) { return $db->real_escape_string($x); };
            $ig = $tarihOku($bul($h, ['ise giris tarihi'])); if ($ig) $set[] = "ise_giris = '$ig'";
            $kb = $tarihOku($bul($h, ['kariyer baslangic tarihi'])); if ($kb) $set[] = "kariyer_baslangic = '$kb'";
            foreach (['departman' => [['departman'], 100], 'unvan' => [['unvan'], 100], 'sirket' => [['sirket'], 120]] as $col => $d) { $v = mb_substr(trim((string)$bul($h, $d[0])), 0, $d[1], 'UTF-8'); if ($v !== '' && $v !== '-') { $set[] = "$col = '" . $e($v) . "'"; if ($col === 'departman') ik_birim_listeye_ekle($db, $v); } }
            if (strlen($tc) === 11) { $ex = $db->query("SELECT kullanici_id FROM ik_personel WHERE tc_kimlik = '$tc' AND kullanici_id <> $kid LIMIT 1"); if ($ex && !$ex->num_rows) $set[] = "tc_kimlik = '$tc'"; }
            if ($set) $db->query("UPDATE ik_personel SET " . implode(', ', $set) . ", guncelleyen_id = $uid, guncelleme_tarihi = NOW() WHERE kullanici_id = $kid");
            $f = function ($x) { return $x === null ? 'NULL' : "'" . $x . "'"; };
            $db->query("REPLACE INTO ik_izin_bakiye (kullanici_id, rapor_tarihi, hak_tarihi, devreden, son_donem_hak, donem_bas, donem_bit, donem_kullanilan, ham, guncelleme) VALUES ($kid, '$rapor', " . $f($hakT) . ", $devir, " . ($sonHak === null ? 'NULL' : $sonHak) . ", " . $f($dBas) . ", " . $f($dBit) . ", $kul, '" . $e($ham) . "', NOW())");
            if ($db->hatano()) { $r['hata'] = 'Kaydedilemedi: ' . $db->hata(); $sonuc[] = $r; continue; }
            $r['ok'] = true; $guncellenen++; $sonuc[] = $r;
        }
        try { ik_ek_log($db, $uid, 'Personel', 'izin_bakiye_aktar', 'İzin bakiye raporu içe aktarıldı (' . $guncellenen . ' kişi)', 0); } catch (Throwable $e2) {}
        ikc(['ok' => true, 'sonuc' => $sonuc, 'guncellenen' => $guncellenen]);
    }
    if ($islem === 'kullanici_yonetici_esle') {            // içe aktarma sonrası: ad soyada göre yönetici ata
        if (empty($Y['ik_duzenle'])) ikh('Bu işlem için yetkiniz yok.', 403);
        $liste = json_decode((string)($girdi['liste'] ?? '[]'), true); if (!is_array($liste) || count($liste) > 60) ikh('Geçersiz liste.');
        $nz = function ($t) { $t = strtr((string)$t, ['ç' => 'c', 'Ç' => 'c', 'ğ' => 'g', 'Ğ' => 'g', 'ı' => 'i', 'İ' => 'i', 'I' => 'i', 'ö' => 'o', 'Ö' => 'o', 'ş' => 's', 'Ş' => 's', 'ü' => 'u', 'Ü' => 'u']); return preg_replace('/[^a-z0-9]/', '', strtolower($t)); };
        $harita = []; $q = $db->query("SELECT id, ad_soyad FROM kullanicilar"); if ($q) while ($r = $q->fetch_row()) { $harita[$nz($r[1])] = intval($r[0]); }
        $n = 0;
        foreach ($liste as $x) {
            $kid = intval($x['id'] ?? 0); $yid = $harita[$nz($x['yonetici'] ?? '')] ?? 0;
            if ($kid > 0 && $yid > 0 && $yid !== $kid) { $db->query("INSERT IGNORE INTO ik_personel (kullanici_id) VALUES ($kid)"); $db->query("UPDATE ik_personel SET yonetici_id = $yid, guncelleyen_id = " . intval($uid) . ", guncelleme_tarihi = NOW() WHERE kullanici_id = $kid"); $n++; }
        }
        ikc(['ok' => true, 'eslesen' => $n]);
    }
    if ($islem === 'kullanici_kaydet') {
        $id = intval($girdi['id'] ?? 0);
        $rol = (string)($girdi['rol'] ?? 'personel');
        if (!in_array($rol, ['personel', 'yetkili', 'kurucu'], true)) ikh('Geçersiz rol.');
        if ($rol === 'kurucu' && !$ben_kurucu) ikh('Kurum yöneticisi rolünü yalnızca kurum yetkilisi verebilir.', 403);
        $ad = trim(preg_replace('/\s+/', ' ', (string)($girdi['ad_soyad'] ?? ''))); if ($ad === '') ikh('Ad soyad gerekli.');
        $gorev = mb_substr(trim((string)($girdi['gorev'] ?? '')), 0, 100, 'UTF-8');
        $tel = mb_substr(trim((string)($girdi['telefon'] ?? '')), 0, 30, 'UTF-8');
        $mail = trim((string)($girdi['email'] ?? '')); if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) ikh('E-posta adresi geçersiz.');
        if ($id === 0) {
            $q = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE aktif = 1"); $say = $q ? intval($q->fetch_row()[0]) : 0;
            if ($sinir > 0 && $say >= $sinir) ikh("Kurumunuzun kullanıcı sınırına ($sinir) ulaşıldı. Platform yöneticinizle görüşün.");
            $r = kurum_kullanici_ekle($db, ['kullanici_adi' => $girdi['kullanici_adi'] ?? '', 'ad_soyad' => $ad, 'rol' => $rol, 'gorev' => $gorev, 'telefon' => $tel, 'email' => $mail, 'sifre' => $girdi['sifre'] ?? '']);
            if (!$r['ok']) ikh($r['hata']);
            $birim = mb_substr(trim((string)($girdi['birim'] ?? '')), 0, 100, 'UTF-8');
            if ($birim !== '') { ik_birim_listeye_ekle($db, $birim); ik_birim_yaz($db, $r['id'], $birim, $uid); }
            ikc(['ok' => true, 'id' => $r['id'], 'kullanici_adi' => $r['kullanici_adi'], 'sifre' => $r['sifre']]);
        }
        $m = $satir($id); if (!$m) ikh('Kullanıcı bulunamadı.', 404);
        if ($mail !== '') { $cak = pf_kullanici_cakisma($db, '', $mail, $db->kurum, $id); if ($cak !== '') ikh($cak); }
        if ($m['rol'] === 'kurucu' && !$ben_kurucu) ikh('Kurum yetkilisinin bilgilerini yalnızca kurum yetkilisi değiştirebilir.', 403);
        if ($m['rol'] === 'kurucu' && $rol !== 'kurucu' && $kurucu_say() <= 1) ikh('Kurumda en az bir kurum yetkilisi kalmalı.');
        $st = $db->prepare("UPDATE kullanicilar SET ad_soyad = ?, rol = ?, gorev = ?, telefon = ?, email = ? WHERE id = ?");
        $st->bind_param('sssssi', $ad, $rol, $gorev, $tel, $mail, $id); $st->execute();
        if ($id === $uid) $_SESSION['ad_soyad'] = $ad;
        if (array_key_exists('birim', $girdi)) { $birim = mb_substr(trim((string)$girdi['birim']), 0, 100, 'UTF-8'); ik_birim_listeye_ekle($db, $birim); ik_birim_yaz($db, $id, $birim, $uid); }
        ikc(['ok' => true]);
    }
    if ($islem === 'kullanici_durum') {
        $id = intval($girdi['id'] ?? 0); $akt = ($girdi['aktif'] ?? '1') === '0' ? 0 : 1;
        $m = $satir($id); if (!$m) ikh('Kullanıcı bulunamadı.', 404);
        if ($id === $uid) ikh('Kendi hesabınızı pasifleştiremezsiniz.');
        if ($m['rol'] === 'kurucu' && !$ben_kurucu) ikh('Kurum yetkilisini yalnızca kurum yetkilisi pasifleştirebilir.', 403);
        if ($akt === 0 && $m['rol'] === 'kurucu' && $kurucu_say() <= 1) ikh('Kurumda en az bir etkin kurum yetkilisi kalmalı.');
        if ($akt === 1 && $sinir > 0) { $q = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE aktif = 1"); if ($q && intval($q->fetch_row()[0]) >= $sinir) ikh("Kullanıcı sınırına ($sinir) ulaşıldı."); }
        $db->query("UPDATE kullanicilar SET aktif = $akt WHERE id = $id");
        if ($akt === 0) pf_hatirla_kisi_sil($db, TB_KURUM_ID, $id);
        ikc(['ok' => true]);
    }
    if ($islem === 'kullanici_sms') {
        ik_csrf($girdi);
        $kanal = in_array(($girdi['kanal'] ?? ''), ['sms', 'mail', 'ikisi'], true) ? $girdi['kanal'] : 'sms';
        if ($kanal === 'sms' && !ik_sms_hazir($db)) ikh('SMS ayarları yapılmamış. Önce “Bildirim kanalları” bölümünden SMS sağlayıcısını tanımlayın.');
        if ($kanal === 'mail' && !ik_mail_hazir($db)) ikh('E-posta ayarları yapılmamış. Önce “Bildirim kanalları” bölümünden e-postayı tanımlayın.');
        if ($kanal === 'ikisi' && !ik_sms_hazir($db) && !ik_mail_hazir($db)) ikh('SMS ve e-posta ayarları yapılmamış. Önce “Bildirim kanalları” bölümünden tanımlayın.');
        $liste = json_decode((string)($girdi['ids'] ?? '[]'), true);
        if (!is_array($liste) || !$liste) ikh('Kimseye gönderilmedi: kişi seçin.');
        $liste = array_slice(array_values(array_unique(array_map('intval', $liste))), 0, 100);
        $sonuc = []; $ok = 0;
        foreach ($liste as $id) {
            $q = $db->query("SELECT id, kullanici_adi, ad_soyad, rol, aktif, telefon, email, sifre, sifre_degistir FROM kullanicilar WHERE id = $id"); $m = $q ? $q->fetch_assoc() : null;
            $r = ['id' => $id, 'ad' => $m['ad_soyad'] ?? '#' . $id, 'ok' => false, 'neden' => '', 'kanallar' => []];
            if (!$m) $r['neden'] = 'Kullanıcı bulunamadı';
            elseif ($id === $uid) $r['neden'] = 'Kendi şifreniz değiştirilmez';
            elseif (!intval($m['aktif'])) $r['neden'] = 'Hesap pasif';
            elseif ($m['rol'] === 'kurucu' && !$ben_kurucu) $r['neden'] = 'Kurum yetkilisi (yalnızca yetkilisi gönderebilir)';
            elseif (($kanal === 'sms' || $kanal === 'ikisi') && ik_telefon_norm($m['telefon']) === '' && $kanal === 'sms') $r['neden'] = 'Geçerli telefon numarası yok';
            elseif ($kanal === 'mail' && !filter_var($m['email'], FILTER_VALIDATE_EMAIL)) $r['neden'] = 'Geçerli e-posta adresi yok';
            elseif ($kanal === 'ikisi' && ik_telefon_norm($m['telefon']) === '' && !filter_var($m['email'], FILTER_VALIDATE_EMAIL)) $r['neden'] = 'Telefon ve e-posta yok';
            else {
                $yeni = pf_sifre_uret(10); $h = password_hash($yeni, PASSWORD_DEFAULT);
                $st = $db->prepare("UPDATE kullanicilar SET sifre = ?, sifre_degistir = 1 WHERE id = ?"); $st->bind_param('si', $h, $id); $st->execute();
                $o = $giris_gonder($m, $yeni, $kanal);
                $iyi = false; $hatalar = [];
                foreach (['sms' => 'SMS', 'mail' => 'E-posta'] as $k => $ad) if ($o[$k]) { if ($o[$k][0]) { $iyi = true; $r['kanallar'][] = $ad; } else $hatalar[] = $ad . ': ' . $o[$k][1]; }
                if ($iyi) { $r['ok'] = true; $ok++; pf_hatirla_kisi_sil($db, TB_KURUM_ID, $id); if ($hatalar) $r['neden'] = implode(' · ', $hatalar); }
                else {                                    // hiçbiri gitmediyse eski şifre geçerli kalsın
                    $st = $db->prepare("UPDATE kullanicilar SET sifre = ?, sifre_degistir = ? WHERE id = ?"); $sd = intval($m['sifre_degistir']); $st->bind_param('sii', $m['sifre'], $sd, $id); $st->execute();
                    $r['neden'] = 'Gönderilemedi — ' . implode(' · ', $hatalar);
                }
            }
            $sonuc[] = $r;
        }
        ikc(['ok' => true, 'gonderilen' => $ok, 'toplam' => count($sonuc), 'sonuc' => $sonuc]);
    }
    if ($islem === 'kullanici_sifre_gonder') {
        ik_csrf($girdi);
        $id = intval($girdi['id'] ?? 0); $kanal = in_array(($girdi['kanal'] ?? ''), ['sms', 'mail', 'ikisi'], true) ? $girdi['kanal'] : 'ikisi';
        $q = $db->query("SELECT id, kullanici_adi, ad_soyad, rol, telefon, email, sifre FROM kullanicilar WHERE id = $id"); $m = $q ? $q->fetch_assoc() : null; if (!$m) ikh('Kullanıcı bulunamadı.', 404);
        if ($m['rol'] === 'kurucu' && !$ben_kurucu) ikh('Kurum yetkilisine yalnızca kurum yetkilisi gönderebilir.', 403);
        $yeni = (string)($girdi['sifre'] ?? ''); if ($yeni === '' || !password_verify($yeni, (string)$m['sifre'])) ikh('Bu şifre artık geçerli değil; şifreyi yeniden sıfırlayın.');
        $o = $giris_gonder($m, $yeni, $kanal);
        ikc(['ok' => true, 'sms' => $o['sms'], 'mail' => $o['mail']]);
    }
    if ($islem === 'kullanici_sifre') {
        $id = intval($girdi['id'] ?? 0); $m = $satir($id); if (!$m) ikh('Kullanıcı bulunamadı.', 404);
        if ($m['rol'] === 'kurucu' && !$ben_kurucu) ikh('Kurum yetkilisinin şifresini yalnızca kurum yetkilisi sıfırlayabilir.', 403);
        $yeni = pf_sifre_uret(10); $h = password_hash($yeni, PASSWORD_DEFAULT);
        $st = $db->prepare("UPDATE kullanicilar SET sifre = ?, sifre_degistir = 1 WHERE id = ?"); $st->bind_param('si', $h, $id); $st->execute();
        pf_hatirla_kisi_sil($db, TB_KURUM_ID, $id);
        ikc(['ok' => true, 'kullanici_adi' => $m['kullanici_adi'], 'sifre' => $yeni]);
    }
}

// ──────────────────────────── YAZMA İŞLEMLERİ (POST — bazı hostinglerde https/www yönlendirmesiyle GET'e düşebildiği için yöntem zorunlu tutulmuyor; CSRF token asıl güvenlik katmanı) ────────────────────────────
ik_csrf($girdi);
$benAd = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $uid")->fetch_row()[0] ?? '';

if ($islem === 'personel_kaydet') {
    $id = intval($girdi['id'] ?? 0);
    $k = $db->query("SELECT id FROM kullanicilar WHERE id = $id")->fetch_row();
    if (!$k) ikh('Personel bulunamadı.', 404);
    ik_form_sema($db);
    $grup = (string)($girdi['grup'] ?? '');
    if (!isset($ALANLAR[$grup])) ikh('Geçersiz bölüm.');
    $izin = ['iletisim' => !empty($Y['ik_duzenle']), 'egitim' => !empty($Y['ik_duzenle']), // kişi kendi bilgisini değiştiremez
             'kisisel' => !empty($Y['ik_duzenle']), 'is' => !empty($Y['ik_duzenle']), 'mali' => !empty($Y['ik_maas'])][$grup];
    if (!$izin) ikh('Personel bilgilerini yalnızca yetkili kişiler güncelleyebilir.', 403);
    $db->query("INSERT IGNORE INTO ik_personel (kullanici_id) VALUES ($id)");
    $pSet = []; $kSet = []; $degisen = [];
    $eskiSatir = [];
    $q1 = $db->query("SELECT * FROM ik_personel WHERE kullanici_id = $id"); if ($q1 && ($r1 = $q1->fetch_assoc())) $eskiSatir += $r1;
    $q2 = $db->query("SELECT telefon, email, dogum_tarihi FROM kullanicilar WHERE id = $id"); if ($q2 && ($r2 = $q2->fetch_assoc())) $eskiSatir = $r2 + $eskiSatir;
    $ETIKET = ['telefon' => 'Telefon', 'email' => 'E-posta', 'adres' => 'Adres', 'il' => 'İl', 'ilce' => 'İlçe', 'acil_kisi' => 'Acil durum kişisi', 'acil_yakinlik' => 'Acil kişi yakınlığı', 'acil_telefon' => 'Acil durum telefonu', 'kan_grubu' => 'Kan grubu', 'medeni_durum' => 'Medeni durum', 'cocuk_sayisi' => 'Çocuk sayısı',
        'egitim_durumu' => 'Eğitim durumu', 'okul' => 'Okul', 'bolum' => 'Bölüm', 'mezuniyet_yili' => 'Mezuniyet yılı', 'tc_kimlik' => 'T.C. kimlik no', 'dogum_tarihi' => 'Doğum tarihi', 'dogum_yeri' => 'Doğum yeri', 'cinsiyet' => 'Cinsiyet',
        'kariyer_baslangic' => 'Kariyer başlangıç tarihi', 'sgk_baslangic' => 'SGK başlangıç tarihi', 'telefon2' => 'Şahsi telefon', 'email2' => 'Şahsi e-posta', 'askerlik' => 'Askerlik durumu', 'anne_adi' => 'Anne adı', 'baba_adi' => 'Baba adı', 'sirket' => 'Şirket', 'ise_giris' => 'İşe giriş tarihi', 'isten_cikis' => 'İşten çıkış tarihi', 'cikis_nedeni' => 'Çıkış nedeni', 'unvan' => 'Unvan', 'departman' => 'Birim', 'yonetici_id' => 'Bağlı olduğu yönetici', 'calisma_sekli' => 'Çalışma şekli', 'sgk_no' => 'SGK no', 'yillik_izin_hak' => 'Yıllık izin hakkı', 'notlar' => 'Notlar',
        'maas' => 'Maaş', 'maas_tipi' => 'Maaş tipi', 'iban' => 'IBAN', 'banka' => 'Banka'];
    $GIZLI = ['tc_kimlik', 'iban', 'maas', 'sgk_no'];
    foreach ($ALANLAR[$grup] as $a) {
        if (!array_key_exists($a, $girdi)) continue;
        $v = trim((string)$girdi[$a]);
        switch ($a) {
            case 'tc_kimlik': $v = preg_replace('/\D/', '', $v); if ($v !== '' && strlen($v) !== 11) ikh('T.C. kimlik numarası 11 haneli olmalı.'); break;
            case 'iban': $v = strtoupper(preg_replace('/\s+/', '', $v)); if ($v !== '' && !preg_match('/^TR\d{24}$/', $v)) ikh('IBAN "TR" ile başlayan 26 karakter olmalı.'); break;
            case 'email2': if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) ikh('Şahsi e-posta adresi geçersiz.'); break;
            case 'email': if ($v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) ikh('E-posta adresi geçersiz.'); if ($v !== '') { $cak = pf_kullanici_cakisma($db, '', $v, $db->kurum, $id); if ($cak !== '') ikh($cak); } break;
            case 'maas': case 'yillik_izin_hak': $v = ik_sayi($v); break;
            case 'cocuk_sayisi': $v = max(0, min(20, intval($v))); break;
            case 'mezuniyet_yili': $v = $v === '' ? null : max(1940, min(2100, intval($v))); break;
            case 'yonetici_id': $v = intval($v) ?: null; break;
            case 'ise_giris': case 'isten_cikis': case 'dogum_tarihi': case 'kariyer_baslangic': case 'sgk_baslangic': $v = ik_tarih($v); break;
            case 'calisma_sekli': if (!isset(IK_CALISMA[$v])) $v = 'tam_zamanli'; break;
            case 'maas_tipi': $v = $v === 'brut' ? 'brut' : 'net'; break;
            default: $v = mb_substr($v, 0, $a === 'adres' || $a === 'notlar' ? 5000 : 255, 'UTF-8');
        }
        $eskiV = (string)($eskiSatir[$a] ?? ''); $yeniV = $v === null ? '' : (string)$v;
        if ($eskiV === '0000-00-00') $eskiV = '';
        if (is_numeric($eskiV) && is_numeric($yeniV)) { if ((float)$eskiV !== (float)$yeniV) $ayni = false; else $ayni = true; } else $ayni = $eskiV === $yeniV;
        if (!$ayni) {
            $gos = function ($x) use ($a) { if ($x === '') return 'boş'; return mb_strlen($x, 'UTF-8') > 40 ? mb_substr($x, 0, 40, 'UTF-8') . '…' : $x; };
            if ($a === 'yonetici_id') { $ad1 = function ($i) use ($db) { $i = intval($i); if (!$i) return 'boş'; $q = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $i"); $r = $q ? $q->fetch_row() : null; return $r ? $r[0] : '#' . $i; }; $degisen[] = $ETIKET[$a] . ': ' . $ad1($eskiV) . ' → ' . $ad1($yeniV); }
            elseif (in_array($a, $GIZLI, true)) $degisen[] = $ETIKET[$a] . ' değiştirildi';
            else $degisen[] = ($ETIKET[$a] ?? $a) . ': ' . $gos($eskiV) . ' → ' . $gos($yeniV);
        }
        $sql = $v === null ? 'NULL' : "'" . $db->real_escape_string((string)$v) . "'";
        if (in_array($a, $KULLANICI_ALAN, true)) $kSet[] = "$a = $sql"; else $pSet[] = "$a = $sql";
    }
    if ($pSet) $db->query("UPDATE ik_personel SET " . implode(', ', $pSet) . ", guncelleyen_id = $uid, guncelleme_tarihi = NOW() WHERE kullanici_id = $id");
    if (isset($girdi['departman'])) ik_birim_listeye_ekle($db, (string)$girdi['departman']); if (isset($girdi['unvan'])) ik_unvan_listeye_ekle($db, (string)$girdi['unvan']);
    if ($kSet) $db->query("UPDATE kullanicilar SET " . implode(', ', $kSet) . " WHERE id = $id");
    if ($db->hatano()) ikh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
    $GLOBALS['IK_LOG'] = null; // ayrıntılı log aşağıda yazılır
    if ($degisen) { try { ik_ek_log($db, $uid, 'Personel', 'personel_kaydet', 'Personel bilgisi güncellendi — ' . implode('; ', $degisen), $id); } catch (Throwable $e) {} }
    if ($id !== $uid && in_array($grup, ['is', 'mali', 'kisisel'], true)) {
        $grupAd = ['is' => 'iş bilgileriniz', 'mali' => 'maaş / banka bilgileriniz', 'kisisel' => 'kişisel bilgileriniz'][$grup];
        ik_bildir($db, $id, $uid, '🗂️ Personel kartınız güncellendi', '🗂️ <b>' . ik_h($benAd) . '</b> ' . $grupAd . 'i güncelledi.', 'ik_personel', $id);
    }
    ikc(['ok' => true]);
}

if ($islem === 'personel_resim') {
    $id = intval($girdi['id'] ?? 0);
    if (!$id) ikh('Personel seçilmedi.');
    if (empty($Y['ik_duzenle'])) ikh('Personel fotoğrafını değiştirme yetkiniz yok (yalnızca İK yetkilisi değiştirebilir).', 403);
    $k = $db->query("SELECT id FROM kullanicilar WHERE id = $id")->fetch_row();
    if (!$k) ikh('Personel bulunamadı.', 404);
    $sonuc = ik_resim_kaydet($db, $id, $_FILES['dosya'] ?? null);
    if (!$sonuc['ok']) ikh($sonuc['hata'], 400);
    if ($id !== $uid) ik_bildir($db, $id, $uid, '🖼️ Profil fotoğrafınız güncellendi', '🖼️ <b>' . ik_h($benAd) . '</b> profil fotoğrafınızı güncelledi.', 'ik_personel', $id);
    ikc(['ok' => true, 'resim' => $sonuc['resim']]);
}

if ($islem === 'izin_talep') {
    $kid = !empty($Y['ik_izin_onay']) && !empty($girdi['kullanici_id']) ? intval($girdi['kullanici_id']) : $uid; // onaylayıcı başkası adına girebilir
    $tur = (string)($girdi['tur'] ?? 'yillik');
    if (!isset(IK_IZIN_TURLERI[$tur])) ikh('Geçersiz izin türü.');
    $bas = ik_tarih($girdi['baslangic'] ?? ''); $bit = ik_tarih($girdi['bitis'] ?? '') ?: $bas;
    if (!$bas) ikh('Başlangıç tarihini seçin.');
    if ($bit < $bas) ikh('Bitiş tarihi başlangıçtan önce olamaz.');
    $yarim = !empty($girdi['yarim_gun']) && $bas === $bit ? 1 : 0;
    $gun = ik_is_gunu($bas, $bit, (bool)$yarim);
    if ($gun <= 0) ikh('Seçilen aralıkta iş günü yok (hafta sonu).');
    $cak = $db->query("SELECT COUNT(*) FROM ik_izin_talepleri WHERE kullanici_id = $kid AND durum IN ('beklemede','onaylandi') AND baslangic <= '$bit' AND bitis >= '$bas'")->fetch_row()[0];
    if (intval($cak)) ikh('Bu tarihlerle çakışan başka bir izin talebi var.');
    if ($tur === 'yillik') {
        $oz = ik_izin_ozet($db, $kid);
        if ($oz['hak'] !== null && $gun > ($oz['kalan'] - $oz['bekleyen']) + 0.001) ikh('Yetersiz yıllık izin: kalan ' . rtrim(rtrim(number_format($oz['kalan'] - $oz['bekleyen'], 1, ',', ''), '0'), ',') . ' gün (onay bekleyenler düşüldü), istenen ' . $gun . ' gün.');
    }
    $acik = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 2000, 'UTF-8');
    $vekil = intval($girdi['vekil_id'] ?? 0) ?: null;
    $akis = ik_akis_cozumle($db, $kid);              // bu personelin biriminin onay zinciri
    $atla = !empty($Y['ik_izin_onay']) && !empty($girdi['atla']); // yetkili: onay sürecine girmeden doğrudan izin ekle
    if ($atla) $akis['adimlar'] = [];
    $durum = 'beklemede'; $dep = $akis['departman'];
    $ilkAdim = $akis['adimlar'] ? 1 : null;
    $st = $db->prepare("INSERT INTO ik_izin_talepleri (kullanici_id, tur, baslangic, bitis, yarim_gun, gun, aciklama, vekil_id, durum, departman, aktif_adim, olusturma_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $st->bind_param('isssidsissi', $kid, $tur, $bas, $bit, $yarim, $gun, $acik, $vekil, $durum, $dep, $ilkAdim);
    if (!$st->execute()) ikh('Talep kaydedilemedi.', 500);
    $tid = intval($db->son_id());
    // Zinciri talebe kopyala (akış sonradan değişse de bu talep etkilenmez)
    $sira = 0;
    foreach ($akis['adimlar'] as $a) {
        $sira++;
        $st2 = $db->prepare("INSERT INTO ik_izin_adimlari (talep_id, sira, onaylayici_id, durum, k_site, k_sms, k_mail, vekil_id) VALUES (?, ?, ?, 'bekliyor', ?, ?, ?, ?)");
        $ks = $a['site'] ? 1 : 0; $km = $a['sms'] ? 1 : 0; $ke = $a['mail'] ? 1 : 0;
        $vkk = intval($a['vekil_id'] ?? 0); $st2->bind_param('iiiiiii', $tid, $sira, $a['id'], $ks, $km, $ke, $vkk);
        $st2->execute();
    }
    $kisiAd = $kid === $uid ? $benAd : ($db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $kid")->fetch_row()[0] ?? '');
    $vekilAd = $vekil ? ($db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $vekil")->fetch_row()[0] ?? '') : '';
    $t = ['id' => $tid, 'kullanici_id' => $kid, 'tur' => $tur, 'baslangic' => $bas, 'bitis' => $bit, 'gun' => $gun, 'aciklama' => $acik];
    $kime = [];
    if ($atla) {
        // Onay süreci atlandı: kimseye onay bildirimi gitmez, talep aşağıda doğrudan onaylanır
    } elseif ($akis['adimlar']) {
        // Yalnızca ilk onaycıya haber gider; sıradakilere o onayladıkça iletilir
        $a = $akis['adimlar'][0];
        ik_izin_adim_bildir($db, $t, ['onaylayici_id' => $a['id'], 'vekil_id' => $a['vekil_id'] ?? 0, 'k_site' => $a['site'], 'k_sms' => $a['sms'], 'k_mail' => $a['mail']], $kisiAd, $vekilAd);
        $kime[] = $a['ad'];
    } else {
        // Akış tanımlı değil (ya da onaycı kalmadı): eski düzen — "izin onaylama" yetkisi olanların hepsine site içi bildirim
        $mesaj = '🌴 <b>' . ik_h($kisiAd) . '</b> ' . ik_izin_tarih_yaz($bas, $bit) . ' için <b>' . IK_IZIN_TURLERI[$tur] . '</b> talep etti (' . ik_gun_yaz($gun) . ' gün).';
        foreach (ik_onay_alicilari($db, 'izin', 'ik_izin_onay', $kid) as $a) if ($a !== $kid) { ik_izin_adim_bildir($db, $t, ['onaylayici_id' => $a, 'vekil_id' => 0, 'k_site' => 1, 'k_sms' => 0, 'k_mail' => 1], $kisiAd, $vekilAd); $kime[] = '#' . $a; }
    }
    $otomatik = false;
    if (!$kime) {
        // Onaylayacak kimse kalmadı (onaycı talebi açan kişinin kendisi / başka yetkili yok): talep sonsuza dek bekleyip kilitlenmesin
        $nt = $atla ? 'Onay süreci atlanarak doğrudan eklendi.' : 'Onaylayacak başka yetkili bulunmadığı için otomatik onaylandı.';
        $st = $db->prepare("UPDATE ik_izin_talepleri SET durum = 'onaylandi', onaylayan_id = ?, onay_notu = ?, onay_tarihi = NOW(), aktif_adim = NULL WHERE id = ? AND durum = 'beklemede'");
        $st->bind_param('isi', $uid, $nt, $tid); $st->execute(); $otomatik = $st->affected_rows > 0;
        if ($otomatik) ik_bildir($db, $kid, $uid, '✅ İzin talebiniz onaylandı', '✅ ' . ik_izin_tarih_yaz($bas, $bit) . ' tarihli <b>' . ik_h(IK_IZIN_TURLERI[$tur]) . '</b> talebiniz onaylandı (' . ($atla ? 'yetkili tarafından doğrudan eklendi' : 'onaylayacak başka yetkili olmadığı için otomatik') . ').', 'ik_izin', $tid);
    }
    if ($vekil) ik_bildir($db, $vekil, $uid, '🤝 Vekil olarak belirlendiniz', '🤝 <b>' . ik_h($kisiAd) . '</b> izindeyken (' . date('d.m', strtotime($bas)) . '–' . date('d.m', strtotime($bit)) . ') vekili olarak sizi belirtti.', 'ik_izin', $tid);
    ikc(['ok' => true, 'id' => $tid, 'gun' => $gun, 'otomatik' => $otomatik, 'atla' => $atla, 'kime' => $kime, 'zincir' => array_map(function ($a) { return $a['ad']; }, $akis['adimlar'])]);
}

if ($islem === 'izin_karar') {
    $id = intval($girdi['id'] ?? 0);
    $t = $db->query("SELECT * FROM ik_izin_talepleri WHERE id = $id")->fetch_assoc();
    if (!$t) ikh('Talep bulunamadı.', 404);
    if (intval($t['kullanici_id']) === $uid) ikh('Kendi izin talebinizi onaylayamazsınız.', 403);
    if ($t['durum'] !== 'beklemede') ikh('Bu talep zaten sonuçlandırılmış.');
    list($izinli, $adimlar, $aktif) = ik_izin_karar_yetkisi($db, $t, $uid, $Y);
    if (!$izinli) ikh($adimlar ? 'Bu talep için onay sırası sizde değil.' : 'İzin onaylama yetkiniz yok.', 403);
    $onay = ($girdi['karar'] ?? '') === 'onay';
    $not = mb_substr(trim((string)($girdi['not'] ?? '')), 0, 500, 'UTF-8');
    if (!$onay && $not === '') ikh('Reddetme nedenini yazın.');
    $akis = ik_akis_cozumle($db, intval($t['kullanici_id']));  // sonuç bildirimi kanalları (güncel akıştan)
    $kisiAd = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = " . intval($t['kullanici_id']))->fetch_row()[0] ?? '';
    $vekilAd = !empty($t['vekil_id']) ? ($db->query("SELECT ad_soyad FROM kullanicilar WHERE id = " . intval($t['vekil_id']))->fetch_row()[0] ?? '') : '';

    if ($aktif) {
        // Kararı sadece sırası gelen adım için işle (çift tıklamaya karşı koşullu güncelleme)
        $yeniDurum = $onay ? 'onaylandi' : 'reddedildi';
        $st = $db->prepare("UPDATE ik_izin_adimlari SET durum = ?, karar_veren_id = ?, karar_tarihi = NOW(), not_metni = ? WHERE id = ? AND durum = 'bekliyor'");
        $aid = intval($aktif['id']); $st->bind_param('sisi', $yeniDurum, $uid, $not, $aid); $st->execute();
        if ($st->affected_rows < 1) ikh('Bu adım zaten sonuçlandırılmış.');
        $sonraki = null;
        if ($onay) foreach ($adimlar as $a) if (intval($a['sira']) > intval($aktif['sira']) && $a['durum'] === 'bekliyor') {
            // Sıradaki adım da kararı veren kişiye aitse (ör. vekil olarak onayladı) tekrar onay istenmez, otomatik geçilir
            if (intval($a['onaylayici_id']) === $uid) {
                $an = intval($a['id']); $nt = 'Aynı kişi önceki adımı onayladığı için otomatik geçildi.';
                $db->query("UPDATE ik_izin_adimlari SET durum = 'onaylandi', karar_veren_id = $uid, karar_tarihi = NOW(), not_metni = '" . $db->real_escape_string($nt) . "' WHERE id = $an AND durum = 'bekliyor'");
                continue;
            }
            $sonraki = $a; break;
        }
        if ($onay && $sonraki) {
            // Zincirin sonraki halkasına geç ve o kişiye haber ver
            $sn = intval($sonraki['sira']);
            $db->query("UPDATE ik_izin_talepleri SET aktif_adim = $sn WHERE id = $id");
            ik_izin_adim_bildir($db, $t, $sonraki, $kisiAd, $vekilAd, $benAd);
            ikc(['ok' => true, 'sonraki' => $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = " . intval($sonraki['onaylayici_id']))->fetch_row()[0] ?? '', 'tamam' => false]);
        }
    }
    // Zincir bitti (ya da zincirsiz talep) → nihai karar
    $karar = $onay ? 'onaylandi' : 'reddedildi';
    $st = $db->prepare("UPDATE ik_izin_talepleri SET durum = ?, onaylayan_id = ?, onay_notu = ?, onay_tarihi = NOW(), aktif_adim = NULL WHERE id = ? AND durum = 'beklemede'");
    $st->bind_param('sisi', $karar, $uid, $not, $id); $st->execute();
    if ($st->affected_rows < 1) ikh('Bu talep zaten sonuçlandırılmış.');
    if (!$onay) $db->query("UPDATE ik_izin_adimlari SET durum = 'atlandi' WHERE talep_id = $id AND durum = 'bekliyor'");
    $t['onaylayan_id'] = $uid;
    ik_izin_sonuc_bildir($db, $t, $onay, $uid, $benAd, $not, $akis);
    ikc(['ok' => true, 'tamam' => true]);
}

if ($islem === 'izin_iptal') {
    $id = intval($girdi['id'] ?? 0);
    $t = $db->query("SELECT * FROM ik_izin_talepleri WHERE id = $id")->fetch_assoc();
    if (!$t) ikh('Talep bulunamadı.', 404);
    if (intval($t['kullanici_id']) !== $uid && empty($Y['ik_izin_onay'])) ikh('Bu talebi iptal edemezsiniz.', 403);
    if (!in_array($t['durum'], ['beklemede', 'onaylandi'], true)) ikh('Bu talep iptal edilemez.');
    if ($t['durum'] === 'onaylandi' && empty($Y['ik_izin_onay'])) ikh('Onaylanmış izni yalnızca İK iptal edebilir.', 403);
    $db->query("UPDATE ik_izin_talepleri SET durum = 'iptal', aktif_adim = NULL, onaylayan_id = $uid, onay_tarihi = NOW() WHERE id = $id");
    $db->query("UPDATE ik_izin_adimlari SET durum = 'atlandi' WHERE talep_id = $id AND durum = 'bekliyor'");
    ikc(['ok' => true]);
}

if ($islem === 'belge_yukle') {
    if (empty($Y['ik_belge'])) ikh('Belge yükleme yetkiniz yok.', 403);
    $kid = intval($girdi['kullanici_id'] ?? 0);
    if (!$db->query("SELECT 1 FROM kullanicilar WHERE id = $kid")->num_rows) ikh('Personel bulunamadı.', 404);
    if (empty($_FILES['dosya']) || $_FILES['dosya']['error'] !== UPLOAD_ERR_OK) ikh('Dosya yüklenemedi (en fazla 15 MB).');
    $f = $_FILES['dosya'];
    if ($f['size'] > 15 * 1024 * 1024) ikh('Dosya en fazla 15 MB olabilir.');
    $uz = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    $izinli = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (!isset($izinli[$uz])) ikh('Yalnızca PDF, görsel, Word veya Excel dosyası yüklenebilir.');
    if (!tb_dosya_icerik_uygun($f['tmp_name'], $uz)) ikh('Dosya içeriği uzantısıyla uyuşmuyor; yükleme reddedildi.');
    if (!is_dir($BELGE_KLASOR)) @mkdir($BELGE_KLASOR, 0755, true);
    if (!is_file($BELGE_KLASOR . '/.htaccess')) @file_put_contents($BELGE_KLASOR . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
    if (!is_file($BELGE_KLASOR . '/index.html')) @file_put_contents($BELGE_KLASOR . '/index.html', '');
    $ad = $kid . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $uz;
    if (!move_uploaded_file($f['tmp_name'], $BELGE_KLASOR . '/' . $ad)) ikh('Dosya kaydedilemedi (klasör yazılabilir değil).', 500);
    $tur = isset(IK_BELGE_TURLERI[$girdi['tur'] ?? '']) ? $girdi['tur'] : 'diger';
    $baslik = mb_substr(trim((string)($girdi['ad'] ?? '')), 0, 200, 'UTF-8');
    $orj = mb_substr(basename($f['name']), 0, 255, 'UTF-8');
    $mime = $izinli[$uz]; $boyut = intval($f['size']); $gec = ik_tarih($girdi['gecerlilik'] ?? '');
    $st = $db->prepare("INSERT INTO ik_belgeler (kullanici_id, tur, ad, dosya, orijinal_ad, mime, boyut, gecerlilik, yukleyen_id, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $st->bind_param('isssssisi', $kid, $tur, $baslik, $ad, $orj, $mime, $boyut, $gec, $uid);
    $st->execute();
    ikc(['ok' => true, 'id' => intval($db->son_id())]);
}

if ($islem === 'belge_sil') {
    if (empty($Y['ik_belge'])) ikh('Belge silme yetkiniz yok.', 403);
    $id = intval($girdi['id'] ?? 0);
    $r = $db->query("SELECT dosya FROM ik_belgeler WHERE id = $id")->fetch_row();
    if ($r) @unlink($BELGE_KLASOR . '/' . basename($r[0]));
    $db->query("DELETE FROM ik_belgeler WHERE id = $id");
    ikc(['ok' => true]);
}

if ($islem === 'zimmet_kaydet') {
    if (empty($Y['ik_belge'])) ikh('Zimmet yönetimi yetkiniz yok.', 403);
    $id = intval($girdi['id'] ?? 0); $kid = intval($girdi['kullanici_id'] ?? 0);
    $esya = mb_substr(trim((string)($girdi['esya'] ?? '')), 0, 150, 'UTF-8');
    if ($esya === '') ikh('Eşya / demirbaş adını yazın.');
    $seri = mb_substr(trim((string)($girdi['seri_no'] ?? '')), 0, 100, 'UTF-8');
    $acik = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 500, 'UTF-8');
    $teslim = ik_tarih($girdi['teslim_tarihi'] ?? '') ?: date('Y-m-d');
    if ($id) {
        $st = $db->prepare("UPDATE ik_zimmetler SET esya = ?, seri_no = ?, aciklama = ?, teslim_tarihi = ? WHERE id = ?");
        $st->bind_param('ssssi', $esya, $seri, $acik, $teslim, $id);
    } else {
        if (!$db->query("SELECT 1 FROM kullanicilar WHERE id = $kid")->num_rows) ikh('Personel bulunamadı.', 404);
        $st = $db->prepare("INSERT INTO ik_zimmetler (kullanici_id, esya, seri_no, aciklama, teslim_tarihi, durum, veren_id, tarih) VALUES (?, ?, ?, ?, ?, 'zimmetli', ?, NOW())");
        $st->bind_param('issssi', $kid, $esya, $seri, $acik, $teslim, $uid);
    }
    $st->execute();
    if (!$id) ik_bildir($db, $kid, $uid, '📦 Zimmet kaydı', '📦 Adınıza zimmet kaydedildi: <b>' . ik_h($esya) . '</b>' . ($seri !== '' ? ' (' . ik_h($seri) . ')' : '') . '.', 'ik_zimmet', $kid);
    ikc(['ok' => true]);
}
if ($islem === 'zimmet_iade') {
    if (empty($Y['ik_belge'])) ikh('Zimmet yönetimi yetkiniz yok.', 403);
    $id = intval($girdi['id'] ?? 0);
    $db->query("UPDATE ik_zimmetler SET durum = 'iade', iade_tarihi = CURDATE() WHERE id = $id");
    ikc(['ok' => true]);
}
if ($islem === 'zimmet_sil') {
    if (empty($Y['ik_belge'])) ikh('Zimmet yönetimi yetkiniz yok.', 403);
    $db->query("DELETE FROM ik_zimmetler WHERE id = " . intval($girdi['id'] ?? 0));
    ikc(['ok' => true]);
}

if ($islem === 'puantaj_kaydet') {
    if (empty($Y['ik_puantaj_yonet'])) ikh('Puantaj girme yetkiniz yok.', 403);
    $kid = intval($girdi['kullanici_id'] ?? 0);
    $tarih = ik_tarih($girdi['tarih'] ?? '');
    $durum = (string)($girdi['durum'] ?? '');
    if (!$kid || !$tarih || !isset(IK_PUANTAJ_DURUM[$durum])) ikh('Geçersiz puantaj verisi.');
    $not = mb_substr(trim((string)($girdi['not'] ?? '')), 0, 300, 'UTF-8');
    $st = $db->prepare("INSERT INTO ik_puantaj (kullanici_id, tarih, durum, not_metni, giren_id, guncelleme_tarihi) VALUES (?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE durum = VALUES(durum), not_metni = VALUES(not_metni), giren_id = VALUES(giren_id), guncelleme_tarihi = NOW()");
    $st->bind_param('isssi', $kid, $tarih, $durum, $not, $uid);
    if (!$st->execute()) ikh('Kaydedilemedi.', 500);
    ikc(['ok' => true]);
}

if ($islem === 'masraf_ekle') {
    $kid = (!empty($Y['ik_masraf_onay']) && !empty($girdi['kullanici_id'])) ? intval($girdi['kullanici_id']) : $uid;
    $kategori = isset(IK_MASRAF_KATEGORI[$girdi['kategori'] ?? '']) ? $girdi['kategori'] : 'diger';
    $tutar = max(0, (float)str_replace(',', '.', preg_replace('/[^\d,.-]/', '', (string)($girdi['tutar'] ?? '0'))));
    if ($tutar <= 0) ikh('Tutarı girin.');
    $tarih = ik_tarih($girdi['tarih'] ?? '') ?: date('Y-m-d');
    $acik = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 500, 'UTF-8');
    $st = $db->prepare("INSERT INTO ik_masraflar (kullanici_id, kategori, tutar, tarih, aciklama, durum, olusturma_tarihi) VALUES (?, ?, ?, ?, ?, 'beklemede', NOW())");
    $st->bind_param('isdss', $kid, $kategori, $tutar, $tarih, $acik);
    if (!$st->execute()) ikh('Kaydedilemedi.', 500);
    $mid = intval($db->son_id());
    $kisiAd = $kid === $uid ? $benAd : ($db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $kid")->fetch_row()[0] ?? '');
    $onayciler = array_map('intval', ik_onay_alicilari($db, 'masraf', 'ik_masraf_onay', $kid)); $hedef = array_values(array_diff($onayciler, [$kid])); if (!$hedef) $hedef = $onayciler; // tek onaycı kendisiyse yine de haber ver
    foreach ($hedef as $a) ik_bildir($db, $a, $uid, '🧾 Yeni masraf talebi', '🧾 <b>' . ik_h($kisiAd) . '</b> ' . ik_h(IK_MASRAF_KATEGORI[$kategori]) . ' için ' . number_format($tutar, 2, ',', '.') . ' ₺ masraf talebi oluşturdu.', 'ik_masraf', $mid);
    ikc(['ok' => true, 'id' => $mid]);
}

if ($islem === 'masraf_karar') {
    if (empty($Y['ik_masraf_onay'])) ikh('Masraf onaylama yetkiniz yok.', 403);
    $id = intval($girdi['id'] ?? 0);
    $m = $db->query("SELECT * FROM ik_masraflar WHERE id = $id")->fetch_assoc();
    if (!$m) ikh('Kayıt bulunamadı.', 404);
    if ($m['durum'] !== 'beklemede') ikh('Bu talep zaten sonuçlandırılmış.');
    $karar = ($girdi['karar'] ?? '') === 'onay' ? 'onaylandi' : 'reddedildi';
    $not = mb_substr(trim((string)($girdi['not'] ?? '')), 0, 500, 'UTF-8');
    $st = $db->prepare("UPDATE ik_masraflar SET durum = ?, onaylayan_id = ?, onay_notu = ?, onay_tarihi = NOW() WHERE id = ?");
    $st->bind_param('sisi', $karar, $uid, $not, $id);
    $st->execute();
    ik_bildir($db, intval($m['kullanici_id']), $uid, $karar === 'onaylandi' ? '✅ Masraf talebiniz onaylandı' : '❌ Masraf talebiniz reddedildi',
        ($karar === 'onaylandi' ? '✅ ' : '❌ ') . number_format($m['tutar'], 2, ',', '.') . ' ₺ tutarındaki masraf talebiniz <b>' . ik_h($benAd) . '</b> tarafından ' . ($karar === 'onaylandi' ? 'onaylandı' : 'reddedildi') . '.' . ($not !== '' ? ' Not: ' . ik_h($not) : ''), 'ik_masraf', $id);
    ikc(['ok' => true]);
}

if ($islem === 'masraf_sil') {
    $id = intval($girdi['id'] ?? 0);
    $m = $db->query("SELECT * FROM ik_masraflar WHERE id = $id")->fetch_assoc();
    if (!$m) ikh('Kayıt bulunamadı.', 404);
    if (intval($m['kullanici_id']) !== $uid && empty($Y['ik_masraf_onay'])) ikh('Bu kaydı silme yetkiniz yok.', 403);
    if ($m['durum'] !== 'beklemede' && empty($Y['ik_masraf_onay'])) ikh('Sonuçlandırılmış bir talebi yalnızca İK silebilir.', 403);
    $db->query("DELETE FROM ik_masraflar WHERE id = $id");
    ikc(['ok' => true]);
}

if ($islem === 'avans_ekle') {
    $kid = (!empty($Y['ik_avans_onay']) && !empty($girdi['kullanici_id'])) ? intval($girdi['kullanici_id']) : $uid;
    $tutar = max(0, (float)str_replace(',', '.', preg_replace('/[^\d,.-]/', '', (string)($girdi['tutar'] ?? '0'))));
    if ($tutar <= 0) ikh('Tutarı girin.');
    $taksit = max(1, min(24, intval($girdi['taksit'] ?? 1)));
    $tarih = ik_tarih($girdi['tarih'] ?? '') ?: null;
    $acik = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 500, 'UTF-8');
    $st = $db->prepare("INSERT INTO ik_avanslar (kullanici_id, tutar, taksit, istenen_tarih, aciklama, durum, olusturma_tarihi) VALUES (?, ?, ?, ?, ?, 'beklemede', NOW())");
    $st->bind_param('idiss', $kid, $tutar, $taksit, $tarih, $acik);
    if (!$st->execute()) ikh('Kaydedilemedi.', 500);
    $mid = intval($db->son_id());
    $kisiAd = $kid === $uid ? $benAd : ($db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $kid")->fetch_row()[0] ?? '');
    $onayciler = array_map('intval', ik_onay_alicilari($db, 'avans', 'ik_avans_onay', $kid)); $hedef = array_values(array_diff($onayciler, [$kid])); if (!$hedef) $hedef = $onayciler; // tek onaycı kendisiyse yine de haber ver
    foreach ($hedef as $a) ik_bildir($db, $a, $uid, '💵 Yeni avans talebi', '💵 <b>' . ik_h($kisiAd) . '</b> ' . number_format($tutar, 2, ',', '.') . ' ₺ avans talep etti' . ($taksit > 1 ? " ($taksit taksit)" : '') . '.', 'ik_avans', $mid);
    ikc(['ok' => true, 'id' => $mid]);
}

if ($islem === 'avans_karar') {
    if (empty($Y['ik_avans_onay'])) ikh('Avans onaylama yetkiniz yok.', 403);
    $id = intval($girdi['id'] ?? 0);
    $m = $db->query("SELECT * FROM ik_avanslar WHERE id = $id")->fetch_assoc();
    if (!$m) ikh('Kayıt bulunamadı.', 404);
    if ($m['durum'] !== 'beklemede') ikh('Bu talep zaten sonuçlandırılmış.');
    $karar = ($girdi['karar'] ?? '') === 'onay' ? 'onaylandi' : 'reddedildi';
    $not = mb_substr(trim((string)($girdi['not'] ?? '')), 0, 500, 'UTF-8');
    $st = $db->prepare("UPDATE ik_avanslar SET durum = ?, onaylayan_id = ?, onay_notu = ?, onay_tarihi = NOW() WHERE id = ?");
    $st->bind_param('sisi', $karar, $uid, $not, $id);
    $st->execute();
    ik_bildir($db, intval($m['kullanici_id']), $uid, $karar === 'onaylandi' ? '✅ Avans talebiniz onaylandı' : '❌ Avans talebiniz reddedildi',
        ($karar === 'onaylandi' ? '✅ ' : '❌ ') . number_format($m['tutar'], 2, ',', '.') . ' ₺ tutarındaki avans talebiniz <b>' . ik_h($benAd) . '</b> tarafından ' . ($karar === 'onaylandi' ? 'onaylandı' : 'reddedildi') . '.' . ($not !== '' ? ' Not: ' . ik_h($not) : ''), 'ik_avans', $id);
    ikc(['ok' => true]);
}

if ($islem === 'avans_sil') {
    $id = intval($girdi['id'] ?? 0);
    $m = $db->query("SELECT * FROM ik_avanslar WHERE id = $id")->fetch_assoc();
    if (!$m) ikh('Kayıt bulunamadı.', 404);
    if (intval($m['kullanici_id']) !== $uid && empty($Y['ik_avans_onay'])) ikh('Bu kaydı silme yetkiniz yok.', 403);
    if ($m['durum'] !== 'beklemede' && empty($Y['ik_avans_onay'])) ikh('Sonuçlandırılmış bir talebi yalnızca İK silebilir.', 403);
    $db->query("DELETE FROM ik_avanslar WHERE id = $id");
    ikc(['ok' => true]);
}

if ($islem === 'unvan_olustur') {
    if (empty($Y['ik_duzenle'])) ikh('Unvan oluşturma yetkiniz yok.', 403);
    $b = mb_substr(trim((string)($girdi['ad'] ?? '')), 0, 100, 'UTF-8'); if ($b === '') ikh('Unvan adı gerekli.');
    foreach (ik_liste_oku($db, 'unvan_listesi') as $x) if (mb_strtolower($x, 'UTF-8') === mb_strtolower($b, 'UTF-8')) ikh('Bu unvan zaten var.');
    ik_unvan_listeye_ekle($db, $b);
    ikc(['ok' => true]);
}
if ($islem === 'unvan_adlandir') {
    if (empty($Y['ik_duzenle'])) ikh('Unvan düzenleme yetkiniz yok.', 403);
    $eski = trim((string)($girdi['eski'] ?? '')); $yeni = mb_substr(trim((string)($girdi['yeni'] ?? '')), 0, 100, 'UTF-8');
    if ($eski === '' || $yeni === '') ikh('Unvan adı gerekli.');
    $e = $db->real_escape_string($eski); $y = $db->real_escape_string($yeni);
    $db->query("UPDATE ik_personel SET unvan = '$y' WHERE LOWER(unvan) = LOWER('$e')");
    $yn = []; foreach (ik_liste_oku($db, 'unvan_listesi') as $x) $yn[] = mb_strtolower($x, 'UTF-8') === mb_strtolower($eski, 'UTF-8') ? $yeni : $x;
    ik_ayar_yaz($db, 'unvan_listesi', implode("\n", array_values(array_unique($yn))));
    ikc(['ok' => true]);
}
if ($islem === 'unvan_sil') {
    if (empty($Y['ik_duzenle'])) ikh('Unvan silme yetkiniz yok.', 403);
    $b = trim((string)($girdi['ad'] ?? '')); $e = $db->real_escape_string($b);
    $n = intval($db->query("SELECT COUNT(*) FROM ik_personel WHERE LOWER(unvan) = LOWER('$e')")->fetch_row()[0] ?? 0);
    if ($n > 0) ikh("Bu unvanda $n kişi var; önce personelin unvanını değiştirin.");
    $l = array_values(array_filter(ik_liste_oku($db, 'unvan_listesi'), function ($x) use ($b) { return mb_strtolower($x, 'UTF-8') !== mb_strtolower($b, 'UTF-8'); }));
    ik_ayar_yaz($db, 'unvan_listesi', implode("\n", $l));
    ikc(['ok' => true]);
}

if ($islem === 'birim_olustur') {
    if (empty($Y['ik_duzenle'])) ikh('Birim oluşturma yetkiniz yok.', 403);
    $b = mb_substr(trim((string)($girdi['ad'] ?? '')), 0, 100, 'UTF-8'); if ($b === '') ikh('Birim adı gerekli.');
    foreach (preg_split('/\R/u', (string)ik_ayar($db, 'departman_listesi', '')) as $x) if (mb_strtolower(trim($x), 'UTF-8') === mb_strtolower($b, 'UTF-8')) ikh('Bu birim zaten var.');
    ik_birim_listeye_ekle($db, $b);
    ikc(['ok' => true]);
}
if ($islem === 'birim_adlandir') {
    if (empty($Y['ik_duzenle'])) ikh('Birim düzenleme yetkiniz yok.', 403);
    $eski = trim((string)($girdi['eski'] ?? '')); $yeni = mb_substr(trim((string)($girdi['yeni'] ?? '')), 0, 100, 'UTF-8');
    if ($eski === '' || $yeni === '') ikh('Birim adı gerekli.');
    $e = $db->real_escape_string($eski); $y = $db->real_escape_string($yeni);
    $db->query("UPDATE ik_personel SET departman = '$y' WHERE LOWER(departman) = LOWER('$e')");
    $db->query("UPDATE ik_onay_akis SET departman = '$y' WHERE LOWER(departman) = LOWER('$e')");
    $db->query("UPDATE ik_onay_bildirim SET departman = '$y' WHERE LOWER(departman) = LOWER('$e')");
    ik_birim_yon_sema($db); $db->query("UPDATE IGNORE ik_birim_yoneticileri SET birim = '$y' WHERE LOWER(birim) = LOWER('$e')");
    $l = array_filter(array_map('trim', preg_split('/\R/u', (string)ik_ayar($db, 'departman_listesi', ''))), 'strlen'); $yn = [];
    foreach ($l as $x) { $yn[] = mb_strtolower($x, 'UTF-8') === mb_strtolower($eski, 'UTF-8') ? $yeni : $x; }
    ik_ayar_yaz($db, 'departman_listesi', implode("\n", array_values(array_unique($yn))));
    ikc(['ok' => true]);
}
if ($islem === 'birim_sil') {
    if (empty($Y['ik_duzenle'])) ikh('Birim silme yetkiniz yok.', 403);
    $b = trim((string)($girdi['ad'] ?? '')); $e = $db->real_escape_string($b);
    $n = intval($db->query("SELECT COUNT(*) FROM ik_personel WHERE LOWER(departman) = LOWER('$e')")->fetch_row()[0] ?? 0);
    if ($n > 0) ikh("Bu birimde $n kişi var; önce personeli başka birime taşıyın veya birimden çıkarın.");
    $l = array_filter(array_map('trim', preg_split('/\R/u', (string)ik_ayar($db, 'departman_listesi', ''))), function ($x) use ($b) { return $x !== '' && mb_strtolower($x, 'UTF-8') !== mb_strtolower($b, 'UTF-8'); });
    ik_ayar_yaz($db, 'departman_listesi', implode("\n", $l));
    ik_birim_yon_sema($db); $db->query("DELETE FROM ik_birim_yoneticileri WHERE LOWER(birim) = LOWER('$e')");
    ikc(['ok' => true]);
}
if ($islem === 'birim_mudur') {
    if (empty($Y['ik_duzenle'])) ikh('Birim müdürü atama yetkiniz yok (personel düzenleme yetkisi gerekir).', 403);
    $birim = mb_substr(trim((string)($girdi['birim'] ?? '')), 0, 100, 'UTF-8'); if ($birim === '') ikh('Birim adı gerekli.');
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)json_decode((string)($girdi['ids'] ?? '[]'), true)))));
    if (count($ids) > 10) ikh('Bir birime en fazla 10 müdür atanabilir.');
    ik_birim_yon_sema($db);
    $gecerli = [];
    foreach ($ids as $kid) if ($db->query("SELECT 1 FROM kullanicilar WHERE id = $kid AND aktif = 1")->fetch_row()) $gecerli[] = $kid;
    $eski = []; $e = $db->real_escape_string($birim);
    $q = $db->query("SELECT kullanici_id FROM ik_birim_yoneticileri WHERE LOWER(birim) = LOWER('$e')"); if ($q) while ($r = $q->fetch_row()) $eski[] = intval($r[0]);
    $db->query("DELETE FROM ik_birim_yoneticileri WHERE LOWER(birim) = LOWER('$e')");
    $st = $db->prepare("INSERT IGNORE INTO ik_birim_yoneticileri (birim, kullanici_id, ekleyen_id) VALUES (?, ?, ?)");
    foreach ($gecerli as $kid) {
        $st->bind_param('sii', $birim, $kid, $uid); $st->execute();
        if ($kid !== $uid && !in_array($kid, $eski, true)) ik_bildir($db, $kid, $uid, '🏢 Birim müdürü oldunuz', '🏢 <b>' . ik_h($birim) . '</b> biriminin müdürü olarak atandınız.', 'ik_personel', $kid);
    }
    $guncel = ik_birim_mudur_esitle($db, $birim, $eski, $gecerli, $uid);
    ikc(['ok' => true, 'n' => count($gecerli), 'yonetici_guncel' => $guncel]);
}
if ($islem === 'birim_ata') {
    if (empty($Y['ik_duzenle'])) ikh('Birim atama yetkiniz yok (personel bilgilerini düzenleme yetkisi gerekir).', 403);
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)json_decode((string)($girdi['ids'] ?? '[]'), true)))));
    $birim = mb_substr(trim((string)($girdi['birim'] ?? '')), 0, 100, 'UTF-8');
    if (!$ids) ikh('Personel seçilmedi.');
    if (count($ids) > 500) ikh('Tek seferde en fazla 500 kişi seçilebilir.');
    ik_birim_listeye_ekle($db, $birim);
    $n = 0;
    foreach ($ids as $kid) {
        if (!$db->query("SELECT 1 FROM kullanicilar WHERE id = $kid")->fetch_row()) continue;
        ik_birim_yaz($db, $kid, $birim, $uid); $n++;
        if ($birim !== '') { ik_birim_yon_sema($db); $eb = $db->real_escape_string($birim); $mq2 = $db->query("SELECT kullanici_id FROM ik_birim_yoneticileri WHERE LOWER(birim) = LOWER('$eb') ORDER BY tarih LIMIT 1"); $mm = $mq2 ? $mq2->fetch_row() : null;
            if ($mm && intval($mm[0]) !== $kid) { $vq = $db->query("SELECT yonetici_id FROM ik_personel WHERE kullanici_id = $kid"); $var = $vq ? $vq->fetch_row() : null; if (!$var || !intval($var[0])) $db->query("UPDATE ik_personel SET yonetici_id = " . intval($mm[0]) . " WHERE kullanici_id = $kid"); } }
        if ($kid !== $uid) ik_bildir($db, $kid, $uid, '🏢 Biriminiz güncellendi', $birim === '' ? '🏢 Birim atamanız kaldırıldı.' : '🏢 <b>' . ik_h($birim) . '</b> birimine atandınız.', 'ik_personel', $kid);
    }
    ikc(['ok' => true, 'n' => $n]);
}

if ($islem === 'egitim_kaydet') {
    if (empty($Y['ik_egitim_yonet'])) ikh('Eğitim oluşturma yetkiniz yok.', 403);
    require_once __DIR__ . '/ik_etkinlik.php'; ik_etkinlik_sema($db);
    $id = intval($girdi['id'] ?? 0);
    $tur = ($girdi['tur'] ?? '') === 'toplanti' ? 'toplanti' : 'egitim';
    $ad = mb_substr(trim((string)($girdi['ad'] ?? '')), 0, 200, 'UTF-8'); if ($ad === '') ikh(($tur === 'toplanti' ? 'Toplantı' : 'Eğitim') . ' adını yazın.');
    $egitmen = mb_substr(trim((string)($girdi['egitmen'] ?? '')), 0, 150, 'UTF-8');
    $tarih = ik_tarih($girdi['tarih'] ?? '');
    $saat = ik_etkinlik_saat($girdi['saat'] ?? '');
    $yer = mb_substr(trim((string)($girdi['yer'] ?? '')), 0, 200, 'UTF-8');
    $sure = max(0, (float)str_replace(',', '.', (string)($girdi['sure_saat'] ?? '0')));
    $acik = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 1000, 'UTF-8');
    $bAc = !empty($girdi['bildirim_ac']) ? 1 : 0;
    $bSms = !empty($girdi['bildirim_sms']) ? 1 : 0; $bMail = !empty($girdi['bildirim_mail']) ? 1 : 0;
    $bGun = implode(',', ik_etkinlik_gunler(is_array($girdi['bildirim_gunler'] ?? null) ? implode(',', $girdi['bildirim_gunler']) : (string)($girdi['bildirim_gunler'] ?? '')));
    $degisti = false; $yeniMi = !$id;
    if ($id) {
        $eski = $db->query("SELECT tarih, saat, yer FROM ik_egitimler WHERE id = $id")->fetch_assoc();
        if (!$eski) ikh('Kayıt bulunamadı.', 404);
        $degisti = (string)$eski['tarih'] !== (string)$tarih || (string)$eski['saat'] !== $saat || (string)$eski['yer'] !== $yer;
        $st = $db->prepare("UPDATE ik_egitimler SET ad=?, egitmen=?, tarih=?, sure_saat=?, aciklama=?, tur=?, saat=?, yer=?, bildirim_ac=?, bildirim_sms=?, bildirim_mail=?, bildirim_gunler=? WHERE id=?");
        $st->bind_param('sssdssssiiisi', $ad, $egitmen, $tarih, $sure, $acik, $tur, $saat, $yer, $bAc, $bSms, $bMail, $bGun, $id);
        $st->execute();
    } else {
        $st = $db->prepare("INSERT INTO ik_egitimler (ad, egitmen, tarih, sure_saat, aciklama, tur, saat, yer, bildirim_ac, bildirim_sms, bildirim_mail, bildirim_gunler, olusturan_id, olusturma_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $st->bind_param('sssdssssiiisi', $ad, $egitmen, $tarih, $sure, $acik, $tur, $saat, $yer, $bAc, $bSms, $bMail, $bGun, $uid);
        $st->execute();
        $id = intval($db->son_id());
    }
    $katilimci = array_values(array_filter(array_map('intval', explode(',', (string)($girdi['katilimcilar'] ?? '')))));
    $oncekiler = ik_etkinlik_katilimcilar($db, $id);
    $yeniKisiler = array_values(array_diff($katilimci, $oncekiler));
    if ($katilimci) {
        $db->query("DELETE FROM ik_egitim_katilim WHERE egitim_id = $id AND kullanici_id NOT IN (" . implode(',', $katilimci) . ")");
        foreach ($katilimci as $kid) $db->query("INSERT IGNORE INTO ik_egitim_katilim (egitim_id, kullanici_id) VALUES ($id, $kid)");
    } else {
        $db->query("DELETE FROM ik_egitim_katilim WHERE egitim_id = $id");
    }
    // Bildirim açıksa SMS/e-posta/site içi (seçilen günlere göre); kapalıysa yalnız site içi davet bildirimi
    $gonderilen = 0;
    if ($bAc) $gonderilen = ik_etkinlik_kayit_bildir($db, $id, $yeniKisiler, $degisti, $yeniMi);
    else foreach ($yeniKisiler as $kid) ik_bildir($db, $kid, $uid, ($tur === 'toplanti' ? '📅 Toplantıya' : '🎓 Eğitime') . ' davet edildiniz', ($tur === 'toplanti' ? '📅' : '🎓') . ' <b>' . ik_h($ad) . '</b> kaydınız oluşturuldu' . ($tarih ? ' — ' . date('d.m.Y', strtotime($tarih)) . ($saat !== '' ? ' ' . $saat : '') : '') . '.', 'ik_egitim', $id);
    ikc(['ok' => true, 'id' => $id, 'bildirim' => $gonderilen]);
}

if ($islem === 'egitim_katilim_guncelle') {
    $egitimId = intval($girdi['egitim_id'] ?? 0);
    $kid = intval($girdi['kullanici_id'] ?? $uid);
    if ($kid !== $uid && empty($Y['ik_egitim_yonet'])) ikh('Bu kaydı güncelleme yetkiniz yok.', 403);
    $tamam = !empty($girdi['tamamlandi']) ? 1 : 0;
    $db->query("UPDATE ik_egitim_katilim SET tamamlandi = $tamam WHERE egitim_id = $egitimId AND kullanici_id = $kid");
    ikc(['ok' => true]);
}

if ($islem === 'egitim_sil') {
    if (empty($Y['ik_egitim_yonet'])) ikh('Eğitim silme yetkiniz yok.', 403);
    $id = intval($girdi['id'] ?? 0);
    $db->query("DELETE FROM ik_egitim_katilim WHERE egitim_id = $id");
    $db->query("DELETE FROM ik_egitim_bildirim_log WHERE egitim_id = $id");
    $db->query("DELETE FROM ik_egitimler WHERE id = $id");
    ikc(['ok' => true]);
}

if ($islem === 'performans_kaydet') {
    if (empty($Y['ik_performans_yonet'])) ikh('Performans değerlendirmesi girme yetkiniz yok.', 403);
    $kid = intval($girdi['kullanici_id'] ?? 0); if (!$kid) ikh('Personel seçin.');
    $donem = mb_substr(trim((string)($girdi['donem'] ?? '')), 0, 30, 'UTF-8'); if ($donem === '') ikh('Dönem yazın (örn. 2026 Q3).');
    $puan = max(0, min(10, (float)str_replace(',', '.', (string)($girdi['puan'] ?? '0'))));
    $notlar = mb_substr(trim((string)($girdi['notlar'] ?? '')), 0, 2000, 'UTF-8');
    $st = $db->prepare("INSERT INTO ik_performans (kullanici_id, donem, puan, degerlendiren_id, notlar, tarih) VALUES (?, ?, ?, ?, ?, NOW())");
    $st->bind_param('isdis', $kid, $donem, $puan, $uid, $notlar);
    if (!$st->execute()) ikh('Kaydedilemedi.', 500);
    ik_bildir($db, $kid, $uid, '⭐ Performans değerlendirmeniz eklendi', '⭐ <b>' . ik_h($donem) . '</b> dönemi için değerlendirmeniz girildi.', 'ik_performans', intval($db->son_id()));
    ikc(['ok' => true]);
}

if ($islem === 'performans_sil') {
    if (empty($Y['ik_performans_yonet'])) ikh('Silme yetkiniz yok.', 403);
    $db->query("DELETE FROM ik_performans WHERE id = " . intval($girdi['id'] ?? 0));
    ikc(['ok' => true]);
}

if ($islem === 'bordro_ekle') {
    if (empty($Y['ik_bordro_yonet'])) ikh('Bordro ekleme yetkiniz yok.', 403);
    $kid = intval($girdi['kullanici_id'] ?? 0); if (!$kid) ikh('Personel seçin.');
    $yil = intval($girdi['yil'] ?? 0); $ay = intval($girdi['ay'] ?? 0);
    if ($yil < 2000 || $ay < 1 || $ay > 12) ikh('Geçerli bir yıl / ay girin.');
    $brut = max(0, (float)str_replace(',', '.', (string)($girdi['brut'] ?? '0')));
    $net = max(0, (float)str_replace(',', '.', (string)($girdi['net'] ?? '0')));
    $kesinti = max(0, (float)str_replace(',', '.', (string)($girdi['kesinti'] ?? '0')));
    $dosyaAd = ''; $orj = '';
    if (!empty($_FILES['dosya']) && $_FILES['dosya']['error'] === UPLOAD_ERR_OK) {
        $f = $_FILES['dosya'];
        if (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) !== 'pdf') ikh('Bordro dosyası yalnızca PDF olabilir.');
        if ($f['size'] > 10 * 1024 * 1024) ikh('Dosya en fazla 10 MB olabilir.');
        if (!tb_dosya_icerik_uygun($f['tmp_name'], 'pdf')) ikh('Dosya geçerli bir PDF değil.');
        $klasor = tb_yukleme_dizin('ik_bordro');
        if (!is_dir($klasor)) @mkdir($klasor, 0755, true);
        if (!is_file($klasor . '/.htaccess')) @file_put_contents($klasor . '/.htaccess', "<IfModule mod_authz_core.c>\n  Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n  Deny from all\n</IfModule>\n");
        $dosyaAd = $kid . '_' . $yil . '_' . $ay . '_' . bin2hex(random_bytes(6)) . '.pdf';
        if (!move_uploaded_file($f['tmp_name'], $klasor . '/' . $dosyaAd)) ikh('Dosya kaydedilemedi.', 500);
        $orj = mb_substr(basename($f['name']), 0, 255, 'UTF-8');
    }
    $st = $db->prepare("INSERT INTO ik_bordrolar (kullanici_id, yil, ay, brut, net, kesinti, dosya, orijinal_ad, ekleyen_id, tarih) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                         ON DUPLICATE KEY UPDATE brut=VALUES(brut), net=VALUES(net), kesinti=VALUES(kesinti), dosya=IF(VALUES(dosya)<>'',VALUES(dosya),dosya), orijinal_ad=IF(VALUES(orijinal_ad)<>'',VALUES(orijinal_ad),orijinal_ad)");
    $st->bind_param('iiidddssi', $kid, $yil, $ay, $brut, $net, $kesinti, $dosyaAd, $orj, $uid);
    if (!$st->execute()) ikh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
    ik_bildir($db, $kid, $uid, '💰 Bordronuz eklendi', '💰 ' . $ay . '/' . $yil . ' dönemi bordronuz sisteme eklendi.', 'ik_bordro', $kid);
    ikc(['ok' => true]);
}

if ($islem === 'bordro_sil') {
    if (empty($Y['ik_bordro_yonet'])) ikh('Silme yetkiniz yok.', 403);
    $id = intval($girdi['id'] ?? 0);
    $r = $db->query("SELECT dosya FROM ik_bordrolar WHERE id = $id")->fetch_row();
    if ($r && $r[0]) @unlink(tb_yukleme_dizin('ik_bordro') . '/' . basename($r[0]));
    $db->query("DELETE FROM ik_bordrolar WHERE id = $id");
    ikc(['ok' => true]);
}

if ($islem === 'ayarlar_kaydet') {
    if (empty($Y['ik_ayarlar']) && empty($Y['ik_bildirim_yonet'])) ikh('İK ayarlarını değiştirme yetkiniz yok.', 403);
    if (!empty($Y['ik_ayarlar'])) foreach (['departman_listesi', 'unvan_listesi', 'onay_alici', 'izin_onay_gerekli', 'aylik_izin_bilgisi'] as $k) {
        if (!array_key_exists($k, $girdi)) continue;
        $v = mb_substr(trim((string)$girdi[$k]), 0, 2000, 'UTF-8');
        $st = $db->prepare("INSERT INTO ik_ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)");
        $st->bind_param('ss', $k, $v);
        $st->execute();
    }
    ikc(['ok' => true]);
}

// ───────────── POST: onay akışı + bildirim kanalları ─────────────
if (in_array($islem, ['akis_kaydet', 'akis_sil', 'akis_gorunum', 'bildirim_ayar_kaydet', 'kanal_test', 'mail_logo_yukle'], true) && empty($Y['ik_akis_yonet'])) ikh('Onay akışı ve bildirim kanallarını yönetme yetkiniz yok.', 403);

if ($islem === 'akis_gorunum') {
    $g = (string)($girdi['gorunum'] ?? '');
    if (!in_array($g, ['zaman', 'avatar', 'metro', 'hap', 'neon', 'klasik'], true)) ikh('Geçersiz görünüm.');
    ik_ayar_yaz($db, 'akis_gorunum_u' . $uid, $g);
    ikc(['ok' => true, 'gorunum' => $g]);
}
if ($islem === 'akis_kaydet') {
    $dep = mb_substr(trim((string)($girdi['departman'] ?? '')), 0, 100, 'UTF-8');
    if ($dep === '') ikh('Birim adı boş olamaz.');
    $v = json_decode((string)($girdi['veri'] ?? ''), true);
    if (!is_array($v)) ikh('Akış verisi okunamadı.');
    $kn = function ($x) { return [empty($x['site']) ? 0 : 1, empty($x['sms']) ? 0 : 1, empty($x['mail']) ? 0 : 1]; };
    $adimlar = array_slice(is_array($v['adimlar'] ?? null) ? $v['adimlar'] : [], 0, 8);
    $var = function ($id) use ($db) { return $id === IK_ONAYCI_YONETICI || $db->query("SELECT 1 FROM kullanicilar WHERE id = " . intval($id) . " AND aktif = 1")->num_rows > 0; };
    foreach ($adimlar as $a) if (!$var(intval($a['id'] ?? 0))) ikh('Seçilen onaycılardan biri bulunamadı ya da pasif.');
    $db->query("DELETE FROM ik_onay_akis WHERE LOWER(departman) = LOWER('" . $db->real_escape_string($dep) . "')");
    $db->query("DELETE FROM ik_onay_bildirim WHERE LOWER(departman) = LOWER('" . $db->real_escape_string($dep) . "')");
    $sira = 0;
    foreach ($adimlar as $a) {
        $sira++; $id = intval($a['id']); list($ks, $km, $ke) = $kn($a); if (!$ks && !$km && !$ke) $ks = 1;
        $st = $db->prepare("INSERT INTO ik_onay_akis (departman, sira, onaylayici_id, k_site, k_sms, k_mail, vekil_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $vk = intval($a['vekil_id'] ?? 0); if ($vk === $id || ($vk > 0 && !$var($vk))) $vk = 0; $st->bind_param('siiiiii', $dep, $sira, $id, $ks, $km, $ke, $vk); $st->execute();
    }
    list($ks, $km, $ke) = $kn($v['talep_sahibi'] ?? ['site' => 1]); if (!$ks && !$km && !$ke) $ks = 1;
    $z = 0;
    $st = $db->prepare("INSERT INTO ik_onay_bildirim (departman, rol, kullanici_id, k_site, k_sms, k_mail) VALUES (?, 'talep_sahibi', ?, ?, ?, ?)");
    $st->bind_param('siiii', $dep, $z, $ks, $km, $ke); $st->execute();
    $goruldu = [];
    foreach (array_slice(is_array($v['bilgi'] ?? null) ? $v['bilgi'] : [], 0, 20) as $b) {
        $id = intval($b['id'] ?? 0); if ($id <= 0 || isset($goruldu[$id]) || !$var($id)) continue; $goruldu[$id] = true;
        list($ks, $km, $ke) = $kn($b); if (!$ks && !$km && !$ke) $ks = 1;
        $st = $db->prepare("INSERT INTO ik_onay_bildirim (departman, rol, kullanici_id, k_site, k_sms, k_mail) VALUES (?, 'bilgi', ?, ?, ?, ?)");
        $st->bind_param('siiii', $dep, $id, $ks, $km, $ke); $st->execute();
    }
    if ($db->hatano()) ikh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
    ikc(['ok' => true]);
}
if ($islem === 'akis_sil') {
    $dep = $db->real_escape_string(trim((string)($girdi['departman'] ?? '')));
    $db->query("DELETE FROM ik_onay_akis WHERE LOWER(departman) = LOWER('$dep')");
    $db->query("DELETE FROM ik_onay_bildirim WHERE LOWER(departman) = LOWER('$dep')");
    ikc(['ok' => true]);
}
if ($islem === 'bildirim_ayar_kaydet') {
    $izinli = ['sms_saglayici' => ['', 'netgsm', 'iletimerkezi', 'ozel'], 'smtp_guvenlik' => ['tls', 'ssl', 'yok']];
    foreach (IK_KANAL_AYARLARI as $k) {
        if (!array_key_exists($k, $girdi)) continue;
        $d = trim((string)$girdi[$k]);
        if (in_array($k, IK_GIZLI_AYARLAR, true)) { if ($d === '') continue; }          // boş gelen şifre = değiştirme
        if (isset($izinli[$k]) && !in_array($d, $izinli[$k], true)) ikh('Geçersiz seçim: ' . $k);
        if ($k === 'smtp_port') $d = (string)max(1, min(65535, intval($d ?: 587)));
        if ($k === 'mail_gonderen' && $d !== '' && !filter_var($d, FILTER_VALIDATE_EMAIL)) ikh('Gönderen e-posta adresi geçersiz.');
        if (($k === 'mail_renk' || $k === 'mail_renk2') && $d !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $d)) ikh('Renk #RRGGBB biçiminde olmalı.');
        if ($k === 'mail_logo' && $d !== '' && !preg_match('~^(https?://|uploads/k\d+/ik_logo/)~i', $d)) ikh('Logo adresi http(s):// ile başlamalı.');
        if ($k === 'site_url' && $d !== '' && !preg_match('~^https?://[^\s]+$~i', $d)) ikh('Site adresi http:// ya da https:// ile başlamalı.');
        if ($k === 'sms_ozel_url' && $d !== '' && !preg_match('~^https?://~i', $d)) ikh('Özel SMS adresi http(s):// ile başlamalı.');
        ik_ayar_yaz($db, $k, mb_substr($d, 0, 1500, 'UTF-8'));
    }
    foreach (['sms_sifre', 'smtp_sifre'] as $k) if (!empty($girdi['temizle_' . $k])) ik_ayar_yaz($db, $k, '');
    ikc(['ok' => true]);
}
if ($islem === 'mail_logo_yukle') {
    $f = $_FILES['dosya'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) ikh('Logo dosyası alınamadı.');
    if ($f['size'] > 1048576) ikh('Logo en fazla 1 MB olabilir.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $uz = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? '';
    if ($uz === '') ikh('Yalnızca PNG, JPG, WEBP veya GIF yükleyin (e-posta uygulamaları SVG göstermez).');
    $kl = tb_yukleme_dizin('ik_logo', true);
    foreach (glob($kl . '/logo_*') ?: [] as $eski) @unlink($eski);
    $ad = 'logo_' . substr(md5(uniqid('', true)), 0, 10) . '.' . $uz;
    if (!move_uploaded_file($f['tmp_name'], $kl . '/' . $ad)) ikh('Logo kaydedilemedi (uploads klasörü yazılabilir mi?).', 500);
    ik_ayar_yaz($db, 'mail_logo', tb_yukleme_web('ik_logo') . '/' . $ad);
    ikc(['ok' => true, 'logo' => tb_yukleme_web('ik_logo') . '/' . $ad]);
}
if ($islem === 'kanal_test') {
    $tur = (string)($girdi['tur'] ?? ''); $hedef = trim((string)($girdi['hedef'] ?? ''));
    if ($tur === 'sms') {
        $r = ik_sms_gonder($db, $hedef, 'İK sistemi test mesajı: SMS ayarlarınız çalışıyor. ✔');
        ik_kanal_log($db, $uid, 'sms', $hedef, 'Test', $r['ok'], $r['sonuc']);
    } elseif ($tur === 'mail') {
        $r = ik_mail_gonder($db, $hedef, 'İK test e-postası', ik_mail_sablon('E-posta ayarlarınız çalışıyor', 'Bu bir test iletisidir. İzin talepleri ve onay bildirimleri bu adresten gönderilecek.', ik_site_url($db) . 'ik.php', 'İK sayfasını aç'));
        ik_kanal_log($db, $uid, 'mail', $hedef, 'Test', $r['ok'], $r['sonuc']);
    } else ikh('Geçersiz test türü.');
    ikc(['ok' => true, 'basarili' => $r['ok'], 'sonuc' => $r['sonuc']]);
}

ikh('Bilinmeyen işlem.');
