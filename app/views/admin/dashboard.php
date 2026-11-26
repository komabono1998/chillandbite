<?php
$first = trim(explode(' ', trim($u['name']))[0]);
$hr = (int)date('G');
$hello = $hr < 12 ? 'Good morning' : ($hr < 17 ? 'Good afternoon' : 'Good evening');
$nextEv = $next ?? null;
?>
<div class="dash-hero">
  <div><span class="dash-date"><?= date('l, j F Y') ?></span><h1><?= h($hello) ?>, <?= h($first) ?> 👋</h1><p>Everything happening at Chill &amp; Bite: bookings, money, and who is visiting the website.</p></div>
  <div class="btn-row">
    <?php if (can('checkin')): ?><a class="btn btn-primary" href="<?= url('admin/checkin') ?>">✅ Check-in desk</a><?php endif; ?>
    <?php if (can('reports')): ?><a class="btn btn-ghost" href="<?= url('admin/reports') ?>">📊 Reports &amp; exports</a><?php endif; ?>
    <a class="btn btn-ghost" href="<?= url() ?>" target="_blank" rel="noopener">View website ↗</a>
  </div>
</div>
<nav class="dash-tabs" aria-label="Dashboard sections">
  <?php foreach ($tabs as $k => [$label, $ic]): ?><a href="<?= url('admin' . ($k === 'overview' ? '' : '?tab=' . $k)) ?>"<?= $k === $tab ? ' aria-current="page"' : '' ?>><span aria-hidden="true"><?= $ic ?></span> <?= h($label) ?></a><?php endforeach; ?>
</nav>
<?php include __DIR__ . '/dash_' . $tab . '.php'; ?>
