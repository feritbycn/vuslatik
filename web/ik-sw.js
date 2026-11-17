/* ==========================================================================
   ik-sw.js — İK bildirimleri için servis çalışanı (Web Push)
   Site kapalıyken / sekme arka plandayken sunucu "uyandırma" itmesi gönderir; burada son bildirimler çekilip
   telefon / bilgisayar bildirimi olarak gösterilir. Dosya SİTE KÖKÜNDE durmalıdır (ik.php ile aynı klasör).
   ========================================================================== */
'use strict';
var IKB_SURUM = 'ik-sw-1';

self.addEventListener('install', function () { self.skipWaiting(); });
// Tarayıcının "uygulamayı yükle" ölçütü için fetch dinleyicisi gerekir; istekler olduğu gibi ağa gider (önbellek yok)
self.addEventListener('fetch', function () {});
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });

function duzMetin(h) { return String(h || '').replace(/<[^>]*>/g, '').replace(/&amp;/g, '&').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"').replace(/&#39;/g, "'").replace(/\s+/g, ' ').trim(); }

self.addEventListener('push', function (olay) {
    olay.waitUntil((async function () {
        var pencereler = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        var liste = [];
        try {
            var r = await fetch('ik_bildirim.php?islem=push_liste', { credentials: 'include', cache: 'no-store' });
            var j = await r.json();
            if (j && j.ok) liste = j.items || [];
        } catch (e) {}
        // Site şu an ekranda açıksa ve bildirim kütüphanesi yüklüyse (el sıkışma): sayfa kendi uyarısını + sesini verir, sistem bildirimi göstermeyiz
        var sahiplendi = false;
        for (var i = 0; i < pencereler.length; i++) {
            if (pencereler[i].visibilityState !== 'visible') continue;
            var ack = await new Promise(function (coz) {
                var kanal = new MessageChannel(), zaman = setTimeout(function () { coz(false); }, 900);
                kanal.port1.onmessage = function () { clearTimeout(zaman); coz(true); };
                try { pencereler[i].postMessage({ tip: 'ik-yeni' }, [kanal.port2]); } catch (e) { clearTimeout(zaman); coz(false); }
            });
            if (ack) sahiplendi = true;
        }
        if (sahiplendi) return;
        if (!liste.length) liste = [{ id: 0, baslik: 'İnsan Kaynakları', mesaj: 'Yeni bir bildiriminiz var.', link: 'ik.php' }];
        await Promise.all(liste.map(function (b) {
            return self.registration.showNotification(duzMetin(b.baslik) || 'İnsan Kaynakları', {
                body: duzMetin(b.mesaj), icon: 'assets/img/ik-bildirim-ikon.png', badge: 'assets/img/ik-bildirim-rozet.png',
                tag: b.id ? 'ik-' + b.id : 'ik-genel', renotify: true, vibrate: [120, 60, 120], data: { link: b.link || 'ik.php' }, timestamp: Date.now()
            });
        }));
    })());
});

self.addEventListener('notificationclick', function (olay) {
    olay.notification.close();
    var hedef = new URL((olay.notification.data && olay.notification.data.link) || 'ik.php', self.registration.scope).href;
    olay.waitUntil((async function () {
        var pencereler = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        for (var i = 0; i < pencereler.length; i++) {
            var c = pencereler[i];
            if (c.url.indexOf(self.registration.scope) === 0 && 'focus' in c) { try { await c.navigate(hedef); } catch (e) {} return c.focus(); }
        }
        return self.clients.openWindow(hedef);
    })());
});
