<?php
// ============================================================
// bakim_ortak.php — Yedekleme, başka sunucuya taşıma, güncelleme paketi ve uzaktan güncelleme yardımcıları
//   Yedek paketi (.zip): veri.sql (tüm pf_ ve k{no}_ tabloları) + uploads/ + surum.json
//   Güncelleme paketi (.zip): kod dosyaları + surum.json (+ migrasyon/*.php). config/db.php, uploads/, storage/ asla ezilmez.
// ============================================================
if (!defined('TB_BAKIM')) {
define('TB_BAKIM', true);
define('TB_KORUMALI', ['config/db.php', 'config/kurulum.kilit', 'config/kurum.id']);                 // güncellemede ezilmez
define('TB_KORUMALI_KLASOR', ['uploads/', 'storage/', 'cron/']);
define('TB_YUKLEME_UZANTI', ['jpg', 'jpeg', 'png', 'webp', 'gif', 'ico', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'mp3', 'svg']);   // geri yüklenen yükleme dosyaları için izinli türler
define('TB_IZINLI_UZANTI', ['php', 'js', 'css', 'json', 'txt', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'ico', 'mp3', 'htaccess', 'html', 'woff2', 'sql']);

function tb_surum_bilgi()
{
    $d = @json_decode((string)@file_get_contents(TB_KOK . '/surum.json'), true);
    return is_array($d) ? $d : ['uygulama' => 'turkuaz-bilesim', 'surum' => '0'];
}
function tb_guvenli_yol($y)
{
    $y = str_replace('\\', '/', (string)$y);
    return $y !== '' && $y[0] !== '/' && strpos($y, '..') === false && strpos($y, "\0") === false && !preg_match('~^[A-Za-z]:~', $y);
}
function tb_korumali($y)
{
    if (in_array($y, TB_KORUMALI, true)) return true;
    foreach (TB_KORUMALI_KLASOR as $k) if (strncmp($y, $k, strlen($k)) === 0) return true;
    return false;
}
function tb_gecici($ek = '.tmp')
{
    $d = TB_KOK . '/storage';
    if (!is_dir($d)) @mkdir($d, 0755, true);
    return $d . '/' . bin2hex(random_bytes(8)) . $ek;
}
function tb_zip_var() { return class_exists('ZipArchive'); }

// ───────────── Veri yedeği (SQL) ─────────────
function tb_tablolar($db)
{
    $l = []; $q = $db->query("SHOW TABLES");
    if ($q) while ($r = $q->fetch_row()) if (preg_match('/^(pf_[a-z0-9_]+|k[0-9]+_[a-z0-9_]+)$/i', $r[0])) $l[] = $r[0];
    sort($l);
    return $l;
}
/** Tüm platform + kurum tablolarını tek satırlık deyimlerle $yol dosyasına yazar. */
function tb_dump($db, $yol, $spec = null)
{
    $db->kurum = 0;
    $f = fopen($yol, 'wb'); if (!$f) throw new RuntimeException('Yedek dosyası yazılamadı (storage/ klasörü yazılabilir mi?).');
    fwrite($f, "-- Turkuaz Bileşim yedeği " . date('Y-m-d H:i:s') . " — sürüm " . (tb_surum_bilgi()['surum'] ?? '?') . "\n");
    fwrite($f, "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n");
    // $spec: tablo => 'veri' | 'yapi' (yalnızca şema) | 'WHERE ...' (süzgeçli veri). Verilmezse tüm tablolar tam yazılır.
    $liste = $spec === null ? tb_tablolar($db) : array_keys($spec);
    foreach ($liste as $t) {
        if (!empty($GLOBALS['tb_iptal']) && $GLOBALS['tb_iptal']()) { fclose($f); @unlink($yol); throw new RuntimeException('İşlem kullanıcı tarafından durduruldu.'); }
        $mod = $spec === null ? 'veri' : $spec[$t];
        $c = $db->query("SHOW CREATE TABLE `$t`"); if (!$c) continue;
        $sql = $c->fetch_row()[1];
        fwrite($f, "DROP TABLE IF EXISTS `$t`;\n" . preg_replace('/\s*\n\s*/', ' ', $sql) . ";\n");
        if ($mod === 'yapi') continue;
        $q = $db->query("SELECT * FROM `$t`" . (strncmp($mod, 'WHERE', 5) === 0 ? ' ' . $mod : ''), MYSQLI_USE_RESULT);
        if (!$q) continue;
        $tur = []; foreach ($q->fetch_fields() as $fl) $tur[] = $fl->name;
        $kol = '`' . implode('`,`', $tur) . '`'; $blok = []; $n = 0;
        while ($r = $q->fetch_row()) {
            $v = [];
            foreach ($r as $x) $v[] = $x === null ? 'NULL' : "'" . $db->real_escape_string($x) . "'";
            $blok[] = '(' . implode(',', $v) . ')';
            if (++$n % 200 === 0) { fwrite($f, "INSERT INTO `$t` ($kol) VALUES " . implode(',', $blok) . ";\n"); $blok = []; }
        }
        $q->free();
        if ($blok) fwrite($f, "INSERT INTO `$t` ($kol) VALUES " . implode(',', $blok) . ";\n");
    }
    fwrite($f, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($f);
}
function tb_klasor_zip($zip, $kok, $onek, $haric = [])
{
    if (!is_dir($kok)) return 0;
    $n = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($kok, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $dosya) {
        if (!$dosya->isFile()) continue;
        $gor = $onek . str_replace('\\', '/', substr($dosya->getPathname(), strlen($kok) + 1));
        $atla = false; foreach ($haric as $h) if (strncmp($gor, $h, strlen($h)) === 0) $atla = true;
        if ($atla || preg_match('~(^|/)\.(git|svn)~', $gor)) continue;
        $zip->addFile($dosya->getPathname(), $gor); $n++;
    }
    return $n;
}
/** Yedek paketi: veri.sql + uploads (+ isteğe bağlı) . Dönüş: geçici zip yolu */
function tb_yedek_olustur($db, $uploadsDahil = true)
{
    if (!tb_zip_var()) throw new RuntimeException('PHP zip eklentisi (ZipArchive) kurulu değil.');
    @set_time_limit(0);
    $sql = tb_gecici('.sql'); tb_dump($db, $sql);
    $zp = tb_gecici('.zip'); $z = new ZipArchive();
    if ($z->open($zp, ZipArchive::CREATE) !== true) throw new RuntimeException('Zip oluşturulamadı.');
    $z->addFile($sql, 'veri.sql');
    $b = tb_surum_bilgi(); $b['tur'] = 'yedek'; $b['yedek_zamani'] = date('c'); $b['uploads'] = $uploadsDahil;
    $z->addFromString('surum.json', json_encode($b, JSON_UNESCAPED_UNICODE));
    if ($uploadsDahil) tb_klasor_zip($z, TB_KOK . '/uploads', 'uploads/', ['uploads/.htaccess']);
    $z->close(); @unlink($sql);
    return $zp;
}
/** Yedek SQL dosyasını (yalnızca izin verilen deyimleri) çalıştırır. Dönüş: tablo sayısı */
function tb_sql_yukle($db, $sqlYol)
{
    if ($db instanceof VDB) $db->kurum = 0;
    $f = fopen($sqlYol, 'rb'); $tablo = 0; $satir = 0;
    while (($l = fgets($f)) !== false) {
        $l = rtrim($l, "\r\n"); $satir++;
        if ($l === '' || strncmp($l, '--', 2) === 0) continue;
        if (!preg_match('/^(SET (NAMES|FOREIGN_KEY_CHECKS|SQL_MODE)\b|DROP TABLE IF EXISTS `(pf_|k[0-9]+_)[a-z0-9_]+`;$|CREATE TABLE `(pf_|k[0-9]+_)[a-z0-9_]+` |INSERT INTO `(pf_|k[0-9]+_)[a-z0-9_]+` )/i', $l)) {
            fclose($f); throw new RuntimeException("Yedekte izin verilmeyen bir deyim var (satır $satir); işlem durduruldu.");
        }
        if (!preg_match('/^(SET |DROP TABLE IF EXISTS `[a-z0-9_]+`;$|CREATE TABLE `[a-z0-9_]+` \(|INSERT INTO `[a-z0-9_]+` \(`[^)]*`\) VALUES \()/', $l)) { fclose($f); throw new RuntimeException("Yedekte beklenmeyen deyim biçimi (satır $satir); işlem durduruldu."); }
        if (!$db->query($l)) { $h = method_exists($db, 'hata') ? $db->hata() : $db->error; fclose($f); throw new RuntimeException("SQL hatası (satır $satir): $h"); }
        if (strncmp($l, 'CREATE TABLE', 12) === 0) $tablo++;
    }
    fclose($f);
    return $tablo;
}
/** Yedek paketinden geri yükler. Dönüş: [tablo sayısı, dosya sayısı] */
function tb_geri_yukle($db, $zipYol)
{
    if (!tb_zip_var()) throw new RuntimeException('PHP zip eklentisi (ZipArchive) kurulu değil.');
    @set_time_limit(0);
    $z = new ZipArchive();
    if ($z->open($zipYol) !== true) throw new RuntimeException('Zip dosyası açılamadı.');
    $b = @json_decode((string)$z->getFromName('surum.json'), true);
    if (!is_array($b) || ($b['uygulama'] ?? '') !== 'turkuaz-bilesim' || ($b['tur'] ?? '') !== 'yedek' || $z->locateName('veri.sql') === false)
        throw new RuntimeException('Bu dosya bir Turkuaz Bileşim yedek paketi değil.');
    $sqlYol = tb_gecici('.sql'); $in = $z->getStream('veri.sql'); $out = fopen($sqlYol, 'wb'); stream_copy_to_stream($in, $out); fclose($out); fclose($in);
    try { $tablo = tb_sql_yukle($db, $sqlYol); } finally { @unlink($sqlYol); }
    $dosya = 0;
    for ($i = 0; $i < $z->numFiles; $i++) {
        $ad = $z->getNameIndex($i);
        if (strncmp($ad, 'uploads/', 8) !== 0 || substr($ad, -1) === '/' || !tb_guvenli_yol($ad) || !in_array(strtolower(pathinfo($ad, PATHINFO_EXTENSION)), TB_YUKLEME_UZANTI, true)) continue;
        $hedef = TB_KOK . '/' . $ad; $d = dirname($hedef); if (!is_dir($d)) @mkdir($d, 0755, true);
        $s = $z->getStream($ad); $o = @fopen($hedef, 'wb'); if (!$s || !$o) continue; stream_copy_to_stream($s, $o); fclose($o); fclose($s); $dosya++;
    }
    $z->close();
    return [$tablo, $dosya];
}

// ───────────── Güncelleme paketi ─────────────
/** Uygulama kodunu (korumalı yollar ve veri hariç) zip'e ekler. Dönüş: dosya sayısı */
function tb_kod_ekle($z)
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(TB_KOK, FilesystemIterator::SKIP_DOTS));
    $n = 0;
    foreach ($it as $d) {
        if (!$d->isFile()) continue;
        $g = str_replace('\\', '/', substr($d->getPathname(), strlen(TB_KOK) + 1));
        if (tb_korumali($g) || preg_match('~(^|/)\.(git|svn|idea)~', $g) || substr($g, -4) === '.zip' || substr($g, -4) === '.log') continue;
        $uz = strtolower(pathinfo($g, PATHINFO_EXTENSION) ?: basename($g, ''));
        if (!in_array($uz, TB_IZINLI_UZANTI, true) && basename($g) !== '.htaccess') continue;
        $z->addFile($d->getPathname(), $g); $n++;
    }
    return $n;
}
function tb_paket_olustur()
{
    if (!tb_zip_var()) throw new RuntimeException('PHP zip eklentisi (ZipArchive) kurulu değil.');
    $zp = tb_gecici('.zip'); $z = new ZipArchive();
    if ($z->open($zp, ZipArchive::CREATE) !== true) throw new RuntimeException('Zip oluşturulamadı.');
    tb_kod_ekle($z);
    $b = tb_surum_bilgi(); $b['tur'] = 'guncelleme'; $b['paket_zamani'] = date('c');
    $z->deleteName('surum.json'); $z->addFromString('surum.json', json_encode($b, JSON_UNESCAPED_UNICODE));
    $z->close();
    return $zp;
}
/** Paketi doğrular ve uygular. Dönüş: ['surum'=>, 'dosya'=>, 'yedek'=>, 'migrasyon'=>[...]] */
function tb_guncelle($db, $zipYol)
{
    if (!tb_zip_var()) throw new RuntimeException('PHP zip eklentisi (ZipArchive) kurulu değil.');
    @set_time_limit(0);
    $z = new ZipArchive();
    if ($z->open($zipYol) !== true) throw new RuntimeException('Zip dosyası açılamadı.');
    $b = @json_decode((string)$z->getFromName('surum.json'), true);
    if (!is_array($b) || ($b['uygulama'] ?? '') !== 'turkuaz-bilesim' || ($b['tur'] ?? '') !== 'guncelleme')
        throw new RuntimeException('Bu dosya bir Turkuaz Bileşim güncelleme paketi değil.');
    // 1) Ön kontrol: yollar, uzantılar, PHP sözdizimi — hiçbir şey yazılmadan önce
    $liste = [];
    for ($i = 0; $i < $z->numFiles; $i++) {
        $ad = $z->getNameIndex($i);
        if (substr($ad, -1) === '/') continue;
        if (!tb_guvenli_yol($ad)) throw new RuntimeException("Paketteki yol güvenli değil: $ad");
        if (tb_korumali($ad)) continue;
        $uz = strtolower(pathinfo($ad, PATHINFO_EXTENSION));
        if (!in_array($uz, TB_IZINLI_UZANTI, true) && basename($ad) !== '.htaccess') throw new RuntimeException("İzin verilmeyen dosya türü: $ad");
        if ($uz === 'php') { try { token_get_all((string)$z->getFromIndex($i), TOKEN_PARSE); } catch (ParseError $e) { throw new RuntimeException("Sözdizimi hatası ($ad, satır " . $e->getLine() . "): " . $e->getMessage()); } }
        $liste[] = [$i, $ad];
    }
    if (!$liste) throw new RuntimeException('Pakette uygulanacak dosya yok.');
    // 2) Yedek: ezilecek mevcut dosyalar
    $yedek = 'storage/yedek_' . date('Ymd_His') . '_' . bin2hex(random_bytes(2)) . '.zip'; $y = new ZipArchive();
    if ($y->open(TB_KOK . '/' . $yedek, ZipArchive::CREATE) === true) {
        foreach ($liste as $e) if (is_file(TB_KOK . '/' . $e[1])) $y->addFile(TB_KOK . '/' . $e[1], $e[1]);
        $y->close();
    } else $yedek = '';
    // 3) Yaz
    foreach ($liste as $e) {
        $hedef = TB_KOK . '/' . $e[1]; $d = dirname($hedef); if (!is_dir($d)) @mkdir($d, 0755, true);
        $veri = $z->getFromIndex($e[0]);
        if (@file_put_contents($hedef, $veri) === false) throw new RuntimeException("Yazılamadı: {$e[1]} (dosya izinlerini kontrol edin). Yedek: $yedek");
    }
    $z->close();
    if (function_exists('opcache_reset')) @opcache_reset();
    // 4) Veritabanı: migrasyonlar + her kurumun şeması
    $m = tb_migrasyon_calistir($db);
    return ['surum' => $b['surum'] ?? '?', 'dosya' => count($liste), 'yedek' => $yedek, 'migrasyon' => $m];
}
function tb_migrasyon_calistir($db)
{
    $db->kurum = 0;
    $db->query("CREATE TABLE IF NOT EXISTS pf_migrasyon (ad VARCHAR(150) NOT NULL, zaman DATETIME NOT NULL, PRIMARY KEY (ad)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $kurumlar = []; $q = $db->query("SELECT * FROM pf_kurumlar ORDER BY id"); if ($q) while ($r = $q->fetch_assoc()) $kurumlar[] = $r;
    $yapilan = [];
    $biten = []; $q = $db->query("SELECT ad FROM pf_migrasyon"); if ($q) while ($r = $q->fetch_row()) $biten[$r[0]] = 1;
    $dosyalar = glob(TB_KOK . '/migrasyon/*.php') ?: []; sort($dosyalar);
    foreach ($dosyalar as $d) {
        $ad = basename($d); if (isset($biten[$ad])) continue;
        $f = require $d;
        if (is_callable($f)) { $f($db, $kurumlar); $db->kurum = 0; }
        $st = $db->prepare("INSERT IGNORE INTO pf_migrasyon (ad, zaman) VALUES (?, NOW())"); $st->bind_param('s', $ad); $st->execute();
        $yapilan[] = $ad;
    }
    // yeni sürümün eklediği tablolar: her kurumun şemasını tamamla
    foreach (glob(TB_KOK . '/cron/.ik_sema*') ?: [] as $s) @unlink($s);
    foreach ($kurumlar as $k) { $db->kurum = intval($k['id']); try { kurum_sema($db); } catch (Throwable $e) {} }
    $db->kurum = 0;
    return $yapilan;
}

// ───────────── Uzaktan güncelleme (başka kurulumdan paket çekme) ─────────────
function tb_uzak_istek($url, $anahtar, $indir, $hedefDosya = null)
{
    if (!preg_match('~^https?://~i', $url)) throw new RuntimeException('Güncelleme adresi http(s):// ile başlamalı.');
    if (!function_exists('curl_init')) throw new RuntimeException('PHP curl eklentisi gerekli.');
    $ayr = (strpos($url, '?') === false ? '?' : '&') . ($indir ? 'indir=1' : 'surum=1') . '&anahtar=' . rawurlencode($anahtar);
    $ch = curl_init($url . $ayr);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => !$hedefDosya, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3, CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_USERAGENT => 'TurkuazBilesim-Guncelleme', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS]);
    $fh = null; if ($hedefDosya) { $fh = fopen($hedefDosya, 'wb'); curl_setopt($ch, CURLOPT_FILE, $fh); }
    $r = curl_exec($ch); $kod = curl_getinfo($ch, CURLINFO_HTTP_CODE); $h = curl_error($ch); curl_close($ch); if ($fh) fclose($fh);
    if ($r === false || $kod !== 200) throw new RuntimeException($kod === 403 ? 'Kaynak, güncelleme anahtarını kabul etmedi.' : 'Kaynağa ulaşılamadı' . ($h ? ": $h" : " (HTTP $kod)") . '.');
    return $hedefDosya ? true : $r;
}

