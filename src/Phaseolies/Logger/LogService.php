<?php

namespace Phaseolies\Logger;

use Phaseolies\Logger\Contracts\LogHandlerInterface;
use Monolog\ResettableInterface;
use Monolog\Logger;

class LogService implements ResettableInterface
{
    /**
     * The Monolog logger instance.
     */
    protected Logger $logger;

    /**
     * Array of log handlers.
     *
     * @var array<int, LogHandlerInterface>
     */
    protected array $handlers = [];

    /**
     * Adds a log handler.
     */
    public function addHandler(LogHandlerInterface $handler): void
    {
        $this->handlers[] = $handler;
    }

    /**
     * Configures and returns a Monolog logger instance with specific handlers.
     */
    public function getLogger(?string $channel = null): Logger
    {
        $channel ??= (is_string($envChannel = env('LOG_CHANNEL')) && $envChannel !== '' ? $envChannel : 'stack');

        $this->logger = new Logger($channel);

        foreach ($this->handlers as $handler) {
            $handler->configureHandler($this->logger, $channel);
        }

        return $this->logger;
    }

    /**
     * Resets the logger state by clearing all handlers.
     */
    public function reset(): void
    {
        $this->handlers = [];
    }
}