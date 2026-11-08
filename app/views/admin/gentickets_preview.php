<div class="adm-head">
  <div><h1>Ticket preview</h1><p class="adm-sub">How a <b><?= h($c['name']) ?></b> ticket looks for <?= h($e['title']) ?>. Change the colour, rate and what it comes with under Ticket categories, then refresh this page.</p></div>
  <a class="btn btn-ghost btn-sm" href="<?= url('admin/gen-tickets#cats') ?>">&larr; Ticket categories</a>
</div>
<div class="panel">
  <form method="get" action="<?= url('admin/gen-tickets/preview') ?>" class="filters">
    <input type="hidden" name="cat" value="<?= (int)$c['id'] ?>">
    <label>Event <select name="event" onchange="this.form.submit()"><?php foreach ($events as $x): ?><option value="<?= (int)$x['id'] ?>"<?= (int)$x['id'] === (int)$e['id'] ? ' selected' : '' ?>><?= h(excerpt($x['title'], 50)) ?> · <?= fmt_date($x['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></label>
    <a class="btn btn-primary btn-sm" href="<?= url('admin/gen-tickets/preview?cat=' . (int)$c['id'] . '&event=' . (int)$e['id'] . '&pdf=1') ?>">⬇ Download sample PDF</a>
  </form>
  <style><?= ticket_css() ?>.gt-prev{overflow-x:auto;padding:6px 0 10px}.gt-prev .gt{margin:0 auto;box-shadow:0 8px 26px rgba(0,0,0,.2)}</style>
  <div class="gt-prev"><?= ticket_html($t) ?></div>
  <p class="small muted">This is a sample: the QR code on real tickets is different for every ticket and works at the gate.</p>
</div>
