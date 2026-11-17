<?php
// ============================================================
// ik_etkinlik.php — Eğitim / toplantı bildirimleri
//  • Oluşturulunca ve tarih/saat değişince katılımcılara site içi + SMS + e-posta
//  • Seçilen günlerde hatırlatma: 7 / 3 / 1 gün önce ve etkinlik günü
//  • Gönderim kaydı (ik_egitim_bildirim_log) ile aynı hatırlatma ikinci kez gitmez
// ============================================================
if (!defined('IK_ETKINLIK')) {
define('IK_ETKINLIK', true);
require_once __DIR__ . '/ik_ortak.php';

/** Kullanılabilir hatırlatma günleri: 'olustur' = oluşturulunca, sayı = kaç gün önce (0 = etkinlik günü) */
define('IK_ETKINLIK_GUNLER', ['olustur', '7', '3', '1', '0']);

function ik_etkinlik_sema($db)
{
    static $y = []; $k = intval($db->kurum);
    if (!empty($y[$k])) return;
    $y[$k] = true;
    $ek = [
        "tur VARCHAR(12) NOT NULL DEFAULT 'egitim'", "saat VARCHAR(5) NOT NULL DEFAULT ''", "yer VARCHAR(200) NOT NULL DEFAULT ''",
        "bildirim_ac TINYINT(1) NOT NULL DEFAULT 0", "bildirim_sms TINYINT(1) NOT NULL DEFAULT 1", "bildirim_mail TINYINT(1) NOT NULL DEFAULT 1",
        "bildirim_gunler VARCHAR(40) NOT NULL DEFAULT 'olustur,1,0'",
    ];
    $var = [];
    $q = $db->query("SHOW COLUMNS FROM ik_egitimler"); if ($q) while ($r = $q->fetch_row()) $var[$r[0]] = 1;
    if ($var) foreach ($ek as $c) { $ad = strtok($c, ' '); if (!isset($var[$ad])) $db->query("ALTER TABLE ik_egitimler ADD COLUMN $c"); }
    $db->query("CREATE TABLE IF NOT EXISTS ik_egitim_bildirim_log (
        egitim_id INT UNSIGNED NOT NULL, kullanici_id INT NOT NULL, anahtar VARCHAR(40) NOT NULL, zaman DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (egitim_id, kullanici_id, anahtar)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ik_etkinlik_gunler($s)
{
    $o = [];
    foreach (explode(',', (string)$s) as $g) { $g = trim($g); if (in_array($g, IK_ETKINLIK_GUNLER, true) && !in_array($g, $o, true)) $o[] = $g; }
    return $o;
}
function ik_etkinlik_saat($s) { $s = trim((string)$s); return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $s) ? $s : ''; }
function ik_etkinlik_tur_ad($tur) { return $tur === 'toplanti' ? 'Toplantı' : 'Eğitim'; }

/** Tek etkinlik + tek kişi için bildirim gönderir (daha önce aynı anahtarla gittiyse atlar). */
function ik_etkinlik_gonder($db, array $e, $kid, $anahtar, $ne)
{
    $kid = intval($kid); $eid = intval($e['id']);
    $a = $db->real_escape_string($anahtar);
    $db->query("INSERT IGNORE INTO ik_egitim_bildirim_log (egitim_id, kullanici_id, anahtar) VALUES ($eid, $kid, '$a')");
    if ($db->etkilenen() < 1) return false; // zaten gönderilmiş
    $ad = ik_etkinlik_tur_ad($e['tur']);
    $tarihYaz = $e['tarih'] ? date('d.m.Y', strtotime($e['tarih'])) : 'tarih belirlenecek';
    $saat = $e['saat'] !== '' ? ' ' . $e['saat'] : '';
    $yer = trim((string)$e['yer']);
    $simge = $e['tur'] === 'toplanti' ? '📅' : '🎓';
    $baslik = ['yeni' => " $ad davetiniz", 'guncel' => " $ad güncellendi", 'g7' => " $ad 1 hafta sonra", 'g3' => " $ad 3 gün sonra", 'g1' => " $ad yarın", 'g0' => " $ad bugün"][$ne] ?? " $ad";
    $baslik = $simge . $baslik;
    $giris = ['yeni' => 'için davet edildiniz', 'guncel' => 'bilgileri güncellendi', 'g7' => '1 hafta sonra', 'g3' => '3 gün sonra', 'g1' => 'yarın', 'g0' => 'bugün'][$ne] ?? '';
    $html = $simge . ' <b>' . ik_h($e['ad']) . '</b> ' . ik_h(mb_strtolower($ad, 'UTF-8')) . ' ' . $giris . ' — <b>' . ik_h($tarihYaz . $saat) . '</b>' . ($yer !== '' ? ' · ' . ik_h($yer) : '') . '.';
    $sms = $ad . ': ' . $e['ad'] . ' — ' . ($ne === 'g0' ? 'bugün' : ($ne === 'g1' ? 'yarın' : $tarihYaz)) . $saat . ($yer !== '' ? ', ' . $yer : '') . ($ne === 'yeni' ? ' (davet)' : ($ne === 'guncel' ? ' (güncellendi)' : '')) . '.';
    $kanal = ['site' => true, 'sms' => !empty($e['bildirim_sms']) && ik_sms_hazir($db), 'mail' => !empty($e['bildirim_mail']) && ik_mail_hazir($db)];
    ik_kisi_bildir($db, $kid, intval($e['olusturan_id'] ?? 0), $kanal, [
        'baslik' => $baslik, 'html' => $html, 'sms' => $sms, 'link' => 'ik.php?sekme=egitimler', 'buton' => 'Eğitimler sayfası', 'tablo' => 'ik_egitim', 'bid' => $eid, 'pdf' => false,
    ]);
    return true;
}

/** Etkinliğin katılımcı listesi */
function ik_etkinlik_katilimcilar($db, $eid)
{
    $o = []; $q = $db->query("SELECT kullanici_id FROM ik_egitim_katilim WHERE egitim_id = " . intval($eid));
    if ($q) while ($r = $q->fetch_row()) $o[] = intval($r[0]);
    return $o;
}

/**
 * Kaydetme sonrası: yeni/güncellenen etkinlik için anında bildirim + zamanı geçmiş hatırlatmaları "gitti" say.
 * $yeniKisiler: bu kayıtta eklenen kişiler (güncellemede yalnız onlara "davet" gider). $degisti: tarih/saat/yer değişti mi
 */
function ik_etkinlik_kayit_bildir($db, $eid, array $yeniKisiler, $degisti, $yeniMi)
{
    ik_etkinlik_sema($db);
    $q = $db->query("SELECT * FROM ik_egitimler WHERE id = " . intval($eid)); $e = $q ? $q->fetch_assoc() : null;
    if (!$e || empty($e['bildirim_ac'])) return 0;
    $gunler = ik_etkinlik_gunler($e['bildirim_gunler']);
    $bugun = date('Y-m-d'); $n = 0;
    $tum = ik_etkinlik_katilimcilar($db, $eid);
    $imza = ($e['tarih'] ?: '-') . ' ' . $e['saat'] . ' ' . $e['yer'];
    // şu an için vakti geçmiş hatırlatmalar tekrar gitmesin
    if ($e['tarih']) foreach (['7', '3', '1', '0'] as $g) {
        if (date('Y-m-d', strtotime($e['tarih'] . " -$g day")) <= $bugun) foreach ($tum as $k) {
            $a = $db->real_escape_string('g' . $g . ':' . $e['tarih']);
            $db->query("INSERT IGNORE INTO ik_egitim_bildirim_log (egitim_id, kullanici_id, anahtar) VALUES (" . intval($eid) . ", $k, '$a')");
        }
    }
    if (in_array('olustur', $gunler, true)) {
        foreach ($yeniKisiler as $k) if (ik_etkinlik_gonder($db, $e, $k, 'yeni', 'yeni')) $n++;
        if (!$yeniMi && $degisti) foreach (array_diff($tum, $yeniKisiler) as $k) if (ik_etkinlik_gonder($db, $e, $k, 'guncel:' . $imza, 'guncel')) $n++;
    }
    return $n;
}

/** Vakti gelen hatırlatmaları gönderir. Saatte en çok 2 kez çalışır; gün içinde 08:00'den sonra gider. */
function ik_etkinlik_hatirlat($db, $zorla = false)
{
    ik_etkinlik_sema($db);
    $son = $db->query("SELECT deger FROM ik_ayarlar WHERE anahtar = 'etkinlik_son'");
    $son = $son ? $son->fetch_row() : null;
    if (!$zorla && $son && intval($son[0]) > time() - 1800) return 0;
    ik_ayar_yaz($db, 'etkinlik_son', (string)time());
    if (!$zorla && intval(date('G')) < 8) return 0;
    $bugun = date('Y-m-d'); $n = 0;
    $q = $db->query("SELECT * FROM ik_egitimler WHERE bildirim_ac = 1 AND tarih IS NOT NULL AND tarih >= '$bugun' AND tarih <= DATE_ADD('$bugun', INTERVAL 7 DAY)");
    $liste = []; if ($q) while ($r = $q->fetch_assoc()) $liste[] = $r;
    foreach ($liste as $e) {
        $fark = intval((strtotime($e['tarih']) - strtotime($bugun)) / 86400);
        $g = (string)$fark;
        if (!in_array($g, ['7', '3', '1', '0'], true) || !in_array($g, ik_etkinlik_gunler($e['bildirim_gunler']), true)) continue;
        foreach (ik_etkinlik_katilimcilar($db, $e['id']) as $k) {
            $ak = $db->query("SELECT 1 FROM kullanicilar WHERE id = $k AND aktif = 1"); // pasif kişiye gitmesin
            if ($ak && !$ak->num_rows) continue;
            if (ik_etkinlik_gonder($db, $e, $k, 'g' . $g . ':' . $e['tarih'], 'g' . $g)) $n++;
        }
    }
    return $n;
}

} // IK_ETKINLIK

/* ───────── komut satırı (cron):  php ik_etkinlik.php  → tüm kurumların hatırlatmaları ───────── */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    require_once __DIR__ . '/config/baglan.php';
    $db->kurum = 0; $top = 0; $kurumlar = [];
    $q = $db->query("SELECT id FROM pf_kurumlar WHERE durum = 'aktif'"); if ($q) while ($r = $q->fetch_row()) $kurumlar[] = intval($r[0]);
    foreach ($kurumlar as $kid) {
        $db->kurum = $kid; unset($GLOBALS['__ik_ayar']);
        try { $top += ik_etkinlik_hatirlat($db, true); } catch (Throwable $e) {}
    }
    $db->kurum = 0;
    echo "Gönderilen hatırlatma: $top\n";
}