/** Yalnızca BİR kurumun verisi + dosyaları (kod yok): başka kurulumun "Kurum verisi çek" özelliği için. */
function tb_kurum_veri_paketi($db, $kid)
{
    if (!tb_zip_var()) throw new RuntimeException('PHP zip eklentisi (ZipArchive) kurulu değil.');
    @set_time_limit(0); $kid = intval($kid); $k = pf_kurum($db, $kid); if (!$k) throw new RuntimeException('Kurum bulunamadı.');
    $db->kurum = 0; $spec = [];
    foreach (tb_tablolar($db) as $t) if (preg_match('/^k' . $kid . '_/', $t)) $spec[$t] = 'veri';
    $sql = tb_gecici('.sql'); tb_dump($db, $sql, $spec);
    $zp = tb_gecici('.zip'); $z = new ZipArchive();
    if ($z->open($zp, ZipArchive::CREATE) !== true) throw new RuntimeException('Zip oluşturulamadı.');
    $z->addFile($sql, 'kurum_verisi/veri.sql');
    $z->addFromString('kurum_verisi/kurum.json', json_encode(['kurum_id' => $kid, 'satir' => $k, 'zaman' => date('c')], JSON_UNESCAPED_UNICODE));
    tb_klasor_zip($z, TB_KOK . '/uploads/k' . $kid, 'uploads/k' . $kid . '/');
    $z->close(); @unlink($sql);
    return $zp;
}
/** Kaynak sunucudan indirilen kurum verisi zip'ini HEDEF veri tabanına yazar. $h verilmezse bu kurulumun veri tabanı.
 *  Hedefte aynı kurumun tabloları varsa yenisiyle DEĞİŞTİRİLİR, kurum kaydı güncellenir; diğer kurumlara dokunulmaz.
 *  Dönüş: ['id','ad','tablo','dosya','guncellendi'] */
