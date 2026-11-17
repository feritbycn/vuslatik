<?php
// ============================================================
// oturum_islem.php — Kurum girişi JSON uç noktası (kendi kendine kayıt YOK; kullanıcıları kurum yetkilisi ekler)
//   POST giris (kurum, kullanici, sifre) · POST sifre_belirle (zorunlu ilk şifre değişimi) · POST sifre_degistir (oturum açıkken)
//   Güvenlik: CSRF, IP+hesap bazlı deneme sınırı, sabit süreli doğrulama, oturum kimliği yenileme, genel hata iletisi
// ============================================================
require_once __DIR__ . '/config/baglan.php';
$islem = (string)($_GET['islem'] ?? $_POST['islem'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') tb_json(['ok' => false, 'hata' => 'Geçersiz istek.'], 405);
if (!csrf_dogru()) tb_json(['ok' => false, 'hata' => 'Oturum doğrulaması başarısız. Sayfayı yenileyip tekrar deneyin.'], 419);

function oturum_sifre_kontrol($s)
{
    if (strlen($s) < 8) return 'Şifre en az 8 karakter olmalı.';
    if (!preg_match('/[A-Za-z]/', $s) || !preg_match('/\d/', $s)) return 'Şifre en az bir harf ve bir rakam içermeli.';
    return '';
}
function oturum_ac($db, $kurum, $kul)
{
    session_regenerate_id(true);
    $_SESSION['kurum_id'] = intval($kurum['id']);
    $_SESSION['kullanici_id'] = intval($kul['id']);
    $_SESSION['ad_soyad'] = (string)$kul['ad_soyad'];
    $_SESSION['tb_csrf'] = bin2hex(random_bytes(24));
    unset($_SESSION['bekleyen']);
    $db->kurum = intval($kurum['id']);
    $db->query("UPDATE kullanicilar SET son_giris = NOW() WHERE id = " . intval($kul['id']));
}

if ($islem === 'giris') {
    $kod = strtolower(trim((string)($_POST['kurum'] ?? '')));
    $u = strtolower(trim((string)($_POST['kullanici'] ?? '')));
    $sifre = (string)($_POST['sifre'] ?? '');
    if (TB_TEK_KURUM > 0) { $tk = pf_kurum($db, TB_TEK_KURUM); $kod = $tk ? $tk['kod'] : '__yok__'; }      // tek kurum / vekil sunucu: yalnızca kendi kurumu
    $genel = 'Kurum, kullanıcı adı veya şifre hatalı.';
    if (pf_ayar($db, 'bakim_modu') === '1') tb_json(['ok' => false, 'hata' => pf_ayar($db, 'bakim_mesaj')], 503);
    if ($u === '' || $sifre === '') tb_json(['ok' => false, 'hata' => 'Kullanıcı adı (veya e-posta) ve şifre gerekli.']);
    $anahtar = ($kod !== '' ? $kod : '*') . '|' . $u;
    $hak = max(3, intval(pf_ayar($db, 'giris_hak', '6')));
    if (pf_deneme_say($db, $anahtar) >= $hak) tb_json(['ok' => false, 'hata' => 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.'], 429);

    $kurum = null; $kul = null;
    if ($kod === '') {                                              // kurum belirtilmedi: kullanıcı adı / e-postadan kurumu bul
        $uyan = [];
        foreach (pf_kullanici_bul($db, $u) as $c) if (password_verify($sifre, $c['kul']['sifre'])) $uyan[] = $c;
        if (count($uyan) > 1) tb_json(['ok' => false, 'kurum_gerekli' => true, 'kurumlar' => array_map(function ($c) { return ['kod' => $c['kurum']['kod'], 'ad' => $c['kurum']['ad']]; }, $uyan), 'hata' => 'Bu bilgilerle birden fazla kurumda hesap bulundu. Lütfen kurumunuzu seçin.']);
        if (count($uyan) === 1) { $kurum = $uyan[0]['kurum']; $kul = $uyan[0]['kul']; }
    } else {
        $kurum = pf_kurum_kod($db, $kod);
        if ($kurum) {
            $db->kurum = intval($kurum['id']);
            $st = $db->prepare("SELECT id, ad_soyad, sifre, aktif, sifre_degistir FROM kullanicilar WHERE kullanici_adi IN (?, ?) OR (email <> '' AND LOWER(email) = ?) LIMIT 1");
            if ($st) { $u2 = str_replace('.', '', $u); $st->bind_param('sss', $u, $u2, $u); $st->execute(); $r = $st->get_result(); $kul = $r ? $r->fetch_assoc() : null; }
            $db->kurum = 0;
        }
    }
    $hash = $kul ? $kul['sifre'] : '$2y$10$6yGgQl2dL8e08n1MivSXje1JLngchwoJfO2iyw8d2gvtJDvqT/G8y'; // kullanıcı yoksa da aynı sürede doğrula
    $dogru = password_verify($sifre, $hash);
    if (!$kurum || !$kul || !$dogru) {
        pf_deneme_ekle($db, $anahtar);
        tb_json(['ok' => false, 'hata' => $genel], 401);
    }
    if (!pf_kurum_kullanilabilir($kurum)) tb_json(['ok' => false, 'hata' => pf_lisans_mesaj($kurum)], 403);
    if (!$TB_VEKIL_KURUM && ($uzak = pf_vekil_uzak_mi($db, $kurum['id'])) !== false) tb_json(['ok' => false, 'hata' => 'Bu kurum başka bir sunucuda çalışıyor' . ($uzak !== '' ? ': ' . $uzak : '') . '. Lütfen kurumun kendi adresinden giriş yapın.'], 403);
    if (intval($kul['aktif']) !== 1) tb_json(['ok' => false, 'hata' => 'Hesabınız pasif durumda. Kurum yöneticinizle görüşün.'], 403);
    pf_deneme_temizle($db, $anahtar);
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $db->kurum = intval($kurum['id']); $yeni = password_hash($sifre, PASSWORD_DEFAULT);
        $st = $db->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?"); $kid0 = intval($kul['id']); $st->bind_param('si', $yeni, $kid0); $st->execute(); $db->kurum = 0;
    }
    if (intval($kul['sifre_degistir']) === 1) {                    // geçici şifreyle ilk giriş → önce yeni şifre
        session_regenerate_id(true);
        $_SESSION['bekleyen'] = ['kurum' => intval($kurum['id']), 'kullanici' => intval($kul['id']), 'ad' => (string)$kul['ad_soyad'], 'zaman' => time(), 'hatirla' => !empty($_POST['hatirla'])];
        $_SESSION['tb_csrf'] = bin2hex(random_bytes(24));
        tb_json(['ok' => true, 'degistir' => true, 'csrf' => $_SESSION['tb_csrf'], 'ad' => $kul['ad_soyad']]);
    }
    oturum_ac($db, $kurum, $kul);
    if (!empty($_POST['hatirla'])) pf_hatirla_olustur($db, $kurum['id'], $kul['id']); else pf_hatirla_sil($db);
    pf_log($db, 'giris', $kurum['kod'] . '/' . $u, $kurum['id']);
    $don = preg_match('/^[a-z0-9_]+\.php$/', (string)($_POST['don'] ?? '')) ? $_POST['don'] : 'ik.php';
    tb_json(['ok' => true, 'git' => $don, 'csrf' => $_SESSION['tb_csrf']]);
}

if ($islem === 'sifre_belirle') {
    $b = $_SESSION['bekleyen'] ?? null;
    if (!$b || time() - intval($b['zaman']) > 900) tb_json(['ok' => false, 'hata' => 'Süre doldu, lütfen yeniden giriş yapın.'], 401);
    $yeni = (string)($_POST['yeni'] ?? '');
    if (($h = oturum_sifre_kontrol($yeni)) !== '') tb_json(['ok' => false, 'hata' => $h]);
    if ($yeni !== (string)($_POST['yeni2'] ?? '')) tb_json(['ok' => false, 'hata' => 'Şifreler aynı değil.']);
    $kurum = pf_kurum($db, $b['kurum']);
    if (!$kurum || !pf_kurum_kullanilabilir($kurum)) tb_json(['ok' => false, 'hata' => 'Kurum bulunamadı.'], 403);
    $db->kurum = intval($kurum['id']);
    $hash = password_hash($yeni, PASSWORD_DEFAULT); $kid = intval($b['kullanici']);
    $st = $db->prepare("UPDATE kullanicilar SET sifre = ?, sifre_degistir = 0 WHERE id = ? AND aktif = 1");
    $st->bind_param('si', $hash, $kid);
    $st->execute();
    $kul = ['id' => $kid, 'ad_soyad' => $b['ad']];
    oturum_ac($db, $kurum, $kul);
    if (!empty($b['hatirla'])) pf_hatirla_olustur($db, $kurum['id'], $kid);
    pf_log($db, 'ilk_sifre', $kurum['kod'], $kurum['id']);
    tb_json(['ok' => true, 'git' => 'ik.php', 'csrf' => $_SESSION['tb_csrf']]);
}

if ($islem === 'sifre_degistir') {
    if (TB_KURUM_ID <= 0 || empty($_SESSION['kullanici_id'])) tb_json(['ok' => false, 'hata' => 'Oturum süresi doldu.'], 401);
    $uid = intval($_SESSION['kullanici_id']);
    $eski = (string)($_POST['eski'] ?? ''); $yeni = (string)($_POST['yeni'] ?? '');
    $sdAnahtar = 'sd|' . TB_KURUM_ID . '|' . $uid;
    if (pf_deneme_say($db, $sdAnahtar) >= 6) tb_json(['ok' => false, 'hata' => 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.'], 429);
    $st = $db->prepare("SELECT sifre FROM kullanicilar WHERE id = ?"); $st->bind_param('i', $uid); $st->execute();
    $r = $st->get_result()->fetch_row();
    if (!$r || !password_verify($eski, $r[0])) { pf_deneme_ekle($db, $sdAnahtar); tb_json(['ok' => false, 'hata' => 'Mevcut şifre hatalı.']); }
    pf_deneme_temizle($db, $sdAnahtar);
    if (($h = oturum_sifre_kontrol($yeni)) !== '') tb_json(['ok' => false, 'hata' => $h]);
    $hash = password_hash($yeni, PASSWORD_DEFAULT);
    $st = $db->prepare("UPDATE kullanicilar SET sifre = ?, sifre_degistir = 0 WHERE id = ?"); $st->bind_param('si', $hash, $uid); $st->execute();
    pf_hatirla_kisi_sil($db, TB_KURUM_ID, $uid);                    // başka cihazlardaki "beni hatırla" oturumları kapanır
    tb_json(['ok' => true]);
}
tb_json(['ok' => false, 'hata' => 'Bilinmeyen işlem.'], 400);
