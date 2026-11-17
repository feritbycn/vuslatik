/* giris-intro.js — Tanıtım animasyonu (video yerine tamamen kodla çizilir: ~5 KB, ağ trafiği yok).
   Yalnızca ilk ziyarette oynar; “Atla”, tıklama ya da Esc ile geçilir. ?intro=1 ile yeniden izlenir. */
(function () {
    'use strict';
    var G = window.GIRIS || {}; if (!G.intro) return;
    var ANAHTAR = 'tb_intro_v1', zorla = /[?&]intro=1\b/.test(location.search);
    try { if (!zorla && localStorage.getItem(ANAHTAR)) return; } catch (e) {}
    if (!zorla && window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    var SURE = 5200, t0 = 0, bitti = false, raf = 0;
    var css = document.createElement('style');
    css.textContent = '#tbi{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;overflow:hidden;background:radial-gradient(120% 90% at 50% 40%,color-mix(in srgb,' + G.renk1 + ' 38%,#05060f),#04050c 70%);color:#fff;font-family:inherit;transition:opacity .7s ease,transform .7s ease}' +
        '#tbi.git{opacity:0;transform:scale(1.04);pointer-events:none}#tbi canvas{position:absolute;inset:0;width:100%;height:100%}' +
        '#tbi .m{position:relative;display:grid;justify-items:center;gap:14px;text-align:center;padding:24px}' +
        '#tbi .l{width:92px;height:92px;border-radius:26px;display:grid;place-items:center;font-size:40px;font-weight:800;background:linear-gradient(135deg,' + G.renk1 + ',' + G.renk2 + ');box-shadow:0 0 0 0 ' + G.renk1 + '88,0 20px 80px ' + G.renk1 + '99;opacity:0;transform:scale(.3) rotate(-18deg);animation:tbiL .9s 1.7s cubic-bezier(.2,1.5,.4,1) forwards,tbiP 2.4s 2.6s ease-out infinite;overflow:hidden}' +
        '#tbi .l img{width:100%;height:100%;object-fit:contain;background:#fff;padding:10px;box-sizing:border-box}' +
        '#tbi h1{margin:0;font-size:clamp(26px,5.5vw,44px);letter-spacing:-.02em;font-weight:800;display:flex;flex-wrap:wrap;justify-content:center}' +
        '#tbi h1 span{opacity:0;transform:translateY(22px);filter:blur(8px);animation:tbiH .6s forwards}' +
        '#tbi p{margin:0;max-width:34ch;font-size:clamp(14px,2.4vw,17px);opacity:0;color:#cbd5e1;animation:tbiF .8s 3.2s forwards}' +
        '#tbi .c{display:flex;gap:10px;flex-wrap:wrap;justify-content:center;margin-top:4px}' +
        '#tbi .c b{font-size:13px;font-weight:600;padding:7px 14px;border-radius:999px;background:#ffffff14;border:1px solid #ffffff26;backdrop-filter:blur(6px);opacity:0;transform:translateY(14px);animation:tbiF .6s forwards}' +
        '#tbi button{position:absolute;right:18px;bottom:18px;border:1px solid #ffffff30;background:#ffffff12;color:#fff;padding:9px 16px;border-radius:999px;font:600 13px inherit;cursor:pointer;backdrop-filter:blur(6px)}#tbi button:hover{background:#ffffff26}' +
        '#tbi .i{position:absolute;left:0;bottom:0;height:3px;width:100%;background:linear-gradient(90deg,' + G.renk1 + ',' + G.renk2 + ');transform-origin:left;transform:scaleX(0)}' +
        '@keyframes tbiL{to{opacity:1;transform:none}}@keyframes tbiH{to{opacity:1;transform:none;filter:none}}@keyframes tbiF{to{opacity:1;transform:none}}' +
        '@keyframes tbiP{0%{box-shadow:0 0 0 0 ' + G.renk1 + '88,0 20px 80px ' + G.renk1 + '99}100%{box-shadow:0 0 0 38px ' + G.renk1 + '00,0 20px 80px ' + G.renk1 + '99}}';
    document.head.appendChild(css);
    var ad = String(G.ad || 'Turkuaz Bileşim'), harfler = ad.split('').map(function (h, i) { return '<span style="animation-delay:' + (2.2 + i * .045).toFixed(2) + 's">' + (h === ' ' ? '&nbsp;' : h.replace(/[<>&"]/g, '')) + '</span>'; }).join('');
    var logo = G.logo ? '<img src="' + String(G.logo).replace(/"/g, '') + '" alt="">' : ad.charAt(0).toLocaleUpperCase('tr-TR');
    var el = document.createElement('div'); el.id = 'tbi'; el.setAttribute('role', 'presentation');
    el.innerHTML = '<canvas></canvas><div class="m"><div class="l">' + logo + '</div><h1>' + harfler + '</h1><p>' + String(G.slogan || '').replace(/[<>&]/g, '') + '</p><div class="c">' +
        ['İzin onayı', 'Giriş-çıkış', 'Bildirimler', 'Tek panel'].map(function (x, i) { return '<b style="animation-delay:' + (3.5 + i * .18).toFixed(2) + 's">' + x + '</b>'; }).join('') + '</div></div><div class="i"></div><button type="button">Atla <span aria-hidden="true">→</span></button>';
    document.body.appendChild(el);
    var cv = el.querySelector('canvas'), cx = cv.getContext('2d'), W = 0, H = 0, D = Math.min(2, window.devicePixelRatio || 1), P = [], bar = el.querySelector('.i');
    function boyut() { W = cv.width = innerWidth * D; H = cv.height = innerHeight * D; } boyut(); addEventListener('resize', boyut);
    for (var i = 0; i < 150; i++) { var a = Math.random() * 6.283, r = .5 + Math.random() * .6; P.push({ a: a, r: r, h: .6 + Math.random() * 1.6, k: Math.random() < .5 ? G.renk1 : G.renk2, s: 1 + Math.random() * 2.2 }); }
    function kare(ts) {
        if (!t0) t0 = ts; var t = ts - t0, e = Math.min(1, t / 1700), s = Math.min(W, H), cx0 = W / 2, cy0 = H * .46;
        cx.clearRect(0, 0, W, H); cx.globalCompositeOperation = 'lighter';
        for (var i = 0; i < P.length; i++) {
            var p = P[i], ease = 1 - Math.pow(1 - e, 3), ang = p.a + (1 - ease) * 3.2 * p.h, rad = (p.r * s * .75) * (1 - ease) + (t > 1700 ? Math.sin((t - 1700) / 380 + i) * 6 * D + s * .1 * Math.min(1, (t - 1700) / 1800) : 6 * D);
            var x = cx0 + Math.cos(ang) * rad, y = cy0 + Math.sin(ang) * rad * .72;
            cx.beginPath(); cx.fillStyle = p.k; cx.globalAlpha = Math.min(1, .25 + ease) * (t > 3800 ? Math.max(.2, 1 - (t - 3800) / 1800) : 1); cx.arc(x, y, p.s * D, 0, 6.283); cx.fill();
        }
        if (t > 1500 && t < 2400) { var f = 1 - (t - 1500) / 900; cx.globalAlpha = f * .5; cx.beginPath(); cx.arc(cx0, cy0, s * .5 * (1 - f) + 10, 0, 6.283); cx.lineWidth = 3 * D; cx.strokeStyle = '#fff'; cx.stroke(); }
        cx.globalAlpha = 1; cx.globalCompositeOperation = 'source-over';
        bar.style.transform = 'scaleX(' + Math.min(1, t / SURE) + ')';
        if (t >= SURE) return kapat(); raf = requestAnimationFrame(kare);
    }
    function kapat() {
        if (bitti) return; bitti = true; cancelAnimationFrame(raf); el.classList.add('git'); removeEventListener('resize', boyut); document.removeEventListener('keydown', tus);
        try { localStorage.setItem(ANAHTAR, '1'); } catch (e) {}
        setTimeout(function () { el.remove(); css.remove(); var u = document.getElementById('g-kullanici'); if (u && !/Mobi|Android/i.test(navigator.userAgent)) u.focus(); }, 750);
    }
    function tus(e) { if (e.key === 'Escape' || e.key === 'Enter' || e.key === ' ') kapat(); }
    el.addEventListener('click', kapat); document.addEventListener('keydown', tus);
    try { localStorage.setItem(ANAHTAR, '1'); } catch (e) {}
    raf = requestAnimationFrame(kare);
})();
