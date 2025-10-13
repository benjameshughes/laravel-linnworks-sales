# Historical Data Fetching System Refactor

**Project:** Sales Insight Dashboard - Linnworks Integration
**Goal:** Modernize historical order import system with PHP 8.2+ features and Laravel best practices
**Status:** Planning Phase
**Created:** 2025-10-12

---

## 🎯 Objectives

This refactor aims to improve the historical data fetching system by applying:

1. **PHP 8.2+ Features** - Enums, readonly properties, typed arrays, union types
2. **Laravel Best Practices** - Service containers, facades, query scopes, validation
3. **Livewire 3 Patterns** - Component composition, Alpine.js state management
4. **Action Pattern** - Single responsibility, invokable classes, type-safe contracts
5. **Separation of Concerns** - Domain logic, presentation, data access layers
6. **Senior-Level Code Quality** - Type safety, testability, documentation, observability

---

## 📊 Current Architecture

### Key Components
- **ProcessedOrdersService** - API communication layer
- **LinnworksApiService** - Facade for all Linnworks operations (686 lines)
- **SyncOrdersJob** - Queue job for unified order sync
- **StreamingOrderImporter** - Bulk order import with DB facade
- **ImportProgress** - Livewire component for UI progress tracking

### Current Strengths ✅
- Excellent performance (300 orders/sec, 18× improvement)
- Streaming import pattern with bulk operations
- DTO pattern (OrderImportDTO, LinnworksOrder)
- Real-time broadcasting with Livewire events
- Session management with caching

---

## 🚀 Refactoring Plan

### Phase 1: Quick Wins (1-2 days)

#### 1.1 Add PHP 8.1+ Enums

**Before:**
```php
// ProcessedOrdersService.php
$dateField = $filters['dateField'] ?? 'received';
```

**After:**
```php
enum ProcessedOrderDateField: string
{
    case RECEIVED = 'received';
    case PROCESSED = 'processed';
    case PAYMENT = 'payment';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match($this) {
            self::RECEIVED => 'Received Date',
            self::PROCESSED => 'Processed Date',
            self::PAYMENT => 'Payment Date',
            self::CANCELLED => 'Cancelled Date',
        };
    }
}

// Usage in service
public function searchProcessedOrders(
    int $userId,
    Carbon $from,
    Carbon $to,
    ProcessedOrderDateField $dateField = ProcessedOrderDateField::RECEIVED,
    int $page = 1,
    int $entriesPerPage = 200
): ApiResponse
```

**Benefits:**
- Type safety - compiler prevents invalid values
- IDE autocomplete
- Self-documenting code
- Refactoring safety

**Files to Create:**
- `app/Enums/Linnworks/ProcessedOrderDateField.php`
- `app/Enums/Linnworks/OrderStatus.php` (future)

---

#### 1.2 Extract Response Parser

**Before:**
```php
// Mixed in ProcessedOrdersService
$data = $response->getData();
$processedOrders = $data->get('ProcessedOrders', []);
$orders = collect($processedOrders['Data'] ?? []);
$totalResults = $processedOrders['TotalEntries'] ?? 0;
```

**After:**
```php
// New: app/Services/Linnworks/Orders/ProcessedOrdersResponseParser.php
final readonly class ProcessedOrdersResponseParser
{
    public function parse(ApiResponse $response): ProcessedOrdersPage
    {
        $data = $response->getData();
        $processedOrders = $data->get('ProcessedOrders', []);

        return new ProcessedOrdersPage(
            orders: collect($processedOrders['Data'] ?? []),
            totalResults: $processedOrders['TotalEntries'] ?? 0,
            totalPages: $processedOrders['TotalPages'] ?? 0,
            currentPage: $processedOrders['PageNumber'] ?? 1,
            entriesPerPage: $processedOrders['EntriesPerPage'] ?? 200,
        );
    }
}

// New: app/DataTransferObjects/Linnworks/ProcessedOrdersPage.php
final readonly class ProcessedOrdersPage
{
    public function __construct(
        public Collection $orders,
        public int $totalResults,
        public int $totalPages,
        public int $currentPage,
        public int $entriesPerPage,
    ) {}

    public function hasMorePages(): bool
    {
        return $this->currentPage < $this->totalPages;
    }

    public function percentComplete(): float
    {
        if ($this->totalPages === 0) return 100.0;
        return round(($this->currentPage / $this->totalPages) * 100, 2);
    }
}

// Usage in service
$page = $this->parser->parse($response);
if ($page->hasMorePages()) {
    // fetch next page
}
```

