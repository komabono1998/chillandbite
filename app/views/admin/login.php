<div class="lg">
  <aside class="lg-art" style="--lg-photo:url('<?= h(photo(44)) ?>')">
    <div class="lg-art-in">
      <img class="lg-logo" src="<?= h(brand_logo()) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253">
      <h2>Run the show,<br>from one place.</h2>
      <p>Bookings, tickets, events, the website and The Gulu Chronicles, all managed by your team in one dashboard.</p>
      <ul class="lg-points">
        <li><span aria-hidden="true">🎟️</span>Reservations &amp; tickets</li>
        <li><span aria-hidden="true">✅</span>Door check-in</li>
        <li><span aria-hidden="true">📰</span>News &amp; contributors</li>
        <li><span aria-hidden="true">📊</span>Reports &amp; exports</li>
      </ul>
    </div>
  </aside>
  <section class="lg-side">
    <div class="lg-card">
      <img class="lg-logo-sm" src="<?= h(brand_logo()) ?>" alt="Chill &amp; Bite MEATINGS" width="160" height="126">
      <p class="lg-eyebrow">Staff &amp; contributors</p>
      <h1>Welcome back</h1>
      <p class="lg-sub">Sign in to manage Chill &amp; Bite MEATINGS.</p>
      <form method="post" action="<?= url('admin/login') ?>" id="lgForm"><?= csrf_field() ?>
        <label class="lg-field"><span>Email</span>
          <span class="lg-input"><i aria-hidden="true">✉️</i><input type="email" name="email" required autocomplete="username" placeholder="you@example.com" autofocus></span></label>
        <label class="lg-field"><span>Password</span>
          <span class="lg-input"><i aria-hidden="true">🔒</i><input type="password" name="password" id="lgPw" required autocomplete="current-password" placeholder="Your password">
            <button type="button" class="lg-eye" id="lgEye" aria-label="Show password" aria-pressed="false">Show</button></span>
          <small class="lg-caps" id="lgCaps" hidden>Caps Lock is on</small></label>
        <button class="btn btn-primary btn-block lg-submit" id="lgBtn">Sign in</button>
      </form>
      <a class="lg-back" href="<?= url() ?>">&larr; Back to website</a>
      <p class="lg-note">Authorised staff and accredited contributors only. Want to write for The Gulu Chronicles? <a href="<?= url('accreditation') ?>">Apply for accreditation</a>.</p>
    </div>
  </section>
</div>
<script>
(function () {
  var pw = document.getElementById('lgPw'), eye = document.getElementById('lgEye'), caps = document.getElementById('lgCaps'), f = document.getElementById('lgForm'), b = document.getElementById('lgBtn');
  eye.addEventListener('click', function () { var s = pw.type === 'password'; pw.type = s ? 'text' : 'password'; eye.textContent = s ? 'Hide' : 'Show'; eye.setAttribute('aria-pressed', s ? 'true' : 'false'); eye.setAttribute('aria-label', s ? 'Hide password' : 'Show password'); });
  pw.addEventListener('keyup', function (e) { caps.hidden = !(e.getModifierState && e.getModifierState('CapsLock')); });
  pw.addEventListener('blur', function () { caps.hidden = true; });
  f.addEventListener('submit', function () { b.disabled = true; b.textContent = 'Signing in…'; });
})();
</script>
