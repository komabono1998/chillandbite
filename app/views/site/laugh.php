<section class="page-hero hero-dark"><div class="wrap"><span class="eyebrow gold">Laugh with Owakabi</span><h1>Life is better with a little humour!</h1>
  <p class="lead">Comedy shows, video highlights and performance bookings with MC Kash Owakabi.</p>
  <div class="btn-row"><a class="btn btn-primary" href="#shows">Upcoming shows</a><a class="btn btn-outline" href="#book">Book a performance</a></div></div></section>

<section class="section section-dark"><div class="wrap">
  <div class="section-head"><span class="eyebrow gold">Video library</span><h2>Watch &amp; laugh</h2></div>
  <?php if ($videos): ?>
    <div class="grid grid-3"><?php foreach ($videos as $v): ?><div><?php video_embed($v); ?><p class="small muted"><?= h(ucwords(str_replace('_', ' ', $v['kind']))) ?><?= $v['description'] ? ' · ' . h($v['description']) : '' ?></p></div><?php endforeach; ?></div>
  <?php else: ?><p class="muted">Videos are coming soon.</p><?php endif; ?>
</div></section>

<section class="section section-cream" id="shows"><div class="wrap">
  <div class="section-head"><span class="eyebrow">Comedy events</span><h2>Upcoming performances</h2></div>
  <?php if ($shows): ?><div class="grid grid-3"><?php foreach ($shows as $e) event_card($e); ?></div><?php else: ?><p class="muted">No shows are scheduled right now. Check back soon or book a private performance below.</p><?php endif; ?>
</div></section>

<section class="section" id="book"><div class="wrap narrow-wrap">
  <div class="section-head"><span class="eyebrow">Performance booking</span><h2>Book MC Kash Owakabi</h2><p>Organizations, brands, event organizers and individuals: tell us about your event.</p></div>
  <form class="form-card" method="post" action="<?= url('laugh') ?>"><?= csrf_field() ?><?php honeypot(); ?>
    <div class="form-grid">
      <?php field('name', 'Contact name', ['required' => true]); field('organization', 'Organization (optional)'); field('phone', 'Telephone', ['required' => true, 'type' => 'tel']); field('email', 'Email', ['type' => 'email']);
      field('event_date', 'Event date', ['type' => 'date', 'required' => true]); field('location', 'Event location', ['required' => true]);
      field('event_type', 'Event type', ['type' => 'select', 'options' => ['' => 'Choose...', 'Corporate' => 'Corporate event', 'Wedding' => 'Wedding / celebration', 'Concert' => 'Concert / festival', 'Launch' => 'Product / brand launch', 'Private' => 'Private party', 'Other' => 'Other']]);
      field('audience', 'Expected audience size', ['type' => 'number', 'attrs' => 'min="1"']); ?>
    </div>
    <?php field('message', 'Additional requirements', ['type' => 'textarea']); ?>
    <button class="btn btn-primary btn-lg">Send request</button>
  </form>
</div></section>
