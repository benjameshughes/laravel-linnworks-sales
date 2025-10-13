<?php

namespace App\Rules;

use App\Services\SettingsService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class BusinessEmailDomain implements ValidationRule
{
    public function __construct(
        private readonly SettingsService $settings
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $email = strtolower(trim($value));

        // Check if specific email is in the allowed list
        $allowedEmails = $this->settings->getArray('security.allowed_emails');
        $allowedEmails = array_map('strtolower', $allowedEmails);

        if (in_array($email, $allowedEmails)) {
            return;
        }

        // Extract domain from email
        if (! str_contains($email, '@')) {
            $fail('The :attribute must be a valid email address.');

            return;
        }

        $domain = substr(strrchr($email, '@'), 1);

        // Check if domain is in the allowed list
        $allowedDomains = $this->settings->getArray('security.allowed_domains');
        $allowedDomains = array_map('strtolower', $allowedDomains);

        if (! in_array($domain, $allowedDomains)) {
            $fail('Registration is restricted to authorized business email addresses.');
        }
    }
}
