<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\Services\UserManager;
use Tihloh\Prefab\Users\User\PrefabUser;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;

/* OPTIONAL COMMON CONFIGURATION
 * PrefabConfig::set(['database'=>$pdo]);
 * Permissions can inherit the shared DB, or a compatible Users DB when its own
 * store/database is not configured. Explicit Permissions config always wins.
 */

if(session_status()!==PHP_SESSION_ACTIVE)session_start();$_SESSION['up_perms']??=[];
class ProjectUser extends PrefabUser implements PermissionSubjectInterface{public function __construct(int $id,string $name,string $email,private array $groups=[]){parent::__construct($id,$name,$email,true);}public function permissionSubjectId():int|string{return $this->id;}public function permissionGroupIds():array{return $this->groups;}}
$userProvider=new class implements UserProviderInterface{
 private function make():ProjectUser{return new ProjectUser(25,'Alice','alice@example.com',[10]);}
 public function find(int|string $id):?PrefabUser{return (int)$id===25?$this->make():null;}
 public function findByEmail(string $email):?PrefabUser{$u=$this->make();return strcasecmp($email,$u->email??'')===0?$u:null;}
 public function all(int $limit=100,int $offset=0):array{return [$this->make()];}
 public function create(array $data):PrefabUser{throw new RuntimeException('Not used');}
 public function update(int|string $id,array $data):PrefabUser{return $this->make();}
 public function delete(int|string $id):bool{return false;}
};
$store=new class implements PermissionStoreInterface{public function get(string $t,int|string $i):array{return $_SESSION['up_perms'][$t][(string)$i]??[];}public function put(string $t,int|string $i,array $p):void{$_SESSION['up_perms'][$t][(string)$i]=$p;}public function remove(string $t,int|string $i):void{unset($_SESSION['up_perms'][$t][(string)$i]);}};

$users=new UserManager($userProvider);
$permissions=new PermissionManager(new PermissionDefinitions([
 'documents.view'=>['name'=>'View Documents','default'=>true],
 'documents.approve'=>['name'=>'Approve Documents','default'=>false]
]),$store);
$permissions->set('group',10,'documents.approve',true);

/* Database-backed alternative:
 * PrefabConfig::set(['database'=>$pdo]);
 * $users = new UserManager();
 * $permissions = new PermissionManager(['definitions'=>$definitions]);
 */

$user=$users->find(25);
if($_SERVER['REQUEST_METHOD']==='POST'){$a=$_POST['action']??'';$p=$_POST['permission']??'';if($a==='allow')$permissions->set('user',25,$p,true);elseif($a==='deny')$permissions->set('user',25,$p,false);elseif($a==='clear')$permissions->clear('user',25,$p);}
$resolved=$permissions->resolvedFor($user);function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Users + Permissions</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-body-tertiary"><main class="container py-4"><h1 class="h3 mb-4">Users + Permissions Interactive Test</h1><div class="row g-4"><div class="col-md-4"><div class="card"><div class="card-header">User</div><div class="card-body"><h5><?=e($user?->name)?></h5><div><?=e($user?->email)?></div><div class="mt-2">Group <span class="badge text-bg-secondary">10</span></div></div></div></div><div class="col-md-8"><div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Permission</th><th>Effective</th><th>Source</th><th>Override</th></tr></thead><tbody><?php foreach($resolved as $id=>$r):?><tr><td><code><?=e($id)?></code></td><td><span class="badge text-bg-<?=$r->allowed?'success':'danger'?>"><?=$r->allowed?'ALLOW':'DENY'?></span></td><td><?=e($r->source)?></td><td><form method="post" class="btn-group btn-group-sm"><input type="hidden" name="permission" value="<?=e($id)?>"><button name="action" value="allow" class="btn btn-outline-success">Allow</button><button name="action" value="deny" class="btn btn-outline-danger">Deny</button><button name="action" value="clear" class="btn btn-outline-secondary">Clear</button></form></td></tr><?php endforeach;?></tbody></table></div></div></div></div></main></body></html>