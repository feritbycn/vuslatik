<?php
// ============================================================
// ik_ek.php — İK ek modülü: işlem logları, ajanda notları + hatırlatma, görünüm (menü aç/kapa, sade tema, favicon),
//             fotoğraf kaldırma, panel özeti.  ik_islem.php içinden çağrılır (ik_ek_calistir).
// ============================================================

/** Menüden kapatılabilen bölümler: anahtar => [etiket, grup] (anahtar = sol menü düğmesi) */
function ik_ek_menu_katalog()
{
    return [
        'izinler' => ['İzin talepleri', 'İzin ve devam'], 'izin_takvimi' => ['Takvim', 'İzin ve devam'], 'puantaj' => ['Puantaj', 'İzin ve devam'],
        'masraflar' => ['Personel masrafları', 'İzin ve devam'], 'avanslar' => ['Avans talepleri', 'İzin ve devam'], 'mesailer' => ['Mesai', 'İzin ve devam'],
        'benimikm' => ['Benim İK’m', 'Genel'],
        'duyurular' => ['Duyurular', 'Duyuru ve paylaşım'], 'dosyalar' => ['Dosyalar', 'Duyuru ve paylaşım'], 'anketler' => ['Anketler', 'Duyuru ve paylaşım'],
        'birimler' => ['Birimler', 'Personel'], 'isealim' => ['İşe alım (adaylar)', 'Personel'], 'belge_tum' => ['Özlük evrakları', 'Personel'], 'zimmet_tum' => ['Zimmetler', 'Personel'],
        'egitimler' => ['Eğitimler', 'Personel'], 'performans' => ['Performans', 'Personel'], 'bordro' => ['Bordro kayıtları', 'Personel'],
        'personel' => ['Personel', 'Personel'], 'raporlar' => ['Raporlar', 'Personel'],
        'gc_bugun' => ['Bugün (giriş-çıkış)', 'Giriş-çıkış'], 'gc_kayitlar' => ['Kayıtlar', 'Giriş-çıkış'], 'gc_rapor' => ['Giriş-çıkış raporu', 'Giriş-çıkış'], 'gc_vardiyalar' => ['Vardiyalar', 'Giriş-çıkış'],
        'gc_cihazlar' => ['Cihazlar', 'Giriş-çıkış'], 'gc_noktalar' => ['Geçiş noktaları', 'Giriş-çıkış'], 'gc_ayarlar' => ['Giriş-çıkış ayarları', 'Giriş-çıkış'],
        'akis' => ['İzin onay akışı', 'Yönetim'], 'kanallar' => ['Bildirim kanalları', 'Yönetim'], 'kullanicilar' => ['Kullanıcılar', 'Yönetim'], 'sayfa_erisim' => ['Sayfa erişimi', 'Yönetim'], 'yedekler' => ['Yedekler', 'Yönetim'],
        'loglar' => ['İşlem logları', 'Yönetim'], 'ozellikler' => ['Site özellikleri', 'Yardım'],
    ];
}
/** Sunucu tarafında da kapatılan işlemler (menü kapalıysa ilgili uç nokta reddedilir) */
function ik_ek_islem_menu()
{
    return ['masraflar' => 'masraflar', 'masraf_ekle' => 'masraflar', 'masraf_karar' => 'masraflar', 'masraf_sil' => 'masraflar',
        'avanslar' => 'avanslar', 'avans_ekle' => 'avanslar', 'avans_karar' => 'avanslar', 'avans_sil' => 'avanslar',
        'puantaj_ay' => 'puantaj', 'puantaj_kaydet' => 'puantaj', 'izin_takvimi' => 'izin_takvimi',
        'egitimler' => 'egitimler', 'egitim_kaydet' => 'egitimler', 'egitim_sil' => 'egitimler', 'egitim_katilim_guncelle' => 'egitimler',
        'performans' => 'performans', 'performans_kaydet' => 'performans', 'performans_sil' => 'performans', 'perf_surecler' => 'performans', 'perf_surec_kaydet' => 'performans', 'perf_surec_detay' => 'performans', 'perf_bekleyen' => 'performans', 'perf_yanit_getir' => 'performans', 'perf_yanit_kaydet' => 'performans', 'perf_rapor' => 'performans', 'perf_hedef_liste' => 'performans', 'perf_hedef_kaydet' => 'performans',
        'bordro' => 'bordro', 'bordro_ekle' => 'bordro', 'bordro_sil' => 'bordro', 'zimmet_tum' => 'zimmet_tum', 'ajanda' => 'izin_takvimi', 'not_kaydet' => 'izin_takvimi', 'not_sil' => 'izin_takvimi', 'takvim' => 'izin_takvimi',
        'aday_liste' => 'isealim', 'aday_kaydet' => 'isealim', 'aday_asama' => 'isealim', 'aday_sil' => 'isealim'];
}
function ik_ek_kapali($db)
{
    $s = (string)ik_ayar($db, 'menu_kapali', '');
    $kat = ik_ek_menu_katalog(); $o = [];
    foreach (explode(',', $s) as $k) { $k = trim($k); if ($k !== '' && isset($kat[$k])) $o[] = $k; }
    return $o;
}
function ik_ek_sade($db) { return (string)ik_ayar($db, 'tema_sade', '0') === '1'; }
function ik_ek_favicon($db) { $f = (string)ik_ayar($db, 'favicon', ''); return preg_match('~^uploads/[\w./\-]+$~', $f) ? $f : ''; }

