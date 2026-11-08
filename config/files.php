<?php

use Nfgarching\Files\Services\ConversionImg;
use Nfgarching\Files\Services\ConversionOcr;
use Nfgarching\Files\Services\ConversionPdf;

use Nfgarching\Files\Services\PathGenerator;

// use Illuminate\Support\Facades\DB;
// $standort = DB::select('SELECT `id`, `bezeichnung`  FROM `edv`.`items`'); // dd( $columns );
// dd( $standort );

return [

    /*
    |--------------------------------------------------------------------------
    | Conversions
    |--------------------------------------------------------------------------
    */

    'img_conversion_service' => ConversionImg::class,
    'ocr_conversion_service' => ConversionOcr::class,
    'pdf_conversion_service' => ConversionPdf::class,

    /*
    |--------------------------------------------------------------------------
    | Default Model Type
    |--------------------------------------------------------------------------
    | default: App\Models\User
    | if you want to use files for another model, set the default model type here
    | and make sure to implement the HasFiles trait in that model
    |--------------------------------------------------------------------------
     */

    'default_model_type' => env('FILE_DEFAULT_MODEL_TYPE', 'App\Models\User'),


    /*
    |--------------------------------------------------------------------------
    | File Directory Prefix, Suffix and Postfix
    |--------------------------------------------------------------------------
    */

    'file_dir_prefix' => env('FILE_DIR_PREFIX', ''),
    'file_dir_suffix' => env('FILE_DIR_SUFFIX', ''),
    'file_dir_postfix' => env('FILE_DIR_POSTFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | File Processing Queue
    | ---
    | default: uses the default queue defined in config/queue.php (recommended for production)
    | noprocess: disables file processing (for testing or if you want to handle it manually)
    | sync: processes files immediately in the same request (not recommended for large files or production)
    |--------------------------------------------------------------------------
    */ 

    'file_processing_queue' => env('FILE_PROCESSING_QUEUE', 'default'),

    ## Wird nicht genutzt
    'file_processing_conversion_queue' => env('FILE_PROCESSING_CONVERSION_QUEUE', config('files.file_processing_queue')),
    'file_processing_ocr_queue' => env('FILE_PROCESSING_OCR_QUEUE', config('files.file_processing_queue')),

    /*
    |--------------------------------------------------------------------------
    | Path Generator
    |--------------------------------------------------------------------------
    */

    'path_generator' => PathGenerator::class,

    /*
    |--------------------------------------------------------------------------
    | Save Disk
    |--------------------------------------------------------------------------
    */

    'save_disk' => config('filesystems.default'),

    /*
    |--------------------------------------------------------------------------
    | Scan Inbox
    |--------------------------------------------------------------------------
    */

    'scan_inbox' => [
        'disk' => env('SCAN_INBOX_DISK', 'public'),
        'path' => env('SCAN_INBOX_PATH', '/'),
    ],
    
    'X_status' => [
        'active' => [
            'txt' => 'Aktiv',
            'btn' => 'primary',
            'col' => 'danger',
            'ico' => 'file',
        ],
        'import' => [
            'txt' => 'Import',
            'btn' => 'primary ',
            'col' => 'danger',
            'ico' => 'file',
        ],
        'inactive' => [
            'txt' => 'Inaktiv',
            'btn' => 'primary',
            'col' => 'danger',
            'ico' => 'file',
        ],
        'unlink' => [
            'txt' => 'No File',
            'btn' => 'primary',
            'col' => 'danger',
            'ico' => 'file',
        ],
    ],

    // $value = config('files.mimeType.'.$type.'.ico', 'question');
    'X_type' => [
        'application/pdf' => [
            'txt' => 'PDF',
            'btn' => 'success',
            'col' => 'primary',
            'ico' => 'file-pdf',
        ],
        'PDF' => [
            'txt' => 'PDF',
            'btn' => 'success',
            'col' => 'primary',
            'ico' => 'file-pdf',
        ],
        'pdf' => [
            'txt' => 'PDF',
            'btn' => 'success',
            'col' => 'primary',
            'ico' => 'file-pdf',
        ],
        'png' => [
            'txt' => 'PNG',
            'btn' => 'success',
            'col' => 'primary',
            'ico' => 'image',
        ],
        'jpg' => [
            'txt' => 'JPG',
            'btn' => 'success',
            'col' => 'primary',
            'ico' => 'image',
        ],
        'html' => [
            'txt' => 'HTML',
            'btn' => 'primary',
            'col' => 'primary',
            'ico' => 'card-text',
        ],
        '...' => [
            'txt' => 'Neu',
            'btn' => 'primary',
            'col' => 'danger',
            'ico' => 'file',
        ],
    ],

    'X_typ' => [
        '1' => [
            'txt' => '...',
            'btn' => 'primary',
            'col' => 'primary',
            'ico' => 'question',
        ],
    ],

];
