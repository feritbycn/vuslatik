<?php
// ============================================================
// platform_vekil.php — Uzak (vekil) sunucu desteği
//   Bir kurum başka bir hostingde yayınlanabilir; orada yalnızca vekil dosyası (index.php) + anahtar dosyası (vekil.anahtar.php) bulunur.
//   Veri tabanı, veri ve uygulama kodu bu (ana) sunucuda kalır. Vekil her isteği HMAC ile imzalayıp buraya iletir.
//   Dosya silinir/değiştirilir ya da anahtar başka bir alan adında kullanılırsa istek reddedilir ve platform panelinde uyarı çıkar.
// ============================================================

function pf_vekil_sema($db)
{
    static $y = false; if ($y) return; $y = true;
    $o = $db->kurum; $db->kurum = 0;
    if (@$db->query("SELECT nonce FROM pf_vekil_nonce LIMIT 0")) { $db->kurum = $o; return; }
    $db->query("CREATE TABLE IF NOT EXISTS pf_vekil (
        kurum_id INT NOT NULL, durum VARCHAR(10) NOT NULL DEFAULT 'aktif', secret CHAR(64) NOT NULL, vekil_hash CHAR(64) NOT NULL DEFAULT '', anahtar_hash CHAR(64) NOT NULL DEFAULT '',
        ana VARCHAR(255) NOT NULL DEFAULT '', anahtar_zaman VARCHAR(25) NOT NULL DEFAULT '', host VARCHAR(190) NOT NULL DEFAULT '', bekleyen_host VARCHAR(190) NOT NULL DEFAULT '', bekleyen_ip VARCHAR(45) NOT NULL DEFAULT '',
        son_istek DATETIME NULL, son_ip VARCHAR(45) NOT NULL DEFAULT '', son_host VARCHAR(190) NOT NULL DEFAULT '',
        uyari VARCHAR(20) NOT NULL DEFAULT '', uyari_zaman DATETIME NULL, uyari_ayrinti VARCHAR(300) NOT NULL DEFAULT '', olusturma DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (kurum_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS pf_vekil_olay (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, kurum_id INT NOT NULL, olay VARCHAR(20) NOT NULL, ayrinti VARCHAR(300) NOT NULL DEFAULT '', ip VARCHAR(45) NOT NULL DEFAULT '',
        zaman DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, PRIMARY KEY (id), KEY ix_k (kurum_id, id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $db->query("CREATE TABLE IF NOT EXISTS pf_vekil_nonce (nonce CHAR(24) NOT NULL, zaman INT NOT NULL, PRIMARY KEY (nonce), KEY ix_z (zaman)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->kurum = $o;
}
define('PF_VEKIL_OLAY_AD', [
    'olustu' => 'Bağlantı dosyası oluşturuldu', 'yenilendi' => 'Anahtar yenilendi', 'kapatildi' => 'Uzak sunucu bağlantısı kapatıldı', 'baglandi' => 'İlk bağlantı kuruldu',
    'host' => 'Başka sitede kullanıldı', 'imza' => 'Anahtar dosyası geçersiz / değiştirilmiş', 'dosya' => 'Vekil dosyası değiştirilmiş', 'anahtar_yok' => 'Anahtar dosyası silinmiş',
    'kod_degisti' => 'Vekil dosyası değiştirilmiş', 'onay' => 'Alan adı onaylandı', 'eski' => 'Eski/tekrar edilen istek',
]);
function pf_vekil_ip($ip) { return filter_var($ip, FILTER_VALIDATE_IP) ? (string)$ip : ''; }
function pf_vekil_host_norm($h) { return strtolower(preg_replace('/:\d+$/', '', trim((string)$h))); }

function pf_vekil_al($db, $kid)
{
    pf_vekil_sema($db); $kid = intval($kid); $o = $db->kurum; $db->kurum = 0;
    $q = $db->query("SELECT * FROM pf_vekil WHERE kurum_id = $kid"); $r = $q ? $q->fetch_assoc() : null; $db->kurum = $o;
    return $r ?: null;
}
function pf_vekil_olay($db, $kid, $olay, $ayrinti = '', $ip = '')
{
    pf_vekil_sema($db); $o = $db->kurum; $db->kurum = 0;
    $st = $db->prepare("INSERT INTO pf_vekil_olay (kurum_id, olay, ayrinti, ip) VALUES (?, ?, ?, ?)");
    if ($st) { $kid = intval($kid); $a = mb_substr((string)$ayrinti, 0, 300, 'UTF-8'); $st->bind_param('isss', $kid, $olay, $a, $ip); $st->execute(); }
    $db->kurum = $o;
}
function pf_vekil_uyari($db, $kid, $tur, $ayrinti = '', $ip = '', $bekleyenHost = '')
{
    $kid = intval($kid); $o = $db->kurum; $db->kurum = 0;
    $st = $db->prepare("UPDATE pf_vekil SET uyari = ?, uyari_zaman = NOW(), uyari_ayrinti = ?, bekleyen_host = IF(? <> '', ?, bekleyen_host), bekleyen_ip = IF(? <> '', ?, bekleyen_ip) WHERE kurum_id = ?");
    if ($st) { $a = mb_substr((string)$ayrinti, 0, 300, 'UTF-8'); $st->bind_param('ssssssi', $tur, $a, $bekleyenHost, $bekleyenHost, $ip, $ip, $kid); $st->execute(); }
    // aynı uyarı art arda yazılmasın (5 dakikada bir kayıt)
    $q = $db->query("SELECT COUNT(*) FROM pf_vekil_olay WHERE kurum_id = $kid AND olay = '" . $db->real_escape_string($tur) . "' AND zaman > (NOW() - INTERVAL 5 MINUTE)");
    if ($q && intval($q->fetch_row()[0]) === 0) {
        pf_vekil_olay($db, $kid, $tur, $ayrinti, $ip);
        if (function_exists('pf_uyari_bildir') && $tur !== 'eski') { $kq = $db->query("SELECT ad FROM pf_kurumlar WHERE id = $kid"); $kr = $kq ? $kq->fetch_row() : null; pf_uyari_bildir($db, 'Kurum sitesi durduruldu: ' . ($kr[0] ?? ('#' . $kid)), '“' . ($kr[0] ?? ('#' . $kid)) . '” kurumunun sitesi durduruldu.' . "\n" . $ayrinti . ($bekleyenHost !== '' ? "\nTaşınan adres: " . $bekleyenHost : '') . ($ip !== '' ? "\nIP: " . $ip : '') . "\n\nPlatform → Kurumlar sayfasından onaylayabilir ya da engelleyebilirsiniz."); }
    }
    $db->kurum = $o;
}

/** Vekil dosyalarını üretir; yeni gizli anahtar oluşturur ve beklenen dosya özetlerini kaydeder. Dönüş: ['index.php'=>..., 'vekil.anahtar.php'=>..., ...] */
function pf_vekil_dosyalar($db, $k, $anaUrl, $yenile)
{
    pf_vekil_sema($db); $kid = intval($k['id']);
    $v = pf_vekil_al($db, $kid);
    $yeniKayit = !$v || $yenile;
    if (!$yeniKayit && $v['ana'] !== '') $anaUrl = $v['ana'];            // aynı paket her indirmede birebir aynı kalsın (dosya özetleri değişmesin)
    $sab = file_get_contents(__DIR__ . '/vekil/index.php.sablon');
    $index = str_replace(['__KOD__', '__ANA__'], [addslashes($k['kod']), addslashes(rtrim($anaUrl, '/'))], $sab);
    $vh = hash('sha256', $index);
    $secret = $yeniKayit ? bin2hex(random_bytes(32)) : $v['secret'];
    $zaman = $yeniKayit ? date('Y-m-d H:i:s') : ($v['anahtar_zaman'] !== '' ? $v['anahtar_zaman'] : date('Y-m-d H:i:s'));
    $anahtar = "<?php\n// Bu dosya ana sunucuya bağlantı anahtarıdır. SİLMEYİN, DEĞİŞTİRMEYİN, BAŞKA BİR SİTEDE KULLANMAYIN.\nreturn " . var_export(['kod' => $k['kod'], 'secret' => $secret, 'vh' => $vh, 'olusturma' => $zaman], true) . ";\n";
    $ah = hash('sha256', $anahtar);
    $o = $db->kurum; $db->kurum = 0;
    $ana = rtrim($anaUrl, '/');
    if (!$v) { $st = $db->prepare("INSERT INTO pf_vekil (kurum_id, durum, secret, vekil_hash, anahtar_hash, ana, anahtar_zaman) VALUES (?, 'aktif', ?, ?, ?, ?, ?)"); $st->bind_param('isssss', $kid, $secret, $vh, $ah, $ana, $zaman); $st->execute(); }
    else { $st = $db->prepare("UPDATE pf_vekil SET durum = 'aktif', secret = ?, vekil_hash = ?, anahtar_hash = ?, ana = ?, anahtar_zaman = ?, uyari = '', uyari_ayrinti = '', bekleyen_host = '', bekleyen_ip = '' WHERE kurum_id = ?"); $st->bind_param('sssssi', $secret, $vh, $ah, $ana, $zaman, $kid); $st->execute(); }
    $db->kurum = $o;
    $ht = "# Tüm istekler vekil dosyasına yönlendirilir\n<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteCond %{REQUEST_URI} !/index\\.php$\nRewriteRule ^ index.php [L,QSA]\n</IfModule>\n<FilesMatch \"^vekil\\.anahtar\\.php$\">\nRequire all denied\n</FilesMatch>\n";
    $oku = "TURKUAZ BİLEŞİM — VEKİL SUNUCU DOSYALARI (" . $k['ad'] . ")\n\nBu klasörde yalnızca 3 dosya olur: index.php, vekil.anahtar.php, .htaccess\n- Bu sunucuda veri, veri tabanı ya da uygulama kodu tutulmaz; her şey ana sunucuda kalır.\n- Dosyaları alan adının köküne (public_html) yükleyin. PHP 7+ ve curl eklentisi gerekir.\n- Nginx kullanıyorsanız tüm istekleri index.php'ye yönlendirin (try_files $uri /index.php?$query_string;) ve vekil.anahtar.php'ye dış erişimi kapatın.\n- index.php ya da vekil.anahtar.php silinir/değiştirilirse site açılmaz ve ana sunucu yöneticisine bildirilir.\n- Aynı dosyalar başka bir alan adında kullanılırsa ana sunucu yöneticisinin onayı gerekir.\n";
    return ['index.php' => $index, 'vekil.anahtar.php' => $anahtar, '.htaccess' => $ht, 'OKU_BENI.txt' => $oku];
}

function pf_vekil_ham_baslik($ad) { return $_SERVER['HTTP_' . strtoupper(str_replace('-', '_', $ad))] ?? ''; }

/**
 * Gelen istek vekilden mi? İmza yoksa null; varsa doğrular. Başarısızlıkta 403 sayfası verip çıkar.
 * Başarıda: REMOTE_ADDR / HTTP_HOST / HTTPS düzeltilir ve kurum kimliği döner.
 */
function pf_vekil_dogrula($db)
{
    $imza = pf_vekil_ham_baslik('X-TB-Imza'); if ($imza === '') return null;
    $kod = strtolower(pf_vekil_ham_baslik('X-TB-Kod'));
    $ret = function ($metin, $kod403 = 403) { http_response_code($kod403); header('Content-Type: text/html; charset=utf-8'); header('Cache-Control: no-store');
        echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bağlantı reddedildi</title><body style="font-family:system-ui,Segoe UI,Arial;background:#f4f6fb;color:#1e293b;display:grid;place-items:center;min-height:100vh;margin:0"><div style="max-width:440px;margin:20px;padding:28px;background:#fff;border-radius:18px;box-shadow:0 10px 40px rgba(0,0,0,.08)"><h2 style="margin:0 0 8px">Bağlantı reddedildi</h2><p style="margin:0;color:#64748b;line-height:1.55">' . htmlspecialchars($metin) . '</p></div></body>'; exit; };
    pf_vekil_sema($db);
    $kurum = pf_kurum_kod($db, $kod); $v = $kurum ? pf_vekil_al($db, $kurum['id']) : null;
    if (!$kurum || !$v) $ret('Bu bağlantı dosyası geçerli değil.');
    $kid = intval($kurum['id']); $ip = pf_vekil_ip(pf_vekil_ham_baslik('X-TB-IP')); $host = pf_vekil_host_norm(pf_vekil_ham_baslik('X-TB-Host'));
    if ($v['durum'] !== 'aktif') $ret('Bu kurumun uzak sunucu bağlantısı kapatılmış.');
    $zaman = pf_vekil_ham_baslik('X-TB-Zaman'); $nonce = pf_vekil_ham_baslik('X-TB-Nonce'); $vh = pf_vekil_ham_baslik('X-TB-VH'); $ah = pf_vekil_ham_baslik('X-TB-AH');
    $yolK = pf_vekil_ham_baslik('X-TB-Yol');
    $beklenen = hash_hmac('sha256', implode("\n", [$kod, $zaman, $nonce, $host, pf_vekil_ham_baslik('X-TB-IP'), $vh, $ah, strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')), $yolK]), $v['secret']);
    if (!hash_equals($beklenen, $imza)) { pf_vekil_uyari($db, $kid, 'imza', 'İmza doğrulanamadı: anahtar dosyası değiştirilmiş, eski ya da başka bir kurumun anahtarı olabilir.', $ip, ''); $ret('Bağlantı anahtarı doğrulanamadı. Anahtar dosyası değiştirilmiş veya geçersiz.'); }
    if (abs(time() - intval($zaman)) > 300) { pf_vekil_uyari($db, $kid, 'eski', 'Saat farkı: vekil sunucunun saati ana sunucudan 5 dakikadan fazla farklı.', $ip); $ret('Vekil sunucunun saati doğru değil (5 dakikadan fazla fark).'); }
    if (!preg_match('/^[a-f0-9]{24}$/', $nonce)) $ret('Geçersiz istek.');
    $o = $db->kurum; $db->kurum = 0;
    $db->query("DELETE FROM pf_vekil_nonce WHERE zaman < " . (time() - 600));
    $st = $db->prepare("INSERT IGNORE INTO pf_vekil_nonce (nonce, zaman) VALUES (?, ?)"); $t = time(); $st->bind_param('si', $nonce, $t); $st->execute(); $yeni = $st->affected_rows > 0;
    $db->kurum = $o;
    if (!$yeni) $ret('Tekrarlanan istek reddedildi.');
    if (!hash_equals($v['vekil_hash'], $vh)) { pf_vekil_uyari($db, $kid, 'dosya', 'Vekil dosyası (index.php) özgün haliyle uyuşmuyor; değiştirilmiş.', $ip); $ret('Vekil dosyası değiştirilmiş. Orijinal dosyayı yeniden yükleyin.'); }
    if (!hash_equals($v['anahtar_hash'], $ah)) { pf_vekil_uyari($db, $kid, 'imza', 'Anahtar dosyasının içeriği özgün haliyle uyuşmuyor; değiştirilmiş.', $ip); $ret('Anahtar dosyası değiştirilmiş.'); }
    if ($host === '') $ret('Alan adı belirlenemedi.');
    if ($v['host'] === '') {                    // ilk kullanım: alan adı bu anahtara bağlanır
        $db->kurum = 0; $st = $db->prepare("UPDATE pf_vekil SET host = ? WHERE kurum_id = ? AND host = ''"); $st->bind_param('si', $host, $kid); $st->execute(); $db->kurum = $o;
        pf_vekil_olay($db, $kid, 'baglandi', $host, $ip); $v['host'] = $host;
    }
    if (!in_array($host, array_filter(array_map('trim', explode(',', (string)$v['host']))), true)) { pf_vekil_uyari($db, $kid, 'host', 'Bu bağlantı dosyası "' . $host . '" adresinde kullanılmaya çalışıldı (kayıtlı: ' . $v['host'] . ').', $ip, $host); $ret('Bu bağlantı dosyası başka bir sitede kullanılıyor. Kullanım için ana sunucu yöneticisinin onayı gerekir.'); }
    // doğrulandı
    $db->kurum = 0;
    $st = $db->prepare("UPDATE pf_vekil SET son_istek = NOW(), son_ip = ?, son_host = ?, uyari = IF(uyari = 'host', uyari, ''), uyari_ayrinti = IF(uyari = 'host', uyari_ayrinti, '') WHERE kurum_id = ? AND (son_istek IS NULL OR son_istek < (NOW() - INTERVAL 20 SECOND) OR uyari NOT IN ('', 'host'))");
    $st->bind_param('ssi', $ip, $host, $kid); $st->execute(); $db->kurum = $o;
    if ($ip !== '') $_SERVER['REMOTE_ADDR'] = $ip;
    $_SERVER['HTTP_HOST'] = $host;
    $_SERVER['HTTPS'] = pf_vekil_ham_baslik('X-Forwarded-Proto') === 'https' ? 'on' : 'off';
    $taban = pf_vekil_ham_baslik('X-TB-Taban'); if (!preg_match('#^(/[A-Za-z0-9._~-]+)*$#', $taban)) $taban = '';   // vekil alt klasördeyse (ör. /ik) bağlantılar o klasörle üretilir
    $GLOBALS['TB_VEKIL_BASE'] = (pf_vekil_ham_baslik('X-Forwarded-Proto') === 'https' ? 'https://' : 'http://') . $host . $taban . '/';
    return $kid;
}

/** Kurumun uzak sunucuda çalışıp çalışmadığı (giriş kısıtı için) */
function pf_vekil_aktif_mi($db, $kid) { $v = pf_vekil_al($db, $kid); return $v && $v['durum'] === 'aktif'; }

/** Panel için özet */
function pf_vekil_ozet($db, $kid)
{
    $v = pf_vekil_al($db, $kid); if (!$v) return ['tur' => 'yerel'];
    $vd = function_exists('pf_vdb_al') ? pf_vdb_al($db, $kid) : null;
    $oto = ['vdb' => (bool)$vd, 'oto_dk' => intval($vd['oto_dk'] ?? 0), 'oto_gun' => (string)($vd['oto_gun'] ?? ''), 'oto_son' => $vd['oto_son'] ?? null, 'oto_sonuc' => (string)($vd['oto_sonuc'] ?? '')];
    return $oto + ['tur' => $v['durum'] === 'aktif' ? 'uzak' : 'yerel', 'durum' => $v['durum'], 'host' => $v['host'], 'bekleyen_host' => $v['bekleyen_host'], 'bekleyen_ip' => $v['bekleyen_ip'], 'son_istek' => $v['son_istek'],
        'son_ip' => $v['son_ip'], 'uyari' => $v['uyari'], 'uyari_zaman' => $v['uyari_zaman'], 'uyari_ayrinti' => $v['uyari_ayrinti'], 'olusturma' => $v['olusturma']];
}

/** Tablo yoksa sema oluşturmadan hızlı kontrol: bu kurum uzak (vekil) sunucuda mı çalışıyor? */
function pf_vekil_uzak_mi($db, $kid)
{
    $kid = intval($kid); if ($kid <= 0) return false; $o = $db->kurum; $db->kurum = 0;
    $q = @$db->query("SELECT host FROM pf_vekil WHERE kurum_id = $kid AND durum = 'aktif'"); $r = $q ? $q->fetch_row() : null; $db->kurum = $o;
    return $r ? (string)$r[0] : false;
}


// ============================================================
// Uzak veri tabanı: kurumun tabloları (k{id}_…) vekil sunucudaki MySQL'e taşınır. Dosyalar (uploads/) taşınmaz, ana sunucuda kalır.
// ============================================================
function pf_vdb_sema($db)
{
    static $y = false; if ($y) return; $y = true;
    $o = $db->kurum; $db->kurum = 0;
    $db->query("CREATE TABLE IF NOT EXISTS pf_vekil_db (
        kurum_id INT NOT NULL, host VARCHAR(190) NOT NULL DEFAULT '', port INT NOT NULL DEFAULT 3306, ad VARCHAR(100) NOT NULL DEFAULT '', kul VARCHAR(100) NOT NULL DEFAULT '', sifre TEXT NULL,
        aktif TINYINT(1) NOT NULL DEFAULT 0, son_test DATETIME NULL, son_sonuc VARCHAR(300) NOT NULL DEFAULT '', tasima DATETIME NULL, tasima_ozet VARCHAR(300) NOT NULL DEFAULT '', PRIMARY KEY (kurum_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    foreach (['oto_saat' => 'INT NOT NULL DEFAULT 0', 'oto_son' => 'DATETIME NULL', 'oto_sonuc' => "VARCHAR(300) NOT NULL DEFAULT ''", 'oto_dk' => 'INT NOT NULL DEFAULT 0', 'oto_gun' => "VARCHAR(5) NOT NULL DEFAULT ''"] as $kol => $tn) {
        $q = $db->query("SHOW COLUMNS FROM pf_vekil_db LIKE '$kol'"); if ($q && $q->num_rows === 0) $db->query("ALTER TABLE pf_vekil_db ADD COLUMN $kol $tn");
    }
    $db->query("UPDATE pf_vekil_db SET oto_dk = oto_saat * 60, oto_saat = 0 WHERE oto_saat > 0 AND oto_dk = 0");   // eski saatlik ayar → dakika
    $db->kurum = $o;
}
function pf_vdb_al($db, $kid)
{
    pf_vdb_sema($db); $kid = intval($kid); $o = $db->kurum; $db->kurum = 0;
    $q = $db->query("SELECT * FROM pf_vekil_db WHERE kurum_id = $kid"); $r = $q ? $q->fetch_assoc() : null; $db->kurum = $o;
    return $r ?: null;
}
/** Panel için özet (parola asla dönmez) */
function pf_vdb_ozet($db, $kid)
{
    $r = pf_vdb_al($db, $kid); if (!$r) return ['var' => false, 'aktif' => false];
    return ['var' => true, 'aktif' => intval($r['aktif']) === 1, 'host' => $r['host'], 'port' => intval($r['port']), 'ad' => $r['ad'], 'kul' => $r['kul'], 'sifre_var' => $r['sifre'] !== null && $r['sifre'] !== '',
        'son_test' => $r['son_test'], 'son_sonuc' => $r['son_sonuc'], 'tasima' => $r['tasima'], 'tasima_ozet' => $r['tasima_ozet'],
        'oto_dk' => intval($r['oto_dk'] ?? 0), 'oto_gun' => (string)($r['oto_gun'] ?? ''), 'oto_son' => $r['oto_son'] ?? null, 'oto_sonuc' => $r['oto_sonuc'] ?? ''];
}
/** Ana veri tabanına ham (ön ek yeniden yazmayan, yönlendirmeyen) bağlantı */
function pf_vdb_ana_baglanti()
{
    $d = $GLOBALS['TB_DB'] ?? [];
    return VDB::uzak_ac($d['host'] ?? 'localhost', $d['port'] ?? 3306, $d['veritabani'] ?? '', $d['kullanici'] ?? '', $d['sifre'] ?? '');
}
function pf_vdb_tablolar($m, $kid)
{
    $l = []; $q = $m->query("SHOW TABLES LIKE 'k" . intval($kid) . "\\_%'"); if ($q) while ($r = $q->fetch_row()) $l[] = $r[0];
    return $l;
}
/** Tabloları $kaynak → $hedef kopyalar (hedefte aynı adlı tablolar önce silinir). Dönüş: [tablo sayısı, satır sayısı] ya da hata metni */
function pf_vdb_kopyala($kaynak, $hedef, $kid)
{
    @set_time_limit(0);
    $tablolar = pf_vdb_tablolar($kaynak, $kid); if (!$tablolar) return 'Kaynakta bu kuruma ait tablo bulunamadı.';
    $hedef->query("SET FOREIGN_KEY_CHECKS = 0"); $hedef->query("SET SESSION sql_mode = ''"); $toplam = 0;
    foreach ($tablolar as $t) {
        $q = $kaynak->query("SHOW CREATE TABLE `$t`"); $r = $q ? $q->fetch_row() : null; if (!$r) return "Tablo yapısı okunamadı: $t";
        if (!$hedef->query("DROP TABLE IF EXISTS `$t`") || !$hedef->query($r[1])) return "Hedefte tablo oluşturulamadı ($t): " . $hedef->error;
        $q = $kaynak->query("SELECT * FROM `$t`", MYSQLI_USE_RESULT); if (!$q) return "Veri okunamadı ($t): " . $kaynak->error;
        $paket = ''; $say = 0;
        $yaz = function () use (&$paket, $hedef, $t) { if ($paket === '') return true; $ok = $hedef->query("INSERT INTO `$t` VALUES " . $paket); $paket = ''; return $ok; };
        while ($s = $q->fetch_row()) {
            $v = []; foreach ($s as $x) $v[] = $x === null ? 'NULL' : "'" . $hedef->real_escape_string($x) . "'";
            $paket .= ($paket === '' ? '' : ',') . '(' . implode(',', $v) . ')'; $say++;
            if (strlen($paket) > 400000 && !$yaz()) return "Veri yazılamadı ($t): " . $hedef->error;
        }
        $q->free(); if (!$yaz()) return "Veri yazılamadı ($t): " . $hedef->error;
        $c = $hedef->query("SELECT COUNT(*) FROM `$t`")->fetch_row()[0]; if (intval($c) !== $say) return "Satır sayısı uyuşmadı ($t): $say ≠ $c";
        $toplam += $say;
    }
    $hedef->query("SET FOREIGN_KEY_CHECKS = 1");
    return [count($tablolar), $toplam];
}

/** Kullanılan veri tabanının DİĞER taraftaki kopyasını günceller (yönlendirme değişmez) → [true, özet] ya da [false, hata] */
function pf_vdb_yedekle($db, $kid, $d)
{
    $m = VDB::uzak_ac($d['host'], $d['port'], $d['ad'], $d['kul'], VDB::uzak_coz($d['sifre'])); if (is_string($m)) return [false, $m];
    $ana = pf_vdb_ana_baglanti(); if (is_string($ana)) return [false, $ana];
    try {
        if (intval($d['aktif']) === 1) { $r = pf_vdb_kopyala($m, $ana, $kid); $yon = 'vekil MySQL → ana sunucu'; }
        else { $r = pf_vdb_kopyala($ana, $m, $kid); $yon = 'ana sunucu → vekil MySQL'; }
    } catch (\Throwable $e) { return [false, $e->getMessage()]; }
    if (is_string($r)) return [false, $r];
    return [true, 'Yedek (' . $yon . '): ' . $r[0] . ' tablo, ' . $r[1] . ' satır'];
}
/** Zamanı gelmiş otomatik yedek koşulu: oto_dk dakikada bir  YA DA  oto_gun (SS:DD) saatinde günde bir */
const PF_VDB_VADE = "((oto_dk > 0 AND (oto_son IS NULL OR oto_son < (NOW() - INTERVAL oto_dk MINUTE)))
        OR (oto_dk = 0 AND oto_gun <> '' AND CURTIME() >= CONCAT(oto_gun, ':00') AND (oto_son IS NULL OR oto_son < TIMESTAMP(CURDATE(), CONCAT(oto_gun, ':00')))))";
/** Zamanı gelen otomatik yedekleri çalıştırır; panel/kurum istekleriyle ya da cron ile tetiklenir */
function pf_vdb_oto($db, $zorla = false)
{
    pf_vdb_sema($db); $o = $db->kurum; $db->kurum = 0; $say = 0;
    $q = $db->query("SELECT kurum_id FROM pf_vekil_db WHERE " . PF_VDB_VADE); $idler = [];
    if ($q) while ($r = $q->fetch_row()) $idler[] = intval($r[0]);
    foreach ($idler as $kid) {
        $kilit = $db->query("SELECT GET_LOCK('tb_vdb_oto_$kid', 0)"); if (!$kilit || intval($kilit->fetch_row()[0]) !== 1) continue;
        @set_time_limit(0); $db->kurum = 0;
        $db->query("UPDATE pf_vekil_db SET oto_son = NOW() WHERE kurum_id = $kid");            // aynı anda ikinci tetiklemeyi önler; hata olsa da bir sonraki aralıkta denenir
        $d = pf_vdb_al($db, $kid); $db->kurum = 0;
        if ($d) { $r = pf_vdb_yedekle($db, $kid, $d); $sn = mb_substr(($r[0] ? 'Başarılı — ' : 'Hata — ') . $r[1], 0, 300, 'UTF-8'); $st = $db->prepare("UPDATE pf_vekil_db SET oto_sonuc = ? WHERE kurum_id = ?"); $st->bind_param('si', $sn, $kid); $st->execute(); $say += $r[0] ? 1 : 0; }
        $db->query("SELECT RELEASE_LOCK('tb_vdb_oto_$kid')");
    }
    $db->kurum = $o; return $say;
}
function pf_vdb_oto_arkada($db)
{
    static $y = false; if ($y) return; $y = true;
    $kf = TB_KOK . '/storage/.vdb_kontrol';                                   // en çok 20 sn'de bir bak (bildirim yoklaması her ~20 sn tetikler)
    if (is_file($kf) && time() - filemtime($kf) < 20) return;
    @touch($kf);
    pf_vdb_sema($db); $o = $db->kurum; $db->kurum = 0;
    $q = $db->query("SELECT 1 FROM pf_vekil_db WHERE " . PF_VDB_VADE . " LIMIT 1"); $var = $q && $q->num_rows > 0; $db->kurum = $o;
    if (!$var) return;
    require_once __DIR__ . '/ik_kanal.php';
    ik_sonra(function () use ($db) { pf_vdb_oto($db); });
}
/* komut satırı (cron):  php platform_vekil.php */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    require_once __DIR__ . '/config/baglan.php';
    echo 'Yedeklenen kurum: ' . pf_vdb_oto($db) . "\n";
}
