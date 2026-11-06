<?php
$me = current_user(); $act = url('admin/users');
$mailOn = env('MAIL_ENABLED', '0') === '1';
$short = fn(string $l) => str_replace([' Manager', ' & Advertising', ' Officer', ' / Gate Staff', 'System ', 'Ticketing & Reservations', 'DJ & Music'], ['', '', '', '', '', 'Ticketing', 'DJ'], $l);
?>
<div class="adm-head"><div><h1>Users &amp; roles</h1><p class="adm-sub">Create accounts in seconds: pick a role, and a secure temporary password is generated for you. Permissions are enforced on the server, not just hidden in the menu.</p></div>
  <a class="btn btn-ghost" href="#guide">Role guide ↓</a></div>

<?php if (!empty($creds)): ?>
<div class="panel us-creds" id="creds">
  <div class="panel-head"><h2>🔑 Login details</h2><span class="muted small">Shown once. Share them securely, then ask each person to sign in and choose their own password.</span></div>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Person</th><th>Sign in with</th><th>Temporary password</th><th></th></tr></thead><tbody>
    <?php foreach ($creds as $i => $c):
        $text = "Chill & Bite admin login\nName: {$c['name']}\nRole: {$c['role']}\nSign in: {$c['login']}\nEmail: {$c['email']}\nTemporary password: {$c['password']}" . ($c['must_change'] ? "\n(You will be asked to choose your own password.)" : ''); ?>
      <tr><td><b><?= h($c['name']) ?></b><br><span class="small muted"><?= h($c['role']) ?></span></td>
        <td class="small"><?= h($c['email']) ?><br><a href="<?= h($c['login']) ?>"><?= h($c['login']) ?></a></td>
        <td><code class="us-pw"><?= h($c['password']) ?></code><?= !empty($c['emailed']) ? '<br><span class="small muted">✉️ also emailed</span>' : '' ?></td>
        <td><div class="actions">
          <button type="button" class="btn btn-primary btn-sm us-copy" data-copy="<?= h($text) ?>">Copy details</button>
          <a class="btn btn-ghost btn-sm" target="_blank" rel="noopener" href="https://wa.me/?text=<?= rawurlencode($text) ?>">WhatsApp</a>
        </div></td></tr>
    <?php endforeach; ?>
  </tbody></table></div>
  <p class="us-creds-actions"><button type="button" class="btn btn-ghost btn-sm" onclick="window.print()">🖨️ Print</button></p>
</div>
<?php endif; ?>

<div class="panel" id="add">
  <div class="panel-head"><h2>Add a team member</h2><span class="muted small">Step 1: choose a role · Step 2: enter their details</span></div>
  <form method="post" action="<?= $act ?>" id="usForm"><?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="us-roles" role="radiogroup" aria-label="Role">
      <?php foreach (ROLES as $rk => $rl): $acc = role_access($rk); ?>
        <label class="us-role"><input type="radio" name="role" value="<?= h($rk) ?>"<?= $rk === 'event_manager' ? ' checked' : '' ?> required>
          <span class="us-role-body"><span class="us-role-ic" aria-hidden="true"><?= ROLE_ICONS[$rk] ?? '👤' ?></span><b><?= h($rl) ?></b><small><?= h(ROLE_DESCRIPTIONS[$rk]) ?></small>
            <span class="us-chips"><?php foreach (array_slice($acc, 0, 6) as $m): ?><i><?= h($m) ?></i><?php endforeach; ?><?= count($acc) > 6 ? '<i>+' . (count($acc) - 6) . ' more</i>' : '' ?></span></span></label>
      <?php endforeach; ?>
    </div>
    <div class="form-grid">
      <div class="field"><label for="u_name">Full name *</label><input id="u_name" name="name" required maxlength="120" autocomplete="off"></div>
      <div class="field"><label for="u_email">Email (used to sign in) *</label><input id="u_email" type="email" name="email" required autocomplete="off"></div>
      <div class="field"><label for="u_pw">Password</label>
        <div class="us-pw-row"><input id="u_pw" name="password" autocomplete="new-password" placeholder="Leave empty to generate a secure one" minlength="10"><button type="button" class="btn btn-ghost btn-sm" id="usGen">Generate</button></div>
        <small>At least 10 characters. Empty = we generate one for you.</small></div>
    </div>
    <label class="chk"><input type="checkbox" name="must_change" value="1" checked> Ask them to choose their own password at first sign-in <span class="muted small">(recommended)</span></label>
    <label class="chk"><input type="checkbox" name="email_login" value="1"<?= $mailOn ? ' checked' : '' ?>> Email the login details to them <span class="muted small"><?= $mailOn ? '' : '(email sending is off on this server, so details are saved to the mail log only)' ?></span></label>
    <button class="btn btn-primary btn-lg">Create account</button>
  </form>
</div>

<div class="panel" id="bulk">
  <div class="panel-head"><h2>Add several people at once</h2><span class="muted small">One person per line: Name, email, role</span></div>
  <form method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="bulk">
    <div class="field"><label for="u_lines">People</label>
      <textarea id="u_lines" name="lines" rows="5" placeholder="Grace Akello, grace@example.com, box_office&#10;Peter Okello, peter@example.com, door&#10;Sarah Auma, sarah@example.com, finance"></textarea>
      <small>Role can be: <?= implode(', ', array_map(fn($k) => '<code>' . h($k) . '</code>', array_keys(ROLES))) ?>. Each person gets a generated temporary password and must change it at first sign-in.</small></div>
    <label class="chk"><input type="checkbox" name="email_login" value="1"<?= $mailOn ? ' checked' : '' ?>> Email each person their login details</label>
    <button class="btn btn-primary">Create all accounts</button>
  </form>
