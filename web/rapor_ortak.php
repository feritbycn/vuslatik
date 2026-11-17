<?php
// ============================================================
// rapor_ortak.php — İK raporları (geç kalma · izin · avans · mesai) + mesai kaydı + resmî tatiller
//   rapor_islem.php (JSON / PDF uç noktası) ve rapor_pdf.php kullanır.
//   Her rapor aynı biçimde döner: başlık, özet kutuları, sütunlar, satırlar, grafikler, ayrıntı tabloları.
// ============================================================
require_once __DIR__ . '/ik_ortak.php';
require_once __DIR__ . '/giris_ortak.php';

const RP_GUN_AD = [1 => 'Pazartesi', 2 => 'Salı', 3 => 'Çarşamba', 4 => 'Perşembe', 5 => 'Cuma', 6 => 'Cumartesi', 7 => 'Pazar'];
const RP_AY_AD = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
const RP_MESAI_TUR = ['normal' => 'Hafta içi', 'haftasonu' => 'Hafta sonu', 'tatil' => 'Resmî tatil'];
/** Bayramların 1. günü (Diyanet takvimi). Bu listede olmayan yıllar için "Tatil günleri" ekranından elle eklenebilir. */
const RP_RAMAZAN = [2024 => '2024-04-10', 2025 => '2025-03-30', 2026 => '2026-03-20', 2027 => '2027-03-09', 2028 => '2028-02-26', 2029 => '2029-02-14', 2030 => '2030-02-04'];
const RP_KURBAN = [2024 => '2024-06-16', 2025 => '2025-06-06', 2026 => '2026-05-27', 2027 => '2027-05-16', 2028 => '2028-05-05', 2029 => '2029-04-24', 2030 => '2030-04-13'];

