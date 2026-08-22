<?php
/** Copyable Bootstrap 5 recipe. Expected: $users from UserManager::all() or equivalent paginated data. */
?>
<div class="card">
  <div class="card-header d-flex justify-content-between align-items-center">
    <strong>Users</strong>
  </div>
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead>
      <tbody>
      <?php foreach ($users as $user): ?>
        <tr>
          <td><?= htmlspecialchars((string)($user->name ?? '')) ?></td>
          <td><?= htmlspecialchars((string)($user->email ?? '')) ?></td>
          <td><span class="badge text-bg-<?= !empty($user->active) ? 'success' : 'secondary' ?>"><?= !empty($user->active) ? 'Active' : 'Inactive' ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
