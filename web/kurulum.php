<?php
// ============================================================
// kurulum.php — Kurulum sihirbazı
//   Adım 1: gereksinimler + veritabanı bilgileri (config/db.php otomatik yazılır)
//   Adım 2: yeni kurulum (site adı + platform yöneticisi)  YA DA  başka sunucudaki yedekten geri yükleme
//   Bitince config/kurulum.kilit oluşur; sihirbaz bir daha açılmaz.
// ============================================================
if (session_status() === PHP_SESSION_NONE) { session_name('TBKURULUM'); @session_start(); }
if (empty($_SESSION['kur_csrf'])) $_SESSION['kur_csrf'] = bin2hex(random_bytes(16));
$KOK = __DIR__; $KILIT = $KOK . '/config/kurulum.kilit'; $DBY = $KOK . '/config/db.php';
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$hata = ''; $mesaj = ''; $adim = 1; $bitti = false; $kilitli = is_file($KILIT);
$ayar = is_file($DBY) ? (require $DBY) : [];
$sablon = ['host' => 'localhost', 'kullanici' => '', 'sifre' => '', 'veritabani' => '', 'port' => 3306, 'saat_dilimi' => 'Europe/Istanbul', 'oturum_adi' => 'TBSID'];

function kur_baglan($a, $dbAdi = true)
{
    mysqli_report(MYSQLI_REPORT_OFF);
    $m = @new mysqli($a['host'] ?? 'localhost', $a['kullanici'] ?? '', $a['sifre'] ?? '', $dbAdi ? ($a['veritabani'] ?? '') : '', intval($a['port'] ?? 3306));
    $GLOBALS['kur_son_hata'] = $m->connect_errno ? ($m->connect_errno . ': ' . $m->connect_error) : '';
    return $m->connect_errno ? null : $m;
}

