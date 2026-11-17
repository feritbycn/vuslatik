/* ==========================================================================
   tb-ik-rapor.js — Raporlar · Mesai · Resmî tatiller · "Bugün kimler?" kartı · Sayfa erişimi (İK sayfası eki)
   Uç noktalar: rapor_islem.php (raporlar, mesai, pano) · tb_yetki_islem.php (sayfa erişimi)
   ========================================================================== */
(function () {
    'use strict';
    function basla() {
        var M = window.IKM; if (!M || !M.kayit) return setTimeout(basla, 30);
        var $ = M.$, $$ = M.$$, esc = M.esc, bildir = M.bildir, onayla = M.onayla, modal = M.modal, icerik = M.icerik, av = M.av, bosKutu = M.bosKutu;
        var API = 'rapor_islem.php', A = M.istek(API), getir = A.getir, gonder = A.gonder;
        function Y() { return M.yetki() || {}; }
        var AYK = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
        var AYU = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        var GUNK = ['Paz', 'Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt'];
        function pad(n) { return ('0' + n).slice(-2); }
        function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
        function dt(s) { var m = String(s || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null; }
        function gunYaz(s) { var d = dt(s); return d ? d.getDate() + ' ' + AYK[d.getMonth()] + ' ' + d.getFullYear() : ''; }
        function gunKisa(s) { var d = dt(s); return d ? d.getDate() + ' ' + AYK[d.getMonth()] : ''; }
        function gunTam(s) { var d = dt(s); return d ? d.getDate() + ' ' + AYU[d.getMonth()] + ' ' + GUNK[d.getDay()] : ''; }
        function nz(s) { return String(s == null ? '' : s).toLocaleLowerCase('tr-TR').replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u'); }
        function sayiYaz(n) { return String(Math.round(n * 100) / 100).replace('.', ','); }

        /* ====================================================================== */
        /*  RAPORLAR                                                              */
        /* ====================================================================== */
        var R = { yuklu: false, tipler: {}, birimler: [], kisiler: [], S: null, son: null, istekNo: 0 };
        function hizliAralik(k) {
            var b = new Date(), y = b.getFullYear(), a = b.getMonth();
            if (k === 'bu_ay') return [ymd(new Date(y, a, 1)), ymd(new Date(y, a + 1, 0))];
            if (k === 'gecen_ay') return [ymd(new Date(y, a - 1, 1)), ymd(new Date(y, a, 0))];
            if (k === 'son30') return [ymd(new Date(y, a, b.getDate() - 29)), ymd(b)];
            if (k === 'bu_yil') return [ymd(new Date(y, 0, 1)), ymd(b)];
            if (k === 'gecen_yil') return [ymd(new Date(y - 1, 0, 1)), ymd(new Date(y - 1, 11, 31))];
            return null;
        }
        var HIZLI = [['bu_ay', 'Bu ay'], ['gecen_ay', 'Geçen ay'], ['son30', 'Son 30 gün'], ['bu_yil', 'Bu yıl'], ['gecen_yil', 'Geçen yıl']];
        function durumKur() {
            var h = hizliAralik('bu_ay'), S = { tip: 'devam', hizli: 'bu_ay', bas: h[0], bit: h[1], secim: 'hepsi', birim: [], kisi: [], op: {}, sutun: {}, sirala: {}, gorunum: 'ikisi' };
            Object.keys(R.tipler).forEach(function (t) {
                var T = R.tipler[t]; S.op[t] = {}; S.sutun[t] = {}; S.sirala[t] = Object.keys(T.siralamalar)[0];
                T.secenekler.forEach(function (o) { S.op[t][o.k] = o.v; });
                T.sutunlar.forEach(function (c) { S.sutun[t][c[0]] = !!c[2]; });
            });
            return S;
        }
        function raporCiz() {
            icerik.innerHTML = M.yukleniyor();
            getir({ islem: 'secenek' }).then(function (j) {
                R.tipler = j.tipler; R.birimler = j.birimler; R.kisiler = j.kisiler;
                if (!R.S) R.S = durumKur();
                iskelet(); onizle(false);
            }).catch(function (e) { icerik.innerHTML = M.hataKutu(e.message); });
        }
        function iskelet() {
            var S = R.S;
            icerik.innerHTML = '<div class="ikr-tipler">' + Object.keys(R.tipler).map(function (t) {
                var T = R.tipler[t]; return '<button type="button" class="ikr-tip' + (S.tip === t ? ' aktif' : '') + '" data-tip="' + t + '" style="--c:' + T.renk + '"><span class="ikr-tip-ikon"><i class="fa-solid ' + T.ikon + '"></i></span><span><b>' + esc(T.ad) + '</b><small>' + esc(T.aciklama) + '</small></span><span class="ikr-tik"><i class="fa-solid fa-check"></i></span></button>';
            }).join('') + '</div><div class="ikr-duzen"><aside class="ikm-kart ikr-panel" id="ikr-panel"></aside><section class="ikr-onizleme" id="ikr-onizleme"></section></div>';
            panelCiz();
            $$('.ikr-tip', icerik).forEach(function (b) { b.onclick = function () { S.tip = b.dataset.tip; $$('.ikr-tip', icerik).forEach(function (x) { x.classList.toggle('aktif', x === b); }); panelCiz(); onizle(true); }; });
        }
        function panelCiz() {
            var S = R.S, T = R.tipler[S.tip], p = $('#ikr-panel');
            var secimSeg = '<div class="ikm-segment" style="width:100%"><button type="button" data-secim="hepsi" class="' + (S.secim === 'hepsi' ? 'aktif' : '') + '" style="flex:1;justify-content:center"><i class="fa-solid fa-users"></i> Tüm personel</button><button type="button" data-secim="sec" class="' + (S.secim === 'sec' ? 'aktif' : '') + '" style="flex:1;justify-content:center"><i class="fa-solid fa-user-check"></i> Seçerek</button></div>';
            var secimIc = '';
            if (S.secim === 'sec') secimIc = '<div class="ikr-birimler">' + R.birimler.map(function (b) { return '<label><input type="checkbox" data-birim value="' + esc(b) + '"' + (S.birim.indexOf(b) >= 0 ? ' checked' : '') + '><span>' + esc(b) + '</span></label>'; }).join('') + '</div>' +
                '<input type="search" id="ikr-kisi-ara" placeholder="Kişi ara…" autocomplete="off" style="margin-top:8px"><div class="ikr-kisiler" id="ikr-kisiler"></div><div class="ikm-soluk" style="font-size:12px;margin-top:6px" id="ikr-sec-say"></div>';
            var h = '<div class="ikr-grup"><h4><i class="fa-regular fa-calendar"></i> Tarih aralığı</h4><div class="ikr-hizli">' + HIZLI.map(function (x) { return '<button type="button" data-hizli="' + x[0] + '" class="' + (S.hizli === x[0] ? 'aktif' : '') + '">' + x[1] + '</button>'; }).join('') + '</div>' +
                '<div class="ikr-tarihler"><label><span>Başlangıç</span><input type="date" id="ikr-bas" value="' + S.bas + '"></label><label><span>Bitiş</span><input type="date" id="ikr-bit" value="' + S.bit + '"></label></div>' +
                '<div class="ikr-tarihler"><label><span>Ay</span><select id="ikr-ay"><option value="">Ay seçiniz</option>' + M.aylar.map(function (a, i) { return '<option value="' + (i + 1) + '"' + (S.ayS === i + 1 ? ' selected' : '') + '>' + a + '</option>'; }).join('') + '</select></label>' +
                '<label><span>Yıl</span><select id="ikr-yil"><option value="">Yıl seçiniz</option>' + (function () { var y = new Date().getFullYear(), o = ''; for (var i = y + 1; i >= y - 6; i--) o += '<option value="' + i + '"' + (S.yilS === i ? ' selected' : '') + '>' + i + '</option>'; return o; })() + '</select></label></div></div>' +
                '<div class="ikr-grup"><h4><i class="fa-solid fa-users"></i> Kimler</h4>' + secimSeg + secimIc + '</div>' +
                '<div class="ikr-grup"><h4><i class="fa-solid fa-arrow-down-wide-short"></i> Sıralama</h4><select id="ikr-sirala">' + Object.keys(T.siralamalar).map(function (k) { return '<option value="' + k + '"' + (S.sirala[S.tip] === k ? ' selected' : '') + '>' + esc(T.siralamalar[k]) + '</option>'; }).join('') + '</select></div>' +
                '<div class="ikr-grup"><h4><i class="fa-solid fa-sliders"></i> Rapor seçenekleri</h4>' + T.secenekler.map(function (o) {
                    var v = S.op[S.tip][o.k];
                    if (o.tip === 'bool') return '<label class="ikm-ozel-satir"><input type="checkbox" data-op="' + o.k + '"' + (v ? ' checked' : '') + '><i></i><span>' + esc(o.ad) + '</span></label>';
                    return '<label class="ikr-secenek"><span>' + esc(o.ad) + '</span><select data-op="' + o.k + '">' + Object.keys(o.sec).map(function (k) { return '<option value="' + esc(k) + '"' + (String(v) === k ? ' selected' : '') + '>' + esc(o.sec[k]) + '</option>'; }).join('') + '</select></label>';
                }).join('') + '</div>' +
                '<div class="ikr-grup"><h4><i class="fa-solid fa-table-columns"></i> Raporda görünecek sütunlar</h4><div class="ikr-sutunlar">' + T.sutunlar.map(function (c) {
                    return '<label class="ikm-ozel-satir"><input type="checkbox" data-sutun="' + c[0] + '"' + (S.sutun[S.tip][c[0]] ? ' checked' : '') + '><i></i><span>' + esc(c[1]) + '</span></label>';
                }).join('') + '</div></div>' +
                '<div class="ikr-eylem"><button type="button" class="ikm-btn ikm-btn-ana" id="ikr-olustur"><i class="fa-solid fa-wand-magic-sparkles"></i> Raporu oluştur</button></div>';
            p.innerHTML = h;
            if (S.secim === 'sec') { kisiListeCiz(); }
            var tarihDegisti = function () { S.bas = $('#ikr-bas').value; S.bit = $('#ikr-bit').value; S.hizli = ''; $$('[data-hizli]', p).forEach(function (b) { b.classList.remove('aktif'); }); };
            $$('[data-hizli]', p).forEach(function (b) { b.onclick = function () { var a = hizliAralik(b.dataset.hizli); S.hizli = b.dataset.hizli; S.ayS = S.yilS = null; $('#ikr-ay').value = ''; $('#ikr-yil').value = ''; S.bas = a[0]; S.bit = a[1]; $('#ikr-bas').value = a[0]; $('#ikr-bit').value = a[1]; $$('[data-hizli]', p).forEach(function (x) { x.classList.toggle('aktif', x === b); }); }; });
            $('#ikr-bas').onchange = function () { S.ayS = S.yilS = null; $('#ikr-ay').value = ''; $('#ikr-yil').value = ''; tarihDegisti(); }; $('#ikr-bit').onchange = $('#ikr-bas').onchange;
            function ayYil() {
                var a = +$('#ikr-ay').value, y = +$('#ikr-yil').value; if (!a && !y) return; if (!y) y = new Date().getFullYear();
                S.ayS = a || null; S.yilS = y; S.bas = a ? ymd(new Date(y, a - 1, 1)) : y + '-01-01'; S.bit = a ? ymd(new Date(y, a, 0)) : y + '-12-31';
                $('#ikr-bas').value = S.bas; $('#ikr-bit').value = S.bit; S.hizli = ''; $$('[data-hizli]', p).forEach(function (b) { b.classList.remove('aktif'); });
            }
            $('#ikr-ay').onchange = ayYil; $('#ikr-yil').onchange = ayYil;
            $$('[data-secim]', p).forEach(function (b) { b.onclick = function () { S.secim = b.dataset.secim; panelCiz(); }; });
            $$('[data-birim]', p).forEach(function (c) { c.onchange = function () { var i = S.birim.indexOf(c.value); if (c.checked && i < 0) S.birim.push(c.value); if (!c.checked && i >= 0) S.birim.splice(i, 1); secSay(); }; });
            $('#ikr-sirala').onchange = function () { S.sirala[S.tip] = this.value; };
            $$('[data-op]', p).forEach(function (c) { c.onchange = function () { S.op[S.tip][c.dataset.op] = c.type === 'checkbox' ? c.checked : c.value; }; });
            $$('[data-sutun]', p).forEach(function (c) { c.onchange = function () { S.sutun[S.tip][c.dataset.sutun] = c.checked; }; });
            $('#ikr-olustur').onclick = function () { onizle(true); if (window.matchMedia('(max-width: 980px)').matches) { var o = $('#ikr-onizleme'); if (o) o.scrollIntoView({ behavior: 'smooth', block: 'start' }); } };
        }
        function secSay() { var e = $('#ikr-sec-say'); if (!e) return; var S = R.S; e.textContent = (!S.birim.length && !S.kisi.length) ? 'Hiçbiri seçilmedi: tüm personel rapora girer.' : (S.birim.length ? S.birim.length + ' birim' : '') + (S.birim.length && S.kisi.length ? ' + ' : '') + (S.kisi.length ? S.kisi.length + ' kişi' : '') + ' seçili.'; }
        function kisiListeCiz() {
            var S = R.S, kap = $('#ikr-kisiler'), ara = $('#ikr-kisi-ara');
            function ciz() {
                var a = nz(ara.value), l = R.kisiler.filter(function (k) { return !a || nz(k.ad + ' ' + k.birim).indexOf(a) >= 0; });
                kap.innerHTML = l.map(function (k) { return '<label><input type="checkbox" value="' + k.id + '"' + (S.kisi.indexOf(k.id) >= 0 ? ' checked' : '') + '><span>' + esc(k.ad) + '</span><small>' + esc(k.birim) + '</small></label>'; }).join('') || '<div class="ikm-soluk" style="padding:12px;font-size:13px">Sonuç yok.</div>';
            }
            ara.oninput = ciz; kap.onchange = function (e) { var i = +e.target.value, x = S.kisi.indexOf(i); if (e.target.checked) { if (x < 0) S.kisi.push(i); } else if (x >= 0) S.kisi.splice(x, 1); secSay(); };
            ciz(); secSay();
        }
        function parametreler() {
            var S = R.S, T = R.tipler[S.tip], p = { tip: S.tip, bas: S.bas, bit: S.bit, sirala: S.sirala[S.tip], op: JSON.stringify(S.op[S.tip]), sutun: JSON.stringify(T.sutunlar.map(function (c) { return c[0]; }).filter(function (k) { return S.sutun[S.tip][k]; })) };
            if (S.secim === 'sec') { p.birim = JSON.stringify(S.birim); p.kisi = JSON.stringify(S.kisi); }
            return p;
        }
        function onizle(bildirimli) {
            var kap = $('#ikr-onizleme'); if (!kap) return;
            var no = ++R.istekNo; kap.innerHTML = '<div class="ikr-iskelet"></div><div class="ikr-iskelet" style="height:260px"></div>';
            var p = parametreler(); p.islem = 'onizle';
            fetch(API + '?' + new URLSearchParams(p), { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
                if (no !== R.istekNo) return;
                if (!j.ok) { R.son = null; kap.innerHTML = '<div class="ikm-kart ikr-bos"><i class="fa-solid fa-circle-exclamation"></i><b>Rapor oluşturulamadı</b>' + esc(j.hata || '') + '</div>'; return; }
                R.son = j; sonucCiz();
            }).catch(function (e) { if (no === R.istekNo) kap.innerHTML = M.hataKutu(e.message); });
        }
        function hucre(c, satir) {
            var v = satir[c.k], vur = satir._h && satir._h[c.k];
            return '<td class="' + (c.h === 'r' ? 'r ' : '') + (c.k === 'ad' ? 'ad ' : '') + (vur ? 'v-' + vur : '') + '">' + esc(v == null ? '' : v) + '</td>';
        }
        function tabloHtml(sut, sat, sinir) {
            var gor = sat.slice(0, sinir || 500);
            return '<div class="ikr-tablo-kap"><table class="ikr-tablo"><thead><tr>' + sut.map(function (c) { return '<th class="' + (c.h === 'r' ? 'r' : '') + '">' + esc(c.ad) + '</th>'; }).join('') + '</tr></thead><tbody>' +
                gor.map(function (s) { return '<tr class="' + (s._c ? 's-' + s._c : '') + '">' + sut.map(function (c) { return hucre(c, s); }).join('') + '</tr>'; }).join('') + '</tbody></table></div>' +
                (sat.length > gor.length ? '<div class="ikr-not">Önizlemede ilk ' + gor.length + ' satır gösterilir (toplam ' + sat.length + '). PDF ve CSV çıktısı tüm satırları içerir.</div>' : '');
        }
        function grafikHtml(g) {
            if (g.tip === 'halka') {
                var top = g.dilimler.reduce(function (a, d) { return a + d.deger; }, 0) || 1, acc = 0;
                var kon = g.dilimler.map(function (d) { var b = acc / top * 100; acc += d.deger; return d.renk + ' ' + b + '% ' + (acc / top * 100) + '%'; }).join(', ');
                return '<div class="ikm-kart ikr-grafik"><h4>' + esc(g.baslik) + '</h4><div class="ikr-halka"><div class="ikr-halka-grafik" style="background:conic-gradient(' + kon + ')" data-orta="' + sayiYaz(top) + '\n' + esc(g.birim || '') + '"></div><ul>' +
                    g.dilimler.map(function (d) { return '<li style="--c:' + d.renk + '">' + esc(d.ad) + '<b>' + sayiYaz(d.deger) + ' ' + esc(g.birim || '') + '</b> <span class="ikm-soluk">(%' + Math.round(d.deger * 100 / top) + ')</span></li>'; }).join('') + '</ul></div></div>';
            }
            var mak = 0; g.etiketler.forEach(function (_, i) { var t = 0; g.seriler.forEach(function (s) { t = g.yigin ? t + s.veri[i] : Math.max(t, s.veri[i]); }); mak = Math.max(mak, t); }); mak = mak || 1;
            return '<div class="ikm-kart ikr-grafik"><h4>' + esc(g.baslik) + '</h4>' + g.etiketler.map(function (ad, i) {
                var top = 0, seg = g.seriler.map(function (s) { var v = s.veri[i]; top = g.yigin ? top + v : Math.max(top, v); return v > 0 ? '<i style="width:' + (v / mak * 100) + '%;background:' + s.renk + '" title="' + esc(s.ad) + ': ' + sayiYaz(v) + '"></i>' : ''; }).join('');
                return '<div class="ikr-bar"><span title="' + esc(ad) + '">' + esc(ad) + '</span><div class="ikr-bar-iz">' + seg + '<b>' + sayiYaz(top) + ' ' + esc(g.birim || '') + '</b></div></div>';
            }).join('') + (g.seriler.length > 1 ? '<div class="ikr-lejant">' + g.seriler.map(function (s) { return '<span style="--c:' + s.renk + '">' + esc(s.ad) + '</span>'; }).join('') + '</div>' : '') + '</div>';
        }
        function sonucCiz() {
            var r = R.son, S = R.S, kap = $('#ikr-onizleme'); if (!r || !kap) return;
            var g = S.gorunum, tablo = g !== 'grafik', grafik = g !== 'tablo';
            var arac = '<div class="ikr-arac"><button type="button" class="ikm-btn ikr-panel-ac" id="ikr-panel-ac"><i class="fa-solid fa-filter"></i> Filtreler</button>' +
                '<div class="ikm-segment" id="ikr-gorunum"><button type="button" data-g="ikisi" class="' + (g === 'ikisi' ? 'aktif' : '') + '"><i class="fa-solid fa-table-cells-large"></i> Tablo + grafik</button><button type="button" data-g="tablo" class="' + (g === 'tablo' ? 'aktif' : '') + '"><i class="fa-solid fa-table"></i> Tablo</button><button type="button" data-g="grafik" class="' + (g === 'grafik' ? 'aktif' : '') + '"><i class="fa-solid fa-chart-column"></i> Grafik</button></div>' +
                '<span class="ikd-bosluk"></span><button type="button" class="ikm-btn" id="ikr-csv"><i class="fa-solid fa-file-csv"></i> CSV</button><button type="button" class="ikm-btn ikm-btn-ana" id="ikr-pdf"><i class="fa-solid fa-file-pdf"></i> PDF indir</button></div>';
            var h = arac + '<h2 class="ikr-baslik">' + esc(r.baslik) + '</h2><p class="ikr-alt">' + esc(r.alt) + '</p>';
            h += '<div class="ikr-ozet">' + r.ozet.map(function (o) { return '<div class="ikm-kpi ' + (o[2] || 'mavi') + '"><div><span>' + esc(o[0]) + '</span><b>' + esc(o[1]) + '</b></div></div>'; }).join('') + '</div>';
            if (!r.satirlar.length) h += '<div class="ikm-kart ikr-bos"><i class="fa-regular fa-folder-open"></i><b>Bu kriterlere uyan kayıt yok</b>Tarih aralığını genişletmeyi veya seçenekleri gevşetmeyi deneyin.</div>';
            else {
                if (grafik && r.grafikler.length) h += '<div class="ikr-bolum" style="margin-top:0">' + r.grafikler.map(grafikHtml).join('<div style="height:12px"></div>') + '</div>';
                if (grafik && !r.grafikler.length && g === 'grafik') h += '<div class="ikm-kart ikr-bos"><i class="fa-solid fa-chart-simple"></i><b>Grafik için yeterli veri yok</b>Tablo görünümüne geçin.</div>';
                if (tablo) {
                    h += '<div class="ikr-bolum"><h3><i class="fa-solid fa-table"></i> Özet tablo <em>' + r.satirlar.length + ' satır</em></h3>' + tabloHtml(r.sutunlar, r.satirlar, 500) + '</div>';
                    r.detaylar.forEach(function (d) { if (d.satirlar.length) h += '<div class="ikr-bolum"><h3><i class="fa-solid fa-list-ul"></i> ' + esc(d.baslik) + ' <em>' + d.satirlar.length + ' kayıt</em></h3>' + tabloHtml(d.sutunlar, d.satirlar, 500) + '</div>'; });
                }
            }
            if (r.notlar.length) h += r.notlar.map(function (n) { return '<div class="ikr-not"><i class="fa-solid fa-circle-info"></i> ' + esc(n) + '</div>'; }).join('');
            kap.innerHTML = h;
            $$('#ikr-gorunum [data-g]', kap).forEach(function (b) { b.onclick = function () { S.gorunum = b.dataset.g; sonucCiz(); }; });
            $('#ikr-pdf', kap).onclick = pdfIndir; $('#ikr-csv', kap).onclick = csvIndir;
            $('#ikr-panel-ac', kap).onclick = function () { $('#ikr-panel').classList.toggle('kapali'); };
            if (window.matchMedia('(max-width: 980px)').matches) $('#ikr-panel').classList.add('kapali');
        }
        function pdfIndir() {
            var p = parametreler(); p.islem = 'pdf'; p.gorunum = R.S.gorunum;
            var a = document.createElement('a'); a.href = API + '?' + new URLSearchParams(p); a.download = ''; document.body.appendChild(a); a.click(); a.remove();
            bildir('PDF hazırlanıyor, indirme birazdan başlar.', 'bilgi');
        }
        function csvIndir() {
            var r = R.son; if (!r) return;
            function tablo(sut, sat) { return [sut.map(function (c) { return c.ad; })].concat(sat.map(function (s) { return sut.map(function (c) { return s[c.k] == null ? '' : s[c.k]; }); })); }
            var rows = [[r.baslik], [r.alt], []].concat(r.ozet.map(function (o) { return [o[0], o[1]]; }), [[]], tablo(r.sutunlar, r.satirlar));
            r.detaylar.forEach(function (d) { rows.push([]); rows.push([d.baslik]); tablo(d.sutunlar, d.satirlar).forEach(function (x) { rows.push(x); }); });
            var csv = '﻿' + rows.map(function (x) { return x.map(function (v) { v = String(v == null ? '' : v); return /[;"\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }).join(';'); }).join('\r\n');
            var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = r.dosya + '.csv'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
        }
        M.kayit('raporlar', 'Raporlar', 'Geç kalma, izin, avans ve mesai raporlarını önizleyin; tablo ya da grafik olarak PDF indirin.', function () { raporCiz(); });

        /* ====================================================================== */
        /*  MESAİ                                                                 */
        /* ====================================================================== */
        var MX = { ay: null, kisi: '', durum: '', cg: [1, 2, 3, 4, 5], yonet: false, kisiler: [], tat: {} };
        function tatilYukle(yil) { if (MX.tat[yil]) return Promise.resolve(MX.tat[yil]); return getir({ islem: 'tatiller', yil: yil }).then(function (j) { var h = {}; j.liste.forEach(function (t) { h[t.tarih] = t; }); MX.tat[yil] = h; return h; }); }
        function gunTuru(tarih) {
            var d = dt(tarih); if (!d) return null; var t = (MX.tat[d.getFullYear()] || {})[tarih], n = d.getDay() === 0 ? 7 : d.getDay();
            if (t && !t.yarim) return { tur: 'tatil', ad: t.ad, n: n };
            if (MX.cg.indexOf(n) < 0) return { tur: 'haftasonu', ad: GUNK[d.getDay()], n: n };
            return { tur: 'normal', ad: t ? t.ad : '', n: n };
        }
        function mesaiCiz() {
            if (!MX.ay) { var b = new Date(); MX.ay = new Date(b.getFullYear(), b.getMonth(), 1); }
            icerik.innerHTML = M.yukleniyor();
            var bas = ymd(MX.ay), bit = ymd(new Date(MX.ay.getFullYear(), MX.ay.getMonth() + 1, 0)), p = { islem: 'mesailer', bas: bas, bit: bit };
            if (MX.kisi) p.kisi = MX.kisi; if (MX.durum) p.durum = MX.durum;
            Promise.all([getir(p), tatilYukle(MX.ay.getFullYear())]).then(function (r) {
                var j = r[0]; MX.cg = j.calisma_gunleri; MX.yonet = j.yonet; MX.kisiler = j.kisiler;
                var T = j.toplam, benId = (window.IK_BEN || {}).id;
                var arac = '<div class="ikm-arac"><div class="ikr-ay"><button type="button" id="mx-onceki" aria-label="Önceki ay"><i class="fa-solid fa-chevron-left"></i></button><b>' + AYU[MX.ay.getMonth()] + ' ' + MX.ay.getFullYear() + '</b><button type="button" id="mx-sonraki" aria-label="Sonraki ay"><i class="fa-solid fa-chevron-right"></i></button></div>' +
                    (j.yonet ? '<select id="mx-kisi"><option value="">Tüm personel</option>' + j.kisiler.map(function (k) { return '<option value="' + k.id + '"' + (String(MX.kisi) === String(k.id) ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' : '') +
                    '<select id="mx-durum"><option value="">Tüm durumlar</option><option value="onaylandi"' + (MX.durum === 'onaylandi' ? ' selected' : '') + '>Onaylı</option><option value="beklemede"' + (MX.durum === 'beklemede' ? ' selected' : '') + '>Onay bekleyen</option><option value="reddedildi"' + (MX.durum === 'reddedildi' ? ' selected' : '') + '>Reddedilen</option></select>' +
                    '<span class="ikd-bosluk" style="flex:1"></span><button type="button" class="ikm-btn" id="mx-tatil"><i class="fa-solid fa-flag"></i> Tatil günleri</button>' + (j.yonet ? '<button type="button" class="ikm-btn" id="mx-toplu"><i class="fa-solid fa-users"></i> Toplu ekle</button>' : '') + '<button type="button" class="ikm-btn ikm-btn-ana" id="mx-yeni"><i class="fa-solid fa-plus"></i> ' + (j.yonet ? 'Mesai ekle' : 'Mesai bildir') + '</button></div>';
                var kpi = '<section class="ikm-kpiler" style="margin-bottom:16px">' +
                    '<div class="ikm-kpi mavi"><span class="ikm-kpi-ikon"><i class="fa-solid fa-business-time"></i></span><div><b>' + esc(T.onayli_yaz === '—' ? '0' : T.onayli_yaz) + '</b><span>Onaylı toplam mesai</span></div></div>' +
                    '<div class="ikm-kpi mor"><span class="ikm-kpi-ikon"><i class="fa-solid fa-couch"></i></span><div><b>' + esc(T.hs === '—' ? '0' : T.hs) + '</b><span>Hafta sonu mesaisi</span></div></div>' +
                    '<div class="ikm-kpi kirmizi"><span class="ikm-kpi-ikon"><i class="fa-solid fa-flag"></i></span><div><b>' + esc(T.tatil === '—' ? '0' : T.tatil) + '</b><span>Resmî tatil / bayram mesaisi</span></div></div>' +
                    '<div class="ikm-kpi turuncu"><span class="ikm-kpi-ikon"><i class="fa-solid fa-hourglass-half"></i></span><div><b>' + T.bek_say + '</b><span>Onay bekleyen' + (T.bek_say ? ' (' + esc(T.bekleyen) + ')' : '') + '</span></div></div></section>';
                var liste = j.liste.length ? '<div class="ikr-mesai-liste">' + j.liste.map(function (m) {
                    var d = dt(m.tarih), sahip = m.kid === benId;
                    var cip = m.tur === 'tatil' ? '<span class="ikm-cip kirmizi"><i class="fa-solid fa-flag"></i> ' + esc(m.tatil_adi) + '</span>' : m.tur === 'haftasonu' ? '<span class="ikm-cip mor"><i class="fa-solid fa-couch"></i> Hafta sonu</span>' : '<span class="ikm-cip gri">Hafta içi</span>';
                    var dur = m.durum === 'beklemede' ? '<span class="ikm-cip turuncu"><i class="fa-solid fa-hourglass-half"></i> Onay bekliyor</span>' : m.durum === 'reddedildi' ? '<span class="ikm-cip gri">Reddedildi</span>' : '';
                    var isl = '';
                    if (j.yonet && m.durum === 'beklemede') isl += '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-yesil" data-karar="onay" data-id="' + m.id + '"><i class="fa-solid fa-check"></i> Onayla</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-karar="red" data-id="' + m.id + '"><i class="fa-solid fa-xmark"></i> Reddet</button>';
                    if (m.silebilir) isl += '<button type="button" class="ikm-btn ikm-btn-kucuk" data-duz="' + m.id + '"><i class="fa-solid fa-pen"></i> Düzenle</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-sil="' + m.id + '"><i class="fa-solid fa-trash"></i></button>';
                    return '<article class="ikr-mesai ' + m.tur + ' ' + m.durum + '"><div class="ikr-mesai-tarih"><b>' + d.getDate() + '</b><small>' + AYK[d.getMonth()] + ' · ' + GUNK[d.getDay()] + '</small></div>' +
                        '<div class="ikr-mesai-ic"><b>' + (j.yonet || !sahip ? esc(m.ad) : 'Mesai') + ' ' + cip + ' ' + dur + '</b><small>' + esc(m.baslangic + '–' + m.bitis) + (m.aciklama ? ' · ' + esc(m.aciklama) : '') + '</small></div>' +
                        '<div class="ikr-mesai-sure">' + esc(m.sure) + '<small>' + sayiYaz(m.dakika / 60) + ' saat</small></div>' + (isl ? '<div class="ikr-mesai-islem">' + isl + '</div>' : '') + '</article>';
                }).join('') + '</div>' : '<div class="ikm-kart">' + bosKutu('fa-moon', 'Bu ay için mesai kaydı yok', j.yonet ? 'Personel adına “Mesai ekle” ile kayıt girebilirsiniz.' : 'Çalıştığınız fazla mesaiyi “Mesai bildir” ile iletin; yöneticiniz onaylar.') + '</div>';
                icerik.innerHTML = arac + kpi + liste;
                $('#mx-onceki').onclick = function () { MX.ay = new Date(MX.ay.getFullYear(), MX.ay.getMonth() - 1, 1); mesaiCiz(); };
                $('#mx-sonraki').onclick = function () { MX.ay = new Date(MX.ay.getFullYear(), MX.ay.getMonth() + 1, 1); mesaiCiz(); };
                var ks = $('#mx-kisi'); if (ks) ks.onchange = function () { MX.kisi = ks.value; mesaiCiz(); };
                $('#mx-durum').onchange = function () { MX.durum = this.value; mesaiCiz(); };
                $('#mx-yeni').onclick = function () { mesaiForm(null); }; var tb = $('#mx-toplu'); if (tb) tb.onclick = topluForm; $('#mx-tatil').onclick = tatilModal;
                $$('[data-karar]', icerik).forEach(function (b) { b.onclick = function () { gonder('mesai_karar', { id: b.dataset.id, karar: b.dataset.karar }).then(function () { bildir(b.dataset.karar === 'onay' ? 'Mesai onaylandı.' : 'Mesai reddedildi.', 'basari'); mesaiCiz(); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
                $$('[data-sil]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Mesai kaydı silinsin mi?', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('mesai_sil', { id: b.dataset.sil }).then(function () { bildir('Kayıt silindi.', 'basari'); mesaiCiz(); }).catch(function (e) { bildir(e.message, 'hata'); }); }); }; });
                $$('[data-duz]', icerik).forEach(function (b) { b.onclick = function () { var m = j.liste.filter(function (x) { return x.id === +b.dataset.duz; })[0]; mesaiForm(m); }; });
            }).catch(function (e) { icerik.innerHTML = M.hataKutu(e.message); });
        }
        function topluForm() {
            var bugun = ymd(new Date()), yarin = ymd(new Date(Date.now() + 864e5));
            var liste = MX.kisiler.map(function (k) { return '<label class="mx-tk"><input type="checkbox" value="' + k.id + '"><span><b>' + esc(k.ad) + '</b><small>' + esc(k.birim || '') + '</small></span></label>'; }).join('');
            var mm = modal({ baslik: 'Toplu mesai ekle', ikon: 'fa-users', gen: 600, sabit: true, alt: '<span></span>', govde: '<form id="mt-form" class="ikm-form" autocomplete="off">' +
                '<div class="mx-tk-ust"><b>Personel <span id="mt-say">0</span> seçili</b><span><button type="button" class="ikm-btn" id="mt-hepsi">Tümünü seç</button> <button type="button" class="ikm-btn" id="mt-temiz">Temizle</button></span></div>' +
                '<input type="search" id="mt-ara" placeholder="Personel ara…" style="width:100%;margin:6px 0;padding:9px 12px;border:1px solid var(--ik-cizgi,#e2e8f0);border-radius:10px;background:transparent;color:inherit;font:inherit"><div class="mx-tk-liste" id="mt-liste">' + liste + '</div>' +
                '<div class="ikm-iki"><label class="ikm-alan"><span>Tarih <b>*</b></span><input type="date" name="tarih" required max="' + yarin + '" value="' + bugun + '"></label><label class="ikm-alan"><span>Bitiş tarihi <small>(aralık için)</small></span><input type="date" name="tarih_bit" max="' + yarin + '"></label></div>' +
                '<div class="mx-mod"><label><input type="radio" name="mod" value="saat" checked> <span>Süre ekle (örn. 5 saat)</span></label><label><input type="radio" name="mod" value="aralik"> <span>Saat aralığı (kaçtan kaça)</span></label></div>' +
                '<div class="ikm-iki" id="mt-saat"><label class="ikm-alan"><span>Süre (saat) <b>*</b></span><input type="number" name="saat" min="0.25" max="16" step="0.25" value="5"></label><label class="ikm-alan"><span>Başlangıç saati</span><input type="time" name="baslangic" value="18:00"></label></div>' +
                '<div class="ikm-iki" id="mt-aralik" hidden><label class="ikm-alan"><span>Başlangıç <b>*</b></span><input type="time" name="baslangic2" value="18:00"></label><label class="ikm-alan"><span>Bitiş <b>*</b></span><input type="time" name="bitis" value="22:00"></label></div>' +
                '<label class="mx-hs"><input type="checkbox" name="hafta_sonu" checked> Hafta sonu ve resmî tatil günlerini de dahil et</label>' +
                '<label class="ikm-alan" style="margin-top:10px"><span>Açıklama</span><textarea name="aciklama" rows="2" maxlength="300" placeholder="Hangi iş için mesai yapıldı?"></textarea></label>' +
                '<p class="ikm-soluk" style="font-size:12.5px;margin:8px 0 0">Kayıtlar doğrudan onaylı girilir; aynı gün ve saatte kaydı olanlar atlanır. Her personele bildirim gider.</p></form>' });
            var f = $('#mt-form', mm.el), alt = $('.ikm-modal-alt', mm.el);
            alt.innerHTML = '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="mt-kaydet"><i class="fa-solid fa-check"></i> Ekle</button>';
            $('[data-kapat]', alt).onclick = mm.kapat;
            function say() { $('#mt-say', mm.el).textContent = $$('#mt-liste input:checked', mm.el).length; }
            $('#mt-liste', mm.el).addEventListener('change', say);
            $('#mt-hepsi', mm.el).onclick = function () { $$('#mt-liste label', mm.el).forEach(function (l) { if (l.style.display !== 'none') $('input', l).checked = true; }); say(); };
            $('#mt-temiz', mm.el).onclick = function () { $$('#mt-liste input', mm.el).forEach(function (c) { c.checked = false; }); say(); };
            $('#mt-ara', mm.el).oninput = function () { var q = this.value.toLocaleLowerCase('tr'); $$('#mt-liste label', mm.el).forEach(function (l) { l.style.display = l.textContent.toLocaleLowerCase('tr').indexOf(q) < 0 ? 'none' : ''; }); };
            $$('[name=mod]', f).forEach(function (r) { r.onchange = function () { var a = f.mod.value === 'aralik'; $('#mt-saat', mm.el).hidden = a; $('#mt-aralik', mm.el).hidden = !a; }; });
            $('#mt-kaydet', alt).onclick = function () {
                var ids = $$('#mt-liste input:checked', mm.el).map(function (c) { return c.value; });
                if (!ids.length) return bildir('En az bir personel seçin.', 'hata');
                var ar = f.mod.value === 'aralik';
                var v = { kisiler: ids, tarih: f.tarih.value, tarih_bit: f.tarih_bit.value, mod: f.mod.value, saat: f.saat.value, baslangic: ar ? f.baslangic2.value : f.baslangic.value, bitis: f.bitis.value, hafta_sonu: f.hafta_sonu.checked ? 1 : 0, aciklama: f.aciklama.value };
                if (!v.tarih) return bildir('Tarih seçin.', 'hata');
                var bt = this; bt.disabled = true;
                gonder('mesai_toplu', v).then(function (r) { mm.kapat(); bildir(r.kisi + ' personele ' + r.eklenen + ' kayıt eklendi (' + r.toplam + ')' + (r.atlanan ? ', ' + r.atlanan + ' atlandı' : '') + '.', 'basari'); mesaiCiz(); }).catch(function (e) { bt.disabled = false; bildir(e.message, 'hata'); });
            };
        }
        function mesaiForm(m) {
            var bugun = ymd(new Date()), yarin = ymd(new Date(Date.now() + 864e5)), yonet = MX.yonet;
            var kisiSec = yonet ? '<label class="ikm-alan"><span>Personel <b>*</b></span><select name="kullanici_id">' + MX.kisiler.map(function (k) { return '<option value="' + k.id + '"' + (m && m.kid === k.id ? ' selected' : (MX.kisi && !m && String(MX.kisi) === String(k.id) ? ' selected' : '')) + '>' + esc(k.ad) + ' — ' + esc(k.birim) + '</option>'; }).join('') + '</select></label>' : '';
            var mm = modal({ baslik: m ? 'Mesaiyi düzenle' : (yonet ? 'Mesai ekle' : 'Mesai bildir'), ikon: 'fa-business-time', gen: 520, sabit: true, alt: '<span></span>', govde: '<form id="mx-form" class="ikm-form" autocomplete="off">' + kisiSec +
                '<label class="ikm-alan"><span>Tarih <b>*</b></span><input type="date" name="tarih" required max="' + yarin + '" value="' + (m ? m.tarih : bugun) + '"></label>' +
                '<div class="ikm-iki"><label class="ikm-alan"><span>Başlangıç <b>*</b></span><input type="time" name="baslangic" required value="' + (m ? m.baslangic : '18:00') + '"></label><label class="ikm-alan"><span>Bitiş <b>*</b></span><input type="time" name="bitis" required value="' + (m ? m.bitis : '20:00') + '"></label></div>' +
                '<div class="ikr-ipucu" id="mx-ipucu"></div>' +
                '<label class="ikm-alan" style="margin-top:12px"><span>Açıklama</span><textarea name="aciklama" rows="2" maxlength="300" placeholder="Hangi iş için mesai yapıldı?">' + esc(m ? m.aciklama : '') + '</textarea></label>' +
                (yonet ? '' : '<p class="ikm-soluk" style="font-size:12.5px;margin:10px 0 0">Bildiriminiz yöneticinin onayına gider; onaylanınca mesai raporlarına işlenir.</p>') + '</form>' });
            var f = $('#mx-form', mm.el), alt = $('.ikm-modal-alt', mm.el);
            alt.innerHTML = '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="mx-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>';
            $('[data-kapat]', alt).onclick = mm.kapat;
            function ipucu() {
                var t = f.tarih.value, e = $('#mx-ipucu', mm.el), b = f.baslangic.value, s = f.bitis.value; if (!t) { e.style.display = 'none'; return; } e.style.display = '';
                var yil = (dt(t) || new Date()).getFullYear();
                tatilYukle(yil).then(function () {
                    var g = gunTuru(t); if (!g) return; var dk = '';
                    if (b && s) { var a = +b.slice(0, 2) * 60 + +b.slice(3), z = +s.slice(0, 2) * 60 + +s.slice(3); if (z <= a) z += 1440; dk = ' Süre: <b>' + (Math.floor((z - a) / 60) ? Math.floor((z - a) / 60) + ' sa' : '') + ((z - a) % 60 ? ' ' + ((z - a) % 60) + ' dk' : '') + '</b>.'; }
                    e.className = 'ikr-ipucu ' + g.tur;
                    e.innerHTML = g.tur === 'tatil' ? '<i class="fa-solid fa-flag"></i><span><b>' + esc(g.ad) + '</b> — resmî tatil günü. Bu mesai <b>resmî tatil mesaisi</b> olarak işlenir.' + dk + '</span>' :
                        g.tur === 'haftasonu' ? '<i class="fa-solid fa-couch"></i><span><b>' + esc(GUNK[dt(t).getDay()]) + '</b> — hafta sonu. Bu mesai <b>hafta sonu mesaisi</b> olarak işlenir.' + dk + '</span>' :
                        '<i class="fa-regular fa-clock"></i><span>Hafta içi mesaisi olarak işlenir.' + (g.ad ? ' (' + esc(g.ad) + ' — yarım gün tatil)' : '') + dk + '</span>';
                });
            }
            ['tarih', 'baslangic', 'bitis'].forEach(function (k) { f[k].addEventListener('input', ipucu); }); ipucu();
            $('#mx-kaydet', alt).onclick = function () {
                if (!f.reportValidity()) return; var v = {}; Array.prototype.forEach.call(f.elements, function (el) { if (el.name) v[el.name] = el.value; }); if (m) v.id = m.id;
                var bt = this; bt.disabled = true;
                gonder('mesai_kaydet', v).then(function (r) { mm.kapat(); bildir(r.durum === 'beklemede' ? 'Mesai bildiriminiz yöneticiye iletildi.' : 'Mesai kaydedildi.', 'basari'); mesaiCiz(); }).catch(function (e) { bt.disabled = false; bildir(e.message, 'hata'); });
            };
        }
        function tatilModal() {
            var yil = MX.ay ? MX.ay.getFullYear() : new Date().getFullYear();
            var mm = modal({ baslik: 'Resmî tatil günleri', ikon: 'fa-flag', gen: 620, govde: M.yukleniyor(), alt: '<button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Kapat</button>' });
            $('[data-kapat]', mm.el).onclick = mm.kapat;
            function yukle(y) {
                yil = y; $('.ikm-modal-govde', mm.el).innerHTML = M.yukleniyor();
                getir({ islem: 'tatiller', yil: y }).then(function (j) {
                    var h = {}; j.liste.forEach(function (t) { h[t.tarih] = t; }); MX.tat[y] = h;
                    var yonet = j.yonet, g = $('.ikm-modal-govde', mm.el);
                    g.innerHTML = '<div class="ikr-ay" style="margin-bottom:12px"><button type="button" id="tt-o"><i class="fa-solid fa-chevron-left"></i></button><b>' + y + '</b><button type="button" id="tt-s"><i class="fa-solid fa-chevron-right"></i></button></div>' +
                        (j.bayram_eksik ? '<div class="ikr-ipucu tatil" style="margin-bottom:12px"><i class="fa-solid fa-triangle-exclamation"></i><span>' + y + ' yılının dinî bayram tarihleri sistemde tanımlı değil. ' + (yonet ? 'Aşağıdan bayram günlerini tek tek ekleyebilirsiniz.' : 'İK yöneticisi ekleyebilir.') + '</span></div>' : '') +
                        '<div>' + j.liste.map(function (t) {
                            return '<div class="ikr-tatil-satir"><b>' + gunKisa(t.tarih) + '</b><span>' + esc(t.ad) + '<br><small>' + t.gun + '</small></span><span>' + (t.yarim ? '<span class="ikm-cip turuncu">Yarım gün</span>' : '<span class="ikm-cip kirmizi">Tam gün</span>') + (t.ozel ? ' <span class="ikm-cip mavi">Eklenen</span>' : '') + '</span><span>' + (yonet && t.ozel ? '<button type="button" class="ikm-ikon-btn tehlike" data-ts="' + t.id + '" title="Sil" aria-label="Sil"><i class="fa-solid fa-trash"></i></button>' : '') + '</span></div>';
                        }).join('') + '</div>' +
                        (yonet ? '<form id="tt-form" class="ikm-form" style="margin-top:14px;padding-top:14px;border-top:1px solid var(--ik-cizgi)"><b style="font-size:13.5px">Tatil günü ekle</b><div class="ikm-iki" style="margin-top:8px"><label class="ikm-alan"><span>Tarih</span><input type="date" name="tarih" required value="' + y + '-01-01"></label><label class="ikm-alan"><span>Adı</span><input name="ad" required maxlength="100" placeholder="Örn. Kurban Bayramı 1. gün"></label></div>' +
                            '<label class="ikm-ozel-satir"><input type="checkbox" name="yarim"><i></i><span>Yarım gün (arife gibi)</span></label><button type="submit" class="ikm-btn ikm-btn-ana" style="margin-top:10px"><i class="fa-solid fa-plus"></i> Ekle</button></form>' : '');
                    $('#tt-o', g).onclick = function () { yukle(y - 1); }; $('#tt-s', g).onclick = function () { yukle(y + 1); };
                    $$('[data-ts]', g).forEach(function (b) { b.onclick = function () { gonder('tatil_sil', { id: b.dataset.ts }).then(function () { bildir('Silindi.', 'basari'); yukle(y); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
                    var tf = $('#tt-form', g); if (tf) tf.onsubmit = function (e) { e.preventDefault(); var v = { tarih: tf.tarih.value, ad: tf.ad.value, yarim: tf.yarim.checked ? 1 : '' }; gonder('tatil_kaydet', v).then(function () { bildir('Tatil günü eklendi.', 'basari'); yukle(y); }).catch(function (er) { bildir(er.message, 'hata'); }); };
                }).catch(function (e) { $('.ikm-modal-govde', mm.el).innerHTML = M.hataKutu(e.message); });
            }
            yukle(yil);
        }
        function mesaiRozet() {
            var e = document.getElementById('ikm-r-mesai'); if (!e || !Y().ik_mesai_yonet) return;
            getir({ islem: 'mesailer', durum: 'beklemede', bas: '2020-01-01', bit: '2099-12-31' }).then(function (j) { var n = j.toplam.bek_say; e.textContent = n > 99 ? '99+' : n; e.hidden = !(n > 0); }).catch(function () {});
        }
        setTimeout(mesaiRozet, 1200);
        M.kayit('mesailer', 'Mesai', 'Fazla mesai kayıtları; hafta sonu ve resmî tatil (bayram) mesaileri ayrıca işaretlenir.', function () { mesaiCiz(); });

        /* ====================================================================== */
        /*  ANA PANEL: "Bugün kimler?" kartı                                      */
        /* ====================================================================== */
        function kisiSatir(k, alt, sag) {
            var benId = (window.IK_BEN || {}).id, tikla = Y().ik_personel_gor || k.id === benId;
            return '<' + (tikla ? 'button type="button" data-kisi="' + k.id + '"' : 'div') + ' class="ikr-kisi">' + av(k.ad, k.renk, k.resim) + '<span><b>' + esc(k.ad) + '</b><small>' + alt + '</small></span>' + (sag || '') + '</' + (tikla ? 'button' : 'div') + '>';
        }
        function kalanYaz(n) { return n === 0 ? 'Bugün' : n === 1 ? 'Yarın' : n + ' gün sonra'; }
        M.genelEk = function (kok, goster) {
            if (!goster) return;
            getir({ islem: 'pano' }).then(function (j) {
                var izg = $('.ikm-izgara', kok); if (!izg || !kok.isConnected) return;
                $$('[data-w="izinli"], [data-w="kutlama"]', izg).forEach(function (e) { e.remove(); });
                var bugunD = j.dogum.filter(function (d) { return d.kalan === 0; }), yakinD = j.dogum.filter(function (d) { return d.kalan > 0; }).slice(0, 5);
                var s1 = '<div class="ikr-bugun-sutun"><h4><i class="fa-solid fa-cake-candles" style="color:#f43f5e"></i> Doğum günleri</h4>' +
                    bugunD.map(function (d) { return '<button type="button" class="ikr-dogum-bugun" data-kisi="' + d.id + '"><span style="font-size:24px">🎂</span><span><b>' + esc(d.ad) + '</b><small>Bugün ' + d.yas + '. yaşını kutluyor · ' + esc(d.birim) + '</small></span></button>'; }).join('') +
                    yakinD.map(function (d) { return kisiSatir(d, gunKisa(d.tarih) + ' · ' + d.yas + '. yaş', '<span class="ikm-cip gri">' + kalanYaz(d.kalan) + '</span>'); }).join('') +
                    (!bugunD.length && !yakinD.length ? '<div class="ikr-bugun-bos"><i class="fa-solid fa-champagne-glasses"></i> Önümüzdeki 30 günde doğum günü yok</div>' : '') + '</div>';
                var s2 = '<div class="ikr-bugun-sutun"><h4><i class="fa-solid fa-umbrella-beach" style="color:#10b981"></i> Şu an izinde <em>' + j.izinde.length + '</em></h4>' +
                    (j.izinde.length ? j.izinde.slice(0, 8).map(function (k) { return kisiSatir(k, esc(k.tur) + ' · Dönüş: ' + esc(gunTam(k.donus)), '<span class="ikm-cip yesil">' + (k.kalan_gun > 1 ? k.kalan_gun + ' gün' : 'Son gün') + '</span>'); }).join('') + (j.izinde.length > 8 ? '<div class="ikm-soluk" style="font-size:12px;padding-top:6px">+' + (j.izinde.length - 8) + ' kişi daha</div>' : '') : '<div class="ikr-bugun-bos"><i class="fa-solid fa-mug-hot"></i> Bugün izinde olan yok</div>') + '</div>';
                var s3 = '<div class="ikr-bugun-sutun"><h4><i class="fa-solid fa-plane-departure" style="color:#0ea5e9"></i> Yakında izne çıkacaklar <em>14 gün</em></h4>' +
                    (j.yaklasan_izin.length ? j.yaklasan_izin.slice(0, 5).map(function (k) { return kisiSatir(k, gunKisa(k.baslangic) + ' – ' + gunKisa(k.bitis) + ' · ' + esc(k.tur), '<span class="ikm-cip mavi">' + k.gun + ' gün</span>'); }).join('') : '<div class="ikr-bugun-bos"><i class="fa-regular fa-calendar-check"></i> Planlı izin yok</div>') +
                    ((j.yildonumu || []).length ? '<h4 style="margin-top:14px"><i class="fa-solid fa-award" style="color:#8b5cf6"></i> İş yıldönümü</h4>' + j.yildonumu.slice(0, 3).map(function (k) { return kisiSatir(k, k.yil + '. yıl · ' + gunKisa(k.tarih), '<span class="ikm-cip mor">' + kalanYaz(k.kalan) + '</span>'); }).join('') : '') + '</div>';
                var tat = (j.tatiller || []).length ? '<div style="display:flex;gap:8px;flex-wrap:wrap;padding:0 18px 14px">' + j.tatiller.map(function (t) { return '<span class="ikr-tatil-cip"><i class="fa-solid fa-flag"></i> ' + esc(t.ad) + ' · ' + gunKisa(t.tarih) + ' (' + kalanYaz(t.kalan).toLowerCase() + ')</span>'; }).join('') + '</div>' : '';
                if (!bugunD.length && !yakinD.length && !j.izinde.length && !(j.yaklasan_izin || []).length && !(j.yildonumu || []).length && !(j.tatiller || []).length) return; // özel gün yoksa kutu yer kaplamasın
                var kart = document.createElement('div'); kart.className = 'ikm-kart ikr-bugun'; kart.setAttribute('data-w', 'bugun');
                kart.innerHTML = '<div class="ikr-bugun-ust"><h3><i class="fa-solid fa-sun"></i> Bugün kimler? <span class="ikm-soluk" style="font-weight:500;font-size:13px">' + esc(new Date().toLocaleDateString('tr-TR', { weekday: 'long', day: 'numeric', month: 'long' })) + '</span></h3></div><div class="ikr-bugun-izgara">' + s1 + s2 + s3 + '</div>' + tat;
                izg.insertBefore(kart, izg.firstChild);
                $$('[data-kisi]', kart).forEach(function (b) { b.onclick = function () { M.kartAc(+b.dataset.kisi); }; });
            }).catch(function () {});
        };

        /* ====================================================================== */
        /*  SAYFA ERİŞİMİ (kurum yetkilisi): hangi rol hangi sayfayı görür         */
        /* ====================================================================== */
        var SAYFALAR = [
            ['Genel', [['İzin takvimi', 'ik_menu_takvim', 'Kim hangi gün izinde'], ['Raporlar', 'ik_rapor_gor', 'Geç kalma, izin, avans ve mesai raporları (tüm personel)'], ['Mesai', 'ik_menu_mesai', 'Mesai sayfası; kendi mesaisini bildirme'], ['Puantaj', 'ik_menu_puantaj', 'Aylık devam çizelgesi'], ['Site özellikleri', 'ik_menu_ozellik', 'Yardım ve özellik rehberi']]],
            ['Talepler ve para', [['Personel masrafları', 'ik_menu_masraf', 'Masraf talepleri'], ['Avans talepleri', 'ik_menu_avans', 'Avans talepleri'], ['Bordro kayıtları', 'ik_menu_bordro', 'Aylık maaş / bordro']]],
            ['Duyuru ve gelişim', [['Duyurular, Dosyalar, Anketler', 'ik_menu_duyuru', 'Duyuru ve paylaşım sayfaları'], ['Eğitimler', 'ik_menu_egitim', 'Eğitim kayıtları'], ['Performans', 'ik_menu_performans', 'Performans değerlendirmeleri']]],
            ['Personel yönetimi', [['Personel ve Birimler', 'ik_personel_gor', 'Tüm personel kartları'], ['Özlük evrakları ve Zimmetler', 'ik_belge', 'Belge ve zimmet yönetimi'], ['Kullanıcılar', 'ik_kullanici_yonet', 'Hesap ekleme, rol, şifre sıfırlama']]],
            ['Giriş-çıkış ve yönetim', [['Giriş-çıkış kayıtları', 'ik_giris_gor', 'Şu an kimler içeride, kayıtlar, raporlar'], ['Giriş-çıkış yönetimi', 'ik_giris_yonet', 'Vardiya, cihaz, nokta, ayarlar, kayıt düzeltme'], ['İzin akışı ve bildirim kanalları', 'ik_akis_yonet', 'Onay akışı, SMS / e-posta ayarları'], ['İK ayarları', 'ik_ayarlar', 'Birim listesi ve genel ayarlar']]]
        ];
        function erisimCiz() {
            if (!Y().kurucu) { icerik.innerHTML = '<div class="ikm-kart">' + bosKutu('fa-lock', 'Bu sayfa yalnızca kurum yetkilisine açıktır') + '</div>'; return; }
            icerik.innerHTML = M.yukleniyor();
            var YA = M.istek('tb_yetki_islem.php');
            function yukle() {
                YA.getir({ islem: 'liste', modul: 'ik' }).then(function (j) {
                    var tan = {}; j.yetkiler.forEach(function (y) { tan[y.anahtar] = y; });
                    function etkin(anahtar, rol) {
                        var t = tan[anahtar]; if (!t) return false; var v = null, hr = null;
                        j.kurallar.forEach(function (k) { if (k.yetkiler[anahtar] === undefined) return; if (k.tip === 'rol' && k.deger === rol) v = k.yetkiler[anahtar]; if (k.tip === 'herkes') hr = k.yetkiler[anahtar]; });
                        if (v !== null) return v === 1; if (hr !== null) return hr === 1;
                        return t.varsayilan === 'Herkes' || (rol === 'yetkili' && t.varsayilan.indexOf('yetkili') >= 0);
                    }
                    function ozel(anahtar) { var n = 0; j.kurallar.forEach(function (k) { if ((k.tip === 'gorev' || k.tip === 'kullanici' || k.tip === 'birim' || k.tip === 'unvan') && k.yetkiler[anahtar] !== undefined) n++; }); return n; }
                    function acik(anahtar, rol) { return j.kurallar.some(function (k) { return k.tip === 'rol' && k.deger === rol && k.yetkiler[anahtar] !== undefined; }); }
                    var h = '<div class="ikm-kart" style="padding:0;overflow:hidden"><div style="padding:16px 18px;border-bottom:1px solid var(--ik-cizgi)"><h3 style="margin:0 0 4px;font-size:16px"><i class="fa-solid fa-lock" style="color:var(--ik-vurgu)"></i> Hangi rol hangi sayfayı görebilir?</h3>' +
                        '<p class="ikm-soluk" style="margin:0;font-size:13px">Anahtarı kapatınca o sayfa, ilgili rolün menüsünden kalkar ve sunucuda da erişimi engellenir. Kurum yetkilisi her şeyi görür. Kişiye, birime, unvana veya göreve özel kurallar için <button type="button" class="ikm-link" id="ea-gelismis">gelişmiş yetkileri</button> kullanın.</p></div>' +
                        '<div class="ikr-erisim-satir ikr-erisim-baslik"><span>Sayfa</span><span>Personel rolü</span><span>Yetkili rolü</span><span>Özel kural</span></div>';
                    SAYFALAR.forEach(function (g) {
                        h += '<div class="ikr-erisim-grup">' + esc(g[0]) + '</div>';
                        g[1].forEach(function (s) {
                            if (!tan[s[1]]) return;
                            h += '<div class="ikr-erisim-satir"><div class="ikr-erisim-ad"><b>' + esc(s[0]) + '</b><small>' + esc(s[2]) + '</small></div>' +
                                ['personel', 'yetkili'].map(function (rol) { return '<label class="ikr-anahtar"><input type="checkbox" data-a="' + s[1] + '" data-rol="' + rol + '"' + (etkin(s[1], rol) ? ' checked' : '') + '><i></i><span>' + (etkin(s[1], rol) ? 'Görür' : 'Görmez') + (acik(s[1], rol) ? '' : ' <small class="ikm-soluk">(varsayılan)</small>') + '</span></label>'; }).join('') +
                                '<span>' + (ozel(s[1]) ? '<span class="ikm-cip mavi">' + ozel(s[1]) + ' özel kural</span>' : '<span class="ikm-soluk" style="font-size:12px">—</span>') + '</span></div>';
                        });
                    });
                    h += '</div>';
                    icerik.innerHTML = h;
                    $$('input[data-a]', icerik).forEach(function (c) {
                        c.onchange = function () {
                            c.disabled = true;
                            YA.gonder('kaydet', { modul: 'ik', yetki: c.dataset.a, hedef_tipi: 'rol', hedef_deger: c.dataset.rol, izin: c.checked ? '1' : '0' }).then(function () { bildir('Kaydedildi.', 'basari'); return ilkVeriYenile(); }).then(yukle).catch(function (e) { bildir(e.message, 'hata'); yukle(); });
                        };
                    });

                    // Onay / talep bildirimleri kime gitsin?
                    var OA = {}; try { OA = JSON.parse((j.ayar_onay_alici) || '{}') || {}; } catch (e0) {}
                    function onayKart(ayarJ) {
                        try { OA = JSON.parse(ayarJ || '{}') || {}; } catch (e1) { OA = {}; }
                        var T = [['izin', 'İzin talepleri', 'fa-umbrella-beach'], ['masraf', 'Masraf talepleri', 'fa-receipt'], ['avans', 'Avans talepleri', 'fa-hand-holding-dollar'], ['mesai', 'Mesai bildirimleri', 'fa-moon']];
                        var kb = $('#ea-onay'); if (!kb) { kb = document.createElement('div'); kb.id = 'ea-onay'; icerik.appendChild(kb); }
                        kb.className = 'ikm-kart'; kb.style.cssText = 'padding:0;overflow:hidden;margin-top:16px';
                        kb.innerHTML = '<div style="padding:16px 18px;border-bottom:1px solid var(--ik-cizgi)"><h3 style="margin:0 0 4px;font-size:16px"><i class="fa-solid fa-bell" style="color:var(--ik-vurgu)"></i> Onay bildirimleri kime gitsin?</h3><p class="ikm-soluk" style="margin:0;font-size:13px">Yeni talep geldiğinde bildirimi kimlerin alacağını seçin. “Yalnızca yöneticiler”, talep sahibinin bağlı olduğu yönetici ile birim müdürlerine gönderir (bulunamazsa yetkililere). “Seçili kişiler”de yalnızca işaretlediğiniz kişiler haber alır.</p></div>' +
                            T.map(function (t) {
                                var a = OA[t[0]] || {}, mod = a.mod || 'yetkili', sec = (a.kisiler || []).map(Number);
                                return '<div class="ea-oa" data-t="' + t[0] + '"><div class="ea-oa-ust"><span><i class="fa-solid ' + t[2] + '"></i> <b>' + t[1] + '</b></span><div class="ikm-segment">' +
                                    [['yetkili', 'Onay yetkisi olanlar'], ['yonetici', 'Yalnızca yöneticiler'], ['secili', 'Seçili kişiler']].map(function (m) { return '<button type="button" data-m="' + m[0] + '"' + (mod === m[0] ? ' class="aktif"' : '') + '>' + m[1] + '</button>'; }).join('') + '</div></div>' +
                                    '<div class="ea-oa-kisi"' + (mod === 'secili' ? '' : ' hidden') + '><input type="search" placeholder="Kişi ara…" class="ea-ara"><div class="ea-liste">' + (M.veri().kisiler || []).map(function (k) { return '<label><input type="checkbox" value="' + k.id + '"' + (sec.indexOf(k.id) >= 0 ? ' checked' : '') + '> <span>' + esc(k.ad) + '</span></label>'; }).join('') + '</div></div></div>';
                            }).join('') + '<div style="padding:12px 18px"><button type="button" class="ikm-btn ikm-btn-ana" id="ea-oa-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button></div>';
                        $$('.ea-oa', kb).forEach(function (r) {
                            $$('[data-m]', r).forEach(function (b) { b.onclick = function () { $$('[data-m]', r).forEach(function (x) { x.classList.toggle('aktif', x === b); }); $('.ea-oa-kisi', r).hidden = b.dataset.m !== 'secili'; }; });
                            var ar = $('.ea-ara', r); ar.oninput = function () { var q = ar.value.toLocaleLowerCase('tr-TR'); $$('.ea-liste label', r).forEach(function (l) { l.style.display = !q || l.textContent.toLocaleLowerCase('tr-TR').indexOf(q) >= 0 ? '' : 'none'; }); };
                        });
                        $('#ea-oa-kaydet', kb).onclick = function () {
                            var o = {}; $$('.ea-oa', kb).forEach(function (r) { var m = $('[data-m].aktif', r); o[r.dataset.t] = { mod: m ? m.dataset.m : 'yetkili', kisiler: $$('.ea-liste input:checked', r).map(function (c) { return +c.value; }) }; });
                            var d = this; d.disabled = true; M.gonder('ayarlar_kaydet', { onay_alici: JSON.stringify(o) }).then(function () { bildir('Bildirim alıcıları kaydedildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }).then(function () { d.disabled = false; });
                        };
                    }
                    M.getir({ islem: 'ayarlar_getir' }).then(function (r) { onayKart((r.ayar || {}).onay_alici); }).catch(function () { onayKart(''); });
                    var gb = $('#ea-gelismis'); if (gb) gb.onclick = function () { var b = document.getElementById('ik-yetkiler'); if (b) b.click(); };
                }).catch(function (e) { icerik.innerHTML = M.hataKutu(e.message); });
            }
            yukle();
        }
        function ilkVeriYenile() { try { return M.yenileVeri(); } catch (e) { return Promise.resolve(); } }
        M.kayit('sayfa_erisim', 'Sayfa erişimi', 'Rollerin hangi sayfaları görebileceğini tek yerden belirleyin.', function () { erisimCiz(); });
    }
    basla();
})();
