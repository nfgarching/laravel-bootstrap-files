<?php

namespace Nfgarching\Files\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Services\Inspection\PdfInspection;

/**
 * Prüft ein PDF (Eigenschaften, Signaturen, PDF/A, Prüfsummen) und speichert
 * das Ergebnis in custom_properties.inspection. Läuft nach ArchivePdf, damit
 * auch die Archivfassung erfasst wird.
 */
class InspectPdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $fileID)
    {
        if ($queue = config('files.archive.queue')) {
            $this->onQueue($queue);
        }
    }

    public function handle(PdfInspection $inspection): void
    {
        $file = File::find($this->fileID);
        if (! $file) {
            Log::channel('files')->error('#'.$this->fileID.' InspectPdf: File not found');

            return;
        }

        $inspection->inspect($file);
        Log::channel('files')->info('#'.$file->id.' InspectPdf: geprüft');
    }
}
