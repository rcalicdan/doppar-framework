<?php

namespace Phaseolies\Support\Storage;

use Phaseolies\Support\File;

class PublicFileSystem extends FileSystem implements IFileSystem
{
    /**
     * Store file as user defined path.
     */
    #[\Override]
    public function store(string $path, File $file, ?string $fileName = null): bool
    {
        return $this->put($this->isDirectoryExists($path), $file, $fileName);
    }

    /**
     * Return project base path in OS.
     */
    #[\Override]
    public function storeageBasePath(): string
    {
        return $this->filePath;
    }

    /**
     * Get the contents of a file.
     */
    #[\Override]
    public function get(string $path): ?string
    {
        $fullPath = $this->resolvePath($path);

        if ($this->isFile($fullPath)) {
            return $fullPath;
        }

        return null;
    }

    /**
     * Get the contents of a file.
     *
     * @throws FileNotFoundException
     */
    #[\Override]
    public function content(string $path): string
    {
        $fullPath = $this->resolvePath($path);

        if ($this->isFile($fullPath)) {
            $file = new \SplFileObject($fullPath, 'r');
            $contents = '';
            while (!$file->eof()) {
                $contents .= $file->fgets();
            }
            return $contents;
        }

        throw new FileNotFoundException("File does not exist at path {$path}.");
    }

    /**
     * Delete one or more files.
     *
     * @param string|array<int, string> $path
     */
    #[\Override]
    public function delete(string|array $path): bool
    {
        $success = true;

        foreach ((array) $path as $filePath) {
            $fullPath = $this->resolvePath($filePath);

            if (file_exists($fullPath)) {
                if (!unlink($fullPath)) {
                    $success = false;
                }
            } else {
                $success = false;
            }
        }

        return $success;
    }
}