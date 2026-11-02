<?php
$phone = trim((string)setting('contact_phone'));
$telHref = $phone ? 'tel:' . preg_replace('/\s+/', '', $phone) : '';
$waHref = $phone ? wa_link($phone, "Hello, I'd like to book MC Kash Owakabi for an event.") : '';
$trust = ctl('laugh.hero.trust');
$kinds = array_values(array_unique(array_column($videos, 'kind')));
$kindLabel = fn(string $k) => ucwords(str_replace('_', ' ', $k));
$firstVideo = $videos[0] ?? null;
$restVideos = $firstVideo ? array_slice($videos, 1) : [];
$nextShow = $shows[0] ?? null;
$selType = old('event_type');
?>
<section class="page-hero hero-dark lw-hero"><div class="wrap lw-hero-in">
  <div class="lw-hero-text">
    <span class="eyebrow gold"><?= cth('laugh.hero.eyebrow') ?></span>
    <h1><?= cth('laugh.hero.heading') ?></h1>
    <p class="lead"><?= cth('laugh.hero.lead') ?></p>
    <div class="btn-row"><a class="btn btn-primary btn-lg" href="#shows"><?= cth('laugh.hero.btn_shows') ?></a><a class="btn btn-outline btn-lg" href="#book"><?= cth('laugh.hero.btn_book') ?></a></div>
    <?php if ($nextShow): ?>
      <a class="lw-next" href="<?= url('events/' . h($nextShow['slug'])) ?>"><span class="lw-live" aria-hidden="true"></span>
        <span><small>Next show</small><b><?= h(fmt_date($nextShow['start_at'], 'D, j M')) ?> · <?= h(fmt_time($nextShow['start_at'])) ?></b></span>
        <span class="lw-next-t" id="lwNext" data-target="<?= h(date('c', strtotime($nextShow['start_at']))) ?>" aria-label="Time until the next show"></span></a>
    <?php endif; ?>
  </div>
  <div class="lw-hero-photo">
    <div class="lw-frame"><img src="<?= h(cti('laugh.host.photo')) ?>" alt="MC Kash Owakabi" width="592" height="800" decoding="async"></div>
    <span class="lw-badge lw-badge-1">😂 <?= cth('laugh.hero.badge') ?></span>
    <span class="lw-badge lw-badge-2">🎤 Owakabi &amp; The Geng</span>
  </div>
</div></section>

<?php if ($trust): $items = ''; foreach ($trust as $t) $items .= '<span>' . h($t) . '</span>'; ?>
<div class="lw-marquee" aria-label="Highlights"><div class="lw-marquee-track"><?= $items ?><span class="lw-dup" aria-hidden="true"><?= $items ?></span></div></div>
<?php endif; ?>

<section class="section section-cream"><div class="wrap lw-host">
  <figure class="lw-host-photo" data-reveal><img src="<?= h(cti('laugh.host.photo')) ?>" alt="MC Kash Owakabi, host and comedian" loading="lazy" decoding="async" width="592" height="800"></figure>
  <div data-reveal>
    <span class="eyebrow"><?= cth('laugh.host.eyebrow') ?></span>
    <h2><?= cth('laugh.host.heading') ?></h2>
    <p><?= cth('laugh.host.bio') ?></p>
    <p><?= cth('laugh.host.bio2') ?></p>
    <ul class="ticks"><?php foreach (ctl('laugh.host.points') as $pt): ?><li><?= h($pt) ?></li><?php endforeach; ?></ul>
    <div class="btn-row"><a class="btn btn-primary" href="#book"><?= cth('laugh.hero.btn_book') ?></a>
      <?php if ($waHref): ?><a class="btn btn-ghost-dark" target="_blank" rel="noopener" href="<?= h($waHref) ?>">WhatsApp us</a><?php endif; ?></div>
  </div>
</div></section>

