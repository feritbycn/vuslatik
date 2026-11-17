<?php
// ============================================================
//  Günlük "giriş yapmayanlar" raporu (e-posta)
//  Ayarlar (giris_ayarlar): gunluk_rapor (aç/kapat), gunluk_rapor_saat (SS:DD), gunluk_rapor_alicilar (virgülle e-posta),
//  gunluk_rapor_gec (geç girenleri de göster). Tetikleme: giriş yapmış kullanıcıların bildirim yoklaması (~20 sn) ya da cron:
//    * * * * * php /yol/giris_rapor_cron.php
// ============================================================
require_once __DIR__ . '/giris_ortak.php';
require_once __DIR__ . '/ik_kanal.php';

function g_rp_dk($t) { return preg_match('/^(\d{1,2}):(\d{2})/', (string)$t, $m) ? intval($m[1]) * 60 + intval($m[2]) : null; }

/** Alıcı metnini temiz e-posta listesine çevirir */
function giris_rapor_alicilar($metin)
{
    $out = [];
    foreach (preg_split('/[\s,;]+/', (string)$metin, -1, PREG_SPLIT_NO_EMPTY) as $m) { $m = strtolower(trim($m)); if (filter_var($m, FILTER_VALIDATE_EMAIL)) $out[$m] = $m; }
    return array_values($out);
}

/** Bugünün listesi: ['gelmeyen' => [...], 'gec' => [...], 'tarih' => 'Y-m-d', 'calisma_gunu' => bool] */
function giris_rapor_hazirla($db, $A = null)
{
    $A = $A ?: giris_ayarlar($db);
    $bugun = (string)$db->query("SELECT CURDATE()")->fetch_row()[0];
    $simdi = g_rp_dk(date('H:i'));
    $hg = intval(date('N'));
    $gunler = array_map('intval', explode(',', (string)$A['calisma_gunleri']));
    $res = ['gelmeyen' => [], 'gec' => [], 'tarih' => $bugun, 'calisma_gunu' => in_array($hg, $gunler, true), 'toplam' => 0];
    if (!$res['calisma_gunu']) return $res;
    $tol = intval($A['tolerans_dk']); $vBas = g_rp_dk($A['mesai_baslangic']); $vBit = g_rp_dk($A['mesai_bitis']);
    $ozel = []; $q = $db->query("SELECT kullanici_id, baslangic, bitis FROM giris_vardiyalar"); if ($q) while ($r = $q->fetch_assoc()) $ozel[intval($r['kullanici_id'])] = $r;
    // izinli / raporlu / tatil olanlar rapora girmez
    $muaf = [];
    try { $q = $db->query("SELECT kullanici_id FROM ik_izin_talepleri WHERE durum = 'onaylandi' AND baslangic <= '$bugun' AND bitis >= '$bugun'"); if ($q) while ($r = $q->fetch_row()) $muaf[intval($r[0])] = 'izinli'; } catch (Throwable $e) {}
    try { $q = $db->query("SELECT kullanici_id FROM ik_puantaj WHERE tarih = '$bugun' AND durum IN ('izinli','rapor','resmi_tatil','haftasonu')"); if ($q) while ($r = $q->fetch_row()) $muaf[intval($r[0])] = 'izinli'; } catch (Throwable $e) {}
    $ilk = []; $q = $db->query("SELECT kullanici_id, MIN(giris_zamani) z FROM giris_kayitlari WHERE giris_zamani >= '$bugun 00:00:00' GROUP BY kullanici_id"); if ($q) while ($r = $q->fetch_row()) $ilk[intval($r[0])] = $r[1];
    $q = $db->query("SELECT id, ad_soyad, gorev FROM kullanicilar WHERE aktif = 1 ORDER BY ad_soyad");
    if ($q) while ($u = $q->fetch_assoc()) {
        $id = intval($u['id']); if (isset($muaf[$id])) continue;
        $vb = $vBas; if (isset($ozel[$id]) && g_rp_dk($ozel[$id]['baslangic']) !== null) $vb = g_rp_dk($ozel[$id]['baslangic']);
        if ($vb === null) continue;
        $res['toplam']++;
        if (!isset($ilk[$id])) {
            if ($simdi !== null && $simdi < $vb + $tol) continue;      // kişinin mesaisi henüz başlamadı
            $res['gelmeyen'][] = ['ad' => $u['ad_soyad'], 'gorev' => (string)$u['gorev'], 'mesai' => sprintf('%02d:%02d', intdiv($vb, 60), $vb % 60)];
        } else {
            $g = g_rp_dk(substr($ilk[$id], 11, 5)); $f = $g - $vb;
            if ($f > $tol) $res['gec'][] = ['ad' => $u['ad_soyad'], 'gorev' => (string)$u['gorev'], 'giris' => substr($ilk[$id], 11, 5), 'fark' => $f];
        }
    }
    usort($res['gec'], function ($a, $b) { return $b['fark'] <=> $a['fark']; });
    return $res;
}

