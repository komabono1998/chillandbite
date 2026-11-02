<div class="adm-head"><div><h1>My account</h1><p class="adm-sub"><?= h($u['email']) ?> · <?= h(ROLES[$u['role']] ?? $u['role']) ?><?= $u['organization'] ? ' · ' . h($u['organization']) : '' ?></p></div></div>
<div class="panel">
  <div class="panel-head"><h2>Your name</h2></div>
  <form method="post" action="<?= url('admin/account') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <?php field('name', 'Display name (shown as your byline)', ['required' => true, 'value' => $u['name'], 'attrs' => 'maxlength="120"']); ?>
    <button class="btn btn-primary">Save name</button>
  </form>
</div>
<div class="panel">
  <div class="panel-head"><h2>Change password</h2></div>
  <form method="post" action="<?= url('admin/account') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="password">
    <div class="form-grid">
      <?php field('current', 'Current password', ['type' => 'password', 'required' => true, 'value' => '', 'attrs' => 'autocomplete="current-password"']);
      field('password', 'New password (min 10 characters)', ['type' => 'password', 'required' => true, 'value' => '', 'attrs' => 'minlength="10" autocomplete="new-password"']);
      field('confirm', 'Repeat new password', ['type' => 'password', 'required' => true, 'value' => '', 'attrs' => 'minlength="10" autocomplete="new-password"']); ?>
    </div>
    <button class="btn btn-primary">Change password</button>
  </form>
</div>
