<section class="page-hero hero-dark"><div class="wrap"><span class="eyebrow gold"><?= cth('laugh.hero.eyebrow') ?></span><h1><?= cth('laugh.hero.heading') ?></h1>
  <p class="lead"><?= cth('laugh.hero.lead') ?></p>
  <div class="btn-row"><a class="btn btn-primary" href="#shows"><?= cth('laugh.hero.btn_shows') ?></a><a class="btn btn-outline" href="#book"><?= cth('laugh.hero.btn_book') ?></a></div></div></section>

<section class="section section-dark"><div class="wrap">
  <div class="section-head"><span class="eyebrow gold"><?= cth('laugh.videos.eyebrow') ?></span><h2><?= cth('laugh.videos.heading') ?></h2></div>
  <?php if ($videos): ?>
    <div class="grid grid-3"><?php foreach ($videos as $v): ?><div><?php video_embed($v); ?><p class="small muted"><?= h(ucwords(str_replace('_', ' ', $v['kind']))) ?><?= $v['description'] ? ' · ' . h($v['description']) : '' ?></p></div><?php endforeach; ?></div>
  <?php else: ?><p class="muted"><?= cth('laugh.videos.empty') ?></p><?php endif; ?>
</div></section>

<section class="section section-cream" id="shows"><div class="wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('laugh.shows.eyebrow') ?></span><h2><?= cth('laugh.shows.heading') ?></h2></div>
  <?php if ($shows): ?><div class="grid grid-3"><?php foreach ($shows as $e) event_card($e); ?></div><?php else: ?><p class="muted"><?= cth('laugh.shows.empty') ?></p><?php endif; ?>
</div></section>

<section class="section" id="book"><div class="wrap narrow-wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('laugh.booking.eyebrow') ?></span><h2><?= cth('laugh.booking.heading') ?></h2><p><?= cth('laugh.booking.intro') ?></p></div>
  <form class="form-card" method="post" action="<?= url('laugh') ?>"><?= csrf_field() ?><?php honeypot(); ?>
    <div class="form-grid">
      <?php field('name', 'Contact name', ['required' => true]); field('organization', 'Organization (optional)'); field('phone', 'Telephone', ['required' => true, 'type' => 'tel']); field('email', 'Email', ['type' => 'email']);
      field('event_date', 'Event date', ['type' => 'date', 'required' => true]); field('location', 'Event location', ['required' => true]);
      field('event_type', 'Event type', ['type' => 'select', 'options' => ['' => 'Choose...', 'Corporate' => 'Corporate event', 'Wedding' => 'Wedding / celebration', 'Concert' => 'Concert / festival', 'Launch' => 'Product / brand launch', 'Private' => 'Private party', 'Other' => 'Other']]);
      field('audience', 'Expected audience size', ['type' => 'number', 'attrs' => 'min="1"']); ?>
    </div>
    <?php field('message', 'Additional requirements', ['type' => 'textarea']); ?>
    <button class="btn btn-primary btn-lg"><?= cth('laugh.booking.button') ?></button>
  </form>
</div></section>
