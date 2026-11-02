<?php
$nextEv = row("SELECT slug FROM events WHERE status='published' AND COALESCE(end_at,start_at) >= NOW() ORDER BY start_at LIMIT 1");
$reserveUrl = $nextEv ? url('events/' . $nextEv['slug'] . '/reserve') : url('events');
$nav = [
    'Home' => ['', url()], 'About' => ['about', url('about')], 'Events' => ['events', url('events')],
    'Reservations' => ['reserve', $reserveUrl], 'Laugh with Owakabi' => ['laugh', url('laugh')],
    'Gulu Chronicles' => ['news', url('news')], 'Advertise With Us' => ['advertise', url('advertise')],
];
$cur = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(BASE)), '/');
$active = fn($k) => ($k === '' ? $cur === '' : ($k === 'reserve' ? str_contains($cur, '/reserve') : str_starts_with($cur, $k) && !str_contains($cur, '/reserve'))) ? ' aria-current="page"' : '';
$seg = explode('/', $cur)[0];
$heroPhotos = ['about' => 10, 'events' => 44, 'booking' => 41, 'laugh' => 12, 'news' => 22, 'advertise' => 42, 'contact' => 33, 'gallery' => 3];
$stripSets = ['about' => ['people', 'social', 'crowd'], 'events' => ['stage', 'crowd'], 'booking' => ['crowd', 'stage'], 'laugh' => ['people', 'stage'],
    'news' => ['social', 'people'], 'advertise' => ['stage', 'crowd'], 'contact' => ['food', 'social', 'people']];
$heroPhoto = $heroPhotos[$seg] ?? 6;
$pageTitle = $title ?? setting('site_name', 'Chill & Bite MEATINGS');
$pageDesc = $desc ?? setting('tagline');
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($pageTitle) ?></title>
<meta name="description" content="<?= h(excerpt($pageDesc, 160)) ?>">
<meta property="og:title" content="<?= h($pageTitle) ?>">
<meta property="og:description" content="<?= h(excerpt($pageDesc, 160)) ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="<?= h(!empty($og_image) ? (str_starts_with($og_image, 'img/') ? abs_url('assets/' . $og_image) : abs_url('uploads/' . $og_image)) : abs_url('assets/img/photos/photo-44.jpg')) ?>">
<?php $heroOverride = cti($seg . '.hero.photo'); // optional photo chosen in Admin > Pages ?>
<style>:root{--hero-photo:url('<?= h($heroOverride !== '' ? $heroOverride : photo($heroPhoto)) ?>')}</style>
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<meta name="theme-color" content="#171717">
<link rel="icon" type="image/png" href="<?= h(asset('img/favicon.png')) ?>"><link rel="apple-touch-icon" href="<?= h(asset('img/favicon.png')) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_v('css/style.css') ?>">
</head>
<body>
<a class="skip" href="#main">Skip to content</a>
<?php if (setting('announcement_on', '1') === '1' && setting('announcement') !== ''): ?>
<?php $barEv = upcoming_events(1, 'chill-bite-meatings')[0] ?? null; $barDate = $barEv ? new DateTimeImmutable($barEv['start_at']) : next_first_sunday(); ?>
<div class="announce" role="note"><div class="announce-in">
  <p class="announce-text"><span class="announce-dot" aria-hidden="true"></span><?= h(setting('announcement')) ?></p>
  <p class="announce-date"><small>Next meeting</small><b><?= h($barDate->format('l, j F Y')) ?></b><span><?= h($barDate->format('g:i A')) ?> · Gulu City</span></p>
  <div class="announce-timer" id="barTimer" data-target="<?= h($barDate->format('c')) ?>" role="timer" aria-label="Time until the next event">
    <span><b data-u="d">--</b><i>Days</i></span><span><b data-u="h">--</b><i>Hours</i></span><span><b data-u="m">--</b><i>Mins</i></span><span><b data-u="s">--</b><i>Secs</i></span>
  </div>
  <a class="announce-cta" href="<?= url('events') ?>">Reserve →</a>
