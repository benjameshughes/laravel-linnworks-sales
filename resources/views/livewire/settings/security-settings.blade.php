<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Security Settings')" :subheading="__('Manage allowed email domains and security policies')">
        <div class="my-6 w-full space-y-10">
            {{-- Allowed Domains Section --}}
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.shield-check class="size-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Allowed Email Domains</flux:heading>
                        <flux:subheading>Only users with these email domains can register</flux:subheading>
                    </div>
                </div>

                @if(count($allowedDomains) > 0)
                    <div class="flex flex-wrap gap-2">
                        @foreach($allowedDomains as $domain)
                            <flux:badge size="lg" color="blue" class="flex items-center gap-2">
                                {{ $domain }}
                                <button
                                    wire:click="removeDomain('{{ $domain }}')"
                                    class="hover:text-red-600 dark:hover:text-red-400 transition-colors"
                                    type="button"
                                    aria-label="Remove domain"
                                >
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            </flux:badge>
                        @endforeach
                    </div>
                @else
                    <div class="p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
                        <div class="flex items-start gap-2">
                            <flux:icon.exclamation-triangle class="size-5 text-amber-600 dark:text-amber-400 flex-shrink-0 mt-0.5" />
                            <p class="text-sm text-amber-800 dark:text-amber-200">
                                No domains configured. Registration will be blocked for all users.
                            </p>
                        </div>
                    </div>
                @endif

                <flux:separator />

                <div class="space-y-2">
                    <flux:field>
                        <flux:label>Add Domain</flux:label>
                        <div class="flex gap-2">
                            <flux:input
                                wire:model="newDomain"
                                placeholder="example.com"
                                wire:keydown.enter="addDomain"
                                class="flex-1"
                            />
                            <flux:button wire:click="addDomain" variant="primary" icon="plus">
                                Add Domain
                            </flux:button>
                        </div>
                        <flux:error name="newDomain" />
                    </flux:field>
                </div>
            </div>

            {{-- Allowed Individual Emails Section --}}
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.user-plus class="size-6 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Allowed Individual Emails</flux:heading>
                        <flux:subheading>Specific email addresses allowed outside approved domains (e.g., contractors)</flux:subheading>
                    </div>
                </div>

                @if(count($allowedEmails) > 0)
                    <div class="flex flex-wrap gap-2">
                        @foreach($allowedEmails as $email)
                            <flux:badge size="lg" color="purple" class="flex items-center gap-2">
                                {{ $email }}
                                <button
                                    wire:click="removeEmail('{{ $email }}')"
                                    class="hover:text-red-600 dark:hover:text-red-400 transition-colors"
                                    type="button"
                                    aria-label="Remove email"
                                >
                                    <flux:icon.x-mark class="size-4" />
                                </button>
                            </flux:badge>
                        @endforeach
                    </div>
                @else
                    <div class="p-4 bg-zinc-50 dark:bg-zinc-800/50 rounded-lg">
                        <p class="text-sm text-zinc-600 dark:text-zinc-400">
                            No individual emails configured.
                        </p>
                    </div>
                @endif

                <flux:separator />

                <div class="space-y-2">
                    <flux:field>
                        <flux:label>Add Email</flux:label>
                        <div class="flex gap-2">
                            <flux:input
                                wire:model="newEmail"
                                type="email"
                                placeholder="contractor@gmail.com"
                                wire:keydown.enter="addEmail"
                                class="flex-1"
                            />
                            <flux:button wire:click="addEmail" variant="primary" icon="plus">
                                Add Email
                            </flux:button>
                        </div>
                        <flux:error name="newEmail" />
                    </flux:field>
                </div>
            </div>

            {{-- Password Requirements Info (Read-only) --}}
            <div class="bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 p-6 space-y-6">
                <div class="flex items-start gap-3">
                    <div class="flex-shrink-0 w-10 h-10 bg-green-100 dark:bg-green-900/20 rounded-lg flex items-center justify-center">
                        <flux:icon.lock-closed class="size-6 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="flex-1">
                        <flux:heading size="lg">Password Requirements</flux:heading>
                        <flux:subheading>Current password policy for all users</flux:subheading>
                    </div>
                </div>

                <div class="p-4 bg-green-50 dark:bg-green-900/10 border border-green-200 dark:border-green-800/50 rounded-lg">
                    <ul class="space-y-2.5 text-sm text-green-900 dark:text-green-100">
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span>Minimum 12 characters</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span>Must contain uppercase and lowercase letters</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span>Must contain at least one number</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span>Must contain at least one special character</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <flux:icon.check-circle class="size-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" />
                            <span>Cannot be a commonly compromised password</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </x-settings.layout>
</section>
