<?php
// Usage: php database/install.php [--sample]
// Creates the database, tables, base settings, categories and the first admin account.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }

$root = dirname(__DIR__);
if (!is_file("$root/.env")) copy("$root/.env.example", "$root/.env");
require "$root/app/bootstrap.php";

$dbName = env('DB_NAME', 'chillandbite');
$pdo = new PDO(sprintf('mysql:host=%s;port=%s;charset=utf8mb4', env('DB_HOST', '127.0.0.1'), env('DB_PORT', '3306')),
    env('DB_USER', 'root'), env('DB_PASS', ''), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
echo "Database `$dbName` ready.\n";

$sql = file_get_contents("$root/database/schema.sql");
$sql = preg_replace('/^--.*$/m', '', $sql);
foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) db()->exec($stmt);
// Upgrade older databases: finance and door staff roles
db()->exec("ALTER TABLE users MODIFY role ENUM('admin','event_manager','editor','marketing','finance','door','contributor') NOT NULL DEFAULT 'editor'");
// Upgrade older databases: contributor organisation, article review status and source credit
$hasCol = fn(string $t, string $c) => (int)val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?', [$t, $c]);
if (!$hasCol('users', 'organization')) db()->exec('ALTER TABLE users ADD COLUMN organization VARCHAR(160) NULL AFTER role');
db()->exec("ALTER TABLE articles MODIFY status ENUM('draft','pending','scheduled','published') NOT NULL DEFAULT 'draft'");
if (!$hasCol('articles', 'source_name')) db()->exec('ALTER TABLE articles ADD COLUMN source_name VARCHAR(140) NULL AFTER sponsor_name');
if (!$hasCol('articles', 'source_url')) db()->exec('ALTER TABLE articles ADD COLUMN source_url VARCHAR(500) NULL AFTER source_name');
// Upgrade older databases: reservations.ticket_token column (unguessable ticket link / QR)
if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'ticket_token'")) {
    db()->exec('ALTER TABLE reservations ADD COLUMN ticket_token VARCHAR(40) NULL AFTER package');
    db()->exec('ALTER TABLE reservations ADD UNIQUE KEY uq_ticket_token (ticket_token)');
}
// Upgrade older databases: reservations.package column
if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'reservations' AND COLUMN_NAME = 'package'"))
    db()->exec('ALTER TABLE reservations ADD COLUMN package VARCHAR(80) NULL AFTER seating');
echo "Schema applied.\n";

// Categories
foreach (['Chill & Bite MEATINGS', 'Laugh with Owakabi Comedy Shows', 'Corporate Networking', 'Special Events', 'Brand Activations', 'Community Events'] as $n)
    q('INSERT IGNORE INTO event_categories (name, slug) VALUES (?,?)', [$n, slugify($n)]);
foreach (['Uganda News', 'World', 'Entertainment', 'Trending', 'Music', 'Lifestyle', 'Business', 'Sport', 'Events', 'Community', 'Gossip'] as $n)
    q('INSERT IGNORE INTO article_categories (name, slug) VALUES (?,?)', [$n, slugify($n)]);
foreach (['Chill & Bite Highlights', 'Food and Drinks', 'Music and Entertainment', 'Networking Moments', 'Outdoor Games', 'Guests and Personalities', 'Behind the Scenes'] as $n)
    q('INSERT IGNORE INTO gallery_albums (name, slug) VALUES (?,?)', [$n, slugify($n)]);

// Default settings (INSERT IGNORE so re-running never overwrites edits)
$defaults = [
    'site_name' => 'Chill & Bite MEATINGS',
    'tagline' => 'Where Connections Are Made, Opportunities Are Born, and Good Vibes Never End!',
    'announcement' => 'THE NEXT CHILL & BITE MEATINGS IS COMING! | EVERY FIRST SUNDAY | GULU CITY',
    'announcement_on' => '1',
    'venue' => 'Revelation Cottages', 'location' => 'Gulu City, Uganda',
    'contact_phone' => '+256768202585', 'contact_email' => '', 'whatsapp' => '+256768202585', 'whatsapp_group' => 'https://chat.whatsapp.com/HcYDbtQgRZN9LqnWogd8dZ?mode=gi_t',
    'social_facebook' => 'https://www.facebook.com/share/1FYRcmDqMn/', 'social_instagram' => 'https://www.instagram.com/chillandbitemeatings', 'social_youtube' => '', 'social_tiktok' => 'https://www.tiktok.com/@chillandbitemeatings', 'social_x' => '',
    'host_bio' => 'From electrifying crowds to creating memorable experiences, MC Kash Owakabi brings charisma, humor, and exceptional audience engagement to every event.',
];
foreach ($defaults as $k => $v) q('INSERT IGNORE INTO settings (`key`, `value`) VALUES (?,?)', [$k, $v]);

// Bundled event photos (assets/img/photos) sorted into the gallery albums; skipped once imported
if (!(int)val("SELECT COUNT(*) FROM gallery_images WHERE file LIKE 'img/photos/%'")) {
    $albums = [
        'chill-bite-highlights' => [1, 3, 4, 5, 6, 10, 14, 15, 17, 18, 37, 39, 40, 45, 48, 51, 52],
        'food-and-drinks' => [2, 23, 25, 26, 30],
        'music-and-entertainment' => [27, 32, 41, 44, 53],
        'networking-moments' => [22, 31, 33, 35, 38],
        'outdoor-games' => [11, 34],
        'guests-and-personalities' => [7, 8, 9, 12, 13, 16, 19, 21, 36, 46, 49, 50],
        'behind-the-scenes' => [20, 42, 43, 47],
    ];
    $featured = [3, 9, 10, 12, 2, 44, 46, 23];
    foreach ($albums as $slug => $nums) {
        $aid = (int)val('SELECT id FROM gallery_albums WHERE slug = ?', [$slug]);
        foreach ($nums as $n)
            q('INSERT INTO gallery_images (album_id, file, featured) VALUES (?,?,?)', [$aid ?: null, sprintf('img/photos/photo-%02d.jpg', $n), in_array($n, $featured, true) ? 1 : 0]);
    }
    echo "Event photos added to the gallery.\n";
}

