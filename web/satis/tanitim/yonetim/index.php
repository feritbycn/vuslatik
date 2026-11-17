<?php
// ============================================================
// yonetim/index.php — site yönetimi: talepler, SEO ve Google ayarları, hesap
// İlk kurulumda data/kurulum_kodu.txt dosyasındaki kodla yönetici şifresi belirlenir.
// ============================================================
require_once dirname(__DIR__) . '/inc/ortak.php';
ts_oturum();
header('X-Robots-Tag: noindex, nofollow'); header('Cache-Control: no-store'); header('X-Frame-Options: DENY');

$yon = ts_yonetici(); $mesaj = ''; $hata = ''; $sekme = in_array($_GET['s'] ?? '', ['talepler', 'ayarlar', 'hesap'], true) ? $_GET['s'] : 'talepler';
$post = ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
if ($post && !ts_csrf_ok($_POST['csrf'] ?? '')) { $post = false; $hata = 'Oturum süresi doldu, işlemi tekrar yapın.'; }
$islem = $post ? (string)($_POST['islem'] ?? '') : '';

/* ——— kurulum ——— */
if (!$yon) {
    $kodDosya = TS_VERI . '/kurulum_kodu.txt';
    if (!is_file($kodDosya)) { if (!is_dir(TS_VERI)) @mkdir(TS_VERI, 0750, true); ts_veri_koru(); @file_put_contents($kodDosya, bin2hex(random_bytes(6))); }
    if ($islem === 'kurulum') {
        $kod = trim((string)@file_get_contents($kodDosya)); $p1 = (string)($_POST['sifre'] ?? ''); $p2 = (string)($_POST['sifre2'] ?? ''); $kul = preg_replace('/[^a-zA-Z0-9._@-]/', '', (string)($_POST['kullanici'] ?? ''));
        if (!ts_hiz('kurulum', 8, 900)) $hata = 'Çok fazla deneme. 15 dakika sonra tekrar deneyin.';
        elseif ($kod === '' || !hash_equals($kod, trim((string)($_POST['kod'] ?? '')))) $hata = 'Kurulum kodu yanlış. Kod, sunucudaki data/kurulum_kodu.txt dosyasında yazar.';
        elseif (strlen($kul) < 3) $hata = 'Kullanıcı adı en az 3 karakter olmalı.';
        elseif (mb_strlen($p1) < 10) $hata = 'Şifre en az 10 karakter olmalı.';
        elseif ($p1 !== $p2) $hata = 'İki şifre aynı değil.';
        else { ts_json_yaz('yonetici.json', ['kullanici' => $kul, 'hash' => password_hash($p1, PASSWORD_DEFAULT)]); @unlink($kodDosya); session_regenerate_id(true); $_SESSION['yonetici'] = 1; header('Location: ./'); exit; }
    }
    $baslik = 'Yönetim kurulumu';
    ?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Kurulum</title><?php ts_yon_css(); ?></head><body class="giris"><form class="kutu" method="post" autocomplete="off">
    <h1>Yönetim kurulumu</h1><p class="soluk">Bu ekranı yalnızca siz görebilirsiniz: sunucudaki <code>data/kurulum_kodu.txt</code> dosyasını açıp içindeki kodu yazın (FTP ya da dosya yöneticisi ile).</p>
    <?php if ($hata): ?><p class="hata"><?= h($hata) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= h(ts_csrf()) ?>"><input type="hidden" name="islem" value="kurulum">
    <label>Kurulum kodu<input name="kod" required></label><label>Kullanıcı adı<input name="kullanici" required minlength="3"></label>
    <label>Şifre (en az 10 karakter)<input type="password" name="sifre" required minlength="10"></label><label>Şifre (tekrar)<input type="password" name="sifre2" required></label>
    <button class="btn">Kurulumu tamamla</button></form></body></html><?php exit;
}

