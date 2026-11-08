<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Jobs\ArchivePdf;
use Nfgarching\Files\Models\File;

uses(RefreshDatabase::class);

/*
 * Die externen Programme (ocrmypdf, veraPDF, pyHanko) werden mit
 * Process::fake simuliert. Der Fake legt – wie die echten Programme – die
 * Ausgabedatei an (letztes bzw. vorletztes Argument).
 */

const ARCHIVE_PLAIN_PDF = "%PDF-1.7\n1 0 obj << /Type /Catalog >> endobj\n%%EOF\n";
const ARCHIVE_PDFA_PDF = "%PDF-1.7\n<x:xmpmeta><pdfaid:part>2</pdfaid:part></x:xmpmeta>\n%%EOF\n";
const ARCHIVE_SIGNED_PDF = "%PDF-1.7\n<< /Type /Sig /ByteRange [0 10 20 30] >>\n%%EOF\n";

function fakeArchiveTools(array $exitCodes = [], bool $compliant = true): void
{
    Process::fake(function (PendingProcess $process) use ($exitCodes, $compliant) {
        $command = $process->command;
        $binary = $command[0];
        $code = $exitCodes[$binary] ?? 0;

        if ($code !== 0) {
            return Process::result('', "sh: 1: exec: {$binary}: not found", $code);
        }

        return match ($binary) {
            'ocrmypdf' => tap(Process::result('Output file is a PDF/A-2b'), fn () => file_put_contents($command[array_key_last($command)], ARCHIVE_PDFA_PDF)),
            'verapdf' => Process::result('<validationReport isCompliant="'.($compliant ? 'true' : 'false').'" failedRules="2">'),
            // pkcs12 … INFILE OUTFILE PFX → Ausgabe ist das vorletzte Argument
            'pyhanko' => tap(Process::result(''), fn () => file_put_contents($command[count($command) - 2], ARCHIVE_PDFA_PDF.ARCHIVE_SIGNED_PDF)),
            default => Process::result('', 'unbekannt', 1),
        };
    });
}

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());

    $this->certificate = tempnam(sys_get_temp_dir(), 'cert');
    file_put_contents($this->certificate, 'dummy');

    config([
        // PDF-Prüfung hat eigene Tests (FileInspectionTest)
        'files.inspect.enabled' => false,
        'files.archive.enabled' => true,
        'files.archive.skip_signed' => true,
        'files.archive.pdfa' => ['enabled' => true, 'binary' => 'ocrmypdf', 'output_type' => 'pdfa-2', 'optimize' => 1],
        'files.archive.validate' => ['enabled' => true, 'binary' => 'verapdf', 'flavour' => '2b'],
        'files.archive.sign' => [
            'enabled' => true,
            'binary' => 'pyhanko',
            'certificate' => $this->certificate,
            'password' => 'geheim',
            'tsa_url' => 'https://tsa.example/tsr',
            'field_name' => 'Archivsiegel',
            'pades' => true,
        ],
    ]);
});

afterEach(fn () => @unlink($this->certificate));

function archiveTestPdf(string $content): File
{
    $file = File::factory()->pdf()->create(['disk' => 'local']);
    Storage::disk('local')->put($file->file, $content);

    return $file;
}

test('ein PDF wird nach PDF/A gewandelt, validiert, gesiegelt und archiviert', function () {
    fakeArchiveTools();
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);

    ArchivePdf::dispatchSync($file->id);

    $file->refresh();
    $archive = $file->archive();

    expect($archive['status'])->toBe('archived')
        ->and($archive['pdfa']['status'])->toBe('converted')
        ->and($archive['validation']['status'])->toBe('compliant')
        ->and($archive['signature']['status'])->toBe('signed')
        ->and($archive['signature']['timestamp'])->toBe('https://tsa.example/tsr')
        ->and($archive['source_sha256'])->toBe(hash('sha256', ARCHIVE_PLAIN_PDF))
        ->and($file->archivePdfPath())->toBe($archive['path'])
        ->and($file->isArchiveSigned())->toBeTrue();

    Storage::disk('local')->assertExists($archive['path']);
    // Original bleibt bitgenau erhalten
    expect(Storage::disk('local')->get($file->file))->toBe(ARCHIVE_PLAIN_PDF);
});

