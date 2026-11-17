<?php
// ============================================================
// giris_islem.php — Personel Giriş-Çıkış JSON uç noktası
//  Herkes:   GET durum | POST pin_kur | POST damga (tur=giris|cikis)
//  Herkese açık (anahtarlı): GET ekran_kod   (ofis ekranı — giris_ekran.php)
//  İK (ik_giris_gor):   GET bugun | kayitlar
//  İK (ik_giris_yonet): GET cihazlar | noktalar | ayarlar · POST kayit_duzelt | kayit_ekle | kayit_sil | cihaz_karar | pin_sifirla | nokta_kaydet | nokta_sil | ayar_kaydet
// Tüm yazma işlemleri CSRF korumalıdır. Saatler yalnızca SUNUCU saatiyle yazılır.
// ============================================================
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/giris_ortak.php';
require_once __DIR__ . '/giris_webauthn.php';
require_once __DIR__ . '/ik_ortak.php'; // ik_h(), ik_bildir() vb. (cihaz uyarılarında argüman olarak kullanılıyor)
if (session_status() === PHP_SESSION_NONE) session_start();
$islem = (string)($_GET['islem'] ?? $_POST['islem'] ?? '');

function gc($v, $k = 200) { if ($k === 200 && !empty($GLOBALS['IK_HLOG'])) { $L = $GLOBALS['IK_HLOG']; $GLOBALS['IK_HLOG'] = null; ik_ek_harici_yaz($L[0], $L[1], $L[2], $v); } http_response_code($k); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo json_encode($v, JSON_UNESCAPED_UNICODE); exit; }
function gh($m, $k = 400, $ek = []) { gc(array_merge(['ok' => false, 'hata' => $m], $ek), $k); }

// ── Ofis ekranı kodu (giriş gerekmez; nokta anahtarı ile korunur) ──
if ($islem === 'ekran_kod') {
    // Ofis ekranı oturumsuz açılır: kurum, adresteki k parametresinden gelir (nokta anahtarı ayrıca doğrulanır)
    $ek = pf_kurum($db, intval($_GET['k'] ?? 0));
    if (!$ek || !pf_kurum_kullanilabilir($ek)) gh('Geçersiz ekran adresi.', 403);
    $db->kurum = intval($ek['id']);
    giris_sema($db);
    $id = intval($_GET['nokta'] ?? 0); $anahtar = (string)($_GET['anahtar'] ?? '');
    $q = $db->query("SELECT id, ad, ekran_anahtari FROM giris_noktalari WHERE id = $id AND aktif = 1"); $n = $q ? $q->fetch_assoc() : null;
    if (!$n || $n['ekran_anahtari'] === '' || !hash_equals((string)$n['ekran_anahtari'], $anahtar)) gh('Geçersiz ekran adresi.', 403);
    $p = (int)floor(time() / 30);
    gc(['ok' => true, 'ad' => $n['ad'], 'kod' => giris_kod($n, $p), 'kalan' => 30 - (time() % 30)]);
}

$uid = intval($_SESSION['kullanici_id'] ?? 0);
if ($uid <= 0) gh('Oturum süresi doldu, lütfen yeniden giriş yapın.', 401);
try { require_once __DIR__ . '/platform_paket.php'; pf_paket_sema($db); if (!in_array($islem, ['damga', 'durum', 'ekran_kod'], true) && in_array('gc_bugun', pf_paket_kilitli($db), true)) gh('Bu özellik kurumunuzun paketinde yok veya süresi bitti.', 402); } catch (Throwable $e) {}
if ($_SERVER['REQUEST_METHOD'] === 'POST') { try { require_once __DIR__ . '/ik_ek.php'; $GLOBALS['IK_HLOG'] = [$db, $uid, ik_ek_harici_hazirla($db, $islem, $_POST)]; } catch (Throwable $e) {} }
giris_sema($db);
$A = giris_ayarlar($db);
giris_otomatik_kapat($db, $A['otomatik_kapat_saat']); // 20 saati (ayarlanabilir) aşan açık kayıtlar kendiliğinden kapanır
$YT = giris_yetki($db, $uid);
$ben = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $uid")->fetch_row(); $benAd = $ben ? (string)$ben[0] : '';

