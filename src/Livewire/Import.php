<?php

namespace Nfgarching\Files\Livewire;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Events\NewFiles;
use Nfgarching\Files\Models\File;
use Nfgarching\Files\Services\DiskService;

#[Layout('layout::popup')]
class Import extends Component
{
    use WithPagination;

    public string $disk = '';

    public string $path = '';

    public array $data = [];

    public string $modelType = 'App\\Models\\User';

    public int $modelId = 0;

    public string $subject = '';

    public string $sort = 'lastModified';

    public string $direction = 'desc';

    public int $perPage = 10;

    public bool $selectAll = false;

    public ?string $mimeFilter = null;

    /** @var array<int, string> */
    public array $selectedFiles = [];

    public ?string $renamingFile = null;

    public string $renameName = '';

    protected string $paginationTheme = 'bootstrap';

    protected DiskService $diskService;

    public function boot(DiskService $diskService): void
    {
        $this->diskService = $diskService;
    }

    /** @var array<string, mixed> */
    public array $datenimport = [];

    public function mount(): void
    {
        // $this->modelType = request()->string('model_type', '')->toString();
        // $this->modelId = request()->integer('model_id') ?: null;
        $this->disk = request()->string('disk', config('files.scan_inbox.disk'))->toString();
        $this->path = request()->string('path', config('files.scan_inbox.path'))->toString();
        if (request()->has('model_type')) {
            $this->modelType = request()->string('model_type', '')->toString();
            $this->modelId = request()->integer('model_id') ?: 0;
        } else {
            $this->modelType = config('files.default_model_type');
        }
        $this->subject = request()->string('subject', '')->toString();
        $raw = request()->input('data', []);
        $this->data = is_string($raw) ? (json_decode($raw, true) ?? []) : (array) $raw;
    }

    #[Computed]
    public function files(): LengthAwarePaginator
    {
        return $this->diskService->getFiles(
            $this->disk,
            $this->path,
            $this->perPage,
            'page',
            $this->sort,
            $this->direction,
            $this->mimeFilter,
            $this->getPage(),
        );
    }

    #[Computed]
    public function mimeTypes(): array
    {
        return $this->diskService->getMimeTypes($this->disk, $this->path);
    }

    public function updatedSelectAll(bool $value): void
    {
        $pageItems = $this->files->items();

        if ($value) {
            $this->selectedFiles = array_values(array_unique(
                array_merge($this->selectedFiles, $pageItems)
            ));
        } else {
            $this->selectedFiles = array_values(array_diff($this->selectedFiles, $pageItems));
        }
    }

    public function sortBy(string $field): void
    {
        if ($this->sort === $field) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $field;
            $this->direction = 'asc';
        }

        $this->selectAll = false;
        $this->resetPage();
    }

    public function filterMime(?string $type): void
    {
        $this->mimeFilter = $type;
        $this->selectAll = false;
        $this->resetPage();
    }

    public function setPerPage(int $n): void
    {
        $this->perPage = $n;
        $this->selectAll = false;
        $this->resetPage();
    }

    public function startRename(string $file): void
    {
        $this->renamingFile = $file;
        $this->renameName = basename($file);
    }

    public function cancelRename(): void
    {
        $this->renamingFile = null;
        $this->renameName = '';
    }

    public function renameFile(string $file): void
    {
        $newName = trim($this->renameName);

        if ($newName === '' || $newName === basename($file)) {
            $this->cancelRename();

            return;
        }

        if ($this->diskService->renameFile($this->disk, $file, $newName)) {
            $this->selectedFiles = array_values(array_diff($this->selectedFiles, [$file]));
            $this->dispatch('show-message', message: 'Datei umbenannt');
        } else {
            $this->dispatch('show-message', message: 'Datei konnte nicht umbenannt werden');
        }

        $this->cancelRename();
    }

    public function deleteFile(string $file): void
    {
        if ($this->diskService->deleteFile($this->disk, $file)) {
            $this->selectedFiles = array_values(array_diff($this->selectedFiles, [$file]));
            $this->dispatch('show-message', message: 'Datei gelöscht');
        } else {
            $this->dispatch('show-message', message: 'Datei konnte nicht gelöscht werden');
        }
    }

    public function importFiles(): void
    {
        if (empty($this->selectedFiles)) {
            return;
        }

        $fileIds = [];
        foreach ($this->selectedFiles as $sourcePath) {
            $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            $fileType = FileType::fromExtension($extension);
            $fileItem = File::importFromDisk($this->disk, $sourcePath, ['file_type' => $fileType->value]);
            if ($fileItem) {
                $fileIds[] = $fileItem->id;
            }
        }

        Log::channel('files')->info('#'.implode(', #', $fileIds).' Import File: '.$this->modelType.' (ID: '.$this->modelId.')');

        $event = new NewFiles(
            $fileIds,
            $this->modelType,
            $this->modelId,
            $this->subject,
            $this->data,
        );
        event($event);

        if ($this->modelId) {
            $this->js("window.opener.location.href = '{$event->route}';
                    window.opener.focus();
                    window.close();");
        } else {
            $this->js("window.open('{$event->route}');window.close();");
        }
    }

    public function render(): View
    {
        return view('disks::import');
    }
}
