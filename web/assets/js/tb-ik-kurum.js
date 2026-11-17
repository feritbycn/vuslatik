/* ==========================================================================
   tb-ik-kurum.js — Kurum içi kullanıcı yönetimi, şifre değiştirme, tema (çok kurumlu ürün)
   Kullanıcılar sayfası: kurum yetkilisi (ya da "Kullanıcı yönetimi" yetkisi olan kişi) kullanıcı ekler, rol verir, şifre sıfırlar, pasifleştirir.
   Hesap kendi kendine açılmaz: giriş sayfasında kayıt yoktur.
   ========================================================================== */
(function () {
    'use strict';
    function basla() {
        var M = window.IKM; if (!M || !M.kayit) return setTimeout(basla, 30);
        var $ = M.$, $$ = M.$$, esc = M.esc, getir = M.getir, gonder = M.gonder, bildir = M.bildir, onayla = M.onayla, modal = M.modal, icerik = M.icerik;
        var ROL = { kurucu: ['Kurum yöneticisi', 'mor', 'Tüm yetkiler; başkalarına yetki dağıtabilir'], yetkili: ['Yetkili', 'mavi', 'Ek izinleri “Yetkiler” penceresinden ayarlanır'], personel: ['Personel', 'gri', 'Kendi kartı ve izin talebi'] };
        var D = null, ara = '';
        var SEC = {};
        /* Birime personel ata: birim seçili ise üyeler işaretli gelir; işaret ekle/kaldır = atama/çıkarma */
        function birimPencere(birimAd, bitti) {
            return Promise.all([getir({ islem: 'kullanicilar' }), birimAd ? Promise.resolve(null) : getir({ islem: 'birimler' })]).then(function (r) {
                var L = r[0].kullanicilar.filter(function (u) { return +u.aktif; }), bl = r[1] ? r[1].liste : [];
                var kucuk = function (x) { return String(x || '').toLocaleLowerCase('tr-TR'); };
                var once = {}, sec = {};
                L.forEach(function (u) { if (birimAd && kucuk(u.birim) === kucuk(birimAd)) { once[u.id] = 1; sec[u.id] = 1; } });
                var m = modal({ baslik: birimAd ? '“' + esc(birimAd) + '” birimine personel ata' : 'Birime personel ata', ikon: 'fa-sitemap', gen: 580,
                    govde: '<div class="ikm-form">' + (birimAd ? '' : '<label class="ikm-alan"><span>Birim</span><input id="bp-birim" list="bp-dl" maxlength="100" placeholder="Seçin veya yeni birim yazın" autocomplete="off"><datalist id="bp-dl">' + bl.map(function (b) { return '<option value="' + esc(b.ad) + '">'; }).join('') + '</datalist></label>') +
                        '<div class="ikm-ara" style="margin-bottom:8px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="bp-ara" placeholder="Personel ara…" autocomplete="off"></div>' +
                        '<div class="ikm-arac" style="margin-bottom:6px"><button type="button" class="ikm-btn ikm-btn-kucuk" id="bp-hepsi">Görünenlerin hepsini seç</button><button type="button" class="ikm-btn ikm-btn-kucuk" id="bp-hic">Seçimi temizle</button></div>' +
                        '<div id="bp-liste" style="max-height:340px;overflow:auto"></div></div>',
                    alt: '<span class="ikm-soluk" id="bp-say" style="margin-right:auto"></span><button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="bp-ok"><i class="fa-solid fa-check"></i> Kaydet</button>' });
                var liste = $('#bp-liste', m.el);
                function say() { var n = Object.keys(sec).length; $('#bp-say', m.el).textContent = n + ' kişi seçili'; }
                function goster() {
                    var q = kucuk($('#bp-ara', m.el).value);
                    var f = L.filter(function (u) { return !q || kucuk(u.ad_soyad).indexOf(q) >= 0; });
                    liste.innerHTML = f.map(function (u) { return '<label class="ikm-pe-satir"><input type="checkbox" value="' + u.id + '"' + (sec[u.id] ? ' checked' : '') + '><i></i>' + M.av(u.ad_soyad, u.avatar_renk, u.resim) + '<span><b>' + esc(u.ad_soyad) + '</b><small>' + (u.birim ? 'Şu an: ' + esc(u.birim) : 'Birimi yok') + '</small></span></label>'; }).join('') || '<div class="ikm-soluk">Sonuç yok.</div>';
                    $$('input', liste).forEach(function (c) { c.onchange = function () { if (c.checked) sec[c.value] = 1; else delete sec[c.value]; say(); }; });
                    return f;
                }
                goster(); say();
                $('#bp-ara', m.el).oninput = goster;
                $('#bp-hepsi', m.el).onclick = function () { goster().forEach(function (u) { sec[u.id] = 1; }); goster(); say(); };
                $('#bp-hic', m.el).onclick = function () { sec = {}; goster(); say(); };
                $('#bp-ok', m.el).onclick = function () {
                    var d = this, ad = birimAd || ($('#bp-birim', m.el).value || '').trim();
                    if (!ad) { bildir('Birim adı seçin ya da yazın.', 'uyari'); return; }
                    var ekle = Object.keys(sec).filter(function (k) { return !once[k]; }).map(Number), cikar = Object.keys(once).filter(function (k) { return !sec[k]; }).map(Number);
                    if (!ekle.length && !cikar.length) { bildir('Değişiklik yok.', 'uyari'); return; }
                    d.disabled = true;
                    var is = Promise.resolve();
                    if (ekle.length) is = is.then(function () { return gonder('birim_ata', { ids: JSON.stringify(ekle), birim: ad }); });
                    if (cikar.length) is = is.then(function () { return gonder('birim_ata', { ids: JSON.stringify(cikar), birim: '' }); });
                    is.then(function () { m.kapat(); bildir((ekle.length ? ekle.length + ' kişi “' + ad + '” birimine atandı' : '') + (ekle.length && cikar.length ? ', ' : '') + (cikar.length ? cikar.length + ' kişi birimden çıkarıldı' : '') + '.', 'basari'); return M.yenileVeri ? M.yenileVeri() : null; }).then(function () { if (bitti) bitti(); }).catch(function (e) { d.disabled = false; bildir(e.message, 'hata'); });
                };
            });
        }
        M.birimPersonelAta = birimPencere;
        /* Seçili kullanıcıları tek birime ata (çoklu seçim) */
        function topluBirimAta(ids, bitti) {
            getir({ islem: 'birimler' }).then(function (j) {
                var m = modal({ baslik: ids.length + ' kişiyi birime ata', ikon: 'fa-sitemap', gen: 460,
                    govde: '<div class="ikm-form"><label class="ikm-alan"><span>Birim</span><input id="tb-birim" list="tb-dl" maxlength="100" placeholder="Seçin veya yeni birim yazın (boş = birimden çıkar)" autocomplete="off"><datalist id="tb-dl">' + j.liste.map(function (b) { return '<option value="' + esc(b.ad) + '">'; }).join('') + '</datalist></label></div>',
                    alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="tb-ok"><i class="fa-solid fa-check"></i> Ata</button>' });
                $('#tb-ok', m.el).onclick = function () {
                    var d = this; d.disabled = true;
                    gonder('birim_ata', { ids: JSON.stringify(ids), birim: ($('#tb-birim', m.el).value || '').trim() }).then(function (r) { m.kapat(); bildir(r.n + ' kişinin birimi güncellendi.', 'basari'); return M.yenileVeri ? M.yenileVeri() : null; }).then(function () { if (bitti) bitti(); }).catch(function (e) { d.disabled = false; bildir(e.message, 'hata'); });
                };
            });
        }

        function waTel(t) { var d = String(t || '').replace(/\D/g, ''); if (d.indexOf('00') === 0) d = d.slice(2); if (d.charAt(0) === '0') d = '90' + d.slice(1); else if (d.length === 10) d = '90' + d; return d.length >= 11 ? d : ''; }
        function bilgiKutusu(baslik, ad, sifre, kid, tel0) {
            var kl = (D && D.kullanicilar || []).filter(function (x) { return +x.id === +kid; })[0], wa = waTel(tel0 || (kl && kl.telefon));
            var url = location.origin + location.pathname.replace(/[^/]*$/, '') + 'giris.php?kurum=' + encodeURIComponent((window.TB.kurum || {}).kod || '');
            var metin = 'Giriş adresi: ' + url + '\nKullanıcı adı: ' + ad + (sifre ? '\nGeçici şifre: ' + sifre : '');
            var m = modal({ baslik: baslik, ikon: 'fa-key', gen: 520, govde: '<div class="pf-gizli-kutu" style="display:grid;gap:10px;padding:14px;border-radius:14px;background:color-mix(in srgb,var(--ik-yesil) 10%,transparent);border:1px solid color-mix(in srgb,var(--ik-yesil) 40%,transparent)">' +
                [['Giriş adresi', url], ['Kullanıcı adı', ad]].concat(sifre ? [['Geçici şifre', sifre]] : []).map(function (r) { return '<div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center"><span style="min-width:110px;color:var(--ik-soluk);font-size:13px">' + r[0] + '</span><code style="font:600 14px ui-monospace,Menlo,Consolas,monospace;padding:5px 9px;border-radius:8px;background:var(--ik-yuzey2);border:1px dashed var(--ik-cizgi);user-select:all;word-break:break-all">' + esc(r[1]) + '</code></div>'; }).join('') +
                (sifre ? '<small class="ikm-soluk">Bu şifre yalnızca <b>şimdi</b> gösterilir. Kişi ilk girişte kendi şifresini belirler.</small>' : '') + '</div>',
                alt: (sifre && kid ? '<div class="ku-gonder-sat"><span>Kişiye gönder:</span><button type="button" class="ikm-btn ikm-btn-kucuk" data-gk="sms"><i class="fa-solid fa-comment-sms"></i> SMS</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-gk="mail"><i class="fa-regular fa-envelope"></i> E-posta</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ana" data-gk="ikisi"><i class="fa-solid fa-paper-plane"></i> İkisi de</button>' + (wa ? '<a class="ikm-btn ikm-btn-kucuk ikm-wa" target="_blank" rel="noopener" href="https://wa.me/' + wa + '?text=' + encodeURIComponent(metin + '\n\nİlk girişte yeni şifre belirlemeniz istenecek.') + '"><i class="fa-brands fa-whatsapp"></i> WhatsApp</a>' : '') + '</div><div id="ku-gsonuc" class="ku-gsonuc"></div>' : '') + '<button type="button" class="ikm-btn" id="ku-kop"><i class="fa-regular fa-copy"></i> Kopyala</button><button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Tamam</button>' });
            $$('[data-gk]', m.el).forEach(function (b) {
                b.onclick = function () {
                    var tum = $$('[data-gk]', m.el); tum.forEach(function (x) { x.disabled = true; });
                    gonder('kullanici_sifre_gonder', { id: kid, sifre: sifre, kanal: b.dataset.gk }).then(function (r) {
                        var h = ''; [['sms', 'SMS'], ['mail', 'E-posta']].forEach(function (k) { var v = r[k[0]]; if (v) h += '<div class="' + (v[0] ? 'ok' : 'hata') + '"><i class="fa-solid ' + (v[0] ? 'fa-circle-check' : 'fa-circle-xmark') + '"></i> ' + k[1] + ': ' + (v[0] ? 'gönderildi' : esc(v[1])) + '</div>'; });
                        $('#ku-gsonuc', m.el).innerHTML = h; tum.forEach(function (x) { x.disabled = false; });
                    }).catch(function (e) { bildir(e.message, 'hata'); tum.forEach(function (x) { x.disabled = false; }); });
                };
            });
            $('#ku-kop', m.el).onclick = function () { if (navigator.clipboard) navigator.clipboard.writeText(metin).then(function () { bildir('Kopyalandı.', 'basari'); }); };
        }

        /* ── Giriş bilgisini SMS ile gönder (tek kişi ya da toplu) ── */
        function smsPenceresi(tek) {
            if (!D.sms_hazir && !D.mail_hazir) { bildir('SMS ve e-posta ayarları yapılmamış. “Bildirim kanalları” bölümünden tanımlayın.', 'uyari'); return; }
            var kanal = D.sms_hazir && D.mail_hazir ? 'ikisi' : (D.sms_hazir ? 'sms' : 'mail');
            var telVar = function (u) { return String(u.telefon || '').replace(/\D/g, '').length >= 10; }, mailVar = function (u) { return /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(String(u.email || '')); };
            var uygun = function (u) { return +u.aktif === 1 && +u.id !== +D.ben && !(u.rol === 'kurucu' && !D.ben_kurucu) && (kanal === 'sms' ? telVar(u) : kanal === 'mail' ? mailVar(u) : (telVar(u) || mailVar(u))); };
            var secili = {}, f = '';
            D.kullanicilar.forEach(function (u) { if (tek ? +u.id === tek : false) secili[u.id] = uygun(u); });
            var m = modal({ baslik: tek ? 'Şifreyi yenile ve gönder' : 'Şifreleri yenile ve gönder', ikon: 'fa-comment-sms', gen: 620, govde: '<div id="sm-ic"></div>',
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="sm-gonder"><i class="fa-solid fa-paper-plane"></i> <span>Gönder</span></button>' });
            var ic = $('#sm-ic', m.el), gon = $('#sm-gonder', m.el);
            function say() { return Object.keys(secili).filter(function (k) { return secili[k]; }).length; }
            function guncelleBtn() { var n = say(); gon.disabled = !n; $('span', gon).textContent = n ? n + ' kişiye gönder' : 'Gönder'; }
            function ciz() {
                var a = f.toLocaleLowerCase('tr-TR');
                var L = D.kullanicilar.filter(function (u) { return !a || (u.ad_soyad + ' ' + u.kullanici_adi).toLocaleLowerCase('tr-TR').indexOf(a) >= 0; });
                ic.innerHTML = '<div class="ku-kanal-sec">' + [['sms', 'fa-comment-sms', 'SMS', D.sms_hazir], ['mail', 'fa-regular fa-envelope', 'E-posta', D.mail_hazir], ['ikisi', 'fa-solid fa-paper-plane', 'İkisi de', D.sms_hazir && D.mail_hazir]].map(function (k) { return '<button type="button" class="ku-kanal' + (kanal === k[0] ? ' aktif' : '') + '" data-kn="' + k[0] + '"' + (k[3] ? '' : ' disabled title="Ayarı yapılmamış"') + '><i class="' + (k[1].indexOf('fa-regular') === 0 || k[1].indexOf('fa-solid') === 0 ? k[1] : 'fa-solid ' + k[1]) + '"></i> ' + k[2] + '</button>'; }).join('') + '</div><div class="ikm-not" style="margin-bottom:10px"><i class="fa-solid fa-circle-info"></i> Şifresini unutan kişilere <b>yeni bir geçici şifre</b> (giriş adresi ve kullanıcı adıyla birlikte) seçtiğiniz kanaldan gider; eski şifre geçersiz olur ve kişi ilk girişte kendi şifresini belirler. Hiçbir kanaldan gitmezse eski şifre değişmez.</div>' +
                    (tek ? '' : '<div class="ikm-arac" style="gap:8px;flex-wrap:wrap"><div class="ikm-ara" style="flex:1;min-width:160px"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="sm-ara" placeholder="Kişi ara…" value="' + esc(f) + '"></div>' +
                    '<button type="button" class="ikm-btn ikm-btn-kucuk" data-h="tum">Telefonu olan herkes</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-h="girmeyen">Hiç girmeyenler</button><button type="button" class="ikm-btn ikm-btn-kucuk" data-h="temizle">Temizle</button></div>') +
                    '<div style="max-height:46vh;overflow:auto;display:grid;gap:6px">' + L.map(function (u) {
                        var ok = uygun(u), why = !+u.aktif ? 'Pasif' : +u.id === +D.ben ? 'Siz' : !ok ? (kanal === 'mail' ? 'E-posta yok' : kanal === 'sms' ? 'Telefon yok' : 'Telefon/e-posta yok') : '';
                        return '<label class="ikm-ozel-satir" style="' + (ok ? '' : 'opacity:.5;cursor:not-allowed') + '"><input type="checkbox" data-u="' + u.id + '"' + (secili[u.id] ? ' checked' : '') + (ok ? '' : ' disabled') + '><i></i><span style="flex:1;min-width:0"><b>' + esc(u.ad_soyad) + '</b><br><small class="ikm-soluk">@' + esc(u.kullanici_adi) + ' · ' + (u.telefon ? esc(u.telefon) : 'telefon yok') + (+u.son_giris ? '' : '') + '</small></span>' +
                            (why ? '<span class="ikm-cip gri">' + why + '</span>' : (u.son_giris ? '' : '<span class="ikm-cip turuncu">Hiç girmedi</span>')) + '</label>';
                    }).join('') + '</div>';
                $$('[data-kn]', ic).forEach(function (b) { b.onclick = function () { kanal = b.dataset.kn; D.kullanicilar.forEach(function (u) { if (secili[u.id] && !uygun(u)) secili[u.id] = false; }); ciz(); guncelleBtn(); }; });
                $$('[data-u]', ic).forEach(function (c) { c.onchange = function () { secili[c.dataset.u] = c.checked; guncelleBtn(); }; });
                $$('[data-h]', ic).forEach(function (b) { b.onclick = function () { D.kullanicilar.forEach(function (u) { secili[u.id] = b.dataset.h === 'temizle' ? false : uygun(u) && (b.dataset.h === 'tum' || !u.son_giris); }); ciz(); guncelleBtn(); }; });
                var ar = $('#sm-ara', ic); if (ar) ar.oninput = function () { f = ar.value; var p = ar.selectionStart; ciz(); var y = $('#sm-ara', m.el); y.focus(); try { y.setSelectionRange(p, p); } catch (e) {} };
            }
            ciz(); guncelleBtn();
            gon.onclick = function () {
                var ids = Object.keys(secili).filter(function (k) { return secili[k]; });
                gon.disabled = true; $('span', gon).textContent = 'Gönderiliyor…';
                gonder('kullanici_sms', { ids: JSON.stringify(ids), kanal: kanal }).then(function (j) {
                    ic.innerHTML = '<div class="ikm-kpiler" style="margin-bottom:10px"><div class="ikm-kpi yesil"><span class="ikm-kpi-ikon"><i class="fa-solid fa-check"></i></span><div><b>' + j.gonderilen + '</b><span>Gönderildi</span></div></div><div class="ikm-kpi ' + (j.toplam > j.gonderilen ? 'turuncu' : 'mavi') + '"><span class="ikm-kpi-ikon"><i class="fa-solid fa-triangle-exclamation"></i></span><div><b>' + (j.toplam - j.gonderilen) + '</b><span>Gönderilemedi</span></div></div></div>' +
                        '<div style="max-height:40vh;overflow:auto;display:grid;gap:6px">' + j.sonuc.map(function (r) { return '<div class="ikm-satir"><span class="ikm-satir-ikon"><i class="fa-solid ' + (r.ok ? 'fa-circle-check' : 'fa-circle-xmark') + '" style="color:' + (r.ok ? '#22c55e' : '#ef4444') + '"></i></span><span class="ikm-satir-ic"><b>' + esc(r.ad) + '</b><small>' + (r.ok ? ((r.kanallar || []).join(' + ') || 'SMS') + ' gönderildi' + (r.neden ? ' · ' + esc(r.neden) : '') : esc(r.neden)) + '</small></span></div>'; }).join('') + '</div>';
                    gon.style.display = 'none'; var v = $('[data-kapat]', m.el); if (v) v.textContent = 'Kapat';
                    yukle();
                }).catch(function (e) { bildir(e.message, 'hata'); guncelleBtn(); });
            };
        }
        function bas(ad) { var p = String(ad || '?').trim().split(/\s+/); return ((p[0] || '?')[0] + (p.length > 1 ? p[p.length - 1][0] : '')).toLocaleUpperCase('tr-TR'); }

        function ciz() {
            var a = ara.toLocaleLowerCase('tr-TR');
            var L = D.kullanicilar.filter(function (u) { return !a || (u.ad_soyad + ' ' + u.kullanici_adi + ' ' + u.gorev + ' ' + u.email).toLocaleLowerCase('tr-TR').indexOf(a) >= 0; });
            var kota = D.sinir > 0 ? D.aktif + ' / ' + D.sinir : String(D.aktif);
            var h = '<div class="ikm-arac"><div class="ikm-ara"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="ku-ara" placeholder="Ad, kullanıcı adı veya görev ara…" value="' + esc(ara) + '"></div>' +
                '<span class="ikm-cip ' + (D.sinir > 0 && D.aktif >= D.sinir ? 'turuncu' : 'mavi') + '"><i class="fa-solid fa-users"></i> ' + kota + ' aktif kullanıcı</span><button type="button" class="ikm-btn ikm-btn-kucuk" id="ku-toplu-birim" style="margin-left:auto"><i class="fa-solid fa-sitemap"></i> Toplu birim ata</button><button type="button" class="ikm-btn ikm-btn-kucuk" id="ku-sms-toplu" title="Şifresini unutanlara (tek ya da toplu) yeni geçici şifreyi SMS ile gönderir"><i class="fa-solid fa-comment-sms"></i> Şifre yenile + SMS gönder</button></div>';
            h += '<div class="ikm-kart"><div class="ikm-tablo-kap"><table class="ikm-tablo"><thead><tr><th style="width:34px"><input type="checkbox" id="ku-hepsi" aria-label="Görünenlerin hepsini seç"></th><th>Kullanıcı</th><th>Rol</th><th>Görev</th><th>Birim</th><th>Son giriş</th><th>Durum</th><th></th></tr></thead><tbody>' +
                (L.length ? L.map(function (u) {
                    var r = ROL[u.rol] || [u.rol, 'gri', ''], ben = +u.id === +D.ben, kilitli = u.rol === 'kurucu' && !D.ben_kurucu;
                    return '<tr' + (+u.aktif ? '' : ' style="opacity:.6"') + '><td><input type="checkbox" data-sec="' + u.id + '"' + (SEC[u.id] ? ' checked' : '') + ' aria-label="Seç"></td><td><div style="display:flex;gap:10px;align-items:center">' + M.av(u.ad_soyad, u.avatar_renk, u.resim) + '<div><b>' + esc(u.ad_soyad) + '</b>' + (ben ? ' <span class="ikm-cip mavi">Siz</span>' : '') + '<br><small class="ikm-soluk">@' + esc(u.kullanici_adi) + (u.email ? ' · ' + esc(u.email) : '') + '</small></div></div></td>' +
                        '<td><span class="ikm-cip ' + r[1] + '" title="' + esc(r[2]) + '">' + r[0] + '</span></td><td>' + esc(u.gorev || '—') + '</td><td>' + esc(u.birim || '—') + '</td><td>' + (u.son_giris ? M.tarihSaat(u.son_giris) : '<span class="ikm-soluk">Hiç girmedi</span>') + (+u.sifre_degistir ? '<br><small class="ikm-soluk">Geçici şifre bekliyor</small>' : '') + '</td>' +
                        '<td><span class="ikm-cip ' + (+u.aktif ? 'yesil' : 'gri') + '">' + (+u.aktif ? 'Aktif' : 'Pasif') + '</span></td><td class="ikm-islem">' +
                        (kilitli ? '<span class="ikm-soluk" title="Yalnızca kurum yetkilisi düzenleyebilir"><i class="fa-solid fa-lock"></i></span>' :
                            '<button type="button" class="ikm-btn ikm-btn-kucuk" data-d="' + u.id + '" title="Düzenle"><i class="fa-solid fa-pen"></i></button><button type="button" class="ikm-btn ikm-btn-kucuk" data-s="' + u.id + '" title="Geçici şifre üret"><i class="fa-solid fa-key"></i></button><button type="button" class="ikm-btn ikm-btn-kucuk" data-m="' + u.id + '" title="Şifreyi yenile ve SMS ile gönder"><i class="fa-solid fa-comment-sms"></i></button>' +
                            (ben ? '' : '<button type="button" class="ikm-btn ikm-btn-kucuk ' + (+u.aktif ? 'ikm-btn-tehlike' : '') + '" data-p="' + u.id + '" title="' + (+u.aktif ? 'Pasifleştir' : 'Etkinleştir') + '"><i class="fa-solid ' + (+u.aktif ? 'fa-user-slash' : 'fa-user-check') + '"></i></button>')) + '</td></tr>';
                }).join('') : '<tr><td colspan="8">' + M.bosKutu('fa-users', 'Kullanıcı bulunamadı') + '</td></tr>') + '</tbody></table></div></div>' +
                (D.ben_kurucu ? '<div class="ikm-not" style="margin-top:14px"><i class="fa-solid fa-circle-info"></i> Başkalarına yetki vermek için menüdeki <b>Yetkiler</b>’i kullanın. “Yetkili” rolü ek izinler alır; <b>Kurum yöneticisi</b> her şeye erişir.</div>' : '');
            icerik.innerHTML = h;
            $('#ku-sms-toplu').onclick = function () { smsPenceresi(null); };
            $$('[data-m]', icerik).forEach(function (b) { b.onclick = function () { smsPenceresi(+b.dataset.m); }; });
            $$('[data-sec]', icerik).forEach(function (c) { c.onchange = function () { if (c.checked) SEC[c.dataset.sec] = 1; else delete SEC[c.dataset.sec]; var n = Object.keys(SEC).length; $('#ku-toplu-birim').innerHTML = '<i class="fa-solid fa-sitemap"></i> ' + (n ? n + ' kişiyi birime ata' : 'Toplu birim ata'); }; });
            var hp = $('#ku-hepsi'); if (hp) hp.onchange = function () { L.forEach(function (u) { if (hp.checked) SEC[u.id] = 1; else delete SEC[u.id]; }); ciz(); };
            (function () { var n = Object.keys(SEC).length; if (n) $('#ku-toplu-birim').innerHTML = '<i class="fa-solid fa-sitemap"></i> ' + n + ' kişiyi birime ata'; })();
            $('#ku-toplu-birim').onclick = function () { var ids = Object.keys(SEC).map(Number); if (!ids.length) { bildir('Önce tablodan bir ya da birkaç kişi seçin (soldaki kutular).', 'uyari'); return; } topluBirimAta(ids, function () { SEC = {}; yukle(); }); };
            var inp = $('#ku-ara'); inp.oninput = function () { ara = inp.value; var p = inp.selectionStart; ciz(); var y = $('#ku-ara'); y.focus(); try { y.setSelectionRange(p, p); } catch (e) {} };
            var bul = function (id) { return D.kullanicilar.filter(function (u) { return String(u.id) === String(id); })[0]; };
            $$('[data-d]', icerik).forEach(function (b) { b.onclick = function () { form(bul(b.dataset.d)); }; });
            $$('[data-s]', icerik).forEach(function (b) { b.onclick = function () { var u = bul(b.dataset.s); onayla({ baslik: 'Şifre sıfırlansın mı?', mesaj: u.ad_soyad + ' için geçici şifre üretilecek; kişi ilk girişte kendi şifresini belirler.', evet: 'Sıfırla' }).then(function (ok) { if (ok) return gonder('kullanici_sifre', { id: u.id }).then(function (r) { bilgiKutusu('Geçici şifre', r.kullanici_adi, r.sifre, u.id); yukle(); }); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
            $$('[data-p]', icerik).forEach(function (b) { b.onclick = function () { var u = bul(b.dataset.p), pasif = +u.aktif === 1; onayla({ baslik: pasif ? 'Kullanıcı pasifleştirilsin mi?' : 'Kullanıcı etkinleştirilsin mi?', mesaj: pasif ? u.ad_soyad + ' artık giriş yapamaz. Kayıtları silinmez.' : u.ad_soyad + ' yeniden giriş yapabilecek.', evet: pasif ? 'Pasifleştir' : 'Etkinleştir', tur: pasif ? 'tehlike' : 'bilgi' }).then(function (ok) { if (ok) return gonder('kullanici_durum', { id: u.id, aktif: pasif ? 0 : 1 }).then(function () { bildir('Güncellendi.', 'basari'); yukle(); }); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
        }
        function form(u) {
            var yeni = !u, v = u || { ad_soyad: '', kullanici_adi: '', rol: 'personel', gorev: '', telefon: '', email: '', birim: '' };
            var rolSec = ['personel', 'yetkili'].concat(D.ben_kurucu ? ['kurucu'] : []);
            var m = modal({ baslik: yeni ? 'Kullanıcı ekle' : 'Kullanıcıyı düzenle', ikon: 'fa-user-plus', gen: 600, govde:
                '<form class="ikm-form" id="ku-f" autocomplete="off"><div class="ikm-iki"><label class="ikm-alan"><span>Ad soyad <b>*</b></span><input name="ad_soyad" value="' + esc(v.ad_soyad) + '" required></label>' +
                '<label class="ikm-alan"><span>Kullanıcı adı ' + (yeni ? '<b>*</b>' : '') + '</span><input name="kullanici_adi" value="' + esc(v.kullanici_adi) + '" ' + (yeni ? 'placeholder="ad.soyad" autocapitalize="none"' : 'readonly') + '></label>' +
                '<label class="ikm-alan"><span>Rol</span><select name="rol">' + rolSec.map(function (r) { return '<option value="' + r + '"' + (v.rol === r ? ' selected' : '') + '>' + ROL[r][0] + ' — ' + ROL[r][2] + '</option>'; }).join('') + '</select></label>' +
                '<label class="ikm-alan"><span>Görev / unvan</span><input name="gorev" list="ku-unvan" value="' + esc(v.gorev) + '" placeholder="Listeden seçin veya yazın" autocomplete="off"><datalist id="ku-unvan"></datalist></label>' +
                '<label class="ikm-alan"><span>Birim <small>(departman)</small></span><input name="birim" list="ku-birim" maxlength="100" value="' + esc(v.birim || '') + '" placeholder="Seçin veya yeni yazın" autocomplete="off"><datalist id="ku-birim"></datalist></label>' +
                '<label class="ikm-alan"><span>Telefon <small>(SMS bildirimleri için)</small></span><input name="telefon" value="' + esc(v.telefon) + '"></label><label class="ikm-alan"><span>E-posta</span><input type="email" name="email" value="' + esc(v.email) + '"></label></div>' +
                (yeni ? '<label class="ikm-alan"><span>Şifre <small>(boş bırakırsanız geçici şifre üretilir)</small></span><input type="text" name="sifre" autocomplete="new-password" placeholder="Otomatik"></label>' : '') + '</form>',
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ku-k"><i class="fa-solid fa-check"></i> Kaydet</button>' });
            getir({ islem: 'unvanlar' }).then(function (j) { var dl = $('#ku-unvan', m.el); if (dl) dl.innerHTML = j.liste.map(function (b) { return '<option value="' + esc(b.ad) + '">'; }).join(''); }).catch(function () {});
            getir({ islem: 'birimler' }).then(function (j) { var dl = $('#ku-birim', m.el); if (dl) dl.innerHTML = j.liste.map(function (b) { return '<option value="' + esc(b.ad) + '">'; }).join(''); }).catch(function () {});
            $('#ku-k', m.el).onclick = function () {
                var f = $('#ku-f', m.el), d = { id: yeni ? 0 : u.id }; Array.prototype.forEach.call(f.elements, function (el) { if (el.name) d[el.name] = el.value; });
                var b = this; b.disabled = true;
                gonder('kullanici_kaydet', d).then(function (r) { m.kapat(); bildir(yeni ? 'Kullanıcı eklendi.' : 'Kaydedildi.', 'basari'); yukle(); if (yeni) setTimeout(function () { bilgiKutusu('Giriş bilgileri', r.kullanici_adi, r.sifre, r.id, d.telefon); }, 250); })
                    .catch(function (e) { b.disabled = false; bildir(e.message, 'hata'); });
            };
        }

        /* ── Toplu içe aktarma (CSV / Excel) ── */
        var IA_SUTUN = [['ad_soyad', 'Ad Soyad'], ['kullanici_adi', 'Kullanıcı Adı'], ['rol', 'Rol'], ['gorev', 'Görev'], ['telefon', 'Telefon'], ['email', 'E-posta'], ['departman', 'Departman (Birim)'], ['unvan', 'Unvan'], ['ise_giris', 'İşe Giriş'], ['dogum_tarihi', 'Doğum Tarihi'], ['tc_kimlik', 'TC Kimlik'], ['yillik_izin_hak', 'Yıllık İzin Hakkı'], ['sgk_no', 'SGK No']];
        /* Eşleştirilebilir alanlar (sütun eşleştirme listesi) */
        var IA_ALAN = [['ad_soyad', 'Ad Soyad (tam ad)'], ['ad', 'Ad'], ['email2', 'E-posta (kişisel / yedek)'], ['telefon2', 'Telefon (kişisel / yedek)'], ['yonetici', 'Yönetici (ad soyad)'], ['soyad', 'Soyad'], ['tc_kimlik', 'T.C. Kimlik No'], ['ise_giris', 'İşe başlama tarihi'], ['pozisyon_baslama', 'Pozisyon başlama tarihi'], ['isten_cikis', 'İşten ayrılma tarihi'], ['calisma_sekli', 'Çalışma şekli'], ['departman', 'Departman (Birim)'], ['unvan', 'Unvan / Pozisyon'], ['durum', 'Çalışma durumu (Aktif / Pasif)'], ['gorev', 'Görev'], ['telefon', 'Telefon'], ['email', 'E-posta'], ['kullanici_adi', 'Kullanıcı adı'], ['rol', 'Rol (personel / yetkili)'], ['dogum_tarihi', 'Doğum tarihi'], ['yillik_izin_hak', 'Yıllık izin hakkı'], ['sgk_no', 'SGK sicil no'], ['sirket', 'Şirket (yok sayılır)']];
        var IA_ESLE = { email2: ['epostakisisel', 'kisiselposta', 'kisiselemail', 'epostakisisel'], telefon2: ['telefonkisisel', 'kisiseltelefon', 'ceptelefonu', 'ceptelefon'], yonetici: ['yonetici', 'amir', 'birimyoneticisi', 'baglioldugukisi'], pozisyon_baslama: ['pozisyonbaslamatarihi', 'pozisyonbaslama', 'gorevbaslamatarihi', 'pozisyongiris'], isten_cikis: ['istenayrilmatarihi', 'istenayrilma', 'istencikis', 'istencikistarihi', 'ayrilmatarihi', 'cikistarihi', 'ayrilis'], calisma_sekli: ['calismasekli', 'calismatipi', 'calismabicimi', 'istihdamsekli', 'istihdamturu'], durum: ['calismadurumu', 'durum', 'aktiflik', 'personeldurumu'],
            ad_soyad: ['adsoyad', 'adisoyadi', 'adsoyadi', 'adivesoyadi', 'isimsoyisim', 'personel', 'personeladi', 'personeladsoyad', 'calisan', 'calisanadi', 'tamad'], ad: ['ad', 'adi', 'isim'], soyad: ['soyad', 'soyadi', 'soyisim'], kullanici_adi: ['kullaniciadi', 'kullanici', 'username'], rol: ['rol', 'yetki'], gorev: ['gorev'], telefon: ['telefon', 'tel', 'gsm', 'cep', 'ceptelefonu', 'telefonno', 'telefonnumarasi'], email: ['eposta', 'email', 'mail', 'epostaadresi', 'mailadresi'], departman: ['departman', 'departmanbirim', 'birimdepartman', 'birim', 'bolum', 'departmani'], unvan: ['unvan', 'unvani', 'pozisyon', 'gorevunvani', 'meslek'], ise_giris: ['isegiris', 'isegirisi', 'isegiristarihi', 'isebaslama', 'isebaslamatarihi', 'girista', 'giristarihi', 'baslamatarihi', 'isebaslangic'], dogum_tarihi: ['dogumtarihi', 'dogum'], tc_kimlik: ['tckimlik', 'tckimlikno', 'tc', 'tcno', 'kimlikno', 'kimliknumarasi', 'tckimliknumarasi', 'tckn', 'tcnumarasi', 'kimlik'], yillik_izin_hak: ['yillikizinhakki', 'yillikizin', 'izinhakki', 'izin'], sgk_no: ['sgkno', 'sgk', 'sgksicilno', 'sicilno'], sirket: ['sirket', 'firma', 'isyeri', 'kurum'] };
        var IA_IPUCU = { pozisyon_baslama: ['pozisyonbasl'], isten_cikis: ['ayril', 'istencik'], calisma_sekli: ['calismasekl', 'calismatip'], durum: ['durum'], tc_kimlik: ['kimlik', 'tckn'], ise_giris: ['isebasla', 'isegiris'], departman: ['departman', 'birim'], unvan: ['unvan', 'pozisyon'], email: ['posta', 'mail'], telefon: ['telefon', 'gsm'], dogum_tarihi: ['dogum'], sgk_no: ['sgk'], ad_soyad: ['adsoyad', 'adisoyad'], soyad: ['soyad', 'soyisim'], ad: ['isim'] };
        function nz(s) { return String(s == null ? '' : s).toLocaleLowerCase('tr-TR').replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u').replace(/[^a-z0-9]/g, ''); }
        function tarihDuz(v) {
            if (v == null || v === '') return '';
            if (v instanceof Date) { var d = new Date(v.getTime() + 12 * 3600e3); return d.getUTCFullYear() + '-' + ('0' + (d.getUTCMonth() + 1)).slice(-2) + '-' + ('0' + d.getUTCDate()).slice(-2); }
            var s = String(v).trim(), m;
            if ((m = s.match(/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/))) return m[3] + '-' + ('0' + m[2]).slice(-2) + '-' + ('0' + m[1]).slice(-2);
            if (/^\d{5}$/.test(s)) { var d2 = new Date(Date.UTC(1899, 11, 30) + (+s) * 86400000); return d2.getUTCFullYear() + '-' + ('0' + (d2.getUTCMonth() + 1)).slice(-2) + '-' + ('0' + d2.getUTCDate()).slice(-2); }
            return s;
        }
        /** CSV metni → satırlar (tırnaklı alanlar, ; , sekme ayırıcılar) */
        function csvOku(t) {
            t = t.replace(/^﻿/, ''); var ilk = t.split(/\r?\n/)[0] || '', ay = [';', ',', '\t'].sort(function (a, b) { return ilk.split(b).length - ilk.split(a).length; })[0];
            var s = [], r = [], h = '', tirnak = false;
            for (var i = 0; i < t.length; i++) {
                var c = t[i];
                if (tirnak) { if (c === '"') { if (t[i + 1] === '"') { h += '"'; i++; } else tirnak = false; } else h += c; }
                else if (c === '"') tirnak = true;
                else if (c === ay) { r.push(h); h = ''; }
                else if (c === '\n' || c === '\r') { if (c === '\r' && t[i + 1] === '\n') i++; r.push(h); h = ''; s.push(r); r = []; }
                else h += c;
            }
            if (h !== '' || r.length) { r.push(h); s.push(r); }
            return s.filter(function (x) { return x.some(function (v) { return String(v).trim() !== ''; }); });
        }
        /** Excel kütüphanesi: önce sunucudaki kopya, olmazsa CDN */
        function xlsxYukle() {
            if (window.XLSX) return Promise.resolve();
            var yukle = function (src) { return new Promise(function (ok, no) { var sc = document.createElement('script'); sc.src = src; sc.onload = ok; sc.onerror = function () { sc.remove(); no(new Error('y')); }; document.head.appendChild(sc); }); };
            return yukle('assets/js/vendor/xlsx.full.min.js').catch(function () { return yukle('https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js'); }).catch(function () { throw new Error('Excel okuyucu yüklenemedi. Dosyayı “CSV UTF-8” olarak kaydedip deneyin.'); });
        }
        function dosyaOku(f) {
            return new Promise(function (coz, red) {
                var uz = (f.name.split('.').pop() || '').toLowerCase();
                if (uz === 'xlsx' || uz === 'xls') {
                    var yukle = xlsxYukle;
                    yukle().then(function () { var fr = new FileReader(); fr.onload = function () { try { var wb = XLSX.read(fr.result, { type: 'array', cellDates: true }); coz(XLSX.utils.sheet_to_json(wb.Sheets[wb.SheetNames[0]], { header: 1, raw: true, defval: '' })); } catch (e) { red(new Error('Excel dosyası okunamadı. CSV olarak kaydedip deneyin.')); } }; fr.onerror = function () { red(new Error('Dosya okunamadı.')); }; fr.readAsArrayBuffer(f); }, red);
                } else {
                    var fr = new FileReader(); fr.onload = function () { var b = fr.result, t = new TextDecoder('utf-8').decode(b); if (t.indexOf('�') >= 0) { try { t = new TextDecoder('windows-1254').decode(b); } catch (e) {} } coz(csvOku(t)); }; fr.onerror = function () { red(new Error('Dosya okunamadı.')); }; fr.readAsArrayBuffer(f);
                }
            });
        }
        /** Bir başlık satırı için sütun → alan eşlemesi (önce birebir, sonra benzerlik) */
        function eslemeBul(bas) {
            var n = bas.map(nz), harita = {}, kul = {}, alanlar = Object.keys(IA_ESLE);
            alanlar.forEach(function (k) { var i = n.findIndex(function (b, ix) { return !kul[ix] && IA_ESLE[k].indexOf(b) >= 0; }); if (i >= 0) { harita[k] = i; kul[i] = 1; } });
            alanlar.forEach(function (k) {
                if (harita[k] != null || !IA_IPUCU[k]) return;
                var i = n.findIndex(function (b, ix) { return !kul[ix] && b && IA_IPUCU[k].some(function (h) { return b.indexOf(h) >= 0; }) && !(k === 'unvan' && /tarih|basla/.test(b)); });
                if (i >= 0) { harita[k] = i; kul[i] = 1; }
            });
            var dizi = n.map(function () { return ''; }); Object.keys(harita).forEach(function (k) { dizi[harita[k]] = k; });
            return dizi;
        }
        /** İlk satırlar arasında en çok alan tanıyan satırı başlık sayar (üstte başlık/başlık dışı satırlar olabilir) */
        function baslikSatiri(tablo) {
            var en = 0, say = -1;
            for (var i = 0; i < Math.min(10, tablo.length); i++) { var c = eslemeBul(tablo[i].map(function (x) { return String(x == null ? '' : x); })).filter(Boolean).length; if (c > say) { say = c; en = i; } }
            return en;
        }
        function bosDeger(v) { var t = String(v == null ? '' : v).trim(); return /^[-–—.]+$/.test(t) ? '' : t; }
        /** BÜYÜK HARFLİ adları "Ahmet Zeki" biçimine çevirir */
        function adDuz(t) {
            t = String(t || '').trim().replace(/\s+/g, ' ');
            if (!t || t !== t.toLocaleUpperCase('tr-TR')) return t;
            return t.toLocaleLowerCase('tr-TR').split(' ').map(function (w) { return w.charAt(0).toLocaleUpperCase('tr-TR') + w.slice(1); }).join(' ');
        }
        /** Tablo + sütun eşlemesi → gönderilecek satır nesneleri */
        function satirlariKur(tablo, bi, esle) {
            var harita = {}; esle.forEach(function (k, i) { if (k && harita[k] == null) harita[k] = i; });
            var out = [];
            tablo.slice(bi + 1).forEach(function (r, n) {
                if (!r.some(function (v) { return String(v == null ? '' : v).trim() !== ''; })) return;
                var o = { satir: bi + n + 2 };
                Object.keys(harita).forEach(function (k) { var v = r[harita[k]]; o[k] = v instanceof Date ? v : bosDeger(v); });
                var ad = adDuz(o.ad), soyad = adDuz(o.soyad);
                o.ad_soyad = (o.ad_soyad != null && o.ad_soyad !== '') ? adDuz(o.ad_soyad) : ((ad + ' ' + soyad).trim());
                ['ise_giris', 'dogum_tarihi', 'pozisyon_baslama', 'isten_cikis'].forEach(function (k) { if (o[k] != null) o[k] = tarihDuz(o[k]); });
                if (o.tc_kimlik) o.tc_kimlik = String(o.tc_kimlik).replace(/\.0+$/, '').replace(/\D/g, '');
                if (o.tc_kimlik && o.tc_kimlik.length === 10) o.tc_kimlik = '0' + o.tc_kimlik;
                delete o.ad; delete o.soyad; delete o.sirket; out.push(o);
            });
            return out;
        }
        function csvIndirAl(ad, satirlar) {
            var csv = '﻿' + satirlar.map(function (r) { return r.map(function (x) { return '"' + String(x == null ? '' : x).replace(/"/g, '""') + '"'; }).join(';'); }).join('\r\n');
            var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = ad; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
        }
        function iceAktar() {
            var m = modal({ baslik: 'Toplu içe aktar (CSV / Excel)', ikon: 'fa-file-import', gen: 760, sabit: true, govde:
                '<p class="ikm-soluk" style="margin:0 0 10px">Personelinizi tek seferde ekleyin: her satır bir kullanıcı ve personel kartı olur. Kullanıcı adı boşsa ad soyaddan üretilir; her kişiye <b>geçici şifre</b> verilir (ilk girişte değiştirir).</p>' +
                '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px"><button type="button" class="ikm-btn ikm-btn-kucuk" id="ia-sablon"><i class="fa-solid fa-download"></i> Şablonu indir (CSV)</button><small class="ikm-soluk" style="align-self:center">Excel (.xlsx) ya da CSV doğrudan yüklenebilir. Sütun başlıkları farklı olsa da tanınır; tanınmayanları aşağıdan kendiniz eşleştirirsiniz.</small></div>' +
                '<label class="ikm-dk" id="ia-dk"><input type="file" id="ia-dosya" accept=".csv,.txt,.xlsx,.xls"><span class="ikm-dk-ik"><i class="fa-solid fa-cloud-arrow-up"></i></span><span class="ikm-dk-y"><b>Dosyayı seçin veya buraya sürükleyin</b><small>.csv, .xlsx — henüz dosya seçilmedi</small></span></label>' +
                '<div id="ia-onizle" style="margin-top:14px"></div>', alt: '<button type="button" class="ikm-btn" data-kapat id="ia-kapat">Kapat</button><button type="button" class="ikm-btn ikm-btn-ana" id="ia-basla" disabled><i class="fa-solid fa-play"></i> İçe aktar</button>' });
            var dk = $('#ia-dk', m.el), inp = $('#ia-dosya', m.el), on = $('#ia-onizle', m.el), basla = $('#ia-basla', m.el), SATIR = [];
            $('#ia-sablon', m.el).onclick = function () { csvIndirAl('personel_sablon.csv', [IA_SUTUN.map(function (c) { return c[1]; }), ['Ayşe Yılmaz', '', 'personel', 'Muhasebe Uzmanı', '0532 000 00 00', 'ayse@ornek.com', 'Muhasebe', 'Uzman', '15.03.2022', '10.05.1990', '', '14', ''], ['Mehmet Demir', 'mehmet.demir', 'yetkili', 'İK Sorumlusu', '', '', 'İnsan Kaynakları', 'Sorumlu', '01.09.2021', '', '', '20', '']]); };
            ['dragenter', 'dragover'].forEach(function (e) { dk.addEventListener(e, function () { dk.classList.add('uz'); }); }); ['dragleave', 'drop'].forEach(function (e) { dk.addEventListener(e, function () { dk.classList.remove('uz'); }); });
            var TABLO = null, BI = 0, ESLE = [];
            function onizle() {
                SATIR = satirlariKur(TABLO, BI, ESLE);
                var baslik = TABLO[BI], haritali = ESLE.filter(Boolean).length;
                var adVar = ESLE.indexOf('ad_soyad') >= 0 || ESLE.indexOf('ad') >= 0;
                var secenek = function (sec) { return '<option value="">— kullanma —</option>' + IA_ALAN.map(function (a) { return '<option value="' + a[0] + '"' + (sec === a[0] ? ' selected' : '') + '>' + esc(a[1]) + '</option>'; }).join(''); };
                var esleme = '<details class="ikm-ia-esle"' + (adVar ? '' : ' open') + ' style="margin-bottom:10px"><summary style="cursor:pointer;font-weight:700"><i class="fa-solid fa-table-columns"></i> Sütun eşleştirme <small class="ikm-soluk">(' + haritali + ' sütun tanındı — yanlışsa değiştirin)</small></summary>' +
                    '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:8px;margin-top:10px">' + baslik.map(function (b, i) { return '<label class="ikm-alan" style="margin:0"><span style="font-size:12px">' + esc(String(b == null || b === '' ? 'Sütun ' + (i + 1) : b)) + '</span><select data-sutun="' + i + '">' + secenek(ESLE[i]) + '</select></label>'; }).join('') + '</div></details>';
                var hata = adVar ? '' : '<div class="ikm-hata-kutu" style="color:var(--ik-kirmizi,#ef4444);margin-bottom:8px"><i class="fa-solid fa-triangle-exclamation"></i> Ad bilgisi için bir sütun seçin (“Ad Soyad” ya da “Ad” + “Soyad”).</div>';
                var gorunen = SATIR.filter(function (o) { return o.ad_soyad; });
                on.innerHTML = esleme + hata + (adVar ? '<div class="ikm-not" style="margin-bottom:10px"><i class="fa-solid fa-circle-check"></i> <b>' + SATIR.length + '</b> satır okundu.</div>' +
                    '<div class="ikm-tablo-kap ikm-ia-sonuc"><table class="ikm-tablo"><thead><tr><th>#</th><th>Ad soyad</th><th>T.C.</th><th>Departman (Birim)</th><th>Unvan</th><th>İşe giriş</th><th>Durum</th></tr></thead><tbody>' +
                    SATIR.slice(0, 8).map(function (o) { return '<tr><td>' + o.satir + '</td><td>' + esc(o.ad_soyad) + '</td><td>' + esc(o.tc_kimlik ? o.tc_kimlik.slice(0, 3) + '••••••' + o.tc_kimlik.slice(-2) : '—') + '</td><td>' + esc(o.departman || '—') + '</td><td>' + esc(o.unvan || '—') + '</td><td>' + esc(o.ise_giris || '—') + '</td><td>' + esc(o.durum || (o.isten_cikis ? 'Ayrıldı' : 'Aktif')) + '</td></tr>'; }).join('') + '</tbody></table></div>' + (SATIR.length > 8 ? '<small class="ikm-soluk">… ve ' + (SATIR.length - 8) + ' satır daha</small>' : '') : '');
                basla.disabled = !adVar || !SATIR.length;
                if (!gorunen.length && adVar) basla.disabled = true;
            }
            on.addEventListener('change', function (e) {
                var sl = e.target.closest && e.target.closest('select[data-sutun]'); if (!sl || !TABLO) return;
                var i = +sl.dataset.sutun, v = sl.value;
                if (v) ESLE = ESLE.map(function (k, j) { return j !== i && k === v ? '' : k; });   // bir alan tek sütuna eşlenir
                ESLE[i] = v; onizle();
            });
            inp.addEventListener('change', function () {
                var f = inp.files[0]; if (!f) return; $('b', dk).textContent = f.name; $('small', dk).textContent = Math.max(1, Math.round(f.size / 1024)) + ' KB'; dk.classList.add('dolu'); basla.disabled = true; on.innerHTML = '<div class="ikm-soluk">Okunuyor…</div>';
                dosyaOku(f).then(function (t) {
                    t = t.filter(function (r) { return r.some(function (v) { return String(v == null ? '' : v).trim() !== ''; }); });
                    if (t.length < 2) throw new Error('Dosyada başlık satırı ve en az bir veri satırı olmalı.');
                    TABLO = t; BI = baslikSatiri(t);
                    var gen = Math.max.apply(null, t.map(function (r) { return r.length; })); TABLO = t.map(function (r) { var c = r.slice(); while (c.length < gen) c.push(''); return c; });
                    ESLE = eslemeBul(TABLO[BI].map(function (x) { return String(x == null ? '' : x); }));
                    if (TABLO.length - BI - 1 > 1000) throw new Error('Tek seferde en fazla 1000 satır aktarılabilir.');
                    onizle();
                }).catch(function (e) { SATIR = []; TABLO = null; on.innerHTML = '<div class="ikm-hata-kutu" style="color:var(--ik-kirmizi,#ef4444)"><i class="fa-solid fa-triangle-exclamation"></i> ' + esc(e.message) + '</div>'; });
            });
            basla.onclick = function () {
                if (!SATIR.length) return; basla.disabled = true; inp.disabled = true; $('#ia-kapat', m.el).disabled = true;
                on.innerHTML = '<div class="ikm-ia-bar"><i id="ia-cubuk"></i></div><div id="ia-durum" class="ikm-soluk" style="margin-top:6px">Aktarılıyor… 0 / ' + SATIR.length + '</div>';
                var sonuc = [], i = 0, GRUP = 25;
                (function sonraki() {
                    if (i >= SATIR.length) return bitir(sonuc);
                    var parca = SATIR.slice(i, i + GRUP);
                    gonder('kullanici_ice_aktar', { satirlar: JSON.stringify(parca) }).then(function (j) {
                        sonuc = sonuc.concat(j.sonuc); i += GRUP; var n = Math.min(i, SATIR.length);
                        $('#ia-cubuk', m.el).style.width = Math.round(n * 100 / SATIR.length) + '%'; $('#ia-durum', m.el).textContent = 'Aktarılıyor… ' + n + ' / ' + SATIR.length; sonraki();
                    }).catch(function (e) { bildir(e.message, 'hata'); parca.forEach(function (o) { sonuc.push({ satir: o.satir, ad_soyad: o.ad_soyad, ok: false, hata: e.message }); }); i += GRUP; sonraki(); });
                })();
            };
            function yoneticiEsle(sonuc, devam) {
                var L = []; sonuc.forEach(function (x) { if (x.ok && x.id) { var sat = SATIR.filter(function (r) { return r.satir === x.satir; })[0]; if (sat && sat.yonetici) L.push({ id: x.id, yonetici: sat.yonetici }); } });
                if (!L.length) return devam(0);
                var n = 0, k = 0; (function g() { if (k >= L.length) return devam(n); gonder('kullanici_yonetici_esle', { liste: JSON.stringify(L.slice(k, k + 60)) }).then(function (j) { n += j.eslesen || 0; k += 60; g(); }).catch(function () { k += 60; g(); }); })();
            }
            function bitir(sonuc) {
                yoneticiEsle(sonuc, function (yn) { bitir2(sonuc, yn); });
            }
            function bitir2(sonuc, yn) {
                var ok = sonuc.filter(function (x) { return x.ok && !x.guncellendi; }), gun = sonuc.filter(function (x) { return x.ok && x.guncellendi; }).length, hata = sonuc.length - ok.length - gun;
                on.innerHTML = '<div class="ikm-not" style="margin-bottom:10px"><i class="fa-solid fa-circle-check"></i> <b>' + ok.length + '</b> kişi eklendi' + (gun ? ', <b>' + gun + '</b> mevcut kişinin birim/unvan gibi bilgileri güncellendi' : '') + (yn ? ', ' + yn + ' kişinin yöneticisi eşlendi' : '') + (hata ? ', <b>' + hata + '</b> satır atlandı' : '') + '. <b>Geçici şifreler yalnızca şimdi gösterilir</b> — listeyi indirip güvenli bir yerde saklayın.</div>' +
                    '<div class="ikm-tablo-kap ikm-ia-sonuc"><table class="ikm-tablo"><thead><tr><th>#</th><th>Ad soyad</th><th>Kullanıcı adı</th><th>Geçici şifre / hata</th></tr></thead><tbody>' +
                    sonuc.map(function (x) { return '<tr><td>' + x.satir + '</td><td>' + esc(x.ad_soyad) + '</td><td>' + esc(x.kullanici_adi || '—') + '</td><td>' + (x.ok ? (x.guncellendi ? '<span class="ikm-soluk">Mevcut kişi — bilgileri güncellendi</span>' : '<code>' + esc(x.sifre) + '</code>') : '<span style="color:var(--ik-kirmizi,#ef4444)">' + esc(x.hata || 'Hata') + '</span>') + '</td></tr>'; }).join('') + '</tbody></table></div>';
                var url = location.origin + location.pathname.replace(/[^/]*$/, '') + 'giris?kurum=' + encodeURIComponent((window.TB.kurum || {}).kod || '');
                basla.innerHTML = '<i class="fa-solid fa-download"></i> Şifre listesini indir'; basla.disabled = false; basla.onclick = function () { csvIndirAl('yeni_kullanicilar.csv', [['Satır', 'Ad Soyad', 'Kullanıcı adı', 'Geçici şifre', 'Giriş adresi', 'Durum']].concat(sonuc.map(function (x) { return [x.satir, x.ad_soyad, x.kullanici_adi || '', x.ok ? (x.sifre || '') : '', x.ok ? url : '', x.ok ? (x.guncellendi ? 'Güncellendi' : 'Eklendi') : (x.hata || 'Hata')]; }))); };
                $('#ia-kapat', m.el).disabled = false; $('#ia-kapat', m.el).textContent = 'Kapat'; m.kapat0 = m.kapat; yukle();
            }
        }
        /** "İzin Raporları" Excel'i → geçmiş izinleri onaylı olarak ekler (takvimde ve bakiyede görünür) */
        function izinAktar() {
            var m = modal({ baslik: 'İzin verisini içe aktar (Excel)', ikon: 'fa-calendar-check', gen: 720, sabit: true, govde:
                '<p class="ikm-soluk" style="margin:0 0 10px"><b>İzin Raporları</b> (kişi başı geçmiş izinler) ya da <b>Çalışan İzin Bakiye Raporu</b> (devreden izin, hak ediş dönemi, güncel bakiye; tüm sütunlar kişiye işlenir) dosyasını yükleyin. Dosya türü otomatik anlaşılır. Kişiler <b>ad soyada göre</b> eşleşir; izinler <b>onaylı</b> eklenir, izin takvimine ve yıllık izin bakiyesine yansır. Aynı kayıt tekrar yüklenirse çift eklenmez.</p>' +
                '<label class="ikm-dk" id="iz-dk"><input type="file" id="iz-dosya" accept=".csv,.txt,.xlsx,.xls"><span class="ikm-dk-ik"><i class="fa-solid fa-cloud-arrow-up"></i></span><span class="ikm-dk-y"><b>Dosyayı seçin veya buraya sürükleyin</b><small>.xlsx, .csv — henüz dosya seçilmedi</small></span></label>' +
                '<div id="iz-onizle" style="margin-top:14px"></div>', alt: '<button type="button" class="ikm-btn" data-kapat id="iz-kapat">Kapat</button><button type="button" class="ikm-btn ikm-btn-ana" id="iz-basla" disabled><i class="fa-solid fa-play"></i> İçe aktar</button>' });
            var dk = $('#iz-dk', m.el), inp = $('#iz-dosya', m.el), on = $('#iz-onizle', m.el), basla = $('#iz-basla', m.el), SATIR = [], MOD = 'izin', RAPOR = '';
            var nz = function (t) { return String(t == null ? '' : t).toLocaleLowerCase('tr').replace(/ı/g, 'i').replace(/[ç]/g, 'c').replace(/[ğ]/g, 'g').replace(/[ö]/g, 'o').replace(/[ş]/g, 's').replace(/[ü]/g, 'u').replace(/[^a-z0-9 ]/g, '').trim(); };
            function tarihMetin(v) { if (v instanceof Date) { var d = new Date(v.getTime() + 12 * 3600e3); return ('0' + d.getUTCDate()).slice(-2) + '.' + ('0' + (d.getUTCMonth() + 1)).slice(-2) + '.' + d.getUTCFullYear(); } return String(v == null ? '' : v).trim(); }
            ['dragenter', 'dragover'].forEach(function (e) { dk.addEventListener(e, function () { dk.classList.add('uz'); }); }); ['dragleave', 'drop'].forEach(function (e) { dk.addEventListener(e, function () { dk.classList.remove('uz'); }); });
            inp.addEventListener('change', function () {
                var f = inp.files[0]; if (!f) return; $('b', dk).textContent = f.name; $('small', dk).textContent = Math.max(1, Math.round(f.size / 1024)) + ' KB'; dk.classList.add('dolu'); basla.disabled = true; on.innerHTML = '<div class="ikm-soluk">Okunuyor…</div>';
                dosyaOku(f).then(function (t) {
                    var bb = -1, hh;
                    for (var q = 0; q < Math.min(t.length, 15); q++) { hh = t[q].map(nz); if (hh.indexOf('ad') >= 0 && hh.indexOf('soyad') >= 0 && hh.some(function (x) { return x.indexOf('devreden') >= 0 || x.indexOf('guncel bakiye') >= 0; })) { bb = q; break; } }
                    if (bb >= 0) {
                        MOD = 'bakiye'; var bas = t[bb].map(function (x) { return String(x == null ? '' : x).trim(); }), adI = bas.map(nz).indexOf('ad'); SATIR = [];
                        t.slice(bb + 1).forEach(function (r) { if (!r[adI]) return; var o = {}; bas.forEach(function (hd, k) { if (hd) o[hd] = r[k] == null ? '' : (r[k] instanceof Date ? tarihMetin(r[k]) : String(r[k]).trim()); }); SATIR.push(o); });
                        if (!SATIR.length) throw new Error('Aktarılacak kişi satırı bulunamadı.');
                        var tm = f.name.match(/(\d{2})\.(\d{2})\.(\d{4})/g); var tt = tm ? tm[tm.length - 1].split('.') : null; RAPOR = tt ? tt[2] + '-' + tt[1] + '-' + tt[0] : new Date().toISOString().slice(0, 10);
                        on.innerHTML = '<div class="ikm-not"><i class="fa-solid fa-circle-check"></i> <b>Bakiye raporu</b>: <b>' + SATIR.length + '</b> kişi, <b>' + bas.filter(Boolean).length + '</b> sütun okundu. Ad-soyad ve T.C. kimlikle eşleşir; birim, unvan, işe giriş, devreden izin ve dönem bilgileri kişiye işlenir, tüm sütunlar kişinin İzinler sekmesinde saklanır.</div>' +
                            '<label class="ikm-alan" style="margin-top:10px"><span>Rapor tarihi <small>bu tarihten sonraki izinler bakiyeden düşülür</small></span><input type="date" id="iz-rt" value="' + RAPOR + '"></label>'; basla.disabled = false; return;
                    }
                    MOD = 'izin';
                    var bi = -1; for (var i = 0; i < Math.min(t.length, 15); i++) { var h = t[i].map(nz); if (h.indexOf('ad') >= 0 && h.some(function (x) { return x.indexOf('baslangic') >= 0; })) { bi = i; break; } }
                    if (bi < 0) throw new Error('Başlık satırı bulunamadı. Dosyada “Ad”, “Soyad”, “İzin Başlangıç Tarihi”, “İzin Bitiş Tarihi”, “Kullanılan Gün”, “İzin Türü” sütunları olmalı.');
                    var h = t[bi].map(nz), ix = function (f2) { for (var k = 0; k < h.length; k++) if (f2(h[k])) return k; return -1; };
                    var c = { ad: ix(function (x) { return x === 'ad'; }), soyad: ix(function (x) { return x === 'soyad'; }), bas: ix(function (x) { return x.indexOf('baslangic') >= 0; }), bit: ix(function (x) { return x.indexOf('bitis') >= 0; }), gun: ix(function (x) { return x.indexOf('kullanilan') >= 0 || x === 'gun'; }), tur: ix(function (x) { return x.indexOf('izin turu') >= 0 || x === 'tur'; }) };
                    if (c.ad < 0 || c.bas < 0 || c.bit < 0) throw new Error('Ad ve izin tarih sütunları tanınamadı.');
                    SATIR = []; t.slice(bi + 1).forEach(function (r, n) { if (!r[c.ad] && !(c.soyad >= 0 && r[c.soyad])) return; SATIR.push({ satir: bi + n + 2, ad: String(r[c.ad] || '').trim(), soyad: c.soyad >= 0 ? String(r[c.soyad] || '').trim() : '', baslangic: tarihMetin(r[c.bas]), bitis: tarihMetin(r[c.bit]), gun: c.gun >= 0 ? r[c.gun] : '', tur: c.tur >= 0 ? String(r[c.tur] || '') : '' }); });
                    SATIR = SATIR.filter(function (o) { return o.baslangic && o.bitis; });
                    if (!SATIR.length) throw new Error('Aktarılacak izin satırı bulunamadı.');
                    if (SATIR.length > 5000) throw new Error('Tek seferde en fazla 5000 satır aktarılabilir.');
                    var kisi = {}; SATIR.forEach(function (o) { kisi[nz(o.ad + ' ' + o.soyad)] = 1; });
                    on.innerHTML = '<div class="ikm-not"><i class="fa-solid fa-circle-check"></i> <b>' + SATIR.length + '</b> izin satırı, <b>' + Object.keys(kisi).length + '</b> kişi okundu. “İçe aktar” ile eklenir.</div>'; basla.disabled = false;
                }).catch(function (e) { SATIR = []; on.innerHTML = '<div class="ikm-hata-kutu" style="color:var(--ik-kirmizi,#ef4444)"><i class="fa-solid fa-triangle-exclamation"></i> ' + esc(e.message) + '</div>'; });
            });
            basla.onclick = function () {
                if (!SATIR.length) return; basla.disabled = true; inp.disabled = true; $('#iz-kapat', m.el).disabled = true;
                on.innerHTML = '<div class="ikm-ia-bar"><i id="iz-cubuk"></i></div><div id="iz-durum" class="ikm-soluk" style="margin-top:6px">Aktarılıyor… 0 / ' + SATIR.length + '</div>';
                var sonuc = [], i = 0, GRUP = MOD === 'bakiye' ? 25 : 80, rt = ($('#iz-rt', m.el) || {}).value || RAPOR;
                (function sonraki() {
                    if (i >= SATIR.length) return bitir();
                    var parca = SATIR.slice(i, i + GRUP);
                    gonder(MOD === 'bakiye' ? 'izin_bakiye_aktar' : 'izin_ice_aktar', { satirlar: JSON.stringify(parca), rapor_tarihi: rt }).then(function (j) { sonuc = sonuc.concat(j.sonuc); }).catch(function (e) { bildir(e.message, 'hata'); parca.forEach(function (o) { sonuc.push({ satir: o.satir, ad_soyad: (o.ad || o['Ad'] || '') + ' ' + (o.soyad || o['Soyad'] || ''), ok: false, hata: e.message }); }); }).then(function () {
                        i += GRUP; var n = Math.min(i, SATIR.length); $('#iz-cubuk', m.el).style.width = Math.round(n * 100 / SATIR.length) + '%'; $('#iz-durum', m.el).textContent = 'Aktarılıyor… ' + n + ' / ' + SATIR.length; sonraki();
                    });
                })();
                function bitir() {
                    var ok = sonuc.filter(function (x) { return x.ok && !x.tekrar; }).length, tk = sonuc.filter(function (x) { return x.tekrar; }).length, hata = sonuc.filter(function (x) { return !x.ok; });
                    var yok = {}; hata.forEach(function (x) { if ((x.hata || '').indexOf('bulunamadı') >= 0) yok[x.ad_soyad] = (yok[x.ad_soyad] || 0) + 1; });
                    var yokL = Object.keys(yok), diger = hata.filter(function (x) { return (x.hata || '').indexOf('bulunamadı') < 0; });
                    on.innerHTML = '<div class="ikm-not" style="margin-bottom:10px"><i class="fa-solid fa-circle-check"></i> <b>' + ok + '</b> ' + (MOD === 'bakiye' ? 'kişinin izin bakiyesi ve kişi bilgileri güncellendi' : 'izin eklendi') + (tk ? ', <b>' + tk + '</b> kayıt zaten vardı (atlandı)' : '') + (hata.length ? ', <b>' + hata.length + '</b> satır eklenemedi' : '') + '.</div>' +
                        (yokL.length ? '<details open style="margin-bottom:8px"><summary style="cursor:pointer;font-weight:700">Kullanıcılar arasında bulunamayan kişiler (' + yokL.length + ')</summary><div class="ikm-soluk" style="margin-top:6px">' + yokL.map(function (a) { return esc(a) + ' (' + yok[a] + ')'; }).join(' · ') + '<br>Önce Personel sayfasındaki içe aktarmayla bu kişileri ekleyip dosyayı yeniden yükleyebilirsiniz; ekli kayıtlar çift oluşmaz.</div></details>' : '') +
                        (diger.length ? '<details><summary style="cursor:pointer;font-weight:700">Diğer hatalar (' + diger.length + ')</summary><div class="ikm-soluk" style="margin-top:6px">' + diger.slice(0, 40).map(function (x) { return 'Satır ' + x.satir + ': ' + esc(x.hata); }).join('<br>') + '</div></details>' : '');
                    $('#iz-kapat', m.el).disabled = false; basla.style.display = 'none';
                }
            };
        }
        /** "Personel Bilgi Formu" Word dosyaları (.docx ya da .zip) → kişi kartlarını doldurur */
        function formAktar() {
            var m = modal({ baslik: 'Personel bilgi formlarını aktar (Word)', ikon: 'fa-file-word', gen: 760, sabit: true, govde:
                '<p class="ikm-soluk" style="margin:0 0 10px">“Personel Bilgi Formu” Word dosyalarını (<b>.docx</b>) toplu seçin ya da <b>.zip</b> olarak yükleyin. Kişi <b>T.C. kimlik</b>, olmazsa <b>ad soyada</b> göre eşleşir; kimlik, doğum, iletişim, adres, acil durum, eğitim, askerlik, anne/baba adı, IBAN, birim/unvan, SGK başlangıcı kartlara işlenir; formun tamamı kişinin kartında saklanır. <b>RAR</b> arşivini önce bilgisayarınızda açın (ya da ZIP’leyin).</p>' +
                '<label class="ikm-alan"><span>Dosyalar</span><input type="file" id="fa-dosya" accept=".docx,.zip" multiple></label>' +
                '<label class="ikm-onay-kutu" style="margin-top:8px"><input type="checkbox" id="fa-ezme"> <span>Kartta dolu olan bilgilerin üzerine de yaz <small>(kapalıysa yalnızca boş alanlar doldurulur)</small></span></label>' +
                '<div id="fa-ozet" class="ikm-soluk" style="margin-top:10px"></div><div id="fa-onizle" style="margin-top:10px"></div>',
                alt: '<button type="button" class="ikm-btn" id="fa-kapat">Kapat</button><button type="button" class="ikm-btn ikm-btn-ana" id="fa-basla" disabled><i class="fa-solid fa-file-import"></i> Aktarmayı başlat</button>' });
            var dosya = $('#fa-dosya', m.el), basla = $('#fa-basla', m.el), on = $('#fa-onizle', m.el), ozet = $('#fa-ozet', m.el);
            $('#fa-kapat', m.el).onclick = function () { m.kapat(); };
            dosya.onchange = function () { var n = dosya.files.length, b = 0; Array.prototype.forEach.call(dosya.files, function (f) { b += f.size; }); ozet.textContent = n ? n + ' dosya seçildi (' + (b / 1048576).toFixed(1).replace('.', ',') + ' MB).' : ''; basla.disabled = !n; };
            basla.onclick = function () {
                var TUM = Array.prototype.slice.call(dosya.files), L = TUM.filter(function (f) { return /\.(docx|zip)$/i.test(f.name); }), gruplar = [], g = [], b = 0, atlanan = TUM.filter(function (f) { return !/\.(docx|zip)$/i.test(f.name); });
                L.forEach(function (f) { if (g.length && (g.length >= 6 || b + f.size > 6 * 1048576)) { gruplar.push(g); g = []; b = 0; } g.push(f); b += f.size; }); if (g.length) gruplar.push(g);
                basla.disabled = true; dosya.disabled = true; $('#fa-kapat', m.el).disabled = true; var sonuc = atlanan.map(function (f) { return { dosya: f.name, ok: false, hata: 'Word (.docx) değil; taranmış resim/PDF okunamaz.', uyari: [] }; }), bitenSay = 0;
                var A = M.istek('ik_islem.php'), ezme = $('#fa-ezme', m.el).checked ? '1' : '';
                (function sira(i) {
                    if (i >= gruplar.length) return bitir();
                    ozet.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + bitenSay + ' / ' + L.length + ' dosya işlendi…';
                    var fd = new FormData(); fd.append('islem', 'personel_form_aktar'); if (ezme) fd.append('uzerine_yaz', ezme);
                    gruplar[i].forEach(function (f) { fd.append('dosyalar[]', f, f.name); });
                    A.form(fd).then(function (j) { sonuc = sonuc.concat(j.sonuc); }).catch(function (e) { bildir(e.message, 'hata'); gruplar[i].forEach(function (f) { sonuc.push({ dosya: f.name, ok: false, hata: e.message, uyari: [] }); }); }).then(function () { bitenSay += gruplar[i].length; sira(i + 1); });
                })(0);
                var ET = { tc_kimlik: 'T.C.', dogum_yeri: 'doğum yeri', dogum_tarihi: 'doğum tarihi', askerlik: 'askerlik', anne_adi: 'anne adı', baba_adi: 'baba adı', medeni_durum: 'medeni durum', kan_grubu: 'kan grubu', adres: 'adres', acil_kisi: 'acil kişi', acil_yakinlik: 'acil yakınlık', acil_telefon: 'acil telefon', egitim_durumu: 'eğitim', okul: 'okul', iban: 'IBAN', banka: 'banka', departman: 'birim', unvan: 'unvan', email2: 'şahsi e-posta', telefon2: 'şahsi telefon', email: 'e-posta', telefon: 'telefon', ise_giris: 'işe giriş', sgk_baslangic: 'SGK başlangıç', cocuk_sayisi: 'çocuk sayısı' };
                function bitir() {
                    var ok = sonuc.filter(function (x) { return x.ok; }), yok = sonuc.filter(function (x) { return x.bulunamadi; }), diger = sonuc.filter(function (x) { return !x.ok && !x.bulunamadi; }), uy = sonuc.filter(function (x) { return x.ok && x.uyari && x.uyari.length; });
                    ozet.textContent = '';
                    on.innerHTML = '<div class="ikm-not" style="margin-bottom:10px"><i class="fa-solid fa-circle-check"></i> <b>' + ok.length + '</b> kişinin kartı güncellendi' + (yok.length ? ', <b>' + yok.length + '</b> kişi bulunamadı' : '') + (diger.length ? ', <b>' + diger.length + '</b> dosya okunamadı' : '') + '.</div>' +
                        (ok.length ? '<details open style="margin-bottom:8px"><summary style="cursor:pointer;font-weight:700">Güncellenenler (' + ok.length + ')</summary><div class="ikm-soluk" style="margin-top:6px;max-height:220px;overflow:auto">' + ok.map(function (x) { return '<b>' + esc(x.ad_soyad) + '</b>: ' + ((x.dolan || []).length ? esc((x.dolan || []).map(function (a) { return ET[a] || a; }).join(', ')) : 'yeni bilgi yok (alanlar zaten doluydu)'); }).join('<br>') + '</div></details>' : '') +
                        (yok.length ? '<details open style="margin-bottom:8px"><summary style="cursor:pointer;font-weight:700">Kullanıcılar arasında bulunamayanlar (' + yok.length + ')</summary><div class="ikm-soluk" style="margin-top:6px">' + yok.map(function (x) { return esc(x.ad_soyad || x.dosya); }).join(' · ') + '<br>Önce “İçe aktar” ile bu kişileri ekleyin, sonra formları yeniden yükleyin.</div></details>' : '') +
                        (uy.length ? '<details style="margin-bottom:8px"><summary style="cursor:pointer;font-weight:700">Uyarılar (' + uy.length + ')</summary><div class="ikm-soluk" style="margin-top:6px">' + uy.map(function (x) { return esc(x.ad_soyad) + ': ' + esc(x.uyari.join('; ')); }).join('<br>') + '</div></details>' : '') +
                        (diger.length ? '<details open><summary style="cursor:pointer;font-weight:700">Okunamayan dosyalar (' + diger.length + ')</summary><div class="ikm-soluk" style="margin-top:6px">' + diger.map(function (x) { return esc(x.dosya) + ': ' + esc(x.hata); }).join('<br>') + '</div></details>' : '');
                    $('#fa-kapat', m.el).disabled = false; basla.style.display = 'none';
                    if (window.IKM && IKM.yenileVeri) IKM.yenileVeri();
                }
            };
        }
        setInterval(function () {
            var e4 = document.getElementById('ikm-ust-sag-ek4'), e5 = document.getElementById('ikm-ust-sag-ek5');
            if (e4 && !e5) { var b = document.createElement('button'); b.type = 'button'; b.className = 'ikm-btn'; b.id = 'ikm-ust-sag-ek5'; b.innerHTML = '<i class="fa-solid fa-file-word"></i> <span>Bilgi formu aktar</span>'; b.onclick = formAktar; e4.parentNode.insertBefore(b, e4.nextSibling); }
            else if (!e4 && e5) e5.remove();
        }, 400);
        function disaAktar() {
            var b = this; var eski = b.innerHTML; b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> <span>Hazırlanıyor…</span>';
            var bitir = function () { b.disabled = false; b.innerHTML = eski; };
            Promise.all([getir({ islem: 'personel_disa_aktar' }), xlsxYukle().then(function () { return true; }, function () { return false; })]).then(function (r) {
                var j = r[0], aoa = [j.baslik].concat(j.satirlar), ay = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'], d = new Date();
                var ad = 'Calisan_Listesi_' + ay[d.getMonth()].replace(/ş/g, 's').replace(/ü/g, 'u').replace(/ı/g, 'i').replace(/ğ/g, 'g') + '_' + d.getFullYear();
                if (r[1] && window.XLSX) {
                    var ws = XLSX.utils.aoa_to_sheet(aoa); ws['!cols'] = [16, 16, 16, 18, 22, 18, 14, 20, 38, 30, 16].map(function (w) { return { wch: w }; }); ws['!freeze'] = { xSplit: 0, ySplit: 1 };
                    var wb = XLSX.utils.book_new(); XLSX.utils.book_append_sheet(wb, ws, 'Çalışanlar'); XLSX.writeFile(wb, ad + '.xlsx');
                } else csvIndirAl(ad + '.csv', aoa);
                bildir(j.satirlar.length + ' kişi dışa aktarıldı.', 'basari'); bitir();
            }).catch(function (e) { bildir(e.message, 'hata'); bitir(); });
        }
        function yukle() { return getir({ islem: 'kullanicilar' }).then(function (j) { D = j; ciz(); }); }
        M.kayit('kullanicilar', 'Kullanıcılar', 'Kurumunuzdaki hesapları ekleyin, rol verin, şifre sıfırlayın.', function () {
            return yukle().then(function () {
                var ust = document.querySelector('.ikm-ust-sag'); if (!ust) return;
                var b = document.createElement('button'); b.type = 'button'; b.className = 'ikm-btn ikm-btn-ana'; b.id = 'ikm-ust-sag-ek'; b.innerHTML = '<i class="fa-solid fa-user-plus"></i> <span>Kullanıcı ekle</span>'; b.onclick = function () { form(null); };
                var b2 = document.createElement('button'); b2.type = 'button'; b2.className = 'ikm-btn'; b2.id = 'ikm-ust-sag-ek2'; b2.innerHTML = '<i class="fa-solid fa-file-import"></i> <span>İçe aktar</span>'; b2.onclick = iceAktar; ust.insertBefore(b2, ust.firstChild);
                var b3 = document.createElement('button'); b3.type = 'button'; b3.className = 'ikm-btn'; b3.id = 'ikm-ust-sag-ek3'; b3.innerHTML = '<i class="fa-solid fa-file-export"></i> <span>Dışa aktar</span>'; b3.onclick = disaAktar; ust.insertBefore(b3, b2);
                var b4 = document.createElement('button'); b4.type = 'button'; b4.className = 'ikm-btn'; b4.id = 'ikm-ust-sag-ek4'; b4.innerHTML = '<i class="fa-solid fa-calendar-check"></i> <span>İzin aktar</span>'; b4.onclick = izinAktar; ust.insertBefore(b4, ust.firstChild);
                ust.insertBefore(b, ust.firstChild);
            }).catch(function (e) { icerik.innerHTML = M.hataKutu(e.message); });
        });
        // Sekme değişince eklenen üst düğmeyi kaldır
        M.sekmeDegisiyor = (function (onceki) { return function (eski, yeni) { if (yeni === 'personel') setTimeout(function () { var u = document.querySelector('.ikm-ust-sag'); if (!u || document.getElementById('ikm-ust-sag-ek2') || document.getElementById('ikm-ust-sag-ek3')) return; var q4 = document.createElement('button'); q4.type = 'button'; q4.className = 'ikm-btn'; q4.id = 'ikm-ust-sag-ek4'; q4.innerHTML = '<i class="fa-solid fa-calendar-check"></i> <span>İzin aktar</span>'; q4.onclick = izinAktar; u.insertBefore(q4, u.firstChild); var q = document.createElement('button'); q.type = 'button'; q.className = 'ikm-btn'; q.id = 'ikm-ust-sag-ek2'; q.innerHTML = '<i class="fa-solid fa-file-import"></i> <span>İçe aktar</span>'; q.onclick = iceAktar; u.insertBefore(q, u.firstChild); var q3 = document.createElement('button'); q3.type = 'button'; q3.className = 'ikm-btn'; q3.id = 'ikm-ust-sag-ek3'; q3.innerHTML = '<i class="fa-solid fa-file-export"></i> <span>Dışa aktar</span>'; q3.onclick = disaAktar; u.insertBefore(q3, q); }, 450); var e = document.getElementById('ikm-ust-sag-ek'); if (e) e.remove(); var e2 = document.getElementById('ikm-ust-sag-ek2'); if (e2) e2.remove(); var e3 = document.getElementById('ikm-ust-sag-ek3'); if (e3) e3.remove(); var e4 = document.getElementById('ikm-ust-sag-ek4'); if (e4) e4.remove(); if (onceki) onceki(eski, yeni); }; })(M.sekmeDegisiyor);

        /* ── Hesap: şifre değiştir / çıkış / tema ── */
        var sf = document.getElementById('ik-sifre');
        if (sf) sf.onclick = function () {
            var m = modal({ baslik: 'Şifremi değiştir', ikon: 'fa-key', gen: 440, govde: '<form class="ikm-form" id="ks-f" autocomplete="off"><label class="ikm-alan"><span>Mevcut şifre</span><input type="password" name="eski" autocomplete="current-password"></label><label class="ikm-alan"><span>Yeni şifre <small>(en az 8 karakter, harf ve rakam)</small></span><input type="password" name="yeni" autocomplete="new-password"></label></form>', alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="ks-k">Kaydet</button>' });
            $('#ks-k', m.el).onclick = function () {
                var f = $('#ks-f', m.el), fd = new FormData(); fd.append('islem', 'sifre_degistir'); fd.append('eski', f.elements.eski.value); fd.append('yeni', f.elements.yeni.value); fd.append('_csrf', window.TB.csrf);
                fetch('oturum_islem.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': window.TB.csrf } }).then(function (r) { return r.json(); }).then(function (j) { if (!j.ok) throw new Error(j.hata || 'Kaydedilemedi'); m.kapat(); bildir('Şifreniz değiştirildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); });
            };
        };
        var tm = document.getElementById('ik-tema');
        if (tm) tm.onclick = function () { var d = document.documentElement, mv = d.getAttribute('data-tema') || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'); window.tbTema(mv === 'dark' ? 'light' : 'dark'); };
    }
    basla();
})();
