/* tb-url.js — Adres biçimi uyumluluğu: sunucu ".php" uzantısını gizliyorsa da göstermiyorsa da istekler çalışsın.
   fetch('platform_islem.php?...') → ilk önce yazıldığı gibi dener; 404/405 ya da yönlendirme (POST'u bozar) olursa diğer biçimi (uzantısız ↔ .php) dener,
   çalışan biçimi oturum boyunca hatırlar. Ayrıca sayfa içi bağlantılar (a href) için aynı mantık tıklamada uygulanmaz; sunucu her iki biçimi de çözmelidir. */
(function () {
    'use strict';
    var re = /^([a-z0-9_]+)(\.php)?(\?[\s\S]*)?$/i, asil = window.fetch, mod = null;
    try { mod = sessionStorage.getItem('tb_ext'); } catch (e) {}
    if (!asil) return;
    function yap(u, m) { var x = re.exec(u); return x ? x[1] + (m === '1' ? '.php' : '') + (x[3] || '') : u; }
    window.fetch = function (u, o) {
        if (typeof u !== 'string' || !re.test(u)) return asil.call(this, u, o);
        var self = this, ilk = mod !== null ? mod : (re.exec(u)[2] ? '1' : '0'), alt = ilk === '1' ? '0' : '1';
        return asil.call(self, yap(u, ilk), o).then(function (r) {
            if (r.status !== 404 && r.status !== 405 && !r.redirected) return r;
            return asil.call(self, yap(u, alt), o).then(function (r2) {
                if (r2.status === 404 || r2.status === 405 || r2.redirected) return r;
                mod = alt; try { sessionStorage.setItem('tb_ext', alt); } catch (e) {}
                return r2;
            }, function () { return r; });
        });
    };
})();
