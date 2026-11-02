<section class="page-hero hero-dark"><div class="wrap">
  <span class="eyebrow gold">Partner with us</span>
  <h1>Grow your brand with Chill &amp; Bite</h1>
  <p class="lead">Join the companies putting their name in front of Gulu City's professionals, entrepreneurs, creatives and entertainment lovers, every month.</p>
  <div class="btn-row"><a class="btn btn-primary btn-lg" href="#apply">Apply to partner</a><a class="btn btn-outline btn-lg" href="#partners">See our partners</a></div>
</div></section>

<section class="section section-cream"><div class="wrap">
  <div class="section-head"><span class="eyebrow">Why partner</span><h2>What your brand gets</h2></div>
  <div class="grid grid-4">
    <?php foreach ([
      ['📣', 'Visibility', 'Your logo on our website, event materials and social media.'],
      ['🤝', 'Networking', 'Meet decision-makers, customers and other brands at every event.'],
      ['🎯', 'Market your offers', 'Promote your products, services and events to an engaged crowd.'],
      ['🏆', 'Credibility', 'Be seen alongside Gulu City\'s most talked-about monthly experience.']] as [$ic, $t, $d]): ?>
      <div class="expect-card"><span class="ic" aria-hidden="true"><?= $ic ?></span><h4><?= h($t) ?></h4><p><?= h($d) ?></p></div>
    <?php endforeach; ?>
  </div>
  <div class="steps mt">
    <div><b>1</b><h4>Apply</h4><p>Send your details and company logo using the form below.</p></div>
    <div><b>2</b><h4>We review</h4><p>Our partnerships team reviews every application and contacts you.</p></div>
    <div><b>3</b><h4>Go live</h4><p>Once approved, your logo appears under Our Partners on the website.</p></div>
  </div>
</div></section>

<section class="section section-dark" id="partners"><div class="wrap center">
  <span class="eyebrow gold">Our partners</span><h2>Brands that grow with us</h2>
  <?php if ($partners): ?><ul class="sponsor-row"><?php foreach ($partners as $s) sponsor_item($s); ?></ul>
  <?php else: ?><p class="lead narrow">Be the first partner to be featured here.</p><?php endif; ?>
</div></section>

<section class="section" id="apply"><div class="narrow-wrap">
  <div class="section-head"><span class="eyebrow">Partnership application</span><h2>Apply to become a partner</h2>
    <p>Fields marked * are required. Approved partners are featured with their logo and a link to their website.</p></div>
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
    <button class="btn btn-primary btn-lg">Submit application</button>
  </form>
</div></section>
<script>
document.getElementById('f_logo').addEventListener('change', function () {
  var p = document.getElementById('logo-preview'), f = this.files[0];
  if (f && f.type.indexOf('image/') === 0) { p.src = URL.createObjectURL(f); p.hidden = false; } else { p.hidden = true; }
});
</script>
