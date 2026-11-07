<?php
$dv = fn($k) => $data[$k] ?? '';
$payOn = daraza_enabled();
$mode = in_array($dv('mode'), ['ticket', 'reserve'], true) ? $dv('mode') : ($payOn ? 'ticket' : 'reserve');
if (!$payOn) $mode = 'reserve';
// the cards: table packages first, then the other reservation types
$cards = [];
$tableOpt = null; foreach ($options as $o) if ($o['type'] === 'table') $tableOpt = $o;
if ($tableOpt && !empty($packages)) {
    foreach ($packages as $p) $cards[] = ['v' => 'pkg:' . $p['name'], 'title' => $p['name'], 'price' => (int)$p['price'], 'flat' => 1, 'min' => (int)$tableOpt['min_guests'], 'max' => (int)$tableOpt['max_guests'],
        'sub' => 'per table', 'icon' => '🪑'];
}
foreach ($options as $o) {
    if ($o['type'] === 'table' && !empty($packages)) continue;
    $pp = (int)$o['price_per_guest'];
    $cards[] = ['v' => 'type:' . $o['type'], 'title' => RES_TYPES[$o['type']], 'price' => $pp, 'flat' => 0, 'min' => (int)$o['min_guests'], 'max' => (int)$o['max_guests'],
        'sub' => $pp > 0 ? 'per guest' : RES_TYPE_HINTS[$o['type']], 'icon' => ['individual' => '🙋', 'couple' => '💑', 'group' => '👯', 'corporate' => '🏢', 'vip' => '👑', 'table' => '🪑'][$o['type']] ?? '🎟️'];
}
$sel = $dv('choice');
if ($sel === '' && $dv('package') !== '') $sel = 'pkg:' . $dv('package');
elseif ($sel === '' && $dv('type') !== '') $sel = 'type:' . $dv('type');
if ($sel === '' && count($cards) === 1) $sel = $cards[0]['v'];
?>
<section class="page-hero compact"><div class="wrap"><span class="eyebrow gold">Book &amp; reserve</span><h1><?= h($e['title']) ?></h1>
  <p class="lead"><?= fmt_date($e['start_at'], 'l, j F Y') ?> · <?= fmt_time($e['start_at']) ?> · <?= h($e['venue']) ?>, <?= h($e['location']) ?></p></div></section>
<section class="section section-cream"><div class="wrap narrow-wrap">
<?php if (!$open): ?>
  <div class="alert alert-err">Sorry, reservations are not open for this event right now.</div>
  <p><a class="btn btn-outline-dark" href="<?= url('events') ?>">Browse other events</a></p>
<?php elseif (!$cards): ?>
  <div class="alert alert-err">Online reservations are not set up for this event yet. Please <a href="<?= url('contact') ?>">contact us</a>.</div>
