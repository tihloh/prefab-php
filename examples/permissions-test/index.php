<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;

$store = new class implements PermissionStoreInterface {
    private array $data=[];
    public function get(string $type,int|string $id): array { return $this->data[$type][(string)$id]??[]; }
    public function put(string $type,int|string $id,array $permissions): void { $this->data[$type][(string)$id]=$permissions; }
    public function remove(string $type,int|string $id): void { unset($this->data[$type][(string)$id]); }
};
$defs = new PermissionDefinitions([
    'documents.view'=>['name'=>'View Documents','description'=>'View documents','default'=>true],
    'documents.approve'=>['name'=>'Approve Documents','description'=>'Approve documents','default'=>false],
]);
$permissions = new PermissionManager($defs,$store);
$permissions->set('group',10,'documents.approve',true);
$permissions->set('user',25,'documents.view',false);
$rows=[];
foreach ($permissions->definitions() as $id=>$def) $rows[$id]=['definition'=>$def,'result'=>$permissions->resolve(25,$id,[10])];
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Permissions Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-body-tertiary"><nav class="navbar bg-dark navbar-dark"><div class="container"><span class="navbar-brand">Prefab Permissions Test</span></div></nav><main class="container py-4">
<div class="alert alert-info">Resolving permissions for <strong>User 25</strong> with membership in <strong>Group 10</strong>.</div>
<div class="card shadow-sm"><div class="card-header fw-semibold">Effective permissions</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Permission</th><th>Description</th><th>Effective</th><th>Source</th></tr></thead><tbody><?php foreach($rows as $id=>$row): $r=$row['result']; ?><tr><td><code><?= e($id) ?></code><div class="fw-semibold"><?= e($row['definition']['name']??$id) ?></div></td><td><?= e($row['definition']['description']??'') ?></td><td><span class="badge text-bg-<?= $r->allowed?'success':'danger' ?>"><?= $r->allowed?'ALLOW':'DENY' ?></span></td><td><?= e($r->source) ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main></body></html>