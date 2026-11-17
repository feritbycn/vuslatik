<?php
// ============================================================
// platform_mesaj.php — Kurumlara kullanım süresi mesajları (SMS + e-posta)
//   • Otomatik: bitişe N gün kala (ör. 10 ve 3) ve süre bitince bir kez gönderir; her (kurum, tür, bitiş tarihi) için tek seferlik
//   • Toplu: platform panelinden seçilen kurumlara elle SMS / e-posta
//   • Gönderim kanalı: "gönderen kurum"un Bildirim kanalları ayarları (SMS sağlayıcı, SMTP) kullanılır
//   • Tetikleme: platform ve İK sayfası istekleriyle (saatte en çok bir tarama) ya da cron:  php platform_mesaj.php
// ============================================================
require_once __DIR__ . '/platform_ortak.php';
if (!defined('PF_MESAJ')) {
define('PF_MESAJ', true);

function pf_lm_tanim()
{
    return [
        'lm_aktif' => '0', 'lm_sms' => '1', 'lm_mail' => '1', 'lm_gunler' => '10,3', 'lm_bitti' => '1', 'lm_kurum' => '0', 'lm_son_tarama' => '0',
        'lm_sms_kalan' => '{kurum} kullanım süreniz {tarih} tarihinde bitiyor ({kalan} gün kaldı). Kesinti yaşamamak için süreyi yenileyin.',
        'lm_mail_kalan_konu' => 'Kullanım süreniz {kalan} gün içinde bitiyor',
        'lm_mail_kalan' => "Merhaba,\n\n{kurum} kurumunun kullanım süresi {tarih} tarihinde sona erecek ({kalan} gün kaldı).\nHizmetin kesintisiz devam etmesi için lütfen süreyi yenileyin.",
        'lm_sms_bitti' => '{kurum} kullanım süreniz {tarih} tarihinde sona erdi. {son_durum} Yenilemek için bizimle iletişime geçin.',
        'lm_mail_bitti_konu' => 'Kullanım süreniz sona erdi',
        'lm_mail_bitti' => "Merhaba,\n\n{kurum} kurumunun kullanım süresi {tarih} tarihinde sona erdi. {son_durum}\nSüreyi yenilemek için lütfen bizimle iletişime geçin.",
    ];
}
function pf_lm_ayar($db)
{
    $a = pf_lm_tanim();
    $o = $db->kurum; $db->kurum = 0;
    $q = @$db->query("SELECT anahtar, deger FROM pf_ayarlar WHERE anahtar LIKE 'lm\\_%'");
    if ($q) while ($r = $q->fetch_row()) if (isset($a[$r[0]]) && (string)$r[1] !== '') $a[$r[0]] = (string)$r[1];
    $db->kurum = $o;
    return $a;
}
function pf_lm_yaz($db, $k, $v)
{
    if (!isset(pf_lm_tanim()[$k])) return false;
    $o = $db->kurum; $db->kurum = 0;
    $st = $db->prepare("INSERT INTO pf_ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)");
    $v = (string)$v; $st->bind_param('ss', $k, $v); $ok = $st->execute();
    $db->kurum = $o;
    return $ok;
}
function pf_lm_sema($db)
{
    static $y = false; if ($y) return; $y = true;
    $o = $db->kurum; $db->kurum = 0;
    $db->query("CREATE TABLE IF NOT EXISTS pf_lisans_mesaj (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, kurum_id INT NOT NULL, tur VARCHAR(20) NOT NULL, bitis DATE NULL, kanal VARCHAR(8) NOT NULL,
        alici VARCHAR(160) NOT NULL DEFAULT '', ok TINYINT(1) NOT NULL DEFAULT 0, sonuc VARCHAR(300) NOT NULL DEFAULT '', zaman DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id), KEY ix_k (kurum_id, tur), KEY ix_z (zaman)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->kurum = $o;
}
function pf_lm_degiskenler($k)
{
    $l = pf_lisans($k);
    $son = $l['durum'] === 'ek_sure' ? ($l['ek_kalan'] . ' gün içinde yenilenmezse hesabınız askıya alınır.') : ($l['durum'] === 'askida' ? 'Hesabınız askıya alınmıştır.' : '');
    return ['{kurum}' => (string)$k['ad'], '{kalan}' => (string)max(0, intval($l['kalan'] ?? 0)), '{tarih}' => !empty($l['bitis']) ? date('d.m.Y', strtotime($l['bitis'])) : '', '{ek_gun}' => (string)intval($l['ek_kalan'] ?? 0), '{son_durum}' => $son,
        '{site}' => (string)pf_ayar($GLOBALS['db'], 'site_adi', ''), '{destek}' => trim(pf_ayar($GLOBALS['db'], 'destek_tel', '') . ' ' . pf_ayar($GLOBALS['db'], 'destek_mail', ''))];
}
/** Kurumun telefon / e-postası; boşsa kurum yetkilisinin (kurucu) bilgisi */
function pf_lm_alicilar($db, $k)
{
    $tel = trim((string)$k['telefon']); $mail = trim((string)$k['email']);
    if ($tel === '' || $mail === '') {
        $o = $db->kurum; $db->kurum = intval($k['id']);
        try { $q = $db->query("SELECT telefon, email FROM kullanicilar WHERE rol = 'kurucu' AND aktif = 1 ORDER BY id"); if ($q) while ($r = $q->fetch_row()) { if ($tel === '' && trim((string)$r[0]) !== '') $tel = trim((string)$r[0]); if ($mail === '' && filter_var(trim((string)$r[1]), FILTER_VALIDATE_EMAIL)) $mail = trim((string)$r[1]); } } catch (Throwable $e) {}
        $db->kurum = $o;
    }
    return [$tel, $mail];
}
function pf_lm_hazir($db)
{
    $a = pf_lm_ayar($db); $gk = intval($a['lm_kurum']);
    if ($gk <= 0 || !pf_kurum($db, $gk)) return ['sms' => false, 'mail' => false, 'kurum' => 0];
    require_once __DIR__ . '/ik_kanal.php';
    $o = $db->kurum; $db->kurum = $gk; unset($GLOBALS['__ik_ayar']);
    $r = ['sms' => ik_sms_hazir($db), 'mail' => ik_mail_hazir($db), 'kurum' => $gk];
    $db->kurum = $o; unset($GLOBALS['__ik_ayar']);
    return $r;
}
/** Tek kuruma gönder. $tur: k10 | k3 | bitti | toplu. Dönen: ['sms'=>[ok,mesaj]|null, 'mail'=>…] */
function pf_lm_gonder($db, $k, $tur, $smsMetin, $mailKonu, $mailMetin, $smsAc, $mailAc)
{
    pf_lm_sema($db);
    $a = pf_lm_ayar($db); $gk = intval($a['lm_kurum']); $sonuc = ['sms' => null, 'mail' => null];
    if ($gk <= 0) return ['hata' => 'Önce “Gönderen kurum” seçin (SMS ve e-posta ayarları o kurumdan alınır).'];
    require_once __DIR__ . '/ik_kanal.php';
    list($tel, $mail) = pf_lm_alicilar($db, $k);
    $v = pf_lm_degiskenler($k);
    $sms = strtr($smsMetin, $v); $konu = strtr($mailKonu, $v); $govde = strtr($mailMetin, $v);
    $bitis = !empty($k['lisans_bitis']) ? $k['lisans_bitis'] : null;
    $o = $db->kurum; $db->kurum = $gk; unset($GLOBALS['__ik_ayar']);
    try {
        if ($smsAc) {
            $r = $tel === '' ? ['ok' => false, 'sonuc' => 'Telefon numarası yok'] : ik_sms_gonder($db, $tel, $sms);
            $sonuc['sms'] = [$r['ok'], $r['sonuc'], $tel];
        }
        if ($mailAc) {
            if ($mail === '') $r = ['ok' => false, 'sonuc' => 'E-posta adresi yok'];
            else {
                $html = ik_mail_sablon($konu, nl2br(htmlspecialchars($govde, ENT_QUOTES, 'UTF-8')), '', '', $db, (string)$k['yetkili_ad']);
                $r = ik_mail_gonder($db, $mail, $konu, $html, (string)$k['yetkili_ad']);
            }
            $sonuc['mail'] = [$r['ok'], $r['sonuc'], $mail];
        }
    } catch (Throwable $e) { foreach (['sms', 'mail'] as $x) if ($sonuc[$x] === null && ($x === 'sms' ? $smsAc : $mailAc)) $sonuc[$x] = [false, 'Hata: ' . $e->getMessage(), '']; }
    $db->kurum = 0; unset($GLOBALS['__ik_ayar']);
    $st = $db->prepare("INSERT INTO pf_lisans_mesaj (kurum_id, tur, bitis, kanal, alici, ok, sonuc) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach (['sms', 'mail'] as $x) if ($sonuc[$x] !== null) {
        $kid = intval($k['id']); $ok = $sonuc[$x][0] ? 1 : 0; $al = mb_substr((string)$sonuc[$x][2], 0, 160, 'UTF-8'); $sn = mb_substr((string)$sonuc[$x][1], 0, 300, 'UTF-8');
        $st->bind_param('issssis', $kid, $tur, $bitis, $x, $al, $ok, $sn); $st->execute();
    }
    $db->kurum = $o;
    return $sonuc;
}
/** Süresi dolmak üzere olan / dolan kurumlara otomatik mesaj. Saatte en çok bir kez çalışır ($zorla ile her zaman). */
function pf_lm_tara($db, $zorla = false)
{
    $o = $db->kurum; $db->kurum = 0;
    try {
        pf_lm_sema($db);
        $a = pf_lm_ayar($db);
        if ($a['lm_aktif'] !== '1' || (intval($a['lm_kurum']) <= 0)) return ['gonderilen' => 0, 'neden' => 'kapali'];
        if (!$zorla && time() - intval($a['lm_son_tarama']) < 3600) return ['gonderilen' => 0, 'neden' => 'erken'];
        $kilit = $db->query("SELECT GET_LOCK('tb_lisans_mesaj', 0)"); if (!$kilit || intval($kilit->fetch_row()[0]) !== 1) return ['gonderilen' => 0, 'neden' => 'kilit'];
        pf_lm_yaz($db, 'lm_son_tarama', (string)time());
        $gunler = array_values(array_unique(array_filter(array_map('intval', preg_split('/[,\s;]+/', $a['lm_gunler'])), function ($g) { return $g >= 0 && $g <= 90; }))); sort($gunler);
        $sayi = 0; $kurumlar = [];
        $q = $db->query("SELECT * FROM pf_kurumlar WHERE durum = 'aktif' AND lisans_tur <> 'sinirsiz' AND lisans_bitis IS NOT NULL ORDER BY id"); if ($q) while ($r = $q->fetch_assoc()) $kurumlar[] = $r;
        foreach ($kurumlar as $k) {
            $l = pf_lisans($k); $kalan = $l['kalan']; if ($kalan === null) continue;
            $tur = null;
            if ($kalan >= 0) { foreach ($gunler as $g) if ($kalan <= $g) { $tur = 'k' . $g; break; } }
            elseif ($a['lm_bitti'] === '1' && $kalan >= -30) $tur = 'bitti';
            if (!$tur) continue;
            $e = $db->real_escape_string($tur); $b = $db->real_escape_string((string)$k['lisans_bitis']);
            $var = $db->query("SELECT 1 FROM pf_lisans_mesaj WHERE kurum_id = " . intval($k['id']) . " AND tur = '$e' AND bitis = '$b' AND (ok = 1 OR zaman > DATE_SUB(NOW(), INTERVAL 6 HOUR)) LIMIT 1");
            if ($var && $var->num_rows) continue;
            $bitti = $tur === 'bitti';
            $s = pf_lm_gonder($db, $k, $tur, $bitti ? $a['lm_sms_bitti'] : $a['lm_sms_kalan'], $bitti ? $a['lm_mail_bitti_konu'] : $a['lm_mail_kalan_konu'], $bitti ? $a['lm_mail_bitti'] : $a['lm_mail_kalan'], $a['lm_sms'] === '1', $a['lm_mail'] === '1');
            if (isset($s['hata'])) break;
            $sayi++;
            pf_log($db, 'lisans_mesaj', $k['ad'] . ' → ' . $tur, intval($k['id']));
            if ($sayi >= 25) break;
        }
        return ['gonderilen' => $sayi];
    } catch (Throwable $e) { return ['gonderilen' => 0, 'neden' => $e->getMessage()]; }
    finally { $db->query("SELECT RELEASE_LOCK('tb_lisans_mesaj')"); $db->kurum = $o; }
}
/** Sayfa isteğinden sonra, yanıtı geciktirmeden tara */
function pf_lm_tara_arkada($db)
{
    require_once __DIR__ . '/ik_kanal.php';
    ik_sonra(function () use ($db) { @set_time_limit(120); pf_lm_tara($db); });
}

/* ───────── komut satırı (cron):  php platform_mesaj.php ───────── */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    require_once __DIR__ . '/config/baglan.php';
    $r = pf_lm_tara($db, true);
    echo 'Gönderilen kurum: ' . intval($r['gonderilen']) . (isset($r['neden']) ? ' (' . $r['neden'] . ')' : '') . "\n";
}
} // PF_MESAJ
