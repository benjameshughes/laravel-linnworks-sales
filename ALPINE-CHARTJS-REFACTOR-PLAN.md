# Alpine.js Chart.js Formatting Refactor Plan

**Problem**: Chart.js formatting is happening in PHP (Livewire/Cache) instead of JavaScript (Alpine.js)

**Root Cause**: This creates tight coupling and prevents Alpine from truly managing the chart lifecycle

---

## Current Architecture (WRONG ❌)

**Cache stores**:
```php
'chart_line' => [
    'labels' => ['Jan 1', 'Jan 2', ...],
    'datasets' => [['label' => 'Revenue', 'data' => [100, 200, ...], ...]]
]
```

**Livewire does**:
```php
$this->chartData = $cached['chart_line']; // Pre-formatted Chart.js data
```

**Alpine does**:
```javascript
this.chart = new Chart(this.$refs.canvas, {
    data: this.data  // Just passes through pre-formatted data
});
```

---

## Target Architecture (CORRECT ✅)

**Cache stores**:
```php
'daily_breakdown' => [
    ['date' => 'Jan 1', 'revenue' => 100, 'orders' => 5, 'items' => 12, ...],
    ['date' => 'Jan 2', 'revenue' => 200, 'orders' => 8, 'items' => 20, ...],
]
```

**Livewire does**:
```php
$this->dailyBreakdown = $cached['daily_breakdown']; // Raw data only
$this->viewMode = 'revenue'; // UI state
```

**Alpine does**:
```javascript
init() {
    // Transform raw data based on view mode
    const chartData = this.formatForChartJs(this.dailyBreakdown, this.viewMode);
    
    this.chart = new Chart(this.$refs.canvas, {
        type: 'line',
        data: chartData,
        options: this.getChartOptions()  // 3-second animations here
    });
    
    // Watch for view mode changes
    this.$watch('viewMode', (newMode) => {
        const newData = this.formatForChartJs(this.dailyBreakdown, newMode);
        this.chart.data = newData;
        this.chart.update('active');  // With animation!
    });
}

formatForChartJs(breakdown, mode) {
    return {
        labels: breakdown.map(d => d.date),
        datasets: [{
            label: mode === 'revenue' ? 'Revenue' : 'Orders',
            data: breakdown.map(d => d[mode]),
            borderColor: 'rgb(59, 130, 246)',
            ...
        }]
    };
}
```

---

## Benefits

1. **Separation of Concerns**: PHP = data, JS = presentation
2. **Reactive View Modes**: Alpine can switch between revenue/orders WITHOUT Livewire roundtrip
3. **Animations Work**: Changing view mode triggers `chart.update('active')` with animations
4. **Single Source of Truth**: One cached `daily_breakdown`, multiple chart views
5. **Smaller Cache**: Raw data is smaller than duplicate pre-formatted charts

---

## Files to Change

1. **`app/Jobs/WarmPeriodCacheJob.php`** - Cache raw `daily_breakdown` only
2. **`app/Livewire/Dashboard/SalesTrendChart.php`** - Pass raw data + viewMode
3. **`app/Livewire/Dashboard/DailyRevenueChart.php`** - Pass raw data + viewMode  
4. **`app/Livewire/Dashboard/ChannelDistributionChart.php`** - Pass raw channel data
5. **`resources/js/components/charts/sales-trend.js`** - Add formatting logic
6. **`resources/js/components/charts/daily-revenue.js`** - Add formatting logic
7. **`resources/js/components/charts/channel-distribution.js`** - Add formatting logic

---

## Implementation Steps

### Step 1: Update Cache Layer (WarmPeriodCacheJob)

Store ONLY raw daily breakdown data:

```php
$dailyBreakdown = $service->getDailyRevenueData(
    period: $this->period,
    channel: $this->channel
);

return [
    // Raw data only - no Chart.js formatting
    'daily_breakdown' => $dailyBreakdown->toArray(),
    
    // Other metrics...
    'revenue' => $summary['total_revenue'],
    'orders' => $summary['total_orders'],
    // ...
];
```

### Step 2: Update Livewire Components

Pass raw data via `@entangle`, let Alpine handle formatting:

```php
// app/Livewire/Dashboard/SalesTrendChart.php
public array $dailyBreakdown = [];
public string $viewMode = 'revenue';

private function calculateChartData(): void
{
    $cached = Cache::get($cacheKey);
    
    if ($cached && isset($cached['daily_breakdown'])) {
        $this->dailyBreakdown = $cached['daily_breakdown'];
        return;
    }
    
    // Cache miss
    $this->dailyBreakdown = [];
}
```

Blade template:
```blade
<div
    wire:ignore
    x-data="salesTrendChart(
        @entangle('dailyBreakdown').live,
        @entangle('viewMode').live
    )"
>
```

### Step 3: Update Alpine Components

Add formatting logic to each Alpine component:

```javascript
// resources/js/components/charts/sales-trend.js
Alpine.data('salesTrendChart', (initialBreakdown, initialViewMode) => ({
    chart: null,
    dailyBreakdown: initialBreakdown,
    viewMode: initialViewMode,
    loading: true,

    init() {
        if (!this.dailyBreakdown || this.dailyBreakdown.length === 0) {
            this.loading = false;
            return;
        }

        const chartData = this.formatForChartJs(this.dailyBreakdown, this.viewMode);
        
        this.chart = new Chart(this.$refs.canvas, {
            type: 'line',
            data: chartData,
            options: this.getChartOptions()
        });

        this.loading = false;

        // Watch for data changes
        this.$watch('dailyBreakdown', (newBreakdown) => {
            if (this.chart && newBreakdown) {
                this.chart.data = this.formatForChartJs(newBreakdown, this.viewMode);
                this.chart.update('none');
            }
        });

        // Watch for view mode changes (THIS MAKES ANIMATIONS WORK!)
        this.$watch('viewMode', (newMode) => {
            if (this.chart && this.dailyBreakdown) {
                this.chart.data = this.formatForChartJs(this.dailyBreakdown, newMode);
                this.chart.update('active'); // Animate the transition!
            }
        });
    },

    formatForChartJs(breakdown, mode) {
        const labels = breakdown.map(d => d.date);
        const data = breakdown.map(d => d[mode]);

        return {
            labels: labels,
            datasets: [{
                label: mode === 'revenue' ? 'Revenue' : 'Orders',
                data: data,
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                fill: true,
            }]
        };
    },

    getChartOptions() {
        return {
            responsive: true,
            maintainAspectRatio: false,
            animation: {
                duration: 3000  // 3-second animations!
            },
            // ... rest of options
        };
    },

    destroy() {
        if (this.chart) {
            this.chart.destroy();
            this.chart = null;
        }
    }
}));
```

---

## Testing Checklist

- [ ] Sales trend chart displays with data
- [ ] Sales trend chart animates on initial load (3 seconds)
- [ ] Switching revenue/orders view mode animates the transition
- [ ] Daily revenue chart displays with data
- [ ] Daily revenue chart animates on initial load
- [ ] Switching orders_revenue/items view mode animates
- [ ] Channel distribution chart displays with data
- [ ] Channel distribution chart animates on initial load
- [ ] Switching detailed/grouped view mode animates
- [ ] All charts work after cache warm
- [ ] All charts work with custom date ranges
- [ ] No console errors
- [ ] Tests pass
