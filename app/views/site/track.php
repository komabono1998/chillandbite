<section class="page-hero compact"><div class="wrap">
  <span class="eyebrow gold"><?= cth('track.hero.eyebrow') ?></span>
  <h1><?= cth('track.hero.heading') ?></h1>
  <p class="lead"><?= cth('track.hero.lead') ?></p>
</div></section>
<section class="section section-cream"><div class="narrow-wrap">
  <form class="form-card track-card" method="post" action="<?= url('track') ?>" novalidate><?= csrf_field() ?><?php honeypot(); ?>
    <div class="field">
      <label for="f_reference"><?= cth('track.form.label') ?></label>
      <input id="f_reference" name="reference" class="track-input" value="<?= h(old('reference')) ?>" required maxlength="14"
             autocomplete="off" autocapitalize="characters" spellcheck="false" inputmode="text" placeholder="CB7K3M9Q" autofocus>
    </div>
    <button class="btn btn-primary btn-lg btn-block"><?= cth('track.form.button') ?></button>
    <p class="small muted track-help"><?= cth('track.form.help') ?></p>
  </form>

  <div class="track-steps">
    <div><span>1</span><b>Enter your reference</b><small>It was shown when you booked.</small></div>
    <div><span>2</span><b>See your status</b><small>Pending, confirmed or cancelled.</small></div>
    <div><span>3</span><b>Download your ticket</b><small>Save it to your phone or print it.</small></div>
  </div>

  <p class="center muted small"><?= cth('track.form.contact') ?>
    <a href="<?= url('contact') ?>">Contact us</a><?php if (setting('whatsapp')): ?> or <a target="_blank" rel="noopener" href="<?= h(wa_link(setting('whatsapp'), 'Hello, I need help finding my booking reference.')) ?>">WhatsApp us</a><?php endif; ?>.</p>
</div></section>
