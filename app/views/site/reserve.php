<section class="page-hero compact"><div class="wrap"><span class="eyebrow gold">Book &amp; reserve</span><h1><?= h($e['title']) ?></h1>
  <p class="lead"><?= fmt_date($e['start_at'], 'l, j F Y') ?> · <?= fmt_time($e['start_at']) ?> · <?= h($e['venue']) ?>, <?= h($e['location']) ?></p></div></section>
<section class="section section-cream"><div class="wrap narrow-wrap">
<?php if (setting('contact_phone')): ?><p class="small">Bookings help: call <a href="tel:<?= h(preg_replace('/\s+/', '', setting('contact_phone'))) ?>"><?= h(setting('contact_phone')) ?></a><?php if (setting('whatsapp')): ?> or <a target="_blank" rel="noopener" href="<?= h(wa_link(setting('whatsapp'), 'Hello, I need help booking ' . $e['title'])) ?>">WhatsApp us</a><?php endif; ?>.</p><?php endif; ?>
<form class="rs-track" method="post" action="<?= url('track') ?>" novalidate aria-label="Track a reservation"><?= csrf_field() ?><?php honeypot(); ?>
  <div class="rs-track-text"><b>🔎 Already reserved?</b><span>Enter your booking code to see your status and get your ticket once approved.</span></div>
  <div class="rs-track-row">
    <input name="reference" maxlength="14" placeholder="e.g. CB7K3M9Q" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-label="Booking code" required>
    <button class="btn btn-primary" name="go" value="status">Track status</button>
    <button class="btn btn-outline-dark" name="go" value="ticket" title="Opens your ticket if your booking is approved">🎟️ Get my ticket</button>
  </div>
</form>
<?php if (!$open): ?>
  <div class="alert alert-err">Sorry, reservations are not open for this event right now.</div>
  <p><a class="btn btn-outline-dark" href="<?= url('events') ?>">Browse other events</a></p>
<?php elseif (!$options): ?>
  <div class="alert alert-err">Online reservations are not set up for this event yet. Please <a href="<?= url('contact') ?>">contact us</a>.</div>
<?php elseif ($step === 'review'): ?>
  <div class="form-card">
    <h2>Review your booking</h2>
    <dl class="summary">
      <dt>Reservation</dt><dd><?= h(RES_TYPES[$data['type']]) ?></dd>
      <dt>Name</dt><dd><?= h($data['full_name']) ?></dd>
      <dt>Phone</dt><dd><?= h($data['phone']) ?></dd>
      <?php if ($data['email']): ?><dt>Email</dt><dd><?= h($data['email']) ?></dd><?php endif; ?>
      <?php if ($data['company']): ?><dt>Company</dt><dd><?= h($data['company']) ?></dd><?php endif; ?>
      <?php if (!empty($data['package'])): ?><dt>Table package</dt><dd><?= h($data['package']) ?></dd><?php endif; ?>
      <?php if (empty($data["package"]) && ($opt["price_per_guest"] ?? 0) > 0): ?><dt>Guests</dt><dd><?= (int)$data["guests"] ?></dd><?php endif; ?>
      <?php if ($data['seating']): ?><dt>Seating</dt><dd><?= h($data['seating']) ?></dd><?php endif; ?>
      <?php if ($data['requests']): ?><dt>Requests</dt><dd><?= h($data['requests']) ?></dd><?php endif; ?>
      <dt>Amount</dt><dd><strong><?= $data['amount'] > 0 ? money($data['amount']) : 'Free' ?></strong></dd>
    </dl>
    <p class="small muted"><?php if ($data['amount'] > 0): ?>Payment is required to secure your place. The event team will contact you with payment instructions and your booking stays pending until payment is recorded.
      <?php elseif ($e['booking_mode'] === 'approval'): ?>This booking is submitted for approval by the event team.
      <?php else: ?>Your place is confirmed instantly while capacity remains.<?php endif; ?></p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="step" value="confirm">
      <?php foreach (['type', 'full_name', 'phone', 'email', 'company', 'seating', 'package', 'requests', 'guests'] as $k): ?><input type="hidden" name="<?= $k ?>" value="<?= h($data[$k]) ?>"><?php endforeach; ?>
      <div class="btn-row"><button class="btn btn-primary btn-lg">Confirm reservation</button><button class="btn btn-ghost-dark" type="button" onclick="history.back()">Edit details</button></div>
    </form>
  </div>
