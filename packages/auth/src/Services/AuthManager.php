<?php

namespace Tihloh\Prefab\Auth\Services;

use Tihloh\Prefab\Auth\Contracts\AuthSessionStoreInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\DTOs\AuthResult;

final class AuthManager
{
    private ?object $context = null;
    private ?object $events = null;
    public function __construct(private AuthUserProviderInterface $users, private AuthSessionStoreInterface $session) {}
    public function useContext(object $context): self { $this->context=$context; return $this; }
    public function useEvents(object $events): self { $this->events=$events; return $this; }

    public function attempt(string $identifier,string $password,array $context=[]):AuthResult
    {
        $user=$this->users->findByIdentifier($identifier);
        if(!$user||!$user->authIsActive()) return $this->result(false,null,$this->log('auth.login_failed',null,$context,['identifier'=>$identifier]),'invalid_credentials');
        $hash=$user->authPasswordHash();
        if(!$hash||!password_verify($password,$hash)) return $this->result(false,null,$this->log('auth.login_failed',$user->authId(),$context),'invalid_credentials');
        $this->session->put($user->authId());
        return $this->result(true,$user,$this->log('auth.login',$user->authId(),$context));
    }

    public function login(AuthenticatableUserInterface $user,array $context=[]):AuthResult
    {
        if(!$user->authIsActive()) return new AuthResult(false,null,null,'inactive');
        $this->session->put($user->authId());
        return $this->result(true,$user,$this->log('auth.login',$user->authId(),$context));
    }

    public function logout(array $context=[]):AuthResult
    {
        $id=$this->session->userId();
        $log=$this->log('auth.logout',$id,$context);
        $this->session->forget();
        return $this->result(true,null,$log);
    }

    public function check():bool{return $this->session->userId()!==null;}
    public function id():int|string|null{return $this->session->userId();}
    public function user():?AuthenticatableUserInterface{$id=$this->session->userId();return $id===null?null:$this->users->findById($id);}

    private function result(bool $success,?AuthenticatableUserInterface $user,?array $log,?string $error=null):AuthResult
    {
        if($log&&$this->events&&method_exists($this->events,'dispatch'))$this->events->dispatch('prefab.log',$log);
        return new AuthResult($success,$user,$log,$error);
    }

    private function log(string $action,int|string|null $userId,array $context,array $metadata=[]):array
    {
        $base=($this->context&&method_exists($this->context,'logContext'))?$this->context->logContext():[];
        $context=array_replace($base,$context);
        $actorId=$action==='auth.login_failed'?($context['actor_id']??null):$userId;
        return ['action'=>$action,'subject_type'=>'user','subject_id'=>$userId,'actor_type'=>$actorId!==null?'user':null,'actor_id'=>$actorId,'message'=>match($action){'auth.login'=>'User signed in.','auth.logout'=>'User signed out.',default=>'Sign-in attempt failed.'},'metadata'=>array_merge($metadata,$context['metadata']??[]),'ip_address'=>$context['ip_address']??null,'user_agent'=>$context['user_agent']??null];
    }
}
