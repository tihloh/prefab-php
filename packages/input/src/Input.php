<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Input;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Converts raw/untrusted input into normalized, typed, validated and whitelisted data.
 *
 * Supports ordinary arrays, nested dot paths, wildcard paths, JSON request bodies,
 * standard PHP multipart uploads, custom transforms, and custom validation rules.
 */
final class Input
{
    private array $customRules = [];
    private array $customTransforms = [];
    private array $attributes = [];
    private array $messages = [];

    public function __construct(private array $data = []) {}

    /**
     * Create input from explicit data and optional PHP-style $_FILES data.
     */
    public static function from(array $data, array $files = []): self
    {
        if ($files !== []) {
            $data = self::mergeRecursive($data, self::normalizeFiles($files));
        }

        return new self($data);
    }

    /**
     * Create input from the current PHP request.
     *
     * - application/json: parses php://input
     * - multipart/form-data: combines $_POST + normalized $_FILES
     * - form-urlencoded/default POST: uses $_POST
     */
    public static function fromRequest(): self
    {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];

            if ($decoded !== null && !is_array($decoded)) {
                throw new InvalidArgumentException('JSON request body must decode to an object or array.');
            }

            if ($raw !== '' && $decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                throw new InvalidArgumentException('Invalid JSON request body: ' . json_last_error_msg());
            }

