<?php

namespace Phaseolies\Database\Migration;

use PDO;
use Phaseolies\Database\Migration\Grammars\GrammarFactory;
use Phaseolies\Database\Migration\Grammars\Grammar;
use Phaseolies\Database\Entity\Model;

class Blueprint
{
    /**
     * The name of the table being created or modified.
     */
    public string $table;

    /**
     * Collection of ColumnDefinition objects for the table.
     *
     * @var array<int, ColumnDefinition>
     */
    protected array $columns = [];

    /**
     * Collection of commands.
     *
     * @var array<int, ForeignKeyDefinition|IndexDefinition|array<string, mixed>>
     */
    protected array $commands = [];

    /**
     * The primary key column name.
     */
    protected string $primaryKey = '';

    /**
     * Table level options (engine, charset, collation, comment, temporary).
     *
     * @var array<string, mixed>
     */
    protected array $tableOptions = [];

    /**
     * The grammar instance for the current database driver.
     */
    protected Grammar $grammar;

    /**
     * Whether this blueprint is creating a new table (true) or
     * modifying an existing one (false).
     */
    protected bool $creating;

    /**
     * Create a new table blueprint instance.
     */
    public function __construct(string $table, ?string $driver = null, bool $creating = true)
    {
        $this->table = $table;
        $this->grammar = GrammarFactory::make($driver ?? $this->getDefaultDriver());
        $this->creating = $creating;
    }

    /**
     * Get the default database driver.
     */
    protected function getDefaultDriver(): string
    {
        $connection = app('db')->getConnection();

        return strtolower($connection->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    /**
     * Create an auto-incrementing primary key column (alias for bigIncrements with primary key).
     */
    public function id(string $column = 'id'): ColumnDefinition
    {
        return $this->bigIncrements($column)->primary();
    }

    /**
     * Create a TINYINT column (1-byte integer, range: -128 to 127).
     */
    public function tinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyInteger', $column);
    }

    /**
     * Create a SMALLINT column (2-byte integer, range: -32,768 to 32,767).
     */
    public function smallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('smallInteger', $column);
    }

