<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Storage disk
    |--------------------------------------------------------------------------
    |
    | Lesson materials and assignment files are kept on a private disk and only
    | ever served through short-lived signed links after an access check. Use
    | "local" (storage/app/private) or an S3-compatible disk for large video
    | libraries. Never point this at the public disk.
    |
    */

    'disk' => env('LMS_DISK', 'local'),

    /*
    | How long a signed file link stays valid, in minutes.
    */

    'signed_url_minutes' => (int) env('LMS_SIGNED_URL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Allowed uploads
    |--------------------------------------------------------------------------
    |
    | Extension => material kind. Both the extension and the detected MIME type
    | are validated. Kinds decide how a file is shown: pdf in the viewer, audio
    | and video in players, images inline, everything else as a download.
    |
    */

    'extensions' => [
        'pdf' => 'pdf',
        'doc' => 'document',
        'docx' => 'document',
        'odt' => 'document',
        'txt' => 'document',
        'ppt' => 'presentation',
        'pptx' => 'presentation',
        'odp' => 'presentation',
        'xls' => 'spreadsheet',
        'xlsx' => 'spreadsheet',
        'csv' => 'spreadsheet',
        'mp3' => 'audio',
        'm4a' => 'audio',
        'wav' => 'audio',
        'ogg' => 'audio',
        'mp4' => 'video',
        'webm' => 'video',
        'mov' => 'video',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'gif' => 'image',
        'webp' => 'image',
    ],

    /*
    | Students may upload these with assignment submissions.
    */

    'submission_extensions' => ['pdf', 'doc', 'docx', 'odt', 'txt', 'ppt', 'pptx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'mp3', 'm4a', 'mp4'],

    'submission_max_mb' => (int) env('LMS_SUBMISSION_MAX_MB', 25),

];
