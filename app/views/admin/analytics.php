<?php
$qs = $range === 'custom' ? ['from' => $from, 'to' => $to] : ['range' => $range];
$days = $r['daily'];
$maxV = max(1, ...array_column($days, 'views') ?: [1]);
$n = count($days);
$W = 720; $H = 190; $pl = 34; $pb = 24; $pt = 10;
$cw = $W - $pl - 8; $ch = $H - $pb - $pt;
$pts = []; $i = 0;
foreach ($days as $d => $v) { $x = $pl + ($n > 1 ? $i / ($n - 1) * $cw : $cw / 2); $pts[] = [$x, $pt + $ch - $v['views'] / $maxV * $ch, $d, $v]; $i++; }
$line = implode(' ', array_map(fn($p) => round($p[0], 1) . ',' . round($p[1], 1), $pts));
$area = $pts ? $line . ' ' . round(end($pts)[0], 1) . ',' . ($pt + $ch) . ' ' . round($pts[0][0], 1) . ',' . ($pt + $ch) : '';
$totalV = max(1, $r['views']);
$maxPage = $r['pages'] ? max(1, (int)$r['pages'][0]['views']) : 1;
$maxCountry = $r['countries'] ? max(1, (int)$r['countries'][0]['views']) : 1;
$maxHour = max(1, ...$r['hours']);
$top = $r['countries'][0] ?? null;
$mobile = 0; foreach ($r['devices'] as $dv) if ($dv['device'] === 'mobile') $mobile = (int)$dv['views'];
$unknownShare = $r['views'] ? 100 - round($r['country_known'] / $r['views'] * 100) : 0;
$k = [
    ['👁️', 'Page views', number_format($r['views']), ''], ['👤', 'Visitors', number_format($r['visitors']), 'info'],
    ['📄', 'Pages per visitor', $r['visitors'] ? number_format($r['views'] / $r['visitors'], 1) : '0', ''],
    ['🌍', 'Top country', $top && $top['country'] ? country_flag($top['country']) . ' ' . country_name($top['country']) : '—', 'ok'],
    ['📱', 'On mobile', pct($mobile, $r['views']), 'info'],
    ['🟢', 'Online now', (int)$r['online'], 'ok'], ['📅', 'Today', number_format($r['today_views']) . ' views / ' . number_format($r['today_visitors']) . ' visitors', ''],
];
$ranges = ['today' => 'Today', '7' => '7 days', '30' => '30 days', '90' => '90 days', 'year' => 'This year'];
$srcLabel = ['direct' => 'Direct (typed or bookmarked)', 'search' => 'Search engines', 'social' => 'Social media', 'referral' => 'Other websites'];
?>
<div class="adm-head">
  <div><h1>Visitors</h1><p class="adm-sub">Who visits the website, from where, and which pages they read. <?= h(fmt_date($from, 'j M Y')) ?> to <?= h(fmt_date($to, 'j M Y')) ?>.</p></div>
  <?= export_buttons('visitors', $qs) ?>
</div>

<form class="panel filters" method="get" action="<?= url('admin/analytics') ?>">
  <div class="chips" style="display:flex;gap:6px;flex-wrap:wrap"><?php foreach ($ranges as $k2 => $l): ?><a class="btn btn-sm <?= $range === (string)$k2 ? 'btn-primary' : 'btn-ghost' ?>" href="<?= url('admin/analytics?range=' . $k2) ?>"><?= $l ?></a><?php endforeach; ?></div>
  <label>From <input type="date" name="from" value="<?= h($from) ?>"></label>
  <label>To <input type="date" name="to" value="<?= h($to) ?>"></label>
  <button class="btn btn-ghost btn-sm">Show</button>
</form>

<div class="kpis"><?php foreach ($k as [$ic, $label, $v, $tone]): ?>
  <div class="kpi<?= $tone ? ' kpi-' . $tone : '' ?>"><span class="kpi-ic" aria-hidden="true"><?= $ic ?></span><div><b><?= h((string)$v) ?></b><span><?= h($label) ?></span></div></div>
