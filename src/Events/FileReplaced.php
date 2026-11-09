<?php

namespace Nfgarching\Files\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Nfgarching\Files\Models\File;

/**
 * Die Hauptdatei eines File-Datensatzes wurde ausgetauscht (file, hash, size):
 *
 *  - 'archive': Original durch die Archivfassung ersetzt (eigenes Dokument)
 *  - 'restore': Original aus dem Papierkorb zurückgeholt
 *
 * Anwendungen, die Pfad oder Hash der Datei selbst gespeichert haben
 * (z. B. die Legacy-Spalte Dokumente.Attachment), ziehen ihn hier nach.
 */
class FileReplaced
{
    use Dispatchable;

    public function __construct(
        public File $file,
        public string $oldPath,
        public ?string $oldHash,
        public string $reason,
    ) {}
}
