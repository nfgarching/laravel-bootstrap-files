<?php

namespace Nfgarching\Files\Services\Inspection;

/**
 * Übersetzt das Prüfergebnis einer Signatur (aus pdf_signatures.py) in
 * einzelne Ampeln: Integrität, Umfang, Vertrauen, Zeitstempel, Qualifikation.
 *
 * pyHanko fasst alles zu einem „bottom line“ zusammen, das schon dann
 * „ungültig“ meldet, wenn nur der Zeitstempeldienst unbekannt ist. Die
 * Ampeln zeigen, woran es liegt.
 */
class SignatureVerdict
{
    public const OK = 'success';

    public const WARN = 'warning';

    public const FAIL = 'danger';

    public const NONE = 'secondary';

    private const TRUST_PROBLEMS = [
        'NO_CERTIFICATE_CHAIN_FOUND' => 'keine Zertifikatskette zu einem Vertrauensanker',
        'REVOKED' => 'Zertifikat gesperrt',
        'TRY_LATER' => 'Sperrstatus derzeit nicht ermittelbar',
        'EXPIRED' => 'Zertifikat abgelaufen',
        'NOT_YET_VALID' => 'Zertifikat noch nicht gültig',
        'OUT_OF_BOUNDS_NO_POE' => 'außerhalb des Gültigkeitszeitraums, kein Existenznachweis',
        'CHAIN_CONSTRAINTS_FAILURE' => 'Bedingungen der Zertifikatskette verletzt',
        'CRYPTO_CONSTRAINTS_FAILURE' => 'Verfahren nicht mehr zulässig (z. B. schwacher Hash)',
        'SIG_CONSTRAINTS_FAILURE' => 'Signaturbedingungen verletzt',
        'NO_POE' => 'kein Existenznachweis',
        'GENERIC' => 'Vertrauen nicht feststellbar',
    ];

    private const MODIFICATIONS = [
        'NONE' => [self::OK, 'keine Änderungen nach der Signatur'],
        'LTA_UPDATES' => [self::OK, 'nur Prüfdaten/Zeitstempel ergänzt'],
        'FORM_FILLING' => [self::WARN, 'danach Formularfelder ausgefüllt oder signiert'],
        'ANNOTATIONS' => [self::WARN, 'danach Kommentare/Anmerkungen ergänzt'],
        'OTHER' => [self::FAIL, 'danach inhaltlich geändert'],
    ];

    /**
     * @param  array<string, mixed>  $sig
     * @return list<array{label: string, level: string, text: string}>
     */
    public static function aspects(array $sig): array
    {
        if (isset($sig['error'])) {
            return [self::aspect('Prüfung', self::FAIL, 'nicht möglich: '.$sig['error'])];
        }

        return array_values(array_filter([
            self::integrity($sig),
            self::coverage($sig),
            self::trust($sig),
            $sig['type'] === 'signature' ? self::timestamp($sig['timestamp'] ?? null) : null,
            self::revocationFetch($sig['fetch_error'] ?? null),
            self::qualification($sig['signer']['qc'] ?? null),
        ]));
    }

    /**
     * Gesamtbild für die Kopfzeile: das schlechteste Einzelergebnis.
     *
     * @param  array<string, mixed>  $sig
     */
    public static function overall(array $sig): string
    {
        $levels = array_column(self::aspects($sig), 'level');

        foreach ([self::FAIL, self::WARN, self::OK] as $level) {
            if (in_array($level, $levels, true)) {
                return $level;
            }
        }

        return self::NONE;
    }

    public static function trustProblem(?string $code): ?string
    {
        return $code === null ? null : (self::TRUST_PROBLEMS[$code] ?? $code);
    }

    /**
     * @param  array<string, mixed>  $sig
     */
    private static function integrity(array $sig): array
    {
        return ($sig['intact'] ?? false) && ($sig['valid'] ?? false)
            ? self::aspect('Integrität', self::OK, 'kryptographisch gültig, signierter Inhalt unverändert')
            : self::aspect('Integrität', self::FAIL, ($sig['intact'] ?? false)
                ? 'Signaturwert ungültig'
                : 'signierter Inhalt verändert (Prüfsumme stimmt nicht)');
    }

