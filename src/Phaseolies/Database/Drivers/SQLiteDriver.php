<?php

namespace Phaseolies\Database\Drivers;

use PDO;
use Phaseolies\Database\Contracts\Driver\DriverInterface;

class SQLiteDriver implements DriverInterface
{
    /**
     * Get the SQLite PDO Instance.
     *
     * @param array<string, mixed> $config
     */
    #[\Override]
    public function connect(array $config): PDO
    {
        $path = $config['database'];

        if ($path === ':memory:') {
            return new PDO('sqlite::memory:');
        }

        $directory = dirname($path);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        return new PDO("sqlite:" . $path, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    /**
     * Get table columns.
     *
     * @return array<int, string>
     */
    #[\Override]
    public function getTableColumns(PDO $pdo, string $table): array
    {
        $stmt = $pdo->query("PRAGMA table_info({$table})");

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN, 1) : [];
    }

    /**
     * Get table list.
     *
     * @return array<int, string>
     */
    #[\Override]
    public function getTables(PDO $pdo): array
    {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    /**
     * Check whether a table exists or not.
     */
    #[\Override]
    public function tableExists(PDO $pdo, string $table): bool
    {
        $stmt = $pdo->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name = :name");
        $stmt->execute([':name' => $table]);
        return $stmt->fetch() !== false;
    }

    /**
     * Call store procedure and get the results.
     *
     * @param array<int|string, mixed> $params
     * @param array<int|string, mixed> $outputParams
     * @return array<int, array<int, array<string, mixed>>>
     */
    #[\Override]
    public function callProcedure(PDO $pdo, string $name, array $params = [], array &$outputParams = []): array
    {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare($name);
            $stmt->execute($params);

            $results = [];
            do {
                /** @var array<int, array<string, mixed>> $rows */
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $results[] = $rows;
            } while ($stmt->nextRowset());

            $pdo->commit();

            return $results;
        } catch (\Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Drop all the tables.
     */
    #[\Override]
    public function dropAllTables(PDO $pdo): int
    {
        $tables = $this->getTables($pdo);
        $count = 0;

        foreach ($tables as $table) {
            if ($table === 'sqlite_sequence') {
                $pdo->exec("DELETE FROM $table");
                continue;
            }
            $pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");
            $count++;
        }

        return $count;
    }

    /**
     * Disable foreign key constraints for table.
     */
    #[\Override]
    public function disableForeignKeyConstraints(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = OFF');
    }

    /**
     * Enable foreign key constraints for table.
     */
    #[\Override]
    public function enableForeignKeyConstraints(PDO $pdo): void
    {
        $pdo->exec('PRAGMA foreign_keys = ON');
    }

    /**
     * Truncate a given table.
     */
    #[\Override]
    public function truncate(PDO $pdo, string $table, bool $resetAutoIncrement = true): int
    {
        $count = $pdo->exec("DELETE FROM \"{$table}\"");

        if ($resetAutoIncrement) {
            $pdo->exec("DELETE FROM sqlite_sequence WHERE name = '{$table}'");
        }

        return $count !== false ? (int) $count : 0;
    }

    /**
     * Drop a given table.
     */
    #[\Override]
    public function dropTable(PDO $pdo, string $table): int
    {
        $result = $pdo->exec("DROP TABLE IF EXISTS \"{$table}\"");

        return $result !== false ? (int) $result : 0;
    }

    /**
     * Delete all records from a given table.
     */
    #[\Override]
    public function deleteAll(PDO $pdo, string $table): int
    {
        $result = $pdo->exec("DELETE FROM \"{$table}\"");

        return $result !== false ? (int) $result : 0;
    }
}