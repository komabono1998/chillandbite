<?php $me = current_user(); ?>
<h1>Users &amp; permissions</h1>
<form class="panel" method="post" action="<?= url('admin/users') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="create"><h2>Add staff account</h2>
  <div class="form-grid"><?php field('name', 'Name', ['required' => true, 'value' => '']); field('email', 'Email', ['type' => 'email', 'required' => true, 'value' => '']);
  field('password', 'Temporary password (min 10 chars)', ['type' => 'password', 'required' => true, 'value' => '', 'attrs' => 'minlength="10" autocomplete="new-password"']); field('role', 'Role', ['type' => 'select', 'options' => ROLES]); ?></div>
  <button class="btn btn-primary">Create user</button></form>
<div class="panel"><div class="tbl-wrap"><table class="tbl"><tr><th>User</th><th>Role</th><th>Status</th><th>Last login</th><th>Manage</th></tr>
<?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?><tr><td><strong><?= h($u['name']) ?></strong><br><span class="small muted"><?= h($u['email']) ?></span></td>
<td><?php if ($self): ?><?= h(ROLES[$u['role']]) ?><?php else: ?><form class="inline" method="post" action="<?= url('admin/users') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $u['id'] ?>">
  <select name="role" onchange="this.form.submit()" style="width:auto"><?php foreach (ROLES as $k => $l): ?><option value="<?= $k ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></form><?php endif; ?></td>
<td><?= $u['active'] ? status_badge('confirmed') : status_badge('cancelled') ?></td><td><?= $u['last_login'] ? fmt_date($u['last_login'], 'j M Y H:i') : '—' ?></td>
<td class="actions"><?php if (!$self): ?><form class="inline" method="post" action="<?= url('admin/users') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-ghost btn-sm"><?= $u['active'] ? 'Deactivate' : 'Activate' ?></button></form><?php endif; ?>
<form class="inline" method="post" action="<?= url('admin/users') ?>"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="hidden" name="id" value="<?= $u['id'] ?>"><input type="password" name="password" placeholder="New password" minlength="10" required style="width:150px" autocomplete="new-password"> <button class="btn btn-ghost btn-sm">Set</button></form></td></tr><?php endforeach; ?></table></div></div>
