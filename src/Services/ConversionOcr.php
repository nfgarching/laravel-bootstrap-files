<?php

namespace Nfgarching\Files\Services;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Traits\FileTrait;

/**
 * OCR-Verarbeitung von PDFs via ocrmypdf/tesseract.
 *
 * Benötigt die System-Tools ocrmypdf, tesseract-ocr(-deu) und jbig2enc.
 * Dauerhafte Installation im Docker/Sail-Image: siehe docs/OCR-Installation.md
 */
class ConversionOcr implements ShouldQueue
{
    use FileTrait, Queueable;

    public File $fileitem;

    /**
     * Create a new job instance.
     */
    public function __construct($fileID = null)
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
        // dump('Starting OCR conversion for File ID: '.$this->fileitem->id.' ('.$this->fileitem->name.')');

        $errtxt = '';
        $text = false;
        $type = 'ocrtext';

        $pdfFile = $this->tempFile();
        $txtFile = $this->tempFile();

        if (! $pdfFile || ! $txtFile) {
            $this->tempFileClean();
            $this->recordOcrFailure('errocr', 'Failed to create temporary files for OCR processing.');

            return;
        }
        if (! is_writable($pdfFile) || ! is_writable($txtFile)) {
            $this->tempFileClean();
            $this->recordOcrFailure('errocr', 'Temporary files are not writable: '.$pdfFile.', '.$txtFile);

            return;
        }
        if (! Storage::disk($this->fileitem->disk)->exists($this->fileitem->file)) {
            $this->tempFileClean();
            $this->recordOcrFailure('errocr', 'Source file does not exist in storage: '.$this->fileitem->file);

            return;
        }

        $source = realpath(Storage::disk($this->fileitem->disk)->path($this->fileitem->file)); // dump('Source file: '.$source);

        if (! $source || ! file_exists($source)) {
            $this->tempFileClean();
            $this->recordOcrFailure('errocr', "Quelldatei nicht gefunden (kein absoluter Pfad auflösbar): {$this->fileitem->file}");

            return;
        }

        // Ausgabe direkt als PDF/A (Typ und Optimierung aus files.archive.pdfa),
        // damit ArchivePdf keine zweite Ghostscript-Umwandlung braucht.
        $outputType = escapeshellarg((string) config('files.archive.pdfa.output_type', 'pdfa-2'));
        $optimize = (int) config('files.archive.pdfa.optimize', 1);

        $command = "ocrmypdf -l deu '{$source}' '{$pdfFile}' --image-dpi 300 --skip-text --output-type {$outputType} --optimize {$optimize} --sidecar {$txtFile} 2>&1";

        try {
            // dump('Running command: '.$command);
            $result = Process::timeout(280)->run($command);
        } catch (ProcessTimedOutException $ex) {
            $this->tempFileClean();
            $this->recordOcrFailure('errocr', $ex->getMessage());

            return;
        }

        if ($result->failed() && (str_contains($result->output(), 'not found') || str_contains($result->output(), 'No such file or directory'))) {
            $this->tempFileClean();
            $errtext = "OCR-Tool nicht gefunden. Installation überprüfen.
                        Docker/Sail: siehe docs/OCR-Installation.md
                        Direkt: sudo apt install ocrmypdf tesseract-ocr tesseract-ocr-deu jbig2

                        Fehler - Output überprüfen:\n\n".
                        $result->output();

            // OCR-Tool fehlt: Verarbeitung nicht hart abbrechen, sondern als 'noocr'
            // vermerken. So schlägt der Job nicht fehl und die restliche Verarbeitung
            // (inkl. NewFiles-Event) läuft weiter.
            $this->recordOcrFailure('noocr', $errtext);

            // sudo apt install ocrmypdf tesseract-ocr tesseract-ocr-deu jbig2

            return;
        }

        if ($result->failed() && str_contains($result->output().$result->errorOutput(), 'DigitalSignatureError')) {
            // dump('PDF has digital signature, falling back to pdf2txt.');
            $text = $this->checkText($source);
            $type = 'digitext';
        } elseif ($result->failed()) {
            $this->tempFileClean();
            $errtext = $command.'\n'.
                $result->output().'\n'.
                $result->errorOutput();
            $this->recordOcrFailure('errocr', $errtext);

            return;
        }

        if ($text === false) {
            $text = file_get_contents($txtFile);
            // dump('OCR output text length: '.strlen($text));
            // dump('OCR output text preview: '.substr($text, 0, 200));

            if (strpos($text, 'OCR skipped') !== false) {
                $errtxt = $result->output();
                $text = $this->checkText($source);
                $type = 'text';
                // dump($errtxt);
            }
        }

        if ($text) {
            // dump($text);
            // Save OCR'd PDF and text in OCR directory
            $baseFilename = pathinfo($this->fileitem->file, PATHINFO_FILENAME);

            // Save OCR PDF – bei digitext (signiertes PDF) liefert ocrmypdf keine Ausgabe
            $ocrPdfPath = null;
            clearstatcache(true, $pdfFile);
            if (filesize($pdfFile) > 0) {
                $ocrPdfPath = $this->fileitem->getPath('ocr').'/ocr-'.$baseFilename.'.pdf';
                Storage::disk($this->fileitem->disk)->put($ocrPdfPath, file_get_contents($pdfFile));
            }

            // Save OCR text
            $ocrTxtFilename = 'ocr-'.$baseFilename.'.txt';
            $ocrTxtPath = $this->fileitem->getPath('ocr').'/'.$ocrTxtFilename;
            Storage::disk($this->fileitem->disk)->put(
                $ocrTxtPath,
                $text
            ); // dump('Saved OCR text: '.$ocrTxtFilename);

            // Nur Links ablegen: OCR-PDF neben preview/thumb, Text-Datei in custom_properties
            $this->fileitem->mergeJson('generated_conversions', ['ocr' => $ocrPdfPath]);
            $this->fileitem->mergeJson('custom_properties', [
                'ocr' => [
                    'type' => $type,
                    'disk' => $this->fileitem->disk,
                    'text' => $ocrTxtPath,
                    'size' => Storage::disk($this->fileitem->disk)->size($ocrTxtPath),
                    'at' => now()->toIso8601String(),
                ],
            ]);
        } else {
            $this->tempFileClean();
            $errtext = $command.'\n'.
                $result->output().'\n'.
                $result->errorOutput();
            $this->recordOcrFailure('errocr', $errtext);

            return;
        }

        $this->tempFileClean();

        // dump('OCR conversion completed for File ID: '.$this->fileitem->id);
        Log::channel('files')->info('#'.$this->fileitem->id.' OCR processed for ('.$this->fileitem->file.')');
    }

    /**
     * Record an OCR failure in custom_properties.ocr instead of aborting the job.
     *
     * OCR is best-effort enrichment; a failure must not kill the surrounding
     * upload/NewFiles flow. The failure is logged in full and vermerkt
     * ('noocr' = OCR-Tool fehlt, 'errocr' = Verarbeitungsfehler).
     */
    private function recordOcrFailure(string $type, string $errtext): void
    {
        Log::channel('files')->error('#'.$this->fileitem->id.' '.$errtext);

        $this->fileitem->mergeJson('custom_properties', [
            'ocr' => [
                'type' => $type,
                'disk' => $this->fileitem->disk,
                'text' => null,
                'error' => Str::limit(trim($errtext), 1000),
                'at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Check if PDF contains text using pdf2txt.
     */
    private function checkText(string $source): string
    {
        $command = "pdf2txt '{$source}' 2>&1";
        $result = Process::run($command);

        return $result->output();
    }
}
