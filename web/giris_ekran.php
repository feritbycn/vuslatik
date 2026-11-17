<?php
// ============================================================
// giris_ekran.php — OFİS EKRANI: 30 saniyede bir değişen 6 haneli giriş kodu
// Ofisteki bir tablet / TV / bilgisayar tarayıcısında açık bırakılır (giriş yapmaya gerek yok; adres gizli anahtarlıdır).
// Adres: giris_ekran.php?k=KURUM_NO&nokta=NOKTA_ID&anahtar=ANAHTAR   (Noktalar sekmesinde "Ofis ekranı adresi" olarak görünür)
// Personel giriş yaparken bu kodu girer → yalnızca ofisteki kişi kodu görebilir (sahte konum uygulaması işe yaramaz).
// ============================================================
$nokta = intval($_GET['nokta'] ?? 0); $anahtar = (string)($_GET['anahtar'] ?? '');
?><!DOCTYPE html>
<html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">
<title>Giriş Kodu</title>
<style>
html, body { height: 100%; margin: 0; background: #0f172a; color: #e2e8f0; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
body { display: flex; align-items: center; justify-content: center; text-align: center; }
.k { padding: 24px; }
.ad { font-size: clamp(18px, 3vw, 30px); color: #94a3b8; font-weight: 600; margin-bottom: 8px; }
.kod { font-size: clamp(64px, 18vw, 220px); font-weight: 800; letter-spacing: .12em; font-variant-numeric: tabular-nums; color: #38bdf8; line-height: 1.05; }
.alt { margin-top: 18px; font-size: clamp(14px, 2.2vw, 22px); color: #94a3b8; }
.cubuk { width: min(560px, 80vw); height: 10px; border-radius: 99px; background: #1e293b; margin: 22px auto 0; overflow: hidden; }
.cubuk i { display: block; height: 100%; width: 100%; background: #38bdf8; transition: width 1s linear; }
.hata { color: #f87171; font-size: 22px; }
<script src="assets/js/tb-url.js"></script></style></head>
<body><div class="k" id="k"><div class="ad" id="ad">Yükleniyor…</div><div class="kod" id="kod">------</div>
<div class="cubuk"><i id="cub"></i></div><div class="alt">Personel giriş yaparken bu kodu girer · kod 30 saniyede bir yenilenir</div></div>
<script>
var URL_ = 'giris_islem.php?islem=ekran_kod&k=<?= intval($_GET['k'] ?? 0) ?>&nokta=<?= $nokta ?>&anahtar=' + encodeURIComponent(<?= json_encode($anahtar) ?>), kalan = 0, bekle = false;
function cek() {
    if (bekle) return; bekle = true;
    fetch(URL_, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
        bekle = false;
        if (!j.ok) { document.getElementById('kod').innerHTML = '<span class="hata">' + (j.hata || 'Hata') + '</span>'; return; }
        document.getElementById('ad').textContent = j.ad; document.getElementById('kod').textContent = j.kod.replace(/(\d{3})(\d{3})/, '$1 $2'); kalan = j.kalan; ciz();
    }).catch(function () { bekle = false; });
}
function ciz() { document.getElementById('cub').style.width = Math.max(0, kalan / 30 * 100) + '%'; }
setInterval(function () { kalan--; if (kalan <= 0) cek(); else ciz(); }, 1000);
cek();
</script></body></html>
