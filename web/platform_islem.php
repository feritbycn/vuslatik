<?php
// ============================================================
// platform_islem.php — Platform paneli JSON uç noktası (yalnızca platform yöneticileri)
//   Herkese açık: POST giris
//   GET  ozet | kurumlar | kurum | ayarlar | yoneticiler | kayitlar
//   POST cikis | sifre_degistir | kurum_kaydet | kurum_durum | kurum_sil | kurum_logo | yetkili_ata | sifre_sifirla | kurum_gir | sema_onar
//        | ayar_kaydet | dosya_yukle | dosya_sil | yonetici_kaydet | yonetici_sil          (hepsi CSRF korumalı)
// ============================================================
require_once __DIR__ . '/config/baglan.php';
if (TB_TEK_KURUM > 0) tb_json(['ok' => false, 'hata' => 'Bu kurulum tek kurum paketidir; platform paneli kapalıdır.'], 403);
$db->kurum = 0;                                   // platform işlemleri kurumsuz çalışır; kurum verisi gerektiğinde açıkça seçilir
$islem = (string)($_GET['islem'] ?? $_POST['islem'] ?? '');
function ph($m, $k = 400) { tb_json(['ok' => false, 'hata' => $m], $k); }
function pf_sifre_ok($s) { return strlen($s) >= 10 && preg_match('/[A-Za-z]/', $s) && preg_match('/\d/', $s); }

if ($islem === 'giris') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ph('Geçersiz istek.', 405);
    if (!csrf_dogru()) ph('Oturum doğrulaması başarısız. Sayfayı yenileyin.', 419);
    $u = strtolower(trim((string)($_POST['kullanici'] ?? ''))); $s = (string)($_POST['sifre'] ?? '');
    $anahtar = 'pf|' . $u;
    if (pf_deneme_say($db, $anahtar) >= 6) ph('Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.', 429);
    $st = $db->prepare("SELECT id, sifre, ad_soyad, aktif FROM pf_yoneticiler WHERE kullanici_adi = ?");
    $st->bind_param('s', $u); $st->execute();
    $r = $st->get_result()->fetch_assoc();
    $ok = password_verify($s, $r ? $r['sifre'] : '$2y$10$6yGgQl2dL8e08n1MivSXje1JLngchwoJfO2iyw8d2gvtJDvqT/G8y');
    if (!$r || !$ok || intval($r['aktif']) !== 1) { pf_deneme_ekle($db, $anahtar); ph('Kullanıcı adı veya şifre hatalı.', 401); }
    pf_deneme_temizle($db, $anahtar);
    session_regenerate_id(true);
    $_SESSION['pf_yonetici_id'] = intval($r['id']); $_SESSION['pf_yonetici_ad'] = (string)$r['ad_soyad'];
    $_SESSION['tb_csrf'] = bin2hex(random_bytes(24));
    $db->query("UPDATE pf_yoneticiler SET son_giris = NOW() WHERE id = " . intval($r['id']));
    pf_log($db, 'platform_giris', $u);
    tb_json(['ok' => true, 'csrf' => $_SESSION['tb_csrf']]);
}

// ── Herkese açık, anahtar korumalı: başka kurulumlar buradan güncelleme paketi çeker ──
if ($islem === 'paket_kaynak') {
    require_once __DIR__ . '/bakim_ortak.php';
    $an = pf_ayar($db, 'guncelleme_anahtari');
    $gel = (string)($_GET['anahtar'] ?? '');
    if (strlen($an) < 20 || !hash_equals($an, $gel)) { usleep(500000); http_response_code(403); header('Content-Type: application/json'); echo '{"ok":false}'; exit; }
    if (!empty($_GET['kurumlar'])) {                               // kurum verisi çekmek isteyen kurulum için liste
        $db->kurum = 0; $l = []; $q = $db->query("SELECT id, ad, kod, durum FROM pf_kurumlar ORDER BY ad"); while ($q && ($r = $q->fetch_assoc())) $l[] = ['id' => intval($r['id']), 'ad' => $r['ad'], 'kod' => $r['kod'], 'durum' => $r['durum']];
        tb_json(['ok' => true, 'kurumlar' => $l]);
    }
    if (!empty($_GET['indir']) && !empty($_GET['kurum'])) {        // tek kurumun verisi (kod yok)
        try { $zp = tb_kurum_veri_paketi($db, intval($_GET['kurum'])); } catch (Throwable $e) { http_response_code(500); echo 'hata'; exit; }
        header('Content-Type: application/zip'); header('Content-Length: ' . filesize($zp)); header('Content-Disposition: attachment; filename="kurum_verisi.zip"'); header('Cache-Control: no-store');
        readfile($zp); @unlink($zp); exit;
    }
    if (!empty($_GET['indir'])) {
        try { $zp = tb_paket_olustur(); } catch (Throwable $e) { http_response_code(500); echo 'hata'; exit; }
        header('Content-Type: application/zip'); header('Content-Length: ' . filesize($zp)); header('Content-Disposition: attachment; filename="guncelleme.zip"'); header('Cache-Control: no-store');
        readfile($zp); @unlink($zp); exit;
    }
    tb_json(['ok' => true, 'bilgi' => tb_surum_bilgi()]);
}

$pid = intval($_SESSION['pf_yonetici_id'] ?? 0);
if ($pid <= 0) ph('Oturum süresi doldu, lütfen yeniden giriş yapın.', 401);
$q = $db->query("SELECT aktif FROM pf_yoneticiler WHERE id = $pid"); $rr = $q ? $q->fetch_row() : null;
if (!$rr || intval($rr[0]) !== 1) { unset($_SESSION['pf_yonetici_id']); ph('Yönetici hesabı pasif.', 403); }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_dogru()) ph('Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.', 419);

