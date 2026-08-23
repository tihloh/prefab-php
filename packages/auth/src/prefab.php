<?php

namespace Tihloh\Prefab;

use RuntimeException;

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
            if (array_key_exists($key, $local)) return ['value' => $local[$key], 'source' => 'module-local'];
            if (isset(self::$modules[$module]) && array_key_exists($key, self::$modules[$module])) return ['value' => self::$modules[$module][$key], 'source' => 'prefab-config-module'];
            if (array_key_exists($key, self::$common)) return ['value' => self::$common[$key], 'source' => 'prefab-config-common'];
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
        private static array $resolutions = [];
        private static bool $configuring = false;
        private static bool $ready = false;

        public static function register(string $name, object $module): void
        {
            if (self::$ready && !isset(self::$modules[$name])) {
                throw new RuntimeException("Prefab runtime is ready; module '{$name}' cannot be registered afterward.");
            }
            self::$modules[$name] = $module;
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
            self::$capabilities[$capability][$provider] = ['value' => $value, 'provider' => $provider, 'priority' => $priority, 'meta' => $meta];
        }

        public static function resolveEntry(string $capability, ?string $preferredProvider = null): ?array
        {
            $providers = self::$capabilities[$capability] ?? [];
            if ($preferredProvider !== null) return $providers[$preferredProvider] ?? null;
            if ($providers === []) return null;
            uasort($providers, fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);
            $top = reset($providers);
            $ties = array_filter($providers, fn (array $entry): bool => $entry['priority'] === $top['priority']);
            if (count($ties) > 1) {
                throw new RuntimeException("Ambiguous Prefab capability '{$capability}'. Providers with equal priority: " . implode(', ', array_keys($ties)) . '. Configure the consuming module explicitly.');
            }
            return $top;
        }

        public static function resolve(string $capability, ?string $preferredProvider = null): mixed
        {
            return self::resolveEntry($capability, $preferredProvider)['value'] ?? null;
        }

        public static function recordResolution(string $module, string $resource, string $source, array $details = []): void
        {
            self::$resolutions[$module][$resource] = ['source' => $source, ...$details];
        }

        public static function explain(string $module): array
        {
            return self::$resolutions[$module] ?? [];
        }

        public static function inspect(): array
        {
            $capabilities = [];
            foreach (self::$capabilities as $name => $providers) {
                foreach ($providers as $provider => $entry) {
                    $capabilities[$name][$provider] = ['priority' => $entry['priority'], 'meta' => $entry['meta']];
                }
            }
            return ['ready' => self::$ready, 'modules' => array_map(fn (object $module): string => $module::class, self::$modules), 'capabilities' => $capabilities, 'resolutions' => self::$resolutions];
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

        public static function emitLog(array $entry): void
        {
            $logger = self::resolve('logger');
            if ($logger && method_exists($logger, 'record')) $logger->record($entry);
        }

        public static function actorId(): int|string|null
        {
            $actor = self::resolve('actor_provider');
            return ($actor && method_exists($actor, 'id')) ? $actor->id() : null;
        }

        public static function reset(): void
        {
            self::$modules = [];
            self::$capabilities = [];
            self::$resolutions = [];
            self::$configuring = false;
            self::$ready = false;
        }
    }
}
