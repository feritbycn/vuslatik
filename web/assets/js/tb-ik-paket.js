/* ============================================================
 * tb-ik-paket.js — Paketlerim, paket vitrini (kilitli modüller, Bilgi al), kilitli modül sayfası
 * ============================================================ */
(function () {
    'use strict';
    var M = window.IKM; if (!M) return;
    var $ = M.$, $$ = M.$$, esc = M.esc, getir = M.getir, gonder = M.gonder, bildir = M.bildir, modal = M.modal;
    var PARA = { TRY: '₺', USD: '$', EUR: '€' }, KAT = {}, VERI = null, DONEM = 'aylik';
    function fiyat(v, para) { v = +v || 0; var s = v.toLocaleString('tr-TR', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 }); return para === 'TRY' ? s + ' ₺' : (PARA[para] || '') + s; }
    function tarih(t) { var m = String(t || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? m[3] + '.' + m[2] + '.' + m[1] : '—'; }
    function chip(k) { var m = KAT[k] || { ad: k, renk: '#64748b', ikon: 'fa-cube' }; return '<span class="pk-chip" style="--c:' + m.renk + '"><i class="fa-solid ' + m.ikon + '"></i>' + esc(m.ad) + '</span>'; }
    function fiyatHtml(p) {
        if (p.fiyat_gizli) return '<div class="pk-yok"><i class="fa-solid fa-circle-info"></i> Fiyat için bilgi alın</div>';
        if (p.fiyat_yok) return '<div class="pk-yok"><i class="fa-solid fa-tag"></i> Fiyat için bilgi alın</div>';
        var d = DONEM, f = p[d], aylikHal = d === 'aylik';
        return '<div class="pk-net">' + (f.indirim > 0 ? '<s>' + fiyat(f.liste, p.para) + '</s>' : '') + '<b>' + fiyat(f.net, p.para) + '</b><small>/' + (aylikHal ? 'ay' : 'yıl') + '</small></div>' +
            (f.indirim > 0 ? '<em class="pk-ind-rozet">Size özel indirim</em>' : '') + (!aylikHal && p.yillik_avantaj > 0 ? '<em class="pk-av-rozet">%' + p.yillik_avantaj + ' daha uygun</em>' : '');
    }
    function talep(p) {
        var m = modal({ baslik: 'Bilgi al — ' + (p ? p.ad : 'Paketler'), ikon: 'fa-paper-plane', gen: 480, govde: '<div class="ikm-form"><div class="ikm-segment" id="tl-d"><button type="button" data-d="aylik" class="' + (DONEM === 'aylik' ? 'aktif' : '') + '">Aylık</button><button type="button" data-d="yillik" class="' + (DONEM === 'yillik' ? 'aktif' : '') + '">Yıllık</button></div><label class="ikm-alan"><span>Mesajınız (isteğe bağlı)</span><textarea id="tl-m" rows="3" maxlength="500" placeholder="Eklemek istediğiniz not…"></textarea></label><p class="ikm-soluk kucuk">Talebiniz yöneticimize iletilir, sizinle iletişime geçilir.</p></div>', alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="tl-g"><i class="fa-solid fa-paper-plane"></i> Gönder</button>' });
        var d = DONEM; $$('#tl-d button', m.el).forEach(function (b) { b.onclick = function () { d = b.dataset.d; $$('#tl-d button', m.el).forEach(function (x) { x.classList.toggle('aktif', x === b); }); }; });
        $('#tl-g', m.el).onclick = function () { gonder('paket_talep', { paket_id: p ? p.id : 0, donem: d, mesaj: $('#tl-m', m.el).value }).then(function () { m.kapat(); bildir('Talebiniz iletildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
    }
    function kart(p) {
        var oran = p.sahip ? Math.max(0, Math.min(100, p.kalan / p.toplam * 100)) : 0, az = p.sahip && p.kalan <= 7;
        var durum = p.sahip && VERI.sure ? '<div class="pk-sure' + (az ? ' az' : '') + '"><div class="pk-sure-ust"><b>' + p.kalan + ' gün kaldı</b><small>Bitiş: ' + tarih(p.bit) + '</small></div><div class="pk-bar"><i style="width:' + oran + '%"></i></div></div>' : (p.bit && VERI.sure ? '<div class="pk-sure bitti"><b>Süresi bitti</b><small>' + tarih(p.bit) + '</small></div>' : '');
        return '<div class="pk-kart' + (p.sahip ? ' sahip' : '') + (p.tam ? ' tam' : '') + '" style="--c:' + esc(p.renk) + '"><div class="pk-ust"><h3>' + (p.tam ? '<i class="fa-solid fa-crown"></i> ' : '') + esc(p.ad) + '</h3><span class="ikd-bosluk"></span>' + (VERI.bilgi ? '<button type="button" class="pk-i" data-i="' + p.id + '" aria-label="Bilgi" title="Bu paket ne işe yarar?"><i class="fa-solid fa-circle-info"></i></button>' : '') + (p.sahip ? '<span class="pk-sahip"><i class="fa-solid fa-check"></i> Aktif</span>' : (VERI.vitrin ? '<i class="fa-solid fa-lock pk-kilit"></i>' : '')) + '</div>' +
            '<p class="pk-ac">' + esc(p.aciklama) + '</p><div class="pk-ayrinti" id="pk-ay-' + p.id + '" hidden>' + p.moduller.map(function (k) { var m = KAT[k] || { ad: k, ikon: 'fa-cube', renk: '#64748b', aciklama: '' }; return '<div class="pk-ay-satir"><i class="fa-solid ' + m.ikon + '" style="color:' + m.renk + '"></i><span><b>' + esc(m.ad) + '</b><small>' + esc(m.aciklama || '') + '</small></span></div>'; }).join('') + '</div><div class="pk-chips">' + p.moduller.map(chip).join('') + '</div>' + durum + (p.sahip && !VERI.vitrin ? '' : '<div class="pk-fiyat">' + fiyatHtml(p) + '</div>') +
            (VERI.vitrin ? '<button type="button" class="ikm-btn ' + (p.sahip ? '' : 'ikm-btn-ana') + ' pk-btn" data-p="' + p.id + '"><i class="fa-solid ' + (p.sahip ? 'fa-rotate' : 'fa-circle-info') + '"></i> ' + (p.sahip ? 'Süre uzat' : 'Bilgi al') + '</button>' : '') + '</div>';
    }
    function paketlerim(ozel) {
        var sadeceInd = !!(ozel && ozel.indirim);
        return getir({ islem: 'paketlerim' }).then(function (j) {
            VERI = j; KAT = j.katalog;
            var sahip = j.paketler.filter(function (p) { return p.sahip; }), diger = j.paketler.filter(function (p) { return !p.sahip; });
            if (sadeceInd) { sahip = []; diger = j.paketler.filter(function (p) { return !p.fiyat_gizli && !p.fiyat_yok && (p.aylik.indirim > 0 || p.yillik.indirim > 0); }); if (diger.length && diger[0].yillik.indirim > 0 && !(diger[0].aylik.indirim > 0)) DONEM = 'yillik'; }
            var donemSec = j.vitrin ? '<div class="ikm-segment pk-donem-sec" id="pk-ds"><button type="button" data-d="aylik" class="' + (DONEM === 'aylik' ? 'aktif' : '') + '">Aylık</button><button type="button" data-d="yillik" class="' + (DONEM === 'yillik' ? 'aktif' : '') + '">Yıllık</button></div>' : '';
            M.icerik.innerHTML = '<div class="pk-sayfa">' +
                '<div class="pk-bilgi">' + (j.modu ? '<i class="fa-solid fa-cube"></i> Kurumunuz paket modunda: yalnızca paketinizdeki modüller açıktır. Veriler hiçbir zaman silinmez; süre bitince modül kilitlenir, yenileyince geri açılır.' : '<i class="fa-solid fa-circle-check"></i> Tüm modüller şu an kullanımınıza açık.') + '</div>' +
                (sadeceInd ? '<div class="pk-bas-satir"><h3 class="pk-bas"><i class="fa-solid fa-percent"></i> İndirimli paketler</h3><button type="button" class="ikm-btn ikm-btn-kucuk" id="pk-tum">Tüm paketleri göster</button></div>' : '<h3 class="pk-bas"><i class="fa-solid fa-circle-check"></i> Paketlerim</h3>' + (sahip.length ? '<div class="pk-izgara">' + sahip.map(kart).join('') + '</div>' : '<p class="ikm-soluk">Henüz aktif paketiniz yok.</p>')) +
                (diger.length ? '<div class="pk-bas-satir">' + (sadeceInd ? '' : '<h3 class="pk-bas"><i class="fa-solid fa-store"></i> ' + (j.vitrin ? 'Diğer paketler' : 'Süresi biten paketler') + '</h3>') + donemSec + '</div><div class="pk-izgara">' + diger.map(kart).join('') + '</div>' : (j.vitrin ? '<div class="pk-bas-satir">' + donemSec + '</div>' : '')) + '</div>';
            $$('#pk-ds button', M.icerik).forEach(function (b) { b.onclick = function () { DONEM = b.dataset.d; paketlerim(ozel); }; });
            var tum = $('#pk-tum', M.icerik); if (tum) tum.onclick = function () { paketlerim(); };
            $$('[data-i]', M.icerik).forEach(function (b) { b.onclick = function () { var e = document.getElementById('pk-ay-' + b.dataset.i); if (e) { e.hidden = !e.hidden; b.classList.toggle('acik', !e.hidden); } }; });
            $$('[data-p]', M.icerik).forEach(function (b) { b.onclick = function () { talep(j.paketler.filter(function (p) { return String(p.id) === b.dataset.p; })[0]); }; });
        }).catch(function (e) { M.icerik.innerHTML = M.hataKutu(e.message); });
    }
    function kilitSayfa(anahtar, ad) {
        return function () {
            M.icerik.innerHTML = '<div class="pk-kilit-sayfa"><span class="pk-kilit-ikon"><i class="fa-solid fa-lock"></i></span><h2>' + esc(ad) + ' paketinizde yok</h2><p>Bu modül kurumunuzun paketinde bulunmuyor ya da süresi doldu. Verileriniz silinmedi; modül açıldığında olduğu gibi devam eder.</p><div id="pk-kilit-ek"></div></div>';
            var y = M.yetki() || {}, v = M.veri() || {}, p = v.paket || {};
            if (p.gorunur && (y.kurucu || y.ik_ayarlar)) { $('#pk-kilit-ek', M.icerik).innerHTML = '<button type="button" class="ikm-btn ikm-btn-ana" id="pk-git"><i class="fa-solid fa-cube"></i> Paketlerime git</button>'; $('#pk-git', M.icerik).onclick = function () { M.sekmeAc('paketlerim'); }; }
        };
    }
    M.kayit('paketlerim', 'Paketlerim', 'Aktif paketleriniz, kalan süre ve diğer paketler.', function (o) { return paketlerim(o); });
    var onceki = M.sekmeDegisiyor;
    M.sekmeDegisiyor = function (eski, yeni) {
        var v = M.veri() || {}, kil = v.kilitli || [];
        if (kil.indexOf(yeni) >= 0) { var nav = document.querySelector('#ik-sekmeler [data-s="' + yeni + '"]'); var ad = nav ? nav.textContent.replace(/\s+/g, ' ').trim() : yeni; M.kayit(yeni, ad, 'Bu modül paketinizde yok.', kilitSayfa(yeni, ad)); }
        if (yeni === 'genel') setTimeout(banner, 700);
        if (onceki) onceki(eski, yeni);
    };
    /* indirim duyurusu (ana sayfa) */
    function indMetin(d) {
        var a = d.aylik > 0 ? (d.aylik_tip === 'tl' ? d.aylik.toLocaleString('tr-TR') + ' ₺' : '%' + d.aylik) : '', y = d.yillik > 0 ? (d.yillik_tip === 'tl' ? d.yillik.toLocaleString('tr-TR') + ' ₺' : '%' + d.yillik) : '';
        return a && y ? (a === y ? a + ' indirim' : 'aylıkta ' + a + ', yıllıkta ' + y + ' indirim') : (a ? 'aylık paketlerde ' + a + ' indirim' : 'yıllık paketlerde ' + y + ' indirim');
    }
    function banner() {
        var v = M.veri(), d = v && v.paket && v.paket.indirim, y = M.yetki() || {}; if (!d || !(y.kurucu || y.ik_ayarlar) || document.getElementById('pk-banner')) return;
        var anahtar = 'pk_ind_' + JSON.stringify(d); try { if (localStorage.getItem(anahtar)) return; } catch (e) {}
        var el = document.createElement('div'); el.id = 'pk-banner'; el.className = 'pk-banner';
        el.innerHTML = '<span class="pk-banner-ikon"><i class="fa-solid fa-percent"></i></span><button type="button" class="pk-banner-metin"><b>Paketlerde ' + esc(indMetin(d)) + ' sizi bekliyor!</b><small>İncelemek için tıklayın</small></button><button type="button" class="ikm-ikon-btn pk-banner-x" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button>';
        M.icerik.insertBefore(el, M.icerik.firstChild);
        $('.pk-banner-metin', el).onclick = function () { M.sekmeAc('paketlerim', { indirim: true }); };
        $('.pk-banner-x', el).onclick = function () { try { localStorage.setItem(anahtar, '1'); } catch (e) {} el.remove(); };
    }
    /* bitmek üzere olan paket uyarısı */
    function uyar() {
        var v = M.veri(); if (!v || !v.paket || !v.paket.sure_goster || !v.paket.bitiyor || !v.paket.bitiyor.length) return;
        var k = v.paket.bitiyor[0]; try { if (sessionStorage.getItem('pk_uyari') === k.ad + k.bit) return; sessionStorage.setItem('pk_uyari', k.ad + k.bit); } catch (e) {}
        bildir('“' + k.ad + '” paketinizin bitmesine ' + k.kalan + ' gün kaldı.', 'uyari');
    }
    setTimeout(uyar, 1800);
})();