function g_csrf() {
    $g = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_csrf'] ?? '');
    if (empty($_SESSION['tb_csrf']) || !hash_equals((string)$_SESSION['tb_csrf'], $g)) gh('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.', 419);
}
function g_cihaz_hash($t) { $t = (string)$t; return preg_match('/^[a-f0-9]{32,64}$/', $t) ? hash('sha256', $t) : ''; }
function g_kayit_satir($r) {
    $etk = []; foreach (array_filter(explode(',', (string)$r['supheli'])) as $k) $etk[] = ['kod' => $k, 'ad' => GIRIS_SUPHELI_ETIKET[$k] ?? $k];
    $gb = $r['giris_bilgi'] ? json_decode($r['giris_bilgi'], true) : []; $cb = $r['cikis_bilgi'] ? json_decode($r['cikis_bilgi'], true) : [];
    return ['id' => intval($r['id']), 'kullanici_id' => intval($r['kullanici_id']), 'ad' => (string)($r['ad_soyad'] ?? ''), 'nokta' => (string)($r['nokta_ad'] ?? ''),
        'giris' => (string)$r['giris_zamani'], 'cikis' => $r['cikis_zamani'] ? (string)$r['cikis_zamani'] : null,
        'sure_sn' => $r['cikis_zamani'] ? max(0, intval($r['sure_sn'])) : null, 'supheli' => $etk, 'not' => (string)$r['not_metni'],
        'giris_mesafe' => $gb['mesafe'] ?? null, 'cikis_mesafe' => $cb['mesafe'] ?? null, 'giris_ip' => $gb['ip'] ?? '', 'cihaz' => $gb['cihaz'] ?? ''];
}
/** PIN doğrula (5 hatada 15 dk kilit). Başarısızlıkta gh() ile çıkar. */
function g_pin_dogrula($db, $uid, $pin) {
    $q = $db->query("SELECT pin_hash, deneme, (kilit_bitis IS NOT NULL AND kilit_bitis > NOW()) kilitli, TIMESTAMPDIFF(MINUTE, NOW(), kilit_bitis) kalan FROM giris_pin WHERE kullanici_id = $uid"); $r = $q ? $q->fetch_assoc() : null;
    if (!$r) gh('Önce kişisel PIN\'inizi oluşturun.', 403, ['pin_yok' => true]);
    if (intval($r['kilitli']) === 1) gh('Çok fazla hatalı PIN: ' . max(1, intval($r['kalan']) + 1) . ' dakika sonra tekrar deneyin.', 429);
    if (!password_verify((string)$pin, $r['pin_hash'])) {
        $d = intval($r['deneme']) + 1;
        if ($d >= 5) { $db->query("UPDATE giris_pin SET deneme = 0, kilit_bitis = NOW() + INTERVAL 15 MINUTE WHERE kullanici_id = $uid"); gh('PIN 5 kez yanlış girildi; 15 dakika kilitlendi.', 429); }
        $db->query("UPDATE giris_pin SET deneme = $d WHERE kullanici_id = $uid");
        gh('PIN hatalı (' . (5 - $d) . ' hakkınız kaldı).', 403);
    }
    $db->query("UPDATE giris_pin SET deneme = 0, kilit_bitis = NULL WHERE kullanici_id = $uid");
}
/** İK'ya (ik_giris_yonet yetkililerine) bildirim: zil + varsayılan kanallar. Aynı olay $saat saat içinde tekrar gönderilmez. */
function g_ik_uyar($db, $uid, $anahtar, $baslik, $mesaj, $tablo = 'ik_giris', $saat = 12) {
    try {
        require_once __DIR__ . '/ik_ortak.php';
        if (function_exists('ik_sema')) ik_sema($db);
        if (!ik_log_uygun($db, $uid, 'gc_' . $anahtar, $saat)) return;
        foreach (vy_yetkililer($db, 'ik', 'ik_giris_yonet') as $a) if (intval($a) !== intval($uid)) ik_bildir($db, intval($a), intval($uid), $baslik, $mesaj, $tablo, 0);
    } catch (Throwable $e) {}
}
function g_kisi_adi($db, $uid) { $r = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = " . intval($uid))->fetch_row(); return $r[0] ?? ('#' . $uid); }

/** Cihaz kaydını doğrular / gerekirse oluşturur. Dönüş: cihaz_hash (kayıt için) */
function g_cihaz_kontrol($db, $uid, $token, $A) {
    $h = g_cihaz_hash($token);
    if ($h === '') gh('Cihaz kimliği okunamadı; sayfayı yenileyip tekrar deneyin.', 400);
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? ''); $ip = giris_ip();
    $q = $db->query("SELECT id, durum FROM giris_cihazlar WHERE kullanici_id = $uid AND cihaz_hash = '$h'"); $r = $q ? $q->fetch_assoc() : null;
    if ($r) {
        if ($r['durum'] === 'reddedildi') { g_ik_uyar($db, $uid, 'red_' . substr($h, 0, 8), '🚫 Reddedilmiş cihazla giriş denemesi', '<b>' . ik_h(g_kisi_adi($db, $uid)) . '</b> İK tarafından reddedilen bir cihazdan (' . ik_h(giris_cihaz_adi($ua)) . ') giriş yapmayı denedi.', 'ik_giris', 6); }
        if ($r['durum'] === 'reddedildi') gh('Bu cihaz İK tarafından reddedildi. İK ile iletişime geçin.', 403);
        if ($r['durum'] === 'bekliyor') gh('Bu cihaz henüz İK tarafından onaylanmadı. Onaylandıktan sonra giriş yapabilirsiniz.', 403, ['cihaz_bekliyor' => true]);
        $db->query("UPDATE giris_cihazlar SET son_kullanim = NOW(), ip = '" . $db->real_escape_string($ip) . "' WHERE id = " . intval($r['id']));
        return $h;
    }
    $sq = $db->query("SELECT COUNT(*) FROM giris_cihazlar WHERE kullanici_id = $uid AND durum = 'onayli'"); $onayli = $sq ? intval($sq->fetch_row()[0]) : 0;
    $durum = ($onayli === 0 && !empty($A['cihaz_ilk_otomatik'])) ? 'onayli' : 'bekliyor';
    $st = $db->prepare("INSERT INTO giris_cihazlar (kullanici_id, cihaz_hash, ad, durum, son_kullanim, ip) VALUES (?, ?, ?, ?, NOW(), ?)");
    $ad = giris_cihaz_adi($ua); $st->bind_param('issss', $uid, $h, $ad, $durum, $ip); $st->execute();
    $ortak = $db->query("SELECT u.ad_soyad FROM giris_cihazlar c JOIN kullanicilar u ON u.id = c.kullanici_id WHERE c.cihaz_hash = '$h' AND c.kullanici_id <> $uid LIMIT 1")->fetch_row();
    $kAd = g_kisi_adi($db, $uid);
    if ($ortak) g_ik_uyar($db, $uid, 'mukerrer_' . substr($h, 0, 8), '⚠️ Mükerrer cihaz: aynı telefon başka kişide de kayıtlı', '<b>' . ik_h($kAd) . '</b> için kaydedilen cihaz (' . ik_h($ad) . '), <b>' . ik_h($ortak[0]) . '</b> adına da kayıtlı. Giriş-çıkışı başkası adına yapılıyor olabilir; Cihazlar sayfasından kontrol edin.', 'ik_giris', 24);
    if ($durum === 'bekliyor') g_ik_uyar($db, $uid, 'yeni_' . substr($h, 0, 8), '📱 Yeni cihaz onayınızı bekliyor', '<b>' . ik_h($kAd) . '</b> yeni bir cihazdan (' . ik_h($ad) . ') giriş yapmak istiyor. Onaylamak için Cihazlar sayfasını açın.', 'ik_giris', 24);
    if ($durum === 'bekliyor') gh('Bu yeni bir cihaz: İK onayı bekleniyor. Onaylandıktan sonra giriş yapabilirsiniz.', 403, ['cihaz_bekliyor' => true]);
    return $h;
}

