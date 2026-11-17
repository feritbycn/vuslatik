<?php
require_once __DIR__ . '/inc/ortak.php';
header('Content-Type: application/manifest+json; charset=utf-8');
echo json_encode(['name' => ts_ayar('marka'), 'short_name' => ts_ayar('marka'), 'description' => ts_ayar('aciklama'), 'start_url' => './', 'display' => 'browser', 'background_color' => '#F4F9F8', 'theme_color' => '#0D3F4A', 'lang' => 'tr',
  'icons' => [['src' => 'assets/img/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png'], ['src' => 'assets/img/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png']]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
