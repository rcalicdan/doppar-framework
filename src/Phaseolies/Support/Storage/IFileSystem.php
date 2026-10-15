<?php

namespace Phaseolies\Support\Storage;

use Phaseolies\Support\File;

interface IFileSystem
{
    /**
     * Store file at user defined path.
     */
    public function store(string $path, File $file, ?string $fileName = null): bool;

    /**
     * Return project storage base path in OS.
     */
    public function storeageBasePath(): string;

    /**
     * Get the path of a file.
     */
    public function get(string $path): ?string;

    /**
     * Get the contents of a file.
     *
     * @throws FileNotFoundException
     */
    public function content(string $path): string;

    /**
     * Delete one or more files.
     *
     * @param string|array<int, string> $path
     */
    public function delete(string|array $path): bool;
}