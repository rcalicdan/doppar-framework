<?php

namespace Phaseolies\Database\Migration;

use RuntimeException;

class MigrationCreator
{
    /**
     * Path to the stub templates.
     */
    protected string $stubPath;

    /**
     * Initializes the path to the migration stubs directory.
     */
    public function __construct()
    {
        $this->stubPath = __DIR__ . '/stubs';
    }

    /**
     * Create a new migration file.
     */
    public function create(string $name, string $path, ?string $table = null, bool $create = false): string
    {
        $this->ensureMigrationDirectoryExists($path);

        $name = $this->getMigrationName($name);
        $file = $path . '/' . $name . '.php';
        $stub = $this->getStub($table, $create);

        $this->populateStub($name, $stub, $table);

        file_put_contents($file, $stub);

        return $file;
    }

    /**
     * Ensure the migration directory exists. If not, create it.
     */
    protected function ensureMigrationDirectoryExists(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Generate a timestamped migration file name.
     */
    protected function getMigrationName(string $name): string
    {
        return date('Y_m_d_His') . '_' . $this->snakeCase($name);
    }

    /**
     * Get the appropriate stub content based on migration context.
     *
     * @throws RuntimeException
     */
    protected function getStub(?string $table, bool $create): string
    {
        $stubFile = match (true) {
            $table === null => $this->stubPath . '/migration.stub',
            $create         => $this->stubPath . '/migration.create.stub',
            default         => $this->stubPath . '/migration.update.stub',
        };

        $content = @file_get_contents($stubFile);

        if ($content === false) {
            throw new RuntimeException("Migration stub not found: {$stubFile}");
        }

        return $content;
    }

    /**
     * Replace placeholders in the stub with actual values.
     */
    protected function populateStub(string $name, string &$stub, ?string $table): void
    {
        $file = str_replace('.php', '', $name);

        if ($table) {
            $className = $this->pascalCase(
                (string) preg_replace('/^\d+_/', '', $file)
            );
        } else {
            $className = $this->pascalCase($file);
        }

        $stub = str_replace(['DummyClass', '{{ class }}', '{{class}}'], $className, $stub);

        if (!is_null($table)) {
            $stub = str_replace(['DummyTable', '{{ table }}', '{{table}}'], $table, $stub);
        }
    }

    /**
     * Convert a string to PascalCase.
     */
    protected function pascalCase(string $input): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $input)));
    }

    /**
     * Convert a string to snake_case.
     */
    protected function snakeCase(string $input): string
    {
        return str()->snake($input);
    }
}