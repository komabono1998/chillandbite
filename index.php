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
require __DIR__ . '/app/controllers/pictures.php';
require __DIR__ . '/app/controllers/setup.php';
require __DIR__ . '/app/analytics.php';
require __DIR__ . '/app/seo.php';
require __DIR__ . '/app/daraza.php';
require __DIR__ . '/app/controllers/payments.php';
require __DIR__ . '/app/controllers/collections.php';
require __DIR__ . '/app/controllers/paytabs.php';
require __DIR__ . '/app/controllers/rates.php';
require __DIR__ . '/app/qr.php';
require __DIR__ . '/app/ticketpdf.php';
require __DIR__ . '/app/controllers/gentickets.php';
require __DIR__ . '/app/controllers/analytics.php';

header('X-Frame-Options: SAMEORIGIN');

$routes = [
    // public
    ['GET',  '',                     'page_home'],
    ['GET',  'about',                'page_about'],
    ['GET',  'events',               'page_events'],
    ['GET',  'events/{slug}',        'page_event'],
    ['GET',  'events/{slug}/reserve', 'page_reserve'],
    ['POST', 'events/{slug}/reserve', 'post_reserve'],
    ['POST', 'events/{slug}/ticket', 'post_ticket'],
    ['POST', 'paytab/{slug}', 'post_paytab'],
    ['GET',  'paytab/{slug}/status/{token}', 'page_paytab_status'],
    ['GET',  'booking/{ref}',        'page_booking'],
    ['GET',  'booking/{ref}/status', 'page_booking_status'],
    ['POST', 'booking/{ref}/pay',    'post_booking_pay'],
    ['GET',  'booking/{ref}/pay/{token}', 'page_pay_status'],
    ['POST', 'admin/reservations/{id}/daraza-refund', 'admin_daraza_refund'],
    ['POST', 'admin/pay-attempts/{id}/resolve', 'admin_pay_resolve'],
    ['GET',  'ticket/{token}',       'page_ticket'],
    ['GET',  'ticket/{token}/status', 'page_ticket_status'],
    ['GET',  'track',                'page_track'],
    ['GET',  'setup',                'page_setup'],
    ['POST', 'setup',                'post_setup'],
    ['POST', 'track',                'post_track'],
    ['GET',  'admin/checkin/{token}', 'admin_checkin'],
    ['GET',  'laugh',                'page_laugh'],
    ['POST', 'laugh',                'post_performance'],
    ['GET',  'djs',                  'page_djs'],
    ['POST', 'djs',                  'post_dj_booking'],
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
    ['GET',  'admin/analytics',      'admin_analytics'],
    ['GET',  'admin/collections',    'admin_collections'],
    ['GET',  'admin/pay-tabs',       'admin_paytabs'],
    ['POST', 'admin/pay-tabs',       'admin_paytabs_save'],
    ['POST', 'admin/pay-tabs/new',   'admin_paytab_new'],
    ['POST', 'admin/pay-tabs/{id}/delete', 'admin_paytab_delete'],
    ['GET',  'admin/rates',          'admin_rates'],
    ['GET',  'admin/gen-tickets',    'admin_gentickets'],
    ['POST', 'admin/gen-tickets/create', 'admin_gen_create'],
    ['POST', 'admin/gen-tickets/categories', 'admin_gen_categories_save'],
    ['POST', 'admin/gen-tickets/categories/{id}/delete', 'admin_gen_category_delete'],
    ['GET',  'admin/gen-tickets/batch/{id}', 'admin_gen_batch'],
    ['GET',  'admin/gen-tickets/batch/{id}/edit', 'admin_gen_batch_edit'],
    ['POST', 'admin/gen-tickets/batch/{id}/edit', 'admin_gen_batch_edit'],
    ['GET',  'admin/gen-tickets/download', 'admin_gen_download'],
    ['GET',  'admin/gen-tickets/preview', 'admin_gen_preview'],
    ['POST', 'admin/gen-tickets/ticket/{id}/{action}', 'admin_gen_ticket_action'],
    ['POST', 'admin/rates/apply-all', 'admin_rates_apply_all'],
    ['POST', 'admin/rates/package-size', 'admin_rates_package_size'],
    ['POST', 'admin/rates/{id}',     'admin_rates_save'],
    ['POST', 'admin/rates/{id}/copy', 'admin_rates_copy'],
    ['GET',  'admin/collections/fees', 'admin_collections_fees'],
    ['POST', 'admin/collections/withdraw', 'admin_withdraw'],
    ['POST', 'admin/withdrawals/{id}/resolve', 'admin_withdraw_resolve'],
    ['POST', 'admin/system/migrate', 'admin_migrate'],
    ['GET',  'admin/checkin',        'admin_checkin_desk'],
    ['GET',  'admin/accreditation',  'admin_accreditation'],
    ['POST', 'admin/accreditation/{id}', 'admin_accreditation_action'],
    ['GET',  'admin/account',        'admin_account'],
    ['POST', 'admin/account',        'admin_account_save'],
    ['GET',  'admin/tickets',        'admin_tickets'],
    ['GET',  'admin/tickets/print',  'admin_tickets_print'],
    ['POST', 'admin/tickets/issue',  'admin_ticket_issue'],
    ['POST', 'admin/tickets/mark-all', 'admin_tickets_mark_all'],
    ['POST', 'admin/tickets/{id}/sent', 'admin_ticket_sent'],
    ['POST', 'admin/tickets/{id}/regenerate', 'admin_ticket_regenerate'],
    ['POST', 'admin/tickets/{id}/email', 'admin_ticket_email'],
    ['POST', 'admin/tickets/{id}/update', 'admin_ticket_update'],
    ['POST', 'admin/tickets/{id}/withdraw', 'admin_ticket_withdraw'],
    ['POST', 'admin/tickets/{id}/reinstate', 'admin_ticket_reinstate'],
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
    ['GET',  'admin/pictures',       'admin_pictures'],
    ['POST', 'admin/pictures',       'admin_pictures_save'],
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

track_visit($path);

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
