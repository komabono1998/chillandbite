<?php
$act = url('admin/djs');
$djOpts = ['' => 'Not on the roster (type the name below)'] + array_column($djs, 'name', 'id');
$mixForm = function (?array $m) use ($act, $djOpts) { $v = fn($k, $d = '') => $m[$k] ?? $d; ?>
  <form method="post" action="<?= $act ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="mix_save"><input type="hidden" name="id" value="<?= (int)$v('id', 0) ?>">
    <div class="form-grid">
      <?php field('title', 'Mix title', ['required' => true, 'value' => $v('title')]);
      field('source', 'Where is it from?', ['type' => 'select', 'value' => $v('source', 'podium'), 'options' => DJ_SOURCES]);
      field('dj_id', 'DJ from the roster', ['type' => 'select', 'value' => $v('dj_id'), 'options' => $djOpts]);
      field('dj_name', 'DJ name (if not on the roster)', ['value' => $v('dj_name'), 'attrs' => 'maxlength="120"']);
      field('genre', 'Genre', ['value' => $v('genre'), 'attrs' => 'placeholder="Afrobeats, Amapiano, Dancehall..."']);
      field('description', 'Short note', ['value' => $v('description'), 'attrs' => 'maxlength="300"']); ?>
    </div>
    <div class="form-grid">
      <div class="field"><label>Audio file (MP3, M4A, WAV or OGG, up to 110MB)</label><input type="file" name="audio" accept="audio/mpeg,audio/mp4,audio/x-m4a,audio/wav,audio/ogg,.mp3,.m4a,.wav,.ogg"><?php if ($v('audio')): ?><small>Current file kept unless you choose a new one.</small><?php endif; ?></div>
      <?php field('external_url', 'Or paste a SoundCloud, Mixcloud or YouTube link', ['type' => 'url', 'value' => $v('external_url'), 'attrs' => 'placeholder="https://"']); ?>
      <div class="field"><label>Cover image (optional)</label><input type="file" name="cover" accept="image/jpeg,image/png,image/webp,image/gif"><?php if ($v('cover')): ?><img src="<?= h(upload_url($v('cover'))) ?>" alt="" style="max-height:60px;margin-top:6px"><?php endif; ?></div>
      <?php field('status', 'Visibility', ['type' => 'select', 'value' => $v('status', 'published'), 'options' => ['published' => 'Published on the website', 'draft' => 'Draft (hidden)']]); ?>
    </div>
    <label class="chk"><input type="checkbox" name="featured" value="1"<?= $v('featured') ? ' checked' : '' ?>> Featured (shown first)</label>
    <label class="chk"><input type="checkbox" name="allow_download" value="1"<?= $v('allow_download') ? ' checked' : '' ?>> Let listeners download this mix</label>
    <button class="btn btn-primary"><?= $m ? 'Save changes' : 'Publish mix' ?></button>
  </form>
<?php };
$djForm = function (?array $d) use ($act) { $v = fn($k, $x = '') => $d[$k] ?? $x; ?>
  <form method="post" action="<?= $act ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="dj_save"><input type="hidden" name="id" value="<?= (int)$v('id', 0) ?>">
    <div class="form-grid">
      <?php field('name', 'Name', ['required' => true, 'value' => $v('name')]);
      field('role', 'Profile type', ['type' => 'select', 'value' => $v('role', 'dj'), 'options' => ['dj' => 'DJ (can be booked)', 'host' => 'Host / MC (shown as the host)']]);
      field('tagline', 'Tagline', ['value' => $v('tagline'), 'attrs' => 'placeholder="Weddings, clubs and corporate events"']);
      field('genres', 'Genres (comma separated)', ['value' => $v('genres'), 'attrs' => 'placeholder="Afrobeats, Amapiano, R&B"']);
      field('instagram', 'Instagram link', ['type' => 'url', 'value' => $v('instagram'), 'attrs' => 'placeholder="https://"']);
      field('sort_order', 'Order on the page (small numbers first)', ['type' => 'number', 'value' => $v('sort_order', '0')]);
      field('photo_pos', 'Photo focus (0 = top of photo, 100 = bottom)', ['type' => 'number', 'value' => $v('photo_pos', '15'), 'attrs' => 'min="0" max="100"', 'help' => 'Lower the number if the head is cut off on the website.']); ?>
      <div class="field"><label>Photo</label><input type="file" name="photo" accept="image/jpeg,image/png,image/webp,image/gif"><?php if ($v('photo')): ?><img src="<?= h(upload_url($v('photo'))) ?>" alt="" style="max-height:60px;margin-top:6px"><?php endif; ?></div>
    </div>
    <?php field('bio', 'Short bio', ['type' => 'textarea', 'rows' => 3, 'value' => $v('bio')]); ?>
    <button class="btn btn-primary"><?= $d ? 'Save changes' : 'Add DJ' ?></button>
  </form>
<?php };
$post = fn(string $a, int $id, string $label, string $cls = 'btn btn-ghost btn-sm', string $confirm = '') => '<form class="inline" method="post" action="' . $act . '">' . csrf_field() . '<input type="hidden" name="action" value="' . $a . '"><input type="hidden" name="id" value="' . $id . '"><button class="' . $cls . '"' . ($confirm ? ' onclick="return confirm(\'' . $confirm . '\')"' : '') . '>' . $label . '</button></form>';
?>
<div class="adm-head"><div><h1>DJs &amp; mixes</h1><p class="adm-sub">Manage the Podium DJs roster and the mixes shown on the website. Booking requests arrive under Inquiries.</p></div>
  <span><a class="btn btn-ghost" target="_blank" href="<?= url('djs') ?>">View page ↗</a> <?php if (can('inquiries')): ?><a class="btn btn-ghost" href="<?= url('admin/inquiries?type=dj') ?>">DJ bookings</a><?php endif; ?></span></div>

