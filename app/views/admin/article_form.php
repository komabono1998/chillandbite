<?php $v = fn($k, $d = '') => $old[$k] ?? ($art[$k] ?? $d); ?>
<div class="adm-head"><h1><?= $art ? 'Edit article' : 'New article' ?></h1><a href="<?= url('admin/news') ?>">&larr; <?= $mine ? 'My stories' : 'All articles' ?></a></div>
<?php if ($mine && $art && $art['status'] === 'pending'): ?><p class="alert alert-ok">This story is with the editor. You can still edit it until it is published.</p><?php endif; ?>
<form class="panel" method="post" action="<?= url('admin/news/save') ?>" enctype="multipart/form-data"><?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)($art['id'] ?? 0) ?>">
  <?php field('title', 'Headline', ['required' => true, 'value' => $v('title')]); ?>
  <div class="form-grid">
    <?php field('category_id', 'Category', ['type' => 'select', 'value' => $v('category_id'), 'options' => ['' => '—'] + array_column($cats, 'name', 'id')]);
    if ($mine) {
        field('label', 'Type of story', ['type' => 'select', 'value' => $v('label', 'news'), 'options' => ['news' => NEWS_LABELS['news'], 'opinion' => NEWS_LABELS['opinion']], 'help' => 'Use Opinion for commentary and blogs. Do not state allegations about identifiable people as fact.']);
    } else {
        field('label', 'Editorial label', ['type' => 'select', 'value' => $v('label', 'news'), 'options' => NEWS_LABELS, 'help' => 'Gossip, opinion and unconfirmed reports are shown with a warning to readers. Do not state allegations about identifiable people as fact.']);
        field('status', 'Status', ['type' => 'select', 'value' => $v('status', 'draft'), 'options' => ['draft' => 'Draft', 'pending' => 'Awaiting review', 'scheduled' => 'Scheduled', 'published' => 'Published']]);
        field('published_at', 'Publish date/time', ['type' => 'datetime-local', 'value' => isset($old['published_at']) ? $old['published_at'] : dt_out($art['published_at'] ?? null), 'help' => 'Required for scheduled stories.']);
        field('sponsor_name', 'Sponsor (sponsored articles)', ['value' => $v('sponsor_name')]);
        field('source_name', 'Source outlet (if summarising another outlet)', ['value' => $v('source_name'), 'help' => 'Shown to readers as "via ..." with a credit box.']);
        field('source_url', 'Source link', ['type' => 'url', 'value' => $v('source_url'), 'attrs' => 'placeholder="https://"']);
    } ?>
  </div>
  <?php field('excerpt', 'Summary', ['value' => $v('excerpt'), 'attrs' => 'maxlength="300"']); field('body', 'Story (blank line = new paragraph)', ['type' => 'textarea', 'rows' => 14, 'value' => $v('body')]); ?>
  <div class="field"><label for="f_image">Featured photograph</label><input id="f_image" type="file" name="image" accept="image/jpeg,image/png,image/webp,image/gif">
    <?php if (!empty($art['image'])): ?><img src="<?= h(upload_url($art['image'])) ?>" alt="" style="max-height:90px;margin-top:8px"><?php endif; ?></div>
  <?php if (!$mine) { field('meta_description', 'SEO description', ['value' => $v('meta_description'), 'attrs' => 'maxlength="300"']); field('correction', 'Correction notice (shown on the article)', ['type' => 'textarea', 'rows' => 2, 'value' => $v('correction')]); ?>
  <label class="chk"><input type="checkbox" name="featured" value="1"<?= $v('featured') ? ' checked' : '' ?>> Featured story</label><?php } ?>
  <p>
    <?php if ($mine): ?>
      <button class="btn btn-ghost" name="save_draft" value="1">Save draft</button>
      <button class="btn btn-primary" name="submit_review" value="1">Send to editor for review</button>
    <?php else: ?>
      <button class="btn btn-primary">Save article</button> <?php if ($art): ?><a class="btn btn-ghost" target="_blank" href="<?= url('news/' . h($art['slug'])) ?>">View</a><?php endif; ?>
    <?php endif; ?>
  </p>
</form>
