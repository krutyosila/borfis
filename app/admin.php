<?php
declare(strict_types=1);
defined('BORFIS') || exit;

const SETTING_GROUPS = [
    'genel' => 'Genel',
    'iletisim' => 'İletişim & adres',
    'saatler' => 'Çalışma saatleri',
    'metinler' => 'Ana sayfa metinleri',
    'gorunum' => 'Görünüm',
    'seo' => 'Google / SEO',
];

/* ================= Kimlik doğrulama ================= */

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function attempt_login(string $user, string $password): ?string
{
    $ip = client_ip();
    $since = time() - 15 * 60;
    db()->prepare('DELETE FROM login_attempts WHERE at < ?')->execute([time() - 86400]);
    $st = db()->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND at > ?');
    $st->execute([$ip, $since]);
    if ((int) $st->fetchColumn() >= 5) {
        return 'Çok fazla hatalı deneme. 15 dakika sonra tekrar deneyin.';
    }
    $ok = hash_equals(CONFIG['admin_user'], $user) && password_verify($password, CONFIG['admin_password_hash']);
    if (!$ok) {
        db()->prepare('INSERT INTO login_attempts(ip, at) VALUES(?, ?)')->execute([$ip, time()]);
        usleep(400000);
        return 'Kullanıcı adı veya şifre hatalı.';
    }
    db()->prepare('DELETE FROM login_attempts WHERE ip = ?')->execute([$ip]);
    session_regenerate_id(true);
    $_SESSION['admin'] = true;
    $_SESSION['admin_hash'] = substr(CONFIG['admin_password_hash'], -16);
    return null;
}

function write_config(array $changes): void
{
    $file = DATA_DIR . '/config.php';
    $current = is_file($file) ? (require $file) : [];
    $data = array_merge(is_array($current) ? $current : [], $changes);
    $php = "<?php\n// Börfi's site ayarları — elle düzenlemeyin, panelden değiştirin.\nreturn " . var_export($data, true) . ";\n";
    if (file_put_contents($file, $php, LOCK_EX) === false) {
        throw new RuntimeException('Ayar dosyası yazılamadı: ' . $file);
    }
    @chmod($file, 0640);
    if (function_exists('opcache_invalidate')) {
        opcache_invalidate($file, true);
    }
}

/* ================= Sayfa iskeleti ================= */

function admin_head(string $title): void
{ ?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($title) ?> · Börfi's Panel</title>
<link rel="icon" href="/assets/favicon.svg">
<link rel="stylesheet" href="<?= e(asset('admin.css')) ?>">
<script src="<?= e(asset('admin.js')) ?>" defer></script>
</head>
<?php }

function admin_setup_page(): void
{
    admin_head('Kurulum'); ?>
<body class="auth"><main class="auth-card">
<div class="auth-logo">Börfi's <span>PANEL</span></div>
<h1>Kurulum gerekli</h1>
<p>Yönetici şifresi henüz belirlenmemiş. Sunucuda şu komutu çalıştırın:</p>
<pre>php <?= e(PUBLIC_DIR) ?>/app/cli.php sifre</pre>
</main></body></html>
<?php }

function admin_login_page(?string $error): void
{
    admin_head('Giriş'); ?>
<body class="auth"><main class="auth-card">
<div class="auth-logo">Börfi's <span>PANEL</span></div>
<?php if ($error): ?><div class="alert err" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" class="form" autocomplete="on">
<?= csrf_field() ?>
<label>Kullanıcı adı<input name="user" required autocomplete="username" autofocus></label>
<label>Şifre<input name="password" type="password" required autocomplete="current-password"></label>
<button class="btn primary" type="submit">Giriş yap</button>
</form>
<a class="back" href="/">← Siteye dön</a>
</main></body></html>
<?php }

