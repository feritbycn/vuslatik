<?php
// talep.php — deneme talebi formu (JSON yanıt döner)
require_once __DIR__ . '/inc/ortak.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
function bitir($ok, $mesaj = '', $ek = [], $kod = 200) { http_response_code($kod); echo json_encode(array_merge(['ok' => $ok, 'hata' => $ok ? null : $mesaj], $ek), JSON_UNESCAPED_UNICODE); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') bitir(false, 'Geçersiz istek.', [], 405);
if (!ts_csrf_ok($_POST['csrf'] ?? '')) bitir(false, 'Sayfanın süresi dolmuş. Sayfayı yenileyip tekrar deneyin.', [], 403);
// Bot önlemleri: gizli alan dolu / form çok hızlı ya da çok eski gönderildi
$t = (int)($_POST['t'] ?? 0); $s = (string)($_POST['s'] ?? '');
if (!empty($_POST['web']) || !hash_equals(hash_hmac('sha256', (string)$t, ts_csrf()), $s) || time() - $t < 3 || time() - $t > 7200) bitir(true, '', ['ad' => 'siz']); // bota başarılıymış gibi yanıt
if (!ts_hiz('talep', 4, 3600)) bitir(false, 'Çok sık deneme yaptınız. Lütfen biraz sonra tekrar deneyin ya da WhatsApp’tan yazın.', [], 429);

$temiz = function ($v, $n) { $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', (string)$v); return mb_substr(trim($v), 0, $n); };
$ad = $temiz($_POST['ad'] ?? '', 80); $firma = $temiz($_POST['firma'] ?? '', 120);
$eposta = $temiz($_POST['eposta'] ?? '', 120); $telefon = $temiz($_POST['telefon'] ?? '', 25);
$personel = $temiz($_POST['personel'] ?? '', 20); $mesaj = $temiz($_POST['mesaj'] ?? '', 800);
$kaynak = $temiz($_POST['kaynak'] ?? '', 200); $sayfa = $temiz($_POST['sayfa'] ?? '', 120);
if (mb_strlen($ad) < 2) bitir(false, 'Adınızı ve soyadınızı yazın.');
if (mb_strlen($firma) < 2) bitir(false, 'Şirket adını yazın.');
if (!filter_var($eposta, FILTER_VALIDATE_EMAIL)) bitir(false, 'E-posta adresi geçerli görünmüyor. Örnek: ad@sirket.com');
if (strlen(preg_replace('/\D/', '', $telefon)) < 10) bitir(false, 'Telefon numarasını alan koduyla yazın. Örnek: 0532 111 22 33');
if (empty($_POST['kvkk'])) bitir(false, 'Devam etmek için aydınlatma metnini onaylayın.');

$kayit = ['id' => bin2hex(random_bytes(5)), 'zaman' => date('Y-m-d H:i:s'), 'ad' => $ad, 'firma' => $firma, 'eposta' => $eposta, 'telefon' => $telefon, 'personel' => $personel, 'mesaj' => $mesaj,
    'kaynak' => $kaynak, 'sayfa' => $sayfa, 'durum' => 'yeni', 'not' => '', 'ip_ozet' => substr(hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . 'ts'), 0, 12)];
// aynı anda gelen iki talep birbirini ezmesin
$kilit = fopen(TS_VERI . '/.talep.kilit', 'c'); if (!$kilit) { @mkdir(TS_VERI, 0750, true); $kilit = fopen(TS_VERI . '/.talep.kilit', 'c'); }
if ($kilit) flock($kilit, LOCK_EX);
$liste = ts_json_oku('talepler.json', []);
array_unshift($liste, $kayit); $liste = array_slice($liste, 0, 5000);
$yazildi = ts_json_yaz('talepler.json', $liste);
if ($kilit) { flock($kilit, LOCK_UN); fclose($kilit); }
if (!$yazildi) bitir(false, 'Talebiniz kaydedilemedi. Lütfen WhatsApp’tan ya da telefonla ulaşın.', [], 500);

// Yöneticiye e-posta (sunucuda mail() açıksa)
$hedef = ts_ayar('talep_eposta', ts_ayar('eposta', ''));
if ($hedef !== '' && filter_var($hedef, FILTER_VALIDATE_EMAIL)) {
    $govde = "Yeni deneme talebi\n\nAd soyad: $ad\nŞirket: $firma\nE-posta: $eposta\nTelefon: $telefon\nPersonel sayısı: $personel\nSayfa: $sayfa\nKaynak: $kaynak\n\nNot:\n$mesaj\n\nTüm talepler: " . ts_url('yonetim/') . "\n";
    $baslik = '=?UTF-8?B?' . base64_encode('Yeni deneme talebi: ' . $firma) . '?=';
    $host = preg_replace('/^www\./', '', parse_url(ts_taban(), PHP_URL_HOST) ?: 'localhost');
    $ust = "From: " . ts_ayar('marka') . " <bildirim@$host>\r\nReply-To: " . str_replace(["\r", "\n"], '', $eposta) . "\r\nContent-Type: text/plain; charset=UTF-8\r\n";
    @mail($hedef, $baslik, $govde, $ust);
}
bitir(true, '', ['ad' => explode(' ', $ad)[0]]);
