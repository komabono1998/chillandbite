<?php if (!empty($wgap) && can('payments')): $back = $back ?? 'dash'; ?>
<div class="panel wallet-rec pay-health">
  <div class="panel-head"><h2>🏦 Daraza wallet and who paid</h2><a class="small" href="<?= url($back === 'dash' ? 'admin?refresh=1' : 'admin/collections?refresh=1') ?>">Refresh balance</a></div>
  <div class="ph-grid">
    <div><b><?= h(money($wgap['balance'])) ?></b><span>available in the wallet now</span></div>
    <div><b><?= h(money($wgap['recorded'])) ?></b><span>online payments recorded</span></div>
    <div><b><?= h(money($wgap['withdrawn'])) ?></b><span>already withdrawn</span></div>
    <div><b style="color:<?= $wgap['gap'] > 0 ? '#d9534f' : '#1f8a4c' ?>"><?= h(money($wgap['gap'])) ?></b><span>in the wallet but not recorded yet</span></div>
  </div>
  <?php if ($wgap['gap'] > 0): ?>
  <p class="ph-warn">This money reached your wallet without going through a booking or payment page here, so the system does not know who sent it. Enter each person's name and phone (from your Daraza statement or the MoMo message) and it is added to the collections and the dashboard.</p>
  <form method="post" action="<?= url('admin/collections/record-wallet') ?>" class="form-grid"><?= csrf_field() ?><input type="hidden" name="back" value="<?= h($back) ?>">
    <label>Name of payer<input name="payer_name" required maxlength="140" autocomplete="off"></label>
    <label>Phone of payer<input name="payer_phone" required placeholder="0772 123 456" inputmode="tel" autocomplete="off"></label>
    <label>Amount (UGX)<input name="amount" required inputmode="numeric" value="<?= (int)$wgap['gap'] ?>" autocomplete="off"></label>
    <label>Booking reference (optional)<input name="reference" placeholder="CB-XXXXXX" autocomplete="off"></label>
    <div><button class="btn btn-primary">Record payment</button></div>
  </form>
  <?php else: ?><p class="ph-ok">Every shilling in the wallet is recorded with the payer's details.</p><?php endif; ?>
</div>
<?php endif; ?>
