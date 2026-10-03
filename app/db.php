<?php
declare(strict_types=1);
defined('BORFIS') || exit;

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $file = DATA_DIR . '/borfis.sqlite';
    $fresh = !is_file($file);
    $pdo = new PDO('sqlite:' . $file, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL; PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    migrate($pdo);
    if ($fresh) {
        seed($pdo);
    }
    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL DEFAULT ''
    );
    CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        tagline TEXT NOT NULL DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0,
        is_visible INTEGER NOT NULL DEFAULT 1,
        show_on_home INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category_id INTEGER NOT NULL REFERENCES categories(id) ON DELETE CASCADE,
        name TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        price REAL,
        badge TEXT NOT NULL DEFAULT '',
        image TEXT NOT NULL DEFAULT '',
        is_featured INTEGER NOT NULL DEFAULT 0,
        is_available INTEGER NOT NULL DEFAULT 1,
        is_visible INTEGER NOT NULL DEFAULT 1,
        sort INTEGER NOT NULL DEFAULT 0
    );
    CREATE TABLE IF NOT EXISTS gallery (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        image TEXT NOT NULL,
        caption TEXT NOT NULL DEFAULT '',
        sort INTEGER NOT NULL DEFAULT 0,
        is_visible INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS reviews (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        author TEXT NOT NULL,
        body TEXT NOT NULL,
        rating INTEGER NOT NULL DEFAULT 5,
        source TEXT NOT NULL DEFAULT 'Google',
        sort INTEGER NOT NULL DEFAULT 0,
        is_visible INTEGER NOT NULL DEFAULT 1
    );
    CREATE TABLE IF NOT EXISTS login_attempts (
        ip TEXT NOT NULL,
        at INTEGER NOT NULL
    );
    SQL);
}

/** Varsayılan ayarlar: anahtar => [etiket, varsayılan, tür, grup] */
function setting_definitions(): array
{
    return [
        // Genel
        'site_name' => ['İşletme adı', "Börfi's Burger", 'text', 'genel'],
        'site_short' => ['Logo yazısı (el yazısı)', "Börfi's", 'text', 'genel'],
        'tagline' => ['Slogan rozeti', 'BURGER & MORE', 'text', 'genel'],
        'logo' => ['Logo görseli (boşsa yazı logo)', '', 'image', 'genel'],
        'favicon' => ['Favicon (kare görsel)', '', 'image', 'genel'],
        'announcement' => ['Duyuru şeridi (boşsa gizli)', '', 'text', 'genel'],
        'ticker' => ['Kayan yazı (★ ile ayırın)', "AÇIĞIZ ★ HER GÜN 12:00 – 00:00 ★ AKYAKA ★ BURGER & MORE ★ PEÇETEYİ UNUTMA ★ WRAP DA VAR", 'text', 'genel'],

        // İletişim
        'phone' => ['Telefon', '0546 730 64 77', 'text', 'iletisim'],
        'whatsapp' => ['WhatsApp (90 ile, boşluksuz; boşsa gizli)', '', 'text', 'iletisim'],
        'address_line1' => ['Adres satırı 1', 'Akyaka Mah. Karanfil Sok. No:8/A', 'text', 'iletisim'],
        'address_line2' => ['Adres satırı 2', '48640 Ula / Muğla', 'text', 'iletisim'],
        'maps_url' => ['Google Haritalar bağlantısı', 'https://www.google.com/maps/search/?api=1&query=B%C3%B6rfi%27s+Burger+Akyaka', 'url', 'iletisim'],
        'maps_embed_query' => ['Harita arama metni (harita için)', "Börfi's Burger, Karanfil Sk. No:8/A, Akyaka, Ula, Muğla", 'text', 'iletisim'],
        'instagram' => ['Instagram kullanıcı adı', 'borfisburger', 'text', 'iletisim'],
        'outdoor_seating' => ['Açık hava oturma alanı var', '1', 'bool', 'iletisim'],

        // Saatler
        'open_time' => ['Açılış saati', '12:00', 'time', 'saatler'],
        'close_time' => ['Kapanış saati', '00:00', 'time', 'saatler'],
        'closed_days' => ['Kapalı günler (1=Pzt … 7=Paz, virgülle)', '', 'text', 'saatler'],
        'hours_text' => ['Saat yazısı', 'Her gün 12:00 – 00:00', 'text', 'saatler'],
        'temporarily_closed' => ['Geçici olarak kapalı (sitede gösterilir)', '0', 'bool', 'saatler'],

        // Ana sayfa metinleri
        'hero_badge' => ['Giriş rozeti', "AKYAKA'DAN SELAM!", 'text', 'metinler'],
        'hero_line1' => ['Büyük başlık 1. satır', 'KARNIN', 'text', 'metinler'],
        'hero_line2' => ['Büyük başlık 2. satır (sarı)', 'ACIKTI', 'text', 'metinler'],
        'hero_line3' => ['Büyük başlık 3. satır', 'MI?', 'text', 'metinler'],
        'hero_text' => ['Giriş paragrafı', "Börfi's'te burgerler kocaman, wrapler doyurucu, patatesler taze. Gel, otur, iki elinle ye.", 'textarea', 'metinler'],
        'hero_sticker' => ['Burger yanındaki etiket', 'iki elle ye!', 'text', 'metinler'],
        'hero_image' => ['Giriş görseli (boşsa burger çizimi)', '', 'image', 'metinler'],
        'categories_title' => ['Kategori başlığı', 'BUGÜN NE YİYORUZ?', 'text', 'metinler'],
        'categories_note' => ['Kategori el yazısı notu', 'hepsinden biraz →', 'text', 'metinler'],
        'featured_kicker' => ['Favoriler el yazısı notu', 'dikkat, bağımlılık yapabilir', 'text', 'metinler'],
        'featured_title' => ['Favoriler başlığı', "BÖRFİ'S FAVORİLERİ", 'text', 'metinler'],
        'slogan' => ['Dev slogan (3 kelime, boşlukla)', 'ISIR. ÇİĞNE. TEKRARLA.', 'text', 'metinler'],
        'about_title' => ['Hakkımızda başlığı', 'BİZ KİMİZ?', 'text', 'metinler'],
        'about_text' => ['Hakkımızda metni', "Börfi's Burger, Akyaka'da kaliteli malzemelerle hazırlanan özgün burgerleriyle hizmet veren bir burger restoranı. Lezzet, hijyen ve misafir memnuniyeti her şeyin önünde. Karanfil Sokak'ta, ızgaranın başında sizi bekliyoruz.", 'textarea', 'metinler'],
        'reviews_title' => ['Yorumlar başlığı', 'MİSAFİRLER NE DİYOR?', 'text', 'metinler'],
        'google_rating' => ['Google puanı', '4,8', 'text', 'metinler'],
        'google_review_count' => ['Google yorum sayısı', '250', 'text', 'metinler'],
        'google_reviews_url' => ['Google yorumları bağlantısı', 'https://www.google.com/search?q=B%C3%B6rfi%27s+Burger+Akyaka+yorumlar', 'url', 'metinler'],
        'price_range' => ['Kişi başı fiyat aralığı', '₺400–600', 'text', 'metinler'],
        'menu_note' => ['Menü sayfası alt notu', 'Fiyatlara KDV dahildir. Alerjen bilgisi için lütfen ekibimize sorun.', 'textarea', 'metinler'],
        'footer_text' => ['Alt bilgi notu', 'Yine bekleriz!', 'text', 'metinler'],

        // Görünüm
        'color_red' => ['Ana kırmızı', '#CF3631', 'color', 'gorunum'],
        'color_yellow' => ['Peynir sarısı', '#F0B445', 'color', 'gorunum'],
        'color_dark' => ['Kömür', '#1E1716', 'color', 'gorunum'],
        'color_paper' => ['Zemin', '#FFF8F1', 'color', 'gorunum'],
        'show_ticker' => ['Kayan yazıyı göster', '1', 'bool', 'gorunum'],
        'show_reviews' => ['Yorumlar bölümünü göster', '1', 'bool', 'gorunum'],
        'show_gallery' => ['Galeri bölümünü göster', '1', 'bool', 'gorunum'],
        'show_prices' => ['Fiyatları göster', '1', 'bool', 'gorunum'],

        // SEO
        'seo_title' => ['Sayfa başlığı (Google)', "Börfi's Burger Akyaka — Burger & More | Ula, Muğla", 'text', 'seo'],
        'seo_description' => ['Açıklama (Google)', "Akyaka Karanfil Sokak'ta özgün burgerler ve wrapler. Her gün 12:00 – 00:00. Menü, adres ve yol tarifi.", 'textarea', 'seo'],
        'og_image' => ['Paylaşım görseli (1200×630)', '', 'image', 'seo'],
    ];
}

