<?php
// ============================================================
// index.php — ana sayfa (tanıtım)
// ============================================================
require_once __DIR__ . '/inc/kafa.php';
require_once __DIR__ . '/inc/ayak.php';

$gruplar = ts_gruplar(); $gun = h(ts_ayar('deneme_gun', '15'));
$toplam = 0; foreach ($gruplar as $g) $toplam += count($g['ozellikler']);
$sss = ts_genel_sss();
foreach ($gruplar as $g) if (!empty($g['sss'][0])) $sss[] = $g['sss'][0];
$moduller = ['İzin talebi', 'Onay akışı', 'Vekil', 'İzin takvimi', 'Mesai', 'Puantaj', 'Avans', 'Masraf', 'Giriş-çıkış', 'Vardiya', 'Özlük evrakı', 'Zimmet', 'Eğitim', 'Toplantı', 'Performans', 'Bordro', 'Duyuru', 'Anket', 'SMS bildirimi', 'E-posta', 'PDF rapor', 'Yedekleme', 'Yetkiler'];
ts_kafa(['yol' => '', 'sss' => $sss, 'govde' => 'ana']);
?>
<section class="hero" id="basla">
  <div class="hero-desen" aria-hidden="true"><svg viewBox="0 0 600 600"><defs><pattern id="yildiz" width="60" height="60" patternUnits="userSpaceOnUse"><path d="M30 6 L36.5 23.5 L54 30 L36.5 36.5 L30 54 L23.5 36.5 L6 30 L23.5 23.5Z" fill="none" stroke="currentColor" stroke-width="1.1"/></pattern></defs><rect width="600" height="600" fill="url(#yildiz)"/></svg></div>
  <div class="hero-isik" aria-hidden="true"></div>
  <div class="kap hero-ic">
    <div class="hero-metin">
      <p class="hero-rozet"><?= ik('sparkles') ?><?= $gun ?> gün ücretsiz deneme, kredi kartı gerekmez</p>
      <h1 class="hero-b"><span class="kelime">Personelin</span> <span class="kelime">izni,</span> <span class="kelime">mesaisi</span> <span class="kelime">ve</span> <span class="kelime">girişi</span> <span class="kelime">tek</span> <span class="kelime">İK</span> <span class="kelime">panelinde.</span></h1>
      <p class="hero-alt">Talep telefondan açılır, onay sırayla ilerler; izin bakiyesi, puantaj ve mesai kendiliğinden hesaplanır. Haberler SMS, e-posta ve telefon bildirimiyle gider.</p>
      <div class="hero-dugme">
        <a class="btn btn-mercan btn-buyuk" href="#deneme" data-olay="deneme_hero"><span class="btn-yazi"><?= $gun ?> gün ücretsiz dene</span><?= ik('arrow-right') ?></a>
        <a class="btn btn-hayalet-koyu btn-buyuk" href="#ozellikler">Özellikleri gör</a>
      </div>
      <p class="hero-not">Paneli deneyin: kaydırarak giriş yapın.</p>
    </div>

    <div class="hero-demo" aria-label="Panel örneği">
      <div class="cihaz" id="cihaz">
        <div class="cihaz-ust"><span class="cihaz-nokta"></span><span class="cihaz-nokta"></span><span class="cihaz-nokta"></span><b>Bugün</b><time id="demo-saat">08:56</time></div>
        <div class="cihaz-govde">
          <div class="gc" id="gc">
            <div class="gc-durum" id="gc-durum"><i></i><span>Henüz giriş yapmadınız</span></div>
            <div class="gc-kayit" id="gc-kayit" role="slider" aria-label="Girişi kaydırarak onaylayın" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" tabindex="0">
              <div class="gc-dolgu" id="gc-dolgu"></div>
              <span class="gc-yazi" id="gc-yazi">Kaydırarak giriş yap</span>
              <button type="button" class="gc-tutac" id="gc-tutac" aria-label="Giriş yap"><?= ik('arrow-right') ?></button>
            </div>
            <p class="gc-alt" id="gc-alt"><?= ik('map-pin') ?>Merkez ofis noktası · cihazınız tanındı</p>
          </div>

          <div class="demo-izin" id="demo-izin">
            <div class="demo-izin-ust"><span class="avatar">EK</span><div><b>Elif K. · 3 gün yıllık izin</b><small>12–14 Kasım · bakiye 11 gün</small></div><em class="chip" id="izin-chip">Bekliyor</em></div>
            <ol class="adimlar" id="izin-adimlar">
              <li><i><?= ik('check') ?></i><span>Talep</span></li>
              <li><i><?= ik('check') ?></i><span>Yönetici</span></li>
              <li><i><?= ik('check') ?></i><span>İK</span></li>
            </ol>
          </div>

          <div class="demo-satir"><span class="demo-ik"><?= ik('moon') ?></span><div><b>Cumartesi mesaisi</b><small>Hafta sonu olarak işaretlendi · 4 sa</small></div></div>
        </div>
      </div>
      <div class="sms" id="demo-sms" aria-hidden="true"><div class="sms-bas"><?= ik('message-square') ?>SMS</div><p>İzin talebiniz onaylandı: 12–14 Kasım. İyi tatiller!</p></div>
      <div class="rozet-yuzen rz1" aria-hidden="true"><?= ik('bell') ?>2 onay bekliyor</div>
      <div class="rozet-yuzen rz2" aria-hidden="true"><?= ik('file-down') ?>İzin belgesi hazır</div>
    </div>
  </div>
