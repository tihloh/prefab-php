<?php

namespace Tihloh\Prefab\Users\Services;

use PDO;
use RuntimeException;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\PrefabRuntime;
use Tihloh\Prefab\Users\Contracts\UserFactoryInterface;
use Tihloh\Prefab\Users\Contracts\UserProviderInterface;
use Tihloh\Prefab\Users\DTOs\OperationResult;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Repositories\PdoUserProvider;
use Tihloh\Prefab\Users\User\PrefabUser;

final class UserManager
{
    private ?UserProviderInterface $provider = null;
    private ?PDO $database = null;
    private array $config = [];
    private ?object $context = null;
    private ?object $events = null;
    private ?object $autoLogger = null;
    private ?object $actorProvider = null;

    public function __construct(UserProviderInterface|array|null $provider = null)
    {
        if ($provider instanceof UserProviderInterface) $this->provider = $provider;
        elseif (is_array($provider)) $this->config = $provider;
        PrefabRuntime::register('users', $this);
    }

    public function prefabConfigure(): void
    {
        if (!$this->provider) {
            $configuredProvider = $this->config['provider'] ?? PrefabConfig::module('users', 'provider');
            if ($configuredProvider instanceof UserProviderInterface) {
                $this->provider = $configuredProvider;
            } else {
                $db = $this->config['database'] ?? PrefabConfig::module('users', 'database');
                if ($db instanceof PDO) {
                    $this->database = $db;
                    $table = $this->config['table'] ?? PrefabConfig::module('users', 'table', 'users');
                    $map = $this->config['map'] ?? PrefabConfig::module('users', 'map');
                    if (!$map instanceof UserMap) $map = new UserMap((string)$table);
                    $factory = $this->config['factory'] ?? PrefabConfig::module('users', 'factory');
                    $this->provider = new PdoUserProvider(
                        $db,
                        $map,
                        $factory instanceof UserFactoryInterface ? $factory : null,
                    );
                }
            }
        }

        $this->autoLogger ??= PrefabRuntime::get('logs');
        $this->actorProvider ??= PrefabRuntime::get('auth');
    }

    public function prefabResource(string $name): mixed
    {
        return match ($name) {
            'database' => $this->database,
            'user_provider' => $this->provider,
            default => null,
        };
    }

    public function useContext(object $context): self { $this->context = $context; return $this; }
    public function useEvents(object $events): self { $this->events = $events; return $this; }
    public function find(int|string $id): ?PrefabUser { return $this->provider()->find($id); }
    public function findByEmail(string $email): ?PrefabUser { return $this->provider()->findByEmail($email); }
    public function all(int $limit = 100, int $offset = 0): array { return $this->provider()->all($limit, $offset); }

    public function create(array $data, array $context = []): OperationResult
    {
        $user = $this->provider()->create($data);
        return $this->result($user, $this->logPayload('user.created', $user->id, "User {$user->name} was created.", $this->createdChanges($user->toArray()), $context));
    }

    public function update(int|string $id, array $data, array $context = []): OperationResult
    {
        $before = $this->provider()->find($id);
        $user = $this->provider()->update($id, $data);
        return $this->result($user, $this->logPayload('user.updated', $user->id, "User {$user->name} was updated.", $this->diff($before?->toArray() ?? [], $user->toArray()), $context));
    }

    public function delete(int|string $id, array $context = []): OperationResult
    {
        $before = $this->provider()->find($id);
        $deleted = $this->provider()->delete($id);
        $name = $before?->name ?? (string)$id;
        return $this->result($deleted, $this->logPayload('user.deleted', $id, "User {$name} was deleted.", $before ? $this->deletedChanges($before->toArray()) : [], $context));
    }

    private function provider(): UserProviderInterface
    {
        if (!$this->provider) throw new RuntimeException('Prefab Users needs a provider or database configuration.');
        return $this->provider;
    }

    private function result(mixed $data, array $log): OperationResult
    {
        if ($this->events && method_exists($this->events, 'dispatch')) {
            $this->events->dispatch('prefab.log', $log);
        } elseif ($this->autoLogger && method_exists($this->autoLogger, 'record')) {
            $this->autoLogger->record($log);
        }
        return new OperationResult(data: $data, log: $log);
    }

    private function context(array $context): array
    {
        $base = ($this->context && method_exists($this->context, 'logContext')) ? $this->context->logContext() : [];
        if (!array_key_exists('actor_id', $base)) {
            $base['actor_id'] = ($this->actorProvider && method_exists($this->actorProvider, 'id')) ? $this->actorProvider->id() : null;
        }
        if (!array_key_exists('actor_type', $base) && ($base['actor_id'] ?? null) !== null) $base['actor_type'] = 'user';
        return array_replace($base, $context);
    }

    private function createdChanges(array $data): array { $r=[]; foreach($data as $k=>$v)$r[$k]=['old'=>null,'new'=>$v]; return $r; }
    private function deletedChanges(array $data): array { $r=[]; foreach($data as $k=>$v)$r[$k]=['old'=>$v,'new'=>null]; return $r; }
    private function diff(array $before,array $after):array { $r=[]; foreach(array_unique([...array_keys($before),...array_keys($after)]) as $k){$o=$before[$k]??null;$n=$after[$k]??null;if($o!==$n)$r[$k]=['old'=>$o,'new'=>$n];} return $r; }

    private function logPayload(string $action,int|string $subjectId,string $message,array $changes,array $context):array
    {
        $context=$this->context($context);
        return ['action'=>$action,'subject_type'=>'user','subject_id'=>$subjectId,'actor_type'=>$context['actor_type']??null,'actor_id'=>$context['actor_id']??null,'message'=>$message,'changes'=>$changes,'metadata'=>$context['metadata']??[],'ip_address'=>$context['ip_address']??null,'user_agent'=>$context['user_agent']??null];
    }
}
