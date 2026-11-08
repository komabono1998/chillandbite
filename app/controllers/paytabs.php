<?php
declare(strict_types=1);

/*
 * Payment tabs: a fixed-amount "Pay" box that the admin switches on for chosen pages (Advertise, DJs, MCs, or any page).
 * Visitors pay with MTN or Airtel; the money lands in the ledger under that tab. The admin only sets the amount and turns it on.
 */
const PAYTAB_PAGES = ['all' => 'Every page', 'home' => 'Home', 'about' => 'About', 'events' => 'Events', 'laugh' => 'Laugh with Owakabi (MC & comedy)', 'djs' => 'Podium DJs',
    'advertise' => 'Advertise', 'partner' => 'Become a partner', 'gallery' => 'Gallery', 'contact' => 'Contact'];

/** Active tabs that belong on a page. $seg is the first part of the address ('' = home). */
function paytabs_active(?string $seg = null): array
{
    if (!daraza_enabled()) return [];
    $sql = 'SELECT * FROM payment_tabs WHERE active = 1 AND amount >= ' . DARAZA_MIN . ' ORDER BY sort, id';
    try { $all = rows($sql); }
    catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; pay_ensure_schema(); $all = rows($sql); }
    if ($seg === null) return $all;
    $key = $seg === '' ? 'home' : $seg;
    return array_values(array_filter($all, function ($t) use ($key) { $p = array_filter(array_map('trim', explode(',', (string)$t['pages']))); return in_array('all', $p, true) || in_array($key, $p, true); }));
}
function paytab_find(string $slug): ?array
{
    foreach (paytabs_active() as $t) if ($t['slug'] === $slug) return $t;
    return null;
}

