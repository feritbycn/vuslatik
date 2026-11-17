<?php
// ============================================================
// platform.php — Platform paneli (ürün sahibi): kurumlar, kurum yetkilisi atama, site/SEO/favicon/giriş sayfası ayarları, yöneticiler, kayıtlar
// Giriş yapılmamışsa kendi giriş formunu gösterir. Kurum girişi giris.php'dedir.
// ============================================================
require_once __DIR__ . '/config/baglan.php';
$db->kurum = 0;
$a = pf_ayarlar($db);
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$v = function ($y) { $t = @filemtime(__DIR__ . '/' . $y); return $t ?: 1; };
$ana = preg_match('/^#[0-9a-fA-F]{6}$/', $a['tema_renk']) ? $a['tema_renk'] : '#6366f1';
$fav = $a['favicon'] !== '' ? $a['favicon'] : 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="' . $ana . '"/><path d="M32 12l18 8v12c0 11-8 18-18 22C22 50 14 43 14 32V20z" fill="#fff"/></svg>');
if (TB_TEK_KURUM > 0) { header('Location: giris.php'); exit; }   // tek kurum paketinde platform paneli yoktur
$kurulu = $db->query("SELECT COUNT(*) FROM pf_yoneticiler")->fetch_row();
$kurulu = intval($kurulu[0] ?? 0) > 0;
$giris = !empty($_SESSION['pf_yonetici_id']);
if ($giris) { $q = $db->query("SELECT aktif FROM pf_yoneticiler WHERE id = " . intval($_SESSION['pf_yonetici_id'])); $r = $q ? $q->fetch_row() : null; if (!$r || intval($r[0]) !== 1) { $giris = false; unset($_SESSION['pf_yonetici_id']); } }
?><!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover"><meta name="robots" content="noindex,nofollow">
<title>Platform paneli — <?= $h($a['site_adi']) ?></title><link rel="icon" href="<?= $h($fav) ?>"><meta name="theme-color" content="<?= $h($ana) ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<script>(function(){try{var t=localStorage.getItem('vk_tema');if(t==='light'||t==='dark')document.documentElement.setAttribute('data-tema',t)}catch(e){}})();</script>
<?php if (!$giris): ?>
<link rel="stylesheet" href="assets/css/giris.css?v=<?= $v('assets/css/giris.css') ?>"><style>:root{--a1:<?= $h($ana) ?>;--a2:<?= $h($a['giris_renk2']) ?>}.g-sayfa{grid-template-columns:1fr;justify-items:center}.g-kart{width:min(440px,100%)}</style>
<script src="assets/js/tb-url.js"></script><script src="assets/js/tb-mobil.js" defer></script></head><body class="g-govde"><canvas id="g-efekt"></canvas><div class="g-sis"></div>
<main class="g-sayfa"><div class="g-kart-kap" style="width:100%"><div class="g-kart" id="g-kart">
    <div class="g-kart-ust"><h2><i class="fa-solid fa-shield-halved"></i> Platform paneli</h2><p>Kurumları ve site ayarlarını yönetmek için giriş yapın.</p></div>
    <?php if (!$kurulu): ?><div class="g-uyari"><i class="fa-solid fa-wand-magic-sparkles"></i><span>Henüz platform yöneticisi yok. <a href="kurulum.php" style="color:inherit;font-weight:700">Kurulumu başlatın</a>.</span></div><?php endif; ?>
    <form id="g-form" novalidate>
        <div class="g-alan"><label class="g-etiket" for="p-k"><i class="fa-solid fa-user"></i> Kullanıcı adı</label><div class="g-girdi"><i class="fa-regular fa-user"></i><input id="p-k" type="text" autocomplete="username" autocapitalize="none" spellcheck="false" required></div></div>
        <div class="g-alan"><label class="g-etiket" for="p-s"><i class="fa-solid fa-lock"></i> Şifre</label><div class="g-girdi"><i class="fa-solid fa-key"></i><input id="p-s" type="password" autocomplete="current-password" required></div></div>
        <div class="g-hata" id="g-hata" role="alert" hidden></div>
        <button type="submit" class="g-buton" id="g-gonder"><span class="g-buton-yazi">Giriş yap</span><i class="fa-solid fa-arrow-right"></i><span class="g-bekle"></span></button>
        <p class="g-not"><a href="giris.php" style="color:inherit"><i class="fa-solid fa-arrow-left"></i> Kurum girişine dön</a></p>
    </form>
