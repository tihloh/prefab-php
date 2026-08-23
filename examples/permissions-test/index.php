<?php
require __DIR__.'/vendor/autoload.php';
use Tihloh\Prefab\Core\Prefab;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$_SESSION['permissions_test']??=[];
$store=new class implements PermissionStoreInterface{
 public function get(string $t,int|string $i):array{return $_SESSION['permissions_test'][$t][(string)$i]??[];}
 public function put(string $t,int|string $i,array $p):void{$_SESSION['permissions_test'][$t][(string)$i]=$p;}
 public function remove(string $t,int|string $i):void{unset($_SESSION['permissions_test'][$t][(string)$i]);}
};
$permissionsManager=new PermissionManager(new PermissionDefinitions([
 'documents.view'=>['name'=>'View Documents','description'=>'View documents','default'=>true],
 'documents.approve'=>['name'=>'Approve Documents','description'=>'Approve documents','default'=>false],
]),$store);

/*
 * Discovery/integration is always automatic. This standalone example uses a
 * custom session permission store, so only Permissions needs explicit init.
 * Any other available Prefab modules remain automatically discoverable.
 */
$prefab=Prefab::create(['modules'=>['permissions'=>$permissionsManager]]);
$permissions=$prefab->permissions();

/*
 * Typical database project when Prefab has a default store:
 * $prefab=Prefab::create(['db'=>$pdo]);
 * $permissions=$prefab->permissions();
 *
 * Optional custom factory:
 * $prefab=Prefab::create(['module_options'=>[
 *   'permissions'=>['factory'=>fn($prefab,$options)=>new PermissionManager($definitions,$customStore)]
 * ]]);
 */

$uid=(int)($_GET['user']??25);
if($_SERVER['REQUEST_METHOD']==='POST'){$a=$_POST['action']??'';$perm=$_POST['permission']??'';if($a==='allow')$permissions->set('user',$uid,$perm,true);elseif($a==='deny')$permissions->set('user',$uid,$perm,false);elseif($a==='clear')$permissions->clear('user',$uid,$perm);elseif($a==='reset'){unset($_SESSION['permissions_test']);header('Location:'.$_SERVER['PHP_SELF']);exit;}}
$resolved=$permissions->resolvedFor($uid,[]);
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Permissions Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-body-tertiary"><main class="container py-4"><div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Permissions Interactive Test</h1><form method="post"><button name="action" value="reset" class="btn btn-outline-secondary btn-sm">Reset</button></form></div><div class="card"><div class="card-header">User #<?=e($uid)?></div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Permission</th><th>Description</th><th>Effective</th><th>Source</th><th>Override</th></tr></thead><tbody><?php foreach($permissions->definitions() as $id=>$def):$r=$resolved[$id];?><tr><td><code><?=e($id)?></code></td><td><?=e($def['description']??'')?></td><td><span class="badge text-bg-<?=$r->allowed?'success':'danger'?>"><?=$r->allowed?'ALLOW':'DENY'?></span></td><td><?=e($r->source)?></td><td><form method="post" class="btn-group btn-group-sm"><input type="hidden" name="permission" value="<?=e($id)?>"><button name="action" value="allow" class="btn btn-outline-success">Allow</button><button name="action" value="deny" class="btn btn-outline-danger">Deny</button><button name="action" value="clear" class="btn btn-outline-secondary">Clear</button></form></td></tr><?php endforeach;?></tbody></table></div></div></main></body></html>