<?php
declare(strict_types=1);
defined('BORFIS') || exit;

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function settings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache !== null && !$refresh) {
        return $cache;
    }
    $values = [];
    foreach (setting_definitions() as $key => $def) {
        $values[$key] = $def[1];
    }
    foreach (db()->query('SELECT key, value FROM settings') as $row) {
        $values[$row['key']] = $row['value'];
    }
    return $cache = $values;
}

function setting(string $key, string $default = ''): string
{
    return settings()[$key] ?? $default;
}

function flag(string $key): bool
{
    return setting($key) === '1';
}

function save_setting(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings(key, value) VALUES(?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}

function format_price(?float $price): string
{
    if ($price === null) {
        return '';
    }
    $decimals = floor($price) == $price ? 0 : 2;
    return number_format($price, $decimals, ',', '.') . ' TL';
}

function upload_url(string $file): string
{
    return $file === '' ? '' : UPLOAD_URL . '/' . rawurlencode($file);
}

function asset(string $path): string
{
    $full = PUBLIC_DIR . '/assets/' . $path;
    $v = is_file($full) ? (string) filemtime($full) : BORFIS_VERSION;
    return '/assets/' . $path . '?v=' . $v;
}

function valid_color(string $value, string $fallback): string
{
    return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : $fallback;
}

/** Şu an açık mı? Kapanış gece yarısını geçebilir (ör. 12:00 – 02:00). */
function open_status(?DateTimeImmutable $now = null): array
{
    $now ??= new DateTimeImmutable('now');
    if (flag('temporarily_closed')) {
        return ['open' => false, 'label' => 'GEÇİCİ OLARAK KAPALI'];
    }
    $open = setting('open_time', '12:00');
    $close = setting('close_time', '00:00');
    $closedDays = array_filter(array_map('trim', explode(',', setting('closed_days'))));
    $toMin = static fn(string $t): int => ((int) substr($t, 0, 2)) * 60 + (int) substr($t, 3, 2);
    $o = $toMin($open);
    $c = $toMin($close);
    $m = (int) $now->format('G') * 60 + (int) $now->format('i');
    $day = $now->format('N');
    $yesterday = $now->modify('-1 day')->format('N');

    if ($c <= $o) { // gece yarısını geçiyor
        $isOpen = ($m >= $o && !in_array($day, $closedDays, true))
            || ($m < $c && !in_array($yesterday, $closedDays, true));
    } else {
        $isOpen = $m >= $o && $m < $c && !in_array($day, $closedDays, true);
    }
    return $isOpen
        ? ['open' => true, 'label' => 'ŞU AN AÇIĞIZ']
        : ['open' => false, 'label' => 'ŞU AN KAPALI · ' . $open . "'DE AÇIYORUZ"];
}

function closed_days_label(): string
{
    $names = [1 => 'PZT', 2 => 'SAL', 3 => 'ÇAR', 4 => 'PER', 5 => 'CUM', 6 => 'CMT', 7 => 'PAZ'];
    $closed = array_filter(array_map('intval', explode(',', setting('closed_days'))), fn($d) => isset($names[$d]));
    if (!$closed) {
        return 'HER GÜN';
    }
    return 'KAPALI: ' . implode(', ', array_map(fn($d) => $names[$d], $closed));
}

function instagram_url(): string
{
    $u = ltrim(trim(setting('instagram')), '@');
    return $u === '' ? '' : 'https://www.instagram.com/' . rawurlencode($u) . '/';
}

function phone_href(): string
{
    $digits = preg_replace('/\D+/', '', setting('phone'));
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0')) {
        $digits = '90' . substr($digits, 1);
    }
    return 'tel:+' . $digits;
}

function maps_embed_src(): string
{
    $q = trim(setting('maps_embed_query'));
    return $q === '' ? '' : 'https://maps.google.com/maps?q=' . rawurlencode($q) . '&z=16&output=embed';
}

/* ---------- Oturum, CSRF, yönlendirme ---------- */

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('borfis_admin');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/admin',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    start_session();
    $token = $_POST['_csrf'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        exit('Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.');
    }
}

