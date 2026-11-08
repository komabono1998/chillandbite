<style>
.gt-cat-row{display:grid;grid-template-columns:1.4fr .7fr .6fr .9fr 70px 2fr auto;gap:8px;align-items:center;padding:8px 0;border-bottom:1px dashed var(--line)}
.gt-cat-row input[type=text],.gt-cat-row input[type=number]{width:100%}.gt-cat-row input[type=color]{width:100%;height:40px;padding:2px;border-radius:8px}
.gt-cat-head{font-size:.72rem;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);font-weight:700;border-bottom:1px solid var(--line)}
.chip{display:inline-flex;align-items:center;gap:6px;font-weight:700;font-size:.82rem;white-space:nowrap}.chip::before{content:"";width:11px;height:11px;border-radius:4px;background:var(--c)}
.gt-new{display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;align-items:end}.gt-new label{font-size:.82rem;font-weight:600;display:block}.gt-new input,.gt-new select{display:block;width:100%;margin-top:4px}
.gt-qty{font-weight:800;color:var(--accent-d)}
.gt-bulk{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:12px}
@media(max-width:900px){.gt-cat-row{grid-template-columns:1fr 1fr}.gt-cat-head{display:none}}
</style>
<div class="adm-head">
  <div><h1>Printable tickets</h1><p class="adm-sub">Make numbered general tickets (for example No. 1 to 200) in categories you define. Each has its own QR code, prints as a landscape ticket on standard A4, and can be scanned at the gate.</p></div>
  <a class="btn btn-ghost btn-sm" href="<?= url('admin/tickets') ?>">&larr; Booking tickets</a>
</div>

<form class="panel" id="new" method="post" action="<?= url('admin/gen-tickets/create') ?>"><?= csrf_field() ?>
  <h2 style="margin:0 0 12px;font-size:1.1rem">＋ Create a batch of tickets</h2>
  <?php if (!$events || !array_filter($cats, fn($c) => $c['active'])): ?><p class="muted">You need at least one upcoming event and one active ticket category first.</p><?php else: ?>
  <div class="gt-new">
    <label>Event<select name="event_id" id="gEvent" required><?php foreach ($events as $x): ?><option value="<?= (int)$x['id'] ?>"><?= h(excerpt($x['title'], 48)) ?> · <?= fmt_date($x['start_at'], 'j M Y') ?></option><?php endforeach; ?></select></label>
    <label>Ticket category<select name="category_id" id="gCat" required><?php foreach ($cats as $c): if (!$c['active']) continue; ?><option value="<?= (int)$c['id'] ?>"><?= h($c['name']) ?> (<?= h($c['prefix']) ?>)</option><?php endforeach; ?></select></label>
    <label>From number<input type="number" name="from_no" id="gFrom" min="1" value="1" required></label>
    <label>To number<input type="number" name="to_no" id="gTo" min="1" value="200" required></label>
    <label>Note (optional)<input type="text" name="note" maxlength="190" placeholder="e.g. Gate sales batch"></label>
    <div><button class="btn btn-primary">Create <span class="gt-qty" id="gQty" style="color:#fff">200</span> tickets</button></div>
  </div>
  <p class="small muted" style="margin:10px 0 0">Tickets are numbered per event and category, for example GA-0001 to GA-0200. A batch can hold up to <?= number_format(GEN_MAX_BATCH) ?> tickets; make another batch to continue the numbering.</p>
  <?php endif; ?>
</form>
<script>
(function () {
  var next = <?= json_encode((object)$next) ?>, ev = document.getElementById('gEvent'), ct = document.getElementById('gCat'), f = document.getElementById('gFrom'), t = document.getElementById('gTo'), q = document.getElementById('gQty'), touched = false;
  if (!ev) return;
  function qty() { var n = (+t.value) - (+f.value) + 1; q.textContent = n > 0 ? n.toLocaleString() : '0'; }
  function suggest() { var n = next[ev.value + '-' + ct.value] || 1; f.value = n; t.value = n + 199; qty(); }
  ev.addEventListener('change', suggest); ct.addEventListener('change', suggest); f.addEventListener('input', qty); t.addEventListener('input', qty); suggest();
})();
</script>

