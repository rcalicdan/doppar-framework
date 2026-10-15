<?php

namespace Phaseolies\Session\Contracts;

interface SessionHandlerInterface
{
    /**
     * Initializes the session environment.
     */
    public function initialize(): void;

    /**
     * Starts the session.
     */
    public function start(): void;

    /**
     * Regenerates the session ID.
     */
    public function regenerate(): void;

    /**
     * Validates the current session.
     */
    public function validate(): void;

    /**
     * Generates a CSRF or session token.
     */
    public function generateToken(): void;
}
