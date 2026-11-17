<?php
// ============================================================
// ik_ortak.php — İnsan Kaynakları ortak yardımcıları
// Tablolar, izin günü / yıllık izin hakkı hesabı, İK hatırlatmaları.
// ik_islem.php (API), ik.php (sayfa) ve tb_hatirlatma.php kullanır.
// ============================================================
if (!defined('IK_ORTAK')) {
define('IK_ORTAK', true);
require_once __DIR__ . '/tb_yetki_motor.php';
require_once __DIR__ . '/ik_kanal.php';

define('IK_IZIN_TURLERI', [
    'yillik' => 'Yıllık izin', 'mazeret' => 'Mazeret izni', 'rapor' => 'Hastalık / rapor', 'ucretsiz' => 'Ücretsiz izin',
    'dogum' => 'Doğum izni', 'babalik' => 'Babalık izni', 'evlilik' => 'Evlilik izni', 'vefat' => 'Vefat izni', 'idari' => 'İdari izin',
]);
define('IK_BELGE_TURLERI', [
    'sozlesme' => 'İş sözleşmesi', 'kimlik' => 'Kimlik fotokopisi', 'diploma' => 'Diploma', 'saglik' => 'Sağlık raporu',
    'sgk' => 'SGK giriş bildirgesi', 'adli' => 'Adli sicil kaydı', 'ikametgah' => 'İkametgah', 'sertifika' => 'Sertifika', 'diger' => 'Diğer',
]);
define('IK_CALISMA', ['tam_zamanli' => 'Tam zamanlı', 'yari_zamanli' => 'Yarı zamanlı', 'sozlesmeli' => 'Sözleşmeli', 'stajyer' => 'Stajyer', 'gonullu' => 'Gönüllü', 'uzaktan' => 'Uzaktan']);
define('IK_PUANTAJ_DURUM', ['tam' => 'Tam gün', 'yarim' => 'Yarım gün', 'izinli' => 'İzinli', 'rapor' => 'Raporlu', 'gelmedi' => 'Gelmedi (mazeretsiz)', 'resmi_tatil' => 'Resmî tatil', 'haftasonu' => 'Hafta sonu']);
define('IK_MASRAF_KATEGORI', ['yol' => 'Yol / ulaşım', 'yemek' => 'Yemek', 'konaklama' => 'Konaklama', 'ofis' => 'Ofis malzemesi', 'iletisim' => 'İletişim', 'diger' => 'Diğer']);

function ik_sema($db)
{
    static $tamam = false;
    if ($tamam) return;
    $tamam = true;
    ik_sema_v2($db);
    ik_sema_v3($db);
    ik_sema_avans($db);
}

function ik_sema_avans($db)
{
    $db->query("CREATE TABLE IF NOT EXISTS ik_avanslar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        tutar DECIMAL(12,2) NOT NULL DEFAULT 0,
        taksit INT NOT NULL DEFAULT 1,
        istenen_tarih DATE NULL,
        aciklama VARCHAR(500) NOT NULL DEFAULT '',
        durum VARCHAR(20) NOT NULL DEFAULT 'beklemede',
        onaylayan_id INT NULL,
        onay_notu VARCHAR(500) NOT NULL DEFAULT '',
        onay_tarihi DATETIME NULL,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_avans_kisi (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ik_sema_v2($db)
{
    $damga = __DIR__ . '/cron/.ik_sema_v2_k' . intval($db->kurum);
    if (is_file($damga) && $db->query("SELECT 1 FROM ik_personel LIMIT 1")) return;
    $db->query("CREATE TABLE IF NOT EXISTS ik_personel (
        kullanici_id INT NOT NULL,
        tc_kimlik VARCHAR(11) NOT NULL DEFAULT '',
        dogum_yeri VARCHAR(80) NOT NULL DEFAULT '',
        cinsiyet VARCHAR(10) NOT NULL DEFAULT '',
        medeni_durum VARCHAR(20) NOT NULL DEFAULT '',
        cocuk_sayisi TINYINT NOT NULL DEFAULT 0,
        kan_grubu VARCHAR(8) NOT NULL DEFAULT '',
        adres TEXT NULL,
        il VARCHAR(60) NOT NULL DEFAULT '',
        ilce VARCHAR(60) NOT NULL DEFAULT '',
        acil_kisi VARCHAR(120) NOT NULL DEFAULT '',
        acil_yakinlik VARCHAR(40) NOT NULL DEFAULT '',
        acil_telefon VARCHAR(30) NOT NULL DEFAULT '',
        ise_giris DATE NULL,
        isten_cikis DATE NULL,
        cikis_nedeni VARCHAR(255) NOT NULL DEFAULT '',
        unvan VARCHAR(100) NOT NULL DEFAULT '',
        departman VARCHAR(100) NOT NULL DEFAULT '',
        yonetici_id INT NULL,
        calisma_sekli VARCHAR(20) NOT NULL DEFAULT 'tam_zamanli',
        sgk_no VARCHAR(30) NOT NULL DEFAULT '',
        egitim_durumu VARCHAR(30) NOT NULL DEFAULT '',
        okul VARCHAR(150) NOT NULL DEFAULT '',
        bolum VARCHAR(150) NOT NULL DEFAULT '',
        mezuniyet_yili SMALLINT NULL,
        maas DECIMAL(12,2) NULL,
        maas_tipi VARCHAR(10) NOT NULL DEFAULT 'net',
        iban VARCHAR(40) NOT NULL DEFAULT '',
        banka VARCHAR(80) NOT NULL DEFAULT '',
        yillik_izin_hak DECIMAL(4,1) NULL,
        notlar TEXT NULL,
        guncelleyen_id INT NULL,
        guncelleme_tarihi DATETIME NULL,
        PRIMARY KEY (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_izin_talepleri (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        tur VARCHAR(20) NOT NULL DEFAULT 'yillik',
        baslangic DATE NOT NULL,
        bitis DATE NOT NULL,
        yarim_gun TINYINT(1) NOT NULL DEFAULT 0,
        gun DECIMAL(5,1) NOT NULL DEFAULT 0,
        aciklama TEXT NULL,
        vekil_id INT NULL,
        durum VARCHAR(20) NOT NULL DEFAULT 'beklemede',
        onaylayan_id INT NULL,
        onay_notu VARCHAR(500) NOT NULL DEFAULT '',
        onay_tarihi DATETIME NULL,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_ik_izin_kisi (kullanici_id),
        KEY ix_ik_izin_tarih (baslangic, bitis)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_belgeler (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        tur VARCHAR(20) NOT NULL DEFAULT 'diger',
        ad VARCHAR(200) NOT NULL DEFAULT '',
        dosya VARCHAR(255) NOT NULL,
        orijinal_ad VARCHAR(255) NOT NULL DEFAULT '',
        mime VARCHAR(100) NOT NULL DEFAULT '',
        boyut INT NOT NULL DEFAULT 0,
        gecerlilik DATE NULL,
        yukleyen_id INT NOT NULL,
        tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_ik_belge_kisi (kullanici_id),
        KEY ix_ik_belge_gecerlilik (gecerlilik)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_zimmetler (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        esya VARCHAR(150) NOT NULL,
        seri_no VARCHAR(100) NOT NULL DEFAULT '',
        aciklama VARCHAR(500) NOT NULL DEFAULT '',
        teslim_tarihi DATE NULL,
        iade_tarihi DATE NULL,
        durum VARCHAR(20) NOT NULL DEFAULT 'zimmetli',
        veren_id INT NULL,
        tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_ik_zimmet_kisi (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_hatirlatma_log (
        kullanici_id INT NOT NULL,
        anahtar VARCHAR(80) NOT NULL,
        son DATETIME NOT NULL,
        PRIMARY KEY (kullanici_id, anahtar)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // ── Puantaj (devam çizelgesi) ──
    $db->query("CREATE TABLE IF NOT EXISTS ik_puantaj (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        tarih DATE NOT NULL,
        durum VARCHAR(15) NOT NULL DEFAULT 'tam',
        not_metni VARCHAR(300) NOT NULL DEFAULT '',
        giren_id INT NULL,
        guncelleme_tarihi DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_puantaj (kullanici_id, tarih)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // ── Personel masrafları ──
    $db->query("CREATE TABLE IF NOT EXISTS ik_masraflar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        kategori VARCHAR(60) NOT NULL DEFAULT 'diger',
        tutar DECIMAL(12,2) NOT NULL DEFAULT 0,
        tarih DATE NOT NULL,
        aciklama VARCHAR(500) NOT NULL DEFAULT '',
        durum VARCHAR(20) NOT NULL DEFAULT 'beklemede',
        onaylayan_id INT NULL,
        onay_notu VARCHAR(500) NOT NULL DEFAULT '',
        onay_tarihi DATETIME NULL,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_masraf_kisi (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // ── Eğitimler ──
    $db->query("CREATE TABLE IF NOT EXISTS ik_egitimler (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ad VARCHAR(200) NOT NULL,
        egitmen VARCHAR(150) NOT NULL DEFAULT '',
        tarih DATE NULL,
        sure_saat DECIMAL(5,1) NOT NULL DEFAULT 0,
        aciklama VARCHAR(1000) NOT NULL DEFAULT '',
        olusturan_id INT NULL,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_egitim_katilim (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        egitim_id INT UNSIGNED NOT NULL,
        kullanici_id INT NOT NULL,
        tamamlandi TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uq_egitim_kisi (egitim_id, kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // ── Performans değerlendirmeleri ──
    $db->query("CREATE TABLE IF NOT EXISTS ik_performans (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        donem VARCHAR(30) NOT NULL,
        puan DECIMAL(3,1) NOT NULL DEFAULT 0,
        degerlendiren_id INT NULL,
        notlar VARCHAR(2000) NOT NULL DEFAULT '',
        tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_performans_kisi (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // ── Bordro kayıtları ──
    $db->query("CREATE TABLE IF NOT EXISTS ik_bordrolar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        yil SMALLINT NOT NULL,
        ay TINYINT NOT NULL,
        brut DECIMAL(12,2) NOT NULL DEFAULT 0,
        net DECIMAL(12,2) NOT NULL DEFAULT 0,
        kesinti DECIMAL(12,2) NOT NULL DEFAULT 0,
        dosya VARCHAR(255) NOT NULL DEFAULT '',
        orijinal_ad VARCHAR(255) NOT NULL DEFAULT '',
        ekleyen_id INT NULL,
        tarih DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_bordro (kullanici_id, yil, ay)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    // ── İK ayarları (basit anahtar/değer) ──
    $db->query("CREATE TABLE IF NOT EXISTS ik_ayarlar (
        anahtar VARCHAR(60) NOT NULL,
        deger VARCHAR(2000) NOT NULL DEFAULT '',
        PRIMARY KEY (anahtar)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    if (is_dir(__DIR__ . '/cron')) @touch($damga);
}

/**
 * Şema v3 — çok adımlı izin onay akışı + bildirim kuyruğu. Bir kere çalışır (ik_ayarlar.sema_v3 işareti).
 *   ik_onay_akis      : birim (departman) başına sıralı onaycılar ve her onaycıya hangi kanaldan (site / SMS / e-posta) haber verileceği
 *   ik_onay_bildirim  : birim başına sonuç bildirimleri (talep eden + ayrıca bilgilendirilecek kişiler)
 *   ik_izin_adimlari  : bir izin talebinin kendi onay zinciri (talep anında akıştan kopyalanır; sonradan akış değişse bile talep etkilenmez)
 */
function ik_sema_v3($db)
{
    static $y = false;
    if ($y) return;
    $y = true;
    try { ik_ayar_tablosu($db); if (ik_ayar($db, 'sema_v5') === '1') return; } catch (Throwable $e) {}
    $db->query("CREATE TABLE IF NOT EXISTS ik_onay_akis (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        departman VARCHAR(100) NOT NULL,
        sira TINYINT NOT NULL DEFAULT 1,
        onaylayici_id INT NOT NULL DEFAULT 0,
        k_site TINYINT(1) NOT NULL DEFAULT 1,
        k_sms TINYINT(1) NOT NULL DEFAULT 0,
        k_mail TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id), KEY ix_akis_dep (departman, sira)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_onay_bildirim (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        departman VARCHAR(100) NOT NULL,
        rol VARCHAR(12) NOT NULL DEFAULT 'bilgi',
        kullanici_id INT NOT NULL DEFAULT 0,
        k_site TINYINT(1) NOT NULL DEFAULT 1,
        k_sms TINYINT(1) NOT NULL DEFAULT 0,
        k_mail TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id), KEY ix_akisb_dep (departman)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_izin_adimlari (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        talep_id INT UNSIGNED NOT NULL,
        sira TINYINT NOT NULL,
        onaylayici_id INT NOT NULL,
        durum VARCHAR(12) NOT NULL DEFAULT 'bekliyor',
        k_site TINYINT(1) NOT NULL DEFAULT 1,
        k_sms TINYINT(1) NOT NULL DEFAULT 0,
        k_mail TINYINT(1) NOT NULL DEFAULT 0,
        karar_veren_id INT NULL,
        karar_tarihi DATETIME NULL,
        not_metni VARCHAR(500) NOT NULL DEFAULT '',
        PRIMARY KEY (id), KEY ix_adim_talep (talep_id, sira), KEY ix_adim_kisi (onaylayici_id, durum)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach (['aktif_adim' => 'TINYINT NULL', 'departman' => "VARCHAR(100) NOT NULL DEFAULT ''"] as $kol => $tanim) {
        $q = $db->query("SHOW COLUMNS FROM ik_izin_talepleri LIKE '$kol'");
        if ($q && $q->num_rows === 0) $db->query("ALTER TABLE ik_izin_talepleri ADD COLUMN $kol $tanim");
    }
    $q = $db->query("SHOW COLUMNS FROM ik_personel LIKE 'pozisyon_baslama'");
    if ($q && $q->num_rows === 0) $db->query("ALTER TABLE ik_personel ADD COLUMN pozisyon_baslama DATE NULL");
    foreach (['ik_onay_akis', 'ik_izin_adimlari'] as $tb) {
        $q = $db->query("SHOW COLUMNS FROM $tb LIKE 'vekil_id'");
        if ($q && $q->num_rows === 0) $db->query("ALTER TABLE $tb ADD COLUMN vekil_id INT NOT NULL DEFAULT 0");
    }
    ik_kanal_sema($db);
    ik_ayar_yaz($db, 'sema_v5', '1');
}

/* ====================== İZİN ONAY AKIŞI ====================== */
define('IK_AKIS_VARSAYILAN', '*');         // "tüm birimler" için varsayılan akış
define('IK_ONAYCI_YONETICI', -1);          // onaycı = talep edenin İK kartındaki bağlı olduğu yönetici

function ik_kisi_departman($db, $kid)
{
    $r = $db->query("SELECT p.departman, k.gorev FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.id = " . intval($kid))->fetch_assoc();
    $d = trim((string)($r['departman'] ?? '')); if ($d === '') $d = trim((string)($r['gorev'] ?? ''));
    return $d !== '' ? $d : 'Belirtilmemiş';
}
function ik_kanal_dizi($r) { return ['site' => !empty($r['k_site']), 'sms' => !empty($r['k_sms']), 'mail' => !empty($r['k_mail'])]; }

/** Birimin akış satırları (yoksa varsayılan "*" akışı). @return array [satirlar, bildirimSatirlari, kaynak] */
function ik_akis_satirlari($db, $dep)
{
    $kucuk = function ($s) { return mb_strtolower(trim((string)$s), 'UTF-8'); };
    $hepsi = [];
    $q = $db->query("SELECT * FROM ik_onay_akis ORDER BY departman, sira, id");
    if ($q) while ($r = $q->fetch_assoc()) $hepsi[$kucuk($r['departman'])][] = $r;
    $bh = [];
    $q = $db->query("SELECT * FROM ik_onay_bildirim ORDER BY id");
    if ($q) while ($r = $q->fetch_assoc()) $bh[$kucuk($r['departman'])][] = $r;
    $k = $kucuk($dep);
    if (!empty($hepsi[$k])) return [$hepsi[$k], $bh[$k] ?? [], $dep];
    if (!empty($hepsi[IK_AKIS_VARSAYILAN])) return [$hepsi[IK_AKIS_VARSAYILAN], $bh[IK_AKIS_VARSAYILAN] ?? [], IK_AKIS_VARSAYILAN];
    return [[], $bh[$k] ?? ($bh[IK_AKIS_VARSAYILAN] ?? []), ''];
}

/**
 * Bir personel izin isterse onay zinciri kimlerden oluşur?
 * Kendisi olan adımlar ve pasif kullanıcılar atlanır; art arda aynı kişi tek sayılır.
 * @return array ['departman','kaynak','adimlar'=>[['id','ad','site','sms','mail']], 'talep_sahibi'=>kanal, 'bilgi'=>[['id','ad',kanal…]]]
 */
function ik_akis_cozumle($db, $kid)
{
    $kid = intval($kid);
    $dep = ik_kisi_departman($db, $kid);
    list($satir, $bildirim, $kaynak) = ik_akis_satirlari($db, $dep);
    $yonetici = intval($db->query("SELECT COALESCE(yonetici_id, 0) FROM ik_personel WHERE kullanici_id = $kid")->fetch_row()[0] ?? 0);
    $ad = function ($id) use ($db) { $r = $db->query("SELECT ad_soyad, aktif FROM kullanicilar WHERE id = " . intval($id))->fetch_row(); return $r && intval($r[1]) === 1 ? (string)$r[0] : null; };
    $adimlar = []; $son = 0;
    foreach ($satir as $r) {
        $id = intval($r['onaylayici_id']);
        if ($id === IK_ONAYCI_YONETICI) $id = $yonetici;
        if ($id <= 0 || $id === $kid || $id === $son) continue;
        $an = $ad($id); if ($an === null) continue;
        $vk = intval($r['vekil_id'] ?? 0);
        if ($vk === $kid || $vk === $id || ($vk > 0 && $ad($vk) === null)) $vk = 0;
        $adimlar[] = ['id' => $id, 'ad' => $an, 'vekil_id' => $vk, 'vekil_ad' => $vk > 0 ? $ad($vk) : ''] + ik_kanal_dizi($r);
        $son = $id;
    }
    $sahibi = ['site' => true, 'sms' => false, 'mail' => false]; $bilgi = [];
    foreach ($bildirim as $r) {
        if ($r['rol'] === 'talep_sahibi') $sahibi = ik_kanal_dizi($r);
        elseif ($r['rol'] === 'bilgi' && intval($r['kullanici_id']) > 0 && intval($r['kullanici_id']) !== $kid) { $an = $ad($r['kullanici_id']); if ($an !== null) $bilgi[] = ['id' => intval($r['kullanici_id']), 'ad' => $an] + ik_kanal_dizi($r); }
    }
    return ['departman' => $dep, 'kaynak' => $kaynak, 'akis_var' => !empty($satir), 'adimlar' => $adimlar, 'talep_sahibi' => $sahibi, 'bilgi' => $bilgi];
}

function ik_izin_tarih_yaz($bas, $bit)
{
    return date('d.m.Y', strtotime($bas)) . ($bit !== $bas ? ' – ' . date('d.m.Y', strtotime($bit)) : '');
}
define('IK_AY_AD', ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık']);
define('IK_GUN_AD', ['', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar']);
/** 24 Eylül 2026 Perşembe */
function ik_tarih_uzun($d) { $t = strtotime($d); return $t ? (int)date('j', $t) . ' ' . IK_AY_AD[(int)date('n', $t) - 1] . ' ' . date('Y', $t) . ' ' . IK_GUN_AD[(int)date('N', $t)] : ''; }
/** İzin bitişinden sonraki ilk iş günü (Cumartesi-Pazar atlanır) = işe dönüş tarihi */
function ik_ise_donus($bit) { $t = strtotime('+1 day', strtotime($bit)); while ((int)date('N', $t) > 5) $t = strtotime('+1 day', $t); return date('Y-m-d', $t); }
function ik_gun_yaz($g) { return str_replace('.', ',', rtrim(rtrim(number_format((float)$g, 1, '.', ''), '0'), '.')); }

/**
 * Bir kişiye seçili kanallardan (site / SMS / e-posta) haber verir.
 * $m = ['baslik'=>…, 'html'=>site içi + e-posta gövdesi (HTML), 'sms'=>düz metin, 'link'=>göreli adres, 'buton'=>e-posta düğmesi, 'tablo'=>…, 'bid'=>…, 'detay'=>e-postaya eklenecek HTML]
 */
function ik_kisi_bildir($db, $alici, $gonderen, $kanal, $m)
{
    $alici = intval($alici);
    if ($alici <= 0) return;
    if (empty($kanal['site']) && empty($kanal['sms']) && empty($kanal['mail'])) $kanal['site'] = true; // en az site içi
    $link = isset($m['link']) && $m['link'] !== '' ? ik_site_url($db) . ltrim($m['link'], '/') : '';
    if (!empty($kanal['site'])) ik_bildir($db, $alici, $gonderen, $m['baslik'], $m['html'], $m['tablo'] ?? 'ik_izin', $m['bid'] ?? 0);
    if (empty($kanal['sms']) && empty($kanal['mail'])) return;
    $k = $db->query("SELECT ad_soyad, telefon, email FROM kullanicilar WHERE id = $alici")->fetch_assoc();
    if (!$k) return;
    $sms = (string)($m['sms'] ?? ''); $sablon = ik_mail_sablon(preg_replace('/^[^\p{L}\p{N}]+/u', '', $m['baslik']), $m['html'] . ($m['detay'] ?? ''), $link, $m['buton'] ?? 'Aç', $db, (string)$k['ad_soyad']);
    $konu = trim(preg_replace('/^[^\p{L}\p{N}]+/u', '', $m['baslik']));
    $pdf = null;
    if (!empty($kanal['mail']) && ($m['pdf'] ?? true) && ik_ayar($db, 'mail_pdf_ek', '1') !== '0') { try { require_once __DIR__ . '/ik_pdf.php'; $pdf = ik_mail_pdf_uret($db, $konu, $m['html'] . ($m['detay'] ?? ''), (string)$k['ad_soyad'], $link); } catch (Throwable $e) { $pdf = null; } }
    $ik = [$db, $alici, $k, $kanal, $sms, $link, $konu, $sablon, $pdf];
    ik_sonra(function () use ($ik) {
        list($db, $alici, $k, $kanal, $sms, $link, $konu, $sablon, $pdf) = $ik;
        if (!empty($kanal['sms'])) {
            $tel = (string)$k['telefon'];
            $r = $tel === '' ? ['ok' => false, 'sonuc' => 'Kişinin telefon numarası kayıtlı değil'] : ik_sms_gonder($db, $tel, $sms . ($link !== '' ? ' ' . $link : ''));
            ik_kanal_log($db, $alici, 'sms', $k['ad_soyad'] . ' · ' . $tel, $konu, $r['ok'], $r['sonuc']);
        }
        if (!empty($kanal['mail'])) {
            $em = (string)$k['email'];
            $r = $em === '' ? ['ok' => false, 'sonuc' => 'Kişinin e-posta adresi kayıtlı değil'] : ik_mail_gonder($db, $em, $konu, $sablon, $k['ad_soyad'], $pdf ? [['ad' => 'belge_' . preg_replace('/[^a-z0-9]+/', '_', strtolower(strtr($konu, ['ı' => 'i', 'İ' => 'i', 'ş' => 's', 'Ş' => 's', 'ğ' => 'g', 'Ğ' => 'g', 'ü' => 'u', 'Ü' => 'u', 'ö' => 'o', 'Ö' => 'o', 'ç' => 'c', 'Ç' => 'c']))) . '.pdf', 'tur' => 'application/pdf', 'icerik' => $pdf]] : []);
            ik_kanal_log($db, $alici, 'mail', $k['ad_soyad'] . ' · ' . $em, $konu, $r['ok'], $r['sonuc']);
        }
    });
}

/** E-postaya eklenen izin özet tablosu */
function ik_izin_detay_html($t, $kisiAd, $vekilAd = '')
{
    $sat = function ($a, $b) { return '<tr><td style="padding:12px 12px 12px 0;border-bottom:1px solid #e2e8f0;color:#0f172a;font-size:14px;font-weight:700;white-space:nowrap">' . $a . '</td><td align="right" style="padding:12px 0;border-bottom:1px solid #e2e8f0;color:#334155;font-size:14px">' . $b . '</td></tr>'; };
    return '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:14px 0 0;width:100%">' .
        $sat('Personel', ik_h($kisiAd)) . $sat('İzin türü', ik_h(IK_IZIN_TURLERI[$t['tur']] ?? $t['tur'])) . $sat('Başlangıç tarihi', ik_tarih_uzun($t['baslangic'])) . $sat('Bitiş tarihi', ik_tarih_uzun($t['bitis'])) . $sat('İşe dönüş tarihi', '<b>' . ik_tarih_uzun(ik_ise_donus($t['bitis'])) . '</b>') .
        $sat('Süre', (!empty($t['yarim_gun']) ? 'Yarım gün' : ik_gun_yaz($t['gun']) . ' iş günü')) . ($vekilAd !== '' ? $sat('Vekil', ik_h($vekilAd)) : '') .
        (trim((string)$t['aciklama']) !== '' ? $sat('Açıklama', nl2br(ik_h($t['aciklama']))) : '') . '</table>';
}

/** Onay sırası $adim olan kişiye "onayınız bekleniyor" haberi gider. $onceki = bir önceki adımı onaylayan kişi adı (varsa) */
function ik_izin_adim_bildir($db, $t, $adim, $kisiAd, $vekilAd = '', $onceki = '')
{
    $tarih = ik_izin_tarih_yaz($t['baslangic'], $t['bitis']); $tur = IK_IZIN_TURLERI[$t['tur']] ?? 'izin'; $gun = ik_gun_yaz($t['gun']);
    if ($onceki !== '') {
        $html = '🌴 <b>' . ik_h($onceki) . '</b>, <b>' . ik_h($kisiAd) . '</b> adlı personelin ' . $tarih . ' tarihli <b>' . ik_h($tur) . '</b> talebini onayladı (' . $gun . ' gün). <b>Sizin onayınızı bekliyor.</b>';
        $sms = $onceki . ', ' . $kisiAd . ' adlı personelin ' . $tarih . ' tarihli ' . $tur . ' talebini onayladı. Sizin onayınızı bekliyor:';
        $baslik = '🌴 İzin talebi sizin onayınızı bekliyor';
    } else {
        $html = '🌴 <b>' . ik_h($kisiAd) . '</b> ' . $tarih . ' için <b>' . ik_h($tur) . '</b> talep etti (' . $gun . ' gün). <b>Onayınız bekleniyor.</b>';
        $sms = $kisiAd . ' ' . $tarih . ' için ' . $tur . ' talep etti (' . $gun . ' gün). Onayınız bekleniyor:';
        $baslik = '🌴 Yeni izin talebi — onayınız bekleniyor';
    }
    // Onaycı izindeyse ve vekili tanımlıysa haber vekile gider (vekil yoksa / vekil de izindeyse yine onaycıya)
    $alici = intval($adim['onaylayici_id']); $vk = intval($adim['vekil_id'] ?? 0);
    if ($vk > 0 && $vk !== intval($t['kullanici_id']) && ik_izinde_mi($db, $alici) && !ik_izinde_mi($db, $vk)) {
        $asilAd = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $alici")->fetch_row()[0] ?? '';
        $html .= '<br><small>🤝 <b>' . ik_h($asilAd) . '</b> izinde olduğu için vekil olarak size iletildi.</small>';
        $sms = $sms . ' (vekil: ' . $asilAd . ' izinde)';
        $alici = $vk;
    }
    ik_kisi_bildir($db, $alici, intval($t['kullanici_id']), ik_kanal_dizi($adim), ['baslik' => $baslik, 'html' => $html, 'sms' => $sms,
        'link' => 'ik.php?sekme=izinler&talep=' . intval($t['id']), 'buton' => 'Talebi incele ve karar ver', 'tablo' => 'ik_izin', 'bid' => intval($t['id']), 'detay' => ik_izin_detay_html($t, $kisiAd, $vekilAd)]);
}

/** Talep sonuçlandığında: talep edene (seçili kanallarla) ve bilgi alıcılarına haber ver */
function ik_izin_sonuc_bildir($db, $t, $onayli, $kararVerenId, $kararVerenAd, $not, $akis)
{
    $kid = intval($t['kullanici_id']);
    $kisiAd = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = $kid")->fetch_row()[0] ?? '';
    $tarih = ik_izin_tarih_yaz($t['baslangic'], $t['bitis']); $tur = IK_IZIN_TURLERI[$t['tur']] ?? 'izin';
    $link = 'ik.php?sekme=izinler&talep=' . intval($t['id']);
    if ($onayli) {
        $m = ['baslik' => '✅ İzniniz onaylandı', 'html' => '✅ <b>' . $tarih . '</b> tarihli <b>' . ik_h($tur) . '</b> talebiniz tüm onaylardan geçti ve <b>onaylandı</b>. İyi dinlenmeler!',
              'sms' => 'İzniniz onaylandı: ' . $tarih . ' tarihli ' . $tur . ' talebiniz tüm onaylardan geçti. İyi dinlenmeler!', 'buton' => 'Talebi görüntüle', 'detay' => ik_izin_detay_html($t, $kisiAd)];
    } else {
        $m = ['baslik' => '❌ İzin talebiniz reddedildi', 'html' => '❌ <b>' . $tarih . '</b> tarihli <b>' . ik_h($tur) . '</b> talebiniz <b>' . ik_h($kararVerenAd) . '</b> tarafından reddedildi.' . ($not !== '' ? '<br>Not: <i>' . ik_h($not) . '</i>' : ''),
              'sms' => 'İzin talebiniz reddedildi: ' . $tarih . ' ' . $tur . ' — ' . $kararVerenAd . ($not !== '' ? '. Not: ' . $not : ''), 'buton' => 'Talebi görüntüle', 'detay' => ik_izin_detay_html($t, $kisiAd)];
    }
    $m += ['link' => $link, 'tablo' => $onayli ? 'ik_izin_sonuc' : 'ik_izin', 'bid' => intval($t['id'])];
    ik_kisi_bildir($db, $kid, intval($kararVerenId), $akis['talep_sahibi'], $m);
    if ($onayli) foreach ($akis['bilgi'] as $b) {
        ik_kisi_bildir($db, $b['id'], $kid, ik_kanal_dizi(['k_site' => $b['site'], 'k_sms' => $b['sms'], 'k_mail' => $b['mail']]), [
            'baslik' => 'ℹ️ Onaylanan izin: ' . $kisiAd, 'html' => 'ℹ️ <b>' . ik_h($kisiAd) . '</b> adlı personelin <b>' . $tarih . '</b> tarihli <b>' . ik_h($tur) . '</b> talebi onaylandı (' . ik_gun_yaz($t['gun']) . ' gün).',
            'sms' => 'Onaylanan izin: ' . $kisiAd . ', ' . $tarih . ' ' . $tur . ' (' . ik_gun_yaz($t['gun']) . ' gün).', 'link' => $link, 'buton' => 'Talebi görüntüle', 'tablo' => 'ik_izin_sonuc', 'bid' => intval($t['id']), 'detay' => ik_izin_detay_html($t, $kisiAd)]);
    }
}

/**
 * Bu kişi bu talep üzerinde karar verebilir mi?
 * Zincirli talepte yalnızca sırası gelen onaycı (ya da kurucu); zincirsiz (eski) taleplerde "İzin taleplerini onaylama" yetkisi olanlar.
 * @return array [bool izinli, array adimlar, array|null aktifAdim]
 */
/** Kişi bugün onaylı bir izinde mi? */
function ik_izinde_mi($db, $kid)
{
    $q = $db->query("SELECT 1 FROM ik_izin_talepleri WHERE kullanici_id = " . intval($kid) . " AND durum = 'onaylandi' AND CURDATE() BETWEEN baslangic AND bitis LIMIT 1");
    return $q && $q->num_rows > 0;
}
function ik_izin_karar_yetkisi($db, $t, $uid, $Y)
{
    $adimlar = [];
    $q = $db->query("SELECT * FROM ik_izin_adimlari WHERE talep_id = " . intval($t['id']) . " ORDER BY sira, id");
    if ($q) while ($r = $q->fetch_assoc()) $adimlar[] = $r;
    if ($t['durum'] !== 'beklemede' || intval($t['kullanici_id']) === intval($uid)) return [false, $adimlar, null];
    if (!$adimlar) return [!empty($Y['ik_izin_onay']), $adimlar, null];
    $aktif = null; foreach ($adimlar as $a) if ($a['durum'] === 'bekliyor') { $aktif = $a; break; }
    if (!$aktif) return [false, $adimlar, null];
    $vekilMi = intval($aktif['vekil_id'] ?? 0) === intval($uid) && intval($uid) > 0 && ik_izinde_mi($db, $aktif['onaylayici_id']);
    return [intval($aktif['onaylayici_id']) === intval($uid) || $vekilMi || !empty($Y['kurucu']), $adimlar, $aktif];
}

/** İki tarih arasındaki iş günü (Cumartesi-Pazar hariç); yarım gün 0,5 */
function ik_is_gunu($bas, $bit, $yarim = false)
{
    $b = strtotime($bas); $e = strtotime($bit);
    if (!$b || !$e || $e < $b) return 0;
    $n = 0;
    for ($t = $b; $t <= $e; $t = strtotime('+1 day', $t)) if ((int)date('N', $t) < 6) $n++;
    return $yarim && $n === 1 ? 0.5 : (float)$n;
}

/** Kıdeme göre yasal yıllık izin hakkı (4857 s. İş Kanunu md. 53): 1-5 yıl 14, 5-15 yıl 20, 15+ yıl 26 gün */
function ik_yasal_hak($iseGiris, $ref = null)
{
    if (!$iseGiris) return null;
    $yil = (($ref ?: time()) - strtotime($iseGiris)) / (365.25 * 86400);
    if ($yil < 1) return 0.0;
    if ($yil < 5) return 14.0;
    if ($yil < 15) return 20.0;
    return 26.0;
}

/** İzin bakiye raporu tablosu + kişi kartına eklenen alanlar (bir kere kurulur) */
function ik_bakiye_sema($db)
{
    static $ok = false; if ($ok) return; $ok = true;
    try { ik_ayar_tablosu($db); if (ik_ayar($db, 'not_temiz', '') !== '1') { $db->query("UPDATE ik_izin_talepleri SET onay_notu = '' WHERE onay_notu = 'Excel içe aktarma'"); ik_ayar_yaz($db, 'not_temiz', '1'); } } catch (Throwable $e) {}
    try { if (ik_ayar($db, 'sema_bakiye', '') === '1') return; } catch (Throwable $e) {}
    $db->query("CREATE TABLE IF NOT EXISTS ik_izin_bakiye (
        kullanici_id INT NOT NULL,
        rapor_tarihi DATE NULL,
        hak_tarihi DATE NULL,
        devreden DECIMAL(7,1) NOT NULL DEFAULT 0,
        son_donem_hak DECIMAL(7,1) NULL,
        donem_bas DATE NULL,
        donem_bit DATE NULL,
        donem_kullanilan DECIMAL(7,1) NOT NULL DEFAULT 0,
        ham MEDIUMTEXT NULL,
        guncelleme DATETIME NULL,
        PRIMARY KEY (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach (['kariyer_baslangic' => 'DATE NULL', 'sirket' => "VARCHAR(120) NOT NULL DEFAULT ''"] as $kol => $tn) {
        $q = $db->query("SHOW COLUMNS FROM ik_personel LIKE '$kol'");
        if ($q && $q->num_rows === 0) $db->query("ALTER TABLE ik_personel ADD COLUMN $kol $tn");
    }
    ik_ayar_yaz($db, 'sema_bakiye', '1');
}

/** Birim / unvan listeleri (ik_ayar: departman_listesi, unvan_listesi) — her satıra bir ad */
function ik_liste_oku($db, $anahtar)
{
    $l = []; $g = [];
    foreach (preg_split('/\R/u', (string)ik_ayar($db, $anahtar, '')) as $x) { $x = trim($x); $k = mb_strtolower($x, 'UTF-8'); if ($x !== '' && !isset($g[$k])) { $g[$k] = 1; $l[] = $x; } }
    return $l;
}
/** Ayar listesi + personel kartlarında fiilen kullanılan değerler (seçim kutuları için) */
function ik_liste_birlesik($db, $anahtar, $kolon)
{
    $l = ik_liste_oku($db, $anahtar); $g = []; foreach ($l as $x) $g[mb_strtolower($x, 'UTF-8')] = 1;
    $q = $db->query("SELECT DISTINCT $kolon FROM ik_personel WHERE $kolon <> ''");
    if ($q) while ($r = $q->fetch_row()) { $x = trim((string)$r[0]); $k = mb_strtolower($x, 'UTF-8'); if ($x !== '' && !isset($g[$k])) { $g[$k] = 1; $l[] = $x; } }
    usort($l, function ($a, $b) { return strcasecmp($a, $b); });
    return $l;
}
function ik_unvan_listeye_ekle($db, $unvan)
{
    $unvan = trim((string)$unvan); if ($unvan === '') return;
    $l = ik_liste_oku($db, 'unvan_listesi');
    foreach ($l as $x) if (mb_strtolower($x, 'UTF-8') === mb_strtolower($unvan, 'UTF-8')) return;
    $l[] = $unvan; ik_ayar_yaz($db, 'unvan_listesi', implode("\n", $l));
}

/**
 * Onay / talep bildirimleri kime gitsin? Ayar (ik_ayar 'onay_alici', JSON): {"izin":{"mod":"yetkili|yonetici|secili","kisiler":[id,…]}, "masraf":…, "avans":…, "mesai":…}
 *   yetkili  → ilgili onay yetkisine sahip herkes (varsayılan)
 *   yonetici → talep sahibinin bağlı olduğu yönetici + birim müdürleri (bulunamazsa yetkililer)
 *   secili   → yalnızca seçilen kişiler (boşsa yetkililer)
 */
function ik_onay_alicilari($db, $tur, $yetki, $talepKid)
{
    $tum = array_map('intval', vy_yetkililer($db, 'ik', $yetki));
    $a = json_decode((string)ik_ayar($db, 'onay_alici', ''), true); $a = is_array($a) ? ($a[$tur] ?? []) : [];
    $mod = (string)($a['mod'] ?? 'yetkili'); $talepKid = intval($talepKid);
    $aktif = function ($ids) use ($db) { $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); if (!$ids) return []; $q = $db->query("SELECT id FROM kullanicilar WHERE aktif = 1 AND id IN (" . implode(',', $ids) . ")"); $o = []; if ($q) while ($r = $q->fetch_row()) $o[] = intval($r[0]); return $o; };
    if ($mod === 'secili') { $l = $aktif((array)($a['kisiler'] ?? [])); return $l ?: $tum; }
    if ($mod === 'yonetici') {
        $ids = [];
        $q = $db->query("SELECT yonetici_id, departman FROM ik_personel WHERE kullanici_id = $talepKid"); $r = $q ? $q->fetch_assoc() : null;
        if ($r && intval($r['yonetici_id'])) $ids[] = intval($r['yonetici_id']);
        if ($r && trim((string)$r['departman']) !== '') {
            $q2 = @$db->query("SELECT kullanici_id FROM ik_birim_yoneticileri WHERE LOWER(birim) = LOWER('" . $db->real_escape_string(trim($r['departman'])) . "')");
            if ($q2) while ($x = $q2->fetch_row()) $ids[] = intval($x[0]);
        }
        $l = array_values(array_diff($aktif($ids), [$talepKid])); return $l ?: $tum;
    }
    return $tum;
}

/** Personel bilgi formu (Word) aktarımı için kişi kartı alanları + ham form tablosu (bir kere kurulur) */
function ik_form_sema($db)
{
    static $ok = false; if ($ok) return; $ok = true;
    try { ik_ayar_tablosu($db); if (ik_ayar($db, 'sema_form', '') === '1') return; } catch (Throwable $e) {}
    $db->query("CREATE TABLE IF NOT EXISTS ik_personel_form (
        kullanici_id INT NOT NULL,
        dosya VARCHAR(200) NOT NULL DEFAULT '',
        ham MEDIUMTEXT NULL,
        guncelleme DATETIME NULL,
        PRIMARY KEY (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach (['askerlik' => "VARCHAR(80) NOT NULL DEFAULT ''", 'anne_adi' => "VARCHAR(80) NOT NULL DEFAULT ''", 'baba_adi' => "VARCHAR(80) NOT NULL DEFAULT ''", 'sgk_baslangic' => 'DATE NULL', 'email2' => "VARCHAR(150) NOT NULL DEFAULT ''", 'telefon2' => "VARCHAR(30) NOT NULL DEFAULT ''"] as $kol => $tn) {
        $q = $db->query("SHOW COLUMNS FROM ik_personel LIKE '$kol'");
        if ($q && $q->num_rows === 0) $db->query("ALTER TABLE ik_personel ADD COLUMN $kol $tn");
    }
    ik_ayar_yaz($db, 'sema_form', '1');
}

/** Hak ediş yıldönümüne göre içinde bulunulan (ya da $ref tarihindeki) izin dönemi: [başlangıç, bitiş] */
function ik_izin_donemi($hakTarihi, $ref = null)
{
    $r = strtotime($ref ?: date('Y-m-d')); $h = strtotime($hakTarihi);
    if (!$h || !$r) return null;
    $bas = strtotime(date('Y', $r) . '-' . date('m-d', $h));
    if ($bas > $r) $bas = strtotime((date('Y', $r) - 1) . '-' . date('m-d', $h));
    return [date('Y-m-d', $bas), date('Y-m-d', strtotime('+1 year -1 day', $bas))];
}

/**
 * Yıllık izin özeti. Kullanılmayan izin sonraki döneme DEVREDER:
 *   kalan = devreden + dönem hakkı − dönemde kullanılan.
 * Dönem, işe giriş / "izne esas hak ediş" yıldönümüne göredir. Devir, içe aktarılan bakiye raporundaki
 * dönemden başlayarak her dönem (hak − kullanılan) eklenerek zincirlenir; rapor yoksa devir 0'dır.
 */
function ik_izin_ozet($db, $kid)
{
    $kid = intval($kid); ik_bakiye_sema($db);
    $p = $db->query("SELECT ise_giris, yillik_izin_hak FROM ik_personel WHERE kullanici_id = $kid")->fetch_assoc();
    $b = $db->query("SELECT * FROM ik_izin_bakiye WHERE kullanici_id = $kid")->fetch_assoc() ?: null;
    $elle = $p && $p['yillik_izin_hak'] !== null;
    $hakT = ($b && $b['hak_tarihi']) ? $b['hak_tarihi'] : ($p['ise_giris'] ?? null);
    $don = $hakT ? ik_izin_donemi($hakT) : [date('Y-01-01'), date('Y-12-31')];
    $yasal = ik_yasal_hak($p['ise_giris'] ?? null);
    $kulSorgu = function ($bas, $bit, $durum, $sonra = null) use ($db, $kid) {
        $w = "kullanici_id = $kid AND tur = 'yillik' AND durum = '$durum' AND baslangic BETWEEN '$bas' AND '$bit'" . ($sonra ? " AND baslangic > '" . $db->real_escape_string($sonra) . "'" : '');
        return (float)($db->query("SELECT COALESCE(SUM(gun),0) FROM ik_izin_talepleri WHERE $w")->fetch_row()[0] ?? 0);
    };
    $devir = 0.0; $hak = $elle ? (float)$p['yillik_izin_hak'] : $yasal; $rapor = false; $kul = null;
    if ($b && $b['donem_bas'] && $b['donem_bas'] <= $don[0]) {
        $rapor = true; $pb = $b['donem_bas']; $dv = (float)$b['devreden']; $n = 0;
        while ($pb < $don[0] && $n++ < 60) {                    // rapor döneminden bugünkü döneme kadar devri zincirle
            $pbit = date('Y-m-d', strtotime("$pb +1 year -1 day")); $ilk = ($pb === $b['donem_bas']);
            $h = ($ilk && $b['son_donem_hak'] !== null) ? (float)$b['son_donem_hak'] : ($elle ? (float)$p['yillik_izin_hak'] : (float)ik_yasal_hak($b['hak_tarihi'] ?: ($p['ise_giris'] ?? null), strtotime($pb)));
            $u = $ilk ? (float)$b['donem_kullanilan'] + $kulSorgu($pb, $pbit, 'onaylandi', $b['rapor_tarihi']) : $kulSorgu($pb, $pbit, 'onaylandi');
            $dv += $h - $u; $pb = date('Y-m-d', strtotime("$pb +1 year"));
        }
        $devir = $dv;
        if ($don[0] === $b['donem_bas']) { if ($b['son_donem_hak'] !== null) $hak = (float)$b['son_donem_hak']; $kul = (float)$b['donem_kullanilan'] + $kulSorgu($don[0], $don[1], 'onaylandi', $b['rapor_tarihi']); }
        elseif (!$elle) $hak = $yasal;
    }
    if ($kul === null) $kul = $kulSorgu($don[0], $don[1], 'onaylandi');
    $bek = $kulSorgu($don[0], $don[1], 'beklemede');
    $toplam = $hak === null ? null : $hak + $devir;
    return ['hak' => $hak, 'yasal' => $yasal, 'elle' => $elle, 'devir' => $devir, 'toplam' => $toplam, 'rapor' => $rapor, 'kullanilan' => $kul, 'bekleyen' => $bek,
        'kalan' => $toplam === null ? null : $toplam - $kul, 'ise_giris' => $p['ise_giris'] ?? null, 'yil' => (int)substr($don[0], 0, 4), 'donem_bas' => $don[0], 'donem_bit' => $don[1]];
}

/** Bildirimin tıklanınca açacağı sayfa */
function ik_bildirim_link($tablo, $bid = 0)
{
    $bid = intval($bid);
    switch ($tablo) {
        case 'ik_izin': case 'ik_izin_sonuc': case 'ik_izin_hatirlatma': return 'ik.php?sekme=izinler' . ($bid && $tablo !== 'ik_izin_hatirlatma' ? '&talep=' . $bid : '&kapsam=bana');
        case 'ik_duyuru': return 'ik.php?sekme=duyurular' . ($bid ? '&id=' . $bid : '');
        case 'ik_anket': return 'ik.php?sekme=anketler' . ($bid ? '&id=' . $bid : '');
        case 'ik_dosya': return 'ik.php?sekme=dosyalar';
        case 'ik_giris': return 'ik.php?sekme=gc_cihazlar';
        case 'ik_giris_kayit': return 'ik.php?sekme=gc_kayitlar';
        case 'ik_mesai': return 'ik.php?sekme=mesailer' . ($bid ? '&id=' . $bid : '');
        case 'ik_masraf': return 'ik.php?sekme=masraflar' . ($bid ? '&id=' . $bid : '');
        case 'ik_avans': return 'ik.php?sekme=avanslar' . ($bid ? '&id=' . $bid : '');
        case 'ik_bordro': return 'ik.php?sekme=bordro';
        case 'ik_egitim': return 'ik.php?sekme=egitimler' . ($bid ? '&id=' . $bid : '');
        case 'ik_performans': return 'ik.php?sekme=performans' . ($bid ? '&id=' . $bid : '');
        case 'ik_belge_hatirlatma': case 'ik_personel': case 'ik_zimmet': return 'ik.php' . ($bid ? '?personel=' . $bid : '');
        case 'ik_ajanda': return 'ik.php?sekme=izin_takvimi';
        case 'ik_perf': return 'ik.php?sekme=performans';
        case 'ik_aday': return 'ik.php?sekme=isealim';
        default: return 'ik.php';
    }
}
/** Site içi bildirim: İK bildirim kuyruğu (zil, anlık uyarı, ses, tarayıcı/telefon bildirimi) */
function ik_bildir($db, $alici, $gonderen, $baslik, $mesaj, $tablo, $bid = 0)
{
    $r = null;
    try { ik_kuyruga_ekle($db, $alici, $baslik, $mesaj, ik_bildirim_link($tablo, $bid), $tablo); } catch (Throwable $e) {}
    return $r;
}
function ik_h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Aynı hatırlatmayı tekrar göndermemek için: son gönderim $saat saatten eskiyse true ve damgalar */
function ik_log_uygun($db, $kid, $anahtar, $saat)
{
    $kid = intval($kid); $a = $db->real_escape_string($anahtar);
    $r = $db->query("SELECT son FROM ik_hatirlatma_log WHERE kullanici_id = $kid AND anahtar = '$a'")->fetch_row();
    if ($r && strtotime($r[0]) > time() - $saat * 3600) return false;
    $db->query("INSERT INTO ik_hatirlatma_log (kullanici_id, anahtar, son) VALUES ($kid, '$a', NOW()) ON DUPLICATE KEY UPDATE son = NOW()");
    return true;
}

/**
 * İK hatırlatmaları (tb_hatirlatma.php üzerinden her sayfada, oturum başına 5 dk'da bir):
 *  • İzin onaylayıcılara: onay bekleyen talepler (2 günde bir özet)
 *  • Belge yöneticilerine: 30 gün ve 7 gün içinde süresi dolacak belgeler (her belge için bir kez)
 *  • Personel kartı görenlere: bugünkü iş yıldönümleri (günde bir)
 *  • Kişinin kendisine: iş yıldönümü kutlaması (yılda bir)
 *  (Doğum günlerini sitenin mevcut dogum_gunu.php sistemi bildirir; tekrar gönderilmez.)
 */
function ik_hatirlatma_kontrol($db, $kid)
{
    $kid = intval($kid);
    if ($kid <= 0) return 0;
    ik_sema($db);
    $y = vy_coz($db, 'ik', $kid);
    $n = 0;
    // Onay bekleyenler: sırası bende olan (zincirli) talepler + zincirsiz eski talepler (yalnızca "izin onaylama" yetkisi olanlara)
    $bek = intval($db->query("SELECT COUNT(DISTINCT t.id) FROM ik_izin_talepleri t JOIN ik_izin_adimlari a ON a.talep_id = t.id AND a.sira = t.aktif_adim AND a.durum = 'bekliyor'
                              WHERE t.durum = 'beklemede' AND a.onaylayici_id = $kid AND t.kullanici_id <> $kid AND t.olusturma_tarihi < DATE_SUB(NOW(), INTERVAL 12 HOUR)")->fetch_row()[0] ?? 0);
    if (!empty($y['ik_izin_onay']))
        $bek += intval($db->query("SELECT COUNT(*) FROM ik_izin_talepleri t WHERE t.durum = 'beklemede' AND t.kullanici_id <> $kid AND t.olusturma_tarihi < DATE_SUB(NOW(), INTERVAL 12 HOUR)
                                   AND NOT EXISTS (SELECT 1 FROM ik_izin_adimlari a WHERE a.talep_id = t.id)")->fetch_row()[0] ?? 0);
    if ($bek > 0 && ik_log_uygun($db, $kid, 'izin_bekleyen', 48)) {
        ik_bildir($db, $kid, 0, '⏰ İzin talepleri onay bekliyor', "⏰ <b>$bek izin talebi</b> onayınızı bekliyor.", 'ik_izin_hatirlatma');
        $n++;
    }
    if (!empty($y['ik_belge'])) {
        $q = $db->query("SELECT b.id, b.ad, b.tur, b.gecerlilik, b.kullanici_id, k.ad_soyad, DATEDIFF(b.gecerlilik, CURDATE()) kalan FROM ik_belgeler b
                         JOIN kullanicilar k ON k.id = b.kullanici_id
                         WHERE b.gecerlilik IS NOT NULL AND b.gecerlilik >= CURDATE() AND b.gecerlilik <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) LIMIT 30");
        if ($q) while ($r = $q->fetch_assoc()) {
            $esik = intval($r['kalan']) <= 7 ? 7 : 30;
            if (!ik_log_uygun($db, $kid, 'belge_' . $r['id'] . '_' . $esik, 24 * 400)) continue;
            $ad = $r['ad'] !== '' ? $r['ad'] : (IK_BELGE_TURLERI[$r['tur']] ?? 'Belge');
            ik_bildir($db, $kid, 0, '⏰ Belge süresi doluyor', '⏰ <b>' . ik_h($r['ad_soyad']) . '</b> — <b>' . ik_h($ad) . '</b> belgesinin süresi <b>' . date('d.m.Y', strtotime($r['gecerlilik'])) . '</b> tarihinde doluyor (' . intval($r['kalan']) . ' gün).', 'ik_belge_hatirlatma', intval($r['kullanici_id']));
            $n++;
        }
    }
    // İş yıldönümleri (doğum günlerini sitenin mevcut doğum günü sistemi — dogum_gunu.php — bildiriyor)
    $bugun = date('m-d');
    if (!empty($y['ik_personel_gor']) && ik_log_uygun($db, $kid, 'yildonumu_' . date('Y-m-d'), 20)) {
        $yd = [];
        $q = $db->query("SELECT k.ad_soyad, YEAR(CURDATE()) - YEAR(p.ise_giris) yil FROM ik_personel p JOIN kullanicilar k ON k.id = p.kullanici_id
                         WHERE k.aktif = 1 AND p.isten_cikis IS NULL AND p.ise_giris IS NOT NULL AND DATE_FORMAT(p.ise_giris, '%m-%d') = '$bugun'
                           AND YEAR(p.ise_giris) < YEAR(CURDATE()) AND k.id <> $kid");
        if ($q) while ($r = $q->fetch_row()) $yd[] = ik_h($r[0]) . ' (' . intval($r[1]) . '. yıl)';
        if ($yd) { ik_bildir($db, $kid, 0, '🎉 Bugün iş yıldönümü', '🎉 İş yıldönümü: <b>' . implode(', ', $yd) . '</b>.', 'ik_kutlama'); $n++; }
    }
    $ben = $db->query("SELECT p.ise_giris FROM ik_personel p WHERE p.kullanici_id = $kid AND p.isten_cikis IS NULL")->fetch_row();
    if ($ben && $ben[0] && date('m-d', strtotime($ben[0])) === $bugun && (int)date('Y') > (int)date('Y', strtotime($ben[0])) && ik_log_uygun($db, $kid, 'yil_' . date('Y'), 24 * 300)) {
        $yil = (int)date('Y') - (int)date('Y', strtotime($ben[0]));
        ik_bildir($db, $kid, 0, '🎉 İş yıldönümünüz kutlu olsun!', "🎉 Bugün aramızdaki <b>$yil. yılınız</b>. Emekleriniz için teşekkürler!", 'ik_kutlama');
        $n++;
    }
    return $n;
}

/**
 * Personel profil fotoğrafını İK panelinden kaydeder (kişi kendisi ya da "ik_duzenle" yetkisi olan biri).
 * Kuruma özel klasör kullanılır: uploads/k{kurum}/profil (config/baglan.php → tb_profil_klasoru).
 * Dönüş: ['ok'=>true,'resim'=>'uploads/profil_resmi/...'] ya da ['ok'=>false,'hata'=>'...']
 */
function ik_resim_kaydet($db, $hedef_id, $dosya)
{
    $hata = function ($m) { return ['ok' => false, 'hata' => $m]; };
    if (!is_array($dosya) || !isset($dosya['error'])) return $hata('Dosya seçilmedi.');
    if ($dosya['error'] !== UPLOAD_ERR_OK)
        return $hata(in_array($dosya['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            ? 'Dosya sunucunun izin verdiği boyuttan büyük (' . ini_get('upload_max_filesize') . ').' : 'Dosya yüklenirken hata oluştu.');
    $bilgi = @getimagesize($dosya['tmp_name']);
    $tur = $bilgi[2] ?? 0;
    // GÜVENLİK: tarayıcının bildirdiği türe değil, dosyanın gerçek içeriğine bakılır
    if (!$bilgi || !in_array($tur, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true))
        return $hata('Sadece JPG, PNG veya WEBP yükleyebilirsiniz.');
    if ($dosya['size'] > 3 * 1024 * 1024) return $hata('Dosya boyutu 3MB\'ı geçemez.');
    $klasor = tb_profil_klasoru();
    if (!is_dir($klasor) || !is_writable($klasor)) return $hata('uploads/profil_resmi klasörü yazılabilir değil (izinleri 755/775 yapın).');
    $uzanti = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'][$tur];
    $ad = time() . '_' . intval($hedef_id) . '.' . $uzanti;
    if (!move_uploaded_file($dosya['tmp_name'], $klasor . '/' . $ad)) return $hata('Dosya sunucuya kaydedilemedi.');

    $eski = '';
    $q = $db->query("SELECT profil_resmi FROM kullanicilar WHERE id = " . intval($hedef_id));
    if ($q && ($r = $q->fetch_row())) $eski = (string)$r[0];
    $deger = TB_PROFIL_KLASOR . '/' . $ad;
    $st = $db->prepare("UPDATE kullanicilar SET profil_resmi = ? WHERE id = ?");
    $hid = intval($hedef_id);
    if (!$st || !$st->bind_param('si', $deger, $hid) || !$st->execute()) {
        @unlink($klasor . '/' . $ad);
        return $hata('Fotoğraf veritabanına kaydedilemedi.' . ($db->hata() ? ' (' . $db->hata() . ')' : ''));
    }
    // Kişinin bu klasördeki bir önceki fotoğrafı silinir (klasör şişmesin)
    if (preg_match('~^' . TB_PROFIL_KLASOR . '/(\d+_' . $hid . '\.(jpg|png|webp))$~', $eski, $m) && $m[1] !== $ad) @unlink($klasor . '/' . $m[1]);
    return ['ok' => true, 'resim' => tb_profil_resmi_yolu($deger)];
}

} // IK_ORTAK
