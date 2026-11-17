/* ==========================================================================
   vuslat-giris-tam.js — Giriş-Çıkış kaydırma ekranını TAM EKRAN yapar (29.09.2026)
   • Sayfa açıldığında kişisel "Giriş-Çıkış" görünümü tüm ekranı kaplar (site menüsü / sohbet düğmesi arkada kalır)
   • Üstte selam + tarih + canlı saat; sağda: gerçek tam ekran (⛶), yönetim sekmeleri (İK için), küçült (✕)
   • Çalışırken arka plan yeşile döner; giriş / çıkış kaydedilince telefon kısa titreşir
   • "Küçült" tercihi bu cihazda hatırlanır; sağ alttaki "Tam ekran" düğmesiyle geri dönülür
   vuslat-giris.js'e dokunmaz: yalnızca sayfadaki öğeleri izler (MutationObserver).
   ========================================================================== */
(function () {
    'use strict';
    var ANAHTAR = 'gc_tam_ekran';
    function tercih() { try { return localStorage.getItem(ANAHTAR) !== '0'; } catch (e) { return true; } }
    function tercihYaz(v) { try { localStorage.setItem(ANAHTAR, v ? '1' : '0'); } catch (e) {} }
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    /* ---- Ana ekrana kısayol ---- */
    var yukleOlayi = null;
    window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); yukleOlayi = e; });
    function uygulamaMi() { return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true; }
    function kisayolGoster() { return !uygulamaMi(); }
    function kisayolPenceresi() {
        var ua = navigator.userAgent || '', ios = /iPhone|iPad|iPod/i.test(ua);
        var adim = ios
            ? ['Safari’de alttaki <b>Paylaş</b> düğmesine dokunun', '<b>Ana Ekrana Ekle</b>’yi seçin', '<b>Ekle</b>’ye dokunun']
            : ['Tarayıcı menüsünü açın (<b>⋮</b>)', '<b>Ana ekrana ekle</b> / <b>Uygulamayı yükle</b>’yi seçin', '<b>Ekle</b>’ye dokunun'];
        var k = document.createElement('div'); k.className = 'gct-modal';
        k.innerHTML = '<div class="gct-modal-kutu"><h3>Ana ekrana kısayol</h3><p>Giriş-çıkışı tek dokunuşla açmak için bu sayfayı ana ekranınıza ekleyin.</p><ol>' + adim.map(function (a) { return '<li>' + a + '</li>'; }).join('') + '</ol><button type="button" class="gct-modal-tamam">Tamam</button></div>';
        k.addEventListener('click', function (e) { if (e.target === k || e.target.classList.contains('gct-modal-tamam')) k.remove(); });
        document.body.appendChild(k);
    }
    // Sayfa açılınca hizmet işçisi kaydedilir: tarayıcı "uygulamayı yükle" penceresini ancak bundan sonra doğrudan açabilir
    try { if ('serviceWorker' in navigator) navigator.serviceWorker.register(new URL('ik-sw.js', document.baseURI).href).catch(function () {}); } catch (e) {}
    function kisayolEkle() {
        function ac() { var o = yukleOlayi; yukleOlayi = null; o.prompt(); if (o.userChoice) o.userChoice.then(function (c) { if (c && c.outcome === 'accepted') { var b = document.getElementById('gct-kisayol'); if (b) b.remove(); } }); }
        if (yukleOlayi) { ac(); return; }
        // Tarayıcı hazır olay göndermediyse kısa süre bekle, yine gelmezse adım adım yönergeyi göster
        var bekle = 0, t = setInterval(function () { bekle += 150; if (yukleOlayi) { clearInterval(t); ac(); } else if (bekle >= 1200) { clearInterval(t); kisayolPenceresi(); } }, 150);
    }

    function basla() {
        var sayfa = document.querySelector('.gc-sayfa'), icerik = document.getElementById('gc-icerik');
        if (!sayfa || !icerik) return;
        var sekmeler = document.getElementById('gc-sekmeler');
        var ad = String((window.GC && window.GC.ad) || '').trim().split(/\s+/)[0] || '';
        var ust = document.createElement('div'); ust.className = 'gct-ust';
        ust.innerHTML = '<div class="gct-selam"><small id="gct-tarih"></small><b id="gct-selam"></b></div><div class="gct-saat" id="gct-saat"></div>' +
            '<div class="gct-btnler">' + (kisayolGoster() ? '<button type="button" id="gct-kisayol" title="Ana ekrana kısayol ekle"><i class="fa-solid fa-mobile-screen-button"></i></button>' : '') +
            '<button type="button" id="gct-gecmis" title="Son kayıtlarım"><i class="fa-solid fa-clock-rotate-left"></i></button>' +
            (document.documentElement.requestFullscreen ? '<button type="button" id="gct-fs" title="Tam ekran"><i class="fa-solid fa-expand"></i></button>' : '') +
            ((sekmeler || (window.GC && window.GC.yetki && window.GC.yetki.gor)) ? '<button type="button" id="gct-yonet" title="Kayıtlar ve raporlar"><i class="fa-solid fa-table-list"></i></button>' : '') +
            '<button type="button" id="gct-kapat" title="Küçült"><i class="fa-solid fa-compress"></i></button></div>';
        sayfa.insertBefore(ust, sayfa.firstChild);
        var ac = document.createElement('button'); ac.type = 'button'; ac.className = 'gct-ac'; ac.innerHTML = '<i class="fa-solid fa-up-right-and-down-left-from-center"></i> Tam ekran';
        document.body.appendChild(ac);

        function aktifSekme() { var a = sekmeler && sekmeler.querySelector('button.aktif'); return a ? a.dataset.s : 'benim'; }
        function kisiselMi() { return aktifSekme() === 'benim'; }
        function uygula() {
            var tam = kisiselMi() && tercih();
            document.body.classList.toggle('gc-tam', tam);
            document.body.classList.toggle('gct-kucuk', kisiselMi() && !tam);
            var h = icerik.querySelector('.gc-halka-kap');
            document.body.classList.toggle('gc-calisiyor', !!(h && h.classList.contains('calisiyor')));
            boyutla();
        }
        /* Süre halkası, ekranda kalan boşluğa göre büyür/küçülür → sayfa hiç kaydırılmadan tek ekrana sığar */
        var sonBoy = 0;
        function dikey(el) { var c = getComputedStyle(el); if (c.display === 'none' || c.position === 'absolute' || c.position === 'fixed') return 0; return el.offsetHeight + (parseFloat(c.marginTop) || 0) + (parseFloat(c.marginBottom) || 0); }
        function boyutla() {
            var h = icerik.querySelector('.gc-halka-kap');
            if (!h || !document.body.classList.contains('gc-tam')) { if (h) h.style.removeProperty('--gct-halka'); sonBoy = 0; return; }
            var cs = getComputedStyle(sayfa), bos = sayfa.clientHeight - (parseFloat(cs.paddingTop) || 0) - (parseFloat(cs.paddingBottom) || 0);
            [].forEach.call(sayfa.children, function (c) { if (c !== icerik) bos -= dikey(c); });
            [].forEach.call(icerik.children, function (c) { if (c !== h) bos -= dikey(c); });
            bos -= 20; // halkanın üst/alt en az boşluğu
            var boy = Math.round(Math.max(130, Math.min(bos, sayfa.clientWidth * 0.78, 320)));
            if (Math.abs(boy - sonBoy) > 2) { sonBoy = boy; h.style.setProperty('--gct-halka', boy + 'px'); }
        }
        window.addEventListener('resize', function () { sonBoy = 0; boyutla(); });
        window.addEventListener('orientationchange', function () { setTimeout(function () { sonBoy = 0; boyutla(); }, 250); });
        var AYLAR = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'], GUNLER = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];
        function saat() {
            var G = window.GC_SUNUCU, p = { y: 0, mo: 0, d: 0, h: 0, mi: 0, w: 0 };
            if (!G) return;                                   // sunucu saati gelmeden telefon saati gösterilmez
            var t = new Date(Date.now() + G.fark), HG = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
            try {
                new Intl.DateTimeFormat('en-US', { timeZone: G.tz, hourCycle: 'h23', year: 'numeric', month: 'numeric', day: 'numeric', hour: 'numeric', minute: 'numeric', weekday: 'short' }).formatToParts(t).forEach(function (x) {
                    if (x.type === 'year') p.y = +x.value; else if (x.type === 'month') p.mo = +x.value; else if (x.type === 'day') p.d = +x.value; else if (x.type === 'hour') p.h = +x.value; else if (x.type === 'minute') p.mi = +x.value; else if (x.type === 'weekday') p.w = HG[x.value];
                });
            } catch (e) { p = { y: t.getUTCFullYear(), mo: t.getUTCMonth() + 1, d: t.getUTCDate(), h: (t.getUTCHours() + 3) % 24, mi: t.getUTCMinutes(), w: t.getUTCDay() }; }
            var s = p.h, selam = s < 6 ? 'İyi geceler' : s < 11 ? 'Günaydın' : s < 17 ? 'İyi günler' : s < 22 ? 'İyi akşamlar' : 'İyi geceler';
            document.getElementById('gct-selam').textContent = selam + (ad ? ', ' + ad : '') + ' 👋';
            document.getElementById('gct-tarih').textContent = p.d + ' ' + AYLAR[p.mo - 1] + ' ' + p.y + ', ' + GUNLER[p.w];
            document.getElementById('gct-saat').textContent = ('0' + s).slice(-2) + ':' + ('0' + p.mi).slice(-2);
        }
        window.addEventListener('gc-sunucu-saat', saat);
        saat(); setInterval(saat, 1000 * 5);

        var sonGoruldu = false;
        new MutationObserver(function () {
            uygula();
            var son = icerik.querySelector('.gc-son .gc-sonuc-ikon.ok');
            if (son && !sonGoruldu) { sonGoruldu = true; try { if (navigator.vibrate) navigator.vibrate([35, 45, 70]); } catch (e) {} }
            if (!son) sonGoruldu = false;
        }).observe(icerik, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
        if (sekmeler) sekmeler.addEventListener('click', function () { setTimeout(uygula, 0); });

        document.getElementById('gct-kapat').onclick = function () { tercihYaz(false); if (document.fullscreenElement) document.exitFullscreen().catch(function () {}); uygula(); };
        ac.onclick = function () { tercihYaz(true); uygula(); };
        var fs = document.getElementById('gct-fs');
        if (fs) fs.onclick = function () {
            if (document.fullscreenElement) document.exitFullscreen().catch(function () {});
            else document.documentElement.requestFullscreen().catch(function () {});
        };
        document.addEventListener('fullscreenchange', function () { if (fs) fs.innerHTML = '<i class="fa-solid ' + (document.fullscreenElement ? 'fa-compress' : 'fa-expand') + '"></i>'; });
        // Geçmiş kayıtlar: tam ekrandan çıkmadan, küçük bir açılır pencerede gösterilir
        document.getElementById('gct-gecmis').onclick = function () {
            var eski = document.getElementById('gcm-perde'); if (eski) eski.remove();
            var kaynak = icerik.querySelector('.gc-gecmis') || icerik.querySelector('.gc-alt-baslik ~ .gc-bos');
            var perde = document.createElement('div'); perde.id = 'gcm-perde';
            perde.innerHTML = '<div class="gcm-sheet" role="dialog" aria-label="Son kayıtlarım"><div class="gcm-ust"><b>Son kayıtlarım</b><button type="button" class="gcm-kapat" aria-label="Kapat"><i class="fa-solid fa-xmark"></i></button></div><div class="gcm-ic"></div></div>';
            var ic = perde.querySelector('.gcm-ic');
            if (kaynak) ic.appendChild(kaynak.cloneNode(true)); else ic.innerHTML = '<div class="gcm-bos">Henüz kayıt yok.</div>';
            function kapat() { perde.remove(); document.removeEventListener('keydown', esc); }
            function esc(e) { if (e.key === 'Escape') kapat(); }
            perde.onclick = function (e) { if (e.target === perde) kapat(); };
            perde.querySelector('.gcm-kapat').onclick = kapat;
            document.addEventListener('keydown', esc);
            document.body.appendChild(perde);
        };
        var ks = document.getElementById('gct-kisayol'); if (ks) ks.onclick = kisayolEkle;
        var yon = document.getElementById('gct-yonet');
        if (yon) yon.onclick = function () { if (!sekmeler) { location.href = 'ik.php?sekme=gc_bugun'; return; } var b = sekmeler.querySelector('[data-s="bugun"]') || sekmeler.querySelector('button:not(.aktif)'); tercihYaz(true); if (b) b.click(); };
        uygula();
        /* Siteye girildiği an: tarayıcılar yalnızca kullanıcı dokunuşuyla tam ekrana izin verir → ilk dokunuşta otomatik tam ekran */
        if (!uygulamaMi() && document.documentElement.requestFullscreen && tercih()) {
            var otoTam = function () {
                document.removeEventListener('pointerdown', otoTam, true); document.removeEventListener('touchend', otoTam, true); document.removeEventListener('click', otoTam, true);
                if (!document.fullscreenElement) document.documentElement.requestFullscreen().catch(function () {});
            };
            document.addEventListener('pointerdown', otoTam, true); document.addEventListener('touchend', otoTam, true); document.addEventListener('click', otoTam, true);
        }
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', basla); else basla();
})();
