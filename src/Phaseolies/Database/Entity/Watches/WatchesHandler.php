<?php

namespace Phaseolies\Database\Entity\Watches;

use Phaseolies\Database\Entity\Model;

class WatchesHandler
{
    /**
     * Registry of all property watches organized by model class and property name.
     *
     * @var array<string, array<string, list<array{watcher: string, when: string|null}>>>
     */
    public static array $watches = [];

    /**
     * Register a watch entry for a specific model property.
     */
    public static function register(
        string $modelClass,
        string $property,
        string $watcher,
        ?string $when = null
    ): void {
        $entry = ['watcher' => $watcher, 'when' => $when];

        if (!self::watchExists($modelClass, $property, $entry)) {
            self::$watches[$modelClass][$property][] = $entry;
        }
    }

    /**
     * Fire all registered watches for every dirty attribute on the given model.
     *
     * @param array<string, mixed> $dirty
     */
    public static function fireForDirty(Model $model, array $dirty): void
    {
        $modelClass = get_class($model);

        if (empty(self::$watches[$modelClass])) {
            return;
        }

        $originals = $model->getOriginalAttributes();

        foreach ($dirty as $property => $newValue) {
            $entries = self::$watches[$modelClass][$property] ?? [];

            if (empty($entries)) {
                continue;
            }

            $oldValue = $originals[$property] ?? null;

            foreach ($entries as $entry) {
                if (!self::conditionPasses($entry['when'], $oldValue, $newValue, $model)) {
                    continue;
                }

                self::callListener($entry['watcher'], $oldValue, $newValue, $model);
            }
        }
    }

    /**
     * Clear the registry for one or all model classes (useful in tests).
     */
    public static function flush(?string $modelClass = null): void
    {
        if ($modelClass !== null) {
            unset(self::$watches[$modelClass]);
        } else {
            self::$watches = [];
        }
    }

    /**
     * Evaluate the optional condition attached to a watch entry.
     */
    protected static function conditionPasses(?string $when, mixed $old, mixed $new, Model $model): bool
    {
        if ($when === null) {
            return true;
        }

        if (class_exists($when)) {
            $condition = app($when);

            if (!($condition instanceof WatchConditionInterface)) {
                throw new \RuntimeException(
                    "Watch condition class '{$when}' must implement "
                        . WatchConditionInterface::class
                );
            }

            return $condition->evaluate($old, $new, $model);
        }

        if (!method_exists($model, $when)) {
            throw new \RuntimeException(
                "Watch condition method '{$when}' does not exist on " . get_class($model)
            );
        }

        $result = $model->$when($old, $new);

        if (!is_bool($result)) {
            throw new \RuntimeException(
                "Watch condition method '{$when}' must return bool, got " . gettype($result)
            );
        }

        return $result;
    }

    /**
     * Resolve and invoke the watcher's handle() method.
     */
    protected static function callListener(
        string $watcherClass,
        mixed $old,
        mixed $new,
        Model $model
    ): void {
        $watcher = app($watcherClass);

        if (is_object($watcher) && method_exists($watcher, 'handle')) {
            $watcher->handle($old, $new, $model);
            return;
        }

        throw new \RuntimeException(
            "Watch watcher '{$watcherClass}' must expose a handle(mixed \$old, mixed \$new, Model \$model): void method."
        );
    }

    /**
     * Check whether an identical watch entry is already registered.
     *
     * @param array{watcher: string, when: string|null} $entry
     */
    protected static function watchExists(string $modelClass, string $property, array $entry): bool
    {
        foreach (self::$watches[$modelClass][$property] ?? [] as $existing) {
            if ($existing['watcher'] === $entry['watcher'] && $existing['when'] === $entry['when']) {
                return true;
            }
        }

        return false;
    }
}