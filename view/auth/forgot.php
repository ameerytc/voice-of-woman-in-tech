<form class="card" method="post" action="<?= e(url('/forgot-password')) ?>">
  <h1>Forgot password</h1>
  <?= csrf_field() ?>
  <?php if (!$mail): ?>
    <div class="alert a-info">Password recovery by email is not available because email (SMTP) has not been configured on this server. Please contact the organisation administrator to have your password reset.</div>
  <?php else: ?>
    <label for="email">Email address</label>
    <input id="email" name="email" type="email" required>
    <button class="btn btn-block" type="submit">Send reset link</button>
  <?php endif; ?>
  <p class="center"><a href="<?= e(url('/login')) ?>">Back to log in</a></p>
</form>
