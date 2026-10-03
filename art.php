<?php
declare(strict_types=1);

// Ürün illüstrasyonu (SVG). Adres içerikten türetilen sürümle değişir, bu yüzden uzun süre önbelleklenir.
require __DIR__ . '/app/bootstrap.php';

$id = (int) ($_GET['p'] ?? 0);
$st = db()->prepare('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?');
$st->execute([$id]);
$p = $st->fetch();
if (!$p) {
    http_response_code(404);
    exit;
}
header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
echo product_art_svg($p, (string) $p['category_name']);
