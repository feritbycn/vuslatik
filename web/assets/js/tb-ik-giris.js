/* ==========================================================================
   tb-ik-giris.js — Giriş-çıkış kayıtları, raporlar ve yönetim (İK sayfası içinde)
   Veri: giris_islem.php. Personelin kaydırarak giriş-çıkış yaptığı sayfa ayrıdır: giris_cikis.php
   ========================================================================== */
(function () {
    'use strict';
    function basla() {
        var M = window.IKM; if (!M || !M.kayit) return setTimeout(basla, 30);
        var $ = M.$, $$ = M.$$, esc = M.esc, bildir = M.bildir, onayla = M.onayla, icerik = M.icerik, modal = M.modal;
        var G = M.istek('giris_islem.php'), getir = G.getir, gonder = G.gonder;
        var Y = M.yetki(), YONET = !!Y.ik_giris_yonet;
        function p2(n) { return (n < 10 ? '0' : '') + n; }
        function sureYaz(sn) { sn = Math.max(0, Math.floor(sn || 0)); var s = Math.floor(sn / 3600), d = Math.floor((sn % 3600) / 60); return (s ? s + 's ' : '') + d + 'd'; }
        function tarihYaz(t) { var m = String(t || '').match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/); return m ? m[3] + '.' + m[2] + '.' + m[1] + ' ' + m[4] + ':' + m[5] : ''; }
        function saatKisa(t) { var m = String(t || '').match(/(\d{2}):(\d{2})/); return m ? m[1] + ':' + m[2] : ''; }
        function mesafe(a, b, c, d) { var R = 6371000, r = Math.PI / 180, dp = (c - a) * r, dl = (d - b) * r, x = Math.sin(dp / 2) * Math.sin(dp / 2) + Math.cos(a * r) * Math.cos(c * r) * Math.sin(dl / 2) * Math.sin(dl / 2); return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x)); }
        function bekle() { icerik.innerHTML = M.yukleniyor(); }
        function hata(e) { icerik.innerHTML = M.hataKutu(e.message); }
        function kutuHtml(ikon, renk, deger, ad, alt) { return '<div class="ikm-kpi ' + renk + '"><span class="ikm-kpi-ikon"><i class="fa-solid ' + ikon + '"></i></span><div><b>' + deger + '</b><span>' + ad + '</span>' + (alt ? '<small>' + alt + '</small>' : '') + '</div></div>'; }
        function tablo(basliklar, satirlar, sinif) { return '<div class="ikm-tablo-kap"><table class="ikm-tablo ' + (sinif || '') + '"><thead><tr>' + basliklar.join('') + '</tr></thead><tbody>' + satirlar + '</tbody></table></div>'; }
        function bosSatir(n, m) { return '<tr><td colspan="' + n + '">' + M.bosKutu('fa-inbox', m) + '</td></tr>'; }

        /* ---- konum (yalnızca nokta ölçümü için) ---- */
        function konumAl() {
            // Tek bir GPS ölçümü kapalı/yarı açık alanda 30-60 m şaşabilir; bu yüzden ~10 sn boyunca ölçüm toplanır,
            // hata payına göre ağırlıklı ortalama alınır (iyi ölçümler ağır basar).
            return new Promise(function (ok, no) {
                if (!navigator.geolocation) { no(new Error('Tarayıcınız konumu desteklemiyor.')); return; }
                var ornek = [], id = null, bitti = false, bas = Date.now(), genel = setTimeout(bit, 15000), kontrol = setInterval(function () { if (ornek.length >= 3 && Date.now() - bas > 9000) bit(); }, 500);
                function bit() {
                    if (bitti) return; bitti = true; clearTimeout(genel); clearInterval(kontrol); if (id !== null) navigator.geolocation.clearWatch(id);
                    if (!ornek.length) { no(new Error('Konum alınamadı. GPS’i açıp konum iznini verin.')); return; }
                    var en = Math.min.apply(null, ornek.map(function (o) { return o.d; })), iyi = ornek.filter(function (o) { return o.d <= Math.max(en * 2, 12); }), w = 0, la = 0, lo = 0;
                    iyi.forEach(function (o) { var k = 1 / (o.d * o.d); w += k; la += o.la * k; lo += o.lo * k; });
                    ok({ enlem: la / w, boylam: lo / w, dogruluk: en, ornek: iyi.length });
                }
                id = navigator.geolocation.watchPosition(function (p) { var c = p.coords; if (c.accuracy > 0) ornek.push({ la: c.latitude, lo: c.longitude, d: Math.max(c.accuracy, 1) }); if (c.accuracy <= 5 && ornek.length >= 6) bit(); },
                    function (e) { if (ornek.length) return; bitti = true; clearTimeout(genel); clearInterval(kontrol); if (id !== null) navigator.geolocation.clearWatch(id); no(new Error(e.code === 1 ? 'Konum izni verilmedi.' : 'Konum alınamadı.')); }, { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 });
            });
        }

        /* ---------------- ŞU AN ---------------- */
        function bugunCiz() {
            bekle();
            getir({ islem: 'bugun' }).then(function (j) {
                var say = { iceride: 0, cikti: 0, gelmedi: 0 }; j.liste.forEach(function (k) { say[k.durum]++; });
                var rz = $('#ikm-r-cihaz'); if (rz) { rz.hidden = !j.bekleyen_cihaz || !YONET; rz.textContent = j.bekleyen_cihaz; }
                var AD = { iceride: 'İçeride', cikti: 'Çıktı', gelmedi: 'Gelmedi' }, RENK = { iceride: 'yesil', cikti: 'gri', gelmedi: 'turuncu' };
                icerik.innerHTML = '<section class="ikm-kpiler">' + kutuHtml('fa-door-open', 'yesil', say.iceride, 'İçeride') + kutuHtml('fa-person-walking-arrow-right', 'mavi', say.cikti, 'Çıkış yaptı') + kutuHtml('fa-user-clock', 'turuncu', say.gelmedi, 'Henüz gelmedi') + '</section>' +
                    (YONET && j.bekleyen_cihaz ? '<div class="ikm-uyari-kutu"><i class="fa-solid fa-mobile-screen"></i><div><b>' + j.bekleyen_cihaz + ' cihaz onay bekliyor.</b> <button type="button" class="ikm-link" data-git="gc_cihazlar">Cihazlara git →</button></div></div>' : '') +
                    '<div class="ikm-kart">' + tablo(['<th>Personel</th>', '<th>Durum</th>', '<th>İlk giriş</th>', '<th>Son çıkış</th>', '<th>Bugün</th>'], j.liste.map(function (k) {
                        return '<tr><td><b>' + esc(k.ad) + '</b><small class="ikm-soluk blok">' + esc(k.gorev) + '</small></td><td><span class="ikm-cip ' + RENK[k.durum] + '">' + AD[k.durum] + '</span></td><td>' + esc(saatKisa(k.ilk_giris)) + '</td><td>' + (k.durum === 'iceride' ? '<em class="ikm-yesil-yazi">içeride</em>' : esc(saatKisa(k.son_cikis))) + '</td><td>' + esc(k.sure) + '</td></tr>';
                    }).join('') || bosSatir(5, 'Personel yok')) + '</div>';
            }).catch(hata);
        }
        M.kayit('gc_bugun', 'Şu an kimler içeride', 'Bugünkü giriş-çıkış durumu.', bugunCiz);

        /* ---------------- KAYITLAR ---------------- */
        var kF = { bas: '', bit: '', kisi: '', supheli: '' };
        function kayitlarCiz() {
            bekle();
            getir({ islem: 'kayitlar', bas: kF.bas, bit: kF.bit, kullanici_id: kF.kisi, supheli: kF.supheli }).then(function (j) {
                kF.bas = j.bas; kF.bit = j.bit;
                var toplam = j.liste.reduce(function (t, k) { return t + (k.sure_sn || 0); }, 0);
                icerik.innerHTML = '<div class="ikm-arac"><input type="date" id="gk-bas" value="' + j.bas + '"><input type="date" id="gk-bit" value="' + j.bit + '"><select id="gk-kisi"><option value="">Tüm personel</option>' + j.kisiler.map(function (k) { return '<option value="' + k.id + '"' + (String(k.id) === String(kF.kisi) ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' +
                    '<label class="ikm-onay-kutu"><input type="checkbox" id="gk-sup"' + (kF.supheli === '1' ? ' checked' : '') + '> <span>Yalnızca şüpheli</span></label>' + (YONET ? '<button type="button" class="ikm-btn ikm-btn-ana" id="gk-ekle" style="margin-left:auto"><i class="fa-solid fa-plus"></i> Kayıt ekle</button>' : '') + '</div>' +
                    '<p class="ikm-soluk kucuk">' + j.liste.length + ' kayıt · toplam ' + sureYaz(toplam) + '</p><div class="ikm-kart">' +
                    tablo(['<th>Personel</th>', '<th>Giriş</th>', '<th>Çıkış</th>', '<th>Süre</th>', '<th>Nokta</th>', '<th>Not / uyarı</th>'].concat(YONET ? ['<th></th>'] : []), j.liste.length ? j.liste.map(function (k) {
                        return '<tr><td><b>' + esc(k.ad) + '</b></td><td>' + esc(tarihYaz(k.giris)) + '</td><td>' + (k.cikis ? esc(tarihYaz(k.cikis)) : '<em class="ikm-yesil-yazi">içeride</em>') + '</td><td>' + (k.sure_sn !== null ? sureYaz(k.sure_sn) : '') + '</td><td>' + esc(k.nokta) + (k.giris_mesafe !== null ? '<small class="ikm-soluk blok">' + k.giris_mesafe + ' m</small>' : '') + '</td><td>' +
                            k.supheli.map(function (s) { return '<span class="ikm-cip ' + (s.kod === 'duzeltildi' ? 'mavi' : 'kirmizi') + '" title="' + esc(s.ad) + '">' + esc(s.ad.length > 42 ? s.ad.slice(0, 40) + '…' : s.ad) + '</span> '; }).join('') + (k.not ? '<small class="ikm-soluk blok">' + esc(k.not) + '</small>' : '') + '</td>' +
                            (YONET ? '<td class="ikm-islem"><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon" data-duz="' + k.id + '" aria-label="Düzelt"><i class="fa-solid fa-pen"></i></button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon ikm-btn-tehlike" data-sil="' + k.id + '" aria-label="Sil"><i class="fa-solid fa-trash"></i></button></td>' : '') + '</tr>';
                    }).join('') : bosSatir(7, 'Bu aralıkta kayıt yok')) + '</div>';
                var yenile = function () { kF = { bas: $('#gk-bas').value, bit: $('#gk-bit').value, kisi: $('#gk-kisi').value, supheli: $('#gk-sup').checked ? '1' : '' }; kayitlarCiz(); };
                ['gk-bas', 'gk-bit', 'gk-kisi', 'gk-sup'].forEach(function (i) { $('#' + i).onchange = yenile; });
                if ($('#gk-ekle')) $('#gk-ekle').onclick = function () { kayitFormu(null, j.kisiler); };
                $$('[data-duz]', icerik).forEach(function (b) { b.onclick = function () { kayitFormu(j.liste.filter(function (k) { return String(k.id) === b.dataset.duz; })[0], j.kisiler); }; });
                $$('[data-sil]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Kayıt silinsin mi?', mesaj: 'Bu giriş-çıkış kaydı kalıcı olarak silinir.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('kayit_sil', { id: b.dataset.sil }).then(function () { bildir('Kayıt silindi.', 'basari'); kayitlarCiz(); }); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
            }).catch(hata);
        }
        function dtl(t) { return t ? String(t).replace(' ', 'T').slice(0, 16) : ''; }
        function kayitFormu(k, kisiler) {
            var m = modal({ baslik: k ? 'Kaydı düzelt — ' + esc(k.ad) : 'Elle kayıt ekle', ikon: 'fa-pen', gen: 480,
                govde: '<div class="ikm-form">' + (k ? '' : '<label class="ikm-alan"><span>Personel</span><select id="gf-kisi">' + kisiler.map(function (x) { return '<option value="' + x.id + '">' + esc(x.ad) + '</option>'; }).join('') + '</select></label>') +
                    '<div class="ikm-iki"><label class="ikm-alan"><span>Giriş</span><input type="datetime-local" id="gf-giris" value="' + dtl(k && k.giris) + '"></label><label class="ikm-alan"><span>Çıkış <small>(içerideyse boş)</small></span><input type="datetime-local" id="gf-cikis" value="' + dtl(k && k.cikis) + '"></label></div>' +
                    '<label class="ikm-alan"><span>Düzeltme nedeni <b>*</b> <small>denetim için saklanır</small></span><input type="text" id="gf-not" maxlength="300" value="' + esc(k ? k.not : '') + '" placeholder="Örn. Çıkışı unuttu, telefonu bozuktu"></label></div>',
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="gf-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
            $('#gf-kaydet', m.el).onclick = function () {
                var v = { giris: $('#gf-giris', m.el).value, cikis: $('#gf-cikis', m.el).value, not: $('#gf-not', m.el).value };
                if (k) v.id = k.id; else v.kullanici_id = $('#gf-kisi', m.el).value;
                gonder(k ? 'kayit_duzelt' : 'kayit_ekle', v).then(function () { bildir('Kaydedildi.', 'basari'); m.kapat(); kayitlarCiz(); }).catch(function (e) { bildir(e.message, 'hata'); });
            };
        }
        M.kayit('gc_kayitlar', 'Giriş-çıkış kayıtları', 'Tüm personelin giriş ve çıkış kayıtları.', kayitlarCiz);

        /* ---------------- RAPORLAR ---------------- */
        function dkYaz(dk) { dk = Math.round(dk || 0); if (!dk) return '—'; return dk >= 60 ? Math.floor(dk / 60) + 's ' + (dk % 60 ? (dk % 60) + 'd' : '') : dk + ' dk'; }
        function ymd(d) { return d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate()); }
        var rF = null, rS = { alan: 'gec_dk', ters: true }, rV = null;
        function donemler() {
            var b = new Date();
            return { ay: [ymd(new Date(b.getFullYear(), b.getMonth(), 1)), ymd(b), 'Bu ay'], gecen: [ymd(new Date(b.getFullYear(), b.getMonth() - 1, 1)), ymd(new Date(b.getFullYear(), b.getMonth(), 0)), 'Geçen ay'], hafta: [ymd(new Date(b.getTime() - 6 * 86400000)), ymd(b), 'Son 7 gün'], yil: [ymd(new Date(b.getFullYear(), 0, 1)), ymd(b), 'Bu yıl'] };
        }
        function raporCiz() {
            var DN = donemler(); if (!rF) rF = { bas: DN.ay[0], bit: DN.ay[1], kisi: '', donem: 'ay' };
            bekle();
            getir({ islem: 'rapor', bas: rF.bas, bit: rF.bit, kullanici_id: rF.kisi }).then(function (j) { rV = j; raporGoster(); }).catch(hata);
        }
        function raporGoster() {
            var j = rV, DN = donemler(), T = j.toplam, tek = !!rF.kisi;
            var sat = j.satirlar.slice().sort(function (a, b) { var x = a[rS.alan], y = b[rS.alan]; if (typeof x === 'string') return rS.ters ? y.localeCompare(x, 'tr') : x.localeCompare(y, 'tr'); return rS.ters ? y - x : x - y; });
            var enGec = j.satirlar.filter(function (o) { return o.gec_dk > 0; }).sort(function (a, b) { return b.gec_dk - a.gec_dk; }).slice(0, 5), enMaks = enGec.length ? enGec[0].gec_dk : 1;
            var h = '<div class="ikm-arac"><div class="ikm-segment">' + Object.keys(DN).map(function (k) { return '<button type="button" data-d="' + k + '" class="' + (rF.donem === k ? 'aktif' : '') + '">' + DN[k][2] + '</button>'; }).join('') + '</div>' +
                '<input type="date" id="gr-bas" value="' + j.bas + '"><input type="date" id="gr-bit" value="' + j.bit + '"><select id="gr-kisi"><option value="">Tüm personel</option>' + j.kisiler.map(function (k) { return '<option value="' + k.id + '"' + (String(k.id) === String(rF.kisi) ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' +
                '<button type="button" class="ikm-btn" id="gr-csv" style="margin-left:auto"><i class="fa-solid fa-file-csv"></i> Excel (CSV) indir</button></div>' +
                '<div class="ikm-bilgi-kutu"><i class="fa-solid fa-circle-info"></i><div>Vardiya: <b>' + esc(j.vardiya.baslangic) + '–' + esc(j.vardiya.bitis) + '</b> (kişiye özel vardiyası olanlar kendi saatiyle) · tolerans <b>' + j.vardiya.tolerans + ' dk</b>. Geç kalma = ilk giriş vardiya başlangıcından sonra; erken çıkış = son çıkış bitişten önce; geç çıkış = bitişten sonra. Onaylı izin / rapor / tatil günleri devamsızlık sayılmaz.</div></div>' +
                '<section class="ikm-kpiler bes">' + kutuHtml('fa-person-running', 'kirmizi', dkYaz(T.gec_dk), 'Toplam geç kalma', T.gec_kisi + ' kişi') + kutuHtml('fa-door-open', 'turuncu', dkYaz(T.erken_cikis_dk), 'Erken çıkış') + kutuHtml('fa-moon', 'mor', dkYaz(T.gec_cikis_dk), 'Geç çıkış / fazla süre') + kutuHtml('fa-sun', 'yesil', dkYaz(T.erken_dk), 'Erken gelme') + kutuHtml('fa-user-xmark', 'gri', T.devamsiz_gun, 'Devamsız gün', 'mazeretsiz') + '</section>';
            if (!tek && enGec.length) h += '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-ranking-star"></i> En çok geç kalanlar</h3></div>' + enGec.map(function (o) { return '<div class="ikm-cubuk-satir tikla" data-kisi="' + o.id + '"><span>' + esc(o.ad) + '</span><div><i style="width:' + Math.max(4, Math.round(o.gec_dk / enMaks * 100)) + '%"></i></div><b>' + dkYaz(o.gec_dk) + '</b></div>'; }).join('') + '</div>';
            if (tek) {
                var o = j.satirlar[0] || {}, ROZ = { zamaninda: ['Zamanında', 'yesil'], gec: ['Geç', 'kirmizi'], devamsiz: ['Devamsız', 'kirmizi'], izinli: ['İzinli', 'mor'], tatil_gunu: ['Tatil günü', 'gri'], hafta_sonu: ['Tatilde çalıştı', 'yesil'], iceride: ['İçeride', 'yesil'], cikis_yok: ['Çıkış yok', 'turuncu'], bekleniyor: ['Bekleniyor', 'gri'] };
                h += '<div class="ikm-kart"><b>' + esc(o.ad || '') + '</b> <span class="ikm-soluk">· vardiya ' + esc(o.vardiya || '') + '</span><div class="ikm-lejant ozet"><span>Çalışılan <b>' + o.calisilan_gun + '</b> gün</span><span>Geç kalınan <b>' + o.gec_gun + '</b> gün (' + dkYaz(o.gec_dk) + ')</span><span>Erken çıkış <b>' + o.erken_cikis_gun + '</b> gün (' + dkYaz(o.erken_cikis_dk) + ')</span><span>Geç çıkış <b>' + o.gec_cikis_gun + '</b> gün (' + dkYaz(o.gec_cikis_dk) + ')</span><span>Devamsız <b>' + o.devamsiz_gun + '</b> gün</span><span>Toplam <b>' + sureYaz(o.toplam_sn) + '</b></span></div></div><div class="ikm-kart">' +
                    tablo(['<th>Tarih</th>', '<th>Durum</th>', '<th>İlk giriş</th>', '<th>Son çıkış</th>', '<th>Geç kalma</th>', '<th>Erken çıkış</th>', '<th>Geç çıkış</th>', '<th>Süre</th>'], j.gunluk.filter(function (d) { return d.durum !== 'gelecek'; }).map(function (d) {
                        var r = ROZ[d.durum] || ['', 'gri']; return '<tr><td><b>' + esc(d.tarih.split('-').reverse().join('.')) + '</b> <small class="ikm-soluk">' + d.gun + '</small></td><td>' + (r[0] ? '<span class="ikm-cip ' + r[1] + '">' + r[0] + '</span>' : '') + '</td><td>' + esc(d.ilk) + '</td><td>' + esc(d.son) + '</td><td class="' + (d.gec_dk ? 'kirmizi-yazi' : '') + '">' + dkYaz(d.gec_dk) + '</td><td class="' + (d.erken_cikis_dk ? 'turuncu-yazi' : '') + '">' + dkYaz(d.erken_cikis_dk) + '</td><td>' + dkYaz(d.gec_cikis_dk) + '</td><td>' + (d.sure_sn ? sureYaz(d.sure_sn) : '') + '</td></tr>';
                    }).join('')) + '</div>';
            } else {
                var kol = [['ad', 'Personel'], ['gec_gun', 'Geç (gün)'], ['gec_dk', 'Toplam geç'], ['en_cok_gec', 'En çok geç'], ['erken_dk', 'Erken gelme'], ['erken_cikis_dk', 'Erken çıkış'], ['gec_cikis_dk', 'Geç çıkış'], ['devamsiz_gun', 'Devamsız'], ['toplam_sn', 'Toplam çalışma']];
                h += '<div class="ikm-kart">' + tablo(kol.map(function (k) { return '<th data-s="' + k[0] + '" class="sirala' + (rS.alan === k[0] ? ' sirali' : '') + '">' + k[1] + (rS.alan === k[0] ? (rS.ters ? ' ▼' : ' ▲') : '') + '</th>'; }), sat.map(function (o) {
                    return '<tr class="tikla" data-kisi="' + o.id + '"><td><b>' + esc(o.ad) + '</b><small class="ikm-soluk blok">' + esc(o.vardiya) + '</small></td><td>' + o.gec_gun + '</td><td class="' + (o.gec_dk ? 'kirmizi-yazi' : '') + '"><b>' + dkYaz(o.gec_dk) + '</b></td><td>' + dkYaz(o.en_cok_gec) + '</td><td class="' + (o.erken_dk ? 'yesil-yazi' : '') + '">' + dkYaz(o.erken_dk) + '</td><td class="' + (o.erken_cikis_dk ? 'turuncu-yazi' : '') + '">' + dkYaz(o.erken_cikis_dk) + '</td><td>' + dkYaz(o.gec_cikis_dk) + '</td><td class="' + (o.devamsiz_gun ? 'kirmizi-yazi' : '') + '">' + (o.devamsiz_gun || '—') + '</td><td>' + sureYaz(o.toplam_sn) + '</td></tr>';
                }).join('') || bosSatir(9, 'Veri yok')) + '</div><p class="ikm-soluk kucuk">Günlük döküm için bir personele tıklayın.' + '</p>';
            }
            icerik.innerHTML = h;
            $$('.ikm-segment button', icerik).forEach(function (b) { b.onclick = function () { var d = DN[b.dataset.d]; rF.bas = d[0]; rF.bit = d[1]; rF.donem = b.dataset.d; raporCiz(); }; });
            var yenile = function () { rF.bas = $('#gr-bas').value; rF.bit = $('#gr-bit').value; rF.kisi = $('#gr-kisi').value; rF.donem = ''; raporCiz(); };
            ['gr-bas', 'gr-bit', 'gr-kisi'].forEach(function (i) { $('#' + i).onchange = yenile; });
            $$('[data-kisi]', icerik).forEach(function (r) { r.onclick = function () { rF.kisi = r.dataset.kisi; raporCiz(); }; });
            $$('th.sirala', icerik).forEach(function (t) { t.onclick = function () { rS = { alan: t.dataset.s, ters: rS.alan === t.dataset.s ? !rS.ters : t.dataset.s !== 'ad' }; raporGoster(); }; });
            $('#gr-csv').onclick = function () { csvIndir(tek); };
        }
        function csvIndir(tek) {
            var j = rV, satir = [];
            if (tek) { satir.push(['Tarih', 'Gün', 'Durum', 'İlk giriş', 'Son çıkış', 'Geç kalma (dk)', 'Erken gelme (dk)', 'Erken çıkış (dk)', 'Geç çıkış (dk)', 'Çalışma (dk)']); j.gunluk.forEach(function (d) { if (d.durum !== 'gelecek') satir.push([d.tarih, d.gun, d.durum, d.ilk, d.son, d.gec_dk, d.erken_dk, d.erken_cikis_dk, d.gec_cikis_dk, Math.round(d.sure_sn / 60)]); }); }
            else { satir.push(['Personel', 'Vardiya', 'Çalışılan gün', 'Geç kalınan gün', 'Toplam geç (dk)', 'En çok geç (dk)', 'Erken gelen gün', 'Erken gelme (dk)', 'Erken çıkış günü', 'Erken çıkış (dk)', 'Geç çıkış günü', 'Geç çıkış (dk)', 'Devamsız gün', 'Toplam çalışma (dk)']); j.satirlar.forEach(function (o) { satir.push([o.ad, o.vardiya, o.calisilan_gun, o.gec_gun, o.gec_dk, o.en_cok_gec, o.erken_gun, o.erken_dk, o.erken_cikis_gun, o.erken_cikis_dk, o.gec_cikis_gun, o.gec_cikis_dk, o.devamsiz_gun, Math.round(o.toplam_sn / 60)]); }); }
            var csv = '﻿' + satir.map(function (r) { return r.map(function (x) { return '"' + String(x == null ? '' : x).replace(/"/g, '""') + '"'; }).join(';'); }).join('\r\n');
            var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = 'giris_cikis_raporu_' + j.bas + '_' + j.bit + '.csv'; document.body.appendChild(a); a.click(); a.remove();
        }
        M.kayit('gc_rapor', 'Giriş-çıkış raporları', 'Geç kalma, erken çıkış, fazla mesai ve devamsızlık.', raporCiz);

        /* ---------------- VARDİYALAR ---------------- */
        function vardiyalarCiz() {
            bekle();
            getir({ islem: 'vardiyalar' }).then(function (j) {
                icerik.innerHTML = '<div class="ikm-bilgi-kutu"><i class="fa-solid fa-circle-info"></i><div>Varsayılan vardiya <b>' + esc(j.varsayilan.baslangic) + '–' + esc(j.varsayilan.bitis) + '</b> (Giriş-çıkış ayarlarından değişir). Farklı saatte çalışanlar için kendi saatini yazın; <b>boş satır varsayılanı kullanır</b>.</div></div><div class="ikm-kart">' +
                    tablo(['<th>Personel</th>', '<th>Başlangıç</th>', '<th>Bitiş</th>'], j.liste.map(function (k) { return '<tr data-id="' + k.id + '"><td><b>' + esc(k.ad) + '</b><small class="ikm-soluk blok">' + esc(k.gorev) + '</small></td><td><input type="time" class="gv-b" value="' + esc(k.baslangic) + '"></td><td><input type="time" class="gv-t" value="' + esc(k.bitis) + '"></td></tr>'; }).join('')) + '</div>' +
                    '<div class="ikm-akis-alt"><button type="button" class="ikm-btn ikm-btn-ana" id="gv-kaydet"><i class="fa-solid fa-floppy-disk"></i> Kaydet</button></div>';
                $('#gv-kaydet').onclick = function () {
                    var kayitlar = $$('tr[data-id]', icerik).map(function (tr) { return { kullanici_id: tr.dataset.id, baslangic: $('.gv-b', tr).value, bitis: $('.gv-t', tr).value }; });
                    gonder('vardiya_kaydet', { kayitlar: JSON.stringify(kayitlar) }).then(function () { bildir('Vardiyalar kaydedildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); });
                };
            }).catch(hata);
        }
        M.kayit('gc_vardiyalar', 'Vardiyalar', 'Kişiye özel çalışma saatleri.', vardiyalarCiz);

        /* ---------------- CİHAZLAR ---------------- */
        function cihazlarCiz() {
            bekle();
            getir({ islem: 'cihazlar' }).then(function (j) {
                var AD = { onayli: 'Onaylı', bekliyor: 'Onay bekliyor', reddedildi: 'Reddedildi' }, RENK = { onayli: 'yesil', bekliyor: 'turuncu', reddedildi: 'kirmizi' };
                icerik.innerHTML = '<div class="ikm-bilgi-kutu"><i class="fa-solid fa-circle-info"></i><div>Personel yalnızca <b>onaylı cihazından</b> giriş yapabilir. İlk cihaz otomatik onaylanır (ayarlardan değiştirilebilir); sonraki cihazlar burada onayınızı bekler. PIN’ini unutan kişi için “PIN sıfırla”yı kullanın.</div></div><div class="ikm-kart">' +
                    tablo(['<th>Personel</th>', '<th>Cihaz</th>', '<th>Durum</th>', '<th>Son kullanım</th>', '<th>IP</th>', '<th></th>'], j.liste.length ? j.liste.map(function (c) {
                        return '<tr><td><b>' + esc(c.ad) + '</b><small class="ikm-soluk blok">' + (c.pin_var ? 'PIN var' : 'PIN yok') + '</small></td><td>' + esc(c.cihaz) + '</td><td><span class="ikm-cip ' + RENK[c.durum] + '">' + AD[c.durum] + '</span></td><td>' + esc(tarihYaz(c.son)) + '</td><td>' + esc(c.ip) + '</td><td class="ikm-islem">' +
                            (c.durum !== 'onayli' ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-yesil" data-k="' + c.id + '" data-v="onayli">Onayla</button>' : '') + (c.durum !== 'reddedildi' ? '<button type="button" class="ikm-btn ikm-btn-kucuk" data-k="' + c.id + '" data-v="reddedildi">Reddet</button>' : '') +
                            '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon" data-pin="' + c.kullanici_id + '" title="PIN sıfırla" aria-label="PIN sıfırla"><i class="fa-solid fa-key"></i></button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon ikm-btn-tehlike" data-k="' + c.id + '" data-v="sil" title="Cihazı sil" aria-label="Cihazı sil"><i class="fa-solid fa-trash"></i></button></td></tr>';
                    }).join('') : bosSatir(6, 'Henüz cihaz kaydı yok')) + '</div>';
                $$('[data-k]', icerik).forEach(function (b) { b.onclick = function () {
                    var is = function () { return gonder('cihaz_karar', { id: b.dataset.k, karar: b.dataset.v }).then(function () { bildir('Güncellendi.', 'basari'); cihazlarCiz(); }); };
                    (b.dataset.v === 'sil' ? onayla({ baslik: 'Cihaz silinsin mi?', mesaj: 'Kişi bu cihazı yeniden kaydettirmek zorunda kalır.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }) : Promise.resolve(true)).then(function (ok) { if (ok) return is(); }).catch(function (e) { bildir(e.message, 'hata'); });
                }; });
                $$('[data-pin]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'PIN sıfırlansın mı?', mesaj: 'Kişi bir sonraki girişte yeni bir PIN oluşturmak zorunda kalacak.', evet: 'Sıfırla', hayir: 'Vazgeç', tur: 'uyari' }).then(function (ok) { if (ok) return gonder('pin_sifirla', { kullanici_id: b.dataset.pin }).then(function () { bildir('PIN sıfırlandı.', 'basari'); cihazlarCiz(); }); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
            }).catch(hata);
        }
        M.kayit('gc_cihazlar', 'Cihazlar', 'Giriş-çıkış yapabilen cihazlar ve PIN yönetimi.', cihazlarCiz);

        /* ---------------- GEÇİŞ NOKTALARI ---------------- */
        function noktalarCiz() {
            bekle();
            getir({ islem: 'noktalar' }).then(function (j) {
                var base = location.href.replace(/[?#].*$/, '').replace(/[^\/]*$/, '');
                icerik.innerHTML = '<div class="ikm-bilgi-kutu"><i class="fa-solid fa-circle-info"></i><div>Personel yalnızca <b>tanımlı noktanın yarıçapı içindeyken</b> giriş-çıkış yapabilir (konum sunucuda doğrulanır). GPS birkaç metre şaşabildiği için yarıçapı 10 m altında tutmayın. Ek güvenlik için <b>ofis IP kısıtı</b> ve <b>ofis ekranı kodu</b> kullanabilirsiniz.</div></div>' +
                    '<div class="ikm-arac"><button type="button" class="ikm-btn ikm-btn-ana" id="gn-yeni"><i class="fa-solid fa-plus"></i> Yeni geçiş noktası</button></div><div class="ikm-izgara">' +
                    (j.liste.map(function (n) {
                        return '<div class="ikm-kart' + (n.aktif ? '' : ' pasif') + '"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-location-dot"></i> ' + esc(n.ad) + '</h3>' + (n.aktif ? '' : '<span class="ikm-cip gri">Pasif</span>') + '</div><p class="ikm-soluk kucuk">' + (n.enlem !== '' ? esc(n.enlem) + ', ' + esc(n.boylam) + ' · yarıçap ' + n.yaricap + ' m' : 'Konum tanımlı değil') + (n.ip_kisit ? ' · IP: ' + esc(n.ip_kisit) : '') + (n.kod_zorunlu ? ' · ofis kodu zorunlu' : '') + '</p>' +
                            (n.kod_zorunlu ? '<label class="ikm-alan"><span>Ofis ekranı adresi</span><input type="text" readonly value="' + esc(base + 'giris_ekran.php?k=' + (window.TB && window.TB.kurum ? window.TB.kurum.id : 0) + '&nokta=' + n.id + '&anahtar=' + n.ekran_anahtari) + '" onclick="this.select()"></label>' : '') +
                            '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk" data-duz="' + n.id + '"><i class="fa-solid fa-pen"></i> Düzenle</button><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ikon ikm-btn-tehlike" data-sil="' + n.id + '" aria-label="Sil"><i class="fa-solid fa-trash"></i></button></div></div>';
                    }).join('') || M.bosKutu('fa-location-dot', 'Henüz geçiş noktası yok')) + '</div>';
                $('#gn-yeni').onclick = function () { noktaFormu(null); };
                $$('[data-duz]', icerik).forEach(function (b) { b.onclick = function () { noktaFormu(j.liste.filter(function (n) { return String(n.id) === b.dataset.duz; })[0]); }; });
                $$('[data-sil]', icerik).forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Nokta silinsin mi?', mesaj: 'Nokta silinir; eski kayıtlar korunur.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('nokta_sil', { id: b.dataset.sil }).then(noktalarCiz); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
            }).catch(hata);
        }
        function noktaFormu(n) {
            var izId = null, basNokta = null;
            function izDurdur() { if (izId !== null) { navigator.geolocation.clearWatch(izId); izId = null; } }
            var m = modal({ baslik: n ? 'Noktayı düzenle' : 'Yeni geçiş noktası', ikon: 'fa-location-dot', gen: 560, kapaninca: izDurdur,
                govde: '<div class="ikm-form"><label class="ikm-alan"><span>Ad</span><input type="text" id="gp-ad" value="' + esc(n ? n.ad : '') + '" placeholder="Örn. Merkez ofis"></label>' +
                    '<div class="ikm-olcum"><b><i class="fa-solid fa-person-walking"></i> Alanı yürüyerek ölç</b><ol><li>Ofisin <b>içinde</b> (merkez olacak yerde) <b>Başla</b>’ya basın.</li><li>Personelin “içeride” sayılacağı <b>en uzak yere</b> yürüyün.</li><li>Orada <b>Bitir</b>’e basın: mesafe <b>yarıçap</b>, ilk nokta <b>merkez</b> olur.</li></ol>' +
                    '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-ana" id="go-basla"><i class="fa-solid fa-play"></i> Başla</button><button type="button" class="ikm-btn ikm-btn-kucuk" id="go-bitir" disabled><i class="fa-solid fa-flag-checkered"></i> Bitir</button></div><div class="ikm-onizle" id="go-durum">Henüz ölçüm yapılmadı. İsterseniz değerleri elle de girebilirsiniz.</div></div>' +
                    '<div class="ikm-iki"><label class="ikm-alan"><span>Enlem</span><input type="text" id="gp-en" value="' + esc(n ? n.enlem : '') + '"></label><label class="ikm-alan"><span>Boylam</span><input type="text" id="gp-bo" value="' + esc(n ? n.boylam : '') + '"></label></div>' +
                    '<label class="ikm-alan"><span>Yarıçap (m) <small>en az 10, önerilen 50–150</small></span><input type="number" id="gp-yar" min="10" max="5000" value="' + (n ? n.yaricap : 100) + '"></label>' +
                    '<label class="ikm-alan"><span>Ofis IP kısıtı <small>isteğe bağlı; virgülle birden çok. Örn: 85.100.20.5 veya 85.100.20.0/24</small></span><input type="text" id="gp-ip" value="' + esc(n ? n.ip_kisit : '') + '"></label>' +
                    '<label class="ikm-onay-kutu"><input type="checkbox" id="gp-kod"' + (n && n.kod_zorunlu ? ' checked' : '') + '> <span>Ofis ekranındaki dönen 6 haneli kodu zorunlu tut <small>(ofiste bir ekranda <b>giris_ekran.php</b> açık kalır — sahte GPS’e karşı en güçlü koruma)</small></span></label>' +
                    '<label class="ikm-onay-kutu"><input type="checkbox" id="gp-aktif"' + (!n || n.aktif ? ' checked' : '') + '> <span>Aktif</span></label></div>',
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="gp-kaydet"><i class="fa-solid fa-check"></i> Kaydet</button>' });
            var durumEl = $('#go-durum', m.el), basBtn = $('#go-basla', m.el), bitBtn = $('#go-bitir', m.el);
            basBtn.onclick = function () {
                izDurdur(); basBtn.disabled = true; bitBtn.disabled = true; durumEl.className = 'ikm-onizle'; durumEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Merkez konumu alınıyor… (yaklaşık 10 sn kıpırdamadan bekleyin, açık alanda daha doğru olur)';
                konumAl().then(function (k) {
                    basNokta = k; $('#gp-en', m.el).value = k.enlem.toFixed(7); $('#gp-bo', m.el).value = k.boylam.toFixed(7); basBtn.disabled = false; basBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Yeniden başla'; bitBtn.disabled = false;
                    durumEl.className = 'ikm-onizle'; durumEl.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Merkez kaydedildi (' + (k.ornek || 1) + ' ölçümün ortalaması, GPS hatası ≈ ' + Math.round(k.dogruluk) + ' m)' + (k.dogruluk > 20 ? ' <b>— hata payı yüksek, açık alana çıkıp “Yeniden başla” deyin.</b>' : '.') + ' Şimdi sınıra yürüyün…';
                    izId = navigator.geolocation.watchPosition(function (p) { var d = Math.round(mesafe(basNokta.enlem, basNokta.boylam, p.coords.latitude, p.coords.longitude)); durumEl.innerHTML = '<i class="fa-solid fa-person-walking"></i> Merkezden <b>' + d + ' m</b> uzaktasınız <small>(GPS ±' + Math.round(p.coords.accuracy) + ' m)</small> — sınıra gelince <b>Bitir</b>’e basın.'; }, function () {}, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
                }).catch(function (e) { basBtn.disabled = false; durumEl.className = 'ikm-onizle uyari'; durumEl.textContent = e.message; });
            };
            bitBtn.onclick = function () {
                if (!basNokta) return; izDurdur(); bitBtn.disabled = true; basBtn.disabled = true; durumEl.className = 'ikm-onizle'; durumEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Bitiş konumu alınıyor…';
                konumAl().then(function (k) {
                    var d = mesafe(basNokta.enlem, basNokta.boylam, k.enlem, k.boylam), yar = Math.max(10, Math.ceil(d / 5) * 5);
                    $('#gp-yar', m.el).value = yar; basBtn.disabled = false;
                    durumEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> Ölçülen mesafe <b>' + Math.round(d) + ' m</b> → yarıçap <b>' + yar + ' m</b>. <small>(GPS hata payı: ±' + Math.round(basNokta.dogruluk) + ' / ±' + Math.round(k.dogruluk) + ' m — gerekirse elle değiştirin)</small>';
                }).catch(function (e) { basBtn.disabled = false; bitBtn.disabled = false; durumEl.className = 'ikm-onizle uyari'; durumEl.textContent = e.message; });
            };
            $('#gp-kaydet', m.el).onclick = function () {
                var v = { ad: $('#gp-ad', m.el).value, enlem: $('#gp-en', m.el).value, boylam: $('#gp-bo', m.el).value, yaricap: $('#gp-yar', m.el).value, ip_kisit: $('#gp-ip', m.el).value, kod_zorunlu: $('#gp-kod', m.el).checked ? 1 : 0, aktif: $('#gp-aktif', m.el).checked ? 1 : 0 };
                if (n) v.id = n.id;
                gonder('nokta_kaydet', v).then(function () { bildir('Kaydedildi.', 'basari'); m.kapat(); noktalarCiz(); }).catch(function (e) { bildir(e.message, 'hata'); });
            };
        }
        M.kayit('gc_noktalar', 'Geçiş noktaları', 'Giriş-çıkışın yapılabileceği konumlar.', noktalarCiz);

        /* ---------------- GİRİŞ-ÇIKIŞ AYARLARI ---------------- */
        function ayarlarCiz() {
            bekle();
            getir({ islem: 'ayarlar' }).then(function (j) {
                var A = j.ayarlar, cb = function (id, ad, a) { return '<label class="ikm-anahtar-satir"><span><b>' + ad + '</b><small>' + a + '</small></span><span class="ikm-anahtar"><input type="checkbox" id="' + id + '"' + (A[id] ? ' checked' : '') + '><i></i></span></label>'; };
                var secimler = function (liste, deger, nitelik) { return '<div class="ikm-secenekler">' + liste.map(function (g) { return '<label class="ikm-secenek-kutu"><input type="checkbox" ' + nitelik + '="' + g[0] + '"' + (String(deger).split(',').indexOf(String(g[0])) !== -1 ? ' checked' : '') + '><span>' + g[1] + '</span></label>'; }).join('') + '</div>'; };
                icerik.innerHTML = '<div class="ikm-izgara iki"><div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-clock"></i> Çalışma düzeni</h3></div><div class="ikm-form">' +
                    '<div class="ikm-iki"><label class="ikm-alan"><span>Günlük hedef (saat)</span><input type="number" id="hedef_saat" min="1" max="24" step="0.5" value="' + A.hedef_saat + '"></label><label class="ikm-alan"><span>En fazla konum hatası (m)</span><input type="number" id="max_dogruluk" min="10" max="1000" value="' + A.max_dogruluk + '"></label></div>' +
                    '<div class="ikm-iki"><label class="ikm-alan"><span>Mesai başlangıcı</span><input type="time" id="mesai_baslangic" value="' + esc(A.mesai_baslangic) + '"></label><label class="ikm-alan"><span>Mesai bitişi</span><input type="time" id="mesai_bitis" value="' + esc(A.mesai_bitis) + '"></label></div>' +
                    '<div class="ikm-iki"><label class="ikm-alan"><span>Tolerans (dk) <small>bu kadar gecikme “geç” sayılmaz</small></span><input type="number" id="tolerans_dk" min="0" max="120" value="' + A.tolerans_dk + '"></label><label class="ikm-alan"><span>Açık kayıt kaç saat sonra otomatik kapansın</span><input type="number" id="otomatik_kapat_saat" min="4" max="48" value="' + A.otomatik_kapat_saat + '"></label></div>' +
                    '<div class="ikm-alan"><span>Çalışma günleri</span>' + secimler([[1, 'Pzt'], [2, 'Sal'], [3, 'Çar'], [4, 'Per'], [5, 'Cum'], [6, 'Cmt'], [7, 'Paz']], A.calisma_gunleri, 'data-gun') + '</div></div></div>' +
                    '<div class="ikm-kart" style="max-height:none;overflow:visible"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-shield-halved"></i> Güvenlik</h3></div><div class="ikm-form">' +
                    '<div class="ikm-alan"><span>Hangi cihazlardan giriş-çıkış yapılabilsin?</span>' + secimler([['android', 'Android telefon'], ['ios', 'iPhone / iPad'], ['masaustu', 'Bilgisayar']], A.izinli_cihaz, 'data-cihaz') + '<small class="ikm-soluk">İzin verilmeyen cihazda personel düğmeyi hiç görmez. Asıl koruma cihaz onayı + PIN + konumdur.</small></div>' +
                    '<div class="ikm-alan tam"><span>Giriş-çıkış onay yöntemi</span><select id="yontem">' + (window.GCY ? GCY.liste.map(function (x) { return '<option value="' + x.k + '"' + (A.yontem === x.k ? ' selected' : '') + '>' + esc(x.ad) + '</option>'; }).join('') : '') + '</select><small class="ikm-soluk" id="yontem-ac"></small><small class="ikm-soluk">Seçim tüm personelin giriş ekranında geçerli olur; konum, PIN, cihaz ve parmak izi doğrulamaları değişmez.</small></div>' +
                    cb('konum_zorunlu', 'Girişte konum zorunlu', 'Kapatılırsa konum doğrulanmaz (kayıt “konumsuz” işaretlenir).') + cb('cikis_konum', 'Çıkışta da konum zorunlu', 'Kapalıysa çıkış ofis dışından yapılabilir.') + cb('pin_zorunlu', 'Her girişte kişisel PIN iste', 'Hesabı ele geçiren kişinin adınıza damga vurmasını engeller.') +
                    cb('webauthn_zorunlu', 'Her girişte parmak izi / Face ID iste', 'Personel telefonunda bir kez kaydeder; sonraki her giriş-çıkışta biyometrik doğrulama ister (HTTPS gerekir).') + cb('cihaz_zorunlu', 'Yalnızca onaylı cihazdan giriş', 'Başka telefondan damga vurulamaz.') + cb('cihaz_ilk_otomatik', 'İlk cihazı otomatik onayla', 'Kapalıysa her yeni cihaz onayınızı bekler.') + '</div></div></div>' +
                    '<div class="ikm-kart" style="max-height:none;overflow:visible;grid-column:1/-1"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-envelope-open-text"></i> Günlük giriş raporu (e-posta)</h3></div><div class="ikm-form">' +
                    cb('gunluk_rapor', 'Her gün giriş yapmayanları e-posta ile bildir', 'Belirlediğiniz saatte, o gün henüz giriş yapmamış personelin listesi aşağıdaki adreslere gönderilir. İzinli / raporlu kişiler ve mesaisi henüz başlamamış kişiler listeye girmez; çalışma günü olmayan günlerde gönderilmez.') +
                    '<div class="ikm-iki"><label class="ikm-alan"><span>Gönderim saati</span><input type="time" id="gunluk_rapor_saat" value="' + esc(A.gunluk_rapor_saat || '11:00') + '"></label>' +
                    '<label class="ikm-alan"><span>Alıcı e-posta adres(ler)i <small>virgülle ayırın, en fazla 10</small></span><input type="text" id="gunluk_rapor_alicilar" placeholder="ornek@kurum.com, ikinci@kurum.com" value="' + esc(A.gunluk_rapor_alicilar || '') + '"></label></div>' +
                    cb('gunluk_rapor_gec', 'Geç giriş yapanları da göster', 'Mailin altına, mesai başlangıcı + toleranstan sonra giriş yapanlar ve giriş saatleri eklenir.') +
                    '<div class="ikm-alan tam" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap"><button type="button" class="ikm-btn" id="gr-test"><i class="fa-solid fa-paper-plane"></i> Şimdi deneme gönder</button><small class="ikm-soluk" id="gr-son">' + (A.gunluk_rapor_sonuc ? 'Son gönderim: ' + esc(A.gunluk_rapor_sonuc) : 'Henüz gönderilmedi.') + ' Gönderim için İK ayarlarında e-posta (gönderen / SMTP) tanımlı olmalıdır.</small></div></div></div>' +
                    '<div class="ikm-akis-alt"><button type="button" class="ikm-btn ikm-btn-ana" id="ga-kaydet"><i class="fa-solid fa-floppy-disk"></i> Ayarları kaydet</button></div>';
                (function () { var yo = $('#yontem'), ya = $('#yontem-ac'); if (!yo || !ya || !window.GCY) return; var g = function () { var x = GCY.liste.filter(function (z) { return z.k === yo.value; })[0]; ya.textContent = x ? x.ac : ''; }; yo.onchange = function () { g(); if (yo.value === 'parmak' && $('#webauthn_zorunlu')) $('#webauthn_zorunlu').checked = true; }; g(); })();
                $('#gr-test').onclick = function () {
                    var b = this; b.disabled = true;
                    gonder('rapor_mail_test', { alicilar: $('#gunluk_rapor_alicilar').value, gec: $('#gunluk_rapor_gec').checked ? 1 : 0 }).then(function (r) { bildir(r.mesaj || 'Gönderildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); }).then(function () { b.disabled = false; });
                };
                $('#ga-kaydet').onclick = function () {
                    var v = {}; ['hedef_saat', 'max_dogruluk', 'mesai_baslangic', 'mesai_bitis', 'tolerans_dk', 'otomatik_kapat_saat'].forEach(function (a) { v[a] = $('#' + a).value; });
                    if ($('#yontem')) v.yontem = $('#yontem').value;
                    v.calisma_gunleri = $$('[data-gun]', icerik).filter(function (c) { return c.checked; }).map(function (c) { return c.dataset.gun; }).join(',');
                    v.izinli_cihaz = $$('[data-cihaz]', icerik).filter(function (c) { return c.checked; }).map(function (c) { return c.dataset.cihaz; }).join(',');
                    ['konum_zorunlu', 'cikis_konum', 'pin_zorunlu', 'webauthn_zorunlu', 'cihaz_zorunlu', 'cihaz_ilk_otomatik', 'gunluk_rapor', 'gunluk_rapor_gec'].forEach(function (a) { v[a] = $('#' + a).checked ? 1 : 0; });
                    v.gunluk_rapor_saat = $('#gunluk_rapor_saat').value; v.gunluk_rapor_alicilar = $('#gunluk_rapor_alicilar').value;
                    gonder('ayar_kaydet', v).then(function () { bildir('Ayarlar kaydedildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); });
                };
            }).catch(hata);
        }
        M.kayit('gc_ayarlar', 'Giriş-çıkış ayarları', 'Mesai saatleri, tolerans ve güvenlik kuralları.', ayarlarCiz);

        // Bekleyen cihaz rozeti
        if (YONET) getir({ islem: 'bugun' }).then(function (j) { var rz = $('#ikm-r-cihaz'); if (rz && j.bekleyen_cihaz) { rz.hidden = false; rz.textContent = j.bekleyen_cihaz; } }).catch(function () {});
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', basla); else basla();
})();
