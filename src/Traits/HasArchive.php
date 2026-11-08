<?php

namespace Nfgarching\Files\Traits;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Jobs\InspectPdf;
use Nfgarching\Files\Services\Archive\PdfInspector;

/**
 * „Eigenes Dokument“ und Ersetzen des Originals durch die Archivfassung.
 *
 * custom_properties.own = [
 *     'value' => bool,
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
    public function own(): ?array
    {
        return $this->custom_properties['own'] ?? null;
    }

    public function isOwn(): bool
    {
        return (bool) ($this->own()['value'] ?? false);
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
     * Vorbelegung des Schalters „Eigenes Dokument“ je nach Quelle.
     *
     * Reihenfolge: ausdrücklich übergeben (App) → Import-Disk laut Config →
     * Erzeugerprogramm laut Config. Nur PDFs werden gekennzeichnet.
     *
     * @return array{value: bool, source: string, producer: ?string}
     */
    public static function detectOwn(string $localPath, ?bool $explicit = null, ?string $sourceDisk = null): array
    {
        $producer = is_readable($localPath) ? app(PdfInspector::class)->producer($localPath) : null;

        if ($explicit !== null) {
            return ['value' => $explicit, 'source' => 'app', 'producer' => $producer];
        }

        if ($sourceDisk && in_array($sourceDisk, (array) config('files.archive.own.disks', []), true)) {
            return ['value' => true, 'source' => 'disk', 'producer' => $producer];
        }

        if ($producer) {
            foreach ((array) config('files.archive.own.producers', []) as $pattern) {
                $pattern = trim((string) $pattern);
                if ($pattern !== '' && stripos($producer, $pattern) !== false) {
                    return ['value' => true, 'source' => 'producer', 'producer' => $producer];
                }
            }
        }

        return ['value' => false, 'source' => 'none', 'producer' => $producer];
    }

    /**
     * Schalter „Eigenes Dokument“ setzen.
     *
     * An: Ist die Archivfassung schon fertig, wird das Original sofort
     * ersetzt, sonst erledigt das ArchivePdf. Aus: Ein bereits ersetztes
     * Original wird aus dem Papierkorb zurückgeholt.
     */
    public function setOwn(bool $value, string $source = 'user'): void
    {
        $this->mergeJson('custom_properties', ['own' => [
            'value' => $value,
            'source' => $source,
            'producer' => $this->own()['producer'] ?? null,
        ]]);

        $changed = match (true) {
            $value && $this->shouldReplaceOriginal() => $this->replaceOriginalWithArchive(),
            ! $value && $this->hasTrashedOriginal() => $this->restoreOriginal(),
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
        return match (config('files.archive.own.replace', 'own')) {
            'always' => true,
            'own' => $this->isOwn(),
            default => false,
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
        if (config('files.archive.own.require_validation', true) ? $validation !== 'compliant' : $validation === 'noncompliant') {
            return false;
        }

        return Storage::disk($archive['disk'] ?? $this->disk)->exists($archive['path']);
    }

    /**
     * Archivfassung wird Hauptdatei, das Original wandert in den Papierkorb
     * (Löschung nach files.archive.own.trash_days durch files:prune-trash).
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
        $size = $disk->size($originalPath);
        $disk->makeDirectory(dirname($trashPath));
        if (! $disk->move($originalPath, $trashPath)) {
            Log::channel('files')->error('#'.$this->id.' Original ersetzen: Verschieben in den Papierkorb fehlgeschlagen');

            return false;
        }

        $days = (int) config('files.archive.own.trash_days', 90);
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
