<?php
// ============================================================
// duyuru_ortak.php — Duyurular · Dosya paylaşımı · Anketler (İK sayfasının eki)
//   Tablolar (kuruma özel, VDB::TABLOLAR): ik_duyurular, ik_dosyalar, ik_dosya_indirme, ik_okumalar,
//                                           ik_anketler, ik_anket_sorular, ik_anket_katilim, ik_anket_cevaplar
//   Hedef kitle: hedef_tip = 'herkes' | 'secili' (hedef_birim = JSON birim adları, hedef_kisi = JSON kullanıcı id'leri; ikisinin birleşimi)
//   Uç nokta: duyuru_islem.php · Arayüz: assets/js/tb-ik-duyuru.js
// ============================================================
require_once __DIR__ . '/ik_ortak.php';

/** Yüklenebilir dosya türleri (uzantı → mime) */
define('DY_UZANTI', [
    'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif',
    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'odt' => 'application/vnd.oasis.opendocument.text', 'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    'txt' => 'text/plain', 'csv' => 'text/csv', 'zip' => 'application/zip',
]);
define('DY_DOSYA_LIMIT', 25 * 1024 * 1024);
define('DY_TURLER', ['genel', 'onemli', 'acil', 'etkinlik', 'bilgi']);

function dy_sema($db)
{
    static $y = false;
    if ($y) return;
    $y = true;
    try { if (ik_ayar($db, 'duyuru_sema_v1') === '1') return; } catch (Throwable $e) {}
    $ek = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->query("CREATE TABLE IF NOT EXISTS ik_duyurular (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        baslik VARCHAR(200) NOT NULL,
        icerik MEDIUMTEXT NULL,
        tur VARCHAR(12) NOT NULL DEFAULT 'genel',
        sabit TINYINT(1) NOT NULL DEFAULT 0,
        hedef_tip VARCHAR(8) NOT NULL DEFAULT 'herkes',
        hedef_birim TEXT NULL,
        hedef_kisi TEXT NULL,
        durum VARCHAR(10) NOT NULL DEFAULT 'yayinda',
        onay_iste TINYINT(1) NOT NULL DEFAULT 0,
        bitis DATE NULL,
        olusturan_id INT NOT NULL DEFAULT 0,
        olusturma DATETIME NULL,
        guncelleme DATETIME NULL,
        yayin DATETIME NULL,
        PRIMARY KEY (id), KEY ix_dy_durum (durum, sabit, yayin)
    ) $ek");
    $db->query("CREATE TABLE IF NOT EXISTS ik_dosyalar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ad VARCHAR(200) NOT NULL,
        aciklama VARCHAR(500) NOT NULL DEFAULT '',
        klasor VARCHAR(80) NOT NULL DEFAULT '',
        dosya VARCHAR(120) NOT NULL,
        orijinal_ad VARCHAR(255) NOT NULL DEFAULT '',
        mime VARCHAR(120) NOT NULL DEFAULT '',
        boyut INT UNSIGNED NOT NULL DEFAULT 0,
        hedef_tip VARCHAR(8) NOT NULL DEFAULT 'herkes',
        hedef_birim TEXT NULL,
        hedef_kisi TEXT NULL,
        indirme_tip VARCHAR(8) NOT NULL DEFAULT 'gorenler',
        indirme_kisi TEXT NULL,
        duyuru_id INT UNSIGNED NULL,
        yukleyen_id INT NOT NULL DEFAULT 0,
        tarih DATETIME NULL,
        indirme_say INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id), KEY ix_df_duyuru (duyuru_id), KEY ix_df_klasor (klasor)
    ) $ek");
    $db->query("CREATE TABLE IF NOT EXISTS ik_dosya_indirme (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        dosya_id INT UNSIGNED NOT NULL,
        kullanici_id INT NOT NULL,
        zaman DATETIME NOT NULL,
        PRIMARY KEY (id), KEY ix_di_dosya (dosya_id)
    ) $ek");
    $db->query("CREATE TABLE IF NOT EXISTS ik_okumalar (
        tip VARCHAR(10) NOT NULL,
        oge_id INT UNSIGNED NOT NULL,
        kullanici_id INT NOT NULL,
        zaman DATETIME NOT NULL,
        onay TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (tip, oge_id, kullanici_id), KEY ix_ok_kisi (kullanici_id)
    ) $ek");
    $db->query("CREATE TABLE IF NOT EXISTS ik_anketler (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        baslik VARCHAR(200) NOT NULL,
        aciklama TEXT NULL,
        hedef_tip VARCHAR(8) NOT NULL DEFAULT 'herkes',
        hedef_birim TEXT NULL,
        hedef_kisi TEXT NULL,
        anonim TINYINT(1) NOT NULL DEFAULT 0,
        sonuc_goster VARCHAR(10) NOT NULL DEFAULT 'katilan',
        bitis DATETIME NULL,
        durum VARCHAR(10) NOT NULL DEFAULT 'acik',
        olusturan_id INT NOT NULL DEFAULT 0,
        olusturma DATETIME NULL,
        PRIMARY KEY (id), KEY ix_an_durum (durum)
    ) $ek");
    $db->query("CREATE TABLE IF NOT EXISTS ik_anket_sorular (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        anket_id INT UNSIGNED NOT NULL,
        sira SMALLINT NOT NULL DEFAULT 1,
        tip VARCHAR(8) NOT NULL DEFAULT 'tek',
        soru VARCHAR(500) NOT NULL,
        secenekler TEXT NULL,
        zorunlu TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id), KEY ix_as_anket (anket_id, sira)
    ) $ek");
    $db->query("CREATE TABLE IF NOT EXISTS ik_anket_katilim (
        anket_id INT UNSIGNED NOT NULL,
        kullanici_id INT NOT NULL,
        zaman DATETIME NOT NULL,
        PRIMARY KEY (anket_id, kullanici_id)
    ) $ek");
    $db->query("CREATE TABLE IF NOT EXISTS ik_anket_cevaplar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        anket_id INT UNSIGNED NOT NULL,
        soru_id INT UNSIGNED NOT NULL,
        yanit_no INT UNSIGNED NOT NULL,
        kullanici_id INT NULL,
        deger TEXT NULL,
        zaman DATETIME NOT NULL,
        PRIMARY KEY (id), KEY ix_ac_anket (anket_id, soru_id)
    ) $ek");
    ik_ayar_yaz($db, 'duyuru_sema_v1', '1');
}

