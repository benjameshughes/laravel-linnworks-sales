// Animated Counter Component with number ticking
// Usage: x-data="animatedCounter(initialValue, options)"
document.addEventListener('alpine:init', () => {

Alpine.data('animatedCounter', (initialValue = 0, options = {}) => ({
    current: initialValue,
    target: initialValue,
    storeKey: options.storeKey || null,

    // Configuration
    prefix: options.prefix || '',
    suffix: options.suffix || '',
    decimals: options.decimals ?? 0,
    separator: options.separator || ',',
    duration: options.duration || 1000, // Duration for animation

    init() {
        // If we have a storeKey, check if there's a previous value to animate from
        if (this.storeKey && this.$store.metrics[this.storeKey] !== 0) {
            // Start from the stored value and animate to new value
            this.current = this.$store.metrics[this.storeKey];
            this.animateCounter(initialValue);
        }

        // Update store with current value
        if (this.storeKey) {
            this.$store.metrics[this.storeKey] = initialValue;
        }
    },

    // Update the target value and animate
    updateValue(newValue) {
        const target = parseFloat(newValue) || 0;
        this.animateCounter(target);
    },

    // Animate counter from current to target
    animateCounter(targetValue) {
        this.target = targetValue;
        const start = this.current;
        const change = targetValue - start;

        // No animation needed if values are the same
        if (change === 0) return;

        // Calculate duration based on magnitude of change (faster for small changes)
        const magnitude = Math.abs(change);
        const calculatedDuration = Math.min(2000, Math.max(300, Math.log10(magnitude + 1) * 400));

        const increment = change / calculatedDuration;
        const startTime = performance.now();

        const animate = () => {
            const elapsed = performance.now() - startTime;

            if (elapsed < calculatedDuration) {
                this.current = start + (change * (elapsed / calculatedDuration));
                requestAnimationFrame(animate);
            } else {
                this.current = targetValue;
                // Update store with final value
                if (this.storeKey) {
                    this.$store.metrics[this.storeKey] = targetValue;
                }
            }
        };

        requestAnimationFrame(animate);
    },

    // Format the current value
    get formattedValue() {
        const num = this.current;
        const fixed = num.toFixed(this.decimals);
        const parts = fixed.split('.');

        // Add thousand separators
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.separator);

        const formatted = parts.join('.');
        return this.prefix + formatted + this.suffix;
    }
}));

// Currency Counter - Specialized for money
Alpine.data('currencyCounter', (initialValue = 0, currency = '£', storeKey = null) => ({
    ...Alpine.raw(Alpine.data('animatedCounter')(initialValue, {
        prefix: currency,
        decimals: 2,
        separator: ',',
        minDuration: 400,
        maxDuration: 1500,
        storeKey: storeKey
    }))
}));

// Percentage Counter - Specialized for percentages
Alpine.data('percentageCounter', (initialValue = 0) => ({
    ...Alpine.raw(Alpine.data('animatedCounter')(initialValue, {
        suffix: '%',
        decimals: 1,
        separator: ',',
        minDuration: 300,
        maxDuration: 1000
    }))
}));

// Integer Counter - Specialized for whole numbers (orders, items, etc)
Alpine.data('integerCounter', (initialValue = 0, storeKey = null) => ({
    ...Alpine.raw(Alpine.data('animatedCounter')(initialValue, {
        decimals: 0,
        separator: ',',
        minDuration: 300,
        maxDuration: 1200,
        storeKey: storeKey
    }))
}));

}); // End alpine:init
