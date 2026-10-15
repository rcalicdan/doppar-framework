<?php

namespace Phaseolies\Logger\Contracts;

use Monolog\Logger;
use Monolog\Level;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

abstract class AbstractHandler
{
    /**
     * Configures the Monolog handler for logging.
     */
    public function handleConfiguration(Logger $logger, string $logFile): void
    {
        $streamHandler = new StreamHandler($logFile, Level::Debug);
        $formatter = new LineFormatter(
            format: "[%datetime%] %channel%.%level_name%: %message% %context%\n",
            dateFormat: null,
            allowInlineLineBreaks: true,
            ignoreEmptyContextAndExtra: true
        );

        $streamHandler->setFormatter($formatter);

        $logger->pushHandler($streamHandler);
    }
}