<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Models\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('can list all files', function () {
    File::factory()->count(5)->create();

    $response = $this->get('/files');

    $response->assertStatus(200);
    $response->assertViewIs('files::files');
    $response->assertViewHas('files');
});

it('can filter files by status', function () {
    File::factory()->count(3)->create(['status' => 'active']);
    File::factory()->count(2)->create(['status' => 'inactive']);

    $response = $this->get('/files?status=active');

    $response->assertStatus(200);
    $response->assertViewHas('files', function ($files) {
        return $files->count() === 3;
    });
});

it('can filter files by type', function () {
    File::factory()->count(2)->pdf()->create();
    File::factory()->count(3)->jpg()->create();

    $response = $this->get('/files?type=pdf');

    $response->assertStatus(200);
    $response->assertViewHas('files', function ($files) {
        return $files->count() === 2;
    });
});

it('can search files', function () {
    File::factory()->create(['name' => 'important-document.pdf']);
    File::factory()->create(['name' => 'random-file.pdf']);

    $response = $this->get('/files?search=important');

    $response->assertStatus(200);
    $response->assertSee('important-document.pdf');
    $response->assertDontSee('random-file.pdf');
});

it('can show a single file', function () {
    $file = File::factory()->create();

    $response = $this->get("/files/{$file->id}/edit");

    $response->assertStatus(200);
    $response->assertViewIs('files::file-show');
    $response->assertViewHas('file', $file);
});

it('can download a file', function () {
    $file = File::factory()->create([
        'disk' => 'public',
        'file' => 'test.pdf',
    ]);

    Storage::disk('public')->put($file->getPathToFile(), 'test content');

    $response = $this->get("/files/{$file->id}");

    $response->assertStatus(200);
    $response->assertHeader('content-type');
});

it('can update file status', function () {
    $file = File::factory()->create(['status' => 'active']);

    $response = $this->put("/files/{$file->id}", [
        'status' => 'inactive',
    ]);

    $response->assertRedirect();

    $file->refresh();
    expect($file->status)->toBe('inactive');
});

it('can update file type', function () {
    $file = File::factory()->create(['type' => 'pdf']);

    $response = $this->put("/files/{$file->id}", [
        'type' => 'jpg',
    ]);

    $response->assertRedirect();

    $file->refresh();
    expect($file->type)->toBe('jpg');
});

it('can delete a file', function () {
    $file = File::factory()->create();

    $response = $this->delete("/files/{$file->id}");

    $response->assertRedirect('/files');
    $this->assertDatabaseMissing('files', ['id' => $file->id]);
});

it('paginates file list', function () {
    File::factory()->count(25)->create();

    $response = $this->get('/files');

    $response->assertStatus(200);
    $response->assertViewHas('files', function ($files) {
        return $files instanceof LengthAwarePaginator;
    });
});

it('can sort files by name', function () {
    File::factory()->create(['name' => 'zebra.pdf']);
    File::factory()->create(['name' => 'apple.pdf']);
    File::factory()->create(['name' => 'banana.pdf']);

    $response = $this->get('/files?sort=name&direction=asc');

    $response->assertStatus(200);
    $response->assertSeeInOrder(['apple.pdf', 'banana.pdf', 'zebra.pdf']);
});

it('can sort files by size', function () {
    File::factory()->create(['size' => 1000]);
    File::factory()->create(['size' => 5000]);
    File::factory()->create(['size' => 2000]);

    $response = $this->get('/files?sort=size&direction=desc');

    $response->assertStatus(200);
});

it('displays file metadata in show view', function () {
    $file = File::factory()->create([
        'name' => 'test-document.pdf',
        'size' => 2048000,
        'type' => 'pdf',
        'mime' => 'application/pdf',
    ]);

    $response = $this->get("/files/{$file->id}/edit");

    $response->assertStatus(200);
    $response->assertSee('test-document.pdf');
    $response->assertSee('pdf');
});

it('returns 404 for non-existent file', function () {
    $response = $this->get('/files/99999/edit');

    $response->assertStatus(404);
});

it('can bulk update file status', function () {
    $files = File::factory()->count(3)->create(['status' => 'active']);

    $response = $this->post('/files', [
        'status' => 'inactive',
        'id' => $files->pluck('id')->toArray(),
    ]);

    $response->assertRedirect();

    foreach ($files as $file) {
        $file->refresh();
        expect($file->status)->toBe('inactive');
    }
});

it('can bulk update file type', function () {
    $files = File::factory()->count(3)->create(['type' => 'pdf']);

    $response = $this->post('/files', [
        'type' => 'jpg',
        'id' => $files->pluck('id')->toArray(),
    ]);

    $response->assertRedirect();

    foreach ($files as $file) {
        $file->refresh();
        expect($file->type)->toBe('jpg');
    }
});

it('shows file with OCR text', function () {
    Storage::fake('local');
    Storage::disk('local')->put('ocr/ocr-x.txt', 'Sample OCR text');
    $file = File::factory()->create([
        'disk' => 'local',
        'custom_properties' => ['ocr' => ['type' => 'ocrtext', 'disk' => 'local', 'text' => 'ocr/ocr-x.txt']],
    ]);

    $response = $this->get("/files/{$file->id}/edit");

    $response->assertStatus(200);
    $response->assertSee('Sample OCR text');
});

it('formats file size correctly', function () {
    $file = File::factory()->create(['size' => 2048000]);

    expect($file->formatBytes())->toContain('MB');
});
