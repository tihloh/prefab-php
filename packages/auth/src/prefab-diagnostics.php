<?php

declare(strict_types=1);

namespace Tihloh\Prefab {
    use BadMethodCallException;
    use RuntimeException;
    use Throwable;

    /** Shared runtime and developer diagnostics state for standalone Prefab packages. */
    if (!class_exists(PrefabRuntime::class, false)) {
        final class PrefabRuntime
        {
            private static array $modules = [];
            private static array $capabilities = [];
            private static array $extensions = [];
            private static array $resolutions = [];
            private static array $traceStack = [];
            private static array $traceHistory = [];
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
                self::traceStep('module.register', ['module' => $name]);
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
                self::$capabilities[$capability][$provider] = compact('value', 'provider', 'priority', 'meta');
                self::traceStep('capability.provide', ['capability' => $capability, 'provider' => $provider]);
            }

            public static function resolveEntry(string $capability, ?string $provider = null): ?array
            {
                $entries = self::$capabilities[$capability] ?? [];
                if ($provider !== null) {
                    $entry = $entries[$provider] ?? null;
                    self::traceStep('capability.resolve', ['capability' => $capability, 'found' => $entry !== null]);
                    return $entry;
                }
                if (!$entries) {
                    self::traceStep('capability.resolve', ['capability' => $capability, 'found' => false]);
                    return null;
                }
                uasort($entries, fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
                $top = reset($entries);
                $ties = array_filter($entries, fn(array $entry): bool => $entry['priority'] === $top['priority']);
                if (count($ties) > 1) {
                    throw new RuntimeException("Ambiguous Prefab capability '{$capability}'.");
                }
                self::traceStep('capability.resolve', ['capability' => $capability, 'found' => true]);
                return $top;
            }

            public static function resolve(string $capability, ?string $provider = null): mixed
            {
                return self::resolveEntry($capability, $provider)['value'] ?? null;
            }

            public static function extend(string $target, string $method, callable $handler, string $provider, int $priority = 0, array $meta = []): void
            {
                self::$extensions[$target][$method][$provider] = compact('handler', 'provider', 'priority', 'meta');
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
                    if ($class !== $type && !is_a($class, $type, true)) continue;
                    foreach (($methods[$method] ?? []) as $provider => $entry) {
                        $entries[$type . ':' . $provider] = $entry;
                    }
                }
                if (!$entries) return null;
                uasort($entries, fn(array $a, array $b): int => $b['priority'] <=> $a['priority']);
                $top = reset($entries);
                $ties = array_filter($entries, fn(array $entry): bool => $entry['priority'] === $top['priority']);
                if (count($ties) > 1) {
                    throw new RuntimeException("Ambiguous Prefab fluent extension '{$method}' for '{$class}'.");
                }
                return $top;
            }

            public static function callExtension(object $target, string $method, array $arguments = []): mixed
            {
                $entry = self::extensionEntry($target, $method);
                if (!$entry) throw new BadMethodCallException("Prefab fluent extension '{$method}' is unavailable.");
                return ($entry['handler'])($target, ...$arguments);
            }

            public static function extensionMethods(object|string $target): array
            {
                $class = is_object($target) ? $target::class : $target;
                $result = [];
                foreach (self::$extensions as $type => $methods) {
                    if ($class !== $type && !is_a($class, $type, true)) continue;
                    foreach (array_keys($methods) as $method) {
                        if (self::hasExtension($class, $method)) $result[] = $method;
                    }
                }
                return array_values(array_unique($result));
            }

            public static function recordResolution(string $module, string $resource, string $source, array $details = []): void
            {
                self::$resolutions[$module][$resource] = ['source' => $source, ...$details];
                self::traceStep('resource.resolve', ['resource' => $resource, 'source' => $source]);
            }

            /** Show the execution story for one module. Structured configuration lives in explainData(). */
            public static function explain(string $module): void
            {
                if (class_exists(PrefabDebugRenderer::class, false)) {
                    PrefabDebugRenderer::explain($module);
                    return;
                }
                $traces = array_values(array_filter(
                    self::$traceHistory,
                    fn(array $trace): bool => ($trace['module'] ?? '') === $module || self::containsModule($trace, $module),
                ));
                self::renderFallback($traces, true, 'Prefab ' . ucfirst($module) . ' Execution');
            }

            public static function explainData(string $module): array
            {
                return self::$resolutions[$module] ?? [];
            }

            public static function inspect(): array
            {
                return [
                    'ready' => self::$ready,
                    'modules' => array_keys(self::$modules),
                    'capabilities' => array_keys(self::$capabilities),
                    'extensions' => array_keys(self::$extensions),
                    'resolutions' => self::$resolutions,
                    'trace_count' => count(self::$traceHistory),
                ];
            }

            public static function configureAll(): void
            {
                if (self::$configuring) return;
                self::$configuring = true;
                try {
                    foreach (self::$modules as $module) {
                        if (method_exists($module, 'prefabConfigure')) $module->prefabConfigure();
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
                self::traceStep('log.emit', ['action' => $event['action'] ?? $event['event'] ?? null]);
                if ($logger && method_exists($logger, 'record')) $logger->record($event);
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
                    'children' => [],
                ];
            }

            public static function traceStep(string $event, array $details = []): void
            {
                if (!self::$traceStack) return;
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
                self::finishTrace('failed', ['error' => self::shortClass($error::class), 'message' => $error->getMessage()]);
            }

            public static function lastTrace(): ?array
            {
                return self::$lastTrace;
            }

            public static function traceHistory(): array
            {
                return self::$traceHistory;
            }

            public static function renderTrace(bool $detailed = false): void
            {
                if (class_exists(PrefabDebugRenderer::class, false)) {
                    PrefabDebugRenderer::trace($detailed);
                    return;
                }
                self::renderFallback(self::$traceHistory, $detailed, $detailed ? 'PREFAB TRACE · DETAILED' : 'PREFAB TRACE');
            }

            public static function reset(): void
            {
                self::$modules = self::$capabilities = self::$extensions = self::$resolutions = [];
                self::$traceStack = self::$traceHistory = [];
                self::$lastTrace = null;
                self::$configuring = self::$ready = false;
            }

            private static function finishTrace(string $status, array $details): void
            {
                if (!self::$traceStack) return;
                $trace = array_pop(self::$traceStack);
                $trace['status'] = $status;
                $trace['details'] = self::safeContext($details);
                $trace['duration_ms'] = round((microtime(true) - $trace['started_at']) * 1000, 3);
                unset($trace['started_at']);

                if (self::$traceStack) {
                    $parent = array_key_last(self::$traceStack);
                    self::$traceStack[$parent]['children'][] = $trace;
                } else {
                    self::$traceHistory[] = $trace;
                }
                self::$lastTrace = $trace;
            }

            private static function safeContext(array $context): array
            {
                $safe = [];
                foreach ($context as $key => $value) {
                    $name = strtolower((string)$key);
                    if (preg_match('/password|passwd|secret|token|authorization|cookie/', $name)) {
                        $safe[$key] = '[redacted]';
                    } elseif (preg_match('/path|class|adapter|namespace|file/', $name)) {
                        continue;
                    } elseif ($name === 'sql' && is_string($value)) {
                        $safe[$key] = strlen($value) > 2000 ? substr($value, 0, 1997) . '...' : $value;
                    } else {
                        $safe[$key] = self::summarize($value);
                    }
                }
                return $safe;
            }

            private static function summarize(mixed $value): mixed
            {
                if (is_object($value)) return self::shortClass($value::class);
                if (is_array($value)) {
                    if (array_is_list($value)) return count($value) . ' items';
                    if (count($value) <= 8) return implode(', ', array_keys($value));
                    return count($value) . ' fields';
                }
                if (is_resource($value)) return get_resource_type($value);
                if (is_string($value)) return strlen($value) > 120 ? substr($value, 0, 117) . '...' : $value;
                return $value;
            }

            private static function shortClass(string $class): string
            {
                $parts = explode('\\', $class);
                return (string)end($parts);
            }

            private static function containsModule(array $trace, string $module): bool
            {
                foreach ($trace['children'] ?? [] as $child) {
                    if (($child['module'] ?? '') === $module || self::containsModule($child, $module)) return true;
                }
                return false;
            }

            /** Minimal fallback for environments loading diagnostics without the UI renderer. */
            private static function renderFallback(array $traces, bool $detailed, string $title): void
            {
                $lines = [$title, ''];
                if (!$traces) $lines[] = 'No traced Prefab operation matched.';
                foreach ($traces as $trace) {
                    $status = ($trace['status'] ?? '') === 'success' ? 'OK' : 'FAILED';
                    $lines[] = ucfirst((string)($trace['module'] ?? 'prefab')) . '::' . ($trace['operation'] ?? 'operation') . ' [' . $status . ']';
                }
                $text = implode("\n", $lines);
                if (PHP_SAPI === 'cli') echo $text . PHP_EOL;
                else echo '<pre>' . htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</pre>';
            }
        }
    }
}