**Benefits:**
- Single responsibility - parsing separate from API calls
- Easier to test
- Type-safe response handling
- Reusable across services

---

#### 1.3 Create Filters DTO

**Before:**
```php
public function getAllProcessedOrders(
    int $userId,
    Carbon $from,
    Carbon $to,
    array $filters = [], // ❌ Magic array
    int $maxOrders = 10000,
```

**After:**
```php
// New: app/DataTransferObjects/Linnworks/ProcessedOrderFilters.php
final readonly class ProcessedOrderFilters
{
    public function __construct(
        public ProcessedOrderDateField $dateField = ProcessedOrderDateField::RECEIVED,
        public ?string $channel = null,
        public ?string $searchTerm = null,
        public ?int $minValue = null,
        public ?int $maxValue = null,
    ) {
        // Validation
        if ($this->minValue !== null && $this->maxValue !== null) {
            throw_if(
                $this->minValue > $this->maxValue,
                InvalidArgumentException::class,
                'Min value must be less than max value'
            );
        }
    }

    public function toArray(): array
    {
        return array_filter([
            'dateField' => $this->dateField->value,
            'channel' => $this->channel,
            'searchTerm' => $this->searchTerm,
            'minValue' => $this->minValue,
            'maxValue' => $this->maxValue,
        ], fn($value) => $value !== null);
    }

    public static function forChannel(string $channel): self
    {
        return new self(channel: $channel);
    }

    public static function byProcessedDate(): self
    {
        return new self(dateField: ProcessedOrderDateField::PROCESSED);
    }
}

// Usage
public function getAllProcessedOrders(
    int $userId,
    Carbon $from,
    Carbon $to,
    ProcessedOrderFilters $filters = new ProcessedOrderFilters(),
    int $maxOrders = 10000,
): Collection
```

**Benefits:**
- Type safety - no more guessing filter keys
- Built-in validation
- Named constructors for common patterns
- IDE autocomplete

---

#### 1.4 Add Query Scopes

**Before:**
```php
// Scattered throughout code
$orders = Order::where('received_date', '>=', Carbon::now()->subDays(90))
    ->whereNotNull('linnworks_order_id')
    ->where('is_processed', true)
    ->get();
```

**After:**
```php
// In app/Models/Order.php
public function scopeRecent(Builder $query, int $days = 90): Builder
{
    return $query->where('received_date', '>=', Carbon::now()->subDays($days));
}

public function scopeWithLinnworksId(Builder $query): Builder
{
    return $query->whereNotNull('linnworks_order_id');
}

public function scopeProcessed(Builder $query): Builder
{
    return $query->where('is_processed', true);
}

public function scopeOpen(Builder $query): Builder
{
    return $query->where('is_open', true);
}

public function scopeFromChannel(Builder $query, string $channel): Builder
{
    return $query->where('channel_name', $channel);
}

// Usage - chainable and readable
$orders = Order::recent(90)
    ->withLinnworksId()
    ->processed()
    ->fromChannel('Amazon')
    ->get();
```

**Benefits:**
- DRY - reusable query logic
- Chainable
- Testable
- Self-documenting

---

#### 1.5 Improve Type Documentation

**Before:**
```php
/**
 * Get all processed orders
 */
public function getAllProcessedOrders(...): Collection
```

**After:**
```php
/**
 * Retrieve all processed orders within a date range
 *
 * @param  int                    $userId          User ID for session token
 * @param  Carbon                 $from            Start date (inclusive)
 * @param  Carbon                 $to              End date (inclusive)
 * @param  ProcessedOrderFilters  $filters         Search and filter criteria
 * @param  int                    $maxOrders       Maximum orders to fetch (1-50000)
 * @param  int|null               $userId          Override user ID
 * @param  Closure|null           $progressCallback  Callback(page, totalPages, count, total)
 *
 * @return Collection<int, LinnworksOrder>
 *
 * @throws InvalidArgumentException When date range is invalid
 * @throws LinnworksApiException    When API request fails
 *
 * @example
 * $orders = $service->getAllProcessedOrders(
 *     userId: 1,
 *     from: Carbon::parse('2024-01-01'),
 *     to: Carbon::parse('2024-01-31'),
 *     filters: ProcessedOrderFilters::byProcessedDate(),
 *     maxOrders: 10000,
 * );
 */
public function getAllProcessedOrders(
    int $userId,
    Carbon $from,
    Carbon $to,
    ProcessedOrderFilters $filters = new ProcessedOrderFilters(),
    int $maxOrders = 10_000,
    ?\Closure $progressCallback = null
): Collection
```

