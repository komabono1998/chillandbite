<section class="page-hero compact"><div class="wrap"><span class="eyebrow gold">Your booking</span><h1>Reference <?= h($r['reference']) ?></h1></div></section>
<section class="section section-cream"><div class="wrap narrow-wrap"><div class="form-card">
  <p><?= status_badge($r['status']) ?></p>
  <?php if ($r['status'] === 'confirmed'): ?><p class="alert alert-ok">Your place is confirmed. Show this reference at the entrance.</p>
  <?php elseif ($r['status'] === 'pending'): ?><p class="alert alert-warn">Your booking is awaiting confirmation<?= $r['amount_due'] > 0 ? ' and payment' : '' ?>. We will contact you on the details you gave.</p>
  <?php else: ?><p class="alert alert-err">This booking is <?= h($r['status']) ?>. Please contact us if you have questions.</p><?php endif; ?>
  <dl class="summary">
    <dt>Event</dt><dd><a href="<?= url('events/' . h($r['slug'])) ?>"><?= h($r['title']) ?></a></dd>
    <dt>When</dt><dd><?= fmt_date($r['start_at'], 'l, j F Y') ?> · <?= fmt_time($r['start_at']) ?></dd>
    <dt>Where</dt><dd><?= h($r['venue']) ?>, <?= h($r['location']) ?></dd>
    <dt>Name</dt><dd><?= h($r['full_name']) ?></dd>
    <dt>Reservation</dt><dd><?= h(RES_TYPES[$r['type']]) ?><?= $r['package'] ? ' · ' . h($r['package']) . ' package' : '' ?> · <?= (int)$r['guests'] ?> guest(s)</dd>
    <?php if ($r['amount_due'] > 0): ?><dt>Amount due</dt><dd><?= money($r['amount_due']) ?></dd><dt>Paid</dt><dd><?= money($paid) ?></dd><?php endif; ?>
  </dl>
  <button class="btn btn-ghost-dark" onclick="window.print()">Print</button>
</div></div></section>
