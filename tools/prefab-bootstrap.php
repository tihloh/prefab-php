<?php

namespace Tihloh\Prefab;

use RuntimeException;

/*
 |--------------------------------------------------------------------------
 | Prefab interoperability bootstrap
 |--------------------------------------------------------------------------
 |
 | This is the canonical development copy of the tiny interoperability layer
 | embedded into every standalone Prefab package as src/prefab.php.
 |
 | Published modules do NOT depend on a Core package. Each package ships the
 | generated copy and guards its declarations with class_exists(), so whichever
 | Prefab package Composer loads first provides the common runtime for that PHP
 | process. Other packages simply reuse it.
 |
 */

if (!class_exists(PrefabConfig::class, false)) {
    /**
     * Stores optional common and per-module project configuration.
     *
     * Configuration has three developer-facing levels:
     * 1. direct module configuration (handled by the module constructor),
     * 2. module-specific PrefabConfig configuration,
     * 3. common PrefabConfig configuration.
     *
     * Modules may then fall back to compatible runtime capabilities and their
     * own internal defaults when a setting is still unresolved.
     */
    final class PrefabConfig
    {
        private static array $common = [];
        private static array $modules = [];

        /**
         * Merge project configuration without replacing unrelated settings.
         */
        public static function set(array $config): void
        {
            $modules = $config['modules'] ?? [];
            unset($config['modules']);

            self::$common = array_replace_recursive(
                self::$common,
                $config,
            );

            if (is_array($modules)) {
                self::$modules = array_replace_recursive(
                    self::$modules,
                    $modules,
                );
            }
        }

        /** Return one common configuration value. */
        public static function get(string $key, mixed $default = null): mixed
        {
            return array_key_exists($key, self::$common)
                ? self::$common[$key]
                : $default;
        }

        /**
         * Return a module-specific value, then common value, then default.
         */
        public static function module(
            string $module,
            string $key,
            mixed $default = null,
        ): mixed {
            return self::resolve($module, $key, default: $default)['value'];
        }

        /**
         * Resolve one setting and report which configuration level supplied it.
         *
         * @return array{value:mixed,source:string}
         */
        public static function resolve(
            string $module,
            string $key,
            array $local = [],
            mixed $default = null,
        ): array {
            if (array_key_exists($key, $local)) {
                return [
                    'value' => $local[$key],
                    'source' => 'module-local',
                ];
            }

            if (
                isset(self::$modules[$module])
                && array_key_exists($key, self::$modules[$module])
            ) {
                return [
                    'value' => self::$modules[$module][$key],
                    'source' => 'prefab-config-module',
                ];
            }

            if (array_key_exists($key, self::$common)) {
                return [
                    'value' => self::$common[$key],
                    'source' => 'prefab-config-common',
                ];
            }

            return [
                'value' => $default,
                'source' => 'internal-default',
            ];
        }

        /**
         * Return common settings merged with one module's settings.
         */
        public static function moduleConfig(string $module): array
        {
            return array_replace_recursive(
                self::$common,
                self::$modules[$module] ?? [],
            );
        }

        /** Return raw module-specific configuration for diagnostics/tests. */
        public static function moduleOnly(string $module): array
        {
            return self::$modules[$module] ?? [];
        }

        /** Clear configuration, primarily for tests and long-running workers. */
        public static function reset(): void
        {
            self::$common = [];
            self::$modules = [];
        }
    }
}

