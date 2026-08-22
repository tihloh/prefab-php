<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\Services\UserManager;
use Tihloh\Prefab\Users\User\PrefabUser;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;
use Tihloh\Prefab\Logs\Services\LogManager;

class ProjectUser extends PrefabUser implements AuthenticatableUserInterface, PermissionSubjectInterface
{
    public function __construct(int|string $id, ?string $name, ?string $email, bool $active, private string $passwordHash, private array $groupIds = []) { parent::__construct($id,$name,$email,$active); }
    public function authId(): int|string { return $this->id; }
    public function authPasswordHash(): ?string { return $this->passwordHash; }
    public function authIsActive(): bool { return $this->active; }
    public function permissionSubjectId(): int|string { return $this->id; }
    public function permissionGroupIds(): array { return $this->groupIds; }
}

$userProvider = new class implements UserProviderInterface {
    private array $rows=[];
    public function __construct() { $this->rows[1]=['id'=>1,'name'=>'Demo User','email'=>'demo@example.com','active'=>true,'hash'=>password_hash('password123',PASSWORD_DEFAULT),'groups'=>[10]]; }
    private function make(array $r): ProjectUser { return new ProjectUser($r['id'],$r['name'],$r['email'],$r['active'],$r['hash'],$r['groups']); }
    public function find(int|string $id): ?PrefabUser { return isset($this->rows[(int)$id])?$this->make($this->rows[(int)$id]):null; }
    public function findByEmail(string $email): ?PrefabUser { foreach($this->rows as $r) if(strcasecmp($r['email'],$email)===0) return $this->make($r); return null; }
    public function all(int $limit=100,int $offset=0): array { return array_map(fn($r)=>$this->make($r),array_slice(array_values($this->rows),$offset,$limit)); }
    public function create(array $data): PrefabUser { $id=max(array_keys($this->rows))+1; $this->rows[$id]=['id'=>$id,'name'=>$data['name']??null,'email'=>$data['email']??null,'active'=>$data['active']??true,'hash'=>password_hash($data['password']??bin2hex(random_bytes(8)),PASSWORD_DEFAULT),'groups'=>$data['groups']??[]]; return $this->make($this->rows[$id]); }
    public function update(int|string $id,array $data): PrefabUser { $i=(int)$id; $this->rows[$i]=array_merge($this->rows[$i],$data); return $this->make($this->rows[$i]); }
    public function delete(int|string $id): bool { $i=(int)$id; if(!isset($this->rows[$i])) return false; unset($this->rows[$i]); return true; }
};
$users = new UserManager($userProvider);
$authProvider = new class($users) implements AuthUserProviderInterface {
    public function __construct(private UserManager $users) {}
    public function findByIdentifier(string $identifier): ?AuthenticatableUserInterface { $u=$this->users->findByEmail($identifier); return $u instanceof AuthenticatableUserInterface?$u:null; }
    public function findById(int|string $id): ?AuthenticatableUserInterface { $u=$this->users->find($id); return $u instanceof AuthenticatableUserInterface?$u:null; }
};
$auth = new AuthManager($authProvider,new NativeSessionStore('combined_test_user'));
$permissionStore = new class implements PermissionStoreInterface {
    private array $data=[];
    public function get(string $type,int|string $id): array { return $this->data[$type][(string)$id]??[]; }
    public function put(string $type,int|string $id,array $permissions): void { $this->data[$type][(string)$id]=$permissions; }
    public function remove(string $type,int|string $id): void { unset($this->data[$type][(string)$id]); }
};
$permissions = new PermissionManager(new PermissionDefinitions([
    'documents.view'=>['name'=>'View Documents','description'=>'Can view documents','default'=>true],
    'documents.approve'=>['name'=>'Approve Documents','description'=>'Can approve documents','default'=>false],
]),$permissionStore);
$logRepo = new class implements LogRepositoryInterface {
    private array $rows=[]; private int $next=1;
    public function record(LogEntry $entry): int|string { $id=$this->next++; $this->rows[$id]=['id'=>$id]+$entry->toArray(); return $id; }
    public function find(int|string $id): ?array { return $this->rows[(int)$id]??null; }
    public function recent(int $limit=100,int $offset=0): array { return array_slice(array_values($this->rows),$offset,$limit); }
    public function forSubject(string $type,int|string $id,int $limit=100): array { return array_values(array_filter($this->rows,fn($r)=>$r['subject_type']===$type&&(string)$r['subject_id']===(string)$id)); }
    public function forActor(int|string $id,int $limit=100): array { return array_values(array_filter($this->rows,fn($r)=>(string)($r['actor_id']??'')===(string)$id)); }
};
$logs = new LogManager($logRepo);

$userUpdate = $users->update(1,['name'=>'Demo User Updated'],['actor_id'=>1]);
$logs->record($userUpdate->log);
$login = $auth->attempt('demo@example.com','password123');
if ($login->log) $logs->record($login->log);
$grant = $permissions->set('user',1,'documents.approve',true,['actor_id'=>1]);
$logs->record($grant->log);
$user = $users->find(1);
$canView = $user instanceof PermissionSubjectInterface ? $permissions->can($user,'documents.view') : false;
$canApprove = $user instanceof PermissionSubjectInterface ? $permissions->can($user,'documents.approve') : false;
$resolved = $user instanceof PermissionSubjectInterface ? $permissions->resolvedFor($user) : [];
$recentLogs = $logs->recent();
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Prefab Combined Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-body-tertiary"><nav class="navbar navbar-dark bg-dark"><div class="container"><span class="navbar-brand">Tihloh Prefab — Combined Test</span></div></nav><main class="container py-4">
<div class="row g-3 mb-4"><div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">User</div><div class="fw-semibold"><?= e($user?->name) ?></div><div class="small"><?= e($user?->email) ?></div></div></div></div><div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Authenticated</div><div class="h3 mb-0 text-<?= $auth->check()?'success':'danger' ?>"><?= $auth->check()?'YES':'NO' ?></div></div></div></div><div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Can approve</div><div class="h3 mb-0 text-<?= $canApprove?'success':'danger' ?>"><?= $canApprove?'YES':'NO' ?></div></div></div></div><div class="col-md-3"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Logs</div><div class="h3 mb-0"><?= count($recentLogs) ?></div></div></div></div></div>
<div class="row g-4"><div class="col-lg-5"><div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Permissions</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Permission</th><th>Effective</th><th>Source</th></tr></thead><tbody><?php foreach($resolved as $id=>$r): ?><tr><td><code><?= e($id) ?></code></td><td><span class="badge text-bg-<?= $r->allowed?'success':'danger' ?>"><?= $r->allowed?'ALLOW':'DENY' ?></span></td><td><?= e($r->source) ?></td></tr><?php endforeach; ?></tbody></table></div></div></div><div class="col-lg-7"><div class="card shadow-sm"><div class="card-header fw-semibold">Activity Logs</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>#</th><th>Action</th><th>Message</th><th>Changes</th></tr></thead><tbody><?php foreach($recentLogs as $row): ?><tr><td><?= e($row['id']) ?></td><td><code><?= e($row['action']) ?></code></td><td><?= e($row['message']??'') ?></td><td><small><code><?= e(json_encode($row['changes']??[])) ?></code></small></td></tr><?php endforeach; ?></tbody></table></div></div></div></div>
</main></body></html>