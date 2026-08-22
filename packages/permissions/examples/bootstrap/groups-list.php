<?php
/**
 * Copyable Bootstrap 5 recipe.
 * Expected: $groups = $groupManager->all();
 */
?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <strong>Groups</strong>
        <a href="/groups/create" class="btn btn-primary btn-sm">Add Group</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Name</th><th>Description</th><th>Users</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($groups as $group): ?>
                <tr>
                    <td><?= htmlspecialchars($group->name) ?></td>
                    <td><?= htmlspecialchars((string)$group->description) ?></td>
                    <td><?= $group->usersCount ?></td>
                    <td class="text-end"><a class="btn btn-outline-secondary btn-sm" href="/groups/<?= urlencode((string)$group->id) ?>/edit">Edit</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
