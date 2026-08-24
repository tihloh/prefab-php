<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Validation\Contracts;

/**
 * Contract for reusable object-based validation rules.
 *
 * Returning null means the value is valid. Returning a string means validation
 * failed and the returned string is used as the field error message.
 */
interface RuleInterface
{
    public function validate(
        string $field,
        mixed $value,
        array $data,
    ): ?string;
}
