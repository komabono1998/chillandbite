<?php
$eid = (int)($ev['id'] ?? 0);
$back = 'admin/tickets?' . http_build_query(array_filter(['event' => $eid ?: '', 'status' => $status !== 'confirmed' ? $status : '', 'q' => $q]));
$tabs = ['confirmed' => 'Tickets issued', 'pending' => 'Awaiting approval', 'cancelled' => 'Cancelled'];
$manage = can('reservations_manage');
?>
<div class="adm-head">
  <div><h1>Tickets</h1><p class="adm-sub">Every confirmed booking has a ticket with a QR code. Find a guest, then open it, download it, send it, or issue a new one.</p></div>
  <div class="btn-row">
    <?php if ($ev): ?><a class="btn btn-outline" href="<?= url('admin/tickets/print?event=' . $eid) ?>" target="_blank" rel="noopener">🖨️ Print all tickets</a><?php endif; ?>
    <a class="btn btn-primary" href="#issue">＋ Issue a ticket</a>
  </div>
</div>

<form class="panel filters" method="get" action="<?= url('admin/tickets') ?>">
  <label>Event <select name="event" onchange="this.form.submit()"><?php foreach ($events as $x): ?><option value="<?= $x['id'] ?>"<?= $eid === (int)$x['id'] ? ' selected' : '' ?>><?= h(excerpt($x['title'], 56)) ?> · <?= fmt_date($x['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></label>
  <label style="flex:1;min-width:220px">Find a guest <input type="search" name="q" value="<?= h($q) ?>" placeholder="Name, phone or reference"></label>
  <input type="hidden" name="status" value="<?= h($status) ?>">
  <button class="btn btn-primary btn-sm">Search</button>
  <?php if ($q !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/tickets?event=' . $eid . '&status=' . $status) ?>">Clear</a><?php endif; ?>
</form>

<nav class="pills" aria-label="Ticket status"><?php foreach ($tabs as $k => $label): ?>
  <a href="<?= url('admin/tickets?' . http_build_query(array_filter(['event' => $eid, 'status' => $k, 'q' => $q]))) ?>"<?= $status === $k ? ' aria-current="true"' : '' ?>><?= $label ?> (<?= (int)($counts[$k] ?? 0) ?>)</a>
<?php endforeach; ?></nav>

<div class="panel">
<?php if (!$ev): ?><div class="empty">There are no events yet. Create one first.</div>
<?php elseif (!$list): ?><div class="empty"><?= $q !== '' ? 'No ' . h(strtolower($tabs[$status])) . ' match “' . h($q) . '”.' : 'Nothing here yet for this event.' ?></div>
<?php else: ?>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Reference</th><th>Guest</th><th>Booking</th><th>Ticket</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($list as $r):
      $isConfirmed = $r['status'] === 'confirmed';
      $tUrl = $isConfirmed ? ticket_url((int)$r['id']) : null;
      $wa = $isConfirmed && $r['phone'] !== 'N/A' ? wa_link($r['phone'], ticket_message(reservation_with_event((int)$r['id']))) : null; ?>
    <tr>
      <td><a href="<?= url('admin/reservations/' . $r['id']) ?>"><b><?= h($r['reference']) ?></b></a></td>
      <td><b><?= h($r['full_name']) ?></b><br><span class="small muted"><?= h($r['phone']) ?><?= $r['email'] ? ' · ' . h($r['email']) : '' ?></span></td>
      <td><?= h(str_replace(' Reservation', '', RES_TYPES[$r['type']])) ?><?= $r['package'] ? ' · ' . h($r['package']) : '' ?><br><span class="small muted"><?= (int)$r['guests'] ?> guest(s)<?= $r['admin_note'] && str_starts_with((string)$r['admin_note'], 'Ticket issued') ? ' · complimentary' : '' ?></span></td>
      <td><?php if ($isConfirmed): ?><?= $r['checked_in_at'] ? '<span class="badge badge-warn">Checked in ' . h(fmt_time($r['checked_in_at'])) . '</span>' : '<span class="badge badge-ok">Valid</span>' ?>
          <?php elseif ($r['status'] === 'pending'): ?><span class="badge badge-warn">Not issued yet</span><?php else: ?><span class="badge badge-err">Void</span><?php endif; ?></td>
      <td><div class="actions">
        <?php if ($isConfirmed): ?>
          <a class="btn btn-ghost btn-sm" href="<?= h($tUrl) ?>" target="_blank" rel="noopener">Open</a>
          <a class="btn btn-ghost btn-sm" href="<?= h($tUrl . '?download=1') ?>" target="_blank" rel="noopener">Download</a>
          <?php if ($wa): ?><a class="btn btn-primary btn-sm" href="<?= h($wa) ?>" target="_blank" rel="noopener">WhatsApp</a><?php endif; ?>
          <?php if ($r['email'] && $manage): ?><form class="inline" method="post" action="<?= url('admin/reservations/' . $r['id'] . '/action') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="send_ticket"><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-ghost btn-sm">Email</button></form><?php endif; ?>
          <form class="inline" method="post" action="<?= url('admin/tickets/' . $r['id'] . '/regenerate') ?>" onsubmit="return confirm('Create a new ticket for this guest? The old QR code and link will stop working.')"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-ghost btn-sm" title="Replaces the secret link and QR code">New link</button></form>
        <?php elseif ($r['status'] === 'pending' && $manage): ?>
          <form class="inline" method="post" action="<?= url('admin/reservations/' . $r['id'] . '/action') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-primary btn-sm">Approve &amp; issue ticket</button></form>
        <?php else: ?><span class="small muted">—</span><?php endif; ?>
      </div></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
</div>

<div class="panel" id="issue">
  <div class="panel-head"><h2>＋ Issue a ticket</h2><span class="muted small">For VIPs, sponsors, staff or walk-ins. Creates a confirmed free booking and its ticket.</span></div>
  <form method="post" action="<?= url('admin/tickets/issue') ?>"><?= csrf_field() ?>
    <div class="form-grid">
      <div class="field"><label for="i_event">Event</label><select id="i_event" name="event_id" required><?php foreach ($events as $x): ?><option value="<?= $x['id'] ?>"<?= $eid === (int)$x['id'] ? ' selected' : '' ?>><?= h(excerpt($x['title'], 60)) ?> · <?= fmt_date($x['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="i_type">Ticket type</label><select id="i_type" name="type"><?php foreach (RES_TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $k === 'vip' ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></div>
      <div class="field"><label for="i_name">Guest name</label><input id="i_name" name="full_name" required maxlength="140"></div>
      <div class="field"><label for="i_guests">Number of guests</label><input id="i_guests" name="guests" type="number" min="1" max="100" value="1" required></div>
      <div class="field"><label for="i_phone">Phone (optional)</label><input id="i_phone" name="phone" type="tel" placeholder="+256 7XX XXX XXX"></div>
      <div class="field"><label for="i_email">Email (optional, we email the ticket)</label><input id="i_email" name="email" type="email"></div>
    </div>
    <div class="field"><label for="i_note">Internal note (optional)</label><input id="i_note" name="note" maxlength="150" placeholder="e.g. Sponsor guest, Johnnie Walker"></div>
    <button class="btn btn-primary">Create ticket</button>
  </form>
</div>