/** The Pay box(es) for a page, as HTML (empty when nothing is switched on). Called from the site layout, so any page gets it. */
function paytabs_render(string $seg): string
{
    if (in_array($seg, ['admin', 'booking', 'ticket', 'track', 'setup'], true) || str_contains($seg, 'reserve')) return '';
    try { $tabs = paytabs_active($seg); } catch (Throwable $e) { return ''; }
    if (!$tabs) return '';
    ob_start(); ?>
<section class="section section-cream paytabs" id="pay-online" aria-label="Pay online"><div class="wrap narrow-wrap">
  <div class="pt-head"><span class="eyebrow">Pay online</span><h2><?= count($tabs) > 1 ? 'Make a payment' : h($tabs[0]['title']) ?></h2></div>
  <?php if (count($tabs) > 1): ?><div class="pt-tabs" role="tablist"><?php foreach ($tabs as $i => $t): ?><button type="button" class="pt-tab<?= $i === 0 ? ' on' : '' ?>" role="tab" data-slug="<?= h($t['slug']) ?>"><?= h($t['title']) ?></button><?php endforeach; ?></div><?php endif; ?>
  <?php foreach ($tabs as $i => $t): ?>
  <article class="pt-card<?= $i === 0 ? ' on' : '' ?>" data-slug="<?= h($t['slug']) ?>" data-post="<?= h(url('paytab/' . $t['slug'])) ?>" data-status="<?= h(url('paytab/' . $t['slug'] . '/status/')) ?>">
    <div class="pt-top"><span class="pt-ic" aria-hidden="true">💳</span><div><h3><?= h($t['title']) ?></h3><?php if ($t['blurb']): ?><p><?= h($t['blurb']) ?></p><?php endif; ?></div><div class="pt-amt"><small>Amount</small><b><?= h(money($t['amount'])) ?></b></div></div>
    <form class="pt-form" novalidate><?= csrf_field() ?><?php honeypot(); ?>
      <div class="pt-err alert alert-err" role="alert" hidden></div>
      <label>Your name <input name="name" autocomplete="name" required></label>
      <label>Mobile money number to pay from <input name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0772 123 456" required></label>
      <label>Email for your receipt (optional) <input name="email" type="email" autocomplete="email"></label>
      <label>What is this payment for? (optional) <input name="note" maxlength="200" placeholder="e.g. company or event name"></label>
      <button class="btn btn-primary btn-lg pt-go" type="submit"><span class="pay-btn-spin" aria-hidden="true"></span><span>Pay <?= h(money($t['amount'])) ?></span></button>
      <p class="small muted">MTN or Airtel. A prompt is sent to your phone: enter your PIN to approve.</p>
    </form>
    <div class="pt-state" hidden aria-live="polite">
      <div class="pay-spin" aria-hidden="true"></div><h3 class="pt-st-title">Check your phone</h3><p class="pt-st-text"></p><span class="pay-timer">0:00</span>
      <ol class="pay-steps"><li><span>Look for the payment prompt on <b class="pt-phone"></b></span></li><li>Enter your Mobile Money PIN to approve</li><li>Stay on this page for your receipt.</li></ol>
      <div class="rs-ref pt-rcpt" hidden>Receipt number <b></b></div><div class="btn-row rs-actions pt-actions"></div>
    </div>
  </article>
  <?php endforeach; ?>
</div></section>
<script>
(function () {
  var root = document.getElementById('pay-online'); if (!root || !window.fetch) return;
  root.querySelectorAll('.pt-tab').forEach(function (b) { b.addEventListener('click', function () {
    root.querySelectorAll('.pt-tab').forEach(function (x) { x.classList.toggle('on', x === b); });
    root.querySelectorAll('.pt-card').forEach(function (c) { c.classList.toggle('on', c.dataset.slug === b.dataset.slug); }); }); });
  function fmt(s) { return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
  root.querySelectorAll('.pt-card').forEach(function (card) {
    var form = card.querySelector('.pt-form'), err = card.querySelector('.pt-err'), go = card.querySelector('.pt-go'), st = card.querySelector('.pt-state');
    var title = card.querySelector('.pt-st-title'), text = card.querySelector('.pt-st-text'), timer = card.querySelector('.pay-timer'), spin = card.querySelector('.pay-spin');
    var steps = card.querySelector('.pay-steps'), acts = card.querySelector('.pt-actions'), rc = card.querySelector('.pt-rcpt'), tick = null, poll = null, t0 = 0;
    function stop() { clearInterval(tick); clearTimeout(poll); }
    function show(ok, head, msg) {
      form.hidden = true; st.hidden = false; st.className = 'pt-state' + (ok === true ? ' pay-ok' : ok === false ? ' pay-bad' : ''); title.textContent = head; text.textContent = msg || '';
      spin.hidden = timer.hidden = steps.hidden = ok !== null; acts.innerHTML = ''; st.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }
    function again() { st.hidden = true; form.hidden = false; go.disabled = false; go.classList.remove('is-busy'); rc.hidden = true; }
    function btn(label, fn) { var b = document.createElement('button'); b.type = 'button'; b.className = 'btn btn-primary'; b.textContent = label; b.onclick = fn; acts.appendChild(b); }
    function check(token) {
      fetch(card.dataset.status + token, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
        if (j.status === 'success') { stop(); show(true, 'Payment received. Thank you!', 'We have recorded your payment of ' + j.amount + '.' + (form.querySelector('[name=email]').value ? ' A receipt was emailed to you.' : ' Please keep your receipt number.')); rc.hidden = false; rc.querySelector('b').textContent = j.receipt; btn('Make another payment', again); return; }
        if (j.status === 'failed') { stop(); show(false, 'Payment not completed', j.message || 'The payment was not approved.'); btn('Try again', again); return; }
        if (j.status === 'unconfirmed') { stop(); show(false, 'Waiting for confirmation', j.message); return; }
        poll = setTimeout(function () { check(token); }, 3000);
      }).catch(function () { poll = setTimeout(function () { check(token); }, 5000); });
    }
    form.addEventListener('submit', function (e) {
      e.preventDefault(); err.hidden = true; go.disabled = true; go.classList.add('is-busy');
      fetch(card.dataset.post, { method: 'POST', body: new FormData(form), headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
        if (!j.ok) { go.disabled = false; go.classList.remove('is-busy'); err.textContent = j.error || 'Could not start the payment.'; err.hidden = false; return; }
        show(null, 'Check your phone', 'Approve the payment prompt. This can take up to 3 minutes. Please keep this page open.'); card.querySelector('.pt-phone').textContent = form.querySelector('[name=phone]').value;
        t0 = Date.now(); timer.textContent = '0:00'; stop(); tick = setInterval(function () { timer.textContent = fmt(Math.round((Date.now() - t0) / 1000)); }, 1000); check(j.token);
      }).catch(function () { go.disabled = false; go.classList.remove('is-busy'); err.textContent = 'Network problem. Please check your connection and try again.'; err.hidden = false; });
    });
  });
})();
</script>
<?php
    return (string)ob_get_clean();
}