/* ——— giriş ——— */
if ($islem === 'giris') {
    $k = (string)($_POST['kullanici'] ?? ''); $s = (string)($_POST['sifre'] ?? '');
    if (!ts_hiz('giris', 6, 900)) $hata = 'Çok fazla yanlış deneme. 15 dakika sonra tekrar deneyin.';
    elseif (hash_equals((string)$yon['kullanici'], $k) && password_verify($s, (string)$yon['hash'])) { session_regenerate_id(true); $_SESSION['yonetici'] = 1; header('Location: ./'); exit; }
    else { usleep(400000); $hata = 'Kullanıcı adı ya da şifre yanlış.'; }
}
if (!ts_giris_yapmis()) {
    ?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Yönetim girişi</title><?php ts_yon_css(); ?></head><body class="giris"><form class="kutu" method="post">
    <h1>Yönetim girişi</h1><?php if ($hata): ?><p class="hata"><?= h($hata) ?></p><?php endif; ?>
    <input type="hidden" name="csrf" value="<?= h(ts_csrf()) ?>"><input type="hidden" name="islem" value="giris">
    <label>Kullanıcı adı<input name="kullanici" required autofocus autocomplete="username"></label><label>Şifre<input type="password" name="sifre" required autocomplete="current-password"></label>
    <button class="btn">Giriş yap</button></form></body></html><?php exit;
}
if (($_GET['cikis'] ?? '') === '1' ) { $_SESSION = []; session_destroy(); header('Location: ./'); exit; }

/* ——— işlemler ——— */
$DURUMLAR = ['yeni' => 'Yeni', 'arandi' => 'Arandı', 'denemede' => 'Denemede', 'musteri' => 'Müşteri oldu', 'ilgisiz' => 'İlgisiz'];
if ($islem === 'talep_durum' || $islem === 'talep_sil') {
    $kilit = fopen(TS_VERI . '/.talep.kilit', 'c'); if ($kilit) flock($kilit, LOCK_EX);
    $liste = ts_json_oku('talepler.json', []); $id = (string)($_POST['id'] ?? '');
    foreach ($liste as $i => $t) if ($t['id'] === $id) {
        if ($islem === 'talep_sil') { array_splice($liste, $i, 1); $mesaj = 'Talep silindi.'; }
        else { $d = (string)($_POST['durum'] ?? 'yeni'); $liste[$i]['durum'] = isset($DURUMLAR[$d]) ? $d : 'yeni'; $liste[$i]['not'] = mb_substr(trim((string)($_POST['not'] ?? '')), 0, 600); $mesaj = 'Kaydedildi.'; }
        break;
    }
    ts_json_yaz('talepler.json', $liste); if ($kilit) { flock($kilit, LOCK_UN); fclose($kilit); }
    $sekme = 'talepler';
}
if ($islem === 'ayar_kaydet') {
    $alanlar = array_keys(ts_varsayilan()); $yeni = [];
    foreach ($alanlar as $a) { if (isset($_POST[$a])) $yeni[$a] = (string)$_POST[$a]; }
    foreach (['cerez_bandi', 'robots_index'] as $c) $yeni[$c] = isset($_POST[$c]) ? '1' : '0';
    $yeni['ga4'] = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $yeni['ga4'] ?? '')); $yeni['gtm'] = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', $yeni['gtm'] ?? ''));
    $yeni['gsc'] = preg_replace('/[^A-Za-z0-9_\-]/', '', preg_replace('~^.*content=["\']?([A-Za-z0-9_\-]+).*$~s', '$1', $yeni['gsc'] ?? '')); $yeni['bing'] = preg_replace('/[^A-Za-z0-9]/', '', $yeni['bing'] ?? '');
    $yeni['deneme_gun'] = (string)max(1, min(90, (int)($yeni['deneme_gun'] ?? 15)));
    $yeni['whatsapp'] = preg_replace('/[^\d+]/', '', $yeni['whatsapp'] ?? '');
    if (ts_ayar_kaydet($yeni)) $mesaj = 'Ayarlar kaydedildi. Değişiklik sitede hemen görünür.'; else $hata = 'Ayarlar yazılamadı: data klasörünün yazma izni olduğundan emin olun.';
    $sekme = 'ayarlar';
}
if ($islem === 'sifre') {
    $m = (string)($_POST['mevcut'] ?? ''); $n1 = (string)($_POST['yeni'] ?? ''); $n2 = (string)($_POST['yeni2'] ?? '');
    if (!password_verify($m, (string)$yon['hash'])) $hata = 'Mevcut şifre yanlış.'; elseif (mb_strlen($n1) < 10) $hata = 'Yeni şifre en az 10 karakter olmalı.'; elseif ($n1 !== $n2) $hata = 'Yeni şifreler aynı değil.';
    else { $yon['hash'] = password_hash($n1, PASSWORD_DEFAULT); ts_json_yaz('yonetici.json', $yon); $mesaj = 'Şifre değiştirildi.'; }
    $sekme = 'hesap';
}
if (($_GET['csv'] ?? '') === '1') {
    header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="deneme-talepleri-' . date('Y-m-d') . '.csv"');
    $f = fopen('php://output', 'w'); fwrite($f, "\xEF\xBB\xBF"); fputcsv($f, ['Tarih', 'Ad soyad', 'Şirket', 'E-posta', 'Telefon', 'Personel', 'Not', 'Kaynak', 'Sayfa', 'Durum', 'Yönetici notu'], ';');
    $guv = function ($v) { $v = (string)$v; return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v; }; // Excel formül enjeksiyonuna karşı
    foreach (ts_json_oku('talepler.json', []) as $t) fputcsv($f, array_map($guv, [$t['zaman'], $t['ad'], $t['firma'], $t['eposta'], $t['telefon'], $t['personel'], $t['mesaj'], $t['kaynak'], $t['sayfa'], $DURUMLAR[$t['durum']] ?? $t['durum'], $t['not']]), ';');
    exit;
}

