<?php

namespace App\Console\Commands;

use App\Services\Linnworks\Auth\SessionManager;
use App\Services\Linnworks\Core\LinnworksClient;
use App\ValueObjects\Linnworks\ApiRequest;
use Illuminate\Console\Command;

class DebugLinnworksViews extends Command
{
    protected $signature = 'linnworks:debug-views';
    protected $description = 'Debug Linnworks views API response structure';

    public function handle(
        LinnworksClient $client,
        SessionManager $sessionManager
    ): int {
        $userId = 1;

        $this->info('Fetching views from Linnworks...');

        $sessionToken = $sessionManager->getValidSessionToken($userId);

        if (!$sessionToken) {
            $this->error('No valid session token');
            return self::FAILURE;
        }

        $request = ApiRequest::get('Orders/GetOrderViews');
        $response = $client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            $this->error('Failed to fetch views: ' . $response->error);
            return self::FAILURE;
        }

        $data = $response->getData();

        $this->info('Response data count: ' . $data->count());
        $this->newLine();

        $this->line(json_encode($data->toArray(), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
