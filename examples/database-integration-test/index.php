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
$mainDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$logDb = new PDO('sqlite:' . $dataDir . '/logs.sqlite');
$logDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$logDb->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

/*
 |--------------------------------------------------------------------------
 | PROJECT-OWNED TABLES
 |--------------------------------------------------------------------------
 | These belong to the host project, not Prefab:
 |   app_users        - existing project users
 |   app_groups       - project groups/roles/teams
 |   app_user_groups  - project user-group relationship
 |
 | Prefab Users maps to app_users. Permissions consumes the group IDs exposed
 | by ProjectUser, but does not own the project's group tables.
 */
$mainDb->exec("CREATE TABLE IF NOT EXISTS app_users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    full_name TEXT NOT NULL,
    email_address TEXT NOT NULL UNIQUE,
    is_active INTEGER NOT NULL DEFAULT 1,
    password_hash TEXT NOT NULL
)");
$mainDb->exec("CREATE TABLE IF NOT EXISTS app_groups (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
)");
$mainDb->exec("CREATE TABLE IF NOT EXISTS app_user_groups (
    user_id INTEGER NOT NULL,
    group_id INTEGER NOT NULL,
    PRIMARY KEY (user_id, group_id),
    FOREIGN KEY (user_id) REFERENCES app_users(id) ON DELETE CASCADE,
    FOREIGN KEY (group_id) REFERENCES app_groups(id) ON DELETE CASCADE
)");

if ((int)$mainDb->query('SELECT COUNT(*) FROM app_users')->fetchColumn() === 0) {
    $stmt = $mainDb->prepare('INSERT INTO app_users (full_name, email_address, is_active, password_hash) VALUES (?, ?, 1, ?)');
    $stmt->execute(['Demo Admin', 'demo@example.com', password_hash('password123', PASSWORD_DEFAULT)]);
    $stmt->execute(['Test User', 'test@example.com', password_hash('password123', PASSWORD_DEFAULT)]);
}
if ((int)$mainDb->query('SELECT COUNT(*) FROM app_groups')->fetchColumn() === 0) {
    $mainDb->exec("INSERT INTO app_groups (name) VALUES ('Managers'), ('Staff')");
    $managerId = (int)$mainDb->query("SELECT id FROM app_groups WHERE name='Managers'")->fetchColumn();
    $staffId = (int)$mainDb->query("SELECT id FROM app_groups WHERE name='Staff'")->fetchColumn();
    $mainDb->prepare('INSERT OR IGNORE INTO app_user_groups (user_id, group_id) VALUES (?, ?)')->execute([1, $managerId]);
    $mainDb->prepare('INSERT OR IGNORE INTO app_user_groups (user_id, group_id) VALUES (?, ?)')->execute([2, $staffId]);
}

class ProjectUser extends PrefabUser implements AuthenticatableUserInterface, PermissionSubjectInterface
{
    public function __construct(
        int|string $id,
        ?string $name,
        ?string $email,
        bool $active,
        array $attributes,
        private array $groupIds,
    ) {
        parent::__construct($id, $name, $email, $active, $attributes);
    }

    public function authId(): int|string { return $this->id; }
    public function authPasswordHash(): ?string { return $this->get('password_hash'); }
    public function authIsActive(): bool { return $this->active; }
    public function permissionSubjectId(): int|string { return $this->id; }
    public function permissionGroupIds(): array { return $this->groupIds; }
}

class ProjectUserFactory implements UserFactoryInterface
{
    public function __construct(private PDO $db) {}

    public function make(int|string $id, ?string $name, ?string $email, bool $active, array $attributes = []): PrefabUser
    {
        $stmt = $this->db->prepare('SELECT group_id FROM app_user_groups WHERE user_id = ? ORDER BY group_id');
        $stmt->execute([$id]);
        $groupIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        return new ProjectUser($id, $name, $email, $active, $attributes, $groupIds);
    }
}

