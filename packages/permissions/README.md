# Tihloh Prefab Permissions

Framework-independent permissions and authorization for PHP, with optional framework integration.

Prefab Permissions is standalone. It does not require Prefab Database, Users, Auth, Logs, Laravel, or another framework package.

## Permission definitions

Definitions may come from an inline PHP array, a PHP template file, or a JSON template file.

```php
$permissions = new PermissionManager([
    'definitions' => __DIR__ . '/config/permissions.php',
]);
```

Equivalent JSON is also supported:

```php
$permissions = new PermissionManager([
    'definitions' => __DIR__ . '/config/permissions.json',
]);
```

A definition may include a stable permission ID, human-friendly name, description, and default value.

## Standalone storage

A custom `PermissionStoreInterface` can be supplied directly:

```php
$permissions = new PermissionManager(
    definitions: $definitions,
    store: $customStore,
);
```

The built-in database store accepts either plain PDO or Prefab's `DatabaseInterface`:

```php
$permissions = new PermissionManager(
    $definitions,
    new PdoPermissionStore($pdo),
);
```

The historical `PdoPermissionStore` class name is retained for compatibility, but it now consumes `DatabaseInterface` internally. PDO is automatically wrapped by `PdoDatabaseAdapter`.

## Automatic database configuration

The normal quick form is:

```php
PrefabConfig::set([
    'database' => $mainPdo,

    'modules' => [
        'permissions' => [
            'definitions' => __DIR__ . '/config/permissions.php',
        ],
    ],
]);

$permissions = new PermissionManager();
```

Or use a named Prefab Database connection:

```php
PrefabConfig::set([
    'modules' => [
        'permissions' => [
            'connection' => 'security',
        ],
    ],
]);
```

Resolution remains:

```text
1. direct Permissions store / database / connection
2. Permissions-specific PrefabConfig
3. common PrefabConfig
4. compatible database capability
5. clear error when database-backed storage is still unresolved
```

## Permission inheritance

Effective permission resolution is:

```text
User override
    ↓
Group permission
    ↓
Permission definition default
```

A missing user override means inherit. Clearing an override restores group/default resolution.

```php
$permissions->set(
    'user',
    $userId,
    'documents.approve',
    true,
);

$permissions->clear(
    'user',
    $userId,
    'documents.approve',
);
```

## Subject integration

Projects keep their own user/group models. A user only needs to implement `PermissionSubjectInterface` when object-based resolution is desired:

```php
class User implements PermissionSubjectInterface
{
    public function __construct(
        public int $id,
        public array $groupIds = [],
    ) {
    }

    public function permissionSubjectId(): int|string
    {
        return $this->id;
    }

    public function permissionGroupIds(): array
    {
        return $this->groupIds;
    }
}
```

Then:

```php
if ($permissions->can($user, 'documents.approve')) {
    // Allowed.
}
```

## Laravel compatibility

Laravel's own user model and Auth/Gate system do not need to be replaced. A Laravel adapter/bridge can expose the required Prefab contracts while Laravel remains the host framework.

The same principle applies to other frameworks: Prefab consumes contracts/capabilities rather than requiring its own full application stack.

## Database abstraction

Built-in storage uses:

```text
PDO or framework database
        ↓
DatabaseInterface
        ↓
PdoPermissionStore
```

The remaining database-specific DDL/upsert differences are isolated inside the built-in store for MySQL/MariaDB, PostgreSQL, SQLite, and SQL Server. A future optional schema abstraction can centralize those differences without enlarging the minimal shared database contract prematurely.

## Logging and diagnostics

When Prefab Logs exists, permission changes are emitted automatically. Human-friendly logs can use permission definition names, for example:

```text
Demo Admin denied View Documents for Test User.
```

Use:

```php
$permissions->explain();
```

to inspect how definitions, storage, table, and database resources were resolved.
