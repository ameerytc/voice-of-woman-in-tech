<form class="card" method="post" action="<?= e(url('/reset-password/' . $token)) ?>">
  <h1>Choose a new password</h1>
  <?= csrf_field() ?>
  <label for="password">New password</label>
  <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
  <label for="password_confirm">Confirm new password</label>
  <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
  <button class="btn btn-block" type="submit">Change password</button>
</form>
