<?php $me = current_user(); $act = url('admin/users'); ?>
<div class="adm-head"><div><h1>Users &amp; roles</h1><p class="adm-sub">Give each person the access they need. Permissions are enforced on the server, not just hidden in the menu.</p></div></div>

<div class="panel">
  <div class="panel-head"><h2>Add a team member</h2></div>
  <form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="form-grid">
      <?php field('name', 'Full name', ['required' => true, 'value' => '']); field('email', 'Email (used to sign in)', ['type' => 'email', 'required' => true, 'value' => '']);
      field('password', 'Temporary password (min 10 characters)', ['type' => 'password', 'required' => true, 'value' => '', 'attrs' => 'minlength="10" autocomplete="new-password"']);
      field('role', 'Role', ['type' => 'select', 'options' => ROLES, 'attrs' => 'id="role-pick"']); ?>
    </div>
    <p class="small muted" id="role-desc"><?= h(ROLE_DESCRIPTIONS['admin']) ?></p>
    <button class="btn btn-primary">Create account</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><h2>Team</h2><span class="muted small"><?= count($users) ?> account(s)</span></div>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Last sign-in</th><th>Manage</th></tr></thead><tbody>
  <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?><tr>
    <td><b><?= h($u['name']) ?></b><?= $self ? ' <span class="role-pill">you</span>' : '' ?><br><span class="small muted"><?= h($u['email']) ?></span></td>
    <td><?php if ($self): ?><span class="role-pill"><?= h(ROLES[$u['role']]) ?></span><?php else: ?>
      <form class="inline" method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $u['id'] ?>">
        <select name="role" onchange="this.form.submit()" style="width:auto" aria-label="Role for <?= h($u['name']) ?>"><?php foreach (ROLES as $k => $l): ?><option value="<?= $k ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></form><?php endif; ?></td>
    <td><?= $u['active'] ? status_badge('confirmed') : status_badge('cancelled') ?></td>
    <td class="small"><?= $u['last_login'] ? fmt_date($u['last_login'], 'j M Y H:i') : '—' ?></td>
    <td><div class="actions">
      <details><summary class="btn btn-ghost btn-sm" style="display:inline-block;cursor:pointer">Edit details</summary>
        <form method="post" action="<?= $act ?>" class="filters" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><input type="hidden" name="id" value="<?= $u['id'] ?>">
          <label>Name <input name="name" value="<?= h($u['name']) ?>" required maxlength="120"></label><label>Email <input type="email" name="email" value="<?= h($u['email']) ?>" required></label><button class="btn btn-primary btn-sm">Save</button></form></details>
      <details><summary class="btn btn-ghost btn-sm" style="display:inline-block;cursor:pointer">Set password</summary>
        <form method="post" action="<?= $act ?>" class="filters" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= $u['id'] ?>">
          <label>New password <input type="password" name="password" minlength="10" required autocomplete="new-password"></label><button class="btn btn-primary btn-sm">Set</button></form></details>
      <?php if (!$self): ?><form class="inline" method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-ghost btn-sm"><?= $u['active'] ? 'Deactivate' : 'Activate' ?></button></form><?php endif; ?>
    </div></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>What each role can do</h2></div>
  <div class="tbl-wrap"><table class="tbl perm-table"><thead><tr><th>Area</th><?php foreach (ROLES as $rk => $rl): ?><th title="<?= h(ROLE_DESCRIPTIONS[$rk]) ?>"><?= h(str_replace([' Manager', ' & Advertising', 'System '], ['', '', ''], $rl)) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach (MODULE_LABELS as $mod => $label): ?><tr><td><?= h($label) ?></td>
    <?php foreach (ROLES as $rk => $rl): $ok = $rk === 'admin' || in_array($rk, PERMS[$mod] ?? [], true); ?><td class="<?= $ok ? 'y' : 'n' ?>"><?= $ok ? '✓' : '·' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
  <ul class="small muted" style="margin-top:12px;padding-left:18px"><?php foreach (ROLES as $rk => $rl): ?><li><b><?= h($rl) ?>:</b> <?= h(ROLE_DESCRIPTIONS[$rk]) ?></li><?php endforeach; ?></ul>
</div>

<div class="panel">
  <div class="panel-head"><h2>Who can edit which website page</h2></div>
  <div class="tbl-wrap"><table class="tbl perm-table"><thead><tr><th>Page</th><?php foreach (ROLES as $rk => $rl): ?><th><?= h(str_replace([' Manager', ' & Advertising', 'System '], ['', '', ''], $rl)) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach (site_pages() as $pid => $pg): ?><tr><td><?= $pg['icon'] ?> <?= h($pg['label']) ?></td>
    <?php foreach (ROLES as $rk => $rl): $ok = $rk === 'admin' || in_array($rk, $pg['roles'], true); ?><td class="<?= $ok ? 'y' : 'n' ?>"><?= $ok ? '✓' : '·' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
</div>
<script>
(function () {
  var d = <?= json_encode(ROLE_DESCRIPTIONS, JSON_UNESCAPED_UNICODE) ?>, s = document.getElementById('role-pick'), t = document.getElementById('role-desc');
  if (s && t) s.addEventListener('change', function () { t.textContent = d[s.value] || ''; });
})();
</script>
