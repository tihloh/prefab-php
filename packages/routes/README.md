# Prefab Routes

**Prefab Routes** is a lightweight, framework-independent PHP routing module designed around one rule:

> Start tiny. Add advanced routing only when the application needs it.

A two-page PHP site can use a few route definitions and `dispatch()`. A larger modular application can progressively add controllers, parameters, constraints, names, URL generation, groups, middleware, resource routes, metadata, route files, inspection and diagnostics without replacing the router or changing the basic API.

Prefab Routes is standalone. It does not require Prefab Auth, Permissions, Logs, Users, Database, Laravel, Symfony, or another framework.

## Requirements

- PHP 8.1 or newer
- Composer when installed as a package

## Installation

When the package is published:

```bash
composer require tihloh/prefab-routes
```

Then load Composer normally:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager();
```

---

# 1. The smallest useful application

For a tiny application, routing can remain this simple:

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager();

$routes->get('/', fn () => 'Home');
$routes->get('/about', fn () => 'About');
$routes->get('/hello/{name}', fn (string $name) => "Hello {$name}");

$result = $routes->dispatch();

if (is_string($result)) {
    echo $result;
}
```

You do not need groups, middleware, route names, controllers or separate route files unless the application actually needs them.

---

# 2. Route registration

Route registration tells Prefab which application URLs exist and which handler should run.

```php
$routes->get('/users', 'UserController@index');
$routes->post('/users', 'UserController@store');
$routes->put('/users/{id}', 'UserController@update');
$routes->patch('/users/{id}', 'UserController@update');
$routes->delete('/users/{id}', 'UserController@delete');
```

Supported helpers include:

```php
$routes->get($path, $handler);
$routes->post($path, $handler);
$routes->put($path, $handler);
$routes->patch($path, $handler);
$routes->delete($path, $handler);
$routes->any($path, $handler);
$routes->matchMethods(['GET', 'POST'], $path, $handler);
```

`any()` accepts the common HTTP methods. `matchMethods()` is useful when a route should accept only a specific set.

## Handler formats

Prefab intentionally supports both compact controller notation and native PHP callables.

### Compact controller syntax

```php
$routes->get('/users/{id}', 'UserController@show');
```

Configure the namespace once:

```php
$routes = new RouteManager([
    'controller_namespace' => 'App\\Controllers',
]);
```

`UserController@show` then resolves to `App\Controllers\UserController::show()`.

You may also use the fully qualified class name in the string.

### Native PHP controller syntax

```php
use App\Controllers\UserController;

$routes->get('/users/{id}', [UserController::class, 'show']);
```

This style works well with IDE navigation and class refactoring.

### Closure

```php
$routes->get('/health', function () {
    return 'OK';
});
```

Both controller styles and closures can coexist in the same application.

---

# 3. Matching

Matching answers one question:

> Which registered route corresponds to this HTTP method and path?

Given:

```php
$routes->get('/users/{id}', 'UserController@show');
```

this request:

```text
GET /users/25
```

matches `/users/{id}` and extracts:

```php
[
    'id' => '25',
]
```

You can use the matcher directly:

```php
$match = $routes->match('GET', '/users/25');

if ($match !== null) {
    $route = $match->route();
    $handler = $match->handler();
    $parameters = $match->parameters();
}
```

Most applications do not need to call `match()` themselves because `dispatch()` performs matching automatically.

---

# 4. Dispatch

`dispatch()` matches a request and executes its handler.

```php
$result = $routes->dispatch();
```

With no arguments, Prefab uses the current HTTP request method and URI.

You may also dispatch an explicit request, which is useful for testing:

```php
$result = $routes->dispatch('GET', '/users/25');
```

The flow is:

```text
HTTP request
    ↓
route matching
    ↓
parameter extraction
    ↓
middleware
    ↓
handler/controller
    ↓
result
```

If no route matches, Prefab sets HTTP status `404` and returns `null`, unless a fallback handler has been configured.

---

# 5. Route parameters

Dynamic path segments use braces:

