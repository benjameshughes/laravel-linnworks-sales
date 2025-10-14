# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a **Sales Insight Dashboard** for Linnworks integration built with Laravel 12.x, Livewire, and Flux UI components. The application provides comprehensive sales analytics and reporting capabilities, allowing users to:

- **Overview Dashboard**: Get high-level sales metrics and performance indicators
- **Product Analysis**: Drill down into specific product performance and trends
- **Channel Analytics**: Analyze sales performance across different sales channels
- **Linnworks Integration**: Connect to the Linnworks API in a strictly read-only fashion to pull sales analytics data (no writes)

The project is currently built on a Laravel Livewire starter kit foundation with authentication features. The sales dashboard functionality will be implemented on top of this foundation.

**Tech Stack:**
- Laravel 12.19.3 (PHP 8.2+)
- Livewire + Flux UI components
- Tailwind CSS v4
- SQLite database (development) / MySQL (production)
- Pest testing framework
- Vite for asset compilation
- Linnworks API integration (read-only data ingestion)

## Development Commands

### Quick Start
```bash
cp .env.example .env
php artisan key:generate
php artisan migrate
composer dev  # Starts all development services
```

### Primary Development Command
```bash
composer dev
```
This single command runs all development services in parallel with color-coded output:
- Laravel development server (`php artisan serve`)
- Queue listener (`php artisan queue:listen --tries=1`)
- Log tailing (`php artisan pail --timeout=0`)
- Vite development server (`npm run dev`)

### Individual Commands
```bash
# Laravel
php artisan serve           # Development server
php artisan migrate         # Run migrations
php artisan db:seed         # Seed database
php artisan tinker          # Interactive REPL
php artisan pail            # Tail logs
php artisan queue:listen    # Process queues
php artisan livewire:make   # Create Livewire components
php artisan flux:activate   # Activate Flux UI components

# Frontend
npm run dev                 # Vite development server
npm run build              # Build production assets

# Testing & Code Quality
php artisan test           # Run tests
composer test              # Clear config and run tests
vendor/bin/pest            # Run Pest tests directly
vendor/bin/pint            # Run Laravel Pint (code formatter)
```

## Architecture

### Authentication System
- Complete Livewire-based authentication (register, login, password reset, email verification)
- User settings pages (Profile, Password, Appearance with dark mode)
- Located in `app/Livewire/Auth/` and `app/Livewire/Settings/`

### Livewire Components
- Authentication components in `app/Livewire/Auth/`
- Settings components in `app/Livewire/Settings/`
- Corresponding views in `resources/views/livewire/`

### Flux UI Integration
- Flux UI components are used throughout the application
- Custom Flux components in `resources/views/flux/`
- Flux must be activated with `php artisan flux:activate`

### Database
- SQLite database (default configuration)
- Standard Laravel migrations for users, cache, and jobs tables
- Database file: `database/database.sqlite`

## Testing

- **Framework**: Pest PHP
- **Configuration**: `phpunit.xml` and `tests/Pest.php`
- **Structure**: Feature tests in `tests/Feature/`, Unit tests in `tests/Unit/`
- **Test Database**: In-memory SQLite for fast execution
- **Coverage**: Authentication, Settings, Dashboard functionality

## GitHub Actions CI/CD

### Workflows
- **Lint** (`.github/workflows/lint.yml`): Runs Laravel Pint on develop/main branches
- **Tests** (`.github/workflows/tests.yml`): Full test suite with PHP 8.4 and Node 22

### Requirements
- **Flux License**: Requires `FLUX_USERNAME` and `FLUX_LICENSE_KEY` secrets for Flux UI access
- **Environment**: Uses "Testing" environment in GitHub Actions

## Order Sync Architecture

This application uses a two-job architecture for syncing orders from Linnworks, splitting concerns between recent data updates and historical imports.

### Sync Jobs Overview

