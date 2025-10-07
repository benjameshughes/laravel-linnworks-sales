{{-- Example usage of chart widgets --}}
<div class="space-y-6">
    {{-- Simple Line Chart --}}
    <x-chart-widget
        type="line"
        :data="[
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            'datasets' => [
                [
                    'label' => 'Sales',
                    'data' => [12000, 19000, 15000, 25000, 22000, 30000],
                    'borderColor' => '#3B82F6',
                    'tension' => 0.4,
                ]
            ]
        ]"
        title="Monthly Sales"
        subtitle="Sales performance over time"
        icon="chart-line"
    />
    
    {{-- Bar Chart with Multiple Datasets --}}
    <x-chart-widget
        type="bar"
        :data="[
            'labels' => ['Q1', 'Q2', 'Q3', 'Q4'],
            'datasets' => [
                [
                    'label' => '2023',
                    'data' => [65000, 78000, 82000, 95000],
                    'backgroundColor' => '#3B82F6',
                ],
                [
                    'label' => '2024',
                    'data' => [72000, 85000, 90000, 0],
                    'backgroundColor' => '#10B981',
                ]
            ]
        ]"
        title="Quarterly Revenue Comparison"
        height="400px"
    />
    
    {{-- Doughnut Chart --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <x-chart-widget
            type="doughnut"
            :data="[
                'labels' => ['Desktop', 'Mobile', 'Tablet'],
                'datasets' => [
                    [
                        'data' => [68, 25, 7],
                        'backgroundColor' => ['#3B82F6', '#10B981', '#F59E0B'],
                    ]
                ]
            ]"
            title="Traffic Sources"
            height="300px"
        />
        
        {{-- Pie Chart --}}
        <x-chart-widget
            type="pie"
            :data="[
                'labels' => ['New', 'Returning', 'Referral'],
                'datasets' => [
                    [
                        'data' => [45, 35, 20],
                        'backgroundColor' => ['#8B5CF6', '#EC4899', '#14B8A6'],
                    ]
                ]
            ]"
            title="Customer Types"
            height="300px"
        />
    </div>
    
    {{-- Area Chart --}}
    <x-chart-widget
        type="area"
        :data="[
            'labels' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            'datasets' => [
                [
                    'label' => 'Page Views',
                    'data' => [3200, 4100, 3800, 5200, 4900, 6100, 5800],
                    'borderColor' => '#6366F1',
                    'backgroundColor' => 'rgba(99, 102, 241, 0.1)',
                    'fill' => true,
                ]
            ]
        ]"
        title="Weekly Page Views"
        subtitle="Traffic patterns throughout the week"
    />
    
    {{-- Mixed Chart Example --}}
    <x-chart-widget
        type="mixed"
        :data="[
            'labels' => ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            'datasets' => [
                [
                    'type' => 'bar',
                    'label' => 'Revenue',
                    'data' => [12000, 19000, 15000, 25000, 22000, 30000],
                    'backgroundColor' => 'rgba(59, 130, 246, 0.5)',
                    'yAxisID' => 'y',
                ],
                [
                    'type' => 'line',
                    'label' => 'Profit Margin %',
                    'data' => [15, 18, 14, 22, 20, 25],
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'transparent',
                    'yAxisID' => 'y1',
                ]
            ]
        ]"
        :options="[
            'scales' => [
                'y' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'left',
                    'ticks' => [
                        'callback' => 'function(value) { return \'£\' + value.toLocaleString(); }'
                    ]
                ],
                'y1' => [
                    'type' => 'linear',
                    'display' => true,
                    'position' => 'right',
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                    'ticks' => [
                        'callback' => 'function(value) { return value + \'%\'; }'
                    ]
                ],
            ]
        ]"
        title="Revenue vs Profit Margin"
        subtitle="Mixed chart with dual Y-axes"
        height="400px"
    />
    
    {{-- Inline Charts Without Container --}}
    <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-6">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100 mb-4">Direct Component Usage</h3>
        
        <livewire:charts.line-chart 
            :data="[
                'labels' => ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
                'datasets' => [
                    [
                        'label' => 'Orders',
                        'data' => [120, 150, 180, 200],
                        'borderColor' => '#EF4444',
                        'tension' => 0.4,
                    ]
                ]
            ]"
            height="200px"
        />
    </div>
    
    {{-- Custom Styled Chart --}}
    <x-chart-widget
        type="bar"
        :data="[
            'labels' => ['Product A', 'Product B', 'Product C', 'Product D', 'Product E'],
            'datasets' => [
                [
                    'label' => 'Units Sold',
                    'data' => [543, 421, 385, 298, 187],
                    'backgroundColor' => [
                        '#3B82F6',
                        '#10B981', 
                        '#F59E0B',
                        '#8B5CF6',
                        '#EF4444'
                    ],
                ]
            ]
        ]"
        :options="[
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => [
                    'display' => false
                ]
            ]
        ]"
        title="Top Products by Units Sold"
        containerClass="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-zinc-800 dark:to-zinc-900"
    />
</div>