</div></div></main>
<script>window.GIRIS = { efekt: 'dalga', renk1: <?= json_encode($ana) ?>, renk2: <?= json_encode($a['giris_renk2']) ?> };
(function () {
    (function(){var el=document.getElementById('p-k');if(el)el.addEventListener('input',function(){var v=el.value,y=v.replace(/İ/g,'i').replace(/I/g,'i').toLowerCase().replace(/\s+/g,'');if(y!==v)el.value=y;});})();
    var csrf = <?= json_encode(csrf_token()) ?>, f = document.getElementById('g-form'), hata = document.getElementById('g-hata'), b = document.getElementById('g-gonder');
    f.addEventListener('submit', function (e) {
        e.preventDefault(); hata.hidden = true; b.classList.add('yukleniyor');
        var fd = new FormData(); fd.append('islem', 'giris'); fd.append('kullanici', document.getElementById('p-k').value); fd.append('sifre', document.getElementById('p-s').value); fd.append('_csrf', csrf);
        fetch('platform_islem.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf } }).then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, hata: 'Sunucu yanıtı okunamadı (HTTP ' + r.status + (r.redirected ? ', yönlendirildi: ' + r.url : '') + '): ' + t.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220) }; } }); }).then(function (j) {
            if (j.ok) { location.reload(); return; }
            b.classList.remove('yukleniyor'); hata.textContent = j.hata || 'Giriş yapılamadı.'; hata.hidden = false;
            var k = document.getElementById('g-kart'); k.classList.remove('sars'); void k.offsetWidth; k.classList.add('sars');
        }).catch(function (e) { b.classList.remove('yukleniyor'); hata.textContent = 'Sunucuya ulaşılamadı (' + (e && e.message ? e.message : 'ağ hatası') + '). Adres: platform_islem.php'; hata.hidden = false; });
    });
})();</script>
<script src="assets/js/giris-efekt.js?v=<?= $v('assets/js/giris-efekt.js') ?>" defer></script>
</body></html>
<?php exit; endif; ?>
<link rel="stylesheet" href="assets/css/tb-ikm.css?v=<?= $v('assets/css/tb-ikm.css') ?>">
<link rel="stylesheet" href="assets/css/platform.css?v=<?= $v('assets/css/platform.css') ?>">
<style>:root{--accent:<?= $h($ana) ?>;--accent-glow:<?= $h($ana) ?>29}</style>
<script src="assets/js/tb-url.js"></script><script src="assets/js/tb-mobil.js" defer></script></head>
<body>
<div class="ikm" id="pf-kok">
    <div class="ikm-perde" id="ikm-perde"></div>
    <aside class="ikm-yan" id="ikm-yan" aria-label="Platform menüsü">
        <div class="ikm-marka">
            <?php if ($a['logo'] !== ''): ?><img class="pf-logo" src="<?= $h($a['logo']) ?>" alt=""><?php else: ?><span class="ikm-logo" style="background:linear-gradient(135deg,var(--ik-vurgu),var(--ik-vurgu2))"><i class="fa-solid fa-shield-halved"></i></span><?php endif; ?>
            <div><b>Platform</b><small><?= $h($a['site_adi']) ?></small></div>
            <button type="button" class="ikm-yan-kapat" id="ikm-yan-kapat" aria-label="Menüyü kapat"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <nav class="ikm-nav-liste" id="pf-menu">
            <div class="ikm-nav-baslik">Yönetim</div>
            <button type="button" class="ikm-nav" data-s="ozet" style="--ni:#6366f1"><span class="ikm-nav-ikon"><i class="fa-solid fa-gauge-high"></i></span><span class="ikm-nav-ad">Genel bakış</span></button>
            <button type="button" class="ikm-nav" data-s="kurumlar" style="--ni:#10b981"><span class="ikm-nav-ikon"><i class="fa-solid fa-building"></i></span><span class="ikm-nav-ad">Kurumlar</span></button>
            <button type="button" class="ikm-nav" data-s="paketler" style="--ni:#8b5cf6"><span class="ikm-nav-ikon"><i class="fa-solid fa-cube"></i></span><span class="ikm-nav-ad">Paketler</span></button>
            <button type="button" class="ikm-nav" data-s="lisansmesaj" style="--ni:#f97316"><span class="ikm-nav-ikon"><i class="fa-solid fa-bell"></i></span><span class="ikm-nav-ad">Süre mesajları</span></button>
            <div class="ikm-nav-baslik">Site</div>
            <button type="button" class="ikm-nav" data-s="ayar-genel" style="--ni:#3b82f6"><span class="ikm-nav-ikon"><i class="fa-solid fa-sliders"></i></span><span class="ikm-nav-ad">Genel ayarlar</span></button>
            <button type="button" class="ikm-nav" data-s="ayar-gorsel" style="--ni:#ec4899"><span class="ikm-nav-ikon"><i class="fa-solid fa-image"></i></span><span class="ikm-nav-ad">Logo ve favicon</span></button>
            <button type="button" class="ikm-nav" data-s="ayar-seo" style="--ni:#f59e0b"><span class="ikm-nav-ikon"><i class="fa-solid fa-magnifying-glass-chart"></i></span><span class="ikm-nav-ad">SEO</span></button>
            <button type="button" class="ikm-nav" data-s="ayar-giris" style="--ni:#8b5cf6"><span class="ikm-nav-ikon"><i class="fa-solid fa-wand-magic-sparkles"></i></span><span class="ikm-nav-ad">Giriş sayfası</span></button>
            <button type="button" class="ikm-nav" data-s="ayar-sistem" style="--ni:#64748b"><span class="ikm-nav-ikon"><i class="fa-solid fa-gear"></i></span><span class="ikm-nav-ad">Sistem</span></button>
            <div class="ikm-nav-baslik">Bakım</div>
            <button type="button" class="ikm-nav" data-s="bakim" style="--ni:#14b8a6"><span class="ikm-nav-ikon"><i class="fa-solid fa-cloud-arrow-up"></i></span><span class="ikm-nav-ad">Yedek ve güncelleme</span></button>
            <button type="button" class="ikm-nav" data-s="yedekler" style="--ni:#0ea5e9"><span class="ikm-nav-ikon"><i class="fa-solid fa-box-archive"></i></span><span class="ikm-nav-ad">Kurum yedekleri</span><b class="ikm-rozet" id="pf-r-yedek" hidden></b></button>
            <div class="ikm-nav-baslik">Güvenlik</div>
            <button type="button" class="ikm-nav" data-s="yoneticiler" style="--ni:#ef4444"><span class="ikm-nav-ikon"><i class="fa-solid fa-user-shield"></i></span><span class="ikm-nav-ad">Platform yöneticileri</span></button>
            <button type="button" class="ikm-nav" data-s="kayitlar" style="--ni:#06b6d4"><span class="ikm-nav-ikon"><i class="fa-solid fa-clipboard-list"></i></span><span class="ikm-nav-ad">İşlem kayıtları</span></button>
        </nav>
        <div class="ikm-yan-alt">
            <a class="ikm-giris-btn" href="giris.php?degistir=1" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i><span><b>Giriş sayfasını gör</b><small>Yeni sekmede açılır</small></span><i class="fa-solid fa-arrow-up-right-from-square"></i></a>
            <div class="ikm-ben">
                <span class="ikm-av" style="background:<?= $h($ana) ?>"><?= $h(mb_strtoupper(mb_substr($_SESSION['pf_yonetici_ad'] ?? '?', 0, 1))) ?></span>
                <div><b><?= $h($_SESSION['pf_yonetici_ad'] ?? '') ?></b><small>Platform yöneticisi</small></div>
                <button type="button" class="ikm-ikon-btn" id="pf-tema" title="Açık / koyu tema" aria-label="Tema"><i class="fa-solid fa-circle-half-stroke"></i></button>
                <button type="button" class="ikm-ikon-btn" id="pf-cikis" title="Çıkış" aria-label="Çıkış"><i class="fa-solid fa-right-from-bracket"></i></button>
            </div>
        </div>
    </aside>
    <div class="ikm-ana">
        <header class="ikm-ust">
            <button type="button" class="ikm-ikon-btn ikm-menu-ac" id="ikm-menu-ac" aria-label="Menüyü aç"><i class="fa-solid fa-bars"></i></button>
            <div class="ikm-ust-baslik"><h1 id="pf-baslik">Genel bakış</h1><p id="pf-alt"></p></div>
            <div class="ikm-ust-sag" id="pf-ust-sag"></div>
        </header>
        <main class="ikm-icerik" id="pf-icerik"><div class="ikm-iskelet"><i></i><i></i><i></i></div></main>
        <footer class="tb-altbilgi">Tüm hakları saklıdır, <b><?= htmlspecialchars($a['site_adi'], ENT_QUOTES, 'UTF-8') ?></b></footer>
    </div>
</div>
<script>window.TB = { csrf: <?= json_encode(csrf_token()) ?> }; window.PF = { ben: <?= json_encode($_SESSION['pf_yonetici_ad'] ?? '') ?> };</script>
<script src="assets/js/tb-yedek.js?v=<?= $v('assets/js/tb-yedek.js') ?>" defer></script>
<script src="assets/js/platform.js?v=<?= $v('assets/js/platform.js') ?>" defer></script>
<script src="assets/js/platform-paket.js?v=<?= $v('assets/js/platform-paket.js') ?>" defer></script>
</body></html>
