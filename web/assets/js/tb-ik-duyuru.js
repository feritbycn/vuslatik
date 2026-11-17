/* ==========================================================================
   tb-ik-duyuru.js — Duyurular · Dosya paylaşımı · Anketler (İK sayfası eki)
   Yayınlama yetkisi: "ik_duyuru_yonet". Uç nokta: duyuru_islem.php
   ========================================================================== */
(function () {
    'use strict';
    function basla() {
        var M = window.IKM; if (!M || !M.kayit) return setTimeout(basla, 30);
        var $ = M.$, $$ = M.$$, esc = M.esc, bildir = M.bildir, onayla = M.onayla, modal = M.modal, icerik = M.icerik, av = M.av, boyutYaz = M.boyutYaz, bosKutu = M.bosKutu;
        var API = 'duyuru_islem.php', A = M.istek(API), getir = A.getir, gonder = A.gonder;
        var YON = !!(M.yetki().ik_duyuru_yonet);
        var SEC = null;                                    // yönetici: birimler, kişiler, klasörler, kanal hazırlığı

        /* ======================= yardımcılar ======================= */
        var AYK = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];
        /** Arama için: küçük harf + Türkçe karakterleri sadeleştir ("çağlar" ≈ "caglar") */
        function nz(s) { return String(s == null ? '' : s).toLocaleLowerCase('tr-TR').replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i').replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u'); }
        function pad(n) { return ('0' + n).slice(-2); }
        function gorece(iso) {
            if (!iso) return ''; var d = new Date(iso), n = new Date(); if (isNaN(d)) return '';
            var g = Math.round((new Date(n.getFullYear(), n.getMonth(), n.getDate()) - new Date(d.getFullYear(), d.getMonth(), d.getDate())) / 864e5), s = pad(d.getHours()) + ':' + pad(d.getMinutes());
            if (g === 0) return 'Bugün ' + s; if (g === 1) return 'Dün ' + s; if (g > 1 && g < 7) return g + ' gün önce';
            return d.getDate() + ' ' + AYK[d.getMonth()] + (d.getFullYear() !== n.getFullYear() ? ' ' + d.getFullYear() : '');
        }
        function tamTarih(iso) { var d = new Date(iso); return isNaN(d) ? '' : d.getDate() + ' ' + AYK[d.getMonth()] + ' ' + d.getFullYear() + ' · ' + pad(d.getHours()) + ':' + pad(d.getMinutes()); }
        function gunYaz(ymd) { var m = String(ymd || '').match(/^(\d{4})-(\d{2})-(\d{2})/); return m ? +m[3] + ' ' + AYK[+m[2] - 1] + ' ' + m[1] : ''; }
        function metin(s) { return esc(s).replace(/(https?:\/\/[^\s<]+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>').replace(/\n/g, '<br>'); }
        var TUR = { genel: ['Genel', 'fa-bullhorn', '#6366f1'], onemli: ['Önemli', 'fa-triangle-exclamation', '#f59e0b'], acil: ['Acil', 'fa-bell', '#f43f5e'], etkinlik: ['Etkinlik', 'fa-champagne-glasses', '#8b5cf6'], bilgi: ['Bilgilendirme', 'fa-circle-info', '#0ea5e9'] };
        function turChip(t) { var x = TUR[t] || TUR.genel; return '<span class="ikd-tur" style="--tc:' + x[2] + '"><i class="fa-solid ' + x[1] + '"></i> ' + x[0] + '</span>'; }
        function dosyaTur(mime, ad) {
            var e = String(ad || '').split('.').pop().toLowerCase();
            if (e === 'pdf') return ['fa-file-pdf', '#ef4444', 'PDF'];
            if (/^(doc|docx|odt)$/.test(e)) return ['fa-file-word', '#2563eb', 'DOC'];
            if (/^(xls|xlsx|ods|csv)$/.test(e)) return ['fa-file-excel', '#16a34a', e === 'csv' ? 'CSV' : 'XLS'];
            if (/^(ppt|pptx)$/.test(e)) return ['fa-file-powerpoint', '#ea580c', 'PPT'];
            if (/^(jpg|jpeg|png|webp|gif)$/.test(e)) return ['fa-file-image', '#8b5cf6', 'IMG'];
            if (e === 'zip') return ['fa-file-zipper', '#64748b', 'ZIP'];
            return ['fa-file-lines', '#0ea5e9', (e || 'DOSYA').toUpperCase().slice(0, 4)];
        }
        function indirUrl(id, goster) { return API + '?islem=dosya_indir&id=' + id + (goster ? '&goster=1' : ''); }
        function onizlenebilir(mime) { return /^(application\/pdf|image\/(jpeg|png|webp|gif))$/.test(mime || ''); }
        function csvIndir(ad, satirlar) {
            var csv = '﻿' + satirlar.map(function (r) { return r.map(function (x) { return '"' + String(x == null ? '' : x).replace(/"/g, '""') + '"'; }).join(';'); }).join('\r\n');
            var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = ad; document.body.appendChild(a); a.click(); setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 500);
        }
        function secYukle() { return SEC ? Promise.resolve(SEC) : getir({ islem: 'hedef_secenek' }).then(function (j) { SEC = j; return j; }); }
        function xhrForm(fd, onprog) {
            return new Promise(function (ok, no) {
                var x = new XMLHttpRequest(), tk = (window.TB && window.TB.csrf) || '';
                fd.append('_csrf', tk); x.open('POST', API); x.withCredentials = true; x.setRequestHeader('X-CSRF-Token', tk);
                if (x.upload && onprog) x.upload.onprogress = function (e) { if (e.lengthComputable) onprog(e.loaded / e.total); };
                x.onload = function () { var j; try { j = JSON.parse(x.responseText); } catch (e) { j = { ok: false, hata: 'Sunucu yanıtı okunamadı (' + x.status + ').' }; } if (j.ok) ok(j); else no(new Error(j.hata || 'İşlem başarısız.')); };
                x.onerror = function () { no(new Error('Bağlantı hatası. İnternetinizi kontrol edin.')); };
                x.send(fd);
            });
        }
        function rozet(id, n) { var e = document.getElementById(id); if (!e) return; e.textContent = n > 99 ? '99+' : n; e.hidden = !(n > 0); }
        function rozetleriYenile() { if (M.sabitYenile) M.sabitYenile(); getir({ islem: 'ozet' }).then(function (j) { rozet('ikm-r-duyuru', j.duyuru); rozet('ikm-r-anket', j.anket); }).catch(function () {}); }
        function toggle(ad, deger, metin2) { return '<label class="ikm-ozel-satir"><input type="checkbox" name="' + ad + '"' + (deger ? ' checked' : '') + '><i></i><span>' + metin2 + '</span></label>'; }

        /* ---------- kişi seçim listesi (arama + onay kutuları) ---------- */
        function kisiListe(kap, secili, liste, degisti) {
            kap.innerHTML = '<input type="search" class="ikd-kisi-ara" placeholder="Kişi ara…" autocomplete="off"><div class="ikd-kisi-sec" style="margin-top:8px"></div>';
            var ara = $('input', kap), kutu = $('.ikd-kisi-sec', kap);
            function ciz() {
                var a = nz(ara.value);
                var l = liste.filter(function (k) { return !a || nz(k.ad + ' ' + k.birim).indexOf(a) >= 0; });
                kutu.innerHTML = l.map(function (k) { return '<label><input type="checkbox" value="' + k.id + '"' + (secili.indexOf(k.id) >= 0 ? ' checked' : '') + '>' + av(k.ad, k.renk, k.resim) + '<span>' + esc(k.ad) + '</span><small>' + esc(k.birim) + '</small></label>'; }).join('') || '<div class="ikd-yaz-soluk" style="padding:10px">Sonuç yok</div>';
            }
            ara.oninput = ciz;
            kutu.onchange = function (e) { var i = +e.target.value, x = secili.indexOf(i); if (e.target.checked) { if (x < 0) secili.push(i); } else if (x >= 0) secili.splice(x, 1); if (degisti) degisti(); };
            ciz();
        }

        /* ---------- hedef kitle seçici: Herkes / Seçili birim ve kişiler ---------- */
        function hedefSecici(kap, h) {
            var S = SEC;
            kap.innerHTML = '<div class="ikd-hedef"><div class="ikm-segment" data-seg><button type="button" data-t="herkes"><i class="fa-solid fa-users"></i> Herkes</button><button type="button" data-t="secili"><i class="fa-solid fa-user-check"></i> Seçili birim / kişiler</button></div>' +
                '<div class="ikd-hedef-gov" data-gov hidden><div><div class="ikd-baslik-kucuk" style="margin-top:0">Birimler</div><div class="ikd-chipler" data-bir>' +
                S.birimler.map(function (b) { return '<button type="button" class="ikd-chip" data-b="' + esc(b.ad) + '">' + esc(b.ad) + ' <small>' + b.n + '</small></button>'; }).join('') + '</div></div>' +
                '<div><div class="ikd-baslik-kucuk" style="margin-top:0">Tek tek kişiler</div><div data-kisi></div></div></div>' +
                '<div class="ikd-say"><i class="fa-solid fa-users"></i><span data-say></span></div></div>';
            var gov = $('[data-gov]', kap), say = $('[data-say]', kap);
            function sayHesapla() {
                var n;
                if (h.tip !== 'secili') n = S.kisiler.length;
                else n = S.kisiler.filter(function (k) { return h.kisiler.indexOf(k.id) >= 0 || h.birimler.indexOf(k.birim) >= 0; }).length;
                say.innerHTML = '<b>' + n + '</b> kişiye ulaşır' + (h.tip === 'secili' && !n ? ' <span style="color:var(--ik-turuncu)">— birim ya da kişi seçin</span>' : '');
            }
            function guncelle() {
                $$('[data-t]', kap).forEach(function (b) { b.classList.toggle('aktif', b.dataset.t === h.tip); });
                gov.hidden = h.tip !== 'secili';
                $$('[data-b]', kap).forEach(function (b) { b.classList.toggle('acik', h.birimler.indexOf(b.dataset.b) >= 0); });
                sayHesapla();
            }
            kap.onclick = function (e) {
                var t = e.target.closest('[data-t]'); if (t) { h.tip = t.dataset.t; guncelle(); return; }
                var b = e.target.closest('[data-b]'); if (b) { var i = h.birimler.indexOf(b.dataset.b); if (i >= 0) h.birimler.splice(i, 1); else h.birimler.push(b.dataset.b); guncelle(); }
            };
            kisiListe($('[data-kisi]', kap), h.kisiler, S.kisiler, sayHesapla);
            guncelle();
        }
        function hedefAlani(fd, h, onek) { onek = onek || 'hedef_'; fd.append(onek + 'tip', h.tip); fd.append(onek + 'birim', JSON.stringify(h.tip === 'secili' ? h.birimler : [])); fd.append(onek + 'kisi', JSON.stringify(h.tip === 'secili' ? h.kisiler : [])); }
        function yeniHedef(x) { return { tip: x && x.hedef_tip === 'secili' ? 'secili' : 'herkes', birimler: x && x.hedef_birim ? x.hedef_birim.slice() : [], kisiler: x && x.hedef_kisi ? x.hedef_kisi.slice() : [] }; }

        /* ---------- bildirim kanalları (Site / SMS / E-posta) ---------- */
        function kanalSecici(kap, k) {
            function ciz() {
                kap.innerHTML = '<div class="ikm-kanallar">' + [['site', 'fa-solid fa-bell', 'Site'], ['mail', 'fa-regular fa-envelope', 'E-posta'], ['sms', 'fa-solid fa-comment-sms', 'SMS']].map(function (x) {
                    var uyar = k[x[0]] && ((x[0] === 'sms' && !SEC.sms_hazir) ? 'SMS ayarı yapılmamış' : (x[0] === 'mail' && !SEC.mail_hazir) ? 'E-posta ayarı yapılmamış' : '');
                    return '<button type="button" class="ikm-kanal' + (k[x[0]] ? ' acik' : '') + (uyar ? ' uyari' : '') + '" data-k="' + x[0] + '" aria-pressed="' + !!k[x[0]] + '" title="' + (uyar || '') + '"><i class="' + x[1] + '"></i><span>' + x[2] + '</span>' + (uyar ? '<em class="ikm-kanal-uyari"><i class="fa-solid fa-triangle-exclamation"></i></em>' : '') + '</button>';
                }).join('') + '</div>' + ((k.sms && !SEC.sms_hazir) || (k.mail && !SEC.mail_hazir) ? '<small class="ikd-yaz-soluk" style="color:var(--ik-turuncu)">Ayarı yapılmamış kanaldan gönderilemez — “Bildirim kanalları” sayfasından ayarlayın.</small>' : '');
            }
            kap.onclick = function (e) { var b = e.target.closest('[data-k]'); if (!b) return; k[b.dataset.k] = !k[b.dataset.k]; ciz(); };
            ciz();
        }
        function kanalAlani(fd, k) { fd.append('bildir_site', k.site ? '1' : '0'); fd.append('bildir_mail', k.mail ? '1' : '0'); fd.append('bildir_sms', k.sms ? '1' : '0'); }

        /* ==================================================================================================== */
        /*  DUYURULAR                                                                                           */
        /* ==================================================================================================== */
        var dF = { durum: 'yayinda', ara: '', sek: 'tum' }, DL = [];
        function duyuruCiz(ozel) {
            icerik.innerHTML = M.yukleniyor();
            getir({ islem: 'duyurular', durum: dF.durum }).then(function (j) {
                DL = j.liste; liste();
                if (ozel && ozel.id) duyuruAc(+ozel.id);
            }).catch(function (e) { icerik.innerHTML = M.hataKutu(e.message); });
        }
        function liste() {
            var a = nz(dF.ara);
            var L = DL.filter(function (d) {
                if (a && nz(d.baslik + ' ' + d.ozet + ' ' + d.yazar).indexOf(a) < 0) return false;
                if (dF.sek === 'okunmamis') return !d.okundu; if (dF.sek === 'onemli') return d.tur === 'onemli' || d.tur === 'acil'; return true;
            });
            var okunmamis = DL.filter(function (d) { return !d.okundu; }).length;
            var seg = function (anahtar, etiket, deger, sayi) { return '<button type="button" data-' + anahtar + '="' + deger + '" class="' + ((anahtar === 'dur' ? dF.durum : dF.sek) === deger ? 'aktif' : '') + '">' + etiket + (sayi ? ' <em class="ikm-rozet" style="height:18px;min-width:18px;font-style:normal;margin-left:2px">' + sayi + '</em>' : '') + '</button>'; };
            var h = '<div class="ikm-arac"><div class="ikm-ara"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="dy-ara" placeholder="Duyurularda ara…" value="' + esc(dF.ara) + '"></div>' +
                '<div class="ikm-segment">' + seg('sek', 'Tümü', 'tum') + seg('sek', 'Okunmamış', 'okunmamis', dF.durum === 'yayinda' ? okunmamis : 0) + seg('sek', 'Önemli', 'onemli') + '</div>' +
                (YON ? '<div class="ikm-segment">' + seg('dur', 'Yayında', 'yayinda') + seg('dur', 'Taslaklar', 'taslak') + seg('dur', 'Arşiv', 'arsiv') + '</div>' : '') +
                '<span class="ikd-bosluk"></span>' + (YON ? '<button type="button" class="ikm-btn ikm-btn-ana" id="dy-yeni"><i class="fa-solid fa-bullhorn"></i> Yeni duyuru</button>' : '') + '</div>';
            h += L.length ? '<div class="ikd-akis">' + L.map(kart).join('') + '</div>' :
                bosKutu('fa-bullhorn', dF.ara || dF.sek !== 'tum' ? 'Eşleşen duyuru yok' : (dF.durum === 'taslak' ? 'Taslak yok' : dF.durum === 'arsiv' ? 'Arşivde duyuru yok' : 'Henüz duyuru yok'), YON && dF.durum === 'yayinda' && !dF.ara ? 'İlk duyurunuzu “Yeni duyuru” ile yayınlayın; istediğiniz kişilere site, e-posta ya da SMS ile haber verebilirsiniz.' : 'Yeni duyurular burada görünecek.');
            icerik.innerHTML = h;
            var ara = $('#dy-ara'); ara.oninput = function () { dF.ara = ara.value; var p = ara.selectionStart; liste(); var y = $('#dy-ara'); y.focus(); try { y.setSelectionRange(p, p); } catch (e) {} };
            $$('[data-sek]', icerik).forEach(function (b) { b.onclick = function () { dF.sek = b.dataset.sek; liste(); }; });
            $$('[data-dur]', icerik).forEach(function (b) { b.onclick = function () { dF.durum = b.dataset.dur; duyuruCiz(); }; });
            $$('.ikd-kart', icerik).forEach(function (k) {
                var tm = k.querySelector('[data-tam]'), ab = k.querySelector('[data-ac]');
                if (tm && ab && (tm.scrollHeight > tm.clientHeight + 2 || (DL.filter(function (x) { return x.id === +k.dataset.id; })[0] || {}).tam.length > 220)) ab.hidden = false;
                function degis(e) { if (e) e.stopPropagation(); var a = k.classList.toggle('acik'); if (ab) { ab.querySelector('span').textContent = a ? 'Daralt' : 'Devamı'; } }
                if (ab) ab.onclick = degis;
                var db = k.querySelector('[data-detay]'); if (db) db.onclick = function (e) { e.stopPropagation(); duyuruAc(+k.dataset.id); };
                k.onclick = function (e) { if (e.target.closest('a,button')) return; if (ab && !ab.hidden) degis(); else duyuruAc(+k.dataset.id); };
                k.onkeydown = function (e) { if (e.target !== k) return; if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); if (ab && !ab.hidden) degis(); else duyuruAc(+k.dataset.id); } };
            });
            var yb = $('#dy-yeni'); if (yb) yb.onclick = function () { duyuruForm(null); };
        }
        function kart(d) {
            var t = TUR[d.tur] || TUR.genel;
            var ilerleme = YON && d.durum === 'yayinda' && d.alici > 0 ? '<span class="ikd-ilerleme" title="Okuyan / hedef kişi"><i><u style="width:' + Math.min(100, Math.round(d.okuyan * 100 / d.alici)) + '%"></u></i><small>' + d.okuyan + '/' + d.alici + ' okudu</small></span>' : '';
            return '<article class="ikd-kart' + (d.sabit ? ' sabit' : '') + (d.okundu ? ' okundu' : '') + '" tabindex="0" role="button" data-id="' + d.id + '" style="--tc:' + t[2] + '"><span class="ikd-serit"></span>' +
                '<div class="ikd-kart-ust">' + turChip(d.tur) + (d.sabit ? '<span class="ikm-cip mor"><i class="fa-solid fa-thumbtack"></i> Sabit</span>' : '') + (d.durum === 'taslak' ? '<span class="ikm-cip turuncu">Taslak</span>' : '') + (d.durum === 'arsiv' ? '<span class="ikm-cip gri">Arşiv</span>' : '') +
                (!d.okundu && d.durum === 'yayinda' ? '<span class="ikd-yeni">Yeni</span>' : '') + (YON && d.durum === 'yayinda' ? '<button type="button" class="ikd-pin' + (d.sabit ? ' aktif' : '') + '" data-pin="' + d.id + '" data-sabit="' + (d.sabit ? 1 : 0) + '" title="' + (d.sabit ? 'Sabiti kaldır' : 'Üste sabitle') + '"><i class="fa-solid fa-thumbtack"></i></button>' : '') + (d.okuyan != null ? '<span class="ikm-cip gri" title="Görenler / alıcılar"><i class="fa-solid fa-eye"></i> ' + d.okuyan + '/' + d.alici + '</span>' : '') + '<span class="ikd-zaman">' + esc(gorece(d.zaman)) + '</span></div>' +
                '<h3>' + esc(d.baslik) + '</h3>' + (d.tam ? '<div class="ikd-ozet" data-tam>' + metin(d.tam) + '</div>' : '') +
                '<div class="ikd-alt"><b>' + esc(d.yazar) + '</b><span class="ikm-cip gri"><i class="fa-solid fa-users"></i> ' + esc(d.hedef) + '</span>' + (d.ek_say ? '<span><i class="fa-solid fa-paperclip"></i> ' + d.ek_say + '</span>' : '') +
                (d.onay_iste && !d.onayladi && !YON ? '<span class="ikm-cip turuncu"><i class="fa-solid fa-hand"></i> Onayınız bekleniyor</span>' : '') + (d.onay_iste && d.onayladi ? '<span class="ikm-cip yesil"><i class="fa-solid fa-check"></i> Onayladınız</span>' : '') + ilerleme +
                '<span class="ikd-bosluk"></span><button type="button" class="ikd-ac" data-ac title="Aç / kapat" hidden><span>Devamı</span><i class="fa-solid fa-chevron-down"></i></button><button type="button" class="ikm-btn ikm-btn-kucuk ikd-detay" data-detay="' + d.id + '"><i class="fa-regular fa-window-maximize"></i> Detay</button></div></article>';
        }

        /* ---------- duyuru ayrıntısı ---------- */
        M.duyuruAc = duyuruAc;
        function duyuruAc(id) {
            var m = modal({ baslik: 'Duyuru', ikon: 'fa-bullhorn', gen: 780, govde: M.yukleniyor(), alt: '<span></span>' });
            getir({ islem: 'duyuru', id: id }).then(function (j) {
                var d = j.duyuru, t = TUR[d.tur] || TUR.genel, govde = $('.ikm-modal-govde', m.el), alt = $('.ikm-modal-alt', m.el);
                $('.ikm-modal-ust h3', m.el).textContent = 'Duyuru';
                function ciz(sek) {
                    var h = '<div class="ikd-det" style="--tc:' + t[2] + '"><div class="ikd-det-ust">' + turChip(d.tur) + (d.sabit ? '<span class="ikm-cip mor"><i class="fa-solid fa-thumbtack"></i> Sabit</span>' : '') +
                        (d.durum === 'taslak' ? '<span class="ikm-cip turuncu">Taslak</span>' : d.durum === 'arsiv' ? '<span class="ikm-cip gri">Arşiv</span>' : '') + '<span>' + esc(tamTarih(d.zaman)) + '</span>' +
                        '<span class="ikm-cip gri"><i class="fa-solid fa-users"></i> ' + esc(d.hedef) + '</span>' + (d.bitis ? '<span class="ikm-cip gri"><i class="fa-regular fa-clock"></i> ' + esc(gunYaz(d.bitis)) + ' tarihine kadar</span>' : '') + '</div>' +
                        '<h2>' + esc(d.baslik) + '</h2>' + (d.icerik ? '<div class="ikd-metin">' + metin(d.icerik) + '</div>' : '<div class="ikd-metin bos">Ayrıntı yazılmamış.</div>');
                    if (d.ekler.length) h += '<div class="ikd-baslik-kucuk">Ekler (' + d.ekler.length + ')</div><div class="ikd-ekler">' + d.ekler.map(function (e) {
                        var ft = dosyaTur(e.mime, e.ad);
                        return '<a class="ikd-ek" href="' + indirUrl(e.id) + '"><span class="ikd-ek-ik" style="--fc:' + ft[1] + '"><i class="fa-solid ' + ft[0] + '"></i></span><span class="ikd-ek-ad"><b>' + esc(e.ad) + '</b><small>' + boyutYaz(e.boyut) + '</small></span>' +
                            (onizlenebilir(e.mime) ? '<span class="ikd-ikon-btn" data-onizle="' + e.id + '" title="Önizle"><i class="fa-regular fa-eye"></i></span>' : '') + '<i class="fa-solid fa-download"></i></a>';
                    }).join('') + '</div>';
                    if (d.onay_iste && d.durum === 'yayinda') h += d.onayladi ? '<div class="ikd-onay-kutu tamam"><i class="fa-solid fa-circle-check"></i> Okuduğunuzu onayladınız.</div>' :
                        '<div class="ikd-onay-kutu"><i class="fa-solid fa-hand" style="color:var(--ik-turuncu)"></i><span>Bu duyurunun okunduğunun <b>onaylanması istendi</b>.</span><button type="button" class="ikm-btn ikm-btn-ana" id="dy-onay"><i class="fa-solid fa-check"></i> Okudum, anladım</button></div>';
                    h += '<div class="ikd-yazar">' + av(d.yazar, '', '') + '<div><b>' + esc(d.yazar) + '</b><small>Yayınlayan</small></div></div>';
                    if (d.okuyanlar) {
                        var ok = d.okuyanlar, okmaz = d.okumayanlar;
                        h += '<div class="ikd-sekme"><button type="button" data-sk="ok" class="' + (sek === 'ok' ? 'aktif' : '') + '"><i class="fa-solid fa-eye"></i> Okuyanlar <em>' + ok.length + '</em></button><button type="button" data-sk="okmaz" class="' + (sek === 'okmaz' ? 'aktif' : '') + '"><i class="fa-solid fa-eye-slash"></i> Okumayanlar <em>' + okmaz.length + '</em></button></div>' +
                            '<div class="ikd-kisiler">' + ((sek === 'ok' ? ok.map(function (k) { return '<div class="ikd-kisi"><b>' + esc(k.ad) + '</b>' + (k.onay ? '<span class="ikm-cip yesil"><i class="fa-solid fa-check"></i> Onayladı</span>' : '') + '<small>' + esc(k.birim) + ' · ' + esc(gorece(k.zaman)) + '</small></div>'; }).join('') :
                                okmaz.map(function (k) { return '<div class="ikd-kisi"><b>' + esc(k.ad) + '</b><small>' + esc(k.birim) + '</small></div>'; }).join('')) || '<div class="ikd-yaz-soluk" style="padding:10px">Kimse yok.</div>') + '</div>';
                    }
                    govde.innerHTML = h + '</div>';
                    $$('[data-sk]', govde).forEach(function (b) { b.onclick = function () { ciz(b.dataset.sk); }; });
                    $$('[data-onizle]', govde).forEach(function (b) { b.onclick = function (e) { e.preventDefault(); e.stopPropagation(); window.open(indirUrl(b.dataset.onizle, 1), '_blank', 'noopener'); }; });
                    var ob = $('#dy-onay', govde); if (ob) ob.onclick = function () { ob.disabled = true; gonder('duyuru_oku', { id: id, onay: 1 }).then(function () { d.onayladi = true; ciz(sek); rozetleriYenile(); bildir('Teşekkürler, onayınız kaydedildi.', 'basari'); }).catch(function (e) { ob.disabled = false; bildir(e.message, 'hata'); }); };
                }
                ciz('ok');
                if (d.durum === 'yayinda') gonder('duyuru_oku', { id: id }).then(function () { rozetleriYenile(); var k = DL.filter(function (x) { return x.id === id; })[0]; if (k && !k.okundu) { k.okundu = true; if (aktifGorunum === 'duyurular') liste(); } }).catch(function () {});
                if (YON) {
                    alt.innerHTML = '<button type="button" class="ikm-btn ikm-btn-kucuk" id="dy-duz"><i class="fa-solid fa-pen"></i> Düzenle</button>' +
                        '<button type="button" class="ikm-btn ikm-btn-kucuk" id="dy-sabit"><i class="fa-solid fa-thumbtack"></i> ' + (d.sabit ? 'Sabiti kaldır' : 'Sabitle') + '</button>' +
                        (d.durum === 'yayinda' ? '<button type="button" class="ikm-btn ikm-btn-kucuk" id="dy-arsiv"><i class="fa-solid fa-box-archive"></i> Arşivle</button>' : '<button type="button" class="ikm-btn ikm-btn-kucuk" id="dy-yayin"><i class="fa-solid fa-upload"></i> Yayınla</button>') +
                        '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" id="dy-sil"><i class="fa-solid fa-trash"></i> Sil</button><span class="ikd-bosluk"></span><button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Kapat</button>';
                    $$('[data-kapat]', alt).forEach(function (b) { b.onclick = m.kapat; });
                    var dur = function (veri, mesaj) { gonder('duyuru_durum', Object.assign({ id: id }, veri)).then(function () { m.kapat(); bildir(mesaj, 'basari'); duyuruCiz(); }).catch(function (e) { bildir(e.message, 'hata'); }); };
                    $('#dy-sabit', alt).onclick = function () { dur({ sabit: d.sabit ? 0 : 1 }, d.sabit ? 'Sabit kaldırıldı.' : 'Duyuru üste sabitlendi.'); };
                    var ar = $('#dy-arsiv', alt); if (ar) ar.onclick = function () { dur({ durum: 'arsiv' }, 'Duyuru arşivlendi.'); };
                    var yy = $('#dy-yayin', alt); if (yy) yy.onclick = function () { dur({ durum: 'yayinda' }, 'Duyuru yayında.'); };
                    $('#dy-duz', alt).onclick = function () { m.kapat(); duyuruForm(d); };
                    $('#dy-sil', alt).onclick = function () { onayla({ baslik: 'Duyuru silinsin mi?', mesaj: 'Duyuru, ekleri ve okunma kayıtları kalıcı olarak silinir.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('duyuru_sil', { id: id }).then(function () { m.kapat(); bildir('Duyuru silindi.', 'basari'); duyuruCiz(); }).catch(function (e) { bildir(e.message, 'hata'); }); }); };
                } else { alt.innerHTML = '<button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Kapat</button>'; $('[data-kapat]', alt).onclick = m.kapat; }
            }).catch(function (e) { $('.ikm-modal-govde', m.el).innerHTML = M.hataKutu(e.message); });
        }

        /* ---------- duyuru oluştur / düzenle ---------- */
        function duyuruForm(d) {
            var yeni = !d, S0 = d ? d : {};
            var f = { tur: S0.tur || 'genel', hedef: yeniHedef(S0), kanal: { site: true, mail: false, sms: false }, dosyalar: [], ek: (S0.ekler || []).slice(), ek_sil: [] };
            var m = modal({ baslik: yeni ? 'Yeni duyuru' : 'Duyuruyu düzenle', ikon: 'fa-bullhorn', gen: 820, sabit: true, govde: M.yukleniyor(), alt: '<span></span>' });
            secYukle().then(function () {
                var govde = $('.ikm-modal-govde', m.el);
                var yayinda = d && d.durum === 'yayinda';
                govde.innerHTML = '<form class="ikm-form" id="dy-f" autocomplete="off" onsubmit="return false">' +
                    '<label class="ikm-alan"><span>Başlık <b>*</b></span><input name="baslik" maxlength="200" value="' + esc(S0.baslik || '') + '" placeholder="Örn. Bayram tatili çalışma düzeni"></label>' +
                    '<div class="ikm-alan"><span>Duyuru türü</span><div class="ikd-turler">' + Object.keys(TUR).map(function (k) { return '<button type="button" class="ikd-tur-sec' + (f.tur === k ? ' aktif' : '') + '" data-tur="' + k + '" style="--tc:' + TUR[k][2] + '"><i class="fa-solid ' + TUR[k][1] + '"></i> ' + TUR[k][0] + '</button>'; }).join('') + '</div></div>' +
                    '<label class="ikm-alan"><span>İçerik</span><textarea name="icerik" rows="8" maxlength="20000" placeholder="Duyuru metnini yazın… Bağlantılar otomatik tıklanabilir olur.">' + esc(S0.icerik || '') + '</textarea><span class="ikd-sayac" id="dy-say">0 / 20000</span></label>' +
                    '<div class="ikm-alan"><span>Ekler <small>PDF, görsel, Word, Excel, PowerPoint, ZIP · dosya başına en fazla 25 MB</small></span>' +
                    '<label class="ikm-dk" id="dy-dk"><input type="file" id="dy-dosya" multiple><span class="ikm-dk-ik"><i class="fa-solid fa-paperclip"></i></span><span class="ikm-dk-y"><b>Dosya seçin veya buraya sürükleyin</b><small>Birden fazla dosya ekleyebilirsiniz</small></span></label><div class="ikd-ek-liste" id="dy-ekler"></div></div>' +
                    '<div class="ikm-alan"><span>Kimler görsün?</span><div id="dy-hedef"></div></div>' +
                    '<div class="ikd-izgara2"><div class="ikd-form-bolum"><div class="ikd-baslik-kucuk">Seçenekler</div>' + toggle('sabit', S0.sabit, 'Listenin en üstüne sabitle') + toggle('onay_iste', S0.onay_iste, '“Okudum, anladım” onayı iste') + '</div>' +
                    '<label class="ikm-alan"><span>Yayın bitiş tarihi <small>(boşsa süresiz)</small></span><input type="date" name="bitis" value="' + esc(S0.bitis || '') + '"></label></div>' +
                    '<div class="ikd-form-bolum"><div class="ikd-baslik-kucuk">' + (yayinda ? 'Alıcılara yeniden haber ver' : 'Yayınlayınca kimlere, nereden haber verilsin?') + '</div>' + (yayinda ? toggle('yeniden', false, 'Değişikliği alıcılara tekrar bildir') : '') + '<div id="dy-kanal"></div></div>' +
                    '<div class="ikd-ilerleme-cubuk" id="dy-ilr" hidden><i></i></div></form>';
                var form = $('#dy-f', m.el), say = $('#dy-say', m.el), ta = form.elements.icerik;
                var sayGunc = function () { say.textContent = ta.value.length + ' / 20000'; }; ta.oninput = sayGunc; sayGunc();
                $$('[data-tur]', form).forEach(function (b) { b.onclick = function () { f.tur = b.dataset.tur; $$('[data-tur]', form).forEach(function (x) { x.classList.toggle('aktif', x === b); }); }; });
                hedefSecici($('#dy-hedef', m.el), f.hedef); kanalSecici($('#dy-kanal', m.el), f.kanal);
                function ekCiz() {
                    $('#dy-ekler', m.el).innerHTML = f.ek.map(function (e) { var ft = dosyaTur('', e.ad); return '<div class="ikd-ek"><span class="ikd-ek-ik" style="--fc:' + ft[1] + '"><i class="fa-solid ' + ft[0] + '"></i></span><span class="ikd-ek-ad"><b>' + esc(e.ad) + '</b><small>' + boyutYaz(e.boyut) + ' · kayıtlı</small></span><button type="button" class="ikd-ek-sil" data-esil="' + e.id + '" title="Kaldır"><i class="fa-solid fa-xmark"></i></button></div>'; }).join('') +
                        f.dosyalar.map(function (x, i) { var ft = dosyaTur('', x.name); return '<div class="ikd-ek"><span class="ikd-ek-ik" style="--fc:' + ft[1] + '"><i class="fa-solid ' + ft[0] + '"></i></span><span class="ikd-ek-ad"><b>' + esc(x.name) + '</b><small>' + boyutYaz(x.size) + (x.size > 25 * 1048576 ? ' · <span style="color:var(--ik-kirmizi)">25 MB üstü</span>' : '') + '</small></span><button type="button" class="ikd-ek-sil" data-ysil="' + i + '" title="Kaldır"><i class="fa-solid fa-xmark"></i></button></div>'; }).join('');
                }
                $('#dy-ekler', m.el).onclick = function (e) {
                    var b = e.target.closest('[data-esil]'); if (b) { f.ek_sil.push(+b.dataset.esil); f.ek = f.ek.filter(function (x) { return x.id !== +b.dataset.esil; }); ekCiz(); return; }
                    b = e.target.closest('[data-ysil]'); if (b) { f.dosyalar.splice(+b.dataset.ysil, 1); ekCiz(); }
                };
                var inp = $('#dy-dosya', m.el), dk = $('#dy-dk', m.el);
                inp.onchange = function () { Array.prototype.forEach.call(inp.files, function (x) { f.dosyalar.push(x); }); inp.value = ''; ekCiz(); };
                ['dragenter', 'dragover'].forEach(function (ev) { dk.addEventListener(ev, function () { dk.classList.add('uz'); }); }); ['dragleave', 'drop'].forEach(function (ev) { dk.addEventListener(ev, function () { dk.classList.remove('uz'); }); });
                ekCiz();
                var alt = $('.ikm-modal-alt', m.el) || (function () { var a = document.createElement('div'); a.className = 'ikm-modal-alt'; $('.ikm-modal', m.el).appendChild(a); return a; })();
                alt.innerHTML = '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button>' + (!yayinda ? '<button type="button" class="ikm-btn" id="dy-taslak"><i class="fa-regular fa-floppy-disk"></i> Taslak kaydet</button>' : '') +
                    '<button type="button" class="ikm-btn ikm-btn-ana" id="dy-yayinla"><i class="fa-solid fa-paper-plane"></i> ' + (yayinda ? 'Kaydet' : 'Yayınla') + '</button>';
                $$('[data-kapat]', alt).forEach(function (b) { b.onclick = m.kapat; });
                function kaydet(durum, dugme) {
                    var baslik = form.elements.baslik.value.trim();
                    if (!baslik) { form.elements.baslik.classList.add('hatali'); form.elements.baslik.focus(); bildir('Başlık yazın.', 'uyari'); return; }
                    if (f.dosyalar.some(function (x) { return x.size > 25 * 1048576; })) { bildir('25 MB’dan büyük dosyaları kaldırın.', 'uyari'); return; }
                    if (f.hedef.tip === 'secili' && !f.hedef.birimler.length && !f.hedef.kisiler.length) { bildir('Birim ya da kişi seçin veya “Herkes”i işaretleyin.', 'uyari'); return; }
                    var fd = new FormData(); fd.append('islem', 'duyuru_kaydet'); if (d) fd.append('id', d.id);
                    fd.append('baslik', baslik); fd.append('icerik', ta.value); fd.append('tur', f.tur); fd.append('durum', durum);
                    if (form.elements.sabit.checked) fd.append('sabit', '1'); if (form.elements.onay_iste.checked) fd.append('onay_iste', '1'); fd.append('bitis', form.elements.bitis.value);
                    if (form.elements.yeniden && form.elements.yeniden.checked) fd.append('yeniden_bildir', '1');
                    hedefAlani(fd, f.hedef); kanalAlani(fd, f.kanal); fd.append('ek_sil', JSON.stringify(f.ek_sil));
                    f.dosyalar.forEach(function (x) { fd.append('dosyalar[]', x); });
                    var cubuk = $('#dy-ilr', m.el), ic = $('i', cubuk); cubuk.hidden = !f.dosyalar.length;
                    $$('button', alt).forEach(function (b) { b.disabled = true; }); dugme.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gönderiliyor…';
                    xhrForm(fd, function (p) { ic.style.width = Math.round(p * 100) + '%'; }).then(function (j) {
                        m.kapat(); bildir(durum === 'taslak' ? 'Taslak kaydedildi.' : (j.bildirilen ? 'Duyuru yayınlandı, ' + j.bildirilen + ' kişiye haber verildi.' : 'Duyuru yayınlandı.'), 'basari');
                        dF.durum = durum === 'taslak' ? 'taslak' : 'yayinda'; duyuruCiz(); rozetleriYenile();
                    }).catch(function (e) { $$('button', alt).forEach(function (b) { b.disabled = false; }); dugme.innerHTML = dugme.id === 'dy-taslak' ? '<i class="fa-regular fa-floppy-disk"></i> Taslak kaydet' : '<i class="fa-solid fa-paper-plane"></i> ' + (yayinda ? 'Kaydet' : 'Yayınla'); bildir(e.message, 'hata'); });
                }
                $('#dy-yayinla', alt).onclick = function () { kaydet(d && d.durum !== 'taslak' ? d.durum : 'yayinda', this); };
                var tb = $('#dy-taslak', alt); if (tb) tb.onclick = function () { kaydet('taslak', this); };
            }).catch(function (e) { $('.ikm-modal-govde', m.el).innerHTML = M.hataKutu(e.message); });
        }

        /* ==================================================================================================== */
        /*  DOSYALAR                                                                                            */
        /* ==================================================================================================== */
        var fF = { ara: '', klasor: '' }, FL = [];
        function dosyaCiz(ozel) {
            icerik.innerHTML = M.yukleniyor();
            getir({ islem: 'dosyalar' }).then(function (j) { FL = j.liste; dosyaListe(); }).catch(function (e) { icerik.innerHTML = M.hataKutu(e.message); });
        }
        function dosyaListe() {
            var a = nz(fF.ara);
            var klasorler = []; FL.forEach(function (f) { if (f.klasor && klasorler.indexOf(f.klasor) < 0) klasorler.push(f.klasor); }); klasorler.sort(function (x, y) { return x.localeCompare(y, 'tr'); });
            var L = FL.filter(function (f) { return (!a || nz(f.ad + ' ' + f.aciklama + ' ' + f.klasor + ' ' + f.orijinal).indexOf(a) >= 0) && (!fF.klasor || (fF.klasor === '__genel' ? !f.klasor : f.klasor === fF.klasor)); });
            var h = '<div class="ikm-arac"><div class="ikm-ara"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="df-ara" placeholder="Dosyalarda ara…" value="' + esc(fF.ara) + '"></div>' +
                (klasorler.length ? '<select id="df-klasor"><option value="">Tüm klasörler</option>' + klasorler.map(function (k) { return '<option value="' + esc(k) + '"' + (fF.klasor === k ? ' selected' : '') + '>' + esc(k) + '</option>'; }).join('') + '<option value="__genel"' + (fF.klasor === '__genel' ? ' selected' : '') + '>Klasörsüz</option></select>' : '') +
                '<span class="ikd-bosluk"></span>' + (YON ? '<button type="button" class="ikm-btn ikm-btn-ana" id="df-yeni"><i class="fa-solid fa-cloud-arrow-up"></i> Dosya yükle</button>' : '') + '</div>';
            if (!L.length) h += bosKutu('fa-folder-open', fF.ara || fF.klasor ? 'Eşleşen dosya yok' : 'Paylaşılan dosya yok', YON && !fF.ara ? 'Yönetmelik, form ya da sunum gibi dosyaları yükleyip kimlerin göreceğini ve indirebileceğini seçin.' : 'Sizinle paylaşılan dosyalar burada görünecek.');
            else {
                var grup = {}; L.forEach(function (f) { (grup[f.klasor || ''] = grup[f.klasor || ''] || []).push(f); });
                var adlar = Object.keys(grup).sort(function (x, y) { return x === '' ? 1 : y === '' ? -1 : x.localeCompare(y, 'tr'); });
                adlar.forEach(function (k) { h += (adlar.length > 1 || k ? '<div class="ikd-klasor-baslik"><i class="fa-solid fa-folder"></i> ' + esc(k || 'Diğer dosyalar') + ' <small>' + grup[k].length + '</small></div>' : '') + '<div class="ikd-dosya-izgara">' + grup[k].map(dosyaKart).join('') + '</div>'; });
            }
            icerik.innerHTML = h;
            var ara = $('#df-ara'); ara.oninput = function () { fF.ara = ara.value; var p = ara.selectionStart; dosyaListe(); var y = $('#df-ara'); y.focus(); try { y.setSelectionRange(p, p); } catch (e) {} };
            var ks = $('#df-klasor'); if (ks) ks.onchange = function () { fF.klasor = ks.value; dosyaListe(); };
            var yb = $('#df-yeni'); if (yb) yb.onclick = function () { dosyaForm(null); };
            icerik.onclick = function (e) {
                var b;
                if ((b = e.target.closest('[data-onizle]'))) { window.open(indirUrl(b.dataset.onizle, 1), '_blank', 'noopener'); return; }
                if ((b = e.target.closest('[data-duz]'))) { dosyaForm(FL.filter(function (x) { return x.id === +b.dataset.duz; })[0]); return; }
                if ((b = e.target.closest('[data-indirenler]'))) { indirenler(+b.dataset.indirenler); return; }
                if ((b = e.target.closest('[data-sil]'))) { var id = +b.dataset.sil; onayla({ baslik: 'Dosya silinsin mi?', mesaj: 'Dosya herkes için kaldırılır ve geri getirilemez.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('dosya_sil', { id: id }).then(function () { bildir('Dosya silindi.', 'basari'); dosyaCiz(); }).catch(function (er) { bildir(er.message, 'hata'); }); }); }
            };
        }
        function dosyaKart(f) {
            var ft = dosyaTur(f.mime, f.orijinal);
            return '<div class="ikd-dosya"><div class="ikd-dosya-ik" style="--fc:' + ft[1] + '"><i class="fa-solid ' + ft[0] + '"></i><b>' + ft[2] + '</b></div><div class="ikd-dosya-g"><h4>' + esc(f.ad) + '</h4>' + (f.aciklama ? '<p>' + esc(f.aciklama) + '</p>' : '') +
                '<div class="ikd-dosya-meta"><span class="ikm-cip gri">' + boyutYaz(f.boyut) + '</span><span class="ikm-cip gri">' + esc(gorece(f.tarih)) + '</span>' + (f.kisitli ? '<span class="ikm-cip ' + (f.indirebilir ? 'mor' : 'turuncu') + '"><i class="fa-solid fa-lock"></i> ' + (f.indirebilir ? 'Seçili kişiler indirebilir' : 'İndirme kısıtlı') + '</span>' : '') +
                (YON ? '<span class="ikm-cip mavi"><i class="fa-solid fa-users"></i> ' + esc(f.hedef) + '</span><span class="ikm-cip gri"><i class="fa-solid fa-download"></i> ' + f.indirme_say + '</span>' : '') + '</div>' +
                '<div class="ikd-dosya-btn">' + (f.indirebilir ? (onizlenebilir(f.mime) ? '<button type="button" class="ikm-btn" data-onizle="' + f.id + '"><i class="fa-regular fa-eye"></i> Önizle</button>' : '') + '<a class="ikm-btn ikm-btn-ana" href="' + indirUrl(f.id) + '"><i class="fa-solid fa-download"></i> İndir</a>' :
                    '<span class="ikd-yaz-soluk"><i class="fa-solid fa-lock"></i> Bu dosyayı yalnızca görüntüleyebilirsiniz; indirme yetkiniz yok.</span>') +
                (YON ? '<button type="button" class="ikd-ikon-btn" data-indirenler="' + f.id + '" title="Kimler indirdi?"><i class="fa-solid fa-user-clock"></i></button><button type="button" class="ikd-ikon-btn" data-duz="' + f.id + '" title="Düzenle"><i class="fa-solid fa-pen"></i></button><button type="button" class="ikd-ikon-btn tehlike" data-sil="' + f.id + '" title="Sil"><i class="fa-solid fa-trash"></i></button>' : '') + '</div></div></div>';
        }
        function indirenler(id) {
            var m = modal({ baslik: 'Kimler indirdi?', ikon: 'fa-user-clock', gen: 520, govde: M.yukleniyor(), alt: '<button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Kapat</button>' });
            getir({ islem: 'dosya_indirenler', id: id }).then(function (j) {
                $('.ikm-modal-govde', m.el).innerHTML = j.liste.length ? '<div class="ikd-kisiler">' + j.liste.map(function (k) { return '<div class="ikd-kisi"><b>' + esc(k.ad) + '</b><span class="ikm-cip gri">' + k.n + '×</span><small>' + esc(k.birim) + ' · ' + esc(gorece(k.son)) + '</small></div>'; }).join('') + '</div>' : bosKutu('fa-download', 'Henüz kimse indirmedi');
            }).catch(function (e) { $('.ikm-modal-govde', m.el).innerHTML = M.hataKutu(e.message); });
        }
        function dosyaForm(f0) {
            var yeni = !f0, f = { hedef: yeniHedef(f0), indirme: f0 ? f0.indirme_tip : 'gorenler', indirmeKisi: f0 && f0.indirme_kisi ? f0.indirme_kisi.slice() : [], kanal: { site: true, mail: false, sms: false }, dosyalar: [] };
            var m = modal({ baslik: yeni ? 'Dosya yükle' : 'Dosyayı düzenle', ikon: 'fa-cloud-arrow-up', gen: 780, sabit: true, govde: M.yukleniyor(), alt: '<span></span>' });
            secYukle().then(function () {
                var govde = $('.ikm-modal-govde', m.el);
                govde.innerHTML = '<form class="ikm-form" id="df-f" autocomplete="off" onsubmit="return false">' +
                    (yeni ? '<div class="ikm-alan"><span>Dosya <b>*</b> <small>PDF, görsel, Word, Excel, PowerPoint, metin, ZIP · en fazla 25 MB</small></span><label class="ikm-dk" id="df-dk"><input type="file" id="df-dosya" multiple><span class="ikm-dk-ik"><i class="fa-solid fa-cloud-arrow-up"></i></span><span class="ikm-dk-y"><b>Dosya seçin veya buraya sürükleyin</b><small>Birden fazla dosya aynı ayarlarla yüklenir</small></span></label><div class="ikd-ek-liste" id="df-liste"></div></div>' : '') +
                    '<label class="ikm-alan" id="df-ad-kap"' + (yeni ? ' hidden' : '') + '><span>Görünen ad <b>*</b></span><input name="ad" maxlength="200" value="' + esc(f0 ? f0.ad : '') + '"></label>' +
                    '<label class="ikm-alan"><span>Açıklama <small>(isteğe bağlı)</small></span><input name="aciklama" maxlength="500" value="' + esc(f0 ? f0.aciklama : '') + '" placeholder="Ör. 2026 iç yönetmelik, son sürüm"></label>' +
                    '<label class="ikm-alan"><span>Klasör <small>(isteğe bağlı — dosyaları gruplar)</small></span><input name="klasor" maxlength="80" list="df-klasorler" value="' + esc(f0 ? f0.klasor : '') + '" placeholder="Ör. Yönetmelikler"><datalist id="df-klasorler">' + SEC.klasorler.map(function (k) { return '<option value="' + esc(k) + '">'; }).join('') + '</datalist></label>' +
                    '<div class="ikm-alan"><span>Kimler görebilsin?</span><div id="df-hedef"></div></div>' +
                    '<div class="ikm-alan"><span>Kimler indirebilsin?</span><div class="ikd-form-bolum">' +
                    '<label class="ikd-secenek-kart"><input type="radio" name="indirme" value="gorenler"' + (f.indirme !== 'secili' ? ' checked' : '') + '><span><b>Görebilen herkes</b><small>Dosyayı gören kişiler indirebilir.</small></span></label>' +
                    '<label class="ikd-secenek-kart"><input type="radio" name="indirme" value="secili"' + (f.indirme === 'secili' ? ' checked' : '') + '><span><b>Yalnızca seçtiğim kişiler</b><small>Diğerleri dosyayı listede görür ama indiremez.</small></span></label>' +
                    '<div id="df-ind-kisi" ' + (f.indirme === 'secili' ? '' : 'hidden') + '></div></div></div>' +
                    (yeni ? '<div class="ikd-form-bolum">' + toggle('bildir', false, 'Yüklenince alıcılara haber ver') + '<div id="df-kanal"></div></div>' : '') +
                    '<div class="ikd-ilerleme-cubuk" id="df-ilr" hidden><i></i></div></form>';
                var form = $('#df-f', m.el);
                hedefSecici($('#df-hedef', m.el), f.hedef);
                kisiListe($('#df-ind-kisi', m.el), f.indirmeKisi, SEC.kisiler);
                $$('[name=indirme]', form).forEach(function (r) { r.onchange = function () { f.indirme = r.value; $('#df-ind-kisi', m.el).hidden = f.indirme !== 'secili'; }; });
                if (yeni) kanalSecici($('#df-kanal', m.el), f.kanal);
                function listeCiz() {
                    var kap = $('#df-liste', m.el); if (!kap) return;
                    kap.innerHTML = f.dosyalar.map(function (x, i) { var ft = dosyaTur('', x.name); return '<div class="ikd-ek"><span class="ikd-ek-ik" style="--fc:' + ft[1] + '"><i class="fa-solid ' + ft[0] + '"></i></span><span class="ikd-ek-ad"><b>' + esc(x.name) + '</b><small>' + boyutYaz(x.size) + (x.size > 25 * 1048576 ? ' · <span style="color:var(--ik-kirmizi)">25 MB üstü</span>' : '') + '</small></span><button type="button" class="ikd-ek-sil" data-ysil="' + i + '"><i class="fa-solid fa-xmark"></i></button></div>'; }).join('');
                    var adKap = $('#df-ad-kap', m.el); adKap.hidden = f.dosyalar.length !== 1;
                    if (f.dosyalar.length === 1 && !form.elements.ad.value) form.elements.ad.value = f.dosyalar[0].name.replace(/\.[^.]+$/, '');
                    kap.onclick = function (e) { var b = e.target.closest('[data-ysil]'); if (b) { f.dosyalar.splice(+b.dataset.ysil, 1); listeCiz(); } };
                }
                if (yeni) {
                    var inp = $('#df-dosya', m.el), dk = $('#df-dk', m.el);
                    inp.onchange = function () { Array.prototype.forEach.call(inp.files, function (x) { f.dosyalar.push(x); }); inp.value = ''; listeCiz(); };
                    ['dragenter', 'dragover'].forEach(function (ev) { dk.addEventListener(ev, function () { dk.classList.add('uz'); }); }); ['dragleave', 'drop'].forEach(function (ev) { dk.addEventListener(ev, function () { dk.classList.remove('uz'); }); });
                }
                var alt = $('.ikm-modal-alt', m.el) || (function () { var a = document.createElement('div'); a.className = 'ikm-modal-alt'; $('.ikm-modal', m.el).appendChild(a); return a; })();
                alt.innerHTML = '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="df-kaydet"><i class="fa-solid ' + (yeni ? 'fa-cloud-arrow-up' : 'fa-check') + '"></i> ' + (yeni ? 'Yükle' : 'Kaydet') + '</button>';
                $$('[data-kapat]', alt).forEach(function (b) { b.onclick = m.kapat; });
                $('#df-kaydet', alt).onclick = function () {
                    var dugme = this, fd = new FormData();
                    if (yeni) {
                        if (!f.dosyalar.length) { bildir('Önce bir dosya seçin.', 'uyari'); return; }
                        if (f.dosyalar.some(function (x) { return x.size > 25 * 1048576; })) { bildir('25 MB’dan büyük dosyaları kaldırın.', 'uyari'); return; }
                    } else if (!form.elements.ad.value.trim()) { form.elements.ad.classList.add('hatali'); bildir('Dosya adı boş olamaz.', 'uyari'); return; }
                    if (f.hedef.tip === 'secili' && !f.hedef.birimler.length && !f.hedef.kisiler.length) { bildir('Birim ya da kişi seçin veya “Herkes”i işaretleyin.', 'uyari'); return; }
                    if (f.indirme === 'secili' && !f.indirmeKisi.length) { bildir('İndirebilecek kişileri seçin ya da “Görebilen herkes”i işaretleyin.', 'uyari'); return; }
                    fd.append('islem', yeni ? 'dosya_yukle' : 'dosya_guncelle'); if (!yeni) fd.append('id', f0.id);
                    fd.append('ad', form.elements.ad.value.trim()); fd.append('aciklama', form.elements.aciklama.value.trim()); fd.append('klasor', form.elements.klasor.value.trim());
                    hedefAlani(fd, f.hedef); fd.append('indirme_tip', f.indirme); fd.append('indirme_kisi', JSON.stringify(f.indirme === 'secili' ? f.indirmeKisi : []));
                    if (yeni) { f.dosyalar.forEach(function (x) { fd.append('dosya[]', x); }); if (form.elements.bildir.checked) fd.append('bildir', '1'); kanalAlani(fd, f.kanal); }
                    var cubuk = $('#df-ilr', m.el), ic = $('i', cubuk); cubuk.hidden = !yeni;
                    $$('button', alt).forEach(function (b) { b.disabled = true; }); dugme.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + (yeni ? 'Yükleniyor…' : 'Kaydediliyor…');
                    xhrForm(fd, function (p) { ic.style.width = Math.round(p * 100) + '%'; }).then(function (j) {
                        m.kapat(); bildir(yeni ? (j.adet + ' dosya yüklendi' + (j.bildirilen ? ', ' + j.bildirilen + ' kişiye haber verildi.' : '.')) : 'Dosya güncellendi.', 'basari'); dosyaCiz();
                    }).catch(function (e) { $$('button', alt).forEach(function (b) { b.disabled = false; }); dugme.innerHTML = '<i class="fa-solid ' + (yeni ? 'fa-cloud-arrow-up' : 'fa-check') + '"></i> ' + (yeni ? 'Yükle' : 'Kaydet'); bildir(e.message, 'hata'); });
                };
            }).catch(function (e) { $('.ikm-modal-govde', m.el).innerHTML = M.hataKutu(e.message); });
        }

        /* ==================================================================================================== */
        /*  ANKETLER                                                                                            */
        /* ==================================================================================================== */
        var aF = { ara: '' }, AL = [];
        function anketCiz(ozel) {
            icerik.innerHTML = M.yukleniyor();
            getir({ islem: 'anketler' }).then(function (j) { AL = j.liste; anketListe(); if (ozel && ozel.id) anketAc(+ozel.id); }).catch(function (e) { icerik.innerHTML = M.hataKutu(e.message); });
        }
        function anketListe() {
            var a = nz(aF.ara);
            var L = AL.filter(function (x) { return !a || nz(x.baslik + ' ' + x.aciklama).indexOf(a) >= 0; });
            var h = '<div class="ikm-arac"><div class="ikm-ara"><i class="fa-solid fa-magnifying-glass"></i><input type="search" id="an-ara" placeholder="Anketlerde ara…" value="' + esc(aF.ara) + '"></div><span class="ikd-bosluk"></span>' +
                (YON ? '<button type="button" class="ikm-btn ikm-btn-ana" id="an-yeni"><i class="fa-solid fa-square-poll-vertical"></i> Yeni anket</button>' : '') + '</div>';
            h += L.length ? '<div class="ikd-anket-liste">' + L.map(anketKart).join('') + '</div>' : bosKutu('fa-square-poll-vertical', aF.ara ? 'Eşleşen anket yok' : 'Henüz anket yok', YON && !aF.ara ? 'Tek seçimli, çoklu seçimli, puanlı ya da açık uçlu sorularla anket hazırlayıp seçtiğiniz kişilere gönderin.' : 'Size gönderilen anketler burada görünecek.');
            icerik.innerHTML = h;
            var ara = $('#an-ara'); ara.oninput = function () { aF.ara = ara.value; var p = ara.selectionStart; anketListe(); var y = $('#an-ara'); y.focus(); try { y.setSelectionRange(p, p); } catch (e) {} };
            $$('.ikd-anket', icerik).forEach(function (k) { k.onclick = function () { anketAc(+k.dataset.id); }; });
            var yb = $('#an-yeni'); if (yb) yb.onclick = function () { anketForm(null); };
        }
        function bitisYaz(iso) { return iso ? tamTarih(iso) : ''; }
        function anketKart(x) {
            var d = x.durum, bekleyen = d === 'acik' && !x.katildi;
            var durumChip = d === 'acik' ? '<span class="ikm-cip yesil"><i class="fa-solid fa-circle"></i> Açık</span>' : d === 'taslak' ? '<span class="ikm-cip turuncu">Taslak</span>' : '<span class="ikm-cip gri"><i class="fa-solid fa-lock"></i> Kapandı</span>';
            var ilr = YON && x.alici ? '<span class="ikd-ilerleme" style="margin-left:0"><i><u style="width:' + Math.min(100, Math.round(x.katilan * 100 / x.alici)) + '%"></u></i><small>' + x.katilan + '/' + x.alici + ' katıldı</small></span>' : '';
            return '<article class="ikd-anket' + (d === 'kapali' ? ' kapali' : '') + '" tabindex="0" role="button" data-id="' + x.id + '"><span class="ikd-anket-ik"><i class="fa-solid fa-square-poll-vertical"></i></span><div class="ikd-anket-g"><h3>' + esc(x.baslik) + '</h3>' + (x.aciklama ? '<p>' + esc(x.aciklama) + '</p>' : '') +
                '<div class="ikd-anket-meta">' + durumChip + (x.katildi ? '<span class="ikm-cip mavi"><i class="fa-solid fa-check"></i> Katıldınız</span>' : bekleyen ? '<span class="ikm-cip kirmizi">Yanıtınız bekleniyor</span>' : '') + '<span class="ikm-cip gri">' + x.soru_say + ' soru</span>' + (x.anonim ? '<span class="ikm-cip mor"><i class="fa-solid fa-user-secret"></i> Anonim</span>' : '') +
                (x.bitis && d === 'acik' ? '<span class="ikm-cip gri"><i class="fa-regular fa-clock"></i> ' + esc(bitisYaz(x.bitis)) + '</span>' : '') + '</div>' + ilr + '</div></article>';
        }

        /* ---------- anket penceresi: Katıl / Sonuçlar / Katılım ---------- */
        function anketAc(id) {
            var m = modal({ baslik: 'Anket', ikon: 'fa-square-poll-vertical', gen: 820, govde: M.yukleniyor(), alt: '<span></span>' });
            function yukle(sek) {
                getir({ islem: 'anket', id: id }).then(function (j) { ciz(j.anket, sek); }).catch(function (e) { $('.ikm-modal-govde', m.el).innerHTML = M.hataKutu(e.message); });
            }
            function ciz(a, sek) {
                var govde = $('.ikm-modal-govde', m.el), alt = $('.ikm-modal-alt', m.el);
                if (!alt) { alt = document.createElement('div'); alt.className = 'ikm-modal-alt'; $('.ikm-modal', m.el).appendChild(alt); }
                var yapan = a.hedef_tip !== undefined;
                var sekmeler = []; if (a.yanitlayabilir) sekmeler.push(['katil', 'fa-pen-to-square', 'Katıl']); if (a.sonuc_gorunur) sekmeler.push(['sonuc', 'fa-chart-simple', 'Sonuçlar']); if (yapan) sekmeler.push(['katilim', 'fa-user-check', 'Katılım']);
                if (!sek || !sekmeler.some(function (s) { return s[0] === sek; })) sek = sekmeler.length ? sekmeler[0][0] : 'bilgi';
                var durum = a.durum === 'acik' ? '<span class="ikm-cip yesil"><i class="fa-solid fa-circle"></i> Açık</span>' : a.durum === 'taslak' ? '<span class="ikm-cip turuncu">Taslak</span>' : '<span class="ikm-cip gri"><i class="fa-solid fa-lock"></i> Kapandı</span>';
                var h = '<div class="ikd-det"><div class="ikd-det-ust">' + durum + (a.anonim ? '<span class="ikm-cip mor"><i class="fa-solid fa-user-secret"></i> Anonim</span>' : '') + (a.bitis && a.durum === 'acik' ? '<span class="ikm-cip gri"><i class="fa-regular fa-clock"></i> Son: ' + esc(bitisYaz(a.bitis)) + '</span>' : '') +
                    '<span class="ikm-cip gri"><i class="fa-solid fa-users"></i> ' + esc(a.hedef) + '</span></div><h2>' + esc(a.baslik) + '</h2>' + (a.aciklama ? '<div class="ikd-metin">' + metin(a.aciklama) + '</div>' : '');
                if (sekmeler.length > 1) h += '<div class="ikd-sekme">' + sekmeler.map(function (s) { return '<button type="button" data-sk="' + s[0] + '" class="' + (sek === s[0] ? 'aktif' : '') + '"><i class="fa-solid ' + s[1] + '"></i> ' + s[2] + '</button>'; }).join('') + '</div>'; else h += '<div style="height:14px"></div>';
                if (sek === 'katil') h += katilFormu(a);
                else if (sek === 'sonuc') h += sonucHtml(a);
                else if (sek === 'katilim') h += katilimHtml(a);
                else h += '<div class="ikd-onay-kutu tamam"><i class="fa-solid fa-circle-check"></i> ' + (a.katildi ? 'Bu ankete katıldınız, teşekkürler!' : a.durum !== 'acik' ? 'Bu anket kapandı.' : 'Bu anketi yanıtlama yetkiniz yok.') + '</div>' + (a.katildi && !a.sonuc_gorunur ? '<p class="ikd-yaz-soluk" style="margin-top:10px">Sonuçlar yöneticiler tarafından paylaşıldığında burada görünecek.</p>' : '');
                govde.innerHTML = h + '</div>';
                $$('[data-sk]', govde).forEach(function (b) { b.onclick = function () { ciz(a, b.dataset.sk); }; });
                // alt düğmeler
                var dugmeler = '';
                if (YON) dugmeler += '<button type="button" class="ikm-btn ikm-btn-kucuk" id="an-duz"><i class="fa-solid fa-pen"></i> Düzenle</button>' + (a.durum === 'acik' ? '<button type="button" class="ikm-btn ikm-btn-kucuk" id="an-kapat"><i class="fa-solid fa-lock"></i> Anketi kapat</button>' : a.durum === 'kapali' ? '<button type="button" class="ikm-btn ikm-btn-kucuk" id="an-ac"><i class="fa-solid fa-lock-open"></i> Yeniden aç</button>' : '') +
                    (yapan && a.katilan ? '<button type="button" class="ikm-btn ikm-btn-kucuk" id="an-csv"><i class="fa-solid fa-file-csv"></i> Excel (CSV)</button>' : '') + '<button type="button" class="ikm-btn ikm-btn-kucuk ikm-btn-tehlike" id="an-sil"><i class="fa-solid fa-trash"></i> Sil</button>';
                dugmeler += '<span class="ikd-bosluk"></span>' + (sek === 'katil' ? '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button><button type="button" class="ikm-btn ikm-btn-ana" id="an-gonder"><i class="fa-solid fa-paper-plane"></i> Yanıtı gönder</button>' : '<button type="button" class="ikm-btn ikm-btn-ana" data-kapat>Kapat</button>');
                alt.innerHTML = dugmeler; $$('[data-kapat]', alt).forEach(function (b) { b.onclick = m.kapat; });
                var gb = $('#an-gonder', alt); if (gb) gb.onclick = function () { gonderCevap(a, gb); };
                if ($('#an-duz', alt)) $('#an-duz', alt).onclick = function () { m.kapat(); anketForm(a); };
                var dur = function (d, ms) { gonder('anket_durum', { id: a.id, durum: d }).then(function () { bildir(ms, 'basari'); anketCiz(); yukle(sek); }).catch(function (e) { bildir(e.message, 'hata'); }); };
                if ($('#an-kapat', alt)) $('#an-kapat', alt).onclick = function () { onayla({ baslik: 'Anket kapatılsın mı?', mesaj: 'Kapandıktan sonra yeni yanıt alınmaz; sonuçlar görünmeye devam eder.', evet: 'Kapat', hayir: 'Vazgeç', tur: 'uyari' }).then(function (ok) { if (ok) dur('kapali', 'Anket kapatıldı.'); }); };
                if ($('#an-ac', alt)) $('#an-ac', alt).onclick = function () { dur('acik', 'Anket yeniden açıldı.'); };
                if ($('#an-sil', alt)) $('#an-sil', alt).onclick = function () { onayla({ baslik: 'Anket silinsin mi?', mesaj: 'Anket ve tüm yanıtlar kalıcı olarak silinir.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) gonder('anket_sil', { id: a.id }).then(function () { m.kapat(); bildir('Anket silindi.', 'basari'); anketCiz(); rozetleriYenile(); }).catch(function (e) { bildir(e.message, 'hata'); }); }); };
                if ($('#an-csv', alt)) $('#an-csv', alt).onclick = function () { getir({ islem: 'anket_disa', id: a.id }).then(function (j) { csvIndir('anket_' + j.ad.replace(/[^\wığüşöçİĞÜŞÖÇ]+/g, '_').slice(0, 40) + '.csv', [j.baslik].concat(j.satirlar)); }).catch(function (e) { bildir(e.message, 'hata'); }); };
                bagla(a, govde);
            }
            function katilFormu(a) {
                return '<form id="an-form" onsubmit="return false">' + a.sorular.map(function (s, i) {
                    var g = '<div class="ikd-soru-blok" data-q="' + s.id + '" data-tip="' + s.tip + '"><div class="ikd-soru-baslik"><span>' + (i + 1) + '</span><div>' + esc(s.soru) + (s.zorunlu ? ' <b>*</b>' : ' <small class="ikd-yaz-soluk">(isteğe bağlı)</small>') + '</div></div>';
                    if (s.tip === 'tek' || s.tip === 'coklu') g += '<div class="ikd-secenekler">' + s.secenekler.map(function (o, k) { return '<label class="ikd-secenek-kart"><input type="' + (s.tip === 'tek' ? 'radio' : 'checkbox') + '" name="q' + s.id + '" value="' + k + '"><span>' + esc(o) + '</span></label>'; }).join('') + '</div>' + (s.tip === 'coklu' ? '<small class="ikd-yaz-soluk">Birden fazla seçebilirsiniz.</small>' : '');
                    else if (s.tip === 'puan') g += '<div class="ikd-yildiz" data-puan>' + [1, 2, 3, 4, 5].map(function (p) { return '<button type="button" data-p="' + p + '" aria-label="' + p + ' puan">' + p + '</button>'; }).join('') + '</div><div class="ikd-yildiz-etiket"><span>Çok kötü</span><span>Mükemmel</span></div>';
                    else g += '<textarea name="q' + s.id + '" maxlength="2000" placeholder="Cevabınızı yazın…"></textarea>';
                    return g + '</div>';
                }).join('') + (a.anonim ? '<div class="ikd-onay-kutu tamam" style="margin-top:14px"><i class="fa-solid fa-user-secret"></i> Bu anket anonimdir; cevaplarınız adınızla eşleştirilmez.</div>' : '') + '</form>';
            }
            function bagla(a, govde) {
                $$('[data-puan]', govde).forEach(function (kap) { kap.onclick = function (e) { var b = e.target.closest('[data-p]'); if (!b) return; var p = +b.dataset.p; kap.dataset.deger = p; $$('[data-p]', kap).forEach(function (x) { x.classList.toggle('acik', +x.dataset.p <= p); }); }; });
            }
            function gonderCevap(a, dugme) {
                var cv = {}, eksik = null;
                $$('.ikd-soru-blok', m.el).forEach(function (b) {
                    var sid = b.dataset.q, tip = b.dataset.tip, s = a.sorular.filter(function (x) { return String(x.id) === sid; })[0], v = null;
                    if (tip === 'tek') { var r = $('input:checked', b); v = r ? +r.value : null; }
                    else if (tip === 'coklu') { v = $$('input:checked', b).map(function (x) { return +x.value; }); if (!v.length) v = null; }
                    else if (tip === 'puan') { var pk = $('[data-puan]', b); v = pk && pk.dataset.deger ? +pk.dataset.deger : null; }
                    else v = $('textarea', b).value.trim() || null;
                    if (v === null && s.zorunlu && !eksik) eksik = b;
                    if (v !== null) cv[sid] = v;
                });
                if (eksik) { eksik.scrollIntoView({ behavior: 'smooth', block: 'center' }); eksik.style.borderColor = 'var(--ik-kirmizi)'; bildir('Lütfen zorunlu (*) soruları cevaplayın.', 'uyari'); return; }
                if (!Object.keys(cv).length) { bildir('En az bir soruyu cevaplayın.', 'uyari'); return; }
                dugme.disabled = true; dugme.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gönderiliyor…';
                gonder('anket_cevapla', { id: a.id, cevaplar: JSON.stringify(cv) }).then(function () { bildir('Yanıtınız kaydedildi, teşekkürler!', 'basari'); anketCiz(); rozetleriYenile(); yukle('sonuc'); })
                    .catch(function (e) { dugme.disabled = false; dugme.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Yanıtı gönder'; bildir(e.message, 'hata'); });
            }
            function sonucHtml(a) {
                var top = a.katilan, h = '<div class="ikd-ozet-kutular"><div class="ikd-ozet-kutu"><b>' + top + '</b><small>katılımcı</small></div>' + (a.alici ? '<div class="ikd-ozet-kutu"><b>%' + Math.round(top * 100 / Math.max(1, a.alici)) + '</b><small>katılım oranı</small></div>' : '') + '<div class="ikd-ozet-kutu"><b>' + a.soru_say + '</b><small>soru</small></div></div>';
                if (!top) return h + bosKutu('fa-chart-simple', 'Henüz yanıt yok', 'Yanıtlar geldikçe sonuçlar burada canlı görünür.');
                a.sorular.forEach(function (s, i) {
                    var r = (a.sonuclar || []).filter(function (x) { return x.id === s.id; })[0] || { say: 0 };
                    h += '<div class="ikd-soru-blok"><div class="ikd-soru-baslik"><span>' + (i + 1) + '</span><div>' + esc(s.soru) + ' <small class="ikd-yaz-soluk">· ' + r.say + ' yanıt</small></div></div>';
                    if (s.tip === 'tek' || s.tip === 'coklu') {
                        var en = Math.max.apply(null, r.sayilar.concat([0]));
                        h += '<div class="ikd-secenekler">' + s.secenekler.map(function (o, k) { var n = r.sayilar[k] || 0, p = r.say ? Math.round(n * 100 / r.say) : 0; return '<div class="ikd-cubuk' + (n === en && n > 0 ? ' enyuksek' : '') + '"><span>' + esc(o) + '</span><i><u style="width:' + p + '%"></u></i><b>' + n + ' <small>%' + p + '</small></b></div>'; }).join('') + '</div>';
                    } else if (s.tip === 'puan') {
                        var en2 = Math.max.apply(null, r.sayilar.concat([0]));
                        h += '<div class="ikd-ortalama"><strong>' + (r.ortalama != null ? String(r.ortalama).replace('.', ',') : '—') + '</strong><small>/ 5 ortalama puan</small></div><div class="ikd-secenekler">' + [5, 4, 3, 2, 1].map(function (p) { var n = r.sayilar[p - 1] || 0, y = r.say ? Math.round(n * 100 / r.say) : 0; return '<div class="ikd-cubuk' + (n === en2 && n > 0 ? ' enyuksek' : '') + '"><span>' + p + ' puan</span><i><u style="width:' + y + '%"></u></i><b>' + n + ' <small>%' + y + '</small></b></div>'; }).join('') + '</div>';
                    } else if (r.metinler) h += r.metinler.length ? '<div class="ikd-yorumlar">' + r.metinler.map(function (y) { return '<div class="ikd-yorum">' + metin(y.deger) + (y.ad ? '<small>— ' + esc(y.ad) + '</small>' : '') + '</div>'; }).join('') + '</div>' : '<div class="ikd-yaz-soluk">Yazılı cevap yok.</div>';
                    else h += '<div class="ikd-yaz-soluk"><i class="fa-solid fa-lock"></i> Yazılı cevapları yalnızca yöneticiler görür.</div>';
                    h += '</div>';
                });
                return h;
            }
            function katilimHtml(a) {
                var satir = function (k, tur) { return '<div class="ikd-kisi"><b>' + esc(k.ad) + '</b><small>' + esc(k.birim) + (tur === 'k' ? ' · ' + esc(gorece(k.zaman)) : '') + '</small></div>'; };
                return '<div class="ikd-ozet-kutular"><div class="ikd-ozet-kutu"><b>' + a.katilanlar.length + '</b><small>katıldı</small></div><div class="ikd-ozet-kutu"><b>' + a.katilmayanlar.length + '</b><small>katılmadı</small></div><div class="ikd-ozet-kutu"><b>' + a.alici + '</b><small>hedef kişi</small></div></div>' +
                    '<div class="ikd-baslik-kucuk" style="margin-top:4px">Katılmayanlar</div><div class="ikd-kisiler">' + (a.katilmayanlar.map(function (k) { return satir(k, 'm'); }).join('') || '<div class="ikd-yaz-soluk" style="padding:8px">Herkes katıldı 🎉</div>') + '</div>' +
                    '<div class="ikd-baslik-kucuk">Katılanlar' + (a.anonim ? ' <small>(anket anonim: yalnızca katılım görünür, cevaplar eşleşmez)</small>' : '') + '</div><div class="ikd-kisiler">' + (a.katilanlar.map(function (k) { return satir(k, 'k'); }).join('') || '<div class="ikd-yaz-soluk" style="padding:8px">Henüz katılan yok.</div>') + '</div>';
            }
            yukle();
        }

        /* ---------- anket oluştur / düzenle ---------- */
        var SORU_TIP = { tek: ['Tek seçim', 'fa-circle-dot'], coklu: ['Çoklu seçim', 'fa-square-check'], puan: ['Puan (1–5)', 'fa-star'], metin: ['Yazılı cevap', 'fa-align-left'] };
        function anketForm(a0) {
            var yeni = !a0, a = a0 || {};
            var q = (a.sorular || []).map(function (s) { return { tip: s.tip, soru: s.soru, secenekler: s.secenekler.slice(), zorunlu: s.zorunlu }; });
            if (!q.length) q.push({ tip: 'tek', soru: '', secenekler: ['', ''], zorunlu: true });
            var f = { hedef: yeniHedef(a0), kanal: { site: true, mail: false, sms: false }, sg: a.sonuc_goster || 'katilan' };
            var kilit = !!a.cevap_var;
            var m = modal({ baslik: yeni ? 'Yeni anket' : 'Anketi düzenle', ikon: 'fa-square-poll-vertical', gen: 860, sabit: true, govde: M.yukleniyor(), alt: '<span></span>' });
            secYukle().then(function () {
                var govde = $('.ikm-modal-govde', m.el);
                var bitisDeger = a.bitis ? (function (d) { d = new Date(d); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':' + pad(d.getMinutes()); })(a.bitis) : '';
                govde.innerHTML = '<form class="ikm-form" id="an-f" autocomplete="off" onsubmit="return false">' +
                    '<label class="ikm-alan"><span>Anket başlığı <b>*</b></span><input name="baslik" maxlength="200" value="' + esc(a.baslik || '') + '" placeholder="Örn. Yemekhane memnuniyet anketi"></label>' +
                    '<label class="ikm-alan"><span>Açıklama <small>(isteğe bağlı)</small></span><textarea name="aciklama" rows="2" maxlength="3000" placeholder="Katılımcılara kısa bilgi…">' + esc(a.aciklama || '') + '</textarea></label>' +
                    (kilit ? '<div class="ikd-kilit-uyari"><i class="fa-solid fa-lock"></i><div><b>Sorular kilitli.</b> Bu ankete ' + a.katilan + ' kişi yanıt verdi; sonuçlar bozulmasın diye sorular ve anonimlik değiştirilemez. Başlık, süre, hedef kitle ve sonuç görünürlüğü düzenlenebilir.</div></div>' : '') +
                    '<div id="an-sorular" class="' + (kilit ? 'ikd-kilitli' : '') + '"></div>' +
                    (kilit ? '' : '<div><button type="button" class="ikm-btn ikm-btn-kucuk" id="an-soru-ekle"><i class="fa-solid fa-plus"></i> Soru ekle</button></div>') +
                    '<div class="ikd-izgara2"><div class="ikd-form-bolum"><div class="ikd-baslik-kucuk">Gizlilik</div><label class="ikm-ozel-satir' + (kilit ? ' ikd-kilitli' : '') + '"><input type="checkbox" name="anonim"' + (a.anonim ? ' checked' : '') + (kilit ? ' disabled' : '') + '><i></i><span>Anonim anket <small>(cevaplar kimlikle eşleşmez)</small></span></label></div>' +
                    '<label class="ikm-alan"><span>Son katılım zamanı <small>(boşsa elle kapatılır)</small></span><input type="datetime-local" name="bitis" value="' + bitisDeger + '"></label></div>' +
                    '<div class="ikm-alan"><span>Sonuçları kimler görsün?</span><div class="ikm-segment" id="an-sg"><button type="button" data-sg="yonetici">Yalnızca yöneticiler</button><button type="button" data-sg="katilan">Katılanlar</button><button type="button" data-sg="herkes">Hedefteki herkes</button></div></div>' +
                    '<div class="ikm-alan"><span>Kimler katılsın?</span><div id="an-hedef"></div></div>' +
                    '<div class="ikd-form-bolum"><div class="ikd-baslik-kucuk">' + (a.durum === 'acik' ? 'Alıcılara yeniden haber ver' : 'Yayınlayınca nereden haber verilsin?') + '</div>' + (a.durum === 'acik' ? toggle('yeniden', false, 'Anketi alıcılara tekrar bildir') : '') + '<div id="an-kanal"></div></div></form>';
                var form = $('#an-f', m.el);
                hedefSecici($('#an-hedef', m.el), f.hedef); kanalSecici($('#an-kanal', m.el), f.kanal);
                var sg = $('#an-sg', m.el); function sgGunc() { $$('[data-sg]', sg).forEach(function (b) { b.classList.toggle('aktif', b.dataset.sg === f.sg); }); } sgGunc();
                sg.onclick = function (e) { var b = e.target.closest('[data-sg]'); if (b) { f.sg = b.dataset.sg; sgGunc(); } };
                var kap = $('#an-sorular', m.el);
                function sorular() {
                    kap.innerHTML = q.map(function (s, i) {
                        var secenek = '';
                        if (s.tip === 'tek' || s.tip === 'coklu') secenek = '<div class="ikd-secenekler">' + s.secenekler.map(function (o, k) { return '<div class="ikd-secenek-satir"><i class="fa-regular ' + (s.tip === 'tek' ? 'fa-circle' : 'fa-square') + '"></i><input type="text" data-o="' + i + ':' + k + '" maxlength="200" value="' + esc(o) + '" placeholder="Seçenek ' + (k + 1) + '"><button type="button" class="ikd-ikon-btn" data-os="' + i + ':' + k + '" title="Seçeneği sil"' + (s.secenekler.length <= 2 ? ' disabled' : '') + '><i class="fa-solid fa-xmark"></i></button></div>'; }).join('') + '<div><button type="button" class="ikm-btn ikm-btn-kucuk" data-oe="' + i + '"><i class="fa-solid fa-plus"></i> Seçenek ekle</button></div></div>';
                        else if (s.tip === 'puan') secenek = '<div class="ikd-yaz-soluk"><i class="fa-solid fa-star" style="color:#f59e0b"></i> Katılımcılar 1 (çok kötü) ile 5 (mükemmel) arasında puan verir.</div>';
                        else secenek = '<div class="ikd-yaz-soluk"><i class="fa-solid fa-align-left"></i> Katılımcılar serbest metinle cevap yazar. Yazılı cevapları yalnızca yöneticiler görür.</div>';
                        return '<div class="ikd-soru" data-i="' + i + '" style="margin-bottom:12px"><div class="ikd-soru-ust"><span class="ikd-soru-no">' + (i + 1) + '</span><input type="text" data-s="' + i + '" maxlength="500" value="' + esc(s.soru) + '" placeholder="Soru metni">' +
                            '<select data-t="' + i + '">' + Object.keys(SORU_TIP).map(function (k) { return '<option value="' + k + '"' + (s.tip === k ? ' selected' : '') + '>' + SORU_TIP[k][0] + '</option>'; }).join('') + '</select>' +
                            '<button type="button" class="ikd-ikon-btn" data-yukari="' + i + '" title="Yukarı"' + (i === 0 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-up"></i></button><button type="button" class="ikd-ikon-btn" data-asagi="' + i + '" title="Aşağı"' + (i === q.length - 1 ? ' disabled' : '') + '><i class="fa-solid fa-arrow-down"></i></button>' +
                            '<button type="button" class="ikd-ikon-btn tehlike" data-ss="' + i + '" title="Soruyu sil"' + (q.length <= 1 ? ' disabled' : '') + '><i class="fa-solid fa-trash"></i></button></div>' + secenek +
                            '<div class="ikd-soru-alt"><label class="ikm-ozel-satir" style="padding:0"><input type="checkbox" data-z="' + i + '"' + (s.zorunlu ? ' checked' : '') + '><i></i><span>Zorunlu soru</span></label></div></div>';
                    }).join('');
                }
                sorular();
                kap.oninput = function (e) { var t = e.target, x; if (t.dataset.s !== undefined) q[+t.dataset.s].soru = t.value; else if (t.dataset.o !== undefined) { x = t.dataset.o.split(':'); q[+x[0]].secenekler[+x[1]] = t.value; } };
                kap.onchange = function (e) {
                    var t = e.target;
                    if (t.dataset.t !== undefined) { var s = q[+t.dataset.t]; s.tip = t.value; if ((s.tip === 'tek' || s.tip === 'coklu') && s.secenekler.length < 2) s.secenekler = ['', '']; if (s.tip === 'metin') s.zorunlu = false; sorular(); }
                    else if (t.dataset.z !== undefined) q[+t.dataset.z].zorunlu = t.checked;
                };
                kap.onclick = function (e) {
                    var b, x;
                    if ((b = e.target.closest('[data-oe]'))) { var s = q[+b.dataset.oe]; if (s.secenekler.length >= 20) { bildir('En fazla 20 seçenek.', 'uyari'); return; } s.secenekler.push(''); sorular(); var l = $$('[data-o^="' + b.dataset.oe + ':"]', kap); if (l.length) l[l.length - 1].focus(); return; }
                    if ((b = e.target.closest('[data-os]'))) { x = b.dataset.os.split(':'); q[+x[0]].secenekler.splice(+x[1], 1); sorular(); return; }
                    if ((b = e.target.closest('[data-ss]'))) { q.splice(+b.dataset.ss, 1); sorular(); return; }
                    if ((b = e.target.closest('[data-yukari]'))) { var i = +b.dataset.yukari; q.splice(i - 1, 0, q.splice(i, 1)[0]); sorular(); return; }
                    if ((b = e.target.closest('[data-asagi]'))) { var j = +b.dataset.asagi; q.splice(j + 1, 0, q.splice(j, 1)[0]); sorular(); }
                };
                var ek = $('#an-soru-ekle', m.el); if (ek) ek.onclick = function () { if (q.length >= 50) { bildir('En fazla 50 soru.', 'uyari'); return; } q.push({ tip: 'tek', soru: '', secenekler: ['', ''], zorunlu: true }); sorular(); var inp = $$('[data-s]', kap); inp[inp.length - 1].focus(); };
                var alt = $('.ikm-modal-alt', m.el) || (function () { var d = document.createElement('div'); d.className = 'ikm-modal-alt'; $('.ikm-modal', m.el).appendChild(d); return d; })();
                var acik = a.durum === 'acik' || a.durum === 'kapali';
                alt.innerHTML = '<button type="button" class="ikm-btn" data-kapat>Vazgeç</button>' + (!acik ? '<button type="button" class="ikm-btn" id="an-taslak"><i class="fa-regular fa-floppy-disk"></i> Taslak kaydet</button>' : '') + '<button type="button" class="ikm-btn ikm-btn-ana" id="an-yayin"><i class="fa-solid fa-paper-plane"></i> ' + (acik ? 'Kaydet' : 'Yayınla') + '</button>';
                $$('[data-kapat]', alt).forEach(function (b) { b.onclick = m.kapat; });
                function kaydet(durum, dugme) {
                    var baslik = form.elements.baslik.value.trim();
                    if (!baslik) { form.elements.baslik.classList.add('hatali'); form.elements.baslik.focus(); bildir('Anket başlığı yazın.', 'uyari'); return; }
                    if (!kilit) {
                        var bosSoru = q.filter(function (s) { return !s.soru.trim(); });
                        if (durum === 'acik' && bosSoru.length) { bildir('Boş soru var: metin yazın ya da soruyu silin.', 'uyari'); return; }
                        for (var i = 0; i < q.length; i++) if (q[i].soru.trim() && (q[i].tip === 'tek' || q[i].tip === 'coklu') && q[i].secenekler.filter(function (o) { return o.trim(); }).length < 2) { bildir((i + 1) + '. soru için en az 2 seçenek girin.', 'uyari'); return; }
                    }
                    if (f.hedef.tip === 'secili' && !f.hedef.birimler.length && !f.hedef.kisiler.length) { bildir('Birim ya da kişi seçin veya “Herkes”i işaretleyin.', 'uyari'); return; }
                    var fd = new FormData(); fd.append('islem', 'anket_kaydet'); if (a0) fd.append('id', a0.id);
                    fd.append('baslik', baslik); fd.append('aciklama', form.elements.aciklama.value.trim()); fd.append('durum', durum); fd.append('sonuc_goster', f.sg);
                    if (form.elements.anonim.checked) fd.append('anonim', '1'); fd.append('bitis', form.elements.bitis.value);
                    if (form.elements.yeniden && form.elements.yeniden.checked) fd.append('yeniden_bildir', '1');
                    fd.append('sorular', JSON.stringify(q.filter(function (s) { return s.soru.trim(); }).map(function (s) { return { tip: s.tip, soru: s.soru.trim(), secenekler: s.secenekler.filter(function (o) { return o.trim(); }), zorunlu: s.zorunlu ? 1 : 0 }; })));
                    hedefAlani(fd, f.hedef); kanalAlani(fd, f.kanal);
                    var yuk = dugme.innerHTML; $$('button', alt).forEach(function (b) { b.disabled = true; }); dugme.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Gönderiliyor…';
                    xhrForm(fd).then(function (j) {
                        m.kapat(); bildir(durum === 'taslak' ? 'Taslak kaydedildi.' : (j.bildirilen ? 'Anket yayınlandı, ' + j.bildirilen + ' kişiye haber verildi.' : 'Anket kaydedildi.'), 'basari'); anketCiz(); rozetleriYenile();
                    }).catch(function (e) { $$('button', alt).forEach(function (b) { b.disabled = false; }); dugme.innerHTML = yuk; bildir(e.message, 'hata'); });
                }
                $('#an-yayin', alt).onclick = function () { kaydet('acik', this); };
                var tb = $('#an-taslak', alt); if (tb) tb.onclick = function () { kaydet('taslak', this); };
            }).catch(function (e) { $('.ikm-modal-govde', m.el).innerHTML = M.hataKutu(e.message); });
        }

        /* ======================= kayıt ======================= */
        var aktifGorunum = '';
        M.kayit('duyurular', 'Duyurular', 'Kurum duyuruları — önemli haberler, bilgilendirmeler ve etkinlikler.', function (o) { aktifGorunum = 'duyurular'; icerik.onclick = null; duyuruCiz(o); });
        M.kayit('dosyalar', 'Dosyalar', 'Paylaşılan belgeler, formlar ve sunumlar.', function (o) { aktifGorunum = 'dosyalar'; icerik.onclick = null; dosyaCiz(o); });
        M.kayit('anketler', 'Anketler', 'Anket oluşturun, katılın ve sonuçları canlı izleyin.', function (o) { aktifGorunum = 'anketler'; icerik.onclick = null; anketCiz(o); });
        rozetleriYenile();
    }
    basla();
})();
