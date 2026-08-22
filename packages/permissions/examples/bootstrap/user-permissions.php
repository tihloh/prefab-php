<?php
/**
 * Copyable Bootstrap 5 recipe.
 * Expected:
 * $definitions = $permissions->definitions();
 * $overrides = $permissions->overridesFor('user', $user->id);
 * $resolved = $permissions->resolvedFor($user);
 * $groups = $groupManager->all();
 * $userGroupIds = $groupManager->groupIdsForUser($user->id);
 */
?>
<form method="post">
    <div class="card mb-3">
        <div class="card-header"><strong>Groups</strong></div>
        <div class="card-body row g-2">
            <?php foreach ($groups as $group): ?>
                <div class="col-md-4">
                    <label class="form-check">
                        <input class="form-check-input" type="checkbox" name="groups[]" value="<?= htmlspecialchars((string)$group->id) ?>" <?= in_array((string)$group->id, array_map('strval', $userGroupIds), true) ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= htmlspecialchars($group->name) ?></span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><strong>Permissions</strong></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>Permission</th><th>Override</th><th>Effective</th><th>Source</th></tr></thead>
                <tbody>
                <?php foreach ($definitions as $id => $definition): $result = $resolved[$id]; ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($definition['name'] ?? $id) ?></strong><div class="small text-body-secondary"><?= htmlspecialchars($definition['description'] ?? '') ?></div></td>
                        <td>
                            <select class="form-select form-select-sm" name="permissions[<?= htmlspecialchars($id) ?>]">
                                <option value="" <?= !array_key_exists($id, $overrides) ? 'selected' : '' ?>>Inherit</option>
                                <option value="1" <?= ($overrides[$id] ?? null) === true ? 'selected' : '' ?>>Allow</option>
                                <option value="0" <?= ($overrides[$id] ?? null) === false ? 'selected' : '' ?>>Deny</option>
                            </select>
                        </td>
                        <td><?= $result->allowed ? 'Allow' : 'Deny' ?></td>
                        <td><?= htmlspecialchars($result->source) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>
