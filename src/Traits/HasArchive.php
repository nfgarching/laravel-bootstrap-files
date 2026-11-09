<?php

namespace Nfgarching\Files\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Events\FileReplaced;
use Nfgarching\Files\Jobs\InspectPdf;
use Nfgarching\Files\Services\Archive\PdfInspector;

/**
 * „Original Dokument“ und Ersetzen des Originals durch die Archivfassung.
 *
 * Standard: Nach erfolgreicher Archivierung (PDF/A geprüft und gesiegelt)
 * wird die Archivfassung Hauptdatei, das Original wandert in den Papierkorb.
 * Ist ein PDF als „Original Dokument“ gekennzeichnet, bleibt das Original
 * die Hauptdatei.
 *
 * custom_properties.original_document = [
 *     'value' => bool,         // true = Original behalten
 *     'source' => 'user' | 'app' | 'disk' | 'producer' | 'none',
 *     'producer' => ?string,   // Erzeugerprogramm laut Metadaten
 * ]
 *
 * custom_properties.archive.original (nach dem Ersetzen) = [
 *     'disk', 'path' (ursprünglicher Pfad), 'trash_path', 'sha256', 'size',
 *     'trashed_at', 'purge_after' | 'restored_at' | 'purged_at',
 * ]
 */
trait HasArchive
{
    /**
     * @return array{value: bool, source: string, producer?: ?string}|null
     */
    public function originalDocument(): ?array
    {
        if (isset($this->custom_properties['original_document'])) {
            return $this->custom_properties['original_document'];
        }

        // Altbestand: früherer Schalter „Eigenes Dokument“ (umgekehrte Bedeutung);
        // nur eine ausdrückliche Wahl wird übernommen, sonst gilt der Standard.
        $own = $this->custom_properties['own'] ?? null;
        if (is_array($own) && in_array($own['source'] ?? null, ['user', 'app'], true)) {
            return ['value' => ! ($own['value'] ?? false), 'source' => $own['source'], 'producer' => $own['producer'] ?? null];
        }

        return is_array($own) ? ['value' => false, 'source' => 'none', 'producer' => $own['producer'] ?? null] : null;
    }

    public function isOriginalDocument(): bool
    {
        return (bool) ($this->originalDocument()['value'] ?? false);
    }

    public function isOriginalReplaced(): bool
    {
        return (bool) ($this->archive()['replaced'] ?? false);
    }

    /**
     * Liegt das ersetzte Original noch im Papierkorb?
     */
    public function hasTrashedOriginal(): bool
    {
        return $this->isOriginalReplaced() && ! empty($this->archive()['original']['trashed_at']);
    }

    /**
     * Vorbelegung des Schalters „Original Dokument“ je nach Quelle.
     *
     * Reihenfolge: ausdrücklich übergeben (App) → Import-Disk laut Config →
     * Erzeugerprogramm laut Config → aus (Archivfassung wird übernommen).
     * Nur PDFs werden gekennzeichnet.
     *
     * @return array{value: bool, source: string, producer: ?string}
     */
    public static function detectOriginalDocument(string $localPath, ?bool $explicit = null, ?string $sourceDisk = null): array
    {
        $producer = is_readable($localPath) ? app(PdfInspector::class)->producer($localPath) : null;

        if ($explicit !== null) {
            return ['value' => $explicit, 'source' => 'app', 'producer' => $producer];
        }

        if ($sourceDisk && in_array($sourceDisk, (array) config('files.archive.original.disks', []), true)) {
            return ['value' => true, 'source' => 'disk', 'producer' => $producer];
        }

        if ($producer) {
            foreach ((array) config('files.archive.original.producers', []) as $pattern) {
                $pattern = trim((string) $pattern);
                if ($pattern !== '' && stripos($producer, $pattern) !== false) {
                    return ['value' => true, 'source' => 'producer', 'producer' => $producer];
                }
            }
        }

        return ['value' => false, 'source' => 'none', 'producer' => $producer];
    }

    /**
     * Schalter „Original Dokument“ setzen.
     *
     * An: Ein bereits ersetztes Original wird aus dem Papierkorb
     * zurückgeholt. Aus: Ist die Archivfassung schon fertig, wird das
     * Original sofort ersetzt, sonst erledigt das ArchivePdf.
     */
    public function setOriginalDocument(bool $value, string $source = 'user'): void
    {
        $this->mergeJson('custom_properties', ['original_document' => [
            'value' => $value,
            'source' => $source,
            'producer' => $this->originalDocument()['producer'] ?? null,
        ]]);

        $changed = match (true) {
            $value && $this->hasTrashedOriginal() => $this->restoreOriginal(),
            ! $value && $this->shouldReplaceOriginal() => $this->replaceOriginalWithArchive(),
            default => false,
        };

        // Prüfergebnis (Tab „PDF“) bezieht sich auf die bisherige Hauptdatei
        if ($changed && config('files.inspect.enabled', true)) {
            InspectPdf::dispatch($this->id);
        }
    }

    /**
     * Soll das Original nach der Archivierung ersetzt werden (Config + Schalter)?
     */
    public function shouldReplaceOriginal(): bool
    {
        return match (config('files.archive.original.replace', 'archive')) {
            'always' => true,
            'never' => false,
            default => ! $this->isOriginalDocument(),
        };
    }

