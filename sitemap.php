<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
require APP_DIR . '/layout.php';
header('Content-Type: application/xml; charset=utf-8');
$base = base_url();
$last = date('Y-m-d', (int) @filemtime(DATA_DIR . '/borfis.sqlite') ?: time());
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<url><loc><?= e($base) ?>/</loc><lastmod><?= $last ?></lastmod><priority>1.0</priority></url>
<url><loc><?= e($base) ?>/menu/</loc><lastmod><?= $last ?></lastmod><priority>0.9</priority></url>
</urlset>