<?php else: ?>
  <form method="post" class="form-card" id="reserve-form" novalidate>
    <?= csrf_field() ?><?php honeypot(); ?>
    <h2>Your reservation</h2>
    <?php foreach ($errors ?? [] as $er): ?><div class="alert alert-err" role="alert"><?= h($er) ?></div><?php endforeach; ?>
    <?php if ($remaining !== null): ?><p class="small muted"><?= $remaining ?> places remaining.</p><?php endif; ?>
    <fieldset class="types"><legend>Choose a reservation type</legend>
      <?php foreach ($options as $o): ?>
        <label class="type-opt"><input type="radio" name="type" value="<?= h($o['type']) ?>" data-min="<?= $o['min_guests'] ?>" data-max="<?= $o['max_guests'] ?>" data-price="<?= (int)$o['price_per_guest'] ?>" <?= ($data['type'] ?? '') === $o['type'] ? 'checked' : '' ?> required>
          <span><strong><?= h(RES_TYPES[$o['type']]) ?></strong><small><?= h(RES_TYPE_HINTS[$o['type']]) ?> · <?= ($o['type'] === 'table' && !empty($packages)) ? 'packages from ' . money(min(array_column($packages, 'price'))) . ' per table' : ($o['price_per_guest'] > 0 ? money($o['price_per_guest']) . ' per guest' : 'Free') ?></small></span></label>
      <?php endforeach; ?>
    </fieldset>
    <?php if (!empty($packages)): ?>
    <fieldset class="types pkg-pick" id="pkg-box"><legend>Table packages and rates</legend>
      <p class="small muted">Prices are per table. Choosing a package selects the Table Reservation type for you.</p>
      <div class="pkg-options">
      <?php foreach ($packages as $p): ?>
        <label class="pkg-opt"><input type="radio" name="package" value="<?= h($p['name']) ?>" data-price="<?= (int)$p['price'] ?>" <?= ($data['package'] ?? '') === $p['name'] ? 'checked' : '' ?>>
          <span class="pkg-opt-name"><?= h($p['name']) ?></span><b><?= money($p['price']) ?></b><small>per table</small></label>
      <?php endforeach; ?>
      </div>
    </fieldset>
    <?php endif; ?>
    <div class="form-grid">
      <?php $dv = fn($k) => $data[$k] ?? ''; ?>
      <?php field('full_name', 'Full name', ['required' => true, 'value' => $dv('full_name')]); ?>
      <?php field('phone', 'Telephone number', ['required' => true, 'type' => 'tel', 'value' => $dv('phone'), 'attrs' => 'placeholder="+256 7XX XXX XXX"']); ?>
      <?php field('email', 'Email (optional)', ['type' => 'email', 'value' => $dv('email'), 'help' => 'We email your confirmation and ticket here once your booking is approved.']); ?>
      <?php field('guests', 'Number of guests', ['type' => 'number', 'required' => true, 'value' => $dv('guests') ?: 1, 'attrs' => 'min="1" max="200"', 'class' => 'guest-field']); ?>
      <?php field('company', 'Company name (corporate bookings)', ['value' => $dv('company'), 'class' => 'corp']); ?>
      <?php field('seating', 'Preferred seating / table arrangement', ['value' => $dv('seating')]); ?>
    </div>
    <?php field('requests', 'Special requests', ['type' => 'textarea', 'rows' => 3, 'value' => $dv('requests')]); ?>
    <p class="total" id="total" aria-live="polite"></p>
    <button class="btn btn-primary btn-lg">Review booking</button>
  </form>
<?php endif; ?>
</div></section>
