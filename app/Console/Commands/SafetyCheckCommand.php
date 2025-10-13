<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class SafetyCheckCommand extends Command
{
    protected $signature = 'db:safety-check';

    protected $description = 'Check if we are about to modify production database';

    public function handle()
    {
        $env = app()->environment();
        $dbConnection = config('database.default');
        $dbDatabase = config('database.connections.'.$dbConnection.'.database');

        $this->info("Environment: {$env}");
        $this->info("Database Connection: {$dbConnection}");
        $this->info("Database File: {$dbDatabase}");

        if ($env === 'production' || str_contains($dbDatabase, 'database.sqlite')) {
            $this->error('⚠️  WARNING: You are connected to a production-like database!');
            $this->error('Use --env=testing for safe testing operations.');

            return 1;
        }

        $this->info('✅ Safe to proceed with database operations.');

        return 0;
    }
}
