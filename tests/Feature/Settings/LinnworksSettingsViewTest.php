<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view linnworks settings page when not connected', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->get(route('settings.linnworks'));

    $response->assertOk()
        ->assertSee('Linnworks Integration')
        ->assertSee('Connection Status')
        ->assertSee('Not Connected')
        ->assertSee('Connect to Linnworks');
});

test('admin can view linnworks settings page with connection form', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->get(route('settings.linnworks'));

    $response->assertOk()
        ->assertSee('How to Get Your Linnworks Credentials')
        ->assertSee('Application ID')
        ->assertSee('Application Secret')
        ->assertSee('Access Token');
});

test('page structure follows standard settings pattern', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->get(route('settings.linnworks'));

    $response->assertOk();

    // Verify the page includes key Flux UI elements
    $content = $response->content();
    expect($content)
        ->toContain('data-flux-icon') // Flux icons are rendered with this attribute
        ->toContain('Connection Status')
        ->toContain('How to Get Your Linnworks Credentials');
});

test('non-admin can view linnworks settings page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)
        ->get(route('settings.linnworks'));

    // Linnworks settings are not restricted to admins
    $response->assertOk();
});

test('guest cannot view linnworks settings page', function () {
    $response = $this->get(route('settings.linnworks'));

    $response->assertRedirect(route('login'));
});
