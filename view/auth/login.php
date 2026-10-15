<form class="card" method="post" action="<?= e(url($admin ? '/admin/login' : '/login')) ?>" novalidate>
  <h1><?= $admin ? 'Administrator sign-in' : 'Log in' ?></h1>
  <?= csrf_field() ?>
  <label for="email">Email address</label>
  <input id="email" name="email" type="email" autocomplete="username" required value="<?= e(old('email')) ?>">
  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="current-password" required>
  <button class="btn btn-block" type="submit">Log in</button>
  <p class="center"><a href="<?= e(url('/forgot-password')) ?>">Forgot your password?</a></p>
  <?php if (!$admin): ?><p class="center">New here? <a href="<?= e(url('/register')) ?>">Create an account</a></p><?php endif; ?>
</form>
