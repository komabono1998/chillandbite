<?php
declare(strict_types=1);

/* ================= Shared queries ================= */
function upcoming_events(int $limit = 6, ?string $catSlug = null): array
{
    $sql = "SELECT e.*, c.name AS category FROM events e LEFT JOIN event_categories c ON c.id = e.category_id
            WHERE e.status = 'published' AND COALESCE(e.end_at, e.start_at) >= NOW()";
    $p = [];
    if ($catSlug) { $sql .= ' AND c.slug = ?'; $p[] = $catSlug; }
    $sql .= ' ORDER BY e.start_at ASC LIMIT ' . (int)$limit;
    return rows($sql, $p);
}
function find_event(string $slug, bool $publicOnly = true): ?array
{
    $sql = 'SELECT e.*, c.name AS category FROM events e LEFT JOIN event_categories c ON c.id = e.category_id WHERE e.slug = ?';
    if ($publicOnly) $sql .= " AND e.status IN ('published','cancelled')";
    return row($sql, [$slug]);
}
function event_options(int $eventId): array
{
    return rows('SELECT * FROM event_reservation_options WHERE event_id = ? ORDER BY FIELD(type,"individual","couple","group","corporate","vip","table")', [$eventId]);
}
function published_articles(int $limit = 6, ?string $catSlug = null, int $offset = 0, ?string $q = null): array
{
    $sql = "SELECT a.*, c.name AS category, c.slug AS category_slug, u.name AS author, u.organization AS author_org, u.role AS author_role FROM articles a
            LEFT JOIN article_categories c ON c.id = a.category_id LEFT JOIN users u ON u.id = a.author_id
            WHERE a.status IN ('published','scheduled') AND a.published_at <= NOW()";
    $p = [];
    if ($catSlug) { $sql .= ' AND c.slug = ?'; $p[] = $catSlug; }
    if ($q !== null && $q !== '') { $like = '%' . addcslashes($q, '%_\\') . '%'; $sql .= ' AND (a.title LIKE ? OR a.excerpt LIKE ? OR a.body LIKE ?)'; array_push($p, $like, $like, $like); }
    $sql .= ' ORDER BY a.published_at DESC LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    return rows($sql, $p);
}
function gcal_link(array $e): string
{
    $fmt = fn($d) => date('Ymd\THis', strtotime($d));
    $end = $e['end_at'] ?: date('Y-m-d H:i:s', strtotime($e['start_at'] . ' +4 hours'));
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE', 'text' => $e['title'], 'dates' => $fmt($e['start_at']) . '/' . $fmt($end),
        'ctz' => 'Africa/Kampala', 'location' => $e['venue'] . ', ' . $e['location'],
        'details' => ($e['short_desc'] ?? '') . ' ' . abs_url('events/' . $e['slug']),
    ]);
}

/** Photos for the homepage wall: featured first, then the newest, mixed so neighbours differ. Mix changes daily. */
function wall_photos(int $limit): array
{
    $rows = rows('SELECT * FROM gallery_images ORDER BY featured DESC, id DESC LIMIT ' . (int)$limit);
    mt_srand(crc32(date('Y-m-d'))); shuffle($rows); mt_srand();
    return $rows;
}

/** Add the countdown columns to a site that was set up before they existed. */
function events_ensure_countdown_cols(): void
{
    static $done = false; if ($done) return; $done = true;
    try {
        if (!(int)val("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'countdown_at'"))
            db()->exec('ALTER TABLE events ADD COLUMN countdown_at DATETIME NULL AFTER video_url, ADD COLUMN countdown_label VARCHAR(80) NULL AFTER countdown_at');
    } catch (PDOException $e) { error_log('events_ensure_countdown_cols: ' . $e->getMessage()); }
}
/**
 * What the homepage countdown runs to: the event's own countdown time (for example the main show at 6 PM) while it is still ahead,
 * otherwise the start time. Returns [DateTimeImmutable time, label or null, true when it is the custom time].
 */
function event_countdown(?array $e, ?DateTimeImmutable $fallback = null): array
{
    $start = $e ? new DateTimeImmutable($e['start_at']) : ($fallback ?? next_first_sunday());
    if ($e && !empty($e['countdown_at'])) {
        $cd = new DateTimeImmutable($e['countdown_at']);
        if ($cd > new DateTimeImmutable('now')) return [$cd, trim((string)($e['countdown_label'] ?? '')) ?: null, true];
    }
    return [$start, null, false];
}

