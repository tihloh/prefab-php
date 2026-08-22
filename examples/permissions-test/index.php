<?php
require __DIR__.'/vendor/autoload.php';

use Tihloh\Prefab\Permissions\Contracts\PermissionStoreInterface;
use Tihloh\Prefab\Permissions\Services\PermissionDefinitions;
use Tihloh\Prefab\Permissions\Services\PermissionManager;

$store = new class implements PermissionStoreInterface {
    private array $data=[];
    public function get(string $type,int|string $id): array { return $this->data[$type][(string)$id]??[]; }
    public function put(string $type,int|string $id,array $permissions): void { $this->data[$type][(string)$id]=$permissions; }
    public function remove(string $type,int|string $id): void { unset($this->data[$type][(string)$id]); }
};
$defs = new PermissionDefinitions([
    'documents.view'=>['name'=>'View Documents','description'=>'View documents','default'=>true],
    'documents.approve'=>['name'=>'Approve Documents','description'=>'Approve documents','default'=>false],
]);
$permissions = new PermissionManager($defs,$store);
$permissions->set('group',10,'documents.approve',true);
$permissions->set('user',25,'documents.view',false);

header('Content-Type:text/plain');
echo "PERMISSIONS TEST\n\n";
foreach ($permissions->definitions() as $id=>$def) {
    $r=$permissions->resolve(25,$id,[10]);
    echo $id.' => '.($r->allowed?'ALLOW':'DENY').' via '.$r->source."\n";
}
