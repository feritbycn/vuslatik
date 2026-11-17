/* Giriş-çıkış onay yöntemleri: kaydırmaya alternatif olarak basılı tutma, geri sayım, kapı, kadran, onay işareti çizme ve çift dokunuş.
   Kullanım: GCY.kur(kapsayici, yontem, 'giris'|'cikis', tamamlaninca). Seçilen yöntem tamamlanınca callback çağrılır;
   konum / PIN / parmak izi / cihaz doğrulamaları değişmez, sunucuda yine aynı kurallarla doğrulanır. */
(function () {
    'use strict';
    var LISTE = [
        { k: 'kaydir', ad: 'Kaydır', ac: 'Düğmeyi sağa kaydırarak onaylar (mevcut yöntem).' },
        { k: 'halka', ad: 'Halka: basılı tut', ac: '2 saniye basılı tutulur, halka dolunca onaylanır.' },
        { k: 'sayac', ad: 'Geri sayım: basılı tut', ac: '3-2-1 sayılırken basılı tutulur.' },
        { k: 'kapi', ad: 'Kapı: basılı tut', ac: 'Basılı tutuldukça kapı açılır.' },
        { k: 'kadran', ad: 'Kadran: çevir', ac: 'Düğme saat yönünde bir tam tur çevrilir.' },
        { k: 'cizim', ad: 'Onay işareti: çiz', ac: 'Parmakla bir tik işareti çizilir.' },
        { k: 'cift', ad: 'Çift dokunuş', ac: 'Bir kez dokunulur, süre dolmadan bir kez daha dokunulur.' },
        { k: 'parmak', ad: 'Parmak izi: dokun', ac: 'Parmak izi alanına dokunulur, telefonun parmak izi / Face ID penceresi açılır. Seçilince “her girişte parmak izi iste” ayarı otomatik açılır.' }
    ];
    function $(q, r) { return (r || document).querySelector(q); }
    function anim(ms, fn, end) { var t0 = performance.now(); (function f(t) { var k = Math.min(1, Math.max(0, (t - t0) / ms)); fn(k); if (k < 1) requestAnimationFrame(f); else if (end) end(); })(t0); }
    function hold(el, ms, cb) {
        var t0 = 0, raf = 0, val = 0, on = false, fin = false;
        function loop(now) {
            if (on) val = Math.min(1, (now - t0) / ms); else val = Math.max(0, val - .05);
            cb.p(val);
            if (val >= 1 && !fin) { fin = true; on = false; cb.done(); return; }
            if (on || val > 0) raf = requestAnimationFrame(loop);
        }
        el.addEventListener('pointerdown', function (e) { if (fin) return; e.preventDefault(); try { el.setPointerCapture(e.pointerId); } catch (x) {} on = true; t0 = performance.now() - val * ms; cancelAnimationFrame(raf); raf = requestAnimationFrame(loop); });
        function up() { on = false; }
        el.addEventListener('pointerup', up); el.addEventListener('pointercancel', up); el.addEventListener('lostpointercapture', up);
        el.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    }
    function surukle(el, f) {
        var on = false;
        el.addEventListener('pointerdown', function (e) { e.preventDefault(); on = true; try { el.setPointerCapture(e.pointerId); } catch (x) {} if (f.down) f.down(e); });
        el.addEventListener('pointermove', function (e) { if (on) f.move(e); });
        function bit(e) { if (!on) return; on = false; if (f.up) f.up(e); }
        el.addEventListener('pointerup', bit); el.addEventListener('pointercancel', bit);
        el.addEventListener('contextmenu', function (e) { e.preventDefault(); });
    }
    function sars(el) { el.classList.remove('gy-ttk'); void el.offsetWidth; el.classList.add('gy-ttk'); }
    function ikon(n) { return '<i class="fa-solid fa-' + n + '"></i>'; }

    var KUR = {};
    KUR.halka = function (c) {
        var C = 2 * Math.PI * 52;
        c.s.innerHTML = '<div class="gy-halka"><svg viewBox="0 0 120 120"><circle class="t" cx="60" cy="60" r="52"/><circle class="p" cx="60" cy="60" r="52" stroke-dasharray="' + C + '" stroke-dashoffset="' + C + '"/></svg><button type="button" class="gy-btn" aria-label="Basılı tutun">' + ikon('fingerprint') + '</button></div>';
        c.ip.textContent = c.on + '2 saniye basılı tutun';
        var p = $('.p', c.s), b = $('.gy-btn', c.s);
        hold(b, 2000, { p: function (v) { p.style.strokeDashoffset = C * (1 - v); b.style.transform = 'scale(' + (1 - v * .1) + ')'; }, done: function () { b.style.transform = ''; b.innerHTML = ikon('check'); c.bitti(); } });
    };
    KUR.sayac = function (c) {
        var C = 2 * Math.PI * 52;
        c.s.innerHTML = '<div class="gy-halka"><svg viewBox="0 0 120 120"><circle class="t" cx="60" cy="60" r="52"/><circle class="p" cx="60" cy="60" r="52" stroke-dasharray="' + C + '" stroke-dashoffset="' + C + '"/></svg><button type="button" class="gy-btn gy-say" aria-label="Basılı tutun"><b>3</b></button></div>';
        c.ip.textContent = c.on + 'sayı bitene kadar basılı tutun';
        var p = $('.p', c.s), b = $('.gy-btn', c.s), sn = 3;
        hold(b, 2400, { p: function (v) { p.style.strokeDashoffset = C * (1 - v); var n = Math.max(1, 3 - Math.floor(v * 3)), e = $('b', b); if (e && n !== sn) { sn = n; e.textContent = n; e.classList.remove('gy-pop'); void e.offsetWidth; e.classList.add('gy-pop'); } }, done: function () { b.innerHTML = ikon('check'); c.bitti(); } });
    };
    KUR.kapi = function (c) {
        c.s.innerHTML = '<div class="gy-kapi"><span class="gy-ck">' + ikon('check') + '</span><div class="gy-dl"><i></i></div><div class="gy-dr"><i></i></div><span class="gy-sm">'+c.on+'basılı tutun</span></div>';
        var w = $('.gy-kapi', c.s), dl = $('.gy-dl', c.s), dr = $('.gy-dr', c.s), sm = $('.gy-sm', c.s);
        hold(w, 2000, { p: function (v) { var o = v * v * (3 - 2 * v); dl.style.transform = 'translateX(' + (-o * 100) + '%)'; dr.style.transform = 'translateX(' + (o * 100) + '%)'; sm.style.opacity = Math.max(0, 1 - v * 2); }, done: function () { c.bitti(); } });
    };
    KUR.kadran = function (c) {
        c.s.innerHTML = '<div class="gy-kadran"><i class="gy-rg"></i><div class="gy-kn"><i></i><span>Çevir</span></div></div>';
        c.ip.textContent = c.on + 'düğmeyi saat yönünde çevirin';
        var w = $('.gy-kadran', c.s), rg = $('.gy-rg', c.s), kn = $('.gy-kn', c.s), ang = 0, last = 0, fin = false;
        function dr() { rg.style.setProperty('--p', ang / 360); kn.style.transform = 'rotate(' + ang + 'deg)'; }
        function aci(e) { var r = w.getBoundingClientRect(); return (Math.atan2(e.clientX - (r.left + r.width / 2), -(e.clientY - (r.top + r.height / 2))) * 180 / Math.PI + 360) % 360; }
        surukle(w, {
            down: function (e) { if (!fin) last = aci(e); },
            move: function (e) { if (fin) return; var a = aci(e), d = a - last; if (d > 180) d -= 360; if (d < -180) d += 360; last = a; ang = Math.max(0, Math.min(360, ang + d)); dr(); if (ang >= 352) { ang = 360; dr(); fin = true; kn.innerHTML = ikon('check'); c.bitti(); } },
            up: function () { if (fin) return; (function b() { ang = Math.max(0, ang - 7); dr(); if (ang > 0 && !fin) requestAnimationFrame(b); })(); }
        });
    };
    KUR.cizim = function (c) {
        c.s.innerHTML = '<canvas class="gy-cv"></canvas><span class="gy-ph">' + c.on + 'tik işareti çizin</span>';
        var cv = $('canvas', c.s), g, W = 300, H = 76, pts = [], on = false, fin = false, d = window.devicePixelRatio || 1;
        function boyut() { var r = cv.getBoundingClientRect(); W = r.width || 300; H = r.height || 76; cv.width = Math.round(W * d); cv.height = Math.round(H * d); g = cv.getContext('2d'); g.setTransform(d, 0, 0, d, 0, 0); }
        boyut();
        function yol() { var cx = W * .72; g.beginPath(); g.moveTo(cx - .45 * H, .52 * H); g.lineTo(cx - .12 * H, .84 * H); g.lineTo(cx + .5 * H, .18 * H); }
        function rehber(son) { g.clearRect(0, 0, W, H); g.lineCap = 'round'; g.lineJoin = 'round'; g.lineWidth = H * (son ? .16 : .1); g.setLineDash(son ? [] : [1, H * .17]); g.strokeStyle = son ? 'rgba(255,255,255,.95)' : 'rgba(255,255,255,.55)'; yol(); g.stroke(); g.setLineDash([]); }
        function nok(e) { var r = cv.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }
        function ciz() { g.lineWidth = H * .13; g.strokeStyle = '#fff'; g.shadowColor = '#fff'; g.shadowBlur = 10; g.beginPath(); pts.forEach(function (p, i) { if (i) g.lineTo(p.x, p.y); else g.moveTo(p.x, p.y); }); g.stroke(); g.shadowBlur = 0; }
        function gecerli() {
            var n = pts.length; if (n < 8) return false;
            var len = 0, mnx = 1e9, mxx = -1e9, mny = 1e9, mxy = -1e9, li = 0, i;
            for (i = 0; i < n; i++) { var p = pts[i]; if (i) len += Math.hypot(p.x - pts[i - 1].x, p.y - pts[i - 1].y); mnx = Math.min(mnx, p.x); mxx = Math.max(mxx, p.x); mny = Math.min(mny, p.y); mxy = Math.max(mxy, p.y); if (p.y > pts[li].y) li = i; }
            var lo = pts[li], a = pts[0], z = pts[n - 1];
            return len > 1.1 * H && (mxx - mnx) > .6 * H && (mxy - mny) > .3 * H && li > n * .1 && li < n * .9 && a.y < lo.y - .15 * H && z.y < lo.y - .3 * H && z.x > lo.x + .25 * H && a.x < lo.x;
        }
        rehber(false);
        surukle(cv, {
            down: function (e) { if (fin) return; on = true; pts = [nok(e)]; rehber(false); $('.gy-ph', c.s).style.opacity = 0; },
            move: function (e) { if (fin || !on) return; pts.push(nok(e)); rehber(false); ciz(); },
            up: function () { if (fin) return; on = false; if (gecerli()) { fin = true; rehber(true); c.bitti(); } else { sars(cv); setTimeout(function () { pts = []; rehber(false); $('.gy-ph', c.s).style.opacity = ''; }, 320); } }
        });
    };
    KUR.parmak = function (c) {
        var C = 2 * Math.PI * 52;
        c.s.innerHTML = '<div class="gy-halka gy-pa"><svg viewBox="0 0 120 120"><circle class="t" cx="60" cy="60" r="52"/><circle class="p" cx="60" cy="60" r="52" stroke-dasharray="' + C + '" stroke-dashoffset="' + C + '"/></svg><button type="button" class="gy-btn" aria-label="Parmak izi ile onayla">' + ikon('fingerprint') + '</button></div>';
        c.ip.textContent = c.on + 'parmak izi alanına dokunun';
        var b = $('.gy-btn', c.s), p = $('.p', c.s), bas = false;
        b.addEventListener('click', function () {
            if (bas) return; bas = true;
            p.style.transition = 'stroke-dashoffset .7s ease-out'; p.style.strokeDashoffset = 0;
            b.classList.add('gy-oku');
            setTimeout(function () { b.innerHTML = ikon('check'); c.bitti(); }, 720);
        });
    };
    KUR.cift = function (c) {
        var C = 2 * Math.PI * 72;
        c.s.innerHTML = '<div class="gy-cift" style="--c:' + C + '"><svg viewBox="0 0 160 160"><circle cx="80" cy="80" r="72" stroke-dasharray="' + C + '"/></svg><button type="button" class="gy-btn gy-yaz">' + (c.giris ? 'Giriş yap' : 'Çıkış yap') + '</button></div>';
        c.ip.textContent = c.on + 'iki kez dokunun';
        var w = $('.gy-cift', c.s), b = $('.gy-btn', c.s), arm = false, to = 0, fin = false;
        b.addEventListener('click', function () {
            if (fin) return;
            if (!arm) { arm = true; w.classList.remove('gy-arm'); void w.offsetWidth; w.classList.add('gy-arm'); b.textContent = 'Tekrar dokun'; to = setTimeout(function () { arm = false; w.classList.remove('gy-arm'); b.textContent = c.giris ? 'Giriş yap' : 'Çıkış yap'; }, 1400); }
            else { clearTimeout(to); fin = true; w.classList.remove('gy-arm'); b.innerHTML = ikon('check'); c.bitti(); }
        });
    };

    function kur(kok, y, sinif, tamam) {
        if (!KUR[y]) return false;
        var giris = sinif !== 'cikis';
        kok.innerHTML = '<div class="gc-kay gc-yo gc-yo-' + y + ' ' + (giris ? 'giris' : 'cikis') + '"><div class="gc-yo-sahne"></div><div class="gc-yo-ipucu"></div></div>';
        var k = $('.gc-kay', kok), c = {
            s: $('.gc-yo-sahne', k), ip: $('.gc-yo-ipucu', k), giris: giris, on: giris ? 'İşe başlamak için ' : 'Çıkış yapmak için ',
            bitti: function () {
                k.classList.add('gc-yo-bitti');
                setTimeout(function () {
                    tamam();
                    // işlem iptal edilirse (PIN penceresi kapatıldı vb.) bileşen kullanılabilir kalsın
                    setTimeout(function () { if (document.body.contains(k) && kok.contains(k)) kur(kok, y, sinif, tamam); }, 800);
                }, 350);
            }
        };
        KUR[y](c);
        return true;
    }
    window.GCY = { liste: LISTE, kur: kur, ad: function (k) { for (var i = 0; i < LISTE.length; i++) if (LISTE[i].k === k) return LISTE[i].ad; return k; } };
})();