<section class="section section-dark lw-services"><div class="wrap">
  <div class="section-head center"><span class="eyebrow gold"><?= cth('laugh.services.eyebrow') ?></span><h2><?= cth('laugh.services.heading') ?></h2><p><?= cth('laugh.services.intro') ?></p></div>
  <div class="lw-svc-grid">
    <?php foreach (ctrows('laugh.services.cards', 4) as [$ic, $t, $d, $val]): ?>
      <button type="button" class="lw-svc" data-type="<?= h($val) ?>" data-reveal><span class="lw-svc-ic" aria-hidden="true"><?= h($ic) ?></span><b><?= h($t) ?></b><span><?= h($d) ?></span><em>Request this →</em></button>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section section-dark lw-videos" id="videos"><div class="wrap">
  <div class="section-head"><span class="eyebrow gold"><?= cth('laugh.videos.eyebrow') ?></span><h2><?= cth('laugh.videos.heading') ?></h2><p><?= cth('laugh.videos.intro') ?></p></div>
  <?php if ($videos): ?>
    <?php if (count($kinds) > 1): ?>
      <nav class="lw-chips" aria-label="Filter videos"><button type="button" class="on" data-kind="all">All</button><?php foreach ($kinds as $k): ?><button type="button" data-kind="<?= h($k) ?>"><?= h($kindLabel($k)) ?></button><?php endforeach; ?></nav>
    <?php endif; ?>
    <div class="lw-vid-feature" data-kind="<?= h($firstVideo['kind']) ?>"><?php video_embed($firstVideo, true); ?>
      <div class="lw-vid-info"><span class="tag"><?= h($kindLabel($firstVideo['kind'])) ?></span><h3><?= h($firstVideo['title']) ?></h3><?php if ($firstVideo['description']): ?><p><?= h($firstVideo['description']) ?></p><?php endif; ?></div></div>
    <?php if ($restVideos): ?><div class="grid grid-3 lw-vid-grid"><?php foreach ($restVideos as $v): ?><div class="lw-vid" data-kind="<?= h($v['kind']) ?>"><?php video_embed($v); ?><p class="small muted"><?= h($kindLabel($v['kind'])) ?><?= $v['description'] ? ' · ' . h($v['description']) : '' ?></p></div><?php endforeach; ?></div><?php endif; ?>
  <?php else: ?>
    <div class="lw-empty"><span aria-hidden="true">🎬</span><p><?= cth('laugh.videos.empty') ?></p></div>
  <?php endif; ?>
</div></section>

<section class="section section-cream" id="shows"><div class="wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('laugh.shows.eyebrow') ?></span><h2><?= cth('laugh.shows.heading') ?></h2></div>
  <?php if ($shows): ?><div class="grid grid-3"><?php foreach ($shows as $e) event_card($e); ?></div>
  <?php else: ?><div class="lw-empty lw-empty-light"><span aria-hidden="true">🎭</span><p><?= cth('laugh.shows.empty') ?></p><a class="btn btn-primary" href="#book">Book a private performance</a></div><?php endif; ?>
</div></section>

<section class="section section-dark lw-process"><div class="wrap">
  <div class="section-head center"><span class="eyebrow gold">Simple process</span><h2><?= cth('laugh.process.heading') ?></h2></div>
  <div class="steps acc-steps">
    <?php foreach (ctrows('laugh.process.steps') as $i => [$t, $d]): ?><div data-reveal><b><?= $i + 1 ?></b><h4><?= h($t) ?></h4><p><?= h($d) ?></p></div><?php endforeach; ?>
  </div>
</div></section>

