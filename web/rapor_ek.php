<?php
// ============================================================
// rapor_ek.php — Ek rapor türleri (rapor_ortak.php'nin sonunda yüklenir):
//   personel_liste · ise_hareket · masraf · zimmet · belge_sure · egitim · perf · bordro_ozet · dogum · islem_log
// Her tür rp_<tip>($db, $p, $kisiler) imzasıyla rp_uret() tarafından çağrılır.
// ============================================================

function rp_ek_tanimlar()
{
    $ad = ['ad' => 'Ada göre (A-Z)'];
    return [
        'personel_liste' => ['ad' => 'Personel listesi', 'ikon' => 'fa-address-book', 'renk' => '#6366f1',
            'aciklama' => 'Tüm personelin unvan, birim, işe giriş tarihi, kıdem, çalışma şekli ve iletişim bilgileri.',
            'siralamalar' => $ad + ['ise_giris' => 'İşe giriş (eskiden yeniye)', 'birim' => 'Birime göre'],
            'secenekler' => [['k' => 'durum', 'ad' => 'Durum', 'tip' => 'sec', 'v' => 'hepsi', 'sec' => ['hepsi' => 'Tümü', 'calisan' => 'Çalışanlar', 'ayrilan' => 'Ayrılanlar']]],
            'sutunlar' => [['unvan', 'Unvan', 1], ['birim', 'Birim', 1], ['ise_giris', 'İşe giriş', 1], ['kidem', 'Kıdem', 1], ['calisma', 'Çalışma şekli', 0], ['telefon', 'Telefon', 1], ['email', 'E-posta', 1], ['dogum', 'Doğum tarihi', 0], ['durum', 'Durum', 1]]],
        'ise_hareket' => ['ad' => 'İşe giriş / ayrılışlar', 'ikon' => 'fa-door-open', 'renk' => '#0ea5e9',
            'aciklama' => 'Seçilen tarih aralığında işe başlayan ve işten ayrılan personel; ayrılış nedenleriyle birlikte.',
            'siralamalar' => ['tarih' => 'Tarihe göre (yeniden eskiye)', 'ad' => 'Ada göre (A-Z)'],
            'secenekler' => [['k' => 'tur', 'ad' => 'Hareket', 'tip' => 'sec', 'v' => '', 'sec' => ['' => 'Giriş ve ayrılışlar', 'giris' => 'Yalnızca işe girişler', 'cikis' => 'Yalnızca ayrılışlar']]],
            'sutunlar' => [['hareket', 'Hareket', 1], ['tarih', 'Tarih', 1], ['unvan', 'Unvan', 1], ['birim', 'Birim', 1], ['neden', 'Ayrılış nedeni', 1]]],
        'masraf' => ['ad' => 'Masraf', 'ikon' => 'fa-wallet', 'renk' => '#ef4444',
            'aciklama' => 'Personel masrafları: kişi bazında toplamlar, kategori dağılımı ve tek tek masraf listesi.',
            'siralamalar' => ['tutar' => 'Tutar (çoktan aza)', 'adet' => 'Masraf sayısı'] + $ad,
            'secenekler' => [['k' => 'durum', 'ad' => 'Durum', 'tip' => 'sec', 'v' => '', 'sec' => ['' => 'Tümü', 'onaylandi' => 'Yalnızca onaylananlar', 'beklemede' => 'Onay bekleyenler', 'reddedildi' => 'Reddedilenler']],
                ['k' => 'detay', 'ad' => 'Tek tek masraf listesini ekle', 'tip' => 'bool', 'v' => true]],
            'sutunlar' => [['adet', 'Masraf sayısı', 1], ['onayli', 'Onaylanan', 1], ['bekleyen', 'Bekleyen', 1], ['red', 'Reddedilen', 0], ['toplam', 'Toplam', 1], ['birim', 'Birim', 1]]],
        'zimmet' => ['ad' => 'Zimmet', 'ikon' => 'fa-box-archive', 'renk' => '#f59e0b',
            'aciklama' => 'Kimin üzerinde hangi eşya zimmetli; teslim ve iade tarihleri.',
            'siralamalar' => ['adet' => 'Zimmetli adet (çoktan aza)'] + $ad,
            'secenekler' => [['k' => 'kapsam', 'ad' => 'Kapsam', 'tip' => 'sec', 'v' => 'zimmetli', 'sec' => ['zimmetli' => 'Şu an zimmetli olanlar (tarih aralığı yok sayılır)', 'aralik' => 'Teslim tarihi seçilen aralıkta olanlar']],
                ['k' => 'detay', 'ad' => 'Eşya listesini ekle', 'tip' => 'bool', 'v' => true]],
            'sutunlar' => [['zimmetli', 'Zimmetli eşya', 1], ['iade', 'İade edilen', 1], ['esyalar', 'Eşyalar', 1], ['birim', 'Birim', 1]]],
        'belge_sure' => ['ad' => 'Belge geçerlilik süreleri', 'ikon' => 'fa-file-circle-exclamation', 'renk' => '#14b8a6',
            'aciklama' => 'Süresi dolacak ya da dolmuş özlük evrakları (ehliyet, sağlık raporu, sertifika vb.).',
            'siralamalar' => ['tarih' => 'En yakın bitiş tarihi'] + $ad,
            'secenekler' => [['k' => 'kapsam', 'ad' => 'Kapsam', 'tip' => 'sec', 'v' => 'aralik', 'sec' => ['aralik' => 'Süresi seçilen aralıkta dolanlar', 'gecmis' => 'Süresi şimdiden geçmiş olanlar', 'hepsi' => 'Geçerlilik tarihi olan tümü']]],
            'sutunlar' => [['adet', 'Belge sayısı', 1], ['belgeler', 'Belgeler', 1], ['en_yakin', 'En yakın bitiş', 1], ['birim', 'Birim', 1]]],
        'egitim' => ['ad' => 'Eğitim katılımı', 'ikon' => 'fa-graduation-cap', 'renk' => '#06b6d4',
            'aciklama' => 'Seçilen aralıktaki eğitimlere kim katılmış, kaçını tamamlamış, toplam kaç saat eğitim almış.',
            'siralamalar' => ['saat' => 'Eğitim saati (çoktan aza)', 'adet' => 'Katıldığı eğitim'] + $ad,
            'secenekler' => [['k' => 'detay', 'ad' => 'Eğitim listesini ekle', 'tip' => 'bool', 'v' => true]],
            'sutunlar' => [['adet', 'Atandığı eğitim', 1], ['tamam', 'Tamamladığı', 1], ['saat', 'Toplam saat', 1], ['oran', 'Tamamlama oranı', 1], ['birim', 'Birim', 1]]],
        'perf' => ['ad' => 'Performans', 'ikon' => 'fa-star', 'renk' => '#eab308',
            'aciklama' => 'Performans değerlendirmeleri: kişi başına ortalama puan, son puan ve dönem.',
            'siralamalar' => ['ort' => 'Ortalama puan (yüksekten düşüğe)', 'dusuk' => 'Ortalama puan (düşükten yükseğe)'] + $ad,
            'secenekler' => [['k' => 'detay', 'ad' => 'Tek tek değerlendirmeleri ekle', 'tip' => 'bool', 'v' => false]],
            'sutunlar' => [['adet', 'Değerlendirme', 1], ['ort', 'Ortalama puan', 1], ['son', 'Son puan', 1], ['donem', 'Son dönem', 1], ['birim', 'Birim', 1]]],
        'bordro_ozet' => ['ad' => 'Bordro özeti', 'ikon' => 'fa-file-invoice-dollar', 'renk' => '#22c55e',
            'aciklama' => 'Seçilen aylar için kişi başına brüt, net ve kesinti toplamları (maaş görme yetkisi gerekir).',
            'siralamalar' => ['net' => 'Net toplam (çoktan aza)', 'brut' => 'Brüt toplam (çoktan aza)'] + $ad,
            'secenekler' => [['k' => 'detay', 'ad' => 'Ay ay bordro listesini ekle', 'tip' => 'bool', 'v' => false]],
            'sutunlar' => [['adet', 'Bordro adedi', 1], ['brut', 'Brüt toplam', 1], ['kesinti', 'Kesinti', 1], ['net', 'Net toplam', 1], ['birim', 'Birim', 1]]],
        'dogum' => ['ad' => 'Doğum günleri ve iş yıldönümleri', 'ikon' => 'fa-cake-candles', 'renk' => '#ec4899',
            'aciklama' => 'Seçilen aralıktaki doğum günleri ve işe giriş yıldönümleri (kaçıncı yıl olduğuyla).',
            'siralamalar' => ['tarih' => 'Takvim sırasına göre'] + $ad,
            'secenekler' => [['k' => 'tur', 'ad' => 'Tür', 'tip' => 'sec', 'v' => '', 'sec' => ['' => 'Doğum günleri ve yıldönümleri', 'dogum' => 'Yalnızca doğum günleri', 'yildonumu' => 'Yalnızca iş yıldönümleri']]],
            'sutunlar' => [['tur', 'Tür', 1], ['tarih', 'Tarih', 1], ['yas', 'Yaş / Yıl', 1], ['birim', 'Birim', 1]]],
        'islem_log' => ['ad' => 'İşlem logları', 'ikon' => 'fa-clock-rotate-left', 'renk' => '#64748b',
            'aciklama' => 'Seçilen aralıkta sistemde kim, hangi işlemi yapmış: kişi ve işlem türü bazında özet ile kayıt listesi.',
            'siralamalar' => ['adet' => 'İşlem sayısı (çoktan aza)'] + $ad,
            'secenekler' => [['k' => 'kategori', 'ad' => 'İşlem türü', 'tip' => 'sec', 'v' => '', 'sec' => ['' => 'Tümü', 'Personel' => 'Personel', 'İzin' => 'İzin', 'Belge' => 'Belge', 'Zimmet' => 'Zimmet', 'Masraf' => 'Masraf', 'Avans' => 'Avans', 'Birim' => 'Birim', 'Ayar' => 'Ayar']],
                ['k' => 'detay', 'ad' => 'Tek tek kayıtları ekle (en fazla 500)', 'tip' => 'bool', 'v' => true]],
            'sutunlar' => [['adet', 'İşlem sayısı', 1], ['son', 'Son işlem', 1], ['turler', 'İşlem türleri', 1], ['birim', 'Birim', 1]]],
    ];
}

