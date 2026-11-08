<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Models\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->actingAs(User::factory()->create());
});

test('mergeJson setzt einzelne Schlüssel und lässt die übrigen stehen', function () {
    $file = File::factory()->create([
        'generated_conversions' => ['preview' => 'c/preview.jpg', 'thumb' => 'c/thumb.jpg'],
    ]);

    // Ein zweiter Job hält eine veraltete Instanz ohne preview/thumb
    $stale = File::find($file->id);
    $stale->generated_conversions = null;

    $stale->mergeJson('generated_conversions', ['ocr' => 'o/ocr.pdf']);

    expect($file->fresh()->generated_conversions)->toBe([
        'preview' => 'c/preview.jpg',
        'thumb' => 'c/thumb.jpg',
        'ocr' => 'o/ocr.pdf',
    ])->and($stale->generated_conversions['ocr'])->toBe('o/ocr.pdf');

    $file->mergeJson('generated_conversions', ['ocr' => null]);

    expect($file->fresh()->generated_conversions)->toBe([
        'preview' => 'c/preview.jpg',
        'thumb' => 'c/thumb.jpg',
    ]);
});

test('OCR-Links: Text aus custom_properties, PDF aus generated_conversions', function () {
    Storage::disk('local')->put('ocrs/ocr-a.txt', 'Erkannter Text');
    $file = File::factory()->create([
        'disk' => 'local',
        'generated_conversions' => ['preview' => 'c/p.jpg', 'ocr' => 'ocrs/ocr-a.pdf'],
        'custom_properties' => ['ocr' => ['type' => 'ocrtext', 'disk' => 'local', 'text' => 'ocrs/ocr-a.txt']],
    ]);

    expect($file->ocrPdfPath())->toBe('ocrs/ocr-a.pdf')
        ->and($file->ocrTextPath())->toBe('ocrs/ocr-a.txt')
        ->and($file->ocrText())->toBe('Erkannter Text')
        ->and($file->ocrFailed())->toBeFalse()
        ->and($file->propsText())->toBe("--- OCR-Text ---\n\nErkannter Text");
});

test('propsText meldet fehlgeschlagenes OCR, fehlende Textdatei und fehlendes OCR', function () {
    $failed = File::factory()->create([
        'custom_properties' => ['ocr' => ['type' => 'noocr', 'text' => null, 'error' => 'ocrmypdf fehlt']],
    ]);
    $missing = File::factory()->create([
        'disk' => 'local',
        'custom_properties' => ['ocr' => ['type' => 'ocrtext', 'disk' => 'local', 'text' => 'weg.txt']],
    ]);

    expect($failed->ocrFailed())->toBeTrue()
        ->and($failed->propsText())->toBe("--- OCR nicht durchgeführt (noocr) ---\nocrmypdf fehlt\n")
        ->and($missing->ocrText())->toBeNull()
        ->and($missing->propsText())->toContain('OCR-Textdatei fehlt: weg.txt')
        ->and(File::factory()->create()->propsText())->toBe('');
});

test('die Detailansicht zeigt die Links im Props-Tab', function () {
    $file = File::factory()->create([
        'disk' => 'local',
        'generated_conversions' => ['thumb' => 'c/thumb-x.jpg', 'ocr' => 'o/ocr-x.pdf'],
        'custom_properties' => ['ocr' => ['type' => 'ocrtext', 'disk' => 'local', 'text' => 'o/ocr-x.txt']],
    ]);

    $this->get(route('files.show', $file))
        ->assertOk()
        ->assertSee('o/ocr-x.pdf')
        ->assertSee('o/ocr-x.txt');
});

test('die Migration übernimmt file_props in die Datei und löscht die Tabelle', function () {
    $migration = require __DIR__.'/../../database/migrations/2026_11_08_120000_move_file_props_to_file_columns.php';

    expect(Schema::hasTable('file_props'))->toBeFalse();
    $migration->down();

    Storage::disk('local')->put('ocrs/ocr-1.pdf', '%PDF');
    $ok = File::factory()->create(['disk' => 'local', 'generated_conversions' => ['thumb' => 'c/t.jpg']]);
    $err = File::factory()->create(['disk' => 'local']);

    DB::table('file_props')->insert([
        ['file_id' => $ok->id, 'key' => 'link2text', 'type' => 'text', 'memo' => 'Log',
            'data' => json_encode(['disk' => 'local', 'file' => 'ocrs/ocr-1.txt', 'size' => 42])],
        ['file_id' => $err->id, 'key' => 'link2text', 'type' => 'errocr', 'memo' => 'kaputt', 'data' => null],
    ]);

    $migration->up();

    expect(Schema::hasTable('file_props'))->toBeFalse();

    $ok->refresh();
    expect($ok->ocr())->toMatchArray(['type' => 'text', 'disk' => 'local', 'text' => 'ocrs/ocr-1.txt', 'size' => 42])
        ->and($ok->ocr())->not->toHaveKey('error')
        ->and($ok->generated_conversions)->toBe(['thumb' => 'c/t.jpg', 'ocr' => 'ocrs/ocr-1.pdf']);

    $err->refresh();
    expect($err->ocr())->toMatchArray(['type' => 'errocr', 'error' => 'kaputt'])
        ->and($err->ocr())->not->toHaveKey('text')
        ->and($err->generated_conversions)->toBeNull();
});
