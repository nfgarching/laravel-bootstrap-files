<?php

namespace Nfgarching\Files\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Services\Archive\PdfaConverter;
use Nfgarching\Files\Services\Archive\PdfaValidator;
use Nfgarching\Files\Services\Archive\PdfInspector;
use Nfgarching\Files\Services\Archive\PdfSigner;
use Nfgarching\Files\Services\Archive\StepResult;

/**
 * Erzeugt die Archivfassung eines PDFs:
 *
 *   Original (unverändert) → PDF/A + Optimierung → veraPDF → Siegel + Zeitstempel
 *
 * Läuft nach ConversionOcr und verwendet, falls vorhanden, dessen
 * durchsuchbares PDF als Ausgangsbasis. Bereits signierte PDFs (beA-Eingänge,
 * Bescheide, signierte Verträge) werden nicht angefasst, weil jede Änderung
 * deren Signatur zerstören würde.
 *
 * Ergebnis: generated_conversions.archive (Pfad) und
 * custom_properties.archive (Status aller Schritte, Prüfsummen).
 */
class ArchivePdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    private string $workdir;

    public function __construct(public readonly int $fileID)
    {
        if ($queue = config('files.archive.queue')) {
            $this->onQueue($queue);
        }
    }

    public function handle(
        PdfInspector $inspector,
        PdfaConverter $converter,
        PdfaValidator $validator,
        PdfSigner $signer,
    ): void {
        if (! config('files.archive.enabled', true)) {
            return;
        }

        $file = File::find($this->fileID);
        if (! $file) {
            Log::channel('files')->error('#'.$this->fileID.' ArchivePdf: File not found');

            return;
        }

        // Original schon durch die (gesiegelte) Archivfassung ersetzt: nicht
        // erneut verarbeiten, sonst gingen die Angaben zum Papierkorb verloren
        if ($file->isOriginalReplaced()) {
            Log::channel('files')->info('#'.$file->id.' ArchivePdf: Original bereits ersetzt, übersprungen');

            return;
        }

        $this->workdir = sys_get_temp_dir().'/files-archive-'.$file->id.'-'.bin2hex(random_bytes(4));
        Filesystem::ensureDirectoryExists($this->workdir, 0700);

        try {
            $this->process($file, $inspector, $converter, $validator, $signer);
        } finally {
            Filesystem::deleteDirectory($this->workdir);
        }
    }

    private function process(
        File $file,
        PdfInspector $inspector,
        PdfaConverter $converter,
        PdfaValidator $validator,
        PdfSigner $signer,
    ): void {
        $original = $this->fetch($file->disk, $file->file, 'original.pdf');
        if (! $original) {
            $this->record($file, ['status' => 'error', 'error' => 'Quelldatei nicht gefunden: '.$file->file]);

            return;
        }

        $base = [
            'source_sha256' => $inspector->hash($original),
            'at' => now()->toIso8601String(),
        ];

        // 1. Signierte Eingänge unverändert lassen
        if ($inspector->isSigned($original) && config('files.archive.skip_signed', true)) {
            $this->record($file, ['status' => 'skipped', 'reason' => 'signed_input'] + $base);
            Log::channel('files')->info('#'.$file->id.' ArchivePdf: signiertes PDF, unverändert gelassen');

            return;
        }

        // 2. Ausgangsbasis: durchsuchbares OCR-PDF, sonst Original
        $current = $original;
        if (($ocrPath = $file->ocrPdfPath()) && ! $file->ocrFailed()) {
            $current = $this->fetch($file->disk, $ocrPath, 'ocr.pdf') ?? $original;
        }

        // 3. PDF/A + Optimierung (entfällt, wenn ocrmypdf schon PDF/A erzeugt hat)
        if ($inspector->hasPdfaIdentification($current)) {
            $pdfa = new StepResult('existing');
        } else {
            $pdfa = $converter->convert($current, $this->workdir.'/pdfa.pdf');
            if ($pdfa->status === 'converted') {
                $current = $this->workdir.'/pdfa.pdf';
            }
        }

        // 4. Validierung – vor der Signatur, die Signatur ändert den Inhalt nicht mehr
        $validation = $pdfa->failed() || $pdfa->status === 'disabled'
            ? new StepResult('skipped')
            : $validator->validate($current);

        // 5. Siegel + Zeitstempel als letzter Schritt
        $signature = $signer->sign($current, $this->workdir.'/signed.pdf');
        if ($signature->status === 'signed') {
            $current = $this->workdir.'/signed.pdf';
        }

        // Nichts erzeugt (weder PDF/A noch Signatur): keine Archivfassung ablegen
        if ($current === $original && $pdfa->status !== 'existing') {
            $this->record($file, [
                'status' => 'error',
                'error' => 'Weder PDF/A noch Signatur erzeugt.',
                'pdfa' => $pdfa->toArray(),
                'validation' => $validation->toArray(),
                'signature' => $signature->toArray(),
            ] + $base);

            return;
        }

        $target = $file->getPath('archive').'/archive-'.pathinfo($file->file, PATHINFO_FILENAME).'.pdf';
        $stream = fopen($current, 'rb');
        Storage::disk($file->disk)->put($target, $stream);
        if (is_resource($stream)) {
            fclose($stream);
        }

        $file->mergeJson('generated_conversions', ['archive' => $target]);
        $this->record($file, [
            'status' => 'archived',
            'disk' => $file->disk,
            'path' => $target,
            'sha256' => $inspector->hash($current),
            'pdfa' => $pdfa->toArray(),
            'validation' => $validation->toArray(),
            'signature' => $signature->toArray(),
        ] + $base);

        foreach (['pdfa' => $pdfa, 'validation' => $validation, 'signature' => $signature] as $step => $result) {
            if ($result->failed() || $result->status === 'nocert') {
                Log::channel('files')->warning('#'.$file->id." ArchivePdf {$step}: {$result->status} ".$result->error);
            }
        }
        Log::channel('files')->info('#'.$file->id.' ArchivePdf: archiviert ('.$target.')');

        // Archivfassung wird Hauptdatei, Original in den Papierkorb (außer „Original Dokument“).
        // Frisch laden – der Schalter kann während der Verarbeitung umgestellt worden sein.
        $file->refresh();
        if ($file->shouldReplaceOriginal()) {
            $file->replaceOriginalWithArchive();
        }
    }

    /**
     * Kopiert eine Datei der Disk in das Arbeitsverzeichnis – funktioniert
     * auch mit S3/SFTP-Disks, die keinen lokalen Pfad haben.
     */
    private function fetch(string $disk, string $path, string $name): ?string
    {
        if (! Storage::disk($disk)->exists($path)) {
            return null;
        }

        $local = $this->workdir.'/'.$name;
        $stream = Storage::disk($disk)->readStream($path);
        if (! $stream) {
            return null;
        }
        file_put_contents($local, $stream);
        fclose($stream);

        return $local;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function record(File $file, array $data): void
    {
        if (($data['status'] ?? null) === 'error') {
            Log::channel('files')->error('#'.$file->id.' ArchivePdf: '.($data['error'] ?? 'Fehler'));
        }

        $file->mergeJson('custom_properties', ['archive' => $data]);
    }
}
