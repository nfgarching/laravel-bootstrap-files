<?php

namespace Nfgarching\Files\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Nfgarching\Files\Enums\FileType;
use Nfgarching\Files\Models\File;

class ProcessFile implements ShouldQueue
{
    use Queueable;

    public ?File $file = null;

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly int $fileID)
    {
        $this->file = File::find($fileID);
        if (! $this->file) {
            Log::channel('files')->error('#'.$fileID.' ProcessFile Job: File not found');
            throw new \Exception('File not found with ID: '.$fileID);
        }
        // dump('ProcessFile Job created for File ID: '.$fileID.' ('.$this->file->file.')');
    }

    /**
     * Execute the job.
     */
    public function handle(): string
    {
        if (config('files.file_processing_queue') == 'noprocess') {
            return 'ProcessFile: Job canceled - file processing queue is set to noprocess';
        }
        Log::channel('files')->info('#'.$this->file->id.' Processing ('.$this->file->file.')');

        // $temp_path = config('path.temp_path');
        // Storage::disk($this->file->disk)->makeDirectory($temp_path);

        // dump('ProcessFile: Starting processing for File ID: '.$this->file->id.' ('.$this->file->file.') of type '.$this->file->type->value);

        return match ($this->file->type) {
            FileType::Pdf => $this->processPdf(),
            FileType::Image => $this->processImage(),
            default => 'ProcessFile: Job canceled - unsupported file type: '.$this->file->type->value,
        };
    }

    private function processPdf(): string
    {
        Log::channel('files')->info('#'.$this->file->id.' ProcessFile: Starting PDF conversion');
        // dump('processPdf called for File ID: '.$this->file->id);

        $pdf_conv_service = config('files.pdf_conversion_service');
        $ocr_conv_service = config('files.ocr_conversion_service');

        if (config('files.file_processing_queue') == 'sync') {
            $pdf_conv_service::dispatchSync($this->file->id);
            $ocr_conv_service::dispatchSync($this->file->id);
        } else {
            $pdf_conv_service::dispatch($this->file->id);
            $ocr_conv_service::dispatch($this->file->id);
        }

        return 'ProcessFile: PDF-Job completed.';
    }

    private function processImage(): string
    {
        Log::channel('files')->info('#'.$this->file->id.' ProcessFile: Starting Image conversion');
        // dump('processImage called for File ID: '.$this->file->id);

        $img_conv_service = config('files.img_conversion_service');

        if (config('files.file_processing_queue') == 'sync') {
            $img_conv_service::dispatchSync($this->file->id);
        } else {
            $img_conv_service::dispatch($this->file->id);
        }

        return 'ProcessFile: Image-Job completed.';
    }
}
