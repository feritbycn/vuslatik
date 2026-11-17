/* ==========================================================================
   tb-ik-izin.js — İzinler sayfası: üst sekmeler (İzinler · Raporlar · Kurallar · Onay Süreçleri · Takvim)
   Raporlar: İzin Bakiye Raporu + Kullanılan İzin Raporu (ay/yıl, Excel/CSV indir). Kurallar: izin türleri ve devir kuralı.
   ========================================================================== */
(function () {
    'use strict';
    function basla() {
        var M = window.IKM; if (!M || !M.kayit || !M.istek) return setTimeout(basla, 30);
        var $ = M.$, $$ = M.$$, esc = M.esc, getir = M.getir, bildir = M.bildir, icerik = M.icerik, tarih = M.tarih, sayi = M.sayi;
        var AY = M.aylar || ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        var GRUP = ['izinler', 'izin_rapor', 'izin_kural', 'akis', 'izin_takvimi'];
        var F = { rapor: 'bakiye', ay: new Date().getMonth() + 1, yil: new Date().getFullYear(), ara: '' };
        function Y() { return M.yetki() || {}; }
        function yonetici() { return !!(Y().ik_personel_gor || Y().ik_izin_onay); }
        function g(n) { return !!$('#ik-sekmeler .ikm-nav[data-s="' + n + '"]'); }
        function sayiN(n) { return (Math.round((+n || 0) * 100) / 100).toLocaleString('tr-TR', { maximumFractionDigits: 2 }); }

        /* ---------- üst sekme çubuğu: sayfa içeriği yeniden çizilse bile başa eklenir ---------- */
        var AKTIF = function () { var m = (location.search.match(/sekme=([a-z_]+)/) || [])[1]; return m; };
        function grupBul(s) {
            if (GRUP.indexOf(s) >= 0) {
                var t = [['izinler', 'İzinler']];
                if (yonetici()) t.push(['izin_rapor', 'Raporlar'], ['izin_kural', 'Kurallar']);
                if (g('akis')) t.push(['akis', 'Onay Süreçleri']);
                if (g('izin_takvimi')) t.push(['izin_takvimi', 'Takvim']);
                return { nav: 'izinler', tabs: t };
            }
            if (s === 'birimler' || s === 'unvanlar') return { nav: 'birimler', tabs: [['birimler', 'Departmanlar'], ['unvanlar', 'Unvanlar']] };
            return null;
        }
        function cubuk(s, gr) {
            if (gr.tabs.length < 2) return '';
            return '<nav class="izt-sekme" role="tablist">' + gr.tabs.map(function (x) { return '<button type="button" role="tab" data-izt="' + x[0] + '"' + (x[0] === s ? ' class="aktif" aria-selected="true"' : '') + '>' + x[1] + '</button>'; }).join('') + '</nav>';
        }
        var kur = false;
        function ekle() {
            var s = AKTIF(), gr = s && grupBul(s); if (!gr) return;
            var f = icerik.firstElementChild;
            if (f && f.classList.contains('izt-sekme') && f.querySelector('.aktif') && f.querySelector('.aktif').dataset.izt === s) return;
            var eski = icerik.querySelector(':scope > .izt-sekme'); if (eski) eski.remove();
            var h = cubuk(s, gr); if (!h) return; icerik.insertAdjacentHTML('afterbegin', h);
            $$('#ik-sekmeler .ikm-nav[data-s]').forEach(function (b) { b.classList.toggle('aktif', b.dataset.s === gr.nav); });
        }
        new MutationObserver(function () { if (kur) return; kur = true; try { ekle(); } finally { kur = false; } }).observe(icerik, { childList: true });
        icerik.addEventListener('click', function (e) { var b = e.target.closest('[data-izt]'); if (b && b.closest('.izt-sekme')) { e.stopPropagation(); M.sekmeAc(b.dataset.izt); } }, true);

        function govde(html) { var c = icerik.querySelector(':scope > .izt-govde'); if (!c) { icerik.innerHTML = '<div class="izt-govde"></div>'; c = icerik.firstElementChild; } c.innerHTML = html; return c; }


        /* ---------- Modern dosya yükleme alanı: pencerelerdeki tüm dosya seçicileri sürükle-bırak kutusuna çevirir ---------- */
        function dzBoyut(b) { return b > 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
        function dzKur(inp) {
            if (inp.dataset.dz || inp.closest('.dz')) return; inp.dataset.dz = '1';
            var uz = (inp.getAttribute('accept') || '').split(',').map(function (x) { return x.trim().replace(/^\./, '').toUpperCase(); }).filter(Boolean).join(' · ');
            var w = document.createElement('div'); w.className = 'dz'; inp.parentNode.insertBefore(w, inp); w.appendChild(inp); inp.classList.add('dz-giris');
            var v = document.createElement('div'); v.className = 'dz-yuz';
            v.innerHTML = '<span class="dz-ikon"><i class="fa-solid fa-cloud-arrow-up"></i></span><b class="dz-baslik">' + (inp.multiple ? 'Dosyaları buraya sürükleyin' : 'Dosyayı buraya sürükleyin') + '</b><span class="dz-alt">ya da <u>bilgisayardan seçin</u>' + (uz ? ' · ' + esc(uz) : '') + '</span><div class="dz-liste"></div>';
            w.appendChild(v);
            ['dragenter', 'dragover'].forEach(function (o) { inp.addEventListener(o, function () { w.classList.add('dz-uzerinde'); }); });
            ['dragleave', 'drop', 'change'].forEach(function (o) { inp.addEventListener(o, function () { w.classList.remove('dz-uzerinde'); }); });
            inp.addEventListener('change', function () {
                var L = Array.prototype.slice.call(inp.files || []), l = v.querySelector('.dz-liste'), t = L.reduce(function (a, f) { return a + f.size; }, 0);
                w.classList.toggle('dz-dolu', L.length > 0);
                v.querySelector('.dz-baslik').textContent = L.length ? (L.length === 1 ? L[0].name : L.length + ' dosya seçildi') : (inp.multiple ? 'Dosyaları buraya sürükleyin' : 'Dosyayı buraya sürükleyin');
                l.innerHTML = L.length ? (L.length > 1 ? '<span class="dz-cip">' + dzBoyut(t) + ' toplam</span>' : '<span class="dz-cip">' + dzBoyut(t) + '</span>') + L.slice(0, 4).map(function (f) { return '<span class="dz-cip"><i class="fa-regular fa-file"></i> ' + esc(f.name.length > 28 ? f.name.slice(0, 26) + '…' : f.name) + '</span>'; }).join('') + (L.length > 4 ? '<span class="dz-cip">+' + (L.length - 4) + '</span>' : '') : '';
            });
        }
        function dzTara() { $$('.ikm-modal input[type=file], .ikm-cekmece input[type=file]').forEach(function (i) { if (i.type === 'file' && !i.hidden && i.style.display !== 'none') dzKur(i); }); }
        new MutationObserver(function () { dzTara(); }).observe(document.body, { childList: true, subtree: true });
        dzTara();

        /* ---------- Raporlar ---------- */
        function csv(ad, baslik, satirlar) {
            var q = function (v) { v = String(v == null ? '' : v); return /[;"\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; };
            var t = '﻿' + [baslik].concat(satirlar).map(function (r) { return r.map(q).join(';'); }).join('\r\n');
            var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([t], { type: 'text/csv;charset=utf-8' })); a.download = ad + '.csv'; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
        }
        function raporCiz() {
            var yil = new Date().getFullYear();
            govde('<div class="izt-arac"><div class="ikm-segment" id="izr-tur"><button type="button" data-t="bakiye"' + (F.rapor === 'bakiye' ? ' class="aktif"' : '') + '>İzin Bakiye Raporu</button><button type="button" data-t="kullanilan"' + (F.rapor === 'kullanilan' ? ' class="aktif"' : '') + '>Kullanılan İzin Raporu</button></div>' +
                '<div class="izt-sag"><input type="search" id="izr-ara" placeholder="Ara…" value="' + esc(F.ara) + '">' +
                (F.rapor === 'kullanilan' ? '<select id="izr-ay">' + AY.map(function (a, i) { return '<option value="' + (i + 1) + '"' + (F.ay === i + 1 ? ' selected' : '') + '>' + a + '</option>'; }).join('') + '</select><select id="izr-yil">' + [yil + 1, yil, yil - 1, yil - 2, yil - 3].map(function (y) { return '<option' + (F.yil === y ? ' selected' : '') + '>' + y + '</option>'; }).join('') + '</select>' : '') +
                '<button type="button" class="ikm-btn" id="izr-indir"><i class="fa-solid fa-download"></i> İndir (Excel)</button></div></div><div id="izr-liste">' + M.yukleniyor() + '</div>');
            $$('#izr-tur button').forEach(function (b) { b.onclick = function () { F.rapor = b.dataset.t; raporCiz(); }; });
            var ara = $('#izr-ara'); if (ara) ara.oninput = function () { F.ara = this.value; ciz(); };
            var ay = $('#izr-ay'), yl = $('#izr-yil'); if (ay) ay.onchange = function () { F.ay = +this.value; raporCiz(); }; if (yl) yl.onchange = function () { F.yil = +this.value; raporCiz(); };
            var VERI = null;
            function nz(s) { return String(s || '').toLocaleLowerCase('tr-TR'); }
            function suz() { var a = nz(F.ara); return (VERI || []).filter(function (r) { return !a || nz(r.ad_soyad + ' ' + r.tc + ' ' + (r.birim || '') + ' ' + (r.tur || '')).indexOf(a) >= 0; }); }
            function ciz() {
                var l = $('#izr-liste'); if (!l || !VERI) return; var L = suz();
                if (!L.length) { l.innerHTML = M.bosKutu('fa-table', 'Kayıt yok', F.rapor === 'bakiye' ? 'Listelenecek personel bulunamadı.' : AY[F.ay - 1] + ' ' + F.yil + ' için izin kaydı yok.'); return; }
                var th, tr;
                if (F.rapor === 'bakiye') {
                    th = ['Sıra', 'Ad Soyad', 'Kimlik Numarası', 'Birim', 'İzin Dönemi', 'Devreden', 'Dönem Hakkı', 'Toplam Hak', 'Kullanılan', 'Bekleyen', 'Kalan'];
                    tr = L.map(function (r, i) { return '<tr><td>' + (i + 1) + '</td><td><a href="#" data-kisi="' + r.id + '">' + esc(r.ad_soyad) + '</a></td><td>' + esc(r.tc || '-') + '</td><td>' + esc(r.birim || '-') + '</td><td>' + (r.donem_bas ? tarih(r.donem_bas) + ' – ' + tarih(r.donem_bit) : '-') + '</td><td>' + sayiN(r.devir) + '</td><td>' + sayiN(r.hak) + '</td><td>' + sayiN(r.toplam) + '</td><td>' + sayiN(r.kullanilan) + '</td><td>' + sayiN(r.bekleyen) + '</td><td class="' + (r.kalan < 0 ? 'izt-eksi' : 'izt-kalan') + '"><b>' + sayiN(r.kalan) + '</b></td></tr>'; });
                } else {
                    var D = { onaylandi: 'Onaylı', beklemede: 'Onay Bekliyor', reddedildi: 'Reddedilmiş' };
                    th = ['Sıra', 'Ad Soyad', 'Kimlik Numarası', 'İzin Türü', 'Başlangıç', 'Bitiş', 'Kullanılan Gün', 'Açıklama', 'Onay Durumu'];
                    tr = L.map(function (r, i) { return '<tr><td>' + (i + 1) + '</td><td>' + esc(r.ad_soyad) + '</td><td>' + esc(r.tc || '-') + '</td><td>' + esc(r.tur) + '</td><td>' + tarih(r.baslangic) + '</td><td>' + tarih(r.bitis) + '</td><td>' + sayiN(r.gun) + '</td><td>' + esc(r.aciklama || '-') + '</td><td><span class="izt-d izt-' + r.durum + '">' + D[r.durum] + '</span></td></tr>'; });
                }
                l.innerHTML = '<h2 class="izt-baslik">' + (F.rapor === 'bakiye' ? 'İzin Bakiye Raporu' : 'Kullanılan İzin Raporu (' + AY[F.ay - 1] + ' ' + F.yil + ')') + '</h2><div class="izt-tablo-kap"><table class="izt-tablo"><thead><tr>' + th.map(function (x) { return '<th>' + x + '</th>'; }).join('') + '</tr></thead><tbody>' + tr.join('') + '</tbody></table></div><p class="ikm-soluk izt-alt">' + L.length + ' kayıt</p>';
            }
            icerik.onclick = function (e) { var a = e.target.closest('[data-kisi]'); if (a) { e.preventDefault(); M.kartAc(+a.dataset.kisi); } };
            $('#izr-indir').onclick = function () {
                var L = suz(); if (!L.length) return bildir('İndirilecek kayıt yok.', 'uyari');
                if (F.rapor === 'bakiye') csv('izin-bakiye-raporu', ['Ad Soyad', 'Kimlik No', 'Birim', 'Dönem başı', 'Dönem sonu', 'Devreden', 'Dönem hakkı', 'Toplam hak', 'Kullanılan', 'Bekleyen', 'Kalan'], L.map(function (r) { return [r.ad_soyad, r.tc, r.birim, r.donem_bas, r.donem_bit, r.devir, r.hak, r.toplam, r.kullanilan, r.bekleyen, r.kalan]; }));
                else csv('kullanilan-izin-' + F.yil + '-' + F.ay, ['Ad Soyad', 'Kimlik No', 'İzin türü', 'Başlangıç', 'Bitiş', 'Kullanılan gün', 'Açıklama', 'Durum'], L.map(function (r) { return [r.ad_soyad, r.tc, r.tur, r.baslangic, r.bitis, r.gun, r.aciklama, r.durum]; }));
            };
            getir({ islem: 'izin_rapor', tur: F.rapor, ay: F.ay, yil: F.yil }).then(function (j) { VERI = j.liste; ciz(); }).catch(function (e) { var l = $('#izr-liste'); if (l) l.innerHTML = M.hataKutu(e.message); });
        }
        M.kayit('izin_rapor', 'İzinler', 'İzin bakiyesi ve kullanılan izin raporları.', raporCiz);

        /* ---------- Unvanlar (Şirket yapısı) ---------- */
        var UF = { ara: '' };
        function unvanCiz() {
            var duz = !!Y().ik_duzenle;
            govde('<div class="izt-arac"><div class="ikm-ara"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="uv-ara" placeholder="Unvan ara…" value="' + esc(UF.ara) + '"></div>' + (duz ? '<button type="button" class="ikm-btn ikm-btn-ana" id="uv-yeni"><i class="fa-solid fa-plus"></i> Yeni unvan</button>' : '') + '</div><p class="izt-giris">Burada oluşturduğunuz unvanlar, personel kartında ve içe aktarmada seçenek olarak çıkar. Kişi bazlı erişim kurallarında da “Unvan” seçilebilir.</p><div id="uv-liste">' + M.yukleniyor() + '</div>');
            var L = [];
            function nz(t) { return String(t || '').toLocaleLowerCase('tr-TR'); }
            function ciz() {
                var a = nz(UF.ara), l = L.filter(function (x) { return !a || nz(x.ad).indexOf(a) >= 0; }), k = $('#uv-liste'); if (!k) return;
                k.innerHTML = l.length ? '<div class="izt-tablo-kap"><table class="izt-tablo"><thead><tr><th>Adı</th><th>Çalışan sayısı</th><th>Çalışanlar</th>' + (duz ? '<th></th>' : '') + '</tr></thead><tbody>' + l.map(function (x) {
                    return '<tr><td><b>' + esc(x.ad) + '</b></td><td>' + x.n + ' çalışan</td><td><span class="izt-av">' + (x.uyeler || []).map(function (u) { return M.av(u.ad, u.renk, u.resim); }).join('') + (x.n > 4 ? '<em>+' + (x.n - 4) + '</em>' : '') + '</span></td>' +
                        (duz ? '<td class="izt-eylem"><button type="button" class="ikm-btn ikm-btn-kucuk" data-ad="' + esc(x.ad) + '" data-e="adlandir" title="Yeniden adlandır"><i class="fa-solid fa-pen"></i></button> <button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-ad="' + esc(x.ad) + '" data-e="sil" title="Sil"><i class="fa-solid fa-trash"></i></button></td>' : '') + '</tr>';
                }).join('') + '</tbody></table></div>' : M.bosKutu('fa-id-badge', 'Unvan yok', duz ? '“Yeni unvan” ile ekleyin.' : '');
            }
            function yukle() { getir({ islem: 'unvanlar' }).then(function (j) { L = j.liste; ciz(); }).catch(function (e) { var k = $('#uv-liste'); if (k) k.innerHTML = M.hataKutu(e.message); }); }
            $('#uv-ara').oninput = function () { UF.ara = this.value; ciz(); };
            var yb = $('#uv-yeni'); if (yb) yb.onclick = function () {
                M.soru({ baslik: 'Yeni unvan', etiket: 'Unvan adı', uzun: false, zorunlu: true, evet: 'Ekle', ikon: 'fa-id-badge', yer: 'Örn. Muhasebe Uzmanı' }).then(function (ad) { if (ad) return M.gonder('unvan_olustur', { ad: ad }).then(function () { bildir('Unvan eklendi.', 'basari'); M.yenileVeri(); yukle(); }); }).catch(function (e) { bildir(e.message, 'hata'); });
            };
            $('#uv-liste').onclick = function (e) {
                var b = e.target.closest('[data-e]'); if (!b) return; var ad = b.dataset.ad;
                if (b.dataset.e === 'adlandir') M.soru({ baslik: 'Unvanı yeniden adlandır', etiket: 'Yeni ad', uzun: false, zorunlu: true, evet: 'Kaydet', ikon: 'fa-pen', deger: ad }).then(function (yeni) { if (yeni && yeni !== ad) return M.gonder('unvan_adlandir', { eski: ad, yeni: yeni }).then(function () { bildir('Unvan güncellendi (kişi kartları da değişti).', 'basari'); M.yenileVeri(); yukle(); }); }).catch(function (er) { bildir(er.message, 'hata'); });
                else M.onayla({ baslik: 'Unvan silinsin mi?', mesaj: '“' + ad + '” unvanı listeden kaldırılacak. Unvanı taşıyan kişi varsa silinemez.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return M.gonder('unvan_sil', { ad: ad }).then(function () { bildir('Unvan silindi.', 'basari'); M.yenileVeri(); yukle(); }); }).catch(function (er) { bildir(er.message, 'hata'); });
            };
            yukle();
        }
        M.kayit('unvanlar', 'Şirket yapısı', 'Departmanları ve unvanları oluşturun; personele buradan atayın.', unvanCiz);

        /* ---------- Kurallar ---------- */
        function kuralCiz() {
            var tur = M.veri && M.veri() && M.veri().izin_turleri; // yoksa sabit liste
            var N = (M.veri() && M.veri().ozet && M.veri().ozet.personel) || 0;
            govde('<p class="izt-giris">İzin türlerini ve hak ediş kurallarını burada görürsünüz. Kural, tüm çalışanlara uygulanır.</p>' +
                '<div class="izt-tablo-kap"><table class="izt-tablo"><thead><tr><th>Adı</th><th>İzin Türleri</th><th>Çalışan Sayısı</th><th>Açıklama</th></tr></thead><tbody><tr class="izt-tik" id="izk-satir"><td><a href="#">Türk İş Kanunu Kuralı</a> <span class="izt-varsayilan">Varsayılan</span></td><td>9 tür</td><td>' + N + ' çalışan</td><td>İzin kuralı ve türleri Türk İş Kanunu’nu temel almaktadır.</td></tr></tbody></table></div>');
            $('#izk-satir').onclick = function (e) {
                e.preventDefault();
                M.modal({ baslik: 'Türk İş Kanunu Kuralı', ikon: 'fa-scale-balanced', gen: 560, govde:
                    '<h4 class="izt-h">Yıllık izin hakkı (hizmet süresine göre)</h4><table class="izt-tablo izt-mini"><tbody><tr><td>1 yıldan az</td><td>Hak yok</td></tr><tr><td>1 – 5 yıl (5 hariç)</td><td>14 iş günü</td></tr><tr><td>5 – 15 yıl (15 hariç)</td><td>20 iş günü</td></tr><tr><td>15 yıl ve üzeri</td><td>26 iş günü</td></tr></tbody></table>' +
                    '<h4 class="izt-h">Devir</h4><p class="ikm-soluk">Kullanılmayan yıllık izin bir sonraki döneme <b>devreder</b>. Dönem, “izne esas hak ediş” (işe giriş) yıldönümüne göre başlar; kalan = devreden + dönem hakkı − kullanılan. “İzin aktar” ile yüklenen bakiye raporu başlangıç devrini belirler.</p>' +
                    '<h4 class="izt-h">İzin türleri</h4><div class="izt-turler">' + ['Yıllık izin', 'Mazeret izni', 'Hastalık / rapor', 'Ücretsiz izin', 'Doğum izni', 'Babalık izni', 'Evlilik izni', 'Vefat izni', 'İdari izin'].map(function (x) { return '<span class="ikm-cip">' + x + '</span>'; }).join('') + '</div>' });
            };
        }
        M.kayit('izin_kural', 'İzinler', 'İzin türleri ve hak ediş kuralları.', kuralCiz);
    }
    basla();
})();
