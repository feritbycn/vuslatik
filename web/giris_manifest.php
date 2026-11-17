<?php
// giris_manifest.php — "Ana ekrana ekle" için Giriş-Çıkış uygulama bildirimi (PWA manifest). Oturum gerektirmez, gizli veri içermez.
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=3600');
echo json_encode([
    'name' => 'Giriş-Çıkış', 'short_name' => 'Giriş-Çıkış', 'description' => 'Kaydırarak işe giriş ve çıkış',
    'id' => 'giris_cikis.php', 'start_url' => 'giris_cikis.php', 'scope' => './', 'display' => 'fullscreen', 'display_override' => ['fullscreen', 'standalone'], 'orientation' => 'portrait',
    'background_color' => '#1e1b4b', 'theme_color' => '#1e1b4b', 'lang' => 'tr',
    'icons' => [['src' => 'assets/img/ik-bildirim-ikon.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'], ['src' => 'assets/img/ik-uygulama-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any']],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
