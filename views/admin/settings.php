<h1>Settings</h1>
<form class="card" method="post" action="<?= e(url('/admin/settings')) ?>"><?= csrf_field() ?>
  <label for="org_name">Organisation name</label><input id="org_name" name="org_name" required value="<?= e(setting('org_name')) ?>">
  <label for="about_text">About text on the landing page</label><textarea id="about_text" name="about_text" rows="4" maxlength="800"><?= e(setting('about_text')) ?></textarea>
  <label for="public_identity">Public results show participants as</label>
  <select id="public_identity" name="public_identity"><?php foreach (['masked' => 'First name + last initial (e.g. Amina B.)', 'name' => 'Full name', 'id' => 'Participant ID (e.g. VW-00042)', 'anonymous' => 'Anonymous code (e.g. Participant 7F3A9C)'] as $k => $l): ?><option value="<?= $k ?>" <?= setting('public_identity', 'masked') === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
  <p class="hint">Emails and phone numbers are never shown publicly. Administrators always see full names. Contact details in the footer come from the ORG_EMAIL, ORG_PHONE and ORG_ADDRESS settings of the server.</p>
  <label class="check"><input type="checkbox" name="collect_gender" value="1" <?= setting('collect_gender') === '1' ? 'checked' : '' ?>> <span>Ask for gender at registration (optional field)</span></label>
  <button class="btn" type="submit">Save</button></form>
<section class="card"><h2>Age groups</h2><p class="fine">Deactivated groups are hidden from new registrations but remain on existing accounts.</p>
<?php foreach ($ages as $g): ?>
  <form class="row-form" method="post" action="<?= e(url('/admin/settings/age')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$g['id'] ?>">
    <input name="label" value="<?= e($g['label']) ?>" aria-label="Label"><input name="sort_order" type="number" value="<?= (int)$g['sort_order'] ?>" aria-label="Order" class="w-sm">
    <button class="btn btn-sm" name="action" value="save">Save</button><button class="btn btn-sm btn-outline" name="action" value="toggle"><?= $g['is_active'] ? 'Deactivate' : 'Activate' ?></button><span class="fine"><?= (int)$g['n'] ?> accounts</span></form>
<?php endforeach; ?>
  <form class="row-form" method="post" action="<?= e(url('/admin/settings/age')) ?>"><?= csrf_field() ?><input name="label" placeholder="New age group, e.g. 41–50 years" required><input name="sort_order" type="number" value="10" class="w-sm"><button class="btn btn-sm" name="action" value="add">Add</button></form></section>
<section class="card"><h2>Email delivery</h2><p><?= $mail ? badge('active', 'SMTP configured') : badge('closed', 'SMTP not configured') ?></p><p class="fine">Set MAIL_HOST, MAIL_PORT, MAIL_ENCRYPTION, MAIL_USER, MAIL_PASS and MAIL_FROM in the .env file. Without it, password recovery tells users to contact an administrator.</p></section>
