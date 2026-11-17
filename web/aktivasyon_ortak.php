<?php
// ============================================================
//  aktivasyon_ortak.php — Aktivasyon kodlu kurum paketi
//
//  ANA SUNUCU (platform):  kurum "aktivasyon kodlu paket" olarak oluşturulur → tek kullanımlık kod + indirilebilir paket.
//  MÜŞTERİ SUNUCUSU:       paket kurulur; ilk açılışta kod istenir. Kod ana sunucuda doğrulanır ve YALNIZCA BİR KEZ çalışır.
//                          Sonrasında ≤6 saatte bir ana sunucuya doğrulama gider (dosyalar, adres, kurulum kimliği).
//                          Dosya değişirse / anahtar dosyası silinirse / klasör başka adrese kopyalanırsa kilitlenir.
//  Uç nokta: aktivasyon_bildir.php · Müşteri sayfası: aktivasyon.php
// ============================================================
if (defined('TB_AKT_ORTAK')) return;
define('TB_AKT_ORTAK', 1);
if (!defined('AKT_GECERLILIK')) define('AKT_GECERLILIK', 900);       // bir doğrulamanın geçerlilik süresi
define('AKT_TOLERANS', 72 * 3600);        // ana sunucuya ulaşılamazsa en fazla bu kadar süre son doğrulamaya güvenilir

function akt_kok() { return defined('TB_KOK') ? TB_KOK : __DIR__; }

/** Kod dosyalarının parmak izi (aynı fonksiyon iki tarafta da çalışır). Ayar/veri klasörleri ve surum.json hariçtir. */
function akt_manifest()
{
    $kok = akt_kok(); $satir = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kok, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $d) {
        if (!$d->isFile()) continue;
        $g = str_replace('\\', '/', substr($d->getPathname(), strlen($kok) + 1));
        if ($g !== 'config/baglan.php' && preg_match('~^(config|storage|uploads|cron|kurum_paketi)/~', $g)) continue;
        if (preg_match('~(^|/)\.(git|svn|idea)~', $g) || $g === 'surum.json') continue;
        if (!in_array(strtolower(pathinfo($g, PATHINFO_EXTENSION)), ['php', 'js', 'css', 'html'], true)) continue;
        $satir[$g] = $g . ':' . hash_file('sha256', $d->getPathname());
    }
    ksort($satir);
    return hash('sha256', implode("\n", $satir));
}
function akt_imza($anahtar, $olay, $zaman, $veri) { return hash_hmac('sha256', $olay . '|' . $zaman . '|' . $veri, (string)$anahtar); }
function akt_host_norm($h) { $h = strtolower(trim((string)$h)); $h = preg_replace('/:\d+$/', '', $h); return preg_replace('/^www\./', '', $h); }
function akt_http_post($url, array $alan, $sn = 6)
{
    $govde = http_build_query($alan);
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $govde, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $sn, CURLOPT_CONNECTTIMEOUT => min(4, $sn), CURLOPT_POSTREDIR => 7, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_USERAGENT => 'TurkuazAktivasyon/1.0']);
        $r = curl_exec($c); $k = intval(curl_getinfo($c, CURLINFO_HTTP_CODE)); curl_close($c);
        return $r === false ? [0, ''] : [$k, (string)$r];
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => $govde, 'timeout' => $sn, 'ignore_errors' => true]]);
    $r = @file_get_contents($url, false, $ctx); $k = 0;
    if (!empty($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) $k = intval($m[1]);
    return $r === false ? [0, ''] : [$k, (string)$r];
}

