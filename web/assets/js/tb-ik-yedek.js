/* tb-ik-yedek.js — Kurum içi "Yedekler" sekmesi: destek girişinde tam yönetim, kurum yetkilisinde geri yükleme talebi */
(function () {
    'use strict';
    function basla() {
        var M = window.IKM; if (!M || !M.kayit || !window.TBYedek) return setTimeout(basla, 30);
        M.kayit('yedekler', 'Yedekler', 'Verilerinizin yedekleri ve geri yükleme.', function () {
            var ic = M.icerik; ic.innerHTML = M.yukleniyor();
            var ilk = null;
            var ctx = {
                liste: function () { return M.getir({ islem: 'yedek_bilgi' }); },
                cagir: function (islem, veri, dosya) { return M.gonder(islem, veri, dosya); },
                indir: function (id) { return 'ik_islem.php?islem=yedek_dosya&id=' + id; },
                bildir: M.bildir, onayla: M.onayla, modal: M.modal, sonra: function () { M.bildir('Veriler değişti; sayfa yenileniyor…', 'bilgi'); setTimeout(function () { location.reload(); }, 1800); }
            };
            return M.getir({ islem: 'yedek_bilgi' }).then(function (j) {
                ic.innerHTML = (j.destek ? '<div class="ikm-uyari yd-destek"><i class="fa-solid fa-headset"></i> Destek girişindesiniz: bu kurumun yedeklerini doğrudan yönetebilirsiniz.</div>' : '') + '<div id="yd-kok"></div>';
                var kap = ic.querySelector('#yd-kok');
                if (j.destek) window.TBYedek.panel(kap, ctx); else window.TBYedek.talepPanel(kap, ctx);
            });
        });
    }
    basla();
})();