/** Kişi kendi özlük evrak ve zimmetini görebilir mi? (belge yetkilisi her zaman; diğerleri genel ayara ya da istisna listesine göre) */
function ik_ek_evrak_gor($db, $uid, $Y)
{
    if (!empty($Y['ik_belge']) || !empty($Y['kurucu'])) return true;
    if ((string)ik_ayar($db, 'kendi_evrak_gor', '1') === '1') return true;
    return in_array((string)intval($uid), array_map('trim', explode(',', (string)ik_ayar($db, 'evrak_istisna', ''))), true);
}
function ik_ek_sema($db)
{
    static $y = false; if ($y) return; $y = true;
    $db->query("CREATE TABLE IF NOT EXISTS ik_islem_log (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, tarih DATETIME NOT NULL, kullanici_id INT UNSIGNED NOT NULL DEFAULT 0, hedef_id INT UNSIGNED NOT NULL DEFAULT 0,
        kategori VARCHAR(40) NOT NULL DEFAULT '', islem VARCHAR(60) NOT NULL DEFAULT '', aciklama VARCHAR(500) NOT NULL DEFAULT '', ip VARCHAR(45) NOT NULL DEFAULT '',
        PRIMARY KEY (id), KEY k_tarih (tarih), KEY k_hedef (hedef_id, tarih), KEY k_kul (kullanici_id, tarih)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS ik_adaylar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, ad VARCHAR(120) NOT NULL, telefon VARCHAR(40) NOT NULL DEFAULT '', email VARCHAR(120) NOT NULL DEFAULT '', pozisyon VARCHAR(120) NOT NULL DEFAULT '',
        kaynak VARCHAR(60) NOT NULL DEFAULT '', asama VARCHAR(12) NOT NULL DEFAULT 'basvuru', puan TINYINT NOT NULL DEFAULT 0, notlar VARCHAR(2000) NOT NULL DEFAULT '',
        mulakat_tarih DATE NULL, mulakat_saat VARCHAR(5) NOT NULL DEFAULT '', mulakat_yer VARCHAR(200) NOT NULL DEFAULT '', mulakatci VARCHAR(120) NOT NULL DEFAULT '',
        gun1_gonderildi TINYINT(1) NOT NULL DEFAULT 0, gun0_gonderildi TINYINT(1) NOT NULL DEFAULT 0, olusturan_id INT UNSIGNED NOT NULL DEFAULT 0, olusturma DATETIME NOT NULL, guncelleme DATETIME NULL,
        PRIMARY KEY (id), KEY k_asama (asama), KEY k_mulakat (mulakat_tarih)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $db->query("CREATE TABLE IF NOT EXISTS ik_notlar (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT, tarih DATE NOT NULL, saat VARCHAR(5) NOT NULL DEFAULT '', baslik VARCHAR(160) NOT NULL DEFAULT '', icerik VARCHAR(2000) NOT NULL DEFAULT '',
        renk VARCHAR(9) NOT NULL DEFAULT '#6366f1', kullanici_id INT UNSIGNED NOT NULL DEFAULT 0, herkese TINYINT(1) NOT NULL DEFAULT 0, hatirlat TINYINT(1) NOT NULL DEFAULT 1,
        gun1_gonderildi TINYINT(1) NOT NULL DEFAULT 0, gun0_gonderildi TINYINT(1) NOT NULL DEFAULT 0, olusturma DATETIME NOT NULL,
        PRIMARY KEY (id), KEY k_tarih (tarih), KEY k_kul (kullanici_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

/** İşlem günlüğüne satır ekler (hata verirse sessiz geçer) */
function ik_ek_log($db, $uid, $kategori, $islem, $aciklama, $hedef = 0)
{
    try {
        ik_ek_sema($db);
        $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        $st = $db->prepare("INSERT INTO ik_islem_log (tarih, kullanici_id, hedef_id, kategori, islem, aciklama, ip) VALUES (NOW(), ?, ?, ?, ?, ?, ?)");
        if (!$st) return;
        $uid = intval($uid); $hedef = intval($hedef); $aciklama = mb_substr((string)$aciklama, 0, 500, 'UTF-8');
        $st->bind_param('iissss', $uid, $hedef, $kategori, $islem, $aciklama, $ip);
        $st->execute();
    } catch (Throwable $e) {}
}
/** islem => [kategori, açıklama] — otomatik kayıt için */
function ik_ek_log_etiket()
{
    return [
        'personel_kaydet' => ['Personel', 'Personel bilgisi güncellendi'], 'personel_resim' => ['Personel', 'Profil fotoğrafı değiştirildi'], 'personel_resim_kaldir' => ['Personel', 'Profil fotoğrafı kaldırıldı'],
        'izin_talep' => ['İzin', 'İzin talebi oluşturuldu'], 'izin_karar' => ['İzin', 'İzin talebi karara bağlandı'], 'izin_iptal' => ['İzin', 'İzin talebi iptal edildi'],
        'belge_yukle' => ['Belge', 'Özlük evrakı yüklendi'], 'belge_sil' => ['Belge', 'Özlük evrakı silindi'],
        'zimmet_kaydet' => ['Zimmet', 'Zimmet kaydedildi'], 'zimmet_iade' => ['Zimmet', 'Zimmet iade alındı'], 'zimmet_sil' => ['Zimmet', 'Zimmet silindi'],
        'puantaj_kaydet' => ['Puantaj', 'Puantaj kaydedildi'], 'masraf_ekle' => ['Masraf', 'Masraf eklendi'], 'masraf_karar' => ['Masraf', 'Masraf karara bağlandı'], 'masraf_sil' => ['Masraf', 'Masraf silindi'],
        'avans_ekle' => ['Avans', 'Avans talebi eklendi'], 'avans_karar' => ['Avans', 'Avans karara bağlandı'], 'avans_sil' => ['Avans', 'Avans silindi'],
        'birim_olustur' => ['Birim', 'Birim oluşturuldu'], 'birim_adlandir' => ['Birim', 'Birim yeniden adlandırıldı'], 'birim_sil' => ['Birim', 'Birim silindi'], 'birim_mudur' => ['Birim', 'Birim müdürü değiştirildi'], 'birim_ata' => ['Birim', 'Personel birime atandı'],
        'egitim_kaydet' => ['Eğitim', 'Eğitim kaydedildi'], 'egitim_katilim_guncelle' => ['Eğitim', 'Eğitim katılımı güncellendi'], 'egitim_sil' => ['Eğitim', 'Eğitim silindi'],
        'performans_kaydet' => ['Performans', 'Performans değerlendirmesi kaydedildi'], 'performans_sil' => ['Performans', 'Performans kaydı silindi'],
        'bordro_ekle' => ['Bordro', 'Bordro kaydı eklendi'], 'bordro_sil' => ['Bordro', 'Bordro kaydı silindi'],
        'ayarlar_kaydet' => ['Ayar', 'İK ayarları değiştirildi'], 'akis_kaydet' => ['Ayar', 'İzin onay akışı kaydedildi'], 'akis_sil' => ['Ayar', 'İzin onay akışı silindi'], 'bildirim_ayar_kaydet' => ['Ayar', 'Bildirim ayarı kaydedildi'],
        'mail_logo_yukle' => ['Ayar', 'E-posta logosu yüklendi'], 'gorunum_kaydet' => ['Ayar', 'Görünüm / menü ayarları değiştirildi'], 'favicon_yukle' => ['Ayar', 'Favicon yüklendi'], 'favicon_sil' => ['Ayar', 'Favicon kaldırıldı'],
        'aday_kaydet' => ['İşe alım', 'Aday kaydedildi'], 'aday_asama' => ['İşe alım', 'Aday aşaması değiştirildi'], 'aday_sil' => ['İşe alım', 'Aday silindi'],
        'perf_hedef_kaydet' => ['Performans', 'Performans hedefi kaydedildi'], 'perf_hedef_sil' => ['Performans', 'Performans hedefi silindi'], 'perf_atama_ekle' => ['Performans', 'Değerlendiren eklendi'], 'perf_atama_sil' => ['Performans', 'Değerlendiren kaldırıldı'],
        'not_kaydet' => ['Ajanda', 'Ajanda notu kaydedildi'], 'not_sil' => ['Ajanda', 'Ajanda notu silindi'],
    ];
}
/** Diğer uç noktalar (duyuru/dosya/anket, giriş-çıkış) için log etiketleri: islem => [kategori, açıklama, tablo, başlık sütunu] */
function ik_ek_harici_etiket()
{
    return [
        'duyuru_kaydet' => ['Duyuru', 'Duyuru yayınlandı/kaydedildi', 'ik_duyurular', 'baslik'], 'duyuru_durum' => ['Duyuru', 'Duyuru durumu / sabitleme değiştirildi', 'ik_duyurular', 'baslik'], 'duyuru_sil' => ['Duyuru', 'Duyuru silindi', 'ik_duyurular', 'baslik'],
        'dosya_yukle' => ['Dosya', 'Dosya paylaşıldı', 'ik_dosyalar', 'ad'], 'dosya_guncelle' => ['Dosya', 'Paylaşılan dosya güncellendi', 'ik_dosyalar', 'ad'], 'dosya_sil' => ['Dosya', 'Paylaşılan dosya silindi', 'ik_dosyalar', 'ad'],
        'anket_kaydet' => ['Anket', 'Anket kaydedildi', 'ik_anketler', 'baslik'], 'anket_durum' => ['Anket', 'Anket durumu değiştirildi', 'ik_anketler', 'baslik'], 'anket_sil' => ['Anket', 'Anket silindi', 'ik_anketler', 'baslik'], 'anket_cevapla' => ['Anket', 'Ankete cevap verildi', 'ik_anketler', 'baslik'],
        'kayit_duzelt' => ['Giriş-çıkış', 'Giriş-çıkış kaydı düzeltildi', '', ''], 'kayit_ekle' => ['Giriş-çıkış', 'Giriş-çıkış kaydı eklendi', '', ''], 'kayit_sil' => ['Giriş-çıkış', 'Giriş-çıkış kaydı silindi', '', ''],
        'vardiya_kaydet' => ['Giriş-çıkış', 'Vardiya kaydedildi', '', ''], 'cihaz_karar' => ['Giriş-çıkış', 'Cihaz kararı verildi', '', ''], 'pin_sifirla' => ['Giriş-çıkış', 'Kişinin PIN’i sıfırlandı', '', ''],
        'nokta_kaydet' => ['Giriş-çıkış', 'Giriş noktası kaydedildi', '', ''], 'nokta_sil' => ['Giriş-çıkış', 'Giriş noktası silindi', '', ''], 'ayar_kaydet' => ['Giriş-çıkış', 'Giriş-çıkış ayarları değiştirildi', '', ''],
    ];
}
/** İşlem yapılmadan ÖNCE çağrılır (silinecek kaydın adını yakalamak için); log bilgisini döndürür */
function ik_ek_harici_hazirla($db, $islem, $girdi)
{
    $et = ik_ek_harici_etiket(); if (!isset($et[$islem])) return null;
    list($kat, $acik, $tab, $col) = $et[$islem]; $ad = ''; $id = intval($girdi['id'] ?? 0);
    try {
        if ($tab && $id) { $q = $db->query("SELECT $col FROM $tab WHERE id = $id"); $r = $q ? $q->fetch_row() : null; if ($r) $ad = (string)$r[0]; }
        if ($ad === '') foreach (['baslik', 'ad'] as $a) if (!empty($girdi[$a]) && is_scalar($girdi[$a])) { $ad = (string)$girdi[$a]; break; }
    } catch (Throwable $e) {}
    $ek = [];
    if ($ad !== '') $ek[] = 'ad: ' . mb_substr($ad, 0, 80, 'UTF-8');
    if ($islem === 'duyuru_durum') { if (isset($girdi['sabit'])) $ek[] = !empty($girdi['sabit']) ? 'sabitlendi' : 'sabitleme kaldırıldı'; if (!empty($girdi['durum'])) $ek[] = 'durum: ' . $girdi['durum']; }
    if ($islem === 'duyuru_kaydet' && !empty($girdi['sabit'])) $ek[] = 'sabit';
    if ($islem === 'duyuru_kaydet') $acik = $id ? 'Duyuru düzenlendi' : 'Duyuru yayınlandı';
    if ($islem === 'anket_durum' && !empty($girdi['durum'])) $ek[] = 'durum: ' . $girdi['durum'];
    $hedef = intval($girdi['kullanici_id'] ?? 0);
    return [$kat, $islem, $acik . ($ek ? ' (' . implode(', ', $ek) . ')' : ''), $hedef];
}
/** Başarılı yanıtta çağrılır */
function ik_ek_harici_yaz($db, $uid, $bilgi, $cevap)
{
    if (!$bilgi || !is_array($cevap) || empty($cevap['ok'])) return;
    try { ik_ek_log($db, $uid, $bilgi[0], $bilgi[1], $bilgi[2], $bilgi[3]); } catch (Throwable $e) {}
}

/** ikc() başarılı bir POST yanıtında çağırır */
function ik_ek_oto_log($db, $uid, $islem, $girdi, $cevap)
{
    $et = ik_ek_log_etiket();
    if (!isset($et[$islem])) return; // yalnızca bilinen değiştirici işlemler
    list($kat, $acik) = $et[$islem];
    $hedef = 0;
    $id = intval($girdi['id'] ?? 0); $kid = intval($girdi['kullanici_id'] ?? 0);
    if (in_array($islem, ['personel_kaydet', 'personel_resim', 'personel_resim_kaldir'], true)) $hedef = $id;
    elseif ($kid) $hedef = $kid;
    elseif ($id) {
        $tab = ['izin_karar' => 'ik_izin_talepleri', 'izin_iptal' => 'ik_izin_talepleri', 'masraf_karar' => 'ik_masraflar', 'masraf_sil' => 'ik_masraflar', 'avans_karar' => 'ik_avanslar', 'avans_sil' => 'ik_avanslar',
            'belge_sil' => 'ik_belgeler', 'zimmet_iade' => 'ik_zimmetler', 'zimmet_sil' => 'ik_zimmetler', 'performans_sil' => 'ik_performans', 'bordro_sil' => 'ik_bordrolar'];
        if (isset($tab[$islem])) { $q = @$db->query("SELECT kullanici_id FROM {$tab[$islem]} WHERE id = $id"); $r = $q ? $q->fetch_row() : null; if ($r) $hedef = intval($r[0]); }
    }
    $ek = '';
    foreach (['grup' => 'bölüm', 'durum' => 'durum', 'karar' => 'karar', 'departman' => 'birim', 'ad' => 'ad', 'baslik' => 'başlık', 'tur' => 'tür'] as $a => $et2) {
        if (isset($girdi[$a]) && is_scalar($girdi[$a]) && (string)$girdi[$a] !== '') { $ek .= ($ek ? ', ' : '') . $et2 . ': ' . mb_substr((string)$girdi[$a], 0, 60, 'UTF-8'); }
    }
    if ($islem === 'birim_ata' && !empty($girdi['kullanici_ids'])) $ek .= ($ek ? ', ' : '') . 'kişi sayısı: ' . count(is_array($girdi['kullanici_ids']) ? $girdi['kullanici_ids'] : explode(',', (string)$girdi['kullanici_ids']));
    ik_ek_log($db, $uid, $kat, $islem, $acik . ($ek ? ' (' . $ek . ')' : ''), $hedef);
}

function ik_ek_kisi_liste($db)
{
    $o = []; $q = $db->query("SELECT id, ad_soyad FROM kullanicilar WHERE aktif = 1 ORDER BY ad_soyad"); if ($q) while ($r = $q->fetch_row()) $o[] = ['id' => intval($r[0]), 'ad' => $r[1]]; return $o;
}
function ik_ek_tarih($s) { $s = (string)$s; return preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && checkdate((int)substr($s, 5, 2), (int)substr($s, 8, 2), (int)substr($s, 0, 4)) ? $s : ''; }

function ik_ek_calistir($db, $uid, $Y, $girdi, $islem)
{
    ik_ek_sema($db);
    $yonetici = !empty($Y['kurucu']) || !empty($Y['ik_ayarlar']) || !empty($Y['ik_duzenle']);
    $ayarYet = !empty($Y['kurucu']) || !empty($Y['ik_ayarlar']);
    $kisiAd = function ($ids) use ($db) {
        $m = []; $ids = array_values(array_unique(array_filter(array_map('intval', $ids)))); if (!$ids) return $m;
        $q = $db->query("SELECT id, ad_soyad, avatar_renk, profil_resmi FROM kullanicilar WHERE id IN (" . implode(',', $ids) . ")");
        if ($q) while ($r = $q->fetch_assoc()) $m[intval($r['id'])] = ['ad' => $r['ad_soyad'], 'renk' => $r['avatar_renk'] ?: '#0ea5e9', 'resim' => tb_profil_resmi_src($r['profil_resmi'] ?? '')];
        return $m;
    };

    // ── Fotoğraf kaldır ──
    if ($islem === 'personel_resim_kaldir') {
        ik_csrf($girdi);
        $id = intval($girdi['id'] ?? 0);
        if (!$id) ikh('Personel seçilmedi.');
        if (empty($Y['ik_duzenle'])) ikh('Personel fotoğrafını kaldırma yetkiniz yok (yalnızca İK yetkilisi).', 403);
        $q = $db->query("SELECT profil_resmi FROM kullanicilar WHERE id = $id"); $r = $q ? $q->fetch_row() : null;
        if (!$r) ikh('Personel bulunamadı.', 404);
        $eski = tb_profil_resmi_yolu($r[0]);
        $db->query("UPDATE kullanicilar SET profil_resmi = '' WHERE id = $id");
        if ($eski !== '' && is_file(TB_KOK . '/' . $eski)) @unlink(TB_KOK . '/' . $eski);
        ikc(['ok' => true]);
    }

    // ── İşlem logları ──
    if ($islem === 'loglar' || $islem === 'personel_log') {
        $kendi = false;
        $hedef = intval($girdi['kisi'] ?? ($islem === 'personel_log' ? ($girdi['id'] ?? 0) : 0));
        if ($islem === 'personel_log') {
            if (empty($Y['ik_personel_gor']) && !$yonetici) ikh('Bu kişinin işlem geçmişini görme yetkiniz yok.', 403);
            $kendi = true;
        } elseif (!$yonetici && empty($Y['ik_rapor_gor'])) ikh('İşlem loglarını görme yetkiniz yok.', 403);
        $kos = ['1=1'];
        if ($islem === 'personel_log') { if (!$hedef) ikh('Personel seçilmedi.'); $kos[] = "(l.hedef_id = $hedef OR l.kullanici_id = $hedef)"; }
        elseif ($hedef) $kos[] = "(l.hedef_id = $hedef OR l.kullanici_id = $hedef)";
        $kat = trim((string)($girdi['kategori'] ?? '')); if ($kat !== '') $kos[] = "l.kategori = '" . $db->real_escape_string($kat) . "'";
        $b = ik_ek_tarih($girdi['bas'] ?? ''); $s = ik_ek_tarih($girdi['bit'] ?? '');
        if ($b) $kos[] = "l.tarih >= '$b 00:00:00'"; if ($s) $kos[] = "l.tarih <= '$s 23:59:59'";
        $ara = trim((string)($girdi['ara'] ?? ''));
        if ($ara !== '') { $e = $db->real_escape_string(str_replace(['%', '_'], ['\\%', '\\_'], mb_substr($ara, 0, 80, 'UTF-8')));
            $alan = (string)($girdi['alan'] ?? '');
            $harita = ['aciklama' => 'l.aciklama', 'yapan' => 'u.ad_soyad', 'hedef' => 'h.ad_soyad', 'islem' => 'l.islem', 'kategori' => 'l.kategori', 'ip' => 'l.ip'];
            if (isset($harita[$alan])) $kos[] = $harita[$alan] . " LIKE '%$e%'";
            else $kos[] = "(l.aciklama LIKE '%$e%' OR l.islem LIKE '%$e%' OR l.kategori LIKE '%$e%' OR l.ip LIKE '%$e%' OR u.ad_soyad LIKE '%$e%' OR h.ad_soyad LIKE '%$e%')"; }
        $w = implode(' AND ', $kos);
        $sayfa = max(1, intval($girdi['sayfa'] ?? 1)); $adet = 30; $of = ($sayfa - 1) * $adet;
        $toplam = intval($db->query("SELECT COUNT(*) FROM ik_islem_log l LEFT JOIN kullanicilar u ON u.id = l.kullanici_id LEFT JOIN kullanicilar h ON h.id = l.hedef_id WHERE $w")->fetch_row()[0] ?? 0);
        $liste = [];
        $q = $db->query("SELECT l.*, u.ad_soyad yapan, u.avatar_renk yrenk, u.profil_resmi yresim, h.ad_soyad hedef FROM ik_islem_log l LEFT JOIN kullanicilar u ON u.id = l.kullanici_id LEFT JOIN kullanicilar h ON h.id = l.hedef_id
                         WHERE $w ORDER BY l.id DESC LIMIT $adet OFFSET $of");
        if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'tarih' => $r['tarih'], 'kategori' => $r['kategori'], 'islem' => $r['islem'], 'aciklama' => $r['aciklama'], 'ip' => $r['ip'],
            'yapan_id' => intval($r['kullanici_id']), 'yapan' => $r['yapan'] ?: 'Sistem', 'yrenk' => $r['yrenk'] ?: '#64748b', 'yresim' => tb_profil_resmi_src($r['yresim'] ?? ''), 'hedef_id' => intval($r['hedef_id']), 'hedef' => $r['hedef'] ?: ''];
        $kats = []; $q = $db->query("SELECT DISTINCT kategori FROM ik_islem_log ORDER BY kategori"); if ($q) while ($r = $q->fetch_row()) $kats[] = $r[0];
        ikc(['ok' => true, 'liste' => $liste, 'toplam' => $toplam, 'sayfa' => $sayfa, 'adet' => $adet, 'kategoriler' => $kats]);
    }

    // ── Ajanda (takvim notları) ──
    if ($islem === 'ajanda') {
        $ay = preg_match('/^\d{4}-\d{2}$/', (string)($girdi['ay'] ?? '')) ? $girdi['ay'] : date('Y-m');
        $bas = $ay . '-01'; $bit = date('Y-m-t', strtotime($bas));
        $notlar = [];
        $q = $db->query("SELECT * FROM ik_notlar WHERE tarih BETWEEN '$bas' AND '$bit' AND kullanici_id = $uid ORDER BY tarih, saat, id");
        if ($q) while ($r = $q->fetch_assoc()) $notlar[] = ['id' => intval($r['id']), 'tarih' => $r['tarih'], 'saat' => $r['saat'], 'baslik' => $r['baslik'], 'icerik' => $r['icerik'], 'renk' => $r['renk'],
            'herkese' => intval($r['herkese']), 'hatirlat' => intval($r['hatirlat']), 'benim' => intval($r['kullanici_id']) === $uid];
        $izin = []; $dogum = [];
        $gorur = !empty($Y['ik_personel_gor']) || !empty($Y['ik_izin_onay']);
        $kosul = $gorur ? '1=1' : "t.kullanici_id = $uid";
        $q = $db->query("SELECT t.kullanici_id, t.baslangic, t.bitis, t.tur, k.ad_soyad FROM ik_izin_talepleri t JOIN kullanicilar k ON k.id = t.kullanici_id WHERE t.durum = 'onaylandi' AND t.baslangic <= '$bit' AND t.bitis >= '$bas' AND $kosul");
        if ($q) while ($r = $q->fetch_assoc()) $izin[] = ['ad' => $r['ad_soyad'], 'bas' => $r['baslangic'], 'bit' => $r['bitis'], 'tur' => IK_IZIN_TURLERI[$r['tur']] ?? $r['tur']];
        if ($gorur) { $q = $db->query("SELECT k.ad_soyad, k.dogum_tarihi FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND p.isten_cikis IS NULL AND k.dogum_tarihi IS NOT NULL AND k.dogum_tarihi <> '0000-00-00' AND MONTH(k.dogum_tarihi) = " . intval(substr($ay, 5, 2)));
            if ($q) while ($r = $q->fetch_assoc()) $dogum[] = ['ad' => $r['ad_soyad'], 'gun' => intval(date('j', strtotime($r['dogum_tarihi'])))]; }
        $th = [];
        try { require_once __DIR__ . '/rapor_ortak.php'; foreach (rp_tatil_haritasi($db, $bas, $bit) as $t => $x) if (strpos((string)$t, $ay) === 0) $th[] = ['tarih' => $t, 'ad' => $x['ad'] ?? '', 'yarim' => !empty($x['yarim'])]; } catch (Throwable $e) {}
        ikc(['ok' => true, 'ay' => $ay, 'notlar' => $notlar, 'izinler' => $izin, 'dogum' => $dogum, 'tatiller' => $th]);
    }
    if ($islem === 'not_kaydet') {
        ik_csrf($girdi);
        $id = intval($girdi['id'] ?? 0);
        $t = ik_ek_tarih($girdi['tarih'] ?? ''); if (!$t) ikh('Tarih seçin.');
        $baslik = mb_substr(trim((string)($girdi['baslik'] ?? '')), 0, 160, 'UTF-8'); if ($baslik === '') ikh('Not başlığı yazın.');
        $icerik = mb_substr(trim((string)($girdi['icerik'] ?? '')), 0, 2000, 'UTF-8');
        $saat = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)($girdi['saat'] ?? '')) ? $girdi['saat'] : '';
        $renk = preg_match('/^#[0-9a-fA-F]{6}$/', (string)($girdi['renk'] ?? '')) ? $girdi['renk'] : '#6366f1';
        $herkese = 0; $hat = !empty($girdi['hatirlat']) ? 1 : 0; // ajanda notları kişiye özeldir
        if ($id) {
            $q = $db->query("SELECT kullanici_id FROM ik_notlar WHERE id = $id"); $r = $q ? $q->fetch_row() : null;
            if (!$r) ikh('Not bulunamadı.', 404);
            if (intval($r[0]) !== $uid) ikh('Bu notu düzenleme yetkiniz yok.', 403);
            $st = $db->prepare("UPDATE ik_notlar SET tarih = ?, saat = ?, baslik = ?, icerik = ?, renk = ?, herkese = ?, hatirlat = ?, gun1_gonderildi = 0, gun0_gonderildi = 0 WHERE id = ?");
            $st->bind_param('sssssiii', $t, $saat, $baslik, $icerik, $renk, $herkese, $hat, $id); $st->execute();
        } else {
            $st = $db->prepare("INSERT INTO ik_notlar (tarih, saat, baslik, icerik, renk, kullanici_id, herkese, hatirlat, olusturma) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $st->bind_param('sssssiii', $t, $saat, $baslik, $icerik, $renk, $uid, $herkese, $hat); $st->execute(); $id = $db->son_id();
        }
        ikc(['ok' => true, 'id' => $id]);
    }
    if ($islem === 'not_sil') {
        ik_csrf($girdi);
        $id = intval($girdi['id'] ?? 0);
        $q = $db->query("SELECT kullanici_id FROM ik_notlar WHERE id = $id"); $r = $q ? $q->fetch_row() : null;
        if (!$r) ikh('Not bulunamadı.', 404);
        if (intval($r[0]) !== $uid) ikh('Bu notu silme yetkiniz yok.', 403);
        $db->query("DELETE FROM ik_notlar WHERE id = $id");
        ikc(['ok' => true]);
    }

    // ── Görünüm (menü aç/kapa, sade tema, favicon) ──
    if ($islem === 'gorunum_getir') {
        ikc(['ok' => true, 'katalog' => ik_ek_menu_katalog(), 'kapali' => ik_ek_kapali($db), 'sade' => ik_ek_sade($db), 'favicon' => ik_ek_favicon($db), 'yonetebilir' => $ayarYet, 'kendi_evrak' => (string)ik_ayar($db, 'kendi_evrak_gor', '1') === '1', 'evrak_istisna' => array_values(array_filter(array_map('intval', explode(',', (string)ik_ayar($db, 'evrak_istisna', ''))))), 'kisiler' => ik_ek_kisi_liste($db)]);
    }
    if (in_array($islem, ['gorunum_kaydet', 'favicon_yukle', 'favicon_sil'], true)) {
        ik_csrf($girdi);
        if (!$ayarYet) ikh('Görünüm ayarlarını değiştirme yetkiniz yok.', 403);
    }
    if ($islem === 'gorunum_kaydet') {
        $kat = ik_ek_menu_katalog(); $k = [];
        $liste = $girdi['kapali'] ?? [];
        if (!is_array($liste)) $liste = explode(',', (string)$liste);
        foreach ($liste as $x) { $x = trim((string)$x); if (isset($kat[$x])) $k[] = $x; }
        ik_ayar_yaz($db, 'menu_kapali', implode(',', array_unique($k)));
        ik_ayar_yaz($db, 'tema_sade', !empty($girdi['sade']) ? '1' : '0');
        if (isset($girdi['kendi_evrak'])) {
            ik_ayar_yaz($db, 'kendi_evrak_gor', !empty($girdi['kendi_evrak']) ? '1' : '0');
            $ist = array_values(array_unique(array_filter(array_map('intval', is_array($girdi['evrak_istisna'] ?? null) ? $girdi['evrak_istisna'] : explode(',', (string)($girdi['evrak_istisna'] ?? ''))))));
            ik_ayar_yaz($db, 'evrak_istisna', implode(',', $ist));
        }
        ikc(['ok' => true, 'kapali' => $k]);
    }
    if ($islem === 'favicon_yukle') {
        $f = $_FILES['dosya'] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) ikh('Dosya alınamadı.');
        if ($f['size'] > 524288) ikh('Favicon en fazla 512 KB olabilir.');
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
        $uz = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/x-icon' => 'ico', 'image/vnd.microsoft.icon' => 'ico'][$mime] ?? '';
        if ($uz === '') ikh('PNG, JPG, WEBP, GIF veya ICO yükleyin.');
        $kl = tb_yukleme_dizin('ik_favicon', true);
        foreach (glob($kl . '/fav_*') ?: [] as $eski) @unlink($eski);
        $ad = 'fav_' . substr(md5(uniqid('', true)), 0, 10) . '.' . $uz;
        if (!move_uploaded_file($f['tmp_name'], $kl . '/' . $ad)) ikh('Favicon kaydedilemedi (uploads klasörü yazılabilir mi?).', 500);
        ik_ayar_yaz($db, 'favicon', tb_yukleme_web('ik_favicon') . '/' . $ad);
        ikc(['ok' => true, 'favicon' => tb_yukleme_web('ik_favicon') . '/' . $ad]);
    }
    if ($islem === 'favicon_sil') {
        $kl = tb_yukleme_dizin('ik_favicon', true);
        foreach (glob($kl . '/fav_*') ?: [] as $eski) @unlink($eski);
        ik_ayar_yaz($db, 'favicon', '');
        ikc(['ok' => true]);
    }


    // ── Takvim: izin, özel gün, etkinlik, not ve mülakatlar tek akışta ──
    $ise = !empty($Y['kurucu']) || !empty($Y['ik_personel_gor']) || !empty($Y['ik_duzenle']);
    if ($islem === 'takvim') {
        $bas = ik_ek_tarih($girdi['bas'] ?? '') ?: date('Y-m-01'); $bit = ik_ek_tarih($girdi['bit'] ?? '') ?: date('Y-m-t', strtotime($bas));
        if ((strtotime($bit) - strtotime($bas)) / 86400 > 62) $bit = date('Y-m-d', strtotime($bas . ' +62 days'));
        $gorur = !empty($Y['ik_personel_gor']) || !empty($Y['ik_izin_onay']);
        $ev = [];
        $q = $db->query("SELECT t.id, t.kullanici_id, t.baslangic, t.bitis, t.tur, t.durum, k.ad_soyad FROM ik_izin_talepleri t JOIN kullanicilar k ON k.id = t.kullanici_id WHERE t.durum IN ('onaylandi','beklemede') AND t.baslangic <= '$bit' AND t.bitis >= '$bas'" . ($gorur ? '' : " AND t.kullanici_id = $uid"));
        if ($q) while ($r = $q->fetch_assoc()) $ev[] = ['tip' => 'izin', 'id' => intval($r['id']), 'baslik' => $r['ad_soyad'], 'alt' => (IK_IZIN_TURLERI[$r['tur']] ?? $r['tur']) . ($r['durum'] === 'beklemede' ? ' · onay bekliyor' : ''), 'bas' => $r['baslangic'], 'bit' => $r['bitis'], 'bekliyor' => $r['durum'] === 'beklemede', 'kid' => intval($r['kullanici_id'])];
        try { require_once __DIR__ . '/rapor_ortak.php'; foreach (rp_tatil_haritasi($db, $bas, $bit) as $t => $x) if ($t >= $bas && $t <= $bit) $ev[] = ['tip' => 'tatil', 'baslik' => $x['ad'] ?? '', 'alt' => !empty($x['yarim']) ? 'yarım gün' : 'resmî tatil', 'bas' => $t, 'bit' => $t]; } catch (Throwable $e) {}
        if ($gorur) {
            $q = $db->query("SELECT k.ad_soyad, k.dogum_tarihi, p.ise_giris FROM kullanicilar k LEFT JOIN ik_personel p ON p.kullanici_id = k.id WHERE k.aktif = 1 AND p.isten_cikis IS NULL");
            $y1 = (int)date('Y', strtotime($bas)); $y2 = (int)date('Y', strtotime($bit));
            if ($q) while ($r = $q->fetch_assoc()) foreach (['dogum' => $r['dogum_tarihi'], 'yildonumu' => $r['ise_giris']] as $tip => $tar) {
                if (!$tar || $tar === '0000-00-00') continue; $md = date('m-d', strtotime($tar));
                for ($y = $y1; $y <= $y2; $y++) { $g = ($md === '02-29' && !checkdate(2, 29, $y)) ? '02-28' : $md; $t = "$y-$g"; if ($t < $bas || $t > $bit) continue; $yil = $y - (int)date('Y', strtotime($tar)); if ($tip === 'yildonumu' && $yil < 1) continue;
                    $ev[] = ['tip' => $tip, 'baslik' => $r['ad_soyad'], 'alt' => $tip === 'dogum' ? 'doğum günü' : $yil . '. iş yılı', 'bas' => $t, 'bit' => $t]; }
            }
        }
        require_once __DIR__ . '/ik_etkinlik.php'; ik_etkinlik_sema($db);
        $eyon = !empty($Y['ik_egitim_yonet']) || $gorur;
        $q = $db->query("SELECT e.id, e.ad, e.tur, e.tarih, e.saat, e.yer, (SELECT COUNT(*) FROM ik_egitim_katilim x WHERE x.egitim_id = e.id) say, (SELECT COUNT(*) FROM ik_egitim_katilim x WHERE x.egitim_id = e.id AND x.kullanici_id = $uid) ben FROM ik_egitimler e WHERE e.tarih BETWEEN '$bas' AND '$bit'");
        if ($q) while ($r = $q->fetch_assoc()) { if (!$eyon && !intval($r['ben'])) continue; $ev[] = ['tip' => $r['tur'] === 'toplanti' ? 'toplanti' : 'etkinlik', 'id' => intval($r['id']), 'baslik' => $r['ad'], 'alt' => ($r['yer'] ?: '') . ' · ' . intval($r['say']) . ' katılımcı', 'bas' => $r['tarih'], 'bit' => $r['tarih'], 'saat' => $r['saat']]; }
        $q = $db->query("SELECT * FROM ik_notlar WHERE tarih BETWEEN '$bas' AND '$bit' AND kullanici_id = $uid");
        if ($q) while ($r = $q->fetch_assoc()) $ev[] = ['tip' => 'not', 'id' => intval($r['id']), 'baslik' => $r['baslik'], 'alt' => $r['icerik'], 'bas' => $r['tarih'], 'bit' => $r['tarih'], 'saat' => $r['saat'], 'renk' => $r['renk'], 'hatirlat' => intval($r['hatirlat']), 'icerik' => $r['icerik']];
        if ($ise) { $q = $db->query("SELECT * FROM ik_adaylar WHERE mulakat_tarih BETWEEN '$bas' AND '$bit' AND asama NOT IN ('red')");
            if ($q) while ($r = $q->fetch_assoc()) $ev[] = ['tip' => 'mulakat', 'id' => intval($r['id']), 'baslik' => $r['ad'], 'alt' => 'Mülakat · ' . $r['pozisyon'] . ($r['mulakat_yer'] !== '' ? ' · ' . $r['mulakat_yer'] : ''), 'bas' => $r['mulakat_tarih'], 'bit' => $r['mulakat_tarih'], 'saat' => $r['mulakat_saat']]; }
        ikc(['ok' => true, 'bas' => $bas, 'bit' => $bit, 'olaylar' => $ev, 'yetki' => ['etkinlik' => !empty($Y['ik_egitim_yonet']), 'mulakat' => $ise, 'izin' => true]]);
    }

    // ── İşe alım: aday hunisi ──
    if (in_array($islem, ['aday_liste', 'aday_kaydet', 'aday_asama', 'aday_sil'], true) && !$ise) ikh('İşe alım bölümünü görme yetkiniz yok.', 403);
    $ASAMA = ['basvuru' => 'Başvuru', 'on_eleme' => 'Ön eleme', 'mulakat' => 'Mülakat', 'teklif' => 'Teklif', 'ise_alindi' => 'İşe alındı', 'red' => 'Reddedildi'];
    if ($islem === 'aday_liste') {
        $liste = []; $q = $db->query("SELECT * FROM ik_adaylar ORDER BY FIELD(asama,'basvuru','on_eleme','mulakat','teklif','ise_alindi','red'), COALESCE(mulakat_tarih,'9999-12-31'), id DESC LIMIT 1000");
        if ($q) while ($r = $q->fetch_assoc()) $liste[] = ['id' => intval($r['id']), 'ad' => $r['ad'], 'telefon' => $r['telefon'], 'email' => $r['email'], 'pozisyon' => $r['pozisyon'], 'kaynak' => $r['kaynak'], 'asama' => $r['asama'], 'puan' => intval($r['puan']), 'notlar' => $r['notlar'],
            'mulakat_tarih' => $r['mulakat_tarih'], 'mulakat_saat' => $r['mulakat_saat'], 'mulakat_yer' => $r['mulakat_yer'], 'mulakatci' => $r['mulakatci'], 'olusturma' => $r['olusturma']];
        ikc(['ok' => true, 'liste' => $liste, 'asamalar' => $ASAMA]);
    }
    if ($islem === 'aday_kaydet') {
        ik_csrf($girdi);
        $id = intval($girdi['id'] ?? 0);
        $ad = mb_substr(trim((string)($girdi['ad'] ?? '')), 0, 120, 'UTF-8'); if ($ad === '') ikh('Aday adını yazın.');
        $tel = mb_substr(trim((string)($girdi['telefon'] ?? '')), 0, 40, 'UTF-8'); $mail = mb_substr(trim((string)($girdi['email'] ?? '')), 0, 120, 'UTF-8');
        $poz = mb_substr(trim((string)($girdi['pozisyon'] ?? '')), 0, 120, 'UTF-8'); $kay = mb_substr(trim((string)($girdi['kaynak'] ?? '')), 0, 60, 'UTF-8');
        $as = isset($ASAMA[$girdi['asama'] ?? '']) ? $girdi['asama'] : 'basvuru'; $puan = max(0, min(5, intval($girdi['puan'] ?? 0)));
        $not = mb_substr(trim((string)($girdi['notlar'] ?? '')), 0, 2000, 'UTF-8'); $mt = ik_ek_tarih($girdi['mulakat_tarih'] ?? '') ?: null;
        $ms = preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', (string)($girdi['mulakat_saat'] ?? '')) ? $girdi['mulakat_saat'] : ''; $my = mb_substr(trim((string)($girdi['mulakat_yer'] ?? '')), 0, 200, 'UTF-8'); $mc = mb_substr(trim((string)($girdi['mulakatci'] ?? '')), 0, 120, 'UTF-8');
        if ($mt && $as === 'basvuru') $as = 'mulakat';
        if ($id) {
            $st = $db->prepare("UPDATE ik_adaylar SET ad=?, telefon=?, email=?, pozisyon=?, kaynak=?, asama=?, puan=?, notlar=?, mulakat_tarih=?, mulakat_saat=?, mulakat_yer=?, mulakatci=?, gun1_gonderildi=0, gun0_gonderildi=0, guncelleme=NOW() WHERE id=?");
            $st->bind_param('ssssssisssssi', $ad, $tel, $mail, $poz, $kay, $as, $puan, $not, $mt, $ms, $my, $mc, $id);
        } else {
            $st = $db->prepare("INSERT INTO ik_adaylar (ad, telefon, email, pozisyon, kaynak, asama, puan, notlar, mulakat_tarih, mulakat_saat, mulakat_yer, mulakatci, olusturan_id, olusturma) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $st->bind_param('ssssssisssssi', $ad, $tel, $mail, $poz, $kay, $as, $puan, $not, $mt, $ms, $my, $mc, $uid);
        }
        $st->execute(); if (!$id) $id = $db->son_id();
        ikc(['ok' => true, 'id' => $id]);
    }
    if ($islem === 'aday_asama') {
        ik_csrf($girdi); $id = intval($girdi['id'] ?? 0); $as = $girdi['asama'] ?? '';
        if (!isset($ASAMA[$as])) ikh('Geçersiz aşama.');
        $db->query("UPDATE ik_adaylar SET asama = '" . $db->real_escape_string($as) . "', guncelleme = NOW() WHERE id = $id");
        ikc(['ok' => true]);
    }
    if ($islem === 'aday_sil') { ik_csrf($girdi); $db->query("DELETE FROM ik_adaylar WHERE id = " . intval($girdi['id'] ?? 0)); ikc(['ok' => true]); }

    // ── Panel özeti (ajanda + son loglar + yaklaşan notlar) ──
    if ($islem === 'panel_ek') {
        $bugun = date('Y-m-d'); $son = date('Y-m-d', strtotime('+7 days'));
        $notlar = [];
        $q = $db->query("SELECT id, tarih, saat, baslik, renk FROM ik_notlar WHERE tarih BETWEEN '$bugun' AND '$son' AND kullanici_id = $uid ORDER BY tarih, saat LIMIT 8");
        if ($q) while ($r = $q->fetch_assoc()) $notlar[] = ['id' => intval($r['id']), 'tarih' => $r['tarih'], 'saat' => $r['saat'], 'baslik' => $r['baslik'], 'renk' => $r['renk']];
        $loglar = [];
        ikc(['ok' => true, 'notlar' => $notlar, 'loglar' => $loglar, 'kapali' => ik_ek_kapali($db)]);
    }
    ikh('Geçersiz işlem.');
}

/** Ajanda hatırlatıcısı: not tarihinden 1 gün önce ve o gün, notun sahibine (herkese açıksa tüm aktif personele) site bildirimi gönderir. Saatte bir çalışır. */
function ik_ek_not_hatirlat($db)
{
    ik_ek_sema($db);
    if (!ik_log_uygun($db, 0, 'ik_not_hatirlat', 10)) return;
    $bugun = date('Y-m-d'); $yarin = date('Y-m-d', strtotime('+1 day'));
    $gonder = function ($n, $kimin, $on) use ($db) {
        $t = date('d.m.Y', strtotime($n['tarih'])) . ($n['saat'] !== '' ? ' ' . $n['saat'] : '');
        $baslik = ($on ? '🗓️ Yarın: ' : '🔔 Bugün: ') . $n['baslik'];
        $govde = ($on ? '🗓️ <b>Yarın</b> için ajanda notunuz var: ' : '🔔 <b>Bugün</b> için ajanda notunuz var: ') . '<b>' . ik_h($n['baslik']) . '</b> (' . ik_h($t) . ')' . ($n['icerik'] !== '' ? '<br>' . ik_h(mb_substr($n['icerik'], 0, 200, 'UTF-8')) : '');
        foreach ($kimin as $k) ik_bildir($db, $k, intval($n['kullanici_id']), $baslik, $govde, 'ik_ajanda', intval($n['id']));
    };
    $alicilar = function ($n) use ($db) {
        return [intval($n['kullanici_id'])];
        $o = []; $q = $db->query("SELECT id FROM kullanicilar WHERE aktif = 1"); if ($q) while ($r = $q->fetch_row()) $o[] = intval($r[0]); return $o;
    };
    foreach ([[$yarin, 'gun1_gonderildi', true], [$bugun, 'gun0_gonderildi', false]] as $x) {
        list($t0, $kol0, $on0) = $x;
        $q0 = $db->query("SELECT * FROM ik_adaylar WHERE mulakat_tarih = '$t0' AND $kol0 = 0 AND asama NOT IN ('red','ise_alindi')");
        $l0 = []; if ($q0) while ($r0 = $q0->fetch_assoc()) $l0[] = $r0;
        foreach ($l0 as $a) { $db->query("UPDATE ik_adaylar SET $kol0 = 1 WHERE id = " . intval($a['id']));
            ik_bildir($db, intval($a['olusturan_id']), intval($a['olusturan_id']), ($on0 ? '🗓️ Yarın mülakat: ' : '🔔 Bugün mülakat: ') . $a['ad'], ($on0 ? '🗓️ <b>Yarın</b>' : '🔔 <b>Bugün</b>') . ' mülakat var: <b>' . ik_h($a['ad']) . '</b> (' . ik_h($a['pozisyon']) . ')' . ($a['mulakat_saat'] !== '' ? ' · ' . ik_h($a['mulakat_saat']) : ''), 'ik_aday', intval($a['id'])); }
        list($t, $kol, $on) = $x;
        $q = $db->query("SELECT * FROM ik_notlar WHERE tarih = '$t' AND hatirlat = 1 AND $kol = 0");
        $liste = []; if ($q) while ($r = $q->fetch_assoc()) $liste[] = $r;
        foreach ($liste as $n) { $db->query("UPDATE ik_notlar SET $kol = 1 WHERE id = " . intval($n['id'])); $gonder($n, $alicilar($n), $on); }
    }
}
