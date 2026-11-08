<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Jobs\InspectPdf;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Services\Inspection\SignatureVerdict;

uses(RefreshDatabase::class);

/*
 * poppler, veraPDF und das pyHanko-Skript werden mit Process::fake simuliert.
 * Die Ausgaben entsprechen echten Ausgaben der Programme (gekürzt).
 */

const INSPECT_PDFINFO = <<<'TXT'
Title:           Klageschrift
Creator:         ocrmypdf 15.2.0
Producer:        pikepdf 8.7.1; pyHanko 0.38.1
CreationDate:    2026-11-05T19:20:53+01
ModDate:         2026-11-08T18:23:47+01
Tagged:          no
Form:            AcroForm
JavaScript:      no
Pages:           8
Encrypted:       no
Page size:       595.276 x 841.89 pts (A4)
File size:       373029 bytes
Optimized:       no
PDF version:     1.7
TXT;

const INSPECT_XMP = '<rdf:Description pdfaid:part="2" pdfaid:conformance="B"></rdf:Description>';

const INSPECT_FONTS = <<<'TXT'
name                                 type              encoding         emb sub uni object ID
------------------------------------ ----------------- ---------------- --- --- --- ---------
AAAAAA+ArialMT                       TrueType          WinAnsi          yes yes no      16  0
Helvetica                            Type 1            Standard         no  no  no      20  0
TXT;

const INSPECT_IMAGES = <<<'TXT'
page   num  type   width height color comp bpc  enc interp  object ID x-ppi y-ppi size ratio
--------------------------------------------------------------------------------------------
   1     0 image    2416  3398  icc     1   1  jbig2  no        29  0   300   300 22.5K 2.2%
   2     1 image    2416  3402  icc     1   1  jbig2  no        35  0   100   100 38.8K 3.9%
TXT;

function inspectSignatureJson(array $overrides = []): string
{
    return json_encode(['ok' => true, 'dss' => false, 'pades_level' => 'B-T', 'trust_missing' => [], 'fetch' => false,
        'signatures' => [array_replace_recursive([
            'field' => 'Archivsiegel', 'type' => 'signature', 'sub_filter' => '/ETSI.CAdES.detached', 'pades' => true,
            'reason' => 'Archivierung', 'byte_range' => [0, 100, 200, 50], 'visible' => false,
            'intact' => true, 'valid' => true, 'trusted' => true, 'revoked' => false, 'bottom_line' => false,
            'md_algorithm' => 'sha384', 'mechanism' => 'sha384_rsa', 'coverage' => 'ENTIRE_FILE',
            'modification_level' => 'NONE', 'docmdp_ok' => true, 'signer_reported_time' => '2026-11-08T18:23:47+01:00',
            'path' => ['trust_anchor' => 'AkV 2026 Test-CA'],
            'signer' => ['common_name' => 'AkV 2026 Testsiegel', 'subject' => 'CN=AkV 2026 Testsiegel', 'qc' => null],
            'timestamp' => ['time' => '2026-11-08T17:23:49+00:00', 'intact' => true, 'valid' => true, 'trusted' => false,
                'trust_problem' => 'NO_CERTIFICATE_CHAIN_FOUND', 'tsa' => ['common_name' => 'www.freetsa.org']],
        ], $overrides)],
    ]);
}

function fakeInspectionTools(): void
{
    Process::fake(function (PendingProcess $process) {
        $command = $process->command;

        return match (true) {
            $command[0] === 'pdfinfo' && $command[1] === '-meta' => Process::result(INSPECT_XMP),
            $command[0] === 'pdfinfo' => Process::result(INSPECT_PDFINFO),
            $command[0] === 'pdffonts' => Process::result(INSPECT_FONTS),
            $command[0] === 'pdfimages' => Process::result(INSPECT_IMAGES),
            $command[0] === 'pdfdetach' => Process::result("1 embedded files\n1: factur-x.xml\n"),
            $command[0] === 'pdftotext' => Process::result(str_repeat('Sehr geehrte Damen und Herren ', 5)),
            $command[0] === 'verapdf' => Process::result(
                '<?xml version="1.0"?><report><validationReport isCompliant="false"><details failedRules="1"></details>'
                .'<rule specification="ISO 19005-2:2011" clause="6.2.4.3" testNumber="2" status="failed" failedChecks="125">'
                .'<description>DeviceRGB shall only be used …</description>'
                .'<check status="failed"><errorMessage>DeviceRGB colour space is used without RGB output intent profile</errorMessage></check>'
                .'</rule></validationReport></report>'
            ),
            str_ends_with($command[2] ?? '', 'pdf_signatures.py') => Process::result(inspectSignatureJson()),
            default => Process::result('', 'unbekannt', 1),
        };
    });
}

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());

    config([
        'files.inspect.python' => 'python-test',
        'files.inspect.trust' => [],
        'files.inspect.fetch' => false,
        'files.archive.validate' => ['enabled' => true, 'binary' => 'verapdf', 'flavour' => '2b'],
    ]);
});

