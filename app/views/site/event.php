<?php $cancelled = $e['status'] === 'cancelled'; ?>
<section class="page-hero"><div class="wrap">
  <span class="eyebrow gold"><?= h($e['category'] ?? 'Event') ?></span>
  <h1><?= h($e['title']) ?></h1>
  <p class="lead"><?= fmt_date($e['start_at'], 'l, j F Y') ?> · <?= fmt_time($e['start_at']) ?><?= $e['end_at'] ? ' – ' . fmt_time($e['end_at']) : '' ?></p>
  <?php if ($cancelled): ?><p><span class="badge badge-err">This event has been cancelled</span></p><?php endif; ?>
</div></section>
<section class="section section-cream"><div class="wrap event-layout">
  <div>
    <div class="event-hero-img"><?php media($e['poster'], $e['title'], 'poster-wide', date('j M', strtotime($e['start_at'])), photo_from('crowd', (int)$e['id'])); ?></div>
<?php
    $isComedy = str_contains(strtolower($e['category'] ?? ''), 'comedy');
    $reserveHref = url('events/' . $e['slug'] . '/reserve');
    $expect = $isComedy ? [
        ['🎤', 'Live stand-up', 'MC Kash Owakabi and guest comedians on stage.'],
        ['😂', 'Big laughs', 'An evening where life is better with a little humour.'],
        ['🎶', 'Music & vibes', 'Great sounds before and after the show.'],
        ['🍢', 'Food & drinks', 'Bites and drinks available at the venue.'],
        ['🤝', 'Meet people', 'Good company and new friends in the crowd.'],
        ['📸', 'Photo moments', 'Capture the night with the Chill & Bite crew.'],
    ] : [
        ['🔥', 'Food from the grill', 'Skewers, roast chicken, ribs and wings, served all afternoon.'],
        ['🎶', 'Live music & DJs', 'Great sounds from midday to evening.'],
        ['🤝', 'Real networking', 'Meet decision-makers, founders, creatives and customers.'],
        ['🎤', 'MC Kash Owakabi', 'A charismatic host keeping the energy up all day.'],
        ['🎲', 'Outdoor games', 'Relaxed games and activities that break the ice.'],
        ['📸', 'Photo moments', 'Branded backdrops and a lively, photo-ready crowd.'],
    ];
    ?>
    <div class="about-event">
      <span class="eyebrow">About this event</span>
      <h2><?= $isComedy ? 'An unforgettable night of laughter' : 'Where Gulu connects, shares and grows' ?></h2>
      <div class="about-lead"><?= paragraphs($e['description']) ?></div>
      <?php if ($e['video_url'] && ($id = youtube_id($e['video_url']))): video_embed(['url' => $e['video_url'], 'title' => 'Event video']); endif; ?>

      <ul class="glance" aria-label="At a glance">
        <li><b><?= fmt_date($e['start_at'], 'D j M') ?></b><span>Date</span></li>
        <li><b><?= fmt_time($e['start_at']) ?></b><span>Starts</span></li>
        <li><b><?= h($e['venue']) ?></b><span>Venue</span></li>
        <li><b><?= $e['capacity'] ? (int)$e['capacity'] . ' guests' : 'Open' ?></b><span>Capacity</span></li>
      </ul>

      <h3 class="about-h">What to expect</h3>
      <div class="expect-grid">
        <?php foreach ($expect as [$ic, $t, $d]): ?><div class="expect-card"><span class="ic" aria-hidden="true"><?= $ic ?></span><h4><?= h($t) ?></h4><p><?= h($d) ?></p></div><?php endforeach; ?>
      </div>

      <?php if (!$isComedy): ?>
      <div class="nmm">
        <span class="eyebrow gold">Network. Market. Grow.</span>
        <h3>Companies and individuals meet, network and showcase what they do</h3>
        <p>Chill &amp; Bite MEATINGS is more than a party. It is a place where businesses and individuals build relationships, promote their events and market their products and services to a lively, engaged crowd.</p>
        <div class="nmm-cols">
          <div><h4>Network</h4><p>Connect with professionals, entrepreneurs, creatives and potential partners over great food.</p></div>
          <div><h4>Market your events</h4><p>Tell guests about your launches, shows, parties and activations while the whole community is in one place.</p></div>
          <div><h4>Promote your services</h4><p>Show your products and services face to face, hand out offers and collect new customers.</p></div>
        </div>
        <div class="btn-row">
          <?php if ($open): ?><a class="btn btn-primary" href="<?= h($reserveHref) ?>">Reserve your place</a><?php endif; ?>
          <a class="btn btn-outline" href="<?= url('advertise#proposal') ?>">Showcase your brand</a>
        </div>
      </div>
      <?php endif; ?>

      <h3 class="about-h">Who should come</h3>
      <ul class="who">
        <?php foreach (['Business owners & founders', 'Companies & corporate teams', 'Entrepreneurs & freelancers', 'Event organizers & promoters', 'Creatives & artists', 'Friends, couples & food lovers'] as $w): ?><li><?= h($w) ?></li><?php endforeach; ?>
      </ul>
    </div>
    <?php if ($sponsors): ?><h3>Featured partners</h3><ul class="sponsor-row dark-on-light"><?php foreach ($sponsors as $s) sponsor_item($s); ?></ul><?php endif; ?>
  </div>
  <aside class="side-card">
    <h3>Event details</h3>
    <dl>
      <dt>When</dt><dd><?= fmt_date($e['start_at'], 'l, j F Y') ?><br><?= fmt_time($e['start_at']) ?></dd>
      <dt>Where</dt><dd><?= h($e['venue']) ?><br><?= h($e['location']) ?></dd>
      <?php if ($remaining !== null): ?><dt>Availability</dt><dd><?= $remaining > 0 ? $remaining . ' places left' : 'Fully booked' ?></dd><?php endif; ?>
      <?php if ($options): ?><dt>Reservation types</dt><dd><?php foreach ($options as $o): ?><?= h(RES_TYPES[$o['type']]) ?> — <?= $o['price_per_guest'] > 0 ? money($o['price_per_guest']) . '/guest' : 'Free' ?><br><?php endforeach; ?></dd><?php endif; ?>
    </dl>
    <?php if ($open): ?><a class="btn btn-primary btn-block" href="<?= url('events/' . h($e['slug']) . '/reserve') ?>">Reserve now</a>
    <?php elseif (!$cancelled): ?><p class="alert alert-err">Reservations are currently closed for this event.</p><?php endif; ?>
    <a class="btn btn-outline-dark btn-block" target="_blank" rel="noopener" href="<?= h(gcal_link($e)) ?>">Add to Google Calendar</a>
    <?php share_buttons($e['title'], 'events/' . $e['slug']); ?>
  </aside>
</div></section>
