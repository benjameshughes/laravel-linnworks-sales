<?php

namespace App\Livewire\Settings;

use App\Services\LinnworksOAuthService;
use Livewire\Component;
use Livewire\Attributes\Computed;

class LinnworksSettings extends Component
{
    public string $applicationId = '';
    public string $applicationSecret = '';
    public string $accessToken = '';
    public bool $showForm = false;

    public function mount(LinnworksOAuthService $oauthService)
    {
        $this->checkConnectionStatus();
    }

    #[Computed]
    public function connectionStatus()
    {
        $oauthService = app(LinnworksOAuthService::class);
        return $oauthService->getConnectionStatus(auth()->id());
    }

    public function showConnectionForm()
    {
        $this->showForm = true;
        $this->reset(['applicationId', 'applicationSecret', 'accessToken']);
    }


    public function hideConnectionForm()
    {
        $this->showForm = false;
        $this->reset(['applicationId', 'applicationSecret', 'accessToken']);
    }


    public function connect()
    {
        $this->validate([
            'applicationId' => 'required|string',
            'applicationSecret' => 'required|string',
            'accessToken' => 'required|string',
        ]);

        try {
            $oauthService = app(LinnworksOAuthService::class);
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
            } else {
                // Get more detailed error info
                $status = $oauthService->getConnectionStatus(auth()->id());
                session()->flash('error', 'Connection created but test failed: ' . ($status['message'] ?? 'Unknown error'));
            }
        } catch (\Exception $e) {
            session()->flash('error', 'Failed to connect to Linnworks: ' . $e->getMessage());
        }
    }

    public function disconnect()
    {
        $oauthService = app(LinnworksOAuthService::class);
        
        if ($oauthService->disconnect(auth()->id())) {
            session()->flash('success', 'Successfully disconnected from Linnworks.');
        } else {
            session()->flash('error', 'Failed to disconnect from Linnworks.');
        }
    }

    public function testConnection()
    {
        $oauthService = app(LinnworksOAuthService::class);
        $connection = $oauthService->getActiveConnection(auth()->id());

        if (!$connection) {
            session()->flash('error', 'No active connection found.');
            return;
        }

        // Add debugging info
        \Log::info('Testing connection', [
            'user_id' => auth()->id(),
            'connection_id' => $connection->id,
            'has_session_token' => !empty($connection->session_token),
            'has_server_location' => !empty($connection->server_location),
            'session_expires_at' => $connection->session_expires_at?->toDateTimeString(),
            'needs_refresh' => $connection->needsNewSession(),
        ]);

        if ($oauthService->testConnection($connection)) {
            session()->flash('success', 'Connection test successful!');
        } else {
            $status = $oauthService->getConnectionStatus(auth()->id());
            session()->flash('error', 'Test failed: ' . ($status['message'] ?? 'Unknown error'));
        }
    }

    public function refreshSession()
    {
        $oauthService = app(LinnworksOAuthService::class);
        $connection = $oauthService->getActiveConnection(auth()->id());

        if (!$connection) {
            session()->flash('error', 'No active connection found.');
            return;
        }

        \Log::info('Refreshing session', [
            'user_id' => auth()->id(),
            'connection_id' => $connection->id,
            'current_session_token' => substr($connection->session_token ?? '', 0, 10) . '...',
            'access_token' => substr($connection->access_token, 0, 10) . '...',
        ]);

        if ($oauthService->refreshSession($connection)) {
            session()->flash('success', 'Session refreshed successfully!');
        } else {
            session()->flash('error', 'Failed to refresh session. Check logs for details.');
        }
    }

    private function checkConnectionStatus()
    {
        // This will trigger the computed property to refresh
        unset($this->connectionStatus);
    }

    public function render()
    {
        return view('livewire.settings.linnworks-settings')
            ->title('Linnworks Settings');
    }
}
