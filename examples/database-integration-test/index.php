<?php

require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Logs\Services\LogManager;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Users\Contracts\UserFactoryInterface;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Services\UserManager;
use Tihloh\Prefab\Users\User\PrefabUser;

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
 |--------------------------------------------------------------------------
 | Database setup
 |--------------------------------------------------------------------------
 |
 | This example intentionally uses two databases:
 |
 | - main.sqlite stores the application's own data and Prefab Permissions.
 | - logs.sqlite is used only by Prefab Logs.
 |
 | This demonstrates that a module-level database override affects only that
 | module and does not replace the shared database used by other Prefabs.
 |
 */
$dataDir = __DIR__ . '/data';

if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$mainDb = new PDO('sqlite:' . $dataDir . '/main.sqlite');
$mainDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$mainDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

$logDb = new PDO('sqlite:' . $dataDir . '/logs.sqlite');
$logDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$logDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/*
 |--------------------------------------------------------------------------
 | Project-owned schema
 |--------------------------------------------------------------------------
 |
 | These tables belong to the host project, not to Prefab:
 |
 | - app_users
 | - app_groups
 | - app_user_groups
 |
 | Prefab Users maps to the existing app_users table instead of creating its
 | own users table. Group membership also remains owned by the application.
 |
 | Prefab Permissions and Prefab Logs create only the tables they themselves
 | require.
 |
 */
$mainDb->exec(<<<SQL
CREATE TABLE IF NOT EXISTS app_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email_address TEXT NOT NULL UNIQUE,
    is_active INTEGER NOT NULL DEFAULT 1,
    password_hash TEXT NOT NULL
)
SQL);

$mainDb->exec(<<<SQL
CREATE TABLE IF NOT EXISTS app_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
)
SQL);

$mainDb->exec(<<<SQL
CREATE TABLE IF NOT EXISTS app_user_groups (
    user_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, group_id),
    FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES app_groups(id) ON DELETE CASCADE
)
SQL);

/*
 |--------------------------------------------------------------------------
 | Seed project data
 |--------------------------------------------------------------------------
 |
 | Seed only when the project tables are empty so the example remains
 | interactive between page loads.
 |
 */
if ((int) $mainDb->query('SELECT COUNT(*) FROM app_users')->fetchColumn() === 0) {
    $statement = $mainDb->prepare(
        'INSERT INTO app_users (full_name, email_address, is_active, password_hash)
         VALUES (?, ?, 1, ?)',
    );

    $statement->execute([
        'Demo Admin',
        'demo@example.com',
        password_hash('password123', PASSWORD_DEFAULT),
    ]);

    $statement->execute([
        'Test User',
        'test@example.com',
        password_hash('password123', PASSWORD_DEFAULT),
    ]);
}

if ((int) $mainDb->query('SELECT COUNT(*) FROM app_groups')->fetchColumn() === 0) {
    $mainDb->exec(
        "INSERT INTO app_groups (name) VALUES ('Managers'), ('Staff')",
    );

    $managerId = (int) $mainDb
        ->query("SELECT id FROM app_groups WHERE name = 'Managers'")
        ->fetchColumn();

    $staffId = (int) $mainDb
        ->query("SELECT id FROM app_groups WHERE name = 'Staff'")
        ->fetchColumn();

    $assignGroup = $mainDb->prepare(
        'INSERT OR IGNORE INTO app_user_groups (user_id, group_id)
         VALUES (?, ?)',
    );

    $assignGroup->execute([1, $managerId]);
    $assignGroup->execute([2, $staffId]);
}

/*
 |--------------------------------------------------------------------------
 | Project user adapter
 |--------------------------------------------------------------------------
 |
 | The application keeps its own user table and its own user object shape.
 | This class adapts that project user to the contracts expected by Prefab
 | Auth and Prefab Permissions.
 |
 | Prefab therefore does not require the project to replace its user model.
 |
 */
