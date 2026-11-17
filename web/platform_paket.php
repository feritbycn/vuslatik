<?php
// ============================================================
// platform_paket.php — Modül paketleri: tanımlar, kuruma atama (süreli), kuruma özel indirim, modül kilidi
// Hem platform paneli (platform_islem.php) hem İK (ik.php / ik_islem.php ...) tarafından kullanılır.
// Tablolar "pf_" ile başlar (ön eksiz). pf_kurumlar.paket_modu = 0 ise hiçbir modül kilitlenmez (eski davranış).
// ============================================================

/** Paketlenebilir modüller: kod => [ad, ikon, renk, açıklama, [sol menü anahtarları]] */
/** Bağımsız tek kurum paketi (platform paneli yok) ise paket sistemi kapalıdır; vekil sunucu üzerinden gelen istekte ana sunucu çalıştığı için açıktır */
function pf_paket_tek_mi()
{
    return defined('TB_TEK_KURUM') && TB_TEK_KURUM > 0 && !(defined('TB_VEKIL_ISTEK') && TB_VEKIL_ISTEK);
}
function pf_paket_katalog()
{
    return [
        'masraf'     => ['Masraf ve avans', 'fa-receipt', '#ef4444', 'Personel masrafları ve avans talepleri, onay akışı.', ['masraflar', 'avanslar']],
        'puantaj'    => ['Puantaj', 'fa-calendar-check', '#14b8a6', 'Aylık puantaj tablosu ve kayıtları.', ['puantaj']],
        'egitim'     => ['Eğitim yönetimi', 'fa-graduation-cap', '#06b6d4', 'Eğitim/toplantı kayıtları, katılım takibi, hatırlatma.', ['egitimler']],
        'performans' => ['Performans', 'fa-star', '#eab308', '90/180/270/360° değerlendirme, hedefler, raporlar.', ['performans']],
        'isealim'    => ['İşe alım', 'fa-user-plus', '#0ea5e9', 'Aday hunisi, mülakat takvimi.', ['isealim']],
        'bordro'     => ['Bordro', 'fa-money-check-dollar', '#22c55e', 'Bordro kayıtları ve özetleri.', ['bordro']],
        'evrak'      => ['Özlük evrakı ve zimmet', 'fa-folder-open', '#6366f1', 'Tüm personel evrak ve zimmet listeleri.', ['belge_tum', 'zimmet_tum']],
        'paylasim'   => ['Dosya paylaşımı ve anket', 'fa-share-nodes', '#8b5cf6', 'Dosya paylaşımı ve anketler.', ['dosyalar', 'anketler']],
        'giris'      => ['Giriş-çıkış ve mesai (PDKS)', 'fa-fingerprint', '#f59e0b', 'Giriş-çıkış kayıtları, vardiya, cihaz/nokta yönetimi ve mesai.', ['mesailer', 'gc_bugun', 'gc_kayitlar', 'gc_rapor', 'gc_vardiyalar', 'gc_cihazlar', 'gc_noktalar', 'gc_ayarlar']],
        'raporlar'   => ['Gelişmiş raporlar', 'fa-chart-column', '#f43f5e', 'Rapor merkezi, dışa aktarma.', ['raporlar']],
    ];
}
function pf_paket_sema($db)
{
    static $tamam = false; if ($tamam) return; $tamam = true;
    $s = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->query("CREATE TABLE IF NOT EXISTS pf_paketler (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, kod VARCHAR(40) NOT NULL, ad VARCHAR(120) NOT NULL, aciklama VARCHAR(500) NOT NULL DEFAULT '', renk VARCHAR(9) NOT NULL DEFAULT '#6366f1',
        moduller TEXT NULL, tam TINYINT(1) NOT NULL DEFAULT 0, aylik DECIMAL(10,2) NOT NULL DEFAULT 0, yillik DECIMAL(10,2) NOT NULL DEFAULT 0, para VARCHAR(3) NOT NULL DEFAULT 'TRY',
        sira INT NOT NULL DEFAULT 0, aktif TINYINT(1) NOT NULL DEFAULT 1, PRIMARY KEY (id), UNIQUE KEY u_kod (kod)) $s");
    $db->query("CREATE TABLE IF NOT EXISTS pf_kurum_paket (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, kurum_id INT UNSIGNED NOT NULL, paket_id INT UNSIGNED NOT NULL, bas DATE NOT NULL, bit DATE NOT NULL, donem VARCHAR(8) NOT NULL DEFAULT 'aylik',
        olusturma DATETIME NOT NULL, PRIMARY KEY (id), UNIQUE KEY u_kp (kurum_id, paket_id)) $s");
    $db->query("CREATE TABLE IF NOT EXISTS pf_paket_talep (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, kurum_id INT UNSIGNED NOT NULL, paket_id INT UNSIGNED NULL, donem VARCHAR(8) NOT NULL DEFAULT 'aylik', kisi VARCHAR(120) NOT NULL DEFAULT '',
        mesaj VARCHAR(500) NOT NULL DEFAULT '', durum VARCHAR(10) NOT NULL DEFAULT 'yeni', olusturma DATETIME NOT NULL, PRIMARY KEY (id), KEY k_d (durum)) $s");
    if (!@$db->query("SELECT paket_modu FROM pf_kurumlar LIMIT 0"))
        @$db->query("ALTER TABLE pf_kurumlar ADD COLUMN paket_modu TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN paket_gorunur TINYINT(1) NOT NULL DEFAULT 0, ADD COLUMN vitrin_gorunur TINYINT(1) NOT NULL DEFAULT 0,
            ADD COLUMN ind_aylik_tip VARCHAR(6) NOT NULL DEFAULT 'yuzde', ADD COLUMN ind_aylik DECIMAL(10,2) NOT NULL DEFAULT 0, ADD COLUMN ind_yillik_tip VARCHAR(6) NOT NULL DEFAULT 'yuzde', ADD COLUMN ind_yillik DECIMAL(10,2) NOT NULL DEFAULT 0");
    if (!@$db->query("SELECT pk_bilgi FROM pf_kurumlar LIMIT 0")) {
        @$db->query("ALTER TABLE pf_kurumlar ADD COLUMN pk_bilgi TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN pk_sure TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN pk_banner TINYINT(1) NOT NULL DEFAULT 1, ADD COLUMN pk_gizli VARCHAR(500) NOT NULL DEFAULT ''");
        // yeni açılan kurumlarda Paketlerim ve vitrin açık gelsin (mevcut kurumlar değişmez)
        @$db->query("ALTER TABLE pf_kurumlar ALTER COLUMN paket_gorunur SET DEFAULT 1, ALTER COLUMN vitrin_gorunur SET DEFAULT 1");
    }
    $r = $db->query("SELECT COUNT(*) FROM pf_paketler"); if ($r && intval($r->fetch_row()[0]) === 0) {
        $sira = 1; $hepsi = [];
        foreach (pf_paket_katalog() as $kod => $m) { $hepsi[] = $kod; $mj = json_encode([$kod]); $st = $db->prepare("INSERT INTO pf_paketler (kod, ad, aciklama, renk, moduller, tam, sira) VALUES (?,?,?,?,?,0,?)"); $st->bind_param('sssssi', $kod, $m[0], $m[3], $m[2], $mj, $sira); $st->execute(); $sira++; }
        $mj = json_encode($hepsi); $ad = 'Tam paket'; $ac = 'Tüm modüller bir arada.'; $rn = '#6366f1'; $kod = 'tam'; $sr = 0;
        $st = $db->prepare("INSERT INTO pf_paketler (kod, ad, aciklama, renk, moduller, tam, sira) VALUES (?,?,?,?,?,1,?)"); $st->bind_param('sssssi', $kod, $ad, $ac, $rn, $mj, $sr); $st->execute();
    }
}
function pf_paket_modul_liste($p)
{
    $k = pf_paket_katalog(); $l = json_decode((string)($p['moduller'] ?? '[]'), true); if (!is_array($l)) $l = [];
    if (!empty($p['tam'])) return array_keys($k);
    return array_values(array_filter($l, function ($x) use ($k) { return isset($k[$x]); }));
}
function pf_paket_kurum_satir($db, $kid)
{
    $q = $db->query("SELECT * FROM pf_kurumlar WHERE id = " . intval($kid)); return $q ? $q->fetch_assoc() : null;
}
/** Kurumun şu an geçerli (süresi bitmemiş) modülleri: kod => bitiş tarihi (en geç) */
function pf_paket_aktif_moduller($db, $kid)
{
    $o = []; $bugun = date('Y-m-d');
    $q = $db->query("SELECT p.*, kp.bit FROM pf_kurum_paket kp JOIN pf_paketler p ON p.id = kp.paket_id WHERE kp.kurum_id = " . intval($kid) . " AND kp.bit >= '$bugun'");
    if ($q) while ($r = $q->fetch_assoc()) foreach (pf_paket_modul_liste($r) as $m) if (!isset($o[$m]) || $o[$m] < $r['bit']) $o[$m] = $r['bit'];
    return $o;
}
/** Kilitli sol menü anahtarları (paket modu açıksa, paketinde olmayan modüllerin menüleri). Tek kurum kurulumunda ya da tablo yoksa boş. */
function pf_paket_kilitli($db)
{
    static $onbellek = []; $kid = intval($db->kurum ?? 0);
    if (isset($onbellek[$kid])) return $onbellek[$kid];
    $onbellek[$kid] = [];
    if ($kid <= 0 || pf_paket_tek_mi()) return [];
    try {
        $k = pf_paket_kurum_satir($db, $kid);
        if (!$k || !array_key_exists('paket_modu', $k) || intval($k['paket_modu']) !== 1) return [];
        $acik = pf_paket_aktif_moduller($db, $kid); $kil = [];
        foreach (pf_paket_katalog() as $kod => $m) if (!isset($acik[$kod])) foreach ($m[4] as $mk) $kil[] = $mk;
        return $onbellek[$kid] = $kil;
    } catch (Throwable $e) { return []; }
}
/** Menü anahtarı hangi modüle ait? */
function pf_paket_menu_modul($menu) { foreach (pf_paket_katalog() as $kod => $m) if (in_array($menu, $m[4], true)) return $kod; return ''; }

/** Fiyat hesabı: liste fiyat, kuruma özel indirim, net fiyat (dönem: aylik|yillik) */
function pf_paket_fiyat($p, $k, $donem)
{
    $aylik = (float)$p['aylik']; $yillik = (float)$p['yillik']; if ($yillik <= 0) $yillik = $aylik * 12;
    $liste = $donem === 'yillik' ? $yillik : $aylik;
    $tip = (string)($k[$donem === 'yillik' ? 'ind_yillik_tip' : 'ind_aylik_tip'] ?? 'yuzde'); $deg = (float)($k[$donem === 'yillik' ? 'ind_yillik' : 'ind_aylik'] ?? 0);
    $ind = $deg <= 0 ? 0 : ($tip === 'tl' ? min($liste, $deg) : $liste * min(100, $deg) / 100);
    return ['liste' => round($liste, 2), 'indirim' => round($ind, 2), 'net' => round($liste - $ind, 2)];
}
function pf_paket_satir_cikti($p, $k)
{
    $a = pf_paket_fiyat($p, $k, 'aylik'); $y = pf_paket_fiyat($p, $k, 'yillik');
    $yilAvantaj = $a['net'] > 0 ? max(0, round((1 - $y['net'] / ($a['net'] * 12)) * 100)) : 0;
    return ['id' => intval($p['id']), 'kod' => $p['kod'], 'ad' => $p['ad'], 'aciklama' => $p['aciklama'], 'renk' => $p['renk'], 'tam' => intval($p['tam']), 'para' => $p['para'], 'moduller' => pf_paket_modul_liste($p),
        'aylik' => $a, 'yillik' => $y, 'yillik_avantaj' => $yilAvantaj, 'fiyat_yok' => ((float)$p['aylik'] <= 0 && (float)$p['yillik'] <= 0)];
}

/** Süre ekleme: mevcut süre devam ediyorsa bitişten uzatır, bittiyse bugünden başlatır */
function pf_paket_ata($db, $kid, $paketId, $tur, $gun, $tarih)
{
    $bugun = date('Y-m-d');
    $q = $db->query("SELECT * FROM pf_kurum_paket WHERE kurum_id = " . intval($kid) . " AND paket_id = " . intval($paketId)); $m = $q ? $q->fetch_assoc() : null;
    $baz = ($m && $m['bit'] >= $bugun) ? $m['bit'] : $bugun; $bas = ($m && $m['bit'] >= $bugun) ? $m['bas'] : $bugun;
    if ($tur === 'tarih' && $tarih) $bit = $tarih;
    elseif ($tur === 'yillik') $bit = date('Y-m-d', strtotime($baz . ' +1 year'));
    elseif ($tur === 'gun') $bit = date('Y-m-d', strtotime($baz . ' +' . max(1, min(3650, intval($gun))) . ' days'));
    else $bit = date('Y-m-d', strtotime($baz . ' +1 month'));
    $d = in_array($tur, ['aylik', 'yillik'], true) ? $tur : 'ozel';
    $st = $db->prepare("INSERT INTO pf_kurum_paket (kurum_id, paket_id, bas, bit, donem, olusturma) VALUES (?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE bas = VALUES(bas), bit = VALUES(bit), donem = VALUES(donem)");
    $st->bind_param('iisss', $kid, $paketId, $bas, $bit, $d); $st->execute();
    return $bit;
}

/** Kurum tarafı (İK) — Paketlerim sayfası ve bilgi alma talebi. Yetki: kurum yetkilisi / İK ayarları */
function pf_paket_ik_calistir($db, $uid, $Y, $girdi, $islem)
{
    $kid = intval($db->kurum ?? 0); pf_paket_sema($db);
    $k = $kid > 0 ? pf_paket_kurum_satir($db, $kid) : null;
    if (!$k || pf_paket_tek_mi()) ikh('Paket bilgisi bu kurulumda kullanılmıyor.', 404);
    if (empty($Y['kurucu']) && empty($Y['ik_ayarlar'])) ikh('Paketleri yalnızca kurum yetkilisi görebilir.', 403);
    if (empty($k['paket_gorunur'])) ikh('Paket sayfası bu kurum için kapalı.', 403);
    if ($islem === 'paketlerim') {
        $acik = []; $bugun = date('Y-m-d'); $q = $db->query("SELECT * FROM pf_kurum_paket WHERE kurum_id = $kid"); if ($q) while ($r = $q->fetch_assoc()) $acik[intval($r['paket_id'])] = $r;
        $liste = []; $q = $db->query("SELECT * FROM pf_paketler WHERE aktif = 1 ORDER BY tam DESC, sira, id");
        if ($q) while ($p = $q->fetch_assoc()) {
            $o = pf_paket_satir_cikti($p, $k); $a = $acik[intval($p['id'])] ?? null; $o['sahip'] = false;
            if ($a) { $kalan = (int)round((strtotime($a['bit']) - strtotime($bugun)) / 86400); $toplam = max(1, (int)round((strtotime($a['bit']) - strtotime($a['bas'])) / 86400));
                $o['sahip'] = $kalan >= 0; $o['bas'] = $a['bas']; $o['bit'] = $a['bit']; $o['kalan'] = $kalan; $o['toplam'] = $toplam; $o['donem'] = $a['donem']; }
            $gz = array_filter(array_map('intval', explode(',', (string)($k['pk_gizli'] ?? '')))); if (!$o['sahip'] && !$a && in_array(intval($p['id']), $gz, true)) continue;
            if (!$o['sahip'] && empty($k['vitrin_gorunur']) && !$a) continue; // vitrin kapalı: sahip olunmayan paket gösterilmez
            if (!$o['sahip'] && empty($k['vitrin_gorunur'])) { $o['fiyat_gizli'] = true; unset($o['aylik'], $o['yillik']); $o['yillik_avantaj'] = 0; }
            $liste[] = $o;
        }
        ikc(['ok' => true, 'paketler' => $liste, 'vitrin' => !empty($k['vitrin_gorunur']), 'modu' => intval($k['paket_modu']), 'bilgi' => intval($k['pk_bilgi'] ?? 1), 'sure' => intval($k['pk_sure'] ?? 1), 'katalog' => array_map(function ($m) { return ['ad' => $m[0], 'ikon' => $m[1], 'renk' => $m[2], 'aciklama' => $m[3]]; }, pf_paket_katalog())]);
    }
    if ($islem === 'paket_talep') {
        ik_csrf($girdi); if (empty($k['vitrin_gorunur'])) ikh('Bu işlem kapalı.', 403);
        $pid = intval($girdi['paket_id'] ?? 0); $don = ($girdi['donem'] ?? '') === 'yillik' ? 'yillik' : 'aylik'; $msj = mb_substr(trim((string)($girdi['mesaj'] ?? '')), 0, 500, 'UTF-8');
        $kisi = (string)($db->query("SELECT ad_soyad FROM kullanicilar WHERE id = " . intval($uid))->fetch_row()[0] ?? '');
        $st = $db->prepare("INSERT INTO pf_paket_talep (kurum_id, paket_id, donem, kisi, mesaj, olusturma) VALUES (?, ?, ?, ?, ?, NOW())"); $pn = $pid ?: null; $st->bind_param('iisss', $kid, $pn, $don, $kisi, $msj); $st->execute();
        ikc(['ok' => true]);
    }
    ikh('Geçersiz işlem.');
}

/** İK arayüzü için kurumun paket özeti: ayarlar + yakında bitenler */
function pf_paket_ik_ozet($db)
{
    $o = ['modu' => 0, 'gorunur' => 0, 'vitrin' => 0, 'bitiyor' => [], 'indirim' => null, 'sure_goster' => 1]; $kid = intval($db->kurum ?? 0);
    if ($kid <= 0 || pf_paket_tek_mi()) return $o;
    try {
        $k = pf_paket_kurum_satir($db, $kid); if (!$k || !array_key_exists('paket_modu', $k)) return $o;
        $o['modu'] = intval($k['paket_modu']); $o['gorunur'] = intval($k['paket_gorunur']); $o['vitrin'] = intval($k['vitrin_gorunur']); $o['sure_goster'] = intval($k['pk_sure'] ?? 1);
        if ($o['gorunur'] && $o['vitrin'] && !empty($k['pk_banner'])) { $ia = (float)($k['ind_aylik'] ?? 0); $iy = (float)($k['ind_yillik'] ?? 0); if ($ia > 0 || $iy > 0) $o['indirim'] = ['aylik_tip' => (string)$k['ind_aylik_tip'], 'aylik' => $ia, 'yillik_tip' => (string)$k['ind_yillik_tip'], 'yillik' => $iy]; }
        if ($o['modu'] && $o['gorunur']) { $q = $db->query("SELECT p.ad, kp.bit, DATEDIFF(kp.bit, CURDATE()) kalan FROM pf_kurum_paket kp JOIN pf_paketler p ON p.id = kp.paket_id WHERE kp.kurum_id = $kid AND kp.bit BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY kp.bit");
            if ($q) while ($r = $q->fetch_assoc()) $o['bitiyor'][] = ['ad' => $r['ad'], 'bit' => $r['bit'], 'kalan' => intval($r['kalan'])]; }
    } catch (Throwable $e) {}
    return $o;
}
