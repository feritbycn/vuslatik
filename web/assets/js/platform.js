/* ==========================================================================
   platform.js — Platform paneli (ürün sahibi)
   Bölümler: Genel bakış · Kurumlar (ekle/düzenle/askıya al/sil · yetkili ata · şifre sıfırla · destek girişi) · Site ayarları (genel, logo/favicon, SEO, giriş sayfası, sistem)
             · Platform yöneticileri · İşlem kayıtları.   Arayüz bileşenleri tb-ikm.css sınıflarını kullanır.
   ========================================================================== */
(function () {
    'use strict';
    var API = 'platform_islem.php';
    var kok = document.getElementById('pf-kok'), icerik = document.getElementById('pf-icerik');
    var $ = function (s, k) { return (k || document).querySelector(s); }, $$ = function (s, k) { return Array.prototype.slice.call((k || document).querySelectorAll(s)); };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function csrf() { return (window.TB && window.TB.csrf) || ''; }
    function oku(r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, hata: 'Sunucu yanıtı okunamadı (HTTP ' + r.status + '): ' + t.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 200) }; } }).then(function (j) { if (!j.ok) { var e = new Error(j.hata || 'İşlem başarısız.'); e.kod = r.status; if (r.status === 401) setTimeout(function () { location.reload(); }, 1200); throw e; } return j; }); }
    function getir(p) { return fetch(API + '?' + new URLSearchParams(p), { credentials: 'same-origin', cache: 'no-store' }).then(oku); }
    function gonder(islem, veri, dosya) {
        var fd = new FormData(); fd.append('islem', islem); fd.append('_csrf', csrf());
        Object.keys(veri || {}).forEach(function (k) { if (veri[k] !== undefined && veri[k] !== null) fd.append(k, veri[k]); });
        if (dosya) fd.append('dosya', dosya);
        return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf() } }).then(oku);
    }
    function tarih(t) { var m = String(t || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/); return m ? m[3] + '.' + m[2] + '.' + m[1] + (m[4] ? ' ' + m[4] + ':' + m[5] : '') : '—'; }
    function bas(ad) { var p = String(ad || '?').trim().split(/\s+/); return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toLocaleUpperCase('tr-TR'); }
    function bosKutu(ikon, baslik, alt) { return '<div class="ikm-bos"><span><i class="fa-solid ' + ikon + '"></i></span><b>' + esc(baslik) + '</b>' + (alt ? '<p>' + esc(alt) + '</p>' : '') + '</div>'; }
    function iskelet() { return '<div class="ikm-iskelet"><i></i><i></i><i></i></div>'; }

    /* ── toast / modal / onay ── */
    var yigin = null;
    function bildir(m, tur) {
        tur = tur || 'bilgi';
        if (!yigin) { yigin = document.createElement('div'); yigin.className = 'ikm-toast-yigin'; document.body.appendChild(yigin); }
        var el = document.createElement('div'), ik = { basari: 'fa-circle-check', hata: 'fa-circle-xmark', uyari: 'fa-triangle-exclamation', bilgi: 'fa-circle-info' }[tur] || 'fa-circle-info', sure = tur === 'hata' ? 6500 : 3800;
        el.className = 'ikm-toast ' + tur; el.innerHTML = '<span class="ikm-toast-ikon"><i class="fa-solid ' + ik + '"></i></span><div class="ikm-toast-yazi">' + esc(m) + '</div><button type="button" class="ikm-toast-x" aria-label="Kapat">✕</button><i class="ikm-toast-cubuk" style="animation-duration:' + sure + 'ms"></i>';
        yigin.appendChild(el);
        var acik = Array.prototype.filter.call(yigin.children, function (x) { return !x.classList.contains('cikis'); });
        while (acik.length > 3) { var eskiT = acik.shift(); eskiT.classList.add('cikis'); (function (n) { setTimeout(function () { n.remove(); }, 240); })(eskiT); }
        var kapat = function () { el.classList.add('cikis'); setTimeout(function () { el.remove(); }, 240); }; setTimeout(kapat, sure); $('.ikm-toast-x', el).onclick = kapat;
    }
    function modal(o) {
        var arka = document.createElement('div'); arka.className = 'ikm-arka';
        arka.innerHTML = '<div class="ikm-modal" role="dialog" aria-modal="true" style="max-width:' + (o.gen || 560) + 'px"><div class="ikm-modal-ust">' + (o.ikon ? '<span class="ikm-modal-ikon"><i class="fa-solid ' + o.ikon + '"></i></span>' : '') + '<h3>' + esc(o.baslik) + '</h3><button type="button" class="ikm-ikon-btn ikm-x" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button></div><div class="ikm-modal-govde">' + (o.govde || '') + '</div>' + (o.alt ? '<div class="ikm-modal-alt">' + o.alt + '</div>' : '') + '</div>';
        document.body.appendChild(arka); document.body.classList.add('ikm-kilit');
        var m = { el: arka };
        function tus(e) { if (e.key === 'Escape' && arka === $$('.ikm-arka').pop()) m.kapat(); }
        m.kapat = function () { if (!arka.parentNode) return; arka.classList.add('cikis'); setTimeout(function () { arka.remove(); if (!$('.ikm-arka')) document.body.classList.remove('ikm-kilit'); }, 160); document.removeEventListener('keydown', tus); };
        document.addEventListener('keydown', tus); arka.addEventListener('mousedown', function (e) { if (e.target === arka && !o.sabit) m.kapat(); });
        $('.ikm-x', arka).onclick = m.kapat; $$('[data-kapat]', arka).forEach(function (b) { b.onclick = m.kapat; });
        return m;
    }
    function onayla(o) {
        return new Promise(function (coz) {
            var tur = o.tur || 'bilgi', bitti = false, arka = document.createElement('div'); arka.className = 'ikm-arka ikm-arka-onay';
            arka.innerHTML = '<div class="ikm-onay ' + tur + '" role="alertdialog" aria-modal="true"><span class="ikm-onay-ikon"><i class="fa-solid ' + (tur === 'tehlike' ? 'fa-trash-can' : 'fa-circle-question') + '"></i></span><h3>' + esc(o.baslik || 'Emin misiniz?') + '</h3>' + (o.mesaj ? '<p>' + esc(o.mesaj) + '</p>' : '') +
                '<div class="ikm-onay-dugmeler"><button type="button" class="ikm-btn" data-h>' + esc(o.hayir || 'Vazgeç') + '</button><button type="button" class="ikm-btn ' + (tur === 'tehlike' ? 'ikm-btn-tehlike-dolu' : 'ikm-btn-ana') + '" data-e>' + esc(o.evet || 'Evet') + '</button></div></div>';
            document.body.appendChild(arka);
            function son(v) { if (bitti) return; bitti = true; arka.classList.add('cikis'); setTimeout(function () { arka.remove(); }, 160); coz(v); }
            $('[data-h]', arka).onclick = function () { son(false); }; $('[data-e]', arka).onclick = function () { son(true); }; arka.addEventListener('mousedown', function (e) { if (e.target === arka) son(false); });
            setTimeout(function () { $('[data-e]', arka).focus(); }, 30);
        });
    }
    function kopyala(m) { if (navigator.clipboard) navigator.clipboard.writeText(m).then(function () { bildir('Kopyalandı.', 'basari'); }, function () { bildir('Kopyalanamadı.', 'uyari'); }); }
    /** Bir kez gösterilen kullanıcı bilgileri (geçici şifre) */
    function waTel(t) { var d = String(t || '').replace(/\D/g, ''); if (d.indexOf('00') === 0) d = d.slice(2); if (d.charAt(0) === '0') d = '90' + d.slice(1); else if (d.length === 10) d = '90' + d; return d.length >= 11 ? d : ''; }
    function bilgiKutusu(baslik, ad, sifre, kurumKod, tel) {
        var wa = waTel(tel);
        var url = location.origin + location.pathname.replace(/[^/]*$/, '') + 'giris.php?kurum=' + encodeURIComponent(kurumKod || '');
        var metin = 'Giriş adresi: ' + url + '\nKullanıcı adı: ' + ad + (sifre ? '\nGeçici şifre: ' + sifre : '');
        var m = modal({ baslik: baslik, ikon: 'fa-key', gen: 520, govde: '<div class="pf-gizli-kutu">' +
            '<div class="pf-satir-kopya"><span>Giriş adresi</span><code>' + esc(url) + '</code></div><div class="pf-satir-kopya"><span>Kullanıcı adı</span><code>' + esc(ad) + '</code></div>' +
            (sifre ? '<div class="pf-satir-kopya"><span>Geçici şifre</span><code>' + esc(sifre) + '</code></div><small>Bu şifre yalnızca <b>şimdi</b> gösterilir. Kişi ilk girişte kendi şifresini belirler.</small>' : '<small>Mevcut kullanıcının şifresi değişmedi.</small>') + '</div>',
            alt: (wa ? '<a class="ikm-btn ikm-wa" target="_blank" rel="noopener" href="https://wa.me/' + wa + '?text=' + encodeURIComponent(metin) + '"><i class="fa-brands fa-whatsapp"></i> WhatsApp ile gönder</a>' : '') + '<button type="button" class="ikm-btn" id="pf-kop"><i class="fa-regular fa-copy"></i> Kopyala</button><button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Tamam</button>' });
        $('#pf-kop', m.el).onclick = function () { kopyala(metin); };
    }

    /* ── Sekmeler ── */
    var GORUNUM = {}, BASLIK = {}, aktif = '';
    function kayit(ad, baslik, alt, ciz) { GORUNUM[ad] = ciz; BASLIK[ad] = [baslik, alt]; }
    function git(ad, yenile) {
        if (!GORUNUM[ad]) ad = 'ozet';
        aktif = ad; $$('.ikm-nav', kok).forEach(function (n) { n.classList.toggle('aktif', n.dataset.s === ad); });
        $('#pf-baslik').textContent = BASLIK[ad][0]; $('#pf-alt').textContent = BASLIK[ad][1]; $('#pf-ust-sag').innerHTML = '';
        document.title = BASLIK[ad][0] + ' — Platform'; kok.classList.remove('menu-acik');
        try { history.replaceState(null, '', '#' + ad); } catch (e) {}
        icerik.innerHTML = iskelet();
        Promise.resolve().then(function () { return GORUNUM[ad](); }).catch(function (e) { icerik.innerHTML = bosKutu('fa-triangle-exclamation', 'Yüklenemedi', e.message); });
    }
    $('#pf-menu').addEventListener('click', function (e) { var b = e.target.closest('.ikm-nav'); if (b) git(b.dataset.s); });
    $('#ikm-menu-ac').onclick = function () { kok.classList.add('menu-acik'); }; $('#ikm-perde').onclick = $('#ikm-yan-kapat').onclick = function () { kok.classList.remove('menu-acik'); };
    $('#pf-cikis').onclick = function () { gonder('cikis').then(function () { location.href = 'platform.php'; }); };
    $('#pf-tema').onclick = function () { var d = document.documentElement, m = d.getAttribute('data-tema') || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'), y = m === 'dark' ? 'light' : 'dark'; d.setAttribute('data-tema', y); try { localStorage.setItem('vk_tema', y); } catch (e) {} };

    /* ======================= GENEL BAKIŞ ======================= */
    kayit('ozet', 'Genel bakış', 'Platformun anlık durumu.', function () {
        return getir({ islem: 'ozet' }).then(function (j) {
            var kutu = function (renk, ikon, d, ad, hedef) { return '<button type="button" class="ikm-kpi ' + renk + '" data-kpi="' + (hedef || '') + '" title="Listeyi gör" style="text-align:left;cursor:pointer;font:inherit;color:inherit"><span class="ikm-kpi-ikon"><i class="fa-solid ' + ikon + '"></i></span><div><b>' + d + '</b><span>' + ad + '</span></div></button>'; };
            var yeni = j.yeni.length ? j.yeni.map(function (k) { return '<button type="button" class="ikm-satir" data-kurum="' + k.id + '"><span class="pf-kurum-av" style="--c:' + esc(k.renk) + ';width:40px;height:40px">' + (k.logo ? '<img src="' + esc(k.logo) + '" alt="">' : esc(bas(k.ad))) + '</span><span class="ikm-satir-ic"><b>' + esc(k.ad) + '</b><small>' + esc(k.kod) + ' · ' + tarih(k.olusturma) + '</small></span></button>'; }).join('') : bosKutu('fa-building', 'Henüz kurum yok', 'İlk kurumu “Kurumlar” bölümünden ekleyin.');
            var son = j.son.length ? j.son.map(function (s) { return '<div class="ikm-satir"><span class="ikm-satir-ikon"><i class="fa-solid fa-clock-rotate-left"></i></span><span class="ikm-satir-ic"><b>' + esc(OLAY[s.olay] || s.olay) + '</b><small>' + esc((s.kurum ? s.kurum + ' · ' : '') + (s.ayrinti || '') + ' · ' + tarih(s.zaman)) + '</small></span></div>'; }).join('') : bosKutu('fa-clipboard-list', 'Kayıt yok');
            icerik.innerHTML = '<div class="ikm-kpiler">' + kutu('yesil', 'fa-building', j.aktif, 'Aktif kurum', 'aktif') + kutu('turuncu', 'fa-pause', j.askida, 'Askıdaki kurum', 'askida') + kutu('mavi', 'fa-users', j.kullanici, 'Toplam kullanıcı', 'tum') + kutu('mor', 'fa-user-check', j.kullanici_aktif, 'Aktif kullanıcı', 'tum') + '</div>' +
                '<div class="ikm-izgara iki"><div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-building"></i> Son eklenen kurumlar</h3><button type="button" class="ikm-btn ikm-btn-kucuk" data-git="kurumlar">Tümü</button></div><div class="ikm-liste">' + yeni + '</div></div>' +
                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-clipboard-list"></i> Son işlemler</h3><button type="button" class="ikm-btn ikm-btn-kucuk" data-git="kayitlar">Tümü</button></div><div class="ikm-liste">' + son + '</div></div></div>';
            $$('[data-kpi]', icerik).forEach(function (b) { b.onclick = function () { KURUM_DURUM = b.dataset.kpi === 'tum' ? '' : b.dataset.kpi; git('kurumlar'); }; });
            $$('[data-git]', icerik).forEach(function (b) { b.onclick = function () { git(b.dataset.git); }; });
            $$('[data-kurum]', icerik).forEach(function (b) { b.onclick = function () { git('kurumlar'); }; });
        });
    });
    var OLAY = { yedek_indir: 'Yedek indirildi', paket_indir: 'Güncelleme paketi indirildi', guncelleme: 'Sistem güncellendi', anahtar_yenile: 'Güncelleme anahtarı yenilendi', kurum_olustur: 'Kurum oluşturuldu', kurum_duzenle: 'Kurum düzenlendi', kurum_askiya: 'Kurum askıya alındı', kurum_aktif: 'Kurum etkinleştirildi', kurum_lisans: 'Kullanım süresi güncellendi', lisans_mesaj: 'Süre mesajı gönderildi', lm_ayar: 'Süre mesajı ayarları kaydedildi', toplu_mesaj: 'Toplu mesaj gönderildi', kurum_sil: 'Kurum silindi', yetkili_ata: 'Kurum yetkilisi atandı', sifre_sifirla: 'Şifre sıfırlandı', destek_girisi: 'Destek girişi yapıldı', sema_onar: 'Kurum şeması onarıldı', ayar_kaydet: 'Site ayarları kaydedildi', dosya_yukle: 'Dosya yüklendi', giris: 'Kurum girişi', ilk_sifre: 'İlk şifre belirlendi', platform_giris: 'Platform girişi', platform_sifre: 'Platform şifresi değişti', yonetici_ekle: 'Yönetici eklendi', yonetici_duzenle: 'Yönetici düzenlendi', yonetici_sil: 'Yönetici silindi', kurulum: 'Kurulum tamamlandı' };

    /* ======================= KURUMLAR ======================= */
    var KURUMLAR = [], KURUM_DURUM = '';
    kayit('kurumlar', 'Kurumlar', 'Müşteri kurumlarını ekleyin, yetkilisini atayın, durumunu yönetin.', function () {
        $('#pf-ust-sag').innerHTML = '<button type="button" class="ikm-btn ikm-btn-ana" id="pf-kurum-yeni"><i class="fa-solid fa-plus"></i> <span>Yeni kurum</span></button>';
        $('#pf-kurum-yeni').onclick = function () { kurumFormu(null); };
        return Promise.all([getir({ islem: 'kurumlar' }), getir({ islem: 'sunucu_liste' }).catch(function () { return null; })]).then(function (r) { KURUMLAR = r[0].kurumlar; SUNUCU = r[1]; kurumCiz(''); });
    });
    var SEC = {}, SIRA = 'ad', AZ_GUN = 10;
    function sureGrup(k) { var l = k.lisans || {}; if (!l.durum || l.durum === 'sinirsiz') return 'sinirsiz'; if (l.durum === 'askida') return 'bitti'; if (l.durum === 'ek_sure') return 'ek'; if (l.kalan !== null && l.kalan <= AZ_GUN) return 'azalan'; return 'normal'; }
    function filtreUyar(k, f) { if (!f) return true; if (f === 'uzak') return !!(k.sunucu && k.sunucu.tur === 'uzak'); if (f === 'uyarili') return !!(k.sunucu && k.sunucu.uyari); if (f === 'deneme') return !!(k.lisans && k.lisans.tur === 'deneme'); if (f === 'aktif' || f === 'askida') return k.durum === f; return sureGrup(k) === f; }
    var FILTRELER = [['', 'Tümü', 'fa-layer-group'], ['aktif', 'Aktif', 'fa-circle-check'], ['askida', 'Askıda', 'fa-pause'], ['azalan', 'Süresi azalan', 'fa-hourglass-half'], ['ek', 'Süresi dolan', 'fa-triangle-exclamation'], ['bitti', 'Süre bitti', 'fa-ban'], ['deneme', 'Deneme', 'fa-flask'], ['uzak', 'Uzak sunucu', 'fa-server'], ['uyarili', 'Bağlantı uyarısı', 'fa-link-slash'], ['sinirsiz', 'Sınırsız', 'fa-infinity']];
    function sureCubuk(k) {
        var l = k.lisans || {}; if (!l.durum || l.durum === 'sinirsiz' || l.kalan === null) return '';
        var topl = 30; if (k.lisans_baslangic && l.bitis) { var d = Math.round((new Date(l.bitis) - new Date(k.lisans_baslangic)) / 86400000); if (d > 0) topl = d; }
        var yuz = l.kalan <= 0 ? 4 : Math.max(4, Math.min(100, Math.round(l.kalan / topl * 100)));
        var renk = l.durum === 'aktif' && l.kalan > AZ_GUN ? 'var(--ik-yesil)' : (l.durum === 'aktif' || l.durum === 'yaklasiyor' ? 'var(--ik-turuncu)' : 'var(--ik-kirmizi)');
        return '<div class="pf-sure-cubuk" title="' + (l.kalan > 0 ? l.kalan + ' gün kaldı' : 'Süre doldu') + '"><i style="width:' + yuz + '%;background:' + renk + '"></i></div>';
    }
    /* ── Sunucu kopyası / taşıma onayı ── */
    var SUNUCU = null;
    function bildirimAyar() {
        var S = SUNUCU || {};
        var m = modal({ baslik: 'Uyarı bildirimi', ikon: 'fa-bell', gen: 480, govde: '<div class="ikm-form"><p class="ikm-soluk" style="margin:0 0 10px;font-size:13px">Bir kurum başka siteye taşındığında ya da uygulama başka sunucuda açıldığında bu adrese e-posta/SMS gönderilir. Gönderim, <b>Süre mesajları</b>\'ndaki “Gönderen kurum” kanalından yapılır' + (S.kanal_hazir ? '.' : ' — <b style="color:var(--ik-kirmizi)">henüz seçilmemiş</b>.') + '</p><label class="ikm-alan"><span>E-posta</span><input id="ub-mail" type="email" value="' + esc(S.uyari_mail || '') + '" placeholder="ornek@firma.com"></label><label class="ikm-alan"><span>Telefon (SMS)</span><input id="ub-tel" value="' + esc(S.uyari_tel || '') + '" placeholder="05xx xxx xx xx"></label></div>', alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn" id="ub-dene">Deneme gönder</button><button type="button" class="ikm-btn ikm-btn-ana" id="ub-kaydet">Kaydet</button>' });
        $('[data-kapat]', m.el).onclick = m.kapat;
        function kaydet() { return gonder('uyari_bildirim_kaydet', { mail: $('#ub-mail', m.el).value, tel: $('#ub-tel', m.el).value }); }
        $('#ub-kaydet', m.el).onclick = function () { kaydet().then(function () { m.kapat(); bildir('Kaydedildi.', 'basari'); git('kurumlar'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
        $('#ub-dene', m.el).onclick = function () { kaydet().then(function () { return gonder('uyari_bildirim_dene', {}); }).then(function () { bildir('Deneme gönderildi (gönderen kurum kanalı hazırsa).', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
    }
    function sunucuBant() {
        return sunucuBant0() + '<div class="pf-sv-ince"><i class="fa-solid fa-user-pen"></i> Kullanıcı adları: <b>ad soyad birleşik</b> (ali.veli → aliveli) <button type="button" class="ikm-btn ikm-btn-kucuk" id="kadi-nokta">Mevcutları güncelle</button></div>';
    }
    function sunucuBant0() {
        var S = SUNUCU; if (!S || !S.liste) return '';
        var bek = S.liste.filter(function (x) { return x.durum === 'bekliyor'; }).length, engel = S.liste.filter(function (x) { return x.durum === 'engelli'; }).length;
        var phpUyari = S.php && S.php.risk ? '<div class="pf-php-uyari"><i class="fa-solid fa-triangle-exclamation"></i><span><b>PHP güvenlik güncellemesi gerekebilir.</b> Bu sunucuda PHP ' + esc(S.php.surum) + ' çalışıyor. PHP2Shell açıkları (CVE-2026-109618 / CVE-2026-109619) <b>' + esc(S.php.duzeltme) + '</b> sürümlerinde kapatıldı. Hosting sağlayıcınız yamayı eski sürüm numarasıyla yayınlamış olabilir; yazılı olarak teyit edin ya da PHP sürümünü güncelleyin.</span></div>' : '';
        var ince = '<div class="pf-sv-ince"><i class="fa-solid fa-shield-halved"></i> Sunucu kopyası algılama: <b>' + (S.kilit ? 'açık' : 'kapalı') + '</b> <button type="button" class="ikm-btn ikm-btn-kucuk" data-sv-kilit="' + (S.kilit ? 0 : 1) + '">' + (S.kilit ? 'Kapat' : 'Aç') + '</button> · Uyarı bildirimi: <b>' + (S.uyari_mail || S.uyari_tel ? esc([S.uyari_mail, S.uyari_tel].filter(Boolean).join(', ')) : 'kapalı') + '</b> <button type="button" class="ikm-btn ikm-btn-kucuk" id="sv-bildirim">Ayarla</button></div>';
        if (!bek && !engel && S.liste.length < 2) return phpUyari + ince;
        var ad = { aktif: 'Çalışıyor', bekliyor: 'Onay bekliyor', engelli: 'Engelli' };
        var kartlar = S.liste.map(function (x) {
            return '<div class="pf-sv-kart ' + x.durum + '"><div class="pf-sv-ust"><b><i class="fa-solid fa-server"></i> ' + esc(x.ad || 'Sunucu') + (x.ben ? ' <small>(şu an bağlı olduğunuz)</small>' : '') + '</b><span class="pf-sv-chip ' + x.durum + '">' + ad[x.durum] + '</span></div>' +
                '<small>' + esc(x.url || x.yol) + '</small><small>' + esc(x.yol) + (x.ip ? ' · ' + esc(x.ip) : '') + '</small><small>İlk görülme: ' + tarih(x.ilk) + ' · Son: ' + tarih(x.son) + '</small>' +
                (x.durum !== 'aktif' || bek ? '<button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" data-sv-karar="' + x.id + '"><i class="fa-solid fa-circle-check"></i> Bu sunucuda devam et</button>' : '') + '</div>';
        }).join('');
        return '<section class="pf-sv-bant ' + (bek ? 'uyari' : '') + '"><h3><i class="fa-solid ' + (bek ? 'fa-triangle-exclamation' : 'fa-server') + '"></i> ' + (bek ? 'Sunucu değişikliği algılandı — onayınız bekleniyor' : 'Sunucular') + '</h3>' +
            (bek ? '<p>Uygulama başka bir sunucuya kopyalanmış ya da taşınmış. <b>Onaylayana kadar tüm sunucularda kurum girişleri kapalıdır.</b> Hangi sunucuda devam edilecekse onu seçin; diğerleri engellenir.</p>' : '<p>Bu uygulama yalnızca işaretli sunucuda çalışır; başka bir sunucuya kopyalanırsa önce sizden onay istenir.</p>') +
            '<div class="pf-sv-liste">' + kartlar + '</div></section>' + phpUyari + ince;
    }
    function sunucuBantBagla() {
        var kn = $('#kadi-nokta', icerik); if (kn) kn.onclick = function () {
            gonder('kullanici_nokta_kaldir', { kuru: 1 }).then(function (j) {
                if (!j.say) return bildir('Noktalı kullanıcı adı yok' + (j.atla.length ? ' (' + j.atla.length + ' kayıt çakışma nedeniyle atlanır).' : '.'), 'basari');
                onayla({ baslik: j.say + ' kullanıcı adındaki nokta kaldırılsın mı?', mesaj: 'Örn. ali.veli → aliveli. Eski adla (ali.veli) giriş yapılsa da otomatik olarak yeni hesaba ulaşılır; şifreler değişmez.' + (j.atla.length ? ' Çakışan ' + j.atla.length + ' ad olduğu gibi kalır.' : ''), evet: 'Güncelle', hayir: 'Vazgeç' }).then(function (e) {
                    if (!e) return; gonder('kullanici_nokta_kaldir', {}).then(function (r) { bildir(r.say + ' kullanıcı adı güncellendi' + (r.atla.length ? ', ' + r.atla.length + ' kayıt atlandı: ' + r.atla.slice(0, 3).join('; ') : '.'), 'basari'); });
                });
            }).catch(function (x) { bildir(x.message, 'hata'); });
        };
        var bb = $('#sv-bildirim', icerik); if (bb) bb.onclick = bildirimAyar;
        $$('[data-sv-karar]', icerik).forEach(function (b) { b.onclick = function () {
            onayla({ baslik: 'Bu sunucuda devam edilsin mi?', mesaj: 'Seçtiğiniz sunucu çalışmaya devam eder; diğer tüm sunucular engellenir ve kurum girişleri orada kapanır.', evet: 'Devam et', hayir: 'Vazgeç' }).then(function (e) {
                if (!e) return; gonder('sunucu_karar', { id: b.dataset.svKarar }).then(function () { bildir('Sunucu kararı kaydedildi.', 'basari'); git('kurumlar'); }).catch(function (x) { bildir(x.message, 'hata'); });
            });
        }; });
        $$('[data-sv-kilit]', icerik).forEach(function (b) { b.onclick = function () { gonder('sunucu_kilit', { ac: b.dataset.svKilit }).then(function () { git('kurumlar'); }).catch(function (x) { bildir(x.message, 'hata'); }); }; });
    }
    function kurumCiz(f) {
        var a = (f || '').toLocaleLowerCase('tr-TR');
        var liste = KURUMLAR.filter(function (k) { if (!filtreUyar(k, KURUM_DURUM)) return false; return !a || (k.ad + ' ' + k.kod + ' ' + k.yetkili_ad).toLocaleLowerCase('tr-TR').indexOf(a) >= 0; });
        liste.sort(function (x, y) {
            if (SIRA === 'sure') { var p = x.lisans && x.lisans.kalan !== null && x.lisans.durum !== 'sinirsiz' ? x.lisans.kalan : 99999, q = y.lisans && y.lisans.kalan !== null && y.lisans.durum !== 'sinirsiz' ? y.lisans.kalan : 99999; return p - q || x.ad.localeCompare(y.ad, 'tr'); }
            if (SIRA === 'yeni') return String(y.olusturma).localeCompare(String(x.olusturma));
            return x.ad.localeCompare(y.ad, 'tr');
        });
        var secN = Object.keys(SEC).filter(function (id) { return KURUMLAR.some(function (k) { return String(k.id) === id; }); }).length;
        var h = '<div class="pf-arac"><div class="ikm-ara"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="pf-ara" placeholder="Kurum, kod veya yetkili ara…" value="' + esc(f || '') + '"></div>' +
            '<label class="pf-sirala" title="Sıralama"><i class="fa-solid fa-arrow-down-wide-short"></i><select id="pf-sira"><option value="ad"' + (SIRA === 'ad' ? ' selected' : '') + '>Ada göre</option><option value="sure"' + (SIRA === 'sure' ? ' selected' : '') + '>Süresi en az olan önce</option><option value="yeni"' + (SIRA === 'yeni' ? ' selected' : '') + '>En yeni önce</option></select></label>' +
            '<button type="button" class="ikm-btn ikm-btn-kucuk" data-sb="hepsi"><i class="fa-regular fa-square-check"></i> Listedekileri seç</button></div>' +
            '<div class="pf-filtreler" role="group" aria-label="Kurum filtresi">' + FILTRELER.map(function (x) { var n = KURUMLAR.filter(function (k) { return filtreUyar(k, x[0]); }).length; return '<button type="button" class="pf-filtre' + (KURUM_DURUM === x[0] ? ' aktif' : '') + (x[0] && !n ? ' bos' : '') + '" data-durum="' + x[0] + '"><i class="fa-solid ' + x[2] + '"></i><span>' + x[1] + '</span><em>' + n + '</em></button>'; }).join('') + '</div>';
        if (!KURUMLAR.length) h += bosKutu('fa-building', 'Henüz kurum yok', '“Yeni kurum” ile ilk müşteriyi ekleyin; yetkilisi giriş bilgilerini alır.');
        else if (!liste.length) h += bosKutu('fa-magnifying-glass', 'Eşleşen kurum yok', 'Filtreyi değiştirin ya da aramayı temizleyin.');
        else h += '<div class="ikm-izgara">' + liste.map(function (k) {
            var sinir = +k.kullanici_siniri ? ' / ' + k.kullanici_siniri : '';
            return '<div class="pf-kurum-kart' + (k.durum !== 'aktif' ? ' askida' : '') + (SEC[k.id] ? ' secili' : '') + '" style="--c:' + esc(k.renk) + '" data-id="' + k.id + '"><div class="pf-kurum-ust"><label class="pf-sec" title="Toplu işlem için seç"><input type="checkbox" data-sec="' + k.id + '"' + (SEC[k.id] ? ' checked' : '') + ' aria-label="' + esc(k.ad) + ' seç"><i></i></label><span class="pf-kurum-av">' + (k.logo ? '<img src="' + esc(k.logo) + '" alt="">' : esc(bas(k.ad))) + '</span><div style="flex:1;min-width:0"><b>' + esc(k.ad) + '</b><small>' + esc(k.kod) + '</small></div>' + (k.lisans && k.lisans.tur === 'deneme' ? '<span class="ikm-cip mor">Deneme</span> ' : '') + '<span class="ikm-cip ' + (k.durum === 'aktif' ? 'yesil' : 'turuncu') + '">' + (k.durum === 'aktif' ? 'Aktif' : 'Askıda') + '</span></div>' +
                '<div class="pf-meta"><div><span>Kurum yetkilisi</span><b>' + esc(k.yetkili_ad || '—') + '</b>' + (k.yetkili_kullanici ? '<small style="color:var(--ik-soluk)"> @' + esc(k.yetkili_kullanici) + '</small>' : '') + '</div><div><span>Kullanıcı</span><b>' + k.kullanici_aktif + sinir + ' aktif</b></div><div><span>Yetkili son giriş</span><b>' + tarih(k.yetkili_son) + '</b></div><div><span>Oluşturma</span><b>' + tarih(k.olusturma) + '</b></div><div style="grid-column:1/-1"><span>Barındırma</span>' + sunucuChip(k.sunucu, k.id) + '</div><div style="grid-column:1/-1"><span>Kullanım süresi</span>' + lisansChip(k.lisans) + sureCubuk(k) + '</div></div>' +
                '<div class="pf-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk" data-a="duzenle"><i class="fa-solid fa-pen"></i> Düzenle</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-a="yetkili"><i class="fa-solid fa-user-shield"></i> Yetkili</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-a="paket" title="Modül paketleri ve indirim"><i class="fa-solid fa-cube"></i> Paketler</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-a="mesaj" title="Bu kuruma SMS / e-posta gönder"><i class="fa-solid fa-paper-plane"></i> Mesaj</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-a="uzak" title="Kurumu başka bir sunucuda (vekil) yayınla"><i class="fa-solid fa-server"></i> Sunucu</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-a="gir" title="Kurum yetkilisi olarak aç (kayıt altına alınır)"><i class="fa-solid fa-arrow-right-to-bracket"></i> Aç</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-a="tasi" title="Bu kurumu başka sunucuya taşımak için bağımsız paket indir"><i class="fa-solid fa-box-archive"></i> Taşı</button>' +
                (k.akt ? aktDugme(k) : '<button type="button" class="ikm-btn ikm-btn-kucuk" data-a="aktkopya" title="Bu kurumun kopyasını aktivasyon kodlu paket olarak çıkar"><i class="fa-solid fa-key"></i> Kodlu kopya</button>') + '<button type="button" class="ikm-btn ikm-btn-kucuk" data-a="durum">' + (k.durum === 'aktif' ? '<i class="fa-solid fa-pause"></i> Askıya al' : '<i class="fa-solid fa-play"></i> Etkinleştir') + '</button></div></div>';
        }).join('') + '</div>';
        if (secN) h += '<div class="pf-secbar" role="region" aria-label="Toplu işlem"><b><i class="fa-solid fa-circle-check"></i> ' + secN + ' kurum seçili</b><button type="button" class="ikm-btn ikm-btn-kucuk" data-sb="temizle">Temizle</button><button type="button" class="ikm-btn ikm-btn-ana" data-sb="mesaj"><i class="fa-solid fa-paper-plane"></i> Toplu mesaj gönder</button></div>';
        icerik.innerHTML = sunucuBant() + h;
        sunucuBantBagla();
        $$('[data-vk]', icerik).forEach(function (b) { b.onclick = function () {
            var id = b.dataset.vkid, t = b.dataset.vk, ist = t === 'engel' ? { baslik: 'Engellensin mi?', mesaj: 'Anahtar yenilenir; taşınan sitedeki ve mevcut sitedeki dosyalar geçersiz olur, yeni dosya indirmeniz gerekir.', evet: 'Engelle', tur: 'tehlike' } : t === 'ekle' ? { baslik: 'İkisi de aktif olsun mu?', mesaj: 'Hem mevcut hem taşınan site aynı kuruma erişir. Veriler yine bu sunucuda tutulur.', evet: 'İkisini de etkinleştir' } : { baslik: 'Yeni site etkinleştirilsin mi?', mesaj: 'Yalnızca taşınan adres çalışır; eski adres kapanır.', evet: 'Etkinleştir' };
            onayla(ist).then(function (e) { if (!e) return; var p = t === 'engel' ? gonder('vekil_yenile', { kurum_id: id, host_koru: '1' }) : gonder('vekil_onayla', { kurum_id: id, mod: t }); return p.then(function () { bildir(t === 'engel' ? 'Engellendi; yeni anahtar üretildi.' : 'Onaylandı.', 'basari'); git('kurumlar'); }); }).catch(function (x) { bildir(x.message, 'hata'); });
        }; });
        $$('[data-durum]', icerik).forEach(function (b) { b.onclick = function () { KURUM_DURUM = b.dataset.durum; kurumCiz($('#pf-ara').value); }; });
        $('#pf-sira').onchange = function () { SIRA = this.value; kurumCiz($('#pf-ara').value); };
        $$('[data-sec]', icerik).forEach(function (c) { c.onchange = function () { if (c.checked) SEC[c.dataset.sec] = 1; else delete SEC[c.dataset.sec]; kurumCiz($('#pf-ara').value); }; });
        $$('[data-sb]', icerik).forEach(function (b) {
            b.onclick = function () {
                if (b.dataset.sb === 'hepsi') { liste.forEach(function (k) { SEC[k.id] = 1; }); kurumCiz($('#pf-ara').value); }
                else if (b.dataset.sb === 'temizle') { SEC = {}; kurumCiz($('#pf-ara').value); }
                else topluMesaj(Object.keys(SEC).map(Number));
            };
        });
        var ara = $('#pf-ara'); ara.oninput = function () { var p = ara.selectionStart; kurumCiz(ara.value); var y = $('#pf-ara'); y.focus(); try { y.setSelectionRange(p, p); } catch (e) {} };
        $$('.pf-kurum-kart', icerik).forEach(function (kart) {
            var k = KURUMLAR.filter(function (x) { return String(x.id) === kart.dataset.id; })[0];
            kart.addEventListener('click', function (e) {
                var b = e.target.closest('[data-a]'); if (!b) return;
                if (b.dataset.a === 'duzenle') kurumFormu(k); else if (b.dataset.a === 'yetkili') yetkiliFormu(k);
                else if (b.dataset.a === 'paket') { if (window.PFP) window.PFP.kurumPaketi(k); } else if (b.dataset.a === 'durum') durumDegistir(k); else if (b.dataset.a === 'gir') destekGirisi(k); else if (b.dataset.a === 'oto') uzakSunucu(k, true); else if (b.dataset.a === 'tasi') tasiPaketi(k); else if (b.dataset.a === 'akt') aktModal(k); else if (b.dataset.a === 'aktkopya') aktKopya(k); else if (b.dataset.a === 'mesaj') topluMesaj([+k.id]); else if (b.dataset.a === 'uzak') uzakSunucu(k);
            });
        });
    }
    var LM_SABLON = {
        bitiyor: ['Kullanım süreniz bitiyor', '{kurum} kullanım süreniz {tarih} tarihinde bitiyor ({kalan} gün kaldı). Kesinti yaşamamak için süreyi yenileyin.'],
        bitti: ['Kullanım süreniz sona erdi', '{kurum} kullanım süreniz {tarih} tarihinde sona erdi. {son_durum} Yenilemek için bizimle iletişime geçin.'],
        duyuru: ['Bilgilendirme', 'Merhaba, {kurum} için kısa bir bilgilendirme: ']
    };
    var LM_DEGISKEN = ['{kurum}', '{kalan}', '{tarih}', '{ek_gun}', '{son_durum}', '{site}', '{destek}'];
    function degiskenSatiri() { return '<div class="pf-degisken"><small>Eklenebilir alanlar:</small>' + LM_DEGISKEN.map(function (d) { return '<button type="button" data-dg="' + d + '">' + d + '</button>'; }).join('') + '</div>'; }
    function topluMesaj(ids) {
        var kl = KURUMLAR.filter(function (k) { return ids.indexOf(+k.id) >= 0; }); if (!kl.length) { bildir('Önce kurum seçin.', 'uyari'); return; }
        var telEksik = kl.filter(function (k) { return !String(k.telefon || '').trim(); }).length, mailEksik = kl.filter(function (k) { return !String(k.email || '').trim(); }).length;
        var m = modal({ baslik: 'Mesaj gönder — ' + (kl.length === 1 ? kl[0].ad : kl.length + ' kurum'), ikon: 'fa-paper-plane', gen: 660, govde:
            '<div class="pf-mesaj"><div class="pf-kanal-sec"><label class="pf-kanal-kart"><input type="checkbox" id="pm-sms" checked><span><i class="fa-solid fa-comment-sms"></i><b>SMS</b><small>' + (telEksik ? telEksik + ' kurumda telefon boş (yetkili telefonu denenir)' : 'Kurum telefonuna') + '</small></span></label>' +
            '<label class="pf-kanal-kart"><input type="checkbox" id="pm-mail" checked><span><i class="fa-regular fa-envelope"></i><b>E-posta</b><small>' + (mailEksik ? mailEksik + ' kurumda e-posta boş (yetkili e-postası denenir)' : 'Kurum e-postasına') + '</small></span></label></div>' +
            '<div class="pf-sablon"><span>Hazır metin:</span><button type="button" data-sbl="bitiyor">Süre bitiyor</button><button type="button" data-sbl="bitti">Süre bitti</button><button type="button" data-sbl="duyuru">Genel duyuru</button></div>' +
            '<label class="ikm-alan"><span>E-posta konusu</span><input id="pm-konu" maxlength="150" placeholder="Örn. Kullanım süreniz bitiyor"></label>' +
            '<label class="ikm-alan"><span>Mesaj <small>(SMS ve e-posta aynı metni kullanır)</small></span><textarea id="pm-mesaj" rows="5" placeholder="Mesajınızı yazın…"></textarea></label>' + degiskenSatiri() +
            '<div class="pf-onizle"><small>Önizleme — ' + esc(kl[0].ad) + '</small><div id="pm-onizle" class="pf-balon"></div><small id="pm-say"></small></div></div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="pm-gonder"><i class="fa-solid fa-paper-plane"></i> Gönder</button>' });
        var mesaj = $('#pm-mesaj', m.el), konu = $('#pm-konu', m.el), son = mesaj;
        function degistir(t, k) { var l = k.lisans || {}; var sd = l.durum === 'ek_sure' ? (l.ek_kalan + ' gün içinde yenilenmezse hesabınız askıya alınır.') : (l.durum === 'askida' ? 'Hesabınız askıya alınmıştır.' : ''); return t.replace(/\{kurum\}/g, k.ad).replace(/\{kalan\}/g, Math.max(0, +l.kalan || 0)).replace(/\{tarih\}/g, l.bitis ? tarih(l.bitis).slice(0, 10) : '').replace(/\{ek_gun\}/g, +l.ek_kalan || 0).replace(/\{son_durum\}/g, sd); }
        function onizle() { var t = degistir(mesaj.value, kl[0]); $('#pm-onizle', m.el).textContent = t || 'Mesaj yazdıkça burada görünür.'; $('#pm-say', m.el).textContent = t.length + ' karakter' + (t.length > 70 ? ' · SMS ~' + Math.ceil(t.length / 67) + ' parça (Türkçe karakterli SMS’te 70 karakter = 1 parça)' : ''); }
        mesaj.oninput = onizle; konu.onfocus = function () { son = konu; }; mesaj.onfocus = function () { son = mesaj; }; onizle();
        $$('[data-sbl]', m.el).forEach(function (b) { b.onclick = function () { var s = LM_SABLON[b.dataset.sbl]; konu.value = s[0]; mesaj.value = s[1]; onizle(); mesaj.focus(); }; });
        $$('[data-dg]', m.el).forEach(function (b) { b.onclick = function () { var p = son.selectionStart || son.value.length; son.value = son.value.slice(0, p) + b.dataset.dg + son.value.slice(son.selectionEnd || p); son.focus(); onizle(); }; });
        $('#pm-gonder', m.el).onclick = function () {
            var sms = $('#pm-sms', m.el).checked, mail = $('#pm-mail', m.el).checked;
            if (!sms && !mail) { bildir('SMS veya e-postadan en az birini seçin.', 'uyari'); return; }
            if (!mesaj.value.trim()) { bildir('Mesaj metnini yazın.', 'uyari'); return; }
            if (mail && !konu.value.trim()) { bildir('E-posta konusunu yazın.', 'uyari'); return; }
            var b = this; onayla({ baslik: kl.length + ' kuruma gönderilsin mi?', mesaj: (sms ? 'SMS' : '') + (sms && mail ? ' ve ' : '') + (mail ? 'e-posta' : '') + ' gönderilecek. Bu işlem geri alınamaz.', evet: 'Gönder' }).then(function (ok) {
                if (!ok) return; b.disabled = true;
                return gonder('kurum_toplu_mesaj', { ids: JSON.stringify(kl.map(function (k) { return +k.id; })), sms: sms ? '1' : '0', mail: mail ? '1' : '0', konu: konu.value, mesaj: mesaj.value }).then(function (r) {
                    m.kapat(); SEC = {}; bildir('Gönderildi: ' + r.kurum + ' kurum · SMS ' + r.sms + ' · e-posta ' + r.mail, r.hatalar.length ? 'uyari' : 'basari'); kurumCiz($('#pf-ara') ? $('#pf-ara').value : '');
                    if (r.hatalar.length) modal({ baslik: 'Gönderilemeyenler', ikon: 'fa-triangle-exclamation', gen: 560, govde: '<ul class="pf-hata-liste">' + r.hatalar.map(function (x) { return '<li>' + esc(x) + '</li>'; }).join('') + '</ul>', alt: '<button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Tamam</button>' });
                });
            }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); });
        };
    }
    function otoMetin(dk, gun) {
        dk = +dk || 0; if (gun) return 'her gün ' + gun; if (!dk) return '';
        if (dk < 60) return dk + ' dakikada bir'; if (dk % 1440 === 0) return dk === 1440 ? 'günde bir' : (dk / 1440) + ' günde bir'; if (dk % 60 === 0) return dk === 60 ? 'her saat' : (dk / 60) + ' saatte bir';
        return Math.floor(dk / 60) + ' sa ' + (dk % 60) + ' dk’da bir';
    }
    function otoChip(s) {
        var m = otoMetin(s.oto_dk, s.oto_gun);
        if (!s.vdb) return '<button type="button" data-a="oto" class="pf-sunucu yerel" style="cursor:pointer" title="Ayarlamak için tıklayın"><i class="fa-solid fa-clock-rotate-left"></i> Otomatik yedek <small>kapalı (veri tabanı bilgisi yok)</small></button>';
        if (!m) return '<button type="button" data-a="oto" class="pf-sunucu yerel" style="cursor:pointer" title="Ayarlamak için tıklayın"><i class="fa-solid fa-clock-rotate-left"></i> Otomatik yedek <small>kapalı</small></button>';
        return '<button type="button" data-a="oto" title="Ayarlamak için tıklayın" class="pf-sunucu uzak" style="cursor:pointer;background:color-mix(in srgb,#10b981 12%,transparent);border-color:color-mix(in srgb,#10b981 45%,var(--ik-cizgi));color:#059669"><i class="fa-solid fa-clock-rotate-left"></i> Otomatik yedek: açık <small>· ' + esc(m) + (s.oto_son ? ' · son ' + tarih(s.oto_son) : '') + '</small></button>';
    }
    function sunucuChip(s, kid) { var c = sunucuChip0(s, kid); return (s && s.tur === 'uzak' && !(s.uyari === 'host' && s.bekleyen_host)) ? '<div style="display:flex;flex-direction:column;gap:6px;align-items:flex-start">' + c + otoChip(s) + '</div>' : c; }
    function sunucuChip0(s, kid) {
        if (!s || s.tur !== 'uzak') return '<span class="pf-sunucu yerel"><i class="fa-solid fa-house-laptop"></i> Bu sunucu <small>(kendi hosting)</small></span>';
        var son = s.son_istek ? 'son istek ' + tarih(s.son_istek) : 'henüz bağlanmadı';
        if (s.uyari === 'host' && s.bekleyen_host) return '<div class="pf-tasindi"><b><i class="fa-solid fa-circle-pause"></i> Site durduruldu — farklı siteye taşındı</b><p>Taşınan adres: <code>' + esc(s.bekleyen_host) + '</code>' + (s.bekleyen_ip ? ' · IP ' + esc(s.bekleyen_ip) : '') + '<br><small>Şu an çalışan site: ' + esc(s.host || '—') + ' · Veriler ve giriş-çıkış kayıtları bu sunucuda tutulmaya devam eder.</small></p>' +
            '<div class="pf-tasindi-islem"><button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" data-vk="degistir" data-vkid="' + kid + '"><i class="fa-solid fa-right-left"></i> Yalnızca yeni siteyi etkinleştir</button><button type="button" class="ikm-btn ikm-btn-yesil ikm-btn-kucuk" data-vk="ekle" data-vkid="' + kid + '"><i class="fa-solid fa-clone"></i> İkisi de aktif olsun</button><button type="button" class="ikm-btn ikm-btn-tehlike ikm-btn-kucuk" data-vk="engel" data-vkid="' + kid + '"><i class="fa-solid fa-ban"></i> Engelle</button></div></div>';
        if (s.uyari) return '<span class="pf-sunucu uyari"><i class="fa-solid fa-triangle-exclamation"></i> Uzak sunucu · ' + esc(s.host || 'alan adı yok') + ' <small>— ' + esc(UYARI_AD[s.uyari] || 'bağlantı uyarısı') + '</small></span>';
        return '<span class="pf-sunucu uzak"><i class="fa-solid fa-server"></i> Uzak sunucu · ' + esc(s.host || 'ilk kullanımda bağlanır') + ' <small>(' + son + ')</small></span>';
    }
    var UYARI_AD = { host: 'başka sitede kullanıldı', imza: 'anahtar dosyası değiştirilmiş', dosya: 'vekil dosyası değiştirilmiş', kod_degisti: 'vekil dosyası değiştirilmiş', anahtar_yok: 'anahtar dosyası silinmiş', eski: 'saat uyumsuzluğu' };
    function uzakSunucu(k, otoGit) {
        getir({ islem: 'vekil_durum', kurum_id: k.id }).then(function (j) {
            var v = j.vekil, uzak = v.tur === 'uzak';
            var m = modal({ baslik: 'Barındırma — ' + k.ad, ikon: 'fa-server', gen: 700, govde: '<div id="uz-ic"></div>', alt: '<button type="button" class="ikm-btn" data-kapat>Kapat</button>' });
            var ic = $('#uz-ic', m.el);
            function yenile() { m.kapat(); uzakSunucu(k); KURUMLAR_YENILE(); }
            function post(i, d, msg) { return gonder(i, Object.assign({ kurum_id: k.id }, d || {})).then(function (r) { if (msg) bildir(msg, 'basari'); return r; }); }
            var D = j.vdb || { var: false, aktif: false };
            function vdbHtml() {
                var f = function (id, ad, deg, tip, ph) { return '<label class="ikm-alan"><span>' + ad + '</span><input id="' + id + '" type="' + (tip || 'text') + '" value="' + esc(deg == null ? '' : deg) + '" placeholder="' + esc(ph || '') + '" autocomplete="off"></label>'; };
                return '<h4 class="pf-uz-baslik"><i class="fa-solid fa-database"></i> Veri tabanı <span class="pf-vdb-rozet ' + (D.aktif ? 'uzak' : '') + '">' + (D.aktif ? 'Vekil sunucuda' : 'Ana sunucuda') + '</span></h4>' +
                    '<p class="ikm-soluk kucuk">Kurumun tüm verileri (personel, izinler, loglar…) vekil sunucudaki MySQL’de tutulabilir. Yüklenen dosyalar taşınmaz, ana sunucuda kalır. Vekil sunucudaki MySQL’in bu (ana) sunucunun IP’sinden bağlantıya izin vermesi gerekir (cPanel: Uzak MySQL).</p>' +
                    '<div class="pf-vdb-form"><div class="ikm-iki">' + f('vd-host', 'MySQL sunucu adresi', D.host, 'text', 'örn. mysql.kurumum.com veya IP') + f('vd-port', 'Port', D.port || 3306, 'number') + '</div>' +
                    '<div class="ikm-iki">' + f('vd-ad', 'Veri tabanı adı', D.ad) + f('vd-kul', 'Kullanıcı', D.kul) + '</div>' +
                    f('vd-sifre', 'Parola' + (D.sifre_var ? ' (değiştirmek için yazın)' : ''), '', 'password') + '</div>' +
                    (D.son_sonuc ? '<p class="pf-vdb-sonuc ' + (/başarılı/i.test(D.son_sonuc) ? 'ok' : 'kotu') + '"><i class="fa-solid ' + (/başarılı/i.test(D.son_sonuc) ? 'fa-circle-check' : 'fa-circle-xmark') + '"></i> ' + esc(D.son_sonuc) + (D.son_test ? ' <small>(' + tarih(D.son_test) + ')</small>' : '') + '</p>' : '') +
                    (D.tasima_ozet ? '<p class="ikm-soluk kucuk"><i class="fa-solid fa-clock-rotate-left"></i> Son taşıma: ' + esc(D.tasima_ozet) + ' · ' + tarih(D.tasima) + '</p>' : '') +
                    '<div class="pf-uz-dugme"><button type="button" class="ikm-btn" id="vd-test"><i class="fa-solid fa-plug"></i> Bağlantıyı dene</button><button type="button" class="ikm-btn" id="vd-kaydet"' + (D.aktif ? ' disabled title="Taşınmış veri tabanının bilgisi değiştirilemez; önce geri alın"' : '') + '><i class="fa-solid fa-floppy-disk"></i> Kaydet</button>' +
                    (D.var && !D.aktif ? '<button type="button" class="ikm-btn ikm-btn-ana" id="vd-tasi"><i class="fa-solid fa-truck-arrow-right"></i> Verileri vekil sunucuya taşı</button>' : '') +
                    (D.var ? '<button type="button" class="ikm-btn" id="vd-yedek"><i class="fa-solid fa-copy"></i> ' + (D.aktif ? 'Yedek al: vekil MySQL → ana sunucu' : 'Yedek al: ana sunucu → vekil MySQL') + '</button>' : '') +
                    (D.aktif ? '<button type="button" class="ikm-btn ikm-btn-tehlike" id="vd-geri"><i class="fa-solid fa-rotate-left"></i> Ana sunucuya geri al</button>' : '') + '</div>' +
                    (D.var ? '<div class="pf-vdb-oto"><label class="ikm-alan" style="margin:10px 0 0"><span><i class="fa-solid fa-clock-rotate-left"></i> Otomatik yedek <small class="ikm-soluk">(' + (D.aktif ? 'vekil MySQL → ana sunucu' : 'ana sunucu → vekil MySQL') + ')</small></span><select id="vd-oto">' + (function () { var on = [[0, 'Kapalı'], [5, 'Her 5 dakikada'], [10, 'Her 10 dakikada'], [15, 'Her 15 dakikada'], [30, 'Her 30 dakikada'], [60, 'Her saat'], [180, 'Her 3 saatte'], [360, 'Her 6 saatte'], [720, 'Her 12 saatte'], [1440, 'Günde bir (24 saat arayla)']], ek = [], sec = '', dk = +D.oto_dk || 0, bil = on.some(function (x) { return x[0] === dk; });
                        var o = on.map(function (x) { return '<option value="' + x[0] + '"' + (!D.oto_gun && dk === x[0] ? ' selected' : '') + '>' + x[1] + '</option>'; }).join('');
                        return o + '<option value="ozel"' + (!D.oto_gun && !bil ? ' selected' : '') + '>Özel aralık (dakika)…</option><option value="gun"' + (D.oto_gun ? ' selected' : '') + '>Her gün belirli saatte…</option>'; })() + '</select></label>' +
                        '<div class="pf-vdb-oto-ek" style="display:flex;gap:8px;align-items:center;margin-top:8px;flex-wrap:wrap"><input type="number" id="vd-oto-dk" min="5" max="10080" step="1" value="' + (+D.oto_dk || 15) + '" style="width:110px"' + ((!D.oto_gun && !(+D.oto_dk && [5, 10, 15, 30, 60, 180, 360, 720, 1440].indexOf(+D.oto_dk) < 0)) ? ' hidden' : '') + '><span id="vd-oto-dk-y" class="ikm-soluk kucuk"' + ((!D.oto_gun && !(+D.oto_dk && [5, 10, 15, 30, 60, 180, 360, 720, 1440].indexOf(+D.oto_dk) < 0)) ? ' hidden' : '') + '>dakikada bir</span><input type="time" id="vd-oto-gun" value="' + esc(D.oto_gun || '03:00') + '"' + (D.oto_gun ? '' : ' hidden') + '><button type="button" class="ikm-btn ikm-btn-ana" id="vd-oto-kaydet"><i class="fa-solid fa-floppy-disk"></i> Kaydet</button></div>' +
                        '<p id="vd-oto-durum" class="pf-vdb-sonuc ' + ((+D.oto_dk || D.oto_gun) ? 'ok' : 'kotu') + '" style="margin:8px 0 0"><i class="fa-solid ' + ((+D.oto_dk || D.oto_gun) ? 'fa-circle-check' : 'fa-circle-pause') + '"></i> Şu an otomatik yedek: <b>' + ((+D.oto_dk || D.oto_gun) ? 'AÇIK · ' + esc(otoMetin(D.oto_dk, D.oto_gun)) : 'KAPALI') + '</b></p>' +
                        (D.oto_son ? '<p class="ikm-soluk kucuk" style="margin:6px 0 0"><i class="fa-solid fa-circle-info"></i> Son otomatik yedek: ' + tarih(D.oto_son) + (D.oto_sonuc ? ' · ' + esc(D.oto_sonuc) : '') + '</p>' : '') + '<p class="ikm-soluk kucuk" style="margin:4px 0 0">Giriş yapmış herhangi bir kullanıcının sayfası açıkken (~20 sn’de bir kontrol) zamanı gelince kendiliğinden çalışır. Kimse girmese de <b>tam zamanında</b> çalışması için sunucuda cron ekleyin: <code>* * * * * php /yol/platform_vekil.php</code> (her dakika bakar, yalnızca zamanı gelenleri yedekler). Her seferinde tablolar baştan kopyalanır; büyük veride aralığı uzun tutun.</p></div>' : '');
            }
            var h = '<div class="pf-uz-ust ' + (uzak ? (v.uyari ? 'uyari' : 'uzak') : 'yerel') + '"><span class="pf-uz-ik"><i class="fa-solid ' + (uzak ? 'fa-server' : 'fa-house-laptop') + '"></i></span><div><b>' + (uzak ? 'Uzak sunucuda çalışıyor' : 'Bu sunucuda çalışıyor') + '</b><small>' + (uzak ? '' + (D.aktif ? 'Veri tabanı vekil sunucudaki MySQL’de tutulur; uygulama kodu ve dosyalar ana sunucuda kalır.' : 'Veriler ve veri tabanı bu (ana) sunucuda kalır; uzak sunucuda yalnızca vekil + anahtar dosyası bulunur.') + '' : 'Kurum kendi hostingimizde yayınlanıyor. İsterseniz başka bir sunucudan yayınlanacak şekilde bağlayabilirsiniz.') + '</small></div></div>';
            if (uzak && v.uyari) {
                h += '<div class="pf-uz-uyari"><b><i class="fa-solid fa-triangle-exclamation"></i> ' + esc(UYARI_AD[v.uyari] ? UYARI_AD[v.uyari].charAt(0).toLocaleUpperCase('tr-TR') + UYARI_AD[v.uyari].slice(1) : 'Bağlantı uyarısı') + '</b><p>' + esc(v.uyari_ayrinti) + '</p><small>' + tarih(v.uyari_zaman) + (v.bekleyen_ip ? ' · IP ' + esc(v.bekleyen_ip) : '') + ' — kurum bu sunucudan şu an <b>açılmıyor</b>.</small><div class="pf-uz-dugme">' +
                    (v.uyari === 'host' && v.bekleyen_host ? '<button type="button" class="ikm-btn ikm-btn-yesil ikm-btn-kucuk" id="uz-onay"><i class="fa-solid fa-check"></i> “' + esc(v.bekleyen_host) + '” adresini onayla</button>' : '') +
                    '<button type="button" class="ikm-btn ikm-btn-tehlike ikm-btn-kucuk" id="uz-engel"><i class="fa-solid fa-ban"></i> Engelle (anahtarı yenile)</button><button type="button" class="ikm-btn ikm-btn-kucuk" id="uz-tamam">Uyarıyı kapat</button></div></div>';
            }
            if (!uzak) {
                h += '<div class="pf-uz-form"><label class="ikm-alan"><span>Uzak sunucunun alan adı <small>(isteğe bağlı — boşsa ilk bağlanılan adres kaydedilir)</small></span><input id="uz-host" placeholder="kurumum.com"></label>' +
                    '<label class="ikm-alan"><span>Ana sunucu adresi <small>(vekil isteklerini buraya iletir)</small></span><input id="uz-ana" value="' + esc(j.ana) + '"></label>' +
                    '<button type="button" class="ikm-btn ikm-btn-ana" id="uz-olustur"' + (j.zip ? '' : ' disabled title="PHP zip eklentisi gerekli"') + '><i class="fa-solid fa-link"></i> Bağlantı oluştur ve dosyaları indir</button></div>' + adimlar();
            } else {
                h += '<div class="pf-uz-bilgi"><div><span>Kayıtlı alan adı</span><b>' + esc(v.host || '— (ilk bağlantıda kaydedilir)') + '</b></div><div><span>Son istek</span><b>' + (v.son_istek ? tarih(v.son_istek) : 'henüz yok') + '</b></div><div><span>Son IP</span><b>' + esc(v.son_ip || '—') + '</b></div><div><span>Oluşturma</span><b>' + tarih(v.olusturma) + '</b></div></div>' +
                    '<div class="pf-uz-dugme"><a class="ikm-btn ikm-btn-ana" href="' + API + '?islem=vekil_indir&kurum_id=' + k.id + '&_csrf=' + encodeURIComponent(csrf()) + '"><i class="fa-solid fa-download"></i> Vekil dosyalarını indir (.zip)</a><button type="button" class="ikm-btn" id="uz-yenile"><i class="fa-solid fa-arrows-rotate"></i> Anahtarı yenile</button><button type="button" class="ikm-btn ikm-btn-tehlike" id="uz-kapat"><i class="fa-solid fa-link-slash"></i> Uzak sunucuyu kapat</button></div>' + adimlar() +
                    vdbHtml() + '<h4 class="pf-uz-baslik">Son olaylar</h4>' + (j.olaylar.length ? '<div class="pf-uz-olay">' + j.olaylar.map(function (o) { return '<div class="' + (/^(host|imza|dosya|anahtar_yok|kod_degisti|eski)$/.test(o.olay) ? 'kotu' : '') + '"><b>' + esc(o.ad) + '</b><span>' + esc(o.ayrinti) + '</span><small>' + tarih(o.zaman) + (o.ip ? ' · ' + esc(o.ip) : '') + '</small></div>'; }).join('') + '</div>' : '<p class="pf-ipucu">Henüz olay yok.</p>');
            }
            ic.innerHTML = h;
            /* Zip'i fetch ile indirir: sunucu hata verirse mesaj gösterilir (sessiz kalmaz) */
            function zipIndir(k) {
                return fetch(API + '?islem=vekil_indir&kurum_id=' + k.id + '&_csrf=' + encodeURIComponent(csrf()), { credentials: 'same-origin' }).then(function (r) {
                    var tur = r.headers.get('Content-Type') || '';
                    if (!r.ok || tur.indexOf('zip') < 0) return r.text().then(function (t) { var m = ''; try { m = JSON.parse(t).hata; } catch (e) {} throw new Error(m || ('Zip indirilemedi (HTTP ' + r.status + '). PHP zip eklentisi kurulu olmayabilir.')); });
                    return r.blob();
                }).then(function (blob) {
                    var u = URL.createObjectURL(blob), a = document.createElement('a'); a.href = u; a.download = 'vekil_' + String(k.kod || k.id).replace(/[^a-z0-9_-]/gi, '') + '.zip';
                    document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(u); a.remove(); }, 1500);
                });
            }
            function adimlar() { return '<ol class="pf-uz-adim"><li>Zip içindeki <b>index.php</b>, <b>vekil.anahtar.php</b> ve <b>.htaccess</b> dosyalarını uzak sunucunun (alan adının) köküne yükleyin. Başka hiçbir dosya gerekmez.</li><li>Uzak sunucuda PHP 7+ ve curl eklentisi olmalı; veri tabanı bilgisi <b>girilmez</b>.</li><li>Alan adını açın: giriş sayfası ana sunucudan gelir. Dosyalar silinir/değiştirilirse ya da başka bir adreste kullanılırsa site açılmaz ve burada uyarı görünür.</li></ol>'; }
            var b;
            if ((b = $('#uz-olustur', ic))) b.onclick = (function (bt) { return function () { bt.disabled = true; post('vekil_olustur', { host: $('#uz-host', ic).value, ana: $('#uz-ana', ic).value }).then(function () { return zipIndir(k).then(function () { bildir('Bağlantı oluşturuldu; zip dosyası indirildi.', 'basari'); setTimeout(yenile, 800); }); }).catch(function (e) { bt.disabled = false; bildir(e.message, 'hata'); }); }; })(b);
            if ((b = $('#uz-yenile', ic))) b.onclick = function () { onayla({ baslik: 'Anahtar yenilensin mi?', mesaj: 'Eski dosyalar çalışmaz; yeni zip’i uzak sunucuya yüklemeniz gerekir.', evet: 'Yenile', tur: 'tehlike' }).then(function (ok) { if (ok) return post('vekil_yenile', { host_koru: '1' }, 'Anahtar yenilendi; yeni dosyaları indirin.').then(yenile); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            if ((b = $('#uz-engel', ic))) b.onclick = function () { onayla({ baslik: 'Engellensin mi?', mesaj: 'Anahtar yenilenir; şu an kullanılan tüm dosyalar geçersiz olur.', evet: 'Engelle', tur: 'tehlike' }).then(function (ok) { if (ok) return post('vekil_yenile', { host_koru: '1' }, 'Engellendi; yeni anahtar üretildi.').then(yenile); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            if ((b = $('#uz-onay', ic))) b.onclick = function () { post('vekil_onayla', {}, 'Alan adı onaylandı.').then(yenile).catch(function (e) { bildir(e.message, 'hata'); }); };
            if ((b = $('#uz-tamam', ic))) b.onclick = function () { post('vekil_uyari_tamam').then(yenile).catch(function (e) { bildir(e.message, 'hata'); }); };
            function vdbVeri() { return { host: $('#vd-host', ic).value, port: $('#vd-port', ic).value, ad: $('#vd-ad', ic).value, kul: $('#vd-kul', ic).value, sifre: $('#vd-sifre', ic).value }; }
            if ((b = $('#vd-test', ic))) b.onclick = function () { b.disabled = true; post('vekil_db_test', vdbVeri()).then(function (r) { bildir(r.sonuc + (r.uzak_tablo ? ' · hedefte ' + r.uzak_tablo + ' kurum tablosu var' : ''), r.ok ? 'basari' : 'hata'); b.disabled = false; }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); }); };
            if ((b = $('#vd-kaydet', ic))) b.onclick = function () { b.disabled = true; post('vekil_db_kaydet', vdbVeri()).then(function (r) { bildir('Kaydedildi: ' + r.sonuc, 'basari'); yenile(); }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); }); };
            if ((b = $('#vd-tasi', ic))) b.onclick = function () { onayla({ baslik: 'Veri tabanı vekil sunucuya taşınsın mı?', mesaj: 'Kurumun tüm tabloları kopyalanır ve doğrulanır, sonra kurum bu veri tabanını kullanmaya başlar. Taşıma bitene kadar kurum kullanıcıları işlem yapmamalı. Ana sunucudaki kopya silinmez.', evet: 'Taşı', tur: 'ana' }).then(function (ok) { if (!ok) return; b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Taşınıyor…'; return post('vekil_db_tasi', {}).then(function (r) { bildir(r.ozet, 'basari'); yenile(); }); }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); }); };
            if ((b = $('#vd-oto', ic))) (function (sel) {
                var dkG = $('#vd-oto-dk', ic), dkY = $('#vd-oto-dk-y', ic), gunG = $('#vd-oto-gun', ic), uyg = $('#vd-oto-kaydet', ic);
                var dur = $('#vd-oto-durum', ic);
                function goster() { var v = sel.value; dkG.hidden = dkY.hidden = v !== 'ozel'; gunG.hidden = v !== 'gun'; }
                function kaydet() {
                    var v = sel.value, d = { dk: 0, gun: '' };
                    if (v === 'ozel') d.dk = +dkG.value; else if (v === 'gun') d.gun = gunG.value; else d.dk = +v;
                    return post('vekil_db_oto', d).then(function () { var m = otoMetin(d.dk, d.gun); if (dur) { dur.className = 'pf-vdb-sonuc ' + (m ? 'ok' : 'kotu'); dur.innerHTML = '<i class="fa-solid ' + (m ? 'fa-circle-check' : 'fa-circle-pause') + '"></i> Kaydedildi — otomatik yedek: <b>' + (m ? 'AÇIK · ' + esc(m) : 'KAPALI') + '</b>'; } bildir(m ? 'Otomatik yedek açıldı: ' + m + '.' : 'Otomatik yedek kapatıldı.', 'basari'); KURUMLAR_YENILE(); }).catch(function (e) { bildir(e.message, 'hata'); });
                }
                sel.onchange = function () { goster(); };
                uyg.onclick = kaydet; goster();
            })(b);
            if ((b = $('#vd-yedek', ic))) b.onclick = function () { var uz = !!D.aktif; onayla({ baslik: 'Yedek alınsın mı?', mesaj: uz ? 'Vekil sunucudaki MySQL’in güncel hali ana sunucudaki (kullanılmayan) kopyanın üzerine yazılır. Kurum çalışmaya devam eder.' : 'Ana sunucudaki güncel veriler vekil sunucudaki MySQL’e kopyalanır (oradaki eski kopyanın üzerine yazılır). Kurum çalışmaya devam eder; kopya aldığınız andaki halidir.', evet: 'Yedek al', tur: 'ana' }).then(function (ok) { if (!ok) return; var e0 = b.innerHTML; b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Kopyalanıyor…'; return post('vekil_db_yedekle', {}).then(function (r) { bildir(r.ozet, 'basari'); yenile(); }); }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); }); };
            if ((b = $('#vd-geri', ic))) b.onclick = function () { onayla({ baslik: 'Ana sunucuya geri alınsın mı?', mesaj: 'Vekil sunucudaki veriler ana sunucuya kopyalanır ve kurum yeniden ana veri tabanını kullanır. Vekil sunucudaki kopya silinmez.', evet: 'Geri al', tur: 'tehlike' }).then(function (ok) { if (!ok) return; b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Geri alınıyor…'; return post('vekil_db_geri', {}).then(function (r) { bildir(r.ozet, 'basari'); yenile(); }); }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); }); };
            if ((b = $('#uz-kapat', ic))) b.onclick = function () { onayla({ baslik: 'Uzak sunucu kapatılsın mı?', mesaj: 'Kurum yeniden yalnızca bu sunucudan açılır; uzak sunucudaki dosyalar çalışmaz.', evet: 'Kapat', tur: 'tehlike' }).then(function (ok) { if (ok) return post('vekil_kapat', {}, 'Uzak sunucu bağlantısı kapatıldı.').then(yenile); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            if (otoGit) setTimeout(function () { var o = $('#vd-oto', m.el); if (!o) return; o.scrollIntoView({ block: 'center', behavior: 'smooth' }); o.focus(); var bx = o.style.boxShadow; o.style.transition = 'box-shadow .4s'; o.style.boxShadow = '0 0 0 4px rgba(99,102,241,.45)'; setTimeout(function () { o.style.boxShadow = bx; }, 1800); }, 150);
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }
    function KURUMLAR_YENILE() { if (aktif === 'kurumlar') git('kurumlar'); }
    function lisansChip(l) {
        if (!l || l.durum === 'sinirsiz') return '<span class="pf-lisans-chip"><i class="fa-solid fa-infinity"></i> Sınırsız</span>';
        var t = l.bitis ? l.bitis.split('-').reverse().join('.') : '';
        if (l.durum === 'aktif') return '<span class="pf-lisans-chip"><i class="fa-regular fa-calendar-check"></i> ' + l.kalan + ' gün kaldı</span><small style="color:var(--ik-soluk)"> · ' + t + '</small>';
        if (l.durum === 'yaklasiyor') return '<span class="pf-lisans-chip sari"><i class="fa-solid fa-hourglass-half"></i> ' + (l.kalan === 0 ? 'Bugün bitiyor' : l.kalan + ' gün kaldı') + '</span><small style="color:var(--ik-soluk)"> · ' + t + '</small>';
        if (l.durum === 'ek_sure') return '<span class="pf-lisans-chip kirmizi"><i class="fa-solid fa-triangle-exclamation"></i> Süre doldu · ek ' + l.ek_kalan + ' gün</span>';
        return '<span class="pf-lisans-chip gri"><i class="fa-solid fa-pause"></i> Süre bitti · askıda</span>';
    }
    function aktDugme(k) {
        var a = k.akt, d = a.durum, t = d === 'bekliyor' ? 'Aktivasyon: kod bekliyor' : d === 'aktif' ? (a.uyari ? 'Aktivasyon: uyarı' : 'Aktivasyon: aktif') : d === 'kilit' ? 'Aktivasyon: kilitli' : 'Aktivasyon: iptal';
        var ik = d === 'aktif' && !a.uyari ? 'fa-circle-check' : d === 'bekliyor' ? 'fa-key' : 'fa-triangle-exclamation';
        var st = d === 'aktif' && !a.uyari ? 'color:#059669' : d === 'bekliyor' ? 'color:#d97706' : 'color:#dc2626';
        return '<button type="button" class="ikm-btn ikm-btn-kucuk" data-a="akt" style="' + st + '" title="Aktivasyon kodlu paket"><i class="fa-solid ' + ik + '"></i> ' + t + '</button>';
    }
    function indir(a) {
        a.addEventListener('click', function (e) {
            e.preventDefault(); if (a.dataset.yuk) return; a.dataset.yuk = 1; var h = a.innerHTML; a.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Hazırlanıyor…';
            var ac = new AbortController(), zt = setTimeout(function () { ac.abort(); }, 240000);
            fetch(a.href, { credentials: 'same-origin', signal: ac.signal }).then(function (r) {
                var ct = r.headers.get('content-type') || '';
                if (!r.ok || ct.indexOf('zip') < 0) return r.text().then(function (t) { var j = null; try { j = JSON.parse(t); } catch (x) {} throw new Error((j && (j.hata || j.mesaj)) || ('Paket indirilemedi (HTTP ' + r.status + ').' + (t && t.length < 300 ? ' ' + t.replace(/<[^>]+>/g, '').trim() : ''))); });
                var ad = ((r.headers.get('content-disposition') || '').match(/filename="?([^";]+)/) || [])[1] || 'paket.zip';
                return r.blob().then(function (b) { var u = URL.createObjectURL(b), l = document.createElement('a'); l.href = u; l.download = ad; document.body.appendChild(l); l.click(); setTimeout(function () { URL.revokeObjectURL(u); l.remove(); }, 4000); bildir('Paket indirildi.', 'basari'); git('kurumlar'); });
            }).catch(function (er) { bildir(er.name === 'AbortError' ? 'Paket 4 dakikada hazırlanamadı (sunucu zaman aşımı ya da çok büyük dosyalar). Kapatıp “Yeni kod üret” ile tekrar deneyin; sorun sürerse “Dosyasız indir” kullanın.' : er.message, 'hata'); }).then(function () { clearTimeout(zt); a.innerHTML = h; delete a.dataset.yuk; });
        });
    }
    function aktKopya(k) {
        onayla({ baslik: 'Kodlu kopya oluşturulsun mu?', mesaj: k.ad + ' kurumunun şu anki verileriyle bir paket hazırlanır ve tek seferlik aktivasyon kodu üretilir. Kurum bu sunucuda (ve bağlı sunucuda) çalışmaya DEVAM eder; paket bir kopyadır, sonraki veri değişiklikleri kopyaya yansımaz.', evet: 'Kod üret' })
            .then(function (ok) { if (ok) return gonder('aktivasyon_kopya', { id: k.id }).then(function (j) { git('kurumlar'); setTimeout(function () { aktKodu(k.id, k.ad, j.kod); }, 300); }); }).catch(function (e) { bildir(e.message, 'hata'); });
    }
    function aktKodu(id, ad, kod) {
        var url = API + '?islem=aktivasyon_paketi_indir&id=' + id + '&_csrf=' + encodeURIComponent(csrf());
        var m = modal({ baslik: esc(ad) + ' — aktivasyon kodu', ikon: 'fa-key', gen: 540, govde:
            '<p class="ikm-soluk" style="margin-top:0">Kodu <b>yalnızca şimdi</b> görebilirsiniz; müşteriye ayrı bir kanaldan iletin. Kod <b>tek seferliktir</b>.</p>' +
            '<div style="display:flex;gap:8px;align-items:center;margin:10px 0 14px"><input readonly id="akt-kod" value="' + esc(kod) + '" onclick="this.select()" style="flex:1;font:700 20px ui-monospace,Consolas,monospace;letter-spacing:2px;text-align:center;padding:12px;border:1px solid var(--ik-cizgi);border-radius:12px;background:transparent;color:inherit"><button type="button" class="ikm-btn" id="akt-kopya"><i class="fa-regular fa-copy"></i> Kopyala</button></div>' +
            '<ol style="margin:0 0 10px;padding-left:20px;line-height:1.7"><li>“Paketi indir” ile dosyayı alın (<b>bir kez</b> indirilebilir).</li><li>Müşteri paketi sunucusuna açar, <b>kurulum.php</b>’de veri tabanı bilgilerini girer.</li><li>Açılan sayfada kodu yazar; kurum etkinleşir.</li></ol>' +
            '<div class="ikm-not"><i class="fa-solid fa-shield-halved"></i> Dosyalar değiştirilirse/silinirse ya da başka adrese kopyalanırsa ana sunucu doğrulamayı reddeder ve kurulum kilitlenir.</div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Kapat</button><a class="ikm-btn ikm-btn-ana" href="' + url + '" id="akt-indir"><i class="fa-solid fa-download"></i> Paketi indir</a>' });
        indir($('#akt-indir', m.el));
        $('#akt-kopya', m.el).onclick = function () { var i = $('#akt-kod', m.el); i.select(); try { navigator.clipboard.writeText(i.value); } catch (e) { document.execCommand('copy'); } bildir('Kod kopyalandı.', 'basari'); };
    }
    function aktModal(k) {
        var a = k.akt || {}, d = a.durum, tz = function (x) { return x ? tarih(x) : '—'; };
        var dur = { bekliyor: 'Kod bekliyor (paket henüz etkinleştirilmedi)', aktif: 'Aktif', kilit: 'Kilitli', iptal: 'İptal edildi' }[d] || d;
        var uy = a.uyari ? '<div class="ikm-not" style="border-color:#dc2626"><i class="fa-solid fa-triangle-exclamation" style="color:#dc2626"></i> <b>' + esc({ dosya: 'Dosyalar değiştirilmiş ya da silinmiş', kopya: 'Kurulumun kopyası algılandı', adres: 'Farklı adresten doğrulama', tekrar: 'Kod yeniden denendi' }[a.uyari] || a.uyari) + '</b>' + (a.uyari_ayrinti ? '<br><small>' + esc(a.uyari_ayrinti) + '</small>' : '') + '</div>' : '';
        var url = API + '?islem=aktivasyon_paketi_indir&id=' + k.id + '&_csrf=' + encodeURIComponent(csrf());
        var m = modal({ baslik: esc(k.ad) + ' — aktivasyon', ikon: 'fa-key', gen: 540, govde:
            '<div class="pf-meta" style="margin-bottom:12px"><div><span>Durum</span><b>' + esc(dur) + '</b></div><div><span>Kod</span><b>…' + esc(a.kod_son || '') + '</b></div><div><span>Bağlı adres</span><b>' + esc(a.host || '—') + '</b></div><div><span>Etkinleştirme</span><b>' + tz(a.etkin_zaman) + '</b></div><div><span>Son doğrulama</span><b>' + tz(a.son_dogrulama) + '</b></div></div>' + uy +
            (+k.paket_kodlu ? '<p class="ikm-soluk">Bu kurumun verisi müşterinin kendi veri tabanındadır; bu sunucuda kurum girişi kapalıdır. Güncelleme için yeni paket (yeni kod) üretmeniz gerekir.</p>' : '<p class="ikm-soluk">Bu paket, kurumun bir <b>kopyasıdır</b>; kurum bu sunucuda çalışmaya devam eder. Paket indirildiği andaki verileri taşır. Güncelleme için yeni paket (yeni kod) üretin.</p>'),
            alt: '<button type="button" class="ikm-btn" data-kapat>Kapat</button>' + (d !== 'iptal' ? '<button type="button" class="ikm-btn ikm-btn-tehlike" id="akt-iptal"><i class="fa-solid fa-ban"></i> İptal et</button>' : '') + '<button type="button" class="ikm-btn" id="akt-yeni"><i class="fa-solid fa-rotate"></i> Yeni kod üret</button>' + (d === 'bekliyor' ? '<a class="ikm-btn ikm-btn-ana" id="akt-indir2" href="' + url + '"><i class="fa-solid fa-download"></i> Paketi indir</a>' : '') });
        var ix = $('#akt-indir2', m.el); if (ix) indir(ix);
        var ib = $('#akt-iptal', m.el); if (ib) ib.onclick = function () { onayla({ baslik: 'Aktivasyon iptal edilsin mi?', mesaj: 'Müşterideki kurulum bir sonraki doğrulamada kilitlenir.', evet: 'İptal et', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('aktivasyon_iptal', { id: k.id }).then(function () { m.kapat(); bildir('İptal edildi.', 'basari'); git('kurumlar'); }); }).catch(function (e) { bildir(e.message, 'hata'); }); };
        $('#akt-yeni', m.el).onclick = function () { onayla({ baslik: 'Yeni kod üretilsin mi?', mesaj: 'Eski kod ve mevcut bağlantı sıfırlanır; müşteri yeni paket/kod ile yeniden kurulmalıdır.', evet: 'Yeni kod üret', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('aktivasyon_yenile', { id: k.id }).then(function (j) { m.kapat(); git('kurumlar'); setTimeout(function () { aktKodu(k.id, k.ad, j.kod); }, 300); }); }).catch(function (e) { bildir(e.message, 'hata'); }); };
    }
    function tasiPaketi(k) {
        var url = API + '?islem=kurum_paketi_indir&id=' + k.id + '&_csrf=' + encodeURIComponent(csrf());
        modal({ baslik: esc(k.ad) + ' — taşıma paketi', ikon: 'fa-box-archive', gen: 560, govde:
            '<p class="ikm-soluk" style="margin-top:0">Bu kurumu <b>başka bir sunucuya</b> taşımak için bağımsız bir paket hazırlanır: uygulama dosyaları + <b>yalnızca bu kuruma ait</b> veriler ve dosyalar (diğer kurumlar pakete girmez).</p>' +
            '<ol style="margin:0 0 12px;padding-left:20px;line-height:1.7"><li>Paketi indirin ve yeni sunucuda siteye ait klasöre açın.</li><li>Siteyi açın; yalnızca <b>veritabanı bilgilerini</b> girin.</li><li>“Kurumu kur” deyin — kurum girişi hazır. Platform yöneticisi oluşturmak gerekmez, kurum seçimi ve platform paneli görünmez.</li></ol>' +
            '<div class="ikm-not"><i class="fa-solid fa-circle-info"></i> Kullanıcı şifreleri, izinler, personel kartları, kurum logosu ve kullanım süresi pakettle birlikte gelir. Bu sunucudaki kurum silinmez; taşıma tamamlanınca isterseniz siz silersiniz.</div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Kapat</button><a class="ikm-btn ikm-btn-ana" href="' + url + '"><i class="fa-solid fa-download"></i> Paketi indir</a>' });
    }
    function durumDegistir(k) {
        var askiya = k.durum === 'aktif';
        onayla({ baslik: askiya ? 'Kurum askıya alınsın mı?' : 'Kurum etkinleştirilsin mi?', mesaj: askiya ? k.ad + ' kullanıcıları giriş yapamaz ve açık oturumları kapanır. Veriler silinmez.' : k.ad + ' kullanıcıları yeniden giriş yapabilecek.', evet: askiya ? 'Askıya al' : 'Etkinleştir', tur: askiya ? 'tehlike' : 'bilgi' })
            .then(function (ok) { if (ok) return gonder('kurum_durum', { id: k.id, durum: askiya ? 'askida' : 'aktif' }).then(function () { bildir(askiya ? 'Kurum askıya alındı.' : 'Kurum etkinleştirildi.', 'basari'); git('kurumlar'); }); }).catch(function (e) { bildir(e.message, 'hata'); });
    }
    function destekGirisi(k) {
        onayla({ baslik: 'Kurumu aç', mesaj: k.ad + ' kurumuna kurum yetkilisi olarak girilecek. Bu işlem kayıt altına alınır.', evet: 'Aç' })
            .then(function (ok) { if (ok) return gonder('kurum_gir', { kurum_id: k.id }).then(function (j) { location.href = j.git; }); }).catch(function (e) { bildir(e.message, 'hata'); });
    }
    var PF_MARKA = 'Turkuaz Bileşim';
    function kurumFormu(k) {
        var yeni = !k, v = k || { ad: '', kod: '', renk: '#6366f1', email: '', telefon: '', kullanici_siniri: 0, notlar: '', imza_goster: 1 };
        var m = modal({ baslik: yeni ? 'Yeni kurum' : 'Kurumu düzenle', ikon: 'fa-building', gen: 640, govde:
            '<form class="ikm-form" id="pf-kf" autocomplete="off"><div class="ikm-iki"><label class="ikm-alan"><span>Kurum adı <b>*</b></span><input name="ad" value="' + esc(v.ad) + '" required></label>' +
            '<label class="ikm-alan"><span>Kurum kodu <small>(giriş adresinde; sonradan değişmez)</small></span><input name="kod" value="' + esc(v.kod) + '" placeholder="otomatik" ' + (yeni ? '' : 'readonly') + ' autocapitalize="none"></label>' +
            '<label class="ikm-alan"><span>Marka rengi</span><input type="color" name="renk" value="' + esc(v.renk) + '" style="height:42px;padding:4px"></label>' +
            '<label class="ikm-alan"><span>Kullanıcı sınırı <small>(0 = sınırsız)</small></span><input type="number" min="0" name="kullanici_siniri" value="' + (+v.kullanici_siniri || 0) + '"></label>' +
            '<label class="ikm-alan"><span>E-posta</span><input type="email" name="email" value="' + esc(v.email) + '"></label><label class="ikm-alan"><span>Telefon</span><input name="telefon" value="' + esc(v.telefon) + '"></label>' +
            (yeni ? '<label class="ikm-alan"><span>Barındırma <small>(kurum nerede yayınlanacak?)</small></span><select name="barindirma"><option value="yerel">Bu sunucuda (kendi hostingimiz)</option><option value="uzak">Uzak sunucuda (vekil bağlantı dosyası oluşturulur)</option><option value="kod">Aktivasyon kodlu paket (müşterinin kendi sunucusu, tek seferlik)</option></select></label>' : '') +
            '<label class="ikm-alan"><span>Kullanım süresi' + (yeni ? '' : ' <small>(şimdi: ' + (v.lisans && v.lisans.durum !== 'sinirsiz' ? esc(v.lisans.bitis.split('-').reverse().join('.')) + ' tarihine kadar' : 'sınırsız') + ')</small>') + '</span><select name="lisans" id="pf-lisans">' + (yeni ? '' : '<option value="degistirme">Değiştirme</option>') + '<option value="sinirsiz">Sınırsız</option><option value="deneme">' + (yeni ? 'Deneme sürümü (15 gün)' : 'Deneme sürümü yap (bugünden 15 gün)') + '</option><option value="aylik">' + (yeni ? '1 aylık' : '+1 ay uzat / 1 aylık yap') + '</option><option value="yillik">' + (yeni ? '1 yıllık' : '+1 yıl uzat / 1 yıllık yap') + '</option><option value="ozel">Özel bitiş tarihi…</option></select></label>' +
            '<label class="ikm-alan" id="pf-lisans-t"><span>Bitiş tarihi <small>(tarihi değiştirirseniz “Özel bitiş tarihi” otomatik seçilir)</small></span><input type="date" name="lisans_bitis" id="pf-lisans-bt" value="' + esc(!yeni && v.lisans && v.lisans.durum !== 'sinirsiz' && v.lisans.bitis ? v.lisans.bitis : '') + '"></label></div>' +
            (yeni ? '<div class="ikm-kart" style="margin:0"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-user-shield"></i> Kurum yetkilisi</h3></div><p class="ikm-soluk" style="margin:0 0 10px">Kurumdaki <b>tüm yetkilere</b> sahip olur ve diğer kullanıcılara yetki verebilir.</p><div class="ikm-iki"><label class="ikm-alan"><span>Ad soyad <b>*</b></span><input name="yetkili_ad" required></label><label class="ikm-alan"><span>Kullanıcı adı <small>(boşsa önerilir)</small></span><input name="yetkili_kullanici" autocapitalize="none" placeholder="ad.soyad"></label></div><small class="ikm-soluk">Geçici şifre otomatik üretilir ve kayıttan sonra bir kez gösterilir. E-posta / telefon yukarıdaki alanlardan alınır.</small></div>' : '') +
            '<label style="display:flex;gap:10px;align-items:flex-start;margin:4px 0 12px;padding:0;border:0;box-shadow:none;background:none;text-align:left;cursor:pointer"><input type="checkbox" name="imza_goster" value="1" ' + (+v.imza_goster === 0 ? '' : 'checked') + ' style="margin-top:3px;width:auto;flex:none"><span style="text-align:left"><b>İmzayı göster</b> <small class="ikm-soluk">(açık: alt bilgide “Tüm hakları saklıdır, ' + esc(PF_MARKA) + '” · kapalı: “Tüm hakları saklıdır, ' + esc(v.ad || 'kurum adı') + '”; platform adı hiçbir yerde görünmez)</small></span></label>' +
            '<label class="ikm-alan"><span>Notlar <small>(yalnızca platform yöneticileri görür)</small></span><textarea name="notlar" rows="2">' + esc(v.notlar || '') + '</textarea></label></form>' +
            (yeni ? '' : '<div class="ikm-kart" style="margin-top:14px"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-image"></i> Kurum logosu</h3></div><div class="pf-gorsel-alan" style="border:0;padding:0"><div class="pf-onizleme" id="pf-klogo">' + (v.logo ? '<img src="' + esc(v.logo) + '" alt="">' : '<i class="fa-regular fa-image"></i>') + '</div><div class="pf-aksiyon"><label class="ikm-btn ikm-btn-kucuk"><i class="fa-solid fa-upload"></i> Yükle<input type="file" id="pf-klogo-dosya" accept="image/png,image/jpeg,image/webp,image/gif,image/svg+xml" hidden></label><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" id="pf-klogo-sil"><i class="fa-solid fa-trash"></i> Kaldır</button></div></div></div>' +
                '<div class="ikm-kart" style="margin-top:14px;border-color:color-mix(in srgb,var(--ik-kirmizi) 40%,var(--ik-cizgi))"><div class="ikm-kart-baslik"><h3 style="color:var(--ik-kirmizi)"><i class="fa-solid fa-triangle-exclamation"></i> Tehlikeli bölge</h3></div><div class="pf-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk" id="pf-onar"><i class="fa-solid fa-screwdriver-wrench"></i> Veri tablolarını onar</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" id="pf-sil"><i class="fa-solid fa-trash"></i> Kurumu kalıcı sil</button></div></div>'),
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="pf-kf-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        $('#pf-lisans-bt', m.el).oninput = function () { if (this.value) $('#pf-lisans', m.el).value = 'ozel'; };
        $('#pf-kf-kaydet', m.el).onclick = function () {
            var f = $('#pf-kf', m.el), d = {}; new FormData(f).forEach(function (val, key) { d[key] = val; }); d.imza_goster = f.elements.imza_goster && f.elements.imza_goster.checked ? 1 : 0; if (!yeni) d.id = k.id; else d.id = 0;
            var b = this; b.disabled = true;
            gonder('kurum_kaydet', d).then(function (j) { m.kapat(); bildir(yeni ? 'Kurum oluşturuldu.' : 'Kurum güncellendi.', 'basari'); git('kurumlar'); if (yeni) setTimeout(function () { bilgiKutusu('Kurum yetkilisi giriş bilgileri', j.kullanici_adi, j.sifre, j.kod, d.telefon); }, 300); if (yeni && d.barindirma === 'kod') setTimeout(function () { aktKodu(j.id, d.ad, j.akt_kod); }, 900); if (yeni && d.barindirma === 'uzak') setTimeout(function () { uzakSunucu({ id: j.id, ad: d.ad }); }, 900); })
                .catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); });
        };
        if (!yeni) {
            $('#pf-klogo-dosya', m.el).onchange = function () { var d = this.files[0]; if (!d) return; gonder('kurum_logo', { id: k.id }, d).then(function (j) { $('#pf-klogo', m.el).innerHTML = '<img src="' + esc(j.logo) + '?' + Date.now() + '" alt="">'; bildir('Logo yüklendi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            $('#pf-klogo-sil', m.el).onclick = function () { gonder('kurum_logo', { id: k.id, kaldir: 1 }).then(function () { $('#pf-klogo', m.el).innerHTML = '<i class="fa-regular fa-image"></i>'; bildir('Logo kaldırıldı.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            $('#pf-onar', m.el).onclick = function () { gonder('sema_onar', { kurum_id: k.id }).then(function () { bildir('Veri tabloları denetlendi / onarıldı.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            $('#pf-sil', m.el).onclick = function () {
                var m2 = modal({ baslik: 'Kurum kalıcı olarak silinsin mi?', ikon: 'fa-trash', gen: 460, govde: '<p class="ikm-soluk" style="margin-top:0"><b>' + esc(k.ad) + '</b> kurumunun tüm kullanıcıları ve verileri silinir; bu işlem geri alınamaz. Onaylamak için kurum kodunu yazın: <span class="pf-kod">' + esc(k.kod) + '</span></p><label class="ikm-alan"><span>Kurum kodu</span><input id="pf-sil-kod" autocomplete="off" autocapitalize="none"></label>', alt: '<button class="ikm-btn" data-kapat>Vazgeç</button><button class="ikm-btn ikm-btn-tehlike-dolu" id="pf-sil-ok"><i class="fa-solid fa-trash"></i> Kalıcı olarak sil</button>' });
                $('#pf-sil-ok', m2.el).onclick = function () { gonder('kurum_sil', { id: k.id, onay: $('#pf-sil-kod', m2.el).value }).then(function () { m2.kapat(); m.kapat(); bildir('Kurum silindi.', 'basari'); git('kurumlar'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            };
        }
    }
    function yetkiliFormu(k) {
        getir({ islem: 'kurum', id: k.id }).then(function (j) {
            var kul = j.kullanicilar.filter(function (u) { return +u.aktif === 1; });
            var m = modal({ baslik: 'Kurum yetkilisi — ' + k.ad, ikon: 'fa-user-shield', gen: 600, govde:
                '<p class="ikm-soluk" style="margin-top:0">Kurum yetkilisi, kurumdaki <b>tüm yetkilere</b> sahiptir ve diğer kullanıcılara yetki dağıtabilir. Mevcut bir kullanıcıyı seçin ya da yeni biri oluşturun.</p>' +
                '<div class="ikm-segment" id="pf-y-mod"><button type="button" class="aktif" data-k="mevcut">Mevcut kullanıcı</button><button type="button" data-k="yeni">Yeni kullanıcı</button></div>' +
                '<form class="ikm-form" id="pf-yf" style="margin-top:14px" autocomplete="off"><div id="pf-y-mevcut"><label class="ikm-alan"><span>Kullanıcı</span><select name="kullanici_id">' + kul.map(function (u) { return '<option value="' + u.id + '"' + (+u.id === +k.yetkili_id ? ' selected' : '') + '>' + esc(u.ad_soyad) + ' (@' + esc(u.kullanici_adi) + ')' + (u.rol === 'kurucu' ? ' — yetkili' : '') + '</option>'; }).join('') + '</select></label></div>' +
                '<div id="pf-y-yeni" hidden><div class="ikm-iki"><label class="ikm-alan"><span>Ad soyad <b>*</b></span><input name="yeni_ad"></label><label class="ikm-alan"><span>Kullanıcı adı</span><input name="yeni_kullanici" autocapitalize="none"></label><label class="ikm-alan"><span>E-posta</span><input type="email" name="yeni_email"></label><label class="ikm-alan"><span>Telefon</span><input name="yeni_telefon"></label></div></div>' +
                '<label class="ikm-anahtar-satir"><span><b>Önceki yetkiliyi normal yetkiliye düşür</b><small>Kapalıysa önceki yetkili de tam yetkili kalır.</small></span><span class="ikm-anahtar"><input type="checkbox" name="eskiyi_dusur" checked><i></i></span></label></form>' +
                (kul.length ? '<div class="ikm-kart" style="margin-top:14px"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-key"></i> Şifre sıfırla</h3></div><div class="pf-aksiyon"><select id="pf-sf-kul" class="ikm-btn" style="min-width:200px">' + kul.map(function (u) { return '<option value="' + u.id + '">' + esc(u.ad_soyad) + '</option>'; }).join('') + '</select><button type="button" class="ikm-btn ikm-btn-kucuk" id="pf-sf">Geçici şifre üret</button></div></div>' : ''),
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="pf-y-kaydet"><i class="fa-solid fa-check"></i> Yetkili ata</button>' });
            var mod = 'mevcut';
            $$('#pf-y-mod button', m.el).forEach(function (b) { b.onclick = function () { mod = b.dataset.k; $$('#pf-y-mod button', m.el).forEach(function (x) { x.classList.toggle('aktif', x === b); }); $('#pf-y-mevcut', m.el).hidden = mod !== 'mevcut'; $('#pf-y-yeni', m.el).hidden = mod !== 'yeni'; }; });
            $('#pf-y-kaydet', m.el).onclick = function () {
                var f = $('#pf-yf', m.el), d = { kurum_id: k.id, eskiyi_dusur: f.elements.eskiyi_dusur.checked ? '1' : '0' };
                if (mod === 'mevcut') d.kullanici_id = f.elements.kullanici_id.value; else { d.yeni_ad = f.elements.yeni_ad.value; d.yeni_kullanici = f.elements.yeni_kullanici.value; d.yeni_email = f.elements.yeni_email.value; d.yeni_telefon = f.elements.yeni_telefon.value; }
                var b = this; b.disabled = true;
                gonder('yetkili_ata', d).then(function (r) { m.kapat(); bildir('Kurum yetkilisi atandı.', 'basari'); git('kurumlar'); if (r.sifre) setTimeout(function () { bilgiKutusu('Yeni yetkili giriş bilgileri', r.kullanici_adi, r.sifre, k.kod, (d && d.yetkili_telefon) || k.telefon); }, 300); })
                    .catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); });
            };
            var sf = $('#pf-sf', m.el);
            if (sf) sf.onclick = function () {
                onayla({ baslik: 'Şifre sıfırlansın mı?', mesaj: 'Seçilen kullanıcının şifresi değişir; ilk girişte kendi şifresini belirlemesi istenir.', evet: 'Sıfırla' }).then(function (ok) {
                    if (ok) return gonder('sifre_sifirla', { kurum_id: k.id, kullanici_id: $('#pf-sf-kul', m.el).value }).then(function (r) { bilgiKutusu('Geçici şifre', r.kullanici_adi, r.sifre, k.kod, k.telefon); });
                }).catch(function (e) { bildir(e.message, 'hata'); });
            };
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }

    /* ======================= SİTE AYARLARI ======================= */
    var GRUP = { genel: ['Genel ayarlar', 'Ürün adı, slogan ve iletişim bilgileri.', 'ayar-genel'], gorsel: ['Logo ve favicon', 'Marka görselleri ve ana renk.', 'ayar-gorsel'], seo: ['SEO', 'Arama motorları ve paylaşım önizlemeleri.', 'ayar-seo'], giris: ['Giriş sayfası', 'Giriş ekranının metni, efekti ve kurum seçimi.', 'ayar-giris'], sistem: ['Sistem', 'Bakım modu ve güvenlik.', 'ayar-sistem'] };
    Object.keys(GRUP).forEach(function (g) { kayit(GRUP[g][2], GRUP[g][0], GRUP[g][1], function () { return ayarSayfasi(g); }); });
    var EFEKTLER = { aurora: ['fa-cloud', 'Aurora'], parcacik: ['fa-circle-nodes', 'Parçacık ağı'], dalga: ['fa-water', 'Dalga'], izgara: ['fa-border-all', 'Işıklı ızgara'], yok: ['fa-ban', 'Efektsiz'] };
    function ayarSayfasi(g) {
        return getir({ islem: 'ayarlar' }).then(function (j) {
            var liste = j.ayarlar.filter(function (a) { return a.grup === g; }), D = {};
            j.ayarlar.forEach(function (a) { D[a.anahtar] = a.deger; });
            var alan = function (a) {
                var t = a.tur, d = esc(a.deger), n = esc(a.anahtar);
                if (t === 'dosya') return '';
                if (t === 'uzun') return '<label class="ikm-alan tam"><span>' + esc(a.etiket) + '</span><textarea name="' + n + '" rows="' + (a.anahtar === 'ek_head' ? 5 : 3) + '">' + d + '</textarea></label>';
                if (t === 'renk') return '<label class="ikm-alan"><span>' + esc(a.etiket) + '</span><input type="color" name="' + n + '" value="' + d + '" style="height:42px;padding:4px"></label>';
                if (t === 'onay') return '<label class="ikm-anahtar-satir"><span><b>' + esc(a.etiket) + '</b></span><span class="ikm-anahtar"><input type="checkbox" name="' + n + '" value="1"' + (a.deger === '1' ? ' checked' : '') + '><i></i></span></label>';
                if (a.anahtar === 'giris_efekt') return '<div class="ikm-alan tam"><span>' + esc(a.etiket) + '</span><div class="pf-efekt-sec">' + Object.keys(EFEKTLER).map(function (e) { return '<label><input type="radio" name="giris_efekt" value="' + e + '"' + (a.deger === e ? ' checked' : '') + '><span><i class="fa-solid ' + EFEKTLER[e][0] + '"></i>' + EFEKTLER[e][1] + '</span></label>'; }).join('') + '</div></div>';
                if (a.anahtar === 'kurum_secimi') return '<label class="ikm-alan"><span>' + esc(a.etiket) + '</span><select name="kurum_secimi"><option value="otomatik"' + (a.deger === 'otomatik' ? ' selected' : '') + '>Otomatik — kullanıcı adı ya da e-postadan kurum bulunur (önerilen)</option><option value="liste"' + (a.deger === 'liste' ? ' selected' : '') + '>Liste — kurumlar kart olarak seçilir</option><option value="kod"' + (a.deger === 'kod' ? ' selected' : '') + '>Kod — kullanıcı kurum kodunu yazar (müşteri listesi gizli kalır)</option></select></label>';
                if (t.indexOf('secim:') === 0) return '<label class="ikm-alan"><span>' + esc(a.etiket) + '</span><select name="' + n + '">' + t.slice(6).split('|').map(function (o) { return '<option' + (a.deger === o ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select></label>';
                return '<label class="ikm-alan"><span>' + esc(a.etiket) + '</span><input name="' + n + '" value="' + d + '"></label>';
            };
            var h = '';
            if (g === 'gorsel') {
                var gorsel = function (ad, anahtar, ipucu) { var yol = D[anahtar]; return '<div class="pf-gorsel-alan"><div class="pf-onizleme" id="pf-g-' + anahtar + '">' + (yol ? '<img src="' + esc(yol) + '?' + Date.now() + '" alt="">' : '<i class="fa-regular fa-image"></i>') + '</div><div><b>' + ad + '</b><p class="ikm-soluk" style="margin:4px 0 10px">' + ipucu + '</p><div class="pf-aksiyon"><label class="ikm-btn ikm-btn-kucuk"><i class="fa-solid fa-upload"></i> Dosya seç<input type="file" data-yukle="' + anahtar + '" accept="' + (anahtar === 'favicon' ? 'image/png,image/x-icon,image/svg+xml,image/webp,.ico' : 'image/png,image/jpeg,image/webp,image/gif,image/svg+xml') + '" hidden></label><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-sil="' + anahtar + '"><i class="fa-solid fa-trash"></i> Kaldır</button></div></div></div>'; };
                h += '<div class="pf-ayar-form">' + gorsel('Logo', 'logo', 'Giriş sayfası ve panel menüsünde görünür. Şeffaf PNG / SVG önerilir (en fazla 2 MB).') + gorsel('Favicon', 'favicon', 'Tarayıcı sekmesi simgesi. Kare PNG (en az 64×64), ICO veya SVG (en fazla 512 KB).') + gorsel('Paylaşım görseli', 'og_gorsel', 'Bağlantı paylaşıldığında görünür (1200×630 önerilir).') + '</div>';
            }
            h += '<form class="pf-ayar-form" id="pf-af"><div class="ikm-kart"><div class="ikm-alanlar" style="grid-template-columns:repeat(auto-fit,minmax(260px,1fr))">' + liste.map(alan).join('') + '</div></div>' +
                (g === 'seo' ? '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-brands fa-google"></i> Arama sonucu önizlemesi</h3></div><div class="pf-seo-onizleme"><div class="u" id="pf-so-u"></div><div class="b" id="pf-so-b"></div><div class="a" id="pf-so-a"></div></div></div>' : '') +
                '<div><button type="submit" class="ikm-btn ikm-btn-ana"><i class="fa-solid fa-check"></i> Kaydet</button></div></form>';
            if (g === 'gorsel') h = h.replace('<form class="pf-ayar-form" id="pf-af">', '<form class="pf-ayar-form" id="pf-af" style="margin-top:16px">');
            icerik.innerHTML = h;
            // yükleme / kaldırma
            $$('[data-yukle]', icerik).forEach(function (inp) { inp.onchange = function () { var d = inp.files[0]; if (!d) return; gonder('dosya_yukle', { anahtar: inp.dataset.yukle }, d).then(function (r) { $('#pf-g-' + inp.dataset.yukle).innerHTML = '<img src="' + esc(r.yol) + '?' + Date.now() + '" alt="">'; bildir('Dosya yüklendi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); inp.value = ''; }; });
            $$('[data-sil]', icerik).forEach(function (b) { b.onclick = function () { gonder('dosya_sil', { anahtar: b.dataset.sil }).then(function () { $('#pf-g-' + b.dataset.sil).innerHTML = '<i class="fa-regular fa-image"></i>'; bildir('Kaldırıldı.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
            // SEO önizleme
            if (g === 'seo') {
                var f0 = $('#pf-af'), gunc = function () { var u = D.site_url || (location.origin + location.pathname.replace(/[^/]*$/, '')); $('#pf-so-u').textContent = (f0.elements.seo_canonical.value || u.replace(/\/$/, '') + '/giris.php'); $('#pf-so-b').textContent = (f0.elements.seo_baslik.value || D.site_adi || '').slice(0, 65); $('#pf-so-a').textContent = (f0.elements.seo_aciklama.value || '').slice(0, 160); };
                f0.addEventListener('input', gunc); gunc();
            }
            $('#pf-af').addEventListener('submit', function (e) {
                e.preventDefault(); var f = e.target, d = {};
                $$('input,select,textarea', f).forEach(function (el) { if (!el.name) return; if (el.type === 'checkbox') d[el.name] = el.checked ? '1' : '0'; else if (el.type === 'radio') { if (el.checked) d[el.name] = el.value; } else d[el.name] = el.value; });
                gonder('ayar_kaydet', d).then(function () { bildir('Ayarlar kaydedildi.', 'basari'); }).catch(function (er) { bildir(er.message, 'hata'); });
            });
            if (g === 'giris') $('#pf-ust-sag').innerHTML = '<a class="ikm-btn" href="giris.php?degistir=1" target="_blank" rel="noopener"><i class="fa-solid fa-eye"></i> <span>Giriş sayfasını gör</span></a>';
        });
    }

    /* ======================= KURUM YEDEKLERİ ======================= */
    function ydCtx(kid, ad) {
        return {
            liste: function () { return getir({ islem: 'yedek_liste', kurum_id: kid }); },
            cagir: function (islem, veri, dosya) { veri = veri || {}; veri.kurum_id = kid; return gonder(islem, veri, dosya); },
            indir: function (id) { return API + '?islem=yedek_dosya&id=' + id + '&_csrf=' + encodeURIComponent(csrf()); },
            bildir: bildir, onayla: onayla, modal: modal, kurum: ad
        };
    }
    kayit('lisansmesaj', 'Süre mesajları', 'Kullanım süresi azalan veya biten kurumlara otomatik SMS ve e-posta.', function () {
        return getir({ islem: 'lm_ayar' }).then(function (j) {
            var A = j.ayar, hz = j.hazir;
            var gunler = String(A.lm_gunler).split(',').filter(Boolean);
            function sw(id, acik, ad, alt) { return '<label class="pf-sw"><span class="ikm-anahtar"><input type="checkbox" id="' + id + '"' + (acik === '1' ? ' checked' : '') + '><i></i></span><span><b>' + ad + '</b><small>' + alt + '</small></span></label>'; }
            function chip(ok, y, n) { return '<span class="ikm-cip ' + (ok ? 'yesil' : 'turuncu') + '"><i class="fa-solid ' + (ok ? 'fa-check' : 'fa-xmark') + '"></i> ' + (ok ? y : n) + '</span>'; }
            var log = j.log.length ? '<div class="pf-tablo-sar"><table class="ikm-tablo"><thead><tr><th>Zaman</th><th>Kurum</th><th>Tür</th><th>Kanal</th><th>Alıcı</th><th>Sonuç</th></tr></thead><tbody>' + j.log.map(function (r) {
                var tur = r.tur === 'bitti' ? 'Süre bitti' : (r.tur === 'toplu' ? 'Toplu' : (r.tur.charAt(0) === 'k' ? r.tur.slice(1) + ' gün kala' : r.tur));
                return '<tr><td data-e="Zaman">' + tarih(r.zaman) + '</td><td data-e="Kurum">' + esc(r.kurum || '—') + '</td><td data-e="Tür">' + esc(tur) + '</td><td data-e="Kanal">' + (r.kanal === 'sms' ? 'SMS' : 'E-posta') + '</td><td data-e="Alıcı">' + esc(r.alici) + '</td><td data-e="Sonuç"><span class="ikm-cip ' + (+r.ok ? 'yesil' : 'kirmizi') + '">' + (+r.ok ? 'Gönderildi' : 'Hata') + '</span>' + (+r.ok ? '' : ' <small>' + esc(r.sonuc) + '</small>') + '</td></tr>';
            }).join('') + '</tbody></table></div>' : bosKutu('fa-paper-plane', 'Henüz mesaj gönderilmedi', 'Otomatik veya toplu gönderilen her mesaj burada listelenir.');
            icerik.innerHTML =
                '<div class="pf-lm-ust ' + (A.lm_aktif === '1' ? 'acik' : '') + '"><div>' + sw('lm-aktif', A.lm_aktif, 'Otomatik süre mesajları', 'Açıkken süresi azalan ve biten kurumlara kendiliğinden gönderilir.') + '</div><button type="button" class="ikm-btn ikm-btn-ana" id="lm-tara"><i class="fa-solid fa-bolt"></i> Şimdi tara ve gönder</button></div>' +
                '<div class="ikm-kart pf-lm-kart"><h3><i class="fa-solid fa-tower-broadcast"></i> Kanal ve gönderen kurum</h3><p class="pf-ipucu">SMS ve e-posta, seçtiğiniz kurumun “Bildirim kanalları” ayarları (SMS sağlayıcı / SMTP) kullanılarak gönderilir.</p>' +
                '<div class="pf-lm-izgara"><label class="ikm-alan"><span>Gönderen kurum</span><select id="lm-kurum"><option value="0">— Seçin —</option>' + j.kurumlar.map(function (k) { return '<option value="' + k.id + '"' + (+A.lm_kurum === k.id ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select></label>' +
                '<div class="pf-lm-hazir">' + chip(hz.kurum, 'Kurum seçili', 'Kurum seçilmedi') + chip(hz.sms, 'SMS hazır', 'SMS ayarı yok') + chip(hz.mail, 'E-posta hazır', 'E-posta ayarı yok') + '</div></div>' +
                sw('lm-sms', A.lm_sms, 'SMS gönder', 'Kurum telefonuna (boşsa kurucu telefonuna)') + sw('lm-mail', A.lm_mail, 'E-posta gönder', 'Kurum e-postasına (boşsa kurucu e-postasına)') + '</div>' +
                '<div class="ikm-kart pf-lm-kart"><h3><i class="fa-solid fa-calendar-day"></i> Ne zaman gönderilsin?</h3><p class="pf-ipucu">Kalan gün bu sayılara düştüğünde bir kez gönderilir. Virgülle ayırın (örn. 10, 3, 1).</p>' +
                '<label class="ikm-alan"><span>Kaç gün kala</span><input id="lm-gunler" value="' + esc(gunler.join(', ')) + '" inputmode="numeric" placeholder="10, 3"></label>' +
                sw('lm-bitti', A.lm_bitti, 'Süre bitince de gönder', 'Süre bittiği gün (en geç 30 gün içinde) bir kez') +
                '<div class="pf-zaman" id="lm-zaman"></div></div>' +
                '<div class="ikm-kart pf-lm-kart"><h3><i class="fa-solid fa-hourglass-half"></i> “Süre bitiyor” metni</h3>' +
                '<label class="ikm-alan"><span>SMS</span><textarea id="lm-sms-k" rows="3">' + esc(A.lm_sms_kalan) + '</textarea></label><label class="ikm-alan"><span>E-posta konusu</span><input id="lm-mk" value="' + esc(A.lm_mail_kalan_konu) + '"></label><label class="ikm-alan"><span>E-posta metni</span><textarea id="lm-mm" rows="5">' + esc(A.lm_mail_kalan) + '</textarea></label></div>' +
                '<div class="ikm-kart pf-lm-kart"><h3><i class="fa-solid fa-ban"></i> “Süre bitti” metni</h3>' +
                '<label class="ikm-alan"><span>SMS</span><textarea id="lm-sms-b" rows="3">' + esc(A.lm_sms_bitti) + '</textarea></label><label class="ikm-alan"><span>E-posta konusu</span><input id="lm-bk" value="' + esc(A.lm_mail_bitti_konu) + '"></label><label class="ikm-alan"><span>E-posta metni</span><textarea id="lm-bm" rows="5">' + esc(A.lm_mail_bitti) + '</textarea></label>' +
                degiskenSatiri() + '</div>' +
                '<div class="ikm-kart pf-lm-kart"><h3><i class="fa-solid fa-clock"></i> Çalışma şekli</h3><p class="pf-ipucu">Sistem, panel veya kurum girişleri açıldığında saatte en fazla bir kez tarar. Siteye kimse girmese de çalışması için sunucuda zamanlanmış görev ekleyin:</p><div class="pf-kod"><code id="lm-cron">0 * * * * ' + esc(j.cron) + '</code><button type="button" class="ikm-btn ikm-btn-kucuk" id="lm-kopya"><i class="fa-regular fa-copy"></i> Kopyala</button></div></div>' +
                '<div class="pf-kaydet-bar"><button type="button" class="ikm-btn ikm-btn-ana" id="lm-kaydet"><i class="fa-solid fa-floppy-disk"></i> Kaydet</button></div>' +
                '<div class="ikm-kart pf-lm-kart"><h3><i class="fa-solid fa-list-check"></i> Son gönderimler</h3>' + log + '</div>';
            var son = $('#lm-sms-k');
            $$('.pf-degisken [data-dg]', icerik).forEach(function (b) { b.onclick = function () { var t = document.activeElement && /^(TEXTAREA|INPUT)$/.test(document.activeElement.tagName) ? document.activeElement : $('#lm-mm'); var p = t.selectionStart || t.value.length; t.value = t.value.slice(0, p) + b.dataset.dg + t.value.slice(t.selectionEnd || p); t.focus(); }; });
            function gunListe() { return $('#lm-gunler').value.split(/[,\s;]+/).map(Number).filter(function (x) { return !isNaN(x) && x >= 0 && x <= 90 && x !== ''; }).filter(function (x, i, a) { return a.indexOf(x) === i; }).sort(function (a, b) { return b - a; }); }
            function zaman() {
                var g = gunListe(), h = '<div class="pf-z-uc">Süre başlangıcı</div>';
                g.forEach(function (x) { h += '<div class="pf-z-nokta"><i></i><span><b>' + (x === 0 ? 'Bitiş günü' : x + ' gün kala') + '</b><small>Süre bitiyor mesajı</small></span></div>'; });
                if ($('#lm-bitti').checked) h += '<div class="pf-z-nokta bitti"><i></i><span><b>Süre bitince</b><small>Süre bitti mesajı</small></span></div>';
                $('#lm-zaman').innerHTML = h;
            }
            $('#lm-gunler').oninput = zaman; $('#lm-bitti').onchange = zaman; zaman();
            $('#lm-kopya').onclick = function () { kopyala($('#lm-cron').textContent); };
            function topla() { return { lm_aktif: $('#lm-aktif').checked ? '1' : '0', lm_sms: $('#lm-sms').checked ? '1' : '0', lm_mail: $('#lm-mail').checked ? '1' : '0', lm_bitti: $('#lm-bitti').checked ? '1' : '0', lm_kurum: $('#lm-kurum').value, lm_gunler: gunListe().join(','), lm_sms_kalan: $('#lm-sms-k').value, lm_mail_kalan_konu: $('#lm-mk').value, lm_mail_kalan: $('#lm-mm').value, lm_sms_bitti: $('#lm-sms-b').value, lm_mail_bitti_konu: $('#lm-bk').value, lm_mail_bitti: $('#lm-bm').value }; }
            $('#lm-kaydet').onclick = function () { var b = this; b.disabled = true; gonder('lm_kaydet', topla()).then(function () { bildir('Ayarlar kaydedildi.', 'basari'); git('lisansmesaj'); }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); }); };
            $('#lm-tara').onclick = function () {
                var b = this; b.disabled = true;
                gonder('lm_kaydet', topla()).then(function () { return gonder('lm_tara'); }).then(function (r) { bildir(r.gonderilen ? r.gonderilen + ' mesaj gönderildi.' : 'Gönderilecek yeni mesaj yok.', r.gonderilen ? 'basari' : 'bilgi'); git('lisansmesaj'); }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); });
            };
        });
    });
    kayit('yedekler', 'Kurum yedekleri', 'Tüm kurumların otomatik yedekleri, geri yükleme ve talepler.', function () {
        return getir({ islem: 'yedek_durum' }).then(function (j) {
            var Y = window.TBYedek, A = j.ayar, gun = +A.aralik, bekleyen = j.talepler.filter(function (t) { return t.durum === 'bekliyor'; });
            var rz = $('#pf-r-yedek'); if (rz) { rz.hidden = !bekleyen.length; rz.textContent = bekleyen.length; }
            var gec = function (son) { if (!son || gun <= 0) return false; return (Date.now() - new Date(son.replace(' ', 'T')).getTime()) > gun * 2 * 86400000; };
            var aralikSec = [[0, 'Kapalı'], [1, 'Her gün'], [2, '2 günde bir'], [3, '3 günde bir'], [5, '5 günde bir'], [7, 'Haftalık'], [10, '10 günde bir'], [14, '2 haftada bir'], [30, 'Aylık']];
            icerik.innerHTML =
                (j.zip ? '' : '<div class="ikm-uyari"><i class="fa-solid fa-triangle-exclamation"></i> PHP zip eklentisi kurulu değil; yedekleme çalışmaz.</div>') +
                '<div class="ikm-kpiler"><div class="ikm-kpi mavi"><span class="ikm-kpi-ikon"><i class="fa-solid fa-clock-rotate-left"></i></span><div><b>' + (gun > 0 ? gun + ' günde bir' : 'Kapalı') + '</b><span>Otomatik yedekleme</span></div></div>' +
                '<div class="ikm-kpi ' + (bekleyen.length ? 'turuncu' : 'yesil') + '"><span class="ikm-kpi-ikon"><i class="fa-solid fa-envelope-open-text"></i></span><div><b>' + bekleyen.length + '</b><span>Bekleyen geri yükleme talebi</span></div></div></div>' +
                (bekleyen.length || j.talepler.length ? '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-envelope-open-text"></i> Geri yükleme talepleri</h3></div><div class="ikm-liste">' + j.talepler.slice(0, 8).map(function (t) {
                    var d = Y.TALEP[t.durum] || Y.TALEP.bekliyor;
                    return '<div class="ikm-satir"><span class="ikm-satir-ikon"><i class="fa-solid fa-life-ring"></i></span><span class="ikm-satir-ic"><b>' + esc(t.kurum || '—') + ' · ' + esc(t.kullanici_ad) + '</b><small>' + tarih(t.olusturma) + (t.yedek_tarih ? ' · istenen yedek: ' + tarih(t.yedek_tarih) : '') + (t.mesaj ? ' · ' + esc(t.mesaj) : '') + (t.yanit ? ' · Yanıt: ' + esc(t.yanit) : '') + '</small></span>' + Y.cip(d[1], d[0]) +
                        (t.durum === 'bekliyor' ? '<span class="yd-eylem"><button type="button" class="ikm-btn ikm-btn-kucuk" data-ac="' + t.kurum_id + '">Yedekleri aç</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-yanit="' + t.id + '|tamamlandi">Tamamlandı</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-yanit="' + t.id + '|reddedildi">Reddet</button></span>' : '') + '</div>';
                }).join('') + '</div></div>' : '') +
                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-building"></i> Kurumlar ve son yedekler</h3><div class="yd-araclar"><button type="button" class="ikm-btn ikm-btn-kucuk" id="yd-yeni-kurum"><i class="fa-solid fa-file-import"></i> Yedekten kurum oluştur</button><button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" id="yd-hepsi"><i class="fa-solid fa-cloud-arrow-down"></i> Hepsini şimdi yedekle</button></div></div><div class="ikm-liste">' +
                (j.kurumlar.length ? j.kurumlar.map(function (k) {
                    return '<button type="button" class="ikm-satir" data-ac="' + k.id + '" data-ad="' + esc(k.ad) + '"><span class="ikm-satir-ikon"><i class="fa-solid fa-building"></i></span><span class="ikm-satir-ic"><b>' + esc(k.ad) + '</b><small>' + (k.son ? 'Son yedek: ' + Y.tarihSaat(k.son) + ' · ' + Y.once(k.son) : 'Henüz yedeklenmedi') + ' · ' + k.adet + ' yedek · ' + Y.boyut(k.boyut) + '</small></span>' +
                        (!k.son ? Y.cip('gri', 'Yedek yok') : gec(k.son) ? Y.cip('turuncu', 'Gecikmiş', 'fa-triangle-exclamation') : Y.cip('yesil', 'Güncel', 'fa-check')) + '<i class="fa-solid fa-chevron-right" style="opacity:.4"></i></button>';
                }).join('') : bosKutu('fa-building', 'Henüz kurum yok')) + '</div></div>' +
                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-sliders"></i> Otomatik yedekleme ve iletişim</h3></div><form id="yd-ayar" class="yd-form">' +
                '<label class="ikm-alan"><span>Yedekleme sıklığı</span><select name="aralik">' + aralikSec.map(function (a) { return '<option value="' + a[0] + '"' + (+A.aralik === a[0] ? ' selected' : '') + '>' + a[1] + '</option>'; }).join('') + '</select></label>' +
                '<label class="ikm-alan"><span>Kurum başına saklanacak otomatik yedek</span><input name="saklama" type="number" min="1" max="60" value="' + esc(A.saklama) + '"></label>' +
                '<label class="ikm-alan"><span>Talep bildirimi e-postası</span><input name="eposta" type="email" value="' + esc(A.eposta) + '" placeholder="ornek@firma.com"></label>' +
                '<label class="ikm-alan"><span>Talep bildirimi SMS telefonu</span><input name="telefon" value="' + esc(A.telefon) + '" placeholder="05xx xxx xx xx"></label>' +
                '<div class="tam"><button type="submit" class="ikm-btn ikm-btn-ana"><i class="fa-solid fa-check"></i> Kaydet</button> <small class="ikm-soluk">Yedekler arka planda alınır; kullanıcılara bildirim gitmez. Düzenli çalışması için panele ya da kurumlara girişler yeterlidir; isterseniz sunucuda <code>php kurum_yedek.php</code> komutunu cron ile de çalıştırabilirsiniz. SMS, talebin geldiği kurumun SMS ayarlarıyla gider.</small></div></form></div>';
            $$('[data-ac]', icerik).forEach(function (b) {
                b.onclick = function () {
                    var kid = +b.dataset.ac, ad = b.dataset.ad || (j.kurumlar.filter(function (k) { return k.id === kid; })[0] || {}).ad || 'Kurum';
                    var m = modal({ baslik: ad + ' — yedekler', ikon: 'fa-box-archive', gen: 820, govde: '<div id="yd-kok"></div>' });
                    var p = Y.panel(m.el.querySelector('#yd-kok'), ydCtx(kid, ad));
                    var mo = new MutationObserver(function () { if (!document.body.contains(m.el)) { mo.disconnect(); p.durdur(); git('yedekler', true); } }); mo.observe(document.body, { childList: true });
                };
            });
            $$('[data-yanit]', icerik).forEach(function (b) {
                b.onclick = function (e) {
                    e.stopPropagation(); var p = b.dataset.yanit.split('|');
                    var mm = modal({ baslik: p[1] === 'tamamlandi' ? 'Talep tamamlandı' : 'Talep reddedilsin', ikon: 'fa-envelope', gen: 460, govde: '<label class="ikm-alan"><span>Kuruma not (isteğe bağlı)</span><textarea id="yd-yn" rows="3"></textarea></label>', alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="yd-yg">Kaydet</button>' });
                    mm.el.querySelector('#yd-yg').onclick = function () { gonder('talep_yanit', { id: p[0], durum: p[1], yanit: mm.el.querySelector('#yd-yn').value }).then(function () { mm.kapat(); bildir('Talep güncellendi.', 'basari'); git('yedekler', true); }).catch(function (er) { bildir(er.message, 'hata'); }); };
                };
            });
            $('#yd-hepsi').onclick = function () {
                var b = this; onayla({ baslik: 'Tüm kurumlar yedeklensin mi?', mesaj: 'Her kurumun şu anki hali ayrı bir yedek olarak kaydedilir.', evet: 'Yedekle' }).then(function (ok) {
                    if (!ok) return; b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Yedekleniyor…';
                    return gonder('yedek_hepsi', {}).then(function (r) { bildir(r.n + ' kurum yedeklendi' + (r.hata && r.hata.length ? ' (' + r.hata.length + ' hata)' : '') + '.', r.hata && r.hata.length ? 'uyari' : 'basari'); git('yedekler', true); });
                }).catch(function (e) { bildir(e.message, 'hata'); b.disabled = false; });
            };
            $('#yd-yeni-kurum').onclick = function () {
                var m = modal({ baslik: 'Yedekten kurum oluştur', ikon: 'fa-file-import', gen: 520, govde: '<p class="yd-not">Daha önce indirilen bir kurum yedeğini (.zip) seçin; kurum, tüm verileri ve dosyalarıyla yeniden kurulur. Yedeğin sürümünüzden eski olması sorun değildir.</p>' +
                    '<label class="ikm-alan"><span>Yedek dosyası (.zip) · en fazla ' + esc(j.limit) + '</span><input type="file" id="yd-kz" accept=".zip"></label><label class="ikm-alan"><span>Kurum adı (boşsa yedektekiyle aynı)</span><input id="yd-ka"></label><label class="ikm-alan"><span>Kurum kodu (boşsa yedektekiyle aynı)</span><input id="yd-kk"></label>',
                    alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="yd-kg">Kurumu oluştur</button>' });
                m.el.querySelector('#yd-kg').onclick = function () {
                    var d = m.el.querySelector('#yd-kz').files[0], b = this; if (!d) { bildir('Önce bir .zip dosyası seçin.', 'uyari'); return; }
                    b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Kuruluyor…';
                    gonder('yedek_kurum_olustur', { ad: m.el.querySelector('#yd-ka').value, kod: m.el.querySelector('#yd-kk').value }, d).then(function (r) { m.kapat(); bildir('“' + r.ad + '” kurumu yedekten oluşturuldu.', 'basari'); git('yedekler', true); }).catch(function (e) { bildir(e.message, 'hata'); b.disabled = false; b.textContent = 'Kurumu oluştur'; });
                };
            };
            $('#yd-ayar').addEventListener('submit', function (e) {
                e.preventDefault(); var d = {}; $$('input,select', e.target).forEach(function (x) { d[x.name] = x.value; });
                gonder('yedek_ayar_kaydet', d).then(function () { bildir('Yedekleme ayarları kaydedildi.', 'basari'); git('yedekler', true); }).catch(function (er) { bildir(er.message, 'hata'); });
            });
        });
    });
    // menü rozeti: bekleyen talep sayısı
    setTimeout(function () { getir({ islem: 'yedek_durum' }).then(function (j) { var n = j.talepler.filter(function (t) { return t.durum === 'bekliyor'; }).length, rz = $('#pf-r-yedek'); if (rz) { rz.hidden = !n; rz.textContent = n; } }).catch(function () {}); }, 600);

    /* ======================= YEDEK VE GÜNCELLEME ======================= */
    function boyut(b) { b = +b || 0; return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    kayit('bakim', 'Yedek ve güncelleme', 'Verileri başka sunucuya taşıyın, yedek alın, yeni sürümü diğer kurulumlara aktarın.', function () {
        return getir({ islem: 'bakim_durum' }).then(function (j) {
            var dl = function (i, ek) { return API + '?islem=' + i + '&_csrf=' + encodeURIComponent(csrf()) + (ek || ''); };
            var uyari = (j.zip ? '' : '<div class="ikm-uyari"><i class="fa-solid fa-triangle-exclamation"></i> PHP zip eklentisi kurulu değil; yedek ve güncelleme çalışmaz.</div>') + (j.yazilabilir ? '' : '<div class="ikm-uyari"><i class="fa-solid fa-triangle-exclamation"></i> Uygulama klasörü yazılabilir değil; güncelleme dosya yazamaz.</div>');
            function ust(ik, renk, baslik, alt) { return '<div class="pf-bk-ust"><span class="pf-bk-ik" style="--c:' + renk + '"><i class="fa-solid ' + ik + '"></i></span><div><h3>' + baslik + '</h3>' + (alt ? '<p>' + alt + '</p>' : '') + '</div></div>'; }
            function dosyaKutu(id) { return '<label class="pf-dk"><input type="file" id="' + id + '" accept=".zip"><span class="pf-dk-ik"><i class="fa-solid fa-cloud-arrow-up"></i></span><span class="pf-dk-y"><b>Zip dosyası seçin veya sürükleyin</b><small>henüz dosya seçilmedi</small></span></label>'; }
            function kopyaSatir(ad, v) { return '<div class="pf-bk-kopya"><span>' + ad + '</span><code>' + esc(v) + '</code><button type="button" class="ikm-btn ikm-btn-kucuk" data-kp="' + esc(v) + '"><i class="fa-regular fa-copy"></i></button></div>'; }
            var mig = j.migrasyonlar.length ? j.migrasyonlar.map(function (m) { return '<span class="ikm-cip">' + esc(m.ad) + '</span>'; }).join(' ') : '<span class="pf-bk-yok">yok</span>';
            var yed = j.yedekler.length ? j.yedekler.map(function (y) { return '<span class="ikm-cip">' + esc(y.ad) + ' · ' + boyut(y.boyut) + '</span>'; }).join(' ') : '<span class="pf-bk-yok">yok</span>';
            icerik.innerHTML = uyari +
                '<div class="pf-bk-surum"><span class="pf-bk-ik buyuk" style="--c:#14b8a6"><i class="fa-solid fa-code-branch"></i></span><div><small>Kurulu sürüm</small><b>' + esc((j.surum && j.surum.surum) || '?') + '</b></div><div><small>Sürüm tarihi</small><b>' + esc((j.surum && j.surum.tarih) || '—') + '</b></div></div>' +
                '<div class="pf-bk-izgara">' +
                '<section class="ikm-kart pf-bk-kart">' + ust('fa-box-archive', '#0ea5e9', 'Yedek al / başka sunucuya taşı', 'Tüm kurumların verisi ve yüklenen dosyalar tek .zip olarak iner.') +
                '<p class="pf-bk-yazi">Yeni sunucuda kurulum sihirbazındaki <b>“Başka sunucudan taşı”</b> bölümüne bu dosyayı yükleyin (önce uygulama dosyalarını da o sunucuya kopyalayın).</p><div class="pf-bk-dugme"><a class="ikm-btn ikm-btn-ana" href="' + dl('yedek_indir') + '"><i class="fa-solid fa-download"></i> Tam yedek indir</a><a class="ikm-btn" href="' + dl('yedek_indir', '&dosyasiz=1') + '"><i class="fa-solid fa-database"></i> Yalnızca veritabanı</a></div></section>' +
                '<section class="ikm-kart pf-bk-kart pf-bk-tehlike">' + ust('fa-rotate-left', '#f43f5e', 'Yedekten geri yükle', 'Mevcut tüm veriler yedektekilerle değiştirilir.') +
                '<div class="ikm-uyari"><i class="fa-solid fa-triangle-exclamation"></i> Bu işlem geri alınamaz. Önce güncel yedek almanız önerilir.</div>' + dosyaKutu('pf-yg-d') +
                '<div class="pf-bk-onay"><input type="text" id="pf-yg-o" placeholder="Onay için GERİ YÜKLE yazın" autocomplete="off"><button type="button" class="ikm-btn ikm-btn-tehlike" id="pf-yg"><i class="fa-solid fa-upload"></i> Geri yükle</button></div></section></div>' +
                '<section class="ikm-kart pf-bk-kart">' + ust('fa-cloud-arrow-up', '#8b5cf6', 'Güncelleme', 'Yeni sürümü paketle ya da kaynaktan çekerek uygulayın. Ayarlarınız, yüklenen dosyalar ve veriler korunur.') +
                '<div class="pf-bk-iki"><div class="pf-bk-adim"><span class="pf-bk-no">1</span><div><h4>Paketi elle taşıyın</h4><p>Bu kurulumun kodundan paket üretin, diğer kurulumda yükleyin. Ezilen dosyalar <code>storage/yedek_*.zip</code> içine yedeklenir.</p></div></div>' +
                '<div class="pf-bk-adim"><span class="pf-bk-no">2</span><div><h4>Doğrudan kaynaktan çekin</h4><p>Güncellemesini yaptığınız ana kurulumun adresi ve anahtarıyla tek tıkla güncelleyin.</p></div></div></div>' +
                '<div class="pf-bk-iki"><div class="pf-bk-alan"><a class="ikm-btn" href="' + dl('paket_indir') + '"><i class="fa-solid fa-file-zipper"></i> Güncelleme paketi indir</a>' + dosyaKutu('pf-gp-d') + '<button type="button" class="ikm-btn ikm-btn-ana" id="pf-gp"><i class="fa-solid fa-circle-up"></i> Paketi uygula</button></div>' +
                '<div class="pf-bk-alan"><div class="pf-bk-kutu">' + kopyaSatir('Bu kurulumun kaynak adresi', j.kaynak_adresi) + kopyaSatir('Bu kurulumun anahtarı', j.anahtar) + '<button type="button" class="ikm-btn ikm-btn-kucuk" id="pf-an-y"><i class="fa-solid fa-arrows-rotate"></i> Anahtarı yenile</button></div>' +
                '<label class="ikm-alan"><span>Çekilecek kaynak adresi</span><input type="text" id="pf-ks-u" value="' + esc(j.kaynak) + '" placeholder="https://ana-site.com/platform_islem.php?islem=paket_kaynak"></label><label class="ikm-alan"><span>Kaynak anahtarı</span><input type="text" id="pf-ks-k" value="' + esc(j.kaynak_anahtar) + '"></label>' +
                '<div class="pf-bk-dugme"><button type="button" class="ikm-btn" id="pf-ks"><i class="fa-solid fa-floppy-disk"></i> Kaydet</button><button type="button" class="ikm-btn ikm-btn-ana" id="pf-ug"' + (j.curl ? '' : ' disabled title="curl eklentisi gerekli"') + '><i class="fa-solid fa-cloud-arrow-down"></i> Kaynaktan güncelle</button></div></div>' +
                '<div class="pf-bk-kutu" style="margin-top:14px"><h4 style="margin:0 0 6px">Kurum verisi çek</h4><p class="ikm-soluk" style="margin:0 0 10px">Yukarıdaki kaynak sunucudan seçtiğiniz kurumun <b>verisini</b> hedef veri tabanına yazar. Hedefte o kurumun verisi varsa <b>yenisiyle değiştirilir</b> (güncellenir); diğer kurumlara dokunulmaz.</p>' +
                '<div class="ikm-iki"><label class="ikm-alan"><span>Hedef veri tabanı adı <small>(boşsa bu kurulumun veri tabanı)</small></span><input type="text" id="pf-hd-ad" autocomplete="off" autocapitalize="none"></label><label class="ikm-alan"><span>Sunucu</span><input type="text" id="pf-hd-host" value="localhost" autocomplete="off"></label>' +
                '<label class="ikm-alan"><span>Kullanıcı</span><input type="text" id="pf-hd-kul" autocomplete="off" autocapitalize="none"></label><label class="ikm-alan"><span>Şifre</span><input type="password" id="pf-hd-sif" autocomplete="new-password"></label><label class="ikm-alan"><span>Port</span><input type="number" id="pf-hd-port" value="3306"></label></div>' +
                '<button type="button" class="ikm-btn" id="pf-kc-l"' + (j.curl ? '' : ' disabled') + '><i class="fa-solid fa-list"></i> Kaynaktaki kurumları listele</button><div id="pf-kc-liste" style="margin-top:10px"></div></div></div></section>' +
                '<section class="ikm-kart pf-bk-kart">' + ust('fa-clock-rotate-left', '#f59e0b', 'Son işlemler', '') + '<div class="pf-bk-liste"><div><small>Uygulanan veritabanı değişiklikleri</small><div>' + mig + '</div></div><div><small>Güncelleme öncesi yedekler <em>(sunucuda storage/ klasöründe)</em></small><div>' + yed + '</div></div></div></section>';
            $$('[data-kp]', icerik).forEach(function (b) { b.onclick = function () { kopyala(b.dataset.kp); }; });
            $$('.pf-dk', icerik).forEach(function (k) { var i = $('input', k), b = $('b', k), a = $('small', k); i.addEventListener('change', function () { var f = i.files[0]; if (f) { b.textContent = f.name; a.textContent = boyut(f.size) + ' · hazır'; k.classList.add('dolu'); } }); ['dragenter', 'dragover'].forEach(function (e) { k.addEventListener(e, function () { k.classList.add('uz'); }); }); ['dragleave', 'drop'].forEach(function (e) { k.addEventListener(e, function () { k.classList.remove('uz'); }); }); });
            function dosyaAl(id) { var d = $(id, icerik).files[0]; if (!d) { bildir('Önce bir .zip dosyası seçin.', 'uyari'); return null; } return d; }
            function bitis(r) { bildir('Güncellendi: sürüm ' + r.surum + ', ' + r.dosya + ' dosya' + (r.migrasyon && r.migrasyon.length ? ', ' + r.migrasyon.length + ' veritabanı değişikliği' : '') + '.', 'basari'); git('bakim', true); }
            $('#pf-gp', icerik).onclick = function () { var d = dosyaAl('#pf-gp-d'); if (!d) return; onayla({ baslik: 'Güncelleme uygulansın mı?', mesaj: 'Kod dosyaları değiştirilir; veriler ve ayarlar korunur.', evet: 'Uygula' }).then(function (ok) { if (ok) return gonder('paket_yukle', {}, d).then(bitis); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            $('#pf-ug', icerik).onclick = function () { onayla({ baslik: 'Kaynaktan güncellensin mi?', evet: 'Güncelle' }).then(function (ok) { if (ok) return gonder('uzak_guncelle', {}).then(bitis); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            $('#pf-ks', icerik).onclick = function () { gonder('kaynak_kaydet', { kaynak: $('#pf-ks-u', icerik).value, kaynak_anahtar: $('#pf-ks-k', icerik).value }).then(function () { bildir('Kaydedildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            $('#pf-kc-l', icerik).onclick = function () {
                var kap = $('#pf-kc-liste', icerik); kap.innerHTML = '<span class="ikm-soluk">Yükleniyor…</span>';
                gonder('kaynak_kaydet', { kaynak: $('#pf-ks-u', icerik).value, kaynak_anahtar: $('#pf-ks-k', icerik).value }).then(function () { return gonder('kaynak_kurumlar', {}); }).then(function (r) {
                    if (!r.kurumlar.length) { kap.innerHTML = '<span class="ikm-soluk">Kaynakta kurum yok.</span>'; return; }
                    kap.innerHTML = r.kurumlar.map(function (k) { return '<div style="display:flex;gap:8px;align-items:center;justify-content:space-between;padding:8px 0;border-top:1px solid var(--ik-cizgi)"><span><b>' + esc(k.ad) + '</b> <small class="ikm-soluk">#' + k.id + ' · ' + esc(k.kod) + '</small></span><button type="button" class="ikm-btn ikm-btn-kucuk" data-kc="' + k.id + '" data-ad="' + esc(k.ad) + '"><i class="fa-solid fa-download"></i> Veriyi çek</button></div>'; }).join('');
                    $$('[data-kc]', kap).forEach(function (b) { b.onclick = function () {
                        var hd = $('#pf-hd-ad', icerik).value.trim(), hedef = hd ? '“' + hd + '” veri tabanı' : 'bu kurulumun veri tabanı';
                        onayla({ baslik: 'Kurum verisi aktarılsın mı?', mesaj: b.dataset.ad + ' kurumunun verisi ' + hedef + ' içine yazılır. Orada bu kurumun verisi varsa yenisiyle DEĞİŞTİRİLİR. Büyük kurumlarda birkaç dakika sürebilir.', evet: 'Aktar', tur: 'tehlike' }).then(function (ok) {
                            if (!ok) return; b.disabled = true;
                            return gonder('kurum_cek', { kurum_id: b.dataset.kc, hdb_ad: hd, hdb_host: $('#pf-hd-host', icerik).value, hdb_port: $('#pf-hd-port', icerik).value, hdb_kullanici: $('#pf-hd-kul', icerik).value, hdb_sifre: $('#pf-hd-sif', icerik).value }).then(function (s) {
                                bildir(s.ad + (s.guncellendi ? ' güncellendi: ' : ' aktarıldı: ') + s.tablo + ' tablo' + (s.yerel ? ', ' + s.dosya + ' dosya' : '') + '.', 'basari'); b.textContent = 'Aktarıldı'; });
                        }).catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); });
                    }; });
                }).catch(function (e) { kap.innerHTML = ''; bildir(e.message, 'hata'); });
            };
            $('#pf-an-y', icerik).onclick = function () { onayla({ baslik: 'Anahtar yenilensin mi?', mesaj: 'Eski anahtarı kullanan kurulumlar paket çekemez.', evet: 'Yenile' }).then(function (ok) { if (ok) return gonder('anahtar_yenile', {}).then(function () { git('bakim', true); }); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            $('#pf-yg', icerik).onclick = function () { var d = dosyaAl('#pf-yg-d'); if (!d) return; gonder('yedek_yukle', { onay: $('#pf-yg-o', icerik).value }, d).then(function (r) { bildir(r.tablo + ' tablo, ' + r.dosya + ' dosya geri yüklendi. Sayfa yenileniyor…', 'basari'); setTimeout(function () { location.reload(); }, 1500); }).catch(function (e) { bildir(e.message, 'hata'); }); };
        });
    });

    /* ======================= YÖNETİCİLER ======================= */
    kayit('yoneticiler', 'Platform yöneticileri', 'Bu paneli kullanabilen kişiler.', function () {
        $('#pf-ust-sag').innerHTML = '<button type="button" class="ikm-btn" id="pf-sif"><i class="fa-solid fa-key"></i> <span>Şifremi değiştir</span></button><button type="button" class="ikm-btn ikm-btn-ana" id="pf-yon-yeni"><i class="fa-solid fa-plus"></i> <span>Yönetici ekle</span></button>';
        $('#pf-yon-yeni').onclick = function () { yoneticiFormu(null); };
        $('#pf-sif').onclick = function () {
            var m = modal({ baslik: 'Şifremi değiştir', ikon: 'fa-key', gen: 440, govde: '<form class="ikm-form" id="pf-sf2" autocomplete="off"><label class="ikm-alan"><span>Mevcut şifre</span><input type="password" name="eski"></label><label class="ikm-alan"><span>Yeni şifre <small>(en az 10 karakter, harf + rakam)</small></span><input type="password" name="yeni" autocomplete="new-password"></label></form>', alt: '<button class="ikm-btn" data-kapat>Vazgeç</button><button class="ikm-btn ikm-btn-ana" id="pf-sf2-k">Kaydet</button>' });
            $('#pf-sf2-k', m.el).onclick = function () { var f = $('#pf-sf2', m.el); gonder('sifre_degistir', { eski: f.elements.eski.value, yeni: f.elements.yeni.value }).then(function () { m.kapat(); bildir('Şifre değiştirildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
        };
        return getir({ islem: 'yoneticiler' }).then(function (j) {
            icerik.innerHTML = '<div class="ikm-kart"><div class="ikm-tablo-kap"><table class="ikm-tablo"><thead><tr><th>Ad soyad</th><th>Kullanıcı adı</th><th>E-posta</th><th>Son giriş</th><th>Durum</th><th></th></tr></thead><tbody>' + j.yoneticiler.map(function (y) {
                return '<tr><td><b>' + esc(y.ad_soyad) + '</b>' + (+y.id === j.ben ? ' <span class="ikm-cip mavi">Siz</span>' : '') + '</td><td>@' + esc(y.kullanici_adi) + '</td><td>' + esc(y.email || '—') + '</td><td>' + tarih(y.son_giris) + '</td><td><span class="ikm-cip ' + (+y.aktif ? 'yesil' : 'gri') + '">' + (+y.aktif ? 'Aktif' : 'Pasif') + '</span></td><td class="ikm-islem"><button type="button" class="ikm-btn ikm-btn-kucuk" data-d="' + y.id + '"><i class="fa-solid fa-pen"></i></button>' + (+y.id === j.ben ? '' : '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-s="' + y.id + '"><i class="fa-solid fa-trash"></i></button>') + '</td></tr>';
            }).join('') + '</tbody></table></div></div>';
            var eb = document.createElement('div'); eb.className = 'ikm-kart'; eb.style.marginTop = '14px'; icerik.appendChild(eb);
            function engelCiz() {
                getir({ islem: 'engel_liste' }).then(function (e) {
                    var L = e.liste || [], n = L.filter(function (x) { return x.engelli; }).length;
                    eb.innerHTML = '<div style="display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap"><div><b><i class="fa-solid fa-ban"></i> Giriş engelleri</b><div class="ikm-soluk" style="font-size:12.5px">Çok fazla hatalı denemede kişi/IP 15 dakika engellenir. Buradan hemen kaldırabilirsiniz.</div></div><button type="button" class="ikm-btn" id="pf-eng-hepsi"' + (L.length ? '' : ' disabled') + '><i class="fa-solid fa-lock-open"></i> Tüm engelleri kaldır</button></div>' +
                        (L.length ? '<div class="ikm-tablo-kap" style="margin-top:10px"><table class="ikm-tablo"><thead><tr><th>Hesap</th><th>IP</th><th>Hatalı deneme</th><th>Son</th><th>Durum</th></tr></thead><tbody>' + L.map(function (x) { return '<tr><td>' + esc(x.anahtar.replace(/^pf\|/, 'platform: ')) + '</td><td>' + esc(x.ip) + '</td><td>' + x.sayi + '</td><td>' + tarih(x.son) + '</td><td><span class="ikm-cip ' + (x.engelli ? 'kirmizi' : 'sari') + '">' + (x.engelli ? 'Engelli' : 'İzleniyor') + '</span></td></tr>'; }).join('') + '</tbody></table></div>' : '<div class="ikm-soluk" style="margin-top:8px">Şu an engelli ya da izlenen giriş yok.</div>');
                    var h = $('#pf-eng-hepsi', eb); if (h) h.onclick = function () { gonder('engel_kaldir', {}).then(function () { bildir('Engeller kaldırıldı (' + n + ').', 'basari'); engelCiz(); }).catch(function (er) { bildir(er.message, 'hata'); }); };
                }).catch(function () {});
            }
            engelCiz();
            $$('[data-d]', icerik).forEach(function (b) { b.onclick = function () { yoneticiFormu(j.yoneticiler.filter(function (y) { return String(y.id) === b.dataset.d; })[0]); }; });
            $$('[data-s]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Yönetici silinsin mi?', evet: 'Sil', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('yonetici_sil', { id: b.dataset.s }).then(function () { bildir('Silindi.', 'basari'); git('yoneticiler'); }); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
        });
    });
    function yoneticiFormu(y) {
        var yeni = !y, v = y || { ad_soyad: '', kullanici_adi: '', email: '', aktif: 1 };
        var m = modal({ baslik: yeni ? 'Yönetici ekle' : 'Yöneticiyi düzenle', ikon: 'fa-user-shield', gen: 480, govde: '<form class="ikm-form" id="pf-yy" autocomplete="off"><label class="ikm-alan"><span>Ad soyad</span><input name="ad_soyad" value="' + esc(v.ad_soyad) + '"></label><label class="ikm-alan"><span>Kullanıcı adı</span><input name="kullanici_adi" value="' + esc(v.kullanici_adi) + '" ' + (yeni ? '' : 'readonly') + ' autocapitalize="none"></label><label class="ikm-alan"><span>E-posta</span><input type="email" name="email" value="' + esc(v.email) + '"></label><label class="ikm-alan"><span>' + (yeni ? 'Şifre' : 'Yeni şifre <small>(boşsa değişmez)</small>') + ' <small>(en az 10 karakter, harf + rakam)</small></span><input type="password" name="sifre" autocomplete="new-password"></label>' +
            (yeni ? '' : '<label class="ikm-anahtar-satir"><span><b>Hesap aktif</b></span><span class="ikm-anahtar"><input type="checkbox" name="aktif"' + (+v.aktif ? ' checked' : '') + '><i></i></span></label>') + '</form>', alt: '<button class="ikm-btn" data-kapat>Vazgeç</button><button class="ikm-btn ikm-btn-ana" id="pf-yy-k">Kaydet</button>' });
        $('#pf-yy-k', m.el).onclick = function () { var f = $('#pf-yy', m.el), d = { id: yeni ? 0 : y.id, ad_soyad: f.elements.ad_soyad.value, kullanici_adi: f.elements.kullanici_adi.value, email: f.elements.email.value, sifre: f.elements.sifre.value, aktif: yeni || f.elements.aktif.checked ? '1' : '0' }; gonder('yonetici_kaydet', d).then(function () { m.kapat(); bildir('Kaydedildi.', 'basari'); git('yoneticiler'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
    }

    /* ======================= KAYITLAR ======================= */
    kayit('kayitlar', 'İşlem kayıtları', 'Son 300 platform ve giriş işlemi.', function () {
        return getir({ islem: 'kayitlar' }).then(function (j) {
            var TUR = [['', 'Tümü'], ['platform', 'Platform'], ['kurum', 'Kurum girişleri'], ['mesaj', 'Mesajlar']], sec = '', ara = '';
            function turu(k) { if (/^platform:/.test(k.kim || '')) return /mesaj/.test(k.olay) ? 'mesaj' : 'platform'; return /mesaj/.test(k.olay) ? 'mesaj' : 'kurum'; }
            function ciz() {
                var q = ara.toLocaleLowerCase('tr-TR');
                var l = j.kayitlar.filter(function (k) { return (!sec || turu(k) === sec) && (!q || (k.kim + ' ' + (OLAY[k.olay] || k.olay) + ' ' + k.kurum + ' ' + k.ayrinti + ' ' + k.ip).toLocaleLowerCase('tr-TR').indexOf(q) >= 0); });
                $('#kl-liste').innerHTML = l.length ? '<div class="ikm-tablo-kap"><table class="ikm-tablo"><thead><tr><th>Zaman</th><th>Kim</th><th>İşlem</th><th>Kurum</th><th>Ayrıntı</th><th>IP</th></tr></thead><tbody>' + l.map(function (k) { var t = turu(k); return '<tr><td data-e="Zaman">' + tarih(k.zaman) + '</td><td data-e="Kim">' + esc(k.kim || '—') + '</td><td data-e="İşlem"><span class="pf-kl-nokta ' + t + '"></span>' + esc(OLAY[k.olay] || k.olay) + '</td><td data-e="Kurum">' + esc(k.kurum || '—') + '</td><td data-e="Ayrıntı">' + esc(k.ayrinti) + '</td><td data-e="IP">' + esc(k.ip) + '</td></tr>'; }).join('') + '</tbody></table></div>' : bosKutu('fa-clipboard-list', 'Kayıt bulunamadı', 'Filtreyi veya aramayı değiştirin.');
            }
            icerik.innerHTML = '<div class="pf-arac"><div class="ikm-ara"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="kl-ara" placeholder="Kayıtlarda ara…"></div></div><div class="pf-filtreler">' + TUR.map(function (t) { var n = j.kayitlar.filter(function (k) { return !t[0] || turu(k) === t[0]; }).length; return '<button type="button" class="pf-filtre' + (t[0] === '' ? ' aktif' : '') + '" data-kt="' + t[0] + '"><span>' + t[1] + '</span><em>' + n + '</em></button>'; }).join('') + '</div><div class="ikm-kart" id="kl-liste"></div>';
            $$('[data-kt]', icerik).forEach(function (b) { b.onclick = function () { sec = b.dataset.kt; $$('[data-kt]', icerik).forEach(function (x) { x.classList.toggle('aktif', x === b); }); ciz(); }; });
            $('#kl-ara').oninput = function () { ara = this.value; ciz(); }; ciz();
        });
    });

    window.PFX = { $: $, $$: $$, esc: esc, getir: getir, gonder: gonder, modal: modal, bildir: bildir, onayla: onayla, kayit: kayit, git: git, tarih: tarih, bosKutu: bosKutu, iskelet: iskelet, icerik: icerik, kurumlar: function () { return KURUMLAR; } };
    document.dispatchEvent(new Event('pfx-hazir'));
    git((location.hash || '#ozet').slice(1));
})();
