<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Input;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Processes raw input through normalization, casting, filtering and validation.
 *
 * Schemas are whitelists: only declared fields can appear in validated output.
 * Custom transformers and validators keep the package extensible without hard
 * dependencies on database, HTTP, framework, or other Prefab modules.
 */
final class Input
{
    private array $customRules = [];
    private array $customTransforms = [];
    private array $attributes = [];
    private array $messages = [];

    public function __construct(private array $data = []) {}

    public static function from(array $data): self
    {
        return new self($data);
    }

    public function data(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /** Register a custom validation rule. Return null when valid or a message when invalid. */
    public function rule(string $name, callable $validator): self
    {
        $this->customRules[$name] = $validator;
        return $this;
    }

    /** Register a custom transformer/caster. */
    public function transform(string $name, callable $transformer): self
    {
        $this->customTransforms[$name] = $transformer;
        return $this;
    }

    /** Friendly names used in generated validation messages. */
    public function attributes(array $attributes): self
    {
        $this->attributes = array_replace($this->attributes, $attributes);
        return $this;
    }

    /** Override generated messages using `field.rule` or `rule` keys. */
    public function messages(array $messages): self
    {
        $this->messages = array_replace($this->messages, $messages);
        return $this;
    }

    /**
     * Process raw data using a compact schema.
     *
     * Rules may be pipe strings or arrays. Transform/cast operations are applied
     * in the declared order. Validation errors are collected per field.
     */
    public function process(array $schema): InputResult
    {
        $processed = [];
        $validated = [];
        $errors = [];

        foreach ($schema as $field => $definition) {
            $operations = $this->normalizeOperations($definition);
            $value = self::getPath($this->data, (string) $field, $exists);

            if ($this->containsOperation($operations, 'sometimes') && !$exists) {
                continue;
            }

            $fieldErrors = [];
            $nullable = $this->containsOperation($operations, 'nullable');

            foreach ($operations as $operation) {
                [$name, $params] = $this->parseOperation($operation);

                if (in_array($name, ['sometimes', 'nullable'], true)) {
                    continue;
                }

                if ($name === 'default' && !$exists) {
                    $value = $params[0] ?? null;
                    $exists = true;
                    continue;
                }

                if ($this->isTransform($name)) {
                    if ($exists) {
                        $value = $this->applyTransform($name, $value, $params, (string) $field);
                    }
                    continue;
                }

                if ($nullable && ($value === null || $value === '')) {
                    $value = null;
                    $exists = true;
                    break;
                }

                $message = $this->validateRule(
                    $name,
                    (string) $field,
                    $value,
                    $exists,
                    $params,
                );

                if ($message !== null) {
                    $fieldErrors[] = $message;
                }
            }

            if ($exists) {
                self::setPath($processed, (string) $field, $value);
            }

            if ($fieldErrors !== []) {
                $errors[(string) $field] = $fieldErrors;
                continue;
            }

            if ($exists) {
                self::setPath($validated, (string) $field, $value);
            }
        }

        return new InputResult($this->data, $processed, $validated, $errors);
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
        if (is_callable($operation) && !is_string($operation)) {
            return ['__callable__', [$operation]];
        }

        if (!is_string($operation)) {
            throw new InvalidArgumentException('Input rule/transform must be a string or callable.');
        }

        [$name, $parameterString] = array_pad(explode(':', $operation, 2), 2, null);
        $params = $parameterString === null ? [] : str_getcsv($parameterString);

        return [strtolower(trim($name)), $params];
    }

    private function containsOperation(array $operations, string $target): bool
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
            'trim',
            'lowercase',
            'uppercase',
            'null_if_empty',
            'string',
            'integer',
            'float',
            'boolean',
            'array',
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
            'string' => $value === null ? null : (string) $value,
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
        mixed $value,
        bool $exists,
        array $params,
    ): ?string {
        if ($name === '__callable__') {
            return null;
        }

        if (isset($this->customRules[$name])) {
            $message = ($this->customRules[$name])($field, $value, $this->data, $params, $exists);
            return is_string($message) && $message !== '' ? $message : null;
        }

        $valid = match ($name) {
            'required' => $exists && !$this->isBlank($value),
            'required_if' => $this->validateRequiredIf($value, $params),
            'required_with' => $this->validateRequiredWith($value, $params),
            'email' => !$exists || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'url' => !$exists || filter_var($value, FILTER_VALIDATE_URL) !== false,
            'string' => !$exists || is_string($value),
            'integer' => !$exists || is_int($value) || filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => !$exists || is_numeric($value),
            'boolean' => !$exists || is_bool($value) || in_array($value, [0, 1, '0', '1', 'true', 'false', 'on', 'off', 'yes', 'no'], true),
            'array' => !$exists || is_array($value),
            'date' => !$exists || $this->isDate($value),
            'min' => !$exists || $this->sizeOf($value) >= (float) ($params[0] ?? 0),
            'max' => !$exists || $this->sizeOf($value) <= (float) ($params[0] ?? INF),
            'between' => !$exists || $this->between($value, $params),
            'in' => !$exists || in_array((string) $value, array_map('strval', $params), true),
            'not_in' => !$exists || !in_array((string) $value, array_map('strval', $params), true),
            'same' => !$exists || $value === self::getPath($this->data, (string) ($params[0] ?? ''), $unused),
            'different' => !$exists || $value !== self::getPath($this->data, (string) ($params[0] ?? ''), $unused),
            'regex' => !$exists || $this->matchesRegex($value, $params[0] ?? ''),
            'confirmed' => !$exists || $value === self::getPath($this->data, $field . '_confirmation', $unused),
            default => throw new InvalidArgumentException("Unknown input rule or transform: {$name}"),
        };

        return $valid ? null : $this->message($field, $name, $params);
    }

    private function validateRequiredIf(mixed $value, array $params): bool
    {
        $other = (string) ($params[0] ?? '');
        $expected = array_slice($params, 1);
        $otherValue = self::getPath($this->data, $other, $exists);

        if (!$exists || !in_array((string) $otherValue, array_map('strval', $expected), true)) {
            return true;
        }

        return !$this->isBlank($value);
    }

    private function validateRequiredWith(mixed $value, array $params): bool
    {
        foreach ($params as $other) {
            $otherValue = self::getPath($this->data, (string) $other, $exists);
            if ($exists && !$this->isBlank($otherValue)) {
                return !$this->isBlank($value);
            }
        }

        return true;
    }

    private function message(string $field, string $rule, array $params): string
    {
        $label = $this->attributes[$field] ?? str_replace(['_', '.'], ' ', $field);
        $custom = $this->messages[$field . '.' . $rule] ?? $this->messages[$rule] ?? null;

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
            'numeric' => "The {$label} field must be numeric.",
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
            default => "The {$label} field is invalid.",
        };
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
            return (float) mb_strlen($value);
        }

        if (is_array($value)) {
            return (float) count($value);
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

    private static function getPath(array $data, string $path, ?bool &$exists = null): mixed
    {
        $exists = true;
        $value = $data;

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
}