function admin_open(string $page): void
{
    $nav = [
        'panel' => ['Genel bakış', 'M3 12l9-8 9 8M5 10v10h14V10'],
        'urunler' => ['Menü ürünleri', 'M4 11c0-5 16-5 16 0zM3 14h18M4 17h16c0 3-16 3-16 0z'],
        'kategoriler' => ['Kategoriler', 'M4 5h16M4 12h16M4 19h10'],
        'galeri' => ['Galeri', 'M4 5h16v14H4zM8 13l3 3 5-6 4 5'],
        'yorumlar' => ['Yorumlar', 'M4 5h16v11H9l-5 4z'],
        'ayarlar' => ['Site ayarları', 'M12 8a4 4 0 100 8 4 4 0 000-8zM4 12h2M18 12h2M12 4v2M12 18v2'],
        'sifre' => ['Şifre', 'M6 11h12v9H6zM9 11V8a3 3 0 016 0v3'],
    ];
    $active = $page === 'urun' ? 'urunler' : $page;
    admin_head($nav[$active][0] ?? 'Panel'); ?>
<body>
<div class="shell">
<aside class="side">
<a class="side-logo" href="/admin/">Börfi's <span>PANEL</span></a>
<nav aria-label="Panel menüsü">
<?php foreach ($nav as $key => [$label, $icon]): ?>
<a href="/admin/?p=<?= $key ?>"<?= $active === $key ? ' aria-current="page"' : '' ?>>
<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="<?= $icon ?>"/></svg><?= e($label) ?></a>
<?php endforeach; ?>
</nav>
<div class="side-foot">
<a href="/" target="_blank" rel="noopener">Siteyi aç ↗</a>
<form method="post" action="/admin/?p=cikis"><?= csrf_field() ?><button type="submit" class="linkbtn">Çıkış yap</button></form>
</div>
</aside>
<main class="main">
<?php if ($f = flash()): ?><div class="alert <?= $f['type'] === 'err' ? 'err' : 'ok' ?>" role="status"><?= e($f['message']) ?></div><?php endif;
}

function admin_close(): void
{ ?>
</main>
</div>
</body>
</html>
<?php }

function page_title(string $title, string $actions = ''): void
{
    echo '<div class="page-head"><h1>' . e($title) . '</h1>' . $actions . '</div>';
}

function toggle_button(string $page, int $id, string $field, bool $on, string $onLabel, string $offLabel): string
{
    return '<form method="post" action="/admin/?p=' . $page . '" class="inline">' . csrf_field()
        . '<input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="' . $id . '">'
        . '<input type="hidden" name="field" value="' . e($field) . '">'
        . '<button type="submit" class="pill ' . ($on ? 'on' : 'off') . '" aria-pressed="' . ($on ? 'true' : 'false') . '">' . e($on ? $onLabel : $offLabel) . '</button></form>';
}

function delete_button(string $page, int $id, string $what): string
{
    return '<form method="post" action="/admin/?p=' . $page . '" class="inline" data-confirm="' . e($what) . ' silinsin mi? Bu işlem geri alınamaz.">' . csrf_field()
        . '<input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="' . $id . '">'
        . '<button type="submit" class="btn small danger">Sil</button></form>';
}

function move_buttons(string $page, int $id): string
{
    $f = fn(string $dir, string $label, string $aria) => '<form method="post" action="/admin/?p=' . $page . '" class="inline">' . csrf_field()
        . '<input type="hidden" name="action" value="move"><input type="hidden" name="id" value="' . $id . '"><input type="hidden" name="dir" value="' . $dir . '">'
        . '<button type="submit" class="icon-btn" aria-label="' . $aria . '">' . $label . '</button></form>';
    return '<span class="move">' . $f('up', '↑', 'Yukarı taşı') . $f('down', '↓', 'Aşağı taşı') . '</span>';
}

/* ================= İşlemler ================= */

const TOGGLE_FIELDS = [
    'products' => ['is_featured', 'is_available', 'is_visible'],
    'categories' => ['is_visible', 'show_on_home'],
    'gallery' => ['is_visible'],
    'reviews' => ['is_visible'],
];

function page_table(string $page): ?string
{
    return ['urunler' => 'products', 'urun' => 'products', 'kategoriler' => 'categories', 'galeri' => 'gallery', 'yorumlar' => 'reviews'][$page] ?? null;
}

/** Sıralamayı yeniden numaralandırır, sonra seçili kaydı bir yukarı/aşağı taşır. */
function move_row(string $table, int $id, string $dir): void
{
    $scope = '';
    $params = [];
    if ($table === 'products') {
        $cat = db()->prepare('SELECT category_id FROM products WHERE id = ?');
        $cat->execute([$id]);
        $scope = ' WHERE category_id = ?';
        $params[] = (int) $cat->fetchColumn();
    }
    $st = db()->prepare("SELECT id FROM {$table}{$scope} ORDER BY sort, id");
    $st->execute($params);
    $ids = array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    $pos = array_search($id, $ids, true);
    if ($pos === false) {
        return;
    }
    $swap = $dir === 'up' ? $pos - 1 : $pos + 1;
    if ($swap >= 0 && $swap < count($ids)) {
        [$ids[$pos], $ids[$swap]] = [$ids[$swap], $ids[$pos]];
    }
    $up = db()->prepare("UPDATE {$table} SET sort = ? WHERE id = ?");
    foreach ($ids as $i => $rowId) {
        $up->execute([$i + 1, $rowId]);
    }
}

