<?php $bal = $r['amount_due'] - $r['paid']; $act = url('admin/reservations/' . $r['id'] . '/action'); ?>
<div class="adm-head"><h1><?= h($r['reference']) ?> <?= status_badge($r['status']) ?></h1><a href="<?= url('admin/reservations?event=' . $r['event_id']) ?>">&larr; Guest list</a></div>
<div class="panel"><dl class="summary">
  <dt>Event</dt><dd><?= h($r['event_title']) ?></dd><dt>Guest</dt><dd><?= h($r['full_name']) ?><?= $r['company'] ? ' · ' . h($r['company']) : '' ?></dd>
  <dt>Phone</dt><dd><a href="tel:<?= h($r['phone']) ?>"><?= h($r['phone']) ?></a></dd><dt>Email</dt><dd><?= h($r['email'] ?: '—') ?></dd>
  <dt>Type</dt><dd><?= h(RES_TYPES[$r['type']]) ?><?= $r['package'] ? ' · <strong>' . h($r['package']) . ' package</strong>' : '' ?> · <?= (int)$r['guests'] ?> guest(s)</dd><dt>Seating</dt><dd><?= h($r['seating'] ?: '—') ?></dd>
  <dt>Requests</dt><dd><?= h($r['requests'] ?: '—') ?></dd><dt>Booked</dt><dd><?= fmt_date($r['created_at'], 'j M Y H:i') ?></dd>
  <dt>Checked in</dt><dd><?= $r['checked_in_at'] ? fmt_date($r['checked_in_at'], 'j M Y H:i') : 'No' ?></dd></dl>
  <div class="actions"><?php foreach (array_filter([
      can('reservations_manage') && $r['status'] === 'pending' ? ['approve', 'Approve', 'btn-primary'] : null, can('reservations_manage') && $r['status'] === 'pending' ? ['reject', 'Reject', 'btn-ghost'] : null,
      can('reservations_manage') && in_array($r['status'], ['pending', 'confirmed']) ? ['cancel', 'Cancel booking', 'btn-ghost'] : null,
      can('checkin') && $r['status'] === 'confirmed' && !$r['checked_in_at'] ? ['checkin', 'Check in guest', 'btn-primary'] : null,
      can('checkin') && $r['checked_in_at'] ? ['undo_checkin', 'Undo check-in', 'btn-ghost'] : null]) as [$a, $lab, $cls]): ?>
    <form class="inline" method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="<?= $a ?>"><button class="btn <?= $cls ?>"<?= $a === 'cancel' ? ' onclick="return confirm(\'Cancel this booking?\')"' : '' ?>><?= $lab ?></button></form>
  <?php endforeach; ?></div></div>
<?php if ($ticket && can('reservations_manage')): ?>
<div class="panel"><h2>Ticket</h2>
  <p class="small muted">The ticket is issued automatically when a booking is confirmed. Send it to the guest by email or WhatsApp.</p>
  <p class="small">Ticket link: <a href="<?= h($ticket) ?>" target="_blank" rel="noopener"><?= h($ticket) ?></a></p>
  <div class="actions">
    <a class="btn btn-primary" href="<?= h($wa) ?>" target="_blank" rel="noopener">Send ticket on WhatsApp</a>
    <?php if ($r['email']): ?><form class="inline" method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="send_ticket"><button class="btn btn-ghost">Email ticket</button></form><?php else: ?><span class="small muted">No email on file</span><?php endif; ?>
    <a class="btn btn-ghost" href="<?= h($ticket) ?>" target="_blank" rel="noopener">Open ticket</a>
  </div></div>
<?php endif; ?>
<div class="panel"><h2>Payments</h2>
  <p>Due <strong><?= money($r['amount_due']) ?></strong> · Paid <strong><?= money($r['paid']) ?></strong> · Outstanding <strong><?= money(max(0, $bal)) ?></strong></p>
  <?php if ($payments): ?><div class="tbl-wrap"><table class="tbl"><tr><th>Date</th><th>Method</th><th>Amount</th><th>Note</th><th>By</th></tr>
  <?php foreach ($payments as $p): ?><tr><td><?= fmt_date($p['created_at'], 'j M H:i') ?></td><td><?= h($p['method']) ?></td><td><?= money($p['amount']) ?></td><td><?= h($p['note']) ?></td><td><?= h($p['by_name']) ?></td></tr><?php endforeach; ?></table></div><?php endif; ?>
  <?php if (can('payments')): ?><form method="post" action="<?= $act ?>" class="filters"><?= csrf_field() ?>
    <label>Action <select name="action"><option value="payment">Record payment</option><option value="refund">Record refund</option></select></label>
    <label>Amount (UGX) <input name="amount" inputmode="numeric" required></label>
    <label>Method <select name="method"><option value="cash">Cash</option><option value="mobile_money">Mobile money</option><option value="bank">Bank</option></select></label>
    <label>Note <input name="note" maxlength="255"></label><button class="btn btn-primary btn-sm">Save</button></form><?php endif; ?>
  <p class="small muted">Card details are never stored. Online payments (Pesapal) are enabled in a later phase.</p></div>
<?php if (can('reservations_manage')): ?><div class="panel"><h2>Internal note</h2><form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="note"><input name="admin_note" value="<?= h($r['admin_note']) ?>" maxlength="255"><p><button class="btn btn-ghost btn-sm">Save note</button></p></form></div><?php endif; ?>
