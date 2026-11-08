<?php

namespace Nfgarching\Files\Services\Archive;

/**
 * Schnelle, rein lesende Prüfungen an PDF-Dateien ohne externe Tools.
 *
 * Die Datei wird blockweise gelesen, damit auch große PDFs nicht komplett
 * in den Speicher geladen werden.
 */
class PdfInspector
{
    private const CHUNK = 1048576; // 1 MB

    private const OVERLAP = 256;

    /**
     * Enthält das PDF eine digitale Signatur?
     *
     * Jede PDF-Signatur besitzt ein /ByteRange-Array im Signaturwörterbuch.
     * Ein unbefülltes Signaturfeld hat keines und gilt daher nicht als signiert.
     */
    public function isSigned(string $path): bool
    {
        return $this->contains($path, ['/ByteRange']);
    }

    /**
     * Trägt das PDF eine PDF/A-Kennung in den XMP-Metadaten?
     *
     * Das ist nur die Behauptung der Datei – ob sie stimmt, prüft erst der
     * Validator (veraPDF).
     */
    public function hasPdfaIdentification(string $path): bool
    {
        return $this->contains($path, ['pdfaid:part']);
    }

    /**
     * SHA-256-Prüfsumme der Datei.
     */
    public function hash(string $path): string
    {
        return hash_file('sha256', $path);
    }

    /**
     * @param  list<string>  $needles
     */
    private function contains(string $path, array $needles): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return false;
        }

        $carry = '';
        try {
            while (! feof($handle)) {
                $chunk = fread($handle, self::CHUNK);
                if ($chunk === false) {
                    break;
                }
                $buffer = $carry.$chunk;
                foreach ($needles as $needle) {
                    if (str_contains($buffer, $needle)) {
                        return true;
                    }
                }
                $carry = substr($buffer, -self::OVERLAP);
            }
        } finally {
            fclose($handle);
        }

        return false;
    }
}
