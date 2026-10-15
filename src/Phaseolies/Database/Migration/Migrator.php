<?php

namespace Phaseolies\Database\Migration;

use Phaseolies\Database\Database;
use Phaseolies\Support\Facades\DB;

class Migrator
{
    /**
     * Path to the directory containing migration files.
     */
    protected string $migrationPath;

    /**
     * Migration repository instance for tracking run migrations.
     */
    protected MigrationRepository $repository;

    /**
     * The registered migrations.
     *
     * @var array<string, Migration>
     */
    protected array $migrations = [];

    /**
     * The paths to package migration files.
     *
     * @var array<int, string>
     */
    protected array $migrationPaths = [];

    /**
     * The database connection name this migration should use.
     */
    protected ?string $connection = null;

    public function __construct(MigrationRepository $repository, string $migrationPath)
    {
        $this->repository = $repository;
        $this->migrationPath = $migrationPath;
    }

    /**
     * Add a migration to the migrator.
     */
    public function addMigration(string $file, Migration $migration): void
    {
        $this->migrations[$file] = $migration;
        $this->migrationPaths[] = $file;
    }

    /**
     * Run all pending migrations.
     *
     * @param array<string, mixed> $options
     * @return array<int, string> The names of the migrations that ran
     */
    public function run(string $connection, ?string $path = null, array $options = []): array
    {
        $conn = $connection !== '' ? $connection : (string) config('database.default');
        $pretend = (bool) ($options['pretend'] ?? false);

        $execute = function () use ($conn, $path, $options, $pretend): array {
            if (!$pretend) {
                $this->ensureMigrationTableExists($conn);
            }

            $executed = $this->getPendingMigrations($conn, $path);
            $batch = $this->repository->getNextBatchNumber($conn);

            foreach ($executed as $file) {
                $this->runMigration($file, $conn, $batch, $options);

                if (!empty($options['step'])) {
                    $batch++;
                }
            }

            return $executed;
        };

        return $pretend ? $execute() : $this->withLock($conn, $execute);
    }

    /**
     * Roll back migrations.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     * @throws \RuntimeException
     */
    public function rollback(string $connection, array $options = []): array
    {
        $pretend = (bool) ($options['pretend'] ?? false);

        $execute = function () use ($connection, $options): array {
            if (!$this->repository->exists($connection)) {
                return [];
            }

            $names = $this->repository->getRollbackCandidates(
                $connection,
                isset($options['step']) ? (int) $options['step'] : null,
                isset($options['batch']) ? (int) $options['batch'] : null
            );

            $migrations = [];
            foreach ($names as $name) {
                $migrations[$name] = $this->resolveMigration($name, $connection, true);
            }

            foreach ($migrations as $name => $migration) {
                $this->rollbackMigration($name, $migration, $connection, $options);
            }

            return $names;
        };

        return $pretend ? $execute() : $this->withLock($connection, $execute);
    }

    /**
     * Roll back every migration that has run.
     *
     * @param array<string, mixed> $options
     * @return array<int, string>
     */
    public function reset(string $connection, array $options = []): array
    {
        unset($options['batch']);
        $options['step'] = PHP_INT_MAX;

        return $this->rollback($connection, $options);
    }

