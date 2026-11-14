<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tenancy Mode
    |--------------------------------------------------------------------------
    |
    | "single": one institute. Everyone works in it automatically; the school
    |           switcher, school creation and platform-level settings screens
    |           are hidden.
    | "multi":  several schools or branches, each isolated, with platform
    |           administrators switching between them.
    |
    | The data model is multi-tenant in both modes, so moving from single to
    | multi later only requires changing this value.
    |
    */

    'mode' => env('TENANCY_MODE', 'single'),

    /*
    | Used to create the institute on first use in single mode.
    */
    'default_institute' => [
        'name' => env('INSTITUTE_NAME', env('APP_NAME', 'My Institute')),
        'code' => env('INSTITUTE_CODE', 'MAIN'),
    ],

];