---

### Phase 2: Structural Improvements (3-5 days)

#### 2.1 Split LinnworksApiService into Focused Facades

**Problem:** God class with 686 lines handling orders, products, auth, sessions

**Solution:** Domain-focused facades

```php
// app/Services/Linnworks/LinnworksApiService.php (simplified)
final readonly class LinnworksApiService
{
    public function __construct(
        public ProcessedOrdersFacade $processedOrders,
        public OpenOrdersFacade $openOrders,
        public ProductsFacade $products,
        public AuthenticationFacade $auth,
    ) {}

    // Backward compatibility methods delegate to facades
    public function getAllProcessedOrders(...): Collection
    {
        return $this->processedOrders->getAll(...);
    }
}

// app/Services/Linnworks/Facades/ProcessedOrdersFacade.php
final readonly class ProcessedOrdersFacade
{
    public function __construct(
        private ProcessedOrdersService $service,
        private ProcessedOrdersResponseParser $parser,
        private UserIdResolver $userIdResolver,
    ) {}

    public function getAll(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ProcessedOrderFilters $filters = new ProcessedOrderFilters(),
        int $maxOrders = 10_000,
        ?int $userId = null,
        ?\Closure $progressCallback = null
    ): Collection {
        $userId = $this->userIdResolver->resolve($userId);
        $from ??= Carbon::now()->subDays(config('linnworks.sync.default_date_range', 30));
        $to ??= Carbon::now();

        return $this->service->getAllProcessedOrders(
            $userId,
            $from,
            $to,
            $filters,
            $maxOrders,
            $progressCallback
        );
    }

    public function getPage(
        int $pageNumber,
        ?Carbon $from = null,
        ?Carbon $to = null,
        ProcessedOrderFilters $filters = new ProcessedOrderFilters(),
        ?int $userId = null
    ): ProcessedOrdersPage {
        $userId = $this->userIdResolver->resolve($userId);
        $response = $this->service->searchProcessedOrders(
            $userId,
            $from ?? Carbon::now()->subDays(30),
            $to ?? Carbon::now(),
            $filters,
            $pageNumber,
        );

        return $this->parser->parse($response);
    }
}
```

**Files to Create:**
- `app/Services/Linnworks/Facades/ProcessedOrdersFacade.php`
- `app/Services/Linnworks/Facades/OpenOrdersFacade.php`
- `app/Services/Linnworks/Facades/ProductsFacade.php`
- `app/Services/Linnworks/Facades/AuthenticationFacade.php`
- `app/Services/Linnworks/Support/UserIdResolver.php`

---

#### 2.2 Create Sync Progress Tracker Action

**Before:** Closure passed through multiple layers