/** Bağlantı hatasının nerede olduğunu bulur: şifre alınışı, kullanıcı doğrulama (veritabanı seçmeden), veritabanı erişimi, farklı sunucu adları */
/** Sık yapılan kullanıcı adı hatalarının olası doğrusu: ön ek iki kez yazılmış, ön ek unutulmuş, kullanıcı = veritabanı adı */
function kur_varyantlar($y)
{
    $u = $y['kullanici']; $db = $y['veritabani']; $v = [];
    $ek = strpos($db, '_') !== false ? substr($db, 0, strpos($db, '_') + 1) : '';
    $t = preg_replace('/^([A-Za-z0-9]+_)\1+/', '$1', $u); if ($t !== $u) $v[] = $t;                 // turk_turk_x → turk_x
    if ($ek !== '' && stripos($u, $ek) !== 0) $v[] = $ek . $u;                                    // x → turk_x
    if ($u !== $db) $v[] = $db;                                                                     // kullanıcı adı = veritabanı adı
    return array_values(array_unique($v));
}
function kur_tani($y)
{
    $sat = []; $sif = (string)$y['sifre'];
    $ozel = [];
    if ($sif === '') $ozel[] = 'şifre BOŞ geldi';
    if ($sif !== trim($sif)) $ozel[] = 'başında/sonunda boşluk var';
    if (preg_match('/[^\x20-\x7E]/', $sif)) $ozel[] = 'ASCII dışı karakter var (Türkçe harf / farklı klavye?)';
    $sat[] = ['Şifre sunucuya ulaştı mı?', $sif === '' ? 'kt' : 'ok', 'uzunluk: ' . strlen($sif) . ($sif !== '' ? ' · ilk karakter: ' . htmlspecialchars(substr($sif, 0, 1)) . ' · son karakter: ' . htmlspecialchars(substr($sif, -1)) : '') . ($ozel ? ' · UYARI: ' . implode(', ', $ozel) : '') . ' — beklediğiniz uzunlukla aynı mı?'];
    $sat[] = ['Kullanıcı adı', 'ok', '"' . htmlspecialchars($y['kullanici']) . '" (' . strlen($y['kullanici']) . ' karakter)'];
    $hostlar = array_values(array_unique([$y['host'], 'localhost', '127.0.0.1']));
    $kimlikTamam = false; $calisan = '';
    foreach ($hostlar as $h) {
        mysqli_report(MYSQLI_REPORT_OFF);
        $m = @new mysqli($h, $y['kullanici'], $sif, '', intval($y['port']));
        if ($m->connect_errno) { $sat[] = ["Kimlik doğrulama — sunucu “$h” (veritabanı seçmeden)", 'kt', htmlspecialchars($m->connect_errno . ': ' . $m->connect_error)]; continue; }
        $kimlikTamam = true; $calisan = $calisan ?: $h;
        $sat[] = ["Kimlik doğrulama — sunucu “$h” (veritabanı seçmeden)", 'ok', 'Kullanıcı ve şifre DOĞRU · sunucu: ' . htmlspecialchars($m->server_info)];
        $d = @$m->select_db($y['veritabani']);
        $sat[] = ["Veritabanı erişimi — “{$y['veritabani']}” ($h)", $d ? 'ok' : 'kt', $d ? 'Erişilebiliyor' : htmlspecialchars($m->errno . ': ' . $m->error) . ' — kullanıcı bu veritabanına eklenmemiş ya da veritabanı adı yanlış'];
        if ($d) break;
    }
    foreach (kur_varyantlar($y) as $vk) {
        $y2 = $y; $y2['kullanici'] = $vk; $mm = kur_baglan($y2, false);
        $sat[] = ['Olası doğru kullanıcı adı denemesi: “' . htmlspecialchars($vk) . '”', $mm ? 'ok' : 'kt', $mm ? 'ÇALIŞIYOR — bu adla kurulum otomatik yapılır' : htmlspecialchars($GLOBALS['kur_son_hata'])];
    }
    $yorum = !$kimlikTamam
        ? 'SONUÇ: Hiçbir sunucu adıyla bu kullanıcı + şifre kabul edilmedi. Sorun kurulum kodunda değil; hosting panelinde bu MySQL kullanıcısının şifresi (veya kullanıcı adı) sunucudakiyle eşleşmiyor. Panelden şifreyi yeniden belirleyip yukarıdaki “şifre uzunluğu” ile karşılaştırın.'
        : 'SONUÇ: Kullanıcı ve şifre doğru (sunucu adı: ' . $calisan . '). ' . ($calisan !== $y['host'] ? 'Sunucu alanına “' . $calisan . '” yazın. ' : '') . 'Veritabanı satırına bakın: kırmızıysa kullanıcıyı veritabanına tüm yetkilerle ekleyin ya da ad hatalıdır.';
    return [$sat, $yorum, 'mysqli: ' . mysqli_get_client_info() . ' · PHP ' . PHP_VERSION . ' · default_socket: ' . ini_get('mysqli.default_socket')];
}
function kur_gereksinim($KOK)
{
    $w = function ($d) { if (!is_dir($d)) @mkdir($d, 0755, true); return is_dir($d) && is_writable($d); };
    return [
        ['PHP 7.4 veya üstü (' . PHP_VERSION . ')', version_compare(PHP_VERSION, '7.4', '>='), true],
        ['mysqli eklentisi', extension_loaded('mysqli'), true],
        ['mbstring eklentisi', extension_loaded('mbstring'), true],
        ['mysqlnd sürücüsü' . (method_exists('mysqli_stmt', 'get_result') ? '' : ' — yok, uyumluluk modu kullanılacak'), method_exists('mysqli_stmt', 'get_result'), false],
        ['zip eklentisi (yedek / güncelleme için)', class_exists('ZipArchive'), false],
        ['curl eklentisi (uzaktan güncelleme için)', function_exists('curl_init'), false],
        ['config/ klasörü yazılabilir', is_writable($KOK . '/config'), false],
        ['uploads/ klasörü yazılabilir', $w($KOK . '/uploads'), true],
        ['storage/ klasörü yazılabilir', $w($KOK . '/storage'), true],
    ];
}
$gerek = kur_gereksinim($KOK);
$gerekTamam = true; foreach ($gerek as $g) if ($g[2] && !$g[1]) $gerekTamam = false;

