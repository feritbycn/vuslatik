/* ==========================================================================
   tb-mobil-push.js — Mobil uygulama (Android / iOS, Capacitor) için anlık bildirim köprüsü
   Yalnızca "Bilge360 İK" mobil uygulamasının içinde çalışır; normal tarayıcıda hiçbir şey yapmaz.
   • İzin ister, Firebase (FCM) cihaz kodunu alıp sunucuya kaydeder (ik_bildirim.php?islem=mobil_kayit)
   • Uygulama AÇIKKEN bildirim gelirse: sayfa içi uyarı kartı + ses (IKBildirim.yokla)
   • Uygulama arka planda / kapalıyken: işletim sisteminin bildirimi; dokununca ilgili sayfa açılır
   • Çıkış yapılırken cihaz kaydı silinir (başka kullanıcıya bildirim gitmesin)
   ========================================================================== */
(function () {
    'use strict';
    var C = window.Capacitor;
    if (!C || !(C.isNativePlatform && C.isNativePlatform())) return;
    document.documentElement.classList.add('mobil-app');
    var FM = null;
    try { FM = C.registerPlugin ? C.registerPlugin('FirebaseMessaging') : (C.Plugins && C.Plugins.FirebaseMessaging); } catch (e) { FM = C.Plugins && C.Plugins.FirebaseMessaging; }
    if (!FM) return;
    var API = 'ik_bildirim.php', platform = C.getPlatform ? C.getPlatform() : '';
    var csrf = function () { return (window.TB && window.TB.csrf) || ''; };
    function yolla(islem, v, hizli) {
        var fd = new FormData(); fd.append('islem', islem); fd.append('_csrf', csrf());
        Object.keys(v || {}).forEach(function (k) { fd.append(k, v[k]); });
        return fetch(API, { method: 'POST', body: fd, credentials: 'same-origin', keepalive: !!hizli, headers: { 'X-CSRF-Token': csrf() } });
    }
    function depo(k, v) { try { if (v === undefined) return localStorage.getItem(k); if (v === null) localStorage.removeItem(k); else localStorage.setItem(k, v); } catch (e) {} return null; }
    function kaydet(token) {
        if (!token) return;
        depo('ik_mobil_token', token);
        yolla('mobil_kayit', { token: token, platform: platform }).catch(function () {});
    }
    function ac(link) {
        if (!link) return;
        try { var u = new URL(link, document.baseURI); if (u.origin === location.origin) location.href = u.href; } catch (e) {}
    }
    function uyar(m) { if (window.IKBildirim && window.IKBildirim.toast) { try { window.IKBildirim.toast({ id: 0, baslik: 'Bilge360 İK', mesaj: m }); return; } catch (e) {} } }
    /* ---- Yerel (GPS) konum + SAHTE KONUM tespiti ----
       Uygulamada konum, tarayıcının değil telefonun kendi GPS'inden alınır (KonumGuvenli eklentisi).
       Android'de "sahte konum uygulaması" / iOS'ta konum benzetimi algılanırsa konum VERİLMEZ ve kayıt reddedilir. */
    var KG = null;
    try { KG = C.registerPlugin ? C.registerPlugin('KonumGuvenli') : (C.Plugins && C.Plugins.KonumGuvenli); } catch (e) {}
    function sahteUyari() {
        window.__sahteKonum = true;
        try { window.dispatchEvent(new CustomEvent('sahtekonum')); } catch (e) {}
        uyar('Sahte konum algılandı. Konum benzetme uygulamasını kapatın; giriş-çıkış kaydı yapılamaz.');
    }
    function pozisyon(d) { return { coords: { latitude: d.latitude, longitude: d.longitude, accuracy: d.accuracy, altitude: d.altitude == null ? null : d.altitude, altitudeAccuracy: null, speed: d.speed == null ? null : d.speed, heading: d.heading == null ? null : d.heading }, timestamp: d.zaman || Date.now() }; }
    function hataKodu(e) { var k = String((e && e.code) || ''); return { code: k === 'IZIN' ? 1 : (k === 'KAPALI' || k === 'KONUM') ? 2 : 3, message: (e && e.message) || 'Konum alınamadı', PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 }; }
    if (KG && navigator.geolocation) {
        var izleme = {}, sayac = 0;
        var yerel = {
            getCurrentPosition: function (ok, err, opt) {
                KG.izinIste().then(function () { return KG.konumAl({ timeout: (opt && opt.timeout) || 20000 }); }).then(function (d) {
                    if (d.sahte) { sahteUyari(); if (err) err({ code: 1, message: 'Sahte konum algılandı', PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 }); return; }
                    ok(pozisyon(d));
                }).catch(function (e) { if (err) err(hataKodu(e)); });
            },
            watchPosition: function (ok, err) {
                var no = ++sayac, kayit = { dinleyici: null, id: null, iptal: false };
                izleme[no] = kayit;
                KG.izinIste().then(function () {
                    return KG.addListener('konum', function (d) {
                        if (kayit.iptal) return;
                        if (d.sahte) { sahteUyari(); if (err) err({ code: 1, message: 'Sahte konum algılandı', PERMISSION_DENIED: 1, POSITION_UNAVAILABLE: 2, TIMEOUT: 3 }); return; }
                        ok(pozisyon(d));
                    });
                }).then(function (h) { kayit.dinleyici = h; if (kayit.iptal) { h.remove(); return; } return KG.izleBasla(); })
                  .then(function (r) { if (r) { kayit.id = r.id; if (kayit.iptal) KG.izleDur({ id: r.id }); } })
                  .catch(function (e) { if (err) err(hataKodu(e)); });
                return no;
            },
            clearWatch: function (no) {
                var k = izleme[no]; if (!k) return; k.iptal = true; delete izleme[no];
                if (k.dinleyici) k.dinleyici.remove();
                if (Object.keys(izleme).length === 0) KG.izleDur({ id: k.id || '' }).catch(function () {});
            }
        };
        try { navigator.geolocation.getCurrentPosition = yerel.getCurrentPosition; navigator.geolocation.watchPosition = yerel.watchPosition; navigator.geolocation.clearWatch = yerel.clearWatch; } catch (e) {}
    }
    /* Konum izni: tarayıcıdaki gibi sorulur (Android/iOS işletim sistemi penceresi çıkar) */
    function konumIzni() {
        if (KG) return KG.izinIste().catch(function () {});
        if (!navigator.geolocation) return Promise.resolve();
        return new Promise(function (ok) { navigator.geolocation.getCurrentPosition(function () { ok(); }, function () { ok(); }, { timeout: 15000, maximumAge: 600000 }); });
    }
    function bildirimIzni() {
        return FM.checkPermissions().then(function (r) { return r.receive === 'granted' ? r : FM.requestPermissions(); }).then(function (r) {
            if (r.receive !== 'granted') { if (!depo('ik_bildirim_uyari')) { depo('ik_bildirim_uyari', '1'); uyar('Bildirim izni verilmedi. Telefon Ayarları → Uygulamalar → Bilge360 İK → Bildirimler bölümünden açabilirsiniz.'); } return null; }
            if (platform === 'android' && FM.createChannel) FM.createChannel({ id: 'ik_bildirim', name: 'İK bildirimleri', description: 'İzin, onay ve duyuru bildirimleri', importance: 5, visibility: 1, vibration: true, sound: 'default' }).catch(function () {});
            return FM.getToken().then(function (t) { kaydet(t.token); return t; });
        }).catch(function (e) { uyar('Bildirim servisi başlatılamadı (Firebase yapılandırması eksik olabilir): ' + ((e && e.message) || e)); });
    }
    function baslat() {
        // Girişten sonra gereken izinler sırayla istenir: bildirim → konum
        bildirimIzni().then(konumIzni);
        FM.addListener('tokenReceived', function (e) { kaydet(e.token); });
        // Uygulama açıkken: sayfa içi kart + ses
        FM.addListener('notificationReceived', function () { if (window.IKBildirim && window.IKBildirim.yokla) window.IKBildirim.yokla(); });
        // Bildirime dokunuldu → ilgili sayfayı aç
        FM.addListener('notificationActionPerformed', function (e) {
            var d = (e && e.notification && e.notification.data) || {};
            if (window.IKBildirim && window.IKBildirim.yokla) window.IKBildirim.yokla();
            ac(d.link);
        });
    }
    // Çıkış: cihaz kaydını sil
    document.addEventListener('click', function (e) {
        var a = e.target.closest && e.target.closest('a[href]'); if (a && !/(^|\/)cikis\.php(\?|$)/.test(a.getAttribute('href'))) a = null;
        var t = depo('ik_mobil_token');
        if (a && t) { yolla('mobil_sil', { token: t }, true).catch(function () {}); depo('ik_mobil_token', null); if (FM.deleteToken) FM.deleteToken().catch(function () {}); }
    }, true);
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', baslat); else baslat();
})();
