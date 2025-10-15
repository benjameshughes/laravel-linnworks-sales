<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;

class AddAllowedDomain extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'domain:add {domain : The domain to add to allowed list}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Add a domain to the security.allowed_domains list';

    /**
     * Execute the console command.
     */
    public function handle(SettingsService $settings): int
    {
        $domain = $this->argument('domain');

        // Validate domain format
        if (! filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $this->error("Invalid domain format: '{$domain}'");
            $this->line('Domain must be a valid hostname (e.g., example.com, subdomain.example.com)');
            return Command::FAILURE;
        }

        // Get current allowed domains
        $allowedDomains = $settings->getArray('security.allowed_domains');

        // Check if domain already exists
        if (in_array($domain, $allowedDomains)) {
            $this->warn("Domain '{$domain}' is already in the allowed domains list.");

            return Command::SUCCESS;
        }

        // Add the new domain
        $allowedDomains[] = $domain;
        $settings->set('security.allowed_domains', $allowedDomains);

        $this->info("Domain '{$domain}' added to allowed domains list.");
        $this->line('Current allowed domains: '.implode(', ', $allowedDomains));

        return Command::SUCCESS;
    }
}
