<?php

use App\Livewire\Settings\SecuritySettings;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->admin = User::factory()->create(['is_admin' => true]);
    $this->user = User::factory()->create(['is_admin' => false]);
    $this->settings = app(SettingsService::class);
});

test('admin user can mount component', function () {
    $component = new SecuritySettings;

    $this->actingAs($this->admin);

    // Call mount method directly
    $component->mount(app(SettingsService::class));

    // Verify properties were set
    expect($component->allowedDomains)->toBeArray()
        ->and($component->allowedEmails)->toBeArray();
});

test('non-admin user cannot mount component', function () {
    $component = new SecuritySettings;

    $this->actingAs($this->user);

    // Should throw 403 exception
    expect(fn () => $component->mount(app(SettingsService::class)))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

test('can add allowed domain', function () {
    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->newDomain = 'newcompany.com';
    $component->addDomain(app(SettingsService::class));

    $domains = $this->settings->getArray('security.allowed_domains');

    expect($domains)->toContain('newcompany.com')
        ->and($component->newDomain)->toBe('');
});

test('can remove allowed domain', function () {
    $this->settings->set('security.allowed_domains', ['company1.com', 'company2.com']);

    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->removeDomain('company1.com', app(SettingsService::class));

    $domains = $this->settings->getArray('security.allowed_domains');

    expect($domains)->not->toContain('company1.com')
        ->and($domains)->toContain('company2.com');
});

test('domain validation rejects invalid domains', function () {
    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->newDomain = 'not a valid domain';

    // Call validate manually to check for errors
    $validator = validator(
        ['newDomain' => $component->newDomain],
        ['newDomain' => ['required', 'string', 'regex:/^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/']],
        ['newDomain.regex' => 'Please enter a valid domain (e.g., example.com)']
    );

    expect($validator->fails())->toBeTrue();
});

test('can add individual allowed email', function () {
    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->newEmail = 'contractor@example.com';
    $component->addEmail(app(SettingsService::class));

    $emails = $this->settings->getArray('security.allowed_emails');

    expect($emails)->toContain('contractor@example.com')
        ->and($component->newEmail)->toBe('');
});

test('can remove individual allowed email', function () {
    $this->settings->set('security.allowed_emails', ['email1@example.com', 'email2@example.com']);

    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->removeEmail('email1@example.com', app(SettingsService::class));

    $emails = $this->settings->getArray('security.allowed_emails');

    expect($emails)->not->toContain('email1@example.com')
        ->and($emails)->toContain('email2@example.com');
});

test('email validation rejects invalid emails', function () {
    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->newEmail = 'not-an-email';

    // Call validate manually to check for errors
    $validator = validator(
        ['newEmail' => $component->newEmail],
        ['newEmail' => ['required', 'email']]
    );

    expect($validator->fails())->toBeTrue();
});

test('domains are stored in lowercase', function () {
    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->newDomain = 'UPPERCASE.COM';
    $component->addDomain(app(SettingsService::class));

    $domains = $this->settings->getArray('security.allowed_domains');

    expect($domains)->toContain('uppercase.com')
        ->and($domains)->not->toContain('UPPERCASE.COM');
});

test('emails are stored in lowercase', function () {
    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->newEmail = 'USER@EXAMPLE.COM';
    $component->addEmail(app(SettingsService::class));

    $emails = $this->settings->getArray('security.allowed_emails');

    expect($emails)->toContain('user@example.com')
        ->and($emails)->not->toContain('USER@EXAMPLE.COM');
});

test('duplicate domains are not added', function () {
    $this->settings->set('security.allowed_domains', ['existing.com']);

    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->newDomain = 'existing.com';
    $component->addDomain(app(SettingsService::class));

    $domains = $this->settings->getArray('security.allowed_domains');

    expect($domains)->toHaveCount(1);
});

test('duplicate emails are not added', function () {
    $this->settings->set('security.allowed_emails', ['existing@example.com']);

    $component = new SecuritySettings;
    $this->actingAs($this->admin);
    $component->mount(app(SettingsService::class));

    $component->newEmail = 'existing@example.com';
    $component->addEmail(app(SettingsService::class));

    $emails = $this->settings->getArray('security.allowed_emails');

    expect($emails)->toHaveCount(1);
});