```php
$routes->get('/users/{id}', function (string $id) {
    return "User {$id}";
});
```

Request:

```text
GET /users/25
```

passes `25` to the handler.

Multiple parameters are supported:

```php
$routes->get('/departments/{department}/users/{id}', function ($department, $id) {
    return "Department {$department}, user {$id}";
});
```

## Optional parameters

Append `?` inside the braces:

```php
$routes->get('/reports/{year?}', 'ReportController@index');
```

Both paths may match:

```text
/reports
/reports/2026
```

## Default values

```php
$routes->get('/reports/{year?}', 'ReportController@index')
    ->defaults([
        'year' => date('Y'),
    ]);
```

If `year` is absent, the default value is supplied to the handler.

---

# 6. Parameter constraints

Use `where()` when a parameter must follow a pattern.

```php
$routes->get('/users/{id}', 'UserController@show')
    ->where('id', '\\d+');
```

Now:

```text
/users/25       matches
/users/100      matches
/users/chris    does not match
```

Another example:

```php
$routes->get('/reports/{year}', 'ReportController@show')
    ->where('year', '\\d{4}');
```

Constraints use regular-expression fragments. Do not include the outer regex delimiters.

---

# 7. Named routes

A route can have a stable application-level name:

```php
$routes->get('/users/{id}', 'UserController@show')
    ->name('users.show');
```

The URL may later change while the rest of the application continues referring to `users.show`.

Find a named route:

```php
$route = $routes->find('users.show');
```

---

# 8. URL generation

Named routes can generate URLs:

```php
$routes->get('/users/{id}', 'UserController@show')
    ->name('users.show');

$url = $routes->url('users.show', [
    'id' => 25,
]);
```

Result:

```text
/users/25
```

Extra parameters become a query string:

```php
$url = $routes->url('users.show', [
    'id' => 25,
    'tab' => 'activity',
]);
```

Result:

```text
/users/25?tab=activity
```

A required path parameter that is missing causes an exception instead of silently generating an invalid URL.

---

# 9. Route groups

Groups apply common settings to several routes.

## Simple prefix

```php
$routes->group('/admin', function (RouteManager $routes) {
    $routes->get('/users', 'UserController@index');
    $routes->get('/logs', 'LogController@index');
    $routes->get('/settings', 'SettingsController@index');
});
```

These become:

```text
/admin/users
/admin/logs
/admin/settings
```

## Advanced group

```php
$routes->group([
    'prefix' => '/admin',
    'name' => 'admin.',
    'middleware' => ['auth'],
    'meta' => [
        'area' => 'administration',
    ],
], function (RouteManager $routes) {
    $routes->get('/users', 'UserController@index')
        ->name('users');

    $routes->get('/logs', 'LogController@index')
        ->name('logs');
});
```

The first route becomes:

```text
Path:        /admin/users
Name:        admin.users
Middleware:  auth
```

Groups may be nested. Child groups inherit their parent group's settings.

---

# 10. Middleware

Middleware is reusable code that runs around the final route handler.

Typical uses include authentication, maintenance mode, CSRF validation, rate limiting, request logging, tenant selection and other cross-cutting behavior.

Register middleware once:

```php
$routes->middleware('auth', function (callable $next, $match) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        return 'Unauthorized';
    }

    return $next();
});
```

Apply it to a route:

```php
$routes->get('/profile', 'ProfileController@index')
    ->middleware('auth');
```

Or to a group:

```php
$routes->group([
    'prefix' => '/admin',
    'middleware' => ['auth'],
], function (RouteManager $routes) {
    $routes->get('/users', 'UserController@index');
    $routes->get('/logs', 'LogController@index');
});
```

Middleware receives:

```php
function (callable $next, RouteMatch $match)
```

It may stop processing:

```php
return 'Unauthorized';
```

or continue to the next middleware/handler:

```php
return $next();
```

Multiple middleware execute as a pipeline:

```php
$routes->get('/admin', 'AdminController@index')
    ->middleware('maintenance', 'auth', 'audit');
```

