<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeUserAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:admin {email : The email of the user to make admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make a user an admin by their email address';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = $this->argument('email');

        // Validate email format
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("Invalid email format: '{$email}'");
            return Command::FAILURE;
        }

        // Find the user by email
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return Command::FAILURE;
        }

        // Check if already admin
        if ($user->is_admin) {
            $this->warn("User '{$email}' is already an admin.");

            return Command::SUCCESS;
        }

        // Make user admin
        $user->is_admin = true;
        $user->save();

        $this->info("User '{$email}' is now an admin.");

        return Command::SUCCESS;
    }
}
