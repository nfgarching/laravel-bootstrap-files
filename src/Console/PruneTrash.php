<?php

namespace Nfgarching\Files\Console;

use Illuminate\Console\Command;
use Nfgarching\Files\Models\File;

/**
 * Löscht Originale im Papierkorb, die durch ihre Archivfassung ersetzt wurden
 * und deren Aufbewahrungsfrist (files.archive.own.trash_days) abgelaufen ist.
 * Läuft standardmäßig täglich über den Scheduler.
 */
class PruneTrash extends Command
{
    protected $signature = 'files:prune-trash
                            {--days= : Frist in Tagen (Standard: FILES_TRASH_DAYS bzw. 90)}
                            {--dry-run : Nur anzeigen, nichts löschen}';

    protected $description = 'Löscht ersetzte Originale aus dem Papierkorb nach Ablauf der Frist';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('files.archive.own.trash_days', 90));
        $cutoff = now()->subDays($days);
        $dry = (bool) $this->option('dry-run');
        $count = 0;

        File::query()
            ->whereNotNull('custom_properties->archive->original->trashed_at')
            ->lazyById()
            ->each(function (File $file) use ($cutoff, $dry, &$count) {
                $trashedAt = $file->archive()['original']['trashed_at'] ?? null;
                if (! $file->hasTrashedOriginal() || ! $trashedAt || \Illuminate\Support\Carbon::parse($trashedAt)->gt($cutoff)) {
                    return;
                }

                $this->line(($dry ? '[dry-run] ' : '').'#'.$file->id.' '.$file->name.' – im Papierkorb seit '.$trashedAt);
                if (! $dry && $file->purgeTrashedOriginal()) {
                    $count++;
                }
            });

        $this->info($dry
            ? 'Probelauf beendet, nichts gelöscht.'
            : "{$count} Original(e) endgültig gelöscht (Frist {$days} Tage).");

        return self::SUCCESS;
    }
}