/* ================= Public payment endpoints ================= */
function post_paytab(array $a): void
{
    $tab = paytab_find($a['slug']);
    if (!$tab) pay_json(['ok' => false, 'error' => 'This payment option is not available right now.'], 404);
    if (!empty($_POST['website'])) pay_json(['ok' => false, 'error' => 'Thank you!']);
    $name = trim((string)($_POST['name'] ?? ''));
    if (mb_strlen($name) < 2) pay_json(['ok' => false, 'error' => 'Please enter your name.'], 422);
    $phone = daraza_phone((string)($_POST['phone'] ?? ''));
    if (!$phone) pay_json(['ok' => false, 'error' => 'Enter your mobile money number, for example 0772 123 456.'], 422);
    $mail = trim((string)($_POST['email'] ?? ''));
    if ($mail !== '' && !filter_var($mail, FILTER_VALIDATE_EMAIL)) pay_json(['ok' => false, 'error' => 'That email address looks invalid.'], 422);
    if (rate_limited('paytab', 300, 900)) pay_json(['ok' => false, 'error' => 'Too many attempts. Please wait a few minutes and try again.'], 429);
    try {
        daraza_expire_stale();
        if (val("SELECT id FROM pay_attempts WHERE phone = ? AND status = 'pending' LIMIT 1", [$phone])) pay_json(['ok' => false, 'error' => 'A payment request is already waiting for approval on that phone. Please approve it or wait a few minutes.'], 409);
        if ((int)val('SELECT COUNT(*) FROM pay_attempts WHERE phone = ? AND created_at > (NOW() - INTERVAL 1 HOUR)', [$phone]) >= 4) pay_json(['ok' => false, 'error' => 'Too many payment requests to this number. Please try again later.'], 429);
    } catch (PDOException $e) { if ($e->getCode() === '42S02') pay_ensure_schema(); else throw $e; }
    $amount = (int)$tab['amount'];
    $at = daraza_start_tab($tab, $amount, $phone, $name, $mail, trim((string)($_POST['note'] ?? '')));
    audit('paytab.start', $tab['slug'] . ' ' . money($amount));
    daraza_respond_then(['ok' => true, 'token' => $at['token'], 'amount' => $amount], fn() => daraza_run((int)$at['id']));
}
function page_paytab_status(array $a): void
{
    if (!preg_match('/^[a-f0-9]{24}$/', $a['token'])) pay_json(['error' => 'bad token'], 404);
    if (rate_limited('paypoll', 20000, 1800)) pay_json(['error' => 'slow down'], 429);
    try { daraza_expire_stale(); } catch (Throwable $e) {}
    $at = row('SELECT a.status, a.message, a.amount, a.token FROM pay_attempts a JOIN payment_tabs t ON t.id = a.tab_id WHERE a.token = ? AND t.slug = ?', [$a['token'], $a['slug']]);
    if (!$at) pay_json(['error' => 'not found'], 404);
    $msg = $at['message'];
    if ($at['status'] === 'unconfirmed') $msg = 'We could not confirm your payment yet. If money left your phone, please do not pay again. Quote ' . pay_receipt_code($at['token']) . ' to the team and we will confirm it.';
    pay_json(['status' => $at['status'], 'message' => $msg, 'amount' => money($at['amount']), 'receipt' => pay_receipt_code($at['token'])]);
}
/** After a tab payment lands: receipt to the payer, heads-up to the team. */
function paytab_notify(array $a): void
{
    $tab = row('SELECT title FROM payment_tabs WHERE id = ?', [$a['tab_id']]);
    $title = $tab['title'] ?? 'Payment';
    $code = pay_receipt_code((string)$a['token']);
    if (!empty($a['payer_email']))
        send_mail($a['payer_email'], "Payment received: $title ($code)", "Hello {$a['payer_name']},\n\nThank you. We received your payment of " . money($a['amount']) . " for $title.\n\nReceipt number: $code\n" .
            ($a['payer_note'] ? "For: {$a['payer_note']}\n" : '') . "\nThe Chill & Bite team will be in touch about your booking.");
    $to = setting('contact_email');
    if ($to !== '')
        send_mail($to, "New online payment: $title " . money($a['amount']), "$title\nAmount: " . money($a['amount']) . "\nFrom: {$a['payer_name']}, {$a['phone']}" . ($a['payer_email'] ? ", {$a['payer_email']}" : '') .
            ($a['payer_note'] ? "\nFor: {$a['payer_note']}" : '') . "\nReceipt: $code\n\nSee Admin > Collections.");
}

