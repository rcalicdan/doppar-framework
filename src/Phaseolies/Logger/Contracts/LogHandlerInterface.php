<?php

namespace Phaseolies\Logger\Contracts;

use Monolog\Logger;

interface LogHandlerInterface
{
    /**
     * Configures the Monolog handler.
     */
    public function configureHandler(Logger $logger, string $channel): void;
}