function flash(?string $message = null, string $type = 'ok'): ?array
{
    start_session();
    if ($message !== null) {
        $_SESSION['flash'] = ['message' => $message, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function is_admin(): bool
{
    start_session();
    return !empty($_SESSION['admin']) && ($_SESSION['admin_hash'] ?? '') === substr(CONFIG['admin_password_hash'], -16);
}

function post(string $key, string $default = ''): string
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $default;
}

/* ---------- Görsel yükleme (WebP'ye dönüştürür) ---------- */

/**
 * Yüklenen görseli doğrular, en fazla $maxWidth genişliğe küçültür ve WebP olarak kaydeder.
 * Dosya seçilmediyse null döner; hatada RuntimeException fırlatır.
 */
function handle_upload(string $field, int $maxWidth = 1600): ?string
{
    $f = $_FILES[$field] ?? null;
    if (!$f || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($f['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Görsel yüklenemedi (hata kodu ' . (int) $f['error'] . '). Dosya çok büyük olabilir.');
    }
    if ($f['size'] > 15 * 1024 * 1024) {
        throw new RuntimeException('Görsel 15 MB\'tan küçük olmalı.');
    }
    $info = @getimagesize($f['tmp_name']);
    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF];
    if (!$info || !in_array($info[2], $allowed, true)) {
        throw new RuntimeException('Sadece JPG, PNG, WebP veya GIF yükleyebilirsiniz.');
    }
    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }
    $name = date('Ymd') . '-' . bin2hex(random_bytes(6));

    if (!function_exists('imagewebp')) {
        $ext = image_type_to_extension($info[2], false);
        $file = $name . '.' . ($ext === 'jpeg' ? 'jpg' : $ext);
        if (!move_uploaded_file($f['tmp_name'], UPLOAD_DIR . '/' . $file)) {
            throw new RuntimeException('Görsel kaydedilemedi.');
        }
        return $file;
    }

    $src = match ($info[2]) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($f['tmp_name']),
        IMAGETYPE_PNG => imagecreatefrompng($f['tmp_name']),
        IMAGETYPE_WEBP => imagecreatefromwebp($f['tmp_name']),
        IMAGETYPE_GIF => imagecreatefromgif($f['tmp_name']),
    };
    if (!$src) {
        throw new RuntimeException('Görsel okunamadı.');
    }
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($f['tmp_name']);
        $rot = match ((int) ($exif['Orientation'] ?? 1)) { 3 => 180, 6 => -90, 8 => 90, default => 0 };
        if ($rot !== 0) {
            $src = imagerotate($src, $rot, 0);
        }
    }
    $w = imagesx($src);
    $h = imagesy($src);
    if ($w > $maxWidth) {
        $nh = (int) round($h * $maxWidth / $w);
        $dst = imagecreatetruecolor($maxWidth, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $maxWidth, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    } else {
        imagepalettetotruecolor($src);
        imagealphablending($src, false);
        imagesavealpha($src, true);
    }
    $file = $name . '.webp';
    imagewebp($src, UPLOAD_DIR . '/' . $file, 82);
    imagedestroy($src);
    return $file;
}

function delete_upload(string $file): void
{
    if ($file !== '' && !str_contains($file, '/') && !str_contains($file, '..')) {
        $path = UPLOAD_DIR . '/' . $file;
        if (is_file($path)) {
            unlink($path);
        }
    }
}

/* ---------- Veri sorguları ---------- */

function visible_categories(): array
{
    return db()->query('SELECT * FROM categories WHERE is_visible = 1 ORDER BY sort, id')->fetchAll();
}

function visible_products(?int $categoryId = null): array
{
    if ($categoryId === null) {
        return db()->query('SELECT * FROM products WHERE is_visible = 1 ORDER BY sort, id')->fetchAll();
    }
    $st = db()->prepare('SELECT * FROM products WHERE is_visible = 1 AND category_id = ? ORDER BY sort, id');
    $st->execute([$categoryId]);
    return $st->fetchAll();
}

function featured_products(int $limit = 6): array
{
    $st = db()->prepare('SELECT p.* FROM products p JOIN categories c ON c.id = p.category_id
        WHERE p.is_visible = 1 AND p.is_featured = 1 AND c.is_visible = 1 ORDER BY p.sort, p.id LIMIT ?');
    $st->execute([$limit]);
    return $st->fetchAll();
}

function visible_gallery(): array
{
    return db()->query('SELECT * FROM gallery WHERE is_visible = 1 ORDER BY sort, id')->fetchAll();
}

function visible_reviews(): array
{
    return db()->query('SELECT * FROM reviews WHERE is_visible = 1 ORDER BY sort, id')->fetchAll();
}
