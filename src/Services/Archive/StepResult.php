<?php

namespace Nfgarching\Files\Services\Archive;

use Illuminate\Contracts\Process\ProcessResult;
use Illuminate\Support\Str;

/**
 * Ergebnis eines Archivierungsschritts (PDF/A, Validierung, Signatur).
 *
 * status ist schrittspezifisch (z. B. converted, compliant, signed) oder einer
 * der gemeinsamen Werte disabled, notool, error.
 */
final class StepResult
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly string $status,
        public readonly array $details = [],
        public readonly ?string $error = null,
    ) {}

    public static function disabled(): self
    {
        return new self('disabled');
    }

    public static function notool(string $binary): self
    {
        return new self('notool', [], "Programm '{$binary}' nicht gefunden. Siehe docs/Archivierung.md.");
    }

    public static function error(string $message): self
    {
        return new self('error', [], Str::limit(trim($message), 1000));
    }

    /**
     * Exit-Code 127 = Programm nicht gefunden (Shell-Konvention).
     */
    public static function binaryMissing(ProcessResult $result): bool
    {
        $output = $result->output().$result->errorOutput();

        return $result->exitCode() === 127
            || str_contains($output, 'command not found')
            || str_contains($output, 'No such file or directory');
    }

    public static function processError(ProcessResult $result): self
    {
        return self::error('Exit '.$result->exitCode().': '.$result->errorOutput().$result->output());
    }

    public function failed(): bool
    {
        return in_array($this->status, ['notool', 'error'], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            ['status' => $this->status, ...$this->details, 'error' => $this->error],
            fn ($value) => $value !== null,
        );
    }
}