/** Kurum çalışma alanı: $db'yi o kuruma bağlar */
function pf_kurum_ac($db, $id)
{
    $k = pf_kurum($db, $id);
    if (!$k) ph('Kurum bulunamadı.', 404);
    $db->kurum = intval($k['id']);
    return $k;
}
function pf_sayilar($db, $id)
{
    $db->kurum = intval($id); $n = [0, 0];
    try { $q = $db->query("SELECT COUNT(*), COALESCE(SUM(aktif = 1), 0) FROM kullanicilar"); if ($q && ($r = $q->fetch_row())) $n = [intval($r[0]), intval($r[1])]; } catch (Throwable $e) {}
    $db->kurum = 0;
    return $n;
}
function pf_yukle_dosya($f, $izinli, $azami)
{
    if (!$f || !isset($f['error']) || $f['error'] !== UPLOAD_ERR_OK) ph('Dosya alınamadı.');
    if ($f['size'] > $azami) ph('Dosya en fazla ' . round($azami / 1048576, 1) . ' MB olabilir.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $uz = $izinli[$mime] ?? '';
    if ($uz === '') ph('Bu dosya türü kabul edilmiyor (' . implode(', ', array_unique($izinli)) . ').');
    if ($uz === 'svg') { $ic = (string)file_get_contents($f['tmp_name']); if (preg_match('/<script|<foreignObject|<iframe|<embed|<object|\bon[a-z]+\s*=|javascript:|data:text\/html|<!ENTITY/i', $ic)) ph('SVG dosyası güvenli değil (betik içeriyor).'); }
    $d = TB_KOK . '/uploads/pf';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    if (!is_file($d . '/.htaccess')) @file_put_contents($d . '/.htaccess', "<FilesMatch \"\\.(php|phtml|phar)$\">\n  Require all denied\n</FilesMatch>\n<IfModule mod_headers.c>\n  <FilesMatch \"\\.svg$\">\n    Header set Content-Security-Policy \"default-src 'none'; style-src 'unsafe-inline'; sandbox\"\n  </FilesMatch>\n</IfModule>\n");
    $ad = bin2hex(random_bytes(6)) . '.' . $uz;
    if (!move_uploaded_file($f['tmp_name'], $d . '/' . $ad)) ph('Dosya kaydedilemedi (uploads klasörü yazılabilir mi?).', 500);
    return 'uploads/pf/' . $ad;
}
function pf_eski_sil($yol) { if (preg_match('~^uploads/pf/[a-f0-9]{12}\.[a-z]+$~', (string)$yol) && is_file(TB_KOK . '/' . $yol)) @unlink(TB_KOK . '/' . $yol); }
const PF_RESIM = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/svg+xml' => 'svg'];
const PF_FAVICON = ['image/png' => 'png', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico', 'image/svg+xml' => 'svg', 'image/webp' => 'webp'];

// ───────────────────────── OKUMA ─────────────────────────
if ($islem === 'ozet') {
    require_once __DIR__ . '/kurum_yedek.php'; yd_zamanli_arkada($db);
    require_once __DIR__ . '/platform_mesaj.php'; pf_lm_tara_arkada($db); pf_vdb_oto_arkada($db);
    $toplam = 0; $aktif = 0; $askida = 0; $kul = 0; $kulAktif = 0;
    $q = $db->query("SELECT id, durum FROM pf_kurumlar"); $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = $r;
    foreach ($liste as $r) { $toplam++; if ($r['durum'] === 'aktif') $aktif++; else $askida++; $n = pf_sayilar($db, $r['id']); $kul += $n[0]; $kulAktif += $n[1]; }
    $son = [];
    $q = $db->query("SELECT g.zaman, g.kim, g.olay, g.ayrinti, k.ad AS kurum FROM pf_kayit g LEFT JOIN pf_kurumlar k ON k.id = g.kurum_id ORDER BY g.id DESC LIMIT 8");
    if ($q) while ($r = $q->fetch_assoc()) $son[] = $r;
    $yeni = []; $q = $db->query("SELECT id, ad, kod, renk, logo, olusturma FROM pf_kurumlar ORDER BY id DESC LIMIT 5");
    if ($q) while ($r = $q->fetch_assoc()) $yeni[] = $r;
    tb_json(['ok' => true, 'kurum' => $toplam, 'aktif' => $aktif, 'askida' => $askida, 'kullanici' => $kul, 'kullanici_aktif' => $kulAktif, 'son' => $son, 'yeni' => $yeni,
        'ben' => ['ad' => $_SESSION['pf_yonetici_ad'] ?? ''], 'site' => pf_ayar($db, 'site_adi')]);
}
if ($islem === 'lm_ayar') {
    require_once __DIR__ . '/platform_mesaj.php'; pf_lm_sema($db);
    $kl = []; $q = $db->query("SELECT id, ad FROM pf_kurumlar ORDER BY ad"); if ($q) while ($r = $q->fetch_assoc()) $kl[] = ['id' => intval($r['id']), 'ad' => $r['ad']];
    $log = []; $q = $db->query("SELECT m.*, k.ad AS kurum FROM pf_lisans_mesaj m LEFT JOIN pf_kurumlar k ON k.id = m.kurum_id ORDER BY m.id DESC LIMIT 60"); if ($q) while ($r = $q->fetch_assoc()) $log[] = $r;
    $yol = rtrim(dirname(__FILE__), '/\\') . '/platform_mesaj.php';
    tb_json(['ok' => true, 'ayar' => pf_lm_ayar($db), 'hazir' => pf_lm_hazir($db), 'kurumlar' => $kl, 'log' => $log, 'cron' => 'php ' . $yol, 'degiskenler' => ['{kurum}', '{kalan}', '{tarih}', '{ek_gun}', '{son_durum}', '{site}', '{destek}']]);
}
if ($islem === 'sunucu_liste') {
    pf_sunucu_sema($db); $ben = pf_sunucu_kimlik(); $db->kurum = 0; $l = [];
    $q = $db->query("SELECT * FROM pf_sunucu ORDER BY (durum = 'bekliyor') DESC, (durum = 'aktif') DESC, id"); if ($q) while ($r = $q->fetch_assoc()) { $r['ben'] = $r['sid'] === $ben['sid']; unset($r['sid']); $r['id'] = intval($r['id']); $l[] = $r; }
    tb_json(['ok' => true, 'liste' => $l, 'kilit' => pf_sunucu_ayar($db, 'sunucu_kilidi') !== '0', 'uyari_mail' => pf_sunucu_ayar($db, 'uyari_mail'), 'uyari_tel' => pf_sunucu_ayar($db, 'uyari_tel'), 'kanal_hazir' => intval(pf_sunucu_ayar($db, 'lm_kurum')) > 0, 'php' => ['surum' => PHP_VERSION, 'risk' => (function () { $d = ['8.5' => '8.5.12', '8.4' => '8.4.27', '8.3' => '8.3.36', '8.2' => '8.2.35']; $d0 = PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION; return isset($d[$d0]) ? version_compare(PHP_VERSION, $d[$d0], '<') : true; })(), 'duzeltme' => '8.5.12 / 8.4.27 / 8.3.36 / 8.2.35']]);
}
if ($islem === 'uyari_bildirim_kaydet') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ph('Geçersiz istek.', 405);
    $m = trim((string)($_POST['mail'] ?? '')); $t = trim((string)($_POST['tel'] ?? ''));
    if ($m !== '' && !filter_var($m, FILTER_VALIDATE_EMAIL)) ph('E-posta geçersiz.');
    if ($t !== '' && !preg_match('/^[0-9+() \-]{7,20}$/', $t)) ph('Telefon geçersiz.');
    pf_sunucu_sema($db); pf_sunucu_ayar($db, 'uyari_mail', $m); pf_sunucu_ayar($db, 'uyari_tel', $t); tb_json(['ok' => true]);
}
if ($islem === 'uyari_bildirim_dene') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ph('Geçersiz istek.', 405);
    pf_sunucu_sema($db); $db->kurum = 0; $db->query("DELETE FROM pf_ayarlar WHERE anahtar LIKE 'uyari_son_%'");
    pf_uyari_bildir($db, 'Deneme bildirimi', 'Bu bir deneme bildirimidir. Sunucu taşıma uyarıları bu adrese gelecektir.'); tb_json(['ok' => true]);
}
if ($islem === 'sunucu_kilit') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ph('Geçersiz istek.', 405);
    pf_sunucu_sema($db); $ac = !empty($_POST['ac']); pf_sunucu_ayar($db, 'sunucu_kilidi', $ac ? '1' : '0');
    pf_log($db, 'sunucu_kilit', $ac ? 'Sunucu kopyası algılama açıldı' : 'Sunucu kopyası algılama kapatıldı', null); tb_json(['ok' => true]);
}
if ($islem === 'sunucu_karar') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ph('Geçersiz istek.', 405);
    pf_sunucu_sema($db); $id = intval($_POST['id'] ?? 0); $db->kurum = 0;
    $q = $db->query("SELECT sid, ad, yol FROM pf_sunucu WHERE id = $id"); $r = $q ? $q->fetch_assoc() : null; if (!$r) ph('Sunucu bulunamadı.', 404);
    $db->query("UPDATE pf_sunucu SET durum = IF(id = $id, 'aktif', 'engelli')");
    $ben = pf_sunucu_kimlik(); pf_sunucu_bildir($db, 'karar', ['aktif' => $r['sid']], $ben['sid']);
    pf_log($db, 'sunucu_karar', 'Devam eden sunucu: ' . $r['ad'] . ' ' . $r['yol'], null); tb_json(['ok' => true]);
}
if ($islem === 'kurumlar') {
    require_once __DIR__ . '/platform_vekil.php'; pf_vekil_sema($db);
    require_once __DIR__ . '/platform_paket.php'; pf_paket_sema($db);
    require_once __DIR__ . '/aktivasyon_ortak.php'; akt_sema($db);
    $q = $db->query("SELECT * FROM pf_kurumlar ORDER BY ad"); $liste = [];
    if ($q) while ($r = $q->fetch_assoc()) $liste[] = $r;
    foreach ($liste as &$r) {
        $n = pf_sayilar($db, $r['id']);
        $r['kullanici'] = $n[0]; $r['kullanici_aktif'] = $n[1]; $r['lisans'] = pf_lisans($r); $r['sunucu'] = pf_vekil_ozet($db, $r['id']); $r['akt'] = akt_ozet($db, $r['id']);
        $db->kurum = intval($r['id']); $r['yetkili_kullanici'] = ''; $r['yetkili_son'] = null;
        if (intval($r['yetkili_id']) > 0) { $y = $db->query("SELECT kullanici_adi, son_giris FROM kullanicilar WHERE id = " . intval($r['yetkili_id'])); if ($y && ($yr = $y->fetch_row())) { $r['yetkili_kullanici'] = $yr[0]; $r['yetkili_son'] = $yr[1]; } }
        $db->kurum = 0;
    }
    unset($r);
    tb_json(['ok' => true, 'kurumlar' => $liste]);
}
if ($islem === 'kurum') {
    $k = pf_kurum_ac($db, $_GET['id'] ?? 0);
    $kul = []; $q = $db->query("SELECT id, ad_soyad, kullanici_adi, rol, gorev, aktif, son_giris FROM kullanicilar ORDER BY ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) $kul[] = $r;
    $db->kurum = 0;
    tb_json(['ok' => true, 'kurum' => $k, 'kullanicilar' => $kul]);
}
if ($islem === 'ayarlar') {
    $a = pf_ayarlar($db); $t = [];
    foreach (pf_ayar_tanim() as $k => $d) $t[] = ['anahtar' => $k, 'grup' => $d[1], 'etiket' => $d[2], 'tur' => $d[3], 'deger' => $a[$k]];
    tb_json(['ok' => true, 'ayarlar' => $t]);
}
if ($islem === 'yoneticiler') {
    $q = $db->query("SELECT id, kullanici_adi, ad_soyad, email, aktif, son_giris FROM pf_yoneticiler ORDER BY id"); $l = [];
    if ($q) while ($r = $q->fetch_assoc()) $l[] = $r;
    tb_json(['ok' => true, 'yoneticiler' => $l, 'ben' => $pid]);
}
if ($islem === 'engel_liste') {                          // giriş denemesi nedeniyle geçici engellenenler (son 15 dk, 6+ hatalı deneme)
    $q = $db->query("SELECT ip, anahtar, COUNT(*) AS sayi, MAX(zaman) AS son FROM pf_giris_deneme WHERE zaman > (NOW() - INTERVAL 15 MINUTE) GROUP BY ip, anahtar ORDER BY son DESC LIMIT 100"); $l = [];
    if ($q) while ($r = $q->fetch_assoc()) { $r['sayi'] = intval($r['sayi']); $r['engelli'] = $r['sayi'] >= 6; $l[] = $r; }
    tb_json(['ok' => true, 'liste' => $l]);
}
if ($islem === 'kayitlar') {
    $q = $db->query("SELECT g.id, g.zaman, g.kim, g.olay, g.ayrinti, g.ip, k.ad AS kurum FROM pf_kayit g LEFT JOIN pf_kurumlar k ON k.id = g.kurum_id ORDER BY g.id DESC LIMIT 300"); $l = [];
    if ($q) while ($r = $q->fetch_assoc()) $l[] = $r;
    tb_json(['ok' => true, 'kayitlar' => $l]);
}

if ($islem === 'bakim_durum') {
    require_once __DIR__ . '/bakim_ortak.php';
    $an = pf_ayar($db, 'guncelleme_anahtari');
    if (strlen($an) < 20) { $an = bin2hex(random_bytes(20)); pf_ayar_yaz($db, 'guncelleme_anahtari', $an); }
    $yed = []; foreach (glob(TB_KOK . '/storage/yedek_*.zip') ?: [] as $f) $yed[] = ['ad' => basename($f), 'boyut' => filesize($f), 'zaman' => date('Y-m-d H:i', filemtime($f))];
    $yol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/platform_islem.php?islem=paket_kaynak';
    $mig = []; $q = $db->query("SELECT ad, zaman FROM pf_migrasyon ORDER BY ad DESC LIMIT 10"); if ($q) while ($r = $q->fetch_assoc()) $mig[] = $r;
    tb_json(['ok' => true, 'surum' => tb_surum_bilgi(), 'zip' => class_exists('ZipArchive'), 'curl' => function_exists('curl_init'), 'anahtar' => $an, 'kaynak_adresi' => $yol,
        'kaynak' => pf_ayar($db, 'guncelleme_kaynak'), 'kaynak_anahtar' => pf_ayar($db, 'guncelleme_kaynak_anahtar'), 'yedekler' => $yed, 'migrasyonlar' => $mig,
        'yazilabilir' => is_writable(TB_KOK) && is_writable(TB_KOK . '/storage')]);
}
if ($islem === 'yedek_indir' || $islem === 'paket_indir') {
    if (!csrf_dogru()) ph('Oturum doğrulaması başarısız.', 419);
    require_once __DIR__ . '/bakim_ortak.php';
    try { $zp = $islem === 'yedek_indir' ? tb_yedek_olustur($db, empty($_GET['dosyasiz'])) : tb_paket_olustur(); }
    catch (Throwable $e) { ph($e->getMessage(), 500); }
    pf_log($db, $islem, '');
    $ad = ($islem === 'yedek_indir' ? 'yedek_' : 'guncelleme_') . date('Ymd_His') . '.zip';
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($zp)); header('Content-Disposition: attachment; filename="' . $ad . '"'); header('Cache-Control: no-store');
    readfile($zp); @unlink($zp); exit;
}