// First admin
if (!(int)val("SELECT COUNT(*) FROM users")) {
    $email = 'admin@chillandbite.local';
    $pass = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Aa'), '=');
    q('INSERT INTO users (name, email, password_hash, role) VALUES (?,?,?,?)',
        ['System Administrator', $email, password_hash($pass, PASSWORD_DEFAULT), 'admin']);
    echo "\n=== FIRST ADMIN ACCOUNT (shown once) ===\nEmail:    $email\nPassword: $pass\nChange these after first login (Admin > Users).\n\n";
}

// Sample content
if (in_array('--sample', $argv, true) && !(int)val('SELECT COUNT(*) FROM events')) {
    $cat = (int)val("SELECT id FROM event_categories WHERE slug = 'chill-bite-meatings'");
    $comedy = (int)val("SELECT id FROM event_categories WHERE slug = 'laugh-with-owakabi-comedy-shows'");
    $now = new DateTimeImmutable('now');
    $start = null;
    for ($i = 0; $i < 4; $i++) {
        $start = next_first_sunday($start ? $start->modify('+1 day') : $now);
        $title = 'Chill & Bite MEATINGS: ' . $start->format('F Y');
        q("INSERT INTO events (category_id, title, slug, short_desc, description, start_at, end_at, status, capacity, booking_mode, featured, reservation_closes)
           VALUES (?,?,?,?,?,?,?, 'published', 200, 'instant', ?, ?)", [
            $cat, $title, unique_slug('events', $title),
            'Great food, networking, music, outdoor games, and entertainment.',
            "The monthly lifestyle and corporate networking experience hosted by MC Kash Owakabi at Revelation Cottages, Gulu City.\n\nEnjoy food, music, comedy, outdoor games and meaningful connections with professionals, entrepreneurs and creatives.

Companies and individuals come to network, market their events and promote their products and services to a lively, engaged crowd.",
            $start->format('Y-m-d H:i:s'), $start->setTime(20, 0)->format('Y-m-d H:i:s'),
            $i === 0 ? 1 : 0, $start->modify('-1 hour')->format('Y-m-d H:i:s'),
        ]);
        $eid = (int)db()->lastInsertId();
        foreach ([['individual', 0, 1, 1], ['couple', 0, 2, 2], ['group', 0, 3, 15], ['corporate', 0, 3, 50], ['table', 0, 2, 10]] as [$t, $p, $mn, $mx])
            q('INSERT INTO event_reservation_options (event_id, type, price_per_guest, min_guests, max_guests) VALUES (?,?,?,?,?)', [$eid, $t, $p, $mn, $mx]);
    }
    $t = 'Laugh with Owakabi: Live Comedy Night';
    $s = $now->modify('+3 weeks')->setTime(18, 0);
    q("INSERT INTO events (category_id, title, slug, short_desc, description, start_at, end_at, status, capacity, booking_mode)
       VALUES (?,?,?,?,?,?,?, 'published', 150, 'approval')", [$comedy, $t, unique_slug('events', $t), 'An evening of stand-up with MC Kash Owakabi and guest comedians.',
        "Sample listing. Replace with your real show details.", $s->format('Y-m-d H:i:s'), $s->modify('+3 hours')->format('Y-m-d H:i:s')]);
    $eid = (int)db()->lastInsertId();
    foreach ([['individual', 20000, 1, 1], ['couple', 20000, 2, 2], ['vip', 50000, 1, 6]] as [$ty, $p, $mn, $mx])
        q('INSERT INTO event_reservation_options (event_id, type, price_per_guest, min_guests, max_guests) VALUES (?,?,?,?,?)', [$eid, $ty, $p, $mn, $mx]);

    $adminId = (int)val("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
    $ent = (int)val("SELECT id FROM article_categories WHERE slug = 'entertainment'");
    $gos = (int)val("SELECT id FROM article_categories WHERE slug = 'gossip'");
    $arts = [
        ['Welcome to The Gulu Chronicles', $ent, 'news', 'Gulu news, gossip and blogs from Uganda and the world.', "The Gulu Chronicles is the news, gossip and blogs home of Chill & Bite MEATINGS.\n\nThis is a sample article. Replace it with your own reporting from Gulu and the wider Northern Uganda region."],
        ['Sample gossip column: what people are talking about', $gos, 'gossip', 'Clearly labelled entertainment chatter. Not verified news.', "This is a sample entertainment gossip piece. Gossip is clearly labelled and must not present allegations about identifiable people as established fact."],
    ];
    foreach ($arts as [$title, $c, $label, $ex, $body])
        q("INSERT INTO articles (category_id, author_id, title, slug, excerpt, body, label, status, published_at, featured) VALUES (?,?,?,?,?,?,?, 'published', NOW(), ?)",
            [$c, $adminId, $title, unique_slug('articles', $title), $ex, $body, $label, $label === 'news' ? 1 : 0]);
    echo "Sample content added.\n";
}
echo "Done.\n";
