{{-- Delete Account Section --}}
<div class="bg-white dark:bg-zinc-900 rounded-xl border border-red-200 dark:border-red-900/50 p-6 space-y-6">
    <div class="flex items-start gap-3">
        <div class="flex-shrink-0 w-10 h-10 bg-red-100 dark:bg-red-900/20 rounded-lg flex items-center justify-center">
            <flux:icon.trash class="size-6 text-red-600 dark:text-red-400" />
        </div>
        <div class="flex-1">
            <flux:heading size="lg">{{ __('Delete Account') }}</flux:heading>
            <flux:subheading>{{ __('Permanently delete your account and all of its data') }}</flux:subheading>
        </div>
    </div>

    <div class="p-4 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800/50 rounded-lg">
        <div class="flex items-start gap-2">
            <flux:icon.exclamation-triangle class="size-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
            <p class="text-sm text-red-800 dark:text-red-200">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. This action cannot be undone.') }}
            </p>
        </div>
    </div>

    <flux:separator />

    <div class="flex justify-end">
        <flux:modal.trigger name="confirm-user-deletion">
            <flux:button variant="danger" icon="trash" x-data="" x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')">
                {{ __('Delete Account') }}
            </flux:button>
        </flux:modal.trigger>
    </div>

    <flux:modal name="confirm-user-deletion" :show="$errors->isNotEmpty()" focusable class="max-w-lg">
        <form wire:submit="deleteUser" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Are you sure you want to delete your account?') }}</flux:heading>

                <flux:subheading>
                    {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                </flux:subheading>
            </div>

            <flux:field>
                <flux:label>{{ __('Password') }}</flux:label>
                <flux:input wire:model="password" type="password" required />
                <flux:error name="password" />
            </flux:field>

            <div class="flex justify-end space-x-2 rtl:space-x-reverse">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" type="submit" icon="trash">{{ __('Delete Account') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
