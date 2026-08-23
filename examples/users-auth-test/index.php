<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\Services\UserManager;
use Tihloh\Prefab\Users\User\PrefabUser;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;

/*
 * OPTIONAL COMMON CONFIGURATION
 * PrefabConfig::set(['database'=>$pdo]);
 *
 * Users and Auth are separate standalone modules. Because Auth below has no
 * provider configured, it automatically uses the compatible Prefab Users module.
 */

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$_SESSION['ua_user']??=['id'=>1,'name'=>'Demo User','email'=>'demo@example.com','active'=>true,'hash'=>password_hash('password123',PASSWORD_DEFAULT)];
class ProjectUser extends PrefabUser implements AuthenticatableUserInterface{public function __construct(int|string $id,?string $name,?string $email,bool $active,private string $hash){parent::__construct($id,$name,$email,$active);}public function authId():int|string{return $this->id;}public function authPasswordHash():?string{return $this->hash;}public function authIsActive():bool{return $this->active;}}
$provider=new class implements UserProviderInterface{
 private function make():ProjectUser{$r=$_SESSION['ua_user'];return new ProjectUser($r['id'],$r['name'],$r['email'],$r['active'],$r['hash']);}
 public function find(int|string $id):?PrefabUser{$u=$this->make();return (string)$id===(string)$u->id?$u:null;}
 public function findByEmail(string $email):?PrefabUser{$u=$this->make();return strcasecmp($email,$u->email??'')===0?$u:null;}
 public function all(int $limit=100,int $offset=0):array{return [$this->make()];}
 public function create(array $data):PrefabUser{throw new RuntimeException('Not used in this test');}
 public function update(int|string $id,array $data):PrefabUser{$_SESSION['ua_user']=array_merge($_SESSION['ua_user'],$data);return $this->make();}
 public function delete(int|string $id):bool{return false;}
};

$users=new UserManager($provider);
$auth=new AuthManager(['session'=>new NativeSessionStore('users_auth_test')]);

/*
 * The important part is that Auth has NO provider argument. On declaration it
 * sees Prefab Users and resolves a direct adapter once. Feature calls do not
 * repeat discovery.
 *
 * To override only Auth:
 * $auth = new AuthManager($customAuthProvider, $customSession);
 */

$msg=null;if($_SERVER['REQUEST_METHOD']==='POST'){$a=$_POST['action']??'';if($a==='login'){$r=$auth->attempt($_POST['email']??'',$_POST['password']??'');$msg=$r->success?'Login successful':'Login failed';}elseif($a==='logout'){$auth->logout();$msg='Logged out';}elseif($a==='rename'){$users->update(1,['name'=>trim($_POST['name'])]);$msg='User renamed';}}
$user=$users->find(1);function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Users + Auth</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-body-tertiary"><main class="container py-4"><h1 class="h3 mb-4">Users + Auth Interactive Test</h1><?php if($msg):?><div class="alert alert-info"><?=e($msg)?></div><?php endif;?><div class="row g-4"><div class="col-md-6"><div class="card"><div class="card-header">User</div><div class="card-body"><form method="post" class="vstack gap-2"><input type="hidden" name="action" value="rename"><input class="form-control" name="name" value="<?=e($user?->name)?>"><input class="form-control" value="<?=e($user?->email)?>" disabled><button class="btn btn-outline-primary">Rename</button></form></div></div></div><div class="col-md-6"><div class="card"><div class="card-header">Auth</div><div class="card-body"><p>Status: <span class="badge text-bg-<?=$auth->check()?'success':'secondary'?>"><?=$auth->check()?'Authenticated':'Guest'?></span></p><?php if($auth->check()):?><form method="post"><button name="action" value="logout" class="btn btn-outline-danger w-100">Logout</button></form><?php else:?><form method="post" class="vstack gap-2"><input type="hidden" name="action" value="login"><input class="form-control" name="email" value="demo@example.com"><input class="form-control" type="password" name="password" value="password123"><button class="btn btn-primary">Login</button></form><?php endif;?></div></div></div></div></main></body></html>