/* ---------------- kullanıcı / hedef kitle ---------------- */
function dy_birim($r) { $d = trim((string)($r['departman'] ?? '')); if ($d === '') $d = trim((string)($r['gorev'] ?? '')); return $d !== '' ? $d : 'Belirtilmemiş'; }
function dy_kucuk($s) { return mb_strtolower(trim((string)$s), 'UTF-8'); }

/** Etkin tüm kullanıcılar: [id => ['id','ad','birim','gorev','email','telefon','renk','resim']] (istek başına bir kez) */
function dy_kullanicilar($db)
{
    static $c = null;
    if ($c !== null) return $c;
    $c = [];
    $q = $db->query("SELECT k.id, k.ad_soyad, k.gorev, k.email, k.telefon, k.avatar_renk, k.profil_resmi, p.departman FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND (p.isten_cikis IS NULL OR p.isten_cikis > CURDATE()) ORDER BY k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) {
        $resim = function_exists('tb_profil_resmi_src') ? tb_profil_resmi_src($r['profil_resmi'] ?? '') : '';
        $c[intval($r['id'])] = ['id' => intval($r['id']), 'ad' => (string)$r['ad_soyad'], 'birim' => dy_birim($r), 'gorev' => (string)($r['gorev'] ?? ''), 'email' => (string)$r['email'], 'telefon' => (string)$r['telefon'], 'renk' => (string)$r['avatar_renk'], 'resim' => $resim];
    }
    return $c;
}
function dy_json_dizi($s) { $a = json_decode((string)$s, true); return is_array($a) ? $a : []; }