    /**
     * Ersetzen nur, wenn die Archivfassung vollständig gelungen ist:
     * PDF/A validiert (sofern verlangt) und gesiegelt.
     */
    public function canReplaceOriginal(): bool
    {
        $archive = $this->archive();

        if ($this->type !== FileType::Pdf || ! $archive || $this->isOriginalReplaced()) {
            return false;
        }
        if (($archive['status'] ?? null) !== 'archived' || empty($archive['path']) || $archive['path'] === $this->file) {
            return false;
        }
        if (($archive['signature']['status'] ?? null) !== 'signed') {
            return false;
        }
        $validation = $archive['validation']['status'] ?? null;
        if (config('files.archive.original.require_validation', true) ? $validation !== 'compliant' : $validation === 'noncompliant') {
            return false;
        }

        return Storage::disk($archive['disk'] ?? $this->disk)->exists($archive['path']);
    }

    /**
     * Archivfassung wird Hauptdatei, das Original wandert in den Papierkorb
     * (Löschung nach files.archive.original.trash_days durch files:prune-trash).
     */
    public function replaceOriginalWithArchive(): bool
    {
        if (! $this->canReplaceOriginal()) {
            return false;
        }

        $archive = $this->archive();
        $disk = Storage::disk($this->disk);
        $pathclass = config('files.path_generator');
        $trashPath = (new $pathclass)->getPathForTrash($this).'/'.basename($this->file);

        if (($archive['disk'] ?? $this->disk) !== $this->disk) {
            Log::channel('files')->warning('#'.$this->id.' Original ersetzen: Archivfassung liegt auf anderer Disk, übersprungen');

            return false;
        }

        $originalPath = $this->file;
        $originalHash = $this->hash;
        $size = $disk->size($originalPath);
        $disk->makeDirectory(dirname($trashPath));
        if (! $disk->move($originalPath, $trashPath)) {
            Log::channel('files')->error('#'.$this->id.' Original ersetzen: Verschieben in den Papierkorb fehlgeschlagen');

            return false;
        }

        $days = (int) config('files.archive.original.trash_days', 90);
        $now = now();

        $this->forceFill([
            'file' => $archive['path'],
            'hash' => $this->md5OnDisk($archive['path']),
            'size' => $disk->size($archive['path']),
        ])->save();

        $this->mergeJson('custom_properties', ['archive' => array_merge($archive, [
            'replaced' => true,
            'original' => [
                'disk' => $this->disk,
                'path' => $originalPath,
                'trash_path' => $trashPath,
                'sha256' => $archive['source_sha256'] ?? null,
                'size' => $size,
                'trashed_at' => $now->toIso8601String(),
                'purge_after' => $now->copy()->addDays($days)->toDateString(),
            ],
        ])]);

        Log::channel('files')->info('#'.$this->id.' Original durch Archivfassung ersetzt, Original im Papierkorb bis '.$now->copy()->addDays($days)->toDateString());

        FileReplaced::dispatch($this, $originalPath, $originalHash, 'archive');

        return true;
    }

    /**
     * Original aus dem Papierkorb zurückholen; die Archivfassung bleibt
     * unter generated_conversions.archive erhalten.
     */
    public function restoreOriginal(): bool
    {
        if (! $this->hasTrashedOriginal()) {
            return false;
        }

        $archive = $this->archive();
        $original = $archive['original'];
        $disk = Storage::disk($original['disk'] ?? $this->disk);

        if (! $disk->exists($original['trash_path'])) {
            Log::channel('files')->error('#'.$this->id.' Wiederherstellen: Original nicht mehr im Papierkorb');

            return false;
        }

        $disk->makeDirectory(dirname($original['path']));
        if (! $disk->move($original['trash_path'], $original['path'])) {
            return false;
        }

        $archivePath = $this->file;
        $archiveHash = $this->hash;

        $this->forceFill([
            'file' => $original['path'],
            'hash' => $this->md5OnDisk($original['path']),
            'size' => $disk->size($original['path']),
        ])->save();

        $this->mergeJson('custom_properties', ['archive' => array_merge($archive, [
            'replaced' => false,
            'original' => [
                'disk' => $original['disk'] ?? $this->disk,
                'path' => $original['path'],
                'sha256' => $original['sha256'] ?? null,
                'restored_at' => now()->toIso8601String(),
            ],
        ])]);

        Log::channel('files')->info('#'.$this->id.' Original aus dem Papierkorb wiederhergestellt');

        FileReplaced::dispatch($this, $archivePath, $archiveHash, 'restore');

        return true;
    }

    /**
     * Original im Papierkorb endgültig löschen.
     */
    public function purgeTrashedOriginal(): bool
    {
        if (! $this->hasTrashedOriginal()) {
            return false;
        }

        $archive = $this->archive();
        $original = $archive['original'];
        Storage::disk($original['disk'] ?? $this->disk)->delete($original['trash_path']);

        $this->mergeJson('custom_properties', ['archive' => array_merge($archive, [
            'original' => [
                'disk' => $original['disk'] ?? $this->disk,
                'path' => $original['path'],
                'sha256' => $original['sha256'] ?? null,
                'size' => $original['size'] ?? null,
                'purged_at' => now()->toIso8601String(),
            ],
        ])]);

        Log::channel('files')->info('#'.$this->id.' Original im Papierkorb endgültig gelöscht');

        return true;
    }

    /**
     * MD5 wie bisher in der Spalte hash – auch für Disks ohne lokalen Pfad.
     */
    private function md5OnDisk(string $path): ?string
    {
        $stream = Storage::disk($this->disk)->readStream($path);
        if (! $stream) {
            return null;
        }
        $context = hash_init('md5');
        hash_update_stream($context, $stream);
        fclose($stream);

        return hash_final($context);
    }
}
