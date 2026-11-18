<?php
// ============================================================
// rapor_islem.php — İK raporları · mesai kaydı · resmî tatiller · "bugün kimler" panosu
//   GET  secenek | onizle | pdf | mesailer | tatiller | pano
//   POST mesai_kaydet | mesai_karar | mesai_sil | tatil_kaydet | tatil_sil      (CSRF korumalı)
// Yetkiler: ik_rapor_gor (raporlar) · ik_mesai_yonet (mesai ekleme/onay, tatil günleri) · ik_menu_mesai (mesai sayfası)
// ============================================================
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/rapor_ortak.php';
if (session_status() === PHP_SESSION_NONE) session_start();

function rpc($v, $k = 200) { $o = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR); http_response_code($k); header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); echo $o; exit; }
function rph($m, $k = 400) { rpc(['ok' => false, 'hata' => $m], $k); }

$uid = intval($_SESSION['kullanici_id'] ?? 0);
if ($uid <= 0) rph('Oturum süresi doldu, lütfen yeniden giriş yapın.', 401);
ik_sema($db); rp_sema($db); giris_sema($db);
$Y = vy_coz($db, 'ik', $uid);
if (empty($Y['ik_erisim'])) rph('İnsan Kaynakları sayfasına erişim yetkiniz yok.', 403);
try { require_once __DIR__ . '/platform_paket.php'; pf_paket_sema($db); if (in_array('raporlar', pf_paket_kilitli($db), true)) rph('Bu özellik kurumunuzun paketinde yok veya süresi bitti.', 402); } catch (Throwable $e) {}
$girdi = $_POST + $_GET;
$islem = (string)($_GET['islem'] ?? $girdi['islem'] ?? '');
$RAPOR = !empty($Y['ik_rapor_gor']); $MYON = !empty($Y['ik_mesai_yonet']);
function rp_csrf($g) { $gelen = (string)($_SERVER['HTTP_X_CSRF_TOKEN'] ?? $g['_csrf'] ?? ''); if (empty($_SESSION['tb_csrf']) || !hash_equals((string)$_SESSION['tb_csrf'], $gelen)) rph('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.', 419); }
function rp_e($db, $s) { return $db->real_escape_string((string)$s); }
$benAd = (string)($db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $uid")->fetch_row()[0] ?? '');

// ── Bugün kimler: doğum günü · izinde · yaklaşan izin (tüm İK kullanıcıları görür) ──
if ($islem === 'pano') {
    $bugun = date('Y-m-d'); $gor = !empty($Y['ik_personel_gor']) || !empty($Y['ik_izin_onay']);
    $dogum = []; $yak = [];
    $q = $db->query("SELECT k.id, k.ad_soyad, k.avatar_renk, k.profil_resmi, k.dogum_tarihi, p.departman, k.gorev FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND (p.isten_cikis IS NULL OR p.isten_cikis > CURDATE()) AND k.dogum_tarihi IS NOT NULL AND k.dogum_tarihi <> '0000-00-00'");
    if ($q) while ($r = $q->fetch_assoc()) {
        $ay = date('m-d', strtotime($r['dogum_tarihi']));
        if ($ay === '02-29' && !checkdate(2, 29, (int)date('Y'))) $ay = '02-28';
        $bu = strtotime(date('Y') . '-' . $ay); if ($bu < strtotime('today')) $bu = strtotime((date('Y') + 1) . '-' . $ay);
        $kalan = (int)round(($bu - strtotime('today')) / 86400);
        if ($kalan > 30) continue;
        $dogum[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'birim' => rp_birim_adi($r), 'kalan' => $kalan,
            'tarih' => date('Y-m-d', $bu), 'yas' => (int)date('Y', $bu) - (int)date('Y', strtotime($r['dogum_tarihi']))];
    }
    usort($dogum, function ($a, $b) { return [$a['kalan'], $a['ad']] <=> [$b['kalan'], $b['ad']]; });
    $izinde = [];
    $q = $db->query("SELECT t.kullanici_id, t.tur, t.baslangic, t.bitis, t.yarim_gun, k.ad_soyad, k.avatar_renk, k.profil_resmi, p.departman, k.gorev FROM ik_izin_talepleri t JOIN kullanicilar k ON k.id = t.kullanici_id LEFT JOIN ik_personel p ON p.kullanici_id = k.id
                     WHERE t.durum = 'onaylandi' AND t.baslangic <= '$bugun' AND t.bitis >= '$bugun' AND k.aktif = 1 ORDER BY t.bitis, k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) {
        $izinde[] = ['id' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'birim' => rp_birim_adi($r),
            'tur' => $gor || intval($r['kullanici_id']) === $uid ? (IK_IZIN_TURLERI[$r['tur']] ?? $r['tur']) : 'İzinde', 'baslangic' => $r['baslangic'], 'bitis' => $r['bitis'], 'donus' => ik_ise_donus($r['bitis']),
            'kalan_gun' => max(1, (int)round((strtotime($r['bitis']) - strtotime($bugun)) / 86400) + 1)];
    }
    $q = $db->query("SELECT t.kullanici_id, t.tur, t.baslangic, t.bitis, t.gun, k.ad_soyad, k.avatar_renk, k.profil_resmi, p.departman, k.gorev FROM ik_izin_talepleri t JOIN kullanicilar k ON k.id = t.kullanici_id LEFT JOIN ik_personel p ON p.kullanici_id = k.id
                     WHERE t.durum = 'onaylandi' AND t.baslangic > '$bugun' AND t.baslangic <= DATE_ADD('$bugun', INTERVAL 14 DAY) AND k.aktif = 1 ORDER BY t.baslangic, k.ad_soyad LIMIT 20");
    if ($q) while ($r = $q->fetch_assoc()) $yak[] = ['id' => intval($r['kullanici_id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'birim' => rp_birim_adi($r),
        'tur' => $gor || intval($r['kullanici_id']) === $uid ? (IK_IZIN_TURLERI[$r['tur']] ?? $r['tur']) : 'İzin', 'baslangic' => $r['baslangic'], 'bitis' => $r['bitis'], 'donus' => ik_ise_donus($r['bitis']), 'gun' => (float)$r['gun']];
    $yild = [];
    if (!empty($Y['ik_personel_gor'])) {
        $q = $db->query("SELECT k.id, k.ad_soyad, k.avatar_renk, k.profil_resmi, k.gorev, p.departman, p.ise_giris FROM kullanicilar k JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND p.isten_cikis IS NULL AND p.ise_giris IS NOT NULL AND p.ise_giris <> '0000-00-00'");
        if ($q) while ($r = $q->fetch_assoc()) {
            $ay = date('m-d', strtotime($r['ise_giris'])); if ($ay === '02-29' && !checkdate(2, 29, (int)date('Y'))) $ay = '02-28';
            $bu = strtotime(date('Y') . '-' . $ay); if ($bu < strtotime('today')) $bu = strtotime((date('Y') + 1) . '-' . $ay);
            $kalan = (int)round(($bu - strtotime('today')) / 86400); $yil = (int)date('Y', $bu) - (int)date('Y', strtotime($r['ise_giris']));
            if ($kalan <= 30 && $yil >= 1) $yild[] = ['id' => intval($r['id']), 'ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? ''), 'birim' => rp_birim_adi($r), 'kalan' => $kalan, 'tarih' => date('Y-m-d', $bu), 'yil' => $yil];
        }
        usort($yild, function ($a, $b) { return [$a['kalan'], $a['ad']] <=> [$b['kalan'], $b['ad']]; });
    }
    $tat = rp_tatil_haritasi($db, $bugun, date('Y-m-d', strtotime('+30 day'))); $tatiller = [];
    foreach ($tat as $t => $v) { if (count($tatiller) >= 4) break; $tatiller[] = ['tarih' => $t, 'ad' => $v['ad'], 'yarim' => !empty($v['yarim']), 'kalan' => (int)round((strtotime($t) - strtotime($bugun)) / 86400)]; }
    $dgTan = 0; $qt = $db->query("SELECT COUNT(*) FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND (p.isten_cikis IS NULL OR p.isten_cikis > CURDATE()) AND k.dogum_tarihi IS NOT NULL AND k.dogum_tarihi <> '0000-00-00'"); if ($qt && ($rt = $qt->fetch_row())) $dgTan = intval($rt[0]);
    rpc(['ok' => true, 'dogum_tanimli' => $dgTan, 'dogum' => $dogum, 'izinde' => $izinde, 'yaklasan_izin' => $yak, 'yildonumu' => array_slice($yild, 0, 8), 'tatiller' => $tatiller]);
}

// ── Rapor seçenekleri ──
if ($islem === 'secenek') {
    if (!$RAPOR) rph('Raporlama yetkiniz yok.', 403);
    $kisiler = []; foreach (rp_kisiler($db, [], []) as $k) $kisiler[] = ['id' => $k['id'], 'ad' => $k['ad'], 'birim' => $k['birim']];
    rpc(['ok' => true, 'tipler' => array_diff_key(rp_tanimlar(), empty($Y['ik_maas']) ? ['bordro_ozet' => 1] : []), 'birimler' => rp_birimler($db), 'kisiler' => $kisiler, 'bugun' => date('Y-m-d')]);
}
if ($islem === 'onizle' || $islem === 'pdf') {
    if (!$RAPOR) rph('Raporlama yetkiniz yok.', 403);
    @set_time_limit(60);
    if (($girdi['tip'] ?? '') === 'bordro_ozet' && empty($Y['ik_maas'])) rph('Bordro raporu için maaş görme yetkisi gerekir.', 403);
    $r = rp_uret($db, $girdi);
    if (empty($r['ok'])) rph($r['hata'] ?? 'Rapor oluşturulamadı.');
    if ($islem === 'onizle') rpc($r);
    require_once __DIR__ . '/rapor_pdf.php';
    $logo = trim((string)ik_ayar($db, 'mail_logo', ''));
    $gorunum = in_array($girdi['gorunum'] ?? '', ['tablo', 'grafik', 'ikisi'], true) ? $girdi['gorunum'] : 'ikisi';
    $pdf = rp_pdf($r, $gorunum, ['renk' => ik_ayar($db, 'mail_renk', '#6366f1'), 'renk2' => ik_ayar($db, 'mail_renk2', ''), 'alt' => (trim((string)ik_ayar($db, 'mail_firma_ad', '')) ?: (function_exists('pf_imza_adi') ? pf_imza_adi($db) : 'Turkuaz Bileşim')) . ' · İnsan Kaynakları',
        'logo_dosya' => preg_match('~^uploads/k\d+/ik_logo/[A-Za-z0-9._-]+$~', $logo) ? TB_KOK . '/' . $logo : '']);
    header('Content-Type: application/pdf'); header('Content-Length: ' . strlen($pdf)); header('Cache-Control: private, no-store');
    header('Content-Disposition: attachment; filename="' . $r['dosya'] . '.pdf"');
    echo $pdf; exit;
}

// ── Mesai ──
if ($islem === 'mesailer') {
    if (array_key_exists('ik_menu_mesai', $Y) && empty($Y['ik_menu_mesai'])) rph('Bu bölümü görme yetkiniz yok.', 403);
    $bas = rp_tarih_gecerli($girdi['bas'] ?? '') ? $girdi['bas'] : date('Y-m-01'); $bit = rp_tarih_gecerli($girdi['bit'] ?? '') ? $girdi['bit'] : date('Y-m-t');
    $w = ["m.tarih BETWEEN '" . rp_e($db, $bas) . "' AND '" . rp_e($db, $bit) . "'"];
    $kisi = intval($girdi['kisi'] ?? 0);
    if (!$MYON) $w[] = "m.kullanici_id = $uid"; elseif ($kisi) $w[] = "m.kullanici_id = $kisi";
    $d = (string)($girdi['durum'] ?? ''); if (in_array($d, ['beklemede', 'onaylandi', 'reddedildi'], true)) $w[] = "m.durum = '$d'";
    $q = $db->query("SELECT m.*, k.ad_soyad, k.avatar_renk, k.profil_resmi FROM ik_mesailer m JOIN kullanicilar k ON k.id = m.kullanici_id WHERE " . implode(' AND ', $w) . " ORDER BY m.tarih DESC, m.id DESC LIMIT 1000");
    $liste = []; $top = ['onay' => 0, 'hs' => 0, 'tatil' => 0, 'bekleyen' => 0, 'bek_say' => 0];
    if ($q) while ($m = $q->fetch_assoc()) {
        $dk = intval($m['dakika']);
        if ($m['durum'] === 'onaylandi') { $top['onay'] += $dk; if ($m['tur'] === 'haftasonu') $top['hs'] += $dk; if ($m['tur'] === 'tatil') $top['tatil'] += $dk; }
        if ($m['durum'] === 'beklemede') { $top['bekleyen'] += $dk; $top['bek_say']++; }
        $liste[] = ['id' => intval($m['id']), 'kid' => intval($m['kullanici_id']), 'ad' => $m['ad_soyad'], 'renk' => $m['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($m['profil_resmi'] ?? ''), 'tarih' => $m['tarih'], 'gun' => RP_GUN_AD[(int)date('N', strtotime($m['tarih']))],
            'baslangic' => $m['baslangic'], 'bitis' => $m['bitis'], 'dakika' => $dk, 'sure' => rp_sure($dk), 'tur' => $m['tur'], 'tur_ad' => RP_MESAI_TUR[$m['tur']] ?? $m['tur'], 'tatil_adi' => $m['tatil_adi'], 'aciklama' => $m['aciklama'], 'durum' => $m['durum'],
            'silebilir' => $MYON || (intval($m['kullanici_id']) === $uid && $m['durum'] === 'beklemede' && intval($m['giren_id']) === $uid)];
    }
    $kisiler = [];
    if ($MYON) foreach (rp_kisiler($db, [], []) as $k) $kisiler[] = ['id' => $k['id'], 'ad' => $k['ad'], 'birim' => $k['birim']];
    rpc(['ok' => true, 'liste' => $liste, 'toplam' => ['onayli' => $top['onay'], 'onayli_yaz' => rp_sure($top['onay']), 'hs' => rp_sure($top['hs']), 'tatil' => rp_sure($top['tatil']), 'bekleyen' => rp_sure($top['bekleyen']), 'bek_say' => $top['bek_say']],
        'yonet' => $MYON, 'kisiler' => $kisiler, 'bas' => $bas, 'bit' => $bit, 'calisma_gunleri' => rp_calisma_gunleri($db)]);
}
if ($islem === 'tatiller') {
    $yil = max(2020, min(2040, intval($girdi['yil'] ?? date('Y'))));
    $tat = rp_tatil_haritasi($db, "$yil-01-01", "$yil-12-31"); $ozelId = [];
    $q = $db->query("SELECT id, tarih FROM ik_tatiller WHERE YEAR(tarih) = $yil"); if ($q) while ($r = $q->fetch_assoc()) $ozelId[$r['tarih']] = intval($r['id']);
    $liste = [];
    foreach ($tat as $t => $v) $liste[] = ['tarih' => $t, 'gun' => RP_GUN_AD[(int)date('N', strtotime($t))], 'ad' => $v['ad'], 'yarim' => !empty($v['yarim']), 'ozel' => !empty($v['ozel']), 'id' => $ozelId[$t] ?? 0];
    $eksik = !isset(RP_RAMAZAN[$yil]) || !isset(RP_KURBAN[$yil]);
    rpc(['ok' => true, 'yil' => $yil, 'liste' => $liste, 'bayram_eksik' => $eksik, 'yonet' => $MYON]);
}

// ── Yazma işlemleri ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') rph('Geçersiz istek.', 405);
rp_csrf($girdi);
if ($islem === 'mesai_kaydet') {
    if (array_key_exists('ik_menu_mesai', $Y) && empty($Y['ik_menu_mesai'])) rph('Bu bölümü görme yetkiniz yok.', 403);
    $kid = $MYON ? intval($girdi['kullanici_id'] ?? $uid) : $uid; if ($kid <= 0) $kid = $uid;
    if (!$db->query("SELECT 1 FROM kullanicilar WHERE id = $kid AND aktif = 1")->fetch_row()) rph('Personel bulunamadı.');
    $tarih = (string)($girdi['tarih'] ?? ''); if (!rp_tarih_gecerli($tarih)) rph('Geçerli bir tarih seçin.');
    if ($tarih > date('Y-m-d', strtotime('+1 day'))) rph('Gelecek tarihli mesai girilemez.');
    if ($tarih < date('Y-m-d', strtotime('-400 day'))) rph('Bu kadar eski bir tarih girilemez.');
    $bas = (string)($girdi['baslangic'] ?? ''); $bit = (string)($girdi['bitis'] ?? '');
    $h = rp_mesai_hesapla($db, $tarih, $bas, $bit); if (!$h) rph('Başlangıç ve bitiş saatini girin.');
    list($dk, $tur, $tad) = $h;
    if ($dk < 1 || $dk > 960) rph('Mesai süresi en fazla 16 saat olabilir.');
    $ac = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 300, 'UTF-8');
    $dur = $MYON ? 'onaylandi' : 'beklemede'; $id = intval($girdi['id'] ?? 0);
    if ($id) {
        $m = $db->query("SELECT * FROM ik_mesailer WHERE id = $id")->fetch_assoc(); if (!$m) rph('Kayıt bulunamadı.', 404);
        if (!$MYON && !(intval($m['kullanici_id']) === $uid && $m['durum'] === 'beklemede')) rph('Bu kaydı düzenleyemezsiniz.', 403);
        $st = $db->prepare("UPDATE ik_mesailer SET kullanici_id = ?, tarih = ?, baslangic = ?, bitis = ?, dakika = ?, tur = ?, tatil_adi = ?, aciklama = ? WHERE id = ?");
        $st->bind_param('isssisssi', $kid, $tarih, $bas, $bit, $dk, $tur, $tad, $ac, $id); $st->execute();
    } else {
        $st = $db->prepare("INSERT INTO ik_mesailer (kullanici_id, tarih, baslangic, bitis, dakika, tur, tatil_adi, aciklama, durum, giren_id, karar_veren_id, karar_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $kv = $MYON ? $uid : null; $kt = $MYON ? date('Y-m-d H:i:s') : null;
        $st->bind_param('isssissssiis', $kid, $tarih, $bas, $bit, $dk, $tur, $tad, $ac, $dur, $uid, $kv, $kt); if (!$st->execute()) rph('Kaydedilemedi (veritabanı hatası; ayrıntı sunucu günlüğüne yazıldı).' . (@error_log('[TB] SQL: ' . $db->hata()) ? '' : ''), 500);
        $id = intval($db->son_id());
        try {
            $ozet = date('d.m.Y', strtotime($tarih)) . ' · ' . $bas . '–' . $bit . ' (' . rp_sure($dk) . ')' . ($tur !== 'normal' ? ' · ' . RP_MESAI_TUR[$tur] . ($tad && $tur === 'tatil' ? ': ' . $tad : '') : '');
            if ($MYON && $kid !== $uid) ik_bildir($db, $kid, $uid, '🕒 Mesainiz kaydedildi', 'Adınıza mesai kaydı girildi: <b>' . ik_h($ozet) . '</b>', 'ik_mesai', $id);
            if (!$MYON) foreach (ik_onay_alicilari($db, 'mesai', 'ik_mesai_yonet', $uid) as $a) if ($a !== $uid) ik_bildir($db, $a, $uid, '🕒 Mesai onayı bekliyor', '<b>' . ik_h($benAd) . '</b> mesai bildirdi: ' . ik_h($ozet), 'ik_mesai', $id);
        } catch (Throwable $e) {}
    }
    rpc(['ok' => true, 'id' => $id, 'dakika' => $dk, 'tur' => $tur, 'tatil_adi' => $tad, 'durum' => $dur]);
}
if ($islem === 'mesai_toplu') {
    if (!$MYON) rph('Toplu mesai ekleme yetkiniz yok.', 403);
    $ids = [];
    if (!empty($girdi['tum'])) { foreach (rp_kisiler($db, [], []) as $k) $ids[] = intval($k['id']); }
    else { $kl = $girdi['kisiler'] ?? []; if (!is_array($kl)) $kl = preg_split('/[,\s]+/', (string)$kl); foreach ($kl as $x) $ids[] = intval($x); }
    $ids = array_values(array_unique(array_filter($ids))); if (!$ids) rph('En az bir personel seçin.');
    if (count($ids) > 500) rph('En fazla 500 personel seçilebilir.');
    $t1 = (string)($girdi['tarih'] ?? ''); $t2 = (string)($girdi['tarih_bit'] ?? '') ?: $t1;
    if (!rp_tarih_gecerli($t1) || !rp_tarih_gecerli($t2)) rph('Geçerli tarih seçin.');
    if ($t2 < $t1) rph('Bitiş tarihi başlangıçtan önce olamaz.');
    if ($t2 > date('Y-m-d', strtotime('+1 day'))) rph('Gelecek tarihli mesai girilemez.');
    if ($t1 < date('Y-m-d', strtotime('-400 day'))) rph('Bu kadar eski bir tarih girilemez.');
    if ((strtotime($t2) - strtotime($t1)) / 86400 > 31) rph('Tarih aralığı en fazla 32 gün olabilir.');
    $bas = (string)($girdi['baslangic'] ?? '');
    if (($girdi['mod'] ?? '') === 'saat') {
        $sa = floatval(str_replace(',', '.', (string)($girdi['saat'] ?? 0))); $dkm = intval(round($sa * 60));
        if ($dkm < 1 || $dkm > 960) rph('Süre 0 ile 16 saat arasında olmalı.');
        if (rp_dk($bas) === null) $bas = '18:00';
        $bit = rp_saat_yaz(rp_dk($bas) + $dkm);
    } else { $bit = (string)($girdi['bitis'] ?? ''); if (rp_dk($bas) === null || rp_dk($bit) === null) rph('Başlangıç ve bitiş saatini girin.'); }
    $hs = !empty($girdi['hafta_sonu']); $cg = rp_calisma_gunleri($db);
    $ac = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 300, 'UTF-8');
    $ekle = 0; $atla = 0; $toplamDk = 0; $kisiSay = [];
    $st = $db->prepare("INSERT INTO ik_mesailer (kullanici_id, tarih, baslangic, bitis, dakika, tur, tatil_adi, aciklama, durum, giren_id, karar_veren_id, karar_tarihi) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'onaylandi', ?, ?, NOW())");
    $kv = $db->prepare("SELECT 1 FROM ik_mesailer WHERE kullanici_id = ? AND tarih = ? AND baslangic = ? AND bitis = ? AND durum <> 'reddedildi' LIMIT 1");
    $aktif = []; $rs = $db->query("SELECT id FROM kullanicilar WHERE aktif = 1"); while ($r = $rs->fetch_row()) $aktif[intval($r[0])] = 1;
    for ($ts = strtotime($t1); $ts <= strtotime($t2); $ts += 86400) {
        $tarih = date('Y-m-d', $ts);
        $h = rp_mesai_hesapla($db, $tarih, $bas, $bit); if (!$h) rph('Saatler geçersiz.');
        list($dk, $tur, $tad) = $h; if ($dk < 1 || $dk > 960) rph('Mesai süresi en fazla 16 saat olabilir.');
        if (!$hs && $tur !== 'normal') { $atla += count($ids); continue; }
        foreach ($ids as $kid) {
            if (empty($aktif[$kid])) { $atla++; continue; }
            $kv->bind_param('isss', $kid, $tarih, $bas, $bit); $kv->execute(); $kv->store_result();
            if ($kv->num_rows > 0) { $atla++; continue; }
            $st->bind_param('isssisssii', $kid, $tarih, $bas, $bit, $dk, $tur, $tad, $ac, $uid, $uid);
            if ($st->execute()) { $ekle++; $toplamDk += $dk; $kisiSay[$kid][] = [$tarih, $dk]; } else $atla++;
        }
    }
    foreach ($kisiSay as $kid => $l) {
        if ($kid === $uid) continue;
        try {
            $top = 0; foreach ($l as $x) $top += $x[1];
            $gun = count($l) === 1 ? date('d.m.Y', strtotime($l[0][0])) : count($l) . ' gün';
            ik_bildir($db, $kid, $uid, '🕒 Mesainiz kaydedildi', 'Adınıza mesai kaydı girildi: <b>' . ik_h($gun . ' · ' . $bas . '–' . $bit . ' · toplam ' . rp_sure($top)) . '</b>', 'ik_mesai', 0);
        } catch (Throwable $e) {}
    }
    rpc(['ok' => true, 'eklenen' => $ekle, 'atlanan' => $atla, 'kisi' => count($kisiSay), 'toplam_dakika' => $toplamDk, 'toplam' => rp_sure($toplamDk)]);
}
if ($islem === 'mesai_karar') {
    if (!$MYON) rph('Mesai onaylama yetkiniz yok.', 403);
    $id = intval($girdi['id'] ?? 0); $m = $db->query("SELECT * FROM ik_mesailer WHERE id = $id")->fetch_assoc(); if (!$m) rph('Kayıt bulunamadı.', 404);
    $k = ($girdi['karar'] ?? '') === 'onay' ? 'onaylandi' : 'reddedildi';
    $st = $db->prepare("UPDATE ik_mesailer SET durum = ?, karar_veren_id = ?, karar_tarihi = NOW() WHERE id = ?"); $st->bind_param('sii', $k, $uid, $id); $st->execute();
    try { ik_bildir($db, intval($m['kullanici_id']), $uid, $k === 'onaylandi' ? '✅ Mesainiz onaylandı' : '❌ Mesainiz reddedildi', date('d.m.Y', strtotime($m['tarih'])) . ' tarihli ' . rp_sure($m['dakika']) . ' mesai kaydınız <b>' . ik_h($benAd) . '</b> tarafından ' . ($k === 'onaylandi' ? 'onaylandı' : 'reddedildi') . '.', 'ik_mesai', $id); } catch (Throwable $e) {}
    rpc(['ok' => true]);
}
if ($islem === 'mesai_sil') {
    $id = intval($girdi['id'] ?? 0); $m = $db->query("SELECT * FROM ik_mesailer WHERE id = $id")->fetch_assoc(); if (!$m) rph('Kayıt bulunamadı.', 404);
    if (!$MYON && !(intval($m['kullanici_id']) === $uid && $m['durum'] === 'beklemede' && intval($m['giren_id']) === $uid)) rph('Bu kaydı silemezsiniz.', 403);
    $db->query("DELETE FROM ik_mesailer WHERE id = $id"); rpc(['ok' => true]);
}
if ($islem === 'tatil_kaydet') {
    if (!$MYON) rph('Tatil günlerini düzenleme yetkiniz yok.', 403);
    $t = (string)($girdi['tarih'] ?? ''); if (!rp_tarih_gecerli($t)) rph('Geçerli bir tarih seçin.');
    $ad = mb_substr(trim((string)($girdi['ad'] ?? '')), 0, 100, 'UTF-8'); if ($ad === '') rph('Tatil adını yazın.');
    $y = !empty($girdi['yarim']) ? 1 : 0;
    $st = $db->prepare("INSERT INTO ik_tatiller (tarih, ad, yarim) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE ad = VALUES(ad), yarim = VALUES(yarim)"); $st->bind_param('ssi', $t, $ad, $y); $st->execute();
    rpc(['ok' => true]);
}
if ($islem === 'tatil_sil') {
    if (!$MYON) rph('Tatil günlerini düzenleme yetkiniz yok.', 403);
    $db->query("DELETE FROM ik_tatiller WHERE id = " . intval($girdi['id'] ?? 0)); rpc(['ok' => true]);
}
rph('Bilinmeyen işlem.', 404);
