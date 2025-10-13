<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Linnworks\Products\InventoryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GetInventoryCountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $userId,
        private readonly int $maxRetries = 3,
    ) {
        $this->onQueue('medium');
    }

    public function handle(InventoryService $inventoryService): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::error('User not found for inventory count job', [
                'user_id' => $this->userId,
            ]);

            return;
        }

        Log::info('Starting inventory count job', [
            'user_id' => $this->userId,
            'attempt' => $this->attempts(),
        ]);

        try {
            $count = $inventoryService->getInventoryCount($this->userId);

            // Cache the count for 1 hour
            Cache::put("inventory_count_user_{$this->userId}", $count, 3600);

            Log::info('Inventory count job completed successfully', [
                'user_id' => $this->userId,
                'count' => $count,
                'attempt' => $this->attempts(),
            ]);

        } catch (\Exception $e) {
            Log::error('Inventory count job failed', [
                'user_id' => $this->userId,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            if ($this->attempts() < $this->maxRetries) {
                $this->release(60); // Retry after 1 minute
            } else {
                $this->fail($e);
            }
        }
    }

    public function retryUntil(): \DateTime
    {
        return now()->addMinutes(30);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Inventory count job failed permanently', [
            'user_id' => $this->userId,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }
}
