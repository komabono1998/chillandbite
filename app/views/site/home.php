<?php $reserve = $next ? url('events/' . $next['slug'] . '/reserve') : url('events'); ?>
<section class="hero">
  <div class="hero-slides" aria-hidden="true"><?php foreach ([44, 3, 6, 41, 18] as $i => $n): ?><div style="background-image:url('<?= h(photo($n)) ?>');animation-delay:<?= $i * 6 - 2 ?>s"></div><?php endforeach; ?></div>
  <div class="wrap hero-in">
    <div class="hero-copy">
      <span class="eyebrow gold">Gulu City · Every first Sunday</span>
      <h1>Good Food. Great People. <span>Unforgettable Connections.</span></h1>
      <p class="lead">Welcome to Chill &amp; Bite MEATINGS, Gulu City's monthly lifestyle and corporate networking experience. Join MC Kash Owakabi and Owakabi &amp; The Geng for an exciting blend of food, music, entertainment, outdoor games, and meaningful connections.</p>
      <div class="btn-row">
        <a class="btn btn-primary btn-lg" href="<?= h($reserve) ?>">Reserve your spot</a>
        <a class="btn btn-outline btn-lg" href="<?= url('events') ?>">Explore upcoming events</a>
      </div>
    </div>
    <aside class="info-panel" aria-label="Event information">
      <p class="eyebrow gold">Next experience</p>
      <p class="countdown-date"><?= $next_date->format('l, j F Y') ?> · <?= $next_date->format('g:i A') ?></p>
      <div class="countdown" id="countdown" data-target="<?= h($next_date->format('c')) ?>" role="timer" aria-label="Time until the next event">
        <div><b data-u="d">00</b><span>Days</span></div><div><b data-u="h">00</b><span>Hours</span></div><div><b data-u="m">00</b><span>Mins</span></div><div><b data-u="s">00</b><span>Secs</span></div>
      </div>
      <p class="live-note" id="live-note" hidden>Happening now! Come and join us.</p>
      <dl>
        <dt>Event</dt><dd>Chill &amp; Bite MEATINGS</dd>
        <dt>Venue</dt><dd>Revelation Cottages, Gulu City</dd>
        <dt>Schedule</dt><dd>Every first Sunday of the month</dd>
        <dt>Starting time</dt><dd>12:00 PM</dd>
        <dt>Host</dt><dd>MC Kash Owakabi</dd>
      </dl>
    </aside>
  </div>
</section>

<?php photo_strip(strip_nums(['crowd', 'people', 'stage'], 11, 16), 'Moments from our events', 'strip-hero'); ?>

<section class="section">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow">Upcoming</span><h2>Chill &amp; Bite MEATINGS: The Monthly Experience</h2></div>
    <?php if ($next): ?>
      <div class="featured-event">
        <div class="fe-poster"><?php media($next['poster'], $next['title'], 'poster-img', fmt_date($next['start_at'], 'M j'), photo(10)); ?></div>
        <div class="fe-body">
          <span class="eyebrow gold"><?= h($next['category'] ?? 'Next event') ?></span>
          <h3><?= h($next['title']) ?></h3>
          <p class="meta"><?= fmt_date($next['start_at'], 'l, j F Y') ?> · <?= fmt_time($next['start_at']) ?> · <?= h($next['venue']) ?>, <?= h($next['location']) ?></p>
          <p><?= h($next['short_desc']) ?></p>
          <p class="small muted">Reservation information: individual, couple, group and corporate bookings available.</p>
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
    <div class="section-head center"><span class="eyebrow gold">Good food</span><h2>Fire. Smoke. Flavour.</h2><p class="lead narrow">Sizzling skewers, slow-roasted chicken, sticky ribs and wings, served all afternoon.</p></div>
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
    <div class="host-photo"><img class="host-img" src="<?= h(asset('img/host-mc-kash-owakabi.jpg')) ?>" alt="MC Kash Owakabi, host of Chill &amp; Bite MEATINGS" width="592" height="800" loading="lazy" decoding="async"><span class="host-tag">Your host · MC Kash Owakabi</span></div>
    <div>
      <span class="eyebrow">Meet the host</span>
      <h2>The Man Behind the Vibes: MC Kash Owakabi</h2>
      <p><?= h(setting('host_bio')) ?></p>
      <p>As the host of Chill &amp; Bite MEATINGS and the personality behind Laugh with Owakabi, he connects people through entertainment, conversation, and shared experiences.</p>
      <ul class="ticks"><li>Charismatic host and master of ceremonies</li><li>Stand-up comedy and live audience engagement</li><li>Founder of Owakabi &amp; The Geng</li></ul>
      <div class="btn-row"><a class="btn btn-primary" href="<?= url('laugh') ?>">Explore Laugh with Owakabi</a>
        <?php foreach (social_links() as $n => $u): ?><a class="btn btn-ghost btn-sm" href="<?= h($u) ?>" target="_blank" rel="noopener"><?= h($n) ?></a><?php endforeach; ?></div>
    </div>
  </div>
