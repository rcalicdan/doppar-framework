<?php

namespace Phaseolies\Translation;

use Phaseolies\Support\File;

class FileLoader
{
    /**
     * The File instance used for file operations.
     */
    protected File $file;

    /**
     * The base path for translation files.
     */
    protected string $path;

    /**
     * Array of namespace hints for package translations.
     *
     * @var array<string, string>
     */
    protected array $hints = [];

    /**
     * Create a new file loader instance.
     */
    public function __construct(string $path)
    {
        $this->file = new File($path, false);
        $this->path = $path;
    }

    /**
     * Load the messages for the given locale, group, and namespace.
     *
     * @return array<string, mixed>
     */
    public function load(string $locale, string $group, ?string $namespace = null): array
    {
        try {
            return $this->loadPath($this->path, $locale, $group);
        } catch (\RuntimeException $e) {
            if ($namespace) {
                if (isset($this->hints[$namespace])) {
                    try {
                        return $this->loadPath($this->hints[$namespace], $locale, $group);
                    } catch (\RuntimeException) {
                    }
                }

                $vendorPath = $this->path . '/vendor/' . $namespace;
                if (file_exists($vendorPath)) {
                    try {
                        return $this->loadPath($vendorPath, $locale, $group);
                    } catch (\RuntimeException) {
                    }
                }
            }

            throw $e;
        }
    }

    /**
     * Load a translation file from the given path.
     *
     * @return array<string, mixed>
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    protected function loadPath(string $path, string $locale, string $group): array
    {
        if (empty($group)) {
            throw new \InvalidArgumentException('Translation group name cannot be empty');
        }

        $fullPath = rtrim($path, '/') . '/' . $locale . '/' . $group . '.php';

        if (!file_exists($fullPath)) {
            throw new \RuntimeException("Translation file [{$fullPath}] not found");
        }

        return require $fullPath;
    }

    /**
     * Load any namespace override files and merge them with the base translations.
     *
     * @param array<string, mixed> $lines
     * @return array<string, mixed>
     */
    protected function loadNamespaceOverrides(array $lines, string $locale, string $group, string $namespace): array
    {
        $file = "{$this->path}/vendor/{$namespace}/{$locale}/{$group}.php";

        if (file_exists($file)) {
            return array_replace_recursive($lines, require $file);
        }

        return $lines;
    }

    /**
     * Add a namespace hint to the loader.
     */
    public function addNamespace(string $namespace, string $hint): void
    {
        $this->hints[$namespace] = $hint;
    }

    /**
     * Get all registered namespace hints.
     *
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return $this->hints;
    }
}