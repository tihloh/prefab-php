<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\Services\UserManager;
use Tihloh\Prefab\Users\User\PrefabUser;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;
use Tihloh\Prefab\Logs\Services\LogManager;

/*
 * OPTIONAL COMMON CONFIGURATION
 * Declare shared resources BEFORE the modules. Every module remains standalone.
 * A module-specific constructor/config value overrides the shared value.
 *
 * PrefabConfig::set([
 *     'database' => $mainPdo,
 *     'modules' => [
 *         'logs' => ['database' => $logPdo], // optional separate DB
 *     ],
 * ]);
 *
 * If nothing is declared here, each module uses its own internal defaults and
 * automatically integrates with compatible Prefab modules that are declared.
 */

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['combined_users'] ??= [1=>['id'=>1,'name'=>'Demo User','email'=>'demo@example.com','active'=>true,'hash'=>password_hash('password123',PASSWORD_DEFAULT),'groups'=>[10]]];
$_SESSION['combined_permissions'] ??= [];
$_SESSION['combined_logs'] ??= [];

class ProjectUser extends PrefabUser implements AuthenticatableUserInterface, PermissionSubjectInterface {
    public function __construct(int|string $id, ?string $name, ?string $email, bool $active, private string $passwordHash, private array $groupIds=[]) { parent::__construct($id,$name,$email,$active); }
    public function authId(): int|string { return $this->id; }
    public function authPasswordHash(): ?string { return $this->passwordHash; }
    public function authIsActive(): bool { return $this->active; }
    public function permissionSubjectId(): int|string { return $this->id; }
    public function permissionGroupIds(): array { return $this->groupIds; }
}

$userProvider = new class implements UserProviderInterface {
    private function make(array $r): ProjectUser { return new ProjectUser($r['id'],$r['name'],$r['email'],$r['active'],$r['hash'],$r['groups']??[]); }
    public function find(int|string $id): ?PrefabUser { $r=$_SESSION['combined_users'][(int)$id]??null; return $r?$this->make($r):null; }
    public function findByEmail(string $email): ?PrefabUser { foreach($_SESSION['combined_users'] as $r) if(strcasecmp($r['email'],$email)===0) return $this->make($r); return null; }
    public function all(int $limit=100,int $offset=0): array { return array_map(fn($r)=>$this->make($r),array_slice(array_values($_SESSION['combined_users']),$offset,$limit)); }
    public function create(array $data): PrefabUser { $id=$_SESSION['combined_users']?max(array_keys($_SESSION['combined_users']))+1:1; $_SESSION['combined_users'][$id]=['id'=>$id,'name'=>$data['name']??'User '.$id,'email'=>$data['email']??"user{$id}@example.com",'active'=>$data['active']??true,'hash'=>password_hash($data['password']??'password123',PASSWORD_DEFAULT),'groups'=>$data['groups']??[]]; return $this->make($_SESSION['combined_users'][$id]); }
    public function update(int|string $id,array $data): PrefabUser { $i=(int)$id; $_SESSION['combined_users'][$i]=array_merge($_SESSION['combined_users'][$i],$data); return $this->make($_SESSION['combined_users'][$i]); }
    public function delete(int|string $id): bool { $i=(int)$id; if(!isset($_SESSION['combined_users'][$i])) return false; unset($_SESSION['combined_users'][$i]); return true; }
};

$permissionStore=new class implements PermissionStoreInterface {
    public function get(string $t,int|string $i):array{return $_SESSION['combined_permissions'][$t][(string)$i]??[];}
    public function put(string $t,int|string $i,array $p):void{$_SESSION['combined_permissions'][$t][(string)$i]=$p;}
    public function remove(string $t,int|string $i):void{unset($_SESSION['combined_permissions'][$t][(string)$i]);}
};

