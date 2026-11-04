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

        'public' => [
            'driver' => 'local',
            // Uploaded covers, audio and note attachments. Inside the web root so cPanel hosts
            // need no storage symlink; URLs are site-relative. When the site is served from
            // public_html instead of blog/public, set UPLOADS_PATH=/home/USER/public_html/uploads.
            // public/uploads/.htaccess turns off PHP and blocks HTML/SVG in this folder.
            'root' => env('UPLOADS_PATH', public_path('uploads')),
            'url' => '/uploads',
            'visibility' => 'public',
            // Fail loudly: a write that cannot happen (full disk, wrong permissions) must not
            // leave a post pointing at a file that does not exist.
            'throw' => true,
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
    | None: uploads are stored directly under public/uploads (see the "public"
    | disk above), so `php artisan storage:link` is not needed on cPanel.
    |
    */

    'links' => [],

];
