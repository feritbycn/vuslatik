<?php
// ============================================================
// header.php — Oturum açmış kurum sayfalarının ortak başı: <head> (site ayarlarından SEO, favicon, ana renk),
//   tema (açık/koyu) ve küçük arayüz yardımcıları (window.TB, tbBildir, tbOnayla).
//   Sayfa, </body></html> ile kendisi kapatır.
// ============================================================
$pf_a = pf_ayarlar($db);
$hd_renk = (!empty($KURUM['renk']) && preg_match('/^#[0-9a-fA-F]{6}$/', $KURUM['renk'])) ? $KURUM['renk'] : (preg_match('/^#[0-9a-fA-F]{6}$/', $pf_a['tema_renk']) ? $pf_a['tema_renk'] : '#6366f1');
$hd_h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
$hd_fav = !empty($KURUM['logo']) ? $KURUM['logo'] : ($pf_a['favicon'] !== '' ? $pf_a['favicon'] : 'data:image/svg+xml,' . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="16" fill="' . $hd_renk . '"/><circle cx="32" cy="24" r="9" fill="#fff"/><path d="M14 52c2-11 10-16 18-16s16 5 18 16" fill="#fff"/></svg>'));
if (function_exists('ik_ayar') && isset($db)) { try { $hd_f = (string)ik_ayar($db, 'favicon', ''); if (preg_match('~^uploads/[\w./\-]+$~', $hd_f)) $hd_fav = $hd_f; } catch (Throwable $e) {} }
$hd_imza = function_exists('pf_imza_adi') ? pf_imza_adi($db, $KURUM) : $pf_a['site_adi'];   // kurumda "imzayı göster" kapalıysa platform adı yerine kurum adı
$hd_baslik = ($KURUM && $hd_imza !== $KURUM['ad'] ? $KURUM['ad'] . ' — ' : '') . $hd_imza;
// rengin koyu tonu ve ışıması
$hd_rgb = sscanf($hd_renk, '#%02x%02x%02x');
$hd_koyu = sprintf('#%02x%02x%02x', (int)($hd_rgb[0] * .78), (int)($hd_rgb[1] * .78), (int)($hd_rgb[2] * .78));
?><!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="<?= $hd_h($hd_renk) ?>">
<title><?= $hd_h($hd_baslik) ?></title>
<link rel="icon" href="<?= $hd_h($hd_fav) ?>">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<style>
:root { --accent: <?= $hd_h($hd_renk) ?>; --accent-alt: <?= $hd_h($hd_koyu) ?>; --accent-glow: rgba(<?= intval($hd_rgb[0]) ?>, <?= intval($hd_rgb[1]) ?>, <?= intval($hd_rgb[2]) ?>, .16);
  --bg-surface: #ffffff; --bg-overlay: #f6f8fc; --bg-hover: rgba(99,102,241,.07); --border: #e5e9f2; --text-primary: #0f172a; --text-secondary: #475569; --text-muted: #8492a6; --input-bg: #ffffff; --shadow-sm: 0 1px 2px rgba(15,23,42,.06); }
@media (prefers-color-scheme: dark) { html:not([data-tema="light"]) { --bg-surface: #131a2e; --bg-overlay: #1a2340; --bg-hover: rgba(129,140,248,.1); --border: #263150; --text-primary: #e8ecf8; --text-secondary: #b4bdd6; --text-muted: #7c88a8; --input-bg: #0f1628; color-scheme: dark; } }
html[data-tema="dark"] { --bg-surface: #131a2e; --bg-overlay: #1a2340; --bg-hover: rgba(129,140,248,.1); --border: #263150; --text-primary: #e8ecf8; --text-secondary: #b4bdd6; --text-muted: #7c88a8; --input-bg: #0f1628; color-scheme: dark; }
html, body { margin: 0; }
body { font-family: Inter, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif; background: var(--bg-overlay); color: var(--text-primary); -webkit-font-smoothing: antialiased; }
.vh-toast-yigin { position: fixed; right: 16px; bottom: 16px; z-index: 99999; display: flex; flex-direction: column; gap: 8px; max-width: calc(100vw - 32px); }
.vh-toast { background: var(--bg-surface); color: var(--text-primary); border: 1px solid var(--border); border-left: 4px solid var(--accent); border-radius: 12px; padding: 11px 14px; box-shadow: 0 12px 32px -10px rgba(0,0,0,.35); font-size: 14px; animation: vh-gir .25s ease; }
.vh-toast.hata { border-left-color: #f43f5e; } .vh-toast.uyari { border-left-color: #f59e0b; } .vh-toast.basari { border-left-color: #10b981; }
.vh-arka { position: fixed; inset: 0; background: rgba(8,12,24,.55); backdrop-filter: blur(3px); z-index: 99998; display: flex; align-items: center; justify-content: center; padding: 16px; animation: vh-gir .15s ease; }
.vh-kutu { background: var(--bg-surface); color: var(--text-primary); border: 1px solid var(--border); border-radius: 16px; padding: 22px; width: min(420px, 100%); box-shadow: 0 30px 80px -20px rgba(0,0,0,.5); }
.vh-kutu h3 { margin: 0 0 8px; font-size: 18px; } .vh-kutu p { margin: 0 0 18px; color: var(--text-secondary); line-height: 1.5; white-space: pre-line; }
.vh-kutu .alt { display: flex; gap: 8px; justify-content: flex-end; } .vh-kutu button { font: inherit; padding: 9px 16px; border-radius: 10px; border: 1px solid var(--border); background: var(--bg-overlay); color: var(--text-primary); cursor: pointer; }
.vh-kutu button.ana { background: var(--accent); border-color: var(--accent); color: #fff; font-weight: 600; } .vh-kutu button.tehlike { background: #f43f5e; border-color: #f43f5e; color: #fff; font-weight: 600; }
@keyframes vh-gir { from { opacity: 0; transform: translateY(6px); } to { opacity: 1; transform: none; } }
</style>
<script>
(function () { try { var t = localStorage.getItem('vk_tema'); if (t === 'light' || t === 'dark') document.documentElement.setAttribute('data-tema', t); } catch (e) {} })();
window.TB = { csrf: <?= json_encode(csrf_token()) ?>, kurum: <?= json_encode($KURUM ? ['id' => intval($KURUM['id']), 'ad' => $KURUM['ad'], 'kod' => $KURUM['kod']] : null) ?>, site: <?= json_encode($hd_imza) ?> };
window.tbTema = function (t) { try { if (t) localStorage.setItem('vk_tema', t); else localStorage.removeItem('vk_tema'); } catch (e) {} if (t) document.documentElement.setAttribute('data-tema', t); else document.documentElement.removeAttribute('data-tema'); };
window.tbBildir = function (m, t) {
    var y = document.querySelector('.vh-toast-yigin'); if (!y) { y = document.createElement('div'); y.className = 'vh-toast-yigin'; document.body.appendChild(y); }
    var e = document.createElement('div'); e.className = 'vh-toast ' + (t || ''); e.textContent = String(m == null ? '' : m); y.appendChild(e);
    setTimeout(function () { e.style.transition = 'opacity .3s'; e.style.opacity = '0'; setTimeout(function () { e.remove(); }, 320); }, t === 'hata' ? 6500 : 4000);
};
window.tbOnayla = function (o) {
    o = o || {};
    return new Promise(function (coz) {
        var a = document.createElement('div'); a.className = 'vh-arka';
        a.innerHTML = '<div class="vh-kutu" role="dialog" aria-modal="true"><h3></h3><p></p><div class="alt"><button type="button" data-h></button><button type="button" data-e></button></div></div>';
        a.querySelector('h3').textContent = o.baslik || 'Emin misiniz?'; a.querySelector('p').textContent = o.mesaj || '';
        var h = a.querySelector('[data-h]'), e = a.querySelector('[data-e]'); h.textContent = o.hayir || 'Vazgeç'; e.textContent = o.evet || 'Evet';
        e.className = o.tur === 'uyari' || o.tur === 'tehlike' ? 'tehlike' : 'ana';
        function bit(v) { a.remove(); document.removeEventListener('keydown', tus); coz(v); }
        function tus(ev) { if (ev.key === 'Escape') bit(false); }
        h.onclick = function () { bit(false); }; e.onclick = function () { bit(true); }; a.addEventListener('click', function (ev) { if (ev.target === a) bit(false); });
        document.addEventListener('keydown', tus); document.body.appendChild(a); e.focus();
    });
};
</script>
<script src="assets/js/tb-url.js"></script><script src="assets/js/tb-mobil.js" defer></script></head>
<body>
