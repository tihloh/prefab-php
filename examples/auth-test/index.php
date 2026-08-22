<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;

$user = new class implements AuthenticatableUserInterface {
    public function authId(): int|string { return 1; }
    public function authPasswordHash(): ?string { return password_hash('password123', PASSWORD_DEFAULT); }
    public function authIsActive(): bool { return true; }
};
$provider = new class($user) implements AuthUserProviderInterface {
    public function __construct(private AuthenticatableUserInterface $user) {}
    public function findByIdentifier(string $identifier): ?AuthenticatableUserInterface { return $identifier==='demo@example.com' ? $this->user : null; }
    public function findById(int|string $id): ?AuthenticatableUserInterface { return (string)$id==='1' ? $this->user : null; }
};
$auth = new AuthManager($provider,new NativeSessionStore('auth_test_user'));
$result = $auth->attempt('demo@example.com','password123');

header('Content-Type:text/plain');
echo "AUTH TEST\n\n";
echo 'Login: '.($result->success?'SUCCESS':'FAILED')."\n";
echo 'Authenticated ID: '.var_export($auth->id(),true)."\n\n";
print_r($result->log);
