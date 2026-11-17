<?php
// gizlilik.php — aydınlatma metni ve çerez bilgisi (ŞABLON: yayına almadan önce hukuk danışmanınızla gözden geçirin)
require_once __DIR__ . '/inc/kafa.php';
require_once __DIR__ . '/inc/ayak.php';
$marka = ts_ayar('marka'); $eposta = ts_ayar('eposta', ts_ayar('talep_eposta', ''));
ts_kafa(['yol' => 'gizlilik', 'baslik' => 'Gizlilik ve Aydınlatma Metni | ' . $marka, 'meta' => 'Deneme talebi formunda paylaştığınız bilgilerin nasıl kullanıldığı, çerezler ve haklarınız hakkında bilgilendirme.', 'kirinti' => [['Gizlilik', 'gizlilik']], 'govde' => 'metin']);
?>
<section class="bolum metin"><div class="kap dar">
<h1>Gizlilik ve aydınlatma metni</h1>
<p>Bu metin, <?= h($marka) ?> tanıtım sitesindeki deneme talebi formunu doldurduğunuzda paylaştığınız bilgilerin nasıl kullanıldığını açıklar.</p>
<h2>Hangi bilgileri alıyoruz?</h2>
<p>Ad soyad, şirket adı, e-posta, telefon, personel sayısı aralığı ve isteğe bağlı notunuz. Ayrıca formu gönderdiğiniz sayfa ve sitemize hangi bağlantıdan geldiğiniz (reklam etiketi gibi) kaydedilebilir.</p>
<h2>Bu bilgileri ne için kullanıyoruz?</h2>
<p>Yalnızca deneme talebinizi değerlendirmek, sizinle iletişime geçmek ve deneme alanınızı hazırlamak için. Bilgileriniz üçüncü kişilere satılmaz.</p>
<h2>Ne kadar saklıyoruz?</h2>
<p>Talebiniz sonuçlanana kadar ve yasal yükümlülüklerin gerektirdiği süre boyunca. Silinmesini istediğiniz an bize yazabilirsiniz.</p>
<h2>Haklarınız</h2>
<p>6698 sayılı Kişisel Verilerin Korunması Kanunu’nun 11. maddesi uyarınca verilerinizin işlenip işlenmediğini öğrenme, düzeltilmesini veya silinmesini isteme ve itiraz etme haklarına sahipsiniz.<?php if ($eposta !== ''): ?> Başvuru için <a href="mailto:<?= h($eposta) ?>"><?= h($eposta) ?></a> adresine yazabilirsiniz.<?php endif; ?></p>
<h2 id="cerez">Çerezler</h2>
<p>Site, çalışması için zorunlu olan oturum çerezini kullanır. Google Analytics tanımlıysa ziyaret istatistikleri için çerezler yalnızca siz “Kabul et” dediğinizde çalıştırılır. Tercihinizi tarayıcınızın verilerini temizleyerek sıfırlayabilirsiniz.</p>
<p class="soluk">Not: Bu metin genel bir şablondur; veri sorumlusunun unvanı, adresi ve başvuru yolu yayına almadan önce kendi bilgilerinizle tamamlanmalıdır.</p>
</div></section>
<?php ts_ayak(); ?>
