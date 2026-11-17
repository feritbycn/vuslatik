<?php
// cikis.php — Oturumu kapatır ve giriş sayfasına döner
require_once __DIR__ . '/config/baglan.php';
if (function_exists('pf_hatirla_sil')) pf_hatirla_sil($db);
$pf = !empty($_SESSION['pf_yonetici_id']);
unset($_SESSION['kurum_id'], $_SESSION['kullanici_id'], $_SESSION['ad_soyad'], $_SESSION['bekleyen']);
if (!$pf) { $_SESSION = []; if (ini_get('session.use_cookies')) { $p = session_get_cookie_params(); setcookie(session_name(), '', time() - 3600, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']); } @session_destroy(); }
header('Location: giris.php');
