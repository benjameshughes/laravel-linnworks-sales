<?php

declare(strict_types=1);

namespace App\Services\Linnworks\Orders;

use App\Models\LinnworksView;
use App\Services\Linnworks\Core\LinnworksClient;
use App\Services\Linnworks\Auth\SessionManager;
use App\ValueObjects\Linnworks\ApiRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

final class ViewsService
{
    public function __construct(
        private readonly LinnworksClient $client,
        private readonly SessionManager $sessionManager,
    ) {}

    public function getOpenOrderViews(int $userId): Collection
    {
        $sessionToken = $this->sessionManager->getValidSessionToken($userId);

        if (!$sessionToken) {
            Log::error('No valid session token for open order views request', ['user_id' => $userId]);
            return collect();
        }

        $request = ApiRequest::get('Orders/GetOrderViews');
        $response = $this->client->makeRequest($request, $sessionToken);

        if ($response->isError()) {
            Log::warning('Failed to fetch Linnworks open order views', [
                'user_id' => $userId,
                'error' => $response->error,
                'status' => $response->statusCode,
            ]);

            return LinnworksView::forUser($userId)
                ->get()
                ->map(fn (LinnworksView $view) => $view->metadata ?? [
                    'ViewId' => $view->view_id,
                    'Name' => $view->name,
                ]);
        }

        $views = $response->getData()->map(fn ($view) => is_array($view) ? $view : (array) $view);

        $views->each(function (array $view) use ($userId) {
            $viewId = $view['pkViewId'] ?? $view['ViewId'] ?? $view['Id'] ?? null;

            if ($viewId === null) {
                return;
            }

            LinnworksView::updateOrCreate(
                ['user_id' => $userId, 'view_id' => (int) $viewId],
                [
                    'name' => $view['ViewName'] ?? $view['Name'] ?? 'Unnamed View',
                    'is_default' => (bool) ($view['IsDefault'] ?? false),
                    'metadata' => $view,
                ]
            );
        });

        return $views;
    }

    public function getDefaultView(int $userId): ?array
    {
        $default = LinnworksView::forUser($userId)
            ->where('is_default', true)
            ->first();

        if ($default) {
            return $default->metadata ?? [
                'ViewId' => $default->view_id,
                'Name' => $default->name,
            ];
        }

        $first = LinnworksView::forUser($userId)->first();

        if ($first) {
            return $first->metadata ?? [
                'ViewId' => $first->view_id,
                'Name' => $first->name,
            ];
        }

        $views = $this->getOpenOrderViews($userId);

        return $views->first();
    }
}
