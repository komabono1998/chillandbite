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
    <div class="prose">
      <h2>About this event</h2>
      <?= paragraphs($e['description']) ?>
      <?php if ($e['video_url'] && ($id = youtube_id($e['video_url']))): video_embed(['url' => $e['video_url'], 'title' => 'Event video']); endif; ?>
    </div>
    <?php if ($sponsors): ?><h3>Featured partners</h3><ul class="sponsor-row dark-on-light"><?php foreach ($sponsors as $s): ?><li><?= $s['logo'] ? '<img src="' . h(upload_url($s['logo'])) . '" alt="' . h($s['name']) . '" loading="lazy">' : '<span>' . h($s['name']) . '</span>' ?></li><?php endforeach; ?></ul><?php endif; ?>
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
