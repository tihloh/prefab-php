<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Routes;

use RuntimeException;

/**
 * Framework-independent route registry, matcher and lightweight dispatcher.
 *
 * Small apps may use only get()/post()/dispatch(). Larger applications can
 * progressively add names, groups, constraints, resources and middleware.
 */
final class RouteManager
{
    /** @var Route[] */
    private array $routes = [];
    private array $named = [];
    private array $middleware = [];
    private array $groupStack = [];
    private mixed $fallback = null;
    private string $controllerNamespace;

    public function __construct(array $config = [])
    {
        $this->controllerNamespace = trim((string) ($config['controller_namespace'] ?? ''), '\\');

        foreach ($config['middleware'] ?? [] as $name => $handler) {
            $this->middleware((string) $name, $handler);
        }
        foreach ($config['routes'] ?? [] as $definition) {
            $this->addArray($definition);
        }
    }

    public function get(string $path, mixed $handler): Route { return $this->add(['GET'], $path, $handler); }
    public function post(string $path, mixed $handler): Route { return $this->add(['POST'], $path, $handler); }
    public function put(string $path, mixed $handler): Route { return $this->add(['PUT'], $path, $handler); }
    public function patch(string $path, mixed $handler): Route { return $this->add(['PATCH'], $path, $handler); }
    public function delete(string $path, mixed $handler): Route { return $this->add(['DELETE'], $path, $handler); }
    public function any(string $path, mixed $handler): Route { return $this->add(['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'], $path, $handler); }
    public function matchMethods(array $methods, string $path, mixed $handler): Route { return $this->add($methods, $path, $handler); }

    public function add(array $methods, string $path, mixed $handler): Route
    {
        $group = $this->currentGroup();
        $route = new Route($methods, ($group['prefix'] ?? '') . '/' . ltrim($path, '/'), $handler);

        if (!empty($group['middleware'])) {
            $route->middleware($group['middleware']);
        }
        if (!empty($group['name'])) {
            // The prefix is applied when the route is registered by refreshNames().
            $route->meta(['_name_prefix' => $group['name']]);
        }
        if (!empty($group['meta'])) {
            $route->meta($group['meta']);
        }

        $this->routes[] = $route;
        return $route;
    }

    /**
     * Group routes with inherited prefix, middleware, name prefix and metadata.
     */
    public function group(string|array $options, callable $callback): self
    {
        if (is_string($options)) {
            $options = ['prefix' => $options];
        }

        $parent = $this->currentGroup();
        $this->groupStack[] = [
            'prefix' => rtrim(($parent['prefix'] ?? '') . '/' . trim((string) ($options['prefix'] ?? ''), '/'), '/'),
            'name' => ($parent['name'] ?? '') . ($options['name'] ?? ''),
            'middleware' => [...($parent['middleware'] ?? []), ...(array) ($options['middleware'] ?? [])],
            'meta' => array_replace($parent['meta'] ?? [], $options['meta'] ?? []),
        ];

        try {
            $callback($this);
        } finally {
            array_pop($this->groupStack);
        }
        return $this;
    }

    /** Register reusable middleware by a friendly name. */
    public function middleware(string $name, callable $handler): self
    {
        $this->middleware[$name] = $handler;
        return $this;
    }

    public function fallback(mixed $handler): self
    {
        $this->fallback = $handler;
        return $this;
    }

    public function redirect(string $from, string $to, int $status = 302): Route
    {
        return $this->any($from, static function () use ($to, $status): never {
            header('Location: ' . $to, true, $status);
            exit;
        });
    }

    /** Generate conventional CRUD routes. */
    public function resource(string $path, string $controller): self
    {
        $base = '/' . trim($path, '/');
        $name = trim($base, '/');
        $this->get($base, [$controller, 'index'])->name($name . '.index');
        $this->get($base . '/{id}', [$controller, 'show'])->name($name . '.show');
        $this->post($base, [$controller, 'store'])->name($name . '.store');
        $this->put($base . '/{id}', [$controller, 'update'])->name($name . '.update');
        $this->patch($base . '/{id}', [$controller, 'update'])->name($name . '.update.patch');
        $this->delete($base . '/{id}', [$controller, 'delete'])->name($name . '.delete');
        return $this;
    }

