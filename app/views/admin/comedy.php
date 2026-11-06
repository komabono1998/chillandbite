<?php
$act = url('admin/comedy');
$kinds = ['clip' => 'Short clip', 'full_show' => 'Full show', 'behind_scenes' => 'Behind the scenes', 'interview' => 'Interview', 'highlight' => 'Live highlight'];
$form = function (?array $v) use ($act, $kinds) { $g = fn($k, $d = '') => $v[$k] ?? $d; ?>
  <form method="post" action="<?= $act ?>" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)$g('id', 0) ?>">
    <div class="form-grid">
      <?php field('title', 'Title', ['required' => true, 'value' => $g('title')]);
      field('kind', 'Type', ['type' => 'select', 'value' => $g('kind', 'clip'), 'options' => $kinds]);
      field('description', 'Short note', ['value' => $g('description')]); ?>
    </div>
    <div class="form-grid">
      <div class="field"><label>MP4 video file (up to 300MB)</label><input type="file" name="video" accept="video/mp4,.mp4,.m4v"><?php if ($g('file')): ?><small>An MP4 is already uploaded. Choose a file only to replace it.</small><?php endif; ?></div>
      <?php field('url', 'Or a YouTube link', ['type' => 'url', 'value' => $g('url'), 'attrs' => 'placeholder="https://www.youtube.com/watch?v="']); ?>
      <div class="field"><label>Cover picture (optional, for MP4 uploads)</label><input type="file" name="poster" accept="image/jpeg,image/png,image/webp,image/gif"><?php if ($g('poster')): ?><img src="<?= h(upload_url($g('poster'))) ?>" alt="" style="max-height:60px;margin-top:6px"><?php endif; ?></div>
    </div>
    <label class="chk"><input type="checkbox" name="featured" value="1"<?= $g('featured') ? ' checked' : '' ?>> Feature first (shown on the homepage and at the top of the comedy page)</label>
    <p class="small muted">Uploaded MP4 videos play on the website and get a <b>Download video</b> button. YouTube videos play from YouTube and cannot be downloaded.</p>
    <button class="btn btn-primary"><?= $v ? 'Save changes' : 'Add video' ?></button>
  </form>
<?php };
$post = fn(string $a, int $id, string $label, string $cls = 'btn btn-ghost btn-sm', string $confirm = '') => '<form class="inline" method="post" action="' . $act . '">' . csrf_field() . '<input type="hidden" name="action" value="' . $a . '"><input type="hidden" name="id" value="' . $id . '"><button class="' . $cls . '"' . ($confirm ? ' onclick="return confirm(\'' . $confirm . '\')"' : '') . '>' . $label . '</button></form>';
?>
<div class="adm-head"><div><h1>Comedy videos</h1><p class="adm-sub">Upload MP4 videos or add YouTube links. They appear on the Laugh with Owakabi page.</p></div>
  <a class="btn btn-ghost" target="_blank" href="<?= url('laugh#videos') ?>">View page ↗</a></div>

<div class="panel">
  <div class="panel-head"><h2>Add a video</h2><span class="muted small">MP4 upload or YouTube link</span></div>
  <?php $form(null); ?>
</div>

<div class="panel">
  <div class="panel-head"><h2>Videos</h2><span class="muted small"><?= count($videos) ?> video(s)</span></div>
  <?php if (!$videos): ?><p class="muted">No videos yet. Add the first one above.</p><?php endif; ?>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Video</th><th>Type</th><th>Source</th><th>Featured</th><th>Manage</th></tr></thead><tbody>
  <?php foreach ($videos as $v): $src = $v['file'] ? upload_url($v['file']) : null; $size = $v['file'] && is_file(ROOT . '/uploads/' . $v['file']) ? round(filesize(ROOT . '/uploads/' . $v['file']) / 1048576, 1) . ' MB' : ''; ?><tr>
    <td><div style="display:flex;align-items:center;gap:12px"><?php if ($v['poster']): ?><img src="<?= h(upload_url($v['poster'])) ?>" alt="" style="width:80px;height:50px;object-fit:cover;border-radius:8px"><?php endif; ?><span><b><?= h($v['title']) ?></b><br><span class="small muted"><?= h((string)$v['description']) ?></span></span></div></td>
    <td><?= h($kinds[$v['kind']] ?? $v['kind']) ?></td>
    <td class="small"><?php if ($src): ?><a href="<?= h($src) ?>" target="_blank" rel="noopener">MP4 file</a> <span class="muted"><?= h($size) ?></span><br><a href="<?= h($src) ?>" download="<?= h(video_filename($v)) ?>">⬇ Download</a>
        <?php elseif ($v['url']): ?><a href="<?= h($v['url']) ?>" target="_blank" rel="noopener nofollow">YouTube ↗</a><?php endif; ?></td>
    <td><?= $v['featured'] ? 'Yes' : '' ?></td>
    <td><div class="actions">
      <details><summary class="btn btn-primary btn-sm" style="display:inline-block;cursor:pointer">Edit</summary><div style="margin-top:10px;min-width:min(560px,80vw)"><?php $form($v); ?></div></details>
      <?= $post('feature', (int)$v['id'], $v['featured'] ? 'Unfeature' : 'Feature') ?>
      <?= $post('delete', (int)$v['id'], 'Remove', 'btn btn-ghost btn-sm', 'Remove this video and its file?') ?>
    </div></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>