<?php else: ?>
  <?php if ($payOn): ?>
  <div class="rs-modes" role="tablist" aria-label="How do you want to book?">
    <button type="button" class="rs-mode<?= $mode === 'ticket' ? ' on' : '' ?>" role="tab" data-mode="ticket" aria-selected="<?= $mode === 'ticket' ? 'true' : 'false' ?>">
      <span class="rs-mode-ic" aria-hidden="true">🎟️</span><span><b>Get my ticket</b><small>Pay now with MTN or Airtel. Your ticket comes straight away.</small></span></button>
    <button type="button" class="rs-mode<?= $mode === 'reserve' ? ' on' : '' ?>" role="tab" data-mode="reserve" aria-selected="<?= $mode === 'reserve' ? 'true' : 'false' ?>">
      <span class="rs-mode-ic" aria-hidden="true">🪑</span><span><b>Reserve a table</b><small>Get a booking number now and pay later.</small></span></button>
  </div>
  <?php endif; ?>

  <form method="post" action="<?= url('events/' . $e['slug'] . '/reserve') ?>" class="form-card rs-card" id="rsForm" novalidate>
    <?= csrf_field() ?><?php honeypot(); ?>
    <?php foreach ($errors ?? [] as $er): ?><div class="alert alert-err" role="alert"><?= h($er) ?></div><?php endforeach; ?>
    <div class="alert alert-err" id="rsErr" role="alert" hidden></div>
    <?php if ($remaining !== null): ?><p class="small muted" style="margin:0 0 10px"><?= (int)$remaining ?> places remaining.</p><?php endif; ?>

    <h2 class="rs-h"><span>1</span> What would you like?</h2>
    <div class="rs-cards">
      <?php foreach ($cards as $c): ?>
        <label class="rs-pick"><input type="radio" name="choice" value="<?= h($c['v']) ?>" data-price="<?= $c['price'] ?>" data-flat="<?= $c['flat'] ?>" data-min="<?= $c['min'] ?>" data-max="<?= $c['max'] ?>" data-corp="<?= $c['v'] === 'type:corporate' ? 1 : 0 ?>" <?= $sel === $c['v'] ? 'checked' : '' ?> required>
          <span class="rs-pick-ic" aria-hidden="true"><?= $c['icon'] ?></span><span class="rs-pick-name"><?= h($c['title']) ?></span>
          <b><?= $c['price'] > 0 ? h(money($c['price'])) : 'Free' ?></b><small><?= h($c['sub']) ?></small></label>
      <?php endforeach; ?>
    </div>

    <h2 class="rs-h"><span>2</span> Your details</h2>
    <div class="form-grid">
      <?php field('full_name', 'Full name', ['required' => true, 'value' => $dv('full_name')]); ?>
      <div class="field"><label for="f_phone"><span id="rsPhoneLabel">Phone number</span> <span aria-hidden="true">*</span></label><input id="f_phone" name="phone" type="tel" inputmode="tel" required value="<?= h($dv('phone')) ?>" placeholder="0772 123 456"><small class="help" id="rsPhoneHelp"></small></div>
      <?php field('email', 'Email (optional)', ['type' => 'email', 'value' => $dv('email'), 'help' => 'We email your ticket and booking number here.']); ?>
      <?php field('guests', 'Number of guests', ['type' => 'number', 'required' => true, 'value' => $dv('guests') ?: 1, 'attrs' => 'min="1" max="200"', 'class' => 'guest-field']); ?>
      <?php field('company', 'Company name', ['value' => $dv('company'), 'class' => 'corp']); ?>
    </div>
    <details class="rs-more"><summary>Add a note (seating, special requests)</summary>
      <?php field('seating', 'Preferred seating / table arrangement', ['value' => $dv('seating')]); ?>
      <?php field('requests', 'Special requests', ['type' => 'textarea', 'rows' => 3, 'value' => $dv('requests')]); ?>
    </details>

    <div class="rs-bottom">
      <p class="rs-total" id="rsTotal" aria-live="polite"></p>
      <button class="btn btn-primary btn-lg rs-go" id="rsGo" type="submit"><span class="pay-btn-spin" aria-hidden="true"></span><span id="rsGoText">Continue</span></button>
      <p class="small muted rs-note" id="rsNote"></p>
    </div>
  </form>

  <div class="form-card rs-state" id="rsState" hidden aria-live="polite">
    <div class="pay-spin" id="rsSpin" aria-hidden="true"></div>
    <h2 id="rsStateTitle">Check your phone</h2>
    <p id="rsStateText">Approve the payment prompt and enter your PIN. This can take up to 3 minutes. Please keep this page open.</p>
    <span class="pay-timer" id="rsTimer">0:00</span>
    <ol class="pay-steps" id="rsSteps" hidden><li><span>Look for the payment prompt on <b id="rsShow"></b></span></li><li>Enter your Mobile Money PIN to approve</li><li>Stay on this page. Your ticket appears here automatically.</li></ol>
    <div class="rs-ref" id="rsRef" hidden>Your booking number <b id="rsRefNo"></b></div>
    <div class="btn-row rs-actions" id="rsActions"></div>
  </div>

  <form class="rs-track" method="post" action="<?= url('track') ?>" novalidate aria-label="Track a reservation"><?= csrf_field() ?><?php honeypot(); ?>
    <div class="rs-track-text"><b>🔎 Already booked?</b><span>Enter your booking number to check, pay or get your ticket.</span></div>
    <div class="rs-track-row">
      <input name="reference" maxlength="14" placeholder="e.g. CB7K3M9Q" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-label="Booking number" required>
      <button class="btn btn-outline-dark" name="go" value="status">Find my booking</button>
    </div>
  </form>
  <?php if (setting('contact_phone')): ?><p class="small" style="margin-top:14px">Need help? Call <a href="tel:<?= h(preg_replace('/\s+/', '', setting('contact_phone'))) ?>"><?= h(setting('contact_phone')) ?></a><?php if (setting('whatsapp')): ?> or <a target="_blank" rel="noopener" href="<?= h(wa_link(setting('whatsapp'), 'Hello, I need help booking ' . $e['title'])) ?>">WhatsApp us</a><?php endif; ?>.</p><?php endif; ?>

