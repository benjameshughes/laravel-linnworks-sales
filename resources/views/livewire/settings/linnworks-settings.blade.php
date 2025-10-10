<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Linnworks Integration')" :subheading="__('Connect your Linnworks account to sync sales data automatically')">
        <div class="my-6 w-full space-y-10">
            {{-- Flash Messages --}}
            @if (session('success'))
                <div class="flex items-center gap-2 p-4 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                    <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400" />
                    <span class="text-sm text-green-800 dark:text-green-200">{{ session('success') }}</span>
                </div>
            @endif

            @if (session('error'))
                <div class="flex items-center gap-2 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <flux:icon.exclamation-circle class="size-5 text-red-600 dark:text-red-400" />
                    <span class="text-sm text-red-800 dark:text-red-200">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Connection Status --}}
            <x-animations.fade-in-up :delay="100" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.link class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
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
                        <flux:subheading>Manage your Linnworks API connection</flux:subheading>
                    </div>
                </div>

                @if($this->connectionStatus['connected'])
                    {{-- Connected State --}}
                    <div class="bg-green-50 dark:bg-green-900/20 p-4 rounded-lg border border-green-200 dark:border-green-800">
                        <div class="flex items-start gap-3">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 mt-0.5 flex-shrink-0" />
                            <div class="flex-1 text-sm">
                                <div class="font-medium text-green-900 dark:text-green-100">
                                    {{ $this->connectionStatus['message'] }}
                                </div>

                                @if(isset($this->connectionStatus['server']))
                                    <div class="text-green-700 dark:text-green-300 mt-1">
                                        Server: {{ $this->connectionStatus['server'] }}
                                    </div>
                                @endif

                                @if(isset($this->connectionStatus['expires_at']))
                                    <div class="text-green-700 dark:text-green-300">
                                        Session expires: {{ $this->connectionStatus['expires_at']->format('M j, Y H:i') }}
                                    </div>
                                @endif

                                @if(isset($this->connectionStatus['last_connected']))
                                    <div class="text-green-700 dark:text-green-300">
                                        Last connected: {{ $this->connectionStatus['last_connected']->diffForHumans() }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Connected Actions --}}
                    <div class="flex flex-wrap gap-3">
                        <flux:button wire:click="testConnection" type="button" size="sm" variant="outline" icon="signal">
                            Test Connection
                        </flux:button>

                        @if($this->connectionStatus['status'] === 'needs_refresh')
                            <flux:button wire:click="refreshSession" type="button" size="sm" variant="primary" icon="arrow-path">
                                Refresh Session
                            </flux:button>
                        @endif

                        <flux:button wire:click="disconnect" type="button" size="sm" variant="danger" icon="x-mark">
                            Disconnect
                        </flux:button>
                    </div>
                @else
                    {{-- Not Connected State --}}
                    <div class="bg-zinc-50 dark:bg-zinc-800/50 p-4 rounded-lg border border-zinc-200 dark:border-zinc-700">
                        <div class="flex items-start gap-3">
                            <flux:icon.exclamation-triangle class="size-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                            <div class="flex-1">
                                <div class="font-medium text-zinc-900 dark:text-white">
                                    No Linnworks connection found
                                </div>
                                <div class="text-sm text-zinc-600 dark:text-zinc-400 mt-1">
                                    Connect your Linnworks account to start syncing sales data automatically.
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Connect Button --}}
                    <flux:separator />

                    <div class="flex justify-start">
                        <flux:button wire:click="showConnectionForm" type="button" variant="primary" icon="link">
                            Connect to Linnworks
                        </flux:button>
                    </div>
                @endif
            </x-animations.fade-in-up>

            {{-- Open Orders Defaults (only show when connected) --}}
            @if($this->connectionStatus['connected'])
                <x-animations.fade-in-up :delay="200" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-lg flex items-center justify-center">
                            <flux:icon.cog-6-tooth class="size-6 text-purple-600 dark:text-purple-400" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="lg">Open Orders Defaults</flux:heading>
                            <flux:subheading>Choose the Linnworks view and fulfilment location used when syncing open orders</flux:subheading>
                        </div>
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
                            <flux:description>Defaults to the first Linnworks location if none is selected</flux:description>
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
                            <flux:description>Leave on the default view or choose a saved Linnworks filter</flux:description>
                        </flux:field>
                    </div>

                    <flux:separator />

                    <div class="flex items-center justify-between">
                        <flux:button
                            wire:click="refreshSourceCatalog"
                            size="sm"
                            variant="outline"
                            icon="arrow-path"
                            :disabled="$isRefreshingSources"
                        >
                            <span wire:loading wire:target="refreshSourceCatalog">
                                Refreshing...
                            </span>
                            <span wire:loading.remove wire:target="refreshSourceCatalog">
                                Refresh from Linnworks
                            </span>
                        </flux:button>

                        <flux:button wire:click="savePreferences" variant="primary" icon="check">
                            Save Preferences
                        </flux:button>
                    </div>
                </x-animations.fade-in-up>
            @endif

            {{-- Connection Form --}}
            @if($showForm)
                <x-animations.fade-in-up :delay="300" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/20 rounded-lg flex items-center justify-center">
                            <flux:icon.key class="size-6 text-green-600 dark:text-green-400" />
                        </div>
                        <div class="flex-1">
                            <flux:heading size="lg">Connect to Linnworks</flux:heading>
                            <flux:subheading>Enter your Linnworks API credentials</flux:subheading>
                        </div>
                        <flux:button wire:click="hideConnectionForm" type="button" size="sm" variant="ghost" icon="x-mark" />
                    </div>

                    <form wire:submit="connect" class="space-y-6">
                        <flux:field>
                            <flux:label>Application ID</flux:label>
                            <flux:input
                                wire:model="applicationId"
                                placeholder="Enter your Linnworks Application ID"
                                required
                            />
                            <flux:error name="applicationId" />
                            <flux:description>Found in your Linnworks Developer Portal application settings</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Application Secret</flux:label>
                            <flux:input
                                wire:model="applicationSecret"
                                type="password"
                                placeholder="Enter your Application Secret"
                                required
                            />
                            <flux:error name="applicationSecret" />
                            <flux:description>Your application's secret key from the Developer Portal</flux:description>
                        </flux:field>

                        <flux:field>
                            <flux:label>Access Token</flux:label>
                            <flux:input
                                wire:model="accessToken"
                                type="password"
                                placeholder="Enter your Access Token"
                                required
                            />
                            <flux:error name="accessToken" />
                            <flux:description>Generated when you install the application on your Linnworks account</flux:description>
                        </flux:field>

                        <flux:separator />

                        <div class="flex justify-end gap-3">
                            <flux:button type="button" wire:click="hideConnectionForm" variant="ghost">
                                Cancel
                            </flux:button>
                            <flux:button type="submit" variant="primary" icon="link">
                                Connect
                            </flux:button>
                        </div>
                    </form>
                </x-animations.fade-in-up>
            @endif

            {{-- Instructions --}}
            <x-animations.fade-in-up :delay="400" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-amber-100 dark:bg-amber-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.information-circle class="size-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">How to Get Your Linnworks Credentials</flux:heading>
                        <flux:subheading>Step-by-step guide to connect your account</flux:subheading>
                    </div>
                </div>

                <div class="bg-amber-50 dark:bg-amber-900/20 p-4 rounded-lg border border-amber-200 dark:border-amber-800">
                    <div class="flex items-start gap-2">
                        <flux:icon.information-circle class="size-5 text-amber-600 dark:text-amber-400 mt-0.5 flex-shrink-0" />
                        <div class="text-sm text-amber-800 dark:text-amber-200">
                            <strong>One-time setup:</strong> You'll need to create and install a Linnworks app to get your credentials. This only needs to be done once, then you can reconnect anytime with the same credentials.
                        </div>
                    </div>
                </div>

                <div class="space-y-4">
                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-sm font-medium">
                            1
                        </div>
                        <div class="flex-1">
                            <div class="font-medium text-zinc-900 dark:text-white">Install the Application</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400 mb-2">
                                Click the button below to install this application on your Linnworks account. This will generate your Application ID, Secret, and Access Token.
                            </div>
                            @php
                                $appId = $applicationId ?: config('linnworks.application_id');
                            @endphp
                            @if($appId)
                                <flux:button
                                    href="https://apps.linnworks.net/Authorization/Authorize/{{ $appId }}"
                                    target="_blank"
                                    size="sm"
                                    variant="outline"
                                    icon="arrow-top-right-on-square"
                                >
                                    Install Application
                                </flux:button>
                            @else
                                <flux:button
                                    href="https://developer.linnworks.com/"
                                    target="_blank"
                                    size="sm"
                                    variant="outline"
                                    icon="arrow-top-right-on-square"
                                >
                                    Open Developer Portal
                                </flux:button>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-sm font-medium">
                            2
                        </div>
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-white">Authorize the Application</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                After clicking "Install Application", you'll be redirected to Linnworks where you can authorize the app. This generates your credentials.
                            </div>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-sm font-medium">
                            3
                        </div>
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-white">Copy Your Credentials</div>
                            <div class="text-sm text-zinc-600 dark:text-zinc-400">
                                After authorization, Linnworks will display your Application ID, Application Secret, and Access Token. Copy all three credentials and enter them in the connection form above.
                            </div>
                        </div>
                    </div>
                </div>

                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
                    <div class="flex items-start gap-2">
                        <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 mt-0.5 flex-shrink-0" />
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <strong>Note:</strong> Make sure to select "Development version" during installation to get access to the latest features and API endpoints.
                        </div>
                    </div>
                </div>
            </x-animations.fade-in-up>
        </div>
    </x-settings.layout>
</section>