test('das Zertifikatspasswort wird über stdin übergeben, nicht als Argument', function () {
    fakeArchiveTools();
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);

    ArchivePdf::dispatchSync($file->id);

    Process::assertRan(fn (PendingProcess $process) => $process->command[0] === 'pyhanko'
        && $process->input === "geheim\n"
        && ! in_array('geheim', $process->command, true)
        && in_array('--timestamp-url', $process->command, true)
        && in_array('--use-pades', $process->command, true));
});

test('ein vorhandenes OCR-PDF/A wird nicht erneut umgewandelt', function () {
    fakeArchiveTools();
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);
    Storage::disk('local')->put('ocrs/ocr-a.pdf', ARCHIVE_PDFA_PDF);
    $file->mergeJson('generated_conversions', ['ocr' => 'ocrs/ocr-a.pdf']);
    $file->mergeJson('custom_properties', ['ocr' => ['type' => 'ocrtext', 'disk' => 'local']]);

    ArchivePdf::dispatchSync($file->id);

    expect($file->fresh()->archive()['pdfa']['status'])->toBe('existing');
    Process::assertDidntRun(fn (PendingProcess $process) => $process->command[0] === 'ocrmypdf');
});

test('bereits signierte PDFs bleiben unverändert', function () {
    fakeArchiveTools();
    $file = archiveTestPdf(ARCHIVE_SIGNED_PDF);

    ArchivePdf::dispatchSync($file->id);

    $file->refresh();
    expect($file->archive()['status'])->toBe('skipped')
        ->and($file->archive()['reason'])->toBe('signed_input')
        ->and($file->archivePdfPath())->toBeNull();
    Process::assertNothingRan();
});

test('fehlende Programme brechen nicht ab, sondern werden vermerkt', function () {
    fakeArchiveTools(['ocrmypdf' => 127, 'pyhanko' => 127]);
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);

    ArchivePdf::dispatchSync($file->id);

    $archive = $file->fresh()->archive();
    expect($archive['status'])->toBe('error')
        ->and($archive['pdfa']['status'])->toBe('notool')
        ->and($archive['signature']['status'])->toBe('notool')
        ->and($file->fresh()->archivePdfPath())->toBeNull();
});

test('ohne Zertifikat wird PDF/A archiviert, aber nicht gesiegelt', function () {
    fakeArchiveTools();
    config(['files.archive.sign.certificate' => null]);
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);

    ArchivePdf::dispatchSync($file->id);

    $archive = $file->fresh()->archive();
    expect($archive['status'])->toBe('archived')
        ->and($archive['signature']['status'])->toBe('nocert');
    Process::assertDidntRun(fn (PendingProcess $process) => $process->command[0] === 'pyhanko');
});

/*
 * Eigene Dokumente: Archivfassung ersetzt das Original, Papierkorb 90 Tage
 */

const ARCHIVE_OWN_PDF = "%PDF-1.7\n1 0 obj << /Producer (LibreOffice 24.2) /Creator (Writer) >> endobj\n%%EOF\n";

test('ein eigenes Dokument wird nach der Archivierung durch die Archivfassung ersetzt', function () {
    fakeArchiveTools();
    config(['files.archive.own.replace' => 'own', 'files.archive.own.trash_days' => 90]);
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);
    $originalPath = $file->file;
    $file->setOwn(true);

    ArchivePdf::dispatchSync($file->id);

    $file->refresh();
    $original = $file->archive()['original'];

    expect($file->isOriginalReplaced())->toBeTrue()
        ->and($file->file)->toBe($file->archivePdfPath())
        ->and($file->hash)->toBe(md5(Storage::disk('local')->get($file->file)))
        ->and($original['path'])->toBe($originalPath)
        ->and($original['sha256'])->toBe(hash('sha256', ARCHIVE_PLAIN_PDF))
        ->and($original['purge_after'])->toBe(now()->addDays(90)->toDateString());

    Storage::disk('local')->assertMissing($originalPath);
    expect(Storage::disk('local')->get($original['trash_path']))->toBe(ARCHIVE_PLAIN_PDF);
});