class ProjectUser extends PrefabUser implements
    AuthenticatableUserInterface,
    PermissionSubjectInterface
{
    /**
     * @param int[] $groupIds Group IDs loaded from the project's relationship table.
     */
    public function __construct(
        int|string $id,
        ?string $name,
        ?string $email,
        bool $active,
        array $attributes,
        private array $groupIds,
    ) {
        parent::__construct(
            $id,
            $name,
            $email,
            $active,
            $attributes,
        );
    }

    /** Return the project user ID used by Prefab Auth. */
    public function authId(): int|string
    {
        return $this->id;
    }

    /** Return the stored password hash used for password verification. */
    public function authPasswordHash(): ?string
    {
        return $this->get('password_hash');
    }

    /** Tell Prefab Auth whether this project user may sign in. */
    public function authIsActive(): bool
    {
        return $this->active;
    }

    /** Return the subject ID used by Prefab Permissions. */
    public function permissionSubjectId(): int|string
    {
        return $this->id;
    }

    /**
     * Return project-owned group memberships used for permission inheritance.
     *
     * @return int[]
     */
    public function permissionGroupIds(): array
    {
        return $this->groupIds;
    }
}

/*
 |--------------------------------------------------------------------------
 | Project user factory
 |--------------------------------------------------------------------------
 |
 | Prefab Users reads app_users through UserMap. The factory adds the extra
 | project-specific information needed by Auth and Permissions—in this case,
 | the user's group IDs.
 |
 */
class ProjectUserFactory implements UserFactoryInterface
{
    public function __construct(
        private PDO $database,
    ) {
    }

    public function make(
        int|string $id,
        ?string $name,
        ?string $email,
        bool $active,
        array $attributes = [],
    ): PrefabUser {
        $statement = $this->database->prepare(
            'SELECT group_id
             FROM app_user_groups
             WHERE user_id = ?
             ORDER BY group_id',
        );
        $statement->execute([$id]);

        $groupIds = array_map(
            'intval',
            $statement->fetchAll(PDO::FETCH_COLUMN),
        );

        return new ProjectUser(
            $id,
            $name,
            $email,
            $active,
            $attributes,
            $groupIds,
        );
    }
}

/*
 |--------------------------------------------------------------------------
 | Shared Prefab configuration
 |--------------------------------------------------------------------------
 |
 | Common configuration is declared before the modules are instantiated.
 |
 | The shared database becomes the normal fallback for modules that need a
 | database and do not define one locally.
 |
 | Configuration priority is per setting:
 |
 | 1. Explicit module configuration
 | 2. Shared Prefab configuration
 | 3. Module internal defaults / compatible Prefab resources
 |
 | A local configuration value affects only that module. It is never written
 | back into this shared configuration and never changes another module.
 |
 */
PrefabConfig::set([
    'database' => $mainDb,

    'modules' => [
        /*
         |------------------------------------------------------------------
         | Prefab Users
         |------------------------------------------------------------------
         |
         | The project already owns app_users, so Prefab simply maps its
         | standard fields to the project's actual column names.
         |
         */
        'users' => [
            'table' => 'app_users',

            'map' => new UserMap(
                table: 'app_users',
                id: 'id',
                name: 'full_name',
                email: 'email_address',
                active: 'is_active',
                attributes: [
                    'password_hash' => 'password_hash',
                ],
                allowCreate: true,
                allowUpdate: true,
                allowDelete: false,
            ),

            'factory' => new ProjectUserFactory($mainDb),
        ],

        /*
         |------------------------------------------------------------------
         | Prefab Permissions
         |------------------------------------------------------------------
         |
         | Permission definitions may come from:
         |
         | - an inline PHP array
         | - a PHP template file that returns an array
         | - a JSON template file
         |
         | This example uses the PHP template because it is the most natural
         | option for PHP projects and supports comments and IDE tooling.
         |
         */
        'permissions' => [
            'definitions' => __DIR__ . '/config/permissions.php',

            // Portable JSON alternative:
            // 'definitions' => __DIR__ . '/config/permissions.json',
        ],
    ],
]);

/*
 |--------------------------------------------------------------------------
 | Prefab module declarations
 |--------------------------------------------------------------------------
 |
 | Each module remains independently usable.
 |
 | Because no custom provider was supplied to Auth, it automatically uses the
 | compatible Prefab Users module already declared in this project.
 |
 | Permissions uses the shared main database.
 |
 | Logs explicitly receives $logDb. That override is local to Logs, so Users,
 | Auth, and Permissions continue using their own resolved configuration.
 |
 */
$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager([
    'database' => $logDb,
]);

/*
 |--------------------------------------------------------------------------
 | Seed permission inheritance examples
 |--------------------------------------------------------------------------
 |
 | Managers can approve and delete documents.
 | Staff are explicitly denied document approval.
 |
 | A user-level override still has higher priority than a group permission.
 |
 */
