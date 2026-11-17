/* ==========================================================================
   tb-ik-akis.js — "İzin onay akışı" ve "Bildirim kanalları" sayfaları (yönetici)
   ========================================================================== */
(function () {
    'use strict';
    function basla() {
        var M = window.IKM; if (!M || !M.kayit) return setTimeout(basla, 30);
        var $ = M.$, $$ = M.$$, esc = M.esc, getir = M.getir, gonder = M.gonder, bildir = M.bildir, icerik = M.icerik;
        var VARS = '*', YONETICI = -1;

        /* ---------------- ONAY AKIŞI ---------------- */
        var S = null, secili = VARS, taslak = null, kirli = false;
        var STILLER = [['zaman', 'Dikey zaman çizgisi', 'fa-timeline'], ['avatar', 'Avatar hattı', 'fa-user-group'], ['metro', 'Metro hattı', 'fa-train-subway'], ['hap', 'Kompakt hap', 'fa-pills'], ['neon', 'Koyu neon', 'fa-wand-magic-sparkles'], ['klasik', 'Klasik liste', 'fa-list']];
        var stil = 'metro', RENKLER = ['#0ea5e9', '#6366f1', '#10b981', '#f59e0b', '#ec4899', '#8b5cf6', '#14b8a6', '#f97316'];
        function stilAd(g) { return STILLER.filter(function (x) { return x[0] === g; })[0] ? g : 'metro'; }
        function bas(ad) { return String(ad || '?').trim().split(/\s+/).map(function (x) { return x.charAt(0); }).slice(0, 2).join('').toLocaleUpperCase('tr-TR') || '?'; }
        function parca(a, i) {
            var k = a.id > 0 ? kisi(a.id) : null, y = a.id === YONETICI;
            var rk = y ? '#64748b' : (k ? RENKLER[k.id % RENKLER.length] : '#94a3b8');
            var ad = y ? 'Talep edenin yöneticisi' : (k ? k.ad : 'Onaycı seçin');
            return {
                i: i, no: i + 1, ad: ad, gorev: y ? 'Otomatik: kişinin bağlı olduğu yönetici' : (k ? (k.gorev || k.departman || '') : ''), renk: rk,
                av: '<span class="ak-av" style="background:' + rk + '">' + (y ? '<i class="fa-solid fa-arrow-up"></i>' : esc(bas(ad))) + '</span>',
                sec: '<select class="ik-ak-kisi" aria-label="' + (i + 1) + '. adım onaycısı">' + kisiSecenek(a.id, null, true) + '</select>',
                kanal: kanalSatiri(a, a.id),
                vekil: '<label class="ak-vekil"><span><i class="fa-solid fa-user-clock"></i> Vekil</span><select class="ik-ak-vekil" aria-label="Vekil"><option value="">Vekil yok</option>' + S.kisiler.map(function (x) { return '<option value="' + x.id + '"' + (x.id === a.vekil_id ? ' selected' : '') + '>' + esc(x.ad) + '</option>'; }).join('') + '</select></label>',
                btn: '<div class="ikm-adim-btn"><button type="button" class="ikm-ikon-btn" data-yukari title="Yukarı"' + (i === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-up"></i></button><button type="button" class="ikm-ikon-btn" data-asagi title="Aşağı"' + (i === taslak.adimlar.length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-down"></i></button><button type="button" class="ikm-ikon-btn tehlike" data-sil title="Kaldır"><i class="fa-solid fa-xmark"></i></button></div>'
            };
        }
        function zincirHtml() {
            var A = taslak.adimlar, P = A.map(parca), n = A.length;
            if (!n) return '<div class="ikm-bos kucuk"><b>Henüz onaycı yok</b><p>Aşağıdan ilk onaycıyı ekleyin. Hiç onaycı tanımlanmazsa talep, “izin onaylama” yetkisi olan tüm İK yetkililerine gider.</p></div>';
            var h = '';
            if (stil === 'klasik') {
                return A.map(function (a, i) {
                    return '<div class="ikm-adim" data-i="' + i + '"><span class="ikm-adim-no">' + (i + 1) + '</span><div class="ikm-adim-ic">' + P[i].sec + P[i].kanal + P[i].vekil.replace('class="ak-vekil"', 'class="ikm-alan ikm-vekil"') + '</div>' + P[i].btn + '</div>';
                }).join('<div class="ikm-adim-ok"><i class="fa-solid fa-arrow-down"></i></div>');
            }
            if (stil === 'zaman') {
                h = '<div class="ak-zaman"><div class="ak-z-satir"><div class="ak-ray"><span class="ak-dugum koyu"><i class="fa-solid fa-plus"></i></span><span class="ak-cizgi"></span></div><div class="ak-z-metin"><b>Talep açılır</b> · personel izin talebini gönderir</div></div>';
                P.forEach(function (p) {
                    h += '<div class="ikm-adim ak-adim ak-z-satir" data-i="' + p.i + '"><div class="ak-ray"><span class="ak-dugum' + (p.i === 0 ? ' halka' : '') + '">' + p.no + '</span><span class="ak-cizgi"></span></div><div class="ak-kart"><div class="ak-ust">' + p.av + p.sec + p.btn + '</div><div class="ak-satir"><span class="ak-etk">Bildirim</span>' + p.kanal + '</div>' + p.vekil + '</div></div>';
                });
                return h + '<div class="ak-z-satir"><div class="ak-ray"><span class="ak-dugum yesil"><i class="fa-solid fa-check"></i></span></div><div class="ak-z-metin"><b>İzin onaylandı</b> · talep sahibi bilgilendirilir</div></div></div>';
            }
            if (stil === 'avatar') {
                h = '<div class="ak-avatar">';
                P.forEach(function (p) {
                    h += (p.i ? '<span class="ak-a-ok" aria-hidden="true"><i class="fa-solid fa-chevron-right"></i></span>' : '') + '<div class="ikm-adim ak-adim ak-a-kol" data-i="' + p.i + '"><div class="ak-a-av' + (p.i === 0 ? ' nabiz' : '') + '">' + p.av + '<em class="ak-no">' + p.no + '</em></div>' + p.sec + '<small class="ak-gorev">' + esc(p.gorev) + '</small>' + p.kanal + p.vekil + p.btn + '</div>';
                });
                return h + '</div>';
            }
            if (stil === 'metro') {
                h = '<div class="ak-metro"><span class="ak-m-ray" aria-hidden="true"><i class="ak-tren"></i></span><div class="ak-m-dr"><span class="ak-m-son koyu"><i class="fa-solid fa-plus"></i></span><span class="ak-z-metin"><b>Talep gönderildi</b> · ilk durak</span></div>';
                P.forEach(function (p) {
                    h += '<div class="ikm-adim ak-adim ak-m-dr" data-i="' + p.i + '"><span class="ak-m-nk' + (p.i === 0 ? ' halka' : '') + '" style="border-color:' + p.renk + '">' + p.no + '</span><div class="ak-kart"><div class="ak-ust">' + p.av + p.sec + p.btn + '</div><div class="ak-satir">' + p.kanal + '</div>' + p.vekil + '</div></div>';
                });
                return h + '<div class="ak-m-dr"><span class="ak-m-son yesil"><i class="fa-solid fa-check"></i></span><span class="ak-z-metin"><b>Son durak</b> · izin onaylandı</span></div></div>';
            }
            if (stil === 'hap') {
                var ad = P.map(function (p) { return '<b>' + esc(p.ad) + '</b>'; });
                h = '<div class="ak-ozet">Talep önce ' + ad.map(function (x, i) { return x + (i < ad.length - 2 ? ', ' : (i === ad.length - 2 ? ' ve ' : '')); }).join('') + ' onayına gider.</div><div class="ak-hap">';
                P.forEach(function (p) {
                    h += (p.i ? '<span class="ak-h-nk" aria-hidden="true"><i></i><i></i><i></i></span>' : '') + '<div class="ikm-adim ak-adim ak-h-satir" data-i="' + p.i + '"><span class="ak-h-no">' + p.no + '</span>' + p.av + p.sec + '<span class="ak-ayr"></span>' + p.kanal + '<span class="ak-ayr"></span>' + p.vekil + p.btn + '</div>';
                });
                return h + '</div>';
            }
            h = '<div class="ak-neon"><span class="ak-n-ray" aria-hidden="true"><i></i></span>';
            P.forEach(function (p) {
                h += '<div class="ikm-adim ak-adim ak-n-sr" data-i="' + p.i + '"><span class="ak-n-dg' + (p.i === 0 ? ' nabiz' : '') + '">' + p.no + '</span><div class="ak-n-cm"><div class="ak-n-ic"><div class="ak-ust">' + p.av + p.sec + p.btn + '</div><div class="ak-satir"><span class="ak-etk">Bildirim</span>' + p.kanal + '</div>' + p.vekil + '</div></div></div>';
            });
            return h + '</div>';
        }


        function bosTaslak() { return { adimlar: [], talep_sahibi: { site: true, sms: true, mail: true }, bilgi: [] }; }
        function kopya(o) { return JSON.parse(JSON.stringify(o)); }
        function kisi(id) { return (S.kisiler || []).filter(function (k) { return k.id === id; })[0]; }
        function taslakYukle(ad) {
            secili = ad; kirli = false;
            var a = S.akislar[ad];
            taslak = a ? kopya(a) : bosTaslak();
            if (!taslak.talep_sahibi) taslak.talep_sahibi = { site: true, sms: true, mail: true };
            taslak.bilgi = taslak.bilgi || []; taslak.adimlar = taslak.adimlar || [];
        }
        function kanalDugme(nesne, anahtar, ikon, ad, uyar) {
            return '<button type="button" class="ikm-kanal' + (nesne[anahtar] ? ' acik' : '') + (uyar ? ' uyari' : '') + '" data-kanal="' + anahtar + '" title="' + ad + (uyar ? ' — ' + uyar : '') + '" aria-pressed="' + (!!nesne[anahtar]) + '"><i class="' + ikon + '"></i><span>' + ad + '</span>' + (uyar ? '<em class="ikm-kanal-uyari"><i class="fa-solid fa-triangle-exclamation"></i></em>' : '') + '</button>';
        }
        function kanalSatiri(nesne, kisiId) {
            var k = kisiId > 0 ? kisi(kisiId) : null;
            var smsU = nesne.sms && k && !k.tel ? 'telefon numarası yok' : (nesne.sms && !S.sms_hazir ? 'SMS ayarı yapılmamış' : '');
            var mailU = nesne.mail && k && !k.mail ? 'e-posta adresi yok' : (nesne.mail && !S.mail_hazir ? 'e-posta ayarı yapılmamış' : '');
            return '<div class="ikm-kanallar">' + kanalDugme(nesne, 'site', 'fa-solid fa-bell', 'Site', '') + kanalDugme(nesne, 'sms', 'fa-solid fa-comment-sms', 'SMS', smsU) + kanalDugme(nesne, 'mail', 'fa-regular fa-envelope', 'E-posta', mailU) + '</div>';
        }
        function kisiSecenek(secilen, haric, yoneticiOlsun) {
            return (yoneticiOlsun ? '<option value="' + YONETICI + '"' + (secilen === YONETICI ? ' selected' : '') + '>↑ Talep edenin bağlı olduğu yönetici</option>' : '<option value="">Kişi seçin…</option>') +
                S.kisiler.map(function (k) { return '<option value="' + k.id + '"' + (k.id === secilen ? ' selected' : '') + '>' + esc(k.ad) + (k.gorev ? ' — ' + esc(k.gorev) : '') + '</option>'; }).join('');
        }
        function akisCiz() {
            var dl = S.departmanlar;
            var sol = '<div class="ikm-kart ikm-akis-sol"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-sitemap"></i> Birimler</h3></div><div class="ikm-liste">' +
                '<button type="button" class="ikm-satir' + (secili === VARS ? ' aktif' : '') + '" data-dep="' + VARS + '"><span class="ikm-satir-ikon"><i class="fa-solid fa-globe"></i></span><span class="ikm-satir-ic"><b>Varsayılan akış</b><small>Kendi akışı olmayan tüm birimler</small></span>' + (S.akislar[VARS] && S.akislar[VARS].adimlar.length ? '<span class="ikm-cip yesil">Tanımlı</span>' : '<span class="ikm-cip gri">Yok</span>') + '</button>' +
                dl.map(function (d) { return '<button type="button" class="ikm-satir' + (secili === d.ad ? ' aktif' : '') + '" data-dep="' + esc(d.ad) + '"><span class="ikm-satir-ikon"><i class="fa-regular fa-building"></i></span><span class="ikm-satir-ic"><b>' + esc(d.ad) + '</b><small>' + d.n + ' kişi</small></span>' + (S.akislar[d.ad] && S.akislar[d.ad].adimlar.length ? '<span class="ikm-cip yesil">Özel</span>' : '<span class="ikm-cip gri">Varsayılan</span>') + '</button>'; }).join('') +
                '</div><div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-ak-yeni-dep"><i class="fa-solid fa-plus"></i> Listede olmayan birim ekle</button><button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-ak-ata"><i class="fa-solid fa-user-plus"></i> ' + (secili === VARS ? 'Birime personel ata' : 'Bu birime personel ata') + '</button></div></div>';
            var ad = secili === VARS ? 'Varsayılan akış' : secili;
            var adimlar = zincirHtml();
            var bilgi = taslak.bilgi.map(function (b, i) {
                return '<div class="ikm-adim bilgi" data-b="' + i + '"><span class="ikm-adim-no"><i class="fa-regular fa-eye"></i></span><div class="ikm-adim-ic"><select class="ik-ak-bkisi">' + kisiSecenek(b.id, null, false) + '</select>' + kanalSatiri(b, b.id) + '</div><div class="ikm-adim-btn"><button type="button" class="ikm-ikon-btn tehlike" data-bsil title="Kaldır"><i class="fa-solid fa-xmark"></i></button></div></div>';
            }).join('');
            var sag = '<div class="ikm-akis-sag"><div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-route"></i> ' + esc(ad) + ' — onay zinciri</h3>' + (kirli ? '<span class="ikm-cip turuncu">Kaydedilmedi</span>' : '') +
                '<label class="ak-gorsec" title="Görünüm (sizin için kaydedilir)"><i class="fa-solid ' + STILLER.filter(function (x) { return x[0] === stil; })[0][2] + '"></i><select id="ik-ak-gor" aria-label="Onay zinciri görünümü">' + STILLER.map(function (g) { return '<option value="' + g[0] + '"' + (g[0] === stil ? ' selected' : '') + '>' + g[1] + '</option>'; }).join('') + '</select></label></div>' +
                '<p class="ikm-soluk kucuk">' + (secili === VARS ? 'Kendine ait akışı olmayan her birim bu sırayı kullanır.' : 'Bu birimdeki personelin izin talepleri aşağıdaki sırayla onaya gider.') + ' Her onaycıya hangi kanaldan haber verileceğini seçin. Bir onaycı onaylayınca sıradakine <b>“… onayladı, sizin onayınızı bekliyor”</b> bildirimi gider.</p>' +
                '<div class="ak-zincir ak-s-' + stil + '">' + adimlar + '</div>' +
                '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-ak-ekle"><i class="fa-solid fa-plus"></i> Onaycı ekle</button></div></div>' +
                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-flag-checkered"></i> Sonuç bildirimleri</h3></div>' +
                '<div class="ikm-adim bilgi"><span class="ikm-adim-no"><i class="fa-solid fa-user"></i></span><div class="ikm-adim-ic"><b class="ikm-adim-baslik">Talep sahibi <small>— “İzniniz onaylandı” / “reddedildi”</small></b>' + kanalSatiri(taslak.talep_sahibi, 0) + '</div></div>' +
                '<h4 class="ikm-alt-baslik"><i class="fa-regular fa-eye"></i> Ayrıca bilgilendirilecekler <small>(son onaydan sonra)</small></h4>' + (bilgi || '<p class="ikm-soluk kucuk">Ör. muhasebe ya da puantaj sorumlusu.</p>') +
                '<div class="ikm-aksiyon"><button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-ak-bekle"><i class="fa-solid fa-plus"></i> Bilgi alıcısı ekle</button></div></div>' +
                '<div class="ikm-akis-alt"><button type="button" class="ikm-btn ikm-btn-ana" id="ik-ak-kaydet"><i class="fa-solid fa-floppy-disk"></i> Akışı kaydet</button>' +
                (S.akislar[secili] ? '<button type="button" class="ikm-btn ikm-btn-tehlike" id="ik-ak-sil"><i class="fa-solid fa-trash"></i> Akışı sil</button>' : '') + '</div></div>';
            var uyari = (!S.sms_hazir || !S.mail_hazir) ? '<div class="ikm-uyari-kutu"><i class="fa-solid fa-circle-info"></i><div>' + (!S.sms_hazir ? '<b>SMS ayarı yapılmamış.</b> ' : '') + (!S.mail_hazir ? '<b>E-posta ayarı yapılmamış.</b> ' : '') + 'Bu kanallar seçilse bile gönderilemez. <button type="button" class="ikm-link" data-git="kanallar">Bildirim kanallarını ayarla →</button></div></div>' : '';
            icerik.innerHTML = uyari + '<div class="ikm-akis">' + sol + sag + '</div>';
        }
        function dugumBul(e) { var a = e.target.closest('.ikm-adim'); return a; }
        function nesneBul(a) { return a.dataset.b !== undefined ? taslak.bilgi[+a.dataset.b] : (a.dataset.i !== undefined ? taslak.adimlar[+a.dataset.i] : taslak.talep_sahibi); }
        function akisOlaylar() {
            icerik.onclick = function (e) {
                var t;
                if ((t = e.target.closest('[data-dep]'))) {
                    var degis = function () { taslakYukle(t.dataset.dep); akisCiz(); };
                    if (kirli) M.onayla({ baslik: 'Kaydedilmemiş değişiklikler var', mesaj: 'Başka birime geçerseniz yaptığınız değişiklikler kaybolur.', evet: 'Yine de geç', hayir: 'Kal', tur: 'uyari' }).then(function (ok) { if (ok) degis(); });
                    else degis();
                    return;
                }
                if (e.target.closest('#ik-ak-ata')) { if (!M.birimPersonelAta) { bildir('Sayfa yükleniyor, tekrar deneyin.', 'uyari'); return; } M.birimPersonelAta(secili === VARS ? '' : secili, function () { akisYukle(secili); }); return; }
                if (e.target.closest('#ik-ak-yeni-dep')) {
                    M.soru({ baslik: 'Birim ekle', mesaj: 'Listede görünmeyen bir birim için akış tanımlayın.', etiket: 'Birim adı', zorunlu: true, uzun: false, evet: 'Ekle', ikon: 'fa-sitemap' }).then(function (ad) {
                        if (!ad) return; if (!S.departmanlar.some(function (d) { return d.ad.toLowerCase() === ad.toLowerCase(); })) S.departmanlar.push({ ad: ad, n: 0, akis: false });
                        taslakYukle(ad); akisCiz();
                    }); return;
                }
                if (e.target.closest('#ik-ak-ekle')) { if (taslak.adimlar.length >= 8) { bildir('En fazla 8 onaycı eklenebilir.', 'uyari'); return; } taslak.adimlar.push({ id: 0, vekil_id: 0, site: true, sms: true, mail: true }); kirli = true; akisCiz(); return; }
                if (e.target.closest('#ik-ak-bekle')) { taslak.bilgi.push({ id: 0, site: true, sms: true, mail: true }); kirli = true; akisCiz(); return; }
                var a = dugumBul(e);
                if (a && (t = e.target.closest('[data-kanal]'))) { var n = nesneBul(a), k = t.dataset.kanal; n[k] = !n[k]; kirli = true; akisCiz(); return; }
                if (a && e.target.closest('[data-yukari]')) { var i = +a.dataset.i; taslak.adimlar.splice(i - 1, 0, taslak.adimlar.splice(i, 1)[0]); kirli = true; akisCiz(); return; }
                if (a && e.target.closest('[data-asagi]')) { var j = +a.dataset.i; taslak.adimlar.splice(j + 1, 0, taslak.adimlar.splice(j, 1)[0]); kirli = true; akisCiz(); return; }
                if (a && e.target.closest('[data-sil]')) { taslak.adimlar.splice(+a.dataset.i, 1); kirli = true; akisCiz(); return; }
                if (a && e.target.closest('[data-bsil]')) { taslak.bilgi.splice(+a.dataset.b, 1); kirli = true; akisCiz(); return; }
                if (e.target.closest('#ik-ak-kaydet')) { kaydet(); return; }
                if (e.target.closest('#ik-ak-sil')) {
                    M.onayla({ baslik: 'Akış silinsin mi?', mesaj: secili === VARS ? 'Varsayılan akış kalkar; akışı olmayan birimlerde talep tüm İK yetkililerine gider.' : 'Bu birim varsayılan akışa döner.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) {
                        if (!ok) return; return gonder('akis_sil', { departman: secili }).then(function () { bildir('Akış silindi.', 'basari'); return akisYukle(secili); });
                    }).catch(function (er) { bildir(er.message, 'hata'); });
                }
            };
            icerik.onchange = function (e) {
                if (e.target.id === 'ik-ak-gor') { stil = stilAd(e.target.value); akisCiz(); gonder('akis_gorunum', { gorunum: stil }).catch(function (er) { bildir(er.message, 'hata'); }); return; }
                var a = dugumBul(e); if (!a) return;
                if (e.target.classList.contains('ik-ak-vekil')) { nesneBul(a).vekil_id = +e.target.value || 0; kirli = true; return; }
                if (e.target.classList.contains('ik-ak-kisi') || e.target.classList.contains('ik-ak-bkisi')) { nesneBul(a).id = +e.target.value || 0; kirli = true; akisCiz(); }
            };
        }
        function kaydet() {
            for (var i = 0; i < taslak.adimlar.length; i++) if (!taslak.adimlar[i].id) { bildir((i + 1) + '. onaycıyı seçin ya da kaldırın.', 'uyari'); return; }
            for (var j = 0; j < taslak.bilgi.length; j++) if (!taslak.bilgi[j].id) { bildir('Bilgi alıcılarından birini seçin ya da kaldırın.', 'uyari'); return; }
            // uyarı: telefon / e-posta eksikleri
            var eksik = [];
            taslak.adimlar.concat(taslak.bilgi).forEach(function (n) { var k = n.id > 0 ? kisi(n.id) : null; if (!k) return; if (n.sms && !k.tel) eksik.push(k.ad + ' (telefon)'); if (n.mail && !k.mail) eksik.push(k.ad + ' (e-posta)'); });
            var git = function () {
                return gonder('akis_kaydet', { departman: secili === VARS ? VARS : secili, veri: JSON.stringify(taslak) }).then(function () { bildir('Onay akışı kaydedildi.', 'basari'); return akisYukle(secili); });
            };
            (eksik.length ? M.onayla({ baslik: 'Bazı bilgiler eksik', mesaj: 'Şu kişilerde ilgili iletişim bilgisi yok, o kanaldan haber gidemez: ' + eksik.join(', ') + '. Yine de kaydedilsin mi?', evet: 'Kaydet', hayir: 'Düzelt', tur: 'uyari' }) : Promise.resolve(true))
                .then(function (ok) { if (ok) return git(); }).catch(function (er) { bildir(er.message, 'hata'); });
        }
        function akisYukle(sec) {
            return getir({ islem: 'akis_getir' }).then(function (j) { S = j; stil = stilAd(j.gorunum || 'metro'); taslakYukle(sec || secili); akisCiz(); akisOlaylar(); });
        }
        M.kayit('akis', 'İzin onay akışı', 'Birim bazında kimin, hangi sırayla ve hangi kanaldan haber alarak onaylayacağını belirleyin.', function () {
            icerik.innerHTML = M.yukleniyor(); secili = VARS; akisYukle(VARS).catch(function (e) { icerik.innerHTML = M.hataKutu(e.message); });
        });
        var oncekiDegis = M.sekmeDegisiyor;
        M.sekmeDegisiyor = function (a, b) { icerik.onclick = null; icerik.onchange = null; if (oncekiDegis) oncekiDegis(a, b); };

        /* ---------------- BİLDİRİM KANALLARI ---------------- */
        var K = null;
        function girdi(ad, etiket, tip, yer, ipucu) { return '<label class="ikm-alan"><span>' + etiket + (ipucu ? ' <small>' + ipucu + '</small>' : '') + '</span><input type="' + (tip || 'text') + '" name="' + ad + '" value="' + esc(K.ayar[ad] || '') + '" placeholder="' + esc(yer || '') + '" autocomplete="off"></label>'; }
        function kanallarCiz() {
            var a = K.ayar, sag = a.sms_saglayici;
            var durum = function (ok, ad) { return '<span class="ikm-cip ' + (ok ? 'yesil' : 'gri') + '"><i class="fa-solid ' + (ok ? 'fa-circle-check' : 'fa-circle-minus') + '"></i> ' + ad + (ok ? ' hazır' : ' kurulu değil') + '</span>'; };
            icerik.innerHTML = '<div class="ikm-izgara iki">' +
                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-comment-sms"></i> SMS</h3>' + durum(K.sms_hazir, 'SMS') + '</div><div class="ikm-form" id="ik-k-sms">' +
                '<label class="ikm-alan"><span>SMS sağlayıcı</span><select name="sms_saglayici"><option value="">Kullanma</option><option value="netgsm"' + (sag === 'netgsm' ? ' selected' : '') + '>NetGSM</option><option value="iletimerkezi"' + (sag === 'iletimerkezi' ? ' selected' : '') + '>İleti Merkezi</option><option value="ozel"' + (sag === 'ozel' ? ' selected' : '') + '>Özel HTTP adresi</option></select></label>' +
                '<div id="ik-k-sms-alanlar">' +
                girdi('sms_kullanici', 'Kullanıcı kodu / API anahtarı') +
                '<label class="ikm-alan"><span>Şifre / API gizli anahtarı ' + (K.sms_sifre_var ? '<small>(kayıtlı — değiştirmek için yazın)</small>' : '') + '</span><input type="password" name="sms_sifre" value="" placeholder="' + (K.sms_sifre_var ? '••••••••' : '') + '" autocomplete="new-password"></label>' +
                girdi('sms_baslik', 'Gönderici başlığı', 'text', 'Operatörde onaylı başlık', 'en fazla 11 karakter') +
                '<div class="ik-k-ozel"' + (sag === 'ozel' ? '' : ' hidden') + '>' + girdi('sms_ozel_url', 'Gönderim adresi (URL)', 'text', 'https://…?no={telefon}&mesaj={mesaj}', '{telefon} ve {mesaj} yer tutucuları') +
                '<label class="ikm-alan"><span>POST gövdesi <small>(boşsa GET)</small></span><textarea name="sms_ozel_govde" rows="2" placeholder="no={telefon}&mesaj={mesaj}">' + esc(a.sms_ozel_govde || '') + '</textarea></label>' + girdi('sms_ozel_basari', 'Başarı ifadesi', 'text', 'Yanıtta geçmesi gereken metin', 'boşsa HTTP 200 yeterli') + '</div></div>' +
                '<div class="ikm-test"><input type="tel" id="ik-k-sms-hedef" placeholder="Test numarası: 05XX XXX XX XX"><button type="button" class="ikm-btn" data-test="sms"><i class="fa-solid fa-paper-plane"></i> Test SMS gönder</button></div>' +
                '<div><button type="button" class="ikm-btn ikm-btn-ana" data-kaydet="ik-k-sms"><i class="fa-solid fa-floppy-disk"></i> SMS ayarlarını kaydet</button></div></div></div>' +

                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-regular fa-envelope"></i> E-posta</h3>' + durum(K.mail_hazir, 'E-posta') + '</div><div class="ikm-form" id="ik-k-mail">' +
                '<p class="ikm-soluk kucuk">SMTP bilgisi girmezseniz sunucunun kendi <code>mail()</code> işlevi kullanılır (çoğu zaman spama düşer; SMTP önerilir).</p>' +
                '<div class="ikm-iki">' + girdi('smtp_host', 'SMTP sunucu', 'text', 'smtp.firma.com') + girdi('smtp_port', 'Port', 'number', '587') + '</div>' +
                '<label class="ikm-alan"><span>Güvenlik</span><select name="smtp_guvenlik"><option value="tls"' + (a.smtp_guvenlik === 'tls' ? ' selected' : '') + '>STARTTLS (587)</option><option value="ssl"' + (a.smtp_guvenlik === 'ssl' ? ' selected' : '') + '>SSL/TLS (465)</option><option value="yok"' + (a.smtp_guvenlik === 'yok' ? ' selected' : '') + '>Yok (25)</option></select></label>' +
                '<div class="ikm-iki">' + girdi('smtp_kullanici', 'Kullanıcı adı') + '<label class="ikm-alan"><span>Şifre ' + (K.smtp_sifre_var ? '<small>(kayıtlı)</small>' : '') + '</span><input type="password" name="smtp_sifre" value="" placeholder="' + (K.smtp_sifre_var ? '••••••••' : '') + '" autocomplete="new-password"></label></div>' +
                '<div class="ikm-iki">' + girdi('mail_gonderen', 'Gönderen adres', 'email', 'ik@firma.com') + girdi('mail_gonderen_ad', 'Gönderen adı', 'text', 'Turkuaz Bileşim İnsan Kaynakları') + '</div>' +
                '<div class="ikm-test"><input type="email" id="ik-k-mail-hedef" placeholder="Test e-posta adresi"><button type="button" class="ikm-btn" data-test="mail"><i class="fa-solid fa-paper-plane"></i> Test e-postası gönder</button></div>' +
                '<div><button type="button" class="ikm-btn ikm-btn-ana" data-kaydet="ik-k-mail"><i class="fa-solid fa-floppy-disk"></i> E-posta ayarlarını kaydet</button></div></div></div>' +

                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-link"></i> Site adresi</h3></div><div class="ikm-form" id="ik-k-site">' +
                girdi('site_url', 'Site adresi', 'text', K.site_url_otomatik, 'SMS ve e-postalardaki bağlantılar için — boşsa otomatik bulunur') +
                '<p class="ikm-soluk kucuk">Otomatik bulunan: <code>' + esc(K.site_url_otomatik) + '</code></p><div><button type="button" class="ikm-btn ikm-btn-ana" data-kaydet="ik-k-site"><i class="fa-solid fa-floppy-disk"></i> Kaydet</button></div></div></div>' +

                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-signature"></i> E-posta görünümü ve imza</h3></div><div class="ikm-form" id="ik-k-gorunum">' +
                '<div class="ikm-alan"><span>Logo <small>PNG / JPG / WEBP, en fazla 1 MB</small></span><div class="ikm-logo-kutu">' + (a.mail_logo ? '<img id="ik-k-logo-img" src="' + esc(/^https?:/i.test(a.mail_logo) ? a.mail_logo : a.mail_logo) + '" alt="Logo">' : '<span class="ikm-soluk kucuk">Logo yüklenmedi</span>') +
                '<label class="ikm-btn ikm-btn-kucuk"><i class="fa-solid fa-upload"></i> Logo yükle<input type="file" id="ik-k-logo" accept="image/png,image/jpeg,image/webp,image/gif" hidden></label>' + (a.mail_logo ? '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" id="ik-k-logo-sil">Kaldır</button>' : '') + '</div></div>' +
                girdi('mail_firma_ad', 'Üst başlık metni', 'text', 'Turkuaz Bileşim İnsan Kaynakları') +
                '<div class="ikm-iki"><label class="ikm-alan"><span>1. renk <small>üst bant başlangıcı</small></span><input type="color" name="mail_renk" value="' + esc(/^#[0-9a-f]{6}$/i.test(a.mail_renk || '') ? a.mail_renk : '#6366f1') + '" style="height:42px;padding:4px;width:100%"></label>' +
                '<label class="ikm-alan"><span>2. renk <small>geçiş (aynıysa tek renk)</small></span><input type="color" name="mail_renk2" value="' + esc(/^#[0-9a-f]{6}$/i.test(a.mail_renk2 || '') ? a.mail_renk2 : (/^#[0-9a-f]{6}$/i.test(a.mail_renk || '') ? a.mail_renk : '#6366f1')) + '" style="height:42px;padding:4px;width:100%"></label></div>' +
                '<div class="ikm-alan"><span>Hazır renk çiftleri</span><div style="display:flex;flex-wrap:wrap;gap:8px">' + [['#6366f1', '#06b6d4'], ['#0ea5e9', '#22c55e'], ['#7c3aed', '#ec4899'], ['#0f766e', '#14b8a6'], ['#f97316', '#ef4444'], ['#1e293b', '#475569'], ['#ffffff', '#e2e8f0']].map(function (c) { return '<button type="button" data-cift="' + c[0] + ',' + c[1] + '" title="' + c[0] + ' → ' + c[1] + '" style="width:54px;height:30px;border-radius:10px;border:1px solid rgba(0,0,0,.15);cursor:pointer;background:linear-gradient(135deg,' + c[0] + ',' + c[1] + ')"></button>'; }).join('') + '</div></div>' +
                '<div class="ikm-alan"><span>Canlı önizleme <small>kaydetmeden görünür</small></span><div id="ik-k-canli" style="border-radius:18px;overflow:hidden;background:#fff;border:1px solid rgba(100,116,139,.25);max-width:420px"></div></div>' +
                '<label class="ikm-alan"><span>İmza <small>her e-postanın sonuna eklenir</small></span><textarea name="mail_imza" rows="4" placeholder="Saygılarımızla,&#10;Turkuaz Bileşim İnsan Kaynakları&#10;0212 000 00 00 · ik@firma.com">' + esc(a.mail_imza || '') + '</textarea></label>' +
                '<label class="ikm-alan"><span>PDF eki <small>giden e-postanın PDF kopyası ekli gider</small></span><select name="mail_pdf_ek"><option value="1"' + (a.mail_pdf_ek !== '0' ? ' selected' : '') + '>Evet — her e-postaya PDF ekle</option><option value="0"' + (a.mail_pdf_ek === '0' ? ' selected' : '') + '>Hayır</option></select></label>' +
                '<div><button type="button" class="ikm-btn ikm-btn-ana" data-kaydet="ik-k-gorunum"><i class="fa-solid fa-floppy-disk"></i> Görünümü kaydet</button></div></div></div>' +

                '<div class="ikm-kart tam"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-eye"></i> Mesaj önizleme</h3><button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-k-onizle-yenile"><i class="fa-solid fa-rotate"></i> Yenile</button></div><div id="ik-k-onizle">' + M.yukleniyor() + '</div></div>' +

                '<div class="ikm-kart"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-bell"></i> Site içi bildirim ve ses</h3>' + durum(K.push_hazir, 'Tarayıcı bildirimi') + '</div>' +
                '<p class="ikm-soluk kucuk">Site açıkken uyarı ve ses otomatik çalışır. Site kapalıyken / arka plandayken bildirim için her kullanıcı bir kez izin vermelidir. Şu an <b>' + K.push_abone + '</b> kullanıcı izin vermiş durumda.</p>' +
                '<div class="ikm-aksiyon"><button type="button" class="ikm-btn" id="ik-k-ses"><i class="fa-solid fa-volume-high"></i> Sesi dinle</button><button type="button" class="ikm-btn ikm-btn-ana" id="ik-k-push"><i class="fa-solid fa-bell"></i> Bu cihazda bildirimleri aç</button></div><div id="ik-k-push-durum" class="ikm-soluk kucuk"></div></div>' +

                '<div class="ikm-kart tam"><div class="ikm-kart-baslik"><h3><i class="fa-solid fa-clock-rotate-left"></i> Son gönderimler</h3><button type="button" class="ikm-btn ikm-btn-kucuk" id="ik-k-log-yenile"><i class="fa-solid fa-rotate"></i> Yenile</button></div><div id="ik-k-log">' + M.yukleniyor() + '</div></div></div>';
            function canli() {
                var kap = $('#ik-k-canli'); if (!kap) return;
                var g = function (n) { var el = $('[name="' + n + '"]', $('#ik-k-gorunum')); return el ? el.value : ''; };
                var r1 = g('mail_renk') || '#6366f1', r2 = g('mail_renk2') || r1, firma = g('mail_firma_ad') || 'İNSAN KAYNAKLARI', imza = g('mail_imza') || firma;
                var logo = $('#ik-k-logo-img');
                var ust = logo ? '<img src="' + esc(logo.getAttribute('src')) + '" alt="" style="display:block;margin:0 auto;height:44px;max-width:200px">'
                    : '<span style="display:inline-block;background:#fff;border-radius:10px;padding:8px 16px;font-weight:800;font-size:15px;color:' + esc(r1) + '">' + esc(firma) + '</span>';
                kap.innerHTML = '<div style="background:' + esc(r1) + ';background-image:linear-gradient(135deg,' + esc(r1) + ',' + esc(r2) + ');padding:22px 18px;text-align:center">' + ust + '</div>' +
                    '<div style="padding:16px 18px 6px;font-size:14px;color:#0f172a">Merhaba <b style="color:inherit">Ayşe</b>,</div><div style="padding:2px 18px 4px;font-size:16px;font-weight:800;color:#0f172a">İzin talebiniz onaylandı</div>' +
                    '<div style="padding:2px 18px 12px;font-size:13px;color:#475569">Yıllık izin · 3 gün · 12.10.2026 – 14.10.2026</div>' +
                    '<div style="padding:0 18px 14px"><span style="display:inline-block;background:' + esc(r1) + ';color:#fff;font-weight:700;font-size:13px;padding:9px 16px;border-radius:10px">İK sayfasını aç</span></div>' +
                    '<div style="padding:0 18px 16px;font-size:12px;color:#64748b;white-space:pre-line">Sevgiler 👋\n<b>' + esc(imza) + '</b></div>';
            }
            icerik.oninput = function (e) { if (e.target.closest && e.target.closest('#ik-k-gorunum')) canli(); };
            canli();
            icerik.onclick = function (e) {
                var t;
                if ((t = e.target.closest('[data-cift]'))) { var c = t.dataset.cift.split(','); $('[name="mail_renk"]').value = c[0]; $('[name="mail_renk2"]').value = c[1]; canli(); return; }
                if ((t = e.target.closest('[data-kaydet]'))) {
                    var v = {}; $$('[name]', $('#' + t.dataset.kaydet)).forEach(function (i) { v[i.name] = i.value; });
                    t.disabled = true;
                    gonder('bildirim_ayar_kaydet', v).then(function () { bildir('Ayarlar kaydedildi.', 'basari'); return kanallarYukle(); }).catch(function (er) { bildir(er.message, 'hata'); t.disabled = false; });
                    return;
                }
                if ((t = e.target.closest('[data-test]'))) {
                    var tur = t.dataset.test, hedef = $('#ik-k-' + tur + '-hedef').value.trim();
                    if (!hedef) { bildir('Test için ' + (tur === 'sms' ? 'telefon numarası' : 'e-posta adresi') + ' girin.', 'uyari'); return; }
                    bildir('Önce yukarıdaki ayarları kaydettiğinizden emin olun.', 'bilgi');
                    t.disabled = true; gonder('kanal_test', { tur: tur, hedef: hedef }).then(function (j) {
                        bildir(j.basarili ? 'Test gönderildi. ' + (j.sonuc || '') : 'Gönderilemedi: ' + (j.sonuc || 'bilinmeyen hata'), j.basarili ? 'basari' : 'hata'); logYukle();
                    }).catch(function (er) { bildir(er.message, 'hata'); }).then(function () { t.disabled = false; });
                    return;
                }
                if (e.target.closest('#ik-k-ses') && window.IKBildirim) { IKBildirim.sesCal(); return; }
                if (e.target.closest('#ik-k-push') && window.IKBildirim) { IKBildirim.pushAc().then(function () { bildir('Bu cihazda bildirimler açıldı.', 'basari'); pushDurum(); }).catch(function (er) { bildir(er.message, 'uyari'); }); return; }
                if (e.target.closest('#ik-k-log-yenile')) logYukle();
                if (e.target.closest('#ik-k-onizle-yenile')) { onizleYukle(); return; }
                if ((t = e.target.closest('[data-onizle]'))) { onizleSec = +t.dataset.onizle; onizleCiz(); return; }
                if (e.target.closest('#ik-k-logo-sil')) { gonder('bildirim_ayar_kaydet', { mail_logo: '' }).then(function () { bildir('Logo kaldırıldı.', 'basari'); return kanallarYukle(); }).catch(function (er) { bildir(er.message, 'hata'); }); return; }
            };
            icerik.onchange = function (e) {
                if (e.target.id === 'ik-k-logo' && e.target.files[0]) { gonder('mail_logo_yukle', {}, e.target.files[0]).then(function () { bildir('Logo yüklendi.', 'basari'); return kanallarYukle(); }).catch(function (er) { bildir(er.message, 'hata'); }); return; }
                if (e.target.name === 'sms_saglayici') { var o = $('.ik-k-ozel', icerik); if (o) o.hidden = e.target.value !== 'ozel'; }
            };
            logYukle(); pushDurum(); onizleYukle();
        }
        var onizleV = null, onizleSec = 0;
        function onizleYukle() { getir({ islem: 'bildirim_onizle' }).then(function (j) { onizleV = j; onizleCiz(); }).catch(function (e) { var k = $('#ik-k-onizle'); if (k) k.innerHTML = M.hataKutu(e.message); }); }
        function onizleCiz() {
            var k = $('#ik-k-onizle'); if (!k || !onizleV) return; var o = onizleV.ornekler[onizleSec] || onizleV.ornekler[0];
            k.innerHTML = '<div class="ikm-segment" style="margin-bottom:14px">' + onizleV.ornekler.map(function (x, i) { return '<button type="button" data-onizle="' + i + '" class="' + (i === onizleSec ? 'aktif' : '') + '">' + esc(x.ad) + '</button>'; }).join('') + '</div>' +
                '<div class="ikm-onizle-iki"><div><div class="ikm-alt-baslik"><i class="fa-solid fa-comment-sms"></i> SMS <small>' + (onizleV.sms_baslik ? 'gönderen: ' + esc(onizleV.sms_baslik) : '') + '</small></div><div class="ikm-telefon"><div class="ikm-sms-balon">' + esc(o.sms_tam) + '</div><small class="ikm-soluk">' + o.sms_tam.length + ' karakter · Türkçe karakterli SMS\'te 70 karakter = 1 parça</small></div></div>' +
                '<div><div class="ikm-alt-baslik"><i class="fa-regular fa-envelope"></i> E-posta <small>konu: ' + esc(o.konu) + '</small></div><iframe class="ikm-mail-cerceve" id="ik-k-mail-cerceve" title="E-posta önizleme" sandbox="" ></iframe></div></div>';
            $('#ik-k-mail-cerceve').srcdoc = o.mail;
        }
        function pushDurum() {
            var k = $('#ik-k-push-durum'); if (!k || !window.IKBildirim) return;
            IKBildirim.pushDurum().then(function (d) { k.textContent = { acik: '✔ Bu cihazda açık.', kapali: 'Bu cihazda henüz açılmadı.', engelli: 'Tarayıcı bildirimleri engellemiş.', desteklenmiyor: 'Bu tarayıcı desteklemiyor.' }[d] || ''; });
        }
        function logYukle() {
            getir({ islem: 'bildirim_log' }).then(function (j) {
                var k = $('#ik-k-log'); if (!k) return;
                k.innerHTML = j.liste.length ? '<div class="ikm-tablo-kap"><table class="ikm-tablo"><thead><tr><th>Zaman</th><th>Kanal</th><th>Alıcı</th><th>Konu</th><th>Sonuç</th></tr></thead><tbody>' + j.liste.map(function (l) {
                    return '<tr><td>' + M.tarihSaat(l.zaman) + '</td><td><span class="ikm-cip">' + (l.kanal === 'sms' ? 'SMS' : l.kanal === 'mail' ? 'E-posta' : esc(l.kanal)) + '</span></td><td>' + esc(l.alici) + '</td><td>' + esc(l.konu) + '</td><td><span class="ikm-cip ' + (l.ok ? 'yesil' : 'kirmizi') + '">' + (l.ok ? 'Gitti' : 'Hata') + '</span> <small class="ikm-soluk">' + esc(String(l.sonuc || '').slice(0, 120)) + '</small></td></tr>';
                }).join('') + '</tbody></table></div>' : M.bosKutu('fa-paper-plane', 'Henüz gönderim yok');
            }).catch(function () {});
        }
        function kanallarYukle() { return getir({ islem: 'bildirim_ayar_getir' }).then(function (j) { K = j; kanallarCiz(); }); }
        M.kayit('kanallar', 'Bildirim kanalları', 'SMS, e-posta ve tarayıcı bildirimi ayarları.', function () {
            icerik.innerHTML = M.yukleniyor(); kanallarYukle().catch(function (e) { icerik.innerHTML = M.hataKutu(e.message); });
        });
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', basla); else basla();
})();
