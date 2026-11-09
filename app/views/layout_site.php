<?php
$nextEv = row("SELECT slug FROM events WHERE status='published' AND COALESCE(end_at,start_at) >= NOW() ORDER BY start_at LIMIT 1");
$reserveUrl = $nextEv ? url('events/' . $nextEv['slug'] . '/reserve') : url('events');
$nav = [
    'Home' => ['', url()], 'About' => ['about', url('about')], 'Events' => ['events', url('events')],
    'Reservations' => ['reserve', $reserveUrl], 'Laugh with Owakabi' => ['laugh', url('laugh')],
    'Podium DJs' => ['djs', url('djs')], 'Advertise With Us' => ['advertise', url('advertise')],
];
$cur = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(BASE)), '/');
$active = fn($k) => ($k === '' ? $cur === '' : ($k === 'reserve' ? str_contains($cur, '/reserve') : str_starts_with($cur, $k) && !str_contains($cur, '/reserve'))) ? ' aria-current="page"' : '';
$seg = explode('/', $cur)[0];
$heroPhotos = ['about' => 10, 'events' => 44, 'booking' => 41, 'laugh' => 12, 'djs' => 41, 'news' => 22, 'advertise' => 42, 'contact' => 33, 'gallery' => 3];
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
<?php if (empty($noindex)): ?><link rel="canonical" href="<?= h(abs_url(trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(BASE)), '/'))) ?>"><?php endif; ?>
<meta property="og:title" content="<?= h($pageTitle) ?>">
<meta property="og:description" content="<?= h(excerpt($pageDesc, 160)) ?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Chill &amp; Bite MEATINGS">
<meta property="og:locale" content="en_UG">
<meta property="og:url" content="<?= h(abs_url($cur)) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= h($pageTitle) ?>">
<meta name="twitter:description" content="<?= h(excerpt($pageDesc, 160)) ?>">
<?php if (empty($noindex)): ?><meta name="robots" content="index,follow,max-image-preview:large,max-snippet:-1"><?php endif; ?>
<meta name="author" content="Owakabi &amp; The Geng">
<meta property="og:image" content="<?= h(!empty($og_image) ? (str_starts_with($og_image, 'img/') ? abs_url('assets/' . $og_image) : abs_url('uploads/' . $og_image)) : abs_from(photo(44))) ?>">
<?php $heroOverride = cti($seg . '.hero.photo'); // optional photo chosen in Admin > Pages ?>
<style>:root{--hero-photo:url('<?= h($heroOverride !== '' ? $heroOverride : photo($heroPhoto)) ?>')}</style>
<?php if (!empty($noindex)): ?><meta name="robots" content="noindex,nofollow"><?php endif; ?>
<?php if (empty($noindex)) echo seo_schema($seg, ($seg === 'events' && isset($e['slug'])) ? $e : null, $packages ?? [], $pageTitle); ?>
<?php if (setting('google_verification') !== ''): ?><meta name="google-site-verification" content="<?= h(setting('google_verification')) ?>">
<?php endif; ?><?php if (setting('bing_verification') !== ''): ?><meta name="msvalidate.01" content="<?= h(setting('bing_verification')) ?>">
<?php endif; ?><meta name="theme-color" content="#171717">
<link rel="icon" type="image/png" href="<?= h(brand_favicon()) ?>"><link rel="apple-touch-icon" href="<?= h(brand_favicon()) ?>">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_v('css/style.css') ?>">
</head>
<body>
<?= quick_edit_bar() ?>
<a class="skip" href="#main">Skip to content</a>
<?php if (setting('announcement_on', '1') === '1' && setting('announcement') !== ''): ?>
<?php $barEv = upcoming_events(1, 'chill-bite-meatings')[0] ?? null; [$barDate, $barLabel] = event_countdown($barEv); ?>
<div class="announce" role="note"><div class="announce-in">
  <p class="announce-text"><span class="announce-dot" aria-hidden="true"></span><?= h(setting('announcement')) ?></p>
  <p class="announce-date"><small>Next meeting</small><b><?= h($barDate->format('l, j F Y')) ?></b><span><?= h(($barLabel ? $barLabel . ' ' : '') . $barDate->format('g:i A')) ?> · Gulu City</span></p>
  <div class="announce-timer" id="barTimer" data-target="<?= h($barDate->format('c')) ?>" role="timer" aria-label="Time until the next event">
    <span><b data-u="d">--</b><i>Days</i></span><span><b data-u="h">--</b><i>Hours</i></span><span><b data-u="m">--</b><i>Mins</i></span><span><b data-u="s">--</b><i>Secs</i></span>
  </div>
  <a class="announce-cta" href="<?= url('events') ?>">Reserve →</a>
