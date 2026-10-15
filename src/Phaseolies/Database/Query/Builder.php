<?php

namespace Phaseolies\Database\Query;

use PDO;
use Phaseolies\Database\Contracts\Driver\DriverInterface;

class Builder
{
    public function __construct(protected string $table, protected PDO $pdo, protected ?DriverInterface $driver = null) {}

    /**
     * Truncate the table.
     *
     * @throws \RuntimeException
     */
    public function truncate(bool $resetAutoIncrement = true): int
    {
        if (!$this->tableExists()) {
            throw new \RuntimeException("Table {$this->table} does not exist");
        }

        if ($this->driver) {
            return $this->driver->truncate($this->pdo, $this->table, $resetAutoIncrement);
        }

        // Fallback to direct SQL if no driver is available
        $sql = $resetAutoIncrement
            ? "TRUNCATE TABLE {$this->table}"
            : "DELETE FROM {$this->table}";

        return (int) $this->execute($sql);
    }

    /**
     * Delete all records from the table.
     */
    public function delete(): int
    {
        if ($this->driver) {
            return $this->driver->deleteAll($this->pdo, $this->table);
        }
        return (int) $this->execute("DELETE FROM {$this->table}");
    }

    /**
     * Drop table from database.
     */
    public function drop(): int
    {
        if ($this->driver) {
            return $this->driver->dropTable($this->pdo, $this->table);
        }
        return (int) $this->execute("DROP TABLE {$this->table}");
    }

    /**
     * Check if table exists.
     */
    protected function tableExists(): bool
    {
        if ($this->driver) {
            return $this->driver->tableExists($this->pdo, $this->table);
        }

        try {
            $result = $this->pdo->query("SELECT 1 FROM {$this->table} LIMIT 1");
            return $result !== false;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Execute SQL statement.
     *
     * @param array<array-key, mixed> $params
     */
    protected function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);

        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Get the PDO instance.
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
