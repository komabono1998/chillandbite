<?php
$u = current_user();
// [label, path, module, icon]
$groups = [
    'Overview' => [['Dashboard', 'admin', 'dashboard', '🏠']],
    'Website' => [
        ['Pages & content', 'admin/pages', 'pages', '📄'],
        ['News & articles', 'admin/news', 'news', '📰'],
        ['Press accreditation', 'admin/accreditation', 'accreditation', '🪪'],
        ['My stories', 'admin/news', 'my_articles', '✍️'],
        ['Comedy videos', 'admin/comedy', 'comedy', '😂'],
        ['DJs & mixes', 'admin/djs', 'djs', '🎧'],
        ['Gallery', 'admin/gallery', 'gallery', '🖼️'],
        ['Sponsors & ads', 'admin/advertising', 'advertising', '📣'],
        ['Partner applications', 'admin/partners', 'advertising', '🤝'],
    ],
    'Operations' => [
        ['Events', 'admin/events', 'events', '📅'],
        ['Reservations', 'admin/reservations', 'reservations', '🎟️'],
        ['Tickets', 'admin/tickets', 'tickets', '🎫'],
        ['Check-in desk', 'admin/checkin', 'checkin', '✅'],
        ['Inquiries', 'admin/inquiries', 'inquiries', '📬'],
    ],
    'Insights' => [['Reports & exports', 'admin/reports', 'reports', '📊']],
    'Administration' => [
        ['Users & roles', 'admin/users', 'users', '👥'],
        ['Settings', 'admin/settings', 'settings', '⚙️'],
        ['Audit log', 'admin/audit', 'audit', '🕒'],
    ],
];
$cur = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(BASE)), '/');
$isActive = fn(string $p) => $p === 'admin' ? $cur === 'admin' : ($cur === $p || str_starts_with($cur, $p . '/'));
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<link rel="icon" type="image/png" href="<?= h(asset('img/favicon.png')) ?>">
<title><?= h($title ?? 'Admin') ?> · Chill &amp; Bite Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_v('css/style.css') ?>">
<link rel="stylesheet" href="<?= asset_v('css/admin.css') ?>">
</head>
<body class="admin<?= $u ? '' : ' admin-login' ?>">
<?php if ($u): ?>
<div class="ad-shell">
  <aside class="ad-side" id="adSide" aria-label="Admin navigation">
    <a class="ad-brand" href="<?= url('admin') ?>"><img class="ad-logo" src="<?= h(asset('img/logo.png')) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253"><small class="ad-tag">Admin</small></a>
    <nav>
      <?php foreach ($groups as $gname => $items):
          $vis = array_filter($items, fn($i) => can($i[2]));
          if (!$vis) continue; ?>
        <p class="ad-group"><?= h($gname) ?></p>
        <?php foreach ($vis as [$label, $path, $mod, $icon]): ?>
          <a href="<?= url($path) ?>"<?= $isActive($path) ? ' aria-current="page"' : '' ?>><span class="ic" aria-hidden="true"><?= $icon ?></span><?= h($label) ?></a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    <div class="ad-side-foot"><a href="<?= url() ?>" target="_blank" rel="noopener">↗ View website</a></div>
  </aside>
  <div class="ad-main">
    <header class="ad-top">
      <button class="ad-burger" type="button" aria-label="Open menu" aria-controls="adSide" aria-expanded="false">☰</button>
      <p class="ad-crumb"><a href="<?= url('admin') ?>">Admin</a> <span aria-hidden="true">›</span> <?= h($title ?? '') ?></p>
      <div class="ad-user">
        <a class="ad-me" href="<?= url('admin/account') ?>" title="My account" style="display:flex;align-items:center;gap:10px;color:inherit;text-decoration:none">
        <span class="ad-avatar" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($u['name'], 0, 1))) ?></span>
        <span class="ad-who"><b><?= h($u['name']) ?></b><small><?= h(ROLES[$u['role']] ?? $u['role']) ?></small></span></a>
        <form class="inline" method="post" action="<?= url('admin/logout') ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-sm">Log out</button></form>
      </div>
    </header>
    <main class="ad-content">
      <?php foreach (flash() as [$t, $m]): ?><div class="alert alert-<?= $t === 'err' ? 'err' : 'ok' ?>" role="<?= $t === 'err' ? 'alert' : 'status' ?>"><?= h($m) ?></div><?php endforeach; ?>
      <?= $content ?>
    </main>
  </div>
</div>
<script>
(function () {
  var b = document.querySelector('.ad-burger'), s = document.getElementById('adSide');
  if (!b || !s) return;
  b.addEventListener('click', function () { var o = s.classList.toggle('open'); b.setAttribute('aria-expanded', o ? 'true' : 'false'); });
  document.addEventListener('click', function (e) { if (s.classList.contains('open') && !s.contains(e.target) && e.target !== b) { s.classList.remove('open'); b.setAttribute('aria-expanded', 'false'); } });
})();
</script>
<?php else: ?>
<main class="ad-login-wrap">
  <?php foreach (flash() as [$t, $m]): ?><div class="alert alert-<?= $t === 'err' ? 'err' : 'ok' ?>" role="alert"><?= h($m) ?></div><?php endforeach; ?>
  <?= $content ?>
</main>
<?php endif; ?>
</body></html>