function ts_yon_css()
{ ?><style>
:root{--p:#0D3F4A;--t:#12B5BF;--m:#F2573F;--k:#2B4FD0;--z:#F4F9F8;--c:#D3E3E4;--y:#12323A;--s:#577379}
*{box-sizing:border-box}body{margin:0;background:var(--z);color:var(--y);font:16px/1.55 system-ui,"Segoe UI",sans-serif}
h1,h2,h3{margin:0;line-height:1.2}a{color:var(--k)}code{background:#E3EEEF;padding:1px 6px;border-radius:6px;font-size:.9em}
.soluk{color:var(--s)}.hata{background:#FDE9E5;color:#A32A16;padding:10px 14px;border-radius:12px}.tamam{background:#D5F5E1;color:#0F6B35;padding:10px 14px;border-radius:12px}
input,select,textarea{width:100%;font:inherit;padding:11px 13px;border:1.5px solid var(--c);border-radius:11px;background:#fff;color:var(--y)}input:focus,select:focus,textarea:focus{outline:none;border-color:var(--t);box-shadow:0 0 0 4px rgba(18,181,191,.2)}
label{display:grid;gap:6px;font-weight:600;font-size:.9rem}label small{font-weight:400;color:var(--s)}input[type=checkbox]{width:auto}
.btn{background:var(--m);color:#fff;border:0;border-radius:999px;padding:12px 22px;font:600 1rem system-ui;cursor:pointer;text-decoration:none;display:inline-block}.btn:hover{filter:brightness(.92)}.btn.ikinci{background:#fff;color:var(--y);box-shadow:inset 0 0 0 1.5px var(--c)}.btn.kucuk{padding:8px 14px;font-size:.875rem}
.giris{display:grid;place-items:center;min-height:100vh;padding:20px;background:var(--p)}.kutu{background:#fff;border-radius:22px;padding:32px;width:min(430px,100%);display:grid;gap:16px;box-shadow:0 30px 70px -30px #000}
.ust{background:var(--p);color:#fff}.ust-ic{max-width:1100px;margin:auto;padding:14px 20px;display:flex;gap:18px;align-items:center;flex-wrap:wrap}.ust b{font-size:1.1rem}.ust nav{display:flex;gap:4px;margin-left:auto;flex-wrap:wrap}.ust nav a{color:#CDE9EB;text-decoration:none;padding:8px 14px;border-radius:999px}.ust nav a.on,.ust nav a:hover{background:rgba(255,255,255,.14);color:#fff}
main{max-width:1100px;margin:28px auto;padding:0 20px 60px;display:grid;gap:22px}
.kart{background:#fff;border:1px solid var(--c);border-radius:18px;padding:24px;display:grid;gap:16px}
.sayi{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px}.sayi div{background:#fff;border:1px solid var(--c);border-radius:16px;padding:16px 18px}.sayi b{font-size:1.9rem;display:block;line-height:1.1}.sayi span{color:var(--s);font-size:.9rem}
.talep{border:1px solid var(--c);border-radius:16px;padding:18px;display:grid;gap:12px;background:#fff}.talep header{display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap}.talep h3{font-size:1.1rem}.talep .meta{display:flex;gap:6px 18px;flex-wrap:wrap;color:var(--s);font-size:.92rem}.talep .meta a{font-weight:600}
.talep form{display:grid;grid-template-columns:200px 1fr auto;gap:10px;align-items:end}.talep .sil{background:transparent;color:#A32A16;border:0;cursor:pointer;font:600 .85rem system-ui;padding:8px}
.rozet{font-size:.78rem;font-weight:700;padding:3px 10px;border-radius:999px;background:#FFF1D6;color:#8A5A00;height:fit-content}.rozet.arandi{background:#DCE6FF;color:#1E3A9E}.rozet.denemede{background:#D9F2F3;color:#0B6F77}.rozet.musteri{background:#D5F5E1;color:#0F6B35}.rozet.ilgisiz{background:#ECEFEF;color:#5B7479}
.iki{display:grid;grid-template-columns:1fr 1fr;gap:16px}.uc{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
details.grup{border:1px solid var(--c);border-radius:18px;background:#fff}details.grup>summary{cursor:pointer;padding:18px 22px;font-weight:700;font-size:1.05rem;list-style:none;display:flex;justify-content:space-between}details.grup>summary::after{content:"+";color:var(--s)}details.grup[open]>summary::after{content:"–"}details.grup .ic{padding:0 22px 22px;display:grid;gap:16px}
.kontrol{display:grid;gap:8px}.kontrol div{display:flex;gap:10px;align-items:flex-start}.kontrol i{flex:none;width:20px;height:20px;border-radius:50%;display:grid;place-items:center;font-style:normal;font-size:.75rem;font-weight:700;margin-top:3px}.kontrol .ok i{background:#D5F5E1;color:#0F6B35}.kontrol .uy i{background:#FFF1D6;color:#8A5A00}
.say{font-size:.8rem;color:var(--s);font-weight:400}.say.kotu{color:#A32A16;font-weight:700}.say.iyi{color:#0F6B35}
@media(max-width:760px){.iki,.uc,.talep form{grid-template-columns:1fr}}
</style><?php }

/* ——— sayfa ——— */
$A = ts_ayar_tumu(); $talepler = ts_json_oku('talepler.json', []);
$yeniSay = count(array_filter($talepler, function ($t) { return $t['durum'] === 'yeni'; }));
$haftaSay = count(array_filter($talepler, function ($t) { return strtotime($t['zaman']) > time() - 7 * 86400; }));
$filtre = (string)($_GET['d'] ?? '');
?><!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Site yönetimi</title><?php ts_yon_css(); ?></head><body>
<div class="ust"><div class="ust-ic"><b><?= h($A['marka']) ?> · yönetim</b>
<nav><a href="?s=talepler" class="<?= $sekme === 'talepler' ? 'on' : '' ?>">Talepler<?= $yeniSay ? ' (' . $yeniSay . ')' : '' ?></a><a href="?s=ayarlar" class="<?= $sekme === 'ayarlar' ? 'on' : '' ?>">Site ayarları</a><a href="?s=hesap" class="<?= $sekme === 'hesap' ? 'on' : '' ?>">Hesap</a><a href="../" target="_blank">Siteyi aç ↗</a><a href="?cikis=1">Çıkış</a></nav></div></div>
<main>
<?php if ($mesaj): ?><p class="tamam"><?= h($mesaj) ?></p><?php endif; if ($hata): ?><p class="hata"><?= h($hata) ?></p><?php endif; ?>

<?php if ($sekme === 'talepler'): ?>
  <div class="sayi"><div><b><?= count($talepler) ?></b><span>toplam talep</span></div><div><b><?= $yeniSay ?></b><span>yeni (aranmadı)</span></div><div><b><?= $haftaSay ?></b><span>son 7 gün</span></div></div>
  <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center"><a class="btn kucuk <?= $filtre === '' ? '' : 'ikinci' ?>" href="?s=talepler">Tümü</a>
    <?php foreach ($DURUMLAR as $k => $ad): ?><a class="btn kucuk <?= $filtre === $k ? '' : 'ikinci' ?>" href="?s=talepler&d=<?= h($k) ?>"><?= h($ad) ?></a><?php endforeach; ?>
    <a class="btn kucuk ikinci" style="margin-left:auto" href="?csv=1">CSV indir</a></div>
  <?php $say = 0; foreach ($talepler as $t): if ($filtre !== '' && $t['durum'] !== $filtre) continue; $say++;
    $tel = preg_replace('/\D/', '', $t['telefon']); if (strlen($tel) === 11 && $tel[0] === '0') $tel = '90' . substr($tel, 1); ?>
  <article class="talep">
    <header><div><h3><?= h($t['firma']) ?> <span class="soluk" style="font-weight:400">· <?= h($t['ad']) ?></span></h3><div class="meta"><span><?= h(date('d.m.Y H:i', strtotime($t['zaman']))) ?></span><a href="tel:<?= h($t['telefon']) ?>"><?= h($t['telefon']) ?></a><a href="mailto:<?= h($t['eposta']) ?>"><?= h($t['eposta']) ?></a><?php if ($t['personel']): ?><span><?= h($t['personel']) ?> personel</span><?php endif; ?></div></div>
      <div><span class="rozet <?= h($t['durum']) ?>"><?= h($DURUMLAR[$t['durum']] ?? $t['durum']) ?></span></div></header>
    <?php if ($t['mesaj']): ?><p style="margin:0">“<?= nl2br(h($t['mesaj'])) ?>”</p><?php endif; ?>
    <div class="meta"><?php if ($tel): ?><a href="https://wa.me/<?= h($tel) ?>" target="_blank" rel="noopener">WhatsApp’tan yaz ↗</a><?php endif; ?><?php if ($t['kaynak']): ?><span>Kaynak: <?= h($t['kaynak']) ?></span><?php endif; ?><span>Sayfa: <?= h($t['sayfa']) ?></span></div>
    <form method="post"><input type="hidden" name="csrf" value="<?= h(ts_csrf()) ?>"><input type="hidden" name="id" value="<?= h($t['id']) ?>">
      <label>Durum<select name="durum"><?php foreach ($DURUMLAR as $k => $ad): ?><option value="<?= h($k) ?>"<?= $t['durum'] === $k ? ' selected' : '' ?>><?= h($ad) ?></option><?php endforeach; ?></select></label>
      <label>Notunuz<input name="not" value="<?= h($t['not']) ?>" maxlength="600" placeholder="Görüşme notu"></label>
      <div style="display:flex;gap:6px"><button class="btn kucuk" name="islem" value="talep_durum">Kaydet</button><button class="sil" name="islem" value="talep_sil" onclick="return confirm('Bu talep silinsin mi?')">Sil</button></div></form>
  </article>
  <?php endforeach; if (!$say): ?><div class="kart"><b>Henüz talep yok</b><span class="soluk">Siteden gelen deneme talepleri burada listelenir. Yeni talep gelince e-posta bildirimi için “Site ayarları → İletişim” bölümüne e-posta yazın.</span></div><?php endif; ?>

<?php elseif ($sekme === 'ayarlar'):
  $kt = mb_strlen($A['baslik']); $ka = mb_strlen($A['aciklama']);
  $kontrol = [
    [$kt >= 30 && $kt <= 65, "Sayfa başlığı $kt karakter (ideal 30–65)"],
    [$ka >= 110 && $ka <= 165, "Açıklama $ka karakter (ideal 110–160)"],
    [$A['alan_adi'] !== '', 'Site adresi (alan adı) girilmiş: arama motorlarına tek ve doğru adres bildirilir'],
    [is_file(TS_KOK . '/' . ltrim($A['og_gorsel'], '/')) || preg_match('~^https?://~', $A['og_gorsel']), 'Paylaşım görseli (1200×630) var'],
    [$A['ga4'] !== '' || $A['gtm'] !== '', 'Google Analytics / Etiket Yöneticisi tanımlı'],
    [$A['gsc'] !== '', 'Google Search Console doğrulaması girilmiş'],
    [$A['robots_index'] === '1', 'Arama motorlarına açık (noindex kapalı)'],
    [$A['whatsapp'] !== '', 'WhatsApp numarası girilmiş'],
    [$A['telefon'] !== '' || $A['eposta'] !== '', 'Telefon ya da e-posta girilmiş (güven ve yapılandırılmış veri için)'],
  ]; ?>
  <div class="kart"><h2>SEO kontrol listesi</h2><div class="kontrol"><?php foreach ($kontrol as $c): ?><div class="<?= $c[0] ? 'ok' : 'uy' ?>"><i><?= $c[0] ? '✓' : '!' ?></i><span><?= h($c[1]) ?></span></div><?php endforeach; ?></div>
    <p class="soluk" style="margin:0">Site haritası: <code><?= h(ts_url('sitemap.xml')) ?></code> · robots.txt: <code><?= h(ts_url('robots.txt')) ?></code></p></div>
  <form method="post" class="kart" style="padding:0;border:0;background:transparent"><input type="hidden" name="csrf" value="<?= h(ts_csrf()) ?>"><input type="hidden" name="islem" value="ayar_kaydet">

  <details class="grup" open><summary>Genel ve SEO</summary><div class="ic">
    <label>Marka / site adı<input name="marka" value="<?= h($A['marka']) ?>"></label>
    <label>Sayfa başlığı (title) <span class="say" data-say="baslik" data-min="30" data-max="65"></span><input name="baslik" value="<?= h($A['baslik']) ?>" maxlength="120" data-sayac><small>Google sonuçlarında mavi başlık olarak görünür. Ana anahtar kelimeyi başa yazın.</small></label>
    <label>Açıklama (meta description) <span class="say" data-say="aciklama" data-min="110" data-max="160"></span><textarea name="aciklama" rows="3" maxlength="300" data-sayac><?= h($A['aciklama']) ?></textarea><small>Sonuçların altındaki gri metin. Faydayı ve “15 gün ücretsiz” çağrısını içersin.</small></label>
    <label>Anahtar kelimeler<textarea name="anahtar" rows="3"><?= h($A['anahtar']) ?></textarea><small>Virgülle ayırın. Google artık bunu puanlamıyor; yine de bazı arama motorları ve araçlar okur. Asıl etki sayfadaki metinlerdendir.</small></label>
    <label>Site adresi (alan adı)<input name="alan_adi" value="<?= h($A['alan_adi']) ?>" placeholder="https://www.siteniz.com"><small>Canonical, Open Graph, sitemap ve yapılandırılmış veride kullanılır. Boş bırakırsanız o an açılan adres kullanılır.</small></label>
    <label>Paylaşım görseli (Open Graph)<input name="og_gorsel" value="<?= h($A['og_gorsel']) ?>"><small>WhatsApp, LinkedIn ve Google’da görünen görsel. Site klasöründeki yol (assets/img/og.png) ya da tam adres. Boyut 1200×630.</small></label>
    <label>Deneme süresi (gün)<input type="number" name="deneme_gun" min="1" max="90" value="<?= h($A['deneme_gun']) ?>"></label>
    <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="robots_index" value="1"<?= $A['robots_index'] === '1' ? ' checked' : '' ?>> Arama motorları siteyi dizine eklesin (kapalıysa noindex + robots Disallow)</label>
  </div></details>

  <details class="grup" open><summary>Google ve izleme</summary><div class="ic">
    <label>Google Analytics 4 ölçüm kimliği<input name="ga4" value="<?= h($A['ga4']) ?>" placeholder="G-XXXXXXXXXX"><small>Analytics → Yönetici → Veri akışları → Web akışı içinde “Ölçüm kimliği”. Girince tüm sayfalara kod kendiliğinden eklenir.</small></label>
    <label>Google Etiket Yöneticisi (isteğe bağlı)<input name="gtm" value="<?= h($A['gtm']) ?>" placeholder="GTM-XXXXXXX"></label>
    <label>Google Search Console doğrulama kodu<input name="gsc" value="<?= h($A['gsc']) ?>" placeholder="HTML etiketi yöntemindeki content değeri"><small>Search Console → Mülk ekle → “HTML etiketi”. Tüm etiketi yapıştırabilirsiniz, kodu kendisi ayıklar.</small></label>
    <label>Bing Webmaster doğrulama kodu<input name="bing" value="<?= h($A['bing']) ?>"></label>
    <label>Meta (Facebook) Piksel kimliği<input name="meta_pixel" value="<?= h($A['meta_pixel']) ?>" placeholder="Yalnızca rakamlar"></label>
    <label style="display:flex;gap:10px;align-items:center"><input type="checkbox" name="cerez_bandi" value="1"<?= $A['cerez_bandi'] === '1' ? ' checked' : '' ?>> Çerez bandı göster: Analytics yalnızca ziyaretçi “Kabul et” derse çalışsın (KVKK için önerilir)</label>
  </div></details>

  <details class="grup" open><summary>İletişim ve WhatsApp</summary><div class="ic">
    <div class="iki"><label>WhatsApp numarası<input name="whatsapp" value="<?= h($A['whatsapp']) ?>" placeholder="905321112233"><small>Ülke koduyla, boşluksuz. 05321112233 yazarsanız 90 eklenir.</small></label>
    <label>Telefon (sitede görünür)<input name="telefon" value="<?= h($A['telefon']) ?>" placeholder="0212 000 00 00"></label></div>
    <label>WhatsApp hazır mesajı<textarea name="whatsapp_mesaj" rows="2"><?= h($A['whatsapp_mesaj']) ?></textarea></label>
    <div class="iki"><label>Site e-postası<input type="email" name="eposta" value="<?= h($A['eposta']) ?>"></label>
    <label>Yeni talep bildirimi gidecek e-posta<input type="email" name="talep_eposta" value="<?= h($A['talep_eposta']) ?>"><small>Boşsa site e-postası kullanılır. Sunucuda e-posta gönderimi kapalıysa talepler yine bu panelde görünür.</small></label></div>
    <div class="iki"><label>Adres<input name="adres" value="<?= h($A['adres']) ?>"></label><label>Şehir<input name="sehir" value="<?= h($A['sehir']) ?>"></label></div>
  </div></details>

  <details class="grup"><summary>Sosyal medya</summary><div class="ic"><div class="iki">
    <label>Instagram<input name="instagram" value="<?= h($A['instagram']) ?>" placeholder="https://instagram.com/…"></label><label>LinkedIn<input name="linkedin" value="<?= h($A['linkedin']) ?>" placeholder="https://linkedin.com/company/…"></label>
    <label>X (Twitter)<input name="x" value="<?= h($A['x']) ?>"></label><label>YouTube<input name="youtube" value="<?= h($A['youtube']) ?>"></label><label>Facebook<input name="facebook" value="<?= h($A['facebook']) ?>"></label></div></div></details>

  <details class="grup"><summary>Gelişmiş: özel kod ekleme</summary><div class="ic">
    <label>&lt;head&gt; içine eklenecek kod<textarea name="ek_head" rows="4" spellcheck="false" style="font-family:monospace"><?= h($A['ek_head']) ?></textarea><small>Başka bir doğrulama etiketi ya da izleme kodu için. Yalnızca güvendiğiniz kodu yapıştırın.</small></label>
    <label>&lt;/body&gt; öncesine eklenecek kod<textarea name="ek_body" rows="4" spellcheck="false" style="font-family:monospace"><?= h($A['ek_body']) ?></textarea></label>
  </div></details>
  <div><button class="btn">Ayarları kaydet</button></div></form>
  <script>document.querySelectorAll('[data-sayac]').forEach(function(i){var s=document.querySelector('[data-say="'+i.name+'"]');function g(){var n=i.value.length,mn=+s.dataset.min,mx=+s.dataset.max;s.textContent=n+' karakter';s.className='say '+(n>=mn&&n<=mx?'iyi':'kotu')}i.addEventListener('input',g);g()})</script>

<?php else: ?>
  <form method="post" class="kart" style="max-width:520px"><h2>Şifre değiştir</h2><input type="hidden" name="csrf" value="<?= h(ts_csrf()) ?>"><input type="hidden" name="islem" value="sifre">
    <label>Mevcut şifre<input type="password" name="mevcut" required autocomplete="current-password"></label><label>Yeni şifre (en az 10 karakter)<input type="password" name="yeni" required minlength="10" autocomplete="new-password"></label><label>Yeni şifre (tekrar)<input type="password" name="yeni2" required autocomplete="new-password"></label>
    <div><button class="btn">Şifreyi değiştir</button></div></form>
<?php endif; ?>
</main></body></html>
