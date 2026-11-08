<?php
$eid = (int)($ev['id'] ?? 0);
$back = 'admin/tickets?' . http_build_query(array_filter(['event' => $eid ?: '', 'status' => $status !== 'confirmed' ? $status : '', 'q' => $q, 'package' => $pkg]));
$tabs = ['confirmed' => 'Tickets issued', 'pending' => 'Awaiting approval', 'withdrawn' => 'Withdrawn', 'cancelled' => 'Cancelled'];
$manage = can('reservations_manage');
?>
<div class="adm-head">
  <div><h1>Tickets</h1><p class="adm-sub">Every confirmed booking has a ticket with a QR code. Find a guest, then open it, download it, send it, or issue a new one.</p></div>
  <div class="btn-row">
    <?php if ($ev): ?><a class="btn btn-outline" href="<?= url('admin/tickets/print?event=' . $eid) ?>" target="_blank" rel="noopener">🖨️ Print all tickets</a><?php endif; ?>
    <a class="btn btn-outline" href="<?= url('admin/gen-tickets') ?>">🖨️ Printable general tickets</a>
    <a class="btn btn-primary" href="#issue">＋ Issue a ticket</a>
  </div>
</div>

<form class="panel filters" method="get" action="<?= url('admin/tickets') ?>">
  <label>Event <select name="event" onchange="this.form.submit()"><?php foreach ($events as $x): ?><option value="<?= $x['id'] ?>"<?= $eid === (int)$x['id'] ? ' selected' : '' ?>><?= h(excerpt($x['title'], 56)) ?> · <?= fmt_date($x['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></label>
  <label>Table package <?= package_select($package_opts, $pkg, true) ?></label>
  <label style="flex:1;min-width:220px">Find a guest <input type="search" name="q" value="<?= h($q) ?>" placeholder="Name, phone or reference"></label>
  <input type="hidden" name="status" value="<?= h($status) ?>">
  <button class="btn btn-primary btn-sm">Search</button>
  <?php if ($q !== '' || $pkg !== ''): ?><a class="btn btn-ghost btn-sm" href="<?= url('admin/tickets?event=' . $eid . '&status=' . $status) ?>">Clear</a><?php endif; ?>
</form>
<?php if ($pkg !== '' && $list): $pkgGuests = array_sum(array_map(fn($x) => (int)$x['guests'], $list)); ?>
<p class="small" style="margin:-4px 0 10px"><b><?= h($pkg === '__none' ? 'No table package' : $pkg) ?></b>: <?= count($list) ?> ticket<?= count($list) === 1 ? '' : 's' ?> · <?= $pkgGuests ?> guest<?= $pkgGuests === 1 ? '' : 's' ?> in this view.</p>
<?php endif; ?>

<nav class="pills" aria-label="Ticket status"><?php foreach ($tabs as $k => $label): ?>
  <a href="<?= url('admin/tickets?' . http_build_query(array_filter(['event' => $eid, 'status' => $k, 'q' => $q, 'package' => $pkg]))) ?>"<?= $status === $k ? ' aria-current="true"' : '' ?>><?= $label ?> (<?= (int)($counts[$k] ?? 0) ?>)</a>
<?php endforeach; ?></nav>

<?php if (env('MAIL_ENABLED', '0') !== '1'): ?><div class="alert alert-err">Email is switched off on this site (MAIL_ENABLED is not 1), so &ldquo;Emailed&rdquo; marks only mean the email was written to the log, not delivered. Switch it on in the .env file.</div><?php endif; ?>
<?php if ($ev && $status === 'confirmed' && $list): ?>
<details class="panel tk-bulk"><summary>✔ Mark tickets that were already sent</summary>
  <form method="post" action="<?= url('admin/tickets/mark-all') ?>" onsubmit="return confirm('Mark every confirmed ticket of this event that has no send recorded as already sent?')"><?= csrf_field() ?>
    <input type="hidden" name="event_id" value="<?= (int)$eid ?>">
    <p class="small muted" style="margin:6px 0 10px">For tickets you sent before this list started recording sends. Every confirmed ticket of <b><?= h(excerpt($ev['title'], 50)) ?></b> that has an email or phone number and no send recorded yet gets the mark. Each ticket then shows a <b>Resend</b> button.</p>
    <div class="btn-row"><button class="btn btn-primary btn-sm" name="channel" value="both">Mark as emailed and sent on WhatsApp</button><button class="btn btn-ghost btn-sm" name="channel" value="email">Only emailed</button><button class="btn btn-ghost btn-sm" name="channel" value="whatsapp">Only WhatsApp</button></div>
  </form></details>
<?php endif; ?>
<?php if (!empty($GLOBALS['tk_sends_error'])): ?><div class="alert alert-err">Sent marks could not be saved on this site: <?= h((string)$GLOBALS['tk_sends_error']) ?>. Press Settings &rarr; Update database, then try again.</div><?php endif; ?>
<style>.tk-edit-num{border:0;background:none;cursor:pointer;font-size:.85rem;padding:0 2px}.tk-wa-preview{margin:6px 0 0;font-weight:600;color:#16632c}.tk-markform{display:inline-block;margin:4px 6px 0 0}.tk-sentmarks{margin-top:8px}.tk-mark{font-size:.78rem;color:#16632c;font-weight:600;margin-top:5px;line-height:1.5}.tk-mark form{display:inline}.tk-unsent{color:var(--muted);font-weight:500}
.tk-resend{padding:2px 9px!important;font-size:.72rem!important;margin-left:4px}.tk-bulk{padding:12px 18px}.tk-bulk summary{cursor:pointer;font-weight:700}</style>
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
      <td data-label="Guest"><b><?= h($r['full_name']) ?></b><br><span class="small muted"><?= h($r['phone']) ?><?= $r['email'] ? ' · ' . h($r['email']) : '' ?></span><?php if ($canManage): ?> <button type="button" class="tk-edit-num" data-tk-jump title="Change this number or email">✏️</button><?php endif; ?></td>
      <td data-label="Booking"><?= h(str_replace(' Reservation', '', RES_TYPES[$r['type']])) ?><?= $r['package'] ? ' · ' . h($r['package']) : '' ?><br><span class="small muted"><?= (int)$r['guests'] ?> guest(s)<?= $r['admin_note'] && str_starts_with((string)$r['admin_note'], 'Ticket issued') ? ' · complimentary' : '' ?></span></td>
      <td data-label="Ticket"><?php if ($isConfirmed): ?><?= $r['checked_in_at'] ? '<span class="badge badge-warn">Checked in ' . h(fmt_time($r['checked_in_at'])) . '</span>' : '<span class="badge badge-ok">Valid</span>' ?>
          <?php if ($r['checked_in_at'] && can('checkin')): ?><form class="inline" method="post" action="<?= url('admin/reservations/' . (int)$r['id'] . '/action') ?>" onsubmit="return confirm('Undo the check-in for <?= h($r['full_name']) ?>? The ticket becomes valid again.')"><?= csrf_field() ?><input type="hidden" name="action" value="undo_checkin"><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-ghost btn-sm tk-resend" title="Undo the check-in">↩ Undo</button></form><?php endif; ?>
          <?php elseif ($isWithdrawn): ?><span class="badge badge-err">Withdrawn</span><br><span class="small muted"><?= h(fmt_date($r['withdrawn_at'], 'j M, H:i')) ?><?= $r['withdrawn_reason'] ? ' · ' . h($r['withdrawn_reason']) : '' ?></span>
          <?php elseif ($r['status'] === 'pending'): ?><span class="badge badge-warn">Not issued yet</span><?php else: ?><span class="badge badge-err">Void</span><?php endif; ?>
        <?php if ($isConfirmed): $sm = $sends[(int)$r['id']] ?? []; ?>
          <div class="tk-sentmarks" data-sent-for="<?= (int)$r['id'] ?>">
            <?php if (!empty($sm['email'])): ?><div class="tk-mark">✔ ✉️ Emailed · <?= h(fmt_date($sm['email']['at'], 'j M H:i')) ?><?= $sm['email']['n'] > 1 ? ' · ' . $sm['email']['n'] . '×' : '' ?>
              <?php if ($r['email']): ?><form class="inline" method="post" action="<?= $act('email') ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-ghost btn-sm tk-resend" title="Email the ticket again to <?= h($r['email']) ?>">↻ Resend email</button></form><?php endif; ?></div><?php endif; ?>
            <?php if (!empty($sm['whatsapp'])): ?><div class="tk-mark">✔ 💬 WhatsApp · <?= h(fmt_date($sm['whatsapp']['at'], 'j M H:i')) ?><?= $sm['whatsapp']['n'] > 1 ? ' · ' . $sm['whatsapp']['n'] . '×' : '' ?>
              <?php if ($wa): ?><a class="btn btn-ghost btn-sm tk-resend" href="<?= h($wa) ?>" target="_blank" rel="noopener" data-wa-sent="<?= $act('sent') ?>" title="Open WhatsApp with the ticket link ready to send again">↻ Resend WhatsApp</a><?php endif; ?></div><?php endif; ?>
            <?php if (!$sm): ?><div class="tk-mark tk-unsent">Not sent yet</div><?php endif; ?>
            <?php if (empty($sm['whatsapp']) && $hasPhone) { ?><form class="inline tk-markform" method="post" action="<?= $act('sent') ?>"><?= csrf_field() ?><input type="hidden" name="channel" value="whatsapp"><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-ghost btn-sm tk-resend" title="Mark this ticket as already sent on WhatsApp">✔ Mark sent on WhatsApp</button></form><?php } ?>
            <?php if (empty($sm['email']) && $r['email']) { ?><form class="inline tk-markform" method="post" action="<?= $act('sent') ?>"><?= csrf_field() ?><input type="hidden" name="channel" value="email"><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-ghost btn-sm tk-resend" title="Mark this ticket as already emailed">✔ Mark sent by email</button></form><?php } ?>
          </div>
        <?php endif; ?></td>
      <td class="tk-cell" data-label="Actions"><div class="tk-actions" data-tk>
        <?php if ($isConfirmed): ?>
          <div class="tk-row tk-share">
            <a class="btn btn-ghost btn-sm" href="<?= h($tUrl) ?>" target="_blank" rel="noopener">Open</a>
            <a class="btn btn-ghost btn-sm" href="<?= h($tUrl . '?download=1') ?>" target="_blank" rel="noopener">Download</a>
            <button type="button" class="btn btn-ghost btn-sm" data-tk-open="update" aria-expanded="false" title="Change the WhatsApp number, email or name used to send this ticket">✏️ Edit details</button>
            <?php $smx = $sends[(int)$r['id']] ?? []; ?>
            <?php if ($wa && empty($smx['whatsapp'])): ?><a class="btn btn-wa btn-sm" href="<?= h($wa) ?>" target="_blank" rel="noopener" data-wa-sent="<?= $act('sent') ?>" title="Open WhatsApp with the ticket link ready to send">💬 WhatsApp</a><?php endif; ?>
            <?php if ($r['email'] && empty($smx['email'])): ?><form class="inline" method="post" action="<?= $act('email') ?>"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-primary btn-sm" title="Email the ticket to <?= h($r['email']) ?>">✉️ Email</button></form><?php endif; ?>
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
              <label>WhatsApp number <small>(used to send the ticket)</small> <input name="phone" type="tel" inputmode="tel" value="<?= $hasPhone ? h($r['phone']) : '' ?>" placeholder="0772 123 456" data-wa-num></label>
              <label>Email <small>(used to email the ticket)</small> <input name="email" type="email" value="<?= h((string)$r['email']) ?>"></label>
              <label>Guest name <input name="full_name" value="<?= h($r['full_name']) ?>" required maxlength="140"></label>
              <label>Guests <input name="guests" type="number" min="1" max="100" value="<?= (int)$r['guests'] ?>" required></label>
              <label>Ticket type <select name="type"><?php foreach (RES_TYPES as $k => $l): ?><option value="<?= $k ?>"<?= $r['type'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
              <label>Table package <input name="package" value="<?= h((string)$r['package']) ?>" maxlength="80"></label>
            </div>
            <p class="small tk-wa-preview" data-wa-preview aria-live="polite"></p>
            <label class="chk"><input type="checkbox" name="notify" value="1"> Email the guest the updated ticket</label>
            <p class="small muted">The ticket link and QR code stay the same and show the new details straight away. If you change the WhatsApp number or email, that channel goes back to &ldquo;not sent yet&rdquo; so you can send to the new one.</p>
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
      <div class="field"><label for="i_pkg">Table package</label><select id="i_pkg" name="package"></select><small class="help" id="i_pkghelp"></small></div>
      <div class="field" id="i_paywrap" hidden><label for="i_pay">Payment for the package</label><select id="i_pay" name="pay"><option value="free">Complimentary (no charge)</option><option value="cash">Paid in cash</option><option value="mobile_money">Paid by mobile money</option><option value="bank">Paid by bank</option></select></div>
      <div class="field"><label for="i_name">Guest name</label><input id="i_name" name="full_name" required maxlength="140"></div>
      <div class="field"><label for="i_guests">Number of guests</label><input id="i_guests" name="guests" type="number" min="1" max="100" value="1" required></div>
      <div class="field"><label for="i_phone">Phone (optional)</label><input id="i_phone" name="phone" type="tel" placeholder="+256 7XX XXX XXX"></div>
      <div class="field"><label for="i_email">Email (optional, we email the ticket)</label><input id="i_email" name="email" type="email"></div>
    </div>
    <div class="field"><label for="i_note">Internal note (optional)</label><input id="i_note" name="note" maxlength="150" placeholder="e.g. Sponsor guest, Johnnie Walker"></div>
    <button class="btn btn-primary">Create ticket</button>
  </form>
</div>
<?php $pkgMap = []; foreach ($events as $x) $pkgMap[(int)$x['id']] = array_map(fn($p) => ['name' => $p['name'], 'price' => (int)$p['price'], 'admits' => (int)($p['admits'] ?? 0)], event_packages((int)$x['id'])); ?>
<script>
(function () {
  var map = <?= json_encode((object)$pkgMap) ?>, ev = document.getElementById('i_event'), pk = document.getElementById('i_pkg'), ty = document.getElementById('i_type'), pw = document.getElementById('i_paywrap'), help = document.getElementById('i_pkghelp');
  if (!ev || !pk) return;
  function fill() {
    var list = map[ev.value] || []; pk.innerHTML = '';
    var none = document.createElement('option'); none.value = ''; none.textContent = list.length ? 'No package' : 'No packages set for this event'; pk.appendChild(none);
    list.forEach(function (p) { var o = document.createElement('option'); o.value = p.name; o.textContent = p.name + ' · UGX ' + p.price.toLocaleString() + (p.admits ? ' · admits ' + p.admits : ''); o.dataset.admits = p.admits || 0; pk.appendChild(o); });
    pk.disabled = !list.length; sync();
  }
  function sync() {
    var on = pk.value !== ''; pw.hidden = !on;
    if (on) {
      ty.value = 'table'; var ad = +pk.options[pk.selectedIndex].dataset.admits || 0, g = document.getElementById('i_guests');
      if (ad && g) g.value = ad;
      help.textContent = 'The ticket shows this package' + (ad ? ' and admits ' + ad + ' people' : '') + '. Choose how it was paid, or leave it complimentary.';
    } else help.textContent = '';
  }
  ev.addEventListener('change', fill); pk.addEventListener('change', sync); fill();
})();
</script>

<script>
// Pressing a WhatsApp button counts as sending the ticket: remember it and show the mark straight away
document.addEventListener('click', function (e) {
  var a = e.target.closest('[data-wa-sent]'); if (!a || !window.fetch) return;
  var fd = new FormData(); fd.append('_csrf', <?= json_encode(csrf_token()) ?>); fd.append('channel', 'whatsapp');
  fetch(a.dataset.waSent, { method: 'POST', body: fd, keepalive: true }).then(function (r) { return r.json(); }).then(function (j) {
    var row = a.closest('tr'), box = row && row.querySelector('.tk-sentmarks'); if (!box || !j.ok) return;
    var un = box.querySelector('.tk-unsent'); if (un) un.remove();
    var old = box.querySelector('.tk-wa-now'); if (old) old.remove();
    var d = document.createElement('div'); d.className = 'tk-mark tk-wa-now'; d.textContent = '✔ 💬 WhatsApp · ' + j.at + ' ';
    var again = a.cloneNode(false); again.className = 'btn btn-ghost btn-sm tk-resend'; again.textContent = '↻ Resend WhatsApp'; d.appendChild(again); box.appendChild(d);
    box.querySelectorAll('.tk-markform').forEach(function (f) { if (f.querySelector('[value=whatsapp]')) f.remove(); });
    if (a.classList.contains('btn-wa')) a.remove();
    else if (a.classList.contains('tk-resend') && !a.closest('.tk-wa-now')) { var m = a.closest('.tk-mark'); if (m) m.firstChild.nodeValue = '✔ 💬 WhatsApp · ' + j.at + ' '; }
  }).catch(function () { alert('The WhatsApp send could not be recorded. Check your connection and use the Mark sent button.'); });
});
// Pencil next to a number opens the ticket editor and puts the cursor in the WhatsApp number box
document.addEventListener('click', function (e) {
  var p = e.target.closest('[data-tk-jump]'); if (!p) return;
  var row = p.closest('tr'), open = row && row.querySelector('[data-tk-open="update"]'); if (!open) return;
  open.click(); var f = row.querySelector('[data-wa-num]'); if (f) { f.focus(); f.select(); }
});
// Show where WhatsApp will open for the number being typed
function waPreview(inp) {
  var out = inp.closest('form').querySelector('[data-wa-preview]'); if (!out) return;
  var d = inp.value.replace(/\D/g, ''); if (d.charAt(0) === '0') d = '256' + d.slice(1);
  out.textContent = d.length >= 11 ? 'WhatsApp will open a chat with +' + d : (inp.value.trim() ? 'This number looks too short.' : 'No WhatsApp number yet.');
}
document.addEventListener('input', function (e) { if (e.target.matches('[data-wa-num]')) waPreview(e.target); });
document.addEventListener('click', function (e) { var b = e.target.closest('[data-tk-open="update"]'); if (b) setTimeout(function () { var f = b.closest('[data-tk]').querySelector('[data-wa-num]'); if (f) waPreview(f); }, 0); });
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
