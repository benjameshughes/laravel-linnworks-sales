<?php

namespace Tests\Pest\Helpers;

use App\Services\Linnworks\Auth\AuthenticationService;
use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Orders\OpenOrdersService;
use App\Services\Linnworks\Orders\OrdersApiService;
use App\Services\Linnworks\Orders\ProcessedOrdersService;
use App\Services\Linnworks\Products\ProductsApiService;
use App\Services\LinnworksApiService;
use App\ValueObjects\Linnworks\ApiCredentials;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

abstract class LinnworksTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set([
            'linnworks.application_id' => 'test-app-id',
            'linnworks.application_secret' => 'test-secret',
            'linnworks.token' => 'test-token',
            'linnworks.base_url' => 'https://api.linnworks.net',
        ]);
    }

    protected function makeLinnworksService(): LinnworksApiService
    {
        return new LinnworksApiService(
            app(AuthenticationService::class),
            app(SessionManager::class),
            app(OrdersApiService::class),
            app(ProcessedOrdersService::class),
            app(OpenOrdersService::class),
            app(ProductsApiService::class),
            app(\App\Actions\Linnworks\Orders\FetchOrdersWithDetails::class),
            app(\App\Actions\Linnworks\Orders\CheckAndUpdateProcessedOrders::class),
            app(ApiCredentials::class)
        );
    }
}

