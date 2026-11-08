<?php

namespace Nfgarching\Files\Services;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Traits\FileTrait;
use Spatie\Image\Image;

class ConversionImg implements ShouldQueue
{
    use FileTrait, Queueable;

    public File $fileitem;

    /**
     * Create a new job instance.
     */
    public function __construct($fileID = null)
    {
        // dump('Initializing ConversionImg for File ID: '.$fileID);
        try {
            $this->fileitem = File::findOrFail($fileID);
        } catch (\Exception $e) {
            Log::channel('files')->error('#'.$fileID.' File not found for conversion – '.$e->getMessage());
            throw $e;
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // dump('Starting image conversion for File ID: '.$this->fileitem->id.' ('.$this->fileitem->name.')');

        $source = realpath(Storage::disk($this->fileitem->disk)->path($this->fileitem->file)); // dump('Source file: '.$source);

        if (! $source || ! file_exists($source)) {
            $errtext = "#{$this->fileitem->id} Quelldatei nicht gefunden (kein absoluter Pfad auflösbar): {$this->fileitem->file}";
            Log::channel('files')->error($errtext);
            throw new \RuntimeException($errtext);
        }

        $pathToConv = Storage::disk($this->fileitem->disk)->path($this->fileitem->getPath('conversions')); // dump('Conversion path: '.$pathToConv . ' / ' . basename($this->fileitem->file) );
        $convPath = $this->fileitem->getPath('conversions');

        // HEIC/HEIF zeigen Browser (außer Safari) nicht an, und Imagick kann HEIC nur lesen,
        // nicht schreiben – daher alle Konvertierungen als JPEG, plus eine 'display'-Fassung in voller Größe.
        $basename = basename($this->fileitem->file);
        $isHeic = $this->isHeic();
        if ($isHeic) {
            $basename .= '.jpg';
        }

        $conversions = [
            'preview' => $convPath.'/preview-'.$basename,
            'thumb' => $convPath.'/thumb-'.$basename,
        ];
        if ($isHeic) {
            $conversions['display'] = $convPath.'/display-'.$basename;
        }

        try {
            if ($isHeic) {
                Image::load($source)->save($pathToConv.'/display-'.$basename);
            }
            $img = Image::load($source);
            $res = $img->width(500)->save($pathToConv.'/preview-'.$basename);
            $res = $img->width(200)->save($pathToConv.'/thumb-'.$basename);
        } catch (\Throwable $e) {
            Log::channel('files')->error('#'.$this->fileitem->id.' Image conversion failed – '.$e->getMessage());
            throw new \RuntimeException('Image::load fehlgeschlagen: '.$e->getMessage());
        }

        $this->fileitem->update(['conversions_disk' => $this->fileitem->disk]);
        $this->fileitem->mergeJson('generated_conversions', $conversions);

        // dump('Image conversion completed for File ID: '.$this->fileitem->id);
        Log::channel('files')->info('#'.$this->fileitem->id.' IMG processed for ('.$this->fileitem->file.')');
    }

    private function isHeic(): bool
    {
        return in_array(strtolower((string) $this->fileitem->mime), ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'])
            || in_array(strtolower(pathinfo($this->fileitem->file, PATHINFO_EXTENSION)), ['heic', 'heif']);
    }
}
