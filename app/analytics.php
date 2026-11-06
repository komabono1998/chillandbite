<?php
declare(strict_types=1);

/*
 * Privacy-friendly visit statistics.
 * No cookies, no raw IP addresses stored: visitors are told apart by a daily rotating hash, and only the country code is kept.
 * Staff, bots and visitors who send "Do Not Track" are not counted.
 */

/* ---------- Visitor details from the request ---------- */
/** Real visitor address, also when the site sits behind a CDN or proxy (used only to find the country and tell visitors apart). */
function visitor_ip(): string
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_TRUE_CLIENT_IP', 'HTTP_X_REAL_IP'] as $k)
        if (!empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) return (string)$_SERVER[$k];
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR']))
        foreach (explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR']) as $ip) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return $ip;
        }
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}
function visitor_is_bot(string $ua): bool
{
    if (trim($ua) === '') return true;
    return (bool)preg_match('/bot|crawl|spider|slurp|facebookexternalhit|facebot|preview|headless|monitor|uptime|pingdom|lighthouse|gtmetrix|curl|wget|python|java\/|httpclient|okhttp|go-http|libwww|scrapy|axios|node-fetch|postman|bingpreview|ahrefs|semrush|mj12|dataprovider|petalbot|yandex|baidu|duckduck(?!go\s*\w*browser)|embedly|whatsapp\/|telegram|discord|slack|twitterbot|linkedinbot|pinterest/i', $ua);
}
function visitor_device(string $ua): string
{
    if (preg_match('/ipad|tablet|kindle|silk|playbook|nexus 7|nexus 9|sm-t\d/i', $ua) || (stripos($ua, 'android') !== false && stripos($ua, 'mobile') === false)) return 'tablet';
    if (preg_match('/mobi|iphone|ipod|android|windows phone|blackberry|opera mini|opera mobi/i', $ua)) return 'mobile';
    return 'desktop';
}
function visitor_browser(string $ua): string
{
    $map = ['/FBAN|FBAV|FB_IAB/i' => 'Facebook app', '/Instagram/i' => 'Instagram app', '/TikTok|musical_ly|Bytedance/i' => 'TikTok app', '/SamsungBrowser/i' => 'Samsung Internet',
            '/UCBrowser|UCWeb/i' => 'UC Browser', '/OPR\/|Opera|OPiOS/i' => 'Opera', '/Edg(e|A|iOS)?\//i' => 'Edge', '/Firefox|FxiOS/i' => 'Firefox',
            '/Chrome|CriOS/i' => 'Chrome', '/Safari/i' => 'Safari', '/MSIE|Trident/i' => 'Internet Explorer'];
    foreach ($map as $re => $name) if (preg_match($re, $ua)) return $name;
    return 'Other';
}
/** [source, host] where source is direct, search, social, referral or internal. */
function visitor_source(?string $referer): array
{
    $host = strtolower((string)parse_url((string)$referer, PHP_URL_HOST));
    $host = preg_replace('/^(www|m|l|lm|mobile)\./', '', $host);
    if ($host === '') return ['direct', null];
    $mine = strtolower((string)preg_replace('/^www\./', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
    $mine = preg_replace('/:\d+$/', '', $mine);
    if ($host === $mine) return ['internal', null];
    if (preg_match('/(^|\.)(google|bing|yahoo|duckduckgo|yandex|baidu|ecosia|startpage|brave)\./', $host . '.')) return ['search', mb_substr($host, 0, 120)];
    if (preg_match('/(^|\.)(facebook|fb|instagram|t|twitter|x|tiktok|youtube|youtu|linkedin|lnkd|pinterest|whatsapp|wa|reddit|snapchat|telegram|threads)\.(com|me|co|be|in|ms|net|org)$/', $host)) return ['social', mb_substr($host, 0, 120)];
    return ['referral', mb_substr($host, 0, 120)];
}
function analytics_trackable_path(string $path): bool
{
    if (preg_match('#^(admin|ticket|booking|setup|assets|uploads|sitemap\.xml|robots\.txt|favicon)#i', $path)) return false;
    return !str_ends_with($path, '/status');
}

/* ---------- Country ---------- */
function analytics_country_from_headers(): ?string
{
    foreach (['HTTP_CF_IPCOUNTRY', 'GEOIP_COUNTRY_CODE', 'HTTP_X_COUNTRY_CODE', 'HTTP_CLOUDFRONT_VIEWER_COUNTRY', 'HTTP_X_GEO_COUNTRY', 'HTTP_X_APPENGINE_COUNTRY'] as $k) {
        $v = strtoupper(trim((string)($_SERVER[$k] ?? '')));
        if (preg_match('/^[A-Z]{2}$/', $v) && !in_array($v, ['XX', 'T1', 'ZZ'], true)) return $v;
    }
    return null;
}
/** Country of an address through a free lookup service, cached by a hash of the address. Switched on with GEO_LOOKUP=1 in .env. */
function analytics_country_lookup(string $ip): ?string
{
    if (env('GEO_LOOKUP', '0') !== '1') return null;
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return null;
    $hash = hash_hmac('sha1', $ip, app_secret());
    $hit = row('SELECT country, checked_at FROM geo_cache WHERE ip_hash = ?', [$hash]);
    if ($hit && strtotime((string)$hit['checked_at']) > time() - 30 * 86400) return $hit['country'] ?: null;
    if ((int)val('SELECT COUNT(*) FROM geo_cache WHERE checked_at >= CURDATE()') > 400) return null;      // daily cap on outside lookups
    $country = null;
    $url = 'https://ipwho.is/' . rawurlencode($ip) . '?fields=success,country_code';
    $body = null;
    if (function_exists('curl_init')) {
        $c = curl_init($url);
        curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 2, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_USERAGENT => 'chillandbite-analytics']);
        $body = curl_exec($c); curl_close($c);
    } elseif (ini_get('allow_url_fopen')) {
        $body = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 2, 'header' => "User-Agent: chillandbite-analytics\r\n"]]));
    }
    $j = is_string($body) ? json_decode($body, true) : null;
    if (is_array($j) && !empty($j['success']) && preg_match('/^[A-Z]{2}$/', (string)($j['country_code'] ?? ''))) $country = $j['country_code'];
    if ($country !== null || $body !== null) q('REPLACE INTO geo_cache (ip_hash, country, checked_at) VALUES (?,?,NOW())', [$hash, $country]);
    return $country;
}

