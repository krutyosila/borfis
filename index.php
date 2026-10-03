<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require APP_DIR . '/layout.php';

$s = settings();
$categories = array_values(array_filter(visible_categories(), fn($c) => (int) $c['show_on_home'] === 1));
$featured = featured_products();
$reviews = flag('show_reviews') ? visible_reviews() : [];
$gallery = flag('show_gallery') ? visible_gallery() : [];
$slogan = array_values(array_filter(preg_split('/\s+/', $s['slogan'])));
$status = open_status();

render_head($s['seo_title'], $s['seo_description'], '/');
render_header('home');
?>

<section class="hero">
<div class="wrap hero-row">
<div class="hero-copy">
<span class="sticker wob"><?= e($s['hero_badge']) ?></span>
<h1 class="mega">
<span><?= e($s['hero_line1']) ?></span>
<span class="outlined"><?= e($s['hero_line2']) ?></span>
<span><?= e($s['hero_line3']) ?></span>
</h1>
<p class="hero-text"><?= e($s['hero_text']) ?></p>
<div class="btn-row">
<a class="btn btn-white" href="/menu/">MENÜYÜ AÇ</a>
<a class="btn btn-dark" href="<?= e($s['maps_url']) ?>" target="_blank" rel="noopener">YOL TARİFİ</a>
</div>
</div>
<div class="hero-art">
<div class="sunburst" aria-hidden="true"></div>
<?php if ($s['hero_image'] !== ''): ?>
<img class="hero-photo" src="<?= e(upload_url($s['hero_image'])) ?>" alt="<?= e($s['site_name']) ?>" width="560" height="560" fetchpriority="high" data-zoom>
<?php else: ?>
<?= burger_svg() ?>
<?php endif; ?>
<div class="hours-badge wob"><span>HER GÜN</span><strong><?= e(substr($s['open_time'], 0, 2)) ?>–<?= e($s['close_time'] === '00:00' ? '24' : substr($s['close_time'], 0, 2)) ?></strong><span><?= $status['open'] ? 'AÇIĞIZ' : 'AÇIK' ?></span></div>
<?php if ($s['hero_sticker'] !== ''): ?><div class="script-sticker"><?= e($s['hero_sticker']) ?></div><?php endif; ?>
</div>
</div>
<?= drip_svg() ?>
</section>

<?php if ($categories): ?>
<section class="sec-yellow">
<div class="wrap stack">
<div class="sec-head">
<h2 class="title"><?= nl2br(e(str_replace(' NE ', " NE\n", $s['categories_title']))) ?></h2>
<span class="script-note"><?= e($s['categories_note']) ?></span>
</div>
<div class="cat-grid">
<?php foreach ($categories as $i => $c): ?>
<a class="cat-tile t<?= $i % 4 ?>" href="/menu/#k<?= (int) $c['id'] ?>">
<span class="cat-name"><?= e($c['name']) ?></span>
<span class="cat-foot"><?= e($c['tagline']) ?> <b aria-hidden="true">→</b></span>
</a>
<?php endforeach; ?>
</div>
</div>
</section>
<?php endif; ?>

<div class="checker" aria-hidden="true"></div>

<?php if ($featured): ?>
<section class="sec-dark">
<div class="wrap stack">
<div>
<span class="script-note yellow"><?= e($s['featured_kicker']) ?></span>
<h2 class="title"><?= e($s['featured_title']) ?></h2>
</div>
<div class="card-grid">
<?php foreach ($featured as $i => $p) { product_card($p, $i); } ?>
</div>
<a class="btn btn-red center" href="/menu/">TÜM MENÜ →</a>
</div>
</section>
<?php endif; ?>

<?php if ($slogan): ?>
<section class="slogan" aria-label="Slogan">
<div class="wrap slogan-row">
<?php foreach ($slogan as $i => $w): ?><span class="sw<?= $i % 3 ?>"><?= e($w) ?></span><?php endforeach; ?>
</div>
</section>
<?php endif; ?>