if (strncmp($islem, 'vekil_', 6) === 0) {
    require_once __DIR__ . '/platform_vekil.php'; pf_vekil_sema($db);
    $varsayAna = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    $kid = intval($_GET['kurum_id'] ?? $_POST['kurum_id'] ?? 0); $k = pf_kurum($db, $kid); if (!$k) ph('Kurum bulunamadı.', 404);
    if ($islem === 'vekil_durum') {
        $o = pf_vekil_ozet($db, $kid); $v = pf_vekil_al($db, $kid); $ol = [];
        $q = $db->query("SELECT olay, ayrinti, ip, zaman FROM pf_vekil_olay WHERE kurum_id = $kid ORDER BY id DESC LIMIT 25"); if ($q) while ($r = $q->fetch_assoc()) { $r['ad'] = PF_VEKIL_OLAY_AD[$r['olay']] ?? $r['olay']; $ol[] = $r; }
        tb_json(['ok' => true, 'kurum' => ['id' => $kid, 'ad' => $k['ad'], 'kod' => $k['kod']], 'vekil' => $o, 'vdb' => pf_vdb_ozet($db, $kid), 'var' => (bool)$v, 'olaylar' => $ol, 'ana' => $varsayAna, 'zip' => class_exists('ZipArchive')]);
    }
    if ($islem === 'vekil_indir') {
        if (!csrf_dogru()) ph('Oturum doğrulaması başarısız.', 419);
        if (!class_exists('ZipArchive')) ph('PHP zip eklentisi kurulu değil.', 500);
        if (!pf_vekil_al($db, $kid)) ph('Önce uzak sunucu bağlantısını oluşturun.');
        $ana = trim((string)($_GET['ana'] ?? '')); if (!preg_match('~^https?://[^\s/]+(/[^\s?#]*)?$~i', $ana)) $ana = $varsayAna;
        $f = pf_vekil_dosyalar($db, $k, $ana, false);
        $zp = tempnam(sys_get_temp_dir(), 'vk'); $z = new ZipArchive(); $z->open($zp, ZipArchive::OVERWRITE);
        foreach ($f as $ad => $ic) $z->addFromString($ad, $ic); $z->close();
        pf_log($db, 'vekil_indir', $k['kod'], $kid);
        header('Content-Type: application/zip'); header('Content-Length: ' . filesize($zp)); header('Content-Disposition: attachment; filename="vekil_' . preg_replace('/[^a-z0-9_-]/i', '', $k['kod']) . '.zip"'); header('Cache-Control: no-store');
        readfile($zp); @unlink($zp); exit;
    }
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') ph('Geçersiz istek.', 405);
    if ($islem === 'vekil_olustur' || $islem === 'vekil_yenile') {
        $yenile = $islem === 'vekil_yenile';
        $ana = trim((string)($_POST['ana'] ?? '')); if (!preg_match('~^https?://[^\s/]+(/[^\s?#]*)?$~i', $ana)) $ana = $varsayAna;
        $yenile = $yenile || pf_vekil_al($db, $kid) !== null;       // var olan (kapatılmış) kayıt yeniden açılırken de yeni anahtar üretilir
        pf_vekil_dosyalar($db, $k, $ana, $yenile);          // kayıt + özetler
        $host = pf_vekil_host_norm(preg_replace('~^https?://~i', '', trim((string)($_POST['host'] ?? ''))));
        $host = preg_replace('~/.*$~', '', $host);
        if ($host !== '' && !preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $host)) ph('Alan adı geçersiz (örn. kurumum.com).');
        if ($islem === 'vekil_yenile') $host = ($_POST['host_koru'] ?? '') === '1' ? (pf_vekil_al($db, $kid)['host'] ?? '') : $host;
        $db->kurum = 0; $st = $db->prepare("UPDATE pf_vekil SET host = ?, bekleyen_host = '', bekleyen_ip = '' WHERE kurum_id = ?"); $st->bind_param('si', $host, $kid); $st->execute();
        pf_vekil_olay($db, $kid, $islem === 'vekil_yenile' ? 'yenilendi' : 'olustu', $host !== '' ? 'Alan adı: ' . $host : 'Alan adı ilk kullanımda bağlanacak', '');
        pf_log($db, $islem, $k['kod'], $kid);
        tb_json(['ok' => true]);
    }
    $v = pf_vekil_al($db, $kid); if (!$v) ph('Bu kurum için uzak sunucu bağlantısı yok.');
    if (strncmp($islem, 'vekil_db_', 9) === 0) {
        // Kurumun veri tabanı vekil sunucudaki MySQL'e taşınır; dosyalar taşınmaz. Uzak MySQL'in bu (ana) sunucudan bağlantı kabul etmesi gerekir.
        pf_vdb_sema($db); $d = pf_vdb_al($db, $kid); $kodl = $k['kod'];
        if ($islem === 'vekil_db_kaydet' || $islem === 'vekil_db_test') {
            $h = trim((string)($_POST['host'] ?? ($d['host'] ?? ''))); $pt = intval($_POST['port'] ?? ($d['port'] ?? 3306)) ?: 3306; $ad = trim((string)($_POST['ad'] ?? ($d['ad'] ?? ''))); $kul = trim((string)($_POST['kul'] ?? ($d['kul'] ?? '')));
            $sifre = (string)($_POST['sifre'] ?? ''); $sifreHam = $sifre !== '' ? $sifre : ($d ? VDB::uzak_coz($d['sifre']) : '');
            if ($h === '' || $ad === '' || $kul === '') ph('Sunucu adresi, veri tabanı adı ve kullanıcı gerekli.');
            if (!preg_match('/^[A-Za-z0-9._\-]+$/', $h) || !preg_match('/^[A-Za-z0-9_$\-]+$/', $ad)) ph('Sunucu adresi veya veri tabanı adı geçersiz.');
            $m = VDB::uzak_ac($h, $pt, $ad, $kul, $sifreHam); $sonuc = is_string($m) ? $m : 'Bağlantı başarılı'; $say = 0; $varMi = 0;
            if (is_object($m)) { $say = count(pf_vdb_tablolar($m, $kid)); $varMi = $say; $surum = (string)$m->server_info; $sonuc .= ' (MySQL ' . $surum . ')'; }
            if ($islem === 'vekil_db_test' || is_string($m)) {
                if ($d) { $db->kurum = 0; $st = $db->prepare("UPDATE pf_vekil_db SET son_test = NOW(), son_sonuc = ? WHERE kurum_id = ?"); $sn = mb_substr($sonuc, 0, 300, 'UTF-8'); $st->bind_param('si', $sn, $kid); $st->execute(); }
                tb_json(is_string($m) ? ['ok' => false, 'hata' => $sonuc, 'sonuc' => $sonuc] : ['ok' => true, 'sonuc' => $sonuc, 'uzak_tablo' => $varMi], is_string($m) ? 400 : 200);
            }
            $enc = $sifre !== '' ? VDB::uzak_sifrele($sifre) : ($d['sifre'] ?? '');
            $db->kurum = 0; $st = $db->prepare("INSERT INTO pf_vekil_db (kurum_id, host, port, ad, kul, sifre, aktif, son_test, son_sonuc) VALUES (?, ?, ?, ?, ?, ?, 0, NOW(), ?) ON DUPLICATE KEY UPDATE host = VALUES(host), port = VALUES(port), ad = VALUES(ad), kul = VALUES(kul), sifre = VALUES(sifre), son_test = NOW(), son_sonuc = VALUES(son_sonuc)");
            $st->bind_param('isissss', $kid, $h, $pt, $ad, $kul, $enc, $sonuc); $st->execute();
            pf_log($db, 'vekil_db_kaydet', $kodl . ' ' . $h . '/' . $ad, $kid);
            tb_json(['ok' => true, 'sonuc' => $sonuc, 'uzak_tablo' => $varMi]);
        }
        if (!$d) ph('Önce veri tabanı bağlantı bilgilerini kaydedin.');
        if ($islem === 'vekil_db_tasi') {       // ana → uzak: kopyala, doğrula, sonra yönlendirmeyi aç
            if (intval($d['aktif']) === 1) ph('Veri tabanı zaten vekil sunucuda.');
            $m = VDB::uzak_ac($d['host'], $d['port'], $d['ad'], $d['kul'], VDB::uzak_coz($d['sifre'])); if (is_string($m)) ph($m);
            $ana = pf_vdb_ana_baglanti(); if (is_string($ana)) ph($ana);
            $r = pf_vdb_kopyala($ana, $m, $kid); if (is_string($r)) ph($r);
            $ozet = $r[0] . ' tablo, ' . $r[1] . ' satır taşındı';
            $db->kurum = 0; $st = $db->prepare("UPDATE pf_vekil_db SET aktif = 1, tasima = NOW(), tasima_ozet = ? WHERE kurum_id = ?"); $st->bind_param('si', $ozet, $kid); $st->execute();
            VDB::uzak_ayar_yenile(); pf_log($db, 'vekil_db_tasi', $kodl . ': ' . $ozet, $kid);
            tb_json(['ok' => true, 'ozet' => $ozet]);
        }
        if ($islem === 'vekil_db_geri') {       // uzak → ana: kopyala, sonra yönlendirmeyi kapat (uzaktaki veri silinmez)
            if (intval($d['aktif']) !== 1) ph('Veri tabanı zaten ana sunucuda.');
            $m = VDB::uzak_ac($d['host'], $d['port'], $d['ad'], $d['kul'], VDB::uzak_coz($d['sifre'])); if (is_string($m)) ph($m);
            $ana = pf_vdb_ana_baglanti(); if (is_string($ana)) ph($ana);
            $r = pf_vdb_kopyala($m, $ana, $kid); if (is_string($r)) ph($r);
            $ozet = $r[0] . ' tablo, ' . $r[1] . ' satır ana sunucuya geri alındı';
            $db->kurum = 0; $st = $db->prepare("UPDATE pf_vekil_db SET aktif = 0, tasima = NOW(), tasima_ozet = ? WHERE kurum_id = ?"); $st->bind_param('si', $ozet, $kid); $st->execute();
            VDB::uzak_ayar_yenile(); pf_log($db, 'vekil_db_geri', $kodl . ': ' . $ozet, $kid);
            tb_json(['ok' => true, 'ozet' => $ozet]);
        }
        if ($islem === 'vekil_db_yedekle') {     // kullanılan veri tabanının, DİĞER taraftaki kopyasını günceller (yönlendirme değişmez)
            if (!$d) ph('Önce veri tabanı bilgilerini kaydedin.');
            $r = pf_vdb_yedekle($db, $kid, $d); if (!$r[0]) ph($r[1]);
            $db->kurum = 0; $st = $db->prepare("UPDATE pf_vekil_db SET tasima = NOW(), tasima_ozet = ? WHERE kurum_id = ?"); $st->bind_param('si', $r[1], $kid); $st->execute();
            pf_log($db, 'vekil_db_yedekle', $kodl . ': ' . $r[1], $kid);
            tb_json(['ok' => true, 'ozet' => $r[1]]);
        }
        if ($islem === 'vekil_db_oto') {         // otomatik yedek aralığı (saat; 0 = kapalı)
            if (!$d) ph('Önce veri tabanı bilgilerini kaydedin.');
            $dk = intval($_POST['dk'] ?? 0); $gun = trim((string)($_POST['gun'] ?? ''));
            if ($gun !== '' && !preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $gun)) ph('Saat SS:DD biçiminde olmalı.');
            if ($gun !== '') $dk = 0;
            elseif ($dk !== 0 && ($dk < 5 || $dk > 10080)) ph('Aralık 5 dakika ile 7 gün (10080 dk) arasında olmalı.');
            $ac = ($dk > 0 || $gun !== '') ? 1 : 0;
            $db->kurum = 0; $st = $db->prepare("UPDATE pf_vekil_db SET oto_dk = ?, oto_gun = ?, oto_saat = 0, oto_son = IF(? > 0, oto_son, NULL) WHERE kurum_id = ?"); $st->bind_param('isii', $dk, $gun, $ac, $kid); $st->execute();
            pf_log($db, 'vekil_db_oto', $kodl . ': ' . ($gun !== '' ? "her gün $gun" : ($dk ? "$dk dakikada bir" : 'kapalı')), $kid);
            tb_json(['ok' => true]);
        }
        ph('Bilinmeyen işlem.');
    }
    if ($islem === 'vekil_kapat') {
        if (($vd = pf_vdb_al($db, $kid)) && intval($vd['aktif']) === 1) ph('Önce veri tabanını ana sunucuya geri alın (Veri tabanı bölümü).');
        $db->kurum = 0; $db->query("UPDATE pf_vekil SET durum = 'kapali', uyari = '', uyari_ayrinti = '', bekleyen_host = '' WHERE kurum_id = $kid");
        pf_vekil_olay($db, $kid, 'kapatildi'); pf_log($db, 'vekil_kapat', $k['kod'], $kid); tb_json(['ok' => true]);
    }
    if ($islem === 'vekil_onayla') {              // başka alan adında kullanılmaya çalışılan bağlantıyı onayla: kayıtlı alan adı yenisi olur
        if ($v['bekleyen_host'] === '') ph('Onay bekleyen alan adı yok.');
        $ekle = ($_POST['mod'] ?? '') === 'ekle'; $yeniHost = $v['bekleyen_host'];
        if ($ekle) { $l = array_filter(array_map('trim', explode(',', (string)$v['host']))); if (!in_array($yeniHost, $l, true)) $l[] = $yeniHost; $yeniHost = implode(',', $l); }
        $db->kurum = 0; $st = $db->prepare("UPDATE pf_vekil SET host = ?, bekleyen_host = '', bekleyen_ip = '', uyari = '', uyari_ayrinti = '' WHERE kurum_id = ?"); $st->bind_param('si', $yeniHost, $kid); $st->execute();
        pf_vekil_olay($db, $kid, 'onay', ($ekle ? 'İkisi de aktif: ' : 'Yeni adres: ') . $yeniHost); pf_log($db, 'vekil_onay', ($ekle ? 'ekle: ' : 'degistir: ') . $v['bekleyen_host'], $kid); tb_json(['ok' => true]);
    }
    if ($islem === 'vekil_uyari_tamam') {
        $db->kurum = 0; $db->query("UPDATE pf_vekil SET uyari = '', uyari_ayrinti = '', bekleyen_host = '', bekleyen_ip = '' WHERE kurum_id = $kid"); tb_json(['ok' => true]);
    }
    ph('Bilinmeyen işlem.');
}

