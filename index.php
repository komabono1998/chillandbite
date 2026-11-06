<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/controllers/site.php';
require __DIR__ . '/app/controllers/booking.php';
require __DIR__ . '/app/controllers/admin.php';
require __DIR__ . '/app/controllers/admin_content.php';
require __DIR__ . '/app/controllers/partners.php';
require __DIR__ . '/app/controllers/admin_hub.php';
require __DIR__ . '/app/controllers/tickets.php';
require __DIR__ . '/app/controllers/accreditation.php';
require __DIR__ . '/app/controllers/djs.php';

header('X-Frame-Options: SAMEORIGIN');

$routes = [
    // public
    ['GET',  '',                     'page_home'],
    ['GET',  'about',                'page_about'],
    ['GET',  'events',               'page_events'],
    ['GET',  'events/{slug}',        'page_event'],
    ['GET',  'events/{slug}/reserve', 'page_reserve'],
    ['POST', 'events/{slug}/reserve', 'post_reserve'],
    ['GET',  'booking/{ref}',        'page_booking'],
    ['GET',  'ticket/{token}',       'page_ticket'],
    ['GET',  'accreditation',        'page_accreditation'],
    ['POST', 'accreditation',        'post_accreditation'],
    ['GET',  'track',                'page_track'],
    ['POST', 'track',                'post_track'],
    ['GET',  'admin/checkin/{token}', 'admin_checkin'],
    ['GET',  'laugh',                'page_laugh'],
    ['POST', 'laugh',                'post_performance'],
    ['GET',  'djs',                  'page_djs'],
    ['POST', 'djs',                  'post_dj_booking'],
    ['GET',  'news',                 'page_news'],
    ['GET',  'news/{slug}',          'page_article'],
    ['GET',  'advertise',            'page_advertise'],
    ['POST', 'advertise',            'post_advertise'],
    ['GET',  'partner',              'page_partner'],
    ['POST', 'partner',              'post_partner'],
    ['GET',  'gallery',              'page_gallery'],
    ['GET',  'contact',              'page_contact'],
    ['POST', 'contact',              'post_contact'],
    ['GET',  'privacy',              'page_privacy'],
    ['GET',  'sitemap.xml',          'page_sitemap'],
    ['GET',  'robots.txt',           'page_robots'],
    // admin
    ['GET',  'admin',                'admin_dashboard'],
    ['GET',  'admin/login',          'admin_login_form'],
    ['POST', 'admin/login',          'admin_login'],
    ['POST', 'admin/logout',         'admin_logout'],
    ['GET',  'admin/pages',          'admin_pages'],
    ['GET',  'admin/pages/{page}',   'admin_page_edit'],
    ['POST', 'admin/pages/{page}',   'admin_page_save'],
    ['GET',  'admin/reports',        'admin_reports'],
    ['GET',  'admin/export/{dataset}', 'admin_export'],
    ['GET',  'admin/checkin',        'admin_checkin_desk'],
    ['GET',  'admin/accreditation',  'admin_accreditation'],
    ['POST', 'admin/accreditation/{id}', 'admin_accreditation_action'],
    ['GET',  'admin/account',        'admin_account'],
    ['POST', 'admin/account',        'admin_account_save'],
    ['GET',  'admin/tickets',        'admin_tickets'],
    ['GET',  'admin/tickets/print',  'admin_tickets_print'],
    ['POST', 'admin/tickets/issue',  'admin_ticket_issue'],
    ['POST', 'admin/tickets/{id}/regenerate', 'admin_ticket_regenerate'],
    ['GET',  'admin/events',         'admin_events'],
    ['GET',  'admin/events/new',     'admin_event_form'],
    ['GET',  'admin/events/{id}',    'admin_event_form'],
    ['POST', 'admin/events/save',    'admin_event_save'],
    ['POST', 'admin/events/{id}/status', 'admin_event_status'],
    ['POST', 'admin/events/recurring', 'admin_event_recurring'],
    ['GET',  'admin/reservations',   'admin_reservations'],
    ['GET',  'admin/reservations/{id}', 'admin_reservation'],
    ['POST', 'admin/reservations/{id}/action', 'admin_reservation_action'],
    ['GET',  'admin/news',           'admin_news'],
    ['GET',  'admin/news/new',       'admin_article_form'],
    ['GET',  'admin/news/{id}',      'admin_article_form'],
    ['POST', 'admin/news/save',      'admin_article_save'],
    ['POST', 'admin/news/{id}/delete', 'admin_article_delete'],
    ['GET',  'admin/comedy',         'admin_comedy'],
    ['POST', 'admin/comedy',         'admin_comedy_save'],
    ['GET',  'admin/djs',            'admin_djs'],
    ['POST', 'admin/djs',            'admin_djs_save'],
    ['GET',  'admin/gallery',        'admin_gallery'],
    ['POST', 'admin/gallery',        'admin_gallery_save'],
    ['GET',  'admin/advertising',    'admin_advertising'],
    ['POST', 'admin/advertising',    'admin_advertising_save'],
    ['GET',  'admin/inquiries',      'admin_inquiries'],
    ['POST', 'admin/inquiries/{id}', 'admin_inquiry_update'],
    ['GET',  'admin/partners',       'admin_partners'],
    ['POST', 'admin/partners/{id}',  'admin_partner_action'],
    ['GET',  'admin/users',          'admin_users'],
    ['POST', 'admin/users',          'admin_users_save'],
    ['GET',  'admin/settings',       'admin_settings'],
    ['POST', 'admin/settings',       'admin_settings_save'],
    ['GET',  'admin/audit',          'admin_audit'],
];

$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
if (BASE !== '' && str_starts_with($path, BASE)) $path = substr($path, strlen(BASE));
$path = trim(rawurldecode($path), '/');

try {
    foreach ($routes as [$m, $pattern, $handler]) {
        if ($m !== $method) continue;
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        if (!preg_match($regex, $path, $mt)) continue;
        $params = array_filter($mt, 'is_string', ARRAY_FILTER_USE_KEY);
        if ($method === 'POST') csrf_verify();
        $handler($params);
        exit;
    }
    not_found();
} catch (Throwable $e) {
    error_log((string)$e);
    http_response_code(500);
    if (DEBUG) { echo '<pre>' . h((string)$e) . '</pre>'; exit; }
    echo 'Something went wrong. Please try again shortly.';
}
