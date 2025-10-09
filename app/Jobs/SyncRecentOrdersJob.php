<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Linnworks\Orders\SyncRecentOrders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class SyncRecentOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600; // 10 minutes

    public function __construct(
        public int $openWindowDays,
        public int $processedWindowDays,
        public bool $forceUpdate,
        public ?int $userId,
    ) {
        $this->onQueue('high');
    }

    public function handle(SyncRecentOrders $syncAction): void
    {
        Log::info('SyncRecentOrdersJob: starting', [
            'user_id' => $this->userId,
            'open_window_days' => $this->openWindowDays,
            'processed_window_days' => $this->processedWindowDays,
        ]);

        $syncAction->handle(
            openWindowDays: $this->openWindowDays,
            processedWindowDays: $this->processedWindowDays,
            forceUpdate: $this->forceUpdate,
            userId: $this->userId,
        );

        Log::info('SyncRecentOrdersJob: completed', [
            'user_id' => $this->userId,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncRecentOrdersJob: failed', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
