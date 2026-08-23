<?php

namespace Tihloh\Prefab\Core;

use Tihloh\Prefab\Core\Connections\ConnectionManager;
use Tihloh\Prefab\Core\Context\Context;
use Tihloh\Prefab\Core\Events\EventDispatcher;
use Tihloh\Prefab\Core\Modules\ModuleRegistry;

final class Prefab
{
    private ModuleRegistry $modules;
    private EventDispatcher $events;
    private Context $context;
    private ConnectionManager $connections;
    private array $config = [];
    private array $factories = [];
    private array $wired = [];

    public function __construct()
    {
        $this->modules = new ModuleRegistry();
        $this->events = new EventDispatcher();
        $this->context = new Context();
        $this->connections = new ConnectionManager();
    }

    public static function create(array $config = []): self
    {
        $self = new self();
        $self->config = $config;
        if (isset($config['db'])) $self->connections->set('default', $config['db']);
        foreach ($config['connections'] ?? [] as $name => $connection) $self->connections->set($name, $connection);
        foreach ($config['factories'] ?? [] as $name => $factory) $self->factory($name, $factory);
        foreach ($config['modules'] ?? [] as $name => $module) {
            if (is_object($module)) $self->register((string)$name, $module);
        }
        if (($config['auto_discover'] ?? true) !== false) $self->discover();
        return $self;
    }

    public function factory(string $name, callable $factory): self
    {
        $this->factories[$name] = $factory;
        return $this;
    }

    public function discover(): self
    {
        $known = [
            'users' => 'Tihloh\\Prefab\\Users\\Services\\UserManager',
            'auth' => 'Tihloh\\Prefab\\Auth\\Services\\AuthManager',
            'permissions' => 'Tihloh\\Prefab\\Permissions\\Services\\PermissionManager',
            'logs' => 'Tihloh\\Prefab\\Logs\\Services\\LogManager',
        ];
        foreach ($known as $name => $class) {
            if ($this->has($name) || !class_exists($class)) continue;
            $moduleConfig = $this->config['module_options'][$name] ?? [];
            if (($moduleConfig['enabled'] ?? true) === false) continue;
            $factory = $moduleConfig['factory'] ?? $this->factories[$name] ?? null;
            if (is_callable($factory)) {
                $module = $factory($this, $moduleConfig);
                if (is_object($module)) $this->register($name, $module);
            }
        }
        return $this;
    }

    public function register(string $name, object $module): self
    {
        $this->modules->set($name, $module);
        $this->wire();
        return $this;
    }

    public function has(string $name): bool { return $this->modules->has($name); }
    public function module(string $name): object { return $this->modules->get($name); }
    public function users(): object { return $this->module('users'); }
    public function auth(): object { return $this->module('auth'); }
    public function permissions(): object { return $this->module('permissions'); }
    public function logs(): object { return $this->module('logs'); }
    public function events(): EventDispatcher { return $this->events; }
    public function context(): Context { return $this->context; }
    public function connections(): ConnectionManager { return $this->connections; }

    private function wire(): void
    {
        if ($this->has('auth') && method_exists($this->auth(), 'id') && !isset($this->wired['actor'])) {
            $auth = $this->auth();
            $this->context->actorUsing(fn() => $auth->id());
            $this->wired['actor'] = true;
        }
        foreach (['users','auth','permissions'] as $name) {
            if (!$this->has($name)) continue;
            $module = $this->module($name);
            if (method_exists($module, 'useContext')) $module->useContext($this->context);
            if (method_exists($module, 'useEvents')) $module->useEvents($this->events);
        }
        if ($this->has('logs') && !isset($this->wired['logs'])) {
            $logs = $this->logs();
            $this->events->listen('prefab.log', static function ($payload) use ($logs): void {
                if (is_array($payload) && method_exists($logs, 'record')) $logs->record($payload);
            });
            $this->wired['logs'] = true;
        }
    }
}
