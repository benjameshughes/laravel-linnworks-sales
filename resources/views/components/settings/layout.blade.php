<div class="flex items-start max-md:flex-col">
    <div class="me-10 w-full pb-4 md:w-[220px] space-y-6">
        {{-- Account Settings --}}
        <div>
            <flux:subheading class="mb-2 px-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                {{ __('Account') }}
            </flux:subheading>
            <flux:navlist>
                <flux:navlist.item :href="route('settings.profile')" wire:navigate icon="user">
                    {{ __('Profile') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('settings.password')" wire:navigate icon="lock-closed">
                    {{ __('Password') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('settings.appearance')" wire:navigate icon="swatch">
                    {{ __('Appearance') }}
                </flux:navlist.item>
            </flux:navlist>
        </div>

        {{-- Integration Settings --}}
        <div>
            <flux:subheading class="mb-2 px-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                {{ __('Integrations') }}
            </flux:subheading>
            <flux:navlist>
                <flux:navlist.item :href="route('settings.linnworks')" wire:navigate icon="link">
                    {{ __('Linnworks') }}
                </flux:navlist.item>
                <flux:navlist.item :href="route('settings.import')" wire:navigate icon="arrow-down-tray">
                    {{ __('Import Orders') }}
                </flux:navlist.item>
            </flux:navlist>
        </div>

        {{-- System Settings (Admin only) --}}
        @if(auth()->user()->can('manage-security') || auth()->user()->can('manage-cache'))
            <div>
                <flux:subheading class="mb-2 px-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400 uppercase tracking-wider">
                    {{ __('System') }}
                </flux:subheading>
                <flux:navlist>
                    @can('manage-security')
                        <flux:navlist.item :href="route('settings.security')" wire:navigate icon="shield-check">
                            {{ __('Security') }}
                        </flux:navlist.item>
                    @endcan

                    @can('manage-cache')
                        <flux:navlist.item :href="route('settings.cache')" wire:navigate icon="server-stack">
                            {{ __('Cache') }}
                        </flux:navlist.item>
                    @endcan
                </flux:navlist>
            </div>
        @endif
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-4xl">
            {{ $slot }}
        </div>
    </div>
</div>