    /**
     * @param  array<string, mixed>  $sig
     */
    private static function coverage(array $sig): array
    {
        if (($sig['coverage'] ?? null) === 'ENTIRE_FILE') {
            return self::aspect('Umfang', self::OK, 'deckt die ganze Datei ab');
        }

        [$level, $text] = self::MODIFICATIONS[$sig['modification_level'] ?? 'OTHER'] ?? self::MODIFICATIONS['OTHER'];

        if (($sig['docmdp_ok'] ?? true) === false) {
            [$level, $text] = [self::FAIL, $text.' – von der Zertifizierung nicht erlaubt'];
        }

        return self::aspect('Umfang', $level, $text);
    }

    /**
     * @param  array<string, mixed>  $sig
     */
    private static function trust(array $sig): array
    {
        if ($sig['revoked'] ?? false) {
            return self::aspect('Vertrauen', self::FAIL, 'Zertifikat gesperrt');
        }

        if ($sig['trusted'] ?? false) {
            $anchor = $sig['path']['trust_anchor'] ?? null;

            return self::aspect('Vertrauen', self::OK, 'Kette bis '.($anchor ?? 'Vertrauensanker').' geprüft');
        }

        return self::aspect('Vertrauen', self::WARN, self::trustProblem($sig['trust_problem'] ?? 'GENERIC'));
    }

    /**
     * @param  array<string, mixed>|null  $ts
     */
    private static function timestamp(?array $ts): array
    {
        if (! $ts) {
            return self::aspect('Zeitstempel', self::NONE, 'kein Zeitstempel – Signaturzeit nur laut Unterzeichner');
        }

        $tsa = $ts['tsa']['common_name'] ?? $ts['tsa']['organization'] ?? 'unbekannt';

        if (! ($ts['intact'] ?? false) || ! ($ts['valid'] ?? false)) {
            return self::aspect('Zeitstempel', self::FAIL, "ungültig (Dienst: {$tsa})");
        }

        return ($ts['trusted'] ?? false)
            ? self::aspect('Zeitstempel', self::OK, "gültig und vertrauenswürdig ({$tsa})")
            : self::aspect('Zeitstempel', self::WARN, "gültig, Dienst nicht vertrauenswürdig ({$tsa}): "
                .self::trustProblem($ts['trust_problem'] ?? 'GENERIC'));
    }

    /**
     * Der Online-Abruf (OCSP/CRL, Zwischenzertifikate) ist gescheitert; das
     * Ergebnis beruht dann nur auf den eingebetteten Daten.
     */
    private static function revocationFetch(?string $error): ?array
    {
        return $error === null ? null : self::aspect('Sperrprüfung', self::WARN,
            'online nicht möglich, nur mit eingebetteten Daten geprüft ('.$error.')');
    }

    /**
     * Angabe im Zertifikat (QC-Statement) – ohne Abgleich mit der
     * EU-Vertrauensliste, daher höchstens „laut Zertifikat“.
     *
     * @param  array<string, bool>|null  $qc
     */
    private static function qualification(?array $qc): ?array
    {
        if (! $qc || ! ($qc['compliance'] ?? false)) {
            return null;
        }

        $kind = match (true) {
            $qc['esign'] ?? false => 'qualifizierte elektronische Signatur',
            $qc['eseal'] ?? false => 'qualifiziertes elektronisches Siegel',
            default => 'qualifiziertes Zertifikat',
        };

        return self::aspect('Qualifikation', self::OK, $kind.' laut Zertifikat'
            .(($qc['qscd'] ?? false) ? ', Schlüssel auf QSCD' : '')
            .' (nicht gegen EU-Vertrauensliste geprüft)');
    }

    /**
     * @return array{label: string, level: string, text: string}
     */
    private static function aspect(string $label, string $level, string $text): array
    {
        return ['label' => $label, 'level' => $level, 'text' => $text];
    }
}