</section>

<section class="serit" aria-label="Modüller">
  <div class="serit-yol" aria-hidden="true">
    <?php for ($t = 0; $t < 2; $t++): ?><div class="serit-grup"><?php foreach ($moduller as $m): ?><span><?= h($m) ?></span><i></i><?php endforeach; ?></div><?php endfor; ?>
  </div>
  <p class="gorunmez">İzin talebi, onay akışı, vekil, izin takvimi, mesai, puantaj, avans, masraf, giriş-çıkış, vardiya, özlük evrakı, zimmet, eğitim, toplantı, performans, bordro, duyuru, anket, SMS bildirimi, e-posta, PDF rapor, yedekleme ve yetkiler.</p>
</section>

<section class="bolum onceki" id="once" aria-labelledby="once-b">
  <div class="kap">
    <div class="bolum-bas" data-goster>
      <h2 id="once-b">Excel tabloları ve WhatsApp grupları arasında dağılan işler</h2>
      <p>İzin, mesai ve evrak takibi bir kişinin hafızasına kalıyorsa her ay biraz zaman kaybedersiniz. Turkuaz Bileşim bu işlerin kaydını ve haberleşmesini tek yerde tutar.</p>
    </div>
    <div class="kars" data-goster>
      <div class="kars-sutun kars-once"><h3>Şimdi</h3>
        <ul>
          <li>İzin talebi mesajla ya da kâğıtla gelir</li><li>Bakiye Excel’de elle hesaplanır</li><li>Onaycı izindeyse talep bekler</li><li>Mesai ve puantaj ay sonunda toplanır</li><li>Süresi dolan evrak fark edilmez</li>
        </ul></div>
      <div class="kars-ok" aria-hidden="true"><?= ik('arrow-right') ?></div>
      <div class="kars-sutun kars-sonra"><h3>Turkuaz Bileşim ile</h3>
        <ul>
          <li>Talep telefondan açılır, onay sırayla ilerler</li><li>Bakiye ve kalan gün kendiliğinden hesaplanır</li><li>Vekil devreye girer, talep durmaz</li><li>Mesai türü ve puantaj otomatik oluşur</li><li>Evrak süresi dolmadan hatırlatma gider</li>
        </ul></div>
    </div>
  </div>
</section>

