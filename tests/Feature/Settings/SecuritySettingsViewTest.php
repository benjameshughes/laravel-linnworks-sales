<?php

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view security settings page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    // Seed some test data
    $settings = app(SettingsService::class);
    $settings->set('security.allowed_domains', ['testcompany.com']);
    $settings->set('security.allowed_emails', ['contractor@example.com']);

    $response = $this->actingAs($admin)
        ->get(route('settings.security'));

    $response->assertOk()
        ->assertSee('Security Settings')
        ->assertSee('Allowed Email Domains')
        ->assertSee('testcompany.com')
        ->assertSee('contractor@example.com');
});

test('non-admin cannot view security settings page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)
        ->get(route('settings.security'));

    $response->assertForbidden();
});

test('guest cannot view security settings page', function () {
    $response = $this->get(route('settings.security'));

    $response->assertRedirect(route('login'));
});
