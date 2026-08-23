<?php

namespace Tihloh\Prefab;

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
            if (is_array($modules)) self::$modules = array_replace_recursive(self::$modules, $modules);
        }

        public static function get(string $key, mixed $default = null): mixed { return self::$common[$key] ?? $default; }
        public static function module(string $module, string $key, mixed $default = null): mixed { return self::$modules[$module][$key] ?? self::$common[$key] ?? $default; }
        public static function moduleConfig(string $module): array { return array_replace(self::$common, self::$modules[$module] ?? []); }
        public static function reset(): void { self::$common = []; self::$modules = []; }
    }
}

if (!class_exists(PrefabRuntime::class, false)) {
    final class PrefabRuntime
    {
        private static array $modules = [];
        private static bool $configuring = false;
        public static function register(string $name, object $module): void { self::$modules[$name] = $module; self::configureAll(); }
        public static function get(string $name): ?object { return self::$modules[$name] ?? null; }
        public static function configureAll(): void
        {
            if (self::$configuring) return;
            self::$configuring = true;
            try { foreach (self::$modules as $module) if (method_exists($module, 'prefabConfigure')) $module->prefabConfigure(); }
            finally { self::$configuring = false; }
        }
        public static function emitLog(array $entry): void { $logs=self::$modules['logs']??null; if($logs&&method_exists($logs,'record'))$logs->record($entry); }
        public static function actorId(): int|string|null { $auth=self::$modules['auth']??null; return ($auth&&method_exists($auth,'id'))?$auth->id():null; }
        public static function reset(): void { self::$modules=[]; }
    }
}