$dbHazir = !empty($ayar['kullanici']) && !empty($ayar['veritabani']) && $ayar['kullanici'] !== 'VERITABANI_KULLANICI' && ($c = kur_baglan($ayar)) !== null;
$dbYaz = null; $tani = null;
// Tek kurum paketi (platformdan indirilen): zip içindeki kurum_paketi/ klasörü varsa sihirbaz tek adıma iner
$kpYol = $KOK . '/kurum_paketi';
$kp = (is_file($kpYol . '/veri.sql') && is_file($kpYol . '/kurum.json')) ? json_decode((string)@file_get_contents($kpYol . '/kurum.json'), true) : null;
if (!is_array($kp)) $kp = null;

if ($kilitli) {
    // kurulu
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postCsrf = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals($_SESSION['kur_csrf'], $postCsrf)) $hata = 'Oturum doğrulaması başarısız, sayfayı yenileyin.';
    elseif (!$gerekTamam) $hata = 'Zorunlu gereksinimler karşılanmadan devam edilemez.';
    elseif (($_POST['islem'] ?? '') === 'db') {
        $y = $sablon;
        $y['host'] = trim((string)($_POST['host'] ?? 'localhost')); $y['port'] = max(1, intval($_POST['port'] ?? 3306));
        $y['veritabani'] = trim((string)($_POST['veritabani'] ?? '')); $y['kullanici'] = trim((string)($_POST['kullanici'] ?? '')); $y['sifre'] = (string)($_POST['sifre'] ?? '');
        $y['oturum_adi'] = 'TBSID' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        if ($y['veritabani'] === '' || $y['kullanici'] === '' || !preg_match('/^[A-Za-z0-9_$.\-]+$/', $y['veritabani'])) $hata = 'Veritabanı adı ve kullanıcı gerekli (veritabanı adında yalnızca harf, rakam, _ - kullanın).';
        else {
            $m = kur_baglan($y);
            $ilkHata = $GLOBALS['kur_son_hata'] ?? '';
            if (!$m && !empty($_POST['olustur'])) {
                $m0 = kur_baglan($y, false);
                if ($m0) { $m0->query('CREATE DATABASE IF NOT EXISTS `' . $m0->real_escape_string($y['veritabani']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $m = kur_baglan($y); }
            }
            $ilkHata2 = $GLOBALS['kur_son_hata'] ?? '';
            if (!$m && $y['sifre'] !== trim($y['sifre']) && trim($y['sifre']) !== '') {   // yapıştırmadan gelen baştaki/sondaki boşluk: kırpılmışı dene
                $y3 = $y; $y3['sifre'] = trim($y['sifre']); $m3 = kur_baglan($y3);
                if ($m3) { $_SESSION['kur_not'] = 'Şifrenin başındaki/sonundaki boşluk silinerek bağlanıldı.'; $y = $y3; $m = $m3; }
            }
            if (!$m) {                                              // olası kullanıcı adı hatası: varyantları dene, çalışanla devam et
                foreach (kur_varyantlar($y) as $vk) {
                    $y2 = $y; $y2['kullanici'] = $vk; $m2 = kur_baglan($y2);
                    if ($m2) { $_SESSION['kur_not'] = 'Kullanıcı adı “' . $y['kullanici'] . '” ile bağlanılamadı; “' . $vk . '” ile bağlanıldı ve bu ad kaydedildi.'; $y = $y2; $m = $m2; break; }
                }
            }
            if (!$m) {
                $son = $ilkHata2 !== '' ? $ilkHata2 : ($GLOBALS['kur_son_hata'] ?? $ilkHata);
                $hata = 'Veritabanına bağlanılamadı' . ($son !== '' ? " (MySQL: $son)" : '') . '. ';
                if (strpos($son, '1045') === 0) $hata .= 'Kullanıcı adı veya şifre hatalı; şifreyi yeniden yazın.';
                elseif (strpos($son, '1049') === 0) $hata .= 'Böyle bir veritabanı yok; hosting panelinde oluşturun (cPanel gibi panellerde buradan oluşturulamaz).';
                elseif (strpos($son, '1044') === 0) $hata .= 'Bu kullanıcının bu veritabanına yetkisi yok; panelde kullanıcıyı veritabanına ekleyin (tüm yetkiler).';
                elseif (strpos($son, '2002') === 0 || strpos($son, '2006') === 0) $hata .= 'Sunucu adı yanlış olabilir; hosting panelindeki MySQL sunucu adını girin.';
                $tani = kur_tani($y);
            }
            else {
                $icerik = "<?php\n// config/db.php — kurulum sihirbazı tarafından oluşturuldu\nreturn " . var_export($y, true) . ";\n";
                if (@file_put_contents($DBY, $icerik) === false) $dbYaz = $icerik;
                else { @chmod($DBY, 0640); if (function_exists('opcache_invalidate')) @opcache_invalidate($DBY, true); header('Location: kurulum.php'); exit; }
            }
        }
    } elseif ($dbHazir && ($_POST['islem'] ?? '') === 'kurum_paketi' && $kp) {
        require_once $KOK . '/config/baglan.php';
        require_once $KOK . '/bakim_ortak.php';
        try {
            @set_time_limit(0);
            $n = tb_sql_yukle($db, $kpYol . '/veri.sql');
            $kk = $db->query("SELECT id, ad FROM pf_kurumlar ORDER BY id LIMIT 1")->fetch_assoc();
            if (!$kk) throw new RuntimeException('Pakette kurum kaydı bulunamadı.');
            if (@file_put_contents($KOK . '/config/kurum.id', (string)intval($kk['id'])) === false) throw new RuntimeException('config/kurum.id yazılamadı (config/ klasörü yazılabilir mi?).');
            pf_ayarlar($db, true); tb_migrasyon_calistir($db);
            $akj = $kpYol . '/aktivasyon.json';
            if (is_file($akj)) {                                              // aktivasyon kodlu paket: anahtar dosyası + veri tabanı işareti
                $A = json_decode((string)file_get_contents($akj), true);
                if (!is_array($A) || empty($A['aid']) || empty($A['anahtar']) || empty($A['ana'])) throw new RuntimeException('aktivasyon.json bozuk.');
                $ic = "<?php\n// config/aktivasyon.anahtar.php — SİLMEYİN / DEĞİŞTİRMEYİN: lisans anahtarı\nreturn " . var_export(['aid' => $A['aid'], 'ana' => $A['ana'], 'anahtar' => $A['anahtar'], 'pk' => $A['pk'] ?? ''], true) . ";\n";
                if (@file_put_contents($KOK . '/config/aktivasyon.anahtar.php', $ic) === false) throw new RuntimeException('config/aktivasyon.anahtar.php yazılamadı.');
                @chmod($KOK . '/config/aktivasyon.anahtar.php', 0640);
                $st = $db->prepare("INSERT INTO pf_ayarlar (anahtar, deger) VALUES ('aktivasyon_id', ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)"); $st->bind_param('s', $A['aid']); $st->execute();
                @unlink($akj);
                $aktYonlendir = true;
            }
            @unlink($kpYol . '/veri.sql');                                   // kişisel veri içerir: kurulumdan sonra sunucuda bırakılmaz
            $mesaj = $kk['ad'] . ' kurumu kuruldu (' . $n . ' tablo).'; $bitti = true;
            @file_put_contents($KILIT, date('c')); $kilitli = true;
        } catch (Throwable $e) { $hata = $e->getMessage(); }
    } elseif ($dbHazir && in_array(($_POST['islem'] ?? ''), ['yeni', 'yedek'], true)) {
        require_once $KOK . '/config/baglan.php';
        require_once $KOK . '/bakim_ortak.php';
        $var = $db->query("SELECT COUNT(*) FROM pf_yoneticiler")->fetch_row();
        if (intval($var[0] ?? 0) > 0 && ($_POST['islem'] ?? '') === 'yeni') $hata = 'Bu veritabanında zaten bir platform yöneticisi var. Başka bir veritabanı kullanın ya da yedekten geri yükleyin.';
        elseif ($_POST['islem'] === 'yeni') {
            $ad = trim((string)($_POST['ad_soyad'] ?? '')); $u = strtolower(trim((string)($_POST['kullanici_adi'] ?? ''))); $s = (string)($_POST['sifre'] ?? ''); $site = trim((string)($_POST['site_adi'] ?? ''));
            if ($ad === '' || !kullanici_adi_gecerli($u)) $hata = 'Ad soyad ve geçerli bir kullanıcı adı (3–60, küçük harf/rakam/./_/-) girin.';
            elseif (strlen($s) < 10 || !preg_match('/[A-Za-z]/', $s) || !preg_match('/\d/', $s)) $hata = 'Şifre en az 10 karakter olmalı ve harf + rakam içermeli.';
            elseif ($s !== (string)($_POST['sifre2'] ?? '')) $hata = 'Şifreler aynı değil.';
            else {
                $hash = password_hash($s, PASSWORD_DEFAULT);
                $st = $db->prepare("INSERT INTO pf_yoneticiler (kullanici_adi, sifre, ad_soyad, aktif) VALUES (?, ?, ?, 1)");
                $st->bind_param('sss', $u, $hash, $ad);
                if ($st->execute()) {
                    if ($site !== '') pf_ayar_yaz($db, 'site_adi', mb_substr($site, 0, 80, 'UTF-8'));
                    pf_log($db, 'kurulum', $u); $bitti = true;
                } else $hata = 'Yönetici kaydedilemedi: ' . $db->error;
            }
        } else {
            $f = $_FILES['yedek'] ?? null;
            if (!$f || $f['error'] !== UPLOAD_ERR_OK) $hata = 'Yedek dosyası yüklenemedi (PHP upload_max_filesize / post_max_size sınırını kontrol edin).';
            else {
                try {
                    $r = tb_geri_yukle($db, $f['tmp_name']);
                    $v = $db->query("SELECT COUNT(*) FROM pf_yoneticiler")->fetch_row();
                    if (intval($v[0] ?? 0) < 1) throw new RuntimeException('Yedekte platform yöneticisi bulunamadı.');
                    pf_ayarlar($db, true); tb_migrasyon_calistir($db);
                    $mesaj = "Yedek yüklendi: {$r[0]} tablo, {$r[1]} dosya."; $bitti = true;
                } catch (Throwable $e) { $hata = $e->getMessage(); }
            }
        }
        if ($bitti) { @file_put_contents($KILIT, date('c')); $kilitli = true; }
    }
}
// Veritabanı hazırsa ve platformda yönetici varsa (eski kurulum) kilidi kendiliğinden oluştur
if (!$kilitli && $dbHazir && !$bitti) {
    require_once $KOK . '/config/baglan.php';
    $v = $db->query("SELECT COUNT(*) FROM pf_yoneticiler")->fetch_row();
    if (intval($v[0] ?? 0) > 0) { @file_put_contents($KILIT, date('c')); $kilitli = true; }
    else $adim = 2;
}
$renk = '#0d9488'; $renk2 = '#6366f1'; $siteAdi = 'Turkuaz Bileşim İK';
if (isset($db) && function_exists('pf_ayarlar')) { $a = pf_ayarlar($db); $renk = $a['tema_renk']; $renk2 = $a['giris_renk2']; $siteAdi = $a['site_adi']; }
$post = function ($k, $v = '') use ($h) { return $h($_POST[$k] ?? $v); };
?><!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Kurulum — <?= $h($siteAdi) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"><link rel="stylesheet" href="assets/css/giris.css"><style>:root{--a1:<?= $h($renk) ?>;--a2:<?= $h($renk2) ?>}.g-sayfa{grid-template-columns:1fr;justify-items:center}.g-kart{width:min(560px,100%)}code,textarea{background:var(--g-girdi);padding:2px 6px;border-radius:6px}.g-tam{width:100%}.kg{list-style:none;padding:0;margin:0 0 14px;font-size:.88rem}.kg li{padding:4px 0;display:flex;gap:8px}.ok{color:#22c55e}.kt{color:#ef4444}.ua{color:#f59e0b}.ayrac{margin:18px 0 8px;font-weight:700;opacity:.85}textarea{width:100%;min-height:150px;color:inherit;border:1px solid var(--g-sinir,#444);font-family:monospace}.g-satir{display:grid;grid-template-columns:1fr 150px;gap:10px}.g-satir input[type=number]{width:100%;text-align:left;padding-left:14px;-moz-appearance:textfield}.dk{position:relative;display:flex;align-items:center;gap:14px;padding:18px 16px;border:2px dashed rgba(99,102,241,.45);border-radius:16px;background:rgba(99,102,241,.06);cursor:pointer;transition:.2s}.dk:hover,.dk.uz{border-color:var(--a1);background:rgba(99,102,241,.12);transform:translateY(-1px)}.dk.dolu{border-style:solid;border-color:var(--a1);background:rgba(16,185,129,.10)}.dk input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}.dk-ik{flex:none;width:46px;height:46px;border-radius:14px;display:grid;place-items:center;font-size:1.3rem;color:#fff;background:linear-gradient(135deg,var(--a1),var(--a2));box-shadow:0 6px 16px rgba(99,102,241,.3)}.dk-y{display:grid;gap:2px;min-width:0}.dk-y b{font-size:.92rem}.dk-y small{opacity:.7;font-size:.78rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}</style></head>
<body class="g-govde"><canvas id="g-efekt"></canvas><div class="g-sis"></div>
<main class="g-sayfa"><div class="g-kart-kap g-tam"><div class="g-kart" id="g-kart">
<div class="g-kart-ust"><h2><i class="fa-solid fa-wand-magic-sparkles"></i> Kurulum</h2><p><?= $h($siteAdi) ?> — <?= $kilitli ? 'kurulum tamamlandı' : 'Adım ' . $adim . ' / 2' ?></p></div>
<?php if ($hata): ?><div class="g-hata"><?= $h($hata) ?></div><?php endif; ?>
<?php if ($tani): ?><div style="margin:12px 0;font-size:.82rem"><b>Tanı — hata nerede?</b><table style="width:100%;border-collapse:collapse;margin-top:6px"><?php foreach ($tani[0] as $t): ?><tr style="border-top:1px solid rgba(128,128,128,.25)"><td style="padding:5px 6px 5px 0;vertical-align:top;white-space:nowrap"><i class="fa-solid <?= $t[1] === 'ok' ? 'fa-circle-check ok' : 'fa-circle-xmark kt' ?>"></i></td><td style="padding:5px 6px"><b><?= $t[0] ?></b><br><span style="opacity:.85"><?= $t[2] ?></span></td></tr><?php endforeach; ?></table><p style="margin:8px 0 2px"><b><?= $h($tani[1]) ?></b></p><small style="opacity:.6"><?= $h($tani[2]) ?></small></div><?php endif; ?>
<?php if ($mesaj): ?><div class="g-bilgi"><?= $h($mesaj) ?></div><?php endif; ?>
<?php if ($kilitli): ?>
    <div class="g-bilgi"><i class="fa-solid fa-circle-check"></i><span><?= $bitti ? 'Kurulum tamamlandı.' : 'Kurulum zaten tamamlanmış.' ?> Sihirbaz kilitlendi (<code>config/kurulum.kilit</code>). Güvenlik için <code>kurulum.php</code> dosyasını silebilirsiniz.</span></div>
    <?php if (is_file($KOK . '/config/kurum.id')): ?><a class="g-buton" style="text-decoration:none" href="giris.php">Kurum girişine git <i class="fa-solid fa-arrow-right"></i></a><?php else: ?><a class="g-buton" style="text-decoration:none" href="platform.php">Platform paneline git <i class="fa-solid fa-arrow-right"></i></a><?php endif; ?>
<?php elseif ($dbYaz !== null): ?>
    <div class="g-uyari"><i class="fa-solid fa-triangle-exclamation"></i><span><code>config/db.php</code> yazılamadı. Aşağıdaki içeriği bu dosyaya kendiniz yapıştırıp sayfayı yenileyin:</span></div><textarea readonly onclick="this.select()"><?= $h($dbYaz) ?></textarea>
<?php elseif ($adim === 1): ?>
    <ul class="kg"><?php foreach ($gerek as $g): ?><li><?= $g[1] ? '<i class="fa-solid fa-circle-check ok"></i>' : ($g[2] ? '<i class="fa-solid fa-circle-xmark kt"></i>' : '<i class="fa-solid fa-triangle-exclamation ua"></i>') ?> <?= $h($g[0]) ?><?= (!$g[1] && !$g[2]) ? ' <small>(önerilir)</small>' : '' ?></li><?php endforeach; ?></ul>
    <?php if (!$gerekTamam): ?><div class="g-uyari">Kırmızı maddeleri giderin ve sayfayı yenileyin.</div><?php else: ?>
    <form method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['kur_csrf']) ?>"><input type="hidden" name="islem" value="db">
        <div class="g-satir"><div class="g-alan"><label class="g-etiket">Sunucu</label><div class="g-girdi"><i class="fa-solid fa-server"></i><input type="text" name="host" value="<?= $post('host', 'localhost') ?>" required></div></div><div class="g-alan"><label class="g-etiket">Port</label><div class="g-girdi"><input type="number" name="port" value="<?= $post('port', 3306) ?>"></div></div></div>
        <div class="g-alan"><label class="g-etiket">Veritabanı adı</label><div class="g-girdi"><i class="fa-solid fa-database"></i><input type="text" name="veritabani" value="<?= $post('veritabani') ?>" required></div></div>
        <div class="g-alan"><label class="g-etiket">Veritabanı kullanıcısı</label><div class="g-girdi"><i class="fa-regular fa-user"></i><input type="text" name="kullanici" value="<?= $post('kullanici') ?>" required autocapitalize="none"></div></div>
        <div class="g-alan"><label class="g-etiket">Veritabanı şifresi</label><div class="g-girdi"><i class="fa-solid fa-lock"></i><input type="text" name="sifre" id="db-sifre" autocomplete="off" autocapitalize="none" autocorrect="off" spellcheck="false" data-lpignore="true" data-1p-ignore style="-webkit-text-security:disc;text-security:disc"></div></div>
        <label style="display:flex;gap:8px;font-size:.85rem;margin:-6px 0 8px"><input type="checkbox" onclick="var i=document.getElementById('db-sifre');i.style.webkitTextSecurity=i.style.textSecurity=this.checked?'none':'disc'"> Şifreyi göster (doğru yazdığınızı görün)</label>
        <label style="display:flex;gap:8px;font-size:.85rem;margin:6px 0 14px"><input type="checkbox" name="olustur" value="1"<?= !empty($_POST["olustur"]) ? " checked" : "" ?>> Veritabanı yoksa oluşturmayı dene</label>
        <button class="g-buton" type="submit">Bağlantıyı dene ve kaydet <i class="fa-solid fa-arrow-right"></i></button>
    </form><?php endif; ?>
