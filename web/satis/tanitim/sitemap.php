<?php
require_once __DIR__ . '/inc/kafa.php';
header('Content-Type: application/xml; charset=utf-8');
$sayfalar = [['', filemtime(__DIR__ . '/index.php'), '1.0', 'weekly']];
foreach (array_keys(ts_gruplar()) as $s) $sayfalar[] = [$s, max(filemtime(__DIR__ . '/inc/icerik.php'), filemtime(__DIR__ . '/konu.php')), '0.8', 'monthly'];
$sayfalar[] = ['gizlilik', filemtime(__DIR__ . '/gizlilik.php'), '0.3', 'yearly'];
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($sayfalar as $p) echo '  <url><loc>' . h(ts_url($p[0])) . '</loc><lastmod>' . date('Y-m-d', $p[1]) . '</lastmod><changefreq>' . $p[3] . '</changefreq><priority>' . $p[2] . "</priority></url>\n";
echo '</urlset>' . "\n";
