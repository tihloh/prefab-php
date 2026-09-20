<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Live;

use InvalidArgumentException;
use ReflectionMethod;
use Tihloh\Prefab\Live\Attributes\Action;

final class ActionInvoker
{
    public function invoke(Component $component, string $method, array $params = []): mixed
    {
        if ($method === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $method) !== 1) {
            throw new InvalidArgumentException('Invalid Prefab Live action name.');
        }

        if (!method_exists($component, $method)) {
            throw new InvalidArgumentException("Prefab Live action does not exist: {$method}");
        }

        $reflection = new ReflectionMethod($component, $method);
        if (!$reflection->isPublic() || $reflection->isStatic() || $reflection->getAttributes(Action::class) === []) {
            throw new InvalidArgumentException("Prefab Live action is not exposed: {$method}");
        }

        return $reflection->invokeArgs($component, array_values($params));
    }
}
