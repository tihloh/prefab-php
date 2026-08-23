# Standalone automatic integration

Tihloh Prefab has no required Core package. Every module is standalone and can be installed/used by itself. When compatible Prefab modules are declared in the same application, they automatically cooperate through a tiny embedded capability runtime.

## Three configuration levels

Configuration is resolved per setting/resource in this order:

1. direct module configuration;
2. module-specific `PrefabConfig` configuration;
3. common `PrefabConfig` configuration;
4. compatible Prefab capability;
5. module internal sensible default;
6. clear configuration error if a required resource is still unresolved.

Example:

```php
PrefabConfig::set([
    // Level 3: common resource.
    'database' => $mainPdo,

    'modules' => [
        // Level 2: centralized Logs-specific configuration.
        'logs' => [
            'connection' => 'logs',
        ],
    ],
]);

// Level 1: direct/local configuration. Highest priority for Auth only.
$auth = new AuthManager([
    'session_key' => 'my_app_auth',
]);
```

A module-local setting never writes back to shared configuration and never changes another module.

## Resource-level precedence

Different option names may configure the same underlying resource. For example, `database` and `connection` are both ways to choose a database resource.

Prefab therefore treats this correctly:

```php
PrefabConfig::set([
    'database' => $mainPdo,
    'modules' => [
        'logs' => [
            'connection' => 'logs',
        ],
    ],
]);
```

Logs uses the module-specific `logs` connection instead of being accidentally masked by the common `$mainPdo` value.

## Capability-based cooperation

Modules publish small capabilities instead of requiring one another directly.

Typical capabilities:

```text
database
database_manager
database.connection.<name>
user_provider
actor_provider
permission_store
logger
```

Example graph:

```text
Prefab Database
  provides database + named connections
        |
        +--> Prefab Users
        |      provides user_provider
        |              |
        |              +--> Prefab Auth
        |                     provides actor_provider
        |
        +--> Prefab Permissions
        |
        +--> Prefab Logs
               provides logger
```

A module consumes a capability only when its three configuration levels did not already provide the resource.

## Declaration sequence

Each module registers itself when constructed and triggers a small configuration pass across modules declared so far. This keeps declaration order flexible.

```php
$database = new DatabaseManager();
$users = new UserManager();
$auth = new AuthManager();
$permissions = new PermissionManager();
$logs = new LogManager();
```

During configuration, modules cache the resolved references. Normal feature calls do not repeatedly scan/discover modules:

```php
$auth->attempt($email, $password);
$users->update(25, ['name' => 'Testing']);
$permissions->set('user', 25, 'documents.approve', true);
$logs->recent();
```

Applications that want an explicit startup boundary may optionally call:

```php
PrefabRuntime::ready();
```

This freezes registration of new module names after the final configuration pass. Normal Prefab applications are not required to call it.

## Transparent diagnostics

Automatic integration must remain understandable.

Each main manager exposes:

```php
$users->explain();
$auth->explain();
$permissions->explain();
$logs->explain();
$database->explain();
```

The complete runtime can be inspected with:

```php
PrefabRuntime::inspect();
```

The diagnostic result identifies modules, capability providers, priorities, metadata, and the recorded source for resolved resources without dumping capability object values.

## Conflict detection

One unambiguous capability provider is used automatically. If multiple providers have different priorities, the highest priority wins. Equal top priorities are treated as ambiguous and Prefab throws a clear configuration error instead of silently guessing.

The project can resolve the ambiguity by choosing a database/provider directly or through `PrefabConfig`.

## Standalone remains standalone

Installing only one module still installs only that module:

```bash
composer require tihloh/prefab-users
```

There is no required runtime/Core package. Each standalone package embeds `src/prefab.php`.

The repository maintains the common bootstrap from one canonical development file:

```text
tools/prefab-bootstrap.php
```

Before release, maintainers can synchronize the embedded copies with:

```bash
php tools/sync-prefab-bootstrap.php
```

This synchronization tool is not part of application startup and is not required by package consumers.
