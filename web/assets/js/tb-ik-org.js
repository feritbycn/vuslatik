/* ==========================================================================
   tb-ik-org.js — Personel kartı "Organizasyon" sekmesi
   11 görünüm (açılır menüden seçilir, "Varsayılan yap" ile kişiye özel kaydedilir; varsayılan: Profil sandviç)
   Yönetici / ast / birim atama her görünümün üstündeki düzenleme çubuğundan yapılır.
   Uç nokta: org_islem.php · tb-ik.js kartCiz() → window.IKM.orgCiz(kap, veri, cekmece)
   ========================================================================== */
(function () {
    'use strict';
    function basla() {
        var M = window.IKM; if (!M || !M.kayit) return setTimeout(basla, 30);
        var $ = M.$, $$ = M.$$, esc = M.esc, bildir = M.bildir, onayla = M.onayla, modal = M.modal, av = M.av;
        var A = M.istek('org_islem.php'), getir = A.getir, gonder = A.gonder;
        var VARSAYILAN = 'sandvic';
        var GORUNUMLER = [
            ['sandvic', 'Profil sandviç', 'Üstte yönetici, ortada kişi, altta astlar'], ['kartlar', 'Kartlar (düzenleme)', 'Yönetici, astlar ve birimler kart olarak'],
            ['akis', 'Akan ağaç', 'Çizgilerde akan ışıkla klasik ağaç'], ['yatay', 'Yatay ağaç', 'Soldan sağa: üst → kişi → ekip'],
            ['yorunge', 'Yörünge', 'Astlar kişinin etrafında döner'], ['yolculuk', 'Onay yolculuğu', 'İzin talebi kimlerden geçer (canlı örnek)'],
            ['merdiven', 'Yetki merdiveni', 'Kademeler ve her kademenin yetkileri'], ['birimler', 'Birim haritası', 'Birimler, sorumluları ve kadro payı'],
            ['acilir', 'Açılır ağaç', 'Ok’a basıp ekibi açın'], ['matris', 'Yetki matrisi', 'Kim hangi yetkiye sahip'], ['duvar', 'Ekip duvarı', 'Tüm ekip birimlere göre bir arada']
        ];
        function nz(s) { return String(s == null ? '' : s).toLocaleLowerCase('tr-TR').replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u'); }
        function alt(k) { return [k.gorev, k.birim].filter(Boolean).filter(function (x, i, a) { return a.indexOf(x) === i; }).join(' · ') || 'Personel'; }
        function ilk(ad) { return String(ad || '').split(' ')[0]; }
        function tikla(k, ic, cls, title) { return '<button type="button" class="' + cls + '" data-okisi="' + k.id + '" title="' + esc(title || k.ad + ' — ' + alt(k)) + '">' + ic + '</button>'; }

        function satir(k, ek, sag) {
            return '<div class="ikor-satir" data-okisi="' + k.id + '">' + av(k.ad, k.renk, k.resim) + '<div class="ikor-ic"><b>' + esc(k.ad) + '</b><small>' + esc(alt(k)) + '</small></div>' + (ek || '') + (sag || '') + '</div>';
        }
        function bos(ikon, metin) { return '<div class="ikor-bos"><i class="fa-solid ' + ikon + '"></i><span>' + metin + '</span></div>'; }

        /* ───────────── GÖRÜNÜMLER ───────────── */
        var V = {};

        V.sandvic = function (D) {
            var h = '<div class="ikor-sw">' + (D.yonetici ? tikla(D.yonetici, av(D.yonetici.ad, D.yonetici.renk, D.yonetici.resim) + '<span><small>Yöneticisi</small><b>' + esc(D.yonetici.ad) + '</b></span><em>' + esc(D.yonetici.gorev || D.yonetici.birim || '') + '</em>', 'ikor-sw-ust') : '<div class="ikor-sw-ust bos"><i class="fa-regular fa-circle-user"></i> Yönetici atanmamış</div>') +
                '<i class="ikor-hat"></i><div class="ikor-sw-ben"><span class="ikor-halka">' + av(D.kisi.ad, D.kisi.renk, D.kisi.resim, 'dev') + '</span><div><h3>' + esc(D.kisi.ad) + '</h3><p>' + esc(alt(D.kisi)) + '</p>' +
                '<div class="ikor-cipler"><span class="ikm-cip mavi">' + D.astlar.length + ' doğrudan ast</span>' + (D.toplam_ekip > D.astlar.length ? '<span class="ikm-cip">Toplam ekip ' + D.toplam_ekip + '</span>' : '') + D.yonettigi_birimler.slice(0, 3).map(function (b) { return '<span class="ikm-cip mor">' + esc(b) + '</span>'; }).join('') + '</div></div></div>' +
                '<i class="ikor-hat"></i><div class="ikor-etiket">ASTLARI</div>' +
                (D.astlar.length ? '<div class="ikor-sw-astlar">' + D.astlar.map(function (k, i) { return tikla(k, av(k.ad, k.renk, k.resim, 'dev') + '<b>' + esc(k.ad) + '</b><small>' + esc(k.gorev || k.birim || '') + '</small>' + (k.alt_say ? '<em>+' + k.alt_say + '</em>' : ''), 'ikor-sw-ast', null).replace('class="ikor-sw-ast"', 'class="ikor-sw-ast" style="animation-delay:' + (0.5 + i * 0.07) + 's"'); }).join('') + '</div>' : bos('fa-people-group', 'Henüz astı yok.')) +
                (D.kardesler.length ? '<div class="ikor-kardes"><small>Aynı yöneticiye bağlı</small><div>' + D.kardesler.slice(0, 8).map(function (k) { return tikla(k, av(k.ad, k.renk, k.resim), '', k.ad); }).join('') + (D.kardesler.length > 8 ? '<span class="ikor-daha">+' + (D.kardesler.length - 8) + '</span>' : '') + '</div></div>' : '') + '</div>';
            return h;
        };

        V.kartlar = function (D) {
            var h = '<div class="ikor-kartlar">' +
                '<section class="ikor-kart"><div class="ikor-kart-ust"><h4><i class="fa-solid fa-user-tie"></i> Yöneticisi</h4>' + (D.duzenle && D.yonetici ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike ikm-btn-ikon" data-o="yon-sil" title="Yöneticiyi kaldır"><i class="fa-solid fa-xmark"></i></button>' : '') + '</div>' +
                (D.yonetici ? satir(D.yonetici, '', '<i class="fa-solid fa-chevron-right ikor-git"></i>') : bos('fa-regular fa-circle-user', 'Yönetici atanmamış.<br><small>İzin onayında “yönetici” adımı bu kişiye gider.</small>')) + '</section>' +
                '<section class="ikor-kart"><div class="ikor-kart-ust"><h4><i class="fa-solid fa-users"></i> Astları <span class="ikm-cip mavi">' + D.astlar.length + '</span></h4></div>' +
                (D.astlar.length ? '<div class="ikor-liste">' + D.astlar.map(function (k) { return satir(k, k.alt_say ? '<span class="ikm-cip" title="Bu kişinin kendi ekibi">' + k.alt_say + ' kişilik ekip</span>' : '', D.duzenle ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon ikm-btn-tehlike" data-o="ast-sil" data-id="' + k.id + '" title="Astlıktan çıkar"><i class="fa-solid fa-user-minus"></i></button>' : ''); }).join('') + '</div>' : bos('fa-people-group', 'Henüz astı yok.')) +
                (D.toplam_ekip > D.astlar.length ? '<div class="ikor-ozet"><i class="fa-solid fa-diagram-project"></i> Dolaylı astlarla birlikte toplam <b>' + D.toplam_ekip + '</b> kişilik ekip</div>' : '') +
                (D.toplam_ekip ? '<div class="ikor-dagilim">' + Object.keys(D.ekip_birim).slice(0, 6).map(function (b) { return '<span class="ikm-cip">' + esc(b) + ' · ' + D.ekip_birim[b] + '</span>'; }).join('') + '</div>' : '') + '</section>' +
                '<section class="ikor-kart"><div class="ikor-kart-ust"><h4><i class="fa-solid fa-sitemap"></i> Yönettiği birimler <span class="ikm-cip mor">' + D.yonettigi_birimler.length + '</span></h4></div>' +
                (D.yonettigi_birimler.length ? '<div class="ikor-birimler">' + D.yonettigi_birimler.map(function (b) { var bb = D.birimler.filter(function (x) { return x.ad === b; })[0]; return '<div class="ikor-birim"><span class="ikor-birim-ikon"><i class="fa-solid fa-building"></i></span><div><b>' + esc(b) + '</b><small>' + (bb ? bb.kisi + ' kişi' : '') + '</small></div></div>'; }).join('') + '</div>' : bos('fa-regular fa-building', 'Herhangi bir birimin yöneticisi değil.')) + '</section></div>';
            return h;
        };

        V.akis = function (D) {
            var h = '<div class="ikor-harita">';
            D.zincir.forEach(function (k) { h += tikla(k, av(k.ad, k.renk, k.resim) + '<span><b>' + esc(k.ad) + '</b><small>' + esc(k.gorev || k.birim || '') + '</small></span>', 'ikor-dugum ust') + '<i class="ikor-hat"></i>'; });
            h += '<div class="ikor-dugum ben">' + av(D.kisi.ad, D.kisi.renk, D.kisi.resim, 'dev') + '<span><b>' + esc(D.kisi.ad) + '</b><small>' + esc(alt(D.kisi)) + '</small></span>' + (D.toplam_ekip ? '<em>' + D.toplam_ekip + ' kişilik ekip</em>' : '') + '</div>';
            if (D.astlar.length) h += '<i class="ikor-hat"></i><div class="ikor-dal">' + D.astlar.slice(0, 14).map(function (k) { return tikla(k, av(k.ad, k.renk, k.resim) + '<span><b>' + esc(ilk(k.ad)) + '</b></span>', 'ikor-dugum ast'); }).join('') + (D.astlar.length > 14 ? '<span class="ikor-daha">+' + (D.astlar.length - 14) + '</span>' : '') + '</div>';
            return h + '</div>';
        };

        V.yatay = function (D) {
            var kolon = function (baslik, icerik) { return '<div class="ikor-yk"><div class="ikor-etiket">' + baslik + '</div>' + icerik + '</div>'; };
            var ok = '<span class="ikor-yok"><i></i></span>';
            var ust = D.zincir.length ? D.zincir.slice().reverse().map(function (k) { return tikla(k, av(k.ad, k.renk, k.resim) + '<span><b>' + esc(k.ad) + '</b><small>' + esc(k.gorev || '') + '</small></span>', 'ikor-chip'); }).join('') : '<div class="ikor-bos kucuk">Üstte kimse yok</div>';
            var asl = D.astlar.length ? D.astlar.slice(0, 10).map(function (k) { return tikla(k, av(k.ad, k.renk, k.resim) + '<span><b>' + esc(k.ad) + '</b><small>' + esc(k.gorev || '') + '</small></span>' + (k.alt_say ? '<em>' + k.alt_say + '</em>' : ''), 'ikor-chip'); }).join('') + (D.astlar.length > 10 ? '<span class="ikor-daha">+' + (D.astlar.length - 10) + ' kişi</span>' : '') : '<div class="ikor-bos kucuk">Astı yok</div>';
            var h = '<div class="ikor-yatay">' + kolon('ÜST YÖNETİM', ust) + ok + kolon('KİŞİ', '<div class="ikor-chip ben">' + av(D.kisi.ad, D.kisi.renk, D.kisi.resim, 'dev') + '<span><b>' + esc(D.kisi.ad) + '</b><small>' + esc(D.kisi.gorev || '') + '</small></span></div>') + ok + kolon('EKİBİ', asl) + '</div>';
            return h;
        };

        V.yorunge = function (D) {
            var L = D.astlar.slice(0, 10), n = L.length || 1;
            var h = '<div class="ikor-yor-sahne">' + (D.yonetici ? tikla(D.yonetici, av(D.yonetici.ad, D.yonetici.renk, D.yonetici.resim) + '<span><small>Yöneticisi</small><b>' + esc(D.yonetici.ad) + '</b></span>', 'ikor-yor-yon') : '') +
                '<div class="ikor-yor-r1"></div><div class="ikor-yor-r2"></div><div class="ikor-yor-nabiz"></div>' +
                '<div class="ikor-yor">' + L.map(function (k, i) { var a = Math.round(i * 360 / n - 90); return '<div class="ikor-yor-slot" style="transform:rotate(' + a + 'deg) translateX(var(--yr))"><div style="transform:rotate(' + (-a) + 'deg)"><div class="ikor-yor-ters">' + tikla(k, av(k.ad, k.renk, k.resim, 'dev'), 'ikor-yor-chip', k.ad + ' — ' + alt(k)) + '</div></div></div>'; }).join('') + '</div>' +
                '<div class="ikor-yor-orta">' + av(D.kisi.ad, D.kisi.renk, D.kisi.resim, 'dev') + '</div>' +
                '<div class="ikor-yor-alt"><b>' + esc(D.kisi.ad) + '</b><small>' + (D.astlar.length ? D.astlar.length + ' doğrudan ast' : 'Astı yok') + '</small></div></div>' +
                (D.astlar.length > 10 ? '<div class="ikor-daha" style="text-align:center">+' + (D.astlar.length - 10) + ' kişi daha (Kartlar görünümünde)</div>' : '');
            return h;
        };

        V.yolculuk = function (D, C) {
            var adimlar = [{ k: D.kisi, rol: 'Talep sahibi' }].concat(D.onay.map(function (k) { return { k: k, rol: 'Onaycı' + (k.vekil ? ' · vekil ' + k.vekil : '') }; })).concat([{ son: true }]);
            var s = C.adim, N = adimlar.length - 1; if (s > N) s = C.adim = N;
            var doluluk = N ? (s / N) * 100 : 0;
            var h = '<div class="ikor-yol"><div class="ikor-yol-bas">' + esc(D.kisi.ad) + ' izin isterse talep şu kişilerden geçer:</div>';
            if (!D.onay.length) return h + bos('fa-route', 'Bu kişi için onay akışı tanımlı değil.<br><small>İK → Onay akışı bölümünden birim bazlı akış tanımlanır.</small>') + '</div>';
            h += '<div class="ikor-yol-kap"><div class="ikor-yol-cizgi"></div><div class="ikor-yol-dolu" style="width:' + doluluk + '%"></div><div class="ikor-yol-adim">' + adimlar.map(function (a, i) {
                var bitti = i < s || (a.son && s === N), simdi = i === s && !a.son;
                var ic = a.son ? '<span class="ikor-yol-son"><i class="fa-solid fa-check"></i></span>' : av(a.k.ad, a.k.renk, a.k.resim, 'dev');
                return '<div class="ikor-yol-d' + (bitti ? ' bitti' : '') + (simdi ? ' simdi' : '') + '"><span class="ikor-yol-yuv">' + ic + '</span><b>' + (a.son ? 'Onaylandı' : esc(ilk(a.k.ad))) + '</b><small>' + (a.son ? 'İzin kesinleşti' : esc(a.rol)) + '</small><em>' + (bitti ? (a.son ? 'Tamam' : (i === 0 ? 'Gönderdi' : 'Onayladı')) : simdi ? 'Bekliyor' : 'Sırada') + '</em></div>';
            }).join('') + '</div></div>' +
                '<div class="ikor-yol-eylem"><button type="button" class="ikm-btn ikm-btn-ana" data-o="yol-ileri"' + (s >= N ? ' disabled' : '') + '><i class="fa-solid fa-play"></i> ' + (s === 0 ? 'Örnek talebi gönder' : s < N ? 'Sıradaki onaylasın' : 'Tamamlandı') + '</button><button type="button" class="ikm-btn" data-o="yol-sifirla">Başa al</button></div>' +
                '<div class="ikor-ozet"><i class="fa-solid fa-circle-info"></i> Bu bir canlı örnektir; gerçek talep oluşturmaz. Akış, onay akışı ayarlarından gelir.</div></div>';
            return h;
        };

        V.merdiven = function (D) {
            var yet = {}; if (D.yetki) D.yetki.satirlar.forEach(function (r) { yet[r.kisi.id] = r.v; });
            var kat = function (ad, kisiler, cls, w, yetkiler) {
                return '<div class="ikor-kat ' + cls + '" style="width:' + w + '%"><b>' + ad + '</b><div class="ikor-kat-k">' + kisiler.slice(0, 6).map(function (k) { return tikla(k, av(k.ad, k.renk, k.resim), 'ikor-kat-a', k.ad); }).join('') + (kisiler.length > 6 ? '<span class="ikor-daha" style="color:#fff">+' + (kisiler.length - 6) + '</span>' : '') + '</div>' +
                    (yetkiler ? '<div class="ikor-yetler">' + yetkiler + '</div>' : '') + '</div>';
            };
            var AD = D.yetki ? D.yetki.sutunlar : [];
            var yc = function (id) { var v = yet[id]; if (!v) return ''; return v.map(function (x, i) { return x ? '<span>' + esc(AD[i]) + '</span>' : ''; }).join('') || '<span class="yok">Ek yetkisi yok</span>'; };
            var kat_say = D.zincir.length + 1 + (D.astlar.length ? 1 : 0), i = 0, W = function () { i++; return Math.round(46 + (i - 1) * (54 / Math.max(1, kat_say - 1))); };
            var h = '<div class="ikor-merdiven">';
            D.zincir.forEach(function (k, ix) { h += kat('Kademe ' + (ix + 1) + ' · ' + esc(k.gorev || 'Üst yönetim'), [k], 'k' + (ix % 4), W(), yc(k.id)); });
            h += kat('Bu kişi · ' + esc(D.kisi.gorev || 'Personel'), [D.kisi], 'ben', W(), yc(D.kisi.id));
            if (D.astlar.length) h += kat('Astları · ' + D.astlar.length + ' kişi', D.astlar, 'ast', W(), '');
            return h + '</div>' + (D.yetki ? '' : '<div class="ikor-ozet"><i class="fa-solid fa-lock"></i> Kademeler görünür; yetki rozetlerini yalnızca personel düzenleme yetkisi olanlar görür.</div>');
        };

        V.birimler = function (D) {
            var yb = {}; D.yonettigi_birimler.forEach(function (b) { yb[nz(b)] = 1; });
            var toplam = D.birimler.reduce(function (t, b) { return t + b.kisi; }, 0) || 1, benB = nz(D.kisi.birim || '');
            var L = D.birimler.slice().sort(function (a, b) { return (yb[nz(b.ad)] ? 1 : 0) - (yb[nz(a.ad)] ? 1 : 0) || b.kisi - a.kisi; });
            if (!L.length) return bos('fa-sitemap', 'Henüz birim tanımlı değil.');
            return '<div class="ikor-bg">' + L.map(function (b, i) {
                var y = yb[nz(b.ad)], c = benB && nz(b.ad) === benB;
                return '<div class="ikor-blok' + (y ? ' yon' : '') + (c ? ' cal' : '') + '" style="animation-delay:' + (i * 0.05) + 's"><div class="ikor-blok-ust"><b>' + esc(b.ad) + '</b><span>' + b.kisi + '</span></div>' +
                    (y ? '<span class="ikm-cip mor"><i class="fa-solid fa-user-tie"></i> ' + esc(ilk(D.kisi.ad)) + ' yönetiyor</span>' : '') + (c ? '<span class="ikm-cip mavi">Çalıştığı birim</span>' : '') + (b.diger.length ? '<small>Yönetici: ' + esc(b.diger.join(', ')) + '</small>' : '') +
                    '<div class="ikor-blok-cubuk"><i style="width:' + Math.max(4, Math.round(b.kisi / toplam * 100)) + '%"></i></div></div>';
            }).join('') + '</div><div class="ikor-ozet"><i class="fa-solid fa-circle-info"></i> Çubuk: kurum kadrosu içindeki pay</div>';
        };

        V.acilir = function (D, C) {
            var ch = {}; D.agac.forEach(function (n) { (ch[n.yonetici_id] = ch[n.yonetici_id] || []).push(n); });
            var yol = {}; D.zincir.forEach(function (k) { yol[k.id] = 1; }); yol[D.kisi.id] = 1; yol[D.kok_id] = 1;
            if (!C.acik) C.acik = yol;
            var h = '<div class="ikor-ac-ust"><button type="button" class="ikm-btn ikm-btn-kucuk" data-o="ac-hepsi">Tümünü aç</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-o="ac-kapat">Kapat</button></div><div class="ikor-ac">';
            (function yur(n, d) {
                var alt0 = ch[n.id] || [], acik = !!C.acik[n.id];
                h += '<div class="ikor-ac-s' + (n.id === D.kisi.id ? ' ben' : '') + '" style="margin-left:' + Math.min(d, 8) * 20 + 'px">' + (alt0.length ? '<button type="button" class="ikor-ok' + (acik ? ' acik' : '') + '" data-o="ac" data-id="' + n.id + '" aria-label="Aç / kapat"><i class="fa-solid fa-chevron-right"></i></button>' : '<span class="ikor-ok-bos"></span>') +
                    '<span class="ikor-ac-k" data-okisi="' + n.id + '">' + av(n.ad, n.renk, n.resim) + '<span class="ikor-ic"><b>' + esc(n.ad) + '</b><small>' + esc(alt(n)) + '</small></span></span>' + (alt0.length ? '<span class="ikm-cip">' + alt0.length + ' ast</span>' : '') + (n.id === D.kisi.id ? '<span class="ikm-cip mavi">Bu kişi</span>' : '') + '</div>';
                if (acik) alt0.forEach(function (x) { yur(x, d + 1); });
            })(D.agac.filter(function (n) { return n.id === D.kok_id; })[0] || D.agac[0], 0);
            return h + '</div>';
        };

        V.matris = function (D) {
            if (!D.yetki) return bos('fa-lock', 'Yetki matrisini yalnızca personel düzenleme yetkisi olanlar görür.');
            var Y = D.yetki;
            return '<div class="ikor-mt"><div class="ikor-mt-bas"><span></span>' + Y.sutunlar.map(function (s) { return '<span>' + esc(s) + '</span>'; }).join('') + '</div>' + Y.satirlar.map(function (r, i) {
                return '<div class="ikor-mt-s" style="animation-delay:' + i * 0.06 + 's" data-okisi="' + r.kisi.id + '"><span class="ikor-mt-k">' + av(r.kisi.ad, r.kisi.renk, r.kisi.resim) + '<span class="ikor-ic"><b>' + esc(r.kisi.ad) + '</b><small>' + r.rol + '</small></span></span>' +
                    r.v.map(function (x) { return '<span class="ikor-mt-h">' + (x ? '<i class="var"><i class="fa-solid fa-check"></i></i>' : '<i class="yok"></i>') + '</span>'; }).join('') + '</div>';
            }).join('') + '</div><div class="ikor-ozet"><i class="fa-solid fa-circle-info"></i> Ayrıntılı düzenleme için menüden “Yetkiler” bölümünü kullanın.</div>';
        };

        V.duvar = function (D) {
            var d = {}; D.agac.forEach(function (n) { if (n.id === D.kok_id || true) { var b = n.birim || 'Belirtilmemiş'; (d[b] = d[b] || []).push(n); } });
            var yon = {}; D.agac.forEach(function (n) { yon[n.yonetici_id] = 1; });
            var ekip = {}; (function f(id) { ekip[id] = 1; D.agac.forEach(function (n) { if (n.yonetici_id === id && !ekip[n.id]) f(n.id); }); })(D.kisi.id); if (D.yonetici) ekip[D.yonetici.id] = 1;
            var B = Object.keys(d).sort(function (a, b) { return d[b].length - d[a].length; });
            var i = 0;
            return '<div class="ikor-dv">' + B.map(function (b) {
                return '<div class="ikor-dv-g"><div class="ikor-dv-b"><b>' + esc(b) + '</b><span>' + d[b].length + '</span></div><div class="ikor-dv-k">' + d[b].map(function (n) {
                    i++; return tikla(n, av(n.ad, n.renk, n.resim, yon[n.id] ? 'dev' : ''), 'ikor-dv-a' + (n.id === D.kisi.id ? ' ben' : '') + (ekip[n.id] ? '' : ' sonuk') + (yon[n.id] ? ' yon' : ''), n.ad + ' — ' + alt(n)).replace('class="', 'style="animation-delay:' + Math.min(i, 40) * 0.02 + 's" class="');
                }).join('') + '</div></div>';
            }).join('') + '</div><div class="ikor-ozet"><i class="fa-solid fa-circle-info"></i> Büyük yuvarlak: yönetici · mavi halka: bu kişi · soluk: farklı ekip</div>';
        };

        /* ───────────── Seçiciler (atama pencereleri) ───────────── */
        function secici(o) {
            var secili = {}; (o.baslangic || []).forEach(function (i) { secili[i] = true; });
            var f = '';
            var m = modal({ baslik: o.baslik, ikon: o.ikon, gen: 560, govde: '<div id="ikor-sec"></div>',
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ikor-tamam"><i class="fa-solid fa-check"></i> <span></span></button>' });
            var kap = $('#ikor-sec', m.el), tamam = $('#ikor-tamam', m.el);
            function say() { return Object.keys(secili).filter(function (k) { return secili[k]; }).length; }
            function etiket() { var n = say(); $('span', tamam).textContent = o.coklu ? (n ? n + ' kişiyi ekle' : 'Ekle') : 'Seç'; tamam.disabled = o.coklu && !n; }
            function ciz() {
                var a = nz(f), L = o.liste.filter(function (k) { return !a || nz(k.ad + ' ' + k.gorev + ' ' + k.birim).indexOf(a) >= 0; });
                kap.innerHTML = '<div class="ikm-ara" style="margin-bottom:10px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="ikor-ara" placeholder="Ad, görev veya birim ara…" value="' + esc(f) + '"></div>' +
                    '<div class="ikor-sec-liste">' + (L.length ? L.map(function (k) {
                        return '<label class="ikor-sec-satir' + (secili[k.id] ? ' acik' : '') + '"><input type="' + (o.coklu ? 'checkbox' : 'radio') + '" name="ikor" value="' + k.id + '"' + (secili[k.id] ? ' checked' : '') + '>' + av(k.ad, k.renk, k.resim) +
                            '<span class="ikor-ic"><b>' + esc(k.ad) + '</b><small>' + esc(alt(k)) + '</small></span>' + (k.not ? '<span class="ikm-cip turuncu">' + esc(k.not) + '</span>' : '') + '</label>';
                    }).join('') : '<div class="ikm-bos-kucuk">Eşleşen kişi yok</div>') + '</div>';
                $$('input[name=ikor]', kap).forEach(function (c) { c.onchange = function () { if (!o.coklu) secili = {}; secili[c.value] = c.checked; $$('.ikor-sec-satir', kap).forEach(function (r) { r.classList.toggle('acik', r.querySelector('input').checked); }); etiket(); }; });
                var ar = $('#ikor-ara', kap); ar.oninput = function () { f = ar.value; var p = ar.selectionStart; ciz(); var y = $('#ikor-ara', m.el); y.focus(); try { y.setSelectionRange(p, p); } catch (e) {} };
            }
            ciz(); etiket();
            tamam.onclick = function () { var ids = Object.keys(secili).filter(function (k) { return secili[k]; }).map(Number); tamam.disabled = true; Promise.resolve(o.tamam(ids)).then(function () { m.kapat(); }).catch(function (e) { bildir(e.message, 'hata'); etiket(); }); };
        }

        function birimSecici(C, D) {
            var secili = {}; D.yonettigi_birimler.forEach(function (b) { secili[b] = true; });
            var m = modal({ baslik: 'Yönettiği birimler', ikon: 'fa-sitemap', gen: 520, govde: '<p class="ikm-soluk" style="margin:0 0 10px">' + esc(D.kisi.ad) + ' hangi birim(ler)in yöneticisi? Birden fazla seçebilirsiniz.</p><div class="ikor-sec-liste" id="ikor-b"></div>',
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ikor-b-tamam"><i class="fa-solid fa-check"></i> Kaydet</button>' });
            $('#ikor-b', m.el).innerHTML = D.birimler.length ? D.birimler.map(function (b) {
                return '<label class="ikor-sec-satir' + (secili[b.ad] ? ' acik' : '') + '"><input type="checkbox" value="' + esc(b.ad) + '"' + (secili[b.ad] ? ' checked' : '') + '><span class="ikor-birim-ikon"><i class="fa-solid fa-building"></i></span><span class="ikor-ic"><b>' + esc(b.ad) + '</b><small>' + b.kisi + ' kişi' + (b.diger.length ? ' · diğer yönetici: ' + esc(b.diger.join(', ')) : '') + '</small></span></label>';
            }).join('') : '<div class="ikm-bos-kucuk">Henüz birim tanımlı değil. Personel kartlarında birim girildikçe burada görünür.</div>';
            $$('input', m.el).forEach(function (c) { c.onchange = function () { secili[c.value] = c.checked; c.closest('.ikor-sec-satir').classList.toggle('acik', c.checked); }; });
            $('#ikor-b-tamam', m.el).onclick = function () {
                gonder('org_birimler', { id: D.kisi.id, birimler: JSON.stringify(Object.keys(secili).filter(function (k) { return secili[k]; })) }).then(function (j) { m.kapat(); bildir('Yönettiği birimler güncellendi.', 'basari'); C.veri(j); }).catch(function (e) { bildir(e.message, 'hata'); });
            };
        }

        /* ───────────── Ana çizici ───────────── */
        M.orgCiz = function (kap, j) {
            var id = j.kart.id, C = { gor: null, kayitli: VARSAYILAN, D: null, adim: 0, acik: null };
            function ad(g) { return GORUNUMLER.filter(function (x) { return x[0] === g; })[0] || GORUNUMLER[0]; }
            C.veri = function (D) { C.D = D; if (!C.gor) { C.kayitli = D.gorunum && ad(D.gorunum)[0] === D.gorunum ? D.gorunum : VARSAYILAN; C.gor = C.kayitli; } iskelet(); };
            function yukle() { return getir({ islem: 'org', id: id }).then(C.veri).catch(function (e) { kap.innerHTML = '<div class="ikm-bos-kucuk">' + esc(e.message) + '</div>'; }); }
            function iskelet() {
                var D = C.D;
                kap.innerHTML = '<div class="ikor"><div class="ikor-arac"><label class="ikor-sec"><i class="fa-solid ' + (ad(C.gor)[0] ? 'fa-eye' : '') + '"></i><select id="ikor-gor" aria-label="Görünüm">' + GORUNUMLER.map(function (g) { return '<option value="' + g[0] + '"' + (g[0] === C.gor ? ' selected' : '') + '>' + g[1] + (g[0] === C.kayitli ? ' ★' : '') + '</option>'; }).join('') + '</select></label>' +
                    '<button type="button" class="ikm-btn ikm-btn-kucuk" id="ikor-vars" ' + (C.gor === C.kayitli ? 'disabled' : '') + '><i class="fa-' + (C.gor === C.kayitli ? 'solid' : 'regular') + ' fa-star"></i> <span>' + (C.gor === C.kayitli ? 'Varsayılan' : 'Varsayılan yap') + '</span></button></div>' +
                    '<p class="ikor-not">' + esc(ad(C.gor)[2]) + '</p>' +
                    (D.duzenle ? '<div class="ikor-duz"><button type="button" class="ikm-btn ikm-btn-kucuk" data-o="yon"><i class="fa-solid fa-user-tie"></i> ' + (D.yonetici ? 'Yöneticiyi değiştir' : 'Yönetici ata') + '</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-o="ast"><i class="fa-solid fa-user-plus"></i> Ast ekle</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-o="birim"><i class="fa-solid fa-sitemap"></i> Birime ata</button>' + (C.gor !== 'kartlar' && D.astlar.length ? '<span class="ikor-ipucu">Astı çıkarmak için “Kartlar” görünümü</span>' : '') + '</div>' : '') +
                    '<div class="ikor-govde" id="ikor-govde"></div></div>';
                govde();
                $('#ikor-gor', kap).onchange = function () { C.gor = this.value; C.adim = 0; C.acik = null; iskelet(); };
                $('#ikor-vars', kap).onclick = function () {
                    var g = C.gor; gonder('org_gorunum', { gorunum: g }).then(function () { C.kayitli = g; bildir('“' + ad(g)[1] + '” varsayılan görünüm olarak kaydedildi.', 'basari'); iskelet(); }).catch(function (e) { bildir(e.message, 'hata'); });
                };
            }
            function govde() { $('#ikor-govde', kap).innerHTML = '<div class="ikor-v v-' + C.gor + '">' + V[C.gor](C.D, C) + '</div>'; }
            kap.onclick = function (e) {
                var t, D = C.D; if (!D) return;
                if ((t = e.target.closest('[data-o]'))) {
                    var o = t.dataset.o;
                    if (o === 'yol-ileri') { C.adim++; govde(); }
                    else if (o === 'yol-sifirla') { C.adim = 0; govde(); }
                    else if (o === 'ac') { C.acik[t.dataset.id] = !C.acik[t.dataset.id]; govde(); }
                    else if (o === 'ac-hepsi') { C.acik = {}; D.agac.forEach(function (n) { C.acik[n.id] = true; }); govde(); }
                    else if (o === 'ac-kapat') { C.acik = {}; govde(); }
                    else if (o === 'yon') {
                        var asag = {}; (function f(x) { D.kisiler.forEach(function (k) { if (k.yonetici_id === x && !asag[k.id]) { asag[k.id] = true; f(k.id); } }); })(id);
                        secici({ baslik: 'Yönetici seç', ikon: 'fa-user-tie', coklu: false, baslangic: D.yonetici ? [D.yonetici.id] : [], liste: D.kisiler.filter(function (k) { return k.id !== id && !asag[k.id]; }),
                            tamam: function (ids) { if (!ids.length) throw new Error('Bir kişi seçin.'); return gonder('org_yonetici', { id: id, yonetici_id: ids[0] }).then(function (r) { bildir('Yönetici güncellendi.', 'basari'); C.veri(r); }); } });
                    } else if (o === 'yon-sil') {
                        onayla({ baslik: 'Yönetici kaldırılsın mı?', mesaj: D.kisi.ad + ' için yönetici ataması kaldırılacak.', evet: 'Kaldır', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('org_yonetici', { id: id, yonetici_id: 0 }).then(function (r) { bildir('Yönetici kaldırıldı.', 'basari'); C.veri(r); }); }).catch(function (er) { bildir(er.message, 'hata'); });
                    } else if (o === 'ast') {
                        var ust = {}; D.zincir.forEach(function (k) { ust[k.id] = true; }); var adlar = {}; D.kisiler.forEach(function (k) { adlar[k.id] = k.ad; });
                        var aday = D.kisiler.filter(function (k) { return k.id !== id && !ust[k.id] && k.yonetici_id !== id; }).map(function (k) { var c = {}; for (var x in k) c[x] = k[x]; if (k.yonetici_id) c.not = 'Şu an: ' + (adlar[k.yonetici_id] || '—'); return c; });
                        secici({ baslik: 'Ast ekle', ikon: 'fa-user-plus', coklu: true, liste: aday, tamam: function (ids) { return gonder('org_ast_ekle', { id: id, ids: JSON.stringify(ids) }).then(function (r) { bildir(ids.length + ' kişi ekibe eklendi.', 'basari'); C.veri(r); }); } });
                    } else if (o === 'ast-sil') {
                        var an = (D.astlar.filter(function (k) { return String(k.id) === t.dataset.id; })[0] || {}).ad || 'Bu kişi';
                        onayla({ baslik: 'Astlıktan çıkarılsın mı?', mesaj: an + ' artık ' + D.kisi.ad + ' kişisine bağlı olmayacak (yöneticisiz kalır).', evet: 'Çıkar', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('org_ast_cikar', { id: id, ast_id: t.dataset.id }).then(function (r) { bildir('Ekipten çıkarıldı.', 'basari'); C.veri(r); }); }).catch(function (er) { bildir(er.message, 'hata'); });
                    } else if (o === 'birim') birimSecici(C, D);
                    return;
                }
                if ((t = e.target.closest('[data-okisi]'))) { if (+t.dataset.okisi !== id && M.kartAc) M.kartAc(+t.dataset.okisi, 'org'); }
            };
            yukle();
        };
    }
    basla();
})();
