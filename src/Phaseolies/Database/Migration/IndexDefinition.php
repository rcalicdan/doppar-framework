<?php

namespace Phaseolies\Database\Migration;

class IndexDefinition
{
    /**
     * Create a new table level index definition.
     *
     * @param array<int, string> $columns
     */
    public function __construct(
        public string $type,
        public array $columns,
        public ?string $name = null,
        public ?string $algorithm = null
    ) {
    }

    /**
     * Set a custom name for the index.
     */
    public function name(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Set the index algorithm (e.g. btree, hash, gin, gist).
     */
    public function algorithm(string $algorithm): self
    {
        $this->algorithm = $algorithm;

        return $this;
    }
}
