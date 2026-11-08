<?php

namespace Nfgarching\Files\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Erzeugt für Entwicklung und Tests eine eigene Test-CA und ein damit
 * ausgestelltes Siegelzertifikat (PKCS#12).
 *
 * Die Signaturen sind technisch vollwertig (PAdES), werden von Prüfprogrammen
 * aber als „Aussteller unbekannt“ angezeigt, solange die Test-CA dort nicht
 * als vertrauenswürdig importiert ist. Für den Echtbetrieb ein Siegel-
 * zertifikat eines Vertrauensdiensteanbieters verwenden.
 */
class MakeTestCertificate extends Command
{
    protected $signature = 'files:make-test-certificate
                            {--name= : Name im Zertifikat (CN), Standard: "<APP_NAME> Testsiegel"}
                            {--org= : Organisation (O), Standard: APP_NAME}
                            {--password= : Passwort der .p12-Datei, Standard: zufällig}
                            {--dir=app/private/files-signing : Zielverzeichnis relativ zu storage/}
                            {--days=825 : Gültigkeit des Siegelzertifikats in Tagen}
                            {--force : Vorhandene Dateien überschreiben}';

    protected $description = 'Erzeugt eine Test-CA und ein Test-Siegelzertifikat (.p12) für die PDF-Signatur';

    public function handle(): int
    {
        $app = (string) config('app.name', 'Laravel');
        $org = $this->option('org') ?: $app;
        $name = $this->option('name') ?: $app.' Testsiegel';
        $password = $this->option('password') ?: Str::password(24, symbols: false);
        $relative = trim((string) $this->option('dir'), '/');
        $dir = storage_path($relative);

        $p12 = $dir.'/test-seal.p12';
        $caCrt = $dir.'/test-ca.crt';

        if (! $this->option('force') && (is_file($p12) || is_file($caCrt))) {
            $this->error("Zertifikat existiert bereits in {$dir}. Mit --force überschreiben.");

            return self::FAILURE;
        }

        Filesystem::ensureDirectoryExists($dir, 0700);
        $work = sys_get_temp_dir().'/files-cert-'.bin2hex(random_bytes(4));
        Filesystem::ensureDirectoryExists($work, 0700);

        try {
            file_put_contents($work.'/seal.ext', implode("\n", [
                'basicConstraints = critical, CA:FALSE',
                'keyUsage = critical, digitalSignature, nonRepudiation',
                'subjectKeyIdentifier = hash',
                'authorityKeyIdentifier = keyid, issuer',
            ])."\n");

            $steps = [
                'Test-CA' => [
                    'openssl', 'req', '-x509', '-utf8', '-newkey', 'rsa:3072', '-nodes', '-sha256',
                    '-keyout', $work.'/ca.key', '-out', $work.'/ca.crt', '-days', '3650',
                    '-subj', $this->subject($org.' Test-CA', $org),
                    '-addext', 'basicConstraints=critical,CA:TRUE',
                    '-addext', 'keyUsage=critical,keyCertSign,cRLSign',
                ],
                'Schlüssel und Antrag' => [
                    'openssl', 'req', '-utf8', '-newkey', 'rsa:3072', '-nodes', '-sha256',
                    '-keyout', $work.'/seal.key', '-out', $work.'/seal.csr',
                    '-subj', $this->subject($name, $org),
                ],
                'Siegelzertifikat' => [
                    'openssl', 'x509', '-req', '-sha256', '-in', $work.'/seal.csr',
                    '-CA', $work.'/ca.crt', '-CAkey', $work.'/ca.key', '-CAcreateserial',
                    '-days', (string) (int) $this->option('days'),
                    '-extfile', $work.'/seal.ext', '-out', $work.'/seal.crt',
                ],
            ];

            foreach ($steps as $label => $command) {
                $result = Process::path($work)->run($command);
                if ($result->failed()) {
                    $this->error("{$label}: ".trim($result->errorOutput() ?: $result->output()));

                    return self::FAILURE;
                }
            }

            $result = Process::path($work)->input($password."\n")->run([
                'openssl', 'pkcs12', '-export',
                '-inkey', $work.'/seal.key', '-in', $work.'/seal.crt', '-certfile', $work.'/ca.crt',
                '-name', $name, '-out', $work.'/seal.p12', '-passout', 'stdin',
            ]);
            if ($result->failed()) {
                $this->error('PKCS#12: '.trim($result->errorOutput()));

                return self::FAILURE;
            }

            copy($work.'/seal.p12', $p12);
            copy($work.'/ca.crt', $caCrt);
            chmod($p12, 0600);
        } finally {
            // Privatschlüssel der Test-CA wird bewusst nicht aufbewahrt
            Filesystem::deleteDirectory($work);
        }

        $this->info('Test-Siegelzertifikat erzeugt:');
        $this->line("  {$p12}");
        $this->line("  {$caCrt}  (zum Import als vertrauenswürdige CA in PDF-Prüfprogramme)");
        $this->newLine();
        $this->line('In die .env übernehmen:');
        $this->newLine();
        $this->line("FILES_SIGN_CERT={$relative}/test-seal.p12");
        $this->line("FILES_SIGN_PASSWORD={$password}");
        $this->line('FILES_TSA_URL=https://freetsa.org/tsr');
        $this->newLine();
        $this->warn('Nur für Tests. Die .p12-Datei und das Passwort nicht ins Repository übernehmen.');

        return self::SUCCESS;
    }

    /**
     * OpenSSL-Subject; Sonderzeichen / und = werden maskiert.
     */
    private function subject(string $cn, string $org): string
    {
        $escape = fn (string $value) => addcslashes($value, '/=');

        return '/CN='.$escape($cn).'/O='.$escape($org);
    }
}
