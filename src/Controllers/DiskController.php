<?php

namespace Nfgarching\Files\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Nfgarching\Files\Services\DiskService;

class DiskController
{
    public function __construct(private readonly DiskService $diskService) {}

    public function index(): View
    {
        return view('disks::index', [
            'disks' => config('filesystems.disks'),
        ]);
    }

    public function show(Request $request, string $disk): View
    {
        $startTime = microtime(true);
        $path = $request->query('path');
        $sort = $request->query('sort', 'name');
        $direction = in_array($request->query('direction'), ['asc', 'desc']) ? $request->query('direction') : 'asc';
        $perPage = max(5, (int) $request->query('per_page', 10));
        $mimeFilter = $request->query('mime') ?: null;

        $root = $path ? Storage::disk($disk)->path($path) : '';
        $updir = $this->diskService->getUpDirectory($path);
        $dirs = $this->diskService->getDirectories($disk, $path);
        $mimeTypes = $this->diskService->getMimeTypes($disk, $path);
        $files = $this->diskService->getFiles($disk, $path, $perPage, 'file_page', $sort, $direction, $mimeFilter);

        return view('disks::show', [
            'dirs' => $dirs,
            'disk' => $disk,
            'files' => $files,
            'mimeFilter' => $mimeFilter,
            'mimeTypes' => $mimeTypes,
            'root' => $root,
            'updir' => $updir,
            'request' => $request,
            'startTime' => $startTime,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {

        switch ($request->switchTo) {

            case 'deleteCheck':
                dd($request->all(), $request->collect('checks'));
                foreach ($request->collect('checks') as $file) {
                    $this->diskService->deleteFile($request->string('disk')->toString(), $file);
                }

                return back()->withInput()->with('success', 'Dateien gelöscht.');

            case 'importCheck':

                dd('importCheck', $request->collect('checks'));
                // dump(get_class() . ' -> ' . __FUNCTION__);
                // dump('importCheck', $request->all());

                $fileIDs = [];
                foreach ($request->collect('checks') as $file) {
                    $item = File::moveDiskFile($request->disk, $file); // dump($item);
                    $prop = (new Ocr)->run($item); // dump($prop);
                    $fileIDs[] = $item->id;
                }
                NewFiles::dispatch($fileIDs);

                dump('DONE storeFile');

                echo '<br><hr><br>';
                echo '<a href='.route('disks.show', $request->disk).'>Zurück zur Disk: '.$request->disk.'</a>';
                echo '<br>';
                echo '<a href='.route('disks.index').'>Zurück zu Storage</a>';
                echo '<br>';
                echo '<a href='.route('startpage').'>Zurück zu HOME</a>';
                dd('---');

                return redirect()->route('files.index');

            case 'linkToCheck':
                dd($request->all(), $request->collect('checks'));
                dump(get_class().' -> '.__FUNCTION__);
                dump('linkToCheck', $request->all());

                $fileIDs = [];
                foreach ($request->collect('checks') as $file) {
                    $item = File::link2DiskFile($request->disk, $file);
                    dump($item);
                    $prop = (new Ocr)->save_disk(false)->run($item);
                    dump($prop);
                    $fileIDs[] = $item->id;

                    // $prop = Filehandler::getOcrText($item);
                    // dump($prop);
                    // if (!$prop) {
                    //     return redirect()->route('files.show', $item);
                    // }

                }
                // dd('STOP');
                $result = NewFiles::dispatch($fileIDs);

                exit();

                return redirect()->route('files.index');

            case 'renameFile':
                dd($request->all(), $request->collect('checks'));
                $this->diskService->renameFile(
                    $request->string('disk')->toString(),
                    $request->string('file')->toString(),
                    $request->string('newName')->toString(),
                );

                return back()->withInput()->with('success', 'Datei umbenannt.');

            case 'testingCheck':
                dump(get_class().' -> '.__FUNCTION__);
                dd('testingCheck', $request->all());
                foreach ($request->collect('checks') as $file) {

                    // dump(Storage::disk($request->disk)->makeDirectory('testing/'));
                    // dump(Storage::disk($request->disk)->path($file));
                    // dump(Storage::disk($request->disk)->put('Testing_123.txt','Dies ist ein Test.'));

                    // dump(Storage::disk($request->disk)->copy('Testing_123.txt','Testing_456.txt'));
                    dump(Storage::disk($request->disk)->copy($file, 'testing/'.$file));
                    // dump(Storage::disk($item->disk)->copy($item->file, 'copy/' . $item->file));

                    // dump(Storage::disk($request->disk)->delete($file));

                }

                // dd('STOP');
                return back()->withInput();

            default:
                throw new \InvalidArgumentException("switchTo nicht implementiert: {$request->switchTo}");
        }
    }

    public function loadFile(Request $request): mixed
    {
        $disk = $request->string('disk')->toString();
        $file = $request->string('file')->toString();
        $name = $request->string('name', basename($file))->toString();

        // Ohne Disk/Pfad würde missing('') die Disk-Wurzel (ein Verzeichnis) finden
        if ($disk === '' || trim($file, '/') === '' || Storage::disk($disk)->missing($file)) {
            abort(404, "Datei nicht gefunden: {$disk} / {$file}");
        }

        $mime = Storage::disk($disk)->mimeType($file);
        $headers = [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.$name.'"',
            'Link' => '</favicon/faviconGreen.ico>; rel="icon"',
        ];

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            return response()->file(Storage::disk($disk)->path($file), $headers);
        }

        return Storage::disk($disk)->download($file, $name, $headers);
    }

    public function showFile(Request $request): View
    {
        dd($request->all());

        $disk = $request->string('disk')->toString();
        $file = $request->string('file')->toString();

        return view('disks::file', [
            'disk' => $disk,
            'file' => $file,
        ]);
    }

    public function streamFile(Request $request): \Illuminate\Http\Response
    {
        dd($request->all());

        $disk = $request->string('disk')->toString();
        $file = $request->string('file')->toString();

        $path = Storage::disk($disk)->path($file);
        $mime = mime_content_type($path);
        $name = basename($file);
        $size = Storage::disk($disk)->size($file);

        $headers = [
            'Content-Disposition' => 'filename="'.$name.'"',
            'Content-Type' => $mime,
            'Content-Length' => $size,
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];

        return response()->file($path, $headers);
    }

    public function getVideo(Request $request): \Illuminate\Http\Response
    {
        dd($request->all());

        $disk = $request->string('disk')->toString();
        $file = $request->string('file')->toString();

        $mime = mime_content_type(Storage::disk($disk)->path($file));
        $name = basename($file);
        $video = Storage::disk($disk)->get($file);

        return Response::make($video, 200)
            ->header('Content-Type', $mime)
            ->header('Content-Disposition', 'filename="'.$name.'"');
    }
}
