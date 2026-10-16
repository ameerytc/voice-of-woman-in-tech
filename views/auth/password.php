<form class="card narrow-card" method="post" action="<?= e(url('/account/password')) ?>">
  <h1>Change password</h1>
  <?php if ($forced): ?><div class="alert a-info">You must set a new password before continuing.</div><?php endif; ?>
  <?= csrf_field() ?>
  <label for="current_password">Current password</label>
  <input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
  <label for="new_password">New password</label>
  <input id="new_password" name="new_password" type="password" autocomplete="new-password" required minlength="8">
  <label for="new_password_confirm">Confirm new password</label>
  <input id="new_password_confirm" name="new_password_confirm" type="password" autocomplete="new-password" required>
  <button class="btn" type="submit">Update password</button>
</form>
