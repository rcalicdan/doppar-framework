<?php

namespace Phaseolies\Session\Contracts;

abstract class AbstractSessionHandler implements SessionHandlerInterface
{
    /**
     * Configuration options for the session handler.
     *
     * @var array<string, mixed>
     */
    protected array $config;

    /**
     * Constructor - initializes the session handler with configuration.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Generates a CSRF token and stores it in the session if not already present.
     */
    public function generateToken(): void
    {
        $token = session('_token', bin2hex(random_bytes(16))) ?? null;

        if (!session()->has('_token')) {
            session()->put('_token', $token);
        }
    }

    /**
     * Regenerates the session ID to prevent session fixation attacks.
     */
    public function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    /**
     * Determines if the session should be regenerated based on the configured lifetime.
     */
    protected function shouldRegenerate(): bool
    {
        $lifetime = (int) ($this->config['lifetime'] ?? 120);

        return !session()->has('last_regenerated') ||
            (time() - (int) session('last_regenerated')) > ($lifetime * 60);
    }
}