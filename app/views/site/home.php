<?php $reserve = $next ? url('events/' . $next['slug'] . '/reserve') : url('events'); ?>
<section class="hero">
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
      <p class="countdown-date" id="countdown" data-target="<?= h($next_date->format('c')) ?>"><?= $next_date->format('l, j F Y') ?></p>
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

<section class="section">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow">Upcoming</span><h2>Chill &amp; Bite MEATINGS: The Monthly Experience</h2></div>
    <?php if ($next): ?>
      <div class="featured-event">
        <div class="fe-poster"><?php media($next['poster'], $next['title'], 'poster-img', fmt_date($next['start_at'], 'M j')); ?></div>
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

<section class="section section-cream">
  <div class="wrap host">
    <div class="host-photo"><div class="ph portrait" role="img" aria-label="Portrait of MC Kash Owakabi (photo coming soon)"><span>MC<br>KASH</span></div></div>
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
    <?php if ($sponsors): ?><ul class="sponsor-row"><?php foreach ($sponsors as $s): ?><li><?php if ($s['logo']): ?><img src="<?= h(upload_url($s['logo'])) ?>" alt="<?= h($s['name']) ?>" loading="lazy"><?php else: ?><span><?= h($s['name']) ?></span><?php endif; ?></li><?php endforeach; ?></ul><?php endif; ?>
    <a class="btn btn-primary btn-lg" href="<?= url('advertise#proposal') ?>">Request an advertising proposal</a>
  </div>
</section>

<?php if ($gallery): ?>
<section class="section">
  <div class="wrap">
    <div class="section-head"><span class="eyebrow">Gallery</span><h2>The Atmosphere</h2></div>
    <div class="gallery-grid"><?php foreach ($gallery as $g): ?><a href="<?= url('gallery') ?>"><img src="<?= h(upload_url($g['file'])) ?>" alt="<?= h($g['caption'] ?: 'Chill & Bite event photo') ?>" loading="lazy"></a><?php endforeach; ?></div>
  </div>
</section>
<?php endif; ?>

<section class="cta-final">
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
