<div class="adm-head no-print">
  <div><h1>Print tickets</h1><p class="adm-sub"><?= h($ev['title']) ?> · <?= fmt_date($ev['start_at'], 'l, j F Y') ?> · <?= count($list) ?> ticket<?= count($list) === 1 ? '' : 's' ?>. Each QR code opens the guest's check-in page when scanned by staff.</p></div>
  <div class="btn-row"><button class="btn btn-primary" type="button" onclick="window.print()">🖨️ Print / save as PDF</button><a class="btn btn-ghost" href="<?= url('admin/tickets?event=' . (int)$ev['id']) ?>">← Back to tickets</a></div>
</div>
<?php if (!$list): ?><div class="empty">There are no confirmed tickets for this event yet.</div><?php else: ?>
<div class="print-tix">
  <?php foreach ($list as $r): ?>
  <article class="ptix">
    <div class="ptix-main">
      <img class="ptix-logo" src="<?= h(asset('img/logo.png')) ?>" alt="Chill &amp; Bite MEATINGS" width="320" height="253">
      <p class="ptix-title"><?= h($ev['title']) ?></p>
      <p class="ptix-meta"><?= fmt_date($ev['start_at'], 'D, j M Y') ?> · <?= fmt_time($ev['start_at']) ?><br><?= h($ev['venue']) ?></p>
      <p class="ptix-guest"><b><?= h($r['full_name']) ?></b><br><span><?= h(str_replace(' Reservation', '', RES_TYPES[$r['type']])) ?><?= $r['package'] ? ' · ' . h($r['package']) : '' ?> · admits <?= (int)$r['guests'] ?></span></p>
      <p class="ptix-ref"><?= h($r['reference']) ?></p>
    </div>
    <div class="ptix-qr" data-url="<?= h($r['checkin_url']) ?>"></div>
  </article>
  <?php endforeach; ?>
</div>
<script src="<?= h(asset('js/vendor/qrcode.min.js')) ?>"></script>
<script>
document.querySelectorAll('.ptix-qr').forEach(function (el) { new QRCode(el, { text: el.dataset.url, width: 150, height: 150, correctLevel: QRCode.CorrectLevel.M }); });
</script>
<?php endif; ?>
