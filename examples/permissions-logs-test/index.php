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
$permissions=new PermissionManager(new PermissionDefinitions(['documents.approve'=>['default'=>false]]),$store);
$logs=new LogManager($repo);
$result=$permissions->set('user',25,'documents.approve',true,['actor_id'=>1]);
$logs->record($result->log);
header('Content-Type:text/plain'); echo "PERMISSIONS + LOGS TEST\n\n"; print_r($logs->recent());