<?php endforeach; ?></div>

<?php if ($r['views'] === 0): ?>
<div class="panel"><div class="empty">No visits recorded for this period yet. Visits are counted automatically from real visitors (not the team, search-engine bots, or people who send "Do Not Track").</div></div>
<?php endif; ?>

<div class="panel">
  <div class="panel-head"><h2>Visits per day</h2><span class="muted small"><?= export_buttons('visitor_daily', $qs) ?></span></div>
  <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" role="img" aria-label="Page views per day" style="max-width:100%;height:auto">
    <?php for ($g = 0; $g <= 3; $g++): $gy = $pt + $ch - $g / 3 * $ch; ?>
      <line x1="<?= $pl ?>" x2="<?= $W - 8 ?>" y1="<?= round($gy, 1) ?>" y2="<?= round($gy, 1) ?>" stroke="#e8dfce" stroke-width="1"/>
      <text x="<?= $pl - 6 ?>" y="<?= round($gy + 4, 1) ?>" text-anchor="end" font-size="10" fill="#8a7f6f"><?= number_format(round($maxV * $g / 3)) ?></text>
    <?php endfor; ?>
    <?php if ($pts): ?>
      <polygon points="<?= $area ?>" fill="#d96b27" opacity=".13"/>
      <polyline points="<?= $line ?>" fill="none" stroke="#d96b27" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>
      <?php $step = max(1, (int)ceil($n / 8)); foreach ($pts as $idx => $p): ?>
        <circle cx="<?= round($p[0], 1) ?>" cy="<?= round($p[1], 1) ?>" r="<?= $n > 45 ? 0 : 3 ?>" fill="#d96b27"><title><?= h(fmt_date($p[2], 'D j M Y')) ?>: <?= (int)$p[3]['views'] ?> views, <?= (int)$p[3]['visitors'] ?> visitors</title></circle>
        <?php if ($idx % $step === 0): ?><text x="<?= round($p[0], 1) ?>" y="<?= $H - 6 ?>" text-anchor="middle" font-size="10" fill="#8a7f6f"><?= date('j M', strtotime($p[2])) ?></text><?php endif; ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </svg>
</div>

