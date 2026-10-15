<?php

namespace Phaseolies\Session\Handlers;

use Phaseolies\Session\Contracts\SessionHandlerInterface;

class SessionHandlerFactory
{
    /**
     * Create and return an instance of a session handler based on the specified driver.
     *
     * @param array<string, mixed> $config
     * @throws \RuntimeException
     */
    public static function create(string $driver, array $config): SessionHandlerInterface
    {
        return match ($driver) {
            'cookie' => new CookieSessionHandler($config),
            'file'   => new FileSessionHandler($config),
            default  => throw new \RuntimeException("Unsupported session driver: {$driver}"),
        };
    }
}