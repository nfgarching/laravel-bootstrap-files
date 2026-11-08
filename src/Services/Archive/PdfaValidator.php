<?php

namespace Nfgarching\Files\Services\Archive;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

/**
 * Prüft die PDF/A-Konformität mit veraPDF, dem Referenz-Validator der
 * PDF Association. Ohne Validierung ist „PDF/A“ nur eine Behauptung der Datei.
 */
class PdfaValidator
{
    public function validate(string $path): StepResult
    {
        $config = config('files.archive.validate');

        if (! ($config['enabled'] ?? true)) {
            return StepResult::disabled();
        }

        $binary = $config['binary'] ?? 'verapdf';
        $flavour = (string) ($config['flavour'] ?? '2b');

        try {
            $result = Process::timeout($config['timeout'] ?? 300)
                ->run([$binary, '--flavour', $flavour, '--format', 'xml', $path]);
        } catch (ProcessTimedOutException $e) {
            return StepResult::error($e->getMessage());
        }

        if (StepResult::binaryMissing($result)) {
            return StepResult::notool($binary);
        }

        // veraPDF meldet Nichtkonformität ebenfalls mit Exit-Code ≠ 0; maßgeblich
        // ist das Attribut isCompliant im Bericht.
        if (! preg_match('/isCompliant="(true|false)"/', $result->output(), $match)) {
            return StepResult::processError($result);
        }

        $details = ['flavour' => $flavour];

        if ($match[1] === 'true') {
            return new StepResult('compliant', $details);
        }

        preg_match('/failedRules="(\d+)"/', $result->output(), $failed);

        return new StepResult('noncompliant', $details + ['failed_rules' => (int) ($failed[1] ?? 0)]);
    }
}
