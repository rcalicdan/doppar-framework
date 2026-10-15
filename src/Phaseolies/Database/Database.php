<?php

namespace Phaseolies\Database;

use Phaseolies\Support\Collection;
use Phaseolies\Database\Query\RawExpression;
use Phaseolies\Database\Query\Builder;
use Phaseolies\Database\Entity\Query\Builder as QueryBuilder;
use Phaseolies\Database\Procedure\ProcedureResult;
use Phaseolies\Database\Entity\Model;
use Phaseolies\Database\Connectors\ConnectionFactory;
use PDOException;
use PDO;
use Phaseolies\Database\Entity\Query\Grammar;

/**
 * @property string $table
 * @property \PDO $pdo
 * @property array<int, mixed> $conditions
 * @property array<int, mixed> $orderBy
 * @phpstan-consistent-constructor
 */
class Database
{
    use Grammar {
        getTableColumnsSql as protected grammarGetTableColumnsSql;
        processTableColumnsResult as protected grammarProcessTableColumnsResult;
    }

    /**
     * The active PDO connections.
     *
     * @var array<string, PDO>
     */
    protected static array $connections = [];

    /**
     * The transaction level counters for each connection.
     *
     * @var array<string, int>
     */
    protected static array $transactions = [];

    /**
     * The driver instances for each connection.
     *
     * @var array<string, \Phaseolies\Database\Contracts\Driver\DriverInterface>
     */
    protected static array $drivers = [];

    /**
     * Statements captured while pretending, or null when running for real.
     *
     * @var array<int, array{sql: string, bindings: array<array-key, mixed>}>|null
     */
    protected static ?array $pretendLog = null;

    /**
     * The connection name for this instance.
     */
    protected ?string $connection;

    /**
     * Create a new database manager instance.
     */
    public function __construct(?string $connection = null)
    {
        $this->connection = $connection;
    }

    /**
     * Get the SQL query to retrieve table column information.
     */
    public function getTableColumnsSql(string $tableName): string
    {
        return $this->grammarGetTableColumnsSql($tableName);
    }

    /**
     * Process the result to extract column names based on driver.
     *
     * @param array<int, array<string, mixed>> $result
     * @return array<int, string>
     */
    public function processTableColumnsResult(array $result): array
    {
        return $this->grammarProcessTableColumnsResult($result);
    }

    /**
     * Get the driver name for the current connection.
     */
    public function getDriver(): string
    {
        return $this->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
    }

    /**
     * Get a PDO instance for the specified connection.
     *
     * @throws \RuntimeException
     * @throws \PDOException
     */
    public static function getPdoInstance(?string $connection = null): PDO
    {
        $connection = $connection ?: (string) config('database.default');

        if (!isset(static::$connections[$connection])) {
            $config = config("database.connections.{$connection}");

            if (empty($config) || !is_array($config)) {
                throw new \RuntimeException("Database connection [{$connection}] not configured.");
            }

            try {
                $driver = ConnectionFactory::createDriver($config);
                static::$drivers[$connection] = $driver;
                static::$connections[$connection] = $driver->connect($config);
            } catch (PDOException $e) {
                throw new \PDOException(
                    "Failed to connect to database [{$connection}]: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            } catch (\RuntimeException $e) {
                throw new \RuntimeException(
                    "Database connection error [{$connection}]: " . $e->getMessage(),
                    (int) $e->getCode(),
                    $e
                );
            }
        }

        return static::$connections[$connection];
    }

    /**
     * Get the PDO instance for this connection.
     */
    protected function getPdo(): PDO
    {
        return static::getPdoInstance($this->connection);
    }

    /**
     * Get the driver instance for a connection.
     */
    protected static function getDriverInstance(?string $connection = null): \Phaseolies\Database\Contracts\Driver\DriverInterface
    {
        $connection = $connection ?: (string) config('database.default');

        if (!isset(static::$drivers[$connection])) {
            static::getPdoInstance($connection);
        }

        return static::$drivers[$connection];
    }

    /**
     * Begin a transaction on the specified connection.
     *
     * @throws PDOException
     */
    public function beginTransaction(): void
    {
        $pdo = $this->getPdo();
        $connection = $this->connection ?? (string) config('database.default');

        if (!isset(static::$transactions[$connection])) {
            static::$transactions[$connection] = 0;
        }

        if (static::$transactions[$connection] === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec("SAVEPOINT trans" . (static::$transactions[$connection] + 1));
        }

        static::$transactions[$connection]++;
    }

    /**
     * Commit a transaction on the specified connection.
     *
     * @throws PDOException
     */
    public function commit(): void
    {
        $pdo = $this->getPdo();
        $connection = $this->connection ?? (string) config('database.default');

        if (static::$transactions[$connection] === 1) {
            $pdo->commit();
        }

        static::$transactions[$connection] = max(0, static::$transactions[$connection] - 1);
    }

    /**
     * Rollback a transaction on the specified connection.
     *
     * @throws PDOException
     */
    public function rollBack(): void
    {
        $pdo = $this->getPdo();
        $connection = $this->connection ?? (string) config('database.default');

        if (static::$transactions[$connection] === 1) {
            $pdo->rollBack();
        } else {
            $pdo->exec("ROLLBACK TO SAVEPOINT trans" . static::$transactions[$connection]);
        }

        static::$transactions[$connection] = max(0, static::$transactions[$connection] - 1);
    }

    /**
     * Execute a Closure within a transaction.
     *
     * @throws \Throwable
     */
    public function transaction(\Closure $callback, int $attempts = 1): mixed
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            $this->beginTransaction();

            try {
                $result = $callback();
                $this->commit();
                return $result;
            } catch (\Throwable $e) {
                $this->rollBack();

                if ($currentAttempt >= $attempts) {
                    throw $e;
                }
            }
        }