$managerId = (int) $mainDb
    ->query("SELECT id FROM app_groups WHERE name = 'Managers'")
    ->fetchColumn();

$staffId = (int) $mainDb
    ->query("SELECT id FROM app_groups WHERE name = 'Staff'")
    ->fetchColumn();

if (
    $managerId
    && $permissions->overridesFor('group', $managerId) === []
) {
    $permissions->set(
        'group',
        $managerId,
        'documents.approve',
        true,
    );

    $permissions->set(
        'group',
        $managerId,
        'documents.delete',
        true,
    );
}

if (
    $staffId
    && $permissions->overridesFor('group', $staffId) === []
) {
    $permissions->set(
        'group',
        $staffId,
        'documents.approve',
        false,
    );
}

/*
 |--------------------------------------------------------------------------
 | Interactive test actions
 |--------------------------------------------------------------------------
 |
 | These actions deliberately use the Prefab public APIs so this file doubles
 | as an executable example of normal application usage.
 |
 */
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'login':
            $result = $auth->attempt(
                trim($_POST['email'] ?? ''),
                $_POST['password'] ?? '',
            );

            $message = $result->success
                ? 'Signed in successfully.'
                : 'Sign-in failed.';
            break;

        case 'logout':
            $auth->logout();
            $message = 'Signed out.';
            break;

        case 'create_user':
            $users->create([
                'name' => trim($_POST['name'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'active' => true,
                'password_hash' => password_hash(
                    $_POST['password'] ?? 'password123',
                    PASSWORD_DEFAULT,
                ),
            ]);

            $message = 'User created.';
            break;

        case 'rename':
            $users->update(
                (int) $_POST['user_id'],
                [
                    'name' => trim($_POST['name'] ?? ''),
                ],
            );

            $message = 'User updated.';
            break;

        case 'create_group':
            $groupName = trim($_POST['group_name'] ?? '');

            if ($groupName !== '') {
                $statement = $mainDb->prepare(
                    'INSERT OR IGNORE INTO app_groups (name) VALUES (?)',
                );
                $statement->execute([$groupName]);
                $message = 'Group created.';
            }
            break;

        case 'assign_group':
            $statement = $mainDb->prepare(
                'INSERT OR IGNORE INTO app_user_groups (user_id, group_id)
                 VALUES (?, ?)',
            );
            $statement->execute([
                (int) $_POST['user_id'],
                (int) $_POST['group_id'],
            ]);

            $message = 'User added to group.';
            break;

        case 'remove_group':
            $statement = $mainDb->prepare(
                'DELETE FROM app_user_groups
                 WHERE user_id = ? AND group_id = ?',
            );
            $statement->execute([
                (int) $_POST['user_id'],
                (int) $_POST['group_id'],
            ]);

            $message = 'User removed from group.';
            break;

        case 'user_allow':
        case 'user_deny':
        case 'user_clear':
            $userId = (int) $_POST['user_id'];
            $permission = (string) $_POST['permission'];

            if ($action === 'user_clear') {
                $permissions->clear(
                    'user',
                    $userId,
                    $permission,
                );
            } else {
                $permissions->set(
                    'user',
                    $userId,
                    $permission,
                    $action === 'user_allow',
                );
            }

            $message = 'User permission override updated.';
            break;

        case 'group_allow':
        case 'group_deny':
        case 'group_clear':
            $groupId = (int) $_POST['group_id'];
            $permission = (string) $_POST['permission'];

            if ($action === 'group_clear') {
                $permissions->clear(
                    'group',
                    $groupId,
                    $permission,
                );
            } else {
                $permissions->set(
                    'group',
                    $groupId,
                    $permission,
                    $action === 'group_allow',
                );
            }

            $message = 'Group permission updated.';
            break;
    }
}

/*
 |--------------------------------------------------------------------------
 | View data
 |--------------------------------------------------------------------------
 */
$currentUser = $auth->user();
$allUsers = $users->all();

$groups = $mainDb
    ->query('SELECT id, name FROM app_groups ORDER BY name')
    ->fetchAll();

$groupNames = [];

foreach ($groups as $group) {
    $groupNames[(int) $group['id']] = $group['name'];
}

$selectedId = (int) (
    $_GET['user']
    ?? $currentUser?->authId()
    ?? $allUsers[0]->id
    ?? 1
);

