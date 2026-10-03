<?php
declare(strict_types=1);
defined('BORFIS') || exit;

function base_url(): string
{
    if (CONFIG['base_url'] !== '') {
        return rtrim(CONFIG['base_url'], '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https://' : 'http://') . $host;
}

function json_ld(): string
{
    $s = settings();
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Restaurant',
        'name' => $s['site_name'],
        'url' => base_url() . '/',
        'telephone' => $s['phone'],
        'servesCuisine' => ['Burger', 'Wrap'],
        'priceRange' => $s['price_range'],
        'menu' => base_url() . '/menu/',
        'address' => [
            '@type' => 'PostalAddress',
            'streetAddress' => $s['address_line1'],
            'addressLocality' => 'Akyaka, Ula',
            'addressRegion' => 'Muğla',
            'postalCode' => '48640',
            'addressCountry' => 'TR',
        ],
        'openingHoursSpecification' => [[
            '@type' => 'OpeningHoursSpecification',
            'dayOfWeek' => array_values(array_diff(
                ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'],
                array_map(fn($d) => ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][(int) $d] ?? '', array_filter(explode(',', $s['closed_days'])))
            )),
            'opens' => $s['open_time'],
            'closes' => $s['close_time'] === '00:00' ? '23:59' : $s['close_time'],
        ]],
    ];
    if ($ig = instagram_url()) {
        $data['sameAs'] = [$ig];
    }
    if ($s['og_image'] !== '' || $s['hero_image'] !== '') {
        $data['image'] = base_url() . upload_url($s['og_image'] ?: $s['hero_image']);
    }
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
}

function render_head(string $title, string $description, string $path): void
{
    $s = settings();
    $red = valid_color($s['color_red'], '#CF3631');
    $yellow = valid_color($s['color_yellow'], '#F0B445');
    $dark = valid_color($s['color_dark'], '#1E1716');
    $paper = valid_color($s['color_paper'], '#FFF8F1');
    $canonical = base_url() . $path;
    $og = $s['og_image'] !== '' ? base_url() . upload_url($s['og_image']) : '';
    $favicon = $s['favicon'] !== '' ? upload_url($s['favicon']) : '/assets/favicon.svg';
    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    ?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<link rel="canonical" href="<?= e($canonical) ?>">
<meta name="theme-color" content="<?= e($red) ?>">
<meta property="og:type" content="restaurant">
<meta property="og:locale" content="tr_TR">
<meta property="og:site_name" content="<?= e($s['site_name']) ?>">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($og): ?><meta property="og:image" content="<?= e($og) ?>"><meta name="twitter:card" content="summary_large_image"><?php endif; ?>
<link rel="icon" href="<?= e($favicon) ?>">
<link rel="preload" href="/assets/fonts/titan-one-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="/assets/fonts/figtree-latin-400-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset('site.css')) ?>">
<style>:root{--red:<?= $red ?>;--yellow:<?= $yellow ?>;--dark:<?= $dark ?>;--paper:<?= $paper ?>}</style>
<script type="application/ld+json"><?= json_ld() ?></script>
<script src="<?= e(asset('site.js')) ?>" defer></script>
</head>
<body>
<a class="skip" href="#icerik">İçeriğe geç</a>
<?php
}

function render_logo(string $class = ''): void
{
    $s = settings();
    if ($s['logo'] !== '') {
        echo '<img class="logo-img ' . e($class) . '" src="' . e(upload_url($s['logo'])) . '" alt="' . e($s['site_name']) . '" width="160" height="64">';
        return;
    }
    echo '<span class="logo-script ' . e($class) . '">' . e($s['site_short']) . '</span>';
}

