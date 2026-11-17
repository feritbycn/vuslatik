<?php
// ============================================================
// fcm_tani.php — Telefon bildirimi (FCM) tanı paneli. Yalnızca kurum yetkilisi (kurucu).
//   Firebase bağlantısı, kayıtlı cihazlar, kuyruk, gönderim kayıtları ve gerçek telefona test bildirimi.
//   NOT: "FCM kabul etti" = Google iletiyi aldı. Telefonda görüntülendiğinin kanıtı DEĞİLDİR; bunu telefona bakarak doğrulayın.
// ============================================================
require_once __DIR__ . '/config/baglan.php';
require_once __DIR__ . '/ik_ortak.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$uid = intval($_SESSION['kullanici_id'] ?? 0);
$csrf = (string)($_SESSION['tb_csrf'] ?? '');
if ($uid <= 0) { http_response_code(401); exit('Oturum gerekli'); }
$Y = vy_coz($db, 'ik', $uid);
if (empty($Y['kurucu'])) { http_response_code(403); exit('Bu sayfa yalnızca kurum yetkilisine açıktır.'); }
ik_kanal_sema($db);
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
$mesaj = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($csrf === '' || !hash_equals($csrf, (string)($_POST['_csrf'] ?? ''))) { http_response_code(419); exit('Oturum doğrulaması başarısız. Sayfayı yenileyin.'); }
    $i = (string)($_POST['islem'] ?? '');
    if ($i === 'test') {
        $hedef = intval($_POST['kullanici_id'] ?? 0) ?: $uid;
        $id = ik_kuyruga_ekle($db, $hedef, 'Test bildirimi', 'Bu bir test bildirimidir (' . date('H:i:s') . ').', 'ik.php', 'test');
        $n = ik_fcm_kuyruk_calistir($db, 5);
        $mesaj = $id ? "Test kuyruğa eklendi ve $n kayıt işlendi. Aşağıdaki kayıtlardan sonucu görün; telefonda görünüp görünmediğini siz doğrulayın." : 'Test oluşturulamadı.';
    } elseif ($i === 'yeniden') {
        $db->query("UPDATE ik_fcm_kuyruk SET durum = 'bekliyor', sonraki = NOW() WHERE durum = 'hata'");
        $n = ik_fcm_kuyruk_calistir($db, 20); $mesaj = "Hatalı kayıtlar yeniden denendi ($n işlendi).";
    }
}
$ayar = ik_fcm_ayar();
function say($db, $sql) { $q = $db->query($sql); return $q ? intval($q->fetch_row()[0]) : 0; }
$cihazSay = say($db, "SELECT COUNT(*) FROM ik_mobil_cihazlar");
$kuyruk = []; $q = $db->query("SELECT durum, COUNT(*) FROM ik_fcm_kuyruk GROUP BY durum"); if ($q) while ($r = $q->fetch_row()) $kuyruk[$r[0]] = intval($r[1]);
$sonBasari = ($q = $db->query("SELECT MAX(gonderim) FROM ik_fcm_kuyruk")) ? $q->fetch_row()[0] : null;
$cihazlar = []; $q = $db->query("SELECT c.id, c.kullanici_id, c.platform, c.olusturma, c.son_basari, k.ad_soyad FROM ik_mobil_cihazlar c LEFT JOIN kullanicilar k ON k.id = c.kullanici_id ORDER BY c.id DESC LIMIT 30"); if ($q) while ($r = $q->fetch_assoc()) $cihazlar[] = $r;
$kayit = []; $q = $db->query("SELECT * FROM ik_fcm_log ORDER BY id DESC LIMIT 40"); if ($q) while ($r = $q->fetch_assoc()) $kayit[] = $r;
$hatali = []; $q = $db->query("SELECT id, kullanici_id, bildirim_id, durum, deneme, sonraki, son_hata FROM ik_fcm_kuyruk WHERE durum IN ('hata','bekliyor','isleniyor') ORDER BY id DESC LIMIT 20"); if ($q) while ($r = $q->fetch_assoc()) $hatali[] = $r;
?><!doctype html><html lang="tr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Telefon bildirimi tanısı</title>
<style>body{font:14px system-ui,sans-serif;margin:0;padding:16px;background:#f4f6fb;color:#111}h1{font-size:18px}.k{background:#fff;border-radius:12px;padding:14px;margin:0 0 12px;box-shadow:0 1px 3px #0001}table{width:100%;border-collapse:collapse;font-size:12px}td,th{padding:5px 6px;border-bottom:1px solid #eee;text-align:left;vertical-align:top;word-break:break-word}.ok{color:#087f5b}.kot{color:#c92a2a}.sari{background:#fff3bf;padding:8px;border-radius:8px}button{padding:8px 14px;border:0;border-radius:8px;background:#4f46e5;color:#fff}</style></head><body>
<h1>Telefon bildirimi (FCM) tanısı</h1>
<?php if ($mesaj): ?><div class="k sari"><?= e($mesaj) ?></div><?php endif; ?>
<div class="k"><b>Firebase bağlantısı:</b> <?= $ayar ? '<span class="ok">Servis hesabı tanımlı</span> (proje: ' . e($ayar['project_id']) . ')' : '<span class="kot">config/fcm_servis.json yok veya geçersiz</span>' ?><br>
<b>openssl:</b> <?= function_exists('openssl_sign') ? 'var' : '<span class="kot">yok</span>' ?> · <b>curl:</b> <?= function_exists('curl_init') ? 'var' : '<span class="kot">yok</span>' ?><br>
<b>Kayıtlı telefon:</b> <?= $cihazSay ?> · <b>Son FCM kabulü:</b> <?= e($sonBasari ?: '—') ?><br>
<b>Kuyruk:</b> bekliyor <?= $kuyruk['bekliyor'] ?? 0 ?> · gönderildi <?= $kuyruk['gonderildi'] ?? 0 ?> · hata <?= $kuyruk['hata'] ?? 0 ?><br>
<small>"Gönderildi/kabul" = Firebase iletiyi aldı. Telefonda görüntülendiğini bu panel kanıtlayamaz; izin, pil kısıtı ve "zorla durdur" durumuna bağlıdır.</small></div>
<div class="k"><form method="post"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="islem" value="test"><button>Bana test bildirimi gönder</button></form>
<form method="post" style="margin-top:8px"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="islem" value="yeniden"><button>Hatalıları yeniden dene</button></form></div>
<div class="k"><b>Kayıtlı cihazlar</b><table><tr><th>Kullanıcı</th><th>Platform</th><th>Kayıt</th><th>Son kabul</th></tr><?php foreach ($cihazlar as $c): ?><tr><td><?= e($c['ad_soyad'] ?? $c['kullanici_id']) ?></td><td><?= e($c['platform']) ?></td><td><?= e($c['olusturma']) ?></td><td><?= e($c['son_basari'] ?: '—') ?></td></tr><?php endforeach; if (!$cihazlar) echo '<tr><td colspan="4">Kayıtlı telefon yok</td></tr>'; ?></table></div>
<div class="k"><b>Bekleyen / hatalı kuyruk</b><table><tr><th>#</th><th>Durum</th><th>Deneme</th><th>Sonraki</th><th>Hata</th></tr><?php foreach ($hatali as $r): ?><tr><td><?= $r['bildirim_id'] ?></td><td><?= e($r['durum']) ?></td><td><?= $r['deneme'] ?></td><td><?= e($r['sonraki']) ?></td><td><?= e($r['son_hata']) ?></td></tr><?php endforeach; if (!$hatali) echo '<tr><td colspan="5">Kayıt yok</td></tr>'; ?></table></div>
<div class="k"><b>Son gönderim kayıtları</b><table><tr><th>Zaman</th><th>Cihaz</th><th>Bildirim</th><th>HTTP</th><th>Sonuç</th><th>Ayrıntı</th></tr><?php foreach ($kayit as $r): ?><tr><td><?= e($r['zaman']) ?></td><td><?= e($r['cihaz_id']) ?></td><td><?= e($r['bildirim_id']) ?></td><td><?= e($r['http']) ?></td><td class="<?= $r['sonuc'] === 'kabul' ? 'ok' : 'kot' ?>"><?= e($r['sonuc']) ?></td><td><?= e($r['ayrinti']) ?></td></tr><?php endforeach; if (!$kayit) echo '<tr><td colspan="6">Kayıt yok</td></tr>'; ?></table></div>
</body></html>
