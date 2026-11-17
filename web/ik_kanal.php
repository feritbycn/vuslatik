<?php
// ============================================================
// ik_kanal.php — İK bildirim kanalları
//   • Site içi bildirim kuyruğu (ik_bildirimler) — sayfa açıkken anlık uyarı + ses, arka planda tarayıcı bildirimi
//   • Tarayıcı / telefon bildirimi (Web Push, VAPID) — site KAPALIYKEN bile gelir (ik-sw.js servis çalışanı)
//   • SMS (NetGSM · İleti Merkezi · özel HTTP API) ve E-posta (SMTP ya da PHP mail())
//   • Her gönderim ik_bildirim_log tablosuna yazılır (Bildirim kanalları sayfasında görünür)
// ik_ortak.php tarafından yüklenir.
// ============================================================
if (!defined('IK_KANAL')) {
define('IK_KANAL', true);

/** Ayar anahtarları: bu anahtarlar tarayıcıya ASLA gönderilmez */
define('IK_GIZLI_AYARLAR', ['sms_sifre', 'smtp_sifre', 'vapid_private']);
define('IK_KANAL_AYARLARI', [
    'sms_saglayici', 'sms_kullanici', 'sms_sifre', 'sms_baslik', 'sms_ozel_url', 'sms_ozel_govde', 'sms_ozel_basari',
    'smtp_host', 'smtp_port', 'smtp_guvenlik', 'smtp_kullanici', 'smtp_sifre', 'mail_gonderen', 'mail_gonderen_ad', 'site_url', 'mail_firma_ad', 'mail_imza', 'mail_renk', 'mail_renk2', 'mail_logo', 'mail_pdf_ek',
]);

/* ---------------------------------------------------------------- Ayar yardımcıları ---------------------------------------------------------------- */
function ik_ayar_tablosu($db)
{
    static $y = false;
    if ($y) return;
    $y = true;
    $db->query("CREATE TABLE IF NOT EXISTS ik_ayarlar (anahtar VARCHAR(60) NOT NULL, deger VARCHAR(2000) NOT NULL DEFAULT '', PRIMARY KEY (anahtar)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}
function ik_ayar($db, $anahtar, $varsayilan = '')
{
    if (!isset($GLOBALS['__ik_ayar'])) {
        $GLOBALS['__ik_ayar'] = [];
        $q = $db->query("SELECT anahtar, deger FROM ik_ayarlar");
        if ($q) while ($r = $q->fetch_row()) $GLOBALS['__ik_ayar'][$r[0]] = $r[1];
    }
    $c = $GLOBALS['__ik_ayar'];
    return array_key_exists($anahtar, $c) && $c[$anahtar] !== '' ? $c[$anahtar] : $varsayilan;
}
function ik_ayar_yaz($db, $anahtar, $deger)
{
    $st = $db->prepare("INSERT INTO ik_ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)");
    $deger = (string)$deger;
    $st->bind_param('ss', $anahtar, $deger);
    $ok = $st->execute();
    if ($ok && isset($GLOBALS['__ik_ayar'])) $GLOBALS['__ik_ayar'][$anahtar] = $deger;
    return $ok;
}

/** Sitenin kök adresi (SMS / e-postadaki bağlantılar için), sonunda "/" ile */
function ik_site_url($db = null)
{
    if (!empty($GLOBALS['TB_VEKIL_BASE'])) return $GLOBALS['TB_VEKIL_BASE'];     // uzak (vekil) sunucudan gelen istekte kurumun gerçek adresi
    if ($db) {
        $o = trim((string)ik_ayar($db, 'site_url', ''));
        if ($o !== '' && preg_match('~^https?://~i', $o)) return rtrim($o, '/') . '/';
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
    $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
    return ($https ? 'https://' : 'http://') . $host . $dir . '/';
}

/** 05xx xxx xx xx / +90 5xx… / 5xx… → 905xxxxxxxxx ; geçersizse '' */
function ik_telefon_norm($t)
{
    $d = preg_replace('/\D/', '', (string)$t);
    if ($d === '') return '';
    if (strpos($d, '00') === 0) $d = substr($d, 2);
    if (strlen($d) === 11 && $d[0] === '0') $d = '90' . substr($d, 1);
    elseif (strlen($d) === 10 && $d[0] === '5') $d = '90' . $d;
    if (strlen($d) < 11 || strlen($d) > 15) return '';
    if (strpos($d, '90') === 0 && !preg_match('/^905\d{9}$/', $d)) return '';
    return $d;
}

/* ---------------------------------------------------------------- Tablolar ---------------------------------------------------------------- */
function ik_kanal_sema($db)
{
    static $y = false;
    if ($y) return;
    $y = true;
    ik_ayar_tablosu($db);
    $db->query("CREATE TABLE IF NOT EXISTS ik_bildirimler (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        baslik VARCHAR(200) NOT NULL DEFAULT '',
        mesaj TEXT NULL,
        link VARCHAR(300) NOT NULL DEFAULT '',
        tur VARCHAR(40) NOT NULL DEFAULT '',
        okundu TINYINT(1) NOT NULL DEFAULT 0,
        push_durum TINYINT(1) NOT NULL DEFAULT 0,
        olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY ix_ikb_kisi (kullanici_id, id),
        KEY ix_ikb_okundu (kullanici_id, okundu)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_push_aboneler (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        endpoint_hash CHAR(64) NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(200) NOT NULL DEFAULT '',
        auth VARCHAR(100) NOT NULL DEFAULT '',
        ua VARCHAR(200) NOT NULL DEFAULT '',
        olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        son_basari DATETIME NULL,
        PRIMARY KEY (id), UNIQUE KEY uq_push (endpoint_hash), KEY ix_push_kisi (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_mobil_cihazlar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kullanici_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL,
        token VARCHAR(512) NOT NULL,
        platform VARCHAR(10) NOT NULL DEFAULT '',
        ua VARCHAR(200) NOT NULL DEFAULT '',
        olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        son_basari DATETIME NULL,
        PRIMARY KEY (id), UNIQUE KEY uq_mobil (token_hash), KEY ix_mobil_kisi (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_fcm_kuyruk (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        bildirim_id BIGINT UNSIGNED NOT NULL,
        kullanici_id INT NOT NULL,
        durum VARCHAR(12) NOT NULL DEFAULT 'bekliyor',
        deneme TINYINT UNSIGNED NOT NULL DEFAULT 0,
        sonraki DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        tamam TEXT NULL,
        son_hata VARCHAR(400) NOT NULL DEFAULT '',
        olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        gonderim DATETIME NULL,
        PRIMARY KEY (id), UNIQUE KEY uq_fcm_bildirim (bildirim_id), KEY ix_fcm_durum (durum, sonraki)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_fcm_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        zaman DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        kullanici_id INT NULL,
        cihaz_id INT NULL,
        bildirim_id BIGINT NULL,
        http SMALLINT NOT NULL DEFAULT 0,
        sonuc VARCHAR(12) NOT NULL DEFAULT '',
        ayrinti VARCHAR(400) NOT NULL DEFAULT '',
        PRIMARY KEY (id), KEY ix_fcm_log_z (zaman)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS ik_bildirim_log (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        zaman DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        kullanici_id INT NULL,
        kanal VARCHAR(10) NOT NULL,
        alici VARCHAR(160) NOT NULL DEFAULT '',
        konu VARCHAR(200) NOT NULL DEFAULT '',
        basarili TINYINT(1) NOT NULL DEFAULT 0,
        sonuc VARCHAR(500) NOT NULL DEFAULT '',
        PRIMARY KEY (id), KEY ix_ikl_zaman (zaman)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function ik_kanal_log($db, $kid, $kanal, $alici, $konu, $ok, $sonuc)
{
    try {
        $st = $db->prepare("INSERT INTO ik_bildirim_log (kullanici_id, kanal, alici, konu, basarili, sonuc) VALUES (?, ?, ?, ?, ?, ?)");
        $kid = $kid ? intval($kid) : null; $alici = mb_substr((string)$alici, 0, 160, 'UTF-8'); $konu = mb_substr((string)$konu, 0, 200, 'UTF-8');
        $ok = $ok ? 1 : 0; $sonuc = mb_substr((string)$sonuc, 0, 500, 'UTF-8');
        $st->bind_param('isssis', $kid, $kanal, $alici, $konu, $ok, $sonuc);
        $st->execute();
        $db->query("DELETE FROM ik_bildirim_log WHERE zaman < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    } catch (Throwable $e) {}
}

/* ---------------------------------------------------------------- Yanıttan sonra çalıştır ---------------------------------------------------------------- */
/** Yavaş işleri (SMS, e-posta, push) yanıt tarayıcıya gittikten SONRA çalıştırır; kullanıcı beklemez. */
function ik_sonra(callable $is)
{
    if (PHP_SAPI === 'cli') { try { $is(); } catch (Throwable $e) {} return; } // cron / komut satırı: bekletmeden hemen çalıştır
    static $liste = [], $kayitli = false;
    $liste[] = $is;
    if ($kayitli) return;
    $kayitli = true;
    register_shutdown_function(function () use (&$liste) {
        ignore_user_abort(true);
        @set_time_limit(60);
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        else { while (ob_get_level() > 0) @ob_end_flush(); @flush(); }
        foreach ($liste as $f) { try { $f(); } catch (Throwable $e) {} }
    });
}

/* ---------------------------------------------------------------- HTTP ---------------------------------------------------------------- */
/** SSRF koruması: giden bağlantı adresi iç ağa / yerel makineye çıkmasın (SMS API adresi ve SMTP sunucusu kurum yöneticisince girilebilir). */
function ik_dis_adres_guvenli($host)
{
    if (getenv('TB_IZIN_YEREL') === '1') return true;                      // yalnızca yerel test
    $host = strtolower(trim((string)$host, " []\t")); if ($host === '' || $host === 'localhost' || substr($host, -6) === '.local' || substr($host, -9) === '.internal') return false;
    $ipler = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (array)@gethostbynamel($host);
    if (!$ipler) { $aaaa = @dns_get_record($host, DNS_AAAA); foreach ((array)$aaaa as $r) if (!empty($r['ipv6'])) $ipler[] = $r['ipv6']; }
    if (!$ipler) return true;                                              // çözümlenemiyor: bağlantı zaten kurulamaz
    foreach ($ipler as $ip) if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return false;
    return true;
}


/** @return array [http_kodu, govde, hata] */
function ik_http($yontem, $url, $baslik = [], $govde = null, $zaman = 10)
{
    $ph = parse_url((string)$url); if (!$ph || !in_array(strtolower($ph['scheme'] ?? ''), ['http', 'https'], true) || !ik_dis_adres_guvenli($ph['host'] ?? '')) return [0, '', 'Güvenlik: bu adrese bağlanılamaz (yerel/iç ağ adresleri engellidir).'];
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $zaman, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_CUSTOMREQUEST => $yontem,
            CURLOPT_HTTPHEADER => $baslik, CURLOPT_FOLLOWLOCATION => false, CURLOPT_SSL_VERIFYPEER => true]);
        if ($govde !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, $govde);
        $c = curl_exec($ch); $kod = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE); $h = curl_error($ch);
        curl_close($ch);
        return [$kod, $c === false ? '' : (string)$c, $h];
    }
    $ctx = stream_context_create(['http' => ['method' => $yontem, 'header' => implode("\r\n", $baslik), 'content' => $govde ?? '', 'timeout' => $zaman, 'ignore_errors' => true]]);
    $c = @file_get_contents($url, false, $ctx); $kod = 0;
    if (!empty($http_response_header[0]) && preg_match('~\s(\d{3})\s~', $http_response_header[0], $m)) $kod = (int)$m[1];
    return [$kod, $c === false ? '' : (string)$c, $c === false ? 'Bağlantı kurulamadı' : ''];
}

/* ---------------------------------------------------------------- SMS ---------------------------------------------------------------- */
function ik_sms_hazir($db)
{
    $s = ik_ayar($db, 'sms_saglayici', '');
    if ($s === 'ozel') return ik_ayar($db, 'sms_ozel_url', '') !== '';
    return in_array($s, ['netgsm', 'iletimerkezi'], true) && ik_ayar($db, 'sms_kullanici', '') !== '' && ik_ayar($db, 'sms_sifre', '') !== '';
}
/** @return array ['ok'=>bool, 'sonuc'=>string] */
function ik_sms_gonder($db, $telefon, $mesaj)
{
    $tel = ik_telefon_norm($telefon);
    if ($tel === '') return ['ok' => false, 'sonuc' => 'Geçerli telefon numarası yok'];
    $s = ik_ayar($db, 'sms_saglayici', '');
    if (!ik_sms_hazir($db)) return ['ok' => false, 'sonuc' => 'SMS ayarları yapılmamış'];
    // SMS operatörleri tireyi, tırnağı ve üç noktayı "?" gösterebilir; düz karakterlere çevir
    $mesaj = strtr((string)$mesaj, ["\u{2013}" => '-', "\u{2014}" => '-', "\u{2212}" => '-', "\u{2018}" => "'", "\u{2019}" => "'", "\u{201C}" => '"', "\u{201D}" => '"', "\u{2026}" => '...', "\u{00A0}" => ' ', "\u{2022}" => '-']);
    $mesaj = mb_substr(trim($mesaj), 0, 450, 'UTF-8');
    $kul = ik_ayar($db, 'sms_kullanici', ''); $sif = ik_ayar($db, 'sms_sifre', ''); $baslik = ik_ayar($db, 'sms_baslik', '');
    try {
        if ($s === 'netgsm') {
            // NetGSM REST v2 (Basic yetkilendirme)
            $yuk = json_encode(['msgheader' => $baslik, 'messages' => [['msg' => $mesaj, 'no' => $tel]], 'encoding' => 'TR', 'iysfilter' => '', 'partnercode' => ''], JSON_UNESCAPED_UNICODE);
            list($kod, $c, $h) = ik_http('POST', 'https://api.netgsm.com.tr/sms/rest/v2/send', ['Content-Type: application/json', 'Authorization: Basic ' . base64_encode($kul . ':' . $sif)], $yuk);
            $j = json_decode($c, true);
            $rk = is_array($j) ? (string)($j['code'] ?? '') : '';
            if ($rk === '00' || $rk === '01' || $rk === '02') return ['ok' => true, 'sonuc' => 'NetGSM: ' . ($j['jobid'] ?? 'gönderildi')];
            return ['ok' => false, 'sonuc' => 'NetGSM yanıtı: ' . ($h !== '' ? $h : mb_substr($c, 0, 300, 'UTF-8')) . ' (HTTP ' . $kod . ')'];
        }
        if ($s === 'iletimerkezi') {
            $x = '<request><authentication><username>' . htmlspecialchars($kul, ENT_XML1) . '</username><password>' . htmlspecialchars($sif, ENT_XML1) . '</password></authentication>' .
                '<order><sender>' . htmlspecialchars($baslik, ENT_XML1) . '</sender><message><text>' . htmlspecialchars($mesaj, ENT_XML1) . '</text><receipents><number>' . $tel . '</number></receipents></message></order></request>';
            list($kod, $c, $h) = ik_http('POST', 'https://api.iletimerkezi.com/v1/send-sms', ['Content-Type: text/xml; charset=utf-8'], $x);
            if (preg_match('~<status>\s*<code>\s*(\d+)~', $c, $m) && $m[1] === '200') return ['ok' => true, 'sonuc' => 'İleti Merkezi: gönderildi'];
            $msg = preg_match('~<message>(.*?)</message>~s', $c, $m2) ? $m2[1] : mb_substr($c, 0, 300, 'UTF-8');
            return ['ok' => false, 'sonuc' => 'İleti Merkezi yanıtı: ' . ($h !== '' ? $h : $msg) . ' (HTTP ' . $kod . ')'];
        }
        // Özel HTTP API: {telefon} {mesaj} {baslik} yer tutucuları
        $url = ik_ayar($db, 'sms_ozel_url', ''); $govde = ik_ayar($db, 'sms_ozel_govde', ''); $basari = ik_ayar($db, 'sms_ozel_basari', '');
        $rep = function ($t, $kodla) use ($tel, $mesaj, $baslik) { return strtr($t, ['{telefon}' => $kodla($tel), '{mesaj}' => $kodla($mesaj), '{baslik}' => $kodla($baslik)]); };
        if ($govde !== '') {
            $js = ltrim($govde)[0] === '{' || ltrim($govde)[0] === '[';
            $b = $rep($govde, function ($v) use ($js) { return $js ? trim(json_encode($v, JSON_UNESCAPED_UNICODE), '"') : rawurlencode($v); });
            list($kod, $c, $h) = ik_http('POST', $rep($url, 'rawurlencode'), ['Content-Type: ' . ($js ? 'application/json' : 'application/x-www-form-urlencoded')], $b);
        } else {
            list($kod, $c, $h) = ik_http('GET', $rep($url, 'rawurlencode'));
        }
        $iyi = $kod >= 200 && $kod < 300 && ($basari === '' || mb_stripos($c, $basari, 0, 'UTF-8') !== false);
        return ['ok' => $iyi, 'sonuc' => 'HTTP ' . $kod . ': ' . ($h !== '' ? $h : mb_substr(trim($c), 0, 300, 'UTF-8'))];
    } catch (Throwable $e) {
        return ['ok' => false, 'sonuc' => 'Hata: ' . $e->getMessage()];
    }
}

/* ---------------------------------------------------------------- E-posta ---------------------------------------------------------------- */
function ik_mail_hazir($db) { return filter_var(ik_ayar($db, 'mail_gonderen', ''), FILTER_VALIDATE_EMAIL) !== false || ik_ayar($db, 'smtp_host', '') !== ''; }
function ik_mail_gonderen($db)
{
    $m = ik_ayar($db, 'mail_gonderen', '');
    if (!filter_var($m, FILTER_VALIDATE_EMAIL)) {
        $host = preg_replace('/^www\./i', '', preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')));
        $m = 'bildirim@' . $host;
    }
    return [$m, ik_ayar($db, 'mail_gonderen_ad', (function_exists('pf_imza_adi') ? pf_imza_adi($db) : 'Turkuaz Bileşim') . ' İnsan Kaynakları')];
}
/** Modern e-posta şablonu (satır içi CSS — tüm e-posta istemcilerinde çalışır).
 *  Üst şerit: kurum logosu (İK ayarlarından yüklenen "mail_logo") ya da logo yoksa firma adı. $ad verilirse "Merhaba {ad}," selamı eklenir. */
function ik_mail_sablon($baslik, $govdeHtml, $link = '', $buton = 'Aç', $db = null, $ad = '')
{
    $db = $db ?: ($GLOBALS['db'] ?? null);
    $h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); };
    $firma = $db ? trim((string)ik_ayar($db, 'mail_firma_ad', '')) : '';
    $imza = $db ? trim((string)ik_ayar($db, 'mail_imza', '')) : '';
    $renk = $db ? trim((string)ik_ayar($db, 'mail_renk', '')) : '';
    if (!preg_match('/^#[0-9a-f]{6}$/i', $renk)) $renk = '#6366f1';
    $renk2 = $db ? trim((string)ik_ayar($db, 'mail_renk2', '')) : '';
    if (!preg_match('/^#[0-9a-f]{6}$/i', $renk2)) $renk2 = $renk;
    $logo = $db ? trim((string)ik_ayar($db, 'mail_logo', '')) : '';
    $logoUrl = $logo === '' ? '' : (preg_match('~^(https?://|data:image/)~i', $logo) ? $logo : ik_site_url($db) . ltrim($logo, '/'));
    // Sunucudaki logo dosyası mailin İÇİNE gömülür (cid): adrese ve "resimleri göster" iznine bağlı kalmaz, spam klasöründe de görünür
    unset($GLOBALS['ik_mail_cid']);
    if ($logo !== '' && !preg_match('~^(https?://|data:)~i', $logo) && defined('TB_KOK')) {
        $yol = TB_KOK . '/' . ltrim($logo, '/'); $uz = strtolower(pathinfo($yol, PATHINFO_EXTENSION));
        $tur = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp'][$uz] ?? '';
        if ($tur !== '' && strpos($logo, '..') === false && is_file($yol) && filesize($yol) <= 1048576) { $GLOBALS['ik_mail_cid'] = ['dosya' => $yol, 'tur' => $tur, 'url' => $logoUrl]; $logoUrl = 'cid:logo@turkuaz.ik'; }
    }
    if ($firma === '') $firma = 'İNSAN KAYNAKLARI';
    $ust = $logoUrl !== ''
        ? '<img src="' . $h($logoUrl) . '" alt="' . $h($firma) . '" height="56" style="display:block;margin:0 auto;height:56px;max-width:260px;border:0">'
        : '<span style="display:inline-block;background:#ffffff;border-radius:12px;padding:10px 20px;font-size:19px;font-weight:800;color:' . $renk . '">' . $h($firma) . '</span>';
    $selam = $ad !== '' ? '<tr><td style="padding:26px 32px 0;color:#0f172a;font-size:15px">Merhaba <b>' . $h($ad) . '</b>,</td></tr>' : '';
    $b = $link !== '' ? '<tr><td style="padding:4px 32px 26px"><a href="' . $h($link) . '" style="display:inline-block;background:' . $renk . ';color:#ffffff;text-decoration:none;font-weight:700;font-size:15px;padding:13px 28px;border-radius:12px">' . $h($buton) . '</a></td></tr>' : '';
    $im = '<tr><td style="padding:0 32px 26px;color:#475569;font-size:14px;line-height:1.6">Sevgiler 👋<br><b>' . ($imza !== '' ? nl2br($h($imza)) : $h($firma)) . '</b></td></tr>';
    return '<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body style="margin:0;padding:0;background:#e9edf3;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif">' .
        '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#e9edf3"><tr><td align="center" style="padding:24px 12px">' .
        '<table role="presentation" width="560" cellspacing="0" cellpadding="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:22px;overflow:hidden">' .
        '<tr><td align="center" style="background:' . $renk . ';background-image:linear-gradient(135deg,' . $renk . ',' . $renk2 . ');padding:26px 24px">' . $ust . '</td></tr>' . $selam .
        '<tr><td style="padding:' . ($ad !== '' ? '8' : '26') . 'px 32px 4px;color:#0f172a;font-size:18px;font-weight:800;line-height:1.35">' . $h($baslik) . '</td></tr>' .
        '<tr><td style="padding:6px 32px 18px;color:#334155;font-size:15px;line-height:1.65">' . $govdeHtml . '</td></tr>' . $b . $im .
        '<tr><td style="padding:14px 32px;background:#f8fafc;color:#94a3b8;font-size:12px">Bu e-posta İK sistemi tarafından otomatik gönderilmiştir.</td></tr>' .
        '</table></td></tr></table></body></html>';
}
function ik_mail_gonder($db, $kime, $konu, $html, $kimeAd = '', array $ekler = [])
{
    if (!filter_var($kime, FILTER_VALIDATE_EMAIL)) return ['ok' => false, 'sonuc' => 'Geçerli e-posta adresi yok'];
    list($gon, $gonAd) = ik_mail_gonderen($db);
    $metin = trim(html_entity_decode(strip_tags(preg_replace('~<(br|/p|/tr|/div)>~i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
    $metin = trim(preg_replace("/\n{3,}/", "\n\n", preg_replace('/[ \t]+/', ' ', $metin)));
    $sn = '=_' . bin2hex(random_bytes(12));
    $enc = function ($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; };
    $cid = $GLOBALS['ik_mail_cid'] ?? null;
    if (strpos($html, 'cid:logo@turkuaz.ik') !== false && !($cid && is_file($cid['dosya']))) $html = str_replace('cid:logo@turkuaz.ik', (string)($cid['url'] ?? ''), $html);
    $alt = "--$sn\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($metin)) .
           "--$sn\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$sn--\r\n";
    if ($cid && strpos($html, 'cid:logo@turkuaz.ik') !== false && is_file($cid['dosya'])) {     // gömülü logo: multipart/related { alternative, resim }
        $rb = '=_rl' . bin2hex(random_bytes(10)); $uzn = preg_replace('~^.*/~', '', $cid['tur']); $uzn = $uzn === 'jpeg' ? 'jpg' : $uzn;
        $alt = "--$rb\r\nContent-Type: multipart/alternative; boundary=\"$sn\"\r\n\r\n" . $alt .
               "--$rb\r\nContent-Type: " . $cid['tur'] . "; name=\"logo." . $uzn . "\"\r\nContent-Transfer-Encoding: base64\r\nContent-ID: <logo@turkuaz.ik>\r\nX-Attachment-Id: logo@turkuaz.ik\r\nContent-Disposition: inline; filename=\"logo." . $uzn . "\"\r\n\r\n" . chunk_split(base64_encode((string)file_get_contents($cid['dosya']))) . "--$rb--\r\n";
        $ustTip = "multipart/related; boundary=\"$rb\""; $sn2 = $rb;
    } else { $ustTip = "multipart/alternative; boundary=\"$sn\""; }
    if ($ekler) {                                                  // ekli e-posta: multipart/mixed { alternative, ekler… }
        $sd = '=_ek' . bin2hex(random_bytes(10));
        $govde = "--$sd\r\nContent-Type: $ustTip\r\n\r\n" . $alt;
        foreach ($ekler as $e) {
            $dn = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$e['ad']);
            $govde .= "--$sd\r\nContent-Type: " . $e['tur'] . "; name=\"$dn\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$dn\"\r\n\r\n" . chunk_split(base64_encode($e['icerik']));
        }
        $govde .= "--$sd--\r\n"; $tip = "multipart/mixed; boundary=\"$sd\"";
    } else { $govde = $alt; $tip = $ustTip; }
    $ortak = 'From: ' . $enc($gonAd) . " <$gon>\r\nMIME-Version: 1.0\r\nContent-Type: $tip";
    try {
        $host = ik_ayar($db, 'smtp_host', '');
        if ($host !== '') {
            $tam = $ortak . "\r\nTo: " . ($kimeAd !== '' ? $enc($kimeAd) . " <$kime>" : $kime) . "\r\nSubject: " . $enc($konu) . "\r\nDate: " . date('r') .
                   "\r\nMessage-ID: <" . bin2hex(random_bytes(10)) . '@' . preg_replace('/^.*@/', '', $gon) . ">\r\n\r\n" . $govde;
            return ik_smtp_gonder(['host' => $host, 'port' => (int)ik_ayar($db, 'smtp_port', '587'), 'guvenlik' => ik_ayar($db, 'smtp_guvenlik', 'tls'),
                'kullanici' => ik_ayar($db, 'smtp_kullanici', ''), 'sifre' => ik_ayar($db, 'smtp_sifre', '')], $gon, $kime, $tam);
        }
        $ok = @mail($kime, $enc($konu), $govde, $ortak, '-f' . $gon);
        return ['ok' => (bool)$ok, 'sonuc' => $ok ? 'Sunucu mail() ile gönderildi' : 'mail() başarısız (sunucu e-posta göndermeyi engelliyor olabilir; SMTP ayarlarını girin)'];
    } catch (Throwable $e) {
        return ['ok' => false, 'sonuc' => 'Hata: ' . $e->getMessage()];
    }
}
/** Küçük SMTP istemcisi: SSL (465) / STARTTLS (587) / düz; AUTH LOGIN */
function ik_smtp_gonder($c, $gon, $kime, $ham)
{
    $guv = $c['guvenlik'] ?? 'tls'; $port = $c['port'] ?: ($guv === 'ssl' ? 465 : 587);
    if (!ik_dis_adres_guvenli($c['host'] ?? '') && !in_array((int)$port, [25, 465, 587, 2525], true)) return ['ok' => false, 'sonuc' => 'Güvenlik: yerel/iç ağ SMTP sunucusuna yalnızca 25, 465, 587 ya da 2525 numaralı bağlantı noktasıyla bağlanılabilir.'];
    $fp = @stream_socket_client(($guv === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $port, $en, $es, 10, STREAM_CLIENT_CONNECT);
    if (!$fp) return ['ok' => false, 'sonuc' => "SMTP bağlantısı kurulamadı ($es)"];
    stream_set_timeout($fp, 12);
    $oku = function () use ($fp) { $s = ''; while (($l = fgets($fp, 1024)) !== false) { $s .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $s; };
    $kod = function ($s) { return (int)substr($s, 0, 3); };
    $yaz = function ($k, $beklenen) use ($fp, $oku, $kod) { fwrite($fp, $k . "\r\n"); $r = $oku(); return in_array($kod($r), (array)$beklenen, true) ? [true, $r] : [false, $r]; };
    $hata = function ($m) use ($fp) { @fwrite($fp, "QUIT\r\n"); @fclose($fp); return ['ok' => false, 'sonuc' => 'SMTP: ' . trim(mb_substr($m, 0, 300, 'UTF-8'))]; };
    $r = $oku(); if ($kod($r) !== 220) return $hata($r);
    $ehlo = 'EHLO ' . (preg_replace('/[^a-z0-9.-]/i', '', (string)($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost');
    list($ok, $r) = $yaz($ehlo, 250); if (!$ok) return $hata($r);
    if ($guv === 'tls') {
        list($ok, $r) = $yaz('STARTTLS', 220); if (!$ok) return $hata($r);
        if (!@stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $hata('TLS başlatılamadı');
        list($ok, $r) = $yaz($ehlo, 250); if (!$ok) return $hata($r);
    }
    if ($c['kullanici'] !== '') {
        list($ok, $r) = $yaz('AUTH LOGIN', 334); if (!$ok) return $hata($r);
        list($ok, $r) = $yaz(base64_encode($c['kullanici']), 334); if (!$ok) return $hata($r);
        list($ok, $r) = $yaz(base64_encode($c['sifre']), 235); if (!$ok) return $hata('Kimlik doğrulama başarısız: ' . $r);
    }
    list($ok, $r) = $yaz("MAIL FROM:<$gon>", 250); if (!$ok) return $hata($r);
    list($ok, $r) = $yaz("RCPT TO:<$kime>", [250, 251]); if (!$ok) return $hata($r);
    list($ok, $r) = $yaz('DATA', 354); if (!$ok) return $hata($r);
    $ham = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r", "\n"], "\n", $ham)); $ham = str_replace("\n", "\r\n", $ham);
    fwrite($fp, $ham . "\r\n.\r\n"); $r = $oku();
    if ($kod($r) !== 250) return $hata($r);
    @fwrite($fp, "QUIT\r\n"); @fclose($fp);
    return ['ok' => true, 'sonuc' => 'SMTP ile gönderildi'];
}

/* ---------------------------------------------------------------- Web Push (VAPID, yüksüz) ---------------------------------------------------------------- */
function ik_b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function ik_b64u_coz($s) { return base64_decode(strtr((string)$s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4)); }

/** @return array|null ['public'=>base64url (65 bayt), 'pem'=>özel anahtar] — yoksa ilk kullanımda üretilir */
function ik_vapid($db)
{
    static $c = null;
    if ($c !== null) return $c ?: null;
    $pem = ik_ayar($db, 'vapid_private', ''); $pub = ik_ayar($db, 'vapid_public', '');
    if ($pem === '' || $pub === '') {
        if (!function_exists('openssl_pkey_new')) { $c = false; return null; }
        $k = @openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$k) { $c = false; return null; }
        openssl_pkey_export($k, $pem);
        $d = openssl_pkey_get_details($k);
        $pub = ik_b64u("\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT));
        ik_ayar_yaz($db, 'vapid_private', $pem); ik_ayar_yaz($db, 'vapid_public', $pub);
    }
    return $c = ['public' => $pub, 'pem' => $pem];
}
/** ECDSA DER imzasını JWT için ham 64 bayta (R||S) çevirir */
function ik_der_ham($der)
{
    $o = 2; if (ord($der[1]) & 0x80) $o += ord($der[1]) & 0x7f;
    $rl = ord($der[$o + 1]); $r = substr($der, $o + 2, $rl); $o += 2 + $rl;
    $sl = ord($der[$o + 1]); $s = substr($der, $o + 2, $sl);
    return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
}
function ik_vapid_jwt($db, $aud)
{
    $v = ik_vapid($db); if (!$v) return null;
    $gonMail = ik_mail_gonderen($db)[0];
    $h = ik_b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $p = ik_b64u(json_encode(['aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => 'mailto:' . $gonMail]));
    if (!openssl_sign("$h.$p", $imza, $v['pem'], OPENSSL_ALGO_SHA256)) return null;
    return "$h.$p." . ik_b64u(ik_der_ham($imza));
}
/** Bir aboneye "uyandırma" itmesi gönderir. @return int HTTP kodu (201 = başarılı, 404/410 = abonelik geçersiz) */
function ik_push_abone($db, $a)
{
    $u = parse_url($a['endpoint']); if (empty($u['scheme']) || empty($u['host'])) return 0;
    $jwt = ik_vapid_jwt($db, $u['scheme'] . '://' . $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '')); $v = ik_vapid($db);
    if (!$jwt || !$v) return 0;
    list($kod) = ik_http('POST', $a['endpoint'], ['Authorization: vapid t=' . $jwt . ', k=' . $v['public'], 'TTL: 86400', 'Urgency: high', 'Content-Length: 0'], '', 8);
    return $kod;
}
function ik_push_kullanici($db, $kid)
{
    $kid = intval($kid);
    $q = $db->query("SELECT id, endpoint FROM ik_push_aboneler WHERE kullanici_id = $kid");
    if (!$q) return;
    while ($a = $q->fetch_assoc()) {
        $kod = ik_push_abone($db, $a);
        if ($kod === 404 || $kod === 410) $db->query("DELETE FROM ik_push_aboneler WHERE id = " . intval($a['id']));
        elseif ($kod >= 200 && $kod < 300) $db->query("UPDATE ik_push_aboneler SET son_basari = NOW() WHERE id = " . intval($a['id']));
    }
}


/* ---------------------------------------------------------------- Mobil uygulama (Android / iOS) — Firebase Cloud Messaging ---------------------------------------------------------------- */
/** Firebase servis hesabı: config/fcm_servis.json (Firebase → Proje ayarları → Hizmet hesapları → Yeni özel anahtar). Yoksa mobil push kapalıdır. */
function ik_fcm_ayar()
{
    static $a = false;
    if ($a !== false) return $a;
    $f = __DIR__ . '/config/fcm_servis.json';
    $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
    $a = (is_array($j) && !empty($j['private_key']) && !empty($j['client_email']) && !empty($j['project_id'])) ? $j : null;
    return $a;
}
function ik_fcm_erisim_anahtari()
{
    $a = ik_fcm_ayar(); if (!$a) return null;
    $onb = __DIR__ . '/storage/fcm_erisim.json';
    $o = is_file($onb) ? json_decode((string)@file_get_contents($onb), true) : null;
    if (is_array($o) && !empty($o['t']) && ($o['e'] ?? 0) > time() + 120) return $o['t'];
    $simdi = time();
    $h = ik_b64u(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
    $p = ik_b64u(json_encode(['iss' => $a['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $simdi, 'exp' => $simdi + 3600]));
    if (!openssl_sign("$h.$p", $imza, $a['private_key'], OPENSSL_ALGO_SHA256)) { $GLOBALS['ik_fcm_son'] = ['kod' => 0, 'govde' => 'Servis hesabı özel anahtarı okunamadı (fcm_servis.json bozuk olabilir)']; return null; }
    list($kod, $govde) = ik_http('POST', 'https://oauth2.googleapis.com/token', ['Content-Type: application/x-www-form-urlencoded'], http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => "$h.$p." . ik_b64u($imza)]), 10);
    $j = json_decode($govde, true);
    if ($kod !== 200 || empty($j['access_token'])) { $GLOBALS['ik_fcm_son'] = ['kod' => $kod, 'govde' => 'Google erişim anahtarı alınamadı: ' . mb_substr((string)$govde, 0, 300, 'UTF-8')]; return null; }
    @file_put_contents($onb, json_encode(['t' => $j['access_token'], 'e' => $simdi + intval($j['expires_in'] ?? 3600)]), LOCK_EX);
    return $j['access_token'];
}
/** Tek cihaza bildirim gönderir. @return int HTTP kodu (200 = başarılı, 404/400 UNREGISTERED = token geçersiz) */
function ik_fcm_gonder($tokenDeger, $baslik, $mesaj, $veri = [])
{
    $a = ik_fcm_ayar(); $t = ik_fcm_erisim_anahtari();
    if (!$a || !$t) return 0;
    $veri = array_map('strval', $veri);
    $ileti = ['message' => [
        'token' => $tokenDeger,
        'notification' => ['title' => $baslik, 'body' => $mesaj],
        'data' => $veri,
        'android' => ['priority' => 'HIGH', 'notification' => ['channel_id' => 'ik_bildirim_v2', 'sound' => 'default', 'icon' => 'ic_stat_ik']],
        'apns' => ['headers' => ['apns-priority' => '10'], 'payload' => ['aps' => ['sound' => 'default']]],
    ]];
    list($kod, $govde) = ik_http('POST', 'https://fcm.googleapis.com/v1/projects/' . rawurlencode($a['project_id']) . '/messages:send', ['Authorization: Bearer ' . $t, 'Content-Type: application/json; charset=utf-8'], json_encode($ileti, JSON_UNESCAPED_UNICODE), 10);
    $GLOBALS['ik_fcm_son'] = ['kod' => $kod, 'govde' => mb_substr((string)$govde, 0, 400, 'UTF-8')];
    if ($kod === 400 && stripos($govde, 'INVALID_ARGUMENT') !== false && stripos($govde, 'token') === false) return 400; // ileti hatası: token silinmesin
    return $kod;
}
/** Bir bildirim için kuyruk kaydını işler: her cihaza gönderir; geçici hatada artan beklemeyle yeniden dener (en çok 6 deneme).
 *  "gonderildi" = FCM sunucusu iletiyi KABUL etti; telefonda gerçekten görüntülendiği bu yolla doğrulanamaz. */
function ik_fcm_kuyruk_isle($db, $satir)
{
    $kid = intval($satir['kullanici_id']); $bid = intval($satir['bildirim_id']); $kid_q = intval($satir['id']);
    $b = $db->query("SELECT baslik, mesaj, link, tur FROM ik_bildirimler WHERE id = $bid AND kullanici_id = $kid");
    $b = $b ? $b->fetch_assoc() : null;
    if (!$b) { $db->query("UPDATE ik_fcm_kuyruk SET durum = 'iptal', son_hata = 'Bildirim bulunamadı' WHERE id = $kid_q"); return; }
    if (!ik_fcm_ayar()) { $db->query("UPDATE ik_fcm_kuyruk SET durum = 'hata', son_hata = 'config/fcm_servis.json yok veya geçersiz' WHERE id = $kid_q"); return; }
    $duz = trim(preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '</p>'], ' ', (string)$b['mesaj'])), ENT_QUOTES, 'UTF-8')));
    $duz = mb_substr($duz, 0, 220, 'UTF-8');
    $baslik = trim((string)$b['baslik']) !== '' ? (string)$b['baslik'] : 'İnsan Kaynakları';
    $veri = ['link' => (string)$b['link'], 'tur' => (string)$b['tur'], 'bildirim_id' => (string)$bid];
    $tamam = array_filter(array_map('intval', explode(',', (string)($satir['tamam'] ?? ''))));
    $gecici = false; $sonHata = '';
    $q = $db->query("SELECT id, token FROM ik_mobil_cihazlar WHERE kullanici_id = $kid");
    $cihazlar = []; if ($q) while ($c = $q->fetch_assoc()) $cihazlar[] = $c;
    foreach ($cihazlar as $c) {
        $cid = intval($c['id']); if (in_array($cid, $tamam, true)) continue;
        $GLOBALS['ik_fcm_son'] = null;
        $kod = ik_fcm_gonder($c['token'], $baslik, $duz, $veri);
        $ayr = mb_substr((string)($GLOBALS['ik_fcm_son']['govde'] ?? ''), 0, 400, 'UTF-8');
        if ($kod === 200) { $tamam[] = $cid; $sonuc = 'kabul'; $db->query("UPDATE ik_mobil_cihazlar SET son_basari = NOW() WHERE id = $cid"); }
        elseif ($kod === 404 || ($kod === 400 && stripos($ayr, 'token') !== false) || $kod === 410) { $sonuc = 'gecersiz'; $db->query("DELETE FROM ik_mobil_cihazlar WHERE id = $cid"); }
        elseif ($kod === 0 || $kod === 401 || $kod === 429 || $kod >= 500) { $sonuc = 'gecici'; $gecici = true; $sonHata = "HTTP $kod $ayr"; }
        else { $sonuc = 'hata'; $sonHata = "HTTP $kod $ayr"; }
        $st = $db->prepare("INSERT INTO ik_fcm_log (kullanici_id, cihaz_id, bildirim_id, http, sonuc, ayrinti) VALUES (?, ?, ?, ?, ?, ?)");
        $st->bind_param('iiiiss', $kid, $cid, $bid, $kod, $sonuc, $ayr); $st->execute();
        if ($kod === 401 || $kod === 0) @unlink(__DIR__ . '/storage/fcm_erisim.json'); // eski erişim anahtarı atılsın
    }
    $tm = $db->real_escape_string(implode(',', $tamam)); $he = $db->real_escape_string(mb_substr($sonHata, 0, 400, 'UTF-8'));
    $deneme = intval($satir['deneme']) + 1;
    if ($gecici && $deneme < 6) {
        $bekle = [1, 5, 15, 60, 180][$deneme - 1] ?? 180;
        $db->query("UPDATE ik_fcm_kuyruk SET durum = 'bekliyor', deneme = $deneme, sonraki = DATE_ADD(NOW(), INTERVAL $bekle MINUTE), tamam = '$tm', son_hata = '$he' WHERE id = $kid_q");
    } elseif ($sonHata !== '') {
        $db->query("UPDATE ik_fcm_kuyruk SET durum = 'hata', deneme = $deneme, tamam = '$tm', son_hata = '$he' WHERE id = $kid_q");
    } else {
        $db->query("UPDATE ik_fcm_kuyruk SET durum = 'gonderildi', deneme = $deneme, tamam = '$tm', son_hata = '', gonderim = NOW() WHERE id = $kid_q");
    }
}
/** Vakti gelmiş kuyruk kayıtlarını işler (istek sonunda ve cron'dan çağrılır). Satır bir kez "isleniyor"a alınır: iki süreç aynı kaydı göndermez. */
function ik_fcm_kuyruk_calistir($db, $sinir = 5)
{
    if (!ik_fcm_ayar()) return 0;
    $db->query("UPDATE ik_fcm_kuyruk SET durum = 'bekliyor' WHERE durum = 'isleniyor' AND sonraki < DATE_SUB(NOW(), INTERVAL 10 MINUTE)");
    $q = $db->query("SELECT * FROM ik_fcm_kuyruk WHERE durum = 'bekliyor' AND sonraki <= NOW() ORDER BY id ASC LIMIT " . intval($sinir));
    $n = 0; $liste = []; if ($q) while ($r = $q->fetch_assoc()) $liste[] = $r;
    foreach ($liste as $r) {
        $db->query("UPDATE ik_fcm_kuyruk SET durum = 'isleniyor', sonraki = NOW() WHERE id = " . intval($r['id']) . " AND durum = 'bekliyor'");
        if ($db->etkilenen() < 1) continue;
        try { ik_fcm_kuyruk_isle($db, $r); $n++; } catch (Throwable $e) { $db->query("UPDATE ik_fcm_kuyruk SET durum = 'bekliyor', son_hata = 'Çalışma hatası' WHERE id = " . intval($r['id'])); }
    }
    return $n;
}

/* ---------------------------------------------------------------- Site içi kuyruk ---------------------------------------------------------------- */
/** Kullanıcıya site içi bildirim yazar (sayfa açıksa anlık toast + ses; kapalıysa Web Push) */
function ik_kuyruga_ekle($db, $kid, $baslik, $mesajHtml, $link = '', $tur = '')
{
    $kid = intval($kid);
    if ($kid <= 0) return 0;
    ik_kanal_sema($db);
    $st = $db->prepare("INSERT INTO ik_bildirimler (kullanici_id, baslik, mesaj, link, tur) VALUES (?, ?, ?, ?, ?)");
    $baslik = mb_substr((string)$baslik, 0, 200, 'UTF-8'); $link = mb_substr((string)$link, 0, 300, 'UTF-8'); $tur = mb_substr((string)$tur, 0, 40, 'UTF-8'); $mesajHtml = (string)$mesajHtml;
    $st->bind_param('issss', $kid, $baslik, $mesajHtml, $link, $tur);
    if (!$st->execute()) return 0;
    $id = intval($db->son_id());
    $var = $db->query("SELECT 1 FROM ik_push_aboneler WHERE kullanici_id = $kid LIMIT 1");
    if ($var && $var->num_rows) ik_sonra(function () use ($db, $kid) { ik_push_kullanici($db, $kid); });
    $mob = $db->query("SELECT 1 FROM ik_mobil_cihazlar WHERE kullanici_id = $kid LIMIT 1");
    if ($mob && $mob->num_rows) {
        // Kalıcı kuyruk: bildirim başına tek kayıt (UNIQUE) → aynı olaydan mükerrer push oluşmaz
        $db->query("INSERT IGNORE INTO ik_fcm_kuyruk (bildirim_id, kullanici_id) VALUES ($id, $kid)");
        ik_sonra(function () use ($db) { ik_fcm_kuyruk_calistir($db, 5); });
    }
    return $id;
}

} // IK_KANAL
