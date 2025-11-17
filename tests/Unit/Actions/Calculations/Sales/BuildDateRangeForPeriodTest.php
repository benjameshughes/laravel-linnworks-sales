<?php

declare(strict_types=1);

use App\Actions\Calculations\Sales\BuildDateRangeForPeriod;
use Carbon\Carbon;

beforeEach(function () {
    Carbon::setTestNow('2025-01-15 14:30:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

describe('BuildDateRangeForPeriod', function () {
    it('returns 3 points for today period', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('0');

        expect($result)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(3)
            ->and($result[0]->format('Y-m-d'))
            ->toBe('2025-01-14') // Yesterday
            ->and($result[1]->format('Y-m-d'))
            ->toBe('2025-01-15') // Today
            ->and($result[2]->format('Y-m-d'))
            ->toBe('2025-01-16'); // Tomorrow
    });

    it('returns 3 points for yesterday period', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('1');

        expect($result)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(3)
            ->and($result[0]->format('Y-m-d'))
            ->toBe('2025-01-13') // Day before yesterday
            ->and($result[1]->format('Y-m-d'))
            ->toBe('2025-01-14') // Yesterday
            ->and($result[2]->format('Y-m-d'))
            ->toBe('2025-01-15'); // Today
    });

    it('returns correct range for 7 day period', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('7');

        expect($result)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(7)
            ->and($result->first()->format('Y-m-d'))
            ->toBe('2025-01-09')
            ->and($result->last()->format('Y-m-d'))
            ->toBe('2025-01-15');
    });

    it('returns correct range for 30 day period', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('30');

        expect($result)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(30)
            ->and($result->first()->format('Y-m-d'))
            ->toBe('2024-12-17')
            ->and($result->last()->format('Y-m-d'))
            ->toBe('2025-01-15');
    });

    it('returns correct range for custom period', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('custom', '2025-01-01', '2025-01-05');

        expect($result)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(5)
            ->and($result->first()->format('Y-m-d'))
            ->toBe('2025-01-01')
            ->and($result->last()->format('Y-m-d'))
            ->toBe('2025-01-05');
    });

    it('returns single date for custom period with same start and end', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('custom', '2025-01-15', '2025-01-15');

        expect($result)
            ->toBeInstanceOf(\Illuminate\Support\Collection::class)
            ->toHaveCount(1)
            ->and($result->first()->format('Y-m-d'))
            ->toBe('2025-01-15');
    });

    it('returns dates in chronological order', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('7');

        $dates = $result->map(fn ($date) => $date->timestamp)->toArray();
        $sortedDates = $dates;
        sort($sortedDates);

        expect($dates)->toBe($sortedDates);
    });

    it('all returned dates are Carbon instances', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('7');

        $result->each(function ($date) {
            expect($date)->toBeInstanceOf(Carbon::class);
        });
    });

    it('handles minimum of 1 day for numeric periods', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('1');

        expect($result)->toHaveCount(3); // Yesterday has 3 points
    });

    it('custom period handles multi-month range', function () {
        $action = new BuildDateRangeForPeriod;
        $result = $action('custom', '2025-01-01', '2025-03-01');

        expect($result)
            ->toHaveCount(60) // Jan (31) + Feb (28) + 1 day of March
            ->and($result->first()->format('Y-m-d'))
            ->toBe('2025-01-01')
            ->and($result->last()->format('Y-m-d'))
            ->toBe('2025-03-01');
    });
});