**After:**
```php
// app/Actions/Sync/TrackSyncProgress.php
final class TrackSyncProgress
{
    public function __construct(
        private SyncLog $syncLog,
    ) {}

    public function onPageFetched(
        SyncStage $stage,
        int $currentPage,
        int $totalPages,
        int $fetchedCount,
        int $totalResults
    ): void {
        $message = $this->buildMessage($stage, $currentPage, $totalPages, $fetchedCount);

        // Broadcast to UI
        event(new SyncProgressUpdated(
            stage: $stage->value,
            message: $message,
            count: $fetchedCount
        ));

        // Persist every 10 pages or on completion
        if ($currentPage % 10 === 0 || $currentPage === $totalPages) {
            $this->syncLog->updateProgress($stage->value, $currentPage, $totalPages, [
                'message' => $message,
                'fetched_count' => $fetchedCount,
                'total_results' => $totalResults,
                'updated_at' => now(),
            ]);
        }
    }

    private function buildMessage(
        SyncStage $stage,
        int $currentPage,
        int $totalPages,
        int $fetchedCount
    ): string {
        return match($stage) {
            SyncStage::FETCHING_OPEN_IDS => "Checking open orders...",
            SyncStage::FETCHING_PROCESSED_IDS => "Fetching processed orders: page {$currentPage}/" .
                ($totalPages ?: '?') . " ({$fetchedCount} fetched)",
            SyncStage::IMPORTING => "Importing batch {$currentPage}/{$totalPages}...",
            SyncStage::COMPLETED => "Sync completed successfully!",
        };
    }
}

// app/Enums/SyncStage.php
enum SyncStage: string
{
    case FETCHING_OPEN_IDS = 'fetching-open-ids';
    case FETCHING_PROCESSED_IDS = 'fetching-processed-ids';
    case IMPORTING = 'importing';
    case COMPLETED = 'completed';
}

// Usage in SyncOrdersJob
$progressTracker = app(TrackSyncProgress::class, ['syncLog' => $syncLog]);

$processedOrders = $api->getAllProcessedOrders(
    from: $processedFrom,
    to: $processedTo,
    filters: $filters,
    progressCallback: fn($page, $totalPages, $count, $total) =>
        $progressTracker->onPageFetched(
            SyncStage::FETCHING_PROCESSED_IDS,
            $page,
            $totalPages,
            $count,
            $total
        )
);
```

---

#### 2.3 Standardize Action Pattern

**Naming Convention:**
- Use verb phrases: `ImportOrdersInBulk`, `TrackSyncProgress`, `ParseProcessedOrdersResponse`
- Implement either `handle()` or `__invoke()` method
- Make classes `final readonly` where possible

**Action Interface:**
```php
// app/Contracts/Action.php
interface Action
{
    // Marker interface for DI container
}

// app/Contracts/ImportAction.php
interface ImportAction extends Action
{
    public function handle(Collection $orders): ImportOrdersResult;
}

// Implementation
final readonly class ImportOrdersInBulk implements ImportAction
{
    public function __construct(
        private OrderBulkWriter $bulkWriter,
        private bool $dryRun = false,
    ) {}

    public function handle(Collection $orders): ImportOrdersResult
    {
        // ... existing logic
    }
}
```

---

#### 2.4 Add Exception Hierarchy

**Create domain-specific exceptions:**
```php
// app/Exceptions/Linnworks/LinnworksApiException.php
class LinnworksApiException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $endpoint = null,
        public readonly ?int $statusCode = null,
        public readonly ?array $context = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function rateLimitExceeded(string $endpoint): self
    {
        return new self(
            message: 'Linnworks API rate limit exceeded',
            endpoint: $endpoint,
            statusCode: 429
        );
    }

    public static function sessionExpired(): self
    {
        return new self(
            message: 'Linnworks session token expired',
            statusCode: 401
        );
    }

    public static function networkError(string $endpoint, \Throwable $previous): self
    {
        return new self(
            message: 'Network error connecting to Linnworks',
            endpoint: $endpoint,
            previous: $previous
        );
    }

    public function isRateLimit(): bool
    {
        return $this->statusCode === 429;
    }

    public function isSessionExpired(): bool
    {
        return $this->statusCode === 401;
    }
}

// Usage with specific handling
try {
    $response = $this->client->makeRequest($request, $sessionToken);
} catch (LinnworksApiException $e) {
    if ($e->isRateLimit()) {
        Log::warning('Rate limit hit, waiting 60s', [
            'endpoint' => $e->endpoint,
        ]);
        sleep(60);
        return $this->searchProcessedOrders(...); // Retry
    }

    if ($e->isSessionExpired()) {
        $this->sessionManager->refreshSessionToken($userId);
        return $this->searchProcessedOrders(...); // Retry with fresh token
    }

    throw $e; // Re-throw if we can't handle
}
```

---

#### 2.5 Use LinnworksClient Consistently

**Problem:** ProcessedOrdersService uses `Http::` facade directly

**Solution:**
```php
// ProcessedOrdersService.php - BEFORE
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => $sessionToken->token,
    'accept' => 'application/json',
    'content-type' => 'application/json',
])->post($sessionToken->getBaseUrl() . 'ProcessedOrders/SearchProcessedOrders', $body);

// ProcessedOrdersService.php - AFTER
$request = ApiRequest::post('ProcessedOrders/SearchProcessedOrders', $body);
$response = $this->client->makeRequest($request, $sessionToken);
```

