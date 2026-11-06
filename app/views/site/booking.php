<?php
$first = trim(explode(' ', trim($r['full_name']))[0]) ?: 'friend';
$st = $r['status'];
$due = (float)$r['amount_due'];
$balance = max(0, $due - (float)$paid);
$pct = $due > 0 ? min(100, (int)round(((float)$paid / $due) * 100)) : 100;
$dress = preg_match('/Dress code:\s*([^\n.]+)/i', (string)$r['description'], $m) ? trim($m[1]) : null;
$phone = setting('contact_phone');
$payWa = setting('whatsapp') ? wa_link(setting('whatsapp'), "Hello Chill & Bite, I would like to pay for booking {$r['reference']} ({$r['full_name']}).") : null;
$goingWa = 'https://wa.me/?text=' . rawurlencode("I'm going to {$r['title']} on " . fmt_date($r['start_at'], 'j F') . "! Get your place: " . abs_url('events/' . $r['slug']));
$here = $r['checked_in_at'] !== null;

// Friendly headline + message for every state
if ($here) {
    $emoji = '🎶'; $head = "Welcome, $first!"; $msg = 'You are checked in. Eat, dance, network and make memories. We are so glad you are here.'; $tone = 'ok';
} elseif ($st === 'confirmed') {
    $emoji = '🥂'; $head = "You're in, $first!";
    $msg = 'Your place is confirmed and your ticket is ready. ' . ($dress ? "Get your $dress look ready, and " : 'Just ') . 'show the QR code at the gate. We cannot wait to celebrate with you.'; $tone = 'ok';
} elseif ($st === 'pending') {
    $emoji = '🎉'; $head = "Thank you, $first!";
    if ($due > 0 && $balance > 0 && $paid > 0) $msg = 'We received your payment so far, thank you! Settle the balance of ' . money($balance) . ' and we will confirm your table and send your ticket.';
    elseif ($due > 0 && $balance > 0) $msg = 'Your spot is saved! Complete your payment and our team will confirm your booking. Your QR ticket will appear on this page and be sent to you straight after.';
    else $msg = 'We have your request and our team is reviewing it. You will hear from us very soon, and your ticket will appear right here once it is approved.';
    $tone = 'warn';
} elseif ($st === 'rejected') {
    $emoji = '💛'; $head = "Sorry, $first"; $msg = 'We could not take this booking this time. Please reach out to us and we will gladly help you find another way to join the fun.'; $tone = 'err';
} elseif ($st === 'cancelled' && !empty($r['withdrawn_at'])) {
    $emoji = '⛔'; $head = 'Ticket withdrawn'; $msg = 'This ticket has been withdrawn and is no longer valid for entry.' . ($r['withdrawn_reason'] ? ' Reason: ' . $r['withdrawn_reason'] . '.' : '') . ' If you think this is a mistake, please contact us.'; $tone = 'err';
} else {
    $emoji = '💛'; $head = "Booking cancelled"; $msg = "This booking has been cancelled. If that was a mistake, or you would like to rebook, we would love to have you, $first."; $tone = 'err';
}

// Progress tracker
$s2 = $st === 'confirmed' ? 'done' : (in_array($st, ['rejected', 'cancelled'], true) ? 'fail' : 'active');
$s3 = $st === 'confirmed' ? 'done' : 'todo';
$steps = [
    ['done', 'Booking received', fmt_date($r['created_at'], 'j M, g:i A')],
    [$s2, $due > 0 ? 'Approval & payment' : 'Approval', $s2 === 'fail' ? ucfirst($st) : ($s2 === 'done' ? 'Confirmed' : 'In progress')],
    [$s3, 'Ticket ready', $s3 === 'done' ? 'Open your QR ticket' : 'Sent once confirmed'],
];
?>
<section class="page-hero compact bk-hero"><div class="wrap">
  <span class="eyebrow gold">Your booking</span>
  <h1><span aria-hidden="true"><?= $emoji ?></span> <?= h($head) ?></h1>
  <p class="lead bk-msg"><?= h($msg) ?></p>
  <p class="ref-chip">Booking code <strong id="ref"><?= h($r['reference']) ?></strong> <button type="button" class="chip-copy" data-copy="<?= h($r['reference']) ?>">Copy</button></p>
  <?php if (!in_array($st, ['rejected', 'cancelled'], true)): ?>
  <div class="btn-row bk-hero-actions" id="bkActions">
    <a class="btn btn-outline btn-lg" href="<?= url('booking/' . h($r['reference'])) ?>" id="bkTrack">🔎 Track my booking</a>
    <?php if ($st === 'confirmed' && $ticket): ?><a class="btn btn-primary btn-lg" href="<?= h($ticket) ?>" id="bkTicket">🎟️ Get my ticket</a>
    <?php else: ?><button class="btn btn-primary btn-lg" type="button" id="bkTicket" disabled aria-disabled="true" title="Available once your booking is approved">🎟️ Get my ticket</button><span class="bk-wait" id="bkWait">Available as soon as your booking is approved. This page updates by itself.</span><?php endif; ?>
  </div>
  <?php endif; ?>
