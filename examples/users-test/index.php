<?php
require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\Services\UserManager;
use Tihloh\Prefab\Users\User\PrefabUser;

$provider = new class implements UserProviderInterface {
    private array $rows = [];
    public function __construct() { $this->rows[1] = ['id'=>1,'name'=>'Alice','email'=>'alice@example.com','active'=>true]; }
    private function user(array $r): PrefabUser { return new PrefabUser($r['id'],$r['name'],$r['email'],$r['active']); }
    public function find(int|string $id): ?PrefabUser { return isset($this->rows[(int)$id]) ? $this->user($this->rows[(int)$id]) : null; }
    public function findByEmail(string $email): ?PrefabUser { foreach ($this->rows as $r) if (strcasecmp($r['email'],$email)===0) return $this->user($r); return null; }
    public function all(int $limit=100,int $offset=0): array { return array_map(fn($r)=>$this->user($r), array_slice(array_values($this->rows),$offset,$limit)); }
    public function create(array $data): PrefabUser { $id=$this->rows?max(array_keys($this->rows))+1:1; $this->rows[$id]=['id'=>$id,'name'=>$data['name']??null,'email'=>$data['email']??null,'active'=>$data['active']??true]; return $this->user($this->rows[$id]); }
    public function update(int|string $id,array $data): PrefabUser { $i=(int)$id; $this->rows[$i]=array_merge($this->rows[$i],$data); return $this->user($this->rows[$i]); }
    public function delete(int|string $id): bool { $i=(int)$id; if(!isset($this->rows[$i])) return false; unset($this->rows[$i]); return true; }
};

$users = new UserManager($provider);
$created = $users->create(['name'=>'Bob','email'=>'bob@example.com']);
$updated = $users->update($created->data->id,['name'=>'Robert'],['actor_id'=>1]);

header('Content-Type: text/plain');
echo "USERS TEST\n\n";
foreach ($users->all() as $user) echo "{$user->id}: {$user->name} <{$user->email}>\n";
echo "\nGenerated log payload:\n";
print_r($updated->log);
