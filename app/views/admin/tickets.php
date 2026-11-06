<?php
$eid = (int)($ev['id'] ?? 0);
$back = 'admin/tickets?' . http_build_query(array_filter(['event' => $eid ?: '', 'status' => $status !== 'confirmed' ? $status : '', 'q' => $q]));
$tabs = ['confirmed' => 'Tickets issued', 'pending' => 'Awaiting approval', 'withdrawn' => 'Withdrawn', 'cancelled' => 'Cancelled'];
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
  <div class="tbl-wrap"><table class="tbl tk-table"><thead><tr><th>Reference</th><th>Guest</th><th>Booking</th><th>Ticket</th><th>Actions</th></tr></thead><tbody>
  <?php foreach ($list as $r):
      $isConfirmed = $r['status'] === 'confirmed';
      $isWithdrawn = $r['status'] === 'cancelled' && !empty($r['withdrawn_at']);
      $canManage = $isConfirmed || $r['status'] === 'pending';
      $full = ($isConfirmed || $isWithdrawn) ? reservation_with_event((int)$r['id']) : null;
      $tUrl = $isConfirmed ? ticket_url((int)$r['id']) : null;
      $hasPhone = $r['phone'] !== 'N/A' && $r['phone'] !== '';
      $wa = $isConfirmed && $hasPhone ? wa_link($r['phone'], ticket_message($full)) : ($isWithdrawn && $hasPhone ? wa_link($r['phone'], ticket_withdrawn_message($full)) : null);
      $act = fn(string $path) => url('admin/tickets/' . $r['id'] . '/' . $path); ?>
    <tr>
      <td data-label="Reference"><a href="<?= url('admin/reservations/' . $r['id']) ?>"><b><?= h($r['reference']) ?></b></a></td>
      <td data-label="Guest"><b><?= h($r['full_name']) ?></b><br><span class="small muted"><?= h($r['phone']) ?><?= $r['email'] ? ' · ' . h($r['email']) : '' ?></span></td>
      <td data-label="Booking"><?= h(str_replace(' Reservation', '', RES_TYPES[$r['type']])) ?><?= $r['package'] ? ' · ' . h($r['package']) : '' ?><br><span class="small muted"><?= (int)$r['guests'] ?> guest(s)<?= $r['admin_note'] && str_starts_with((string)$r['admin_note'], 'Ticket issued') ? ' · complimentary' : '' ?></span></td>
      <td data-label="Ticket"><?php if ($isConfirmed): ?><?= $r['checked_in_at'] ? '<span class="badge badge-warn">Checked in ' . h(fmt_time($r['checked_in_at'])) . '</span>' : '<span class="badge badge-ok">Valid</span>' ?>
          <?php elseif ($isWithdrawn): ?><span class="badge badge-err">Withdrawn</span><br><span class="small muted"><?= h(fmt_date($r['withdrawn_at'], 'j M, H:i')) ?><?= $r['withdrawn_reason'] ? ' · ' . h($r['withdrawn_reason']) : '' ?></span>
          <?php elseif ($r['status'] === 'pending'): ?><span class="badge badge-warn">Not issued yet</span><?php else: ?><span class="badge badge-err">Void</span><?php endif; ?></td>
      <td class="tk-cell" data-label="Actions"><div class="tk-actions" data-tk>
        <?php if ($isConfirmed): ?>
          <div class="tk-row tk-share">
            <a class="btn btn-ghost btn-sm" href="<?= h($tUrl) ?>" target="_blank" rel="noopener">Open</a>
            <a class="btn btn-ghost btn-sm" href="<?= h($tUrl . '?download=1') ?>" target="_blank" rel="noopener">Download</a>
            <?php if ($wa): ?><a class="btn btn-wa btn-sm" href="<?= h($wa) ?>" target="_blank" rel="noopener" title="Open WhatsApp with the ticket link ready to send">💬 WhatsApp</a><?php endif; ?>
            <?php if ($r['email']): ?><form class="inline" method="post" action="<?= $act('email') ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-primary btn-sm" title="Email the ticket to <?= h($r['email']) ?>">✉️ Email</button></form><?php endif; ?>
          </div>
        <?php elseif ($isWithdrawn): ?>
          <div class="tk-row tk-share">
            <?php if ($wa): ?><a class="btn btn-wa btn-sm" href="<?= h($wa) ?>" target="_blank" rel="noopener" title="Tell the guest on WhatsApp">💬 Notify</a><?php endif; ?>
            <?php if ($r['email']): ?><form class="inline" method="post" action="<?= $act('email') ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-ghost btn-sm">✉️ Email notice</button></form><?php endif; ?>
            <form class="inline" method="post" action="<?= $act('reinstate') ?>" onsubmit="return confirm('Reinstate this ticket? The guest link and QR code will work again.')"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-primary btn-sm">↩ Reinstate</button></form>
          </div>
        <?php elseif ($r['status'] === 'pending' && $manage): ?>
          <div class="tk-row tk-share"><form class="inline" method="post" action="<?= url('admin/reservations/' . $r['id'] . '/action') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-primary btn-sm">Approve &amp; issue ticket</button></form></div>
        <?php endif; ?>
        <?php if ($canManage): ?>
          <div class="tk-row tk-manage">
            <button type="button" class="btn btn-ghost btn-sm" data-tk-open="update" aria-expanded="false">✏️ Update</button>
            <?php if ($isConfirmed): ?><form class="inline" method="post" action="<?= $act('regenerate') ?>" onsubmit="return confirm('Create a new ticket for this guest? The old QR code and link will stop working.')"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-ghost btn-sm" title="Replaces the secret link and QR code">🔗 New link</button></form><?php endif; ?>
            <button type="button" class="btn btn-ghost btn-sm btn-danger-soft" data-tk-open="withdraw" aria-expanded="false">⛔ Withdraw</button>
          </div>
          <form class="tk-form" data-tk-panel="update" hidden method="post" action="<?= $act('update') ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>">
            <b class="tk-form-title">Update ticket</b>
            <div class="tk-grid">
              <label>Guest name <input name="full_name" value="<?= h($r['full_name']) ?>" required maxlength="140"></label>
              <label>Phone <input name="phone" type="tel" value="<?= $hasPhone ? h($r['phone']) : '' ?>"></label>
              <label>Email <input name="email" type="email" value="<?= h((string)$r['email']) ?>"></label>
              <label>Guests <input name="guests" type="number" min="1" max="100" value="<?= (int)$r['guests'] ?>" required></label>
              <label>Ticket type <select name="type"><?php foreach (RES_TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $r['type'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
              <label>Table package <input name="package" value="<?= h((string)$r['package']) ?>" maxlength="80"></label>
            </div>
            <label class="chk"><input type="checkbox" name="notify" value="1"> Email the guest the updated ticket</label>
            <p class="small muted">The ticket link and QR code stay the same and show the new details straight away.</p>
            <div class="tk-form-actions"><button class="btn btn-primary btn-sm">Save changes</button><button type="button" class="btn btn-ghost btn-sm" data-tk-close>Cancel</button></div>
          </form>
          <form class="tk-form tk-form-danger" data-tk-panel="withdraw" hidden method="post" action="<?= $act('withdraw') ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>">
            <b class="tk-form-title">Withdraw ticket</b>
            <p class="small">The ticket link and QR code will show <b>Withdrawn</b> straight away and the door will refuse it. You can reinstate it later.</p>
            <label>Reason (optional) <input name="reason" maxlength="200" placeholder="e.g. Payment bounced, duplicate booking"></label>
            <?php if ($r['email']): ?><label class="chk"><input type="checkbox" name="notify" value="1" checked> Email the guest that it was withdrawn</label><?php endif; ?>
            <div class="tk-form-actions"><button class="btn btn-primary btn-sm tk-withdraw-btn">Withdraw ticket</button><button type="button" class="btn btn-ghost btn-sm" data-tk-close>Cancel</button></div>
          </form>
        <?php elseif (!$isWithdrawn): ?><span class="small muted">—</span><?php endif; ?>
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

<script>
// Update / Withdraw panels: one opens at a time under the buttons of its own row
document.addEventListener('click', function (e) {
  var open = e.target.closest('[data-tk-open]'), close = e.target.closest('[data-tk-close]');
  var box = (open || close) && (open || close).closest('[data-tk]');
  if (!box) return;
  var panels = box.querySelectorAll('[data-tk-panel]'), btns = box.querySelectorAll('[data-tk-open]');
  var want = open ? open.dataset.tkOpen : null;
  panels.forEach(function (p) { p.hidden = !(want && p.dataset.tkPanel === want && p.hidden); });
  btns.forEach(function (b) { var on = want && b.dataset.tkOpen === want && !box.querySelector('[data-tk-panel="' + want + '"]').hidden; b.setAttribute('aria-expanded', on ? 'true' : 'false'); });
});
</script>
