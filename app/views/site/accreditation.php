<section class="page-hero hero-dark"><div class="wrap">
  <span class="eyebrow gold"><?= cth('accreditation.hero.eyebrow') ?></span>
  <h1><?= cth('accreditation.hero.heading') ?></h1>
  <p class="lead"><?= cth('accreditation.hero.lead') ?></p>
  <div class="btn-row"><a class="btn btn-primary btn-lg" href="#apply"><?= cth('accreditation.hero.btn_apply') ?></a><a class="btn btn-outline btn-lg" href="<?= url('news') ?>">Read The Gulu Chronicles</a></div>
</div></section>

<section class="section section-cream"><div class="wrap">
  <div class="section-head"><h2><?= cth('accreditation.who.heading') ?></h2></div>
  <div class="grid grid-4">
    <?php foreach (ctrows('accreditation.who.cards', 3) as [$ic, $t, $d]): ?>
      <div class="expect-card" data-reveal><span class="ic" aria-hidden="true"><?= h($ic) ?></span><h4><?= h($t) ?></h4><p><?= h($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section section-dark"><div class="wrap">
  <div class="section-head center"><span class="eyebrow gold">Benefits</span><h2><?= cth('accreditation.benefits.heading') ?></h2></div>
  <div class="ab-pillars acc-benefits">
    <?php foreach (ctrows('accreditation.benefits.cards', 3) as [$ic, $t, $d]): ?>
      <div class="acc-benefit" data-reveal><span class="ab-mv-ic" aria-hidden="true"><?= h($ic) ?></span><b><?= h($t) ?></b><span><?= h($d) ?></span></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section section-cream"><div class="wrap">
  <div class="section-head center"><span class="eyebrow">Process</span><h2><?= cth('accreditation.process.heading') ?></h2></div>
  <div class="steps acc-steps">
    <?php foreach (ctrows('accreditation.process.steps') as $i => [$t, $d]): ?>
      <div data-reveal><b><?= $i + 1 ?></b><h4><?= h($t) ?></h4><p><?= h($d) ?></p></div>
    <?php endforeach; ?>
  </div>
</div></section>

<section class="section section-dark"><div class="wrap acc-standards">
  <div>
    <span class="eyebrow gold">Standards</span>
    <h2><?= cth('accreditation.standards.heading') ?></h2>
    <p class="lead"><?= cth('accreditation.standards.intro') ?></p>
  </div>
  <ul class="ab-checks"><?php foreach (ctl('accreditation.standards.list') as $li): ?><li><?= h($li) ?></li><?php endforeach; ?></ul>
</div></section>

<section class="section section-cream" id="apply"><div class="narrow-wrap">
  <div class="section-head"><span class="eyebrow"><?= cth('accreditation.apply.eyebrow') ?></span><h2><?= cth('accreditation.apply.heading') ?></h2><p><?= cth('accreditation.apply.intro') ?></p></div>
  <form class="form-card" method="post" action="<?= url('accreditation') ?>" enctype="multipart/form-data" novalidate><?= csrf_field() ?>
    <div class="hp" aria-hidden="true"><label>Leave this empty<input type="text" name="hp_site" tabindex="-1" autocomplete="off"></label></div>
    <fieldset class="types"><legend>I am applying as <span aria-hidden="true">*</span></legend>
      <?php foreach (ACC_TYPES as $k => $label): ?>
        <label class="type-opt"><input type="radio" name="applicant_type" value="<?= h($k) ?>" <?= old('applicant_type') === $k ? 'checked' : '' ?> required><span><strong><?= h($label) ?></strong></span></label>
      <?php endforeach; ?>
    </fieldset>
    <div class="form-grid">
      <?php field('full_name', 'Your full name', ['required' => true]);
      field('organization', 'Blog, channel or media house name', ['help' => 'Required for media houses.']);
      field('email', 'Email', ['type' => 'email', 'required' => true, 'help' => 'Your login details are sent here if you are approved.']);
      field('phone', 'Telephone / WhatsApp', ['type' => 'tel', 'required' => true, 'attrs' => 'placeholder="+256 7XX XXX XXX"']);
      field('location', 'City and country');
      field('website', 'Website or blog link', ['type' => 'url', 'attrs' => 'placeholder="https://"']);
      field('socials', 'Social media links', ['help' => 'Facebook, Instagram, YouTube, X or TikTok pages.']);
      field('coverage', 'What do you cover?', ['help' => 'For example: Gulu news, music, business, sport, politics.']); ?>
    </div>
    <?php field('samples', 'Links to your published work', ['type' => 'textarea', 'required' => true, 'rows' => 4, 'help' => 'Paste 2 to 5 links to stories, blog posts or videos. One per line.']);
    field('about', 'About you and your work', ['type' => 'textarea', 'required' => true, 'rows' => 4, 'help' => 'Your experience, audience size and why you want to publish on The Gulu Chronicles.']); ?>
    <div class="field"><label for="f_document">ID, press card or registration certificate (optional)</label>
      <input id="f_document" type="file" name="document" accept="image/png,image/jpeg,image/webp">
      <small>A photo or scan helps us verify you faster. JPG, PNG or WebP, max 5MB. Only our editors can see it.</small></div>
    <label class="chk"><input type="checkbox" name="standards" value="1" required> I have read and agree to the editorial standards above, and I understand that every story is reviewed by an editor before publication.</label>
    <button class="btn btn-primary btn-lg"><?= cth('accreditation.apply.button') ?></button>
  </form>
</div></section>
