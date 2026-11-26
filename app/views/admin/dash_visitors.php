<?php
$r = $v;
$ranges = ['today' => 'Today', '7' => '7 days', '30' => '30 days', '90' => '90 days', 'year' => 'This year'];
$days = $r['daily']; $n = count($days); $maxV = max(1, ...array_column($days, 'views') ?: [1]);
$W = 720; $H = 190; $pl = 34; $pb = 24; $pt = 10; $cw = $W - $pl - 8; $chh = $H - $pb - $pt;
$pts = []; $i = 0;
foreach ($days as $d => $vv) { $x = $pl + ($n > 1 ? $i / ($n - 1) * $cw : $cw / 2); $pts[] = [$x, $pt + $chh - $vv['views'] / $maxV * $chh, $d, $vv]; $i++; }
$line = implode(' ', array_map(fn($p) => round($p[0], 1) . ',' . round($p[1], 1), $pts));
$area = $pts ? $line . ' ' . round(end($pts)[0], 1) . ',' . ($pt + $chh) . ' ' . round($pts[0][0], 1) . ',' . ($pt + $chh) : '';
$maxPage = $r['pages'] ? max(1, (int)$r['pages'][0]['views']) : 1; $maxCountry = $r['countries'] ? max(1, (int)$r['countries'][0]['views']) : 1;
$mobile = 0; foreach ($r['devices'] as $dv) if ($dv['device'] === 'mobile') $mobile = (int)$dv['views'];
$top = $r['countries'][0] ?? null; $srcLabel = ['direct' => 'Direct', 'search' => 'Search engines', 'social' => 'Social media', 'referral' => 'Other websites'];
?>
<form class="panel filters dash-filter" method="get" action="<?= url('admin') ?>"><input type="hidden" name="tab" value="visitors">
  <div style="display:flex;gap:6px;flex-wrap:wrap"><?php foreach ($ranges as $k2 => $l): ?><a class="btn btn-sm <?= $range === (string)$k2 ? 'btn-primary' : 'btn-ghost' ?>" href="<?= url('admin?tab=visitors&range=' . $k2) ?>"><?= h($l) ?></a><?php endforeach; ?></div>
  <label>From <input type="date" name="from" value="<?= h($from) ?>"></label><label>To <input type="date" name="to" value="<?= h($to) ?>"></label><button class="btn btn-ghost btn-sm">Show</button>
  <a class="btn btn-ghost btn-sm" href="<?= url('admin/analytics') ?>">Full statistics &amp; exports →</a>
</form>
<div class="kpis">
  <div class="kpi"><span class="kpi-ic">👁️</span><div><b><?= number_format($r['views']) ?></b><span>Page views</span></div></div>
  <div class="kpi kpi-info"><span class="kpi-ic">👤</span><div><b><?= number_format($r['visitors']) ?></b><span>Visitors</span></div></div>
  <div class="kpi"><span class="kpi-ic">📄</span><div><b><?= $r['visitors'] ? number_format($r['views'] / $r['visitors'], 1) : '0' ?></b><span>Pages per visitor</span></div></div>
  <div class="kpi kpi-ok"><span class="kpi-ic">🟢</span><div><b><?= (int)$r['online'] ?></b><span>Online now</span></div></div>
  <div class="kpi kpi-ok"><span class="kpi-ic">🌍</span><div><b><?= $top && $top['country'] ? country_flag($top['country']) . ' ' . h(country_name($top['country'])) : '—' ?></b><span>Top country</span></div></div>
  <div class="kpi kpi-info"><span class="kpi-ic">📱</span><div><b><?= h(pct($mobile, $r['views'])) ?></b><span>On mobile</span></div></div>
