<?php

namespace Nfgarching\Files\Services\Archive;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

/**
 * Wandelt ein PDF mit ocrmypdf in PDF/A um und optimiert es.
 *
 * --skip-text: Seiten mit vorhandenem Text werden nicht erneut erkannt; die
 * PDF/A-Umwandlung (Ghostscript) und Optimierung laufen trotzdem.
 */
class PdfaConverter
{
    public function convert(string $source, string $target): StepResult
    {
        $config = config('files.archive.pdfa');

        if (! ($config['enabled'] ?? true)) {
            return StepResult::disabled();
        }

        $binary = $config['binary'] ?? 'ocrmypdf';
        $outputType = $config['output_type'] ?? 'pdfa-2';

        $command = [
            $binary,
            '--skip-text',
            '--output-type', $outputType,
            '--optimize', (string) ($config['optimize'] ?? 1),
            '-l', $config['language'] ?? 'deu',
            $source,
            $target,
        ];

        try {
            $result = Process::timeout($config['timeout'] ?? 600)->run($command);
        } catch (ProcessTimedOutException $e) {
            return StepResult::error($e->getMessage());
        }

        if (StepResult::binaryMissing($result)) {
            return StepResult::notool($binary);
        }

        // Exit 10 = PDF/A-Umwandlung fehlgeschlagen, Ausgabe ist ein normales PDF
        if ($result->exitCode() === 10) {
            return new StepResult('error', ['output_type' => $outputType], 'PDF/A-Umwandlung fehlgeschlagen: '.trim($result->errorOutput()));
        }

        if ($result->failed() || ! is_file($target) || filesize($target) === 0) {
            return StepResult::processError($result);
        }

        return new StepResult('converted', ['output_type' => $outputType]);
    }
}
