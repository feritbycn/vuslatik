/* ==========================================================================
   tb-yetki-panel.js — Sayfa içi "Yetkiler" penceresi (İş Takip, İK …)
   Kullanım: TbYetkiPanel.ac('istakip')   (yalnızca kurucuya gösterilir)
   Her hedef (herkes / rol / görev / kişi) için her yetki: Devral → İzin ver → Engelle
   ========================================================================== */
(function () {
    'use strict';
    if (window.TbYetkiPanel) return;
    var API = 'tb_yetki_islem.php';
    var TIP = { herkes: 'Herkes', rol: 'Rol', gorev: 'Görev', birim: 'Birim', unvan: 'Unvan', kullanici: 'Kişi' };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function bildir(m, t) { if (window.tbBildir) window.tbBildir(m, t || 'bilgi'); else alert(m); }
    function onayla(o) { return window.tbOnayla ? window.tbOnayla(o) : Promise.resolve(confirm(o.mesaj)); }
    function csrf() { return (window.TB && window.TB.csrf) || ''; }
    function jsonOku(r) { return r.json().catch(function () { return { ok: false, hata: 'Sunucu yanıtı okunamadı.' }; }).then(function (j) { if (!j.ok) throw new Error(j.hata || 'Hata'); return j; }); }
    function getir(p) { return fetch(API + '?' + new URLSearchParams(p), { credentials: 'same-origin', cache: 'no-store' }).then(jsonOku); }
    function gonder(p) {
        var fd = new FormData(); Object.keys(p).forEach(function (k) { fd.append(k, p[k]); }); fd.append('_csrf', csrf());
        var qp = new URLSearchParams(); Object.keys(p).forEach(function (k) { qp.set(k, p[k]); }); qp.set('_csrf', csrf());
        return fetch(API + '?' + qp.toString(), { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf() } }).then(jsonOku);
    }

    function ac(modul) {
        document.querySelectorAll('.vyp-arka').forEach(function (eski) { eski.remove(); });
        var arka = document.createElement('div');
        arka.className = 'vyp-arka';
        arka.innerHTML = '<div class="vyp" role="dialog" aria-modal="true">' +
            '<div class="vyp-ust"><h3><i class="fa-solid fa-user-shield"></i> <span class="vyp-baslik">Yetkiler</span></h3><button type="button" class="vyp-kapat" aria-label="Kapat">✕</button></div>' +
            '<div class="vyp-govde">' +
            '<p class="vyp-not">Kural yoksa <b>varsayılan</b> geçerlidir. Birden çok kural uyarsa öncelik: <b>Kişi › Unvan › Birim › Görev › Rol › Herkes</b>. ' +
            'Bir yetkiye tıklayarak <span class="vyp-ornek d">— devral</span> <span class="vyp-ornek i">✓ izin ver</span> <span class="vyp-ornek e">✕ engelle</span> arasında geçin; değişiklik anında kaydedilir.</p>' +
            '<div class="vyp-ekle"><select class="vyp-tip"><option value="kullanici">Kişi</option><option value="birim">Birim (departman)</option><option value="unvan">Unvan</option><option value="gorev">Görev</option><option value="rol">Rol</option><option value="herkes">Herkes</option></select>' +
            '<div class="vyp-aramali"><input type="search" class="vyp-deger-ara" placeholder="Kişi / birim / unvan / rol ara…"><select class="vyp-deger"></select></div><button type="button" class="vyp-btn vyp-btn-ana vyp-ekle-btn"><i class="fa-solid fa-plus"></i> Kural ekle</button></div>' +
            '<div class="vyp-liste"><div class="vyp-bos">Yükleniyor…</div></div>' +
            '<div class="vyp-onizle"><label><i class="fa-solid fa-eye"></i> Kimin neye yetkisi var? </label><div class="vyp-aramali vyp-aramali-satir"><input type="search" class="vyp-kisi-ara" placeholder="İsim yazın…"><select class="vyp-kisi"></select></div><div class="vyp-sonuc"></div></div>' +
            '</div></div>';
        document.body.appendChild(arka);
        var $ = function (s) { return arka.querySelector(s); };
        var veri = null, yerel = []; // yerel: henüz kuralı olmayan yeni eklenmiş hedefler
        function kapat() { arka.remove(); document.removeEventListener('keydown', tus); }
        function tus(e) { if (e.key === 'Escape') kapat(); }
        document.addEventListener('keydown', tus);
        arka.addEventListener('click', function (e) { if (e.target === arka || e.target.closest('.vyp-kapat')) kapat(); });

        // Arama kutusu: yazdıkça açılır listeyi süzer (büyük/küçük harf ve Türkçe karakter duyarsız); tek sonuç kalırsa seçer
        function katla(x) { return String(x || '').toLocaleLowerCase('tr-TR').replace(/[çğıöşü]/g, function (h) { return { 'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u' }[h]; }); }
        function aramaBagla(inp, sel, degisince) {
            inp.addEventListener('input', function () {
                var a = katla(inp.value.trim()), gorunen = [];
                Array.prototype.forEach.call(sel.options, function (o) {
                    var uy = !a || !o.value || katla(o.text).indexOf(a) >= 0;
                    o.hidden = !uy; o.disabled = !uy && !!o.value;
                    if (uy && o.value) gorunen.push(o);
                });
                // Seçili öğe süzüldüyse (ya da boş seçenekteyse) ilk eşleşeni seç
                if (a && gorunen.length && gorunen.indexOf(sel.selectedOptions[0]) < 0) { sel.value = gorunen[0].value; if (degisince) degisince(); }
            });
        }
        function degerDoldur() {
            var t = $('.vyp-tip').value, h = '';
            if ($('.vyp-deger-ara')) { $('.vyp-deger-ara').value = ''; $('.vyp-deger-ara').disabled = t === 'herkes'; }
            if (t === 'herkes') h = '<option value="">Tüm kullanıcılar</option>';
            else if (t === 'rol') h = veri.roller.map(function (r) { return '<option>' + esc(r) + '</option>'; }).join('');
            else if (t === 'birim') h = (veri.birimler || []).map(function (g) { return '<option>' + esc(g) + '</option>'; }).join('') || '<option value="">Birim tanımlı değil</option>';
            else if (t === 'unvan') h = (veri.unvanlar || []).map(function (g) { return '<option>' + esc(g) + '</option>'; }).join('') || '<option value="">Unvan tanımlı değil</option>';
            else if (t === 'gorev') h = veri.gorevler.map(function (g) { return '<option>' + esc(g) + '</option>'; }).join('') || '<option value="">Görev tanımlı değil</option>';
            else h = veri.kisiler.filter(function (k) { return String(k.rol).toLowerCase() !== 'kurucu'; })
                .map(function (k) { return '<option value="' + k.id + '">' + esc(k.ad) + (k.gorev ? ' — ' + esc(k.gorev) : '') + '</option>'; }).join('');
            $('.vyp-deger').innerHTML = h; $('.vyp-deger').disabled = t === 'herkes';
        }
        function ciz() {
            var satirlar = veri.kurallar.concat(yerel.filter(function (y) {
                return !veri.kurallar.some(function (k) { return k.tip === y.tip && k.deger === y.deger; });
            }));
            var h = '<div class="vyp-kart vyp-varsayilan"><div class="vyp-kart-ust"><span class="vyp-tip-etk">Varsayılan</span><b>Kural yoksa</b></div><div class="vyp-yetkiler">' +
                veri.yetkiler.map(function (y) { return '<div class="vyp-yetki"><span>' + esc(y.ad) + '</span><em>' + esc(y.varsayilan) + '</em></div>'; }).join('') + '</div></div>';
            h += satirlar.map(function (s) {
                return '<div class="vyp-kart" data-tip="' + s.tip + '" data-deger="' + esc(s.deger) + '"><div class="vyp-kart-ust"><span class="vyp-tip-etk">' + TIP[s.tip] + '</span><b>' + esc(s.ad) + '</b>' +
                    '<button type="button" class="vyp-sil" title="Bu hedefin tüm kurallarını kaldır"><i class="fa-solid fa-trash"></i></button></div><div class="vyp-yetkiler">' +
                    veri.yetkiler.map(function (y) {
                        var v = s.yetkiler[y.anahtar], cls = v === 1 ? 'i' : (v === 0 ? 'e' : 'd');
                        return '<button type="button" class="vyp-yetki vyp-dugme ' + cls + '" data-yetki="' + y.anahtar + '"><span>' + esc(y.ad) + '</span><em>' +
                            (v === 1 ? '✓ İzin' : v === 0 ? '✕ Engel' : '— Devral') + '</em></button>';
                    }).join('') + '</div></div>';
            }).join('');
            $('.vyp-liste').innerHTML = h;
        }
        function yukle() {
            return getir({ islem: 'liste', modul: modul }).then(function (j) {
                var ilk = !veri; veri = j;
                $('.vyp-baslik').textContent = j.baslik + ' — Yetkiler';
                if (ilk) {
                    degerDoldur();
                    $('.vyp-kisi').innerHTML = '<option value="">Kişi seçin…</option>' + j.kisiler.map(function (k) { return '<option value="' + k.id + '">' + esc(k.ad) + (k.gorev ? ' — ' + esc(k.gorev) : '') + ' (' + esc(k.rol) + ')</option>'; }).join('');
                }
                ciz(); onizle();
            }).catch(function (e) { bildir(e.message, 'hata'); });
        }
        function onizle() {
            var id = $('.vyp-kisi').value, kutu = $('.vyp-sonuc');
            if (!id) { kutu.innerHTML = ''; return; }
            getir({ islem: 'kontrol', modul: modul, kullanici_id: id }).then(function (j) {
                kutu.innerHTML = veri.yetkiler.map(function (y) { var v = !!j.yetki[y.anahtar]; return '<span class="vyp-cip ' + (v ? 'i' : 'e') + '">' + (v ? '✓ ' : '✕ ') + esc(y.ad) + '</span>'; }).join('');
            }).catch(function (e) { bildir(e.message, 'hata'); });
        }
        $('.vyp-tip').addEventListener('change', degerDoldur);
        $('.vyp-kisi').addEventListener('change', onizle);
        aramaBagla($('.vyp-deger-ara'), $('.vyp-deger'));
        aramaBagla($('.vyp-kisi-ara'), $('.vyp-kisi'), onizle);
        $('.vyp-ekle-btn').addEventListener('click', function () {
            var t = $('.vyp-tip').value, sel = $('.vyp-deger'), d = t === 'herkes' ? '' : sel.value;
            if (t !== 'herkes' && !d) { bildir('Kimin için olduğunu seçin.', 'uyari'); return; }
            var ad = t === 'herkes' ? 'Tüm kullanıcılar' : sel.options[sel.selectedIndex].text.replace(/ — .*$/, '');
            if (!yerel.concat(veri.kurallar).some(function (k) { return k.tip === t && k.deger === d; })) yerel.push({ tip: t, deger: d, ad: ad, yetkiler: {} });
            ciz();
            var kart = arka.querySelector('.vyp-kart[data-tip="' + t + '"][data-deger="' + (window.CSS && CSS.escape ? CSS.escape(d) : d) + '"]');
            if (kart) { kart.classList.add('vyp-yeni'); kart.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
        });
        $('.vyp-liste').addEventListener('click', function (e) {
            var kart = e.target.closest('.vyp-kart[data-tip]'); if (!kart) return;
            var tip = kart.dataset.tip, deger = kart.dataset.deger;
            var d = e.target.closest('.vyp-dugme');
            if (d) {
                var sonraki = d.classList.contains('d') ? '1' : d.classList.contains('i') ? '0' : '';
                d.disabled = true;
                gonder({ islem: 'kaydet', modul: modul, hedef_tipi: tip, hedef_deger: deger, yetki: d.dataset.yetki, izin: sonraki })
                    .then(yukle).catch(function (er) { bildir(er.message, 'hata'); d.disabled = false; });
                return;
            }
            if (e.target.closest('.vyp-sil')) {
                onayla({ baslik: 'Kurallar kaldırılsın mı?', mesaj: kart.querySelector('b').textContent + ' için tüm kurallar silinecek; varsayılanlar geçerli olur.', evet: 'Kaldır', hayir: 'Vazgeç', tur: 'uyari' })
                    .then(function (ok) {
                        if (!ok) return;
                        yerel = yerel.filter(function (y) { return !(y.tip === tip && y.deger === deger); });
                        return gonder({ islem: 'hedef_sil', modul: modul, hedef_tipi: tip, hedef_deger: deger }).then(yukle);
                    }).catch(function (er) { bildir(er.message, 'hata'); });
            }
        });
        yukle();
    }
    window.TbYetkiPanel = { ac: ac };
})();
