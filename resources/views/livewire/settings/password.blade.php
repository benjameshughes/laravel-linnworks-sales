<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Update Password')" :subheading="__('Ensure your account is using a long, random password to stay secure')">
        <div class="my-6 w-full">
            {{-- Update Password --}}
            <x-animations.fade-in-up :delay="100" class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.lock-closed class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Change Password</flux:heading>
                        <flux:subheading>Update your password to keep your account secure</flux:subheading>
                    </div>
                </div>

                <form wire:submit="updatePassword" class="space-y-6">
                    <flux:field>
                        <flux:label>{{ __('Current Password') }}</flux:label>
                        <flux:input
                            wire:model="current_password"
                            type="password"
                            required
                            autocomplete="current-password"
                        />
                        <flux:error name="current_password" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('New Password') }}</flux:label>
                        <flux:input
                            wire:model="password"
                            type="password"
                            required
                            autocomplete="new-password"
                        />
                        <flux:error name="password" />
                        <flux:description>
                            Must be at least 12 characters with mixed case, numbers, and symbols
                        </flux:description>
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Confirm New Password') }}</flux:label>
                        <flux:input
                            wire:model="password_confirmation"
                            type="password"
                            required
                            autocomplete="new-password"
                        />
                        <flux:error name="password_confirmation" />
                    </flux:field>

                    <flux:separator />

                    <div class="flex items-center justify-between">
                        <x-action-message on="password-updated">
                            {{ __('Password updated successfully.') }}
                        </x-action-message>

                        <flux:button variant="primary" type="submit" icon="check">
                            {{ __('Update Password') }}
                        </flux:button>
                    </div>
                </form>
            </x-animations.fade-in-up>
        </div>
    </x-settings.layout>
</section>
