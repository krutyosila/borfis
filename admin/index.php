<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
require APP_DIR . '/admin.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");

start_session();
$page = preg_replace('/[^a-z]/', '', (string) ($_GET['p'] ?? 'panel'));
$method = $_SERVER['REQUEST_METHOD'];

/* ---------- Giriş / çıkış ---------- */
if (CONFIG['admin_password_hash'] === '') {
    admin_setup_page();
    exit;
}

if ($page === 'cikis') {
    if ($method === 'POST') {
        verify_csrf();
        $_SESSION = [];
        session_regenerate_id(true);
    }
    redirect('/admin/');
}

if (!is_admin()) {
    $error = null;
    if ($method === 'POST') {
        verify_csrf();
        $error = attempt_login(post('user'), (string) ($_POST['password'] ?? ''));
        if ($error === null) {
            redirect('/admin/');
        }
    }
    admin_login_page($error);
    exit;
}

/* ---------- İşlemler (POST) ---------- */
if ($method === 'POST') {
    verify_csrf();
    $action = post('action');
    try {
        handle_admin_action($page, $action);
    } catch (RuntimeException $ex) {
        flash($ex->getMessage(), 'err');
        redirect('/admin/?p=' . $page . (isset($_GET['id']) ? '&id=' . (int) $_GET['id'] : ''));
    }
}

/* ---------- Sayfalar ---------- */
admin_open($page);
match ($page) {
    'urunler' => page_products(),
    'urun' => page_product_form(),
    'kategoriler' => page_categories(),
    'galeri' => page_gallery(),
    'yorumlar' => page_reviews(),
    'ayarlar' => page_settings(),
    'sifre' => page_password(),
    default => page_dashboard(),
};
admin_close();
