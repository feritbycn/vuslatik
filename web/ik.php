<?php
// ============================================================
// ik.php — İnsan Kaynakları (modern, sol menülü)
//   İK paneli · Personel kartları · İzin talepleri + ÇOK ADIMLI ONAY AKIŞI · Belgeler · Zimmet · Puantaj · Masraflar
//   · Eğitim · Performans · Bordro · GİRİŞ-ÇIKIŞ KAYITLARI (şu an, kayıtlar, raporlar, vardiya, cihaz, nokta, ayar)
//   · Bildirim kanalları (SMS / e-posta / tarayıcı) · Sayfa içi yetkilendirme
//   Veriler: ik_islem.php (İK) · giris_islem.php (giriş-çıkış) · ik_bildirim.php (bildirimler)
//   Personelin kaydırarak giriş-çıkış yaptığı sayfa ayrıdır: giris_cikis.php
// ============================================================
require_once __DIR__ . '/config/baglan.php';
girisKontrol();
require_once __DIR__ . '/ik_ortak.php';
require_once __DIR__ . '/yetkisiz.php';
ik_sema($db);
$ik_uid = intval($_SESSION['kullanici_id'] ?? 0);
$ik_yetki = vy_coz($db, 'ik', $ik_uid);
if (empty($ik_yetki['ik_erisim'])) tbYetkisizSayfa('İnsan Kaynakları');

// Hatırlatmalar (onay bekleyen talepler, belge süreleri, iş yıldönümleri): oturum başına 5 dakikada bir
if (time() - intval($_SESSION['ik_hatirlatma'] ?? 0) > 300) {
    $_SESSION['ik_hatirlatma'] = time();
    try { ik_hatirlatma_kontrol($db, $ik_uid); } catch (Throwable $e) {}
}
$ik_ben = $db->query("SELECT ad_soyad, avatar_renk, profil_resmi, gorev FROM kullanicilar WHERE id = $ik_uid")->fetch_assoc() ?: [];
$ik_ben_resim = function_exists('tb_profil_resmi_src') ? tb_profil_resmi_src($ik_ben['profil_resmi'] ?? '') : '';
$ik_gc_gor = !empty($ik_yetki['ik_giris_gor']) || !empty($ik_yetki['ik_giris_yonet']);
$ik_gc_yonet = !empty($ik_yetki['ik_giris_yonet']);
$ik_akis = !empty($ik_yetki['ik_akis_yonet']);

require_once __DIR__ . '/header.php';
$ik_surum = htmlspecialchars($tb_surum ?? '1', ENT_QUOTES, 'UTF-8');
$ik_v = function ($yol) use ($ik_surum) { $t = @filemtime(__DIR__ . '/' . $yol); return $t ? $t : $ik_surum; };
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };

/** Menü bazlı görünürlük: yetki anahtarı hiç tanımlı değilse (eski kurulum) görünür sayılır */
$gor = function ($k) use ($ik_yetki) { return !array_key_exists($k, $ik_yetki) || !empty($ik_yetki[$k]); };
/** Sol menü öğesi — her sekmenin kendi renginde simgesi var */
$renkler = ['raporlar' => '#f43f5e', 'mesailer' => '#6366f1', 'ozellikler' => '#8b5cf6', 'sayfa_erisim' => '#ef4444', 'duyurular' => '#f43f5e', 'dosyalar' => '#0ea5e9', 'anketler' => '#8b5cf6', 'genel' => '#6366f1', 'benimikm' => '#8b5cf6', 'izinler' => '#10b981', 'izin_takvimi' => '#0ea5e9', 'puantaj' => '#f59e0b', 'masraflar' => '#ec4899', 'avanslar' => '#f97316', 'personel' => '#3b82f6', 'birimler' => '#0ea5e9', 'belge_tum' => '#14b8a6', 'zimmet_tum' => '#f97316', 'egitimler' => '#a855f7',
    'performans' => '#eab308', 'kullanicilar' => '#0ea5e9', 'bordro' => '#22c55e', 'gc_bugun' => '#06b6d4', 'gc_kayitlar' => '#6366f1', 'gc_rapor' => '#f43f5e', 'gc_vardiyalar' => '#f59e0b', 'gc_cihazlar' => '#64748b', 'gc_noktalar' => '#ef4444', 'gc_ayarlar' => '#8b5cf6', 'akis' => '#d946ef', 'kanallar' => '#0ea5e9', 'ayarlar' => '#64748b'];
