// Alpine Store for Metrics - persists counter values across Livewire re-renders
// This allows counters to remember their previous value and animate smoothly
document.addEventListener('alpine:init', () => {
    Alpine.store('metrics', {
        revenue: 0,
        orders: 0,
        avgOrder: 0,
        items: 0,
        ordersPerDay: 0,
        bestDayRevenue: 0,
        bestDayOrders: 0,
    });
});