/* ================= Pages ================= */
function page_home(): void
{
    // Hero/featured card is the next monthly Chill & Bite; other upcoming events (any category) follow it.
    $events = upcoming_events(8);
    $monthly = upcoming_events(1, 'chill-bite-meatings')[0] ?? ($events[0] ?? null);
    $next = $monthly;
    $events = $next ? array_merge([$next], array_values(array_filter($events, fn($e) => $e['id'] !== $next['id']))) : [];
    $events = array_slice($events, 0, 4);
    render('site/home', [
        'title' => 'Chill & Bite MEATINGS | Monthly Event in Gulu by MC Kash Owakabi',
        'desc' => 'Chill & Bite MEATINGS is the Gulu City monthly food, music, comedy and networking event, hosted by MC Kash Owakabi and organized by Owakabi & The Geng. Reserve your table today.',
        'next' => $next,
        'next_date' => $next ? new DateTimeImmutable($next['start_at']) : next_first_sunday(),
        'cd' => event_countdown($next),
        'others' => array_slice($events, 1),
        'video' => row('SELECT * FROM videos ORDER BY featured DESC, id DESC LIMIT 1'),
        'sponsors' => rows('SELECT * FROM sponsors WHERE active = 1 ORDER BY FIELD(tier,"title","gold","silver","partner"), name'),
        'gallery' => wall_photos(36),
        'gallery_total' => (int)val('SELECT COUNT(*) FROM gallery_images'),
        'comedy_events' => upcoming_events(2, 'laugh-with-owakabi-comedy-shows'),
    ]);
}
function page_about(): void
{
    $next = upcoming_events(1, 'chill-bite-meatings')[0] ?? (upcoming_events(1)[0] ?? null);
    render('site/about', [
        'title' => 'About Chill & Bite MEATINGS | MC Kash Owakabi & The Geng, Gulu',
        'desc' => 'The story of Chill & Bite MEATINGS, the monthly lifestyle and networking experience in Gulu City, Uganda, hosted by MC Kash Owakabi and organized by Owakabi & The Geng.',
        'desc' => 'The story, mission, team and partners behind Chill & Bite MEATINGS, the monthly lifestyle and corporate networking experience in Gulu City.',
        'sponsors' => approved_partners(),
        'next' => $next,
    ]);
}
function page_events(): void
{
    $cat = preg_match('/^[a-z0-9-]+$/', $_GET['cat'] ?? '') ? $_GET['cat'] : null;
    $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $_GET['month'] ?? '') ? $_GET['month'] : date('Y-m');
    $view = ($_GET['view'] ?? 'list') === 'calendar' ? 'calendar' : 'list';
    $from = $month . '-01 00:00:00';
    $to = date('Y-m-d 23:59:59', strtotime("$month-01 +1 month -1 day"));
    $sql = "SELECT e.*, c.name AS category FROM events e LEFT JOIN event_categories c ON c.id = e.category_id
            WHERE e.status IN ('published','cancelled') AND e.start_at BETWEEN ? AND ?";
    $p = [$from, $to];
    if ($cat) { $sql .= ' AND c.slug = ?'; $p[] = $cat; }
    $events = rows($sql . ' ORDER BY e.start_at', $p);
    render('site/events', [
        'title' => 'Events in Gulu | Chill & Bite MEATINGS Calendar & Reservations',
        'desc' => 'See upcoming Chill & Bite MEATINGS and Laugh with Owakabi events in Gulu City, Uganda, and reserve your table or tickets online.', 'events' => $events, 'month' => $month, 'cat' => $cat, 'view' => $view,
        'categories' => rows('SELECT * FROM event_categories ORDER BY id'),
        'upcoming' => $view === 'list' && !isset($_GET['month']) ? upcoming_events(12, $cat) : null,
    ]);
}
function page_event(array $a): void
{
    $e = find_event($a['slug']) ?? not_found();
    render('site/event', [
        'title' => $e['title'] . ' | Chill & Bite MEATINGS', 'desc' => $e['short_desc'], 'e' => $e,
        'options' => event_options((int)$e['id']), 'packages' => event_packages((int)$e['id']), 'open' => event_open_for_booking($e), 'remaining' => event_remaining($e),
        'sponsors' => rows('SELECT * FROM sponsors WHERE active = 1 ORDER BY FIELD(tier,"title","gold","silver","partner") LIMIT 8'),
    ]);
}
function page_laugh(): void
{
    render('site/laugh', [
        'title' => 'Laugh with Owakabi | Comedy Shows & Videos by MC Kash Owakabi',
        'desc' => 'Life is better with a little humour! Comedy shows, videos and performance bookings with MC Kash Owakabi.',
        'videos' => rows('SELECT * FROM videos ORDER BY featured DESC, id DESC'),
        'shows' => upcoming_events(6, 'laugh-with-owakabi-comedy-shows'),
    ]);
}
function page_advertise(): void
{
    render('site/advertise', [
        'title' => 'Advertise & Sponsor | Chill & Bite MEATINGS Gulu, Uganda',
        'desc' => 'Advertise your business or sponsor Chill & Bite MEATINGS and Laugh with Owakabi events in Gulu. Banner ads, sponsored articles, brand activations and annual partnerships.',
        'sponsors' => rows('SELECT * FROM sponsors WHERE active = 1 ORDER BY FIELD(tier,"title","gold","silver","partner"), name'),
    ]);
}
function page_gallery(): void
{
    $album = preg_match('/^[a-z0-9-]+$/', $_GET['album'] ?? '') ? $_GET['album'] : null;
    $sql = 'SELECT i.*, a.name AS album FROM gallery_images i LEFT JOIN gallery_albums a ON a.id = i.album_id';
    $p = [];
    if ($album) { $sql .= ' WHERE a.slug = ?'; $p[] = $album; }
    render('site/gallery', [
        'title' => 'Photo Gallery | Chill & Bite MEATINGS Events in Gulu',
        'desc' => 'Photos from Chill & Bite MEATINGS and Laugh with Owakabi events in Gulu City, Uganda.', 'album' => $album,
        'images' => rows($sql . ' ORDER BY i.id DESC', $p), 'albums' => rows('SELECT * FROM gallery_albums ORDER BY id'),
    ]);
}
function page_contact(): void { render('site/contact', ['title' => 'Contact Chill & Bite MEATINGS | Owakabi & The Geng, Gulu', 'desc' => 'Contact Chill & Bite MEATINGS and Owakabi & The Geng in Gulu City, Uganda for bookings, partnerships and enquiries.']); }
function page_privacy(): void { render('site/privacy', ['title' => 'Privacy & Cookies | Chill & Bite MEATINGS']); }
function page_robots(): void
{
    header('Content-Type: text/plain');
    echo "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /app\nDisallow: /booking/\nDisallow: /ticket/\nDisallow: /track\nDisallow: /setup\nSitemap: " . abs_url('sitemap.xml') . "\n";
}
function page_sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $pri = ['' => ['1.0', 'weekly'], 'events' => ['0.9', 'daily'], 'about' => ['0.8', 'monthly'], 'laugh' => ['0.8', 'weekly'], 'djs' => ['0.7', 'weekly'], 'advertise' => ['0.6', 'monthly'],
            'partner' => ['0.5', 'monthly'], 'gallery' => ['0.6', 'weekly'], 'contact' => ['0.6', 'yearly'], 'privacy' => ['0.2', 'yearly']];
    $u = [];
    foreach ($pri as $p => [$prio, $freq]) $u[] = [abs_url($p), null, $prio, $freq];
    foreach (rows("SELECT slug, updated_at FROM events WHERE status = 'published' ORDER BY start_at DESC") as $r)
        $u[] = [abs_url('events/' . $r['slug']), date('c', strtotime((string)$r['updated_at'])), '0.8', 'weekly'];
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($u as [$l, $mod, $prio, $freq]) echo '<url><loc>' . h($l) . '</loc>' . ($mod ? '<lastmod>' . $mod . '</lastmod>' : '') . '<changefreq>' . $freq . '</changefreq><priority>' . $prio . '</priority></url>';
    echo '</urlset>';
}

