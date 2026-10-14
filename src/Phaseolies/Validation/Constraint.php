<?php

namespace Phaseolies\Validation;

interface Constraint
{
    /**
     * @param mixed $value
     * @param string $property
     * @param array<string, mixed> $payload
     * @return ConstraintViolation|null
     */
    public function validate(mixed $value, string $property, array $payload): ?ConstraintViolation;
}