**Benefits:**
- Consistent error handling
- Centralized rate limiting
- Easier to mock in tests
- Logging in one place

---

### Phase 3: Advanced Features (5-7 days)

#### 3.1 Add Retry Logic

```php
use Illuminate\Support\Facades\Http;

// In LinnworksClient
public function makeRequest(ApiRequest $request, SessionToken $token): ApiResponse
{
    return retry(3, function () use ($request, $token) {
        $response = Http::withHeaders($this->buildHeaders($token))
            ->timeout(30)
            ->post($token->getBaseUrl() . $request->endpoint, $request->parameters->toArray());

        if ($response->status() === 429) {
            throw LinnworksApiException::rateLimitExceeded($request->endpoint);
        }

        return ApiResponse::fromHttpResponse($response);
    }, sleepMilliseconds: function (int $attempt) {
        return $attempt * 1000; // Exponential backoff: 1s, 2s, 3s
    });
}
```

---

#### 3.2 Add Caching Layer

```php
// app/Services/Linnworks/Facades/CachedProcessedOrdersFacade.php
final readonly class CachedProcessedOrdersFacade
{
    public function __construct(
        private ProcessedOrdersFacade $facade,
    ) {}

    public function getAll(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ProcessedOrderFilters $filters = new ProcessedOrderFilters(),
        int $maxOrders = 10_000,
        bool $fresh = false,
    ): Collection {
        if ($fresh) {
            return $this->facade->getAll($from, $to, $filters, $maxOrders);
        }

        $cacheKey = $this->buildCacheKey($from, $to, $filters);
        $ttl = $this->determineTTL($from, $to);

        return Cache::remember($cacheKey, $ttl, function () use ($from, $to, $filters, $maxOrders) {
            return $this->facade->getAll($from, $to, $filters, $maxOrders);
        });
    }

    private function buildCacheKey(?Carbon $from, ?Carbon $to, ProcessedOrderFilters $filters): string
    {
        return sprintf(
            'processed_orders:%s:%s:%s',
            $from?->format('Y-m-d') ?? 'default',
            $to?->format('Y-m-d') ?? 'default',
            md5(serialize($filters->toArray()))
        );
    }

    private function determineTTL(?Carbon $from, ?Carbon $to): int
    {
        // Historical data (> 30 days old) can be cached longer
        if ($from && $from->lt(Carbon::now()->subDays(30))) {
            return 3600 * 24; // 24 hours
        }

        // Recent data cached for 1 hour
        return 3600;
    }
}
```

---

#### 3.3 Livewire + Alpine.js State Management

```php
// ImportProgress.php - Simplified Livewire Component
class ImportProgress extends Component
{
    #[Locked]
    public ImportProgressState $state;

    public string $fromDate = '';
    public string $toDate = '';
    public int $batchSize = 200;

    public function mount(ManageImportProgressState $stateManager): void
    {
        $this->state = $stateManager->loadActiveSync();

        // Set default date range
        $this->toDate = now()->format('Y-m-d');
        $this->fromDate = now()->subDays(730)->format('Y-m-d');
    }

    public function startImport(): void
    {
        $this->validate([
            'fromDate' => 'required|date',
            'toDate' => 'required|date|after_or_equal:fromDate',
            'batchSize' => 'required|integer|min:50|max:200',
        ]);

        SyncOrdersJob::dispatch(
            startedBy: auth()->user()?->name ?? 'UI Import',
            historicalImport: true,
            fromDate: Carbon::parse($this->fromDate)->startOfDay(),
            toDate: Carbon::parse($this->toDate)->endOfDay(),
        );
    }

    #[On('echo:sync-progress,SyncProgressUpdated')]
    public function handleSyncProgress(array $data, ManageImportProgressState $stateManager): void
    {
        $this->state = $stateManager->updateFromEvent($data);
    }
}
```

