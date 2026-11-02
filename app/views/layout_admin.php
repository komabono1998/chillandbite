<?php
$u = current_user();
$items = [['Dashboard', 'admin', 'dashboard'], ['Events', 'admin/events', 'events'], ['Reservations', 'admin/reservations', 'reservations'], ['News', 'admin/news', 'news'],
    ['Comedy videos', 'admin/comedy', 'comedy'], ['Gallery', 'admin/gallery', 'gallery'], ['Sponsors & ads', 'admin/advertising', 'advertising'], ['Inquiries', 'admin/inquiries', 'inquiries'],
    ['Users', 'admin/users', 'users'], ['Settings', 'admin/settings', 'settings'], ['Audit log', 'admin/audit', 'audit']];
$cur = trim(substr(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), strlen(BASE)), '/');
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= h($title ?? 'Admin') ?> · Chill &amp; Bite Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset_v('css/style.css') ?>"></head>
<body class="admin">
<div class="adm-bar">
  <strong>Chill &amp; Bite · Admin</strong>
  <span><?php if ($u): ?><?= h($u['name']) ?> (<?= h(ROLES[$u['role']]) ?>) · <?php endif; ?><a href="<?= url() ?>" target="_blank">View site</a>
    <?php if ($u): ?><form class="inline" method="post" action="<?= url('admin/logout') ?>"><?= csrf_field() ?> <button class="btn btn-ghost btn-sm">Log out</button></form><?php endif; ?></span>
</div>
<?php if ($u): ?><div class="adm"><nav class="adm-nav" aria-label="Admin">
  <?php foreach ($items as [$l, $p, $m]) if (can($m)): ?><a href="<?= url($p) ?>"<?= ($cur === $p || ($p !== 'admin' && str_starts_with($cur, $p))) ? ' aria-current="page"' : '' ?>><?= h($l) ?></a><?php endif; ?>
</nav><div class="adm-main"><?php else: ?><div class="adm-main"><?php endif; ?>
<?php foreach (flash() as [$t, $m]): ?><div class="alert alert-<?= $t === 'err' ? 'err' : 'ok' ?>"><?= h($m) ?></div><?php endforeach; ?>
<?= $content ?>
</div><?php if ($u): ?></div><?php endif; ?>
</body></html>
