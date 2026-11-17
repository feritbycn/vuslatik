/* ==========================================================================
   tb-ik-bildirim.js — İK bildirim istemcisi (her sayfada kullanılabilir, kendi kendine yeten)
   • Sayfa açıkken: ~20 sn'de bir yoklar → modern uyarı kartı + bildirim sesi + sekme başlığında sayaç
   • Sekme arka plandayken: tarayıcı bildirimi (Notification API) + ses
   • Site / tarayıcı kapalıyken: Web Push (ik-sw.js servis çalışanı) — "Bildirimleri aç" ile bir kez izin verilir
   Kullanım:  <script src="assets/js/tb-ik-bildirim.js" defer></script>   (ik.php ve giris_cikis.php zaten yükler;
              diğer sayfalarda da bildirim gelsin isterseniz header.php'ye bu tek satırı ekleyin)
   Dışa açılan: window.IKBildirim { on, liste, okundu, sesCal, sesAcikMi, sesAyarla, pushDurum, pushAc, pushKapat, sayi }
   ========================================================================== */
(function () {
    'use strict';
    if (window.IKBildirim) return;
    var API = 'ik_bildirim.php', SES_URL = 'assets/sound/ik-bildirim.mp3';
    var dinleyici = [], sonId = 0, okunmamis = 0, baslangicOldu = false, durdu = false, zamanlayici = null, hataSayac = 0, anaBaslik = document.title;

    function csrf() { return (window.TB && window.TB.csrf) || ''; }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function duz(h) { return String(h || '').replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/\s+/g, ' ').trim(); }
    function guvenliHtml(h) { return esc(duz(h)); } // toast gövdesi düz metin (XSS'e karşı etiketler atılır)
    function depo(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) {} return null; }
    function json(r) { return r.json().catch(function () { return { ok: false, hata: 'Yanıt okunamadı' }; }).then(function (j) { if (!j.ok) { var e = new Error(j.hata || 'Hata'); e.kod = r.status; throw e; } return j; }); }
    function al(p) { return fetch(API + '?' + new URLSearchParams(p), { credentials: 'same-origin', cache: 'no-store' }).then(json); }
    function yolla(islem, v) {
        var fd = new FormData(); fd.append('islem', islem); fd.append('_csrf', csrf());
        Object.keys(v || {}).forEach(function (k) { fd.append(k, v[k]); });
        return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf() } }).then(json);
    }
    function yay(olay) { dinleyici.forEach(function (f) { try { f(olay); } catch (e) {} }); }

    /* ---------------- Ses ---------------- */
    var audio = null, ctx = null;
    function sesAcikMi() { return depo('ikb_ses') !== '0'; }
    function sesAyarla(a) { depo('ikb_ses', a ? '1' : '0'); }
    function sesHazir() { if (!audio) { audio = new Audio(SES_URL); audio.preload = 'auto'; audio.volume = 0.85; } return audio; }
    function sentez() { // dosya çalınamazsa tarayıcıda üretilen iki notalı zil
        try {
            ctx = ctx || new (window.AudioContext || window.webkitAudioContext)();
            if (ctx.state === 'suspended') ctx.resume();
            [[987.77, 0], [1318.51, 0.13]].forEach(function (n) {
                var o = ctx.createOscillator(), g = ctx.createGain(), t = ctx.currentTime + n[1];
                o.type = 'sine'; o.frequency.value = n[0];
                g.gain.setValueAtTime(0.0001, t); g.gain.exponentialRampToValueAtTime(0.35, t + 0.01); g.gain.exponentialRampToValueAtTime(0.0001, t + 0.9);
                o.connect(g); g.connect(ctx.destination); o.start(t); o.stop(t + 1);
            });
        } catch (e) {}
    }
    function sesCal(zorla) {
        if (!zorla && !sesAcikMi()) return;
        try { var a = sesHazir(); a.currentTime = 0; var p = a.play(); if (p && p.catch) p.catch(sentez); } catch (e) { sentez(); }
    }
    // Tarayıcılar sesi kullanıcı etkileşiminden sonra serbest bırakır: ilk tıklamada sessizce "ısıtılır"
    function sesKilidiAc() {
        document.removeEventListener('pointerdown', sesKilidiAc, true); document.removeEventListener('keydown', sesKilidiAc, true);
        try { var a = sesHazir(); a.muted = true; var p = a.play(); if (p && p.then) p.then(function () { a.pause(); a.currentTime = 0; a.muted = false; }).catch(function () { a.muted = false; }); } catch (e) {}
        try { ctx = ctx || new (window.AudioContext || window.webkitAudioContext)(); if (ctx.state === 'suspended') ctx.resume(); } catch (e) {}
    }
    document.addEventListener('pointerdown', sesKilidiAc, true); document.addEventListener('keydown', sesKilidiAc, true);

    /* ---------------- Uyarı kartı (toast) ---------------- */
    function stilEkle() {
        if (document.getElementById('ikb-stil')) return;
        var s = document.createElement('style'); s.id = 'ikb-stil';
        s.textContent = '.ikb-yigin{position:fixed;right:18px;bottom:18px;z-index:2147483000;display:flex;flex-direction:column-reverse;gap:10px;width:min(380px,calc(100vw - 28px));pointer-events:none;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}' +
            '@media(max-width:560px){.ikb-yigin{right:14px;left:14px;bottom:auto;top:14px;flex-direction:column;width:auto}}' +
            '.ikb-kart{pointer-events:auto;position:relative;display:flex;gap:12px;align-items:flex-start;padding:14px 38px 16px 14px;border-radius:18px;background:var(--bg-surface,#fff);color:var(--text-primary,#0f172a);border:1px solid var(--border,rgba(15,23,42,.1));box-shadow:0 18px 50px rgba(15,23,42,.28),0 2px 8px rgba(15,23,42,.08);cursor:pointer;overflow:hidden;animation:ikbGir .38s cubic-bezier(.2,1.2,.35,1)}' +
            '.ikb-kart.cikis{animation:ikbCik .25s ease-in forwards}' +
            '@keyframes ikbGir{from{opacity:0;transform:translateX(40px) scale(.96)}to{opacity:1;transform:none}}@keyframes ikbCik{to{opacity:0;transform:translateX(40px) scale(.96)}}' +
            '@media(max-width:560px){@keyframes ikbGir{from{opacity:0;transform:translateY(-30px) scale(.96)}to{opacity:1;transform:none}}@keyframes ikbCik{to{opacity:0;transform:translateY(-30px)}}}' +
            '.ikb-ikon{flex:none;width:40px;height:40px;border-radius:13px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:17px;background:linear-gradient(135deg,#6366f1,#ec4899);box-shadow:0 8px 18px rgba(99,102,241,.35);animation:ikbSalla 1s .3s ease-in-out}' +
            '@keyframes ikbSalla{0%,100%{transform:rotate(0)}15%{transform:rotate(-14deg)}30%{transform:rotate(12deg)}45%{transform:rotate(-8deg)}60%{transform:rotate(6deg)}}' +
            '.ikb-ic{min-width:0;flex:1}.ikb-baslik{font-size:14px;font-weight:800;line-height:1.3;margin:1px 0 3px}.ikb-mesaj{font-size:13px;line-height:1.5;color:var(--text-secondary,#475569);display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}' +
            '.ikb-mesaj b{color:var(--text-primary,#0f172a)}.ikb-ac{margin-top:8px;font-size:12px;font-weight:700;color:var(--accent,#6366f1)}' +
            '.ikb-x{position:absolute;top:8px;right:8px;width:26px;height:26px;border-radius:9px;border:0;background:transparent;color:var(--text-muted,#94a3b8);cursor:pointer;font-size:13px}.ikb-x:hover{background:var(--bg-hover,rgba(0,0,0,.06))}' +
            '.ikb-cubuk{position:absolute;left:0;bottom:0;height:3px;background:linear-gradient(90deg,#6366f1,#ec4899);width:100%;transform-origin:left;animation:ikbCubuk linear forwards}@keyframes ikbCubuk{to{transform:scaleX(0)}}' +
            '.ikb-kart:hover .ikb-cubuk{animation-play-state:paused}';
        document.head.appendChild(s);
    }
    function yiginAl() { var y = document.querySelector('.ikb-yigin'); if (!y) { stilEkle(); y = document.createElement('div'); y.className = 'ikb-yigin'; y.setAttribute('role', 'status'); y.setAttribute('aria-live', 'polite'); document.body.appendChild(y); } return y; }
    function toast(b) {
        var y = yiginAl(), el = document.createElement('div'), sure = 9000;
        el.className = 'ikb-kart';
        var ilkHarf = String(b.baslik || '').trim();
        el.innerHTML = '<div class="ikb-ikon"><i class="fa-solid fa-bell"></i></div><div class="ikb-ic"><div class="ikb-baslik">' + esc(ilkHarf) + '</div><div class="ikb-mesaj">' + guvenliHtml(b.mesaj) + '</div>' + (b.link ? '<div class="ikb-ac">Aç →</div>' : '') + '</div>' +
            '<button type="button" class="ikb-x" aria-label="Kapat">✕</button><div class="ikb-cubuk" style="animation-duration:' + sure + 'ms"></div>';
        y.appendChild(el);
        while (y.children.length > 4) y.removeChild(y.firstChild);
        var kapat = function () { if (el.classList.contains('cikis')) return; el.classList.add('cikis'); setTimeout(function () { el.remove(); }, 260); };
        var t = setTimeout(kapat, sure);
        el.addEventListener('mouseenter', function () { clearTimeout(t); }); el.addEventListener('mouseleave', function () { t = setTimeout(kapat, 2500); });
        el.querySelector('.ikb-x').addEventListener('click', function (e) { e.stopPropagation(); kapat(); });
        el.addEventListener('click', function () { kapat(); okundu(b.id); git(b.link); });
    }
    function git(link) {
        if (!link) return;
        if (window.IKM && typeof window.IKM.git === 'function' && window.IKM.git(link)) return;
        location.href = link;
    }

    /* ---------------- Tarayıcı bildirimi (sekme arka plandayken) ---------------- */
    function tarayiciBildirimi(b) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;
        try {
            var n = new Notification(duz(b.baslik) || 'İnsan Kaynakları', { body: duz(b.mesaj), icon: 'assets/img/ik-bildirim-ikon.png', badge: 'assets/img/ik-bildirim-rozet.png', tag: 'ik-' + b.id, renotify: true });
            n.onclick = function () { window.focus(); n.close(); okundu(b.id); git(b.link); };
        } catch (e) {}
    }

    /* ---------------- Sekme başlığı ---------------- */
    function baslikGuncelle() {
        var temiz = anaBaslik.replace(/^\(\d+\)\s*/, '');
        document.title = okunmamis > 0 ? '(' + okunmamis + ') ' + temiz : temiz;
    }
    new MutationObserver(function () { if (!/^\(\d+\)\s/.test(document.title) || okunmamis === 0) { var t = document.title.replace(/^\(\d+\)\s*/, ''); if (t !== anaBaslik) { anaBaslik = t; baslikGuncelle(); } } }).observe(document.querySelector('title') || document.head, { childList: true, subtree: true, characterData: true });

    /* ---------------- Yoklama ---------------- */
    function isle(liste) {
        var islendi = parseInt(depo('ikb_islendi') || '0', 10) || 0, yeniler = liste.filter(function (b) { return b.id > islendi; });
        if (!yeniler.length) return;
        depo('ikb_islendi', String(Math.max.apply(null, yeniler.map(function (b) { return b.id; }))));
        var gizli = document.hidden;
        sesCal();
        yeniler.slice(-3).forEach(function (b) { if (gizli) tarayiciBildirimi(b); else toast(b); });
    }
    function yokla() {
        clearTimeout(zamanlayici);
        if (durdu) return;
        al({ islem: 'son', after: sonId }).then(function (j) {
            hataSayac = 0;
            var ilk = !baslangicOldu; baslangicOldu = true;
            if (ilk) { var islendi = parseInt(depo('ikb_islendi') || '0', 10) || 0; if (j.son_id > islendi) depo('ikb_islendi', String(j.son_id)); }
            sonId = Math.max(sonId, j.son_id);
            if (j.items && j.items.length) { isle(j.items); yay({ tip: 'yeni', items: j.items }); }
            if (j.okunmamis !== okunmamis) { okunmamis = j.okunmamis; baslikGuncelle(); yay({ tip: 'sayi', okunmamis: okunmamis }); }
        }).catch(function (e) { hataSayac++; if (e && e.kod === 401) durdu = true; }).then(function () {
            if (durdu) return;
            var bekle = document.hidden ? 45000 : 20000;
            zamanlayici = setTimeout(yokla, Math.min(180000, bekle * Math.pow(2, Math.min(hataSayac, 3))));
        });
    }
    document.addEventListener('visibilitychange', function () { if (!document.hidden) yokla(); });
    window.addEventListener('storage', function (e) { if (e.key === 'ikb_islendi') yokla(); });

    /* ---------------- Dışa açık işlevler ---------------- */
    function liste() { return al({ islem: 'liste' }).then(function (j) { okunmamis = j.okunmamis; baslikGuncelle(); return j; }); }
    function okundu(id) {
        var p = id ? { id: id } : { hepsi: 1 };
        return yolla('okundu', p).then(function (j) { okunmamis = j.okunmamis; baslikGuncelle(); yay({ tip: 'sayi', okunmamis: okunmamis }); return j; }).catch(function () {});
    }

    /* ---------------- Web Push ---------------- */
    function b64Dizi(s) { var p = '='.repeat((4 - s.length % 4) % 4), b = (s + p).replace(/-/g, '+').replace(/_/g, '/'), r = atob(b), d = new Uint8Array(r.length); for (var i = 0; i < r.length; i++) d[i] = r.charCodeAt(i); return d; }
    function pushDestek() { return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window; }
    function pushDurum() {
        if (!pushDestek()) return Promise.resolve('desteklenmiyor');
        if (Notification.permission === 'denied') return Promise.resolve('engelli');
        if (Notification.permission !== 'granted') return Promise.resolve('kapali');
        return navigator.serviceWorker.getRegistration().then(function (r) { return r ? r.pushManager.getSubscription() : null; }).then(function (s) { return s ? 'acik' : 'kapali'; }).catch(function () { return 'kapali'; });
    }
    function pushKaydet(sessiz) {
        return navigator.serviceWorker.register(new URL('ik-sw.js', document.baseURI).href).then(function () { return navigator.serviceWorker.ready; }).then(function (reg) {
            return al({ islem: 'vapid' }).then(function (v) {
                var anahtar = b64Dizi(v.anahtar);
                return reg.pushManager.getSubscription().then(function (sub) {
                    if (sub && sub.options && sub.options.applicationServerKey) {
                        var m = new Uint8Array(sub.options.applicationServerKey), ayni = m.length === anahtar.length && m.every(function (x, i) { return x === anahtar[i]; });
                        if (!ayni) return sub.unsubscribe().then(function () { return null; });
                    }
                    return sub;
                }).then(function (sub) { return sub || reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: anahtar }); });
            });
        }).then(function (sub) { var j = sub.toJSON(); return yolla('abone', { endpoint: j.endpoint, p256dh: (j.keys || {}).p256dh || '', auth: (j.keys || {}).auth || '' }); });
    }
    function pushAc() {
        if (!pushDestek()) return Promise.reject(new Error('Bu tarayıcı anlık bildirimi desteklemiyor. iPhone/iPad için siteyi önce “Ana Ekrana Ekle” ile yükleyin.'));
        return Notification.requestPermission().then(function (izin) {
            if (izin !== 'granted') throw new Error(izin === 'denied' ? 'Bildirimler tarayıcıdan engellenmiş. Adres çubuğundaki kilit simgesinden izin verin.' : 'Bildirim izni verilmedi.');
            return pushKaydet();
        }).then(function () { return 'acik'; });
    }
    function pushKapat() {
        return navigator.serviceWorker.getRegistration().then(function (r) { return r ? r.pushManager.getSubscription() : null; }).then(function (s) {
            if (!s) return; var ep = s.endpoint; return s.unsubscribe().then(function () { return yolla('abone_sil', { endpoint: ep }); });
        });
    }
    // Servis çalışanından gelen "yeni bildirim var" mesajı (el sıkışma: sayfa uyarıyı kendisi verecek)
    if ('serviceWorker' in navigator) navigator.serviceWorker.addEventListener('message', function (e) {
        if (e.data && e.data.tip === 'ik-yeni') { if (e.ports && e.ports[0]) e.ports[0].postMessage('ok'); yokla(); }
    });

    window.IKBildirim = { on: function (f) { dinleyici.push(f); }, liste: liste, okundu: okundu, sesCal: function () { sesCal(true); }, sesAcikMi: sesAcikMi, sesAyarla: sesAyarla,
        pushDurum: pushDurum, pushAc: pushAc, pushKapat: pushKapat, sayi: function () { return okunmamis; }, yokla: yokla, toast: toast };

    function basla() {
        yokla();
        // İzin daha önce verildiyse aboneliği sessizce tazele (anahtar değişimi, tarayıcı temizliği vb.)
        if (pushDestek() && 'Notification' in window && Notification.permission === 'granted' && !sessionStorage.getItem('ikb_push_tazelendi')) {
            try { sessionStorage.setItem('ikb_push_tazelendi', '1'); } catch (e) {}
            pushKaydet(true).catch(function () {});
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', basla); else basla();
})();
