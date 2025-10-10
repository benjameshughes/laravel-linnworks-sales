<div class="space-y-6">
    <div>
        <flux:heading size="xl">Security Settings</flux:heading>
        <flux:subheading>Manage allowed email domains and security policies</flux:subheading>
    </div>

    {{-- Allowed Domains Section --}}
    <flux:card>
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Allowed Email Domains</flux:heading>
                <flux:subheading>Only users with these email domains can register</flux:subheading>
            </div>

            @if(count($allowedDomains) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach($allowedDomains as $domain)
                        <flux:badge size="lg" color="zinc" class="flex items-center gap-2">
                            {{ $domain }}
                            <button
                                wire:click="removeDomain('{{ $domain }}')"
                                class="hover:text-red-600 dark:hover:text-red-400"
                                type="button"
                            >
                                <flux:icon.x-mark class="size-4" />
                            </button>
                        </flux:badge>
                    @endforeach
                </div>
            @else
                <flux:subheading>No domains configured. Registration will be blocked for all users.</flux:subheading>
            @endif

            <flux:separator />

            <div class="flex gap-2">
                <flux:field class="flex-1">
                    <flux:label>Add Domain</flux:label>
                    <flux:input
                        wire:model="newDomain"
                        placeholder="example.com"
                        wire:keydown.enter="addDomain"
                    />
                    <flux:error name="newDomain" />
                </flux:field>
                <flux:button wire:click="addDomain" variant="primary" class="self-end">
                    Add Domain
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Allowed Individual Emails Section --}}
    <flux:card>
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Allowed Individual Emails</flux:heading>
                <flux:subheading>Specific email addresses allowed outside approved domains (e.g., contractors)</flux:subheading>
            </div>

            @if(count($allowedEmails) > 0)
                <div class="flex flex-wrap gap-2">
                    @foreach($allowedEmails as $email)
                        <flux:badge size="lg" color="zinc" class="flex items-center gap-2">
                            {{ $email }}
                            <button
                                wire:click="removeEmail('{{ $email }}')"
                                class="hover:text-red-600 dark:hover:text-red-400"
                                type="button"
                            >
                                <flux:icon.x-mark class="size-4" />
                            </button>
                        </flux:badge>
                    @endforeach
                </div>
            @else
                <flux:subheading>No individual emails configured.</flux:subheading>
            @endif

            <flux:separator />

            <div class="flex gap-2">
                <flux:field class="flex-1">
                    <flux:label>Add Email</flux:label>
                    <flux:input
                        wire:model="newEmail"
                        type="email"
                        placeholder="contractor@gmail.com"
                        wire:keydown.enter="addEmail"
                    />
                    <flux:error name="newEmail" />
                </flux:field>
                <flux:button wire:click="addEmail" variant="primary" class="self-end">
                    Add Email
                </flux:button>
            </div>
        </div>
    </flux:card>

    {{-- Password Requirements Info (Read-only) --}}
    <flux:card>
        <div class="space-y-4">
            <div>
                <flux:heading size="lg">Password Requirements</flux:heading>
                <flux:subheading>Current password policy for all users</flux:subheading>
            </div>

            <ul class="list-disc list-inside space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                <li>Minimum 12 characters</li>
                <li>Must contain uppercase and lowercase letters</li>
                <li>Must contain at least one number</li>
                <li>Must contain at least one special character</li>
                <li>Cannot be a commonly compromised password</li>
            </ul>
        </div>
    </flux:card>
</div>