if ($islem === 'lm_kaydet' || $islem === 'lm_tara' || $islem === 'kurum_toplu_mesaj') {
    require_once __DIR__ . '/platform_mesaj.php'; pf_lm_sema($db); @set_time_limit(120);
    if ($islem === 'lm_kaydet') {
        $t = pf_lm_tanim(); unset($t['lm_son_tarama']);
        foreach ($t as $k => $_) {
            if (!array_key_exists($k, $_POST)) continue;
            $v = trim((string)$_POST[$k]);
            if (in_array($k, ['lm_aktif', 'lm_sms', 'lm_mail', 'lm_bitti'], true)) $v = $v === '1' ? '1' : '0';
            elseif ($k === 'lm_kurum') $v = (string)intval($v);
            elseif ($k === 'lm_gunler') { $g = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s;]+/', $v)), function ($x) { return $x >= 0 && $x <= 90; }))); rsort($g); $v = implode(',', $g); if ($v === '') $v = '10,3'; }
            else $v = mb_substr($v, 0, 1500, 'UTF-8');
            pf_lm_yaz($db, $k, $v);
        }
        pf_log($db, 'lm_ayar', 'Süre mesajı ayarları kaydedildi');
        tb_json(['ok' => true]);
    }
    if ($islem === 'lm_tara') {
        $r = pf_lm_tara($db, true);
        if (($r['neden'] ?? '') === 'kapali') ph('Önce otomatik mesajları açın ve gönderen kurumu seçin.');
        tb_json(['ok' => true, 'gonderilen' => intval($r['gonderilen'])]);
    }
    // kurum_toplu_mesaj
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)json_decode((string)($_POST['ids'] ?? '[]'), true)))));
    if (!$ids) ph('Kurum seçilmedi.'); if (count($ids) > 200) ph('Tek seferde en fazla 200 kurum seçilebilir.');
    $sms = ($_POST['sms'] ?? '') === '1'; $mail = ($_POST['mail'] ?? '') === '1'; if (!$sms && !$mail) ph('SMS veya e-postadan en az birini seçin.');
    $mesaj = trim((string)($_POST['mesaj'] ?? '')); if ($mesaj === '') ph('Mesaj metnini yazın.');
    $konu = trim((string)($_POST['konu'] ?? '')); if ($mail && $konu === '') ph('E-posta konusunu yazın.');
    $hz = pf_lm_hazir($db); if (!$hz['kurum']) ph('Önce Süre mesajları sayfasından “Gönderen kurum” seçin (SMS / e-posta ayarları oradan alınır).');
    if ($sms && !$hz['sms']) ph('Gönderen kurumda SMS ayarı yapılmamış.'); if ($mail && !$hz['mail']) ph('Gönderen kurumda e-posta ayarı yapılmamış.');
    $ok = 0; $hata = []; $smsOk = 0; $mailOk = 0;
    foreach ($ids as $kid) {
        $k = pf_kurum($db, $kid); if (!$k) continue;
        $r = pf_lm_gonder($db, $k, 'toplu', $mesaj, $konu, $mesaj, $sms, $mail);
        if (isset($r['hata'])) ph($r['hata']);
        $iyi = false;
        if ($r['sms'] && $r['sms'][0]) { $smsOk++; $iyi = true; } elseif ($r['sms']) $hata[] = $k['ad'] . ' (SMS): ' . $r['sms'][1];
        if ($r['mail'] && $r['mail'][0]) { $mailOk++; $iyi = true; } elseif ($r['mail']) $hata[] = $k['ad'] . ' (e-posta): ' . $r['mail'][1];
        if ($iyi) $ok++;
    }
    pf_log($db, 'toplu_mesaj', count($ids) . ' kurum · SMS ' . $smsOk . ' · e-posta ' . $mailOk);
    tb_json(['ok' => true, 'kurum' => $ok, 'sms' => $smsOk, 'mail' => $mailOk, 'hatalar' => array_slice($hata, 0, 30)]);
}
if (strncmp($islem, 'yedek', 5) === 0 || strncmp($islem, 'talep_', 6) === 0) {
    require_once __DIR__ . '/kurum_yedek.php'; yd_sema($db);
    if ($islem === 'yedek_durum') {
        $k = []; $q = $db->query("SELECT id, ad, kod, durum FROM pf_kurumlar ORDER BY ad");
        if ($q) while ($r = $q->fetch_assoc()) { $id = intval($r['id']); $i = $db->query("SELECT COUNT(*), COALESCE(SUM(boyut),0) FROM pf_yedekler WHERE kurum_id = $id")->fetch_row();
            $k[] = ['id' => $id, 'ad' => $r['ad'], 'kod' => $r['kod'], 'durum' => $r['durum'], 'son' => yd_son_yedek($db, $id), 'adet' => intval($i[0]), 'boyut' => intval($i[1])]; }
        tb_json(['ok' => true, 'ayar' => ['aralik' => pf_ayar($db, 'yedek_aralik', '7'), 'saklama' => pf_ayar($db, 'yedek_saklama', '8'), 'eposta' => pf_ayar($db, 'iletisim_eposta'), 'telefon' => pf_ayar($db, 'iletisim_telefon')],
            'kurumlar' => $k, 'talepler' => yd_talepler($db), 'islemler' => yd_islemler($db), 'zip' => class_exists('ZipArchive'), 'limit' => ini_get('upload_max_filesize')]);
    }
    if ($islem === 'yedek_dosya') {
        if (!csrf_dogru()) ph('Oturum doğrulaması başarısız.', 419);
        yd_indir($db, intval($_GET['id'] ?? 0));
    }
    if ($islem === 'yedek_liste') { $r = yd_api($db, $islem, intval($_GET['kurum_id'] ?? 0)); tb_json($r); }
}
if ($islem === 'kurum_paketi_indir') {
    if (!csrf_dogru()) ph('Oturum doğrulaması başarısız.', 419);
    require_once __DIR__ . '/bakim_ortak.php'; @set_time_limit(0); if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $k = pf_kurum($db, intval($_GET['id'] ?? 0)); if (!$k) ph('Kurum bulunamadı.', 404);
    try { $zp = tb_kurum_paketi($db, $k['id']); } catch (Throwable $e) { ph($e->getMessage(), 500); }
    pf_log($db, 'kurum_paketi', $k['ad'], intval($k['id']));
    $ad = preg_replace('/[^a-z0-9_-]/', '', $k['kod']) . '_kurum_paketi_' . date('Ymd') . '.zip';
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($zp)); header('Content-Disposition: attachment; filename="' . $ad . '"'); header('Cache-Control: no-store');
    readfile($zp); @unlink($zp); exit;
}

