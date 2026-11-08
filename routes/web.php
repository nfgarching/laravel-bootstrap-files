<?php

namespace Nfgarching\Files\Controllers;

use Illuminate\Support\Facades\Route;
use Nfgarching\Files\Livewire\Import;
use Nfgarching\Files\Livewire\Upload;

Route::middleware(['web', 'auth'])->group(function () {

    Route::get('/files/{id}/action', [FileController::class, 'action'])
        ->name('files.action');

    // PDF-Prüfung (Eigenschaften, Signaturen, PDF/A) neu ausführen
    Route::post('/files/{file}/inspect', [FileController::class, 'inspect'])
        ->name('files.inspect');

    // Sammelaktion aus der Datei-Liste (markierte Zeilen)
    Route::post('/files/action', [FileController::class, 'bulkAction'])
        ->name('files.bulk-action');

    Route::resources([
        'disks' => DiskController::class,
        'files' => FileController::class,
    ]);

    Route::get('/file-import', Import::class)->name('file-import');

    Route::get('/file-upload', Upload::class)->name('file-upload');
    Route::get('/file-upload/create', Upload::class)->name('file-upload.create');
    Route::get('/file-upload/index', Upload::class)->name('file-upload.index');

    Route::get('/download', [DiskController::class, 'loadFile'])->name('download');
    Route::get('/showFile', [DiskController::class, 'showFile'])->name('showFile');

    Route::get('/disk-inbox', [ImportController::class, 'index'])->name('disk-inbox.index');
    Route::post('/disk-inbox', [ImportController::class, 'store'])->name('disk-inbox.store');

    Route::get('/upload', Upload::class)->name('upload');
    Route::get('/upload/create', Upload::class)->name('upload.create');
    Route::get('/upload/index', Upload::class)->name('upload.index');

    // Route::get('/file-import', [ImportController::class, 'index'])->name('file-import.index');
    // Route::post('/file-import', [ImportController::class, 'store'])->name('file-import.store');

});

// Route::get('/files/{id}', [FileController::class, 'show']);
