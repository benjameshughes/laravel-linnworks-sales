<?php

namespace App\Livewire\Settings;

use Throwable;
use Livewire\Component;
use App\Models\LinnworksView;
use App\Models\LinnworksLocation;
use Livewire\Attributes\Computed;
use Illuminate\Contracts\View\View;
use App\Services\LinnworksOAuthService;
use App\Services\Linnworks\Orders\ViewsService;
use App\Exceptions\Linnworks\LinnworksApiException;
use App\Services\Linnworks\Orders\LocationsService;
use App\Exceptions\Linnworks\AuthenticationException;

final class LinnworksSettings extends Component
{
    private ?LinnworksOAuthService $linnworksOAuthService = null;

    private ?LocationsService $locationsService = null;

    private ?ViewsService $viewsService = null;

    /**
     * Livewire cannot inject through the constructor, so dependencies are
     * resolved here - boot() runs on every request, mount and hydrate alike.
     */
    public function boot(LinnworksOAuthService $linnworksOAuthService, LocationsService $locationsService, ViewsService $viewsService): void
    {
        $this->linnworksOAuthService = $linnworksOAuthService;
        $this->locationsService = $locationsService;
        $this->viewsService = $viewsService;
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function linnworksOAuthService(): LinnworksOAuthService
    {
        return $this->linnworksOAuthService ??= app(LinnworksOAuthService::class);
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function locationsService(): LocationsService
    {
        return $this->locationsService ??= app(LocationsService::class);
    }

    /**
     * Livewire skips boot() on the lazy-load request, so always reach the
     * dependency through here rather than the property directly.
     */
    private function viewsService(): ViewsService
    {
        return $this->viewsService ??= app(ViewsService::class);
    }

    public string $applicationId = '';

    public string $applicationSecret = '';

    public string $accessToken = '';

    public bool $showForm = false;

    public array $availableLocations = [];

    public array $availableViews = [];

    public ?string $selectedLocation = null;

    public ?int $selectedView = null;

    public bool $isRefreshingSources = false;

    public function mount(LinnworksOAuthService $oauthService): void
    {
        $this->checkConnectionStatus();
        $this->loadPreferences();
    }

    #[Computed]
    public function connectionStatus()
    {
        $oauthService = $this->linnworksOAuthService();

        return $oauthService->getConnectionStatus(auth()->id());
    }

    public function showConnectionForm(): void
    {
        $this->showForm = true;
        $this->reset(['applicationId', 'applicationSecret', 'accessToken']);
    }

    public function hideConnectionForm(): void
    {
        $this->showForm = false;
        $this->reset(['applicationId', 'applicationSecret', 'accessToken']);
    }

    public function connect(): void
    {
        $this->validate([
            'applicationId' => 'required|string',
            'applicationSecret' => 'required|string',
            'accessToken' => 'required|string',
        ]);

        try {
            $oauthService = $this->linnworksOAuthService();
            $connection = $oauthService->createConnection(
                auth()->id(),
                $this->applicationId,
                $this->applicationSecret,
                $this->accessToken
            );

            if ($oauthService->testConnection($connection)) {
                session()->flash('success', 'Successfully connected to Linnworks!');
                $this->showForm = false;
                $this->reset(['applicationId', 'applicationSecret', 'accessToken']);
                $this->loadPreferences();
            } else {
                // Get more detailed error info
                $status = $oauthService->getConnectionStatus(auth()->id());
                session()->flash('error', 'Connection created but test failed: '.($status['message'] ?? 'Unknown error'));
            }
        } catch (LinnworksApiException|AuthenticationException $e) {
            session()->flash('error', $e->getUserMessage());
        } catch (\Exception $e) {
            session()->flash('error', 'An unexpected error occurred. Please try again or contact support.');
            \Log::error('Linnworks connection error', [
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function disconnect(): void
    {
        $oauthService = $this->linnworksOAuthService();

        if ($oauthService->disconnect(auth()->id())) {
            session()->flash('success', 'Successfully disconnected from Linnworks.');
            $this->availableLocations = [];
            $this->availableViews = [];
            $this->selectedLocation = null;
            $this->selectedView = null;
        } else {
            session()->flash('error', 'Failed to disconnect from Linnworks.');
        }
    }

    public function testConnection(): void
    {
        $oauthService = $this->linnworksOAuthService();
        $connection = $oauthService->getActiveConnection(auth()->id());

        if (! $connection) {
            session()->flash('error', 'No active connection found.');

            return;
        }

        // Add debugging info
        \Log::info('Testing connection', [
            'user_id' => auth()->id(),
            'connection_id' => $connection->id,
            'has_session_token' => ! empty($connection->session_token),
            'has_server_location' => ! empty($connection->server_location),
            'session_expires_at' => $connection->session_expires_at?->toDateTimeString(),
            'needs_refresh' => $connection->needs_new_session,
        ]);

        if ($oauthService->testConnection($connection)) {
            session()->flash('success', 'Connection test successful!');
        } else {
            $status = $oauthService->getConnectionStatus(auth()->id());
            session()->flash('error', 'Test failed: '.($status['message'] ?? 'Unknown error'));
        }
    }

    public function refreshSession(): void
    {
        $oauthService = $this->linnworksOAuthService();
        $connection = $oauthService->getActiveConnection(auth()->id());

        if (! $connection) {
            session()->flash('error', 'No active connection found.');

            return;
        }

        \Log::info('Refreshing session', [
            'user_id' => auth()->id(),
            'connection_id' => $connection->id,
            'current_session_token' => substr($connection->session_token ?? '', 0, 10).'...',
            'access_token' => substr($connection->access_token, 0, 10).'...',
        ]);

        if ($oauthService->refreshSession($connection)) {
            session()->flash('success', 'Session refreshed successfully!');
            $this->loadPreferences();
        } else {
            session()->flash('error', 'Failed to refresh session. Check logs for details.');
        }
    }

    private function checkConnectionStatus(): void
    {
        // This will trigger the computed property to refresh
        unset($this->connectionStatus);
    }

    private function loadPreferences(): void
    {
        $userId = auth()->id();

        if (! $userId) {
            $this->availableLocations = [];
            $this->availableViews = [];
            $this->selectedLocation = null;
            $this->selectedView = null;

            return;
        }

        $oauthService = $this->linnworksOAuthService();
        $connection = $oauthService->getActiveConnection($userId);

        $locations = LinnworksLocation::forUser($userId)->orderBy('name')->get();

        if ($locations->isEmpty() && $connection) {
            $this->locationsService()->getLocations($userId);
            $locations = LinnworksLocation::forUser($userId)->orderBy('name')->get();
        }

        $views = LinnworksView::forUser($userId)->orderBy('name')->get();

        if ($views->isEmpty() && $connection) {
            $this->viewsService()->getOpenOrderViews($userId);
            $views = LinnworksView::forUser($userId)->orderBy('name')->get();
        }

        $this->availableLocations = $locations->map(function (LinnworksLocation $location) {
            return [
                'id' => $location->location_id,
                'name' => $location->name ?? $location->location_id,
            ];
        })->values()->toArray();

        $this->availableViews = $views->map(function (LinnworksView $view) {
            return [
                'id' => $view->view_id,
                'name' => $view->name ?? ('View '.$view->view_id),
            ];
        })->values()->toArray();

        $this->selectedLocation = $connection?->preferred_open_orders_location_id
            ?? config('linnworks.open_orders.location_id')
            ?? ($this->availableLocations[0]['id'] ?? null);

        $preferredView = $connection?->preferred_open_orders_view_id
            ?? config('linnworks.open_orders.view_id', 0);

        $this->selectedView = $preferredView ?: ($this->availableViews[0]['id'] ?? null);
    }

    public function refreshSourceCatalog(): void
    {
        $this->isRefreshingSources = true;

        try {
            $userId = auth()->id();

            if (! $userId) {
                session()->flash('error', 'You must be signed in to refresh Linnworks data.');

                return;
            }

            $connection = $this->linnworksOAuthService()->getActiveConnection($userId);

            if (! $connection) {
                session()->flash('error', 'Connect to Linnworks before refreshing locations or views.');

                return;
            }

            $this->locationsService()->getLocations($userId);
            $this->viewsService()->getOpenOrderViews($userId);

            $this->loadPreferences();

            session()->flash('success', 'Synced Linnworks locations and views.');
        } catch (LinnworksApiException|AuthenticationException $e) {
            session()->flash('error', $e->getUserMessage());
            report($e);
        } catch (Throwable $exception) {
            session()->flash('error', 'An unexpected error occurred while refreshing. Please try again.');
            report($exception);
        } finally {
            $this->isRefreshingSources = false;
        }
    }

    public function savePreferences(): void
    {
        $userId = auth()->id();

        if (! $userId) {
            session()->flash('error', 'You must be signed in to update preferences.');

            return;
        }

        $oauthService = $this->linnworksOAuthService();
        $connection = $oauthService->getActiveConnection($userId);

        if (! $connection) {
            session()->flash('error', 'Connect to Linnworks before saving preferences.');

            return;
        }

        $connection->updateOpenOrdersPreferences(
            $this->selectedView !== null ? (int) $this->selectedView : null,
            $this->selectedLocation ?: null,
        );

        session()->flash('success', 'Open orders defaults saved.');

        $this->loadPreferences();
    }

    public function updatedSelectedView($value): void
    {
        $this->selectedView = ($value === null || $value === '') ? null : (int) $value;
    }

    public function updatedSelectedLocation($value): void
    {
        $this->selectedLocation = $value !== '' ? $value : null;
    }

    public function render(): View
    {
        return view('livewire.settings.linnworks-settings')
            ->title('Linnworks Settings');
    }
}
