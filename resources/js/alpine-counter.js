// Animated Counter Component
// Usage: x-data="animatedCounter(initialValue, options)"
Alpine.data('animatedCounter', (initialValue = 0, options = {}) => ({
    displayValue: initialValue,
    targetValue: initialValue,
    isAnimating: false,

    // Configuration
    duration: options.duration || 1000, // Base duration in ms
    prefix: options.prefix || '',
    suffix: options.suffix || '',
    decimals: options.decimals ?? 0,
    separator: options.separator || ',',
    minDuration: options.minDuration || 300,
    maxDuration: options.maxDuration || 2000,

    init() {
        // Watch for updates to the target value
        this.$watch('targetValue', (newValue, oldValue) => {
            if (newValue !== oldValue) {
                this.animateTo(newValue);
            }
        });
    },

    // Update the target (call this from Livewire)
    updateValue(newValue) {
        this.targetValue = parseFloat(newValue) || 0;
    },

    animateTo(endValue) {
        if (this.isAnimating) return;

        const startValue = this.displayValue;
        const change = endValue - startValue;

        // No animation needed if values are the same
        if (change === 0) return;

        // Calculate smart duration based on difference
        const diffMagnitude = Math.abs(change);
        const calculatedDuration = Math.min(
            this.maxDuration,
            Math.max(
                this.minDuration,
                Math.log10(diffMagnitude + 1) * 200 // Logarithmic scaling
            )
        );

        this.isAnimating = true;
        const startTime = performance.now();

        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / calculatedDuration, 1);

            // Easing function: easeOutQuart for that "settling" effect
            const eased = 1 - Math.pow(1 - progress, 4);

            this.displayValue = startValue + (change * eased);

            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                this.displayValue = endValue;
                this.isAnimating = false;
            }
        };

        requestAnimationFrame(animate);
    },

    // Format the display value
    get formattedValue() {
        const num = this.displayValue;
        const fixed = num.toFixed(this.decimals);
        const parts = fixed.split('.');

        // Add thousand separators
        parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.separator);

        const formatted = parts.join('.');
        return this.prefix + formatted + this.suffix;
    }
}));

// Currency Counter - Specialized for money
Alpine.data('currencyCounter', (initialValue = 0, currency = '£') => ({
    ...Alpine.raw(Alpine.data('animatedCounter')(initialValue, {
        prefix: currency,
        decimals: 2,
        separator: ',',
        minDuration: 400,
        maxDuration: 1500
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
Alpine.data('integerCounter', (initialValue = 0) => ({
    ...Alpine.raw(Alpine.data('animatedCounter')(initialValue, {
        decimals: 0,
        separator: ',',
        minDuration: 300,
        maxDuration: 1200
    }))
}));