    /**
     * Describe every known migration.
     *
     * @return array<int, array{migration: string, ran: bool, batch: ?int, ran_at: ?string, execution_time: ?int, modified: bool, missing: bool}>
     */
    public function status(?string $connection = null): array
    {
        $conn = $connection ?? (string) config('database.default');

        $files = $this->migrationFiles($conn);
        $records = $this->repository->getRecords($conn);

        $rows = [];

        foreach ($files as $name => $file) {
            $record = $records[$name] ?? null;

            $rows[$name] = [
                'migration' => $name,
                'ran' => $record !== null,
                'batch' => $record['batch'] ?? null,
                'ran_at' => $record['ran_at'] ?? null,
                'execution_time' => $record['execution_time'] ?? null,
                'modified' => $record !== null
                    && $record['checksum'] !== null
                    && $record['checksum'] !== $this->checksum($file),
                'missing' => false,
            ];
        }

        foreach ($records as $name => $record) {
            if (isset($rows[$name])) {
                continue;
            }

            $rows[$name] = [
                'migration' => $name,
                'ran' => true,
                'batch' => $record['batch'],
                'ran_at' => $record['ran_at'],
                'execution_time' => $record['execution_time'],
                'modified' => false,
                'missing' => true,
            ];
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * Get the names of the migrations that have not run yet.
     *
     * @return array<int, string>
     */
    public function getPendingMigrations(string $connection, ?string $path = null): array
    {
        $files = $this->getMigrationFiles($connection);
        $ran = $this->repository->getRan($connection);

        $vendorMigrations = [];

        foreach ($files as $file) {
            $normalized = str_replace('\\', '/', $file);

            if (str_contains($normalized, '/vendor/')) {
                $vendorMigrations[basename($file)] = $file;
            }
        }

        $fileNames = array_map('basename', $files);
        $migrations = array_diff($fileNames, $ran);

        foreach ($vendorMigrations as $basename => $vendorPath) {
            if (!file_exists(schema_path('migrations/' . $basename))) {
                $migrations[] = $vendorPath;
            }

            foreach ($migrations as $key => $value) {
                if (basename($value) === $basename && strpos($value, 'vendor') === false) {
                    unset($migrations[$key]);
                }
            }
        }

        if ($path) {
            $file = basename($path);
            if (in_array($file, $ran)) {
                return [];
            }

            $fullPath = is_file($path) ? $path : $this->migrationPath . DIRECTORY_SEPARATOR . $file;
            if (!is_file($fullPath)) {
                throw new \RuntimeException("Migration file not found: {$fullPath}\n\n");
            }

            return [$file];
        }

        $pending = [];
        foreach ($migrations as $file) {
            $fullPath = is_file($file) ? $file : $this->migrationPath . DIRECTORY_SEPARATOR . $file;

            if (!is_file($fullPath)) {
                continue;
            }

            $pending[] = basename($fullPath);
        }
        sort($pending);

        return $pending;
    }

    /**
     * Ensure the migration tracking table exists in the database.
     */
    protected function ensureMigrationTableExists(?string $connection = null): void
    {
        if (!$this->repository->exists($connection)) {
            $this->repository->create($connection);

            return;
        }

        $this->repository->upgrade($connection);
    }

    /**
     * Get all migration files from the migration path.
     *
     * @return array<int, string>
     */
    public function getMigrationFiles(?string $connection = null): array
    {
        $conn = $connection ?? (string) config('database.default');

        $files = glob($this->migrationPath . '/*.php') ?: [];
        $packageMigrations = $this->migrationPaths;

        $normalizedPackages = array_map(function ($file) {
            return is_file($file) ? $file : (base_path($file));
        }, $packageMigrations);

        $migrationMap = [];

        foreach ($normalizedPackages as $vendorFile) {
            if (is_file($vendorFile)) {
                $migrationMap[basename($vendorFile)] = $vendorFile;
            }
        }

        foreach ($files as $localFile) {
            $migrationMap[basename($localFile)] = $localFile;
        }

        $allFiles = array_values($migrationMap);
        $filtered = [];

        foreach ($allFiles as $path) {
            if (!is_file($path)) continue;

            $content = file_get_contents($path);
            if ($content === false) continue;

            $patterns = [
                "/Schema::connection\(\s*['\"]([^'\"]+)['\"]\s*\)/i",
                "/DB::connection\(\s*['\"]([^'\"]+)['\"]\s*\)/i",
                "/protected\s+\$connection\s*=\s*['\"]([^'\"]+)['\"]\s*;/i",
                "/public\s+\$connection\s*=\s*['\"]([^'\"]+)['\"]\s*;/i",
                "/\$this->connection\s*=\s*['\"]([^'\"]+)['\"]\s*;/i",
            ];

            $migrationConnection = null;
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $content, $m)) {
                    $migrationConnection = $m[1];
                    break;
                }
            }

            if ($migrationConnection === null) {
                $migrationConnection = config('database.default');
            }

            if ($migrationConnection === $conn) {
                $filtered[] = $path;
            }
        }

