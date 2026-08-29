<?php

declare(strict_types=1);

namespace Tihloh\Prefab {
    use BadMethodCallException;
    use RuntimeException;
    use Throwable;

    if (!class_exists(PrefabConfig::class, false)) {
        final class PrefabConfig
        {
            private static array $common = [];
            private static array $modules = [];

            public static function set(array $config): void
            {
                $modules = $config['modules'] ?? [];
                unset($config['modules']);
                self::$common = array_replace_recursive(self::$common, $config);
                if (is_array($modules)) {
                    self::$modules = array_replace_recursive(self::$modules, $modules);
                }
            }

            public static function get(string $key, mixed $default = null): mixed
            {
                return array_key_exists($key, self::$common) ? self::$common[$key] : $default;
            }

            public static function module(string $module, string $key, mixed $default = null): mixed
            {
                return self::resolve($module, $key, default: $default)['value'];
            }

            public static function resolve(string $module, string $key, array $local = [], mixed $default = null): array
            {
                if (array_key_exists($key, $local)) {
                    return ['value' => $local[$key], 'source' => 'module-local'];
                }
                if (isset(self::$modules[$module]) && array_key_exists($key, self::$modules[$module])) {
                    return ['value' => self::$modules[$module][$key], 'source' => 'prefab-config-module'];
                }
                if (array_key_exists($key, self::$common)) {
                    return ['value' => self::$common[$key], 'source' => 'prefab-config-common'];
                }
                return ['value' => $default, 'source' => 'internal-default'];
            }

            public static function moduleConfig(string $module): array
            {
                return array_replace_recursive(self::$common, self::$modules[$module] ?? []);
            }

            public static function moduleOnly(string $module): array
            {
                return self::$modules[$module] ?? [];
            }

            public static function reset(): void
            {
                self::$common = [];
                self::$modules = [];
            }
        }
    }