/* ---------- Recording ---------- */
function analytics_ensure_tables(): void
{
    db()->exec("CREATE TABLE IF NOT EXISTS page_views (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        viewed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        path VARCHAR(190) NOT NULL,
        visitor CHAR(16) NOT NULL,
        country CHAR(2) NULL,
        device ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
        browser VARCHAR(30) NULL,
        source VARCHAR(12) NOT NULL DEFAULT 'direct',
        ref_host VARCHAR(120) NULL,
        KEY idx_pv_time (viewed_at), KEY idx_pv_path (path, viewed_at), KEY idx_pv_country (country, viewed_at), KEY idx_pv_visitor (visitor, viewed_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS geo_cache (ip_hash CHAR(40) PRIMARY KEY, country CHAR(2) NULL, checked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
/** Called for every public GET page; the count is written after the page has been sent. */
function track_visit(string $path): void
{
    if (env('ANALYTICS', '1') === '0') return;
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET') return;
    $path = trim($path, '/');
    if (!analytics_trackable_path($path)) return;
    $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
    if (visitor_is_bot($ua)) return;
    if (($_SERVER['HTTP_DNT'] ?? '') === '1' || ($_SERVER['HTTP_SEC_GPC'] ?? '') === '1') return;                  // respect Do Not Track
    if (!str_contains((string)($_SERVER['HTTP_ACCEPT'] ?? ''), 'text/html')) return;                              // pages only, not files or background calls
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || str_contains(strtolower((string)($_SERVER['HTTP_PURPOSE'] ?? '') . (string)($_SERVER['HTTP_SEC_PURPOSE'] ?? '')), 'prefetch')) return;
    try { if (current_user()) return; } catch (Throwable $e) { return; }                                          // do not count the team's own browsing
    $ctx = ['path' => '/' . mb_strtolower(mb_substr($path, 0, 180)), 'ua' => $ua, 'ip' => visitor_ip(), 'ref' => (string)($_SERVER['HTTP_REFERER'] ?? ''), 'country' => analytics_country_from_headers()];
    register_shutdown_function(function () use ($ctx) {
        if (http_response_code() !== 200) return;                                                                  // real pages only, not 404s and errors
        if (function_exists('fastcgi_finish_request')) @fastcgi_finish_request();
        elseif (function_exists('litespeed_finish_request')) @litespeed_finish_request();
        try {
            analytics_record($ctx);
        } catch (Throwable $e) { error_log('analytics: ' . $e->getMessage()); }
    });
}
function analytics_record(array $c): void
{
    $country = $c['country'] ?? null;
    if ($country === null) $country = analytics_country_lookup($c['ip']);
    [$source, $host] = visitor_source($c['ref']);
    $visitor = substr(hash_hmac('sha256', date('Y-m-d') . '|' . $c['ip'] . '|' . $c['ua'], app_secret()), 0, 16);       // changes every day, cannot be reversed
    $params = [$c['path'], $visitor, $country, visitor_device($c['ua']), visitor_browser($c['ua']), $source, $host];
    $sql = 'INSERT INTO page_views (path, visitor, country, device, browser, source, ref_host) VALUES (?,?,?,?,?,?,?)';
    try { q($sql, $params); }
    catch (PDOException $e) {
        if ($e->getCode() !== '42S02') throw $e;                                                                   // table missing after an update: create it once
        analytics_ensure_tables(); q($sql, $params);
    }
    if (random_int(1, 300) === 1) q('DELETE FROM page_views WHERE viewed_at < (NOW() - INTERVAL 540 DAY)');
}

/* ---------- Names ---------- */
function country_names(): array
{
    static $m = null;
    if ($m !== null) return $m;
    $raw = 'AF:Afghanistan|AL:Albania|DZ:Algeria|AD:Andorra|AO:Angola|AG:Antigua and Barbuda|AR:Argentina|AM:Armenia|AU:Australia|AT:Austria|AZ:Azerbaijan|BS:Bahamas|BH:Bahrain|BD:Bangladesh|BB:Barbados|BY:Belarus|BE:Belgium|BZ:Belize|BJ:Benin|BT:Bhutan|BO:Bolivia|BA:Bosnia and Herzegovina|BW:Botswana|BR:Brazil|BN:Brunei|BG:Bulgaria|BF:Burkina Faso|BI:Burundi|CV:Cabo Verde|KH:Cambodia|CM:Cameroon|CA:Canada|CF:Central African Republic|TD:Chad|CL:Chile|CN:China|CO:Colombia|KM:Comoros|CG:Congo|CD:DR Congo|CR:Costa Rica|CI:Cote d\'Ivoire|HR:Croatia|CU:Cuba|CY:Cyprus|CZ:Czechia|DK:Denmark|DJ:Djibouti|DM:Dominica|DO:Dominican Republic|EC:Ecuador|EG:Egypt|SV:El Salvador|GQ:Equatorial Guinea|ER:Eritrea|EE:Estonia|SZ:Eswatini|ET:Ethiopia|FJ:Fiji|FI:Finland|FR:France|GA:Gabon|GM:Gambia|GE:Georgia|DE:Germany|GH:Ghana|GR:Greece|GD:Grenada|GT:Guatemala|GN:Guinea|GW:Guinea-Bissau|GY:Guyana|HT:Haiti|HN:Honduras|HU:Hungary|IS:Iceland|IN:India|ID:Indonesia|IR:Iran|IQ:Iraq|IE:Ireland|IL:Israel|IT:Italy|JM:Jamaica|JP:Japan|JO:Jordan|KZ:Kazakhstan|KE:Kenya|KI:Kiribati|KW:Kuwait|KG:Kyrgyzstan|LA:Laos|LV:Latvia|LB:Lebanon|LS:Lesotho|LR:Liberia|LY:Libya|LI:Liechtenstein|LT:Lithuania|LU:Luxembourg|MG:Madagascar|MW:Malawi|MY:Malaysia|MV:Maldives|ML:Mali|MT:Malta|MH:Marshall Islands|MR:Mauritania|MU:Mauritius|MX:Mexico|FM:Micronesia|MD:Moldova|MC:Monaco|MN:Mongolia|ME:Montenegro|MA:Morocco|MZ:Mozambique|MM:Myanmar|NA:Namibia|NR:Nauru|NP:Nepal|NL:Netherlands|NZ:New Zealand|NI:Nicaragua|NE:Niger|NG:Nigeria|KP:North Korea|MK:North Macedonia|NO:Norway|OM:Oman|PK:Pakistan|PW:Palau|PS:Palestine|PA:Panama|PG:Papua New Guinea|PY:Paraguay|PE:Peru|PH:Philippines|PL:Poland|PT:Portugal|QA:Qatar|RO:Romania|RU:Russia|RW:Rwanda|KN:Saint Kitts and Nevis|LC:Saint Lucia|VC:Saint Vincent and the Grenadines|WS:Samoa|SM:San Marino|ST:Sao Tome and Principe|SA:Saudi Arabia|SN:Senegal|RS:Serbia|SC:Seychelles|SL:Sierra Leone|SG:Singapore|SK:Slovakia|SI:Slovenia|SB:Solomon Islands|SO:Somalia|ZA:South Africa|KR:South Korea|SS:South Sudan|ES:Spain|LK:Sri Lanka|SD:Sudan|SR:Suriname|SE:Sweden|CH:Switzerland|SY:Syria|TW:Taiwan|TJ:Tajikistan|TZ:Tanzania|TH:Thailand|TL:Timor-Leste|TG:Togo|TO:Tonga|TT:Trinidad and Tobago|TN:Tunisia|TR:Turkiye|TM:Turkmenistan|TV:Tuvalu|UG:Uganda|UA:Ukraine|AE:United Arab Emirates|GB:United Kingdom|US:United States|UY:Uruguay|UZ:Uzbekistan|VU:Vanuatu|VA:Vatican City|VE:Venezuela|VN:Vietnam|YE:Yemen|ZM:Zambia|ZW:Zimbabwe|HK:Hong Kong|MO:Macau|PR:Puerto Rico|XK:Kosovo|GL:Greenland|RE:Reunion|GP:Guadeloupe|MQ:Martinique|GF:French Guiana|NC:New Caledonia|PF:French Polynesia|GI:Gibraltar|JE:Jersey|GG:Guernsey|IM:Isle of Man|AW:Aruba|CW:Curacao|BM:Bermuda|KY:Cayman Islands|VG:British Virgin Islands|VI:US Virgin Islands|GU:Guam|SX:Sint Maarten|AX:Aland Islands|FO:Faroe Islands|YT:Mayotte|EH:Western Sahara|AQ:Antarctica';
    $m = [];
    foreach (explode('|', $raw) as $pair) { [$k, $v] = explode(':', $pair, 2); $m[$k] = $v; }
    return $m;
}
function country_name(?string $code): string { return $code ? (country_names()[$code] ?? $code) : 'Unknown'; }
function country_flag(?string $code): string
{
    if (!$code || !preg_match('/^[A-Z]{2}$/', $code) || !function_exists('mb_chr')) return '🌐';
    return mb_chr(0x1F1E6 + ord($code[0]) - 65, 'UTF-8') . mb_chr(0x1F1E6 + ord($code[1]) - 65, 'UTF-8');
}
/** Friendly name for a stored page path. */
function page_label(string $path): string
{
    static $known = ['/' => 'Home', '/about' => 'About us', '/events' => 'Events calendar', '/laugh' => 'Laugh with Owakabi', '/djs' => 'Podium DJs', '/advertise' => 'Advertise with us',
        '/partner' => 'Become a partner', '/gallery' => 'Gallery', '/contact' => 'Contact us', '/track' => 'Track a booking', '/privacy' => 'Privacy & cookies'];
    if (isset($known[$path])) return $known[$path];
    if (preg_match('#^/events/([^/]+)(/reserve)?$#', $path, $m)) {
        $t = val('SELECT title FROM events WHERE slug = ?', [$m[1]]);
        return ($m[2] ?? '') ? 'Reserve: ' . ($t ?: $m[1]) : 'Event: ' . ($t ?: $m[1]);
    }
    return $path;
}

/* ---------- Reports ---------- */
/** [from, to] as Y-m-d from ?range=today|7|30|90|year or ?from=&to=, default last 30 days. */
function analytics_range(): array
{
    $today = date('Y-m-d');
    $r = (string)($_GET['range'] ?? '');
    if (!empty($_GET['from']) && strtotime((string)$_GET['from'])) {
        $from = date('Y-m-d', strtotime((string)$_GET['from']));
        $to = !empty($_GET['to']) && strtotime((string)$_GET['to']) ? date('Y-m-d', strtotime((string)$_GET['to'])) : $today;
        if ($to < $from) [$from, $to] = [$to, $from];
        return [$from, $to, 'custom'];
    }
    return match ($r) {
        'today' => [$today, $today, 'today'],
        '7' => [date('Y-m-d', strtotime('-6 days')), $today, '7'],
        '90' => [date('Y-m-d', strtotime('-89 days')), $today, '90'],
        'year' => [date('Y-01-01'), $today, 'year'],
        default => [date('Y-m-d', strtotime('-29 days')), $today, '30'],
    };
}
/** Everything the Visitors page and the exports need for one period. */
function analytics_report(string $from, string $to): array
{
    $w = 'viewed_at >= ? AND viewed_at < DATE_ADD(?, INTERVAL 1 DAY)'; $p = [$from, $to];
    $one = fn(string $sql, array $args = []) => (int)val($sql, $args);
    try {
        $views = $one("SELECT COUNT(*) FROM page_views WHERE $w", $p);
        $visitors = $one("SELECT COUNT(*) FROM (SELECT DISTINCT DATE(viewed_at), visitor FROM page_views WHERE $w) t", $p);
    } catch (PDOException $e) { analytics_ensure_tables(); $views = $visitors = 0; }
    $daily = [];
    for ($d = strtotime($from), $n = 0; $d <= strtotime($to) && $n < 800; $d = strtotime('+1 day', $d), $n++) $daily[date('Y-m-d', $d)] = ['views' => 0, 'visitors' => 0];
    foreach (rows("SELECT DATE(viewed_at) d, COUNT(*) v, COUNT(DISTINCT visitor) u FROM page_views WHERE $w GROUP BY DATE(viewed_at)", $p) as $r)
        if (isset($daily[$r['d']])) $daily[$r['d']] = ['views' => (int)$r['v'], 'visitors' => (int)$r['u']];
    $pages = rows("SELECT path, COUNT(*) views, COUNT(DISTINCT CONCAT(DATE(viewed_at), visitor)) visitors FROM page_views WHERE $w GROUP BY path ORDER BY views DESC, path LIMIT 40", $p);
    foreach ($pages as &$pg) { $pg['label'] = page_label($pg['path']); } unset($pg);
    $countries = rows("SELECT country, COUNT(*) views, COUNT(DISTINCT CONCAT(DATE(viewed_at), visitor)) visitors FROM page_views WHERE $w GROUP BY country ORDER BY views DESC LIMIT 60", $p);
    $sources = rows("SELECT source, COUNT(*) views FROM page_views WHERE $w AND source <> 'internal' GROUP BY source ORDER BY views DESC", $p);
    $referrers = rows("SELECT ref_host, source, COUNT(*) views FROM page_views WHERE $w AND ref_host IS NOT NULL GROUP BY ref_host, source ORDER BY views DESC LIMIT 12", $p);
    $devices = rows("SELECT device, COUNT(*) views, COUNT(DISTINCT CONCAT(DATE(viewed_at), visitor)) visitors FROM page_views WHERE $w GROUP BY device ORDER BY views DESC", $p);
    $browsers = rows("SELECT browser, COUNT(*) views FROM page_views WHERE $w GROUP BY browser ORDER BY views DESC LIMIT 10", $p);
    $hours = array_fill(0, 24, 0);
    foreach (rows("SELECT HOUR(viewed_at) h, COUNT(*) v FROM page_views WHERE $w GROUP BY HOUR(viewed_at)", $p) as $r) $hours[(int)$r['h']] = (int)$r['v'];
    $known = $one("SELECT COUNT(*) FROM page_views WHERE $w AND country IS NOT NULL", $p);
    return ['from' => $from, 'to' => $to, 'views' => $views, 'visitors' => $visitors, 'daily' => $daily, 'pages' => $pages, 'countries' => $countries, 'sources' => $sources,
            'referrers' => $referrers, 'devices' => $devices, 'browsers' => $browsers, 'hours' => $hours, 'country_known' => $known,
            'online' => $one('SELECT COUNT(DISTINCT visitor) FROM page_views WHERE viewed_at >= (NOW() - INTERVAL 5 MINUTE)'),
            'today_views' => $one('SELECT COUNT(*) FROM page_views WHERE viewed_at >= CURDATE()'),
            'today_visitors' => $one('SELECT COUNT(DISTINCT visitor) FROM page_views WHERE viewed_at >= CURDATE()')];
}
function pct(int|float $n, int|float $total): string { return $total > 0 ? number_format($n / $total * 100, 1) . '%' : '0%'; }