</div>
<div class="dash-grid">
  <div class="col-12"><div class="panel"><div class="panel-head"><h2>Page views per day</h2><span class="muted small"><?= h(fmt_date($from, 'j M')) ?> to <?= h(fmt_date($to, 'j M Y')) ?></span></div>
    <?php if ($r['views']): ?><svg viewBox="0 0 <?= $W ?> <?= $H ?>" style="width:100%;height:auto" role="img" aria-label="Page views per day">
      <?php for ($g = 0; $g <= 3; $g++): $yy = $pt + $chh - $g / 3 * $chh; ?><line x1="<?= $pl ?>" x2="<?= $W - 8 ?>" y1="<?= $yy ?>" y2="<?= $yy ?>" stroke="#eee6d6"/><text x="<?= $pl - 6 ?>" y="<?= $yy + 4 ?>" text-anchor="end" font-size="10" fill="#8a8378"><?= round($maxV * $g / 3) ?></text><?php endfor; ?>
      <polygon points="<?= $area ?>" fill="rgba(217,107,39,.18)"/><polyline points="<?= $line ?>" fill="none" stroke="#d96b27" stroke-width="2.5" stroke-linejoin="round"/>
      <?php foreach ($pts as $k3 => $p): if ($n <= 31 || $k3 % ceil($n / 31) === 0): ?><circle cx="<?= round($p[0], 1) ?>" cy="<?= round($p[1], 1) ?>" r="3" fill="#d96b27"><title><?= h(fmt_date($p[2], 'D j M')) ?>: <?= (int)$p[3]['views'] ?> views, <?= (int)$p[3]['visitors'] ?> visitors</title></circle><?php endif; endforeach; ?>
    </svg><?php else: ?><div class="empty">No visits recorded in this period yet.</div><?php endif; ?></div></div>
  <div class="col-7"><div class="panel"><div class="panel-head"><h2>🔥 Most visited pages</h2><a class="small" href="<?= url('admin/analytics') ?>">See all</a></div>
    <?php if ($r['pages']): ?><div class="hbars"><?php foreach (array_slice($r['pages'], 0, 12) as $i2 => $pg): ?>
      <div class="row" style="grid-template-columns:28px minmax(110px,190px) 1fr 80px"><span class="muted small">#<?= $i2 + 1 ?></span><span title="<?= h($pg['path']) ?>"><b><?= h(excerpt($pg['label'], 30)) ?></b></span><span class="track"><i class="fill" style="width:<?= round($pg['views'] / $maxPage * 100) ?>%"></i></span><span><?= number_format($pg['views']) ?> <small class="muted">(<?= number_format($pg['visitors']) ?> people)</small></span></div>
    <?php endforeach; ?></div><?php else: ?><div class="empty">No page views yet.</div><?php endif; ?></div></div>
  <div class="col-5"><div class="panel"><div class="panel-head"><h2>🌍 Where visitors are from</h2></div>
    <?php if ($r['countries']): ?><div class="hbars"><?php foreach (array_slice($r['countries'], 0, 8) as $c): ?>
      <div class="row" style="grid-template-columns:minmax(100px,150px) 1fr 60px"><span><?= $c['country'] ? country_flag($c['country']) . ' ' . h(country_name($c['country'])) : 'Unknown' ?></span><span class="track"><i class="fill" style="width:<?= round($c['views'] / $maxCountry * 100) ?>%"></i></span><span><?= number_format($c['views']) ?></span></div>
    <?php endforeach; ?></div><?php else: ?><div class="empty">No data yet.</div><?php endif; ?></div></div>
  <div class="col-6"><div class="panel"><div class="panel-head"><h2>How they found us</h2></div>
    <?php if ($r['sources']): $totS = max(1, array_sum(array_column($r['sources'], 'views'))); ?><div class="hbars"><?php foreach ($r['sources'] as $s): ?><div class="row" style="grid-template-columns:150px 1fr 90px"><span><?= h($srcLabel[$s['source']] ?? ucfirst($s['source'])) ?></span><span class="track"><i class="fill" style="width:<?= round($s['views'] / $totS * 100) ?>%"></i></span><span><?= number_format($s['views']) ?> <small class="muted"><?= h(pct($s['views'], $totS)) ?></small></span></div><?php endforeach; ?></div><?php else: ?><div class="empty">No data yet.</div><?php endif; ?></div></div>
  <div class="col-6"><div class="panel"><div class="panel-head"><h2>Devices</h2></div>
    <?php if ($r['devices']): $totD = max(1, array_sum(array_column($r['devices'], 'views'))); ?><div class="hbars"><?php foreach ($r['devices'] as $dv): ?><div class="row" style="grid-template-columns:120px 1fr 90px"><span><?= h(ucfirst($dv['device'])) ?></span><span class="track"><i class="fill" style="width:<?= round($dv['views'] / $totD * 100) ?>%"></i></span><span><?= number_format($dv['views']) ?> <small class="muted"><?= h(pct($dv['views'], $totD)) ?></small></span></div><?php endforeach; ?></div><?php else: ?><div class="empty">No data yet.</div><?php endif; ?></div></div>
</div>
