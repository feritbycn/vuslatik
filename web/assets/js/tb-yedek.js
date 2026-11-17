/* ==========================================================================
   tb-yedek.js — Kurum yedekleri: liste · yedek al · içe aktar · geri yükle (tam / birleştir) · durdur · geri yükleme talebi
   Hem platform paneli hem kurumun içi (destek girişi / kurum yetkilisi) kullanır.
   ctx: { liste(): Promise<{yedekler,islemler,son,talepler,...}>, cagir(islem, veri, dosya): Promise, indir(id): url,
          bildir, onayla, modal, esc, yenile(): void, kurum: 'Ad' }
   ========================================================================== */
(function () {
    'use strict';
    var TUR = { otomatik: ['Otomatik', 'mavi', 'fa-clock-rotate-left'], elle: ['Elle', 'mor', 'fa-hand-pointer'], oncesi: ['Geri yükleme öncesi', 'turuncu', 'fa-shield-halved'], ice: ['İçe aktarılan', 'gri', 'fa-file-import'] };
    var ISL = { calisiyor: ['Çalışıyor', 'mavi'], hazir: ['Onay bekliyor', 'turuncu'], bitti: ['Tamamlandı', 'yesil'], hata: ['Hata', 'kirmizi'], iptal: ['Durduruldu', 'gri'] };
    var TALEP = { bekliyor: ['Bekliyor', 'turuncu'], tamamlandi: ['Tamamlandı', 'yesil'], reddedildi: ['Reddedildi', 'kirmizi'], iptal: ['İptal', 'gri'] };
    var TAD = { kullanicilar: 'Kullanıcılar', ik_izinler: 'İzin talepleri', ik_avanslar: 'Avans talepleri', ik_masraflar: 'Masraflar', ik_belgeler: 'Özlük evrakları', ik_zimmetler: 'Zimmetler', ik_egitimler: 'Eğitimler', ik_performans: 'Performans', ik_ayarlar: 'Ayarlar', ik_bildirimler: 'Bildirimler', giris_cikis: 'Giriş-çıkış', ik_personel: 'Personel kartları' };
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function boyut(b) { b = +b || 0; return b > 1048576 ? (b / 1048576).toFixed(1).replace('.', ',') + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }
    function tarihSaat(t) { var m = String(t || '').match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2}))?/); return m ? m[3] + '.' + m[2] + '.' + m[1] + (m[4] ? ' ' + m[4] + ':' + m[5] : '') : '—'; }
    function once(t) {
        var m = String(t || '').match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})/); if (!m) return '';
        var s = (Date.now() - new Date(+m[1], m[2] - 1, +m[3], +m[4], +m[5]).getTime()) / 1000;
        if (s < 90) return 'az önce'; if (s < 3600) return Math.round(s / 60) + ' dk önce'; if (s < 86400) return Math.round(s / 3600) + ' saat önce'; return Math.round(s / 86400) + ' gün önce';
    }
    function cip(renk, yazi, ikon) { return '<span class="ikm-cip ' + renk + '">' + (ikon ? '<i class="fa-solid ' + ikon + '"></i> ' : '') + esc(yazi) + '</span>'; }
    function tabloAd(t) { return TAD[t] || t.replace(/^ik_/, '').replace(/_/g, ' '); }
    var noop = function () {};

    /* ───────── Yönetici paneli (liste + işlemler) ───────── */
    function panel(kap, ctx) {
        var yukleniyor = false, izleme = null;
        function yukle() {
            return ctx.liste().then(function (j) { cizYedek(j); }).catch(function (e) { kap.innerHTML = '<div class="ikm-uyari"><i class="fa-solid fa-triangle-exclamation"></i> ' + esc(e.message) + '</div>'; });
        }
        function izleBasla() { izleBitir(); izleme = setInterval(function () { ctx.liste().then(function (j) { var k = kap.querySelector('#yd-islem'); if (k) k.innerHTML = islemHtml(j.islemler || []); }).catch(noop); }, 1500); }
        function izleBitir() { if (izleme) { clearInterval(izleme); izleme = null; } }
        function islemHtml(l) {
            l = l.filter(function (r) { return r.durum === 'calisiyor' || r.durum === 'hazir'; });
            return l.map(function (r) {
                return '<div class="ikm-satir yd-islem"><span class="ikm-satir-ikon"><i class="fa-solid ' + (r.durum === 'calisiyor' ? 'fa-gear fa-spin' : 'fa-hourglass-half') + '"></i></span><span class="ikm-satir-ic"><b>' + (r.tur === 'yedek' ? 'Yedekleniyor' : 'Geri yükleme') + '</b><small>' + esc(r.mesaj || '') + ' · ' + tarihSaat(r.baslangic) + '</small></span>' + cip(ISL[r.durum][1], ISL[r.durum][0]) +
                    '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-durdur="' + r.id + '"><i class="fa-solid fa-stop"></i> Durdur</button></div>';
            }).join('');
        }
        function cizYedek(j) {
            var Y = j.yedekler || [], son = j.son;
            var h = '<div class="ikm-kpiler">' +
                '<div class="ikm-kpi mavi"><span class="ikm-kpi-ikon"><i class="fa-solid fa-clock-rotate-left"></i></span><div><b>' + (son ? tarihSaat(son) : 'Yok') + '</b><span>Son yedek' + (son ? ' · ' + once(son) : '') + '</span></div></div>' +
                '<div class="ikm-kpi yesil"><span class="ikm-kpi-ikon"><i class="fa-solid fa-database"></i></span><div><b>' + Y.length + '</b><span>Saklanan yedek</span></div></div></div>' +
                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-box-archive"></i> Yedekler</h3><div class="yd-araclar">' +
                '<label class="ikm-btn ikm-btn-kucuk" title="Daha önce indirilmiş bir kurum yedeğini (.zip) ekler"><i class="fa-solid fa-file-import"></i> İçe aktar<input type="file" accept=".zip" id="yd-ice" hidden></label>' +
                '<button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" id="yd-al"><i class="fa-solid fa-cloud-arrow-down"></i> Verileri yedekle</button></div></div>' +
                '<div id="yd-islem">' + islemHtml(j.islemler || []) + '</div><div class="ikm-liste">' +
                (Y.length ? Y.map(function (y) {
                    var t = TUR[y.tur] || TUR.elle;
                    return '<div class="ikm-satir yd-satir"><span class="ikm-satir-ikon"><i class="fa-solid ' + t[2] + '"></i></span><span class="ikm-satir-ic"><b>' + tarihSaat(y.olusturma) + '</b><small>' + (y.kayit || 0) + ' kayıt · ' + (y.tablolar || 0) + ' tablo · ' + boyut(y.boyut) + (y.aciklama ? ' · ' + esc(y.aciklama) : '') + '</small></span>' + cip(t[1], t[0]) +
                        '<span class="yd-eylem"><a class="ikm-btn ikm-btn-kucuk" href="' + esc(ctx.indir(y.id)) + '" title="İndir"><i class="fa-solid fa-download"></i></a>' +
                        '<button type="button" class="ikm-btn ikm-btn-kucuk" data-geri="' + y.id + '"><i class="fa-solid fa-rotate-left"></i> Geri yükle</button>' +
                        '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" data-sil="' + y.id + '" title="Sil"><i class="fa-solid fa-trash"></i></button></span></div>';
                }).join('') : '<div class="ikm-bos"><span><i class="fa-solid fa-box-open"></i></span><b>Henüz yedek yok</b><p>“Verileri yedekle” ile bu kurumun şu anki halini kaydedin.</p></div>') + '</div></div>' +
                ((j.islemler || []).filter(function (r) { return r.durum !== 'calisiyor' && r.durum !== 'hazir'; }).length ? '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-list-check"></i> Son işlemler</h3></div><div class="ikm-liste">' +
                    j.islemler.filter(function (r) { return r.durum !== 'calisiyor' && r.durum !== 'hazir'; }).slice(0, 5).map(function (r) { return '<div class="ikm-satir"><span class="ikm-satir-ic"><b>' + (r.tur === 'yedek' ? 'Yedekleme' : 'Geri yükleme') + '</b><small>' + tarihSaat(r.baslangic) + ' · ' + esc(r.mesaj || '') + '</small></span>' + cip(ISL[r.durum][1], ISL[r.durum][0]) + '</div>'; }).join('') + '</div></div>' : '');
            kap.innerHTML = h;
            var al = kap.querySelector('#yd-al');
            al.onclick = function () {
                if (yukleniyor) return; yukleniyor = true; al.disabled = true; al.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Yedekleniyor…'; izleBasla();
                ctx.cagir('yedek_al', {}).then(function () { ctx.bildir('Yedek alındı.', 'basari'); }).catch(function (e) { ctx.bildir(e.message, 'hata'); }).then(function () { yukleniyor = false; izleBitir(); yukle(); });
            };
            kap.querySelector('#yd-ice').onchange = function () {
                var d = this.files[0]; if (!d) return; ctx.bildir('Yedek okunuyor…', 'bilgi');
                ctx.cagir('yedek_ice_al', {}, d).then(function () { ctx.bildir('Yedek listeye eklendi. Şimdi “Geri yükle” ile kullanabilirsiniz.', 'basari'); yukle(); }).catch(function (e) { ctx.bildir(e.message, 'hata'); });
            };
            [].forEach.call(kap.querySelectorAll('[data-durdur]'), function (b) { b.onclick = function () { b.disabled = true; ctx.cagir('yedek_islem_durdur', { islem_id: b.dataset.durdur }).then(function () { ctx.bildir('Durdurma isteği gönderildi.', 'basari'); setTimeout(yukle, 700); }).catch(function (e) { ctx.bildir(e.message, 'hata'); b.disabled = false; }); }; });
            [].forEach.call(kap.querySelectorAll('[data-sil]'), function (b) {
                b.onclick = function () { ctx.onayla({ baslik: 'Yedek silinsin mi?', mesaj: 'Bu yedek kalıcı olarak silinir.', evet: 'Sil', tur: 'tehlike' }).then(function (ok) { if (ok) return ctx.cagir('yedek_sil', { id: b.dataset.sil }).then(function () { ctx.bildir('Yedek silindi.', 'basari'); yukle(); }); }).catch(function (e) { ctx.bildir(e.message, 'hata'); }); };
            });
            [].forEach.call(kap.querySelectorAll('[data-geri]'), function (b) { b.onclick = function () { var y = Y.filter(function (x) { return String(x.id) === b.dataset.geri; })[0]; geriYukle(y); }; });
            if ((j.islemler || []).some(function (r) { return r.durum === 'calisiyor'; })) izleBasla();
        }

        /* ───── geri yükleme sihirbazı ───── */
        function geriYukle(y) {
            var mod = 'degistir', islemId = 0, bitti = false, poll = null;
            var m = ctx.modal({ baslik: 'Yedeği geri yükle', ikon: 'fa-rotate-left', gen: 620, sabit: true, govde: '', alt: '' });
            var govde = m.el.querySelector('.ikm-modal-govde'), alt = m.el.querySelector('.ikm-modal-alt') || (function () { var a = document.createElement('div'); a.className = 'ikm-modal-alt'; m.el.querySelector('.ikm-modal').appendChild(a); return a; })();
            // pencere herhangi bir yolla kapanırsa hazırlanmış geçici veriyi temizle
            var mo = new MutationObserver(function () { if (!document.body.contains(m.el)) { mo.disconnect(); if (poll) clearInterval(poll); if (!bitti && islemId) ctx.cagir('yedek_islem_durdur', { islem_id: islemId }).catch(noop); if (bitti === 'tamam') yukle(); } });
            mo.observe(document.body, { childList: true });
            var kapat = function () { m.kapat(); };
            function adim1() {
                govde.innerHTML = '<p class="yd-not"><b>' + tarihSaat(y.olusturma) + '</b> tarihli yedek (' + (y.kayit || 0) + ' kayıt) nasıl geri yüklensin?</p>' +
                    '<label class="yd-sec"><input type="radio" name="yd-mod" value="degistir" checked><span class="yd-sec-ic"><i class="fa-solid fa-eraser"></i><b>Önceki veriler silinsin</b><small>Kurumdaki mevcut tüm veriler silinir, yedek olduğu gibi yüklenir. Kurum eski haline döner.</small></span></label>' +
                    '<label class="yd-sec"><input type="radio" name="yd-mod" value="birlestir"><span class="yd-sec-ic"><i class="fa-solid fa-code-merge"></i><b>Mevcut verilerin üzerine ekle</b><small>Mevcut veriler korunur; yedekte olup şu an olmayanlar eklenir. Aynı kayıt farklıysa değiştirilsin mi diye sorulur.</small></span></label>' +
                    '<div class="yd-bilgi"><i class="fa-solid fa-shield-halved"></i> İşlemden önce kurumun şu anki hali otomatik olarak yedeklenir; gerekirse geri dönebilirsiniz.</div>';
                alt.innerHTML = '<button type="button" class="ikm-btn" data-k>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="yd-devam">Devam <i class="fa-solid fa-arrow-right"></i></button>';
                alt.querySelector('[data-k]').onclick = kapat;
                alt.querySelector('#yd-devam').onclick = function () { mod = govde.querySelector('input[name=yd-mod]:checked').value; hazirla(); };
            }
            function hazirla() {
                govde.innerHTML = '<div class="yd-bekle"><i class="fa-solid fa-spinner fa-spin"></i><b>Yedek okunuyor ve karşılaştırılıyor…</b><small>Canlı verilere henüz dokunulmuyor.</small></div>';
                alt.innerHTML = '<button type="button" class="ikm-btn ikm-btn-tehlike" id="yd-dur" disabled><i class="fa-solid fa-stop"></i> Durdur</button>';
                var d = alt.querySelector('#yd-dur');
                poll = setInterval(function () { ctx.liste().then(function (j) { var r = (j.islemler || []).filter(function (x) { return x.durum === 'calisiyor' && x.tur === 'geri'; })[0]; if (r) { d.disabled = false; d.dataset.id = r.id; } }).catch(noop); }, 1200);
                d.onclick = function () { d.disabled = true; ctx.cagir('yedek_islem_durdur', { islem_id: d.dataset.id }).then(function () { ctx.bildir('Durdurma isteği gönderildi.', 'bilgi'); }).catch(function (e) { ctx.bildir(e.message, 'hata'); }); };
                ctx.cagir('yedek_hazirla', { id: y.id, mod: mod }).then(function (r) { clearInterval(poll); islemId = r.islem_id; adim2(r); }).catch(function (e) { clearInterval(poll); ctx.bildir(e.message, 'hata'); kapat(); });
            }
            function adim2(r) {
                var h = '';
                if (mod === 'degistir') {
                    h = '<div class="yd-uyari"><i class="fa-solid fa-triangle-exclamation"></i><div><b>Kurumdaki mevcut tüm veriler silinecek.</b><p>' + tarihSaat(y.olusturma) + ' tarihindeki hale (' + (r.meta.kayit || 0) + ' kayıt, ' + r.tablo_sayisi + ' tablo) dönülecek. Bu tarihten sonra yapılan kayıtlar kaybolur.</p></div></div>';
                } else {
                    var A = r.analiz, T = A.toplam;
                    h = '<div class="yd-ozet">' + cip('yesil', T.yeni + ' yeni kayıt eklenecek', 'fa-plus') + cip('gri', T.ayni + ' aynı kayıt', 'fa-equals') + cip(T.farkli ? 'turuncu' : 'gri', T.farkli + ' farklı kayıt', 'fa-not-equal') + '</div>' +
                        '<div class="ikm-tablo-kap"><table class="ikm-tablo"><thead><tr><th>Bölüm</th><th>Yeni</th><th>Aynı</th><th>Farklı</th></tr></thead><tbody>' +
                        A.tablolar.map(function (t) { return '<tr><td>' + esc(tabloAd(t.tablo)) + '</td><td>' + t.yeni + '</td><td>' + t.ayni + '</td><td>' + (t.farkli ? '<b style="color:var(--ik-turuncu)">' + t.farkli + '</b>' : 0) + '</td></tr>'; }).join('') + '</tbody></table></div>';
                    if (T.farkli) {
                        var ornek = A.tablolar.filter(function (t) { return t.ornek && t.ornek.length; }).map(function (t) { return '<div class="yd-ornek"><b>' + esc(tabloAd(t.tablo)) + ':</b> ' + t.ornek.map(esc).join(', ') + (t.farkli > t.ornek.length ? ' …' : '') + '</div>'; }).join('');
                        h += '<div class="yd-soru"><b><i class="fa-solid fa-circle-question"></i> Aynı kayıt hem yedekte hem mevcut veride var ama içeriği farklı. Ne yapılsın?</b>' + ornek +
                            '<label class="yd-sec"><input type="radio" name="yd-cak" value="degistir"><span class="yd-sec-ic"><b>Yedektekiyle değiştir</b><small>' + T.farkli + ' kayıt yedekteki haline döner.</small></span></label>' +
                            '<label class="yd-sec"><input type="radio" name="yd-cak" value="koru" checked><span class="yd-sec-ic"><b>Mevcut olanı koru</b><small>Farklı kayıtlara dokunulmaz, yalnızca eksikler eklenir.</small></span></label></div>';
                    }
                }
                govde.innerHTML = h;
                alt.innerHTML = '<button type="button" class="ikm-btn" data-k>Vazgeç</button><button type="button" class="ikm-btn ' + (mod === 'degistir' ? 'ikm-btn-tehlike-dolu' : 'ikm-btn-ana') + '" id="yd-uygula">' + (mod === 'degistir' ? 'Verileri sil ve yedeği yükle' : 'Birleştir') + '</button>';
                alt.querySelector('[data-k]').onclick = kapat;
                alt.querySelector('#yd-uygula').onclick = function () {
                    var c = govde.querySelector('input[name=yd-cak]:checked'); var b = this; b.disabled = true; b.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uygulanıyor…';
                    ctx.cagir('yedek_uygula', { islem_id: islemId, mod: mod, cakisma: c ? c.value : 'koru' }).then(function (s) { bitti = 'tamam'; sonuc(s.rapor); }).catch(function (e) { bitti = false; ctx.bildir(e.message, 'hata'); kapat(); });
                };
            }
            function sonuc(R) {
                var s = R.mod === 'degistir' ? '<b>Kurum yedeğe döndürüldü.</b>' : '<b>Birleştirme tamamlandı:</b> ' + R.eklenen + ' eklendi' + (R.guncellenen ? ', ' + R.guncellenen + ' güncellendi' : '') + (R.atlanan ? ', ' + R.atlanan + ' atlandı' : '') + '.';
                govde.innerHTML = '<div class="yd-bitti"><span><i class="fa-solid fa-circle-check"></i></span>' + s + '<small>' + (R.dosya || 0) + ' dosya geri yüklendi.' + (R.yeniden_adlandirilan ? ' ' + R.yeniden_adlandirilan + ' kullanıcının adı/e-postası, başka bir kurumda kullanıldığı için değiştirildi.' : '') + '</small></div>';
                alt.innerHTML = '<button type="button" class="ikm-btn ikm-btn-ana" data-k>Tamam</button>'; alt.querySelector('[data-k]').onclick = kapat;
                if (ctx.sonra) ctx.sonra();
            }
            adim1();
        }
        yukle();
        return { yenile: yukle, durdur: izleBitir };
    }

    /* ───────── Kurum yetkilisi: geri yükleme talebi ───────── */
    function talepPanel(kap, ctx) {
        function yukle() { return ctx.liste().then(cizTalep).catch(function (e) { kap.innerHTML = '<div class="ikm-uyari">' + esc(e.message) + '</div>'; }); }
        function cizTalep(j) {
            var Y = j.yedekler || [], T = j.talepler || [], gun = +j.aralik;
            kap.innerHTML = '<div class="ikm-kpiler"><div class="ikm-kpi mavi"><span class="ikm-kpi-ikon"><i class="fa-solid fa-clock-rotate-left"></i></span><div><b>' + (j.son ? tarihSaat(j.son) : 'Henüz yok') + '</b><span>Son yedek' + (j.son ? ' · ' + once(j.son) : '') + '</span></div></div>' +
                '<div class="ikm-kpi yesil"><span class="ikm-kpi-ikon"><i class="fa-solid fa-shield-halved"></i></span><div><b>' + (gun > 0 ? gun + ' günde bir' : 'Kapalı') + '</b><span>Otomatik yedekleme</span></div></div></div>' +
                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-life-ring"></i> Yedek geri yükleme talebi</h3><button type="button" class="ikm-btn ikm-btn-ana ikm-btn-kucuk" id="yd-talep"><i class="fa-solid fa-paper-plane"></i> Talep oluştur</button></div>' +
                '<p class="yd-not">Verileriniz sistem tarafından düzenli olarak yedeklenir. Yanlışlıkla silinen ya da bozulan verileri belirli bir tarihteki haline döndürmek için talep oluşturabilirsiniz; talep platform yöneticisine iletilir.' + (j.iletisim ? ' Acil durumlar için: <a href="mailto:' + esc(j.iletisim) + '">' + esc(j.iletisim) + '</a>' : '') + '</p>' +
                (T.length ? '<div class="ikm-liste">' + T.map(function (t) {
                    var d = TALEP[t.durum] || TALEP.bekliyor;
                    return '<div class="ikm-satir"><span class="ikm-satir-ikon"><i class="fa-solid fa-envelope-open-text"></i></span><span class="ikm-satir-ic"><b>' + (t.yedek_tarih ? tarihSaat(t.yedek_tarih) + ' tarihli yedek' : 'Yedek geri yükleme') + '</b><small>' + tarihSaat(t.olusturma) + (t.mesaj ? ' · ' + esc(t.mesaj) : '') + (t.yanit ? ' · Yanıt: ' + esc(t.yanit) : '') + '</small></span>' + cip(d[1], d[0]) +
                        (t.durum === 'bekliyor' ? '<button type="button" class="ikm-btn ikm-btn-kucuk" data-iptal="' + t.id + '">İptal</button>' : '') + '</div>';
                }).join('') + '</div>' : '') + '</div>' +
                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-calendar-check"></i> Mevcut yedek tarihleri</h3></div><div class="yd-tarihler">' +
                (Y.length ? Y.map(function (y) { return '<span class="ikm-cip">' + tarihSaat(y.olusturma) + '</span>'; }).join('') : '<span class="ikm-soluk">Henüz yedek alınmamış.</span>') + '</div></div>';
            kap.querySelector('#yd-talep').onclick = function () { talepForm(Y); };
            [].forEach.call(kap.querySelectorAll('[data-iptal]'), function (b) { b.onclick = function () { ctx.cagir('yedek_talep_iptal', { id: b.dataset.iptal }).then(function () { ctx.bildir('Talep iptal edildi.', 'basari'); yukle(); }).catch(function (e) { ctx.bildir(e.message, 'hata'); }); }; });
        }
        function talepForm(Y) {
            var m = ctx.modal({ baslik: 'Yedek geri yükleme talebi', ikon: 'fa-paper-plane', gen: 520, govde:
                '<label class="ikm-alan"><span>Hangi tarihteki yedek?</span><select id="yt-y"><option value="0">En uygun yedeği siz seçin</option>' + Y.map(function (y) { return '<option value="' + y.id + '">' + tarihSaat(y.olusturma) + '</option>'; }).join('') + '</select></label>' +
                '<label class="ikm-alan"><span>Açıklama</span><textarea id="yt-m" rows="4" maxlength="1500" placeholder="Ne oldu? Hangi veriler etkilendi?"></textarea></label>',
                alt: '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="yt-g">Talebi gönder</button>' });
            m.el.querySelector('#yt-g').onclick = function () {
                var b = this; b.disabled = true;
                ctx.cagir('yedek_talep', { yedek_id: m.el.querySelector('#yt-y').value, mesaj: m.el.querySelector('#yt-m').value }).then(function () { m.kapat(); ctx.bildir('Talebiniz platform yöneticisine iletildi.', 'basari'); yukle(); }).catch(function (e) { ctx.bildir(e.message, 'hata'); b.disabled = false; });
            };
        }
        yukle();
    }
    window.TBYedek = { panel: panel, talepPanel: talepPanel, cip: cip, tarihSaat: tarihSaat, once: once, boyut: boyut, TALEP: TALEP };
})();
