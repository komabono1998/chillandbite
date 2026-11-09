<h1>Website settings</h1>
<form class="panel" method="post" action="<?= url('admin/settings') ?>"><?= csrf_field() ?>
  <label class="chk"><input type="checkbox" name="announcement_on" value="1"<?= setting('announcement_on', '1') === '1' ? ' checked' : '' ?>> Show announcement bar</label>
  <?php field('announcement', 'Announcement text', ['value' => setting('announcement')]); ?>
  <div class="form-grid"><?php
  foreach (['site_name' => 'Site name', 'tagline' => 'Tagline', 'venue' => 'Venue', 'location' => 'Location', 'contact_phone' => 'Contact phone', 'contact_email' => 'Contact email', 'whatsapp' => 'WhatsApp number (with country code)', 'whatsapp_group' => 'WhatsApp group invite link',
            'social_facebook' => 'Facebook URL', 'social_instagram' => 'Instagram URL', 'social_youtube' => 'YouTube URL', 'social_tiktok' => 'TikTok URL', 'social_x' => 'X URL'] as $k => $l) field($k, $l, ['value' => setting($k)]); ?></div>
  <?php field('host_bio', 'Host biography (homepage)', ['type' => 'textarea', 'rows' => 4, 'value' => setting('host_bio')]); ?>
  <h2 id="credit" style="margin:24px 0 6px;font-size:1.1rem">Footer credit line</h2>
  <p class="small muted" style="margin:0 0 10px">The small line at the very bottom of every page, for example &ldquo;Designed &amp; maintained by Santos Jr | BONO TECHNOLOGIES&rdquo;. Leave a box empty to leave that part out.</p>
  <label class="chk"><input type="checkbox" name="credit_on" value="1"<?= setting('credit_on', '1') === '1' ? ' checked' : '' ?>> Show the credit line</label>
  <div class="form-grid">
    <?php field('credit_prefix', 'Text before the name', ['value' => setting('credit_prefix', 'Designed & maintained by'), 'attrs' => 'maxlength="80" data-credit="p"']);
    field('credit_name', 'Name', ['value' => setting('credit_name', 'Santos Jr'), 'attrs' => 'maxlength="80" data-credit="n"']);
    field('credit_org', 'Company', ['value' => setting('credit_org', 'BONO TECHNOLOGIES'), 'attrs' => 'maxlength="80" data-credit="o"']);
    field('credit_url', 'Link on the name (optional, starts with https://)', ['value' => setting('credit_url'), 'attrs' => 'maxlength="300" placeholder="https://"']); ?>
  </div>
  <p class="small" style="margin:6px 0 16px"><b>Preview:</b> <span id="creditPreview" style="color:#555"></span></p>
  <script>
  (function () {
    var f = {}; document.querySelectorAll('[data-credit]').forEach(function (i) { f[i.dataset.credit] = i; });
    var out = document.getElementById('creditPreview'), on = document.querySelector('[name=credit_on]');
    function show() { var p = f.p.value.trim(), n = f.n.value.trim(), o = f.o.value.trim(); var parts = [p, n && o ? n + ' | ' + o : (n || o)].filter(Boolean); out.textContent = on.checked ? (parts.join(' ') || '(nothing shown)') : '(hidden)'; }
    document.querySelectorAll('[data-credit], [name=credit_on]').forEach(function (i) { i.addEventListener('input', show); i.addEventListener('change', show); }); show();
  })();
  </script>
  <button class="btn btn-primary">Save settings</button></form>

<form class="panel" method="post" action="<?= url('admin/system/migrate') ?>" onsubmit="return confirm('Update the database now? This is safe and keeps all your data.')"><?= csrf_field() ?>
  <h2>Update database</h2>
  <p class="muted">After a website update adds new features (for example visitor statistics), press this once to create the new tables. It never deletes anything.</p>
  <button class="btn btn-ghost">Update database</button>
</form>
