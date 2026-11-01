<?php
$prev = date('Y-m', strtotime("$month-01 -1 month")); $nextM = date('Y-m', strtotime("$month-01 +1 month"));
$qs = fn(array $o) => '?' . http_build_query(array_filter(array_merge(['view' => $view, 'cat' => $cat, 'month' => $month], $o)));
?>
<section class="page-hero"><div class="wrap"><span class="eyebrow gold">Events calendar</span><h1>What's on</h1><p class="lead">Chill &amp; Bite MEATINGS every first Sunday, plus comedy nights, networking and special events.</p></div></section>
<section class="section section-cream"><div class="wrap">
  <form class="filters" method="get" action="<?= url('events') ?>">
    <label>Month <input type="month" name="month" value="<?= h($month) ?>"></label>
    <label>Category <select name="cat"><option value="">All</option><?php foreach ($categories as $c): ?><option value="<?= h($c['slug']) ?>"<?= $cat === $c['slug'] ? ' selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></label>
    <label>View <select name="view"><option value="list"<?= $view === 'list' ? ' selected' : '' ?>>List</option><option value="calendar"<?= $view === 'calendar' ? ' selected' : '' ?>>Calendar</option></select></label>
    <button class="btn btn-primary btn-sm">Apply</button>
  </form>

  <div class="month-nav">
    <a class="btn btn-ghost-dark btn-sm" href="<?= $qs(['month' => $prev]) ?>">&larr; <?= date('M', strtotime("$prev-01")) ?></a>
    <h2><?= date('F Y', strtotime("$month-01")) ?></h2>
    <a class="btn btn-ghost-dark btn-sm" href="<?= $qs(['month' => $nextM]) ?>"><?= date('M', strtotime("$nextM-01")) ?> &rarr;</a>
  </div>

  <?php if ($view === 'calendar'):
    $first = strtotime("$month-01"); $days = (int)date('t', $first); $lead = (int)date('w', $first);
    $byDay = []; foreach ($events as $e) $byDay[(int)date('j', strtotime($e['start_at']))][] = $e; ?>
    <div class="cal" role="table" aria-label="Events in <?= date('F Y', $first) ?>">
      <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dn): ?><div class="cal-h" role="columnheader"><?= $dn ?></div><?php endforeach; ?>
      <?php for ($i = 0; $i < $lead; $i++): ?><div class="cal-d empty"></div><?php endfor; ?>
      <?php for ($d = 1; $d <= $days; $d++): ?>
        <div class="cal-d<?= isset($byDay[$d]) ? ' has' : '' ?>"><span class="n"><?= $d ?></span>
          <?php foreach ($byDay[$d] ?? [] as $e): ?><a class="cal-ev<?= $e['status'] === 'cancelled' ? ' x' : '' ?>" href="<?= url('events/' . h($e['slug'])) ?>"><?= fmt_time($e['start_at']) ?> <?= h($e['title']) ?></a><?php endforeach; ?></div>
      <?php endfor; ?>
    </div>
  <?php endif; ?>

  <?php $list = ($view === 'list' && $upcoming !== null) ? $upcoming : $events; ?>
  <?php if ($view === 'list' || $view === 'calendar'): ?>
    <h3 class="mt"><?= ($view === 'list' && $upcoming !== null) ? 'Upcoming events' : 'Events this month' ?></h3>
    <?php if ($list): ?><div class="grid grid-3"><?php foreach ($list as $e) event_card($e); ?></div>
    <?php else: ?><p class="muted">No events found for this selection. Try another month or category.</p><?php endif; ?>
  <?php endif; ?>
</div></section>
