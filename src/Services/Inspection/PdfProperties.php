<?php

namespace Nfgarching\Files\Services\Inspection;

use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * Liest die Eigenschaften eines PDFs mit den poppler-Werkzeugen aus:
 * pdfinfo (Metadaten, Technik, XMP), pdffonts, pdfimages, pdfdetach und
 * pdftotext (Textebene vorhanden?).
 *
 * Fehlt poppler, liefert inspect() ['status' => 'notool'].
 */
class PdfProperties
{
    /**
     * @return array<string, mixed>
     */
    public function inspect(string $path): array
    {
        $info = $this->run(['pdfinfo', '-isodates', $path]);
        if ($info === null) {
            return ['status' => 'notool', 'error' => "Programm 'pdfinfo' (poppler-utils) nicht gefunden."];
        }
        if ($info === false) {
            return ['status' => 'error', 'error' => 'pdfinfo konnte die Datei nicht lesen.'];
        }

        $fields = $this->parseInfo($info);
        $xmp = (string) $this->run(['pdfinfo', '-meta', $path]);

        return array_filter([
            'status' => 'ok',
            'metadata' => array_filter([
                'title' => $fields['Title'] ?? null,
                'subject' => $fields['Subject'] ?? null,
                'author' => $fields['Author'] ?? null,
                'keywords' => $fields['Keywords'] ?? null,
                'creator' => $fields['Creator'] ?? null,
                'producer' => $fields['Producer'] ?? null,
                'created' => $fields['CreationDate'] ?? null,
                'modified' => $fields['ModDate'] ?? null,
            ], fn ($v) => $v !== null && $v !== ''),
            'pdf_version' => $fields['PDF version'] ?? null,
            'pages' => isset($fields['Pages']) ? (int) $fields['Pages'] : null,
            'page_size' => $fields['Page size'] ?? null,
            'file_size' => isset($fields['File size']) ? (int) $fields['File size'] : null,
            'linearized' => $this->yes($fields['Optimized'] ?? null),
            'tagged' => $this->yes($fields['Tagged'] ?? null),
            'form' => ($fields['Form'] ?? 'none') === 'none' ? null : $fields['Form'],
            'javascript' => $this->yes($fields['JavaScript'] ?? null),
            'encryption' => $this->encryption($fields['Encrypted'] ?? 'no'),
            'xmp' => $this->xmp($xmp),
            'fonts' => $this->fonts($path),
            'images' => $this->images($path),
            'attachments' => $this->attachments($path),
            'text_layer' => $this->textLayer($path),
        ], fn ($v) => $v !== null);
    }

    /**
     * @return array<string, string>
     */
    private function parseInfo(string $output): array
    {
        $fields = [];
        foreach (preg_split('/\R/', $output) as $line) {
            if (preg_match('/^([^:]+):\s*(.*)$/', $line, $m)) {
                $fields[trim($m[1])] = trim($m[2]);
            }
        }

        return $fields;
    }

    private function yes(?string $value): ?bool
    {
        return $value === null ? null : str_starts_with($value, 'yes');
    }

    /**
     * „yes (print:yes copy:no change:no addNotes:no algorithm:AES)“
     *
     * @return array<string, mixed>|null
     */
    private function encryption(string $value): ?array
    {
        if (! str_starts_with($value, 'yes')) {
            return null;
        }

        preg_match_all('/(\w+):(\S+?)(?=[\s)])/', $value, $m, PREG_SET_ORDER);
        $details = [];
        foreach ($m as [, $key, $val]) {
            $details[$key] = match ($val) {
                'yes' => true,
                'no' => false,
                default => $val,
            };
        }

        return $details ?: ['encrypted' => true];
    }

