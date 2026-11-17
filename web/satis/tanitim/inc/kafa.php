<?php
// ============================================================
// inc/kafa.php — <head> (SEO, Open Graph, JSON-LD, Google etiketleri) + üst menü
// ============================================================
require_once __DIR__ . '/ortak.php';
require_once __DIR__ . '/icerik.php';

function ts_surum($yol) { $f = TS_KOK . '/' . $yol; return $yol . '?v=' . (is_file($f) ? filemtime($f) : 1); }
function ts_logo($sinif = '')
{
    return '<svg class="logo-isaret ' . h($sinif) . '" viewBox="0 0 40 40" aria-hidden="true"><rect x="7" y="7" width="26" height="26" rx="3" fill="#12B5BF"/><rect x="7" y="7" width="26" height="26" rx="3" fill="#2B4FD0" fill-opacity=".92" transform="rotate(45 20 20)"/><circle cx="20" cy="20" r="6" fill="#F4F9F8"/><circle cx="20" cy="20" r="2.4" fill="#F2573F"/></svg>';
}
function ts_json_ld(array $grafik)
{
    return '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $grafik], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
}

/**
 * $o: baslik, meta, yol ('' = ana sayfa), sss (dizi), kirinti (dizi [ad, yol]), indeks (bool)
 */
function ts_kafa(array $o)
{
    ts_oturum(); // oturum çerezi, sayfa çıktısından önce gönderilmeli (form için CSRF)
    $marka = ts_ayar('marka'); $taban = ts_taban();
    $baslik = $o['baslik'] ?? ts_ayar('baslik'); $meta = $o['meta'] ?? ts_ayar('aciklama');
    $yol = $o['yol'] ?? ''; $kanonik = ts_url($yol);
    $og = ts_ayar('og_gorsel', 'assets/img/og.png'); $ogUrl = preg_match('~^https?://~', $og) ? $og : ts_url($og);
    $indeks = ts_ayar('robots_index', '1') === '1' && ($o['indeks'] ?? true);
    $ga4 = preg_replace('/[^A-Za-z0-9\-]/', '', ts_ayar('ga4', '')); $gtm = preg_replace('/[^A-Za-z0-9\-]/', '', ts_ayar('gtm', ''));
    $bandi = ts_ayar('cerez_bandi', '1') === '1';
    $sosyal = array_values(array_filter([ts_ayar('instagram'), ts_ayar('linkedin'), ts_ayar('x'), ts_ayar('youtube'), ts_ayar('facebook')], function ($u) { return preg_match('~^https?://~i', $u); }));
    $tel = ts_ayar('telefon'); $eposta = ts_ayar('eposta');

    // —— JSON-LD
    $grafik = [];
    $org = ['@type' => 'Organization', '@id' => $taban . '/#kurum', 'name' => $marka, 'url' => $taban . '/', 'logo' => ts_url('assets/img/logo.png')];
    if ($sosyal) $org['sameAs'] = $sosyal;
    $cp = []; if ($tel !== '') $cp['telephone'] = $tel; if ($eposta !== '') $cp['email'] = $eposta;
    if ($cp) $org['contactPoint'] = array_merge(['@type' => 'ContactPoint', 'contactType' => 'sales', 'areaServed' => 'TR', 'availableLanguage' => 'Turkish'], $cp);
    $grafik[] = $org;
    $grafik[] = ['@type' => 'WebSite', '@id' => $taban . '/#site', 'url' => $taban . '/', 'name' => $marka, 'inLanguage' => 'tr-TR', 'publisher' => ['@id' => $taban . '/#kurum']];
    $ozellikListe = [];
    foreach (ts_gruplar() as $g) foreach ($g['ozellikler'] as $f) $ozellikListe[] = $f[0];
    $grafik[] = ['@type' => 'SoftwareApplication', '@id' => $taban . '/#yazilim', 'name' => $marka . ' İK Yazılımı', 'applicationCategory' => 'BusinessApplication', 'applicationSubCategory' => 'İnsan kaynakları yönetimi',
        'operatingSystem' => 'Web, Android, iOS (tarayıcı)', 'inLanguage' => 'tr-TR', 'url' => $taban . '/', 'description' => ts_ayar('aciklama'),
        'featureList' => implode(', ', $ozellikListe),
        'offers' => ['@type' => 'Offer', 'price' => '0', 'priceCurrency' => 'TRY', 'description' => ts_ayar('deneme_gun', '15') . ' gün ücretsiz deneme', 'url' => $taban . '/#deneme', 'availability' => 'https://schema.org/InStock'],
        'publisher' => ['@id' => $taban . '/#kurum']];
    if (!empty($o['sss'])) {
        $q = []; foreach ($o['sss'] as $s) $q[] = ['@type' => 'Question', 'name' => $s[0], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $s[1]]];
        $grafik[] = ['@type' => 'FAQPage', 'mainEntity' => $q];
    }
    if (!empty($o['kirinti'])) {
        $l = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Ana sayfa', 'item' => $taban . '/']]; $i = 2;
        foreach ($o['kirinti'] as $k) $l[] = ['@type' => 'ListItem', 'position' => $i++, 'name' => $k[0], 'item' => ts_url($k[1])];
        $grafik[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $l];
    }
    ?>
