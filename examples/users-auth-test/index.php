<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\Users\User\PrefabUser;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;

class ProjectUser extends PrefabUser implements AuthenticatableUserInterface {
    public function __construct(int|string $id,?string $name,?string $email,bool $active,private string $hash) { parent::__construct($id,$name,$email,$active); }
    public function authId(): int|string { return $this->id; }
    public function authPasswordHash(): ?string { return $this->hash; }
    public function authIsActive(): bool { return $this->active; }
}
$user = new ProjectUser(1,'Demo User','demo@example.com',true,password_hash('password123',PASSWORD_DEFAULT));
$provider = new class($user) implements AuthUserProviderInterface {
    public function __construct(private ProjectUser $user) {}
    public function findByIdentifier(string $identifier): ?AuthenticatableUserInterface { return strcasecmp($identifier,$this->user->email??'')===0?$this->user:null; }
    public function findById(int|string $id): ?AuthenticatableUserInterface { return (string)$id===(string)$this->user->id?$this->user:null; }
};
$auth = new AuthManager($provider,new NativeSessionStore('users_auth_test'));
$result=$auth->attempt('demo@example.com','password123');
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Users + Auth Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-body-tertiary"><nav class="navbar bg-dark navbar-dark"><div class="container"><span class="navbar-brand">Prefab Users + Auth Test</span></div></nav><main class="container py-4"><div class="row g-3"><div class="col-md-6"><div class="card shadow-sm"><div class="card-header fw-semibold">User</div><div class="card-body"><div class="h5"><?=e($user->name)?></div><div><?=e($user->email)?></div><span class="badge text-bg-success mt-2">Active</span></div></div></div><div class="col-md-6"><div class="card shadow-sm"><div class="card-header fw-semibold">Authentication</div><div class="card-body"><div class="small text-muted">Login result</div><div class="h2 text-<?=$result->success?'success':'danger'?>"><?=$result->success?'SUCCESS':'FAILED'?></div><div>Authenticated ID: <strong><?=e($auth->id()??'—')?></strong></div></div></div></div></div></main></body></html>