if ($islem === 'aktivasyon_paketi_indir') {
    if (!csrf_dogru()) ph('Oturum doğrulaması başarısız.', 419);
    require_once __DIR__ . '/aktivasyon_ortak.php'; @set_time_limit(0); @ini_set('memory_limit', '1024M'); if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    $k = pf_kurum($db, intval($_GET['id'] ?? 0)); if (!$k) ph('Kurum bulunamadı.', 404);
    try { $zp = akt_paket($db, $k['id'], rtrim(preg_match('~^https?://~i', (string)pf_ayar($db, 'site_url', '')) ? pf_ayar($db, 'site_url', '') : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/')), '/')); } catch (Throwable $e) { ph($e->getMessage(), 400); }
    pf_log($db, 'aktivasyon_paketi', $k['ad'], intval($k['id']));
    $ad = preg_replace('/[^a-z0-9_-]/', '', $k['kod']) . '_aktivasyonlu_paket_' . date('Ymd') . '.zip';
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($zp)); header('Content-Disposition: attachment; filename="' . $ad . '"'); header('Cache-Control: no-store');
    readfile($zp); @unlink($zp); exit;
}

// ───────────────────────── YAZMA ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !in_array($islem, ['paket_liste', 'kurum_paket'], true)) ph('Bilinmeyen işlem.', 400);

if (strncmp($islem, 'yedek', 5) === 0 || strncmp($islem, 'talep_', 6) === 0) {
    if (!csrf_dogru()) ph('Oturum doğrulaması başarısız.', 419);
    require_once __DIR__ . '/kurum_yedek.php'; yd_sema($db); @set_time_limit(0);
    if ($islem === 'yedek_ayar_kaydet') {
        $ar = intval($_POST['aralik'] ?? 7); if (!in_array($ar, [0, 1, 2, 3, 5, 7, 10, 14, 30], true)) ph('Geçersiz aralık.');
        $sk = max(1, min(60, intval($_POST['saklama'] ?? 8))); $m = trim((string)($_POST['eposta'] ?? '')); $t = trim((string)($_POST['telefon'] ?? ''));
        if ($m !== '' && !filter_var($m, FILTER_VALIDATE_EMAIL)) ph('İletişim e-postası geçersiz.');
        pf_ayar_yaz($db, 'yedek_aralik', (string)$ar); pf_ayar_yaz($db, 'yedek_saklama', (string)$sk); pf_ayar_yaz($db, 'iletisim_eposta', $m); pf_ayar_yaz($db, 'iletisim_telefon', mb_substr($t, 0, 30));
        pf_ayar_yaz($db, 'yedek_son_kontrol', '0'); pf_log($db, 'yedek_ayar', "aralık: $ar gün");
        tb_json(['ok' => true]);
    }
    if ($islem === 'yedek_hepsi') {
        $q = $db->query("SELECT id FROM pf_kurumlar ORDER BY id"); $ids = []; if ($q) while ($r = $q->fetch_row()) $ids[] = intval($r[0]); $n = 0; $h = [];
        foreach ($ids as $id) { try { yd_yedekle($db, $id, 'elle', 'Toplu yedek'); $n++; } catch (Throwable $e) { $db->kurum = 0; $h[] = $e->getMessage(); } }
        pf_log($db, 'yedek_hepsi', "$n kurum"); tb_json(['ok' => true, 'n' => $n, 'hata' => $h]);
    }
    if ($islem === 'yedek_kurum_olustur') {
        if (empty($_FILES['dosya']) || $_FILES['dosya']['error'] !== UPLOAD_ERR_OK) ph('Dosya yüklenemedi (boyut sınırı: ' . ini_get('upload_max_filesize') . ').');
        try { $r = yd_kurum_yedekten($db, $_FILES['dosya']['tmp_name'], (string)($_POST['ad'] ?? ''), (string)($_POST['kod'] ?? '')); }
        catch (Throwable $e) { ph($e->getMessage()); }
        tb_json(['ok' => true] + $r);
    }
    if ($islem === 'talep_yanit') {
        $d = (string)($_POST['durum'] ?? ''); if (!in_array($d, ['tamamlandi', 'reddedildi', 'bekliyor'], true)) ph('Geçersiz durum.');
        $st = $db->prepare("UPDATE pf_yedek_talepleri SET durum = ?, yanit = ?, islem_zamani = NOW() WHERE id = ?"); $y = mb_substr(trim((string)($_POST['yanit'] ?? '')), 0, 800, 'UTF-8'); $id = intval($_POST['id'] ?? 0);
        $st->bind_param('ssi', $d, $y, $id); $st->execute(); tb_json(['ok' => true]);
    }
    $r = yd_api($db, $islem, intval($_POST['kurum_id'] ?? 0));
    if ($r === null) ph('Bilinmeyen işlem.');
    if (!empty($r['ok']) && in_array($islem, ['yedek_al', 'yedek_sil', 'yedek_uygula'], true)) pf_log($db, $islem, '', intval($_POST['kurum_id'] ?? 0));
    tb_json($r, !empty($r['ok']) ? 200 : 400);
}
if ($islem === 'cikis') { unset($_SESSION['pf_yonetici_id'], $_SESSION['pf_yonetici_ad'], $_SESSION['destek']); tb_json(['ok' => true]); }

if ($islem === 'sifre_degistir') {
    $st = $db->prepare("SELECT sifre FROM pf_yoneticiler WHERE id = ?"); $st->bind_param('i', $pid); $st->execute(); $r = $st->get_result()->fetch_row();
    if (!$r || !password_verify((string)($_POST['eski'] ?? ''), $r[0])) ph('Mevcut şifre hatalı.');
    $yeni = (string)($_POST['yeni'] ?? ''); if (!pf_sifre_ok($yeni)) ph('Yeni şifre en az 10 karakter olmalı ve harf + rakam içermeli.');
    $h = password_hash($yeni, PASSWORD_DEFAULT); $st = $db->prepare("UPDATE pf_yoneticiler SET sifre = ? WHERE id = ?"); $st->bind_param('si', $h, $pid); $st->execute();
    pf_log($db, 'platform_sifre', ''); tb_json(['ok' => true]);
}

if ($islem === 'kurum_kaydet') {
    $id = intval($_POST['id'] ?? 0);
    if ($id === 0) {
        $r = pf_kurum_olustur($db, $_POST);
        if (!$r['ok']) ph($r['hata']);
        if (($_POST['barindirma'] ?? '') === 'kod') { require_once __DIR__ . '/aktivasyon_ortak.php'; $r['akt_kod'] = akt_olustur($db, $r['id']); pf_log($db, 'aktivasyon_kodu', $_POST['ad'] ?? '', intval($r['id'])); }
        tb_json($r);
    }
    $k = pf_kurum($db, $id); if (!$k) ph('Kurum bulunamadı.', 404);
    $ad = trim((string)($_POST['ad'] ?? '')); if ($ad === '') ph('Kurum adı gerekli.');
    $renk = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($_POST['renk'] ?? '')) ? $_POST['renk'] : $k['renk'];
    $mail = trim((string)($_POST['email'] ?? '')); if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) ph('E-posta adresi geçersiz.');
    $tel = mb_substr(trim((string)($_POST['telefon'] ?? '')), 0, 30, 'UTF-8'); $sinir = max(0, intval($_POST['kullanici_siniri'] ?? 0)); $not = (string)($_POST['notlar'] ?? '');
    $imza = !empty($_POST['imza_goster']) ? 1 : 0;
    $st = $db->prepare("UPDATE pf_kurumlar SET ad = ?, renk = ?, email = ?, telefon = ?, kullanici_siniri = ?, notlar = ?, imza_goster = ? WHERE id = ?");
    $st->bind_param('ssssisii', $ad, $renk, $mail, $tel, $sinir, $not, $imza, $id); $st->execute();
    $sec = (string)($_POST['lisans'] ?? '');
    $bt = trim((string)($_POST['lisans_bitis'] ?? ''));
    if (($sec === '' || $sec === 'degistirme') && $bt !== '' && $bt !== (string)($k['lisans_bitis'] ?? '')) $sec = 'ozel'; // yalnız tarih değiştirildiyse de uygula
    if ($sec !== '' && $sec !== 'degistirme') {
        $lis = pf_lisans_hesapla($sec, $_POST['lisans_bitis'] ?? '', $sec === 'ozel' ? null : $k); if (!$lis) ph('Bitiş tarihini seçin.');
        $st = $db->prepare("UPDATE pf_kurumlar SET lisans_tur = ?, lisans_baslangic = ?, lisans_bitis = ? WHERE id = ?"); $st->bind_param('sssi', $lis[0], $lis[1], $lis[2], $id); $st->execute();
        pf_log($db, 'kurum_lisans', $k['ad'] . ' → ' . $lis[0] . ($lis[2] ? ' (' . $lis[2] . ')' : ''), $id);
    }
    pf_log($db, 'kurum_duzenle', $ad, $id); tb_json(['ok' => true]);
}
if ($islem === 'aktivasyon_yenile' || $islem === 'aktivasyon_iptal') {
    require_once __DIR__ . '/aktivasyon_ortak.php';
    $id = intval($_POST['id'] ?? 0); $k = pf_kurum($db, $id); if (!$k) ph('Kurum bulunamadı.', 404);
    if ($islem === 'aktivasyon_iptal') { akt_iptal($db, $id); pf_log($db, 'aktivasyon_iptal', $k['ad'], $id); tb_json(['ok' => true]); }
    $kod = akt_olustur($db, $id, !empty($k['paket_kodlu'])); pf_log($db, 'aktivasyon_yenile', $k['ad'], $id); tb_json(['ok' => true, 'kod' => $kod]);
}
if ($islem === 'aktivasyon_kopya') {      // var olan kurumun kodlu KOPYASI: kurum bu sunucuda çalışmaya devam eder
    require_once __DIR__ . '/aktivasyon_ortak.php'; akt_sema($db);
    $id = intval($_POST['id'] ?? 0); $k = pf_kurum($db, $id); if (!$k) ph('Kurum bulunamadı.', 404);
    if (akt_al($db, $id)) ph('Bu kurum için zaten bir aktivasyon kaydı var; “Aktivasyon” düğmesinden yönetin.');
    $kod = akt_olustur($db, $id, false); pf_log($db, 'aktivasyon_kopya', $k['ad'], $id); tb_json(['ok' => true, 'kod' => $kod]);
}
if ($islem === 'kurum_durum') {
    $id = intval($_POST['id'] ?? 0); $d = ($_POST['durum'] ?? '') === 'askida' ? 'askida' : 'aktif';
    $k = pf_kurum($db, $id); if (!$k) ph('Kurum bulunamadı.', 404);
    $db->query("UPDATE pf_kurumlar SET durum = '$d' WHERE id = $id");
    pf_log($db, $d === 'askida' ? 'kurum_askiya' : 'kurum_aktif', $k['ad'], $id); tb_json(['ok' => true]);
}
if ($islem === 'kurum_sil') {
    $id = intval($_POST['id'] ?? 0); $k = pf_kurum($db, $id); if (!$k) ph('Kurum bulunamadı.', 404);
    if (!hash_equals($k['kod'], strtolower(trim((string)($_POST['onay'] ?? ''))))) ph('Silmek için kurum kodunu aynen yazmalısınız.');
    $n = pf_kurum_tablolarini_sil($db, $id);
    try { $db->query("DELETE FROM pf_vekil WHERE kurum_id = $id"); $db->query("DELETE FROM pf_vekil_olay WHERE kurum_id = $id"); } catch (Throwable $e) {}
    $db->query("DELETE FROM pf_kurumlar WHERE id = $id");
    $d = TB_KOK . '/uploads/k' . $id;
    if (is_dir($d)) { $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST); foreach ($it as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); } @rmdir($d); }
    pf_eski_sil($k['logo']);
    pf_log($db, 'kurum_sil', $k['ad'] . " ($n tablo)", null); tb_json(['ok' => true]);
}
if ($islem === 'kurum_logo') {
    $id = intval($_POST['id'] ?? 0); $k = pf_kurum($db, $id); if (!$k) ph('Kurum bulunamadı.', 404);
    if (!empty($_POST['kaldir'])) { pf_eski_sil($k['logo']); $db->query("UPDATE pf_kurumlar SET logo = '' WHERE id = $id"); tb_json(['ok' => true, 'logo' => '']); }
    $yol = pf_yukle_dosya($_FILES['dosya'] ?? null, PF_RESIM, 1048576);
    pf_eski_sil($k['logo']);
    $st = $db->prepare("UPDATE pf_kurumlar SET logo = ? WHERE id = ?"); $st->bind_param('si', $yol, $id); $st->execute();
    tb_json(['ok' => true, 'logo' => $yol]);
}
if ($islem === 'yetkili_ata') {
    $kid = intval($_POST['kurum_id'] ?? 0); $k = pf_kurum_ac($db, $kid);
    $dusur = !isset($_POST['eskiyi_dusur']) || $_POST['eskiyi_dusur'] !== '0';
    $hedef = intval($_POST['kullanici_id'] ?? 0); $sifre = '';
    if ($hedef > 0) {
        $q = $db->query("SELECT id, ad_soyad, kullanici_adi FROM kullanicilar WHERE id = $hedef AND aktif = 1"); $u = $q ? $q->fetch_assoc() : null;
        if (!$u) { $db->kurum = 0; ph('Seçilen kullanıcı bulunamadı ya da pasif.'); }
        $kadi = $u['kullanici_adi']; $yad = $u['ad_soyad'];
    } else {
        $yad = trim((string)($_POST['yeni_ad'] ?? '')); $kadi = strtolower(trim((string)($_POST['yeni_kullanici'] ?? ''))) ?: kullanici_adi_oner($yad);
        $r = kurum_kullanici_ekle($db, ['kullanici_adi' => $kadi, 'ad_soyad' => $yad, 'rol' => 'kurucu', 'gorev' => 'Kurum yöneticisi', 'telefon' => $_POST['yeni_telefon'] ?? '', 'email' => $_POST['yeni_email'] ?? '']);
        if (!$r['ok']) { $db->kurum = 0; ph($r['hata']); }
        $hedef = $r['id']; $sifre = $r['sifre'];
    }
    if ($dusur && intval($k['yetkili_id']) > 0 && intval($k['yetkili_id']) !== $hedef) $db->query("UPDATE kullanicilar SET rol = 'yetkili' WHERE id = " . intval($k['yetkili_id']) . " AND rol = 'kurucu'");
    $db->query("UPDATE kullanicilar SET rol = 'kurucu', aktif = 1 WHERE id = $hedef");
    $db->kurum = 0;
    $st = $db->prepare("UPDATE pf_kurumlar SET yetkili_id = ?, yetkili_ad = ? WHERE id = ?"); $st->bind_param('isi', $hedef, $yad, $kid); $st->execute();
    pf_log($db, 'yetkili_ata', "$yad ($kadi)", $kid);
    tb_json(['ok' => true, 'kullanici_adi' => $kadi, 'sifre' => $sifre]);
}
if ($islem === 'sifre_sifirla') {
    $kid = intval($_POST['kurum_id'] ?? 0); pf_kurum_ac($db, $kid); $uid = intval($_POST['kullanici_id'] ?? 0);
    $q = $db->query("SELECT kullanici_adi FROM kullanicilar WHERE id = $uid"); $r = $q ? $q->fetch_row() : null;
    if (!$r) { $db->kurum = 0; ph('Kullanıcı bulunamadı.', 404); }
    $s = pf_sifre_uret(10); $h = password_hash($s, PASSWORD_DEFAULT);
    $st = $db->prepare("UPDATE kullanicilar SET sifre = ?, sifre_degistir = 1 WHERE id = ?"); $st->bind_param('si', $h, $uid); $st->execute();
    pf_hatirla_kisi_sil($db, $kid, $uid);
    $db->kurum = 0; pf_log($db, 'sifre_sifirla', $r[0], $kid);
    tb_json(['ok' => true, 'kullanici_adi' => $r[0], 'sifre' => $s]);
}
if ($islem === 'kurum_gir') {                      // Destek girişi: kurum yetkilisi olarak kurumu açar (kayıt altına alınır)
    $kid = intval($_POST['kurum_id'] ?? 0); $k = pf_kurum_ac($db, $kid);
    if ($k['durum'] !== 'aktif') { $db->kurum = 0; ph('Kurum askıda; önce etkinleştirin.'); }
    $uid = intval($k['yetkili_id']); $q = $db->query("SELECT id, ad_soyad FROM kullanicilar WHERE id = $uid AND aktif = 1"); $u = $q ? $q->fetch_assoc() : null;
    $db->kurum = 0;
    if (!$u) ph('Kurumun etkin bir yetkilisi yok; önce yetkili atayın.');
    $pfid = $_SESSION['pf_yonetici_id']; $pfad = $_SESSION['pf_yonetici_ad'];
    session_regenerate_id(true);
    $_SESSION['pf_yonetici_id'] = $pfid; $_SESSION['pf_yonetici_ad'] = $pfad;
    $_SESSION['kurum_id'] = $kid; $_SESSION['kullanici_id'] = intval($u['id']); $_SESSION['ad_soyad'] = $u['ad_soyad']; $_SESSION['destek'] = 1;
    $_SESSION['tb_csrf'] = bin2hex(random_bytes(24));
    pf_log($db, 'destek_girisi', $k['ad'], $kid);
    tb_json(['ok' => true, 'git' => 'ik.php']);
}
if ($islem === 'kullanici_nokta_kaldir') {   // tüm kurumlarda "ali.veli" → "aliveli" (kuru=1: yalnızca say)
    $kuru = !empty($_POST['kuru']); $say = 0; $atla = []; $kurumlar = [];
    $db->kurum = 0; $q = $db->query("SELECT id, kod FROM pf_kurumlar ORDER BY id"); if ($q) while ($r = $q->fetch_assoc()) $kurumlar[] = $r;
    foreach ($kurumlar as $k) {
        $kid = intval($k['id']); $db->kurum = $kid; $liste = [];
        $q = @$db->query("SELECT id, kullanici_adi FROM kullanicilar WHERE kullanici_adi LIKE '%.%'"); if ($q) while ($r = $q->fetch_assoc()) $liste[] = $r;
        foreach ($liste as $r) {
            $yeni = str_replace('.', '', (string)$r['kullanici_adi']); $id = intval($r['id']);
            if (strlen($yeni) < 3 || !kullanici_adi_gecerli($yeni)) { $atla[] = $k['kod'] . ': ' . $r['kullanici_adi'] . ' (geçersiz olur)'; continue; }
            $cak = pf_kullanici_cakisma($db, $yeni, '', $kid, $id); $db->kurum = $kid;
            if ($cak !== '') { $atla[] = $k['kod'] . ': ' . $r['kullanici_adi'] . ' (“' . $yeni . '” başkasında var)'; continue; }
            if (!$kuru) { $st = $db->prepare("UPDATE kullanicilar SET kullanici_adi = ? WHERE id = ?"); $st->bind_param('si', $yeni, $id); if (!$st->execute()) { $atla[] = $k['kod'] . ': ' . $r['kullanici_adi'] . ' (kaydedilemedi)'; continue; } }
            $say++;
        }
    }
    $db->kurum = 0; if (!$kuru) pf_log($db, 'kullanici_nokta_kaldir', $say . ' kullanıcı adı güncellendi, ' . count($atla) . ' atlandı');
    tb_json(['ok' => true, 'say' => $say, 'atla' => $atla, 'kuru' => $kuru]);
}
if ($islem === 'sema_onar') {
    $kid = intval($_POST['kurum_id'] ?? 0); pf_kurum_ac($db, $kid);
    try { kurum_sema($db); } catch (Throwable $e) { $db->kurum = 0; ph('Şema onarılamadı: ' . $e->getMessage(), 500); }
    $db->kurum = 0; pf_log($db, 'sema_onar', '', $kid); tb_json(['ok' => true]);
}

