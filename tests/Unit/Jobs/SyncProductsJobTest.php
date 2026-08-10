<?php

declare(strict_types=1);

use App\Models\User;
use RuntimeException;
use App\Models\SyncLog;
use App\Jobs\SyncProductsJob;
use App\Models\LinnworksConnection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use App\Actions\Linnworks\SyncProducts;
use BenHughes\Linnworks\Exceptions\ApiException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function linnworksConnection(): void
{
    $user = User::factory()->create();
    LinnworksConnection::factory()->create(['user_id' => $user->id, 'is_active' => true]);
    Cache::put('linnworks_session_token', 'test-token', now()->addHour());
    Cache::put('linnworks_session_token_server', 'https://eu-ext.linnworks.test/api/', now()->addHour());
}

function fakeStockPage(array $items): void
{
    Http::fake(fn () => Http::response($items));
}

describe('SyncProductsJob', function () {
    it('implements ShouldQueue interface', function () {
        $job = new SyncProductsJob;

        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class);
    });

    it('implements ShouldBeUnique interface', function () {
        $job = new SyncProductsJob;

        expect($job)->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeUnique::class);
    });

    it('has correct queue configuration', function () {
        $job = new SyncProductsJob;

        expect($job->uniqueFor)->toBe(3600)
            ->and($job->tries)->toBe(3)
            ->and($job->timeout)->toBe(900)
            ->and($job->queue)->toBe('medium');
    });

    it('has unique id for deduplication', function () {
        $job = new SyncProductsJob;

        expect($job->uniqueId())->toBe('sync-products');
    });

    it('stores startedBy and maxProducts as readonly properties', function () {
        $job = new SyncProductsJob(startedBy: 'dashboard', maxProducts: 5000);

        expect($job->startedBy)->toBe('dashboard')
            ->and($job->maxProducts)->toBe(5000);
    });

    it('refuses to run without an active linnworks connection', function () {
        Http::fake();

        // the client binding needs credentials, so this fails before any work
        expect(fn () => (new SyncProductsJob)->handle(app(SyncProducts::class)))
            ->toThrow(RuntimeException::class);

        Http::assertNothingSent();
    });

    it('asks Linnworks for stock levels and pricing', function () {
        linnworksConnection();
        fakeStockPage([]);

        (new SyncProductsJob(startedBy: 'test'))->handle(app(SyncProducts::class));

        // form encoding turns the array into a json string on the way out
        Http::assertSent(fn ($r) => str_contains($r->url(), 'Stock/GetStockItemsFull')
            && $r['dataRequirements'] === json_encode(['StockLevels', 'Pricing']));
    });

    it('creates a completed sync log on success', function () {
        linnworksConnection();
        fakeStockPage([['SKU' => 'ABC-1', 'ItemTitle' => 'Thing', 'StockItemId' => 'x-1']]);

        (new SyncProductsJob(startedBy: 'test'))->handle(app(SyncProducts::class));

        $log = SyncLog::where('sync_type', 'products')->first();
        expect($log->status)->toBe('completed')
            ->and($log->total_fetched)->toBe(1)
            ->and($log->total_created)->toBe(1);
    });

    it('lets sync failures bubble out of handle', function () {
        linnworksConnection();
        Http::fake(fn () => Http::response('upstream exploded', 400));

        $job = new SyncProductsJob(startedBy: 'test');

        expect(fn () => $job->handle(app(SyncProducts::class)))->toThrow(ApiException::class);

        // The log stays open mid-retry - only the final failure closes it off
        $log = SyncLog::where('sync_type', SyncLog::TYPE_PRODUCTS)->first();
        expect($log)->not->toBeNull()
            ->and($log->status)->toBe(SyncLog::STATUS_STARTED);
    });

    it('marks the active sync log as failed once retries are exhausted', function () {
        linnworksConnection();
        Http::fake(fn () => Http::response('upstream exploded', 400));

        $job = new SyncProductsJob(startedBy: 'test');

        expect(fn () => $job->handle(app(SyncProducts::class)))->toThrow(ApiException::class);

        $job->failed(new RuntimeException('API connection failed'));

        $log = SyncLog::where('sync_type', SyncLog::TYPE_PRODUCTS)->first();
        expect($log->fresh()->status)->toBe(SyncLog::STATUS_FAILED);
    });

    it('survives a permanent failure when no sync log was ever opened', function () {
        $job = new SyncProductsJob(startedBy: 'test');

        $job->failed(new RuntimeException('died before starting'));

        expect(SyncLog::count())->toBe(0);
    });

    it('can be dispatched to the queue', function () {
        Queue::fake();

        SyncProductsJob::dispatch(startedBy: 'test');

        Queue::assertPushed(SyncProductsJob::class, function ($job) {
            return $job->startedBy === 'test' && $job->queue === 'medium';
        });
    });

});
