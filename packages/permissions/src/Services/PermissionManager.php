<?php

namespace Tihloh\Prefab\Permissions\Services;

use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\DTOs\OperationResult;
use Tihloh\Prefab\Permissions\DTOs\PermissionResult;

final class PermissionManager
{
    private ?object $context=null;
    private ?object $events=null;
    public function __construct(private PermissionDefinitions $definitions,private PermissionStoreInterface $store){}
    public function useContext(object $context):self{$this->context=$context;return $this;}
    public function useEvents(object $events):self{$this->events=$events;return $this;}
    public function can(PermissionSubjectInterface|int|string $subject,string $permission,array $groupIds=[]):bool{return $this->resolve($subject,$permission,$groupIds)->allowed;}
    public function resolve(PermissionSubjectInterface|int|string $subject,string $permission,array $groupIds=[]):PermissionResult
    {
        if(!$this->definitions->has($permission))return new PermissionResult(false,'unknown');
        if($subject instanceof PermissionSubjectInterface){$subjectId=$subject->permissionSubjectId();$groupIds=$subject->permissionGroupIds();}else{$subjectId=$subject;}
        $user=$this->store->get('user',$subjectId);if(array_key_exists($permission,$user))return new PermissionResult((bool)$user[$permission],'user');
        $allows=[];$denies=[];foreach($groupIds as $gid){$g=$this->store->get('group',$gid);if(!array_key_exists($permission,$g))continue;$g[$permission]===true?$allows[]=$gid:$denies[]=$gid;}
        if($allows!==[])return new PermissionResult(true,'group',$allows,$denies);
        if($denies!==[])return new PermissionResult(false,'group',$denies,$denies);
        return new PermissionResult($this->definitions->default($permission),'default');
    }
    public function overridesFor(string $type,int|string $id):array{return $this->store->get($type,$id);}
    public function resolvedFor(PermissionSubjectInterface|int|string $subject,array $groups=[]):array{$r=[];foreach(array_keys($this->definitions->all()) as $p)$r[$p]=$this->resolve($subject,$p,$groups);return $r;}
    public function set(string $type,int|string $id,string $permission,bool $value,array $context=[]):OperationResult
    {
        $p=$this->store->get($type,$id);$old=array_key_exists($permission,$p)?(bool)$p[$permission]:null;$p[$permission]=$value;$this->store->put($type,$id,$this->definitions->validateOverrides($p));
        return $this->result($value,$this->logPayload($value?'permission.granted':'permission.denied',$type,$id,$permission,$old,$value,$context));
    }
    public function clear(string $type,int|string $id,string $permission,array $context=[]):OperationResult
    {
        $p=$this->store->get($type,$id);$old=array_key_exists($permission,$p)?(bool)$p[$permission]:null;unset($p[$permission]);$p===[]?$this->store->remove($type,$id):$this->store->put($type,$id,$p);
        return $this->result(true,$this->logPayload('permission.cleared',$type,$id,$permission,$old,null,$context));
    }
    public function clearAll(string $type,int|string $id):void{$this->store->remove($type,$id);}
    public function definitions():array{return $this->definitions->all();}
    public function definition(string $p):?array{return $this->definitions->get($p);}
    public function defined(string $p):bool{return $this->definitions->has($p);}
    private function result(mixed $data,array $log):OperationResult{if($this->events&&method_exists($this->events,'dispatch'))$this->events->dispatch('prefab.log',$log);return new OperationResult($data,$log);}
    private function logPayload(string $action,string $type,int|string $id,string $permission,?bool $old,?bool $new,array $context):array
    {
        $base=($this->context&&method_exists($this->context,'logContext'))?$this->context->logContext():[];$context=array_replace($base,$context);
        $verb=match($action){'permission.granted'=>'granted to','permission.denied'=>'denied for',default=>'cleared from'};
        return ['action'=>$action,'subject_type'=>$type,'subject_id'=>$id,'actor_type'=>$context['actor_type']??null,'actor_id'=>$context['actor_id']??null,'message'=>"Permission {$permission} was {$verb} {$type} {$id}.",'changes'=>[$permission=>['old'=>$old,'new'=>$new]],'metadata'=>array_merge(['permission'=>$permission],$context['metadata']??[]),'ip_address'=>$context['ip_address']??null,'user_agent'=>$context['user_agent']??null];
    }
}
