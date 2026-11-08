<?php

namespace Nfgarching\Files\Livewire;

use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Events\NewFiles;
use Nfgarching\Files\Models\File;

#[Layout('layout::popup')]
class Upload extends Component
{
    use WithFileUploads;

    public array $data = [];

    public string $modelType = 'App\\Models\\User';

    public int $modelId = 0;

    public string $subject = '';

    /** @var array<int, array{id: int, name: string, size: int, file_type: string, file_type_label: string}> */
    public array $uploadedFiles = [];

    #[Validate(['files.*' => ['required', 'file', 'mimes:jpg,pdf,png', 'min:1', 'max:10240']])]
    public array $files = [];

    public function mount(): void
    {
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

    public function storeFiles(): void
    {
        $this->validate();

        foreach ($this->files as $file) {
            $fileType = FileType::fromMimeType($file->getMimeType());
            $fileItem = File::storeUpload($file, ['file_type' => $fileType->value]);
            $this->uploadedFiles[] = [
                'id' => $fileItem->id,
                'name' => $fileItem->name,
                'size' => $fileItem->size,
                'file_type' => $fileType->value,
                'file_type_label' => $fileType->label(),
            ];
        }

        $this->files = [];
    }

    public function dispatchToModel(): void
    {
        if (empty($this->uploadedFiles)) {
            return;
        }

        $fileIds = array_column($this->uploadedFiles, 'id');

        Log::channel('files')->info('#'.implode(', #', $fileIds).' Upload File: '.$this->modelType.' (ID: '.$this->modelId.')');

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
        return view('files::upload');
    }
}
