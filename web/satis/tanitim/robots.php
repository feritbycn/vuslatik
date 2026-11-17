<?php
require_once __DIR__ . '/inc/ortak.php';
header('Content-Type: text/plain; charset=utf-8');
if (ts_ayar('robots_index', '1') !== '1') { echo "User-agent: *\nDisallow: /\n"; exit; }
echo "User-agent: *\nAllow: /\nDisallow: /yonetim/\nDisallow: /data/\nDisallow: /talep.php\n\nSitemap: " . ts_url('sitemap.xml') . "\n";
