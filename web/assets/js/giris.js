/* giris.js — Giriş formu: kurum seçimi (kart listesi / kod), doğrulama, giriş isteği, geçici şifre adımı */
(function () {
    'use strict';
    var G = window.GIRIS || {}, $ = function (s) { return document.querySelector(s); };
    var csrf = G.csrf || '', form = $('#g-form'), hata = $('#g-hata'), seciliKod = G.onsec || '';
    function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function katla(x) { return String(x || '').toLocaleLowerCase('tr-TR').replace(/[çğıöşü]/g, function (h) { return { 'ç': 'c', 'ğ': 'g', 'ı': 'i', 'ö': 'o', 'ş': 's', 'ü': 'u' }[h]; }); }
    function bas(ad) { var p = String(ad).trim().split(/\s+/); return (p[0] ? p[0][0] : '?').toLocaleUpperCase('tr-TR') + (p[1] ? p[1][0].toLocaleUpperCase('tr-TR') : ''); }
    function avatar(k) { return '<span class="g-av" style="--c:' + esc(k.renk || '#6366f1') + '">' + (k.logo ? '<img src="' + esc(k.logo) + '" alt="">' : esc(bas(k.ad))) + '</span>'; }
    function goster(el, m) { el.textContent = m; el.hidden = !m; }
    function sars() { var k = $('#g-kart'); k.classList.remove('sars'); void k.offsetWidth; k.classList.add('sars'); }
    function yukleniyor(b, v) { b.classList.toggle('yukleniyor', !!v); b.disabled = !!v; }

    // ── Kurum seçimi (liste modu) ──
    if (G.liste) {
        var liste = $('#g-liste'), ara = $('#g-kurum-ara'), secim = $('#g-secim'), secili = $('#g-secili');
        function ciz(f) {
            var a = katla(f || ''), n = 0, h = '';
            G.kurumlar.forEach(function (k, i) {
                if (a && katla(k.ad + ' ' + k.kod).indexOf(a) < 0) return; n++;
                h += '<button type="button" class="g-kurum" role="option" data-kod="' + esc(k.kod) + '" style="--i:' + Math.min(i, 8) + '">' + avatar(k) + '<span class="g-kurum-ad">' + esc(k.ad) + '<small>' + esc(k.kod) + '</small></span></button>';
            });
            liste.innerHTML = h || (G.kurumlar.length ? '<div class="g-bos">Eşleşen kurum yok.</div>' : '');
        }
        function sec(kod, odak) {
            var k = G.kurumlar.filter(function (x) { return x.kod === kod; })[0]; if (!k) return;
            seciliKod = k.kod; $('#g-kurum-deger').value = k.kod;
            $('#g-secili-av').outerHTML = avatar(k).replace('class="g-av"', 'class="g-av" id="g-secili-av"'); $('#g-secili-ad').textContent = k.ad;
            secili.hidden = false; secim.hidden = true;
            try { localStorage.setItem('vk_kurum', k.kod); } catch (e) {}
            if (odak !== false) $('#g-kullanici').focus();
        }
        liste.addEventListener('click', function (e) { var b = e.target.closest('.g-kurum'); if (b) sec(b.dataset.kod); });
        $('#g-degistir').addEventListener('click', function () { secili.hidden = true; secim.hidden = false; seciliKod = ''; $('#g-kurum-deger').value = ''; if (ara) ara.focus(); });
        if (ara) ara.addEventListener('input', function () { ciz(ara.value); var b = liste.querySelectorAll('.g-kurum'); if (b.length === 1 && ara.value.length > 1) b[0].classList.add('on'); });
        if (ara) ara.addEventListener('keydown', function (e) { if (e.key === 'Enter') { var b = liste.querySelector('.g-kurum'); if (b) { e.preventDefault(); sec(b.dataset.kod); } } });
        ciz('');
        var son = ''; try { son = localStorage.getItem('vk_kurum') || ''; } catch (e) {}
        if (seciliKod) sec(seciliKod, false); else if (son && G.kurumlar.some(function (k) { return k.kod === son; })) sec(son, false); else if (G.kurumlar.length === 1) sec(G.kurumlar[0].kod, false);
    } else {
        var kodGiris = $('#g-kurum-ara');
        try { if (kodGiris && !kodGiris.value) kodGiris.value = localStorage.getItem('vk_kurum') || ''; } catch (e) {}
    }
    var otoKurum = '';
    function kurumDeger() { if (G.otomatik) return otoKurum; return G.liste ? seciliKod : ($('#g-kurum-ara').value || '').trim().toLowerCase(); }

    // ── Şifre göster / Caps Lock / tema ──
    $('#g-goz').addEventListener('click', function () { var s = $('#g-sifre'), a = s.type === 'password'; s.type = a ? 'text' : 'password'; this.firstElementChild.className = a ? 'fa-regular fa-eye-slash' : 'fa-regular fa-eye'; });
    ['keydown', 'keyup'].forEach(function (o) { $('#g-sifre').addEventListener(o, function (e) { if (e.getModifierState) $('#g-caps').hidden = !e.getModifierState('CapsLock'); }); });
    $('#g-tema').addEventListener('click', function () {
        var d = document.documentElement, mevcut = d.getAttribute('data-tema') || (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark'), yeni = mevcut === 'dark' ? 'light' : 'dark';
        d.setAttribute('data-tema', yeni); try { localStorage.setItem('vk_tema', yeni); } catch (e) {}
    });

    // ── İstek ──
    function istek(p) {
        var fd = new FormData(); Object.keys(p).forEach(function (k) { fd.append(k, p[k]); }); fd.append('_csrf', csrf);
        return fetch('oturum_islem.php', { method: 'POST', body: fd, credentials: 'same-origin', headers: { 'X-CSRF-Token': csrf } })
            .then(function (r) { return r.text().then(function (t) { try { return JSON.parse(t); } catch (e) { return { ok: false, hata: 'Sunucu yanıtı okunamadı (HTTP ' + r.status + (r.redirected ? ', yönlendirildi' : '') + '): ' + t.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220) }; } }); })
            .catch(function (e) { return { ok: false, hata: 'Sunucuya ulaşılamadı (' + (e && e.message ? e.message : 'ağ hatası') + '). Bağlantınızı kontrol edin.' }; });
    }
    var bekleyenAd = '';
    form.addEventListener('submit', function (e) {
        e.preventDefault(); goster(hata, '');
        var kurum = kurumDeger(), u = $('#g-kullanici').value.trim(), s = $('#g-sifre').value, b = $('#g-gonder');
        if (!kurum && !G.otomatik) { goster(hata, G.liste ? 'Lütfen kurumunuzu seçin.' : 'Lütfen kurum kodunu yazın.'); sars(); return; }
        if (!u || !s) { goster(hata, 'Kullanıcı adı ve şifre gerekli.'); sars(); (u ? $('#g-sifre') : $('#g-kullanici')).focus(); return; }
        yukleniyor(b, true);
        istek({ islem: 'giris', kurum: kurum, kullanici: u, sifre: s, hatirla: ($('#g-hatirla') && $('#g-hatirla').checked) ? 1 : '', don: form.elements.don ? form.elements.don.value : '' }).then(function (j) {
            if (!j.ok && j.kurum_gerekli) {
                yukleniyor(b, false); goster(hata, j.hata); sars();
                var kap = document.getElementById('g-kurum-sec2'); if (kap) kap.remove();
                kap = document.createElement('div'); kap.id = 'g-kurum-sec2'; kap.className = 'g-liste'; kap.style.margin = '10px 0';
                kap.innerHTML = (j.kurumlar || []).map(function (k) { return '<button type="button" class="g-kurum" data-k="' + esc(k.kod) + '"><span>' + esc(k.ad) + '</span></button>'; }).join('');
                hata.parentNode.insertBefore(kap, hata.nextSibling);
                kap.addEventListener('click', function (ev) { var x = ev.target.closest('[data-k]'); if (!x) return; otoKurum = x.dataset.k; kap.remove(); form.requestSubmit ? form.requestSubmit() : b.click(); });
                return;
            }
            if (!j.ok) { yukleniyor(b, false); goster(hata, j.hata || 'Giriş yapılamadı.'); sars(); $('#g-sifre').select(); return; }
            if (j.csrf) csrf = j.csrf;
            if (j.degistir) { yukleniyor(b, false); $('#g-adim-giris').hidden = true; $('#g-adim-sifre').hidden = false; $('#g-yeni').focus(); return; }
            $('#g-kart').style.transition = 'opacity .4s, transform .5s'; $('#g-kart').style.opacity = '0'; $('#g-kart').style.transform = 'translateY(-14px) scale(.97)';
            setTimeout(function () { location.href = j.git || 'ik.php'; }, 260);
        });
    });
    $('#g-sifre-kaydet').addEventListener('click', function () {
        var b = this, h2 = $('#g-hata2'); goster(h2, '');
        yukleniyor(b, true);
        istek({ islem: 'sifre_belirle', yeni: $('#g-yeni').value, yeni2: $('#g-yeni2').value }).then(function (j) {
            if (!j.ok) { yukleniyor(b, false); goster(h2, j.hata || 'Kaydedilemedi.'); sars(); return; }
            location.href = j.git || 'ik.php';
        });
    });
    ['g-yeni', 'g-yeni2'].forEach(function (id) { $('#' + id).addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#g-sifre-kaydet').click(); } }); });
    // Açılışta otomatik odak yalnızca bilgisayarda: telefonda/uygulamada klavyeyi kendiliğinden açmasın
    if (!(window.matchMedia && matchMedia('(pointer: coarse)').matches) && !/Mobi|Android|iPhone|iPad/i.test(navigator.userAgent)) {
    if (!seciliKod && G.liste) { var ara2 = $('#g-kurum-ara'); if (ara2) ara2.focus(); } else if (!G.liste && !($('#g-kurum-ara').value)) $('#g-kurum-ara').focus(); else $('#g-kullanici').focus();
    }
})();
(function(){
    /* Kullanıcı adı her zaman küçük harfle yazılır (İ→i, I→i) */
    (function () {
        function kucult(e) { var el = e.target, v = el.value, y = v.replace(/İ/g, 'i').replace(/I/g, 'i').toLowerCase().replace(/\s+/g, ''); if (y !== v) { var p = el.selectionStart; el.value = y; try { el.setSelectionRange(p, p); } catch (x) {} } }
        ['g-kullanici', 'p-k'].forEach(function (id) { var el = document.getElementById(id); if (el) { el.addEventListener('input', kucult); el.setAttribute('autocapitalize', 'none'); el.setAttribute('autocorrect', 'off'); el.setAttribute('spellcheck', 'false'); } });
    })();
})();
