<div class="login-box panel">
  <h1>Staff login</h1>
  <form method="post" action="<?= url('admin/login') ?>"><?= csrf_field() ?>
    <?php field('email', 'Email', ['type' => 'email', 'required' => true, 'value' => '', 'attrs' => 'autocomplete="username"']); field('password', 'Password', ['type' => 'password', 'required' => true, 'value' => '', 'attrs' => 'autocomplete="current-password"']); ?>
    <button class="btn btn-primary btn-block">Sign in</button>
  </form>
  <p class="login-back"><a class="btn btn-ghost btn-block" href="<?= url() ?>">&larr; Back to website</a></p>
</div>
