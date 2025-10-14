<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Linnworks API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for connecting to the Linnworks API.
    |
    | NOTE: Credentials (application_id, application_secret, access_token) are
    | stored encrypted in the database via the LinnworksConnection model.
    | This config file only contains non-sensitive application settings.
    |
    */

    'base_url' => env('LINNWORKS_BASE_URL', 'https://api.linnworks.net'),

    // Application ID for UI purposes only (installation URL generation)
    // Actual credentials are stored encrypted in the database
    'application_id' => env('LINNWORKS_APPLICATION_ID'),

    'session_ttl' => env('LINNWORKS_SESSION_TTL', 55),

    'fulfilment_center' => env('LINNWORKS_FULFILMENT_CENTER', '00000000-0000-0000-0000-000000000000'),

    'open_orders' => [
        // ViewId 4 is the standard "All Open Orders" view in Linnworks
        'view_id' => (int) env('LINNWORKS_OPEN_ORDERS_VIEW_ID', 4),
        // Default location (all-zeros UUID is the Linnworks default location)
        'location_id' => env('LINNWORKS_OPEN_ORDERS_LOCATION_ID', '00000000-0000-0000-0000-000000000000'),
        'entries_per_page' => (int) env('LINNWORKS_OPEN_ORDERS_PAGE_SIZE', 200),
    ],

    'defaults' => [
        'location_fallback' => '00000000-0000-0000-0000-000000000000',
        'view_id' => 4,
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