</div></div>
<?php endif; ?>
<header class="site-header">
  <div class="wrap header-in">
    <a class="brand" href="<?= url() ?>" aria-label="Chill &amp; Bite MEATINGS, home"><img class="brand-logo" src="<?= h(asset('img/logo.png')) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253"></a>
    <button class="nav-toggle" aria-expanded="false" aria-controls="nav" aria-label="Menu"><span></span><span></span><span></span></button>
    <nav id="nav" class="nav" aria-label="Main">
      <ul>
        <?php foreach ($nav as $label => [$k, $href]): ?><li><a href="<?= h($href) ?>"<?= $active($k) ?>><?= h($label) ?></a></li><?php endforeach; ?>
      </ul>
      <div class="nav-cta">
        <a class="btn btn-primary btn-sm" href="<?= h($reserveUrl) ?>">Book your place</a>
        <a class="btn btn-outline btn-sm" href="<?= url('events') ?>">Upcoming events</a>
      </div>
    </nav>
  </div>
</header>

<main id="main">
<?php foreach (flash() as [$t, $m]): ?><div class="wrap"><div class="alert alert-<?= $t === 'err' ? 'err' : 'ok' ?>" role="<?= $t === 'err' ? 'alert' : 'status' ?>"><?= h($m) ?></div></div><?php endforeach; ?>
<?= $content ?>
<?php if ($cur !== '' && !in_array($seg, ['gallery', 'ticket'], true) && http_response_code() === 200): ?>
<?php photo_strip(strip_nums($stripSets[$seg] ?? ['crowd', 'people'], crc32($seg), 14), 'Moments from Chill & Bite', 'strip-dark'); ?>
<?php endif; ?>
</main>

<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <a class="brand" href="<?= url() ?>" aria-label="Chill &amp; Bite MEATINGS, home"><img class="brand-logo brand-logo-lg" src="<?= h(asset('img/logo.png')) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253"></a>
      <p class="muted"><?= h(setting('tagline')) ?></p>
      <p class="muted small">Hosted by MC Kash Owakabi.<br>Organized by Owakabi &amp; The Geng.</p>
    </div>
    <div>
      <h4>Explore</h4>
      <ul class="plain"><li><a href="<?= url('events') ?>">Events Calendar</a></li><li><a href="<?= h($reserveUrl) ?>">Book &amp; Reserve</a></li><li><a href="<?= url('track') ?>">Track your booking</a></li><li><a href="<?= url('laugh') ?>">Laugh with Owakabi</a></li><li><a href="<?= url('news') ?>">The Gulu Chronicles</a></li><li><a href="<?= url('gallery') ?>">Gallery</a></li></ul>
    </div>
    <div>
      <h4>Company</h4>
      <ul class="plain"><li><a href="<?= url('about') ?>">About Us</a></li><li><a href="<?= url('advertise') ?>">Brands &amp; Advertising</a></li><li><a href="<?= url('partner') ?>">Become a Partner</a></li><li><a href="<?= url('contact') ?>">Contact Us</a></li><li><a href="<?= url('privacy') ?>">Privacy &amp; Cookies</a></li></ul>
    </div>
    <div>
      <h4>Find us</h4>
      <p class="muted"><?= h(setting('venue')) ?>, <?= h(setting('location')) ?><br>Every first Sunday · 12:00 PM</p>
      <?php if (setting('contact_phone')): ?><p><a href="tel:<?= h(preg_replace('/\s+/', '', setting('contact_phone'))) ?>"><?= h(setting('contact_phone')) ?></a></p><?php endif; ?>
      <?php if (setting('whatsapp_group')): ?><p><a class="btn btn-primary btn-sm" href="<?= h(setting('whatsapp_group')) ?>" target="_blank" rel="noopener">Join our WhatsApp group</a></p><?php endif; ?>
      <p class="socials"><?php foreach (social_links() as $n => $u): ?><a href="<?= h($u) ?>" target="_blank" rel="noopener"><?= h($n) ?></a> <?php endforeach; ?></p>
    </div>
  </div>
  <div class="wrap footer-bottom"><span>© <?= date('Y') ?> Chill &amp; Bite MEATINGS · Owakabi &amp; The Geng</span><a href="<?= url('admin') ?>">Staff login</a></div>
  <div class="wrap credit"><p>Designed &amp; maintained by <strong>Santos Junior</strong> <span class="credit-sep" aria-hidden="true">|</span> <span class="credit-org">LAF Academy</span></p></div>
</footer>

<div id="cookie" class="cookie" hidden>
  <p>We use only essential cookies to keep forms secure. <a href="<?= url('privacy') ?>">Learn more</a></p>
  <button class="btn btn-primary btn-sm" type="button" id="cookie-ok">OK</button>
</div>
<script src="<?= asset_v('js/app.js') ?>" defer></script>
</body>
</html>
