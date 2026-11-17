<?php
// aktivasyon.php — MÜŞTERİ KURULUMU: aktivasyon kodu girişi (kod ana sunucuda doğrulanır ve yalnızca bir kez çalışır)
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/aktivasyon_ortak.php';
$h = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
if (!akt_zorunlu()) { header('Location: giris.php'); exit; }
$d = akt_durum();
if ($d['ok']) { header('Location: giris.php'); exit; }
if (empty($_SESSION['akt_csrf'])) $_SESSION['akt_csrf'] = bin2hex(random_bytes(16));
$hata = ''; $kurum = TB_TEK_KURUM > 0 ? pf_kurum($db, TB_TEK_KURUM) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['akt_csrf'], (string)($_POST['_csrf'] ?? ''))) $hata = 'Oturum doğrulaması başarısız, sayfayı yenileyin.';
    elseif ($d['durum'] === 'dosya' || $d['durum'] === 'askida') $hata = $d['mesaj'];
    else {
        $s = akt_etkinlestir((string)($_POST['kod'] ?? ''));
        if ($s['ok']) { header('Location: giris.php'); exit; }
        $hata = $s['mesaj'];
    }
}
$ad = $kurum ? $kurum['ad'] : 'Kurulum'; $renk = ($kurum && preg_match('/^#[0-9a-f]{6}$/i', $kurum['renk'])) ? $kurum['renk'] : '#6366f1';
$kilit = in_array($d['durum'], ['kilit', 'dosya', 'askida'], true);
?><!doctype html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex">
<title>Aktivasyon — <?= $h($ad) ?></title>
<style>
:root{--r:<?= $h($renk) ?>;--y:#fff;--t:#0f172a;--s:#64748b;--c:#e2e8f0;--bg:#f1f5f9}
@media (prefers-color-scheme:dark){:root{--y:#1e293b;--t:#f1f5f9;--s:#94a3b8;--c:#334155;--bg:#0f172a}}
*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:16px;background:radial-gradient(1000px 500px at 20% -10%,color-mix(in srgb,var(--r) 22%,transparent),transparent),var(--bg);color:var(--t);font:15px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif}
.k{width:100%;max-width:440px;background:var(--y);border:1px solid var(--c);border-radius:20px;padding:28px 24px;box-shadow:0 20px 50px rgba(15,23,42,.12)}
.l{width:52px;height:52px;border-radius:14px;background:linear-gradient(135deg,var(--r),color-mix(in srgb,var(--r) 55%,#06b6d4));display:grid;place-items:center;color:#fff;font-weight:800;font-size:22px;margin-bottom:14px}
h1{margin:0 0 4px;font-size:22px}p{margin:0 0 16px;color:var(--s)}
input{width:100%;padding:14px;border:1px solid var(--c);border-radius:12px;background:transparent;color:var(--t);font:600 20px/1 ui-monospace,Consolas,monospace;letter-spacing:2px;text-align:center;text-transform:uppercase}
input:focus{outline:2px solid var(--r);outline-offset:1px}
button{margin-top:14px;width:100%;padding:14px;border:0;border-radius:12px;background:var(--r);color:#fff;font:700 16px system-ui;cursor:pointer}
.h{background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;border-radius:12px;padding:12px;margin-bottom:14px;font-size:14px}
@media (prefers-color-scheme:dark){.h{background:#450a0a;border-color:#7f1d1d;color:#fca5a5}}
small{display:block;margin-top:14px;color:var(--s);font-size:12.5px}
</style></head><body><main class="k">
<div class="l"><?= $h(mb_strtoupper(mb_substr($ad, 0, 1, 'UTF-8'), 'UTF-8')) ?></div>
<h1><?= $d['durum'] === 'askida' ? 'Hesap askıda' : ($kilit ? 'Lisans doğrulanamadı' : 'Aktivasyon') ?></h1>
<p><?= $h($kilit ? '' : $ad . ' kurulumunu başlatmak için size verilen aktivasyon kodunu girin.') ?></p>
<?php if ($hata !== ''): ?><div class="h"><?= $h($hata) ?></div><?php elseif ($kilit): ?><div class="h"><?= $h($d['mesaj']) ?></div><?php endif; ?>
<?php if ($d['durum'] !== 'dosya' && $d['durum'] !== 'askida'): ?>
<form method="post" autocomplete="off"><input type="hidden" name="_csrf" value="<?= $h($_SESSION['akt_csrf']) ?>">
<input name="kod" placeholder="XXXX-XXXX-XXXX-XXXX" maxlength="19" required autofocus inputmode="text" autocapitalize="characters">
<button type="submit"><?= $kilit ? 'Yeni kodla etkinleştir' : 'Etkinleştir' ?></button></form>
<small>Kod yalnızca <b>bir kez</b> kullanılabilir ve bu kurulumu bu adrese bağlar. Dosyaları değiştirmek, silmek ya da başka bir adrese kopyalamak lisansı geçersiz kılar.</small>
<?php endif; ?>
</main></body></html>