function inspectTestPdf(array $attributes = []): File
{
    $file = File::factory()->pdf()->create(['disk' => 'local'] + $attributes);
    Storage::disk('local')->put($file->file, '%PDF-1.7 original');

    return $file;
}

test('die Prüfung speichert Eigenschaften, Signaturen, PDF/A und Prüfsumme', function () {
    fakeInspectionTools();
    $file = inspectTestPdf();

    InspectPdf::dispatchSync($file->id);

    $original = $file->fresh()->inspection()['variants']['original'];

    expect($original['status'])->toBe('ok')
        ->and($original['properties']['metadata']['title'])->toBe('Klageschrift')
        ->and($original['properties']['pages'])->toBe(8)
        ->and($original['properties']['xmp']['pdfa'])->toBe('2b')
        ->and($original['properties']['fonts'][1])->toBe(['name' => 'Helvetica', 'type' => 'Type 1', 'embedded' => false])
        ->and($original['properties']['fonts'][0]['name'])->toBe('ArialMT')
        ->and($original['properties']['images'])->toMatchArray(['count' => 2, 'ppi_min' => 100, 'ppi_max' => 300])
        ->and($original['properties']['attachments'])->toBe(['factur-x.xml'])
        ->and($original['properties']['text_layer']['present'])->toBeTrue()
        ->and($original['signatures']['status'])->toBe('signed')
        ->and($original['signatures']['signatures'][0]['field'])->toBe('Archivsiegel')
        ->and($original['pdfa']['validation'])->toMatchArray(['status' => 'noncompliant', 'flavour' => '2b', 'failed_rules' => 1])
        ->and($original['pdfa']['validation']['rules'][0])->toMatchArray(['clause' => '6.2.4.3', 'checks' => 125])
        ->and($original['integrity']['sha256'])->toBe(hash('sha256', '%PDF-1.7 original'))
        ->and($original['integrity'])->not->toHaveKey('ok');

    // veraPDF prüft gegen das Profil laut XMP-Kennung
    Process::assertRan(fn (PendingProcess $p) => $p->command[0] === 'verapdf' && $p->command[2] === '2b');
});

test('Original und Archivfassung werden mit den Prüfsummen der Archivierung verglichen', function () {
    fakeInspectionTools();
    $file = inspectTestPdf([
        'generated_conversions' => ['archive' => 'archive/archive-x.pdf'],
        'custom_properties' => ['archive' => [
            'status' => 'archived', 'disk' => 'local', 'path' => 'archive/archive-x.pdf',
            'source_sha256' => hash('sha256', '%PDF-1.7 original'),
            'sha256' => hash('sha256', 'anderer Inhalt'),
        ]],
    ]);
    Storage::disk('local')->put('archive/archive-x.pdf', '%PDF-1.7 archiv');

    InspectPdf::dispatchSync($file->id);

    $variants = $file->fresh()->inspection()['variants'];

    expect($variants['original']['integrity']['ok'])->toBeTrue()
        ->and($variants['archive']['integrity']['ok'])->toBeFalse()
        // das Archivergebnis bleibt neben der Prüfung erhalten
        ->and($file->fresh()->archive()['status'])->toBe('archived');
});

test('fehlende Archivdatei wird als missing gemeldet', function () {
    fakeInspectionTools();
    $file = inspectTestPdf(['generated_conversions' => ['archive' => 'archive/fehlt.pdf']]);

    InspectPdf::dispatchSync($file->id);

    expect($file->fresh()->inspection()['variants']['archive']['status'])->toBe('missing');
});

test('ohne PDF/A-Kennung wird veraPDF nicht aufgerufen', function () {
    Process::fake([
        '*pdfinfo*-meta*' => Process::result(''),
        '*pdf_signatures.py*' => Process::result(json_encode(['ok' => true, 'signatures' => [], 'dss' => false, 'pades_level' => null])),
        '*' => Process::result(''),
    ]);
    $file = inspectTestPdf();

    InspectPdf::dispatchSync($file->id);

    $original = $file->fresh()->inspection()['variants']['original'];
    expect($original['pdfa'])->toBe(['claimed' => null, 'validation' => ['status' => 'unclaimed']])
        ->and($original['signatures']['status'])->toBe('unsigned');
    Process::assertDidntRun(fn (PendingProcess $p) => $p->command[0] === 'verapdf');
});

test('fehlt pyHanko, meldet die Signaturprüfung notool', function () {
    Process::fake([
        '*pdf_signatures.py*' => Process::result(json_encode(['ok' => false, 'notool' => true, 'error' => 'pyHanko nicht importierbar'])),
        '*' => Process::result(''),
    ]);
    $file = inspectTestPdf();

    InspectPdf::dispatchSync($file->id);

    expect($file->fresh()->inspection()['variants']['original']['signatures'])
        ->toMatchArray(['status' => 'notool', 'error' => 'pyHanko nicht importierbar']);
});

