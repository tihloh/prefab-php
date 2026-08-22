<?php
/** Copyable Bootstrap 5 recipe. Expected: $user from UserManager::find(). */
?>
<div class="card">
  <div class="card-header"><strong>User Details</strong></div>
  <div class="card-body">
    <dl class="row mb-0">
      <dt class="col-sm-3">ID</dt><dd class="col-sm-9"><?= htmlspecialchars((string)$user->id) ?></dd>
      <dt class="col-sm-3">Name</dt><dd class="col-sm-9"><?= htmlspecialchars((string)($user->name ?? '')) ?></dd>
      <dt class="col-sm-3">Email</dt><dd class="col-sm-9"><?= htmlspecialchars((string)($user->email ?? '')) ?></dd>
      <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= !empty($user->active) ? 'Active' : 'Inactive' ?></dd>
    </dl>
  </div>
</div>
