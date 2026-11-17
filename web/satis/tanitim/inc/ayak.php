<?php
// ============================================================
// inc/ayak.php — deneme talebi bölümü, alt bilgi, WhatsApp düğmesi, çerez bandı
// ============================================================
function ts_talep_bolumu()
{
    $gun = h(ts_ayar('deneme_gun', '15')); $wa = ts_wa_link();
    $zaman = time();
    $imza = hash_hmac('sha256', (string)$zaman, ts_csrf());
    ?>
<section class="deneme" id="deneme" aria-labelledby="deneme-b">
  <div class="deneme-desen" aria-hidden="true"></div>
  <div class="kap deneme-ic">
    <div class="deneme-sol" data-goster>
      <h2 id="deneme-b"><?= $gun ?> gün boyunca ücretsiz deneyin</h2>
      <p class="deneme-giris">Formu doldurun, sizi arayalım ve deneme alanınızı hazırlayalım. Kredi kartı gerekmez, taahhüt yoktur.</p>
      <ul class="tikli">
        <li><?= ik('check') ?>Tüm modüller açık: izin, mesai, puantaj, giriş-çıkış, özlük, bildirim</li>
        <li><?= ik('check') ?>Personel listenizi Excel’den aktarabilirsiniz</li>
        <li><?= ik('check') ?>Deneme boyunca sorularınıza yanıt veririz</li>
      </ul>
      <?php if ($wa !== ''): ?>
      <a class="btn btn-wa" href="<?= h($wa) ?>" target="_blank" rel="noopener" data-olay="whatsapp_form"><?= ik('whatsapp') ?>Ya da WhatsApp’tan yazın</a>
      <?php endif; ?>
      <?php if (ts_ayar('telefon') !== ''): ?><p class="deneme-tel">Ya da arayın: <a href="tel:<?= h(preg_replace('/[^\d+]/', '', ts_ayar('telefon'))) ?>"><?= h(ts_ayar('telefon')) ?></a></p><?php endif; ?>
    </div>
    <div class="form-kutu" data-goster>
      <form class="talep" id="talep-formu" method="post" action="talep.php" novalidate>
        <input type="hidden" name="csrf" value="<?= h(ts_csrf()) ?>">
        <input type="hidden" name="t" value="<?= $zaman ?>"><input type="hidden" name="s" value="<?= h($imza) ?>">
        <input type="hidden" name="kaynak" value=""><input type="hidden" name="sayfa" value="">
        <div class="bal" aria-hidden="true"><label>Web sitesi <input type="text" name="web" tabindex="-1" autocomplete="off"></label></div>
        <div class="alan-2">
          <label class="alan"><span>Ad soyad</span><input type="text" name="ad" autocomplete="name" required maxlength="80"></label>
          <label class="alan"><span>Şirket adı</span><input type="text" name="firma" autocomplete="organization" required maxlength="120"></label>
        </div>
        <div class="alan-2">
          <label class="alan"><span>E-posta</span><input type="email" name="eposta" autocomplete="email" required maxlength="120"></label>
          <label class="alan"><span>Telefon</span><input type="tel" name="telefon" autocomplete="tel" inputmode="tel" required maxlength="25" placeholder="05xx xxx xx xx"></label>
        </div>
        <label class="alan"><span>Personel sayısı</span>
          <select name="personel">
            <option value="">Seçin</option><option>1–10</option><option>11–50</option><option>51–250</option><option>251–1000</option><option>1000’den fazla</option>
          </select></label>
        <label class="alan"><span>Eklemek istediğiniz not <em>(isteğe bağlı)</em></span><textarea name="mesaj" rows="3" maxlength="800" placeholder="Şu an izin ve mesaiyi nasıl takip ediyorsunuz?"></textarea></label>
        <label class="onay"><input type="checkbox" name="kvkk" value="1" required><span><a href="gizlilik" target="_blank" rel="noopener">Aydınlatma metnini</a> okudum; iletişim bilgilerimin deneme talebim için kullanılmasını kabul ediyorum.</span></label>
        <p class="form-hata" role="alert" hidden></p>
        <button class="btn btn-mercan btn-buyuk" type="submit" data-olay="deneme_gonder"><span class="btn-yazi"><?= $gun ?> günlük denemeyi başlat</span><?= ik('arrow-right') ?></button>
      </form>
      <div class="form-tamam" id="form-tamam" hidden tabindex="-1">
        <div class="tamam-isaret"><?= ik('check') ?></div>
        <h3>Talebiniz alındı</h3>
        <p>Teşekkürler <b data-ad></b>. Deneme alanınızı hazırlamak için sizinle iletişime geçeceğiz.</p>
        <?php if ($wa !== ''): ?><a class="btn btn-wa" data-wa-tamam href="<?= h($wa) ?>" target="_blank" rel="noopener" data-olay="whatsapp_tamam"><?= ik('whatsapp') ?>Hemen WhatsApp’tan da yazın</a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php
}

