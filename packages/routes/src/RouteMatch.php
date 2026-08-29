<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Routes;

use Tihloh\Prefab\PrefabRuntime;

/** Result of matching a method/path against the route table. */
final class RouteMatch
{
    public function __construct(
        private Route $route,
        private array $parameters = [],
    ) {
        PrefabRuntime::traceStart('routes', 'match', [
            'parameters' => count($parameters),
        ]);
        PrefabRuntime::traceEnd([
            'matched' => true,
        ]);
    }

    public function route(): Route { return $this->route; }
    public function parameters(): array { return $this->parameters; }
    public function handler(): mixed { return $this->route->handler(); }
}
