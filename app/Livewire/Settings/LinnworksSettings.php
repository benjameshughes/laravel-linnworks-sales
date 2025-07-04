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
                session()->flash('error', 'Connection created but failed to establish session. Please check your credentials.');
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

        if ($oauthService->testConnection($connection)) {
            session()->flash('success', 'Connection test successful!');
        } else {
            session()->flash('error', 'Connection test failed. Please check your settings.');
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

        if ($oauthService->refreshSession($connection)) {
            session()->flash('success', 'Session refreshed successfully!');
        } else {
            session()->flash('error', 'Failed to refresh session. Please check your settings.');
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
