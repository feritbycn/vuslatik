/* ============================================================
 * platform-paket.js — Platform: Paketler sayfası, kuruma paket atama (süreli), kuruma özel indirim
 * ============================================================ */
(function () {
    'use strict';
    var X = window.PFX; if (!X) return;
    var $ = X.$, $$ = X.$$, esc = X.esc, getir = X.getir, gonder = X.gonder, modal = X.modal, bildir = X.bildir, onayla = X.onayla, icerik = X.icerik;
    var PARA = { TRY: '₺', USD: '$', EUR: '€' };
    function fiyat(v, para) { v = +v || 0; var s = v.toLocaleString('tr-TR', { minimumFractionDigits: v % 1 ? 2 : 0, maximumFractionDigits: 2 }); return para === 'TRY' ? s + ' ₺' : (PARA[para] || '') + s; }
    var KAT = {};
    function modulChip(kod) { var m = KAT[kod] || { ad: kod, renk: '#64748b', ikon: 'fa-cube' }; return '<span class="pk-chip" style="--c:' + m.renk + '"><i class="fa-solid ' + m.ikon + '"></i>' + esc(m.ad) + '</span>'; }

    /* ---------------- Paketler sayfası ---------------- */
    X.kayit('paketler', 'Paketler', 'Modül paketlerinin fiyatlarını belirleyin; kurumlara süreli atayın.', function () {
        $('#pf-ust-sag').innerHTML = '<button type="button" class="ikm-btn ikm-btn-ana" id="pk-yeni"><i class="fa-solid fa-plus"></i> <span>Yeni paket</span></button>';
        return getir({ islem: 'paket_liste' }).then(function (j) { KAT = j.katalog; ciz(j); $('#pk-yeni').onclick = function () { paketFormu(null, j); }; });
    });
    function ciz(j) {
        var kartlar = j.paketler.map(function (p) {
            var av = p.aylik > 0 && p.yillik > 0 ? Math.max(0, Math.round((1 - p.yillik / (p.aylik * 12)) * 100)) : 0;
            return '<div class="pk-kart' + (p.aktif ? '' : ' pasif') + (p.tam ? ' tam' : '') + '" style="--c:' + esc(p.renk) + '" data-id="' + p.id + '"><div class="pk-ust"><h3>' + (p.tam ? '<i class="fa-solid fa-crown"></i> ' : '') + esc(p.ad) + '</h3>' + (p.aktif ? '' : '<span class="pk-pasif">Pasif</span>') + '</div>' +
                '<p class="pk-ac">' + esc(p.aciklama) + '</p><div class="pk-chips">' + p.moduller.map(modulChip).join('') + '</div>' +
                '<div class="pk-fiyat">' + ((p.aylik > 0 || p.yillik > 0) ? '<div><small>Aylık</small><b>' + (p.aylik > 0 ? fiyat(p.aylik, p.para) : '—') + '</b></div><div><small>Yıllık</small><b>' + (p.yillik > 0 ? fiyat(p.yillik, p.para) : '—') + '</b>' + (av ? '<em>%' + av + ' avantaj</em>' : '') + '</div>' : '<div class="pk-yok"><i class="fa-solid fa-tag"></i> Fiyat belirlenmedi</div>') + '</div>' +
                '<div class="pk-alt"><span><i class="fa-solid fa-building"></i> ' + p.kurum_say + ' kurumda aktif</span><span class="ikd-bosluk"></span><button type="button" class="ikm-btn ikm-btn-kucuk" data-akt="' + p.id + '" title="Paketi satışa aç/kapat"><i class="fa-solid ' + (p.aktif ? 'fa-eye-slash' : 'fa-eye') + '"></i> ' + (p.aktif ? 'Pasifleştir' : 'Aktifleştir') + '</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-d="' + p.id + '"><i class="fa-solid fa-pen"></i> Düzenle</button></div></div>';
        }).join('');
        var talep = j.talepler.length ? '<div class="pk-tbl">' + j.talepler.map(function (t) {
            return '<div class="pk-talep' + (t.durum === 'yeni' ? ' yeni' : '') + '"><div><b>' + esc(t.kurum || '—') + '</b> · ' + esc(t.paket || 'Genel bilgi') + ' <small>(' + (t.donem === 'yillik' ? 'yıllık' : 'aylık') + ')</small><br><small>' + esc(t.kisi) + ' · ' + X.tarih(t.olusturma) + (t.mesaj ? ' · ' + esc(t.mesaj) : '') + '</small></div>' +
                (t.durum === 'yeni' ? '<button type="button" class="ikm-btn ikm-btn-kucuk" data-t="' + t.id + '"><i class="fa-solid fa-check"></i> İşlendi</button>' : '<span class="ikm-soluk kucuk">İşlendi</span>') + '</div>';
        }).join('') + '</div>' : '<p class="ikm-soluk">Henüz bilgi talebi yok. Kurumlar “Bilgi al” dediğinde burada görünür.</p>';
        icerik.innerHTML = '<div class="pk-izgara">' + kartlar + '</div><h3 class="pk-bas"><i class="fa-solid fa-inbox"></i> Bilgi talepleri' + (j.talepler.filter(function (t) { return t.durum === 'yeni'; }).length ? ' <span class="pk-say">' + j.talepler.filter(function (t) { return t.durum === 'yeni'; }).length + ' yeni</span>' : '') + '</h3>' + talep;
        $$('[data-d]', icerik).forEach(function (b) { b.onclick = function () { paketFormu(j.paketler.filter(function (p) { return String(p.id) === b.dataset.d; })[0], j); }; });
        $$('[data-akt]', icerik).forEach(function (b) { b.onclick = function () { var p = j.paketler.filter(function (x) { return String(x.id) === b.dataset.akt; })[0]; gonder('paket_kaydet', { id: p.id, ad: p.ad, aciklama: p.aciklama, aylik: p.aylik, yillik: p.yillik, para: p.para, renk: p.renk, moduller: p.moduller.join(','), tam: p.tam, aktif: p.aktif ? 0 : 1 }).then(function () { bildir(p.aktif ? 'Paket pasifleştirildi.' : 'Paket aktifleştirildi.', 'basari'); X.git('paketler', true); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
        $$('[data-t]', icerik).forEach(function (b) { b.onclick = function () { gonder('paket_talep_durum', { id: b.dataset.t, durum: 'islendi' }).then(function () { X.git('paketler', true); }); }; });
    }
    function paketFormu(p, j) {
        var yeni = !p; p = p || { ad: '', aciklama: '', renk: '#6366f1', para: 'TRY', aylik: 0, yillik: 0, moduller: [], aktif: 1, tam: 0 };
        var kodlar = Object.keys(KAT), sec = {}; p.moduller.forEach(function (k) { sec[k] = 1; });
        var m = modal({ baslik: yeni ? 'Yeni paket' : 'Paketi düzenle', ikon: 'fa-cube', gen: 600, sabit: true, govde:
            '<div class="ikm-form"><label class="ikm-alan"><span>Paket adı <b>*</b></span><input id="pf-ad" maxlength="120" value="' + esc(p.ad) + '"></label><label class="ikm-alan"><span>Açıklama</span><textarea id="pf-ac" rows="2" maxlength="500">' + esc(p.aciklama) + '</textarea></label>' +
            '<div class="ikm-iki"><label class="ikm-alan"><span>Aylık fiyat</span><input id="pf-ay" type="number" min="0" step="0.01" value="' + p.aylik + '"></label><label class="ikm-alan"><span>Yıllık fiyat</span><input id="pf-yi" type="number" min="0" step="0.01" value="' + p.yillik + '"></label></div>' +
            '<div class="ikm-iki"><label class="ikm-alan"><span>Para birimi</span><select id="pf-para">' + ['TRY', 'USD', 'EUR'].map(function (x) { return '<option value="' + x + '"' + (x === p.para ? ' selected' : '') + '>' + x + ' ' + PARA[x] + '</option>'; }).join('') + '</select></label><label class="ikm-alan"><span>Renk</span><input id="pf-renk" type="color" value="' + esc(p.renk) + '"></label></div>' +
            '<small class="ikm-soluk" id="pf-avantaj"></small>' +
            '<div class="ikm-alan"><span>İçerdiği modüller <button type="button" class="ikm-btn ikm-btn-kucuk" id="pf-hepsi" style="margin-left:8px">Tümünü seç</button></span><div class="pk-modul-liste">' + kodlar.map(function (k) { return '<label class="pk-modul"><input type="checkbox" value="' + k + '"' + (sec[k] || p.tam ? ' checked' : '') + (p.tam ? ' disabled' : '') + '><span style="--c:' + KAT[k].renk + '"><i class="fa-solid ' + KAT[k].ikon + '"></i><b>' + esc(KAT[k].ad) + '</b><small>' + esc(KAT[k].aciklama) + '</small></span></label>'; }).join('') + '</div></div>' +
            '<label class="ikm-onay-satir"><input type="checkbox" id="pf-akt"' + (p.aktif ? ' checked' : '') + '> <span>Aktif (kurumlara satışa açık)</span></label></div>',
            alt: (yeni || p.tam ? '' : '<button type="button" class="ikm-btn ikm-btn-tehlike" id="pf-sil"><i class="fa-solid fa-trash"></i> Sil</button>') + '<span class="ikd-bosluk"></span><button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="pf-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
        function avantaj() { var a = +$('#pf-ay', m.el).value, y = +$('#pf-yi', m.el).value; $('#pf-avantaj', m.el).textContent = a > 0 && y > 0 ? 'Yıllık ödeme, 12 aylık ödemeye göre %' + Math.max(0, Math.round((1 - y / (a * 12)) * 100)) + ' avantajlı görünür.' : 'Yıllık fiyat boş bırakılırsa 12 × aylık hesaplanır.'; }
        $('#pf-ay', m.el).oninput = $('#pf-yi', m.el).oninput = avantaj; avantaj();
        $('#pf-hepsi', m.el).onclick = function () { $$('.pk-modul input', m.el).forEach(function (c) { c.checked = true; }); };
        if ($('#pf-sil', m.el)) $('#pf-sil', m.el).onclick = function () { onayla({ baslik: 'Paket silinsin mi?', mesaj: 'Kurumlara atanmış süreler de kalkar.', evet: 'Sil', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('paket_sil', { id: p.id }).then(function () { m.kapat(); X.git('paketler', true); }); }); };
        $('#pf-kaydet', m.el).onclick = function () {
            var mod = $$('.pk-modul input:checked', m.el).map(function (c) { return c.value; });
            if (!mod.length) { bildir('En az bir modül seçin.', 'uyari'); return; }
            gonder('paket_kaydet', { id: p.id || 0, ad: $('#pf-ad', m.el).value, aciklama: $('#pf-ac', m.el).value, aylik: $('#pf-ay', m.el).value, yillik: $('#pf-yi', m.el).value, para: $('#pf-para', m.el).value, renk: $('#pf-renk', m.el).value, moduller: mod.join(','), tam: p.tam ? 1 : 0, aktif: $('#pf-akt', m.el).checked ? 1 : 0 }).then(function () { m.kapat(); bildir('Paket kaydedildi.', 'basari'); X.git('paketler', true); }).catch(function (e) { bildir(e.message, 'hata'); });
        };
    }

    /* ---------------- Kurum: paket atama + ayar + indirim ---------------- */
    function kurumPaketi(k) {
        getir({ islem: 'kurum_paket', kurum_id: k.id }).then(function (j) {
            if (!Object.keys(KAT).length) return getir({ islem: 'paket_liste' }).then(function (x) { KAT = x.katalog; ac(k, j); });
            ac(k, j);
        }).catch(function (e) { bildir(e.message, 'hata'); });
    }
    function ac(k, j) {
        var K = j.kurum, secili = {};
        var m = modal({ baslik: 'Paketler — ' + esc(K.ad), ikon: 'fa-cube', gen: 760, sabit: true, govde: '<div class="pk-sekme"><button type="button" class="aktif" data-sk="a">Paket ata / uzat</button><button type="button" data-sk="b">Ayarlar ve indirim</button></div><div id="pk-g"></div>', alt: '<button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Kapat</button>' });
        var sk = 'a';
        function bas() { var p = j.paketler[0]; return p ? p.para : 'TRY'; }
        function ciz() {
            var g = $('#pk-g', m.el);
            if (sk === 'a') {
                g.innerHTML = '<div class="pk-ata-ust"><button type="button" class="ikm-btn ikm-btn-kucuk" id="pk-hepsi"><i class="fa-solid fa-check-double"></i> Tümünü seç</button><button type="button" class="ikm-btn ikm-btn-kucuk" id="pk-tam"><i class="fa-solid fa-crown"></i> Tam paketi seç</button><button type="button" class="ikm-btn ikm-btn-kucuk" id="pk-temiz">Temizle</button></div>' +
                    '<div class="pk-ata-liste">' + j.paketler.filter(function (p) { return p.aktif || p.bit; }).map(function (p) {
                        var d = p.bit ? (p.kalan >= 0 ? '<em class="ok">' + p.kalan + ' gün kaldı</em> · ' + X.tarih(p.bit) : '<em class="bitti">Süresi bitti</em> · ' + X.tarih(p.bit)) : '<em class="yok">Yok</em>';
                        return '<label class="pk-ata" style="--c:' + esc(p.renk) + '"><input type="checkbox" value="' + p.id + '"' + (secili[p.id] ? ' checked' : '') + '><span><b>' + (p.tam ? '<i class="fa-solid fa-crown"></i> ' : '') + esc(p.ad) + '</b><small>' + d + '</small></span>' + (p.atama_id ? '<button type="button" class="ikm-ikon-btn" data-sil="' + p.atama_id + '" title="Atamayı kaldır"><i class="fa-solid fa-xmark"></i></button>' : '') + '</label>';
                    }).join('') + '</div>' +
                    '<div class="pk-donem"><div class="ikm-segment" id="pk-tur">' + [['aylik', 'Aylık (+1 ay)'], ['yillik', 'Yıllık (+1 yıl)'], ['gun', 'Gün sayısı'], ['tarih', 'Bitiş tarihi']].map(function (x, i) { return '<button type="button" data-tur="' + x[0] + '" class="' + (i === 0 ? 'aktif' : '') + '">' + x[1] + '</button>'; }).join('') + '</div>' +
                    '<input type="number" id="pk-gun" min="1" max="3650" value="30" hidden placeholder="Gün"><input type="date" id="pk-tarih" hidden></div>' +
                    '<label class="ikm-onay-satir"><input type="checkbox" id="pk-modu"' + (K.paket_modu ? '' : ' checked') + '> <span>Paket modunu aç <small class="ikm-soluk">(açıkken kurum yalnızca aldığı modülleri kullanır, diğerleri kilitlenir)</small></span></label>' +
                    '<p class="ikm-soluk kucuk">Süresi devam eden pakete ekleme yapılırsa süre bitiş tarihinden uzatılır; bittiyse bugünden başlar.</p>' +
                    '<div style="text-align:right"><button type="button" class="ikm-btn ikm-btn-ana" id="pk-ata"><i class="fa-solid fa-plus"></i> Seçilenlere ekle</button></div>';
                var tur = 'aylik';
                $$('.pk-ata input', g).forEach(function (c) { c.onchange = function () { secili[c.value] = c.checked; }; });
                $('#pk-hepsi', g).onclick = function () { j.paketler.forEach(function (p) { if (p.aktif) secili[p.id] = true; }); ciz(); };
                $('#pk-tam', g).onclick = function () { secili = {}; j.paketler.forEach(function (p) { if (p.tam && p.aktif) secili[p.id] = true; }); ciz(); };
                $('#pk-temiz', g).onclick = function () { secili = {}; ciz(); };
                $$('[data-tur]', g).forEach(function (b) { b.onclick = function () { tur = b.dataset.tur; $$('[data-tur]', g).forEach(function (x) { x.classList.toggle('aktif', x === b); }); $('#pk-gun', g).hidden = tur !== 'gun'; $('#pk-tarih', g).hidden = tur !== 'tarih'; }; });
                $$('[data-sil]', g).forEach(function (b) { b.onclick = function (e) { e.preventDefault(); onayla({ baslik: 'Atama kaldırılsın mı?', evet: 'Kaldır', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('kurum_paket_sil', { id: b.dataset.sil }).then(yenile); }); }; });
                $('#pk-ata', g).onclick = function () {
                    var ids = Object.keys(secili).filter(function (x) { return secili[x]; }); if (!ids.length) { bildir('Paket seçin.', 'uyari'); return; }
                    gonder('kurum_paket_ata', { kurum_id: K.id, paket_ids: ids.join(','), tur: tur, gun: $('#pk-gun', g).value, tarih: $('#pk-tarih', g).value, modu_ac: $('#pk-modu', g).checked ? 1 : 0 }).then(function () { bildir('Paketler eklendi.', 'basari'); secili = {}; yenile(); }).catch(function (e) { bildir(e.message, 'hata'); });
                };
            } else {
                var ind = function (ad, tip, deg) { return '<div class="pk-ind"><b>' + ad + '</b><div class="pk-ind-satir"><select id="in-' + ad[0] + 't"><option value="yuzde"' + (tip === 'yuzde' ? ' selected' : '') + '>Yüzde (%)</option><option value="tl"' + (tip === 'tl' ? ' selected' : '') + '>Tutar (paket başına)</option></select><input type="number" id="in-' + ad[0] + 'd" min="0" step="0.01" value="' + deg + '"></div></div>'; };
                var gizli = String(K.pk_gizli || '').split(',');
                function sw(id, on, ad, alt) { return '<label class="ikm-anahtar-satir"><span><b>' + ad + '</b><small>' + alt + '</small></span><span class="ikm-anahtar"><input type="checkbox" id="' + id + '"' + (on ? ' checked' : '') + '><i></i></span></label>'; }
                g.innerHTML = '<div class="pk-ayar">' + sw('ay-modu', K.paket_modu, 'Paket modu', 'Açık: kurum sadece aldığı modülleri kullanır. Kapalı: tüm modüller serbest.') + sw('ay-gor', K.paket_gorunur, '“Paketlerim” sayfası görünsün', 'Kurum yetkilisi kalan süreyi ve bitiş tarihini görür.') + sw('ay-vit', K.vitrin_gorunur, 'Paket vitrini görünsün', 'Kilitli modüller menüde kilitli görünür; fiyatlar ve “Bilgi al” açılır.') + sw('ay-bil', K.pk_bilgi, 'Paket kartlarında “i” bilgi düğmesi', 'Paketin ne işe yaradığını ve modül açıklamalarını gösterir.') + sw('ay-sur', K.pk_sure, 'Kalan süre görünsün', 'Paketlerim’de kalan gün ve bitiş tarihi çubuğu.') + sw('ay-ban', K.pk_banner, 'İndirim bildirimi görünsün', 'İndirim tanımlıysa ana sayfada “indirim sizi bekliyor” bildirimi.') + '</div>' +
                    '<div class="pk-ind-kap"><div class="ikm-alt-baslik"><i class="fa-solid fa-eye"></i> Bu kurum hangi paketleri görsün? <small>(sahip olduğu paketler her zaman görünür)</small></div><div class="pk-gor-liste">' + j.paketler.map(function (p) { return '<label class="pk-gor"><input type="checkbox" value="' + p.id + '"' + (gizli.indexOf(String(p.id)) < 0 ? ' checked' : '') + '><span>' + esc(p.ad) + (p.aktif ? '' : ' <small>(pasif)</small>') + '</span></label>'; }).join('') + '</div></div>' +
                    '<div class="pk-ind-kap"><div class="ikm-alt-baslik"><i class="fa-solid fa-percent"></i> Bu kuruma özel indirim <small>(tüm paketlere uygulanır)</small></div>' + ind('Aylık', K.ind_aylik_tip, K.ind_aylik) + ind('Yıllık', K.ind_yillik_tip, K.ind_yillik) + '</div>' +
                    '<div class="pk-onizle" id="pk-onizle"></div><p class="ikm-soluk kucuk"><i class="fa-solid fa-bolt"></i> Değişiklikler anında kaydedilir.</p>';
                function hesap(p, don) {
                    var liste = don === 'yillik' ? (p.yillik.liste) : p.aylik.liste, tip = $('#in-' + (don === 'yillik' ? 'Y' : 'A') + 't', g).value, deg = +$('#in-' + (don === 'yillik' ? 'Y' : 'A') + 'd', g).value || 0;
                    var i = deg <= 0 ? 0 : (tip === 'tl' ? Math.min(liste, deg) : liste * Math.min(100, deg) / 100); return [liste, liste - i];
                }
                function onizle() {
                    var l = j.paketler.filter(function (p) { return p.aktif && !(p.aylik.liste <= 0 && p.yillik.liste <= 0); });
                    $('#pk-onizle', g).innerHTML = l.length ? '<div class="pk-on-baslik">Bu kuruma görünecek net fiyatlar</div>' + l.map(function (p) { var a = hesap(p, 'aylik'), y = hesap(p, 'yillik'); return '<div class="pk-on-satir"><span>' + esc(p.ad) + '</span><span>' + (a[0] !== a[1] ? '<s>' + fiyat(a[0], p.para) + '</s> ' : '') + '<b>' + fiyat(a[1], p.para) + '</b>/ay</span><span>' + (y[0] !== y[1] ? '<s>' + fiyat(y[0], p.para) + '</s> ' : '') + '<b>' + fiyat(y[1], p.para) + '</b>/yıl</span></div>'; }).join('') : '<p class="ikm-soluk kucuk">Paketlere fiyat girildiğinde net fiyat önizlemesi burada görünür.</p>';
                }
                $$('input,select', g).forEach(function (e) { e.oninput = e.onchange = onizle; }); onizle();
                var zm = null;
                function kaydet() {
                    var v = { kurum_id: K.id, paket_modu: $('#ay-modu', g).checked ? 1 : 0, paket_gorunur: $('#ay-gor', g).checked ? 1 : 0, vitrin_gorunur: $('#ay-vit', g).checked ? 1 : 0, ind_aylik_tip: $('#in-At', g).value, ind_aylik: $('#in-Ad', g).value, ind_yillik_tip: $('#in-Yt', g).value, ind_yillik: $('#in-Yd', g).value, pk_bilgi: $('#ay-bil', g).checked ? 1 : 0, pk_sure: $('#ay-sur', g).checked ? 1 : 0, pk_banner: $('#ay-ban', g).checked ? 1 : 0, pk_gizli: $$('.pk-gor input', g).filter(function (c) { return !c.checked; }).map(function (c) { return c.value; }).join(',') };
                    gonder('kurum_paket_ayar', v).then(function () {
                        K.paket_modu = v.paket_modu; K.paket_gorunur = v.paket_gorunur; K.vitrin_gorunur = v.vitrin_gorunur; K.ind_aylik_tip = v.ind_aylik_tip; K.ind_aylik = v.ind_aylik; K.ind_yillik_tip = v.ind_yillik_tip; K.ind_yillik = v.ind_yillik; K.pk_bilgi = v.pk_bilgi; K.pk_sure = v.pk_sure; K.pk_banner = v.pk_banner; K.pk_gizli = v.pk_gizli;
                        bildir('Kaydedildi.', 'basari');
                    }).catch(function (e) { bildir(e.message, 'hata'); });
                }
                $$('input,select', g).forEach(function (e) { var eski = e.oninput; e.addEventListener('change', function () { clearTimeout(zm); zm = setTimeout(kaydet, 350); }); if (e.type === 'number') e.addEventListener('input', function () { clearTimeout(zm); zm = setTimeout(kaydet, 900); }); });
            }
        }
        function yenile() { getir({ islem: 'kurum_paket', kurum_id: K.id }).then(function (x) { j = x; K = x.kurum; ciz(); }); }
        $$('[data-sk]', m.el).forEach(function (b) { b.onclick = function () { sk = b.dataset.sk; $$('[data-sk]', m.el).forEach(function (x) { x.classList.toggle('aktif', x === b); }); ciz(); }; });
        ciz();
    }
    window.PFP = { kurumPaketi: kurumPaketi };
    if ((location.hash || '') === '#paketler') X.git('paketler');
})();