test('der Tab PDF zeigt das Prüfergebnis an', function () {
    fakeInspectionTools();
    $file = inspectTestPdf();
    InspectPdf::dispatchSync($file->id);

    $this->get(route('files.show', $file))
        ->assertOk()
        ->assertSee('AkV 2026 Testsiegel')
        ->assertSee('kryptographisch gültig, signierter Inhalt unverändert')
        ->assertSee('Dienst nicht vertrauenswürdig (www.freetsa.org)')
        ->assertSee('PAdES-Level B-T')
        ->assertSee('PDF/A-2b')
        ->assertSee('6.2.4.3')
        ->assertSee('nicht eingebettet: Helvetica')
        ->assertSee('geringe Auflösung')
        ->assertSee('factur-x.xml');
});

test('Neu prüfen führt die Prüfung aus und öffnet den Tab PDF', function () {
    fakeInspectionTools();
    $file = inspectTestPdf();

    $this->post(route('files.inspect', $file), ['search' => 'Klage'])
        ->assertRedirect(route('files.show', ['file' => $file, 'search' => 'Klage', 'tab' => 'pdf']));

    expect($file->fresh()->inspection())->not->toBeNull();
});

test('files:inspect --all prüft nur PDFs ohne Ergebnis', function () {
    fakeInspectionTools();
    $neu = inspectTestPdf();
    $geprueft = inspectTestPdf(['custom_properties' => ['inspection' => ['at' => 'gestern', 'variants' => []]]]);
    File::factory()->jpg()->create();

    $this->artisan('files:inspect', ['--all' => true, '--sync' => true])
        ->expectsOutput('1 PDF(s) geprüft.')
        ->assertSuccessful();

    expect($neu->fresh()->inspection())->not->toBeNull()
        ->and($geprueft->fresh()->inspection()['at'])->toBe('gestern');
});

describe('SignatureVerdict', function () {
    $sig = fn (array $overrides = []) => array_replace_recursive(
        json_decode(inspectSignatureJson(), true)['signatures'][0], $overrides,
    );

    test('ein unbekannter Zeitstempeldienst ist eine Warnung, kein Fehler', function () use ($sig) {
        $aspects = collect(SignatureVerdict::aspects($sig()))->keyBy('label');

        expect($aspects['Integrität']['level'])->toBe(SignatureVerdict::OK)
            ->and($aspects['Vertrauen']['level'])->toBe(SignatureVerdict::OK)
            ->and($aspects['Zeitstempel']['level'])->toBe(SignatureVerdict::WARN)
            ->and(SignatureVerdict::overall($sig()))->toBe(SignatureVerdict::WARN);
    });

    test('ein veränderter Inhalt ist ein Fehler', function () use ($sig) {
        $aspects = collect(SignatureVerdict::aspects($sig(['intact' => false, 'valid' => false])))->keyBy('label');

        expect($aspects['Integrität'])->toMatchArray(['level' => SignatureVerdict::FAIL, 'text' => 'signierter Inhalt verändert (Prüfsumme stimmt nicht)']);
    });

    test('nachträgliche Änderungen werden nach Art bewertet', function () use ($sig) {
        $coverage = fn (string $level) => collect(SignatureVerdict::aspects($sig([
            'coverage' => 'ENTIRE_REVISION', 'modification_level' => $level,
        ])))->firstWhere('label', 'Umfang')['level'];

        expect($coverage('LTA_UPDATES'))->toBe(SignatureVerdict::OK)
            ->and($coverage('FORM_FILLING'))->toBe(SignatureVerdict::WARN)
            ->and($coverage('OTHER'))->toBe(SignatureVerdict::FAIL);
    });

    test('fehlendes Vertrauen und Sperrung werden unterschieden', function () use ($sig) {
        $trust = fn (array $o) => collect(SignatureVerdict::aspects($sig($o)))->firstWhere('label', 'Vertrauen');

        expect($trust(['trusted' => false, 'trust_problem' => 'NO_CERTIFICATE_CHAIN_FOUND']))
            ->toMatchArray(['level' => SignatureVerdict::WARN, 'text' => 'keine Zertifikatskette zu einem Vertrauensanker'])
            ->and($trust(['trusted' => false, 'revoked' => true])['level'])->toBe(SignatureVerdict::FAIL);
    });

    test('QC-Statements ergeben die Qualifikation laut Zertifikat', function () use ($sig) {
        $aspect = collect(SignatureVerdict::aspects($sig([
            'signer' => ['qc' => ['compliance' => true, 'qscd' => true, 'esign' => true, 'eseal' => false]],
        ])))->firstWhere('label', 'Qualifikation');

        expect($aspect['text'])->toContain('qualifizierte elektronische Signatur laut Zertifikat, Schlüssel auf QSCD');
    });

    test('ohne Zeitstempel ist die Signaturzeit nur behauptet', function () use ($sig) {
        $data = $sig();
        $data['timestamp'] = null;

        expect(collect(SignatureVerdict::aspects($data))->firstWhere('label', 'Zeitstempel')['level'])
            ->toBe(SignatureVerdict::NONE);
    });
});
