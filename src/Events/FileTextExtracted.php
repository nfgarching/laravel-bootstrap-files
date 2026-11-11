<?php

namespace Nfgarching\Files\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Nfgarching\Files\Models\File;

/**
 * Zu einer Datei liegt Text vor (OCR oder aus dem PDF gelesener Text) –
 * auch bei „Original Dokumenten“ und gesperrten Dateien, deren Hauptdatei
 * unverändert bleibt. Gedacht für die Volltextsuche (z. B. Meilisearch):
 *
 *     Event::listen(FileTextExtracted::class, fn ($e) => $index->add($e->file->id, $e->file->ocrText()));
 *
 * $type: ocrtext (OCR), digitext (signiertes PDF, Text ausgelesen), text (PDF hatte bereits Text)
 */
class FileTextExtracted
{
    use Dispatchable;

    public function __construct(
        public File $file,
        public string $type,
        public string $disk,
        public string $path,
    ) {}
}
