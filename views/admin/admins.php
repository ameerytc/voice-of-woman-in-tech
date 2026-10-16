<h1>Administrator accounts</h1>
<div class="table-wrap"><table class="cards-sm"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Manage</th></tr></thead><tbody>
<?php foreach ($rows as $r): ?><tr><td data-l="Name"><?= e($r['full_name']) ?></td><td data-l="Email"><?= e($r['email']) ?></td><td data-l="Role"><?= e($r['roles']) ?></td><td data-l="Status"><?= badge($r['status']) ?></td><td data-l="Last login"><?= e(fmt_dt($r['last_login_at'])) ?></td>
<td data-l="Manage">
  <form class="inline" method="post" action="<?= e(url('/admin/admins/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="role"><select name="role_id"><?php foreach ($roles as $ro): ?><option value="<?= (int)$ro['id'] ?>" <?= (int)$r['role_id'] === (int)$ro['id'] ? 'selected' : '' ?>><?= e($ro['label']) ?></option><?php endforeach; ?></select><button class="btn btn-sm" type="submit">Set role</button></form>
  <form class="inline" method="post" action="<?= e(url('/admin/admins/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="status"><input type="hidden" name="status" value="<?= $r['status'] === 'active' ? 'inactive' : 'active' ?>"><button class="btn btn-sm btn-outline" type="submit"><?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button></form>
  <form class="inline" method="post" action="<?= e(url('/admin/admins/' . $r['id'])) ?>"><?= csrf_field() ?><input type="hidden" name="action" value="password"><input type="password" name="password" placeholder="Temporary password" autocomplete="new-password" required><button class="btn btn-sm btn-outline" type="submit">Reset password</button></form></td></tr>
<?php endforeach; ?></tbody></table></div>
<form class="card" method="post" action="<?= e(url('/admin/admins')) ?>"><h2>Add administrator</h2><?= csrf_field() ?>
  <label for="full_name">Full name</label><input id="full_name" name="full_name" required value="<?= e(old('full_name')) ?>">
  <label for="email">Email</label><input id="email" name="email" type="email" required value="<?= e(old('email')) ?>">
  <label for="role_id">Role</label><select id="role_id" name="role_id"><?php foreach ($roles as $ro): ?><option value="<?= (int)$ro['id'] ?>"><?= e($ro['label']) ?></option><?php endforeach; ?></select>
  <label for="password">Temporary password</label><input id="password" name="password" type="password" autocomplete="new-password" required><p class="hint">They must change it at first login. Share it through a secure channel.</p>
  <button class="btn" type="submit">Create administrator</button></form>
<section class="card"><h2>Role permissions</h2><ul class="plain">
<li><strong>Super Administrator</strong> — full access, including administrators, settings, audit logs, every participant record and every submission.</li>
<li><strong>Quiz Manager</strong> — quizzes, questions, announcements, participants (incl. extra attempts), submissions (view), reports.</li>
<li><strong>Results Reviewer</strong> — submissions, eligibility decisions, score corrections, results finalisation, participants (view), reports.</li>
<li><strong>Read-only Report Viewer</strong> — reports and published results only.</li></ul></section>
