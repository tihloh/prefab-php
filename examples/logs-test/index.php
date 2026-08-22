<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;
use Tihloh\Prefab\Logs\Services\LogManager;

$repo = new class implements LogRepositoryInterface {
    private array $rows=[]; private int $next=1;
    public function record(LogEntry $entry): int|string { $id=$this->next++; $this->rows[$id]=['id'=>$id]+$entry->toArray(); return $id; }
    public function find(int|string $id): ?array { return $this->rows[(int)$id]??null; }
    public function recent(int $limit=100,int $offset=0): array { return array_slice(array_values(array_reverse($this->rows,true)),$offset,$limit); }
    public function forSubject(string $type,int|string $id,int $limit=100): array { return array_values(array_filter($this->rows,fn($r)=>$r['subject_type']===$type&&(string)$r['subject_id']===(string)$id)); }
    public function forActor(int|string $id,int $limit=100): array { return array_values(array_filter($this->rows,fn($r)=>(string)($r['actor_id']??'')===(string)$id)); }
};
$logs = new LogManager($repo);
$logs->record(['action'=>'user.created','subject_type'=>'user','subject_id'=>25,'actor_id'=>1,'message'=>'Created user 25']);
$logs->record(['action'=>'permission.granted','subject_type'=>'user','subject_id'=>25,'actor_id'=>1,'message'=>'Granted documents.approve']);
$rows=$logs->recent();
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logs Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body class="bg-body-tertiary"><nav class="navbar bg-dark navbar-dark"><div class="container"><span class="navbar-brand">Prefab Logs Test</span></div></nav><main class="container py-4">
<div class="row g-3 mb-4"><div class="col-md-4"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Log entries</div><div class="display-6"><?= count($rows) ?></div></div></div></div><div class="col-md-8"><div class="card shadow-sm"><div class="card-body"><div class="small text-muted">Storage</div><div class="fw-semibold">In-memory LogRepositoryInterface implementation</div></div></div></div></div>
<div class="card shadow-sm"><div class="card-header fw-semibold">Activity log</div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>ID</th><th>Action</th><th>Subject</th><th>Actor</th><th>Message</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr><td><?= e($row['id']) ?></td><td><code><?= e($row['action']) ?></code></td><td><?= e($row['subject_type'].' #'.$row['subject_id']) ?></td><td><?= e($row['actor_id']??'—') ?></td><td><?= e($row['message']??'') ?></td></tr><?php endforeach; ?></tbody></table></div></div>
</main></body></html>