<script>
(function () {
  var form = document.getElementById('rsForm'); if (!form) return;
  var payOn = <?= $payOn ? 'true' : 'false' ?>, mode = <?= json_encode($mode) ?>;
  var go = document.getElementById('rsGo'), goText = document.getElementById('rsGoText'), total = document.getElementById('rsTotal'), note = document.getElementById('rsNote');
  var err = document.getElementById('rsErr'), guests = form.querySelector('[name=guests]'), gField = guests.closest('.field'), corp = form.querySelector('.corp');
  var phoneLabel = document.getElementById('rsPhoneLabel'), phoneHelp = document.getElementById('rsPhoneHelp');
  var st = document.getElementById('rsState'), stTitle = document.getElementById('rsStateTitle'), stText = document.getElementById('rsStateText'), spin = document.getElementById('rsSpin');
  var timer = document.getElementById('rsTimer'), acts = document.getElementById('rsActions'), refBox = document.getElementById('rsRef'), refNo = document.getElementById('rsRefNo');
  var ticketUrl = <?= json_encode(url('events/' . $e['slug'] . '/ticket')) ?>, bookingBase = <?= json_encode(url('booking/')) ?>, csrf = <?= json_encode(csrf_token()) ?>;
  var tick = null, poll = null, t0 = 0, curRef = '';
  function money(n) { return 'UGX ' + Math.round(n).toLocaleString(); }
  function sel() { return form.querySelector('[name=choice]:checked'); }
  function amount() {
    var c = sel(); if (!c) return null; var p = +c.dataset.price, min = +c.dataset.min, max = +c.dataset.max;
    if (c.dataset.flat === '1') return p;
    return p * (p > 0 && min < max ? Math.max(1, +guests.value || 1) : min);
  }
  function refresh() {
    var c = sel(), a = amount(), needCount = c && c.dataset.flat !== '1' && +c.dataset.price > 0 && +c.dataset.min < +c.dataset.max;
    gField.style.display = needCount ? 'block' : 'none'; guests.required = !!needCount; if (c && !needCount) guests.value = c.dataset.min;
    corp.style.display = c && c.dataset.corp === '1' ? 'block' : 'none';
    var paid = a !== null && a > 0, ticket = payOn && mode === 'ticket';
    phoneLabel.textContent = ticket && paid ? 'Mobile money number (MTN or Airtel)' : 'Phone number';
    phoneHelp.textContent = ticket && paid ? 'A payment prompt is sent to this phone. Enter your PIN to approve.' : '';
    total.textContent = a === null ? 'Choose an option above' : (paid ? 'Total ' + money(a) : 'Free');
    goText.innerHTML = a === null ? 'Choose an option' : (ticket ? (paid ? 'Pay ' + money(a) + '<span class="hide-m"> &amp; get my ticket</span>' : 'Get my free ticket') : 'Reserve<span class="hide-m"> &amp; get my booking number</span>');
    note.textContent = ticket ? (paid ? 'You get your ticket the moment the payment is received.' : '') :
      (paid ? 'No payment now. You can pay any time with MTN or Airtel, or the team confirms it when they record your payment.' : '');
  }
  document.querySelectorAll('.rs-mode').forEach(function (b) { b.addEventListener('click', function () {
    mode = b.dataset.mode; document.querySelectorAll('.rs-mode').forEach(function (x) { var on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-selected', on); }); refresh(); }); });
  form.addEventListener('change', refresh); form.addEventListener('input', refresh); refresh();

  function showErr(m) { err.innerHTML = ''; (Array.isArray(m) ? m : [m]).forEach(function (t) { var d = document.createElement('div'); d.textContent = t; err.appendChild(d); }); err.hidden = false; err.scrollIntoView({ block: 'nearest' }); }
  function stopTimers() { clearInterval(tick); clearTimeout(poll); }
  function fmt(s) { return Math.floor(s / 60) + ':' + ('0' + (s % 60)).slice(-2); }
  function btn(label, href, cls, fn) { var e = document.createElement(href ? 'a' : 'button'); e.className = 'btn ' + cls; e.textContent = label; if (href) e.href = href; else { e.type = 'button'; e.onclick = fn; } acts.appendChild(e); return e; }
  function showState(ok, title, text, extra) {
    form.hidden = true; var tr = document.querySelector('.rs-track'); if (tr) tr.hidden = true; st.hidden = false; st.className = 'form-card rs-state' + (ok === true ? ' pay-ok' : ok === false ? ' pay-bad' : ''); stTitle.textContent = title; stText.textContent = text; acts.innerHTML = '';
    spin.hidden = ok !== null; timer.hidden = ok !== null; document.getElementById('rsSteps').hidden = !(ok === null && title === 'Check your phone'); document.getElementById('rsShow').textContent = form.querySelector('[name=phone]').value; if (curRef) { refBox.hidden = false; refNo.textContent = curRef; }
    st.scrollIntoView({ block: 'start', behavior: 'smooth' });
  }
  function waiting(ref, token) {
    curRef = ref; showState(null, 'Check your phone', 'Approve the payment prompt and enter your PIN. This can take up to 3 minutes. Please keep this page open.');
    t0 = Date.now(); timer.textContent = '0:00'; stopTimers(); tick = setInterval(function () { timer.textContent = fmt(Math.round((Date.now() - t0) / 1000)); }, 1000); check(ref, token);
  }
  function retry() {
    var fd = new FormData(); fd.append('_csrf', csrf); fd.append('phone', form.querySelector('[name=phone]').value); fd.append('email', form.querySelector('[name=email]').value);
    showState(null, 'Starting payment...', 'One moment.');
    fetch(bookingBase + curRef + '/pay', { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.ok) waiting(curRef, j.token); else failed(j.error || 'Could not start the payment.');
    }).catch(function () { failed('Network problem. Please check your connection.'); });
  }
  function failed(msg) {
    stopTimers(); showState(false, 'Payment not completed', msg);
    btn('Try again', null, 'btn-primary', retry); btn('Pay later from my booking', bookingBase + curRef, 'btn-ghost-dark');
  }
  function done(j) {
    stopTimers(); showState(true, "You're booked! 🎉", 'Your ticket is ready. ' + (form.querySelector('[name=email]').value ? 'We also emailed it to you.' : 'Save or download it now.'));
    if (j.ticket) { btn('Open my ticket', j.ticket, 'btn-primary'); btn('Download ticket', j.download, 'btn-ghost-dark'); } else btn('View my booking', bookingBase + curRef, 'btn-primary');
  }
  function check(ref, token) {
    fetch(bookingBase + ref + '/pay/' + token, { cache: 'no-store' }).then(function (r) { return r.json(); }).then(function (j) {
      if (j.status === 'success') return done(j);
      if (j.status === 'failed') return failed(j.message || 'The payment was not approved.');
      if (j.status === 'unconfirmed') { stopTimers(); showState(false, 'Waiting for confirmation', j.message); btn('View my booking', bookingBase + ref, 'btn-primary'); return; }
      poll = setTimeout(function () { check(ref, token); }, 3000);
    }).catch(function () { poll = setTimeout(function () { check(ref, token); }, 5000); });
  }
  form.addEventListener('submit', function (e) {
    if (!(payOn && mode === 'ticket') || !window.fetch) return;        // "Reserve a table" is a normal form post
    e.preventDefault(); err.hidden = true;
    if (!sel()) return showErr('Please choose what you would like.');
    var fd = new FormData(form); go.disabled = true; go.classList.add('is-busy');
    fetch(ticketUrl, { method: 'POST', body: fd, headers: { 'Accept': 'application/json' } }).then(function (r) { return r.json(); }).then(function (j) {
      go.disabled = false; go.classList.remove('is-busy');
      if (!j.ok) return showErr(j.errors || [j.error || 'Something went wrong. Please try again.']);
      curRef = j.ref;
      if (j.free) { if (j.ticket) return done(j); showState(true, 'Booking received', 'Your booking number is ' + j.ref + '. The team will confirm it shortly.'); btn('View my booking', j.booking, 'btn-primary'); return; }
      waiting(j.ref, j.token);
    }).catch(function () { go.disabled = false; go.classList.remove('is-busy'); showErr('Network problem. Please check your connection and try again.'); });
  });
})();
</script>
<?php endif; ?>
</div></section>
