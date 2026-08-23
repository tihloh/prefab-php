<?php

require dirname(__DIR__) . '/vendor/autoload.php';

use TestApp\InMemoryUserProvider;
use TestApp\SessionSocialAccountStore;
use TestApp\TestSocialUserResolver;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Services\SocialAuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;
use Tihloh\Prefab\Auth\Social\CallbackSocialProvider;
use Tihloh\Prefab\Auth\Social\NativeSessionSocialStateStore;
use Tihloh\Prefab\Auth\Social\SocialIdentity;
use Tihloh\Prefab\Auth\Social\SocialProviderRegistry;

/*
 * OPTIONAL COMMON CONFIGURATION
 * PrefabConfig::set([...]);
 * Social provider credentials/configuration remain project-specific.
 */

$users = new InMemoryUserProvider();
$auth = new AuthManager($users, new NativeSessionStore());

/*
 * This standalone social-auth demo explicitly supplies its in-memory user source.
 * In a combined project, Auth can automatically use a compatible Prefab Users
 * module when no Auth provider is configured.
 */

$providers = new SocialProviderRegistry();
$providers->register(new CallbackSocialProvider(
    'mock-google',
    function (string $state): string {
        return '/?action=mock-provider&state=' . urlencode($state);
    },
    function (array $query): SocialIdentity {
        return new SocialIdentity(
            provider: 'mock-google',
            providerUserId: 'google-demo-001',
            email: 'demo@example.com',
            name: 'Demo Google User',
            avatar: null,
            raw: $query,
        );
    },
));

$social = new SocialAuthManager(
    providers: $providers,
    accounts: new SessionSocialAccountStore(),
    states: new NativeSessionSocialStateStore(),
    users: $users,
    resolver: new TestSocialUserResolver($users),
    auth: $auth,
);

$action = $_GET['action'] ?? 'home';

if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = $auth->attempt($_POST['email'] ?? '', $_POST['password'] ?? '');
    $_SESSION['last_log'] = $result->log;
    header('Location: /');
    exit;
}

if ($action === 'social') {
    header('Location: ' . $social->authorizationUrl('mock-google'));
    exit;
}

if ($action === 'mock-provider') {
    $state = $_GET['state'] ?? '';
    header('Location: /?action=callback&state=' . urlencode($state) . '&code=demo-code');
    exit;
}

if ($action === 'callback') {
    $result = $social->callback('mock-google', $_GET);
    $_SESSION['last_log'] = $result->log;
    header('Location: /');
    exit;
}

if ($action === 'logout') {
    $result = $auth->logout();
    $_SESSION['last_log'] = $result->log;
    header('Location: /');
    exit;
}

$user = $auth->user();
$accounts = $user ? $social->accountsForUser($user->authId()) : [];
$lastLog = $_SESSION['last_log'] ?? null;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Prefab Auth Social Test</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-body-tertiary">
<div class="container py-5" style="max-width: 760px">
    <h1 class="mb-4">Tihloh Prefab Auth Test</h1>

    <?php if (!$auth->check()): ?>
        <div class="card mb-3"><div class="card-body"><h5 class="card-title">Password sign-in</h5><form method="post" action="/?action=login" class="row g-3"><div class="col-12"><label class="form-label">Email</label><input class="form-control" name="email" value="demo@example.com"></div><div class="col-12"><label class="form-label">Password</label><input class="form-control" type="password" name="password" value="password123"></div><div class="col-12"><button class="btn btn-primary">Sign in</button></div></form></div></div>
        <div class="card"><div class="card-body"><h5 class="card-title">Social sign-in</h5><p class="text-muted">This uses a local mock provider but follows the real OAuth redirect/callback flow.</p><a class="btn btn-outline-dark" href="/?action=social">Continue with Mock Google</a></div></div>
    <?php else: ?>
        <div class="alert alert-success">Signed in successfully.</div>
        <div class="card mb-3"><div class="card-body"><h5><?= htmlspecialchars($user->name ?? 'User') ?></h5><div><?= htmlspecialchars($user->email ?? '') ?></div><div class="text-muted">User ID: <?= htmlspecialchars((string)$user->authId()) ?></div></div></div>
        <div class="card mb-3"><div class="card-header">Linked social accounts</div><div class="card-body"><?php if ($accounts === []): ?><span class="text-muted">None</span><?php else: ?><pre class="mb-0"><?= htmlspecialchars(json_encode($accounts, JSON_PRETTY_PRINT)) ?></pre><?php endif; ?></div></div>
        <a class="btn btn-danger" href="/?action=logout">Logout</a>
    <?php endif; ?>

    <?php if ($lastLog): ?><div class="card mt-4"><div class="card-header">Last structured log payload</div><div class="card-body"><pre class="mb-0"><?= htmlspecialchars(json_encode($lastLog, JSON_PRETTY_PRINT)) ?></pre></div></div><?php endif; ?>
</div>
</body>
</html>
