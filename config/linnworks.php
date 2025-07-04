<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Linnworks API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the Linnworks API.
    | You'll need to obtain these credentials from your Linnworks account.
    |
    */

    'base_url' => env('LINNWORKS_BASE_URL', 'https://api.linnworks.net'),
    
    'application_id' => env('LINNWORKS_APPLICATION_ID'),
    
    'application_secret' => env('LINNWORKS_APPLICATION_SECRET'),
    
    'token' => env('LINNWORKS_TOKEN'),
    
    /*
    |--------------------------------------------------------------------------
    | Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for data synchronization behavior.
    |
    */
    
    'sync' => [
        'batch_size' => env('LINNWORKS_SYNC_BATCH_SIZE', 100),
        'delay_between_requests' => env('LINNWORKS_SYNC_DELAY', 100), // milliseconds
        'max_retries' => env('LINNWORKS_SYNC_MAX_RETRIES', 3),
        'default_date_range' => env('LINNWORKS_SYNC_DEFAULT_DAYS', 30), // days
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Data Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for how data is stored locally.
    |
    */
    
    'storage' => [
        'store_raw_data' => env('LINNWORKS_STORE_RAW_DATA', true),
        'cleanup_old_data' => env('LINNWORKS_CLEANUP_OLD_DATA', false),
        'cleanup_after_days' => env('LINNWORKS_CLEANUP_AFTER_DAYS', 365),
    ],
];