if ($islem === 'ayar_kaydet') {
    $tan = pf_ayar_tanim(); $n = 0;
    foreach ($tan as $k => $d) {
        if (!array_key_exists($k, $_POST) || $d[3] === 'dosya') continue;
        $v = trim((string)$_POST[$k]); $tur = $d[3];
        if ($tur === 'renk' && !preg_match('/^#[0-9a-fA-F]{6}$/', $v)) ph('“' . $d[2] . '” #RRGGBB biçiminde olmalı.');
        if ($tur === 'onay') $v = $v === '1' ? '1' : '0';
        elseif (strncmp($tur, 'secim:', 6) === 0) { if (!in_array($v, explode('|', substr($tur, 6)), true)) ph('“' . $d[2] . '” için geçersiz seçim.'); }
        elseif ($tur === 'uzun') $v = mb_substr($v, 0, 6000, 'UTF-8');
        else $v = mb_substr($v, 0, 500, 'UTF-8');
        if ($k === 'site_url' && $v !== '' && !preg_match('~^https?://[^\s]+$~i', $v)) ph('Site adresi http(s):// ile başlamalı.');
        if ($k === 'seo_canonical' && $v !== '' && !preg_match('~^https?://[^\s]+$~i', $v)) ph('Canonical adres http(s):// ile başlamalı.');
        if ($k === 'destek_mail' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) ph('Destek e-postası geçersiz.');
        if ($k === 'analitik_kod' && $v !== '' && !preg_match('/^(G|GT|UA|AW)-[A-Z0-9-]{4,20}$/i', $v)) ph('Ölçüm kimliği G-XXXXXXXX biçiminde olmalı.');
        if ($k === 'site_adi' && $v === '') ph('Site adı boş olamaz.');
        if ($k === 'giris_hak') $v = (string)max(3, min(30, intval($v ?: 6)));
        pf_ayar_yaz($db, $k, $v); $n++;
    }
    pf_log($db, 'ayar_kaydet', "$n ayar"); tb_json(['ok' => true, 'n' => $n]);
}
if ($islem === 'dosya_yukle' || $islem === 'dosya_sil') {
    $k = (string)($_POST['anahtar'] ?? '');
    if (!in_array($k, ['logo', 'favicon', 'og_gorsel'], true)) ph('Geçersiz alan.');
    $eski = pf_ayar($db, $k);
    if ($islem === 'dosya_sil') { pf_eski_sil($eski); pf_ayar_yaz($db, $k, ''); tb_json(['ok' => true, 'yol' => '']); }
    $yol = pf_yukle_dosya($_FILES['dosya'] ?? null, $k === 'favicon' ? PF_FAVICON : PF_RESIM, $k === 'favicon' ? 524288 : 2097152);
    pf_eski_sil($eski); pf_ayar_yaz($db, $k, $yol);
    pf_log($db, 'dosya_yukle', $k); tb_json(['ok' => true, 'yol' => $yol]);
}
if ($islem === 'yonetici_kaydet') {
    $id = intval($_POST['id'] ?? 0); $ad = trim((string)($_POST['ad_soyad'] ?? '')); $u = strtolower(trim((string)($_POST['kullanici_adi'] ?? '')));
    $mail = trim((string)($_POST['email'] ?? '')); $s = (string)($_POST['sifre'] ?? ''); $aktif = ($_POST['aktif'] ?? '1') === '0' ? 0 : 1;
    if ($ad === '' || !kullanici_adi_gecerli($u)) ph('Ad soyad ve geçerli bir kullanıcı adı girin.');
    if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) ph('E-posta geçersiz.');
    if ($id === 0) {
        if (!pf_sifre_ok($s)) ph('Şifre en az 10 karakter olmalı ve harf + rakam içermeli.');
        $h = password_hash($s, PASSWORD_DEFAULT);
        $st = $db->prepare("INSERT INTO pf_yoneticiler (kullanici_adi, sifre, ad_soyad, email, aktif) VALUES (?, ?, ?, ?, 1)"); $st->bind_param('ssss', $u, $h, $ad, $mail);
        if (!$st->execute()) ph($db->hatano() === 1062 ? 'Bu kullanıcı adı kullanılıyor.' : 'Kaydedilemedi.');
        pf_log($db, 'yonetici_ekle', $u); tb_json(['ok' => true]);
    }
    if ($id === $pid && $aktif === 0) ph('Kendi hesabınızı pasifleştiremezsiniz.');
    $st = $db->prepare("UPDATE pf_yoneticiler SET ad_soyad = ?, email = ?, aktif = ? WHERE id = ?"); $st->bind_param('ssii', $ad, $mail, $aktif, $id); $st->execute();
    if ($s !== '') { if (!pf_sifre_ok($s)) ph('Şifre en az 10 karakter olmalı ve harf + rakam içermeli.'); $h = password_hash($s, PASSWORD_DEFAULT); $st = $db->prepare("UPDATE pf_yoneticiler SET sifre = ? WHERE id = ?"); $st->bind_param('si', $h, $id); $st->execute(); }
    pf_log($db, 'yonetici_duzenle', $u); tb_json(['ok' => true]);
}
if ($islem === 'engel_kaldir') {                         // tüm (ya da seçili ip/anahtar) giriş engellerini kaldırır
    $ip = trim((string)($_POST['ip'] ?? ''));
    if ($ip !== '') { $st = $db->prepare("DELETE FROM pf_giris_deneme WHERE ip = ?"); $st->bind_param('s', $ip); $st->execute(); }
    else $db->query("DELETE FROM pf_giris_deneme");
    pf_log($db, 'engel_kaldir', $ip !== '' ? $ip : 'tümü'); tb_json(['ok' => true]);
}
if ($islem === 'yonetici_sil') {
    $id = intval($_POST['id'] ?? 0);
    if ($id === $pid) ph('Kendi hesabınızı silemezsiniz.');
    $q = $db->query("SELECT COUNT(*) FROM pf_yoneticiler WHERE aktif = 1 AND id <> $id"); if (!$q || intval($q->fetch_row()[0]) < 1) ph('Son etkin yöneticiyi silemezsiniz.');
    $db->query("DELETE FROM pf_yoneticiler WHERE id = $id"); pf_log($db, 'yonetici_sil', "#$id"); tb_json(['ok' => true]);
}
if ($islem === 'kaynak_kurumlar' || $islem === 'kurum_cek') {
    require_once __DIR__ . '/bakim_ortak.php'; @set_time_limit(0);
    $u = pf_ayar($db, 'guncelleme_kaynak'); $k = pf_ayar($db, 'guncelleme_kaynak_anahtar');
    if ($u === '' || $k === '') ph('Önce kaynak adresini ve anahtarını kaydedin.');
    try {
        if ($islem === 'kaynak_kurumlar') {
            $r = json_decode((string)tb_uzak_istek($u . (strpos($u, '?') === false ? '?' : '&') . 'kurumlar=1', $k, false), true);
            if (!is_array($r) || empty($r['ok'])) ph('Kaynak kurum listesini vermedi (kaynak sunucuda da güncel sürüm olmalı).');
            tb_json(['ok' => true, 'kurumlar' => $r['kurumlar'] ?? []]);
        }
        $kid = intval($_POST['kurum_id'] ?? 0); if ($kid < 1) ph('Kurum seçin.');
        $zp = tb_gecici('.zip'); tb_uzak_istek($u . (strpos($u, '?') === false ? '?' : '&') . 'kurum=' . $kid, $k, true, $zp);
        $h = null; $hd = trim((string)($_POST['hdb_ad'] ?? ''));
        if ($hd !== '') {                                              // hedef: başka bir veri tabanı (bilgiler forma yazılır, saklanmaz)
            $hh = trim((string)($_POST['hdb_host'] ?? '')) ?: 'localhost'; $hp = intval($_POST['hdb_port'] ?? 3306) ?: 3306; $hk = trim((string)($_POST['hdb_kullanici'] ?? '')); $hs = (string)($_POST['hdb_sifre'] ?? '');
            if (!preg_match('/^[A-Za-z0-9_$.\-]+$/', $hd)) ph('Veri tabanı adı geçersiz.');
            mysqli_report(MYSQLI_REPORT_OFF); $h = @new mysqli($hh, $hk, $hs, $hd, $hp);
            if ($h->connect_errno) { @unlink($zp); ph('Hedef veri tabanına bağlanılamadı: ' . $h->connect_error . ' (' . $h->connect_errno . ')'); }
        }
        try { $r = tb_kurum_veri_al($db, $zp, $h); } finally { @unlink($zp); }
        pf_log($db, 'kurum_cek', $r['ad'] . ' (kaynaktan #' . $r['id'] . ($h ? ' → ' . $hd : '') . ')', $r['yerel'] ? $r['id'] : null); tb_json(['ok' => true] + $r);
    } catch (Throwable $e) { ph($e->getMessage(), 500); }
}
if (in_array($islem, ['yedek_yukle', 'paket_yukle', 'uzak_guncelle', 'kaynak_kaydet', 'anahtar_yenile'], true)) {
    require_once __DIR__ . '/bakim_ortak.php';
    if ($islem === 'anahtar_yenile') { pf_ayar_yaz($db, 'guncelleme_anahtari', bin2hex(random_bytes(20))); pf_log($db, 'anahtar_yenile', ''); tb_json(['ok' => true]); }
    if ($islem === 'kaynak_kaydet') {
        $u = trim((string)($_POST['kaynak'] ?? '')); $k = trim((string)($_POST['kaynak_anahtar'] ?? ''));
        if ($u !== '' && !preg_match('~^https?://[^\s]+$~i', $u)) ph('Kaynak adresi http(s):// ile başlamalı.');
        pf_ayar_yaz($db, 'guncelleme_kaynak', $u); pf_ayar_yaz($db, 'guncelleme_kaynak_anahtar', $k); tb_json(['ok' => true]);
    }
    try {
        if ($islem === 'uzak_guncelle') {
            $u = pf_ayar($db, 'guncelleme_kaynak'); $k = pf_ayar($db, 'guncelleme_kaynak_anahtar');
            if ($u === '' || $k === '') ph('Önce güncelleme kaynağı adresini ve anahtarını kaydedin.');
            $zp = tb_gecici('.zip'); tb_uzak_istek($u, $k, true, $zp);
        } else {
            $f = $_FILES['dosya'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) ph('Dosya yüklenemedi (PHP upload_max_filesize / post_max_size sınırını kontrol edin).');
            $zp = $f['tmp_name'];
        }
        if ($islem === 'yedek_yukle') {
            if (trim((string)($_POST['onay'] ?? '')) !== 'GERİ YÜKLE') ph('Onay ifadesi hatalı.');
            $r = tb_geri_yukle($db, $zp);
            $db->kurum = 0; pf_ayarlar($db, true); tb_migrasyon_calistir($db);
            @file_put_contents(TB_KOK . '/config/kurulum.kilit', date('c'));
            tb_json(['ok' => true, 'tablo' => $r[0], 'dosya' => $r[1], 'oturum_gecersiz' => true]);
        }
        $r = tb_guncelle($db, $zp);
        if ($islem === 'uzak_guncelle') @unlink($zp);
        pf_log($db, 'guncelleme', 'sürüm ' . $r['surum'] . ', ' . $r['dosya'] . ' dosya');
        tb_json(['ok' => true] + $r);
    } catch (Throwable $e) { if ($islem === 'uzak_guncelle' && isset($zp)) @unlink($zp); ph($e->getMessage(), 500); }
}

