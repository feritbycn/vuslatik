<?php
// ============================================================
// kurum_yedek.php — Kurum bazlı yedekleme / geri yükleme motoru
//   • Yedek: storage/kurum_yedek/k{id}/*.zip  (veri.sql + meta.json + uploads/k{id}/…)
//   • Geri yükleme önce "hazırlık" (geçici tablolara yükler, canlı veriye dokunmaz), sonra
//       - "degistir"  : önceki veriler silinir, yedek aynen yüklenir
//       - "birlestir" : mevcut verinin üzerine eklenir; aynı kayıt farklıysa kullanıcıya sorulur
//   • Her işlem pf_yedek_islemler'de izlenir; çalışan / bekleyen işlem durdurulabilir.
//   • Zamanlayıcı: yd_zamanli() (sayfa istekleriyle tetiklenir) ya da  php kurum_yedek.php  (cron)
// ============================================================
require_once __DIR__ . '/bakim_ortak.php';
define('YD_DIZIN', TB_KOK . '/storage/kurum_yedek');

function yd_sema($db)
{
    static $t = false; if ($t) return; $t = true;
    $o = $db->kurum; $db->kurum = 0;
    $s = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->query("CREATE TABLE IF NOT EXISTS pf_yedekler (id INT UNSIGNED NOT NULL AUTO_INCREMENT, kurum_id INT UNSIGNED NOT NULL, dosya VARCHAR(120) NOT NULL, boyut BIGINT NOT NULL DEFAULT 0, tur VARCHAR(12) NOT NULL DEFAULT 'elle', tablolar INT NOT NULL DEFAULT 0, kayit INT NOT NULL DEFAULT 0, aciklama VARCHAR(200) NOT NULL DEFAULT '', olusturma DATETIME NOT NULL, PRIMARY KEY (id), KEY ix_k (kurum_id, olusturma)) $s");
    $db->query("CREATE TABLE IF NOT EXISTS pf_yedek_islemler (id INT UNSIGNED NOT NULL AUTO_INCREMENT, tur VARCHAR(12) NOT NULL, kurum_id INT UNSIGNED NOT NULL DEFAULT 0, durum VARCHAR(12) NOT NULL DEFAULT 'calisiyor', mesaj TEXT NULL, veri MEDIUMTEXT NULL, iptal TINYINT(1) NOT NULL DEFAULT 0, baslangic DATETIME NOT NULL, bitis DATETIME NULL, PRIMARY KEY (id), KEY ix_d (durum)) $s");
    $db->query("CREATE TABLE IF NOT EXISTS pf_yedek_talepleri (id INT UNSIGNED NOT NULL AUTO_INCREMENT, kurum_id INT UNSIGNED NOT NULL, kullanici_id INT UNSIGNED NOT NULL DEFAULT 0, kullanici_ad VARCHAR(120) NOT NULL DEFAULT '', yedek_id INT UNSIGNED NULL, mesaj TEXT NULL, durum VARCHAR(12) NOT NULL DEFAULT 'bekliyor', yanit TEXT NULL, olusturma DATETIME NOT NULL, islem_zamani DATETIME NULL, PRIMARY KEY (id), KEY ix_k (kurum_id, durum)) $s");
    $db->kurum = $o;
}
/** $f'yi platform bağlamında ($db->kurum = 0) çalıştırır, sonra eski bağlama döner. */
function yd_k0($db, callable $f)
{
    $o = $db->kurum; $db->kurum = 0;
    try { return $f(); } finally { $db->kurum = $o; }
}
function yd_dizin($kid)
{
    $d = YD_DIZIN . '/k' . intval($kid);
    if (!is_dir($d)) { @mkdir($d, 0775, true); }
    foreach ([YD_DIZIN] as $x) if (is_dir($x) && !is_file($x . '/.htaccess')) @file_put_contents($x . '/.htaccess', "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    if (!is_dir($d) || !is_writable($d)) throw new RuntimeException('storage/kurum_yedek klasörü yazılabilir değil.');
    return $d;
}

/* ───────── işlem izleme / durdurma ───────── */
function yd_islem_ac($db, $tur, $kid, $mesaj = '')
{
    return yd_k0($db, function () use ($db, $tur, $kid, $mesaj) {
        $st = $db->prepare("INSERT INTO pf_yedek_islemler (tur, kurum_id, durum, mesaj, baslangic) VALUES (?, ?, 'calisiyor', ?, NOW())");
        $st->bind_param('sis', $tur, $kid, $mesaj); $st->execute(); return $db->son_id();
    });
}
function yd_islem_son($db, $id, $durum, $mesaj = '', $veri = null)
{
    yd_k0($db, function () use ($db, $id, $durum, $mesaj, $veri) {
        $son = in_array($durum, ['bitti', 'hata', 'iptal'], true) ? ', bitis = NOW()' : '';
        $st = $db->prepare("UPDATE pf_yedek_islemler SET durum = ?, mesaj = ?, veri = COALESCE(?, veri)$son WHERE id = ?");
        $st->bind_param('sssi', $durum, $mesaj, $veri, $id); $st->execute();
    });
}
function yd_iptalmi($db, $id)
{
    return yd_k0($db, function () use ($db, $id) { $q = $db->query("SELECT iptal FROM pf_yedek_islemler WHERE id = " . intval($id)); return $q && intval(($q->fetch_row()[0] ?? 0)) === 1; });
}
function yd_iptal_ayarla($db, $id)
{
    $GLOBALS['tb_iptal'] = $id ? function () use ($db, $id) { return yd_iptalmi($db, $id); } : null;
}
/** Bir işlemi durdurur: çalışıyorsa bayrak koyar, "hazır" (karar bekleyen) ise geçici tabloları siler. */
function yd_islem_durdur($db, $id)
{
    return yd_k0($db, function () use ($db, $id) {
        $id = intval($id); $q = $db->query("SELECT * FROM pf_yedek_islemler WHERE id = $id"); $r = $q ? $q->fetch_assoc() : null;
        if (!$r || !in_array($r['durum'], ['calisiyor', 'hazir'], true)) return false;
        $db->query("UPDATE pf_yedek_islemler SET iptal = 1 WHERE id = $id");
        if ($r['durum'] === 'hazir' || strtotime($r['baslangic']) < time() - 600) {      // karar bekliyor ya da takılı kalmış
            $v = json_decode((string)$r['veri'], true); if (!empty($v['stage'])) yd_stage_sil($db, $v['stage']);
            $db->query("UPDATE pf_yedek_islemler SET durum = 'iptal', mesaj = 'Kullanıcı tarafından durduruldu.', bitis = NOW() WHERE id = $id");
        }
        return true;
    });
}
function yd_islemler($db, $kid = 0)
{
    return yd_k0($db, function () use ($db, $kid) {
        $db->query("UPDATE pf_yedek_islemler SET durum = 'hata', mesaj = 'Zaman aşımı; işlem tamamlanamadı.', bitis = NOW() WHERE durum = 'calisiyor' AND baslangic < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $w = $kid ? 'WHERE kurum_id = ' . intval($kid) : '';
        $q = $db->query("SELECT id, tur, kurum_id, durum, mesaj, iptal, baslangic, bitis FROM pf_yedek_islemler $w ORDER BY id DESC LIMIT 12");
        $l = []; if ($q) while ($r = $q->fetch_assoc()) $l[] = $r; return $l;
    });
}

/* ───────── yedek alma ───────── */
function yd_kurum_tablolari($db, $kid)
{
    $l = []; foreach (tb_tablolar($db) as $t) if (preg_match('/^k' . intval($kid) . '_/', $t)) $l[] = $t; return $l;
}
function yd_yedekle($db, $kid, $tur = 'elle', $aciklama = '')
{
    if (!tb_zip_var()) throw new RuntimeException('PHP zip eklentisi (ZipArchive) kurulu değil.');
    @set_time_limit(0); yd_sema($db);
    $kid = intval($kid); $o = $db->kurum; $db->kurum = 0;
    $k = pf_kurum($db, $kid); if (!$k) { $db->kurum = $o; throw new RuntimeException('Kurum bulunamadı.'); }
    $isl = yd_islem_ac($db, 'yedek', $kid, $k['ad'] . ' yedekleniyor');
    yd_iptal_ayarla($db, $isl); $sql = null; $zp = null;
    try {
        $spec = []; $kayit = 0;
        foreach (yd_kurum_tablolari($db, $kid) as $t) { $spec[$t] = 'veri'; $q = $db->query("SELECT COUNT(*) FROM `$t`"); if ($q) $kayit += intval($q->fetch_row()[0]); }
        $sql = tb_gecici('.sql'); tb_dump($db, $sql, $spec);
        $zp = tb_gecici('.zip'); $z = new ZipArchive();
        if ($z->open($zp, ZipArchive::CREATE) !== true) throw new RuntimeException('Zip oluşturulamadı.');
        $z->addFile($sql, 'veri.sql');
        $b = tb_surum_bilgi();
        $z->addFromString('meta.json', json_encode(['kurum_id' => $kid, 'kod' => $k['kod'], 'ad' => $k['ad'], 'renk' => $k['renk'], 'telefon' => $k['telefon'], 'email' => $k['email'], 'zaman' => date('c'), 'surum' => $b['surum'] ?? '', 'tablolar' => count($spec), 'kayit' => $kayit, 'tur' => $tur], JSON_UNESCAPED_UNICODE));
        tb_klasor_zip($z, TB_KOK . '/uploads/k' . $kid, 'uploads/k' . $kid . '/');
        $z->close(); @unlink($sql); $sql = null;
        if (yd_iptalmi($db, $isl)) throw new RuntimeException('İşlem kullanıcı tarafından durduruldu.');
        $ad = date('Ymd_His') . '_' . $tur . '.zip'; $hedef = yd_dizin($kid) . '/' . $ad;
        if (!@rename($zp, $hedef)) { if (!@copy($zp, $hedef)) throw new RuntimeException('Yedek dosyası kaydedilemedi.'); @unlink($zp); }
        $zp = null; $boyut = filesize($hedef); $nt = count($spec);
        $st = $db->prepare("INSERT INTO pf_yedekler (kurum_id, dosya, boyut, tur, tablolar, kayit, aciklama, olusturma) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $st->bind_param('isisiis', $kid, $ad, $boyut, $tur, $nt, $kayit, $aciklama); $st->execute(); $yid = $db->son_id();
        yd_temizlik($db, $kid);
        yd_islem_son($db, $isl, 'bitti', 'Yedek alındı (' . $ad . ')');
        return $yid;
    } catch (Throwable $e) {
        if ($sql) @unlink($sql); if ($zp) @unlink($zp);
        yd_islem_son($db, $isl, yd_iptalmi($db, $isl) ? 'iptal' : 'hata', $e->getMessage());
        throw $e;
    } finally { yd_iptal_ayarla($db, 0); $db->kurum = $o; }
}
function yd_temizlik($db, $kid)
{
    $sak = max(1, intval(pf_ayar($db, 'yedek_saklama', '8')));
    foreach ([['otomatik', $sak], ['oncesi', 4]] as [$tur, $n]) {
        $q = $db->query("SELECT id, dosya FROM pf_yedekler WHERE kurum_id = " . intval($kid) . " AND tur = '$tur' ORDER BY olusturma DESC, id DESC LIMIT 100 OFFSET $n");
        $sil = []; if ($q) while ($r = $q->fetch_assoc()) $sil[] = $r;
        foreach ($sil as $r) { @unlink(YD_DIZIN . '/k' . intval($kid) . '/' . $r['dosya']); $db->query("DELETE FROM pf_yedekler WHERE id = " . intval($r['id'])); }
    }
}
function yd_liste($db, $kid)
{
    yd_sema($db);
    return yd_k0($db, function () use ($db, $kid) {
        $q = $db->query("SELECT id, boyut, tur, tablolar, kayit, aciklama, olusturma FROM pf_yedekler WHERE kurum_id = " . intval($kid) . " ORDER BY olusturma DESC, id DESC LIMIT 60");
        $l = []; if ($q) while ($r = $q->fetch_assoc()) { $r['id'] = intval($r['id']); $r['boyut'] = intval($r['boyut']); $l[] = $r; } return $l;
    });
}
function yd_kayit($db, $id, $kid = null)
{
    return yd_k0($db, function () use ($db, $id, $kid) {
        $q = $db->query("SELECT * FROM pf_yedekler WHERE id = " . intval($id) . ($kid !== null ? " AND kurum_id = " . intval($kid) : ''));
        $r = $q ? $q->fetch_assoc() : null; if (!$r) return null;
        $r['yol'] = YD_DIZIN . '/k' . intval($r['kurum_id']) . '/' . basename($r['dosya']); return is_file($r['yol']) ? $r : null;
    });
}
function yd_sil($db, $id, $kid)
{
    $r = yd_kayit($db, $id, $kid); if (!$r) return false;
    @unlink($r['yol']); yd_k0($db, function () use ($db, $id) { $db->query("DELETE FROM pf_yedekler WHERE id = " . intval($id)); }); return true;
}
function yd_indir($db, $id, $kid = null)
{
    $r = yd_kayit($db, $id, $kid); if (!$r) { http_response_code(404); echo 'Yedek bulunamadı.'; exit; }
    $k = yd_k0($db, function () use ($db, $r) { return pf_kurum($db, $r['kurum_id']); });
    $ad = preg_replace('/[^a-z0-9_-]/', '', strtolower($k['kod'] ?? 'kurum')) . '_' . substr($r['dosya'], 0, 15) . '.zip';
    header('Content-Type: application/zip'); header('Content-Length: ' . filesize($r['yol'])); header('Content-Disposition: attachment; filename="' . $ad . '"'); header('Cache-Control: no-store');
    readfile($r['yol']); exit;
}
/** Yüklenen yedek zip'ini kuruma ait yedek olarak kaydeder (tur = ice). */
function yd_ice_al($db, $kid, $tmpZip, $aciklama = '')
{
    yd_sema($db); $kid = intval($kid);
    $m = yd_zip_oku($tmpZip); $m['z']->close();
    $ad = date('Ymd_His') . '_ice.zip'; $hedef = yd_dizin($kid) . '/' . $ad;
    if (!@copy($tmpZip, $hedef)) throw new RuntimeException('Yedek kaydedilemedi.');
    $boyut = filesize($hedef); $nt = intval($m['meta']['tablolar'] ?? 0); $ky = intval($m['meta']['kayit'] ?? 0); $ac = mb_substr($aciklama !== '' ? $aciklama : 'İçe aktarılan yedek' . (!empty($m['meta']['zaman']) ? ' (' . substr($m['meta']['zaman'], 0, 10) . ')' : ''), 0, 190, 'UTF-8');
    yd_k0($db, function () use ($db, $kid, $ad, $boyut, $nt, $ky, $ac) {
        $st = $db->prepare("INSERT INTO pf_yedekler (kurum_id, dosya, boyut, tur, tablolar, kayit, aciklama, olusturma) VALUES (?, ?, ?, 'ice', ?, ?, ?, NOW())");
        $st->bind_param('isiiis', $kid, $ad, $boyut, $nt, $ky, $ac); $st->execute();
    });
    return $db->son_id();
}

/* ───────── zip okuma / SQL yükleme ───────── */
function yd_zip_oku($zipYol)
{
    if (!tb_zip_var()) throw new RuntimeException('PHP zip eklentisi (ZipArchive) kurulu değil.');
    $z = new ZipArchive(); if ($z->open($zipYol) !== true) throw new RuntimeException('Yedek dosyası açılamadı (geçerli bir zip değil).');
    $sqlAd = $z->locateName('veri.sql') !== false ? 'veri.sql' : ($z->locateName('kurum_paketi/veri.sql') !== false ? 'kurum_paketi/veri.sql' : null);
    if (!$sqlAd) { $z->close(); throw new RuntimeException('Bu dosya bir kurum yedeği değil (veri.sql bulunamadı).'); }
    $mj = $z->getFromName('meta.json'); if ($mj === false) $mj = $z->getFromName('kurum_paketi/kurum.json');
    $meta = json_decode((string)$mj, true);
    if (!is_array($meta) || empty($meta['kurum_id'])) { $z->close(); throw new RuntimeException('Yedeğin kurum bilgisi (meta) okunamadı.'); }
    return ['z' => $z, 'sql' => $sqlAd, 'meta' => $meta];
}
function yd_yukle_sql($db, $sqlYol, $eski, $hedefOnek)
{
    $db->kurum = 0; $f = fopen($sqlYol, 'rb'); $n = 0; $satir = 0; $tablolar = [];
    $on = 'k' . intval($eski) . '_';
    while (($l = fgets($f)) !== false) {
        $l = rtrim($l, "\r\n"); $satir++;
        if ($l === '' || strncmp($l, '--', 2) === 0) continue;
        if (preg_match('/^SET (NAMES|FOREIGN_KEY_CHECKS|SQL_MODE)\b/i', $l)) { $db->query($l); continue; }
        if (!preg_match('/^(DROP TABLE IF EXISTS |CREATE TABLE |INSERT INTO )`([a-z0-9_]+)`/i', $l, $m) || !preg_match('/^(DROP TABLE IF EXISTS `[a-z0-9_]+`;|CREATE TABLE `[a-z0-9_]+` \(.*|INSERT INTO `[a-z0-9_]+` \(`[^)]*`\) VALUES \(.*;)$/s', $l)) { fclose($f); throw new RuntimeException("Yedekte izin verilmeyen bir deyim var (satır $satir)."); }
        if (strncmp($m[2], $on, strlen($on)) !== 0) { if (!preg_match('/^(pf_|k[0-9]+_)/', $m[2])) { fclose($f); throw new RuntimeException("Yedekte tanınmayan tablo: {$m[2]}"); } continue; }
        $kisa = substr($m[2], strlen($on)); $yeni = $hedefOnek . $kisa;
        $l = $m[1] . '`' . $yeni . '`' . substr($l, strlen($m[0]));
        if (strncmp($m[1], 'DROP', 4) === 0) continue;                                  // geçici tabloya DROP gerekmez
        if (strncmp($m[1], 'CREATE', 6) === 0) {
            if (!empty($GLOBALS['tb_iptal']) && $GLOBALS['tb_iptal']()) { fclose($f); throw new RuntimeException('İşlem kullanıcı tarafından durduruldu.'); }
            $db->query("DROP TABLE IF EXISTS `$yeni`"); $tablolar[] = $kisa;
        }
        if (!$db->query($l)) { $h = $db->hata(); fclose($f); throw new RuntimeException("SQL hatası (satır $satir): $h"); }
    }
    fclose($f);
    return $tablolar;
}
function yd_stage_sil($db, $onek)
{
    if (!preg_match('/^zy[a-z0-9]{6}_$/', $onek)) return;
    $o = $db->kurum; $db->kurum = 0;
    $q = $db->query("SHOW TABLES LIKE '" . $onek . "%'"); $l = []; if ($q) while ($r = $q->fetch_row()) $l[] = $r[0];
    foreach ($l as $t) $db->query("DROP TABLE IF EXISTS `$t`");
    $db->kurum = $o;
}
function yd_dosya_ok($ad) { return $ad !== '' && !preg_match('~(^|/)\.\.(/|$)~', $ad) && preg_match('~^[A-Za-z0-9._/@ +()-]+$~', $ad); }
/** Yedekteki uploads/k{eski}/… dosyalarını uploads/k{kid}/…'e açar. $ustune=false ise var olan dosyaya dokunmaz. */
function yd_dosyalar($z, $eski, $kid, $ustune)
{
    $on = 'uploads/k' . intval($eski) . '/'; $n = 0; $hedefKok = TB_KOK . '/uploads/k' . intval($kid) . '/';
    for ($i = 0; $i < $z->numFiles; $i++) {
        $ad = $z->getNameIndex($i);
        if (strncmp($ad, $on, strlen($on)) !== 0 || substr($ad, -1) === '/') continue;
        $alt = substr($ad, strlen($on)); if (!yd_dosya_ok($alt)) continue;
        if (!in_array(strtolower(pathinfo($alt, PATHINFO_EXTENSION)), TB_YUKLEME_UZANTI, true)) continue;
        $h = $hedefKok . $alt; if (is_file($h) && !$ustune) continue;
        if (!is_dir(dirname($h))) @mkdir(dirname($h), 0775, true);
        $in = $z->getStream($ad); if (!$in) continue; $out = fopen($h, 'wb'); stream_copy_to_stream($in, $out); fclose($out); fclose($in); $n++;
    }
    return $n;
}
function yd_klasor_sil($d)
{
    if (!is_dir($d)) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $x) $x->isDir() ? @rmdir($x->getPathname()) : @unlink($x->getPathname());
    @rmdir($d);
}

/* ───────── geri yükleme: hazırlık → (tam | birleştir) ───────── */
/** Yedeği geçici tablolara yükler; canlı veriye dokunmaz. Dönüş: işlem kimliği + özet */
/** Kurumun veri tabanı vekil sunucuya taşınmışsa yedekten geri yükleme yapılamaz (aşama tabloları ana veri tabanında oluşur) */
function yd_uzak_db($db, $kid)
{
    $q = @$db->query("SELECT aktif FROM pf_vekil_db WHERE kurum_id = " . intval($kid)); $r = $q ? $q->fetch_row() : null;
    return $r && intval($r[0]) === 1;
}
function yd_hazirla($db, $kid, $zipYol, $mod)
{
    if (yd_uzak_db($db, $kid)) throw new RuntimeException('Bu kurumun veri tabanı vekil sunucuda; yedekten geri yükleme için önce veri tabanını ana sunucuya geri alın (Platform > Kurum > Vekil sunucu > Veri tabanı).');
    @set_time_limit(0); yd_sema($db); $kid = intval($kid); $o = $db->kurum;
    $isl = yd_islem_ac($db, 'geri', $kid, 'Yedek okunuyor'); yd_iptal_ayarla($db, $isl); $stage = 'zy' . substr(bin2hex(random_bytes(4)), 0, 6) . '_'; $sql = null;
    try {
        $p = yd_zip_oku($zipYol); $z = $p['z']; $meta = $p['meta']; $eski = intval($meta['kurum_id']);
        $sql = tb_gecici('.sql'); $in = $z->getStream($p['sql']); $out = fopen($sql, 'wb'); stream_copy_to_stream($in, $out); fclose($in); fclose($out); $z->close();
        $tablolar = yd_yukle_sql($db, $sql, $eski, $stage); @unlink($sql); $sql = null;
        if (!$tablolar) throw new RuntimeException('Yedekte bu kuruma ait tablo bulunamadı.');
        $veri = ['stage' => $stage, 'zip' => $zipYol, 'eski' => $eski, 'hedef' => $kid, 'mod' => $mod, 'tablolar' => $tablolar, 'meta' => $meta];
        $sonuc = ['islem_id' => $isl, 'meta' => ['ad' => $meta['ad'] ?? '', 'zaman' => $meta['zaman'] ?? '', 'kayit' => $meta['kayit'] ?? 0], 'tablo_sayisi' => count($tablolar)];
        if ($mod === 'birlestir') $sonuc['analiz'] = yd_analiz($db, $kid, $stage, $tablolar);
        yd_islem_son($db, $isl, 'hazir', 'Karar bekleniyor', json_encode($veri, JSON_UNESCAPED_UNICODE));
        return $sonuc;
    } catch (Throwable $e) {
        if ($sql) @unlink($sql); yd_stage_sil($db, $stage);
        yd_islem_son($db, $isl, yd_iptalmi($db, $isl) ? 'iptal' : 'hata', $e->getMessage()); throw $e;
    } finally { yd_iptal_ayarla($db, 0); $db->kurum = $o; }
}
function yd_sutunlar($db, $t)
{
    $c = []; $q = $db->query("SHOW COLUMNS FROM `$t`"); if ($q) while ($r = $q->fetch_assoc()) $c[$r['Field']] = $r['Key'] === 'PRI'; return $c;
}
/** Birleştirme çözümlemesi: tablo başına yeni / aynı / farklı kayıt sayıları */
function yd_analiz($db, $kid, $stage, $tablolar)
{
    $db->kurum = 0; $liste = []; $toplam = ['yeni' => 0, 'ayni' => 0, 'farkli' => 0];
    foreach ($tablolar as $kisa) {
        $s = $stage . $kisa; $c = 'k' . $kid . '_' . $kisa;
        if (!$db->query("SELECT 1 FROM `$c` LIMIT 1")) { $q = $db->query("SELECT COUNT(*) FROM `$s`"); $liste[] = ['tablo' => $kisa, 'yeni' => $q ? intval($q->fetch_row()[0]) : 0, 'ayni' => 0, 'farkli' => 0]; continue; }
        $sc = yd_sutunlar($db, $s); $cc = yd_sutunlar($db, $c);
        $ort = array_values(array_filter(array_keys($sc), function ($x) use ($cc) { return isset($cc[$x]); }));
        $pk = array_values(array_filter($ort, function ($x) use ($cc) { return $cc[$x]; }));
        $say = intval(($db->query("SELECT COUNT(*) FROM `$s`")->fetch_row()[0] ?? 0));
        if (!$say) continue;
        if (!$pk) { $liste[] = ['tablo' => $kisa, 'yeni' => $say, 'ayni' => 0, 'farkli' => 0, 'not' => 'anahtarsız']; $toplam['yeni'] += $say; continue; }
        $on = implode(' AND ', array_map(function ($x) { return "c.`$x` <=> s.`$x`"; }, $pk));
        $esit = implode(' AND ', array_map(function ($x) { return "c.`$x` <=> s.`$x`"; }, array_diff($ort, $pk))) ?: '1=1';
        $ayni = intval($db->query("SELECT COUNT(*) FROM `$s` s JOIN `$c` c ON $on WHERE $esit")->fetch_row()[0] ?? 0);
        $var = intval($db->query("SELECT COUNT(*) FROM `$s` s JOIN `$c` c ON $on")->fetch_row()[0] ?? 0);
        $yeni = $say - $var; $fark = $var - $ayni;
        $satir = ['tablo' => $kisa, 'yeni' => $yeni, 'ayni' => $ayni, 'farkli' => $fark];
        if ($fark > 0) {                                              // kullanıcıya örnek göster
            $ad = isset($sc['ad_soyad']) ? 'ad_soyad' : (isset($sc['kullanici_adi']) ? 'kullanici_adi' : (isset($sc['baslik']) ? 'baslik' : (isset($sc['ad']) ? 'ad' : $pk[0])));
            $q = $db->query("SELECT s.`$ad` FROM `$s` s JOIN `$c` c ON $on WHERE NOT ($esit) LIMIT 6"); $ors = []; if ($q) while ($r = $q->fetch_row()) $ors[] = (string)$r[0]; $satir['ornek'] = $ors;
        }
        if ($yeni || $ayni || $fark) $liste[] = $satir;
        $toplam['yeni'] += $yeni; $toplam['ayni'] += $ayni; $toplam['farkli'] += $fark;
    }
    return ['tablolar' => $liste, 'toplam' => $toplam];
}
function yd_hazir_islem($db, $islemId)
{
    $r = yd_k0($db, function () use ($db, $islemId) { $q = $db->query("SELECT * FROM pf_yedek_islemler WHERE id = " . intval($islemId) . " AND durum = 'hazir'"); return $q ? $q->fetch_assoc() : null; });
    if (!$r) throw new RuntimeException('Bekleyen geri yükleme işlemi bulunamadı (durdurulmuş ya da süresi dolmuş olabilir).');
    $v = json_decode((string)$r['veri'], true); if (!is_array($v) || empty($v['stage'])) throw new RuntimeException('İşlem verisi bozuk.');
    return [$r, $v];
}
/** Hazırlanmış yedeği uygular. $mod: degistir | birlestir ; $cakisma: degistir | koru */
function yd_uygula($db, $islemId, $mod, $cakisma = 'koru')
{
    @set_time_limit(0); yd_sema($db); $o = $db->kurum; $db->kurum = 0;
    [$r, $v] = yd_hazir_islem($db, $islemId); $kid = intval($v['hedef']); $stage = $v['stage']; $eski = intval($v['eski']);
    if (!yd_k0($db, function () use ($db, $kid) { return pf_kurum($db, $kid); })) throw new RuntimeException('Hedef kurum bulunamadı.');
    yd_islem_son($db, $islemId, 'calisiyor', 'Uygulanıyor');
    try {
        yd_yedekle($db, $kid, 'oncesi', 'Geri yüklemeden önce otomatik alındı');       // güvenlik yedeği
        $db->kurum = 0; $rapor = [];
        $p = yd_zip_oku($v['zip']); $z = $p['z'];
        if ($mod === 'degistir') {
            $canli = yd_kurum_tablolari($db, $kid);
            foreach ($canli as $t) $db->query("DROP TABLE IF EXISTS `$t`");
            foreach ($v['tablolar'] as $kisa) if (!$db->query("RENAME TABLE `{$stage}{$kisa}` TO `k{$kid}_{$kisa}`")) throw new RuntimeException('Tablo yerleştirilemedi: ' . $db->hata());
            yd_klasor_sil(TB_KOK . '/uploads/k' . $kid);
            $rapor['dosya'] = yd_dosyalar($z, $eski, $kid, true);
            $rapor['mod'] = 'degistir';
        } else {
            $rapor = yd_birlestir($db, $kid, $stage, $v['tablolar'], $cakisma);
            $rapor['dosya'] = yd_dosyalar($z, $eski, $kid, $cakisma === 'degistir');
            $rapor['mod'] = 'birlestir';
        }
        $z->close(); yd_stage_sil($db, $stage);
        $db->kurum = $kid; try { kurum_sema($db); } catch (Throwable $e) {} $db->kurum = 0;
        $rapor['yeniden_adlandirilan'] = yd_cakisma_gider($db, $kid);
        yd_yetkili_duzelt($db, $kid);
        yd_islem_son($db, $islemId, 'bitti', 'Geri yükleme tamamlandı', json_encode($rapor, JSON_UNESCAPED_UNICODE));
        pf_log($db, 'yedek_geri_yukle', $mod . ' · ' . ($v['meta']['zaman'] ?? ''), $kid);
        return $rapor;
    } catch (Throwable $e) {
        $db->kurum = 0; yd_stage_sil($db, $stage);
        yd_islem_son($db, $islemId, 'hata', $e->getMessage()); throw $e;
    } finally { $db->kurum = $o; }
}
/** Mevcut verinin üzerine birleştirir. */
function yd_birlestir($db, $kid, $stage, $tablolar, $cakisma)
{
    $db->kurum = 0; $rapor = ['eklenen' => 0, 'guncellenen' => 0, 'atlanan' => 0];
    foreach ($tablolar as $kisa) {
        $s = $stage . $kisa; $c = 'k' . $kid . '_' . $kisa;
        if (!$db->query("SELECT 1 FROM `$c` LIMIT 1")) {                                  // canlıda tablo yok → olduğu gibi taşı
            $db->query("RENAME TABLE `$s` TO `$c`"); continue;
        }
        $sc = yd_sutunlar($db, $s); $cc = yd_sutunlar($db, $c);
        $ort = array_values(array_filter(array_keys($sc), function ($x) use ($cc) { return isset($cc[$x]); }));
        $pk = array_values(array_filter($ort, function ($x) use ($cc) { return $cc[$x]; }));
        $kol = '`' . implode('`,`', $ort) . '`';
        $skol = implode(',', array_map(function ($x) { return "s.`$x`"; }, $ort));
        if ($pk) {
            $on = implode(' AND ', array_map(function ($x) { return "c.`$x` <=> s.`$x`"; }, $pk));
            $nonpk = array_diff($ort, $pk);
            if ($cakisma === 'degistir' && $nonpk) {
                $set = implode(', ', array_map(function ($x) { return "c.`$x` = s.`$x`"; }, $nonpk));
                $esit = implode(' AND ', array_map(function ($x) { return "c.`$x` <=> s.`$x`"; }, $nonpk));
                $db->query("UPDATE `$c` c JOIN `$s` s ON $on SET $set WHERE NOT ($esit)"); $rapor['guncellenen'] += max(0, $db->etkilenen());
            }
            $db->query("INSERT IGNORE INTO `$c` ($kol) SELECT $skol FROM `$s` s LEFT JOIN `$c` c ON $on WHERE " . implode(' AND ', array_map(function ($x) { return "c.`$x` IS NULL"; }, $pk)));
            $e = max(0, $db->etkilenen()); $rapor['eklenen'] += $e;
            $say = intval($db->query("SELECT COUNT(*) FROM `$s`")->fetch_row()[0] ?? 0);
            $rapor['atlanan'] += max(0, $say - $e - intval($db->query("SELECT COUNT(*) FROM `$s` s JOIN `$c` c ON $on")->fetch_row()[0] ?? 0));
        } else {
            $db->query("INSERT INTO `$c` ($kol) SELECT $skol FROM `$s` s"); $rapor['eklenen'] += max(0, $db->etkilenen());
        }
    }
    return $rapor;
}
/** Geri yüklenen kullanıcılardan, başka kurumlarda zaten kullanılan ad / e-postaları düzeltir. */
function yd_cakisma_gider($db, $kid)
{
    $db->kurum = 0; $kod = yd_k0($db, function () use ($db, $kid) { return (pf_kurum($db, $kid)['kod'] ?? 'k' . $kid); });
    $t = "k{$kid}_kullanicilar"; $q = $db->query("SELECT id, kullanici_adi, email FROM `$t`"); if (!$q) return 0;
    $satir = []; while ($r = $q->fetch_assoc()) $satir[] = $r; $n = 0;
    foreach ($satir as $r) {
        $c = pf_kullanici_cakisma($db, $r['kullanici_adi'], $r['email'], $kid, intval($r['id'])); if ($c === '') continue;
        $yeniAd = $r['kullanici_adi']; $yeniMail = $r['email'];
        if (stripos($c, 'kullanıcı adı') !== false) { $yeniAd = substr($r['kullanici_adi'] . '.' . preg_replace('/[^a-z0-9]/', '', strtolower($kod)), 0, 58); $i = 1; while (pf_kullanici_cakisma($db, $yeniAd, '', $kid, intval($r['id'])) !== '' || $db->query("SELECT 1 FROM `$t` WHERE kullanici_adi = '" . $db->real_escape_string($yeniAd) . "' AND id <> " . intval($r['id']))->num_rows) $yeniAd = substr($r['kullanici_adi'], 0, 54) . '.' . (++$i); }
        else $yeniMail = '';
        if (stripos($c, 'kullanıcı adı') !== false && pf_kullanici_cakisma($db, $yeniAd, $r['email'], $kid, intval($r['id'])) !== '') $yeniMail = '';
        $st = $db->prepare("UPDATE `$t` SET kullanici_adi = ?, email = ? WHERE id = ?"); $iid = intval($r['id']); $st->bind_param('ssi', $yeniAd, $yeniMail, $iid); $st->execute(); $n++;
    }
    return $n;
}
function yd_yetkili_duzelt($db, $kid)
{
    $db->kurum = 0; $q = $db->query("SELECT yetkili_id FROM pf_kurumlar WHERE id = " . intval($kid)); $y = $q ? intval($q->fetch_row()[0]) : 0;
    $ok = $y ? ($db->query("SELECT 1 FROM `k{$kid}_kullanicilar` WHERE id = $y AND aktif = 1")->num_rows ?? 0) : 0;
    if ($ok) return;
    $q = $db->query("SELECT id, ad_soyad FROM `k{$kid}_kullanicilar` WHERE rol = 'kurucu' AND aktif = 1 ORDER BY id LIMIT 1"); $r = $q ? $q->fetch_assoc() : null;
    if ($r) $db->query("UPDATE pf_kurumlar SET yetkili_id = " . intval($r['id']) . ", yetkili_ad = '" . $db->real_escape_string($r['ad_soyad']) . "' WHERE id = " . intval($kid));
}
/** Yedekten yeni kurum oluşturur (kurum geri döndüğünde). */
function yd_kurum_yedekten($db, $zipYol, $ad = '', $kod = '')
{
    yd_sema($db); $db->kurum = 0;
    $p = yd_zip_oku($zipYol); $meta = $p['meta']; $p['z']->close();
    $ad = trim($ad) !== '' ? trim($ad) : (string)($meta['ad'] ?? 'Geri yüklenen kurum');
    $baz = pf_kod_uret(trim($kod) !== '' ? $kod : (string)($meta['kod'] ?? $ad)); $kod = $baz; $i = 1;
    while (pf_kurum_kod($db, $kod)) $kod = $baz . '-' . (++$i);
    $renk = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($meta['renk'] ?? '')) ? $meta['renk'] : '#6366f1'; $tel = (string)($meta['telefon'] ?? ''); $mail = (string)($meta['email'] ?? '');
    $st = $db->prepare("INSERT INTO pf_kurumlar (ad, kod, renk, telefon, email, lisans_tur, olusturma) VALUES (?, ?, ?, ?, ?, 'sinirsiz', NOW())");
    $st->bind_param('sssss', $ad, $kod, $renk, $tel, $mail);
    if (!$st->execute()) throw new RuntimeException('Kurum kaydedilemedi: ' . $db->hata());
    $kid = $db->son_id();
    try {
        $r = yd_hazirla($db, $kid, $zipYol, 'degistir');
        $rapor = yd_uygula($db, $r['islem_id'], 'degistir');
    } catch (Throwable $e) {
        $db->kurum = 0; $db->query("DELETE FROM pf_kurumlar WHERE id = $kid"); pf_kurum_tablolarini_sil($db, $kid); yd_klasor_sil(TB_KOK . '/uploads/k' . $kid);
        throw $e;
    }
    pf_log($db, 'kurum_yedekten', "$ad ($kod)", $kid);
    return ['id' => $kid, 'kod' => $kod, 'ad' => $ad, 'rapor' => $rapor];
}

/* ───────── zamanlayıcı ───────── */
function yd_son_yedek($db, $kid)
{
    return yd_k0($db, function () use ($db, $kid) { $q = $db->query("SELECT MAX(olusturma) FROM pf_yedekler WHERE kurum_id = " . intval($kid) . " AND tur IN ('otomatik','elle')"); return $q ? $q->fetch_row()[0] : null; });
}
function yd_zamanli($db, $zorla = false)
{
    $o = $db->kurum; $db->kurum = 0;
    try {
        yd_sema($db);
        $gun = intval(pf_ayar($db, 'yedek_aralik', '7')); if ($gun <= 0) return 0;
        if (!$zorla && time() - intval(pf_ayar($db, 'yedek_son_kontrol', '0')) < 900) return 0;
        $kilit = $db->query("SELECT GET_LOCK('tb_kurum_yedek', 0)"); if (!$kilit || intval($kilit->fetch_row()[0]) !== 1) return 0;
        pf_ayar_yaz($db, 'yedek_son_kontrol', (string)time());
        $yapilan = 0;
        $db->query("UPDATE pf_yedek_islemler SET durum = 'hata', mesaj = 'Zaman aşımı; işlem tamamlanamadı.', bitis = NOW() WHERE durum = 'calisiyor' AND baslangic < DATE_SUB(NOW(), INTERVAL 30 MINUTE)");
        $q = $db->query("SELECT id, veri FROM pf_yedek_islemler WHERE durum = 'hazir' AND baslangic < DATE_SUB(NOW(), INTERVAL 3 HOUR)");
        $eski = []; if ($q) while ($r = $q->fetch_assoc()) $eski[] = $r;
        foreach ($eski as $r) { $v = json_decode((string)$r['veri'], true); if (!empty($v['stage'])) yd_stage_sil($db, $v['stage']); $db->query("UPDATE pf_yedek_islemler SET durum = 'iptal', mesaj = 'Süre doldu; geçici veriler silindi.', bitis = NOW() WHERE id = " . intval($r['id'])); }
        $q = $db->query("SELECT id FROM pf_kurumlar WHERE durum = 'aktif' ORDER BY id"); $ids = []; if ($q) while ($r = $q->fetch_row()) $ids[] = intval($r[0]);
        foreach ($ids as $kid) {
            if ($yapilan >= 3) break;
            $son = yd_son_yedek($db, $kid);
            if ($son && strtotime($son) + $gun * 86400 - 1800 > time()) continue;
            try { yd_yedekle($db, $kid, 'otomatik'); $yapilan++; } catch (Throwable $e) { $db->kurum = 0; }
        }
        $db->query("SELECT RELEASE_LOCK('tb_kurum_yedek')");
        return $yapilan;
    } finally { $db->kurum = $o; }
}
/** İstek bittikten sonra zamanlayıcıyı çalıştırır (kullanıcı beklemez). */
function yd_zamanli_arkada($db)
{
    if (!function_exists('ik_sonra')) require_once __DIR__ . '/ik_kanal.php';
    if (!function_exists('ik_sonra')) return;
    ik_sonra(function () use ($db) { @set_time_limit(300); yd_zamanli($db); });
}

/* ───────── yedek geri yükleme talepleri ───────── */
function yd_talep_ac($db, $kid, $uid, $uad, $yedekId, $mesaj)
{
    yd_sema($db);
    $kurum = yd_k0($db, function () use ($db, $kid) { return pf_kurum($db, $kid); });
    $id = yd_k0($db, function () use ($db, $kid, $uid, $uad, $yedekId, $mesaj) {
        $q = $db->query("SELECT COUNT(*) FROM pf_yedek_talepleri WHERE kurum_id = " . intval($kid) . " AND durum = 'bekliyor'");
        if ($q && intval($q->fetch_row()[0]) >= 3) throw new RuntimeException('Bekleyen en fazla 3 talebiniz olabilir; önce birini iptal edin ya da yanıtını bekleyin.');
        $yid = $yedekId ? intval($yedekId) : null; $mesaj = mb_substr($mesaj, 0, 1500, 'UTF-8');
        $st = $db->prepare("INSERT INTO pf_yedek_talepleri (kurum_id, kullanici_id, kullanici_ad, yedek_id, mesaj, olusturma) VALUES (?, ?, ?, ?, ?, NOW())");
        $st->bind_param('iisis', $kid, $uid, $uad, $yid, $mesaj); $st->execute(); return $db->son_id();
    });
    $tarih = '—'; if ($yedekId) { $r = yd_k0($db, function () use ($db, $yedekId) { $q = $db->query("SELECT olusturma FROM pf_yedekler WHERE id = " . intval($yedekId)); return $q ? $q->fetch_row() : null; }); if ($r) $tarih = date('d.m.Y H:i', strtotime($r[0])); }
    $hedefMail = yd_k0($db, function () use ($db) { return trim(pf_ayar($db, 'iletisim_eposta', '')) ?: trim(pf_ayar($db, 'destek_mail', '')); });
    $hedefTel = yd_k0($db, function () use ($db) { return trim(pf_ayar($db, 'iletisim_telefon', '')); });
    $konu = 'Yedek geri yükleme talebi — ' . ($kurum['ad'] ?? '');
    $html = '<p><b>' . htmlspecialchars($kurum['ad'] ?? '', ENT_QUOTES, 'UTF-8') . '</b> kurumundan <b>' . htmlspecialchars($uad, ENT_QUOTES, 'UTF-8') . '</b> bir yedek geri yükleme talebi oluşturdu.</p><p>İstenen yedek tarihi: <b>' . htmlspecialchars($tarih, ENT_QUOTES, 'UTF-8') . '</b></p>' . ($mesaj !== '' ? '<p>Not: ' . nl2br(htmlspecialchars($mesaj, ENT_QUOTES, 'UTF-8')) . '</p>' : '') . '<p>Platform paneli → Yedekler bölümünden inceleyebilirsiniz.</p>';
    $govde = function_exists('ik_mail_sablon') ? ik_mail_sablon($konu, $html, '', 'Aç', $db) : $html;
    $kod = $kurum['kod'] ?? '';
    $db->kurum = intval($kid);                       // kurumun kendi e-posta / SMS kanalı kullanılır
    if (function_exists('ik_sonra')) ik_sonra(function () use ($db, $kid, $hedefMail, $hedefTel, $konu, $govde, $uad, $tarih, $kod) {
        $db->kurum = intval($kid);
        try { if ($hedefMail !== '' && function_exists('ik_mail_gonder')) ik_mail_gonder($db, $hedefMail, $konu, $govde); } catch (Throwable $e) {}
        try { if ($hedefTel !== '' && function_exists('ik_sms_hazir') && ik_sms_hazir($db)) ik_sms_gonder($db, $hedefTel, "Yedek geri yükleme talebi: $kod / $uad (yedek: $tarih)"); } catch (Throwable $e) {}
    });
    return $id;
}
function yd_talepler($db, $kid = 0, $sadeceBekleyen = false)
{
    yd_sema($db);
    return yd_k0($db, function () use ($db, $kid, $sadeceBekleyen) {
        $w = []; if ($kid) $w[] = 't.kurum_id = ' . intval($kid); if ($sadeceBekleyen) $w[] = "t.durum = 'bekliyor'";
        $q = $db->query("SELECT t.id, t.kurum_id, k.ad AS kurum, t.kullanici_ad, t.yedek_id, y.olusturma AS yedek_tarih, t.mesaj, t.durum, t.yanit, t.olusturma FROM pf_yedek_talepleri t LEFT JOIN pf_kurumlar k ON k.id = t.kurum_id LEFT JOIN pf_yedekler y ON y.id = t.yedek_id" . ($w ? ' WHERE ' . implode(' AND ', $w) : '') . " ORDER BY (t.durum = 'bekliyor') DESC, t.id DESC LIMIT 40");
        $l = []; if ($q) while ($r = $q->fetch_assoc()) { $r['id'] = intval($r['id']); $r['kurum_id'] = intval($r['kurum_id']); $l[] = $r; } return $l;
    });
}

/* ───────── ortak API (platform paneli ve destek girişi kullanır) ───────── */
/** Yedek uç noktaları. Bilinmeyen işlemde null döner. $kid: hedef kurum. Yanıt dizisi döner (ok/hata). */
function yd_api($db, $islem, $kid)
{
    $o = $db->kurum; $kid = intval($kid); $G = $_POST + $_GET;
    if (strncmp($islem, 'yedek_', 6) !== 0) return null;
    $db->kurum = 0;
    try {
        yd_sema($db);
        switch ($islem) {
            case 'yedek_liste':
                return ['ok' => true, 'yedekler' => yd_liste($db, $kid), 'islemler' => yd_islemler($db, $kid), 'son' => yd_son_yedek($db, $kid), 'talepler' => yd_talepler($db, $kid)];
            case 'yedek_al':
                return ['ok' => true, 'id' => yd_yedekle($db, $kid, 'elle', mb_substr(trim((string)($G['aciklama'] ?? '')), 0, 190, 'UTF-8'))];
            case 'yedek_sil':
                return yd_sil($db, intval($G['id'] ?? 0), $kid) ? ['ok' => true] : ['ok' => false, 'hata' => 'Yedek bulunamadı.'];
            case 'yedek_ice_al':
                if (empty($_FILES['dosya']) || $_FILES['dosya']['error'] !== UPLOAD_ERR_OK) return ['ok' => false, 'hata' => 'Dosya yüklenemedi (boyut sınırını aşmış olabilir: upload_max_filesize).'];
                return ['ok' => true, 'id' => yd_ice_al($db, $kid, $_FILES['dosya']['tmp_name'])];
            case 'yedek_hazirla':
                $r = yd_kayit($db, intval($G['id'] ?? 0), $kid); if (!$r) return ['ok' => false, 'hata' => 'Yedek bulunamadı.'];
                $mod = ($G['mod'] ?? '') === 'birlestir' ? 'birlestir' : 'degistir';
                return ['ok' => true] + yd_hazirla($db, $kid, $r['yol'], $mod);
            case 'yedek_uygula':
                [$ri, $v] = yd_hazir_islem($db, intval($G['islem_id'] ?? 0));
                if (intval($v['hedef']) !== $kid) return ['ok' => false, 'hata' => 'İşlem bu kuruma ait değil.'];
                $mod = ($G['mod'] ?? '') === 'birlestir' ? 'birlestir' : 'degistir';
                return ['ok' => true, 'rapor' => yd_uygula($db, $ri['id'], $mod, ($G['cakisma'] ?? '') === 'degistir' ? 'degistir' : 'koru')];
            case 'yedek_islem_durdur':
                $q = $db->query("SELECT kurum_id FROM pf_yedek_islemler WHERE id = " . intval($G['islem_id'] ?? 0)); $r = $q ? $q->fetch_row() : null;
                if (!$r || intval($r[0]) !== $kid) return ['ok' => false, 'hata' => 'İşlem bulunamadı.'];
                return yd_islem_durdur($db, intval($G['islem_id'])) ? ['ok' => true] : ['ok' => false, 'hata' => 'İşlem zaten bitmiş.'];
        }
        return null;
    } catch (Throwable $e) { return ['ok' => false, 'hata' => $e->getMessage()]; }
    finally { $db->kurum = $o; }
}

/* ───────── komut satırı (cron):  php kurum_yedek.php ───────── */
if (PHP_SAPI === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    require_once __DIR__ . '/config/baglan.php';
    echo 'Yedeklenen kurum: ' . yd_zamanli($db, true) . "\n";
}
