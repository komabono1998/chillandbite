<?php
$state = 'bad'; $headline = 'Ticket not recognised'; $note = 'No booking matches this code.';
if ($r) {
    $balance = max(0, (float)$r['amount_due'] - (float)$r['paid']);
    if ($r['status'] === 'cancelled' && !empty($r['withdrawn_at'])) { $state = 'bad'; $headline = 'TICKET WITHDRAWN'; $note = 'Do not admit. This ticket was withdrawn' . ($r['withdrawn_reason'] ? ': ' . $r['withdrawn_reason'] : '') . '. Send the guest to the event team.'; }
    elseif ($r['status'] !== 'confirmed') { $state = 'bad'; $headline = 'NOT VALID: booking is ' . $r['status']; $note = 'Do not admit. Send the guest to the event team.'; }
    elseif ($r['checked_in_at']) { $state = 'warn'; $headline = 'ALREADY CHECKED IN'; $note = 'Checked in at ' . fmt_date($r['checked_in_at'], 'j M Y H:i') . '. Do not admit again without checking with the event manager.'; }
    else { $state = 'ok'; $headline = 'VALID TICKET'; $note = 'Admit ' . (int)$r['guests'] . ' guest(s).'; }
}
?>
<div class="adm-head"><h1>Door check-in</h1><a href="<?= url('admin/reservations') ?>">&larr; All reservations</a></div>
<div class="panel checkin checkin-<?= $state ?>">
  <h2><?= h($headline) ?></h2>
  <p class="checkin-note"><?= h($note) ?></p>
  <?php if ($r): ?>
    <dl class="summary">
      <dt>Guest</dt><dd><strong><?= h($r['full_name']) ?></strong><?= $r['company'] ? ' · ' . h($r['company']) : '' ?></dd>
      <dt>Event</dt><dd><?= h($r['event_title']) ?></dd>
      <dt>Booking</dt><dd><?= h(RES_TYPES[$r['type']]) ?><?= $r['package'] ? ' · ' . h($r['package']) : '' ?> · <strong><?= (int)$r['guests'] ?> guest(s)</strong></dd>
      <dt>Reference</dt><dd><?= h($r['reference']) ?></dd>
      <?php if ($r['amount_due'] > 0): ?><dt>Payment</dt><dd><?= $balance > 0 ? '<span class="badge badge-err">Balance due ' . h(money($balance)) . '</span>' : '<span class="badge badge-ok">Paid in full</span>' ?></dd><?php endif; ?>
    </dl>
    <?php if ($state === 'ok'): ?>
      <form method="post" action="<?= url('admin/reservations/' . $r['id'] . '/action') ?>"><?= csrf_field() ?>
        <input type="hidden" name="action" value="checkin"><input type="hidden" name="back" value="admin/checkin/<?= h($token) ?>">
        <button class="btn btn-primary btn-lg btn-block">Check in <?= (int)$r['guests'] ?> guest(s)</button></form>
    <?php endif; ?>
    <p><a href="<?= url('admin/reservations/' . $r['id']) ?>">Open full booking</a></p>
  <?php endif; ?>
</div>