$selectedUser = $users->find($selectedId);

$resolvedPermissions = $selectedUser instanceof PermissionSubjectInterface
    ? $permissions->resolvedFor($selectedUser)
    : [];

$selectedGroups = [];

if ($selectedUser instanceof PermissionSubjectInterface) {
    foreach ($selectedUser->permissionGroupIds() as $groupId) {
        $selectedGroups[] = [
            'id' => $groupId,
            'name' => $groupNames[(int) $groupId]
                ?? "Group #{$groupId}",
        ];
    }
}

/*
 |--------------------------------------------------------------------------
 | Log views
 |--------------------------------------------------------------------------
 |
 | Both views come from the same records in logs.sqlite:
 |
 | - recent() returns the complete technical/audit representation.
 | - humanRecent() returns a compact ordinary-user-friendly presentation.
 |
 | Human presentation does not duplicate the stored log data.
 |
 */
$technicalLogs = $logs->recent(100);

$humanLogs = $logs->humanRecent(
    100,
    0,
    actorResolver: fn ($id) => $users->find($id)?->name,
    subjectResolver: function ($type, $id) use ($users, $groupNames) {
        return match ($type) {
            'user' => $users->find($id)?->name,
            'group' => $groupNames[(int) $id] ?? null,
            default => null,
        };
    },
);

$mainTables = $mainDb
    ->query(
        "SELECT name
         FROM sqlite_master
         WHERE type = 'table'
           AND name NOT LIKE 'sqlite_%'
         ORDER BY name",
    )
    ->fetchAll(PDO::FETCH_COLUMN);

$logTables = $logDb
    ->query(
        "SELECT name
         FROM sqlite_master
         WHERE type = 'table'
           AND name NOT LIKE 'sqlite_%'
         ORDER BY name",
    )
    ->fetchAll(PDO::FETCH_COLUMN);

/** Escape output for safe HTML rendering. */
function e(mixed $value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8',
    );
}

