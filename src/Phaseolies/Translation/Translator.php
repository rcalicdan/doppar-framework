<?php

namespace Phaseolies\Translation;

class Translator extends FileLoader
{
    /**
     * The file loader instance responsible for loading translation files.
     */
    protected FileLoader $loader;

    /**
     * The current locale being used for translations.
     */
    protected string $locale;

    /**
     * The fallback locale to use when a translation isn't found.
     */
    protected string $fallback;

    /**
     * Array of loaded translation lines grouped by namespace, group, and locale.
     *
     * @var array<string, array<string, array<string, mixed>>>
     */
    protected array $loaded = [];

    /**
     * Create a new translator instance.
     */
    public function __construct(FileLoader $loader, string $locale)
    {
        parent::__construct($loader->path);

        $this->loader = $loader;
        $this->locale = $locale;
        $this->fallback = (string) config('app.fallback_locale', 'en');
    }

    /**
     * Get the translation for the given key.
     *
     * @param array<string, mixed> $replace
     * @return string|array<string, mixed>
     */
    public function trans(string $key, array $replace = [], ?string $locale = null): string|array
    {
        return $this->get($key, $replace, $locale);
    }

    /**
     * Get the translation for the given key.
     *
     * @param array<string, mixed> $replace
     * @return string|array<string, mixed>
     */
    public function get(string $key, array $replace = [], ?string $locale = null): string|array
    {
        [$namespace, $group, $item] = $this->parseKey($key);

        $targetLocale = $locale ?: $this->locale;

        if ($group === '*') {
            return $key;
        }

        $this->loadTranslations($namespace, $group, $targetLocale);

        $line = $this->getLine(
            $namespace,
            $group,
            $targetLocale,
            $item,
            $replace
        );

        if (!is_null($line)) {
            return $line;
        }

        if ($this->fallback && $targetLocale !== $this->fallback) {
            return $this->get($key, $replace, $this->fallback);
        }

        return $key;
    }

    /**
     * Parse a key into namespace, group, and item.
     *
     * @return array{0: string|null, 1: string|null, 2: string}
     */
    protected function parseKey(string $key): array
    {
        if (strpos($key, '::') !== false) {
            $segments = explode('::', $key, 2);
            $namespace = $segments[0];
            $rest = $segments[1];

            if (strpos($rest, '.') !== false) {
                list($group, $item) = explode('.', $rest, 2);
                return [$namespace, $group, $item];
            }

            return [$namespace, '*', $rest];
        }

        if (strpos($key, '.') !== false) {
            list($group, $item) = explode('.', $key, 2);
            return [null, $group, $item];
        }

        return [null, '*', $key];
    }

    /**
     * Parse a namespaced key into components.
     *
     * @return array{0: string|null, 1: string|null, 2: string}
     */
    protected function parseNamespacedKey(string $key): array
    {
        $segments = explode('::', $key);

        if (count($segments) !== 2) {
            return [null, '*', $key];
        }

        $item = $segments[1];

        if (strpos($item, '.') !== false) {
            list($group, $item) = explode('.', $item, 2);
            return [$segments[0], $group, $item];
        }

        return [$segments[0], null, $item];
    }

    /**
     * Get a translation line from the loaded translations.
     *
     * @param array<string, mixed> $replace
     * @return string|array<string, mixed>|null
     */
    protected function getLine(?string $namespace, ?string $group, string $locale, string $item, array $replace): string|array|null
    {
        $this->loadTranslations($namespace, $group, $locale);

        $keys = explode('.', $item);

        $namespaceKey = $namespace ?? '';
        $groupKey = $group ?? '';

        $line = $this->loaded[$namespaceKey][$groupKey][$locale] ?? null;

        foreach ($keys as $key) {
            if (!is_array($line)) {
                return null;
            }
            $line = $line[$key] ?? null;
        }

        if (is_string($line)) {
            return $this->makeReplacements($line, $replace);
        } elseif (is_array($line) && count($line) > 0) {
            return $line;
        }

        return null;
    }

    /**
     * Make the place-holder replacements on a translation line.
     *
     * @param array<string, mixed> $replace
     */
    public function makeReplacements(string $line, array $replace): string
    {
        foreach ($replace as $key => $value) {
            $valStr = (string) $value;
            $line = str_replace(
                [':' . $key, ':' . strtoupper($key), ':' . ucfirst($key)],
                [$valStr, strtoupper($valStr), ucfirst($valStr)],
                $line
            );
        }

        return $line;
    }

    /**
     * Load the specified language group.
     */
    public function loadTranslations(?string $namespace, ?string $group, string $locale): void
    {
        if ($this->isLoaded($namespace, $group, $locale)) {
            return;
        }

        $lines = $this->loader->load($locale, $group ?? '*', $namespace);

        $namespaceKey = $namespace ?? '';
        $groupKey = $group ?? '';

        $this->loaded[$namespaceKey][$groupKey][$locale] = $lines;
    }

    /**
     * Determine if the given group has been loaded.
     */
    protected function isLoaded(?string $namespace, ?string $group, string $locale): bool
    {
        $namespaceKey = $namespace ?? '';
        $groupKey = $group ?? '';

        return isset($this->loaded[$namespaceKey][$groupKey][$locale]);
    }

    /**
     * Set the current locale.
     */
    public function setLocale(string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Get the current locale.
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Set the fallback locale.
     */
    public function setFallback(string $fallback): void
    {
        $this->fallback = $fallback;
    }

    /**
     * Get the fallback locale.
     */
    public function getFallback(): string
    {
        return $this->fallback;
    }
}