if (!class_exists(PrefabRuntime::class, false)) {
    /**
     * Tiny runtime used only for optional cooperation between Prefab modules.
     *
     * Modules register themselves and publish capabilities such as `database`,
     * `user_provider`, `actor_provider`, or `logger`. Consumers resolve those
     * capabilities during configuration and keep direct references afterward.
     * Normal feature calls therefore do not repeatedly scan/discover modules.
     */
    final class PrefabRuntime
    {
        /** @var array<string, object> */
        private static array $modules = [];

        /** @var array<string, array<string, array<string, mixed>>> */
        private static array $capabilities = [];

        /** @var array<string, array<string, array<string, mixed>>> */
        private static array $resolutions = [];

        private static bool $configuring = false;
        private static bool $ready = false;

        /**
         * Register a module and re-run declaration-time configuration.
         *
         * Declaration order remains flexible. Calling ready() optionally freezes
         * the module graph for applications that want an explicit startup end.
         */
        public static function register(string $name, object $module): void
        {
            if (self::$ready && !isset(self::$modules[$name])) {
                throw new RuntimeException(
                    "Prefab runtime is ready; module '{$name}' cannot be registered afterward.",
                );
            }

            self::$modules[$name] = $module;
            self::configureAll();
        }

        /** Return a registered module by name for compatibility/debugging. */
        public static function get(string $name): ?object
        {
            return self::$modules[$name] ?? null;
        }

        /**
         * Publish or replace a capability from one provider.
         *
         * Higher priority wins. Equal top priorities from different providers
         * are considered ambiguous and require explicit project configuration.
         */
        public static function provide(
            string $capability,
            mixed $value,
            string $provider,
            int $priority = 0,
            array $meta = [],
        ): void {
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
        }

        /**
         * Resolve a capability and include provider metadata.
         *
         * @return array{value:mixed,provider:string,priority:int,meta:array}|null
         */
        public static function resolveEntry(
            string $capability,
            ?string $preferredProvider = null,
        ): ?array {
            $providers = self::$capabilities[$capability] ?? [];

            if ($preferredProvider !== null) {
                return $providers[$preferredProvider] ?? null;
            }

            if ($providers === []) {
                return null;
            }

            uasort(
                $providers,
                fn (array $a, array $b): int => $b['priority'] <=> $a['priority'],
            );

            $top = reset($providers);
            $topPriority = $top['priority'];
            $ties = array_filter(
                $providers,
                fn (array $entry): bool => $entry['priority'] === $topPriority,
            );

            if (count($ties) > 1) {
                $names = implode(', ', array_keys($ties));

                throw new RuntimeException(
                    "Ambiguous Prefab capability '{$capability}'. "
                    . "Providers with equal priority: {$names}. "
                    . 'Configure the consuming module explicitly.',
                );
            }

            return $top;
        }

        /** Resolve only the capability value. */
        public static function resolve(
            string $capability,
            ?string $preferredProvider = null,
        ): mixed {
            return self::resolveEntry($capability, $preferredProvider)['value'] ?? null;
        }

        /**
         * Record why a module resolved a resource/setting to aid diagnostics.
         */
        public static function recordResolution(
            string $module,
            string $resource,
            string $source,
            array $details = [],
        ): void {
            self::$resolutions[$module][$resource] = [
                'source' => $source,
                ...$details,
            ];
        }

        /** Return one module's resolved integration/configuration explanation. */
        public static function explain(string $module): array
        {
            return self::$resolutions[$module] ?? [];
        }

        /**
         * Return runtime diagnostics without exposing capability object values.
         */
        public static function inspect(): array
        {
            $capabilities = [];

            foreach (self::$capabilities as $name => $providers) {
                $capabilities[$name] = [];

                foreach ($providers as $provider => $entry) {
                    $capabilities[$name][$provider] = [
                        'priority' => $entry['priority'],
                        'meta' => $entry['meta'],
                    ];
                }
            }

            return [
                'ready' => self::$ready,
                'modules' => array_map(
                    fn (object $module): string => $module::class,
                    self::$modules,
                ),
                'capabilities' => $capabilities,
                'resolutions' => self::$resolutions,
            ];
        }

        /**
         * Re-run configuration for all currently declared modules.
         */
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

        /**
         * Optional explicit end of startup/configuration.
         *
         * Normal Prefab usage does not require this method.
         */
        public static function ready(): void
        {
            self::configureAll();
            self::$ready = true;
        }

        public static function isReady(): bool
        {
            return self::$ready;
        }

        /** Send an activity payload to the discovered logger capability. */
        public static function emitLog(array $entry): void
        {
            $logger = self::resolve('logger');

            if ($logger && method_exists($logger, 'record')) {
                $logger->record($entry);
            }
        }

        /** Return the current actor ID through the actor-provider capability. */
        public static function actorId(): int|string|null
        {
            $actor = self::resolve('actor_provider');

            return ($actor && method_exists($actor, 'id'))
                ? $actor->id()
                : null;
        }

        /** Reset runtime state for tests or long-running workers. */
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
