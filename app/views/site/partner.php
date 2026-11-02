<section class="page-hero hero-dark"><div class="wrap">
  <span class="eyebrow gold"><?= cth('partner.hero.eyebrow') ?></span>
  <h1><?= cth('partner.hero.heading') ?></h1>
  <p class="lead"><?= cth('partner.hero.lead') ?></p>
  <div class="btn-row"><a class="btn btn-primary btn-lg" href="#apply"><?= cth('partner.hero.btn_apply') ?></a><a class="btn btn-outline btn-lg" href="#partners"><?= cth('partner.hero.btn_partners') ?></a></div>
</div></section>

<section class="section section-cream"><div class="wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('partner.why.eyebrow') ?></span><h2><?= cth('partner.why.heading') ?></h2></div>
  <div class="grid grid-4">
    <?php foreach (ctrows('partner.why.cards', 3) as [$ic, $t, $d]): ?>
      <div class="expect-card"><span class="ic" aria-hidden="true"><?= h($ic) ?></span><h4><?= h($t) ?></h4><p><?= h($d) ?></p></div>
    <?php endforeach; ?>
  </div>
  <div class="steps mt">
    <?php foreach (ctrows('partner.why.steps') as $i => [$t, $d]): ?>
      <div><b><?= $i + 1 ?></b><h4><?= h($t) ?></h4><p><?= h($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section section-dark" id="partners"><div class="wrap center">
  <span class="eyebrow gold"><?= cth('partner.partners.eyebrow') ?></span><h2><?= cth('partner.partners.heading') ?></h2>
  <?php if ($partners): ?><ul class="sponsor-row"><?php foreach ($partners as $s) sponsor_item($s); ?></ul>
  <?php else: ?><p class="lead narrow"><?= cth('partner.partners.empty') ?></p><?php endif; ?>
</div></section>

<section class="section" id="apply"><div class="narrow-wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('partner.apply.eyebrow') ?></span><h2><?= cth('partner.apply.heading') ?></h2>
    <p><?= cth('partner.apply.intro') ?></p></div>
  <form class="form-card" method="post" action="<?= url('partner') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="website_url" tabindex="-1" autocomplete="off"></label></div>
    <div class="form-grid">
      <?php field('company', 'Company / brand name', ['required' => true]);
      field('contact_name', 'Contact person', ['required' => true]);
      field('phone', 'Telephone', ['required' => true, 'type' => 'tel', 'attrs' => 'placeholder="+256 7XX XXX XXX"']);
      field('email', 'Email', ['required' => true, 'type' => 'email']);
      field('site', 'Website (optional)', ['type' => 'url', 'attrs' => 'placeholder="https://"']);
      field('industry', 'Industry');
      field('partnership_type', 'Type of partnership', ['required' => true, 'type' => 'select', 'options' => ['' => 'Choose...'] + array_combine(PARTNER_TYPES, PARTNER_TYPES)]); ?>
      <div class="field"><label for="f_logo">Company logo <span aria-hidden="true">*</span></label>
        <input id="f_logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" required>
        <small>PNG, JPG, WebP or GIF, max 5MB. A clear logo on a transparent or plain background works best.</small>
        <img id="logo-preview" alt="Logo preview" hidden></div>
    </div>
    <?php field('about', 'About your business', ['type' => 'textarea', 'required' => true, 'rows' => 4, 'help' => 'What you do, who your customers are and how long you have operated.']);
    field('proposal', 'How would you like to partner with us?', ['type' => 'textarea', 'rows' => 4, 'help' => 'Ideas, budget range, events you want to be part of, offers for our guests.']); ?>
    <label class="chk"><input type="checkbox" name="consent" value="1" required> I agree to be contacted about this application and to my logo being shown on the website if approved.</label>
    <button class="btn btn-primary btn-lg"><?= cth('partner.apply.button') ?></button>
  </form>
</div></section>
<script>
document.getElementById('f_logo').addEventListener('change', function () {
  var p = document.getElementById('logo-preview'), f = this.files[0];
  if (f && f.type.indexOf('image/') === 0) { p.src = URL.createObjectURL(f); p.hidden = false; } else { p.hidden = true; }
});
</script>
