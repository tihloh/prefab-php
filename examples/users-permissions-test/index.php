<?php
require __DIR__.'/vendor/autoload.php';
use Tihloh\Prefab\Users\User\PrefabUser;
use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;
use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
class ProjectUser extends PrefabUser implements PermissionSubjectInterface {
    public function __construct(int $id,string $name,string $email,private array $groups=[]) { parent::__construct($id,$name,$email,true); }
    public function permissionSubjectId(): int|string { return $this->id; }
    public function permissionGroupIds(): array { return $this->groups; }
}
$store=new class implements PermissionStoreInterface { private array $d=[]; public function get(string $t,int|string $i):array{return $this->d[$t][(string)$i]??[];} public function put(string $t,int|string $i,array $p):void{$this->d[$t][(string)$i]=$p;} public function remove(string $t,int|string $i):void{unset($this->d[$t][(string)$i]);} };
$permissions=new PermissionManager(new PermissionDefinitions(['documents.view'=>['default'=>true],'documents.approve'=>['default'=>false]]),$store);
$permissions->set('group',10,'documents.approve',true);
$user=new ProjectUser(25,'Alice','alice@example.com',[10]);
header('Content-Type:text/plain');
echo "USERS + PERMISSIONS TEST\n\n";
echo $user->name."\n";
echo 'view: '.($permissions->can($user,'documents.view')?'ALLOW':'DENY')."\n";
echo 'approve: '.($permissions->can($user,'documents.approve')?'ALLOW':'DENY')."\n";
