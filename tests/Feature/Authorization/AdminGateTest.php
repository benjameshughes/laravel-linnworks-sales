<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

test('admin user can manage security', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    expect($admin->can('manage-security'))->toBeTrue();
});

test('non-admin user cannot manage security', function () {
    $user = User::factory()->create(['is_admin' => false]);

    expect($user->can('manage-security'))->toBeFalse();
});

test('admin user can manage users', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    expect($admin->can('manage-users'))->toBeTrue();
});

test('non-admin user cannot manage users', function () {
    $user = User::factory()->create(['is_admin' => false]);

    expect($user->can('manage-users'))->toBeFalse();
});

test('admin user can manage settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    expect($admin->can('manage-settings'))->toBeTrue();
});

test('Gate facade allows check returns correct result for admin', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    expect(Gate::forUser($admin)->allows('manage-security'))->toBeTrue()
        ->and(Gate::forUser($admin)->check('manage-users'))->toBeTrue();
});

test('Gate facade denies check returns correct result for non-admin', function () {
    $user = User::factory()->create(['is_admin' => false]);

    expect(Gate::forUser($user)->denies('manage-security'))->toBeTrue()
        ->and(Gate::forUser($user)->denies('manage-users'))->toBeTrue();
});

test('security settings route requires admin permission', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->actingAs($user)
        ->get(route('settings.security'))
        ->assertForbidden();
});

test('admin can access security settings component', function () {
    // This test verifies authorization without rendering the Flux view
    // The full component functionality is tested in SecuritySettingsTest
    $admin = User::factory()->create(['is_admin' => true]);

    $component = new \App\Livewire\Settings\SecuritySettings();

    $this->actingAs($admin);

    // Should be able to mount without exception
    expect(fn() => $component->mount(app(\App\Services\SettingsService::class)))
        ->not->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