function render_header(string $active): void
{
    $s = settings();
    $status = open_status();
    if ($s['announcement'] !== ''): ?>
<div class="announce" role="status"><?= e($s['announcement']) ?></div>
<?php endif;
    if (flag('show_ticker') && $s['ticker'] !== ''):
        $items = array_filter(array_map('trim', explode('★', $s['ticker']))); ?>
<div class="ticker" aria-hidden="true"><div class="ticker-track">
<?php for ($i = 0; $i < 2; $i++): ?><span><?php foreach ($items as $it): ?><?= e($it) ?> ★ <?php endforeach; ?></span><?php endfor; ?>
</div></div>
<?php endif; ?>
<header class="site-header">
<div class="wrap header-row">
<a class="brand" href="/" aria-label="<?= e($s['site_name']) ?> ana sayfa"><?php render_logo(); ?><span class="tag-chip"><?= e($s['tagline']) ?></span></a>
<nav class="nav" aria-label="Ana menü">
<a href="/menu/"<?= $active === 'menu' ? ' aria-current="page"' : '' ?>>MENÜ</a>
<a href="/#bizi-bul">BİZİ BUL</a>
<?php if ($ig = instagram_url()): ?><a href="<?= e($ig) ?>" rel="noopener" target="_blank">INSTAGRAM</a><?php endif; ?>
</nav>
<span class="status-pill <?= $status['open'] ? 'is-open' : 'is-closed' ?>"><span class="dot"></span><?= e($status['label']) ?></span>
</div>
</header>
<main id="icerik">
<?php
}

function render_footer(): void
{
    $s = settings(); ?>
</main>
<div class="checker" aria-hidden="true"></div>
<footer class="site-footer">
<div class="wrap">
<div class="footer-logo"><?php render_logo('big'); ?></div>
<div class="footer-row">
<address><?= e($s['address_line1']) ?>, <?= e($s['address_line2']) ?> · <?= e($s['hours_text']) ?><?php if ($s['phone']): ?> · <a href="<?= e(phone_href()) ?>"><?= e($s['phone']) ?></a><?php endif; ?></address>
<nav class="footer-nav" aria-label="Alt menü">
<a href="/menu/">Menü</a>
<?php if ($ig = instagram_url()): ?><a href="<?= e($ig) ?>" rel="noopener" target="_blank">Instagram</a><?php endif; ?>
<a href="<?= e($s['maps_url']) ?>" rel="noopener" target="_blank">Yol tarifi</a>
</nav>
</div>
<p class="footer-note">© <?= date('Y') ?> <?= e($s['site_name']) ?> · <?= e($s['footer_text']) ?></p>
</div>
</footer>
<?php if ($s['whatsapp'] !== ''): ?>
<a class="wa-fab" href="https://wa.me/<?= e(preg_replace('/\D+/', '', $s['whatsapp'])) ?>" target="_blank" rel="noopener" aria-label="WhatsApp ile yaz">
<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 11.5a8.4 8.4 0 0 1-12.5 7.4L3 21l2.1-5.3A8.4 8.4 0 1 1 21 11.5z"/></svg></a>
<?php endif; ?>
<dialog class="lightbox" aria-label="Fotoğraf">
<button type="button" class="lb-close" aria-label="Kapat">×</button>
<div class="lb-stage"><img class="lb-img" alt=""></div>
<p class="lb-hint">İki parmakla ya da çift dokunarak büyüt</p>
</dialog>
</body>
</html>
<?php
}