</div></div>
<?php endif; ?>
<header class="site-header">
  <div class="wrap header-in">
    <a class="brand" href="<?= url() ?>" aria-label="Chill &amp; Bite MEATINGS, home"><img class="brand-logo" src="<?= h(brand_logo()) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253"></a>
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
<?= paytabs_render($seg) ?>
<?php if ($cur !== '' && !in_array($seg, ['gallery', 'ticket'], true) && http_response_code() === 200): ?>
<?php photo_strip(strip_nums($stripSets[$seg] ?? ['crowd', 'people'], crc32($seg), 14), 'Moments from Chill & Bite', 'strip-dark'); ?>
<?php endif; ?>
</main>

<footer class="site-footer">
  <div class="wrap footer-grid">
    <div>
      <a class="brand" href="<?= url() ?>" aria-label="Chill &amp; Bite MEATINGS, home"><img class="brand-logo brand-logo-lg" src="<?= h(brand_logo()) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253"></a>
      <p class="muted"><?= h(setting('tagline')) ?></p>
      <p class="muted small">Hosted by MC Kash Owakabi.<br>Organized by Owakabi &amp; The Geng.</p>
    </div>
    <div>
      <h4>Explore</h4>
      <ul class="plain"><li><a href="<?= url('events') ?>">Events Calendar</a></li><li><a href="<?= h($reserveUrl) ?>">Book &amp; Reserve</a></li><li><a href="<?= url('track') ?>">Track your booking</a></li><li><a href="<?= url('laugh') ?>">Laugh with Owakabi</a></li><li><a href="<?= url('djs') ?>">Podium DJs</a></li><li><a href="<?= url('gallery') ?>">Gallery</a></li></ul>
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
  <?php if (setting('credit_on', '1') === '1'): $crP = setting('credit_prefix', 'Designed & maintained by'); $crN = setting('credit_name', 'Santos Jr'); $crO = setting('credit_org', 'BONO TECHNOLOGIES'); $crU = setting('credit_url');
        if ($crP !== '' || $crN !== '' || $crO !== ''): ?>
  <div class="wrap credit"><p><?php if ($crP !== ''): ?><?= h($crP) ?> <?php endif; ?><?php if ($crN !== ''): ?><strong><?= $crU !== '' && preg_match('#^https?://#i', $crU) ? '<a href="' . h($crU) . '" target="_blank" rel="noopener">' . h($crN) . '</a>' : h($crN) ?></strong><?php endif; ?><?php if ($crN !== '' && $crO !== ''): ?> <span class="credit-sep" aria-hidden="true">|</span> <?php endif; ?><?php if ($crO !== ''): ?><span class="credit-org"><?= h($crO) ?></span><?php endif; ?></p></div>
  <?php endif; endif; ?>
</footer>

<div id="cookie" class="cookie" hidden>
  <p>We use only essential cookies to keep forms secure. <a href="<?= url('privacy') ?>">Learn more</a></p>
  <button class="btn btn-primary btn-sm" type="button" id="cookie-ok">OK</button>
</div>
<?php if (setting('whatsapp')): ?><a class="wa-fab" target="_blank" rel="noopener" href="<?= h(wa_link(setting('whatsapp'), 'Hello, I would like to make some enquiries about Chill and Bite MEATINGS.')) ?>" aria-label="Chat with support on WhatsApp"><svg viewBox="0 0 32 32" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M16 3C8.8 3 3 8.8 3 16c0 2.3.6 4.5 1.7 6.4L3 29l6.8-1.8A13 13 0 0 0 16 29c7.2 0 13-5.8 13-13S23.2 3 16 3zm0 23.7c-2 0-3.900-.5-5.500-1.500l-.4-.2-4 1.100 1.100-3.900-.3-.4A10.700 10.700 0 1 1 16 26.700zm5.900-8c-.3-.2-1.900-.9-2.200-1-.3-.1-.5-.2-.7.200-.2.300-.8 1-1 1.200-.2.200-.4.200-.7.100-.3-.2-1.400-.5-2.600-1.600-1-.9-1.600-1.900-1.800-2.200-.2-.3 0-.5.100-.7l.5-.6c.1-.2.200-.3.300-.5.100-.2.100-.4 0-.6l-1-2.300c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.6.100-.9.400-.3.300-1.200 1.100-1.200 2.800s1.200 3.300 1.400 3.500c.2.200 2.400 3.700 5.800 5.100.8.400 1.500.6 2 .7.800.3 1.600.2 2.200.1.700-.1 1.900-.8 2.200-1.500.3-.8.300-1.400.2-1.500-.1-.2-.3-.3-.6-.4z"/></svg><span>Support</span></a><?php endif; ?>
<script>(function(){var a=document.querySelector('.wa-fab');if(!a)return;try{var n=localStorage.getItem('cb_name');if(n){var u=new URL(a.href);u.searchParams.set('text',u.searchParams.get('text')+' My name is '+n+'.');a.href=u.toString();}}catch(e){}})();</script>
<script src="<?= asset_v('js/app.js') ?>" defer></script>
</body>
</html>