<?php else: ?>
    <div class="g-bilgi"><i class="fa-solid fa-circle-check"></i><span>Veritabanı bağlantısı çalışıyor, platform tabloları hazır.<?= !empty($_SESSION['kur_not']) ? '<br><b>' . $h($_SESSION['kur_not']) . '</b>' : '' ?></span></div>
    <?php if ($kp): ?>
    <div class="ayrac"><i class="fa-solid fa-building"></i> Kurum paketi hazır</div>
    <div class="g-bilgi"><i class="fa-solid fa-box-open"></i><span><b><?= $h($kp['ad'] ?? '') ?></b> kurumunun verileri bu pakette. Veritabanı bağlantısı tamam; tek tıkla kurun. Platform yöneticisi oluşturmanız gerekmez.</span></div>
    <form method="post"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['kur_csrf']) ?>"><input type="hidden" name="islem" value="kurum_paketi"><button class="g-buton" type="submit">Kurumu kur <i class="fa-solid fa-arrow-right"></i></button></form>
    <?php else: ?>
    <div class="ayrac"><i class="fa-solid fa-sparkles"></i> A) Yeni kurulum</div>
    <form method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['kur_csrf']) ?>"><input type="hidden" name="islem" value="yeni">
        <div class="g-alan"><label class="g-etiket">Site / ürün adı</label><div class="g-girdi"><i class="fa-solid fa-signature"></i><input type="text" name="site_adi" value="<?= $post('site_adi', $siteAdi) ?>"></div></div>
        <div class="g-alan"><label class="g-etiket">Ad soyad</label><div class="g-girdi"><i class="fa-regular fa-user"></i><input type="text" name="ad_soyad" value="<?= $post('ad_soyad') ?>" required></div></div>
        <div class="g-alan"><label class="g-etiket">Platform kullanıcı adı</label><div class="g-girdi"><i class="fa-solid fa-at"></i><input type="text" name="kullanici_adi" value="<?= $post('kullanici_adi') ?>" autocapitalize="none" required></div></div>
        <div class="g-alan"><label class="g-etiket">Şifre (en az 10 karakter, harf + rakam)</label><div class="g-girdi"><i class="fa-solid fa-lock"></i><input type="password" name="sifre" autocomplete="new-password" required></div></div>
        <div class="g-alan"><label class="g-etiket">Şifre (tekrar)</label><div class="g-girdi"><i class="fa-solid fa-lock"></i><input type="password" name="sifre2" autocomplete="new-password" required></div></div>
        <button class="g-buton" type="submit">Kurulumu tamamla <i class="fa-solid fa-arrow-right"></i></button>
    </form>
    <div class="ayrac"><i class="fa-solid fa-box-archive"></i> B) Başka sunucudan taşı (yedekten geri yükle)</div>
    <form method="post" enctype="multipart/form-data"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['kur_csrf']) ?>"><input type="hidden" name="islem" value="yedek">
        <div class="g-alan"><label class="g-etiket">Eski sunucuda Platform → Yedek ve güncelleme bölümünden indirdiğiniz .zip</label><label class="dk" id="dk"><input type="file" name="yedek" accept=".zip,application/zip" required><span class="dk-ik"><i class="fa-solid fa-cloud-arrow-up"></i></span><span class="dk-y"><b>Yedek dosyasını seçin veya buraya sürükleyin</b><small id="dk-ad">.zip — henüz dosya seçilmedi</small></span></label></div>
        <button class="g-buton" type="submit">Yedeği yükle <i class="fa-solid fa-upload"></i></button>
    </form>
    <?php endif; ?>
<?php endif; ?>
</div></div></main>
<script>(function(){var k=document.getElementById('dk');if(!k)return;var i=k.querySelector('input'),a=document.getElementById('dk-ad'),b=k.querySelector('b'),ik=k.querySelector('.dk-ik i');function g(){var f=i.files[0];if(f){var m=f.size>1048576?(f.size/1048576).toFixed(1)+' MB':Math.max(1,Math.round(f.size/1024))+' KB';b.textContent=f.name;a.textContent=m+' · yüklemeye hazır';k.classList.add('dolu');ik.className='fa-solid fa-file-zipper';}}i.addEventListener('change',g);['dragenter','dragover'].forEach(function(e){k.addEventListener(e,function(){k.classList.add('uz');});});['dragleave','drop'].forEach(function(e){k.addEventListener(e,function(){k.classList.remove('uz');});});})();</script>
<script>(function(){var i=document.getElementById('db-sifre');if(i){i.value='';setTimeout(function(){i.value='';},300);}})();</script><script>window.GIRIS = { efekt: 'aurora', renk1: <?= json_encode($renk) ?>, renk2: <?= json_encode($renk2) ?> };</script><script src="assets/js/giris-efekt.js" defer></script></body></html>
