<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Env Definitions File
    |--------------------------------------------------------------------------
    |
    | This file defines the structure, groups, and metadata for the .env
    | variables managed by this package.
    |
    */
    'definitions_file' => config_path('env-definitions.yaml'), // O .json

    /*
    |--------------------------------------------------------------------------
    | Backup Settings
    |--------------------------------------------------------------------------
    |
    | Configure where .env backups are stored and how long they are kept.
    |
    */
    'backup' => [
        'enabled' => true,
        'path' => storage_path('app/env_backups'),
        'retention_days' => 7, // Backups older than this will be pruned
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    |
    | Configure aspects of the user interface, like which middleware to use
    | for the routes.
    |
    */
    'ui' => [
        'route_prefix' => 'env-manager',
        'middleware' => ['web'], // Or your custom middleware for admin access
    ],
];