        return $filtered;
    }

    /**
     * Run a single migration file.
     *
     * @param array<string, mixed> $options
     * @throws \RuntimeException
     */
    protected function runMigration(string $file, string $connection, int $batch, array $options = []): void
    {
        $progress = $options['progress'] ?? null;
        $migration = $this->resolveMigration($file, $connection);

        if (!empty($options['pretend'])) {
            $queries = Database::pretend(fn() => $migration->up());
            if ($progress && is_callable($progress)) {
                $progress('pretend', $file, ['queries' => $queries]);
            }

            return;
        }

        if ($progress && is_callable($progress)) {
            $progress('running', $file, []);
        }

        $elapsed = 0;

        try {
            $this->transactional($connection, $migration, function () use ($migration, $file, $connection, $batch, &$elapsed) {
                $start = hrtime(true);
                $migration->up();
                $elapsed = (int) round((hrtime(true) - $start) / 1e6);

                $this->repository->log($file, $connection, $batch, $this->checksum($this->pathFor($file)), $elapsed);
            });
        } catch (\Throwable $e) {
            throw new \RuntimeException("Migration {$file} failed: " . $e->getMessage(), (int) $e->getCode(), $e);
        }

        if ($progress && is_callable($progress)) {
            $progress('ran', $file, ['time' => $elapsed]);
        }
    }

    /**
     * Revert a single migration and forget its record.
     *
     * @param array<string, mixed> $options
     * @throws \RuntimeException
     */
    protected function rollbackMigration(string $file, Migration $migration, string $connection, array $options = []): void
    {
        $progress = $options['progress'] ?? null;

        if (!empty($options['pretend'])) {
            $queries = Database::pretend(fn() => $migration->down());
            if ($progress && is_callable($progress)) {
                $progress('pretend', $file, ['queries' => $queries]);
            }

            return;
        }

        if ($progress && is_callable($progress)) {
            $progress('rolling_back', $file, []);
        }

        $elapsed = 0;

        try {
            $this->transactional($connection, $migration, function () use ($migration, $file, $connection, &$elapsed) {
                $start = hrtime(true);
                $migration->down();
                $elapsed = (int) round((hrtime(true) - $start) / 1e6);

                $this->repository->delete($file, $connection);
            });
        } catch (\Throwable $e) {
            throw new \RuntimeException("Rolling back {$file} failed: " . $e->getMessage(), (int) $e->getCode(), $e);
        }

        if ($progress && is_callable($progress)) {
            $progress('rolled_back', $file, ['time' => $elapsed]);
        }
    }

    /**
     * Run a callback atomically when the driver can roll back schema changes.
     */
    protected function transactional(string $connection, Migration $migration, \Closure $callback): void
    {
        $db = DB::connection($connection);

        if ($migration->withinTransaction && in_array($db->getDriver(), ['pgsql', 'sqlite'], true)) {
            $db->transaction($callback);

            return;
        }

        $callback();
    }

    /**
     * Hold a database-wide lock so two deploys cannot migrate at the same time.
     *
     * @throws \RuntimeException
     */
    protected function withLock(string $connection, \Closure $callback): mixed
    {
        $db = DB::connection($connection);
        $driver = $db->getDriver();

        $key = 'doppar_migrations_' . md5($connection . '|' . (string) config("database.connections.{$connection}.database"));
        $number = crc32($key);

        $acquired = match ($driver) {
            'mysql' => (bool) $db->statement('SELECT GET_LOCK(?, 0)', [$key])->fetchColumn(),
            'pgsql' => (bool) $db->statement('SELECT pg_try_advisory_lock(?)', [$number])->fetchColumn(),
            default => true,
        };

        if (!$acquired) {
            throw new \RuntimeException(
                "Another migration process is already running on connection [{$connection}]."
            );
        }

        try {
            return $callback();
        } finally {
            match ($driver) {
                'mysql' => $db->statement('SELECT RELEASE_LOCK(?)', [$key])->fetchColumn(),
                'pgsql' => $db->statement('SELECT pg_advisory_unlock(?)', [$number])->fetchColumn(),
                default => null,
            };
        }
    }

    /**
     * Map migration name to file path for the given connection.
     *
     * @return array<string, string>
     */
    protected function migrationFiles(string $connection): array
    {
        $files = [];

        foreach ($this->getMigrationFiles($connection) as $file) {
            $files[basename($file)] = $file;
        }

        return $files;
    }

    /**
     * Locate the file of a migration: the project's copy wins over a package's.
     */
    protected function pathFor(string $file): ?string
    {
        $local = $this->migrationPath . DIRECTORY_SEPARATOR . $file;

        if (is_file($local)) {
            return $local;
        }

        foreach ($this->migrationPaths as $path) {
            if (basename($path) === $file) {
                return is_file($path) ? $path : base_path($path);
            }
        }

        return null;
    }

    /**
     * Fingerprint of a migration file, insensitive to line-ending differences.
     */
    protected function checksum(?string $path): ?string
    {
        if ($path === null || !is_file($path)) {
            return null;
        }

        return hash('sha256', str_replace("\r\n", "\n", (string) file_get_contents($path)));
    }

    /**
     * Resolve a migration file into a Migration instance.
     *
     * @throws \RuntimeException
     */
    protected function resolveMigration(string $file, ?string $connection = null, bool $forRollback = false): Migration
    {
        foreach ($this->migrations as $path => $migration) {
            if (basename($path) === $file && !is_file($this->migrationPath . DIRECTORY_SEPARATOR . $file)) {
                return $migration;
            }
        }

        $path = $this->pathFor($file);

        if ($path === null) {
            throw new \RuntimeException(
                $forRollback
                    ? "Cannot roll back [{$file}]: the migration file no longer exists."
                    : "Migration file not found: {$this->migrationPath}" . DIRECTORY_SEPARATOR . $file
            );
        }

        $migration = require $path;

        if ($migration instanceof \Closure) {
            $migration = $migration();
        }

        if (!$migration instanceof Migration) {
            throw new \RuntimeException("Migration {$file} must return an instance of Migration");
        }

        return $migration;
    }

    /**
     * Convert a migration file name to a class name.
     */
    protected function getMigrationClass(string $file): string
    {
        $file = str_replace('.php', '', $file);

        return str()->camel($file);
    }
}