/* Shared/default configuration. Module-local constructor config wins only for that module. */
PrefabConfig::set([
    'database' => $mainDb,
    'modules' => [
        'users' => [
            'table' => 'app_users',
            'map' => new UserMap(
                table: 'app_users',
                id: 'id',
                name: 'full_name',
                email: 'email_address',
                active: 'is_active',
                attributes: ['password_hash' => 'password_hash'],
                allowCreate: true,
                allowUpdate: true,
                allowDelete: false,
            ),
            'factory' => new ProjectUserFactory($mainDb),
        ],
        'permissions' => [
            'definitions' => [
                'documents.view' => ['name' => 'View Documents', 'description' => 'Can view documents', 'default' => true],
                'documents.approve' => ['name' => 'Approve Documents', 'description' => 'Can approve documents', 'default' => false],
                'documents.delete' => ['name' => 'Delete Documents', 'description' => 'Can delete documents', 'default' => false],
            ],
        ],
    ],
]);

$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager(['database' => $logDb]); // local override: Logs only

/* Seed group permissions once so inheritance is visible immediately. */
$managerId = (int)$mainDb->query("SELECT id FROM app_groups WHERE name='Managers'")->fetchColumn();
$staffId = (int)$mainDb->query("SELECT id FROM app_groups WHERE name='Staff'")->fetchColumn();
if ($managerId && $permissions->overridesFor('group', $managerId) === []) {
    $permissions->set('group', $managerId, 'documents.approve', true);
    $permissions->set('group', $managerId, 'documents.delete', true);
}
if ($staffId && $permissions->overridesFor('group', $staffId) === []) {
    $permissions->set('group', $staffId, 'documents.approve', false);
}

$message = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'login') {
        $result = $auth->attempt(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
        $message = $result->success ? 'Signed in successfully.' : 'Sign-in failed.';
    } elseif ($action === 'logout') {
        $auth->logout();
        $message = 'Signed out.';
    } elseif ($action === 'create_user') {
        $users->create([
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'active' => true,
            'password_hash' => password_hash($_POST['password'] ?? 'password123', PASSWORD_DEFAULT),
        ]);
        $message = 'User created.';
    } elseif ($action === 'rename') {
        $users->update((int)$_POST['user_id'], ['name' => trim($_POST['name'] ?? '')]);
        $message = 'User updated.';
    } elseif ($action === 'create_group') {
        $name = trim($_POST['group_name'] ?? '');
        if ($name !== '') {
            $stmt = $mainDb->prepare('INSERT OR IGNORE INTO app_groups (name) VALUES (?)');
            $stmt->execute([$name]);
            $message = 'Group created.';
        }
    } elseif ($action === 'assign_group') {
        $stmt = $mainDb->prepare('INSERT OR IGNORE INTO app_user_groups (user_id, group_id) VALUES (?, ?)');
        $stmt->execute([(int)$_POST['user_id'], (int)$_POST['group_id']]);
        $message = 'User added to group.';
    } elseif ($action === 'remove_group') {
        $stmt = $mainDb->prepare('DELETE FROM app_user_groups WHERE user_id = ? AND group_id = ?');
        $stmt->execute([(int)$_POST['user_id'], (int)$_POST['group_id']]);
        $message = 'User removed from group.';
    } elseif (in_array($action, ['user_allow', 'user_deny', 'user_clear'], true)) {
        $userId = (int)$_POST['user_id'];
        $permission = (string)$_POST['permission'];
        if ($action === 'user_clear') $permissions->clear('user', $userId, $permission);
        else $permissions->set('user', $userId, $permission, $action === 'user_allow');
        $message = 'User permission override updated.';
    } elseif (in_array($action, ['group_allow', 'group_deny', 'group_clear'], true)) {
        $groupId = (int)$_POST['group_id'];
        $permission = (string)$_POST['permission'];
        if ($action === 'group_clear') $permissions->clear('group', $groupId, $permission);
        else $permissions->set('group', $groupId, $permission, $action === 'group_allow');
        $message = 'Group permission updated.';
    }
}

$current = $auth->user();
$allUsers = $users->all();
$groups = $mainDb->query('SELECT id, name FROM app_groups ORDER BY name')->fetchAll();
$groupNames = [];
foreach ($groups as $g) $groupNames[(int)$g['id']] = $g['name'];

