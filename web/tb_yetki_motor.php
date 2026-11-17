<?php
// ============================================================
// tb_yetki_motor.php — Modül yetkileri (İş Takip, İK …) için ortak motor
// Kurallar modul_yetkileri tablosunda: (modul, yetki, hedef_tipi, hedef_deger, izin 1/0)
// Öncelik: kullanıcı › görev › rol › herkes › modülün varsayılanı. Kurucu her zaman tam yetkili.
// Sayfa içindeki "Yetkiler" penceresi (assets/js/tb-yetki-panel.js) tb_yetki_islem.php ile yönetir.
// Çok kurumlu sürüm: tablolar kurum ön ekiyle (k{no}_) ayrılır; "kurucu" rolü = kurum yetkilisi (kurumdaki tüm yetkiler).
// ============================================================
if (!defined('VY_YUKLENDI')) {
define('VY_YUKLENDI', true);

/**
 * Modül ve yetki tanımları. varsayilan: herkes için 0/1, roller: rol => 0/1 (kural yoksa geçerli)
 */
function vy_tanimlar()
{
    return [
        'ik' => [
            'baslik' => 'İnsan Kaynakları',
            'yetkiler' => [
                'ik_erisim'       => ['ad' => 'İK sayfası (kendi kartı + izin talebi)', 'herkes' => 1, 'roller' => []],
                'ik_kullanici_yonet' => ['ad' => 'Kullanıcı ekleme / düzenleme / pasifleştirme', 'herkes' => 0, 'roller' => []],
                'ik_personel_gor' => ['ad' => 'Tüm personel kartlarını görme',          'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_duzenle'      => ['ad' => 'Personel bilgilerini düzenleme',         'herkes' => 0, 'roller' => []],
                'ik_maas'         => ['ad' => 'Maaş / IBAN görme ve düzenleme',         'herkes' => 0, 'roller' => []],
                'ik_izin_onay'    => ['ad' => 'İzin taleplerini onaylama',              'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_belge'        => ['ad' => 'Belge ve zimmet yönetimi',               'herkes' => 0, 'roller' => []],
                'ik_puantaj_yonet'   => ['ad' => 'Puantaj (devam) girme/düzenleme',       'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_masraf_onay'     => ['ad' => 'Personel masraf taleplerini onaylama',  'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_avans_onay'      => ['ad' => 'Personel avans taleplerini onaylama',   'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_egitim_yonet'    => ['ad' => 'Eğitim kaydı oluşturma/düzenleme',      'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_performans_yonet'=> ['ad' => 'Performans değerlendirmesi girme',      'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_bordro_yonet'    => ['ad' => 'Bordro kaydı ekleme/düzenleme',         'herkes' => 0, 'roller' => []],
                'ik_ayarlar'         => ['ad' => 'İK ayarlarını değiştirme',              'herkes' => 0, 'roller' => []],
                'ik_giris_gor'       => ['ad' => 'Giriş-çıkış: tüm personelin giriş-çıkış kayıtlarını görme', 'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_giris_yonet'     => ['ad' => 'Giriş-çıkış: kayıt düzeltme, cihaz onayı, PIN sıfırlama, nokta ve ayarlar', 'herkes' => 0, 'roller' => []],
                'ik_akis_yonet'      => ['ad' => 'İzin onay akışı + bildirim kanalları (SMS / e-posta) yönetimi', 'herkes' => 0, 'roller' => []],
                'ik_duyuru_yonet'    => ['ad' => 'Duyuru yayınlama, dosya paylaşma ve anket oluşturma',   'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_rapor_gor'       => ['ad' => 'Raporlar: geç kalma, izin, avans ve mesai raporlarını alma (tüm personel)', 'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_mesai_yonet'     => ['ad' => 'Mesai: personel adına mesai girme, mesai onaylama, resmî tatil günlerini düzenleme', 'herkes' => 0, 'roller' => ['yetkili' => 1]],
                'ik_menu_mesai'      => ['ad' => 'Menü: Mesai sayfasını görme (kendi mesaisini bildirme)', 'herkes' => 1, 'roller' => []],
                'ik_menu_ozellik'    => ['ad' => 'Menü: Site özellikleri (yardım) sayfasını görme', 'herkes' => 1, 'roller' => []],
                'ik_menu_duyuru'     => ['ad' => 'Menü: Duyurular, Dosyalar ve Anketler sayfalarını görme', 'herkes' => 1, 'roller' => []],
                'ik_menu_takvim'     => ['ad' => 'Menü: İzin takvimini görme',                 'herkes' => 1, 'roller' => []],
                'ik_menu_puantaj'    => ['ad' => 'Menü: Puantaj sayfasını görme',              'herkes' => 1, 'roller' => []],
                'ik_menu_masraf'     => ['ad' => 'Menü: Personel masrafları sayfasını görme',  'herkes' => 1, 'roller' => []],
                'ik_menu_avans'      => ['ad' => 'Menü: Avans talepleri sayfasını görme',      'herkes' => 1, 'roller' => []],
                'ik_menu_egitim'     => ['ad' => 'Menü: Eğitimler sayfasını görme',            'herkes' => 1, 'roller' => []],
                'ik_menu_performans' => ['ad' => 'Menü: Performans sayfasını görme',           'herkes' => 0, 'roller' => ['yetkili' => 1]],   // varsayılan: yalnızca yetkili/kurucu; personele yetki ekranından verilir
                'ik_menu_bordro'     => ['ad' => 'Menü: Bordro kayıtları sayfasını görme',     'herkes' => 1, 'roller' => []],
            ],
        ],
    ];
}

function vy_tablo($db)
{
    static $tamam = false;
    if ($tamam) return;
    $tamam = true;
    if ($db->query("SELECT 1 FROM modul_yetkileri LIMIT 1")) return;
    $db->query("CREATE TABLE IF NOT EXISTS modul_yetkileri (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        modul VARCHAR(30) NOT NULL,
        yetki VARCHAR(40) NOT NULL,
        hedef_tipi VARCHAR(20) NOT NULL,
        hedef_deger VARCHAR(150) NOT NULL DEFAULT '',
        izin TINYINT(1) NOT NULL,
        guncelleyen_id INT NULL,
        guncelleme_tarihi DATETIME NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_modul_yetki (modul, yetki, hedef_tipi, hedef_deger),
        KEY ix_modul (modul)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function vy_kucuk($s) { return trim(mb_strtolower((string)$s, 'UTF-8')); }

function vy_kullanici($db, $kid)
{
    static $c = [];
    $kid = intval($kid);
    if (isset($c[$kid])) return $c[$kid];
    $k = ['id' => $kid, 'ad_soyad' => '', 'rol' => '', 'gorev' => '', 'gorev_ham' => '', 'birim' => '', 'unvan' => '', 'kurucu' => false];
    $q = $db->query("SELECT ad_soyad, rol, gorev FROM kullanicilar WHERE id = $kid LIMIT 1");
    if ($q && ($r = $q->fetch_assoc())) {
        $k['ad_soyad'] = (string)$r['ad_soyad'];
        $k['rol'] = vy_kucuk($r['rol']);
        $k['gorev_ham'] = trim((string)($r['gorev'] ?? ''));
        $k['gorev'] = vy_kucuk($k['gorev_ham']);
        $k['kurucu'] = $k['rol'] === 'kurucu';
    }
    // Birim (departman) ve unvan: İK kartından (tablo yoksa sessizce atlanır)
    $q2 = @$db->query("SELECT departman, unvan FROM ik_personel WHERE kullanici_id = $kid LIMIT 1");
    if ($q2 && ($r2 = $q2->fetch_assoc())) { $k['birim'] = vy_kucuk($r2['departman']); $k['unvan'] = vy_kucuk($r2['unvan']); }
    return $c[$kid] = $k;
}

function vy_kurallar($db, $modul, $yenile = false)
{
    static $c = [];
    if (!$yenile && isset($c[$modul])) return $c[$modul];
    $m = $db->real_escape_string($modul);
    $kurallar = [];
    $q = $db->query("SELECT id, yetki, hedef_tipi, hedef_deger, izin FROM modul_yetkileri WHERE modul = '$m'");
    if ($q) while ($r = $q->fetch_assoc()) $kurallar[] = $r;
    return $c[$modul] = $kurallar;
}

/** Modüldeki tüm yetkiler için karar: ['is_olustur' => true, ..., 'kurucu' => bool] */
function vy_coz($db, $modul, $kid)
{
    static $c = [];
    $anah = $modul . ':' . intval($kid);
    if (isset($c[$anah])) return $c[$anah];
    vy_tablo($db);
    $tan = vy_tanimlar()[$modul] ?? null;
    $k = vy_kullanici($db, $kid);
    $y = ['kurucu' => $k['kurucu']];
    if (!$tan) return $c[$anah] = $y;
    if ($k['kurucu']) { foreach ($tan['yetkiler'] as $a => $_) $y[$a] = true; return $c[$anah] = $y; }

    $kurallar = vy_kurallar($db, $modul);

    foreach ($tan['yetkiler'] as $a => $t) {
        $seviye = [null, null, null, null, null, null]; // kullanıcı, unvan, birim, görev, rol, herkes
        foreach ($kurallar as $r) {
            if ($r['yetki'] !== $a) continue;
            $d = (string)$r['hedef_deger'];
            if ($r['hedef_tipi'] === 'kullanici' && $d === (string)intval($kid)) $seviye[0] = intval($r['izin']);
            elseif ($r['hedef_tipi'] === 'unvan' && $k['unvan'] !== '' && vy_kucuk($d) === $k['unvan']) $seviye[1] = intval($r['izin']);
            elseif ($r['hedef_tipi'] === 'birim' && $k['birim'] !== '' && vy_kucuk($d) === $k['birim']) $seviye[2] = intval($r['izin']);
            elseif ($r['hedef_tipi'] === 'gorev' && $k['gorev'] !== '' && vy_kucuk($d) === $k['gorev']) $seviye[3] = intval($r['izin']);
            elseif ($r['hedef_tipi'] === 'rol' && $k['rol'] !== '' && vy_kucuk($d) === $k['rol']) $seviye[4] = intval($r['izin']);
            elseif ($r['hedef_tipi'] === 'herkes') $seviye[5] = intval($r['izin']);
        }
        $karar = null;
        foreach ($seviye as $s) if ($s !== null) { $karar = $s; break; }
        if ($karar === null) $karar = isset($t['roller'][$k['rol']]) ? intval($t['roller'][$k['rol']]) : intval($t['herkes']);
        $y[$a] = $karar === 1;
    }
    return $c[$anah] = $y;
}

/** Tek yetki kısayolu */
function vy_var($db, $modul, $kid, $yetki)
{
    $y = vy_coz($db, $modul, $kid);
    return !empty($y[$yetki]);
}

/** Bir yetkiye sahip aktif kullanıcıların kimlikleri (bildirim göndermek için) */
function vy_yetkililer($db, $modul, $yetki)
{
    $ids = [];
    $q = $db->query("SELECT id FROM kullanicilar WHERE aktif = 1");
    if ($q) while ($r = $q->fetch_row()) if (vy_var($db, $modul, intval($r[0]), $yetki)) $ids[] = intval($r[0]);
    return $ids;
}

} // VY_YUKLENDI
