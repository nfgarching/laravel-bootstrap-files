<?php

namespace Nfgarching\Files\Services\Inspection;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Symfony\Component\Process\ExecutableFinder;

/**
 * Prüft die Signaturen eines PDFs mit der pyHanko-Bibliothek
 * (resources/python/pdf_signatures.py) und liefert je Signatur
 * Unterzeichner, Zeitstempel, Umfang und Bewertung als Array.
 *
 * Python-Interpreter: files.inspect.python, sonst der Interpreter aus der
 * Shebang-Zeile des pyhanko-Kommandos (pipx/venv-Installation), sonst python3.
 */
class SignatureInspector
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(string $path): array
    {
        $command = [$this->python(), '-I', self::script()];
        foreach (self::trustCertificates() as $certificate) {
            array_push($command, '--trust', $certificate);
        }
        if (config('files.inspect.fetch', true)) {
            $command[] = '--fetch';
        }
        $command[] = $path;

        try {
            $result = Process::timeout((int) config('files.inspect.timeout', 120))->run($command);
        } catch (ProcessTimedOutException $e) {
            return ['status' => 'error', 'error' => $e->getMessage()];
        }

        if ($result->exitCode() === 127) {
            return ['status' => 'notool', 'error' => "Python-Interpreter '{$command[0]}' nicht gefunden."];
        }

        $data = json_decode(trim($result->output()), true);
        if (! is_array($data)) {
            return ['status' => 'error', 'error' => Str::limit(trim($result->errorOutput().$result->output()), 1000)];
        }

        if (! ($data['ok'] ?? false)) {
            return ['status' => ($data['notool'] ?? false) ? 'notool' : 'error', 'error' => $data['error'] ?? 'unbekannter Fehler'];
        }

        unset($data['ok']);

        return ['status' => $data['signatures'] ? 'signed' : 'unsigned'] + $data;
    }

    public static function script(): string
    {
        return dirname(__DIR__, 3).'/resources/python/pdf_signatures.py';
    }

    /**
     * Vertrauensanker aus files.inspect.trust (Pfade relativ zu storage/);
     * nicht vorhandene Dateien werden übergangen.
     *
     * @return list<string>
     */
    public static function trustCertificates(): array
    {
        $paths = config('files.inspect.trust', []);
        if (is_string($paths)) {
            $paths = explode(',', $paths);
        }

        return collect($paths)
            ->map(fn ($path) => trim((string) $path))
            ->filter()
            ->map(fn ($path) => str_starts_with($path, '/') ? $path : storage_path($path))
            ->filter(fn ($path) => is_readable($path))
            ->values()
            ->all();
    }

    public function python(): string
    {
        if ($python = config('files.inspect.python')) {
            return $python;
        }

        $binary = (string) config('files.archive.sign.binary', 'pyhanko');
        $pyhanko = str_starts_with($binary, '/') ? $binary : (new ExecutableFinder)->find($binary);
        if ($pyhanko && ($handle = @fopen($pyhanko, 'rb'))) {
            $line = (string) fgets($handle);
            fclose($handle);
            if (preg_match('/^#!\s*(\/\S*python[\d.]*)\s*$/', $line, $m) && is_executable($m[1])) {
                return $m[1];
            }
        }

        return 'python3';
    }
}
