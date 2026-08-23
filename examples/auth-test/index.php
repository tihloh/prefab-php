<?php
require __DIR__.'/vendor/autoload.php';
use Tihloh\Prefab\Core\Prefab;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;
$user=new class implements AuthenticatableUserInterface{
 public function authId():int|string{return 1;}
 public function authPasswordHash():?string{return password_hash('password123',PASSWORD_DEFAULT);}
 public function authIsActive():bool{return true;}
};
$provider=new class($user) implements AuthUserProviderInterface{
 public function __construct(private AuthenticatableUserInterface $user){}
 public function findByIdentifier(string $identifier):?AuthenticatableUserInterface{return $identifier==='demo@example.com'?$this->user:null;}
 public function findById(int|string $id):?AuthenticatableUserInterface{return (string)$id==='1'?$this->user:null;}
};
$authManager=new AuthManager($provider,new NativeSessionStore('auth_test_user'));

/*
 * Core always discovers/integrates available modules. This standalone Auth
 * test has no Users module/default user source, so Auth itself is explicitly
 * initialized with the test provider. If Users were already initialized and
 * supplied a compatible auth user source, Auth could be derived automatically.
 */
$prefab=Prefab::create(['modules'=>['auth'=>$authManager]]);
$auth=$prefab->auth();

/*
 * Typical project with defaults:
 * $prefab=Prefab::create(['db'=>$pdo]);
 * $auth=$prefab->auth();
 *
 * Optional custom Auth only when Prefab cannot infer your user/session source:
 * $prefab=Prefab::create(['modules'=>['auth'=>$customAuth]]);
 */

$message=null;$lastLog=null;
if($_SERVER['REQUEST_METHOD']==='POST'){$a=$_POST['action']??'';if($a==='login'){$r=$auth->attempt(trim($_POST['email']??''),$_POST['password']??'');$message=$r->success?'Login successful':'Login failed';$lastLog=$r->log;}elseif($a==='logout'){$r=$auth->logout();$message='Logged out';$lastLog=$r->log;}}
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Auth Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-body-tertiary"><main class="container py-5" style="max-width:720px"><h1 class="h3 mb-4">Auth Interactive Test</h1><?php if($message):?><div class="alert alert-info"><?=e($message)?></div><?php endif;?><div class="row g-4"><div class="col-md-6"><div class="card"><div class="card-header">Session</div><div class="card-body"><p>Status: <span class="badge text-bg-<?=$auth->check()?'success':'secondary'?>"><?=$auth->check()?'Authenticated':'Guest'?></span></p><p>User ID: <?=e($auth->id()??'—')?></p><?php if($auth->check()):?><form method="post"><button name="action" value="logout" class="btn btn-outline-danger w-100">Logout</button></form><?php else:?><form method="post" class="vstack gap-2"><input type="hidden" name="action" value="login"><input class="form-control" name="email" value="demo@example.com"><input class="form-control" type="password" name="password" value="password123"><button class="btn btn-primary">Login</button></form><?php endif;?></div></div></div><div class="col-md-6"><div class="card"><div class="card-header">Last Auth Log</div><div class="card-body"><pre class="small mb-0"><?=e($lastLog?json_encode($lastLog,JSON_PRETTY_PRINT):'No action yet')?></pre></div></div></div></div></main></body></html>