</div></section>

<?php if (!empty($is_new)): ?>
<section class="bk-newcode"><div class="wrap">
  <div class="bk-code-card" role="status">
    <span class="bk-code-ic" aria-hidden="true">✅</span>
    <h2><?= $st === 'confirmed' ? 'Booking confirmed' : 'Booking submitted' ?></h2>
    <p>Your booking code has been generated. Keep it safe: it is how you track your booking and get your ticket.</p>
    <div class="bk-code" aria-label="Your booking code"><?= h($r['reference']) ?></div>
    <div class="btn-row">
      <button type="button" class="btn btn-primary" id="bkCopyBig" data-copy="<?= h($r['reference']) ?>">Copy code</button>
      <a class="btn btn-outline-dark" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode('My Chill & Bite booking code is ' . $r['reference'] . '. Track it and get my ticket here: ' . abs_url('booking/' . $r['reference'])) ?>">Save on WhatsApp</a>
    </div>
    <?php if (!empty($r['email'])): ?><p class="small muted">We also emailed the code to <?= h($r['email']) ?>.</p><?php endif; ?>
  </div>
</div></section>
<?php endif; ?>

<section class="section section-cream bk-section"><div class="wrap">
  <ol class="tracker" aria-label="Booking progress">
    <?php foreach ($steps as $i => [$state, $label, $sub]): ?>
      <li class="tr-<?= $state ?>"><span class="tr-dot"><?= $state === 'done' ? '✓' : ($state === 'fail' ? '!' : $i + 1) ?></span><b><?= h($label) ?></b><small><?= h($sub) ?></small></li>
    <?php endforeach; ?>
  </ol>

  <?php if ($st === 'confirmed'): ?>
    <div class="ticket-cta">
      <div><strong>Your ticket is ready</strong><span>Show the QR code at the entrance. One ticket admits <?= (int)$r['guests'] ?> guest<?= (int)$r['guests'] === 1 ? '' : 's' ?>.</span></div>
      <div class="btn-row"><a class="btn btn-primary btn-lg" href="<?= h($ticket) ?>">Open my ticket</a><a class="btn btn-outline btn-lg" style="border-color:var(--gold);color:var(--gold)" href="<?= h($ticket . '?download=1') ?>">⬇ Download</a></div>
    </div>
  <?php endif; ?>

  <div class="bk-grid">
    <article class="bk-card">
      <h2>Booking details</h2>
      <div class="bk-event">
        <div class="bk-thumb"><?php media($r['poster'], $r['title'], '', date('j M', strtotime($r['start_at'])), photo_from('crowd', (int)$r['event_id'])); ?></div>
        <div><a href="<?= url('events/' . h($r['slug'])) ?>"><strong><?= h($r['title']) ?></strong></a>
          <p class="small muted"><?= h($r['venue']) ?>, <?= h($r['location']) ?></p></div>
      </div>
      <dl class="bk-list">
        <div><dt>Date</dt><dd><?= fmt_date($r['start_at'], 'l, j F Y') ?></dd></div>
        <div><dt>Gates open</dt><dd><?= fmt_time($r['start_at']) ?></dd></div>
        <div><dt>Name</dt><dd><?= h($r['full_name']) ?></dd></div>
        <div><dt>Reservation</dt><dd><?= h(RES_TYPES[$r['type']]) ?></dd></div>
        <?php if ($r['package']): ?><div><dt>Table package</dt><dd><?= h($r['package']) ?></dd></div><?php endif; ?>
        <div><dt>Guests</dt><dd><?= (int)$r['guests'] ?></dd></div>
        <?php if ($dress): ?><div><dt>Dress code</dt><dd><?= h($dress) ?></dd></div><?php endif; ?>
      </dl>
    </article>

    <div class="bk-side">
      <?php if ($due > 0): ?>
      <article class="bk-card">
        <h2>Payment</h2>
        <div class="pay-amount"><span><?= $balance > 0 ? 'Balance to pay' : 'Paid in full' ?></span><b><?= $balance > 0 ? h(money($balance)) : '✓ ' . h(money($due)) ?></b></div>
        <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" aria-label="Payment progress"><i style="width:<?= $pct ?>%"></i></div>
        <p class="small muted"><?= h(money($paid)) ?> paid of <?= h(money($due)) ?> (<?= $pct ?>%)</p>
        <?php if ($balance > 0 && !in_array($st, ['rejected', 'cancelled'], true)): ?>
          <p class="small">To pay, contact the team and quote your reference <strong><?= h($r['reference']) ?></strong>. We will confirm as soon as your payment is recorded.</p>
          <div class="btn-row">
            <?php if ($payWa): ?><a class="btn btn-primary btn-sm" target="_blank" rel="noopener" href="<?= h($payWa) ?>">Pay via WhatsApp</a><?php endif; ?>
            <?php if ($phone): ?><a class="btn btn-ghost-dark btn-sm" href="tel:<?= h(preg_replace('/\s+/', '', $phone)) ?>">Call <?= h($phone) ?></a><?php endif; ?>
          </div>
        <?php endif; ?>
      </article>
      <?php endif; ?>

      <article class="bk-card">
        <h2>What happens next</h2>
        <?php if ($st === 'confirmed'): ?>
          <ul class="ticks"><li>Open your ticket and save or print it</li><li>Arrive from <?= h(fmt_time($r['start_at'])) ?> and show the QR code</li><li>Network, enjoy the food and have a great time</li></ul>
        <?php elseif ($st === 'pending'): ?>
          <ul class="ticks"><li>Our team reviews your booking<?= $due > 0 ? ' and records your payment' : '' ?></li><li>You get a confirmation and your QR ticket</li><li>Bookmark this page to check your status any time</li></ul>
        <?php else: ?>
          <p class="small">Questions? <a href="<?= url('contact') ?>">Contact us</a> and quote <strong><?= h($r['reference']) ?></strong>. <a href="<?= url('events') ?>">Browse upcoming events</a>.</p>
        <?php endif; ?>
      </article>
    </div>
  </div>

  <div class="btn-row bk-actions">
    <?php if ($st === 'confirmed'): ?>
      <a class="btn btn-outline-dark" target="_blank" rel="noopener" href="<?= h(gcal_link($r)) ?>">Add to calendar</a>
      <a class="btn btn-outline-dark" target="_blank" rel="noopener" href="<?= h($goingWa) ?>">Tell a friend on WhatsApp</a>
    <?php endif; ?>
    <button class="btn btn-ghost-dark" type="button" onclick="window.print()">Print</button>
    <a class="btn btn-ghost-dark" href="<?= url('events/' . h($r['slug'])) ?>">Event details</a>
  </div>
  <p class="bk-thanks">Thank you for choosing <strong>Chill &amp; Bite MEATINGS</strong>. Where connections are made and good vibes never end. 🧡</p>