function tb_kurum_veri_al($db, $zipYol, $h = null)
{
    $h = $h ?: $db; $yerel = ($h === $db);
    $z = new ZipArchive(); if ($z->open($zipYol) !== true) throw new RuntimeException('Paket açılamadı.');
    $j = json_decode((string)$z->getFromName('kurum_verisi/kurum.json'), true); $sql = $z->getFromName('kurum_verisi/veri.sql');
    if (!is_array($j) || empty($j['satir']['id']) || $sql === false) { $z->close(); throw new RuntimeException('Bu zip bir kurum verisi paketi değil.'); }
    $kid = intval($j['kurum_id']); $s = $j['satir'];
    if ($kid < 1 || intval($s['id']) !== $kid) { $z->close(); throw new RuntimeException('Paket kurum numarası tutarsız.'); }
    if ($yerel) $h->kurum = 0;
    $h->query("SET NAMES utf8mb4");
    $var = $h->query("SHOW TABLES LIKE 'k" . $kid . "\\_%'"); $once = $var && $var->num_rows > 0;
    $gec = tb_gecici('.sql'); file_put_contents($gec, $sql); unset($sql);
    $f = fopen($gec, 'rb');                                              // yalnızca bu kuruma ait tablolar içe alınır
    while (($l = fgets($f)) !== false) {
        if (preg_match('/^(DROP TABLE IF EXISTS|CREATE TABLE|INSERT INTO) `([^`]+)`/', $l, $m) && !preg_match('/^k' . $kid . '_[a-z0-9_]+$/i', $m[2])) { fclose($f); @unlink($gec); $z->close(); throw new RuntimeException('Pakette bu kuruma ait olmayan bir tablo var (' . $m[2] . '); işlem durduruldu.'); }
    }
    fclose($f);
    try {
        $tablo = tb_sql_yukle($h, $gec); @unlink($gec);
        // hedefte platform kurum tablosu yoksa bu kurulumdakinin şemasıyla oluştur
        $v = $h->query("SHOW TABLES LIKE 'pf_kurumlar'");
        if (!$v || !$v->num_rows) { $c = $db->query("SHOW CREATE TABLE pf_kurumlar"); $ddl = $c ? $c->fetch_row()[1] : ''; if ($ddl === '' || !$h->query($ddl)) throw new RuntimeException('Hedefte pf_kurumlar tablosu oluşturulamadı.'); }
        $kol = []; $r = $h->query("SHOW COLUMNS FROM pf_kurumlar"); while ($r && ($c = $r->fetch_assoc())) $kol[$c['Field']] = true;
        $s['paket_kodlu'] = 0; $ad = []; $deg = []; $tur = ''; $gunc = [];
        foreach ($s as $a => $x) if (isset($kol[$a])) { $ad[] = '`' . $a . '`'; $deg[] = $x; $tur .= 's'; if ($a !== 'id') $gunc[] = '`' . $a . '` = VALUES(`' . $a . '`)'; }
        $st = $h->prepare("INSERT INTO pf_kurumlar (" . implode(',', $ad) . ") VALUES (" . implode(',', array_fill(0, count($ad), '?')) . ") ON DUPLICATE KEY UPDATE " . implode(',', $gunc));
        if (!$st) throw new RuntimeException('Kurum kaydı hazırlanamadı: ' . $h->error);
        $st->bind_param($tur, ...$deg); if (!$st->execute()) throw new RuntimeException('Kurum kaydı yazılamadı: ' . $h->error);
        $dosya = 0; $onek = 'uploads/k' . $kid . '/';
        if ($yerel) for ($i = 0; $i < $z->numFiles; $i++) {             // dosyalar yalnızca bu sunucuya aktarılır (başka sunucunun disklerine yazılamaz)
            $ad2 = $z->getNameIndex($i);
            if (strncmp($ad2, $onek, strlen($onek)) !== 0 || substr($ad2, -1) === '/' || strpos($ad2, '..') !== false || preg_match('/\.(php|phtml|phar|php[0-9])$/i', $ad2)) continue;
            $d = TB_KOK . '/' . $ad2; if (!is_dir(dirname($d))) @mkdir(dirname($d), 0755, true);
            if (@file_put_contents($d, $z->getFromIndex($i)) !== false) $dosya++;
        }
        $z->close();
        return ['id' => $kid, 'ad' => (string)$s['ad'], 'tablo' => $tablo, 'dosya' => $dosya, 'guncellendi' => $once, 'yerel' => $yerel];
    } catch (Throwable $e) {
        @unlink($gec); $z->close();
        if (!$once) { $t = $h->query("SHOW TABLES LIKE 'k" . $kid . "\\_%'"); while ($t && ($x = $t->fetch_row())) $h->query("DROP TABLE IF EXISTS `" . $x[0] . "`"); }   // yeni aktarımsa yarım kalanı geri al
        throw $e;
    }
}

} // TB_BAKIM

