<?php

declare(strict_types=1);

use App\Models\User;
use RuntimeException;
use App\Models\SyncLog;
use App\Jobs\SyncProductsJob;
use App\Models\LinnworksConnection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Services\Linnworks\Contracts\ProductSyncServiceInterface;

uses(RefreshDatabase::class);

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

    it('skips when no active linnworks connection exists', function () {
        $syncService = Mockery::mock(ProductSyncServiceInterface::class);
        $syncService->shouldNotReceive('syncProducts');

        $job = new SyncProductsJob;
        $job->handle($syncService);

        expect(SyncLog::count())->toBe(0);
    });

    it('calls ProductSyncService with correct parameters', function () {
        $user = User::factory()->create();
        LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $syncService = Mockery::mock(ProductSyncServiceInterface::class);
        $syncService->shouldReceive('syncProducts')
            ->once()
            ->with(
                Mockery::on(fn ($userId) => $userId === $user->id),
                ['StockLevels', 'Pricing'],
                null,
                200,
                5000,
            )
            ->andReturn([
                'synced' => 100,
                'created' => 80,
                'updated' => 20,
                'failed' => 0,
            ]);

        $job = new SyncProductsJob(startedBy: 'test', maxProducts: 5000);
        $job->handle($syncService);

        expect(SyncLog::where('sync_type', 'products')->count())->toBe(1);
    });

    it('creates a completed sync log on success', function () {
        $user = User::factory()->create();
        LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $syncService = Mockery::mock(ProductSyncServiceInterface::class);
        $syncService->shouldReceive('syncProducts')
            ->andReturn([
                'synced' => 50,
                'created' => 30,
                'updated' => 20,
                'failed' => 0,
            ]);

        $job = new SyncProductsJob(startedBy: 'test');
        $job->handle($syncService);

        $log = SyncLog::where('sync_type', 'products')->first();
        expect($log)->not->toBeNull()
            ->and($log->status)->toBe('completed')
            ->and($log->total_fetched)->toBe(50)
            ->and($log->total_created)->toBe(30)
            ->and($log->total_updated)->toBe(20);
    });

    it('lets sync failures bubble out of handle', function () {
        $user = User::factory()->create();
        LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $syncService = Mockery::mock(ProductSyncServiceInterface::class);
        $syncService->shouldReceive('syncProducts')
            ->andThrow(new RuntimeException('API connection failed'));

        $job = new SyncProductsJob(startedBy: 'test');

        expect(fn () => $job->handle($syncService))->toThrow(RuntimeException::class);

        // The log stays open mid-retry - only the final failure closes it off
        $log = SyncLog::where('sync_type', SyncLog::TYPE_PRODUCTS)->first();
        expect($log)->not->toBeNull()
            ->and($log->status)->toBe(SyncLog::STATUS_STARTED);
    });

    it('marks the active sync log as failed once retries are exhausted', function () {
        $user = User::factory()->create();
        LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $syncService = Mockery::mock(ProductSyncServiceInterface::class);
        $syncService->shouldReceive('syncProducts')
            ->andThrow(new RuntimeException('API connection failed'));

        $job = new SyncProductsJob(startedBy: 'test');

        expect(fn () => $job->handle($syncService))->toThrow(RuntimeException::class);

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

    it('respects maxProducts parameter', function () {
        $user = User::factory()->create();
        LinnworksConnection::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        $syncService = Mockery::mock(ProductSyncServiceInterface::class);
        $syncService->shouldReceive('syncProducts')
            ->once()
            ->with(
                $user->id,
                ['StockLevels', 'Pricing'],
                null,
                200,
                2000,
            )
            ->andReturn(['synced' => 0, 'created' => 0, 'updated' => 0, 'failed' => 0]);

        $job = new SyncProductsJob(maxProducts: 2000);
        $job->handle($syncService);
    });
});