function ts_ayak()
{
    $marka = ts_ayar('marka'); $wa = ts_wa_link(); $bandi = ts_ayar('cerez_bandi', '1') === '1'; $ga = ts_ayar('ga4', '') !== '' || ts_ayar('gtm', '') !== '';
    ?>
</main>
<footer class="alt">
  <div class="kap alt-ic">
    <div class="alt-marka">
      <a class="logo" href="./" aria-label="<?= h($marka) ?>"><?= ts_logo() ?><span><?= h($marka) ?></span></a>
      <p>İzin, mesai, puantaj, giriş-çıkış ve özlük dosyalarını tek panelde toplayan İK programı.</p>
      <?php foreach (['instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'x' => 'X', 'youtube' => 'YouTube', 'facebook' => 'Facebook'] as $k => $ad): if (preg_match('~^https?://~i', ts_ayar($k))): ?>
        <a class="sosyal" href="<?= h(ts_ayar($k)) ?>" target="_blank" rel="noopener me"><?= h($ad) ?></a>
      <?php endif; endforeach; ?>
    </div>
    <nav class="alt-nav" aria-label="Özellik sayfaları">
      <h2>Özellikler</h2>
      <?php foreach (ts_gruplar() as $slug => $g): ?><a href="<?= h($slug) ?>"><?= h($g['ana']) ?></a><?php endforeach; ?>
    </nav>
    <div class="alt-nav">
      <h2>İletişim</h2>
      <?php if ($wa !== ''): ?><a href="<?= h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
      <?php if (ts_ayar('telefon') !== ''): ?><a href="tel:<?= h(preg_replace('/[^\d+]/', '', ts_ayar('telefon'))) ?>"><?= h(ts_ayar('telefon')) ?></a><?php endif; ?>
      <?php if (ts_ayar('eposta') !== ''): ?><a href="mailto:<?= h(ts_ayar('eposta')) ?>"><?= h(ts_ayar('eposta')) ?></a><?php endif; ?>
      <?php if (ts_ayar('adres') !== ''): ?><span class="adres"><?= h(ts_ayar('adres')) ?></span><?php endif; ?>
      <a href="./#deneme">Deneme talebi</a>
      <a href="gizlilik">Gizlilik ve aydınlatma</a>
    </div>
  </div>
  <div class="kap alt-son"><span>© <?= date('Y') ?> <?= h($marka) ?>. Tüm hakları saklıdır.</span></div>
</footer>
<?php if ($wa !== ''): ?>
<a class="wa-yuzen" href="<?= h($wa) ?>" target="_blank" rel="noopener" aria-label="WhatsApp ile yazın" data-olay="whatsapp_yuzen"><?= ik('whatsapp') ?><span>WhatsApp</span></a>
<?php endif; ?>
<?php if ($bandi && $ga): ?>
<div class="cerez" id="cerez" role="dialog" aria-label="Çerez tercihi" hidden>
  <p>Ziyaretçi sayısını anlamak için Google Analytics kullanıyoruz. Kabul ederseniz çerez ve benzeri teknolojiler çalışır. <a href="gizlilik#cerez">Ayrıntı</a></p>
  <div><button type="button" class="btn btn-kucuk btn-hayalet" data-cerez="hayir">Reddet</button><button type="button" class="btn btn-kucuk btn-turkuaz" data-cerez="evet">Kabul et</button></div>
</div>
<?php endif; ?>
<script src="<?= h(ts_surum('assets/js/site.js')) ?>" defer></script>
<?= ts_ayar('ek_body') . "\n" ?>
</body>
</html>
<?php
}
