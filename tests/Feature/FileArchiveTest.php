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

function fakeArchiveTools(array $exitCodes = []): void
{
    Process::fake(function (PendingProcess $process) use ($exitCodes) {
        $command = $process->command;
        $binary = $command[0];
        $code = $exitCodes[$binary] ?? 0;

        if ($code !== 0) {
            return Process::result('', "sh: 1: exec: {$binary}: not found", $code);
        }

        return match ($binary) {
            'ocrmypdf' => tap(Process::result('Output file is a PDF/A-2b'), fn () => file_put_contents($command[array_key_last($command)], ARCHIVE_PDFA_PDF)),
            'verapdf' => Process::result('<validationReport isCompliant="true">'),
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