            return new self($decoded ?? []);
        }

        return self::from($_POST, $_FILES);
    }

    public function data(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /** Register a validation rule. Return null when valid or an error string when invalid. */
    public function rule(string $name, callable $validator): self
    {
        $this->customRules[strtolower($name)] = $validator;
        return $this;
    }

    /** Register a value transformer/caster. */
    public function transform(string $name, callable $transformer): self
    {
        $this->customTransforms[strtolower($name)] = $transformer;
        return $this;
    }

    public function attributes(array $attributes): self
    {
        $this->attributes = array_replace($this->attributes, $attributes);
        return $this;
    }

    public function messages(array $messages): self
    {
        $this->messages = array_replace($this->messages, $messages);
        return $this;
    }

    /**
     * Process raw data against a schema.
     *
     * Wildcards such as `items.*.qty` are expanded against the current data tree.
     * If a parent field also has child schema definitions, only explicitly
     * validated child fields are copied into validated output (deep whitelist).
     */
    public function process(array $schema): InputResult
    {
        $processed = [];
        $validated = [];
        $errors = [];

        foreach ($schema as $pattern => $definition) {
            $pattern = (string) $pattern;
            $paths = str_contains($pattern, '*')
                ? $this->expandWildcardPath($pattern)
                : [$pattern];

            // A wildcard with no matching parent collection has no concrete item
            // to validate. The parent rule (e.g. items|required|array) owns absence.
            if ($paths === []) {
                continue;
            }

            foreach ($paths as $field) {
                $this->processField(
                    $field,
                    $pattern,
                    $definition,
                    $schema,
                    $processed,
                    $validated,
                    $errors,
                );
            }
        }

        return new InputResult($this->data, $processed, $validated, $errors);
    }

    private function processField(
        string $field,
        string $pattern,
        mixed $definition,
        array $schema,
        array &$processed,
        array &$validated,
        array &$errors,
    ): void {
        $operations = $this->normalizeOperations($definition);
        $value = self::getPath($this->data, $field, $exists);

        if ($this->hasOperation($operations, 'sometimes') && !$exists) {
            return;
        }

        $nullable = $this->hasOperation($operations, 'nullable');
        $fieldErrors = [];

        foreach ($operations as $operation) {
            if (is_callable($operation) && !is_string($operation)) {
                $message = $operation($field, $value, $this->data, $exists);
                if (is_string($message) && $message !== '') {
                    $fieldErrors[] = $message;
                }
                continue;
            }

            [$name, $params] = $this->parseOperation($operation);

            if (in_array($name, ['sometimes', 'nullable'], true)) {
                continue;
            }

            if ($name === 'default') {
                if (!$exists) {
                    $value = $this->literal($params[0] ?? null);
                    $exists = true;
                }
                continue;
            }

            if ($nullable && (!$exists || $value === null || $value === '')) {
                $value = null;
                $exists = true;
                break;
            }

            if ($this->isTransform($name)) {
                if ($exists) {
                    $value = $this->applyTransform($name, $value, $params, $field);

                    if (in_array($name, ['integer', 'float', 'boolean', 'string'], true)) {
                        $message = $this->validateRule($name, $field, $pattern, $value, true, $params);
                        if ($message !== null) {
                            $fieldErrors[] = $message;
                        }
                    }
                }
                continue;
            }

            $message = $this->validateRule($name, $field, $pattern, $value, $exists, $params);
            if ($message !== null) {
                $fieldErrors[] = $message;
            }
        }

        if ($exists) {
            self::setPath($processed, $field, $value);
        }

        if ($fieldErrors !== []) {
            $errors[$field] = array_values(array_unique($fieldErrors));
            return;
        }

        if ($exists && !$this->hasDeclaredChildren($pattern, $schema)) {
            self::setPath($validated, $field, $value);
        }
    }

    private function normalizeOperations(mixed $definition): array
    {
        if (is_string($definition)) {
            return $definition === '' ? [] : explode('|', $definition);
        }

        if (!is_array($definition)) {
            throw new InvalidArgumentException('Input field schema must be a string or array.');
        }

        return array_values($definition);
    }

    private function parseOperation(mixed $operation): array
    {
        if (!is_string($operation)) {
            throw new InvalidArgumentException('Input rule/transform must be a string or callable.');
        }

        [$name, $parameterString] = array_pad(explode(':', $operation, 2), 2, null);
        $params = $parameterString === null ? [] : str_getcsv($parameterString);

        return [strtolower(trim($name)), $params];
    }

    private function hasOperation(array $operations, string $target): bool
    {
        foreach ($operations as $operation) {
            if (!is_string($operation)) {
                continue;
            }

            [$name] = explode(':', $operation, 2);
            if (strtolower(trim($name)) === $target) {
                return true;
            }
        }

        return false;
    }

    private function isTransform(string $name): bool
    {
        return isset($this->customTransforms[$name]) || in_array($name, [
            'trim', 'lowercase', 'uppercase', 'null_if_empty',
            'string', 'integer', 'float', 'boolean', 'array',
        ], true);
    }

    private function applyTransform(string $name, mixed $value, array $params, string $field): mixed
    {
        if (isset($this->customTransforms[$name])) {
            return ($this->customTransforms[$name])($value, $params, $field, $this->data);
        }

        return match ($name) {
            'trim' => is_string($value) ? trim($value) : $value,
            'lowercase' => is_string($value) ? strtolower($value) : $value,
            'uppercase' => is_string($value) ? strtoupper($value) : $value,
            'null_if_empty' => $value === '' ? null : $value,
            'string' => $this->castString($value),
            'integer' => filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'boolean' => $this->castBoolean($value),
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    private function validateRule(
        string $name,
        string $field,
        string $pattern,
        mixed $value,
        bool $exists,
        array $params,
    ): ?string {
        if (isset($this->customRules[$name])) {
            $message = ($this->customRules[$name])($field, $value, $this->data, $params, $exists);
            return is_string($message) && $message !== '' ? $message : null;
        }

        $valid = match ($name) {
            'required' => $exists && !$this->isBlank($value),
            'required_if' => $this->requiredIf($field, $pattern, $value, $params),
            'required_with' => $this->requiredWith($field, $pattern, $value, $params),
            'email' => !$exists || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => !$exists || filter_var($value, FILTER_VALIDATE_URL) !== false,
            'string' => !$exists || is_string($value),
            'integer' => !$exists || is_int($value),
            'float' => !$exists || is_float($value) || is_int($value),
            'numeric' => !$exists || is_numeric($value),
            'boolean' => !$exists || is_bool($value),
            'array' => !$exists || is_array($value),
            'date' => !$exists || $this->isDate($value),
            'min' => !$exists || $this->sizeOf($value) >= (float) ($params[0] ?? 0),
            'max' => !$exists || $this->sizeOf($value) <= (float) ($params[0] ?? INF),
            'between' => !$exists || $this->between($value, $params),
            'in' => !$exists || in_array((string) $value, array_map('strval', $params), true),
            'not_in' => !$exists || !in_array((string) $value, array_map('strval', $params), true),
            'same' => !$exists || $value === $this->relatedValue($field, $pattern, (string) ($params[0] ?? '')),
            'different' => !$exists || $value !== $this->relatedValue($field, $pattern, (string) ($params[0] ?? '')),
            'regex' => !$exists || $this->matchesRegex($value, $params[0] ?? ''),
            'confirmed' => !$exists || $value === self::getPath($this->data, $field . '_confirmation', $unused),
            'distinct' => !$exists || $this->isDistinct($value),
            'file' => !$exists || ($value instanceof UploadedFile && $value->isValid()),
            'image' => !$exists || ($value instanceof UploadedFile && $value->isImage()),
            'mimes' => !$exists || ($value instanceof UploadedFile && $value->isValid() && $value->matchesExtension($params)),
            'mimetypes' => !$exists || ($value instanceof UploadedFile && $value->isValid() && $value->matchesMime($params)),
            'min_size' => !$exists || ($value instanceof UploadedFile && $value->isValid() && $value->size() >= $this->parseBytes($params[0] ?? '0')),
            'max_size' => !$exists || ($value instanceof UploadedFile && $value->isValid() && $value->size() <= $this->parseBytes($params[0] ?? (string) PHP_INT_MAX)),
            'dimensions' => !$exists || ($value instanceof UploadedFile && $this->validDimensions($value, $params)),
            default => throw new InvalidArgumentException("Unknown input rule or transform: {$name}"),
        };

        return $valid ? null : $this->message($field, $pattern, $name, $params);
    }

    private function requiredIf(string $field, string $pattern, mixed $value, array $params): bool
    {
        $otherPattern = (string) ($params[0] ?? '');
        $expected = array_slice($params, 1);
        $otherPath = $this->resolveRelatedPath($field, $pattern, $otherPattern);
        $otherValue = self::getPath($this->data, $otherPath, $exists);

        if (!$exists || !in_array((string) $otherValue, array_map('strval', $expected), true)) {
            return true;
        }

        return !$this->isBlank($value);
    }

    private function requiredWith(string $field, string $pattern, mixed $value, array $params): bool
    {
        foreach ($params as $otherPattern) {
            $otherPath = $this->resolveRelatedPath($field, $pattern, (string) $otherPattern);
            $otherValue = self::getPath($this->data, $otherPath, $exists);
            if ($exists && !$this->isBlank($otherValue)) {
                return !$this->isBlank($value);
            }
        }

        return true;
    }

    private function relatedValue(string $field, string $pattern, string $relatedPattern): mixed
    {
        $path = $this->resolveRelatedPath($field, $pattern, $relatedPattern);
        return self::getPath($this->data, $path, $unused);
    }

    /** Resolve wildcard references using the concrete indexes from the current field. */
    private function resolveRelatedPath(string $field, string $pattern, string $relatedPattern): string
    {
        if (!str_contains($relatedPattern, '*')) {
            return $relatedPattern;
        }

        $patternSegments = explode('.', $pattern);
        $fieldSegments = explode('.', $field);
        $indexes = [];

        foreach ($patternSegments as $i => $segment) {
            if ($segment === '*' && isset($fieldSegments[$i])) {
                $indexes[] = $fieldSegments[$i];
            }
        }

        $cursor = 0;
        $resolved = array_map(function (string $segment) use (&$cursor, $indexes): string {
            if ($segment !== '*') {
                return $segment;
            }
            return $indexes[$cursor++] ?? '*';
        }, explode('.', $relatedPattern));

        return implode('.', $resolved);
    }

    private function message(string $field, string $pattern, string $rule, array $params): string
    {
        $label = $this->attributes[$field]
            ?? $this->attributes[$pattern]
            ?? str_replace(['_', '.'], ' ', $field);

        $custom = $this->messages[$field . '.' . $rule]
            ?? $this->messages[$pattern . '.' . $rule]
            ?? $this->messages[$rule]
            ?? null;

        if (is_string($custom)) {
            return strtr($custom, [
                ':attribute' => $label,
                ':value' => (string) ($params[0] ?? ''),
            ]);
        }

        return match ($rule) {
            'required', 'required_if', 'required_with' => "The {$label} field is required.",
            'email' => "The {$label} field must be a valid email address.",
            'url' => "The {$label} field must be a valid URL.",
            'string' => "The {$label} field must be a string.",
            'integer' => "The {$label} field must be an integer.",
            'float', 'numeric' => "The {$label} field must be numeric.",
            'boolean' => "The {$label} field must be boolean.",
            'array' => "The {$label} field must be an array.",
            'date' => "The {$label} field must be a valid date.",
            'min' => "The {$label} field must be at least " . ($params[0] ?? '') . '.',
            'max' => "The {$label} field must not be greater than " . ($params[0] ?? '') . '.',
            'between' => "The {$label} field must be between " . ($params[0] ?? '') . ' and ' . ($params[1] ?? '') . '.',
            'in' => "The {$label} field contains an invalid value.",
            'not_in' => "The {$label} field contains a prohibited value.",
            'same' => "The {$label} field must match " . ($params[0] ?? 'the comparison field') . '.',
            'different' => "The {$label} field must be different from " . ($params[0] ?? 'the comparison field') . '.',
            'regex' => "The {$label} field format is invalid.",
            'confirmed' => "The {$label} confirmation does not match.",
            'distinct' => "The {$label} field contains duplicate values.",
            'file' => "The {$label} field must be a valid uploaded file.",
            'image' => "The {$label} field must be a valid image.",
            'mimes' => "The {$label} field has an invalid file type.",
            'mimetypes' => "The {$label} field has an invalid MIME type.",
            'min_size' => "The {$label} file is smaller than the minimum allowed size.",
            'max_size' => "The {$label} file is larger than the maximum allowed size.",
            'dimensions' => "The {$label} image dimensions are invalid.",
            default => "The {$label} field is invalid.",
        };
    }

    private function literal(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => $value,
        };
    }

    private function castString(mixed $value): mixed
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $value;
    }

    private function castBoolean(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value;
        }

        $filtered = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $filtered ?? $value;
    }

    private function isBlank(mixed $value): bool
    {
        if ($value instanceof UploadedFile) {
            return !$value->isValid();
        }

        return $value === null || $value === '' || (is_array($value) && $value === []);
    }

    private function isDate(mixed $value): bool
    {
        if ($value instanceof \DateTimeInterface) {
            return true;
        }

        if (!is_string($value) || trim($value) === '') {
            return false;
        }

        try {
            new DateTimeImmutable($value);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function sizeOf(mixed $value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            return (float) (function_exists('mb_strlen') ? mb_strlen($value) : strlen($value));
        }

        if (is_array($value)) {
            return (float) count($value);
        }

        if ($value instanceof UploadedFile) {
            return (float) $value->size();
        }

        return 0.0;
    }

    private function between(mixed $value, array $params): bool
    {
        $min = (float) ($params[0] ?? 0);
        $max = (float) ($params[1] ?? $min);
        $size = $this->sizeOf($value);
        return $size >= $min && $size <= $max;
    }

    private function matchesRegex(mixed $value, string $pattern): bool
    {
        if (!is_scalar($value) && $value !== null) {
            return false;
        }

        if ($pattern === '') {
            return false;
        }

        $regex = str_starts_with($pattern, '/') ? $pattern : '/' . str_replace('/', '\\/', $pattern) . '/';
        return preg_match($regex, (string) $value) === 1;
    }

    private function isDistinct(mixed $value): bool
    {
        if (!is_array($value)) {
            return false;
        }

        $seen = [];
        foreach ($value as $item) {
            $key = is_scalar($item) || $item === null
                ? get_debug_type($item) . ':' . var_export($item, true)
                : serialize($item);

            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
        }

        return true;
    }

    private function parseBytes(string|int|float $value): int
    {
        if (is_int($value) || is_float($value) || is_numeric($value)) {
            return max(0, (int) $value);
        }

        if (!preg_match('/^\s*(\d+(?:\.\d+)?)\s*(b|kb|mb|gb)?\s*$/i', $value, $m)) {
            throw new InvalidArgumentException("Invalid file size value: {$value}");
        }

        $number = (float) $m[1];
        $unit = strtolower($m[2] ?? 'b');
        $multiplier = match ($unit) {
            'kb' => 1024,
            'mb' => 1024 ** 2,
            'gb' => 1024 ** 3,
            default => 1,
        };

        return (int) round($number * $multiplier);
    }

    private function validDimensions(UploadedFile $file, array $params): bool
    {
        $dimensions = $file->dimensions();
        if ($dimensions === null) {
            return false;
        }

        $rules = [];
        foreach ($params as $param) {
            [$key, $value] = array_pad(explode('=', (string) $param, 2), 2, null);
            if ($value !== null) {
                $rules[strtolower(trim($key))] = (int) $value;
            }
        }

        $width = $dimensions['width'];
        $height = $dimensions['height'];

        if (isset($rules['width']) && $width !== $rules['width']) return false;
        if (isset($rules['height']) && $height !== $rules['height']) return false;
        if (isset($rules['min_width']) && $width < $rules['min_width']) return false;
        if (isset($rules['max_width']) && $width > $rules['max_width']) return false;
        if (isset($rules['min_height']) && $height < $rules['min_height']) return false;
        if (isset($rules['max_height']) && $height > $rules['max_height']) return false;

        return true;
    }

    /**
     * Expand wildcard paths against existing collection indexes.
     * Missing final/non-wildcard children are still emitted so `required` works.
     */
    private function expandWildcardPath(string $pattern): array
    {
        $segments = explode('.', $pattern);
        $results = [];
        $this->expandSegments($this->data, $segments, 0, [], $results);
        return array_values(array_unique($results));
    }

    private function expandSegments(mixed $node, array $segments, int $index, array $path, array &$results): void
    {
        if ($index >= count($segments)) {
            $results[] = implode('.', $path);
            return;
        }

        $segment = $segments[$index];

        if ($segment === '*') {
            if (!is_array($node)) {
                return;
            }

            foreach (array_keys($node) as $key) {
                $this->expandSegments($node[$key], $segments, $index + 1, [...$path, (string) $key], $results);
            }
            return;
        }

        $nextNode = is_array($node) && array_key_exists($segment, $node)
            ? $node[$segment]
            : null;

        $this->expandSegments($nextNode, $segments, $index + 1, [...$path, $segment], $results);
    }

    /** True when the schema declares deeper fields below this pattern. */
    private function hasDeclaredChildren(string $pattern, array $schema): bool
    {
        $prefix = rtrim($pattern, '.') . '.';
        foreach (array_keys($schema) as $candidate) {
            if ((string) $candidate !== $pattern && str_starts_with((string) $candidate, $prefix)) {
                return true;
            }
        }
        return false;
    }

    private static function getPath(array $data, string $path, ?bool &$exists = null): mixed
    {
        $exists = true;
        $value = $data;

        if ($path === '') {
            return $value;
        }

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                $exists = false;
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    private static function setPath(array &$data, string $path, mixed $value): void
    {
        $segments = explode('.', $path);
        $cursor =& $data;

        foreach ($segments as $index => $segment) {
            if ($index === array_key_last($segments)) {
                $cursor[$segment] = $value;
                return;
            }

            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor =& $cursor[$segment];
        }
    }

    /** Normalize PHP's column-oriented $_FILES shape into normal nested UploadedFile values. */
    public static function normalizeFiles(array $files): array
    {
        $normalized = [];

        foreach ($files as $field => $spec) {
            if (!is_array($spec) || !array_key_exists('name', $spec)) {
                continue;
            }

            $value = self::normalizeFileSpec($spec);
            if ($value !== null) {
                $normalized[$field] = $value;
            }
        }

        return $normalized;
    }

    private static function normalizeFileSpec(array $spec): mixed
    {
        $names = $spec['name'] ?? null;

        if (!is_array($names)) {
            $error = (int) ($spec['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                return null;
            }

            return new UploadedFile(
                originalName: (string) $names,
                tmpPath: (string) ($spec['tmp_name'] ?? ''),
                error: $error,
                size: (int) ($spec['size'] ?? 0),
                clientType: isset($spec['type']) ? (string) $spec['type'] : null,
            );
        }

        $result = [];
        foreach (array_keys($names) as $key) {
            $childSpec = [
                'name' => $spec['name'][$key] ?? null,
                'type' => $spec['type'][$key] ?? null,
                'tmp_name' => $spec['tmp_name'][$key] ?? null,
                'error' => $spec['error'][$key] ?? UPLOAD_ERR_NO_FILE,
                'size' => $spec['size'][$key] ?? 0,
            ];

            $child = self::normalizeFileSpec($childSpec);
            if ($child !== null) {
                $result[$key] = $child;
            }
        }

        return $result;
    }

    private static function mergeRecursive(array $data, array $files): array
    {
        foreach ($files as $key => $value) {
            if (isset($data[$key]) && is_array($data[$key]) && is_array($value)) {
                $data[$key] = self::mergeRecursive($data[$key], $value);
            } else {
                $data[$key] = $value;
            }
        }

        return $data;
    }
}
