<?php
// konu.php — her özellik grubu için ayrı SEO sayfası (/izin-takip-programi vb.)
require_once __DIR__ . '/inc/kafa.php';
require_once __DIR__ . '/inc/ayak.php';
$gruplar = ts_gruplar(); $k = trim((string)($_GET['k'] ?? ''), '/');
if (!isset($gruplar[$k])) { http_response_code(404); ts_kafa(['yol' => $k, 'baslik' => 'Sayfa bulunamadı | ' . ts_ayar('marka'), 'indeks' => false]); ?>
<section class="bolum"><div class="kap yok"><h1>Sayfa bulunamadı</h1><p>Aradığınız sayfa yok ya da taşınmış olabilir.</p><a class="btn btn-mercan" href="./">Ana sayfaya dön</a></div></section>
<?php ts_ayak(); exit; }
$g = $gruplar[$k]; $gun = h(ts_ayar('deneme_gun', '15'));
$sss = array_merge($g['sss'], array_slice(ts_genel_sss(), 0, 3));
ts_kafa(['yol' => $k, 'baslik' => $g['baslik'], 'meta' => $g['meta'], 'sss' => $sss, 'kirinti' => [[$g['kisa'], $k]], 'govde' => 'konu']);
?>
<section class="konu-hero" style="--r:<?= h($g['renk']) ?>">
  <div class="hero-desen" aria-hidden="true"><svg viewBox="0 0 600 600"><rect width="600" height="600" fill="url(#yildiz)"/><defs><pattern id="yildiz" width="60" height="60" patternUnits="userSpaceOnUse"><path d="M30 6 L36.5 23.5 L54 30 L36.5 36.5 L30 54 L23.5 36.5 L6 30 L23.5 23.5Z" fill="none" stroke="currentColor" stroke-width="1.1"/></pattern></defs></svg></div>
  <div class="kap konu-ic">
    <nav class="kirinti" aria-label="Sayfa yolu"><a href="./">Ana sayfa</a><span>/</span><span><?= h($g['kisa']) ?></span></nav>
    <span class="oz-ikon buyuk"><?= ik($g['ikon']) ?></span>
    <h1><?= h($g['h1']) ?></h1>
    <p class="konu-giris"><?= h($g['giris']) ?></p>
    <div class="hero-dugme"><a class="btn btn-mercan btn-buyuk" href="#deneme" data-olay="deneme_konu"><span class="btn-yazi"><?= $gun ?> gün ücretsiz dene</span><?= ik('arrow-right') ?></a><?php if (ts_wa_link() !== ''): ?><a class="btn btn-hayalet-koyu btn-buyuk" href="<?= h(ts_wa_link()) ?>" target="_blank" rel="noopener"><?= ik('whatsapp') ?>WhatsApp</a><?php endif; ?></div>
  </div>
</section>

<section class="bolum konu-liste" style="--r:<?= h($g['renk']) ?>" aria-labelledby="kl-b">
  <div class="kap">
    <div class="bolum-bas" data-goster><h2 id="kl-b"><?= h($g['ana']) ?>: neler var?</h2></div>
    <dl class="oz-liste genis" data-goster>
      <?php foreach ($g['ozellikler'] as $f): ?><div><dt><?= h($f[0]) ?></dt><dd><?= h($f[1]) ?></dd></div><?php endforeach; ?>
    </dl>
  </div>
</section>

<section class="bolum diger" aria-labelledby="dg-b">
  <div class="kap">
    <div class="bolum-bas" data-goster><h2 id="dg-b">Diğer özellik başlıkları</h2></div>
    <div class="diger-liste" data-goster>
      <?php foreach ($gruplar as $slug => $d): if ($slug === $k) continue; ?>
        <a href="<?= h($slug) ?>" style="--r:<?= h($d['renk']) ?>"><?= ik($d['ikon']) ?><span><b><?= h($d['kisa']) ?></b><small><?= h($d['ana']) ?></small></span><?= ik('arrow-up-right') ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="bolum sss" id="sss" aria-labelledby="sss-b">
  <div class="kap sss-ic">
    <div class="bolum-bas" data-goster><h2 id="sss-b">Sık sorulan sorular</h2></div>
    <div class="sss-liste" data-goster>
      <?php foreach ($sss as $s): ?><details><summary><span><?= h($s[0]) ?></span><?= ik('chevron-down') ?></summary><div class="sss-cevap"><p><?= h($s[1]) ?></p></div></details><?php endforeach; ?>
    </div>
  </div>
</section>
<?php ts_talep_bolumu(); ts_ayak(); ?>
