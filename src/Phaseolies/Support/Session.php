<?php

namespace Phaseolies\Support;

class Session
{
    /**
     * The session data.
     *
     * @var array<string, mixed>
     */
    protected array $data = [];

    /**
     * The session peek data.
     *
     * @var array<string, mixed>
     */
    protected array $peek = [];

    /**
     * Flash data key.
     */
    protected string $flashKey = '_flash';

    /**
     * Constructor.
     */
    public function __construct()
    {
        if (!isset($_SESSION)) {
            $_SESSION = [];
        }

        $this->data = &$_SESSION;
        $this->peek = $_SESSION;
    }

    /**
     * Get a session value by key.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Get a session value by key and remove it.
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        $value = $this->data[$key] ?? $default;

        $this->forget($key);

        return $value;
    }

    /**
     * Get a session value by key from peek data and remove it.
     */
    public function getPeek(string $key, mixed $default = null): mixed
    {
        $value = $this->peek[$key] ?? $default;

        $this->forget($key);

        return $value;
    }

    /**
     * Put a key-value pair into the peek session storage.
     */
    public function putPeek(string $key, mixed $value): void
    {
        $this->peek[$key] = $value;
    }

    /**
     * Remove all session peek data.
     */
    public function flushPeek(): void
    {
        $this->peek = [];
    }

    /**
     * Put a key-value pair into the session.
     *
     * @param string|array<string, mixed> $key
     */
    public function put(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            foreach ($key as $k => $v) {
                $this->data[$k] = $v;
            }
        } else {
            $this->data[$key] = $value;
        }
    }

    /**
     * Check if a session key exists.
     */
    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    /**
     * Remove a session key.
     */
    public function forget(string $key): void
    {
        unset($this->data[$key]);
    }

    /**
     * Remove all session data.
     */
    public function flush(): void
    {
        $this->data = [];
    }

    /**
     * Regenerate the session ID.
     */
    public function regenerate(bool $deleteOldSession = true): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOldSession);
        }
    }

    /**
     * Get all session data.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }

    /**
     * Get the session ID.
     */
    public function getId(): string
    {
        return (string) session_id();
    }

    /**
     * Set the session ID.
     */
    public function setId(string $id): void
    {
        session_id($id);
    }

    /**
     * Destroy the session.
     */
    public function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        $this->data = [];
        $this->peek = [];
    }

    /**
     * Get the CSRF token.
     */
    public function token(): ?string
    {
        $token = $this->get('_token');

        return is_string($token) ? $token : null;
    }

    /**
     * Set a flash message.
     */
    public function flash(string $key, mixed $value): void
    {
        $this->put($key, $value);
    }

    /**
     * Keep flash data for the next request.
     *
     * @param string|array<int, string> $keys
     */
    public function reflash(string|array $keys): void
    {
        foreach ((array) $keys as $key) {
            if ($this->has($this->flashKey . '.' . $key)) {
                $this->flash($key, $this->get($this->flashKey . '.' . $key));
            }
        }
    }

    /**
     * Invalidate the session (regenerate ID and clear all data).
     */
    public function invalidate(): void
    {
        $this->regenerate(true);

        $this->flush();
    }

    /**
     * Regenerate the CSRF token.
     */
    public function regenerateToken(): void
    {
        $this->put('_token', $this->generateToken());
    }

    /**
     * Generate a new CSRF token.
     */
    protected function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}
