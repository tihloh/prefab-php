# Groups in Prefab

Prefab treats a group as a simple user-organization concept first, then lets Permissions enhance the same group with authorization rules.

## Users only

`prefab-users` supports simple groups with:

- `id`
- `name`
- optional `description`
- user/group membership

```php
$groups = $users->groups();

$budget = $groups->create('Budget Office', 'Provincial Budget Office users');
$groups->addUser($userId, $budget->id);

$userGroups = $groups->groupIdsForUser($userId);
```

No permission rules are required. When Users has a database resource, it ensures the shared `prefab_groups` and `prefab_user_groups` tables exist.

## Permissions only

`prefab-permissions` keeps its own group management for projects that use another user manager or have no group feature. It can create groups, maintain membership, and attach permission overrides.

## Users + Permissions

When Prefab Users is present, it publishes the `group_provider` capability. Prefab Permissions detects that provider and operates on the same groups and memberships rather than creating a second group system.

```text
Prefab Users
   └── groups + memberships
            │
            └── group_provider
                     ↓
              Prefab Permissions
                     └── permission overrides
```

The result is one logical group regardless of which module is used to manage it:

```php
$users->groups()->addUser($userId, $groupId);

// The same membership is visible to Permissions.
$permissionGroups->groupIdsForUser($userId);
```

Likewise, a group created through Permissions is visible through Users when both use the shared provider.

## Design rule

> Users owns simple group organization. Permissions adds authorization. When both are installed, they share the same group provider.

This keeps both modules useful independently while making them stronger together.
