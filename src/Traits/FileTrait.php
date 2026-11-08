<?php

namespace Nfgarching\Files\Traits;

const MODEL_FILE = 'Nfgarching\Files\Models\File';

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use Nfgarching\Files\Models\File;

trait FileTrait
{
    private string $disk;

    private string $file;

    private string $filesystem = 'local';

    private function checkExecutable($command): bool
    {
        if (file_exists($command)) {
            dd('Command exist');

            return true;
        }
        dd('Command not exist');

        return false;
    }

    private function checkSource($disk, $file)
    {
        // $this->filesystem = config("filesystems.disks.$disk.driver");

        if (Storage::disk($disk)->exists($file)) {

            $size = 1024 * 1024 * 10; // 10MB
            if (Storage::disk($disk)->size($file) > $size) {
                // dump(Number::fileSize(Storage::disk($disk)->size($file)) . ' > ' . Number::fileSize($size));
                return false;
                // throw new \Exception("File to big: " . Number::fileSize($size) ." < " . Number::fileSize(Storage::disk($disk)->size($file)));
            }

            if ($this->filesystem == 'local') {
                // dump("Local Filesystem: $disk / $file");
                return Storage::disk($disk)->path($file);
            } else {
                dump("Remote Filesystem: $disk / $file");
                $current = Storage::disk($disk)->get($file);
                $newFile = $this->tempFile();
                // Write the content to the file
                file_put_contents($newFile, $current);

                return $newFile;
            }

        } else {

            throw new \Exception("Source not found: $disk / $file");
        }

    }

    public function disk($disk)
    {
        $this->disk = $disk;
        $this->filesytem = config("filesystems.disks.$disk.driver");

        return $this;
    }

    public function file($file)
    {
        $this->file = $file;

        return $this;
    }

    /** @var list<string> */
    private array $tempFiles = [];

    private function tempFile()
    {
        $file = tempnam(sys_get_temp_dir(), 'tmp');
        if ($file !== false) {
            $this->tempFiles[] = $file;
        }

        return $file;
    }

    /**
     * Löscht nur die von diesem Job angelegten Temp-Dateien. Früher wurde
     * /tmp/tmp* gelöscht – das traf auch Dateien parallel laufender Jobs.
     */
    private function tempFileClean()
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        return true;
    }
}
