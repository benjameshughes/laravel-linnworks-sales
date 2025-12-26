<?php

use App\Enums\Period;

return [
    /*
    |--------------------------------------------------------------------------
    | Default Period
    |--------------------------------------------------------------------------
    |
    | The default time period to display when loading the dashboard.
    | Add/remove periods by editing App\Enums\Period enum.
    |
    */
    'default_period' => Period::SEVEN_DAYS,

    /*
    |--------------------------------------------------------------------------
    | Cache Warming Strategy Threshold
    |--------------------------------------------------------------------------
    |
    | Periods with this many days or more will use database aggregation
    | for cache warming instead of loading all orders into memory.
    |
    | Recommended: 365 for typical datasets (50K-200K orders)
    | If you have fewer orders: Can increase to 730
    | If you have millions of orders: Decrease to 180 or lower
    |
    | With 253K orders, using 30 days for ultra-safe memory efficiency
    |
    */
    'chunked_calculation_threshold' => 0,

    /*
    |--------------------------------------------------------------------------
    | Custom Range Cache TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | When users select a custom date range, the calculated metrics are
    | cached for this many minutes to avoid repeated DB hits.
    |
    | Dashboard custom ranges: 10 minutes
    | Product custom ranges: 5 minutes
    |
    */
    'custom_range_cache_ttl' => 10,
    'product_custom_range_cache_ttl' => 5,
];