Conceptually:

```text
request
  ↓
maintenance
  ↓
auth
  ↓
audit
  ↓
controller
```

If a route references middleware that has not been registered, Prefab throws an exception instead of silently skipping it.

---

# 11. Resource routes

CRUD-heavy applications often repeat the same route pattern. `resource()` generates the conventional routes:

```php
use App\Controllers\UserController;

$routes->resource('/users', UserController::class);
```

Equivalent route set:

| Method | Path | Controller method | Generated name |
|---|---|---|---|
| GET | `/users` | `index` | `users.index` |
| GET | `/users/{id}` | `show` | `users.show` |
| POST | `/users` | `store` | `users.store` |
| PUT | `/users/{id}` | `update` | `users.update` |
| PATCH | `/users/{id}` | `update` | `users.update.patch` |
| DELETE | `/users/{id}` | `delete` | `users.delete` |

Resource routing is optional. Explicit routes remain fully supported when an application needs a different URL or action structure.

---

# 12. Fallback routes

Define what happens when nothing matches:

```php
$routes->fallback(function () {
    http_response_code(404);
    return 'Page not found';
});
```

Or use a controller:

```php
$routes->fallback('ErrorController@notFound');
```

---

# 13. Redirects

Simple redirects can be registered as routes:

```php
$routes->redirect('/old-users', '/users');
```

Custom status:

```php
$routes->redirect('/old-page', '/new-page', 301);
```

---

# 14. Metadata

Metadata attaches application information to a route without coupling the router to a specific framework or module.

```php
$routes->get('/documents/{id}', 'DocumentController@show')
    ->meta([
        'title' => 'Document Details',
        'module' => 'documents',
        'icon' => 'file',
    ]);
```

Metadata can later be consumed by application code, adapters, documentation tools, menus, administration tools or other Prefab modules.

Read it from a route:

```php
$route->metadata();
```

---

# 15. Tags

Tags provide a lightweight way to classify routes:

```php
$routes->get('/users', 'UserController@index')
    ->tag('admin', 'users');
```

Retrieve routes by tag:

```php
$adminRoutes = $routes->byTag('admin');
```

Tags are useful for inspection, documentation, module grouping and tooling.

---

# 16. Auth, permission and log hooks

Prefab Routes remains independent from the other Prefab modules. These helpers attach neutral integration metadata:

```php
$routes->get('/documents/{id}', 'DocumentController@show')
    ->auth()
    ->permission('documents.view')
    ->log('documents.view');
```

Internally, the route records requirements such as authentication, permission and log action. Routes itself does not require `prefab-auth`, `prefab-permissions` or `prefab-logs` merely to define them.

This allows an application or integration layer to interpret the metadata when those capabilities are available.

Conceptually:

```text
request
   ↓
Prefab Routes
   ↓
auth requirement
   ↓
permission requirement
   ↓
controller
   ↓
logging integration
```

For enforcement today, middleware can consume the route metadata. Future Prefab interoperability can provide automatic adapters while keeping Routes standalone.

---

# 17. Route arrays

Routes may be supplied as arrays instead of method calls.

## Compact form

```php
$routes = new RouteManager([
    'routes' => [
        ['GET', '/', 'HomeController@index', 'home'],
        ['GET', '/users', 'UserController@index', 'users.index'],
        ['GET', '/users/{id}', 'UserController@show', 'users.show'],
    ],
]);
```

The compact positions are:

```text
[method, path, handler, optional-name]
```

## Descriptive form

```php
$routes = new RouteManager([
    'routes' => [
        [
            'method' => 'GET',
            'path' => '/users/{id}',
            'handler' => 'UserController@show',
            'name' => 'users.show',
            'middleware' => ['auth'],
            'constraints' => [
                'id' => '\\d+',
            ],
            'tags' => ['users'],
            'meta' => [
                'title' => 'User Details',
            ],
        ],
    ],
]);
```

Both forms normalize into the same `Route` objects.

---