$selectedId = (int)($_GET['user'] ?? ($current?->authId() ?? ($allUsers[0]->id ?? 1)));
$selected = $users->find($selectedId);
$resolved = $selected instanceof PermissionSubjectInterface ? $permissions->resolvedFor($selected) : [];
$selectedGroups = [];
if ($selected instanceof PermissionSubjectInterface) {
    foreach ($selected->permissionGroupIds() as $gid) {
        $selectedGroups[] = ['id' => $gid, 'name' => $groupNames[(int)$gid] ?? ('Group '.$gid)];
    }
}

$recentLogs = $logs->recent(100);
$mainTables = $mainDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
$logTables = $logDb->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);

function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function sourceLabel(object $r, array $groupNames): string {
    if ($r->source !== 'group') return ucfirst($r->source);
    $ids = $r->groups ?: $r->deniedGroups;
    $names = array_map(fn($id) => $groupNames[(int)$id] ?? ('Group #'.$id), $ids);
    return 'Inherited from '.implode(', ', $names);
}
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Prefab Database Integration</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-body-tertiary"><nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand">Tihloh Prefab — Users, Groups & Permission Inheritance</span></div></nav><main class="container py-4">
<?php if ($message): ?><div class="alert alert-info"><?=e($message)?></div><?php endif; ?>
<div class="alert alert-secondary small"><strong>main.sqlite:</strong> <?=e(implode(', ', $mainTables))?> &nbsp;|&nbsp; <strong>logs.sqlite:</strong> <?=e(implode(', ', $logTables))?></div>
<div class="row g-4">
<div class="col-lg-4">
<div class="card mb-3"><div class="card-header fw-semibold">Authentication</div><div class="card-body"><?php if($auth->check()):?><p>Signed in as <strong><?=e($current?->name??$auth->id())?></strong></p><form method="post"><button name="action" value="logout" class="btn btn-outline-danger w-100">Sign out</button></form><?php else:?><form method="post" class="vstack gap-2"><input class="form-control" name="email" value="demo@example.com"><input class="form-control" type="password" name="password" value="password123"><button name="action" value="login" class="btn btn-primary">Sign in</button></form><?php endif;?></div></div>
<div class="card mb-3"><div class="card-header fw-semibold">Create User</div><div class="card-body"><form method="post" class="vstack gap-2"><input class="form-control" name="name" placeholder="Full name" required><input class="form-control" type="email" name="email" placeholder="Email" required><input class="form-control" type="password" name="password" value="password123"><button name="action" value="create_user" class="btn btn-success">Create user</button></form></div></div>
<div class="card"><div class="card-header fw-semibold">Create Group</div><div class="card-body"><form method="post" class="input-group"><input class="form-control" name="group_name" placeholder="Group name" required><button name="action" value="create_group" class="btn btn-outline-primary">Add</button></form></div></div>
</div>
<div class="col-lg-8">
<div class="card mb-4"><div class="card-header fw-semibold">Users</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th></th></tr></thead><tbody><?php foreach($allUsers as $u):?><tr><td><?=e($u->id)?></td><td><?=e($u->name)?></td><td><?=e($u->email)?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="?user=<?=e($u->id)?>">Manage</a></td></tr><?php endforeach;?></tbody></table></div></div>