<div class="dash-grid">
  <div class="col-7"><div class="panel">
    <div class="panel-head"><h2>Most visited pages</h2><?= export_buttons('visitor_pages', $qs) ?></div>
    <?php if ($r['pages']): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Page</th><th></th><th>Views</th><th>Visitors</th></tr></thead><tbody>
      <?php foreach ($r['pages'] as $p): ?><tr><td><b><?= h($p['label']) ?></b><br><span class="muted small"><?= h($p['path']) ?></span></td>
        <td style="min-width:110px"><div class="hbars"><span class="track"><i class="fill" style="width:<?= round($p['views'] / $maxPage * 100) ?>%"></i></span></div></td>
        <td><b><?= number_format((int)$p['views']) ?></b> <span class="muted small"><?= pct((int)$p['views'], $totalV) ?></span></td><td><?= number_format((int)$p['visitors']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div><?php else: ?><div class="empty">No pages yet.</div><?php endif; ?>
  </div></div>

  <div class="col-5"><div class="panel">
    <div class="panel-head"><h2>Countries</h2><?= export_buttons('visitor_countries', $qs) ?></div>
    <?php if ($r['countries']): ?><div class="tbl-wrap"><table class="tbl"><thead><tr><th>Country</th><th></th><th>Views</th></tr></thead><tbody>
      <?php foreach ($r['countries'] as $c): ?><tr><td><?= country_flag($c['country']) ?> <b><?= h(country_name($c['country'])) ?></b></td>
        <td style="min-width:80px"><div class="hbars"><span class="track"><i class="fill" style="width:<?= round($c['views'] / $maxCountry * 100) ?>%"></i></span></div></td>
        <td><b><?= number_format((int)$c['views']) ?></b> <span class="muted small"><?= pct((int)$c['views'], $totalV) ?></span></td></tr><?php endforeach; ?>
    </tbody></table></div>
    <?php if ($r['views'] && $unknownShare >= 50): ?><p class="muted small" style="margin-top:10px">ℹ️ Most visits have no country yet. Add the line <code>GEO_LOOKUP=1</code> to your <code>.env</code> file to find each visitor's country (only a scrambled version of the address is kept).</p><?php endif; ?>
    <?php else: ?><div class="empty">No countries yet.</div><?php endif; ?>
  </div></div>

  <div class="col-6"><div class="panel">
    <div class="panel-head"><h2>Where visitors come from</h2></div>
    <?php if ($r['sources']): $srcMax = max(1, (int)$r['sources'][0]['views']); ?><div class="hbars"><?php foreach ($r['sources'] as $s): ?>
      <div class="row"><span><?= h($srcLabel[$s['source']] ?? ucfirst($s['source'])) ?></span><span class="track"><i class="fill" style="width:<?= round($s['views'] / $srcMax * 100) ?>%"></i></span><span class="num"><?= number_format((int)$s['views']) ?></span></div><?php endforeach; ?></div>
      <?php if ($r['referrers']): ?><h3 style="margin:14px 0 6px;font-size:.9rem">Top referring sites</h3><ul class="plain small"><?php foreach ($r['referrers'] as $x): ?><li><?= h($x['ref_host']) ?> <span class="muted">· <?= (int)$x['views'] ?></span></li><?php endforeach; ?></ul><?php endif; ?>
    <?php else: ?><div class="empty">No data yet.</div><?php endif; ?>
  </div></div>

  <div class="col-6"><div class="panel">
    <div class="panel-head"><h2>Devices &amp; browsers</h2></div>
    <?php if ($r['devices']): ?><div class="hbars"><?php foreach ($r['devices'] as $dv): ?>
      <div class="row"><span><?= ['desktop' => '🖥️ Desktop', 'mobile' => '📱 Mobile', 'tablet' => '📲 Tablet'][$dv['device']] ?? h($dv['device']) ?></span><span class="track"><i class="fill" style="width:<?= round($dv['views'] / $totalV * 100) ?>%"></i></span><span class="num"><?= pct((int)$dv['views'], $totalV) ?></span></div><?php endforeach; ?>
      <h3 style="margin:14px 0 8px;font-size:.9rem">Browsers</h3>
      <?php foreach ($r['browsers'] as $b): ?><div class="row"><span><?= h((string)$b['browser']) ?></span><span class="track"><i class="fill" style="width:<?= round($b['views'] / $totalV * 100) ?>%"></i></span><span class="num"><?= pct((int)$b['views'], $totalV) ?></span></div><?php endforeach; ?>
    </div><?php else: ?><div class="empty">No data yet.</div><?php endif; ?>
  </div></div>

  <div class="col-12"><div class="panel">
    <div class="panel-head"><h2>Busiest hours</h2><span class="muted small">East Africa Time</span></div>
    <div class="bars" role="img" aria-label="Visits by hour of day" style="height:120px"><?php foreach ($r['hours'] as $hr => $cnt): ?>
      <div class="col" title="<?= sprintf('%02d:00', $hr) ?>: <?= $cnt ?> view(s)"><div class="bar" style="height:<?= $cnt ? max(5, round($cnt / $maxHour * 100)) : 2 ?>%"></div><span class="lab"><?= $hr ?></span></div><?php endforeach; ?></div>
  </div></div>
</div>

<p class="muted small">Statistics are anonymous: no cookies are used, no IP addresses are stored, and the team, search-engine bots and visitors who send "Do Not Track" are not counted.</p>
