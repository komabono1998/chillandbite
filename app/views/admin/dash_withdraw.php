<?php
$bal = ($wallet && $wallet['balance'] !== null) ? (int)$wallet['balance'] : null;
$tone = ['success' => ['✅ Sent', '#1f8a4c'], 'failed' => ['❌ Failed', '#d9534f'], 'pending' => ['⏳ Sending', '#d99a1c'], 'unconfirmed' => ['⚠️ Needs checking', '#7a5cc7']];
?>
<?php if (!$daraza_on): ?>
  <div class="panel"><h2>Withdraw to mobile money</h2><p class="muted">Online payments are not switched on yet. Add <code>DARAZA_API_KEY</code> to the <code>.env</code> file on the server, then come back here.</p></div>
<?php else: ?>
<div class="wd-hero">
  <div class="wd-bal"><span>Available to transfer now</span><b id="wdBal" data-bal="<?= $bal ?? 0 ?>"><?= $bal !== null ? h(money($bal)) : '—' ?></b>
    <small>Your Daraza wallet balance<?php if ($wallet && $wallet['error']): ?> · <span style="color:#ffb4a8"><?= h($wallet['error']) ?></span><?php endif; ?> · <a href="<?= url('admin?tab=withdraw&refresh=1') ?>">Refresh</a></small></div>
  <div class="wd-stats">
    <div><b><?= h(money($online_in - $online_refunds)) ?></b><span>Collected online (MTN + Airtel)</span></div>
    <div><b><?= h(money($withdrawn)) ?></b><span>Already withdrawn</span></div>
    <?php if ($wd_pending): ?><div><b><?= h(money($wd_pending)) ?></b><span>Sending / needs checking</span></div><?php endif; ?>
  </div>
</div>
<?php if ($cash_in): ?><p class="small muted" style="margin:0 0 12px">Cash and bank payments you recorded by hand (<?= h(money($cash_in)) ?>) are not in the wallet, so they cannot be sent from here.</p><?php endif; ?>

<form class="panel wd-form wd-big" method="post" action="<?= url('admin/collections/withdraw') ?>" id="wdForm" onsubmit="return confirm('This sends REAL money from your Daraza wallet to the number entered. Continue?')"><?= csrf_field() ?><input type="hidden" name="back" value="dash">
  <h2>📲 Send money to mobile money</h2>
  <div class="form-grid">
    <label>Send to (MTN or Airtel number)<input name="phone" id="wdPhone" placeholder="0772 123 456" inputmode="tel" required autocomplete="off">
      <?php if (!empty($recent_numbers)): ?><span class="wd-nums"><small class="muted">Used before:</small><?php foreach ($recent_numbers as $ph): ?><button type="button" class="ev-chip" data-fill="<?= h($ph) ?>"><?= h($ph) ?></button><?php endforeach; ?></span><?php endif; ?></label>
    <label>Amount (UGX)<input name="amount" id="wdAmount" inputmode="numeric" placeholder="e.g. 500000" required autocomplete="off">
      <span class="wd-nums"><button type="button" class="btn btn-ghost btn-sm" id="wdAll"<?= $bal ? '' : ' disabled' ?>>Transfer everything available</button><small class="muted">The fee is taken from the wallet too, so the button works out the most that can be sent.</small></span></label>
  </div>
  <p class="fee-line" id="wdFee">Enter an amount to see the withdrawal fee. Daraza takes the fee from your wallet on top of the amount sent. Each transfer is limited to <?= h(money(DARAZA_MAX)) ?>; send more than once for larger balances.</p>
  <div class="form-grid">
    <label>Your password (to confirm it is you)<input type="password" name="password" autocomplete="current-password" required></label>
    <label>Type WITHDRAW to confirm<input name="confirm" autocomplete="off" required></label>
  </div>
  <button class="btn btn-primary btn-lg">Send to mobile money</button>
  <p class="small muted" style="margin:8px 0 0">Only administrators can do this. Every transfer is saved below with its time and who sent it, and is limited to a few per hour for safety.</p>
