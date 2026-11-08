<?php

namespace Nfgarching\Files\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Core\Helpers\Paginate;

class DiskService
{
    /**
     * Paginierte Liste der Unterverzeichnisse.
     */
    public function getDirectories(string $disk, ?string $path, int $perPage = 8, string $pageName = 'dirs_page'): LengthAwarePaginator
    {
        $dirs = Storage::disk($disk)->directories($path);
        $dirs = Arr::sort($dirs);

        return Paginate::paginate($dirs, $perPage, $pageName)->withQueryString();
    }

    /**
     * Paginierte Liste der Dateien, optional nach Mimetype gefiltert.
     */
    public function getFiles(string $disk, ?string $path, int $perPage = 10, string $pageName = 'file_page', string $sort = 'name', string $direction = 'asc', ?string $mimeFilter = null, ?int $page = null): LengthAwarePaginator
    {
        $files = Storage::disk($disk)->files($path);

        if ($mimeFilter) {
            $files = array_values(array_filter(
                $files,
                fn ($file) => Storage::disk($disk)->mimeType($file) === $mimeFilter,
            ));
        }

        $files = $this->sortFiles($disk, $files, $sort, $direction);

        return Paginate::paginate($files, $perPage, $pageName, $page)->withQueryString();
    }

    /**
     * Alle eindeutigen Mimetypes der Dateien im Verzeichnis (nicht für FTP/SFTP).
     *
     * @return array<string>
     */
    public function getMimeTypes(string $disk, ?string $path): array
    {
        $driver = config("filesystems.disks.$disk.driver");

        if (in_array($driver, ['ftp', 'sftp'])) {
            return [];
        }

        $files = Storage::disk($disk)->files($path);
        $types = [];

        foreach ($files as $file) {
            $type = Storage::disk($disk)->mimeType($file);
            if ($type && ! in_array($type, $types)) {
                $types[] = $type;
            }
        }

        sort($types);

        return $types;
    }

    private function sortFiles(string $disk, array $files, string $sort, string $direction): array
    {
        $files = match ($sort) {
            'size' => $this->sortBySize($disk, $files),
            'lastModified' => $this->sortByLastModified($disk, $files),
            default => $this->sortByName($files),
        };

        return $direction === 'desc' ? array_reverse($files) : $files;
    }

    private function sortByName(array $files): array
    {
        usort($files, fn ($a, $b) => strcmp(basename($a), basename($b)));

        return $files;
    }

    private function sortByLastModified(string $disk, array $files): array
    {
        usort($files, fn ($a, $b) => Storage::disk($disk)->lastModified($a) <=> Storage::disk($disk)->lastModified($b));

        return $files;
    }

    private function sortBySize(string $disk, array $files): array
    {
        $driver = config("filesystems.disks.$disk.driver");

        if (in_array($driver, ['ftp', 'sftp'])) {
            return $this->sortByName($files);
        }

        usort($files, fn ($a, $b) => Storage::disk($disk)->size($a) <=> Storage::disk($disk)->size($b));

        return $files;
    }

    /**
     * Berechnet den übergeordneten Verzeichnispfad.
     */
    public function getUpDirectory(?string $path): string
    {
        if (! $path || $path === basename($path)) {
            return '';
        }

        return substr($path, 0, -strlen(basename($path)) - 1);
    }

    /**
     * Löscht eine Datei von der Disk.
     */
    public function deleteFile(string $disk, string $file): bool
    {
        return Storage::disk($disk)->delete($file);
    }

    /**
     * Benennt eine Datei um (innerhalb desselben Verzeichnisses).
     */
    public function renameFile(string $disk, string $filePath, string $newName): bool
    {
        $directory = dirname($filePath);
        $newPath = ($directory === '.' ? '' : $directory.'/').$newName;

        return Storage::disk($disk)->move($filePath, $newPath);
    }
}
