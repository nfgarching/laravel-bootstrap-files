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
     * Erzeugerprogramm laut PDF-Metadaten (XMP CreatorTool/Producer bzw.
     * Info-Wörterbuch /Creator, /Producer), z. B. „LibreOffice 24.2“.
     *
     * Nur ein Hinweis für die Vorbelegung „Eigenes Dokument“ – Metadaten
     * lassen sich beliebig setzen und fehlen bei manchen PDFs ganz (etwa wenn
     * das Info-Wörterbuch in einem komprimierten Objektstrom liegt).
     */
    public function producer(string $path): ?string
    {
        $content = @file_get_contents($path, false, null, 0, 8 * self::CHUNK);
        if ($content === false || $content === '') {
            return null;
        }

        // Erstellendes Programm (Creator) und PDF-Erzeuger (Producer) – beide,
        // weil z. B. Writer als Creator und LibreOffice als Producer auftritt
        $values = [];

        foreach (['xmp:CreatorTool', 'pdf:Producer'] as $tag) {
            if (preg_match('#<'.$tag.'>\s*([^<]{1,200}?)\s*</'.$tag.'>#', $content, $match)
                || preg_match('#'.$tag.'="([^"]{1,200})"#', $content, $match)) {
                $values[] = $this->clean(html_entity_decode($match[1], ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }
        }

        foreach (['Creator', 'Producer'] as $key) {
            // Literal-String (…) mit maskierten Klammern
            if (preg_match('#/'.$key.'\s*\(((?:\\\\.|[^\\\\)]){1,400})\)#s', $content, $match)) {
                $values[] = $this->clean($this->decodeLiteral($match[1]));
            } elseif (preg_match('#/'.$key.'\s*<([0-9A-Fa-f\s]{2,800})>#', $content, $match)) {
                // Hex-String <…>
                $values[] = $this->clean($this->decodeText((string) hex2bin(preg_replace('/\s+/', '', $match[1]))));
            }
        }

        $values = array_values(array_unique(array_filter($values)));

        return $values ? mb_substr(implode(' / ', $values), 0, 200) : null;
    }

    private function decodeLiteral(string $value): string
    {
        $value = preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', function ($m) {
            return match (true) {
                ctype_digit($m[1]) => chr(octdec($m[1]) & 0xFF),
                $m[1] === 'n' => "\n",
                $m[1] === 'r' => "\r",
                $m[1] === 't' => "\t",
                default => $m[1],
            };
        }, $value);

        return $this->decodeText($value);
    }

    /**
     * PDF-Textstrings sind UTF-16BE (mit BOM), UTF-8 (mit BOM) oder PDFDocEncoding.
     */
    private function decodeText(string $value): string
    {
        if (str_starts_with($value, "\xFE\xFF")) {
            return mb_convert_encoding(substr($value, 2), 'UTF-8', 'UTF-16BE');
        }
        if (str_starts_with($value, "\xEF\xBB\xBF")) {
            return substr($value, 3);
        }

        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
    }

    private function clean(string $value): ?string
    {
        $value = trim(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '');

        return $value === '' ? null : mb_substr($value, 0, 200);
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
