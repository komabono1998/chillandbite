<?php $reserve = $next ? url('events/' . $next['slug'] . '/reserve') : url('events'); ?>
<section class="hero">
  <div class="hero-slides" aria-hidden="true"><?php foreach ([44, 3, 6, 41, 18] as $i => $n): ?><div style="background-image:url('<?= h(photo($n)) ?>');animation-delay:<?= $i * 6 - 2 ?>s"></div><?php endforeach; ?></div>
  <div class="wrap hero-in">
    <div class="hero-copy">
      <span class="eyebrow gold"><?= cth('home.hero.eyebrow') ?></span>
      <h1><?= cth('home.hero.headline') ?> <span><?= cth('home.hero.accent') ?></span></h1>
      <p class="lead"><?= cth('home.hero.intro') ?></p>
      <div class="btn-row">
        <a class="btn btn-primary btn-lg" href="<?= h($reserve) ?>"><?= cth('home.hero.btn_reserve') ?></a>
        <a class="btn btn-outline btn-lg" href="<?= url('events') ?>"><?= cth('home.hero.btn_events') ?></a>
      </div>
    </div>
    <aside class="info-panel" aria-label="Event information">
      <p class="eyebrow gold"><?= cth('home.panel.label') ?></p>
      <p class="countdown-date"><?= $next_date->format('l, j F Y') ?> · <?= $next_date->format('g:i A') ?></p>
      <div class="countdown" id="countdown" data-target="<?= h($next_date->format('c')) ?>" role="timer" aria-label="Time until the next event">
        <div><b data-u="d">00</b><span>Days</span></div><div><b data-u="h">00</b><span>Hours</span></div><div><b data-u="m">00</b><span>Mins</span></div><div><b data-u="s">00</b><span>Secs</span></div>
      </div>
      <p class="live-note" id="live-note" hidden>Happening now! Come and join us.</p>
      <dl>
        <dt>Event</dt><dd><?= cth('home.panel.event') ?></dd>
        <dt>Venue</dt><dd><?= cth('home.panel.venue') ?></dd>
        <dt>Schedule</dt><dd><?= cth('home.panel.schedule') ?></dd>
        <dt>Starting time</dt><dd><?= cth('home.panel.time') ?></dd>
        <dt>Host</dt><dd><?= cth('home.panel.host') ?></dd>
      </dl>
    </aside>
  </div>
</section>

<?php photo_strip(strip_nums(['crowd', 'people', 'stage'], 11, 16), 'Moments from our events', 'strip-hero'); ?>

<section class="section">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow"><?= cth('home.events.eyebrow') ?></span><h2><?= cth('home.events.heading') ?></h2></div>
    <?php if ($next): ?>
      <div class="featured-event">
        <div class="fe-poster"><?php media($next['poster'], $next['title'], 'poster-img', fmt_date($next['start_at'], 'M j'), photo(10)); ?></div>
        <div class="fe-body">
          <span class="eyebrow gold"><?= h($next['category'] ?? 'Next event') ?></span>
          <h3><?= h($next['title']) ?></h3>
          <p class="meta"><?= fmt_date($next['start_at'], 'l, j F Y') ?> · <?= fmt_time($next['start_at']) ?> · <?= h($next['venue']) ?>, <?= h($next['location']) ?></p>
          <p><?= h($next['short_desc']) ?></p>
          <p class="small muted"><?= cth('home.events.note') ?></p>
          <div class="btn-row">
            <a class="btn btn-outline" href="<?= url('events/' . h($next['slug'])) ?>">View event</a>
            <a class="btn btn-primary" href="<?= h($reserve) ?>">Reserve now</a>
          </div>
        </div>
      </div>
      <?php if ($others): ?><div class="grid grid-3 mt"><?php foreach ($others as $e) event_card($e, true); ?></div><?php endif; ?>
    <?php else: ?>
      <p class="muted">No event is scheduled yet. The next Chill &amp; Bite is expected on <strong><?= $next_date->format('l, j F Y') ?></strong>. Check back soon!</p>
    <?php endif; ?>
  </div>
</section>

