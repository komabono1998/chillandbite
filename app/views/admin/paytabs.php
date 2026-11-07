<style>
.pt-adm{margin-bottom:16px}.pt-adm-head{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:12px}
.pt-adm-head h2{margin:0;font-size:1.1rem}.pt-state-pill{font-size:.72rem;font-weight:800;padding:3px 10px;border-radius:99px}
.pt-on{background:#dcf3e3;color:#16632c}.pt-off{background:#efe9dd;color:#6b6258}
.pt-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.pt-grid label{font-size:.82rem;font-weight:600;display:block}.pt-grid input[type=text],.pt-grid input[type=number]{display:block;width:100%;margin-top:4px}
.pt-pages{display:flex;flex-wrap:wrap;gap:6px 14px;margin-top:10px}.pt-pages label{font-weight:500;font-size:.84rem;display:flex;gap:6px;align-items:center}
.pt-switch{display:flex;gap:10px;align-items:center;font-weight:700;cursor:pointer}.pt-switch input{width:22px;height:22px}
.pt-stat{font-size:.8rem;color:var(--muted)}
</style>
<div class="adm-head"><div><h1>Payment tabs</h1><p class="adm-sub">A Pay box that appears on a page only when you switch it on. Set the amount, tick the pages, and visitors can pay with MTN or Airtel. Every payment lands in Collections.</p></div></div>
<?php if (!$daraza_on): ?><div class="alert alert-err">Online payments are not switched on yet. Add <code>DARAZA_API_KEY</code> to the <code>.env</code> file. Tabs stay hidden until then.</div><?php endif; ?>

<form method="post" action="<?= url('admin/pay-tabs') ?>"><?= csrf_field() ?>
<?php foreach ($tabs as $t): $pages = array_filter(array_map('trim', explode(',', (string)$t['pages']))); $tot = $totals[(int)$t['id']] ?? null; ?>
  <div class="panel pt-adm">
    <div class="pt-adm-head">
      <h2><?= h($t['title']) ?> <span class="pt-state-pill <?= $t['active'] ? 'pt-on' : 'pt-off' ?>"><?= $t['active'] ? 'ON: visible on the site' : 'OFF' ?></span></h2>
      <label class="pt-switch"><input type="checkbox" name="tab[<?= (int)$t['id'] ?>][active]" value="1"<?= $t['active'] ? ' checked' : '' ?>> Switch on</label>
    </div>
    <div class="pt-grid">
      <label>Amount (UGX)<input type="text" inputmode="numeric" name="tab[<?= (int)$t['id'] ?>][amount]" value="<?= (int)$t['amount'] ?>"></label>
      <label>Heading<input type="text" name="tab[<?= (int)$t['id'] ?>][title]" value="<?= h($t['title']) ?>" maxlength="120"></label>
      <label>Short line under the heading<input type="text" name="tab[<?= (int)$t['id'] ?>][blurb]" value="<?= h($t['blurb']) ?>" maxlength="255"></label>
    </div>
    <div class="pt-pages"><strong class="small" style="flex-basis:100%">Show on these pages</strong>
      <?php foreach (PAYTAB_PAGES as $k => $lab): ?><label><input type="checkbox" name="tab[<?= (int)$t['id'] ?>][pages][]" value="<?= h($k) ?>"<?= in_array($k, $pages, true) ? ' checked' : '' ?>> <?= h($lab) ?></label><?php endforeach; ?>
    </div>
    <p class="pt-stat" style="margin-top:10px"><?= $tot ? 'Received so far: <b>' . h(money($tot['amt'])) . '</b> in ' . (int)$tot['n'] . ' payment' . ((int)$tot['n'] === 1 ? '' : 's') : 'No payments yet.' ?>
      <button class="btn btn-ghost btn-sm" style="margin-left:10px" form="del<?= (int)$t['id'] ?>" onclick="return confirm('Delete this tab?')">Delete</button></p>
  </div>
<?php endforeach; ?>
  <p><button class="btn btn-primary">Save payment tabs</button></p>
</form>
<?php foreach ($tabs as $t): ?><form id="del<?= (int)$t['id'] ?>" method="post" action="<?= url('admin/pay-tabs/' . (int)$t['id'] . '/delete') ?>" hidden><?= csrf_field() ?></form><?php endforeach; ?>

<form class="panel pt-adm" method="post" action="<?= url('admin/pay-tabs/new') ?>"><?= csrf_field() ?>
  <h2 style="margin:0 0 10px;font-size:1.05rem">Add another payment tab</h2>
  <div class="pt-grid"><label>Name (for example &ldquo;Sponsorship deposit&rdquo;)<input type="text" name="title" maxlength="120" required></label></div>
  <p style="margin-top:10px"><button class="btn btn-ghost btn-sm">Add tab</button></p>
</form>
