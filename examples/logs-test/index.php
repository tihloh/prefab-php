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

header('Content-Type:text/plain');
echo "LOGS TEST\n\n";
print_r($logs->recent());
