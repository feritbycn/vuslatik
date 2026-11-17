<?php
// ============================================================
// giris_ortak.php — Personel Giriş-Çıkış (yoklama) ortak yardımcıları
// giris_cikis.php (sayfa), giris_islem.php (API), giris_ekran.php (ofis ekranı) kullanır.
//
// GÜVENLİK KATMANLARI (hile önleme):
//  1) Konum çemberi: sunucu, telefonun konumunu "geçiş noktası" (enlem/boylam/yarıçap) ile karşılaştırır; doğruluk düşükse reddeder.
//  2) Cihaz kaydı: yalnızca kişinin ONAYLI cihazından giriş yapılır → başkası hesaba başka telefondan girse bile damga vuramaz.
//  3) Kişisel PIN: her giriş/çıkışta kişinin kendi PIN'i istenir (5 hatada 15 dk kilit) → telefonu/hesabı ele geçiren biri PIN'i bilmeden damga vuramaz.
//  4) İsteğe bağlı: ofis IP kısıtı (ofis Wi-Fi'si) ve ofis ekranında dönen 6 haneli kod (fiziksel olarak ofiste olmayı kanıtlar; sahte GPS'i etkisiz kılar).
//  5) Süre/saat yalnızca SUNUCU saatiyle yazılır; şüpheli durumlar (aşırı "mükemmel" doğruluk, cihaz saati farkı…) işaretlenip İK'ya gösterilir.
// ============================================================
require_once __DIR__ . '/tb_yetki_motor.php';
if (!defined('GIRIS_ORTAK')) {
define('GIRIS_ORTAK', true);
define('GIRIS_VARSAYILAN', ['hedef_saat' => 8, 'max_dogruluk' => 100, 'konum_zorunlu' => 1, 'cikis_konum' => 1, 'pin_zorunlu' => 1, 'webauthn_zorunlu' => 1, 'cihaz_zorunlu' => 1, 'cihaz_ilk_otomatik' => 1,
    'mesai_baslangic' => '09:00', 'mesai_bitis' => '18:00', 'tolerans_dk' => 5, 'calisma_gunleri' => '1,2,3,4,5', 'otomatik_kapat_saat' => 20,
    'izinli_cihaz' => 'android', 'yontem' => 'kaydir',
    'gunluk_rapor' => 0, 'gunluk_rapor_saat' => '11:00', 'gunluk_rapor_alicilar' => '', 'gunluk_rapor_gec' => 1, 'gunluk_rapor_son' => '', 'gunluk_rapor_sonuc' => '']); // giriş-çıkış yapılabilecek cihaz türleri (virgülle): android, ios, masaustu
define('GIRIS_CIHAZ_TURLERI', ['android' => 'Android telefon', 'ios' => 'iPhone / iPad', 'masaustu' => 'Bilgisayar (Windows / Mac / Linux)']);
define('GIRIS_SUPHELI_ETIKET', [
    'tam_dogruluk' => 'Şüpheli: konum doğruluğu gerçek dışı derecede mükemmel (sahte konum uygulaması olabilir)',
    'saat_farki'   => 'Cihaz saati sunucu saatinden çok farklı',
    'cikis_unutuldu' => 'Önceki çıkış yapılmadı (otomatik kapatıldı)',
    'otomatik_kapandi' => 'Çıkış yapılmadığı için otomatik kapatıldı',
    'duzeltildi'   => 'İK tarafından düzeltildi / elle eklendi',
    'ortak_cihaz'  => 'Mükerrer cihaz: aynı cihazdan başka kişi adına da giriş yapılmış',
    'konumsuz'     => 'Konum doğrulaması yapılmadı (ayarlarda kapalı)',
]);

function giris_sema($db)
{
    static $tamam = false;
    if ($tamam) return;
    $tamam = true;
    try {
        if ($db->query("SELECT 1 FROM giris_kayitlari LIMIT 1") && $db->query("SELECT 1 FROM giris_noktalari LIMIT 1") && $db->query("SELECT 1 FROM giris_pin LIMIT 1")
            && $db->query("SELECT 1 FROM giris_cihazlar LIMIT 1") && $db->query("SELECT 1 FROM giris_ayarlar LIMIT 1")) { giris_vardiya_tablosu($db); giris_sekme_kaydet($db); return; }
    } catch (Throwable $e) {}
    $db->query("CREATE TABLE IF NOT EXISTS giris_noktalari (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ad VARCHAR(120) NOT NULL,
        enlem DECIMAL(10,7) NULL,
        boylam DECIMAL(10,7) NULL,
        yaricap INT NOT NULL DEFAULT 100,
        ip_kisit VARCHAR(500) NOT NULL DEFAULT '',
        kod_zorunlu TINYINT(1) NOT NULL DEFAULT 0,
        ekran_anahtari VARCHAR(64) NOT NULL DEFAULT '',
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS giris_kayitlari (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        nokta_id INT NULL,
        giris_zamani DATETIME NOT NULL,
        cikis_zamani DATETIME NULL,
        giris_bilgi TEXT NULL,
        cikis_bilgi TEXT NULL,
        supheli VARCHAR(300) NOT NULL DEFAULT '',
        not_metni VARCHAR(300) NOT NULL DEFAULT '',
        duzelten_id INT NULL,
        PRIMARY KEY (id), KEY ix_gk_kisi (kullanici_id, giris_zamani), KEY ix_gk_acik (cikis_zamani), KEY ix_gk_tarih (giris_zamani)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS giris_pin (
        kullanici_id INT NOT NULL,
        pin_hash VARCHAR(255) NOT NULL,
        deneme INT NOT NULL DEFAULT 0,
        kilit_bitis DATETIME NULL,
        guncelleme_tarihi DATETIME NULL,
        PRIMARY KEY (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS giris_cihazlar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        cihaz_hash CHAR(64) NOT NULL,
        ad VARCHAR(150) NOT NULL DEFAULT '',
        durum VARCHAR(12) NOT NULL DEFAULT 'bekliyor',
        ilk_kullanim DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        son_kullanim DATETIME NULL,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        PRIMARY KEY (id), UNIQUE KEY uq_gc (kullanici_id, cihaz_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS giris_ayarlar (
        anahtar VARCHAR(40) NOT NULL,
        deger VARCHAR(255) NOT NULL DEFAULT '',
        PRIMARY KEY (anahtar)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    giris_vardiya_tablosu($db);
    giris_sekme_kaydet($db);
}

/** Kişiye özel vardiya (boş bırakılan saat = varsayılan vardiya) */
function giris_vardiya_tablosu($db)
{
    static $yapildi = false;
    if ($yapildi) return;
    $yapildi = true;
    try {
        $db->query("CREATE TABLE IF NOT EXISTS giris_vardiyalar (
            kullanici_id INT NOT NULL,
            baslangic CHAR(5) NULL,
            bitis CHAR(5) NULL,
            PRIMARY KEY (kullanici_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch (Throwable $e) {}
}

/** Süresi dolan (varsayılan 20 saat) açık kayıtları otomatik kapatır: çıkış = giriş + süre, kayıt "otomatik kapandı" olarak işaretlenir */
function giris_otomatik_kapat($db, $saat = 20)
{
    $saat = max(4, min(48, intval($saat)));
    try {
        $db->query("UPDATE giris_kayitlari SET cikis_zamani = giris_zamani + INTERVAL $saat HOUR,
            supheli = CONCAT(supheli, IF(supheli = '', '', ','), 'otomatik_kapandi')
            WHERE cikis_zamani IS NULL AND giris_zamani < NOW() - INTERVAL $saat HOUR");
    } catch (Throwable $e) {}
}

/** Yan menüye "Giriş-Çıkış" sekmesini ekler — herkese görünür (kısıtlamak için Menü Yönetimi) */
function giris_sekme_kaydet($db)
{
    static $yapildi = false;
    if ($yapildi) return;
    $yapildi = true;
    // (Ürün sürümünde yan menü sekmesi yok: giriş-çıkış, ik.php menüsünden ve “Giriş-çıkış yap” düğmesinden açılır.)
}

define('GIRIS_YONTEMLER', ['kaydir', 'halka', 'sayac', 'kapi', 'kadran', 'cizim', 'cift', 'parmak']);
function giris_ayarlar($db, $yenile = false)
{
    static $c = null;
    if ($c !== null && !$yenile) return $c;
    $c = GIRIS_VARSAYILAN;
    $q = $db->query("SELECT anahtar, deger FROM giris_ayarlar");
    if ($q) while ($r = $q->fetch_assoc()) if (array_key_exists($r['anahtar'], $c)) $c[$r['anahtar']] = is_numeric($r['deger']) ? $r['deger'] + 0 : $r['deger'];
    return $c;
}

/** ['ik_giris_gor' => bool, 'ik_giris_yonet' => bool] — kurucu ikisini de yapar; yönetim yetkisi görmeyi de kapsar */
function giris_yetki($db, $uid)
{
    $Y = vy_coz($db, 'ik', $uid);
    $yon = !empty($Y['ik_giris_yonet']);
    return ['gor' => $yon || !empty($Y['ik_giris_gor']), 'yonet' => $yon];
}

function giris_ip()
{
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}
/** "85.100.20.5", "85.100.20.0/24", "85.100." (önek) — virgül / boşluk / satır ile ayrılmış liste */
function giris_ip_eslesir($ip, $liste)
{
    $liste = preg_split('/[\s,;]+/', trim((string)$liste), -1, PREG_SPLIT_NO_EMPTY);
    if (!$liste) return true; // kısıt yok
    foreach ($liste as $g) {
        if ($g === $ip) return true;
        if (substr($g, -1) === '.' && strpos($ip, $g) === 0) return true;
        if (strpos($g, '/') !== false && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            list($ag, $bit) = explode('/', $g, 2); $bit = intval($bit);
            if (filter_var($ag, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) && $bit >= 0 && $bit <= 32) {
                $mask = $bit === 0 ? 0 : (~0 << (32 - $bit)) & 0xFFFFFFFF;
                if ((ip2long($ip) & $mask) === (ip2long($ag) & $mask)) return true;
            }
        }
    }
    return false;
}
/** Haversine — metre */
function giris_mesafe($lat1, $lon1, $lat2, $lon2)
{
    $R = 6371000; $p1 = deg2rad($lat1); $p2 = deg2rad($lat2); $dp = deg2rad($lat2 - $lat1); $dl = deg2rad($lon2 - $lon1);
    $a = sin($dp / 2) ** 2 + cos($p1) * cos($p2) * sin($dl / 2) ** 2;
    return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
}
/** Ofis ekranındaki dönen kod: 30 sn'lik pencere + nokta anahtarı ile HMAC → 6 hane */
function giris_kod($nokta, $pencere)
{
    $h = hash_hmac('sha256', $pencere . '|' . intval($nokta['id']), (string)$nokta['ekran_anahtari']);
    return str_pad((string)(hexdec(substr($h, 0, 8)) % 1000000), 6, '0', STR_PAD_LEFT);
}
function giris_kod_dogru($nokta, $kod)
{
    $kod = preg_replace('/\D/', '', (string)$kod);
    if (strlen($kod) !== 6 || (string)$nokta['ekran_anahtari'] === '') return false;
    $p = (int)floor(time() / 30);
    foreach ([$p, $p - 1, $p + 1] as $w) if (hash_equals(giris_kod($nokta, $w), $kod)) return true;
    return false;
}
/**
 * İstek hangi tür cihazdan geliyor? 'android' | 'ios' | 'masaustu' | 'diger'
 * $k = istekle gelen istemci ipuçları ($_GET / $_POST): dokunmatik (maxTouchPoints), kaba (pointer:coarse 1/0), ekran (ekranın kısa kenarı, px), platform (navigator.platform).
 *
 * Telefon tarayıcısında "Masaüstü sitesi" açıkken tarayıcı kendini Linux/Windows bilgisayar gibi tanıtır (User-Agent ve Chrome ipuçları değişir).
 * Bu durumda telefonun GERÇEK özellikleri kullanılır: dokunmatik ekran + kaba işaretçi (parmak) + küçük ekran + ARM işlemci platformu
 * ("Linux armv8l") birlikte varsa cihaz Android telefon sayılır. iPhone/iPad "masaüstü modunda" Mac gibi görünür → dokunmatik nokta sayısı (>1) ile ayırt edilir.
 * NOT: Bu yazılımsal bir engeldir; tarayıcı geliştirici araçlarıyla taklit edilebilir. Asıl koruma cihaz onayı + PIN + konumdur.
 */
function giris_cihaz_turu($k = [])
{
    $k = is_array($k) ? $k : [];
    $dokunmatik = intval($k['dokunmatik'] ?? 0); $kaba = intval($k['kaba'] ?? 0) === 1; $ekran = intval($k['ekran'] ?? 0); $platform = (string)($k['platform'] ?? '');
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    $plat = strtolower(trim((string)($_SERVER['HTTP_SEC_CH_UA_PLATFORM'] ?? ''), " \"'"));
    if ($plat === 'android') return 'android';
    if ($plat === 'ios') return 'ios';
    $tur = 'diger';
    if (in_array($plat, ['windows', 'macos', 'linux', 'chrome os', 'chromeos'], true)) $tur = ($dokunmatik > 1 && $plat === 'macos') ? 'ios' : 'masaustu';
    elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $tur = 'ios';
    elseif (preg_match('/Android/i', $ua)) $tur = 'android';
    elseif (preg_match('/Macintosh/i', $ua) && $dokunmatik > 1) $tur = 'ios';
    elseif (preg_match('/Windows|Macintosh|X11|Linux|CrOS/i', $ua)) $tur = 'masaustu';
    // "Masaüstü sitesi" modundaki Android telefon: UA bilgisayar der ama cihaz gerçekte dokunmatik + parmak + küçük ekran + ARM
    if ($tur === 'masaustu' && $dokunmatik >= 1 && $kaba && $ekran > 0 && $ekran <= 900 && preg_match('/arm|aarch|android/i', $platform)) return 'android';
    return $tur;
}
function giris_cihaz_izinli($izinli, $tur)
{
    $liste = array_values(array_filter(array_map('trim', explode(',', (string)$izinli))));
    return !$liste || in_array($tur, $liste, true);
}
function giris_izinli_etiket($izinli)
{
    $o = []; foreach (array_filter(array_map('trim', explode(',', (string)$izinli))) as $t) if (isset(GIRIS_CIHAZ_TURLERI[$t])) $o[] = GIRIS_CIHAZ_TURLERI[$t];
    return $o ? implode(' / ', $o) : 'tüm cihazlar';
}
function giris_cihaz_adi($ua)
{
    $ua = (string)$ua;
    $os = preg_match('/Android/i', $ua) ? 'Android' : (preg_match('/iPhone|iPad|iOS/i', $ua) ? 'iPhone/iPad' : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Mac OS/i', $ua) ? 'Mac' : (preg_match('/Linux/i', $ua) ? 'Linux' : 'Bilinmeyen'))));
    $br = preg_match('/Edg/i', $ua) ? 'Edge' : (preg_match('/OPR|Opera/i', $ua) ? 'Opera' : (preg_match('/SamsungBrowser/i', $ua) ? 'Samsung Internet' : (preg_match('/Chrome|CriOS/i', $ua) ? 'Chrome' : (preg_match('/Firefox|FxiOS/i', $ua) ? 'Firefox' : (preg_match('/Safari/i', $ua) ? 'Safari' : 'Tarayıcı')))));
    if (preg_match('/Bilge360App/i', $ua)) $br = 'Uygulama';   // mobil uygulama (WebView) tarayıcı sayılmasın
    return $os . ' · ' . $br;
}
function giris_sure_yaz($sn)
{
    $sn = max(0, intval($sn)); $s = intdiv($sn, 3600); $d = intdiv($sn % 3600, 60);
    return ($s ? $s . 's ' : '') . $d . 'd';
}

} // GIRIS_ORTAK
