<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Appearance')" :subheading="__('Customize how the application looks on your device')">
        <div class="my-6 w-full">
            {{-- Theme Settings --}}
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.swatch class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Theme Preference</flux:heading>
                        <flux:subheading>Choose how the application appears to you</flux:subheading>
                    </div>
                </div>

                <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                    <flux:radio.group x-data variant="segmented" x-model="$flux.appearance" class="w-full">
                        <flux:radio value="light" icon="sun" class="flex-1">{{ __('Light') }}</flux:radio>
                        <flux:radio value="dark" icon="moon" class="flex-1">{{ __('Dark') }}</flux:radio>
                        <flux:radio value="system" icon="computer-desktop" class="flex-1">{{ __('System') }}</flux:radio>
                    </flux:radio.group>
                </div>

                <div class="p-4 bg-blue-50 dark:bg-blue-900/10 border border-blue-200 dark:border-blue-800/50 rounded-lg">
                    <div class="flex items-start gap-2">
                        <flux:icon.information-circle class="size-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
                        <div class="text-sm text-blue-800 dark:text-blue-200">
                            <p class="font-medium mb-1">About theme settings</p>
                            <ul class="space-y-1 text-blue-700 dark:text-blue-300">
                                <li><strong>Light:</strong> Always use light theme</li>
                                <li><strong>Dark:</strong> Always use dark theme</li>
                                <li><strong>System:</strong> Automatically match your device's theme</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-settings.layout>
</section>
