<div>
    <div class="space-y-6">
        {{-- Header --}}
        <div>
            <flux:heading size="xl" class="text-gray-900 dark:text-white">Linnworks Integration</flux:heading>
            <flux:subheading class="text-gray-600 dark:text-gray-400">
                Connect your Linnworks account to sync sales data automatically
            </flux:subheading>
        </div>

        {{-- Flash Messages --}}
        @if (session('success'))
            <x-banner color="green" size="sm">
                <div class="flex items-center space-x-2">
                    <flux:icon.check-circle class="size-5" />
                    <span>{{ session('success') }}</span>
                </div>
            </x-banner>
        @endif

        @if (session('error'))
            <x-banner color="red" size="sm">
                <div class="flex items-center space-x-2">
                    <flux:icon.exclamation-circle class="size-5" />
                    <span>{{ session('error') }}</span>
                </div>
            </x-banner>
        @endif

        {{-- Connection Status Card --}}
        <x-card>
            <div class="flex items-center justify-between mb-6">
                <flux:heading size="lg">Connection Status</flux:heading>
                
                @if($this->connectionStatus['connected'])
                    <flux:badge color="green" size="sm">
                        <flux:icon.check-circle class="size-4" />
                        Connected
                    </flux:badge>
                @else
                    <flux:badge color="red" size="sm">
                        <flux:icon.x-circle class="size-4" />
                        Not Connected
                    </flux:badge>
                @endif
            </div>

            <div class="space-y-4">
                @if($this->connectionStatus['connected'])
                    {{-- Connected State --}}
                    <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                        <div class="flex items-start space-x-3">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 mt-0.5" />
                            <div class="flex-1">
                                <div class="font-medium text-green-900 dark:text-green-100">
                                    {{ $this->connectionStatus['message'] }}
                                </div>
                                
                                @if(isset($this->connectionStatus['server']))
                                    <div class="text-sm text-green-700 dark:text-green-300 mt-1">
                                        Server: {{ $this->connectionStatus['server'] }}
                                    </div>
                                @endif
                                
                                @if(isset($this->connectionStatus['expires_at']))
                                    <div class="text-sm text-green-700 dark:text-green-300">
                                        Session expires: {{ $this->connectionStatus['expires_at']->format('M j, Y H:i') }}
                                    </div>
                                @endif
                                
                                @if(isset($this->connectionStatus['last_connected']))
                                    <div class="text-sm text-green-700 dark:text-green-300">
                                        Last connected: {{ $this->connectionStatus['last_connected']->diffForHumans() }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Connected Actions --}}
                    <div class="flex flex-wrap gap-3">
                        <flux:button wire:click="testConnection" type="button" size="sm" variant="outline">
                            <flux:icon.signal class="size-4" />
                            Test Connection
                        </flux:button>
                        
                        @if($this->connectionStatus['status'] === 'needs_refresh')
                            <flux:button wire:click="refreshSession" type="button" size="sm" color="blue">
                                <flux:icon.arrow-path class="size-4" />
                                Refresh Session
                            </flux:button>
                        @endif
                        
                        <flux:button wire:click="disconnect" type="button" size="sm" color="red" variant="outline">
                            <flux:icon.x-mark class="size-4" />
                            Disconnect
                        </flux:button>
                    </div>
                @else
                    {{-- Not Connected State --}}
                    <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-lg border border-gray-200 dark:border-gray-700">
                        <div class="flex items-start space-x-3">
                            <flux:icon.exclamation-triangle class="size-5 text-amber-600 dark:text-amber-400 mt-0.5" />
                            <div class="flex-1">
                                <div class="font-medium text-gray-900 dark:text-white">
                                    No Linnworks connection found
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    Connect your Linnworks account to start syncing sales data automatically.
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Connect Button --}}
                    <div>
                        <flux:button wire:click="showConnectionForm" type="button" color="blue" icon="link">
                            Connect to Linnworks
                        </flux:button>
                    </div>
                @endif
            </div>
        </x-card>

        @if($this->connectionStatus['connected'])
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <flux:heading size="lg">Open Orders Defaults</flux:heading>
                        <flux:description class="text-gray-600 dark:text-gray-400">
                            Choose the Linnworks view and fulfilment location used when syncing open orders.
                        </flux:description>
                    </div>
                    <flux:button
                        wire:click="refreshSourceCatalog"
                        size="sm"
                        variant="outline"
                        :disabled="$isRefreshingSources"
                    >
                        <span wire:loading wire:target="refreshSourceCatalog" class="flex items-center gap-2">
                            <flux:icon.arrow-path class="size-4 animate-spin" />
                            Refreshing...
                        </span>
                        <span wire:loading.remove wire:target="refreshSourceCatalog" class="flex items-center gap-2">
                            <flux:icon.arrow-path class="size-4" />
                            Refresh from Linnworks
                        </span>
                    </flux:button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <flux:field>
                        <flux:label>Fulfilment Location</flux:label>
                        <flux:select wire:model="selectedLocation">
                            @forelse($availableLocations as $location)
                                <flux:select.option value="{{ $location['id'] }}">
                                    {{ $location['name'] }}
                                </flux:select.option>
                            @empty
                                <flux:select.option value="">
                                    No locations detected yet
                                </flux:select.option>
                            @endforelse
                        </flux:select>
                        <flux:description>
                            Defaults to the first Linnworks location if none is selected.
                        </flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>Open Orders View</flux:label>
                        <flux:select wire:model="selectedView">
                            @forelse($availableViews as $view)
                                <flux:select.option value="{{ $view['id'] }}">
                                    {{ $view['name'] }}
                                </flux:select.option>
                            @empty
                                <flux:select.option value="0">
                                    Default Linnworks view
                                </flux:select.option>
                            @endforelse
                        </flux:select>
                        <flux:description>
                            Leave on the default view or choose a saved Linnworks filter.
                        </flux:description>
                    </flux:field>
                </div>

                <div class="flex justify-end mt-6">
                    <flux:button wire:click="savePreferences" color="blue">
                        <flux:icon.check class="size-4" />
                        Save Preferences
                    </flux:button>
                </div>
            </x-card>
        @endif


        {{-- Connection Form --}}
        @if($showForm)
            <x-card>
                <div class="flex items-center justify-between mb-6">
                    <flux:heading size="lg">Connect to Linnworks</flux:heading>
                    <flux:button wire:click="hideConnectionForm" type="button" size="sm" variant="ghost">
                        <flux:icon.x-mark class="size-4" />
                    </flux:button>
                </div>

                <form wire:submit="connect" class="space-y-6">
                    <div class="grid grid-cols-1 gap-6">
                        {{-- Application ID --}}
                        <div>
                            <flux:field>
                                <flux:label>Application ID</flux:label>
                                <flux:input 
                                    wire:model="applicationId" 
                                    placeholder="Enter your Linnworks Application ID"
                                    required
                                />
                                <flux:error name="applicationId" />
                                <flux:description>
                                    Found in your Linnworks Developer Portal application settings
                                </flux:description>
                            </flux:field>
                        </div>

                        {{-- Application Secret --}}
                        <div>
                            <flux:field>
                                <flux:label>Application Secret</flux:label>
                                <flux:input 
                                    wire:model="applicationSecret" 
                                    type="password"
                                    placeholder="Enter your Application Secret"
                                    required
                                />
                                <flux:error name="applicationSecret" />
                                <flux:description>
                                    Your application's secret key from the Developer Portal
                                </flux:description>
                            </flux:field>
                        </div>

                        {{-- Access Token --}}
                        <div>
                            <flux:field>
                                <flux:label>Access Token</flux:label>
                                <flux:input 
                                    wire:model="accessToken" 
                                    type="password"
                                    placeholder="Enter your Access Token"
                                    required
                                />
                                <flux:error name="accessToken" />
                                <flux:description>
                                    Generated when you install the application on your Linnworks account
                                </flux:description>
                            </flux:field>
                        </div>
                    </div>

                    {{-- Form Actions --}}
                    <div class="flex justify-end space-x-3">
                        <flux:button type="button" wire:click="hideConnectionForm" variant="ghost">
                            Cancel
                        </flux:button>
                        <flux:button type="submit" color="blue">
                            <flux:icon.link class="size-4" />
                            Connect
                        </flux:button>
                    </div>
                </form>
            </x-card>
        @endif

        {{-- Instructions Card --}}
        <x-card>
            <flux:heading size="lg" class="mb-4">How to Get Your Linnworks Credentials</flux:heading>

            <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-lg border border-amber-200 dark:border-amber-800 mb-6">
                <div class="flex items-start space-x-2">
                    <flux:icon.information-circle class="size-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                    <div class="text-sm text-amber-800 dark:text-amber-200">
                        <strong>One-time setup:</strong> You'll need to create and install a Linnworks app to get your credentials. This only needs to be done once, then you can reconnect anytime with the same credentials.
                    </div>
                </div>
            </div>

            <div class="prose prose-sm dark:prose-invert max-w-none">
                <div class="space-y-4">
                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-sm font-medium">
                            1
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-gray-900 dark:text-white">Create a Developer Application</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                Go to the Linnworks Developer Portal and create a new application with type "External Application" or "System Integration".
                            </div>
                            <a href="https://developer.linnworks.com/" target="_blank" class="inline-flex items-center gap-2 text-sm px-3 py-1.5 bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                <flux:icon.arrow-top-right-on-square class="size-4" />
                                Open Developer Portal
                            </a>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-sm font-medium">
                            2
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">Get Application Credentials</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Copy your Application ID and Application Secret from the application settings.
                            </div>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-sm font-medium">
                            3
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">Install Application</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                Click the installation URL provided in the Developer Portal to install the application on your Linnworks account.
                            </div>
                        </div>
                    </div>

                    <div class="flex items-start space-x-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-sm font-medium">
                            4
                        </div>
                        <div>
                            <div class="font-medium text-gray-900 dark:text-white">Get Access Token</div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                After installation, you'll receive an Access Token. Copy this token for use in the connection form above.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start space-x-2">
                        <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 mt-0.5" />
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <strong>Note:</strong> Make sure to select "Development version" during installation to get access to the latest features and API endpoints.
                        </div>
                    </div>
                </div>
            </div>
        </x-card>
    </div>
</div>
