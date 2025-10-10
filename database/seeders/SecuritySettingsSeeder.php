<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class SecuritySettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'security.allowed_domains',
                'value' => ['example.com'], // Replace with your business domain
                'type' => 'security',
                'description' => 'Allowed email domains for user registration',
            ],
            [
                'key' => 'security.allowed_emails',
                'value' => [], // Individual email exceptions
                'type' => 'security',
                'description' => 'Individual email addresses allowed outside approved domains',
            ],
            [
                'key' => 'security.enforce_verification',
                'value' => true,
                'type' => 'security',
                'description' => 'Require email verification before accessing the app',
            ],
            [
                'key' => 'security.max_login_attempts',
                'value' => 5,
                'type' => 'security',
                'description' => 'Maximum login attempts before throttling',
            ],
            [
                'key' => 'security.lockout_duration',
                'value' => 60, // minutes
                'type' => 'security',
                'description' => 'Duration of account lockout after max failed attempts (in minutes)',
            ],
        ];

        foreach ($settings as $setting) {
            AppSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
