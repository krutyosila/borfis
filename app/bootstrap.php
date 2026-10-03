<?php
declare(strict_types=1);

const BORFIS = true;
const BORFIS_VERSION = '1.0.0';

date_default_timezone_set('Europe/Istanbul');
mb_internal_encoding('UTF-8');

define('PUBLIC_DIR', dirname(__DIR__));
define('APP_DIR', __DIR__);

// Veri klasörü: Hestia'da /home/<kullanıcı>/web/<domain>/private (web kökünün dışında).
// Yoksa public_html/app/data kullanılır (erişime kapalı).
$privateDir = getenv('BORFIS_DATA_DIR') ?: dirname(PUBLIC_DIR) . '/private';
if (!is_dir($privateDir) || !is_writable($privateDir)) {
    $privateDir = APP_DIR . '/data';
    if (!is_dir($privateDir)) {
        mkdir($privateDir, 0750, true);
    }
}
define('DATA_DIR', $privateDir);
define('UPLOAD_DIR', PUBLIC_DIR . '/uploads');
define('UPLOAD_URL', '/uploads');

$configFile = DATA_DIR . '/config.php';
$config = is_file($configFile) ? require $configFile : [];
define('CONFIG', array_merge([
    'admin_user' => 'admin',
    'admin_password_hash' => '',
    'debug' => false,
    'base_url' => '',
], is_array($config) ? $config : []));

if (CONFIG['debug']) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
}

require APP_DIR . '/db.php';
require APP_DIR . '/helpers.php';
