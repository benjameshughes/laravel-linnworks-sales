# Reports Dashboard System Plan

**Created:** 2025-11-13
**Status:** Planning Phase
**Estimated Timeline:** Phase 1 MVP = 1-2 weeks

---

## Executive Summary

This document outlines a comprehensive plan for building a Reports Dashboard system that allows easy creation, management, and execution of custom reports (like the existing Sophie variation group sales report).

**Architecture:** Abstract Report Class pattern with auto-discovery
**UI Pattern:** Two-panel layout (report selector + viewer)
**Tech Stack:** Laravel 12, Livewire 3, Flux UI, PhpSpreadsheet

---

## Table of Contents

1. [Architecture Pattern](#1-architecture-pattern)
2. [Database Schema](#2-database-schema)
3. [File Structure](#3-file-structure)
4. [UI/UX Wireframe](#4-uiux-wireframe)
5. [User Flow](#5-user-flow)
6. [Report Definition Example](#6-report-definition-example)
7. [Core Architecture Classes](#7-core-architecture-classes)
8. [Phase 1 Implementation Plan (MVP)](#8-phase-1-implementation-plan-mvp)
9. [Phase 2+ Roadmap](#9-phase-2-roadmap)
10. [Key Design Decisions](#10-key-design-decisions)
11. [Testing Strategy](#13-testing-strategy)
12. [Security & Performance](#14-15-security--performance)

---

## 1. Architecture Pattern

### Pattern: Abstract Report Class + Concrete Implementations

**Core Concept:**
```
AbstractReport (base class)
├── Defines contract: filters(), columns(), query(), export()
├── Handles common logic: pagination, caching, Excel generation
└── Concrete reports extend and implement specifics
    ├── SophieVariationGroupSalesReport
    ├── ProductPerformanceReport (future)
    └── ChannelAnalyticsReport (future)
```

**Why This Pattern?**
- ✅ **Simplicity**: Each report is a focused PHP class
- ✅ **Type Safety**: PHP 8.2+ features (readonly properties, enums)
- ✅ **Testability**: Unit test each report independently
- ✅ **Discoverability**: Auto-registration via directory scanning
- ✅ **Flexibility**: Complex reports can override base behavior

---

## 2. Database Schema

```sql
-- Track all report generations
CREATE TABLE report_executions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    report_class VARCHAR(255) NOT NULL,      -- Fully qualified class name
    filters JSON NOT NULL,                   -- Filter values used
    row_count INT UNSIGNED,                  -- Total rows in report
    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
    file_path VARCHAR(255),                  -- Relative path in storage/reports/
    file_size INT UNSIGNED,                  -- File size in bytes
    error_message TEXT,                      -- If status=failed
    started_at TIMESTAMP NULL,
    completed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_created (user_id, created_at DESC),
    INDEX idx_status (status),
    INDEX idx_report_class (report_class),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Optional: Quick access to favorite reports
CREATE TABLE user_report_favorites (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    report_class VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_report (user_id, report_class),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

**Migration command:**
```bash
php artisan make:migration create_reports_tables
```

---

## 3. File Structure

```
app/
├── Reports/
│   ├── AbstractReport.php                    # Base class with common logic
│   ├── Concerns/
│   │   ├── HasDateRangeFilter.php           # Trait for date filters
│   │   ├── HasSkuFilter.php                 # Trait for SKU filters
│   │   └── HasSubsourceFilter.php           # Trait for channel filters
│   ├── Enums/
│   │   ├── ReportCategory.php               # Sales, Products, Channels, etc.
│   │   └── ExportFormat.php                 # XLSX, CSV, PDF (future)
│   ├── Filters/
│   │   ├── DateRangeFilter.php              # Filter definition class
│   │   ├── SkuFilter.php
│   │   ├── SubsourceFilter.php
│   │   └── FilterContract.php               # Interface for filters
│   ├── Exports/
│   │   └── ReportExport.php                 # PhpSpreadsheet wrapper
│   ├── SophieVariationGroupSalesReport.php  # Concrete report
│   └── ReportRegistry.php                   # Auto-discovers reports
│
├── Livewire/
│   └── Reports/
│       ├── ReportsIndex.php                 # Main dashboard component
│       ├── ReportViewer.php                 # Filters + preview + download
│       └── ReportExecutionStatus.php        # Download queue status
│
├── Jobs/
│   └── GenerateReportExport.php             # Background job for large reports
│
├── Models/
│   ├── ReportExecution.php                  # Eloquent model
│   └── UserReportFavorite.php               # Eloquent model
│
└── View/
    └── Components/
        └── Reports/
            ├── ReportCard.php               # Blade component for report cards
            ├── FilterRenderer.php           # Blade component for filters
            └── PreviewTable.php             # Blade component for data preview

resources/
└── views/
    ├── livewire/
    │   └── reports/
    │       ├── reports-index.blade.php      # Main dashboard
    │       ├── report-viewer.blade.php      # Filters + preview + download
    │       └── report-execution-status.blade.php
    └── components/
        └── reports/
            ├── report-card.blade.php
            ├── filter-renderer.blade.php
            └── preview-table.blade.php

routes/
└── web.php                                  # Route: /reports
```

---

## 4. UI/UX Wireframe

### Layout: Two-Panel with Report Cards

```
┌─────────────────────────────────────────────────────────────────┐
│  Reports Dashboard                                     [User ▼] │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────────────┐  ┌──────────────────────────────┐ │
│  │ 📊 SALES REPORTS       │  │  Selected Report: Sophie      │ │
│  ├─────────────────────────┤  │  Variation Group Sales        │ │
│  │                         │  ├──────────────────────────────┤ │
│  │ [Sophie Report Card]    │  │  FILTERS                      │ │
│  │ Variation Group Sales   │  │  ┌─────────────────────────┐ │ │
│  │ Last run: 2h ago        │  │  │ Date Range:             │ │ │
│  │ [View Report →]         │  │  │ [Start] to [End]        │ │ │
│  │                         │  │  │                         │ │ │
│  │ [+ Future Report]       │  │  │ Subsource:              │ │ │
│  │ Coming soon...          │  │  │ [Multi-select ▼]       │ │ │
│  │                         │  │  └─────────────────────────┘ │ │
│  ├─────────────────────────┤  │  [Apply Filters] [Reset]      │ │
│  │ 📦 PRODUCT REPORTS     │  ├──────────────────────────────┤ │
│  ├─────────────────────────┤  │  PREVIEW (First 100 rows)     │ │
│  │ (No reports yet)        │  │  [Data Table - 10 rows/page]  │ │
│  ├─────────────────────────┤  │  ┌──────┬────────┬─────────┐ │ │
│  │ 📺 CHANNEL REPORTS     │  │  │ SKU  │ Sales  │ Revenue │ │ │
│  ├─────────────────────────┤  │  ├──────┼────────┼─────────┤ │ │
│  │ (No reports yet)        │  │  │ ...  │ ...    │ ...     │ │ │
│  └─────────────────────────┘  │  └──────┴────────┴─────────┘ │ │
│                                │  Page 1 of 10 [< 1 2 3 ... >] │
│                                ├──────────────────────────────┤ │
│                                │  DOWNLOAD                     │ │
│                                │  Total rows: 1,234            │ │
│                                │  [Download XLSX]              │ │
│                                │                               │ │
│                                │  Recent Downloads:            │ │
│                                │  • Today 10:30 - 1,234 rows   │ │
│                                │    [Download again]           │ │
│                                └──────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

**Responsive:**
- **Desktop**: Side-by-side (30% left, 70% right)
- **Tablet**: Stacked, report list as dropdown
- **Mobile**: Single column, report selector as modal

---

## 5. User Flow

```
1. User navigates to /reports
   ↓
2. ReportsIndex loads available reports from ReportRegistry
   ↓
3. User clicks "Sophie Variation Group Sales" card
   ↓
4. ReportViewer loads with Sophie report
   ├─ Render filters (date, subsource, SKUs)
   └─ Apply defaults (last 30 days, all subsources)
   ↓
5. User adjusts filters and clicks "Apply"
   ↓
6. Backend executes $report->preview($filters, limit: 100)
   ↓
7. Preview table displays first 100 rows (paginated)
   ↓
8. User reviews and clicks "Download XLSX"
   ↓
9. Download logic:
   ├─ If < 1000 rows: Immediate download
   └─ If ≥ 1000 rows: Queue job, show progress
   ↓
10. File downloaded or queued execution shown in history
```

---

## 6. Report Definition Example

### Creating a New "Product Performance Report"

**Step 1: Create Report Class**

```php
// app/Reports/ProductPerformanceReport.php

namespace App\Reports;

use App\Reports\Concerns\{HasDateRangeFilter, HasSkuFilter};
use App\Reports\Enums\ReportCategory;
use App\Reports\Filters\{DateRangeFilter, SkuFilter};

class ProductPerformanceReport extends AbstractReport
{
    use HasDateRangeFilter, HasSkuFilter;

    public function name(): string
    {
        return 'Product Performance';
    }

    public function description(): string
    {
        return 'Sales, revenue, and margin analysis by product SKU';
    }

    public function icon(): string
    {
        return 'heroicon-o-chart-bar';
    }

    public function category(): ReportCategory
    {
        return ReportCategory::Products;
    }

    public function filters(): array
    {
        return [
            new DateRangeFilter(required: true),
            new SkuFilter(multiple: true),
        ];
    }

    public function columns(): array
    {
        return [
            'sku' => ['label' => 'SKU', 'type' => 'string'],
            'product_name' => ['label' => 'Product Name', 'type' => 'string'],
            'units_sold' => ['label' => 'Units Sold', 'type' => 'integer'],
            'total_revenue' => ['label' => 'Revenue', 'type' => 'currency'],
            'margin_percent' => ['label' => 'Margin %', 'type' => 'percentage'],
        ];
    }

    protected function buildQuery(array $filters): Builder
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->whereBetween('orders.order_date', [
                $filters['date_range']['start'],
                $filters['date_range']['end']
            ])
            ->when($filters['skus'] ?? null, fn($q, $skus) => $q->whereIn('sku', $skus))
            ->select([
                'order_items.sku',
                DB::raw('SUM(quantity) as units_sold'),
                DB::raw('SUM(quantity * unit_price) as total_revenue'),
                // ... more columns
            ])
            ->groupBy('sku')
            ->orderByDesc('total_revenue');
    }
}
```

**Step 2: That's It!**

The report is **auto-discovered** by `ReportRegistry`. No registration needed.

---

## 7. Core Architecture Classes

### AbstractReport (Base Class)

```php
// app/Reports/AbstractReport.php

namespace App\Reports;

abstract class AbstractReport
{
    // Metadata
    abstract public function name(): string;
    abstract public function description(): string;
    abstract public function icon(): string;
    abstract public function category(): ReportCategory;
    abstract public function filters(): array;
    abstract public function columns(): array;

    // Query building
    abstract protected function buildQuery(array $filters): Builder;

    // Public API
    public function preview(array $filters, int $limit = 100): Collection
    {
        $this->validateFilters($filters);
        return $this->buildQuery($filters)->limit($limit)->get();
    }

    public function count(array $filters): int
    {
        $this->validateFilters($filters);
        return $this->buildQuery($filters)->count();
    }

    public function export(array $filters, ExportFormat $format = ExportFormat::XLSX): string
    {
        $this->validateFilters($filters);
        $exporter = new ReportExport($this, $this->buildQuery($filters), $filters, $format);
        return $exporter->generate();
    }

    // Helpers
    protected function validateFilters(array $filters): void { /* ... */ }
    public function slug(): string { return Str::kebab(class_basename($this)); }
}
```

### ReportRegistry (Auto-Discovery)

```php
// app/Reports/ReportRegistry.php

namespace App\Reports;

class ReportRegistry
{
    private static ?Collection $reports = null;

    public static function all(): Collection
    {
        if (self::$reports !== null) return self::$reports;

        self::$reports = collect();

        // Scan app/Reports for classes extending AbstractReport
        $reportFiles = File::allFiles(app_path('Reports'));

        foreach ($reportFiles as $file) {
            $className = 'App\\Reports\\' . $file->getFilenameWithoutExtension();

            if (is_subclass_of($className, AbstractReport::class) &&
                !(new ReflectionClass($className))->isAbstract()) {
                self::$reports->push(new $className());
            }
        }

        return self::$reports;
    }

    public static function byCategory(): Collection
    {
        return self::all()->groupBy(fn($r) => $r->category());
    }

    public static function find(string $className): ?AbstractReport
    {
        return self::all()->first(fn($r) => get_class($r) === $className);
    }
}
```

---

## 8. Phase 1 Implementation Plan (MVP)

**Goal:** Get Variant Group Sales Report into the Reports framework + basic infrastructure

**Timeline:** 1-2 weeks

### Phase 1.1: Foundation (Days 1-2)
- [ ] Create database migrations (`report_executions`)
- [ ] Create `AbstractReport` base class
- [ ] Create `ReportRegistry` with auto-discovery
- [ ] Create `ReportCategory` enum
- [ ] Create filter system (FilterContract, DateRangeFilter, etc.)
- [ ] Create `ReportExport` wrapper for PhpSpreadsheet
- [ ] Write unit tests

### Phase 1.2: Migrate Variation Group Sales Report (Days 3-4)
- [ ] Create `VariationGroupSalesReport` class
- [ ] Migrate existing Variations Group Sales Report logic (filters, query, export)
- [ ] Write tests for Variations Group Sales Report report
- [ ] Verify parity with existing component

### Phase 1.3: UI Components (Days 5-7)
- [ ] Create `ReportsIndex` Livewire component
- [ ] Create `ReportViewer` Livewire component
- [ ] Create Blade components (report-card, filter-renderer, preview-table)
- [ ] Write feature tests for UI

### Phase 1.4: Background Jobs (Days 8-9)
- [ ] Create `GenerateReportExport` job
- [ ] Create `ReportExecution` model
- [ ] Add download history UI
- [ ] Write tests for background flow

### Phase 1.5: Polish & Deploy (Days 10-11)
- [ ] Loading states, error handling
- [ ] Dark mode support
- [ ] Responsive design
- [ ] Performance testing
- [ ] Documentation
- [ ] Deploy

### Phase 1.6: Cleanup (Day 12)
- [ ] Deprecate old Sophie component
- [ ] Update navigation
- [ ] User communication

**Deliverables:**
- Functional Reports dashboard
- Sophie migrated to new system
- Infrastructure for future reports
- Test coverage
- Documentation

---

## 9. Phase 2+ Roadmap

### Phase 2: Expand Library (Month 2)
- Add 3-5 new reports (Product Performance, Channel Analytics, etc.)
- Refine common patterns (traits, mixins)
- Report comparison feature

### Phase 3: Advanced Features (Month 3)
- **Scheduled Reports**: Cron-based generation with email delivery
- **Report Subscriptions**: Email when report updates
- **Report Snapshots**: Save and compare over time
- **Export Formats**: Add CSV, PDF

### Phase 4: Report Builder UI (Month 4-5)
- **Visual Report Builder**: No-code report creation
- Drag-and-drop columns, visual filter builder
- Store report definitions in database
- Report templates

### Phase 5: Analytics & Insights (Month 6)
- Usage analytics (which reports used most)
- Smart recommendations
- AI-powered insights (OpenAI integration)
- Data visualizations (Chart.js)

### Phase 6: Performance & Scale (Ongoing)
- Query optimization, caching (Redis)
- Streaming downloads
- Parallel processing
- CDN integration (S3/R2)

---

## 10. Key Design Decisions

### Why Abstract Report Class vs Config-Driven?

**Decision:** Abstract PHP class

**Rationale:**
- Type safety with PHP 8.2+ features
- IDE autocomplete and refactoring
- Easy to test
- Flexible for complex logic
- Phase 1 prioritizes developer velocity
- Can add UI builder later (Phase 4)

### Why Two-Panel Layout?

**Decision:** Side-by-side panels

**Rationale:**
- Always see available reports (context)
- Fast switching between reports
- Familiar pattern (Gmail, Slack)
- Desktop-first (most users)

### Why Background Jobs for Large Reports?

**Decision:** Queue if ≥ 1000 rows

**Rationale:**
- Prevents PHP timeouts
- Better UX (no long loading)
- Server resource management
- Email notification option

---

## 11. Testing Strategy

### Unit Tests
- `AbstractReportTest`
- `ReportRegistryTest`
- Filter tests (DateRangeFilter, SkuFilter, etc.)
- Individual report tests

### Feature Tests
- `ReportsIndexTest`
- `ReportViewerTest`
- `ReportDownloadTest`
- `ReportExecutionStatusTest`

### Browser Tests (Optional, Dusk)
- End-to-end flow: Select → Filter → Preview → Download
- Responsive layouts

---

## 12. Security Considerations

1. **Authorization**: Verify user owns report execution before download
2. **Validation**: Validate all filter inputs (prevent SQL injection, XSS)
3. **Rate Limiting**: Limit downloads (10/min per user)
4. **File Storage**: Private storage (not public)
5. **Cleanup**: Delete old files (>30 days)

---

## 13. Performance Considerations

1. **Query Optimization**: Add indexes, use `cursor()` for exports
2. **Caching**: Cache report list (5min), preview data (1min)
3. **Streaming**: Stream large XLSX to browser
4. **Background Processing**: Use queue workers with timeout

---

## 14. File Size Estimates

**Phase 1 Total:** ~2,150 lines of code

- `AbstractReport.php`: ~200 lines
- `ReportRegistry.php`: ~100 lines
- `VariationGroupSalesReport.php`: ~150 lines
- `ReportExport.php`: ~150 lines
- Filters: ~300 lines
- Livewire components: ~300 lines
- Blade views: ~300 lines
- Job: ~100 lines
- Tests: ~500 lines
- Migrations: ~50 lines

---

## Next Steps

1. **Review this plan** - Approve or request changes
2. **Start Phase 1.1** - Build foundation (AbstractReport, ReportRegistry, filters)
3. **Iterate** - Gather feedback, adjust as needed

---

## Questions?

- Which phase do you want to start with?
- Any changes to the architecture?
- Any additional reports planned for Phase 2?

---

**Document Version:** 1.0
**Last Updated:** 2025-11-13
**Author:** Claude Code + Ben Hughes