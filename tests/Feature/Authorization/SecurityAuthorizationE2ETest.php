<?php

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('non-admin user is completely blocked from security features', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $settings = app(SettingsService::class);
    $settings->set('security.allowed_domains', ['test.com']);

    // 1. Gate check should fail
    expect($user->can('manage-security'))->toBeFalse();

    // 2. Route access should be forbidden
    $response = $this->actingAs($user)->get(route('settings.security'));
    $response->assertForbidden();

    // 3. Component mount should throw 403
    $component = new \App\Livewire\Settings\SecuritySettings;
    $this->actingAs($user);

    expect(fn () => $component->mount($settings))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    // 4. Navigation item should not appear in blade
    $response = $this->actingAs($user)->get(route('settings.profile'));
    $response->assertDontSee('Security');
});

test('admin user has full access to security features', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $settings = app(SettingsService::class);
    $settings->set('security.allowed_domains', ['test.com']);

    // 1. Gate check should pass
    expect($admin->can('manage-security'))->toBeTrue();

    // 2. Route access should succeed
    $response = $this->actingAs($admin)->get(route('settings.security'));
    $response->assertOk();

    // 3. Component mount should succeed
    $component = new \App\Livewire\Settings\SecuritySettings;
    $this->actingAs($admin);

    expect(fn () => $component->mount($settings))
        ->not->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    // 4. Navigation item should appear in blade
    $response = $this->actingAs($admin)->get(route('settings.profile'));
    $response->assertSee('Security');
});

test('guest user cannot access security features at all', function () {
    // 1. Route should redirect to login
    $response = $this->get(route('settings.security'));
    $response->assertRedirect(route('login'));

    // 2. Component mount should fail (no authenticated user)
    $component = new \App\Livewire\Settings\SecuritySettings;

    expect(fn () => $component->mount(app(SettingsService::class)))
        ->toThrow(\Error::class); // Trying to call ->can() on null
});

test('authorization is enforced on all component actions', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);
    $settings = app(SettingsService::class);

    // Admin can perform actions
    $this->actingAs($admin);
    $component = new \App\Livewire\Settings\SecuritySettings;
    $component->mount($settings);

    $component->newDomain = 'example.com';
    $component->addDomain($settings);
    expect($component->newDomain)->toBe(''); // Should be cleared after adding

    // Non-admin cannot even mount to perform actions
    $this->actingAs($user);
    $component2 = new \App\Livewire\Settings\SecuritySettings;

    expect(fn () => $component2->mount($settings))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('is_admin flag is properly cast to boolean', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    // Should be actual booleans, not strings or integers
    expect($admin->is_admin)->toBeTrue()
        ->and($user->is_admin)->toBeFalse()
        ->and($admin->is_admin)->toBeBool()
        ->and($user->is_admin)->toBeBool();
});

test('multiple authorization layers provide defense in depth', function () {
    // This test documents that we have 4 layers of authorization:
    // 1. Route middleware (can:manage-security)
    // 2. Component mount() check
    // 3. Blade @can directive (hides nav)
    // 4. Laravel Gates

    $user = User::factory()->create(['is_admin' => false]);

    // Even if one layer fails, others should catch it
    // Layer 1: Route middleware
    $response = $this->actingAs($user)->get(route('settings.security'));
    expect($response->status())->toBe(403);

    // Layer 2: Component authorization
    $component = new \App\Livewire\Settings\SecuritySettings;
    $this->actingAs($user);
    expect(fn () => $component->mount(app(SettingsService::class)))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);

    // Layer 3: Blade directive (navigation hidden)
    $response = $this->actingAs($user)->get(route('settings.profile'));
    expect($response->content())->not->toContain('settings/security');

    // Layer 4: Gate check
    expect($user->can('manage-security'))->toBeFalse();
});
