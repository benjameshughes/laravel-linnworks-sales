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
    
    'redirect_uri' => env('LINNWORKS_REDIRECT_URI', 'https://localhost/linnworks/callback'),
    
    'server_id' => env('LINNWORKS_SERVER_ID'),
    
    'token' => env('LINNWORKS_TOKEN'),

    'session_ttl' => env('LINNWORKS_SESSION_TTL', 55),

    'fulfilment_center' => env('LINNWORKS_FULFILMENT_CENTER', '00000000-0000-0000-0000-000000000000'),

    'open_orders' => [
        'view_id' => env('LINNWORKS_OPEN_ORDERS_VIEW_ID', 0),
        'location_id' => env(
            'LINNWORKS_OPEN_ORDERS_LOCATION_ID',
            env('LINNWORKS_FULFILMENT_CENTER', '00000000-0000-0000-0000-000000000000')
        ),
        'entries_per_page' => env('LINNWORKS_OPEN_ORDERS_PAGE_SIZE', 200),
        'auto_detect' => (bool) env('LINNWORKS_OPEN_ORDERS_AUTO_DETECT', true),
    ],

    'defaults' => [
        'location_fallback' => '00000000-0000-0000-0000-000000000000',
        'view_id' => 0,
    ],

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
        'max_open_orders' => env('LINNWORKS_SYNC_MAX_OPEN_ORDERS', 1000),
        'max_processed_orders' => env('LINNWORKS_SYNC_MAX_PROCESSED_ORDERS', 5000),
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
