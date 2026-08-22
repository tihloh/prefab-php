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
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Auth Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-body-tertiary"><nav class="navbar bg-dark navbar-dark"><div class="container"><span class="navbar-brand">Prefab Auth Test</span></div></nav><main class="container py-4">
<div class="row g-3 mb-4"><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Login result</div><div class="h3 mb-0 text-<?= $result->success?'success':'danger' ?>"><?= $result->success?'SUCCESS':'FAILED' ?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Authenticated</div><div class="h3 mb-0"><?= $auth->check()?'YES':'NO' ?></div></div></div></div><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">User ID</div><div class="h3 mb-0"><?= e($auth->id()??'—') ?></div></div></div></div></div>
<div class="card shadow-sm"><div class="card-header fw-semibold">Generated auth log</div><div class="card-body"><pre class="mb-0"><?= e(json_encode($result->log, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre></div></div>
</main></body></html>