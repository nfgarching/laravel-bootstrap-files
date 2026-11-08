<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Jobs\ProcessFile;
use Nfgarching\Files\Models\File;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Queue::fake();
    $this->withoutMiddleware();

    // Share empty errors bag with all views for testing
    view()->share('errors', session()->get('errors', new \Illuminate\Support\ViewErrorBag));
});

it('displays upload form', function () {
    $response = $this->get('/upload');

    $response->assertStatus(200);
    $response->assertViewIs('files::upload');
});

it('can upload a single PDF file', function () {
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $response = $this->post('/upload', [
        'file' => $file,
    ]);

    $response->assertRedirect('/upload');

    $this->assertDatabaseHas('files', [
        'name' => 'document.pdf',
        'type' => 'pdf',
        'mime' => 'application/pdf',
        'status' => 'active',
    ]);

    Queue::assertPushed(ProcessFile::class);
});

it('can upload a single JPG file', function () {
    $file = UploadedFile::fake()->image('photo.jpg');

    $response = $this->post('/upload', [
        'file' => $file,
    ]);

    $response->assertRedirect('/upload');

    $this->assertDatabaseHas('files', [
        'name' => 'photo.jpg',
        'type' => 'jpg',
        'status' => 'active',
    ]);

    Queue::assertPushed(ProcessFile::class);
});

it('can upload a single PNG file', function () {
    $file = UploadedFile::fake()->image('image.png');

    $response = $this->post('/upload', [
        'file' => $file,
    ]);

    $response->assertRedirect('/upload');

    $this->assertDatabaseHas('files', [
        'name' => 'image.png',
        'type' => 'png',
        'status' => 'active',
    ]);

    Queue::assertPushed(ProcessFile::class);
});

it('can upload multiple files at once', function () {
    $files = [
        UploadedFile::fake()->create('document1.pdf', 1024, 'application/pdf'),
        UploadedFile::fake()->image('photo.jpg'),
        UploadedFile::fake()->image('image.png'),
    ];

    $response = $this->post('/upload', [
        'files' => $files,
    ]);

    $response->assertRedirect('/upload');

    $this->assertDatabaseHas('files', ['name' => 'document1.pdf']);
    $this->assertDatabaseHas('files', ['name' => 'photo.jpg']);
    $this->assertDatabaseHas('files', ['name' => 'image.png']);

    Queue::assertPushed(ProcessFile::class, 3);
});

it('validates file type', function () {
    $file = UploadedFile::fake()->create('document.txt', 1024, 'text/plain');

    $response = $this->post('/upload', [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('files', 0);
});

it('validates file size maximum', function () {
    $file = UploadedFile::fake()->create('large.pdf', 11000, 'application/pdf');

    $response = $this->post('/upload', [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('files', 0);
});

it('validates file size minimum', function () {
    $file = UploadedFile::fake()->create('tiny.pdf', 0, 'application/pdf');

    $response = $this->post('/upload', [
        'file' => $file,
    ]);

    $response->assertSessionHasErrors('file');
    $this->assertDatabaseCount('files', 0);
});

it('requires a file to be uploaded', function () {
    $response = $this->post('/upload', []);

    $response->assertRedirect('/upload');
    $this->assertDatabaseCount('files', 0);
});

it('stores file metadata correctly', function () {
    $file = UploadedFile::fake()->create('test.pdf', 2048, 'application/pdf');

    $response = $this->post('/upload', [
        'file' => $file,
    ]);

    $uploadedFile = File::first();

    expect($uploadedFile)->not->toBeNull()
        ->and($uploadedFile->name)->toBe('test.pdf')
        ->and($uploadedFile->type)->toBe('pdf')
        ->and($uploadedFile->mime)->toBe('application/pdf')
        ->and($uploadedFile->status)->toBe('active')
        ->and($uploadedFile->disk)->toBe('public')
        ->and($uploadedFile->hash)->not->toBeNull()
        ->and($uploadedFile->size)->toBeGreaterThan(0);
});

it('can associate uploaded file with a model', function () {
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $response = $this->post('/upload', [
        'file' => $file,
        'model' => 'App\Models\User',
        'modid' => 1,
    ]);

    $this->assertDatabaseHas('files', [
        'name' => 'document.pdf',
        'model_type' => 'App\Models\User',
        'model_id' => 1,
    ]);
});

it('dispatches ProcessFile job with correct file ID', function () {
    $file = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $this->post('/upload', [
        'file' => $file,
    ]);

    $uploadedFile = File::first();

    Queue::assertPushed(ProcessFile::class, function ($job) use ($uploadedFile) {
        return $job->file->id === $uploadedFile->id;
    });
});

it('stores file in correct disk location', function () {
    $file = UploadedFile::fake()->create('test.pdf', 1024, 'application/pdf');

    $this->post('/upload', [
        'file' => $file,
    ]);

    $uploadedFile = File::first();

    Storage::disk('public')->assertExists($uploadedFile->getPathToFile());
});

it('generates unique filenames for uploaded files', function () {
    $file1 = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');
    $file2 = UploadedFile::fake()->create('document.pdf', 1024, 'application/pdf');

    $this->post('/upload', ['file' => $file1]);
    $this->post('/upload', ['file' => $file2]);

    $files = File::all();

    expect($files)->toHaveCount(2)
        ->and($files[0]->file)->not->toBe($files[1]->file);
});
