<?php

namespace Nfgarching\Files\Services\Inspection;

use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Services\Archive\PdfaValidator;
use Nfgarching\Files\Services\Archive\PdfInspector;

/**
 * Prüft Original und Archivfassung eines PDFs und legt das Ergebnis in
 * custom_properties.inspection ab:
 *
 *   properties  Metadaten, Technik, Schriften, Bilder, Anhänge (poppler)
 *   signatures  Signaturen mit Bewertung je Aspekt (pyHanko)
 *   pdfa        PDF/A-Kennung laut XMP und Prüfung mit veraPDF
 *   integrity   SHA-256 im Vergleich zum Wert bei der Archivierung
 *
 * Siehe docs/PDF-Pruefung.md.
 */
class PdfInspection
{
    public function __construct(
        private readonly PdfProperties $properties,
        private readonly SignatureInspector $signatures,
        private readonly PdfaValidator $validator,
        private readonly PdfInspector $inspector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function inspect(File $file): array
    {
        $workdir = sys_get_temp_dir().'/files-inspect-'.$file->id.'-'.bin2hex(random_bytes(4));
        Filesystem::ensureDirectoryExists($workdir, 0700);

        try {
            $archive = $file->archive() ?? [];
            $original = $archive['original'] ?? [];

            if ($file->isOriginalReplaced()) {
                // Eigenes Dokument: Hauptdatei ist die Archivfassung, das Original
                // liegt im Papierkorb oder ist nach Fristablauf gelöscht
                $variants = ['original' => $file->hasTrashedOriginal()
                    ? $this->variant(
                        $original['disk'] ?? $file->disk, $original['trash_path'], $workdir.'/original.pdf',
                        $original['sha256'] ?? $archive['source_sha256'] ?? null,
                    ) + ['trashed' => true, 'purge_after' => $original['purge_after'] ?? null]
                    : [
                        'disk' => $original['disk'] ?? $file->disk,
                        'path' => $original['path'] ?? '',
                        'status' => 'purged',
                        'purged_at' => $original['purged_at'] ?? null,
                    ]];
            } else {
                $variants = ['original' => $this->variant(
                    $file->disk, $file->file, $workdir.'/original.pdf', $archive['source_sha256'] ?? null,
                )];
            }

            if ($path = $file->archivePdfPath()) {
                $variants['archive'] = $this->variant(
                    $archive['disk'] ?? $file->disk, $path, $workdir.'/archive.pdf', $archive['sha256'] ?? null,
                );
            }
        } finally {
            Filesystem::deleteDirectory($workdir);
        }

        $inspection = [
            'at' => now()->toIso8601String(),
            'trust' => array_map('basename', SignatureInspector::trustCertificates()),
            'variants' => $variants,
        ];

        $file->mergeJson('custom_properties', ['inspection' => $inspection]);

        return $inspection;
    }

    /**
     * @return array<string, mixed>
     */
    private function variant(string $disk, string $path, string $local, ?string $expectedHash): array
    {
        $base = ['disk' => $disk, 'path' => $path];

        if (! $this->fetch($disk, $path, $local)) {
            return $base + ['status' => 'missing'];
        }

        $hash = $this->inspector->hash($local);
        $properties = $this->properties->inspect($local);

        return $base + [
            'status' => 'ok',
            'integrity' => array_filter([
                'sha256' => $hash,
                'expected' => $expectedHash,
                'ok' => $expectedHash === null ? null : hash_equals($expectedHash, $hash),
            ], fn ($v) => $v !== null),
            'properties' => $properties,
            'signatures' => $this->signatures->inspect($local),
            'pdfa' => $this->pdfa($local, $properties['xmp']['pdfa'] ?? null),
        ];
    }

    /**
     * Nur PDFs mit PDF/A-Kennung werden geprüft; bei allen anderen wäre die
     * Liste verletzter Regeln bloß Rauschen.
     *
     * @return array<string, mixed>
     */
    private function pdfa(string $local, ?string $claimed): array
    {
        if (! $claimed) {
            return ['claimed' => null, 'validation' => ['status' => 'unclaimed']];
        }

        return [
            'claimed' => $claimed,
            'validation' => $this->validator->validate($local, $claimed)->toArray(),
        ];
    }

    /**
     * Kopiert die Datei ins Arbeitsverzeichnis – auch für Disks ohne lokalen Pfad.
     */
    private function fetch(string $disk, string $path, string $local): bool
    {
        if (! Storage::disk($disk)->exists($path)) {
            return false;
        }

        $stream = Storage::disk($disk)->readStream($path);
        if (! $stream) {
            return false;
        }
        file_put_contents($local, $stream);
        fclose($stream);

        return true;
    }
}
