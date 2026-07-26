<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

final class RateLimitLinnworks
{
    /**
     * Linnworks allows 150 requests per minute - we stay under it deliberately
     * so a burst never trips their limit.
     */
    private const MAX_REQUESTS_PER_WINDOW = 120;

    private const WINDOW_SECONDS = 60;

    /**
     * Process the queued job.
     */
    public function handle(mixed $job, callable $next): mixed
    {
        $rateLimitKey = 'linnworks-api-rate-limit';
        $maxRequests = self::MAX_REQUESTS_PER_WINDOW;
        $windowSeconds = self::WINDOW_SECONDS;

        // Get current request count for this window
        $currentCount = (int) Cache::get($rateLimitKey, 0);

        if ($currentCount >= $maxRequests) {
            // Calculate remaining time in current window
            $cacheExpiry = Cache::get($rateLimitKey.':expiry');
            $waitTime = $cacheExpiry ? max(1, $cacheExpiry - time()) : 1;

            Log::info('Linnworks API rate limit reached, releasing job back to queue', [
                'job' => get_class($job),
                'current_count' => $currentCount,
                'max_requests' => $maxRequests,
                'wait_time' => $waitTime,
            ]);

            // Release the job back to the queue with delay
            return $job->release($waitTime);
        }

        // Increment the counter
        $newCount = $currentCount + 1;

        // Set expiry on first request of the window
        if ($currentCount === 0) {
            $expiryTime = time() + $windowSeconds;
            Cache::put($rateLimitKey, $newCount, $windowSeconds);
            Cache::put($rateLimitKey.':expiry', $expiryTime, $windowSeconds);
        } else {
            // Just increment without changing expiry
            Cache::put($rateLimitKey, $newCount, $windowSeconds);
        }

        return $next($job);
    }
}
