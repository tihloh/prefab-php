<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Live;

use InvalidArgumentException;
use JsonException;

final class SnapshotSigner
{
    public function __construct(private string $key)
    {
        if (strlen($key) < 32) {
            throw new InvalidArgumentException('Prefab Live signing key must be at least 32 bytes.');
        }
    }

    public function sign(string $id, string $component, array $snapshot): string
    {
        return hash_hmac('sha256', $this->payload($id, $component, $snapshot), $this->key);
    }

    public function verify(string $id, string $component, array $snapshot, string $checksum): bool
    {
        return hash_equals($this->sign($id, $component, $snapshot), $checksum);
    }

    private function payload(string $id, string $component, array $snapshot): string
    {
        try {
            return json_encode([
                'id' => $id,
                'component' => $component,
                'snapshot' => $this->canonicalize($snapshot),
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $e) {
            throw new InvalidArgumentException('Prefab Live snapshot cannot be signed: ' . $e->getMessage(), previous: $e);
        }
    }

    private function canonicalize(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
