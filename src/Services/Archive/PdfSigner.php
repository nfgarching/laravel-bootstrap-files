<?php

namespace Nfgarching\Files\Services\Archive;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;

/**
 * Bringt mit pyHanko eine PAdES-Signatur (unsichtbares Siegel) samt
 * Zeitstempel (RFC 3161) an.
 *
 * Schlüsselmaterial: PKCS#12-Datei (.p12/.pfx). Das Passwort wird über stdin
 * übergeben und taucht daher weder in der Prozessliste noch im Log auf.
 */
class PdfSigner
{
    public function sign(string $source, string $target): StepResult
    {
        $config = config('files.archive.sign');

        if (! ($config['enabled'] ?? true)) {
            return StepResult::disabled();
        }

        $certificate = self::certificatePath();
        if (! $certificate) {
            return new StepResult('nocert', [], 'Kein Zertifikat konfiguriert (FILES_SIGN_CERT).');
        }
        if (! is_readable($certificate)) {
            return StepResult::error("Zertifikat nicht lesbar: {$certificate}");
        }

        $password = (string) ($config['password'] ?? '');
        $binary = $config['binary'] ?? 'pyhanko';
        $tsaUrl = ($config['tsa_url'] ?? null) ?: null;

        $command = [$binary, 'sign', 'addsig', '--field', $config['field_name'] ?? 'Archivsiegel'];

        foreach (['reason' => '--reason', 'location' => '--location', 'contact' => '--contact-info'] as $key => $option) {
            if (! empty($config[$key])) {
                array_push($command, $option, $config[$key]);
            }
        }
        if ($config['pades'] ?? true) {
            $command[] = '--use-pades';
        }
        if ($tsaUrl) {
            array_push($command, '--timestamp-url', $tsaUrl);
        }
        if ($config['with_validation_info'] ?? false) {
            $command[] = '--with-validation-info';
        }

        $command[] = 'pkcs12';
        if ($password === '') {
            $command[] = '--no-pass';
        }
        array_push($command, $source, $target, $certificate);

        try {
            $process = Process::timeout($config['timeout'] ?? 120);
            if ($password !== '') {
                $process = $process->input($password."\n");
            }
            $result = $process->run($command);
        } catch (ProcessTimedOutException $e) {
            return StepResult::error($e->getMessage());
        }

        if (StepResult::binaryMissing($result)) {
            return StepResult::notool($binary);
        }

        if ($result->failed() || ! is_file($target) || filesize($target) === 0) {
            // pyHanko meldet ein falsches Passwort nur als „Generic processing error“
            if ($this->subject($certificate, $password) === null) {
                return StepResult::error('Zertifikat nicht lesbar – Passwort (FILES_SIGN_PASSWORD) falsch oder Format nicht unterstützt. '
                    .$result->errorOutput());
            }

            return StepResult::processError($result);
        }

        return new StepResult('signed', [
            'subject' => $this->subject($certificate, $password),
            'field' => $config['field_name'] ?? 'Archivsiegel',
            'pades' => (bool) ($config['pades'] ?? true),
            'timestamp' => $tsaUrl,
        ]);
    }

    /**
     * Absoluter Pfad des Zertifikats; relative Angaben gelten ab storage/.
     */
    public static function certificatePath(): ?string
    {
        $path = config('files.archive.sign.certificate');

        if (! $path) {
            return null;
        }

        return str_starts_with($path, '/') ? $path : storage_path($path);
    }

    /**
     * Inhaber des Zertifikats (CN bzw. O), nur zur Protokollierung.
     */
    private function subject(string $certificate, string $password): ?string
    {
        $certs = [];
        if (! function_exists('openssl_pkcs12_read')
            || ! @openssl_pkcs12_read((string) file_get_contents($certificate), $certs, $password)) {
            return null;
        }

        $parsed = openssl_x509_parse($certs['cert'] ?? '');

        return $parsed['subject']['CN'] ?? $parsed['subject']['O'] ?? null;
    }
}