require_once __DIR__ . '/ik_ek.php';
$ik_kapali = ik_ek_kapali($db); $ik_sade = ik_ek_sade($db);
$renkler += ['isealim' => '#0ea5e9', 'ajanda' => '#f59e0b', 'loglar' => '#64748b', 'gorunum' => '#14b8a6', 'paketlerim' => '#8b5cf6'];
require_once __DIR__ . '/platform_paket.php';
try { pf_paket_sema($db); } catch (Throwable $e) {}
$ik_kilitli = pf_paket_kilitli($db); $ik_paket = pf_paket_ik_ozet($db);
$nav = function ($anahtar, $ikon, $ad, $rozet = '') use ($h, $renkler, $ik_kapali, $ik_kilitli, $ik_paket) {
    if (in_array($anahtar, $ik_kapali, true)) return '';
    $kilit = in_array($anahtar, $ik_kilitli, true);
    if ($kilit && !($ik_paket['gorunur'] && $ik_paket['vitrin'])) return '';
    if ($kilit) $rozet = '<i class="fa-solid fa-lock ikm-kilit-ikon"></i>';
    return '<button type="button" class="ikm-nav' . ($kilit ? ' ikm-nav-kilit' : '') . '" data-s="' . $anahtar . '" style="--ni:' . ($renkler[$anahtar] ?? '#6366f1') . '"><span class="ikm-nav-ikon"><i class="fa-solid ' . $ikon . '"></i></span><span class="ikm-nav-ad">' . $ad . '</span>' . $rozet . '</button>';
};
?>
<script>document.title = <?= json_encode('İnsan Kaynakları — ' . ($KURUM['ad'] ?? '')) ?>; window.IK_YETKI = <?= json_encode($ik_yetki) ?>; window.IK_BEN = <?= json_encode(['id' => $ik_uid, 'ad' => $ik_ben['ad_soyad'] ?? '']) ?>;</script>
<link rel="stylesheet" href="assets/css/tb-yetki-panel.css?v=<?= $ik_v('assets/css/tb-yetki-panel.css') ?>">
<link rel="stylesheet" href="assets/css/tb-ikm.css?v=<?= $ik_v('assets/css/tb-ikm.css') ?>">
<link rel="stylesheet" href="assets/css/tb-ik-duyuru.css?v=<?= $ik_v('assets/css/tb-ik-duyuru.css') ?>">
<link rel="stylesheet" href="assets/css/tb-ik-rapor.css?v=<?= $ik_v('assets/css/tb-ik-rapor.css') ?>">
<link rel="stylesheet" href="assets/css/tb-ik-org.css?v=<?= $ik_v('assets/css/tb-ik-org.css') ?>">
<link rel="stylesheet" href="assets/css/tb-ik-akis.css?v=<?= $ik_v('assets/css/tb-ik-akis.css') ?>">
<link rel="stylesheet" href="assets/css/tb-ik-ek.css?v=<?= $ik_v('assets/css/tb-ik-ek.css') ?>">

