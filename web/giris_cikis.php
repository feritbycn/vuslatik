<?php
// ============================================================
// giris_cikis.php — Personel Giriş-Çıkış (yalnızca kaydırarak işe giriş / çıkış). Kayıt, rapor ve yönetim ekranları ik.php içindedir.
// Veriler giris_islem.php'den gelir. Tablolar ve yan menü sekmesi otomatik oluşur (giris_cikis.sql isteğe bağlı).
// Herkes kendi giriş-çıkışını yapar; İK panel sekmeleri yetkiye göre görünür (ik_giris_gor / ik_giris_yonet).
// ============================================================
require_once __DIR__ . '/config/baglan.php';
girisKontrol();
require_once __DIR__ . '/giris_ortak.php';
giris_sema($db);
$gc_uid = intval($_SESSION['kullanici_id'] ?? 0);
$gc_yetki = giris_yetki($db, $gc_uid);
require_once __DIR__ . '/header.php';
$gc_v = function ($yol) { return (int)@filemtime(__DIR__ . '/' . $yol); };
?>
<script>document.title = 'Giriş-Çıkış — ' + <?= json_encode(function_exists('pf_imza_adi') ? pf_imza_adi($db) : 'Turkuaz Bileşim') ?>; window.GC = { webauthn: true, ad: <?= json_encode($_SESSION['ad_soyad'] ?? '') ?>, yetki: <?= json_encode($gc_yetki) ?>, kid: <?= $gc_uid ?> };</script>
<link rel="stylesheet" href="assets/css/tb-giris.css?v=<?= $gc_v('assets/css/tb-giris.css') ?>">
<link rel="manifest" href="giris_manifest.php">
<meta name="mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-title" content="Giriş-Çıkış"><link rel="apple-touch-icon" href="assets/img/ik-bildirim-ikon.png">
<link rel="stylesheet" href="assets/css/tb-giris-tam.css?v=<?= $gc_v('assets/css/tb-giris-tam.css') ?>">

<main class="gc-sayfa">
    <div class="gc-baslik">
        <a href="ik.php" class="gc-geri" title="İK paneline dön"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h1><i class="fa-solid fa-fingerprint"></i> Giriş-Çıkış</h1>
            <p>Ofisteyken kaydırarak işe giriş ve çıkışınızı kaydedin.</p>
        </div>
    </div>
    <?php if ($gc_yetki['gor']): ?>
    <a class="gc-geri" href="ik.php?sekme=gc_bugun" style="display:inline-flex;gap:8px;align-items:center;width:auto;padding:8px 14px;margin:0 0 12px;text-decoration:none"><i class="fa-solid fa-table-list"></i> Giriş-çıkış kayıtları ve raporlar (İK sayfası)</a>
    <?php endif; ?>
    <div id="gc-icerik"><div class="gc-bos">Yükleniyor…</div></div>
</main>
<script src="assets/js/tb-ik-bildirim.js?v=<?= $gc_v('assets/js/tb-ik-bildirim.js') ?>" defer></script>
<link rel="stylesheet" href="assets/css/tb-giris-yontem.css?v=<?= $gc_v('assets/css/tb-giris-yontem.css') ?>">
<script src="assets/js/tb-giris-yontem.js?v=<?= $gc_v('assets/js/tb-giris-yontem.js') ?>" defer></script>
<script src="assets/js/tb-giris.js?v=<?= $gc_v('assets/js/tb-giris.js') ?>" defer></script>
<script src="assets/js/tb-giris-tam.js?v=<?= $gc_v('assets/js/tb-giris-tam.js') ?>" defer></script>
</body>
</html>
