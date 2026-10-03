<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/layout.php';

$s = settings();
$categories = visible_categories();
$byCat = [];
foreach (visible_products() as $p) {
    $byCat[(int) $p['category_id']][] = $p;
}

render_head('Menü — ' . $s['site_name'] . ' Akyaka', 'Börfi\'s Burger menüsü: burgerler, wrapler, yanlar ve içecekler. ' . $s['hours_text'] . '.', '/menu/');
render_header('menu');
?>
<section class="hero hero-menu">
<div class="wrap stack">
<div class="sec-head">
<h1 class="mega menu-title">MEN<span class="outlined">Ü</span></h1>
<span class="sticker tilt"><?= e(mb_strtoupper($s['hours_text'])) ?></span>
</div>
<nav class="cat-tabs" aria-label="Kategoriler">
<?php foreach ($categories as $c): if (empty($byCat[(int) $c['id']])) continue; ?>
<a class="tab" href="#k<?= (int) $c['id'] ?>"><?= e($c['name']) ?></a>
<?php endforeach; ?>
</nav>
</div>
<?= drip_svg() ?>
</section>

<div class="menu-body">
<?php $any = false; foreach ($categories as $c): $items = $byCat[(int) $c['id']] ?? []; if (!$items) continue; $any = true; ?>
<section class="wrap menu-cat" id="k<?= (int) $c['id'] ?>">
<div class="menu-cat-head">
<h2 class="title"><?= e($c['name']) ?></h2>
<?php if ($c['tagline'] !== ''): ?><span class="script-note"><?= e($c['tagline']) ?></span><?php endif; ?>
</div>
<div class="card-grid light">
<?php foreach ($items as $i => $p) { product_card($p, $i, false); } ?>
</div>
</section>
<?php endforeach; ?>
<?php if (!$any): ?>
<section class="wrap menu-cat"><p class="empty">Menü çok yakında burada!</p></section>
<?php endif; ?>
<?php if ($s['menu_note'] !== ''): ?><p class="wrap menu-note"><?= e($s['menu_note']) ?></p><?php endif; ?>
</div>
<?php render_footer();