<div class="ikm<?= $ik_sade ? ' ikm-sade' : '' ?>" id="ik-kok">
    <div class="ikm-perde" id="ikm-perde"></div>

    <aside class="ikm-yan" id="ikm-yan" aria-label="İnsan Kaynakları menüsü">
        <div class="ikm-marka">
            <?php if (!empty($KURUM['logo'])): ?><img class="ikm-kurum-logo" src="<?= $h($KURUM['logo']) ?>" alt=""><?php else: ?><span class="ikm-logo"><i class="fa-solid fa-people-group"></i></span><?php endif; ?>
            <div><b>İnsan Kaynakları</b><small><?= $h($KURUM['ad'] ?? '') ?></small></div>
            <button type="button" class="ikm-yan-kapat" id="ikm-yan-kapat" aria-label="Menüyü kapat"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <nav class="ikm-nav-liste" id="ik-sekmeler">
            <div class="ikm-nav-baslik">Genel</div>
            <?= $nav('genel', 'fa-gauge-high', 'İK paneli') ?>
            <?= $nav('benimikm', 'fa-id-badge', 'Benim İK’m') ?>

            <div class="ikm-nav-baslik">İzin ve devam</div>
            <?= $nav('izinler', 'fa-calendar-check', 'İzin talepleri', '<b class="ikm-rozet" id="ikm-r-izin" hidden></b>') ?>
            <?php if ($gor('ik_menu_takvim')) echo $nav('izin_takvimi', 'fa-calendar-days', 'Takvim'); ?>
            <?php if ($gor('ik_menu_puantaj')) echo $nav('puantaj', 'fa-business-time', 'Puantaj'); ?>
            <?php if ($gor('ik_menu_masraf')) echo $nav('masraflar', 'fa-wallet', 'Personel masrafları'); ?>
            <?php if ($gor('ik_menu_avans')) echo $nav('avanslar', 'fa-hand-holding-dollar', 'Avans talepleri'); ?>
            <?php if ($gor('ik_menu_mesai')) echo $nav('mesailer', 'fa-moon', 'Mesai', '<b class="ikm-rozet" id="ikm-r-mesai" hidden></b>'); ?>

            <?php if ($gor('ik_menu_duyuru') && count(array_diff(['duyurular', 'dosyalar', 'anketler'], $ik_kapali))): ?>
            <div class="ikm-nav-baslik">Duyuru ve paylaşım</div>
            <?= $nav('duyurular', 'fa-bullhorn', 'Duyurular', '<b class="ikm-rozet" id="ikm-r-duyuru" hidden></b>') ?>
            <?= $nav('dosyalar', 'fa-folder-open', 'Dosyalar') ?>
            <?= $nav('anketler', 'fa-square-poll-vertical', 'Anketler', '<b class="ikm-rozet" id="ikm-r-anket" hidden></b>') ?>
            <?php endif; ?>

            <div class="ikm-nav-baslik">Personel</div>
            <?php if (!empty($ik_yetki['ik_personel_gor'])) echo $nav('personel', 'fa-address-book', 'Personel'); ?>
            <?php if (!empty($ik_yetki['ik_personel_gor'])) echo $nav('birimler', 'fa-sitemap', 'Şirket yapısı'); ?>
            <?php if (!empty($ik_yetki['ik_personel_gor']) || !empty($ik_yetki['ik_duzenle']) || !empty($ik_yetki['kurucu'])) echo $nav('isealim', 'fa-user-plus', 'İşe alım'); ?>
            <?php if (!empty($ik_yetki['ik_belge'])) echo $nav('belge_tum', 'fa-file-lines', 'Özlük evrakları') . $nav('zimmet_tum', 'fa-box-archive', 'Zimmetler'); ?>
            <?php if ($gor('ik_menu_egitim')) echo $nav('egitimler', 'fa-graduation-cap', 'Eğitimler'); ?>
            <?php if ($gor('ik_menu_performans')) echo $nav('performans', 'fa-star', 'Performans'); ?>
            <?php if ($gor('ik_menu_bordro')) echo $nav('bordro', 'fa-file-invoice-dollar', 'Bordro kayıtları'); ?>

            <?php if (!empty($ik_yetki['ik_rapor_gor'])): ?>
            <div class="ikm-nav-baslik">Raporlama</div>
            <?= $nav('raporlar', 'fa-chart-pie', 'Raporlar') ?>
            <?php endif; ?>

            <?php if ($ik_gc_gor): ?>
            <div class="ikm-nav-baslik">Giriş-çıkış kayıtları</div>
            <?= $nav('gc_bugun', 'fa-users-viewfinder', 'Şu an kimler içeride', '<b class="ikm-rozet" id="ikm-r-cihaz" hidden></b>') ?>
            <?= $nav('gc_kayitlar', 'fa-table-list', 'Kayıtlar') ?>
            <?= $nav('gc_rapor', 'fa-chart-column', 'Raporlar') ?>
            <?php if ($ik_gc_yonet) echo $nav('gc_vardiyalar', 'fa-clock', 'Vardiyalar') . $nav('gc_cihazlar', 'fa-mobile-screen', 'Cihazlar') . $nav('gc_noktalar', 'fa-location-dot', 'Geçiş noktaları') . $nav('gc_ayarlar', 'fa-sliders', 'Giriş-çıkış ayarları'); ?>
            <?php endif; ?>

            <?php if ($ik_akis || !empty($ik_yetki['ik_ayarlar']) || !empty($ik_yetki['ik_duzenle']) || !empty($ik_yetki['ik_personel_gor']) || !empty($ik_yetki['kurucu']) || !empty($ik_yetki['ik_bildirim_yonet']) || !empty($ik_yetki['ik_kullanici_yonet'])): ?>
            <div class="ikm-nav-baslik">Yönetim</div>
            <?php if ($ik_akis) echo $nav('akis', 'fa-diagram-project', 'İzin onay akışı') . $nav('kanallar', 'fa-paper-plane', 'Bildirim kanalları'); ?>
            <?php if (!empty($ik_yetki['ik_ayarlar']) || !empty($ik_yetki['kurucu']) || !empty($ik_yetki['ik_bildirim_yonet'])) echo $nav('ayarlar', 'fa-gear', 'İK ayarları'); ?>
            <?php if (!empty($ik_yetki['kurucu']) || !empty($ik_yetki['ik_ayarlar']) || !empty($ik_yetki['ik_personel_gor']) || !empty($ik_yetki['ik_duzenle'])) echo $nav('loglar', 'fa-clock-rotate-left', 'İşlem logları'); ?>
            <?php if (!empty($ik_yetki['kurucu']) || !empty($ik_yetki['ik_ayarlar'])) echo $nav('gorunum', 'fa-palette', 'Görünüm ve menü'); ?>
            <?php if ((!empty($ik_yetki['kurucu']) || !empty($ik_yetki['ik_ayarlar'])) && !empty($ik_paket['gorunur'])) echo $nav('paketlerim', 'fa-cube', 'Paketlerim'); ?>
            <?php if (!empty($ik_yetki['ik_kullanici_yonet'])) echo $nav('kullanicilar', 'fa-users-gear', 'Kullanıcılar'); ?>
            <?php if (!empty($ik_yetki['kurucu'])) echo $nav('sayfa_erisim', 'fa-lock', 'Sayfa erişimi'); ?>
            <?php if (!empty($ik_yetki['kurucu']) || !empty($_SESSION['destek'])) echo $nav('yedekler', 'fa-box-archive', 'Yedekler'); ?>
            <?php if (!empty($ik_yetki['kurucu'])): ?><button type="button" class="ikm-nav" id="ik-yetkiler" style="--ni:#ef4444"><span class="ikm-nav-ikon"><i class="fa-solid fa-user-shield"></i></span><span class="ikm-nav-ad">Yetkiler (kim neyi görür)</span></button><?php endif; ?>
            <?php endif; ?>

            <?php if ($gor('ik_menu_ozellik')): ?>
            <div class="ikm-nav-baslik">Yardım</div>
            <?= $nav('ozellikler', 'fa-circle-question', 'Site özellikleri') ?>
            <?php endif; ?>
        </nav>

        <div class="ikm-yan-alt">
            <a class="ikm-giris-btn" href="giris_cikis.php"><i class="fa-solid fa-fingerprint"></i><span><b>Giriş-çıkış yap</b><small>Kaydırarak işe giriş / çıkış</small></span><i class="fa-solid fa-arrow-right"></i></a>
            <div class="ikm-ben">
                <span class="ikm-av<?= $ik_ben_resim ? ' foto' : '' ?>" style="background:<?= $h($ik_ben['avatar_renk'] ?? '#6366f1') ?>"><?= $h(mb_strtoupper(mb_substr($ik_ben['ad_soyad'] ?? '?', 0, 1, 'UTF-8'), 'UTF-8')) ?><?php if ($ik_ben_resim): ?><img src="<?= $h($ik_ben_resim) ?>" alt="" onerror="this.parentNode.classList.remove('foto');this.remove()"><?php endif; ?></span>
                <div><b><?= $h($ik_ben['ad_soyad'] ?? '') ?></b><small><?= $h($ik_ben['gorev'] ?? '') ?></small></div>
                <button type="button" class="ikm-ikon-btn" id="ik-tema" title="Açık / koyu tema" aria-label="Tema"><i class="fa-solid fa-circle-half-stroke"></i></button>
                <button type="button" class="ikm-ikon-btn" id="ik-sifre" title="Şifremi değiştir" aria-label="Şifremi değiştir"><i class="fa-solid fa-key"></i></button>
                <a href="cikis.php" class="ikm-ikon-btn" title="Çıkış yap" aria-label="Çıkış yap"><i class="fa-solid fa-right-from-bracket"></i></a>
            </div>
        </div>
    </aside>

    <div class="ikm-ana">
        <?php if (!empty($_SESSION['destek']) && !empty($_SESSION['pf_yonetici_id'])): ?><div class="ikm-destek"><i class="fa-solid fa-headset"></i> Destek oturumu — <b><?= $h($KURUM['ad'] ?? '') ?></b> kurumunu kurum yetkilisi olarak görüntülüyorsunuz. <a href="platform.php#kurumlar">Platform paneline dön</a></div><?php endif; ?>
        <header class="ikm-ust">
            <button type="button" class="ikm-ikon-btn ikm-menu-ac" id="ikm-menu-ac" aria-label="Menüyü aç"><i class="fa-solid fa-bars"></i></button>
            <div class="ikm-ust-baslik">
                <h1 id="ikm-baslik">İK paneli</h1>
                <p id="ikm-altbaslik">Personel kartları, izinler, belgeler ve zimmetler tek yerde.</p>
            </div>
            <div class="ikm-ust-sag">
                <div class="ikm-zil-kap">
                    <button type="button" class="ikm-ikon-btn" id="ikm-zil" aria-label="Bildirimler" aria-haspopup="true"><i class="fa-regular fa-bell"></i><b class="ikm-zil-sayi" id="ikm-zil-sayi" hidden>0</b></button>
                    <div class="ikm-zil-panel" id="ikm-zil-panel" hidden></div>
                </div>
                <button type="button" class="ikm-btn" id="ik-kartim"><i class="fa-solid fa-id-card"></i> <span>Kartım</span></button>
                <button type="button" class="ikm-btn ikm-btn-ana" id="ik-izin-yeni"><i class="fa-solid fa-circle-plus"></i> <span>Talep oluştur</span></button>
            </div>
        </header>

        <div class="ikm-sabit" id="ikm-sabit" aria-live="polite"></div>
        <main class="ikm-icerik" id="ik-icerik"><div class="ikm-iskelet"><i></i><i></i><i></i></div></main>
        <footer class="tb-altbilgi">Tüm hakları saklıdır, <b><?= htmlspecialchars(pf_imza_adi($db), ENT_QUOTES, 'UTF-8') ?></b></footer>
    </div>