</div></section>
<script>
(function () {
  if (location.search.indexOf('new=') > -1 && history.replaceState) history.replaceState(null, '', location.pathname);
  var big = document.getElementById('bkCopyBig');
  if (big) big.addEventListener('click', function () { var t = big.dataset.copy, done = function () { big.textContent = 'Copied!'; setTimeout(function () { big.textContent = 'Copy code'; }, 1600); }; if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, done); else done(); });
  // Pending bookings: check every 15 seconds and refresh the page as soon as the booking is approved
  var st = <?= json_encode($st) ?>;
  if (st !== 'pending' || !window.fetch) return;
  var url = <?= json_encode(url('booking/' . $r['reference'] . '/status')) ?>;
  function check() {
    if (document.hidden) return;
    fetch(url, { cache: 'no-store' }).then(function (x) { return x.json(); }).then(function (j) { if (j && j.status && j.status !== 'pending') location.reload(); }).catch(function () {});
  }
  setInterval(check, 15000); document.addEventListener('visibilitychange', check);
})();
document.querySelectorAll('.chip-copy').forEach(function (b) {
  b.addEventListener('click', function () {
    var t = b.dataset.copy, done = function () { b.textContent = 'Copied!'; setTimeout(function () { b.textContent = 'Copy'; }, 1600); };
    if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, done); else done();
  });
});
</script>
