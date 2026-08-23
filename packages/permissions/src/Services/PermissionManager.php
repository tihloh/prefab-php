<?php

namespace Tihloh\Prefab\Permissions\Services;

use PDO;
use RuntimeException;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\DTOs\OperationResult;
use Tihloh\Prefab\Permissions\DTOs\PermissionResult;
use Tihloh\Prefab\Permissions\Repositories\PdoPermissionStore;

final class PermissionManager
{
    private ?PermissionDefinitions $definitions = null;
    private ?PermissionStoreInterface $store = null;
    private ?PDO $database = null;
    private array $config = [];
    private ?object $context=null;
    private ?object $events=null;

    public function __construct(PermissionDefinitions|array|null $definitions = null, ?PermissionStoreInterface $store = null)
    {
        if ($definitions instanceof PermissionDefinitions) $this->definitions = $definitions;
        elseif (is_array($definitions)) $this->config = $definitions;
        if ($store) $this->store = $store;
        PrefabRuntime::register('permissions', $this);
    }

    public function prefabConfigure(): void
    {
        if (!$this->definitions) {
            $defs = $this->config['definitions'] ?? PrefabConfig::module('permissions', 'definitions');
            if ($defs instanceof PermissionDefinitions) $this->definitions = $defs;
            elseif (is_array($defs)) $this->definitions = new PermissionDefinitions($defs);
            else $this->definitions = new PermissionDefinitions([]);
        }

        if ($this->store) return;

        $configured = $this->config['store'] ?? PrefabConfig::module('permissions', 'store');
        if ($configured instanceof PermissionStoreInterface) {
            $this->store = $configured;
            return;
        }

        $db = $this->config['database'] ?? PrefabConfig::module('permissions', 'database');
        if (!$db instanceof PDO) {
            $users = PrefabRuntime::get('users');
            if ($users && method_exists($users, 'prefabResource')) $db = $users->prefabResource('database');
        }
        if ($db instanceof PDO) {
            $this->database = $db;
            $this->store = new PdoPermissionStore($db, $this->config['table'] ?? 'prefab_subject_permissions');
        }
    }

    public function prefabResource(string $name): mixed
    {
        return match ($name) {
            'database' => $this->database,
            'permission_store' => $this->store,
            default => null,
        };
    }

    public function useContext(object $context):self{$this->context=$context;return $this;}
    public function useEvents(object $events):self{$this->events=$events;return $this;}
    public function can(PermissionSubjectInterface|int|string $subject,string $permission,array $groupIds=[]):bool{return $this->resolve($subject,$permission,$groupIds)->allowed;}
    public function resolve(PermissionSubjectInterface|int|string $subject,string $permission,array $groupIds=[]):PermissionResult
    {
        $definitions=$this->defs();$store=$this->store();
        if(!$definitions->has($permission))return new PermissionResult(false,'unknown');
        if($subject instanceof PermissionSubjectInterface){$subjectId=$subject->permissionSubjectId();$groupIds=$subject->permissionGroupIds();}else{$subjectId=$subject;}
        $user=$store->get('user',$subjectId);if(array_key_exists($permission,$user))return new PermissionResult((bool)$user[$permission],'user');
        $allows=[];$denies=[];foreach($groupIds as $gid){$g=$store->get('group',$gid);if(!array_key_exists($permission,$g))continue;$g[$permission]===true?$allows[]=$gid:$denies[]=$gid;}
        if($allows!==[])return new PermissionResult(true,'group',$allows,$denies);
        if($denies!==[])return new PermissionResult(false,'group',$denies,$denies);
        return new PermissionResult($definitions->default($permission),'default');
    }
    public function overridesFor(string $type,int|string $id):array{return $this->store()->get($type,$id);}
    public function resolvedFor(PermissionSubjectInterface|int|string $subject,array $groups=[]):array{$r=[];foreach(array_keys($this->defs()->all()) as $p)$r[$p]=$this->resolve($subject,$p,$groups);return $r;}
    public function set(string $type,int|string $id,string $permission,bool $value,array $context=[]):OperationResult
    {
        $store=$this->store();$defs=$this->defs();$p=$store->get($type,$id);$old=array_key_exists($permission,$p)?(bool)$p[$permission]:null;$p[$permission]=$value;$store->put($type,$id,$defs->validateOverrides($p));
        return $this->result($value,$this->logPayload($value?'permission.granted':'permission.denied',$type,$id,$permission,$old,$value,$context));
    }
    public function clear(string $type,int|string $id,string $permission,array $context=[]):OperationResult
    {
        $store=$this->store();$p=$store->get($type,$id);$old=array_key_exists($permission,$p)?(bool)$p[$permission]:null;unset($p[$permission]);$p===[]?$store->remove($type,$id):$store->put($type,$id,$p);
        return $this->result(true,$this->logPayload('permission.cleared',$type,$id,$permission,$old,null,$context));
    }
    public function clearAll(string $type,int|string $id):void{$this->store()->remove($type,$id);}
    public function definitions():array{return $this->defs()->all();}
    public function definition(string $p):?array{return $this->defs()->get($p);}
    public function defined(string $p):bool{return $this->defs()->has($p);}

    private function defs(): PermissionDefinitions
    {
        if (!$this->definitions) $this->prefabConfigure();
        return $this->definitions ?? new PermissionDefinitions([]);
    }

    private function store(): PermissionStoreInterface
    {
        if (!$this->store) throw new RuntimeException('Prefab Permissions needs a store/database configuration.');
        return $this->store;
    }

    private function result(mixed $data,array $log):OperationResult
    {
        if($this->events&&method_exists($this->events,'dispatch'))$this->events->dispatch('prefab.log',$log);
        else PrefabRuntime::emitLog($log);
        return new OperationResult($data,$log);
    }
    private function logPayload(string $action,string $type,int|string $id,string $permission,?bool $old,?bool $new,array $context):array
    {
        $base=($this->context&&method_exists($this->context,'logContext'))?$this->context->logContext():[];
        if(!array_key_exists('actor_id',$base))$base['actor_id']=PrefabRuntime::actorId();
        if(!array_key_exists('actor_type',$base)&&($base['actor_id']??null)!==null)$base['actor_type']='user';
        $context=array_replace($base,$context);
        $verb=match($action){'permission.granted'=>'granted to','permission.denied'=>'denied for',default=>'cleared from'};
        return ['action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'actor_type'=>$context['actor_type']??null,'actor_id'=>$context['actor_id']??null,'message'=>"Permission {$permission} was {$verb} {$type} {$id}.",'changes'=>[$permission=>['old'=>$old,'new'=>$new]],'metadata'=>array_merge(['permission'=>$permission],$context['metadata']??[]),'ip_address'=>$context['ip_address']??null,'user_agent'=>$context['user_agent']??null];
    }
}
