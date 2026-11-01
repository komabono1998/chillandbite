<h1>Website settings</h1>
<form class="panel" method="post" action="<?= url('admin/settings') ?>"><?= csrf_field() ?>
  <label class="chk"><input type="checkbox" name="announcement_on" value="1"<?= setting('announcement_on', '1') === '1' ? ' checked' : '' ?>> Show announcement bar</label>
  <?php field('announcement', 'Announcement text', ['value' => setting('announcement')]); ?>
  <div class="form-grid"><?php
  foreach (['site_name' => 'Site name', 'tagline' => 'Tagline', 'venue' => 'Venue', 'location' => 'Location', 'contact_phone' => 'Contact phone', 'contact_email' => 'Contact email', 'whatsapp' => 'WhatsApp number (with country code)', 'whatsapp_group' => 'WhatsApp group invite link',
            'social_facebook' => 'Facebook URL', 'social_instagram' => 'Instagram URL', 'social_youtube' => 'YouTube URL', 'social_tiktok' => 'TikTok URL', 'social_x' => 'X URL'] as $k => $l) field($k, $l, ['value' => setting($k)]); ?></div>
  <?php field('host_bio', 'Host biography (homepage)', ['type' => 'textarea', 'rows' => 4, 'value' => setting('host_bio')]); ?>
  <button class="btn btn-primary">Save settings</button></form>