<form class="panel" method="get" action="<?= url('admin/gen-tickets/download') ?>" id="batches">
  <div class="panel-head"><h2>Your batches</h2><span class="muted small">Tick batches to download or print several at once</span></div>
  <?php if (!$batches): ?><div class="empty">No batches yet. Create your first one above.</div><?php else: ?>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th></th><th>Created</th><th>Event</th><th>Category</th><th>Numbers</th><th>Unused</th><th>Used</th><th>Void</th><th></th></tr></thead><tbody>
  <?php foreach ($batches as $b): $cat = ['prefix' => $b['prefix'] ?? 'TK']; ?>
    <tr><td><input type="checkbox" name="batch[]" value="<?= (int)$b['id'] ?>" aria-label="Select batch <?= (int)$b['id'] ?>"></td><td><?= fmt_date($b['created_at'], 'j M H:i') ?></td>
      <td><?= h(excerpt($b['event_title'], 36)) ?><br><span class="muted small"><?= fmt_date($b['start_at'], 'j M Y') ?></span></td>
      <td><span class="chip" style="--c:<?= h($b['color'] ?? '#888') ?>"><?= h($b['cat_name'] ?? 'Removed') ?></span></td>
      <td><b><?= h(gen_serial($cat, (int)$b['from_no'])) ?></b> to <b><?= h(gen_serial($cat, (int)$b['to_no'])) ?></b><br><span class="muted small"><?= number_format((int)$b['to_no'] - (int)$b['from_no'] + 1) ?> tickets<?= $b['note'] ? ' · ' . h($b['note']) : '' ?></span></td>
      <td><?= (int)$b['n_valid'] ?></td><td><?= (int)$b['n_used'] ?></td><td><?= (int)$b['n_void'] ?></td>
      <td style="white-space:nowrap"><a class="btn btn-ghost btn-sm" href="<?= url('admin/gen-tickets/batch/' . (int)$b['id']) ?>">Open</a>
        <a class="btn btn-primary btn-sm" href="<?= url('admin/gen-tickets/download?batch[]=' . (int)$b['id'] . '&layout=a4') ?>">PDF</a>
        <a class="btn btn-ghost btn-sm" href="<?= url('admin/gen-tickets/download?batch[]=' . (int)$b['id'] . '&mode=print') ?>" target="_blank" rel="noopener">Print</a></td></tr>
  <?php endforeach; ?></tbody></table></div>
  <div class="gt-bulk">
    <strong class="small">Selected batches:</strong>
    <label class="small">Page <select name="layout"><option value="a4">A4, 3 landscape tickets per page</option><option value="a5">A5 landscape, 1 ticket per page</option></select></label>
    <label class="small"><input type="checkbox" name="only_valid" value="1"> Unused tickets only</label>
    <button class="btn btn-primary btn-sm" name="mode" value="pdf">⬇ Download PDF</button>
    <button class="btn btn-ghost btn-sm" name="mode" value="print" formtarget="_blank">🖨️ Print</button>
  </div>
  <?php endif; ?>
</form>

<div class="panel" id="cats">
  <div class="panel-head"><h2>Ticket categories</h2><span class="muted small">Name, short code, how many it admits, printed price and colour</span></div>
  <?php if (!$can_cats): ?><p class="small muted">Only an administrator or event manager can change categories.</p><?php endif; ?>
  <form method="post" action="<?= url('admin/gen-tickets/categories') ?>"><?= csrf_field() ?>
    <div class="gt-cat-row gt-cat-head"><span>Name</span><span>Short code</span><span>Admits</span><span>Price (UGX, 0 = hidden)</span><span>Colour</span><span>Small print on the ticket</span><span></span></div>
    <?php foreach ($cats as $c): $d = $can_cats ? '' : ' disabled'; ?>
    <div class="gt-cat-row">
      <input type="text" name="cat[<?= (int)$c['id'] ?>][name]" value="<?= h($c['name']) ?>" maxlength="80" aria-label="Name"<?= $d ?>>
      <input type="text" name="cat[<?= (int)$c['id'] ?>][prefix]" value="<?= h($c['prefix']) ?>" maxlength="8" aria-label="Short code" style="text-transform:uppercase"<?= $d ?>>
      <input type="number" name="cat[<?= (int)$c['id'] ?>][admits]" value="<?= (int)$c['admits'] ?>" min="1" max="50" aria-label="Admits"<?= $d ?>>
      <input type="text" inputmode="numeric" name="cat[<?= (int)$c['id'] ?>][price]" value="<?= (int)$c['price'] ?>" aria-label="Price"<?= $d ?>>
      <input type="color" name="cat[<?= (int)$c['id'] ?>][color]" value="<?= h($c['color']) ?>" aria-label="Colour"<?= $d ?>>
      <input type="text" name="cat[<?= (int)$c['id'] ?>][terms]" value="<?= h($c['terms']) ?>" maxlength="255" placeholder="Valid for one entry only. Not refundable." aria-label="Small print"<?= $d ?>>
      <span style="white-space:nowrap"><label class="small"><input type="checkbox" name="cat[<?= (int)$c['id'] ?>][active]" value="1"<?= $c['active'] ? ' checked' : '' ?><?= $d ?>> On</label>
        <?php if ($can_cats): ?><button class="btn btn-ghost btn-sm" form="delcat<?= (int)$c['id'] ?>" onclick="return confirm('Delete this category?')">Delete</button><?php endif; ?></span>
    </div>
    <?php endforeach; ?>
    <?php if ($can_cats): ?>
    <h3 style="margin:16px 0 4px;font-size:.95rem">Add a category</h3>
    <div class="gt-cat-row">
      <input type="text" name="new[name]" placeholder="e.g. Early Bird" maxlength="80" aria-label="New name"><input type="text" name="new[prefix]" placeholder="EB" maxlength="8" aria-label="New short code" style="text-transform:uppercase">
      <input type="number" name="new[admits]" value="1" min="1" max="50" aria-label="Admits"><input type="text" inputmode="numeric" name="new[price]" value="0" aria-label="Price"><input type="color" name="new[color]" value="#D96B27" aria-label="Colour">
      <input type="text" name="new[terms]" maxlength="255" placeholder="Small print (optional)" aria-label="Small print"><span></span>
    </div>
    <p style="margin-top:14px"><button class="btn btn-primary btn-sm">Save categories</button></p>
    <?php endif; ?>
  </form>
  <?php foreach ($cats as $c): if ($can_cats): ?><form id="delcat<?= (int)$c['id'] ?>" method="post" action="<?= url('admin/gen-tickets/categories/' . (int)$c['id'] . '/delete') ?>" hidden><?= csrf_field() ?></form><?php endif; endforeach; ?>
</div>
