<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can view import progress page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->get(route('settings.import'));

    $response->assertOk()
        ->assertSee('Import Orders')
        ->assertSee('Configure Import')
        ->assertSee('From Date')
        ->assertSee('To Date')
        ->assertSee('Batch Size');
});

test('page shows configuration form when not importing', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->get(route('settings.import'));

    $response->assertOk()
        ->assertSee('Start Import')
        ->assertSee('Important Notes')
        ->assertSee('This will import all processed orders from Linnworks');
});

test('page renders with flux ui components', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)
        ->get(route('settings.import'));

    $response->assertOk();

    // Verify Flux components are being used (they render with data-flux-icon attribute)
    expect($response->content())
        ->toContain('data-flux-icon');
});

test('non-admin can view import progress page', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)
        ->get(route('settings.import'));

    // Import progress is not restricted to admins
    $response->assertOk();
});

test('guest cannot view import progress page', function () {
    $response = $this->get(route('settings.import'));

    $response->assertRedirect(route('login'));
});
