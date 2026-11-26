<?php if (!empty($wgap) && can('payments')): $back = $back ?? 'dash'; ?>
<div class="panel wallet-rec pay-health">
  <div class="panel-head"><h2>🏦 Daraza wallet and who paid</h2><a class="small" href="<?= url($back === 'dash' ? 'admin?refresh=1' : 'admin/collections?refresh=1') ?>">Refresh balance</a></div>
  <div class="ph-grid">
    <div><b><?= h(money($wgap['balance'])) ?></b><span>available in the wallet now</span></div>
    <div><b><?= h(money($wgap['recorded'])) ?></b><span>online payments recorded</span></div>
    <div><b><?= h(money($wgap['withdrawn'])) ?></b><span>already withdrawn</span></div>
    <div><b style="color:<?= $wgap['gap'] > 0 ? '#d9534f' : '#1f8a4c' ?>"><?= h(money($wgap['gap'])) ?></b><span>in the wallet but not recorded yet</span></div>
  </div>
  <?php if ($wgap['gap'] > 0): ?>
  <?php if (!empty($wcand)): ?>
  <h3 class="ev-sub" style="margin:14px 0 6px">1. Match it to a payment someone started on the website</h3>
  <p class="small muted" style="margin:0 0 8px">These are online payments that were started but never marked as received. If the money is in your wallet, press <b>Money received</b> beside the right person. It is then filed on their booking (or payment page) with their name, phone, event and time, and the ticket is sent when the booking is paid in full. Check the amount and phone against your Daraza statement first.</p>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Started</th><th>Who</th><th>For</th><th>Amount</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($wcand as $c): ?><tr><td><?= h(fmt_date($c['created_at'], 'j M, g:i A')) ?></td>
    <td><b><?= h($c['full_name'] ?: ($c['payer_name'] ?: 'Unknown')) ?></b><br><span class="small muted"><?= h($c['phone']) ?></span></td>
    <td><?= $c['reference'] ? h($c['reference']) . '<br><span class="small muted">' . h(excerpt((string)$c['ev_title'], 36)) . '</span>' : h((string)($c['tab_title'] ?: '')) ?></td>
    <td><b><?= h(money((int)$c['amount'])) ?></b></td>
    <td><span class="pill" style="background:<?= $c['status'] === 'failed' ? '#d9534f' : '#7a5cc7' ?>;color:#fff"><?= $c['status'] === 'failed' ? 'Marked failed' : 'Waiting' ?></span></td>
    <td><?php if ((int)$c['amount'] <= $wgap['gap']): ?><form class="inline" method="post" action="<?= url('admin/pay-attempts/' . (int)$c['id'] . '/match') ?>" onsubmit="return confirm('Only press OK if this money is really in the Daraza wallet.')"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>"><button class="btn btn-primary btn-sm">Money received</button></form><?php else: ?><span class="small muted">Bigger than the unrecorded money</span><?php endif; ?></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <h3 class="ev-sub" style="margin:16px 0 6px">2. Or record someone who is not in that list</h3>
  <?php endif; ?>
  <p class="ph-warn">This money reached your wallet without going through a booking or payment page here, so the system does not know who sent it. Enter each person's name and phone (from your Daraza statement or the MoMo message) and it is added to that event's collections and the dashboard.</p>
  <form method="post" action="<?= url('admin/collections/record-wallet') ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>">
    <label>Name of payer<input name="payer_name" required maxlength="140" autocomplete="off"></label>
    <label>Phone of payer<input name="payer_phone" required placeholder="0772 123 456" inputmode="tel" autocomplete="off"></label>
    <label>Which event is it for?<select name="event_id" required><?php foreach (rows('SELECT id, title, start_at FROM events ORDER BY ABS(DATEDIFF(start_at, NOW())) ASC LIMIT 20') as $ev): ?><option value="<?= (int)$ev['id'] ?>"><?= h(fmt_date($ev['start_at'], 'j M Y')) ?> · <?= h(excerpt($ev['title'], 50)) ?></option><?php endforeach; ?></select></label>
    <label>Amount (UGX)<input name="amount" required inputmode="numeric" value="<?= (int)$wgap['gap'] ?>" autocomplete="off"></label>
    <label>Booking reference (optional)<input name="reference" placeholder="CB-XXXXXX" autocomplete="off"></label>
    <div><button class="btn btn-primary">Record payment</button></div>
  </form>
  <?php else: ?><p class="ph-ok">Every shilling in the wallet is recorded with the payer's details.</p><?php endif; ?>
</div>
<?php endif; ?>
