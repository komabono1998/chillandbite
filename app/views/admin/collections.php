<?php
$qs = $range === 'custom' ? ['from' => $r['from'], 'to' => $r['to']] : ['range' => $range];
if ($event) $qs['event'] = $event;
$ranges = ['today' => 'Today', '7' => '7 days', '30' => '30 days', '90' => '90 days', 'year' => 'This year'];
$order = ['daraza_mtn', 'daraza_airtel', 'mobile_money', 'cash', 'bank', 'pesapal'];
$inChannels = array_filter($order, fn($c) => isset($r['by'][$c]) || in_array($c, ['daraza_mtn', 'daraza_airtel', 'cash', 'bank', 'mobile_money'], true));
$refundTotal = 0; foreach (['refund', 'daraza_refund'] as $c) $refundTotal += -($r['by'][$c]['amt'] ?? 0);
$online = ($r['by']['daraza_mtn']['amt'] ?? 0) + ($r['by']['daraza_airtel']['amt'] ?? 0);
$maxCh = max(1, ...array_map(fn($c) => $r['by'][$c]['amt'] ?? 0, $order));
// stacked daily chart
$days = $r['daily']; $nd = count($days);
$maxDay = 1; foreach ($days as $d => $chs) $maxDay = max($maxDay, array_sum($chs));
$W = 760; $H = 200; $pl = 54; $pb = 26; $pt = 10; $cw = $W - $pl - 8; $chh = $H - $pb - $pt;
$bw = $nd ? max(3, min(34, $cw / $nd - 3)) : 10;
$chip = fn($c) => '<span class="ch-chip" style="--c:' . (PAY_CHANNELS[$c][1] ?? '#888') . '">' . h(PAY_CHANNELS[$c][0] ?? $c) . '</span>';
?>
<style>
.ch-chip{display:inline-flex;align-items:center;gap:6px;font-size:.78rem;font-weight:700;white-space:nowrap}.ch-chip::before{content:"";width:11px;height:11px;border-radius:4px;background:var(--c)}
.ch-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px;margin:14px 0}
.ch-card{background:#fff;border:1px solid var(--line);border-top:5px solid var(--c);border-radius:14px;padding:14px 16px;box-shadow:0 2px 8px rgba(30,20,10,.04)}
.ch-card small{display:block;color:var(--muted);font-size:.76rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em}
.ch-card b{display:block;font-size:1.35rem;margin:2px 0}.ch-card span{font-size:.78rem;color:var(--muted)}
.ch-card.neg b{color:#b3261e}.ch-total{border-top-color:#171717;background:#171717;color:#fff}.ch-total small,.ch-total span{color:#d9cfbf}
.wallet-box{display:flex;flex-wrap:wrap;gap:16px;align-items:center;justify-content:space-between}
.wd-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;align-items:end;margin-top:12px}
.wd-form label{font-size:.82rem;font-weight:600}.wd-form input{display:block;width:100%;margin-top:4px}
.fee-line{font-size:.85rem;color:var(--muted);min-height:1.3em;margin:8px 0 0}
.legend{display:flex;flex-wrap:wrap;gap:12px;margin:6px 0 2px}
</style>
<div class="adm-head">
  <div><h1>Collections</h1><p class="adm-sub">All money received, split by how it was paid. <?= h(fmt_date($r['from'], 'j M Y')) ?> to <?= h(fmt_date($r['to'], 'j M Y')) ?>.</p></div>
  <?= export_buttons('collections', $qs) ?>
</div>

<form class="panel filters" method="get" action="<?= url('admin/collections') ?>">
  <div style="display:flex;gap:6px;flex-wrap:wrap"><?php foreach ($ranges as $k2 => $l): ?><a class="btn btn-sm <?= $range === (string)$k2 ? 'btn-primary' : 'btn-ghost' ?>" href="<?= url('admin/collections?' . http_build_query(['range' => $k2] + ($event ? ['event' => $event] : []))) ?>"><?= $l ?></a><?php endforeach; ?></div>
  <label>Event <select name="event"><option value="">All events</option><?php foreach ($all_events as $x): ?><option value="<?= $x['id'] ?>"<?= $event === (int)$x['id'] ? ' selected' : '' ?>><?= h(excerpt($x['title'], 46)) ?> · <?= fmt_date($x['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></label>
  <label>From <input type="date" name="from" value="<?= h($r['from']) ?>"></label>
  <label>To <input type="date" name="to" value="<?= h($r['to']) ?>"></label>
  <button class="btn btn-ghost btn-sm">Show</button>
</form>

<div class="ch-cards">
  <div class="ch-card ch-total"><small>Net collected</small><b><?= h(money($r['net'])) ?></b><span><?= h(money($r['in'])) ?> in · <?= h(money($r['out'])) ?> refunded</span></div>
  <div class="ch-card" style="--c:#f5b800"><small>Online (Daraza)</small><b><?= h(money($online)) ?></b><span>MTN + Airtel, paid by guests themselves</span></div>
  <?php foreach ($inChannels as $c): $v = $r['by'][$c] ?? ['n' => 0, 'amt' => 0]; ?>
    <div class="ch-card" style="--c:<?= PAY_CHANNELS[$c][1] ?>"><small><?= h(PAY_CHANNELS[$c][0]) ?></small><b><?= h(money($v['amt'])) ?></b><span><?= (int)$v['n'] ?> payment<?= $v['n'] === 1 ? '' : 's' ?> · <?= pct($v['amt'], max(1, $r['in'])) ?></span></div>
  <?php endforeach; ?>
  <div class="ch-card neg" style="--c:#b3261e"><small>Refunds</small><b><?= h(money($refundTotal)) ?></b><span>Staff recorded + sent via Daraza</span></div>
</div>

<div class="dash-grid">
  <div class="col-8"><div class="panel">
    <div class="panel-head"><h2>Collected per day</h2></div>
    <div class="legend"><?php foreach (['daraza_mtn', 'daraza_airtel', 'mobile_money', 'cash', 'bank', 'pesapal'] as $c): ?><?= $chip($c) ?><?php endforeach; ?></div>
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" role="img" aria-label="Money collected per day, by channel" style="max-width:100%;height:auto">
      <?php for ($g = 0; $g <= 3; $g++): $gy = $pt + $chh - $g / 3 * $chh; ?>
        <line x1="<?= $pl ?>" x2="<?= $W - 8 ?>" y1="<?= round($gy, 1) ?>" y2="<?= round($gy, 1) ?>" stroke="#e8dfce"/>
        <text x="<?= $pl - 6 ?>" y="<?= round($gy + 4, 1) ?>" text-anchor="end" font-size="10" fill="#8a7f6f"><?= $maxDay >= 1000000 ? round($maxDay * $g / 3 / 1000000, 1) . 'M' : number_format(round($maxDay * $g / 3 / 1000)) . 'k' ?></text>
      <?php endfor; ?>
      <?php $i = 0; $step = max(1, (int)ceil($nd / 8)); foreach ($days as $d => $chs): $x = $pl + ($nd > 1 ? $i / ($nd - 1) * ($cw - $bw) : 0); $y = $pt + $chh;
        foreach (['daraza_mtn', 'daraza_airtel', 'mobile_money', 'cash', 'bank', 'pesapal'] as $c): $amt = $chs[$c] ?? 0; if ($amt <= 0) continue; $hh = $amt / $maxDay * $chh; $y -= $hh; ?>
          <rect x="<?= round($x, 1) ?>" y="<?= round($y, 1) ?>" width="<?= round($bw, 1) ?>" height="<?= round($hh, 1) ?>" fill="<?= PAY_CHANNELS[$c][1] ?>" rx="2"><title><?= h(fmt_date($d, 'D j M') . ': ' . PAY_CHANNELS[$c][0] . ' ' . money($amt)) ?></title></rect>
        <?php endforeach;
        if ($i % $step === 0): ?><text x="<?= round($x + $bw / 2, 1) ?>" y="<?= $H - 8 ?>" text-anchor="middle" font-size="10" fill="#8a7f6f"><?= date('j M', strtotime($d)) ?></text><?php endif; $i++; endforeach; ?>
    </svg>
  </div></div>

  <div class="col-4"><div class="panel">
    <div class="panel-head"><h2>Share by channel</h2></div>
    <div class="hbars"><?php foreach ($order as $c): $v = $r['by'][$c]['amt'] ?? 0; if (!$v && !in_array($c, ['daraza_mtn', 'daraza_airtel'], true)) continue; ?>
      <div class="row" style="grid-template-columns:120px 1fr 44px"><span><?= $chip($c) ?></span><span class="track"><i class="fill" style="width:<?= round($v / $maxCh * 100) ?>%;background:<?= PAY_CHANNELS[$c][1] ?>"></i></span><span class="num"><?= pct($v, max(1, $r['in'])) ?></span></div>
    <?php endforeach; ?></div>
  </div></div>
</div>

<div class="panel" id="withdraw">
  <div class="panel-head"><h2>Daraza wallet</h2><?php if ($daraza_on): ?><a class="small" href="<?= url('admin/collections?' . http_build_query($qs + ['refresh' => 1])) ?>">Refresh balance</a><?php endif; ?></div>
  <?php if (!$daraza_on): ?>
    <p class="muted">Online payments are not switched on yet. Add <code>DARAZA_API_KEY</code> to the <code>.env</code> file.</p>
  <?php else: ?>
  <div class="wallet-box">
    <div><span class="muted small">Available to withdraw</span><div style="font-size:1.8rem;font-weight:800"><?= $wallet && $wallet['balance'] !== null ? h(money($wallet['balance'])) : '—' ?></div>
      <?php if ($wallet && $wallet['error']): ?><span class="small" style="color:#b3261e"><?= h($wallet['error']) ?></span><?php endif; ?></div>
    <div><span class="muted small">Withdrawn in this period</span><div style="font-size:1.3rem;font-weight:800"><?= h(money($r['withdrawn'])) ?></div></div>
  </div>
  <?php if ($can_withdraw): ?>
  <form class="wd-form" method="post" action="<?= url('admin/collections/withdraw') ?>" id="wdForm" onsubmit="return confirm('This sends real money from your Daraza wallet to the number entered. Continue?')"><?= csrf_field() ?>
    <label>Send to (mobile money number) <input name="phone" id="wdPhone" placeholder="0772 123 456" inputmode="tel" required></label>
    <label>Amount (UGX) <input name="amount" id="wdAmount" inputmode="numeric" placeholder="e.g. 500000" required></label>
    <label>Your password <input type="password" name="password" autocomplete="current-password" required></label>
    <label>Type WITHDRAW to confirm <input name="confirm" autocomplete="off" required></label>
    <div><button class="btn btn-primary">Withdraw to mobile money</button></div>
  </form>
  <p class="fee-line" id="wdFee">Enter an amount to see the withdrawal fee. Daraza takes the fee from your wallet on top of the amount sent.</p>
  <script>
  (function () {
    var a = document.getElementById('wdAmount'), out = document.getElementById('wdFee'), t = null;
    a.addEventListener('input', function () {
      clearTimeout(t); var v = a.value.replace(/\D/g, ''); if (v < <?= DARAZA_MIN ?>) { out.textContent = ''; return; }
      t = setTimeout(function () {
        fetch(<?= json_encode(url('admin/collections/fees')) ?> + '?amount=' + v, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
          out.textContent = j.ok ? 'Fee about UGX ' + j.fees.toLocaleString() + ' + tax UGX ' + j.tax.toLocaleString() + ' = UGX ' + j.total.toLocaleString() + ' leaves your wallet.' : (j.error || '');
        }).catch(function () { out.textContent = ''; });
      }, 450);
    });
  })();
  </script>
  <?php else: ?><p class="small muted">Only an administrator can withdraw money.</p><?php endif; ?>
  <?php if ($r['withdrawals']): ?>
  <h3 style="margin-top:18px">Recent withdrawals</h3>
  <div class="tbl-wrap"><table class="tbl"><tr><th>When</th><th>To</th><th>Amount</th><th>Status</th><th>By</th><th></th></tr>
  <?php foreach ($r['withdrawals'] as $w): $tone = ['success' => '✅ Sent', 'failed' => '❌ Failed', 'pending' => '⏳ Sending', 'unconfirmed' => '⚠️ Needs checking'][$w['status']]; ?>
    <tr><td><?= fmt_date($w['created_at'], 'j M H:i') ?></td><td><?= h($w['phone']) ?></td><td><b><?= h(money($w['amount'])) ?></b></td>
      <td><b><?= $tone ?></b><br><span class="small muted"><?= h($w['message']) ?><?= $w['request_id'] ? ' · ref ' . h($w['request_id']) : '' ?></span></td><td><?= h($w['by_name']) ?></td>
      <td><?php if ($w['status'] === 'unconfirmed' && $can_withdraw): ?><form class="inline" method="post" action="<?= url('admin/withdrawals/' . (int)$w['id'] . '/resolve') ?>"><?= csrf_field() ?>
        <button class="btn btn-primary btn-sm" name="choice" value="received" onclick="return confirm('Only choose this if the money really left your Daraza statement.')">Money was sent</button>
        <button class="btn btn-ghost btn-sm" name="choice" value="dismiss">Nothing was sent</button></form><?php endif; ?></td></tr>
  <?php endforeach; ?></table></div>
  <?php endif; ?>
  <?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head"><h2>By event</h2></div>
  <?php
  $byEvent = [];
  foreach ($r['events'] as $x) { $byEvent[$x['id']]['title'] = $x['title']; $byEvent[$x['id']]['date'] = $x['start_at']; $byEvent[$x['id']]['ch'][$x['ch']] = (int)$x['amt']; }
  ?>
  <?php if ($byEvent): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Event</th><th>MTN</th><th>Airtel</th><th>Staff recorded</th><th>Refunds</th><th>Net</th></tr></thead><tbody>
    <?php foreach ($byEvent as $ev): $c = $ev['ch']; $staff = ($c['mobile_money'] ?? 0) + ($c['cash'] ?? 0) + ($c['bank'] ?? 0) + ($c['pesapal'] ?? 0); $ref = ($c['refund'] ?? 0) + ($c['daraza_refund'] ?? 0); ?>
      <tr><td><b><?= h(excerpt($ev['title'], 50)) ?></b><br><span class="muted small"><?= fmt_date($ev['date'], 'j M Y') ?></span></td>
        <td><?= h(money($c['daraza_mtn'] ?? 0)) ?></td><td><?= h(money($c['daraza_airtel'] ?? 0)) ?></td><td><?= h(money($staff)) ?></td><td><?= h(money(-$ref)) ?></td>
        <td><b><?= h(money(array_sum($c))) ?></b></td></tr>
    <?php endforeach; ?></tbody></table></div><?php else: ?><div class="empty">No payments in this period.</div><?php endif; ?>
</div>

<div class="panel">
  <div class="panel-head"><h2>Latest payments</h2><span class="muted small">newest first (the export has them all)</span></div>
  <?php if ($r['recent']): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>When</th><th>Channel</th><th>Guest</th><th>Event</th><th>Amount</th></tr></thead><tbody>
    <?php foreach ($r['recent'] as $x): ?><tr><td><?= fmt_date($x['created_at'], 'j M H:i') ?></td><td><?= $chip($x['ch']) ?></td>
      <td><a href="<?= url('admin/reservations/' . (int)$x['rid']) ?>"><?= h($x['full_name']) ?></a><br><span class="muted small"><?= h($x['reference']) ?></span></td><td><?= h(excerpt($x['title'], 38)) ?></td>
      <td><b style="<?= $x['amount'] < 0 ? 'color:#b3261e' : '' ?>"><?= h(money($x['amount'])) ?></b></td></tr><?php endforeach; ?>
  </tbody></table></div><?php else: ?><div class="empty">No payments in this period.</div><?php endif; ?>
</div>
