<h1>Audit log</h1>
<div class="panel"><div class="tbl-wrap"><table class="tbl"><tr><th>When</th><th>User</th><th>Action</th><th>Detail</th><th>IP</th></tr>
<?php foreach ($log as $l): ?><tr><td><?= fmt_date($l['created_at'], 'j M Y H:i:s') ?></td><td><?= h($l['name'] ?? '—') ?></td><td><?= h($l['action']) ?></td><td><?= h($l['detail']) ?></td><td><?= h($l['ip']) ?></td></tr><?php endforeach; ?></table></div></div>