function next_sort(string $table, string $where = '', array $params = []): int
{
    $st = db()->prepare("SELECT COALESCE(MAX(sort), 0) + 1 FROM {$table}" . ($where ? " WHERE {$where}" : ''));
    $st->execute($params);
    return (int) $st->fetchColumn();
}

function handle_admin_action(string $page, string $action): void
{
    $table = page_table($page);
    $id = (int) post('id', '0');

    if ($action === 'toggle' && $table) {
        $field = post('field');
        if (!in_array($field, TOGGLE_FIELDS[$table], true)) {
            throw new RuntimeException('Geçersiz alan.');
        }
        db()->prepare("UPDATE {$table} SET {$field} = 1 - {$field} WHERE id = ?")->execute([$id]);
        redirect('/admin/?p=' . $page . '#r' . $id);
    }
    if ($action === 'move' && $table) {
        move_row($table, $id, post('dir') === 'up' ? 'up' : 'down');
        redirect('/admin/?p=' . $page . '#r' . $id);
    }
    if ($action === 'delete' && $table) {
        if (in_array($table, ['products', 'gallery'], true)) {
            $st = db()->prepare("SELECT image FROM {$table} WHERE id = ?");
            $st->execute([$id]);
            delete_upload((string) $st->fetchColumn());
        }
        if ($table === 'categories') {
            $st = db()->prepare('SELECT image FROM products WHERE category_id = ?');
            $st->execute([$id]);
            foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $img) {
                delete_upload((string) $img);
            }
        }
        db()->prepare("DELETE FROM {$table} WHERE id = ?")->execute([$id]);
        flash('Silindi.');
        redirect('/admin/?p=' . ($page === 'urun' ? 'urunler' : $page));
    }

    match ($page) {
        'urun' => save_product(),
        'kategoriler' => save_category(),
        'galeri' => save_gallery($action),
        'yorumlar' => save_review(),
        'ayarlar' => save_settings(),
        'sifre' => save_password(),
        'panel' => save_quick(),
        default => null,
    };
}

