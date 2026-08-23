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

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);

$mainDb = new PDO('sqlite:' . $dataDir . '/main.sqlite');
$mainDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$logDb = new PDO('sqlite:' . $dataDir . '/logs.sqlite');
$logDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/*
 | PROJECT-OWNED TABLE: Prefab Users maps to it but does not create it.
 */
$mainDb->exec("CREATE TABLE IF NOT EXISTS app_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email_address TEXT NOT NULL UNIQUE,
    is_active INTEGER NOT NULL DEFAULT 1,
    password_hash TEXT NOT NULL
)");

$count = (int) $mainDb->query('SELECT COUNT(*) FROM app_users')->fetchColumn();
if ($count === 0) {
    $stmt = $mainDb->prepare('INSERT INTO app_users (full_name, email_address, is_active, password_hash) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Demo Admin', 'demo@example.com', password_hash('password123', PASSWORD_DEFAULT)]);
    $stmt->execute(['Test User', 'test@example.com', password_hash('password123', PASSWORD_DEFAULT)]);
}

class ProjectUser extends PrefabUser implements AuthenticatableUserInterface, PermissionSubjectInterface
{
    public function authId(): int|string { return $this->id; }
    public function authPasswordHash(): ?string { return $this->get('password_hash'); }
    public function authIsActive(): bool { return $this->active; }
    public function permissionSubjectId(): int|string { return $this->id; }
    public function permissionGroupIds(): array { return []; }
}

class ProjectUserFactory implements UserFactoryInterface
{
    public function make(int|string $id, ?string $name, ?string $email, bool $active, array $attributes = []): PrefabUser
    {
        return new ProjectUser($id, $name, $email, $active, $attributes);
    }
}

PrefabConfig::set([
    'database' => $mainDb,
    'modules' => [
        'users' => [
            'table' => 'app_users',
            'map' => new UserMap(
                table: 'app_users', id: 'id', name: 'full_name', email: 'email_address', active: 'is_active',
                attributes: ['password_hash' => 'password_hash'], allowCreate: true, allowUpdate: true, allowDelete: false,
            ),
            'factory' => new ProjectUserFactory(),
        ],
        'permissions' => [
            'definitions' => [
                'documents.view' => ['name' => 'View Documents', 'description' => 'Can view documents', 'default' => true],
                'documents.approve' => ['name' => 'Approve Documents', 'description' => 'Can approve documents', 'default' => false],
            ],
        ],
    ],
]);

$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager(['database' => $logDb]);

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'login') {
        $result = $auth->attempt(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
        $message = $result->success ? 'Signed in successfully.' : 'Sign-in failed.';
    } elseif ($action === 'logout') {
        $auth->logout(); $message = 'Signed out.';
    } elseif ($action === 'rename') {
        $users->update((int) $_POST['user_id'], ['name' => trim($_POST['name'] ?? '')]); $message = 'User updated.';
    } elseif (in_array($action, ['allow', 'deny', 'clear'], true)) {
        $userId = (int) $_POST['user_id']; $permission = (string) $_POST['permission'];
        if ($action === 'clear') $permissions->clear('user', $userId, $permission);
        else $permissions->set('user', $userId, $permission, $action === 'allow');
        $message = 'Permission updated.';
    }
}

$current = $auth->user();
$selectedId = (int) ($_GET['user'] ?? ($current?->authId() ?? 1));
$selected = $users->find($selectedId);
$resolved = $selected instanceof PermissionSubjectInterface ? $permissions->resolvedFor($selected) : [];
$recentLogs = $logs->recent(50);
$mainTables = $mainDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$logTables = $logDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Prefab Database Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-body-tertiary"><nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand">Tihloh Prefab — Real Database Integration</span></div></nav><main class="container py-4">
<?php if ($message): ?><div class="alert alert-info"><?= e($message) ?></div><?php endif; ?>
<div class="alert alert-secondary small"><strong>main.sqlite:</strong> <?= e(implode(', ', $mainTables)) ?> | <strong>logs.sqlite:</strong> <?= e(implode(', ', $logTables)) ?></div>
<div class="row g-4"><div class="col-lg-4"><div class="card mb-4"><div class="card-header fw-semibold">Authentication</div><div class="card-body">
<?php if ($auth->check()): ?><p>Signed in as <strong><?= e($current?->name ?? $auth->id()) ?></strong></p><form method="post"><button name="action" value="logout" class="btn btn-outline-danger w-100">Sign out</button></form>
<?php else: ?><form method="post" class="vstack gap-2"><input class="form-control" name="email" value="demo@example.com"><input class="form-control" type="password" name="password" value="password123"><button name="action" value="login" class="btn btn-primary">Sign in</button></form><?php endif; ?>
</div></div><div class="card"><div class="card-header fw-semibold">Database ownership</div><div class="card-body small"><p><strong>Project:</strong> <code>app_users</code></p><p><strong>Prefab Permissions:</strong> <code>prefab_subject_permissions</code></p><p class="mb-0"><strong>Prefab Logs:</strong> <code>prefab_logs</code> in separate DB</p></div></div></div>
<div class="col-lg-8"><div class="card mb-4"><div class="card-header fw-semibold">Project Users</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th></th></tr></thead><tbody>
<?php foreach ($users->all() as $user): ?><tr><td><?= e($user->id) ?></td><td><?= e($user->name) ?></td><td><?= e($user->email) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="?user=<?= e($user->id) ?>">Manage</a></td></tr><?php endforeach; ?>
</tbody></table></div></div>
<?php if ($selected): ?><div class="card mb-4"><div class="card-header fw-semibold">Manage <?= e($selected->name) ?></div><div class="card-body"><form method="post" class="row g-2 mb-3"><input type="hidden" name="user_id" value="<?= e($selected->id) ?>"><div class="col"><input class="form-control" name="name" value="<?= e($selected->name) ?>"></div><div class="col-auto"><button name="action" value="rename" class="btn btn-outline-primary">Rename</button></div></form><table class="table table-sm align-middle"><thead><tr><th>Permission</th><th>Effective</th><th>Source</th><th>Override</th></tr></thead><tbody>
<?php foreach ($permissions->definitions() as $id => $definition): $result = $resolved[$id]; ?><tr><td><?= e($definition['name'] ?? $id) ?><br><code class="small"><?= e($id) ?></code></td><td><span class="badge text-bg-<?= $result->allowed ? 'success' : 'danger' ?>"><?= $result->allowed ? 'ALLOW' : 'DENY' ?></span></td><td><?= e($result->source) ?></td><td><form method="post" class="btn-group btn-group-sm"><input type="hidden" name="user_id" value="<?= e($selected->id) ?>"><input type="hidden" name="permission" value="<?= e($id) ?>"><button name="action" value="allow" class="btn btn-outline-success">Allow</button><button name="action" value="deny" class="btn btn-outline-danger">Deny</button><button name="action" value="clear" class="btn btn-outline-secondary">Clear</button></form></td></tr><?php endforeach; ?>
</tbody></table></div></div><?php endif; ?>
<div class="card"><div class="card-header fw-semibold">Activity stored in logs.sqlite</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>#</th><th>Action</th><th>Actor</th><th>Subject</th><th>Message</th></tr></thead><tbody><?php foreach ($recentLogs as $log): ?><tr><td><?= e($log['id']) ?></td><td><code><?= e($log['action']) ?></code></td><td><?= e($log['actor_id'] ?? '—') ?></td><td><?= e($log['subject_type']) ?> #<?= e($log['subject_id'] ?? '—') ?></td><td><?= e($log['message'] ?? '') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</div></div></main></body></html>