<?php if($selected):?>
<div class="card mb-4"><div class="card-header fw-semibold">Manage <?=e($selected->name)?></div><div class="card-body">
<form method="post" class="row g-2 mb-4"><input type="hidden" name="user_id" value="<?=e($selected->id)?>"><div class="col"><input class="form-control" name="name" value="<?=e($selected->name)?>"></div><div class="col-auto"><button name="action" value="rename" class="btn btn-outline-primary">Rename</button></div></form>
<h6>Groups</h6><div class="d-flex flex-wrap gap-2 mb-3"><?php if(!$selectedGroups):?><span class="text-muted">No groups</span><?php endif;?><?php foreach($selectedGroups as $g):?><form method="post"><input type="hidden" name="user_id" value="<?=e($selected->id)?>"><input type="hidden" name="group_id" value="<?=e($g['id'])?>"><button name="action" value="remove_group" class="btn btn-sm btn-secondary"><?=e($g['name'])?> ×</button></form><?php endforeach;?></div>
<form method="post" class="row g-2 mb-4"><input type="hidden" name="user_id" value="<?=e($selected->id)?>"><div class="col"><select class="form-select" name="group_id"><?php foreach($groups as $g):?><option value="<?=e($g['id'])?>"><?=e($g['name'])?></option><?php endforeach;?></select></div><div class="col-auto"><button name="action" value="assign_group" class="btn btn-outline-secondary">Add to group</button></div></form>
<h6>Effective permissions</h6><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Permission</th><th>Effective</th><th>Source / inheritance</th><th>User override</th></tr></thead><tbody><?php foreach($permissions->definitions() as $pid=>$def):$r=$resolved[$pid];?><tr><td><?=e($def['name']??$pid)?><br><code><?=e($pid)?></code></td><td><span class="badge text-bg-<?=$r->allowed?'success':'danger'?>"><?=$r->allowed?'ALLOW':'DENY'?></span></td><td><?=e(sourceLabel($r,$groupNames))?></td><td><form method="post" class="btn-group btn-group-sm"><input type="hidden" name="user_id" value="<?=e($selected->id)?>"><input type="hidden" name="permission" value="<?=e($pid)?>"><button name="action" value="user_allow" class="btn btn-outline-success">Allow</button><button name="action" value="user_deny" class="btn btn-outline-danger">Deny</button><button name="action" value="user_clear" class="btn btn-outline-secondary">Inherit</button></form></td></tr><?php endforeach;?></tbody></table></div>
</div></div>
<?php endif;?>

<div class="card mb-4"><div class="card-header fw-semibold">Group Permissions</div><div class="card-body"><p class="small text-muted">Set permissions on a group, then add a user to that group. Clear a user's override with <strong>Inherit</strong> to see the group value become effective.</p><?php foreach($groups as $g):$gp=$permissions->overridesFor('group',$g['id']);?><div class="border rounded p-3 mb-3"><h6><?=e($g['name'])?> <span class="text-muted">#<?=e($g['id'])?></span></h6><div class="table-responsive"><table class="table table-sm mb-0"><tbody><?php foreach($permissions->definitions() as $pid=>$def):?><tr><td><?=e($def['name']??$pid)?></td><td><?php if(array_key_exists($pid,$gp)):?><span class="badge text-bg-<?=$gp[$pid]?'success':'danger'?>"><?=$gp[$pid]?'ALLOW':'DENY'?></span><?php else:?><span class="badge text-bg-secondary">INHERIT DEFAULT</span><?php endif;?></td><td class="text-end"><form method="post" class="btn-group btn-group-sm"><input type="hidden" name="group_id" value="<?=e($g['id'])?>"><input type="hidden" name="permission" value="<?=e($pid)?>"><button name="action" value="group_allow" class="btn btn-outline-success">Allow</button><button name="action" value="group_deny" class="btn btn-outline-danger">Deny</button><button name="action" value="group_clear" class="btn btn-outline-secondary">Clear</button></form></td></tr><?php endforeach;?></tbody></table></div></div><?php endforeach;?></div></div>

<div class="card"><div class="card-header fw-semibold">Activity stored in logs.sqlite <span class="badge text-bg-secondary"><?=count($recentLogs)?></span></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>#</th><th>Action</th><th>Actor</th><th>Subject</th><th>Message</th></tr></thead><tbody><?php foreach($recentLogs as $log):?><tr><td><?=e($log['id'])?></td><td><code><?=e($log['action'])?></code></td><td><?=e($log['actor_id']??'—')?></td><td><?=e($log['subject_type'])?> #<?=e($log['subject_id']??'—')?></td><td><?=e($log['message']??'')?></td></tr><?php endforeach;?></tbody></table></div></div>
</div></div></main></body></html>