/** Convert a permission result source into a management-UI label. */
function sourceLabel(object $result, array $groupNames): string
{
    if ($result->source !== 'group') {
        return ucfirst($result->source);
    }

    $groupIds = $result->groups ?: $result->deniedGroups;

    $names = array_map(
        fn ($id) => $groupNames[(int) $id] ?? "Group #{$id}",
        $groupIds,
    );

    return 'Inherited from ' . implode(', ', $names);
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prefab Database Integration</title>
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
</head>
<body class="bg-body-tertiary">
<nav class="navbar navbar-dark bg-dark">
    <div class="container">
        <span class="navbar-brand">
            Tihloh Prefab — Database Integration Test
        </span>
    </div>
</nav>

<main class="container py-4">
    <?php if ($message): ?>
        <div class="alert alert-info">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-secondary small">
        <strong>main.sqlite:</strong>
        <?= e(implode(', ', $mainTables)) ?>

        <span class="mx-2">|</span>

        <strong>logs.sqlite:</strong>
        <?= e(implode(', ', $logTables)) ?>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header fw-semibold">
                    Authentication
                </div>

                <div class="card-body">
                    <?php if ($auth->check()): ?>
                        <p>
                            Signed in as
                            <strong><?= e($currentUser?->name ?? $auth->id()) ?></strong>
                        </p>

                        <form method="post">
                            <button
                                name="action"
                                value="logout"
                                class="btn btn-outline-danger w-100"
                            >
                                Sign out
                            </button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="vstack gap-2">
                            <input
                                class="form-control"
                                name="email"
                                value="demo@example.com"
                                placeholder="Email"
                            >

                            <input
                                class="form-control"
                                type="password"
                                name="password"
                                value="password123"
                                placeholder="Password"
                            >

                            <button
                                name="action"
                                value="login"
                                class="btn btn-primary"
                            >
                                Sign in
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header fw-semibold">
                    Create User
                </div>

                <div class="card-body">
                    <form method="post" class="vstack gap-2">
                        <input
                            class="form-control"
                            name="name"
                            placeholder="Full name"
                            required
                        >

                        <input
                            class="form-control"
                            type="email"
                            name="email"
                            placeholder="Email"
                            required
                        >

                        <input
                            class="form-control"
                            type="password"
                            name="password"
                            value="password123"
                        >

                        <button
                            name="action"
                            value="create_user"
                            class="btn btn-success"
                        >
                            Create user
                        </button>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header fw-semibold">
                    Create Group
                </div>

                <div class="card-body">
                    <form method="post" class="input-group">
                        <input
                            class="form-control"
                            name="group_name"
                            placeholder="Group name"
                            required
                        >

                        <button
                            name="action"
                            value="create_group"
                            class="btn btn-outline-primary"
                        >
                            Add
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    Users
                </div>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <tr>
                                <td><?= e($user->id) ?></td>
                                <td><?= e($user->name) ?></td>
                                <td><?= e($user->email) ?></td>
                                <td class="text-end">
                                    <a
                                        class="btn btn-sm btn-outline-secondary"
                                        href="?user=<?= e($user->id) ?>"
                                    >
                                        Manage
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($selectedUser): ?>
                <div class="card mb-4">
                    <div class="card-header fw-semibold">
                        Manage <?= e($selectedUser->name) ?>
                    </div>

                    <div class="card-body">
                        <form method="post" class="row g-2 mb-4">
                            <input
                                type="hidden"
                                name="user_id"
                                value="<?= e($selectedUser->id) ?>"
                            >

                            <div class="col">
                                <input
                                    class="form-control"
                                    name="name"
                                    value="<?= e($selectedUser->name) ?>"
                                >
                            </div>

                            <div class="col-auto">
                                <button
                                    name="action"
                                    value="rename"
                                    class="btn btn-outline-primary"
                                >
                                    Rename
                                </button>
                            </div>
                        </form>

                        <h6>Groups</h6>

                        <div class="d-flex flex-wrap gap-2 mb-3">
                            <?php if (!$selectedGroups): ?>
                                <span class="text-muted">No groups</span>
                            <?php endif; ?>

                            <?php foreach ($selectedGroups as $group): ?>
                                <form method="post">
                                    <input
                                        type="hidden"
                                        name="user_id"
                                        value="<?= e($selectedUser->id) ?>"
                                    >
                                    <input
                                        type="hidden"
                                        name="group_id"
                                        value="<?= e($group['id']) ?>"
                                    >

                                    <button
                                        name="action"
                                        value="remove_group"
                                        class="btn btn-sm btn-secondary"
                                    >
                                        <?= e($group['name']) ?> ×
                                    </button>
                                </form>
                            <?php endforeach; ?>
                        </div>

                        <form method="post" class="row g-2 mb-4">
                            <input
                                type="hidden"
                                name="user_id"
                                value="<?= e($selectedUser->id) ?>"
                            >

                            <div class="col">
                                <select class="form-select" name="group_id">
                                    <?php foreach ($groups as $group): ?>
                                        <option value="<?= e($group['id']) ?>">
                                            <?= e($group['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-auto">
                                <button
                                    name="action"
                                    value="assign_group"
                                    class="btn btn-outline-secondary"
                                >
                                    Add to group
                                </button>
                            </div>
                        </form>

                        <h6>Effective Permissions</h6>

                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead>
                                <tr>
                                    <th>Permission</th>
                                    <th>Effective</th>
                                    <th>Source</th>
                                    <th>User Override</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($permissions->definitions() as $id => $definition): ?>
                                    <?php $result = $resolvedPermissions[$id]; ?>
                                    <tr>
                                        <td>
                                            <?= e($definition['name'] ?? $id) ?>
                                            <br>
                                            <code><?= e($id) ?></code>
                                        </td>
                                        <td>
                                            <span class="badge text-bg-<?= $result->allowed ? 'success' : 'danger' ?>">
                                                <?= $result->allowed ? 'ALLOW' : 'DENY' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= e(sourceLabel($result, $groupNames)) ?>
                                        </td>
                                        <td>
                                            <form method="post" class="btn-group btn-group-sm">
                                                <input
                                                    type="hidden"
                                                    name="user_id"
                                                    value="<?= e($selectedUser->id) ?>"
                                                >
                                                <input
                                                    type="hidden"
                                                    name="permission"
                                                    value="<?= e($id) ?>"
                                                >

                                                <button
                                                    name="action"
                                                    value="user_allow"
                                                    class="btn btn-outline-success"
                                                >
                                                    Allow
                                                </button>

                                                <button
                                                    name="action"
                                                    value="user_deny"
                                                    class="btn btn-outline-danger"
                                                >
                                                    Deny
                                                </button>

                                                <button
                                                    name="action"
                                                    value="user_clear"
                                                    class="btn btn-outline-secondary"
                                                >
                                                    Inherit
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header fw-semibold">
                    Group Permissions
                </div>

                <div class="card-body">
                    <p class="small text-muted">
                        Group values are inherited by members unless a user-level
                        override exists.
                    </p>

                    <?php foreach ($groups as $group): ?>
                        <?php
                        $groupPermissions = $permissions->overridesFor(
                            'group',
                            $group['id'],
                        );
                        ?>

                        <div class="border rounded p-3 mb-3">
                            <h6><?= e($group['name']) ?></h6>

                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <tbody>
                                    <?php foreach ($permissions->definitions() as $id => $definition): ?>
                                        <tr>
                                            <td>
                                                <?= e($definition['name'] ?? $id) ?>
                                            </td>
                                            <td>
                                                <?php if (array_key_exists($id, $groupPermissions)): ?>
                                                    <span class="badge text-bg-<?= $groupPermissions[$id] ? 'success' : 'danger' ?>">
                                                        <?= $groupPermissions[$id] ? 'ALLOW' : 'DENY' ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge text-bg-secondary">
                                                        INHERIT DEFAULT
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <form method="post" class="btn-group btn-group-sm">
                                                    <input
                                                        type="hidden"
                                                        name="group_id"
                                                        value="<?= e($group['id']) ?>"
                                                    >
                                                    <input
                                                        type="hidden"
                                                        name="permission"
                                                        value="<?= e($id) ?>"
                                                    >

                                                    <button
                                                        name="action"
                                                        value="group_allow"
                                                        class="btn btn-outline-success"
                                                    >
                                                        Allow
                                                    </button>

                                                    <button
                                                        name="action"
                                                        value="group_deny"
                                                        class="btn btn-outline-danger"
                                                    >
                                                        Deny
                                                    </button>

                                                    <button
                                                        name="action"
                                                        value="group_clear"
                                                        class="btn btn-outline-secondary"
                                                    >
                                                        Clear
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span class="fw-semibold">
                        Activity Logs
                        <span class="badge text-bg-secondary">
                            <?= count($technicalLogs) ?>
                        </span>
                    </span>

                    <span class="small text-muted">
                        stored in separate logs.sqlite
                    </span>
                </div>

                <div class="card-body pb-0">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <button
                                class="nav-link active"
                                data-bs-toggle="tab"
                                data-bs-target="#humanLogs"
                                type="button"
                            >
                                User Friendly
                            </button>
                        </li>

                        <li class="nav-item">
                            <button
                                class="nav-link"
                                data-bs-toggle="tab"
                                data-bs-target="#technicalLogs"
                                type="button"
                            >
                                Technical / Audit
                            </button>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div
                        class="tab-pane fade show active"
                        id="humanLogs"
                    >
                        <div class="list-group list-group-flush">
                            <?php if (!$humanLogs): ?>
                                <div class="p-4 text-muted">
                                    No activity yet.
                                </div>
                            <?php endif; ?>

                            <?php foreach ($humanLogs as $log): ?>
                                <div class="list-group-item py-2">
                                    <div class="d-flex justify-content-between gap-3">
                                        <div>
                                            <?= e($log['summary']) ?>

                                            <?php foreach ($log['details'] as $detail): ?>
                                                <div class="small text-muted">
                                                    <?= e($detail['field']) ?>:
                                                    <?= e($detail['old']) ?>
                                                    →
                                                    <strong><?= e($detail['new']) ?></strong>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <small class="text-muted text-nowrap">
                                            <?= e($log['when'] ?? '') ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div
                        class="tab-pane fade"
                        id="technicalLogs"
                    >
                        <div class="table-responsive">
                            <table class="table table-hover table-sm align-middle mb-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Action</th>
                                    <th>Actor</th>
                                    <th>Subject</th>
                                    <th>Message</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($technicalLogs as $log): ?>
                                    <tr>
                                        <td><?= e($log['id']) ?></td>
                                        <td>
                                            <code><?= e($log['action']) ?></code>
                                        </td>
                                        <td>
                                            <?= e($log['actor_id'] ?? '—') ?>
                                        </td>
                                        <td>
                                            <code>
                                                <?= e($log['subject_type']) ?>
                                                #<?= e($log['subject_id'] ?? '—') ?>
                                            </code>
                                        </td>
                                        <td>
                                            <?= e($log['message'] ?? '') ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
