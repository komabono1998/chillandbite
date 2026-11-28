<div class="lg">
  <aside class="lg-art" style="--lg-photo:url('<?= h(photo(44)) ?>')">
    <div class="lg-art-in">
      <img class="lg-logo" src="<?= h(brand_logo()) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253">
      <h2>One more step.</h2>
      <p>For safety, every admin sign-in needs a one-time code sent to the owner's email.</p>
    </div>
  </aside>
  <section class="lg-side">
    <div class="lg-card">
      <img class="lg-logo-sm" src="<?= h(brand_logo()) ?>" alt="Chill &amp; Bite MEATINGS" width="160" height="126">
      <p class="lg-eyebrow">Two-step sign-in</p>
      <h1>Enter your code</h1>
      <p class="lg-sub">We emailed a 6-digit code to <b><?= h($masked) ?></b>. It expires in <span id="otpLeft"><?= (int)floor($left / 60) ?>:<?= str_pad((string)($left % 60), 2, '0', STR_PAD_LEFT) ?></span>.</p>
      <form method="post" action="<?= url('admin/verify') ?>"><?= csrf_field() ?>
        <label class="lg-field"><span>6-digit code</span>
          <span class="lg-input"><i aria-hidden="true">🔑</i><input name="code" inputmode="numeric" pattern="[0-9 ]{6,7}" maxlength="7" autocomplete="one-time-code" placeholder="123456" required autofocus style="letter-spacing:.35em;font-size:1.3rem"></span></label>
        <button class="btn btn-primary btn-block lg-submit">Verify and sign in</button>
      </form>
      <form method="post" action="<?= url('admin/verify/resend') ?>" style="margin-top:12px"><?= csrf_field() ?>
        <button class="btn btn-ghost btn-sm" id="otpResend"<?= $wait > 0 ? ' disabled' : '' ?>>Send a new code<?= $wait > 0 ? ' (<span id="otpWait">' . (int)$wait . '</span>s)' : '' ?></button>
      </form>
      <a class="lg-back" href="<?= url('admin/login') ?>">&larr; Start again</a>
      <p class="lg-note">Check the spam folder if it does not arrive within a minute.</p>
    </div>
  </section>
</div>
<script>
(function () {
  var left = <?= (int)$left ?>, el = document.getElementById('otpLeft'), w = <?= (int)$wait ?>, wb = document.getElementById('otpWait'), rb = document.getElementById('otpResend');
  setInterval(function () {
    if (left > 0) { left--; el.textContent = Math.floor(left / 60) + ':' + ('0' + (left % 60)).slice(-2); }
    if (w > 0) { w--; if (wb) wb.textContent = w; if (w === 0 && rb) { rb.disabled = false; rb.textContent = 'Send a new code'; } }
  }, 1000);
})();
</script>