function seed(PDO $pdo): void
{
    $pdo->beginTransaction();
    $ins = $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES(?, ?)');
    foreach (setting_definitions() as $key => $def) {
        $ins->execute([$key, $def[1]]);
    }

    $cats = [
        ['BURGERLER', 'Kocaman köfteler, eriyen cheddar', 1],
        ['WRAPLER', 'Sar, ısır, yürü', 2],
        ['YANLAR', 'Taze patates ve dostları', 3],
        ['İÇECEKLER', 'Serinlemek için', 4],
    ];
    $c = $pdo->prepare('INSERT INTO categories(name, tagline, sort) VALUES(?,?,?)');
    $ids = [];
    foreach ($cats as $cat) {
        $c->execute($cat);
        $ids[] = (int) $pdo->lastInsertId();
    }

    // Instagram ve Google'da adı geçen ürünler; fiyatlar panelden girilecek.
    $products = [
        [$ids[0], "BÖRFİ'S BURGER", 'Evin imza burgeri.', null, 'İMZA', 1, 1],
        [$ids[0], 'L.A. BURGER', 'Misafirlerin en sevdiği.', null, 'FAVORİ', 1, 2],
        [$ids[0], 'AKYAKA BURGER', '', null, '', 1, 3],
        [$ids[0], 'MUSHGOVA', 'Mantarlı burger.', null, '', 0, 4],
        [$ids[0], 'PERİ BURGER', 'Tavuk burger.', null, '', 0, 5],
        [$ids[2], 'PATATES KIZARTMASI', 'Taze patatesten.', null, '', 0, 1],
    ];
    $p = $pdo->prepare('INSERT INTO products(category_id, name, description, price, badge, is_featured, sort) VALUES(?,?,?,?,?,?,?)');
    foreach ($products as $row) {
        $p->execute($row);
    }

    $reviews = [
        ['Ekrem T.', "Akyaka'da yediğim en temiz yemek. Patatesler taze, hamburger köftesi lezzetli, fiyatlar normal, personel güler yüzlü. Mutlaka şans verin.", 5, 'Google', 1],
        ['Cafer N.', 'Gerçekten çok lezzetli burgerler yedik ve memnun ayrıldık, biz de 5 yıldız veriyoruz.', 5, 'Google', 2],
        ['Tuğbanur D.', 'Küçük, işlek bir caddede tatlı bir mekan. Misafirperver bir işletme.', 5, 'Google', 3],
    ];
    $r = $pdo->prepare('INSERT INTO reviews(author, body, rating, source, sort) VALUES(?,?,?,?,?)');
    foreach ($reviews as $row) {
        $r->execute($row);
    }
    $pdo->commit();
}
