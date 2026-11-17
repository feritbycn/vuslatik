<?php
// ============================================================
// platform_ortak.php — Platform katmanı: şema (pf_ tabloları), site ayarları, kurum ve kullanıcı yardımcıları
//   Platform tabloları ön eksizdir (pf_...), kurum tabloları k{no}_ ön ekiyle VDB tarafından ayrılır.
// ============================================================
if (!defined('PF_ORTAK')) {
define('PF_ORTAK', true);

/** Site ayarları: anahtar => [varsayılan, grup, etiket, tür] (platform panelindeki "Site ayarları" formu bunlardan çizilir) */
function pf_ayar_tanim()
{
    return [
        'site_adi'       => ['Turkuaz Bileşim İK', 'genel', 'Site / ürün adı', 'metin'],
        'slogan'         => ['Kurumunuzun insan kaynakları, tek yerde.', 'genel', 'Slogan', 'metin'],
        'site_url'       => ['', 'genel', 'Site adresi (https://…)', 'metin'],
        'destek_mail'    => ['', 'genel', 'Destek e-postası', 'metin'],
        'destek_tel'     => ['', 'genel', 'Destek telefonu', 'metin'],
        'altbilgi'       => ['© ' . date('Y') . ' Tüm hakları saklıdır.', 'genel', 'Alt bilgi yazısı', 'metin'],
        'logo'           => ['', 'gorsel', 'Logo', 'dosya'],
        'favicon'        => ['', 'gorsel', 'Favicon (PNG / ICO / SVG)', 'dosya'],
        'og_gorsel'      => ['', 'gorsel', 'Paylaşım görseli (1200×630)', 'dosya'],
        'tema_renk'      => ['#6366f1', 'gorsel', 'Ana renk', 'renk'],
        'seo_baslik'     => ['Turkuaz Bileşim İK — Personel, izin ve giriş-çıkış yönetimi', 'seo', 'SEO başlığı (title)', 'metin'],
        'seo_aciklama'   => ['İzin onay akışı, personel kartları, giriş-çıkış kayıtları ve bildirimler tek panelde.', 'seo', 'Meta açıklama (description)', 'uzun'],
        'seo_anahtar'    => ['', 'seo', 'Anahtar kelimeler (virgülle)', 'metin'],
        'seo_robots'     => ['noindex,nofollow', 'seo', 'Robots', 'secim:index,follow|noindex,nofollow|index,nofollow|noindex,follow'],
        'seo_canonical'  => ['', 'seo', 'Canonical adres', 'metin'],
        'seo_yazar'      => ['', 'seo', 'Yazar / yayıncı', 'metin'],
        'analitik_kod'   => ['', 'seo', 'Google Analytics / Tag ölçüm kimliği (G-XXXX)', 'metin'],
        'ek_head'        => ['', 'seo', 'Ek <head> kodu (doğrulama etiketleri vb.)', 'uzun'],
        'giris_baslik'   => ['Hoş geldiniz', 'giris', 'Giriş sayfası başlığı', 'metin'],
        'giris_alt'      => ['Hesabınıza giriş yapmak için kurumunuzu seçin.', 'giris', 'Giriş sayfası alt yazısı', 'metin'],
        'giris_efekt'    => ['aurora', 'giris', 'Arka plan efekti', 'secim:aurora|parcacik|dalga|izgara|yok'],
        'giris_renk2'    => ['#06b6d4', 'giris', 'İkinci renk (efektler)', 'renk'],
        'giris_ozellik'  => ["İzin onay akışı\nGiriş-çıkış takibi\nSMS ve e-posta bildirimleri\nKurum bazlı yetkilendirme", 'giris', 'Giriş sayfası özellik listesi (her satır bir madde)', 'uzun'],
        'giris_intro'    => ['1', 'giris', 'İlk ziyarette kısa tanıtım animasyonunu göster', 'onay'],
        'kurum_secimi'   => ['otomatik', 'giris', 'Kurum seçimi (otomatik: kullanıcı adı/e-postadan bulunur)', 'secim:otomatik|liste|kod'],
        'bakim_modu'     => ['0', 'sistem', 'Bakım modu (kurum girişlerini durdur)', 'onay'],
        'bakim_mesaj'    => ['Sistem kısa süreliğine bakımda. Lütfen daha sonra tekrar deneyin.', 'sistem', 'Bakım mesajı', 'metin'],
        'giris_hak'      => ['6', 'sistem', 'Hatalı giriş hakkı (15 dk içinde)', 'metin'],
        'yedek_aralik'   => ['7', 'yedek', 'Otomatik yedek aralığı (gün, 0 = kapalı)', 'metin'],
        'yedek_saklama'  => ['8', 'yedek', 'Kurum başına saklanacak otomatik yedek sayısı', 'metin'],
        'yedek_son_kontrol' => ['0', 'yedek', 'Son zamanlayıcı kontrolü', 'metin'],
        'iletisim_eposta' => ['', 'yedek', 'Yedek talepleri / destek e-postası', 'metin'],
        'iletisim_telefon' => ['', 'yedek', 'Yedek talepleri SMS telefonu', 'metin'],
        'guncelleme_anahtari' => ['', 'bakim', 'Güncelleme paketi anahtarı (diğer kurulumlar bu anahtarla paket çeker)', 'metin'],
        'guncelleme_kaynak'   => ['', 'bakim', 'Güncelleme kaynağı adresi', 'metin'],
        'guncelleme_kaynak_anahtar' => ['', 'bakim', 'Güncelleme kaynağı anahtarı', 'metin'],
    ];
}

function pf_sema($db)
{
    static $tamam = false;
    if ($tamam) return;
    $tamam = true;
    if (@$db->query("SELECT 1 FROM pf_kurumlar LIMIT 1")) return;
    $s = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->query("CREATE TABLE IF NOT EXISTS pf_ayarlar (anahtar VARCHAR(60) NOT NULL, deger MEDIUMTEXT NULL, PRIMARY KEY (anahtar)) $s");
    $db->query("CREATE TABLE IF NOT EXISTS pf_kurumlar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ad VARCHAR(150) NOT NULL,
        kod VARCHAR(40) NOT NULL,
        logo VARCHAR(255) NOT NULL DEFAULT '',
        renk VARCHAR(9) NOT NULL DEFAULT '#6366f1',
        durum VARCHAR(12) NOT NULL DEFAULT 'aktif',
        kullanici_siniri INT NOT NULL DEFAULT 0,
        yetkili_id INT NULL,
        yetkili_ad VARCHAR(120) NOT NULL DEFAULT '',
        telefon VARCHAR(30) NOT NULL DEFAULT '',
        email VARCHAR(150) NOT NULL DEFAULT '',
        notlar TEXT NULL,
        olusturma DATETIME NOT NULL,
        PRIMARY KEY (id), UNIQUE KEY uq_kurum_kod (kod)
    ) $s");
    $db->query("CREATE TABLE IF NOT EXISTS pf_yoneticiler (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_adi VARCHAR(60) NOT NULL,
        sifre VARCHAR(255) NOT NULL,
        ad_soyad VARCHAR(120) NOT NULL DEFAULT '',
        email VARCHAR(150) NOT NULL DEFAULT '',
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        son_giris DATETIME NULL,
        PRIMARY KEY (id), UNIQUE KEY uq_pf_yon (kullanici_adi)
    ) $s");
    $db->query("CREATE TABLE IF NOT EXISTS pf_hiz (anahtar CHAR(40) NOT NULL, pencere INT NOT NULL, sayi INT NOT NULL DEFAULT 0, PRIMARY KEY (anahtar, pencere), KEY ix_p (pencere)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS pf_giris_deneme (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip VARCHAR(45) NOT NULL,
        anahtar VARCHAR(120) NOT NULL DEFAULT '',
        zaman DATETIME NOT NULL,
        PRIMARY KEY (id), KEY ix_deneme (ip, zaman), KEY ix_deneme_a (anahtar, zaman)
    ) $s");
    $db->query("CREATE TABLE IF NOT EXISTS pf_kayit (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        zaman DATETIME NOT NULL,
        kim VARCHAR(80) NOT NULL DEFAULT '',
        kurum_id INT NULL,
        olay VARCHAR(60) NOT NULL,
        ayrinti VARCHAR(255) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        PRIMARY KEY (id), KEY ix_kayit (zaman)
    ) $s");
}

// ── Site ayarları ──
function pf_ayarlar($db, $yenile = false)
{
    static $c = null;
    if ($c !== null && !$yenile) return $c;
    $c = [];
    foreach (pf_ayar_tanim() as $k => $t) $c[$k] = $t[0];
    $q = @$db->query("SELECT anahtar, deger FROM pf_ayarlar");
    if ($q) while ($r = $q->fetch_row()) if (isset($c[$r[0]])) $c[$r[0]] = (string)$r[1];
    return $c;
}
function pf_ayar($db, $k, $varsayilan = '') { $a = pf_ayarlar($db); return $a[$k] ?? $varsayilan; }
function pf_ayar_yaz($db, $k, $v)
{
    if (!isset(pf_ayar_tanim()[$k])) return false;
    $st = $db->prepare("INSERT INTO pf_ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)");
    $st->bind_param('ss', $k, $v);
    $ok = $st->execute();
    pf_ayarlar($db, true);
    return $ok;
}

// ── Kurumlar ──
define('PF_LISANS_EK_GUN', 5);      // süre bittikten sonra askıya alınmadan önce tanınan ek gün
define('PF_LISANS_UYARI_GUN', 5);   // bitişe bu kadar gün kala uyarı başlar

/**
 * Sitede görünecek marka/imza adı: kurumda "imzayı göster" açıksa platform site adı (Turkuaz Bileşim),
 * kapalıysa kurumun kendi adı. $kurum verilmezse oturumdaki kurum kullanılır.
 */
function pf_imza_adi($db, $kurum = null)
{
    if ($kurum === null) $kurum = $GLOBALS['KURUM'] ?? null;
    $site = $db ? pf_ayar($db, 'site_adi', 'Turkuaz Bileşim İK') : 'Turkuaz Bileşim';
    if (is_array($kurum) && array_key_exists('imza_goster', $kurum) && intval($kurum['imza_goster']) === 0 && trim((string)($kurum['ad'] ?? '')) !== '') return (string)$kurum['ad'];
    return $site;
}

/** pf_kurumlar'a lisans alanlarını (eski kurulumlarda) ekler */
function pf_lisans_sema($db)
{
    static $tamam = false;
    if ($tamam) return;
    $tamam = true;
    if (!@$db->query("SELECT imza_goster FROM pf_kurumlar LIMIT 0") && @$db->query("SELECT lisans_tur FROM pf_kurumlar LIMIT 0"))
        @$db->query("ALTER TABLE pf_kurumlar ADD COLUMN imza_goster TINYINT(1) NOT NULL DEFAULT 1");
    if (@$db->query("SELECT lisans_tur FROM pf_kurumlar LIMIT 0")) return;
    @$db->query("ALTER TABLE pf_kurumlar ADD COLUMN imza_goster TINYINT(1) NOT NULL DEFAULT 1");
    @$db->query("ALTER TABLE pf_kurumlar ADD COLUMN lisans_tur VARCHAR(12) NOT NULL DEFAULT 'sinirsiz', ADD COLUMN lisans_baslangic DATE NULL, ADD COLUMN lisans_bitis DATE NULL");
}

/**
 * Kurumun kullanım süresi durumu.
 * durum: sinirsiz | aktif | yaklasiyor (bitişe ≤5 gün) | ek_sure (bitti, 5 günlük ek süre) | askida (ek süre de bitti)
 */
function pf_lisans($k)
{
    $tur = (string)($k['lisans_tur'] ?? 'sinirsiz'); $bitis = $k['lisans_bitis'] ?? null;
    $o = ['tur' => $tur, 'bitis' => $bitis, 'kalan' => null, 'ek_kalan' => null, 'durum' => 'sinirsiz'];
    if ($tur === 'sinirsiz' || !$bitis || $bitis === '0000-00-00') return $o;
    $kalan = (int)round((strtotime($bitis) - strtotime(date('Y-m-d'))) / 86400);
    $o['kalan'] = $kalan;
    if ($kalan > PF_LISANS_UYARI_GUN) $o['durum'] = 'aktif';
    elseif ($kalan >= 0) $o['durum'] = 'yaklasiyor';
    elseif ($kalan >= -PF_LISANS_EK_GUN) { $o['durum'] = 'ek_sure'; $o['ek_kalan'] = PF_LISANS_EK_GUN + $kalan; }
    else $o['durum'] = 'askida';
    return $o;
}
/** Kurum giriş yapabilir mi? (elle askıya alınmamış ve süresi/ek süresi bitmemiş) */
function pf_kurum_kullanilabilir($k) { return $k && $k['durum'] === 'aktif' && pf_lisans($k)['durum'] !== 'askida' && pf_aktivasyon_engel($k) === ''; }
/** Aktivasyon kodlu paket kuralları. '' = engel yok; aksi halde kullanıcıya gösterilecek neden.
 *  Ana sunucuda: paket olarak dağıtılan kurum burada çalışmaz (verisi müşteri sunucusundadır).
 *  Müşteri kurulumunda: kod girilmemiş / lisans geçersizse çalışmaz. */
function pf_aktivasyon_engel($k)
{
    static $tek = null; if ($tek === null) $tek = is_file(__DIR__ . '/config/kurum.id');
    if (!$tek) return !empty($k['paket_kodlu']) ? 'Bu kurum kendi sunucusunda (aktivasyon kodlu paket) çalışır; bu adresten açılmaz.' : '';
    $f = __DIR__ . '/aktivasyon_ortak.php'; if (!is_file($f)) return is_file(__DIR__ . '/config/aktivasyon.anahtar.php') ? 'Aktivasyon dosyaları eksik; kurulum çalışmaz.' : '';
    require_once $f; $d = akt_durum(); return $d['ok'] ? '' : ($d['mesaj'] !== '' ? $d['mesaj'] : 'Aktivasyon gerekli.');
}
function pf_lisans_mesaj($k)
{
    $ae = pf_aktivasyon_engel($k); if ($ae !== '') return $ae;
    $l = pf_lisans($k);
    if ($l['durum'] === 'askida') return 'Kullanım süreniz sona erdi ve hesabınız askıya alındı. Devam etmek için lütfen yöneticinizle iletişime geçip süreyi yenileyin.';
    return 'Bu kurumun hesabı askıya alınmış. Lütfen yöneticiyle iletişime geçin.';
}
/** Form değerinden (sinirsiz|deneme|aylik|yillik|ozel) yeni lisans alanlarını hesaplar; $mevcut verilirse süre bitiş tarihinden (gelecekteyse) uzatılır */
function pf_lisans_hesapla($secim, $ozelTarih, $mevcut = null)
{
    $bugun = date('Y-m-d');
    if ($secim === 'sinirsiz') return ['sinirsiz', null, null];
    $baz = $bugun;
    if ($mevcut && !empty($mevcut['lisans_bitis']) && $mevcut['lisans_bitis'] > $bugun) $baz = $mevcut['lisans_bitis'];
    if ($secim === 'deneme') return ['deneme', $bugun, date('Y-m-d', strtotime($bugun . ' +15 days'))];
    if ($secim === 'aylik') return ['aylik', $bugun, date('Y-m-d', strtotime($baz . ' +1 month'))];
    if ($secim === 'yillik') return ['yillik', $bugun, date('Y-m-d', strtotime($baz . ' +1 year'))];
    if ($secim === 'ozel') { $t = date_create_from_format('Y-m-d', (string)$ozelTarih); if (!$t) return null; return ['ozel', $bugun, $t->format('Y-m-d')]; }
    return null;
}

function pf_kurum($db, $id)
{
    $id = intval($id);
    $q = @$db->query("SELECT * FROM pf_kurumlar WHERE id = $id");
    return $q ? ($q->fetch_assoc() ?: null) : null;
}
function pf_kurum_kod($db, $kod)
{
    $st = $db->prepare("SELECT * FROM pf_kurumlar WHERE kod = ?");
    $st->bind_param('s', $kod);
    $st->execute();
    $r = $st->get_result();
    return $r ? ($r->fetch_assoc() ?: null) : null;
}
/** "Örnek Hastane A.Ş." → "ornek-hastane-a-s" */
function pf_kod_uret($ad)
{
    $t = ['ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'i̇' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u'];
    $s = strtr(mb_strtolower((string)$ad, 'UTF-8'), $t);
    $s = trim(preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    return substr($s !== '' ? $s : 'kurum', 0, 40);
}
function pf_sifre_uret($n = 10)
{
    $a = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $s = '';
    for ($i = 0; $i < $n; $i++) $s .= $a[random_int(0, strlen($a) - 1)];
    return $s;
}
function pf_log($db, $olay, $ayrinti = '', $kurum_id = null)
{
    $kim = !empty($_SESSION['pf_yonetici_ad']) ? 'platform:' . $_SESSION['pf_yonetici_ad'] : (!empty($_SESSION['ad_soyad']) ? (string)$_SESSION['ad_soyad'] : '');
    $ip = tb_istemci_ip(); $ay = mb_substr((string)$ayrinti, 0, 250, 'UTF-8'); $k = $kurum_id === null ? null : intval($kurum_id);
    $st = @$db->prepare("INSERT INTO pf_kayit (zaman, kim, kurum_id, olay, ayrinti, ip) VALUES (NOW(), ?, ?, ?, ?, ?)");
    if ($st) { $st->bind_param('sisss', $kim, $k, $olay, $ay, $ip); @$st->execute(); }
}

// ── "Beni hatırla": kalıcı oturum (sunucuda yalnızca özeti tutulan rastgele anahtar; 90 gün, her kullanımda uzar) ──
if (!defined('TB_HATIRLA_GUN')) define('TB_HATIRLA_GUN', 90);
function pf_hatirla_tablo($db)
{
    @$db->query("CREATE TABLE IF NOT EXISTS pf_beni_hatirla (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, secici CHAR(18) NOT NULL, dogrulayici CHAR(64) NOT NULL,
        kurum_id INT UNSIGNED NOT NULL, kullanici_id INT UNSIGNED NOT NULL, cihaz VARCHAR(160) NOT NULL DEFAULT '',
        olusturma DATETIME NOT NULL, son_kullanim DATETIME NOT NULL, bitis DATETIME NOT NULL,
        UNIQUE KEY u_secici (secici), KEY k_kul (kurum_id, kullanici_id), KEY k_bitis (bitis)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function pf_hatirla_cerez($deger, $sure)
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if (!headers_sent()) setcookie('tb_hatirla', $deger, ['expires' => $sure, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
    if ($sure > time()) $_COOKIE['tb_hatirla'] = $deger; else unset($_COOKIE['tb_hatirla']);
}
/** Bu cihaz için kalıcı oturum anahtarı üretir ve çerezi bırakır */
function pf_hatirla_olustur($db, $kurumId, $kullaniciId)
{
    try {
        pf_hatirla_tablo($db);
        @$db->query("DELETE FROM pf_beni_hatirla WHERE bitis < NOW()");
        $sec = bin2hex(random_bytes(9)); $dog = bin2hex(random_bytes(24)); $ozet = hash('sha256', $dog);
        $ua = mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160, 'UTF-8'); $k = intval($kurumId); $u = intval($kullaniciId);
        $st = $db->prepare("INSERT INTO pf_beni_hatirla (secici, dogrulayici, kurum_id, kullanici_id, cihaz, olusturma, son_kullanim, bitis) VALUES (?, ?, ?, ?, ?, NOW(), NOW(), DATE_ADD(NOW(), INTERVAL " . TB_HATIRLA_GUN . " DAY))");
        $st->bind_param('ssiis', $sec, $ozet, $k, $u, $ua); $st->execute();
        pf_hatirla_cerez($sec . ':' . $dog, time() + TB_HATIRLA_GUN * 86400);
    } catch (Throwable $e) {}
}
/** Çerezdeki anahtar geçerliyse [kurum_id, kullanici_id] döner (süreyi uzatır); değilse null */
function pf_hatirla_coz($db)
{
    $c = (string)($_COOKIE['tb_hatirla'] ?? '');
    if (!preg_match('/^([a-f0-9]{18}):([a-f0-9]{48})$/', $c, $m)) return null;
    try {
        $st = @$db->prepare("SELECT id, dogrulayici, kurum_id, kullanici_id FROM pf_beni_hatirla WHERE secici = ? AND bitis > NOW()");
        if (!$st) return null;
        $st->bind_param('s', $m[1]); $st->execute(); $r = $st->get_result()->fetch_assoc();
        if (!$r || !hash_equals($r['dogrulayici'], hash('sha256', $m[2]))) { if ($r) @$db->query("DELETE FROM pf_beni_hatirla WHERE id = " . intval($r['id'])); return null; }
        @$db->query("UPDATE pf_beni_hatirla SET son_kullanim = NOW(), bitis = DATE_ADD(NOW(), INTERVAL " . TB_HATIRLA_GUN . " DAY) WHERE id = " . intval($r['id']));
        pf_hatirla_cerez($c, time() + TB_HATIRLA_GUN * 86400);
        return [intval($r['kurum_id']), intval($r['kullanici_id'])];
    } catch (Throwable $e) { return null; }
}
/** Çıkış: bu cihazın anahtarını siler */
function pf_hatirla_sil($db)
{
    $c = (string)($_COOKIE['tb_hatirla'] ?? '');
    if (preg_match('/^([a-f0-9]{18}):/', $c, $m)) { try { $st = @$db->prepare("DELETE FROM pf_beni_hatirla WHERE secici = ?"); if ($st) { $st->bind_param('s', $m[1]); $st->execute(); } } catch (Throwable $e) {} }
    pf_hatirla_cerez('', time() - 3600);
}
/** Kişinin tüm cihazlardaki kalıcı oturumlarını kapatır (şifre sıfırlama / pasifleştirme) */
function pf_hatirla_kisi_sil($db, $kurumId, $kullaniciId)
{
    try { @$db->query("DELETE FROM pf_beni_hatirla WHERE kurum_id = " . intval($kurumId) . " AND kullanici_id = " . intval($kullaniciId)); } catch (Throwable $e) {}
}

// ── Giriş denemesi sınırı (IP + hesap bazlı) ──
function pf_deneme_say($db, $anahtar)
{
    $ip = tb_istemci_ip();
    @$db->query("DELETE FROM pf_giris_deneme WHERE zaman < (NOW() - INTERVAL 1 DAY)");
    $st = $db->prepare("SELECT COUNT(*) FROM pf_giris_deneme WHERE zaman > (NOW() - INTERVAL 15 MINUTE) AND (ip = ? OR anahtar = ?)");
    $st->bind_param('ss', $ip, $anahtar);
    $st->execute();
    $r = $st->get_result()->fetch_row();
    return intval($r[0] ?? 0);
}
function pf_deneme_ekle($db, $anahtar)
{
    $ip = tb_istemci_ip(); $anahtar = mb_substr($anahtar, 0, 120, 'UTF-8');
    $st = $db->prepare("INSERT INTO pf_giris_deneme (ip, anahtar, zaman) VALUES (?, ?, NOW())");
    $st->bind_param('ss', $ip, $anahtar);
    $st->execute();
}
function pf_deneme_temizle($db, $anahtar)
{
    $st = $db->prepare("DELETE FROM pf_giris_deneme WHERE anahtar = ?");
    $st->bind_param('s', $anahtar);
    $st->execute();
}


/** İstek hız sınırı (IP + ad başına, $sn saniyelik pencerede en çok $limit). true = geçebilir. */
function tb_hiz_sinir($db, $ad, $limit, $sn = 60)
{
    $o = $db->kurum; $db->kurum = 0;
    try {
        $pen = intdiv(time(), $sn); $k = sha1($ad . '|' . tb_istemci_ip());
        if (!$db->query("INSERT INTO pf_hiz (anahtar, pencere, sayi) VALUES ('$k', $pen, 1) ON DUPLICATE KEY UPDATE sayi = sayi + 1")) {   // tablo yoksa (güncelleme sonrası ilk istek) oluştur
            $db->query("CREATE TABLE IF NOT EXISTS pf_hiz (anahtar CHAR(40) NOT NULL, pencere INT NOT NULL, sayi INT NOT NULL DEFAULT 0, PRIMARY KEY (anahtar, pencere), KEY ix_p (pencere)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $db->query("INSERT INTO pf_hiz (anahtar, pencere, sayi) VALUES ('$k', $pen, 1) ON DUPLICATE KEY UPDATE sayi = sayi + 1");
        }
        $q = $db->query("SELECT sayi FROM pf_hiz WHERE anahtar = '$k' AND pencere = $pen"); $n = $q ? intval($q->fetch_row()[0]) : 0;
        if (mt_rand(1, 60) === 1) $db->query("DELETE FROM pf_hiz WHERE pencere < " . intdiv(time() - 3600, $sn));
    } catch (Throwable $e) { $db->kurum = $o; return true; }
    $db->kurum = $o; return $n <= $limit;
}
/** Hız sınırı aşıldıysa 429 ile durur. */
function tb_hiz_kapi($db)
{
    $d = basename((string)($_SERVER['SCRIPT_NAME'] ?? '')); $json = strpos($d, '_islem') !== false || stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'json') !== false;
    $kural = ['oturum_islem.php' => [120, 'oturum'], 'vekil_bildir.php' => [20, 'vbild'], 'sunucu_bildir.php' => [20, 'sbild'], 'giris.php' => [120, 'giris'], 'giris_ekran.php' => [120, 'gekran'], 'kurulum.php' => [30, 'kur'], 'platform.php' => [120, 'plat']];
    $ok = true;
    if (isset($kural[$d])) $ok = tb_hiz_sinir($db, $kural[$d][1], $kural[$d][0]);
    elseif (PHP_SAPI !== 'cli' && strpos($d, '_islem') !== false && isset($_SESSION)) {            // oturumlu uçlar: oturum başına dakikada en çok 600 istek
        $w = intdiv(time(), 60); if (($_SESSION['__hiz'][0] ?? 0) !== $w) $_SESSION['__hiz'] = [$w, 0]; $_SESSION['__hiz'][1]++; $ok = $_SESSION['__hiz'][1] <= 600;
    }
    if ($ok) return;
    http_response_code(429); header('Retry-After: 60'); header('Cache-Control: no-store');
    if ($json) { header('Content-Type: application/json; charset=utf-8'); echo json_encode(['ok' => false, 'hata' => 'Çok fazla istek. Bir dakika sonra tekrar deneyin.'], JSON_UNESCAPED_UNICODE); }
    else echo '<!doctype html><meta charset="utf-8"><title>Çok fazla istek</title><body style="font-family:system-ui;padding:40px"><h2>Çok fazla istek</h2><p>Lütfen bir dakika sonra tekrar deneyin.</p></body>';
    exit;
}

// ── Kurum şeması ve kullanıcı ──
/** Kurum tablolarını oluşturur ($db->kurum ayarlı olmalıdır). Güvenle tekrar çalıştırılabilir. */
function kurum_sema($db)
{
    if ($db->kurum <= 0) throw new RuntimeException('Kurum seçili değil.');
    $s = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->query("CREATE TABLE IF NOT EXISTS kullanicilar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_adi VARCHAR(60) NOT NULL,
        sifre VARCHAR(255) NOT NULL,
        ad_soyad VARCHAR(120) NOT NULL,
        rol VARCHAR(30) NOT NULL DEFAULT 'personel',
        gorev VARCHAR(100) NOT NULL DEFAULT '',
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        avatar_renk VARCHAR(20) NOT NULL DEFAULT '#6366f1',
        profil_resmi VARCHAR(255) NOT NULL DEFAULT '',
        telefon VARCHAR(30) NOT NULL DEFAULT '',
        email VARCHAR(150) NOT NULL DEFAULT '',
        dogum_tarihi DATE NULL,
        sifre_degistir TINYINT(1) NOT NULL DEFAULT 0,
        son_giris DATETIME NULL,
        olusturma DATETIME NOT NULL,
        PRIMARY KEY (id), UNIQUE KEY uq_kullanici_adi (kullanici_adi)
    ) $s");
    require_once TB_KOK . '/ik_ortak.php';
    require_once TB_KOK . '/giris_ortak.php';
    vy_tablo($db);
    ik_sema($db);
    giris_sema($db);
}
function kullanici_renk($ad)
{
    $r = ['#6366f1', '#8b5cf6', '#ec4899', '#f59e0b', '#10b981', '#0ea5e9', '#ef4444', '#14b8a6', '#f97316', '#3b82f6'];
    return $r[abs(crc32((string)$ad)) % count($r)];
}
/** Kullanıcı adı: küçük harf, rakam, . _ - (3–60) */
function kullanici_adi_gecerli($u) { return (bool)preg_match('/^[a-z0-9][a-z0-9._-]{2,59}$/', (string)$u); }
function kullanici_adi_oner($ad)
{
    $s = pf_kod_uret($ad);
    $s = str_replace('-', '', $s);                 // ad soyad birleşik: "Ali Veli" → aliveli (araya nokta konmaz)
    return strlen($s) >= 3 ? $s : $s . 'kullanici';
}
/** Kurumda kullanıcı ekler ($db->kurum ayarlı). Dönüş: ['ok'=>true,'id'=>..,'sifre'=>..] | ['ok'=>false,'hata'=>..] */
/** Tüm kurumlarda kullanıcı adı / e-posta çakışması arar. $haricKurum + $haricId: düzenlenen kaydın kendisi sayılmaz. Çakışma yoksa '' döner. */
function pf_kullanici_cakisma($db, $u, $mail, $haricKurum = 0, $haricId = 0)
{
    $u = strtolower(trim((string)$u)); $mail = strtolower(trim((string)$mail));
    if ($u === '' && $mail === '') return '';
    $onceki = $db->kurum; $hata = '';
    $ids = []; $db->kurum = 0; $q = $db->query("SELECT id FROM pf_kurumlar"); if ($q) while ($r = $q->fetch_row()) $ids[] = intval($r[0]);
    foreach ($ids as $kid) {
        $db->kurum = $kid;
        foreach ([['kullanici_adi', $u, 'Bu kullanıcı adı sistemde başka bir hesapta kullanılıyor. Lütfen başka bir ad seçin.'], ['email', $mail, 'Bu e-posta adresi sistemde başka bir hesapta kayıtlı.']] as $k) {
            if ($k[1] === '') continue;
            $st = @$db->prepare("SELECT id FROM kullanicilar WHERE LOWER({$k[0]}) = ?");
            if (!$st) continue;
            $st->bind_param('s', $k[1]); $st->execute(); $res = $st->get_result();
            while ($res && ($r = $res->fetch_row())) { if (!($kid === intval($haricKurum) && intval($r[0]) === intval($haricId))) { $hata = $k[2]; break 3; } }
        }
    }
    $db->kurum = $onceki;
    return $hata;
}
/** Girişte kurum belirtilmediğinde: kullanıcı adı ya da e-postaya göre tüm kurumlardaki adaylar [[kurum, kul], ...] */
function pf_kullanici_bul($db, $giris)
{
    $giris = strtolower(trim((string)$giris)); $onceki = $db->kurum; $out = [];
    $db->kurum = 0; $kurumlar = []; $q = $db->query("SELECT * FROM pf_kurumlar ORDER BY id"); if ($q) while ($r = $q->fetch_assoc()) $kurumlar[] = $r;
    foreach ($kurumlar as $k) {
        $db->kurum = intval($k['id']);
        $st = @$db->prepare("SELECT id, ad_soyad, sifre, aktif, sifre_degistir FROM kullanicilar WHERE kullanici_adi IN (?, ?) OR (email <> '' AND LOWER(email) = ?) LIMIT 2");
        if (!$st) continue;
        $g2 = str_replace('.', '', $giris);       // eski "ali.veli" yazılsa da noktasız "aliveli" hesabına ulaşır
        $st->bind_param('sss', $giris, $g2, $giris); $st->execute(); $res = $st->get_result();
        while ($res && ($r = $res->fetch_assoc())) $out[] = ['kurum' => $k, 'kul' => $r];
    }
    $db->kurum = $onceki;
    return $out;
}
function kurum_kullanici_ekle($db, $a)
{
    $u = strtolower(trim((string)($a['kullanici_adi'] ?? '')));
    $ad = trim(preg_replace('/\s+/', ' ', (string)($a['ad_soyad'] ?? '')));
    if ($ad === '') return ['ok' => false, 'hata' => 'Ad soyad gerekli.'];
    if (!kullanici_adi_gecerli($u)) return ['ok' => false, 'hata' => 'Kullanıcı adı 3–60 karakter; küçük harf, rakam, nokta, alt çizgi ve tire kullanılabilir.'];
    $rol = in_array($a['rol'] ?? 'personel', ['personel', 'yetkili', 'kurucu'], true) ? $a['rol'] : 'personel';
    $sifre = (string)($a['sifre'] ?? '');
    $gecici = 0;
    if ($sifre === '') { $sifre = pf_sifre_uret(10); $gecici = 1; }
    elseif (strlen($sifre) < 8) return ['ok' => false, 'hata' => 'Şifre en az 8 karakter olmalı.'];
    $hash = password_hash($sifre, PASSWORD_DEFAULT);
    $gorev = mb_substr(trim((string)($a['gorev'] ?? '')), 0, 100, 'UTF-8');
    $tel = mb_substr(trim((string)($a['telefon'] ?? '')), 0, 30, 'UTF-8');
    $mail = trim((string)($a['email'] ?? ''));
    if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'hata' => 'E-posta adresi geçersiz.'];
    $cak = pf_kullanici_cakisma($db, $u, $mail); if ($cak !== '') return ['ok' => false, 'hata' => $cak];
    $renk = kullanici_renk($ad);
    $st = $db->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre, ad_soyad, rol, gorev, aktif, avatar_renk, telefon, email, sifre_degistir, olusturma) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, NOW())");
    $st->bind_param('ssssssssi', $u, $hash, $ad, $rol, $gorev, $renk, $tel, $mail, $gecici);
    if (!$st->execute()) return ['ok' => false, 'hata' => $db->hatano() === 1062 ? 'Bu kullanıcı adı bu kurumda zaten var.' : 'Kullanıcı eklenemedi.'];
    return ['ok' => true, 'id' => $db->son_id(), 'sifre' => $gecici ? $sifre : '', 'kullanici_adi' => $u];
}
/** Yeni kurum + yetkilisi (rol: kurucu = kurumdaki tüm yetkiler). */
function pf_kurum_olustur($db, $a)
{
    $ad = trim((string)($a['ad'] ?? ''));
    if ($ad === '') return ['ok' => false, 'hata' => 'Kurum adı gerekli.'];
    $kod = pf_kod_uret(trim((string)($a['kod'] ?? '')) !== '' ? $a['kod'] : $ad);
    if (pf_kurum_kod($db, $kod)) return ['ok' => false, 'hata' => "“{$kod}” kurum kodu zaten kullanılıyor."];
    $renk = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($a['renk'] ?? '')) ? $a['renk'] : '#6366f1';
    $yad = trim((string)($a['yetkili_ad'] ?? '')); $yuk = strtolower(trim((string)($a['yetkili_kullanici'] ?? '')));
    if ($yad === '') return ['ok' => false, 'hata' => 'Kurum yetkilisinin adı gerekli.'];
    if ($yuk === '') $yuk = kullanici_adi_oner($yad);
    if (!kullanici_adi_gecerli($yuk)) return ['ok' => false, 'hata' => 'Yetkili kullanıcı adı geçersiz (3–60 karakter; küçük harf, rakam, . _ -).'];
    $tel = mb_substr(trim((string)($a['telefon'] ?? '')), 0, 30, 'UTF-8'); $mail = trim((string)($a['email'] ?? ''));
    if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'hata' => 'E-posta adresi geçersiz.'];
    $sinir = max(0, intval($a['kullanici_siniri'] ?? 0)); $notlar = (string)($a['notlar'] ?? '');
    $lis = pf_lisans_hesapla((string)($a['lisans'] ?? 'sinirsiz'), $a['lisans_bitis'] ?? '') ?: ['sinirsiz', null, null];
    $st = $db->prepare("INSERT INTO pf_kurumlar (ad, kod, renk, kullanici_siniri, yetkili_ad, telefon, email, notlar, lisans_tur, lisans_baslangic, lisans_bitis, olusturma) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $st->bind_param('sssisssssss', $ad, $kod, $renk, $sinir, $yad, $tel, $mail, $notlar, $lis[0], $lis[1], $lis[2]);
    if (!$st->execute()) return ['ok' => false, 'hata' => 'Kurum kaydedilemedi: ' . $db->hata()];
    $kid = $db->son_id();
    $onceki = $db->kurum;
    $db->kurum = $kid;
    try {
        kurum_sema($db);
        $r = kurum_kullanici_ekle($db, ['kullanici_adi' => $yuk, 'ad_soyad' => $yad, 'rol' => 'kurucu', 'gorev' => 'Kurum yöneticisi', 'telefon' => $tel, 'email' => $mail, 'sifre' => (string)($a['sifre'] ?? '')]);
        if (!$r['ok']) throw new RuntimeException($r['hata']);
        $db->kurum = 0;
        $db->query("UPDATE pf_kurumlar SET yetkili_id = " . intval($r['id']) . " WHERE id = $kid");
    } catch (Throwable $e) {
        $db->kurum = 0;
        $db->query("DELETE FROM pf_kurumlar WHERE id = $kid");
        pf_kurum_tablolarini_sil($db, $kid);
        $db->kurum = $onceki;
        return ['ok' => false, 'hata' => 'Kurum oluşturulamadı: ' . $e->getMessage()];
    }
    $db->kurum = $onceki;
    pf_log($db, 'kurum_olustur', "$ad ($kod)", $kid);
    return ['ok' => true, 'id' => $kid, 'kod' => $kod, 'kullanici_adi' => $r['kullanici_adi'], 'sifre' => $r['sifre']];
}
/** Bir kurumun bütün tablolarını siler (k{id}_ ile başlayanlar) */
function pf_kurum_tablolarini_sil($db, $kid)
{
    $kid = intval($kid);
    if ($kid <= 0) return 0;
    $n = 0;
    $q = $db->query("SHOW TABLES LIKE 'k" . $kid . "\\_%'");
    $adlar = [];
    if ($q) while ($r = $q->fetch_row()) $adlar[] = $r[0];
    $db->query("SET FOREIGN_KEY_CHECKS = 0");
    foreach ($adlar as $t) if (preg_match('/^k' . $kid . '_[a-z0-9_]+$/i', $t)) { $db->query("DROP TABLE IF EXISTS `$t`"); $n++; }
    $db->query("SET FOREIGN_KEY_CHECKS = 1");
    return $n;
}
/** Kurumun logosu ya da (yoksa) baş harf için güvenli bilgi */
function pf_kurum_ozet($k)
{
    return ['id' => intval($k['id']), 'ad' => $k['ad'], 'kod' => $k['kod'], 'renk' => $k['renk'], 'logo' => $k['logo'], 'durum' => $k['durum']];
}

} // PF_ORTAK