$logRepo=new class implements LogRepositoryInterface {
    public function record(LogEntry $e):int|string{$id=count($_SESSION['combined_logs'])+1;$_SESSION['combined_logs'][$id]=['id'=>$id]+$e->toArray();return $id;}
    public function find(int|string $id):?array{return $_SESSION['combined_logs'][(int)$id]??null;}
    public function recent(int $limit=100,int $offset=0):array{return array_slice(array_values(array_reverse($_SESSION['combined_logs'],true)),$offset,$limit);}
    public function forSubject(string $t,int|string $id,int $limit=100):array{return array_values(array_filter($_SESSION['combined_logs'],fn($r)=>$r['subject_type']===$t&&(string)$r['subject_id']===(string)$id));}
    public function forActor(int|string $id,int $limit=100):array{return array_values(array_filter($_SESSION['combined_logs'],fn($r)=>(string)($r['actor_id']??'')===(string)$id));}
};

/*
 * MODULE DECLARATIONS
 * No Core object and no register() chain.
 *
 * Users is explicitly given a session-backed provider for this demo.
 * Auth has no user provider: it automatically inherits the compatible Users module.
 * Permissions/Logs use custom session stores only because this demo has no database.
 * The last declaration completes the automatic configuration pass; normal feature
 * calls below use already-resolved references and do not rediscover modules.
 */
$users = new UserManager($userProvider);
$auth = new AuthManager(['session' => new NativeSessionStore('combined_test_user')]);
$permissions = new PermissionManager(new PermissionDefinitions([
    'documents.view'=>['name'=>'View Documents','description'=>'Can view documents','default'=>true],
    'documents.approve'=>['name'=>'Approve Documents','description'=>'Can approve documents','default'=>false],
]), $permissionStore);
$logs = new LogManager($logRepo);

/*
 * Normal database-backed project can be much shorter:
 *
 * PrefabConfig::set(['database' => $pdo]);
 * $users = new UserManager();
 * $auth = new AuthManager();
 * $permissions = new PermissionManager(['definitions' => $definitions]);
 * $logs = new LogManager();
 *
 * Override only an exception:
 * $logs = new LogManager(['database' => $separateLogPdo]);
 */

