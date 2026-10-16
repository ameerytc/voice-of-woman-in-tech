<h1 style="color:#fff">My profile</h1>
<form class="card" method="post" action="<?= e(url('/account/avatar')) ?>" enctype="multipart/form-data"><?= csrf_field() ?><h3>Profile picture</h3>
  <div class="upload"><?= avatar_html(['id' => $u['id'], 'full_name' => $u['full_name'], 'has_avatar' => has_avatar((int)$u['id'])], 84, '') ?><input type="file" name="avatar" accept="image/jpeg,image/png,image/webp" data-preview=".upload .avatar" required>
  <button class="btn btn-sm" type="submit">Upload</button><?php if (has_avatar((int)$u['id'])): ?><button class="btn btn-sm btn-outline" type="submit" name="remove" value="1" data-confirm="Remove your profile picture?" formnovalidate>Remove</button><?php endif; ?></div>
  <p class="hint">JPG, PNG or WebP, up to 3 MB. The picture is cropped to a square and location data is removed.</p></form>
<div class="grid2">
<form class="card" method="post" action="<?= e(url('/profile')) ?>">
  <?= csrf_field() ?>
  <label for="full_name">Full name</label><input id="full_name" name="full_name" required maxlength="120" value="<?= e($u['full_name']) ?>">
  <label>Email address</label><input value="<?= e($u['email']) ?>" disabled><p class="hint">To change your email, send a correction request below.</p>
  <label for="phone">Phone number</label><input id="phone" name="phone" type="tel" required value="<?= e($u['phone']) ?>">
  <label for="age_group_id">Age group</label>
  <select id="age_group_id" name="age_group_id"><?php foreach ($ages as $g): ?><option value="<?= (int)$g['id'] ?>" <?= (int)$u['age_group_id'] === (int)$g['id'] ? 'selected' : '' ?>><?= e($g['label']) ?></option><?php endforeach; ?></select>
  <label for="state">State of residence</label>
  <select id="state" name="state"><option value="">Prefer not to say</option><?php foreach (STATES as $s): ?><option <?= $u['state'] === $s ? 'selected' : '' ?>><?= e($s) ?></option><?php endforeach; ?></select>
  <?php if (setting('collect_gender') === '1'): ?>
  <label for="gender">Gender (optional)</label>
  <select id="gender" name="gender"><option value="">Prefer not to say</option><?php foreach (['Female', 'Male'] as $g): ?><option <?= $u['gender'] === $g ? 'selected' : '' ?>><?= $g ?></option><?php endforeach; ?></select>
  <?php endif; ?>
  <button class="btn" type="submit">Save changes</button>
</form>
<div>
  <div class="card"><h3>Account</h3>
    <p>Registered: <strong><?= e(fmt_dt($u['created_at'], 'd M Y')) ?></strong></p>
    <p>Status: <?= badge($u['status']) ?></p>
    <p>Age group: <?= e($ageLabel ?? '—') ?></p></div>
  <form class="card" method="post" action="<?= e(url('/profile/request')) ?>">
    <h3>Privacy requests</h3><?= csrf_field() ?>
    <label for="type">Request</label>
    <select id="type" name="type"><option value="correction">Correct my information</option><option value="deletion">Delete my account and personal data</option></select>
    <label for="message">Details</label><textarea id="message" name="message" rows="3" required></textarea>
    <button class="btn btn-outline" type="submit">Send request</button>
    <?php foreach ($reqs as $r): ?><p class="fine"><?= e(ucfirst($r['type'])) ?> request · <?= e(fmt_dt($r['created_at'], 'd M Y')) ?> · <?= badge($r['status'] === 'open' ? 'open_request' : $r['status']) ?></p><?php endforeach; ?>
  </form>
</div></div>
