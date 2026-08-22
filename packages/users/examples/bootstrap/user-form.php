<?php
/** Copyable Bootstrap 5 recipe. Expected optional $user for edit mode. Project owns validation/routes/storage. */
$user = $user ?? null;
?>
<div class="card">
  <div class="card-header"><strong><?= $user ? 'Edit User' : 'Create User' ?></strong></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= htmlspecialchars((string)($user->name ?? '')) ?>"></div>
      <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= htmlspecialchars((string)($user->email ?? '')) ?>"></div>
      <div class="col-md-6"><label class="form-label">Password</label><input type="password" class="form-control" name="password"></div>
      <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="active"><option value="1" <?= !isset($user) || !empty($user->active) ? 'selected' : '' ?>>Active</option><option value="0" <?= isset($user) && empty($user->active) ? 'selected' : '' ?>>Inactive</option></select></div>
    </div>
  </div>
</div>
