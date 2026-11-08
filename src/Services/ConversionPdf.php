<?php

namespace Nfgarching\Files\Services;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Traits\FileTrait;
use Spatie\PdfToImage\Pdf;

class ConversionPdf implements ShouldQueue
{
    use FileTrait, Queueable;

    public File $fileitem;

    /**
     * Create a new job instance.
     */
    public function __construct($fileID = 8)
    {
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
        // dump('Starting PDF conversion for File ID: '.$this->fileitem->id.' ('.$this->fileitem->name.')');

        $source = realpath(Storage::disk($this->fileitem->disk)->path($this->fileitem->file)); // dump('Source file: '.$source);

        if (! $source || ! file_exists($source)) {
            $errtext = "#{$this->fileitem->id} Quelldatei nicht gefunden (kein absoluter Pfad auflösbar): {$this->fileitem->file}";
            Log::channel('files')->error($errtext);
            throw new \RuntimeException($errtext);
        }

        $pathToConv = Storage::disk($this->fileitem->disk)->path($this->fileitem->getPath('conversions')); // dump('Conversion path: '.$pathToConv . ' / ' . basename($this->fileitem->file) );

        try {
            $pdf = new Pdf($source);
            $pdf->thumbnailSize(500);
            $pdf->save($pathToConv.'/preview-'.basename($this->fileitem->file).'.jpg');
            $pdf->thumbnailSize(200);
            $pdf->save($pathToConv.'/thumb-'.basename($this->fileitem->file).'.jpg');
        } catch (\Throwable $e) {
            Log::channel('files')->error('#'.$this->fileitem->id.' PDF conversion failed – '.$e->getMessage());
            throw new \RuntimeException('Image::load fehlgeschlagen: '.$e->getMessage());
        }

        $convPath = $this->fileitem->getPath('conversions');
        // Nur preview/thumb setzen: ConversionOcr schreibt parallel 'ocr' in dieselbe Spalte
        $this->fileitem->update(['conversions_disk' => $this->fileitem->disk]);
        $this->fileitem->mergeJson('generated_conversions', [
            'preview' => $convPath.'/preview-'.basename($this->fileitem->file).'.jpg',
            'thumb' => $convPath.'/thumb-'.basename($this->fileitem->file).'.jpg',
        ]);

        // dump('Image conversion completed for File ID: '.$this->fileitem->id);
        Log::channel('files')->info('#'.$this->fileitem->id.' PDF processed for ('.$this->fileitem->file.')');
    }
}