/* ================= Public forms (contact / performance / advertising) ================= */
function form_guard(string $bucket, string $back): array
{
    if (!empty($_POST['website'])) { flash('Thank you!'); redirect($back); } // honeypot
    if (rate_limited($bucket, 5, 3600)) { flash('Too many submissions from your connection. Please try again later.', 'err'); redirect($back); }
    $d = [];
    foreach ($_POST as $k => $v) if (is_string($v)) $d[$k] = trim($v);
    return $d;
}
function form_fail(array $errors, array $data, string $back): never
{
    keep_old($data);
    foreach ($errors as $e) flash($e, 'err');
    redirect($back);
}
function valid_phone(string $p): bool { return (bool)preg_match('/^\+?[0-9][0-9 \-]{7,18}$/', $p); }

function post_contact(): void
{
    $d = form_guard('contact', 'contact');
    $err = [];
    if (mb_strlen($d['name'] ?? '') < 2) $err[] = 'Please enter your name.';
    if (!valid_phone($d['phone'] ?? '') && !filter_var($d['email'] ?? '', FILTER_VALIDATE_EMAIL)) $err[] = 'Please give a valid phone number or email so we can reply.';
    if (mb_strlen($d['message'] ?? '') < 5) $err[] = 'Please write a message.';
    if ($err) form_fail($err, $d, 'contact');
    q('INSERT INTO inquiries (type, name, phone, email, subject, message) VALUES ("contact",?,?,?,?,?)',
        [$d['name'], $d['phone'] ?? null, ($d['email'] ?? '') ?: null, mb_substr($d['subject'] ?? '', 0, 190), mb_substr($d['message'], 0, 5000)]);
    clear_old();
    flash('Thank you! Your message has been received and the team will get back to you.');
    redirect('contact');
}
function post_performance(): void
{
    $d = form_guard('performance', 'laugh#book');
    $err = [];
    if (mb_strlen($d['name'] ?? '') < 2) $err[] = 'Please enter your name.';
    if (!valid_phone($d['phone'] ?? '')) $err[] = 'Please enter a valid phone number.';
    if (($d['email'] ?? '') !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $err[] = 'Email address looks invalid.';
    if (empty($d['event_date']) || !strtotime($d['event_date'])) $err[] = 'Please choose the event date.';
    if (mb_strlen($d['location'] ?? '') < 2) $err[] = 'Please tell us the event location.';
    if ($err) form_fail($err, $d, 'laugh#book');
    $details = json_encode(array_intersect_key($d, array_flip(['event_date', 'location', 'event_type', 'audience'])), JSON_UNESCAPED_UNICODE);
    q('INSERT INTO inquiries (type, name, organization, phone, email, subject, message, details) VALUES ("performance",?,?,?,?,?,?,?)',
        [$d['name'], $d['organization'] ?? null, $d['phone'], ($d['email'] ?? '') ?: null, 'Comedy performance booking', mb_substr($d['message'] ?? '', 0, 5000), $details]);
    clear_old();
    flash('Thank you! Your performance request was sent. We will contact you shortly.');
    redirect('laugh');
}
function post_advertise(): void
{
    $d = form_guard('advertise', 'advertise#proposal');
    $err = [];
    if (mb_strlen($d['organization'] ?? '') < 2) $err[] = 'Please enter your business or organization name.';
    if (mb_strlen($d['name'] ?? '') < 2) $err[] = 'Please enter the contact person.';
    if (!valid_phone($d['phone'] ?? '')) $err[] = 'Please enter a valid phone number.';
    if (!filter_var($d['email'] ?? '', FILTER_VALIDATE_EMAIL)) $err[] = 'Please enter a valid email address.';
    if (empty($d['service'])) $err[] = 'Please choose the advertising service you need.';
    if ($err) form_fail($err, $d, 'advertise#proposal');
    $details = json_encode(array_intersect_key($d, array_flip(['industry', 'service', 'dates', 'budget'])), JSON_UNESCAPED_UNICODE);
    q('INSERT INTO inquiries (type, name, organization, phone, email, subject, message, details) VALUES ("advertising",?,?,?,?,?,?,?)',
        [$d['name'], $d['organization'], $d['phone'], $d['email'], mb_substr($d['service'], 0, 190), mb_substr($d['message'] ?? '', 0, 5000), $details]);
    clear_old();
    flash('Thank you! Our partnerships team will send you a proposal soon.');
    redirect('advertise');
}
