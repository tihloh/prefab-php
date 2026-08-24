# Prefab Routes

Lightweight routing that stays simple for small PHP sites and grows with larger modular applications.

## Quick start

```php
<?php

require __DIR__ . '/vendor/autoload.php';

use Tihloh\Prefab\Routes\RouteManager;

$routes = new RouteManager([
    'controller_namespace' => 'App\\Controllers',
]);

// Both controller styles are supported.
$routes->get('/', 'HomeController@index');
$routes->get('/users/{id}', 'UserController@show');
$routes->post('/users', [App\Controllers\UserController::class, 'store']);

// Closures are ideal for tiny routes.
$routes->get('/health', fn () => 'OK');

$result = $routes->dispatch();
if (is_string($result)) {
    echo $result;
}
```

## Named routes, constraints and URLs

```php
$routes->get('/users/{id}', 'UserController@show')
    ->name('users.show')
    ->where('id', '\\d+');

echo $routes->url('users.show', ['id' => 15]); // /users/15
```

Optional parameters and defaults are supported:

```php
$routes->get('/reports/{year?}', 'ReportController@index')
    ->defaults(['year' => date('Y')]);
```

## Groups

```php
$routes->group([
    'prefix' => '/admin',
    'name' => 'admin.',
    'middleware' => ['auth'],
], function (RouteManager $routes) {
    $routes->get('/users', 'UserController@index')->name('users');
    $routes->get('/logs', 'LogController@index')->name('logs');
});
```

## Middleware

Middleware runs around the matched handler. Register it once, then reuse its name on routes or groups.

```php
$routes->middleware('auth', function (callable $next, $match) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        return 'Unauthorized';
    }

    return $next();
});

$routes->get('/profile', 'ProfileController@index')
    ->middleware('auth');
```

Middleware can stop the request by returning a response, or continue it by calling `$next()`.

## Prefab integration metadata

Routes stays standalone. These methods record neutral metadata that Auth, Permissions, Logs, framework adapters, or application middleware may enforce:

```php
$routes->post('/documents', 'DocumentController@store')
    ->auth()
    ->permission('documents.create')
    ->log('documents.create');
```

No Auth, Permissions or Logs package is required to define the route.

## Resource routes

```php
$routes->resource('/users', App\Controllers\UserController::class);
```

This generates conventional index, show, store, update and delete routes.

## Route files / arrays

`routes/web.php`:

```php
<?php

return [
    ['GET', '/', 'HomeController@index', 'home'],
    ['GET', '/users', 'UserController@index', 'users.index'],
    ['GET', '/users/{id}', 'UserController@show', 'users.show'],
];
```

Load once:

```php
$routes->load(__DIR__ . '/routes/web.php');
```

## Inspection

```php
$routes->all();
$routes->find('users.show');
$routes->byTag('admin');
$routes->toArray();
$routes->table();
$routes->explain();
```

`explain()` reports counts, registered middleware and exact method/path conflicts.

## Static resources

Prefab Routes does not force CSS, JavaScript, fonts or images through PHP. Put public assets in the web server's public/document root and let Apache/Nginx serve existing files directly. Only non-file application requests should reach the PHP front controller. This is simpler and faster than registering every asset as a route.

## Design rule

Small applications should need only `get()`, `post()` and `dispatch()`. Advanced features are additive; adopting groups, middleware, resource routes or metadata does not require rewriting simple routes.
