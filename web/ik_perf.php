<?php
// ============================================================
// ik_perf.php — Performans süreçleri (90/180/270/360°), hazır şablonlar, hedefler, hatırlatma, raporlar
// ik_islem.php içinden çağrılır: ik_perf_calistir($db,$uid,$Y,$girdi,$islem)
// ============================================================

function ik_perf_sema($db)
{
    static $y = false; if ($y) return; $y = true;
    $db->query("CREATE TABLE IF NOT EXISTS ik_perf_surec (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, ad VARCHAR(160) NOT NULL, tur SMALLINT NOT NULL DEFAULT 180, sablon VARCHAR(30) NOT NULL DEFAULT '', sorular MEDIUMTEXT NULL,
        bas DATE NULL, bit DATE NULL, anonim TINYINT(1) NOT NULL DEFAULT 1, durum VARCHAR(10) NOT NULL DEFAULT 'taslak', olusturan_id INT UNSIGNED NOT NULL DEFAULT 0, olusturma DATETIME NOT NULL,
        PRIMARY KEY (id), KEY k_durum (durum)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS ik_perf_atama (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, surec_id INT UNSIGNED NOT NULL, hedef_id INT UNSIGNED NOT NULL, degerlendiren_id INT UNSIGNED NOT NULL, iliski VARCHAR(8) NOT NULL DEFAULT 'amir',
        durum VARCHAR(10) NOT NULL DEFAULT 'bekliyor', yanitlar MEDIUMTEXT NULL, yorum VARCHAR(2000) NOT NULL DEFAULT '', puan DECIMAL(4,2) NULL, tamam_tarih DATETIME NULL, son_hatirlatma DATETIME NULL,
        PRIMARY KEY (id), UNIQUE KEY u_a (surec_id, hedef_id, degerlendiren_id), KEY k_deg (degerlendiren_id, durum), KEY k_hedef (hedef_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS ik_perf_hedef (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, kullanici_id INT UNSIGNED NOT NULL, baslik VARCHAR(200) NOT NULL, aciklama VARCHAR(1000) NOT NULL DEFAULT '', bitis DATE NULL,
        agirlik TINYINT NOT NULL DEFAULT 0, ilerleme TINYINT NOT NULL DEFAULT 0, durum VARCHAR(10) NOT NULL DEFAULT 'devam', olusturan_id INT UNSIGNED NOT NULL DEFAULT 0, olusturma DATETIME NOT NULL,
        PRIMARY KEY (id), KEY k_kul (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** Hazır şablonlar: anahtar => [ad, açıklama, sorular[]]; soru = [metin, tür(puan|metin), grup] */
function ik_perf_sablonlar()
{
    $y = function ($g, $l) { $o = []; foreach ($l as $m) $o[] = [$m, 'puan', $g]; return $o; };
    $a = function ($l) { $o = []; foreach ($l as $m) $o[] = [$m, 'metin', 'Genel']; return $o; };
    return [
        'yetkinlik' => ['Temel yetkinlikler', 'Davranışsal yetkinlikler, 1–5 ölçeği ve açık uçlu geri bildirim.', array_merge(
            $y('İletişim', ['Fikirlerini açık ve anlaşılır biçimde ifade eder.', 'Karşısındakini dinler, geri bildirime açıktır.']),
            $y('Takım çalışması', ['Ekip arkadaşlarıyla uyumlu çalışır, bilgiyi paylaşır.', 'Ortak hedef için üzerine düşeni yapar.']),
            $y('Sorumluluk', ['İşini sahiplenir, söz verdiği zamanda teslim eder.', 'Hatalarının sorumluluğunu alır.']),
            $y('Problem çözme', ['Sorunlara çözüm odaklı yaklaşır.', 'Veriye dayalı karar verir.']),
            $y('Gelişim', ['Yeni şeyler öğrenmeye isteklidir.', 'Değişime hızlı uyum sağlar.']),
            $a(['Bu kişinin en güçlü yönleri nelerdir?', 'Gelişmesi gereken alanlar nelerdir?']))],
        'hedef' => ['Hedef ve sonuç odaklı', 'İş çıktıları: hedef gerçekleştirme, kalite, verimlilik, inisiyatif.', array_merge(
            $y('Sonuç', ['Dönem hedeflerini gerçekleştirdi.', 'İşin kalitesi beklentiyi karşıladı.', 'Teslimleri zamanında yaptı.']),
            $y('Verimlilik', ['Kaynakları (zaman, bütçe) verimli kullandı.', 'Süreçleri iyileştirmek için öneri getirdi.']),
            $y('İnisiyatif', ['Söylenmeden sorumluluk aldı.', 'Beklenenin ötesinde katkı sağladı.']),
            $a(['Dönemin öne çıkan başarısı nedir?', 'Bir sonraki dönem için önerin nedir?']))],
        'yonetici' => ['Yönetici değerlendirmesi', 'Çalışanların yöneticisini değerlendirmesi (yukarı yönlü geri bildirim).', array_merge(
            $y('Liderlik', ['Net hedef ve beklenti belirler.', 'Ekibe ilham verir, örnek olur.']),
            $y('Destek', ['Gerektiğinde destek verir, engelleri kaldırır.', 'Düzenli ve yapıcı geri bildirim verir.']),
            $y('Adalet', ['Ekibe adil ve tarafsız davranır.', 'Başarıyı takdir eder.']),
            $a(['Yöneticinizin en iyi yaptığı şey nedir?', 'Neyi farklı yapmasını isterdiniz?']))],
        'deneme' => ['Deneme süresi', 'Yeni personelin uyum ve performans değerlendirmesi.', array_merge(
            $y('Uyum', ['Ekibe ve şirket kültürüne uyum sağladı.', 'Süreçleri hızlı öğrendi.']),
            $y('Performans', ['Görev tanımının gerektirdiği işleri yapabiliyor.', 'Kalite ve hız beklentiyi karşılıyor.']),
            $a(['Kadroda devam önerisi ve gerekçesi?']))],
    ];
}
function ik_perf_sorular($sablon, $ozel)
{
    $s = ik_perf_sablonlar(); $o = [];
    $liste = $ozel ?: ($s[$sablon][2] ?? $s['yetkinlik'][2]);
    foreach ($liste as $i => $q) { $m = mb_substr(trim((string)$q[0]), 0, 300, 'UTF-8'); if ($m === '') continue; $o[] = ['id' => count($o) + 1, 'metin' => $m, 'tur' => ($q[1] ?? 'puan') === 'metin' ? 'metin' : 'puan', 'grup' => mb_substr((string)($q[2] ?? 'Genel'), 0, 60, 'UTF-8')]; }
    return $o;
}
function ik_perf_iliski_ad() { return ['kendi' => 'Kendisi', 'amir' => 'Yönetici', 'es' => 'Takım arkadaşı', 'ast' => 'Ast']; }

function ik_perf_calistir($db, $uid, $Y, $girdi, $islem)
{
    ik_perf_sema($db);
    $yonet = !empty($Y['kurucu']) || !empty($Y['ik_performans_yonet']);
    $ad = function ($id) use ($db) { $r = $db->query("SELECT ad_soyad FROM kullanicilar WHERE id = " . intval($id)); $x = $r ? $r->fetch_row() : null; return $x ? $x[0] : '—'; };
    $post = in_array($islem, ['perf_surec_kaydet', 'perf_surec_durum', 'perf_surec_sil', 'perf_yanit_kaydet', 'perf_hatirlat', 'perf_hedef_kaydet', 'perf_hedef_sil', 'perf_atama_ekle', 'perf_atama_sil'], true);
    if ($post) ik_csrf($girdi);
    $yonetGerek = ['perf_surec_kaydet', 'perf_surec_durum', 'perf_surec_sil', 'perf_hatirlat', 'perf_atama_ekle', 'perf_atama_sil', 'perf_rapor', 'perf_surecler'];
    if (in_array($islem, $yonetGerek, true) && !$yonet) ikh('Bu işlem için performans yönetimi yetkisi gerekir.', 403);

    if ($islem === 'perf_sablonlar') {
        $o = []; foreach (ik_perf_sablonlar() as $k => $s) $o[] = ['anahtar' => $k, 'ad' => $s[0], 'aciklama' => $s[1], 'sorular' => ik_perf_sorular($k, null)];
        ikc(['ok' => true, 'sablonlar' => $o]);
    }
    if ($islem === 'perf_surecler') {
        $o = []; $q = $db->query("SELECT s.*, (SELECT COUNT(*) FROM ik_perf_atama a WHERE a.surec_id = s.id) top, (SELECT COUNT(*) FROM ik_perf_atama a WHERE a.surec_id = s.id AND a.durum = 'tamam') tam, (SELECT COUNT(DISTINCT hedef_id) FROM ik_perf_atama a WHERE a.surec_id = s.id) kisi FROM ik_perf_surec s ORDER BY s.id DESC LIMIT 200");
        if ($q) while ($r = $q->fetch_assoc()) $o[] = ['id' => intval($r['id']), 'ad' => $r['ad'], 'tur' => intval($r['tur']), 'sablon' => $r['sablon'], 'bas' => $r['bas'], 'bit' => $r['bit'], 'durum' => $r['durum'], 'anonim' => intval($r['anonim']), 'toplam' => intval($r['top']), 'tamam' => intval($r['tam']), 'kisi' => intval($r['kisi'])];
        ikc(['ok' => true, 'liste' => $o]);
    }
    if ($islem === 'perf_surec_kaydet') {
        $sid = intval($girdi['id'] ?? 0);
        $sad = mb_substr(trim((string)($girdi['ad'] ?? '')), 0, 160, 'UTF-8'); if ($sad === '') ikh('Süreç adını yazın.');
        $tur = in_array(intval($girdi['tur'] ?? 180), [90, 180, 270, 360], true) ? intval($girdi['tur']) : 180;
        $sab = isset(ik_perf_sablonlar()[$girdi['sablon'] ?? '']) ? $girdi['sablon'] : 'yetkinlik';
        $ozel = null;
        $sor = json_encode(ik_perf_sorular($sab, $ozel), JSON_UNESCAPED_UNICODE);
        $bas = ik_ek_tarih($girdi['bas'] ?? '') ?: null; $bit = ik_ek_tarih($girdi['bit'] ?? '') ?: null; $anonim = !empty($girdi['anonim']) ? 1 : 0;
        $kisiler = $girdi['kisiler'] ?? []; if (!is_array($kisiler)) $kisiler = explode(',', (string)$kisiler); $kisiler = array_values(array_unique(array_filter(array_map('intval', $kisiler))));
        if ($sid) {
            $st = $db->prepare("UPDATE ik_perf_surec SET ad=?, bas=?, bit=?, anonim=? WHERE id=?"); $st->bind_param('sssii', $sad, $bas, $bit, $anonim, $sid); $st->execute();
            ikc(['ok' => true, 'id' => $sid]);
        }
        if (!$kisiler) ikh('En az bir değerlendirilecek kişi seçin.');
        $st = $db->prepare("INSERT INTO ik_perf_surec (ad, tur, sablon, sorular, bas, bit, anonim, durum, olusturan_id, olusturma) VALUES (?,?,?,?,?,?,?,'aktif',?,NOW())");
        $st->bind_param('sissssii', $sad, $tur, $sab, $sor, $bas, $bit, $anonim, $uid); $st->execute(); $sid = $db->son_id();
        $say = 0; $bildirilen = [];
        foreach ($kisiler as $k) {
            $r = $db->query("SELECT COALESCE(p.yonetici_id,0), COALESCE(p.departman,'') FROM kullanicilar u LEFT JOIN ik_personel p ON p.kullanici_id = u.id WHERE u.id = $k AND u.aktif = 1"); $x = $r ? $r->fetch_row() : null; if (!$x) continue;
            $liste = [[$k, 'kendi']];
            if ($tur === 90) $liste = [];
            if (intval($x[0]) > 0) $liste[] = [intval($x[0]), 'amir'];
            if ($tur >= 270 && $x[1] !== '') { $e = $db->query("SELECT u.id FROM kullanicilar u JOIN ik_personel p2 ON p2.kullanici_id = u.id WHERE u.aktif = 1 AND p2.isten_cikis IS NULL AND LOWER(p2.departman) = LOWER('" . $db->real_escape_string($x[1]) . "') AND u.id <> $k AND u.id <> " . intval($x[0]) . " ORDER BY RAND() LIMIT 3"); if ($e) while ($z = $e->fetch_row()) $liste[] = [intval($z[0]), 'es']; }
            if ($tur >= 360) { $e = $db->query("SELECT p.kullanici_id FROM ik_personel p JOIN kullanicilar u ON u.id = p.kullanici_id AND u.aktif = 1 WHERE p.yonetici_id = $k LIMIT 5"); if ($e) while ($z = $e->fetch_row()) $liste[] = [intval($z[0]), 'ast']; }
            if ($tur === 90 && !$liste) $liste[] = [$uid, 'amir'];
            foreach ($liste as $l) {
                $db->query("INSERT IGNORE INTO ik_perf_atama (surec_id, hedef_id, degerlendiren_id, iliski) VALUES ($sid, $k, " . $l[0] . ", '" . $l[1] . "')");
                if ($db->etkilenen() > 0) { $say++; $bildirilen[$l[0]] = 1; }
            }
        }
        foreach (array_keys($bildirilen) as $b) ik_bildir($db, $b, $uid, '⭐ Performans değerlendirmesi', 'Sizden <b>' . ik_h($sad) . '</b> sürecinde değerlendirme yapmanız bekleniyor.' . ($bit ? ' Son tarih: ' . date('d.m.Y', strtotime($bit)) : ''), 'ik_perf', $sid);
        ik_ek_log($db, $uid, 'performans', 'surec_olustur', "Performans süreci başlatıldı: $sad ($tur°, " . count($kisiler) . ' kişi)');
        ikc(['ok' => true, 'id' => $sid, 'atama' => $say]);
    }
    if ($islem === 'perf_surec_durum') {
        $sid = intval($girdi['id'] ?? 0); $d = ($girdi['durum'] ?? '') === 'kapali' ? 'kapali' : 'aktif';
        $db->query("UPDATE ik_perf_surec SET durum = '$d' WHERE id = $sid"); ik_ek_log($db, $uid, 'performans', 'surec_durum', "Performans süreci #$sid → $d"); ikc(['ok' => true]);
    }
    if ($islem === 'perf_surec_sil') { $sid = intval($girdi['id'] ?? 0); $db->query("DELETE FROM ik_perf_atama WHERE surec_id = $sid"); $db->query("DELETE FROM ik_perf_surec WHERE id = $sid"); ik_ek_log($db, $uid, 'performans', 'surec_sil', "Performans süreci #$sid silindi"); ikc(['ok' => true]); }
    if ($islem === 'perf_atama_ekle') {
        $sid = intval($girdi['surec_id'] ?? 0); $h = intval($girdi['hedef_id'] ?? 0); $d = intval($girdi['degerlendiren_id'] ?? 0); $il = isset(ik_perf_iliski_ad()[$girdi['iliski'] ?? '']) ? $girdi['iliski'] : 'es';
        if (!$sid || !$h || !$d) ikh('Eksik bilgi.');
        $db->query("INSERT IGNORE INTO ik_perf_atama (surec_id, hedef_id, degerlendiren_id, iliski) VALUES ($sid, $h, $d, '$il')");
        $n = $db->query("SELECT ad FROM ik_perf_surec WHERE id = $sid")->fetch_row()[0] ?? '';
        if ($db->etkilenen() > 0) ik_bildir($db, $d, $uid, '⭐ Performans değerlendirmesi', 'Sizden <b>' . ik_h($n) . '</b> sürecinde değerlendirme yapmanız bekleniyor.', 'ik_perf', $sid);
        ikc(['ok' => true]);
    }
    if ($islem === 'perf_atama_sil') { $db->query("DELETE FROM ik_perf_atama WHERE id = " . intval($girdi['id'] ?? 0) . " AND durum = 'bekliyor'"); ikc(['ok' => true]); }

    if ($islem === 'perf_surec_detay') {
        $sid = intval($_GET['id'] ?? 0);
        $s = $db->query("SELECT * FROM ik_perf_surec WHERE id = $sid")->fetch_assoc(); if (!$s) ikh('Süreç bulunamadı.', 404);
        if (!$yonet) ikh('Yetkiniz yok.', 403);
        $at = []; $q = $db->query("SELECT a.id, a.hedef_id, a.degerlendiren_id, a.iliski, a.durum, a.tamam_tarih, a.son_hatirlatma, h.ad_soyad hedef, d.ad_soyad deg FROM ik_perf_atama a JOIN kullanicilar h ON h.id = a.hedef_id JOIN kullanicilar d ON d.id = a.degerlendiren_id WHERE a.surec_id = $sid ORDER BY h.ad_soyad, FIELD(a.iliski,'kendi','amir','es','ast')");
        if ($q) while ($r = $q->fetch_assoc()) $at[] = ['id' => intval($r['id']), 'hedef_id' => intval($r['hedef_id']), 'hedef' => $r['hedef'], 'deg_id' => intval($r['degerlendiren_id']), 'deg' => $r['deg'], 'iliski' => $r['iliski'], 'durum' => $r['durum'], 'tamam_tarih' => $r['tamam_tarih'], 'son_hatirlatma' => $r['son_hatirlatma']];
        ikc(['ok' => true, 'surec' => ['id' => intval($s['id']), 'ad' => $s['ad'], 'tur' => intval($s['tur']), 'bas' => $s['bas'], 'bit' => $s['bit'], 'durum' => $s['durum'], 'anonim' => intval($s['anonim'])], 'atamalar' => $at, 'iliskiler' => ik_perf_iliski_ad()]);
    }
    if ($islem === 'perf_hatirlat') {
        $sid = intval($girdi['surec_id'] ?? 0); $kim = intval($girdi['degerlendiren_id'] ?? 0);
        $s = $db->query("SELECT ad, bit, durum FROM ik_perf_surec WHERE id = $sid")->fetch_assoc(); if (!$s) ikh('Süreç bulunamadı.', 404);
        if ($s['durum'] !== 'aktif') ikh('Süreç kapalı.');
        $q = $db->query("SELECT DISTINCT degerlendiren_id FROM ik_perf_atama WHERE surec_id = $sid AND durum = 'bekliyor'" . ($kim ? " AND degerlendiren_id = $kim" : ''));
        $n = 0; $ids = []; if ($q) while ($r = $q->fetch_row()) $ids[] = intval($r[0]);
        foreach ($ids as $b) { ik_bildir($db, $b, $uid, '⏰ Performans değerlendirme hatırlatması', '<b>' . ik_h($s['ad']) . '</b> sürecinde bekleyen değerlendirmeleriniz var.' . ($s['bit'] ? ' Son tarih: ' . date('d.m.Y', strtotime($s['bit'])) : ''), 'ik_perf', $sid); $n++; }
        $db->query("UPDATE ik_perf_atama SET son_hatirlatma = NOW() WHERE surec_id = $sid AND durum = 'bekliyor'" . ($kim ? " AND degerlendiren_id = $kim" : ''));
        ik_ek_log($db, $uid, 'performans', 'hatirlat', "Performans hatırlatması gönderildi: {$s['ad']} ($n kişi)");
        ikc(['ok' => true, 'sayi' => $n]);
    }

    // ── Değerlendiren tarafı (herkes kendisine atananları görür) ──
    if ($islem === 'perf_bekleyen') {
        $o = []; $q = $db->query("SELECT a.id, a.iliski, a.durum, a.tamam_tarih, s.ad surec, s.bit, s.tur, h.ad_soyad hedef, h.id hid FROM ik_perf_atama a JOIN ik_perf_surec s ON s.id = a.surec_id AND s.durum = 'aktif' JOIN kullanicilar h ON h.id = a.hedef_id WHERE a.degerlendiren_id = $uid ORDER BY a.durum = 'tamam', s.bit IS NULL, s.bit, h.ad_soyad");
        if ($q) while ($r = $q->fetch_assoc()) $o[] = ['id' => intval($r['id']), 'iliski' => $r['iliski'], 'durum' => $r['durum'], 'surec' => $r['surec'], 'bit' => $r['bit'], 'tur' => intval($r['tur']), 'hedef' => $r['hedef'], 'hedef_id' => intval($r['hid']), 'kendi' => intval($r['hid']) === $uid, 'tamam_tarih' => $r['tamam_tarih']];
        ikc(['ok' => true, 'liste' => $o, 'iliskiler' => ik_perf_iliski_ad()]);
    }
    if ($islem === 'perf_yanit_getir') {
        $id = intval($_GET['id'] ?? 0);
        $r = $db->query("SELECT a.*, s.sorular, s.ad surec, h.ad_soyad hedef FROM ik_perf_atama a JOIN ik_perf_surec s ON s.id = a.surec_id JOIN kullanicilar h ON h.id = a.hedef_id WHERE a.id = $id AND a.degerlendiren_id = $uid")->fetch_assoc();
        if (!$r) ikh('Değerlendirme bulunamadı.', 404);
        ikc(['ok' => true, 'surec' => $r['surec'], 'hedef' => $r['hedef'], 'iliski' => $r['iliski'], 'durum' => $r['durum'], 'sorular' => json_decode($r['sorular'], true) ?: [], 'yanitlar' => json_decode((string)$r['yanitlar'], true) ?: new stdClass(), 'yorum' => $r['yorum']]);
    }
    if ($islem === 'perf_yanit_kaydet') {
        $id = intval($girdi['id'] ?? 0);
        $r = $db->query("SELECT a.*, s.sorular, s.durum sd, s.ad surec FROM ik_perf_atama a JOIN ik_perf_surec s ON s.id = a.surec_id WHERE a.id = $id AND a.degerlendiren_id = $uid")->fetch_assoc();
        if (!$r) ikh('Değerlendirme bulunamadı.', 404); if ($r['sd'] !== 'aktif') ikh('Süreç kapalı.');
        $sor = json_decode($r['sorular'], true) ?: []; $gel = $girdi['yanitlar'] ?? []; if (!is_array($gel)) $gel = json_decode((string)$gel, true) ?: []; $y = []; $top = 0; $n = 0; $eksik = 0;
        foreach ($sor as $q) {
            $v = $gel[$q['id']] ?? $gel[(string)$q['id']] ?? null;
            if ($q['tur'] === 'puan') { $p = intval($v); if ($p >= 1 && $p <= 5) { $y[$q['id']] = $p; $top += $p; $n++; } else $eksik++; }
            else { $t = mb_substr(trim((string)$v), 0, 1500, 'UTF-8'); if ($t !== '') $y[$q['id']] = $t; }
        }
        $bitir = !empty($girdi['bitir']);
        if ($bitir && $eksik) ikh("Puanlanmamış $eksik soru var. Tüm puanlı soruları yanıtlayın.");
        $puan = $n ? round($top / $n, 2) : null; $yj = json_encode($y, JSON_UNESCAPED_UNICODE); $yorum = mb_substr(trim((string)($girdi['yorum'] ?? '')), 0, 2000, 'UTF-8'); $dur = $bitir ? 'tamam' : 'bekliyor';
        $st = $db->prepare("UPDATE ik_perf_atama SET yanitlar=?, yorum=?, puan=?, durum=?, tamam_tarih=" . ($bitir ? 'NOW()' : 'NULL') . " WHERE id=?");
        $st->bind_param('ssdsi', $yj, $yorum, $puan, $dur, $id); $st->execute();
        if ($bitir) ik_ek_log($db, $uid, 'performans', 'degerlendirme', "Performans değerlendirmesi tamamlandı: {$r['surec']}", intval($r['hedef_id']));
        ikc(['ok' => true]);
    }

    // ── Raporlar ──
    if ($islem === 'perf_rapor') {
        $sid = intval($_GET['surec_id'] ?? 0); $hid = intval($_GET['hedef_id'] ?? 0);
        $s = $db->query("SELECT * FROM ik_perf_surec WHERE id = $sid")->fetch_assoc(); if (!$s) ikh('Süreç bulunamadı.', 404);
        $sor = json_decode($s['sorular'], true) ?: []; $il = ik_perf_iliski_ad();
        $q = $db->query("SELECT a.*, h.ad_soyad hedef, d.ad_soyad deg FROM ik_perf_atama a JOIN kullanicilar h ON h.id = a.hedef_id JOIN kullanicilar d ON d.id = a.degerlendiren_id WHERE a.surec_id = $sid AND a.durum = 'tamam'" . ($hid ? " AND a.hedef_id = $hid" : ''));
        $kisi = [];
        if ($q) while ($r = $q->fetch_assoc()) {
            $h = intval($r['hedef_id']); if (!isset($kisi[$h])) $kisi[$h] = ['id' => $h, 'ad' => $r['hedef'], 'grup' => [], 'soru' => [], 'yorumlar' => [], 'tamam' => 0];
            $kisi[$h]['tamam']++; $yan = json_decode((string)$r['yanitlar'], true) ?: []; $ik = $r['iliski'];
            foreach ($sor as $s1) if ($s1['tur'] === 'puan' && isset($yan[$s1['id']])) { $kisi[$h]['soru'][$s1['id']][$ik][] = $yan[$s1['id']]; $kisi[$h]['grup'][$ik][] = $yan[$s1['id']]; }
            $yy = []; foreach ($sor as $s1) if ($s1['tur'] === 'metin' && !empty($yan[$s1['id']])) $yy[] = ['soru' => $s1['metin'], 'yanit' => $yan[$s1['id']]];
            if ($r['yorum'] !== '') $yy[] = ['soru' => 'Ek yorum', 'yanit' => $r['yorum']];
            if ($yy) $kisi[$h]['yorumlar'][] = ['iliski' => $ik, 'kim' => ($s['anonim'] && $ik !== 'kendi' && $ik !== 'amir') ? 'Anonim' : $r['deg'], 'liste' => $yy];
        }
        $avg = function ($a) { return $a ? round(array_sum($a) / count($a), 2) : null; };
        $o = [];
        foreach ($kisi as $k) {
            $g = []; foreach ($il as $kod => $_) $g[$kod] = $avg($k['grup'][$kod] ?? []);
            $diger = []; foreach (['amir', 'es', 'ast'] as $kod) foreach ($k['grup'][$kod] ?? [] as $v) $diger[] = $v;
            $sr = []; foreach ($sor as $s1) if ($s1['tur'] === 'puan') { $row = ['id' => $s1['id'], 'metin' => $s1['metin'], 'grup' => $s1['grup']]; foreach ($il as $kod => $_) $row[$kod] = $avg($k['soru'][$s1['id']][$kod] ?? []); $sr[] = $row; }
            $o[] = ['id' => $k['id'], 'ad' => $k['ad'], 'genel' => $avg($diger) ?? $avg($k['grup']['kendi'] ?? []), 'kendi' => $g['kendi'], 'gruplar' => $g, 'sorular' => $sr, 'yorumlar' => $k['yorumlar'], 'tamam' => $k['tamam']];
        }
        usort($o, function ($a, $b) { return ($b['genel'] ?? 0) <=> ($a['genel'] ?? 0); });
        ikc(['ok' => true, 'surec' => ['id' => intval($s['id']), 'ad' => $s['ad'], 'tur' => intval($s['tur']), 'anonim' => intval($s['anonim'])], 'kisiler' => $o, 'iliskiler' => $il]);
    }
    // Kişinin kendi sonuçları (yönetici açıp paylaşmadıkça görmez → yalnız yönetici)
    // ── Hedefler ──
    if ($islem === 'perf_hedef_liste') {
        $kid = ($yonet && !empty($_GET['kullanici_id'])) ? intval($_GET['kullanici_id']) : $uid;
        if (!$yonet && !empty($_GET['kullanici_id']) && intval($_GET['kullanici_id']) !== $uid) { $r = $db->query("SELECT 1 FROM ik_personel WHERE kullanici_id = " . intval($_GET['kullanici_id']) . " AND yonetici_id = $uid"); if ($r && $r->num_rows) $kid = intval($_GET['kullanici_id']); }
        $o = []; $q = $db->query("SELECT * FROM ik_perf_hedef WHERE kullanici_id = $kid ORDER BY durum = 'bitti', bitis IS NULL, bitis, id DESC");
        if ($q) while ($r = $q->fetch_assoc()) $o[] = ['id' => intval($r['id']), 'baslik' => $r['baslik'], 'aciklama' => $r['aciklama'], 'bitis' => $r['bitis'], 'agirlik' => intval($r['agirlik']), 'ilerleme' => intval($r['ilerleme']), 'durum' => $r['durum']];
        ikc(['ok' => true, 'liste' => $o, 'kullanici_id' => $kid, 'yonet' => $yonet, 'ben' => $uid]);
    }
    if ($islem === 'perf_hedef_kaydet') {
        $id = intval($girdi['id'] ?? 0); $kid = intval($girdi['kullanici_id'] ?? $uid) ?: $uid;
        if ($kid !== $uid && !$yonet) { $r = $db->query("SELECT 1 FROM ik_personel WHERE kullanici_id = $kid AND yonetici_id = $uid"); if (!$r || !$r->num_rows) ikh('Bu kişiye hedef verme yetkiniz yok.', 403); }
        if ($id) { $r = $db->query("SELECT kullanici_id, olusturan_id FROM ik_perf_hedef WHERE id = $id")->fetch_assoc(); if (!$r) ikh('Hedef bulunamadı.', 404); $kid = intval($r['kullanici_id']); if ($kid !== $uid && !$yonet && intval($r['olusturan_id']) !== $uid) ikh('Yetkiniz yok.', 403); }
        $b = mb_substr(trim((string)($girdi['baslik'] ?? '')), 0, 200, 'UTF-8'); if ($b === '') ikh('Hedef başlığını yazın.');
        $ac = mb_substr(trim((string)($girdi['aciklama'] ?? '')), 0, 1000, 'UTF-8'); $bt = ik_ek_tarih($girdi['bitis'] ?? '') ?: null; $ag = max(0, min(100, intval($girdi['agirlik'] ?? 0))); $il = max(0, min(100, intval($girdi['ilerleme'] ?? 0)));
        $du = $il >= 100 ? 'bitti' : 'devam';
        if ($id) { $st = $db->prepare("UPDATE ik_perf_hedef SET baslik=?, aciklama=?, bitis=?, agirlik=?, ilerleme=?, durum=? WHERE id=?"); $st->bind_param('sssiisi', $b, $ac, $bt, $ag, $il, $du, $id); }
        else { $st = $db->prepare("INSERT INTO ik_perf_hedef (kullanici_id, baslik, aciklama, bitis, agirlik, ilerleme, durum, olusturan_id, olusturma) VALUES (?,?,?,?,?,?,?,?,NOW())"); $st->bind_param('isssiisi', $kid, $b, $ac, $bt, $ag, $il, $du, $uid); }
        $st->execute();
        if (!$id && $kid !== $uid) ik_bildir($db, $kid, $uid, '🎯 Yeni hedef', 'Size yeni bir hedef atandı: <b>' . ik_h($b) . '</b>', 'ik_perf', 0);
        ikc(['ok' => true]);
    }
    if ($islem === 'perf_hedef_sil') {
        $id = intval($girdi['id'] ?? 0); $r = $db->query("SELECT kullanici_id, olusturan_id FROM ik_perf_hedef WHERE id = $id")->fetch_assoc();
        if ($r && ($yonet || intval($r['olusturan_id']) === $uid)) $db->query("DELETE FROM ik_perf_hedef WHERE id = $id"); else ikh('Yetkiniz yok.', 403);
        ikc(['ok' => true]);
    }
    ikh('Geçersiz işlem.');
}
