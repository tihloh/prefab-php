<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\Logs\Contracts\LogRepositoryInterface;
use Tihloh\Prefab\Logs\DTOs\LogEntry;
use Tihloh\Prefab\Logs\Services\LogManager;

/*
 * OPTIONAL COMMON CONFIGURATION
 * PrefabConfig::set(['database'=>$pdo]);
 *
 * Logs can use the shared database automatically. Override only Logs when needed:
 * PrefabConfig::set(['database'=>$mainPdo,'modules'=>['logs'=>['database'=>$logPdo]]]);
 * Or explicitly: $logs = new LogManager(['database'=>$logPdo]);
 */

if(session_status()!==PHP_SESSION_ACTIVE)session_start();
$_SESSION['logs_test_rows']??=[];
$repo=new class implements LogRepositoryInterface{
 public function record(LogEntry $e):int|string{$id=count($_SESSION['logs_test_rows'])+1;$_SESSION['logs_test_rows'][$id]=['id'=>$id]+$e->toArray();return $id;}
 public function find(int|string $id):?array{return $_SESSION['logs_test_rows'][(int)$id]??null;}
 public function recent(int $limit=100,int $offset=0):array{return array_slice(array_values(array_reverse($_SESSION['logs_test_rows'],true)),$offset,$limit);}
 public function forSubject(string $t,int|string $id,int $limit=100):array{return array_values(array_filter($_SESSION['logs_test_rows'],fn($r)=>$r['subject_type']===$t&&(string)$r['subject_id']===(string)$id));}
 public function forActor(int|string $id,int $limit=100):array{return array_values(array_filter($_SESSION['logs_test_rows'],fn($r)=>(string)($r['actor_id']??'')===(string)$id));}
};

// Explicit repository only because this test intentionally avoids a database.
$logs=new LogManager($repo);

/* Database-backed alternative:
 * PrefabConfig::set(['database'=>$pdo]);
 * $logs = new LogManager();
 */

if($_SERVER['REQUEST_METHOD']==='POST'){$a=$_POST['action']??'';if($a==='add')$logs->record(['action'=>trim($_POST['event']),'subject_type'=>trim($_POST['subject_type']),'subject_id'=>trim($_POST['subject_id']),'actor_id'=>trim($_POST['actor_id']),'message'=>trim($_POST['message'])]);elseif($a==='reset'){unset($_SESSION['logs_test_rows']);header('Location:'.$_SERVER['PHP_SELF']);exit;}}
$rows=$logs->recent();
function e(mixed $v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?><!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Logs Test</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-body-tertiary"><main class="container py-4"><div class="d-flex justify-content-between align-items-center mb-3"><h1 class="h3 mb-0">Logs Interactive Test</h1><form method="post"><button name="action" value="reset" class="btn btn-outline-secondary btn-sm">Reset</button></form></div><div class="row g-4"><div class="col-md-4"><div class="card"><div class="card-header">Add Log Entry</div><div class="card-body"><form method="post" class="vstack gap-2"><input type="hidden" name="action" value="add"><input class="form-control" name="event" value="user.updated" placeholder="Action"><input class="form-control" name="subject_type" value="user" placeholder="Subject type"><input class="form-control" name="subject_id" value="25" placeholder="Subject ID"><input class="form-control" name="actor_id" value="1" placeholder="Actor ID"><textarea class="form-control" name="message" placeholder="Message">Updated user profile</textarea><button class="btn btn-primary">Record</button></form></div></div></div><div class="col-md-8"><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>#</th><th>Action</th><th>Subject</th><th>Actor</th><th>Message</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($r['id'])?></td><td><code><?=e($r['action'])?></code></td><td><?=e($r['subject_type'])?> #<?=e($r['subject_id'])?></td><td><?=e($r['actor_id'])?></td><td><?=e($r['message']??'')?></td></tr><?php endforeach;?></tbody></table></div></div></div></div></main></body></html>