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
    /**
     * @param  string|null  $flavour  veraPDF-Profil (z. B. 2b); null = files.archive.validate.flavour
     */
    public function validate(string $path, ?string $flavour = null): StepResult
    {
        $config = config('files.archive.validate');

        if (! ($config['enabled'] ?? true)) {
            return StepResult::disabled();
        }

        $binary = $config['binary'] ?? 'verapdf';
        $flavour ??= (string) ($config['flavour'] ?? '2b');

        try {
            // je Regel nur eine fehlgeschlagene Prüfung ausgeben – große Scans
            // erzeugen sonst Berichte mit Zehntausenden Einträgen
            $result = Process::timeout($config['timeout'] ?? 300)
                ->run([$binary, '--flavour', $flavour, '--format', 'xml', '--maxfailuresdisplayed', '1', $path]);
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

        return new StepResult('noncompliant', $details + [
            'failed_rules' => (int) ($failed[1] ?? 0),
            'rules' => $this->failedRules($result->output()),
        ]);
    }

    /**
     * Verletzte Regeln mit Klausel, Beschreibung und erster Fehlermeldung.
     *
     * @return list<array<string, mixed>>
     */
    private function failedRules(string $xml): array
    {
        $start = strpos($xml, '<?xml');
        $report = $start === false ? false : @simplexml_load_string(substr($xml, $start));
        if ($report === false) {
            return [];
        }

        $rules = [];
        foreach ($report->xpath('//rule[@status="failed"]') ?: [] as $rule) {
            $rules[] = array_filter([
                'specification' => (string) $rule['specification'],
                'clause' => (string) $rule['clause'],
                'test' => (int) $rule['testNumber'],
                'checks' => (int) $rule['failedChecks'],
                'description' => trim((string) $rule->description),
                'message' => trim((string) ($rule->check->errorMessage ?? '')),
            ], fn ($v) => $v !== '' && $v !== 0);

            if (count($rules) >= 50) {
                break;
            }
        }

        return $rules;
    }
}