function save_product(): void
{
    $id = (int) post('id', '0');
    $name = post('name');
    $cat = (int) post('category_id');
    if ($name === '' || $cat === 0) {
        throw new RuntimeException('Ürün adı ve kategori zorunlu.');
    }
    $priceRaw = str_replace(['₺', ' ', '.'], '', post('price'));
    $priceRaw = str_replace(',', '.', $priceRaw);
    $price = $priceRaw === '' ? null : (float) $priceRaw;
    if ($price !== null && $price < 0) {
        throw new RuntimeException('Fiyat negatif olamaz.');
    }

    $old = null;
    if ($id) {
        $st = db()->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([$id]);
        $old = $st->fetch() ?: null;
    }
    $image = $old['image'] ?? '';
    $new = handle_upload('image', 1400);
    if ($new !== null || post('remove_image') === '1') {
        delete_upload($image);
        $image = $new ?? '';
    }
    $data = [
        $cat, $name, post('description'), $price, mb_strtoupper(post('badge')), $image,
        post('is_featured') === '1' ? 1 : 0, post('is_available') === '1' ? 1 : 0, post('is_visible') === '1' ? 1 : 0,
    ];
    if ($old) {
        $data[] = $id;
        db()->prepare('UPDATE products SET category_id=?, name=?, description=?, price=?, badge=?, image=?, is_featured=?, is_available=?, is_visible=? WHERE id=?')->execute($data);
        flash('Ürün güncellendi.');
    } else {
        $data[] = next_sort('products', 'category_id = ?', [$cat]);
        db()->prepare('INSERT INTO products(category_id, name, description, price, badge, image, is_featured, is_available, is_visible, sort) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute($data);
        flash('Ürün eklendi.');
    }
    redirect('/admin/?p=urunler');
}

function save_category(): void
{
    $id = (int) post('id', '0');
    $name = post('name');
    if ($name === '') {
        throw new RuntimeException('Kategori adı zorunlu.');
    }
    if ($id) {
        db()->prepare('UPDATE categories SET name = ?, tagline = ? WHERE id = ?')->execute([mb_strtoupper($name), post('tagline'), $id]);
        flash('Kategori güncellendi.');
    } else {
        db()->prepare('INSERT INTO categories(name, tagline, sort) VALUES(?,?,?)')->execute([mb_strtoupper($name), post('tagline'), next_sort('categories')]);
        flash('Kategori eklendi.');
    }
    redirect('/admin/?p=kategoriler');
}

function save_gallery(string $action): void
{
    if ($action === 'caption') {
        db()->prepare('UPDATE gallery SET caption = ? WHERE id = ?')->execute([post('caption'), (int) post('id')]);
        flash('Açıklama kaydedildi.');
        redirect('/admin/?p=galeri');
    }
    $files = $_FILES['images'] ?? null;
    if (!$files || !is_array($files['name'])) {
        throw new RuntimeException('Görsel seçin.');
    }
    $count = 0;
    foreach (array_keys($files['name']) as $i) {
        $_FILES['_one'] = [
            'name' => $files['name'][$i], 'type' => $files['type'][$i], 'tmp_name' => $files['tmp_name'][$i],
            'error' => $files['error'][$i], 'size' => $files['size'][$i],
        ];
        $file = handle_upload('_one', 1400);
        if ($file) {
            db()->prepare('INSERT INTO gallery(image, sort) VALUES(?, ?)')->execute([$file, next_sort('gallery')]);
            $count++;
        }
    }
    flash($count . ' görsel eklendi.');
    redirect('/admin/?p=galeri');
}

function save_review(): void
{
    $id = (int) post('id', '0');
    $author = post('author');
    $body = post('body');
    if ($author === '' || $body === '') {
        throw new RuntimeException('İsim ve yorum zorunlu.');
    }
    $rating = max(1, min(5, (int) post('rating', '5')));
    $source = post('source') ?: 'Google';
    if ($id) {
        db()->prepare('UPDATE reviews SET author=?, body=?, rating=?, source=? WHERE id=?')->execute([$author, $body, $rating, $source, $id]);
        flash('Yorum güncellendi.');
    } else {
        db()->prepare('INSERT INTO reviews(author, body, rating, source, sort) VALUES(?,?,?,?,?)')->execute([$author, $body, $rating, $source, next_sort('reviews')]);
        flash('Yorum eklendi.');
    }
    redirect('/admin/?p=yorumlar');
}

function save_settings(): void
{
    $group = post('group');
    if (!isset(SETTING_GROUPS[$group])) {
        throw new RuntimeException('Geçersiz bölüm.');
    }
    $current = settings();
    foreach (setting_definitions() as $key => [$label, $default, $type, $g]) {
        if ($g !== $group) {
            continue;
        }
        $value = match ($type) {
            'bool' => post($key) === '1' ? '1' : '0',
            'color' => valid_color(post($key), $default),
            'time' => preg_match('/^\d{2}:\d{2}$/', post($key)) ? post($key) : $default,
            'url' => (post($key) === '' || preg_match('#^https?://#i', post($key))) ? post($key) : $current[$key],
            'image' => null,
            default => post($key),
        };
        if ($type === 'image') {
            $new = handle_upload($key, $key === 'og_image' ? 1200 : 1600);
            if ($new !== null || post('remove_' . $key) === '1') {
                delete_upload($current[$key]);
                save_setting($key, $new ?? '');
            }
            continue;
        }
        save_setting($key, $value);
    }
    flash('Ayarlar kaydedildi.');
    redirect('/admin/?p=ayarlar&g=' . $group);
}

function save_quick(): void
{
    save_setting('temporarily_closed', post('temporarily_closed') === '1' ? '1' : '0');
    save_setting('announcement', post('announcement'));
    flash('Kaydedildi.');
    redirect('/admin/');
}

function save_password(): void
{
    $currentPw = (string) ($_POST['current'] ?? '');
    $new = (string) ($_POST['new'] ?? '');
    $again = (string) ($_POST['again'] ?? '');
    if (!password_verify($currentPw, CONFIG['admin_password_hash'])) {
        throw new RuntimeException('Mevcut şifre hatalı.');
    }
    if (mb_strlen($new) < 10) {
        throw new RuntimeException('Yeni şifre en az 10 karakter olmalı.');
    }
    if ($new !== $again) {
        throw new RuntimeException('Yeni şifreler eşleşmiyor.');
    }
    $user = post('user') ?: CONFIG['admin_user'];
    $hash = password_hash($new, PASSWORD_DEFAULT);
    write_config(['admin_user' => $user, 'admin_password_hash' => $hash]);
    $_SESSION['admin_hash'] = substr($hash, -16);
    flash('Şifre değiştirildi.');
    redirect('/admin/?p=sifre');
}

/* ================= Sayfalar ================= */

function page_dashboard(): void
{
    $s = settings();
    $stats = [
        'Ürün' => (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
        'Tükendi' => (int) db()->query('SELECT COUNT(*) FROM products WHERE is_available = 0')->fetchColumn(),
        'Fiyatı girilmemiş' => (int) db()->query('SELECT COUNT(*) FROM products WHERE price IS NULL')->fetchColumn(),
        'Galeri görseli' => (int) db()->query('SELECT COUNT(*) FROM gallery')->fetchColumn(),
    ];
    $status = open_status();
    page_title('Merhaba', '<a class="btn primary" href="/admin/?p=urun">+ Yeni ürün</a>');
    ?>
<div class="stats">
<div class="stat"><span>Site durumu</span><strong class="<?= $status['open'] ? 'green' : 'red' ?>"><?= e($status['label']) ?></strong></div>
<?php foreach ($stats as $label => $n): ?><div class="stat"><span><?= e($label) ?></span><strong><?= $n ?></strong></div><?php endforeach; ?>
</div>
<section class="box">
<h2>Hızlı ayarlar</h2>
<form method="post" class="form">
<?= csrf_field() ?>
<label class="check"><input type="checkbox" name="temporarily_closed" value="1"<?= $s['temporarily_closed'] === '1' ? ' checked' : '' ?>> Bugün / geçici olarak kapalıyız (sitede "Geçici olarak kapalı" yazar)</label>
<label>Duyuru şeridi (sitenin en üstünde; boş bırakırsanız gizlenir)<input name="announcement" value="<?= e($s['announcement']) ?>" placeholder="Ör: Bu akşam 22:00'de kapanıyoruz"></label>
<button class="btn primary" type="submit">Kaydet</button>
</form>
</section>
<?php
    $missing = db()->query('SELECT p.id, p.name FROM products p WHERE p.price IS NULL OR p.image = "" ORDER BY p.sort LIMIT 8')->fetchAll();
    if ($missing): ?>
<section class="box">
<h2>Eksik bilgisi olan ürünler</h2>
<p class="muted">Fiyatı veya fotoğrafı olmayan ürünler. Tıklayıp tamamlayın.</p>
<ul class="chips"><?php foreach ($missing as $m): ?><li><a href="/admin/?p=urun&id=<?= (int) $m['id'] ?>"><?= e($m['name']) ?></a></li><?php endforeach; ?></ul>
</section>
<?php endif;
}

function page_products(): void
{
    $cats = db()->query('SELECT * FROM categories ORDER BY sort, id')->fetchAll();
    page_title('Menü ürünleri', '<a class="btn primary" href="/admin/?p=urun">+ Yeni ürün</a>');
    if (!$cats) {
        echo '<p class="empty">Önce bir <a href="/admin/?p=kategoriler">kategori</a> ekleyin.</p>';
        return;
    }
    foreach ($cats as $c):
        $st = db()->prepare('SELECT * FROM products WHERE category_id = ? ORDER BY sort, id');
        $st->execute([$c['id']]);
        $items = $st->fetchAll(); ?>
<section class="box">
<h2><?= e($c['name']) ?> <span class="count"><?= count($items) ?></span></h2>
<?php if (!$items): ?><p class="muted">Bu kategoride ürün yok.</p><?php endif; ?>
<ul class="rows">
<?php foreach ($items as $p): ?>
<li class="row<?= $p['is_visible'] ? '' : ' faded' ?>" id="r<?= (int) $p['id'] ?>">
<?= move_buttons('urunler', (int) $p['id']) ?>
<a class="thumb" href="/admin/?p=urun&id=<?= (int) $p['id'] ?>"><?php if ($p['image']): ?><img src="<?= e(upload_url($p['image'])) ?>" alt="" loading="lazy"><?php endif; ?></a>
<a class="row-main" href="/admin/?p=urun&id=<?= (int) $p['id'] ?>"><strong><?= e($p['name']) ?></strong><span><?= $p['price'] === null ? '<em class="warn">Fiyat yok</em>' : e(format_price((float) $p['price'])) ?><?= $p['badge'] ? ' · ' . e($p['badge']) : '' ?></span></a>
<div class="row-actions">
<?= toggle_button('urunler', (int) $p['id'], 'is_available', (bool) $p['is_available'], 'Stokta', 'Tükendi') ?>
<?= toggle_button('urunler', (int) $p['id'], 'is_featured', (bool) $p['is_featured'], '★ Favori', '☆ Favori') ?>
<?= toggle_button('urunler', (int) $p['id'], 'is_visible', (bool) $p['is_visible'], 'Görünür', 'Gizli') ?>
<a class="btn small" href="/admin/?p=urun&id=<?= (int) $p['id'] ?>">Düzenle</a>
</div>
</li>
<?php endforeach; ?>
</ul>
</section>
<?php endforeach;
}

function page_product_form(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $p = ['id' => 0, 'category_id' => (int) ($_GET['k'] ?? 0), 'name' => '', 'description' => '', 'price' => null, 'badge' => '', 'image' => '', 'is_featured' => 0, 'is_available' => 1, 'is_visible' => 1];
    if ($id) {
        $st = db()->prepare('SELECT * FROM products WHERE id = ?');
        $st->execute([$id]);
        $p = $st->fetch() ?: $p;
    }
    $cats = db()->query('SELECT id, name FROM categories ORDER BY sort, id')->fetchAll();
    page_title($p['id'] ? 'Ürünü düzenle' : 'Yeni ürün', '<a class="btn" href="/admin/?p=urunler">← Ürünler</a>'); ?>
<section class="box">
<form method="post" action="/admin/?p=urun<?= $p['id'] ? '&id=' . (int) $p['id'] : '' ?>" enctype="multipart/form-data" class="form grid2">
<?= csrf_field() ?>
<input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
<label>Ürün adı<input name="name" value="<?= e($p['name']) ?>" required maxlength="80"></label>
<label>Kategori<select name="category_id" required>
<?php foreach ($cats as $c): ?><option value="<?= (int) $c['id'] ?>"<?= (int) $c['id'] === (int) $p['category_id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?>
</select></label>
<label class="span2">Açıklama (içindekiler)<textarea name="description" rows="3" maxlength="400"><?= e($p['description']) ?></textarea></label>
<label>Fiyat (₺) <small>boş bırakılırsa fiyat gösterilmez</small><input name="price" inputmode="decimal" value="<?= $p['price'] === null ? '' : e(rtrim(rtrim(number_format((float) $p['price'], 2, ',', ''), '0'), ',')) ?>" placeholder="Ör: 450"></label>
<label>Rozet <small>ör: YENİ, ACI, İMZA</small><input name="badge" value="<?= e($p['badge']) ?>" maxlength="20"></label>
<div class="span2 image-field">
<?php if ($p['image']): ?><img src="<?= e(upload_url($p['image'])) ?>" alt="" class="preview"><?php endif; ?>
<label>Fotoğraf <small>JPG/PNG/WebP — otomatik küçültülür ve WebP'ye çevrilir</small><input type="file" name="image" accept="image/*"></label>
<?php if ($p['image']): ?><label class="check"><input type="checkbox" name="remove_image" value="1"> Fotoğrafı kaldır</label><?php endif; ?>
</div>
<div class="span2 checks">
<label class="check"><input type="checkbox" name="is_available" value="1"<?= $p['is_available'] ? ' checked' : '' ?>> Stokta (kapalıysa "Bugün tükendi" yazar)</label>
<label class="check"><input type="checkbox" name="is_featured" value="1"<?= $p['is_featured'] ? ' checked' : '' ?>> Ana sayfada favorilerde göster</label>
<label class="check"><input type="checkbox" name="is_visible" value="1"<?= $p['is_visible'] ? ' checked' : '' ?>> Sitede görünür</label>
</div>
<div class="span2 form-actions"><button class="btn primary" type="submit">Kaydet</button></div>
</form>
<?php if ($p['id']): ?><div class="danger-zone"><?= delete_button('urun', (int) $p['id'], $p['name']) ?></div><?php endif; ?>
</section>
<?php
}

function page_categories(): void
{
    $cats = db()->query('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS n FROM categories c ORDER BY sort, id')->fetchAll();
    $editId = (int) ($_GET['id'] ?? 0);
    page_title('Kategoriler'); ?>
<section class="box">
<ul class="rows">
<?php foreach ($cats as $c): ?>
<li class="row<?= $c['is_visible'] ? '' : ' faded' ?>" id="r<?= (int) $c['id'] ?>">
<?= move_buttons('kategoriler', (int) $c['id']) ?>
<?php if ($editId === (int) $c['id']): ?>
<form method="post" class="row-edit">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
<input name="name" value="<?= e($c['name']) ?>" required aria-label="Kategori adı">
<input name="tagline" value="<?= e($c['tagline']) ?>" aria-label="Kısa açıklama" placeholder="Kısa açıklama">
<button class="btn small primary" type="submit">Kaydet</button>
</form>
<?php else: ?>
<div class="row-main"><strong><?= e($c['name']) ?></strong><span><?= e($c['tagline']) ?> · <?= (int) $c['n'] ?> ürün</span></div>
<div class="row-actions">
<?= toggle_button('kategoriler', (int) $c['id'], 'show_on_home', (bool) $c['show_on_home'], 'Ana sayfada', 'Ana sayfada değil') ?>
<?= toggle_button('kategoriler', (int) $c['id'], 'is_visible', (bool) $c['is_visible'], 'Görünür', 'Gizli') ?>
<a class="btn small" href="/admin/?p=kategoriler&id=<?= (int) $c['id'] ?>#r<?= (int) $c['id'] ?>">Düzenle</a>
<?= delete_button('kategoriler', (int) $c['id'], $c['name'] . ' kategorisi ve içindeki ' . (int) $c['n'] . ' ürün') ?>
</div>
<?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
</section>
<section class="box">
<h2>Yeni kategori</h2>
<form method="post" class="form grid2">
<?= csrf_field() ?>
<label>Ad<input name="name" required placeholder="Ör: TATLILAR"></label>
<label>Kısa açıklama<input name="tagline" placeholder="Ör: Tatlı bir kapanış"></label>
<div class="span2"><button class="btn primary" type="submit">Ekle</button></div>
</form>
</section>
<?php
}

function page_gallery(): void
{
    $items = db()->query('SELECT * FROM gallery ORDER BY sort, id')->fetchAll();
    page_title('Galeri'); ?>
<section class="box">
<h2>Görsel yükle</h2>
<p class="muted">Instagram'daki fotoğraflarınızı buraya yükleyin; ana sayfada polaroid olarak görünür. Birden fazla seçebilirsiniz.</p>
<form method="post" enctype="multipart/form-data" class="form">
<?= csrf_field() ?><input type="hidden" name="action" value="upload">
<input type="file" name="images[]" accept="image/*" multiple required>
<button class="btn primary" type="submit">Yükle</button>
</form>
</section>
<section class="box">
<?php if (!$items): ?><p class="muted">Henüz görsel yok.</p><?php endif; ?>
<ul class="gallery-grid">
<?php foreach ($items as $g): ?>
<li class="g-item<?= $g['is_visible'] ? '' : ' faded' ?>" id="r<?= (int) $g['id'] ?>">
<img src="<?= e(upload_url($g['image'])) ?>" alt="" loading="lazy">
<form method="post" class="g-caption"><?= csrf_field() ?><input type="hidden" name="action" value="caption"><input type="hidden" name="id" value="<?= (int) $g['id'] ?>">
<input name="caption" value="<?= e($g['caption']) ?>" placeholder="Açıklama (isteğe bağlı)" aria-label="Açıklama"><button class="btn small" type="submit">✓</button></form>
<div class="row-actions"><?= move_buttons('galeri', (int) $g['id']) ?><?= toggle_button('galeri', (int) $g['id'], 'is_visible', (bool) $g['is_visible'], 'Görünür', 'Gizli') ?><?= delete_button('galeri', (int) $g['id'], 'Bu görsel') ?></div>
</li>
<?php endforeach; ?>
</ul>
</section>
<?php
}

function page_reviews(): void
{
    $items = db()->query('SELECT * FROM reviews ORDER BY sort, id')->fetchAll();
    $editId = (int) ($_GET['id'] ?? 0);
    $edit = null;
    foreach ($items as $r) {
        if ((int) $r['id'] === $editId) {
            $edit = $r;
        }
    }
    page_title('Yorumlar'); ?>
<section class="box">
<p class="muted">Google'daki misafir yorumlarından seçtiklerinizi ekleyin. Google puanı ve yorum sayısı Site ayarları › Ana sayfa metinleri'nde.</p>
<ul class="rows">
<?php foreach ($items as $r): ?>
<li class="row<?= $r['is_visible'] ? '' : ' faded' ?>" id="r<?= (int) $r['id'] ?>">
<?= move_buttons('yorumlar', (int) $r['id']) ?>
<div class="row-main"><strong><?= e($r['author']) ?> · <?= str_repeat('★', (int) $r['rating']) ?></strong><span><?= e(mb_strimwidth($r['body'], 0, 120, '…')) ?></span></div>
<div class="row-actions">
<?= toggle_button('yorumlar', (int) $r['id'], 'is_visible', (bool) $r['is_visible'], 'Görünür', 'Gizli') ?>
<a class="btn small" href="/admin/?p=yorumlar&id=<?= (int) $r['id'] ?>#form">Düzenle</a>
<?= delete_button('yorumlar', (int) $r['id'], $r['author'] . ' yorumu') ?>
</div>
</li>
<?php endforeach; ?>
</ul>
</section>
<section class="box" id="form">
<h2><?= $edit ? 'Yorumu düzenle' : 'Yeni yorum' ?></h2>
<form method="post" class="form grid2">
<?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<label>İsim<input name="author" value="<?= e($edit['author'] ?? '') ?>" required placeholder="Ör: Ayşe K."></label>
<label>Kaynak<input name="source" value="<?= e($edit['source'] ?? 'Google') ?>"></label>
<label class="span2">Yorum<textarea name="body" rows="3" required><?= e($edit['body'] ?? '') ?></textarea></label>
<label>Puan<select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option<?= (int) ($edit['rating'] ?? 5) === $i ? ' selected' : '' ?>><?= $i ?></option><?php endfor; ?></select></label>
<div class="span2"><button class="btn primary" type="submit">Kaydet</button><?php if ($edit): ?> <a class="btn" href="/admin/?p=yorumlar">Vazgeç</a><?php endif; ?></div>
</form>
</section>
<?php
}

function page_settings(): void
{
    $group = (string) ($_GET['g'] ?? 'genel');
    if (!isset(SETTING_GROUPS[$group])) {
        $group = 'genel';
    }
    $s = settings();
    page_title('Site ayarları'); ?>
<nav class="tabs" aria-label="Ayar bölümleri">
<?php foreach (SETTING_GROUPS as $key => $label): ?><a href="/admin/?p=ayarlar&g=<?= $key ?>"<?= $key === $group ? ' aria-current="page"' : '' ?>><?= e($label) ?></a><?php endforeach; ?>
</nav>
<section class="box">
<form method="post" enctype="multipart/form-data" class="form grid2">
<?= csrf_field() ?><input type="hidden" name="group" value="<?= e($group) ?>">
<?php foreach (setting_definitions() as $key => [$label, $default, $type, $g]):
        if ($g !== $group) {
            continue;
        }
        $v = $s[$key];
        switch ($type) {
            case 'textarea': ?>
<label class="span2"><?= e($label) ?><textarea name="<?= $key ?>" rows="3"><?= e($v) ?></textarea></label>
<?php break;
            case 'bool': ?>
<label class="check span2"><input type="checkbox" name="<?= $key ?>" value="1"<?= $v === '1' ? ' checked' : '' ?>> <?= e($label) ?></label>
<?php break;
            case 'color': ?>
<label><?= e($label) ?><span class="color-row"><input type="color" name="<?= $key ?>" value="<?= e(valid_color($v, $default)) ?>"><code><?= e($v) ?></code> <small>varsayılan <?= e($default) ?></small></span></label>
<?php break;
            case 'image': ?>
<div class="image-field"><?php if ($v): ?><img src="<?= e(upload_url($v)) ?>" alt="" class="preview small"><?php endif; ?>
<label><?= e($label) ?><input type="file" name="<?= $key ?>" accept="image/*"></label>
<?php if ($v): ?><label class="check"><input type="checkbox" name="remove_<?= $key ?>" value="1"> Kaldır</label><?php endif; ?></div>
<?php break;
            default: ?>
<label><?= e($label) ?><input type="<?= $type === 'time' ? 'time' : ($type === 'url' ? 'url' : 'text') ?>" name="<?= $key ?>" value="<?= e($v) ?>"></label>
<?php
        }
    endforeach; ?>
<div class="span2 form-actions"><button class="btn primary" type="submit">Kaydet</button></div>
</form>
</section>
<?php
}

function page_password(): void
{
    page_title('Şifre değiştir'); ?>
<section class="box narrow">
<form method="post" class="form">
<?= csrf_field() ?>
<label>Kullanıcı adı<input name="user" value="<?= e(CONFIG['admin_user']) ?>" autocomplete="username" required></label>
<label>Mevcut şifre<input name="current" type="password" required autocomplete="current-password"></label>
<label>Yeni şifre <small>en az 10 karakter</small><input name="new" type="password" required minlength="10" autocomplete="new-password"></label>
<label>Yeni şifre (tekrar)<input name="again" type="password" required minlength="10" autocomplete="new-password"></label>
<button class="btn primary" type="submit">Şifreyi değiştir</button>
</form>
</section>
<?php
}
