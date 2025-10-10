<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Profile')" :subheading="__('Update your name and email address')">
        <div class="my-6 w-full space-y-10">
            {{-- Profile Information --}}
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.user class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Profile Information</flux:heading>
                        <flux:subheading>Update your account's name and email address</flux:subheading>
                    </div>
                </div>

                <form wire:submit="updateProfileInformation" class="space-y-6">
                    <flux:field>
                        <flux:label>{{ __('Name') }}</flux:label>
                        <flux:input wire:model="name" type="text" required autofocus autocomplete="name" />
                        <flux:error name="name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Email') }}</flux:label>
                        <flux:input wire:model="email" type="email" required autocomplete="email" />
                        <flux:error name="email" />

                        @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail &&! auth()->user()->hasVerifiedEmail())
                            <div class="mt-3 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                                <div class="flex items-start gap-2">
                                    <flux:icon.exclamation-triangle class="size-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                                    <div class="text-sm text-amber-800 dark:text-amber-200">
                                        {{ __('Your email address is unverified.') }}
                                        <flux:button
                                            wire:click.prevent="resendVerificationNotification"
                                            variant="ghost"
                                            size="sm"
                                            class="mt-1"
                                        >
                                            {{ __('Click here to re-send the verification email.') }}
                                        </flux:button>
                                    </div>
                                </div>

                                @if (session('status') === 'verification-link-sent')
                                    <div class="mt-2 p-2 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded">
                                        <div class="flex items-center gap-2">
                                            <flux:icon.check-circle class="size-4 text-green-600 dark:text-green-400" />
                                            <span class="text-sm text-green-800 dark:text-green-200">
                                                {{ __('A new verification link has been sent to your email address.') }}
                                            </span>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </flux:field>

                    <flux:separator />

                    <div class="flex items-center justify-between">
                        <x-action-message on="profile-updated">
                            {{ __('Saved.') }}
                        </x-action-message>

                        <flux:button variant="primary" type="submit" icon="check">
                            {{ __('Save Changes') }}
                        </flux:button>
                    </div>
                </form>
            </div>

            <livewire:settings.delete-user-form />
        </div>
    </x-settings.layout>
</section>