/** İstekten hedef kitleyi okur → ['tip','birim'(JSON),'kisi'(JSON)] */
function dy_hedef_oku($g, $prefix = 'hedef_')
{
    $tip = ($g[$prefix . 'tip'] ?? 'herkes') === 'secili' ? 'secili' : 'herkes';
    $birim = []; $kisi = [];
    if ($tip === 'secili') {
        foreach (dy_json_dizi($g[$prefix . 'birim'] ?? '') as $b) { $b = mb_substr(trim((string)$b), 0, 100, 'UTF-8'); if ($b !== '') $birim[dy_kucuk($b)] = $b; }
        foreach (dy_json_dizi($g[$prefix . 'kisi'] ?? '') as $k) { $k = intval($k); if ($k > 0) $kisi[$k] = $k; }
        if (!$birim && !$kisi) $tip = 'herkes';
    }
    return ['tip' => $tip, 'birim' => json_encode(array_values($birim), JSON_UNESCAPED_UNICODE), 'kisi' => json_encode(array_values($kisi))];
}

/** Kullanıcı bu hedef kitlede mi? (kayıt: hedef_tip, hedef_birim, hedef_kisi) */
function dy_hedefte($r, $kid, $birimKucuk)
{
    if (($r['hedef_tip'] ?? 'herkes') !== 'secili') return true;
    if (in_array(intval($kid), array_map('intval', dy_json_dizi($r['hedef_kisi'] ?? '')), true)) return true;
    foreach (dy_json_dizi($r['hedef_birim'] ?? '') as $b) if (dy_kucuk($b) === $birimKucuk) return true;
    return false;
}
/** Hedef kitledeki etkin kullanıcı kimlikleri */
function dy_alicilar($db, $r)
{
    $out = [];
    foreach (dy_kullanicilar($db) as $id => $k) if (dy_hedefte($r, $id, dy_kucuk($k['birim']))) $out[] = $id;
    return $out;
}
function dy_hedef_ozet($r)
{
    if (($r['hedef_tip'] ?? 'herkes') !== 'secili') return 'Herkes';
    $b = dy_json_dizi($r['hedef_birim'] ?? ''); $k = dy_json_dizi($r['hedef_kisi'] ?? ''); $p = [];
    if ($b) $p[] = count($b) === 1 ? $b[0] : $b[0] . ' +' . (count($b) - 1) . ' birim';
    if ($k) $p[] = count($k) . ' kişi';
    return implode(' · ', $p) ?: 'Herkes';
}
function dy_kullanici_birim($db, $kid) { $k = dy_kullanicilar($db)[intval($kid)] ?? null; return $k ? dy_kucuk($k['birim']) : dy_kucuk(ik_kisi_departman($db, $kid)); }

/* ---------------- bildirim ---------------- */
function dy_kanal($g)
{
    return ['site' => !isset($g['bildir_site']) || $g['bildir_site'] !== '0', 'mail' => ($g['bildir_mail'] ?? '0') === '1', 'sms' => ($g['bildir_sms'] ?? '0') === '1'];
}
/**
 * Hedef kitleye haber verir. $m = ['baslik','html','sms','buton','tablo','bid','link']. Gönderen kendisine bildirim almaz.
 * @return int alıcı sayısı
 */
function dy_bildir($db, $alicilar, $gonderen, $kanal, $m)
{
    $n = 0;
    foreach ($alicilar as $a) {
        if (intval($a) === intval($gonderen)) continue;
        try { ik_kisi_bildir($db, $a, $gonderen, $kanal, $m + ['pdf' => false]); $n++; } catch (Throwable $e) {}
    }
    return $n;
}
function dy_ozet_metin($s, $n = 220)
{
    $s = trim(preg_replace('/\s+/u', ' ', (string)$s));
    return mb_strlen($s, 'UTF-8') > $n ? rtrim(mb_substr($s, 0, $n, 'UTF-8')) . '…' : $s;
}