    if (!class_exists(PrefabRuntime::class, false)) {
        final class PrefabRuntime
        {
            private static array $modules = [];
            private static array $capabilities = [];
            private static array $extensions = [];
            private static array $resolutions = [];
            private static array $traceStack = [];
            private static ?array $lastTrace = null;
            private static bool $configuring = false;
            private static bool $ready = false;

            public static function register(string $name, object $module): void
            {
                if (self::$ready && !isset(self::$modules[$name])) {
                    throw new RuntimeException("Prefab runtime is ready; module '{$name}' cannot be registered afterward.");
                }
                unset(self::$modules[$name]);
                self::$modules = [$name => $module] + self::$modules;
                self::traceStep('module.register', ['module' => $name, 'class' => $module::class]);
                self::configureAll();
            }

            public static function get(string $name): ?object
            {
                return self::$modules[$name] ?? null;
            }

            public static function provide(string $capability, mixed $value, string $provider, int $priority = 0, array $meta = []): void
            {
                if ($value === null) {
                    unset(self::$capabilities[$capability][$provider]);
                    return;
                }
                self::$capabilities[$capability][$provider] = [
                    'value' => $value,
                    'provider' => $provider,
                    'priority' => $priority,
                    'meta' => $meta,
                ];
                self::traceStep('capability.provide', ['capability' => $capability, 'provider' => $provider]);
            }

            public static function resolveEntry(string $capability, ?string $provider = null): ?array
            {
                $entries = self::$capabilities[$capability] ?? [];
                if ($provider !== null) {
                    $entry = $entries[$provider] ?? null;
                    self::traceStep('capability.resolve', [
                        'capability' => $capability,
                        'provider' => $entry['provider'] ?? $provider,
                        'found' => $entry !== null,
                    ]);
                    return $entry;
                }
                if (!$entries) {
                    self::traceStep('capability.resolve', ['capability' => $capability, 'found' => false]);
                    return null;
                }
                uasort($entries, fn ($a, $b) => $b['priority'] <=> $a['priority']);
                $top = reset($entries);
                $ties = array_filter($entries, fn ($entry) => $entry['priority'] === $top['priority']);
                if (count($ties) > 1) {
                    throw new RuntimeException("Ambiguous Prefab capability '{$capability}'.");
                }
                self::traceStep('capability.resolve', [
                    'capability' => $capability,
                    'provider' => $top['provider'],
                    'found' => true,
                ]);
                return $top;
            }

            public static function resolve(string $capability, ?string $provider = null): mixed
            {
                return self::resolveEntry($capability, $provider)['value'] ?? null;
            }

            public static function extend(string $target, string $method, callable $handler, string $provider, int $priority = 0, array $meta = []): void
            {
                self::$extensions[$target][$method][$provider] = [
                    'handler' => $handler,
                    'provider' => $provider,
                    'priority' => $priority,
                    'meta' => $meta,
                ];
            }

            public static function hasExtension(object|string $target, string $method): bool
            {
                return self::extensionEntry($target, $method) !== null;
            }

            public static function extensionEntry(object|string $target, string $method): ?array
            {
                $class = is_object($target) ? $target::class : $target;
                $entries = [];
                foreach (self::$extensions as $type => $methods) {
                    if ($class !== $type && !is_a($class, $type, true)) {
                        continue;
                    }
                    foreach (($methods[$method] ?? []) as $provider => $entry) {
                        $entries[$type . ':' . $provider] = $entry;
                    }
                }
                if (!$entries) {
                    return null;
                }
                uasort($entries, fn ($a, $b) => $b['priority'] <=> $a['priority']);
                $top = reset($entries);
                $ties = array_filter($entries, fn ($entry) => $entry['priority'] === $top['priority']);
                if (count($ties) > 1) {
                    throw new RuntimeException("Ambiguous Prefab fluent extension '{$method}' for '{$class}'.");
                }
                return $top;
            }

            public static function callExtension(object $target, string $method, array $arguments = []): mixed
            {
                $entry = self::extensionEntry($target, $method);
                if (!$entry) {
                    $class = $target::class;
                    throw new BadMethodCallException("Prefab fluent extension '{$method}' is unavailable for {$class}.");
                }
                return ($entry['handler'])($target, ...$arguments);
            }

            public static function extensionMethods(object|string $target): array
            {
                $class = is_object($target) ? $target::class : $target;
                $result = [];
                foreach (self::$extensions as $type => $methods) {
                    if ($class === $type || is_a($class, $type, true)) {
                        foreach (array_keys($methods) as $method) {
                            if (self::hasExtension($class, $method)) {
                                $result[] = $method;
                            }
                        }
                    }
                }
                return array_values(array_unique($result));
            }

            public static function recordResolution(string $module, string $resource, string $source, array $details = []): void
            {
                self::$resolutions[$module][$resource] = ['source' => $source, ...$details];
                self::traceStep('resource.resolve', [
                    'module' => $module,
                    'resource' => $resource,
                    'source' => $source,
                ]);
            }

            public static function explain(string $module): array
            {
                return self::$resolutions[$module] ?? [];
            }

            public static function inspect(): array
            {
                return [
                    'ready' => self::$ready,
                    'modules' => array_map(fn ($module) => $module::class, self::$modules),
                    'capabilities' => array_keys(self::$capabilities),
                    'extensions' => array_keys(self::$extensions),
                    'resolutions' => self::$resolutions,
                ];
            }

            public static function configureAll(): void
            {
                if (self::$configuring) {
                    return;
                }
                self::$configuring = true;
                try {
                    foreach (self::$modules as $module) {
                        if (method_exists($module, 'prefabConfigure')) {
                            $module->prefabConfigure();
                        }
                    }
                } finally {
                    self::$configuring = false;
                }
            }

            public static function ready(): void
            {
                self::configureAll();
                self::$ready = true;
            }

            public static function isReady(): bool
            {
                return self::$ready;
            }

            public static function emitLog(array $event): void
            {
                $logger = self::resolve('logger');
                self::traceStep('log.emit', [
                    'action' => $event['action'] ?? $event['event'] ?? null,
                    'provider' => $logger ? $logger::class : null,
                ]);
                if ($logger && method_exists($logger, 'record')) {
                    $logger->record($event);
                }
            }

            public static function actorId(): int|string|null
            {
                $actor = self::resolve('actor_provider');
                $id = ($actor && method_exists($actor, 'id')) ? $actor->id() : null;
                self::traceStep('actor.resolve', ['id' => $id]);
                return $id;
            }

            public static function traceCall(string $module, string $operation, array $context, callable $callback): mixed
            {
                self::traceStart($module, $operation, $context);
                try {
                    $result = $callback();
                    self::traceEnd(['result' => self::summarize($result)]);
                    return $result;
                } catch (Throwable $error) {
                    self::traceFail($error);
                    throw $error;
                }
            }

            public static function traceStart(string $module, string $operation, array $context = []): void
            {
                self::$traceStack[] = [
                    'module' => $module,
                    'operation' => $operation,
                    'started_at' => microtime(true),
                    'context' => self::safeContext($context),
                    'steps' => [],
                ];
            }

            public static function traceStep(string $event, array $details = []): void
            {
                if (!self::$traceStack) {
                    return;
                }
                $index = array_key_last(self::$traceStack);
                $started = self::$traceStack[$index]['started_at'];
                self::$traceStack[$index]['steps'][] = [
                    'event' => $event,
                    'at_ms' => round((microtime(true) - $started) * 1000, 3),
                    'details' => self::safeContext($details),
                ];
            }

            public static function traceEnd(array $details = []): void
            {
                self::finishTrace('success', $details);
            }

            public static function traceFail(Throwable $error): void
            {
                self::finishTrace('failed', [
                    'error' => $error::class,
                    'message' => $error->getMessage(),
                ]);
            }

            public static function lastTrace(): ?array
            {
                return self::$lastTrace;
            }

            public static function renderTrace(bool $detailed = false): void
            {
                $trace = self::$lastTrace;
                if ($trace === null) {
                    self::writeTrace("Prefab Trace\nNo Prefab operation has been traced yet.");
                    return;
                }

                $status = $trace['status'] === 'success' ? 'OK' : 'FAILED';
                $lines = [
                    'Prefab Trace',
                    $trace['module'] . '::' . $trace['operation'] . ' [' . $status . ']',
                ];

                foreach ($trace['context'] as $key => $value) {
                    $lines[] = '  ' . $key . ': ' . self::displayValue($value);
                }

                if ($detailed) {
                    foreach ($trace['steps'] as $step) {
                        $line = sprintf('  %7.3f ms  %s', $step['at_ms'], $step['event']);
                        if ($step['details']) {
                            $pairs = [];
                            foreach ($step['details'] as $key => $value) {
                                $pairs[] = $key . '=' . self::displayValue($value);
                            }
                            $line .= '  ' . implode(', ', $pairs);
                        }
                        $lines[] = $line;
                    }
                }

                foreach ($trace['details'] as $key => $value) {
                    $lines[] = '  ' . $key . ': ' . self::displayValue($value);
                }
                $lines[] = '  duration: ' . number_format($trace['duration_ms'], 3) . ' ms';

                self::writeTrace(implode("\n", $lines));
            }

            public static function reset(): void
            {
                self::$modules = self::$capabilities = self::$extensions = self::$resolutions = [];
                self::$traceStack = [];
                self::$lastTrace = null;
                self::$configuring = self::$ready = false;
            }

            private static function finishTrace(string $status, array $details): void
            {
                if (!self::$traceStack) {
                    return;
                }
                $trace = array_pop(self::$traceStack);
                $trace['status'] = $status;
                $trace['details'] = self::safeContext($details);
                $trace['duration_ms'] = round((microtime(true) - $trace['started_at']) * 1000, 3);
                unset($trace['started_at']);

                if (self::$traceStack) {
                    self::traceStep('nested.' . $trace['module'] . '.' . $trace['operation'], [
                        'status' => $status,
                        'duration_ms' => $trace['duration_ms'],
                    ]);
                }
                self::$lastTrace = $trace;
            }

            private static function summarize(mixed $value): mixed
            {
                if (is_object($value)) {
                    return $value::class;
                }
                if (is_array($value)) {
                    return 'array(' . count($value) . ')';
                }
                if (is_resource($value)) {
                    return get_resource_type($value);
                }
                if (is_string($value)) {
                    return strlen($value) > 80 ? substr($value, 0, 77) . '...' : $value;
                }
                return $value;
            }

            private static function safeContext(array $context): array
            {
                $safe = [];
                foreach ($context as $key => $value) {
                    $name = strtolower((string) $key);
                    if (preg_match('/password|passwd|secret|token|authorization|cookie/', $name)) {
                        $safe[$key] = '[redacted]';
                        continue;
                    }
                    $safe[$key] = self::summarize($value);
                }
                return $safe;
            }

            private static function displayValue(mixed $value): string
            {
                if ($value === null) {
                    return 'null';
                }
                if ($value === true) {
                    return 'true';
                }
                if ($value === false) {
                    return 'false';
                }
                if (is_array($value)) {
                    return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';
                }
                return (string) $value;
            }

            private static function writeTrace(string $text): void
            {
                if (PHP_SAPI === 'cli') {
                    echo $text . PHP_EOL;
                    return;
                }
                echo '<pre>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
            }
        }
    }

    if (!trait_exists(FluentExtensions::class, false)) {
        trait FluentExtensions
        {
            public function __call(string $method, array $arguments): mixed
            {
                return PrefabRuntime::callExtension($this, $method, $arguments);
            }

            public function hasExtension(string $method): bool
            {
                return PrefabRuntime::hasExtension($this, $method);
            }

            public function extensions(): array
            {
                return PrefabRuntime::extensionMethods($this);
            }
        }
    }
}

namespace {
    if (!function_exists('prefab_trace')) {
        function prefab_trace(bool $detailed = false): void
        {
            \Tihloh\Prefab\PrefabRuntime::renderTrace($detailed);
        }
    }
}