// ─────────────────────────── GET (herkes) ───────────────────────────
// ── Parmak izi / Face ID (WebAuthn) ──
if ($islem === 'webauthn_secenekler_kayit') { try { gc(wa_secenek_kayit($db, $uid, $benAd ?: 'Kullanıcı')); } catch (Throwable $e) { gh($e->getMessage()); } }
if ($islem === 'webauthn_secenekler_giris') { try { gc(wa_secenek_giris($db, $uid)); } catch (Throwable $e) { gh($e->getMessage()); } }
if ($islem === 'webauthn_liste') {
    wa_sema($db); $l = []; $q = $db->query("SELECT id, ad, olusturma, son FROM giris_webauthn WHERE kullanici_id = $uid ORDER BY id");
    if ($q) while ($r = $q->fetch_assoc()) $l[] = ['id' => intval($r['id']), 'ad' => (string)$r['ad'], 'olusturma' => $r['olusturma'], 'son' => $r['son']];
    gc(['ok' => true, 'liste' => $l]);
}
if ($islem === 'durum') {
    $simdi = intval($db->query("SELECT UNIX_TIMESTAMP()")->fetch_row()[0]);
    $q = $db->query("SELECT k.*, UNIX_TIMESTAMP(k.giris_zamani) g_ts, n.ad nokta_ad FROM giris_kayitlari k LEFT JOIN giris_noktalari n ON n.id = k.nokta_id WHERE k.kullanici_id = $uid AND k.cikis_zamani IS NULL ORDER BY k.id DESC LIMIT 1");
    $acik = $q ? $q->fetch_assoc() : null;
    $bq = $db->query("SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, giris_zamani, COALESCE(cikis_zamani, NOW()))), 0) FROM giris_kayitlari WHERE kullanici_id = $uid AND giris_zamani >= CURDATE()");
    $bugunSn = $bq ? intval($bq->fetch_row()[0]) : 0;
    $noktalar = []; $koduVar = false;
    $q = $db->query("SELECT id, ad, enlem, boylam, yaricap, kod_zorunlu FROM giris_noktalari WHERE aktif = 1 ORDER BY ad");
    if ($q) while ($r = $q->fetch_assoc()) { $noktalar[] = ['id' => intval($r['id']), 'ad' => $r['ad'], 'enlem' => $r['enlem'] === null ? null : (float)$r['enlem'], 'boylam' => $r['boylam'] === null ? null : (float)$r['boylam'], 'yaricap' => intval($r['yaricap']), 'kod' => intval($r['kod_zorunlu']) === 1]; }
    $pinVar = (bool)$db->query("SELECT 1 FROM giris_pin WHERE kullanici_id = $uid")->num_rows;
    $cihaz = 'yeni'; $h = g_cihaz_hash($_GET['cihaz'] ?? '');
    if ($h !== '') { $cq = $db->query("SELECT durum FROM giris_cihazlar WHERE kullanici_id = $uid AND cihaz_hash = '$h'"); $cr = $cq ? $cq->fetch_row() : null; if ($cr) $cihaz = $cr[0]; }
    $gecmis = [];
    $q = $db->query("SELECT k.*, TIMESTAMPDIFF(SECOND, k.giris_zamani, k.cikis_zamani) sure_sn, n.ad nokta_ad FROM giris_kayitlari k LEFT JOIN giris_noktalari n ON n.id = k.nokta_id WHERE k.kullanici_id = $uid ORDER BY k.id DESC LIMIT 12");
    if ($q) while ($r = $q->fetch_assoc()) $gecmis[] = g_kayit_satir($r);
    $cTur = giris_cihaz_turu($_GET);
    $tani = 'platform: ' . (trim((string)($_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? ''), '"') ?: 'yok') . ' · tarayıcı platformu: ' . substr((string)($_GET['platform'] ?? '?'), 0, 30) . ' · dokunmatik: ' . intval($_GET['dokunmatik'] ?? 0) . ' · parmak işaretçi: ' . (intval($_GET['kaba'] ?? 0) ? 'evet' : 'hayır') . ' · ekran: ' . intval($_GET['ekran'] ?? 0) . ' px';
    gc(['ok' => true, 'cihaz_turu' => $cTur, 'tani' => $tani, 'cihaz_izinli' => giris_cihaz_izinli($A['izinli_cihaz'], $cTur), 'izinli_cihaz_ad' => giris_izinli_etiket($A['izinli_cihaz']), 'cihaz_turu_ad' => GIRIS_CIHAZ_TURLERI[$cTur] ?? 'Bilinmeyen cihaz',
        'simdi' => $simdi, 'tz' => date_default_timezone_get(), 'calisiyor' => (bool)$acik, 'giris_ts' => $acik ? intval($acik['g_ts']) : null, 'giris' => $acik ? $acik['giris_zamani'] : null, 'nokta_ad' => $acik ? (string)$acik['nokta_ad'] : '',
        'bugun_sn' => $bugunSn, 'hedef_sn' => (int)round($A['hedef_saat'] * 3600), 'noktalar' => $noktalar, 'pin_var' => $pinVar, 'cihaz' => $cihaz,
        'webauthn_var' => wa_say($db, $uid) > 0,
        'ayar' => ['cikis_konum' => !empty($A['cikis_konum']), 'ilk_otomatik' => !empty($A['cihaz_ilk_otomatik']), 'webauthn' => !empty($A['webauthn_zorunlu']), 'pin' => !empty($A['pin_zorunlu']), 'cihaz' => !empty($A['cihaz_zorunlu']), 'konum' => !empty($A['konum_zorunlu']), 'max_dogruluk' => intval($A['max_dogruluk']), 'yontem' => in_array((string)$A['yontem'], GIRIS_YONTEMLER, true) ? (string)$A['yontem'] : 'kaydir'],
        'gecmis' => $gecmis, 'yetki' => $YT]);
}

// ─────────────────────────── GET (İK) ───────────────────────────
if ($islem === 'bugun') {
    if (!$YT['gor']) gh('Bu bilgiyi görme yetkiniz yok.', 403);
    $liste = [];
    $q = $db->query("SELECT u.id, u.ad_soyad, u.gorev,
            (SELECT k.giris_zamani FROM giris_kayitlari k WHERE k.kullanici_id = u.id AND k.giris_zamani >= CURDATE() ORDER BY k.id ASC LIMIT 1) ilk_giris,
            (SELECT k.cikis_zamani FROM giris_kayitlari k WHERE k.kullanici_id = u.id AND k.giris_zamani >= CURDATE() ORDER BY k.id DESC LIMIT 1) son_cikis,
            (SELECT COUNT(*) FROM giris_kayitlari k WHERE k.kullanici_id = u.id AND k.cikis_zamani IS NULL) acik,
            (SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, k.giris_zamani, COALESCE(k.cikis_zamani, NOW()))), 0) FROM giris_kayitlari k WHERE k.kullanici_id = u.id AND k.giris_zamani >= CURDATE()) sn
        FROM kullanicilar u WHERE u.aktif = 1 ORDER BY u.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) {
        $durum = intval($r['acik']) > 0 ? 'iceride' : ($r['ilk_giris'] ? 'cikti' : 'gelmedi');
        $liste[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'gorev' => (string)($r['gorev'] ?? ''), 'durum' => $durum, 'ilk_giris' => $r['ilk_giris'], 'son_cikis' => $r['son_cikis'], 'sure' => giris_sure_yaz($r['sn'])];
    }
    $bek = $db->query("SELECT COUNT(*) FROM giris_cihazlar WHERE durum = 'bekliyor'")->fetch_row()[0] ?? 0;
    gc(['ok' => true, 'liste' => $liste, 'bekleyen_cihaz' => intval($bek)]);
}
if ($islem === 'kayitlar') {
    if (!$YT['gor']) gh('Bu bilgiyi görme yetkiniz yok.', 403);
    $bas = preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string)($_GET['bas'] ?? '')) ? $_GET['bas'] : date('Y-m-d', strtotime('-6 days'));
    $bit = preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string)($_GET['bit'] ?? '')) ? $_GET['bit'] : date('Y-m-d');
    $kisi = intval($_GET['kullanici_id'] ?? 0); $sadeceSupheli = ($_GET['supheli'] ?? '') === '1';
    $w = "k.giris_zamani >= '$bas 00:00:00' AND k.giris_zamani <= '$bit 23:59:59'" . ($kisi ? " AND k.kullanici_id = $kisi" : '') . ($sadeceSupheli ? " AND k.supheli <> ''" : '');
    $liste = [];
    $q = $db->query("SELECT k.*, u.ad_soyad, n.ad nokta_ad, TIMESTAMPDIFF(SECOND, k.giris_zamani, k.cikis_zamani) sure_sn FROM giris_kayitlari k LEFT JOIN kullanicilar u ON u.id = k.kullanici_id LEFT JOIN giris_noktalari n ON n.id = k.nokta_id WHERE $w ORDER BY k.giris_zamani DESC LIMIT 500");
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = g_kayit_satir($r);
    $kisiler = []; $q = $db->query("SELECT id, ad_soyad FROM kullanicilar WHERE aktif = 1 ORDER BY ad_soyad"); if ($q) while ($r = $q->fetch_assoc()) $kisiler[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad']];
    gc(['ok' => true, 'liste' => $liste, 'kisiler' => $kisiler, 'bas' => $bas, 'bit' => $bit]);
}
/** "HH:MM" → dakika */
function g_dk($t) { return preg_match('/^(\d{1,2}):(\d{2})/', (string)$t, $m) ? intval($m[1]) * 60 + intval($m[2]) : null; }

