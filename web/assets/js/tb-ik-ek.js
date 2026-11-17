/* ============================================================
 * tb-ik-ek.js — İK ek özellikleri: işlem logları (kişi geçmişi + genel sayfa), ajanda (takvim notları + hatırlatma),
 *               görünüm (menü aç/kapa, sade tema, favicon), fotoğraf büyütme, panel ek kutuları.
 * ============================================================ */
(function () {
    'use strict';
    var M = window.IKM; if (!M) return;
    var $ = M.$, $$ = M.$$, esc = M.esc, getir = M.getir, gonder = M.gonder, bildir = M.bildir, modal = M.modal;
    var AYLAR = M.aylar, GUN = ['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'];
    function z2(n) { return (n < 10 ? '0' : '') + n; }
    function ymd(d) { return d.getFullYear() + '-' + z2(d.getMonth() + 1) + '-' + z2(d.getDate()); }

    /* ---------- Fotoğraf büyütme (liste kartı ve personel kartı) ---------- */
    document.addEventListener('click', function (e) {
        var im = e.target.closest('.ikm-kisi .ikm-av img, .ikm-avatar-kap .ikm-av img, .ikm-satir .ikm-av img, .ikm-amir .ikm-av img');
        if (!im || e.target.closest('.ikm-avatar-yukle, .ikm-avatar-kaldir')) return;
        e.stopPropagation(); e.preventDefault();
        var amir = im.closest('[data-ad]'), ad = amir ? { textContent: amir.dataset.ad } : (im.closest('.ikm-kisi, .ikm-c-kimlik, .ikm-satir') || document).querySelector('b, h2');
        var arka = document.createElement('div'); arka.className = 'ikm-foto-arka';
        arka.innerHTML = '<figure><img src="' + esc(im.getAttribute('src')) + '" alt=""><figcaption>' + esc(ad ? ad.textContent : '') + '</figcaption><button type="button" class="ikm-ikon-btn" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button></figure>';
        function kapat() { arka.remove(); document.removeEventListener('keydown', tus, true); }
        function tus(ev) { if (ev.key === 'Escape') { ev.stopImmediatePropagation(); ev.preventDefault(); kapat(); } }
        arka.addEventListener('click', function (ev) { ev.stopPropagation(); kapat(); }); document.addEventListener('keydown', tus, true); document.body.appendChild(arka);
    }, true);

    /* ---------- İşlem logları ---------- */
    var KAT_RENK = { Personel: '#6366f1', İzin: '#10b981', Belge: '#0ea5e9', Zimmet: '#f59e0b', Puantaj: '#14b8a6', Masraf: '#ef4444', Avans: '#f97316', Birim: '#8b5cf6', Eğitim: '#06b6d4', Performans: '#eab308', Bordro: '#22c55e', Ayar: '#64748b', Ajanda: '#f59e0b', Duyuru: '#f43f5e', Dosya: '#0ea5e9', Anket: '#8b5cf6', 'Giriş-çıkış': '#06b6d4', 'İşe alım': '#0ea5e9' };
    function kd(t) { return M.tarihSaat(t) + ' · ' + String(t).slice(0, 4); }
    function logSatir(r, kisiBazli) {
        var c = KAT_RENK[r.kategori] || '#64748b';
        return '<div class="ikm-log-satir"><span class="ikm-log-nokta" style="background:' + c + '"></span><div class="ikm-log-ic"><div><span class="ikm-cip" style="--c:' + c + '">' + esc(r.kategori) + '</span> <b>' + esc(r.aciklama) + '</b></div>' +
            '<small>' + esc(r.yapan) + (r.hedef && !kisiBazli ? ' → ' + esc(r.hedef) : '') + (r.hedef && kisiBazli && r.hedef_id !== r.yapan_id ? ' · ilgili: ' + esc(r.hedef) : '') + ' · ' + esc(kd(r.tarih)) + (r.ip ? ' · ' + esc(r.ip) : '') + '</small></div></div>';
    }
    function sayfalama(j, git) {
        var top = Math.max(1, Math.ceil(j.toplam / j.adet));
        return '<div class="ikm-sayfa"><button type="button" class="ikm-btn ikm-btn-kucuk" data-p="-1"' + (j.sayfa <= 1 ? ' disabled' : '') + '><i class="fa-solid fa-chevron-left"></i></button><span>' + j.sayfa + ' / ' + top + ' · ' + j.toplam + ' kayıt</span><button type="button" class="ikm-btn ikm-btn-kucuk" data-p="1"' + (j.sayfa >= top ? ' disabled' : '') + '><i class="fa-solid fa-chevron-right"></i></button></div>';
    }
    M.kisiLog = function (kok, id) {
        var f = { sayfa: 1 };
        function yukle() {
            getir({ islem: 'personel_log', id: id, sayfa: f.sayfa, kategori: f.kat || '', ara: f.ara || '' }).then(function (j) {
                kok.innerHTML = '<div class="ikm-arac"><input type="search" placeholder="Geçmişte ara…" value="' + esc(f.ara || '') + '" data-a><select data-k><option value="">Tüm işlemler</option>' + j.kategoriler.map(function (k) { return '<option' + (k === f.kat ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('') + '</select></div>' +
                    (j.liste.length ? '<div class="ikm-log-liste">' + j.liste.map(function (r) { return logSatir(r, true); }).join('') + '</div>' + sayfalama(j) : M.bosKutu('fa-clock-rotate-left', 'İşlem geçmişi yok', 'Bu kişiyle ilgili işlemler burada listelenir.'));
                var a = $('[data-a]', kok), t; a.oninput = function () { clearTimeout(t); t = setTimeout(function () { f.ara = a.value.trim(); f.sayfa = 1; yukle(); }, 350); };
                $('[data-k]', kok).onchange = function () { f.kat = this.value; f.sayfa = 1; yukle(); };
                $$('[data-p]', kok).forEach(function (b) { b.onclick = function () { f.sayfa += +b.dataset.p; yukle(); }; });
                if (document.activeElement === document.body && f.ara) { a.focus(); a.setSelectionRange(a.value.length, a.value.length); }
            }).catch(function (e) { kok.innerHTML = M.hataKutu(e.message); });
        }
        yukle();
    };
    var LF = { sayfa: 1, ara: '', alan: '', kisi: '', kat: '', bas: '', bit: '' };
    function loglarCiz() {
        var ic = M.icerik; ic.innerHTML = M.yukleniyor();
        getir({ islem: 'loglar', sayfa: LF.sayfa, ara: LF.ara, alan: LF.alan, kisi: LF.kisi, kategori: LF.kat, bas: LF.bas, bit: LF.bit }).then(function (j) {
            var kisiler = (M.veri() && M.veri().kisiler) || [];
            ic.innerHTML = '<section class="ikm-kart"><div class="ikm-arac ikm-log-filtre">' +
                '<select id="lg-alan" title="Hangi alanda aranacak">' + [['', 'Her yerde ara'], ['aciklama', 'Açıklama (duyuru adı vb.)'], ['yapan', 'İşlemi yapan'], ['hedef', 'Etkilenen kişi'], ['kategori', 'Bölüm'], ['islem', 'İşlem kodu'], ['ip', 'IP adresi']].map(function (x) { return '<option value="' + x[0] + '"' + (x[0] === LF.alan ? ' selected' : '') + '>' + x[1] + '</option>'; }).join('') + '</select>' +
                '<input type="search" id="lg-ara" placeholder="Ara…" value="' + esc(LF.ara) + '">' +
                '<select id="lg-kisi"><option value="">Tüm kişiler</option>' + kisiler.map(function (k) { return '<option value="' + k.id + '"' + (String(k.id) === String(LF.kisi) ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' +
                '<select id="lg-kat"><option value="">Tüm işlem türleri</option>' + j.kategoriler.map(function (k) { return '<option' + (k === LF.kat ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('') + '</select>' +
                '<input type="date" id="lg-bas" value="' + esc(LF.bas) + '" title="Başlangıç"><input type="date" id="lg-bit" value="' + esc(LF.bit) + '" title="Bitiş">' +
                '<button type="button" class="ikm-btn" id="lg-temizle"><i class="fa-solid fa-filter-circle-xmark"></i> Temizle</button></div>' +
                (j.liste.length ? '<div class="ikm-log-liste">' + j.liste.map(function (r) { return logSatir(r, false); }).join('') + '</div>' + sayfalama(j) : M.bosKutu('fa-clock-rotate-left', 'Kayıt bulunamadı', 'Filtreleri değiştirin.')) + '</section>';
            var t; $('#lg-ara').oninput = function () { var v = this.value.trim(); clearTimeout(t); t = setTimeout(function () { LF.ara = v; LF.sayfa = 1; loglarCiz(); }, 400); };
            [['lg-alan', 'alan'], ['lg-kisi', 'kisi'], ['lg-kat', 'kat'], ['lg-bas', 'bas'], ['lg-bit', 'bit']].forEach(function (x) { $('#' + x[0]).onchange = function () { LF[x[1]] = this.value; LF.sayfa = 1; loglarCiz(); }; });
            $('#lg-temizle').onclick = function () { LF = { sayfa: 1, ara: '', alan: '', kisi: '', kat: '', bas: '', bit: '' }; loglarCiz(); };
            $$('[data-p]', ic).forEach(function (b) { b.onclick = function () { LF.sayfa += +b.dataset.p; loglarCiz(); window.scrollTo({ top: 0 }); }; });
            var a = $('#lg-ara'); if (LF.ara) { a.focus(); a.setSelectionRange(a.value.length, a.value.length); }
        }).catch(function (e) { ic.innerHTML = M.hataKutu(e.message); });
    }
    M.kayit('loglar', 'İşlem logları', 'Kim, neyi, ne zaman değiştirdi — arama ve filtrelerle.', loglarCiz);

    /* ---------- Görünüm ve menü ---------- */
    function gorunumCiz() {
        var ic = M.icerik; ic.innerHTML = M.yukleniyor();
        getir({ islem: 'gorunum_getir' }).then(function (j) {
            var gruplar = {}; Object.keys(j.katalog).forEach(function (k) { var g = j.katalog[k][1]; (gruplar[g] = gruplar[g] || []).push(k); });
            var kapali = j.kapali.slice();
            ic.innerHTML = '<section class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-list-check"></i> Menüde görünecek bölümler</h3></div>' +
                '<p class="ikm-soluk">Kullanmadığınız bölümleri kapatın; menüden kalkar. Verileriniz silinmez, tekrar açınca geri gelir. “Görünüm ve menü” sayfası kapatılamaz.</p>' +
                Object.keys(gruplar).map(function (g) {
                    return '<div class="ikm-alt-baslik gor-baslik">' + esc(g) + '<span class="gor-hizli"><button type="button" data-hepsi="1" data-g="' + esc(g) + '">Tümünü aç</button><button type="button" data-hepsi="0" data-g="' + esc(g) + '">Tümünü kapat</button></span></div><div class="ikm-gorunum-liste gor-kompakt" data-grup="' + esc(g) + '">' + gruplar[g].map(function (k) {
                        return '<label class="gor-satir"><span>' + esc(j.katalog[k][0]) + '</span><span class="ikm-anahtar mini"><input type="checkbox" data-m="' + k + '"' + (kapali.indexOf(k) < 0 ? ' checked' : '') + '><i></i></span></label>';
                    }).join('') + '</div>';
                }).join('') + '</section>' +
                '<section class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-palette"></i> Tema</h3></div>' +
                '<label class="ikm-anahtar"><input type="checkbox" id="g-sade"' + (j.sade ? ' checked' : '') + '><i></i><span><b>Sade tema</b> — gradyan ve gölgeler kaldırılır, düz ve sakin görünüm</span></label></section>' +
                '<section class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-folder-closed"></i> Özlük evrakı ve zimmet görünürlüğü</h3></div>' +
                '<p class="ikm-soluk">Evrak ekleme/silme her zaman yalnızca özlük yetkililerindedir. Burada kişilerin <b>kendi</b> evrak ve zimmetlerini görüp göremeyeceğini seçersiniz.</p>' +
                '<label class="ikm-anahtar"><input type="checkbox" id="g-kevrak"' + (j.kendi_evrak ? ' checked' : '') + '><i></i><span><b>Herkes kendi özlük evrakını ve zimmetini görebilsin</b></span></label>' +
                '<div id="g-ist" ' + (j.kendi_evrak ? 'hidden' : '') + '><div class="ikm-alt-baslik">Kapalıyken yine de görebilecek kişiler</div><input type="search" class="ikm-ks-ara" id="g-ist-ara" placeholder="Kişi ara…"><div class="ikm-ist-liste" id="g-ist-l"></div></div></section>' +
                '<section class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-regular fa-star"></i> Favicon (sekme simgesi)</h3></div>' +
                '<div class="ikm-fav"><span class="ikm-fav-onizle">' + (j.favicon ? '<img src="' + esc(j.favicon) + '?v=' + Date.now() + '" alt="">' : '<i class="fa-regular fa-image"></i>') + '</span><div class="ikm-fav-btn">' +
                '<label class="ikm-btn"><i class="fa-solid fa-upload"></i> ' + (j.favicon ? 'Değiştir' : 'Yükle') + '<input type="file" id="g-fav" accept="image/png,image/jpeg,image/webp,image/gif,image/x-icon,.ico" hidden></label>' +
                (j.favicon ? '<button type="button" class="ikm-btn ikm-btn-tehlike" id="g-fav-sil"><i class="fa-solid fa-trash"></i> Kaldır</button>' : '') + '</div><small class="ikm-soluk">PNG/ICO/JPG, en fazla 512 KB. Kare bir görsel önerilir.</small></div></section>' +
                '<p class="ikm-soluk kucuk"><i class="fa-solid fa-bolt"></i> Değişiklikler anında kaydedilir.</p>';
            $$('[data-hepsi]', ic).forEach(function (b) { b.onclick = function () { $$('[data-grup="' + b.dataset.g + '"] [data-m]', ic).forEach(function (c) { c.checked = b.dataset.hepsi === '1'; }); }; });
            var ist = (j.evrak_istisna || []).slice();
            function istCiz() { var a = ($('#g-ist-ara').value || '').toLocaleLowerCase('tr-TR'); $('#g-ist-l').innerHTML = j.kisiler.filter(function (k) { return !a || k.ad.toLocaleLowerCase('tr-TR').indexOf(a) >= 0; }).slice(0, 100).map(function (k) { return '<label class="ikm-onay-satir"><input type="checkbox" value="' + k.id + '"' + (ist.indexOf(k.id) >= 0 ? ' checked' : '') + '> <span>' + esc(k.ad) + '</span></label>'; }).join(''); }
            $('#g-ist-ara').oninput = istCiz; istCiz();
            $('#g-ist-l').onchange = function (e) { var v = +e.target.value, i = ist.indexOf(v); if (e.target.checked && i < 0) ist.push(v); if (!e.target.checked && i >= 0) ist.splice(i, 1); };
            $('#g-kevrak').onchange = function () { $('#g-ist').hidden = this.checked; };
            var zamanla = null;
            function menuYenile() {
                fetch(location.href, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) { return r.text(); }).then(function (h) {
                    var d = new DOMParser().parseFromString(h, 'text/html'), yeni = d.getElementById('ik-sekmeler'), eski = document.getElementById('ik-sekmeler'); if (!yeni || !eski) return;
                    eski.innerHTML = yeni.innerHTML; $$('.ikm-nav[data-s]', eski).forEach(function (n) { n.dataset.yeni = '1'; n.classList.toggle('aktif', n.dataset.s === 'gorunum'); });
                }).catch(function () {});
            }
            if (!window.__gorMenuDeleg) { window.__gorMenuDeleg = true; document.addEventListener('click', function (e) { var n = e.target.closest && e.target.closest('#ik-sekmeler .ikm-nav[data-yeni]'); if (!n) return; var kok = document.getElementById('ik-kok'); if (kok) kok.classList.remove('menu-acik'); if (n.dataset.s === 'benimikm') { if (M.veri() && M.veri().ben) M.kartAc(M.veri().ben); return; } M.sekmeAc(n.dataset.s); }); }
            function kaydet() {
                var k = $$('[data-m]', ic).filter(function (c) { return !c.checked; }).map(function (c) { return c.dataset.m; });
                gonder('gorunum_kaydet', { kapali: k.join(','), sade: $('#g-sade').checked ? 1 : 0, kendi_evrak: $('#g-kevrak').checked ? 1 : 0, evrak_istisna: ist.join(',') }).then(function () {
                    if (M.veri()) M.veri().menu_kapali = k; var kok = document.getElementById('ik-kok'); if (kok) kok.classList.toggle('ikm-sade', $('#g-sade').checked);
                    bildir('Kaydedildi.', 'basari'); menuYenile();
                }).catch(function (e) { bildir(e.message, 'hata'); });
            }
            function otoKaydet() { clearTimeout(zamanla); zamanla = setTimeout(kaydet, 450); }
            ic.addEventListener('change', function (e) { if (e.target.matches('[data-m], #g-sade, #g-kevrak, #g-ist-l input')) otoKaydet(); });
            $('#g-fav').onchange = function () { if (this.files[0]) gonder('favicon_yukle', {}, this.files[0]).then(function () { bildir('Favicon yüklendi.', 'basari'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { bildir(e.message, 'hata'); }); };
            var s = $('#g-fav-sil'); if (s) s.onclick = function () { gonder('favicon_sil', {}).then(function () { bildir('Favicon kaldırıldı.', 'basari'); setTimeout(function () { location.reload(); }, 500); }).catch(function (e) { bildir(e.message, 'hata'); }); };
        }).catch(function (e) { ic.innerHTML = M.hataKutu(e.message); });
    }
    M.kayit('gorunum', 'Görünüm ve menü', 'Menüyü sadeleştirin, tema ve favicon seçin.', gorunumCiz);

    /* ---------- Ana panel: ek kutular (ajanda, son işlemler, birim halkası) ---------- */
    var eskiEk = M.genelEk;
    M.genelEk = function (kok, goster) {
        if (eskiEk) eskiEk(kok, goster);
        var o = (M.veri() && M.veri().ozet) || {}, kapali = (M.veri() && M.veri().menu_kapali) || [];
        // birim halkası (donut) — mevcut çubuklu kutunun yerine
        var kb = $('[data-w="birim"]', kok);
        if (kb && o.departmanlar && o.departmanlar.length) {
            var top = o.departmanlar.reduce(function (s, d) { return s + d.n; }, 0) || 1, ac = 0, R = 15.9155, kol = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#64748b'];
            var halka = o.departmanlar.map(function (d, i) { var p = d.n / top * 100, s = '<circle r="' + R + '" cx="21" cy="21" fill="none" stroke="' + kol[i % kol.length] + '" stroke-width="5" stroke-dasharray="' + p + ' ' + (100 - p) + '" stroke-dashoffset="' + (25 - ac) + '"></circle>'; ac += p; return s; }).join('');
            kb.innerHTML = '<div class="ikm-kart-baslik"><h3><i class="fa-solid fa-sitemap"></i> Birimlere göre personel</h3></div><div class="ikm-donut"><svg viewBox="0 0 42 42" role="img" aria-label="Birim dağılımı">' + halka + '<text x="21" y="22.5" text-anchor="middle" font-size="7" font-weight="700" fill="currentColor">' + top + '</text></svg>' +
                '<ul>' + o.departmanlar.slice(0, 8).map(function (d, i) { return '<li><i style="background:' + kol[i % kol.length] + '"></i><span>' + esc(d.ad) + '</span><b>' + d.n + '</b></li>'; }).join('') + '</ul></div>';
        }
        var izg = $('.ikm-izgara', kok); if (!izg) return;
        var yer = document.createElement('div'); yer.className = 'ikm-kart ikm-ek-kart'; yer.setAttribute('data-w', 'ek'); yer.innerHTML = M.yukleniyor();
        izg.appendChild(yer);
        getir({ islem: 'panel_ek' }).then(function (j) {
            var h = '';
            if (kapali.indexOf('izin_takvimi') < 0) h += '<div class="ikm-kart-baslik"><h3><i class="fa-solid fa-calendar-plus"></i> Takvim — önümüzdeki 7 gün</h3><button type="button" class="ikm-btn ikm-btn-kucuk" data-git="izin_takvimi">Aç</button></div>' +
                (j.notlar.length ? '<div class="ikm-liste">' + j.notlar.map(function (n) { return '<button type="button" class="ikm-satir" data-git="izin_takvimi"><span class="ikm-aj-serit" style="background:' + esc(n.renk) + '"></span><span class="ikm-satir-ic"><b>' + esc(n.baslik) + '</b><small>' + M.tarihKisa(n.tarih) + (n.saat ? ' · ' + esc(n.saat) : '') + '</small></span></button>'; }).join('') + '</div>' : M.bosKutu('fa-note-sticky', 'Yaklaşan not yok'));
            if (!h) yer.remove(); else yer.innerHTML = h;
        }).catch(function () { yer.remove(); });
    };

    /* ---------- Kişi seçici (arama yapılabilir) ---------- */
    M.kisiSec = function (baslik, cb) {
        var liste = (M.veri() && M.veri().kisiler) || [];
        var m = modal({ baslik: esc(baslik), ikon: 'fa-user-plus', gen: 460, govde: '<input type="search" class="ikm-ks-ara" placeholder="İsim ara…" autocomplete="off"><div class="ikm-ks-liste"></div>' });
        var ara = $('.ikm-ks-ara', m.el), kap = $('.ikm-ks-liste', m.el);
        function nz(x) { return String(x || '').toLocaleLowerCase('tr-TR'); }
        function ciz() {
            var a = nz(ara.value), l = liste.filter(function (k) { return !a || nz(k.ad + ' ' + (k.departman || '')).indexOf(a) >= 0; }).slice(0, 80);
            kap.innerHTML = l.map(function (k) { return '<button type="button" class="ikm-satir" data-id="' + k.id + '">' + M.av(k.ad, k.renk, k.resim) + '<span class="ikm-satir-ic"><b>' + esc(k.ad) + '</b><small>' + esc(k.departman || '') + '</small></span></button>'; }).join('') || '<div class="ikm-soluk" style="padding:14px">Sonuç yok.</div>';
        }
        ara.oninput = ciz; ciz(); setTimeout(function () { ara.focus(); }, 80);
        kap.onclick = function (e) { var b = e.target.closest('[data-id]'); if (!b) return; var k = liste.filter(function (x) { return x.id === +b.dataset.id; })[0]; m.kapat(); setTimeout(function () { cb(k); }, 180); };
    };

    /* ---------- Sabit duyuru çubuğu (her sayfanın üstünde, anlık güncellenir) ---------- */
    (function () {
        var kap = $('#ikm-sabit'); if (!kap) return;
        var DY = M.istek('duyuru_islem.php'), son = '', ilk = true, sayac, acik = 0, veri = [];
        var TUR = { genel: ['fa-bullhorn', '#6366f1'], onemli: ['fa-triangle-exclamation', '#f59e0b'], acil: ['fa-bell', '#f43f5e'], etkinlik: ['fa-champagne-glasses', '#8b5cf6'], bilgi: ['fa-circle-info', '#0ea5e9'] };
        function ciz(liste) {
            var imza = JSON.stringify(liste.map(function (d) { return [d.id, d.baslik, d.okundu, d.okuyan, d.tam]; }));
            veri = liste; if (acik && !liste.some(function (d) { return d.id === acik; })) acik = 0;
            if (imza === son) return; son = imza;
            var eski = {}; $$('.ikm-sabit-oge', kap).forEach(function (x) { eski[x.dataset.id] = 1; });
            kap.classList.toggle('dolu', !!liste.length);
            kap.innerHTML = liste.map(function (d) {
                var t = TUR[d.tur] || TUR.genel, yeni = !ilk && !eski[d.id];
                return '<button type="button" class="ikm-sabit-oge' + (yeni ? ' yeni' : '') + (acik === d.id ? ' acik' : '') + (d.okundu ? '' : ' okunmadi') + '" data-id="' + d.id + '" style="--tc:' + t[1] + '" title="' + esc(d.baslik) + '">' +
                    '<span class="ikm-sabit-pin"><i class="fa-solid fa-thumbtack"></i></span><span class="ikm-sabit-ik"><i class="fa-solid ' + t[0] + '"></i></span><b>' + esc(d.baslik) + '</b>' +
                    (d.yonet && d.okuyan != null ? '<span class="ikm-sabit-say" title="Gören / alıcı"><i class="fa-solid fa-eye"></i> ' + d.okuyan + '/' + d.alici + '</span>' : '') + (d.okundu ? '' : '<em>Yeni</em>') + '<i class="fa-solid fa-chevron-down ikm-sabit-ok"></i></button>';
            }).join('') + panel();
            ilk = false;
        }
        function panel() {
            var d = veri.filter(function (x) { return x.id === acik; })[0]; if (!d) return '';
            var t = TUR[d.tur] || TUR.genel;
            return '<div class="ikm-sabit-panel" style="--tc:' + t[1] + '"><div class="ikm-sabit-metin">' + (d.tam ? esc(d.tam) : '<span class="ikm-soluk">Açıklama yok.</span>') + '</div><div class="ikm-sabit-alt"><small>' + esc(d.yazar || '') + '</small><span><button type="button" class="ikm-btn ikm-btn-kucuk" data-detay="' + d.id + '">Detay</button> <button type="button" class="ikm-btn ikm-btn-kucuk" data-kapat="1">Kapat</button></span></div></div>';
        }
        function yukle() { DY.getir({ islem: 'sabitler' }).then(function (j) { ciz(j.liste || []); }).catch(function () {}); }
        M.sabitYenile = yukle;
        kap.onclick = function (e) {
            var dt = e.target.closest('[data-detay]');
            if (dt) { var i2 = +dt.dataset.detay; if (M.duyuruAc) M.duyuruAc(i2); else M.sekmeAc('duyurular'); return; }
            if (e.target.closest('[data-kapat]')) { acik = 0; son = ''; ciz(veri); return; }
            var b = e.target.closest('.ikm-sabit-oge'); if (!b) return; var id = +b.dataset.id;
            acik = acik === id ? 0 : id; son = ''; ciz(veri);
        };
        document.addEventListener('click', function (e) {
            var b = e.target.closest('[data-pin]'); if (!b) return; e.stopPropagation(); e.preventDefault();
            var yeni = b.dataset.sabit === '1' ? 0 : 1;
            DY.gonder('duyuru_durum', { id: b.dataset.pin, sabit: yeni }).then(function () {
                b.dataset.sabit = yeni; b.classList.toggle('aktif', !!yeni); b.title = yeni ? 'Sabiti kaldır' : 'Üste sabitle'; M.bildir(yeni ? 'Duyuru üste sabitlendi.' : 'Sabit kaldırıldı.', 'basari'); yukle();
            }).catch(function (er) { M.bildir(er.message, 'hata'); });
        }, true);
        yukle(); sayac = setInterval(yukle, 20000);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) yukle(); });
    })();
})();
