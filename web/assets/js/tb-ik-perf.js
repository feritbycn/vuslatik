/* ============================================================
 * tb-ik-perf.js — Performans: 90/180/270/360° süreçler, hazır şablonlar, kişi seçerek değerlendirme,
 * hedefler, hatırlatma, bireysel ve toplu raporlar
 * ============================================================ */
(function () {
    'use strict';
    var M = window.IKM; if (!M) return;
    var $ = M.$, $$ = M.$$, esc = M.esc, getir = M.getir, gonder = M.gonder, bildir = M.bildir, modal = M.modal;
    var IL = { kendi: 'Kendisi', amir: 'Yönetici', es: 'Takım arkadaşı', ast: 'Ast' }, ILR = { kendi: '#8b5cf6', amir: '#6366f1', es: '#0ea5e9', ast: '#14b8a6' };
    var TURAC = { 90: 'Yalnız yönetici değerlendirir', 180: 'Kişi kendini + yöneticisi değerlendirir', 270: '180° + takım arkadaşları', 360: '270° + astlar (tam çevre)' };
    function nz(x) { return String(x || '').toLocaleLowerCase('tr-TR'); }
    var S = { sekme: 'benim', surec: null };
    function ust() {
        var y = M.yetki() || {}, yon = !!(y.ik_performans_yonet || y.kurucu);
        var t = [['benim', 'Değerlendirmelerim', 'fa-pen-to-square'], ['hedef', 'Hedefler', 'fa-bullseye']];
        if (yon) { t.push(['surec', 'Süreçler', 'fa-diagram-project']); }
        t.push(['kayit', 'Puan kayıtları', 'fa-star']);
        return '<div class="ikm-sekme-cubuk pf-sekme">' + t.map(function (x) { return '<button type="button" data-ps="' + x[0] + '" class="' + (S.sekme === x[0] ? 'aktif' : '') + '"><i class="fa-solid ' + x[2] + '"></i> ' + x[1] + '</button>'; }).join('') + '</div>' +
            (yon ? '<div class="pf-eylem"><button type="button" class="ikm-btn ikm-btn-ana" id="pf-yeni"><i class="fa-solid fa-user-check"></i> Performans değerlendir</button></div>' : '');
    }
    function cerceve(govde, sonra) {
        M.icerik.innerHTML = '<div class="pf">' + ust() + '<div id="pf-g">' + govde + '</div></div>';
        $$('[data-ps]', M.icerik).forEach(function (b) { b.onclick = function () { S.sekme = b.dataset.ps; S.surec = null; ciz(); }; });
        var y = $('#pf-yeni'); if (y) y.onclick = baslat;
        if (sonra) sonra();
    }
    function ciz() { ({ benim: benim, hedef: hedefler, surec: surecler, kayit: kayitlar }[S.sekme] || benim)(); }
    function hata(e) { $('#pf-g') ? $('#pf-g').innerHTML = M.hataKutu(e.message) : (M.icerik.innerHTML = M.hataKutu(e.message)); }
    function yildiz(n, buyuk) { var o = ''; for (var i = 1; i <= 5; i++) o += '<i class="fa-' + (i <= Math.round(n) ? 'solid' : 'regular') + ' fa-star"></i>'; return '<span class="pf-yildiz' + (buyuk ? ' b' : '') + '">' + o + '</span>'; }
    function bar(v, renk) { return v == null ? '<span class="ikm-soluk">—</span>' : '<div class="pf-bar"><i style="width:' + (v * 20) + '%;background:' + (renk || 'var(--ik-vurgu)') + '"></i></div><b class="pf-bar-d">' + v.toFixed(1).replace('.', ',') + '</b>'; }

    /* ------------ Değerlendirmelerim ------------ */
    function benim() {
        cerceve(M.yukleniyor(), function () {
            getir({ islem: 'perf_bekleyen' }).then(function (j) {
                var b = j.liste.filter(function (x) { return x.durum !== 'tamam'; }), t = j.liste.filter(function (x) { return x.durum === 'tamam'; });
                var kart = function (x) {
                    return '<div class="ikm-kart pf-kart"><div class="ikm-kart-baslik"><h3>' + esc(x.kendi ? 'Öz değerlendirme' : x.hedef) + '</h3><span class="ikm-cip" style="--c:' + ILR[x.iliski] + '">' + IL[x.iliski] + '</span></div><p class="ikm-soluk kucuk">' + esc(x.surec) + ' · ' + x.tur + '°' + (x.bit ? ' · son ' + M.tarih(x.bit) : '') + '</p>' +
                        '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk ' + (x.durum === 'tamam' ? '' : 'ikm-btn-ana') + '" data-pa="' + x.id + '">' + (x.durum === 'tamam' ? '<i class="fa-solid fa-eye"></i> Görüntüle' : '<i class="fa-solid fa-pen"></i> Değerlendir') + '</button></div></div>';
                };
                $('#pf-g').innerHTML = (b.length ? '<h4 class="pf-bas">Bekleyen (' + b.length + ')</h4><div class="ikm-izgara">' + b.map(kart).join('') + '</div>' : M.bosKutu('fa-circle-check', 'Bekleyen değerlendirme yok', 'Size atanan değerlendirmeler burada görünür.')) +
                    (t.length ? '<h4 class="pf-bas">Tamamlanan (' + t.length + ')</h4><div class="ikm-izgara">' + t.map(kart).join('') + '</div>' : '');
                $$('[data-pa]', M.icerik).forEach(function (x) { x.onclick = function () { form(+x.dataset.pa); }; });
            }).catch(hata);
        });
    }
    function form(id) {
        getir({ islem: 'perf_yanit_getir', id: id }).then(function (j) {
            var kilit = j.durum === 'tamam', y = j.yanitlar || {}, grup = '', h = '';
            j.sorular.forEach(function (q) {
                if (q.grup !== grup && q.tur === 'puan') { grup = q.grup; h += '<h4 class="pf-grup">' + esc(grup) + '</h4>'; }
                if (q.tur === 'puan') h += '<div class="pf-soru"><p>' + esc(q.metin) + '</p><div class="pf-skala" data-q="' + q.id + '">' + [1, 2, 3, 4, 5].map(function (i) { return '<button type="button" data-v="' + i + '" class="' + (+y[q.id] === i ? 'aktif' : '') + '"' + (kilit ? ' disabled' : '') + '>' + i + '</button>'; }).join('') + '<small>1 Çok zayıf · 5 Mükemmel</small></div></div>';
                else h += '<label class="ikm-alan pf-soru"><span>' + esc(q.metin) + '</span><textarea data-t="' + q.id + '" rows="2" maxlength="1500"' + (kilit ? ' disabled' : '') + '>' + esc(y[q.id] || '') + '</textarea></label>';
            });
            var m = modal({ baslik: esc(j.iliski === 'kendi' ? 'Öz değerlendirme' : j.hedef), ikon: 'fa-pen-to-square', gen: 680, sabit: !kilit,
                govde: '<p class="ikm-soluk kucuk">' + esc(j.surec) + ' · ' + IL[j.iliski] + ' olarak değerlendiriyorsunuz</p><div class="pf-ilerleme"><i></i></div>' + h + '<label class="ikm-alan"><span>Ek yorum</span><textarea id="pf-y" rows="3" maxlength="2000"' + (kilit ? ' disabled' : '') + '>' + esc(j.yorum || '') + '</textarea></label>',
                alt: kilit ? '<button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Kapat</button>' : '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn" id="pf-tas">Taslak kaydet</button><button type="button" class="ikm-btn ikm-btn-ana" id="pf-bit"><i class="fa-solid fa-check"></i> Gönder</button>' });
            var cev = {}; Object.keys(y).forEach(function (k) { cev[k] = y[k]; });
            function say() { var top = j.sorular.filter(function (q) { return q.tur === 'puan'; }).length, dol = j.sorular.filter(function (q) { return q.tur === 'puan' && cev[q.id]; }).length; $('.pf-ilerleme i', m.el).style.width = (top ? dol * 100 / top : 0) + '%'; }
            say();
            $$('.pf-skala', m.el).forEach(function (s) { s.onclick = function (e) { var b = e.target.closest('[data-v]'); if (!b || kilit) return; cev[s.dataset.q] = +b.dataset.v; $$('[data-v]', s).forEach(function (x) { x.classList.toggle('aktif', x === b); }); say(); }; });
            function yolla(bitir) {
                $$('[data-t]', m.el).forEach(function (t) { cev[t.dataset.t] = t.value; });
                return gonder('perf_yanit_kaydet', { id: id, yanitlar: JSON.stringify(cev), yorum: $('#pf-y', m.el).value, bitir: bitir ? 1 : 0 }).then(function () { m.kapat(); bildir(bitir ? 'Değerlendirmeniz gönderildi, teşekkürler.' : 'Taslak kaydedildi.', 'basari'); benim(); }).catch(function (e) { bildir(e.message, 'hata'); });
            }
            if (!kilit) { $('#pf-tas', m.el).onclick = function () { yolla(false); }; $('#pf-bit', m.el).onclick = function () { yolla(true); }; }
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }

    /* ------------ Başlat: kişi seç → ayarlar ------------ */
    function baslat() {
        var kisiler = (M.veri() && M.veri().kisiler) || [], sec = {};
        var m = modal({ baslik: 'Performans değerlendir', ikon: 'fa-user-check', gen: 520, sabit: true,
            govde: '<p class="ikm-soluk kucuk">Değerlendirilecek kişileri seçin. Aşağıdaki kutudan isim veya birim arayabilirsiniz.</p><div class="pf-ara"><input type="search" class="ikm-ks-ara" id="pk-ara" placeholder="İsim veya birim ara…" autocomplete="off"><button type="button" class="ikm-btn ikm-btn-kucuk" id="pk-hepsi">Görünenleri seç</button></div><div class="ikm-ks-liste pk-liste"></div>',
            alt: '<span class="ikm-soluk" id="pk-say">0 kişi seçili</span><span class="ikd-bosluk"></span><button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="pk-ileri">Devam <i class="fa-solid fa-arrow-right"></i></button>' });
        var liste = $('.pk-liste', m.el);
        function goster() { return kisiler.filter(function (k) { var a = nz($('#pk-ara', m.el).value); return !a || nz(k.ad + ' ' + (k.departman || '')).indexOf(a) >= 0; }); }
        function ciz() {
            liste.innerHTML = goster().slice(0, 150).map(function (k) { return '<label class="ikm-satir pk-satir' + (sec[k.id] ? ' secili' : '') + '"><input type="checkbox" data-id="' + k.id + '"' + (sec[k.id] ? ' checked' : '') + '>' + M.av(k.ad, k.renk, k.resim) + '<span class="ikm-satir-ic"><b>' + esc(k.ad) + '</b><small>' + esc(k.departman || '') + '</small></span></label>'; }).join('') || '<div class="ikm-soluk" style="padding:14px">Sonuç yok</div>';
            $('#pk-say', m.el).textContent = Object.keys(sec).filter(function (k) { return sec[k]; }).length + ' kişi seçili';
        }
        liste.onchange = function (e) { var c = e.target.closest('[data-id]'); if (c) { sec[c.dataset.id] = c.checked; c.closest('.pk-satir').classList.toggle('secili', c.checked); $('#pk-say', m.el).textContent = Object.keys(sec).filter(function (k) { return sec[k]; }).length + ' kişi seçili'; } };
        $('#pk-ara', m.el).oninput = ciz; $('#pk-hepsi', m.el).onclick = function () { goster().forEach(function (k) { sec[k.id] = true; }); ciz(); };
        ciz(); setTimeout(function () { $('#pk-ara', m.el).focus(); }, 100);
        $('#pk-ileri', m.el).onclick = function () { var ids = Object.keys(sec).filter(function (k) { return sec[k]; }).map(Number); if (!ids.length) { bildir('En az bir kişi seçin.', 'uyari'); return; } m.kapat(); setTimeout(function () { ayarlar(ids); }, 180); };
    }
    function ayarlar(ids) {
        getir({ islem: 'perf_sablonlar' }).then(function (j) {
            var tur = 180, sab = j.sablonlar[0].anahtar, bugun = M.bugunYmd();
            var m = modal({ baslik: 'Değerlendirme ayarları', ikon: 'fa-sliders', gen: 600, sabit: true,
                govde: '<div class="ikm-form"><label class="ikm-alan"><span>Süreç adı <b>*</b></span><input type="text" id="pa-ad" maxlength="160" value="' + esc('Performans değerlendirmesi ' + new Date().getFullYear()) + '"></label>' +
                    '<div class="ikm-alan"><span>Değerlendirme türü</span><div class="pf-tur" id="pa-tur">' + [90, 180, 270, 360].map(function (t) { return '<button type="button" data-t="' + t + '" class="' + (t === tur ? 'aktif' : '') + '"><strong>' + t + '°</strong><small>' + TURAC[t] + '</small></button>'; }).join('') + '</div></div>' +
                    '<div class="ikm-alan"><span>Hazır şablon</span><div class="pf-sab" id="pa-sab">' + j.sablonlar.map(function (s) { return '<button type="button" data-s="' + s.anahtar + '" class="' + (s.anahtar === sab ? 'aktif' : '') + '"><b>' + esc(s.ad) + '</b><small>' + esc(s.aciklama) + ' · ' + s.sorular.length + ' soru</small></button>'; }).join('') + '</div></div>' +
                    '<div class="ikm-iki"><label class="ikm-alan"><span>Dönem başlangıcı</span><input type="date" id="pa-bas"></label><label class="ikm-alan"><span>Son yanıt tarihi</span><input type="date" id="pa-bit"></label></div>' +
                    '<label class="ikm-onay-satir"><input type="checkbox" id="pa-an" checked> <span>Takım arkadaşı ve ast yorumlarını anonim göster</span></label>' +
                    '<p class="ikm-soluk kucuk">' + ids.length + ' kişi için, yöneticileri ve türe göre ilgili kişiler değerlendiren olarak atanır; hepsine bildirim gider.</p></div>',
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="pa-ok"><i class="fa-solid fa-paper-plane"></i> Başlat</button>' });
            $$('#pa-tur button', m.el).forEach(function (b) { b.onclick = function () { tur = +b.dataset.t; $$('#pa-tur button', m.el).forEach(function (x) { x.classList.toggle('aktif', x === b); }); }; });
            $$('#pa-sab button', m.el).forEach(function (b) { b.onclick = function () { sab = b.dataset.s; $$('#pa-sab button', m.el).forEach(function (x) { x.classList.toggle('aktif', x === b); }); }; });
            $('#pa-ok', m.el).onclick = function () {
                var btn = this; btn.disabled = true;
                gonder('perf_surec_kaydet', { ad: $('#pa-ad', m.el).value, tur: tur, sablon: sab, bas: $('#pa-bas', m.el).value, bit: $('#pa-bit', m.el).value, anonim: $('#pa-an', m.el).checked ? 1 : 0, kisiler: ids.join(',') }).then(function (r) {
                    m.kapat(); bildir('Süreç başlatıldı, ' + r.atama + ' değerlendirme atandı.', 'basari'); S.sekme = 'surec'; ciz();
                }).catch(function (e) { btn.disabled = false; bildir(e.message, 'hata'); });
            };
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }

    /* ------------ Süreçler ------------ */
    function surecler() {
        if (S.surec) return detay(S.surec);
        cerceve(M.yukleniyor(), function () {
            getir({ islem: 'perf_surecler' }).then(function (j) {
                $('#pf-g').innerHTML = j.liste.length ? '<div class="ikm-izgara">' + j.liste.map(function (s) {
                    var yz = s.toplam ? Math.round(s.tamam * 100 / s.toplam) : 0;
                    return '<div class="ikm-kart pf-kart tikla" data-s="' + s.id + '"><div class="ikm-kart-baslik"><h3>' + esc(s.ad) + '</h3><span class="ikm-cip" style="--c:' + (s.durum === 'aktif' ? '#10b981' : '#64748b') + '">' + (s.durum === 'aktif' ? 'Aktif' : 'Kapalı') + '</span></div>' +
                        '<p class="ikm-soluk kucuk">' + s.tur + '° · ' + s.kisi + ' kişi' + (s.bit ? ' · son ' + M.tarih(s.bit) : '') + '</p><div class="ikm-ilerleme"><i class="k" style="width:' + yz + '%"></i></div><p class="ikm-soluk kucuk">' + s.tamam + '/' + s.toplam + ' değerlendirme tamamlandı (%' + yz + ')</p></div>';
                }).join('') + '</div>' : M.bosKutu('fa-diagram-project', 'Henüz süreç yok', '“Performans değerlendir” düğmesiyle kişileri seçip başlatın.');
                $$('[data-s]', M.icerik).forEach(function (k) { k.onclick = function () { S.surec = +k.dataset.s; surecler(); }; });
            }).catch(hata);
        });
    }
    function detay(id) {
        cerceve(M.yukleniyor(), function () {
            getir({ islem: 'perf_surec_detay', id: id }).then(function (j) {
                var s = j.surec, bek = j.atamalar.filter(function (a) { return a.durum !== 'tamam'; }).length, kisiG = {};
                j.atamalar.forEach(function (a) { (kisiG[a.hedef_id] = kisiG[a.hedef_id] || { ad: a.hedef, l: [] }).l.push(a); });
                $('#pf-g').innerHTML = '<div class="pf-det-ust"><button type="button" class="ikm-btn ikm-btn-kucuk" id="pd-geri"><i class="fa-solid fa-arrow-left"></i> Süreçler</button><h3>' + esc(s.ad) + ' <small>' + s.tur + '°</small></h3><span class="ikd-bosluk"></span>' +
                    '<button type="button" class="ikm-btn" id="pd-rapor"><i class="fa-solid fa-chart-column"></i> Toplu rapor</button>' + (s.durum === 'aktif' ? '<button type="button" class="ikm-btn" id="pd-hat"' + (bek ? '' : ' disabled') + '><i class="fa-solid fa-bell"></i> Hatırlatma gönder (' + bek + ')</button><button type="button" class="ikm-btn" id="pd-kapat">Kapat</button>' : '<button type="button" class="ikm-btn" id="pd-ac">Yeniden aç</button>') + '<button type="button" class="ikm-btn ikm-btn-tehlike" id="pd-sil"><i class="fa-solid fa-trash"></i></button></div>' +
                    '<div class="pf-kisiler">' + Object.keys(kisiG).map(function (k) {
                        var g = kisiG[k], tam = g.l.filter(function (a) { return a.durum === 'tamam'; }).length;
                        return '<div class="ikm-kart pf-kisi"><div class="ikm-kart-baslik"><h3>' + esc(g.ad) + '</h3><span class="ikm-soluk kucuk">' + tam + '/' + g.l.length + '</span>' + (tam ? '<button type="button" class="ikm-btn ikm-btn-kucuk" data-rp="' + k + '"><i class="fa-solid fa-file-lines"></i> Rapor</button>' : '') + '</div>' +
                            g.l.map(function (a) { return '<div class="pf-atama"><span class="ikm-cip" style="--c:' + ILR[a.iliski] + '">' + IL[a.iliski] + '</span><span class="pf-ad">' + esc(a.deg) + '</span>' + (a.durum === 'tamam' ? '<i class="fa-solid fa-circle-check" style="color:#10b981" title="Tamamlandı"></i>' : '<button type="button" class="ikm-ikon-btn" data-h="' + a.deg_id + '" title="Hatırlat"><i class="fa-regular fa-bell"></i></button>' + (s.durum === 'aktif' && a.iliski !== 'kendi' ? '<button type="button" class="ikm-ikon-btn" data-as="' + a.id + '" title="Atamayı kaldır"><i class="fa-solid fa-xmark"></i></button>' : '')) + '</div>'; }).join('') +
                            (s.durum === 'aktif' ? '<button type="button" class="ikm-btn ikm-btn-kucuk pf-ekle" data-ek="' + k + '"><i class="fa-solid fa-user-plus"></i> Değerlendiren ekle</button>' : '') + '</div>';
                    }).join('') + '</div>';
                $('#pd-geri').onclick = function () { S.surec = null; surecler(); };
                $('#pd-rapor').onclick = function () { rapor(id, 0); };
                if ($('#pd-hat')) $('#pd-hat').onclick = function () { gonder('perf_hatirlat', { surec_id: id }).then(function (r) { bildir(r.sayi + ' kişiye hatırlatma gönderildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); };
                var dur = function (d) { return function () { gonder('perf_surec_durum', { id: id, durum: d }).then(function () { detay(id); }); }; };
                if ($('#pd-kapat')) $('#pd-kapat').onclick = dur('kapali'); if ($('#pd-ac')) $('#pd-ac').onclick = dur('aktif');
                $('#pd-sil').onclick = function () { M.onayla({ baslik: 'Süreç silinsin mi?', mesaj: 'Tüm yanıtlar ve raporlar kalıcı olarak silinir.', evet: 'Sil', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('perf_surec_sil', { id: id }).then(function () { S.surec = null; surecler(); }); }); };
                $$('[data-rp]', M.icerik).forEach(function (b) { b.onclick = function () { rapor(id, +b.dataset.rp); }; });
                $$('[data-h]', M.icerik).forEach(function (b) { b.onclick = function () { gonder('perf_hatirlat', { surec_id: id, degerlendiren_id: b.dataset.h }).then(function () { bildir('Hatırlatma gönderildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
                $$('[data-as]', M.icerik).forEach(function (b) { b.onclick = function () { gonder('perf_atama_sil', { id: b.dataset.as }).then(function () { detay(id); }); }; });
                $$('[data-ek]', M.icerik).forEach(function (b) { b.onclick = function () { M.kisiSec('Değerlendiren ekle', function (k) {
                    var m = modal({ baslik: 'İlişki türü', ikon: 'fa-user-group', gen: 380, govde: '<div class="tk-ekle">' + ['amir', 'es', 'ast'].map(function (x) { return '<button type="button" class="ikm-talep-kart" data-i="' + x + '" style="--c:' + ILR[x] + '"><span><i class="fa-solid fa-user"></i></span><div><b>' + esc(k.ad) + ' — ' + IL[x] + '</b></div></button>'; }).join('') + '</div>' });
                    $$('[data-i]', m.el).forEach(function (x) { x.onclick = function () { gonder('perf_atama_ekle', { surec_id: id, hedef_id: b.dataset.ek, degerlendiren_id: k.id, iliski: x.dataset.i }).then(function () { m.kapat(); detay(id); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
                }); }; });
            }).catch(hata);
        });
    }

    /* ------------ Raporlar ------------ */
    function rapor(sid, hid) {
        getir({ islem: 'perf_rapor', surec_id: sid, hedef_id: hid || '' }).then(function (j) {
            var il = Object.keys(IL), h;
            if (!hid) {
                h = j.kisiler.length ? '<div class="pf-tablo-kap"><table class="pf-tablo"><thead><tr><th>Kişi</th><th>Genel (5 üzerinden)</th>' + il.map(function (k) { return '<th>' + IL[k] + '</th>'; }).join('') + '<th>Yanıt</th></tr></thead><tbody>' + j.kisiler.map(function (k) {
                    return '<tr data-k="' + k.id + '"><td><b>' + esc(k.ad) + '</b></td><td class="pf-genel">' + bar(k.genel) + '</td>' + il.map(function (x) { return '<td>' + (k.gruplar[x] == null ? '<span class="ikm-soluk">—</span>' : '<b>' + k.gruplar[x].toFixed(1).replace('.', ',') + '</b>') + '</td>'; }).join('') + '<td>' + k.tamam + '</td></tr>';
                }).join('') + '</tbody></table></div>' : M.bosKutu('fa-chart-column', 'Henüz tamamlanmış yanıt yok');
            } else {
                var k = j.kisiler[0]; if (!k) { bildir('Bu kişi için tamamlanmış yanıt yok.', 'uyari'); return; }
                var g = '';
                h = '<div class="pf-ozet"><div><span>Genel puan</span><b>' + (k.genel == null ? '—' : k.genel.toFixed(1).replace('.', ',')) + '</b>' + (k.genel != null ? yildiz(k.genel, 1) : '') + '</div>' + il.map(function (x) { return '<div style="--c:' + ILR[x] + '"><span>' + IL[x] + '</span><b>' + (k.gruplar[x] == null ? '—' : k.gruplar[x].toFixed(1).replace('.', ',')) + '</b></div>'; }).join('') + '</div>' +
                    '<div class="pf-tablo-kap"><table class="pf-tablo"><thead><tr><th>Soru</th>' + il.map(function (x) { return '<th>' + IL[x] + '</th>'; }).join('') + '</tr></thead><tbody>' + k.sorular.map(function (q) {
                        var r = ''; if (q.grup !== g) { g = q.grup; r = '<tr class="pf-gr"><td colspan="5">' + esc(g) + '</td></tr>'; }
                        return r + '<tr><td>' + esc(q.metin) + '</td>' + il.map(function (x) { var v = q[x]; return '<td class="' + (v != null && k.kendi != null && x !== 'kendi' && Math.abs(v - k.kendi) >= 1 ? 'pf-fark' : '') + '">' + (v == null ? '—' : v.toFixed(1).replace('.', ',')) + '</td>'; }).join('') + '</tr>';
                    }).join('') + '</tbody></table></div>' +
                    (k.yorumlar.length ? '<h4 class="pf-bas">Yorumlar</h4>' + k.yorumlar.map(function (y) { return '<div class="pf-yorum"><span class="ikm-cip" style="--c:' + ILR[y.iliski] + '">' + IL[y.iliski] + '</span> <b>' + esc(y.kim) + '</b>' + y.liste.map(function (z) { return '<p><small>' + esc(z.soru) + '</small><br>' + esc(z.yanit) + '</p>'; }).join('') + '</div>'; }).join('') : '');
            }
            var m = modal({ baslik: esc(j.surec.ad) + (hid ? ' — ' + esc(j.kisiler[0].ad) : ' — Toplu rapor'), ikon: 'fa-chart-column', gen: hid ? 820 : 900, sinif: 'pf-rapor', govde: h,
                alt: '<button type="button" class="ikm-btn" id="pr-yaz"><i class="fa-solid fa-print"></i> Yazdır / PDF</button>' + (hid ? '' : '<button type="button" class="ikm-btn" id="pr-csv"><i class="fa-solid fa-file-csv"></i> CSV</button>') + '<button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Kapat</button>' });
            $$('tr[data-k]', m.el).forEach(function (r) { r.onclick = function () { m.kapat(); setTimeout(function () { rapor(sid, +r.dataset.k); }, 180); }; });
            $('#pr-yaz', m.el).onclick = function () {
                var w = window.open('', '_blank'); if (!w) { bildir('Açılır pencere engellendi.', 'uyari'); return; }
                w.document.write('<!doctype html><meta charset="utf-8"><title>' + esc(j.surec.ad) + '</title><style>body{font:13px system-ui;padding:24px;color:#0f172a}table{border-collapse:collapse;width:100%;margin:10px 0}td,th{border:1px solid #cbd5e1;padding:6px 8px;text-align:left}th{background:#f1f5f9}.pf-bar,.pf-yildiz{display:none}.pf-ozet{display:flex;gap:18px;margin:10px 0}.pf-ozet div{display:grid}.pf-ozet b{font-size:22px}.pf-gr td{background:#eef2ff;font-weight:700}</style><h2>' + esc(j.surec.ad) + (hid ? ' — ' + esc(j.kisiler[0].ad) : '') + '</h2>' + $('.ikm-modal-govde', m.el).innerHTML);
                w.document.close(); setTimeout(function () { w.print(); }, 300);
            };
            if (!hid) $('#pr-csv', m.el).onclick = function () {
                var s = [['Kişi', 'Genel'].concat(il.map(function (x) { return IL[x]; })).concat(['Yanıt'])].concat(j.kisiler.map(function (k) { return [k.ad, k.genel].concat(il.map(function (x) { return k.gruplar[x]; })).concat([k.tamam]); }));
                var csv = '﻿' + s.map(function (r) { return r.map(function (v) { return '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"'; }).join(';'); }).join('\n'), a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' })); a.download = 'performans_' + sid + '.csv'; a.click();
            };
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }

    /* ------------ Hedefler ------------ */
    var HD = { kid: null };
    function hedefler() {
        cerceve(M.yukleniyor(), function () {
            var p = { islem: 'perf_hedef_liste' }; if (HD.kid) p.kullanici_id = HD.kid;
            getir(p).then(function (j) {
                HD.kid = j.kullanici_id; var yon = j.yonet, kisiler = (M.veri().kisiler || []);
                $('#pf-g').innerHTML = '<div class="ikm-arac">' + (yon ? '<select id="ph-kisi">' + kisiler.map(function (k) { return '<option value="' + k.id + '"' + (k.id === j.kullanici_id ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' : '') + '<button type="button" class="ikm-btn ikm-btn-ana" id="ph-yeni" style="margin-left:auto"><i class="fa-solid fa-plus"></i> Hedef ekle</button></div>' +
                    (j.liste.length ? '<div class="ikm-izgara">' + j.liste.map(function (h) {
                        return '<div class="ikm-kart pf-kart"><div class="ikm-kart-baslik"><h3>' + esc(h.baslik) + '</h3>' + (h.durum === 'bitti' ? '<span class="ikm-cip" style="--c:#10b981">Tamamlandı</span>' : '') + '</div>' + (h.aciklama ? '<p class="ikm-soluk kucuk">' + esc(h.aciklama) + '</p>' : '') +
                            '<div class="ikm-ilerleme"><i class="k" style="width:' + h.ilerleme + '%;background:' + (h.ilerleme >= 100 ? '#10b981' : 'var(--ik-vurgu)') + '"></i></div><p class="ikm-soluk kucuk">%' + h.ilerleme + (h.bitis ? ' · hedef ' + M.tarih(h.bitis) : '') + (h.agirlik ? ' · ağırlık %' + h.agirlik : '') + '</p>' +
                            '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk" data-he="' + h.id + '"><i class="fa-solid fa-pen"></i></button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-hs="' + h.id + '"><i class="fa-solid fa-trash"></i></button></div></div>';
                    }).join('') + '</div>' : M.bosKutu('fa-bullseye', 'Hedef yok', 'Hedef ekleyip ilerlemeyi takip edin.'));
                if ($('#ph-kisi')) $('#ph-kisi').onchange = function () { HD.kid = +this.value; hedefler(); };
                $('#ph-yeni').onclick = function () { hedefForm(null, j.kullanici_id); };
                $$('[data-he]', M.icerik).forEach(function (b) { b.onclick = function () { hedefForm(j.liste.filter(function (x) { return x.id === +b.dataset.he; })[0], j.kullanici_id); }; });
                $$('[data-hs]', M.icerik).forEach(function (b) { b.onclick = function () { M.onayla({ baslik: 'Hedef silinsin mi?', evet: 'Sil', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('perf_hedef_sil', { id: b.dataset.hs }).then(hedefler).catch(function (e) { bildir(e.message, 'hata'); }); }); }; });
            }).catch(hata);
        });
    }
    function hedefForm(h, kid) {
        h = h || { baslik: '', aciklama: '', bitis: '', agirlik: 0, ilerleme: 0 };
        var m = modal({ baslik: h.id ? 'Hedefi düzenle' : 'Yeni hedef', ikon: 'fa-bullseye', gen: 480, govde: '<div class="ikm-form"><label class="ikm-alan"><span>Hedef <b>*</b></span><input type="text" id="h-b" maxlength="200" value="' + esc(h.baslik) + '"></label><label class="ikm-alan"><span>Açıklama</span><textarea id="h-a" rows="2" maxlength="1000">' + esc(h.aciklama) + '</textarea></label>' +
            '<div class="ikm-iki"><label class="ikm-alan"><span>Bitiş tarihi</span><input type="date" id="h-t" value="' + esc(h.bitis || '') + '"></label><label class="ikm-alan"><span>Ağırlık (%)</span><input type="number" id="h-w" min="0" max="100" value="' + h.agirlik + '"></label></div>' +
            '<label class="ikm-alan"><span>İlerleme: <b id="h-d">%' + h.ilerleme + '</b></span><input type="range" id="h-i" min="0" max="100" step="5" value="' + h.ilerleme + '"></label></div>',
            alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="h-k"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        $('#h-i', m.el).oninput = function () { $('#h-d', m.el).textContent = '%' + this.value; };
        $('#h-k', m.el).onclick = function () { gonder('perf_hedef_kaydet', { id: h.id || 0, kullanici_id: kid, baslik: $('#h-b', m.el).value, aciklama: $('#h-a', m.el).value, bitis: $('#h-t', m.el).value, agirlik: $('#h-w', m.el).value, ilerleme: $('#h-i', m.el).value }).then(function () { m.kapat(); bildir('Hedef kaydedildi.', 'basari'); hedefler(); }).catch(function (e) { bildir(e.message, 'hata'); }); };
    }

    /* ------------ Eski puan kayıtları ------------ */
    var KD = { kid: null };
    function kayitlar() {
        cerceve(M.yukleniyor(), function () {
            var p = { islem: 'performans' }; if (KD.kid) p.kullanici_id = KD.kid;
            getir(p).then(function (j) {
                KD.kid = j.kullanici_id;
                $('#pf-g').innerHTML = '<div class="ikm-arac">' + (j.yonet ? '<select id="pk-kisi">' + (M.veri().kisiler || []).map(function (k) { return '<option value="' + k.id + '"' + (k.id === j.kullanici_id ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select><button type="button" class="ikm-btn" id="pk-ekle" style="margin-left:auto"><i class="fa-solid fa-plus"></i> Hızlı puan ekle</button>' : '') + '</div>' +
                    (j.liste.length ? '<div class="ikm-izgara">' + j.liste.map(function (p) {
                        return '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-star"></i> ' + esc(p.donem) + '</h3><span class="ikm-puan">★ ' + M.sayi(p.puan) + '<small>/10</small></span></div><div class="ikm-ilerleme"><i class="k" style="width:' + (p.puan * 10) + '%;background:linear-gradient(90deg,#f59e0b,#f43f5e)"></i></div><p class="ikm-soluk kucuk">' + M.tarih(p.tarih) + (p.degerlendiren ? ' · ' + esc(p.degerlendiren) : '') + '</p>' + (p.notlar ? '<p>' + esc(p.notlar) + '</p>' : '') +
                            (j.yonet ? '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-ks="' + p.id + '"><i class="fa-solid fa-trash"></i></button></div>' : '') + '</div>';
                    }).join('') + '</div>' : M.bosKutu('fa-star', 'Puan kaydı yok'));
                if ($('#pk-kisi')) $('#pk-kisi').onchange = function () { KD.kid = +this.value; kayitlar(); };
                if ($('#pk-ekle')) $('#pk-ekle').onclick = function () {
                    var m = modal({ baslik: 'Hızlı puan ekle', ikon: 'fa-star', gen: 440, govde: '<div class="ikm-form"><label class="ikm-alan"><span>Dönem <b>*</b></span><input type="text" id="q-d" placeholder="Örn. 2026 Q3"></label><label class="ikm-alan"><span>Puan (0-10)</span><input type="number" step="0.5" min="0" max="10" id="q-p"></label><label class="ikm-alan"><span>Notlar</span><textarea id="q-n" rows="3"></textarea></label></div>', alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="q-k">Kaydet</button>' });
                    $('#q-k', m.el).onclick = function () { gonder('performans_kaydet', { kullanici_id: KD.kid, donem: $('#q-d', m.el).value, puan: $('#q-p', m.el).value, notlar: $('#q-n', m.el).value }).then(function () { m.kapat(); kayitlar(); }).catch(function (e) { bildir(e.message, 'hata'); }); };
                };
                $$('[data-ks]', M.icerik).forEach(function (b) { b.onclick = function () { M.onayla({ baslik: 'Kayıt silinsin mi?', evet: 'Sil', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('performans_sil', { id: b.dataset.ks }).then(kayitlar); }); }; });
            }).catch(hata);
        });
    }
    M.kayit('performans', 'Performans', '90/180/270/360° değerlendirme, hedefler ve raporlar.', function () { S.surec = null; ciz(); });
})();