function rp_ek_ad_sirala(array &$s) { usort($s, function ($a, $b) { return strcmp(rp_kucuk($a['ad']), rp_kucuk($b['ad'])); }); }
function rp_ek_kidem($t)
{
    if (!$t || $t === '0000-00-00') return '';
    $a = (new DateTime($t))->diff(new DateTime('today')); if ($a->invert) return '';
    return ($a->y ? $a->y . ' yıl ' : '') . $a->m . ' ay';
}
function rp_ek_filtre($db, $p, array $satir)
{ // birim ve kişi seçimine göre ham personel satırlarını süzer (ayrılanlar da dahil)
    $b = []; foreach ($p['birim'] as $x) $b[rp_kucuk($x)] = true; $k = array_flip(array_map('intval', $p['kisi']));
    $o = [];
    foreach ($satir as $r) {
        if ($k && !isset($k[intval($r['kid'])])) continue;
        if ($b && !isset($b[rp_kucuk(rp_birim_adi($r))])) continue;
        $o[] = $r;
    }
    return $o;
}
function rp_ek_p_oku($db, $kosul = '1=1')
{
    $o = []; $q = $db->query("SELECT k.id kid, k.ad_soyad, k.gorev, k.telefon, k.email, k.dogum_tarihi, k.aktif, p.departman, p.unvan, p.ise_giris, p.isten_cikis, p.cikis_nedeni, p.calisma_sekli FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE $kosul ORDER BY k.ad_soyad");
    if ($q) while ($r = $q->fetch_assoc()) $o[] = $r; return $o;
}