// ── Geç kalma / erken gelme / erken çıkma / geç çıkma raporu (kişi bazlı ya da tümü) ──
if ($islem === 'rapor') {
    if (!$YT['gor']) gh('Bu raporu görme yetkiniz yok.', 403);
    $bas = preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string)($_GET['bas'] ?? '')) ? $_GET['bas'] : date('Y-m-01');
    $bit = preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string)($_GET['bit'] ?? '')) ? $_GET['bit'] : date('Y-m-d');
    if (strtotime($bit) < strtotime($bas)) gh('Bitiş tarihi başlangıçtan önce olamaz.');
    if ((strtotime($bit) - strtotime($bas)) / 86400 > 366) gh('En fazla 1 yıllık aralık seçilebilir.');
    $kisi = intval($_GET['kullanici_id'] ?? 0);
    $bugun = (string)$db->query("SELECT CURDATE()")->fetch_row()[0];
    $tol = intval($A['tolerans_dk']); $gunler = array_map('intval', explode(',', (string)$A['calisma_gunleri']));
    $vBas = g_dk($A['mesai_baslangic']); $vBit = g_dk($A['mesai_bitis']);
    // Kişiye özel vardiyalar
    $ozel = []; $q = $db->query("SELECT kullanici_id, baslangic, bitis FROM giris_vardiyalar");
    if ($q) while ($r = $q->fetch_assoc()) $ozel[intval($r['kullanici_id'])] = $r;
    // Personel listesi
    $kisiler = []; $q = $db->query("SELECT id, ad_soyad, gorev FROM kullanicilar WHERE aktif = 1" . ($kisi ? " AND id = $kisi" : '') . " ORDER BY ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) $kisiler[intval($r['id'])] = $r;
    // Aralıktaki kayıtlar: kişi + gün bazında
    $gun = []; // [kid][tarih] = ['ilk'=>dk, 'son'=>dk|null, 'acik'=>bool, 'sn'=>int, 'sup'=>int, 'ilk_s'=>'HH:MM', 'son_s'=>'HH:MM']
    $q = $db->query("SELECT kullanici_id, DATE(giris_zamani) t, giris_zamani, cikis_zamani, supheli, TIMESTAMPDIFF(SECOND, giris_zamani, COALESCE(cikis_zamani, NOW())) sn
                     FROM giris_kayitlari WHERE giris_zamani >= '$bas 00:00:00' AND giris_zamani <= '$bit 23:59:59'" . ($kisi ? " AND kullanici_id = $kisi" : '') . " ORDER BY giris_zamani");
    if ($q) while ($r = $q->fetch_assoc()) {
        $k = intval($r['kullanici_id']); $t = $r['t'];
        if (!isset($kisiler[$k])) continue;
        $g = &$gun[$k][$t];
        if (!isset($g)) $g = ['ilk' => null, 'ilk_s' => '', 'son' => null, 'son_s' => '', 'acik' => false, 'sn' => 0, 'sup' => 0];
        $gd = g_dk(substr($r['giris_zamani'], 11, 5));
        if ($g['ilk'] === null || $gd < $g['ilk']) { $g['ilk'] = $gd; $g['ilk_s'] = substr($r['giris_zamani'], 11, 5); }
        if ($r['cikis_zamani'] === null) $g['acik'] = true;
        else { $cd = g_dk(substr($r['cikis_zamani'], 11, 5)); if (substr($r['cikis_zamani'], 0, 10) === $t && ($g['son'] === null || $cd > $g['son'])) { $g['son'] = $cd; $g['son_s'] = substr($r['cikis_zamani'], 11, 5); } }
        $g['sn'] += max(0, intval($r['sn'])); if ($r['supheli'] !== '') $g['sup']++;
        unset($g);
    }
    // Onaylı izinler + puantajdaki izin / rapor / tatil günleri (devamsızlığa sayılmaz)
    $muaf = []; // [kid][tarih] = true
    $q = $db->query("SELECT kullanici_id, baslangic, bitis FROM ik_izin_talepleri WHERE durum = 'onaylandi' AND baslangic <= '$bit' AND bitis >= '$bas'");
    if ($q) while ($r = $q->fetch_assoc()) for ($t = max(strtotime($r['baslangic']), strtotime($bas)); $t <= min(strtotime($r['bitis']), strtotime($bit)); $t += 86400) $muaf[intval($r['kullanici_id'])][date('Y-m-d', $t)] = true;
    try { $q = $db->query("SELECT kullanici_id, tarih FROM ik_puantaj WHERE tarih BETWEEN '$bas' AND '$bit' AND durum IN ('izinli','rapor','resmi_tatil','haftasonu')"); if ($q) while ($r = $q->fetch_assoc()) $muaf[intval($r['kullanici_id'])][$r['tarih']] = true; } catch (Throwable $e) {}

    $GUN_AD = [1 => 'Pzt', 2 => 'Sal', 3 => 'Çar', 4 => 'Per', 5 => 'Cum', 6 => 'Cmt', 7 => 'Paz'];
    $satirlar = []; $gunluk = [];
    foreach ($kisiler as $k => $kr) {
        $vb = $vBas; $vt = $vBit;
        if (isset($ozel[$k])) { if ($ozel[$k]['baslangic'] !== null && g_dk($ozel[$k]['baslangic']) !== null) $vb = g_dk($ozel[$k]['baslangic']); if ($ozel[$k]['bitis'] !== null && g_dk($ozel[$k]['bitis']) !== null) $vt = g_dk($ozel[$k]['bitis']); }
        $o = ['id' => $k, 'ad' => $kr['ad_soyad'], 'gorev' => (string)($kr['gorev'] ?? ''), 'vardiya' => sprintf('%02d:%02d–%02d:%02d', intdiv($vb, 60), $vb % 60, intdiv($vt, 60), $vt % 60),
              'calisilan_gun' => 0, 'gec_gun' => 0, 'gec_dk' => 0, 'erken_gun' => 0, 'erken_dk' => 0, 'erken_cikis_gun' => 0, 'erken_cikis_dk' => 0, 'gec_cikis_gun' => 0, 'gec_cikis_dk' => 0, 'devamsiz_gun' => 0, 'toplam_sn' => 0, 'supheli' => 0, 'en_cok_gec' => 0];
        for ($t = strtotime($bas); $t <= strtotime($bit); $t += 86400) {
            $tar = date('Y-m-d', $t); $hg = intval(date('N', $t)); $calisma = in_array($hg, $gunler, true);
            $g = $gun[$k][$tar] ?? null; $d = ['tarih' => $tar, 'gun' => $GUN_AD[$hg], 'ilk' => '', 'son' => '', 'sure_sn' => 0, 'gec_dk' => 0, 'erken_dk' => 0, 'erken_cikis_dk' => 0, 'gec_cikis_dk' => 0, 'durum' => ''];
            if ($g) {
                $o['calisilan_gun']++; $o['toplam_sn'] += $g['sn']; $o['supheli'] += $g['sup'];
                $d['ilk'] = $g['ilk_s']; $d['son'] = $g['son_s']; $d['sure_sn'] = $g['sn'];
                if ($calisma) {
                    $f = $g['ilk'] - $vb;
                    if ($f > $tol) { $d['gec_dk'] = $f; $o['gec_gun']++; $o['gec_dk'] += $f; $o['en_cok_gec'] = max($o['en_cok_gec'], $f); }
                    elseif ($f < 0) { $d['erken_dk'] = -$f; $o['erken_gun']++; $o['erken_dk'] += -$f; }
                    if ($vt > $vb && $g['son'] !== null && !$g['acik']) {
                        $c = $g['son'] - $vt;
                        if ($c < -$tol) { $d['erken_cikis_dk'] = -$c; $o['erken_cikis_gun']++; $o['erken_cikis_dk'] += -$c; }
                        elseif ($c > $tol) { $d['gec_cikis_dk'] = $c; $o['gec_cikis_gun']++; $o['gec_cikis_dk'] += $c; }
                    }
                }
                $d['durum'] = $g['acik'] ? ($tar === $bugun ? 'iceride' : 'cikis_yok') : ($d['gec_dk'] > 0 ? 'gec' : ($calisma ? 'zamaninda' : 'hafta_sonu'));
            } else {
                if (!$calisma) $d['durum'] = 'tatil_gunu';
                elseif (!empty($muaf[$k][$tar])) $d['durum'] = 'izinli';
                elseif ($tar < $bugun) { $d['durum'] = 'devamsiz'; $o['devamsiz_gun']++; }
                else $d['durum'] = $tar === $bugun ? 'bekleniyor' : 'gelecek';
            }
            if ($kisi) $gunluk[] = $d;
        }
        $satirlar[] = $o;
    }
    $top = ['gec_dk' => 0, 'gec_kisi' => 0, 'erken_dk' => 0, 'erken_cikis_dk' => 0, 'gec_cikis_dk' => 0, 'devamsiz_gun' => 0];
    foreach ($satirlar as $o) { $top['gec_dk'] += $o['gec_dk']; if ($o['gec_gun'] > 0) $top['gec_kisi']++; $top['erken_dk'] += $o['erken_dk']; $top['erken_cikis_dk'] += $o['erken_cikis_dk']; $top['gec_cikis_dk'] += $o['gec_cikis_dk']; $top['devamsiz_gun'] += $o['devamsiz_gun']; }
    $kisiListe = []; $q = $db->query("SELECT id, ad_soyad FROM kullanicilar WHERE aktif = 1 ORDER BY ad_soyad"); if ($q) while ($r = $q->fetch_assoc()) $kisiListe[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad']];
    gc(['ok' => true, 'bas' => $bas, 'bit' => $bit, 'satirlar' => $satirlar, 'gunluk' => $gunluk, 'toplam' => $top, 'kisiler' => $kisiListe,
        'vardiya' => ['baslangic' => $A['mesai_baslangic'], 'bitis' => $A['mesai_bitis'], 'tolerans' => $tol, 'gunler' => $gunler]]);
}
if ($islem === 'vardiyalar') {
    if (!$YT['yonet']) gh('Bu bölüm için yönetim yetkiniz yok.', 403);
    $ozel = []; $q = $db->query("SELECT kullanici_id, baslangic, bitis FROM giris_vardiyalar"); if ($q) while ($r = $q->fetch_assoc()) $ozel[intval($r['kullanici_id'])] = $r;
    $liste = []; $q = $db->query("SELECT id, ad_soyad, gorev FROM kullanicilar WHERE aktif = 1 ORDER BY ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) { $o = $ozel[intval($r['id'])] ?? null; $liste[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'gorev' => (string)($r['gorev'] ?? ''), 'baslangic' => $o ? (string)$o['baslangic'] : '', 'bitis' => $o ? (string)$o['bitis'] : '']; }
    gc(['ok' => true, 'liste' => $liste, 'varsayilan' => ['baslangic' => $A['mesai_baslangic'], 'bitis' => $A['mesai_bitis']]]);
}
if ($islem === 'cihazlar' || $islem === 'noktalar' || $islem === 'ayarlar') {
    if (!$YT['yonet']) gh('Bu bölüm için yönetim yetkiniz yok.', 403);
    if ($islem === 'cihazlar') {
        $liste = [];
        $q = $db->query("SELECT c.*, u.ad_soyad, (SELECT COUNT(*) FROM giris_pin p WHERE p.kullanici_id = c.kullanici_id) pin_var FROM giris_cihazlar c LEFT JOIN kullanicilar u ON u.id = c.kullanici_id ORDER BY (c.durum = 'bekliyor') DESC, u.ad_soyad, c.id DESC LIMIT 500");
        if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'kullanici_id' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'cihaz' => $r['ad'], 'durum' => $r['durum'], 'ilk' => $r['ilk_kullanim'], 'son' => $r['son_kullanim'], 'ip' => $r['ip'], 'pin_var' => intval($r['pin_var']) > 0];
        gc(['ok' => true, 'liste' => $liste]);
    }
    if ($islem === 'noktalar') {
        $liste = [];
        $q = $db->query("SELECT * FROM giris_noktalari ORDER BY aktif DESC, ad");
        if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'ad' => $r['ad'], 'enlem' => $r['enlem'] === null ? '' : (string)(float)$r['enlem'], 'boylam' => $r['boylam'] === null ? '' : (string)(float)$r['boylam'], 'yaricap' => intval($r['yaricap']), 'ip_kisit' => $r['ip_kisit'], 'kod_zorunlu' => intval($r['kod_zorunlu']) === 1, 'aktif' => intval($r['aktif']) === 1, 'ekran_anahtari' => $r['ekran_anahtari']];
        gc(['ok' => true, 'liste' => $liste]);
    }
    gc(['ok' => true, 'ayarlar' => $A]);
}

// ─────────────────────────── POST ───────────────────────────
g_csrf();

if ($islem === 'webauthn_kayit') { try { wa_kayit($db, $uid, $_POST); gc(['ok' => true]); } catch (Throwable $e) { gh($e->getMessage()); } }
if ($islem === 'webauthn_sil') { wa_sema($db); $db->query("DELETE FROM giris_webauthn WHERE id = " . intval($_POST['id'] ?? 0) . " AND kullanici_id = $uid"); gc(['ok' => true]); }

// ── Kişisel PIN oluştur / değiştir ──
if ($islem === 'pin_kur') {
    $pin = (string)($_POST['pin'] ?? ''); $pin2 = (string)($_POST['pin2'] ?? '');
    if (!preg_match('/^\d{4,6}$/', $pin)) gh('PIN 4 ile 6 rakamdan oluşmalı.');
    if ($pin !== $pin2) gh('PIN\'ler aynı değil.');
    if (preg_match('/^(\d)\1+$/', $pin) || in_array($pin, ['1234', '12345', '123456', '0123', '4321', '1122', '2580'], true)) gh('Bu PIN çok kolay tahmin edilir; başka bir PIN seçin.');
    $var = $db->query("SELECT 1 FROM giris_pin WHERE kullanici_id = $uid")->num_rows > 0;
    if ($var) g_pin_dogrula($db, $uid, (string)($_POST['eski_pin'] ?? ''));
    $hash = password_hash($pin, PASSWORD_DEFAULT);
    $st = $db->prepare("INSERT INTO giris_pin (kullanici_id, pin_hash, deneme, kilit_bitis, guncelleme_tarihi) VALUES (?, ?, 0, NULL, NOW()) ON DUPLICATE KEY UPDATE pin_hash = VALUES(pin_hash), deneme = 0, kilit_bitis = NULL, guncelleme_tarihi = NOW()");
    $st->bind_param('is', $uid, $hash); $st->execute();
    gc(['ok' => true]);
}

// ── Giriş / çıkış damgası ──
if ($islem === 'damga') {
    $tur = ($_POST['tur'] ?? '') === 'cikis' ? 'cikis' : 'giris';
    // 0) Cihaz türü: yalnızca izin verilen cihazlardan (varsayılan: Android telefon) giriş-çıkış yapılabilir
    $cTur = giris_cihaz_turu($_POST);
    if (!giris_cihaz_izinli($A['izinli_cihaz'], $cTur)) gh('Giriş-çıkış yalnızca ' . giris_izinli_etiket($A['izinli_cihaz']) . ' ile yapılabilir (bu cihaz: ' . (GIRIS_CIHAZ_TURLERI[$cTur] ?? 'bilinmeyen') . ').', 403, ['cihaz_izinsiz' => true]);
    // 1) Kişisel PIN
    if (!empty($A['pin_zorunlu'])) g_pin_dogrula($db, $uid, (string)($_POST['pin'] ?? ''));
    // 1b) Parmak izi / Face ID
    if (!empty($A['webauthn_zorunlu'])) {
        if (wa_say($db, $uid) === 0) gh('Önce parmak izinizi / Face ID\'nizi kaydetmeniz gerekiyor.', 400, ['webauthn_yok' => true]);
        try { wa_dogrula($db, $uid, $_POST['webauthn'] ?? ''); } catch (Throwable $e) { gh($e->getMessage(), 403); }
    }
    // 2) Onaylı cihaz
    $cihazHash = !empty($A['cihaz_zorunlu']) ? g_cihaz_kontrol($db, $uid, $_POST['cihaz'] ?? '', $A) : g_cihaz_hash($_POST['cihaz'] ?? '');
    // 3) Konum / IP / ofis kodu
    $konumGerekli = $tur === 'giris' ? !empty($A['konum_zorunlu']) : !empty($A['cikis_konum']);
    $bilgi = ['ip' => giris_ip(), 'cihaz' => giris_cihaz_adi($_SERVER['HTTP_USER_AGENT'] ?? ''), 'cihaz_turu' => $cTur, 'cihaz_hash' => substr($cihazHash, 0, 12)];
    $supheli = []; $noktaId = null; $cevapNokta = ''; $cevapMesafe = null;
    $lat = is_numeric($_POST['enlem'] ?? null) ? (float)$_POST['enlem'] : null; $lon = is_numeric($_POST['boylam'] ?? null) ? (float)$_POST['boylam'] : null; $acc = is_numeric($_POST['dogruluk'] ?? null) ? (float)$_POST['dogruluk'] : null;
    if ($lat !== null) { $bilgi['enlem'] = round($lat, 6); $bilgi['boylam'] = round($lon, 6); $bilgi['dogruluk'] = $acc; }
    if (!empty($_POST['sahte_konum'])) {
        // Mobil uygulama telefonda sahte konum (mock location / konum benzetimi) algıladı: kayıt yapılmaz
        $supheli[] = 'sahte_konum'; $bilgi['sahte_konum'] = 1;
        gh('Sahte konum algılandı. Konum benzetme uygulamasını kapatıp tekrar deneyin; bu deneme kayıt altına alındı.', 403, ['sahte_konum' => true]);
    }
    if ($konumGerekli) {
        if ($lat === null || $lon === null || abs($lat) > 90 || abs($lon) > 180) gh('Konum alınamadı. Konum iznini verip GPS\'in açık olduğundan emin olun.', 400, ['konum_yok' => true]);
        if ($acc === null || $acc > $A['max_dogruluk']) gh('Konum doğruluğu yetersiz (' . ($acc === null ? '?' : round($acc)) . ' m; en fazla ' . intval($A['max_dogruluk']) . ' m). Açık alanda GPS\'i açarak tekrar deneyin.', 400, ['konum_yok' => true]);
        $sec = intval($_POST['nokta_id'] ?? 0); $bulunan = null; $enYakin = null; $enYakinMesafe = null; $secilenMesafe = null;
        $q = $db->query("SELECT * FROM giris_noktalari WHERE aktif = 1 AND enlem IS NOT NULL AND boylam IS NOT NULL");
        if ($q) while ($n = $q->fetch_assoc()) {
            $m = giris_mesafe($lat, $lon, (float)$n['enlem'], (float)$n['boylam']);
            if ($enYakinMesafe === null || $m < $enYakinMesafe) { $enYakinMesafe = $m; $enYakin = $n; }
            if (intval($n['id']) === $sec) { $secilenMesafe = $m; $bulunan = $n; }
        }
        if (!$enYakin) gh('Henüz bir geçiş noktası tanımlanmamış. İK ile iletişime geçin.', 400);
        $hedef = $bulunan ?: $enYakin; $mesafe = $bulunan ? $secilenMesafe : $enYakinMesafe;
        if ($mesafe > intval($hedef['yaricap'])) gh('Konum dışındasınız: “' . $hedef['ad'] . '” noktasına ' . round($mesafe) . ' m uzaktasınız (izin verilen: ' . intval($hedef['yaricap']) . ' m).', 403, ['mesafe' => round($mesafe)]);
        if (!giris_ip_eslesir(giris_ip(), $hedef['ip_kisit'])) gh('Ofis ağı dışındasınız: bu geçiş noktası yalnızca ofis internetinden (Wi-Fi) yapılabilir.', 403);
        if (intval($hedef['kod_zorunlu']) === 1 && !giris_kod_dogru($hedef, $_POST['kod'] ?? '')) gh('Ofis ekranındaki 6 haneli kod hatalı ya da süresi geçmiş (kod 30 saniyede bir yenilenir).', 403, ['kod_hata' => true]);
        $noktaId = intval($hedef['id']); $cevapNokta = (string)$hedef['ad']; $cevapMesafe = round($mesafe); $bilgi['mesafe'] = round($mesafe); $bilgi['nokta'] = $hedef['ad'];
        if ($acc < 1.5) $supheli[] = 'tam_dogruluk';
    } else {
        $sec = intval($_POST['nokta_id'] ?? 0); if ($sec > 0) { $r = $db->query("SELECT id FROM giris_noktalari WHERE id = $sec AND aktif = 1")->fetch_row(); if ($r) $noktaId = intval($r[0]); }
        if ($noktaId) { $nr = $db->query("SELECT ad FROM giris_noktalari WHERE id = $noktaId")->fetch_row(); $cevapNokta = $nr ? (string)$nr[0] : ''; }
        $supheli[] = 'konumsuz';
    }
    // Cihaz saati sunucu saatinden 5 dk'dan fazla farklıysa işaretle
    $istemciTs = is_numeric($_POST['ts'] ?? null) ? (int)$_POST['ts'] : 0;
    if ($istemciTs > 0 && abs($istemciTs - time()) > 300) { $supheli[] = 'saat_farki'; $bilgi['saat_farki_sn'] = $istemciTs - time(); }
    if ($cihazHash !== '') { $oq = $db->query("SELECT 1 FROM giris_kayitlari k WHERE k.kullanici_id <> $uid AND k.giris_bilgi LIKE '%\"cihaz_hash\":\"" . $db->real_escape_string(substr($cihazHash, 0, 12)) . "\"%' AND k.giris_zamani > DATE_SUB(NOW(), INTERVAL 30 DAY) LIMIT 1"); if ($oq && $oq->fetch_row()) $supheli[] = 'ortak_cihaz'; }
    foreach (['ortak_cihaz' => ['🔁 Mükerrer cihaz kullanımı', 'aynı cihazdan başka kişi adına da giriş-çıkış yapılmış'], 'tam_dogruluk' => ['📍 Şüpheli konum', 'konum doğruluğu gerçek dışı mükemmel (sahte konum olabilir)'], 'saat_farki' => ['🕒 Cihaz saati farklı', 'telefon saati sunucudan 5 dakikadan fazla farklı']] as $kod => $b)
        if (in_array($kod, $supheli, true)) g_ik_uyar($db, $uid, 'sup_' . $kod, $b[0], '<b>' . ik_h(g_kisi_adi($db, $uid)) . '</b> kaydında şüpheli durum: ' . $b[1] . '. Giriş-çıkış kayıtlarında işaretli görünür.', 'ik_giris_kayit', 24);
    $bilgiJson = $db->real_escape_string(json_encode($bilgi, JSON_UNESCAPED_UNICODE));

    // 4) Kaydı yaz (çift dokunuşa karşı 20 sn kilidi)
    $son = $db->query("SELECT TIMESTAMPDIFF(SECOND, GREATEST(giris_zamani, COALESCE(cikis_zamani, giris_zamani)), NOW()) FROM giris_kayitlari WHERE kullanici_id = $uid ORDER BY id DESC LIMIT 1")->fetch_row();
    if ($son && $son[0] !== null && intval($son[0]) < 20) gh('Az önce bir işlem yaptınız; lütfen birkaç saniye bekleyin.', 429);
    $acik = $db->query("SELECT id, TIMESTAMPDIFF(HOUR, giris_zamani, NOW()) saat FROM giris_kayitlari WHERE kullanici_id = $uid AND cikis_zamani IS NULL ORDER BY id DESC LIMIT 1")->fetch_assoc();
    if ($tur === 'giris') {
        if ($acik) gh('Zaten içeridesiniz (giriş yapılmış). Önce çıkış yapın.', 409); // 20 saati aşan kayıtlar zaten otomatik kapanmış olur
        $sup = $db->real_escape_string(implode(',', $supheli));
        $db->query("INSERT INTO giris_kayitlari (kullanici_id, nokta_id, giris_zamani, giris_bilgi, supheli) VALUES ($uid, " . ($noktaId ?: 'NULL') . ", NOW(), '$bilgiJson', '$sup')");
        if ($db->hatano()) gh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
        gc(['ok' => true, 'mesaj' => 'Giriş kaydedildi. İyi çalışmalar!', 'zaman' => date('Y-m-d H:i:s'), 'nokta_ad' => $cevapNokta, 'mesafe' => $cevapMesafe]);
    }
    if (!$acik) gh('Açık bir giriş kaydınız yok (önce giriş yapmalısınız).', 409);
    $sup = $db->real_escape_string(implode(',', $supheli));
    $db->query("UPDATE giris_kayitlari SET cikis_zamani = NOW(), cikis_bilgi = '$bilgiJson', supheli = CONCAT(supheli, IF(supheli = '' OR '$sup' = '', '', ','), '$sup') WHERE id = " . intval($acik['id']));
    if ($db->hatano()) gh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
    // Günlük toplam → İK puantajı (İK elle bir şey girmemişse "tam / yarım gün" olarak otomatik yazılır)
    try {
        $sn = intval($db->query("SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, giris_zamani, COALESCE(cikis_zamani, NOW()))), 0) FROM giris_kayitlari WHERE kullanici_id = $uid AND DATE(giris_zamani) = CURDATE()")->fetch_row()[0]);
        $durum = $sn >= $A['hedef_saat'] * 3600 * 0.75 ? 'tam' : ($sn >= $A['hedef_saat'] * 3600 * 0.3 ? 'yarim' : 'yarim');
        $db->query("INSERT IGNORE INTO ik_puantaj (kullanici_id, tarih, durum, not_metni, giren_id, guncelleme_tarihi) VALUES ($uid, CURDATE(), '$durum', 'Giriş-çıkış kaydından otomatik', NULL, NOW())");
    } catch (Throwable $e) {}
    gc(['ok' => true, 'mesaj' => 'Çıkış kaydedildi. Güle güle!', 'zaman' => date('Y-m-d H:i:s'), 'nokta_ad' => $cevapNokta, 'mesafe' => $cevapMesafe]);
}

// ─────────────────────────── POST (İK yönetim) ───────────────────────────
if (!$YT['yonet']) gh('Bu işlem için yönetim yetkiniz yok.', 403);

function g_zaman($v) { $v = trim(str_replace('T', ' ', (string)$v)); $t = strtotime($v); return ($v !== '' && $t) ? date('Y-m-d H:i:s', $t) : null; }

if ($islem === 'kayit_duzelt' || $islem === 'kayit_ekle') {
    $giris = g_zaman($_POST['giris'] ?? ''); $cikis = g_zaman($_POST['cikis'] ?? ''); $not = mb_substr(trim((string)($_POST['not'] ?? '')), 0, 300, 'UTF-8');
    if (!$giris) gh('Giriş zamanı geçersiz.');
    if ($cikis && strtotime($cikis) < strtotime($giris)) gh('Çıkış zamanı girişten önce olamaz.');
    if ($not === '') gh('Düzeltme nedenini (not) yazın — kayıt denetim için saklanır.');
    if ($islem === 'kayit_ekle') {
        $k = intval($_POST['kullanici_id'] ?? 0); if (!$db->query("SELECT 1 FROM kullanicilar WHERE id = $k")->num_rows) gh('Personel bulunamadı.', 404);
        $st = $db->prepare("INSERT INTO giris_kayitlari (kullanici_id, giris_zamani, cikis_zamani, supheli, not_metni, duzelten_id) VALUES (?, ?, ?, 'duzeltildi', ?, ?)");
        $st->bind_param('isssi', $k, $giris, $cikis, $not, $uid); $st->execute();
    } else {
        $id = intval($_POST['id'] ?? 0);
        $st = $db->prepare("UPDATE giris_kayitlari SET giris_zamani = ?, cikis_zamani = ?, not_metni = ?, duzelten_id = ?, supheli = IF(FIND_IN_SET('duzeltildi', supheli), supheli, CONCAT(supheli, IF(supheli = '', '', ','), 'duzeltildi')) WHERE id = ?");
        $st->bind_param('sssii', $giris, $cikis, $not, $uid, $id); $st->execute();
    }
    if ($db->hatano()) gh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
    gc(['ok' => true]);
}
if ($islem === 'vardiya_kaydet') {
    // Toplu: kayitlar = JSON [{kullanici_id, baslangic, bitis}] — boş saat = varsayılan vardiyaya dön
    $liste = json_decode((string)($_POST['kayitlar'] ?? '[]'), true);
    if (!is_array($liste)) gh('Vardiya bilgisi okunamadı.');
    foreach ($liste as $v) {
        $k = intval($v['kullanici_id'] ?? 0); $b = trim((string)($v['baslangic'] ?? '')); $t = trim((string)($v['bitis'] ?? ''));
        if ($k <= 0) continue;
        foreach ([$b, $t] as $x) if ($x !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $x)) gh('Vardiya saati SS:DD biçiminde olmalı.');
        if ($b === '' && $t === '') { $db->query("DELETE FROM giris_vardiyalar WHERE kullanici_id = $k"); continue; }
        $bs = $b === '' ? null : $b; $ts = $t === '' ? null : $t;
        $st = $db->prepare("INSERT INTO giris_vardiyalar (kullanici_id, baslangic, bitis) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE baslangic = VALUES(baslangic), bitis = VALUES(bitis)");
        $st->bind_param('iss', $k, $bs, $ts); $st->execute();
    }
    gc(['ok' => true]);
}
if ($islem === 'kayit_sil') { $db->query("DELETE FROM giris_kayitlari WHERE id = " . intval($_POST['id'] ?? 0)); gc(['ok' => true]); }
if ($islem === 'cihaz_karar') {
    $id = intval($_POST['id'] ?? 0); $k = (string)($_POST['karar'] ?? '');
    if ($k === 'sil') $db->query("DELETE FROM giris_cihazlar WHERE id = $id");
    elseif (in_array($k, ['onayli', 'reddedildi'], true)) $db->query("UPDATE giris_cihazlar SET durum = '$k' WHERE id = $id");
    else gh('Geçersiz karar.');
    gc(['ok' => true]);
}
if ($islem === 'pin_sifirla') { $k = intval($_POST['kullanici_id'] ?? 0); $db->query("DELETE FROM giris_pin WHERE kullanici_id = $k"); gc(['ok' => true]); }
if ($islem === 'nokta_kaydet') {
    $id = intval($_POST['id'] ?? 0); $ad = mb_substr(trim((string)($_POST['ad'] ?? '')), 0, 120, 'UTF-8');
    if ($ad === '') gh('Nokta adı gerekli.');
    $en = trim((string)($_POST['enlem'] ?? '')); $bo = trim((string)($_POST['boylam'] ?? ''));
    if (($en !== '' && (!is_numeric($en) || abs($en) > 90)) || ($bo !== '' && (!is_numeric($bo) || abs($bo) > 180))) gh('Enlem / boylam geçersiz.');
    $enSql = $en === '' ? null : (float)$en; $boSql = $bo === '' ? null : (float)$bo;
    $yar = max(10, min(5000, intval($_POST['yaricap'] ?? 100))); $ip = mb_substr(trim((string)($_POST['ip_kisit'] ?? '')), 0, 500, 'UTF-8');
    $kod = !empty($_POST['kod_zorunlu']) ? 1 : 0; $aktif = isset($_POST['aktif']) && $_POST['aktif'] === '0' ? 0 : 1;
    if ($id) {
        $st = $db->prepare("UPDATE giris_noktalari SET ad = ?, enlem = ?, boylam = ?, yaricap = ?, ip_kisit = ?, kod_zorunlu = ?, aktif = ? WHERE id = ?");
        $st->bind_param('sddisiii', $ad, $enSql, $boSql, $yar, $ip, $kod, $aktif, $id); $st->execute();
    } else {
        $anahtar = bin2hex(random_bytes(16));
        $st = $db->prepare("INSERT INTO giris_noktalari (ad, enlem, boylam, yaricap, ip_kisit, kod_zorunlu, ekran_anahtari, aktif) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $st->bind_param('sddisisi', $ad, $enSql, $boSql, $yar, $ip, $kod, $anahtar, $aktif); $st->execute();
    }
    if ($db->hatano()) gh('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
    gc(['ok' => true]);
}
if ($islem === 'nokta_sil') { $id = intval($_POST['id'] ?? 0); $db->query("UPDATE giris_kayitlari SET nokta_id = NULL WHERE nokta_id = $id"); $db->query("DELETE FROM giris_noktalari WHERE id = $id"); gc(['ok' => true]); }
if ($islem === 'ayar_kaydet') {
    if (($_POST['yontem'] ?? '') === 'parmak') $_POST['webauthn_zorunlu'] = 1; // parmak izi yöntemi biyometrik doğrulamayı zorunlu kılar
    foreach (GIRIS_VARSAYILAN as $a => $v) {
        if (!array_key_exists($a, $_POST) || $a === 'gunluk_rapor_son' || $a === 'gunluk_rapor_sonuc') continue;
        $d = $_POST[$a];
        if ($a === 'hedef_saat') $d = max(1, min(24, (float)$d));
        elseif ($a === 'max_dogruluk') $d = max(10, min(1000, intval($d)));
        elseif ($a === 'tolerans_dk') $d = max(0, min(120, intval($d)));
        elseif ($a === 'otomatik_kapat_saat') $d = max(4, min(48, intval($d)));
        elseif ($a === 'mesai_baslangic' || $a === 'mesai_bitis') { if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$d)) gh('Mesai saati SS:DD biçiminde olmalı (örn. 09:00).'); $d = (string)$d; }
        elseif ($a === 'izinli_cihaz') { $g = array_values(array_intersect(array_map('trim', explode(',', (string)$d)), array_keys(GIRIS_CIHAZ_TURLERI))); if (!$g) gh('En az bir cihaz türüne izin verin (aksi halde kimse giriş yapamaz).'); $d = implode(',', $g); }
        elseif ($a === 'calisma_gunleri') { $g = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)$d)), function ($x) { return $x >= 1 && $x <= 7; }))); sort($g); if (!$g) gh('En az bir çalışma günü seçin.'); $d = implode(',', $g); }
        elseif ($a === 'gunluk_rapor_saat') { if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$d)) gh('Rapor saati SS:DD biçiminde olmalı (örn. 11:00).'); $d = (string)$d; }
        elseif ($a === 'gunluk_rapor_alicilar') {
            require_once __DIR__ . '/giris_rapor_mail.php';
            $ham = preg_split('/[\s,;]+/', trim((string)$d), -1, PREG_SPLIT_NO_EMPTY);
            foreach ($ham as $m) if (!filter_var($m, FILTER_VALIDATE_EMAIL)) gh('Geçersiz e-posta adresi: ' . $m);
            $l = giris_rapor_alicilar($d); if (count($l) > 10) gh('En fazla 10 alıcı girilebilir.'); $d = implode(', ', $l);
        }
        elseif ($a === 'yontem') { $d = (string)$d; if (!in_array($d, GIRIS_YONTEMLER, true)) gh('Geçersiz giriş-çıkış yöntemi.'); }
        else $d = !empty($d) && $d !== '0' ? 1 : 0;
        $st = $db->prepare("INSERT INTO giris_ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)");
        $ds = (string)$d; $st->bind_param('ss', $a, $ds); $st->execute();
    }
    gc(['ok' => true]);
}
if ($islem === 'rapor_mail_test') {      // ayarlardaki alıcılara raporu hemen gönderir (deneme)
    require_once __DIR__ . '/giris_rapor_mail.php';
    $A = giris_ayarlar($db);
    if (isset($_POST['alicilar']) && trim((string)$_POST['alicilar']) !== '') $A['gunluk_rapor_alicilar'] = (string)$_POST['alicilar'];
    if (isset($_POST['gec'])) $A['gunluk_rapor_gec'] = !empty($_POST['gec']) && $_POST['gec'] !== '0' ? 1 : 0;
    $s = giris_rapor_gonder($db, $A);
    if (!$s['ok']) gh($s['mesaj']);
    gc(['ok' => true, 'mesaj' => $s['mesaj']]);
}
gh('Bilinmeyen işlem.', 400);
