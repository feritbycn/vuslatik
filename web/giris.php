<?php
// ============================================================
// giris.php — Kurum girişi (efektli, modern). Kendi kendine kayıt yoktur: kullanıcıları kurum yetkilisi ekler.
//   Tüm içerik (başlık, SEO, favicon, logo, efekt, özellik listesi, kurum seçimi biçimi) platform panelindeki "Site ayarları"ndan gelir.
//   Kurum seçimi: "liste" → kurumlar kart olarak seçilir · "kod" → kurum kodu yazılır. giris.php?kurum=KOD adresi kurumu önceden seçer.
// ============================================================
require_once __DIR__ . '/config/baglan.php';
if (TB_KURUM_ID > 0 && !empty($_SESSION['kullanici_id']) && !isset($_GET['degistir'])) { header('Location: ik.php'); exit; }
$a = pf_ayarlar($db);
if (!empty($a['logo']) && preg_match('~^http://~i', (string)$a['logo'])) $a['logo'] = 'https://' . substr((string)$a['logo'], 7);   // karışık içerik (http logo) mobil uygulamada engellenir
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$kurumlar = [];
$otomatik = $a['kurum_secimi'] === 'otomatik';
$kurum_liste = $a['kurum_secimi'] === 'liste';
if ($kurum_liste) {
    $q = $db->query("SELECT kod, ad, renk, logo FROM pf_kurumlar WHERE durum = 'aktif' ORDER BY ad");
    if ($q) while ($r = $q->fetch_assoc()) $kurumlar[] = $r;
}
$onsec = strtolower(trim((string)($_GET['kurum'] ?? '')));
$onsec_k = null;
$tek = TB_TEK_KURUM > 0 ? pf_kurum($db, TB_TEK_KURUM) : null;     // tek kurum paketi: kurum sabit, seçim gizli
if ($tek && is_file(__DIR__ . '/aktivasyon_ortak.php')) { require_once __DIR__ . '/aktivasyon_ortak.php'; if (akt_zorunlu() && !akt_durum()['ok']) { header('Location: aktivasyon.php'); exit; } }   // aktivasyon kodlu paket: kod girilmeden giriş sayfası açılmaz
if ($tek) { $kurum_liste = false; $otomatik = false; $kurumlar = []; $onsec = $tek['kod']; }
if ($otomatik && $onsec === '') $kurumlar = [];
if ($onsec !== '') { $onsec_k = pf_kurum_kod($db, $onsec); if (!$onsec_k || $onsec_k['durum'] !== 'aktif') { $onsec_k = null; $onsec = ''; } }
// "İmzayı göster" kapalı kurum (kurum seçili / tek kurum paketi): sekme başlığı, logo yazısı ve ilk ekranda platform adı yerine KURUM ADI görünür
if ($onsec_k && array_key_exists('imza_goster', $onsec_k) && intval($onsec_k['imza_goster']) === 0 && trim((string)$onsec_k['ad']) !== '') { $a['site_adi'] = $onsec_k['ad']; $a['seo_baslik'] = $onsec_k['ad']; if (!empty($onsec_k['logo'])) $a['logo'] = $onsec_k['logo']; }
$don = preg_match('/^[a-z0-9_]+\.php$/', (string)($_GET['don'] ?? '')) ? $_GET['don'] : '';
$ana = preg_match('/^#[0-9a-fA-F]{6}$/', $a['tema_renk']) ? $a['tema_renk'] : '#6366f1';
$ikinci = preg_match('/^#[0-9a-fA-F]{6}$/', $a['giris_renk2']) ? $a['giris_renk2'] : '#06b6d4';
$efekt = in_array($a['giris_efekt'], ['aurora', 'parcacik', 'dalga', 'izgara', 'yok'], true) ? $a['giris_efekt'] : 'aurora';
$ozellikler = array_values(array_filter(array_map('trim', preg_split('/\R/u', $a['giris_ozellik']))));
$fav = $a['favicon'] !== '' ? $a['favicon'] : 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="' . $ana . '"/><circle cx="32" cy="24" r="9" fill="#fff"/><path d="M14 52c2-11 10-16 18-16s16 5 18 16" fill="#fff"/></svg>');
$baslik = $a['seo_baslik'] !== '' ? $a['seo_baslik'] : $a['site_adi'];
$kanon = $a['seo_canonical'] !== '' ? $a['seo_canonical'] : ($a['site_url'] !== '' ? rtrim($a['site_url'], '/') . '/giris.php' : '');
$og = $a['og_gorsel'] !== '' ? (preg_match('~^https?://~i', $a['og_gorsel']) ? $a['og_gorsel'] : ($a['site_url'] !== '' ? rtrim($a['site_url'], '/') . '/' . ltrim($a['og_gorsel'], '/') : $a['og_gorsel'])) : '';
$gtag = preg_match('/^(G|GT|UA|AW)-[A-Z0-9-]{4,20}$/i', $a['analitik_kod']) ? $a['analitik_kod'] : '';
$bakim = $a['bakim_modu'] === '1';
$v = function ($y) { $t = @filemtime(__DIR__ . '/' . $y); return $t ?: 1; };
$ver = ['efekt' => $efekt, 'renk1' => $ana, 'renk2' => $ikinci, 'liste' => $kurum_liste, 'otomatik' => $otomatik && $onsec === '', 'kurumlar' => $kurumlar, 'onsec' => $onsec, 'don' => $don, 'csrf' => csrf_token(), 'bakim' => $bakim];
$ver['intro'] = $a['giris_intro'] === '1' && !$bakim && stripos((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 'Bilge360App') === false; $ver['ad'] = $a['site_adi']; $ver['logo'] = $a['logo']; $ver['slogan'] = $a['slogan'];
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= $h($baslik) ?></title>
<meta name="description" content="<?= $h($a['seo_aciklama']) ?>">
<?php if ($a['seo_anahtar'] !== ''): ?><meta name="keywords" content="<?= $h($a['seo_anahtar']) ?>"><?php endif; ?>
<meta name="robots" content="<?= $h($a['seo_robots']) ?>">
<?php if ($a['seo_yazar'] !== ''): ?><meta name="author" content="<?= $h($a['seo_yazar']) ?>"><?php endif; ?>
<?php if ($kanon !== ''): ?><link rel="canonical" href="<?= $h($kanon) ?>"><?php endif; ?>
<meta name="theme-color" content="<?= $h($ana) ?>">
<link rel="icon" href="<?= $h($fav) ?>">
<link rel="apple-touch-icon" href="<?= $h($fav) ?>">
<meta property="og:type" content="website"><meta property="og:title" content="<?= $h($baslik) ?>"><meta property="og:description" content="<?= $h($a['seo_aciklama']) ?>"><meta property="og:site_name" content="<?= $h($a['site_adi']) ?>"><meta property="og:locale" content="tr_TR">
<?php if ($kanon !== ''): ?><meta property="og:url" content="<?= $h($kanon) ?>"><?php endif; ?>
<?php if ($og !== ''): ?><meta property="og:image" content="<?= $h($og) ?>"><meta name="twitter:image" content="<?= $h($og) ?>"><?php endif; ?>
<meta name="twitter:card" content="<?= $og !== '' ? 'summary_large_image' : 'summary' ?>"><meta name="twitter:title" content="<?= $h($baslik) ?>"><meta name="twitter:description" content="<?= $h($a['seo_aciklama']) ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="assets/css/giris.css?v=<?= $v('assets/css/giris.css') ?>">
<style>:root{--a1:<?= $h($ana) ?>;--a2:<?= $h($ikinci) ?>}</style>
<script>(function(){try{var t=localStorage.getItem('vk_tema');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-tema',t)}catch(e){}})();</script>
<?php if ($gtag !== ''): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= $h($gtag) ?>"></script><script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}gtag('js',new Date());gtag('config',<?= json_encode($gtag) ?>);</script><?php endif; ?>
<?= $a['ek_head'] /* platform yöneticisi tarafından girilen güvenilir <head> kodu */ ?>
<script src="assets/js/tb-url.js"></script></head>
<body class="g-govde">
<canvas id="g-efekt" aria-hidden="true"></canvas>
<div class="g-sis" aria-hidden="true"></div>

<main class="g-sayfa">
    <section class="g-hero" aria-label="<?= $h($a['site_adi']) ?>">
        <div class="g-marka">
            <?php if ($a['logo'] !== ''): ?><img class="g-logo-img" src="<?= $h($a['logo']) ?>" alt="<?= $h($a['site_adi']) ?>"><?php else: ?><span class="g-logo"><i class="fa-solid fa-people-group"></i></span><?php endif; ?>
            <b><?= $h($a['site_adi']) ?></b>
        </div>
        <h1 class="g-slogan"><?= $h($a['slogan']) ?></h1>
        <?php if ($ozellikler): ?>
        <ul class="g-ozellik">
            <?php foreach ($ozellikler as $i => $o): ?><li style="--i:<?= $i ?>"><i class="fa-solid fa-circle-check"></i><span><?= $h($o) ?></span></li><?php endforeach; ?>
        </ul>
        <?php endif; ?>
        <div class="g-alt-yazi">Tüm hakları saklıdır, <b><?= $h(pf_imza_adi($db, $onsec_k)) ?></b><?php if ($a['destek_mail'] !== ''): ?> · <a href="mailto:<?= $h($a['destek_mail']) ?>"><?= $h($a['destek_mail']) ?></a><?php endif; ?></div>
    </section>

    <section class="g-kart-kap">
        <div class="g-kart" id="g-kart">
            <button type="button" class="g-tema" id="g-tema" aria-label="Temayı değiştir" title="Açık / koyu tema"><i class="fa-solid fa-circle-half-stroke"></i></button>
            <div class="g-kart-ust">
                <h2><?= $h($a['giris_baslik']) ?></h2>
                <p><?= $h($a['giris_alt']) ?></p>
            </div>

            <?php if ($bakim): ?><div class="g-uyari"><i class="fa-solid fa-screwdriver-wrench"></i> <?= $h($a['bakim_mesaj']) ?></div><?php endif; ?>

            <form id="g-form" autocomplete="on" novalidate>
                <input type="hidden" name="don" value="<?= $h($don) ?>">
                <div class="g-adim" id="g-adim-giris">
                    <div class="g-alan" id="g-kurum-kap"<?= (!empty($tek) || ($otomatik && $onsec === '')) ? ' hidden style="display:none"' : '' ?>>
                        <label class="g-etiket" for="g-kurum-ara"><i class="fa-solid fa-building"></i> Kurum</label>
                        <?php if ($kurum_liste): ?>
                            <div class="g-secili" id="g-secili" hidden>
                                <span class="g-av" id="g-secili-av"></span><span class="g-secili-ad" id="g-secili-ad"></span>
                                <button type="button" class="g-degistir" id="g-degistir">Değiştir</button>
                            </div>
                            <div class="g-secim" id="g-secim">
                                <?php if (count($kurumlar) > 5): ?><div class="g-ara"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="g-kurum-ara" placeholder="Kurum ara…" autocomplete="off"></div><?php endif; ?>
                                <div class="g-liste" id="g-liste" role="listbox" aria-label="Kurumlar"></div>
                                <?php if (!$kurumlar): ?><div class="g-bos">Henüz tanımlı kurum yok.</div><?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="g-girdi"><i class="fa-solid fa-hashtag"></i><input type="text" id="g-kurum-ara" name="kurum" placeholder="Kurum kodu" autocomplete="organization" autocapitalize="none" spellcheck="false" value="<?= $h($onsec) ?>"></div>
                        <?php endif; ?>
                        <input type="hidden" name="kurum_sec" id="g-kurum-deger" value="<?= $h($onsec) ?>">
                    </div>

                    <div class="g-alan">
                        <label class="g-etiket" for="g-kullanici"><i class="fa-solid fa-user"></i> Kullanıcı adı</label>
                        <div class="g-girdi"><i class="fa-regular fa-user"></i><input type="text" id="g-kullanici" name="kullanici" placeholder="kullaniciadi veya e-posta" autocomplete="username" autocapitalize="none" spellcheck="false" required></div>
                    </div>
                    <div class="g-alan">
                        <label class="g-etiket" for="g-sifre"><i class="fa-solid fa-lock"></i> Şifre</label>
                        <div class="g-girdi"><i class="fa-solid fa-key"></i><input type="password" id="g-sifre" name="sifre" placeholder="••••••••" autocomplete="current-password" required>
                            <button type="button" class="g-goz" id="g-goz" aria-label="Şifreyi göster / gizle"><i class="fa-regular fa-eye"></i></button></div>
                        <div class="g-ipucu" id="g-caps" hidden><i class="fa-solid fa-triangle-exclamation"></i> Caps Lock açık</div>
                    </div>
                    <label class="g-hatirla"><input type="checkbox" id="g-hatirla" checked> <span>Beni hatırla <small>(bu cihazda oturumum açık kalsın)</small></span></label>
                    <div class="g-hata" id="g-hata" role="alert" hidden></div>
                    <button type="submit" class="g-buton" id="g-gonder"><span class="g-buton-yazi">Giriş yap</span><i class="fa-solid fa-arrow-right"></i><span class="g-bekle"></span></button>
                    <p class="g-not"><i class="fa-solid fa-shield-halved"></i> Hesap oluşturma kapalıdır. Hesabınızı kurum yöneticiniz tanımlar.</p>
                </div>

                <div class="g-adim" id="g-adim-sifre" hidden>
                    <div class="g-bilgi"><i class="fa-solid fa-key"></i> Geçici şifre ile girdiniz. Devam etmeden önce kendi şifrenizi belirleyin (en az 8 karakter, harf ve rakam).</div>
                    <div class="g-alan"><label class="g-etiket" for="g-yeni">Yeni şifre</label><div class="g-girdi"><i class="fa-solid fa-lock"></i><input type="password" id="g-yeni" autocomplete="new-password"></div></div>
                    <div class="g-alan"><label class="g-etiket" for="g-yeni2">Yeni şifre (tekrar)</label><div class="g-girdi"><i class="fa-solid fa-lock"></i><input type="password" id="g-yeni2" autocomplete="new-password"></div></div>
                    <div class="g-hata" id="g-hata2" role="alert" hidden></div>
                    <button type="button" class="g-buton" id="g-sifre-kaydet"><span class="g-buton-yazi">Şifreyi kaydet ve gir</span><i class="fa-solid fa-arrow-right"></i><span class="g-bekle"></span></button>
                </div>
            </form>
        </div>
    </section>
</main>
<script>window.GIRIS = <?= json_encode($ver, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script src="assets/js/giris-intro.js?v=<?= $v('assets/js/giris-intro.js') ?>" defer></script>
<script src="assets/js/giris-efekt.js?v=<?= $v('assets/js/giris-efekt.js') ?>" defer></script>
<script src="assets/js/giris.js?v=<?= $v('assets/js/giris.js') ?>" defer></script>
</body>
</html>
