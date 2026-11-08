<?php

namespace Nfgarching\Files\Console;

use Illuminate\Console\Command;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Jobs\InspectPdf;
use Nfgarching\Files\Models\File;

/**
 * Prüft vorhandene PDFs nachträglich (Eigenschaften, Signaturen, PDF/A,
 * Prüfsummen), z. B. nach Änderung der Vertrauensanker.
 */
class InspectFiles extends Command
{
    protected $signature = 'files:inspect
                            {id?* : IDs einzelner Dateien}
                            {--all : Alle PDFs ohne Prüfergebnis}
                            {--force : Auch bereits geprüfte PDFs erneut prüfen}
                            {--sync : Sofort ausführen statt über die Queue}';

    protected $description = 'Prüft PDFs (Eigenschaften, Signaturen, PDF/A, Prüfsummen)';

    public function handle(): int
    {
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
        $query->lazyById()->each(function (File $file) use (&$count, $ids) {
            if (! $ids && ! $this->option('force') && $file->inspection()) {
                return;
            }

            $this->option('sync')
                ? InspectPdf::dispatchSync($file->id)
                : InspectPdf::dispatch($file->id);
            $count++;
        });

        $this->info("{$count} PDF(s) ".($this->option('sync') ? 'geprüft.' : 'zur Prüfung eingereiht.'));

        return self::SUCCESS;
    }
}
