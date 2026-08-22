<?php
require __DIR__.'/vendor/autoload.php';
use Tihloh\Prefab\Users\User\PrefabUser;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
class ProjectUser extends PrefabUser implements PermissionSubjectInterface {
    public function __construct(int $id,string $name,string $email,private array $groups=[]) { parent::__construct($id,$name,$email,true); }
    public function permissionSubjectId(): int|string { return $this->id; }
    public function permissionGroupIds(): array { return $this->groups; }
}
$store=new class implements PermissionStoreInterface { private array $d=[]; public function get(string $t,int|string $i):array{return $this->d[$t][(string)$i]??[];} public function put(string $t,int|string $i,array $p):void{$this->d[$t][(string)$i]=$p;} public function remove(string $t,int|string $i):void{unset($this->d[$t][(string)$i]);} };
$permissions=new PermissionManager(new PermissionDefinitions(['documents.view'=>['name'=>'View Documents','default'=>true],'documents.approve'=>['name'=>'Approve Documents','default'=>false]]),$store);
$permissions->set('group',10,'documents.approve',true);
$user=new ProjectUser(25,'Alice','alice@example.com',[10]);
$resolved=$permissions->resolvedFor($user);
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Users + Permissions Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-body-tertiary"><nav class="navbar bg-dark navbar-dark"><div class="container"><span class="navbar-brand">Prefab Users + Permissions Test</span></div></nav><main class="container py-4"><div class="row g-3"><div class="col-md-4"><div class="card shadow-sm"><div class="card-header fw-semibold">User</div><div class="card-body"><div class="h5"><?=e($user->name)?></div><div><?=e($user->email)?></div><div class="mt-2">Groups: <span class="badge text-bg-secondary">10</span></div></div></div></div><div class="col-md-8"><div class="card shadow-sm"><div class="card-header fw-semibold">Effective permissions</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Permission</th><th>Result</th><th>Source</th></tr></thead><tbody><?php foreach($resolved as $id=>$r):?><tr><td><code><?=e($id)?></code></td><td><span class="badge text-bg-<?=$r->allowed?'success':'danger'?>"><?=$r->allowed?'ALLOW':'DENY'?></span></td><td><?=e($r->source)?></td></tr><?php endforeach;?></tbody></table></div></div></div></div></main></body></html>