<section class="bolum ozellik" id="ozellikler" aria-labelledby="oz-b">
  <div class="kap">
    <div class="bolum-bas" data-goster>
      <h2 id="oz-b"><span class="say" data-say="<?= $toplam ?>"><?= $toplam ?></span> özellik, altı başlıkta</h2>
      <p>Aşağıdaki her başlık kendi sayfasında ayrıntılı anlatılır. Denemede hepsi açıktır.</p>
    </div>
    <div class="oz-yerlesim">
      <nav class="oz-nav" id="oz-nav" aria-label="Özellik başlıkları">
        <?php $i = 0; foreach ($gruplar as $slug => $g): ?>
          <a href="#g-<?= h($slug) ?>" data-hedef="g-<?= h($slug) ?>" style="--r:<?= h($g['renk']) ?>"<?= $i === 0 ? ' class="aktif"' : '' ?>><?= ik($g['ikon']) ?><span><?= h($g['kisa']) ?></span></a>
        <?php $i++; endforeach; ?>
        <i class="oz-cizgi" aria-hidden="true"></i>
      </nav>
      <div class="oz-icerik">
        <?php foreach ($gruplar as $slug => $g): ?>
        <article class="oz-grup" id="g-<?= h($slug) ?>" style="--r:<?= h($g['renk']) ?>" data-goster>
          <header>
            <span class="oz-ikon"><?= ik($g['ikon']) ?></span>
            <h3><?= h($g['ana']) ?></h3>
            <p><?= h($g['giris']) ?></p>
          </header>
          <dl class="oz-liste">
            <?php foreach ($g['ozellikler'] as $f): ?><div><dt><?= h($f[0]) ?></dt><dd><?= h($f[1]) ?></dd></div><?php endforeach; ?>
          </dl>
          <a class="oz-link" href="<?= h($slug) ?>"><?= h($g['kisa']) ?> ayrıntıları<?= ik('arrow-up-right') ?></a>
        </article>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>

<section class="bolum nasil" id="nasil" aria-labelledby="nasil-b">
  <div class="kap">
    <div class="bolum-bas" data-goster>
      <h2 id="nasil-b">Üç adımda denemeye başlayın</h2>
    </div>
    <ol class="adim-liste" data-goster>
      <li><span class="adim-no">1</span><h3>Talep oluşturun</h3><p>Formu doldurun ya da WhatsApp’tan yazın. Kaç personeliniz olduğunu ve şu an işleri nasıl yürüttüğünüzü kısaca bilmemiz yeter.</p></li>
      <li><span class="adim-no">2</span><h3>Deneme alanınızı hazırlayalım</h3><p>Sizinle iletişime geçeriz. Birimlerinizi ve personel listenizi Excel’den aktarırsınız; onay akışınızı birlikte kurarız.</p></li>
      <li><span class="adim-no">3</span><h3><?= $gun ?> gün kullanın, karar verin</h3><p>Ekibinizle gerçek izin, mesai ve giriş-çıkış kayıtları üzerinde deneyin. Uygun bulmazsanız yükümlülüğünüz olmaz.</p></li>
    </ol>
  </div>
</section>

<section class="bolum sss" id="sss" aria-labelledby="sss-b">
  <div class="kap sss-ic">
    <div class="bolum-bas" data-goster>
      <h2 id="sss-b">Sık sorulan sorular</h2>
      <p>Aradığınız cevabı bulamazsanız<?= ts_wa_link() !== '' ? ' <a href="' . h(ts_wa_link()) . '" target="_blank" rel="noopener">WhatsApp’tan</a> yazın' : ' bize yazın' ?>.</p>
    </div>
    <div class="sss-liste" data-goster>
      <?php foreach ($sss as $s): ?>
      <details><summary><span><?= h($s[0]) ?></span><?= ik('chevron-down') ?></summary><div class="sss-cevap"><p><?= h($s[1]) ?></p></div></details>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<?php ts_talep_bolumu(); ts_ayak(); ?>