    /**
     * PDF/A- und ZUGFeRD-/Factur-X-Kennung aus den XMP-Metadaten. Die Werte
     * stehen je nach Erzeuger als Attribut (pdfaid:part="2") oder Element.
     *
     * @return array<string, string>|null
     */
    private function xmp(string $xmp): ?array
    {
        if ($xmp === '') {
            return null;
        }

        $value = fn (string $name) => preg_match('/'.preg_quote($name, '/').'(?:="|>)\s*([^"<]+)/', $xmp, $m)
            ? trim($m[1]) : null;

        $part = $value('pdfaid:part');
        $conformance = $value('pdfaid:conformance');

        $zugferd = null;
        foreach (['fx', 'zf', 'rsm'] as $ns) {
            if ($level = $value("{$ns}:ConformanceLevel")) {
                $zugferd = array_filter([
                    'level' => $level,
                    'document' => $value("{$ns}:DocumentFileName"),
                    'version' => $value("{$ns}:Version"),
                ]);
                break;
            }
        }

        return array_filter([
            'pdfa' => $part ? $part.strtolower((string) $conformance) : null,
            'pdfua' => $value('pdfuaid:part'),
            'zugferd' => $zugferd,
        ]) ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>|null
     */
    private function fonts(string $path): ?array
    {
        $output = $this->run(['pdffonts', $path]);
        if (! is_string($output)) {
            return null;
        }

        $fonts = [];
        foreach (array_slice(preg_split('/\R/', trim($output)), 2) as $line) {
            // Name ohne Leerzeichen; Typ kann Leerzeichen enthalten („Type 1C“)
            if (preg_match('/^(\S+)\s+(.+?)\s+(\S+)\s+(yes|no)\s+(yes|no)\s+(yes|no)\s+\d+\s+\d+$/', $line, $m)) {
                $fonts[] = [
                    'name' => preg_replace('/^[A-Z]{6}\+/', '', $m[1]),
                    'type' => $m[2],
                    'embedded' => $m[4] === 'yes',
                ];
            }
        }

        return $fonts;
    }

    /**
     * Bilder mit Auflösung; zusammengefasst, weil Scans je Seite ein Bild haben.
     *
     * @return array<string, mixed>|null
     */
    private function images(string $path): ?array
    {
        $output = $this->run(['pdfimages', '-list', $path]);
        if (! is_string($output)) {
            return null;
        }

        $count = 0;
        $ppi = [];
        $encodings = [];
        foreach (array_slice(preg_split('/\R/', trim($output)), 2) as $line) {
            $cols = preg_split('/\s+/', trim($line));
            if (count($cols) < 14 || $cols[2] !== 'image') {
                continue;
            }
            $count++;
            $ppi[] = (int) $cols[12];
            $encodings[$cols[8]] = true;
        }

        if ($count === 0) {
            return ['count' => 0];
        }

        return [
            'count' => $count,
            'ppi_min' => min($ppi),
            'ppi_max' => max($ppi),
            'encodings' => array_keys($encodings),
        ];
    }

    /**
     * @return array<int, string>|null
     */
    private function attachments(string $path): ?array
    {
        $output = $this->run(['pdfdetach', '-list', $path]);
        if (! is_string($output)) {
            return null;
        }

        preg_match_all('/^\d+:\s*(.+)$/m', $output, $m);

        return array_map('trim', $m[1]);
    }

    /**
     * Zeichen Text auf den ersten Seiten – 0 heißt: reiner Scan ohne OCR.
     *
     * @return array<string, mixed>|null
     */
    private function textLayer(string $path): ?array
    {
        $pages = (int) config('files.inspect.text_pages', 3);
        $output = $this->run(['pdftotext', '-l', (string) $pages, $path, '-']);
        if (! is_string($output)) {
            return null;
        }

        $chars = mb_strlen((string) preg_replace('/\s+/u', '', $output));

        return [
            'chars' => $chars,
            'pages' => $pages,
            'present' => $chars >= 20,
            'sample' => Str::limit(trim((string) preg_replace('/\s+/u', ' ', $output)), 200),
        ];
    }

    /**
     * @param  list<string>  $command
     * @return string|false|null Ausgabe, false bei Fehler, null wenn das Programm fehlt
     */
    private function run(array $command): string|false|null
    {
        try {
            $result = Process::timeout((int) config('files.inspect.timeout', 120))->run($command);
        } catch (ProcessTimedOutException) {
            return false;
        }

        if ($result->exitCode() === 127 || str_contains($result->errorOutput(), 'not found')) {
            return null;
        }

        // pdfinfo meldet Syntaxfehler auf stderr, liefert aber trotzdem Daten
        return $result->successful() || $result->output() !== '' ? $result->output() : false;
    }
}