# 18. Separate route files

Small applications can keep routes beside the bootstrap code. Larger applications can split them into files.

Example project:

```text
project/
├── public/
│   └── index.php
├── routes/
│   ├── web.php
│   ├── admin.php
│   └── api.php
├── src/
└── vendor/
```

`routes/web.php`:

```php
<?php

return [
    ['GET', '/', 'HomeController@index', 'home'],
    ['GET', '/users', 'UserController@index', 'users.index'],
    ['GET', '/users/{id}', 'UserController@show', 'users.show'],
];
```

Load the file:

```php
$routes->load(__DIR__ . '/../routes/web.php');
$routes->load(__DIR__ . '/../routes/admin.php');
$routes->load(__DIR__ . '/../routes/api.php');
```

A route file must return a PHP array. Invalid or missing files produce an exception so configuration mistakes are visible immediately.

---

# 19. Inspection and route-to-array

Prefab Routes is intentionally inspectable.

## All routes

```php
$all = $routes->all();
```

## Find by name

```php
$route = $routes->find('users.show');
```

## Find by tag

```php
$routes->byTag('admin');
```

## Convert everything to arrays

```php
$array = $routes->toArray();
```

A normalized route contains information similar to:

```php
[
    'methods' => ['GET'],
    'path' => '/users/{id}',
    'handler' => 'UserController@show',
    'name' => 'users.show',
    'middleware' => ['auth'],
    'constraints' => [
        'id' => '\\d+',
    ],
    'defaults' => [],
    'meta' => [],
    'tags' => ['users'],
]
```

Closures are represented as `[Closure]` when exported because executable closure code cannot be meaningfully serialized as a route definition.

---

# 20. Route table

`table()` returns a developer-friendly representation:

```php
print_r($routes->table());
```

The data contains:

```text
METHOD   PATH          NAME         HANDLER                 MIDDLEWARE
GET      /             home         HomeController@index
GET      /users        users.index  UserController@index
GET      /users/{id}   users.show   UserController@show     auth
POST     /users        users.store  UserController@store    auth
```

Because `table()` returns data rather than forcing HTML or CLI output, your application can render it however it wants.

---

# 21. Conflict diagnostics

Duplicate routes are easy to introduce in large applications.

```php
$routes->get('/users', 'UserController@index');
$routes->get('/users', 'OtherController@index');
```

Inspect diagnostics:

```php
$info = $routes->explain();
```

Example structure:

```php
[
    'routes' => 2,
    'named' => 0,
    'middleware' => [],
    'conflicts' => [
        'GET /users',
    ],
]
```

Current conflict diagnostics detect exact method/path duplicates. More sophisticated ambiguity detection can be added later without changing the route API.

---

# 22. Static CSS, JavaScript, fonts and images

Static files do **not** need route registration.

Recommended structure:

```text
project/
├── src/
├── routes/
├── vendor/
└── public/
    ├── index.php
    ├── css/
    │   └── app.css
    ├── js/
    │   └── app.js
    ├── fonts/
    └── images/
```

HTML can reference them normally:

```html
<link rel="stylesheet" href="/css/app.css">
<script src="/js/app.js"></script>
<img src="/images/logo.png" alt="Logo">
```

Apache/Nginx should serve existing files directly. Only application URLs should be forwarded to the PHP front controller.

Prefab Routes therefore does not require code such as:

```php
// Not necessary.
$routes->get('/css/app.css', ...);
```

This keeps routing focused on application requests and lets the web server do the job it performs best.

---

# 23. A practical small application

`public/index.php`:

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager();

$routes->get('/', fn () => '<h1>Home</h1>');
$routes->get('/hello/{name}', fn ($name) => "Hello {$name}");

$result = $routes->dispatch();

