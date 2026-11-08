<?php

namespace Nfgarching\Files\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Nfgarching\Files\Events\NewFiles;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Services\DiskService;

class ImportController
{
    public function __construct(private readonly DiskService $diskService) {}

    /**
     * GET /disk-inbox
     * Renders the popup with the file list from the configured inbox disk/path.
     */
    public function index(Request $request): View
    {
        $disk = $request->filled('disk') ? $request->string('disk')->toString() : config('path.scan_inbox.disk');
        $path = $request->filled('path') ? $request->string('path')->toString() : config('path.scan_inbox.path');
        $sort = $request->query('sort', 'name');
        $direction = in_array($request->query('direction'), ['asc', 'desc']) ? $request->query('direction') : 'asc';
        $perPage = max(5, (int) $request->query('per_page', 10));
        $mimeFilter = $request->query('mime') ?: null;

        $mimeTypes = $this->diskService->getMimeTypes($disk, $path);
        $files = $this->diskService->getFiles($disk, $path, $perPage, 'file_page', $sort, $direction, $mimeFilter);

        return view('disks::inbox', [
            'disk' => $disk,
            'path' => $path,
            'files' => $files,
            'mimeFilter' => $mimeFilter,
            'mimeTypes' => $mimeTypes,
            'model_type' => $request->model_type,
            'model_id' => $request->model_id,
            'request' => $request,
            'sort' => $sort,
            'direction' => $direction,
            'perPage' => $perPage,
        ]);
    }

    /**
     * POST /disk-inbox
     * Imports selected files from the source disk and dispatches the NewFiles event in one step.
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'disk' => ['required', 'string'],
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'string'],
        ]);

        $fileIds = [];
        foreach ($request->input('files') as $sourcePath) {
            $fileItem = File::importFromDisk(
                $request->input('disk'),
                $sourcePath,
                $request->has('own') ? ['own' => $request->boolean('own')] : []
            );
            $fileIds[] = $fileItem->id;
        }

        Log::channel('files')->info(
            '#'.implode(', #', $fileIds).' Inbox Dispatch: '.$request->model_type.' (ID: '.$request->model_id.')'
        );

        $event = new NewFiles(
            $fileIds,
            $request->model_type,
            $request->model_id,
            $request->Betreff,
        );
        event($event);

        return redirect()->route('files.index')->with('success', count($fileIds).' Datei(en) importiert.');
    }
}