</div>

<script src="assets/js/tb-yetki-panel.js?v=<?= $ik_v('assets/js/tb-yetki-panel.js') ?>" defer></script>
<script src="assets/js/tb-ik-bildirim.js?v=<?= $ik_v('assets/js/tb-ik-bildirim.js') ?>" defer></script>
<script src="assets/js/tb-mobil-push.js?v=<?= $ik_v('assets/js/tb-mobil-push.js') ?>" defer></script>
<script src="assets/js/tb-ik.js?v=<?= $ik_v('assets/js/tb-ik.js') ?>" defer></script>
<script src="assets/js/tb-ik-akis.js?v=<?= $ik_v('assets/js/tb-ik-akis.js') ?>" defer></script>
<script src="assets/js/tb-ik-izin.js?v=<?= $ik_v('assets/js/tb-ik-izin.js') ?>" defer></script>
<?php if ($gor('ik_menu_duyuru')): ?><script src="assets/js/tb-ik-duyuru.js?v=<?= $ik_v('assets/js/tb-ik-duyuru.js') ?>" defer></script><?php endif; ?>
<script src="assets/js/tb-ik-rapor.js?v=<?= $ik_v('assets/js/tb-ik-rapor.js') ?>" defer></script>
<script src="assets/js/tb-ik-org.js?v=<?= $ik_v('assets/js/tb-ik-org.js') ?>" defer></script>
<?php if ($gor('ik_menu_ozellik')): ?><script src="assets/js/tb-ik-ozellik.js?v=<?= $ik_v('assets/js/tb-ik-ozellik.js') ?>" defer></script><?php endif; ?>
<script src="assets/js/tb-ik-kurum.js?v=<?= $ik_v('assets/js/tb-ik-kurum.js') ?>" defer></script>
<script src="assets/js/tb-ik-perf.js?v=<?= $ik_v('assets/js/tb-ik-perf.js') ?>" defer></script>
<script src="assets/js/tb-ik-paket.js?v=<?= $ik_v('assets/js/tb-ik-paket.js') ?>" defer></script>
<script src="assets/js/tb-ik-takvim.js?v=<?= $ik_v('assets/js/tb-ik-takvim.js') ?>" defer></script>
<script src="assets/js/tb-ik-ek.js?v=<?= $ik_v('assets/js/tb-ik-ek.js') ?>" defer></script>
<script src="assets/js/tb-yedek.js?v=<?= $ik_v('assets/js/tb-yedek.js') ?>" defer></script>
<script src="assets/js/tb-ik-yedek.js?v=<?= $ik_v('assets/js/tb-ik-yedek.js') ?>" defer></script>
<?php if ($ik_gc_gor): ?><script src="assets/js/tb-giris-yontem.js?v=<?= $ik_v('assets/js/tb-giris-yontem.js') ?>" defer></script><script src="assets/js/tb-ik-giris.js?v=<?= $ik_v('assets/js/tb-ik-giris.js') ?>" defer></script><?php endif; ?>
</body>
</html>