test('ohne Kennzeichnung bleibt das Original die Hauptdatei', function () {
    fakeArchiveTools();
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);
    $originalPath = $file->file;

    ArchivePdf::dispatchSync($file->id);

    expect($file->fresh()->file)->toBe($originalPath)
        ->and($file->fresh()->isOriginalReplaced())->toBeFalse();
});

test('ohne gültige PDF/A-Validierung wird nicht ersetzt', function () {
    fakeArchiveTools(compliant: false);
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);
    $originalPath = $file->file;
    $file->setOwn(true);

    ArchivePdf::dispatchSync($file->id);

    $file->refresh();
    expect($file->archive()['validation']['status'])->toBe('noncompliant')
        ->and($file->file)->toBe($originalPath)
        ->and($file->isOriginalReplaced())->toBeFalse();
});

test('der Schalter ersetzt nachträglich und holt das Original wieder zurück', function () {
    fakeArchiveTools();
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);
    $originalPath = $file->file;
    ArchivePdf::dispatchSync($file->id);

    $file->refresh()->setOwn(true);
    expect($file->fresh()->isOriginalReplaced())->toBeTrue();

    $file->refresh()->setOwn(false);
    $file->refresh();

    expect($file->isOriginalReplaced())->toBeFalse()
        ->and($file->file)->toBe($originalPath)
        ->and($file->archive()['original']['restored_at'])->not->toBeNull()
        ->and(Storage::disk('local')->get($originalPath))->toBe(ARCHIVE_PLAIN_PDF);
    // Archivfassung bleibt erhalten
    Storage::disk('local')->assertExists($file->archivePdfPath());
});

test('ein ersetztes Original wird nicht erneut archiviert', function () {
    fakeArchiveTools();
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);
    $file->setOwn(true);
    ArchivePdf::dispatchSync($file->id);
    $before = $file->fresh()->archive();

    ArchivePdf::dispatchSync($file->id);

    expect($file->fresh()->archive())->toBe($before);
});

test('files:prune-trash löscht Originale erst nach Ablauf der Frist', function () {
    fakeArchiveTools();
    config(['files.archive.own.trash_days' => 90]);
    $file = archiveTestPdf(ARCHIVE_PLAIN_PDF);
    $file->setOwn(true);
    ArchivePdf::dispatchSync($file->id);
    $trashPath = $file->fresh()->archive()['original']['trash_path'];

    $this->travel(89)->days();
    $this->artisan('files:prune-trash')->assertSuccessful();
    Storage::disk('local')->assertExists($trashPath);

    $this->travel(2)->days();
    $this->artisan('files:prune-trash')->assertSuccessful();
    Storage::disk('local')->assertMissing($trashPath);

    $file->refresh();
    expect($file->hasTrashedOriginal())->toBeFalse()
        ->and($file->archive()['original']['purged_at'])->not->toBeNull()
        ->and($file->setOwn(false))->toBeNull()
        ->and($file->fresh()->isOriginalReplaced())->toBeTrue();
});

test('Vorbelegung: ausdrücklich, Import-Disk, Erzeugerprogramm', function () {
    config([
        'files.archive.own.disks' => ['scan_inbox'],
        'files.archive.own.producers' => ['libreoffice'],
    ]);
    $path = tempnam(sys_get_temp_dir(), 'pdf');
    file_put_contents($path, ARCHIVE_OWN_PDF);

    expect(File::detectOwn($path, false)['source'])->toBe('app')
        ->and(File::detectOwn($path, false)['value'])->toBeFalse()
        ->and(File::detectOwn($path, null, 'scan_inbox')['source'])->toBe('disk')
        ->and(File::detectOwn($path)['value'])->toBeTrue()
        ->and(File::detectOwn($path)['source'])->toBe('producer')
        ->and(File::detectOwn($path)['producer'])->toBe('Writer / LibreOffice 24.2');

    file_put_contents($path, ARCHIVE_PLAIN_PDF);
    expect(File::detectOwn($path)['value'])->toBeFalse()
        ->and(File::detectOwn($path)['source'])->toBe('none');

    unlink($path);
});
