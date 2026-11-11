<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Nfgarching\Files\Models\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('eine gesperrte Datei bleibt Original Dokument und wird nie ersetzt', function () {
    config(['files.archive.original.replace' => 'always']);
    $file = File::factory()->create();

    $file->lock('bea-signature', 'Signatur liegt in der Original-ZIP');

    expect($file->isLocked())->toBeTrue()
        ->and($file->lockInfo()['reason'])->toBe('bea-signature')
        ->and($file->isOriginalDocument())->toBeTrue()
        ->and($file->shouldReplaceOriginal())->toBeFalse();

    $file->setOriginalDocument(false);

    expect($file->fresh()->isOriginalDocument())->toBeTrue();

    $file->unlock();

    expect($file->fresh()->isLocked())->toBeFalse();
});

test('gesperrte Dateien werden weder einzeln noch per Sammelaktion gelöscht', function () {
    $locked = File::factory()->create();
    $locked->lock('bea-signature');
    $free = File::factory()->create();

    $this->delete(route('files.destroy', $locked));
    $this->post(route('files.bulk-action'), ['switchTo' => 'delete', 'checks' => [$locked->id, $free->id]]);

    expect(File::find($locked->id))->not->toBeNull()
        ->and(File::find($free->id))->toBeNull();
});
