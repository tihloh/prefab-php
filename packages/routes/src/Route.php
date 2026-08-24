<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Routes;

/**
 * Fluent route definition.
 *
 * A Route stores normalized routing data only. It deliberately does not depend
 * on Auth, Permissions, Logs, a framework, or a service container.
 */
final class Route
{
    private ?string $name = null;
    private array $middleware = [];
    private array $constraints = [];
    private array $defaults = [];
    private array $meta = [];
    private array $tags = [];

    public function __construct(
        private array $methods,
        private string $path,
        private mixed $handler,
    ) {
        $this->methods = array_values(array_unique(array_map('strtoupper', $methods)));
        $this->path = self::normalizePath($path);
    }

    public function name(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function middleware(string|array ...$middleware): self
    {
        foreach ($middleware as $item) {
            foreach ((array) $item as $value) {
                $this->middleware[] = $value;
            }
        }
        $this->middleware = array_values(array_unique($this->middleware));
        return $this;
    }

    public function where(string $parameter, string $pattern): self
    {
        $this->constraints[$parameter] = $pattern;
        return $this;
    }

    public function defaults(array $defaults): self
    {
        $this->defaults = array_replace($this->defaults, $defaults);
        return $this;
    }

    public function meta(array $meta): self
    {
        $this->meta = array_replace($this->meta, $meta);
        return $this;
    }

    public function tag(string ...$tags): self
    {
        $this->tags = array_values(array_unique([...$this->tags, ...$tags]));
        return $this;
    }

    /** Mark this route as requiring an authenticated user. */
    public function auth(bool $required = true): self
    {
        return $this->meta(['auth' => $required]);
    }

    /** Attach a permission requirement without requiring prefab-permissions. */
    public function permission(string $permission): self
    {
        return $this->meta(['permission' => $permission]);
    }

    /** Attach an audit/log action without requiring prefab-logs. */
    public function log(string $action): self
    {
        return $this->meta(['log' => $action]);
    }

    public function methods(): array { return $this->methods; }
    public function path(): string { return $this->path; }
    public function handler(): mixed { return $this->handler; }
    public function routeName(): ?string { return $this->name; }
    public function middlewareList(): array { return $this->middleware; }
    public function constraints(): array { return $this->constraints; }
    public function defaultValues(): array { return $this->defaults; }
    public function metadata(): array { return $this->meta; }
    public function tags(): array { return $this->tags; }

    public function toArray(): array
    {
        return [
            'methods' => $this->methods,
            'path' => $this->path,
            'handler' => $this->exportHandler(),
            'name' => $this->name,
            'middleware' => $this->middleware,
            'constraints' => $this->constraints,
            'defaults' => $this->defaults,
            'meta' => $this->meta,
            'tags' => $this->tags,
        ];
    }

    private function exportHandler(): mixed
    {
        if ($this->handler instanceof \Closure) {
            return '[Closure]';
        }
        return $this->handler;
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        return $path === '//' ? '/' : $path;
    }
}
