<?php
declare(strict_types=1);

// Komut satırı araçları:
//   php app/cli.php sifre [kullanici]   → yönetici şifresi belirler (sorar)
//   php app/cli.php yedek               → veritabanının yedeğini alır
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';
require APP_DIR . '/admin.php';

$cmd = $argv[1] ?? '';

if ($cmd === 'sifre') {
    $user = $argv[2] ?? (CONFIG['admin_user'] ?: 'admin');
    $pw = getenv('BORFIS_PASSWORD') ?: '';
    if ($pw === '') {
        fwrite(STDOUT, "Yeni şifre ({$user}): ");
        if (DIRECTORY_SEPARATOR === '/') {
            system('stty -echo');
        }
        $pw = trim((string) fgets(STDIN));
        if (DIRECTORY_SEPARATOR === '/') {
            system('stty echo');
        }
        fwrite(STDOUT, PHP_EOL);
    }
    if (mb_strlen($pw) < 10) {
        fwrite(STDERR, "Şifre en az 10 karakter olmalı.\n");
        exit(1);
    }
    write_config(['admin_user' => $user, 'admin_password_hash' => password_hash($pw, PASSWORD_DEFAULT)]);
    db(); // veritabanını oluştur
    fwrite(STDOUT, "Tamam. Kullanıcı: {$user}\nVeri klasörü: " . DATA_DIR . "\n");
    exit(0);
}

if ($cmd === 'yedek') {
    $target = DATA_DIR . '/yedek-' . date('Ymd-His') . '.sqlite';
    db()->exec('VACUUM INTO ' . db()->quote($target));
    fwrite(STDOUT, "Yedek: {$target}\n");
    exit(0);
}

if ($cmd === 'menu') {
    if (($argv[2] ?? '') !== '--evet') {
        fwrite(STDOUT, "DİKKAT: Tüm kategoriler ve ürünler silinip menü panosundaki menü yüklenir.\nOnaylamak için: php app/cli.php menu --evet\n");
        exit(1);
    }
    upgrade_menu(db(), true);
    fwrite(STDOUT, "Menü yüklendi.\n");
    exit(0);
}

fwrite(STDOUT, "Kullanım:\n  php app/cli.php sifre [kullanici]\n  php app/cli.php yedek\n  php app/cli.php menu --evet   (menüyü sıfırdan yükler)\n");
