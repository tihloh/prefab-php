<?php
require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\Services\UserManager;
use Tihloh\Prefab\Users\User\PrefabUser;

$provider = new class implements UserProviderInterface {
    private array $rows = [];
    public function __construct() { $this->rows[1] = ['id'=>1,'name'=>'Alice','email'=>'alice@example.com','active'=>true]; }
    private function user(array $r): PrefabUser { return new PrefabUser($r['id'],$r['name'],$r['email'],$r['active']); }
    public function find(int|string $id): ?PrefabUser { return isset($this->rows[(int)$id]) ? $this->user($this->rows[(int)$id]) : null; }
    public function findByEmail(string $email): ?PrefabUser { foreach ($this->rows as $r) if (strcasecmp($r['email'],$email)===0) return $this->user($r); return null; }
    public function all(int $limit=100,int $offset=0): array { return array_map(fn($r)=>$this->user($r), array_slice(array_values($this->rows),$offset,$limit)); }
    public function create(array $data): PrefabUser { $id=$this->rows?max(array_keys($this->rows))+1:1; $this->rows[$id]=['id'=>$id,'name'=>$data['name']??null,'email'=>$data['email']??null,'active'=>$data['active']??true]; return $this->user($this->rows[$id]); }
    public function update(int|string $id,array $data): PrefabUser { $i=(int)$id; $this->rows[$i]=array_merge($this->rows[$i],$data); return $this->user($this->rows[$i]); }
    public function delete(int|string $id): bool { $i=(int)$id; if(!isset($this->rows[$i])) return false; unset($this->rows[$i]); return true; }
};

$users = new UserManager($provider);
$created = $users->create(['name'=>'Bob','email'=>'bob@example.com']);
$updated = $users->update($created->data->id,['name'=>'Robert'],['actor_id'=>1]);
$list = $users->all();
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Users Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-body-tertiary"><nav class="navbar bg-dark navbar-dark"><div class="container"><span class="navbar-brand">Prefab Users Test</span></div></nav><main class="container py-4">
<div class="row g-3 mb-4"><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Users</div><div class="display-6"><?= count($list) ?></div></div></div></div><div class="col-md-8"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Last operation</div><div class="fw-semibold"><?= e($updated->log['action']) ?></div><div><?= e($updated->log['message']) ?></div></div></div></div></div>
<div class="card shadow-sm mb-4"><div class="card-header fw-semibold">Users</div><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th></tr></thead><tbody><?php foreach($list as $user): ?><tr><td><?= e($user->id) ?></td><td><?= e($user->name) ?></td><td><?= e($user->email) ?></td><td><span class="badge text-bg-<?= $user->active?'success':'secondary' ?>"><?= $user->active?'Active':'Inactive' ?></span></td></tr><?php endforeach; ?></tbody></table></div></div>
<div class="card shadow-sm"><div class="card-header fw-semibold">Generated log payload</div><div class="card-body"><pre class="mb-0"><?= e(json_encode($updated->log, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)) ?></pre></div></div>
</main></body></html>