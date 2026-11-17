/* ==========================================================================
   tb-giris.js — Personel Giriş-Çıkış
   • Kaydırarak giriş / çıkış (yanlışlıkla cepte açılmasın) → PIN + (gerekirse) ofis kodu + konum → sunucu doğrular
   • Cihaz kimliği: bu tarayıcıya özel rastgele anahtar (localStorage); İK onayı ile "güvenilir cihaz" olur
   • Parmak izi / Face ID (WebAuthn): isteğe bağlı; biyometrik veri cihazdan hiç çıkmaz, yalnızca imza sunucuya gelir
   • İK sekmeleri: Şu an · Kayıtlar · Cihazlar · Noktalar · Ayarlar
   ========================================================================== */
(function () {
    'use strict';
    var API = 'giris_islem.php', GC = window.GC || {}, YT = GC.yetki || {};
    var $ = function (s, k) { return (k || document).querySelector(s); };
    var $$ = function (s, k) { return Array.prototype.slice.call((k || document).querySelectorAll(s)); };
    var icerik = $('#gc-icerik'), kioskOto = false;

    /* ---------------- Yeşil ekran görünümü: yan menüyü ve sayfa başlığının üstündeki çubuğu (menü, tema, zil) kaldır ---------------- */
    document.documentElement.classList.add('gc-yesil');
    (function ustuGizle() {
        function tara() {
            var sayfa = $('.gc-sayfa') || icerik; if (!sayfa) return;
            ['vuslat-sidebar-menu', 'vuslat-sidebar-overlay', 'vuslat-yedek-ust-cubuk'].forEach(function (id) { var e = document.getElementById(id); if (e) e.style.display = 'none'; });
            // sayfa içeriğinin üstünde kalan çubuk(lar): zil / tema / hamburger içerenler ya da header / nav
            var d = sayfa;
            while (d && d !== document.body) {
                var o = d.previousElementSibling;
                while (o) {
                    var t = o.tagName;
                    if (t !== 'SCRIPT' && t !== 'STYLE' && t !== 'LINK' && o.id !== 'vuslat-sohbet-root' &&
                        (t === 'HEADER' || t === 'NAV' || o.querySelector('#bildirim-toggle-btn, #vuslat-tema-btn, .fa-bars, [id*="sidebar"], [onclick*="idebar"]'))) o.classList.add('gc-gizle-ust');
                    o = o.previousElementSibling;
                }
                d = d.parentElement;
            }
        }
        tara(); document.addEventListener('DOMContentLoaded', tara); setTimeout(tara, 600); setTimeout(tara, 1800);
    })();
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function csrf() { return (window.TB && window.TB.csrf) || ''; }
    function bildir(m, t) { if (window.tbBildir) window.tbBildir(m, t || 'bilgi'); else alert(m); }
    function onayla(o) { return window.tbOnayla ? window.tbOnayla(o) : Promise.resolve(confirm(o.mesaj || o.baslik)); }
    function cevapOku(r) { return r.json().catch(function () { return { ok: false, hata: 'Sunucu yanıtı okunamadı (' + r.status + ').' }; }).then(function (j) { if (!j.ok) { var e = new Error(j.hata || 'İşlem başarısız.'); e.veri = j; throw e; } return j; }); }
    function getir(p) { return fetch(API + '?' + new URLSearchParams(p).toString(), { credentials: 'same-origin', cache: 'no-store' }).then(cevapOku); }
    function gonder(islem, v) {
        var fd = new FormData(); fd.append('islem', islem); fd.append('_csrf', csrf());
        Object.keys(v || {}).forEach(function (k) { if (v[k] !== undefined && v[k] !== null) fd.append(k, v[k]); });
        return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf() } }).then(cevapOku);
    }
    function p2(n) { return (n < 10 ? '0' : '') + n; }
    function sureYaz(sn) { sn = Math.max(0, Math.floor(sn)); var s = Math.floor(sn / 3600), d = Math.floor((sn % 3600) / 60); return (s ? s + 's ' : '') + d + 'd'; }
    function saatYaz(sn) { sn = Math.max(0, Math.floor(sn)); return p2(Math.floor(sn / 3600)) + ':' + p2(Math.floor((sn % 3600) / 60)) + ':' + p2(sn % 60); }
    function tarihYaz(t) { var m = String(t || '').match(/^(\d{4})-(\d{2})-(\d{2}) (\d{2}):(\d{2})/); return m ? m[3] + '.' + m[2] + '.' + m[1] + ' ' + m[4] + ':' + m[5] : ''; }
    function saatKisa(t) { var m = String(t || '').match(/(\d{2}):(\d{2})/); return m ? m[1] + ':' + m[2] : ''; }

    /* Ekran kartı adı: gerçek telefonda Adreno / Mali / PowerVR görünür; bilgisayarda (tarayıcı "telefon modu"nda bile) NVIDIA / Intel / AMD / Apple M */
    var GPU = (function () {
        try {
            var c = document.createElement('canvas'), g = c.getContext('webgl') || c.getContext('experimental-webgl'); if (!g) return '';
            var e = g.getExtension('WEBGL_debug_renderer_info'), r = e ? String(g.getParameter(e.UNMASKED_RENDERER_WEBGL) || '').slice(0, 160) : '';
            try { var lc = g.getExtension('WEBGL_lose_context'); if (lc) lc.loseContext(); } catch (x2) {}
            return r;
        } catch (x) { return ''; }
    })();
    /* Android Chrome'da telefon modeli (yalnızca İK'nın cihaz listesinde gösterim için) */
    var MODEL = '';
    try { if (navigator.userAgentData && navigator.userAgentData.getHighEntropyValues) navigator.userAgentData.getHighEntropyValues(['model']).then(function (h) { MODEL = String((h && h.model) || '').slice(0, 60); }).catch(function () {}); } catch (e) {}
    /* ---------------- Cihaz ipuçları: tarayıcı "masaüstü sitesi" modunda olsa bile telefonun gerçek özellikleri ---------------- */
    function cihazIpuclari() {
        var kaba = 0; try { kaba = window.matchMedia('(pointer: coarse)').matches ? 1 : 0; } catch (e) {}
        var uad = navigator.userAgentData, tz = ''; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ''; } catch (e) {}
        return { dokunmatik: navigator.maxTouchPoints || 0, kaba: kaba, ekran: Math.min(screen.width || 0, screen.height || 0), eu: Math.max(screen.width || 0, screen.height || 0), platform: navigator.platform || '', gpu: GPU, oe: window.outerWidth || 0, sw: screen.width || 0,
            // taklit tespiti + donanım izi (tarayıcı verisinden bağımsız; gizli sekme / başka tarayıcıda da aynı)
            uad: uad ? (uad.platform || '-') : '', wd: navigator.webdriver ? 1 : 0, dpr: Math.round((window.devicePixelRatio || 1) * 100) / 100, hc: navigator.hardwareConcurrency || 0, tz: tz, model: MODEL };
    }

    /* ---------------- Cihaz kimliği (bu tarayıcıya özel, rastgele) ---------------- */
    var CIHAZ = (function () {
        var t = null; try { t = localStorage.getItem('vg_cihaz'); } catch (e) {}
        if (!t || !/^[a-f0-9]{64}$/.test(t)) {
            var a = new Uint8Array(32); (window.crypto || window.msCrypto).getRandomValues(a);
            t = Array.prototype.map.call(a, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
            try { localStorage.setItem('vg_cihaz', t); } catch (e) {}
        }
        return t;
    })();

    /* ---------------- Parmak izi / Face ID (WebAuthn) yardımcıları ---------------- */
    var WA_DESTEK = !!(GC.webauthn && window.PublicKeyCredential && navigator.credentials && navigator.credentials.create && navigator.credentials.get);
    function b64uArabellek(s) {
        s = String(s || '').replace(/-/g, '+').replace(/_/g, '/'); while (s.length % 4) s += '=';
        var bin = atob(s), a = new Uint8Array(bin.length); for (var i = 0; i < bin.length; i++) a[i] = bin.charCodeAt(i); return a.buffer;
    }
    function arabellekB64u(buf) {
        var b = new Uint8Array(buf), s = ''; for (var i = 0; i < b.length; i++) s += String.fromCharCode(b[i]);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    }
    /** Yeni cihaz kaydı (kullanıcı kendi isteğiyle veya zorunlu akışta). sonra(): başarılı olunca çağrılır. */
    function parmakIziKaydet(sonra) {
        if (!WA_DESTEK) { bildir('Bu tarayıcı/cihaz parmak izi veya Face ID doğrulamasını desteklemiyor.', 'hata'); return; }
        getir({ islem: 'webauthn_secenekler_kayit' }).then(function (o) {
            return navigator.credentials.create({ publicKey: {
                challenge: b64uArabellek(o.challenge),
                rp: { id: o.rpId, name: o.rpName },
                user: { id: b64uArabellek(o.userId), name: o.userName, displayName: o.userName },
                pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -257 }],
                authenticatorSelection: { authenticatorAttachment: 'platform', userVerification: 'required', residentKey: 'discouraged' },
                attestation: 'none', timeout: o.timeout || 60000,
                excludeCredentials: (o.excludeCredentials || []).map(function (c) { return { id: b64uArabellek(c.id), type: 'public-key' }; })
            } });
        }).then(function (cred) {
            if (!cred) throw new Error('Kayıt iptal edildi.');
            var r = cred.response;
            return gonder('webauthn_kayit', {
                id: arabellekB64u(cred.rawId), attestationObject: arabellekB64u(r.attestationObject), clientDataJSON: arabellekB64u(r.clientDataJSON),
                ad: (navigator.platform || 'Cihaz') + ' · ' + ((navigator.userAgent.match(/Edg|OPR|Chrome|Firefox|Safari/i) || [''])[0])
            });
        }).then(function () {
            bildir('Parmak izi / Face ID eklendi.', 'basari'); if (D) D.webauthn_var = true;
            if (sonra) sonra(); else kisiselCiz();
        }).catch(function (e) {
            var m = (e && e.name === 'NotAllowedError') ? 'İşlem iptal edildi ya da izin verilmedi.' : ((e && e.message) || 'Kayıt başarısız.');
            bildir(m, 'hata');
        });
    }
    /** Damga anında biyometrik doğrulama ister; sonucu JSON string olarak (veya boş) döndüren Promise verir. */
    var waOn = null; // damga başlarken (konum beklenirken) önceden istenen WebAuthn seçenekleri: biyometrik pencere beklemeden açılır
    function parmakIziDogrula() {
        var on = waOn; waOn = null;
        return (on || getir({ islem: 'webauthn_secenekler_giris' })).then(function (o) {
            return navigator.credentials.get({ publicKey: {
                challenge: b64uArabellek(o.challenge), rpId: o.rpId, userVerification: o.userVerification || 'required', timeout: o.timeout || 30000,
                allowCredentials: (o.allowCredentials || []).map(function (c) { return { id: b64uArabellek(c.id), type: 'public-key', transports: c.transports }; })
            } });
        }).then(function (assertion) {
            var r = assertion.response;
            return JSON.stringify({ id: arabellekB64u(assertion.rawId), response: {
                authenticatorData: arabellekB64u(r.authenticatorData), clientDataJSON: arabellekB64u(r.clientDataJSON), signature: arabellekB64u(r.signature)
            } });
        }).catch(function (e) {
            throw new Error(e && e.name === 'NotAllowedError' ? 'Parmak izi / Face ID doğrulaması iptal edildi ya da izin verilmedi.' : ((e && e.message) || 'Parmak izi / Face ID doğrulaması başarısız.'));
        });
    }
    /** Ayarlarda zorunlu ama kullanıcının henüz kaydı yoksa: önce kaydettirir, sonra devam eder. */
    function parmakIziKaydetZorunlu(sonra) {
        bildir('Devam etmeden önce parmak izinizi / Face ID\'nizi kaydedin.', 'bilgi');
        parmakIziKaydet(sonra);
    }
    /** Kayıtlı cihazları listeleyip silme imkânı veren pencere */
    function waYonetPenceresi() {
        var m = modal('<i class="fa-solid fa-fingerprint"></i> Parmak izi / Face ID', '<div class="gc-bos">Yükleniyor…</div>',
            '<button type="button" class="gc-btn" data-k>Kapat</button><button type="button" class="gc-btn gc-btn-ana" id="wa-ekle"><i class="fa-solid fa-plus"></i> Yeni cihaz ekle</button>', 420);
        $('[data-k]', m.el).onclick = m.kapat;
        $('#wa-ekle', m.el).onclick = function () { m.kapat(); parmakIziKaydet(); };
        function yukle() {
            getir({ islem: 'webauthn_liste' }).then(function (j) {
                $('.gc-govde', m.el).innerHTML = j.liste.length ? j.liste.map(function (k) {
                    return '<div class="gc-g-satir"><div><b>' + esc(k.ad || 'Cihaz') + '</b><span>' + esc(tarihYaz(k.olusturma)) + (k.son ? ' · son kullanım ' + esc(tarihYaz(k.son)) : '') + '</span></div><button type="button" class="gc-btn gc-btn-kucuk gc-tehlike" data-sil="' + k.id + '"><i class="fa-solid fa-trash"></i></button></div>';
                }).join('') : '<div class="gc-bos">Henüz kayıtlı cihaz yok.</div>';
                $$('[data-sil]', m.el).forEach(function (b) {
                    b.onclick = function () {
                        onayla({ baslik: 'Silinsin mi?', mesaj: 'Bu cihazın parmak izi / Face ID kaydı silinecek.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) {
                            if (ok) return gonder('webauthn_sil', { id: b.dataset.sil }).then(function () { if (D) D.webauthn_var = null; yukle(); });
                        }).catch(function (e) { bildir(e.message, 'hata'); });
                    };
                });
            }).catch(function (e) { $('.gc-govde', m.el).innerHTML = '<div class="gc-bos">' + esc(e.message) + '</div>'; });
        }
        yukle();
    }

    /* ---------------- Pencere ---------------- */
    function modal(baslik, govde, alt, gen) {
        var el = document.createElement('div'); el.className = 'gc-arka';
        el.innerHTML = '<div class="gc-modal" role="dialog" aria-modal="true"' + (gen ? ' style="max-width:' + gen + 'px"' : '') + '><div class="gc-modal-ust"><h3>' + baslik + '</h3><button type="button" class="gc-x" aria-label="Kapat">✕</button></div><div class="gc-govde">' + govde + '</div>' + (alt ? '<div class="gc-modal-alt">' + alt + '</div>' : '') + '</div>';
        document.body.appendChild(el);
        var m = { el: el, kapat: function () { el.remove(); } };
        $('.gc-x', el).onclick = m.kapat;
        return m;
    }

    /* ---------------- Sahte konum (mock GPS) tespiti ----------------
     * Android'deki sahte konum uygulamaları (ör. "Fake GPS location") konumu
     * Android'in test sağlayıcısıyla verir; tarayıcıya "sahte" bayrağı gelmez.
     * Ama bu uygulamaların ürettiği konumun izleri vardır:
     *   • doğruluk tam 1 m (varsayılan), tam sayı ve hiç değişmez
     *   • yükseklik tam sayı (varsayılan 65 m), dikey doğruluk hiç yok
     *   • hız bilgisi hiç yok (gerçek GPS ölçümünde hız 0.0 da olsa gelir)
     *   • koordinatlar birebir aynı tekrar eder (gerçek GPS dururken bile titrer)
     * Her belirti puan ekler; eşiği aşarsa giriş/çıkış engellenir. Ham ölçümler
     * sunucuya da gönderilir ki sunucu aynı hesabı kendisi yapabilsin.
     * iOS'ta bu tür uygulamalar yaygın değildir ve iOS doğruluğu farklı raporlar,
     * bu yüzden puan yalnızca Android'de hesaplanır (yanlış alarm olmasın). */
    var SAHTE_ESIK = 50;
    var ANDROID = /Android/i.test(navigator.userAgent || '');
    var konumTampon = [];
    function tamponaEkle(p) {
        var c = p && p.coords; if (!c || !isFinite(c.latitude) || !isFinite(c.longitude)) return;
        var simdi = Date.now();
        konumTampon.push({ la: c.latitude, lo: c.longitude, ac: c.accuracy, al: c.altitude, aa: c.altitudeAccuracy, sp: c.speed, hd: c.heading, t: simdi });
        konumTampon = konumTampon.filter(function (x) { return simdi - x.t < 60000; }).slice(-30);
        if (KONUM_TEST) testPaneliCiz();
        // Kartı hemen güncelle (doğruluğu kötü diye süzülen ölçümlerde de sahte uyarısı gecikmesin)
        if (D && $('#gc-konum-kart')) mesafeYaz();
        if (D) sahteIzle(); // giriş yapılmışken sahte konum gelirse otomatik çıkış + bildirim
    }
    // Kişi sahte konum uygulamasından tarayıcıya döndüğü anda önbelleksiz yeni konum al
    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState !== 'visible' || !konumIds.length) return;
        konumIzle(); konumBaslat(true);
    });
    /* Test ekranı: adresin sonuna ?konumtest=1 ekleyince sağ altta ham konum değerleri ve puan görünür.
       (Yeni dosyanın yüklendiğini de buradan anlarsınız: panelde "v3" yazar.) */
    var KONUM_TEST = /[?&]konumtest=1/.test(location.search);
    if (KONUM_TEST) setTimeout(function () { testPaneliCiz(); }, 1200); // ölçüm gelmese de panel görünsün
    function testPaneliCiz() {
        var p = document.getElementById('gc-konum-test');
        if (!p) {
            p = document.createElement('div'); p.id = 'gc-konum-test';
            p.style.cssText = 'position:fixed;left:8px;right:8px;bottom:8px;z-index:99999;max-height:45vh;overflow:auto;background:rgba(0,0,0,.88);color:#0f0;font:11px/1.45 monospace;padding:8px;border-radius:8px;white-space:pre-wrap;user-select:text;-webkit-user-select:text';
            document.body.appendChild(p);
        }
        var s = sahteKonumPuani();
        function f(v) { return v === null || v === undefined ? 'YOK' : (typeof v === 'number' ? String(Math.round(v * 1e7) / 1e7) : String(v)); }
        p.textContent = 'SAHTE KONUM TESTİ v8 · Android: ' + (ANDROID ? 'evet' : 'HAYIR') + '\n' +
            'Doğruluk sınırı (İK ayarı): ' + ((D && D.ayar && D.ayar.max_dogruluk) || 'yok→100') + ' m · kart: ' + konumDurum + (konumNetlik ? ' (±' + konumNetlik + ')' : '') + ' · bekleme: ' + Math.round((Date.now() - konumBasladi) / 1000) + ' sn\n' +
            'PUAN: ' + s.puan + ' / eşik ' + SAHTE_ESIK + ' → ' + (s.puan >= SAHTE_ESIK ? 'ENGELLENİR' : 'geçer') + '\n' +
            'Nedenler: ' + (s.neden.join(', ') || '-') + '\n' +
            'Son ölçümler (enlem, boylam | doğruluk | yükseklik | dikey doğ. | hız | yön):\n' +
            konumTampon.slice(-8).reverse().map(function (x) {
                return f(x.la) + ', ' + f(x.lo) + ' | ' + f(x.ac) + ' | ' + f(x.al) + ' | ' + f(x.aa) + ' | ' + f(x.sp) + ' | ' + f(x.hd);
            }).join('\n');
    }
    function tamponTaze() { var s = Date.now(); return konumTampon.filter(function (x) { return s - x.t < 45000; }); }
    function sahteKonumPuani() {
        var o = tamponTaze(), s = { puan: 0, neden: [], ornek: o.length };
        // Chrome aynı konumu tekrar göndermeyebilir; bu yüzden tek ölçümle de puanlanır.
        if (!ANDROID || o.length < 1) return s;
        function ekle(p, n) { s.puan += p; s.neden.push(n); }
        function bos(v) { return v === null || v === undefined || (typeof v === 'number' && isNaN(v)); }
        function tamsayi(v) { return !bos(v) && Math.abs(v - Math.round(v)) < 1e-9; }
        function hepsi(f) { return o.every(f); }
        // Yükseklik yalnızca GPS benzeri ölçümlerde gelir. Wi-Fi/baz konumu aynı noktayı
        // doğal olarak tekrarlayabildiği için "donuk" kuralları yalnızca bu ölçümlere uygulanır.
        var yuk = o.filter(function (x) { return !bos(x.al); });
        if (hepsi(function (x) { return x.ac <= 2; })) ekle(40, 'dogruluk_2m_alti');
        if (hepsi(function (x) { return tamsayi(x.ac); })) ekle(15, 'dogruluk_tamsayi');
        if (yuk.length >= 3 && yuk.every(function (x) { return x.ac === yuk[0].ac; })) ekle(15, 'dogruluk_sabit');
        if (yuk.length && yuk.every(function (x) { return tamsayi(x.al) && bos(x.aa); })) ekle(30, 'yukseklik_tamsayi_dikey_yok');
        if (yuk.length && yuk.every(function (x) { return bos(x.sp); })) ekle(15, 'gps_hiz_yok');
        if (yuk.length >= 3 && yuk.every(function (x) { return x.la === yuk[0].la && x.lo === yuk[0].lo; })) ekle(25, 'koordinat_donuk');

        // Hızlı tespit: tek bir ölçüm bile sahte imzası taşıyorsa yeter. Böylece önbellekten
        // gelen eski gerçek ölçümler araya karışsa da tespit gecikmez. Gerçek GPS'te yanlış
        // alarm olmasın diye yalnızca "tam sayı yükseklik + dikey doğruluk yok" imzası olan
        // ölçümler tek başına puanlanır.
        var enIyi = { puan: 0, neden: [] };
        o.forEach(function (x) {
            if (bos(x.al) || !tamsayi(x.al) || !bos(x.aa)) return;
            var p = 30, n = ['tek_olcum_yukseklik_imzasi'];
            if (x.ac <= 2) { p += 40; n.push('tek_olcum_dogruluk_2m_alti'); }
            if (tamsayi(x.ac)) { p += 15; n.push('tek_olcum_dogruluk_tamsayi'); }
            if (bos(x.sp)) { p += 15; n.push('tek_olcum_hiz_yok'); }
            if (p > enIyi.puan) enIyi = { puan: p, neden: n };
        });
        if (enIyi.puan > s.puan) { s.puan = enIyi.puan; s.neden = enIyi.neden; }
        return s;
    }
    function hamKonumJson() {
        return JSON.stringify(tamponTaze().slice(-12).map(function (x) {
            return [x.la, x.lo, x.ac, x.al, x.aa, x.sp, x.hd, x.t];
        }));
    }

    /* ---------------- Konum: en doğru örneği seçer ---------------- */
    /*
     * Konum güvenlik katmanı:
     * Tarayıcı API'si mock GPS bilgisini "gerçek" diye gösterebildiği için web
     * tarafında %100 mock-location tespiti mümkün değildir. Bunun yerine her
     * damga işleminde taze ölçüm, birden fazla örnek ve tutarlılık kontrolü
     * uygulanır. Sonuçlar ayrıca sunucuya gönderilir.
     */
    function konumAl(taze) {
        return new Promise(function (ok, hata) {
            if (!navigator.geolocation) { hata(new Error('Tarayıcınız konumu desteklemiyor.')); return; }

            var ornekler = [], id = null, bitti = false, ilk = null;
            var baslangic = Date.now(), maxSure = 25000; // kapalı alanda GPS ilk ölçümü 15-20 sn sürebilir
            var minOrnek = 2;
            // "İyi ölçüm" eşiği İK ayarındaki doğruluk sınırıyla aynı olsun (sabit 50 m değil)
            var iyiEsik = (D && D.ayar && D.ayar.max_dogruluk) || 50;

            function temizle() {
                if (id !== null) { try { navigator.geolocation.clearWatch(id); } catch (e) {} id = null; }
                clearTimeout(genel); clearTimeout(ilk);
            }
            function mesafe2(a, b) { return mesafe(a.latitude, a.longitude, b.latitude, b.longitude); }
            function bit() {
                if (bitti) return;
                bitti = true; temizle();

                // Bu çağrıda ölçüm gelmediyse, canlı izleyicinin son 20 sn'deki ölçümlerini kullan
                // (Chrome konum değişmediğinde watchPosition'a yeni ölçüm göndermeyebilir).
                if (!ornekler.length) {
                    var s20 = Date.now();
                    konumTampon.filter(function (x) { return s20 - x.t < 20000; }).forEach(function (x) {
                        ornekler.push({ latitude: x.la, longitude: x.lo, accuracy: Number(x.ac) || 9999, altitude: x.al, altitudeAccuracy: x.aa, speed: x.sp, heading: x.hd, t: x.t });
                    });
                }

                if (!ornekler.length) {
                    hata(new Error('Konum alınamadı. GPS\'i açıp konum iznini verin, sonra tekrar deneyin.'));
                    return;
                }

                // Geçersiz / gelecekteki / anlamsız koordinatları kabul etme.
                ornekler = ornekler.filter(function (x) {
                    return isFinite(x.latitude) && isFinite(x.longitude) &&
                        Math.abs(x.latitude) <= 90 && Math.abs(x.longitude) <= 180 &&
                        isFinite(x.accuracy) && x.accuracy > 0 && x.accuracy <= 5000;
                });
                if (!ornekler.length) {
                    hata(new Error('Konum ölçümü geçersiz görünüyor. Lütfen GPS\'i açıp tekrar deneyin.'));
                    return;
                }

                // En iyi (en düşük hata paylı) ölçümü seç.
                ornekler.sort(function (a, b) { return a.accuracy - b.accuracy; });
                var en = ornekler[0];

                // Aynı ölçümün tekrar tekrar dönmesi yerine mümkün olduğunca
                // birbirinden farklı iki örnek alınmış olmasını kontrol et.
                var yayilim = 0;
                if (ornekler.length > 1) {
                    for (var i = 1; i < ornekler.length; i++) {
                        yayilim = Math.max(yayilim, mesafe2(en, ornekler[i]));
                    }
                }

                // Kalite bilgisi: backend isterse ayrıca risk puanı hesaplayabilir.
                en = {
                    enlem: en.latitude,
                    boylam: en.longitude,
                    dogruluk: en.accuracy,
                    ts: Date.now(),
                    ornek: ornekler.length,
                    yayilim: Math.round(yayilim * 10) / 10,
                    sure_ms: Date.now() - baslangic,
                    taze: !!taze,
                    guvenli_baglanti: !!window.isSecureContext,
                    izin: 'unknown'
                };

                try {
                    if (navigator.permissions && navigator.permissions.query) {
                        navigator.permissions.query({ name: 'geolocation' }).then(function (p) {
                            en.izin = p.state || 'unknown'; ok(en);
                        }).catch(function () { ok(en); });
                    } else ok(en);
                } catch (e) { ok(en); }
            }

            var genel = setTimeout(bit, maxSure);
            var ham = 0; // ham ölçüm sayısı (aynı noktanın tekrarları dahil; sahte konum tespiti için)
            id = navigator.geolocation.watchPosition(function (p) {
                var c = p.coords;
                if (!c || !isFinite(c.latitude) || !isFinite(c.longitude)) return;
                tamponaEkle(p); ham++;

                // Aynı noktadan gelen spam ölçümleri sınırsız büyütme.
                var son = ornekler[ornekler.length - 1];
                if (!son || Math.abs(c.latitude - son.latitude) > 0.00000001 ||
                    Math.abs(c.longitude - son.longitude) > 0.00000001 ||
                    Math.abs(c.accuracy - son.accuracy) > 0.1) {
                    ornekler.push({
                        latitude: c.latitude,
                        longitude: c.longitude,
                        accuracy: Number(c.accuracy) || 9999,
                        altitude: c.altitude,
                        altitudeAccuracy: c.altitudeAccuracy,
                        speed: c.speed,
                        heading: c.heading,
                        t: Date.now()
                    });
                    if (ornekler.length > 6) ornekler.shift();
                }

                // 2 kaliteli örnek geldiyse beklemeyi uzatma.
                var iyi = ornekler.filter(function (x) { return x.accuracy <= iyiEsik; });
                // Sahte konum kontrolü için en az 3 ham ölçüm bekle (en fazla ~6 sn).
                var hamYeter = ham >= 3 || Date.now() - baslangic >= 6000;
                if (hamYeter && (iyi.length >= minOrnek || (iyi.length >= 1 && Date.now() - baslangic >= 3500))) bit();
            }, function (e) {
                if (ornekler.length || bitti) return;
                // Zaman aşımı (kod 3) ölümcül değil: izleme sürer, genel süre dolunca bit() karar verir.
                if (e && e.code === 3) return;
                bitti = true; temizle();
                hata(new Error(e.code === 1
                    ? 'Konum izni verilmedi. Tarayıcı ayarlarından bu site için konuma izin verin.'
                    : 'Konum alınamadı. GPS\'in açık olduğundan emin olun.'));
            }, {
                enableHighAccuracy: true,
                timeout: 20000,
                maximumAge: taze ? 0 : 8000
            });
        });
    }
    function mesafe(a, b, c, d) { var R = 6371000, r = Math.PI / 180, dp = (c - a) * r, dl = (d - b) * r, x = Math.sin(dp / 2) * Math.sin(dp / 2) + Math.cos(a * r) * Math.cos(c * r) * Math.sin(dl / 2) * Math.sin(dl / 2); return R * 2 * Math.atan2(Math.sqrt(x), Math.sqrt(1 - x)); }

    /* ---------------- Kaydırma düğmesi ---------------- */
    function yontemSec() {
        var v = D && D.ayar && D.ayar.yontem; return (v && window.GCY && GCY.liste.some(function (x) { return x.k === v; })) ? v : 'kaydir';
    }
    function kaydirma(kok, etiket, sinif, tamam) {
        var yo = yontemSec();
        if (yo !== 'kaydir' && window.GCY && GCY.kur(kok, yo, sinif, tamam)) return;
        kok.innerHTML = '<div class="gc-kay ' + sinif + '"><span class="gc-kay-yazi">' + etiket + '</span><div class="gc-kay-top"><i class="fa-solid fa-chevron-right"></i></div></div>';
        var iz = $('.gc-kay', kok), top = $('.gc-kay-top', kok), bas = null, maks = 0, x = 0;
        function geri() { x = 0; top.style.transform = ''; iz.style.setProperty('--ilerleme', 0); }
        top.addEventListener('pointerdown', function (e) { if (iz.classList.contains('pasif')) return; bas = e.clientX - x; maks = iz.clientWidth - top.offsetWidth - 8; try { top.setPointerCapture(e.pointerId); } catch (er) {} iz.classList.add('surukle'); });
        top.addEventListener('pointermove', function (e) { if (bas === null) return; x = Math.max(0, Math.min(maks, e.clientX - bas)); top.style.transform = 'translateX(' + x + 'px)'; iz.style.setProperty('--ilerleme', maks ? x / maks : 0); });
        function birak() { if (bas === null) return; bas = null; iz.classList.remove('surukle'); var basardi = maks > 0 && x >= maks * 0.9; geri(); if (basardi) tamam(); }
        top.addEventListener('pointerup', birak); top.addEventListener('pointercancel', function () { bas = null; iz.classList.remove('surukle'); geri(); });
    }

    /* ==================== KİŞİSEL GÖRÜNÜM ==================== */
    var D = null, sayac = null, yuklemeZ = 0, seciliNokta = 0, konumOn = null, konumDurum = 'bekliyor';
    function sureDk(sn) { sn = Math.max(0, Math.floor(sn)); var sa = Math.floor(sn / 3600), dk = Math.floor((sn % 3600) / 60); return (sa ? sa + ' sa ' : '') + dk + ' dk'; }
    function enYakinNokta() { if (!konumOn) return null; var en = null, ed = 1e12; D.noktalar.forEach(function (n) { if (n.enlem === null) return; var m = mesafe(konumOn.enlem, konumOn.boylam, n.enlem, n.boylam); if (m < ed) { ed = m; en = n; } }); return en; }
    function etkinNokta() { return seciliNokta ? nokta(seciliNokta) : (enYakinNokta() || D.noktalar[0] || null); }
    function durumYukle() {
        return getir(Object.assign({ islem: 'durum', cihaz: CIHAZ }, cihazIpuclari())).then(function (j) {
            D = j; yuklemeZ = Math.floor(Date.now() / 1000);
            if (j.simdi) { window.GC_SUNUCU = { fark: j.simdi * 1000 - Date.now(), tz: j.tz || 'Europe/Istanbul' }; try { window.dispatchEvent(new Event('gc-sunucu-saat')); } catch (e) {} } // ekrandaki saat telefondan değil sunucudan
            
            kisiselCiz();
        }).catch(function (e) { icerik.innerHTML = '<div class="gc-bos">' + esc(e.message) + '</div>'; });
    }
    function nokta(id) { return (D.noktalar.filter(function (n) { return n.id === id; })[0]) || null; }
    function kisiselCiz() {
        // İzin verilmeyen cihaz türü (örn. bilgisayar / iPhone): giriş-çıkış düğmesi hiç gösterilmez
        if (D.cihaz_izinli === false) {
            clearInterval(sayac); konumIzle();
            icerik.innerHTML = '<div class="gc-kart gc-izinsiz"><div class="gc-izinsiz-ikon"><i class="fa-solid fa-mobile-screen-button"></i></div><h2>Giriş-çıkış bu cihazdan yapılamaz</h2>' +
                '<p>Giriş ve çıkışınızı yalnızca <b>' + esc(D.izinli_cihaz_ad) + '</b> ile yapabilirsiniz.</p><div class="gc-alt">Şu an algılanan cihaz: ' + esc(D.cihaz_turu_ad) + '</div>' +
                (D.taklit ? '<div class="gc-hata-bant"><i class="fa-solid fa-user-secret"></i><span><b>Mobil görünüm taklidi algılandı:</b> ' + esc(D.taklit) + '. Tarayıcının geliştirici araçlarıyla (F12 → mobil görünüm) veya eklentiyle telefon taklidi yapılarak giriş-çıkış yapılamaz.</span></div>'
                  : '<div class="gc-uyari" style="margin:14px 0 0;text-align:left"><i class="fa-solid fa-lightbulb"></i><span>Telefondan giriyorsanız tarayıcınızda <b>“Masaüstü sitesi” (Desktop site)</b> seçeneği açık olabilir: tarayıcı menüsünden (⋮) kapatıp sayfayı yenileyin.</span></div>') +
                '<button type="button" class="gc-btn gc-btn-ana" id="gc-tekrar-dene" style="margin-top:12px"><i class="fa-solid fa-rotate"></i> Tekrar dene</button>' +
                '<details class="gc-tani"><summary>Algılama ayrıntısı (destek için)</summary><div>' + esc(D.tani || '') + '</div></details></div>' +
                '<h3 class="gc-alt-baslik">Son kayıtlarım</h3>' + gecmisHtml(D.gecmis);
            var td = $('#gc-tekrar-dene'); if (td) td.onclick = durumYukle;
            return;
        }
        // Bu telefon başka bir personele kayıtlı: aynı telefonda başkasının hesabıyla giriş-çıkış yapılamaz
        if (D.cihaz === 'baskasi') {
            clearInterval(sayac); konumIzle();
            icerik.innerHTML = '<div class="gc-kart gc-izinsiz"><div class="gc-izinsiz-ikon kirmizi"><i class="fa-solid fa-user-lock"></i></div><h2>Bu telefon başka bir personele kayıtlı</h2>' +
                '<p>Bu telefon <b>' + esc(D.cihaz_sahibi || '') + '</b> adlı personelin giriş-çıkış telefonu. Kurum kuralı gereği bir telefon yalnızca bir kişinin giriş-çıkışı için kullanılabilir; başkasının telefonunda kendi hesabınızla oturum açarak giriş-çıkış yapamazsınız.</p>' +
                '<div class="gc-alt">Kendi telefonunuzdan giriş yapın. Telefonunuz değiştiyse İK biriminden cihaz kaydını güncellemesini isteyin.</div></div>' +
                '<h3 class="gc-alt-baslik">Son kayıtlarım</h3>' + gecmisHtml(D.gecmis);
            return;
        }
        var calisiyor = D.calisiyor;
        if (calisiyor && !kioskOto) { kioskOto = true; tamEkranAc(); }
        var cihazBant = D.ayar.cihaz && D.cihaz !== 'onayli' ? '<div class="gc-uyari"><i class="fa-solid fa-mobile-screen"></i> ' + (D.cihaz === 'bekliyor' ? 'Bu cihaz İK onayı bekliyor; onaylanınca giriş yapabilirsiniz.' : (D.cihaz === 'reddedildi' ? 'Bu cihaz İK tarafından reddedildi.' : 'Bu cihaz ilk giriş-çıkışta kaydedilecek' + (D.ayar.ilk_otomatik ? '' : ' ve İK onayına gönderilecek') + (D.pin_var || !D.ayar.pin ? '.' : ' (önce PIN oluşturacaksınız).'))) + '</div>' : '';
        var noktaSec = D.noktalar.length ? '<div class="gc-alan"><label>Geçiş noktası</label><select id="gc-nokta"><option value="0"' + (!seciliNokta ? ' selected' : '') + '>En yakın (otomatik)</option>' + D.noktalar.map(function (n) { return '<option value="' + n.id + '"' + (n.id === seciliNokta ? ' selected' : '') + '>' + esc(n.ad) + '</option>'; }).join('') + '</select></div>'
            : '<div class="gc-uyari"><i class="fa-solid fa-triangle-exclamation"></i> Henüz geçiş noktası tanımlanmamış. İK ile iletişime geçin.</div>';
        icerik.innerHTML =
            '<div class="gc-kart gc-bugun"><b class="gc-bugun-baslik">Bugünkü çalışma</b>' +
            '<div class="gc-satir"><span class="gc-buyuk" id="gc-bugun">0 dk</span><span class="gc-hedef" id="gc-hedef">Hedef ' + sureDk(D.hedef_sn) + '</span></div><div class="gc-cubuk"><i id="gc-cubuk"></i></div>' +
            '<div class="gc-satir gc-fazla-satir"><span>Fazla mesai</span><b id="gc-fazla">—</b></div></div>' +
            '<div class="gc-halka-kap ' + (calisiyor ? 'calisiyor' : 'bosta') + '"><svg viewBox="0 0 200 200" class="gc-halka"><circle cx="100" cy="100" r="90" class="gc-halka-iz"/><circle cx="100" cy="100" r="90" class="gc-halka-ilerle" id="gc-halka" transform="rotate(-90 100 100)"/></svg>' +
            '<div class="gc-halka-orta"><small>Süre</small><div class="gc-saat"><span id="gc-saat">00:00</span><span class="gc-sn" id="gc-sn">:00</span></div>' +
            '<span class="gc-durum ' + (calisiyor ? 'ic' : 'dis') + '">' + (calisiyor ? 'Çalışıyor' : 'Boşta') + '</span></div></div>' +
            (calisiyor ? '<div class="gc-bilgi"><i class="fa-regular fa-clock"></i> Giriş: <b>' + esc(tarihYaz(D.giris)) + '</b>' + (D.nokta_ad ? ' · ' + esc(D.nokta_ad) : '') + '</div>' : '') +
            cihazBant + '<div class="gc-konum-kart bekliyor" id="gc-konum-kart"></div>' + noktaSec + '<div id="gc-kay"></div>' +
            '<div class="gc-linkler">' + (D.ayar.pin ? '<button type="button" id="gc-pin-btn"><i class="fa-solid fa-key"></i> ' + (D.pin_var ? 'PIN\'imi değiştir' : 'PIN oluştur') + '</button>' : '') +
            (WA_DESTEK ? '<button type="button" id="gc-wa-btn"><i class="fa-solid fa-fingerprint"></i> ' + (D.webauthn_var ? 'Parmak izi / Face ID kayıtlı ✓' : 'Parmak izi / Face ID ekle') + '</button>' : '') + '</div>' +
            '<h3 class="gc-alt-baslik">Son kayıtlarım</h3>' + gecmisHtml(D.gecmis);
        var pinBtn = $('#gc-pin-btn'); if (pinBtn) pinBtn.onclick = function () { pinPenceresi(false); };
        var waBtn = $('#gc-wa-btn'); if (waBtn) waBtn.onclick = function () { D.webauthn_var ? waYonetPenceresi() : parmakIziKaydet(); };
        var sel = $('#gc-nokta'); if (sel) { sel.onchange = function () { seciliNokta = +sel.value; mesafeYaz(); }; }
        var kayCiz = function () { kaydirma($('#gc-kay'), calisiyor ? 'Çıkış yapmak için kaydırın' : 'İşe başlamak için kaydırın', calisiyor ? 'cikis' : 'giris', function () { damgaAkisi(calisiyor ? 'cikis' : 'giris'); }); };
        kayCiz();

        clearInterval(sayac); sayac = setInterval(sayacYaz, 1000); sayacYaz();
        konumOn = null; konumDurum = 'bekliyor';
        var konumGerek = calisiyor ? D.ayar.cikis_konum : D.ayar.konum;
        if (konumGerek && D.noktalar.some(function (n) { return n.enlem !== null; })) { konumBaslat(); konumDegerlendir(); mesafeYaz(); }
        else if (calisiyor) {
            // Çıkışta konum istenmese de, giriş konumla yapıldıysa sahte konum izlemesi arka planda sürer
            konumDurum = 'yok'; mesafeYaz(); konumBaslat();
        }
        else { konumIzle(); konumDurum = 'yok'; mesafeYaz(); }
        sahteIzle();
    }
    function gecmisHtml(l) {
        if (!l.length) return '<div class="gc-bos">Henüz kayıt yok.</div>';
        return '<div class="gc-gecmis">' + l.map(function (k) {
            return '<div class="gc-g-satir"><div><b>' + esc(tarihYaz(k.giris).slice(0, 10)) + '</b> <span>' + esc(saatKisa(k.giris)) + ' – ' + (k.cikis ? esc(saatKisa(k.cikis)) : '<em class="ic">içeride</em>') + '</span></div><div class="gc-g-sag">' + (k.sure_sn !== null ? sureYaz(k.sure_sn) : '') + '</div></div>';
        }).join('') + '</div>';
    }
    function sayacYaz() {
        if (!D) return;
        var gecen = Math.floor(Date.now() / 1000) - yuklemeZ, bugun = D.bugun_sn + (D.calisiyor ? gecen : 0);
        var el = $('#gc-bugun'); if (!el) { clearInterval(sayac); return; }
        if (konumDurum === 'bekliyor') mesafeYaz();
        el.textContent = sureDk(bugun);
        var oran = D.hedef_sn ? Math.min(1, bugun / D.hedef_sn) : 0; $('#gc-cubuk').style.width = (oran * 100) + '%';
        $('#gc-fazla').textContent = bugun > D.hedef_sn ? sureDk(bugun - D.hedef_sn) : '—';
        if (D.calisiyor) {
            var g = (D.simdi - D.giris_ts) + gecen, hs = saatYaz(g);
            $('#gc-saat').textContent = hs.slice(0, 5); $('#gc-sn').textContent = ':' + hs.slice(6);
            var h = $('#gc-halka'), C = 2 * Math.PI * 90; h.style.strokeDasharray = C; h.style.strokeDashoffset = C * (1 - Math.min(1, g / (D.hedef_sn || 28800)));
        }
    }
    function mesafeYaz() {
        var k = $('#gc-konum-kart'), kay = $('#gc-kay .gc-kay'); if (!k) return;
        var durum = 'bekliyor', b1 = 'Konum bekleniyor…', b2 = konumNetlik ? 'Konum netleştiriliyor (±' + konumNetlik + ' m)' : (Date.now() - konumBasladi > 8000 ? 'Uzun sürüyor: GPS\'i açın, mümkünse açık alana çıkın' : 'Tarayıcıya konum izni verin'), tekrar = false;
        if (konumDurum === 'kontrol') { durum = 'bekliyor'; b1 = 'Konum kontrol ediliyor…'; b2 = 'Anlık konum alınıyor, lütfen birkaç saniye bekleyin.'; }
        else if (konumDurum === 'hata') { durum = 'hata'; b1 = 'Konum alınamadı'; b2 = konumHataMesaj || 'İzin verin ve GPS\'in açık olduğundan emin olun.'; tekrar = true; }
        else if (konumDurum === 'yok') { durum = 'yok'; b1 = 'Konum doğrulaması kapalı'; b2 = 'Bu işlem için konum istenmiyor.'; }
        else if (sahteKonumPuani().puan >= SAHTE_ESIK) {
            // Sahte konum şüphesi: kart kırmızı, kaydırma düğmesi kilitli
            durum = 'hata sahte'; tekrar = true;
            b1 = '<i class="fa-solid fa-skull-crossbones"></i> Güvenlik uyarısı: sahte konum yazılımı';
            b2 = 'Telefonunuzda konumu değiştiren zararlı/sahte GPS yazılımı çalışıyor. Giriş-çıkış engellendi ve bu durum İK\'ya bildirilebilir. Uygulamayı kapatın (Geliştirici seçenekleri → Sahte konum uygulaması: Yok), GPS\'i açıp tekrar deneyin.';
            if (!window.__sahteUyarildi) { window.__sahteUyarildi = true; try { navigator.vibrate && navigator.vibrate([300, 120, 300, 120, 500]); } catch (e) {} }
        }
        else if (konumOn) {
            var n = etkinNokta(), dg = Math.round(konumOn.dogruluk || 0);
            if (n && n.enlem !== null) {
                var m = Math.round(mesafe(konumOn.enlem, konumOn.boylam, n.enlem, n.boylam)), ic = m <= n.yaricap;
                durum = ic ? 'tamam' : 'disarida'; b1 = esc(n.ad) + ' · merkeze ' + m + ' m';
                b2 = ic ? 'Alan içindesiniz · yarıçap ' + n.yaricap + ' m (±' + dg + ' m)' : 'Alan dışındasınız · izin verilen ' + n.yaricap + ' m';
            } else { durum = 'tamam'; b1 = 'Konum alındı'; b2 = '±' + dg + ' m'; }
        }
        var kontrol = konumDurum === 'kontrol', dugme = '';
        if (konumDurum !== 'yok') dugme = '<button type="button" class="gc-btn gc-btn-kucuk" id="gc-konum-tekrar"' + (kontrol ? ' disabled' : '') + '><i class="fa-solid ' + (kontrol ? 'fa-spinner fa-spin' : 'fa-location-crosshairs') + '"></i> ' + (tekrar ? 'Tekrar dene' : 'Konumu yenile') + '</button>';
        var html = '<span class="gc-nokta-ikon"></span><div><b>' + b1 + '</b><small>' + b2 + '</small></div>' + dugme, sinif = 'gc-konum-kart ' + durum;
        if (k.className !== sinif || k._son !== html) { // aynıysa yeniden çizme (düğmeye basış kaçmasın)
            k.className = sinif; k.innerHTML = html; k._son = html;
            var t = $('#gc-konum-tekrar'); if (t) t.onclick = konumYenile;
        }
        if (kay) kay.classList.toggle('pasif', durum !== 'tamam' && durum !== 'yok');
    }
    var konumIds = [], konumNetlik = null, konumHam = null, konumHata = false, konumBasladi = 0;
    function konumIzle() { if (navigator.geolocation) konumIds.forEach(function (i) { navigator.geolocation.clearWatch(i); }); konumIds = []; }
    // Ölçüm geldiğinde: yalnızca kaydet; sayfa (D) hazırsa kartı güncelle
    function konumGeldi(p) {
        var c = p.coords;
        tamponaEkle(p); // her ham ölçüm sahte konum tespiti için saklanır (aşağıdaki süzgeçten önce)
        if (konumHam && Date.now() - konumHam.ts < 15000 && c.accuracy > konumHam.dogruluk * 1.6) return; // daha kötü ölçümle bozma
        konumHam = { enlem: c.latitude, boylam: c.longitude, dogruluk: c.accuracy, ts: Date.now() }; konumHata = false;
        konumDegerlendir();
    }
    function tamamenIcerde(h) {
        if (!h || !D || !D.noktalar || !(h.dogruluk > 0) || h.dogruluk > 150) return false;
        var aday = seciliNokta ? [nokta(seciliNokta)] : D.noktalar;
        return aday.some(function (n) {
            return n && n.enlem !== null && mesafe(h.enlem, h.boylam, n.enlem, n.boylam) + h.dogruluk <= n.yaricap;
        });
    }
    function konumDegerlendir() {
        if (!D || !$('#gc-konum-kart') || konumDurum === 'yok') return;
        // Eski ölçüm sayılmaz. İzleyici çalışırken Chrome aynı konumu tekrar göndermediği için süre 60 sn.
        if (konumHam && Date.now() - konumHam.ts > (konumIds.length ? 60000 : 25000)) konumHam = null;
        var max = (D.ayar && D.ayar.max_dogruluk) || 100;
        // Hata payı sınırın üstünde olsa bile, hata dairesinin TAMAMI alanın içindeyse kişi kesin içeridedir: beklemeye gerek yok.
        if (konumHam && (konumHam.dogruluk <= max || tamamenIcerde(konumHam))) { konumOn = konumHam; konumDurum = 'tamam'; konumNetlik = null; }
        else if (konumHam) { konumNetlik = Math.round(konumHam.dogruluk); if (!konumOn) konumDurum = 'bekliyor'; }
        else if (konumHata && !konumOn) konumDurum = 'hata';
        mesafeYaz();
    }
    // İki izleyici birlikte: biri hızlı (Wi-Fi/baz, birkaç sn), diğeri GPS (yavaş ama net). İlk gelen kartı açar, sonrası netleştirir.
    function konumBaslat(taze) {
        if (konumIds.length || !navigator.geolocation) { if (!navigator.geolocation) { konumHata = true; } return; }
        konumBasladi = Date.now(); konumHata = false;
        // Zaman aşımı (kod 3) hata sayılmaz: izleme sürer, kart "Uzun sürüyor…" der. Yalnızca izin reddi / konum kapalı hata gösterir.
        var hata = function (e) { if (e && e.code === 3) return; if (!konumHam) { konumHata = true; if (e && e.code === 1) konumNetlik = null; konumDegerlendir(); } };
        konumIds.push(navigator.geolocation.watchPosition(konumGeldi, hata, { enableHighAccuracy: false, timeout: 20000, maximumAge: taze ? 0 : 60000 }));
        konumIds.push(navigator.geolocation.watchPosition(konumGeldi, hata, { enableHighAccuracy: true, timeout: 45000, maximumAge: taze ? 0 : 30000 }));
    }
    function konumOnizle() { konumIzle(); konumHam = null; konumBaslat(); }
    // "Konumu yenile": eski ölçümü at, önbelleksiz taze konum al, hemen alan içinde miyim diye değerlendir
    var konumHataMesaj = '', konumYenileniyor = false;
    function konumYenile() {
        if (konumYenileniyor) return;
        konumYenileniyor = true;
        konumIzle(); konumHam = null; konumOn = null; konumNetlik = null; konumHata = false; konumHataMesaj = '';
        konumTampon = []; // sahte uygulama kapatıldıysa eski sahte ölçümler hesaba katılmasın
        konumDurum = 'kontrol'; konumBasladi = Date.now(); mesafeYaz();
        konumAl(true).then(function (k) {
            konumHam = { enlem: k.enlem, boylam: k.boylam, dogruluk: k.dogruluk, ts: Date.now() };
            konumDurum = 'bekliyor'; konumYenileniyor = false;
            konumDegerlendir();        // yeterince netse hemen 'alan içinde / dışında' der
            konumBaslat(true);         // ardından canlı izleme sürer, netleştikçe kart güncellenir
        }).catch(function (e) {
            konumHata = true; konumHataMesaj = (e && e.message) || ''; konumDurum = 'hata'; konumYenileniyor = false;
            mesafeYaz();
        });
    }

    /* ---------------- PIN oluştur / değiştir ---------------- */
    function pinPenceresi(zorunlu, sonra) {
        var var_ = D && D.pin_var;
        var m = modal('<i class="fa-solid fa-key"></i> ' + (var_ ? 'PIN değiştir' : 'Kişisel PIN oluştur'),
            '<p class="gc-not">Giriş ve çıkışlarda <b>yalnızca sizin bileceğiniz</b> 4–6 haneli bir PIN istenir. Böylece telefonunuz ya da hesabınız başkasının eline geçse bile sizin yerinize giriş yapılamaz. PIN\'i kimseyle paylaşmayın.</p>' +
            (var_ ? '<div class="gc-alan"><label>Mevcut PIN</label><input type="password" inputmode="numeric" maxlength="6" id="gc-p0" autocomplete="off"></div>' : '') +
            '<div class="gc-alan"><label>Yeni PIN (4–6 rakam)</label><input type="password" inputmode="numeric" maxlength="6" id="gc-p1" autocomplete="off"></div>' +
            '<div class="gc-alan"><label>Yeni PIN (tekrar)</label><input type="password" inputmode="numeric" maxlength="6" id="gc-p2" autocomplete="off"></div>',
            '<button type="button" class="gc-btn" data-k>Vazgeç</button><button type="button" class="gc-btn gc-btn-ana" id="gc-p-kaydet">Kaydet</button>', 420);
        $('[data-k]', m.el).onclick = m.kapat;
        $('#gc-p-kaydet', m.el).onclick = function () {
            var b = $('#gc-p-kaydet', m.el); b.disabled = true;
            gonder('pin_kur', { pin: $('#gc-p1', m.el).value, pin2: $('#gc-p2', m.el).value, eski_pin: var_ ? $('#gc-p0', m.el).value : '' }).then(function () {
                bildir('PIN kaydedildi.', 'basari'); m.kapat(); D.pin_var = true; if (sonra) sonra(); else durumYukle();
            }).catch(function (e) { bildir(e.message, 'hata'); b.disabled = false; });
        };
    }

    /* ---------------- Giriş-çıkış fotoğrafı: ön kamera, 3 sn sonra otomatik (veya hemen "Çek"), en fazla 480 px JPEG ---------------- */
    function fotoAl() {
        return new Promise(function (ok, red) {
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) { red(new Error('Bu tarayıcı kamerayı desteklemiyor.')); return; }
            var m = modal('<i class="fa-solid fa-camera"></i> Giriş-çıkış fotoğrafı', '<div class="gc-kamera"><video id="gc-v" autoplay playsinline muted></video><div class="gc-geri-say" id="gc-gs"></div></div><p class="gc-alt" style="text-align:center">Yüzünüz görünecek şekilde tutun — fotoğraf yalnızca İK\'nın giriş-çıkış kaydında görünür.</p>',
                '<button type="button" class="gc-btn" data-k>Vazgeç</button><button type="button" class="gc-btn gc-btn-ana" id="gc-cek" disabled><i class="fa-solid fa-camera"></i> Çek</button>', 420);
            var v = $('#gc-v', m.el), gs = $('#gc-gs', m.el), cek = $('#gc-cek', m.el), akis = null, t = null, bitti = false;
            function dur() { clearInterval(t); if (akis) akis.getTracks().forEach(function (x) { x.stop(); }); }
            function iptal() { if (bitti) return; bitti = true; dur(); m.el.remove(); red(new Error('Fotoğraf çekilmedi — giriş-çıkış için fotoğraf gerekli.')); }
            $('[data-k]', m.el).onclick = iptal; $('.gc-x', m.el).onclick = iptal;
            function cekim() {
                if (bitti || !v.videoWidth) return; bitti = true;
                var k = Math.min(1, 480 / Math.max(v.videoWidth, v.videoHeight)), c = document.createElement('canvas'); c.width = Math.round(v.videoWidth * k); c.height = Math.round(v.videoHeight * k);
                c.getContext('2d').drawImage(v, 0, 0, c.width, c.height); var url = c.toDataURL('image/jpeg', 0.72); dur(); m.el.remove(); ok(url);
            }
            navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 } }, audio: false }).then(function (s) {
                akis = s; v.srcObject = s; var p = v.play(); if (p && p.catch) p.catch(function () {});
                cek.disabled = false; cek.onclick = cekim; var n = 3; gs.textContent = n;
                t = setInterval(function () { n--; if (n <= 0) { gs.textContent = ''; cekim(); } else gs.textContent = n; }, 1000);
            }).catch(function () { if (bitti) return; bitti = true; dur(); m.el.remove(); red(new Error('Kameraya erişilemedi — tarayıcıya kamera izni verin.')); });
        });
    }

    /* ---------------- Giriş / çıkış akışı: kaydır → PIN (+kod) → konum → sunucu ---------------- */
    function damgaAkisi(tur) {
        if (D.ayar.pin && !D.pin_var) { pinPenceresi(true, function () { damgaAkisi(tur); }); return; }
        if (D.ayar.webauthn) {
            if (!WA_DESTEK) { bildir('Bu cihaz/tarayıcı parmak izi veya Face ID doğrulamasını desteklemiyor. İK ile iletişime geçin.', 'hata'); return; }
            if (!D.webauthn_var) { parmakIziKaydetZorunlu(function () { damgaAkisi(tur); }); return; }
        }
        var n = etkinNokta(), kodGerek = !!(n && n.kod);
        // PIN / ofis kodu kapalıysa hiçbir pencere açılmaz: kaydırınca doğrudan giriş yapılır
        if (!D.ayar.pin && !kodGerek) { damgaGonder(tur, '', ''); return; }
        var m = modal('<i class="fa-solid fa-lock"></i> ' + (tur === 'giris' ? 'Girişi onayla' : 'Çıkışı onayla'),
            (D.ayar.pin ? '<div class="gc-alan"><label>PIN\'iniz</label><input type="password" inputmode="numeric" maxlength="6" id="gc-pin" autocomplete="off" placeholder="••••"></div>' : '') +
            (kodGerek ? '<div class="gc-alan"><label>Ofis ekranındaki 6 haneli kod</label><input type="text" inputmode="numeric" maxlength="7" id="gc-kod" autocomplete="off" placeholder="000 000"></div>' : ''),
            '<button type="button" class="gc-btn" data-k>Vazgeç</button><button type="button" class="gc-btn gc-btn-ana" id="gc-onay">' + (tur === 'giris' ? 'Giriş yap' : 'Çıkış yap') + '</button>', 380);
        $('[data-k]', m.el).onclick = m.kapat;
        var pinEl = $('#gc-pin', m.el), kodEl = $('#gc-kod', m.el);
        function git() { var pin = pinEl ? pinEl.value : '', kod = kodEl ? kodEl.value : ''; m.kapat(); damgaGonder(tur, pin, kod); }
        $('#gc-onay', m.el).onclick = git;
        [pinEl, kodEl].forEach(function (e) { if (e) e.addEventListener('keydown', function (ev) { if (ev.key === 'Enter') git(); }); });
        setTimeout(function () { (pinEl || kodEl).focus(); }, 150);
    }
    function mesgul(yazi) { var k = $('#gc-kay'); if (k) k.innerHTML = '<div class="gc-kay giris kaydediliyor"><span class="gc-kay-yazi"><i class="fa-solid fa-spinner fa-spin"></i> ' + yazi + '</span></div>'; }
    // Sunucuya giden giriş-çıkış verisi (normal damga ve otomatik çıkış ortak kullanır)
    function damgaVerisi(tur, pin, kod, k, wa, foto, sp) {
        var nk = etkinNokta(); if (k) { konumOn = { enlem: k.enlem, boylam: k.boylam, dogruluk: k.dogruluk, ts: Date.now() }; nk = etkinNokta(); }
        var h = cihazIpuclari();
        return {
            sahte_puan: sp ? sp.puan : 0, sahte_neden: sp ? sp.neden.join(',') : '', konum_ham: hamKonumJson(),
            sahte_konum: window.__sahteKonum ? 1 : 0, uygulama: /Bilge360App/.test(navigator.userAgent) ? 1 : 0,
            tur: tur, pin: pin, cihaz: CIHAZ, nokta_id: (nk || {}).id || 0, kod: kod, webauthn: wa,
            enlem: k ? k.enlem : '', boylam: k ? k.boylam : '', dogruluk: k ? k.dogruluk : '', ts: Math.floor(Date.now() / 1000),
            konum_ornek: k ? k.ornek : 0, konum_yayilim: k ? k.yayilim : 0, konum_sure_ms: k ? k.sure_ms : 0,
            konum_taze: k && k.taze ? 1 : 0, konum_guvenli: k && k.guvenli_baglanti ? 1 : 0, konum_izin: k ? k.izin : 'unknown',
            dokunmatik: h.dokunmatik, kaba: h.kaba, ekran: h.ekran, eu: h.eu, platform: h.platform, gpu: h.gpu, oe: h.oe, sw: h.sw,
            uad: h.uad, wd: h.wd, dpr: h.dpr, hc: h.hc, tz: h.tz, model: h.model, foto: foto || undefined
        };
    }

    /* ---------------- Girişten SONRA sahte konum yakalanırsa: otomatik çıkış + İK'ya bildirim ----------------
     * Kişi giriş yapmış (D.calisiyor) ve sayfa açıkken sahte konum algılanırsa:
     *   1) 'sahte_konum_bildir' ile sunucuya (İK'ya) bildirilir,
     *   2) 'damga' ile otomatik çıkış yapılır (sahte_otomatik=1 işaretiyle),
     *   3) ekrana kapatılamayan bir uyarı çıkar. Her sayfa açılışında en fazla bir kez çalışır. */
    var sonPin = '', sahteIslem = false;
    function sahteIzle() {
        // Giriş yapılmışsa (hangi ayarla yapılmış olursa olsun) sahte konum görülünce devreye girer
        if (sahteIslem || !D || !D.calisiyor) return;
        var sp = sahteKonumPuani();
        if (sp.puan < SAHTE_ESIK) return;
        sahteIslem = true;
        sahteOtomatikCikis(sp);
    }
    function sahteOtomatikCikis(sp) {
        var son = konumTampon[konumTampon.length - 1] || null;
        var k = son ? { enlem: son.la, boylam: son.lo, dogruluk: son.ac, ornek: konumTampon.length, yayilim: 0, sure_ms: 0, taze: true, guvenli_baglanti: !!window.isSecureContext, izin: 'granted' } : null;
        var veri = damgaVerisi('cikis', sonPin, '', k, '', '', sp);
        veri.sahte_otomatik = 1;
        veri.giris_zamani = D.giris || '';

        // Kapatılamayan uyarı
        var kat = document.createElement('div');
        kat.style.cssText = 'position:fixed;inset:0;z-index:99998;background:rgba(10,0,0,.92);display:flex;align-items:center;justify-content:center;padding:20px';
        kat.innerHTML = '<div style="max-width:420px;background:#2a0b12;border:1px solid #ff4f6d;border-radius:18px;padding:24px;color:#ffd7de;text-align:center;font-family:inherit">' +
            '<div style="font-size:44px;line-height:1">⚠</div><h2 style="margin:10px 0;color:#fff">Sahte konum tespit edildi</h2>' +
            '<p id="gc-sahte-mesaj" style="margin:0 0 16px;line-height:1.5">Çıkışınız yapılıyor ve durum İK\'ya bildiriliyor…</p>' +
            '<button type="button" id="gc-sahte-tamam" style="display:none;padding:10px 22px;border:0;border-radius:10px;background:#ff4f6d;color:#fff;font-weight:600">Tamam</button></div>';
        document.body.appendChild(kat);
        var mesajEl = kat.querySelector('#gc-sahte-mesaj'), tamamEl = kat.querySelector('#gc-sahte-tamam');
        tamamEl.onclick = function () { kat.remove(); durumYukle(); };

        var bildirSoz = gonder('sahte_konum_bildir', veri).then(function () { return true; }).catch(function () { return false; });
        var cikisSoz = gonder('damga', veri).then(function () { return null; }).catch(function (e) { return (e && e.message) || 'bilinmeyen hata'; });
        Promise.all([bildirSoz, cikisSoz]).then(function (r) {
            var bildirildi = r[0], cikisHata = r[1];
            var m = 'Telefonunuzda sahte konum kullanıldığı tespit edildi.';
            if (cikisHata === null) m += ' <b>Çıkışınız otomatik olarak yapıldı</b>';
            if (bildirildi) m += (cikisHata === null ? ' ve' : '') + ' durum <b>İK\'ya bildirildi</b>';
            m += '.';
            if (cikisHata !== null) m += '<br><small style="opacity:.75">Otomatik çıkış yapılamadı: ' + esc(cikisHata) + '</small>';
            mesajEl.innerHTML = m;
            tamamEl.style.display = 'inline-block';
        });
    }

    function damgaGonder(tur, pin, kod) {
        var konumGerek = tur === 'giris' ? D.ayar.konum : D.ayar.cikis_konum;
        mesgul(konumGerek ? 'Konum kontrol ediliyor…' : 'Kaydediliyor…');
        if (D.ayar.webauthn && D.webauthn_var) { waOn = getir({ islem: 'webauthn_secenekler_giris' }); waOn.catch(function () {}); }
        if (tur === 'giris') sonPin = pin || ''; // sahte konum sonradan yakalanırsa otomatik çıkış için (yalnızca bu sayfa açıkken bellekte)
        // Konum gerekiyorsa önbellekteki eski ölçüm KULLANILMAZ: her giriş-çıkışta taze ölçüm alınır.
        // Böylece sahte konum algılanmadan "anlık giriş" yapılamaz (2-6 sn sürer).
        var taze = !konumGerek && konumOn && Date.now() - konumOn.ts < 30000;
        // Canlı izleyici son 15 sn içinde kabul edilebilir bir ölçüm verdiyse onu kullan: kişi ikinci kez beklemesin.
        // (Sahte konum puanı yine canlı izleyicinin ham ölçümlerinden hesaplanır.)
        var canli = konumGerek && konumDurum === 'tamam' && konumOn && konumIds.length && Date.now() - konumOn.ts < 60000; // izleyici açıkken Chrome aynı konumu tekrar göndermez; 60 sn içindeki ölçüm taze sayılır (bekleme yok)
        if (canli) taze = true;
        var konumSoz = taze ? Promise.resolve(canli ? {
                enlem: konumOn.enlem, boylam: konumOn.boylam, dogruluk: konumOn.dogruluk, ts: konumOn.ts,
                ornek: tamponTaze().length, yayilim: 0, sure_ms: 0, taze: true, guvenli_baglanti: !!window.isSecureContext, izin: 'granted'
            } : konumOn) : konumAl(!!konumGerek).catch(function (e) { if (konumGerek) throw e; return null; });
        var foto = '', k = null, sp = null;
        function sahteDur() {
            sp = sahteKonumPuani();
            if (sp.puan >= SAHTE_ESIK) {
                var e = new Error('Sahte konum tespit edildi. Telefonunuzda sahte konum uygulaması (Geliştirici seçenekleri → "Sahte konum uygulaması seç") açıksa kapatın, GPS\'i açıp tekrar deneyin.');
                e.sahteKonum = true; throw e;
            }
        }
        konumSoz.then(function (kk) {
            k = kk;
            sahteDur(); // fotoğraf / parmak izi istemeden önce kontrol et
            mesgul('Kaydediliyor…');
            return D.ayar.foto ? fotoAl() : '';
        }).then(function (f) {
            foto = f || '';
            return D.ayar.webauthn ? parmakIziDogrula() : '';
        }).then(function (wa) {
            sahteDur(); // fotoğraf çekilirken sahte konum açıldıysa göndermeden önce bir kez daha kontrol
            return gonder('damga', damgaVerisi(tur, pin, kod, k, wa, foto, sp));
        }).then(function (j) { sonucGoster(true, j, tur); })
          .catch(function (e) {
              bildir(e.message, 'hata');
              if (e.veri && e.veri.pin_yok) { pinPenceresi(true, function () { damgaAkisi(tur); }); return; }
              if (e.veri && e.veri.webauthn_yok) { parmakIziKaydetZorunlu(function () { damgaAkisi(tur); }); return; }
              if (e.veri && (e.veri.cihaz_baskasi || e.veri.cihaz_izinsiz || e.veri.cihaz_bekliyor)) { durumYukle(); return; }
              kisiselCiz(); // kaydırma düğmesini geri getir
          });
    }
    function sonucGoster(ok, j, tur) {
        clearInterval(sayac); konumIzle();
        if (ok && tur !== 'cikis') setTimeout(tamEkranAc, 300);
        var t = String(j.zaman || '').match(/(\d{2}):(\d{2})/), saat = t ? t[1] + ':' + t[2] : '';
        var AY = ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'], d = String(j.zaman || '').match(/^(\d{4})-(\d{2})-(\d{2})/);
        var tarih = d ? parseInt(d[3], 10) + ' ' + AY[parseInt(d[2], 10) - 1] + ' ' + d[1] + ' ' + saat : saat;
        var dog = ['konum'].concat(['cihaz']).concat(D && D.ayar && D.ayar.pin ? ['PIN'] : []).concat(D && D.ayar && D.ayar.webauthn ? ['parmak izi/Face ID'] : []).concat(D && D.ayar && D.ayar.foto ? ['fotoğraf'] : []).join(' + ');
        icerik.innerHTML = '<div class="gc-son"><div class="gc-sonuc-ikon ' + (ok ? 'ok' : 'hata') + '"><i class="fa-solid ' + (ok ? 'fa-check' : 'fa-xmark') + '"></i></div>' +
            '<h2>' + (tur === 'cikis' ? 'Çıkış kaydedildi' : 'Giriş kaydedildi') + '</h2><div class="gc-son-saat">' + esc(saat) + '</div>' +
            '<div class="gc-kart gc-son-kart">' +
            '<div class="gc-son-satir"><span>Geçiş noktası</span><b>' + esc(j.nokta_ad || '—') + (j.mesafe != null ? ' · ' + esc(j.mesafe) + ' m' : '') + '</b></div>' +
            '<div class="gc-son-satir"><span>' + (tur === 'cikis' ? 'Çıkış' : 'Giriş') + '</span><b>' + esc(tarih) + '</b></div>' +
            '<div class="gc-son-satir"><span>Doğrulama</span><b>' + esc(dog) + '</b></div></div>' +
            '<button type="button" class="gc-btn gc-btn-ana gc-son-tamam" id="gc-son-tamam">Tamam</button></div>';
        var git = function () { clearTimeout(zm); durumYukle(); }, zm = setTimeout(git, 3500);
        $('#gc-son-tamam').onclick = git;
    }

    /* ==================== İK SEKMELERİ ==================== */
    function bugunCiz() {
        icerik.innerHTML = '<div class="gc-bos">Yükleniyor…</div>';
        getir({ islem: 'bugun' }).then(function (j) {
            var say = { iceride: 0, cikti: 0, gelmedi: 0 }; j.liste.forEach(function (k) { say[k.durum]++; });
            var rz = $('#gc-rozet-cihaz'); if (rz) { rz.hidden = !j.bekleyen_cihaz || !YT.yonet; rz.textContent = j.bekleyen_cihaz; }
            var AD = { iceride: 'İçeride', cikti: 'Çıktı', gelmedi: 'Gelmedi' };
            icerik.innerHTML = '<div class="gc-kutular"><div class="gc-kutu ic"><b>' + say.iceride + '</b><span>İçeride</span></div><div class="gc-kutu cikti"><b>' + say.cikti + '</b><span>Çıktı</span></div><div class="gc-kutu gelmedi"><b>' + say.gelmedi + '</b><span>Henüz gelmedi</span></div></div>' +
                (YT.yonet && j.bekleyen_cihaz ? '<div class="gc-uyari"><i class="fa-solid fa-mobile-screen"></i> ' + j.bekleyen_cihaz + ' cihaz onay bekliyor (Cihazlar sekmesi).</div>' : '') +
                '<div class="gc-kart gc-tablo-kap"><table class="gc-tablo"><thead><tr><th>Personel</th><th>Durum</th><th>İlk giriş</th><th>Son çıkış</th><th>Bugün</th></tr></thead><tbody>' +
                j.liste.map(function (k) { return '<tr class="' + k.durum + '"><td><b>' + esc(k.ad) + '</b><div class="gc-alt">' + esc(k.gorev) + '</div></td><td><span class="gc-durum ' + (k.durum === 'iceride' ? 'ic' : 'dis') + '">' + AD[k.durum] + '</span></td><td>' + esc(saatKisa(k.ilk_giris)) + '</td><td>' + (k.durum === 'iceride' ? '<em class="ic">içeride</em>' : esc(saatKisa(k.son_cikis))) + '</td><td>' + esc(k.sure) + '</td></tr>'; }).join('') + '</tbody></table></div>';
        }).catch(function (e) { icerik.innerHTML = '<div class="gc-bos">' + esc(e.message) + '</div>'; });
    }
    var kFiltre = { bas: '', bit: '', kisi: '', supheli: '' };
    function kayitlarCiz() {
        icerik.innerHTML = '<div class="gc-bos">Yükleniyor…</div>';
        getir({ islem: 'kayitlar', bas: kFiltre.bas, bit: kFiltre.bit, kullanici_id: kFiltre.kisi, supheli: kFiltre.supheli }).then(function (j) {
            kFiltre.bas = j.bas; kFiltre.bit = j.bit;
            var toplam = j.liste.reduce(function (t, k) { return t + (k.sure_sn || 0); }, 0);
            icerik.innerHTML = '<div class="gc-filtre"><input type="date" id="gk-bas" value="' + j.bas + '"><input type="date" id="gk-bit" value="' + j.bit + '"><select id="gk-kisi"><option value="">Tüm personel</option>' + j.kisiler.map(function (k) { return '<option value="' + k.id + '"' + (String(k.id) === String(kFiltre.kisi) ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' +
                '<label class="gc-onay-kutu"><input type="checkbox" id="gk-sup"' + (kFiltre.supheli === '1' ? ' checked' : '') + '> Yalnızca şüpheli</label>' + (YT.yonet ? '<button type="button" class="gc-btn gc-btn-ana gc-btn-kucuk" id="gk-ekle"><i class="fa-solid fa-plus"></i> Kayıt ekle</button>' : '') + '</div>' +
                '<div class="gc-not">' + j.liste.length + ' kayıt · toplam ' + sureYaz(toplam) + '</div><div class="gc-kart gc-tablo-kap"><table class="gc-tablo"><thead><tr><th>Personel</th><th>Fotoğraf</th><th>Giriş</th><th>Çıkış</th><th>Süre</th><th>Nokta</th><th>Not / uyarı</th>' + (YT.yonet ? '<th></th>' : '') + '</tr></thead><tbody>' +
                (j.liste.length ? j.liste.map(function (k) {
                    var fu = function (t) { return API + '?islem=foto&t=' + t + '&id=' + k.id; };
                    return '<tr' + (k.supheli.some(function (x) { return x.kod !== 'duzeltildi'; }) ? ' class="gc-sup-satir"' : '') + '><td><b>' + esc(k.ad) + '</b></td><td class="gc-foto-hucre">' + (k.giris_foto ? '<img src="' + fu('giris') + '" data-foto="' + fu('giris') + '" alt="giriş" title="Giriş fotoğrafı" loading="lazy">' : '') + (k.cikis_foto ? '<img src="' + fu('cikis') + '" data-foto="' + fu('cikis') + '" alt="çıkış" title="Çıkış fotoğrafı" loading="lazy">' : '') + (!k.giris_foto && !k.cikis_foto ? '<span class="gc-alt">—</span>' : '') + '</td><td>' + esc(tarihYaz(k.giris)) + '</td><td>' + (k.cikis ? esc(tarihYaz(k.cikis)) : '<em class="ic">içeride</em>') + '</td><td>' + (k.sure_sn !== null ? sureYaz(k.sure_sn) : '') + '</td><td>' + esc(k.nokta) + (k.giris_mesafe !== null ? '<div class="gc-alt">' + k.giris_mesafe + ' m</div>' : '') + '</td><td>' +
                        k.supheli.map(function (s) { return '<span class="gc-cip ' + (s.kod === 'duzeltildi' ? 'duz' : 'sup') + '" title="' + esc(s.ad) + '">' + esc(s.ad.length > 42 ? s.ad.slice(0, 40) + '…' : s.ad) + '</span>'; }).join('') + (k.not ? '<div class="gc-alt">' + esc(k.not) + '</div>' : '') + '</td>' +
                        (YT.yonet ? '<td class="gc-islem"><button type="button" class="gc-btn gc-btn-kucuk" data-duz="' + k.id + '"><i class="fa-solid fa-pen"></i></button><button type="button" class="gc-btn gc-btn-kucuk gc-tehlike" data-sil="' + k.id + '"><i class="fa-solid fa-trash"></i></button></td>' : '') + '</tr>';
                }).join('') : '<tr><td colspan="8" class="gc-bos">Bu aralıkta kayıt yok.</td></tr>') + '</tbody></table></div>';
            var yenile = function () { kFiltre = { bas: $('#gk-bas').value, bit: $('#gk-bit').value, kisi: $('#gk-kisi').value, supheli: $('#gk-sup').checked ? '1' : '' }; kayitlarCiz(); };
            ['gk-bas', 'gk-bit', 'gk-kisi', 'gk-sup'].forEach(function (i) { $('#' + i).onchange = yenile; });
            var ek = $('#gk-ekle'); if (ek) ek.onclick = function () { kayitFormu(null, j.kisiler); };
            $$('[data-foto]').forEach(function (im) { im.onclick = function () { var fm = modal('<i class="fa-solid fa-camera"></i> Giriş-çıkış fotoğrafı', '<img src="' + esc(im.dataset.foto) + '" alt="" style="width:100%;border-radius:12px">', '<button type="button" class="gc-btn" data-k>Kapat</button>', 460); $('[data-k]', fm.el).onclick = fm.kapat; }; });
            $$('[data-duz]').forEach(function (b) { b.onclick = function () { kayitFormu(j.liste.filter(function (k) { return String(k.id) === b.dataset.duz; })[0], j.kisiler); }; });
            $$('[data-sil]').forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Kayıt silinsin mi?', mesaj: 'Bu giriş-çıkış kaydı kalıcı olarak silinir.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('kayit_sil', { id: b.dataset.sil }).then(kayitlarCiz); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
        }).catch(function (e) { icerik.innerHTML = '<div class="gc-bos">' + esc(e.message) + '</div>'; });
    }
    function dtl(t) { return t ? String(t).replace(' ', 'T').slice(0, 16) : ''; }
    function kayitFormu(k, kisiler) {
        var m = modal('<i class="fa-solid fa-pen"></i> ' + (k ? 'Kaydı düzelt — ' + esc(k.ad) : 'Elle kayıt ekle'),
            (k ? '' : '<div class="gc-alan"><label>Personel</label><select id="gf-kisi">' + kisiler.map(function (x) { return '<option value="' + x.id + '">' + esc(x.ad) + '</option>'; }).join('') + '</select></div>') +
            '<div class="gc-alan"><label>Giriş</label><input type="datetime-local" id="gf-giris" value="' + dtl(k && k.giris) + '"></div><div class="gc-alan"><label>Çıkış <small>(içerideyse boş bırakın)</small></label><input type="datetime-local" id="gf-cikis" value="' + dtl(k && k.cikis) + '"></div>' +
            '<div class="gc-alan"><label>Düzeltme nedeni <small>(zorunlu — denetim için saklanır)</small></label><input type="text" id="gf-not" maxlength="300" value="' + esc(k ? k.not : '') + '" placeholder="Örn. Çıkışı unuttu, telefonu bozuktu"></div>',
            '<button type="button" class="gc-btn" data-k>Vazgeç</button><button type="button" class="gc-btn gc-btn-ana" id="gf-kaydet">Kaydet</button>', 460);
        $('[data-k]', m.el).onclick = m.kapat;
        $('#gf-kaydet', m.el).onclick = function () {
            var v = { giris: $('#gf-giris', m.el).value, cikis: $('#gf-cikis', m.el).value, not: $('#gf-not', m.el).value };
            if (k) v.id = k.id; else v.kullanici_id = $('#gf-kisi', m.el).value;
            gonder(k ? 'kayit_duzelt' : 'kayit_ekle', v).then(function () { bildir('Kaydedildi.', 'basari'); m.kapat(); kayitlarCiz(); }).catch(function (e) { bildir(e.message, 'hata'); });
        };
    }
    function cihazlarCiz() {
        icerik.innerHTML = '<div class="gc-bos">Yükleniyor…</div>';
        getir({ islem: 'cihazlar' }).then(function (j) {
            var AD = { onayli: 'Onaylı', bekliyor: 'Onay bekliyor', reddedildi: 'Reddedildi' };
            var bek = j.liste.filter(function (c) { return c.durum === 'bekliyor'; }).length;
            icerik.innerHTML = '<div class="gc-not">Personel yalnızca <b>onaylı cihazından</b> giriş yapabilir. Yeni cihazlar burada onayınızı bekler (Ayarlar\'dan ilk cihaz otomatik onaylanabilir). <b>Başka bir personelin telefonuna benzeyen</b> cihazlar (gizli sekme, başka tarayıcı, tarayıcı verisini silme ile aynı telefon) her durumda onaya düşer — <b>“Benzer telefon”</b> uyarısı varsa onaylamadan önce kontrol edin. Bir kişinin PIN\'ini unutması halinde “PIN sıfırla” ile yeniden oluşturmasını sağlayabilirsiniz.</div>' +
                (bek ? '<div class="gc-uyari"><i class="fa-solid fa-mobile-screen"></i> ' + bek + ' cihaz onayınızı bekliyor.</div>' : '') +
                '<div class="gc-kart gc-tablo-kap"><table class="gc-tablo"><thead><tr><th>Personel</th><th>Cihaz</th><th>Durum</th><th>Son kullanım</th><th>IP</th><th></th></tr></thead><tbody>' +
                (j.liste.length ? j.liste.map(function (c) {
                    var bi = c.bilgi, ipucu = bi ? 'Ekran kartı: ' + (bi.gpu || '—') + ' · Ekran: ' + bi.ekran + ' · Çekirdek: ' + bi.cekirdek + ' · Platform: ' + (bi.platform || '—') : '';
                    return '<tr' + (c.benzer.length || c.ortak.length || c.durum === 'bekliyor' ? ' class="gc-sup-satir"' : '') + '><td><b>' + esc(c.ad) + '</b><div class="gc-alt">' + (c.pin_var ? 'PIN var' : 'PIN yok') + '</div>' + (c.not && c.durum === 'bekliyor' ? '<div class="gc-alt">' + esc(c.not) + '</div>' : '') + '</td><td title="' + esc(ipucu) + '">' + esc(c.cihaz) +
                        (c.ortak.length ? '<div><span class="gc-cip kir" title="Aynı telefon (tarayıcı) birden fazla personele kayıtlı"><i class="fa-solid fa-ban"></i> Ortak telefon: ' + esc(c.ortak.join(', ')) + '</span></div>' : '') +
                        (c.benzer.length ? '<div><span class="gc-cip sup" title="Donanım özellikleri aynı: gizli sekme / başka tarayıcıyla aynı telefon olabilir (veya aynı model telefon)"><i class="fa-solid fa-triangle-exclamation"></i> Benzer telefon: ' + esc(c.benzer.join(', ')) + '</span></div>' : '') + '</td><td><span class="gc-durum ' + (c.durum === 'onayli' ? 'ic' : (c.durum === 'bekliyor' ? 'bek' : 'dis')) + '">' + AD[c.durum] + '</span></td><td>' + esc(tarihYaz(c.son)) + '</td><td>' + esc(c.ip) + '</td><td class="gc-islem">' +
                        (c.durum !== 'onayli' ? '<button type="button" class="gc-btn gc-btn-kucuk gc-btn-ana" data-k="' + c.id + '" data-v="onayli">Onayla</button>' : '') + (c.durum !== 'reddedildi' ? '<button type="button" class="gc-btn gc-btn-kucuk" data-k="' + c.id + '" data-v="reddedildi">Reddet</button>' : '') +
                        '<button type="button" class="gc-btn gc-btn-kucuk" data-pin="' + c.kullanici_id + '" title="PIN sıfırla"><i class="fa-solid fa-key"></i></button><button type="button" class="gc-btn gc-btn-kucuk gc-tehlike" data-k="' + c.id + '" data-v="sil" title="Cihazı sil"><i class="fa-solid fa-trash"></i></button></td></tr>';
                }).join('') : '<tr><td colspan="6" class="gc-bos">Henüz cihaz kaydı yok.</td></tr>') + '</tbody></table></div>';
            $$('[data-k]').forEach(function (b) { b.onclick = function () {
                var c = j.liste.filter(function (x) { return String(x.id) === b.dataset.k; })[0] || {}, uyari = b.dataset.v === 'onayli' && ((c.benzer || []).length || (c.ortak || []).length);
                (uyari ? onayla({ baslik: 'Cihaz onaylansın mı?', mesaj: 'DİKKAT: Bu cihaz ' + (c.ortak.length ? c.ortak : c.benzer).join(', ') + ' adlı personelin telefonuyla aynı ya da çok benzer. Aynı model telefon değilse başkasının telefonuyla giriş-çıkış yapılıyor olabilir. Personelin kendi telefonu olduğundan emin misiniz?', evet: 'Onayla', hayir: 'Vazgeç', tur: 'tehlike' }) : Promise.resolve(true))
                    .then(function (ok) { if (ok) return gonder('cihaz_karar', { id: b.dataset.k, karar: b.dataset.v }).then(cihazlarCiz); }).catch(function (e) { bildir(e.message, 'hata'); });
            }; });
            $$('[data-pin]').forEach(function (b) { b.onclick = function () { onayla({ baslik: 'PIN sıfırlansın mı?', mesaj: 'Kişi bir sonraki girişte yeni bir PIN oluşturmak zorunda kalacak.', evet: 'Sıfırla', hayir: 'Vazgeç' }).then(function (ok) { if (ok) return gonder('pin_sifirla', { kullanici_id: b.dataset.pin }).then(function () { bildir('PIN sıfırlandı.', 'basari'); cihazlarCiz(); }); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
        }).catch(function (e) { icerik.innerHTML = '<div class="gc-bos">' + esc(e.message) + '</div>'; });
    }
    function noktalarCiz() {
        icerik.innerHTML = '<div class="gc-bos">Yükleniyor…</div>';
        getir({ islem: 'noktalar' }).then(function (j) {
            var base = location.href.replace(/[^\/]*$/, '');
            icerik.innerHTML = '<div class="gc-not">Personel yalnızca <b>tanımlı noktanın yarıçapı içindeyken</b> giriş-çıkış yapabilir (konum sunucuda doğrulanır). Telefon GPS\'i birkaç metre şaşabildiği için yarıçapı çok küçük (10 m altı) tutmayın. Ek güvenlik için <b>ofis IP kısıtı</b> ve <b>ofis ekranı kodu</b> ekleyebilirsiniz.</div>' +
                '<button type="button" class="gc-btn gc-btn-ana" id="gn-yeni"><i class="fa-solid fa-plus"></i> Yeni geçiş noktası</button><div class="gc-noktalar">' +
                j.liste.map(function (n) {
                    return '<div class="gc-kart gc-nokta ' + (n.aktif ? '' : 'pasif') + '"><div class="gc-satir"><b>' + esc(n.ad) + '</b>' + (n.aktif ? '' : '<span class="gc-cip">Pasif</span>') + '</div><div class="gc-alt">' + (n.enlem !== '' ? esc(n.enlem) + ', ' + esc(n.boylam) + ' · yarıçap ' + n.yaricap + ' m' : 'Konum tanımlı değil') + (n.ip_kisit ? ' · IP: ' + esc(n.ip_kisit) : '') + (n.kod_zorunlu ? ' · ofis kodu zorunlu' : '') + '</div>' +
                        (n.kod_zorunlu ? '<div class="gc-alt">Ofis ekranı adresi: <input type="text" readonly value="' + esc(base + 'giris_ekran.php?nokta=' + n.id + '&anahtar=' + n.ekran_anahtari) + '" class="gc-adres" onclick="this.select()"></div>' : '') +
                        '<div class="gc-islem"><button type="button" class="gc-btn gc-btn-kucuk" data-duz="' + n.id + '"><i class="fa-solid fa-pen"></i> Düzenle</button><button type="button" class="gc-btn gc-btn-kucuk gc-tehlike" data-sil="' + n.id + '"><i class="fa-solid fa-trash"></i></button></div></div>';
                }).join('') + '</div>';
            $('#gn-yeni').onclick = function () { noktaFormu(null); };
            $$('[data-duz]').forEach(function (b) { b.onclick = function () { noktaFormu(j.liste.filter(function (n) { return String(n.id) === b.dataset.duz; })[0]); }; });
            $$('[data-sil]').forEach(function (b) { b.onclick = function () { onayla({ baslik: 'Nokta silinsin mi?', mesaj: 'Nokta silinir; eski kayıtlar korunur.', evet: 'Sil', hayir: 'Vazgeç', tur: 'tehlike' }).then(function (ok) { if (ok) return gonder('nokta_sil', { id: b.dataset.sil }).then(noktalarCiz); }).catch(function (e) { bildir(e.message, 'hata'); }); }; });
        }).catch(function (e) { icerik.innerHTML = '<div class="gc-bos">' + esc(e.message) + '</div>'; });
    }
    function noktaFormu(n) {
        var m = modal('<i class="fa-solid fa-location-dot"></i> ' + (n ? 'Noktayı düzenle' : 'Yeni geçiş noktası'),
            '<div class="gc-alan"><label>Ad</label><input type="text" id="gp-ad" value="' + esc(n ? n.ad : '') + '" placeholder="Örn. Vuslat Merkez Ofis"></div>' +
            '<div class="gc-olcum"><div class="gc-olcum-baslik"><i class="fa-solid fa-person-walking"></i> Alanı yürüyerek ölç</div>' +
            '<ol><li>Derneğin / ofisin <b>içinde</b> (merkez olacak yerde) <b>Başla</b>\'ya basın.</li><li>Personelin “içeride” sayılacağı <b>en uzak yere</b> (örn. derneğin hemen dışı) yürüyün.</li><li>Orada <b>Bitir</b>\'e basın: iki nokta arasındaki mesafe <b>yarıçap</b> olur, ilk nokta <b>merkez</b> olur.</li></ol>' +
            '<div class="gc-olcum-dugmeler"><button type="button" class="gc-btn gc-btn-kucuk gc-btn-ana" id="go-basla"><i class="fa-solid fa-play"></i> Başla</button><button type="button" class="gc-btn gc-btn-kucuk" id="go-bitir" disabled><i class="fa-solid fa-flag-checkered"></i> Bitir</button></div>' +
            '<div class="gc-konum-durum" id="go-durum" style="margin-top:10px">Henüz ölçüm yapılmadı. (Yalnızca merkezi almak için “Başla”ya basıp hemen “Bitir” demeyin; manuel de girebilirsiniz.)</div></div>' +
            '<div class="gc-iki"><div class="gc-alan"><label>Enlem</label><input type="text" id="gp-en" value="' + esc(n ? n.enlem : '') + '"></div><div class="gc-alan"><label>Boylam</label><input type="text" id="gp-bo" value="' + esc(n ? n.boylam : '') + '"></div></div>' +
            '<div class="gc-alan"><label>Yarıçap (metre) <small>izin verilen alan; en az 10, önerilen 50–150</small></label><input type="number" id="gp-yar" min="10" max="5000" value="' + (n ? n.yaricap : 100) + '"></div>' +
            '<div class="gc-alan"><label>Ofis IP kısıtı <small>(isteğe bağlı; ofis internetinin dış IP\'si. Birden çoksa virgülle. Örn: 85.100.20.5 veya 85.100.20.0/24)</small></label><input type="text" id="gp-ip" value="' + esc(n ? n.ip_kisit : '') + '"></div>' +
            '<label class="gc-onay-kutu"><input type="checkbox" id="gp-kod"' + (n && n.kod_zorunlu ? ' checked' : '') + '> Ofis ekranındaki dönen 6 haneli kodu zorunlu tut <small>(ofiste bir tablet/TV\'de <b>giris_ekran.php</b> açık kalır — sahte GPS\'e karşı en güçlü koruma)</small></label>' +
            '<label class="gc-onay-kutu"><input type="checkbox" id="gp-aktif"' + (!n || n.aktif ? ' checked' : '') + '> Aktif</label>',
            '<button type="button" class="gc-btn" data-k>Vazgeç</button><button type="button" class="gc-btn gc-btn-ana" id="gp-kaydet">Kaydet</button>', 480);
        $('[data-k]', m.el).onclick = m.kapat;
        // ── Yürüyerek ölçüm: Başla (merkez) → yürü (canlı mesafe) → Bitir (yarıçap = mesafe) ──
        var basNokta = null, izId = null, durumEl = $('#go-durum', m.el), basBtn = $('#go-basla', m.el), bitBtn = $('#go-bitir', m.el);
        function izDurdur() { if (izId !== null) { navigator.geolocation.clearWatch(izId); izId = null; } }
        var kapatAsil = m.kapat; m.kapat = function () { izDurdur(); kapatAsil(); }; $('.gc-x', m.el).onclick = m.kapat; $('[data-k]', m.el).onclick = m.kapat;
        basBtn.onclick = function () {
            izDurdur(); basBtn.disabled = true; bitBtn.disabled = true; durumEl.className = 'gc-konum-durum'; durumEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Merkez konumu alınıyor… (kıpırdamadan bekleyin)';
            konumAl().then(function (k) {
                basNokta = k; $('#gp-en', m.el).value = k.enlem.toFixed(7); $('#gp-bo', m.el).value = k.boylam.toFixed(7);
                basBtn.disabled = false; basBtn.innerHTML = '<i class="fa-solid fa-rotate-right"></i> Yeniden başla'; bitBtn.disabled = false;
                durumEl.className = 'gc-konum-durum tamam'; durumEl.innerHTML = '<i class="fa-solid fa-location-crosshairs"></i> Merkez kaydedildi (GPS hatası ≈ ' + Math.round(k.dogruluk) + ' m). Şimdi sınıra yürüyün…';
                izId = navigator.geolocation.watchPosition(function (p) {
                    var d = Math.round(mesafe(basNokta.enlem, basNokta.boylam, p.coords.latitude, p.coords.longitude));
                    durumEl.innerHTML = '<i class="fa-solid fa-person-walking"></i> Merkezden şu an <b>' + d + ' m</b> uzaktasınız <small>(GPS ±' + Math.round(p.coords.accuracy) + ' m)</small> — sınıra gelince <b>Bitir</b>\'e basın.';
                }, function () {}, { enableHighAccuracy: true, maximumAge: 0, timeout: 20000 });
            }).catch(function (e) { basBtn.disabled = false; durumEl.className = 'gc-konum-durum hata'; durumEl.textContent = e.message; });
        };
        bitBtn.onclick = function () {
            if (!basNokta) return;
            izDurdur(); bitBtn.disabled = true; basBtn.disabled = true; durumEl.className = 'gc-konum-durum'; durumEl.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Bitiş konumu alınıyor…';
            konumAl().then(function (k) {
                var d = mesafe(basNokta.enlem, basNokta.boylam, k.enlem, k.boylam), yar = Math.max(10, Math.ceil(d / 5) * 5);
                $('#gp-yar', m.el).value = yar; basBtn.disabled = false;
                durumEl.className = 'gc-konum-durum tamam';
                durumEl.innerHTML = '<i class="fa-solid fa-circle-check"></i> Ölçülen mesafe <b>' + Math.round(d) + ' m</b> → yarıçap <b>' + yar + ' m</b> olarak ayarlandı. <small>(GPS hata payı: başlangıç ±' + Math.round(basNokta.dogruluk) + ' m, bitiş ±' + Math.round(k.dogruluk) + ' m — gerekirse yarıçapı elle değiştirin)</small>';
            }).catch(function (e) { basBtn.disabled = false; bitBtn.disabled = false; durumEl.className = 'gc-konum-durum hata'; durumEl.textContent = e.message; });
        };
        $('#gp-kaydet', m.el).onclick = function () {
            var v = { ad: $('#gp-ad', m.el).value, enlem: $('#gp-en', m.el).value, boylam: $('#gp-bo', m.el).value, yaricap: $('#gp-yar', m.el).value, ip_kisit: $('#gp-ip', m.el).value, kod_zorunlu: $('#gp-kod', m.el).checked ? 1 : 0, aktif: $('#gp-aktif', m.el).checked ? 1 : 0 };
            if (n) v.id = n.id;
            gonder('nokta_kaydet', v).then(function () { bildir('Kaydedildi.', 'basari'); m.kapat(); noktalarCiz(); }).catch(function (e) { bildir(e.message, 'hata'); });
        };
    }
    function ayarlarCiz() {
        icerik.innerHTML = '<div class="gc-bos">Yükleniyor…</div>';
        getir({ islem: 'ayarlar' }).then(function (j) {
            var A = j.ayarlar, cb = function (id, ad, aciklama) { return '<label class="gc-onay-kutu"><input type="checkbox" id="' + id + '"' + (A[id] ? ' checked' : '') + '> <span><b>' + ad + '</b><small>' + aciklama + '</small></span></label>'; };
            icerik.innerHTML = '<div class="gc-kart gc-form">' +
                '<div class="gc-iki"><div class="gc-alan"><label>Günlük çalışma hedefi (saat)</label><input type="number" id="hedef_saat" min="1" max="24" step="0.5" value="' + A.hedef_saat + '"></div><div class="gc-alan"><label>En fazla konum hatası (metre)</label><input type="number" id="max_dogruluk" min="10" max="1000" value="' + A.max_dogruluk + '"></div></div>' +
                '<div class="gc-ayar-baslik"><i class="fa-solid fa-clock"></i> Varsayılan vardiya <small>(geç kalma / erken çıkma raporları buna göre hesaplanır; kişiye özel saat için Vardiyalar sekmesi)</small></div>' +
                '<div class="gc-iki"><div class="gc-alan"><label>Mesai başlangıcı</label><input type="time" id="mesai_baslangic" value="' + esc(A.mesai_baslangic) + '"></div><div class="gc-alan"><label>Mesai bitişi</label><input type="time" id="mesai_bitis" value="' + esc(A.mesai_bitis) + '"></div></div>' +
                '<div class="gc-iki"><div class="gc-alan"><label>Tolerans (dakika) <small>bu kadar gecikme “geç” sayılmaz</small></label><input type="number" id="tolerans_dk" min="0" max="120" value="' + A.tolerans_dk + '"></div><div class="gc-alan"><label>Açık kalan kayıt kaç saat sonra otomatik kapansın</label><input type="number" id="otomatik_kapat_saat" min="4" max="48" value="' + A.otomatik_kapat_saat + '"></div></div>' +
                '<div class="gc-alan"><label>Çalışma günleri</label><div class="gc-gunler">' + [[1, 'Pzt'], [2, 'Sal'], [3, 'Çar'], [4, 'Per'], [5, 'Cum'], [6, 'Cmt'], [7, 'Paz']].map(function (g) { return '<label class="gc-gun"><input type="checkbox" data-gun="' + g[0] + '"' + (String(A.calisma_gunleri).split(',').indexOf(String(g[0])) !== -1 ? ' checked' : '') + '><span>' + g[1] + '</span></label>'; }).join('') + '</div></div>' +
                '<div class="gc-ayar-baslik"><i class="fa-solid fa-shield-halved"></i> Güvenlik</div>' +
                '<div class="gc-alan"><label>Hangi cihazlardan giriş-çıkış yapılabilsin?</label><div class="gc-gunler">' + [['android', 'Android telefon'], ['ios', 'iPhone / iPad'], ['masaustu', 'Bilgisayar (Windows / Mac / Linux)']].map(function (g) { return '<label class="gc-gun"><input type="checkbox" data-cihaz="' + g[0] + '"' + (String(A.izinli_cihaz).split(',').indexOf(g[0]) !== -1 ? ' checked' : '') + '><span>' + g[1] + '</span></label>'; }).join('') + '</div><small class="gc-alt">İzin verilmeyen cihazda personel giriş-çıkış düğmesini hiç görmez. Kimse seçili değilse kaydedilmez. Bilgisayarda tarayıcının geliştirici araçlarıyla (F12 → mobil görünüm) yapılan telefon taklidi algılanır ve bilgisayar sayılır.</small></div>' +
                '<div class="gc-alan"><label for="yontem">Giriş-çıkış onay yöntemi</label><select id="yontem">' + (window.GCY ? GCY.liste.map(function (x) { return '<option value="' + x.k + '"' + (A.yontem === x.k ? ' selected' : '') + '>' + esc(x.ad) + '</option>'; }).join('') : '') + '</select><small>Seçim tüm personelin giriş ekranında geçerli olur; güvenlik doğrulamaları değişmez.</small></div>' + cb('konum_zorunlu', 'Girişte konum zorunlu', 'Kapatılırsa konum doğrulanmaz (kayıt “konumsuz” olarak işaretlenir).') + cb('cikis_konum', 'Çıkışta da konum zorunlu', 'Kapalıysa personel çıkışı ofis dışından yapabilir.') +
                cb('pin_zorunlu', 'Her girişte kişisel PIN iste', 'Hesabı / telefonu ele geçiren kişinin personel yerine damga vurmasını engeller.') +
                cb('webauthn_zorunlu', 'Her girişte parmak izi / Face ID iste', 'PIN\'in aksine paylaşılamaz: telefonu ele geçiren biri, kişinin parmağı/yüzü olmadan damga vuramaz. Cihaz desteklemiyorsa personel giriş-çıkış yapamaz; önce herkesin uyumlu cihazı olduğundan emin olun.') +
                cb('cihaz_zorunlu', 'Yalnızca onaylı cihazdan giriş', 'Hesaba başka telefondan girilse bile damga vurulamaz.') +
                cb('cihaz_ilk_otomatik', 'Kişinin ilk cihazını otomatik onayla', 'Kapalıyken her yeni cihaz (ilki dahil) sizin onayınızı bekler (önerilen). Açıkken de başka personelin telefonuna benzeyen cihazlar onaya düşer.') +
                cb('tek_telefon', 'Bir telefon = bir personel', 'Bir telefon yalnızca bir kişinin giriş-çıkışı için kullanılabilir: başkasının telefonunda kendi hesabıyla oturum açan personel giriş-çıkış yapamaz.') +
                cb('foto_zorunlu', 'Giriş-çıkış fotoğrafı', 'Her giriş-çıkışta ön kameradan anlık fotoğraf çekilir; Kayıtlar sekmesinde görünür (arkadaşının yerine basmayı ortaya çıkarır).') +
                '<button type="button" class="gc-btn gc-btn-ana" id="ga-kaydet">Kaydet</button></div>';
            if ($('#yontem')) $('#yontem').onchange = function () { if (this.value === 'parmak' && $('#webauthn_zorunlu')) $('#webauthn_zorunlu').checked = true; };
            $('#ga-kaydet').onclick = function () {
                var v = {}; ['hedef_saat', 'max_dogruluk', 'mesai_baslangic', 'mesai_bitis', 'tolerans_dk', 'otomatik_kapat_saat'].forEach(function (a) { v[a] = $('#' + a).value; });
                if ($('#yontem')) v.yontem = $('#yontem').value;
                v.calisma_gunleri = $$('[data-gun]').filter(function (c) { return c.checked; }).map(function (c) { return c.dataset.gun; }).join(',');
                v.izinli_cihaz = $$('[data-cihaz]').filter(function (c) { return c.checked; }).map(function (c) { return c.dataset.cihaz; }).join(',');
                ['konum_zorunlu', 'cikis_konum', 'pin_zorunlu', 'webauthn_zorunlu', 'cihaz_zorunlu', 'cihaz_ilk_otomatik', 'tek_telefon', 'foto_zorunlu'].forEach(function (a) { v[a] = $('#' + a).checked ? 1 : 0; });
                gonder('ayar_kaydet', v).then(function () { bildir('Ayarlar kaydedildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); });
            };
        }).catch(function (e) { icerik.innerHTML = '<div class="gc-bos">' + esc(e.message) + '</div>'; });
    }

    /* ==================== RAPORLAR: geç kalma · erken gelme · erken/geç çıkma · devamsızlık ==================== */
    function dkYaz(dk) { dk = Math.round(dk || 0); if (!dk) return '—'; return dk >= 60 ? Math.floor(dk / 60) + 's ' + (dk % 60 ? (dk % 60) + 'd' : '') : dk + ' dk'; }
    function ymd(d) { return d.getFullYear() + '-' + p2(d.getMonth() + 1) + '-' + p2(d.getDate()); }
    var rFiltre = null, rSirala = { alan: 'gec_dk', ters: true }, rVeri = null;
    function donemler() {
        var b = new Date(), ayBas = new Date(b.getFullYear(), b.getMonth(), 1), gecenBas = new Date(b.getFullYear(), b.getMonth() - 1, 1), gecenSon = new Date(b.getFullYear(), b.getMonth(), 0), h7 = new Date(b.getTime() - 6 * 86400000), yil = new Date(b.getFullYear(), 0, 1);
        return { ay: [ymd(ayBas), ymd(b), 'Bu ay'], gecen: [ymd(gecenBas), ymd(gecenSon), 'Geçen ay'], hafta: [ymd(h7), ymd(b), 'Son 7 gün'], yil: [ymd(yil), ymd(b), 'Bu yıl'] };
    }
    function raporCiz() {
        var DN = donemler(); if (!rFiltre) rFiltre = { bas: DN.ay[0], bit: DN.ay[1], kisi: '', donem: 'ay' };
        icerik.innerHTML = '<div class="gc-bos">Rapor hazırlanıyor…</div>';
        getir({ islem: 'rapor', bas: rFiltre.bas, bit: rFiltre.bit, kullanici_id: rFiltre.kisi }).then(function (j) { rVeri = j; raporGoster(); })
            .catch(function (e) { icerik.innerHTML = '<div class="gc-bos">' + esc(e.message) + '</div>'; });
    }
    function raporGoster() {
        var j = rVeri, DN = donemler(), T = j.toplam, tek = !!rFiltre.kisi;
        var sat = j.satirlar.slice().sort(function (a, b) { var x = a[rSirala.alan], y = b[rSirala.alan]; if (typeof x === 'string') return rSirala.ters ? y.localeCompare(x, 'tr') : x.localeCompare(y, 'tr'); return rSirala.ters ? y - x : x - y; });
        var enGec = j.satirlar.filter(function (o) { return o.gec_dk > 0; }).sort(function (a, b) { return b.gec_dk - a.gec_dk; }).slice(0, 5), enMaks = enGec.length ? enGec[0].gec_dk : 1;
        var kpi = function (ikon, sinif, deger, ad, alt) { return '<div class="gc-kpi ' + sinif + '"><i class="fa-solid ' + ikon + '"></i><div><b>' + deger + '</b><span>' + ad + '</span>' + (alt ? '<small>' + alt + '</small>' : '') + '</div></div>'; };
        var h = '<div class="gc-r-filtre"><div class="gc-donem">' + Object.keys(DN).map(function (k) { return '<button type="button" data-d="' + k + '" class="' + (rFiltre.donem === k ? 'aktif' : '') + '">' + DN[k][2] + '</button>'; }).join('') + '</div>' +
            '<input type="date" id="gr-bas" value="' + j.bas + '"><input type="date" id="gr-bit" value="' + j.bit + '">' +
            '<select id="gr-kisi"><option value="">Tüm personel</option>' + j.kisiler.map(function (k) { return '<option value="' + k.id + '"' + (String(k.id) === String(rFiltre.kisi) ? ' selected' : '') + '>' + esc(k.ad) + '</option>'; }).join('') + '</select>' +
            '<button type="button" class="gc-btn gc-btn-kucuk" id="gr-csv"><i class="fa-solid fa-file-csv"></i> Excel (CSV) indir</button></div>' +
            '<div class="gc-not">Vardiya: <b>' + esc(j.vardiya.baslangic) + '–' + esc(j.vardiya.bitis) + '</b> (kişiye özel vardiyası olanlar kendi saatiyle) · tolerans <b>' + j.vardiya.tolerans + ' dk</b>. Geç kalma = ilk giriş vardiya başlangıcından sonra; erken çıkış = son çıkış vardiya bitişinden önce; geç çıkış = bitişten sonra. Onaylı izin / rapor / tatil günleri devamsızlık sayılmaz.</div>' +
            '<div class="gc-kpiler">' + kpi('fa-person-running', 'kirmizi', dkYaz(T.gec_dk), 'Toplam geç kalma', T.gec_kisi + ' kişi geç kalmış') + kpi('fa-door-open', 'turuncu', dkYaz(T.erken_cikis_dk), 'Erken çıkış (toplam)') + kpi('fa-moon', 'mor', dkYaz(T.gec_cikis_dk), 'Geç çıkış / fazla süre') + kpi('fa-sun', 'yesil', dkYaz(T.erken_dk), 'Erken gelme (toplam)') + kpi('fa-user-xmark', 'gri', T.devamsiz_gun, 'Devamsız gün', 'mazeretsiz / kayıtsız') + '</div>';
        if (!tek && enGec.length) h += '<div class="gc-kart"><div class="gc-r-baslik"><i class="fa-solid fa-ranking-star"></i> En çok geç kalanlar</div>' + enGec.map(function (o) { return '<div class="gc-bar-satir" data-kisi="' + o.id + '"><span class="ad">' + esc(o.ad) + '</span><div class="gc-bar"><i style="width:' + Math.max(4, Math.round(o.gec_dk / enMaks * 100)) + '%"></i></div><b>' + dkYaz(o.gec_dk) + '</b><small>' + o.gec_gun + ' gün</small></div>'; }).join('') + '</div>';
        if (tek) {
            var o = j.satirlar[0] || {}, ROZ = { zamaninda: ['Zamanında', 'ic'], gec: ['Geç', 'gec'], devamsiz: ['Devamsız', 'dev'], izinli: ['İzinli', 'izin'], tatil_gunu: ['Tatil günü', 'tat'], hafta_sonu: ['Tatil günü çalıştı', 'ic'], iceride: ['İçeride', 'ic'], cikis_yok: ['Çıkış yok', 'gec'], bekleniyor: ['Bekleniyor', 'tat'], gecelek: ['', ''] };
            h += '<div class="gc-kart gc-r-ozet"><b>' + esc(o.ad || '') + '</b> · vardiya ' + esc(o.vardiya || '') + '<div class="gc-r-ozet-satir"><span>Çalışılan <b>' + o.calisilan_gun + '</b> gün</span><span>Geç kalınan <b>' + o.gec_gun + '</b> gün (<b>' + dkYaz(o.gec_dk) + '</b>, en çok ' + dkYaz(o.en_cok_gec) + ')</span><span>Erken çıkış <b>' + o.erken_cikis_gun + '</b> gün (' + dkYaz(o.erken_cikis_dk) + ')</span><span>Geç çıkış <b>' + o.gec_cikis_gun + '</b> gün (' + dkYaz(o.gec_cikis_dk) + ')</span><span>Devamsız <b>' + o.devamsiz_gun + '</b> gün</span><span>Toplam çalışma <b>' + sureYaz(o.toplam_sn) + '</b></span></div></div>' +
                '<div class="gc-kart gc-tablo-kap"><table class="gc-tablo"><thead><tr><th>Tarih</th><th>Durum</th><th>İlk giriş</th><th>Son çıkış</th><th>Geç kalma</th><th>Erken çıkış</th><th>Geç çıkış</th><th>Süre</th></tr></thead><tbody>' +
                j.gunluk.filter(function (d) { return d.durum !== 'gelecek'; }).map(function (d) { var r = ROZ[d.durum] || ['', '']; return '<tr class="gc-g-' + d.durum + '"><td><b>' + esc(d.tarih.split('-').reverse().join('.')) + '</b> <span class="gc-alt">' + d.gun + '</span></td><td>' + (r[0] ? '<span class="gc-rozet2 ' + r[1] + '">' + r[0] + '</span>' : '') + '</td><td>' + esc(d.ilk) + '</td><td>' + esc(d.son) + '</td><td class="' + (d.gec_dk ? 'kir' : '') + '">' + dkYaz(d.gec_dk) + '</td><td class="' + (d.erken_cikis_dk ? 'tur' : '') + '">' + dkYaz(d.erken_cikis_dk) + '</td><td>' + dkYaz(d.gec_cikis_dk) + '</td><td>' + (d.sure_sn ? sureYaz(d.sure_sn) : '') + '</td></tr>'; }).join('') + '</tbody></table></div>';
        } else {
            var kol = [['ad', 'Personel'], ['gec_gun', 'Geç (gün)'], ['gec_dk', 'Toplam geç'], ['en_cok_gec', 'En çok geç'], ['erken_dk', 'Erken gelme'], ['erken_cikis_dk', 'Erken çıkış'], ['gec_cikis_dk', 'Geç çıkış'], ['devamsiz_gun', 'Devamsız'], ['toplam_sn', 'Toplam çalışma']];
            h += '<div class="gc-kart gc-tablo-kap"><table class="gc-tablo gc-siralanir"><thead><tr>' + kol.map(function (k) { return '<th data-s="' + k[0] + '" class="' + (rSirala.alan === k[0] ? 'sirali' : '') + '">' + k[1] + (rSirala.alan === k[0] ? (rSirala.ters ? ' ▼' : ' ▲') : '') + '</th>'; }).join('') + '</tr></thead><tbody>' +
                sat.map(function (o) { return '<tr class="gc-tikla" data-kisi="' + o.id + '"><td><b>' + esc(o.ad) + '</b><div class="gc-alt">' + esc(o.vardiya) + '</div></td><td>' + o.gec_gun + '</td><td class="' + (o.gec_dk ? 'kir' : '') + '"><b>' + dkYaz(o.gec_dk) + '</b></td><td>' + dkYaz(o.en_cok_gec) + '</td><td class="' + (o.erken_dk ? 'yes' : '') + '">' + dkYaz(o.erken_dk) + '</td><td class="' + (o.erken_cikis_dk ? 'tur' : '') + '">' + dkYaz(o.erken_cikis_dk) + '</td><td>' + dkYaz(o.gec_cikis_dk) + '</td><td class="' + (o.devamsiz_gun ? 'kir' : '') + '">' + (o.devamsiz_gun || '—') + '</td><td>' + sureYaz(o.toplam_sn) + '</td></tr>'; }).join('') + '</tbody></table></div><div class="gc-alt" style="margin-top:6px">Ayrıntılı günlük döküm için bir personele tıklayın.</div>';
        }
        icerik.innerHTML = h;
        $$('.gc-donem button').forEach(function (b) { b.onclick = function () { var d = DN[b.dataset.d]; rFiltre.bas = d[0]; rFiltre.bit = d[1]; rFiltre.donem = b.dataset.d; raporCiz(); }; });
        var yenile = function () { rFiltre.bas = $('#gr-bas').value; rFiltre.bit = $('#gr-bit').value; rFiltre.kisi = $('#gr-kisi').value; rFiltre.donem = ''; raporCiz(); };
        ['gr-bas', 'gr-bit', 'gr-kisi'].forEach(function (i) { $('#' + i).onchange = yenile; });
        $$('[data-kisi]').forEach(function (r) { r.onclick = function () { rFiltre.kisi = r.dataset.kisi; raporCiz(); }; });
        $$('.gc-siralanir th').forEach(function (t) { t.onclick = function () { rSirala = { alan: t.dataset.s, ters: rSirala.alan === t.dataset.s ? !rSirala.ters : t.dataset.s !== 'ad' }; raporGoster(); }; });
        $('#gr-csv').onclick = function () { csvIndir(tek); };
    }
    function csvIndir(tek) {
        var j = rVeri, satir = [];
        if (tek) { satir.push(['Tarih', 'Gün', 'Durum', 'İlk giriş', 'Son çıkış', 'Geç kalma (dk)', 'Erken gelme (dk)', 'Erken çıkış (dk)', 'Geç çıkış (dk)', 'Çalışma (dk)']); j.gunluk.forEach(function (d) { if (d.durum !== 'gelecek') satir.push([d.tarih, d.gun, d.durum, d.ilk, d.son, d.gec_dk, d.erken_dk, d.erken_cikis_dk, d.gec_cikis_dk, Math.round(d.sure_sn / 60)]); }); }
        else { satir.push(['Personel', 'Vardiya', 'Çalışılan gün', 'Geç kalınan gün', 'Toplam geç (dk)', 'En çok geç (dk)', 'Erken gelen gün', 'Erken gelme (dk)', 'Erken çıkış günü', 'Erken çıkış (dk)', 'Geç çıkış günü', 'Geç çıkış (dk)', 'Devamsız gün', 'Toplam çalışma (dk)']); j.satirlar.forEach(function (o) { satir.push([o.ad, o.vardiya, o.calisilan_gun, o.gec_gun, o.gec_dk, o.en_cok_gec, o.erken_gun, o.erken_dk, o.erken_cikis_gun, o.erken_cikis_dk, o.gec_cikis_gun, o.gec_cikis_dk, o.devamsiz_gun, Math.round(o.toplam_sn / 60)]); }); }
        var csv = '\uFEFF' + satir.map(function (r) { return r.map(function (x) { return '"' + String(x == null ? '' : x).replace(/"/g, '""') + '"'; }).join(';'); }).join('\r\n');
        var a = document.createElement('a'); a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' })); a.download = 'giris_cikis_raporu_' + j.bas + '_' + j.bit + '.csv'; document.body.appendChild(a); a.click(); a.remove();
    }
    function vardiyalarCiz() {
        icerik.innerHTML = '<div class="gc-bos">Yükleniyor…</div>';
        getir({ islem: 'vardiyalar' }).then(function (j) {
            icerik.innerHTML = '<div class="gc-not">Varsayılan vardiya <b>' + esc(j.varsayilan.baslangic) + '–' + esc(j.varsayilan.bitis) + '</b> (Ayarlar sekmesinden değişir). Farklı saatte çalışanlar için o satıra kendi başlangıç / bitiş saatini yazın; <b>boş bırakılan satır varsayılanı kullanır</b>.</div>' +
                '<div class="gc-kart gc-tablo-kap"><table class="gc-tablo"><thead><tr><th>Personel</th><th>Başlangıç</th><th>Bitiş</th></tr></thead><tbody>' +
                j.liste.map(function (k) { return '<tr data-id="' + k.id + '"><td><b>' + esc(k.ad) + '</b><div class="gc-alt">' + esc(k.gorev) + '</div></td><td><input type="time" class="gv-b" value="' + esc(k.baslangic) + '"></td><td><input type="time" class="gv-t" value="' + esc(k.bitis) + '"></td></tr>'; }).join('') + '</tbody></table></div>' +
                '<button type="button" class="gc-btn gc-btn-ana" id="gv-kaydet" style="margin-top:12px"><i class="fa-solid fa-floppy-disk"></i> Kaydet</button>';
            $('#gv-kaydet').onclick = function () {
                var kayitlar = $$('tr[data-id]').map(function (tr) { return { kullanici_id: tr.dataset.id, baslangic: $('.gv-b', tr).value, bitis: $('.gv-t', tr).value }; });
                gonder('vardiya_kaydet', { kayitlar: JSON.stringify(kayitlar) }).then(function () { bildir('Vardiyalar kaydedildi.', 'basari'); }).catch(function (e) { bildir(e.message, 'hata'); });
            };
        }).catch(function (e) { icerik.innerHTML = '<div class="gc-bos">' + esc(e.message) + '</div>'; });
    }

    /* ---------------- Sekmeler ---------------- */
    var GORUNUM = { benim: durumYukle, bugun: bugunCiz, kayitlar: kayitlarCiz, rapor: raporCiz, vardiyalar: vardiyalarCiz, cihazlar: cihazlarCiz, noktalar: noktalarCiz, ayarlar: ayarlarCiz };
    function ac(s) { clearInterval(sayac); $$('#gc-sekmeler button').forEach(function (b) { b.classList.toggle('aktif', b.dataset.s === s); }); (GORUNUM[s] || durumYukle)(); }
    $$('#gc-sekmeler button').forEach(function (b) { b.addEventListener('click', function () { ac(b.dataset.s); }); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden && D && $('#gc-saat')) durumYukle(); });
    if (YT.yonet) getir({ islem: 'bugun' }).then(function (j) { var rz = $('#gc-rozet-cihaz'); if (rz && j.bekleyen_cihaz) { rz.hidden = false; rz.textContent = j.bekleyen_cihaz; } }).catch(function () {});
    /* ---------------- Geri oku kaldırıldı → yerine küçük "Oturumu kapat" düğmesi ---------------- */
    (function () {
        function ekle() {
            var bas = $('.gc-baslik'); if (!bas || $('.gc-cikis-btn', bas)) return;
            var g = $('.gc-geri', bas); if (g) g.style.display = 'none';
            var a = document.createElement('a');
            a.className = 'gc-cikis-btn'; a.href = 'cikis.php'; a.title = 'Oturumu kapat'; a.setAttribute('aria-label', 'Oturumu kapat');
            a.innerHTML = '<i class="fa-solid fa-power-off"></i>';
            a.addEventListener('click', function (e) {
                e.preventDefault();
                onayla({ baslik: 'Oturum kapatılsın mı?', mesaj: 'Hesabınızdan çıkış yapılacak. (Mesai çıkışı kaydedilmez.)', evet: 'Evet, kapat', tur: 'uyari', ikon: 'fa-power-off' }).then(function (t) { if (t) location.href = 'cikis.php'; });
            });
            bas.insertBefore(a, bas.firstChild);
        }
        ekle(); document.addEventListener('DOMContentLoaded', ekle);
    })();

    /* ---------------- Tam ekran (kiosk): giriş yapıldığı an (ve zaten içerideyken sayfa açılınca) kendiliğinden açılır ---------------- */
    function tamEkranAc() {
        var deneme = 0;
        function bul() {
            var adaylar = $$('button, a, [role="button"]');
            for (var i = 0; i < adaylar.length; i++) {
                var e = adaylar[i], t = (e.textContent || '').replace(/\s+/g, ' ').trim().toLowerCase();
                var et = ((e.getAttribute('aria-label') || '') + ' ' + (e.getAttribute('title') || '')).toLowerCase();
                if ((t === 'tam ekran' || et.indexOf('tam ekran') > -1) && e.offsetParent !== null) return e;
            }
            return null;
        }
        (function dene() {
            var b = bul();
            if (b) { b.click(); return; }
            if (++deneme < 20) setTimeout(dene, 300);
        })();
    }

    konumBaslat();
    durumYukle();
})();
