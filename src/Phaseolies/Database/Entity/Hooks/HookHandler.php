<?php

namespace Phaseolies\Database\Entity\Hooks;

use Phaseolies\Database\Entity\Model;

class HookHandler
{
    /**
     * Registry of all model hooks organized by model class and event name.
     *
     * @var array<string, array<string, list<array{handler: callable|string, when: bool|callable}>>>
     */
    public static array $hooks = [];

    /**
     * Register hooks for a model.
     *
     * @param array<string, mixed> $hooks
     */
    public static function register(string $modelClass, array $hooks): void
    {
        foreach ($hooks as $event => $handler) {
            $normalized = self::normalizeHandler($handler);
            if (!self::hookExists($modelClass, $event, $normalized)) {
                self::$hooks[$modelClass][$event][] = $normalized;
            }
        }
    }

    /**
     * Check hook exists or not.
     *
     * @param array{handler: callable|string, when: bool|callable} $newHook
     */
    protected static function hookExists(string $modelClass, string $event, array $newHook): bool
    {
        if (!isset(self::$hooks[$modelClass][$event])) {
            return false;
        }

        foreach (self::$hooks[$modelClass][$event] as $existingHook) {
            if (self::hooksEqual($existingHook, $newHook)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determines if two hook configurations are identical.
     *
     * @param array{handler: callable|string, when: bool|callable} $hook1
     * @param array{handler: callable|string, when: bool|callable} $hook2
     */
    protected static function hooksEqual(array $hook1, array $hook2): bool
    {
        if ($hook1['handler'] !== $hook2['handler']) {
            return false;
        }

        if (is_callable($hook1['when']) && is_callable($hook2['when'])) {
            return $hook1['when'] == $hook2['when'];
        }

        return $hook1['when'] === $hook2['when'];
    }

    /**
     * Normalize handler configuration.
     *
     * @return array{handler: callable|string, when: bool|callable}
     */
    protected static function normalizeHandler(mixed $handler): array
    {
        if (is_callable($handler)) {
            return [
                'handler' => $handler,
                'when' => true
            ];
        }

        if (is_string($handler)) {
            return [
                'handler' => $handler,
                'when' => true
            ];
        }

        if (is_array($handler)) {
            return [
                'handler' => $handler['handler'] ?? '',
                'when' => $handler['when'] ?? true
            ];
        }

        throw new \InvalidArgumentException('Invalid hook handler format');
    }

    /**
     * Execute hooks for a given event.
     */
    public static function execute(string $event, Model $model): void
    {
        $modelClass = get_class($model);
        $hooks = self::$hooks[$modelClass][$event] ?? [];

        if (str_starts_with($event, 'before_')) {
            if (empty($model->getOriginalAttributes())) {
                $model->setOriginalAttributes($model->getAttributes());
            }
        }

        foreach ($hooks as $hook) {
            if (!self::shouldExecute($model, $hook['when'])) {
                continue;
            }

            self::executeHandler($hook['handler'], $model);
        }
    }

    /**
     * Check if a hook should be executed.
     */
    protected static function shouldExecute(Model $model, callable|bool $condition = true): bool
    {
        if (is_bool($condition)) {
            return $condition;
        }

        $result = $condition($model);

        if (!is_bool($result)) {
            throw new \RuntimeException(
                "Hook condition must return boolean, got " . gettype($result)
            );
        }

        return $result;
    }

    /**
     * Execute a single handler.
     */
    protected static function executeHandler(callable|string $handler, Model $model): void
    {
        if (is_callable($handler)) {
            $handler($model);
            return;
        }

        if (is_string($handler)) {
            $instance = app($handler);
            if (is_object($instance) && method_exists($instance, 'handle')) {
                $instance->handle($model);
                return;
            }
        }

        throw new \RuntimeException("Invalid hook handler for model " . get_class($model));
    }

    /**
     * Check if a hook should be executed for unit testing.
     */
    public static function shouldExecuteForUnitTest(Model $model, callable|bool $condition = true): bool
    {
        return self::shouldExecute($model, $condition);
    }
}