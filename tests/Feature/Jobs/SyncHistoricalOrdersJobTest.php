<?php

use Carbon\Carbon;
use App\Jobs\SyncHistoricalOrdersJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function historicalJob(string $from = '2026-01-01', string $to = '2026-01-31'): SyncHistoricalOrdersJob
{
    return new SyncHistoricalOrdersJob(
        fromDate: Carbon::parse($from),
        toDate: Carbon::parse($to),
        startedBy: 'test',
    );
}

it('is queue-unique so two imports cannot run at once', function () {
    // uniqueId() existed before but the interface did not, so Laravel never
    // called it and overlapping imports were possible
    expect(historicalJob())->toBeInstanceOf(ShouldBeUnique::class);
});

it('locks on one key regardless of the date range', function () {
    expect(historicalJob('2026-01-01', '2026-01-31')->uniqueId())
        ->toBe(historicalJob('2020-05-05', '2020-06-06')->uniqueId());
});

it('holds the unique lock long enough for a multi-hour import', function () {
    expect(historicalJob()->uniqueFor)->toBeGreaterThanOrEqual(3600);
});

it('runs on the low queue so it cannot block recent syncs', function () {
    expect(historicalJob()->queue)->toBe('low');
});
