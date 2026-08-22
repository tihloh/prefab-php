<?php
require __DIR__.'/vendor/autoload.php';
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Auth\Session\NativeSessionStore;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;
use Tihloh\Prefab\Logs\Services\LogManager;
$user=new class implements AuthenticatableUserInterface { public function authId():int|string{return 1;} public function authPasswordHash():?string{return password_hash('password123',PASSWORD_DEFAULT);} public function authIsActive():bool{return true;} };
$users=new class($user) implements AuthUserProviderInterface { public function __construct(private AuthenticatableUserInterface $u){} public function findByIdentifier(string $i):?AuthenticatableUserInterface{return $i==='demo@example.com'?$this->u:null;} public function findById(int|string $id):?AuthenticatableUserInterface{return (string)$id==='1'?$this->u:null;} };
$repo=new class implements LogRepositoryInterface { private array $r=[]; private int $n=1; public function record(LogEntry $e):int|string{$id=$this->n++;$this->r[$id]=['id'=>$id]+$e->toArray();return $id;} public function find(int|string $id):?array{return $this->r[(int)$id]??null;} public function recent(int $limit=100,int $offset=0):array{return array_slice(array_values($this->r),$offset,$limit);} public function forSubject(string $t,int|string $id,int $limit=100):array{return array_values(array_filter($this->r,fn($x)=>$x['subject_type']===$t&&(string)$x['subject_id']===(string)$id));} public function forActor(int|string $id,int $limit=100):array{return array_values(array_filter($this->r,fn($x)=>(string)($x['actor_id']??'')===(string)$id));} };
$logs=new LogManager($repo); $auth=new AuthManager($users,new NativeSessionStore('auth_logs_test'));
$result=$auth->attempt('demo@example.com','password123'); if($result->log)$logs->record($result->log);
header('Content-Type:text/plain'); echo "AUTH + LOGS TEST\n\n"; echo 'Login: '.($result->success?'SUCCESS':'FAILED')."\n\n"; print_r($logs->recent());