if (is_string($result)) {
    echo $result;
}
```

That is a valid Prefab Routes application. Nothing else is required by the router.

---

# 24. A practical controller application

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\HomeController;
use App\Controllers\UserController;
use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager([
    'controller_namespace' => 'App\\Controllers',
]);

$routes->get('/', 'HomeController@index')
    ->name('home');

$routes->get('/users', [UserController::class, 'index'])
    ->name('users.index');

$routes->get('/users/{id}', 'UserController@show')
    ->name('users.show')
    ->where('id', '\\d+');

$routes->post('/users', [UserController::class, 'store'])
    ->name('users.store');

$result = $routes->dispatch();

if (is_string($result)) {
    echo $result;
}
```

The two controller handler styles are deliberately interchangeable.

---

# 25. A practical larger application

```php
<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Controllers\DocumentController;
use App\Controllers\UserController;
use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager([
    'controller_namespace' => 'App\\Controllers',
]);

$routes->middleware('auth', function (callable $next, $match) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        return 'Unauthorized';
    }

    return $next();
});

$routes->get('/', 'HomeController@index')
    ->name('home');

$routes->group([
    'prefix' => '/admin',
    'name' => 'admin.',
    'middleware' => ['auth'],
], function (RouteManager $routes) {
    $routes->resource('/users', UserController::class);

    $routes->get('/documents/{id}', [DocumentController::class, 'show'])
        ->name('documents.show')
        ->where('id', '\\d+')
        ->permission('documents.view')
        ->log('documents.view')
        ->tag('documents');
});

$result = $routes->dispatch();

if (is_string($result)) {
    echo $result;
}
```

The application still uses the same `RouteManager`; it has simply opted into more capabilities.

---

# 26. Recommended application structure

Prefab Routes does not require a project layout, but this structure scales cleanly:

```text
project/
├── composer.json
├── public/
│   ├── index.php
│   ├── css/
│   ├── js/
│   └── images/
├── routes/
│   ├── web.php
│   ├── admin.php
│   └── api.php
├── src/
│   └── Controllers/
└── vendor/
```

Small projects may use fewer directories. Prefab does not require ceremony merely for consistency.

---

# 27. API quick reference

| API | Purpose |
|---|---|
| `get()` | Register a GET route |
| `post()` | Register a POST route |
| `put()` | Register a PUT route |
| `patch()` | Register a PATCH route |
| `delete()` | Register a DELETE route |
| `any()` | Register common HTTP methods |
| `matchMethods()` | Register selected HTTP methods |
| `match()` | Find the matching route without executing it |
| `dispatch()` | Match and execute a request |
| `group()` | Apply shared route configuration |
| `middleware()` | Register reusable middleware |
| `resource()` | Generate conventional CRUD routes |
| `fallback()` | Define the unmatched-request handler |
| `redirect()` | Register an HTTP redirect |
| `url()` | Generate a URL from a named route |
| `load()` | Load route definitions from a PHP file |
| `all()` | Return all Route objects |
| `find()` | Find a named route |
| `byTag()` | Return routes with a tag |
| `toArray()` | Export normalized route information |
| `table()` | Return a developer-friendly route table |
| `explain()` | Return route diagnostics |

Fluent `Route` methods:

| API | Purpose |
|---|---|
| `name()` | Assign a stable route name |
| `middleware()` | Attach middleware names |
| `where()` | Add a parameter constraint |
| `defaults()` | Add default parameter values |
| `meta()` | Attach arbitrary metadata |
| `tag()` | Classify a route |
| `auth()` | Mark authentication requirement |
| `permission()` | Attach permission metadata |
| `log()` | Attach logging/audit metadata |

---

# 28. Design philosophy

Prefab Routes deliberately avoids forcing framework-sized complexity onto small projects.

```text
Small application
    ↓
get() + post() + dispatch()

Application grows
    ↓
controllers + parameters + names

Application grows further
    ↓
groups + middleware + resources

Large modular application
    ↓
route files + metadata + permissions
+ inspection + diagnostics
```

The simple API never stops being valid:

```php
$routes->get('/health', fn () => 'OK');
```

Advanced capabilities are additive rather than mandatory.

That is the purpose of Prefab Routes: **framework-independent routing that is small when you need small and capable when you need more.**
