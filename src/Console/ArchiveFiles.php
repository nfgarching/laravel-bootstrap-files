<?php

namespace Nfgarching\Files\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Jobs\ArchivePdf;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Services\Archive\PdfSigner;

/**
 * Archiviert vorhandene PDFs nachträglich (PDF/A + Siegel + Zeitstempel)
 * und prüft mit --check, ob die benötigten Programme installiert sind.
 */
class ArchiveFiles extends Command
{
    protected $signature = 'files:archive
                            {id?* : IDs einzelner Dateien}
                            {--all : Alle PDFs ohne Archivfassung}
                            {--force : Auch bereits archivierte PDFs neu verarbeiten}
                            {--sync : Sofort ausführen statt über die Queue}
                            {--check : Nur Programme und Zertifikat prüfen}';

    protected $description = 'Erzeugt Archivfassungen (PDF/A, gesiegelt) für vorhandene PDFs';

    public function handle(): int
    {
        if ($this->option('check')) {
            return $this->check();
        }

        $ids = $this->argument('id');
        if (! $ids && ! $this->option('all')) {
            $this->error('IDs angeben oder --all verwenden.');

            return self::FAILURE;
        }

        $query = File::query()->where('type', FileType::Pdf->value);
        if ($ids) {
            $query->whereKey($ids);
        }

        $count = 0;
        $query->lazyById()->each(function (File $file) use (&$count) {
            if (! $this->option('force') && $file->archive() && ($file->archive()['status'] ?? null) !== 'error') {
                return;
            }

            $this->option('sync')
                ? ArchivePdf::dispatchSync($file->id)
                : ArchivePdf::dispatch($file->id);
            $count++;
        });

        $this->info("{$count} PDF(s) ".($this->option('sync') ? 'archiviert.' : 'zur Archivierung eingereiht.'));

        return self::SUCCESS;
    }

    private function check(): int
    {
        $config = config('files.archive');
        $rows = [];

        foreach ([
            'ocrmypdf' => [$config['pdfa']['binary'] ?? 'ocrmypdf', '--version'],
            'veraPDF' => [$config['validate']['binary'] ?? 'verapdf', '--version'],
            'pyHanko' => [$config['sign']['binary'] ?? 'pyhanko', '--version'],
        ] as $label => $command) {
            $result = Process::timeout(60)->run($command);
            $version = trim(strtok($result->output().$result->errorOutput(), "\n") ?: '');
            $rows[] = [$label, $result->successful() ? 'ok' : 'FEHLT', $result->successful() ? $version : ''];
        }

        $cert = PdfSigner::certificatePath();
        $rows[] = ['Zertifikat', $cert && is_readable($cert) ? 'ok' : 'FEHLT', $cert ?? 'FILES_SIGN_CERT nicht gesetzt'];
        $rows[] = ['Zeitstempel', ($config['sign']['tsa_url'] ?? null) ? 'ok' : 'aus', $config['sign']['tsa_url'] ?? 'FILES_TSA_URL nicht gesetzt'];

        $this->table(['Baustein', 'Status', 'Details'], $rows);

        return collect($rows)->contains(fn ($row) => $row[1] === 'FEHLT') ? self::FAILURE : self::SUCCESS;
    }
}