// ── Modül paketleri ──
if (strpos($islem, 'paket_') === 0 && $islem !== 'paket_kaynak' && $islem !== 'paket_indir' || $islem === 'kurum_paket' || strpos($islem, 'kurum_paket_') === 0) {
    require_once __DIR__ . '/platform_paket.php'; pf_paket_sema($db);
    $kat = pf_paket_katalog();
    if ($islem === 'paket_liste') {
        $liste = []; $q = $db->query("SELECT p.*, (SELECT COUNT(*) FROM pf_kurum_paket kp WHERE kp.paket_id = p.id AND kp.bit >= CURDATE()) kurum_say FROM pf_paketler p ORDER BY p.tam DESC, p.sira, p.id");
        if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'kod' => $r['kod'], 'ad' => $r['ad'], 'aciklama' => $r['aciklama'], 'renk' => $r['renk'], 'tam' => intval($r['tam']), 'aylik' => (float)$r['aylik'], 'yillik' => (float)$r['yillik'], 'para' => $r['para'], 'aktif' => intval($r['aktif']), 'moduller' => pf_paket_modul_liste($r), 'kurum_say' => intval($r['kurum_say'])];
        $tal = []; $q = $db->query("SELECT t.*, k.ad kurum, p.ad paket FROM pf_paket_talep t LEFT JOIN pf_kurumlar k ON k.id = t.kurum_id LEFT JOIN pf_paketler p ON p.id = t.paket_id ORDER BY t.id DESC LIMIT 60");
        if ($q) while ($r = $q->fetch_assoc()) $tal[] = $r;
        tb_json(['ok' => true, 'paketler' => $liste, 'talepler' => $tal, 'katalog' => array_map(function ($m) { return ['ad' => $m[0], 'ikon' => $m[1], 'renk' => $m[2], 'aciklama' => $m[3]]; }, $kat)]);
    }
    if ($islem === 'paket_kaydet') {
        $id = intval($_POST['id'] ?? 0); $ad = mb_substr(trim((string)($_POST['ad'] ?? '')), 0, 120, 'UTF-8'); if ($ad === '') ph('Paket adını yazın.');
        $ac = mb_substr(trim((string)($_POST['aciklama'] ?? '')), 0, 500, 'UTF-8'); $rn = preg_match('/^#[0-9a-f]{6}$/i', (string)($_POST['renk'] ?? '')) ? $_POST['renk'] : '#6366f1';
        $ay = max(0, round((float)str_replace(',', '.', (string)($_POST['aylik'] ?? 0)), 2)); $yi = max(0, round((float)str_replace(',', '.', (string)($_POST['yillik'] ?? 0)), 2));
        $para = in_array($_POST['para'] ?? 'TRY', ['TRY', 'USD', 'EUR'], true) ? $_POST['para'] : 'TRY'; $akt = !empty($_POST['aktif']) ? 1 : 0;
        $tam = !empty($_POST['tam']) ? 1 : 0; $mod = array_values(array_filter(explode(',', (string)($_POST['moduller'] ?? '')), function ($x) use ($kat) { return isset($kat[$x]); })); $mj = json_encode($mod);
        if ($id) { $st = $db->prepare("UPDATE pf_paketler SET ad=?, aciklama=?, renk=?, moduller=?, aylik=?, yillik=?, para=?, aktif=? WHERE id=?"); $st->bind_param('ssssddsii', $ad, $ac, $rn, $mj, $ay, $yi, $para, $akt, $id); $st->execute(); }
        else { $kod = 'p' . substr(md5(uniqid('', true)), 0, 8); $sr = 50; $st = $db->prepare("INSERT INTO pf_paketler (kod, ad, aciklama, renk, moduller, tam, aylik, yillik, para, sira, aktif) VALUES (?,?,?,?,?,?,?,?,?,?,?)"); $st->bind_param('sssssiddsii', $kod, $ad, $ac, $rn, $mj, $tam, $ay, $yi, $para, $sr, $akt); $st->execute(); $id = $db->son_id(); }
        pf_log($db, 'paket_kaydet', $ad . ' · aylık ' . $ay . ' / yıllık ' . $yi . ' ' . $para); tb_json(['ok' => true, 'id' => $id]);
    }
    if ($islem === 'paket_sil') {
        $id = intval($_POST['id'] ?? 0); $r = $db->query("SELECT tam FROM pf_paketler WHERE id = $id")->fetch_row(); if (!$r) ph('Paket bulunamadı.', 404);
        $db->query("DELETE FROM pf_kurum_paket WHERE paket_id = $id"); $db->query("DELETE FROM pf_paketler WHERE id = $id"); pf_log($db, 'paket_sil', '#' . $id); tb_json(['ok' => true]);
    }
    if ($islem === 'paket_talep_durum') { $id = intval($_POST['id'] ?? 0); $d = in_array($_POST['durum'] ?? '', ['yeni', 'islendi'], true) ? $_POST['durum'] : 'islendi'; $db->query("UPDATE pf_paket_talep SET durum = '$d' WHERE id = $id"); tb_json(['ok' => true]); }
    if ($islem === 'kurum_paket') {
        $kid = intval($_GET['kurum_id'] ?? 0); $k = pf_paket_kurum_satir($db, $kid); if (!$k) ph('Kurum bulunamadı.', 404);
        $bugun = date('Y-m-d'); $at = []; $q = $db->query("SELECT * FROM pf_kurum_paket WHERE kurum_id = $kid"); if ($q) while ($r = $q->fetch_assoc()) $at[intval($r['paket_id'])] = $r;
        $liste = []; $q = $db->query("SELECT * FROM pf_paketler ORDER BY tam DESC, sira, id");
        if ($q) while ($p = $q->fetch_assoc()) { $o = pf_paket_satir_cikti($p, $k); $o['aktif'] = intval($p['aktif']); $a = $at[intval($p['id'])] ?? null;
            if ($a) { $o['bas'] = $a['bas']; $o['bit'] = $a['bit']; $o['kalan'] = (int)round((strtotime($a['bit']) - strtotime($bugun)) / 86400); $o['atama_id'] = intval($a['id']); }
            $liste[] = $o; }
        tb_json(['ok' => true, 'kurum' => ['id' => $kid, 'ad' => $k['ad'], 'paket_modu' => intval($k['paket_modu']), 'paket_gorunur' => intval($k['paket_gorunur']), 'vitrin_gorunur' => intval($k['vitrin_gorunur']), 'pk_bilgi' => intval($k['pk_bilgi']), 'pk_sure' => intval($k['pk_sure']), 'pk_banner' => intval($k['pk_banner']), 'pk_gizli' => (string)$k['pk_gizli'],
            'ind_aylik_tip' => $k['ind_aylik_tip'], 'ind_aylik' => (float)$k['ind_aylik'], 'ind_yillik_tip' => $k['ind_yillik_tip'], 'ind_yillik' => (float)$k['ind_yillik']], 'paketler' => $liste]);
    }
    if ($islem === 'kurum_paket_ata') {
        $kid = intval($_POST['kurum_id'] ?? 0); $k = pf_paket_kurum_satir($db, $kid); if (!$k) ph('Kurum bulunamadı.', 404);
        $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['paket_ids'] ?? '')))))); if (!$ids) ph('En az bir paket seçin.');
        $tur = in_array($_POST['tur'] ?? '', ['aylik', 'yillik', 'gun', 'tarih'], true) ? $_POST['tur'] : 'aylik'; $gun = intval($_POST['gun'] ?? 30); $tar = ik_tarih_pf($_POST['tarih'] ?? '');
        if ($tur === 'tarih' && !$tar) ph('Bitiş tarihini seçin.'); if ($tur === 'gun' && $gun < 1) ph('Gün sayısını yazın.');
        $son = ''; foreach ($ids as $pid) { if (!$db->query("SELECT 1 FROM pf_paketler WHERE id = $pid")->num_rows) continue; $son = pf_paket_ata($db, $kid, $pid, $tur, $gun, $tar); }
        if (!empty($_POST['modu_ac'])) $db->query("UPDATE pf_kurumlar SET paket_modu = 1 WHERE id = $kid");
        pf_log($db, 'kurum_paket_ata', $k['ad'] . ' · ' . count($ids) . ' paket · ' . $tur . ($son ? ' → ' . $son : ''), $kid); tb_json(['ok' => true]);
    }
    if ($islem === 'kurum_paket_sil') { $id = intval($_POST['id'] ?? 0); $r = $db->query("SELECT kurum_id FROM pf_kurum_paket WHERE id = $id")->fetch_row(); $db->query("DELETE FROM pf_kurum_paket WHERE id = $id"); pf_log($db, 'kurum_paket_sil', '#' . $id, $r ? intval($r[0]) : null); tb_json(['ok' => true]); }
    if ($islem === 'kurum_paket_ayar') {
        $kid = intval($_POST['kurum_id'] ?? 0); $k = pf_paket_kurum_satir($db, $kid); if (!$k) ph('Kurum bulunamadı.', 404);
        $tip = function ($x) { return $x === 'tl' ? 'tl' : 'yuzde'; };
        $dg = function ($t, $v) { $v = max(0, round((float)str_replace(',', '.', (string)$v), 2)); return $t === 'yuzde' ? min(100, $v) : $v; };
        $ta = $tip($_POST['ind_aylik_tip'] ?? ''); $ty = $tip($_POST['ind_yillik_tip'] ?? ''); $va = $dg($ta, $_POST['ind_aylik'] ?? 0); $vy = $dg($ty, $_POST['ind_yillik'] ?? 0);
        $m = !empty($_POST['paket_modu']) ? 1 : 0; $g = !empty($_POST['paket_gorunur']) ? 1 : 0; $v = !empty($_POST['vitrin_gorunur']) ? 1 : 0;
        $pb = !empty($_POST['pk_bilgi']) ? 1 : 0; $ps = !empty($_POST['pk_sure']) ? 1 : 0; $pn = !empty($_POST['pk_banner']) ? 1 : 0;
        $gz = implode(',', array_values(array_unique(array_filter(array_map('intval', explode(',', (string)($_POST['pk_gizli'] ?? '')))))));
        $st = $db->prepare("UPDATE pf_kurumlar SET paket_modu=?, paket_gorunur=?, vitrin_gorunur=?, ind_aylik_tip=?, ind_aylik=?, ind_yillik_tip=?, ind_yillik=?, pk_bilgi=?, pk_sure=?, pk_banner=?, pk_gizli=? WHERE id=?"); $st->bind_param('iiisdsdiiisi', $m, $g, $v, $ta, $va, $ty, $vy, $pb, $ps, $pn, $gz, $kid); $st->execute();
        pf_log($db, 'kurum_paket_ayar', $k['ad'] . ' · mod ' . $m . ' · görünür ' . $g . ' · vitrin ' . $v . ' · indirim aylık ' . $va . ($ta === 'tl' ? ' TL' : '%') . ' yıllık ' . $vy . ($ty === 'tl' ? ' TL' : '%'), $kid); tb_json(['ok' => true]);
    }
}
function ik_tarih_pf($t) { $t = (string)$t; return preg_match('/^\d{4}-\d{2}-\d{2}$/', $t) && checkdate((int)substr($t, 5, 2), (int)substr($t, 8, 2), (int)substr($t, 0, 4)) ? $t : ''; }

ph('Bilinmeyen işlem.', 400);