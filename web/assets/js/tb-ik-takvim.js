/* ============================================================
 * tb-ik-takvim.js — Takvim (izin, özel gün, etkinlik, not, mülakat) ve İşe alım (aday hunisi)
 * ============================================================ */
(function () {
    'use strict';
    var M = window.IKM; if (!M) return;
    var $ = M.$, $$ = M.$$, esc = M.esc, getir = M.getir, gonder = M.gonder, bildir = M.bildir, modal = M.modal;
    var AY = M.aylar, GUNK = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
    function z2(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + z2(d.getMonth() + 1) + '-' + z2(d.getDate()); }
    function dt(s) { return new Date(s + 'T00:00:00'); }
    function ekle(d, n) { var x = new Date(d); x.setDate(x.getDate() + n); return x; }
    function pazartesi(d) { var x = new Date(d); x.setDate(x.getDate() - ((x.getDay() + 6) % 7)); return x; }
    function nz(x) { return String(x || '').toLocaleLowerCase('tr-TR'); }

    /* ======================= TAKVİM ======================= */
    var TIP = {
        izin: { ad: 'İzin', renk: '#10b981', ikon: 'fa-umbrella-beach', grup: 'izin' }, tatil: { ad: 'Resmî tatil', renk: '#ef4444', ikon: 'fa-flag', grup: 'ozel' },
        dogum: { ad: 'Doğum günü', renk: '#ec4899', ikon: 'fa-cake-candles', grup: 'ozel' }, yildonumu: { ad: 'İş yıldönümü', renk: '#8b5cf6', ikon: 'fa-award', grup: 'ozel' },
        etkinlik: { ad: 'Eğitim', renk: '#6366f1', ikon: 'fa-graduation-cap', grup: 'etkinlik' }, toplanti: { ad: 'Toplantı', renk: '#0ea5e9', ikon: 'fa-people-group', grup: 'etkinlik' },
        not: { ad: 'Not', renk: '#f59e0b', ikon: 'fa-note-sticky', grup: 'not' }, mulakat: { ad: 'Mülakat', renk: '#14b8a6', ikon: 'fa-user-tie', grup: 'mulakat' }
    };
    var GRUP = [['izin', 'İzinler', '#10b981'], ['ozel', 'Özel günler', '#ef4444'], ['etkinlik', 'Etkinlikler', '#6366f1'], ['not', 'Notlarım', '#f59e0b'], ['mulakat', 'Mülakatlar', '#14b8a6']];
    var T = { gorunum: 'ay', tarih: new Date(), sec: ymd(new Date()), filtre: { izin: 1, ozel: 1, etkinlik: 1, not: 1, mulakat: 1 }, ev: [], yetki: {}, ara: '' };
    try { var kg = JSON.parse(localStorage.getItem('tb_ik_takvim') || '{}'); if (kg.gorunum) T.gorunum = kg.gorunum; if (kg.filtre) T.filtre = Object.assign(T.filtre, kg.filtre); } catch (e) {}
    function kaydetTercih() { try { localStorage.setItem('tb_ik_takvim', JSON.stringify({ gorunum: T.gorunum, filtre: T.filtre })); } catch (e) {} }
    function aralik() {
        var a = T.tarih, bas, bit;
        if (T.gorunum === 'ay') { bas = pazartesi(new Date(a.getFullYear(), a.getMonth(), 1)); bit = ekle(bas, 41); }
        else if (T.gorunum === 'hafta') { bas = pazartesi(a); bit = ekle(bas, 6); } else { bas = new Date(a); bit = new Date(a); }
        return [bas, bit];
    }
    function takvimCiz() {
        var ic = M.icerik; if (!$('.tk', ic)) ic.innerHTML = M.yukleniyor();
        var r = aralik();
        getir({ islem: 'takvim', bas: ymd(r[0]), bit: ymd(r[1]) }).then(function (j) { T.ev = j.olaylar; T.yetki = j.yetki; govde(); }).catch(function (e) { ic.innerHTML = M.hataKutu(e.message); });
    }
    M.takvimYenile = takvimCiz;
    function gorunenler() { return T.ev.filter(function (o) { return T.filtre[TIP[o.tip].grup]; }); }
    function gunOlay(t, liste) {
        return liste.filter(function (o) { return t >= o.bas && t <= o.bit; }).sort(function (a, b) { return (a.saat || '99') < (b.saat || '99') ? -1 : 1; });
    }
    function chip(o, kisa) {
        var tp = TIP[o.tip], renk = o.renk || tp.renk;
        return '<button type="button" class="tk-chip' + (o.bekliyor ? ' bekliyor' : '') + '" data-o="' + T.ev.indexOf(o) + '" style="--c:' + renk + '" title="' + esc(o.baslik + (o.alt ? ' — ' + o.alt : '')) + '"><i class="fa-solid ' + tp.ikon + '"></i>' + (o.saat ? '<em>' + esc(o.saat) + '</em>' : '') + '<span>' + esc(o.baslik) + (kisa ? '' : (o.alt ? ' <small>' + esc(o.alt) + '</small>' : '')) + '</span></button>';
    }
    function govde() {
        var ic = M.icerik, liste = gorunenler(), r = aralik(), bugun = ymd(new Date()), baslik;
        if (T.gorunum === 'ay') baslik = AY[T.tarih.getMonth()] + ' ' + T.tarih.getFullYear();
        else if (T.gorunum === 'hafta') baslik = r[0].getDate() + ' ' + AY[r[0].getMonth()] + ' – ' + r[1].getDate() + ' ' + AY[r[1].getMonth()] + ' ' + r[1].getFullYear();
        else baslik = T.tarih.toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', year: 'numeric', weekday: 'long' });
        var sayac = {}; T.ev.forEach(function (o) { var g = TIP[o.tip].grup; sayac[g] = (sayac[g] || 0) + 1; });
        var h = '<div class="tk"><div class="tk-ust"><div class="tk-gez"><button type="button" class="ikm-btn" data-bugun>Bugün</button><button type="button" class="ikm-ikon-btn" data-g="-1" aria-label="Önceki"><i class="fa-solid fa-arrow-left"></i></button><button type="button" class="ikm-ikon-btn" data-g="1" aria-label="Sonraki"><i class="fa-solid fa-arrow-right"></i></button><h3>' + esc(baslik) + '</h3></div>' +
            '<div class="tk-sag"><div class="ikm-segment tk-seg">' + [['ay', 'Aylık'], ['hafta', 'Haftalık'], ['gun', 'Günlük']].map(function (x) { return '<button type="button" data-v="' + x[0] + '" class="' + (T.gorunum === x[0] ? 'aktif' : '') + '">' + x[1] + '</button>'; }).join('') + '</div>' +
            '<button type="button" class="ikm-btn ikm-btn-ana" data-yeni><i class="fa-solid fa-plus"></i> <span>Ekle</span></button></div></div>' +
            '<div class="tk-filtre">' + GRUP.filter(function (g) { return g[0] !== 'mulakat' || T.yetki.mulakat; }).map(function (g) { return '<button type="button" class="tk-f' + (T.filtre[g[0]] ? ' aktif' : '') + '" data-f="' + g[0] + '" style="--c:' + g[2] + '"><i></i>' + g[1] + (sayac[g[0]] ? ' <b>' + sayac[g[0]] + '</b>' : '') + '</button>'; }).join('') + '</div>';
        if (T.gorunum === 'ay') {
            var a = T.tarih.getMonth(), g = '';
            for (var w = 0; w < 6; w++) {
                var ws = ymd(ekle(r[0], w * 7)), we = ymd(ekle(r[0], w * 7 + 6)), seg = [], serit = [];
                liste.forEach(function (o) { if (o.bas !== o.bit && o.bas <= we && o.bit >= ws) seg.push(o); });
                seg.sort(function (x, y) { return x.bas < y.bas ? -1 : x.bas > y.bas ? 1 : (y.bit > x.bit ? 1 : -1); });
                var seritler = [], bar = '';
                seg.forEach(function (o) {
                    var s1 = o.bas > ws ? o.bas : ws, e1 = o.bit < we ? o.bit : we, c1 = Math.round((dt(s1) - dt(ws)) / 864e5), c2 = Math.round((dt(e1) - dt(ws)) / 864e5), ln = 0;
                    while (seritler[ln] !== undefined && seritler[ln] >= c1) ln++;
                    seritler[ln] = c2;
                    var tp = TIP[o.tip], renk = o.renk || tp.renk;
                    bar += '<button type="button" class="tk-bar' + (o.bit > we ? ' devam' : '') + (o.bas < ws ? ' onceki' : '') + (o.bekliyor ? ' bekliyor' : '') + '" data-o="' + T.ev.indexOf(o) + '" style="--c:' + renk + ';left:calc(' + c1 + ' * 100% / 7 + ' + (o.bas < ws ? 0 : 3) + 'px);width:calc(' + (c2 - c1 + 1) + ' * 100% / 7 - ' + ((o.bas < ws ? 0 : 3) + (o.bit > we ? 0 : 3)) + 'px);top:' + (34 + ln * 24) + 'px" title="' + esc(o.baslik + (o.alt ? ' — ' + o.alt : '')) + ' (' + M.tarihKisa(o.bas) + ' – ' + M.tarihKisa(o.bit) + ')">' + (o.bas < ws ? '<i class="fa-solid fa-chevron-left"></i>' : '') + '<span>' + esc(o.baslik) + (o.alt ? ' · ' + esc(o.alt) : '') + '</span>' + (o.bit > we ? '<i class="fa-solid fa-chevron-right"></i>' : '') + '</button>';
                });
                g += '<div class="tk-satir" style="--ln:' + seritler.length + '"><div class="tk-izgara">';
                for (var i = 0; i < 7; i++) {
                    var d = ekle(r[0], w * 7 + i), t = ymd(d), tum = gunOlay(t, liste), ol = tum.filter(function (o) { return o.bas === o.bit; }), dis = d.getMonth() !== a, hs = d.getDay() === 0 || d.getDay() === 6;
                    g += '<div class="tk-gun' + (dis ? ' dis' : '') + (t === bugun ? ' bugun' : '') + (t === T.sec ? ' secili' : '') + (hs ? ' hs' : '') + '" data-t="' + t + '"><div class="tk-gun-ust"><b>' + d.getDate() + '</b><button type="button" class="tk-art" data-ekle="' + t + '" aria-label="Ekle"><i class="fa-solid fa-plus"></i></button></div><div class="tk-olaylar">' +
                        ol.slice(0, 3).map(function (o) { return chip(o, true); }).join('') + (ol.length > 3 ? '<button type="button" class="tk-daha" data-t2="' + t + '">+' + (ol.length - 3) + ' daha</button>' : '') + '</div><div class="tk-noktalar">' + tum.slice(0, 5).map(function (o) { return '<i style="background:' + (o.renk || TIP[o.tip].renk) + '"></i>'; }).join('') + '</div></div>';
                }
                g += '</div>' + bar + '</div>';
            }
            h += '<div class="tk-ay"><div class="tk-hbas">' + GUNK.map(function (x) { return '<span>' + x + '</span>'; }).join('') + '</div>' + g + '</div><div class="tk-yan" id="tk-yan"></div>';
        } else if (T.gorunum === 'hafta') {
            h += '<div class="tk-hafta">' + [0, 1, 2, 3, 4, 5, 6].map(function (i) {
                var d = ekle(r[0], i), t = ymd(d), ol = gunOlay(t, liste);
                return '<div class="tk-hg' + (t === bugun ? ' bugun' : '') + '"><div class="tk-hg-ust"><span>' + GUNK[i] + '</span><b>' + d.getDate() + '</b><button type="button" class="tk-art" data-ekle="' + t + '" aria-label="Ekle"><i class="fa-solid fa-plus"></i></button></div><div class="tk-olaylar">' + (ol.map(function (o) { return chip(o); }).join('') || '<div class="tk-bos">—</div>') + '</div></div>';
            }).join('') + '</div>';
        } else {
            var t1 = ymd(T.tarih), ol1 = gunOlay(t1, liste);
            h += '<div class="tk-gunluk">' + (ol1.length ? ol1.map(function (o) { return '<div class="tk-sat">' + chip(o) + '</div>'; }).join('') : M.bosKutu('fa-calendar-day', 'Bu gün için kayıt yok', 'Ekle düğmesiyle not, etkinlik ya da izin girebilirsiniz.')) + '</div>';
        }
        ic.innerHTML = h + '</div>';
        if (T.gorunum === 'ay') yanCiz();
        // olaylar
        $('[data-bugun]', ic).onclick = function () { T.tarih = new Date(); T.sec = ymd(T.tarih); takvimCiz(); };
        $$('[data-g]', ic).forEach(function (b) { b.onclick = function () { var n = +b.dataset.g; if (T.gorunum === 'ay') T.tarih = new Date(T.tarih.getFullYear(), T.tarih.getMonth() + n, 1); else T.tarih = ekle(T.tarih, n * (T.gorunum === 'hafta' ? 7 : 1)); T.sec = ymd(T.tarih); takvimCiz(); }; });
        $$('[data-v]', ic).forEach(function (b) { b.onclick = function () { T.gorunum = b.dataset.v; kaydetTercih(); takvimCiz(); }; });
        $$('[data-f]', ic).forEach(function (b) { b.onclick = function () { T.filtre[b.dataset.f] = T.filtre[b.dataset.f] ? 0 : 1; kaydetTercih(); govde(); }; });
        $('[data-yeni]', ic).onclick = function () { ekleMenu(T.gorunum === 'gun' ? ymd(T.tarih) : T.sec); };
        ic.onclick = function (e) {
            var c = e.target.closest('.tk-chip, .tk-bar'); if (c) { e.stopPropagation(); olayAc(T.ev[+c.dataset.o]); return; }
            var ar = e.target.closest('[data-ekle]'); if (ar) { e.stopPropagation(); ekleMenu(ar.dataset.ekle); return; }
            var dh = e.target.closest('[data-t2]'); if (dh) { gunModal(dh.dataset.t2); return; }
            var gn = e.target.closest('.tk-gun'); if (gn) { T.sec = gn.dataset.t; if (window.innerWidth < 900) { $$('.tk-gun', ic).forEach(function (x) { x.classList.toggle('secili', x === gn); }); yanCiz(); } else gunModal(T.sec); }
        };
    }
    function yanCiz() {
        var p = $('#tk-yan'); if (!p) return;
        var ol = gunOlay(T.sec, gorunenler()), d = dt(T.sec);
        p.innerHTML = '<div class="tk-yan-ust"><b>' + d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', weekday: 'long' }) + '</b><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ana" data-ekle="' + T.sec + '"><i class="fa-solid fa-plus"></i> Ekle</button></div>' + (ol.length ? ol.map(function (o) { return '<div class="tk-sat">' + chip(o) + '</div>'; }).join('') : '<div class="tk-bos">Kayıt yok</div>');
    }
    function gunModal(t) {
        var ol = gunOlay(t, gorunenler()), d = dt(t);
        var m = modal({ baslik: d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', weekday: 'long', year: 'numeric' }), ikon: 'fa-calendar-day', gen: 520, govde: '<div class="tk-modal-liste">' + (ol.length ? ol.map(function (o) { return '<div class="tk-sat">' + chip(o) + '</div>'; }).join('') : M.bosKutu('fa-calendar-day', 'Bu gün için kayıt yok')) + '</div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Kapat</button><button type="button" class="ikm-btn ikm-btn-ana" id="tk-m-ekle"><i class="fa-solid fa-plus"></i> Ekle</button>' });
        $('#tk-m-ekle', m.el).onclick = function () { m.kapat(); setTimeout(function () { ekleMenu(t); }, 180); };
        $('.tk-modal-liste', m.el).onclick = function (e) { var c = e.target.closest('.tk-chip, .tk-bar'); if (c) { var o = T.ev[+c.dataset.o]; m.kapat(); setTimeout(function () { olayAc(o); }, 180); } };
    }
    function olayAc(o) {
        if (o.tip === 'not') { notPencere(o, o.bas); return; }
        if (o.tip === 'izin') { M.git('ik.php?sekme=izinler&talep=' + o.id); return; }
        if (o.tip === 'etkinlik' || o.tip === 'toplanti') { M.sekmeAc('egitimler'); return; }
        if (o.tip === 'mulakat') { adayAc(o.id); return; }
        var tp = TIP[o.tip];
        var m = modal({ baslik: esc(o.baslik), ikon: tp.ikon, gen: 420, govde: '<div class="ikm-alanlar"><div class="ikm-deger"><span>Tür</span><b>' + tp.ad + '</b></div><div class="ikm-deger"><span>Tarih</span><b>' + M.tarih(o.bas) + '</b></div><div class="ikm-deger tam"><span>Ayrıntı</span><b>' + esc(o.alt || '') + '</b></div></div>', alt: '<button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Kapat</button>' });
    }
    function ekleMenu(t) {
        var d = dt(t), sec = [['not', 'fa-note-sticky', 'Not / hatırlatma', 'Kendinize özel not ve bildirim', '#f59e0b', true], ['izin', 'fa-umbrella-beach', 'İzin talebi', 'Bu tarihe izin talebi oluştur', '#10b981', true],
            ['etkinlik', 'fa-people-group', 'Etkinlik / toplantı / eğitim', 'Katılımcılara bildirim gider', '#6366f1', !!T.yetki.etkinlik], ['mulakat', 'fa-user-tie', 'Mülakat', 'Aday için mülakat planla', '#14b8a6', !!T.yetki.mulakat]].filter(function (x) { return x[5]; });
        var m = modal({ baslik: d.toLocaleDateString('tr-TR', { day: 'numeric', month: 'long', weekday: 'long' }) + ' için ekle', ikon: 'fa-circle-plus', gen: 480,
            govde: '<div class="tk-ekle">' + sec.map(function (x) { return '<button type="button" class="ikm-talep-kart" data-e="' + x[0] + '" style="--c:' + x[4] + '"><span><i class="fa-solid ' + x[1] + '"></i></span><div><b>' + x[2] + '</b><small>' + x[3] + '</small></div></button>'; }).join('') + '</div>' });
        $$('[data-e]', m.el).forEach(function (b) { b.onclick = function () { var e = b.dataset.e; m.kapat(); setTimeout(function () {
            if (e === 'not') notPencere(null, t); else if (e === 'izin') M.izinTalebi(null, t); else if (e === 'etkinlik') etkinlikForm(t); else adayAc(0, { mulakat_tarih: t });
        }, 200); }; });
    }
    var RENKLER = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#ec4899'];
    function notPencere(n, tarih) {
        var renk = n ? (n.renk || RENKLER[0]) : RENKLER[0];
        var m = modal({ baslik: n ? 'Notu düzenle' : 'Yeni not', ikon: 'fa-note-sticky', gen: 480, govde:
            '<div class="ikm-form"><label class="ikm-alan"><span>Başlık <b>*</b></span><input type="text" id="n-b" maxlength="160" value="' + esc(n ? n.baslik : '') + '"></label>' +
            '<label class="ikm-alan"><span>Açıklama</span><textarea id="n-i" rows="3" maxlength="2000">' + esc(n ? n.icerik : '') + '</textarea></label>' +
            '<div class="ikm-iki"><label class="ikm-alan"><span>Tarih</span><input type="date" id="n-t" value="' + (n ? n.bas : tarih) + '"></label><label class="ikm-alan"><span>Saat <small>(isteğe bağlı)</small></span><input type="time" id="n-s" value="' + (n ? n.saat || '' : '') + '"></label></div>' +
            '<div class="ikm-alan"><span>Renk</span><div class="ikm-renk-sec" id="n-r">' + RENKLER.map(function (r) { return '<button type="button" data-r="' + r + '" class="' + (r === renk ? 'aktif' : '') + '" style="background:' + r + '" aria-label="' + r + '"></button>'; }).join('') + '</div></div>' +
            '<label class="ikm-onay-satir"><input type="checkbox" id="n-h"' + (!n || n.hatirlat ? ' checked' : '') + '> <span>Hatırlat — bir gün önce ve o gün bildirim gönder</span></label><small class="ikm-soluk">Notlar yalnızca size görünür.</small></div>',
            alt: (n ? '<button type="button" class="ikm-btn ikm-btn-tehlike" id="n-sil"><i class="fa-solid fa-trash"></i> Sil</button>' : '') + '<span class="ikd-bosluk"></span><button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="n-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        $$('#n-r button', m.el).forEach(function (b) { b.onclick = function () { renk = b.dataset.r; $$('#n-r button', m.el).forEach(function (x) { x.classList.toggle('aktif', x === b); }); }; });
        if (n) $('#n-sil', m.el).onclick = function () { gonder('not_sil', { id: n.id }).then(function () { m.kapat(); bildir('Not silindi.', 'basari'); takvimCiz(); }).catch(function (e) { bildir(e.message, 'hata'); }); };
        $('#n-kaydet', m.el).onclick = function () {
            var btn = this; btn.disabled = true;
            gonder('not_kaydet', { id: n ? n.id : 0, baslik: $('#n-b', m.el).value, icerik: $('#n-i', m.el).value, tarih: $('#n-t', m.el).value, saat: $('#n-s', m.el).value, renk: renk, hatirlat: $('#n-h', m.el).checked ? 1 : 0 }).then(function () {
                var tt = $('#n-t', m.el).value; m.kapat(); T.sec = tt; T.tarih = dt(tt); bildir('Not kaydedildi.', 'basari'); takvimCiz();
            }).catch(function (e) { btn.disabled = false; bildir(e.message, 'hata'); });
        };
    }
    function etkinlikForm(t) {
        var kisiler = (M.veri() && M.veri().kisiler) || [], sec = {}, mod = 'herkes';
        var m = modal({ baslik: 'Etkinlik / toplantı ekle', ikon: 'fa-people-group', gen: 560, govde:
            '<div class="ikm-form"><div class="ikm-segment" id="e-tur" style="width:100%"><button type="button" data-t="toplanti" class="aktif" style="flex:1;justify-content:center">Toplantı</button><button type="button" data-t="egitim" style="flex:1;justify-content:center">Eğitim</button></div>' +
            '<label class="ikm-alan"><span>Başlık <b>*</b></span><input type="text" id="e-ad" maxlength="200"></label>' +
            '<div class="ikm-iki"><label class="ikm-alan"><span>Tarih</span><input type="date" id="e-t" value="' + t + '"></label><label class="ikm-alan"><span>Saat</span><input type="time" id="e-s"></label></div>' +
            '<label class="ikm-alan"><span>Yer / bağlantı</span><input type="text" id="e-y" maxlength="200"></label>' +
            '<div class="ikm-alan"><span>Katılımcılar</span><div class="ikm-segment" id="e-mod" style="width:100%"><button type="button" data-m="herkes" class="aktif" style="flex:1;justify-content:center">Tüm personel</button><button type="button" data-m="sec" style="flex:1;justify-content:center">Seçili kişiler</button><button type="button" data-m="ben" style="flex:1;justify-content:center">Yalnız ben</button></div></div>' +
            '<div id="e-kisi" hidden><input type="search" class="ikm-ks-ara" id="e-ara" placeholder="Kişi ara…"><div class="ikm-ist-liste" id="e-l"></div></div></div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="e-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        var tur = 'toplanti';
        $$('#e-tur button', m.el).forEach(function (b) { b.onclick = function () { tur = b.dataset.t; $$('#e-tur button', m.el).forEach(function (x) { x.classList.toggle('aktif', x === b); }); }; });
        $$('#e-mod button', m.el).forEach(function (b) { b.onclick = function () { mod = b.dataset.m; $$('#e-mod button', m.el).forEach(function (x) { x.classList.toggle('aktif', x === b); }); $('#e-kisi', m.el).hidden = mod !== 'sec'; }; });
        function liste() { var a = nz($('#e-ara', m.el).value); $('#e-l', m.el).innerHTML = kisiler.filter(function (k) { return !a || nz(k.ad).indexOf(a) >= 0; }).slice(0, 120).map(function (k) { return '<label class="ikm-onay-satir"><input type="checkbox" value="' + k.id + '"' + (sec[k.id] ? ' checked' : '') + '> <span>' + esc(k.ad) + '</span></label>'; }).join(''); }
        $('#e-ara', m.el).oninput = liste; liste(); $('#e-l', m.el).onchange = function (e) { sec[e.target.value] = e.target.checked; };
        $('#e-kaydet', m.el).onclick = function () {
            var btn = this, ids = mod === 'herkes' ? kisiler.map(function (k) { return k.id; }) : mod === 'ben' ? [M.veri().ben] : Object.keys(sec).filter(function (k) { return sec[k]; });
            if (!ids.length) { bildir('En az bir katılımcı seçin.', 'uyari'); return; }
            btn.disabled = true;
            gonder('egitim_kaydet', { tur: tur, ad: $('#e-ad', m.el).value, tarih: $('#e-t', m.el).value, saat: $('#e-s', m.el).value, yer: $('#e-y', m.el).value, katilimcilar: ids.join(','), bildirim_ac: 1, bildirim_gunler: 'olustur,1,0' }).then(function () {
                var tt = $('#e-t', m.el).value; m.kapat(); T.sec = tt; if (tt) T.tarih = dt(tt); bildir('Etkinlik kaydedildi, katılımcılara haber verildi.', 'basari'); takvimCiz();
            }).catch(function (e) { btn.disabled = false; bildir(e.message, 'hata'); });
        };
    }
    M.kayit('izin_takvimi', 'Takvim', 'İzinler, özel günler, etkinlikler, notlar ve mülakatlar — güne tıklayıp ekleyin.', takvimCiz);
    M.kayit('ajanda', 'Takvim', 'İzinler, özel günler, etkinlikler, notlar ve mülakatlar — güne tıklayıp ekleyin.', takvimCiz);

    /* ======================= İŞE ALIM ======================= */
    var AS = [['basvuru', 'Başvuru', '#64748b', 'fa-inbox'], ['on_eleme', 'Ön eleme', '#0ea5e9', 'fa-filter'], ['mulakat', 'Mülakat', '#14b8a6', 'fa-user-tie'], ['teklif', 'Teklif', '#f59e0b', 'fa-file-signature'], ['ise_alindi', 'İşe alındı', '#10b981', 'fa-circle-check'], ['red', 'Reddedildi', '#ef4444', 'fa-ban']];
    var AD = { liste: [], ara: '', poz: '', red: false };
    function iseAlimCiz() {
        var ic = M.icerik; if (!$('.ia', ic)) ic.innerHTML = M.yukleniyor();
        getir({ islem: 'aday_liste' }).then(function (j) { AD.liste = j.liste; iaGovde(); }).catch(function (e) { ic.innerHTML = M.hataKutu(e.message); });
    }
    function yildiz(n) { var o = ''; for (var i = 1; i <= 5; i++) o += '<i class="fa-' + (i <= n ? 'solid' : 'regular') + ' fa-star"></i>'; return o; }
    function iaGovde() {
        var ic = M.icerik, poz = {}; AD.liste.forEach(function (a) { if (a.pozisyon) poz[a.pozisyon] = 1; });
        var l = AD.liste.filter(function (a) { return (!AD.poz || a.pozisyon === AD.poz) && (!AD.ara || nz(a.ad + ' ' + a.pozisyon + ' ' + a.email + ' ' + a.telefon).indexOf(AD.ara) >= 0); });
        var say = function (k) { return l.filter(function (a) { return a.asama === k; }).length; };
        var h = '<div class="ia"><div class="ikm-arac"><input type="search" id="ia-ara" placeholder="Aday, pozisyon ara…" value="' + esc(AD.ara) + '"><select id="ia-poz"><option value="">Tüm pozisyonlar</option>' + Object.keys(poz).sort().map(function (p) { return '<option' + (p === AD.poz ? ' selected' : '') + '>' + esc(p) + '</option>'; }).join('') + '</select>' +
            '<label class="ikm-onay-satir"><input type="checkbox" id="ia-red"' + (AD.red ? ' checked' : '') + '> <span>Reddedilenleri göster</span></label><button type="button" class="ikm-btn ikm-btn-ana" id="ia-yeni" style="margin-left:auto"><i class="fa-solid fa-user-plus"></i> Aday ekle</button></div>' +
            '<div class="ia-ozet">' + [['Toplam aday', l.length, '#6366f1'], ['Mülakat bekleyen', say('mulakat'), '#14b8a6'], ['Teklif aşamasında', say('teklif'), '#f59e0b'], ['İşe alınan', say('ise_alindi'), '#10b981']].map(function (x) { return '<div style="--c:' + x[2] + '"><b>' + x[1] + '</b><span>' + x[0] + '</span></div>'; }).join('') + '</div>' +
            '<div class="ia-kolonlar">' + AS.filter(function (a) { return a[0] !== 'red' || AD.red; }).map(function (a) {
                var k = l.filter(function (x) { return x.asama === a[0]; });
                return '<section class="ia-kol" data-as="' + a[0] + '" style="--c:' + a[2] + '"><header><span><i class="fa-solid ' + a[3] + '"></i> ' + a[1] + '</span><em>' + k.length + '</em></header><div class="ia-liste">' + (k.map(function (x) {
                    return '<article class="ia-kart" draggable="true" data-id="' + x.id + '"><b>' + esc(x.ad) + '</b><small>' + esc(x.pozisyon || 'Pozisyon belirtilmedi') + '</small>' + (x.puan ? '<div class="ia-puan">' + yildiz(x.puan) + '</div>' : '') +
                        (x.mulakat_tarih ? '<span class="ikm-cip" style="--c:#14b8a6"><i class="fa-regular fa-calendar"></i> ' + M.tarihKisa(x.mulakat_tarih) + (x.mulakat_saat ? ' ' + esc(x.mulakat_saat) : '') + '</span>' : '') + '</article>';
                }).join('') || '<div class="ia-bos">Sürükleyip bırakın</div>') + '</div></section>';
            }).join('') + '</div></div>';
        ic.innerHTML = h;
        var t; $('#ia-ara').oninput = function () { var v = nz(this.value.trim()); clearTimeout(t); t = setTimeout(function () { AD.ara = v; iaGovde(); var a = $('#ia-ara'); a.focus(); a.setSelectionRange(a.value.length, a.value.length); }, 250); };
        $('#ia-poz').onchange = function () { AD.poz = this.value; iaGovde(); }; $('#ia-red').onchange = function () { AD.red = this.checked; iaGovde(); };
        $('#ia-yeni').onclick = function () { adayAc(0); };
        var surukle = null;
        ic.ondragstart = function (e) { var k = e.target.closest('.ia-kart'); if (k) { surukle = k.dataset.id; k.classList.add('suruk'); e.dataTransfer.effectAllowed = 'move'; try { e.dataTransfer.setData('text/plain', surukle); } catch (x) {} } };
        ic.ondragend = function (e) { var k = e.target.closest('.ia-kart'); if (k) k.classList.remove('suruk'); $$('.ia-kol.uzerinde', ic).forEach(function (x) { x.classList.remove('uzerinde'); }); };
        ic.ondragover = function (e) { var k = e.target.closest('.ia-kol'); if (k && surukle) { e.preventDefault(); $$('.ia-kol.uzerinde', ic).forEach(function (x) { if (x !== k) x.classList.remove('uzerinde'); }); k.classList.add('uzerinde'); } };
        ic.ondrop = function (e) {
            var k = e.target.closest('.ia-kol'); if (!k || !surukle) return; e.preventDefault(); var id = surukle; surukle = null;
            var a = AD.liste.filter(function (x) { return String(x.id) === String(id); })[0]; if (!a || a.asama === k.dataset.as) { iaGovde(); return; }
            a.asama = k.dataset.as; iaGovde();
            gonder('aday_asama', { id: id, asama: k.dataset.as }).then(function () { bildir('Aday “' + (AS.filter(function (x) { return x[0] === k.dataset.as; })[0] || [0, ''])[1] + '” aşamasına taşındı.', 'basari'); }).catch(function (er) { bildir(er.message, 'hata'); iseAlimCiz(); });
        };
        ic.onclick = function (e) { var k = e.target.closest('.ia-kart'); if (k) adayAc(+k.dataset.id); };
    }
    function adayAc(id, on) {
        var a = id ? AD.liste.filter(function (x) { return x.id === id; })[0] : null;
        if (id && !a) { getir({ islem: 'aday_liste' }).then(function (j) { AD.liste = j.liste; adayAc(id); }); return; }
        a = a || Object.assign({ ad: '', telefon: '', email: '', pozisyon: '', kaynak: '', asama: 'basvuru', puan: 0, notlar: '', mulakat_tarih: '', mulakat_saat: '', mulakat_yer: '', mulakatci: '' }, on || {});
        var puan = a.puan;
        var poz = {}; AD.liste.forEach(function (x) { if (x.pozisyon) poz[x.pozisyon] = 1; });
        var m = modal({ baslik: id ? esc(a.ad) : 'Yeni aday', ikon: 'fa-user-tie', gen: 640, govde:
            '<div class="ikm-form"><div class="ikm-iki"><label class="ikm-alan"><span>Ad soyad <b>*</b></span><input type="text" id="a-ad" maxlength="120" value="' + esc(a.ad) + '"></label><label class="ikm-alan"><span>Pozisyon</span><input type="text" id="a-poz" list="a-pl" maxlength="120" value="' + esc(a.pozisyon) + '"><datalist id="a-pl">' + Object.keys(poz).map(function (p) { return '<option value="' + esc(p) + '">'; }).join('') + '</datalist></label></div>' +
            '<div class="ikm-iki"><label class="ikm-alan"><span>Telefon</span><input type="tel" id="a-tel" maxlength="40" value="' + esc(a.telefon) + '"></label><label class="ikm-alan"><span>E-posta</span><input type="email" id="a-mail" maxlength="120" value="' + esc(a.email) + '"></label></div>' +
            '<div class="ikm-iki"><label class="ikm-alan"><span>Aşama</span><select id="a-as">' + AS.map(function (x) { return '<option value="' + x[0] + '"' + (x[0] === a.asama ? ' selected' : '') + '>' + x[1] + '</option>'; }).join('') + '</select></label><label class="ikm-alan"><span>Kaynak</span><input type="text" id="a-kay" maxlength="60" placeholder="Kariyer sitesi, referans…" value="' + esc(a.kaynak) + '"></label></div>' +
            '<div class="ia-mulakat"><div class="ikm-alt-baslik"><i class="fa-regular fa-calendar"></i> Mülakat <small>(takvimde görünür, 1 gün önce ve o gün hatırlatılır)</small></div><div class="ikm-iki"><label class="ikm-alan"><span>Tarih</span><input type="date" id="a-mt" value="' + esc(a.mulakat_tarih || '') + '"></label><label class="ikm-alan"><span>Saat</span><input type="time" id="a-ms" value="' + esc(a.mulakat_saat || '') + '"></label></div>' +
            '<div class="ikm-iki"><label class="ikm-alan"><span>Yer / bağlantı</span><input type="text" id="a-my" maxlength="200" value="' + esc(a.mulakat_yer || '') + '"></label><label class="ikm-alan"><span>Mülakatı yapan</span><input type="text" id="a-mc" maxlength="120" value="' + esc(a.mulakatci || '') + '"></label></div></div>' +
            '<div class="ikm-alan"><span>Değerlendirme puanı</span><div class="ia-yildiz" id="a-p">' + [1, 2, 3, 4, 5].map(function (i) { return '<button type="button" data-p="' + i + '" class="' + (i <= puan ? 'aktif' : '') + '"><i class="fa-solid fa-star"></i></button>'; }).join('') + '</div></div>' +
            '<label class="ikm-alan"><span>Notlar</span><textarea id="a-not" rows="3" maxlength="2000">' + esc(a.notlar) + '</textarea></label></div>',
            alt: (id ? '<button type="button" class="ikm-btn ikm-btn-tehlike" id="a-sil"><i class="fa-solid fa-trash"></i> Sil</button>' : '') + '<span class="ikd-bosluk"></span><button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="a-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        $$('#a-p button', m.el).forEach(function (b) { b.onclick = function () { var v = +b.dataset.p; puan = puan === v ? 0 : v; $$('#a-p button', m.el).forEach(function (x) { x.classList.toggle('aktif', +x.dataset.p <= puan); }); }; });
        if (id) $('#a-sil', m.el).onclick = function () { M.onayla({ baslik: 'Aday silinsin mi?', mesaj: a.ad + ' kalıcı olarak silinir.', evet: 'Sil', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('aday_sil', { id: id }).then(function () { m.kapat(); bildir('Aday silindi.', 'basari'); yenileHepsi(); }).catch(function (e) { bildir(e.message, 'hata'); }); }); };
        $('#a-kaydet', m.el).onclick = function () {
            var btn = this; btn.disabled = true;
            gonder('aday_kaydet', { id: id || 0, ad: $('#a-ad', m.el).value, pozisyon: $('#a-poz', m.el).value, telefon: $('#a-tel', m.el).value, email: $('#a-mail', m.el).value, asama: $('#a-as', m.el).value, kaynak: $('#a-kay', m.el).value,
                mulakat_tarih: $('#a-mt', m.el).value, mulakat_saat: $('#a-ms', m.el).value, mulakat_yer: $('#a-my', m.el).value, mulakatci: $('#a-mc', m.el).value, puan: puan, notlar: $('#a-not', m.el).value }).then(function () {
                m.kapat(); bildir('Aday kaydedildi.', 'basari'); yenileHepsi();
            }).catch(function (e) { btn.disabled = false; bildir(e.message, 'hata'); });
        };
    }
    function yenileHepsi() { if ($('.ia', M.icerik)) iseAlimCiz(); else if ($('.tk', M.icerik)) takvimCiz(); }
    M.kayit('isealim', 'İşe alım', 'Adayları aşamalara sürükleyin; mülakatlar takvimde görünür.', iseAlimCiz);
})();