<section class="section section-dark taste">
  <div class="wrap">
    <div class="section-head center"><span class="eyebrow gold"><?= cth('home.food.eyebrow') ?></span><h2><?= cth('home.food.heading') ?></h2><p class="lead narrow"><?= cth('home.food.intro') ?></p></div>
    <div class="mosaic" data-stagger>
      <?php foreach ([
          ['a', 2,  'Skewers',          'Charcoal-grilled, served hot'],
          ['b', 23, 'Slow-roasted chicken', 'Turned over the fire'],
          ['c', 25, 'Sticky ribs',      'Sweet, smoky glaze'],
          ['e', 26, 'BBQ ribs',         'Fall-off-the-bone tender'],
          ['d', 30, 'Glazed wings',     'Crispy outside, juicy inside'],
      ] as $i => [$area, $n, $name, $sub]): ?>
        <a class="mosaic-item m-<?= $area ?>" style="--i:<?= $i ?>" href="<?= h(photo($n)) ?>" data-lightbox aria-label="<?= h($name) ?>: view larger">
          <img src="<?= h(photo($n)) ?>" alt="<?= h($name) ?> at Chill &amp; Bite MEATINGS" loading="lazy" decoding="async">
          <span class="m-cap"><b><?= h($name) ?></b><small><?= h($sub) ?></small></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<section class="section section-cream">
  <div class="wrap host">
    <div class="host-photo"><img class="host-img" src="<?= h(cti('home.host.photo')) ?>" alt="MC Kash Owakabi, host of Chill &amp; Bite MEATINGS" width="592" height="800" loading="lazy" decoding="async"><span class="host-tag"><?= cth('home.host.tag') ?></span></div>
    <div>
      <span class="eyebrow"><?= cth('home.host.eyebrow') ?></span>
      <h2><?= cth('home.host.heading') ?></h2>
      <p><?= cth('home.host.bio') ?></p>
      <p><?= cth('home.host.bio2') ?></p>
      <ul class="ticks"><?php foreach (ctl('home.host.points') as $pt): ?><li><?= h($pt) ?></li><?php endforeach; ?></ul>
      <div class="btn-row"><a class="btn btn-primary" href="<?= url('laugh') ?>"><?= cth('home.host.button') ?></a>
        <?php foreach (social_links() as $n => $u): ?><a class="btn btn-ghost btn-sm" href="<?= h($u) ?>" target="_blank" rel="noopener"><?= h($n) ?></a><?php endforeach; ?></div>
    </div>
  </div>
</section>

<section class="section section-dark">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow gold"><?= cth('home.comedy.eyebrow') ?></span><h2><?= cth('home.comedy.heading') ?></h2></div>
    <div class="grid grid-2">
      <div><?php if ($video): video_embed($video, true); else: ?><div class="ph video-ph"><span>Video coming soon</span></div><?php endif; ?></div>
      <div>
        <h3><?= cth('home.comedy.shows_title') ?></h3>
        <?php if ($comedy_events): foreach ($comedy_events as $e): ?>
          <p><a href="<?= url('events/' . h($e['slug'])) ?>"><strong><?= h($e['title']) ?></strong></a><br><span class="muted"><?= fmt_date($e['start_at']) ?> · <?= h($e['venue']) ?></span></p>
        <?php endforeach; else: ?><p class="muted">New show dates are announced soon.</p><?php endif; ?>
        <p><?= cth('home.comedy.blurb') ?></p>
        <div class="btn-row"><a class="btn btn-primary" href="<?= url('laugh#book') ?>"><?= cth('home.comedy.btn_book') ?></a><a class="btn btn-outline" href="<?= url('laugh') ?>"><?= cth('home.comedy.btn_more') ?></a></div>
      </div>
    </div>
  </div>
</section>

<section class="section section-dark">
  <div class="wrap center">
    <span class="eyebrow gold"><?= cth('home.partners.eyebrow') ?></span>
    <h2><?= cth('home.partners.heading') ?></h2>
    <p class="lead narrow"><?= cth('home.partners.intro') ?></p>
    <?php if ($sponsors): ?><ul class="sponsor-row"><?php foreach ($sponsors as $s) sponsor_item($s); ?></ul><?php endif; ?>
    <a class="btn btn-primary btn-lg" href="<?= url('advertise#proposal') ?>"><?= cth('home.partners.btn_proposal') ?></a> <a class="btn btn-outline btn-lg" href="<?= url('partner') ?>"><?= cth('home.partners.btn_partner') ?></a>
  </div>
</section>

<?php if ($gallery): ?>
<section class="section wall-section">
  <div class="wrap wall-head">
    <div><span class="eyebrow"><?= cth('home.gallery.eyebrow') ?></span><h2><?= cth('home.gallery.heading') ?></h2><p class="lead"><?= cth('home.gallery.intro') ?></p></div>
    <a class="btn btn-outline" href="<?= url('gallery') ?>"><?= cth('home.gallery.button') ?></a>
  </div>
  <?php photo_wall($gallery, 3); ?>
  <p class="center small muted wall-note">Hover to pause · tap any photo to enlarge · <?= (int)$gallery_total ?> photos in our gallery</p>
</section>
<?php endif; ?>


<section class="cta-final" style="--cta-photo:url('<?= h(cti('home.cta.photo')) ?>')">
  <div class="wrap center">
    <div class="cta-card">
      <p class="cta-date"><span aria-hidden="true">📅</span> <?= $next_date->format('l, j F Y') ?> · <?= $next_date->format('g:i A') ?></p>
      <h2><?= cth('home.cta.heading') ?></h2>
      <p class="lead"><?= cth('home.cta.tagline') ?></p>
      <p class="cta-text"><?= cth('home.cta.text') ?></p>
      <div class="btn-row" style="justify-content:center">
        <a class="btn btn-primary btn-lg" href="<?= h($reserve) ?>"><?= cth('home.cta.button') ?></a>
        <?php if (setting('whatsapp_group')): ?><a class="btn btn-outline btn-lg" href="<?= h(setting('whatsapp_group')) ?>" target="_blank" rel="noopener">Join our WhatsApp group</a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