</div>

<div class="panel">
  <div class="panel-head"><h2>Team</h2><span class="muted small"><?= count($users) ?> account(s)</span></div>
  <div class="tbl-wrap"><table class="tbl"><thead><tr><th>Person</th><th>Role</th><th>Status</th><th>Last sign-in</th><th>Manage</th></tr></thead><tbody>
  <?php foreach ($users as $u): $self = (int)$u['id'] === (int)$me['id']; ?><tr>
    <td><b><?= h($u['name']) ?></b><?= $self ? ' <span class="role-pill">you</span>' : '' ?><?= !empty($u['must_change']) ? ' <span class="badge badge-warn">must set password</span>' : '' ?><br><span class="small muted"><?= h($u['email']) ?></span></td>
    <td><?php if ($self): ?><span class="role-pill"><?= h(ROLES[$u['role']] ?? $u['role']) ?></span><?php else: ?>
      <form class="inline" method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="role"><input type="hidden" name="id" value="<?= $u['id'] ?>">
        <select name="role" onchange="this.form.submit()" style="width:auto" aria-label="Role for <?= h($u['name']) ?>"><?php if (!isset(ROLES[$u['role']])): ?><option selected><?= h($u['role']) ?> (retired)</option><?php endif; ?><?php foreach (ROLES as $k => $l): ?><option value="<?= $k ?>"<?= $u['role'] === $k ? ' selected' : '' ?>><?= h($l) ?></option><?php endforeach; ?></select></form><?php endif; ?></td>
    <td><?= $u['active'] ? status_badge('confirmed') : status_badge('cancelled') ?></td>
    <td class="small"><?= $u['last_login'] ? fmt_date($u['last_login'], 'j M Y H:i') : '—' ?></td>
    <td><div class="actions">
      <details><summary class="btn btn-ghost btn-sm" style="display:inline-block;cursor:pointer">Edit details</summary>
        <form method="post" action="<?= $act ?>" class="filters" style="margin-top:8px"><?= csrf_field() ?><input type="hidden" name="action" value="profile"><input type="hidden" name="id" value="<?= $u['id'] ?>">
          <label>Name <input name="name" value="<?= h($u['name']) ?>" required maxlength="120"></label><label>Email <input type="email" name="email" value="<?= h($u['email']) ?>" required></label><button class="btn btn-primary btn-sm">Save</button></form></details>
      <?php if (!$self): ?>
        <form class="inline" method="post" action="<?= $act ?>" onsubmit="return confirm('Generate a new temporary password for <?= h(addslashes($u['name'])) ?>? Their old password stops working.')"><?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-ghost btn-sm">🔑 Reset password</button></form>
        <form class="inline" method="post" action="<?= $act ?>"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $u['id'] ?>"><button class="btn btn-ghost btn-sm"><?= $u['active'] ? 'Deactivate' : 'Activate' ?></button></form>
      <?php endif; ?>
    </div></td></tr>
  <?php endforeach; ?></tbody></table></div>
</div>

<div class="panel" id="guide">
  <div class="panel-head"><h2>Role guide: what each role can open</h2></div>
  <div class="tbl-wrap"><table class="tbl perm-table"><thead><tr><th>Area</th><?php foreach (ROLES as $rk => $rl): ?><th title="<?= h(ROLE_DESCRIPTIONS[$rk]) ?>"><?= h($short($rl)) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach (MODULE_LABELS as $mod => $label): if (in_array($mod, ['accreditation', 'news'], true)) continue; ?><tr><td><?= h($label) ?></td>
    <?php foreach (ROLES as $rk => $rl): $ok = $rk === 'admin' || in_array($rk, PERMS[$mod] ?? [], true); ?><td class="<?= $ok ? 'y' : 'n' ?>"><?= $ok ? '✓' : '·' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
</div>

<div class="panel">
  <div class="panel-head"><h2>Who can edit which website page</h2></div>
  <div class="tbl-wrap"><table class="tbl perm-table"><thead><tr><th>Page</th><?php foreach (ROLES as $rk => $rl): ?><th><?= h($short($rl)) ?></th><?php endforeach; ?></tr></thead><tbody>
  <?php foreach (site_pages() as $pid => $pg): ?><tr><td><?= $pg['icon'] ?> <?= h($pg['label']) ?></td>
    <?php foreach (ROLES as $rk => $rl): $ok = $rk === 'admin' || (in_array($rk, $pg['roles'], true) && in_array($rk, PERMS['pages'] ?? [], true)); ?><td class="<?= $ok ? 'y' : 'n' ?>"><?= $ok ? '✓' : '·' ?></td><?php endforeach; ?></tr><?php endforeach; ?>
  </tbody></table></div>
</div>

<script>
(function () {
  var gen = document.getElementById('usGen'), pw = document.getElementById('u_pw');
  if (gen && pw) gen.addEventListener('click', function () {
    var a = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789', out = '', b = new Uint32Array(14);
    (window.crypto || window.msCrypto).getRandomValues(b); b.forEach(function (n) { out += a[n % a.length]; });
    pw.value = out; pw.type = 'text';
  });
  document.querySelectorAll('.us-copy').forEach(function (b) { b.addEventListener('click', function () {
    var t = b.dataset.copy, done = function () { var o = b.textContent; b.textContent = 'Copied!'; setTimeout(function () { b.textContent = o; }, 1600); };
    if (navigator.clipboard) navigator.clipboard.writeText(t).then(done, done); else done();
  }); });
  var c = document.getElementById('creds'); if (c) c.scrollIntoView({ block: 'start' });
})();
</script>