function rp_personel_liste($db, array $p, array $kisiler)
{
    $ids = implode(',', array_keys($kisiler) ?: [0]); $d = $p['op']['durum'];
    $satirlar = []; $calisan = 0; $ayrilan = 0;
    foreach (rp_ek_p_oku($db, "k.id IN ($ids)") as $r) {
        $ayr = !empty($r['isten_cikis']) && $r['isten_cikis'] !== '0000-00-00';
        if ($d === 'calisan' && $ayr || $d === 'ayrilan' && !$ayr) continue;
        $ayr ? $ayrilan++ : $calisan++;
        $satirlar[] = ['ad' => $r['ad_soyad'], 'unvan' => (string)($r['unvan'] ?: $r['gorev']), 'birim' => rp_birim_adi($r), 'ise_giris' => rp_tr($r['ise_giris']), 'kidem' => rp_ek_kidem($r['ise_giris']),
            'calisma' => (IK_CALISMA[$r['calisma_sekli']] ?? ''), 'telefon' => (string)$r['telefon'], 'email' => (string)$r['email'], 'dogum' => rp_tr($r['dogum_tarihi']), 'durum' => $ayr ? 'Ayrıldı (' . rp_tr($r['isten_cikis']) . ')' : 'Çalışıyor',
            '_t' => $r['ise_giris'] ?: '9999', '_b' => rp_kucuk(rp_birim_adi($r)), '_c' => $ayr ? 'uyari' : ''];
    }
    if ($p['sirala'] === 'ise_giris') usort($satirlar, function ($a, $b) { return strcmp($a['_t'], $b['_t']); });
    elseif ($p['sirala'] === 'birim') usort($satirlar, function ($a, $b) { return strcmp($a['_b'], $b['_b']) ?: strcmp(rp_kucuk($a['ad']), rp_kucuk($b['ad'])); });
    else rp_ek_ad_sirala($satirlar);
    $r = rp_iskelet('Personel listesi', rp_alt_yazi($p, count($satirlar)));
    $r['ozet'] = [['Toplam personel', count($satirlar), 'mavi'], ['Çalışan', $calisan, 'yesil'], ['Ayrılan', $ayrilan, 'turuncu']];
    $r['sutunlar'] = rp_sutun_sec('personel_liste', $p['sutun']); $r['satirlar'] = $satirlar;
    $say = []; foreach ($satirlar as $s) $say[$s['birim']] = ($say[$s['birim']] ?? 0) + 1; arsort($say);
    $renk = ['#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#0ea5e9', '#8b5cf6', '#14b8a6', '#eab308', '#64748b']; $i = 0; $dil = [];
    foreach ($say as $b => $n) $dil[] = ['ad' => $b, 'deger' => $n, 'renk' => $renk[$i++ % 9]];
    if ($dil) $r['grafikler'][] = ['baslik' => 'Birimlere göre personel', 'tip' => 'halka', 'birim' => 'kişi', 'dilimler' => $dil];
    return $r;
}

function rp_ise_hareket($db, array $p, array $kisiler)
{
    $b = $db->real_escape_string($p['bas']); $e = $db->real_escape_string($p['bit']);
    $ham = rp_ek_filtre($db, $p, rp_ek_p_oku($db, "(p.ise_giris BETWEEN '$b' AND '$e' OR p.isten_cikis BETWEEN '$b' AND '$e')"));
    $satirlar = []; $g = 0; $c = 0; $t = $p['op']['tur'];
    foreach ($ham as $r) {
        if ($r['ise_giris'] && $r['ise_giris'] >= $p['bas'] && $r['ise_giris'] <= $p['bit'] && $t !== 'cikis') { $g++; $satirlar[] = ['ad' => $r['ad_soyad'], 'hareket' => 'İşe giriş', 'tarih' => rp_tr($r['ise_giris']), 'unvan' => (string)($r['unvan'] ?: $r['gorev']), 'birim' => rp_birim_adi($r), 'neden' => '', '_t' => $r['ise_giris'], '_c' => '', '_h' => ['hareket' => 'yesil']]; }
        if ($r['isten_cikis'] && $r['isten_cikis'] >= $p['bas'] && $r['isten_cikis'] <= $p['bit'] && $t !== 'giris') { $c++; $satirlar[] = ['ad' => $r['ad_soyad'], 'hareket' => 'Ayrılış', 'tarih' => rp_tr($r['isten_cikis']), 'unvan' => (string)($r['unvan'] ?: $r['gorev']), 'birim' => rp_birim_adi($r), 'neden' => (string)$r['cikis_nedeni'], '_t' => $r['isten_cikis'], '_c' => 'uyari', '_h' => ['hareket' => 'kirmizi']]; }
    }
    if ($p['sirala'] === 'ad') rp_ek_ad_sirala($satirlar); else usort($satirlar, function ($a, $b) { return strcmp($b['_t'], $a['_t']); });
    $r = rp_iskelet('İşe giriş / ayrılış raporu', rp_tr($p['bas']) . ' – ' . rp_tr($p['bit']) . ' · ' . count($satirlar) . ' hareket');
    $r['ozet'] = [['İşe giriş', $g, 'yesil'], ['Ayrılış', $c, 'kirmizi'], ['Net değişim', ($g - $c > 0 ? '+' : '') . ($g - $c), 'mavi']];
    $r['sutunlar'] = rp_sutun_sec('ise_hareket', $p['sutun']); $r['satirlar'] = $satirlar;
    $r['grafikler'][] = ['baslik' => 'Giriş ve ayrılış', 'tip' => 'halka', 'birim' => 'kişi', 'dilimler' => [['ad' => 'İşe giriş', 'deger' => $g, 'renk' => '#10b981'], ['ad' => 'Ayrılış', 'deger' => $c, 'renk' => '#f43f5e']]];
    return $r;
}

