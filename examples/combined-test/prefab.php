<?php

use Tihloh\Prefab\Core\Prefab;

/*
 |--------------------------------------------------------------------------
 | Prefab initialization options
 |--------------------------------------------------------------------------
 |
 | DEFAULT: automatic discovery is ON. Installed modules are detected and
 | compatible registered/booted modules are automatically integrated.
 |
 | $prefab = Prefab::create(['db' => $pdo]);
 |
 | This example uses session-backed adapters, so its module factories are
 | supplied by index.php instead of a PDO connection.
 |
 | OPTIONAL: disable discovery when you want total manual control:
 |
 | $prefab = Prefab::create([
 |     'db' => $pdo,
 |     'auto_discover' => false,
 | ]);
 |
 | OPTIONAL: use different databases/connections:
 |
 | $prefab = Prefab::create([
 |     'connections' => [
 |         'default' => $mainPdo,
 |         'logs' => $logPdo,
 |     ],
 | ]);
 |
 | OPTIONAL: disable or customize one discovered module:
 |
 | $prefab = Prefab::create([
 |     'db' => $pdo,
 |     'module_options' => [
 |         'logs' => ['enabled' => false],
 |         // 'users' => ['factory' => fn ($prefab, $options) => new MyUserManager(...)],
 |     ],
 | ]);
 |
 | OPTIONAL: explicitly supply an already-created module. Explicit modules
 | win over discovery and remain useful for custom providers/repositories:
 |
 | $prefab = Prefab::create([
 |     'db' => $pdo,
 |     'modules' => [
 |         // 'users' => $customUsers,
 |         // 'logs' => $customLogs,
 |     ],
 | ]);
 |
 | Rule: explicit configuration > automatic discovery > defaults.
 */

return Prefab::create();