function burger_svg(string $class = 'burger-art'): string
{
    return <<<SVG
<svg class="{$class}" viewBox="0 0 400 360" role="img" aria-label="Kocaman bir burger çizimi">
<path d="M40 150 C40 40 360 40 360 150 Z" fill="#E08A2C" stroke="currentColor" stroke-width="6" stroke-linejoin="round"/>
<path d="M90 92 C110 70 150 60 180 62" fill="none" stroke="#F4B866" stroke-width="10" stroke-linecap="round"/>
<g fill="#FFF3D6" stroke="currentColor" stroke-width="2.5"><ellipse cx="140" cy="100" rx="9" ry="5" transform="rotate(-20 140 100)"/><ellipse cx="200" cy="78" rx="9" ry="5"/><ellipse cx="255" cy="96" rx="9" ry="5" transform="rotate(20 255 96)"/><ellipse cx="175" cy="120" rx="9" ry="5" transform="rotate(10 175 120)"/><ellipse cx="230" cy="124" rx="9" ry="5" transform="rotate(-15 230 124)"/><ellipse cx="300" cy="126" rx="9" ry="5" transform="rotate(25 300 126)"/><ellipse cx="105" cy="128" rx="9" ry="5" transform="rotate(-30 105 128)"/></g>
<path d="M28 160 Q60 140 92 162 Q124 182 156 160 Q188 140 220 162 Q252 182 284 160 Q316 140 348 162 Q366 172 374 160 L370 180 L30 180 Z" fill="#5CB646" stroke="currentColor" stroke-width="6" stroke-linejoin="round"/>
<rect x="36" y="176" width="328" height="22" rx="11" fill="#E8463C" stroke="currentColor" stroke-width="6"/>
<path d="M30 196 L370 196 L352 222 Q346 248 336 222 L300 214 Q292 262 280 216 L220 214 Q212 246 202 216 L140 214 Q132 270 120 216 L70 214 Q62 240 54 216 Z" fill="#F7C531" stroke="currentColor" stroke-width="6" stroke-linejoin="round"/>
<rect x="32" y="204" width="336" height="46" rx="22" fill="#5A2E1A" stroke="currentColor" stroke-width="6"/>
<path d="M70 222 H120 M160 230 H230 M270 222 H330" stroke="#3A1C10" stroke-width="5" stroke-linecap="round"/>
<path d="M40 262 L360 262 C360 318 40 318 40 262 Z" fill="#E08A2C" stroke="currentColor" stroke-width="6" stroke-linejoin="round"/>
</svg>
SVG;
}

function drip_svg(string $fill = 'var(--yellow)'): string
{
    return '<svg class="drip" viewBox="0 0 1440 90" preserveAspectRatio="none" aria-hidden="true"><path style="fill:' . $fill . '" d="M0 90 V40 C60 40 70 10 120 10 C170 10 160 70 210 70 C260 70 250 20 300 20 C360 20 350 80 420 80 C480 80 470 30 530 30 C590 30 580 60 640 60 C700 60 690 0 760 0 C830 0 810 75 880 75 C940 75 930 25 990 25 C1050 25 1040 65 1100 65 C1160 65 1150 15 1210 15 C1270 15 1260 70 1320 70 C1380 70 1380 40 1440 40 V90 Z"/></svg>';
}

function product_card(array $p, int $i = 0, bool $fallbackArt = true): void
{
    $rot = [10, -8, 6, -10, 8, -6][$i % 6];
    $sticker = ['s-red', 's-yellow', 's-white'][$i % 3];
    $showPrice = flag('show_prices') && $p['price'] !== null;
    $hasMedia = $p['image'] !== '' || $fallbackArt; ?>
<article class="card<?= $p['is_available'] ? '' : ' is-out' ?><?= $hasMedia ? '' : ' no-media' ?><?= $showPrice ? '' : ' no-price' ?>" style="--rot:<?= $rot ?>deg">
<?php if ($hasMedia): ?>
<div class="card-media">
<?php if ($p['image'] !== ''): ?>
<img src="<?= e(upload_url($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy" decoding="async" width="800" height="600" data-zoom>
<?php else: ?>
<?= burger_svg('card-fallback') ?>
<?php endif; ?>
</div>
<?php endif; ?>
<?php if ($showPrice): ?><div class="price-sticker <?= $sticker ?>"><?= e(format_price((float) $p['price'])) ?></div><?php endif; ?>
<?php if ($p['badge'] !== ''): ?><span class="badge"><?= e($p['badge']) ?></span><?php endif; ?>
<div class="card-body">
<h3><?= e($p['name']) ?></h3>
<?php if ($p['description'] !== ''): ?><p><?= e($p['description']) ?></p><?php endif; ?>
<?php if (!$p['is_available']): ?><span class="out-tag">BUGÜN TÜKENDİ :(</span><?php endif; ?>
</div>
</article>
<?php
}
