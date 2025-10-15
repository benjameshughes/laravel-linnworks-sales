<?php

namespace App\Livewire\Settings;

use App\Services\SettingsService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SecuritySettings extends Component
{
    public array $allowedDomains = [];

    public array $allowedEmails = [];

    public string $newDomain = '';

    public string $newEmail = '';

    public function mount(SettingsService $settings): void
    {
        // Check authorization
        if (! auth()->user()->can('manage-security')) {
            abort(403);
        }

        $this->allowedDomains = $settings->getArray('security.allowed_domains');
        $this->allowedEmails = $settings->getArray('security.allowed_emails');
    }

    public function addDomain(SettingsService $settings): void
    {
        $this->validate([
            'newDomain' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (! filter_var($value, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
                        $fail('Please enter a valid domain (e.g., example.com or subdomain.example.com)');
                    }
                },
            ],
        ]);

        $domain = strtolower(trim($this->newDomain));

        if (! in_array($domain, $this->allowedDomains)) {
            $this->allowedDomains[] = $domain;
            $settings->set('security.allowed_domains', $this->allowedDomains, auth()->id());
        }

        $this->newDomain = '';
        $this->dispatch('domain-added');
    }

    public function removeDomain(string $domain, SettingsService $settings): void
    {
        $this->allowedDomains = array_values(array_filter(
            $this->allowedDomains,
            fn ($d) => $d !== $domain
        ));

        $settings->set('security.allowed_domains', $this->allowedDomains, auth()->id());
    }

    public function addEmail(SettingsService $settings): void
    {
        $this->validate([
            'newEmail' => ['required', 'email'],
        ]);

        $email = strtolower(trim($this->newEmail));

        if (! in_array($email, $this->allowedEmails)) {
            $this->allowedEmails[] = $email;
            $settings->set('security.allowed_emails', $this->allowedEmails, auth()->id());
        }

        $this->newEmail = '';
        $this->dispatch('email-added');
    }

    public function removeEmail(string $email, SettingsService $settings): void
    {
        $this->allowedEmails = array_values(array_filter(
            $this->allowedEmails,
            fn ($e) => $e !== $email
        ));

        $settings->set('security.allowed_emails', $this->allowedEmails, auth()->id());
    }

    public function render()
    {
        return view('livewire.settings.security-settings');
    }
}
