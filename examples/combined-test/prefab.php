<?php

use Tihloh\Prefab\Core\Prefab;

/*
 |--------------------------------------------------------------------------
 | Prefab initialization options
 |--------------------------------------------------------------------------
 |
 | DEFAULT: discovery is ALWAYS automatic. Prefab detects available modules
 | and integrates compatible modules whenever they are initialized.
 |
 | $prefab = Prefab::create(['db' => $pdo]);
 |
 | Explicit initialization is only needed when a module cannot be initialized
 | from another module or from Prefab's available defaults/resources.
 |
 | This example uses session-backed adapters, so it explicitly creates those
 | adapters. Once initialized, Core discovers/integrates the modules normally.
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
 | OPTIONAL: provide a factory only for a module that needs project-specific
 | initialization. Discovery itself remains automatic:
 |
 | $prefab = Prefab::create([
 |     'db' => $pdo,
 |     'module_options' => [
 |         // 'users' => ['factory' => fn ($prefab, $options) => new MyUserManager(...)],
 |     ],
 | ]);
 |
 | OPTIONAL: explicitly supply an already-created module when no automatic
 | source/default exists or when the project requires a custom implementation:
 |
 | $prefab = Prefab::create([
 |     'modules' => [
 |         // 'users' => $customUsers,
 |         // 'logs' => $customLogs,
 |     ],
 | ]);
 |
 | Explicit initialization does NOT turn discovery off. Prefab still discovers
 | and integrates every other compatible module automatically.
 */

return Prefab::create();