<?php if ($reviews): ?>
<section class="sec-paper dots">
<div class="wrap stack">
<div class="sec-head">
<h2 class="title"><?= e($s['reviews_title']) ?></h2>
<?php if ($s['google_rating'] !== ''): ?>
<a class="rating-chip" href="<?= e($s['google_reviews_url']) ?>" target="_blank" rel="noopener">
<strong><?= e($s['google_rating']) ?></strong><span class="stars" aria-hidden="true">★★★★★</span><span><?= e($s['google_review_count']) ?> Google yorumu</span>
</a>
<?php endif; ?>
</div>
<div class="review-grid">
<?php foreach ($reviews as $i => $r): ?>
<figure class="review" style="--rot:<?= [-2, 1.5, -1, 2][$i % 4] ?>deg">
<div class="stars" aria-label="<?= (int) $r['rating'] ?> yıldız"><?= str_repeat('★', max(1, min(5, (int) $r['rating']))) ?></div>
<blockquote><?= e($r['body']) ?></blockquote>
<figcaption><?= e($r['author']) ?> · <?= e($r['source']) ?></figcaption>
</figure>
<?php endforeach; ?>
</div>
</div>
</section>
<?php endif; ?>

<section id="bizi-bul" class="sec-red">
<div class="wrap find-row">
<div class="receipt-wrap">
<div class="receipt">
<div class="r-logo"><?= e($s['site_short']) ?></div>
<div class="r-sub"><?= e($s['tagline']) ?> · AKYAKA</div>
<hr>
<div class="r-line"><span>ADRES</span><span><?= e($s['address_line1']) ?><br><?= e($s['address_line2']) ?></span></div>
<div class="r-line"><span>SAAT</span><span><?= e($s['open_time']) ?> – <?= e($s['close_time']) ?></span></div>
<div class="r-line"><span>GÜNLER</span><span><?= e(closed_days_label()) ?></span></div>
<?php if ($s['phone'] !== ''): ?><div class="r-line"><span>TELEFON</span><a href="<?= e(phone_href()) ?>"><?= e($s['phone']) ?></a></div><?php endif; ?>
<?php if (flag('outdoor_seating')): ?><div class="r-line"><span>OTURMA</span><span>AÇIK HAVA ✓</span></div><?php endif; ?>
<?php if ($s['price_range'] !== ''): ?><div class="r-line"><span>KİŞİ BAŞI</span><span><?= e($s['price_range']) ?></span></div><?php endif; ?>
<hr>
<div class="r-line r-total"><span>TOPLAM</span><span>MUTLULUK</span></div>
<div class="r-foot">*** <?= e(mb_strtoupper($s['footer_text'])) ?> ***</div>
</div>
</div>
<div class="find-copy">
<h2 class="title">BİZİ <span class="yellow">BUL</span></h2>
<?php if ($s['about_text'] !== ''): ?>
<p class="about"><?= e($s['about_text']) ?></p>
<?php endif; ?>
<?php if ($src = maps_embed_src()): ?>
<div class="map-box" data-map-src="<?= e($src) ?>">
<button type="button" class="map-load">HARİTAYI YÜKLE</button>
<noscript><a href="<?= e($s['maps_url']) ?>">Haritada aç</a></noscript>
</div>
<?php endif; ?>
<div class="btn-row">
<a class="btn btn-yellow" href="<?= e($s['maps_url']) ?>" target="_blank" rel="noopener">YOL TARİFİ AL →</a>
<?php if ($s['phone'] !== ''): ?><a class="btn btn-white" href="<?= e(phone_href()) ?>">ARA: <?= e($s['phone']) ?></a><?php endif; ?>
</div>
</div>
</div>
</section>

<?php if ($gallery || instagram_url()): ?>
<section class="sec-yellow ig">
<div class="wrap stack">
<div class="sec-head">
<h2 class="title">@<?= e(mb_strtoupper(ltrim($s['instagram'], '@'))) ?></h2>
<?php if ($ig = instagram_url()): ?><a class="btn btn-dark" href="<?= e($ig) ?>" target="_blank" rel="noopener">TAKİP ET →</a><?php endif; ?>
</div>
<?php if ($gallery): ?>
<div class="polaroids">
<?php foreach ($gallery as $i => $g): ?>
<figure class="polaroid" style="--rot:<?= [-4, 3, -2, 5, -3, 2][$i % 6] ?>deg">
<img src="<?= e(upload_url($g['image'])) ?>" alt="<?= e($g['caption'] ?: $s['site_name']) ?>" loading="lazy" decoding="async" width="600" height="600" data-zoom>
<?php if ($g['caption'] !== ''): ?><figcaption><?= e($g['caption']) ?></figcaption><?php endif; ?>
</figure>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</section>
<?php endif; ?>

<?php render_footer();