```blade
<!-- resources/views/livewire/settings/import-progress.blade.php -->
<div x-data="importProgress" x-init="initState(@js($state))">
    <!-- UI-only calculations in Alpine -->
    <div class="progress-bar">
        <div class="progress-fill"
             :style="`width: ${progressPercentage}%`"
             x-text="`${progressPercentage}%`">
        </div>
    </div>

    <div x-show="state.isImporting" x-transition>
        <p x-text="`Processing: ${ordersPerSecond} orders/sec`"></p>
        <p x-text="`Estimated time remaining: ${estimatedTimeFormatted}`"></p>
    </div>
</div>

<script>
Alpine.data('importProgress', () => ({
    state: {},

    initState(initialState) {
        this.state = initialState;
    },

    get progressPercentage() {
        if (!this.state.totalBatches) return 0;
        return Math.round((this.state.batchNumber / this.state.totalBatches) * 100);
    },

    get ordersPerSecond() {
        return this.state.ordersPerSecond?.toFixed(1) || 0;
    },

    get estimatedTimeFormatted() {
        if (!this.state.estimatedRemaining) return 'Calculating...';
        const minutes = Math.floor(this.state.estimatedRemaining / 60);
        const seconds = Math.floor(this.state.estimatedRemaining % 60);
        return `${minutes}m ${seconds}s`;
    }
}));
</script>
```

---

### Phase 4: Testing & Observability (2-3 days)

#### 4.1 Create Test Factories

```php
// tests/Factories/LinnworksOrderFactory.php
final class LinnworksOrderFactory
{
    public static function make(array $overrides = []): LinnworksOrder
    {
        return LinnworksOrder::fromArray(array_merge([
            'pkOrderID' => Str::uuid()->toString(),
            'nOrderId' => fake()->numberBetween(1000000, 9999999),
            'nStatus' => 0,
            'cFullName' => fake()->name(),
            'cEmailAddress' => fake()->email(),
            'cPostCode' => fake()->postcode(),
            'cCountry' => 'United Kingdom',
            'Source' => fake()->randomElement(['AMAZON', 'EBAY', 'WEBSITE']),
            'SubSource' => 'Default',
            'dReceivedDate' => Carbon::now()->subDays(rand(1, 30))->toISOString(),
            'dProcessedOn' => null,
            'fTotalCharge' => fake()->randomFloat(2, 10, 500),
            'fPostageCost' => fake()->randomFloat(2, 0, 20),
            'Items' => [],
        ], $overrides));
    }

    public static function processed(array $overrides = []): LinnworksOrder
    {
        return self::make(array_merge([
            'nStatus' => 1,
            'dProcessedOn' => Carbon::now()->subDays(rand(1, 10))->toISOString(),
        ], $overrides));
    }

    public static function withItems(int $itemCount = 3, array $overrides = []): LinnworksOrder
    {
        $items = collect()->times($itemCount, fn() => [
            'ItemId' => Str::uuid()->toString(),
            'SKU' => 'TEST-' . fake()->bothify('###??'),
            'ItemTitle' => fake()->words(3, true),
            'Quantity' => rand(1, 5),
            'Price' => fake()->randomFloat(2, 5, 100),
        ])->toArray();

        return self::make(array_merge(['Items' => $items], $overrides));
    }
}

// Usage in tests
$order = LinnworksOrderFactory::processed(['Source' => 'AMAZON']);
$orderWithItems = LinnworksOrderFactory::withItems(5);
```

---

#### 4.2 Add Metrics with Laravel Pulse

```php
// In ProcessedOrdersFacade
use Laravel\Pulse\Facades\Pulse;

public function getAll(...): Collection
{
    $startTime = microtime(true);

    try {
        $orders = $this->service->getAllProcessedOrders(...);

        $duration = microtime(true) - $startTime;

        // Record metrics
        Pulse::record('linnworks.orders.fetched', $orders->count())
            ->tag('type', 'processed')
            ->tag('date_range', $from->format('Y-m'));

        Pulse::record('linnworks.api.latency', (int)($duration * 1000))
            ->tag('endpoint', 'ProcessedOrders/SearchProcessedOrders');

        return $orders;
    } catch (\Throwable $e) {
        Pulse::record('linnworks.api.errors', 1)
            ->tag('endpoint', 'ProcessedOrders/SearchProcessedOrders')
            ->tag('error_type', get_class($e));

        throw $e;
    }
}
```

---

## 📁 File Structure After Refactor