</section>

<section class="section section-dark">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow gold">Comedy</span><h2>Laugh with Owakabi: Life Is Better with a Little Humour!</h2></div>
    <div class="grid grid-2">
      <div><?php if ($video): video_embed($video, true); else: ?><div class="ph video-ph"><span>Video coming soon</span></div><?php endif; ?></div>
      <div>
        <h3>Upcoming comedy shows</h3>
        <?php if ($comedy_events): foreach ($comedy_events as $e): ?>
          <p><a href="<?= url('events/' . h($e['slug'])) ?>"><strong><?= h($e['title']) ?></strong></a><br><span class="muted"><?= fmt_date($e['start_at']) ?> · <?= h($e['venue']) ?></span></p>
        <?php endforeach; else: ?><p class="muted">New show dates are announced soon.</p><?php endif; ?>
        <p>Guest comedians, behind-the-scenes clips and show announcements. Want MC Kash Owakabi at your event?</p>
        <div class="btn-row"><a class="btn btn-primary" href="<?= url('laugh#book') ?>">Book a performance</a><a class="btn btn-outline" href="<?= url('laugh') ?>">More comedy</a></div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow">News &amp; Gossips</span><h2>Northern Buzz: What's Happening Up North?</h2></div>
    <?php if ($articles): ?><div class="grid grid-3"><?php foreach ($articles as $a) article_card($a); ?></div>
    <?php else: ?><p class="muted">Fresh stories are on the way.</p><?php endif; ?>
    <p class="center mt"><a class="btn btn-outline-dark" href="<?= url('news') ?>">All stories</a></p>
  </div>
</section>

<section class="section section-dark">
  <div class="wrap center">
    <span class="eyebrow gold">Brand partners</span>
    <h2>Your Brand Deserves to Be Seen.</h2>
    <p class="lead narrow">Connect your business with professionals, entrepreneurs, creatives, and entertainment lovers through Chill &amp; Bite MEATINGS and our digital media platforms.</p>
    <?php if ($sponsors): ?><ul class="sponsor-row"><?php foreach ($sponsors as $s) sponsor_item($s); ?></ul><?php endif; ?>
    <a class="btn btn-primary btn-lg" href="<?= url('advertise#proposal') ?>">Request an advertising proposal</a> <a class="btn btn-outline btn-lg" href="<?= url('partner') ?>">Become a partner</a>
  </div>
</section>

<?php if ($gallery): ?>
<section class="section">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow">Gallery</span><h2>The Atmosphere</h2></div>
    <div class="gallery-grid"><?php foreach ($gallery as $g): ?><a href="<?= h(upload_url($g['file'])) ?>" data-lightbox><img src="<?= h(upload_url($g['file'])) ?>" alt="<?= h($g['caption'] ?: 'Chill & Bite event photo') ?>" loading="lazy" decoding="async"></a><?php endforeach; ?></div>
    <p class="center mt"><a class="btn btn-outline-dark" href="<?= url('gallery') ?>">See the full gallery</a></p>
  </div>
</section>
<?php endif; ?>

<?php photo_strip(strip_nums(['people', 'social', 'crowd'], 29, 16), 'More moments', 'strip-light'); ?>

<section class="cta-final" style="--cta-photo:url('<?= h(photo(40)) ?>')">
  <div class="wrap center">
    <h2>Your Next Great Connection Starts Here!</h2>
    <p class="lead">One Sunday. One Location. Endless Connections.</p>
    <p>Join us at Revelation Cottages, Gulu City, for the next Chill &amp; Bite MEATINGS experience.</p>
    <div class="btn-row" style="justify-content:center">
      <a class="btn btn-dark btn-lg" href="<?= h($reserve) ?>">Book your place today</a>
      <?php if (setting('whatsapp_group')): ?><a class="btn btn-outline-dark btn-lg" style="border-color:#fff;color:#fff" href="<?= h(setting('whatsapp_group')) ?>" target="_blank" rel="noopener">Join our WhatsApp group</a><?php endif; ?>
    </div>
  </div>
</section>
