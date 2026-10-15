<?php

namespace Phaseolies\Support\Storage;

use Phaseolies\Support\File;

/**
 * @method static bool store(string $path, File $file, ?string $fileName = null)
 * @method static string storeageBasePath()
 * @method static ?string get(string $path)
 * @method static string content(string $path)
 * @method static bool delete(string|array<int, string> $path)
 * @see \Phaseolies\Support\Storage\StorageFileService
 */
class StorageFileService
{
    /**
     * The array of resolved filesystem drivers.
     *
     * @var array<string, IFileSystem>
     */
    protected array $disks = [];

    /**
     * Get a filesystem instance.
     *
     * @throws DiskNotFoundException
     */
    public function disk(?string $name = null): IFileSystem
    {
        $name ??= (string) config('filesystem.default', 'local');
        $path = $this->getDiskPath($name);

        if ($path === null) {
            throw new DiskNotFoundException("Disk [{$name}] has no configured root path.");
        }

        return $this->disks[$name] ??= match ($name) {
            'local' => app(LocalFileSystem::class, [$path]),
            'public' => app(PublicFileSystem::class, [$path]),
            default => throw new DiskNotFoundException("Unsupported disk [{$name}]"),
        };
    }

    /**
     * Return Storage Base Path.
     */
    public function getDiskPath(string $disk): ?string
    {
        return config("filesystem.disks.{$disk}.root");
    }
}