// ───────────── Tek kurum paketi (taşınabilir) ─────────────
/**
 * Bir kurumu başka sunucuya taşımak için bağımsız paket: uygulama kodu + yalnızca o kurumun verisi/dosyaları.
 * Hedef sunucuda zip açılıp siteye girilince yalnızca veritabanı bilgileri sorulur; platform paneli/yönetici kurulumu gelmez.
 */
function tb_kurum_paketi($db, $kid)
{
    if (!tb_zip_var()) throw new RuntimeException('PHP zip eklentisi (ZipArchive) kurulu değil.');
    @set_time_limit(0);
    $kid = intval($kid); $k = pf_kurum($db, $kid); if (!$k) throw new RuntimeException('Kurum bulunamadı.');
    $db->kurum = 0;
    // Veri: platform şeması (boş) + ayarlar + yalnızca bu kurumun kaydı + kurumun tüm tabloları
    $spec = [];
    foreach (tb_tablolar($db) as $t) {
        if (preg_match('/^k([0-9]+)_/', $t, $m)) { if (intval($m[1]) === $kid) $spec[$t] = 'veri'; continue; }
        if ($t === 'pf_ayarlar') $spec[$t] = "WHERE anahtar NOT LIKE 'akt\\_%'";   // ana sunucunun aktivasyon gizli anahtarı pakete girmez
        elseif ($t === 'pf_migrasyon') $spec[$t] = 'veri';
        elseif ($t === 'pf_kurumlar') $spec[$t] = 'WHERE id = ' . $kid;
        else $spec[$t] = 'yapi';
    }
    $sql = tb_gecici('.sql'); tb_dump($db, $sql, $spec);
    $zp = tb_gecici('.zip'); $z = new ZipArchive();
    if ($z->open($zp, ZipArchive::CREATE) !== true) throw new RuntimeException('Zip oluşturulamadı.');
    tb_kod_ekle($z);
    foreach (['uploads/.htaccess', 'storage/.htaccess'] as $g) if (is_file(TB_KOK . '/' . $g)) $z->addFile(TB_KOK . '/' . $g, $g);
    $z->addFile($sql, 'kurum_paketi/veri.sql');
    $z->addFromString('kurum_paketi/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    $z->addFromString('kurum_paketi/kurum.json', json_encode(['kurum_id' => $kid, 'kod' => $k['kod'], 'ad' => $k['ad'], 'zaman' => date('c')], JSON_UNESCAPED_UNICODE));
    tb_klasor_zip($z, TB_KOK . '/uploads/k' . $kid, 'uploads/k' . $kid . '/');
    $b = tb_surum_bilgi(); $b['tur'] = 'kurum_paketi'; $b['paket_zamani'] = date('c');
    $z->deleteName('surum.json'); $z->addFromString('surum.json', json_encode($b, JSON_UNESCAPED_UNICODE));
    $z->close(); @unlink($sql);
    return $zp;
}