</form>
<script>
(function () {
  var a = document.getElementById('wdAmount'), out = document.getElementById('wdFee'), all = document.getElementById('wdAll'), t = null;
  var bal = parseInt(document.getElementById('wdBal').dataset.bal, 10) || 0, MIN = <?= (int)DARAZA_MIN ?>, MAX = <?= (int)DARAZA_MAX ?>, feeUrl = <?= json_encode(url('admin/collections/fees')) ?>;
  function fee(v) { return fetch(feeUrl + '?amount=' + v, { cache: 'no-store' }).then(function (r) { return r.json(); }); }
  function show(v) {
    if (v < MIN) { out.textContent = ''; return; }
    fee(v).then(function (j) { out.textContent = j.ok ? 'Fee about UGX ' + j.fees.toLocaleString() + ' + tax UGX ' + j.tax.toLocaleString() + ' = UGX ' + j.total.toLocaleString() + ' leaves your wallet in total.' : (j.error || ''); }).catch(function () { out.textContent = ''; });
  }
  a.addEventListener('input', function () { clearTimeout(t); var v = parseInt(a.value.replace(/\D/g, ''), 10) || 0; t = setTimeout(function () { show(v); }, 450); });
  document.querySelectorAll('[data-fill]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById('wdPhone').value = b.dataset.fill; }); });
  if (all) all.addEventListener('click', function () {
    if (bal < MIN) { out.textContent = 'The wallet is below the minimum transfer of UGX ' + MIN.toLocaleString() + '.'; return; }
    out.textContent = 'Working out the most you can send...'; var cand = Math.min(bal, MAX), tries = 0, tries2 = false;
    (function step() {
      fee(cand).then(function (j) {
        if (!j.ok) { out.textContent = j.error || 'Could not get the fee.'; return; }
        if (j.total > bal && tries < 6) { cand = Math.max(MIN, cand - (j.total - bal)); tries++; return step(); }
        var left = bal - j.total;
        if (left > 2000 && cand < MAX && !tries2) {   // one more try: give back the unused part so almost nothing is left behind
          tries2 = true; var up = Math.min(MAX, cand + Math.floor(left * 0.92));
          return fee(up).then(function (k) { a.value = String(k.ok && k.total <= bal ? up : cand); show(k.ok && k.total <= bal ? up : cand); });
        }
        a.value = String(cand); show(cand);
        if (bal > MAX) out.textContent += ' (The wallet holds more than the UGX ' + MAX.toLocaleString() + ' limit for one transfer, so send this one first and then the rest.)';
      }).catch(function () { out.textContent = 'Could not get the fee.'; });
    })();
  });
})();
</script>

<?php $__wd_pending = false; foreach ($wds as $__w) if ($__w['status'] === 'pending') $__wd_pending = true; ?>
<?php if (!empty($__wd_pending)): ?><script>setTimeout(function () { location.reload(); }, 3000);</script><?php endif; ?>
<div class="panel"><div class="panel-head"><h2>Transfer history</h2><span class="muted small">Latest 40, newest first</span></div>
<?php if ($wds): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Date &amp; time</th><th>Sent to</th><th>Amount</th><th>Result</th><th>Sent by</th><th></th></tr></thead><tbody>
<?php foreach ($wds as $w): [$lab, $col] = $tone[$w['status']]; ?><tr>
  <td><?= h(fmt_date($w['created_at'], 'D j M Y')) ?><br><span class="small muted"><?= h(date('g:i:s A', strtotime($w['created_at']))) ?><?= $w['completed_at'] ? ' → ' . h(date('g:i:s A', strtotime($w['completed_at']))) : '' ?></span></td>
  <td><b><?= h($w['phone']) ?></b><br><span class="small muted"><?= (int)$w['method'] === 2 ? 'Airtel' : 'MTN' ?></span></td>
  <td><b><?= h(money($w['amount'])) ?></b></td>
  <td><span class="pill" style="background:<?= $col ?>;color:#fff"><?= h($lab) ?></span><br><span class="small muted"><?= h((string)$w['message']) ?><?= $w['request_id'] ? ' · ref ' . h($w['request_id']) : '' ?></span></td>
  <td><?= h($w['by_name'] ?: '') ?></td>
  <td><?php if ($w['status'] === 'unconfirmed'): ?><form class="inline" method="post" action="<?= url('admin/withdrawals/' . (int)$w['id'] . '/resolve') ?>"><?= csrf_field() ?><input type="hidden" name="back" value="dash">
    <button class="btn btn-primary btn-sm" name="choice" value="received" onclick="return confirm('Only choose this if the money really left your Daraza statement.')">Money was sent</button>
    <button class="btn btn-ghost btn-sm" name="choice" value="dismiss" onclick="return confirm('Choose this only if the money did NOT leave the wallet.')">Nothing was sent</button></form><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php else: ?><div class="empty">No transfers yet.</div><?php endif; ?></div>
<?php endif; ?>
