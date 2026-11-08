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

    // # Wird nicht genutzt
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

    /*
    |--------------------------------------------------------------------------
    | Archivierung (PDF/A + Siegel + Zeitstempel)
    |--------------------------------------------------------------------------
    | Jedes hochgeladene PDF erhält nach OCR eine Archivfassung:
    | PDF/A + Optimierung (ocrmypdf) → Validierung (veraPDF) →
    | PAdES-Siegel mit Zeitstempel (pyHanko). Das Original bleibt unverändert.
    | Bereits signierte PDFs werden nicht verändert (skip_signed).
    | Installation der Tools: docs/Archivierung.md
    |--------------------------------------------------------------------------
    */

    'archive' => [
        'enabled' => env('FILES_ARCHIVE_ENABLED', true),
        'queue' => env('FILES_ARCHIVE_QUEUE'),
        'skip_signed' => true,

        'pdfa' => [
            'enabled' => env('FILES_PDFA_ENABLED', true),
            'binary' => env('FILES_OCRMYPDF_BINARY', 'ocrmypdf'),
            'output_type' => env('FILES_PDFA_OUTPUT_TYPE', 'pdfa-2'), // pdfa-1 | pdfa-2 | pdfa-3
            'optimize' => (int) env('FILES_PDFA_OPTIMIZE', 1),        // 0–3; 2/3 benötigen jbig2 + pngquant
            'language' => env('FILES_PDFA_LANGUAGE', 'deu'),
            'timeout' => 600,
        ],

        'validate' => [
            'enabled' => env('FILES_PDFA_VALIDATE', true),
            'binary' => env('FILES_VERAPDF_BINARY', 'verapdf'),
            'flavour' => env('FILES_PDFA_FLAVOUR', '2b'),             // passend zu output_type
            'timeout' => 300,
        ],

        'sign' => [
            'enabled' => env('FILES_SIGN_ENABLED', true),
            'binary' => env('FILES_PYHANKO_BINARY', 'pyhanko'),
            // PKCS#12-Datei; relative Pfade gelten ab storage/
            'certificate' => env('FILES_SIGN_CERT'),
            'password' => env('FILES_SIGN_PASSWORD'),
            // RFC-3161-Zeitstempeldienst, leer = ohne Zeitstempel
            'tsa_url' => env('FILES_TSA_URL'),
            'field_name' => env('FILES_SIGN_FIELD', 'Archivsiegel'),
            'reason' => env('FILES_SIGN_REASON', 'Archivierung'),
            'location' => env('FILES_SIGN_LOCATION'),
            'contact' => env('FILES_SIGN_CONTACT'),
            'pades' => true,
            // Sperrinformationen (OCSP/CRL) einbetten – nur mit öffentlicher PKI sinnvoll
            'with_validation_info' => env('FILES_SIGN_LTV', false),
            'timeout' => 120,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | PDF-Prüfung (Eigenschaften, Signaturen, PDF/A, Prüfsummen)
    |--------------------------------------------------------------------------
    | Ergebnis in custom_properties.inspection, Anzeige im Tab „PDF“ der
    | Datei. Siehe docs/PDF-Pruefung.md.
    |--------------------------------------------------------------------------
    */

    'inspect' => [
        // nach der Archivierung automatisch prüfen
        'enabled' => env('FILES_INSPECT_ENABLED', true),
        // Python mit pyHanko; leer = Interpreter des pyhanko-Kommandos
        'python' => env('FILES_INSPECT_PYTHON'),
        // Vertrauensanker (PEM/DER), kommagetrennt, relativ zu storage/
        'trust' => env('FILES_INSPECT_TRUST', 'app/private/files-signing/test-ca.crt'),
        // Sperrinformationen (OCSP/CRL) aus dem Netz laden
        'fetch' => env('FILES_INSPECT_FETCH', true),
        'text_pages' => 3,
        'timeout' => 120,
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