```
app/
├── Actions/
│   ├── Sync/
│   │   ├── TrackSyncProgress.php
│   │   └── ManageImportProgressState.php
│   └── Orders/
│       ├── ImportOrdersInBulk.php (renamed from StreamingOrderImporter)
│       └── ParseProcessedOrdersResponse.php
│
├── Contracts/
│   ├── Action.php
│   └── ImportAction.php
│
├── DataTransferObjects/
│   └── Linnworks/
│       ├── ProcessedOrderFilters.php
│       ├── ProcessedOrdersPage.php
│       └── ImportProgressState.php
│
├── Enums/
│   └── Linnworks/
│       ├── ProcessedOrderDateField.php
│       ├── OrderStatus.php
│       └── SyncStage.php
│
├── Exceptions/
│   └── Linnworks/
│       ├── LinnworksApiException.php
│       ├── RateLimitExceededException.php
│       └── SessionExpiredException.php
│
├── Services/
│   └── Linnworks/
│       ├── LinnworksApiService.php (simplified facade)
│       ├── Facades/
│       │   ├── ProcessedOrdersFacade.php
│       │   ├── CachedProcessedOrdersFacade.php
│       │   ├── OpenOrdersFacade.php
│       │   ├── ProductsFacade.php
│       │   └── AuthenticationFacade.php
│       ├── Orders/
│       │   ├── ProcessedOrdersService.php
│       │   └── ProcessedOrdersResponseParser.php
│       └── Support/
│           └── UserIdResolver.php
│
└── Models/
    └── Order.php (with new scopes)

tests/
└── Factories/
    └── LinnworksOrderFactory.php
```

---

## ✅ Implementation Checklist

### Phase 1: Quick Wins
- [ ] Create `ProcessedOrderDateField` enum
- [ ] Create `OrderStatus` enum
- [ ] Create `ProcessedOrdersResponseParser` class
- [ ] Create `ProcessedOrdersPage` DTO
- [ ] Create `ProcessedOrderFilters` DTO
- [ ] Add query scopes to Order model
- [ ] Improve PHPDoc annotations
- [ ] Write tests for new classes

### Phase 2: Structural
- [ ] Create facade directory structure
- [ ] Extract `ProcessedOrdersFacade`
- [ ] Extract `OpenOrdersFacade`
- [ ] Extract `ProductsFacade`
- [ ] Create `UserIdResolver` helper
- [ ] Create `SyncStage` enum
- [ ] Create `TrackSyncProgress` action
- [ ] Create `ManageImportProgressState` action
- [ ] Rename `StreamingOrderImporter` → `ImportOrdersInBulk`
- [ ] Create `LinnworksApiException` hierarchy
- [ ] Update ProcessedOrdersService to use LinnworksClient
- [ ] Update all action classes to use standardized pattern
- [ ] Write integration tests

### Phase 3: Advanced
- [ ] Add retry logic to LinnworksClient
- [ ] Create `CachedProcessedOrdersFacade`
- [ ] Add cache warmup command
- [ ] Simplify ImportProgress Livewire component
- [ ] Add Alpine.js state management
- [ ] Extract ImportProgressState DTO
- [ ] Add Laravel Pulse metrics
- [ ] Create custom Pulse dashboard
- [ ] Write performance tests

### Phase 4: Polish
- [ ] Create `LinnworksOrderFactory`
- [ ] Write comprehensive unit tests
- [ ] Write integration tests
- [ ] Write feature tests for historical imports
- [ ] Add PHPStan level 8 compliance
- [ ] Run Laravel Pint
- [ ] Update documentation
- [ ] Create architecture diagrams

---

## 🎓 Learning Resources

### PHP 8.2+ Features
- [Enums](https://www.php.net/manual/en/language.enumerations.php)
- [Readonly Classes](https://www.php.net/manual/en/language.oop5.basic.php#language.oop5.basic.class.readonly)
- [DNF Types](https://wiki.php.net/rfc/dnf_types)

### Laravel Best Practices
- [Laravel Actions](https://laravelactions.com/)
- [Service Container](https://laravel.com/docs/11.x/container)
- [Query Scopes](https://laravel.com/docs/11.x/eloquent#query-scopes)

### Testing
- [Pest PHP](https://pestphp.com/)
- [Laravel Testing](https://laravel.com/docs/11.x/testing)

---

## 📝 Notes

- All changes are backward compatible
- Old methods will be deprecated, not removed
- Each phase can be deployed independently
- Performance should improve or remain same
- Test coverage should increase to 80%+

---

**Last Updated:** 2025-10-12
**Next Review:** After Phase 1 completion
