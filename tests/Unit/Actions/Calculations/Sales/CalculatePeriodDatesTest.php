<?php

declare(strict_types=1);

use App\Actions\Calculations\Sales\CalculatePeriodDates;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-01-15 14:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('CalculatePeriodDates', function () {
    it('calculates today period correctly', function () {
        $action = new CalculatePeriodDates;
        $result = $action('0');

        expect($result)
            ->toBeArray()
            ->toHaveKeys(['start', 'end', 'days'])
            ->and($result['start'])
            ->toBeInstanceOf(Carbon::class)
            ->and($result['start']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-15 00:00:00')
            ->and($result['end'])
            ->toBeInstanceOf(Carbon::class)
            ->and($result['end']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-15 14:30:00')
            ->and($result['days'])
            ->toBe(1);
    });

    it('calculates yesterday period correctly', function () {
        $action = new CalculatePeriodDates;
        $result = $action('1');

        expect($result)
            ->toBeArray()
            ->toHaveKeys(['start', 'end', 'days'])
            ->and($result['start'])
            ->toBeInstanceOf(Carbon::class)
            ->and($result['start']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-14 00:00:00')
            ->and($result['end'])
            ->toBeInstanceOf(Carbon::class)
            ->and($result['end']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-14 23:59:59')
            ->and($result['days'])
            ->toBe(1);
    });

    it('calculates 7 day period correctly', function () {
        $action = new CalculatePeriodDates;
        $result = $action('7');

        expect($result)
            ->toBeArray()
            ->toHaveKeys(['start', 'end', 'days'])
            ->and($result['start'])
            ->toBeInstanceOf(Carbon::class)
            ->and($result['start']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-08 00:00:00')
            ->and($result['end'])
            ->toBeInstanceOf(Carbon::class)
            ->and($result['end']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-15 14:30:00')
            ->and($result['days'])
            ->toBe(7);
    });

    it('calculates 30 day period correctly', function () {
        $action = new CalculatePeriodDates;
        $result = $action('30');

        expect($result)
            ->toBeArray()
            ->and($result['start']->format('Y-m-d'))
            ->toBe('2024-12-16')
            ->and($result['end']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-15 14:30:00')
            ->and($result['days'])
            ->toBe(30);
    });

    it('calculates 90 day period correctly', function () {
        $action = new CalculatePeriodDates;
        $result = $action('90');

        expect($result)
            ->toBeArray()
            ->and($result['start']->format('Y-m-d'))
            ->toBe('2024-10-17')
            ->and($result['end']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-15 14:30:00')
            ->and($result['days'])
            ->toBe(90);
    });

    it('calculates custom period correctly', function () {
        $action = new CalculatePeriodDates;
        $result = $action('custom', '2025-01-01', '2025-01-10');

        expect($result)
            ->toBeArray()
            ->and($result['start']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-01 00:00:00')
            ->and($result['end']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-10 23:59:59')
            ->and($result['days'])
            ->toBeGreaterThanOrEqual(10)
            ->and($result['days'])
            ->toBeLessThanOrEqual(11);
    });

    it('calculates custom period with same start and end date', function () {
        $action = new CalculatePeriodDates;
        $result = $action('custom', '2025-01-15', '2025-01-15');

        expect($result)
            ->toBeArray()
            ->and($result['start']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-15 00:00:00')
            ->and($result['end']->format('Y-m-d H:i:s'))
            ->toBe('2025-01-15 23:59:59')
            ->and($result['days'])
            ->toBeGreaterThanOrEqual(1)
            ->and($result['days'])
            ->toBeLessThanOrEqual(2);
    });

    it('handles custom period without custom dates by falling back to default', function () {
        $action = new CalculatePeriodDates;
        $result = $action('custom');

        expect($result)
            ->toBeArray()
            ->and($result['days'])
            ->toBeGreaterThanOrEqual(1);
    });

    it('ensures minimum of 1 day for custom period', function () {
        $action = new CalculatePeriodDates;
        // Same day should still result in at least 1 day
        $result = $action('custom', '2025-01-15 10:00:00', '2025-01-15 11:00:00');

        expect($result['days'])->toBeGreaterThanOrEqual(1);
    });

    it('ensures minimum of 1 day for numeric periods', function () {
        $action = new CalculatePeriodDates;
        $result = $action('0');

        expect($result['days'])->toBe(1);
    });

    it('handles invalid numeric period gracefully', function () {
        $action = new CalculatePeriodDates;
        $result = $action('-5'); // Negative should be converted to 1

        expect($result['days'])->toBe(1);
    });

    it('handles zero period gracefully', function () {
        $action = new CalculatePeriodDates;
        $result = $action('0');

        expect($result)
            ->toBeArray()
            ->and($result['days'])
            ->toBe(1);
    });
});
