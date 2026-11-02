<section class="section section-cream ticket-wrap"><div class="narrow-wrap">
<?php if (!$valid): ?>
  <div class="form-card center">
    <h1 style="font-size:1.8rem">Ticket not available</h1>
    <p>Booking <strong><?= h($r['reference']) ?></strong> is currently <strong><?= h($r['status']) ?></strong>, so no ticket is active.
      <?= $r['status'] === 'pending' ? 'You will receive your ticket as soon as the event team approves it.' : 'Please contact us if you think this is a mistake.' ?></p>
    <p><a class="btn btn-primary" href="<?= url('booking/' . h($r['reference'])) ?>">View booking status</a></p>
  </div>
<?php else: ?>
  <article class="ticket" aria-label="Event ticket">
    <header class="ticket-head">
      <span class="brand-mark" aria-hidden="true">C&amp;B</span>
      <div><p class="eyebrow gold">Admit <?= (int)$r['guests'] ?> guest<?= (int)$r['guests'] === 1 ? '' : 's' ?></p><h1><?= h($r['title']) ?></h1></div>
    </header>
    <div class="ticket-body">
      <dl class="ticket-info">
        <div><dt>Date</dt><dd><?= fmt_date($r['start_at'], 'l, j F Y') ?></dd></div>
        <div><dt>Gates open</dt><dd><?= fmt_time($r['start_at']) ?></dd></div>
        <div><dt>Venue</dt><dd><?= h($r['venue']) ?>, <?= h($r['location']) ?></dd></div>
        <div><dt>Guest</dt><dd><?= h($r['full_name']) ?><?= $r['company'] ? ' · ' . h($r['company']) : '' ?></dd></div>
        <div><dt>Reservation</dt><dd><?= h(RES_TYPES[$r['type']]) ?><?= $r['package'] ? ' · ' . h($r['package']) . ' package' : '' ?></dd></div>
        <div><dt>Reference</dt><dd class="ticket-ref"><?= h($r['reference']) ?></dd></div>
      </dl>
      <div class="ticket-qr">
        <div id="qr" data-url="<?= h($checkin_url) ?>" role="img" aria-label="QR code for entry"></div>
        <?= $r['checked_in_at'] ? '<span class="badge badge-warn">Checked in ' . h(fmt_time($r['checked_in_at'])) . '</span>' : '<span class="badge badge-ok">Valid</span>' ?>
      </div>
    </div>
    <div class="ticket-tear" aria-hidden="true"></div>
    <footer class="ticket-foot">
      <p>Show this QR code at the entrance. One ticket admits the number of guests shown above. Please arrive with your reference <strong><?= h($r['reference']) ?></strong> if the code cannot be scanned.</p>
      <p class="small">Chill &amp; Bite MEATINGS · Organized by Owakabi &amp; The Geng</p>
    </footer>
  </article>
  <div class="btn-row center-row">
    <button class="btn btn-primary" type="button" onclick="window.print()">Print / Save as PDF</button>
    <a class="btn btn-outline-dark" target="_blank" rel="noopener" href="<?= h(gcal_link($r)) ?>">Add to calendar</a>
    <a class="btn btn-ghost-dark" href="<?= url('events/' . h($r['slug'])) ?>">Event details</a>
  </div>
  <script src="<?= h(asset('js/vendor/qrcode.min.js')) ?>"></script>
  <script>
    (function () {
      var el = document.getElementById('qr');
      if (el && window.QRCode) new QRCode(el, { text: el.dataset.url, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
    })();
  </script>
<?php endif; ?>
</div></section>
