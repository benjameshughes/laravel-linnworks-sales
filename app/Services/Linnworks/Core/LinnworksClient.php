<?php

namespace App\Services\Linnworks\Core;

use App\Exceptions\Linnworks\LinnworksApiException;
use App\ValueObjects\Linnworks\ApiRequest;
use App\ValueObjects\Linnworks\ApiResponse;
use App\ValueObjects\Linnworks\SessionToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LinnworksClient
{
    private const BASE_URL = 'https://api.linnworks.net/api/';

    private const CACHE_PREFIX = 'linnworks_response:';

    private const CACHE_TTL = 900; // 15 minutes

    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly int $timeout = 30,
        private readonly bool $enableCaching = true,
    ) {}

    /**
     * Make an API request with rate limiting and error handling
     *
     * @throws LinnworksApiException
     */
    public function makeRequest(ApiRequest $request, ?SessionToken $sessionToken = null): ApiResponse
    {
        // Check cache first
        if ($this->enableCaching && $request->method === 'GET') {
            $cachedResponse = $this->getCachedResponse($request);
            if ($cachedResponse !== null) {
                return $cachedResponse;
            }
        }

        // Check rate limits
        if (! $this->rateLimiter->canMakeRequest()) {
            Log::warning('Linnworks rate limit exceeded, waiting for reset', [
                'endpoint' => $request->endpoint,
                'current_requests' => $this->rateLimiter->getCurrentRequestCount(),
            ]);

            $this->rateLimiter->waitForReset();
        }

        try {
            // Record request for rate limiting
            $this->rateLimiter->recordRequest();

            // Make HTTP request
            $startTime = microtime(true);
            $response = $this->executeHttpRequest($request, $sessionToken);
            $duration = microtime(true) - $startTime;

            // Check for HTTP errors
            $this->validateResponse($response, $request, $duration);

            // Create API response
            $apiResponse = ApiResponse::fromHttpResponse($response);

            // Cache successful GET requests
            if ($this->enableCaching && $request->method === 'GET' && $apiResponse->isSuccess()) {
                $this->cacheResponse($request, $apiResponse);
            }

            // Log request details
            $this->logRequest($request, $apiResponse);

            return $apiResponse;

        } catch (ConnectionException $e) {
            throw LinnworksApiException::timeout(
                endpoint: $request->endpoint,
                duration: $duration ?? 0
            );
        } catch (RequestException $e) {
            throw LinnworksApiException::fromResponse(
                response: $e->response,
                message: "Linnworks API request to {$request->endpoint} failed"
            );
        } catch (LinnworksApiException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('Unexpected error during Linnworks API request', [
                'endpoint' => $request->endpoint,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw new LinnworksApiException(
                message: "Unexpected error: {$e->getMessage()}",
                context: ['endpoint' => $request->endpoint],
                code: 500,
                previous: $e
            );
        }
    }

    /**
     * Make multiple requests with proper batching and rate limiting
     */
    public function makeBatchRequests(array $requests, ?SessionToken $sessionToken = null): array
    {
        $responses = [];
        $totalRequests = count($requests);

        Log::info('Linnworks batch request started', [
            'total_requests' => $totalRequests,
            'rate_limit_remaining' => $this->rateLimiter->getRemainingRequests(),
        ]);

        foreach ($requests as $key => $request) {
            $responses[$key] = $this->makeRequest($request, $sessionToken);

            // Log progress for large batches
            if ($totalRequests > 10 && ($key + 1) % 10 === 0) {
                Log::info('Linnworks batch progress', [
                    'completed' => $key + 1,
                    'total' => $totalRequests,
                    'rate_limit_remaining' => $this->rateLimiter->getRemainingRequests(),
                ]);
            }
        }

        return $responses;
    }

    /**
     * Execute HTTP request with proper headers and error handling
     */
    private function executeHttpRequest(ApiRequest $request, ?SessionToken $sessionToken = null): Response
    {
        $baseUrl = $sessionToken ? $sessionToken->getBaseUrl() : self::BASE_URL;
        $url = $baseUrl.ltrim($request->endpoint, '/');

        // Build headers
        $headers = $request->headers->toArray();

        if ($request->requiresAuth && $sessionToken) {
            $headers = array_merge($headers, $sessionToken->getAuthHeaders());
        }

        // Make HTTP request
        $httpClient = Http::timeout($request->timeout)
            ->withHeaders($headers);

        // Apply JSON mode if requested
        if ($request->asJson) {
            $httpClient = $httpClient->asJson();
        }

        return match ($request->method) {
            'GET' => $httpClient->get($url, $request->parameters->toArray()),
            'POST' => $httpClient->post($url, $request->parameters->toArray()),
            'PUT' => $httpClient->put($url, $request->parameters->toArray()),
            'DELETE' => $httpClient->delete($url, $request->parameters->toArray()),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$request->method}"),
        };
    }

    /**
     * Get cached response if available
     */
    private function getCachedResponse(ApiRequest $request): ?ApiResponse
    {
        $cacheKey = self::CACHE_PREFIX.$request->getCacheKey();
        $cached = Cache::get($cacheKey);

        if ($cached) {
            Log::debug('Linnworks cached response hit', [
                'endpoint' => $request->endpoint,
                'cache_key' => $cacheKey,
            ]);

            return new ApiResponse(
                data: collect($cached['data']),
                statusCode: $cached['status_code'],
                headers: collect($cached['headers']),
                error: $cached['error'],
                meta: $cached['meta'] ? collect($cached['meta']) : null,
                requestedAt: \Carbon\Carbon::parse($cached['requested_at']),
            );
        }

        return null;
    }

    /**
     * Cache API response
     */
    private function cacheResponse(ApiRequest $request, ApiResponse $response): void
    {
        $cacheKey = self::CACHE_PREFIX.$request->getCacheKey();

        Cache::put($cacheKey, $response->jsonSerialize(), self::CACHE_TTL);

        Log::debug('Linnworks response cached', [
            'endpoint' => $request->endpoint,
            'cache_key' => $cacheKey,
            'ttl' => self::CACHE_TTL,
        ]);
    }

    /**
     * Log request details
     */
    private function logRequest(ApiRequest $request, ApiResponse $response): void
    {
        Log::info('Linnworks API request completed', [
            'endpoint' => $request->endpoint,
            'method' => $request->method,
            'status_code' => $response->statusCode,
            'has_data' => $response->hasData(),
            'data_count' => $response->hasData() ? $response->data->count() : 0,
            'rate_limit_remaining' => $this->rateLimiter->getRemainingRequests(),
        ]);
    }

    /**
     * Validate HTTP response and throw appropriate exceptions
     *
     * @throws LinnworksApiException
     */
    private function validateResponse(Response $response, ApiRequest $request, float $duration): void
    {
        // Check for rate limiting
        if ($response->status() === 429) {
            throw LinnworksApiException::rateLimited($response);
        }

        // Check for authentication failures
        if ($response->status() === 401) {
            throw LinnworksApiException::fromResponse(
                response: $response,
                message: "Authentication failed for {$request->endpoint} - session may have expired"
            );
        }

        // Check for server errors (5xx)
        if ($response->serverError()) {
            throw LinnworksApiException::fromResponse(
                response: $response,
                message: "Linnworks API server error for {$request->endpoint}"
            );
        }

        // Check for client errors (4xx) - excluding 404 which might be expected
        if ($response->clientError() && $response->status() !== 404) {
            throw LinnworksApiException::fromResponse(
                response: $response,
                message: "Linnworks API client error for {$request->endpoint}"
            );
        }
    }

    /**
     * Clear cache for specific endpoint or all cached responses
     */
    public function clearCache(?string $endpoint = null): void
    {
        if ($endpoint) {
            $request = ApiRequest::get($endpoint);
            $cacheKey = self::CACHE_PREFIX.$request->getCacheKey();
            Cache::forget($cacheKey);
        } else {
            // Clear all cached responses (pattern-based)
            Cache::flush(); // In production, you'd want a more targeted approach
        }
    }

    /**
     * Get rate limiter instance
     */
    public function getRateLimiter(): RateLimiter
    {
        return $this->rateLimiter;
    }

    /**
     * Get client statistics
     */
    public function getStats(): array
    {
        return [
            'rate_limiter' => $this->rateLimiter->getStats(),
            'caching_enabled' => $this->enableCaching,
            'default_timeout' => $this->timeout,
        ];
    }
}
