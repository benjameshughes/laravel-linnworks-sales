<?php

namespace App\Livewire\Settings;

use App\Services\SettingsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.settings')]
class SecuritySettings extends Component
{
    public array $allowedDomains = [];
    public array $allowedEmails = [];
    public string $newDomain = '';
    public string $newEmail = '';

    public function __construct(
        private readonly SettingsService $settings
    ) {}

    public function mount(): void
    {
        // Check authorization
        if (!auth()->user()->can('manage-security')) {
            abort(403);
        }

        $this->allowedDomains = $this->settings->getArray('security.allowed_domains');
        $this->allowedEmails = $this->settings->getArray('security.allowed_emails');
    }

    public function addDomain(): void
    {
        $this->validate([
            'newDomain' => ['required', 'string', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/'],
        ], [
            'newDomain.regex' => 'Please enter a valid domain (e.g., example.com)',
        ]);

        $domain = strtolower(trim($this->newDomain));

        if (!in_array($domain, $this->allowedDomains)) {
            $this->allowedDomains[] = $domain;
            $this->settings->set('security.allowed_domains', $this->allowedDomains, auth()->id());
        }

        $this->newDomain = '';
        $this->dispatch('domain-added');
    }

    public function removeDomain(string $domain): void
    {
        $this->allowedDomains = array_values(array_filter(
            $this->allowedDomains,
            fn($d) => $d !== $domain
        ));

        $this->settings->set('security.allowed_domains', $this->allowedDomains, auth()->id());
    }

    public function addEmail(): void
    {
        $this->validate([
            'newEmail' => ['required', 'email'],
        ]);

        $email = strtolower(trim($this->newEmail));

        if (!in_array($email, $this->allowedEmails)) {
            $this->allowedEmails[] = $email;
            $this->settings->set('security.allowed_emails', $this->allowedEmails, auth()->id());
        }

        $this->newEmail = '';
        $this->dispatch('email-added');
    }

    public function removeEmail(string $email): void
    {
        $this->allowedEmails = array_values(array_filter(
            $this->allowedEmails,
            fn($e) => $e !== $email
        ));

        $this->settings->set('security.allowed_emails', $this->allowedEmails, auth()->id());
    }

    public function render()
    {
        return view('livewire.settings.security-settings');
    }
}
