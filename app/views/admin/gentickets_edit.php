<?php $cat = ['prefix' => $c['prefix']]; $dl = 'admin/gen-tickets/download?batch[]=' . (int)$b['id']; ?>
<div class="adm-head">
  <div><h1>Edit tickets</h1>
    <p class="adm-sub"><?= h($b['event_title']) ?> · <b><?= h(gen_serial($cat, (int)$b['from_no'])) ?></b> to <b><?= h(gen_serial($cat, (int)$b['to_no'])) ?></b> · <?= number_format((int)$b['to_no'] - (int)$b['from_no'] + 1) ?> tickets</p></div>
  <div class="btn-row"><a class="btn btn-ghost btn-sm" href="<?= url('admin/gen-tickets/batch/' . (int)$b['id']) ?>">Open tickets</a><a class="btn btn-ghost btn-sm" href="<?= url('admin/gen-tickets') ?>">&larr; All batches</a></div>
</div>
<style>
.ge-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px}.ge-grid label{font-size:.82rem;font-weight:600;display:block}.ge-grid input,.ge-grid textarea{display:block;width:100%;margin-top:4px}.ge-grid input[type=color]{height:42px;padding:2px;border-radius:8px}
.ge-wide{grid-column:1/-1}.ge-note{font-size:.8rem;color:var(--muted);margin:2px 0 0}.ge-bar{display:flex;flex-wrap:wrap;gap:10px;align-items:center;margin-top:16px}
.ge-prev{overflow-x:auto;padding:6px 0 10px}.ge-prev .gt{margin:0 auto;box-shadow:0 8px 26px rgba(0,0,0,.2)}
</style>
<?php if ($unsaved): ?><div class="alert alert-err" role="status">This is a preview. <b>Nothing is saved yet.</b> Press &ldquo;Update tickets&rdquo; to keep these details.</div><?php endif; ?>
<form class="panel" method="post" action="<?= url('admin/gen-tickets/batch/' . (int)$b['id'] . '/edit') ?>"><?= csrf_field() ?>
  <div class="panel-head"><h2>What is printed on these tickets</h2><span class="muted small"><?= $custom ? 'This batch has its own details' : 'Following the ' . h($c['name']) . ' category' ?></span></div>
  <div class="ge-grid">
    <label>Ticket name<input type="text" name="label" value="<?= h($form['label']) ?>" maxlength="80"></label>
    <label>How many it admits<input type="number" name="admits" value="<?= (int)$form['admits'] ?>" min="1" max="50"><span class="ge-note">Guests let in on one ticket</span></label>
    <label>Rate (UGX)<input type="text" inputmode="numeric" name="price" value="<?= (int)$form['price'] ?>"><span class="ge-note">0 = no price printed</span></label>
    <label>Colour<input type="color" name="color" value="<?= h($form['color']) ?>"></label>
    <label class="ge-wide">What it comes with <span class="ge-note" style="display:inline">(one per line, up to 6)</span>
      <textarea name="perks" rows="4" maxlength="500" placeholder="2 beers&#10;A stick of meat&#10;Reserved seating"><?= h($form['perks']) ?></textarea></label>
    <label class="ge-wide">Small print<input type="text" name="terms" value="<?= h($form['terms']) ?>" maxlength="255" placeholder="Valid for one entry only. Not refundable."></label>
  </div>
  <div class="ge-bar">
    <button class="btn btn-ghost" name="action" value="preview">👁 Preview</button>
    <button class="btn btn-primary" name="action" value="save">✔ Update tickets</button>
    <?php if ($custom): ?><button class="btn btn-ghost btn-sm" name="action" value="reset" onclick="return confirm('Go back to the category details for this batch?')">Reset to category</button><?php endif; ?>
    <span class="small muted">You can update a batch at any time, even after printing.</span>
  </div>
</form>

<div class="panel">
  <div class="panel-head"><h2>Preview</h2><span class="muted small"><?= $unsaved ? 'Unsaved changes' : 'As saved' ?> · first ticket of the batch</span></div>
  <style><?= ticket_css() ?></style>
  <div class="ge-prev"><?= ticket_html($sample) ?></div>
  <div class="btn-row" style="margin-top:12px">
    <a class="btn btn-primary btn-sm" href="<?= url($dl . '&layout=a4&mode=pdf') ?>">⬇ Download PDF (saved details)</a>
    <a class="btn btn-ghost btn-sm" href="<?= url($dl . '&mode=print') ?>" target="_blank" rel="noopener">🖨️ Print</a>
  </div>
  <p class="small muted">Downloads and prints use the saved details. The QR codes and numbers never change when you update a batch.</p>
</div>