    /** Load a PHP route file returning an array of definitions. */
    public function load(string $file): self
    {
        if (!is_file($file)) {
            throw new RuntimeException("Route file not found: {$file}");
        }
        $definitions = require $file;
        if (!is_array($definitions)) {
            throw new RuntimeException("Route file must return an array: {$file}");
        }
        foreach ($definitions as $definition) {
            $this->addArray($definition);
        }
        return $this;
    }

    public function match(string $method, string $path): ?RouteMatch
    {
        $method = strtoupper($method);
        $path = $this->normalizeRequestPath($path);

        foreach ($this->routes as $route) {
            if (!in_array($method, $route->methods(), true) && !($method === 'HEAD' && in_array('GET', $route->methods(), true))) {
                continue;
            }

            [$regex, $names] = $this->compile($route);
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }

            $parameters = $route->defaultValues();
            foreach ($names as $name) {
                if (isset($matches[$name]) && $matches[$name] !== '') {
                    $parameters[$name] = rawurldecode($matches[$name]);
                }
            }
            return new RouteMatch($route, $parameters);
        }
        return null;
    }

    /**
     * Match and execute the current request (or supplied method/path).
     * Middleware receives ($next, $match) and may return early or call $next().
     */
    public function dispatch(?string $method = null, ?string $path = null): mixed
    {
        $method ??= $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $path ??= parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $match = $this->match($method, $path);

        if ($match === null) {
            if ($this->fallback !== null) {
                return $this->invoke($this->fallback, []);
            }
            if (!headers_sent()) {
                http_response_code(404);
            }
            return null;
        }

        $next = fn () => $this->invoke($match->handler(), $match->parameters());
        foreach (array_reverse($match->route()->middlewareList()) as $name) {
            if (!isset($this->middleware[$name])) {
                throw new RuntimeException("Route middleware '{$name}' is not registered.");
            }
            $middleware = $this->middleware[$name];
            $previous = $next;
            $next = fn () => $middleware($previous, $match);
        }
        return $next();
    }

    public function url(string $name, array $parameters = []): string
    {
        $this->refreshNames();
        if (!isset($this->named[$name])) {
            throw new RuntimeException("Named route not found: {$name}");
        }

        $path = $this->named[$name]->path();
        $used = [];
        $path = preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)(\?)?\}/', function (array $m) use ($parameters, &$used): string {
            $key = $m[1];
            if (array_key_exists($key, $parameters)) {
                $used[$key] = true;
                return rawurlencode((string) $parameters[$key]);
            }
            if (($m[2] ?? '') === '?') {
                return '';
            }
            throw new RuntimeException("Missing route parameter: {$key}");
        }, $path) ?? $path;

        $path = preg_replace('#/+#', '/', $path) ?: '/';
        $query = array_diff_key($parameters, $used);
        return $path . ($query ? '?' . http_build_query($query) : '');
    }

    /** @return Route[] */
    public function all(): array { return $this->routes; }

    public function find(string $name): ?Route
    {
        $this->refreshNames();
        return $this->named[$name] ?? null;
    }

    public function byTag(string $tag): array
    {
        return array_values(array_filter($this->routes, static fn (Route $route) => in_array($tag, $route->tags(), true)));
    }

    public function toArray(): array
    {
        return array_map(static fn (Route $route) => $route->toArray(), $this->routes);
    }

    /** Human/developer friendly route table for diagnostics. */
    public function table(): array
    {
        $this->refreshNames();
        return array_map(function (Route $route): array {
            return [
                'method' => implode('|', $route->methods()),
                'path' => $route->path(),
                'name' => $this->effectiveName($route),
                'handler' => $this->handlerLabel($route->handler()),
                'middleware' => implode(', ', $route->middlewareList()),
            ];
        }, $this->routes);
    }

    /** Return useful diagnostics without modifying the route table. */
    public function explain(): array
    {
        $conflicts = [];
        $seen = [];
        foreach ($this->routes as $route) {
            foreach ($route->methods() as $method) {
                $key = $method . ' ' . $route->path();
                if (isset($seen[$key])) {
                    $conflicts[] = $key;
                }
                $seen[$key] = true;
            }
        }
        return [
            'routes' => count($this->routes),
            'named' => count(array_filter($this->routes, fn (Route $r) => $this->effectiveName($r) !== null)),
            'middleware' => array_keys($this->middleware),
            'conflicts' => array_values(array_unique($conflicts)),
        ];
    }

    private function addArray(array $definition): Route
    {
        if (array_is_list($definition)) {
            [$methods, $path, $handler, $name] = array_pad($definition, 4, null);
            $route = $this->add((array) $methods, (string) $path, $handler);
            return $name ? $route->name((string) $name) : $route;
        }

        $route = $this->add((array) ($definition['methods'] ?? $definition['method'] ?? 'GET'), (string) ($definition['path'] ?? '/'), $definition['handler'] ?? null);
        if (isset($definition['name'])) $route->name((string) $definition['name']);
        if (isset($definition['middleware'])) $route->middleware((array) $definition['middleware']);
        if (isset($definition['constraints'])) foreach ($definition['constraints'] as $key => $pattern) $route->where((string) $key, (string) $pattern);
        if (isset($definition['defaults'])) $route->defaults((array) $definition['defaults']);
        if (isset($definition['meta'])) $route->meta((array) $definition['meta']);
        if (isset($definition['tags'])) $route->tag(...array_map('strval', (array) $definition['tags']));
        return $route;
    }

    private function compile(Route $route): array
    {
        $names = [];
        $offset = 0;
        $pattern = '';
        $path = $route->path();

        preg_match_all('/\{([A-Za-z_][A-Za-z0-9_]*)(\?)?\}/', $path, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $i => $full) {
            [$token, $position] = $full;
            $name = $matches[1][$i][0];
            $optional = ($matches[2][$i][0] ?? '') === '?';
            $names[] = $name;
            $before = substr($path, $offset, $position - $offset);
            $constraint = $route->constraints()[$name] ?? '[^/]+';

            if ($optional && str_ends_with($before, '/')) {
                $before = substr($before, 0, -1);
                $pattern .= preg_quote($before, '#') . '(?:/(?P<' . $name . '>' . $constraint . '))?';
            } else {
                $pattern .= preg_quote($before, '#') . '(?P<' . $name . '>' . $constraint . ')' . ($optional ? '?' : '');
            }
            $offset = $position + strlen($token);
        }
        $pattern .= preg_quote(substr($path, $offset), '#');
        return ['#^' . $pattern . '/?$#u', $names];
    }

    private function invoke(mixed $handler, array $parameters): mixed
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            if (!str_contains($class, '\\') && $this->controllerNamespace !== '') {
                $class = $this->controllerNamespace . '\\' . $class;
            }
            $handler = [$class, $method];
        }

        if (is_array($handler) && isset($handler[0], $handler[1]) && is_string($handler[0])) {
            $class = $handler[0];
            if (!str_contains($class, '\\') && $this->controllerNamespace !== '') {
                $class = $this->controllerNamespace . '\\' . $class;
            }
            $handler = [new $class(), $handler[1]];
        } elseif (is_string($handler) && class_exists($handler)) {
            $handler = new $handler();
        }

        if (!is_callable($handler)) {
            throw new RuntimeException('Route handler is not callable: ' . $this->handlerLabel($handler));
        }
        return $handler(...array_values($parameters));
    }

    private function refreshNames(): void
    {
        $this->named = [];
        foreach ($this->routes as $route) {
            $name = $this->effectiveName($route);
            if ($name !== null) {
                $this->named[$name] = $route;
            }
        }
    }

    private function effectiveName(Route $route): ?string
    {
        $name = $route->routeName();
        if ($name === null) return null;
        return (string) ($route->metadata()['_name_prefix'] ?? '') . $name;
    }

    private function currentGroup(): array
    {
        return $this->groupStack ? $this->groupStack[array_key_last($this->groupStack)] : [];
    }

    private function normalizeRequestPath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }

    private function handlerLabel(mixed $handler): string
    {
        if ($handler instanceof \Closure) return '[Closure]';
        if (is_array($handler)) {
            $class = is_object($handler[0] ?? null) ? $handler[0]::class : (string) ($handler[0] ?? '');
            return $class . '@' . ($handler[1] ?? '');
        }
        return is_string($handler) ? $handler : get_debug_type($handler);
    }
}
