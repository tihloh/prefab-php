<?php

declare(strict_types=1);

namespace Tihloh\Prefab {
    /** Browser/CLI renderer for Prefab developer diagnostics. */
    if (!class_exists(PrefabDebugRenderer::class, false)) {
        final class PrefabDebugRenderer
        {
            public static function trace(bool $detailed = false): void
            {
                self::render(PrefabRuntime::traceHistory(), $detailed, $detailed ? 'PREFAB TRACE · DETAILED' : 'PREFAB TRACE', 'trace');
            }

            public static function explain(string $module): void
            {
                $traces = array_values(array_filter(PrefabRuntime::traceHistory(), fn(array $trace): bool => ($trace['module'] ?? '') === $module || self::contains($trace, $module)));
                self::render($traces, true, 'PREFAB EXPLAIN · ' . strtoupper($module), 'explain');
            }

            private static function contains(array $trace, string $module): bool
            {
                foreach ($trace['children'] ?? [] as $child) {
                    if (($child['module'] ?? '') === $module || self::contains($child, $module)) return true;
                }
                return false;
            }

            private static function render(array $traces, bool $detailed, string $title, string $kind): void
            {
                $lines = [];
                if (!$traces) $lines[] = 'No traced Prefab operation matched.';
                foreach ($traces as $i => $trace) {
                    self::node($lines, $trace, '', true, $detailed);
                    if ($i < count($traces) - 1) $lines[] = '';
                }
                $text = implode("\n", $lines);
                if (PHP_SAPI === 'cli') {
                    echo $title . PHP_EOL . PHP_EOL . $text . PHP_EOL;
                    return;
                }
                $accent = $kind === 'explain' ? '#a78bfa' : '#22d3ee';
                $safeTitle = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $safeText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                echo '<section class="prefab-debug-card" style="--prefab-accent:' . $accent . ';background:#0b1220;color:#dbeafe;border:1px solid #334155;border-left:4px solid var(--prefab-accent);border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.22);margin:16px 0;overflow:hidden;font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;color-scheme:dark;">'
                    . '<header style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:#111827;border-bottom:1px solid #334155;color:var(--prefab-accent);font:700 12px/1.4 system-ui,sans-serif;letter-spacing:.08em;">' . $safeTitle . '<span style="color:#94a3b8;font-weight:600;letter-spacing:0">development</span></header>'
                    . '<pre style="margin:0;padding:14px;overflow:auto;white-space:pre-wrap;word-break:break-word;background:transparent;color:#dbeafe;font:13px/1.65 ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;">' . $safeText . '</pre></section>';
            }

            private static function node(array &$lines, array $trace, string $prefix, bool $root, bool $detailed, bool $last = true): void
            {
                $branch = $root ? '' : ($last ? '└─ ' : '├─ ');
                $status = ($trace['status'] ?? '') === 'success' ? 'OK' : 'FAILED';
                $module = ucfirst((string)($trace['module'] ?? 'prefab'));
                $operation = (string)($trace['operation'] ?? 'operation');
                $lines[] = $prefix . $branch . $module . '::' . $operation . ' [' . $status . ']  ' . number_format((float)($trace['duration_ms'] ?? 0), 3) . ' ms';
                $next = $root ? '' : $prefix . ($last ? '   ' : '│  ');
                $items = [];
                foreach (($trace['context'] ?? []) as $k => $v) if (self::visible((string)$k, $detailed)) $items[] = ['fact', self::label((string)$k) . ': ' . self::value($v)];
                foreach (($trace['children'] ?? []) as $child) $items[] = ['child', $child];
                if ($detailed) foreach (($trace['steps'] ?? []) as $step) {
                    if (str_starts_with((string)($step['event'] ?? ''), 'module.')) continue;
                    $detail = [];
                    foreach (($step['details'] ?? []) as $k => $v) if (self::visible((string)$k, true)) $detail[] = self::label((string)$k) . '=' . self::value($v);
                    $items[] = ['fact', self::label(str_replace('.', ' ', (string)($step['event'] ?? 'step'))) . ($detail ? ': ' . implode(', ', $detail) : '') . ' (' . number_format((float)($step['at_ms'] ?? 0), 3) . ' ms)'];
                }
                foreach (($trace['details'] ?? []) as $k => $v) if ($k !== 'result' && self::visible((string)$k, $detailed)) $items[] = ['fact', self::label((string)$k) . ': ' . self::value($v)];
                if (array_key_exists('result', $trace['details'] ?? [])) $items[] = ['fact', 'Result: ' . self::value($trace['details']['result'])];
                foreach ($items as $i => $item) {
                    $isLast = $i === count($items) - 1;
                    if ($item[0] === 'child') self::node($lines, $item[1], $next, false, $detailed, $isLast);
                    else $lines[] = $next . ($isLast ? '└─ ' : '├─ ') . $item[1];
                }
            }

            private static function visible(string $key, bool $detailed): bool
            {
                $key = strtolower($key);
                if (preg_match('/password|passwd|secret|token|authorization|cookie|path|class|adapter|namespace|file|line/', $key)) return false;
                if (!$detailed && in_array($key, ['bindings','provider','actor_id','sql'], true)) return false;
                return true;
            }

            private static function label(string $key): string { return ucwords(str_replace(['_','-'], ' ', $key)); }
            private static function value(mixed $value): string
            {
                if ($value === null) return 'none';
                if ($value === true) return 'yes';
                if ($value === false) return 'no';
                if (is_array($value)) return implode(', ', array_map('strval', $value));
                return (string)$value;
            }
        }
    }
}

namespace {
    if (!function_exists('prefab_trace')) {
        function prefab_trace(): void { \Tihloh\Prefab\PrefabDebugRenderer::trace(false); }
    }
    if (!function_exists('prefab_trace_detailed')) {
        function prefab_trace_detailed(): void { \Tihloh\Prefab\PrefabDebugRenderer::trace(true); }
    }
}
