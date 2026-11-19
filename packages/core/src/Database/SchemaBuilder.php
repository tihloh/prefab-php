<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Core\Database;

use PDO;

/**
 * Small, additive schema API that compiles portable DDL via SqlDialect.
 *
 * Destructive/alter-table operations and versioned migrations are deliberately
 * separate: there is no implicit data conversion or schema migration.
 */
final class SchemaBuilder
{
    private SqlDialect $dialect;

    public function __construct(private PDO $pdo)
    {
        $this->dialect = new SqlDialect((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    public function dialect(): SqlDialect { return $this->dialect; }

    public function create(string $table, callable $define, bool $ifNotExists = true): void
    {
        $this->pdo->exec($this->compileCreate($table, $define, $ifNotExists));
    }

    public function compileCreate(string $table, callable $define, bool $ifNotExists = true): string
    {
        $blueprint = new TableBlueprint();
        $define($blueprint);
        return $this->dialect->createTable($table, $blueprint, $ifNotExists);
    }

    public function hasTable(string $table): bool
    {
        TableBlueprint::assertIdentifier($table);
        $statement = $this->pdo->prepare($this->dialect->tableExistsQuery());
        $statement->execute([$table]);
        return $statement->fetchColumn() !== false;
    }

    public function dropIfExists(string $table): void
    {
        $this->pdo->exec($this->dialect->dropTable($table));
    }
}