        return null;
    }

    /**
     * Get the transaction level for a connection.
     */
    public static function transactionLevel(?string $connection = null): int
    {
        $connection = $connection ?: (string) config('database.default');

        return static::$transactions[$connection] ?? 0;
    }

    /**
     * Get the column names for a given table.
     *
     * @return array<int, string>
     * @throws PDOException
     */
    public function getTableColumns(?string $table = null): array
    {
        if ($table === null) {
            throw new \InvalidArgumentException('Table name cannot be null');
        }

        try {
            $sql = $this->getTableColumnsSql($table);
            $stmt = $this->getPdo()->query($sql);
            $result = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            return $this->processTableColumnsResult($result);
        } catch (PDOException $e) {
            throw new PDOException("Failed to get table columns: " . $e->getMessage());
        }
    }

    /**
     * Get the list of tables in the database.
     *
     * @return array<int, string>
     */
    public function getTables(): array
    {
        $sql = $this->getTablesSql();
        $stmt = $this->getPdo()->query($sql);

        return $stmt !== false ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    /**
     * Check if a table exists in the database.
     */
    public function tableExists(string $table): bool
    {
        try {
            $result = $this->getPdo()->query("SELECT 1 FROM {$table} LIMIT 1");

            return $result !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get table name from model instance.
     *
     * @throws \InvalidArgumentException
     */
    public static function getTable(Model $model): string
    {
        return $model->getTable();
    }

    /**
     * Get the database connection.
     */
    public static function getConnection(): PDO
    {
        return static::getPdoInstance();
    }

    /**
     * Execute a stored procedure with optional result flattening.
     *
     * @param array<array-key, mixed> $params
     * @param array<array-key, mixed> $outputParams
     * @throws PDOException
     */
    public function procedure(string $procedureName, array $params = [], array $outputParams = []): ProcedureResult
    {
        $pdo = $this->getPdo();

        $placeholders = implode(',', array_fill(0, count($params) + count($outputParams), '?'));
        $stmt = $pdo->prepare("CALL {$procedureName}({$placeholders})");

        $i = 1;
        foreach ($params as $param) {
            $stmt->bindValue($i++, $param);
        }

        foreach ($outputParams as &$param) {
            $stmt->bindParam($i++, $param, PDO::PARAM_INPUT_OUTPUT);
        }

        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute();

        $results = [];
        do {
            /** @var array<int, array<string, mixed>> $rows */
            $rows = $stmt->fetchAll();
            $results[] = $rows;
        } while ($stmt->nextRowset());

        return new ProcedureResult($results);
    }

    /**
     * Execute a view and return results.
     *
     * @param array<string, mixed> $where
     * @param array<string, mixed> $params
     * @return array<int, array<string, mixed>>
     * @throws PDOException
     */
    public static function view(string $viewName, array $where = [], array $params = []): array
    {
        $sql = "SELECT * FROM {$viewName}";

        if (!empty($where)) {
            $conditions = [];
            foreach ($where as $column => $value) {
                $conditions[] = "{$column} = :{$column}";
                $params[":{$column}"] = $value;
            }
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $stmt = static::getPdoInstance()->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute($params);

        /** @var array<int, array<string, mixed>> $rows */
        $rows = $stmt->fetchAll();

        return $rows;
    }

    /**
     * Execute a raw SQL query and return results as a Collection.
     *
     * @param array<array-key, mixed> $params
     * @return Collection<array-key, mixed>
     * @throws PDOException
     */
    public function query(string $sql, array $params = []): Collection
    {
        try {
            $stmt = $this->getPdo()->prepare($sql);
            $stmt->setFetchMode(PDO::FETCH_ASSOC);
            $stmt->execute($params);
            $results = $stmt->fetchAll();

            return new Collection(
                'array',
                count($results) > 1 ? $results : $results[0] ?? []
            );
        } catch (PDOException $e) {
            throw new PDOException("Database error: " . $e->getMessage());
        }
    }

    /**
     * Execute a raw SQL query.
     *
     * @param array<array-key, mixed> $params
     * @throws \PDOException
     */
    public function statement(string $sql, array $params = []): \PDOStatement
    {
        $stmt = $this->getPdo()->prepare($sql);
        $stmt->setFetchMode(PDO::FETCH_ASSOC);
        $stmt->execute($params);

        return $stmt;
    }

    /**
     * Execute a raw SQL statement (INSERT, UPDATE, DELETE).
     *
     * @param array<array-key, mixed> $params
     * @return int Number of affected rows
     * @throws PDOException
     */
    public function execute(string $sql, array $params = []): int
    {
        if (static::$pretendLog !== null) {
            static::$pretendLog[] = ['sql' => $sql, 'bindings' => $params];

            return 0;
        }

        $pdo = $this->getPdo();
        $driver = $this->getDriver();

        if ($driver === 'pgsql') {
            $statements = array_filter(array_map('trim', explode(';', $sql)));

            $rowCount = 0;
            foreach ($statements as $stmt) {
                if (trim($stmt) !== '') {
                    $statement = $pdo->prepare($stmt);
                    $statement->execute($params);
                    $rowCount += $statement->rowCount();
                }
            }
            return $rowCount;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Run the callback without executing any write statements, instead capturing them for inspection.
     *
     * @return array<int, array{sql: string, bindings: array<array-key, mixed>}>
     */
    public static function pretend(\Closure $callback): array
    {
        $previous = static::$pretendLog;
        static::$pretendLog = [];

        try {
            $callback();

            return static::$pretendLog;
        } finally {
            static::$pretendLog = $previous;
        }
    }

    /**
     * Determine if statements are currently being captured instead of run.
     */
    public static function isPretending(): bool
    {
        return static::$pretendLog !== null;
    }

    /**
     * Drop all tables in the database.
     *
     * @throws PDOException
     */
    public function dropAllTables(): int
    {
        $driver = static::getDriverInstance($this->connection);

        return $driver->dropAllTables($this->getPdo());
    }

    /**
     * Disable foreign key constraints.
     */
    public function disableForeignKeyConstraints(): void
    {
        $driver = static::getDriverInstance($this->connection);

        $driver->disableForeignKeyConstraints($this->getPdo());
    }

    /**
     * Enable foreign key constraints.
     */
    public function enableForeignKeyConstraints(): void
    {
        $driver = static::getDriverInstance($this->connection);

        $driver->enableForeignKeyConstraints($this->getPdo());
    }

    /**
     * Create a raw SQL expression.
     *
     * @param array<array-key, mixed> $bindings
     */
    public function sql(string $expression, array $bindings = []): RawExpression
    {
        return new RawExpression($expression, $bindings);
    }

    /**
     * Begin a fluent query against a database table.
     */
    public function table(string $table): Builder
    {
        $driver = self::getDriverInstance($this->connection);

        return new Builder($table, $this->getPdo(), $driver);
    }

    /**
     * Get a connection instance for the specified connection name.
     */
    public function connection(?string $name = null): self
    {
        return new static($name);
    }

    /**
     * Get the query builder instance.
     */
    public function bucket(string $table): QueryBuilder
    {
        return new QueryBuilder($this->getPdo(), $table);
    }

    /**
     * Disconnect from the database and clean up resources.
     */
    public function disconnect(?string $connection = null): bool
    {
        $connection = $connection ?: (string) config('database.default');

        unset(static::$connections[$connection]);
        unset(static::$drivers[$connection]);

        if (isset(static::$transactions[$connection])) {
            static::$transactions[$connection] = 0;
        }

        return true;
    }

    /**
     * Reconnect to the database.
     *
     * @throws \RuntimeException
     */
    public function reconnect(?string $connection = null): PDO
    {
        $connection = $connection ?: (string) config('database.default');

        $this->disconnect($connection);

        return static::getPdoInstance($connection);
    }

    /**
     * Check if connection is alive.
     */
    public function isConnected(?string $connection = null): bool
    {
        $connection = $connection ?: (string) config('database.default');

        if (!isset(static::$connections[$connection])) {
            return false;
        }

        try {
            $stmt = static::$connections[$connection]->query('SELECT 1');
            return $stmt !== false;
        } catch (\PDOException $e) {
            return false;
        }
    }

    /**
     * Get a fresh database connection (new instance).
     *
     * @throws \RuntimeException
     */
    public function getFreshConnection(?string $connection = null): PDO
    {
        $connection = $connection ?: (string) config('database.default');
        $config = config("database.connections.{$connection}");

        if (empty($config) || !is_array($config)) {
            throw new \RuntimeException("Database connection [{$connection}] not configured.");
        }

        $driver = ConnectionFactory::createDriver($config);

        return $driver->connect($config);
    }

    /**
     * Clean up all database connections.
     */
    public function cleanupAllConnections(): void
    {
        foreach (array_keys(static::$connections) as $connection) {
            $this->disconnect($connection);
        }

        static::$connections = [];
        static::$drivers = [];
        static::$transactions = [];
    }
}