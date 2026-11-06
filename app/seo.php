<?php
declare(strict_types=1);

/* Search-engine helpers: structured data (JSON-LD) that helps Google show rich results for the brand, the host and each event. */

const SEO_BRAND = 'Chill & Bite MEATINGS';
const SEO_HOST = 'MC Kash Owakabi';

function seo_json(array $data): string
{
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>' . "\n";
}
function seo_same_as(): array
{
    $out = [];
    foreach (['social_facebook', 'social_instagram', 'social_youtube', 'social_tiktok', 'social_x'] as $k) {
        $u = trim((string)setting($k));
        if (preg_match('#^https?://#i', $u)) $out[] = $u;
    }
    return $out;
}
function seo_iso(?string $dt): ?string { return $dt ? date('Y-m-d\TH:i:s', strtotime($dt)) . '+03:00' : null; }   // East Africa Time

/** The place an event happens at. */
function seo_place(array $e): array
{
    return ['@type' => 'Place', 'name' => (string)($e['venue'] ?? setting('venue', 'Revelation Cottages')),
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Gulu', 'addressRegion' => 'Northern Region', 'addressCountry' => 'UG', 'streetAddress' => (string)($e['location'] ?? 'Gulu City, Uganda')]];
}
function seo_event(array $e, array $packages = []): array
{
    $img = !empty($e['poster']) ? abs_from(upload_url($e['poster'])) : abs_from(photo(44));
    $ev = ['@type' => 'Event', 'name' => $e['title'], 'description' => $e['short_desc'] ?: excerpt(strip_tags((string)$e['description']), 200),
        'startDate' => seo_iso($e['start_at']), 'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
        'eventStatus' => $e['status'] === 'cancelled' ? 'https://schema.org/EventCancelled' : 'https://schema.org/EventScheduled',
        'location' => seo_place($e), 'image' => [$img], 'url' => abs_url('events/' . $e['slug']),
        'organizer' => ['@type' => 'Organization', 'name' => 'Owakabi & The Geng', 'url' => abs_url()],
        'performer' => ['@type' => 'Person', 'name' => SEO_HOST]];
    if (!empty($e['end_at'])) $ev['endDate'] = seo_iso($e['end_at']);
    $prices = array_values(array_filter(array_map(fn($p) => (int)$p['price'], $packages), fn($p) => $p > 0));
    if ($prices) {
        $ev['offers'] = ['@type' => 'AggregateOffer', 'priceCurrency' => 'UGX', 'lowPrice' => min($prices), 'highPrice' => max($prices), 'offerCount' => count($prices),
            'availability' => 'https://schema.org/InStock', 'url' => abs_url('events/' . $e['slug'] . '/reserve')];
    }
    return $ev;
}
/** All structured data for the current page, as ready-to-print script tags. */
function seo_schema(string $seg, ?array $event, array $packages, string $title): string
{
    $home = abs_url();
    $logo = abs_from(brand_logo());
    $org = ['@type' => ['Organization', 'LocalBusiness'], '@id' => $home . '#org', 'name' => SEO_BRAND, 'alternateName' => ['Chill and Bite MEATINGS', 'Chill & Bite Gulu', 'Chill and Bite Meating'],
        'url' => $home, 'logo' => $logo, 'image' => $logo, 'description' => setting('tagline'),
        'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Gulu', 'addressCountry' => 'UG'],
        'areaServed' => 'Gulu City, Northern Uganda', 'founder' => ['@id' => $home . '#host']];
    if (setting('contact_phone')) $org['telephone'] = setting('contact_phone');
    if (setting('contact_email')) $org['email'] = setting('contact_email');
    if ($same = seo_same_as()) $org['sameAs'] = $same;
    $host = ['@type' => 'Person', '@id' => $home . '#host', 'name' => 'Kash Owakabi', 'alternateName' => ['MC Kash Owakabi', 'Owakabi', 'Owakabi Kash'],
        'jobTitle' => 'MC, comedian and event host', 'url' => abs_url('about'), 'image' => abs_from(photo(44)), 'worksFor' => ['@id' => $home . '#org'], 'sameAs' => $same ?: null];
    if (!$host['sameAs']) unset($host['sameAs']);
    $site = ['@type' => 'WebSite', '@id' => $home . '#site', 'url' => $home, 'name' => SEO_BRAND, 'alternateName' => ['Owakabi', 'Chill and Bite Meating'], 'publisher' => ['@id' => $home . '#org'], 'inLanguage' => 'en-UG'];
    $graph = [$org, $host, $site];

    if ($event) $graph[] = seo_event($event, $packages);
    elseif ($seg === '' ) {
        foreach (rows("SELECT * FROM events WHERE status='published' AND COALESCE(end_at,start_at) >= NOW() ORDER BY start_at LIMIT 3") as $e) $graph[] = seo_event($e, event_packages((int)$e['id']));
    }
    if ($seg !== '') {
        $crumbs = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $home]];
        $label = trim(explode('|', $title)[0]);
        $crumbs[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $label];
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => $crumbs];
    }
    return seo_json(['@context' => 'https://schema.org', '@graph' => $graph]);
}
