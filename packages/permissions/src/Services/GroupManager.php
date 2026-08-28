<?php

namespace Tihloh\Prefab\Permissions\Services;

use PDO;
use RuntimeException;
use Tihloh\Prefab\PrefabRuntime;
use Tihloh\Prefab\Permissions\DTOs\Group;
use Tihloh\Prefab\Permissions\DTOs\OperationResult;

final class GroupManager
{
    public function __construct(
        private PDO $pdo,
        private PermissionManager $permissions,
    ) {
        if (!$this->external()) {
            PrefabRuntime::provide('group_provider', $this, 'prefab-permissions');
        }
    }

    private function external(): ?object
    {
        $entry = PrefabRuntime::resolveEntry('group_provider');
        if (!$entry || $entry['provider'] === 'prefab-permissions') return null;
        $provider = $entry['value'];
        return is_object($provider) && method_exists($provider, 'groupIdsForUser') ? $provider : null;
    }

    private function asGroup(object $group): Group
    {
        return new Group($group->id, $group->name, $group->description ?? null, (int)($group->usersCount ?? 0));
    }

    /** @return list<Group> */
    public function all(): array
    {
        if ($external = $this->external()) return array_map(fn(object $g) => $this->asGroup($g), $external->all());
        $sql = 'SELECT g.id, g.name, g.description, COUNT(ug.user_id) AS users_count FROM prefab_groups g LEFT JOIN prefab_user_groups ug ON ug.group_id = g.id GROUP BY g.id, g.name, g.description ORDER BY g.name';
        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $r) => new Group($r['id'], $r['name'], $r['description'], (int)$r['users_count']), $rows);
    }

    public function find(int|string $id): ?Group
    {
        if ($external = $this->external()) {
            $group = $external->find($id);
            return $group ? $this->asGroup($group) : null;
        }
        $stmt = $this->pdo->prepare('SELECT g.id, g.name, g.description, COUNT(ug.user_id) AS users_count FROM prefab_groups g LEFT JOIN prefab_user_groups ug ON ug.group_id = g.id WHERE g.id = :id GROUP BY g.id, g.name, g.description LIMIT 1');
        $stmt->execute(['id' => $id]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        return $r ? new Group($r['id'], $r['name'], $r['description'], (int)$r['users_count']) : null;
    }

    public function create(string $name, ?string $description = null, array $permissionOverrides = [], array $context = []): OperationResult
    {
        if ($external = $this->external()) {
            $group = $this->asGroup($external->create($name, $description));
            $id = $group->id;
        } else {
            $stmt = $this->pdo->prepare('INSERT INTO prefab_groups (name, description) VALUES (:name, :description)');
            $stmt->execute(['name' => $name, 'description' => $description]);
            $id = $this->pdo->lastInsertId();
            $group = $this->find($id) ?? throw new RuntimeException('Group could not be reloaded.');
        }
        foreach ($permissionOverrides as $permission => $value) $this->permissions->set('group', $id, (string)$permission, (bool)$value, $context);
        return new OperationResult($group, ['action'=>'group.created','subject_type'=>'group','subject_id'=>$id,'actor_id'=>$context['actor_id']??null,'message'=>"Group {$group->name} was created.",'metadata'=>['permissions'=>$permissionOverrides]]);
    }

    public function update(int|string $id, array $data, array $context = []): OperationResult
    {
        $before = $this->find($id) ?? throw new RuntimeException('Group not found.');
        if ($external = $this->external()) {
            $basic = array_intersect_key($data, array_flip(['name','description']));
            if ($basic) $external->update($id, $basic);
        } else {
            $fields=[];$params=['id'=>$id];
            foreach(['name','description'] as $field) if(array_key_exists($field,$data)){ $fields[]="$field = :$field";$params[$field]=$data[$field]; }
            if($fields) $this->pdo->prepare('UPDATE prefab_groups SET '.implode(', ',$fields).' WHERE id = :id')->execute($params);
        }
        if (array_key_exists('permissions',$data)) {
            foreach ($this->permissions->definitions() as $permission => $_) {
                if (array_key_exists($permission,$data['permissions'])) $this->permissions->set('group',$id,$permission,(bool)$data['permissions'][$permission],$context);
                else $this->permissions->clear('group',$id,$permission,$context);
            }
        }
        $after=$this->find($id)??throw new RuntimeException('Group not found after update.');
        return new OperationResult($after,['action'=>'group.updated','subject_type'=>'group','subject_id'=>$id,'actor_id'=>$context['actor_id']??null,'message'=>"Group {$after->name} was updated.",'changes'=>['name'=>['old'=>$before->name,'new'=>$after->name],'description'=>['old'=>$before->description,'new'=>$after->description]]]);
    }

    public function delete(int|string $id, array $context = []): OperationResult
    {
        $group=$this->find($id)??throw new RuntimeException('Group not found.');
        $this->permissions->clearAll('group',$id);
        if ($external=$this->external()) $external->delete($id);
        else $this->pdo->prepare('DELETE FROM prefab_groups WHERE id = :id')->execute(['id'=>$id]);
        return new OperationResult(true,['action'=>'group.deleted','subject_type'=>'group','subject_id'=>$id,'actor_id'=>$context['actor_id']??null,'message'=>"Group {$group->name} was deleted."]);
    }

    /** @return list<string> */
    public function userIds(int|string $groupId): array
    {
        if($external=$this->external()) return $external->userIds($groupId);
        $stmt=$this->pdo->prepare('SELECT user_id FROM prefab_user_groups WHERE group_id = :id ORDER BY user_id');$stmt->execute(['id'=>$groupId]);return array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @return list<string> */
    public function groupIdsForUser(int|string $userId): array
    {
        if($external=$this->external()) return $external->groupIdsForUser($userId);
        $stmt=$this->pdo->prepare('SELECT group_id FROM prefab_user_groups WHERE user_id = :id ORDER BY group_id');$stmt->execute(['id'=>(string)$userId]);return array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function syncUserGroups(int|string $userId, array $groupIds, array $context = []): OperationResult
    {
        $before=$this->groupIdsForUser($userId);
        if($external=$this->external()) $after=$external->syncUserGroups($userId,$groupIds);
        else {
            $this->pdo->beginTransaction();
            try{$this->pdo->prepare('DELETE FROM prefab_user_groups WHERE user_id = :id')->execute(['id'=>(string)$userId]);$insert=$this->pdo->prepare('INSERT INTO prefab_user_groups (user_id, group_id) VALUES (:user_id, :group_id)');foreach(array_unique($groupIds) as $groupId)$insert->execute(['user_id'=>(string)$userId,'group_id'=>$groupId]);$this->pdo->commit();}catch(\Throwable $e){$this->pdo->rollBack();throw $e;}
            $after=$this->groupIdsForUser($userId);
        }
        return new OperationResult($after,['action'=>'user.groups_synced','subject_type'=>'user','subject_id'=>$userId,'actor_id'=>$context['actor_id']??null,'message'=>"Groups for user {$userId} were updated.",'changes'=>['groups'=>['old'=>$before,'new'=>$after]]]);
    }
}
