<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Database;

use InvalidArgumentException;

/**
 * Describes portable columns and constraints without coupling application
 * migrations to a particular SQL engine.
 */
final class TableBlueprint
{
    /** @var array<string, array<string, mixed>> */
    private array $columns = [];

    /** @var list<list<string>> */
    private array $uniqueConstraints = [];

    public function id(string $name = 'id'): self
    {
        return $this->add($name, 'id');
    }

    public function string(string $name, int $length = 255): self
    {
        if ($length < 1 || $length > 4000) {
            throw new InvalidArgumentException('String length must be between 1 and 4000 for portable schemas.');
        }
        return $this->add($name, 'string', ['length' => $length]);
    }

    public function text(string $name): self { return $this->add($name, 'text'); }
    public function integer(string $name): self { return $this->add($name, 'integer'); }
    public function bigInteger(string $name): self { return $this->add($name, 'bigInteger'); }
    public function boolean(string $name): self { return $this->add($name, 'boolean'); }
    public function date(string $name): self { return $this->add($name, 'date'); }
    public function dateTime(string $name): self { return $this->add($name, 'dateTime'); }
    public function json(string $name): self { return $this->add($name, 'json'); }

    public function decimal(string $name, int $precision = 12, int $scale = 2): self
    {
        if ($precision < 1 || $precision > 38 || $scale < 0 || $scale > $precision) {
            throw new InvalidArgumentException('Decimal precision/scale must fit the portable range 1..38.');
        }
        return $this->add($name, 'decimal', ['precision' => $precision, 'scale' => $scale]);
    }

    /** Mark a column as nullable. */
    public function nullable(string $name): self
    {
        $this->assertExistingColumn($name);
        if ($this->columns[$name]['type'] === 'id') {
            throw new InvalidArgumentException('An identity primary key cannot be nullable.');
        }
        $this->columns[$name]['nullable'] = true;
        return $this;
    }

    /** Supply a scalar default. SQL expressions must be handled by explicit, driver-specific migrations. */
    public function default(string $name, string|int|float|bool|null $value): self
    {
        $this->assertExistingColumn($name);
        if ($this->columns[$name]['type'] === 'id') {
            throw new InvalidArgumentException('An identity primary key cannot have a user-defined default.');
        }
        $this->columns[$name]['hasDefault'] = true;
        $this->columns[$name]['default'] = $value;
        return $this;
    }

    public function unique(string ...$columns): self
    {
        if ($columns === []) {
            throw new InvalidArgumentException('A unique constraint requires at least one column.');
        }
        foreach ($columns as $column) {
            $this->assertExistingColumn($column);
        }
        if (count(array_unique($columns)) !== count($columns)) {
            throw new InvalidArgumentException('Unique constraint contains repeated columns.');
        }
        $this->uniqueConstraints[] = $columns;
        return $this;
    }

    /** @return array<string, array<string, mixed>> */
    public function columns(): array { return $this->columns; }

    /** @return list<list<string>> */
    public function uniqueConstraints(): array { return $this->uniqueConstraints; }

    private function add(string $name, string $type, array $options = []): self
    {
        self::assertIdentifier($name);
        if (isset($this->columns[$name])) {
            throw new InvalidArgumentException("Duplicate column '{$name}'.");
        }
        if ($type === 'id' && array_filter($this->columns, static fn(array $col): bool => $col['type'] === 'id') !== []) {
            throw new InvalidArgumentException('Only one identity primary key is supported.');
        }
        $this->columns[$name] = array_merge([
            'type' => $type,
            'nullable' => false,
            'hasDefault' => false,
            'default' => null,
        ], $options);
        return $this;
    }

    private function assertExistingColumn(string $name): void
    {
        if (!isset($this->columns[$name])) {
            throw new InvalidArgumentException("Unknown column '{$name}'.");
        }
    }

    public static function assertIdentifier(string $value): void
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D', $value)) {
            throw new InvalidArgumentException("Invalid SQL identifier '{$value}'.");
        }
    }
}