function rp_masraf($db, array $p, array $kisiler)
{
    $ids = implode(',', array_keys($kisiler) ?: [0]); $d = in_array($p['op']['durum'], ['onaylandi', 'beklemede', 'reddedildi'], true) ? " AND m.durum = '" . $p['op']['durum'] . "'" : '';
    $q = $db->query("SELECT m.*, o.ad_soyad onaylayan FROM ik_masraflar m LEFT JOIN kullanicilar o ON o.id = m.onaylayan_id WHERE m.kullanici_id IN ($ids) AND m.tarih BETWEEN '{$p['bas']}' AND '{$p['bit']}'$d ORDER BY m.tarih, m.id");
    $per = []; $det = []; $kat = []; $top = ['onaylandi' => 0, 'beklemede' => 0, 'reddedildi' => 0]; $DUR = ['onaylandi' => 'Onaylandı', 'beklemede' => 'Onay bekliyor', 'reddedildi' => 'Reddedildi'];
    if ($q) while ($m = $q->fetch_assoc()) {
        $k = intval($m['kullanici_id']); if (!isset($kisiler[$k])) continue; $dd = isset($DUR[$m['durum']]) ? $m['durum'] : 'beklemede'; $t = (float)$m['tutar'];
        if (!isset($per[$k])) $per[$k] = ['adet' => 0, 'onaylandi' => 0, 'beklemede' => 0, 'reddedildi' => 0];
        $per[$k]['adet']++; $per[$k][$dd] += $t; $top[$dd] += $t; if ($dd !== 'reddedildi') $kat[$m['kategori']] = ($kat[$m['kategori']] ?? 0) + $t;
        $det[] = ['ad' => $kisiler[$k]['ad'], 'tarih' => rp_tr($m['tarih']), 'kategori' => (string)$m['kategori'], 'tutar' => rp_para($t), 'durum' => $DUR[$dd], 'onaylayan' => (string)($m['onaylayan'] ?? ''), 'aciklama' => (string)$m['aciklama'], '_c' => $dd === 'reddedildi' ? 'hata' : ($dd === 'beklemede' ? 'uyari' : '')];
    }
    $satirlar = [];
    foreach ($per as $k => $o) { $tp = $o['onaylandi'] + $o['beklemede'] + $o['reddedildi'];
        $satirlar[] = ['ad' => $kisiler[$k]['ad'], 'birim' => $kisiler[$k]['birim'], 'adet' => $o['adet'], 'onayli' => rp_para($o['onaylandi']), 'bekleyen' => rp_para($o['beklemede']), 'red' => rp_para($o['reddedildi']), 'toplam' => rp_para($tp), '_tutar' => $tp, '_adet' => $o['adet'],
            '_h' => ['onayli' => $o['onaylandi'] > 0 ? 'yesil' : '', 'bekleyen' => $o['beklemede'] > 0 ? 'turuncu' : '', 'red' => $o['reddedildi'] > 0 ? 'kirmizi' : '']]; }
    if ($p['sirala'] === 'ad') rp_ek_ad_sirala($satirlar); else rp_sirala($satirlar, $p['sirala'] === 'adet' ? 'adet' : 'tutar', true);
    $r = rp_iskelet('Masraf raporu', rp_alt_yazi($p, count($kisiler)));
    $r['ozet'] = [['Masraf yapan', count($satirlar), 'mavi'], ['Onaylanan', rp_para($top['onaylandi']), 'yesil'], ['Onay bekleyen', rp_para($top['beklemede']), 'turuncu'], ['Reddedilen', rp_para($top['reddedildi']), 'kirmizi']];
    $r['sutunlar'] = rp_sutun_sec('masraf', $p['sutun']); $r['satirlar'] = $satirlar;
    if ($satirlar) $r['grafikler'][] = ['baslik' => 'Kişi başına masraf (₺)', 'tip' => 'yatay', 'birim' => '₺', 'etiketler' => array_column(array_slice($satirlar, 0, 15), 'ad'), 'seriler' => [['ad' => 'Toplam', 'renk' => '#ef4444', 'veri' => array_map(function ($s) { return round($s['_tutar'], 2); }, array_slice($satirlar, 0, 15))]]];
    if ($kat) { arsort($kat); $renk = ['#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#0ea5e9', '#8b5cf6', '#14b8a6', '#eab308', '#64748b']; $i = 0; $dil = []; foreach ($kat as $kk => $v) $dil[] = ['ad' => $kk, 'deger' => round($v, 2), 'renk' => $renk[$i++ % 9]]; $r['grafikler'][] = ['baslik' => 'Kategoriye göre masraf (₺)', 'tip' => 'halka', 'birim' => '₺', 'dilimler' => $dil]; }
    if ($p['op']['detay'] && $det) $r['detaylar'][] = ['baslik' => 'Masraf kayıtları', 'sutunlar' => [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l'], ['k' => 'tarih', 'ad' => 'Tarih', 'h' => 'l'], ['k' => 'kategori', 'ad' => 'Kategori', 'h' => 'l'], ['k' => 'tutar', 'ad' => 'Tutar', 'h' => 'r'], ['k' => 'durum', 'ad' => 'Durum', 'h' => 'l'], ['k' => 'onaylayan', 'ad' => 'Karar veren', 'h' => 'l'], ['k' => 'aciklama', 'ad' => 'Açıklama', 'h' => 'l']], 'satirlar' => $det];
    return $r;
}

function rp_zimmet($db, array $p, array $kisiler)
{
    $ids = implode(',', array_keys($kisiler) ?: [0]);
    $w = $p['op']['kapsam'] === 'aralik' ? "AND z.teslim_tarihi BETWEEN '{$p['bas']}' AND '{$p['bit']}'" : "AND z.durum = 'zimmetli'";
    $q = $db->query("SELECT z.* FROM ik_zimmetler z WHERE z.kullanici_id IN ($ids) $w ORDER BY z.teslim_tarihi, z.id");
    $per = []; $det = []; $zt = 0; $it = 0;
    if ($q) while ($z = $q->fetch_assoc()) {
        $k = intval($z['kullanici_id']); if (!isset($kisiler[$k])) continue; $iade = $z['durum'] === 'iade';
        if (!isset($per[$k])) $per[$k] = ['z' => 0, 'i' => 0, 'e' => []]; if ($iade) { $per[$k]['i']++; $it++; } else { $per[$k]['z']++; $zt++; $per[$k]['e'][] = $z['esya']; }
        $det[] = ['ad' => $kisiler[$k]['ad'], 'esya' => $z['esya'] . ($z['seri_no'] !== '' ? ' (' . $z['seri_no'] . ')' : ''), 'teslim' => rp_tr($z['teslim_tarihi']), 'iade' => rp_tr($z['iade_tarihi']), 'durum' => $iade ? 'İade edildi' : 'Zimmetli', 'aciklama' => (string)$z['aciklama'], '_c' => $iade ? '' : 'uyari'];
    }
    $satirlar = [];
    foreach ($per as $k => $o) $satirlar[] = ['ad' => $kisiler[$k]['ad'], 'birim' => $kisiler[$k]['birim'], 'zimmetli' => $o['z'], 'iade' => $o['i'], 'esyalar' => implode(', ', array_slice($o['e'], 0, 6)) . (count($o['e']) > 6 ? ' +' . (count($o['e']) - 6) : ''), '_adet' => $o['z']];
    if ($p['sirala'] === 'ad') rp_ek_ad_sirala($satirlar); else rp_sirala($satirlar, 'adet', true);
    $r = rp_iskelet('Zimmet raporu', rp_alt_yazi($p, count($kisiler)));
    $r['ozet'] = [['Zimmeti olan kişi', count($satirlar), 'mavi'], ['Zimmetli eşya', $zt, 'turuncu'], ['İade edilen', $it, 'yesil']];
    $r['sutunlar'] = rp_sutun_sec('zimmet', $p['sutun']); $r['satirlar'] = $satirlar;
    if ($p['op']['detay'] && $det) $r['detaylar'][] = ['baslik' => 'Zimmet kayıtları', 'sutunlar' => [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l'], ['k' => 'esya', 'ad' => 'Eşya', 'h' => 'l'], ['k' => 'teslim', 'ad' => 'Teslim', 'h' => 'l'], ['k' => 'iade', 'ad' => 'İade', 'h' => 'l'], ['k' => 'durum', 'ad' => 'Durum', 'h' => 'l'], ['k' => 'aciklama', 'ad' => 'Açıklama', 'h' => 'l']], 'satirlar' => $det];
    return $r;
}

function rp_belge_sure($db, array $p, array $kisiler)
{
    $ids = implode(',', array_keys($kisiler) ?: [0]); $kap = $p['op']['kapsam']; $bugun = date('Y-m-d');
    $w = $kap === 'gecmis' ? "b.gecerlilik < '$bugun'" : ($kap === 'hepsi' ? "1=1" : "b.gecerlilik BETWEEN '{$p['bas']}' AND '{$p['bit']}'");
    $q = $db->query("SELECT b.* FROM ik_belgeler b WHERE b.kullanici_id IN ($ids) AND b.gecerlilik IS NOT NULL AND $w ORDER BY b.gecerlilik, b.id");
    $per = []; $det = []; $gec = 0;
    if ($q) while ($b = $q->fetch_assoc()) {
        $k = intval($b['kullanici_id']); if (!isset($kisiler[$k])) continue; $ad = $b['ad'] ?: (IK_BELGE_TURLERI[$b['tur']] ?? $b['tur']); $kalan = (int)round((strtotime($b['gecerlilik']) - strtotime($bugun)) / 86400); if ($kalan < 0) $gec++;
        if (!isset($per[$k])) $per[$k] = ['n' => 0, 'b' => [], 'en' => $b['gecerlilik']]; $per[$k]['n']++; $per[$k]['b'][] = $ad;
        $det[] = ['ad' => $kisiler[$k]['ad'], 'belge' => $ad, 'tur' => IK_BELGE_TURLERI[$b['tur']] ?? $b['tur'], 'gecerlilik' => rp_tr($b['gecerlilik']), 'kalan' => $kalan < 0 ? 'Süresi doldu (' . abs($kalan) . ' gün)' : $kalan . ' gün', '_c' => $kalan < 0 ? 'hata' : ($kalan <= 30 ? 'uyari' : '')];
    }
    $satirlar = [];
    foreach ($per as $k => $o) $satirlar[] = ['ad' => $kisiler[$k]['ad'], 'birim' => $kisiler[$k]['birim'], 'adet' => $o['n'], 'belgeler' => implode(', ', array_slice($o['b'], 0, 5)) . (count($o['b']) > 5 ? ' +' . (count($o['b']) - 5) : ''), 'en_yakin' => rp_tr($o['en']), '_t' => $o['en']];
    if ($p['sirala'] === 'ad') rp_ek_ad_sirala($satirlar); else usort($satirlar, function ($a, $b) { return strcmp($a['_t'], $b['_t']); });
    $r = rp_iskelet('Belge geçerlilik raporu', rp_alt_yazi($p, count($kisiler)));
    $r['ozet'] = [['Belgesi olan kişi', count($satirlar), 'mavi'], ['Toplam belge', count($det), 'turuncu'], ['Süresi geçmiş', $gec, 'kirmizi']];
    $r['sutunlar'] = rp_sutun_sec('belge_sure', $p['sutun']); $r['satirlar'] = $satirlar;
    if ($det) $r['detaylar'][] = ['baslik' => 'Belgeler', 'sutunlar' => [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l'], ['k' => 'belge', 'ad' => 'Belge', 'h' => 'l'], ['k' => 'tur', 'ad' => 'Tür', 'h' => 'l'], ['k' => 'gecerlilik', 'ad' => 'Geçerlilik', 'h' => 'l'], ['k' => 'kalan', 'ad' => 'Kalan', 'h' => 'l']], 'satirlar' => $det];
    return $r;
}

function rp_egitim($db, array $p, array $kisiler)
{
    $ids = implode(',', array_keys($kisiler) ?: [0]);
    $q = $db->query("SELECT e.id eid, e.ad, e.egitmen, e.tarih, e.sure_saat, k.kullanici_id, k.tamamlandi FROM ik_egitim_katilim k JOIN ik_egitimler e ON e.id = k.egitim_id WHERE k.kullanici_id IN ($ids) AND e.tarih BETWEEN '{$p['bas']}' AND '{$p['bit']}' ORDER BY e.tarih, e.id");
    $per = []; $egt = []; $toplamSaat = 0;
    if ($q) while ($x = $q->fetch_assoc()) {
        $k = intval($x['kullanici_id']); if (!isset($kisiler[$k])) continue; if (!isset($per[$k])) $per[$k] = ['a' => 0, 't' => 0, 's' => 0.0];
        $per[$k]['a']++; if ($x['tamamlandi']) { $per[$k]['t']++; $per[$k]['s'] += (float)$x['sure_saat']; $toplamSaat += (float)$x['sure_saat']; }
        $e = &$egt[$x['eid']]; if (!isset($e)) $e = ['ad' => $x['ad'], 'tarih' => rp_tr($x['tarih']), 'egitmen' => (string)$x['egitmen'], 'saat' => rp_gun_say($x['sure_saat']), 'katilimci' => 0, 'tamam' => 0]; $e['katilimci']++; if ($x['tamamlandi']) $e['tamam']++; unset($e);
    }
    $satirlar = [];
    foreach ($per as $k => $o) $satirlar[] = ['ad' => $kisiler[$k]['ad'], 'birim' => $kisiler[$k]['birim'], 'adet' => $o['a'], 'tamam' => $o['t'], 'saat' => rp_gun_say($o['s']), 'oran' => '%' . round($o['t'] / max(1, $o['a']) * 100), '_saat' => $o['s'], '_adet' => $o['a'], '_h' => ['oran' => $o['t'] >= $o['a'] ? 'yesil' : 'turuncu']];
    if ($p['sirala'] === 'ad') rp_ek_ad_sirala($satirlar); else rp_sirala($satirlar, $p['sirala'] === 'adet' ? 'adet' : 'saat', true);
    $r = rp_iskelet('Eğitim katılım raporu', rp_alt_yazi($p, count($kisiler)));
    $r['ozet'] = [['Eğitim sayısı', count($egt), 'mavi'], ['Katılan kişi', count($satirlar), 'mor'], ['Tamamlanan eğitim saati', rp_gun_say($toplamSaat), 'yesil']];
    $r['sutunlar'] = rp_sutun_sec('egitim', $p['sutun']); $r['satirlar'] = $satirlar;
    if ($satirlar) $r['grafikler'][] = ['baslik' => 'Kişi başına tamamlanan eğitim saati', 'tip' => 'yatay', 'birim' => 'saat', 'etiketler' => array_column(array_slice($satirlar, 0, 15), 'ad'), 'seriler' => [['ad' => 'Saat', 'renk' => '#06b6d4', 'veri' => array_map(function ($s) { return round($s['_saat'], 1); }, array_slice($satirlar, 0, 15))]]];
    if ($p['op']['detay'] && $egt) $r['detaylar'][] = ['baslik' => 'Eğitimler', 'sutunlar' => [['k' => 'ad', 'ad' => 'Eğitim', 'h' => 'l'], ['k' => 'tarih', 'ad' => 'Tarih', 'h' => 'l'], ['k' => 'egitmen', 'ad' => 'Eğitmen', 'h' => 'l'], ['k' => 'saat', 'ad' => 'Süre (saat)', 'h' => 'r'], ['k' => 'katilimci', 'ad' => 'Katılımcı', 'h' => 'r'], ['k' => 'tamam', 'ad' => 'Tamamlayan', 'h' => 'r']], 'satirlar' => array_values($egt)];
    return $r;
}

function rp_perf($db, array $p, array $kisiler)
{
    $ids = implode(',', array_keys($kisiler) ?: [0]);
    $q = $db->query("SELECT f.*, d.ad_soyad degerlendiren FROM ik_performans f LEFT JOIN kullanicilar d ON d.id = f.degerlendiren_id WHERE f.kullanici_id IN ($ids) AND DATE(f.tarih) BETWEEN '{$p['bas']}' AND '{$p['bit']}' ORDER BY f.tarih, f.id");
    $per = []; $det = [];
    if ($q) while ($f = $q->fetch_assoc()) {
        $k = intval($f['kullanici_id']); if (!isset($kisiler[$k])) continue; if (!isset($per[$k])) $per[$k] = ['n' => 0, 't' => 0.0, 'son' => 0, 'd' => ''];
        $per[$k]['n']++; $per[$k]['t'] += (float)$f['puan']; $per[$k]['son'] = (float)$f['puan']; $per[$k]['d'] = $f['donem'];
        $det[] = ['ad' => $kisiler[$k]['ad'], 'donem' => $f['donem'], 'puan' => rp_gun_say($f['puan']), 'degerlendiren' => (string)$f['degerlendiren'], 'tarih' => rp_tr(substr($f['tarih'], 0, 10)), 'not' => (string)$f['notlar']];
    }
    $satirlar = []; $genel = 0; $say = 0;
    foreach ($per as $k => $o) { $ort = $o['t'] / max(1, $o['n']); $genel += $ort; $say++;
        $satirlar[] = ['ad' => $kisiler[$k]['ad'], 'birim' => $kisiler[$k]['birim'], 'adet' => $o['n'], 'ort' => rp_gun_say(round($ort, 1)), 'son' => rp_gun_say($o['son']), 'donem' => $o['d'], '_ort' => $ort, '_h' => ['ort' => $ort >= 4 ? 'yesil' : ($ort < 2.5 ? 'kirmizi' : '')]]; }
    if ($p['sirala'] === 'ad') rp_ek_ad_sirala($satirlar); else rp_sirala($satirlar, 'ort', $p['sirala'] !== 'dusuk');
    $r = rp_iskelet('Performans raporu', rp_alt_yazi($p, count($kisiler)));
    $r['ozet'] = [['Değerlendirilen kişi', $say, 'mavi'], ['Değerlendirme', count($det), 'mor'], ['Genel ortalama', $say ? rp_gun_say(round($genel / $say, 2)) : '—', 'yesil']];
    $r['sutunlar'] = rp_sutun_sec('perf', $p['sutun']); $r['satirlar'] = $satirlar;
    if ($satirlar) $r['grafikler'][] = ['baslik' => 'Ortalama puan', 'tip' => 'yatay', 'birim' => 'puan', 'etiketler' => array_column(array_slice($satirlar, 0, 15), 'ad'), 'seriler' => [['ad' => 'Ortalama', 'renk' => '#eab308', 'veri' => array_map(function ($s) { return round($s['_ort'], 2); }, array_slice($satirlar, 0, 15))]]];
    if ($p['op']['detay'] && $det) $r['detaylar'][] = ['baslik' => 'Değerlendirmeler', 'sutunlar' => [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l'], ['k' => 'donem', 'ad' => 'Dönem', 'h' => 'l'], ['k' => 'puan', 'ad' => 'Puan', 'h' => 'r'], ['k' => 'degerlendiren', 'ad' => 'Değerlendiren', 'h' => 'l'], ['k' => 'tarih', 'ad' => 'Tarih', 'h' => 'l'], ['k' => 'not', 'ad' => 'Not', 'h' => 'l']], 'satirlar' => $det];
    return $r;
}

function rp_bordro_ozet($db, array $p, array $kisiler)
{
    $ids = implode(',', array_keys($kisiler) ?: [0]);
    $b = (int)date('Ym', strtotime($p['bas'])); $e = (int)date('Ym', strtotime($p['bit']));
    $q = $db->query("SELECT * FROM ik_bordrolar WHERE kullanici_id IN ($ids) AND (yil * 100 + ay) BETWEEN $b AND $e ORDER BY yil, ay");
    $per = []; $det = []; $AY = ['', 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık']; $tb = 0; $tn = 0; $tk = 0;
    if ($q) while ($x = $q->fetch_assoc()) {
        $k = intval($x['kullanici_id']); if (!isset($kisiler[$k])) continue; if (!isset($per[$k])) $per[$k] = ['n' => 0, 'b' => 0, 'k' => 0, 'nt' => 0];
        $per[$k]['n']++; $per[$k]['b'] += $x['brut']; $per[$k]['k'] += $x['kesinti']; $per[$k]['nt'] += $x['net']; $tb += $x['brut']; $tn += $x['net']; $tk += $x['kesinti'];
        $det[] = ['ad' => $kisiler[$k]['ad'], 'donem' => $AY[(int)$x['ay']] . ' ' . $x['yil'], 'brut' => rp_para($x['brut']), 'kesinti' => rp_para($x['kesinti']), 'net' => rp_para($x['net'])];
    }
    $satirlar = [];
    foreach ($per as $k => $o) $satirlar[] = ['ad' => $kisiler[$k]['ad'], 'birim' => $kisiler[$k]['birim'], 'adet' => $o['n'], 'brut' => rp_para($o['b']), 'kesinti' => rp_para($o['k']), 'net' => rp_para($o['nt']), '_net' => $o['nt'], '_brut' => $o['b']];
    if ($p['sirala'] === 'ad') rp_ek_ad_sirala($satirlar); else rp_sirala($satirlar, $p['sirala'] === 'brut' ? 'brut' : 'net', true);
    $r = rp_iskelet('Bordro özeti', rp_tr($p['bas']) . ' – ' . rp_tr($p['bit']) . ' · ' . count($satirlar) . ' kişi');
    $r['ozet'] = [['Bordrosu olan kişi', count($satirlar), 'mavi'], ['Brüt toplam', rp_para($tb), 'mor'], ['Kesinti toplam', rp_para($tk), 'turuncu'], ['Net toplam', rp_para($tn), 'yesil']];
    $r['sutunlar'] = rp_sutun_sec('bordro_ozet', $p['sutun']); $r['satirlar'] = $satirlar;
    if ($satirlar) $r['grafikler'][] = ['baslik' => 'Kişi başına net ödeme (₺)', 'tip' => 'yatay', 'birim' => '₺', 'etiketler' => array_column(array_slice($satirlar, 0, 15), 'ad'), 'seriler' => [['ad' => 'Net', 'renk' => '#22c55e', 'veri' => array_map(function ($s) { return round($s['_net'], 2); }, array_slice($satirlar, 0, 15))]]];
    if ($p['op']['detay'] && $det) $r['detaylar'][] = ['baslik' => 'Bordro kayıtları', 'sutunlar' => [['k' => 'ad', 'ad' => 'Personel', 'h' => 'l'], ['k' => 'donem', 'ad' => 'Dönem', 'h' => 'l'], ['k' => 'brut', 'ad' => 'Brüt', 'h' => 'r'], ['k' => 'kesinti', 'ad' => 'Kesinti', 'h' => 'r'], ['k' => 'net', 'ad' => 'Net', 'h' => 'r']], 'satirlar' => $det];
    return $r;
}

function rp_dogum($db, array $p, array $kisiler)
{
    $ids = implode(',', array_keys($kisiler) ?: [0]); $t = $p['op']['tur']; $satirlar = []; $d = 0; $y = 0;
    $bas = strtotime($p['bas']); $bit = strtotime($p['bit']);
    foreach (rp_ek_p_oku($db, "k.id IN ($ids)") as $r) {
        foreach (['dogum' => $r['dogum_tarihi'], 'yildonumu' => $r['ise_giris']] as $tip => $tar) {
            if ($t !== '' && $t !== $tip) continue; if (!$tar || $tar === '0000-00-00') continue;
            $ay = date('m-d', strtotime($tar));
            for ($yil = (int)date('Y', $bas); $yil <= (int)date('Y', $bit); $yil++) {
                $gun = ($ay === '02-29' && !checkdate(2, 29, $yil)) ? '02-28' : $ay; $x = strtotime("$yil-$gun"); if ($x < $bas || $x > $bit) continue;
                $yas = $yil - (int)date('Y', strtotime($tar)); if ($tip === 'yildonumu' && $yas < 1) continue;
                $tip === 'dogum' ? $d++ : $y++;
                $satirlar[] = ['ad' => $r['ad_soyad'], 'birim' => rp_birim_adi($r), 'tur' => $tip === 'dogum' ? '🎂 Doğum günü' : '🎉 İş yıldönümü', 'tarih' => date('d.m.Y', $x), 'yas' => $yas . ($tip === 'dogum' ? '. yaş' : '. yıl'), '_t' => date('Y-m-d', $x)];
            }
        }
    }
    if ($p['sirala'] === 'ad') rp_ek_ad_sirala($satirlar); else usort($satirlar, function ($a, $b) { return strcmp($a['_t'], $b['_t']); });
    $r = rp_iskelet('Doğum günleri ve iş yıldönümleri', rp_tr($p['bas']) . ' – ' . rp_tr($p['bit']) . ' · ' . count($satirlar) . ' kayıt');
    $r['ozet'] = [['Doğum günü', $d, 'mor'], ['İş yıldönümü', $y, 'mavi']];
    $r['sutunlar'] = rp_sutun_sec('dogum', $p['sutun']); $r['satirlar'] = $satirlar;
    return $r;
}

function rp_islem_log($db, array $p, array $kisiler)
{
    require_once __DIR__ . '/ik_ek.php'; ik_ek_sema($db);
    $kat = $p['op']['kategori'] !== '' ? " AND l.kategori = '" . $db->real_escape_string($p['op']['kategori']) . "'" : '';
    $q = $db->query("SELECT l.*, u.ad_soyad yapan, h.ad_soyad hedef FROM ik_islem_log l LEFT JOIN kullanicilar u ON u.id = l.kullanici_id LEFT JOIN kullanicilar h ON h.id = l.hedef_id WHERE l.tarih BETWEEN '{$p['bas']} 00:00:00' AND '{$p['bit']} 23:59:59'$kat ORDER BY l.id DESC");
    $per = []; $det = []; $top = 0; $turSay = [];
    if ($q) while ($l = $q->fetch_assoc()) {
        $top++; $k = intval($l['kullanici_id']); $ad = $l['yapan'] ?: 'Sistem';
        if (!isset($per[$k])) $per[$k] = ['ad' => $ad, 'n' => 0, 'son' => $l['tarih'], 'tur' => []]; $per[$k]['n']++; $per[$k]['tur'][$l['kategori']] = true; $turSay[$l['kategori']] = ($turSay[$l['kategori']] ?? 0) + 1;
        if (count($det) < 500) $det[] = ['tarih' => date('d.m.Y H:i', strtotime($l['tarih'])), 'yapan' => $ad, 'kategori' => $l['kategori'], 'aciklama' => $l['aciklama'], 'hedef' => (string)$l['hedef'], 'ip' => $l['ip']];
    }
    $bm = []; foreach ($kisiler as $id => $x) $bm[$id] = $x['birim'];
    $satirlar = [];
    foreach ($per as $k => $o) $satirlar[] = ['ad' => $o['ad'], 'birim' => $bm[$k] ?? '', 'adet' => $o['n'], 'son' => date('d.m.Y H:i', strtotime($o['son'])), 'turler' => implode(', ', array_keys($o['tur'])), '_adet' => $o['n']];
    if ($p['sirala'] === 'ad') rp_ek_ad_sirala($satirlar); else rp_sirala($satirlar, 'adet', true);
    $r = rp_iskelet('İşlem logları raporu', rp_tr($p['bas']) . ' – ' . rp_tr($p['bit']) . ' · ' . $top . ' işlem');
    $r['ozet'] = [['Toplam işlem', $top, 'mavi'], ['İşlem yapan kişi', count($satirlar), 'mor'], ['İşlem türü', count($turSay), 'turuncu']];
    $r['sutunlar'] = rp_sutun_sec('islem_log', $p['sutun']); $r['satirlar'] = $satirlar;
    if ($turSay) { arsort($turSay); $renk = ['#6366f1', '#10b981', '#f59e0b', '#f43f5e', '#0ea5e9', '#8b5cf6', '#14b8a6', '#eab308', '#64748b']; $i = 0; $dil = []; foreach ($turSay as $k => $n) $dil[] = ['ad' => $k, 'deger' => $n, 'renk' => $renk[$i++ % 9]]; $r['grafikler'][] = ['baslik' => 'İşlem türlerine göre dağılım', 'tip' => 'halka', 'birim' => 'işlem', 'dilimler' => $dil]; }
    if ($p['op']['detay'] && $det) $r['detaylar'][] = ['baslik' => 'İşlem kayıtları' . ($top > 500 ? ' (son 500)' : ''), 'sutunlar' => [['k' => 'tarih', 'ad' => 'Tarih', 'h' => 'l'], ['k' => 'yapan', 'ad' => 'İşlemi yapan', 'h' => 'l'], ['k' => 'kategori', 'ad' => 'Tür', 'h' => 'l'], ['k' => 'aciklama', 'ad' => 'İşlem', 'h' => 'l'], ['k' => 'hedef', 'ad' => 'İlgili kişi', 'h' => 'l'], ['k' => 'ip', 'ad' => 'IP', 'h' => 'l']], 'satirlar' => $det];
    return $r;
}
