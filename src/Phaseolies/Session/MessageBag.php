<?php

namespace Phaseolies\Session;

class MessageBag
{
    /**
     * Set a value in the session.
     */
    public static function set(string $key, mixed $value): void
    {
        session()->put($key, $value);
    }

    /**
     * Stores the provided input data in the session for later retrieval.
     */
    public static function flashInput(): void
    {
        $input = request()->except('_token', '_method');

        $sensitiveInputExclusions = self::getSensitiveInputFields();

        foreach ($sensitiveInputExclusions as $field) {
            unset($input[$field]);
        }

        session()->put('input', $input);
    }

    /**
     * Retrieves the old input data that was previously stored in the session..
     */
    public static function old(?string $key = null): ?string
    {
        $input = session('input') ?? null;

        if ($key) {
            $data = session('input');
            session()->forget('input');
            return is_array($data) ? ($data[$key] ?? null) : null;
        }

        return is_string($input) ? $input : null;
    }

    /**
     * Checks if old input data exists for a specific key.
     */
    public static function has(string $key): bool
    {
        $input = session('input');

        return is_array($input) && isset($input[$key]);
    }

    /**
     * Clears all old input data from the session.
     */
    public static function clear(): void
    {
        session()->flushPeek();

        session()->forget('input');
    }

    /**
     * Gets all old input data from the session.
     *
     * @return array<string, mixed>|null
     */
    public static function all(): ?array
    {
        $input = session('input');

        return is_array($input) ? $input : null;
    }

    /**
     * Get the excluded sensitive input fields from being stored in the session.
     *
     * @return array<int, string>
     */
    public static function getSensitiveInputFields(): array
    {
        if (PHP_SAPI === 'cli' || defined('STDIN')) {
            return [];
        }

        $fields = config('app.exclude_sensitive_input');

        return is_array($fields) ? $fields : [];
    }
}