<!DOCTYPE html>
<html lang="tr" data-tema="acik">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= h($baslik) ?></title>
<meta name="description" content="<?= h($meta) ?>">
<?php if ($yol === '' && ts_ayar('anahtar') !== ''): ?><meta name="keywords" content="<?= h(ts_ayar('anahtar')) ?>">
<?php endif; ?>
<meta name="robots" content="<?= $indeks ? 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1' : 'noindex, nofollow' ?>">
<meta name="author" content="<?= h($marka) ?>">
<meta name="theme-color" content="#0D3F4A">
<meta name="format-detection" content="telephone=no">
<link rel="canonical" href="<?= h($kanonik) ?>">
<link rel="alternate" hreflang="tr" href="<?= h($kanonik) ?>">
<link rel="alternate" hreflang="x-default" href="<?= h($kanonik) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="tr_TR">
<meta property="og:site_name" content="<?= h($marka) ?>">
<meta property="og:title" content="<?= h($baslik) ?>">
<meta property="og:description" content="<?= h($meta) ?>">
<meta property="og:url" content="<?= h($kanonik) ?>">
<meta property="og:image" content="<?= h($ogUrl) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="<?= h($marka) ?> İK programı panelinden bir görünüm">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($baslik) ?>">
<meta name="twitter:description" content="<?= h($meta) ?>">
<meta name="twitter:image" content="<?= h($ogUrl) ?>">
<?php if (ts_ayar('gsc') !== ''): ?><meta name="google-site-verification" content="<?= h(ts_ayar('gsc')) ?>">
<?php endif; if (ts_ayar('bing') !== ''): ?><meta name="msvalidate.01" content="<?= h(ts_ayar('bing')) ?>">
<?php endif; ?>
<link rel="icon" href="assets/img/favicon.svg" type="image/svg+xml">
<link rel="icon" href="assets/img/favicon-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="assets/img/apple-touch-icon.png">
<link rel="manifest" href="manifest.webmanifest">
<script>document.documentElement.className+=" js"</script>
<link rel="preload" href="assets/fonts/bricolage-grotesque-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="assets/fonts/instrument-sans-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= h(ts_surum('assets/css/site.css')) ?>">
<?= ts_json_ld($grafik) . "\n" ?>
<?php if ($ga4 !== '' || $gtm !== ''): ?>
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}
<?php if ($bandi): ?>gtag('consent', 'default', {ad_storage:'denied', ad_user_data:'denied', ad_personalization:'denied', analytics_storage:'denied', wait_for_update: 500});
try { if (localStorage.getItem('ts_cerez') === 'evet') gtag('consent', 'update', {analytics_storage:'granted', ad_storage:'granted', ad_user_data:'granted', ad_personalization:'granted'}); } catch (e) {}
<?php endif; ?>
</script>
<?php endif; ?>
<?php if ($gtm !== ''): ?><script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= h($gtm) ?>');</script>
<?php endif; if ($ga4 !== ''): ?><script async src="https://www.googletagmanager.com/gtag/js?id=<?= h($ga4) ?>"></script>
<script>gtag('js', new Date()); gtag('config', '<?= h($ga4) ?>', {anonymize_ip: true});</script>
<?php endif; if (ts_ayar('meta_pixel') !== ''): $px = preg_replace('/\D/', '', ts_ayar('meta_pixel')); ?><script>!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';n.queue=[];t=b.createElement(e);t.async=!0;t.src='https://connect.facebook.net/en_US/fbevents.js';s=b.getElementsByTagName(e)[0];s.parentNode.insertBefore(t,s)}(window,document,'script');fbq('init','<?= h($px) ?>');fbq('track','PageView');</script>
<?php endif; ?>
<?= ts_ayar('ek_head') . "\n" ?>
</head>
<body class="<?= h($o['govde'] ?? '') ?>">
<?php if ($gtm !== ''): ?><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= h($gtm) ?>" height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<?php endif; ?>
<a class="atla" href="#icerik">İçeriğe geç</a>
<div class="ilerleme" aria-hidden="true"><i></i></div>
<header class="ust" id="ust">
  <div class="kap ust-ic">
    <a class="logo" href="<?= $yol === '' ? '#' : './' ?>" aria-label="<?= h($marka) ?> ana sayfa"><?= ts_logo() ?><span><?= h($marka) ?></span></a>
    <nav class="menu" id="menu" aria-label="Ana menü">
      <a href="<?= $yol === '' ? '' : './' ?>#ozellikler">Özellikler</a>
      <a href="<?= $yol === '' ? '' : './' ?>#nasil">Nasıl çalışır</a>
      <a href="<?= $yol === '' ? '' : './' ?>#sss">Sık sorulanlar</a>
      <?php if (ts_wa_link() !== ''): ?><a class="menu-wa" href="<?= h(ts_wa_link()) ?>" target="_blank" rel="noopener"><?= ik('whatsapp') ?>WhatsApp</a><?php endif; ?>
    </nav>
    <a class="btn btn-mercan btn-kucuk ust-btn" href="<?= $yol === '' ? '' : './' ?>#deneme" data-olay="deneme_ust"><?= h(ts_ayar('deneme_gun', '15')) ?> gün ücretsiz dene</a>
    <button class="menu-ac" type="button" aria-label="Menüyü aç" aria-expanded="false" aria-controls="menu"><?= ik('menu') ?></button>
  </div>
</header>
<main id="icerik">
<?php
}
