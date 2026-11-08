<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DATA Filesystem
    |--------------------------------------------------------------------------
    */

    'data_disk' => env('APP_DATA_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Templates for additional Filesystems
    |--------------------------------------------------------------------------
    */

    'add_disks' => [

        'backup' => [
            'driver' => 's3',
            'key' => env('S3_BACKUP_ACCESS_KEY_ID'),
            'secret' => env('S3_BACKUP_SECRET_ACCESS_KEY'),
            'region' => env('S3_BACKUP_DEFAULT_REGION', 'eu-central-1'),
            'bucket' => env('S3_BACKUP_BUCKET'),
            'url' => env('S3_BACKUP_URL', ''),
            'endpoint' => env('S3_BACKUP_ENDPOINT'),
            'use_path_style_endpoint' => env('S3_BACKUP_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'data' => [
            'driver' => 'local',
            'root' => storage_path('app/data'),
            'visibility' => 'public',
            'throw' => false,
        ],

        'ftp' => [
            'driver' => 'ftp',
            'host' => env('FTP_HOST'),
            'username' => env('FTP_USERNAME'),
            'password' => env('FTP_PASSWORD'),
            'root' => env('FTP_ROOT', ''),
        ],

        'hidrive' => [
            'driver' => 's3',
            'key' => env('S3_ACCESS_KEY_ID'),
            'secret' => env('S3_SECRET_ACCESS_KEY'),
            'region' => env('S3_DEFAULT_REGION', 'eu-central-1'),
            'bucket' => env('S3_BUCKET'),
            'url' => env('S3_URL', ''),
            'endpoint' => env('S3_ENDPOINT'),
            'use_path_style_endpoint' => env('S3_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
        ],

        'privat' => [
            'driver' => 'local',
            'root' => storage_path('app/privat'),
            // 'url' => env('APP_URL') . '/privat',
            'visibility' => 'privat',
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
        ],

        'sftp' => [
            'driver' => 'sftp',
            'host' => env('SFTP_HOST'),
            'username' => env('SFTP_USERNAME'),
            'password' => env('SFTP_PASSWORD'),
            'visibility' => 'private', // `private` = 0600, `public` = 0644
            'directory_visibility' => 'private', // `private` = 0700, `public` = 0755
            'root' => env('SFTP_ROOT', ''),
        ],

        'scans' => [
            'driver' => 'local',
            'root' => storage_path('app/scans'),
            'url' => env('APP_URL').'/scans',
            'visibility' => 'public',
            'throw' => false,
        ],

        'temp' => [
            'driver' => 'local',
            'root' => storage_path('app/temp'),
            'visibility' => 'public',
            'throw' => false,
        ],

    ],

    'add_links' => [
        public_path('scans') => storage_path('app/scans'),
    ],

    /*
    |--------------------------------------------------------------------------
    | NewFiles Event Listener
    |--------------------------------------------------------------------------
    |
    | Bestimmt, welcher Listener das NewFiles-Event verarbeitet:
    |   'documents' → Nfgarching\Documents\Listeners\RecieveNewFiles (neues Document-Modell)
    |   'legacy'    → App\Listeners\RecieveNewFiles (altes Dokument-Modell)
    |
    */

    'new_files_listener' => env('NEW_FILES_LISTENER', 'nolegacy'),

];