<div class="panel">
  <div class="panel-head"><h2>Add a mix</h2><span class="muted small">Podium DJs or Chill &amp; Bite MEATINGS</span></div>
  <?php $mixForm(null); ?>
</div>

<div class="panel">
  <div class="panel-head"><h2>Mixes</h2><span class="muted small"><?= count($mixes) ?> mix(es)</span></div>
  <?php if (!$mixes): ?><p class="muted">No mixes yet. Add the first one above.</p><?php endif; ?>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Mix</th><th>Source</th><th>Type</th><th>Status</th><th>Manage</th></tr></thead><tbody>
  <?php foreach ($mixes as $m): ?><tr>
    <td><b><?= h($m['title']) ?></b><?= $m['featured'] ? ' <span class="role-pill">featured</span>' : '' ?><br><span class="small muted"><?= h($m['dj_name']) ?><?= $m['genre'] ? ' · ' . h($m['genre']) : '' ?></span></td>
    <td><?= h(DJ_SOURCES[$m['source']] ?? $m['source']) ?></td>
    <td class="small"><?php if ($m['audio']): ?><a href="<?= h(upload_url($m['audio'])) ?>" target="_blank" rel="noopener">Audio file</a><?php elseif ($m['external_url']): ?><a href="<?= h($m['external_url']) ?>" target="_blank" rel="noopener nofollow">Link ↗</a><?php endif; ?></td>
    <td><?= status_badge($m['status']) ?></td>
    <td><div class="actions">
      <details><summary class="btn btn-ghost btn-sm" style="display:inline-block;cursor:pointer">Edit</summary><div style="margin-top:10px;min-width:min(560px,80vw)"><?php $mixForm($m); ?></div></details>
      <?= $post('mix_toggle', (int)$m['id'], $m['status'] === 'published' ? 'Hide' : 'Publish') ?>
      <?= $post('mix_feature', (int)$m['id'], $m['featured'] ? 'Unfeature' : 'Feature') ?>
      <?= $post('mix_delete', (int)$m['id'], 'Delete', 'btn btn-ghost btn-sm', 'Delete this mix and its audio file?') ?>
    </div></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Add a DJ</h2></div>
  <?php $djForm(null); ?>
</div>

<div class="panel">
  <div class="panel-head"><h2>DJ roster</h2><span class="muted small"><?= count($djs) ?> DJ(s)</span></div>
  <?php if (!$djs): ?><p class="muted">No DJs yet. Add the first one above. Visitors can still send a booking request and you will match a DJ.</p><?php endif; ?>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>DJ</th><th>Genres</th><th>Status</th><th>Manage</th></tr></thead><tbody>
  <?php foreach ($djs as $d): ?><tr>
    <td><div style="display:flex;align-items:center;gap:12px"><?php if ($d['photo']): ?><img src="<?= h(upload_url($d['photo'])) ?>" alt="" style="width:52px;height:64px;object-fit:cover;object-position:center <?= (int)$d['photo_pos'] ?>%;border-radius:10px"><?php endif; ?><span><b><?= h($d['name']) ?></b><?= $d['role'] === 'host' ? ' <span class="role-pill">host</span>' : '' ?><br><span class="small muted"><?= h($d['tagline']) ?></span></span></div></td>
    <td class="small"><?= h($d['genres']) ?></td>
    <td><?= $d['active'] ? status_badge('published') : status_badge('draft') ?></td>
    <td><div class="actions">
      <details><summary class="btn btn-primary btn-sm" style="display:inline-block;cursor:pointer">Edit profile</summary><div style="margin-top:10px;min-width:min(560px,80vw)"><?php $djForm($d); ?></div></details>
      <?= $post('dj_toggle', (int)$d['id'], $d['active'] ? 'Hide' : 'Show') ?>
      <?= $post('dj_delete', (int)$d['id'], 'Delete', 'btn btn-ghost btn-sm', 'Remove this DJ from the roster?') ?>
    </div></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>
