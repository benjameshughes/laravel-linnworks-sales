<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cacheable Periods
    |--------------------------------------------------------------------------
    |
    | These are the time periods that will be pre-warmed in the cache.
    | Any period not in this list will require live calculation.
    |
    */
    'cacheable_periods' => [
        '1',         // Last 24 hours
        'yesterday', // Yesterday
        '7',         // Last 7 days
        '30',        // Last 30 days
        '90',        // Last 90 days
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Period
    |--------------------------------------------------------------------------
    |
    | The default time period to display when loading the dashboard.
    |
    */
    'default_period' => '7',
];