**1. SyncRecentOrdersJob** (`app/Jobs/SyncRecentOrdersJob.php`)
- **Purpose**: Keep dashboard fresh with up-to-date order data
- **Frequency**: Every 15 minutes (scheduled) + user-triggered
- **Speed**: < 2 minutes typically
- **Data Scope**: Last 30 days + ALL open orders
- **Behavior**: NO LIMITS - syncs ALL recent data
- **Queue Priority**: `high` (doesn't block dashboard updates)
- **Cache**: ALWAYS warms cache on success
- **PHP 8.2+**: Uses readonly properties, constructor promotion

**Key characteristics:**
```php
// Always syncs ALL open orders (no date filter, no limit)
$openOrderIds = $api->getAllOpenOrderIds();

// Always syncs ALL processed orders from last 30 days (no limit)
$processedOrderIdsStream = $api->streamProcessedOrderIds(
    from: Carbon::now()->subDays(30)->startOfDay(),
    to: Carbon::now()->endOfDay(),
    // NO maxOrders parameter - streams everything
);

// Always updates open/closed status
$this->markMissingOrdersAsClosed($openOrderIds);

// Always warms cache (no conditionals)
if ($success && $totalProcessed > 0) {
    event(new OrdersSynced(...));
}
```

**Triggered by:**
- Dashboard sync button: `app/Livewire/Dashboard/DashboardFilters.php::syncOrders()`
- CLI command: `php artisan sync:orders` (`app/Console/Commands/SyncOpenOrders.php`)
- Scheduled task (if configured in `app/Console/Kernel.php`)

**2. SyncHistoricalOrdersJob** (`app/Jobs/SyncHistoricalOrdersJob.php`)
- **Purpose**: One-time backfill of historical data
- **Frequency**: Manual (triggered from settings page)
- **Speed**: 10-60+ minutes (depends on date range)
- **Data Scope**: User-specified date range
- **Behavior**: Only syncs PROCESSED orders (skips open orders)
- **Queue Priority**: `low` (doesn't block recent syncs)
- **Cache**: Only warms if data affects dashboard (last 730 days)
- **Progress**: Persists state to database every 10 batches for UI display
- **PHP 8.2+**: Uses readonly properties for date range parameters

**Key characteristics:**
```php
public function __construct(
    public readonly Carbon $fromDate,
    public readonly Carbon $toDate,
    public readonly ?string $startedBy = null,
) {
    $this->timeout = 3600; // 1 hour for large imports
    $this->onQueue('low'); // Don't block recent syncs
}

// Only syncs processed orders (no open orders)
$processedOrderIdsStream = $api->streamProcessedOrderIds(
    from: $this->fromDate,
    to: $this->toDate,
    filters: ProcessedOrderFilters::forHistoricalImport()->toArray(),
    // Uses 'processed' date field (when order was fulfilled)
);

// Skips open/closed status updates (not relevant for historical)

// Conditional cache warming
if ($success && $totalProcessed > 0 && $this->affectsDashboardPeriods()) {
    event(new OrdersSynced(...));
}
```

**Triggered by:**
- Settings import page: `app/Livewire/Settings/ImportProgress.php::startImport()`

### Why Two Jobs?

**Previous unified job problems:**
- Complex conditional logic (`if (!$historicalImport)` scattered throughout)
- Artificial 5,000 order limit caused recent data to be truncated
- Single Responsibility Principle violation
- Hard to understand and maintain

**Benefits of split:**
1. **Fixes the bug**: No artificial limits for recent sync
2. **Simpler code**: Each job has single responsibility
3. **Better performance**: Recent sync optimized for speed
4. **Better UX**: Recent sync fast, historical shows progress
5. **Safer operations**: Historical import isolated, can't break daily operations

### Memory Management

Both jobs use streaming with generators to handle large datasets:

**Streaming Pattern:**
```php
// Stream order IDs page by page (memory-efficient)
foreach ($processedOrderIdsStream as $pageOrderIds) {
    // Fetch full details for this batch (200 orders)
    $orders = $api->getOrdersByIds($pageOrderIds->toArray());

    // Import batch
    $result = $importer->import($orders);

    // Free memory
    unset($orders, $result, $pageOrderIds);

    // GC hint every 10 batches
    if ($currentBatch % 10 === 0 && function_exists('gc_collect_cycles')) {
        gc_collect_cycles();
    }
}
```

**Why this works:**
- Memory controlled by **batch size (200)**, not total count
- Generator pattern (`yield from`) streams IDs without loading all into memory
- Explicit memory cleanup with `unset()` and `gc_collect_cycles()`
- No artificial `maxOrders` limit needed

### Retry Logic

Both jobs include exponential backoff for resilient API calls:

```php
// Retry failed batches up to 3 times
$maxRetries = 3;
$baseBackoffSeconds = 5;

while ($attempt < $maxRetries) {
    try {
        // Attempt fetch and import
        $orders = $api->getOrdersByIds($orderIds->toArray());
        $result = $importer->import($orders);
        return; // Success!

    } catch (\App\Exceptions\Linnworks\LinnworksApiException $e) {
        if (!$e->isRetryable()) {
            throw $e; // Don't retry auth failures
        }

        // Exponential backoff: 5s, 10s, 20s
        $backoffSeconds = $baseBackoffSeconds * (2 ** ($attempt - 1));

        // Special handling for rate limits
        if ($e->isRateLimited() && $e->getRetryAfter()) {
            $backoffSeconds = $e->getRetryAfter();
        }

        sleep($backoffSeconds);
    }
}
```

### Progress Tracking

**Recent Sync:**
- Uses `SyncLog` with type `TYPE_OPEN_ORDERS`
- Broadcasts real-time events for UI updates
- Updates SyncLog on completion

**Historical Import:**
- Uses `SyncLog` with type `TYPE_HISTORICAL_ORDERS`
- Persists progress to database every 10 batches
- UI can refresh page and see live progress
- ImportProgress component reads from SyncLog

**SyncLog Usage:**
```php
// Start sync
$syncLog = SyncLog::startSync(SyncLog::TYPE_HISTORICAL_ORDERS, [
    'started_by' => 'user-123',
    'date_range' => [
        'from' => '2024-01-01',
        'to' => '2024-12-31',
    ],
]);

// Update progress (every 10 batches for historical)
$syncLog->updateProgress('importing', $currentBatch, 0, [
    'total_processed' => $totalProcessed,
    'created' => $totalCreated,
    'updated' => $totalUpdated,
    'failed' => $totalFailed,
    'current_batch' => $currentBatch,
    'message' => "Processed {$totalProcessed} orders",
]);

// Complete
$syncLog->complete(
    fetched: $totalOrdersFetched,
    created: $totalCreated,
    updated: $totalUpdated,
    skipped: $totalSkipped,
    failed: $totalFailed
);
```

### Date Field Strategy

**Recent Sync** uses `received` date:
- Date when customer placed the order
- Captures orders placed in last 30 days
- Correct for sales metrics and revenue tracking

**Historical Import** uses `processed` date:
- Date when order was fulfilled
- Better for backfilling by fulfillment date
- Useful for operational metrics

### Important Notes

**Orders Updated After 30 Days:**
- If an order from 60 days ago gets updated today, it won't be synced by recent sync
- Use historical import to refresh old data if needed
- Consider adding "sync specific order" feature later if needed

**No Artificial Limits:**
- Both jobs removed all `maxOrders` parameters
- Memory controlled by batch size (200), not total count
- If you have 10,000 orders in 30 days, you NEED all 10,000
- Recent sync will handle any volume within 30-day window

**Queue Worker Caching:**
- When updating job code, restart queue workers: `php artisan queue:restart`
- `composer dev` uses `queue:listen` which auto-reloads on changes
- Manual `queue:work` requires restart to pick up code changes

### Key Files

**Jobs:**
- `app/Jobs/SyncRecentOrdersJob.php` - Recent data sync (30 days + open orders)
- `app/Jobs/SyncHistoricalOrdersJob.php` - Historical import (custom date range)

**Services:**
- `app/Services/LinnworksApiService.php` - Public API facade
- `app/Services/Linnworks/Orders/ProcessedOrdersService.php` - Order ID streaming
- `app/Actions/Sync/Orders/ImportInBulk.php` - Bulk order import

**UI Components:**
- `app/Livewire/Dashboard/DashboardFilters.php` - Dashboard sync button
- `app/Livewire/Settings/ImportProgress.php` - Historical import UI

**Commands:**
- `app/Console/Commands/SyncOpenOrders.php` - CLI sync trigger

## Development Notes

### Flux UI Credentials
This project uses Flux UI components which require authentication. The credentials are configured in CI/CD via secrets, but for local development, you may need to set up Flux authentication.

## Code Style & Best Practices

### Laravel & PHP Standards
Follow Laravel and PHP best practices when working with this codebase:

**File Organization:**
- Use Laravel's standard directory structure
- Place models in `app/Models/`
- Place Livewire components in `app/Livewire/`
- Use singular names for models (e.g., `User`, `Product`)
- Use plural names for database tables (e.g., `users`, `products`)

**Naming Conventions:**
- Classes: `PascalCase` (e.g., `ProductController`, `OrderModel`)
- Methods/variables: `camelCase` (e.g., `getUserName()`, `$productPrice`)
- Constants: `SCREAMING_SNAKE_CASE` (e.g., `MAX_RETRY_ATTEMPTS`)
- Database columns: `snake_case` (e.g., `created_at`, `user_id`)
- Routes: `kebab-case` (e.g., `/user-profile`, `/order-history`)

**Laravel Conventions:**
- Use Eloquent relationships instead of manual joins
- Leverage Laravel's built-in features (validation, middleware, etc.)
- Use resource controllers for CRUD operations
- Implement proper request validation using Form Requests
- Use Laravel's dependency injection container
- Follow the repository pattern for complex data access

**Livewire Best Practices:**
- Keep component logic focused and single-purpose
- Use Livewire's lifecycle hooks appropriately
- Implement proper validation in Livewire components
- Use Livewire's event system for component communication
- Leverage Livewire's wire:model for two-way data binding

**Code Quality:**
- Write descriptive method and variable names
- Use type hints for method parameters and return types
- Implement proper error handling and logging
- Write comprehensive tests for all functionality
- Use Laravel's built-in validation rules
- Implement proper database transactions for complex operations

**Formatting:**
- Laravel Pint is used for PHP code formatting (runs automatically in CI)
- Run `vendor/bin/pint` before committing changes
- No specific JavaScript/CSS linting configured

## Sales Dashboard Implementation

### Core Features to Implement

**Dashboard Overview:**
- Total sales metrics (revenue, order count, average order value)
- Sales trends and performance charts
- Top performing products and channels
- Recent sales activity feed

**Product Analysis:**
- Individual product performance metrics
- Product sales trends over time
- Stock level indicators (from Linnworks)
- Product profitability analysis
- Category-based performance comparisons

**Channel Analytics:**
- Sales performance by channel (Amazon, eBay, website, etc.)
- Channel-specific metrics and trends
- Channel profitability analysis
- Cross-channel product performance

**Linnworks Integration:**
- API authentication and connection management
- Real-time data synchronization
- Order import and processing
- Product catalog synchronization
- Inventory level monitoring

### Data Models to Create
- `Sale` - Individual sales transactions
- `Product` - Product catalog from Linnworks
- `Channel` - Sales channels (Amazon, eBay, etc.)
- `LinnworksConnection` - API connection configuration
- `SalesMetric` - Calculated metrics and KPIs
- `SyncLog` - Track data synchronization activities

### Livewire Components to Build
- `SalesDashboard` - Main dashboard overview
- `ProductAnalytics` - Product performance drill-down
- `ChannelAnalytics` - Channel performance analysis
- `LinnworksSettings` - API connection management
- `SalesChart` - Reusable chart components
- `MetricsCard` - Dashboard metric display cards

### Key Files to Understand
- `composer.json`: Contains the powerful `composer dev` script
- `routes/web.php`: Main application routes
- `app/Livewire/`: All Livewire components
- `resources/views/components/layouts/app.blade.php`: Main layout
- `resources/views/livewire/`: Livewire component views

## Memories

- `flux:option in a flux:select should be flux:select.option`
- Do not use `flux:card`
- Don't use `flux:table`, check for a reusable blade component for a table and use that
- Using zinc for the colour of dark mode
- Flux buttons have an icon prop. Use that instead of adding icons in tag
- Flux buttons have an icon prop, use that
- Don't use try catches. Use exceptions. Laravel's built-in exception handler is amazing
- When coding follow senior-level laravel and php standards
