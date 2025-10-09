<?php

declare(strict_types=1);

namespace App\Jobs\Middleware;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class RateLimitLinnworks
{
    /**
     * Process the queued job.
     *
     * @param  mixed  $job
     * @param  callable  $next
     * @return mixed
     */
    public function handle($job, $next)
    {
        // Linnworks API rate limit: 150 requests per minute
        // We'll throttle to 120 requests per minute to be safe
        $rateLimitKey = 'linnworks-api-rate-limit';
        $maxRequests = 120;
        $windowSeconds = 60;

        // Get current request count for this window
        $currentCount = (int) Cache::get($rateLimitKey, 0);

        if ($currentCount >= $maxRequests) {
            // Calculate remaining time in current window
            $cacheExpiry = Cache::get($rateLimitKey . ':expiry');
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
            Cache::put($rateLimitKey . ':expiry', $expiryTime, $windowSeconds);
        } else {
            // Just increment without changing expiry
            Cache::put($rateLimitKey, $newCount, $windowSeconds);
        }

        return $next($job);
    }
}