    /**
     * Create a MEDIUMINT column (3-byte integer, range: -8,388,608 to 8,388,607).
     */
    public function mediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumInteger', $column);
    }

    /**
     * Create a BIGINT column (8-byte integer, large range).
     */
    public function bigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('bigInteger', $column);
    }

    /**
     * Create an UNSIGNED INT column (4-byte, only positive numbers).
     */
    public function unsignedInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedInteger', $column);
    }

    /**
     * Create an UNSIGNED TINYINT column (1-byte, only positive numbers).
     */
    public function unsignedTinyInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedTinyInteger', $column);
    }

    /**
     * Create an UNSIGNED SMALLINT column (2-byte, only positive numbers).
     */
    public function unsignedSmallInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedSmallInteger', $column);
    }

    /**
     * Create an UNSIGNED MEDIUMINT column (3-byte, only positive numbers).
     */
    public function unsignedMediumInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedMediumInteger', $column);
    }

    /**
     * Create a FLOAT column (single-precision floating point number).
     */
    public function float(string $column, ?int $precision = null, ?int $scale = null): ColumnDefinition
    {
        return $this->addColumn('float', $column, array_filter(compact('precision', 'scale')));
    }

    /**
     * Create a DECIMAL column (fixed-point number, exact precision).
     */
    public function decimal(string $column, int $precision = 10, int $scale = 2): ColumnDefinition
    {
        return $this->addColumn('decimal', $column, compact('precision', 'scale'));
    }

    /**
     * Create a CHAR column (fixed-length string).
     */
    public function char(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('char', $column, compact('length'));
    }

    /**
     * Create a TEXT column (variable-length string, up to 65,535 characters).
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a TINYTEXT column (variable-length string, up to 255 characters).
     */
    public function tinyText(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyText', $column);
    }

    /**
     * Create a DATE column (date only, no time).
     */
    public function date(string $column): ColumnDefinition
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a DATETIME column (date and time, no timezone).
     */
    public function dateTime(string $column, ?int $precision = null): ColumnDefinition
    {
        return $this->addColumn('dateTime', $column, $this->precision($precision));
    }

    /**
     * Create a DATETIME column with timezone awareness.
     */
    public function dateTimeTz(string $column, ?int $precision = null): ColumnDefinition
    {
        return $this->addColumn('dateTimeTz', $column, $this->precision($precision));
    }

    /**
     * Create a TIME column (time only, no date).
     */
    public function time(string $column, ?int $precision = null): ColumnDefinition
    {
        return $this->addColumn('time', $column, $this->precision($precision));
    }

    /**
     * Create a TIME column with timezone awareness.
     */
    public function timeTz(string $column, ?int $precision = null): ColumnDefinition
    {
        return $this->addColumn('timeTz', $column, $this->precision($precision));
    }

    /**
     * Create a TIMESTAMP column with timezone awareness.
     */
    public function timestampTz(string $column, ?int $precision = null): ColumnDefinition
    {
        return $this->addColumn('timestampTz', $column, $this->precision($precision));
    }

    /**
     * Create a YEAR column (year only, 2 or 4 digit format).
     */
    public function year(string $column): ColumnDefinition
    {
        return $this->addColumn('year', $column);
    }

    /**
     * Create a BLOB column (binary data, up to 65,535 bytes).
     */
    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a TINYBLOB column (binary data, up to 255 bytes).
     */
    public function tinyBlob(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyBlob', $column);
    }

    /**
     * Create a MEDIUMBLOB column (binary data, up to 16,777,215 bytes).
     */
    public function mediumBlob(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumBlob', $column);
    }

    /**
     * Create a LONGBLOB column (binary data, up to 4GB).
     */
    public function longBlob(string $column): ColumnDefinition
    {
        return $this->addColumn('longBlob', $column);
    }

    /**
     * Create an ENUM column (string with predefined possible values).
     *
     * @param array<int, string|int> $values
     */
    public function enum(string $column, array $values): ColumnDefinition
    {
        return $this->addColumn('enum', $column, ['values' => $values]);
    }

    /**
     * Create an ENUM column with nullable option.
     *
     * @param array<int, string|int> $values
     */
    public function enumNullable(string $column, array $values, bool $nullable = true): ColumnDefinition
    {
        return $this->addColumn('enum', $column, ['values' => $values, 'nullable' => $nullable]);
    }

    /**
     * Create a SET column (string that can have zero or more values from predefined set).
     *
     * @param array<int, string|int> $values
     */
    public function set(string $column, array $values): ColumnDefinition
    {
        return $this->addColumn('set', $column, ['values' => $values]);
    }

    /**
     * Create a UUID column (stored as CHAR(36)).
     */
    public function uuid(string $column): ColumnDefinition
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create an IP address column (stored as VARCHAR(45) to support IPv6).
     */
    public function ipAddress(string $column): ColumnDefinition
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * Create a MAC address column (stored as VARCHAR(17)).
     */
    public function macAddress(string $column): ColumnDefinition
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * Create a GEOMETRY column (any type of spatial data).
     */
    public function geometry(string $column): ColumnDefinition
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * Create a POINT column (single location in coordinate space).
     */
    public function point(string $column): ColumnDefinition
    {
        return $this->addColumn('point', $column);
    }

    /**
     * Create a LINESTRING column (curve with linear interpolation between points).
     */
    public function lineString(string $column): ColumnDefinition
    {
        return $this->addColumn('lineString', $column);
    }

    /**
     * Create a POLYGON column (polygonal surface).
     */
    public function polygon(string $column): ColumnDefinition
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * Create a GEOMETRYCOLLECTION column (collection of geometry objects).
     */
    public function geometryCollection(string $column): ColumnDefinition
    {
        return $this->addColumn('geometryCollection', $column);
    }

    /**
     * Create a MULTIPOINT column (collection of points).
     */
    public function multiPoint(string $column): ColumnDefinition
    {
        return $this->addColumn('multiPoint', $column);
    }

    /**
     * Create a MULTILINESTRING column (collection of linestrings).
     */
    public function multiLineString(string $column): ColumnDefinition
    {
        return $this->addColumn('multiLineString', $column);
    }

    /**
     * Create a MULTIPOLYGON column (collection of polygons).
     */
    public function multiPolygon(string $column): ColumnDefinition
    {
        return $this->addColumn('multiPolygon', $column);
    }

    /**
     * Create a big auto-incrementing unsigned integer column.
     */
    public function bigIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('bigIncrements', $column);
    }

    /**
     * Create a string (VARCHAR) column.
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Create a medium text column.
     */
    public function mediumText(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Create a long text column.
     */
    public function longText(string $column): ColumnDefinition
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a boolean (TINYINT(1)) column.
     */
    public function boolean(string $column): ColumnDefinition
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a BIT column (binary flag).
     */
    public function bit(string $column, int $length = 1): ColumnDefinition
    {
        if ($this->getDefaultDriver() === 'pgsql') {
            if ($length === 1) {
                return $this->boolean($column);
            }
            return $this->addColumn('bit', $column, compact('length'));
        }

        return $this->addColumn('bit', $column, compact('length'));
    }

    /**
     * Create a JSON column.
     */
    public function json(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a JSON column that will store arrays.
     */
    public function jsonArray(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create an integer column.
     */
    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn('integer', $column);
    }

    /**
     * Create a timestamp column.
     */
    public function timestamp(string $column, ?int $precision = null): ColumnDefinition
    {
        return $this->addColumn('timestamp', $column, $this->precision($precision));
    }

    /**
     * Add nullable creation and update timestamps to the table.
     */
    public function timestamps(?int $precision = null): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Alias of timestamps().
     */
    public function nullableTimestamps(?int $precision = null): void
    {
        $this->timestamps($precision);
    }

    /**
     * Add created_at and updated_at with timezone awareness.
     */
    public function timestampsTz(?int $precision = null): void
    {
        $this->timestampTz('created_at', $precision)->nullable();
        $this->timestampTz('updated_at', $precision)->nullable();
    }

    /**
     * Add a nullable deletion timestamp to the table.
     */
    public function softDeletes(string $column = 'deleted_at', ?int $precision = null): ColumnDefinition
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    /**
     * Add a nullable deletion timestamp with timezone awareness.
     */
    public function softDeletesTz(string $column = 'deleted_at', ?int $precision = null): ColumnDefinition
    {
        return $this->timestampTz($column, $precision)->nullable();
    }

    /**
     * Add a nullable remember_token VARCHAR(100) column.
     */
    public function rememberToken(): ColumnDefinition
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Create an auto-incrementing INT UNSIGNED primary key column.
     */
    public function increments(string $column): ColumnDefinition
    {
        return $this->addColumn('increments', $column)->primary();
    }

    /**
     * Alias of increments().
     */
    public function integerIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('integerIncrements', $column)->primary();
    }

    /**
     * Create an auto-incrementing TINYINT UNSIGNED primary key column.
     */
    public function tinyIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('tinyIncrements', $column)->primary();
    }

    /**
     * Create an auto-incrementing SMALLINT UNSIGNED primary key column.
     */
    public function smallIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('smallIncrements', $column)->primary();
    }

    /**
     * Create an auto-incrementing MEDIUMINT UNSIGNED primary key column.
     */
    public function mediumIncrements(string $column): ColumnDefinition
    {
        return $this->addColumn('mediumIncrements', $column)->primary();
    }

    /**
     * Create a ULID column (stored as CHAR(26)).
     */
    public function ulid(string $column = 'ulid'): ColumnDefinition
    {
        return $this->addColumn('ulid', $column);
    }

    /**
     * Create an unsigned BIGINT column that can be constrained to another table:
     * $table->foreignId('user_id')->constrained()->cascadeOnDelete()
     */
    public function foreignId(string $column): ForeignIdColumnDefinition
    {
        return $this->addForeignIdColumn('unsignedBigInteger', $column);
    }

    /**
     * Create a UUID column that can be constrained to another table.
     */
    public function foreignUuid(string $column): ForeignIdColumnDefinition
    {
        return $this->addForeignIdColumn('uuid', $column);
    }

    /**
     * Create a ULID column that can be constrained to another table.
     */
    public function foreignUlid(string $column): ForeignIdColumnDefinition
    {
        return $this->addForeignIdColumn('ulid', $column);
    }

    /**
     * Add the {name}_type and {name}_id columns of a polymorphic relation
     * together with a composite index on them.
     */
    public function morphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->unsignedBigInteger("{$name}_id");
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Nullable version of morphs().
     */
    public function nullableMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();
        $this->unsignedBigInteger("{$name}_id")->nullable();
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Polymorphic relation whose key is a UUID.
     */
    public function uuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->uuid("{$name}_id");
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Polymorphic relation whose key is a ULID.
     */
    public function ulidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->ulid("{$name}_id");
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Set the storage engine of the table (MySQL only).
     */
    public function engine(string $engine): static
    {
        $this->tableOptions['engine'] = $engine;

        return $this;
    }

    /**
     * Set the default character set of the table (MySQL only).
     */
    public function charset(string $charset): static
    {
        $this->tableOptions['charset'] = $charset;

        return $this;
    }

    /**
     * Set the default collation of the table (MySQL only).
     */
    public function collation(string $collation): static
    {
        $this->tableOptions['collation'] = $collation;

        return $this;
    }

    /**
     * Add a comment to the table (MySQL and PostgreSQL).
     */
    public function comment(string $comment): static
    {
        $this->tableOptions['comment'] = $comment;

        return $this;
    }

    /**
     * Create the table as a TEMPORARY table.
     */
    public function temporary(): static
    {
        $this->tableOptions['temporary'] = true;

        return $this;
    }

    /**
     * Add a (composite) index.
     *
     * @param string|array<int, string> $columns
     */
    public function index(string|array $columns, ?string $name = null, ?string $algorithm = null): IndexDefinition
    {
        return $this->addIndexCommand('index', $columns, $name, $algorithm);
    }

    /**
     * Add a (composite) unique constraint.
     *
     * @param string|array<int, string> $columns
     */
    public function unique(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndexCommand('unique', $columns, $name);
    }

    /**
     * Add a (composite) primary key.
     *
     * @param string|array<int, string> $columns
     */
    public function primary(string|array $columns): IndexDefinition
    {
        return $this->addIndexCommand('primary', $columns);
    }

    /**
     * Add a full text index.
     *
     * @param string|array<int, string> $columns
     */
    public function fullText(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndexCommand('fulltext', $columns, $name);
    }

    /**
     * Add a spatial index.
     *
     * @param string|array<int, string> $columns
     */
    public function spatialIndex(string|array $columns, ?string $name = null): IndexDefinition
    {
        return $this->addIndexCommand('spatial', $columns, $name);
    }

    /**
     * Drop one or more columns.
     *
     * @param string|array<int, string> $columns
     */
    public function dropColumn(string|array $columns): void
    {
        $this->commands[] = ['command' => 'dropColumn', 'columns' => (array) $columns];
    }

    /**
     * Rename a column.
     */
    public function renameColumn(string $from, string $to): void
    {
        $this->commands[] = ['command' => 'renameColumn', 'from' => $from, 'to' => $to];
    }

    /**
     * Drop an index by name, or by the column list it was created with.
     *
     * @param string|array<int, string> $index
     */
    public function dropIndex(string|array $index): void
    {
        $this->addDropIndexCommand('index', $index);
    }

    /**
     * Drop a unique constraint by name, or by the column list it was created with.
     *
     * @param string|array<int, string> $index
     */
    public function dropUnique(string|array $index): void
    {
        $this->addDropIndexCommand('unique', $index);
    }

    /**
     * Drop a full text index by name, or by the column list it was created with.
     *
     * @param string|array<int, string> $index
     */
    public function dropFullText(string|array $index): void
    {
        $this->addDropIndexCommand('fulltext', $index);
    }

    /**
     * Drop a spatial index by name, or by the column list it was created with.
     *
     * @param string|array<int, string> $index
     */
    public function dropSpatialIndex(string|array $index): void
    {
        $this->addDropIndexCommand('spatial', $index);
    }

    /**
     * Drop the primary key.
     */
    public function dropPrimary(): void
    {
        $this->commands[] = ['command' => 'dropIndex', 'type' => 'primary', 'name' => ''];
    }

    /**
     * Drop a foreign key by constraint name, or by the column(s) it was created on.
     *
     * @param string|array<int, string> $index
     */
    public function dropForeign(string|array $index): void
    {
        $name = is_array($index) ? 'fk_' . $this->table . '_' . implode('_', $index) : $index;

        $this->commands[] = ['command' => 'dropIndex', 'type' => 'foreign', 'name' => $name];
    }

    /**
     * Drop a foreign key column together with its constraint
     * (the default fk_{table}_{column} constraint name is used).
     */
    public function dropConstrainedForeignId(string $column): void
    {
        $this->dropForeign([$column]);
        $this->dropColumn($column);
    }

    /**
     * Rename an index.
     */
    public function renameIndex(string $from, string $to): void
    {
        $this->commands[] = ['command' => 'renameIndex', 'from' => $from, 'to' => $to];
    }

    /**
     * Drop the created_at and updated_at columns.
     */
    public function dropTimestamps(): void
    {
        $this->dropColumn(['created_at', 'updated_at']);
    }

    /**
     * Drop the timezone aware created_at and updated_at columns.
     */
    public function dropTimestampsTz(): void
    {
        $this->dropTimestamps();
    }

    /**
     * Drop the soft delete column.
     */
    public function dropSoftDeletes(string $column = 'deleted_at'): void
    {
        $this->dropColumn($column);
    }

    /**
     * Drop the timezone aware soft delete column.
     */
    public function dropSoftDeletesTz(string $column = 'deleted_at'): void
    {
        $this->dropSoftDeletes($column);
    }

    /**
     * Drop the remember_token column.
     */
    public function dropRememberToken(): void
    {
        $this->dropColumn('remember_token');
    }

    /**
     * Drop the columns (and composite index) of a polymorphic relation.
     * Pass the same index name that was given to morphs(), if any.
     */
    public function dropMorphs(string $name, ?string $indexName = null): void
    {
        $this->dropIndex($indexName ?? ["{$name}_type", "{$name}_id"]);
        $this->dropColumn(["{$name}_type", "{$name}_id"]);
    }

    /**
     * Create a foreign key column for the given model with optional cascade options.
     *
     * @param class-string<Model> $model
     */
    public function foreignIdFor(string $model, bool $onDeleteCascade = false, bool $onUpdateCascade = false): ColumnDefinition
    {
        /** @var Model $modelInstance */
        $modelInstance = new $model();
        $foreignKey = $this->snakeCase(class_basename($model)) . '_id';

        $column = $this->unsignedBigInteger($foreignKey);

        $foreignKeyDefinition = $this->foreign($foreignKey)
            ->references('id')
            ->on($modelInstance->getTable());

        if ($onDeleteCascade) {
            $foreignKeyDefinition->onDelete('cascade');
        }

        if ($onUpdateCascade) {
            $foreignKeyDefinition->onUpdate('cascade');
        }

        return $column;
    }

    /**
     * Create a foreign key constraint on the given column.
     *
     * @param string|array<int, string> $column
     */
    public function foreign(string|array $column): ForeignKeyDefinition
    {
        $foreign = new ForeignKeyDefinition($this, $column);
        $this->commands[] = $foreign;

        return $foreign;
    }

    /**
     * Add a new column to the blueprint.
     *
     * @param array<string, mixed> $parameters
     */
    public function addColumn(string $type, string $name, array $parameters = []): ColumnDefinition
    {
        $column = new ColumnDefinition(array_merge(compact('type', 'name'), $parameters));

        $this->columns[] = $column;

        return $column;
    }

    /**
     * Add a column that can later be constrained with constrained().
     */
    protected function addForeignIdColumn(string $type, string $name): ForeignIdColumnDefinition
    {
        $column = new ForeignIdColumnDefinition($this, compact('type', 'name'));

        $this->columns[] = $column;

        return $column;
    }

    /**
     * Register a table level index command.
     *
     * @param string|array<int, string> $columns
     */
    protected function addIndexCommand(string $type, string|array $columns, ?string $name = null, ?string $algorithm = null): IndexDefinition
    {
        $index = new IndexDefinition($type, (array) $columns, $name, $algorithm);
        $this->commands[] = $index;

        return $index;
    }

    /**
     * Register a DROP INDEX style command.
     *
     * @param string|array<int, string> $index
     */
    protected function addDropIndexCommand(string $type, string|array $index): void
    {
        $name = is_array($index) ? $this->grammar->indexName($this->table, $index, $type) : $index;

        $this->commands[] = ['command' => 'dropIndex', 'type' => $type, 'name' => $name];
    }

    /**
     * Build the optional precision attribute of temporal columns.
     *
     * @return array<string, int>
     */
    protected function precision(?int $precision): array
    {
        return $precision === null ? [] : ['precision' => $precision];
    }

    /**
     * Create an unsigned big integer column.
     */
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        return $this->addColumn('unsignedBigInteger', $column);
    }

    /**
     * Create a DOUBLE column (double-precision floating point number).
     */
    public function double(string $column, ?int $precision = null, ?int $scale = null): ColumnDefinition
    {
        return $this->addColumn('double', $column, array_filter(compact('precision', 'scale')));
    }

    /**
     * Create a BLOB column (binary data, up to 65,535 bytes).
     */
    public function blob(string $column): ColumnDefinition
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a JSONB column (binary JSON format, more efficient for storage and querying).
     * Note: In MySQL, JSONB is the same as JSON (unlike PostgreSQL which has distinct types).
     */
    public function jsonb(string $column): ColumnDefinition
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Convert the blueprint to a list of SQL statements, in execution order.
     *
     * @return array<int, string>
     * @throws \RuntimeException
     */
    public function toStatements(): array
    {
        $statements = [];

        $columns = array_values($this->columns);

        if (empty($columns) && ($this->creating || empty($this->commands))) {
            throw new \RuntimeException("No columns defined for table {$this->table}");
        }

        if ($this->creating) {
            $this->compileCreate($columns, $statements);
        } else {
            $this->compileAlter($columns, $statements);
        }

        foreach ($this->commands as $command) {
            $this->compileCommand($command, $statements);
        }

        return array_values(array_filter($statements));
    }

    /**
     * Convert the blueprint to SQL statements joined into one string.
     *
     * @throws \RuntimeException
     */
    public function toSql(): string
    {
        return implode(';' . PHP_EOL, $this->toStatements()) . ';';
    }

    /**
     * Compile CREATE TABLE and everything that has to follow it.
     *
     * @param array<int, ColumnDefinition> $columns
     * @param array<int, string> &$statements
     */
    protected function compileCreate(array $columns, array &$statements): void
    {
        foreach ($this->commands as $command) {
            if ($command instanceof IndexDefinition && $command->type === 'primary') {
                foreach ($command->columns as $name) {
                    $this->findColumn($columns, $name)->attributes['primary'] = true;
                }
            }
        }

        $primaryKeys = [];

        foreach ($columns as $column) {
            if (!empty($column->attributes['primary'])) {
                $primaryKeys[] = trim(explode(' ', $column->name)[0], '`');
            }
        }

        $this->grammar->setTableOptions($this->tableOptions);

        $create = $this->grammar->compileCreateTable($this->table, $columns, $primaryKeys);

        if (!$this->grammar->supportsAddingForeignKey()) {
            $inline = [];

            foreach ($this->commands as $command) {
                if ($command instanceof ForeignKeyDefinition) {
                    $inline[] = $command->toConstraintSql();
                }
            }

            if ($inline) {
                $create = $this->grammar->appendTableConstraints($create, $inline);
            }
        }

        $statements[] = $create;

        foreach ($this->grammar->compileTableOptionStatements($this->table) as $statement) {
            $statements[] = $statement;
        }

        foreach ($columns as $column) {
            $this->handleIndexAndUniqueColumn($column, $statements);

            foreach ($this->grammar->compileColumnComment($this->table, $column) as $statement) {
                $statements[] = $statement;
            }
        }
    }

    /**
     * Compile the statements that add or modify columns of an existing table.
     *
     * @param array<int, ColumnDefinition> $columns
     * @param array<int, string> &$statements
     */
    protected function compileAlter(array $columns, array &$statements): void
    {
        foreach ($columns as $column) {
            $column->attributes['altering'] = true;

            if (!empty($column->attributes['change'])) {
                foreach ($this->grammar->compileChangeColumn($this->table, $column) as $statement) {
                    $statements[] = $statement;
                }
            } else {
                if (
                    !empty($column->attributes['primary']) &&
                    !$this->grammar->supportsAddingPrimaryKey()
                ) {
                    continue;
                }

                $statements[] = $this->grammar->compileAddColumn($this->table, $column->toSql());
            }

            $this->handleIndexAndUniqueColumn($column, $statements);

            foreach ($this->grammar->compileColumnComment($this->table, $column) as $statement) {
                $statements[] = $statement;
            }
        }
    }

    /**
     * Compile a single table level command.
     *
     * @param ForeignKeyDefinition|IndexDefinition|array<string, mixed> $command
     * @param array<int, string> &$statements
     */
    protected function compileCommand(ForeignKeyDefinition|IndexDefinition|array $command, array &$statements): void
    {
        if ($command instanceof ForeignKeyDefinition) {
            if ($this->grammar->supportsAddingForeignKey()) {
                $statements[] = $command->toSql();
            } elseif (!$this->creating) {
                throw new \RuntimeException(
                    "Cannot add a foreign key to existing table \"{$this->table}\" on this database driver. " .
                        "Declare it in the table's original CREATE TABLE migration instead."
                );
            }

            return;
        }

        if ($command instanceof IndexDefinition) {
            if ($command->type === 'primary') {
                if (!$this->creating) {
                    $statements[] = $this->grammar->compileAddPrimary($this->table, $command->columns);
                }

                return;
            }

            $statements[] = $this->grammar->compileIndex($this->table, $command);

            return;
        }

        switch ($command['command']) {
            case 'dropColumn':
                /** @var array<int, string> $dropCols */
                $dropCols = $command['columns'];
                foreach ($this->grammar->compileDropColumn($this->table, $dropCols) as $statement) {
                    $statements[] = $statement;
                }
                break;
            case 'renameColumn':
                $statements[] = $this->grammar->compileRenameColumn($this->table, (string) $command['from'], (string) $command['to']);
                break;
            case 'dropIndex':
                $statements[] = $this->grammar->compileDropIndex($this->table, (string) $command['name'], (string) $command['type']);
                break;
            case 'renameIndex':
                $statements[] = $this->grammar->compileRenameIndex($this->table, (string) $command['from'], (string) $command['to']);
                break;
        }
    }

    /**
     * Find a declared column by name.
     *
     * @param array<int, ColumnDefinition> $columns
     * @throws \InvalidArgumentException
     */
    protected function findColumn(array $columns, string $name): ColumnDefinition
    {
        foreach ($columns as $column) {
            if ($column->name === $name) {
                return $column;
            }
        }

        throw new \InvalidArgumentException("Column [{$name}] is not defined on table [{$this->table}].");
    }

    /**
     * Handles the creation of index and unique constraints for a column.
     *
     * @param array<int, string> &$statements
     */
    protected function handleIndexAndUniqueColumn(ColumnDefinition $column, array &$statements): void
    {
        $attributes = $column->attributes;

        if (!empty($attributes['index'])) {
            $statements[] = $this->grammar->compileCreateIndex(
                $this->table,
                $column->name,
                is_string($attributes['index']) ? $attributes['index'] : null
            );
        }

        if (!empty($attributes['unique'])) {
            $sql = $this->grammar->compileCreateUnique(
                $this->table,
                $column->name,
                is_string($attributes['unique']) ? $attributes['unique'] : null
            );

            if (!empty($sql)) {
                $statements[] = $sql;
            } elseif (!$this->creating) {
                throw new \RuntimeException(
                    "Cannot add a UNIQUE constraint to column \"{$column->name}\" on table " .
                        "\"{$this->table}\" via ALTER TABLE on this database driver. " .
                        "Declare the column as unique in the table's original CREATE TABLE " .
                        "migration instead."
                );
            }
        }

        foreach (['fulltext', 'spatial'] as $type) {
            if (!empty($attributes[$type])) {
                $statements[] = $this->grammar->compileIndex($this->table, new IndexDefinition(
                    $type,
                    [$column->name],
                    is_string($attributes[$type]) ? $attributes[$type] : null
                ));
            }
        }
    }

    /**
     * Check if the current database connection is SQLite.
     */
    protected function isSQLite(): bool
    {
        return $this->getDefaultDriver() === 'sqlite';
    }

    /**
     * Convert the given string to snake_case.
     */
    protected function snakeCase(string $input): string
    {
        return str()->snake($input);
    }
}
