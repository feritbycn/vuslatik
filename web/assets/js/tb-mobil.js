/* tb-mobil.js — Tablolar telefonda kart görünümüne geçebilsin diye her hücreye sütun başlığını (data-et) yazar.
   Dinamik çizilen tablolar için MutationObserver kullanır. Masaüstünde görünüme etkisi yoktur. */
(function () {
    'use strict';
    function etiketle(t) {
        if (t.__tbEt) return; var th = t.querySelectorAll('thead th'); if (!th.length) return;
        var et = Array.prototype.map.call(th, function (h) { return (h.textContent || '').trim(); });
        t.querySelectorAll('tbody tr').forEach(function (tr) {
            Array.prototype.forEach.call(tr.children, function (td, i) { if (td.tagName === 'TD' && !td.hasAttribute('colspan') && et[i] && !td.hasAttribute('data-et')) td.setAttribute('data-et', et[i]); });
        });
        t.__tbEt = 1; t.classList.add('ikm-mobil-kart');
    }
    function tara(k) { (k || document).querySelectorAll('table.ikm-tablo').forEach(function (t) { t.__tbEt = 0; etiketle(t); }); }
    var bekle = null;
    function planla() { if (bekle) return; bekle = setTimeout(function () { bekle = null; tara(document); }, 80); }
    function basla() { tara(document); new MutationObserver(planla).observe(document.body, { childList: true, subtree: true }); }
    if (document.body) basla(); else document.addEventListener('DOMContentLoaded', basla);
})();
