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
header('Content-Type:text/plain');
echo "USERS + AUTH TEST\n\n";
echo "PrefabUser name: {$user->name}\n";
echo 'Login: '.($result->success?'SUCCESS':'FAILED')."\n";
