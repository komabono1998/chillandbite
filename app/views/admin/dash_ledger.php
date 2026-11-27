<?php
$S = $ledger['sum']; $all = $S['success']['n'] + $S['waiting']['n'] + $S['failed']['n'];
$stl = ['success' => ['✅ Paid', '#1f8a4c'], 'pending' => ['⏳ Waiting', '#7a5cc7'], 'unconfirmed' => ['⏳ Waiting', '#7a5cc7'], 'failed' => ['❌ Failed', '#d9534f']];
$qs = ['tab' => 'ledger', 'status' => $lf['status'] !== 'all' ? $lf['status'] : null, 'event' => $lf['event'] ?: null, 'q' => $lf['q'] ?: null];
$link = fn(array $o) => url('admin?' . http_build_query(array_filter(array_merge($qs, $o), fn($v) => $v !== null && $v !== '')));
?>
<div class="kpis">
  <a class="kpi kpi-ok" href="<?= h($link(['status' => 'success', 'page' => null])) ?>"><span class="kpi-ic">✅</span><div><b><?= (int)$S['success']['n'] ?></b><span>paid · <?= h(money($S['success']['amt'])) ?></span></div></a>
  <a class="kpi kpi-info" href="<?= h($link(['status' => 'waiting', 'page' => null])) ?>"><span class="kpi-ic">⏳</span><div><b><?= (int)$S['waiting']['n'] ?></b><span>waiting · <?= h(money($S['waiting']['amt'])) ?></span></div></a>
  <a class="kpi" href="<?= h($link(['status' => 'failed', 'page' => null])) ?>"><span class="kpi-ic">❌</span><div><b><?= (int)$S['failed']['n'] ?></b><span>failed · <?= h(money($S['failed']['amt'])) ?></span></div></a>
  <a class="kpi" href="<?= h($link(['status' => null, 'page' => null])) ?>"><span class="kpi-ic">📒</span><div><b><?= (int)$all ?></b><span>all online payments started</span></div></a>
</div>
<form class="panel filters dash-filter" method="get" action="<?= url('admin') ?>"><input type="hidden" name="tab" value="ledger">
  <label>Show <select name="status"><?php foreach (['all' => 'Everything', 'success' => 'Paid', 'waiting' => 'Waiting', 'failed' => 'Failed'] as $k => $l): ?><option value="<?= $k ?>"<?= $lf['status'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></label>
  <label>Event <select name="event"><option value="0">All events</option><?php foreach ($all_events as $e): ?><option value="<?= (int)$e['id'] ?>"<?= (int)$e['id'] === $lf['event'] ? ' selected' : '' ?>><?= h(excerpt($e['title'], 44)) ?> · <?= h(fmt_date($e['start_at'], 'j M')) ?></option><?php endforeach; ?></select></label>
  <label>Search <input type="search" name="q" value="<?= h($lf['q']) ?>" placeholder="name, phone or reference" style="min-width:190px"></label>
  <button class="btn btn-primary btn-sm">Show</button><a class="btn btn-ghost btn-sm" href="<?= url('admin?tab=ledger') ?>">Clear</a>
</form>
<p class="small muted" style="margin:0 0 10px">Every online payment a guest started on the website, with who made it, what it was for and, once paid, the payment record it created. Paid ones are recorded automatically and counted in the collections. Waiting ones show a button to file them once you see the money in the wallet.</p>
<div class="panel">
<?php if ($ledger['rows']): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Started</th><th>Who paid</th><th>For</th><th>Amount</th><th>Status</th><th>Record</th></tr></thead><tbody>
<?php foreach ($ledger['rows'] as $x): [$lab, $col] = $stl[$x['status']]; $name = $x['full_name'] ?: ($x['payer_name'] ?: 'Unknown'); ?><tr>
  <td><?= h(fmt_date($x['created_at'], 'D j M')) ?><br><span class="small muted"><?= h(date('g:i:s A', strtotime($x['created_at']))) ?><?= $x['completed_at'] ? ' → ' . h(date('g:i:s A', strtotime($x['completed_at']))) : '' ?></span></td>
  <td><b><?= h($name) ?></b><br><span class="small muted"><?= h($x['phone']) ?></span></td>
  <td><?php if ($x['rid']): ?><a href="<?= url('admin/reservations/' . (int)$x['rid']) ?>"><?= h($x['reference']) ?></a><br><span class="small muted"><?= h(excerpt((string)$x['ev_title'], 40)) ?><?= $x['package'] ? ' · ' . h($x['package']) : '' ?></span><?php else: ?><?= h((string)($x['tab_title'] ?: 'Payment page')) ?><?php endif; ?></td>
  <td><b><?= h(money((int)$x['amount'])) ?></b></td>
  <td><span class="pill" style="background:<?= $col ?>;color:#fff"><?= h($lab) ?></span><br><span class="small muted"><?= h(excerpt((string)$x['message'], 70)) ?></span></td>
  <td><?php if ($x['status'] === 'success'): ?><span class="small">Payment <b>#<?= (int)($x['py_id'] ?: 0) ?: '—' ?></b><?= $x['request_id'] ? '<br><span class="muted">ref ' . h($x['request_id']) . '</span>' : '' ?></span>
    <?php elseif (!empty($wgap) && (int)$x['amount'] <= $wgap['gap'] && can('payments')): ?><form class="inline" method="post" action="<?= url('admin/pay-attempts/' . (int)$x['id'] . '/match') ?>" onsubmit="return confirm('Only press OK if this money is really in the Daraza wallet.')"><?= csrf_field() ?><input type="hidden" name="back" value="ledger"><button class="btn btn-primary btn-sm">Money received</button></form>
    <?php else: ?><span class="small muted">Not recorded</span><?php endif; ?></td></tr>
<?php endforeach; ?></tbody></table></div>
<?php if ($lf['page'] > 1 || $ledger['more']): ?><p class="center" style="margin-top:14px"><?php if ($lf['page'] > 1): ?><a class="btn btn-ghost btn-sm" href="<?= h($link(['page' => $lf['page'] - 1])) ?>">← Newer</a> <?php endif; ?><span class="small muted">Page <?= (int)$lf['page'] ?></span> <?php if ($ledger['more']): ?><a class="btn btn-ghost btn-sm" href="<?= h($link(['page' => $lf['page'] + 1])) ?>">Older →</a><?php endif; ?></p><?php endif; ?>
<?php else: ?><div class="empty">No online payments match.</div><?php endif; ?>
</div>
