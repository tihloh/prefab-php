<?php
/**
 * Copyable Bootstrap 5 recipe.
 * Expected: $users = $userManager->all(50, 0);
 */
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Users</strong>
        <a href="/users/create" class="btn btn-primary btn-sm">Add User</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Email</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars((string)$user->name) ?></td>
                    <td><?= htmlspecialchars((string)$user->email) ?></td>
                    <td><span class="badge text-bg-<?= $user->active ? 'success' : 'secondary' ?>"><?= $user->active ? 'Active' : 'Inactive' ?></span></td>
                    <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="/users/<?= urlencode((string)$user->id) ?>/edit">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