$flash=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action=$_POST['action']??'';
    if($action==='create_user'){
        $users->create(['name'=>trim($_POST['name']??''),'email'=>trim($_POST['email']??''),'password'=>$_POST['password']??'password123']); $flash='User created.';
    } elseif($action==='rename_user'){
        $users->update((int)$_POST['user_id'],['name'=>trim($_POST['name']??'')]); $flash='User updated.';
    } elseif($action==='delete_user'){
        $users->delete((int)$_POST['user_id']); $flash='User deleted.';
    } elseif($action==='login'){
        $r=$auth->attempt(trim($_POST['email']??''),$_POST['password']??''); $flash=$r->success?'Logged in.':'Login failed.';
    } elseif($action==='logout'){
        $auth->logout(); $flash='Logged out.';
    } elseif(in_array($action,['allow','deny','clear'],true)){
        $uid=(int)$_POST['user_id']; $perm=(string)$_POST['permission'];
        $action==='clear'?$permissions->clear('user',$uid,$perm):$permissions->set('user',$uid,$perm,$action==='allow');
        $flash='Permission updated.';
    } elseif($action==='reset'){
        foreach(['combined_users','combined_permissions','combined_logs','combined_test_user'] as $k) unset($_SESSION[$k]); header('Location: '.$_SERVER['PHP_SELF']); exit;
    }
}
$current=$auth->user();
$selectedId=(int)($_GET['user']??($current?->authId()??1));
$selected=$users->find($selectedId);
$resolved=$selected instanceof PermissionSubjectInterface?$permissions->resolvedFor($selected):[];
$recentLogs=$logs->recent();
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Interactive Combined Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-body-tertiary">
<nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand">Tihloh Prefab — Interactive Combined Test</span><form method="post"><input type="hidden" name="action" value="reset"><button class="btn btn-sm btn-outline-light">Reset</button></form></div></nav>
<main class="container py-4"><?php if($flash):?><div class="alert alert-info"><?=e($flash)?></div><?php endif;?>
<div class="row g-4"><div class="col-lg-4">
<div class="card mb-3"><div class="card-header fw-semibold">Auth</div><div class="card-body"><?php if($auth->check()):?><p class="mb-2">Signed in as <strong><?=e($current?->name??$auth->id())?></strong></p><form method="post"><input type="hidden" name="action" value="logout"><button class="btn btn-outline-danger w-100">Logout</button></form><?php else:?><form method="post" class="vstack gap-2"><input type="hidden" name="action" value="login"><input class="form-control" name="email" value="demo@example.com"><input class="form-control" type="password" name="password" value="password123"><button class="btn btn-primary">Login</button></form><?php endif;?></div></div>
<div class="card mb-3"><div class="card-header fw-semibold">Create User</div><div class="card-body"><form method="post" class="vstack gap-2"><input type="hidden" name="action" value="create_user"><input class="form-control" name="name" placeholder="Name" required><input class="form-control" type="email" name="email" placeholder="Email" required><input class="form-control" name="password" value="password123"><button class="btn btn-success">Create</button></form></div></div>
</div><div class="col-lg-8">
<div class="card mb-4"><div class="card-header fw-semibold">Users</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th></th></tr></thead><tbody><?php foreach($users->all() as $u):?><tr><td><?=e($u->id)?></td><td><?=e($u->name)?></td><td><?=e($u->email)?></td><td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="?user=<?=e($u->id)?>">Manage</a></td></tr><?php endforeach;?></tbody></table></div></div>
<?php if($selected):?><div class="card mb-4"><div class="card-header fw-semibold">Manage <?=e($selected->name)?></div><div class="card-body"><form method="post" class="row g-2 mb-3"><input type="hidden" name="action" value="rename_user"><input type="hidden" name="user_id" value="<?=e($selected->id)?>"><div class="col"><input class="form-control" name="name" value="<?=e($selected->name)?>"></div><div class="col-auto"><button class="btn btn-outline-primary">Rename</button></div></form><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Permission</th><th>Effective</th><th>Source</th><th>Override</th></tr></thead><tbody><?php foreach($permissions->definitions() as $pid=>$def): $r=$resolved[$pid];?><tr><td><code><?=e($pid)?></code></td><td><span class="badge text-bg-<?=$r->allowed?'success':'danger'?>"><?=$r->allowed?'ALLOW':'DENY'?></span></td><td><?=e($r->source)?></td><td><form method="post" class="btn-group btn-group-sm"><input type="hidden" name="user_id" value="<?=e($selected->id)?>"><input type="hidden" name="permission" value="<?=e($pid)?>"><button name="action" value="allow" class="btn btn-outline-success">Allow</button><button name="action" value="deny" class="btn btn-outline-danger">Deny</button><button name="action" value="clear" class="btn btn-outline-secondary">Clear</button></form></td></tr><?php endforeach;?></tbody></table></div><form method="post" onsubmit="return confirm('Delete this user?')"><input type="hidden" name="action" value="delete_user"><input type="hidden" name="user_id" value="<?=e($selected->id)?>"><button class="btn btn-sm btn-danger">Delete User</button></form></div></div><?php endif;?>
<div class="card"><div class="card-header fw-semibold">Activity Logs <span class="badge text-bg-secondary"><?=count($recentLogs)?></span></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>#</th><th>Action</th><th>Actor</th><th>Subject</th><th>Message</th></tr></thead><tbody><?php foreach($recentLogs as $row):?><tr><td><?=e($row['id'])?></td><td><code><?=e($row['action'])?></code></td><td><?=e(($row['actor_type']??'user').' #'.($row['actor_id']??'—'))?></td><td><?=e($row['subject_type'])?> #<?=e($row['subject_id'])?></td><td><?=e($row['message']??'')?></td></tr><?php endforeach;?></tbody></table></div></div>
</div></div></main></body></html>