/* ================= Admin: payment tabs ================= */
function admin_paytabs(): void
{
    require_can('paytabs');
    try { $tabs = rows('SELECT * FROM payment_tabs ORDER BY sort, id'); }
    catch (PDOException $e) { if ($e->getCode() !== '42S02') throw $e; pay_ensure_schema(); $tabs = rows('SELECT * FROM payment_tabs ORDER BY sort, id'); }
    pay_ensure_schema();
    $totals = [];
    foreach (rows('SELECT tab_id, COUNT(*) n, COALESCE(SUM(amount),0) amt FROM payments WHERE tab_id IS NOT NULL GROUP BY tab_id') as $x) $totals[(int)$x['tab_id']] = $x;
    admin_view('paytabs', ['title' => 'Payment tabs', 'tabs' => $tabs, 'totals' => $totals, 'daraza_on' => daraza_enabled()]);
}
function admin_paytabs_save(): void
{
    require_can('paytabs');
    $notes = [];
    foreach ((array)($_POST['tab'] ?? []) as $id => $t) {
        $id = (int)$id; if (!$id || !val('SELECT id FROM payment_tabs WHERE id = ?', [$id])) continue;
        $amount = (int)preg_replace('/\D+/', '', (string)($t['amount'] ?? '0'));
        if ($amount > DARAZA_MAX) { $notes[] = 'The largest online payment is ' . money(DARAZA_MAX) . '.'; $amount = DARAZA_MAX; }
        $active = !empty($t['active']) ? 1 : 0;
        if ($active && $amount < DARAZA_MIN) { $active = 0; $notes[] = '"' . mb_substr((string)($t['title'] ?? ''), 0, 60) . '" stays off: set an amount of at least ' . money(DARAZA_MIN) . ' first.'; }
        $pages = array_values(array_intersect(array_keys(PAYTAB_PAGES), array_map('strval', (array)($t['pages'] ?? []))));
        q('UPDATE payment_tabs SET title = ?, blurb = ?, amount = ?, active = ?, pages = ? WHERE id = ?',
            [mb_substr(trim((string)($t['title'] ?? '')), 0, 120) ?: 'Payment', mb_substr(trim((string)($t['blurb'] ?? '')), 0, 255), $amount, $active, implode(',', $pages), $id]);
    }
    audit('paytabs.save', 'payment tabs updated');
    flash($notes ? 'Saved. ' . implode(' ', $notes) : 'Payment tabs saved.', $notes ? 'err' : 'ok');
    redirect('admin/pay-tabs');
}
function admin_paytab_new(): void
{
    require_can('paytabs');
    $title = mb_substr(trim((string)($_POST['title'] ?? '')), 0, 120);
    if (mb_strlen($title) < 3) { flash('Give the new tab a name.', 'err'); redirect('admin/pay-tabs'); }
    pay_ensure_schema();
    $slug = unique_slug('payment_tabs', $title);
    q('INSERT INTO payment_tabs (slug, title, sort) VALUES (?,?,?)', [$slug, $title, (int)val('SELECT COALESCE(MAX(sort),0)+1 FROM payment_tabs')]);
    audit('paytabs.create', $title);
    flash('Tab added. Set its amount, choose the pages and switch it on.');
    redirect('admin/pay-tabs');
}
function admin_paytab_delete(array $a): void
{
    require_can('paytabs');
    $id = (int)$a['id'];
    if ((int)val('SELECT COUNT(*) FROM payments WHERE tab_id = ?', [$id]) > 0) { q('UPDATE payment_tabs SET active = 0 WHERE id = ?', [$id]); flash('This tab has payments in the ledger, so it was switched off instead of deleted.'); }
    else { q('DELETE FROM payment_tabs WHERE id = ?', [$id]); audit('paytabs.delete', "#$id"); flash('Tab deleted.'); }
    redirect('admin/pay-tabs');
}
