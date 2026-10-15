<?php

namespace Phaseolies\Database\Query;

class RawExpression
{
    /**
     * The raw SQL expression string.
     */
    protected string $value;

    /**
     * The parameter bindings for the raw expression.
     *
     * @var array<int|string, mixed>
     */
    protected array $bindings = [];

    /**
     * Create a new raw SQL expression instance.
     *
     * @param array<int|string, mixed> $bindings
     */
    public function __construct(string $value, array $bindings = [])
    {
        $this->value = $value;
        $this->bindings = $bindings;
    }

    /**
     * Get the raw SQL expression value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the parameter bindings for the expression.
     *
     * @return array<int|string, mixed>
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Convert the expression to its string representation.
     */
    public function __toString(): string
    {
        return $this->value;
    }
}