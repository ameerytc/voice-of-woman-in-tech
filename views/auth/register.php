<form class="card" method="post" action="<?= e(url('/register')) ?>" enctype="multipart/form-data" novalidate>
  <h1>Create your account</h1>
  <?= csrf_field() ?>
  <div class="upload"><span id="av-prev" class="avatar avatar-ini" style="width:84px;height:84px;font-size:32px" aria-hidden="true">VW</span>
    <div><label for="avatar" style="margin-top:0">Profile picture <span class="fine">(optional)</span></label><input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" data-preview="#av-prev"><p class="hint">JPG, PNG or WebP, up to 3 MB. Only you and administrators can see it.</p></div></div>
  <label for="full_name">Full name</label>
  <input id="full_name" name="full_name" autocomplete="name" required maxlength="120" value="<?= e(old('full_name')) ?>">
  <label for="email">Email address</label>
  <input id="email" name="email" type="email" autocomplete="email" required value="<?= e(old('email')) ?>">
  <label for="phone">Phone number</label>
  <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" placeholder="0803 123 4567" required value="<?= e(old('phone')) ?>">
  <label for="age_group_id">Age group</label>
  <select id="age_group_id" name="age_group_id" required>
    <option value="">Select…</option>
    <?php foreach ($ages as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)old('age_group_id') === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['label']) ?></option><?php endforeach; ?>
  </select>
  <label for="state">State of residence <span class="fine">(optional)</span></label>
  <select id="state" name="state"><option value="">Prefer not to say</option>
    <?php foreach (STATES as $s): ?><option <?= old('state') === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?>
  </select>
  <?php if (setting('collect_gender') === '1'): ?>
  <label for="gender">Gender <span class="fine">(optional)</span></label>
  <select id="gender" name="gender"><option value="">Prefer not to say</option><option>Female</option><option>Male</option></select>
  <?php endif; ?>
  <label for="password">Password</label>
  <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
  <p class="hint">At least 8 characters with letters and numbers.</p>
  <label for="password_confirm">Confirm password</label>
  <input id="password_confirm" name="password_confirm" type="password" autocomplete="new-password" required>
  <button class="btn btn-block" type="submit">Create account</button>
  <p class="center">Already registered? <a href="<?= e(url('/login')) ?>">Log in</a></p>
</form>
