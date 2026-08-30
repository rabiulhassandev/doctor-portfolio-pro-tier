<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        /*
         | Everything the doctor uploads through the admin panel — the portrait,
         | blog covers, gallery photos, video thumbnails, self-hosted videos.
         |
         | The URL is deliberately root-relative ('/storage') rather than built
         | from APP_URL. A buyer who forgets to update APP_URL after moving to
         | their real domain would otherwise get every image pointing back at
         | localhost, and images that 404 only in production are a miserable
         | thing to debug. Root-relative always resolves against whatever host
         | actually served the page. App\Support\Media::absoluteUrl() adds the
         | scheme and host for the handful of places that genuinely need it
         | (og:image and the schema.org blocks).
         |
         | Requires `php artisan storage:link` once per install.
         */
        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => '/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         | Prescriptions, lab reports and invoices.
         |
         | These are patient health records. They live OUTSIDE public/, are not
         | reachable through the storage symlink, and have no URL at all. The
         | only way to one is App\Http\Controllers\MedicalDocumentController,
         | which authorises every single request.
         |
         | 'serve' => false is load-bearing, not cosmetic. Laravel auto-registers
         | a /storage/{path} route for local disks that have it enabled, which
         | would hand out these files to anyone who guessed a path and defeat
         | the entire point of the separate disk.
         */
        'medical' => [
            'driver' => 'local',
            'root' => storage_path('app/medical'),
            'serve' => false,
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
