/* giris-efekt.js — Giriş sayfası arka plan efektleri (canvas): aurora · parcacik · dalga · izgara · yok
   Fareyi/dokunmayı izler, sekme gizliyken durur, "hareketi azalt" tercihine uyar. window.GIRIS.efekt/renk1/renk2 kullanır. */
(function () {
    'use strict';
    var G = window.GIRIS || {}, cv = document.getElementById('g-efekt');
    if (!cv || G.efekt === 'yok') { if (cv) cv.style.display = 'none'; return; }
    var cx = cv.getContext('2d'), W = 0, H = 0, dpr = Math.min(window.devicePixelRatio || 1, 2), fare = { x: -9999, y: -9999, hedefX: 0, hedefY: 0 }, t0 = performance.now(), calisiyor = true;
    var azalt = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    function rgb(h) { h = String(h || '#6366f1').replace('#', ''); return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)]; }
    var C1 = rgb(G.renk1), C2 = rgb(G.renk2);
    function rgba(c, a) { return 'rgba(' + c[0] + ',' + c[1] + ',' + c[2] + ',' + a + ')'; }
    function koyu() { var t = document.documentElement.getAttribute('data-tema'); if (t) return t === 'dark'; return !(window.matchMedia && matchMedia('(prefers-color-scheme: light)').matches); }
    function boyut() { W = innerWidth; H = innerHeight; cv.width = W * dpr; cv.height = H * dpr; cx.setTransform(dpr, 0, 0, dpr, 0, 0); kur(); }
    addEventListener('resize', boyut);
    addEventListener('pointermove', function (e) { fare.hedefX = e.clientX; fare.hedefY = e.clientY; if (fare.x < -999) { fare.x = e.clientX; fare.y = e.clientY; } }, { passive: true });
    addEventListener('pointerleave', function () { fare.hedefX = fare.hedefY = -9999; });
    document.addEventListener('visibilitychange', function () { calisiyor = !document.hidden; if (calisiyor) { t0 = performance.now() - t0x; dongu(); } });
    var t0x = 0;

    // ── Aurora: yavaşça süzülen bulanık renk kürecikleri ──
    var kure = [];
    function aurora(t) {
        var d = koyu();
        cx.globalCompositeOperation = 'source-over'; cx.clearRect(0, 0, W, H);
        cx.globalCompositeOperation = d ? 'lighter' : 'source-over';
        kure.forEach(function (k, i) {
            var x = W * (k.x + Math.sin(t * k.hx + i) * k.ax) + (fare.x > -999 ? (fare.x - W / 2) * k.par : 0);
            var y = H * (k.y + Math.cos(t * k.hy + i * 2) * k.ay) + (fare.y > -999 ? (fare.y - H / 2) * k.par : 0);
            var r = Math.max(W, H) * k.r, g = cx.createRadialGradient(x, y, 0, x, y, r);
            g.addColorStop(0, rgba(k.c, d ? .38 : .30)); g.addColorStop(1, rgba(k.c, 0));
            cx.fillStyle = g; cx.fillRect(0, 0, W, H);
        });
    }
    function aurora_kur() {
        kure = [];
        var renkler = [C1, C2, [168, 85, 247], C1, C2, [236, 72, 153]];
        for (var i = 0; i < 6; i++) kure.push({ x: Math.random(), y: Math.random(), ax: .08 + Math.random() * .12, ay: .08 + Math.random() * .12, hx: .00012 + Math.random() * .00022, hy: .0001 + Math.random() * .0002, r: .28 + Math.random() * .22, par: .02 + Math.random() * .05, c: renkler[i] });
    }

    // ── Parçacıklar: birbirine bağlanan noktalar, fare iter ──
    var nok = [];
    function parcacik_kur() { nok = []; var n = Math.min(120, Math.round(W * H / 14000)); for (var i = 0; i < n; i++) nok.push({ x: Math.random() * W, y: Math.random() * H, vx: (Math.random() - .5) * .35, vy: (Math.random() - .5) * .35, r: 1 + Math.random() * 1.8, c: Math.random() < .5 ? C1 : C2 }); }
    function parcacik() {
        cx.clearRect(0, 0, W, H);
        var d = koyu(), mesafe = 130, i, j, a, b, dx, dy, m;
        for (i = 0; i < nok.length; i++) {
            a = nok[i]; a.x += a.vx; a.y += a.vy;
            if (a.x < -10) a.x = W + 10; else if (a.x > W + 10) a.x = -10; if (a.y < -10) a.y = H + 10; else if (a.y > H + 10) a.y = -10;
            dx = a.x - fare.x; dy = a.y - fare.y; m = dx * dx + dy * dy;
            if (m < 20000 && m > 1) { var f = (1 - m / 20000) * 1.4, q = Math.sqrt(m); a.x += dx / q * f; a.y += dy / q * f; }
            cx.beginPath(); cx.arc(a.x, a.y, a.r, 0, 6.2832); cx.fillStyle = rgba(a.c, d ? .9 : .7); cx.fill();
            for (j = i + 1; j < nok.length; j++) {
                b = nok[j]; dx = a.x - b.x; dy = a.y - b.y; m = dx * dx + dy * dy;
                if (m < mesafe * mesafe) { cx.strokeStyle = rgba(a.c, (1 - Math.sqrt(m) / mesafe) * (d ? .35 : .28)); cx.lineWidth = 1; cx.beginPath(); cx.moveTo(a.x, a.y); cx.lineTo(b.x, b.y); cx.stroke(); }
            }
            if (fare.x > -999) { dx = a.x - fare.x; dy = a.y - fare.y; m = dx * dx + dy * dy; if (m < 25000) { cx.strokeStyle = rgba(C2, (1 - m / 25000) * .5); cx.beginPath(); cx.moveTo(a.x, a.y); cx.lineTo(fare.x, fare.y); cx.stroke(); } }
        }
    }

    // ── Dalga: üst üste akan sinüs katmanları ──
    function dalga(t) {
        cx.clearRect(0, 0, W, H);
        var d = koyu(), katman = 5, i, x, y;
        var yb = fare.y > -999 ? (fare.y / H - .5) * 40 : 0;
        for (i = 0; i < katman; i++) {
            var c = i % 2 ? C2 : C1, taban = H * (.55 + i * .08) + yb * (i + 1) / katman, genlik = 30 + i * 14, hiz = .0004 + i * .00018, dal = .0035 - i * .0003;
            cx.beginPath(); cx.moveTo(0, H);
            for (x = 0; x <= W + 8; x += 8) { y = taban + Math.sin(x * dal + t * hiz * 6 + i) * genlik + Math.sin(x * dal * 2.3 - t * hiz * 4) * genlik * .4; cx.lineTo(x, y); }
            cx.lineTo(W, H); cx.closePath();
            var g = cx.createLinearGradient(0, taban - genlik, 0, H); g.addColorStop(0, rgba(c, d ? .26 - i * .03 : .22 - i * .025)); g.addColorStop(1, rgba(c, 0)); cx.fillStyle = g; cx.fill();
        }
        var g2 = cx.createRadialGradient(W * .2, H * .15, 0, W * .2, H * .15, Math.max(W, H) * .5); g2.addColorStop(0, rgba(C1, d ? .22 : .16)); g2.addColorStop(1, rgba(C1, 0)); cx.fillStyle = g2; cx.fillRect(0, 0, W, H);
    }

    // ── Izgara: nokta ızgarası + fare ışığı + akan ışık çizgileri ──
    var akan = [];
    function izgara_kur() { akan = []; for (var i = 0; i < 7; i++) akan.push({ yatay: Math.random() < .5, p: Math.floor(Math.random() * 40), s: Math.random(), v: .0006 + Math.random() * .0012, c: Math.random() < .5 ? C1 : C2 }); }
    function izgara() {
        cx.clearRect(0, 0, W, H);
        var d = koyu(), ad = 38, x, y, dx, dy, m, k, r;
        for (x = ad / 2; x < W; x += ad) for (y = ad / 2; y < H; y += ad) {
            dx = x - fare.x; dy = y - fare.y; m = Math.sqrt(dx * dx + dy * dy); k = m < 190 ? 1 - m / 190 : 0; r = 1 + k * 2.6;
            cx.fillStyle = k > 0 ? rgba(C2, .25 + k * .7) : (d ? 'rgba(255,255,255,.14)' : 'rgba(15,23,42,.16)'); cx.beginPath(); cx.arc(x, y, r, 0, 6.2832); cx.fill();
        }
        akan.forEach(function (a) {
            a.s += a.v; if (a.s > 1.2) { a.s = -.2; a.p = Math.floor(Math.random() * 40); a.c = Math.random() < .5 ? C1 : C2; }
            var kon = a.p * ad + ad / 2, len = 220, bas = a.s * (a.yatay ? W : H), g;
            if (a.yatay) { g = cx.createLinearGradient(bas - len, 0, bas, 0); } else { g = cx.createLinearGradient(0, bas - len, 0, bas); }
            g.addColorStop(0, rgba(a.c, 0)); g.addColorStop(1, rgba(a.c, .85)); cx.strokeStyle = g; cx.lineWidth = 2; cx.beginPath();
            if (a.yatay) { cx.moveTo(bas - len, kon); cx.lineTo(bas, kon); } else { cx.moveTo(kon, bas - len); cx.lineTo(kon, bas); } cx.stroke();
        });
    }

    function kur() {
        if (G.efekt === 'parcacik') parcacik_kur(); else if (G.efekt === 'izgara') izgara_kur(); else if (G.efekt === 'aurora') aurora_kur();
    }
    function kare(zaman) {
        var t = zaman - t0; t0x = t;
        fare.x += (fare.hedefX - fare.x) * .08; fare.y += (fare.hedefY - fare.y) * .08;
        if (fare.hedefX < -999) { fare.x = fare.y = -9999; }
        if (G.efekt === 'parcacik') parcacik(); else if (G.efekt === 'dalga') dalga(t); else if (G.efekt === 'izgara') izgara(); else aurora(t);
    }
    function dongu() { if (!calisiyor) return; kare(performance.now()); if (!azalt) requestAnimationFrame(dongu); }
    boyut();
    if (azalt) { t0 = performance.now(); kare(t0 + 4000); } else dongu();

    // Kartı fareye göre hafifçe eğ
    var kart = document.getElementById('g-kart');
    if (kart && !azalt && matchMedia('(hover: hover)').matches) {
        addEventListener('pointermove', function (e) {
            var r = kart.getBoundingClientRect(), x = (e.clientX - (r.left + r.width / 2)) / innerWidth, y = (e.clientY - (r.top + r.height / 2)) / innerHeight;
            kart.style.transform = 'rotateY(' + (x * 6).toFixed(2) + 'deg) rotateX(' + (-y * 6).toFixed(2) + 'deg)';
        }, { passive: true });
        document.addEventListener('mouseleave', function () { kart.style.transform = ''; });
    }
})();
