<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateUserCommand extends Command
{
    protected $signature = 'user:create
                            {--name= : The full name of the user}
                            {--email= : The email address}
                            {--role=user : The role (admin or user)}';

    protected $description = 'Create a new user account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Full name');
        $email = $this->option('email') ?: $this->ask('Email address');
        $role = $this->option('role');
        $password = $this->secret('Password');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'min:8'],
            'role' => ['required', 'in:admin,user'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => $role === 'admin',
            'email_verified_at' => now(),
        ]);

        $this->info("User created successfully!");
        $this->table(
            ['ID', 'Name', 'Email', 'Role'],
            [[$user->id, $user->name, $user->email, $role]]
        );

        return self::SUCCESS;
    }
}
