<?php
// ============================================================
// yetkisiz.php — Modern "Bu sayfayı görmeye yetkiniz yok" ekranı
// Kullanım:  require_once __DIR__ . '/yetkisiz.php';
//            tbYetkisizSayfa('Raporlar');   // çıktı verip durur (403)
// Doğrudan açılırsa da genel yetkisiz ekranını gösterir.
// Giriş yapmamış kullanıcılar zaten girisKontrol() ile giriş sayfasına gider.
// ============================================================

if (!function_exists('tbYetkisizSayfa')) {
function tbYetkisizSayfa($sayfaAdi = '', $aciklama = '', $kod = 403)
{
    while (ob_get_level() > 0) @ob_end_clean();
    if (!headers_sent()) {
        http_response_code($kod);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    $e = function ($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); };
    $baslik = 'Bu sayfayı görmeye yetkiniz yok';
    if ($aciklama === '') {
        $aciklama = ($sayfaAdi !== '' ? '<b>' . $e($sayfaAdi) . '</b> sayfası' : 'Bu sayfa') .
            ' için erişim izniniz bulunmuyor. Erişim gerekiyorsa kurucu veya yetkili bir kişiyle iletişime geçin.';
    } else {
        $aciklama = $e($aciklama);
    }
    $adSoyad = $_SESSION['ad_soyad'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Erişim yok — <?= htmlspecialchars(function_exists('pf_imza_adi') ? pf_imza_adi($GLOBALS['db'] ?? null) : 'Turkuaz Bileşim', ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>
(function () { var t = 'light'; try { t = localStorage.getItem('tb-tema') || 'light'; } catch (e) {} document.documentElement.setAttribute('data-tema', t === 'dark' ? 'dark' : 'light'); })();
</script>
<style>
:root, [data-tema="light"] { --bg:#f5f7fb; --kart:#ffffff; --yazi:#0f172a; --soluk:#64748b; --cizgi:rgba(15,23,42,.08); --izgara:rgba(15,23,42,.035); }
[data-tema="dark"] { --bg:#0a0a0f; --kart:#13131c; --yazi:#eef0f7; --soluk:#9a9ab0; --cizgi:rgba(255,255,255,.09); --izgara:rgba(255,255,255,.03); }
*{box-sizing:border-box;margin:0;padding:0}
body{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;background:var(--bg);color:var(--yazi);
  font-family:'DM Sans',system-ui,-apple-system,'Segoe UI',Roboto,Arial,sans-serif;overflow:hidden;position:relative}
body::before{content:'';position:fixed;inset:0;background-image:linear-gradient(var(--izgara) 1px,transparent 1px),linear-gradient(90deg,var(--izgara) 1px,transparent 1px);background-size:56px 56px;pointer-events:none}
.isik{position:fixed;width:560px;height:560px;border-radius:50%;filter:blur(90px);opacity:.35;pointer-events:none;animation:yuz 9s ease-in-out infinite alternate}
.isik.a{background:#6366f1;top:-180px;left:-160px}.isik.b{background:#ec4899;bottom:-200px;right:-160px;animation-delay:-4s}
@keyframes yuz{to{transform:translate(40px,30px) scale(1.08)}}
.kart{position:relative;width:100%;max-width:520px;background:var(--kart);border:1px solid var(--cizgi);border-radius:28px;padding:44px 36px 36px;text-align:center;
  box-shadow:0 30px 80px rgba(15,23,42,.18);animation:gir .5s cubic-bezier(.2,1.3,.4,1)}
@keyframes gir{from{opacity:0;transform:translateY(18px) scale(.97)}to{opacity:1;transform:none}}
.rozet{width:96px;height:96px;margin:0 auto 22px;border-radius:30px;display:flex;align-items:center;justify-content:center;position:relative;
  background:linear-gradient(135deg,#f97316,#ec4899 55%,#8b5cf6);color:#fff;font-size:40px;box-shadow:0 18px 40px rgba(236,72,153,.35);animation:salla 3.2s ease-in-out infinite}
.rozet::after{content:'';position:absolute;inset:-8px;border-radius:36px;border:2px dashed rgba(236,72,153,.35);animation:don 14s linear infinite}
@keyframes salla{0%,86%,100%{transform:rotate(0)}90%{transform:rotate(-8deg)}94%{transform:rotate(7deg)}}
@keyframes don{to{transform:rotate(360deg)}}
.kod{display:inline-block;font-size:11px;font-weight:800;letter-spacing:.14em;color:#db2777;background:rgba(236,72,153,.1);padding:4px 12px;border-radius:999px;margin-bottom:14px}
h1{font-size:24px;line-height:1.25;font-weight:900;letter-spacing:-.02em;margin-bottom:12px}
p{color:var(--soluk);font-size:14.5px;line-height:1.6}
p b{color:var(--yazi)}
.kim{margin-top:16px;font-size:12.5px;color:var(--soluk)}
.kim i{color:#8b5cf6;margin-right:4px}
.dugmeler{display:flex;gap:10px;justify-content:center;margin-top:26px;flex-wrap:wrap}
.btn{display:inline-flex;align-items:center;gap:8px;height:44px;padding:0 20px;border-radius:14px;font:inherit;font-size:14px;font-weight:800;text-decoration:none;cursor:pointer;transition:transform .12s,filter .15s,background .15s}
.btn:active{transform:translateY(1px)}
.btn.ana{color:#fff;border:none;background:linear-gradient(135deg,#6366f1,#8b5cf6);box-shadow:0 10px 24px rgba(99,102,241,.35)}
.btn.ana:hover{filter:brightness(1.08)}
.btn.ikinci{color:var(--yazi);background:transparent;border:1px solid var(--cizgi)}
.btn.ikinci:hover{background:var(--izgara)}
@media (max-width:480px){.kart{padding:36px 22px 26px;border-radius:22px}h1{font-size:20px}}
@media (prefers-reduced-motion:reduce){*{animation:none!important}}
</style>
</head>
<body>
<div class="isik a"></div><div class="isik b"></div>
<main class="kart" role="alert">
    <div class="rozet"><i class="fa-solid fa-lock"></i></div>
    <span class="kod">ERİŞİM YOK · <?= intval($kod) ?></span>
    <h1><?= $e($baslik) ?></h1>
    <p><?= $aciklama ?></p>
    <?php if ($adSoyad !== ''): ?><div class="kim"><i class="fa-solid fa-circle-user"></i><?= $e($adSoyad) ?> olarak giriş yaptınız</div><?php endif; ?>
    <div class="dugmeler">
        <a class="btn ana" href="ik.php"><i class="fa-solid fa-house"></i> Ana sayfaya dön</a>
        <button type="button" class="btn ikinci" onclick="history.length > 1 ? history.back() : location.href='ik.php'"><i class="fa-solid fa-arrow-left"></i> Geri</button>
    </div>
</main>
</body>
</html>
<?php
    exit;
}
}

// Dosya doğrudan açıldıysa genel ekranı göster
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'yetkisiz.php') {
    if (session_status() === PHP_SESSION_NONE) @session_start();
    tbYetkisizSayfa();
}