<section class="section section-cream lw-book" id="book"><div class="wrap lw-book-in">
  <div>
    <div class="section-head"><span class="eyebrow"><?= cth('laugh.booking.eyebrow') ?></span><h2><?= cth('laugh.booking.heading') ?></h2><p><?= cth('laugh.booking.intro') ?></p></div>
    <form class="form-card" id="lwForm" method="post" action="<?= url('laugh') ?>"><?= csrf_field() ?><?php honeypot(); ?>
      <fieldset class="lw-types"><legend>What kind of event is it?</legend>
        <?php foreach (['Corporate' => '🏢 Corporate', 'Wedding' => '💍 Wedding', 'Concert' => '🎤 Concert / festival', 'Launch' => '🚀 Launch', 'Private' => '🎉 Private party', 'Other' => '🎙️ Other'] as $k => $l): ?>
          <label><input type="radio" name="event_type" value="<?= h($k) ?>"<?= $selType === $k ? ' checked' : '' ?>><span><?= h($l) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <div class="form-grid">
        <?php field('name', 'Contact name', ['required' => true]); field('organization', 'Organization (optional)'); field('phone', 'Telephone', ['required' => true, 'type' => 'tel']); field('email', 'Email', ['type' => 'email']);
        field('event_date', 'Event date', ['type' => 'date', 'required' => true, 'attrs' => 'min="' . date('Y-m-d') . '"']); field('location', 'Event location', ['required' => true]);
        field('audience', 'Expected audience size', ['type' => 'number', 'attrs' => 'min="1"']); ?>
      </div>
      <?php field('message', 'Additional requirements', ['type' => 'textarea']); ?>
      <button class="btn btn-primary btn-lg"><?= cth('laugh.booking.button') ?></button>
    </form>
  </div>
  <aside class="lw-side" data-reveal>
    <h3><?= cth('laugh.booking.side_title') ?></h3>
    <p><?= cth('laugh.booking.side_text') ?></p>
    <?php if ($telHref): ?><a class="lw-contact" href="<?= h($telHref) ?>"><span aria-hidden="true">📞</span><span><small>Call</small><b><?= h($phone) ?></b></span></a><?php endif; ?>
    <?php if ($waHref): ?><a class="lw-contact lw-wa" target="_blank" rel="noopener" href="<?= h($waHref) ?>"><span aria-hidden="true">💬</span><span><small>WhatsApp</small><b>Chat with us</b></span></a><?php endif; ?>
    <ul class="ticks"><li>Clean, audience-friendly humour</li><li>Professional MC for your whole programme</li><li>Gulu-based, available across Uganda</li></ul>
  </aside>
</div></section>

<section class="section section-dark"><div class="narrow-wrap">
  <div class="section-head center"><span class="eyebrow gold">FAQ</span><h2><?= cth('laugh.faq.heading') ?></h2></div>
  <div class="ab-faq">
    <?php foreach (ctrows('laugh.faq.items') as [$qq, $aa]): ?><details><summary><?= h($qq) ?></summary><p><?= h($aa) ?></p></details><?php endforeach; ?>
  </div>
</div></section>

<script>
(function () {
  // Countdown to the next show
  var t = document.getElementById('lwNext');
  if (t) {
    var target = new Date(t.dataset.target).getTime();
    var tick = function () {
      var d = target - Date.now();
      if (d <= 0) { t.textContent = 'Happening now'; return false; }
      var dd = Math.floor(d / 864e5), hh = Math.floor(d % 864e5 / 36e5), mm = Math.floor(d % 36e5 / 6e4);
      t.textContent = dd + 'd ' + hh + 'h ' + mm + 'm';
      return true;
    };
    if (tick()) setInterval(tick, 30000);
  }
  // Service cards pick the event type and jump to the form
  var form = document.getElementById('lwForm');
  document.querySelectorAll('.lw-svc').forEach(function (c) {
    c.addEventListener('click', function () {
      var r = form && form.querySelector('input[name="event_type"][value="' + c.dataset.type + '"]');
      if (r) r.checked = true;
      var book = document.getElementById('book');
      if (book) book.scrollIntoView({ behavior: 'smooth' });
      var n = form && form.querySelector('input[name="name"]'); if (n) setTimeout(function () { n.focus({ preventScroll: true }); }, 600);
    });
  });
  // Video filter
  var chips = document.querySelectorAll('.lw-chips button');
  chips.forEach(function (b) {
    b.addEventListener('click', function () {
      chips.forEach(function (x) { x.classList.toggle('on', x === b); });
      document.querySelectorAll('.lw-vid-feature, .lw-vid').forEach(function (v) { v.hidden = b.dataset.kind !== 'all' && v.dataset.kind !== b.dataset.kind; });
    });
  });
})();
</script>
