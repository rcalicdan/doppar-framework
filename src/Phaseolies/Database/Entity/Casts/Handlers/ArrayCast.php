<?php

namespace Phaseolies\Database\Entity\Casts\Handlers;

use Phaseolies\Database\Entity\Casts\Contracts\CastableInterface;

class ArrayCast implements CastableInterface
{
    /**
     * Get the value as an array.
     *
     * @return array<array-key, mixed>
     */
    #[\Override]
    public function get(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE && is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /**
     * Set the value as a JSON string.
     */
    #[\Override]
    public function set(mixed $value): mixed
    {
        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}