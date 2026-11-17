                '<div class="ikm-alan"><span>Vekil <small>(isteğe bağlı)</small></span><div class="ik-vekil"><input type="hidden" name="vekil_id" value=""><button type="button" class="ik-vekil-btn" id="ik-vekil-btn"><span>—</span><i class="fa-solid fa-chevron-down"></i></button><div class="ik-vekil-panel" id="ik-vekil-panel" hidden><input type="search" id="ik-vekil-ara" placeholder="Personel ara…" autocomplete="off" aria-label="Personel ara"><div class="ik-vekil-liste" id="ik-vekil-liste"></div></div></div></div>' +
/* ==========================================================================
   tb-ik.js — İnsan Kaynakları (modern arayüz, sol menü)
   Çekirdek + arayüz parçaları (uyarı, onay penceresi, modal) + İK görünümleri.
   Eklenti dosyaları (tb-ik-akis.js, tb-ik-giris.js) window.IKM üzerinden görünüm ekler.
   ========================================================================== */
(function () {
    'use strict';
    var API = 'ik_islem.php';
    var kok = document.getElementById('ik-kok');
    if (!kok) return;
    var icerik = document.getElementById('ik-icerik');
    var V = null, Y = window.IK_YETKI || {}, aktifSekme = 'genel';

    /* ======================= yardımcılar ======================= */
    var $ = function (s, k) { return (k || document).querySelector(s); };
    var $$ = function (s, k) { return Array.prototype.slice.call((k || document).querySelectorAll(s)); };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function csrf() { return (window.TB && window.TB.csrf) || ''; }
    function istek(api) {
        function oku(r) { return r.json().catch(function () { return { ok: false, hata: 'Sunucu yanıtı okunamadı (' + r.status + ').' }; }).then(function (j) { if (!j.ok) { var e = new Error(j.hata || 'İşlem başarısız.'); e.veri = j; throw e; } return j; }); }
        return {
            getir: function (p) { return fetch(api + '?' + new URLSearchParams(p), { credentials: 'same-origin', cache: 'no-store' }).then(oku); },
            gonder: function (islem, veri, dosya) {
                var fd = new FormData(); fd.append('islem', islem); fd.append('_csrf', csrf());
                Object.keys(veri || {}).forEach(function (k) { if (veri[k] !== undefined && veri[k] !== null) fd.append(k, veri[k]); });
                if (dosya) fd.append('dosya', dosya);
                return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf() } }).then(oku);
            },
            form: function (fd) { fd.append('_csrf', csrf()); return fetch(api, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf() } }).then(oku); }
        };
    }
    var IK = istek(API), getir = IK.getir, gonder = IK.gonder;
    var AYLAR = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
    var AYK = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
    function tarih(t) { var m = String(t || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? m[3] + '.' + m[2] + '.' + m[1] : ''; }
    function tarihKisa(t) { var m = String(t || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? +m[3] + ' ' + AYK[+m[2] - 1] : ''; }
    function tarihSaat(t) { var m = String(t || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/); return m ? +m[3] + ' ' + AYK[+m[2] - 1] + ' ' + m[4] + ':' + m[5] : ''; }
    function sayi(n) { return n == null ? '—' : String(+n % 1 ? (+n).toFixed(1) : +n).replace('.', ','); }
    function para(n) { return n == null || n === '' ? '' : Number(n).toLocaleString('tr-TR', { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + ' ₺'; }
    function bas(ad) { var p = String(ad || '?').trim().split(/\s+/); return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toLocaleUpperCase('tr-TR'); }
    function av(ad, renk, resim, boy) {
        return '<span class="ikm-av' + (resim ? ' foto' : '') + (boy ? ' ' + boy : '') + '" style="background:' + esc(renk || '#6366f1') + '">' + esc(bas(ad)) + (resim ? '<img src="' + esc(resim) + '" alt="" onerror="this.parentNode.classList.remove(\'foto\');this.remove()">' : '') + '</span>';
    }
    function boyutYaz(b) { return b > 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    function bugunYmd() { var d = new Date(); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
    function isGunu(b0, b1, yarim) {
        var b = new Date(b0 + 'T00:00:00'), e = new Date((b1 || b0) + 'T00:00:00'), n = 0;
        if (isNaN(b) || isNaN(e) || e < b) return 0;
        for (var d = new Date(b); d <= e; d.setDate(d.getDate() + 1)) { var g = d.getDay(); if (g !== 0 && g !== 6) n++; }
        return yarim && n === 1 ? 0.5 : n;
    }
    function yukleniyor() { return '<div class="ikm-iskelet"><i></i><i></i><i></i></div>'; }
    function bosKutu(ikon, baslik, alt) { return '<div class="ikm-bos"><span><i class="fa-solid ' + ikon + '"></i></span><b>' + esc(baslik) + '</b>' + (alt ? '<p>' + esc(alt) + '</p>' : '') + '</div>'; }
    function hataKutu(m) { return bosKutu('fa-triangle-exclamation', 'Yüklenemedi', m); }
    function ikonlar(k) { return { basari: 'fa-circle-check', hata: 'fa-circle-xmark', uyari: 'fa-triangle-exclamation', bilgi: 'fa-circle-info', tehlike: 'fa-trash-can' }[k] || 'fa-circle-info'; }

    /* ======================= MODERN UYARI (toast) ======================= */
    var toastYigin = null;
    function bildir(mesaj, tur) {
        tur = { basari: 'basari', hata: 'hata', uyari: 'uyari', bilgi: 'bilgi', success: 'basari', error: 'hata', warning: 'uyari', info: 'bilgi' }[tur] || 'bilgi';
        if (!toastYigin) { toastYigin = document.createElement('div'); toastYigin.className = 'ikm-toast-yigin'; toastYigin.setAttribute('aria-live', 'polite'); document.body.appendChild(toastYigin); }
        var el = document.createElement('div'), sure = tur === 'hata' ? 6500 : 3800;
        el.className = 'ikm-toast ' + tur; el.setAttribute('role', tur === 'hata' ? 'alert' : 'status');
        el.innerHTML = '<span class="ikm-toast-ikon"><i class="fa-solid ' + ikonlar(tur) + '"></i></span><div class="ikm-toast-yazi">' + esc(mesaj) + '</div><button type="button" class="ikm-toast-x" aria-label="Kapat">✕</button><i class="ikm-toast-cubuk" style="animation-duration:' + sure + 'ms"></i>';
        toastYigin.appendChild(el);
        var acik = Array.prototype.filter.call(toastYigin.children, function (x) { return !x.classList.contains('cikis'); });
        while (acik.length > 3) { var eskiT = acik.shift(); eskiT.classList.add('cikis'); (function (n) { setTimeout(function () { n.remove(); }, 240); })(eskiT); }
        var kapat = function () { if (el.classList.contains('cikis')) return; el.classList.add('cikis'); setTimeout(function () { el.remove(); }, 240); };
        var t = setTimeout(kapat, sure);
        el.addEventListener('mouseenter', function () { clearTimeout(t); el.classList.add('dur'); });
        el.addEventListener('mouseleave', function () { el.classList.remove('dur'); t = setTimeout(kapat, 2200); });
        $('.ikm-toast-x', el).addEventListener('click', kapat);
    }

    /* ======================= MODAL / ONAY / SORU ======================= */
    var modalSayac = 0;
    function modal(o) {
        var arka = document.createElement('div'); arka.className = 'ikm-arka';
        arka.innerHTML = '<div class="ikm-modal' + (o.sinif ? ' ' + o.sinif : '') + '" role="dialog" aria-modal="true" style="max-width:' + (o.gen || 560) + 'px">' +
            '<div class="ikm-modal-ust">' + (o.ikon ? '<span class="ikm-modal-ikon ' + (o.renk || '') + '"><i class="fa-solid ' + o.ikon + '"></i></span>' : '') + '<h3>' + o.baslik + '</h3><button type="button" class="ikm-ikon-btn ikm-x" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button></div>' +
            '<div class="ikm-modal-govde">' + (o.govde || '') + '</div>' + (o.alt ? '<div class="ikm-modal-alt">' + o.alt + '</div>' : '') + '</div>';
        document.body.appendChild(arka);
        document.body.classList.add('ikm-kilit');
        var m = { el: arka, kapat: null };
        m.kapat = function () { if (!arka.parentNode) return; arka.classList.add('cikis'); setTimeout(function () { arka.remove(); if (!$('.ikm-arka')) document.body.classList.remove('ikm-kilit'); }, 160); document.removeEventListener('keydown', tus); if (o.kapaninca) o.kapaninca(); };
        function tus(e) { if (e.key === 'Escape' && arka === Array.prototype.slice.call(document.querySelectorAll('.ikm-arka')).pop()) { e.stopPropagation(); m.kapat(); } }
        document.addEventListener('keydown', tus);
        arka.addEventListener('mousedown', function (e) { if (e.target === arka && !o.sabit) m.kapat(); });
        $('.ikm-x', arka).addEventListener('click', m.kapat);
        $$('[data-kapat]', arka).forEach(function (b) { b.addEventListener('click', m.kapat); });
        return m;
    }
    /** onayla({baslik, mesaj, evet, hayir, tur:'tehlike'|'basari'|'uyari'|'bilgi'}) → Promise<boolean> */
    function onayla(o) {
        return new Promise(function (coz) {
            var tur = o.tur || 'bilgi', bitti = false;
            var arka = document.createElement('div'); arka.className = 'ikm-arka ikm-arka-onay';
            arka.innerHTML = '<div class="ikm-onay ' + tur + '" role="alertdialog" aria-modal="true"><span class="ikm-onay-ikon"><i class="fa-solid ' + ikonlar(tur === 'tehlike' ? 'tehlike' : tur) + '"></i></span>' +
                '<h3>' + esc(o.baslik || 'Emin misiniz?') + '</h3>' + (o.mesaj ? '<p>' + esc(o.mesaj) + '</p>' : '') +
                '<div class="ikm-onay-dugmeler"><button type="button" class="ikm-btn" data-h>' + esc(o.hayir || 'Vazgeç') + '</button><button type="button" class="ikm-btn ' + (tur === 'tehlike' ? 'ikm-btn-tehlike-dolu' : tur === 'basari' ? 'ikm-btn-yesil' : 'ikm-btn-ana') + '" data-e>' + esc(o.evet || 'Evet') + '</button></div></div>';
            document.body.appendChild(arka);
            function son(v) { if (bitti) return; bitti = true; document.removeEventListener('keydown', tus, true); arka.classList.add('cikis'); setTimeout(function () { arka.remove(); }, 160); coz(v); }
            function tus(e) { if (e.key === 'Escape') { e.stopPropagation(); son(false); } else if (e.key === 'Enter') { e.preventDefault(); son(true); } }
            document.addEventListener('keydown', tus, true);
            $('[data-h]', arka).onclick = function () { son(false); }; $('[data-e]', arka).onclick = function () { son(true); };
            arka.addEventListener('mousedown', function (e) { if (e.target === arka) son(false); });
            setTimeout(function () { $('[data-e]', arka).focus(); }, 30);
        });
    }
    /** soru({baslik, mesaj, etiket, yer, zorunlu, evet, tur, uzun, deger}) → Promise<string|null> */
    function soru(o) {
        return new Promise(function (coz) {
            var bitti = false, tur = o.tur || 'bilgi';
            var arka = document.createElement('div'); arka.className = 'ikm-arka ikm-arka-onay';
            arka.innerHTML = '<div class="ikm-onay ikm-soru ' + tur + '" role="dialog" aria-modal="true"><span class="ikm-onay-ikon"><i class="fa-solid ' + (o.ikon || 'fa-pen-to-square') + '"></i></span><h3>' + esc(o.baslik || '') + '</h3>' + (o.mesaj ? '<p>' + esc(o.mesaj) + '</p>' : '') +
                '<label class="ikm-alan"><span>' + esc(o.etiket || '') + (o.zorunlu ? ' <b>*</b>' : '') + '</span>' + (o.uzun === false ? '<input type="text" maxlength="500">' : '<textarea rows="3" maxlength="500"></textarea>') + '</label>' +
                '<div class="ikm-onay-dugmeler"><button type="button" class="ikm-btn" data-h>Vazgeç</button><button type="button" class="ikm-btn ' + (tur === 'tehlike' ? 'ikm-btn-tehlike-dolu' : 'ikm-btn-ana') + '" data-e>' + esc(o.evet || 'Tamam') + '</button></div></div>';
            document.body.appendChild(arka);
            var girdi = $('textarea, input', arka); girdi.value = o.deger || ''; if (o.yer) girdi.placeholder = o.yer;
            function son(v) { if (bitti) return; bitti = true; document.removeEventListener('keydown', tus, true); arka.classList.add('cikis'); setTimeout(function () { arka.remove(); }, 160); coz(v); }
            function tamam() { var v = girdi.value.trim(); if (o.zorunlu && !v) { girdi.classList.add('hatali'); girdi.focus(); return; } son(v); }
            function tus(e) { if (e.key === 'Escape') { e.stopPropagation(); son(null); } else if (e.key === 'Enter' && (e.ctrlKey || girdi.tagName === 'INPUT')) { e.preventDefault(); tamam(); } }
            document.addEventListener('keydown', tus, true);
            $('[data-h]', arka).onclick = function () { son(null); }; $('[data-e]', arka).onclick = tamam;
            arka.addEventListener('mousedown', function (e) { if (e.target === arka) son(null); });
            setTimeout(function () { girdi.focus(); }, 40);
        });
    }
    window.__tbBildirOrj = window.tbBildir; window.__tbOnaylaOrj = window.tbOnayla;
    window.tbBildir = bildir; window.tbOnayla = onayla; // bu sayfadaki tüm uyarılar (Yetkiler penceresi dahil) modern bileşenleri kullanır

    /* ======================= SEKMELER / YÖNLENDİRME ======================= */
    var GORUNUM = {}, BASLIK = {};
    function kayit(ad, baslik, alt, ciz) { GORUNUM[ad] = ciz; BASLIK[ad] = [baslik, alt]; }
    var menuKapat = function () { kok.classList.remove('menu-acik'); };
    $('#ikm-menu-ac').addEventListener('click', function () { kok.classList.add('menu-acik'); });
    $('#ikm-perde').addEventListener('click', menuKapat); $('#ikm-yan-kapat').addEventListener('click', menuKapat);
    // Tıklama dalgası (menü)
    $('#ik-sekmeler').addEventListener('pointerdown', function (e) {
        var b = e.target.closest('.ikm-nav'); if (!b) return;
        var r = b.getBoundingClientRect(), d = Math.max(r.width, r.height) * 2, o = document.createElement('i');
        o.className = 'ikm-dalga'; o.style.cssText = 'width:' + d + 'px;height:' + d + 'px;left:' + (e.clientX - r.left - d / 2) + 'px;top:' + (e.clientY - r.top - d / 2) + 'px';
        b.appendChild(o); setTimeout(function () { o.remove(); }, 650);
    });
    $$('#ik-sekmeler .ikm-nav[data-s]').forEach(function (b) {
        b.addEventListener('click', function () { menuKapat(); if (b.dataset.s === 'benimikm') { if (V) kartAc(V.ben); return; } sekmeAc(b.dataset.s); });
    });
    function urlYaz(guncelle) {
        var u = new URL(location.href); guncelle(u.searchParams); history.replaceState(null, '', u.pathname + (u.searchParams.toString() ? '?' + u.searchParams : ''));
    }
    function sekmeAc(s, ozel) {
        if (typeof cek !== 'undefined' && cek && !(ozel && ozel.kartAcik)) cekmeceKapat();
        if (!GORUNUM[s]) s = 'genel';
        if (typeof window.IKM.sekmeDegisiyor === 'function') window.IKM.sekmeDegisiyor(aktifSekme, s);
        if (aktifSekme !== s) pF = { ara: '', departman: '', durum: 'aktif' }; // başka sayfadan dönünce arama/filtre sıfırlansın
        aktifSekme = s;
        $$('#ik-sekmeler .ikm-nav[data-s]').forEach(function (b) { b.classList.toggle('aktif', b.dataset.s === s); });
        var bs = BASLIK[s] || ['', '']; $('#ikm-baslik').textContent = bs[0]; $('#ikm-altbaslik').textContent = bs[1];
        urlYaz(function (p) { p.set('sekme', s); p.delete('personel'); if (!ozel) { p.delete('talep'); p.delete('kapsam'); p.delete('id'); } });
        icerik.scrollTop = 0; window.scrollTo({ top: 0 });
        icerik.classList.remove('ikm-gir'); void icerik.offsetWidth; icerik.classList.add('ikm-gir');
        try { GORUNUM[s](ozel || {}); } catch (e) { icerik.innerHTML = hataKutu(e.message); }
        if (ozel && ozel.id && !/^(duyurular|anketler|izinler|avanslar)$/.test(s)) vurgula(ozel.id);
    }
    /** Bildirimden gelen kayıt numarasını açılan listede bulur, ortaya kaydırır ve vurgular (her sayfa kendi biçiminde çizer; satırlardaki data-* etiketlerine bakılır) */
    function vurgula(id) {
        var sid = String(id), n = 0;
        var dsen = /^data-(?:[a-z]+-)*(?:id|onay|red|sil|duz|tamam|detay|ac|karar|kayit)$|^data-(?:mas|ms|mesai)[a-z-]*$/;
        var t = setInterval(function () {
            n++;
            var hedef = null, hepsi = icerik.getElementsByTagName('*');
            for (var i = 0; i < hepsi.length && !hedef; i++) {
                var at = hepsi[i].attributes;
                for (var k = 0; k < at.length; k++) { if (at[k].value === sid && dsen.test(at[k].name)) { hedef = hepsi[i]; break; } }
            }
            if (hedef) {
                clearInterval(t);
                var sat = (hedef.closest && hedef.closest('.ikm-satir,.ikm-kart,tr,li')) || hedef;
                try { sat.scrollIntoView({ block: 'center' }); } catch (e) {}
                sat.style.boxShadow = '0 0 0 2px #6366f1'; sat.style.background = 'rgba(99,102,241,.10)'; sat.style.borderRadius = '14px';
            } else if (n > 14) clearInterval(t);
        }, 250);
    }
    /** ik.php?sekme=…&talep=… biçimli bağlantıları sayfa yenilemeden açar. true = işlendi */
    function git(link) {
        try {
            var u = new URL(link, location.href);
            if (u.pathname.replace(/.*\//, '').replace(/\.php$/, '') !== 'ik') return false;
            var p = u.searchParams, s = p.get('sekme') || 'genel';
            if (p.get('kapsam') && s === 'izinler') izinF.kapsam = p.get('kapsam');
            if (p.get('personel')) { sekmeAc(s); kartAc(+p.get('personel')); return true; }
            sekmeAc(s, { talep: p.get('talep'), id: p.get('id') });
            if (p.get('talep') && s === 'izinler') izinDetay(+p.get('talep'));
            return true;
        } catch (e) { return false; }
    }

    /* ======================= DIŞA AÇILAN API (eklentiler için) ======================= */
    window.IKM = { $: $, $$: $$, esc: esc, istek: istek, getir: getir, gonder: gonder, bildir: bildir, onayla: onayla, soru: soru, modal: modal, kayit: kayit, git: git, av: av,
        tarih: tarih, tarihKisa: tarihKisa, tarihSaat: tarihSaat, sayi: sayi, para: para, yukleniyor: yukleniyor, bosKutu: bosKutu, hataKutu: hataKutu, boyutYaz: boyutYaz, bugunYmd: bugunYmd,
        icerik: icerik, yetki: function () { return Y; }, veri: function () { return V; }, sekmeAc: sekmeAc, aylar: AYLAR, kartAc: function (id) { return kartAc(id); }, yenileVeri: function () { return ilkVeri(); } };

    window.IKM.izinTalebi = function (adina, tarih) { izinTalebi(adina || null); if (tarih) setTimeout(function () { var a = $('.ikm-modal [name=baslangic]'), b = $('.ikm-modal [name=bitis]'); if (a) { a.value = tarih; a.dispatchEvent(new Event('change', { bubbles: true })); } if (b) { b.value = tarih; b.dispatchEvent(new Event('change', { bubbles: true })); } }, 120); };

    /* ======================= GENEL BAKIŞ ======================= */
    function selam() { var s = new Date().getHours(); return s < 6 ? 'İyi geceler' : s < 12 ? 'Günaydın' : s < 18 ? 'İyi günler' : 'İyi akşamlar'; }
    function wOku() { try { return JSON.parse(localStorage.getItem('tb_ik_panel_' + (V && V.ben || 0)) || '{}') || {}; } catch (e) { return {}; } }
    function wYaz(o) { try { localStorage.setItem('tb_ik_panel_' + (V && V.ben || 0), JSON.stringify(o)); } catch (e) {} }
    function panelOzellestir(liste, W, yenile) {
        var m = modal({ baslik: 'Paneli özelleştir', ikon: 'fa-sliders', gen: 460, govde: '<p class="ikm-soluk" style="margin:0 0 12px">Ana panelde görmek istediğiniz kutuları açıp kapatın. Bu tercih yalnızca bu tarayıcıda, sizin için saklanır.</p><div class="ikm-ozel-liste">' +
            liste.map(function (x) { return '<label class="ikm-ozel-satir"><input type="checkbox" data-w="' + x[0] + '"' + (W[x[0]] !== false ? ' checked' : '') + '><i></i><span>' + esc(x[1]) + '</span></label>'; }).join('') + '</div>',
            alt: '<button type="button" class="ikm-btn" id="ik-w-sifirla">Varsayılana dön</button><button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Tamam</button>' });
        $$('[data-w]', m.el).forEach(function (c) { c.onchange = function () { W[c.dataset.w] = c.checked; wYaz(W); yenile(); }; });
        $('#ik-w-sifirla', m.el).onclick = function () { wYaz({}); m.kapat(); yenile(); };
    }
    function genelCiz() {
        icerik.innerHTML = yukleniyor();
        Promise.all([ilkVeri(), V && V.ozet && V.ozet.bana_bekleyen > 0 ? getir({ islem: 'izinler', kapsam: 'bana' }) : Promise.resolve(null), V && V.yetki && (V.yetki.ik_masraf_onay || V.yetki.ik_avans_onay) ? getir({ islem: 'bekleyenler' }).catch(function () { return null; }) : Promise.resolve(null)]).then(function (r) {
            var o = V.ozet, iz = o.izin, bana = r[1], bek2 = r[2], W = wOku();
            var gun = new Date().toLocaleDateString('tr-TR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
            var simdi = new Date(), gunNo = ('0' + simdi.getDate()).slice(-2), ayKisa = simdi.toLocaleDateString('tr-TR', { month: 'long' }) + ' · ' + simdi.toLocaleDateString('tr-TR', { weekday: 'short' });
            var kahraman = '<section class="ikm-kahraman"><div class="ikm-kh-gun" title="' + esc(gun) + '"><b>' + gunNo + '</b><span>' + esc(ayKisa) + '</span></div><div class="ikm-kh-orta"><h2>' + selam() + ', ' + esc((V.ben_ad || '').split(' ')[0] || '') + '</h2>' +
                '<p>' + (function () { var t = []; if (o.bana_bekleyen > 0) t.push('<b>' + o.bana_bekleyen + ' izin</b>'); if (bek2 && bek2.masraf_say) t.push('<b>' + bek2.masraf_say + ' masraf</b>'); if (bek2 && bek2.avans_say) t.push('<b>' + bek2.avans_say + ' avans</b>'); return t.length ? t.join(', ') + ' talebi onayınızı bekliyor.' : 'Bugün onayınızı bekleyen bir talep yok.'; })() + '</p></div>' +
                '<div class="ikm-kahraman-btn">' + (o.bana_bekleyen > 0 ? '<button type="button" class="ikm-btn ikm-btn-ana ikm-btn-beyaz" data-git="izinler-bana"><i class="fa-solid fa-check-double"></i> Taleplere git</button>' : '') +
                '<button type="button" class="ikm-btn ikm-btn-hayalet-beyaz" data-yeni-izin><i class="fa-solid fa-plus"></i> Talep oluştur</button><button type="button" class="ikm-btn ikm-btn-hayalet-beyaz" data-panel-ozel title="Panelde görünecek kutuları seçin"><i class="fa-solid fa-sliders"></i> Paneli özelleştir</button></div></section>';
            var kutu = function (renk, ikon, deger, ad, git) { return '<' + (git ? 'button type="button" data-git="' + git + '"' : 'div') + ' class="ikm-kpi ' + renk + '"><span class="ikm-kpi-ikon"><i class="fa-solid ' + ikon + '"></i></span><div><b>' + deger + '</b><span>' + ad + '</span></div></' + (git ? 'button' : 'div') + '>'; };
            var kutular = '<section class="ikm-kpiler">' +
                kutu('mavi', 'fa-umbrella-beach', iz.hak == null ? '—' : sayi(iz.kalan), 'Kalan yıllık izin (gün)', '') +
                kutu('yesil', 'fa-plane-departure', o.izinli.length, 'Bugün izinde', 'izin_takvimi') +
                (o.bekleyen != null ? kutu('turuncu', 'fa-hourglass-half', o.bekleyen, 'Onay bekleyen talep', 'izinler-bekleyen') : kutu('turuncu', 'fa-hourglass-half', o.benim_bekleyen, 'Bekleyen talebim', 'izinler')) +
                (o.personel != null ? kutu('mor', 'fa-users', o.personel, 'Aktif personel', 'personel') : kutu('mor', 'fa-id-card', '<i class="fa-solid fa-arrow-right" style="font-size:18px"></i>', 'Personel kartım', 'kartim')) + '</section>';
            var banaKart = '';
            (function () {
                var izL = (bana && bana.liste) || [], mL = (bek2 && bek2.masraf) || [], aL = (bek2 && bek2.avans) || [], onayci = !!(V.yetki && (V.yetki.ik_masraf_onay || V.yetki.ik_avans_onay)) || izL.length > 0;
                if (!onayci) return;
                var topl = izL.length + (bek2 ? (bek2.masraf_say || 0) + (bek2.avans_say || 0) : 0);
                var grup = function (ad, ikon, say, govde, git) { return say ? '<div class="ikm-onay-grup"><div class="ikm-onay-baslik"><span><i class="fa-solid ' + ikon + '"></i> ' + ad + ' <b>' + say + '</b></span>' + (git ? '<button type="button" class="ikm-btn ikm-btn-kucuk" data-git="' + git + '">Tümü</button>' : '') + '</div><div class="ikm-liste">' + govde + '</div></div>' : ''; };
                var kisa = function (k, bilgi, tur) { return '<div class="ikm-satir sabit">' + av(k.ad, k.renk, k.resim) + '<span class="ikm-satir-ic"><b>' + esc(k.ad) + '</b><small>' + esc(bilgi) + '</small></span><span class="ikm-tutar">' + para(k.tutar) + '</span><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-yesil" data-g-karar="' + tur + ':' + k.id + ':onay">Onayla</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-g-karar="' + tur + ':' + k.id + ':red">Reddet</button></div>'; };
                banaKart = '<div class="ikm-kart ikm-kart-vurgu tam" data-w="onay"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-bell-concierge"></i> Onay bekleyenler' + (topl ? ' <span class="ikm-cip turuncu">' + topl + '</span>' : '') + '</h3></div>' +
                    (topl ? grup('İzin talepleri', 'fa-plane-departure', izL.length, izL.slice(0, 4).map(function (t) { return izinSatir(t, 'kisa'); }).join(''), 'izinler-bana') +
                        grup('Masraf talepleri', 'fa-wallet', bek2 ? bek2.masraf_say : 0, mL.map(function (k) { return kisa(k, k.bilgi + ' · ' + tarih(k.tarih), 'masraf'); }).join(''), 'masraflar') +
                        grup('Avans talepleri', 'fa-hand-holding-dollar', bek2 ? bek2.avans_say : 0, aL.map(function (k) { return kisa(k, k.bilgi + (k.tarih ? ' · ' + tarih(k.tarih) : ''), 'avans'); }).join(''), 'avanslar') : bosKutu('fa-circle-check', 'Onayınızı bekleyen talep yok', 'Yeni izin, masraf ve avans talepleri burada görünür.')) + '</div>';
            })();
            var kull = +iz.kullanilan || 0, bek = +iz.bekleyen || 0, hak = iz.toplam == null ? (iz.hak == null ? null : +iz.hak) : +iz.toplam;
            var bakiye = '<div class="ikm-kart" data-w="bakiye"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-umbrella-beach"></i> Yıllık izin ' + iz.yil + '</h3></div>' +
                (hak == null ? '<p class="ikm-soluk">İşe giriş tarihiniz girilmediği için yıllık izin hakkı hesaplanamıyor. İK ile iletişime geçin.</p>' :
                    '<div class="ikm-bakiye"><b>' + sayi(iz.kalan) + '</b><span>/ ' + sayi(hak) + ' gün kaldı</span></div>' +
                    '<div class="ikm-ilerleme" role="img" aria-label="Kullanılan ' + kull + ', bekleyen ' + bek + ', hak ' + hak + ' gün">' + (hak > 0 ? '<i class="k" style="width:' + Math.min(100, kull / hak * 100) + '%"></i><i class="b" style="width:' + Math.min(100 - Math.min(100, kull / hak * 100), bek / hak * 100) + '%"></i>' : '') + '</div>' +
                    '<div class="ikm-lejant"><span style="--c:#6366f1">Kullanılan ' + sayi(kull) + '</span><span style="--c:#f59e0b">Onay bekleyen ' + sayi(bek) + '</span></div>' +
                    '<p class="ikm-soluk kucuk">' + (iz.elle ? 'Hak İK tarafından belirlendi.' : 'Kıdeme göre yasal hak (İş Kanunu md. 53).') + '</p>') + '</div>';
            var izinli = '<div class="ikm-kart" data-w="izinli"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-plane-departure"></i> Bugün izinde</h3></div>' + (o.izinli.length ? '<div class="ikm-liste">' + o.izinli.map(function (k) {
                return '<button type="button" class="ikm-satir" data-kisi="' + k.id + '">' + av(k.ad, k.renk, k.resim) + '<span class="ikm-satir-ic"><b>' + esc(k.ad) + '</b><small>' + esc(k.tur) + '</small></span><span class="ikm-cip">' + tarihKisa(k.bitis) + '’e kadar</span></button>';
            }).join('') + '</div>' : bosKutu('fa-mug-hot', 'Bugün izinde olan yok')) + '</div>';
            var yak = o.yaklasan ? '<div class="ikm-kart" data-w="kutlama"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-cake-candles"></i> Yaklaşan kutlamalar</h3></div>' + (o.yaklasan.length ? '<div class="ikm-liste">' + o.yaklasan.map(function (k) {
                return '<button type="button" class="ikm-satir" data-kisi="' + k.id + '">' + av(k.ad, k.renk, k.resim) + '<span class="ikm-satir-ic"><b>' + esc(k.ad) + '</b><small>' + (k.tip === 'dogum' ? '🎂 Doğum günü' : '🎉 ' + k.yil + '. iş yılı') + ' · ' + tarihKisa(k.tarih) + '</small></span><span class="ikm-cip' + (k.kalan === 0 ? ' yesil' : '') + '">' + (k.kalan === 0 ? 'Bugün' : k.kalan + ' gün') + '</span></button>';
            }).join('') + '</div>' : bosKutu('fa-champagne-glasses', 'Önümüzdeki 30 günde kutlama yok')) + '</div>' : '';
            var belgeler = o.biten_belgeler ? '<div class="ikm-kart" data-w="belge"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-file-circle-exclamation"></i> Süresi dolacak belgeler</h3></div>' + (o.biten_belgeler.length ? '<div class="ikm-liste">' + o.biten_belgeler.map(function (b) {
                return '<button type="button" class="ikm-satir" data-kisi="' + b.kid + '" data-kisi-sekme="belgeler"><span class="ikm-satir-ikon"><i class="fa-regular fa-file-lines"></i></span><span class="ikm-satir-ic"><b>' + esc(b.ad) + '</b><small>' + esc(b.kisi) + ' · ' + tarih(b.gecerlilik) + '</small></span><span class="ikm-cip ' + (b.kalan <= 7 ? 'kirmizi' : 'turuncu') + '">' + (b.kalan < 0 ? 'doldu' : b.kalan + ' gün') + '</span></button>';
            }).join('') + '</div>' : bosKutu('fa-circle-check', '30 gün içinde süresi dolacak belge yok')) + '</div>' : '';
            var dep = o.departmanlar ? (function () {
                var enc = Math.max.apply(null, o.departmanlar.map(function (d) { return d.n; }).concat([1]));
                return '<div class="ikm-kart" data-w="birim"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-sitemap"></i> Birimlere göre personel</h3></div>' + o.departmanlar.map(function (d) {
                    return '<div class="ikm-cubuk-satir" title="' + esc(d.ad) + ': ' + d.n + ' kişi"><span>' + esc(d.ad) + '</span><div><i style="width:' + (d.n / enc * 100) + '%"></i></div><b>' + d.n + '</b></div>';
                }).join('') + '</div>';
            })() : '';
            var ac = function (id) { return W[id] !== false; };
            /* Ana sayfa: kart panosu (sütunlara akan kartlar; kenarlar boş kalmaz) */
            var DYK = istek('duyuru_islem.php');
            Promise.all([
                getir({ islem: 'pano' }).catch(function () { return null; }),
                getir({ islem: 'personel', id: V.ben }).catch(function () { return null; }),
                DYK.getir({ islem: 'duyurular' }).catch(function () { return null; }),
                getir({ islem: 'panel_ek' }).catch(function () { return null; })
            ]).then(function (x) {
                if (!icerik.isConnected) return;
                var pano = x[0] || {}, kisi = x[1] && x[1].kart ? x[1] : null, duy = x[2] && x[2].liste ? x[2].liste : null, pek = x[3] || {};
                var kapali = (V && V.menu_kapali) || [], ilkAd = ((V.ben_ad || '').split(' ')[0] || '').toLocaleUpperCase('tr-TR');
                var kart = function (id, ikon, baslik, govde, alt, renk) { return '<section class="pn-kart" data-w="' + id + '"><header class="pn-bas"><span class="pn-ikon" style="--pc:' + (renk || 'var(--ik-vurgu)') + '"><i class="fa-solid ' + ikon + '"></i></span><h3>' + baslik + '</h3></header><div class="pn-govde">' + govde + '</div>' + (alt || '') + '</section>'; };
                var tumu = function (git, ad) { return '<footer class="pn-alt"><button type="button" class="pn-link" data-git="' + git + '">' + (ad || 'Tümünü Görün') + ' <i class="fa-solid fa-angle-right"></i></button></footer>'; };
                var bos = function (m) { return '<p class="pn-bos">' + m + '</p>'; };
                var satir = function (k, ara, sag) { return '<button type="button" class="pn-satir"' + (k.id ? ' data-kisi="' + k.id + '"' : '') + '>' + av(k.ad, k.renk, k.resim) + '<span class="pn-ad">' + esc(k.ad) + '</span>' + (ara ? '<span class="pn-ara">' + ara + '</span>' : '') + '<span class="pn-sag">' + (sag || '') + '</span></button>'; };
                var gk = function (t) { return tarihKisa(t); };
                var W2 = [];
                // 1) Profil
                if (kisi) {
                    var K = kisi.kart; var sr = K.resim ? '<img src="' + esc(K.resim) + '" alt="">' : '<span style="background:' + esc(K.renk || '#6366f1') + '">' + esc((K.ad || '?').split(' ').map(function (w) { return w.charAt(0); }).slice(0, 2).join('').toLocaleUpperCase('tr-TR')) + '</span>';
                    W2.push(['profil', '<section class="pn-kart pn-profil" data-w="profil"><div class="pn-p-ust"><div><h3>' + esc((K.ad || '').toLocaleUpperCase('tr-TR')) + '</h3><p>' + esc(K.unvan || K.gorev || '') + '</p></div><div class="pn-foto">' + sr + '</div></div>' +
                        '<dl class="pn-dl"><div><dt>Yöneticim</dt><dd>' + (K.yonetici_adi ? '<a data-kisi="' + K.yonetici_id + '">' + esc(K.yonetici_adi.toLocaleUpperCase('tr-TR')) + '</a>' : '—') + '</dd></div><div><dt>İşe Başlama Tarihi</dt><dd>' + (K.ise_giris ? tarih(K.ise_giris) : '—') + '</dd></div></dl>' + tumu('kartim', 'Hesabım') + '</section>']);
                }
                // 2) İzin bilgilerim
                (function () {
                    var son = kisi ? (kisi.izinler || []).filter(function (t) { return t.durum === 'onaylandi' || t.durum === 'beklemede'; }).slice(0, 5) : [];
                    var oran = hak > 0 ? Math.min(100, kull / hak * 100) : 0;
                    var govde = hak == null ? bos('İşe giriş tarihiniz girilmediği için yıllık izin hakkı hesaplanamıyor. İK ile iletişime geçin.') :
                        '<p class="pn-soluk">' + (iz.yil || '') + ' dönemi yıllık izin' + (iz.devir ? ' · <b>' + sayi(iz.devir) + ' gün devreden</b> + ' + sayi(iz.hak) + ' gün hak' : '') + '</p><div class="pn-buyuk">' + sayi(iz.kalan) + ' <small>gün</small></div><div class="pn-bar"><i class="y" style="width:' + (100 - oran) + '%"></i><i class="k" style="width:' + oran + '%"></i></div>' +
                        '<div class="pn-lej"><span><i style="background:#f59e0b"></i>Onay bekleyen <b>' + sayi(bek) + '</b></span><span><i style="background:#ef4444"></i>Kullanılan <b>' + sayi(kull) + '</b></span></div>';
                    var liste = son.length ? '<ul class="pn-liste pn-izin">' + son.map(function (t) { return '<li><i class="fa-solid ' + (t.durum === 'onaylandi' ? 'fa-circle-check ok' : 'fa-hourglass-half bk') + '"></i><span>' + esc(t.tur_ad) + '</span><em>' + sayi(t.gun) + 'g</em><b>' + gk(t.baslangic) + '</b></li>'; }).join('') + '</ul>' : '';
                    W2.push(['bakiye', kart('bakiye', 'fa-umbrella-beach', 'İzin bilgilerim', govde + liste, tumu('izinler'), '#2563eb')]);
                })();
                // 3) Çalışan dağılımı
                if (o.departmanlar && o.departmanlar.length) {
                    var top = o.departmanlar.reduce(function (t, d) { return t + d.n; }, 0) || 1, acc = 0, R = 15.9155, kol = ['#4f6bd6', '#8fd16b', '#fbc34a', '#ef6a6a', '#6ec6e8', '#3aa876', '#f59e6b', '#b07be0', '#e87ab8', '#64748b'];
                    var halka = o.departmanlar.map(function (d, i) { var pp = d.n / top * 100, c = '<circle r="' + R + '" cx="21" cy="21" fill="none" stroke="' + kol[i % kol.length] + '" stroke-width="6" stroke-dasharray="' + pp + ' ' + (100 - pp) + '" stroke-dashoffset="' + (25 - acc) + '"></circle>'; acc += pp; return c; }).join('');
                    W2.push(['birim', kart('birim', 'fa-chart-pie', 'Çalışan dağılımı', '<div class="pn-donut"><svg viewBox="0 0 42 42" role="img" aria-label="Birim dağılımı">' + halka + '<text x="21" y="23" text-anchor="middle" font-size="7.5" font-weight="700" fill="currentColor">' + top + '</text></svg></div>' +
                        '<ul class="pn-lej-liste">' + o.departmanlar.slice(0, 8).map(function (d, i) { return '<li><i style="background:' + kol[i % kol.length] + '"></i><span>' + esc(d.ad) + '</span><b>' + d.n + '</b></li>'; }).join('') + '</ul>', '<footer class="pn-alt pn-top"><i class="fa-solid fa-users"></i> <b>' + (o.personel != null ? o.personel : top) + '</b> aktif personel</footer>', '#4f6bd6')]);
                }
                // 4) Resmi tatiller
                W2.push(['tatil', kart('tatil', 'fa-umbrella-beach', 'Resmi tatiller', (pano.tatiller || []).length ? '<ul class="pn-liste">' + pano.tatiller.slice(0, 5).map(function (t) { return '<li><span>' + esc(t.ad) + '</span><em>' + (t.kalan === 0 ? 'Bugün' : t.kalan + ' gün sonra') + '</em><b>' + gk(t.tarih) + '</b></li>'; }).join('') + '</ul>' : bos('Yaklaşan resmi tatil yok.'), '', '#1e6fa8')]);
                // 5) Etkinlik takvimi
                if (kapali.indexOf('izin_takvimi') < 0) W2.push(['takvim', kart('takvim', 'fa-calendar-days', 'Etkinlik takvimi', (pek.notlar || []).length ? '<ul class="pn-liste">' + pek.notlar.slice(0, 6).map(function (n) { return '<li data-git="izin_takvimi" class="tik"><span>' + esc(n.baslik) + '</span><b>' + gk(n.tarih) + (n.saat ? ' · ' + esc(n.saat) : '') + '</b></li>'; }).join('') + '</ul>' : bos('Yaklaşan şirket etkinliği bulunmuyor.'), tumu('izin_takvimi', 'Takvimi Aç'), '#64748b')]);
                // 6) Yaklaşan doğum günleri
                W2.push(['kutlama', kart('kutlama', 'fa-cake-candles', 'Yaklaşan doğum günleri', (pano.dogum || []).length ? '<ul class="pn-liste pn-kisi">' + pano.dogum.slice(0, 6).map(function (d) { return '<li>' + satir(d, '', '<b>' + (d.kalan === 0 ? 'Bugün' : gk(d.tarih)) + '</b>') + '</li>'; }).join('') + '</ul>' : bos('Önümüzdeki 30 günde doğum günü yok.'), '', '#e0457b')]);
                // 7) Yaklaşan izinler (şu an izinde + yakında çıkacaklar)
                (function () {
                    var iz1 = (pano.izinde || []).map(function (k) { return satir(k, '<em>' + sayi(k.gun || k.kalan_gun || 0) + 'g</em>', '<b class="yesil">' + (k.kalan_gun > 1 ? 'Dönüş ' + gk(k.donus) : 'Son gün') + '</b>'); });
                    var iz2 = (pano.yaklasan_izin || []).map(function (k) { return satir(k, '<em>' + sayi(k.gun) + 'g</em>', '<b>' + gk(k.baslangic) + '</b>'); });
                    var hepsi = iz1.concat(iz2);
                    W2.push(['izinli', kart('izinli', 'fa-plane-departure', 'Yaklaşan izinler', hepsi.length ? '<ul class="pn-liste pn-kisi">' + hepsi.slice(0, 7).map(function (h) { return '<li>' + h + '</li>'; }).join('') + '</ul>' : bos('Şu an izinde olan ya da yakında izne çıkacak kimse yok.'), tumu('izin_takvimi'), '#334e8a')]);
                })();
                // 8) Onay bekleyenler: izin / masraf / avans ayrı kartlar
                (function () {
                    var izL = (bana && bana.liste) || [], mL = (bek2 && bek2.masraf) || [], aL = (bek2 && bek2.avans) || [], onayci = !!(V.yetki && (V.yetki.ik_masraf_onay || V.yetki.ik_avans_onay)) || izL.length > 0;
                    if (!onayci) return;
                    var kisa = function (k, bilgi, tur) { return '<div class="ikm-satir sabit">' + av(k.ad, k.renk, k.resim) + '<span class="ikm-satir-ic"><b>' + esc(k.ad) + '</b><small>' + esc(bilgi) + '</small></span><span class="ikm-tutar">' + para(k.tutar) + '</span><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-yesil" data-g-karar="' + tur + ':' + k.id + ':onay">Onayla</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-g-karar="' + tur + ':' + k.id + ':red">Reddet</button></div>'; };
                    var rozet = function (n) { return n ? ' <span class="ikm-cip turuncu">' + n + '</span>' : ''; };
                    W2.push(['onay', kart('onay', 'fa-plane-circle-check', 'İzin talepleri' + rozet(izL.length), izL.length ? '<div class="ikm-liste">' + izL.slice(0, 4).map(function (t) { return izinSatir(t, 'kisa'); }).join('') + '</div>' : bos('Onayınızı bekleyen bir talep bulunmuyor.'), izL.length ? tumu('izinler-bana') : '', '#6366f1')]);
                    if (V.yetki && V.yetki.ik_masraf_onay) W2.push(['onay', kart('onay-m', 'fa-wallet', 'Masraf talepleri' + rozet(bek2 ? bek2.masraf_say : 0), mL.length ? '<div class="ikm-liste">' + mL.map(function (k) { return kisa(k, k.bilgi + ' · ' + tarih(k.tarih), 'masraf'); }).join('') + '</div>' : bos('Onayınızı bekleyen bir talep bulunmuyor.'), mL.length ? tumu('masraflar') : '', '#0f9d6a')]);
                    if (V.yetki && V.yetki.ik_avans_onay) W2.push(['onay', kart('onay-a', 'fa-hand-holding-dollar', 'Avans talepleri' + rozet(bek2 ? bek2.avans_say : 0), aL.length ? '<div class="ikm-liste">' + aL.map(function (k) { return kisa(k, k.bilgi + (k.tarih ? ' · ' + tarih(k.tarih) : ''), 'avans'); }).join('') + '</div>' : bos('Onayınızı bekleyen bir talep bulunmuyor.'), aL.length ? tumu('avanslar') : '', '#e08a1e')]);
                })();
                // 9) Süresi dolacak belgeler
                if (o.biten_belgeler) W2.push(['belge', kart('belge', 'fa-file-circle-exclamation', 'Süresi dolacak belgeler', o.biten_belgeler.length ? '<ul class="pn-liste">' + o.biten_belgeler.slice(0, 6).map(function (b) { return '<li class="tik" data-kisi="' + b.kid + '" data-kisi-sekme="belgeler"><span>' + esc(b.ad) + '<small>' + esc(b.kisi) + '</small></span><b class="' + (b.kalan <= 7 ? 'kirmizi' : 'turuncu') + '">' + (b.kalan < 0 ? 'doldu' : b.kalan + ' gün') + '</b></li>'; }).join('') + '</ul>' : bos('30 gün içinde süresi dolacak belge yok.'), '', '#dc2626')]);
                // 10) Yeni ne var (duyurular)
                if (duy) W2.push(['duyuru', kart('duyuru', 'fa-bullhorn', 'Duyurular', duy.length ? '<div class="pn-dyler">' + duy.slice(0, 5).map(function (d) {
                    var t = d.tam || d.ozet || ''; return '<article class="pn-dy' + (d.okundu ? '' : ' yeni') + '"><div class="pn-dy-ust"><b>' + esc(d.baslik) + '</b>' + (d.okundu ? '' : '<i class="pn-dy-nokta" title="Okunmadı"></i>') + '</div>' +
                        (t ? '<div class="pn-dy-metin">' + esc(t) + '</div>' : '') + '<div class="pn-dy-alt"><em>' + tarihKisa(d.zaman) + (d.yazar ? ' · ' + esc(d.yazar) : '') + '</em><span>' + (t.length > 110 ? '<button type="button" class="pn-dy-ac" data-pn-ac><span>Devamı</span> <i class="fa-solid fa-chevron-down"></i></button>' : '') + '<button type="button" class="pn-dy-detay" data-duyuru="' + d.id + '">Detay</button></span></div></article>';
                }).join('') + '</div>' : bos('Henüz duyuru yok.'), tumu('duyurular', 'Tüm duyurular'), '#f43f5e')]);
                var ozetListe = [['profil', 'Profil kartım', !!kisi], ['bakiye', 'İzin bilgilerim', true], ['birim', 'Çalışan dağılımı', !!(o.departmanlar && o.departmanlar.length)], ['tatil', 'Resmi tatiller', true], ['takvim', 'Etkinlik takvimi', kapali.indexOf('izin_takvimi') < 0], ['kutlama', 'Yaklaşan doğum günleri', true], ['izinli', 'Yaklaşan izinler', true], ['onay', 'Onay bekleyen talepler', W2.some(function (q) { return q[0] === 'onay'; })], ['belge', 'Süresi dolacak belgeler', !!o.biten_belgeler], ['duyuru', 'Yeni ne var? (duyurular)', !!duy]];
                icerik.innerHTML = '<div class="pn-ust"><div><small>Merhaba ' + esc(ilkAd) + '!</small><h2>Bugün <b>' + esc(gun.replace(/^(\d+ \S+ \d+) (\S+)$/, '$1, $2')) + '</b></h2></div><div class="pn-ust-btn"><button type="button" class="ikm-btn" data-panel-ozel title="Panelde görünecek kartları seçin"><i class="fa-solid fa-sliders"></i> <span>Paneli özelleştir</span></button></div></div>' +
                    '<div class="pn-pano">' + W2.filter(function (q) { return ac(q[0]); }).map(function (q) { return q[1]; }).join('') + '</div>';
                rozetGuncelle();
                var ozelBtn = $('[data-panel-ozel]', icerik); if (ozelBtn) ozelBtn.onclick = function () { panelOzellestir(ozetListe.filter(function (q) { return q[2]; }), W, genelCiz); };
                $$('[data-pn-ac]', icerik).forEach(function (b) { b.onclick = function (ev) { ev.stopPropagation(); var a = b.closest('.pn-dy').classList.toggle('acik'); b.querySelector('span').textContent = a ? 'Daralt' : 'Devamı'; }; });
                $$('[data-duyuru]', icerik).forEach(function (el) { el.onclick = function () { if (window.IKM.duyuruAc) window.IKM.duyuruAc(+el.dataset.duyuru); else sekmeAc('duyurular'); }; });
                $$('[data-g-karar]', icerik).forEach(function (b) {
                    b.onclick = function (ev) {
                        ev.stopPropagation(); var q = b.dataset.gKarar.split(':'), tur = q[0], id = q[1], k = q[2], ad = tur === 'masraf' ? 'Masraf' : 'Avans';
                        var yap = function (not) { return gonder(tur + '_karar', { id: id, karar: k, not: not || '' }).then(function () { bildir(ad + (k === 'onay' ? ' onaylandı.' : ' reddedildi.'), 'basari'); genelCiz(); }); };
                        if (k === 'onay') yap().catch(function (er) { bildir(er.message, 'hata'); });
                        else soru({ baslik: ad + ' reddedilsin mi?', etiket: 'Reddetme nedeni', zorunlu: true, evet: 'Reddet', tur: 'tehlike', ikon: 'fa-ban' }).then(function (n) { if (n) return yap(n); }).catch(function (er) { bildir(er.message, 'hata'); });
                    };
                });
            });
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    icerik.addEventListener('click', function (e) {
        var g = e.target.closest('[data-git]');
        if (g) { var h = g.dataset.git; if (h === 'kartim') kartAc(V.ben); else if (h === 'izinler-bekleyen') { izinF.kapsam = 'tum'; izinF.durum = 'beklemede'; sekmeAc('izinler'); } else if (h === 'izinler-bana') { izinF.kapsam = 'bana'; izinF.durum = ''; sekmeAc('izinler'); } else sekmeAc(h); return; }
        if (e.target.closest('[data-yeni-izin]')) { talepOlustur(); return; }
        var ik = e.target.closest('[data-izin-ac]'); if (ik && !e.target.closest('button[data-izin-onay], button[data-izin-red], button[data-izin-iptal], a[href*=izin_pdf]')) { izinDetay(+ik.dataset.izinAc); return; }
        var k = e.target.closest('[data-kisi]'); if (k) kartAc(+k.dataset.kisi, k.dataset.kisiSekme);
    });
    kayit('genel', 'İK paneli', 'Personel kartları, izinler, belgeler ve zimmetler tek yerde.', genelCiz);

    /* ======================= BİRİMLER ======================= */
    var BIRIM = [];
    function birimlerCiz() {
        icerik.innerHTML = yukleniyor();
        getir({ islem: 'birimler' }).then(function (j) {
            BIRIM = j.liste;
            var duz = !!(Y && Y.ik_duzenle);
            var ust = '<div class="ikm-arac"><span class="ikm-soluk kucuk">' + BIRIM.length + ' birim</span>' + (duz ? '<button type="button" class="ikm-btn ikm-btn-ana" id="ik-b-yeni" style="margin-left:auto"><i class="fa-solid fa-plus"></i> Yeni birim</button>' : '') + '</div>';
            var gövde = BIRIM.length ? '<div class="ikm-birimler">' + BIRIM.map(function (b, i) {
                return '<button type="button" class="ikm-birim-kart" data-i="' + i + '"><div class="ikm-birim-ust"><span class="ikm-birim-ikon"><i class="fa-solid fa-sitemap"></i></span><div><b>' + esc(b.ad) + '</b><small>' + b.n + ' kişi</small></div>' + ((b.mudurler || []).length ? '<span class="ikm-amirler">' + b.mudurler.slice(0, 3).map(function (u) { return '<span class="ikm-amir" data-ad="' + esc(u.ad) + ' · ' + esc(b.ad) + ' amiri" title="' + esc(u.ad) + ' (birim amiri)">' + av(u.ad, u.renk, u.resim) + '</span>'; }).join('') + '</span>' : '') + '</div>' +
                    '<div class="ikm-birim-mudur' + ((b.mudurler || []).length ? '' : ' yok') + '"><i class="fa-solid fa-user-tie"></i> ' + ((b.mudurler || []).length ? 'Müdür: <b>' + (b.mudurler || []).map(function (u) { return esc(u.ad); }).join(', ') + '</b>' : 'Müdür atanmadı') + '</div>' +
                    '<div class="ikm-birim-uyeler">' + (b.uyeler || []).map(function (u) { return av(u.ad, u.renk, u.resim); }).join('') + (b.n > 4 ? '<span class="ikm-cip">+' + (b.n - 4) + '</span>' : '') + (b.n === 0 ? '<span class="ikm-soluk kucuk">Henüz kimse yok</span>' : '') + '</div></button>';
            }).join('') + '</div>' : bosKutu('fa-sitemap', 'Henüz birim yok', duz ? '“Yeni birim” ile ilk birimi oluşturun; sonra personel ekleyin.' : '');
            icerik.innerHTML = ust + gövde;
            $$('.ikm-birim-kart', icerik).forEach(function (k) { k.onclick = function () { birimAc(BIRIM[+k.dataset.i]); }; });
            if ($('#ik-b-yeni')) $('#ik-b-yeni').onclick = function () {
                soru({ baslik: 'Yeni birim', etiket: 'Birim adı', yer: 'Örn. Muhasebe', zorunlu: true, evet: 'Oluştur', ikon: 'fa-sitemap' }).then(function (ad) { if (ad) return gonder('birim_olustur', { ad: ad }).then(function () { bildir('Birim oluşturuldu.', 'basari'); birimlerCiz(); }); }).catch(function (e) { bildir(e.message, 'hata'); });
            };
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    function birimAc(b) {
        var duz = !!(Y && Y.ik_duzenle);
        var m = modal({ baslik: esc(b.ad), ikon: 'fa-sitemap', gen: 620, govde: '<div id="ik-bu-ic">' + yukleniyor() + '</div>',
            alt: (duz ? '<button type="button" class="ikm-btn" id="ik-bu-ad"><i class="fa-solid fa-pen"></i> Adı değiştir</button><button type="button" class="ikm-btn ikm-btn-tehlike" id="ik-bu-sil"><i class="fa-solid fa-trash"></i> Birimi sil</button>' : '') + '<button type="button" class="ikm-btn" data-kapat style="margin-left:auto">Kapat</button>' });
        var ic = $('#ik-bu-ic', m.el);
        function yukle() {
            Promise.all([getir({ islem: 'birim_uyeler', birim: b.ad }), getir({ islem: 'birimler' }).catch(function () { return null; })]).then(function (sn) {
                var j = sn[0], bl = sn[1];
                if (bl) { var eş = bl.liste.filter(function (x) { return x.ad.toLocaleLowerCase('tr-TR') === b.ad.toLocaleLowerCase('tr-TR'); })[0]; if (eş) b.mudurler = eş.mudurler || []; }
                var md = b.mudurler || [];
                ic.innerHTML = '<div class="ikm-mudur-kutu"><div class="ikm-mudur-ust"><b><i class="fa-solid fa-user-tie"></i> Birim müdürü</b>' + (duz ? '<button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-bu-mudur"><i class="fa-solid fa-magnifying-glass"></i> ' + (md.length ? 'Müdürü değiştir' : 'Müdür ata') + '</button>' : '') + '</div>' +
                    (md.length ? '<div class="ikm-mudur-liste">' + md.map(function (u) { return '<span class="ikm-mudur-cip">' + av(u.ad, u.renk, u.resim) + '<b>' + esc(u.ad) + '</b></span>'; }).join('') + '</div>' : '<p class="ikm-soluk kucuk" style="margin:6px 0 0">Bu birime henüz müdür atanmadı.' + (duz ? ' “Müdür ata” ile isim arayarak seçin.' : '') + '</p>') + '</div>' +
                    '<div class="ikm-arac" style="margin-bottom:10px"><b>' + j.liste.length + ' kişi</b>' + (duz ? '<button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" id="ik-bu-ekle" style="margin-left:auto"><i class="fa-solid fa-user-plus"></i> Personel ekle</button>' : '') + '</div>' +
                    (j.liste.length ? '<div class="ikm-liste">' + j.liste.map(function (u) { return '<div class="ikm-satir sabit">' + av(u.ad, u.renk, u.resim) + '<span class="ikm-satir-ic"><b>' + esc(u.ad) + '</b><small>' + esc(u.unvan || '') + '</small></span>' + (duz ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-cikar="' + u.id + '" title="Birimden çıkar"><i class="fa-solid fa-user-minus"></i></button>' : '') + '</div>'; }).join('') + '</div>' : bosKutu('fa-user-group', 'Bu birimde kimse yok', duz ? '“Personel ekle” ile ekleyin.' : ''));
                if ($('#ik-bu-mudur', ic)) $('#ik-bu-mudur', ic).onclick = function () { mudurSec(b, md.map(function (u) { return u.id; }), j.liste.map(function (u) { return u.id; }), function () { yukle(); ilkVeri().then(birimlerCiz); }); };
                if ($('#ik-bu-ekle', ic)) $('#ik-bu-ekle', ic).onclick = function () { personelEkle(b, j.liste.map(function (u) { return u.id; }), function () { yukle(); birimlerCiz(); }); };
                $$('[data-cikar]', ic).forEach(function (x) { x.onclick = function () { gonder('birim_ata', { ids: JSON.stringify([+x.dataset.cikar]), birim: '' }).then(function () { bildir('Birimden çıkarıldı.', 'basari'); yukle(); ilkVeri().then(birimlerCiz); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
            }).catch(function (e) { ic.innerHTML = hataKutu(e.message); });
        }
        yukle();
        if (duz) {
            $('#ik-bu-ad', m.el).onclick = function () { soru({ baslik: 'Birim adını değiştir', etiket: 'Yeni ad', deger: b.ad, zorunlu: true, evet: 'Kaydet', ikon: 'fa-pen' }).then(function (ad) { if (ad) return gonder('birim_adlandir', { eski: b.ad, yeni: ad }).then(function () { m.kapat(); bildir('Birim adı güncellendi.', 'basari'); ilkVeri().then(birimlerCiz); }); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            $('#ik-bu-sil', m.el).onclick = function () { onayla({ baslik: 'Birim silinsin mi?', mesaj: '“' + b.ad + '” birimi listeden kaldırılır. İçinde personel varsa silinemez.', evet: 'Sil', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('birim_sil', { ad: b.ad }).then(function () { m.kapat(); bildir('Birim silindi.', 'basari'); birimlerCiz(); }); }).catch(function (e) { bildir(e.message, 'hata'); }); };
        }
    }
    function mudurSec(b, mevcutMudur, uyeIds, bitti) {
        var kisiler = (V.kisiler || []).slice().sort(function (x, y) { var a1 = uyeIds.indexOf(x.id) >= 0 ? 0 : 1, b1 = uyeIds.indexOf(y.id) >= 0 ? 0 : 1; return a1 - b1 || x.ad.localeCompare(y.ad, 'tr'); }), sec = {};
        mevcutMudur.forEach(function (id) { sec[id] = 1; });
        var m = modal({ baslik: '“' + esc(b.ad) + '” birim müdürü', ikon: 'fa-user-tie', gen: 560, govde: '<p class="ikm-soluk kucuk" style="margin:0 0 10px">İsim yazarak arayın. Bu birimin üyeleri listenin başında görünür; başka birimden biri de müdür olabilir.</p><div class="ikm-ara" style="margin-bottom:10px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="ik-mu-ara" placeholder="Müdür olacak kişinin adını yazın…" autocomplete="off"></div><div class="ikm-pe-liste" id="ik-mu-liste"></div>',
            alt: '<span class="ikm-soluk" id="ik-mu-say" style="margin-right:auto"></span><button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-mu-ok"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        var liste = $('#ik-mu-liste', m.el);
        function say() { var n = Object.keys(sec).length; $('#ik-mu-say', m.el).textContent = n ? n + ' müdür seçili' : 'Müdür seçilmedi (boş bırakılırsa kaldırılır)'; }
        function ciz() {
            var a = ($('#ik-mu-ara', m.el).value || '').toLocaleLowerCase('tr-TR');
            liste.innerHTML = kisiler.filter(function (k) { return !a || k.ad.toLocaleLowerCase('tr-TR').indexOf(a) >= 0 || (k.departman || '').toLocaleLowerCase('tr-TR').indexOf(a) >= 0; }).map(function (k) {
                var uye = uyeIds.indexOf(k.id) >= 0;
                return '<label class="ikm-pe-satir"><input type="checkbox" value="' + k.id + '"' + (sec[k.id] ? ' checked' : '') + '><i></i>' + av(k.ad, k.renk, k.resim) + '<span><b>' + esc(k.ad) + '</b><small>' + esc(uye ? 'Bu birimin üyesi' : (k.departman ? 'Birimi: ' + k.departman : 'Birimi yok')) + '</small></span></label>';
            }).join('') || '<div class="ikm-soluk">Eşleşen kişi yok.</div>';
            $$('input', liste).forEach(function (c) { c.onchange = function () { if (c.checked) sec[c.value] = 1; else delete sec[c.value]; say(); }; });
        }
        ciz(); say(); $('#ik-mu-ara', m.el).oninput = ciz; setTimeout(function () { var i = $('#ik-mu-ara', m.el); if (i) i.focus(); }, 60);
        $('#ik-mu-ok', m.el).onclick = function () {
            var d = this; d.disabled = true;
            gonder('birim_mudur', { birim: b.ad, ids: JSON.stringify(Object.keys(sec).map(Number)) }).then(function (r) { m.kapat(); bildir((r.n ? 'Birim müdürü kaydedildi.' : 'Birim müdürü kaldırıldı.') + (r.yonetici_guncel ? ' ' + r.yonetici_guncel + ' kişinin yöneticisi güncellendi.' : ''), 'basari'); bitti(); }).catch(function (e) { d.disabled = false; bildir(e.message, 'hata'); });
        };
    }
    function personelEkle(b, mevcut, bitti) {
        var kisiler = (V.kisiler || []).filter(function (k) { return mevcut.indexOf(k.id) < 0; }), sec = {};
        var m = modal({ baslik: '“' + esc(b.ad) + '” birimine personel ekle', ikon: 'fa-user-plus', gen: 560, govde: '<div class="ikm-ara" style="margin-bottom:10px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="ik-pe-ara" placeholder="İsim ara…"></div><div class="ikm-pe-liste" id="ik-pe-liste"></div>',
            alt: '<span class="ikm-soluk" id="ik-pe-say" style="margin-right:auto"></span><button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-pe-ok" disabled><i class="fa-solid fa-check"></i> Ekle</button>' });
        var liste = $('#ik-pe-liste', m.el);
        function ciz() {
            var a = ($('#ik-pe-ara', m.el).value || '').toLocaleLowerCase('tr-TR');
            liste.innerHTML = kisiler.filter(function (k) { return !a || k.ad.toLocaleLowerCase('tr-TR').indexOf(a) >= 0; }).map(function (k) {
                return '<label class="ikm-pe-satir"><input type="checkbox" value="' + k.id + '"' + (sec[k.id] ? ' checked' : '') + '><i></i>' + av(k.ad, k.renk, k.resim) + '<span><b>' + esc(k.ad) + '</b><small>' + esc(k.departman ? 'Şu an: ' + k.departman : 'Birimi yok') + '</small></span></label>';
            }).join('') || '<div class="ikm-soluk">Eklenebilecek kişi yok.</div>';
            $$('input', liste).forEach(function (c) { c.onchange = function () { if (c.checked) sec[c.value] = 1; else delete sec[c.value]; var n = Object.keys(sec).length; $('#ik-pe-say', m.el).textContent = n ? n + ' kişi seçili' : ''; $('#ik-pe-ok', m.el).disabled = !n; }; });
        }
        ciz(); $('#ik-pe-ara', m.el).oninput = ciz;
        $('#ik-pe-ok', m.el).onclick = function () {
            var d = this; d.disabled = true;
            gonder('birim_ata', { ids: JSON.stringify(Object.keys(sec).map(Number)), birim: b.ad }).then(function (r) { m.kapat(); bildir(r.n + ' kişi eklendi.', 'basari'); ilkVeri().then(bitti); }).catch(function (e) { d.disabled = false; bildir(e.message, 'hata'); });
        };
    }
    kayit('birimler', 'Şirket yapısı', 'Departmanları ve unvanları oluşturun; personele buradan atayın.', birimlerCiz);

    /* ======================= PERSONEL ======================= */
    var pF = { ara: '', departman: '', durum: 'aktif' };
    function personelCiz() {
        icerik.innerHTML = '<div class="ikm-arac"><div class="ikm-ara"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="ik-p-ara" placeholder="İsim, unvan, telefon, e-posta…" value="' + esc(pF.ara) + '"></div>' +
            '<select id="ik-p-dep"><option value="">Tüm birimler</option>' + ((V && V.ozet.departmanlar) || []).map(function (d) { return '<option' + (d.ad === pF.departman ? ' selected' : '') + '>' + esc(d.ad) + '</option>'; }).join('') + '</select>' +
            '<select id="ik-p-durum"><option value="aktif">Çalışanlar</option><option value="ayrilan">Ayrılanlar</option><option value="">Hepsi</option></select>' +
            '<span class="ikm-soluk kucuk" id="ik-p-say" style="margin-left:auto"></span>' + (V && V.yetki && V.yetki.ik_duzenle ? '<button type="button" class="ikm-btn" id="ik-p-sec"><i class="fa-solid fa-list-check"></i> <span>Seç</span></button>' : '') + '</div><div class="ikm-secim-bar" id="ik-p-bar" hidden></div><div class="ikm-kisiler" id="ik-p-liste">' + yukleniyor() + '</div>';
        $('#ik-p-durum').value = pF.durum;
        var z; $('#ik-p-ara').addEventListener('input', function () { var v = this.value.trim(); clearTimeout(z); z = setTimeout(function () { pF.ara = v; personelYukle(); }, 250); });
        $('#ik-p-dep').addEventListener('change', function () { pF.departman = this.value; personelYukle(); });
        $('#ik-p-durum').addEventListener('change', function () { pF.durum = this.value; personelYukle(); });
        pSec.acik = false; pSec.ids = {};
        $('#ik-p-liste').addEventListener('click', function (e) {
            var k = e.target.closest('[data-kisi]'); if (!k) return;
            if (pSec.acik) { e.stopPropagation(); var id = k.dataset.kisi; if (pSec.ids[id]) delete pSec.ids[id]; else pSec.ids[id] = 1; k.classList.toggle('secili', !!pSec.ids[id]); secimBar(); return; }
            kartAc(+k.dataset.kisi);
        });
        if ($('#ik-p-sec')) $('#ik-p-sec').onclick = function () { pSec.acik = !pSec.acik; pSec.ids = {}; $('#ik-p-liste').classList.toggle('secim', pSec.acik); $('span', this).textContent = pSec.acik ? 'Seçimi bitir' : 'Seç'; $$('.ikm-kisi.secili', icerik).forEach(function (x) { x.classList.remove('secili'); }); secimBar(); };
        personelYukle();
    }
    var pSec = { acik: false, ids: {} };
    function secimBar() {
        var bar = $('#ik-p-bar'); if (!bar) return; var n = Object.keys(pSec.ids).length;
        if (!pSec.acik) { bar.hidden = true; return; }
        bar.hidden = false;
        bar.innerHTML = '<span><b>' + n + '</b> kişi seçili</span><button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-sb-hepsi">Listedekilerin hepsini seç</button><button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-sb-temizle"' + (n ? '' : ' disabled') + '>Temizle</button><button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" id="ik-sb-ata"' + (n ? '' : ' disabled') + '><i class="fa-solid fa-sitemap"></i> Birime ata</button>';
        $('#ik-sb-hepsi').onclick = function () { $$('#ik-p-liste [data-kisi]').forEach(function (k) { pSec.ids[k.dataset.kisi] = 1; k.classList.add('secili'); }); secimBar(); };
        $('#ik-sb-temizle').onclick = function () { pSec.ids = {}; $$('#ik-p-liste .secili').forEach(function (k) { k.classList.remove('secili'); }); secimBar(); };
        $('#ik-sb-ata').onclick = function () { birimAtaModal(Object.keys(pSec.ids)); };
    }
    function birimAtaModal(ids) {
        getir({ islem: 'birimler' }).then(function (j) {
            var m = modal({ baslik: ids.length + ' kişiyi birime ata', ikon: 'fa-sitemap', gen: 480, govde: '<div class="ikm-form"><label class="ikm-alan"><span>Birim</span><select id="ik-ba-sec"><option value="">— Birim seçin —</option>' + j.liste.map(function (b) { return '<option value="' + esc(b.ad) + '">' + esc(b.ad) + ' (' + b.n + ')</option>'; }).join('') + '<option value="__yeni">+ Yeni birim oluştur…</option><option value="__kaldir">Birimi kaldır (boş bırak)</option></select></label>' +
                '<label class="ikm-alan" id="ik-ba-yeni-k" hidden><span>Yeni birim adı</span><input id="ik-ba-yeni" maxlength="100" placeholder="Örn. Muhasebe"></label></div>',
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-ba-kaydet"><i class="fa-solid fa-check"></i> Ata</button>' });
            $('#ik-ba-sec', m.el).onchange = function () { $('#ik-ba-yeni-k', m.el).hidden = this.value !== '__yeni'; };
            $('#ik-ba-kaydet', m.el).onclick = function () {
                var s = $('#ik-ba-sec', m.el).value, b = s === '__yeni' ? $('#ik-ba-yeni', m.el).value.trim() : (s === '__kaldir' ? '' : s);
                if (!s || (s === '__yeni' && !b)) { bildir('Bir birim seçin ya da yeni birim adı yazın.', 'uyari'); return; }
                var d = this; d.disabled = true;
                gonder('birim_ata', { ids: JSON.stringify(ids), birim: b }).then(function (r) { m.kapat(); bildir(r.n + ' kişi ' + (b ? '“' + b + '” birimine atandı.' : 'birimden çıkarıldı.'), 'basari'); pSec.ids = {}; ilkVeri(true).then(function () { personelCiz(); }); }).catch(function (e) { d.disabled = false; bildir(e.message, 'hata'); });
            };
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }
    function personelYukle() {
        getir({ islem: 'personeller', ara: pF.ara, departman: pF.departman, durum: pF.durum }).then(function (j) {
            var l = $('#ik-p-liste'); if (!l) return;
            $('#ik-p-say').textContent = j.liste.length + ' kişi';
            l.innerHTML = j.liste.length ? j.liste.map(function (k) {
                return '<button type="button" class="ikm-kisi' + (k.ayrildi ? ' ayrildi' : '') + '" data-kisi="' + k.id + '"><div class="ikm-kisi-ust">' + av(k.ad, k.renk, k.resim, 'buyuk') + '<div><b>' + esc(k.ad) + '</b><small>' + esc([k.unvan, k.departman].filter(Boolean).join(' · ') || k.rol) + '</small></div></div>' +
                    '<div class="ikm-iletisim">' + (k.email ? '<span><i class="fa-regular fa-envelope"></i> ' + esc(k.email) + '</span>' : '') + '</div>' +
                    '<div class="ikm-kisi-alt">' + (k.izinde ? '<span class="ikm-cip yesil"><i class="fa-solid fa-umbrella-beach"></i> İzinde</span>' : '') + (k.ayrildi ? '<span class="ikm-cip gri">Ayrıldı</span>' : '') + '</div></button>';
            }).join('') : bosKutu('fa-user-slash', 'Kişi bulunamadı', 'Aramayı değiştirin.');
            $$('#ik-p-liste [data-kisi]').forEach(function (x) { if (pSec.ids[x.dataset.kisi]) x.classList.add('secili'); });
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }
    kayit('personel', 'Personel', 'Kartı açmak için bir kişiye tıklayın.', personelCiz);

    /* ======================= PERSONEL KARTI (çekmece) ======================= */
    var SECIM = {
        kan_grubu: ['', 'A Rh+', 'A Rh-', 'B Rh+', 'B Rh-', 'AB Rh+', 'AB Rh-', '0 Rh+', '0 Rh-'], medeni_durum: ['', 'Bekar', 'Evli', 'Boşanmış', 'Dul'], cinsiyet: ['', 'Kadın', 'Erkek'],
        egitim_durumu: ['', 'İlkokul', 'Ortaokul', 'Lise', 'Ön lisans', 'Lisans', 'Yüksek lisans', 'Doktora'], maas_tipi: ['net', 'brut']
    };
    var GRUPLAR = [
        { k: 'iletisim', ad: 'İletişim ve acil durum', ikon: 'fa-address-card', alanlar: [['telefon', 'Telefon', 'tel'], ['email', 'E-posta', 'email'], ['telefon2', 'Şahsi telefon', 'tel'], ['email2', 'Şahsi e-posta', 'email'], ['adres', 'Adres', 'textarea', 1], ['il', 'İl'], ['ilce', 'İlçe'],
            ['acil_kisi', 'Acil durumda aranacak kişi'], ['acil_yakinlik', 'Yakınlığı'], ['acil_telefon', 'Acil durum telefonu', 'tel'], ['kan_grubu', 'Kan grubu', 'select'], ['medeni_durum', 'Medeni durum', 'select'], ['cocuk_sayisi', 'Çocuk sayısı', 'number']] },
        { k: 'kisisel', ad: 'Kimlik bilgileri', ikon: 'fa-id-card', alanlar: [['tc_kimlik', 'T.C. kimlik no'], ['dogum_tarihi', 'Doğum tarihi', 'date'], ['dogum_yeri', 'Doğum yeri'], ['cinsiyet', 'Cinsiyet', 'select'], ['askerlik', 'Askerlik durumu'], ['anne_adi', 'Anne adı'], ['baba_adi', 'Baba adı']] },
        { k: 'is', ad: 'İş bilgileri', ikon: 'fa-briefcase', alanlar: [['unvan', 'Unvan / pozisyon', 'unvanlist'], ['departman', 'Birim (departman)', 'birimlist'], ['yonetici_id', 'Bağlı olduğu yönetici', 'kisi'], ['calisma_sekli', 'Çalışma şekli', 'calisma'],
            ['ise_giris', 'İşe giriş tarihi', 'date'], ['kariyer_baslangic', 'Kariyer başlangıç tarihi', 'date'], ['sgk_baslangic', 'SGK başlangıç tarihi', 'date'], ['sirket', 'Şirket'], ['sgk_no', 'SGK sicil no'], ['yillik_izin_hak', 'Yıllık izin hakkı (gün)', 'number'], ['isten_cikis', 'İşten çıkış tarihi', 'date'], ['cikis_nedeni', 'Çıkış nedeni', 'text', 1], ['notlar', 'İK notları (kişi göremez)', 'textarea', 1]] },
        { k: 'egitim', ad: 'Eğitim', ikon: 'fa-graduation-cap', alanlar: [['egitim_durumu', 'Eğitim durumu', 'select'], ['okul', 'Okul'], ['bolum', 'Bölüm'], ['mezuniyet_yili', 'Mezuniyet yılı', 'number']] },
        { k: 'mali', ad: 'Maaş ve banka', ikon: 'fa-wallet', alanlar: [['maas', 'Maaş', 'para'], ['maas_tipi', 'Net / brüt', 'select'], ['iban', 'IBAN'], ['banka', 'Banka']] }
    ];
    var cek = null;
    function cekmeceKapat() {
        if (!cek) return; var c = cek; cek = null; c.el.classList.add('cikis'); c.arka.classList.add('cikis');
        setTimeout(function () { c.arka.remove(); c.el.remove(); }, 200); document.removeEventListener('keydown', c.tus); document.body.classList.remove('ikm-kilit');
        urlYaz(function (p) { p.delete('personel'); });
    }
    function kartAc(id, sekme) {
        if (!cek) {
            var arka = document.createElement('div'); arka.className = 'ikm-cekmece-arka';
            var el = document.createElement('aside'); el.className = 'ikm-cekmece'; el.setAttribute('role', 'dialog'); el.setAttribute('aria-modal', 'true');
            el.innerHTML = '<div class="ikm-iskelet" style="padding:24px"><i></i><i></i><i></i></div>';
            var yan = document.querySelector('.ikm-yan'), yr = yan ? yan.getBoundingClientRect() : null;
            if (yr && window.innerWidth > 980) { var sol = Math.round(yr.right) + 'px', ust = Math.max(0, Math.round(yr.top)) + 'px'; [arka, el].forEach(function (x) { x.style.left = sol; x.style.top = ust; }); }
            document.body.appendChild(arka); document.body.appendChild(el); document.body.classList.add('ikm-kilit');
            var tus = function (e) { if (e.key === 'Escape' && !$('.ikm-arka')) cekmeceKapat(); };
            document.addEventListener('keydown', tus); arka.addEventListener('click', cekmeceKapat);
            cek = { arka: arka, el: el, tus: tus, sekme: sekme || 'bilgi' };
        } else if (sekme) cek.sekme = sekme;
        cek.id = id;
        return getir({ islem: 'personel', id: id }).then(function (j) { if (cek && cek.id === id) { cek.veri = j; kartCiz(); } })
            .catch(function (e) { bildir(e.message, 'hata'); cekmeceKapat(); });
    }
    function alanDeger(a, kart) {
        var v = kart[a[0]], tip = a[2];
        if (v == null || v === '') return '<b class="bos">—</b>';
        if (tip === 'date') return '<b>' + tarih(v) + '</b>';
        if (tip === 'para') return '<b>' + esc(para(v)) + '</b>';
        if (tip === 'kisi') return '<b>' + esc(kart.yonetici_adi || '#' + v) + '</b>';
        if (tip === 'calisma') return '<b>' + esc((V.calisma || {})[v] || v) + '</b>';
        if (a[0] === 'maas_tipi') return '<b>' + (v === 'brut' ? 'Brüt' : 'Net') + '</b>';
        if (a[0] === 'iban') return '<b>' + esc(String(v).replace(/(.{4})/g, '$1 ').trim()) + '</b>';
        if (a[0] === 'yillik_izin_hak') return '<b>' + sayi(v) + ' gün (elle)</b>';
        return '<b>' + esc(v) + '</b>';
    }
    function alanGirdi(a, kart) {
        var v = kart[a[0]], tip = a[2] || 'text', ad = a[0];
        if (v == null) v = '';
        if (tip === 'textarea') return '<textarea name="' + ad + '">' + esc(v) + '</textarea>';
        if (tip === 'select') return '<select name="' + ad + '">' + SECIM[ad].map(function (o) { return '<option value="' + esc(o) + '"' + (o === v ? ' selected' : '') + '>' + (o === '' ? '—' : o === 'brut' ? 'Brüt' : o === 'net' ? 'Net' : esc(o)) + '</option>'; }).join('') + '</select>';
        if (tip === 'kisi') return '<select name="' + ad + '"><option value="">—</option>' + V.kisiler.filter(function (k) { return k.id !== kart.id; }).map(function (k) { return '<option value="' + k.id + '"' + (+v === k.id ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>';
        if (tip === 'unvanlist' || tip === 'birimlist') {
            var LS = ((tip === 'unvanlist' ? V.unvan_listesi : V.birim_listesi) || []).slice(); if (v && LS.map(function (x) { return x.toLocaleLowerCase('tr-TR'); }).indexOf(String(v).toLocaleLowerCase('tr-TR')) < 0) LS.unshift(v);
            return '<select name="' + ad + '"><option value="">— Seçin —</option>' + LS.map(function (o) { return '<option value="' + esc(o) + '"' + (o === v ? ' selected' : '') + '>' + esc(o) + '</option>'; }).join('') + '</select>' + (LS.length ? '' : '<small class="ikm-soluk">Liste boş — “Şirket yapısı” sayfasından ' + (tip === 'unvanlist' ? 'unvan' : 'departman') + ' ekleyin.</small>');
        }
        if (tip === 'calisma') return '<select name="' + ad + '">' + Object.keys(V.calisma).map(function (c) { return '<option value="' + c + '"' + (c === v ? ' selected' : '') + '>' + esc(V.calisma[c]) + '</option>'; }).join('') + '</select>';
        var it = { date: 'date', number: 'number', tel: 'tel', email: 'email', para: 'text' }[tip] || 'text';
        var ph = ad === 'yillik_izin_hak' ? ' placeholder="Boş = yasal hak (' + (cek.veri.izin_ozet.yasal == null ? 'işe giriş yok' : sayi(cek.veri.izin_ozet.yasal) + ' gün') + ')"' : ad === 'iban' ? ' placeholder="TR00 0000 …"' : ad === 'tc_kimlik' ? ' maxlength="11" inputmode="numeric"' : '';
        return '<input type="' + it + '" name="' + ad + '" value="' + esc(tip === 'para' && v !== '' ? String(v).replace('.', ',') : v) + '"' + ph + '>';
    }
    function kartCiz() {
        var j = cek.veri, k = j.kart, el = cek.el, s = cek.sekme;
        var unvan = [k.unvan, k.departman].filter(Boolean).join(' · ') || k.rol;
        var rozet = (k.isten_cikis ? '<span class="ikm-cip gri">Ayrıldı · ' + tarih(k.isten_cikis) + '</span>' : '') + (k.kidem ? '<span class="ikm-cip"><i class="fa-solid fa-briefcase"></i> ' + esc(k.kidem) + '</span>' : '') +
            (k.calisma_sekli ? '<span class="ikm-cip">' + esc((V.calisma || {})[k.calisma_sekli] || '') + '</span>' : '') + (j.kendisi ? '<span class="ikm-cip mavi">Sizin kartınız</span>' : '');
        var govde = '';
        if (s === 'bilgi') {
            govde = GRUPLAR.map(function (g) {
                if (g.k === 'mali' && !j.maas_gor) return '<section class="ikm-grup"><div class="ikm-grup-ust"><h4><i class="fa-solid ' + g.ikon + '"></i> ' + g.ad + '</h4></div><div class="ikm-kilit"><i class="fa-solid fa-lock"></i> Bu bilgileri görme yetkiniz yok.</div></section>';
                var alanlar = g.alanlar.filter(function (a) { return a[0] in k || a[0] === 'dogum_tarihi' || a[0] === 'telefon' || a[0] === 'email'; });
                if (!alanlar.length) return '';
                return '<section class="ikm-grup" data-grup="' + g.k + '"><div class="ikm-grup-ust"><h4><i class="fa-solid ' + g.ikon + '"></i> ' + g.ad + '</h4>' +
                    (j.duzenlenebilir[g.k] ? '<button type="button" class="ikm-btn ikm-btn-kucuk" data-duzenle="' + g.k + '"><i class="fa-solid fa-pen"></i> Düzenle</button>' : '') + '</div>' +
                    '<div class="ikm-alanlar">' + alanlar.map(function (a) { return '<div class="ikm-deger' + (a[3] ? ' tam' : '') + '"><span>' + a[1] + '</span>' + alanDeger(a, k) + '</div>'; }).join('') + '</div></section>';
            }).join('') + (j.bilgi_formu ? '<details class="ikm-grup ikm-bakiye-detay"><summary><b>Personel bilgi formu</b> <span class="ikm-soluk">· ' + esc(j.bilgi_formu.dosya) + (j.bilgi_formu.guncelleme ? ' · ' + tarih(j.bilgi_formu.guncelleme) : '') + '</span></summary><div class="ikm-alanlar">' + Object.keys(j.bilgi_formu.ham).filter(function (a) { return !/^(PERSONEL BİLGİ FORMU|İMZA)$/i.test(a); }).map(function (a) { var v = j.bilgi_formu.ham[a]; return '<div class="ikm-deger tam"><span>' + esc(a) + '</span><b>' + esc(v === '' || v == null ? '—' : v) + '</b></div>'; }).join('') + '</div></details>' : '');
        } else if (s === 'izin') {
            var oz = j.izin_ozet;
            govde = '<section class="ikm-grup"><div class="ikm-grup-ust"><h4><i class="fa-solid fa-umbrella-beach"></i> Yıllık izin dönemi · ' + tarih(oz.donem_bas) + ' – ' + tarih(oz.donem_bit) + '</h4>' + (j.kendisi ? '<button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" data-e="izin-yeni"><i class="fa-solid fa-plus"></i> İzin talebi</button>' : (Y.ik_izin_onay ? '<button type="button" class="ikm-btn ikm-btn-kucuk" data-e="izin-yeni"><i class="fa-solid fa-plus"></i> Adına izin gir</button>' : '')) + '</div>' +
                '<div class="ikm-alanlar"><div class="ikm-deger"><span>Bu dönem hakkı</span><b>' + (oz.hak == null ? '— (işe giriş tarihi yok)' : sayi(oz.hak) + ' gün' + (oz.elle ? ' (elle)' : '')) + '</b></div><div class="ikm-deger"><span>Önceki dönemden devreden</span><b>' + (oz.devir ? sayi(oz.devir) + ' gün' : '—') + '</b></div><div class="ikm-deger"><span>Kalan</span><b>' + (oz.hak == null ? '—' : sayi(oz.kalan) + ' gün') + '</b></div>' +
                '<div class="ikm-deger"><span>Kullanılan</span><b>' + sayi(oz.kullanilan) + ' gün</b></div><div class="ikm-deger"><span>Onay bekleyen</span><b>' + sayi(oz.bekleyen) + ' gün</b></div></div></section>' +
                (j.izin_bakiye ? '<details class="ikm-grup ikm-bakiye-detay"><summary><b>İzin bakiye raporu</b> <span class="ikm-soluk">· ' + tarih(j.izin_bakiye.rapor_tarihi) + ' tarihli Excel</span></summary><div class="ikm-alanlar">' + Object.keys(j.izin_bakiye.ham).filter(function (a) { return a !== 'Sıra'; }).map(function (a) { var v = j.izin_bakiye.ham[a]; return '<div class="ikm-deger"><span>' + esc(a) + '</span><b>' + esc(v === '' || v == null ? '—' : v) + '</b></div>'; }).join('') + '</div></details>' : '') +
                '<div class="ikm-liste">' + (j.izinler.length ? j.izinler.map(function (t) { return '<div class="ikm-izin kucuk"><span class="ikm-satir-ikon"><i class="fa-solid fa-umbrella-beach"></i></span><div class="ikm-izin-ic"><b>' + esc(t.tur_ad) + '</b><div class="ikm-izin-alt"><span><i class="fa-regular fa-calendar"></i> ' + tarih(t.baslangic) + (t.bitis !== t.baslangic ? ' – ' + tarih(t.bitis) : '') + '</span><span>' + sayi(t.gun) + ' iş günü</span></div>' + (t.onaylayan && t.durum !== 'beklemede' ? '<div class="ikm-not">' + esc(t.onaylayan) + (t.onay_notu ? ': ' + esc(t.onay_notu) : '') + '</div>' : '') + '</div>' + durumCip(t.durum) + '</div>'; }).join('') : bosKutu('fa-calendar-xmark', 'İzin kaydı yok')) + '</div>';
        } else if (s === 'log') {
            govde = '<div id="ikm-kisi-log"><div class="ikm-iskelet" style="padding:8px"><i></i><i></i><i></i></div></div>';
        } else if (s === 'org') {
            govde = '<div id="ikm-org"><div class="ikm-iskelet" style="padding:8px"><i></i><i></i><i></i></div></div>';
        } else if (s === 'belgeler') {
            govde = (j.belge_yonet ? '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" data-e="belge-yukle"><i class="fa-solid fa-upload"></i> Belge yükle</button></div>' : '') +
                (j.belgeler.length ? '<div class="ikm-liste">' + j.belgeler.map(function (b) {
                    var kalan = b.gecerlilik ? Math.round((new Date(b.gecerlilik + 'T00:00:00') - new Date(new Date().toDateString())) / 86400000) : null;
                    var ikon = /pdf/.test(b.mime) ? 'fa-file-pdf' : /image/.test(b.mime) ? 'fa-file-image' : /sheet|excel/.test(b.mime) ? 'fa-file-excel' : 'fa-file-word';
                    return '<div class="ikm-satir sabit"><span class="ikm-satir-ikon"><i class="fa-regular ' + ikon + '"></i></span><div class="ikm-satir-ic"><b>' + esc(b.ad || b.tur_ad) + '</b><small>' + esc(b.tur_ad) + ' · ' + boyutYaz(b.boyut) + ' · ' + tarih(b.tarih) +
                        (b.gecerlilik ? ' · <span class="' + (kalan != null && kalan <= 30 ? 'kirmizi-yazi' : '') + '">geçerlilik ' + tarih(b.gecerlilik) + (kalan != null && kalan <= 30 ? (kalan < 0 ? ' (doldu)' : ' (' + kalan + ' gün)') : '') + '</span>' : '') + '</small></div>' +
                        '<a class="ikm-btn ikm-btn-kucuk ikm-btn-ikon" href="' + esc(b.url) + '" target="_blank" rel="noopener" title="Aç / indir"><i class="fa-solid fa-arrow-up-right-from-square"></i></a>' +
                        (j.belge_yonet ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon ikm-btn-tehlike" data-belge-sil="' + b.id + '" title="Sil"><i class="fa-solid fa-trash"></i></button>' : '') + '</div>';
                }).join('') + '</div>' : bosKutu('fa-folder-open', 'Belge yok'));
        } else if (s === 'zimmet') {
            govde = (j.belge_yonet ? '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" data-e="zimmet-yeni"><i class="fa-solid fa-plus"></i> Zimmet ekle</button></div>' : '') +
                (j.zimmetler.length ? '<div class="ikm-liste">' + j.zimmetler.map(function (z) {
                    return '<div class="ikm-satir sabit"><span class="ikm-satir-ikon"><i class="fa-solid fa-box"></i></span><div class="ikm-satir-ic"><b>' + esc(z.esya) + (z.seri_no ? ' <small>(' + esc(z.seri_no) + ')</small>' : '') + '</b><small>Teslim ' + tarih(z.teslim_tarihi) +
                        (z.durum === 'iade' ? ' · iade ' + tarih(z.iade_tarihi) : '') + (z.aciklama ? ' · ' + esc(z.aciklama) : '') + '</small></div><span class="ikm-cip ' + (z.durum === 'iade' ? 'gri' : 'yesil') + '">' + (z.durum === 'iade' ? 'İade edildi' : 'Zimmetli') + '</span>' +
                        (j.belge_yonet && z.durum !== 'iade' ? '<button type="button" class="ikm-btn ikm-btn-kucuk" data-zimmet-iade="' + z.id + '">İade al</button>' : '') +
                        (j.belge_yonet ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon ikm-btn-tehlike" data-zimmet-sil="' + z.id + '" title="Sil"><i class="fa-solid fa-trash"></i></button>' : '') + '</div>';
                }).join('') + '</div>' : bosKutu('fa-box-open', 'Zimmet kaydı yok'));
        }
        var avatarHtml = '<span class="ikm-avatar-kap">' + av(k.ad, k.renk, k.resim, 'dev') + (j.resim_yonet ? '<label class="ikm-avatar-yukle" title="Fotoğraf değiştir"><i class="fa-solid fa-camera"></i><input type="file" accept="image/jpeg,image/png,image/webp" data-resim-yukle="' + k.id + '" hidden></label>' : '') + (j.resim_yonet && k.resim ? '<button type="button" class="ikm-avatar-kaldir" data-resim-kaldir="' + k.id + '" title="Fotoğrafı kaldır"><i class="fa-solid fa-trash"></i></button>' : '') + '</span>';
        el.innerHTML = '<div class="ikm-c-ust"><div class="ikm-c-kimlik">' + avatarHtml + '<div><h2>' + esc(k.ad) + '</h2><p>' + esc(unvan) + '</p></div></div><button type="button" class="ikm-btn ikm-x ikm-geri" aria-label="Personel listesine dön"><i class="fa-solid fa-arrow-left"></i> <span>Personel listesi</span></button></div>' +
            '<div class="ikm-c-rozetler">' + rozet + '</div>' +
            '<div class="ikm-sekme-cubuk">' + [['bilgi', 'Bilgiler', 'fa-user'], ['org', 'Organizasyon', 'fa-sitemap'], ['izin', 'İzinler', 'fa-umbrella-beach'], ].concat(j.evrak_gizli ? [] : [['belgeler', 'Belgeler (' + j.belgeler.length + ')', 'fa-folder-open'], ['zimmet', 'Zimmet (' + j.zimmetler.filter(function (z) { return z.durum !== 'iade'; }).length + ')', 'fa-box']]).concat(j.log_gor ? [['log', 'İşlem geçmişi', 'fa-clock-rotate-left']] : [])
                .map(function (x) { return '<button type="button" data-s="' + x[0] + '" class="' + (x[0] === s ? 'aktif' : '') + '"><i class="fa-solid ' + x[2] + '"></i> ' + x[1] + '</button>'; }).join('') + '</div>' +
            '<div class="ikm-c-govde">' + govde + '</div>';
        if (s === 'log' && window.IKM && window.IKM.kisiLog) window.IKM.kisiLog($('#ikm-kisi-log', el), k.id);
        if (s === 'org' && window.IKM && window.IKM.orgCiz) window.IKM.orgCiz($('#ikm-org', el), j, cek);
        urlYaz(function (p) { p.set('personel', k.id); });
        $('.ikm-x', el).onclick = cekmeceKapat;
        el.onchange = function (e) {
            var t = e.target.closest('[data-resim-yukle]');
            if (!t || !t.files || !t.files[0]) return;
            var dosya = t.files[0], id = t.dataset.resimYukle;
            gonder('personel_resim', { id: id }, dosya).then(function (r) {
                bildir('Fotoğraf güncellendi.', 'basari');
                if (cek && cek.veri && cek.veri.kart.id === +id) { cek.veri.kart.resim = r.resim; kartCiz(); }
                if (aktifSekme === 'personel') personelYukle();
            }).catch(function (er) { bildir(er.message, 'hata'); });
        };
        el.onclick = function (e) {
            var t;
            if ((t = e.target.closest('.ikm-sekme-cubuk [data-s]'))) { cek.sekme = t.dataset.s; kartCiz(); return; }
            if ((t = e.target.closest('[data-resim-kaldir]'))) {
                var rid = +t.dataset.resimKaldir;
                window.tbOnayla({ baslik: 'Fotoğraf kaldırılsın mı?', mesaj: 'Personelin profil fotoğrafı silinir; yerine baş harf görünür.', evet: 'Kaldır', tur: 'tehlike' }).then(function (ok) {
                    if (!ok) return;
                    gonder('personel_resim_kaldir', { id: rid }).then(function () {
                        bildir('Fotoğraf kaldırıldı.', 'basari');
                        if (cek && cek.veri && cek.veri.kart.id === rid) { cek.veri.kart.resim = ''; kartCiz(); }
                        if (aktifSekme === 'personel') personelYukle();
                    }).catch(function (er) { bildir(er.message, 'hata'); });
                });
                return;
            }
            if ((t = e.target.closest('[data-duzenle]'))) { grupDuzenle(t.dataset.duzenle); return; }
            if ((t = e.target.closest('[data-e="izin-yeni"]'))) { izinTalebi(j.kendisi ? null : k.id); return; }
            if ((t = e.target.closest('[data-e="belge-yukle"]'))) { belgeYukle(k); return; }
            if ((t = e.target.closest('[data-e="zimmet-yeni"]'))) { zimmetFormu(k); return; }
            if ((t = e.target.closest('[data-belge-sil]'))) {
                var bid = t.dataset.belgeSil;
                onayla({ baslik: 'Belge silinsin mi?', mesaj: 'Dosya kalıcı olarak silinecek.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('belge_sil', { id: bid }).then(function () { bildir('Belge silindi.', 'basari'); kartAc(k.id); }); }).catch(function (er) { bildir(er.message, 'hata'); });
                return;
            }
            if ((t = e.target.closest('[data-zimmet-iade]'))) { gonder('zimmet_iade', { id: t.dataset.zimmetIade }).then(function () { bildir('İade alındı.', 'basari'); kartAc(k.id); }).catch(function (er) { bildir(er.message, 'hata'); }); return; }
            if ((t = e.target.closest('[data-zimmet-sil]'))) {
                var zid = t.dataset.zimmetSil;
                onayla({ baslik: 'Zimmet kaydı silinsin mi?', mesaj: 'Bu kayıt geçmişten de kaldırılır. İade için “İade al”ı kullanın.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('zimmet_sil', { id: zid }).then(function () { kartAc(k.id); }); }).catch(function (er) { bildir(er.message, 'hata'); });
                return;
            }
        };
    }
    function grupDuzenle(gk) {
        var g = GRUPLAR.filter(function (x) { return x.k === gk; })[0], k = cek.veri.kart;
        var kutu = cek.el.querySelector('.ikm-grup[data-grup="' + gk + '"]');
        var alanlar = g.alanlar.filter(function (a) { return a[0] in k || ['dogum_tarihi', 'telefon', 'email'].indexOf(a[0]) >= 0; });
        kutu.innerHTML = '<div class="ikm-grup-ust"><h4><i class="fa-solid ' + g.ikon + '"></i> ' + g.ad + '</h4></div><div class="ikm-alanlar">' +
            alanlar.map(function (a) { return '<label class="ikm-alan' + (a[3] ? ' tam' : '') + '"><span>' + a[1] + '</span>' + alanGirdi(a, k) + '</label>'; }).join('') + '</div>' +
            '<div class="ikm-aksiyon sag"><button type="button" class="ikm-btn ikm-btn-kucuk" data-vazgec>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" data-kaydet><i class="fa-solid fa-floppy-disk"></i> Kaydet</button></div>';
        var ilk = kutu.querySelector('input, select, textarea'); if (ilk) ilk.focus();
        kutu.querySelector('[data-vazgec]').onclick = function (e) { e.stopPropagation(); kartCiz(); };
        kutu.querySelector('[data-kaydet]').onclick = function (e) {
            e.stopPropagation();
            var veri = { id: k.id, grup: gk };
            $$('[name]', kutu).forEach(function (i) { veri[i.name] = i.value; });
            var b = this; b.disabled = true;
            gonder('personel_kaydet', veri).then(function () { bildir('Kaydedildi.', 'basari'); kartAc(k.id); if (aktifSekme === 'personel') personelYukle(); })
                .catch(function (er) { bildir(er.message, 'hata'); b.disabled = false; });
        };
    }
    function belgeYukle(k) {
        var m = modal({ baslik: 'Belge yükle — ' + esc(k.ad), ikon: 'fa-upload', gen: 600,
            govde: '<div class="ikm-form"><div class="ikm-iki"><label class="ikm-alan"><span>Belge türü</span><select name="tur">' + Object.keys(V.belge_turleri).map(function (t) { return '<option value="' + t + '">' + esc(V.belge_turleri[t]) + '</option>'; }).join('') + '</select></label>' +
                '<label class="ikm-alan"><span>Geçerlilik bitişi <small>(varsa — 30 ve 7 gün kala hatırlatılır)</small></span><input type="date" name="gec"></label></div>' +
                '<label class="ikm-alan"><span>Açıklama / başlık</span><input type="text" name="ad" maxlength="200" placeholder="Ör: 2026 iş sözleşmesi"></label>' +
                '<label class="ikm-alan"><span>Dosya <b>*</b> <small>PDF, görsel, Word, Excel · en fazla 15 MB</small></span><input type="file" name="dosya" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"></label></div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-b-kaydet"><i class="fa-solid fa-upload"></i> Yükle</button>' });
        $('#ik-b-kaydet', m.el).onclick = function () {
            var f = $('[name=dosya]', m.el).files[0];
            if (!f) { bildir('Dosya seçin.', 'uyari'); return; }
            var b = this; b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Yükleniyor…';
            gonder('belge_yukle', { kullanici_id: k.id, tur: $('[name=tur]', m.el).value, ad: $('[name=ad]', m.el).value, gecerlilik: $('[name=gec]', m.el).value }, f)
                .then(function () { m.kapat(); bildir('Belge yüklendi.', 'basari'); cek.sekme = 'belgeler'; kartAc(k.id); })
                .catch(function (er) { bildir(er.message, 'hata'); b.disabled = false; b.innerHTML = '<i class="fa-solid fa-upload"></i> Yükle'; });
        };
    }
    function zimmetFormu(k) {
        var m = modal({ baslik: 'Zimmet ekle — ' + esc(k.ad), ikon: 'fa-box', gen: 540,
            govde: '<div class="ikm-form"><label class="ikm-alan"><span>Eşya / demirbaş <b>*</b></span><input type="text" name="esya" maxlength="150" placeholder="Ör: Dizüstü bilgisayar, telefon, kulaklık"></label>' +
                '<div class="ikm-iki"><label class="ikm-alan"><span>Seri no / etiket</span><input type="text" name="seri_no" maxlength="100"></label><label class="ikm-alan"><span>Teslim tarihi</span><input type="date" name="teslim_tarihi" value="' + bugunYmd() + '"></label></div>' +
                '<label class="ikm-alan"><span>Açıklama</span><input type="text" name="aciklama" maxlength="500" placeholder="Durumu, aksesuarları…"></label></div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-z-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        setTimeout(function () { $('[name=esya]', m.el).focus(); }, 60);
        $('#ik-z-kaydet', m.el).onclick = function () {
            var v = { kullanici_id: k.id }; $$('[name]', m.el).forEach(function (i) { v[i.name] = i.value; });
            gonder('zimmet_kaydet', v).then(function () { m.kapat(); bildir('Zimmet kaydedildi; personele bildirildi.', 'basari'); cek.sekme = 'zimmet'; kartAc(k.id); }).catch(function (er) { bildir(er.message, 'hata'); });
        };
    }

    /* ======================= İZİNLER (çok adımlı onay) ======================= */
    var izinF = { kapsam: 'benim', durum: '', yil: '' };
    var DURUM_AD = { beklemede: 'Onay bekliyor', onaylandi: 'Onaylandı', reddedildi: 'Reddedildi', iptal: 'Geri çekildi', atlandi: 'Atlandı', bekliyor: 'Sırada' };
    var DURUM_RENK = { beklemede: 'turuncu', onaylandi: 'yesil', reddedildi: 'kirmizi', iptal: 'gri' };
    function durumCip(d) { return '<span class="ikm-cip ' + (DURUM_RENK[d] || 'gri') + '">' + (DURUM_AD[d] || d) + '</span>'; }

    /** Onay zinciri: kim onayladı, sıra kimde? */
    function zincirHtml(t, dikey) {
        var a = t.adimlar || [];
        if (!a.length) return t.durum === 'beklemede' ? '<div class="ikm-zincir-not"><i class="fa-solid fa-user-shield"></i> Onay: İK yetkilileri</div>' : '';
        var h = '<div class="ikm-zincir' + (dikey ? ' dikey' : '') + '">' + a.map(function (x, i) {
            var sinif = x.durum === 'onaylandi' ? 'ok' : x.durum === 'reddedildi' ? 'red' : x.durum === 'atlandi' ? 'atlandi' : (t.durum === 'beklemede' && t.aktif_ad === x.ad && i === a.map(function (y) { return y.durum; }).indexOf('bekliyor') ? 'sira' : 'bekliyor');
            var ikon = sinif === 'ok' ? 'fa-check' : sinif === 'red' ? 'fa-xmark' : sinif === 'sira' ? 'fa-hourglass-half' : sinif === 'atlandi' ? 'fa-minus' : 'fa-ellipsis';
            var alt = x.durum === 'onaylandi' ? 'Onayladı · ' + tarihSaat(x.tarih) : x.durum === 'reddedildi' ? 'Reddetti · ' + tarihSaat(x.tarih) : x.durum === 'atlandi' ? 'Atlandı' : sinif === 'sira' ? 'Onay bekleniyor' : 'Sırada';
            var kanal = (x.kanal && (x.kanal.sms || x.kanal.mail)) ? '<span class="ikm-z-kanal">' + (x.kanal.sms ? '<i class="fa-solid fa-comment-sms" title="SMS"></i>' : '') + (x.kanal.mail ? '<i class="fa-regular fa-envelope" title="E-posta"></i>' : '') + '</span>' : '';
            return '<div class="ikm-z-dugum ' + sinif + '"><span class="ikm-z-nokta"><i class="fa-solid ' + ikon + '"></i></span><div class="ikm-z-yazi"><b>' + esc(x.ad) + '</b>' + kanal + '<small>' + alt + (x.not ? ' · “' + esc(x.not) + '”' : '') + '</small></div></div>';
        }).join('') + '<div class="ikm-z-dugum son ' + (t.durum === 'onaylandi' ? 'ok' : t.durum === 'reddedildi' ? 'red' : t.durum === 'iptal' ? 'atlandi' : 'bekliyor') + '"><span class="ikm-z-nokta"><i class="fa-solid ' + (t.durum === 'onaylandi' ? 'fa-flag-checkered' : t.durum === 'reddedildi' ? 'fa-ban' : 'fa-flag') + '"></i></span><div class="ikm-z-yazi"><b>' + (t.durum === 'onaylandi' ? 'İzin onaylandı' : t.durum === 'reddedildi' ? 'Reddedildi' : t.durum === 'iptal' ? 'İptal edildi' : 'Sonuç') + '</b></div></div></div>';
        return h;
    }
    function izinSatir(t, mod) {
        var kisa = mod === 'kisa';
        var iptalBant = t.durum === 'iptal' ? '<div class="ikm-iptal-bant"><i class="fa-solid fa-rotate-left"></i> <b>' + (t.onaylayan && t.onaylayan !== t.ad ? 'İzin ' + esc(t.onaylayan) + ' tarafından iptal edildi' : 'Talep geri çekildi') + '</b>' + (t.onay_tarihi ? '<span>' + tarih(t.onay_tarihi) + '</span>' : '') + '</div>' : '';
        return '<article class="ikm-izin' + (t.sira_bende ? ' sirada' : '') + (t.durum === 'iptal' ? ' iptal' : '') + '" data-izin-ac="' + t.id + '">' + iptalBant +
            '<div class="ikm-izin-ust">' + av(t.ad, t.renk, t.resim) +
            '<div class="ikm-izin-ic"><b>' + esc(t.ad) + ' <span class="ikm-izin-tur">' + esc(t.tur_ad) + '</span></b>' +
            '<div class="ikm-izin-alt"><span><i class="fa-regular fa-calendar"></i> ' + tarih(t.baslangic) + (t.bitis !== t.baslangic ? ' – ' + tarih(t.bitis) : '') + '</span><span><i class="fa-regular fa-clock"></i> ' + sayi(t.gun) + ' iş günü</span>' + (t.departman && !kisa ? '<span><i class="fa-solid fa-sitemap"></i> ' + esc(t.departman) + '</span>' : '') + (t.vekil ? '<span><i class="fa-solid fa-user-group"></i> Vekil: ' + esc(t.vekil) + '</span>' : '') + '</div>' +
            (t.aciklama && !kisa ? '<div class="ikm-not">“' + esc(t.aciklama) + '”</div>' : '') + '</div>' +
            '<div class="ikm-izin-sag">' + durumCip(t.durum) + (t.sira_bende ? '<span class="ikm-cip mor"><i class="fa-solid fa-bolt"></i> Sıra sizde</span>' : (t.durum === 'beklemede' && t.aktif_ad ? '<small class="ikm-soluk">Bekleyen: ' + esc(t.aktif_ad) + '</small>' : '')) + '</div></div>' +
            (kisa ? '' : zincirHtml(t)) +
            ((!kisa || t.karar_verebilir || t.iptal_edebilir) ? '<div class="ikm-izin-islem">' +
                (t.karar_verebilir ? '<button type="button" class="ikm-btn ikm-btn-yesil ikm-btn-kucuk" data-izin-onay="' + t.id + '"><i class="fa-solid fa-check"></i> Onayla</button><button type="button" class="ikm-btn ikm-btn-tehlike ikm-btn-kucuk" data-izin-red="' + t.id + '"><i class="fa-solid fa-xmark"></i> Reddet</button>' : '') +
                '<a class="ikm-btn ikm-btn-kucuk" href="ik_islem.php?islem=izin_pdf&id=' + t.id + '" title="İzin belgesini PDF olarak indir"><i class="fa-solid fa-file-pdf"></i> PDF</a>' + (t.iptal_edebilir ? '<button type="button" class="ikm-btn ikm-btn-kucuk" data-izin-iptal="' + t.id + '">' + (t.durum === 'onaylandi' ? 'İzni iptal et' : 'Geri çek') + '</button>' : '') + '</div>' : '') + '</article>';
    }
    function izinEylem(e, sonra) {
        var t;
        if ((t = e.target.closest('[data-izin-onay]'))) {
            e.stopPropagation(); var id = t.dataset.izinOnay;
            onayla({ baslik: 'İzin onaylansın mı?', mesaj: 'Sıradaki kişiye (ya da zincir bittiyse talep sahibine) bildirim gönderilecek.', evet: 'Onayla', hayir: 'Vazgeç', tur: 'basari' }).then(function (ok) {
                if (ok) { t.disabled = true; return gonder('izin_karar', { id: id, karar: 'onay' }).then(function (j) { bildir(j.tamam ? 'İzin onaylandı; talep sahibine haber verildi.' : 'Onayınız kaydedildi; sıradaki kişi: ' + (j.sonraki || '—') + '.', 'basari'); sonra(); rozetYenile(); }); }
            }).catch(function (er) { bildir(er.message, 'hata'); t.disabled = false; });
            return true;
        }
        if ((t = e.target.closest('[data-izin-red]'))) {
            e.stopPropagation(); var rid = t.dataset.izinRed;
            soru({ baslik: 'İzni reddet', mesaj: 'Reddetme nedeni talep sahibine iletilecek.', etiket: 'Reddetme nedeni', zorunlu: true, evet: 'Reddet', tur: 'tehlike', ikon: 'fa-ban', yer: 'Örn. O tarihlerde ekip yoğun, lütfen başka bir tarih seçin.' }).then(function (not) {
                if (not) return gonder('izin_karar', { id: rid, karar: 'red', not: not }).then(function () { bildir('İzin reddedildi; talep sahibine haber verildi.', 'basari'); sonra(); rozetYenile(); });
            }).catch(function (er) { bildir(er.message, 'hata'); });
            return true;
        }
        if ((t = e.target.closest('[data-izin-iptal]'))) {
            e.stopPropagation(); var iid = t.dataset.izinIptal;
            onayla({ baslik: 'Talep geri çekilsin mi?', mesaj: 'Talep iptal edilecek.', evet: 'Geri çek', hayir: 'Vazgeç', tur: 'uyari' }).then(function (ok) { if (ok) return gonder('izin_iptal', { id: iid }).then(function () { bildir('Talep geri çekildi.', 'basari'); sonra(); rozetYenile(); }); }).catch(function (er) { bildir(er.message, 'hata'); });
            return true;
        }
        return false;
    }
    function izinlerCiz(ozel) {
        var tumu = Y.ik_izin_onay || Y.ik_personel_gor, yil = new Date().getFullYear();
        if (!tumu && izinF.kapsam === 'tum') izinF.kapsam = 'benim';
        if (!izinF._basladi) { izinF._basladi = true; if (V && V.ozet.bana_bekleyen > 0 && izinF.kapsam === 'benim') izinF.kapsam = 'bana'; }
        icerik.innerHTML = '<div class="ikm-arac"><div class="ikm-segment" id="ik-i-kapsam"><button type="button" data-k="bana"><i class="fa-solid fa-bolt"></i> Bana gelenler <b class="ikm-rozet" id="ik-i-bana" hidden></b></button><button type="button" data-k="benim">Taleplerim</button>' + (tumu ? '<button type="button" data-k="tum">Tüm talepler</button>' : '') + '</div>' +
            '<select id="ik-i-durum"><option value="">Tüm durumlar</option><option value="beklemede">Onay bekleyen</option><option value="onaylandi">Onaylanan</option><option value="reddedildi">Reddedilen</option><option value="iptal">İptal</option></select>' +
            '<select id="ik-i-yil"><option value="">Tüm yıllar</option>' + [yil + 1, yil, yil - 1, yil - 2].map(function (y) { return '<option>' + y + '</option>'; }).join('') + '</select>' +
            '<button type="button" class="ikm-btn ikm-btn-ana" style="margin-left:auto" data-yeni-izin><i class="fa-solid fa-plus"></i> Talep oluştur</button></div><div class="ikm-liste izin-liste" id="ik-i-liste">' + yukleniyor() + '</div>';
        $$('#ik-i-kapsam button').forEach(function (b) { b.classList.toggle('aktif', b.dataset.k === izinF.kapsam); b.onclick = function () { izinF.kapsam = b.dataset.k; $$('#ik-i-kapsam button').forEach(function (x) { x.classList.toggle('aktif', x === b); }); izinYukle(); }; });
        $('#ik-i-durum').value = izinF.durum; $('#ik-i-yil').value = izinF.yil;
        $('#ik-i-durum').onchange = function () { izinF.durum = this.value; izinYukle(); };
        $('#ik-i-yil').onchange = function () { izinF.yil = this.value; izinYukle(); };
        $('#ik-i-liste').onclick = function (e) { if (izinEylem(e, izinYukle)) return; var a = e.target.closest('[data-izin-ac]'); if (a) { e.stopPropagation(); izinDetay(+a.dataset.izinAc); } };
        izinYukle();
    }
    function izinYukle() {
        getir({ islem: 'izinler', kapsam: izinF.kapsam, durum: izinF.durum, yil: izinF.yil }).then(function (j) {
            var l = $('#ik-i-liste'); if (!l) return;
            var r = $('#ik-i-bana'); if (r) { r.hidden = !j.bana_bekleyen; r.textContent = j.bana_bekleyen; }
            l.innerHTML = j.liste.length ? j.liste.map(function (t) { return izinSatir(t); }).join('') : bosKutu(izinF.kapsam === 'bana' ? 'fa-circle-check' : 'fa-calendar', izinF.kapsam === 'bana' ? 'Onayınızı bekleyen talep yok' : 'İzin talebi yok', izinF.kapsam === 'bana' ? 'Harika, bekleyen bir işiniz kalmadı.' : '“İzin talebi” ile yeni talep oluşturun.');
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }
    kayit('izinler', 'İzinler', 'Taleplerinizi ve onayınızı bekleyenleri buradan yönetin.', izinlerCiz);

    function izinDetay(id) {
        getir({ islem: 'izin_detay', id: id }).then(function (j) {
            var t = j.talep;
            var m = modal({ baslik: 'İzin talebi', ikon: 'fa-umbrella-beach', gen: 640,
                govde: '<div class="ikm-detay-ust">' + av(t.ad, t.renk, t.resim, 'dev') + '<div><h2>' + esc(t.ad) + '</h2><p>' + esc(t.departman || '') + '</p></div><div style="margin-left:auto">' + durumCip(t.durum) + '</div></div>' +
                    '<div class="ikm-alanlar"><div class="ikm-deger"><span>İzin türü</span><b>' + esc(t.tur_ad) + '</b></div><div class="ikm-deger"><span>Süre</span><b>' + sayi(t.gun) + ' iş günü' + (t.yarim_gun ? ' (yarım gün)' : '') + '</b></div>' +
                    '<div class="ikm-deger"><span>Tarih</span><b>' + tarih(t.baslangic) + (t.bitis !== t.baslangic ? ' – ' + tarih(t.bitis) : '') + '</b></div><div class="ikm-deger"><span>Talep tarihi</span><b>' + tarihSaat(t.olusturma) + '</b></div>' +
                    (t.vekil ? '<div class="ikm-deger"><span>Vekil</span><b>' + esc(t.vekil) + '</b></div>' : '') + (t.aciklama ? '<div class="ikm-deger tam"><span>Açıklama</span><b>' + esc(t.aciklama) + '</b></div>' : '') + '</div>' +
                    '<h4 class="ikm-alt-baslik"><i class="fa-solid fa-route"></i> Onay süreci</h4>' + (zincirHtml(t, true) || '<p class="ikm-soluk">Bu talep için tanımlı bir onay zinciri yok.</p>') +
                    (t.onaylayan && t.durum !== 'beklemede' && !(t.adimlar || []).length ? '<p class="ikm-soluk">' + esc(t.onaylayan) + (t.onay_notu ? ': ' + esc(t.onay_notu) : '') + '</p>' : ''),
                alt: ((t.karar_verebilir || t.iptal_edebilir) ? '<div class="ikm-izin-islem sag" id="ik-d-islem">' + (t.karar_verebilir ? '<button type="button" class="ikm-btn ikm-btn-yesil" data-izin-onay="' + t.id + '"><i class="fa-solid fa-check"></i> Onayla</button><button type="button" class="ikm-btn ikm-btn-tehlike" data-izin-red="' + t.id + '"><i class="fa-solid fa-xmark"></i> Reddet</button>' : '') +
                    (t.iptal_edebilir ? '<button type="button" class="ikm-btn" data-izin-iptal="' + t.id + '">' + (t.durum === 'onaylandi' ? 'İzni iptal et' : 'Geri çek') + '</button>' : '') + '</div>' : '<button type="button" class="ikm-btn" data-kapat>Kapat</button>') });
            var isl = $('#ik-d-islem', m.el);
            if (isl) isl.onclick = function (e) { izinEylem(e, function () { m.kapat(); if (aktifSekme === 'izinler') izinYukle(); else if (aktifSekme === 'genel') genelCiz(); if (cek) kartAc(cek.id); }); };
            urlYaz(function (p) { p.set('talep', id); });
            var kb = m.kapat; m.kapat = function () { kb(); urlYaz(function (p) { p.delete('talep'); }); };
            $('.ikm-x', m.el).onclick = m.kapat;
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }

    /** Tek giriş noktası: izin / masraf / avans */
    function talepOlustur() {
        var yenile = function () { if (aktifSekme === 'masraflar') masraflarCiz(); else if (aktifSekme === 'avanslar') avanslarCiz(); else if (aktifSekme === 'genel') genelCiz(); };
        var T = [['izin', 'fa-umbrella-beach', 'İzin', 'Yıllık, mazeret, rapor…', '#10b981'], ['masraf', 'fa-wallet', 'Masraf', 'Yol, yemek, konaklama…', '#ec4899'], ['avans', 'fa-hand-holding-dollar', 'Avans', 'Maaştan mahsup edilecek', '#f97316']];
        var m = modal({ baslik: 'Talep oluştur', ikon: 'fa-circle-plus', gen: 520, govde: '<p class="ikm-soluk" style="margin:0 0 12px">Ne için talepte bulunmak istiyorsunuz?</p><div class="ikm-talep-tur">' +
            T.map(function (t) { return '<button type="button" class="ikm-talep-kart" data-tt="' + t[0] + '" style="--c:' + t[4] + '"><span><i class="fa-solid ' + t[1] + '"></i></span><b>' + t[2] + '</b><small>' + t[3] + '</small></button>'; }).join('') + '</div>' });
        $$('[data-tt]', m.el).forEach(function (b) { b.onclick = function () { var t = b.dataset.tt; m.kapat(); setTimeout(function () { if (t === 'izin') izinTalebi(null); else if (t === 'masraf') masrafFormu(yenile); else avansFormu(yenile); }, 200); }; });
    }
    function izinTalebi(adinaId) {
        var oz = V.ozet.izin, tur = 'yillik', atla = false;
        var kisiSec = V.kisiler.filter(function (k) { return k.id !== V.ben; });
        var m = modal({ baslik: 'İzin talebi', ikon: 'fa-umbrella-beach', gen: 660,
            govde: '<div class="ikm-form">' +
                (Y.ik_izin_onay ? (adinaId ? '<input type="hidden" name="kullanici_id" value="' + adinaId + '">' : '<label class="ikm-alan"><span>Personel</span><select name="kullanici_id" data-ara="yok"><option value="">Kendim</option>' + kisiSec.map(function (k) { return '<option value="' + k.id + '">' + esc(k.ad) + '</option>'; }).join('') + '</select></label>') : '') +
                '<div class="ikm-alan"><span>İzin türü</span><div class="ikm-secenekler ik-tur">' + Object.keys(V.izin_turleri).map(function (t) { return '<button type="button" class="ikm-secenek' + (t === tur ? ' aktif' : '') + '" data-t="' + t + '">' + esc(V.izin_turleri[t]) + '</button>'; }).join('') + '</div></div>' +
                '<div class="ikm-iki"><label class="ikm-alan"><span>Başlangıç <b>*</b></span><input type="date" name="baslangic"></label><label class="ikm-alan"><span>Bitiş</span><input type="date" name="bitis"></label></div>' +
                '<label class="ikm-onay-kutu"><input type="checkbox" name="yarim_gun"> <span>Yarım gün <small>(tek günlük izinlerde)</small></span></label>' +
                '<div class="ikm-onizle" id="ik-gun">Tarih seçin.</div>' +
                '<label class="ikm-alan"><span>Vekil <small>(isteğe bağlı)</small></span><select name="vekil_id"><option value="">—</option>' + kisiSec.map(function (k) { return '<option value="' + k.id + '">' + esc(k.ad) + '</option>'; }).join('') + '</select></label>' +
                '<label class="ikm-alan"><span>Açıklama</span><textarea name="aciklama" placeholder="Neden / ulaşılabileceğiniz numara…" rows="3"></textarea></label>' +
                '<div class="ikm-akis-onizle" id="ik-akis-on"></div>' +
                (Y.ik_izin_onay ? '<input type="hidden" name="atla" value="0">' : '') + '</div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-t-gonder"><i class="fa-solid fa-paper-plane"></i> Talep gönder</button>' });
        var el = m.el;
        function onizle() {
            var b = $('[name=baslangic]', el).value, e = $('[name=bitis]', el).value || b, y = $('[name=yarim_gun]', el).checked;
            var kutu = $('#ik-gun', el);
            if (!b) { kutu.className = 'ikm-onizle'; kutu.textContent = 'Tarih seçin.'; return; }
            if (e < b) { kutu.className = 'ikm-onizle uyari'; kutu.textContent = 'Bitiş başlangıçtan önce olamaz.'; return; }
            var g = isGunu(b, e, y), adina = $('[name=kullanici_id]', el) && $('[name=kullanici_id]', el).value;
            var metin = '<i class="fa-regular fa-calendar-check"></i> <b>' + sayi(g) + ' iş günü</b> (hafta sonları sayılmaz)', uyari = false;
            if (tur === 'yillik' && !adina && oz.hak != null) { var kalan = oz.kalan - oz.bekleyen; metin += ' · kalan yıllık izniniz <b>' + sayi(kalan) + '</b> gün'; if (g > kalan) { uyari = true; metin += ' — yetersiz!'; } }
            kutu.className = 'ikm-onizle' + (uyari || !g ? ' uyari' : ''); kutu.innerHTML = g ? metin : 'Seçilen aralıkta iş günü yok.';
        }
        function akisYukle() {
            var kid = $('[name=kullanici_id]', el) ? $('[name=kullanici_id]', el).value : '';
            var p = { islem: 'izin_zincir' }; if (kid) p.kullanici_id = kid;
            getir(p).then(function (j) {
                var k = $('#ik-akis-on', el); if (!k) return;
                var atlaBtn = Y.ik_izin_onay ? '<button type="button" class="ik-atla' + (atla ? ' aktif' : '') + '" id="ik-atla" title="Seçiliyse onay sürecine girmeden izin doğrudan eklenir"><i class="fa-solid ' + (atla ? 'fa-square-check' : 'fa-square') + '"></i> Bu seçimi atla</button>' : '';
                var bas = function (metin, ikon, soluk) { return '<div class="ikm-akis-ust"><div class="ikm-akis-baslik' + (soluk ? ' soluk' : '') + '"><i class="fa-solid ' + ikon + '"></i> ' + metin + '</div>' + atlaBtn + '</div>'; };
                k.className = 'ikm-akis-onizle' + (atla ? ' atlandi' : '');
                if (atla) { k.innerHTML = bas('Onay süreci atlanacak', 'fa-forward', true) + '<div class="ikm-akis-baslik soluk" style="margin:8px 0 0"><i class="fa-solid fa-circle-check"></i> Talep onay sürecine girmeden doğrudan izin olarak eklenecek.</div>'; }
                else k.innerHTML = j.adimlar.length ? bas('Bu talep sırayla şu kişilerin onayına gidecek', 'fa-route') + '<div class="ikm-akis-zincir">' +
                    j.adimlar.map(function (a, i) { return '<span class="ikm-akis-kisi"><em>' + (i + 1) + '</em>' + esc(a.ad) + (a.sms ? ' <i class="fa-solid fa-comment-sms" title="SMS gider"></i>' : '') + (a.mail ? ' <i class="fa-regular fa-envelope" title="E-posta gider"></i>' : '') + '</span>'; }).join('<i class="fa-solid fa-chevron-right ok"></i>') + '<i class="fa-solid fa-chevron-right ok"></i><span class="ikm-akis-kisi son"><i class="fa-solid fa-flag-checkered"></i> Onay</span></div>'
                    : bas('Talep, izin onaylama yetkisi olan İK yetkililerine iletilecek.', 'fa-user-shield', true);
                var ab = $('#ik-atla', el); if (ab) ab.onclick = function () { atla = !atla; $('[name=atla]', el).value = atla ? 1 : 0; akisYukle(); };
            }).catch(function () {});
        }
        akisYukle();
        (function () {
            var btn = $('#ik-vekil-btn', el), pn = $('#ik-vekil-panel', el), q = $('#ik-vekil-ara', el), ls = $('#ik-vekil-liste', el), hid = $('[name=vekil_id]', el);
            var kc = function (x) { return String(x || '').toLocaleLowerCase('tr-TR'); };
            function ciz() {
                var a = kc(q.value).trim(), h = '<button type="button" class="ik-vekil-oge" data-id="">—</button>';
                kisiSec.forEach(function (k) { if (!a || kc(k.ad).indexOf(a) > -1) h += '<button type="button" class="ik-vekil-oge' + (String(k.id) === hid.value ? ' aktif' : '') + '" data-id="' + k.id + '">' + esc(k.ad) + '</button>'; });
                ls.innerHTML = h;
                $$('.ik-vekil-oge', ls).forEach(function (o) { o.onclick = function () { hid.value = o.dataset.id; $('span', btn).textContent = o.dataset.id ? o.textContent : '—'; pn.hidden = true; btn.classList.remove('acik'); }; });
            }
            btn.onclick = function () { var ac = pn.hidden; pn.hidden = !ac; btn.classList.toggle('acik', ac); if (ac) { q.value = ''; ciz(); q.focus(); } };
            q.addEventListener('input', ciz);
            q.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); var o = $('.ik-vekil-oge[data-id]:not([data-id=""])', ls); if (o) o.click(); } else if (e.key === 'Escape') { e.stopPropagation(); pn.hidden = true; btn.classList.remove('acik'); } });
        })();
        $$('.ik-tur .ikm-secenek', el).forEach(function (b) { b.onclick = function () { tur = b.dataset.t; $$('.ik-tur .ikm-secenek', el).forEach(function (x) { x.classList.toggle('aktif', x === b); }); onizle(); }; });
        $$('[name=baslangic], [name=bitis], [name=yarim_gun], [name=kullanici_id]', el).forEach(function (i) { i.addEventListener('change', function () { if (i.name === 'baslangic' && !$('[name=bitis]', el).value) $('[name=bitis]', el).value = i.value; if (i.name === 'kullanici_id') akisYukle(); onizle(); }); });
        $('#ik-t-gonder', el).onclick = function () {
            var v = { tur: tur }; $$('[name]', el).forEach(function (i) { v[i.name] = i.type === 'checkbox' ? (i.checked ? 1 : 0) : i.value; });
            if (!v.baslangic) { bildir('Başlangıç tarihini seçin.', 'uyari'); return; }
            var b = this; b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gönderiliyor…';
            gonder('izin_talep', v).then(function (j) {
                m.kapat(); bildir(j.otomatik ? 'Onaylayacak başka yetkili olmadığı için talebiniz otomatik onaylandı (' + sayi(j.gun) + ' gün).' : 'İzin talebi gönderildi (' + sayi(j.gun) + ' gün)' + (j.zincir && j.zincir.length ? ' · ilk onaycı: ' + j.zincir[0] : (j.kime && j.kime.length ? ' · ' + j.kime.length + ' yetkiliye bildirildi' : '')) + '.', 'basari');
                if (aktifSekme === 'izinler') izinYukle(); else if (aktifSekme === 'genel') genelCiz();
                if (cek) kartAc(cek.id);
                ilkVeri();
            }).catch(function (er) { bildir(er.message, 'hata'); b.disabled = false; b.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Talep gönder'; });
        };
    }

    /* ======================= İZİN TAKVİMİ ======================= */
    var itk = { yil: (new Date()).getFullYear(), ay: (new Date()).getMonth() + 1 };
    function izinTakvimiCiz() {
        icerik.innerHTML = yukleniyor();
        getir({ islem: 'izin_takvimi', yil: itk.yil, ay: itk.ay }).then(function (j) {
            var ilkGun = new Date(j.yil, j.ay - 1, 1), gunSayi = new Date(j.yil, j.ay, 0).getDate(), bosB = (ilkGun.getDay() + 6) % 7, haritasi = {}, bugun = bugunYmd();
            j.liste.forEach(function (k) {
                for (var d = new Date(k.baslangic + 'T00:00:00'); d <= new Date(k.bitis + 'T00:00:00'); d.setDate(d.getDate() + 1)) {
                    var an = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
                    (haritasi[an] = haritasi[an] || []).push(k);
                }
            });
            var h = '';
            for (var i = 0; i < bosB; i++) h += '<div class="ikm-tkv-hucre bos"></div>';
            for (var g = 1; g <= gunSayi; g++) {
                var an = j.yil + '-' + ('0' + j.ay).slice(-2) + '-' + ('0' + g).slice(-2), kisiler = haritasi[an] || [], hs = (bosB + g - 1) % 7;
                h += '<div class="ikm-tkv-hucre' + (an === bugun ? ' bugun' : '') + (hs > 4 ? ' haftasonu' : '') + '"><b>' + g + '</b>' + kisiler.slice(0, 3).map(function (k) {
                    return '<span class="ikm-tkv-etiket' + (k.durum === 'beklemede' ? ' bekliyor' : '') + '" style="--c:' + esc(k.renk) + '" title="' + esc(k.ad) + ' · ' + esc(k.tur) + (k.durum === 'beklemede' ? ' (onay bekliyor)' : '') + '">' + esc(k.ad.split(' ')[0]) + '</span>';
                }).join('') + (kisiler.length > 3 ? '<span class="ikm-tkv-etiket">+' + (kisiler.length - 3) + '</span>' : '') + '</div>';
            }
            icerik.innerHTML = '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-calendar-days"></i> ' + AYLAR[j.ay - 1] + ' ' + j.yil + '</h3>' +
                '<div class="ikm-grup-btn"><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon" id="ik-tkv-onceki" aria-label="Önceki ay"><i class="fa-solid fa-chevron-left"></i></button><button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-tkv-bugun">Bugün</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon" id="ik-tkv-sonraki" aria-label="Sonraki ay"><i class="fa-solid fa-chevron-right"></i></button></div></div>' +
                '<div class="ikm-tkv-baslik">' + ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'].map(function (g) { return '<span>' + g + '</span>'; }).join('') + '</div><div class="ikm-tkv-izgara">' + h + '</div>' +
                '<div class="ikm-lejant"><span style="--c:#10b981">Onaylı izin</span><span style="--c:#f59e0b">Onay bekleyen (çizgili)</span></div></div>';
            $('#ik-tkv-onceki').onclick = function () { itk.ay--; if (itk.ay < 1) { itk.ay = 12; itk.yil--; } izinTakvimiCiz(); };
            $('#ik-tkv-sonraki').onclick = function () { itk.ay++; if (itk.ay > 12) { itk.ay = 1; itk.yil++; } izinTakvimiCiz(); };
            $('#ik-tkv-bugun').onclick = function () { var d = new Date(); itk.yil = d.getFullYear(); itk.ay = d.getMonth() + 1; izinTakvimiCiz(); };
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    kayit('izin_takvimi', 'İzin takvimi', 'Kim hangi gün izinde — onaylı ve bekleyen talepler.', izinTakvimiCiz);

    /* ======================= PUANTAJ ======================= */
    var pj = { yil: (new Date()).getFullYear(), ay: (new Date()).getMonth() + 1, kid: null };
    var PJ_RENK = { tam: '#10b981', yarim: '#3b82f6', izinli: '#8b5cf6', rapor: '#f59e0b', gelmedi: '#f43f5e', resmi_tatil: '#64748b', haftasonu: '#94a3b8' };
    function puantajCiz() {
        icerik.innerHTML = yukleniyor();
        getir({ islem: 'puantaj_ay', yil: pj.yil, ay: pj.ay, kullanici_id: pj.kid || (V ? V.ben : '') }).then(function (j) {
            var secim = j.personeller.length ? '<select id="ik-pj-kisi">' + j.personeller.map(function (k) { return '<option value="' + k.id + '"' + (k.id === j.kullanici_id ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' : '';
            V.puantaj_durum = j.durumlar;
            var satirlar = j.gunler.map(function (g) {
                var d = new Date(g.tarih + 'T00:00:00'), renk = g.durum ? PJ_RENK[g.durum] : 'var(--ik-cizgi)';
                return '<div class="ikm-pj-gun' + (j.yonet ? ' tikla' : '') + '" data-tarih="' + g.tarih + '" style="--c:' + renk + '"><b>' + d.getDate() + '</b><span>' + (g.durum ? esc(j.durumlar[g.durum] || g.durum) : '—') + '</span></div>';
            }).join('');
            icerik.innerHTML = '<div class="ikm-arac">' + secim + '<span class="ikm-soluk kucuk" style="margin-left:auto">' + (j.yonet ? 'Bir güne tıklayarak durum girin.' : 'Yalnızca İK durum girebilir.') + '</span></div>' +
                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-business-time"></i> ' + AYLAR[j.ay - 1] + ' ' + j.yil + '</h3><div class="ikm-grup-btn"><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon" id="ik-pj-onceki"><i class="fa-solid fa-chevron-left"></i></button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon" id="ik-pj-sonraki"><i class="fa-solid fa-chevron-right"></i></button></div></div>' +
                '<div class="ikm-pj-izgara">' + satirlar + '</div><div class="ikm-lejant">' + Object.keys(PJ_RENK).map(function (k) { return '<span style="--c:' + PJ_RENK[k] + '">' + esc((j.durumlar || {})[k] || k) + '</span>'; }).join('') + '</div></div>';
            if ($('#ik-pj-kisi')) $('#ik-pj-kisi').onchange = function () { pj.kid = +this.value; puantajCiz(); };
            $('#ik-pj-onceki').onclick = function () { pj.ay--; if (pj.ay < 1) { pj.ay = 12; pj.yil--; } puantajCiz(); };
            $('#ik-pj-sonraki').onclick = function () { pj.ay++; if (pj.ay > 12) { pj.ay = 1; pj.yil++; } puantajCiz(); };
            if (j.yonet) $$('.ikm-pj-gun', icerik).forEach(function (h) { h.onclick = function () { puantajGunSec(h.dataset.tarih, j.kullanici_id, j.durumlar); }; });
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    function puantajGunSec(t, kid, durumlar) {
        var m = modal({ baslik: tarih(t) + ' — durum seçin', ikon: 'fa-business-time', gen: 440,
            govde: '<div class="ikm-secenekler dikey">' + Object.keys(durumlar).map(function (k) { return '<button type="button" class="ikm-secenek" data-d="' + k + '" style="--c:' + PJ_RENK[k] + '"><i class="ikm-nokta"></i> ' + esc(durumlar[k]) + '</button>'; }).join('') + '</div>' });
        $$('[data-d]', m.el).forEach(function (b) { b.onclick = function () { gonder('puantaj_kaydet', { kullanici_id: kid, tarih: t, durum: b.dataset.d }).then(function () { m.kapat(); bildir('Puantaj kaydedildi.', 'basari'); puantajCiz(); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
    }
    kayit('puantaj', 'Puantaj', 'Aylık devam çizelgesi. Giriş-çıkış kayıtlarından otomatik dolar.', puantajCiz);

    /* ======================= PERSONEL MASRAFLARI ======================= */
    var mf = { kapsam: 'benim' };
    var MASRAF_KAT = { yol: 'Yol / ulaşım', yemek: 'Yemek', konaklama: 'Konaklama', ofis: 'Ofis malzemesi', iletisim: 'İletişim', diger: 'Diğer' };
    function masraflarCiz() {
        icerik.innerHTML = yukleniyor();
        getir({ islem: 'masraflar', kapsam: mf.kapsam }).then(function (j) {
            var ust = '<div class="ikm-arac">' + (j.tum_gorebilir ? '<div class="ikm-segment"><button type="button" data-k="benim" class="' + (mf.kapsam === 'benim' ? 'aktif' : '') + '">Benim taleplerim</button><button type="button" data-k="tum" class="' + (mf.kapsam === 'tum' ? 'aktif' : '') + '">Tüm personel</button></div>' : '') +
                '<button type="button" class="ikm-btn ikm-btn-ana" id="ik-mf-ekle" style="margin-left:auto"><i class="fa-solid fa-plus"></i> Masraf ekle</button></div>';
            var liste = j.liste.length ? '<div class="ikm-liste">' + j.liste.map(function (k) {
                return '<div class="ikm-satir sabit">' + av(k.ad, k.renk, k.resim) + '<div class="ikm-satir-ic"><b>' + esc(k.kategori_ad) + ' · ' + tarih(k.tarih) + '</b><small>' + esc(k.ad) + (k.aciklama ? ' · ' + esc(k.aciklama) : '') + '</small>' + (k.durum !== 'beklemede' && k.onay_notu ? '<div class="ikm-not">Not: ' + esc(k.onay_notu) + '</div>' : '') + '</div>' +
                    durumCip(k.durum) + '<span class="ikm-tutar">' + para(k.tutar) + '</span>' +
                    (k.durum === 'beklemede' && mf.kapsam === 'tum' ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-yesil" data-mf-onay="' + k.id + '">Onayla</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-mf-red="' + k.id + '">Reddet</button>' :
                        (k.durum === 'beklemede' ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon ikm-btn-tehlike" data-mf-sil="' + k.id + '" title="Sil"><i class="fa-solid fa-trash"></i></button>' : '')) + '</div>';
            }).join('') + '</div>' : bosKutu('fa-receipt', 'Masraf kaydı yok', '“Masraf ekle” ile yeni talep oluşturun.');
            icerik.innerHTML = ust + liste;
            $$('.ikm-segment [data-k]', icerik).forEach(function (b) { b.onclick = function () { mf.kapsam = b.dataset.k; masraflarCiz(); }; });
            $('#ik-mf-ekle').onclick = masrafFormu;
            $$('[data-mf-onay]', icerik).forEach(function (b) { b.onclick = function () { gonder('masraf_karar', { id: b.dataset.mfOnay, karar: 'onay' }).then(function () { bildir('Masraf onaylandı.', 'basari'); masraflarCiz(); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
            $$('[data-mf-red]', icerik).forEach(function (b) { b.onclick = function () { soru({ baslik: 'Masrafı reddet', etiket: 'Reddetme nedeni', zorunlu: true, evet: 'Reddet', tur: 'tehlike', ikon: 'fa-ban' }).then(function (n) { if (n) return gonder('masraf_karar', { id: b.dataset.mfRed, karar: 'red', not: n }).then(function () { bildir('Masraf reddedildi.', 'basari'); masraflarCiz(); }); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
            $$('[data-mf-sil]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Masraf silinsin mi?', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('masraf_sil', { id: b.dataset.mfSil }).then(function () { masraflarCiz(); }); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    function masrafFormu(cb) {
        var m = modal({ baslik: 'Masraf ekle', ikon: 'fa-receipt', gen: 480,
            govde: '<div class="ikm-form"><label class="ikm-alan"><span>Kategori</span><select name="kategori">' + Object.keys(MASRAF_KAT).map(function (k) { return '<option value="' + k + '">' + esc(MASRAF_KAT[k]) + '</option>'; }).join('') + '</select></label>' +
                '<div class="ikm-iki"><label class="ikm-alan"><span>Tutar (₺) <b>*</b></span><input type="number" step="0.01" name="tutar"></label><label class="ikm-alan"><span>Tarih</span><input type="date" name="tarih" value="' + bugunYmd() + '"></label></div>' +
                '<label class="ikm-alan"><span>Açıklama</span><textarea name="aciklama" rows="3"></textarea></label></div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-mk-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        $('#ik-mk-kaydet', m.el).onclick = function () {
            var v = {}; $$('[name]', m.el).forEach(function (i) { v[i.name] = i.value; });
            if (!v.tutar) { bildir('Tutarı girin.', 'uyari'); return; }
            gonder('masraf_ekle', v).then(function () { m.kapat(); bildir('Masraf talebi kaydedildi.', 'basari'); (typeof cb === 'function' ? cb : masraflarCiz)(); }).catch(function (er) { bildir(er.message, 'hata'); });
        };
    }
    kayit('masraflar', 'Personel masrafları', 'Yol, yemek, konaklama gibi masraf talepleri ve onayları.', masraflarCiz);

    /* ======================= AVANS TALEPLERİ ======================= */
    var av2 = { kapsam: 'benim' };
    function avanslarCiz(ozel) {
        var hedef = (ozel && ozel.id) ? parseInt(ozel.id, 10) || 0 : 0;   // bildirimden gelen talep numarası
        icerik.innerHTML = yukleniyor();
        getir({ islem: 'avanslar', kapsam: av2.kapsam }).then(function (j) {
            if (hedef && av2.kapsam === 'benim' && j.tum_gorebilir && !j.liste.some(function (k) { return +k.id === hedef; })) { av2.kapsam = 'tum'; return avanslarCiz({ id: hedef }); }
            var ust = '<div class="ikm-arac">' + (j.tum_gorebilir ? '<div class="ikm-segment"><button type="button" data-k="benim" class="' + (av2.kapsam === 'benim' ? 'aktif' : '') + '">Benim taleplerim</button><button type="button" data-k="tum" class="' + (av2.kapsam === 'tum' ? 'aktif' : '') + '">Tüm personel</button></div>' : '') +
                '<button type="button" class="ikm-btn ikm-btn-ana" id="ik-av-ekle" style="margin-left:auto"><i class="fa-solid fa-plus"></i> Avans talep et</button></div>';
            var liste = j.liste.length ? '<div class="ikm-liste">' + j.liste.map(function (k) {
                return '<div class="ikm-satir sabit" data-avid="' + k.id + '">' + av(k.ad, k.renk, k.resim) + '<div class="ikm-satir-ic"><b>Avans' + (k.taksit > 1 ? ' · ' + k.taksit + ' taksit' : ' · tek seferde') + (k.tarih ? ' · ' + tarih(k.tarih) : '') + '</b><small>' + esc(k.ad) + (k.aciklama ? ' · ' + esc(k.aciklama) : '') + '</small>' + (k.durum !== 'beklemede' && k.onay_notu ? '<div class="ikm-not">Not: ' + esc(k.onay_notu) + '</div>' : '') + '</div>' +
                    durumCip(k.durum) + '<span class="ikm-tutar">' + para(k.tutar) + '</span>' +
                    (k.durum === 'beklemede' && av2.kapsam === 'tum' ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-yesil" data-av-onay="' + k.id + '">Onayla</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-av-red="' + k.id + '">Reddet</button>' :
                        (k.durum === 'beklemede' ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon ikm-btn-tehlike" data-av-sil="' + k.id + '" title="Sil"><i class="fa-solid fa-trash"></i></button>' : '')) + '</div>';
            }).join('') + '</div>' : bosKutu('fa-hand-holding-dollar', 'Avans talebi yok', '“Avans talep et” ile yeni talep oluşturun.');
            icerik.innerHTML = ust + liste;
            if (hedef) { var hs = icerik.querySelector('[data-avid="' + hedef + '"]'); if (hs) { try { hs.scrollIntoView({ block: 'center' }); } catch (e) {} hs.style.boxShadow = '0 0 0 2px #6366f1'; hs.style.background = 'rgba(99,102,241,.10)'; hs.style.borderRadius = '14px'; } }
            $$('.ikm-segment [data-k]', icerik).forEach(function (b) { b.onclick = function () { av2.kapsam = b.dataset.k; avanslarCiz(); }; });
            $('#ik-av-ekle').onclick = avansFormu;
            $$('[data-av-onay]', icerik).forEach(function (b) { b.onclick = function () { gonder('avans_karar', { id: b.dataset.avOnay, karar: 'onay' }).then(function () { bildir('Avans onaylandı.', 'basari'); avanslarCiz(); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
            $$('[data-av-red]', icerik).forEach(function (b) { b.onclick = function () { soru({ baslik: 'Avansı reddet', etiket: 'Reddetme nedeni', zorunlu: true, evet: 'Reddet', tur: 'tehlike', ikon: 'fa-ban' }).then(function (n) { if (n) return gonder('avans_karar', { id: b.dataset.avRed, karar: 'red', not: n }).then(function () { bildir('Avans reddedildi.', 'basari'); avanslarCiz(); }); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
            $$('[data-av-sil]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Talep silinsin mi?', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('avans_sil', { id: b.dataset.avSil }).then(function () { avanslarCiz(); }); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    function avansFormu(cb) {
        var m = modal({ baslik: 'Avans talep et', ikon: 'fa-hand-holding-dollar', gen: 480,
            govde: '<div class="ikm-form"><div class="ikm-iki"><label class="ikm-alan"><span>Tutar (₺) <b>*</b></span><input type="number" step="0.01" name="tutar"></label><label class="ikm-alan"><span>Taksit sayısı</span><input type="number" min="1" max="24" name="taksit" value="1"></label></div>' +
                '<label class="ikm-alan"><span>İstenen ödeme tarihi</span><input type="date" name="tarih" value="' + bugunYmd() + '"></label>' +
                '<label class="ikm-alan"><span>Gerekçe</span><textarea name="aciklama" rows="3"></textarea></label></div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-avk-kaydet"><i class="fa-solid fa-check"></i> Gönder</button>' });
        $('#ik-avk-kaydet', m.el).onclick = function () {
            var v = {}; $$('[name]', m.el).forEach(function (i) { v[i.name] = i.value; });
            if (!v.tutar) { bildir('Tutarı girin.', 'uyari'); return; }
            gonder('avans_ekle', v).then(function () { m.kapat(); bildir('Avans talebi gönderildi.', 'basari'); (typeof cb === 'function' ? cb : avanslarCiz)(); }).catch(function (er) { bildir(er.message, 'hata'); });
        };
    }
    kayit('avanslar', 'Avans talepleri', 'Maaş avansı talepleri ve onayları.', avanslarCiz);

    /* ======================= ZİMMETLER / ÖZLÜK EVRAKLARI (tüm personel) ======================= */
    function zimmetTumCiz() {
        icerik.innerHTML = yukleniyor();
        getir({ islem: 'zimmet_tum' }).then(function (j) {
            icerik.innerHTML = (Y.ik_belge ? '<div class="ikm-arac"><button type="button" class="ikm-btn ikm-btn-ana" id="ik-zt-ekle"><i class="fa-solid fa-plus"></i> Zimmet ekle</button></div>' : '') + (j.liste.length ? '<div class="ikm-liste">' + j.liste.map(function (z) {
                return '<button type="button" class="ikm-satir" data-kisi="' + z.kid + '" data-kisi-sekme="zimmet">' + av(z.ad, z.renk, z.resim) + '<span class="ikm-satir-ic"><b>' + esc(z.esya) + (z.seri_no ? ' (' + esc(z.seri_no) + ')' : '') + '</b><small>' + esc(z.ad) + ' · teslim ' + tarih(z.teslim_tarihi) + '</small></span>' +
                    '<span class="ikm-cip ' + (z.durum === 'iade' ? 'gri' : 'yesil') + '">' + (z.durum === 'iade' ? 'İade edildi' : 'Zimmetli') + '</span></button>';
            }).join('') + '</div>' : bosKutu('fa-box-archive', 'Zimmet kaydı yok'));
            var zb = $('#ik-zt-ekle', icerik); if (zb) zb.onclick = function () { window.IKM.kisiSec('Zimmet kime eklensin?', function (k) { zimmetFormu(k); }); };
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    kayit('zimmet_tum', 'Zimmetler', 'Personele teslim edilen eşya ve demirbaşlar.', zimmetTumCiz);
    function belgeTumCiz() {
        icerik.innerHTML = yukleniyor();
        getir({ islem: 'belge_tum' }).then(function (j) {
            icerik.innerHTML = (Y.ik_belge ? '<div class="ikm-arac"><button type="button" class="ikm-btn ikm-btn-ana" id="ik-bt-ekle"><i class="fa-solid fa-upload"></i> Belge yükle</button></div>' : '') + (j.liste.length ? '<div class="ikm-liste">' + j.liste.map(function (b) {
                return '<button type="button" class="ikm-satir" data-kisi="' + b.kid + '" data-kisi-sekme="belgeler">' + av(b.ad, b.renk, b.resim) + '<span class="ikm-satir-ic"><b>' + esc(b.ad_belge) + '</b><small>' + esc(b.ad) + ' · ' + esc(b.tur_ad) + (b.gecerlilik ? ' · geçerlilik ' + tarih(b.gecerlilik) : '') + '</small></span>' +
                    '<a class="ikm-btn ikm-btn-kucuk ikm-btn-ikon" href="' + esc(b.url) + '" target="_blank" rel="noopener" onclick="event.stopPropagation()" aria-label="Aç"><i class="fa-solid fa-arrow-up-right-from-square"></i></a></button>';
            }).join('') + '</div>' : bosKutu('fa-file-lines', 'Belge yok'));
            var bb = $('#ik-bt-ekle', icerik); if (bb) bb.onclick = function () { window.IKM.kisiSec('Belge kime yüklensin?', function (k) { belgeYukle(k); }); };
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    kayit('belge_tum', 'Özlük evrakları', 'Personele yüklenmiş tüm belgeler.', belgeTumCiz);

    /* ======================= EĞİTİMLER ======================= */
    var EG_GUN = [['olustur', 'Oluşturulunca'], ['7', '1 hafta önce'], ['3', '3 gün önce'], ['1', '1 gün önce'], ['0', 'Etkinlik günü']];
    var egListe = [], egHazir = { sms: false, mail: false };
    function egitimlerCiz() {
        icerik.innerHTML = yukleniyor();
        getir({ islem: 'egitimler' }).then(function (j) {
            egListe = j.liste; egHazir = { sms: j.sms_hazir, mail: j.mail_hazir };
            var ust = j.yonet ? '<div class="ikm-arac"><button type="button" class="ikm-btn ikm-btn-ana" id="ik-eg-ekle" style="margin-left:auto"><i class="fa-solid fa-plus"></i> Eğitim / toplantı ekle</button></div>' : '';
            var liste = j.liste.length ? j.liste.map(function (e) {
                var tamam = e.katilimcilar.filter(function (k) { return k.tamamlandi; }).length, top = e.tur === 'toplanti';
                var bil = e.bildirim_ac ? '<span class="ikm-cip" title="Katılımcılara bildirim gider"><i class="fa-solid fa-bell"></i> ' + (e.bildirim_sms ? 'SMS ' : '') + (e.bildirim_mail ? 'E-posta' : '') + '</span>' : '<span class="ikm-cip ikm-soluk"><i class="fa-solid fa-bell-slash"></i> Bildirimsiz</span>';
                return '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid ' + (top ? 'fa-people-group' : 'fa-graduation-cap') + '"></i> ' + esc(e.ad) + '</h3>' +
                    (!top && e.katilimcilar.length ? '<span class="ikm-cip">' + tamam + '/' + e.katilimcilar.length + ' tamamladı</span>' : '<span class="ikm-cip">' + (top ? 'Toplantı' : 'Eğitim') + '</span>') + '</div>' +
                    '<p class="ikm-soluk kucuk">' + (e.tarih ? tarih(e.tarih) + (e.saat ? ' ' + esc(e.saat) : '') + ' · ' : '') + (e.yer ? esc(e.yer) + ' · ' : '') + (e.egitmen ? (top ? 'Yürüten: ' : 'Eğitmen: ') + esc(e.egitmen) + ' · ' : '') + (e.sure_saat ? sayi(e.sure_saat) + ' saat' : '') + '</p>' + (e.aciklama ? '<p>' + esc(e.aciklama) + '</p>' : '') +
                    (j.yonet ? '<p>' + bil + '</p>' : '') +
                    '<div class="ikm-lejant">' + e.katilimcilar.map(function (k) { return '<span style="--c:' + (k.tamamlandi ? '#10b981' : '#94a3b8') + '">' + esc(k.ad) + (!top && k.tamamlandi ? ' ✓' : '') + '</span>'; }).join('') + '</div>' +
                    (!top && e.benim_durum !== null ? '<label class="ikm-onay-kutu"><input type="checkbox" data-eg-tamam="' + e.id + '"' + (e.benim_durum ? ' checked' : '') + '> <span>Tamamladım</span></label>' : '') +
                    (j.yonet ? '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk" data-eg-duz="' + e.id + '"><i class="fa-solid fa-pen"></i> Düzenle</button> <button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-eg-sil="' + e.id + '"><i class="fa-solid fa-trash"></i> Sil</button></div>' : '') + '</div>';
            }).join('') : bosKutu('fa-graduation-cap', 'Eğitim veya toplantı kaydı yok');
            icerik.innerHTML = ust + '<div class="ikm-izgara">' + liste + '</div>';
            if ($('#ik-eg-ekle')) $('#ik-eg-ekle').onclick = function () { egitimFormu(null); };
            $$('[data-eg-duz]', icerik).forEach(function (b) { b.onclick = function () { egitimFormu(egListe.filter(function (e) { return String(e.id) === b.dataset.egDuz; })[0]); }; });
            $$('[data-eg-tamam]', icerik).forEach(function (c) { c.onchange = function () { gonder('egitim_katilim_guncelle', { egitim_id: c.dataset.egTamam, tamamlandi: c.checked ? 1 : 0 }).then(function () { bildir('Güncellendi.', 'basari'); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
            $$('[data-eg-sil]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Kayıt silinsin mi?', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('egitim_sil', { id: b.dataset.egSil }).then(function () { egitimlerCiz(); }); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    function egitimFormu(d) {
        var yeni = !d; d = d || { tur: 'egitim', bildirim_ac: true, bildirim_sms: true, bildirim_mail: true, bildirim_gunler: ['olustur', '1', '0'], katilimcilar: [] };
        var secili = {}; (d.katilimcilar || []).forEach(function (k) { secili[k.id] = 1; });
        var hazirUyari = (!egHazir.sms || !egHazir.mail) ? '<p class="ikm-soluk kucuk"><i class="fa-solid fa-circle-info"></i> ' + (!egHazir.sms ? 'SMS ayarı yapılmadığı için SMS gitmez. ' : '') + (!egHazir.mail ? 'E-posta ayarı yapılmadığı için e-posta gitmez. ' : '') + 'Ayarlar → Bildirim kanalları bölümünden tanımlayabilirsiniz.</p>' : '';
        var m = modal({ baslik: yeni ? 'Eğitim / toplantı ekle' : 'Düzenle', ikon: 'fa-graduation-cap', gen: 560,
            govde: '<div class="ikm-form"><div class="ikm-iki"><label class="ikm-alan"><span>Tür</span><select name="tur"><option value="egitim"' + (d.tur === 'egitim' ? ' selected' : '') + '>Eğitim</option><option value="toplanti"' + (d.tur === 'toplanti' ? ' selected' : '') + '>Toplantı</option></select></label>' +
                '<label class="ikm-alan"><span>Ad <b>*</b></span><input type="text" name="ad" value="' + esc(d.ad || '') + '"></label></div>' +
                '<div class="ikm-iki"><label class="ikm-alan"><span>Tarih</span><input type="date" name="tarih" value="' + esc(d.tarih || '') + '"></label><label class="ikm-alan"><span>Saat</span><input type="time" name="saat" value="' + esc(d.saat || '') + '"></label></div>' +
                '<div class="ikm-iki"><label class="ikm-alan"><span>Yer / bağlantı</span><input type="text" name="yer" maxlength="200" value="' + esc(d.yer || '') + '" placeholder="Toplantı odası, Teams bağlantısı…"></label><label class="ikm-alan"><span>Eğitmen / yürüten</span><input type="text" name="egitmen" value="' + esc(d.egitmen || '') + '"></label></div>' +
                '<label class="ikm-alan"><span>Süre (saat)</span><input type="number" step="0.5" name="sure_saat" value="' + (d.sure_saat || '') + '"></label><label class="ikm-alan"><span>Açıklama</span><textarea name="aciklama" rows="2">' + esc(d.aciklama || '') + '</textarea></label>' +
                '<div class="ikm-alan"><span>Katılımcılar</span><div class="ikm-kutu-liste">' + (V.kisiler || []).map(function (k) { return '<label class="ikm-onay-kutu"><input type="checkbox" value="' + k.id + '" class="ik-eg-kat"' + (secili[k.id] ? ' checked' : '') + '> <span>' + esc(k.ad) + '</span></label>'; }).join('') + '</div></div>' +
                '<div class="ikm-alan"><label class="ikm-onay-kutu"><input type="checkbox" id="ik-eg-bil"' + (d.bildirim_ac ? ' checked' : '') + '> <span><b>Katılımcılara bildirim gitsin</b></span></label>' +
                '<div id="ik-eg-bil-kutu" style="margin:8px 0 0 4px;display:' + (d.bildirim_ac ? 'block' : 'none') + '">' +
                '<div class="ikm-soluk kucuk" style="margin-bottom:4px">Hangi kanaldan?</div><div class="ikm-lejant" style="gap:14px"><label class="ikm-onay-kutu"><input type="checkbox" id="ik-eg-sms"' + (d.bildirim_sms ? ' checked' : '') + '> <span>SMS</span></label><label class="ikm-onay-kutu"><input type="checkbox" id="ik-eg-mail"' + (d.bildirim_mail ? ' checked' : '') + '> <span>E-posta</span></label><span class="ikm-soluk kucuk">(site içi bildirim her zaman gider)</span></div>' +
                '<div class="ikm-soluk kucuk" style="margin:10px 0 4px">Hangi günlerde?</div><div class="ikm-lejant" style="gap:14px">' + EG_GUN.map(function (g) { return '<label class="ikm-onay-kutu"><input type="checkbox" class="ik-eg-gun" value="' + g[0] + '"' + ((d.bildirim_gunler || []).indexOf(g[0]) >= 0 ? ' checked' : '') + '> <span>' + g[1] + '</span></label>'; }).join('') + '</div>' + hazirUyari + '</div></div></div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-eg-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        $('#ik-eg-bil', m.el).onchange = function () { $('#ik-eg-bil-kutu', m.el).style.display = this.checked ? 'block' : 'none'; };
        $('#ik-eg-kaydet', m.el).onclick = function () {
            var v = {}; $$('input[name],select[name],textarea[name]', m.el).forEach(function (i) { v[i.name] = i.value; });
            if (!v.ad.trim()) { bildir('Adını yazın.', 'uyari'); return; }
            if (d.id) v.id = d.id;
            v.katilimcilar = $$('.ik-eg-kat:checked', m.el).map(function (c) { return c.value; }).join(',');
            v.bildirim_ac = $('#ik-eg-bil', m.el).checked ? 1 : 0; v.bildirim_sms = $('#ik-eg-sms', m.el).checked ? 1 : 0; v.bildirim_mail = $('#ik-eg-mail', m.el).checked ? 1 : 0;
            v.bildirim_gunler = $$('.ik-eg-gun:checked', m.el).map(function (c) { return c.value; }).join(',');
            if (v.bildirim_ac && !v.bildirim_gunler) { bildir('Bildirim için en az bir gün seçin ya da bildirimi kapatın.', 'uyari'); return; }
            if (v.bildirim_ac && !v.bildirim_sms && !v.bildirim_mail) { bildir('SMS veya e-postadan en az birini seçin (yoksa yalnız site içi gider).', 'uyari'); }
            gonder('egitim_kaydet', v).then(function (r) { m.kapat(); bildir('Kaydedildi.' + (r.bildirim ? ' ' + r.bildirim + ' bildirim gönderiliyor.' : ''), 'basari'); egitimlerCiz(); }).catch(function (er) { bildir(er.message, 'hata'); });
        };
    }
    kayit('egitimler', 'Eğitimler', 'Eğitim kayıtları ve katılım durumları.', egitimlerCiz);

    /* ======================= PERFORMANS ======================= */
    var pf = { kid: null };
    function performansCiz() {
        icerik.innerHTML = yukleniyor();
        var p = { islem: 'performans' }; if (pf.kid) p.kullanici_id = pf.kid;
        getir(p).then(function (j) {
            pf.kid = j.kullanici_id;
            var secim = j.yonet ? '<select id="ik-pf-kisi">' + (V.kisiler || []).map(function (k) { return '<option value="' + k.id + '"' + (k.id === j.kullanici_id ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' : '';
            var ust = '<div class="ikm-arac">' + secim + (j.yonet ? '<button type="button" class="ikm-btn ikm-btn-ana" id="ik-pf-ekle" style="margin-left:auto"><i class="fa-solid fa-plus"></i> Değerlendirme ekle</button>' : '') + '</div>';
            var liste = j.liste.length ? j.liste.map(function (p) {
                return '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-star"></i> ' + esc(p.donem) + '</h3><span class="ikm-puan">★ ' + sayi(p.puan) + '<small>/10</small></span></div>' +
                    '<div class="ikm-ilerleme"><i class="k" style="width:' + (p.puan * 10) + '%;background:linear-gradient(90deg,#f59e0b,#f43f5e)"></i></div>' +
                    '<p class="ikm-soluk kucuk">' + tarih(p.tarih) + (p.degerlendiren ? ' · ' + esc(p.degerlendiren) : '') + '</p>' + (p.notlar ? '<p>' + esc(p.notlar) + '</p>' : '') +
                    (j.yonet ? '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-pf-sil="' + p.id + '"><i class="fa-solid fa-trash"></i></button></div>' : '') + '</div>';
            }).join('') : bosKutu('fa-star', 'Değerlendirme yok');
            icerik.innerHTML = ust + '<div class="ikm-izgara">' + liste + '</div>';
            if ($('#ik-pf-kisi')) $('#ik-pf-kisi').onchange = function () { pf.kid = +this.value; performansCiz(); };
            if ($('#ik-pf-ekle')) $('#ik-pf-ekle').onclick = function () { performansFormu(pf.kid); };
            $$('[data-pf-sil]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Değerlendirme silinsin mi?', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('performans_sil', { id: b.dataset.pfSil }).then(function () { performansCiz(); }); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    function performansFormu(kid) {
        var m = modal({ baslik: 'Değerlendirme ekle', ikon: 'fa-star', gen: 480,
            govde: '<div class="ikm-form"><label class="ikm-alan"><span>Dönem <b>*</b></span><input type="text" name="donem" placeholder="Örn. 2026 Q3"></label><label class="ikm-alan"><span>Puan (0-10)</span><input type="number" step="0.5" min="0" max="10" name="puan"></label><label class="ikm-alan"><span>Notlar</span><textarea name="notlar" rows="3"></textarea></label></div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-pf-k"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        $('#ik-pf-k', m.el).onclick = function () {
            var v = { kullanici_id: kid }; $$('[name]', m.el).forEach(function (i) { v[i.name] = i.value; });
            if (!v.donem.trim()) { bildir('Dönemi yazın.', 'uyari'); return; }
            gonder('performans_kaydet', v).then(function () { m.kapat(); bildir('Değerlendirme kaydedildi.', 'basari'); performansCiz(); }).catch(function (er) { bildir(er.message, 'hata'); });
        };
    }
    kayit('performans', 'Performans değerlendirmeleri', 'Dönemsel puanlar ve notlar.', performansCiz);

    /* ======================= BORDRO ======================= */
    var bd = { kid: null };
    function bordroCiz() {
        icerik.innerHTML = yukleniyor();
        var p = { islem: 'bordro' }; if (bd.kid) p.kullanici_id = bd.kid;
        getir(p).then(function (j) {
            bd.kid = j.kullanici_id;
            var secim = j.yonet ? '<select id="ik-bd-kisi">' + (V.kisiler || []).map(function (k) { return '<option value="' + k.id + '"' + (k.id === j.kullanici_id ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' : '';
            var ust = '<div class="ikm-arac">' + secim + (j.yonet ? '<button type="button" class="ikm-btn ikm-btn-ana" id="ik-bd-ekle" style="margin-left:auto"><i class="fa-solid fa-plus"></i> Bordro ekle</button>' : '') + '</div>';
            var liste = j.liste.length ? '<div class="ikm-liste">' + j.liste.map(function (b) {
                return '<div class="ikm-satir sabit"><span class="ikm-satir-ikon"><i class="fa-solid fa-file-invoice-dollar"></i></span><div class="ikm-satir-ic"><b>' + AYLAR[b.ay - 1] + ' ' + b.yil + '</b><small>Brüt ' + para(b.brut) + ' · Kesinti ' + para(b.kesinti) + '</small></div>' +
                    '<span class="ikm-tutar">' + para(b.net) + '</span>' + (b.dosya ? '<a class="ikm-btn ikm-btn-kucuk ikm-btn-ikon" href="' + esc(b.dosya) + '" target="_blank" rel="noopener" aria-label="PDF"><i class="fa-solid fa-file-pdf"></i></a>' : '') +
                    (j.yonet ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon ikm-btn-tehlike" data-bd-sil="' + b.id + '" aria-label="Sil"><i class="fa-solid fa-trash"></i></button>' : '') + '</div>';
            }).join('') + '</div>' : bosKutu('fa-file-invoice-dollar', 'Bordro kaydı yok');
            icerik.innerHTML = ust + liste;
            if ($('#ik-bd-kisi')) $('#ik-bd-kisi').onchange = function () { bd.kid = +this.value; bordroCiz(); };
            if ($('#ik-bd-ekle')) $('#ik-bd-ekle').onclick = function () { bordroFormu(bd.kid); };
            $$('[data-bd-sil]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Bordro silinsin mi?', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('bordro_sil', { id: b.dataset.bdSil }).then(function () { bordroCiz(); }); }).catch(function (er) { bildir(er.message, 'hata'); }); }; });
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    function bordroFormu(kid) {
        var d = new Date();
        var m = modal({ baslik: 'Bordro ekle', ikon: 'fa-file-invoice-dollar', gen: 480,
            govde: '<form id="ik-bd-form" class="ikm-form" enctype="multipart/form-data"><div class="ikm-iki"><label class="ikm-alan"><span>Yıl</span><input type="number" name="yil" value="' + d.getFullYear() + '"></label><label class="ikm-alan"><span>Ay (1-12)</span><input type="number" min="1" max="12" name="ay" value="' + (d.getMonth() + 1) + '"></label></div>' +
                '<div class="ikm-iki"><label class="ikm-alan"><span>Brüt (₺)</span><input type="number" step="0.01" name="brut"></label><label class="ikm-alan"><span>Kesinti (₺)</span><input type="number" step="0.01" name="kesinti" value="0"></label></div>' +
                '<label class="ikm-alan"><span>Net (₺)</span><input type="number" step="0.01" name="net"></label><label class="ikm-alan"><span>Bordro PDF <small>(isteğe bağlı)</small></span><input type="file" name="dosya" accept="application/pdf"></label></form>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="submit" form="ik-bd-form" class="ikm-btn ikm-btn-ana"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        $('#ik-bd-form', m.el).addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(e.target); fd.append('islem', 'bordro_ekle'); fd.append('kullanici_id', kid);
            IK.form(fd).then(function () { m.kapat(); bildir('Bordro kaydedildi.', 'basari'); bordroCiz(); }).catch(function (er) { bildir(er.message, 'hata'); });
        });
    }
    kayit('bordro', 'Bordro kayıtları', 'Aylık brüt / net maaş kayıtları ve PDF bordrolar.', bordroCiz);

    /* ======================= İK AYARLARI ======================= */
    function ayarlarCiz() {
        icerik.innerHTML = yukleniyor();
        getir({ islem: 'ayarlar_getir' }).then(function (j) {
            var a = j.ayar;
            icerik.innerHTML = '<div class="ikm-izgara iki">' + (j.ayarlar_yonet ? '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-sitemap"></i> Birimler ve notlar</h3></div><div class="ikm-form">' +
                '<label class="ikm-alan"><span>Birim (departman) listesi <small>her satıra bir birim — onay akışında bu birimler listelenir</small></span><textarea id="ik-ay-dep" rows="6">' + esc(a.departman_listesi || '') + '</textarea></label>' +
                '<label class="ikm-alan"><span>Yıllık izin / genel notlar</span><textarea id="ik-ay-not" rows="3">' + esc(a.aylik_izin_bilgisi || '') + '</textarea></label>' +
                '<div><button type="button" class="ikm-btn ikm-btn-ana" id="ik-ay-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button></div></div></div>' : '') +
                '</div>';
            if ($('#ik-ay-kaydet')) $('#ik-ay-kaydet').onclick = function () {
                gonder('ayarlar_kaydet', { departman_listesi: $('#ik-ay-dep').value, aylik_izin_bilgisi: $('#ik-ay-not').value }).then(function () { bildir('Ayarlar kaydedildi.', 'basari'); }).catch(function (er) { bildir(er.message, 'hata'); });
            };
        }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
    }
    kayit('ayarlar', 'İK ayarları', 'Birim listesi ve genel notlar.', ayarlarCiz);

    /* ======================= ROZETLER · ZİL (bildirim paneli) ======================= */
    function rozetGuncelle() {
        var r = $('#ikm-r-izin'); if (!r || !V) return;
        var n = V.ozet.bana_bekleyen || 0; r.hidden = !n; r.textContent = n;
    }
    function rozetYenile() { return ilkVeri().then(rozetGuncelle).catch(function () {}); }
    function lisansBanner(l) {
        var b = document.getElementById('ikm-lisans');
        if (!l) { if (b) b.remove(); return; }
        if (!b) { b = document.createElement('div'); b.id = 'ikm-lisans'; b.className = 'ikm-lisans'; icerik.parentNode.insertBefore(b, icerik); }
        var t = l.bitis ? l.bitis.split('-').reverse().join('.') : '';
        b.className = 'ikm-lisans ' + (l.durum === 'ek_sure' ? 'kirmizi' : 'turuncu');
        b.innerHTML = '<i class="fa-solid ' + (l.durum === 'ek_sure' ? 'fa-triangle-exclamation' : 'fa-hourglass-half') + '"></i><div>' + (l.durum === 'ek_sure' ?
            '<b>Kullanım süreniz ' + esc(t) + ' tarihinde doldu.</b> Ek süre: <b>' + l.ek_kalan + ' gün</b>. Süre yenilenmezse hesap askıya alınır ve kimse giriş yapamaz.' :
            '<b>Kullanım süreniz ' + esc(t) + ' tarihinde bitiyor</b> (' + (l.kalan === 0 ? 'bugün' : l.kalan + ' gün kaldı') + '). Kesinti yaşamamak için süreyi yenileyin.') + ' <span>Yenilemek için platform yöneticinizle iletişime geçin.</span></div>';
    }
    function ilkVeri() { return getir({ islem: 'baslangic' }).then(function (j) { V = j; Y = j.yetki; lisansBanner(j.lisans_uyari); rozetGuncelle(); return j; }); }

    var zilPanel = $('#ikm-zil-panel'), zilBtn = $('#ikm-zil');
    function zilSayi(n) { var s = $('#ikm-zil-sayi'); s.hidden = !n; s.textContent = n > 99 ? '99+' : n; zilBtn.classList.toggle('dolu', n > 0); }
    function zamanOnce(t) { var d = (Date.now() - new Date(String(t).replace(' ', 'T')).getTime()) / 1000; if (isNaN(d)) return ''; if (d < 60) return 'şimdi'; if (d < 3600) return Math.floor(d / 60) + ' dk önce'; if (d < 86400) return Math.floor(d / 3600) + ' sa önce'; return Math.floor(d / 86400) + ' gün önce'; }
    function plain(h) { return String(h || '').replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#39;/g, "'"); }
    function zilCiz() {
        if (!window.IKBildirim) { zilPanel.innerHTML = '<div class="ikm-bos"><b>Bildirimler yüklenemedi</b></div>'; return; }
        zilPanel.innerHTML = '<div class="ikm-zil-ust"><b>Bildirimler</b><button type="button" class="ikm-link" id="ikm-zil-hepsi">Tümünü okundu yap</button></div><div class="ikm-zil-liste" id="ikm-zil-liste">' + yukleniyor() + '</div>' +
            '<div class="ikm-zil-alt"><label class="ikm-anahtar-satir kucuk"><span><b>Bildirim sesi</b></span><span class="ikm-anahtar"><input type="checkbox" id="ikm-ses"><i></i></span></label><button type="button" class="ikm-link" id="ikm-ses-dene"><i class="fa-solid fa-volume-high"></i> Sesi dene</button>' +
            '<div class="ikm-zil-push" id="ikm-zil-push"></div></div>';
        var ses = $('#ikm-ses'); ses.checked = IKBildirim.sesAcikMi(); ses.onchange = function () { IKBildirim.sesAyarla(ses.checked); if (ses.checked) IKBildirim.sesCal(); };
        $('#ikm-ses-dene').onclick = function () { IKBildirim.sesCal(); };
        $('#ikm-zil-hepsi').onclick = function () { IKBildirim.okundu(); $$('#ikm-zil-liste .okunmadi').forEach(function (x) { x.classList.remove('okunmadi'); }); };
        pushKutu();
        IKBildirim.liste().then(function (j) {
            zilSayi(j.okunmamis);
            $('#ikm-zil-liste').innerHTML = j.items.length ? j.items.map(function (b) {
                return '<button type="button" class="ikm-zil-oge' + (b.okundu ? '' : ' okunmadi') + '" data-id="' + b.id + '" data-link="' + esc(b.link) + '"><span class="ikm-zil-nokta"></span><span><b>' + esc(plain(b.baslik)) + '</b><small>' + esc(plain(b.mesaj)) + '</small><em>' + zamanOnce(b.tarih) + '</em></span></button>';
            }).join('') : bosKutu('fa-bell-slash', 'Henüz bildirim yok');
        }).catch(function () { $('#ikm-zil-liste').innerHTML = bosKutu('fa-triangle-exclamation', 'Yüklenemedi'); });
    }
    function pushKutu() {
        var k = $('#ikm-zil-push'); if (!k || !window.IKBildirim) return;
        IKBildirim.pushDurum().then(function (d) {
            if (d === 'acik') { k.innerHTML = '<div class="ikm-push ok"><i class="fa-solid fa-mobile-screen-button"></i> Site kapalıyken de bildirim alıyorsunuz. <button type="button" class="ikm-link" id="ikm-push-kapat">Kapat</button></div>'; $('#ikm-push-kapat').onclick = function () { IKBildirim.pushKapat().then(pushKutu); }; }
            else if (d === 'desteklenmiyor') k.innerHTML = '<div class="ikm-push"><i class="fa-solid fa-circle-info"></i> Bu tarayıcı site kapalıyken bildirimi desteklemiyor.</div>';
            else if (d === 'engelli') k.innerHTML = '<div class="ikm-push uyari"><i class="fa-solid fa-ban"></i> Bildirimler tarayıcıdan engellenmiş. Adres çubuğundaki kilit simgesinden izin verin.</div>';
            else { k.innerHTML = '<button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" id="ikm-push-ac" style="width:100%"><i class="fa-solid fa-bell"></i> Telefon / bilgisayar bildirimlerini aç</button><small class="ikm-soluk kucuk">Site kapalıyken ve arka plandayken de uyarı gelir.</small>'; $('#ikm-push-ac').onclick = function () { IKBildirim.pushAc().then(function () { bildir('Bildirimler açıldı. Artık site kapalıyken de haber alacaksınız.', 'basari'); pushKutu(); }).catch(function (e) { bildir(e.message, 'uyari'); }); }; }
        });
    }
    zilBtn.addEventListener('click', function (e) { e.stopPropagation(); var ac = zilPanel.hidden; zilPanel.hidden = !ac; zilBtn.setAttribute('aria-expanded', ac); if (ac) zilCiz(); });
    zilPanel.addEventListener('click', function (e) {
        e.stopPropagation(); var o = e.target.closest('.ikm-zil-oge'); if (!o) return;
        IKBildirim.okundu(+o.dataset.id); o.classList.remove('okunmadi'); zilPanel.hidden = true; if (o.dataset.link && !git(o.dataset.link)) location.href = o.dataset.link;
    });
    document.addEventListener('click', function (e) { if (!zilPanel.hidden && !e.target.closest('.ikm-zil-kap')) zilPanel.hidden = true; });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !zilPanel.hidden) zilPanel.hidden = true; });
    if (window.IKBildirim) {
        IKBildirim.on(function (o) {
            if (o.tip === 'sayi') zilSayi(o.okunmamis);
            if (o.tip === 'yeni') {
                if (!zilPanel.hidden) zilCiz();
                // İzinle ilgili yeni bildirim geldiyse ekrandaki liste / rozetler tazelensin
                if (o.items.some(function (b) { return /^ik_izin/.test(b.tur); })) { rozetYenile(); if (aktifSekme === 'izinler') izinYukle(); else if (aktifSekme === 'genel') genelCiz(); }
            }
        });
        IKBildirim.liste().then(function (j) { zilSayi(j.okunmamis); }).catch(function () {});
    }

    /* ======================= BAŞLAT ======================= */
    $('#ik-izin-yeni').onclick = function () { if (V) talepOlustur(); };
    $('#ik-kartim').onclick = function () { if (V) kartAc(V.ben); };
    var yb = $('#ik-yetkiler'); if (yb) yb.onclick = function () { menuKapat(); if (window.TbYetkiPanel) TbYetkiPanel.ac('ik'); };
    ilkVeri().then(function (j) {
        var p = new URLSearchParams(location.search), s = p.get('sekme');
        if (p.get('kapsam') && s === 'izinler') izinF.kapsam = p.get('kapsam');
        if (s === 'izinler' && !p.get('kapsam') && j.ozet.bana_bekleyen > 0) izinF.kapsam = 'bana';
        // Eklenti dosyaları (defer) kendini kaydettikten sonra açılsın
        var ac = function () {
            var bulundu = s && (document.querySelector('#ik-sekmeler [data-s="' + s + '"]') || ({ izin_rapor: 'izinler', izin_kural: 'izinler', unvanlar: 'birimler' }[s] && document.querySelector('#ik-sekmeler [data-s="' + { izin_rapor: 'izinler', izin_kural: 'izinler', unvanlar: 'birimler' }[s] + '"]'))) && GORUNUM[s];
            sekmeAc(bulundu ? s : 'genel', (p.get('talep') || p.get('id')) ? { talep: p.get('talep'), id: p.get('id') } : null);
            if (p.get('talep') && s === 'izinler') izinDetay(+p.get('talep'));
            if (p.get('personel')) kartAc(+p.get('personel'));
        };
        if (document.readyState === 'complete') setTimeout(ac, 0); else window.addEventListener('load', function () { setTimeout(ac, 0); });
    }).catch(function (e) { icerik.innerHTML = hataKutu(e.message); });
})();

/* Uzun personel listelerinde (8+ seçenek) seçim kutusunun üstüne arama çubuğu ekler */
(function () {
    var kucuk = function (s) { return String(s || '').toLocaleLowerCase('tr-TR').replace(/i̇/g, 'i'); };
    function guclendir(sel) {
        if (sel.dataset.araDone || sel.dataset.ara === 'yok' || sel.multiple || sel.options.length < 8) return;
        if (!/kullanici_id|vekil_id|gf-kisi|kisi|personel/i.test((sel.name || '') + ' ' + (sel.id || ''))) return;
        sel.dataset.araDone = '1';
        var tum = Array.prototype.map.call(sel.options, function (o) { return { v: o.value, t: o.textContent, s: o.defaultSelected }; });
        var q = document.createElement('input');
        q.type = 'search'; q.placeholder = 'Personel ara…'; q.autocomplete = 'off'; q.setAttribute('aria-label', 'Personel ara');
        q.style.marginBottom = '6px';
        sel.parentNode.insertBefore(q, sel);
        q.addEventListener('input', function () {
            var a = kucuk(q.value).trim(), secili = sel.value, html = '', n = 0;
            tum.forEach(function (o) {
                if (o.v === '' || !a || kucuk(o.t).indexOf(a) > -1 || o.v === secili) { html += '<option value="' + o.v.replace(/"/g, '&quot;') + '">' + o.t.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</option>'; if (o.v !== '') n++; }
            });
            sel.innerHTML = html || '<option value="">Sonuç yok</option>';
            sel.value = secili;
            if (a && n && sel.value === secili && sel.options.length > 1) { /* seçili kişi korunur */ }
            if (a) { var ilk = Array.prototype.filter.call(sel.options, function (o) { return o.value !== '' && kucuk(o.textContent).indexOf(a) > -1; })[0]; if (ilk) sel.value = ilk.value; }
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        });
        q.addEventListener('keydown', function (e) { if (e.key === 'Enter') e.preventDefault(); });
    }
    function tara(k) { Array.prototype.forEach.call((k.querySelectorAll ? k.querySelectorAll('.ikm-form select') : []), guclendir); }
    new MutationObserver(function (l) { l.forEach(function (m) { Array.prototype.forEach.call(m.addedNodes, function (n) { if (n.nodeType === 1) tara(n); }); }); })
        .observe(document.documentElement, { childList: true, subtree: true });
})();
