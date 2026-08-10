<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Exceptions\Linnworks\LinnworksApiException;
use App\Services\Linnworks\Orders\ProcessedOrdersService;

uses(RefreshDatabase::class);

const PAGE_SIZE = 200;

function seedSession(int $userId = 1): void
{
    Cache::put('linnworks_session:'.$userId, [
        'token' => 'test-token',
        'server' => 'https://linnworks.test',
        'expires_at' => now()->addHour()->toISOString(),
        'user_id' => $userId,
    ], now()->addHour());
}

function ordersPage(int $count, int $totalEntries, int $totalPages): array
{
    return [
        'ProcessedOrders' => [
            'Data' => collect(range(1, $count))
                ->map(fn (int $i) => ['pkOrderID' => "order-{$i}"])
                ->all(),
            'TotalEntries' => $totalEntries,
            'TotalPages' => $totalPages,
        ],
    ];
}

function streamIds(): Generator
{
    return app(ProcessedOrdersService::class)->streamProcessedOrderIds(
        userId: 1,
        from: Carbon::parse('2026-01-01'),
        to: Carbon::parse('2026-01-31'),
    );
}

it('throws instead of silently truncating when a page fails mid-stream', function () {
    seedSession();

    $page = 0;
    Http::fake(function () use (&$page) {
        $page++;

        // first page is fine, the second one falls over
        return $page === 1
            ? Http::response(ordersPage(PAGE_SIZE, 400, 2))
            : Http::response('upstream exploded', 500);
    });

    $collected = [];

    expect(function () use (&$collected) {
        foreach (streamIds() as $ids) {
            $collected[] = $ids;
        }
    })->toThrow(LinnworksApiException::class);

    // the first page still yielded - the failure stops it, it does not pretend to finish
    expect($collected)->toHaveCount(1)
        ->and($collected[0])->toHaveCount(PAGE_SIZE);
});

it('streams every page when the api behaves', function () {
    seedSession();

    $page = 0;
    Http::fake(function () use (&$page) {
        $page++;

        return $page === 1
            ? Http::response(ordersPage(PAGE_SIZE, 250, 2))
            : Http::response(ordersPage(50, 250, 2));
    });

    $ids = collect();

    foreach (streamIds() as $pageIds) {
        $ids = $ids->merge($pageIds);
    }

    expect($ids)->toHaveCount(250);
});

it('reports the total entries through the progress callback', function () {
    seedSession();

    Http::fake(fn () => Http::response(ordersPage(10, 10, 1)));

    $seen = null;
    $stream = app(ProcessedOrdersService::class)->streamProcessedOrderIds(
        userId: 1,
        from: Carbon::parse('2026-01-01'),
        to: Carbon::parse('2026-01-31'),
        progressCallback: function ($page, $totalPages, $fetched, $totalResults) use (&$seen) {
            $seen = $totalResults;
        },
    );

    foreach ($stream as $ignored) {
        // draining the generator is what runs the callback at all
    }

    expect($seen)->toBe(10);
});
it('refuses a range larger than the cap rather than truncating it', function () {
    seedSession();

    // 500 orders exist but the caller capped at 200 - returning 200 silently
    // would look identical to "that is all there is"
    Http::fake(fn () => Http::response(ordersPage(PAGE_SIZE, 500, 3)));

    expect(fn () => app(ProcessedOrdersService::class)->getAllProcessedOrders(
        userId: 1,
        from: Carbon::parse('2026-01-01'),
        to: Carbon::parse('2026-01-31'),
        maxOrders: PAGE_SIZE,
    ))->toThrow(LinnworksApiException::class);
});

it('returns everything when the range fits inside the cap', function () {
    seedSession();

    $page = 0;
    Http::fake(function () use (&$page) {
        $page++;

        return $page === 1
            ? Http::response(ordersPage(PAGE_SIZE, 250, 2))
            : Http::response(ordersPage(50, 250, 2));
    });

    $orders = app(ProcessedOrdersService::class)->getAllProcessedOrders(
        userId: 1,
        from: Carbon::parse('2026-01-01'),
        to: Carbon::parse('2026-01-31'),
        maxOrders: 10_000,
    );

    expect($orders)->toHaveCount(250);
});