function giris_rapor_sure($dk) { $dk = intval($dk); return $dk >= 60 ? intdiv($dk, 60) . ' sa ' . ($dk % 60 ? ($dk % 60) . ' dk' : '') : $dk . ' dk'; }

function giris_rapor_html($db, $R, $gecGoster)
{
    $h = function ($x) { return htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8'); };
    $tar = date('d.m.Y', strtotime($R['tarih']));
    $tab = function ($basliklar, $satirlar) use ($h) {
        $o = '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;margin:8px 0 18px;font-size:14px"><tr>';
        foreach ($basliklar as $b) $o .= '<th align="left" style="padding:8px 10px;background:#f1f5f9;border-bottom:1px solid #e2e8f0;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.3px">' . $h($b) . '</th>';
        $o .= '</tr>';
        foreach ($satirlar as $s) { $o .= '<tr>'; foreach ($s as $c) $o .= '<td style="padding:8px 10px;border-bottom:1px solid #eef2f7;color:#0f172a">' . $c . '</td>'; $o .= '</tr>'; }
        return $o . '</table>';
    };
    $g = '<p style="margin:0 0 12px">' . $h($tar) . ' tarihli giriş-çıkış durumu (saat ' . date('H:i') . ' itibarıyla):</p>';
    $g .= '<h3 style="margin:14px 0 4px;font-size:16px;color:#b91c1c">Giriş yapmayanlar (' . count($R['gelmeyen']) . ')</h3>';
    if ($R['gelmeyen']) $g .= $tab(['Ad soyad', 'Görev', 'Mesai başlangıcı'], array_map(function ($x) use ($h) { return ['<b>' . $h($x['ad']) . '</b>', $h($x['gorev'] ?: '—'), $h($x['mesai'])]; }, $R['gelmeyen']));
    else $g .= '<p style="margin:6px 0 18px;color:#15803d">Herkes giriş yapmış. ✔</p>';
    if ($gecGoster) {
        $g .= '<h3 style="margin:14px 0 4px;font-size:16px;color:#b45309">Geç giriş yapanlar (' . count($R['gec']) . ')</h3>';
        if ($R['gec']) $g .= $tab(['Ad soyad', 'Görev', 'Giriş saati', 'Gecikme'], array_map(function ($x) use ($h) { return ['<b>' . $h($x['ad']) . '</b>', $h($x['gorev'] ?: '—'), '<b>' . $h($x['giris']) . '</b>', $h(giris_rapor_sure($x['fark']))]; }, $R['gec']));
        else $g .= '<p style="margin:6px 0 18px;color:#15803d">Geç giriş yapan yok. ✔</p>';
    }
    $g .= '<p style="margin:0;color:#64748b;font-size:12px">İzinli, raporlu ve mesaisi henüz başlamamış kişiler listeye dahil edilmez. Rapor, Giriş-çıkış ayarlarından kapatılabilir.</p>';
    return $g;
}

function giris_ayar_yaz($db, $a, $d) { $st = $db->prepare("INSERT INTO giris_ayarlar (anahtar, deger) VALUES (?, ?) ON DUPLICATE KEY UPDATE deger = VALUES(deger)"); $s = (string)$d; $st->bind_param('ss', $a, $s); $st->execute(); }
function giris_ayar_oku($db, $a) { $st = $db->prepare("SELECT deger FROM giris_ayarlar WHERE anahtar = ?"); $st->bind_param('s', $a); $st->execute(); $r = $st->get_result(); $x = $r ? $r->fetch_row() : null; return $x ? (string)$x[0] : ''; }

/** Raporu hemen gönderir. Dönüş: ['ok'=>bool,'mesaj'=>..., 'gonderilen'=>n] */
function giris_rapor_gonder($db, $A = null)
{
    $A = $A ?: giris_ayarlar($db);
    $al = giris_rapor_alicilar($A['gunluk_rapor_alicilar']);
    if (!$al) return ['ok' => false, 'mesaj' => 'Alıcı e-posta adresi girilmemiş.', 'gonderilen' => 0];
    if (!ik_mail_hazir($db)) return ['ok' => false, 'mesaj' => 'E-posta gönderimi ayarlı değil (İK ayarlarından gönderen adresi / SMTP girin).', 'gonderilen' => 0];
    $R = giris_rapor_hazirla($db, $A);
    $gec = !empty($A['gunluk_rapor_gec']);
    $konu = 'Giriş yapmayanlar: ' . count($R['gelmeyen']) . ' kişi' . ($gec ? ' · geç girenler: ' . count($R['gec']) : '') . ' — ' . date('d.m.Y', strtotime($R['tarih']));
    $html = ik_mail_sablon('Günlük giriş raporu', giris_rapor_html($db, $R, $gec), '', 'Aç', $db);
    $ok = 0; $hata = '';
    foreach ($al as $m) { $s = ik_mail_gonder($db, $m, $konu, $html); if (!empty($s['ok'])) $ok++; else $hata = (string)($s['sonuc'] ?? 'gönderilemedi'); }
    return ['ok' => $ok > 0, 'mesaj' => $ok > 0 ? $ok . ' kişiye gönderildi' . ($ok < count($al) ? ' (bazıları başarısız: ' . $hata . ')' : '') : 'Gönderilemedi: ' . $hata, 'gonderilen' => $ok];
}

/** Zamanı geldiyse (ayarlı saat geçti, bugün gönderilmedi, çalışma günü) raporu bir kez gönderir. $db->kurum ayarlı olmalı. */
function giris_rapor_tara($db)
{
    giris_sema($db);
    $A = giris_ayarlar($db, true);                 // önbelleği yenile (cron birden çok kurumu sırayla işler)
    if (empty($A['gunluk_rapor'])) return;
    if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)$A['gunluk_rapor_saat'])) return;
    $bugun = date('Y-m-d');
    if (giris_ayar_oku($db, 'gunluk_rapor_son') === $bugun) return;
    if (date('H:i') < $A['gunluk_rapor_saat']) return;
    $gunler = array_map('intval', explode(',', (string)$A['calisma_gunleri']));
    giris_ayar_yaz($db, 'gunluk_rapor_son', $bugun);                      // önce işaretle: aynı gün çift gönderim olmasın
    if (!in_array(intval(date('N')), $gunler, true)) { giris_ayar_yaz($db, 'gunluk_rapor_sonuc', $bugun . ': çalışma günü değil, gönderilmedi'); return; }
    $s = giris_rapor_gonder($db, $A);
    giris_ayar_yaz($db, 'gunluk_rapor_sonuc', date('d.m.Y H:i') . ' — ' . $s['mesaj']);
}
