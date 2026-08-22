<?php
require __DIR__.'/vendor/autoload.php';
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;
use Tihloh\Prefab\Logs\Services\LogManager;
$store=new class implements PermissionStoreInterface { private array $d=[]; public function get(string $t,int|string $i):array{return $this->d[$t][(string)$i]??[];} public function put(string $t,int|string $i,array $p):void{$this->d[$t][(string)$i]=$p;} public function remove(string $t,int|string $i):void{unset($this->d[$t][(string)$i]);} };
$repo=new class implements LogRepositoryInterface { private array $r=[]; private int $n=1; public function record(LogEntry $e):int|string{$id=$this->n++;$this->r[$id]=['id'=>$id]+$e->toArray();return $id;} public function find(int|string $id):?array{return $this->r[(int)$id]??null;} public function recent(int $limit=100,int $offset=0):array{return array_slice(array_values($this->r),$offset,$limit);} public function forSubject(string $t,int|string $id,int $limit=100):array{return array_values(array_filter($this->r,fn($x)=>$x['subject_type']===$t&&(string)$x['subject_id']===(string)$id));} public function forActor(int|string $id,int $limit=100):array{return array_values(array_filter($this->r,fn($x)=>(string)($x['actor_id']??'')===(string)$id));} };
$permissions=new PermissionManager(new PermissionDefinitions(['documents.approve'=>['name'=>'Approve Documents','default'=>false]]),$store);
$logs=new LogManager($repo);
$result=$permissions->set('user',25,'documents.approve',true,['actor_id'=>1]);
$logs->record($result->log);
$effective=$permissions->resolve(25,'documents.approve');
$rows=$logs->recent();
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Permissions + Logs Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-body-tertiary"><nav class="navbar bg-dark navbar-dark"><div class="container"><span class="navbar-brand">Prefab Permissions + Logs Test</span></div></nav><main class="container py-4"><div class="row g-3 mb-4"><div class="col-md-6"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">documents.approve for User 25</div><div class="h2 text-<?=$effective->allowed?'success':'danger'?>"><?=$effective->allowed?'ALLOW':'DENY'?></div><div>Source: <?=e($effective->source)?></div></div></div></div><div class="col-md-6"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Logs recorded</div><div class="display-6"><?=count($rows)?></div></div></div></div></div><div class="card shadow-sm"><div class="card-header fw-semibold">Permission activity</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Action</th><th>Subject</th><th>Message</th><th>Changes</th></tr></thead><tbody><?php foreach($rows as $row):?><tr><td><?=e($row['id'])?></td><td><code><?=e($row['action'])?></code></td><td><?=e(($row['subject_type']??'').' #'.($row['subject_id']??''))?></td><td><?=e($row['message']??'')?></td><td><small><code><?=e(json_encode($row['changes']??[]))?></code></small></td></tr><?php endforeach;?></tbody></table></div></div></main></body></html>