function rp_sema($db)
{
    static $y = false;
    if ($y) return;
    $y = true;
    try { ik_ayar_tablosu($db); if (ik_ayar($db, 'rapor_sema_v1') === '1') return; } catch (Throwable $e) {}
    $db->query("CREATE TABLE IF NOT EXISTS ik_mesailer (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        tarih DATE NOT NULL,
        baslangic CHAR(5) NOT NULL DEFAULT '',
        bitis CHAR(5) NOT NULL DEFAULT '',
        dakika INT NOT NULL DEFAULT 0,
        tur VARCHAR(12) NOT NULL DEFAULT 'normal',
        tatil_adi VARCHAR(100) NOT NULL DEFAULT '',
        aciklama VARCHAR(300) NOT NULL DEFAULT '',
        durum VARCHAR(12) NOT NULL DEFAULT 'onaylandi',
        giren_id INT NULL,
        karar_veren_id INT NULL,
        karar_tarihi DATETIME NULL,
        olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_mesai_kisi (kullanici_id, tarih),
        KEY ix_mesai_tarih (tarih)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_tatiller (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        tarih DATE NOT NULL,
        ad VARCHAR(100) NOT NULL,
        yarim TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        UNIQUE KEY uq_tatil (tarih)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    ik_ayar_yaz($db, 'rapor_sema_v1', '1');
}

/* ───────────────────────── Tarih / biçim yardımcıları ───────────────────────── */
function rp_tarih_gecerli($s) { return preg_match('/^\d{4}-\d{2}-\d{2}$/D', (string)$s) && strtotime($s) !== false; }
function rp_tr($ymd) { return $ymd ? date('d.m.Y', strtotime($ymd)) : ''; }
function rp_dk($t) { return preg_match('/^(\d{1,2}):(\d{2})/', (string)$t, $m) ? intval($m[1]) * 60 + intval($m[2]) : null; }
function rp_saat_yaz($dk) { $dk = max(0, intval($dk)); return sprintf('%02d:%02d', intdiv($dk, 60) % 24, $dk % 60); }
/** 135 → "2 sa 15 dk" */
function rp_sure($dk)
{
    $dk = intval(round($dk)); if ($dk <= 0) return '—';
    $s = intdiv($dk, 60); $d = $dk % 60;
    return ($s ? $s . ' sa' : '') . ($s && $d ? ' ' : '') . ($d ? $d . ' dk' : '');
}
function rp_saat_ond($dk) { return number_format(intval($dk) / 60, 2, ',', '.'); }
function rp_para($v) { return number_format((float)$v, 2, ',', '.') . ' ₺'; }
function rp_gun_say($g) { return str_replace('.', ',', rtrim(rtrim(number_format((float)$g, 1, '.', ''), '0'), '.')) ?: '0'; }
function rp_kucuk($s) { return mb_strtolower(trim((string)$s), 'UTF-8'); }

/* ───────────────────────── Resmî tatiller ───────────────────────── */
/** [tarih => ['ad' => , 'yarim' => bool]] — yerleşik takvim + İK'nın eklediği günler */
function rp_tatil_haritasi($db, $bas, $bit)
{
    rp_sema($db);
    $h = [];
    $y1 = (int)date('Y', strtotime($bas)); $y2 = (int)date('Y', strtotime($bit));
    $ekle = function ($t, $ad, $yarim = false) use (&$h) { if (!isset($h[$t]) || (!$yarim && !empty($h[$t]['yarim']))) $h[$t] = ['ad' => $ad, 'yarim' => $yarim]; };
    for ($y = $y1 - 0; $y <= $y2; $y++) {
        $ekle("$y-01-01", 'Yılbaşı');
        $ekle("$y-04-23", 'Ulusal Egemenlik ve Çocuk Bayramı');
        $ekle("$y-05-01", 'Emek ve Dayanışma Günü');
        $ekle("$y-05-19", 'Atatürk’ü Anma, Gençlik ve Spor Bayramı');
        $ekle("$y-07-15", 'Demokrasi ve Millî Birlik Günü');
        $ekle("$y-08-30", 'Zafer Bayramı');
        $ekle("$y-10-28", 'Cumhuriyet Bayramı arifesi', true);
        $ekle("$y-10-29", 'Cumhuriyet Bayramı');
        if (isset(RP_RAMAZAN[$y])) {
            $t = strtotime(RP_RAMAZAN[$y]); $ekle(date('Y-m-d', strtotime('-1 day', $t)), 'Ramazan Bayramı arifesi', true);
            for ($i = 0; $i < 3; $i++) $ekle(date('Y-m-d', strtotime("+$i day", $t)), 'Ramazan Bayramı ' . ($i + 1) . '. gün');
        }
        if (isset(RP_KURBAN[$y])) {
            $t = strtotime(RP_KURBAN[$y]); $ekle(date('Y-m-d', strtotime('-1 day', $t)), 'Kurban Bayramı arifesi', true);
            for ($i = 0; $i < 4; $i++) $ekle(date('Y-m-d', strtotime("+$i day", $t)), 'Kurban Bayramı ' . ($i + 1) . '. gün');
        }
    }
    $q = $db->query("SELECT tarih, ad, yarim FROM ik_tatiller WHERE tarih BETWEEN '" . $db->real_escape_string($bas) . "' AND '" . $db->real_escape_string($bit) . "'");
    if ($q) while ($r = $q->fetch_assoc()) $h[$r['tarih']] = ['ad' => $r['ad'], 'yarim' => intval($r['yarim']) === 1, 'ozel' => true];
    ksort($h);
    return array_filter($h, function ($k) use ($bas, $bit) { return $k >= $bas && $k <= $bit; }, ARRAY_FILTER_USE_KEY);
}
/** Bir günün mesai türü: ['normal'|'haftasonu'|'tatil', tatil adı] (tam gün tatil > hafta sonu > hafta içi) */
function rp_gun_turu($tarih, array $tatiller, array $calismaGunleri)
{
    $n = (int)date('N', strtotime($tarih));
    if (isset($tatiller[$tarih]) && empty($tatiller[$tarih]['yarim'])) return ['tatil', $tatiller[$tarih]['ad'] . (!in_array($n, $calismaGunleri, true) ? ' (' . RP_GUN_AD[$n] . ' gününe denk gelir)' : '')];
    if (!in_array($n, $calismaGunleri, true)) return ['haftasonu', RP_GUN_AD[$n]];
    return ['normal', isset($tatiller[$tarih]) ? $tatiller[$tarih]['ad'] : ''];
}
function rp_calisma_gunleri($db)
{
    $A = giris_ayarlar($db);
    $g = array_values(array_filter(array_map('intval', explode(',', (string)($A['calisma_gunleri'] ?? '1,2,3,4,5')))));
    return $g ?: [1, 2, 3, 4, 5];
}

/* ───────────────────────── Personel süzme ───────────────────────── */
/** [id => ['id','ad','birim','gorev']] — birim ve/veya kişi seçimine göre (ikisi de boşsa tüm etkin personel) */
function rp_kisiler($db, array $birimler, array $kisiler, $bas = null)
{
    $kosul = "k.aktif = 1";
    if ($bas) $kosul .= " AND (p.isten_cikis IS NULL OR p.isten_cikis >= '" . $db->real_escape_string($bas) . "')";
    $q = $db->query("SELECT k.id, k.ad_soyad, k.gorev, p.departman FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE $kosul ORDER BY k.ad_soyad");
    $b = []; foreach ($birimler as $x) $b[rp_kucuk($x)] = true;
    $k = array_flip(array_map('intval', $kisiler));
    $hepsi = !$b && !$k; $out = [];
    if ($q) while ($r = $q->fetch_assoc()) {
        $id = intval($r['id']); $birim = rp_birim_adi($r);
        if ($hepsi || isset($k[$id]) || isset($b[rp_kucuk($birim)])) $out[$id] = ['id' => $id, 'ad' => (string)$r['ad_soyad'], 'birim' => $birim, 'gorev' => (string)($r['gorev'] ?? '')];
    }
    return $out;
}
function rp_birimler($db)
{
    $s = [];
    $q = $db->query("SELECT p.departman, k.gorev FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1");
    if ($q) while ($r = $q->fetch_assoc()) $s[rp_birim_adi($r)] = true;
    $l = array_keys($s); sort($l, SORT_LOCALE_STRING);
    return $l;
}

/* ───────────────────────── Rapor tanımları (arayüz bunları okur) ───────────────────────── */
function rp_tanimlar()
{
    return rp_tanimlar_temel() + rp_ek_tanimlar();
}
function rp_tanimlar_temel()
{
    return [
        'devam' => ['ad' => 'Giriş-çıkış ve geç kalma', 'ikon' => 'fa-clock', 'renk' => '#f59e0b',
            'aciklama' => 'Kim kaçta girip çıkmış, kaç gün ve toplam kaç dakika geç kalmış; en çok geç kalandan aza sıralanır.',
            'siralamalar' => ['gec_dk' => 'Toplam geç kalma (çoktan aza)', 'gec_gun' => 'Geç kalınan gün (çoktan aza)', 'devamsiz' => 'Devamsız gün (çoktan aza)', 'sure' => 'Toplam çalışma süresi', 'ad' => 'Ada göre (A-Z)'],
            'secenekler' => [
                ['k' => 'sadece_gec', 'ad' => 'Yalnızca geç kalanlar', 'tip' => 'bool', 'v' => false],
                ['k' => 'kayitsiz_gizle', 'ad' => 'Hiç giriş-çıkış kaydı olmayanları gizle', 'tip' => 'bool', 'v' => false],
                ['k' => 'detay', 'ad' => 'Gün gün giriş-çıkış listesi', 'tip' => 'sec', 'v' => 'yok', 'sec' => ['yok' => 'Ekleme', 'sorunlu' => 'Yalnızca geç / erken / devamsız günler', 'tum' => 'Tüm günler']],
            ],
            'sutunlar' => [['gun', 'Geldiği gün', 1], ['gec_gun', 'Geç kalınan gün', 1], ['gec_dk', 'Toplam geç kalma', 1], ['ort_gec', 'Ortalama geç kalma', 0], ['en_gec', 'En uzun geç kalma', 1], ['erken_cik', 'Erken çıkış', 1],
                ['devamsiz', 'Devamsız gün', 1], ['izinli', 'İzinli gün', 1], ['sure', 'Toplam çalışma', 1], ['hs_gun', 'Hafta sonu / tatil çalışması', 0], ['vardiya', 'Vardiya', 0], ['birim', 'Birim', 1]]],
        'izin' => ['ad' => 'İzin kullanımı', 'ikon' => 'fa-umbrella-beach', 'renk' => '#10b981',
            'aciklama' => 'Seçilen dönemde kim kaç gün, hangi türde izin kullanmış; şu an izinde olanlar ve işe dönüş tarihleri.',
            'siralamalar' => ['toplam' => 'Toplam izin günü (çoktan aza)', 'ad' => 'Ada göre (A-Z)', 'talep' => 'Talep sayısı'],
            'secenekler' => [
                ['k' => 'bekleyen', 'ad' => 'Onay bekleyen talepleri de say', 'tip' => 'bool', 'v' => false],
                ['k' => 'sifir', 'ad' => 'İzin kullanmayanları da göster', 'tip' => 'bool', 'v' => false],
                ['k' => 'sadece_izinde', 'ad' => 'Yalnızca şu an izinde olanlar', 'tip' => 'bool', 'v' => false],
                ['k' => 'tur', 'ad' => 'İzin türü', 'tip' => 'sec', 'v' => '', 'sec' => ['' => 'Tüm türler'] + IK_IZIN_TURLERI],
                ['k' => 'detay', 'ad' => 'Talep listesini ekle (tarih aralıklarıyla)', 'tip' => 'bool', 'v' => true],
            ],
            'sutunlar' => [['toplam', 'Kullandığı gün', 1], ['talep', 'Talep sayısı', 1], ['turler', 'Türlere göre', 1], ['izinde', 'Şu an izinde', 1], ['donus', 'İşe dönüş', 1],
                ['hak', 'Yıllık izin hakkı', 0], ['kalan', 'Yıllık izin kalan', 0], ['birim', 'Birim', 1]]],
        'avans' => ['ad' => 'Avans', 'ikon' => 'fa-hand-holding-dollar', 'renk' => '#8b5cf6',
            'aciklama' => 'Avans talepleri: kime, ne kadar, kaç taksit; onaylanan / bekleyen / reddedilen toplamları.',
            'siralamalar' => ['tutar' => 'Tutar (çoktan aza)', 'ad' => 'Ada göre (A-Z)', 'adet' => 'Talep sayısı'],
            'secenekler' => [
                ['k' => 'durum', 'ad' => 'Durum', 'tip' => 'sec', 'v' => '', 'sec' => ['' => 'Tümü', 'onaylandi' => 'Yalnızca onaylananlar', 'beklemede' => 'Onay bekleyenler', 'reddedildi' => 'Reddedilenler']],
                ['k' => 'detay', 'ad' => 'Tek tek avans listesini ekle', 'tip' => 'bool', 'v' => true],
            ],
            'sutunlar' => [['adet', 'Talep sayısı', 1], ['onayli', 'Onaylanan tutar', 1], ['bekleyen', 'Bekleyen tutar', 1], ['red', 'Reddedilen tutar', 0], ['toplam', 'Toplam talep', 1], ['son', 'Son talep', 0], ['birim', 'Birim', 1]]],
        'mesai' => ['ad' => 'Mesai', 'ikon' => 'fa-business-time', 'renk' => '#6366f1',
            'aciklama' => 'Kim kaç saat mesai yapmış, kaç saati hafta sonuna veya resmî tatile (bayram vb.) denk gelmiş.',
            'siralamalar' => ['toplam' => 'Toplam mesai (çoktan aza)', 'hs' => 'Hafta sonu + tatil mesaisi', 'ad' => 'Ada göre (A-Z)'],
            'secenekler' => [
                ['k' => 'durum', 'ad' => 'Hangi kayıtlar', 'tip' => 'sec', 'v' => 'onaylandi', 'sec' => ['onaylandi' => 'Yalnızca onaylananlar', 'hepsi' => 'Onay bekleyenler dahil']],
                ['k' => 'giris', 'ad' => 'Hafta sonu / tatil günü giriş-çıkış yapanları da mesai say', 'tip' => 'bool', 'v' => false],
                ['k' => 'detay', 'ad' => 'Tek tek mesai kayıtlarını ekle', 'tip' => 'bool', 'v' => true],
            ],
            'sutunlar' => [['kayit', 'Mesai günü', 1], ['toplam', 'Toplam mesai', 1], ['saat', 'Toplam (saat)', 1], ['hici', 'Hafta içi', 1], ['hsonu', 'Hafta sonu', 1], ['tatil', 'Resmî tatil / bayram', 1], ['bekleyen', 'Onay bekleyen', 0], ['birim', 'Birim', 1]]],
    ];
}

/** Ortak çıktı iskeleti */
function rp_iskelet($baslik, $alt)
{
    return ['ok' => true, 'baslik' => $baslik, 'alt' => $alt, 'ozet' => [], 'sutunlar' => [], 'satirlar' => [], 'toplam' => null, 'grafikler' => [], 'detaylar' => [], 'notlar' => []];
}
/** Kullanıcının sütun seçimini uygular: ['ad' sütunu her zaman var] */
function rp_sutun_sec($tip, array $secili)
{
    $tan = rp_tanimlar()[$tip]; $out = [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l']];
    foreach ($tan['sutunlar'] as $s) if (!$secili || in_array($s[0], $secili, true)) $out[] = ['k' => $s[0], 'ad' => $s[1], 'h' => in_array($s[0], ['birim', 'turler', 'izinde', 'donus', 'vardiya', 'son', 'unvan', 'ise_giris', 'kidem', 'calisma', 'telefon', 'email', 'dogum', 'durum', 'hareket', 'tarih', 'neden', 'esyalar', 'belgeler', 'en_yakin', 'donem', 'tur'], true) ? 'l' : 'r'];
    return $out;
}
function rp_alt_yazi($p, $kisiSay)
{
    $a = rp_tr($p['bas']) . ' – ' . rp_tr($p['bit']);
    $f = [];
    if (!empty($p['birim'])) $f[] = implode(', ', array_slice($p['birim'], 0, 3)) . (count($p['birim']) > 3 ? ' +' . (count($p['birim']) - 3) : '');
    if (!empty($p['kisi'])) $f[] = count($p['kisi']) . ' kişi seçili';
    return $a . ' · ' . ($f ? implode(' · ', $f) : 'Tüm personel') . ' · ' . $kisiSay . ' kişi';
}
function rp_sirala(array &$satirlar, $alan, $azalan = true)
{
    usort($satirlar, function ($a, $b) use ($alan, $azalan) {
        $x = $a['_' . $alan] ?? 0; $y = $b['_' . $alan] ?? 0;
        if ($x === $y) return strcmp(rp_kucuk($a['ad']), rp_kucuk($b['ad']));
        return $azalan ? ($y <=> $x) : ($x <=> $y);
    });
}

/* ───────────────────────── 1) Giriş-çıkış ve geç kalma ───────────────────────── */
function rp_devam($db, array $p, array $kisiler)
{
    $A = giris_ayarlar($db);
    $tol = intval($A['tolerans_dk']); $gunler = rp_calisma_gunleri($db);
    $vB = rp_dk($A['mesai_baslangic']); $vT = rp_dk($A['mesai_bitis']);
    $bugun = date('Y-m-d'); $bas = $p['bas']; $bit = min($p['bit'], $bugun);
    $ids = $kisiler ? implode(',', array_keys($kisiler)) : '0';
    $ozel = []; $q = $db->query("SELECT kullanici_id, baslangic, bitis FROM giris_vardiyalar");
    if ($q) while ($r = $q->fetch_assoc()) $ozel[intval($r['kullanici_id'])] = $r;
    $g = [];
    $q = $db->query("SELECT kullanici_id, giris_zamani, cikis_zamani, TIMESTAMPDIFF(SECOND, giris_zamani, COALESCE(cikis_zamani, NOW())) sn FROM giris_kayitlari
                     WHERE kullanici_id IN ($ids) AND giris_zamani >= '$bas 00:00:00' AND giris_zamani <= '$bit 23:59:59' ORDER BY giris_zamani");
    if ($q) while ($r = $q->fetch_assoc()) {
        $k = intval($r['kullanici_id']); $t = substr($r['giris_zamani'], 0, 10); $d = &$g[$k][$t];
        if (!isset($d)) $d = ['ilk' => null, 'son' => null, 'acik' => false, 'sn' => 0];
        $gd = rp_dk(substr($r['giris_zamani'], 11, 5));
        if ($d['ilk'] === null || $gd < $d['ilk']) $d['ilk'] = $gd;
        if ($r['cikis_zamani'] === null) $d['acik'] = true;
        elseif (substr($r['cikis_zamani'], 0, 10) === $t) { $cd = rp_dk(substr($r['cikis_zamani'], 11, 5)); if ($d['son'] === null || $cd > $d['son']) $d['son'] = $cd; }
        $d['sn'] += max(0, intval($r['sn'])); unset($d);
    }
    $muaf = [];
    $q = $db->query("SELECT kullanici_id, baslangic, bitis FROM ik_izin_talepleri WHERE durum = 'onaylandi' AND kullanici_id IN ($ids) AND baslangic <= '$bit' AND bitis >= '$bas'");
    if ($q) while ($r = $q->fetch_assoc()) for ($t = max(strtotime($r['baslangic']), strtotime($bas)); $t <= min(strtotime($r['bitis']), strtotime($bit)); $t += 86400) $muaf[intval($r['kullanici_id'])][date('Y-m-d', $t)] = 'izin';
    try { $q = $db->query("SELECT kullanici_id, tarih, durum FROM ik_puantaj WHERE kullanici_id IN ($ids) AND tarih BETWEEN '$bas' AND '$bit' AND durum IN ('izinli','rapor','resmi_tatil','haftasonu')"); if ($q) while ($r = $q->fetch_assoc()) $muaf[intval($r['kullanici_id'])][$r['tarih']] = in_array($r['durum'], ['izinli', 'rapor'], true) ? 'izin' : 'tatil'; } catch (Throwable $e) {}
    $tatil = $bit >= $bas ? rp_tatil_haritasi($db, $bas, $bit) : [];

    $satirlar = []; $detay = []; $topGec = 0; $gecKisi = 0; $topDevamsiz = 0; $enCok = ['ad' => '', 'dk' => 0];
    foreach ($kisiler as $id => $kr) {
        $vb = $vB; $vt = $vT;
        if (isset($ozel[$id])) { if (rp_dk($ozel[$id]['baslangic']) !== null) $vb = rp_dk($ozel[$id]['baslangic']); if (rp_dk($ozel[$id]['bitis']) !== null) $vt = rp_dk($ozel[$id]['bitis']); }
        $o = ['gun' => 0, 'gec_gun' => 0, 'gec_dk' => 0, 'en_gec' => 0, 'erken_cik' => 0, 'devamsiz' => 0, 'izinli' => 0, 'sn' => 0, 'hs' => 0]; $kd = [];
        for ($t = strtotime($bas); $t <= strtotime($bit); $t += 86400) {
            $tar = date('Y-m-d', $t); $n = (int)date('N', $t);
            $tamTatil = isset($tatil[$tar]) && empty($tatil[$tar]['yarim']);
            $calisma = in_array($n, $gunler, true) && !$tamTatil;
            $d = $g[$id][$tar] ?? null; $durum = ''; $gecDk = 0; $erkenDk = 0;
            if ($d) {
                $o['gun']++; $o['sn'] += $d['sn'];
                if ($calisma) {
                    $f = $d['ilk'] - $vb;
                    if ($f > $tol) { $gecDk = $f; $o['gec_gun']++; $o['gec_dk'] += $f; $o['en_gec'] = max($o['en_gec'], $f); }
                    if ($vt > $vb && $d['son'] !== null && !$d['acik'] && $d['son'] < $vt - $tol) { $erkenDk = $vt - $d['son']; $o['erken_cik'] += $erkenDk; }
                    $durum = $gecDk ? 'Geç kaldı' : ($erkenDk ? 'Erken çıktı' : ($d['acik'] && $tar < $bugun ? 'Çıkış yapılmadı' : 'Zamanında'));
                } else { $o['hs']++; $durum = $tamTatil ? 'Tatil çalışması: ' . $tatil[$tar]['ad'] : 'Hafta sonu çalışması'; }
            } elseif ($calisma) {
                if (!empty($muaf[$id][$tar])) { $o['izinli']++; $durum = 'İzinli'; }
                elseif ($tar < $bugun) { $o['devamsiz']++; $durum = 'Devamsız'; }
            } elseif (!empty($muaf[$id][$tar]) && $muaf[$id][$tar] === 'izin' && $n <= 5 && !$tamTatil) { $o['izinli']++; $durum = 'İzinli'; }
            if ($p['op']['detay'] !== 'yok' && $durum !== '' && ($p['op']['detay'] === 'tum' || in_array($durum, ['Geç kaldı', 'Erken çıktı', 'Devamsız', 'Çıkış yapılmadı'], true)) && ($d || $durum === 'Devamsız' || $durum === 'İzinli')) {
                $kd[] = ['ad' => $kr['ad'], 'tarih' => rp_tr($tar), 'gun' => RP_GUN_AD[$n], 'giris' => $d && $d['ilk'] !== null ? rp_saat_yaz($d['ilk']) : '—', 'cikis' => $d && $d['son'] !== null ? rp_saat_yaz($d['son']) : ($d && $d['acik'] ? 'açık' : '—'),
                    'sure' => $d ? rp_sure($d['sn'] / 60) : '—', 'gec' => $gecDk ? $gecDk . ' dk' : '—', 'durum' => $durum,
                    '_c' => $gecDk ? 'uyari' : ($durum === 'Devamsız' ? 'hata' : (strpos($durum, 'çalışması') !== false ? 'tatil' : '')), '_t' => $tar . $kr['ad']];
            }
        }
        if ($p['op']['sadece_gec'] && $o['gec_gun'] === 0) continue;
        if ($p['op']['kayitsiz_gizle'] && $o['gun'] === 0) continue;
        foreach ($kd as $x) $detay[] = $x;
        if ($o['gec_dk'] > 0) $gecKisi++;
        $topGec += $o['gec_dk']; $topDevamsiz += $o['devamsiz'];
        if ($o['gec_dk'] > $enCok['dk']) $enCok = ['ad' => $kr['ad'], 'dk' => $o['gec_dk']];
        $satirlar[] = ['ad' => $kr['ad'], 'birim' => $kr['birim'], 'vardiya' => rp_saat_yaz($vb) . '–' . rp_saat_yaz($vt), 'gun' => $o['gun'], 'gec_gun' => $o['gec_gun'], 'gec_dk' => $o['gec_dk'] ? rp_sure($o['gec_dk']) : '—',
            'ort_gec' => $o['gec_gun'] ? rp_sure($o['gec_dk'] / $o['gec_gun']) : '—', 'en_gec' => $o['en_gec'] ? rp_sure($o['en_gec']) : '—', 'erken_cik' => $o['erken_cik'] ? rp_sure($o['erken_cik']) : '—',
            'devamsiz' => $o['devamsiz'], 'izinli' => $o['izinli'], 'sure' => rp_sure($o['sn'] / 60), 'hs_gun' => $o['hs'],
            '_gec_dk' => $o['gec_dk'], '_gec_gun' => $o['gec_gun'], '_devamsiz' => $o['devamsiz'], '_sure' => $o['sn'], '_ad' => 0,
            '_h' => ['gec_dk' => $o['gec_dk'] >= 60 ? 'kirmizi' : ($o['gec_dk'] > 0 ? 'turuncu' : ''), 'gec_gun' => $o['gec_gun'] ? 'turuncu' : '', 'devamsiz' => $o['devamsiz'] ? 'kirmizi' : '', 'izinli' => $o['izinli'] ? 'yesil' : '', 'hs_gun' => $o['hs'] ? 'mor' : '']];
    }
    $sk = ['gec_dk' => ['gec_dk', true], 'gec_gun' => ['gec_gun', true], 'devamsiz' => ['devamsiz', true], 'sure' => ['sure', true], 'ad' => ['ad', false]][$p['sirala']] ?? ['gec_dk', true];
    if ($sk[0] === 'ad') usort($satirlar, function ($a, $b) { return strcmp(rp_kucuk($a['ad']), rp_kucuk($b['ad'])); }); else rp_sirala($satirlar, $sk[0], $sk[1]);

    $r = rp_iskelet('Giriş-çıkış ve geç kalma raporu', rp_alt_yazi($p, count($kisiler)));
    $r['ozet'] = [['Personel', count($satirlar), 'mavi'], ['Geç kalan kişi', $gecKisi, 'turuncu'], ['Toplam geç kalma', rp_sure($topGec), 'kirmizi'], ['En çok geç kalan', $enCok['dk'] ? $enCok['ad'] . ' (' . rp_sure($enCok['dk']) . ')' : '—', 'mor'], ['Devamsız gün', $topDevamsiz, 'kirmizi']];
    $r['sutunlar'] = rp_sutun_sec('devam', $p['sutun']); $r['satirlar'] = $satirlar;
    $gr = array_values(array_filter($satirlar, function ($s) { return $s['_gec_dk'] > 0; }));
    if ($gr) $r['grafikler'][] = ['baslik' => 'Toplam geç kalma (dakika)', 'tip' => 'yatay', 'birim' => 'dk', 'etiketler' => array_column(array_slice($gr, 0, 15), 'ad'), 'seriler' => [['ad' => 'Geç kalma (dk)', 'renk' => '#f59e0b', 'veri' => array_column(array_slice($gr, 0, 15), '_gec_dk')]]];
    if ($p['op']['detay'] !== 'yok') {
        usort($detay, function ($a, $b) { return strcmp($a['_t'], $b['_t']); });
        $kes = count($detay) > 3000; if ($kes) { $detay = array_slice($detay, 0, 3000); $r['notlar'][] = 'Gün gün liste ilk 3000 satırla sınırlandı; aralığı daraltın.'; }
        $r['detaylar'][] = ['baslik' => 'Gün gün giriş-çıkış', 'sutunlar' => [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l'], ['k' => 'tarih', 'ad' => 'Tarih', 'h' => 'l'], ['k' => 'gun', 'ad' => 'Gün', 'h' => 'l'], ['k' => 'giris', 'ad' => 'Giriş', 'h' => 'r'], ['k' => 'cikis', 'ad' => 'Çıkış', 'h' => 'r'],
            ['k' => 'sure', 'ad' => 'Süre', 'h' => 'r'], ['k' => 'gec', 'ad' => 'Geç kalma', 'h' => 'r'], ['k' => 'durum', 'ad' => 'Durum', 'h' => 'l']], 'satirlar' => $detay];
    }
    $r['notlar'][] = 'Vardiya ' . rp_saat_yaz($vB) . '–' . rp_saat_yaz($vT) . ', tolerans ' . $tol . ' dk. Onaylı izin, rapor ve resmî tatil günleri devamsızlığa sayılmaz. Bugünden sonraki günler değerlendirilmez.';
    return $r;
}

/* ───────────────────────── 2) İzin ───────────────────────── */
function rp_izin($db, array $p, array $kisiler)
{
    $bas = $p['bas']; $bit = $p['bit']; $ids = $kisiler ? implode(',', array_keys($kisiler)) : '0';
    $dur = $p['op']['bekleyen'] ? "'onaylandi','beklemede'" : "'onaylandi'";
    $tur = $p['op']['tur'] && isset(IK_IZIN_TURLERI[$p['op']['tur']]) ? " AND tur = '" . $p['op']['tur'] . "'" : '';
    $bugun = date('Y-m-d');
    $q = $db->query("SELECT * FROM ik_izin_talepleri WHERE kullanici_id IN ($ids) AND durum IN ($dur) AND baslangic <= '$bit' AND bitis >= '$bas'$tur ORDER BY baslangic");
    $per = []; $detay = []; $turTop = [];
    $izindeKim = [];
    if ($q) while ($t = $q->fetch_assoc()) {
        $k = intval($t['kullanici_id']); if (!isset($kisiler[$k])) continue;
        $b = max($t['baslangic'], $bas); $e = min($t['bitis'], $bit);
        $gun = ik_is_gunu($b, $e, !empty($t['yarim_gun']));
        $o = &$per[$k]; if (!isset($o)) $o = ['toplam' => 0, 'talep' => 0, 'tur' => [], 'bekleyen' => 0];
        if ($t['durum'] === 'onaylandi') { $o['toplam'] += $gun; $o['tur'][$t['tur']] = ($o['tur'][$t['tur']] ?? 0) + $gun; $turTop[$t['tur']] = ($turTop[$t['tur']] ?? 0) + $gun; } else $o['bekleyen'] += $gun;
        $o['talep']++; unset($o);
        if ($t['durum'] === 'onaylandi' && $t['baslangic'] <= $bugun && $t['bitis'] >= $bugun) $izindeKim[$k] = $t['bitis'];
        $detay[] = ['ad' => $kisiler[$k]['ad'], 'tur' => IK_IZIN_TURLERI[$t['tur']] ?? $t['tur'], 'bas' => rp_tr($t['baslangic']), 'bit' => rp_tr($t['bitis']), 'gun' => rp_gun_say($gun) . (!empty($t['yarim_gun']) ? ' (yarım)' : ''),
            'durum' => $t['durum'] === 'onaylandi' ? 'Onaylı' : 'Onay bekliyor', 'donus' => rp_tr(ik_ise_donus($t['bitis'])), '_c' => $t['durum'] === 'onaylandi' ? '' : 'uyari', '_t' => $t['baslangic'] . $kisiler[$k]['ad']];
    }
    // Şu an izinde olanlar (aralıktan bağımsız) — ayrı sorgu
    {
        $q = $db->query("SELECT kullanici_id, bitis FROM ik_izin_talepleri WHERE durum = 'onaylandi' AND kullanici_id IN ($ids) AND baslangic <= '$bugun' AND bitis >= '$bugun'");
        if ($q) while ($t = $q->fetch_assoc()) $izindeKim[intval($t['kullanici_id'])] = $t['bitis'];
    }
    $satirlar = []; $izindeSay = 0; $topGun = 0; $enCok = ['ad' => '', 'g' => 0];
    foreach ($kisiler as $id => $kr) {
        $o = $per[$id] ?? null; $izinde = isset($izindeKim[$id]);
        if ($p['op']['sadece_izinde'] && !$izinde) continue;
        if (!$o && !$p['op']['sifir'] && !$p['op']['sadece_izinde']) continue;
        $o = $o ?: ['toplam' => 0, 'talep' => 0, 'tur' => [], 'bekleyen' => 0];
        if ($izinde) $izindeSay++;
        $topGun += $o['toplam']; if ($o['toplam'] > $enCok['g']) $enCok = ['ad' => $kr['ad'], 'g' => $o['toplam']];
        $turler = []; arsort($o['tur']); foreach ($o['tur'] as $tk => $g) $turler[] = (IK_IZIN_TURLERI[$tk] ?? $tk) . ' ' . rp_gun_say($g);
        $hak = '—'; $kalan = '—';
        if (in_array('hak', $p['sutun'], true) || in_array('kalan', $p['sutun'], true)) { $oz = ik_izin_ozet($db, $id); $hak = $oz['hak'] === null ? '—' : rp_gun_say($oz['hak']); $kalan = $oz['kalan'] === null ? '—' : rp_gun_say($oz['kalan']); }
        $satirlar[] = ['ad' => $kr['ad'], 'birim' => $kr['birim'], 'toplam' => rp_gun_say($o['toplam']) . ' gün', 'talep' => $o['talep'], 'turler' => $turler ? implode(' · ', $turler) : '—',
            'izinde' => $izinde ? 'Evet' : 'Hayır', 'donus' => $izinde ? rp_tr(ik_ise_donus($izindeKim[$id])) : '—', 'hak' => $hak, 'kalan' => $kalan,
            '_toplam' => $o['toplam'], '_talep' => $o['talep'], '_h' => ['izinde' => $izinde ? 'yesil' : '', 'toplam' => $o['toplam'] > 0 ? 'mavi' : '']];
    }
    if ($p['sirala'] === 'ad') usort($satirlar, function ($a, $b) { return strcmp(rp_kucuk($a['ad']), rp_kucuk($b['ad'])); }); else rp_sirala($satirlar, $p['sirala'] === 'talep' ? 'talep' : 'toplam', true);
    $r = rp_iskelet('İzin kullanım raporu', rp_alt_yazi($p, count($kisiler)));
    $r['ozet'] = [['İzin kullanan', count(array_filter($satirlar, function ($s) { return $s['_toplam'] > 0; })), 'mavi'], ['Toplam izin günü', rp_gun_say($topGun), 'yesil'], ['Şu an izinde', $izindeSay, 'turuncu'], ['En çok izin kullanan', $enCok['g'] ? $enCok['ad'] . ' (' . rp_gun_say($enCok['g']) . ' gün)' : '—', 'mor']];
    $r['sutunlar'] = rp_sutun_sec('izin', $p['sutun']); $r['satirlar'] = $satirlar;
    $gr = array_values(array_filter($satirlar, function ($s) { return $s['_toplam'] > 0; }));
    if ($gr) $r['grafikler'][] = ['baslik' => 'Kişi başına izin günü', 'tip' => 'yatay', 'birim' => 'gün', 'etiketler' => array_column(array_slice($gr, 0, 15), 'ad'), 'seriler' => [['ad' => 'İzin günü', 'renk' => '#10b981', 'veri' => array_column(array_slice($gr, 0, 15), '_toplam')]]];
    if ($turTop) {
        arsort($turTop); $renk = ['#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#0ea5e9', '#8b5cf6', '#14b8a6', '#eab308', '#64748b']; $i = 0; $dilim = [];
        foreach ($turTop as $tk => $g) $dilim[] = ['ad' => IK_IZIN_TURLERI[$tk] ?? $tk, 'deger' => $g, 'renk' => $renk[$i++ % 9]];
        $r['grafikler'][] = ['baslik' => 'İzin türlerine göre dağılım (gün)', 'tip' => 'halka', 'birim' => 'gün', 'dilimler' => $dilim];
    }
    if ($p['op']['detay'] && $detay) {
        usort($detay, function ($a, $b) { return strcmp($a['_t'], $b['_t']); });
        $r['detaylar'][] = ['baslik' => 'İzin talepleri', 'sutunlar' => [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l'], ['k' => 'tur', 'ad' => 'Tür', 'h' => 'l'], ['k' => 'bas', 'ad' => 'Başlangıç', 'h' => 'l'], ['k' => 'bit', 'ad' => 'Bitiş', 'h' => 'l'], ['k' => 'gun', 'ad' => 'Gün', 'h' => 'r'], ['k' => 'donus', 'ad' => 'İşe dönüş', 'h' => 'l'], ['k' => 'durum', 'ad' => 'Durum', 'h' => 'l']], 'satirlar' => $detay];
    }
    $r['notlar'][] = 'Gün sayısı yalnızca seçilen tarih aralığına düşen iş günlerini kapsar (hafta sonları sayılmaz).';
    return $r;
}

/* ───────────────────────── 3) Avans ───────────────────────── */
function rp_avans($db, array $p, array $kisiler)
{
    $bas = $p['bas']; $bit = $p['bit']; $ids = $kisiler ? implode(',', array_keys($kisiler)) : '0';
    $d = $p['op']['durum'] && in_array($p['op']['durum'], ['onaylandi', 'beklemede', 'reddedildi'], true) ? " AND a.durum = '" . $p['op']['durum'] . "'" : '';
    $q = $db->query("SELECT a.*, o.ad_soyad onaylayan FROM ik_avanslar a LEFT JOIN kullanicilar o ON o.id = a.onaylayan_id WHERE a.kullanici_id IN ($ids) AND DATE(a.olusturma_tarihi) BETWEEN '$bas' AND '$bit'$d ORDER BY a.olusturma_tarihi");
    $per = []; $detay = []; $top = ['onaylandi' => 0, 'beklemede' => 0, 'reddedildi' => 0]; $DUR = ['onaylandi' => 'Onaylandı', 'beklemede' => 'Onay bekliyor', 'reddedildi' => 'Reddedildi'];
    if ($q) while ($a = $q->fetch_assoc()) {
        $k = intval($a['kullanici_id']); if (!isset($kisiler[$k])) continue;
        $o = &$per[$k]; if (!isset($o)) $o = ['adet' => 0, 'onaylandi' => 0, 'beklemede' => 0, 'reddedildi' => 0, 'son' => ''];
        $tut = (float)$a['tutar']; $dd = isset($DUR[$a['durum']]) ? $a['durum'] : 'beklemede';
        $o['adet']++; $o[$dd] += $tut; $o['son'] = substr($a['olusturma_tarihi'], 0, 10); $top[$dd] += $tut; unset($o);
        $tak = max(1, intval($a['taksit']));
        $detay[] = ['ad' => $kisiler[$k]['ad'], 'tarih' => rp_tr(substr($a['olusturma_tarihi'], 0, 10)), 'tutar' => rp_para($tut), 'taksit' => $tak . ' × ' . rp_para($tut / $tak), 'istenen' => rp_tr($a['istenen_tarih']),
            'durum' => $DUR[$dd], 'onaylayan' => (string)($a['onaylayan'] ?? ''), 'aciklama' => (string)$a['aciklama'], '_c' => $dd === 'reddedildi' ? 'hata' : ($dd === 'beklemede' ? 'uyari' : ''), '_t' => $a['olusturma_tarihi'] . $kisiler[$k]['ad']];
    }
    $satirlar = [];
    foreach ($per as $k => $o) $satirlar[] = ['ad' => $kisiler[$k]['ad'], 'birim' => $kisiler[$k]['birim'], 'adet' => $o['adet'], 'onayli' => rp_para($o['onaylandi']), 'bekleyen' => rp_para($o['beklemede']), 'red' => rp_para($o['reddedildi']),
        'toplam' => rp_para($o['onaylandi'] + $o['beklemede'] + $o['reddedildi']), 'son' => rp_tr($o['son']), '_tutar' => $o['onaylandi'] + $o['beklemede'] + $o['reddedildi'], '_adet' => $o['adet'],
        '_h' => ['onayli' => $o['onaylandi'] > 0 ? 'yesil' : '', 'bekleyen' => $o['beklemede'] > 0 ? 'turuncu' : '', 'red' => $o['reddedildi'] > 0 ? 'kirmizi' : '']];
    if ($p['sirala'] === 'ad') usort($satirlar, function ($a, $b) { return strcmp(rp_kucuk($a['ad']), rp_kucuk($b['ad'])); }); else rp_sirala($satirlar, $p['sirala'] === 'adet' ? 'adet' : 'tutar', true);
    $r = rp_iskelet('Avans raporu', rp_alt_yazi($p, count($kisiler)));
    $r['ozet'] = [['Talep eden kişi', count($satirlar), 'mavi'], ['Onaylanan toplam', rp_para($top['onaylandi']), 'yesil'], ['Onay bekleyen', rp_para($top['beklemede']), 'turuncu'], ['Reddedilen', rp_para($top['reddedildi']), 'kirmizi']];
    $r['sutunlar'] = rp_sutun_sec('avans', $p['sutun']); $r['satirlar'] = $satirlar;
    if ($satirlar) $r['grafikler'][] = ['baslik' => 'Kişi başına avans tutarı (₺)', 'tip' => 'yatay', 'birim' => '₺', 'etiketler' => array_column(array_slice($satirlar, 0, 15), 'ad'), 'seriler' => [['ad' => 'Toplam talep', 'renk' => '#8b5cf6', 'veri' => array_map(function ($s) { return round($s['_tutar'], 2); }, array_slice($satirlar, 0, 15))]]];
    if ($p['op']['detay'] && $detay) {
        usort($detay, function ($a, $b) { return strcmp($a['_t'], $b['_t']); });
        $r['detaylar'][] = ['baslik' => 'Avans talepleri', 'sutunlar' => [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l'], ['k' => 'tarih', 'ad' => 'Talep tarihi', 'h' => 'l'], ['k' => 'tutar', 'ad' => 'Tutar', 'h' => 'r'], ['k' => 'taksit', 'ad' => 'Taksit', 'h' => 'r'], ['k' => 'istenen', 'ad' => 'İstenen tarih', 'h' => 'l'], ['k' => 'durum', 'ad' => 'Durum', 'h' => 'l'], ['k' => 'onaylayan', 'ad' => 'Karar veren', 'h' => 'l'], ['k' => 'aciklama', 'ad' => 'Açıklama', 'h' => 'l']], 'satirlar' => $detay];
    }
    return $r;
}

/* ───────────────────────── 4) Mesai ───────────────────────── */
/** Mesai kaydı için süre ve tür hesabı. Dönüş: [dakika, tür, tatil adı] — bitiş başlangıçtan küçükse gece yarısını geçmiş sayılır */
function rp_mesai_hesapla($db, $tarih, $bas, $bit)
{
    $b = rp_dk($bas); $e = rp_dk($bit);
    if ($b === null || $e === null) return null;
    if ($e <= $b) $e += 1440;
    $dk = $e - $b;
    $tat = rp_tatil_haritasi($db, $tarih, $tarih);
    list($tur, $ad) = rp_gun_turu($tarih, $tat, rp_calisma_gunleri($db));
    return [$dk, $tur, $tur === 'normal' ? '' : $ad];
}
function rp_mesai($db, array $p, array $kisiler)
{
    $bas = $p['bas']; $bit = $p['bit']; $ids = $kisiler ? implode(',', array_keys($kisiler)) : '0';
    $dur = $p['op']['durum'] === 'hepsi' ? "'onaylandi','beklemede'" : "'onaylandi'";
    $q = $db->query("SELECT * FROM ik_mesailer WHERE kullanici_id IN ($ids) AND tarih BETWEEN '$bas' AND '$bit' AND durum IN ($dur) ORDER BY tarih, baslangic");
    $liste = [];
    if ($q) while ($m = $q->fetch_assoc()) { $m['kaynak'] = 'mesai'; $liste[] = $m; }
    $A = giris_ayarlar($db); $gunler = rp_calisma_gunleri($db); $tat = rp_tatil_haritasi($db, $bas, $bit);
    if ($p['op']['giris']) {
        $var = []; foreach ($liste as $m) $var[$m['kullanici_id'] . '|' . $m['tarih']] = true;
        $q = $db->query("SELECT kullanici_id, DATE(giris_zamani) t, MIN(giris_zamani) a, MAX(COALESCE(cikis_zamani, giris_zamani)) b, SUM(TIMESTAMPDIFF(MINUTE, giris_zamani, COALESCE(cikis_zamani, giris_zamani))) dk
                         FROM giris_kayitlari WHERE kullanici_id IN ($ids) AND giris_zamani >= '$bas 00:00:00' AND giris_zamani <= '$bit 23:59:59' AND cikis_zamani IS NOT NULL GROUP BY kullanici_id, DATE(giris_zamani)");
        if ($q) while ($r = $q->fetch_assoc()) {
            list($tur, $ad) = rp_gun_turu($r['t'], $tat, $gunler);
            if ($tur === 'normal' || isset($var[$r['kullanici_id'] . '|' . $r['t']]) || intval($r['dk']) <= 0) continue;
            $liste[] = ['kullanici_id' => $r['kullanici_id'], 'tarih' => $r['t'], 'baslangic' => substr($r['a'], 11, 5), 'bitis' => substr($r['b'], 11, 5), 'dakika' => intval($r['dk']), 'tur' => $tur, 'tatil_adi' => $ad, 'aciklama' => 'Giriş-çıkış kaydından', 'durum' => 'onaylandi', 'kaynak' => 'giris'];
        }
        usort($liste, function ($a, $b) { return strcmp($a['tarih'] . $a['baslangic'], $b['tarih'] . $b['baslangic']); });
    }
    $per = []; $detay = []; $top = ['normal' => 0, 'haftasonu' => 0, 'tatil' => 0, 'bekleyen' => 0];
    foreach ($liste as $m) {
        $k = intval($m['kullanici_id']); if (!isset($kisiler[$k])) continue;
        $o = &$per[$k]; if (!isset($o)) $o = ['kayit' => 0, 'normal' => 0, 'haftasonu' => 0, 'tatil' => 0, 'bekleyen' => 0, 'gunler' => [], 'toplam' => 0];
        $dk = intval($m['dakika']); $tur = isset(RP_MESAI_TUR[$m['tur']]) ? $m['tur'] : 'normal';
        if ($m['durum'] === 'beklemede') { $o['bekleyen'] += $dk; $top['bekleyen'] += $dk; } else { $o[$tur] += $dk; $o['toplam'] += $dk; $top[$tur] += $dk; }
        $o['kayit']++; unset($o);
        $gunAd = RP_GUN_AD[(int)date('N', strtotime($m['tarih']))];
        $detay[] = ['ad' => $kisiler[$k]['ad'], 'tarih' => rp_tr($m['tarih']), 'gun' => $gunAd, 'aralik' => $m['baslangic'] . '–' . $m['bitis'], 'sure' => rp_sure($dk),
            'tur' => RP_MESAI_TUR[$tur] . ($tur === 'tatil' ? ': ' . $m['tatil_adi'] : ($tur === 'haftasonu' ? '' : '')), 'durum' => $m['durum'] === 'beklemede' ? 'Onay bekliyor' : 'Onaylı',
            'aciklama' => (string)$m['aciklama'], '_c' => $tur === 'tatil' ? 'tatil' : ($tur === 'haftasonu' ? 'haftasonu' : ($m['durum'] === 'beklemede' ? 'uyari' : '')), '_t' => $m['tarih'] . $m['baslangic'] . $kisiler[$k]['ad']];
    }
    $satirlar = [];
    foreach ($per as $k => $o) $satirlar[] = ['ad' => $kisiler[$k]['ad'], 'birim' => $kisiler[$k]['birim'], 'kayit' => $o['kayit'], 'toplam' => rp_sure($o['toplam']), 'saat' => rp_saat_ond($o['toplam']), 'hici' => rp_sure($o['normal']),
        'hsonu' => rp_sure($o['haftasonu']), 'tatil' => rp_sure($o['tatil']), 'bekleyen' => rp_sure($o['bekleyen']), '_toplam' => $o['toplam'], '_hs' => $o['haftasonu'] + $o['tatil'], '_n' => $o['normal'], '_hsonu' => $o['haftasonu'], '_tatil' => $o['tatil'],
        '_h' => ['hsonu' => $o['haftasonu'] ? 'mor' : '', 'tatil' => $o['tatil'] ? 'kirmizi' : '', 'bekleyen' => $o['bekleyen'] ? 'turuncu' : '', 'toplam' => 'mavi']];
    if ($p['sirala'] === 'ad') usort($satirlar, function ($a, $b) { return strcmp(rp_kucuk($a['ad']), rp_kucuk($b['ad'])); }); else rp_sirala($satirlar, $p['sirala'] === 'hs' ? 'hs' : 'toplam', true);
    $topAll = $top['normal'] + $top['haftasonu'] + $top['tatil'];
    $r = rp_iskelet('Mesai raporu', rp_alt_yazi($p, count($kisiler)));
    $r['ozet'] = [['Mesai yapan', count($satirlar), 'mavi'], ['Toplam mesai', rp_sure($topAll) . ' (' . rp_saat_ond($topAll) . ' sa)', 'yesil'], ['Hafta sonu', rp_sure($top['haftasonu']), 'mor'], ['Resmî tatil / bayram', rp_sure($top['tatil']), 'kirmizi']];
    if ($top['bekleyen']) $r['ozet'][] = ['Onay bekleyen', rp_sure($top['bekleyen']), 'turuncu'];
    $r['sutunlar'] = rp_sutun_sec('mesai', $p['sutun']); $r['satirlar'] = $satirlar;
    if ($satirlar) {
        $s15 = array_slice($satirlar, 0, 15); $sa = function ($k) use ($s15) { return array_map(function ($s) use ($k) { return round($s[$k] / 60, 2); }, $s15); };
        $r['grafikler'][] = ['baslik' => 'Mesai süresi (saat) — hafta içi / hafta sonu / resmî tatil', 'tip' => 'yatay', 'birim' => 'sa', 'yigin' => true, 'etiketler' => array_column($s15, 'ad'),
            'seriler' => [['ad' => 'Hafta içi', 'renk' => '#6366f1', 'veri' => $sa('_n')], ['ad' => 'Hafta sonu', 'renk' => '#a855f7', 'veri' => $sa('_hsonu')], ['ad' => 'Resmî tatil', 'renk' => '#f43f5e', 'veri' => $sa('_tatil')]]];
    }
    if ($p['op']['detay'] && $detay) {
        usort($detay, function ($a, $b) { return strcmp($a['_t'], $b['_t']); });
        $r['detaylar'][] = ['baslik' => 'Mesai kayıtları', 'sutunlar' => [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l'], ['k' => 'tarih', 'ad' => 'Tarih', 'h' => 'l'], ['k' => 'gun', 'ad' => 'Gün', 'h' => 'l'], ['k' => 'aralik', 'ad' => 'Saat', 'h' => 'l'], ['k' => 'sure', 'ad' => 'Süre', 'h' => 'r'], ['k' => 'tur', 'ad' => 'Mesai türü', 'h' => 'l'], ['k' => 'durum', 'ad' => 'Durum', 'h' => 'l'], ['k' => 'aciklama', 'ad' => 'Açıklama', 'h' => 'l']], 'satirlar' => $detay];
    }
    $r['notlar'][] = 'Hafta sonu ve resmî tatil (bayram vb.) günlerine denk gelen mesailer ayrıca vurgulanır. Tatil günleri İK > Mesai > Tatil günleri bölümünden düzenlenebilir.';
    return $r;
}

/** Ortak giriş: parametreleri temizler ve ilgili raporu üretir */
function rp_uret($db, array $g)
{
    rp_sema($db); giris_sema($db);
    $tip = (string)($g['tip'] ?? ''); $tan = rp_tanimlar();
    if (!isset($tan[$tip])) return ['ok' => false, 'hata' => 'Geçersiz rapor türü.'];
    $bas = rp_tarih_gecerli($g['bas'] ?? '') ? $g['bas'] : date('Y-m-01'); $bit = rp_tarih_gecerli($g['bit'] ?? '') ? $g['bit'] : date('Y-m-d');
    if ($bit < $bas) return ['ok' => false, 'hata' => 'Bitiş tarihi başlangıçtan önce olamaz.'];
    if ((strtotime($bit) - strtotime($bas)) / 86400 > 400) return ['ok' => false, 'hata' => 'En fazla 13 aylık aralık seçilebilir.'];
    $js = function ($k) use ($g) { $a = json_decode((string)($g[$k] ?? ''), true); return is_array($a) ? $a : []; };
    $birim = array_values(array_filter(array_map('strval', $js('birim')), 'strlen')); $kisi = array_values(array_filter(array_map('intval', $js('kisi'))));
    $sutun = array_values(array_filter(array_map('strval', $js('sutun')), 'strlen')); $opH = $js('op'); $op = [];
    foreach ($tan[$tip]['secenekler'] as $s) {
        $v = $opH[$s['k']] ?? $s['v'];
        if ($s['tip'] === 'bool') $op[$s['k']] = !empty($v) && $v !== 'false' && $v !== '0';
        else $op[$s['k']] = isset($s['sec'][(string)$v]) ? (string)$v : $s['v'];
    }
    $sirala = isset($tan[$tip]['siralamalar'][(string)($g['sirala'] ?? '')]) ? $g['sirala'] : array_key_first($tan[$tip]['siralamalar']);
    $p = ['tip' => $tip, 'bas' => $bas, 'bit' => $bit, 'birim' => $birim, 'kisi' => $kisi, 'sutun' => $sutun, 'op' => $op, 'sirala' => $sirala];
    $kisiler = rp_kisiler($db, $birim, $kisi, $bas);
    if (!$kisiler) return ['ok' => false, 'hata' => 'Seçime uyan personel bulunamadı.'];
    $f = 'rp_' . $tip; $r = $f($db, $p, $kisiler);
    $r['tip'] = $tip; $r['tur_ad'] = $tan[$tip]['ad']; $r['dosya'] = 'ik_' . $tip . '_raporu_' . str_replace('-', '', $bas) . '_' . str_replace('-', '', $bit);
    foreach ($r['satirlar'] as &$s) foreach (array_keys($s) as $k) if ($k !== '_h' && $k !== '_c' && $k[0] === '_') unset($s[$k]);
    unset($s);
    foreach ($r['detaylar'] as &$d) foreach ($d['satirlar'] as &$s) unset($s['_t']);
    unset($d, $s);
    return $r;
}

/** Birim = İK kartındaki departman; yoksa görev; yoksa "Belirtilmemiş" */
function rp_birim_adi($r) { $d = trim((string)($r['departman'] ?? '')); if ($d === '') $d = trim((string)($r['gorev'] ?? '')); return $d !== '' ? $d : 'Belirtilmemiş'; }

require_once __DIR__ . '/rapor_ek.php';
