<?php

namespace Phaseolies\Support\Storage;

use Phaseolies\Support\File;

class FileSystem
{
    /**
     * Full File path directory without filename.
     */
    protected string $filePath;

    /**
     * Custom FileName.
     */
    protected string $fileName;

    public function __construct(string $filePath, string $fileName = '')
    {
        $this->filePath = $filePath;
        $this->fileName = $fileName;
    }

    /**
     * Resolve a caller-supplied relative path against the storage root,
     * rejecting any path that would escape outside the root.
     *
     * @throws \InvalidArgumentException
     */
    protected function resolvePath(string $relativePath): string
    {
        $segments = preg_split('#[/\\\\]#', $relativePath);

        if ($segments === false) {
            throw new \InvalidArgumentException("Invalid path \"{$relativePath}\".");
        }

        foreach ($segments as $segment) {
            if ($segment === '..' || $segment === '.') {
                throw new \InvalidArgumentException(
                    "Invalid path \"{$relativePath}\": path traversal is not allowed."
                );
            }
        }

        $root = rtrim($this->filePath, '/\\');
        $fullPath = $root . '/' . ltrim($relativePath, '/\\');

        $resolvedTarget = realpath($fullPath);

        if ($resolvedTarget !== false) {
            $resolvedRoot = realpath($root);
            $withinRoot = $resolvedRoot !== false && (
                $resolvedTarget === $resolvedRoot ||
                str_starts_with($resolvedTarget, $resolvedRoot . DIRECTORY_SEPARATOR)
            );

            if (!$withinRoot) {
                throw new \InvalidArgumentException(
                    "Invalid path \"{$relativePath}\": resolves outside the storage root."
                );
            }
        }

        return $fullPath;
    }

    /**
     * Move file in local file system.
     */
    public function put(string $path, File $file, ?string $fileName = null): bool
    {
        $fileName = $fileName ?? $this->getFileName($file);
        $stream = fopen($file->getClientOriginalPath(), 'rb');

        if (is_resource($stream)) {
            $this->writeStream($path, $fileName, $stream);
            return true;
        }

        return false;
    }

    /**
     * Get the file name for storage.
     */
    public function getFileName(File $file): string
    {
        if ($this->fileName === '') {
            return $file->getClientOriginalName();
        }

        $fileType = explode('/', $file->getClientOriginalType())[1] ?? '';

        return $this->fileName . '.' . $fileType;
    }

    /**
     * Writes data from a stream to a file at the specified path.
     *
     * @param resource $stream
     */
    public function writeStream(string $path, string $name, $stream): bool
    {
        $realPath = $this->destinationFile($path, $name);

        $outputStream = fopen($realPath, 'wb');

        if ($outputStream === false) {
            throw new \RuntimeException("Unable to open file for writing: {$realPath}");
        }

        $streamSize = $this->getStreamSize($stream);

        // Use 5 MB chunks for files > 50 MB, otherwise 4 KB
        $chunkSize = ($streamSize > 50 * 1024 * 1024) ? 5 * 1024 * 1024 : 4096;

        while (!feof($stream)) {
            $buffer = fread($stream, $chunkSize);
            if ($buffer === false) {
                break;
            }
            fwrite($outputStream, $buffer);
        }

        fclose($stream);
        fclose($outputStream);

        return true;
    }

    /**
     * Retrieves the size of a given stream.
     *
     * @param resource $stream
     */
    private function getStreamSize($stream): int
    {
        $stat = fstat($stream);

        return $stat !== false ? (int) $stat['size'] : 0;
    }

    /**
     * Path is already exist or not.
     */
    public function isDirectoryExists(string $path): string
    {
        $fullPath = $this->resolvePath($path);

        if (! $this->isDirectory($fullPath)) {
            $this->makeDirectory($fullPath, 0755, true);
        }
        return $path;
    }

    /**
     * Determine if the given path is a directory.
     */
    public function isDirectory(string $directory): bool
    {
        return is_dir($directory);
    }

    /**
     * Create the destination full path according in OS.
     */
    public function destinationFile(string $path, string $fileName): string
    {
        return $this->resolvePath($path . '/' . $fileName);
    }

    /**
     * Return project base path in OS.
     */
    public function storeageBasePath(): string
    {
        return $this->filePath;
    }

    /**
     * Determine if the given path is a file.
     */
    public function isFile(string $file): bool
    {
        return is_file($file);
    }

    /**
     * Create a directory.
     */
    public function makeDirectory(string $path, int $mode = 0755, bool $recursive = false, bool $force = false): bool
    {
        if ($force) {
            return @mkdir($path, $mode, $recursive);
        }

        return mkdir($path, $mode, $recursive);
    }
}