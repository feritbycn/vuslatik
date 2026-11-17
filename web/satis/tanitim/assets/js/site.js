/* ============================================================
   site.js — etkileşimler ve efektler (bağımlılık yok)
   ============================================================ */
(function () {
  'use strict';
  var $ = function (s, k) { return (k || document).querySelector(s); };
  var $$ = function (s, k) { return Array.prototype.slice.call((k || document).querySelectorAll(s)); };
  var azHareket = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
  var dokunmatik = window.matchMedia && matchMedia('(hover: none)').matches;
  function olay(ad, veri) { try { if (window.gtag) gtag('event', ad, veri || {}); } catch (e) {} }

  /* ——— üst menü, ilerleme çubuğu ——— */
  var ust = $('#ust'), bar = $('.ilerleme i');
  function kaydir() {
    var y = window.scrollY || 0, h = document.documentElement.scrollHeight - innerHeight;
    if (ust) ust.classList.toggle('kayik', y > 24);
    if (bar) bar.style.transform = 'scaleX(' + (h > 0 ? Math.min(1, y / h) : 0) + ')';
  }
  addEventListener('scroll', kaydir, { passive: true }); kaydir();
  var menuAc = $('.menu-ac'), menu = $('#menu');
  if (menuAc && menu) {
    menuAc.addEventListener('click', function () { var a = menu.classList.toggle('acik'); menuAc.setAttribute('aria-expanded', a ? 'true' : 'false'); });
    $$('a', menu).forEach(function (a) { a.addEventListener('click', function () { menu.classList.remove('acik'); menuAc.setAttribute('aria-expanded', 'false'); }); });
  }

  /* ——— olay izleme (Google Analytics tanımlıysa) ——— */
  document.addEventListener('click', function (e) { var a = e.target.closest && e.target.closest('[data-olay]'); if (a) olay(a.getAttribute('data-olay')); });

  /* ——— hero: kelime sırası, imleç ışığı, eğim ——— */
  $$('.hero-b .kelime').forEach(function (k, i) { k.style.setProperty('--i', i); });
  var hero = $('.hero'), cihaz = $('#cihaz');
  if (hero && !dokunmatik && !azHareket) {
    hero.addEventListener('pointermove', function (e) {
      var r = hero.getBoundingClientRect();
      hero.style.setProperty('--mx', (e.clientX - r.left) + 'px'); hero.style.setProperty('--my', (e.clientY - r.top) + 'px');
      if (cihaz) {
        var c = cihaz.getBoundingClientRect(), dx = (e.clientX - (c.left + c.width / 2)) / innerWidth, dy = (e.clientY - (c.top + c.height / 2)) / innerHeight;
        cihaz.style.setProperty('--ry', (-6 + dx * 10).toFixed(2) + 'deg'); cihaz.style.setProperty('--rx', (3 - dy * 8).toFixed(2) + 'deg');
      }
    });
    hero.addEventListener('pointerleave', function () { if (cihaz) { cihaz.style.setProperty('--ry', '-6deg'); cihaz.style.setProperty('--rx', '3deg'); } });
  }

  /* ——— demo: saat, kaydırarak giriş ——— */
  function saatYaz(d) { return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }
  var saatEl = $('#demo-saat'); if (saatEl) { saatEl.textContent = saatYaz(new Date()); setInterval(function () { saatEl.textContent = saatYaz(new Date()); }, 15000); }
  var kayit = $('#gc-kayit'), tutac = $('#gc-tutac'), dolgu = $('#gc-dolgu'), gc = $('#gc'), yazi = $('#gc-yazi'), durum = $('#gc-durum span'), gcAlt = $('#gc-alt');
  if (kayit && tutac) {
    var surukleme = false, baslangic = 0, azami = 0, bitti = false;
    kayit.classList.add('ipucu');
    var yerles = function (x) { tutac.style.transform = 'translateX(' + x + 'px)'; dolgu.style.width = (x + 53) + 'px'; kayit.setAttribute('aria-valuenow', Math.round(x / Math.max(1, azami) * 100)); };
    var tamamla = function () {
      if (bitti) return; bitti = true; yerles(azami); gc.classList.add('tamam'); kayit.classList.remove('ipucu');
      var s = saatYaz(new Date()); yazi.textContent = 'Giriş kaydedildi · ' + s; durum.textContent = 'İçeridesiniz · giriş ' + s; gcAlt.lastChild.textContent = 'Merkez ofis noktasında doğrulandı';
      tutac.innerHTML = '<svg class="ik" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>';
      tutac.style.transition = 'transform .5s cubic-bezier(.2,.8,.2,1)'; olay('demo_giris');
      if (!azHareket) parcalar(tutac);
      setTimeout(function () { bitti = false; gc.classList.remove('tamam'); yazi.textContent = 'Kaydırarak çıkış yap'; durum.textContent = 'Giriş kaydedildi · ' + s; tutac.innerHTML = '<svg class="ik" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>'; tutac.style.transition = 'transform .5s cubic-bezier(.2,.8,.2,1)'; yerles(0); }, 4200);
    };
    var olc = function () { azami = kayit.clientWidth - tutac.offsetWidth - 10; };
    tutac.addEventListener('pointerdown', function (e) { if (bitti) return; olc(); surukleme = true; baslangic = e.clientX; tutac.style.transition = 'none'; tutac.setPointerCapture(e.pointerId); kayit.classList.remove('ipucu'); tutac.style.animation = 'none'; });
    tutac.addEventListener('pointermove', function (e) { if (!surukleme) return; yerles(Math.max(0, Math.min(azami, e.clientX - baslangic))); });
    var birak = function () { if (!surukleme) return; surukleme = false; tutac.style.transition = 'transform .4s cubic-bezier(.2,.8,.2,1)'; dolgu.style.transition = 'width .4s'; var g = parseFloat((tutac.style.transform.match(/-?[\d.]+/) || [0])[0]); if (g > azami * .88) tamamla(); else { yerles(0); setTimeout(function () { dolgu.style.transition = ''; }, 420); } };
    tutac.addEventListener('pointerup', birak); tutac.addEventListener('pointercancel', birak);
    kayit.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ' || e.key === 'ArrowRight') { e.preventDefault(); olc(); tutac.style.transition = 'transform .5s cubic-bezier(.2,.8,.2,1)'; dolgu.style.transition = 'width .5s'; tamamla(); } });
    tutac.addEventListener('click', function () { if (!bitti && !surukleme && !baslangic) { olc(); tamamla(); } baslangic = 0; });
  }
  function parcalar(el) {
    var r = el.getBoundingClientRect(), renk = ['#12B5BF', '#F2573F', '#2B4FD0', '#FFC857', '#1FA855'];
    for (var i = 0; i < 26; i++) {
      var p = document.createElement('i'); p.className = 'parca'; p.style.left = (r.left + r.width / 2) + 'px'; p.style.top = (r.top + r.height / 2) + 'px'; p.style.background = renk[i % renk.length];
      document.body.appendChild(p);
      var a = Math.random() * Math.PI * 2, d = 60 + Math.random() * 110;
      p.animate([{ transform: 'translate(0,0) rotate(0)', opacity: 1 }, { transform: 'translate(' + Math.cos(a) * d + 'px,' + (Math.sin(a) * d + 70) + 'px) rotate(' + (Math.random() * 540) + 'deg)', opacity: 0 }], { duration: 900 + Math.random() * 500, easing: 'cubic-bezier(.2,.8,.2,1)' }).onfinish = (function (q) { return function () { q.remove(); }; })(p);
    }
  }

  /* ——— demo: izin onayı döngüsü ——— */
  var adimlar = $$('#izin-adimlar li'), chip = $('#izin-chip'), sms = $('#demo-sms');
  if (adimlar.length) {
    var tur = 0, metinler = ['Bekliyor', 'Yönetici onayladı', 'Onaylandı'];
    var sonraki = function () {
      if (tur < adimlar.length) { adimlar[tur].classList.add('tamam'); chip.textContent = metinler[Math.min(tur, 2)]; chip.classList.toggle('ok', tur === 2); if (tur === 2 && sms) sms.classList.add('goster'); tur++; setTimeout(sonraki, tur === 3 ? 5200 : 1500); }
      else { adimlar.forEach(function (a) { a.classList.remove('tamam'); }); chip.textContent = 'Bekliyor'; chip.classList.remove('ok'); if (sms) sms.classList.remove('goster'); tur = 0; setTimeout(sonraki, 1400); }
    };
    setTimeout(sonraki, 1900);
  }

  /* ——— kayarken görünme ——— */
  var goster = $$('[data-goster]');
  $$('.oz-liste').forEach(function (l) { $$('div', l).forEach(function (d, i) { d.style.setProperty('--s', i % 8); }); });
  $$('.kars-sutun ul').forEach(function (u) { $$('li', u).forEach(function (d, i) { d.style.setProperty('--s', i); }); });
  function sayac(el) {
    var hedef = parseInt(el.getAttribute('data-say'), 10) || 0, t0 = null;
    if (azHareket) { el.textContent = hedef; return; }
    var adim = function (t) { if (!t0) t0 = t; var o = Math.min(1, (t - t0) / 1400); el.textContent = Math.round(hedef * (1 - Math.pow(1 - o, 3))); if (o < 1) requestAnimationFrame(adim); };
    requestAnimationFrame(adim);
  }
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (l) { l.forEach(function (g) { if (g.isIntersecting) { g.target.classList.add('gor'); $$('[data-say]', g.target).forEach(sayac); io.unobserve(g.target); } }); }, { threshold: .14, rootMargin: '0px 0px -6% 0px' });
    goster.forEach(function (g) { io.observe(g); });
  } else goster.forEach(function (g) { g.classList.add('gor'); });

  /* ——— özellik menüsü: kaydırınca etkin başlık ——— */
  var nav = $('#oz-nav');
  if (nav && 'IntersectionObserver' in window) {
    var linkler = $$('a', nav), cizgi = $('.oz-cizgi', nav);
    var sec = function (id) {
      linkler.forEach(function (a) { var ak = a.getAttribute('data-hedef') === id; a.classList.toggle('aktif', ak); if (ak) { if (cizgi) { cizgi.style.setProperty('--y', a.offsetTop + 'px'); cizgi.style.height = a.offsetHeight + 'px'; cizgi.style.background = getComputedStyle(a).getPropertyValue('--r'); } if (nav.scrollWidth > nav.clientWidth) nav.scrollTo({ left: a.offsetLeft - 24, behavior: 'smooth' }); } });
    };
    var so = new IntersectionObserver(function (l) { l.forEach(function (g) { if (g.isIntersecting) sec(g.target.id); }); }, { rootMargin: '-35% 0px -55% 0px' });
    $$('.oz-grup').forEach(function (g) { so.observe(g); });
    sec(linkler[0].getAttribute('data-hedef'));
  }

  /* ——— mıknatıslı düğmeler ——— */
  if (!dokunmatik && !azHareket) $$('.btn-buyuk').forEach(function (b) {
    b.addEventListener('pointermove', function (e) { var r = b.getBoundingClientRect(); b.style.transform = 'translate(' + ((e.clientX - r.left - r.width / 2) * .12).toFixed(1) + 'px,' + ((e.clientY - r.top - r.height / 2) * .22 - 2).toFixed(1) + 'px)'; });
    b.addEventListener('pointerleave', function () { b.style.transform = ''; });
  });

  /* ——— deneme talebi formu ——— */
  var form = $('#talep-formu');
  if (form) {
    var q = new URLSearchParams(location.search), kaynak = [];
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'gclid'].forEach(function (k) { if (q.get(k)) kaynak.push(k + '=' + q.get(k)); });
    if (!kaynak.length && document.referrer) { try { var rh = new URL(document.referrer); if (rh.host !== location.host) kaynak.push('yonlendiren=' + rh.host); } catch (e) {} }
    form.kaynak.value = kaynak.join('&').slice(0, 200); form.sayfa.value = location.pathname.replace(/^.*\//, '') || 'anasayfa';
    var hata = $('.form-hata', form), dugme = $('button[type=submit]', form);
    var goster2 = function (m, alan) { hata.textContent = m; hata.hidden = false; if (alan) { alan.classList.add('hata'); alan.focus(); } };
    $$('input,select,textarea', form).forEach(function (i) { i.addEventListener('input', function () { i.classList.remove('hata'); hata.hidden = true; }); });
    form.addEventListener('submit', function (e) {
      e.preventDefault(); hata.hidden = true;
      var f = form.elements;
      if (f.ad.value.trim().length < 2) return goster2('Adınızı ve soyadınızı yazın.', f.ad);
      if (f.firma.value.trim().length < 2) return goster2('Şirket adını yazın.', f.firma);
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.eposta.value.trim())) return goster2('E-posta adresi geçerli görünmüyor. Örnek: ad@sirket.com', f.eposta);
      if (f.telefon.value.replace(/\D/g, '').length < 10) return goster2('Telefon numarasını alan koduyla yazın. Örnek: 0532 111 22 33', f.telefon);
      if (!f.kvkk.checked) return goster2('Devam etmek için aydınlatma metnini onaylayın.', f.kvkk);
      dugme.disabled = true; dugme.classList.add('bekle');
      fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return { ok: false, hata: 'Sunucu yanıtı okunamadı. Lütfen WhatsApp’tan yazın.' }; }); })
        .then(function (j) {
          dugme.disabled = false; dugme.classList.remove('bekle');
          if (!j.ok) return goster2(j.hata || 'Talep gönderilemedi. Lütfen tekrar deneyin.');
          olay('generate_lead', { method: 'deneme_formu' }); try { if (window.fbq) fbq('track', 'Lead'); } catch (x) {}
          var tamam = $('#form-tamam'); $('[data-ad]', tamam).textContent = j.ad || '';
          var wa = $('[data-wa-tamam]', tamam);
          if (wa) { try { var u = new URL(wa.href); u.searchParams.set('text', 'Merhaba, ben ' + f.ad.value.trim() + ' (' + f.firma.value.trim() + '). Sitenizden deneme talebi bıraktım.'); wa.href = u.toString(); } catch (x) {} }
          form.hidden = true; tamam.hidden = false; tamam.focus();
        })
        .catch(function () { dugme.disabled = false; dugme.classList.remove('bekle'); goster2('Bağlantı kurulamadı. İnternetinizi kontrol edip tekrar deneyin.'); });
    });
  }

  /* ——— çerez bandı ——— */
  var cerez = $('#cerez');
  if (cerez) {
    var kayitli = null; try { kayitli = localStorage.getItem('ts_cerez'); } catch (e) {}
    if (!kayitli) setTimeout(function () { cerez.hidden = false; }, 1200);
    $$('[data-cerez]', cerez).forEach(function (b) {
      b.addEventListener('click', function () {
        var evet = b.getAttribute('data-cerez') === 'evet';
        try { localStorage.setItem('ts_cerez', evet ? 'evet' : 'hayir'); } catch (e) {}
        if (evet && window.gtag) gtag('consent', 'update', { analytics_storage: 'granted', ad_storage: 'granted', ad_user_data: 'granted', ad_personalization: 'granted' });
        cerez.hidden = true;
      });
    });
  }
})();
