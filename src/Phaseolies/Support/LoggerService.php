<?php

namespace Phaseolies\Support;

use Phaseolies\Logger\LogService;
use Phaseolies\Logger\Exceptions\UnsupportedLogDriverException;
use Phaseolies\Logger\Drivers\DefaultLogHandler;
use Phaseolies\Logger\Drivers\DailyLogHandler;
use Monolog\Logger;
use Phaseolies\Logger\Drivers\SingleLogHandler;
use Phaseolies\Logger\Drivers\SlackLogHandler;

class LoggerService extends LogService
{
    /**
     * The current logging channel.
     */
    protected ?string $currentChannel = null;

    /**
     * Sets the logging channel.
     */
    public function channel(string $channel): self
    {
        match ($channel) {
            'daily' => $this->addHandler(new DailyLogHandler()),
            'stack' => $this->addHandler(new DefaultLogHandler()),
            'slack' => $this->addHandler(new SlackLogHandler()),
            'single' => $this->addHandler(new SingleLogHandler()),
            default => throw new UnsupportedLogDriverException("Unsupported log channel: $channel"),
        };

        $this->currentChannel = $channel;

        return $this;
    }

    /**
     * Logs an informational message.
     *
     * @param array<string, mixed> $context
     */
    public function info(mixed $message, array $context = []): void
    {
        $formatted = $this->formatMessage($message);

        $this->reader()->info($formatted, $context);
    }

    /**
     * Logs a notice message.
     *
     * @param array<string, mixed> $context
     */
    public function notice(mixed $message, array $context = []): void
    {
        $formatted = $this->formatMessage($message);

        $this->reader()->notice($formatted, $context);
    }

    /**
     * Logs a warning message.
     *
     * @param array<string, mixed> $context
     */
    public function warning(mixed $message, array $context = []): void
    {
        $formatted = $this->formatMessage($message);

        $this->reader()->warning($formatted, $context);
    }

    /**
     * Logs an error message.
     *
     * @param array<string, mixed> $context
     */
    public function error(mixed $message, array $context = []): void
    {
        $formatted = $this->formatMessage($message);

        $this->reader()->error($formatted, $context);
    }

    /**
     * Logs a debug message.
     *
     * @param array<string, mixed> $context
     */
    public function debug(mixed $message, array $context = []): void
    {
        $formatted = $this->formatMessage($message);

        $this->reader()->debug($formatted, $context);
    }

    /**
     * Logs a critical message.
     *
     * @param array<string, mixed> $context
     */
    public function critical(mixed $message, array $context = []): void
    {
        $formatted = $this->formatMessage($message);

        $this->reader()->critical($formatted, $context);
    }

    /**
     * Logs an alert message.
     *
     * @param array<string, mixed> $context
     */
    public function alert(mixed $message, array $context = []): void
    {
        $formatted = $this->formatMessage($message);

        $this->reader()->alert($formatted, $context);
    }

    /**
     * Logs an emergency message.
     *
     * @param array<string, mixed> $context
     */
    public function emergency(mixed $message, array $context = []): void
    {
        $formatted = $this->formatMessage($message);

        $this->reader()->emergency($formatted, $context);
    }

    /**
     * Overrides reader to pass the dynamic channel.
     */
    protected function reader(): Logger
    {
        $channel = $this->currentChannel;

        if ($channel === null) {
            $configured = is_string($envChannel = env('LOG_CHANNEL')) && $envChannel !== '' ? $envChannel : 'stack';

            match ($configured) {
                'daily' => $this->addHandler(new DailyLogHandler()),
                'stack' => $this->addHandler(new DefaultLogHandler()),
                'slack' => $this->addHandler(new SlackLogHandler()),
                'single' => $this->addHandler(new SingleLogHandler()),
                default => throw new UnsupportedLogDriverException("Unsupported log channel: {$configured}"),
            };
        }

        $logger = parent::getLogger($channel);
        $this->reset();

        return $logger;
    }

    /**
     * Format the log message.
     */
    private function formatMessage(mixed $message): mixed
    {
        return is_array($message) ? json_encode($message) : $message;
    }
}