// ───────────────────────── MÜŞTERİ SUNUCUSU ─────────────────────────
/** pf_ayarlar'a doğrudan okuma/yazma (pf_ayar() yalnızca tanımlı anahtarları görür) */
function akt_ayar($db, $k) { $st = @$db->prepare("SELECT deger FROM pf_ayarlar WHERE anahtar = ?"); if (!$st) return ''; $st->bind_param('s', $k); $st->execute(); $r = $st->get_result(); $x = $r ? $r->fetch_row() : null; return $x ? (string)$x[0] : ''; }
function akt_ayar_yaz($db, $k, $v) { $st = $db->prepare("INSERT INTO pf_ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)"); $st->bind_param('ss', $k, $v); $st->execute(); }
function akt_anahtar_dosya() { return akt_kok() . '/config/aktivasyon.anahtar.php'; }
function akt_lisans_dosya() { return akt_kok() . '/storage/lisans.json'; }
/** ['aid'=>, 'ana'=>, 'anahtar'=>, 'pk'=>] ya da null */
function akt_anahtar()
{
    static $a = false; if ($a !== false) return $a;
    $f = akt_anahtar_dosya(); $v = is_file($f) ? @include $f : null;
    return $a = (is_array($v) && !empty($v['aid']) && !empty($v['anahtar']) && !empty($v['ana'])) ? $v : null;
}
/** Bu kurulum aktivasyon gerektiriyor mu? (anahtar dosyası YA DA veri tabanındaki işaret) — dosyayı silmek kilidi kaldırmaz */
function akt_zorunlu()
{
    static $z = null; if ($z !== null) return $z;
    if (is_file(akt_anahtar_dosya())) return $z = true;
    $db = $GLOBALS['db'] ?? null;
    if ($db && true) { try { return $z = (trim(akt_ayar($db, 'aktivasyon_id')) !== ''); } catch (Throwable $e) {} }
    return $z = false;
}
function akt_belirtec_dogrula($A, $jsonMetin, $imza, $ed)
{
    if ($ed !== '' && !empty($A['pk']) && function_exists('sodium_crypto_sign_verify_detached')) {
        $pk = @hex2bin((string)$A['pk']); $sg = @hex2bin((string)$ed);
        return $pk !== false && $sg !== false && strlen($pk) === 32 && strlen($sg) === 64 && sodium_crypto_sign_verify_detached($sg, $jsonMetin, $pk);
    }
    return hash_equals(hash_hmac('sha256', $jsonMetin, (string)$A['anahtar']), (string)$imza);
}
function akt_lisans_oku($A)
{
    $f = akt_lisans_dosya(); if (!is_file($f)) return null;
    $j = json_decode((string)@file_get_contents($f), true);
    if (!is_array($j) || empty($j['veri']) || !is_array($j['veri'])) return null;
    $metin = json_encode($j['veri'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $edGerek = !empty($A['pk']) && function_exists('sodium_crypto_sign_verify_detached') && ($j['veri']['durum'] ?? '') === 'aktif';   // ana sunucunun imzası şart: yerel HMAC ile sahte "aktif" yazılamaz
    if ($edGerek && empty($j['ed'])) return null;
    if (!akt_belirtec_dogrula($A, $metin, (string)($j['imza'] ?? ''), (string)($j['ed'] ?? ''))) return null;
    if (($j['veri']['aid'] ?? '') !== $A['aid']) return null;
    return $j['veri'];
}
function akt_lisans_yaz($A, array $veri, $imza, $ed)
{
    $d = dirname(akt_lisans_dosya()); if (!is_dir($d)) @mkdir($d, 0755, true);
    @file_put_contents(akt_lisans_dosya(), json_encode(['veri' => $veri, 'imza' => $imza, 'ed' => $ed], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}
/** Ana sunucuya imzalı istek: ['ok'=>bool,'ag'=>bool(ulaşılamadı),'mesaj'=>,'yanit'=>dizi] */
function akt_ana_istek($A, $olay, array $veri)
{
    $zaman = time(); $veri['nonce'] = bin2hex(random_bytes(8)); $js = json_encode($veri, JSON_UNESCAPED_UNICODE);
    list($k, $g) = akt_http_post(rtrim($A['ana'], '/') . '/aktivasyon_bildir.php', ['aid' => $A['aid'], 'olay' => $olay, 'zaman' => $zaman, 'veri' => $js, 'imza' => akt_imza($A['anahtar'], $olay, $zaman, $js)]);
    $y = json_decode($g, true);
    if (!is_array($y)) return ['ok' => false, 'ag' => true, 'mesaj' => 'Aktivasyon sunucusuna ulaşılamadı (adres: ' . $A['ana'] . ').', 'yanit' => []];
    $yj = (string)($y['yanit'] ?? ''); $yi = (string)($y['imza'] ?? '');
    if ($yj === '' || !hash_equals(hash_hmac('sha256', $yj . '|' . $veri['nonce'], (string)$A['anahtar']), $yi)) return ['ok' => false, 'ag' => true, 'mesaj' => 'Aktivasyon sunucusunun yanıtı doğrulanamadı.', 'yanit' => []];
    $r = json_decode($yj, true); if (!is_array($r)) return ['ok' => false, 'ag' => true, 'mesaj' => 'Geçersiz yanıt.', 'yanit' => []];
    return ['ok' => !empty($r['ok']), 'ag' => false, 'mesaj' => (string)($r['mesaj'] ?? ''), 'yanit' => $r, 'ed' => (string)($y['ed'] ?? '')];
}
function akt_kilitle($A, $neden, $host = '')
{
    $v = ['aid' => $A['aid'], 'durum' => 'kilit', 'neden' => $neden, 'host' => $host, 'zaman' => time()];
    akt_lisans_yaz($A, $v, hash_hmac('sha256', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string)$A['anahtar']), '');
}
function akt_belirtec_kaydet($A, array $r, $ed)
{
    $v = $r['belirtec']; $metin = json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $imza = hash_hmac('sha256', $metin, (string)$A['anahtar']);
    akt_lisans_yaz($A, $v, $imza, (string)($r['ed'] ?? $ed));
}
/** Kodu ana sunucuda etkinleştirir (YALNIZCA BİR KEZ). Dönüş: ['ok'=>bool,'mesaj'=>] */
function akt_etkinlestir($kod)
{
    $A = akt_anahtar(); if (!$A) return ['ok' => false, 'mesaj' => 'Aktivasyon anahtar dosyası (config/aktivasyon.anahtar.php) bulunamadı.'];
    $sid = bin2hex(random_bytes(20)); $host = akt_host_norm($_SERVER['HTTP_HOST'] ?? '');
    $s = akt_ana_istek($A, 'etkinlestir', ['kod' => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$kod)), 'sid' => $sid, 'host' => $host, 'manifest' => akt_manifest()]);
    if (!$s['ok']) return ['ok' => false, 'mesaj' => $s['mesaj'] ?: 'Aktivasyon başarısız.'];
    $s['yanit']['ed'] = $s['ed'] ?? ''; akt_belirtec_kaydet($A, $s['yanit'], $s['ed'] ?? '');
    return ['ok' => true, 'mesaj' => 'Aktivasyon tamamlandı.'];
}
/** Anlık durum: ['ok'=>bool, 'durum'=>yok|bekliyor|aktif|kilit|dosya, 'mesaj'=>] */
function akt_durum()
{
    static $d = null; if ($d !== null) return $d;
    if (!akt_zorunlu()) return $d = ['ok' => true, 'durum' => 'yok', 'mesaj' => ''];
    $A = akt_anahtar();
    if (!$A) return $d = ['ok' => false, 'durum' => 'dosya', 'mesaj' => 'Aktivasyon anahtar dosyası silinmiş ya da değiştirilmiş. Bu kurulum çalışmaz; lütfen sağlayıcınızla iletişime geçin.'];
    $L = akt_lisans_oku($A);
    if (!$L) return $d = ['ok' => false, 'durum' => 'bekliyor', 'mesaj' => 'Kurulumu başlatmak için aktivasyon kodunu girin.'];
    if (($L['durum'] ?? '') === 'kilit') return $d = ['ok' => false, 'durum' => 'kilit', 'mesaj' => (string)($L['neden'] ?? 'Lisans doğrulanamadı.')];
    $host = akt_host_norm($_SERVER['HTTP_HOST'] ?? '');
    if (PHP_SAPI !== 'cli' && $host !== '' && $host !== ($L['host'] ?? '')) return $d = ['ok' => false, 'durum' => 'kilit', 'mesaj' => 'Bu kurulum yalnızca etkinleştirildiği adreste çalışır (' . ($L['host'] ?? '?') . '). Başka adrese kopyalanamaz.'];
    $ask = akt_kok() . '/storage/.akt_askida'; $askVar = is_file($ask);                // ana sunucu "askıya alındı" dediyse: geri açılana kadar her ~60 sn'de sorulur
    if ($askVar && @filemtime($ask) > time() - 60) return $d = ['ok' => false, 'durum' => 'askida', 'mesaj' => (string)@file_get_contents($ask) ?: 'Bu kurum askıya alınmış.'];
    if (!$askVar && intval($L['exp'] ?? 0) > time()) return $d = ['ok' => true, 'durum' => 'aktif', 'mesaj' => ''];
    // doğrulama süresi doldu → ana sunucuya sor (aynı anda tek istek)
    $kilit = akt_kok() . '/storage/.akt_dene'; $dir = dirname($kilit); if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $fp = @fopen($kilit, 'c');
    if ($fp && !flock($fp, LOCK_EX | LOCK_NB)) { fclose($fp); return $d = ['ok' => (intval($L['son_ok'] ?? 0) + AKT_TOLERANS) > time(), 'durum' => 'aktif', 'mesaj' => 'Doğrulanıyor…']; }
    $son = $fp ? intval(trim((string)stream_get_contents($fp))) : 0;
    if ($son > time() - 60 && (intval($L['son_ok'] ?? 0) + AKT_TOLERANS) > time()) { if ($fp) { flock($fp, LOCK_UN); fclose($fp); } return $d = ['ok' => true, 'durum' => 'aktif', 'mesaj' => '']; }   // yakın zamanda denendi (ağ yok): toleransta kal
    $s = akt_ana_istek($A, 'dogrula', ['sid' => $L['sid'] ?? '', 'host' => $host, 'manifest' => akt_manifest()]);
    if ($fp) { ftruncate($fp, 0); rewind($fp); if (!empty($s['ag'])) fwrite($fp, (string)time()); flock($fp, LOCK_UN); fclose($fp); }   // yalnızca ağ hatasında: 60 sn tekrar denemeyi beklet
    if ($s['ag']) {
        if ((intval($L['son_ok'] ?? 0) + AKT_TOLERANS) > time()) return $d = ['ok' => true, 'durum' => 'aktif', 'mesaj' => ''];
        akt_kilitle($A, 'Lisans doğrulama sunucusuna uzun süredir ulaşılamıyor. İnternet erişimini kontrol edin; bağlantı gelince çalışır.', $host);
        return $d = ['ok' => false, 'durum' => 'kilit', 'mesaj' => 'Lisans doğrulama sunucusuna uzun süredir ulaşılamıyor.'];
    }
    if (!$s['ok'] && !empty($s['yanit']['gecici'])) { @file_put_contents($ask, $s['mesaj'] ?: 'Bu kurum askıya alınmış.'); return $d = ['ok' => false, 'durum' => 'askida', 'mesaj' => $s['mesaj'] ?: 'Bu kurum askıya alınmış.']; }   // kalıcı kilit YOK: geri açılınca kendiliğinden çalışır
    if ($askVar) @unlink($ask);
    if (!$s['ok']) { akt_kilitle($A, $s['mesaj'] ?: 'Lisans doğrulanamadı.', $host); return $d = ['ok' => false, 'durum' => 'kilit', 'mesaj' => $s['mesaj'] ?: 'Lisans doğrulanamadı.']; }
    akt_belirtec_kaydet($A, $s['yanit'], $s['ed'] ?? '');
    return $d = ['ok' => true, 'durum' => 'aktif', 'mesaj' => ''];
}

// ───────────────────────── ANA SUNUCU ─────────────────────────
function akt_sema($db)
{
    static $t = false; if ($t) return; $t = true;
    $o = $db->kurum; $db->kurum = 0;
    $db->query("CREATE TABLE IF NOT EXISTS pf_aktivasyon (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        kurum_id INT NOT NULL,
        aid VARCHAR(24) NOT NULL,
        kod_hash CHAR(64) NOT NULL,
        kod_son VARCHAR(4) NOT NULL DEFAULT '',
        anahtar CHAR(64) NOT NULL,
        durum VARCHAR(12) NOT NULL DEFAULT 'bekliyor',
        manifest CHAR(64) NOT NULL DEFAULT '',
        ana VARCHAR(255) NOT NULL DEFAULT '',
        sid CHAR(40) NULL,
        host VARCHAR(190) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '',
        etkin_zaman DATETIME NULL,
        son_dogrulama DATETIME NULL,
        uyari VARCHAR(20) NOT NULL DEFAULT '',
        uyari_ayrinti VARCHAR(300) NOT NULL DEFAULT '',
        uyari_zaman DATETIME NULL,
        deneme INT NOT NULL DEFAULT 0,
        deneme_zaman DATETIME NULL,
        paket_zaman DATETIME NULL,
        olusturma DATETIME NOT NULL,
        PRIMARY KEY (id), UNIQUE KEY uq_akt_kurum (kurum_id), UNIQUE KEY uq_akt_aid (aid)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    $ek = ['manifest' => "CHAR(64) NOT NULL DEFAULT ''", 'ana' => "VARCHAR(255) NOT NULL DEFAULT ''", 'sid' => 'CHAR(40) NULL', 'host' => "VARCHAR(190) NOT NULL DEFAULT ''", 'ip' => "VARCHAR(45) NOT NULL DEFAULT ''",
        'etkin_zaman' => 'DATETIME NULL', 'son_dogrulama' => 'DATETIME NULL', 'uyari' => "VARCHAR(20) NOT NULL DEFAULT ''", 'uyari_ayrinti' => "VARCHAR(300) NOT NULL DEFAULT ''", 'uyari_zaman' => 'DATETIME NULL',
        'deneme' => 'INT NOT NULL DEFAULT 0', 'deneme_zaman' => 'DATETIME NULL', 'paket_zaman' => 'DATETIME NULL', 'kod_son' => "VARCHAR(4) NOT NULL DEFAULT ''"];
    $var = []; $q = $db->query("SHOW COLUMNS FROM pf_aktivasyon"); while ($q && ($c = $q->fetch_assoc())) $var[$c['Field']] = true;
    foreach ($ek as $kol => $tn) if (!isset($var[$kol])) $db->query("ALTER TABLE pf_aktivasyon ADD COLUMN `$kol` $tn");
    $q = $db->query("SHOW COLUMNS FROM pf_kurumlar LIKE 'paket_kodlu'"); if ($q && $q->num_rows === 0) $db->query("ALTER TABLE pf_kurumlar ADD COLUMN paket_kodlu TINYINT(1) NOT NULL DEFAULT 0");
    $db->kurum = $o;
}
function akt_kod_uret() { $a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; $s = ''; for ($i = 0; $i < 16; $i++) $s .= $a[random_int(0, strlen($a) - 1)]; return implode('-', str_split($s, 4)); }
/** Ana sunucu imza anahtar çifti (ed25519); sodium yoksa boş döner */
function akt_anahtar_cifti($db)
{
    if (!function_exists('sodium_crypto_sign_keypair')) return ['sk' => '', 'pk' => ''];
    $sk = akt_ayar($db, 'akt_sk'); $pk = akt_ayar($db, 'akt_pk');
    if ($sk === '' || $pk === '') { $kp = sodium_crypto_sign_keypair(); $sk = bin2hex(sodium_crypto_sign_secretkey($kp)); $pk = bin2hex(sodium_crypto_sign_publickey($kp)); akt_ayar_yaz($db, 'akt_sk', $sk); akt_ayar_yaz($db, 'akt_pk', $pk); }
    return ['sk' => $sk, 'pk' => $pk];
}
/** Kurum için kayıt oluşturur / sıfırlar. Düz kod yalnızca burada döner (veri tabanında özeti tutulur). */
function akt_olustur($db, $kid, $kapat = true)
{
    akt_sema($db); $kid = intval($kid); $kod = akt_kod_uret(); $hash = hash('sha256', str_replace('-', '', $kod)); $son = substr(str_replace('-', '', $kod), -4);
    $o = $db->kurum; $db->kurum = 0;
    $q = $db->query("SELECT id, aid, anahtar FROM pf_aktivasyon WHERE kurum_id = $kid"); $v = $q ? $q->fetch_assoc() : null;
    if ($v) {
        $st = $db->prepare("UPDATE pf_aktivasyon SET kod_hash = ?, kod_son = ?, durum = 'bekliyor', sid = NULL, host = '', ip = '', etkin_zaman = NULL, son_dogrulama = NULL, uyari = '', uyari_ayrinti = '', uyari_zaman = NULL, deneme = 0, deneme_zaman = NULL, paket_zaman = NULL WHERE id = ?");
        $id = intval($v['id']); $st->bind_param('ssi', $hash, $son, $id); $st->execute();
    } else {
        $aid = 'A' . strtoupper(bin2hex(random_bytes(8))); $an = bin2hex(random_bytes(32));
        $st = $db->prepare("INSERT INTO pf_aktivasyon (kurum_id, aid, kod_hash, kod_son, anahtar, durum, olusturma) VALUES (?, ?, ?, ?, ?, 'bekliyor', NOW())");
        $st->bind_param('issss', $kid, $aid, $hash, $son, $an); $st->execute();
    }
    if ($kapat) $db->query("UPDATE pf_kurumlar SET paket_kodlu = 1 WHERE id = $kid");   // $kapat=false: kurum bu sunucuda çalışmaya devam eder, paket bir KOPYADIR
    $db->kurum = $o;
    return $kod;
}
function akt_al($db, $kid) { $o = $db->kurum; $db->kurum = 0; $q = $db->query("SELECT * FROM pf_aktivasyon WHERE kurum_id = " . intval($kid)); $r = $q ? $q->fetch_assoc() : null; $db->kurum = $o; return $r ?: null; }
function akt_ozet($db, $kid)
{
    $r = akt_al($db, $kid); if (!$r) return null;
    return ['durum' => $r['durum'], 'kod_son' => $r['kod_son'], 'host' => $r['host'], 'ip' => $r['ip'], 'etkin_zaman' => $r['etkin_zaman'], 'son_dogrulama' => $r['son_dogrulama'], 'uyari' => $r['uyari'], 'uyari_ayrinti' => $r['uyari_ayrinti'], 'uyari_zaman' => $r['uyari_zaman'], 'paket_zaman' => $r['paket_zaman'], 'olusturma' => $r['olusturma']];
}
function akt_iptal($db, $kid) { akt_sema($db); $o = $db->kurum; $db->kurum = 0; $db->query("UPDATE pf_aktivasyon SET durum = 'iptal' WHERE kurum_id = " . intval($kid)); $db->kurum = $o; }
/** Paketi hazırlar (yalnızca "bekliyor" iken): kurum paketi + aktivasyon anahtar dosyası. Zip yolu döner. */
function akt_paket($db, $kid, $anaUrl)
{
    require_once __DIR__ . '/bakim_ortak.php';
    $r = akt_al($db, $kid); if (!$r) throw new RuntimeException('Bu kurum aktivasyon kodlu paket değil.');
    if ($r['durum'] !== 'bekliyor') throw new RuntimeException($r['durum'] === 'aktif' ? 'Paket tek seferliktir ve etkinleştirilmiş; yeniden indirilemez. Gerekirse “Yeni kod üret” ile sıfırlayın.' : 'Aktivasyon iptal edilmiş.');
    $manifest = akt_manifest(); $cift = akt_anahtar_cifti($db);
    $zp = tb_kurum_paketi($db, $kid);
    $z = new ZipArchive(); if ($z->open($zp) !== true) throw new RuntimeException('Paket açılamadı.');
    $z->addFromString('kurum_paketi/aktivasyon.json', json_encode(['aid' => $r['aid'], 'ana' => rtrim($anaUrl, '/'), 'anahtar' => $r['anahtar'], 'pk' => $cift['pk']], JSON_UNESCAPED_SLASHES));
    $z->close();
    $db->kurum = 0; $st = $db->prepare("UPDATE pf_aktivasyon SET manifest = ?, ana = ?, paket_zaman = NOW() WHERE id = ?"); if (!$st) throw new RuntimeException('Aktivasyon tablosu güncellenemedi: ' . $db->error); $id = intval($r['id']); $ana = rtrim($anaUrl, '/'); $st->bind_param('ssi', $manifest, $ana, $id); $st->execute();
    return $zp;
}
/** Ana sunucu: müşteri kurulumundan gelen istek. Dönüş: yanıt dizisi (imzalanmadan) */
function akt_istek_isle($db, $aid, $olay, array $v, $ip)
{
    $r = null; $o = $db->kurum; $db->kurum = 0;
    $st = $db->prepare("SELECT * FROM pf_aktivasyon WHERE aid = ?"); $st->bind_param('s', $aid); $st->execute(); $q = $st->get_result(); $r = $q ? $q->fetch_assoc() : null;
    $ret = function ($mesaj, $uyari = '', $ayr = '') use ($db, $r) {
        if ($r && $uyari !== '') { $s = $db->prepare("UPDATE pf_aktivasyon SET uyari = ?, uyari_ayrinti = ?, uyari_zaman = NOW() WHERE id = ?"); $id = intval($r['id']); $ayr = mb_substr($ayr, 0, 300, 'UTF-8'); $s->bind_param('ssi', $uyari, $ayr, $id); $s->execute(); }
        return ['ok' => false, 'mesaj' => $mesaj];
    };
    $sid = (string)($v['sid'] ?? ''); $host = akt_host_norm($v['host'] ?? ''); $man = (string)($v['manifest'] ?? '');
    if (!preg_match('/^[a-f0-9]{40}$/', $sid) || $host === '') { $db->kurum = $o; return ['ok' => false, 'mesaj' => 'Geçersiz istek.']; }
    $belirtec = function ($exp) use ($r, $sid, $host) { return ['aid' => $r['aid'], 'durum' => 'aktif', 'sid' => $sid, 'host' => $host, 'exp' => $exp, 'son_ok' => time(), 'zaman' => time()]; };
    $out = null;
    if (!$r) $out = ['ok' => false, 'mesaj' => 'Bu paket tanınmıyor (kurum silinmiş olabilir).'];
    elseif ($r['durum'] === 'iptal') $out = $ret('Lisans iptal edilmiş. Lütfen sağlayıcınızla iletişime geçin.');
    elseif ($olay === 'etkinlestir') {
        $simdi = time();
        if (intval($r['deneme']) >= 8 && $r['deneme_zaman'] && strtotime($r['deneme_zaman']) > $simdi - 900) $out = ['ok' => false, 'mesaj' => 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.'];
        elseif ($r['durum'] !== 'bekliyor') $out = $ret('Bu aktivasyon kodu daha önce kullanılmış. Kod yalnızca bir kez geçerlidir.', 'tekrar', 'Kullanılmış kod ile etkinleştirme denendi (' . $host . ', ' . $ip . ')');
        elseif (!hash_equals($r['kod_hash'], hash('sha256', (string)($v['kod'] ?? '')))) {
            $db->query("UPDATE pf_aktivasyon SET deneme = IF(deneme_zaman IS NULL OR deneme_zaman < NOW() - INTERVAL 15 MINUTE, 1, deneme + 1), deneme_zaman = NOW() WHERE id = " . intval($r['id']));
            $out = ['ok' => false, 'mesaj' => 'Aktivasyon kodu hatalı.'];
        }
        elseif ($r['manifest'] !== '' && !hash_equals($r['manifest'], $man)) $out = $ret('Paket dosyaları değiştirilmiş; etkinleştirilemez.', 'dosya', 'Etkinleştirme sırasında dosya parmak izi uyuşmadı (' . $host . ')');
        else {
            $s = $db->prepare("UPDATE pf_aktivasyon SET durum = 'aktif', sid = ?, host = ?, ip = ?, etkin_zaman = NOW(), son_dogrulama = NOW(), uyari = '', uyari_ayrinti = '', deneme = 0 WHERE id = ? AND durum = 'bekliyor'");
            $id = intval($r['id']); $s->bind_param('sssi', $sid, $host, $ip, $id); $s->execute();
            if ($db->affected_rows < 1) $out = ['ok' => false, 'mesaj' => 'Bu aktivasyon kodu daha önce kullanılmış.'];
            else { $exp = time() + AKT_GECERLILIK; $out = ['ok' => true, 'mesaj' => 'Aktivasyon tamamlandı.', 'belirtec' => $belirtec($exp)]; if (function_exists('pf_log')) pf_log($db, 'aktivasyon', 'Kod kullanıldı: ' . $host, intval($r['kurum_id'])); }
        }
    } elseif ($olay === 'dogrula') {
        $kr = function_exists('pf_kurum') ? pf_kurum($db, intval($r['kurum_id'])) : null;
        if ($r['durum'] !== 'aktif') $out = $ret('Yeni bir aktivasyon kodu gerekiyor. Lütfen sağlayıcınızla iletişime geçin.');
        elseif (!$kr) $out = ['ok' => false, 'mesaj' => 'Kurum kaydı bulunamadı.'];
        elseif ($kr['durum'] !== 'aktif') $out = ['ok' => false, 'gecici' => true, 'mesaj' => 'Bu kurum askıya alınmış. Lütfen sağlayıcınızla iletişime geçin.'];
        elseif (function_exists('pf_lisans') && pf_lisans($kr)['durum'] === 'askida') $out = ['ok' => false, 'gecici' => true, 'mesaj' => 'Kullanım süresi dolmuş. Lütfen sağlayıcınızla iletişime geçin.'];
        elseif (!hash_equals((string)$r['sid'], $sid)) $out = $ret('Bu kurulum, etkinleştirilmiş kurulumun kopyası olarak algılandı ve çalışmaz.', 'kopya', 'Başka bir kopya doğrulama istedi: ' . $host . ' (' . $ip . ')');
        elseif ($host !== $r['host']) $out = $ret('Bu kurulum yalnızca etkinleştirildiği adreste çalışır (' . $r['host'] . ').', 'adres', 'Farklı adresten doğrulama: ' . $host . ' (' . $ip . ')');
        elseif ($r['manifest'] !== '' && !hash_equals($r['manifest'], $man)) $out = $ret('Uygulama dosyaları değiştirilmiş ya da silinmiş; lisans doğrulanamadı.', 'dosya', 'Dosya parmak izi uyuşmadı (' . $host . ')');
        else { $db->query("UPDATE pf_aktivasyon SET son_dogrulama = NOW() WHERE id = " . intval($r['id'])); $out = ['ok' => true, 'mesaj' => '', 'belirtec' => $belirtec(time() + AKT_GECERLILIK)]; }
    } else $out = ['ok' => false, 'mesaj' => 'Bilinmeyen işlem.'];
    $db->kurum = $o;
    return $out;
}
